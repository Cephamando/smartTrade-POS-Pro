<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];

// --- 1. RESOLVE USER ROLE ---
$roleStmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
$roleStmt->execute([$userId]);
$userRole = strtolower($roleStmt->fetchColumn() ?: '');
$_SESSION['role'] = $userRole; // heal session

// --- 2. HANDLE MANUAL LOCATION SWITCH ---
if (isset($_POST['set_pos_location'])) {
    $_SESSION['pos_location_id'] = (int)$_POST['set_pos_location'];
    session_write_close(); // Force save before redirect
    header("Location: index.php?page=pos"); 
    exit;
}

// --- 3. DETERMINE LOCATION & SHIFT ---
$locationId = $_SESSION['pos_location_id'] ?? 0;
$activeShiftId = null; 
$pendingShift = null; 
$expectedShiftCash = 0.00; 
$locationName = 'Unknown';

$shiftStmt = $pdo->prepare("SELECT s.*, u.full_name as cashier_name FROM shifts s JOIN users u ON s.user_id = u.id WHERE s.user_id = ? AND s.status IN ('open', 'pending_approval') ORDER BY s.id DESC LIMIT 1"); 
$shiftStmt->execute([$userId]);
$currentShift = $shiftStmt->fetch();

if ($currentShift) {
    $activeShiftId = ($currentShift['status'] === 'open') ? $currentShift['id'] : null;
    $pendingShift  = ($currentShift['status'] === 'pending_approval') ? $currentShift : null;
    
    // If NOT an admin/manager, lock them to the shift location.
    if (!in_array($userRole, ['admin', 'manager', 'dev'])) {
        $locationId = $currentShift['location_id'];
        $_SESSION['pos_location_id'] = $locationId;
    } else {
        // For Admins: Only use shift location if they haven't manually selected one yet.
        if ($locationId == 0) {
            $locationId = $currentShift['location_id'];
            $_SESSION['pos_location_id'] = $locationId;
        }
    }
    
    // Calculate expected cash
    if($activeShiftId) {
        $startCash = floatval($currentShift['starting_cash']);
        $csStmt = $pdo->prepare("SELECT SUM(final_total) FROM sales WHERE shift_id = ? AND payment_status = 'paid' AND payment_method LIKE '%Cash%'"); 
        $csStmt->execute([$activeShiftId]); 
        $cashSales = floatval($csStmt->fetchColumn() ?: 0);
        
        $exStmt = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE user_id = ? AND created_at >= ?"); 
        $exStmt->execute([$userId, $currentShift['start_time']]); 
        $expenses = floatval($exStmt->fetchColumn() ?: 0);
        
        $expectedShiftCash = ($startCash + $cashSales) - $expenses;
    }
}

if ($locationId > 0) {
    $locationName = $pdo->query("SELECT name FROM locations WHERE id = $locationId")->fetchColumn();
}


