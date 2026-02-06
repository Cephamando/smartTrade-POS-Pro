<?php
// Ensure session is started if not already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security Check
if (!isset($_SESSION['user_id'])) { 
    die('<div class="alert alert-danger">Session expired. Please log in again.</div>'); 
}

require_once __DIR__ . '/config.php';

// Determine which shift to show
$userId = $_SESSION['user_id'];
$targetShiftId = null;
$isDrillDown = false;

// 1. Check if Admin/Manager is drilling down
if (isset($_GET['shift_id']) && in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    $targetShiftId = $_GET['shift_id'];
    $isDrillDown = true;
} else {
    // 2. Otherwise, find my own active shift
    $stmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $targetShiftId = $stmt->fetchColumn();
}

if ($targetShiftId) {
    // Fetch Shift Meta
    $meta = $pdo->prepare("SELECT s.*, u.username FROM shifts s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
    $meta->execute([$targetShiftId]);
    $shiftMeta = $meta->fetch();

    // Calculate Sales Breakdown
    $salesSql = "SELECT p.name as product_name, SUM(si.quantity) as qty_sold, SUM(si.quantity * si.price_at_sale) as actual_revenue 
                 FROM sale_items si 
                 JOIN sales s ON si.sale_id = s.id 
                 JOIN products p ON si.product_id = p.id 
                 WHERE s.shift_id = ? AND s.payment_status = 'paid' 
                 GROUP BY p.id";
    $salesStmt = $pdo->prepare($salesSql);
    $salesStmt->execute([$targetShiftId]);
    $salesData = $salesStmt->fetchAll();
    
    // Calculate Totals by Payment Method
    $totalSql = "SELECT payment_method, SUM(final_total) as total 
                 FROM sales 
                 WHERE shift_id = ? AND payment_status = 'paid' 
                 GROUP BY payment_method";
    $totalStmt = $pdo->prepare($totalSql);
    $totalStmt->execute([$targetShiftId]);
    $totalsData = $totalStmt->fetchAll();
    
    // Set Data for Template
    $_SESSION['shift_report'] = [
        'user' => $shiftMeta['username'], 
        'start' => $shiftMeta['start_time'], 
        'end' => date('Y-m-d H:i:s'), 
        'sales' => $salesData, 
        'totals' => $totalsData,
        'is_drill_down' => $isDrillDown
    ];
    
    // Load Template directly
    require_once __DIR__ . '/../templates/shift_summary.php';

} else {
    echo "<div class='alert alert-warning'>No active shift found. Please start a shift first.</div>";
}
?>
