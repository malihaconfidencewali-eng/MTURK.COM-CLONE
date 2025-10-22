<?php
// db.php
// Database connection - using credentials provided by user
session_start();

$DB_HOST = 'localhost';
$DB_NAME = 'dbv7ifghadtkcu';
$DB_USER = 'ueyhm8rqreljw';
$DB_PASS = 'gutn2hie5vxa';

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO("mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4", $DB_USER, $DB_PASS, $options);
} catch (Exception $e) {
    // Show helpful error (in production, hide details)
    echo "<h2>Database connection failed</h2><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
    exit;
}

// helper functions
function is_logged_in() {
    return !empty($_SESSION['user_id']);
}
function current_user() {
    global $pdo;
    if (!is_logged_in()) return null;
    $stmt = $pdo->prepare("SELECT id, name, email, role, balance FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}
?>
