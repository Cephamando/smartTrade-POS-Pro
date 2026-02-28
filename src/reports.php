<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); } 
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; } 

// --- AJAX HANDLER: FETCH SALE DETAILS FOR MODAL ---
if (isset($_POST['fetch_sale_details'])) {
    header('Content-Type: application/json');
    $saleId = (int)$_POST['sale_id'];
    try {
        $stmt = $pdo->prepare("SELECT si.*, COALESCE(p.name, 'Custom Item') as name, c.type as cat_type FROM sale_items si LEFT JOIN products p ON si.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE si.sale_id = ? AND si.status != 'voided'");
        $stmt->execute([$saleId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['status' => 'success', 'items' => $items]);
    } catch(Exception $e) {
        echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]);
    }
    exit;
}

// --- 1. REFUND HANDLER --- 
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_sale'])) { 
    if (!in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) { 
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Permission Denied."; 
        header("Location: index.php?page=reports"); exit; 
    } 
    $saleId = $_POST['sale_id']; 
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?"); 
    $stmt->execute([$saleId]); 
    $sale = $stmt->fetch(); 
    
    if (!$sale) { 
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Sale not found."; 
    } elseif (strtolower($sale['payment_status']) === 'refunded') { 
        $_SESSION['swal_type'] = 'warning'; $_SESSION['swal_msg'] = "Already refunded."; 
    } elseif (strtolower($sale['payment_status']) !== 'paid') { 
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Cannot refund unpaid sales."; 
    } else { 
        $pdo->beginTransaction(); 
        try { 
            // Update Sale Status 
            $pdo->prepare("UPDATE sales SET payment_status = 'refunded' WHERE id = ?")->execute([$saleId]); 
            
            // Restore Stock 
            $items = $pdo->query("SELECT * FROM sale_items WHERE sale_id = $saleId")->fetchAll(); 
            foreach ($items as $item) { 
                $isService = $pdo->query("SELECT type FROM products WHERE id = {$item['product_id']}")->fetchColumn() === 'service'; 
                if (!$isService) { 
                    $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE product_id = ? AND location_id = ?")->execute([$item['quantity'], $item['product_id'], $sale['location_id']]); 
                    $newQty = $pdo->query("SELECT quantity FROM inventory WHERE product_id = {$item['product_id']} AND location_id = {$sale['location_id']}")->fetchColumn(); 
                    $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id, created_at) VALUES (?, ?, ?, ?, ?, 'refund', ?, NOW())")->execute([$item['product_id'], $sale['location_id'], $_SESSION['user_id'], $item['quantity'], $newQty, $saleId]); 
                } 
            } 
            $pdo->commit(); 
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Refund Processed & Stock Restored."; 
        } catch (Exception $e) { 
            $pdo->rollBack(); 
            $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "DB Error: " . $e->getMessage(); 
        } 
    } 
    header("Location: index.php?page=reports"); exit; 
} 

// --- 2. FILTERS & METRICS --- 
$startDate = $_GET['start'] ?? date('Y-m-d'); 
$endDate = $_GET['end'] ?? date('Y-m-d'); 
$locationId = $_GET['location'] ?? ''; 
$reportType = $_GET['type'] ?? 'sales'; 
$params = [$startDate . ' 00:00:00', $endDate . ' 23:59:59']; 
$locSql = ($locationId) ? " AND s.location_id = ?" : ""; 
if ($locationId) $params[] = $locationId; 

$metricsSql = "SELECT SUM(final_total) as total_revenue, SUM(tip_amount) as total_tips, COUNT(*) as trans_count, AVG(final_total) as avg_ticket FROM sales s WHERE created_at BETWEEN ? AND ?" . $locSql . " AND payment_status = 'paid'"; 
$stmt = $pdo->prepare($metricsSql); 
$stmt->execute($params); 
$metrics = $stmt->fetch(); 

$refundParams = $params; 
$refundSql = "SELECT SUM(final_total) as total_refunded, COUNT(*) as refund_count FROM sales s WHERE created_at BETWEEN ? AND ?" . $locSql . " AND payment_status = 'refunded'"; 
$stmt = $pdo->prepare($refundSql); 
$stmt->execute($refundParams); 
$refundStats = $stmt->fetch(); 

// --- 3. DATA FETCHING --- 
$reportData = []; 
if ($reportType === 'sales') { 
    $stmt = $pdo->prepare("SELECT s.*, u.username as cashier, l.name as location FROM sales s LEFT JOIN users u ON s.user_id = u.id LEFT JOIN locations l ON s.location_id = l.id WHERE s.created_at BETWEEN ? AND ? $locSql ORDER BY s.created_at DESC"); 
    $stmt->execute($params); 
    $reportData = $stmt->fetchAll(); 
} elseif ($reportType === 'itemized') { 
    // NEW: LIVE ITEMIZED PRODUCT SALES
    $iParams = [$startDate . ' 00:00:00', $endDate . ' 23:59:59']; 
    $iLocSql = ($locationId) ? " AND s.location_id = ?" : ""; 
    if ($locationId) $iParams[] = $locationId; 
    $stmt = $pdo->prepare("
        SELECT si.id as item_id, s.id as sale_id, s.created_at, COALESCE(p.name, 'Custom Item') as product_name, 
               si.quantity, si.price, (si.price * si.quantity) as line_total, 
               s.payment_status, l.name as location_name, u.username as cashier, si.status as item_status 
        FROM sale_items si 
        JOIN sales s ON si.sale_id = s.id 
        LEFT JOIN products p ON si.product_id = p.id 
        LEFT JOIN locations l ON s.location_id = l.id 
        LEFT JOIN users u ON s.user_id = u.id 
        WHERE s.created_at BETWEEN ? AND ? $iLocSql 
        ORDER BY s.created_at DESC
    "); 
    $stmt->execute($iParams); 
    $reportData = $stmt->fetchAll(); 
} elseif ($reportType === 'product') { 
    $pParams = [$startDate . ' 00:00:00', $endDate . ' 23:59:59']; 
    $pLocSql = ($locationId) ? " AND s.location_id = ?" : ""; 
    if ($locationId) $pParams[] = $locationId; 
    $stmt = $pdo->prepare("SELECT p.name, p.sku, SUM(si.quantity) as qty_sold, SUM(si.price * si.quantity) as revenue FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id WHERE s.created_at BETWEEN ? AND ? $pLocSql AND s.payment_status = 'paid' AND si.status NOT IN ('voided', 'refunded') GROUP BY p.id ORDER BY qty_sold DESC"); 
    $stmt->execute($pParams); 
    $reportData = $stmt->fetchAll(); 
} elseif ($reportType === 'audit') { 
    $aParams = [$startDate . ' 00:00:00', $endDate . ' 23:59:59']; 
    $aLocSql = ($locationId) ? " AND il.location_id = ?" : ""; 
    if ($locationId) $aParams[] = $locationId; 
    $stmt = $pdo->prepare("SELECT il.*, COALESCE(p.name, 'Unknown Product') as product_name, COALESCE(u.username, 'Unknown User') as username FROM inventory_logs il LEFT JOIN products p ON il.product_id = p.id LEFT JOIN users u ON il.user_id = u.id WHERE il.created_at BETWEEN ? AND ? $aLocSql ORDER BY il.created_at DESC"); 
    $stmt->execute($aParams); 
    $reportData = $stmt->fetchAll(); 
} 
$locations = $pdo->query("SELECT * FROM locations")->fetchAll(); 
?>
