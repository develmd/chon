<?php
function generateToken() {
    return bin2hex(random_bytes(16));
}

function generateQRCode($token, $schedule_id, $date) {
    require_once __DIR__ . '/phpqrcode.php';
    
    $filename = 'qr_' . $schedule_id . '_' . $date . '_' . $token . '.png';
    $filepath = QR_DIR . '/' . $filename;
    
    $data = $token;
    QRcode::png($data, $filepath, QR_ECLEVEL_L, 4);
    
    return $filepath;
}

function getQRCodeInfo($token) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            active_sessions.qr_code_id,
            active_sessions.schedule_id,
            schedule.discipline,
            schedule.teacher,
            schedule.classroom,
            schedule.start_time,
            schedule.end_time,
            qr_codes.date,
            qr_codes.token as qr_token
        FROM active_sessions
        INNER JOIN schedule ON active_sessions.schedule_id = schedule.id
        INNER JOIN qr_codes ON active_sessions.qr_code_id = qr_codes.id
        WHERE active_sessions.session_token = ? 
        AND active_sessions.is_active = 1 
        AND active_sessions.expires_at > datetime('now')
    ");
    $stmt->execute([$token]);
    $result = $stmt->fetch();
    
    if ($result) {
        return [
            'id' => $result['qr_code_id'],
            'schedule_id' => $result['schedule_id'],
            'date' => $result['date'],
            'token' => $result['qr_token'],
            'discipline' => $result['discipline'],
            'teacher' => $result['teacher'],
            'classroom' => $result['classroom'],
            'start_time' => $result['start_time'],
            'end_time' => $result['end_time']
        ];
    }
    
    $stmt = $pdo->prepare("
        SELECT 
            qr_codes.id,
            qr_codes.schedule_id,
            qr_codes.date,
            qr_codes.token,
            schedule.discipline,
            schedule.teacher,
            schedule.classroom,
            schedule.start_time,
            schedule.end_time
        FROM qr_codes
        INNER JOIN schedule ON qr_codes.schedule_id = schedule.id
        WHERE qr_codes.token = ?
    ");
    $stmt->execute([$token]);
    return $stmt->fetch();
}

function checkLateness($scan_time, $start_time, $grace_minutes = 5) {
    $scan = strtotime($scan_time);
    $start = strtotime($start_time);
    $diff_minutes = ($scan - $start) / 60;
    
    return ($diff_minutes <= $grace_minutes) ? 'present' : 'late';
}

function sendNotification($student_id, $attendance_id, $student_name, $parent_email, $discipline, $date, $status, $scan_time) {
    global $pdo;
    
    $status_text = ($status == 'present') ? 'присутствовал(а) вовремя' : 'опоздал(а)';
    $message = "Уважаемый родитель!\n\n";
    $message .= "Студент: {$student_name}\n";
    $message .= "Занятие: {$discipline}\n";
    $message .= "Дата: {$date}\n";
    $message .= "Время отметки: {$scan_time}\n";
    $message .= "Статус: {$status_text}\n\n";
    $message .= "С уважением, система CHECKON";
    
    $sent_status = 'sent';
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications_log (student_id, attendance_id, sent_at, status)
        VALUES (?, ?, datetime('now'), ?)
    ");
    $stmt->execute([$student_id, $attendance_id, $sent_status]);
    
    return true;
}
function notifyTeacher($schedule_id, $student_name, $status) {
    $log_file = __DIR__ . '/notifications.log';
    $log_entry = date('Y-m-d H:i:s') . " - Студент: {$student_name}, Занятие ID: {$schedule_id}, Статус: {$status}\n";
    file_put_contents($log_file, $log_entry, FILE_APPEND);
    return true;
}

function getGroups() {
    global $pdo;
    $stmt = $pdo->query("SELECT DISTINCT group_name FROM students ORDER BY group_name");
    return $stmt->fetchAll();
}

function getStudents($search = '', $group = '') {
    global $pdo;
    $sql = "SELECT * FROM students WHERE 1=1";
    $params = [];
    
    if ($search) {
        $sql .= " AND full_name LIKE ?";
        $params[] = "%{$search}%";
    }
    
    if ($group) {
        $sql .= " AND group_name = ?";
        $params[] = $group;
    }
    
    $sql .= " ORDER BY full_name";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function getTodayStats() {
    global $pdo;
    $today = date('Y-m-d');
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present,
            SUM(CASE WHEN status = 'late' THEN 1 ELSE 0 END) as late
        FROM attendance
        WHERE DATE(scan_time) = ?
    ");
    $stmt->execute([$today]);
    $stats = $stmt->fetch();
    
    return [
        'total' => $stats['total'] ?? 0,
        'present' => $stats['present'] ?? 0,
        'late' => $stats['late'] ?? 0
    ];
}

function getTodaySchedule() {
    global $pdo;
    $today = date('N');
    
    $stmt = $pdo->prepare("
        SELECT * FROM schedule
        WHERE day_of_week = ?
        ORDER BY start_time
    ");
    $stmt->execute([$today]);
    return $stmt->fetchAll();
}
?>