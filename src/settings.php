<?php
// SECURITY: Admins and Managers Only
$userRole = strtolower($_SESSION['role'] ?? '');
if (!in_array($userRole, ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect all POST variables
    $settingsToSave = [
        'business_name' => $_POST['business_name'] ?? '',
        'receipt_header' => $_POST['receipt_header'] ?? '',
        'receipt_footer' => $_POST['receipt_footer'] ?? '',
        'theme_color' => $_POST['theme_color'] ?? '#2c2c2c',
        'theme_accent' => $_POST['theme_accent'] ?? '#ffc107',
        'theme_cart' => $_POST['theme_cart'] ?? '#3e2723'
    ];

    // SECURE DEV OVERRIDE: Only devs can save license/lockout changes
    if ($userRole === 'dev') {
        if (isset($_POST['license_tier'])) {
            $tier = strtolower($_POST['license_tier']);
            // Fallback safety: If an old cached browser sends 'hospitality', force it to 'pro+'
            if ($tier === 'hospitality') {
                $tier = 'pro+';
            }
            $settingsToSave['license_tier'] = $tier;
        }
        if (isset($_POST['lockout_date'])) {
            $settingsToSave['lockout_date'] = $_POST['lockout_date'];
        }
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
        foreach ($settingsToSave as $key => $val) {
            $stmt->execute([$key, $val, $val]);
        }
        $pdo->commit();
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Settings saved successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = "Error saving settings: " . $e->getMessage();
    }
    header("Location: index.php?page=settings");
    exit;
}

// Fetch existing settings
$stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$settings = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>
