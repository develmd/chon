<?php
require_once 'config.php';
require_once 'functions.php';

// Простая аутентификация преподавателей
session_start();

// Список преподавателей (в реальном проекте хранить в БД)
$teachers = [
    'ivanov' => ['password' => 'ivanov123', 'name' => 'Иванов А.Б.'],
    'petrov' => ['password' => 'petrov123', 'name' => 'Петров В.Г.'],
    'sidorov' => ['password' => 'sidorov123', 'name' => 'Сидоров Д.Е.'],
    'admin' => ['password' => 'admin123', 'name' => 'Администратор']
];

// Обработка выхода
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: teacher_panel.php');
    exit;
}

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (isset($teachers[$username]) && $teachers[$username]['password'] == $password) {
        $_SESSION['teacher_auth'] = true;
        $_SESSION['teacher_username'] = $username;
        $_SESSION['teacher_name'] = $teachers[$username]['name'];
    } else {
        $error = "Неверный логин или пароль!";
    }
}

// Проверка авторизации
if (!isset($_SESSION['teacher_auth'])) {
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Вход для преподавателя</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
        <style>
            body { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); min-height: 100vh; }
            .card { border-radius: 20px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); }
        </style>
    </head>
    <body class="d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="card">
                        <div class="card-header bg-primary text-white text-center">
                            <h5><i class="fas fa-chalkboard-teacher"></i> Вход в панель преподавателя</h5>
                        </div>
                        <div class="card-body">
                            <?php if (isset($error)): ?>
                                <div class="alert alert-danger"><?= $error ?></div>
                            <?php endif; ?>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Логин</label>
                                    <input type="text" name="username" class="form-control" placeholder="Введите логин" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Пароль</label>
                                    <input type="password" name="password" class="form-control" placeholder="Введите пароль" required>
                                </div>
                                <button type="submit" name="login" class="btn btn-primary w-100">
                                    <i class="fas fa-sign-in-alt"></i> Войти
                                </button>
                            </form>
                            <hr><!--
                            <div class="text-center">
                                <small class="text-muted">Тестовые преподаватели:</small><br>
                                <small class="text-muted">
                                    <strong>Иванов А.Б.</strong> - логин: ivanov, пароль: ivanov123<br>
                                    <strong>Петров В.Г.</strong> - логин: petrov, пароль: petrov123<br>
                                    <strong>Сидоров Д.Е.</strong> - логин: sidorov, пароль: sidorov123
                                </small>
                            </div>-->
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// ============ ИСПРАВЛЕННЫЕ SQL ЗАПРОСЫ (без AS) ============

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
            active_sessions.created_at,
            schedule.discipline,
            schedule.teacher,
            schedule.classroom,
            schedule.start_time,
            qr_codes.date as qr_date
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
    // Таблица может не существовать
    $active_sessions = [];
}

// Получаем свежие отметки за сегодня
$today_attendance = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            attendance.id,
            attendance.student_id,
            attendance.schedule_id,
            attendance.scan_time,
            attendance.status,
            attendance.is_confirmed,
            students.full_name,
            students.group_name,
            schedule.discipline
        FROM attendance
        INNER JOIN students ON attendance.student_id = students.id
        INNER JOIN schedule ON attendance.schedule_id = schedule.id
        WHERE DATE(attendance.scan_time) = date('now')
        ORDER BY attendance.scan_time DESC
    ");
    $stmt->execute();
    $today_attendance = $stmt->fetchAll();
} catch (PDOException $e) {
    $today_attendance = [];
}

