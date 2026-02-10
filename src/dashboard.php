<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { return; }

$userId = $_SESSION['user_id'];
$dashLocId = $_SESSION['pos_location_id'] ?? $_SESSION['location_id'] ?? 0;
$params = [];
$locSql = "";

if ($dashLocId > 0) {
    $locSql = " AND location_id = ?";
    $params[] = $dashLocId;
}

// 1. KPI COUNTERS
$todaySales = $pdo->prepare("SELECT SUM(final_total) FROM sales WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'" . $locSql);
$todaySales->execute($params);
$todaySales = $todaySales->fetchColumn() ?: 0.00;

$todayTransactions = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'" . $locSql);
$todayTransactions->execute($params);
$todayTransactions = $todayTransactions->fetchColumn() ?: 0;

$unpaidTabs = $pdo->prepare("SELECT COUNT(*) FROM sales WHERE payment_status = 'pending'" . $locSql);
$unpaidTabs->execute($params);
$unpaidTabs = $unpaidTabs->fetchColumn() ?: 0;

$lowStockSql = ($dashLocId > 0) ? "SELECT COUNT(*) FROM inventory WHERE location_id = ? AND quantity <= 5" : "SELECT COUNT(*) FROM inventory WHERE quantity <= 5";
$lowStockStmt = $pdo->prepare($lowStockSql);
if ($dashLocId > 0) $lowStockStmt->execute([$dashLocId]); else $lowStockStmt->execute();
$lowStockCount = $lowStockStmt->fetchColumn();

// 2. CHART DATA: LAST 7 DAYS SALES
// We need a loop for the last 7 days to ensure zero-values are filled
$dates = [];
$salesData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dates[] = date('D d', strtotime($date));
    
    $dSql = "SELECT SUM(final_total) FROM sales WHERE DATE(created_at) = ? AND payment_status = 'paid'" . $locSql;
    $dParams = array_merge([$date], $params);
    $stmt = $pdo->prepare($dSql);
    $stmt->execute($dParams);
    $salesData[] = $stmt->fetchColumn() ?: 0;
}

// 3. CHART DATA: PAYMENT METHODS (Today)
$pmSql = "SELECT payment_method, COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'" . $locSql . " GROUP BY payment_method";
$stmt = $pdo->prepare($pmSql);
$stmt->execute($params);
$pmData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Location Name
$dashLocName = "All Locations";
if ($dashLocId > 0) {
    $stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
    $stmt->execute([$dashLocId]);
    $dashLocName = $stmt->fetchColumn() ?: "Unknown";
}
?>
