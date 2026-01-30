<?php
// SECURITY: Managers/Admins Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// 1. DEFAULT FILTERS (Current Month)
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$locationId = $_GET['location_id'] ?? '';

// 2. BUILD QUERY CONDITIONS
$where = "WHERE s.status = 'completed' AND DATE(s.created_at) BETWEEN ? AND ?";
$params = [$startDate, $endDate];

if (!empty($locationId)) {
    $where .= " AND s.location_id = ?";
    $params[] = $locationId;
}

// 3. FETCH KPI METRICS
$kpiSql = "
    SELECT 
        COUNT(id) as total_txns,
        SUM(final_total) as total_revenue,
        AVG(final_total) as avg_basket
    FROM sales s
    $where
";
$stmt = $pdo->prepare($kpiSql);
$stmt->execute($params);
$kpi = $stmt->fetch();

// 4. FETCH PAYMENT BREAKDOWN
$paySql = "
    SELECT payment_method, SUM(final_total) as total
    FROM sales s
    $where
    GROUP BY payment_method
";
$stmt = $pdo->prepare($paySql);
$stmt->execute($params);
$payments = $stmt->fetchAll(PDO::FETCH_KEY_PAIR); // ['cash' => 1000, 'card' => 500]

// 5. FETCH TOP 5 PRODUCTS
$topSql = "
    SELECT p.name, SUM(si.quantity) as qty_sold, SUM(si.quantity * si.price_at_sale) as revenue
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    $where
    GROUP BY p.id
    ORDER BY qty_sold DESC
    LIMIT 5
";
$stmt = $pdo->prepare($topSql);
$stmt->execute($params);
$topProducts = $stmt->fetchAll();

// 6. FETCH RECENT TRANSACTIONS
$txnSql = "
    SELECT s.*, u.username, l.name as loc_name
    FROM sales s
    JOIN users u ON s.user_id = u.id
    JOIN locations l ON s.location_id = l.id
    $where
    ORDER BY s.created_at DESC
    LIMIT 100
";
$stmt = $pdo->prepare($txnSql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// Fetch Locations for Filter Dropdown
$locs = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
?>