// Статистика
$total_present = 0;
$total_late = 0;
foreach ($today_attendance as $att) {
    if ($att['status'] == 'present') {
        $total_present++;
    } else {
        $total_late++;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKON - Панель преподавателя</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta http-equiv="refresh" content="15">
    <style>
        .qr-card { transition: transform 0.3s; }
        .qr-card:hover { transform: translateY(-5px); }
        .stat-card { border-radius: 15px; transition: all 0.3s; }
        .stat-card:hover { transform: scale(1.02); }
    </style>
</head>
<body>
    <div class="container mt-4">
        <!-- Шапка -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap">
            <div>
                <h1><i class="fas fa-chalkboard-teacher text-primary"></i> Панель преподавателя</h1>
                <p class="text-muted">Добро пожаловать, <strong><?= htmlspecialchars($_SESSION['teacher_name']) ?></strong>!</p>
            </div>
            <div>
                <span class="badge bg-info me-2 py-2 px-3">
                    <i class="fas fa-sync-alt"></i> Обновление каждые 15 сек
                </span>
                <a href="?logout=1" class="btn btn-outline-danger">
                    <i class="fas fa-sign-out-alt"></i> Выйти
                </a>
            </div>
        </div>
        
        <!-- Статистика -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0">Всего отметок</h6>
                                <h2 class="mb-0"><?= count($today_attendance) ?></h2>
                            </div>
                            <i class="fas fa-clipboard-list fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0">Вовремя</h6>
                                <h2 class="mb-0"><?= $total_present ?></h2>
                            </div>
                            <i class="fas fa-check-circle fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-warning text-dark">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0">Опоздали</h6>
                                <h2 class="mb-0"><?= $total_late ?></h2>
                            </div>
                            <i class="fas fa-clock fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="mb-0">Активных QR</h6>
                                <h2 class="mb-0"><?= count($active_sessions) ?></h2>
                            </div>
                            <i class="fas fa-qrcode fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Активные QR-сессии -->
        <div class="card mb-4">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0"><i class="fas fa-qrcode"></i> Активные QR-коды для сканирования</h5>
            </div>
            <div class="card-body">
                <?php if (count($active_sessions) > 0): ?>
                    <div class="row">
                        <?php foreach ($active_sessions as $session): ?>
                            <div class="col-md-4 col-lg-3 mb-3">
                                <div class="card qr-card h-100">
                                    <div class="card-body text-center">
                                        <?php
                                        $qr_filename = 'qr_' . $session['schedule_id'] . '_' . $session['qr_date'] . '_' . $session['session_token'] . '.png';
                                        $qr_filepath = QR_DIR . '/' . $qr_filename;
                                        ?>
                                        <div class="bg-light p-2 rounded mb-2">
                                            <?php if (file_exists($qr_filepath) && filesize($qr_filepath) > 0): ?>
                                                <img src="qrcodes/<?= $qr_filename ?>?t=<?= time() ?>" alt="QR Code" style="width: 120px; height: 120px; object-fit: contain;">
                                            <?php else: ?>
                                                <div class="bg-secondary text-white p-3 rounded" style="width: 120px; height: 120px; display: inline-flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-qrcode fa-4x"></i>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <h6 class="mb-1"><?= htmlspecialchars($session['discipline']) ?></h6>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-clock"></i> <?= $session['start_time'] ?>
                                        </small>
                                        <small class="text-muted d-block">
                                            <i class="fas fa-door-open"></i> Ауд. <?= htmlspecialchars($session['classroom']) ?>
                                        </small>
                                        <hr class="my-2">
                                        <small class="text-danger">
                                            <i class="fas fa-hourglass-half"></i> До: <?= date('H:i:s', strtotime($session['expires_at'])) ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> Нет активных QR-сессий. 
                        <a href="generate_qr.php" class="alert-link">Сгенерируйте QR-код для занятия</a>
                    </div>
                <?php endif; ?>
                
                <div class="text-center mt-3">
                    <a href="generate_qr.php" class="btn btn-primary">
                        <i class="fas fa-plus-circle"></i> Создать новую сессию
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Сегодняшние отметки -->
        <div class="card">
            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Сегодняшние отметки</h5>
                <span class="badge bg-light text-dark"><?= count($today_attendance) ?> записей</span>
            </div>
            <div class="card-body">
                <?php if (count($today_attendance) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th><i class="fas fa-clock"></i> Время</th>
                                    <th><i class="fas fa-user"></i> Студент</th>
                                    <th><i class="fas fa-users"></i> Группа</th>
                                    <th><i class="fas fa-book"></i> Дисциплина</th>
                                    <th><i class="fas fa-tag"></i> Статус</th>
                                    <th><i class="fas fa-check-circle"></i> Подтверждение</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($today_attendance as $att): ?>
                                    <tr>
                                        <td><?= date('H:i:s', strtotime($att['scan_time'])) ?></td>
                                        <td><strong><?= htmlspecialchars($att['full_name']) ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($att['group_name']) ?></span></td>
                                        <td><?= htmlspecialchars($att['discipline']) ?></td>
                                        <td>
                                            <?php if ($att['status'] == 'present'): ?>
                                                <span class="badge bg-success"><i class="fas fa-check"></i> Вовремя</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> Опоздал</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($att['is_confirmed']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle"></i> Подтверждено
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    <i class="fas fa-hourglass"></i> Ожидает
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> Сегодня пока нет отметок. Студенты ещё не сканировали QR-код.
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Быстрые ссылки -->
        <div class="row mt-4">
            <div class="col-md-4">
                <a href="generate_qr.php" class="text-decoration-none">
                    <div class="card text-center bg-primary text-white">
                        <div class="card-body">
                            <i class="fas fa-qrcode fa-2x"></i>
                            <h6 class="mt-2">Сгенерировать QR</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="attendance.php" class="text-decoration-none">
                    <div class="card text-center bg-success text-white">
                        <div class="card-body">
                            <i class="fas fa-clipboard-list fa-2x"></i>
                            <h6 class="mt-2">Вся посещаемость</h6>
                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-4">
                <a href="reports.php" class="text-decoration-none">
                    <div class="card text-center bg-warning text-dark">
                        <div class="card-body">
                            <i class="fas fa-chart-bar fa-2x"></i>
                            <h6 class="mt-2">Отчёты</h6>
                        </div>
                    </div>
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>