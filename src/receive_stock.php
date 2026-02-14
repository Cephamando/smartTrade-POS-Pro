<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// --- 1. HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_grv'])) {
    $locId = $_POST['location_id'];
    $ref = $_POST['ref_number'] ?? '';
    $items = $_POST['items'] ?? []; 

    if (empty($items) || empty($locId)) {
        $_SESSION['swal_type'] = 'warning';
        $_SESSION['swal_msg'] = "Please select a location and add at least one item.";
    } else {
        $pdo->beginTransaction();
        try {
            foreach ($items as $item) {
                if (empty($item['product_id']) || $item['qty'] <= 0) continue;

                $pid = $item['product_id'];
                $qty = floatval($item['qty']);
                $cost = floatval($item['cost']);

                // A. Update Stock Level
                $exists = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locId")->fetch();
                if ($exists) {
                    $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE product_id = ? AND location_id = ?")->execute([$qty, $pid, $locId]);
                } else {
                    $pdo->prepare("INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?)")->execute([$pid, $locId, $qty]);
                }

                // B. Update Product Cost (Optional: Updates system cost price)
                if ($cost > 0) {
                    $pdo->prepare("UPDATE products SET cost_price = ? WHERE id = ?")->execute([$cost, $pid]);
                }

                // C. Log the Transaction
                $newQty = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locId")->fetchColumn();
                $logSql = "INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id, created_at) 
                           VALUES (?, ?, ?, ?, ?, 'restock', ?, NOW())";
                $pdo->prepare($logSql)->execute([$pid, $locId, $_SESSION['user_id'], $qty, $newQty, "GRV:$ref"]);
            }
            $pdo->commit();
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Stock Received Successfully.";
            header("Location: index.php?page=inventory"); exit;
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
        }
    }
}

// --- 2. FETCH DATA FOR FORM ---
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
$vendors = $pdo->query("SELECT * FROM vendors WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT id, name, sku, cost_price FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// --- 3. AUTO-DETECT LOCATION ---
// Use the session location if set, otherwise default to 0
$currentLocId = $_SESSION['pos_location_id'] ?? 0;
?>
