<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];

// --- 1. SUPER USER LOCATION SWITCHER ---
// Allow Admins/Devs to switch their session location
if (isset($_POST['global_switch_location']) && in_array($_SESSION['role'], ['admin', 'dev'])) {
    $newLocId = $_POST['target_location_id'];
    
    // Verify location exists
    $stmt = $pdo->prepare("SELECT id, name FROM locations WHERE id = ?");
    $stmt->execute([$newLocId]);
    $newLoc = $stmt->fetch();

    if ($newLoc) {
        $_SESSION['location_id'] = $newLoc['id'];
        $_SESSION['location_name'] = $newLoc['name'];
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Switched to " . $newLoc['name'];
    }
    
    // Refresh to apply changes
    header("Location: index.php?page=dashboard"); 
    exit;
}

// Get current working location
$locationId = $_SESSION['location_id'];

// Fetch all locations for the switcher dropdown (Admin/Dev only)
$allLocations = [];
if (in_array($_SESSION['role'], ['admin', 'dev'])) {
    $allLocations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
}

// --- 2. STANDARD DASHBOARD DATA ---
// Fetch User & Location Data
$user = $pdo->prepare("SELECT u.*, l.name as location_name FROM users u JOIN locations l ON u.location_id = l.id WHERE u.id = ?");
$user->execute([$userId]);
$userData = $user->fetch();

// Critical Stock Alerts
$stockAlerts = $pdo->prepare("SELECT p.id as product_id, p.name, i.quantity FROM inventory i JOIN products p ON i.product_id = p.id WHERE i.location_id = ? AND i.quantity < 3");
$stockAlerts->execute([$locationId]);
$lowStockItems = $stockAlerts->fetchAll();

// Auto-Transfer Logic (Warehouse to Bar)
$warehouseStmt = $pdo->prepare("SELECT id FROM locations WHERE type = 'warehouse' LIMIT 1");
$warehouseStmt->execute();
$warehouseId = $warehouseStmt->fetchColumn();

if ($warehouseId && $warehouseId != $locationId && !empty($lowStockItems)) {
    foreach ($lowStockItems as $item) {
        $check = $pdo->prepare("SELECT id FROM inventory_transfers WHERE product_id = ? AND dest_location_id = ? AND status = 'pending'");
        $check->execute([$item['product_id'], $locationId]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO inventory_transfers (source_location_id, dest_location_id, product_id, quantity, user_id, status, created_at) VALUES (?, ?, ?, 10, ?, 'pending', NOW())")
                ->execute([$warehouseId, $locationId, $item['product_id'], $userId]);
        }
    }
}

// Shift Stats
$stmt = $pdo->prepare("SELECT COUNT(*) as txn_count, COALESCE(SUM(final_total), 0) as shift_total FROM sales WHERE user_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'");
$stmt->execute([$userId]);
$myStats = $stmt->fetch();

$topItem = $pdo->prepare("SELECT p.name FROM sale_items si JOIN products p ON si.product_id = p.id JOIN sales s ON si.sale_id = s.id WHERE s.user_id = ? AND DATE(s.created_at) = CURDATE() GROUP BY p.id ORDER BY SUM(si.quantity) DESC LIMIT 1");
$topItem->execute([$userId]);
$myStats['top_item'] = $topItem->fetchColumn() ?: '-';
$myStats['shift_status'] = 'Active'; 

// Action Center
$tabStmt = $pdo->prepare("SELECT COUNT(id) as count, COALESCE(SUM(final_total), 0.00) as total FROM sales WHERE location_id = ? AND payment_status = 'pending'");
$tabStmt->execute([$locationId]);
$pendingTabs = $tabStmt->fetch();

$reqStmt = $pdo->prepare("SELECT COUNT(id) FROM inventory_transfers WHERE (source_location_id = ? OR dest_location_id = ?) AND status IN ('pending', 'in_transit')");
$reqStmt->execute([$locationId, $locationId]);
$pendingReqs = $reqStmt->fetchColumn();

// Active Staff Monitor (Admin/Manager/Dev)
$activeStaff = [];
if (in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    $staffSql = "SELECT u.id, u.username, u.role, l.name as location_name, s.start_time, s.id as shift_id 
                 FROM shifts s 
                 JOIN users u ON s.user_id = u.id 
                 JOIN locations l ON s.location_id = l.id 
                 WHERE s.status = 'open' 
                 ORDER BY s.start_time DESC";
    $activeStaff = $pdo->query($staffSql)->fetchAll();
}
?>
