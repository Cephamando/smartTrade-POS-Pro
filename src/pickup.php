<?php
// SECURITY: Logged in users only
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$locId = $_SESSION['location_id'];

// HANDLE "COLLECTED" ACTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_order'])) {
    $saleId = $_POST['sale_id'];
    
    // Mark items as 'served' to clear them from the screen/badge
    $stmt = $pdo->prepare("UPDATE sale_items SET status = 'served' WHERE sale_id = ? AND status = 'ready'");
    $stmt->execute([$saleId]);
    
    header("Location: index.php?page=pickup");
    exit;
}

// FETCH READY ORDERS
// We group by Sale ID (Ticket #)
$sql = "
    SELECT DISTINCT s.id as sale_id, s.created_at, u.username as server
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN users u ON s.user_id = u.id
    WHERE si.status = 'ready' AND s.location_id = ?
    ORDER BY s.created_at ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$locId]);

// THIS IS THE VARIABLE THE TEMPLATE NEEDS:
$readyOrders = $stmt->fetchAll(); 
?>
