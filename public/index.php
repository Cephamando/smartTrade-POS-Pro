<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_start();
define('BASE_PATH', dirname(__DIR__));
$configFile = BASE_PATH . '/src/config.php';

if (file_exists($configFile)) { require_once $configFile; } else { die("<h1>System Error</h1>"); }

$appSettings = [
    'license_tier' => 'lite', 
    'business_name' => 'OdeliaPOS', 
    'theme_color' => '#3e2723',
    'receipt_header' => '',
    'receipt_footer' => 'Thank you!',
    'lockout_date' => ''
];

try {
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'settings'");
    if ($tableCheck->rowCount() > 0) {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $appSettings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (Exception $e) {}

define('LICENSE_TIER', strtolower($appSettings['license_tier']));
define('APP_NAME', $appSettings['business_name']);
define('THEME_COLOR', $appSettings['theme_color']);
define('RECEIPT_HEADER', $appSettings['receipt_header']);
define('RECEIPT_FOOTER', $appSettings['receipt_footer']);

define('LOCKOUT_DATE', $appSettings['lockout_date']);
define('SYSTEM_LOCKED', (!empty(LOCKOUT_DATE) && strtotime(LOCKOUT_DATE) <= time()));

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['global_switch_location'])) {
    if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dev'])) {
        $newLocId = (int)$_POST['target_location_id'];
        $_SESSION['location_id'] = $newLocId;
        $_SESSION['pos_location_id'] = $newLocId;
        $_SESSION['manual_location_override'] = true; 
        $stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
        $stmt->execute([$newLocId]);
        $_SESSION['location_name'] = $stmt->fetchColumn() ?: 'Unknown';
    }
    $page = $_GET['page'] ?? 'dashboard';
    header("Location: index.php?page=" . urlencode($page));
    exit;
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    if ($action === 'check_ready_orders') {
        header('Content-Type: application/json');
        $locationId = $_SESSION['pos_location_id'] ?? $_SESSION['location_id'] ?? 0;
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE s.location_id = ? AND si.status = 'ready' AND si.fulfillment_status = 'uncollected'");
            $stmt->execute([$locationId]);
            echo json_encode(['count' => (int)$stmt->fetchColumn()]);
        } catch(Exception $e) { echo json_encode(['count' => 0, 'error' => $e->getMessage()]); }
        exit;
    }
    if ($action === 'get_shift_sales') {
        header('Content-Type: application/json');
        $shiftId = (int)($_GET['shift_id'] ?? 0);
        try {
            $stmt = $pdo->prepare("SELECT id, created_at, customer_name, final_total, payment_method FROM sales WHERE shift_id = ? AND payment_status = 'paid' ORDER BY created_at DESC");
            $stmt->execute([$shiftId]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) { echo json_encode(['error' => $e->getMessage()]); }
        exit;
    }
    if ($action === 'get_stock_level') {
        header('Content-Type: application/json');
        $product_id = $_GET['product_id'] ?? 0;
        $location_id = $_GET['location_id'] ?? 0;
        try {
            $stmt = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?");
            $stmt->execute([$product_id, $location_id]);
            echo json_encode(['stock' => (int)$stmt->fetchColumn() ?: 0]);
        } catch (Exception $e) { echo json_encode(['stock' => 0, 'error' => $e->getMessage()]); }
        exit;
    }
    if ($action === 'logout') {
        session_destroy();
        header("Location: index.php?page=login");
        exit;
    }
}

$page = isset($_GET['page']) ? basename($_GET['page']) : '';
$userRole = $_SESSION['role'] ?? 'cashier';

if (empty($page)) {
    if (isset($_SESSION['user_id'])) {
        if (in_array($userRole, ['admin', 'manager', 'dev'])) { $page = 'dashboard'; } 
        elseif (in_array($userRole, ['chef', 'head_chef']) && in_array(LICENSE_TIER, ['pro+', 'enterprise'])) { $page = 'kitchen'; } 
        else { $page = 'pos'; }
    } else {
        $page = 'login';
    }
}

