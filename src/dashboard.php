<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];
$locationId = $_SESSION['location_id'];

// 1. Fetch User & Location
$user = $pdo->prepare("SELECT u.*, l.name as location_name FROM users u JOIN locations l ON u.location_id = l.id WHERE u.id = ?");
$user->execute([$userId]);
$userData = $user->fetch();

// 2. Auto-Transfer Logic (Replenish from Warehouse)
$warehouseStmt = $pdo->prepare("SELECT id FROM locations WHERE type = 'warehouse' LIMIT 1");
$warehouseStmt->execute();
$warehouseId = $warehouseStmt->fetchColumn();

// 3. Critical Stock Alerts
$stockAlerts = $pdo->prepare("SELECT p.id as product_id, p.name, i.quantity FROM inventory i JOIN products p ON i.product_id = p.id WHERE i.location_id = ? AND i.quantity < 3");
$stockAlerts->execute([$locationId]);
$lowStockItems = $stockAlerts->fetchAll();

if ($warehouseId && $warehouseId != $locationId) {
    foreach ($lowStockItems as $item) {
        $check = $pdo->prepare("SELECT id FROM inventory_transfers WHERE product_id = ? AND dest_location_id = ? AND status = 'pending'");
        $check->execute([$item['product_id'], $locationId]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO inventory_transfers (source_location_id, dest_location_id, product_id, quantity, user_id, status, created_at) VALUES (?, ?, ?, 10, ?, 'pending', NOW())")
                ->execute([$warehouseId, $locationId, $item['product_id'], $userId]);
        }
    }
}

// 4. Shift Stats
$stmt = $pdo->prepare("SELECT COUNT(*) as txn_count, COALESCE(SUM(final_total), 0) as shift_total FROM sales WHERE user_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'");
$stmt->execute([$userId]);
$myStats = $stmt->fetch();

// 5. Top Selling Item
$topItem = $pdo->prepare("SELECT p.name FROM sale_items si JOIN products p ON si.product_id = p.id JOIN sales s ON si.sale_id = s.id WHERE s.user_id = ? AND DATE(s.created_at) = CURDATE() GROUP BY p.id ORDER BY SUM(si.quantity) DESC LIMIT 1");
$topItem->execute([$userId]);
$myStats['top_item'] = $topItem->fetchColumn() ?: '-';
$myStats['shift_status'] = 'Active'; 

// 6. NEW: Unpaid Invoices (Open Tabs)
$tabStmt = $pdo->prepare("SELECT COUNT(id) as count, COALESCE(SUM(final_total), 0.00) as total FROM sales WHERE location_id = ? AND payment_status = 'pending'");
$tabStmt->execute([$locationId]);
$pendingTabs = $tabStmt->fetch();

// 7. NEW: Pending Requisitions (My Requests + Incoming Dispatches)
$reqStmt = $pdo->prepare("SELECT COUNT(id) FROM inventory_transfers WHERE (source_location_id = ? OR dest_location_id = ?) AND status IN ('pending', 'in_transit')");
$reqStmt->execute([$locationId, $locationId]);
$pendingReqs = $reqStmt->fetchColumn();
?>
