<?php
// 1. ENABLE ERROR REPORTING
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// 2. DEFINE BASE PATH (The root of your project)
define('BASE_PATH', dirname(__DIR__));

// 3. LOAD DATABASE CONFIGURATION (User specified location)
$configFile = BASE_PATH . '/src/config.php';

if (file_exists($configFile)) {
    require_once $configFile;
} else {
    die("<h1>System Error</h1><p>Configuration file not found at: <b>" . htmlspecialchars($configFile) . "</b></p>");
}

// 4. GET PAGE REQUEST
$page = isset($_GET['page']) ? basename($_GET['page']) : 'login';

// 5. HANDLE LOGOUT
if (isset($_GET['action']) && $_GET['action'] == 'logout') {
    session_destroy();
    header("Location: index.php?page=login");
    exit;
}

// 6. ROUTING LOGIC
$controllerPath = BASE_PATH . "/src/{$page}.php";
$templatePath   = BASE_PATH . "/templates/{$page}.php";

if (file_exists($controllerPath)) {
    // Load the Controller (Logic)
    require_once $controllerPath;

    // Load the View (Template)
    if (file_exists($templatePath)) {
        // Only load Header/Footer for standard pages (exclude Login, Receipt, simple scripts)
        $isStandalone = in_array($page, ['login', 'receipt']);
        
        if (!$isStandalone && file_exists(BASE_PATH . "/templates/header.php")) {
            require_once BASE_PATH . "/templates/header.php";
        }
        
        require_once $templatePath;
        
        if (!$isStandalone && file_exists(BASE_PATH . "/templates/footer.php")) {
            require_once BASE_PATH . "/templates/footer.php";
        }
    }
} else {
    // 404 Handler - Redirect to Login or Dashboard
    if (isset($_SESSION['user_id'])) {
        header("Location: index.php?page=dashboard");
    } else {
        header("Location: index.php?page=login");
    }
    exit;
}
?>
