<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'init.php';

$notifications = $pdo->query("
    SELECT n.*, s.full_name, s.group_name, s.parent_email,
           a.status as attendance_status, a.scan_time,
           sc.discipline
    FROM notifications_log n
    JOIN students s ON n.student_id = s.id
    JOIN attendance a ON n.attendance_id = a.id
    JOIN schedule sc ON a.schedule_id = sc.id
    ORDER BY n.sent_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKON - Лог уведомлений</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1><i class="fas fa-bell"></i> Лог уведомлений</h1>
        <p class="text-muted">История отправки email-уведомлений родителям</p>
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Успешно отправлено</h5>
                        <h2>
                            <?php 
                            $success = count(array_filter($notifications, function($n) {
                                return $n['status'] == 'sent';
                            }));
                            echo $success;
                            ?>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-danger text-white">
                    <div class="card-body">
                        <h5 class="card-title">Ошибок отправки</h5>
                        <h2>
                            <?php 
                            $failed = count(array_filter($notifications, function($n) {
                                return $n['status'] == 'failed';
                            }));
                            echo $failed;
                            ?>
                        </h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Всего уведомлений</h5>
                        <h2><?= count($notifications) ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <h5 class="card-title">Уникальных студентов</h5>
                        <h2>
                            <?php 
                            $unique_students = count(array_unique(array_column($notifications, 'student_id')));
                            echo $unique_students;
                            ?>
                        </h2>
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
                                    <th>Email родителя</th>
                                    <th>Занятие</th>
                                    <th>Статус отметки</th>
                                    <th>Время отметки</th>
                                    <th>Статус отправки</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $n): ?>
                                    <tr>
                                        <td><?= date('d.m.Y H:i:s', strtotime($n['sent_at'])) ?></td>
                                        <td><strong><?= htmlspecialchars($n['full_name']) ?></strong></td>
                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($n['group_name']) ?></span></td>
                                        <td><?= htmlspecialchars($n['parent_email']) ?></td>
                                        <td><?= htmlspecialchars($n['discipline']) ?></td>
                                        <td>
                                            <?php if ($n['attendance_status'] == 'present'): ?>
                                                <span class="badge bg-success">Вовремя</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Опоздал</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= date('d.m.Y H:i:s', strtotime($n['scan_time'])) ?></td>
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
                                        </td>
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
            <h5><i class="fas fa-envelope"></i> О системе уведомлений:</h5>
            <ul>
                <li>Уведомления отправляются автоматически при отметке посещаемости</li>
                <li>В учебных целях используется имитация отправки (логгирование)</li>
                <li>Для реальной отправки необходимо настроить SMTP сервер</li>
                <li>Родитель получает уведомление о посещении или опоздании студента</li>
            </ul>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>