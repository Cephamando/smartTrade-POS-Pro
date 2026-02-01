<?php
// FIX: Use __DIR__ to go up one level to find the config folder
require_once __DIR__ . '/../src/config.php';
session_start();

if (!isset($_SESSION['user_id'])) die("<h1>Please Log In First</h1>");

$userId = $_SESSION['user_id'];
$locationId = $_SESSION['location_id'];

echo "<h1>POS Stock Debugger</h1>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>Session Info</th><th>Value</th></tr>";
echo "<tr><td>User ID</td><td>$userId</td></tr>";
echo "<tr><td>Location ID</td><td><strong>$locationId</strong></td></tr>";

// Get Location Name
$stmt = $pdo->prepare("SELECT * FROM locations WHERE id = ?");
$stmt->execute([$locationId]);
$loc = $stmt->fetch();
echo "<tr><td>Location Name (DB)</td><td>" . ($loc ? $loc['name'] : 'NOT FOUND') . "</td></tr>";
echo "</table>";

echo "<h2>Inventory Check for Location ID: $locationId</h2>";
echo "<p><em>If Quantity is 0 or Empty here, the POS is correct and you need to Transfer Stock.</em></p>";

// Fetch Products + Stock
$sql = "
    SELECT 
        p.id as prod_id, 
        p.name as prod_name, 
        i.location_id as inv_loc_id,
        i.quantity as inv_qty
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = ?
    ORDER BY p.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$locationId]);
$rows = $stmt->fetchAll();

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Product ID</th><th>Product Name</th><th>Inventory Loc ID</th><th>Quantity Found</th></tr>";

foreach ($rows as $r) {
    $qty = $r['inv_qty'] !== null ? "<strong>{$r['inv_qty']}</strong>" : "<span style='color:red'>NULL (No Row)</span>";
    $bg = ($r['inv_qty'] > 0) ? "style='background:#e6fffa'" : "style='background:#fff5f5'";
    
    echo "<tr $bg>";
    echo "<td>{$r['prod_id']}</td>";
    echo "<td>{$r['prod_name']}</td>";
    echo "<td>{$r['inv_loc_id']}</td>";
    echo "<td>$qty</td>";
    echo "</tr>";
}
echo "</table>";
?>
