<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];
$locationId = $_SESSION['location_id'];

// --- HELPER: Verify Manager ---
function verifyManagerForAction($pdo, $password, $locationId, $currentUserId) {
    // Allow Admins, Devs, or Managers of this location
    $sql = "SELECT id, password_hash FROM users 
            WHERE (role IN ('admin', 'head_chef','dev') OR (role = 'manager' AND location_id = ?))";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$locationId]);
    $managers = $stmt->fetchAll();

    foreach ($managers as $mgr) {
        if (password_verify($password, $mgr['password_hash'])) {
            return $mgr['id'];
        }
    }
    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. Find active shift
        $stmt = $pdo->prepare("SELECT id, starting_cash, start_time FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $stmt->execute([$userId]);
        $shift = $stmt->fetch();

        if (!$shift) {
            header("Location: index.php?page=dashboard");
            exit;
        }

        $shiftId = $shift['id'];

        // 2. Verify Manager
        $mgrPass = $_POST['manager_password'] ?? '';
        $mgrId = verifyManagerForAction($pdo, $mgrPass, $locationId, $userId);

        if ($mgrId === 'SELF_ERROR') throw new Exception("Security: You cannot verify your own shift close.");
        if (!$mgrId) throw new Exception("Incorrect Manager Password.");

        // 3. Calculate Financials
        $closingCash = floatval($_POST['closing_cash'] ?? 0);
        $varianceReason = $_POST['variance_reason'] ?? '';

        // Expected Cash
        $salesStmt = $pdo->prepare("SELECT SUM(final_total) FROM sales WHERE shift_id = ? AND payment_status = 'paid' AND payment_method LIKE '%Cash%'");
        $salesStmt->execute([$shiftId]);
        $cashSales = floatval($salesStmt->fetchColumn() ?: 0);
        
        $expStmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND created_at >= ?");
        $expStmt->execute([$userId, $shift['start_time']]);
        $expenses = floatval($expStmt->fetchColumn() ?: 0);

        $expectedCash = ($shift['starting_cash'] + $cashSales) - $expenses;

        // 4. Close the Shift
        $closeStmt = $pdo->prepare("
            UPDATE shifts SET 
                end_time = NOW(), 
                status = 'closed', 
                closing_cash = ?, 
                expected_cash = ?,
                variance_reason = ?,
                end_verified_by = ?,
                end_verified_at = NOW()
            WHERE id = ?");
        
        $closeStmt->execute([$closingCash, $expectedCash, $varianceReason, $mgrId, $shiftId]);
        
        // 5. Success - Redirect back to POS with flag to open modal
        // We do not destroy session yet so they can view the Z-Report Modal
        header("Location: index.php?page=pos&closed_shift_id=" . $shiftId);
        exit;

    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = $e->getMessage();
        header("Location: index.php?page=pos"); 
        exit;
    }
} else {
    header("Location: index.php?page=dashboard");
    exit;
}
?>
