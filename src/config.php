<?php
// Prevent direct access
if (basename($_SERVER['PHP_SELF']) == basename(__FILE__)) { die('Access denied'); }

// DB Settings - Docker Aware
$host = getenv('DB_HOST') ?: 'pos_db';
$db   = getenv('DB_NAME') ?: 'pos_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: 'posRoot123!';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    error_log($e->getMessage());
    die("Database Connection Error. Please check logs.");
}

// Start Session Globally
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>