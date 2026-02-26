<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) { 
    die("<h1>Access Denied</h1>"); 
}

$userId = $_SESSION['user_id'];
$locations = $pdo->query("SELECT id, name FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$selectedLoc = $_POST['location_id'] ?? $_GET['location_id'] ?? $_SESSION['location_id'] ?? 0;
$selectedDate = $_POST['target_date'] ?? $_GET['target_date'] ?? date('Y-m-d');

// 1. Check if already closed
$stmt = $pdo->prepare("SELECT * FROM daily_closures WHERE location_id = ? AND closure_date = ?");
$stmt->execute([$selectedLoc, $selectedDate]);
$closure = $stmt->fetch(PDO::FETCH_ASSOC);
$isClosed = ($closure !== false);

// 2. Check for Open Shifts
$stmt = $pdo->prepare("SELECT COUNT(*) FROM shifts WHERE location_id = ? AND DATE(start_time) = ? AND status IN ('open', 'pending')");
$stmt->execute([$selectedLoc, $selectedDate]);
$openShiftsCount = $stmt->fetchColumn();

// 3. Calculate Totals for the Day
$totals = ['Cash' => 0, 'Card' => 0, 'Mobile' => 0, 'Pending' => 0];
$totalTips = 0;

$stmt = $pdo->prepare("SELECT payment_method, COALESCE(SUM(final_total), 0) as total, COALESCE(SUM(tip_amount), 0) as tips FROM sales WHERE location_id = ? AND DATE(created_at) = ? AND payment_status = 'paid' GROUP BY payment_method");
$stmt->execute([$selectedLoc, $selectedDate]);
$salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($salesData as $row) {
    $totalTips += $row['tips'];
    if (strpos(strtolower($row['payment_method']), 'money') !== false || in_array($row['payment_method'], ['MTN Money', 'Airtel Money', 'Zamtel Money'])) {
        $totals['Mobile'] += $row['total'];
    } elseif ($row['payment_method'] === 'Card') {
        $totals['Card'] += $row['total'];
    } elseif ($row['payment_method'] === 'Cash') {
        $totals['Cash'] += $row['total'];
    } else {
        $totals['Pending'] += $row['total']; // Tabs/Other
    }
}

// 4. Calculate Expenses
$stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE location_id = ? AND DATE(created_at) = ?");
$stmt->execute([$selectedLoc, $selectedDate]);
$totalExpenses = (float)$stmt->fetchColumn();

// 5. Shift Cash Reconciliation (Opening floats + declared closing cash)
$stmt = $pdo->prepare("SELECT COALESCE(SUM(starting_cash), 0) as total_start, COALESCE(SUM(closing_cash), 0) as total_close FROM shifts WHERE location_id = ? AND DATE(start_time) = ? AND status = 'closed'");
$stmt->execute([$selectedLoc, $selectedDate]);
$shiftCash = $stmt->fetch(PDO::FETCH_ASSOC);

$totalStartingCash = (float)$shiftCash['total_start'];
$totalActualCash = (float)$shiftCash['total_close'];

$expectedCash = $totalStartingCash + $totals['Cash'] - $totalExpenses;
$dailyVariance = $totalActualCash - $expectedCash;

// --- EXECUTE Z-READ (LOCK DAY) ---
if (isset($_POST['execute_z_read']) && !$isClosed) {
    if ($openShiftsCount > 0) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = "Cannot close day. There are still $openShiftsCount open shifts!";
        header("Location: index.php?page=z_read&location_id=$selectedLoc&target_date=$selectedDate");
        exit;
    }

    try {
        $stmt = $pdo->prepare("INSERT INTO daily_closures (location_id, closure_date, closed_by, total_cash_expected, total_cash_actual, total_card, total_mobile, total_tips, total_expenses, variance) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $selectedLoc, $selectedDate, $userId, 
            $expectedCash, $totalActualCash, $totals['Card'], $totals['Mobile'], 
            $totalTips, $totalExpenses, $dailyVariance
        ]);
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Day successfully closed and locked.";
    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
    }
    header("Location: index.php?page=z_read&location_id=$selectedLoc&target_date=$selectedDate");
    exit;
}
?>
