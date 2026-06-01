<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'init.php';
session_start();

$today = date('Y-m-d');
$today_stats = getTodayStats();
$total_students = $pdo->query("SELECT COUNT(*) as count FROM students")->fetch()['count'];

$active_sessions = 0;
$tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='active_sessions'")->fetch();
if ($tables) {
    $active_sessions = $pdo->query("SELECT COUNT(*) as count FROM active_sessions WHERE is_active = 1 AND expires_at > datetime('now')")->fetch()['count'];
}
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
        body { background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); min-height: 100vh; }
        .card { background: #0f0f1a; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.3); border-radius: 15px; }
        .card:hover { transform: translateY(-5px); transition: all 0.3s; }
        .role-card { cursor: pointer; transition: all 0.3s; }
        .role-card:hover { transform: translateY(-10px); }
        .text-white { color: #eee !important; }
        .text-muted { color: #888 !important; }
        h1, h2, h3, h4, h5, h6, p { color: #eee; }
        .bg-dark-custom { background: #0f0f1a; }
        .table { color: #eee; }
        .table-striped>tbody>tr:nth-of-type(odd) { background-color: rgba(255,255,255,0.05); }
        .alert-light { background: #1a1a2e; color: #eee; border: 1px solid #333; }
        .list-group-item { background: #1a1a2e; color: #eee; border-color: #333; }
        .step { color: #eee; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <div class="text-center mb-5">
            <h1><i class="fas fa-check-circle" style="color: #667eea;"></i> CHECKON</h1>
            <p class="lead" style="color: #ccc;">Система контроля посещаемости по QR-кодам</p>
            <p style="color: #888;">С привязкой к расписанию и автоматическими уведомлениями родителям</p>
        </div>
        
        <div class="row mb-5">
            <div class="col-md-4">
                <div class="card role-card text-center" onclick="location.href='scan.php'">
                    <div class="card-body">
                        <i class="fas fa-user-graduate fa-4x" style="color: #667eea;"></i>
                        <h3 class="mt-3" style="color: #eee;">Я студент</h3>
                        <p style="color: #888;">Отметить посещаемость через QR-код</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card role-card text-center" onclick="location.href='teacher_panel.php'">
                    <div class="card-body">
                        <i class="fas fa-chalkboard-teacher fa-4x" style="color: #28a745;"></i>
                        <h3 class="mt-3" style="color: #eee;">Я преподаватель</h3>
                        <p style="color: #888;">Генерация QR-кодов и контроль посещаемости</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card role-card text-center" onclick="location.href='admin_panel.php'">
                    <div class="card-body">
                        <i class="fas fa-user-shield fa-4x" style="color: #dc3545;"></i>
                        <h3 class="mt-3" style="color: #eee;">Администратор</h3>
                        <p style="color: #888;">Полное управление системой</p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (isset($_SESSION['user_id'])): ?>
        <div class="alert alert-info text-center" style="background: #1a1a2e; border: 1px solid #667eea; color: #eee;">
            <i class="fas fa-user-circle"></i> Вы вошли как <strong><?= htmlspecialchars($_SESSION['full_name']) ?></strong>
            (<?php 
                if ($_SESSION['role'] == 'admin') echo 'Администратор';
                elseif ($_SESSION['role'] == 'teacher') echo 'Преподаватель';
                else echo 'Ученик';
            ?>)
        </div>
        <?php endif; ?>
        
        <div class="row mt-4">
            <div class="col-md-3">
                <div class="card text-white mb-3" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                    <div class="card-body">
                        <h5 class="card-title">Всего студентов</h5>
                        <h2><?= $total_students ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white mb-3" style="background: linear-gradient(135deg, #28a745, #20c997);">
                    <div class="card-body">
                        <h5 class="card-title">Присутствуют сегодня</h5>
                        <h2><?= $today_stats['present'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white mb-3" style="background: linear-gradient(135deg, #ffc107, #fd7e14);">
                    <div class="card-body">
                        <h5 class="card-title">Опоздали сегодня</h5>
                        <h2><?= $today_stats['late'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white mb-3" style="background: linear-gradient(135deg, #17a2b8, #6f42c1);">
                    <div class="card-body">
                        <h5 class="card-title">Активных QR-сессий</h5>
                        <h2><?= $active_sessions ?></h2>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header" style="background: linear-gradient(45deg, #667eea, #764ba2); border: none;">
                <h5 class="mb-0" style="color: #fff;"><i class="fas fa-info-circle"></i> Как работает система</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="step">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 24px; background: linear-gradient(45deg, #667eea, #764ba2) !important;">1</div>
                            <h6 class="mt-2">Преподаватель генерирует QR-код</h6>
                            <small>На каждое занятие создается уникальный QR-код с ограничением по времени</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="step">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 24px; background: linear-gradient(45deg, #667eea, #764ba2) !important;">2</div>
                            <h6 class="mt-2">Студент сканирует QR-код</h6>
                            <small>Через камеру телефона или вводом кода</small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="step">
                            <div class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center" style="width: 60px; height: 60px; font-size: 24px; background: linear-gradient(45deg, #667eea, #764ba2) !important;">3</div>
                            <h6 class="mt-2">Родитель получает уведомление</h6>
                            <small>Автоматическое оповещение о посещении или опоздании</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>