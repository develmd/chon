<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'init.php';
session_start();

$message = '';
$generated_qr = null;

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
    $message = "Ошибка загрузки расписания: " . $e->getMessage();
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
            $qr_token = $token;
        } else {
            $qr_code_id = $qr_code['id'];
            $qr_token = $qr_code['token'];
        }
        
        $session_token = generateToken();
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$expires_minutes} minutes"));
        
        $stmt = $pdo->prepare("
            INSERT INTO active_sessions (schedule_id, qr_code_id, session_token, is_active, expires_at)
            VALUES (?, ?, ?, 1, ?)
        ");
        $stmt->execute([$schedule_id, $qr_code_id, $session_token, $expires_at]);
        
        $qr_data = $session_token;
        
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
            active_sessions.created_at,
            schedule.discipline,
            schedule.teacher,
            schedule.classroom,
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
        .btn-primary:hover { transform: scale(1.02); transition: all 0.3s; }
        .btn-success { background: linear-gradient(45deg, #28a745, #20c997); border: none; }
        .btn-warning { background: linear-gradient(45deg, #ffc107, #fd7e14); border: none; color: #000; }
        .alert-info { background: #1a1a2e; border: 1px solid #17a2b8; color: #eee; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1><i class="fas fa-qrcode" style="color: #667eea;"></i> Генерация QR-кодов для занятий</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus-circle"></i> Создать QR-код для занятия</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Выберите занятие *</label>
                                <select class="form-select" name="schedule_id" required>
                                    <option value="">-- Выберите занятие --</option>
                                    <?php foreach ($schedule_list as $s): ?>
                                        <option value="<?= $s['id'] ?>">
                                            <?= $s['day_name'] ?>, <?= $s['start_time'] ?> - <?= htmlspecialchars($s['discipline']) ?> 
                                            (<?= htmlspecialchars($s['teacher']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Дата занятия *</label>
                                <input type="date" class="form-control" name="date" required value="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Время действия QR-кода (минут)</label>
                                <input type="number" class="form-control" name="expires_minutes" value="30" min="5" max="180">
                                <small class="text-muted">Через это время QR-код станет недействительным</small>
                            </div>
                            <button type="submit" name="generate" class="btn btn-primary w-100">
                                <i class="fas fa-qrcode"></i> Сгенерировать QR-код
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php if ($generated_qr): ?>
                <div class="col-md-7">
                    <div class="card border-success">
                        <div class="card-header" style="background: linear-gradient(45deg, #28a745, #20c997);">
                            <h5 class="mb-0">✅ QR-код готов к использованию!</h5>
                        </div>
                        <div class="card-body text-center">
                            <?php 
                            $qr_path = 'qrcodes/' . $generated_qr['qr_file'];
                            if (file_exists(QR_DIR . '/' . $generated_qr['qr_file'])): 
                            ?>
                                <img src="<?= $qr_path ?>?t=<?= time() ?>" alt="QR Code" class="img-fluid" style="max-width: 250px; border: 5px solid #fff; box-shadow: 0 0 20px rgba(0,0,0,0.2); border-radius: 10px;">
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-spinner fa-spin"></i> QR-код генерируется...
                                </div>
                            <?php endif; ?>
                            <hr>
                            <p><strong>📚 Занятие:</strong> <?= htmlspecialchars($generated_qr['schedule']['discipline']) ?></p>
                            <p><strong>👨‍🏫 Преподаватель:</strong> <?= htmlspecialchars($generated_qr['schedule']['teacher']) ?></p>
                            <p><strong>🏢 Аудитория:</strong> <?= htmlspecialchars($generated_qr['schedule']['classroom']) ?></p>
                            <p><strong>⏰ Время:</strong> <?= $generated_qr['schedule']['start_time'] ?> - <?= $generated_qr['schedule']['end_time'] ?></p>
                            <p><strong>⌛ Действителен до:</strong> <?= $generated_qr['expires_at'] ?></p>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Покажите этот QR-код студентам. Они отсканируют его своими телефонами.
                            </div>
                            <div class="d-grid gap-2">
                                <a href="teacher_panel.php" class="btn btn-primary">
                                    <i class="fas fa-chalkboard-teacher"></i> Перейти в панель преподавателя
                                </a>
                                <a href="generate_qr.php" class="btn btn-secondary">Сгенерировать еще</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (count($active_sessions) > 0): ?>
        <div class="card mt-4">
            <div class="card-header" style="background: linear-gradient(45deg, #ffc107, #fd7e14);">
                <h5 class="mb-0"><i class="fas fa-clock"></i> Активные QR-сессии</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th>Занятие</th>
                                <th>Дата</th>
                                <th>Время</th>
                                <th>Действителен до</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($active_sessions as $session): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($session['discipline']) ?></strong><br><small><?= htmlspecialchars($session['teacher']) ?></small></td>
                                    <td><?= $session['date'] ?></td>
                                    <td><?= $session['start_time'] ?></td>
                                    <td><?= $session['expires_at'] ?></td>
                                    <td><span class="badge bg-success"><i class="fas fa-check-circle"></i> Активен</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="alert alert-secondary mt-4" style="background: #1a1a2e; border: 1px solid #333;">
            <h6><i class="fas fa-lightbulb"></i> Инструкция по использованию:</h6>
            <ol class="small mb-0">
                <li>Выберите занятие из расписания</li>
                <li>Укажите дату занятия</li>
                <li>Нажмите "Сгенерировать QR-код"</li>
                <li>Покажите QR-код на экране или распечатайте</li>
                <li>Студенты сканируют QR-код своими телефонами</li>
                <li>В панели преподавателя вы видите отметки в реальном времени</li>
            </ol>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>