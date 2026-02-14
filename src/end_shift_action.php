<?php
// ENABLE ERROR REPORTING FOR DEBUGGING
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { die("Error: User not logged in."); }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Get Inputs
    $userId = $_SESSION['user_id'];
    $closingCash = floatval($_POST['closing_cash'] ?? 0);
    $varianceReason = $_POST['variance_reason'] ?? '';
    $password = $_POST['manager_password'] ?? '';

    echo "<h3>Processing Shift Closure...</h3>";

    // 2. Find Active Shift
    $shiftStmt = $pdo->prepare("SELECT * FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $shiftStmt->execute([$userId]);
    $shift = $shiftStmt->fetch();

    if (!$shift) {
        die("<h2 style='color:red'>ERROR: No open shift found for this user.</h2><p>Please check if the shift is already closed or if you are logged in as the correct user.</p><a href='index.php?page=pos'>Back to POS</a>");
    }

    echo "Found Shift ID: " . $shift['id'] . "<br>";

    // 3. Verify Password
    $authSuccess = false;
    
    // A. Check Current User
    $currentUser = $pdo->query("SELECT * FROM users WHERE id = $userId")->fetch();
    if (password_verify($password, $currentUser['password_hash'])) {
        $authSuccess = true;
    } else {
        // B. Check Admin Override
        $admins = $pdo->query("SELECT * FROM users WHERE role IN ('admin', 'manager', 'dev')")->fetchAll();
        foreach ($admins as $admin) {
            if (password_verify($password, $admin['password_hash'])) {
                $authSuccess = true;
                break;
            }
        }
    }

    if (!$authSuccess) {
        die("<h2 style='color:red'>ERROR: Password Incorrect.</h2><p>The password you entered does not match the current user or any manager.</p><a href='index.php?page=pos'>Try Again</a>");
    }

    echo "Password Verified.<br>";

    // 4. Calculate Values
    $startCash = floatval($shift['starting_cash']);
    
    $salesStmt = $pdo->prepare("SELECT SUM(final_total) FROM sales WHERE shift_id = ? AND payment_status = 'paid' AND payment_method LIKE '%Cash%'");
    $salesStmt->execute([$shift['id']]);
    $cashSales = floatval($salesStmt->fetchColumn() ?: 0);

    $expStmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND created_at >= ?");
    $expStmt->execute([$userId, $shift['start_time']]);
    $expenses = floatval($expStmt->fetchColumn() ?: 0);

    $expectedCash = ($startCash + $cashSales) - $expenses;
    $variance = $closingCash - $expectedCash;

    echo "Expected: $expectedCash | Actual: $closingCash | Variance: $variance<br>";

    // 5. Update Database
    try {
        $updateSql = "UPDATE shifts SET 
                        end_time = NOW(), 
                        closing_cash = ?, 
                        expected_cash = ?, 
                        variance = ?, 
                        variance_reason = ?, 
                        status = 'closed' 
                      WHERE id = ?";
        
        $stmt = $pdo->prepare($updateSql);
        $result = $stmt->execute([$closingCash, $expectedCash, $variance, $varianceReason, $shift['id']]);

        if ($stmt->rowCount() > 0) {
            // SUCCESS - Clear Session and Redirect
            unset($_SESSION['pos_location_id']);
            unset($_SESSION['cart']);
            unset($_SESSION['current_tab_id']);
            unset($_SESSION['tab_paid']);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Shift Closed Successfully.";
            
            echo "<h2 style='color:green'>SUCCESS: Shift Closed. Redirecting...</h2>";
            echo "<script>window.location.href = 'index.php?page=dashboard';</script>";
            exit;
        } else {
            die("<h2 style='color:orange'>WARNING: Database executed but no rows changed.</h2><p>The shift might already be closed.</p><a href='index.php?page=dashboard'>Go to Dashboard</a>");
        }

    } catch (PDOException $e) {
        die("<h2 style='color:red'>DATABASE ERROR: " . $e->getMessage() . "</h2><p>This usually happens if the 'shifts' table is missing columns (closing_cash, variance, etc).</p><p><b>Run the database update command provided earlier.</b></p><a href='index.php?page=pos'>Back to POS</a>");
    }
} else {
    header("Location: index.php?page=pos");
    exit;
}
?>