$publicPages = ['login']; 
if (!in_array($page, $publicPages) && !isset($_SESSION['user_id'])) {
    session_destroy(); 
    header("Location: index.php?page=login");
    exit;
}
if ($page === 'login' && isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// --- 🛡️ STRICT ROLE-BASED ACCESS CONTROL (RBAC) ---
if (isset($_SESSION['user_id']) && !in_array($userRole, ['admin', 'manager', 'dev'])) {
    $allowedCommon = ['receipt', 'logout', 'pickup', 'print_shift', 'end_shift_action'];
    
    if (in_array($userRole, ['chef', 'head_chef']) && in_array(LICENSE_TIER, ['pro+', 'enterprise'])) {
        $allowed = array_merge($allowedCommon, ['kitchen', 'kds', 'menu', 'inventory']);
        if (!in_array($page, $allowed)) {
            $page = 'kitchen';
        }
    } else {
        $allowed = array_merge($allowedCommon, ['pos']);
        if (!in_array($page, $allowed)) {
            $page = 'pos';
        }
    }
}

// --- 🛡️ STRICT LICENSE TIER ENFORCEMENT ---
// Prevents forced URL browsing by Admins/Managers into locked tiers
if (isset($_SESSION['user_id'])) {
    $proPlusFeatures = ['kds', 'kitchen', 'menu', 'tables'];
    $proFeatures = ['members', 'audit', 'receive_stock', 'transfers', 'pickup'];
    
    if (in_array($page, $proPlusFeatures) && !in_array(LICENSE_TIER, ['pro+', 'enterprise'])) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = 'Feature locked. Requires Pro+ or Enterprise tier.';
        $page = in_array($userRole, ['admin', 'manager', 'dev']) ? 'dashboard' : 'pos';
    }
    
    if (in_array($page, $proFeatures) && LICENSE_TIER === 'lite') {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = 'Feature locked. Requires at least Pro tier.';
        $page = in_array($userRole, ['admin', 'manager', 'dev']) ? 'dashboard' : 'pos';
    }
}

// --- 🛑 SYSTEM LOCKOUT ENFORCEMENT ---
if (SYSTEM_LOCKED && isset($_SESSION['role']) && $_SESSION['role'] !== 'dev') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $page !== 'login') {
        die("<div style='padding:20px; color:red; font-family:sans-serif;'><h2>SYSTEM LOCKED</h2><p>Modifications disabled. License expired.</p><a href='index.php'>Go Back</a></div>");
    }
    if (!in_array($userRole, ['admin', 'manager'])) {
        if ($page !== 'logout') {
            die("<div style='text-align:center; padding-top:150px; font-family:sans-serif; background:#111; color:#fff; height:100vh; margin:0;'><h1 style='color:#ff4d4d; font-size: 3rem;'>SYSTEM LOCKED</h1><p style='font-size: 1.2rem; color: #ccc;'>Software license has expired. Please notify management.</p><br><br><a href='index.php?action=logout' style='color:#fff; background: #dc3545; border-radius: 5px; padding:15px 30px; text-decoration:none; font-weight: bold;'>LOGOUT</a></div>");
        }
    } else {
        $allowedWhenLocked = ['reports', 'z_read', 'logout', 'receipt', 'print_shift'];
        if (!in_array($page, $allowedWhenLocked)) {
            $page = 'reports'; 
        }
    }
}

// 6. ROUTING LOGIC
$controllerPath = BASE_PATH . "/src/{$page}.php";
$templatePath   = BASE_PATH . "/templates/{$page}.php";

if (file_exists($controllerPath)) {
    require_once $controllerPath;
    if (file_exists($templatePath)) {
        $isStandalone = in_array($page, ['login', 'receipt', 'pos', 'kds', 'pickup', 'print_shift']);
        
        if (!$isStandalone && file_exists(BASE_PATH . "/templates/header.php")) {
            require_once BASE_PATH . "/templates/header.php";
        }
        require_once $templatePath;
        if (!$isStandalone && file_exists(BASE_PATH . "/templates/footer.php")) {
            require_once BASE_PATH . "/templates/footer.php";
        }
    }
} else {
    header("Location: index.php");
    exit;
}
?>
