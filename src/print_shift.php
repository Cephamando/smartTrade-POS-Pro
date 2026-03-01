<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { die("Unauthorized"); }

$shiftId = (int)($_GET['shift_id'] ?? 0);

// Fetch shift details
$stmt = $pdo->prepare("SELECT s.*, u.username, l.name as loc_name FROM shifts s JOIN users u ON s.user_id = u.id JOIN locations l ON s.location_id = l.id WHERE s.id = ?");
$stmt->execute([$shiftId]);
$shift = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$shift) { die("Shift not found."); }

// Fetch Sales grouped by Payment Method (using final_total)
$stmt = $pdo->prepare("SELECT payment_method, COUNT(*) as tx_count, COALESCE(SUM(final_total), 0) as total, COALESCE(SUM(tip_amount), 0) as total_tips FROM sales WHERE shift_id = ? AND payment_status = 'paid' GROUP BY payment_method");
$stmt->execute([$shiftId]);
$paymentBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

$grandTotal = 0;
$cashSales = 0;
foreach($paymentBreakdown as $pb) {
    $grandTotal += $pb['total'];
    if ($pb['payment_method'] === 'Cash') {
        $cashSales = $pb['total'];
    }
}

// Fetch Payouts / Expenses
$stmt = $pdo->prepare("SELECT * FROM expenses WHERE shift_id = ?");
$stmt->execute([$shiftId]);
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalExpenses = 0;
foreach($expenses as $ex) { $totalExpenses += $ex['amount']; }

// Calculate expectations
$expectedCash = $shift['starting_cash'] + $cashSales - $totalExpenses;

// NEW: Breakdown by Category
$stmt = $pdo->prepare("
    SELECT c.name as category_name, SUM(si.quantity) as qty, SUM(si.price * si.quantity) as amount
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    LEFT JOIN products p ON si.product_id = p.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE s.shift_id = ? AND s.payment_status = 'paid' AND si.status NOT IN ('voided', 'refunded')
    GROUP BY c.id
    ORDER BY amount DESC
");
$stmt->execute([$shiftId]);
$categoriesBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);

// NEW: Breakdown by Product (Itemized)
$stmt = $pdo->prepare("
    SELECT COALESCE(p.name, 'Custom Item') as name, SUM(si.quantity) as qty, SUM(si.price * si.quantity) as amount
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    LEFT JOIN products p ON si.product_id = p.id
    WHERE s.shift_id = ? AND s.payment_status = 'paid' AND si.status NOT IN ('voided', 'refunded')
    GROUP BY p.id, p.name
    ORDER BY qty DESC
");
$stmt->execute([$shiftId]);
$productBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
