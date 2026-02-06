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

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$targetShiftId = null;
$isDrillDown = false;

// 1. Determine Target Shift
if (isset($_GET['shift_id'])) {
    $requestedId = $_GET['shift_id'];
    
    // Security: Allow if Admin/Manager OR if the shift belongs to the current user
    $checkStmt = $pdo->prepare("SELECT user_id FROM shifts WHERE id = ?");
    $checkStmt->execute([$requestedId]);
    $ownerId = $checkStmt->fetchColumn();

    if (in_array($userRole, ['admin', 'manager', 'dev']) || $ownerId == $userId) {
        $targetShiftId = $requestedId;
        $isDrillDown = true; // Treats as a view-only mode
    } else {
        die('<div class="alert alert-danger">Access Denied: You cannot view this shift report.</div>');
    }
} else {
    // 2. Default: Find my own active shift
    $stmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $targetShiftId = $stmt->fetchColumn();
}

if ($targetShiftId) {
    // Fetch Shift Meta (Includes closing cash, expected cash, etc.)
    $meta = $pdo->prepare("SELECT s.*, u.username, u.full_name FROM shifts s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
    $meta->execute([$targetShiftId]);
    $shiftMeta = $meta->fetch();

    if (!$shiftMeta) die("Shift not found.");

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
        'meta' => $shiftMeta, // Pass full meta for closing stats
        'sales' => $salesData, 
        'totals' => $totalsData,
        'is_closed' => ($shiftMeta['status'] === 'closed')
    ];
    
    // Load Template directly
    require_once __DIR__ . '/../templates/shift_summary.php';

} else {
    echo "<div class='container mt-5'><div class='alert alert-warning shadow-sm'>No active shift found. Please clock in first.</div></div>";
}
?>
