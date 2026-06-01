<?php
require_once 'config.php';
require_once 'functions.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';
$student = null;

if (isset($_SESSION['student_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$_SESSION['student_id']]);
    $student = $stmt->fetch();
}

if (!$student) {
    session_destroy();
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['scan_qr'])) {
    try {
        $token = trim($_POST['token']);
        $student_id = $_SESSION['student_id'] ?? null;
        
        if (!$student_id) {
            $error = "Ошибка: студент не найден!";
        } else {
            $qr_info = getQRCodeInfo($token);
            
            if (!$qr_info) {
                $error = "Недействительный QR-код!";
            } else {
                $stmt = $pdo->prepare("
                    SELECT * FROM attendance 
                    WHERE student_id = ? AND schedule_id = ? AND qr_code_id = ?
                ");
                $stmt->execute([$student_id, $qr_info['schedule_id'], $qr_info['id']]);
                
                if ($stmt->fetch()) {
                    $error = "Вы уже отметились на это занятие!";
                } else {
                    $scan_time = date('Y-m-d H:i:s');
                    $status = checkLateness($scan_time, $qr_info['start_time']);
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance (student_id, schedule_id, qr_code_id, scan_time, status, is_confirmed)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([$student_id, $qr_info['schedule_id'], $qr_info['id'], $scan_time, $status]);
                    $attendance_id = $pdo->lastInsertId();
                    
                    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                    $stmt->execute([$student_id]);
                    $student_data = $stmt->fetch();
                    
                    if ($student_data['parent_email']) {
                        sendNotification(
                            $student_id,
                            $attendance_id,
                            $student_data['full_name'],
                            $student_data['parent_email'],
                            $qr_info['discipline'],
                            $qr_info['date'],
                            $status,
                            $scan_time
                        );
                    }
                    
                    $status_text = $status == 'present' ? 'вовремя' : 'с опозданием';
                    $message = "✅ Отметка успешно зафиксирована! Вы пришли {$status_text}.";
                    
                    notifyTeacher($qr_info['schedule_id'], $student_data['full_name'], $status);
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Ошибка: " . $e->getMessage();
    }
}

$student_schedule = [];
if ($student) {
    $current_day = date('N');
    $stmt = $pdo->prepare("
        SELECT s.*, 
               CASE WHEN q.id IS NOT NULL THEN 1 ELSE 0 END as qr_exists
        FROM schedule s
        LEFT JOIN qr_codes q ON q.schedule_id = s.id AND q.date = date('now')
        WHERE s.day_of_week = ?
        ORDER BY s.start_time
    ");
    $stmt->execute([$current_day]);
    $student_schedule = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>CHECKON - Сканирование QR-кода</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; }
        .card { background: #0f0f1a; border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.3); }
        .btn-scan { background: linear-gradient(45deg, #667eea, #764ba2); color: white; border: none; }
        #qr-reader { width: 100%; max-width: 500px; margin: 0 auto; }
        #qr-reader video { border-radius: 20px; }
        .text-white, h1, h2, h3, h4, h5, p { color: #eee !important; }
        .text-muted { color: #888 !important; }
        .list-group-item { background: #1a1a2e; color: #eee; border-color: #333; }
        .alert-light { background: #1a1a2e; color: #eee; border: 1px solid #333; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4 mb-4">
        <div class="text-center mb-4">
            <i class="fas fa-check-circle fa-3x" style="color: #667eea;"></i>
            <h1 class="mt-2">CHECKON</h1>
            <p class="lead">Система контроля посещаемости</p>
        </div>
        
        <?php if ($student): ?>
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h4>👋 Здравствуйте, <?= htmlspecialchars(explode(' ', $student['full_name'])[0]) ?>!</h4>
                        <p class="text-muted mb-0">Группа: <?= htmlspecialchars($student['group_name']) ?></p>
                    </div>
                    <a href="logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt"></i> Выйти
                    </a>
                </div>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-header" style="background: linear-gradient(45deg, #17a2b8, #6f42c1);">
                <h5 class="mb-0"><i class="fas fa-calendar-day"></i> Расписание на сегодня</h5>
            </div>
            <div class="card-body">
                <?php if (count($student_schedule) > 0): ?>
                    <div class="list-group">
                        <?php foreach ($student_schedule as $lesson): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong><?= htmlspecialchars($lesson['discipline']) ?></strong>
                                        <br>
                                        <small class="text-muted">
                                            <?= $lesson['start_time'] ?> - <?= $lesson['end_time'] ?> | 
                                            Ауд. <?= htmlspecialchars($lesson['classroom']) ?>
                                        </small>
                                    </div>
                                    <?php if ($lesson['qr_exists']): ?>
                                        <span class="badge bg-success">QR готов</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">QR не активен</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">Сегодня занятий нет 🎉</div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header" style="background: linear-gradient(45deg, #667eea, #764ba2);">
                <h5 class="mb-0"><i class="fas fa-camera"></i> Отметка посещаемости</h5>
            </div>
            <div class="card-body text-center">
                <p class="text-muted">Наведите камеру на QR-код, показанный преподавателем</p>
                
                <div id="qr-reader"></div>
                
                <button id="startScanBtn" class="btn btn-scan btn-lg mt-3">
                    <i class="fas fa-play"></i> Запустить камеру
                </button>
                <button id="stopScanBtn" class="btn btn-danger btn-lg mt-3" style="display:none;">
                    <i class="fas fa-stop"></i> Остановить
                </button>
                
                <hr>
                
                <p class="text-muted small">или введите код вручную:</p>
                <form method="POST" class="mt-2">
                    <div class="input-group">
                        <input type="text" class="form-control" name="token" id="tokenInput" 
                               placeholder="Введите код из QR" required>
                        <button type="submit" name="scan_qr" class="btn btn-success">
                            <i class="fas fa-check"></i> Подтвердить
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="alert alert-light mt-4">
            <h6><i class="fas fa-info-circle"></i> Как это работает:</h6>
            <ol class="small mb-0">
                <li>Преподаватель показывает QR-код на экране</li>
                <li>Вы сканируете код через камеру телефона</li>
                <li>Система автоматически отмечает ваше присутствие</li>
                <li>Родитель получает уведомление (вовремя/опоздал)</li>
                <li>Преподаватель видит подтверждение на своем экране</li>
            </ol>
        </div>
        <?php endif; ?>
    </div>
    
    <script>
        let html5QrCode;
        let isScanning = false;
        
        const startBtn = document.getElementById('startScanBtn');
        const stopBtn = document.getElementById('stopScanBtn');
        
        if (startBtn) {
            startBtn.addEventListener('click', function() {
                if (!isScanning) {
                    html5QrCode = new Html5Qrcode("qr-reader");
                    const qrCodeSuccessCallback = (decodedText, decodedResult) => {
                        document.getElementById('tokenInput').value = decodedText;
                        setTimeout(() => {
                            const form = document.querySelector('form');
                            if (form) form.submit();
                        }, 500);
                        if (html5QrCode) {
                            html5QrCode.stop();
                        }
                        isScanning = false;
                        if (startBtn) startBtn.style.display = 'inline-block';
                        if (stopBtn) stopBtn.style.display = 'none';
                    };
                    
                    html5QrCode.start(
                        { facingMode: "environment" },
                        { fps: 10, qrbox: { width: 250, height: 250 } },
                        qrCodeSuccessCallback
                    ).then(() => {
                        isScanning = true;
                        if (startBtn) startBtn.style.display = 'none';
                        if (stopBtn) stopBtn.style.display = 'inline-block';
                    }).catch(err => {
                        console.error("Ошибка: ", err);
                        alert("Не удалось получить доступ к камере. Проверьте разрешения.");
                    });
                }
            });
        }
        
        if (stopBtn) {
            stopBtn.addEventListener('click', function() {
                if (html5QrCode && isScanning) {
                    html5QrCode.stop();
                    isScanning = false;
                    if (startBtn) startBtn.style.display = 'inline-block';
                    if (stopBtn) stopBtn.style.display = 'none';
                }
            });
        }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>