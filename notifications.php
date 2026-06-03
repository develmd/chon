<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'init.php';
session_start();

$notifications = $pdo->query("
    SELECT n.*, s.full_name, s.group_name, s.parent_email, s.telegram_id,
           a.status as attendance_status, a.scan_time,
           sc.discipline
    FROM notifications_log n
    JOIN students s ON n.student_id = s.id
    JOIN attendance a ON n.attendance_id = a.id
    JOIN schedule sc ON a.schedule_id = sc.id
    ORDER BY n.sent_at DESC
")->fetchAll();

$success = count(array_filter($notifications, function($n) { return $n['status'] == 'sent'; }));
$failed = count(array_filter($notifications, function($n) { return $n['status'] == 'failed'; }));
$unique_students = count(array_unique(array_column($notifications, 'student_id')));
$telegram_count = count(array_filter($notifications, function($n) { 
    return strpos($n['sent_via'], 'telegram') !== false; 
}));
$email_count = count(array_filter($notifications, function($n) { 
    return strpos($n['sent_via'], 'email') !== false; 
}));
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKON - Лог уведомлений</title>
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
        .alert-secondary { background: #1a1a2e; border: 1px solid #333; color: #eee; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1><i class="fas fa-bell" style="color: #667eea;"></i> Лог уведомлений</h1>
        <p class="text-muted">История отправки уведомлений родителям (Telegram + Email)</p>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="card-body">
                        <h5 class="card-title">Успешно отправлено</h5>
                        <h2><?= $success ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white" style="background: linear-gradient(135deg, #dc3545, #c82333);">
                    <div class="card-body">
                        <h5 class="card-title">Ошибок отправки</h5>
                        <h2><?= $failed ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white" style="background: linear-gradient(135deg, #0088cc, #006699);">
                    <div class="card-body">
                        <h5 class="card-title">Telegram</h5>
                        <h2><?= $telegram_count ?></h2>
                        <small>через бота @ChonRobot</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white" style="background: linear-gradient(135deg, #17a2b8, #6f42c1);">
                    <div class="card-body">
                        <h5 class="card-title">Email</h5>
                        <h2><?= $email_count ?></h2>
                        <small>(имитация)</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Детальный лог уведомлений</h5>
            </div>
            <div class="card-body">
                <?php if (count($notifications) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Дата отправки</th>
                                    <th>Студент</th>
                                    <th>Группа</th>
                                    <th>Контакт</th>
                                    <th>Занятие</th>
                                    <th>Статус отметки</th>
                                    <th>Способ</th>
                                    <th>Результат</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $n): ?>
                                    <tr>
                                        <td><?= date('d.m.Y H:i:s', strtotime($n['sent_at'])) ?></td>
                                        <td><strong><?= htmlspecialchars($n['full_name']) ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($n['group_name']) ?></span></td>
                                        <td>
                                            <?php if (!empty($n['telegram_id'])): ?>
                                                <i class="fab fa-telegram"></i> <?= htmlspecialchars($n['telegram_id']) ?>
                                            <?php elseif (!empty($n['parent_email'])): ?>
                                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($n['parent_email']) ?>
                                            <?php else: ?>
                                                <span class="text-muted">нет контакта</span>
                                            <?php endif; ?>
                                         </nav>
                                        <td><?= htmlspecialchars($n['discipline']) ?></td>
                                        <td>
                                            <?php if ($n['attendance_status'] == 'present'): ?>
                                                <span class="badge bg-success">Вовремя</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Опоздал</span>
                                            <?php endif; ?>
                                         </nav>
                                        <td>
                                            <?php 
                                            $via = explode(',', $n['sent_via']);
                                            foreach ($via as $v): 
                                            ?>
                                                <?php if ($v == 'telegram'): ?>
                                                    <span class="badge bg-info"><i class="fab fa-telegram"></i> Telegram</span>
                                                <?php elseif ($v == 'email'): ?>
                                                    <span class="badge bg-secondary"><i class="fas fa-envelope"></i> Email</span>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                         </nav>
                                        <td>
                                            <?php if ($n['status'] == 'sent'): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle"></i> Отправлено
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">
                                                    <i class="fas fa-times-circle"></i> Ошибка
                                                </span>
                                            <?php endif; ?>
                                         </nav>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info text-center">
                        <i class="fas fa-info-circle"></i> Нет отправленных уведомлений
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="alert alert-secondary mt-4">
            <h5><i class="fab fa-telegram"></i> Как настроить Telegram уведомления:</h5>
            <ol>
                <li>Напишите боту <strong>@ChonRobot</strong> команду <code>/start</code></li>
                <li>Бот ответит вашим Telegram ID (число)</li>
                <li>Скопируйте этот ID в поле "Telegram ID родителя" в карточке студента</li>
                <li>После отметки студента родитель получит уведомление в Telegram</li>
            </ol>
            <hr>
            <h5><i class="fas fa-envelope"></i> О системе уведомлений:</h5>
            <ul>
                <li>✅ <strong>Telegram</strong> - реальные уведомления через бота @ChonRobot</li>
                <li>📧 <strong>Email</strong> - в учебных целях используется имитация (логгирование)</li>
                <li>Для реальной отправки email необходимо настроить SMTP сервер</li>
            </ul>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
