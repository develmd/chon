<?php
require_once 'config.php';
require_once 'functions.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

$total_users = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch()['count'];
$total_students = $pdo->query("SELECT COUNT(*) as count FROM students")->fetch()['count'];
$total_teachers = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")->fetch()['count'];
$total_attendance = $pdo->query("SELECT COUNT(*) as count FROM attendance")->fetch()['count'];
$total_schedule = $pdo->query("SELECT COUNT(*) as count FROM schedule")->fetch()['count'];

$recent_attendance = $pdo->query("
    SELECT a.*, s.full_name, sc.discipline 
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN schedule sc ON a.schedule_id = sc.id
    ORDER BY a.scan_time DESC LIMIT 10
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKON - Панель администратора</title>
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
        .table-dark { background-color: #0f0f1a; }
        .btn-primary { background: linear-gradient(45deg, #667eea, #764ba2); border: none; }
        .btn-primary:hover { transform: scale(1.02); transition: all 0.3s; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-user-shield" style="color: #667eea;"></i> Панель администратора</h1>
            <div>
                <span class="badge py-2 px-3" style="background: linear-gradient(45deg, #667eea, #764ba2);">
                    <i class="fas fa-crown"></i> Администратор: <?= htmlspecialchars($_SESSION['full_name']) ?>
                </span>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Пользователей</h6>
                                <h2 class="mb-0"><?= $total_users ?></h2>
                            </div>
                            <i class="fas fa-users fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Студентов</h6>
                                <h2 class="mb-0"><?= $total_students ?></h2>
                            </div>
                            <i class="fas fa-user-graduate fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white" style="background: linear-gradient(135deg, #17a2b8, #6f42c1);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Преподавателей</h6>
                                <h2 class="mb-0"><?= $total_teachers ?></h2>
                            </div>
                            <i class="fas fa-chalkboard-teacher fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6>Занятий в расписании</h6>
                                <h2 class="mb-0"><?= $total_schedule ?></h2>
                            </div>
                            <i class="fas fa-calendar fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-chart-line"></i> Общая статистика</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-6">
                                <h3><?= $total_attendance ?></h3>
                                <p class="text-muted">Всего отметок</p>
                            </div>
                            <div class="col-md-6">
                                <h3><?= round($total_attendance / ($total_students ?: 1)) ?></h3>
                                <p class="text-muted">Средних отметок на студента</p>
                            </div>
                        </div>
                        <canvas id="statsChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-cogs"></i> Быстрые действия</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <a href="users.php" class="btn btn-primary w-100 py-3">
                                    <i class="fas fa-users fa-2x d-block"></i>
                                    Управление пользователями
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="students.php" class="btn btn-primary w-100 py-3" style="background: linear-gradient(135deg, #28a745, #20c997);">
                                    <i class="fas fa-user-graduate fa-2x d-block"></i>
                                    Управление студентами
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="schedule.php" class="btn btn-primary w-100 py-3" style="background: linear-gradient(135deg, #17a2b8, #6f42c1);">
                                    <i class="fas fa-calendar fa-2x d-block"></i>
                                    Управление расписанием
                                </a>
                            </div>
                            <div class="col-md-6">
                                <a href="reports.php" class="btn btn-primary w-100 py-3" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                                    <i class="fas fa-chart-bar fa-2x d-block"></i>
                                    Отчёты и статистика
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> Последние отметки</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead class="table-dark">
                            <tr>
                                <th>Время</th>
                                <th>Студент</th>
                                <th>Дисциплина</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_attendance as $att): ?>
                            <tr>
                                <td><?= $att['scan_time'] ?></td>
                                <td><?= htmlspecialchars($att['full_name']) ?></td>
                                <td><?= htmlspecialchars($att['discipline']) ?></td>
                                <td>
                                    <?php if ($att['status'] == 'present'): ?>
                                        <span class="badge bg-success">Вовремя</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning">Опоздал</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Студенты', 'Преподаватели', 'Администраторы'],
                datasets: [{
                    data: [<?= $total_students ?>, <?= $total_teachers ?>, <?= $total_users - $total_students - $total_teachers ?>],
                    backgroundColor: ['#28a745', '#17a2b8', '#ffc107'],
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { position: 'bottom', labels: { color: '#eee' } } }
            }
        });
    </script>
</body>
</html>