<?php
// SECURITY: Case-Insensitive Role Check
$allowedRoles = ['admin', 'manager', 'dev'];
$userRole = isset($_SESSION['role']) ? strtolower($_SESSION['role']) : '';

if (!$userRole || !in_array($userRole, $allowedRoles)) {
    $_SESSION['swal_type'] = 'error';
    $_SESSION['swal_msg'] = "Access Denied.";
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

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll();

    // Get action types safely
    $typeStmt = $pdo->query("SHOW COLUMNS FROM inventory_logs LIKE 'action_type'");
    if ($typeRow = $typeStmt->fetch()) {
        preg_match("/^enum\(\'(.*)\'\)$/", $typeRow['Type'], $matches);
        $actionTypes = explode("','", $matches[1]);
    } else {
        $actionTypes = ['sale', 'grv', 'transfer_in', 'transfer_out', 'adjustment'];
    }
} catch (Exception $e) {
    // If table missing, empty array to prevent crash
    $logs = [];
    $actionTypes = [];
}
?>
