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

function checkLateness($scan_time, $start_time, $grace_minutes = 10) {
    $scan = strtotime($scan_time);
    $start = strtotime($start_time);
    $diff_minutes = ($scan - $start) / 60;
    
    return ($diff_minutes <= $grace_minutes) ? 'present' : 'late';
}

function sendTelegramNotification($chat_id, $student_name, $discipline, $date, $status, $scan_time) {
    global $site_config;
    
    if (!$site_config['telegram_enabled']) {
        error_log("Telegram: отправка отключена в настройках");
        return false;
    }
    
    if (empty($chat_id)) {
        error_log("Telegram: chat_id пустой");
        return false;
    }
    
    $botToken = $site_config['telegram_bot_token'];
    $status_text = ($status == 'present') ? 'ПРИСУТСТВОВАЛ ВОВРЕМЯ' : 'ОПОЗДАЛ';
    $status_emoji = ($status == 'present') ? '✅' : '⚠️';
    
    $message = "📚 CHECKON - Уведомление о посещаемости\n\n";
    $message .= "👨‍🎓 Студент: {$student_name}\n";
    $message .= "📖 Занятие: {$discipline}\n";
    $message .= "📅 Дата: {$date}\n";
    $message .= "⏰ Время отметки: {$scan_time}\n";
    $message .= "{$status_emoji} Статус: {$status_text}\n\n";
    $message .= "С уважением, система CHECKON";
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $postData = [
        'chat_id' => $chat_id,
        'text' => $message
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_PROXY, 'socks5h://127.0.0.1:9050');
    curl_setopt($ch, CURLOPT_PROXYTYPE, CURLPROXY_SOCKS5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode == 200) {
        $response = json_decode($result, true);
        if (isset($response['ok']) && $response['ok'] === true) {
            error_log("Telegram: сообщение отправлено в чат $chat_id");
            return true;
        } else {
            error_log("Telegram API error: " . ($response['description'] ?? 'unknown'));
        }
    } else {
        error_log("Telegram HTTP error: $httpCode, curl: $curlError");
    }
    
    return false;
}

function sendEmailNotification($to_email, $student_name, $discipline, $date, $status, $scan_time) {
    global $site_config;
    
    if (!$site_config['email_enabled']) {
        return false;
    }
    
    $status_text = ($status == 'present') ? 'присутствовал(а) вовремя' : 'опоздал(а)';
    
    $subject = "CHECKON - Посещаемость: {$student_name}";
    $message = "Уважаемый родитель!\n\n";
    $message .= "Студент: {$student_name}\n";
    $message .= "Занятие: {$discipline}\n";
    $message .= "Дата: {$date}\n";
    $message .= "Время отметки: {$scan_time}\n";
    $message .= "Статус: {$status_text}\n\n";
    $message .= "С уважением, система CHECKON\n";
    $message .= $site_config['site_url'];
    
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/plain; charset=utf-8\r\n";
    $headers .= "From: CHECKON <noreply@checkon.ru2rdp.ip4.icu>\r\n";
    
    return mail($to_email, $subject, $message, $headers);
}

function sendNotification($student_id, $attendance_id, $student_name, $parent_email, $discipline, $date, $status, $scan_time) {
    global $pdo, $site_config;
    
    $stmt = $pdo->prepare("SELECT telegram_id FROM students WHERE id = ?");
    $stmt->execute([$student_id]);
    $student = $stmt->fetch();
    $telegram_id = $student['telegram_id'];
    
    $sent_via = [];
    
    if (!empty($telegram_id) && $site_config['telegram_enabled']) {
        if (sendTelegramNotification($telegram_id, $student_name, $discipline, $date, $status, $scan_time)) {
            $sent_via[] = 'telegram';
        }
    }
    
    if (!empty($parent_email) && $site_config['email_enabled']) {
        if (sendEmailNotification($parent_email, $student_name, $discipline, $date, $status, $scan_time)) {
            $sent_via[] = 'email';
        }
    }
    
    if (empty($sent_via)) {
        $sent_via[] = 'log';
    }
    
    $sent_status = 'sent';
    $sent_via_str = implode(',', $sent_via);
    
    $stmt = $pdo->prepare("
        INSERT INTO notifications_log (student_id, attendance_id, sent_at, status, sent_via)
        VALUES (?, ?, datetime('now'), ?, ?)
    ");
    $stmt->execute([$student_id, $attendance_id, $sent_status, $sent_via_str]);
    
    return !empty($sent_via);
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
