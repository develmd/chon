<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once 'config.php';
require_once 'functions.php';
require_once 'init.php';
require_once 'settings.php';
session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'teacher' && $_SESSION['role'] !== 'admin')) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';
$generated_qr = null;

// Заморозить сессию
if (isset($_POST['freeze_session'])) {
    $session_id = $_POST['session_id'];
    try {
        $stmt = $pdo->prepare("UPDATE active_sessions SET is_active = 0 WHERE id = ?");
        $stmt->execute([$session_id]);
        $message = "❄️ QR-сессия заморожена!";
    } catch (PDOException $e) {
        $error = "Ошибка: " . $e->getMessage();
    }
}

// Разморозить сессию
if (isset($_POST['unfreeze_session'])) {
    $session_id = $_POST['session_id'];
    try {
        $stmt = $pdo->prepare("SELECT expires_at FROM active_sessions WHERE id = ?");
        $stmt->execute([$session_id]);
        $session = $stmt->fetch();
        
        if (strtotime($session['expires_at']) > time()) {
            $stmt = $pdo->prepare("UPDATE active_sessions SET is_active = 1 WHERE id = ?");
            $stmt->execute([$session_id]);
            $message = "🔥 QR-сессия разморожена!";
        } else {
            $error = "❌ Нельзя разморозить - время действия истекло!";
        }
    } catch (PDOException $e) {
        $error = "Ошибка: " . $e->getMessage();
    }
}

// Полное удаление QR-кода
if (isset($_POST['delete_qr_code'])) {
    $qr_code_id = $_POST['qr_code_id'];
    try {
        $stmt = $pdo->prepare("SELECT schedule_id, date, token FROM qr_codes WHERE id = ?");
        $stmt->execute([$qr_code_id]);
        $qr = $stmt->fetch();
        
        if ($qr) {
            $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE qr_code_id = ?");
            $stmt->execute([$qr_code_id]);
            
            $qr_filename = 'qr_' . $qr['schedule_id'] . '_' . $qr['date'] . '_' . $qr['token'] . '.png';
            $qr_filepath = QR_DIR . '/' . $qr_filename;
            if (file_exists($qr_filepath)) {
                unlink($qr_filepath);
            }
            
            $stmt = $pdo->prepare("DELETE FROM qr_codes WHERE id = ?");
            $stmt->execute([$qr_code_id]);
            
            $message = "🗑️ QR-код полностью удалён!";
        }
    } catch (PDOException $e) {
        $error = "Ошибка: " . $e->getMessage();
    }
}

try {
    $stmt = $pdo->query("
        SELECT 
            schedule.id,
            schedule.discipline,
            schedule.teacher,
            schedule.classroom,
            schedule.day_of_week,
            schedule.start_time,
            schedule.end_time,
            CASE schedule.day_of_week 
                WHEN 1 THEN 'Пн' 
                WHEN 2 THEN 'Вт' 
                WHEN 3 THEN 'Ср'
                WHEN 4 THEN 'Чт' 
                WHEN 5 THEN 'Пт' 
                WHEN 6 THEN 'Сб' 
                ELSE 'Вс'
            END as day_name
        FROM schedule
        ORDER BY schedule.day_of_week, schedule.start_time
    ");
    $schedule_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $schedule_list = [];
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['generate'])) {
    try {
        $schedule_id = $_POST['schedule_id'];
        $date = $_POST['date'];
        $expires_minutes = $_POST['expires_minutes'] ?? 30;
        
        $stmt = $pdo->prepare("SELECT * FROM schedule WHERE id = ?");
        $stmt->execute([$schedule_id]);
        $schedule = $stmt->fetch();
        
        if (!$schedule) {
            throw new Exception("Занятие не найдено!");
        }
        
        $stmt = $pdo->prepare("SELECT * FROM qr_codes WHERE schedule_id = ? AND date = ?");
        $stmt->execute([$schedule_id, $date]);
        $qr_code = $stmt->fetch();
        
        if (!$qr_code) {
            $token = generateToken();
            $stmt = $pdo->prepare("
                INSERT INTO qr_codes (schedule_id, date, token, generated_at)
                VALUES (?, ?, ?, datetime('now'))
            ");
            $stmt->execute([$schedule_id, $date, $token]);
            $qr_code_id = $pdo->lastInsertId();
        } else {
            $qr_code_id = $qr_code['id'];
        }
        
        $session_token = generateToken();
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_minutes} minutes"));
        
        $stmt = $pdo->prepare("
            INSERT INTO active_sessions (schedule_id, qr_code_id, session_token, is_active, expires_at)
            VALUES (?, ?, ?, 1, ?)
        ");
        $stmt->execute([$schedule_id, $qr_code_id, $session_token, $expires_at]);
        
        $qr_data = getFullUrl('scan.php?token=' . urlencode($session_token));
        
        $qr_filename = 'qr_' . $schedule_id . '_' . $date . '_' . $session_token . '.png';
        $qr_filepath = QR_DIR . '/' . $qr_filename;
        
        require_once 'phpqrcode.php';
        QRcode::png($qr_data, $qr_filepath, QR_ECLEVEL_L, 15);
        
        $generated_qr = [
            'schedule' => $schedule,
            'qr_file' => $qr_filename,
            'expires_at' => $expires_at,
            'session_token' => $session_token
        ];
        
        $message = "✅ QR-код успешно сгенерирован! Действителен до: {$expires_at}";
        
    } catch (Exception $e) {
        $message = "❌ Ошибка: " . $e->getMessage();
    }
}

// Получаем активные сессии
$active_sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            active_sessions.id,
            active_sessions.schedule_id,
            active_sessions.qr_code_id,
            active_sessions.session_token,
            active_sessions.is_active,
            active_sessions.expires_at,
            schedule.discipline,
            schedule.teacher,
            schedule.start_time,
            qr_codes.date
        FROM active_sessions
        INNER JOIN schedule ON active_sessions.schedule_id = schedule.id
        INNER JOIN qr_codes ON active_sessions.qr_code_id = qr_codes.id
        WHERE active_sessions.is_active = 1 
        AND active_sessions.expires_at > datetime('now')
        ORDER BY active_sessions.created_at DESC
    ");
    $stmt->execute();
    $active_sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    $active_sessions = [];
}

