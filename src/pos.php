<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];

// --- 1. LOCATION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_pos_location'])) {
    $_SESSION['pos_location_id'] = $_POST['pos_location_id'];
    $stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
    $stmt->execute([$_POST['pos_location_id']]);
    $_SESSION['pos_location_name'] = $stmt->fetchColumn();
    header("Location: index.php?page=pos"); exit;
}

$locationId = $_SESSION['pos_location_id'] ?? $_SESSION['location_id'] ?? 0;
$locationName = $_SESSION['pos_location_name'] ?? $_SESSION['location_name'] ?? 'Unknown Station';

$sellableLocations = $pdo->query("SELECT * FROM locations WHERE can_sell = 1 ORDER BY name ASC")->fetchAll();

// --- 2. CHECK SHIFT STATUS ---
$activeShiftId = null;
$pendingShift = null;
$expectedShiftCash = 0.00;

if ($locationId > 0) {
    $shiftStmt = $pdo->prepare("SELECT s.*, u.full_name as cashier_name FROM shifts s JOIN users u ON s.user_id = u.id WHERE s.user_id = ? AND s.status IN ('open', 'pending_approval') ORDER BY s.id DESC LIMIT 1");
    $shiftStmt->execute([$userId]);
    $currentShift = $shiftStmt->fetch();

    $activeShiftId = ($currentShift && $currentShift['status'] === 'open') ? $currentShift['id'] : null;
    $pendingShift  = ($currentShift && $currentShift['status'] === 'pending_approval') ? $currentShift : null;

    if ($activeShiftId) {
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

// --- 3. HANDLE REQUEST START ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_start_shift'])) {
    if ($locationId > 0) {
        $startCash = floatval($_POST['starting_cash']);
        try {
            $pdo->prepare("INSERT INTO shifts (user_id, location_id, start_time, starting_cash, status) VALUES (?, ?, NOW(), ?, 'pending_approval')")->execute([$userId, $locationId, $startCash]);
            header("Location: index.php?page=pos"); exit;
        } catch (PDOException $e) {
            $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Database Error: " . $e->getMessage();
        }
    } else {
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Please select a valid station first.";
    }
}

// --- 4. HANDLE MANAGER APPROVAL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_shift_start'])) {
    $mgrUser = $_POST['mgr_username'];
    $mgrPass = $_POST['mgr_password'];
    $shiftId = $_POST['pending_shift_id'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$mgrUser]);
    $manager = $stmt->fetch();
    if ($manager && password_verify($mgrPass, $manager['password_hash'])) {
        if (in_array($manager['role'], ['admin', 'manager', 'dev'])) {
            $pdo->prepare("UPDATE shifts SET status = 'open', start_verified_by = ?, start_verified_at = NOW() WHERE id = ?")->execute([$manager['id'], $shiftId]);
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Shift Approved!"; header("Location: index.php?page=pos"); exit;
        } else { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Approval Denied: Not a Manager."; }
    } else { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Invalid Credentials."; }
}

// --- HANDLE TAB RECALL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recall_tab'])) {
    $saleId = $_POST['sale_id'];
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND payment_status = 'pending'");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();
    
    if ($sale) {
        $_SESSION['current_tab_id'] = $sale['id'];
        $_SESSION['current_customer'] = $sale['customer_name'];
        $_SESSION['cart'] = [];
        $items = $pdo->prepare("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
        $items->execute([$saleId]);
        foreach ($items->fetchAll() as $item) {
            $_SESSION['cart'][$item['product_id']] = ['name' => $item['name'], 'price' => $item['price_at_sale'], 'qty' => $item['quantity']];
        }
        $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Tab Recalled: " . $sale['customer_name'];
    }
    header("Location: index.php?page=pos"); exit;
}

// --- AJAX ---
if (isset($_GET['ajax_ready_count'])) {
    if ($locationId > 0) {
        $sql = "SELECT COUNT(DISTINCT s.id) FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id JOIN categories c ON p.category_id = c.id WHERE si.status = 'ready' AND s.location_id = ? AND c.type IN ('food', 'meal')";
        $stmt = $pdo->prepare($sql); $stmt->execute([$locationId]); echo $stmt->fetchColumn() ?: 0;
    } else { echo 0; }
    exit;
}

// --- POS LOGIC ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeShiftId) {
    if (isset($_POST['add_item'])) {
        $pid = $_POST['product_id'];
        
        // 1. Check Stock Level
        $stockCheck = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?");
        $stockCheck->execute([$pid, $locationId]);
        $currentStock = $stockCheck->fetchColumn() ?: 0;

        // 2. Only Add if Stock > 0
        if ($currentStock > 0) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?"); $stmt->execute([$pid]);
            $product = $stmt->fetch();
            if ($product) {
                if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
                
                // Check if adding exceeds stock
                $currentQtyInCart = $_SESSION['cart'][$pid]['qty'] ?? 0;
                
                if ($currentQtyInCart < $currentStock) {
                    if (isset($_SESSION['cart'][$pid])) { 
                        $_SESSION['cart'][$pid]['qty']++; 
                    } else { 
                        $_SESSION['cart'][$pid] = ['name' => $product['name'], 'price' => $product['price'], 'qty' => 1]; 
                    }
                } else {
                    $_SESSION['swal_type'] = 'warning';
                    $_SESSION['swal_msg'] = "Cannot add more. Max stock reached.";
                }
            }
        } else {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Item is Out of Stock!";
        }
    }

    if (isset($_POST['update_qty'])) {
        $pid = $_POST['product_id'];
        $action = $_POST['action']; 
        
        // Check max stock for increment
        $stockCheck = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?");
        $stockCheck->execute([$pid, $locationId]);
        $maxStock = $stockCheck->fetchColumn() ?: 0;

        if (isset($_SESSION['cart'][$pid])) {
            if ($action === 'inc') { 
                if ($_SESSION['cart'][$pid]['qty'] < $maxStock) {
                    $_SESSION['cart'][$pid]['qty']++; 
                } else {
                    $_SESSION['swal_type'] = 'warning';
                    $_SESSION['swal_msg'] = "Max stock reached.";
                }
            } elseif ($action === 'dec') { 
                $_SESSION['cart'][$pid]['qty']--; 
                if ($_SESSION['cart'][$pid]['qty'] <= 0) unset($_SESSION['cart'][$pid]); 
            }
        }
    }
    if (isset($_POST['remove_item'])) { unset($_SESSION['cart'][$_POST['product_id']]); }
    if (isset($_POST['clear_cart'])) { unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['pos_member']); }

    if (isset($_POST['checkout'])) {
        if (empty($_SESSION['cart'])) { header("Location: index.php?page=pos"); exit; }
        
        $cartTotal = 0; foreach ($_SESSION['cart'] as $item) { $cartTotal += $item['price'] * $item['qty']; }
        $finalTotal = $cartTotal; 
        
        $isSplit = isset($_POST['is_split']) && $_POST['is_split'] == 1;
        if ($isSplit) {
            $m1 = $_POST['method_1']; $a1 = floatval($_POST['amount_1']);
            $m2 = $_POST['method_2']; $a2 = floatval($_POST['amount_2']);
            if (($a1 + $a2) < $finalTotal) { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Total tendered is less than bill amount!"; header("Location: index.php?page=pos"); exit; }
            $method = "$m1 & $m2"; $tendered = $a1 + $a2;
        } else {
            $method = $_POST['payment_method']; $tendered = floatval($_POST['amount_tendered'] ?? $finalTotal);
        }

        $customerName = !empty($_POST['customer_name']) ? $_POST['customer_name'] : 'Walk-in';
        $status = ($method === 'Pending') ? 'pending' : 'paid'; 
        $collectedBy = ($status === 'paid') ? $_SESSION['username'] : null;

        $pdo->beginTransaction();
        try {
            if (isset($_SESSION['current_tab_id'])) {
                $saleId = $_SESSION['current_tab_id'];
                $stmt = $pdo->prepare("UPDATE sales SET final_total = ?, payment_method = ?, payment_status = ?, customer_name = ?, amount_tendered = ?, change_due = ?, collected_by = ? WHERE id = ?");
                $stmt->execute([$finalTotal, $method, $status, $customerName, $tendered, ($tendered - $finalTotal), $collectedBy, $saleId]);
                $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$saleId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, total_amount, final_total, payment_method, payment_status, customer_name, amount_tendered, change_due, collected_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $locationId, $activeShiftId, $cartTotal, $finalTotal, $method, $status, $customerName, $tendered, ($tendered - $finalTotal), $collectedBy]);
                $saleId = $pdo->lastInsertId();
            }
            foreach ($_SESSION['cart'] as $pid => $item) {
                // FORCE 'pending' status for KDS to pick it up
                $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status) VALUES (?, ?, ?, ?, 'pending')")
                    ->execute([$saleId, $pid, $item['qty'], $item['price']]);
                
                if ($status === 'paid') {
                    $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$item['qty'], $pid, $locationId]);
                    $newQty = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn();
                    $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id) VALUES (?, ?, ?, ?, ?, 'sale', ?)")->execute([$pid, $locationId, $userId, -$item['qty'], $newQty, $saleId]);
                }
            }
            $pdo->commit();
            $_SESSION['last_sale_id'] = $saleId;
            unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer']);
        } catch (Exception $e) { $pdo->rollBack(); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Transaction Failed: " . $e->getMessage(); }
    }
    
    header("Location: index.php?page=pos"); exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

if ($locationId > 0) {
    $prodStmt = $pdo->prepare("SELECT p.*, COALESCE(i.quantity, 0) as stock_qty FROM products p LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = ? WHERE p.is_active = 1 ORDER BY p.name ASC");
    $prodStmt->execute([$locationId]);
    $products = $prodStmt->fetchAll();
    
    $openTabs = $pdo->query("SELECT s.id, s.customer_name, s.final_total, s.created_at, u.username as cashier, l.name as loc_name FROM sales s JOIN users u ON s.user_id = u.id JOIN locations l ON s.location_id = l.id WHERE s.payment_status = 'pending' ORDER BY s.created_at DESC")->fetchAll();
} else {
    $products = [];
    $openTabs = [];
}

$total = 0;
if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $total += ($item['price'] * $item['qty']);
    }
}
?>
