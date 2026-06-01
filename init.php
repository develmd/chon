<?php
require_once 'config.php';

function initDatabase() {
    global $pdo;
    
    $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name='users'")->fetch();
    
    $sqls = [
        "CREATE TABLE IF NOT EXISTS students (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            full_name TEXT NOT NULL,
            group_name TEXT NOT NULL,
            parent_email TEXT,
            parent_phone TEXT
        )",
        
        "CREATE TABLE IF NOT EXISTS schedule (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            discipline TEXT NOT NULL,
            teacher TEXT NOT NULL,
            classroom TEXT NOT NULL,
            day_of_week INTEGER NOT NULL,
            start_time TEXT NOT NULL,
            end_time TEXT NOT NULL
        )",
        
        "CREATE TABLE IF NOT EXISTS qr_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            schedule_id INTEGER NOT NULL,
            date TEXT NOT NULL,
            token TEXT UNIQUE NOT NULL,
            generated_at TEXT,
            FOREIGN KEY (schedule_id) REFERENCES schedule(id)
        )",
        
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
        
        "CREATE TABLE IF NOT EXISTS notifications_log (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            student_id INTEGER NOT NULL,
            attendance_id INTEGER NOT NULL,
            sent_at TEXT,
            status TEXT
        )",
        
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
        )",
        
        "CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password TEXT NOT NULL,
            full_name TEXT NOT NULL,
            email TEXT,
            role TEXT NOT NULL CHECK(role IN ('admin', 'teacher', 'student')),
            student_id INTEGER,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id)
        )"
    ];
    
    foreach ($sqls as $sql) {
        $pdo->exec($sql);
    }
    
    $check_users = $pdo->query("SELECT COUNT(*) as count FROM users")->fetch();
    if ($check_users['count'] > 0) {
        return;
    }
    
    $students = [
        ['Иванов Иван Иванович', 'ИП-11', 'ivanov_parent@example.com', '+7(999)111-11-11'],
        ['Петрова Анна Сергеевна', 'ИП-52', 'petrova_parent@example.com', '+7(999)222-22-22'],
        ['Сидоров Алексей Владимирович', 'ИП-11', 'sidorov_parent@example.com', '+7(999)333-33-33'],
        ['Козлова Екатерина Дмитриевна', 'ИП-13', 'kozlova_parent@example.com', '+7(999)444-44-44'],
        ['Морозов Дмитрий Андреевич', 'ИП-15', 'morozov_parent@example.com', '+7(999)555-55-55'],
        ['Волкова Ольга Петровна', 'ИП-67', 'volkova_parent@example.com', '+7(999)666-66-66'],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO students (full_name, group_name, parent_email, parent_phone) VALUES (?, ?, ?, ?)");
    foreach ($students as $student) {
        $stmt->execute($student);
    }
    
    $schedule = [
        ['Основы программирования', 'Иванов А.Б.', '101', 1, '09:00', '10:30'],
        ['Базы данных', 'Петров В.Г.', '102', 1, '10:45', '12:15'],
        ['Веб-технологии', 'Сидоров Д.Е.', '103', 2, '09:00', '10:30'],
        ['Операционные системы', 'Козлова Е.Ж.', '104', 3, '13:00', '14:30'],
        ['Компьютерные сети', 'Морозов К.И.', '105', 4, '10:45', '12:15'],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO schedule (discipline, teacher, classroom, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($schedule as $item) {
        $stmt->execute($item);
    }
    
    $users = [
        ['admin', md5('admin123'), 'Администратор Системы', 'admin@checkon.ru', 'admin', null],
        ['developer', md5('dev123'), 'Разработчик Системы', 'dev@checkon.ru', 'admin', null],
        ['ivanov', md5('ivanov123'), 'Иванов А.Б.', 'ivanov@school.ru', 'teacher', null],
        ['petrov', md5('petrov123'), 'Петров В.Г.', 'petrov@school.ru', 'teacher', null],
        ['sidorov', md5('sidorov123'), 'Сидоров Д.Е.', 'sidorov@school.ru', 'teacher', null],
        ['student_ivan', md5('student123'), 'Иванов Иван', 'ivan@student.ru', 'student', 1],
        ['student_petrova', md5('student123'), 'Петрова Анна', 'petrova@student.ru', 'student', 2],
        ['student_sidorov', md5('student123'), 'Сидоров Алексей', 'sidorov@student.ru', 'student', 3],
    ];
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, student_id) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($users as $user) {
        $stmt->execute($user);
    }
    
    require_once 'functions.php';
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("INSERT INTO qr_codes (schedule_id, date, token, generated_at) VALUES (?, ?, ?, datetime('now'))");
    $stmt->execute([1, $today, generateToken()]);
    $stmt->execute([2, $today, generateToken()]);
    $stmt->execute([3, $today, generateToken()]);
}

initDatabase();
?>