<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'dev') {
    header("Location: index.php"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    $fields = ['business_name', 'license_tier', 'theme_color', 'receipt_header', 'receipt_footer', 'lockout_date'];
    
    foreach ($fields as $key) {
        if (isset($_POST[$key])) {
            $val = $_POST[$key];
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
            $stmt->execute([$key, $val, $val]);
        }
    }
    $_SESSION['swal_type'] = 'success';
    $_SESSION['swal_msg'] = "System Configuration Saved.";
    header("Location: index.php?page=settings"); exit;
}

$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$sysSettings = [];
while ($sRow = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $sysSettings[$sRow['setting_key']] = $sRow['setting_value'];
}
?>
