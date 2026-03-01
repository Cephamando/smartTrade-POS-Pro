<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard"); exit;
}

$date = $_GET['date'] ?? date('Y-m-d');
$locationId = $_GET['location_id'] ?? ($_SESSION['location_id'] ?? 0);

// Get Locations for filter
$locations = $pdo->query("SELECT id, name FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// 1. Core Metrics (Sales, Refunds, Tips)
$stmt = $pdo->prepare("
    SELECT 
        COUNT(id) as total_transactions,
        SUM(CASE WHEN payment_status = 'paid' THEN final_total ELSE 0 END) as gross_sales,
        SUM(CASE WHEN payment_status = 'paid' THEN tip_amount ELSE 0 END) as total_tips,
        SUM(CASE WHEN payment_status = 'refunded' THEN final_total ELSE 0 END) as total_refunded
    FROM sales 
    WHERE DATE(created_at) = ? AND location_id = ?
");
$stmt->execute([$date, $locationId]);
$metrics = $stmt->fetch(PDO::FETCH_ASSOC);

// 2. Breakdown by Payment Method
$stmt = $pdo->prepare("
    SELECT payment_method, SUM(final_total) as amount, COUNT(id) as count
    FROM sales
    WHERE DATE(created_at) = ? AND location_id = ? AND payment_status = 'paid'
    GROUP BY payment_method
");
$stmt->execute([$date, $locationId]);
$paymentMethods = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Breakdown by Category
$stmt = $pdo->prepare("
    SELECT c.name as category_name, SUM(si.quantity) as qty, SUM(si.price * si.quantity) as amount
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    LEFT JOIN products p ON si.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE DATE(s.created_at) = ? AND s.location_id = ? AND s.payment_status = 'paid' AND si.status NOT IN ('voided', 'refunded')
    GROUP BY c.id
    ORDER BY amount DESC
");
$stmt->execute([$date, $locationId]);
$categoriesBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Breakdown by Product (Itemized)
$stmt = $pdo->prepare("
    SELECT COALESCE(p.name, 'Custom Item') as name, SUM(si.quantity) as qty, SUM(si.price * si.quantity) as amount
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    LEFT JOIN products p ON si.product_id = p.id
    WHERE DATE(s.created_at) = ? AND s.location_id = ? AND s.payment_status = 'paid' AND si.status NOT IN ('voided', 'refunded')
    GROUP BY p.id, p.name
    ORDER BY qty DESC
");
$stmt->execute([$date, $locationId]);
$productBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Expenses / Payouts
$stmt = $pdo->prepare("
    SELECT SUM(amount) as total_expenses, COUNT(id) as expense_count
    FROM expenses
    WHERE DATE(created_at) = ? AND location_id = ?
");
$stmt->execute([$date, $locationId]);
$expenses = $stmt->fetch(PDO::FETCH_ASSOC);
?>
