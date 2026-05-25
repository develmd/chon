<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'init.php';

$today = date('Y-m-d');
$today_stats = getTodayStats();
$today_schedule = getTodaySchedule();
$total_students = $pdo->query("SELECT COUNT(*) as count FROM students")->fetch()['count'];

$active_sessions = 0;
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='active_sessions'")->fetch();
if ($tables) {
    $active_sessions = $pdo->query("SELECT COUNT(*) as count FROM active_sessions WHERE is_active = 1 AND expires_at > datetime('now')")->fetch()['count'];
}

$percent = $total_students > 0 ? round(($today_stats['total'] / $total_students) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKON - Система контроля посещаемости</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .role-card {
            transition: transform 0.3s;
            cursor: pointer;
        }
        .role-card:hover {
            transform: translateY(-10px);
        }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="text-center mb-5">
            <h1><i class="fas fa-check-circle" style="color: #667eea;"></i> CHECKON</h1>
            <p class="lead">Система контроля посещаемости по QR-кодам</p>
            <p class="text-muted">С привязкой к расписанию и автоматическими уведомлениями родителям</p>
        </div>
        
        <div class="row mb-5">
            <div class="col-md-6">
                <div class="card role-card text-center" onclick="location.href='scan.php'">
                    <div class="card-body">
                        <i class="fas fa-user-graduate fa-4x text-primary"></i>
                        <h3 class="mt-3">Я студент</h3>
                        <p class="text-muted">Отметить посещаемость через QR-код</p>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card role-card text-center" onclick="location.href='teacher_panel.php'">
                    <div class="card-body">
                        <i class="fas fa-chalkboard-teacher fa-4x text-success"></i>
                        <h3 class="mt-3">Я преподаватель</h3>
                        <p class="text-muted">Генерация QR-кодов и контроль посещаемости</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Всего студентов</h5>
                        <h2><?= $total_students ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Присутствуют сегодня</h5>
                        <h2><?= $today_stats['present'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Опоздали сегодня</h5>
                        <h2><?= $today_stats['late'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info mb-3">
                    <div class="card-body">
                        <h5 class="card-title">Активных QR-сессий</h5>
                        <h2><?= $active_sessions ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header bg-dark text-white">
                <h5><i class="fas fa-info-circle"></i> Как работает система</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="step">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 24px;">1</div>
                            <h6 class="mt-2">Преподаватель генерирует QR-код</h6>
                            <small class="text-muted">На каждое занятие создается уникальный QR-код с ограничением по времени</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="step">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 24px;">2</div>
                            <h6 class="mt-2">Студент сканирует QR-код</h6>
                            <small class="text-muted">Через камеру телефона или вводом кода</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="step">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 24px;">3</div>
                            <h6 class="mt-2">Родитель получает уведомление</h6>
                            <small class="text-muted">Автоматическое оповещение о посещении или опоздании</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>