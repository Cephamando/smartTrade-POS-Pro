<?php
// SECURITY: Logged in users only
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$saleId = $_GET['sale_id'] ?? $_SESSION['last_sale_id'] ?? 0;
if (!$saleId) { header("Location: index.php?page=pos"); exit; }

// 1. FETCH SALE
$stmt = $pdo->prepare("
    SELECT s.*, u.username, l.name as location_name 
    FROM sales s
    JOIN users u ON s.user_id = u.id
    JOIN locations l ON s.location_id = l.id
    WHERE s.id = ?
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch();

if (!$sale) die("Sale not found.");

// 2. FETCH ITEMS
$stmt = $pdo->prepare("
    SELECT si.*, p.name 
    FROM sale_items si
    JOIN products p ON si.product_id = p.id
    WHERE si.sale_id = ?
");
$stmt->execute([$saleId]);
$items = $stmt->fetchAll();

// 3. KITCHEN ITEMS
// For now, we print EVERYTHING to the kitchen so you can see the 2nd receipt.
// In the future, you can filter this by Category ID if you want only Food.
$kitchenItems = $items; 
?>