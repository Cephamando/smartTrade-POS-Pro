<?php
// 1. ENABLE ERROR REPORTING
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();

// 2. DEFINE BASE PATH
define('BASE_PATH', dirname(__DIR__));

// 3. LOAD DATABASE CONFIGURATION
$configFile = BASE_PATH . '/src/config.php';

if (file_exists($configFile)) {
    require_once $configFile;
} else {
    die("<h1>System Error</h1><p>Configuration file not found.</p>");
}

// 4. HANDLE GLOBAL ACTIONS (Standalone AJAX/API requests)
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    // Handle Stock Level Check for Requisition Form
    if ($action === 'get_stock_level') {
        header('Content-Type: application/json');
        $product_id = $_GET['product_id'] ?? 0;
        $location_id = $_GET['location_id'] ?? 0;

        try {
            // Query live inventory table
            $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?");
            $stmt->execute([$product_id, $location_id]);
            $stock = $stmt->fetchColumn() ?: 0;
            echo json_encode(['stock' => (int)$stock]);
        } catch (Exception $e) {
            echo json_encode(['stock' => 0, 'error' => $e->getMessage()]);
        }
        exit; // Stop execution after serving AJAX
    }

    // Handle Logout
    if ($action === 'logout') {
        session_destroy();
        header("Location: index.php?page=login");
        exit;
    }
}

// 5. GET PAGE REQUEST
$page = isset($_GET['page']) ? basename($_GET['page']) : 'login';

// 6. ROUTING LOGIC
$controllerPath = BASE_PATH . "/src/{$page}.php";
$templatePath   = BASE_PATH . "/templates/{$page}.php";

if (file_exists($controllerPath)) {
    require_once $controllerPath;

    if (file_exists($templatePath)) {
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
    if (isset($_SESSION['user_id'])) {
        header("Location: index.php?page=dashboard");
    } else {
        header("Location: index.php?page=login");
    }
    exit;
}
?>
