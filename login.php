<?php
require_once 'config.php';
session_start();

$error = '';
$register_error = '';
$register_success = '';

if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'admin') {
        header('Location: admin_panel.php');
    } elseif ($_SESSION['role'] === 'teacher') {
        header('Location: teacher_panel.php');
    } elseif ($_SESSION['role'] === 'student') {
        if (isset($_SESSION['pending_token'])) {
            $pending_token = $_SESSION['pending_token'];
            unset($_SESSION['pending_token']);
            header('Location: scan.php?token=' . urlencode($pending_token));
        } else {
            header('Location: scan.php');
        }
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['login'])) {
        $username = trim($_POST['username']);
        $password = md5(trim($_POST['password']));
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND password = ?");
        $stmt->execute([$username, $password]);
        $user = $stmt->fetch();
        
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['student_id'] = $user['student_id'];
            
            if ($user['role'] === 'admin') {
                header('Location: admin_panel.php');
            } elseif ($user['role'] === 'teacher') {
                header('Location: teacher_panel.php');
            } else {
                if (isset($_SESSION['pending_token'])) {
                    $pending_token = $_SESSION['pending_token'];
                    unset($_SESSION['pending_token']);
                    header('Location: scan.php?token=' . urlencode($pending_token));
                } else {
                    header('Location: scan.php');
                }
            }
            exit;
        } else {
            $error = "Неверный логин или пароль!";
        }
    } elseif (isset($_POST['register'])) {
        $username = trim($_POST['reg_username']);
        $password = trim($_POST['reg_password']);
        $confirm_password = trim($_POST['reg_confirm_password']);
        $full_name = trim($_POST['reg_full_name']);
        $email = trim($_POST['reg_email']);
        
        if ($password !== $confirm_password) {
            $register_error = "Пароли не совпадают!";
        } elseif (strlen($password) < 4) {
            $register_error = "Пароль должен содержать минимум 4 символа!";
        } else {
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()['count'] > 0) {
                $register_error = "Пользователь с таким логином уже существует!";
            } else {
                $hashed_password = md5($password);
                
                $stmt = $pdo->prepare("INSERT INTO students (full_name, group_name, parent_email, parent_phone) VALUES (?, 'Новая группа', ?, '')");
                $stmt->execute([$full_name, $email]);
                $student_id = $pdo->lastInsertId();
                
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, student_id) VALUES (?, ?, ?, ?, 'student', ?)");
                $stmt->execute([$username, $hashed_password, $full_name, $email, $student_id]);
                
                $register_success = "Регистрация успешна! Теперь вы можете войти.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>CHECKON - Вход в систему</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
        }
        body { 
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%); 
            min-height: 100vh;
            padding: 0;
            margin: 0;
        }
        .card { 
            background: #0f0f1a; 
            border: none; 
            border-radius: 20px; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.3); 
        }
        .card-header { 
            background: linear-gradient(45deg, #667eea, #764ba2); 
            border-radius: 20px 20px 0 0 !important; 
        }
        .form-control, .form-select { 
            background: #1a1a2e; 
            border: 1px solid #333; 
            color: #fff; 
            padding: 12px;
            font-size: 16px;
        }
        .form-control:focus, .form-select:focus { 
            background: #1a1a2e; 
            color: #fff; 
            border-color: #667eea; 
            box-shadow: 0 0 0 0.25rem rgba(102,126,234,0.25); 
        }
        .form-label { 
            color: #fff !important; 
            font-weight: 500; 
            margin-bottom: 8px;
        }
        .text-muted { color: #aaa !important; }
        .nav-tabs .nav-link { 
            color: #ededed; 
            background: transparent; 
            border: none; 
            padding: 10px 16px;
            font-size: 16px;
        }
        .nav-tabs .nav-link.active { 
            color: #667eea; 
            background: transparent; 
            border-bottom: 2px solid #667eea; 
        }
        .nav-tabs { border-bottom: 1px solid #333; }
        hr { border-color: #333; }
        .alert-danger { background: rgba(220,53,69,0.2); border: 1px solid #dc3545; color: #ff6b6b; }
        .alert-success { background: rgba(40,167,69,0.2); border: 1px solid #28a745; color: #6bff6b; }
        .alert-info { background: rgba(23,162,184,0.2); border: 1px solid #17a2b8; color: #6bc4d4; }
        .btn { padding: 12px; font-size: 16px; font-weight: 500; }
        .container { padding-left: 12px; padding-right: 12px; }
        h1 { font-size: 1.8rem; }
        .lead { font-size: 1rem; }
        @media (max-width: 768px) {
            .container { padding-left: 10px; padding-right: 10px; }
            h1 { font-size: 1.6rem; }
            .card-body { padding: 1rem; }
            .nav-tabs .nav-link { padding: 8px 12px; font-size: 14px; }
            .form-control, .form-select, .btn { padding: 10px; font-size: 14px; }
            small { font-size: 0.7rem; }
        }
    </style>
</head>
<body class="d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-11 col-sm-10 col-md-8 col-lg-6">
                <div class="text-center text-white mb-3 mb-md-4">
                    <i class="fas fa-check-circle fa-3x fa-md-4x" style="color: #667eea;"></i>
                    <h1 class="mt-1 mt-md-2">CHECKON</h1>
                    <p class="lead">Система контроля посещаемости</p>
                </div>
                
                <div class="card">
                    <div class="card-header text-center">
                        <ul class="nav nav-tabs card-header-tabs justify-content-center" style="background: transparent;">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#loginTab">Вход</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#registerTab">Регистрация</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body p-3 p-md-4">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="loginTab">
                                <?php if ($error): ?>
                                    <div class="alert alert-danger"><?= $error ?></div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Логин</label>
                                        <input type="text" name="username" class="form-control" placeholder="Введите логин" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Пароль</label>
                                        <input type="password" name="password" class="form-control" placeholder="Введите пароль" required>
                                    </div>
                                    <button type="submit" name="login" class="btn btn-primary w-100" style="background: linear-gradient(45deg, #667eea, #764ba2); border: none;">
                                        <i class="fas fa-sign-in-alt"></i> Войти
                                    </button>
                                </form>
                            </div>
                            
                            <div class="tab-pane fade" id="registerTab">
                                <?php if ($register_error): ?>
                                    <div class="alert alert-danger"><?= $register_error ?></div>
                                <?php endif; ?>
                                <?php if ($register_success): ?>
                                    <div class="alert alert-success"><?= $register_success ?></div>
                                <?php endif; ?>
                                
                                <form method="POST">
                                    <div class="mb-3">
                                        <label class="form-label">Логин *</label>
                                        <input type="text" name="reg_username" class="form-control" placeholder="Придумайте логин" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Пароль *</label>
                                        <input type="password" name="reg_password" class="form-control" placeholder="Минимум 4 символа" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Подтверждение пароля *</label>
                                        <input type="password" name="reg_confirm_password" class="form-control" placeholder="Повторите пароль" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">ФИО *</label>
                                        <input type="text" name="reg_full_name" class="form-control" placeholder="Ваше полное имя" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="reg_email" class="form-control" placeholder="example@mail.ru">
                                    </div>
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle"></i> При регистрации создаётся аккаунт ученика. Для получения роли преподавателя или администратора обратитесь к администратору.
                                    </div>
                                    <button type="submit" name="register" class="btn btn-success w-100" style="background: linear-gradient(45deg, #28a745, #20c997); border: none;">
                                        <i class="fas fa-user-plus"></i> Зарегистрироваться
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <hr class="my-3 my-md-4">
                        
                        <div class="text-center">
                            <small class="text-muted">Тестовые аккаунты:</small><br>
                            <small class="text-muted">
                                <strong>Администратор:</strong> admin / admin123<br>
                                <strong>Преподаватель:</strong> ivanov / ivanov123<br>
                                <strong>Студент:</strong> student_ivan / student123
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>