// --- 4. HANDLE ALL OTHER ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Service Management
    if (isset($_POST['save_service'])) {
        $name = trim($_POST['name']); $price = floatval($_POST['price']); $isOpen = isset($_POST['is_open_price']) ? 1 : 0;
        try {
            $pdo->prepare("INSERT INTO products (name, price, type, is_open_price, is_active) VALUES (?, ?, 'service', ?, 1)")->execute([$name, $price, $isOpen]);
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Service added.";
        } catch (PDOException $e) { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "DB Error: " . $e->getMessage(); }
        header("Location: index.php?page=pos"); exit;
    }
    if (isset($_POST['delete_service'])) {
        $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?")->execute([$_POST['service_id']]);
        header("Location: index.php?page=pos"); exit;
    }

    // Mark Collected
    if (isset($_POST['mark_collected'])) {
        $itemId = $_POST['item_id'];
        $stmt = $pdo->prepare("
            SELECT si.sale_id, si.status as kds_status, p.type as prod_type, c.type as cat_type 
            FROM sale_items si 
            JOIN products p ON si.product_id = p.id 
            LEFT JOIN categories c ON p.category_id = c.id
            WHERE si.id = ?
        ");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();

        if ($item && $item['prod_type'] === 'item') {
            $isFood = in_array(strtolower($item['cat_type'] ?? ''), ['food', 'meal']);
            if ($isFood) {
                if ($item['kds_status'] === 'cooking') { echo json_encode(['status'=>'error', 'msg'=>'🚫 Item is currently COOKING. Please wait.']); exit; }
                if ($item['kds_status'] === 'ready') { echo json_encode(['status'=>'redirect_pickup', 'msg'=>'⚠️ Item is READY. Please collect at the Pickup Screen.']); exit; }
                if ($item['kds_status'] === 'pending') { echo json_encode(['status'=>'error', 'msg'=>'🚫 Item is PENDING in Kitchen.']); exit; }
            }
        }

        $pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected', status = 'served' WHERE id = ?")->execute([$itemId]);
        
        $saleId = $item['sale_id'];
        $saleState = $pdo->query("SELECT payment_status FROM sales WHERE id = $saleId")->fetch();
        $pendingCount = $pdo->query("SELECT COUNT(*) FROM sale_items WHERE sale_id = $saleId AND fulfillment_status = 'uncollected'")->fetchColumn();
        $isTabComplete = ($saleState['payment_status'] === 'paid' && $pendingCount == 0);

        echo json_encode(['status' => 'success', 'tab_completed' => $isTabComplete, 'print_receipt' => true, 'sale_id' => $saleId, 'item_id' => $itemId]); 
        exit;
    }

    // Shift Processing
    if (isset($_POST['request_start_shift']) && $locationId > 0) { 
        $pdo->prepare("INSERT INTO shifts (user_id, location_id, start_time, starting_cash, status) VALUES (?, ?, NOW(), ?, 'pending_approval')")->execute([$userId, $locationId, floatval($_POST['starting_cash'])]); 
        header("Location: index.php?page=pos"); exit; 
    }
    if (isset($_POST['approve_shift_start'])) {
        $mgr = $pdo->prepare("SELECT * FROM users WHERE username = ?"); $mgr->execute([$_POST['mgr_username'] ?? '']); $m = $mgr->fetch();
        if ($m && password_verify($_POST['mgr_password'] ?? '', $m['password_hash']) && in_array($m['role'], ['admin','manager','dev'])) { 
            $pdo->prepare("UPDATE shifts SET status='open' WHERE id=?")->execute([$_POST['pending_shift_id']]); 
        } header("Location: index.php?page=pos"); exit;
    }
    
    // Active Shift Actions
    if ($activeShiftId) {
        
        if (isset($_POST['add_item'])) {
            $pid = intval($_POST['product_id']); 
            $p = $pdo->query("SELECT p.*, c.type as cat_type FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = $pid")->fetch();
            if ($p) {
                $price = ($p['is_open_price'] && !empty($_POST['custom_price'])) ? floatval($_POST['custom_price']) : $p['price'];
                $cartKey = uniqid(); 
                $_SESSION['cart'][$cartKey] = [
                    'product_id' => $pid, 'name' => $p['name'], 'price' => $price, 'qty' => 1, 
                    'type' => $p['type'], 'cat_type' => $p['cat_type'] ?? 'other', 'fulfillment' => 'uncollected'
                ]; 
            } header("Location: index.php?page=pos"); exit;
        }

        if (isset($_POST['toggle_fulfillment'])) { 
            $key = $_POST['cart_key']; 
            if(isset($_SESSION['cart'][$key])) { 
                $cType = strtolower($_SESSION['cart'][$key]['cat_type'] ?? '');
                // Block toggling if it's a food/meal item
                if (!in_array($cType, ['food', 'meal'])) {
                    $_SESSION['cart'][$key]['fulfillment'] = ($_SESSION['cart'][$key]['fulfillment'] === 'collected') ? 'uncollected' : 'collected'; 
                }
            } 
            header("Location: index.php?page=pos"); exit; 
        }

        if (isset($_POST['update_qty'])) { $key = $_POST['cart_key']; if ($_POST['action'] === 'inc') $_SESSION['cart'][$key]['qty']++; elseif ($_POST['action'] === 'dec') { $_SESSION['cart'][$key]['qty']--; if($_SESSION['cart'][$key]['qty']<=0) unset($_SESSION['cart'][$key]); } header("Location: index.php?page=pos"); exit; }
        if (isset($_POST['remove_item'])) { unset($_SESSION['cart'][$_POST['cart_key']]); header("Location: index.php?page=pos"); exit; }
        if (isset($_POST['clear_cart'])) { unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['tab_paid'], $_SESSION['pos_member']); header("Location: index.php?page=pos"); exit; }
        
        if (isset($_POST['log_waste']) && !empty($_SESSION['cart'])) {
            $pdo->beginTransaction();
            try {
                foreach ($_SESSION['cart'] as $item) {
                    if (($item['type'] ?? 'item') !== 'service') {
                        $qty = $item['qty']; $pid = $item['product_id'];
                        $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$qty, $pid, $locationId]);
                        $nq = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn();
                        $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id, created_at) VALUES (?,?,?,?,?,'waste', 0, NOW())")->execute([$pid, $locationId, $userId, -$qty, $nq]);
                    }
                }
                $pdo->commit(); unset($_SESSION['cart']); $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Items logged as waste.";
            } catch (Exception $e) { $pdo->rollBack(); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = $e->getMessage(); }
            header("Location: index.php?page=pos"); exit;
        }

        if (isset($_POST['recall_tab'])) {
            $saleId = $_POST['sale_id']; $sale = $pdo->query("SELECT * FROM sales WHERE id = $saleId")->fetch();
            $_SESSION['current_tab_id'] = $saleId; $_SESSION['current_customer'] = $sale['customer_name']; $_SESSION['tab_paid'] = $sale['amount_tendered']; $_SESSION['cart'] = [];
            $items = $pdo->query("SELECT si.*, p.name, p.type, c.type as cat_type FROM sale_items si JOIN products p ON si.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE si.sale_id = $saleId")->fetchAll();
            foreach($items as $i) { 
                $key = uniqid();
                $_SESSION['cart'][$key] = [
                    'product_id' => $i['product_id'], 'name' => $i['name'], 'price' => $i['price_at_sale'], 
                    'qty' => $i['quantity'], 'type' => $i['type'] ?? 'item', 'cat_type' => $i['cat_type'] ?? 'other', 'fulfillment' => $i['fulfillment_status']
                ]; 
            } header("Location: index.php?page=pos"); exit;
        }

        // Add To Tab
        if (isset($_POST['add_to_tab_action']) && !empty($_SESSION['cart'])) {
            $targetTabId = $_POST['target_tab_id'] ?? 'new'; 
            $customerName = $_POST['tab_customer_name'] ?? 'Walk-in';
            $pdo->beginTransaction();
            try {
                $saleId = 0;
                if ($targetTabId === 'new') {
                    $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, final_total, final_total, payment_method, payment_status, customer_name, amount_tendered, change_due, created_at) VALUES (?, ?, ?, 0, 0, 'Pending', 'pending', ?, 0, 0, NOW())")->execute([$userId, $locationId, $activeShiftId, $customerName]);
                    $saleId = $pdo->lastInsertId();
                } else {
                    $saleId = $targetTabId;
                    if (isset($_SESSION['current_tab_id']) && $_SESSION['current_tab_id'] == $saleId) {
                        $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$saleId]);
                    }
                }
                foreach ($_SESSION['cart'] as $item) {
                    $fulfill = $item['fulfillment'] ?? 'uncollected'; $pid = $item['product_id']; $qty = intval($item['qty']);
                    $catType = strtolower($item['cat_type'] ?? 'other');
                    $initialStatus = in_array($catType, ['food', 'meal']) ? 'pending' : 'ready';

                    $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status, fulfillment_status) VALUES (?, ?, ?, ?, ?, ?)")->execute([$saleId, $pid, $qty, $item['price'], $initialStatus, $fulfill]);
                    if (($item['type'] ?? 'item') !== 'service') {
                        $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$qty, $pid, $locationId]);
                        $nq = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn();
                        $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id, created_at) VALUES (?,?,?,?,?,'sale',?, NOW())")->execute([$pid, $locationId, $userId, -$qty, $nq, $saleId]);
                    }
                }
                $newTotal = $pdo->query("SELECT SUM(quantity * price_at_sale) FROM sale_items WHERE sale_id = $saleId")->fetchColumn();
                $pdo->prepare("UPDATE sales SET final_total = ?, final_total = ? WHERE id = ?")->execute([$newTotal, $newTotal, $saleId]);
                $pdo->commit(); $_SESSION['last_sale_id'] = $saleId; 
                unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['tab_paid'], $_SESSION['pos_member']); 
                $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Tab Updated.";
            } catch (Exception $e) { $pdo->rollBack(); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage(); }
            header("Location: index.php?page=pos"); exit;
        }

        // Checkout
        if (isset($_POST['checkout']) && !empty($_SESSION['cart'])) {
            $total = 0; foreach ($_SESSION['cart'] as $i) $total += $i['price'] * $i['qty'];
            $disc = (isset($_POST['apply_discount']) && isset($_SESSION['pos_member'])) ? $total * 0.10 : 0;
            $final = $total - $disc;
            $isSplit = isset($_POST['is_split']) && $_POST['is_split'] == 1;
            $method = $isSplit ? ($_POST['method_1'] . ' & ' . $_POST['method_2']) : $_POST['payment_method'];
            $tendered = ($method === 'Pending' && !$isSplit) ? 0 : floatval($_POST['amount_tendered']);
            $tip = floatval($_POST['tip_amount']);
            $status = ($method === 'Pending' || $tendered < ($final + $tip - 0.01)) ? 'pending' : 'paid';
            $change = $tendered - ($final + $tip);
            $cust = $_POST['customer_name'] ?? 'Walk-in';
            $memId = $_SESSION['pos_member']['id'] ?? null;

            $pdo->beginTransaction();
            try {
                if (isset($_SESSION['current_tab_id'])) {
                    $oldTabId = $_SESSION['current_tab_id'];
                    $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, final_total, final_total, payment_method, payment_status, customer_name, member_id, amount_tendered, change_due, tip_amount, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?, NOW())")->execute([$userId, $locationId, $activeShiftId, $total, $final, $method, $status, $cust, $memId, $tendered, $change, $tip]);
                    $newSaleId = $pdo->lastInsertId();

                    foreach ($_SESSION['cart'] as $item) {
                        $fulfill = $item['fulfillment'] ?? 'uncollected'; $pid = $item['product_id']; $qty = $item['qty'];
                        $catType = strtolower($item['cat_type'] ?? 'other');
                        $initialStatus = in_array($catType, ['food', 'meal']) ? 'pending' : 'ready';
                        $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status, fulfillment_status) VALUES (?,?,?,?,?,?)")->execute([$newSaleId, $pid, $qty, $item['price'], $initialStatus, $fulfill]);
                    }
                    $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$oldTabId]);
                    $pdo->prepare("DELETE FROM sales WHERE id = ?")->execute([$oldTabId]);
                    $_SESSION['last_sale_id'] = $newSaleId;
                } else {
                    $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, final_total, final_total, payment_method, payment_status, customer_name, member_id, amount_tendered, change_due, tip_amount, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?, NOW())")->execute([$userId, $locationId, $activeShiftId, $total, $final, $method, $status, $cust, $memId, $tendered, $change, $tip]);
                    $sid = $pdo->lastInsertId(); $_SESSION['last_sale_id'] = $sid;
                    foreach ($_SESSION['cart'] as $item) {
                        $pid = $item['product_id']; $qty = $item['qty']; $fulfill = $item['fulfillment'] ?? 'uncollected';
                        $catType = strtolower($item['cat_type'] ?? 'other');
                        $initialStatus = in_array($catType, ['food', 'meal']) ? 'pending' : 'ready';
                        $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status, fulfillment_status) VALUES (?,?,?,?,?,?)")->execute([$sid, $pid, $qty, $item['price'], $initialStatus, $fulfill]);
                        if (($item['type'] ?? 'item') !== 'service') { 
                            $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$qty, $pid, $locationId]);
                            $nq = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn();
                            $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id, created_at) VALUES (?,?,?,?,?,'sale',?, NOW())")->execute([$pid, $locationId, $userId, -$qty, $nq, $sid]);
                        }
                    }
                }
                $pdo->commit(); unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['tab_paid'], $_SESSION['pos_member']);
            } catch(Exception $e) { $pdo->rollBack(); }
            header("Location: index.php?page=pos"); exit;
        }

        // Finalize Split
        if (isset($_POST['finalize_split'])) {
            $splitData = json_decode($_POST['split_data'], true);
            if (!empty($splitData)) {
                $pdo->beginTransaction();
                try {
                    foreach ($splitData as $guest) {
                        $total = $guest['total']; $method = $guest['method']; $status = ($method === 'Pending') ? 'pending' : 'paid';
                        $customer = !empty($guest['name']) ? $guest['name'] : 'Guest'; $tendered = ($status === 'paid') ? $total : 0;
                        $stmt = $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, final_total, final_total, payment_method, payment_status, customer_name, amount_tendered, change_due, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
                        $stmt->execute([$userId, $locationId, $activeShiftId, $total, $total, $method, $status, $customer, $tendered]);
                        $saleId = $pdo->lastInsertId();
                        foreach ($guest['items'] as $item) {
                            $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status, fulfillment_status) VALUES (?, ?, ?, ?, 'ready', 'uncollected')")->execute([$saleId, $item['id'], $item['qty'], $item['price']]);
                            if (($item['type'] ?? 'item') !== 'service') {
                                $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$item['qty'], $item['id'], $locationId]);
                                $nq = $pdo->query("SELECT quantity FROM inventory WHERE product_id = {$item['id']} AND location_id = $locationId")->fetchColumn();
                                $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id, created_at) VALUES (?,?,?,?,?,'sale',?, NOW())")->execute([$item['id'], $locationId, $userId, -$item['qty'], $nq, $saleId]);
                            }
                        }
                    }
                    $pdo->commit(); unset($_SESSION['cart']); $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Split Processed.";
                } catch (Exception $e) { $pdo->rollBack(); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage(); }
            }
            header("Location: index.php?page=pos"); exit;
        }
    }
}

