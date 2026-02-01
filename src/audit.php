<?php
// SECURITY: Admins and Managers Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// 1. DEFAULT FILTERS
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
$endDate   = $_GET['end_date'] ?? date('Y-m-d');
$actionFilter = $_GET['action_type'] ?? '';

// 2. BUILD QUERY
$sql = "
    SELECT 
        il.*, 
        p.name as product_name, 
        u.username as staff_name, 
        l.name as loc_name
    FROM inventory_logs il
    JOIN products p ON il.product_id = p.id
    JOIN users u ON il.user_id = u.id
    JOIN locations l ON il.location_id = l.id
    WHERE DATE(il.created_at) BETWEEN ? AND ?
";

$params = [$startDate, $endDate];

if ($actionFilter) {
    $sql .= " AND il.action_type = ?";
    $params[] = $actionFilter;
}

$sql .= " ORDER BY il.created_at DESC LIMIT 500";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// Get action types for the dropdown from DB schema
$typeStmt = $pdo->query("SHOW COLUMNS FROM inventory_logs LIKE 'action_type'");
$typeRow = $typeStmt->fetch();
preg_match("/^enum\(\'(.*)\'\)$/", $typeRow['Type'], $matches);
$actionTypes = explode("','", $matches[1]);
?>
