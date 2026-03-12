<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// --- 1. HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_grv'])) {
    $locId = $_POST['location_id'] ?? null;
    $vendorId = $_POST['vendor_id'] ?? null;
    $ref = $_POST['ref_number'] ?? '';
    $items = $_POST['items'] ?? []; 
    $userId = $_SESSION['user_id'];

    // VALIDATION
    if (empty($items) || empty($locId)) {
        $_SESSION['swal_type'] = 'warning';
        $_SESSION['swal_msg'] = "Please select a location and add at least one item.";
    } elseif (empty($vendorId)) {
        $_SESSION['swal_type'] = 'warning';
        $_SESSION['swal_msg'] = "Please select a Vendor.";
    } else {
        $pdo->beginTransaction();
        try {
            // A. Calculate Total Cost for the GRV Header (FIXED: Cast to float to prevent string * string error)
            $totalCost = 0;
            foreach ($items as $item) {
                $calcQty = isset($item['qty']) ? floatval($item['qty']) : 0;
                $calcCost = isset($item['cost']) ? floatval($item['cost']) : 0;
                
                if (!empty($item['product_id']) && $calcQty > 0) {
                    $totalCost += ($calcQty * $calcCost);
                }
            }

            // B. Create the GRV Header (Goods Received Voucher)
            // This generates the numeric ID we need for the logs
            $stmtGrv = $pdo->prepare("INSERT INTO grvs (vendor_id, location_id, received_by, total_cost, reference_no, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $stmtGrv->execute([$vendorId, $locId, $userId, $totalCost, $ref]);
            $grvId = $pdo->lastInsertId(); // <--- THIS IS THE INTEGER ID

            // Prepare statements for the loop
            $stmtGrvItem = $pdo->prepare("INSERT INTO grv_items (grv_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)");
            $stmtUpsert = $pdo->prepare("INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)");
            $stmtCost = $pdo->prepare("UPDATE products SET cost_price = ? WHERE id = ?");
            $stmtLog = $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id, created_at) VALUES (?, ?, ?, ?, ?, 'restock', ?, NOW())");
            $stmtGetQty = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?");

            // C. Process Items
            foreach ($items as $item) {
                $qty = isset($item['qty']) ? floatval($item['qty']) : 0;
                $cost = isset($item['cost']) ? floatval($item['cost']) : 0;
                
                if (empty($item['product_id']) || $qty <= 0) continue;

                $pid = $item['product_id'];

                // 1. Link Item to GRV
                $stmtGrvItem->execute([$grvId, $pid, $qty, $cost]);

                // 2. Update Stock Level
                $stmtUpsert->execute([$pid, $locId, $qty]);

                // 3. Update Product Cost (Weighted average logic could go here, currently overwriting)
                if ($cost > 0) {
                    $stmtCost->execute([$cost, $pid]);
                }

                // 4. Get new quantity for log
                $stmtGetQty->execute([$pid, $locId]);
                $newQty = $stmtGetQty->fetchColumn();

                // 5. Log the Transaction using the GRV ID (Integer)
                // This fixes the 'Incorrect integer value' error
                $stmtLog->execute([$pid, $locId, $userId, $qty, $newQty, $grvId]);
            }

            $pdo->commit();
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Stock Received Successfully (GRV #$grvId).";
            
            // Redirect happens in the View via JS to show the alert first
            
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Database Error: " . $e->getMessage();
        }
    }
}

// --- 2. FETCH DATA FOR FORM ---
try {
    $locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
    $vendors = $pdo->query("SELECT * FROM vendors WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
    $products = $pdo->query("SELECT id, name, sku, cost_price FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// --- 3. AUTO-DETECT LOCATION ---
$currentLocId = $_SESSION['pos_location_id'] ?? ($_SESSION['location_id'] ?? 0);
?>
