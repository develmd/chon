<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'init.php';

$message = '';
$error = '';
$days = ['', 'Понедельник', 'Вторник', 'Среда', 'Четверг', 'Пятница', 'Суббота', 'Воскресенье'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        if (isset($_POST['add'])) {
            $stmt = $pdo->prepare("
                INSERT INTO schedule (discipline, teacher, classroom, day_of_week, start_time, end_time)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['discipline'],
                $_POST['teacher'],
                $_POST['classroom'],
                $_POST['day_of_week'],
                $_POST['start_time'],
                $_POST['end_time']
            ]);
            $message = "Занятие добавлено!";
        } elseif (isset($_POST['edit'])) {
            $stmt = $pdo->prepare("
                UPDATE schedule 
                SET discipline = ?, teacher = ?, classroom = ?, day_of_week = ?, start_time = ?, end_time = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['discipline'],
                $_POST['teacher'],
                $_POST['classroom'],
                $_POST['day_of_week'],
                $_POST['start_time'],
                $_POST['end_time'],
                $_POST['id']
            ]);
            $message = "Занятие обновлено!";
        } elseif (isset($_POST['delete'])) {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM qr_codes WHERE schedule_id = ?");
            $stmt->execute([$_POST['id']]);
            if ($stmt->fetch()['count'] > 0) {
                $error = "Нельзя удалить занятие, для которого сгенерированы QR-коды!";
            } else {
                $stmt = $pdo->prepare("DELETE FROM schedule WHERE id = ?");
                $stmt->execute([$_POST['id']]);
                $message = "Занятие удалено!";
            }
        }
    } catch (PDOException $e) {
        $error = "Ошибка: " . $e->getMessage();
    }
}

$schedule = $pdo->query("
    SELECT * FROM schedule 
    ORDER BY day_of_week, start_time
")->fetchAll();

$edit_item = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM schedule WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_item = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKON - Расписание</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1><i class="fas fa-calendar-alt"></i> Управление расписанием</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><?= $edit_item ? 'Редактирование' : 'Добавление' ?> занятия</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="<?= $edit_item ? 'edit' : 'add' ?>" value="1">
                            <?php if ($edit_item): ?>
                                <input type="hidden" name="id" value="<?= $edit_item['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label>Дисциплина *</label>
                                <input type="text" class="form-control" name="discipline" required
                                       value="<?= $edit_item ? htmlspecialchars($edit_item['discipline']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label>Преподаватель *</label>
                                <input type="text" class="form-control" name="teacher" required
                                       value="<?= $edit_item ? htmlspecialchars($edit_item['teacher']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label>Аудитория *</label>
                                <input type="text" class="form-control" name="classroom" required
                                       value="<?= $edit_item ? htmlspecialchars($edit_item['classroom']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label>День недели *</label>
                                <select class="form-select" name="day_of_week" required>
                                    <?php for ($i = 1; $i <= 7; $i++): ?>
                                        <option value="<?= $i ?>" <?= $edit_item && $edit_item['day_of_week'] == $i ? 'selected' : '' ?>>
                                            <?= $days[$i] ?>
                                        </option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label>Время начала *</label>
                                <input type="time" class="form-control" name="start_time" required
                                       value="<?= $edit_item ? $edit_item['start_time'] : '09:00' ?>">
                            </div>
                            <div class="mb-3">
                                <label>Время окончания *</label>
                                <input type="time" class="form-control" name="end_time" required
                                       value="<?= $edit_item ? $edit_item['end_time'] : '10:30' ?>">
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> <?= $edit_item ? 'Обновить' : 'Добавить' ?>
                            </button>
                            <?php if ($edit_item): ?>
                                <a href="schedule.php" class="btn btn-secondary">Отмена</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Расписание занятий</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>День</th>
                                        <th>Время</th>
                                        <th>Дисциплина</th>
                                        <th>Преподаватель</th>
                                        <th>Ауд.</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schedule as $item): ?>
                                        <tr>
                                            <td><?= $days[$item['day_of_week']] ?></td>
                                            <td><?= $item['start_time'] ?> - <?= $item['end_time'] ?></td>
                                            <td><?= htmlspecialchars($item['discipline']) ?></td>
                                            <td><?= htmlspecialchars($item['teacher']) ?></td>
                                            <td><?= htmlspecialchars($item['classroom']) ?></td>
                                            <td>
                                                <a href="?edit=<?= $item['id'] ?>" class="btn btn-sm btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" style="display:inline" 
                                                      onsubmit="return confirm('Удалить занятие?')">
                                                    <input type="hidden" name="delete" value="1">
                                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>