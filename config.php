<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);

define('DB_PATH', __DIR__ . '/database.sqlite');
define('QR_DIR', __DIR__ . '/qrcodes');

if (!file_exists(QR_DIR)) {
    mkdir(QR_DIR, 0777, true);
}

try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Ошибка подключения к базе данных: " . $e->getMessage());
}

function query($sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isTeacher() {
    return isset($_SESSION['role']) && ($_SESSION['role'] === 'teacher' || $_SESSION['role'] === 'admin');
}

function isStudent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'student';
}

function requireAdmin() {
    if (!isAdmin()) {
        header('Location: index.php');
        exit;
    }
}

function requireTeacher() {
    if (!isTeacher()) {
        header('Location: index.php');
        exit;
    }
}

function requireAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit;
    }
}
?>