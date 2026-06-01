<nav class="navbar navbar-expand-lg navbar-dark" style="background: linear-gradient(45deg, #0f0f1a, #1a1a2e); border-bottom: 1px solid #333;">
    <div class="container">
        <a class="navbar-brand" href="index.php">
            <i class="fas fa-check-circle" style="color: #667eea;"></i> CHECKON
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="fas fa-home"></i> Главная
                    </a>
                </li>
                <?php if (isset($_SESSION['role']) && ($_SESSION['role'] == 'admin' || $_SESSION['role'] == 'teacher')): ?>
                <li class="nav-item">
                    <a class="nav-link" href="students.php">
                        <i class="fas fa-users"></i> Студенты
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="schedule.php">
                        <i class="fas fa-calendar"></i> Расписание
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="generate_qr.php">
                        <i class="fas fa-qrcode"></i> QR-коды
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="attendance.php">
                        <i class="fas fa-clipboard-list"></i> Посещаемость
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="reports.php">
                        <i class="fas fa-chart-bar"></i> Отчёты
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="notifications.php">
                        <i class="fas fa-bell"></i> Уведомления
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="users.php">
                        <i class="fas fa-user-plus"></i> Пользователи
                    </a>
                </li>
                <?php endif; ?>
                <?php if (isset($_SESSION['role']) && $_SESSION['role'] == 'student'): ?>
                <li class="nav-item">
                    <a class="nav-link" href="scan.php">
                        <i class="fas fa-camera"></i> Отметка
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            <ul class="navbar-nav">
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" style="color: #eee;">
                        <i class="fas fa-user-circle"></i> 
                        <?= htmlspecialchars($_SESSION['full_name'] ?? 'Пользователь') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" style="background: #1a1a2e; border: 1px solid #333;">
                        <li><a class="dropdown-item" href="logout.php" style="color: #eee;">
                            <i class="fas fa-sign-out-alt"></i> Выйти
                        </a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="login.php" style="color: #eee;">
                        <i class="fas fa-sign-in-alt"></i> Вход
                    </a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>