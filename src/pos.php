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

// --- 3. SHIFT ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_start_shift'])) {
    if ($locationId > 0) {
        try {
            $pdo->prepare("INSERT INTO shifts (user_id, location_id, start_time, starting_cash, status) VALUES (?, ?, NOW(), ?, 'pending_approval')")
                ->execute([$userId, $locationId, floatval($_POST['starting_cash'])]);
            header("Location: index.php?page=pos"); exit;
        } catch (PDOException $e) { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = $e->getMessage(); }
    } else { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Select a station first."; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_shift_start'])) {
    $mgrUser = $_POST['mgr_username'];
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$mgrUser]);
    $manager = $stmt->fetch();
    if ($manager && password_verify($_POST['mgr_password'], $manager['password_hash']) && in_array($manager['role'], ['admin', 'manager', 'dev'])) {
        $pdo->prepare("UPDATE shifts SET status = 'open', start_verified_by = ?, start_verified_at = NOW() WHERE id = ?")->execute([$manager['id'], $_POST['pending_shift_id']]);
        $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Shift Approved!"; 
    } else { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Invalid Manager Credentials."; }
    header("Location: index.php?page=pos"); exit;
}

// --- 4. MEMBER HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Select Member
    if (isset($_POST['select_member'])) {
        $_SESSION['pos_member'] = [
            'id' => $_POST['member_id'],
            'name' => $_POST['member_name'],
            'phone' => $_POST['member_phone']
        ];
        $_SESSION['current_customer'] = $_POST['member_name']; 
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Member Linked: " . $_POST['member_name'];
        header("Location: index.php?page=pos"); exit;
    }
    
    // Remove Member
    if (isset($_POST['remove_member'])) {
        unset($_SESSION['pos_member']);
        $_SESSION['current_customer'] = 'Walk-in'; 
        $_SESSION['swal_type'] = 'info';
        $_SESSION['swal_msg'] = "Member Removed";
        header("Location: index.php?page=pos"); exit;
    }
}

// --- 5. TAB RECALL ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['recall_tab'])) {
    $saleId = $_POST['sale_id'];
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->execute([$saleId]);
    $sale = $stmt->fetch();
    
    if ($sale) {
        $_SESSION['current_tab_id'] = $sale['id'];
        $_SESSION['current_customer'] = $sale['customer_name'];
        $_SESSION['tab_paid'] = floatval($sale['amount_tendered']);
        $_SESSION['cart'] = [];
        
        // Link member if existing
        if (!empty($sale['member_id'])) {
             $mStmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
             $mStmt->execute([$sale['member_id']]);
             $m = $mStmt->fetch();
             if($m) $_SESSION['pos_member'] = ['id'=>$m['id'], 'name'=>$m['name'], 'phone'=>$m['phone']];
        }

        $items = $pdo->prepare("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
        $items->execute([$saleId]);
        foreach ($items->fetchAll() as $item) {
            $_SESSION['cart'][$item['product_id']] = ['name' => $item['name'], 'price' => $item['price_at_sale'], 'qty' => $item['quantity']];
        }
    }
    header("Location: index.php?page=pos"); exit;
}

