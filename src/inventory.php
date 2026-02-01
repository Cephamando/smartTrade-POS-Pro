<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$locationId = $_SESSION['location_id'];

// --- AJAX: FETCH PRODUCT HISTORY (STOCK CARD) ---
if (isset($_GET['ajax_history']) && isset($_GET['product_id'])) {
    $pid = $_GET['product_id'];
    
    $sql = "SELECT il.*, u.username 
            FROM inventory_logs il 
            JOIN users u ON il.user_id = u.id 
            WHERE il.product_id = ? AND il.location_id = ? 
            ORDER BY il.created_at DESC LIMIT 50";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$pid, $locationId]);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($logs);
    exit;
}

// --- HANDLE STOCK UPDATES (GRV / Adjustments) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_stock'])) {
        $pid = $_POST['product_id'];
        $qtyChange = floatval($_POST['quantity_change']);
        $reason = $_POST['reason']; // 'restock', 'damage', 'correction'

        if ($qtyChange != 0) {
            $pdo->beginTransaction();
            
            // 1. Update Inventory
            $pdo->prepare("INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)")
                ->execute([$pid, $locationId, $qtyChange]);
            
            // 2. Log Movement
            $newQty = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn();
            
            $actionType = ($qtyChange > 0) ? 'grv' : 'adjustment';
            
            $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id) VALUES (?, ?, ?, ?, ?, ?, NULL)")
                ->execute([$pid, $locationId, $_SESSION['user_id'], $qtyChange, $newQty, $actionType]);
            
            $pdo->commit();
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Stock updated successfully.";
        }
    }
    header("Location: index.php?page=inventory"); exit;
}

// --- FETCH INVENTORY LIST ---
$sql = "SELECT p.id, p.name, p.category_id, c.name as category_name, p.price, COALESCE(i.quantity, 0) as quantity 
        FROM products p 
        LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = $locationId 
        JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1 
        ORDER BY p.name ASC";
$inventory = $pdo->query($sql)->fetchAll();
?>
