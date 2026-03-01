<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];
$locationId = $_SESSION['pos_location_id'] ?? $_SESSION['location_id'] ?? 0;
$userRole = $_SESSION['role'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $closingCash = (float)($_POST['closing_cash'] ?? 0);
    $varianceReason = trim($_POST['variance_reason'] ?? '');
    $mgrUsername = trim($_POST['manager_username'] ?? '');
    $mgrPassword = $_POST['manager_password'] ?? '';

    try {
        // 1. Validate Manager Credentials strictly by Username
        $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$mgrUsername]);
        $mgr = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$mgr || !in_array($mgr['role'], ['admin', 'manager', 'dev']) || !password_verify($mgrPassword, $mgr['password_hash'])) {
            throw new Exception("Invalid Manager Credentials. Shift closure denied.");
        }

        // 2. Find the active shift
        $stmt = $pdo->prepare("SELECT id, starting_cash FROM shifts WHERE user_id = ? AND location_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId, $locationId]);
        $shift = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$shift) {
            throw new Exception("No open shift found.");
        }

        $shiftId = $shift['id'];

        // 3. Calculate Cash Sales (using new final_total)
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(final_total), 0) FROM sales WHERE shift_id = ? AND payment_method = 'Cash' AND payment_status = 'paid'");
        $stmt->execute([$shiftId]);
        $cashSales = (float)$stmt->fetchColumn();

        // 4. Calculate Payouts/Expenses
        $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE shift_id = ?");
        $stmt->execute([$shiftId]);
        $expenses = (float)$stmt->fetchColumn();

        // 5. Final Math
        $expectedCash = $shift['starting_cash'] + $cashSales - $expenses;
        $variance = $closingCash - $expectedCash;

        // 6. Audit Trail: Stamp the manager's name onto the record
        $finalReason = $varianceReason;
        if (!empty($finalReason)) {
            $finalReason .= " (Auth: " . $mgrUsername . ")";
        } else {
            $finalReason = "Auth: " . $mgrUsername;
        }

        // Close the shift
        $stmt = $pdo->prepare("UPDATE shifts SET status = 'closed', end_time = NOW(), closing_cash = ?, expected_cash = ?, variance = ?, variance_reason = ? WHERE id = ?");
        $stmt->execute([$closingCash, $expectedCash, $variance, $finalReason, $shiftId]);

        unset($_SESSION['cart']);
        unset($_SESSION['pos_member']);

        // --- SMART LOGOUT / REDIRECT LOGIC ---
        if (!in_array($userRole, ['admin', 'manager', 'dev'])) {
            // Auto-logout standard staff (Cashiers, Waiters) after closing shift
            header("Location: index.php?action=logout");
            exit;
        } else {
            // Let Admins/Managers return to the dashboard
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = 'Shift closed successfully. Variance: ZMW ' . number_format($variance, 2);
            header("Location: index.php?page=dashboard");
            exit;
        }

    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = $e->getMessage();
        header("Location: index.php?page=pos");
        exit;
    }
}
