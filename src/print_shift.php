<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { die("Unauthorized"); }

$shiftId = (int)($_GET['shift_id'] ?? 0);

// Fetch shift details
$stmt = $pdo->prepare("SELECT s.*, u.username, l.name as loc_name FROM shifts s JOIN users u ON s.user_id = u.id JOIN locations l ON s.location_id = l.id WHERE s.id = ?");
$stmt->execute([$shiftId]);
$shift = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$shift) { die("Shift not found."); }

require_once __DIR__ . '/split_payments.php';
ensureSaleSplitPaymentsColumn($pdo);

// Fetch Sales to manually group and accommodate Splits
$stmt = $pdo->prepare("SELECT payment_method, final_total, tip_amount, change_due, split_method_1, split_amount_1, split_method_2, split_amount_2, split_payments FROM sales WHERE shift_id = ? AND payment_status = 'paid'");
$stmt->execute([$shiftId]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paymentBreakdownAssoc = [];
$grandTotal = 0;
$cashSales = 0;

foreach($sales as $s) {
    $grandTotal += $s['final_total'];
    if ($s['payment_method'] === 'Split') {
        $legs = applySplitChangeToCash(saleSplitLegs($s), (float)($s['change_due'] ?? 0));
        $n = max(1, count($legs));
        foreach ($legs as $leg) {
            $m = $leg['method'];
            if (!isset($paymentBreakdownAssoc[$m])) $paymentBreakdownAssoc[$m] = ['payment_method' => $m, 'tx_count' => 0, 'total' => 0, 'total_tips' => 0];
            $paymentBreakdownAssoc[$m]['total'] += (float)$leg['amount'];
            $paymentBreakdownAssoc[$m]['tx_count'] += 1 / $n;
            if (strcasecmp($m, 'Cash') === 0) $cashSales += (float)$leg['amount'];
        }
    } else {
        $pm = $s['payment_method'];
        if (!isset($paymentBreakdownAssoc[$pm])) $paymentBreakdownAssoc[$pm] = ['payment_method' => $pm, 'tx_count' => 0, 'total' => 0, 'total_tips' => 0];
        $paymentBreakdownAssoc[$pm]['total'] += $s['final_total'];
        $paymentBreakdownAssoc[$pm]['total_tips'] += $s['tip_amount'];
        $paymentBreakdownAssoc[$pm]['tx_count'] += 1;
        if ($pm === 'Cash') $cashSales += $s['final_total'];
    }
}
$paymentBreakdown = array_values($paymentBreakdownAssoc);
foreach($paymentBreakdown as &$pb) { $pb['tx_count'] = ceil($pb['tx_count']); }

// 🛡️ FIX: Initialize variables strictly to avoid Undefined Variable errors
$expenses = [];
$totalExpenses = 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE shift_id = ?");
    $stmt->execute([$shiftId]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($expenses) {
        foreach($expenses as $ex) { $totalExpenses += $ex['amount']; }
    }
} catch (Exception $e) {}

$expectedCash = $shift['starting_cash'] + $cashSales - $totalExpenses;

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
