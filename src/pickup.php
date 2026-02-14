<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$locId = $_SESSION['location_id'];

// HANDLE COLLECTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collected'])) {
    $notifId = $_POST['notif_id'];
    
    // Default to 'customer' if missing
    $type = $_POST['picker_type'] ?? 'customer'; 
    $waiterId = $_POST['waiter_id'] ?? NULL;
    if ($type === 'customer') { $waiterId = NULL; }

    // 1. Mark Notification as Collected
    $stmt = $pdo->prepare("UPDATE pickup_notifications SET status = 'collected', collected_by_type = ?, collected_by_user_id = ? WHERE id = ?");
    $stmt->execute([$type, $waiterId, $notifId]);

    // 2. SYNC WITH SALES TABLE (Mark actual item as collected in POS)
    // Find the item linked to this notification
    $notif = $pdo->query("SELECT sale_id, item_name FROM pickup_notifications WHERE id = $notifId")->fetch();
    if ($notif) {
        $pdo->prepare("
            UPDATE sale_items 
            SET fulfillment_status = 'collected', status = 'served' 
            WHERE sale_id = ? 
            AND product_id = (SELECT id FROM products WHERE name = ? LIMIT 1) 
            AND fulfillment_status = 'uncollected' 
            LIMIT 1
        ")->execute([$notif['sale_id'], $notif['item_name']]);
    }
}

// FETCH READY ITEMS
$stmt = $pdo->prepare("
    SELECT pn.*, s.id as sale_ref 
    FROM pickup_notifications pn
    JOIN sales s ON pn.sale_id = s.id
    WHERE pn.status = 'ready' 
    AND s.location_id = ?
    ORDER BY pn.created_at DESC
");
$stmt->execute([$locId]);
$readyItems = $stmt->fetchAll();

// FETCH STAFF LIST
$staffStmt = $pdo->prepare("SELECT id, username FROM users WHERE location_id = ? ORDER BY username");
$staffStmt->execute([$locId]);
$staffList = $staffStmt->fetchAll();
?>