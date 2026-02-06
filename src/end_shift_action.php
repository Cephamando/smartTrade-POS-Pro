<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];

// Find active shift
$stmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
$stmt->execute([$userId]);
$shiftId = $stmt->fetchColumn();

if ($shiftId) {
    // 1. Calculate actual closing cash from Sales
    $salesStmt = $pdo->prepare("SELECT SUM(final_total) FROM sales WHERE shift_id = ? AND payment_status = 'paid' AND payment_method = 'cash'");
    $salesStmt->execute([$shiftId]);
    $cashSales = $salesStmt->fetchColumn() ?: 0;
    
    // Get Starting Cash
    $startStmt = $pdo->prepare("SELECT starting_cash FROM shifts WHERE id = ?");
    $startStmt->execute([$shiftId]);
    $startCash = $startStmt->fetchColumn() ?: 0;
    
    $expectedCash = $startCash + $cashSales;

    // 2. Close the Shift
    $closeStmt = $pdo->prepare("UPDATE shifts SET end_time = NOW(), status = 'closed', closing_cash = ?, expected_cash = ? WHERE id = ?");
    $closeStmt->execute([$expectedCash, $expectedCash, $shiftId]);
    
    // 3. Logout User
    session_destroy();
    header("Location: index.php?page=login");
    exit;
} else {
    // No active shift? Just redirect.
    header("Location: index.php?page=dashboard");
    exit;
}
?>
