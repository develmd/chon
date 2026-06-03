<?php
require_once 'config.php';
require_once 'functions.php';
session_start();

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'teacher')) {
    header('Location: login.php');
    exit;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action == 'add' && $_SESSION['role'] == 'admin') {
            $username = trim($_POST['username']);
            $password = md5(trim($_POST['password']));
            $full_name = trim($_POST['full_name']);
            $email = trim($_POST['email']);
            $role = $_POST['role'];
            $student_id = $_POST['student_id'] ?: null;
            
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()['count'] > 0) {
                $error = "Пользователь с таким логином уже существует!";
            } else {
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, student_id) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$username, $password, $full_name, $email, $role, $student_id]);
                $message = "Пользователь добавлен!";
            }
        } elseif ($action == 'promote_to_teacher' && $_SESSION['role'] == 'admin') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE users SET role = 'teacher' WHERE id = ? AND role = 'student'");
            $stmt->execute([$id]);
            $message = "Пользователь повышен до преподавателя!";
        } elseif ($action == 'demote_to_student' && $_SESSION['role'] == 'admin') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE users SET role = 'student' WHERE id = ? AND role = 'teacher'");
            $stmt->execute([$id]);
            $message = "Пользователь понижен до ученика!";
        } elseif ($action == 'delete' && $_SESSION['role'] == 'admin') {
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
            $stmt->execute([$id]);
            $message = "Пользователь удалён!";
        } elseif ($action == 'reset_password' && $_SESSION['role'] == 'admin') {
            $id = $_POST['id'];
            $new_password = md5('123456');
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$new_password, $id]);
            $message = "Пароль сброшен на 123456!";
        }
    } catch (PDOException $e) {
        $error = "Ошибка: " . $e->getMessage();
    }
}

if ($_SESSION['role'] == 'admin') {
    $users = $pdo->query("
        SELECT u.*, s.full_name as student_name 
        FROM users u
        LEFT JOIN students s ON u.student_id = s.id
        ORDER BY 
            CASE u.role 
                WHEN 'admin' THEN 1 
                WHEN 'teacher' THEN 2 
                WHEN 'student' THEN 3 
                ELSE 4 
            END, u.username
    ")->fetchAll();
} else {
    $users = $pdo->query("
        SELECT u.*, s.full_name as student_name 
        FROM users u
        LEFT JOIN students s ON u.student_id = s.id
        WHERE u.role != 'admin'
        ORDER BY 
            CASE u.role 
                WHEN 'teacher' THEN 1 
                WHEN 'student' THEN 2 
                ELSE 3 
            END, u.username
    ")->fetchAll();
}

$students = $pdo->query("SELECT id, full_name, group_name FROM students ORDER BY full_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CHECKON - Управление пользователями</title>
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
        .btn-secondary { background: #6c757d; border: none; }
        .btn-info { background: linear-gradient(45deg, #17a2b8, #6f42c1); border: none; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-4">
        <h1><i class="fas fa-users" style="color: #667eea;"></i> Управление пользователями</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= $message ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= $error ?></div>
        <?php endif; ?>
        
        <div class="row">
            <?php if ($_SESSION['role'] == 'admin'): ?>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-plus-circle"></i> Создать аккаунт</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="mb-3">
                                <label class="form-label">Логин *</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Пароль *</label>
                                <input type="text" class="form-control" name="password" value="123456" required>
                                <small class="text-muted">По умолчанию: 123456</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">ФИО *</label>
                                <input type="text" class="form-control" name="full_name" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Роль *</label>
                                <select class="form-select" name="role" id="roleSelect" required>
                                    <option value="student">Ученик</option>
                                    <option value="teacher">Преподаватель</option>
                                    <option value="admin">Администратор</option>
                                </select>
                            </div>
                            <div class="mb-3" id="studentSelectDiv" style="display:none;">
                                <label class="form-label">Связать со студентом</label>
                                <select class="form-select" name="student_id">
                                    <option value="">-- Не связывать (создать нового) --</option>
                                    <?php foreach ($students as $s): ?>
                                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['full_name']) ?> (<?= htmlspecialchars($s['group_name']) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="text-muted">Если выбрать студента, аккаунт привяжется к существующему</small>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-user-plus"></i> Создать аккаунт
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="col-md-<?= $_SESSION['role'] == 'admin' ? '8' : '12' ?>">
                <div class="card">
                    <div class="card-header">
                        <h5>Список пользователей</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Логин</th>
                                        <th>ФИО</th>
                                        <th>Email</th>
                                        <th>Роль</th>
                                        <th>Действия</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= $user['id'] ?></td>
                                        <td><?= htmlspecialchars($user['username']) ?></td>
                                        <td><?= htmlspecialchars($user['full_name']) ?>
                                            <?php if ($user['student_name']): ?>
                                                <br><small class="text-muted">Студент: <?= htmlspecialchars($user['student_name']) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <?php 
                                            if ($user['role'] == 'admin') echo '<span class="badge" style="background: #dc3545;">Админ</span>';
                                            elseif ($user['role'] == 'teacher') echo '<span class="badge" style="background: #17a2b8;">Учитель</span>';
                                            else echo '<span class="badge" style="background: #28a745;">Ученик</span>';
                                            ?>
                                        </td>
                                        <td>
                                            <?php if ($_SESSION['role'] == 'admin'): ?>
                                                <?php if ($user['role'] == 'student'): ?>
                                                    <form method="POST" style="display:inline-block">
                                                        <input type="hidden" name="action" value="promote_to_teacher">
                                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-info" onclick="return confirm('Повысить пользователя до преподавателя?')">
                                                            <i class="fas fa-arrow-up"></i>
                                                        </button>
                                                    </form>
                                                <?php elseif ($user['role'] == 'teacher'): ?>
                                                    <form method="POST" style="display:inline-block">
                                                        <input type="hidden" name="action" value="demote_to_student">
                                                        <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Понизить пользователя до ученика?')">
                                                            <i class="fas fa-arrow-down"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                
                                                <?php if ($user['role'] != 'admin'): ?>
                                                <form method="POST" style="display:inline-block">
                                                    <input type="hidden" name="action" value="reset_password">
                                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-secondary" onclick="return confirm('Сбросить пароль на 123456?')">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display:inline-block">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Удалить пользователя?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                         </nav>
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
    
    <script>
        document.getElementById('roleSelect')?.addEventListener('change', function() {
            const studentDiv = document.getElementById('studentSelectDiv');
            if (this.value === 'student') {
                studentDiv.style.display = 'block';
            } else {
                studentDiv.style.display = 'none';
            }
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>