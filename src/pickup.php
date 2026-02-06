<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_order'])) {
    $saleId = $_POST['sale_id'];
    $collectedBy = $_POST['collected_by'];
    
    try {
        $pdo->beginTransaction();

        // 1. Update Notification Status
        $stmt = $pdo->prepare("UPDATE pickup_notifications SET status = 'collected', collected_by = ? WHERE sale_id = ?");
        $stmt->execute([$collectedBy, $saleId]);

        // 2. CRITICAL: Update Sales Record for Receipt Reconciliation
        $stmt2 = $pdo->prepare("UPDATE sales SET collected_by = ? WHERE id = ?");
        $stmt2->execute([$collectedBy, $saleId]);

        $pdo->commit();

        if (isset($_POST['ajax'])) { echo json_encode(['status' => 'success']); exit; }

    } catch (Exception $e) {
        $pdo->rollBack();
        if (isset($_POST['ajax'])) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit; }
    }
    header("Location: index.php?page=pickup"); exit;
}

$sql = "
    SELECT DISTINCT p.sale_id, u.full_name as server, p.created_at
    FROM pickup_notifications p
    JOIN sales s ON p.sale_id = s.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE p.status = 'ready'
    ORDER BY p.created_at ASC
";
$readyOrders = $pdo->query($sql)->fetchAll();

try {
    $waitersStmt = $pdo->query("SELECT full_name FROM users WHERE full_name IS NOT NULL AND full_name != '' ORDER BY full_name ASC");
    $waiters = $waitersStmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) { $waiters = ['Staff']; }
?>
