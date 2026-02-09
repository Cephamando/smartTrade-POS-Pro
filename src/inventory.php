<?php
// src/inventory.php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// Filters
$locFilter = $_GET['location_id'] ?? '';
$catFilter = $_GET['category_id'] ?? '';
$search = $_GET['search'] ?? '';

// Build Query
$sql = "SELECT i.*, p.name as product_name, p.sku, p.price, p.cost_price, p.unit, c.name as category_name, l.name as location_name, (i.quantity * p.price) as stock_value 
        FROM inventory i 
        JOIN products p ON i.product_id = p.id 
        JOIN locations l ON i.location_id = l.id 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE 1=1";

$params = [];

if ($locFilter) {
    $sql .= " AND i.location_id = ?";
    $params[] = $locFilter;
}

if ($catFilter) {
    $sql .= " AND p.category_id = ?";
    $params[] = $catFilter;
}

if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventory = $stmt->fetchAll();

// Fetch dropdown data
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Calculate Total Value
$totalValue = 0;
foreach ($inventory as $item) {
    $totalValue += $item['stock_value'];
}
?>
