<?php
// SECURITY: Ensure user is logged in
if (!isset($_SESSION['user_id'])) { 
    header("Location: index.php?page=login"); 
    exit; 
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role']; 
$locId  = $_SESSION['location_id'];

// Define roles that handle money
$moneyRoles = ['cashier', 'manager', 'admin', 'dev', 'bartender'];
$isMoneyRole = in_array($userRole, $moneyRoles);

// --- HELPER: Verify Manager Password ---
function verifyManager($pdo, $password, $locationId, $currentUserId) {
    // Allow Admins, Devs, or Managers of this location
    $sql = "SELECT id, password_hash FROM users 
            WHERE (role IN ('admin', 'head_chef','dev') OR (role = 'manager' AND location_id = ?))";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$locationId]);
    $managers = $stmt->fetchAll();

    foreach ($managers as $mgr) {
        if (password_verify($password, $mgr['password_hash'])) {
            // Prevent self-verification
            if ($mgr['id'] == $currentUserId) return 'SELF_ERROR';
            return $mgr['id'];
        }
    }
    return false;
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    try {
        // 1. START SHIFT
        if (isset($_POST['start_shift'])) {
            $mgrPass = $_POST['manager_password'] ?? '';
            $mgrId = verifyManager($pdo, $mgrPass, $locId, $userId);

            if ($mgrId === 'SELF_ERROR') throw new Exception("⛔ Security: You cannot verify your own shift.");
            if (!$mgrId) throw new Exception("Incorrect Manager Password.");

            // Check for existing open shift
            $check = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open'");
            $check->execute([$userId]);
            if ($check->fetch()) throw new Exception("You already have an open shift.");

            $startingCash = $isMoneyRole ? ($_POST['start_amount'] ?? 0) : 0;

            $stmt = $pdo->prepare("INSERT INTO shifts (user_id, location_id, start_time, starting_cash, status, start_verified_by, start_verified_at) VALUES (?, ?, NOW(), ?, 'open', ?, NOW())");
            $stmt->execute([$userId, $locId, $startingCash, $mgrId]);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = 'Clock-In Successful!';
        }

        // 2. END SHIFT
        if (isset($_POST['end_shift'])) {
            $shiftId = $_POST['shift_id'];
            $mgrPass = $_POST['manager_password'] ?? '';
            $mgrId = verifyManager($pdo, $mgrPass, $locId, $userId);

            if ($mgrId === 'SELF_ERROR') throw new Exception("⛔ Security: You cannot verify your own shift close.");
            if (!$mgrId) throw new Exception("Incorrect Manager Password.");

            $closingCash = 0; $notes = null; $varianceReason = null; $managerCount = 0;

            if ($isMoneyRole) {
                $closingCash = $_POST['end_amount'] ?? 0;
                $managerCount = $_POST['manager_count'] ?? $closingCash; // Optional double count
                $varianceReason = $_POST['variance_reason'] ?? null;
            } else {
                $notes = $_POST['handover_notes'] ?? '';
            }

            // Calculate Expected Cash (for DB record)
            // Note: In a real app, you might want to fetch sales totals here to save 'expected_cash' accurately.
            // For now, we trust the view passed or recalculate if needed. We will keep it simple.

            $stmt = $pdo->prepare("
                UPDATE shifts SET 
                    end_time = NOW(), 
                    closing_cash = ?, 
                    manager_closing_cash = ?,
                    variance_reason = ?, 
                    handover_notes = ?, 
                    status = 'closed', 
                    end_verified_by = ?, 
                    end_verified_at = NOW() 
                WHERE id = ?");
            
            $stmt->execute([$closingCash, $managerCount, $varianceReason, $notes, $mgrId, $shiftId]);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = 'Shift Closed Successfully.';
        }

    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = $e->getMessage();
    }

    // REDIRECT (PRG Pattern)
    header("Location: index.php?page=shifts");
    exit;
}

// --- FETCH VIEW DATA ---
// 1. Current Active Shift
$stmt = $pdo->prepare("SELECT * FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY start_time DESC LIMIT 1");
$stmt->execute([$userId]);
$currentShift = $stmt->fetch();

// 2. Statistics for Closing (Only if active shift exists)
$calculatedSummary = [];
if ($currentShift && $isMoneyRole) {
    // Cash Sales
    $sStmt = $pdo->prepare("SELECT SUM(final_total) FROM sales WHERE shift_id = ? AND payment_method = 'cash' AND status != 'refunded'");
    $sStmt->execute([$currentShift['id']]);
    $cashSales = $sStmt->fetchColumn() ?: 0.00;

    // Expenses paid from till
    $eStmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND created_at >= ?");
    $eStmt->execute([$userId, $currentShift['start_time']]);
    $expenses = $eStmt->fetchColumn() ?: 0.00;

    $expected = ($currentShift['starting_cash'] + $cashSales) - $expenses;
    
    $calculatedSummary = [
        'float' => $currentShift['starting_cash'],
        'cash_sales' => $cashSales,
        'expenses' => $expenses,
        'expected' => $expected
    ];
}

// 3. History
$histStmt = $pdo->prepare("
    SELECT s.*, u1.username as start_mgr, u2.username as end_mgr 
    FROM shifts s 
    LEFT JOIN users u1 ON s.start_verified_by = u1.id
    LEFT JOIN users u2 ON s.end_verified_by = u2.id
    WHERE s.user_id = ? 
    ORDER BY s.start_time DESC LIMIT 10");
$histStmt->execute([$userId]);
$history = $histStmt->fetchAll();
?>