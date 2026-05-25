<?php
require_once 'config.php';

function initDatabase() {
    global $pdo;
    
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='students'")->fetch();
    
    $sqls = [
        // Студенты
        "CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            group_name TEXT NOT NULL,
            parent_email TEXT,
            parent_phone TEXT
        )",
        
        // Расписание
        "CREATE TABLE IF NOT EXISTS schedule (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            discipline TEXT NOT NULL,
            teacher TEXT NOT NULL,
            classroom TEXT NOT NULL,
            day_of_week INTEGER NOT NULL,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL
        )",
        
        // QR-коды
        "CREATE TABLE IF NOT EXISTS qr_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            schedule_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            token TEXT UNIQUE NOT NULL,
            generated_at TEXT,
            FOREIGN KEY (schedule_id) REFERENCES schedule(id)
        )",
        
        // Посещаемость
        "CREATE TABLE IF NOT EXISTS attendance (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            schedule_id INTEGER NOT NULL,
            qr_code_id INTEGER NOT NULL,
            scan_time TEXT NOT NULL,
            status TEXT NOT NULL,
            is_confirmed INTEGER DEFAULT 0,
            FOREIGN KEY (student_id) REFERENCES students(id),
            FOREIGN KEY (schedule_id) REFERENCES schedule(id),
            FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id)
        )",
        
        // Лог уведомлений
        "CREATE TABLE IF NOT EXISTS notifications_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            attendance_id INTEGER NOT NULL,
            sent_at TEXT,
            status TEXT
        )",
        
        // Активные сессии QR
        "CREATE TABLE IF NOT EXISTS active_sessions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            schedule_id INTEGER NOT NULL,
            qr_code_id INTEGER NOT NULL,
            session_token TEXT UNIQUE NOT NULL,
            is_active INTEGER DEFAULT 1,
            expires_at TEXT NOT NULL,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (schedule_id) REFERENCES schedule(id),
            FOREIGN KEY (qr_code_id) REFERENCES qr_codes(id)
        )"
    ];
    
    foreach ($sqls as $sql) {
        $pdo->exec($sql);
    }
    
    $check = $pdo->query("SELECT COUNT(*) as count FROM students")->fetch();
    if ($check['count'] > 0) {
        return;
    }
    // Студенты
    $students = [
        ['Иванов Иван Иванович', 'ИП-11', 'ivanov_parent@example.com', '+7(999)111-11-11'],
        ['Петрова Анна Сергеевна', 'ИП-52', 'petrova_parent@example.com', '+7(999)222-22-22'],
        ['Сидоров Алексей Владимирович', 'ИП-11', 'sidorov_parent@example.com', '+7(999)333-33-33'],
        ['Козлова Екатерина Дмитриевна', 'ИП-13', 'kozlova_parent@example.com', '+7(999)444-44-44'],
        ['Морозов Дмитрий Андреевич', 'ИП-15', 'morozov_parent@example.com', '+7(999)555-55-55'],
        ['Волкова Ольга Петровна', 'ИП-67', 'volkova_parent@example.com', '+7(999)666-66-66'],
        ['Смирнов Михаил Александрович', 'ИП-11', 'smirnov_parent@example.com', '+7(999)777-77-77'],
        ['Кузнецова Мария Игоревна', 'ИП-52', 'kuznetsova_parent@example.com', '+7(999)888-88-88'],
        ['Попов Андрей Николаевич', 'ИП-13', 'popov_parent@example.com', '+7(999)999-99-99'],
        ['Васильева Елена Викторовна', 'ИП-15', 'vasilieva_parent@example.com', '+7(999)000-00-01'],
        ['Соколов Артем Сергеевич', 'ИП-67', 'sokolov_parent@example.com', '+7(999)000-00-02'],
        ['Михайлова Дарья Алексеевна', 'ИП-11', 'mihailova_parent@example.com', '+7(999)000-00-03'],
        ['Новиков Егор Денисович', 'ИП-52', 'novikov_parent@example.com', '+7(999)000-00-04'],
        ['Федорова Алиса Романовна', 'ИП-13', 'fedorova_parent@example.com', '+7(999)000-00-05'],
        ['Морозов Илья Олегович', 'ИП-15', 'morozov2_parent@example.com', '+7(999)000-00-06'],
        ['Волкова Яна Владиславовна', 'ИП-67', 'volkova2_parent@example.com', '+7(999)000-00-07'],
        ['Лебедев Никита Петрович', 'ИП-11', 'lebedev_parent@example.com', '+7(999)000-00-08'],
        ['Семенова Кристина Юрьевна', 'ИП-52', 'semenova_parent@example.com', '+7(999)000-00-09'],
        ['Егоров Максим Тимофеевич', 'ИП-13', 'egorov_parent@example.com', '+7(999)000-00-10'],
        ['Полякова София Борисовна', 'ИП-15', 'polyakova_parent@example.com', '+7(999)000-00-11'],
        ['Степанов Богдан Артурович', 'ИП-67', 'stepanov_parent@example.com', '+7(999)000-00-12'],
        ['Николаева Юлия Кирилловна', 'ИП-11', 'nikolaeva_parent@example.com', '+7(999)000-00-13'],
        ['Козлов Владислав Вадимович', 'ИП-52', 'kozlov2_parent@example.com', '+7(999)000-00-14'],
        ['Захарова Анна Матвеевна', 'ИП-13', 'zaharova_parent@example.com', '+7(999)000-00-15'],
        ['Орлов Даниил Русланович', 'ИП-15', 'orlov_parent@example.com', '+7(999)000-00-16'],
        ['Макарова Ева Евгеньевна', 'ИП-67', 'makarova_parent@example.com', '+7(999)000-00-17'],
        ['Никитин Павел Григорьевич', 'ИП-11', 'nikitin_parent@example.com', '+7(999)000-00-18'],
        ['Гусева Ксения Витальевна', 'ИП-52', 'guseva_parent@example.com', '+7(999)000-00-19'],
        ['Тарасов Тимур потапович', 'ИП-13', 'tarasov_parent@example.com', '+7(999)000-00-20'],
        ['Жукова Алина ярославовна', 'ИП-15', 'zhukova_parent@example.com', '+7(999)000-00-21']
    ];
    
    
    $stmt = $pdo->prepare("INSERT INTO students (full_name, group_name, parent_email, parent_phone) VALUES (?, ?, ?, ?)");
    foreach ($students as $student) {
        $stmt->execute($student);
    }
    // Учителя
    $schedule = [
        ['Основы программирования', 'Иванов А.Б.', '101', 1, '09:00', '10:30'],
        ['Базы данных', 'Петров В.Г.', '102', 1, '10:45', '12:15'],
        ['Веб-технологии', 'Сидоров Д.Е.', '103', 2, '09:00', '10:30'],
        ['Операционные системы', 'Козлова Е.Ж.', '104', 3, '13:00', '14:30'],
        ['Компьютерные сети', 'Морозов К.И.', '105', 4, '10:45', '12:15'],
        ['Алгоритмы и структуры данных', 'Смирнов М.Н.', '201', 1, '13:00', '14:30'],
        ['Дискретная математика', 'Кузнецова О.П.', '202', 2, '10:45', '12:15'],
        ['Объектно-ориентированное программирование', 'Попов С.В.', '203', 2, '13:00', '14:30'],
        ['Тестирование ПО', 'Васильева Т.А.', '204', 3, '09:00', '10:30'],
        ['Информационная безопасность', 'Соколов Ю.Д.', '205', 3, '10:45', '12:15'],
        ['Архитектура ЭВМ', 'Новиков И.М.', '301', 4, '09:00', '10:30'],
        ['Управление проектами', 'Федорова Л.С.', '302', 4, '13:00', '14:30'],
        ['Системное администрирование', 'Лебедев А.П.', '303', 5, '09:00', '10:30'],
        ['Разработка мобильных приложений', 'Семенова Н.В.', '304', 5, '10:45', '12:15'],
        ['Искусственный интеллект', 'Егоров К.Е.', '305', 5, '13:00', '14:30']
    ];
    
    
    $stmt = $pdo->prepare("INSERT INTO schedule (discipline, teacher, classroom, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($schedule as $item) {
        $stmt->execute($item);
    }

    // Таблица преподавателей
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS teachers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            full_name TEXT NOT NULL,
            email TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM teachers");
    $stmt->execute();
    $count = $stmt->fetch()['count'];
    
    //авторицазия в лк учителя
    if ($count == 0) {
        $teachers = [
            ['ivanov', md5('ivanov123'), 'Иванов А.Б.', 'ivanov@school.ru'],
            ['petrov', md5('petrov123'), 'Петров В.Г.', 'petrov@school.ru'],
            ['sidorov', md5('sidorov123'), 'Сидоров Д.Е.', 'sidorov@school.ru'],
            ['admin', md5('admin123'), 'Администратор', 'admin@school.ru']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO teachers (username, password, full_name, email) VALUES (?, ?, ?, ?)");
        foreach ($teachers as $teacher) {
            $stmt->execute($teacher);
        }
    }
    
    require_once 'functions.php';
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    
    $stmt = $pdo->prepare("INSERT INTO qr_codes (schedule_id, date, token, generated_at) VALUES (?, ?, ?, datetime('now'))");
    $stmt->execute([1, $today, generateToken()]);
    $stmt->execute([2, $today, generateToken()]);
    $stmt->execute([3, $tomorrow, generateToken()]);
    
    $stmt = $pdo->prepare("SELECT id FROM qr_codes WHERE schedule_id = 1 AND date = ?");
    $stmt->execute([$today]);
    $qr = $stmt->fetch();
    
    if ($qr) {
        $session_token = generateToken();
        $expires_at = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        $stmt = $pdo->prepare("
            INSERT INTO active_sessions (schedule_id, qr_code_id, session_token, is_active, expires_at)
            VALUES (1, ?, ?, 1, ?)
        ");
        $stmt->execute([$qr['id'], $session_token, $expires_at]);
    }
    
    echo "<div class='alert alert-success'>База данных успешно инициализирована!</div>";
}

initDatabase();
?>