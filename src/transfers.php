<?php
// SECURITY: Managers/Admins Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

$userId = $_SESSION['user_id'];
$sourceLocId = $_SESSION['location_id']; // Sending FROM current location

// --- HANDLE POST: CREATE TRANSFER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_transfer'])) {
    try {
        $destLocId = $_POST['destination_id'];
        $productIds = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];

        if (empty($destLocId) || empty($productIds)) throw new Exception("Invalid Transfer details.");
        if ($destLocId == $sourceLocId) throw new Exception("Cannot transfer to the same location.");

        $pdo->beginTransaction();

        // 1. Create Transfer Header
        $stmt = $pdo->prepare("INSERT INTO stock_transfers (source_location_id, destination_location_id, user_id, status, created_at) VALUES (?, ?, ?, 'completed', NOW())");
        $stmt->execute([$sourceLocId, $destLocId, $userId]);
        $transferId = $pdo->lastInsertId();

        // 2. Process Items
        $checkStock = $pdo->prepare("SELECT quantity FROM location_stock WHERE location_id = ? AND product_id = ?");
        $deductStock = $pdo->prepare("UPDATE location_stock SET quantity = quantity - ? WHERE location_id = ? AND product_id = ?");
        $addStock = $pdo->prepare("INSERT INTO location_stock (location_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");
        $logItem = $pdo->prepare("INSERT INTO stock_transfer_items (transfer_id, product_id, quantity_requested, quantity_sent, quantity_received) VALUES (?, ?, ?, ?, ?)");

        foreach ($productIds as $k => $pid) {
            $qty = floatval($quantities[$k]);
            if ($qty <= 0) continue;

            // CHECK AVAILABILITY
            $checkStock->execute([$sourceLocId, $pid]);
            $currentQty = $checkStock->fetchColumn() ?: 0;

            if ($currentQty < $qty) {
                throw new Exception("Insufficient Stock for Product ID $pid. Available: $currentQty");
            }

            // DEDUCT FROM SOURCE
            $deductStock->execute([$qty, $sourceLocId, $pid]);

            // ADD TO DESTINATION (Immediate 'completed' for simplified demo flow)
            // In a complex app, you'd set status='pending' and make destination 'accept' it.
            $addStock->execute([$destLocId, $pid, $qty, $qty]);

            // LOG
            $logItem->execute([$transferId, $pid, $qty, $qty, $qty]);
        }

        $pdo->commit();
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Transfer #$transferId Completed Successfully.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = $e->getMessage();
    }

    header("Location: index.php?page=transfers");
    exit;
}

// FETCH DATA
// 1. Destinations (All locations except current)
$stmt = $pdo->prepare("SELECT * FROM locations WHERE id != ? ORDER BY name ASC");
$stmt->execute([$sourceLocId]);
$destinations = $stmt->fetchAll();

// 2. Available Products (Only showing what we actually have in stock)
$pStmt = $pdo->prepare("
    SELECT p.id, p.name, p.unit, ls.quantity 
    FROM products p
    JOIN location_stock ls ON p.id = ls.product_id
    WHERE ls.location_id = ? AND ls.quantity > 0
    ORDER BY p.name ASC
");
$pStmt->execute([$sourceLocId]);
$availableProducts = $pStmt->fetchAll();
?>