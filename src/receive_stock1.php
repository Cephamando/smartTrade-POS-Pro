<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// --- 1. SECURITY: LOCATION RESTRICTION CHECK ---
$currentLocId = $_SESSION['location_id'];
$locCheck = $pdo->prepare("SELECT id, name, type, can_receive_from_vendor FROM locations WHERE id = ?");
$locCheck->execute([$currentLocId]);
$currentLoc = $locCheck->fetch();

// RULE: Only 'warehouse' and 'kitchen' (or locations with explicit flag) can receive GRVs.
$isAllowed = ($currentLoc['type'] === 'warehouse' || $currentLoc['type'] === 'kitchen' || $currentLoc['can_receive_from_vendor'] == 1);

if (!$isAllowed) {
    $_SESSION['swal_type'] = 'warning';
    $_SESSION['swal_msg'] = "Access Denied: " . $currentLoc['name'] . " cannot receive external stock. Please use Internal Transfers.";
    header("Location: index.php?page=dashboard");
    exit;
}

// --- 2. HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // A. HANDLE NEW PRODUCT CREATION (From Modal)
    if (isset($_POST['save_product'])) {
        try {
            $name = trim($_POST['name']);
            $catId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
            $price = !empty($_POST['price']) ? $_POST['price'] : 0;
            $cost = !empty($_POST['cost_price']) ? $_POST['cost_price'] : 0;
            $unit = !empty($_POST['unit']) ? $_POST['unit'] : 'unit';
            
            // Duplicate Check
            $check = $pdo->prepare("SELECT id FROM products WHERE name = ?");
            $check->execute([$name]);
            if ($check->rowCount() > 0) throw new Exception("Product '$name' already exists.");

            $stmt = $pdo->prepare("INSERT INTO products (name, category_id, price, cost_price, unit) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $catId, $price, $cost, $unit]);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Product '$name' added successfully!";
            
            // Redirect back to Receive Stock to refresh the list
            header("Location: index.php?page=receive_stock");
            exit;

        } catch (Exception $e) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Error adding product: " . $e->getMessage();
        }
    }

    // B. HANDLE STOCK RECEIPT (GRV)
    if (isset($_POST['receive_stock'])) {
        try {
            $vendorId = $_POST['vendor_id'];
            $locationId = $_SESSION['location_id']; // FORCE CURRENT LOCATION
            $refNo = $_POST['reference_no'];
            $products = $_POST['products'];
            $quantities = $_POST['quantities'];
            $costs = $_POST['unit_costs'];

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("INSERT INTO grvs (vendor_id, location_id, received_by, reference_no, total_cost) VALUES (?, ?, ?, ?, 0)");
            $stmt->execute([$vendorId, $locationId, $_SESSION['user_id'], $refNo]);
            $grvId = $pdo->lastInsertId();

            $totalCost = 0;

            foreach ($products as $index => $pid) {
                $qty = floatval($quantities[$index]);
                $cost = floatval($costs[$index]);
                if ($qty > 0) {
                    $pdo->prepare("INSERT INTO grv_items (grv_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)")->execute([$grvId, $pid, $qty, $cost]);
                    
                    // Update Inventory
                    $pdo->prepare("INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + ?")->execute([$pid, $locationId, $qty, $qty]);

                    // Log
                    $newQty = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn();
                    $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id) VALUES (?, ?, ?, ?, ?, 'grv', ?)")
                        ->execute([$pid, $locationId, $_SESSION['user_id'], $qty, $newQty, $grvId]);

                    $totalCost += ($qty * $cost);
                }
            }

            $pdo->prepare("UPDATE grvs SET total_cost = ? WHERE id = ?")->execute([$totalCost, $grvId]);

            $pdo->commit();
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Stock Received Successfully (GRV #$grvId)!";
            header("Location: index.php?page=inventory");
            exit;

        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
        }
    }
}

// FETCH DATA
$vendors = $pdo->query("SELECT * FROM vendors ORDER BY name ASC")->fetchAll();
$products = $pdo->query("SELECT * FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>
