<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// --- AJAX HANDLER FOR COLLECTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_order'])) {
    header('Content-Type: application/json'); 
    
    $saleId = $_POST['sale_id'];
    $collectedById = $_POST['collected_by']; // This is an INT (User ID)
    
    if (empty($collectedById)) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a waiter/staff member.']);
        exit;
    }

    try {
        $pdo->beginTransaction();

        // 1. Update Notification Status (Remove from Pickup Board)
        $stmt = $pdo->prepare("UPDATE pickup_notifications SET status = 'collected', collected_by = ? WHERE sale_id = ?");
        $stmt->execute([$collectedById, $saleId]);

        // 2. Update Sales Record (For Receipt Reconciliation)
        $stmt2 = $pdo->prepare("UPDATE sales SET collected_by = ? WHERE id = ?");
        $stmt2->execute([$collectedById, $saleId]);

        $pdo->commit();
        
        // SUCCESS: Return sale_id for the receipt popup
        echo json_encode(['status' => 'success', 'sale_id' => $saleId]);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        exit;
    }
}

// --- VIEW LOGIC (Initial Load) ---
$sql = "
    SELECT DISTINCT p.sale_id, u.full_name as server, p.created_at
    FROM pickup_notifications p
    JOIN sales s ON p.sale_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE p.status = 'ready'
    ORDER BY p.created_at ASC
";
$readyOrders = $pdo->query($sql)->fetchAll();

// Get Waiters List (ID and Name)
try {
    $waitersStmt = $pdo->query("SELECT id, full_name FROM users WHERE role IN ('waiter', 'cashier', 'manager', 'admin') AND full_name IS NOT NULL ORDER BY full_name ASC");
    $waiters = $waitersStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { $waiters = []; }
?>
