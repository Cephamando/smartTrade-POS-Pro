<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// SECURITY: Strictly DEV only
if ($_SESSION['role'] !== 'dev') {
    die("<h1>Access Denied</h1><p>Only the System Developer can access this module.</p>");
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    try {
        $pdo->beginTransaction();
        
        $settingsToUpdate = [
            'license_tier' => $_POST['license_tier'] ?? 'lite',
            'business_name' => $_POST['business_name'] ?? 'OdeliaPOS',
            'theme_color' => $_POST['theme_color'] ?? '#3e2723',
            'receipt_header' => $_POST['receipt_header'] ?? '',
            'receipt_footer' => $_POST['receipt_footer'] ?? ''
        ];

        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");
        foreach ($settingsToUpdate as $key => $val) {
            $stmt->execute([$val, $key]);
        }
        
        $pdo->commit();
        $_SESSION['swal_type'] = 'success'; 
        $_SESSION['swal_msg'] = "System configuration updated successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_type'] = 'error'; 
        $_SESSION['swal_msg'] = "Error saving settings: " . $e->getMessage();
    }
    
    header("Location: index.php?page=settings");
    exit;
}

// Fetch Current Settings
$currentSettings = [];
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $currentSettings[$row['setting_key']] = $row['setting_value'];
}
?>
