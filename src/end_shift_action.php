<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];
$locationId = $_SESSION['pos_location_id'] ?? $_SESSION['location_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $closingCash = (float)($_POST['closing_cash'] ?? 0);
    $varianceReason = trim($_POST['variance_reason'] ?? '');
    $mgrPassword = $_POST['manager_password'] ?? '';

    try {
        // Validate Manager Password against any Admin/Manager/Dev
        $mgrValid = false;
        $stmt = $pdo->query("SELECT password_hash FROM users WHERE role IN ('admin', 'manager', 'dev')");
        while ($row = $stmt->fetch()) {
            if (password_verify($mgrPassword, $row['password_hash'])) {
                $mgrValid = true;
                break;
            }
        }
        if (!$mgrValid) {
            throw new Exception("Invalid Manager Password. Shift closure denied.");
        }

        // Find the active shift
        $stmt = $pdo->prepare("SELECT id, starting_cash FROM shifts WHERE user_id = ? AND location_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId, $locationId]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$shift) {
            throw new Exception("No open shift found.");
        }

        $shiftId = $shift['id'];

        // 1. Calculate Cash Sales (using new final_total)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(final_total), 0) FROM sales WHERE shift_id = ? AND payment_method = 'Cash' AND payment_status = 'paid'");
        $stmt->execute([$shiftId]);
        $cashSales = (float)$stmt->fetchColumn();

        // 2. Calculate Payouts/Expenses
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE shift_id = ?");
        $stmt->execute([$shiftId]);
        $expenses = (float)$stmt->fetchColumn();

        // 3. Final Math
        $expectedCash = $shift['starting_cash'] + $cashSales - $expenses;
        $variance = $closingCash - $expectedCash;

        // Close the shift
        $stmt = $pdo->prepare("UPDATE shifts SET status = 'closed', end_time = NOW(), closing_cash = ?, expected_cash = ?, variance = ?, variance_reason = ? WHERE id = ?");
        $stmt->execute([$closingCash, $expectedCash, $variance, $varianceReason, $shiftId]);

        unset($_SESSION['cart']);
        unset($_SESSION['pos_member']);

        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = 'Shift closed successfully. Variance: ZMW ' . number_format($variance, 2);
        header("Location: index.php?page=dashboard");
        exit;
    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = $e->getMessage();
        header("Location: index.php?page=pos");
        exit;
    }
}
