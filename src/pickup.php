<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$locId = $_SESSION['pos_location_id'] ?? 0;

// --- 1. HANDLE COLLECTION (AJAX) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $collectedBy = $_POST['collected_by'] ?? $_SESSION['user_id'];

    if (isset($_POST['collect_order'])) {
        $saleId = $_POST['sale_id'];
        
        // Mark items as 'served' and 'collected'
        // This removes them from the Pickup Screen AND updates the POS status to "Done"
        $pdo->prepare("
            UPDATE sale_items 
            SET fulfillment_status = 'collected', status = 'served' 
            WHERE sale_id = ? 
            AND fulfillment_status = 'uncollected'
            AND status = 'ready' 
        ")->execute([$saleId]);

        // Cleanup Notification table
        $pdo->prepare("UPDATE pickup_notifications SET status = 'collected', collected_by = ? WHERE sale_id = ?")->execute([$collectedBy, $saleId]);
        
        echo json_encode(['status' => 'success', 'sale_id' => $saleId]);
        exit;
    }
}

// --- 2. FETCH READY ORDERS (The Fix) ---
// We fetch items that are 'ready' (cooked) but 'uncollected'.
// We REMOVED 'payment_status = paid' so Tabs show up here too.
$sql = "SELECT 
            s.id as sale_id, 
            s.customer_name, 
            s.created_at, 
            u.full_name as server_name,
            si.id as item_id, 
            si.quantity, 
            p.name as product_name
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        LEFT JOIN users u ON s.user_id = u.id
        WHERE si.status = 'ready' 
        AND si.fulfillment_status = 'uncollected'
        " . ($locId ? "AND s.location_id = $locId" : "") . "
        ORDER BY s.created_at ASC";

$rawItems = $pdo->query($sql)->fetchAll();

// Group items by Order
$readyOrders = [];
foreach ($rawItems as $row) {
    $sid = $row['sale_id'];
    if (!isset($readyOrders[$sid])) {
        $readyOrders[$sid] = [
            'sale_id' => $sid,
            'customer' => $row['customer_name'],
            'created_at' => $row['created_at'],
            'server' => $row['server_name'],
            'items' => []
        ];
    }
    $readyOrders[$sid]['items'][] = $row;
}

// Fetch Waiters for the dropdown
$waiters = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('waiter', 'cashier', 'manager', 'admin') ORDER BY full_name ASC")->fetchAll();
?>