// --- 6. POS ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeShiftId) {
    if (isset($_POST['add_item'])) {
        $pid = $_POST['product_id'];
        $stockCheck = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?");
        $stockCheck->execute([$pid, $locationId]);
        $currentStock = $stockCheck->fetchColumn() ?: 0;

        if ($currentStock > 0) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?"); $stmt->execute([$pid]);
            $product = $stmt->fetch();
            if ($product) {
                if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
                $qty = $_SESSION['cart'][$pid]['qty'] ?? 0;
                if ($qty < $currentStock) {
                    if (isset($_SESSION['cart'][$pid])) { $_SESSION['cart'][$pid]['qty']++; } 
                    else { $_SESSION['cart'][$pid] = ['name' => $product['name'], 'price' => $product['price'], 'qty' => 1]; }
                } else { $_SESSION['swal_type'] = 'warning'; $_SESSION['swal_msg'] = "Max stock reached."; }
            }
        } else { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Out of Stock!"; }
    }

    if (isset($_POST['update_qty'])) {
        $pid = $_POST['product_id'];
        $action = $_POST['action']; 
        $stockCheck = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?");
        $stockCheck->execute([$pid, $locationId]);
        $maxStock = $stockCheck->fetchColumn() ?: 0;

        if (isset($_SESSION['cart'][$pid])) {
            if ($action === 'inc') { 
                if ($_SESSION['cart'][$pid]['qty'] < $maxStock) { $_SESSION['cart'][$pid]['qty']++; } 
                else { $_SESSION['swal_type'] = 'warning'; $_SESSION['swal_msg'] = "Max stock reached."; }
            } elseif ($action === 'dec') { 
                $_SESSION['cart'][$pid]['qty']--; 
                if ($_SESSION['cart'][$pid]['qty'] <= 0) unset($_SESSION['cart'][$pid]); 
            }
        }
    }
    
    if (isset($_POST['remove_item'])) unset($_SESSION['cart'][$_POST['product_id']]);
    if (isset($_POST['clear_cart'])) unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['tab_paid'], $_SESSION['pos_member']);
    
    // --- HOLD ORDER HANDLER (NEW) ---
    if (isset($_POST['hold_order'])) {
        if (!empty($_SESSION['cart'])) {
            $cartTotal = 0;
            foreach ($_SESSION['cart'] as $item) $cartTotal += $item['price'] * $item['qty'];
            
            $customerName = !empty($_POST['hold_name']) ? $_POST['hold_name'] : 'Walk-in (Held)';
            $memberId = $_SESSION['pos_member']['id'] ?? null;
            
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, total_amount, final_total, payment_method, payment_status, customer_name, member_id, amount_tendered, change_due, created_at) VALUES (?, ?, ?, ?, ?, 'Hold', 'pending', ?, ?, 0.00, 0.00, NOW())");
                $stmt->execute([$userId, $locationId, $activeShiftId, $cartTotal, $cartTotal, $customerName, $memberId]);
                $saleId = $pdo->lastInsertId();
                
                foreach ($_SESSION['cart'] as $pid => $item) {
                    // Record Items as pending. We DO deduct inventory for Holds in a bar environment to prevent overselling.
                    $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status) VALUES (?, ?, ?, ?, 'pending')")->execute([$saleId, $pid, $item['qty'], $item['price']]);
                    
                    $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$item['qty'], $pid, $locationId]);
                    
                    $newQty = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn();
                    $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id) VALUES (?, ?, ?, ?, ?, 'sale_hold', ?)")->execute([$pid, $locationId, $userId, -$item['qty'], $newQty, $saleId]);
                }
                
                $pdo->commit();
                unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['tab_paid'], $_SESSION['pos_member']);
                $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Order Held Successfully!";
            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Failed to hold order: " . $e->getMessage();
            }
        }
        header("Location: index.php?page=pos"); exit;
    }

    // --- 7. CHECKOUT ---
    if (isset($_POST['checkout'])) {
        if (empty($_SESSION['cart'])) { header("Location: index.php?page=pos"); exit; }
        
        // 1. Calculate Base Total
        $cartTotal = 0; 
        foreach ($_SESSION['cart'] as $item) $cartTotal += $item['price'] * $item['qty'];
        
        // 2. Apply Member Discount (Backend Verification)
        $discountAmount = 0;
        if (isset($_POST['apply_discount']) && $_POST['apply_discount'] == '1' && isset($_SESSION['pos_member'])) {
            $discountAmount = $cartTotal * 0.10; // 10% Discount
        }
        $finalTotal = $cartTotal - $discountAmount;
        
        // 3. Payment Method & Amounts
        $isSplit = isset($_POST['is_split']) && $_POST['is_split'] == 1;
        $currentTendered = floatval($_POST['amount_tendered'] ?? 0);
        
        if ($isSplit) {
            $m1 = $_POST['method_1']; 
            $m2 = $_POST['method_2'];
            $method = "$m1 & $m2";
        } else {
            $method = $_POST['payment_method'];
        }

        $customerName = !empty($_POST['customer_name']) ? $_POST['customer_name'] : 'Walk-in';
        $memberId = $_SESSION['pos_member']['id'] ?? null;

        // 4. Status Logic
        $previousPaid = $_SESSION['tab_paid'] ?? 0;
        $totalRecordedTendered = $previousPaid + $currentTendered;
        
        // If "Put on Tab" selected OR Tendered < Total, status is pending
        if ($method === 'Pending' || $totalRecordedTendered < ($finalTotal - 0.01)) {
            $status = 'pending';
        } else {
            $status = 'paid';
        }
        
        $changeDue = $totalRecordedTendered - $finalTotal;

        $pdo->beginTransaction();
        try {
            if (isset($_SESSION['current_tab_id'])) {
                // Update Tab
                $saleId = $_SESSION['current_tab_id'];
                $stmt = $pdo->prepare("UPDATE sales SET total_amount = ?, final_total = ?, payment_method = ?, payment_status = ?, customer_name = ?, member_id = ?, amount_tendered = ?, change_due = ? WHERE id = ?");
                $stmt->execute([$cartTotal, $finalTotal, $method, $status, $customerName, $memberId, $totalRecordedTendered, $changeDue, $saleId]);
                $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$saleId]);
            } else {
                // New Sale
                $stmt = $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, total_amount, final_total, payment_method, payment_status, customer_name, member_id, amount_tendered, change_due) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $locationId, $activeShiftId, $cartTotal, $finalTotal, $method, $status, $customerName, $memberId, $totalRecordedTendered, $changeDue]);
                $saleId = $pdo->lastInsertId();
            }
            
            // Insert Items
            foreach ($_SESSION['cart'] as $pid => $item) {
                // Note: We save the original price in sale_items. Discount is global on the sale record.
                $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status) VALUES (?, ?, ?, ?, 'pending')")
                    ->execute([$saleId, $pid, $item['qty'], $item['price']]);
                
                if ($status === 'paid' || $status === 'pending') { 
                    $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$item['qty'], $pid, $locationId]);
                    $newQty = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn();
                    $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id) VALUES (?, ?, ?, ?, ?, 'sale', ?)")->execute([$pid, $locationId, $userId, -$item['qty'], $newQty, $saleId]);
                }
            }
            $pdo->commit();
            
            $_SESSION['last_sale_id'] = $saleId;
            unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['tab_paid'], $_SESSION['pos_member']);
            session_write_close(); 
            
        } catch (Exception $e) { $pdo->rollBack(); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage(); }
    }
    header("Location: index.php?page=pos"); exit;
}

// Data Fetching
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$members = $pdo->query("SELECT * FROM members ORDER BY name ASC")->fetchAll(); 

if ($locationId > 0) {
    $prodStmt = $pdo->prepare("SELECT p.*, COALESCE(i.quantity, 0) as stock_qty FROM products p LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = ? WHERE p.is_active = 1 ORDER BY p.name ASC");
    $prodStmt->execute([$locationId]);
    $products = $prodStmt->fetchAll();
    $openTabs = $pdo->query("SELECT s.id, s.customer_name, s.final_total, s.amount_tendered, s.created_at, u.username as cashier FROM sales s JOIN users u ON s.user_id = u.id WHERE s.payment_status = 'pending' ORDER BY s.created_at DESC")->fetchAll();
} else { $products = []; $openTabs = []; }

$total = 0;
if (isset($_SESSION['cart'])) foreach ($_SESSION['cart'] as $item) $total += $item['price'] * $item['qty'];
?>
