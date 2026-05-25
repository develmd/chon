<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'init.php';

$date_filter = $_GET['date'] ?? '';
$group_filter = $_GET['group'] ?? '';
$schedule_filter = $_GET['schedule'] ?? '';

$sql = "
    SELECT a.*, 
           s.full_name, s.group_name,
           sc.discipline, sc.teacher, sc.start_time,
           q.date as qr_date
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    JOIN schedule sc ON a.schedule_id = sc.id
    JOIN qr_codes q ON a.qr_code_id = q.id
    WHERE 1=1
";
$params = [];

if ($date_filter) {
    $sql .= " AND DATE(a.scan_time) = ?";
    $params[] = $date_filter;
}

if ($group_filter) {
    $sql .= " AND s.group_name = ?";
    $params[] = $group_filter;
}

if ($schedule_filter) {
    $sql .= " AND sc.id = ?";
    $params[] = $schedule_filter;
}

$sql .= " ORDER BY a.scan_time DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendance = $stmt->fetchAll();

$groups = getGroups();
$schedule_list = $pdo->query("SELECT id, discipline FROM schedule ORDER BY discipline")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKON - Посещаемость</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1><i class="fas fa-clipboard-list"></i> Журнал посещаемости</h1>
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label>Дата</label>
                        <input type="date" class="form-control" name="date" value="<?= htmlspecialchars($date_filter) ?>">
                    </div>
                    <div class="col-md-3">
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
                        <label>Занятие</label>
                        <select class="form-select" name="schedule">
                            <option value="">Все занятия</option>
                            <?php foreach ($schedule_list as $sch): ?>
                                <option value="<?= $sch['id'] ?>" <?= $schedule_filter == $sch['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($sch['discipline']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter"></i> Применить фильтры
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <h5>Записи посещаемости (<?= count($attendance) ?>)</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Дата и время</th>
                                <th>Студент</th>
                                <th>Группа</th>
                                <th>Дисциплина</th>
                                <th>Преподаватель</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($attendance) > 0): ?>
                                <?php foreach ($attendance as $item): ?>
                                    <tr>
                                        <td><?= $item['scan_time'] ?></td>
                                        <td><?= htmlspecialchars($item['full_name']) ?></td>
                                        <td><?= htmlspecialchars($item['group_name']) ?></td>
                                        <td><?= htmlspecialchars($item['discipline']) ?></td>
                                        <td><?= htmlspecialchars($item['teacher']) ?></td>
                                        <td>
                                            <?php if ($item['status'] == 'present'): ?>
                                                <span class="badge bg-success">Вовремя</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">Опоздал</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center">Нет записей</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>