<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { die("Unauthorized"); }

$shiftId = (int)($_GET['shift_id'] ?? 0);

// Fetch shift details
$stmt = $pdo->prepare("SELECT s.*, u.username, l.name as loc_name FROM shifts s JOIN users u ON s.user_id = u.id JOIN locations l ON s.location_id = l.id WHERE s.id = ?");
$stmt->execute([$shiftId]);
$shift = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$shift) { die("Shift not found."); }

// Fetch Sales to manually group and accommodate Splits
$stmt = $pdo->prepare("SELECT payment_method, final_total, tip_amount, split_method_1, split_amount_1, split_method_2, split_amount_2 FROM sales WHERE shift_id = ? AND payment_status = 'paid'");
$stmt->execute([$shiftId]);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

$paymentBreakdownAssoc = [];
$grandTotal = 0;
$cashSales = 0;

foreach($sales as $s) {
    $grandTotal += $s['final_total'];
    if ($s['payment_method'] === 'Split') {
        $change = $s['change_due'] ?? 0;
        $sa1 = $s['split_amount_1'];
        $sa2 = $s['split_amount_2'];
        
        // Deduct any physical change given from the Cash portions so X-Read balances perfectly
        if ($s['split_method_1'] === 'Cash' && $change > 0) {
            $deduct = min($sa1, $change);
            $sa1 -= $deduct;
            $change -= $deduct;
        }
        if ($s['split_method_2'] === 'Cash' && $change > 0) {
            $deduct = min($sa2, $change);
            $sa2 -= $deduct;
            $change -= $deduct;
        }

        if (!empty($s['split_method_1']) && $s['split_amount_1'] > 0) {
            $m1 = $s['split_method_1'];
            if (!isset($paymentBreakdownAssoc[$m1])) $paymentBreakdownAssoc[$m1] = ['payment_method' => $m1, 'tx_count' => 0, 'total' => 0, 'total_tips' => 0];
            $paymentBreakdownAssoc[$m1]['total'] += $sa1;
            $paymentBreakdownAssoc[$m1]['tx_count'] += 0.5;
            if ($m1 === 'Cash') $cashSales += $sa1;
        }
        if (!empty($s['split_method_2']) && $s['split_amount_2'] > 0) {
            $m2 = $s['split_method_2'];
            if (!isset($paymentBreakdownAssoc[$m2])) $paymentBreakdownAssoc[$m2] = ['payment_method' => $m2, 'tx_count' => 0, 'total' => 0, 'total_tips' => 0];
            $paymentBreakdownAssoc[$m2]['total'] += $sa2;
            $paymentBreakdownAssoc[$m2]['tx_count'] += 0.5;
            if ($m2 === 'Cash') $cashSales += $sa2;
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
