<?php
require_once 'config.php';
require_once 'functions.php';
require_once 'init.php';
session_start();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        try {
            if ($action == 'add') {
                $stmt = $pdo->prepare("
                    INSERT INTO students (full_name, group_name, parent_email, parent_phone, telegram_id)
                    VALUES (?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $_POST['full_name'],
                    $_POST['group_name'],
                    $_POST['parent_email'],
                    $_POST['parent_phone'],
                    $_POST['telegram_id']
                ]);
                $message = "Студент успешно добавлен!";
            } elseif ($action == 'edit') {
                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET full_name = ?, group_name = ?, parent_email = ?, parent_phone = ?, telegram_id = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $_POST['full_name'],
                    $_POST['group_name'],
                    $_POST['parent_email'],
                    $_POST['parent_phone'],
                    $_POST['telegram_id'],
                    $_POST['id']
                ]);
                $message = "Студент успешно обновлён!";
            } elseif ($action == 'delete') {
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM attendance WHERE student_id = ?");
                $stmt->execute([$_POST['id']]);
                $count = $stmt->fetch()['count'];
                
                if ($count > 0) {
                    $error = "Нельзя удалить студента, у которого есть записи о посещаемости!";
                } else {
                    $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
                    $stmt->execute([$_POST['id']]);
                    $message = "Студент удалён!";
                }
            }
        } catch (PDOException $e) {
            $error = "Ошибка: " . $e->getMessage();
        }
    }
}

$search = $_GET['search'] ?? '';
$group_filter = $_GET['group'] ?? '';
$students = getStudents($search, $group_filter);
$groups = getGroups();

$edit_student = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_student = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKON - Студенты</title>
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
        .form-control, .form-select { background: #1a1a2e; border: 1px solid #333; color: #eee; }
        .form-control:focus, .form-select:focus { background: #1a1a2e; color: #eee; }
        .btn-primary { background: linear-gradient(45deg, #667eea, #764ba2); border: none; }
        .btn-primary:hover { transform: scale(1.02); transition: all 0.3s; }
        .btn-success { background: linear-gradient(45deg, #28a745, #20c997); border: none; }
        .btn-warning { background: linear-gradient(45deg, #ffc107, #fd7e14); border: none; color: #000; }
        .btn-danger { background: linear-gradient(45deg, #dc3545, #c82333); border: none; }
        .btn-info { background: linear-gradient(45deg, #17a2b8, #6f42c1); border: none; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1><i class="fas fa-users" style="color: #667eea;"></i> Управление студентами</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= $message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= $error ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <input type="text" class="form-control" name="search" placeholder="Поиск по ФИО..." value="<?= htmlspecialchars($search) ?>">
                    </div>
                    <div class="col-md-4">
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
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Поиск
                        </button>
                        <a href="students.php" class="btn btn-secondary">
                            <i class="fas fa-redo"></i> Сброс
                        </a>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><?= $edit_student ? 'Редактирование' : 'Добавление' ?> студента</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="<?= $edit_student ? 'edit' : 'add' ?>">
                            <?php if ($edit_student): ?>
                                <input type="hidden" name="id" value="<?= $edit_student['id'] ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">ФИО *</label>
                                <input type="text" class="form-control" name="full_name" required 
                                       value="<?= $edit_student ? htmlspecialchars($edit_student['full_name']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Группа *</label>
                                <input type="text" class="form-control" name="group_name" required
                                       value="<?= $edit_student ? htmlspecialchars($edit_student['group_name']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email родителя</label>
                                <input type="email" class="form-control" name="parent_email"
                                       value="<?= $edit_student ? htmlspecialchars($edit_student['parent_email']) : '' ?>">
                                <small class="text-muted">Для email-уведомлений</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Телефон родителя</label>
                                <input type="text" class="form-control" name="parent_phone"
                                       value="<?= $edit_student ? htmlspecialchars($edit_student['parent_phone']) : '' ?>">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Telegram ID родителя</label>
                                <input type="text" class="form-control" name="telegram_id" placeholder="Например: 123456789"
                                       value="<?= $edit_student ? htmlspecialchars($edit_student['telegram_id']) : '' ?>">
                                <small class="text-muted">
                                    <i class="fab fa-telegram"></i> Как получить: напишите боту <strong>@ChonRobot</strong> команду /start, он ответит вашим ID
                                </small>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-save"></i> <?= $edit_student ? 'Обновить' : 'Добавить' ?>
                            </button>
                            <?php if ($edit_student): ?>
                                <a href="students.php" class="btn btn-secondary w-100 mt-2">Отмена</a>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5>Список студентов (<?= count($students) ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ФИО</th>
                                        <th>Группа</th>
                                        <th>Email</th>
                                        <th>Телефон</th>
                                        <th>Telegram</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (count($students) > 0): ?>
                                        <?php foreach ($students as $student): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($student['full_name']) ?></td>
                                                <td><?= htmlspecialchars($student['group_name']) ?></td>
                                                <td><?= htmlspecialchars($student['parent_email']) ?></td>
                                                <td><?= htmlspecialchars($student['parent_phone']) ?></td>
                                                <td>
                                                    <?php if (!empty($student['telegram_id'])): ?>
                                                        <span class="badge bg-info"><i class="fab fa-telegram"></i> <?= htmlspecialchars($student['telegram_id']) ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Не указан</span>
                                                    <?php endif; ?>
                                                 </nav>
                                                <td>
                                                    <a href="?edit=<?= $student['id'] ?>" class="btn btn-sm btn-warning">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger" 
                                                            onclick="confirmDelete(<?= $student['id'] ?>, '<?= htmlspecialchars($student['full_name']) ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                 </nav>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center">Нет данных</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <form id="deleteForm" method="POST" style="display:none">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmDelete(id, name) {
            if (confirm(`Вы уверены, что хотите удалить студента "${name}"?`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }
    </script>
</body>
</html>
