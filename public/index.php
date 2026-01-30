<?php
// 1. ENABLE ERROR REPORTING
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Start Buffer
ob_start(); 

// 2. Load Config
require_once __DIR__ . '/../src/config.php';

// 3. Determine Page
$page = $_GET['page'] ?? 'dashboard';
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// 4. Page Allowlist
$allowed_pages = [
    'login', 'dashboard', 'pos', 'shifts', 'products', 'reports', 
    'inventory', 'users', 'settings', 'kds', 'pickup', 'change_password',
    'receive', 'transfers', 'receipt', 'vendors', 'locations', 'categories', 'menu'
];

if (!in_array($page, $allowed_pages)) {
    $page = 'dashboard'; 
}

// 5. Authentication Check
if (!isset($_SESSION['user_id']) && $page !== 'login') {
    header("Location: index.php?page=login");
    exit;
}

// --- NEW: FORCE PASSWORD CHANGE GATEKEEPER ---
if (isset($_SESSION['force_change']) && $_SESSION['force_change'] == 1) {
    // Only allow them to be on the change_password page OR to logout
    if ($page !== 'change_password' && $action !== 'logout') {
        header("Location: index.php?page=change_password");
        exit;
    }
}
// ---------------------------------------------

// 6. Logout Logic
if ($action === 'logout') {
    session_destroy();
    header("Location: index.php?page=login");
    exit;
}

// 7. Load Logic (Controller)
$logicFile = __DIR__ . "/../src/$page.php";
if (file_exists($logicFile)) {
    require_once $logicFile;
}

// 8. Render View
$hideLayout = in_array($page, ['login', 'pos', 'kds', 'pickup', 'receipt']);

if (!$hideLayout) {
    require_once __DIR__ . '/../templates/header.php';
}

$viewFile = __DIR__ . "/../templates/$page.php";
if (file_exists($viewFile)) {
    require_once $viewFile;
} else {
    echo "<div class='alert alert-warning'>View not found: $page</div>";
}

if (!$hideLayout) {
    require_once __DIR__ . '/../templates/footer.php';
}

ob_end_flush();
?>
