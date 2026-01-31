<?php
// SECURITY: Logged in users only
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit;
}

$userId = $_SESSION['user_id'];

// 1. FETCH FULL USER DETAILS (Fixes the "offset" error)
$stmt = $pdo->prepare("
    SELECT u.*, l.name as location_name 
    FROM users u 
    LEFT JOIN locations l ON u.location_id = l.id 
    WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

// 2. CALCULATE SHIFT STATISTICS
// Check for open shift
$shiftStmt = $pdo->prepare("SELECT id, start_time FROM shifts WHERE user_id = ? AND status = 'open'");
$shiftStmt->execute([$userId]);
$currentShift = $shiftStmt->fetch();

$myStats = [
    'shift_total' => 0,
    'txn_count'   => 0,
    'top_item'    => 'No sales yet',
    'shift_status'=> $currentShift ? 'Open since ' . date('H:i', strtotime($currentShift['start_time'])) : 'Clocked Out'
];

if ($currentShift) {
    // Calculate Sales Total & Count for THIS shift
    $salesStmt = $pdo->prepare("
        SELECT 
            COUNT(id) as txn_count, 
            SUM(final_total) as total 
        FROM sales 
        WHERE shift_id = ? AND status = 'completed'
    ");
    $salesStmt->execute([$currentShift['id']]);
    $salesData = $salesStmt->fetch();
    
    $myStats['shift_total'] = $salesData['total'] ?? 0;
    $myStats['txn_count']   = $salesData['txn_count'] ?? 0;

    // Find Top Selling Item for THIS shift
    $topItemStmt = $pdo->prepare("
        SELECT p.name 
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        WHERE s.shift_id = ?
        GROUP BY p.id
        ORDER BY SUM(si.quantity) DESC
        LIMIT 1
    ");
    $topItemStmt->execute([$currentShift['id']]);
    $topItem = $topItemStmt->fetchColumn();
    
    if ($topItem) {
        $myStats['top_item'] = $topItem;
    }
}
?>
