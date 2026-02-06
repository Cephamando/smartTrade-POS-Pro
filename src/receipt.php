<?php
// LOGIC ONLY - No HTML Output
if (!isset($_GET['sale_id'])) die("Sale ID required");

$saleId = $_GET['sale_id'];

// 1. Fetch Sale Details
$stmt = $pdo->prepare("
    SELECT s.*, u.full_name as cashier_name, l.name as location_name, l.address, l.phone 
    FROM sales s 
    JOIN users u ON s.user_id = u.id 
    JOIN locations l ON s.location_id = l.id 
    WHERE s.id = ?
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch();

if (!$sale) die("Sale not found");

// 2. Fetch Items
$items = $pdo->prepare("
    SELECT si.*, p.name 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    WHERE si.sale_id = ?
");
$items->execute([$saleId]);
$lineItems = $items->fetchAll();

// 3. Determine if this order needs collection (Has Food/Meal)
$checkKitchen = $pdo->prepare("
    SELECT COUNT(*) 
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    JOIN categories c ON p.category_id = c.id 
    WHERE si.sale_id = ? AND LOWER(c.type) IN ('food', 'meal')
");
$checkKitchen->execute([$saleId]);
$isKitchenOrder = $checkKitchen->fetchColumn() > 0;

// 4. Set Collection Status Text
$collectionStatus = "";
$statusColor = "black";

if ($isKitchenOrder) {
    if (!empty($sale['collected_by'])) {
        $collectionStatus = "COLLECTED BY: " . strtoupper($sale['collected_by']);
        $statusColor = "green";
    } else {
        $collectionStatus = "NOT COLLECTED";
        $statusColor = "red";
    }
}
?>
