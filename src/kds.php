<?php
// SECURITY: Chefs, Head Chefs, Admins Only
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }
if (!in_array($_SESSION['role'], ['chef', 'head_chef', 'admin', 'dev'])) {
    $_SESSION['swal_type'] = 'error';
    $_SESSION['swal_msg'] = "Access Denied: KDS is for Kitchen Staff only.";
    header("Location: index.php?page=dashboard");
    exit;
}

// HANDLE STATUS UPDATES (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId = $_POST['item_id'];
    $newStatus = $_POST['status']; 

    $stmt = $pdo->prepare("UPDATE sale_items SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $itemId]);
    exit;
}

// FETCH ACTIVE ORDERS - Filtered by Category Type
$sql = "
    SELECT s.id as sale_id, s.created_at, u.username as server,
           si.id as item_id, si.quantity, si.status, p.name as product_name
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON s.user_id = u.id
    WHERE si.status IN ('pending', 'cooking')
    AND c.type IN ('food', 'meal')
    ORDER BY s.created_at ASC
";
$rows = $pdo->query($sql)->fetchAll();

$orders = [];
foreach ($rows as $r) {
    $saleId = $r['sale_id'];
    if (!isset($orders[$saleId])) {
        $orders[$saleId] = [
            'id' => $saleId,
            'time' => $r['created_at'],
            'waiter' => $r['server'],
            'items' => []
        ];
    }
    $orders[$saleId]['items'][] = [
        'id' => $r['item_id'],
        'name' => $r['product_name'],
        'qty' => $r['quantity'],
        'status' => $r['status']
    ];
}
?>
