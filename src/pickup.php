<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_order'])) {
    $saleId = $_POST['sale_id'];
    $collectedBy = $_POST['collected_by'];
    try {
        $stmt = $pdo->prepare("UPDATE pickup_notifications SET status = 'collected', collected_by = ? WHERE sale_id = ?");
        $stmt->execute([$collectedBy, $saleId]);
        if (isset($_POST['ajax'])) { echo json_encode(['status' => 'success']); exit; }
    } catch (Exception $e) {
        if (isset($_POST['ajax'])) { echo json_encode(['status' => 'error', 'message' => $e->getMessage()]); exit; }
    }
    header("Location: index.php?page=pickup"); exit;
}

// FIXED QUERY: Added p.created_at to SELECT
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
