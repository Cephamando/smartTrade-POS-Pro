<?php
if (!isset($_SESSION['user_id'])) { exit; }

// --- 1. HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Mark single item as collected
    if (isset($_POST['collect_item'])) {
        $itemId = $_POST['item_id'];
        $pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected' WHERE id = ?")->execute([$itemId]);
    }
    // Mark whole order as collected
    if (isset($_POST['collect_order'])) {
        $saleId = $_POST['sale_id'];
        $pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected' WHERE sale_id = ?")->execute([$saleId]);
    }
    // Redirect to self to refresh
    $url = "index.php?page=pickup" . (isset($_GET['embedded']) ? "&embedded=1" : "");
    header("Location: $url"); 
    exit;
}

// --- 2. FETCH UNCOLLECTED ITEMS ---
// We only want items that are 'uncollected' from sales that are 'paid'
$sql = "SELECT 
            s.id as sale_id, 
            s.customer_name, 
            s.created_at, 
            si.id as item_id, 
            si.quantity, 
            p.name as product_name
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        WHERE si.fulfillment_status = 'uncollected' 
        AND s.payment_status = 'paid'
        ORDER BY s.created_at ASC";

$rawItems = $pdo->query($sql)->fetchAll();

// Group by Order
$orders = [];
foreach ($rawItems as $row) {
    $sid = $row['sale_id'];
    if (!isset($orders[$sid])) {
        $orders[$sid] = [
            'id' => $sid,
            'customer' => $row['customer_name'],
            'time' => $row['created_at'],
            'items' => []
        ];
    }
    $orders[$sid]['items'][] = $row;
}
?>
