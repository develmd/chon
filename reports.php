<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'init.php';

$group_filter = $_GET['group'] ?? '';
$period = $_GET['period'] ?? 'week';
$export = isset($_GET['export']);

// Определяем период
if ($period == 'week') {
    $start_date = date('Y-m-d', strtotime('monday this week'));
    $end_date = date('Y-m-d', strtotime('sunday this week'));
} else {
    $start_date = date('Y-m-d', strtotime('first day of this month'));
    $end_date = date('Y-m-d', strtotime('last day of this month'));
}

// Получаем статистику по студентам
$sql = "
    SELECT 
        s.id,
        s.full_name,
        s.group_name,
        COUNT(DISTINCT a.id) as total_attendance,
        SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) as present_count,
        SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) as late_count
    FROM students s
    LEFT JOIN attendance a ON s.id = a.student_id
    LEFT JOIN qr_codes q ON a.qr_code_id = q.id
    WHERE DATE(a.scan_time) BETWEEN ? AND ?
";

$params = [$start_date, $end_date];

if ($group_filter) {
    $sql .= " AND s.group_name = ?";
    $params[] = $group_filter;
}

$sql .= " GROUP BY s.id, s.full_name, s.group_name ORDER BY s.full_name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$statistics = $stmt->fetchAll();

// Если экспорт в Excel
if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $period . '_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Студент', 'Группа', 'Всего посещений', 'Вовремя', 'Опоздал', '% посещаемости']);
    
    foreach ($statistics as $stat) {
        $total_lessons = $pdo->query("SELECT COUNT(*) as count FROM schedule")->fetch()['count'];
        $percent = $total_lessons > 0 ? round(($stat['total_attendance'] / $total_lessons) * 100) : 0;
        fputcsv($output, [
            $stat['full_name'],
            $stat['group_name'],
            $stat['total_attendance'],
            $stat['present_count'],
            $stat['late_count'],
            $percent . '%'
        ]);
    }
    
    fclose($output);
    exit;
}

$groups = getGroups();
$total_lessons = $pdo->query("SELECT COUNT(*) as count FROM schedule")->fetch()['count'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKON - Отчёты</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1><i class="fas fa-chart-bar"></i> Отчёты и статистика</h1>
        
        <!-- Форма отчёта -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label>Группа</label>
                        <select class="form-select" name="group">
                            <option value="">Все группы</option>
                            <?php foreach ($groups as $g): ?>
                                <option value="<?= htmlspecialchars($g['group_name']) ?>" <?= $group_filter == $g['group_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($g['group_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label>Период</label>
                        <select class="form-select" name="period">
                            <option value="week" <?= $period == 'week' ? 'selected' : '' ?>>Текущая неделя</option>
                            <option value="month" <?= $period == 'month' ? 'selected' : '' ?>>Текущий месяц</option>
                        </select>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-chart-line"></i> Показать
                        </button>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <a href="?<?= http_build_query(array_merge($_GET, ['export' => 1])) ?>" class="btn btn-success w-100">
                            <i class="fas fa-file-excel"></i> Экспорт в Excel
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Статистика -->
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h5>Общая статистика</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $total_present = array_sum(array_column($statistics, 'present_count'));
                        $total_late = array_sum(array_column($statistics, 'late_count'));
                        $total_attendance = $total_present + $total_late;
                        ?>
                        <div class="row text-center">
                            <div class="col-md-4">
                                <h3><?= $total_attendance ?></h3>
                                <p class="text-muted">Всего отметок</p>
                            </div>
                            <div class="col-md-4">
                                <h3 class="text-success"><?= $total_present ?></h3>
                                <p class="text-muted">Вовремя</p>
                            </div>
                            <div class="col-md-4">
                                <h3 class="text-warning"><?= $total_late ?></h3>
                                <p class="text-muted">Опоздал</p>
                            </div>
                        </div>
                        <canvas id="statsChart" height="200"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-info text-white">
                        <h5>Информация о периоде</h5>
                    </div>
                    <div class="card-body">
                        <p><strong>Период:</strong> <?= $start_date ?> - <?= $end_date ?></p>
                        <p><strong>Всего занятий в расписании:</strong> <?= $total_lessons ?></p>
                        <p><strong>Студентов в выбранной группе:</strong> 
                            <?php
                            if ($group_filter) {
                                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE group_name = ?");
                                $stmt->execute([$group_filter]);
                                echo $stmt->fetch()['count'];
                            } else {
                                echo $pdo->query("SELECT COUNT(*) as count FROM students")->fetch()['count'];
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Детальная таблица -->
        <div class="card mt-4">
            <div class="card-header">
                <h5>Детальная статистика по студентам</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Студент</th>
                                <th>Группа</th>
                                <th>Посещений</th>
                                <th>Вовремя</th>
                                <th>Опоздал</th>
                                <th>% посещаемости</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statistics as $stat): ?>
                                <?php $percent = $total_lessons > 0 ? round(($stat['total_attendance'] / $total_lessons) * 100) : 0; ?>
                                <tr>
                                    <td><?= htmlspecialchars($stat['full_name']) ?></td>
                                    <td><?= htmlspecialchars($stat['group_name']) ?></td>
                                    <td><?= $stat['total_attendance'] ?></td>
                                    <td class="text-success"><?= $stat['present_count'] ?></td>
                                    <td class="text-warning"><?= $stat['late_count'] ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar bg-success" role="progressbar" style="width: <?= $percent ?>%">
                                                <?= $percent ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // График статистики
        const ctx = document.getElementById('statsChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Вовремя', 'Опоздал'],
                datasets: [{
                    data: [<?= $total_present ?>, <?= $total_late ?>],
                    backgroundColor: ['#28a745', '#ffc107'],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>