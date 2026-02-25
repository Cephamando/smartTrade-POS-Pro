<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// Restrict access
if (!in_array($_SESSION['role'], ['admin', 'manager', 'dev', 'head_chef'])) {
    die("<h1>Access Denied</h1><p>You do not have permission to view consumption reports.</p>");
}

// Set up filters
$locations = $pdo->query("SELECT id, name FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$selectedLoc = $_POST['location_id'] ?? $_SESSION['location_id'] ?? 0;

$filterStart = $_POST['start_date'] ?? date('Y-m-d');
$filterEnd = $_POST['end_date'] ?? date('Y-m-d');

$sqlStart = $filterStart . ' 00:00:00';
$sqlEnd = $filterEnd . ' 23:59:59';

// 1. QUERY: What sellable items (with recipes) were sold?
$stmtCocktails = $pdo->prepare("
    SELECT p.name AS cocktail_name, SUM(si.quantity) AS total_sold
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    WHERE s.created_at BETWEEN ? AND ? 
      AND s.location_id = ? 
      AND s.payment_status = 'paid'
      AND p.id IN (SELECT DISTINCT parent_product_id FROM product_recipes)
    GROUP BY p.id, p.name
    ORDER BY total_sold DESC
");
$stmtCocktails->execute([$sqlStart, $sqlEnd, $selectedLoc]);
$cocktailsSold = $stmtCocktails->fetchAll(PDO::FETCH_ASSOC);

// 2. QUERY: What raw ingredients were deducted as a result?
$stmtIngredients = $pdo->prepare("
    SELECT 
        i.name AS raw_name, 
        i.unit, 
        SUM(si.quantity * pr.quantity) AS theoretical_usage,
        COALESCE(inv.quantity, 0) AS current_stock
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN product_recipes pr ON si.product_id = pr.parent_product_id
    JOIN products i ON pr.ingredient_product_id = i.id
    LEFT JOIN inventory inv ON i.id = inv.product_id AND inv.location_id = s.location_id
    WHERE s.created_at BETWEEN ? AND ? 
      AND s.location_id = ? 
      AND s.payment_status = 'paid'
    GROUP BY i.id, i.name, i.unit, inv.quantity
    ORDER BY theoretical_usage DESC
");
$stmtIngredients->execute([$sqlStart, $sqlEnd, $selectedLoc]);
$ingredientsConsumed = $stmtIngredients->fetchAll(PDO::FETCH_ASSOC);
?>
