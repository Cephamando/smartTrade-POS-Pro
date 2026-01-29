<?php
// SECURITY: Kitchen/Admin/Manager Roles
$allowed = ['admin', 'manager', 'dev', 'chef'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed)) {
    header("Location: index.php?page=dashboard");
    exit;
}

$locId = $_SESSION['location_id'];

// --- HANDLE STATUS UPDATES ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = $_POST['item_id'];
    $newStatus = $_POST['new_status']; // 'cooking' or 'ready'

    if ($itemId && $newStatus) {
        // Update Item Status
        $stmt = $pdo->prepare("UPDATE sale_items SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $itemId]);

        // IF READY -> Notify Waiters
        if ($newStatus === 'ready') {
            // Fetch info for notification
            $info = $pdo->prepare("
                SELECT si.sale_id, p.name 
                FROM sale_items si 
                JOIN products p ON si.product_id = p.id 
                WHERE si.id = ?");
            $info->execute([$itemId]);
            $item = $info->fetch();

            if ($item) {
                $notif = $pdo->prepare("INSERT INTO pickup_notifications (sale_id, item_name, status) VALUES (?, ?, 'ready')");
                $notif->execute([$item['sale_id'], $item['name']]);
            }
        }
    }
    
    // Return (PRG)
    header("Location: index.php?page=kds");
    exit;
}

// --- FETCH ACTIVE ORDERS ---
// We want items that are 'pending' or 'cooking'. 
// We group them by Sale ID (Order #)
$sql = "
    SELECT 
        s.id as sale_id, 
        s.created_at,
        u.username as waiter,
        si.id as item_id,
        si.quantity,
        si.status as item_status,
        p.name as product_name
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN users u ON s.user_id = u.id
    JOIN products p ON si.product_id = p.id
    WHERE s.location_id = ? 
      AND si.status IN ('pending', 'cooking')
    ORDER BY s.id ASC, si.id ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$locId]);
$rows = $stmt->fetchAll();

// Group raw rows into Orders
$orders = [];
foreach ($rows as $r) {
    $sid = $r['sale_id'];
    if (!isset($orders[$sid])) {
        $orders[$sid] = [
            'id' => $sid,
            'time' => $r['created_at'],
            'waiter' => $r['waiter'],
            'items' => []
        ];
    }
    $orders[$sid]['items'][] = $r;
}
?>