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

// --- 🌍 GLOBAL LOCATION SWITCH (ADMIN/DEV) 🌍 ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['global_switch_location'])) {
    if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dev'])) {
        $newLocId = (int)$_POST['target_location_id'];
        
        // Update all relevant session tracking variables
        $_SESSION['location_id'] = $newLocId;
        $_SESSION['pos_location_id'] = $newLocId;
        $_SESSION['manual_location_override'] = true; 
        
        // Fetch and update the display name for the header
        $stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
        $stmt->execute([$newLocId]);
        $_SESSION['location_name'] = $stmt->fetchColumn() ?: 'Unknown';
    }
    
    // Redirect back to the requested page cleanly
    $page = $_GET['page'] ?? 'dashboard';
    header("Location: index.php?page=" . urlencode($page));
    exit;
}
// ------------------------------------------------

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
$page = isset($_GET['page']) ? basename($_GET['page']) : '';

// If no specific page is requested, default based on session
if (empty($page)) {
    $page = isset($_SESSION['user_id']) ? 'dashboard' : 'login';
}

// --- 🔒 GLOBAL AUTHENTICATION CHECK 🔒 ---
$publicPages = ['login']; // Pages that do not require a user session

if (!in_array($page, $publicPages) && !isset($_SESSION['user_id'])) {
    // Destroy any lingering broken session data
    session_destroy(); 
    // Force unauthenticated users back to the login page
    header("Location: index.php?page=login");
    exit;
}

// Redirect logged-in users away from the login page if they accidentally go there
if ($page === 'login' && isset($_SESSION['user_id'])) {
    header("Location: index.php?page=dashboard");
    exit;
}
// -----------------------------------------

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
    // Fallback if requested page doesn't exist
    if (isset($_SESSION['user_id'])) {
        header("Location: index.php?page=dashboard");
    } else {
        header("Location: index.php?page=login");
    }
    exit;
}
?>
