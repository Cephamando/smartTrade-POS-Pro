<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Security check
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev', 'head_chef'])) {
    header("Location: index.php?page=dashboard"); exit;
}

$startDate = $_GET['start_date'] ?? date('Y-m-d');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$locId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;

$locQuery = $locId > 0 ? "AND il.location_id = " . (int)$locId : "";

// Fetch locations for the filter dropdown
$locations = $pdo->query("SELECT id, name FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch the aggregated consumption data
$stmt = $pdo->prepare("
    SELECT 
        p.name as ingredient_name,
        l.name as location_name,
        SUM(ABS(il.change_qty)) as total_consumed
    FROM inventory_logs il
    JOIN products p ON il.product_id = p.id
    JOIN locations l ON il.location_id = l.id
    WHERE il.action_type = 'recipe_deduction'
    AND DATE(il.created_at) >= ? AND DATE(il.created_at) <= ?
    $locQuery
    GROUP BY p.id, l.id
    ORDER BY total_consumed DESC
");
$stmt->execute([$startDate, $endDate]);
$consumption = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
