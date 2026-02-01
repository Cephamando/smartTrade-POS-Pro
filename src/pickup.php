<?php
// SECURITY: Logged in users only
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$locId = $_SESSION['location_id'];

// HANDLE "COLLECTED" ACTION (AJAX & STANDARD)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['collect_order'])) {
    $saleId = $_POST['sale_id'];
    $collectedBy = $_POST['collected_by'];

    if (!empty($collectedBy)) {
        // 1. Mark items as 'served'
        $stmt = $pdo->prepare("UPDATE sale_items SET status = 'served' WHERE sale_id = ? AND status = 'ready'");
        $stmt->execute([$saleId]);

        // 2. Record who collected it
        $stmt = $pdo->prepare("UPDATE sales SET collected_by = ? WHERE id = ?");
        $stmt->execute([$collectedBy, $saleId]);
        
        // 3. IF AJAX, RETURN JSON
        if (isset($_POST['ajax'])) {
            echo json_encode(['status' => 'success', 'sale_id' => $saleId]);
            exit;
        }

        header("Location: index.php?page=pickup");
        exit;
    }
}

// FETCH READY ORDERS - Filtered to exclude drinks
$sql = "
    SELECT DISTINCT s.id as sale_id, s.created_at, u.username as server
    FROM sale_items si
    JOIN sales s ON si.sale_id = s.id
    JOIN products p ON si.product_id = p.id
    JOIN categories c ON p.category_id = c.id
    JOIN users u ON s.user_id = u.id
    WHERE si.status = 'ready' 
    AND s.location_id = ?
    AND c.type IN ('food', 'meal')
    ORDER BY s.created_at ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$locId]);
$readyOrders = $stmt->fetchAll();

// FETCH WAITERS for the collection dropdown
$waiters = $pdo->query("SELECT full_name FROM users WHERE role IN ('cashier', 'waiter', 'bartender', 'manager', 'admin', 'dev') AND location_id = $locId ORDER BY full_name ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
