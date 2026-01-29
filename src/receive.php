<?php
// SECURITY: Managers/Admins Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

$userId = $_SESSION['user_id'];
$locId  = $_SESSION['location_id']; // Receives into CURRENT location

// --- HANDLE POST: PROCESS GRV ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_grv'])) {
    try {
        $vendorId = $_POST['vendor_id'];
        $refNo = trim($_POST['reference_no']);
        $productIds = $_POST['product_ids'] ?? [];
        $quantities = $_POST['quantities'] ?? [];
        $costs = $_POST['costs'] ?? [];

        if (empty($vendorId) || empty($productIds)) {
            throw new Exception("Please select a vendor and at least one product.");
        }

        $pdo->beginTransaction();

        // 1. Create GRV Record
        // Calculate Total Cost
        $totalCost = 0;
        foreach ($productIds as $k => $pid) {
            $qty = floatval($quantities[$k]);
            $unitCost = floatval($costs[$k]);
            $totalCost += ($qty * $unitCost);
        }

        $stmt = $pdo->prepare("INSERT INTO grvs (vendor_id, location_id, received_by, total_cost, reference_no, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$vendorId, $locId, $userId, $totalCost, $refNo]);
        $grvId = $pdo->lastInsertId();

        // 2. Process Items & Update Stock
        $itemStmt = $pdo->prepare("INSERT INTO grv_items (grv_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)");
        
        // Upsert Stock (Insert or Update if exists)
        $stockStmt = $pdo->prepare("INSERT INTO location_stock (location_id, product_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?");

        foreach ($productIds as $k => $pid) {
            $qty = floatval($quantities[$k]);
            $unitCost = floatval($costs[$k]);

            if ($qty > 0) {
                // Record Item
                $itemStmt->execute([$grvId, $pid, $qty, $unitCost]);

                // Update Stock Level
                $stockStmt->execute([$locId, $pid, $qty, $qty]);
            }
        }

        $pdo->commit();
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Stock Received Successfully! GRV #$grvId created.";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
    }

    header("Location: index.php?page=receive");
    exit;
}

// FETCH DATA
$vendors = $pdo->query("SELECT * FROM vendors ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT id, name, unit FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
?>