// --- 5. LOAD UI DATA ---
$sellableLocations = $pdo->query("SELECT * FROM locations WHERE can_sell = 1 ORDER BY name ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$members = $pdo->query("SELECT * FROM members ORDER BY name ASC")->fetchAll();
$products = []; $services = []; $openTabs = []; $tabItems = []; 

if ($locationId > 0) {
    $products = $pdo->query("SELECT p.*, c.type as cat_type, COALESCE(i.quantity, 0) as stock_qty FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = $locationId WHERE p.is_active = 1 AND p.type = 'item' ORDER BY p.name ASC")->fetchAll();
    $services = $pdo->query("SELECT * FROM products WHERE type = 'service' AND is_active = 1 ORDER BY name ASC")->fetchAll();
    $openTabs = $pdo->query("SELECT s.id, s.customer_name, s.final_total, s.amount_tendered, s.created_at, s.payment_status, (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id AND si.fulfillment_status = 'uncollected') as uncollected_count FROM sales s WHERE (s.payment_status = 'pending' OR (s.payment_status = 'paid' AND EXISTS (SELECT 1 FROM sale_items si WHERE si.sale_id = s.id AND si.fulfillment_status = 'uncollected'))) AND s.location_id = $locationId ORDER BY s.created_at DESC")->fetchAll();
    
    if (!empty($openTabs)) {
        $ids = implode(',', array_column($openTabs, 'id'));
        $rawItems = $pdo->query("SELECT si.*, p.name, p.type, c.type as cat_type FROM sale_items si JOIN products p ON si.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE si.sale_id IN ($ids)")->fetchAll();
        foreach($rawItems as $r) { $tabItems[$r['sale_id']][] = $r; }
    }
}

$total = 0; if(isset($_SESSION['cart'])) { foreach($_SESSION['cart'] as $i) $total += $i['price'] * $i['qty']; }
$balance = $total - ($_SESSION['tab_paid'] ?? 0);
?>
