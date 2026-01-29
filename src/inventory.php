<?php
// SECURITY: Logged in users only
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$locId = $_SESSION['location_id'];
$role = $_SESSION['role'];

// FETCH DATA
// Admins can filter by location, others forced to their own
$selectedLoc = $locId;
if (in_array($role, ['admin', 'dev']) && isset($_GET['loc'])) {
    $selectedLoc = $_GET['loc'];
}

// Get Location Name
$stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
$stmt->execute([$selectedLoc]);
$locName = $stmt->fetchColumn() ?: 'Unknown';

// Fetch Stock
// We use a RIGHT JOIN on products to show items even if stock is 0 (optional preference)
// But usually, for inventory view, we only want to see what is tracked.
// Let's list ALL products and show 0 if no record exists.

$sql = "SELECT p.name, p.sku, p.unit, c.name as category, 
               COALESCE(ls.quantity, 0) as qty 
        FROM products p
        LEFT JOIN location_stock ls ON p.id = ls.product_id AND ls.location_id = ?
        LEFT JOIN categories c ON p.category_id = c.id
        ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$selectedLoc]);
$stock = $stmt->fetchAll();

// Fetch Locations list for Admin filter
$allLocations = [];
if (in_array($role, ['admin', 'dev'])) {
    $allLocations = $pdo->query("SELECT * FROM locations")->fetchAll();
}
?>