// Получаем замороженные сессии
$frozen_sessions = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            active_sessions.id,
            active_sessions.schedule_id,
            active_sessions.qr_code_id,
            active_sessions.session_token,
            active_sessions.is_active,
            active_sessions.expires_at,
            schedule.discipline,
            schedule.teacher,
            schedule.start_time,
            qr_codes.date
        FROM active_sessions
        INNER JOIN schedule ON active_sessions.schedule_id = schedule.id
        INNER JOIN qr_codes ON active_sessions.qr_code_id = qr_codes.id
        WHERE active_sessions.is_active = 0 
        AND active_sessions.expires_at > datetime('now')
        ORDER BY active_sessions.created_at DESC
    ");
    $stmt->execute();
    $frozen_sessions = $stmt->fetchAll();
} catch (PDOException $e) {
    $frozen_sessions = [];
}

// Получаем все QR-коды (без активных сессий)
$all_qr_codes = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            qr_codes.id,
            qr_codes.schedule_id,
            qr_codes.date,
            qr_codes.generated_at,
            schedule.discipline,
            schedule.teacher,
            schedule.start_time,
            CASE 
                WHEN EXISTS (SELECT 1 FROM active_sessions WHERE active_sessions.qr_code_id = qr_codes.id AND active_sessions.expires_at > datetime('now'))
                THEN 1 ELSE 0 
            END as has_active_session
        FROM qr_codes
        INNER JOIN schedule ON qr_codes.schedule_id = schedule.id
        WHERE NOT EXISTS (SELECT 1 FROM active_sessions WHERE active_sessions.qr_code_id = qr_codes.id AND active_sessions.expires_at > datetime('now'))
        ORDER BY qr_codes.generated_at DESC
    ");
    $stmt->execute();
    $all_qr_codes = $stmt->fetchAll();
} catch (PDOException $e) {
    $all_qr_codes = [];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKON - Генерация QR-кодов</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; }
        .card { background: #0f0f1a; border: none; border-radius: 15px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .card-header { border-bottom: 1px solid #333; background: transparent; }
        .text-white, h1, h2, h3, h4, h5, h6, p, label { color: #eee !important; }
        .text-muted { color: #888 !important; }
        .table { color: #eee; }
        .table-striped>tbody>tr:nth-of-type(odd) { background-color: rgba(255,255,255,0.05); }
        .form-control, .form-select { background: #1a1a2e; border: 1px solid #333; color: #eee; }
        .form-control:focus, .form-select:focus { background: #1a1a2e; color: #eee; }
        .btn-primary { background: linear-gradient(45deg, #667eea, #764ba2); border: none; }
        .btn-warning { background: linear-gradient(45deg, #ffc107, #fd7e14); border: none; color: #000; }
        .btn-info { background: linear-gradient(45deg, #17a2b8, #6f42c1); border: none; }
        .btn-danger { background: linear-gradient(45deg, #dc3545, #c82333); border: none; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1><i class="fas fa-qrcode" style="color: #667eea;"></i> Генерация QR-кодов для занятий</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus-circle"></i> Создать QR-код</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Занятие *</label>
                                <select class="form-select" name="schedule_id" required>
                                    <option value="">-- Выберите --</option>
                                    <?php foreach ($schedule_list as $s): ?>
                                        <option value="<?= $s['id'] ?>">
                                            <?= $s['day_name'] ?>, <?= $s['start_time'] ?> - <?= htmlspecialchars($s['discipline']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Дата *</label>
                                <input type="date" class="form-control" name="date" required value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Время действия (минут)</label>
                                <input type="number" class="form-control" name="expires_minutes" value="30" min="5" max="180">
                            </div>
                            <button type="submit" name="generate" class="btn btn-primary w-100">
                                <i class="fas fa-qrcode"></i> Сгенерировать
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php if ($generated_qr): ?>
                <div class="col-md-7">
                    <div class="card">
                        <div class="card-header" style="background: linear-gradient(45deg, #28a745, #20c997);">
                            <h5>✅ QR-код готов!</h5>
                        </div>
                        <div class="card-body text-center">
                            <img src="qrcodes/<?= $generated_qr['qr_file'] ?>?t=<?= time() ?>" class="img-fluid" style="max-width: 200px;">
                            <hr>
                            <p><strong><?= htmlspecialchars($generated_qr['schedule']['discipline']) ?></strong></p>
                            <p><?= $generated_qr['schedule']['teacher'] ?> | Ауд. <?= $generated_qr['schedule']['classroom'] ?></p>
                            <p>⏰ <?= $generated_qr['schedule']['start_time'] ?> - <?= $generated_qr['schedule']['end_time'] ?></p>
                            <p>⌛ Действителен до: <?= $generated_qr['expires_at'] ?></p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (count($active_sessions) > 0): ?>
        <div class="card mt-4">
            <div class="card-header" style="background: linear-gradient(45deg, #28a745, #20c997);">
                <h5>🟢 Активные QR-сессии</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead class="table-dark">
                        <tr><th>Занятие</th><th>Дата</th><th>Время</th><th>Действителен до</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($active_sessions as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['discipline']) ?></strong><br><small><?= htmlspecialchars($s['teacher']) ?></small></td>
                            <td><?= $s['date'] ?></td>
                            <td><?= $s['start_time'] ?></td>
                            <td><?= $s['expires_at'] ?></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                    <button type="submit" name="freeze_session" class="btn btn-sm btn-warning">❄️ Заморозить</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (count($frozen_sessions) > 0): ?>
        <div class="card mt-4">
            <div class="card-header" style="background: linear-gradient(45deg, #ffc107, #fd7e14);">
                <h5>❄️ Замороженные сессии</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead class="table-dark">
                        <tr><th>Занятие</th><th>Дата</th><th>Время</th><th>Действителен до</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($frozen_sessions as $s): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($s['discipline']) ?></strong><br><small><?= htmlspecialchars($s['teacher']) ?></small></td>
                            <td><?= $s['date'] ?></td>
                            <td><?= $s['start_time'] ?></td>
                            <td><?= $s['expires_at'] ?></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                    <button type="submit" name="unfreeze_session" class="btn btn-sm btn-info">🔥 Разморозить</button>
                                </form>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="qr_code_id" value="<?= $s['qr_code_id'] ?>">
                                    <button type="submit" name="delete_qr_code" class="btn btn-sm btn-danger">🗑️ Удалить</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if (count($all_qr_codes) > 0): ?>
        <div class="card mt-4">
            <div class="card-header" style="background: linear-gradient(45deg, #6c757d, #495057);">
                <h5>📦 Архив QR-кодов</h5>
            </div>
            <div class="card-body">
                <table class="table">
                    <thead class="table-dark">
                        <tr><th>Занятие</th><th>Дата</th><th>Время</th><th>Дата генерации</th><th>Статус</th><th>Действия</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($all_qr_codes as $qr): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($qr['discipline']) ?></strong><br><small><?= htmlspecialchars($qr['teacher']) ?></small></td>
                            <td><?= $qr['date'] ?></td>
                            <td><?= $qr['start_time'] ?></td>
                            <td><?= date('d.m.Y H:i', strtotime($qr['generated_at'])) ?></td>
                            <td><span class="badge bg-secondary">Истек</span></td>
                            <td>
                                <form method="POST" style="display:inline">
                                    <input type="hidden" name="qr_code_id" value="<?= $qr['id'] ?>">
                                    <button type="submit" name="delete_qr_code" class="btn btn-sm btn-danger" onclick="return confirm('Удалить навсегда?')">🗑️ Удалить</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
