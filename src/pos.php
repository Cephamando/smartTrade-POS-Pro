<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$locationId = $_SESSION['pos_location_id'] ?? $_SESSION['location_id'] ?? 0;
if (empty($_SESSION['location_name']) && $locationId > 0) { $stmtLoc = $pdo->prepare("SELECT name FROM locations WHERE id = ?"); $stmtLoc->execute([$locationId]); $_SESSION['location_name'] = $stmtLoc->fetchColumn() ?: 'HQ'; }
$locationName = $_SESSION['location_name'] ?? 'HQ';
$userId = $_SESSION['user_id'];
$tier = defined('LICENSE_TIER') ? LICENSE_TIER : 'lite';

$userRole = strtolower($_SESSION['role'] ?? '');
$isManager = in_array($userRole, ['admin', 'manager', 'dev']);

function executeDeduction($pdo, $pId, $qty, $locId, $uId, $actionType) {
    $stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE product_id = ? AND location_id = ?");
    $stmt->execute([$pId, $locId]);
    $inv = $stmt->fetch();
    if ($inv) {
        $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?")->execute([$qty, $inv['id']]);
        $newQty = $inv['quantity'] - $qty;
    } else {
        $pdo->prepare("INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?)")->execute([$pId, $locId, -$qty]);
        $newQty = -$qty;
    }
    $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$pId, $locId, $uId, -$qty, $newQty, $actionType]);
}

function deductStock($pdo, $productId, $qty, $locId, $uId, $actionOverride = 'sale') {
    $stmt = $pdo->prepare("SELECT ingredient_product_id, quantity FROM product_recipes WHERE parent_product_id = ?");
    $stmt->execute([$productId]);
    $recipe = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($recipe) > 0) {
        foreach($recipe as $ing) {
            $deductQty = $ing['quantity'] * $qty;
            $act = ($actionOverride === 'sale') ? 'recipe_deduction' : $actionOverride;
            executeDeduction($pdo, $ing['ingredient_product_id'], $deductQty, $locId, $uId, $act);
        }
    } else {
        executeDeduction($pdo, $productId, $qty, $locId, $uId, $actionOverride);
    }
}

if (isset($_POST['mark_collected'])) {
    header('Content-Type: application/json');
    $itemId = (int)$_POST['item_id'];
    try {
        $stmt = $pdo->prepare("SELECT si.sale_id, si.fulfillment_status, c.type as cat_type FROM sale_items si LEFT JOIN products p ON si.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE si.id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if ($item) {
            if (in_array(strtolower($item['cat_type'] ?? ''), ['food', 'meal']) && in_array($tier, ['pro+', 'enterprise'])) {
                echo json_encode(['status' => 'redirect_pickup', 'msg' => 'Meals must be processed through the Kitchen screen.']);
                exit;
            }
            if ($item['fulfillment_status'] !== 'collected') {
                $pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected' WHERE id = ?")->execute([$itemId]);
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND fulfillment_status != 'collected' AND status NOT IN ('voided', 'refunded')");
                $stmtCheck->execute([$item['sale_id']]);
                echo json_encode(['status' => 'success', 'sale_id' => $item['sale_id'], 'tab_completed' => ($stmtCheck->fetchColumn() == 0), 'print_receipt' => ($stmtCheck->fetchColumn() == 0)]);
            } else { echo json_encode(['status' => 'error', 'msg' => 'Item already collected.']); }
        } else { echo json_encode(['status' => 'error', 'msg' => 'Item not found.']); }
    } catch(Exception $e) { echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]); }
    exit;
}

if (isset($_POST['void_item'])) {
    header('Content-Type: application/json');
    $itemId = (int)$_POST['item_id'];
    $mgrUser = $_POST['mgr_user'] ?? '';
    $mgrPass = $_POST['mgr_pass'] ?? '';
    $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
    $stmt->execute([$mgrUser]);
    $mgr = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$mgr || !in_array($mgr['role'], ['admin', 'manager', 'dev']) || !password_verify($mgrPass, $mgr['password_hash'])) {
        echo json_encode(['status' => 'error', 'msg' => 'Manager Authorization Failed.']);
        exit;
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT si.*, s.payment_status FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE si.id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        if ($item && $item['payment_status'] === 'pending' && !in_array($item['status'], ['voided', 'refunded'])) {
            $pdo->prepare("UPDATE sale_items SET status = 'voided', fulfillment_status = 'voided' WHERE id = ?")->execute([$itemId]);
            $saleId = $item['sale_id'];
            $pdo->prepare("UPDATE sales SET subtotal = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')), final_total = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')) WHERE id = ?")->execute([$saleId, $saleId, $saleId]); $pdo->prepare("UPDATE sales SET payment_status = 'voided' WHERE id = ? AND final_total <= 0")->execute([$saleId]);
            deductStock($pdo, $item['product_id'], -$item['quantity'], $locationId, $userId, 'void_return');
            $pdo->commit(); echo json_encode(['status' => 'success']);
        } else { throw new Exception("Cannot void this item. Table might already be paid."); }
    } catch(Exception $e) { $pdo->rollBack(); echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]); }
    exit;
}

if (isset($_POST['set_pos_location'])) {
    $_SESSION['pos_location_id'] = (int)$_POST['set_pos_location'];
    $stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?"); $stmt->execute([$_SESSION['pos_location_id']]);
    $_SESSION['location_name'] = $stmt->fetchColumn() ?: 'Unknown';
    header("Location: index.php?page=pos"); exit;
}
$sellableLocations = $pdo->query("SELECT id, name FROM locations WHERE can_sell = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT id, starting_cash FROM shifts WHERE user_id = ? AND location_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
$stmt->execute([$userId, $locationId]);
$activeShift = $stmt->fetch(PDO::FETCH_ASSOC);
$activeShiftId = $activeShift['id'] ?? null;

$expectedShiftCash = $activeShift['starting_cash'] ?? 0;
if ($activeShiftId) {
    $stmtCash = $pdo->prepare("SELECT COALESCE(SUM(final_total), 0) FROM sales WHERE shift_id = ? AND payment_method = 'Cash' AND payment_status = 'paid'"); $stmtCash->execute([$activeShiftId]); $expectedShiftCash += (float)$stmtCash->fetchColumn();
    $stmtExp = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE shift_id = ?"); $stmtExp->execute([$activeShiftId]); $expectedShiftCash -= (float)$stmtExp->fetchColumn();
}

$pendingShift = null;
if (!$activeShiftId) { $stmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND location_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1"); $stmt->execute([$userId, $locationId]); $pendingShift = $stmt->fetch(PDO::FETCH_ASSOC); }

if (isset($_POST['request_start_shift'])) { $pdo->prepare("INSERT INTO shifts (user_id, location_id, starting_cash, status) VALUES (?, ?, ?, 'pending')")->execute([$userId, $locationId, $_POST['starting_cash']]); header("Location: index.php?page=pos"); exit; }
if (isset($_POST['approve_shift_start'])) {
    $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?"); $stmt->execute([$_POST['mgr_username']]); $mgr = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($mgr && in_array($mgr['role'], ['admin', 'manager', 'dev']) && password_verify($_POST['mgr_password'], $mgr['password_hash'])) { $pdo->prepare("UPDATE shifts SET status = 'open', start_time = NOW() WHERE id = ?")->execute([$_POST['pending_shift_id']]); $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Shift started."; } 
    else { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Invalid credentials."; } header("Location: index.php?page=pos"); exit;
}

if (isset($_POST['transfer_tab_items']) && $activeShiftId) {
    try {
        $pdo->beginTransaction();
        $sourceTabId = (int)$_POST['source_tab_id'];
        $targetTabId = $_POST['target_tab_id'];
        $selectedItems = $_POST['transfer_item_ids'] ?? [];

        if (empty($selectedItems)) throw new Exception("No items selected for transfer.");

        if ($targetTabId === 'new') {
            $targetTableId = !empty($_POST['target_table_id']) ? (int)$_POST['target_table_id'] : null;
            $name = trim($_POST['new_tab_name'] ?? '');
            if (empty($name)) {
                if ($targetTableId) {
                    $stmtTbl = $pdo->prepare("SELECT table_name FROM restaurant_tables WHERE id = ?");
                    $stmtTbl->execute([$targetTableId]);
                    $name = $stmtTbl->fetchColumn() ?: 'Table ' . $targetTableId;
                } else {
                    $name = 'Transferred ' . rand(100,999);
                }
            }
            $stmt = $pdo->prepare("INSERT INTO sales (location_id, table_id, user_id, shift_id, subtotal, final_total, payment_method, payment_status, customer_name) VALUES (?, ?, ?, ?, 0, 0, 'Pending', 'pending', ?)");
            $stmt->execute([$locationId, $targetTableId, $userId, $activeShiftId, $name]);
            $targetTabId = $pdo->lastInsertId();
        } else {
            $targetTabId = (int)$targetTabId;
            if ($targetTabId === $sourceTabId) throw new Exception("Cannot transfer to the same tab.");
        }

        $inClause = implode(',', array_map('intval', $selectedItems));
        $pdo->prepare("UPDATE sale_items SET sale_id = ? WHERE id IN ($inClause) AND sale_id = ?")->execute([$targetTabId, $sourceTabId]);

        $recalc = $pdo->prepare("UPDATE sales SET subtotal = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')), final_total = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')) WHERE id = ?");
        $recalc->execute([$sourceTabId, $sourceTabId, $sourceTabId]);
        $recalc->execute([$targetTabId, $targetTabId, $targetTabId]);

        $checkEmpty = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')");
        $checkEmpty->execute([$sourceTabId]);
        if ($checkEmpty->fetchColumn() == 0) {
            $pdo->prepare("UPDATE sales SET payment_status = 'voided' WHERE id = ?")->execute([$sourceTabId]);
        }

        $pdo->commit();
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Items transferred successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = "Transfer Error: " . $e->getMessage();
    }
    header("Location: index.php?page=pos"); exit;
}

if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

if (isset($_POST['add_item']) && $activeShiftId) {
    $pid = (int)$_POST['product_id'];
    $stmt = $pdo->prepare("SELECT p.*, c.type as cat_type FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
    $stmt->execute([$pid]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($prod) {
        $price = isset($_POST['custom_price']) ? (float)$_POST['custom_price'] : (float)$prod['price'];
        $isRefund = isset($_POST['is_refund']) && $_POST['is_refund'] === '1';
        if ($isRefund) { $price = -abs($price); } 
        
        $refundSaleItemId = isset($_POST['refund_sale_item_id']) && $_POST['refund_sale_item_id'] !== 'undefined' ? (int)$_POST['refund_sale_item_id'] : 0;
        $key = $pid . '_' . md5($price) . ($isRefund ? '_refund' : '');
        $fulfillment = 'collected'; 
        if (!$isRefund && in_array(strtolower($prod['cat_type']), ['food', 'meal']) && in_array($tier, ['pro+', 'enterprise'])) { $fulfillment = 'uncollected'; }
        
        if (isset($_SESSION['cart'][$key])) { $_SESSION['cart'][$key]['qty']++; } 
        else { $_SESSION['cart'][$key] = ['product_id' => $pid, 'name' => ($isRefund ? '🔙 [RETURN] ' : '') . $prod['name'], 'price' => $price, 'qty' => 1, 'cat_type' => $prod['cat_type'], 'fulfillment' => $fulfillment, 'type' => $prod['type'], 'is_refund' => $isRefund, 'refund_sale_item_id' => $refundSaleItemId]; }
    }
    header("Location: index.php?page=pos"); exit;
}

if (isset($_POST['update_qty'])) {
    $key = $_POST['cart_key'];
    if (isset($_SESSION['cart'][$key])) {
        if ($_POST['action'] === 'inc') $_SESSION['cart'][$key]['qty']++;
        elseif ($_POST['action'] === 'dec') { $_SESSION['cart'][$key]['qty']--; if ($_SESSION['cart'][$key]['qty'] <= 0) unset($_SESSION['cart'][$key]); }
    }
    header("Location: index.php?page=pos"); exit;
}
if (isset($_POST['remove_item'])) { unset($_SESSION['cart'][$_POST['cart_key']]); header("Location: index.php?page=pos"); exit; }
if (isset($_POST['clear_cart'])) { $_SESSION['cart'] = []; unset($_SESSION['current_customer']); header("Location: index.php?page=pos"); exit; }

if (isset($_POST['toggle_fulfillment'])) {
    $key = $_POST['cart_key'];
    if (isset($_SESSION['cart'][$key]) && !($_SESSION['cart'][$key]['is_refund']??false)) {
        if (!in_array(strtolower($_SESSION['cart'][$key]['cat_type']), ['food', 'meal'])) { 
            $_SESSION['cart'][$key]['fulfillment'] = ($_SESSION['cart'][$key]['fulfillment'] === 'collected') ? 'uncollected' : 'collected'; 
        }
    }
    header("Location: index.php?page=pos"); exit;
}

if (isset($_POST['log_waste']) && $activeShiftId) {
    $mgrUser = $_POST['mgr_user'] ?? '';
    $mgrPass = $_POST['mgr_pass'] ?? '';
    $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
    $stmt->execute([$mgrUser]);
    $mgr = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($mgr && in_array($mgr['role'], ['admin', 'manager', 'dev']) && password_verify($mgrPass, $mgr['password_hash'])) {
        foreach($_SESSION['cart'] as $item) { deductStock($pdo, $item['product_id'], $item['qty'], $locationId, $userId, 'waste'); }
        $_SESSION['cart'] = []; $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Waste logged."; 
    } else {
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Manager Authorization Failed.";
    }
    header("Location: index.php?page=pos"); exit;
}

if (isset($_POST['checkout']) && $activeShiftId) {
    try {
        $pdo->beginTransaction();
        $settleTabId = (int)($_POST['settle_tab_id'] ?? 0);
        $tip = (float)($_POST['tip_amount'] ?? 0);
        $pm = $_POST['payment_method'] ?? 'Cash';
        $isSplit = $_POST['is_split'] ?? '0';
        $status = ($pm === 'Pending') ? 'pending' : 'paid';
        if ($isSplit == '1') { $pm = 'Split'; }
        $amountTendered = isset($_POST['amount_tendered']) ? (float)$_POST['amount_tendered'] : 0;
        $sm1 = $_POST['split_method_1'] ?? null;
        $sa1 = (float)($_POST['split_amount_1'] ?? 0);
        $sm2 = $_POST['split_method_2'] ?? null;
        $sa2 = (float)($_POST['split_amount_2'] ?? 0);

        // 🛡️ BLOCK CHECKOUT IF WEB ORDER IS COOKING
        if ($settleTabId > 0) {
            $stmtCheckOnline = $pdo->prepare("SELECT split_group_id FROM sales WHERE id = ?");
            $stmtCheckOnline->execute([$settleTabId]);
            $extIdCheck = $stmtCheckOnline->fetchColumn();
            if (!empty($extIdCheck)) { 
                $stmtCheckKds = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND status IN ('pending', 'cooking') AND status NOT IN ('voided', 'refunded')");
                $stmtCheckKds->execute([$settleTabId]);
                if ($stmtCheckKds->fetchColumn() > 0) {
                    throw new Exception("Cannot process checkout: The Kitchen is still preparing items for this Web Order.");
                }
            }
        }

        $checkTotal = 0;
        if ($settleTabId > 0) {
            $stmt = $pdo->prepare("SELECT SUM(price * quantity) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')"); 
            $stmt->execute([$settleTabId]); 
            $checkTotal = (float)$stmt->fetchColumn();
        } else {
            foreach($_SESSION['cart'] as $item) { $checkTotal += $item['price'] * $item['qty']; }
        }
        if (isset($_POST['apply_discount']) && $_POST['apply_discount'] == '1') { $checkTotal *= 0.9; }
        $checkTotal += $tip;

        if ($checkTotal < 0) {
            $mgrUser = $_POST['mgr_username'] ?? '';
            $mgrPass = $_POST['mgr_password'] ?? '';
            $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
            $stmt->execute([$mgrUser]);
            $mgr = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$mgr || !in_array($mgr['role'], ['admin', 'manager', 'dev']) || !password_verify($mgrPass, $mgr['password_hash'])) {
                throw new Exception("Manager Authorization required for Cash Refunds.");
            }
        }

        if ($settleTabId > 0) {
            // NEW: Use split_group_id (External ID) to definitively identify Web Orders regardless of Payment Method
            $stmtOrig = $pdo->prepare("SELECT split_group_id FROM sales WHERE id = ?");
            $stmtOrig->execute([$settleTabId]);
            $extOrderId = $stmtOrig->fetchColumn();
            $wasOnlineOrder = !empty($extOrderId);

            $stmt = $pdo->prepare("SELECT SUM(price * quantity) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')"); $stmt->execute([$settleTabId]); $total = (float)$stmt->fetchColumn();
            if (isset($_POST['apply_discount']) && $_POST['apply_discount'] == '1') { $total *= 0.9; }
            $finalTotal = $total + $tip;
            $stmtPT = $pdo->prepare("SELECT amount_tendered FROM sales WHERE id = ?"); 
            $stmtPT->execute([$settleTabId]); 
            $prevTendered = (float)$stmtPT->fetchColumn(); 
            $cumulativeTendered = $prevTendered + $amountTendered; 
            
            $status = ($cumulativeTendered >= $finalTotal) ? 'paid' : 'pending'; 
            $saleStatus = ($status === 'paid') ? 'completed' : 'pending';
            $changeDue = ($cumulativeTendered > $finalTotal) ? ($cumulativeTendered - $finalTotal) : 0;
            
            $pdo->prepare("UPDATE sales SET shift_id = ?, status = ?, subtotal = ?, tip_amount = ?, final_total = ?, payment_method = ?, payment_status = ?, amount_tendered = ?, change_due = ?, split_method_1 = ?, split_amount_1 = ?, split_method_2 = ?, split_amount_2 = ? WHERE id = ?")
                ->execute([$activeShiftId, $saleStatus, $total, $tip, $finalTotal, $pm, $status, $cumulativeTendered, $changeDue, $sm1, $sa1, $sm2, $sa2, $settleTabId]);
            
            if ($saleStatus === 'completed') {
                $pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected' WHERE sale_id = ?")->execute([$settleTabId]);

                // ==========================================
                // 🔔 WEBHOOK: ORDER COMPLETED & HANDED TO DRIVER
                // ==========================================
                if ($wasOnlineOrder && $extOrderId) {
                    
                    // ⚠️ IMPORTANT: REPLACE THIS WITH YOUR REAL WEBHOOK URL ⚠️
                    $webhookUrl = "https://webhook.site/b97db7ed-2317-469d-952c-0e9badfd7a03"; 
                    
                    $payload = json_encode([
                        "order_id" => $extOrderId,
                        "status" => "completed", 
                        "store_id" => "MAIN_COUNTER_001",
                        "timestamp" => date('c')
                    ]);

                    $ch = curl_init($webhookUrl);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        'Content-Type: application/json',
                        'Content-Length: ' . strlen($payload)
                    ]);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Prevent SSL block
                    curl_setopt($ch, CURLOPT_TIMEOUT, 3); 
                    curl_exec($ch);
                    curl_close($ch);
                }
                // ==========================================
            }

            if ($status === 'paid') { $_SESSION['last_sale_id'] = $settleTabId; } $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Tab settled successfully.";
        } else {
            $total = 0; foreach($_SESSION['cart'] as $item) { $total += $item['price'] * $item['qty']; }
            if (isset($_POST['apply_discount']) && $_POST['apply_discount'] == '1') { $total *= 0.9; }
            $finalTotal = $total + $tip;
            $customerName = $_POST['customer_name'] ?? 'Walk-in';

            $status = ($amountTendered >= $finalTotal && $pm !== 'Pending') ? 'paid' : 'pending';
            $changeDue = ($amountTendered > $finalTotal) ? ($amountTendered - $finalTotal) : 0;
            $stmt = $pdo->prepare("INSERT INTO sales (location_id, user_id, shift_id, subtotal, tip_amount, final_total, payment_method, payment_status, customer_name, amount_tendered, change_due, split_method_1, split_amount_1, split_method_2, split_amount_2) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$locationId, $userId, $activeShiftId, $total, $tip, $finalTotal, $pm, $status, $customerName, $amountTendered, $changeDue, $sm1, $sa1, $sm2, $sa2]);
            $saleId = $pdo->lastInsertId();

            foreach($_SESSION['cart'] as $item) {
                $fulfillment = $item['fulfillment'] ?? 'collected';
                $itemStatus = ($fulfillment === 'uncollected') ? 'pending' : 'ready';
                $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, fulfillment_status, status) VALUES (?, ?, ?, ?, ?, ?)")->execute([$saleId, $item['product_id'], $item['qty'], $item['price'], $fulfillment, $itemStatus]);
                
                $isRefund = $item['is_refund'] ?? false;
                $dbQty = $isRefund ? -$item['qty'] : $item['qty']; 
                $stockAction = $isRefund ? 'refund' : 'sale';
                deductStock($pdo, $item['product_id'], $dbQty, $locationId, $userId, $stockAction); 
                
                if ($isRefund && !empty($item['refund_sale_item_id'])) {
                    $stmt = $pdo->prepare("SELECT sale_id FROM sale_items WHERE id = ?");
                    $stmt->execute([$item['refund_sale_item_id']]);
                    $origSaleId = $stmt->fetchColumn();
                    if ($origSaleId) {
                        $pdo->prepare("UPDATE sale_items SET status = 'refunded', fulfillment_status = 'refunded' WHERE id = ?")->execute([$item['refund_sale_item_id']]);
                        $stmt2 = $pdo->prepare("SELECT payment_status FROM sales WHERE id = ?");
                        $stmt2->execute([$origSaleId]);
                        if ($stmt2->fetchColumn() === 'pending') {
                            $pdo->prepare("UPDATE sales SET subtotal = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')), final_total = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')) WHERE id = ?")->execute([$origSaleId, $origSaleId, $origSaleId]);
                        }
                    }
                }
            }
            $_SESSION['cart'] = []; $_SESSION['current_customer'] = '';
            if ($status === 'paid') { $_SESSION['last_sale_id'] = $saleId; }
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Transaction complete.";
        }
        $pdo->commit();
    } catch(Exception $e) { $pdo->rollBack(); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage(); }
    header("Location: index.php?page=pos"); exit;
}

if (isset($_POST['finalize_split']) && $activeShiftId) {
    $splitData = json_decode($_POST['split_data'], true);
    if ($splitData) {
        try {
            $pdo->beginTransaction();
            foreach($splitData as $guest) {
                if ($guest['total'] <= 0) continue;
                $status = ($guest['method'] === 'Pending') ? 'pending' : 'paid';
                $stmt = $pdo->prepare("INSERT INTO sales (location_id, user_id, shift_id, subtotal, tip_amount, final_total, payment_method, payment_status, customer_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$locationId, $userId, $activeShiftId, $guest['total'], $guest['tip'], $guest['total'] + $guest['tip'], $guest['method'], $status, $guest['name']]);
                $saleId = $pdo->lastInsertId();

                if ($_POST['split_type'] === 'item') {
                    foreach($guest['items'] as $item) {
                        $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, fulfillment_status, status) VALUES (?, ?, ?, ?, 'collected', 'ready')")->execute([$saleId, $item['id'], $item['qty'], $item['price']]);
                        deductStock($pdo, $item['id'], $item['qty'], $locationId, $userId);
                    }
                } else {
                    $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, fulfillment_status, status) VALUES (?, 0, 1, ?, 'collected', 'ready')")->execute([$saleId, $guest['total']]);
                }
            }
            if ($_POST['split_type'] === 'even') { foreach($_SESSION['cart'] as $item) { deductStock($pdo, $item['product_id'], $item['qty'], $locationId, $userId); } }
            $pdo->commit(); $_SESSION['cart'] = []; $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Split bills processed.";
        } catch (Exception $e) { $pdo->rollBack(); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage(); }
    }
    header("Location: index.php?page=pos"); exit;
}

if (isset($_POST['add_to_tab_action']) && $activeShiftId) {
    try {
        $pdo->beginTransaction();
        $targetId = $_POST['target_tab_id']; $tableId = isset($_POST['target_table_id']) && $_POST['target_table_id'] !== '' ? (int)$_POST['target_table_id'] : null;

        if ($targetId === 'new') {
            $name = $_POST['tab_customer_name'] ?: 'Tab ' . rand(100,999);
            $stmt = $pdo->prepare("INSERT INTO sales (location_id, table_id, user_id, shift_id, subtotal, final_total, payment_method, payment_status, customer_name) VALUES (?, ?, ?, ?, 0, 0, 'Pending', 'pending', ?)"); $stmt->execute([$locationId, $tableId, $userId, $activeShiftId, $name]); $targetId = $pdo->lastInsertId();
        } else { if ($tableId) { $pdo->prepare("UPDATE sales SET table_id = ? WHERE id = ?")->execute([$tableId, $targetId]); } }
        
        $new_item_ids = [];
        foreach($_SESSION['cart'] as $item) {
            $fulfillment = $item['fulfillment'] ?? 'collected'; $itemStatus = ($fulfillment === 'uncollected') ? 'pending' : 'ready';
            $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, fulfillment_status, status) VALUES (?, ?, ?, ?, ?, ?)")->execute([$targetId, $item['product_id'], $item['qty'], $item['price'], $fulfillment, $itemStatus]);
            $new_item_ids[] = $pdo->lastInsertId();
            $isRefund = $item['is_refund'] ?? false; $dbQty = $isRefund ? -$item['qty'] : $item['qty']; 
            deductStock($pdo, $item['product_id'], $dbQty, $locationId, $userId, $isRefund ? 'refund' : 'sale');
        }
        $pdo->prepare("UPDATE sales SET subtotal = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')), final_total = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')) WHERE id = ?")->execute([$targetId, $targetId, $targetId]);
        $pdo->commit(); $_SESSION['cart'] = []; $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Added to tab/table."; $_SESSION['last_bill_id'] = $targetId; $_SESSION['last_added_item_ids'] = implode(',', $new_item_ids);
    } catch(Exception $e) { $pdo->rollBack(); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage(); }
    header("Location: index.php?page=pos"); exit;
}

if (isset($_POST['log_expense']) && $activeShiftId) {
    $amt = (float)$_POST['expense_amount']; $reason = trim($_POST['expense_reason']); $mgrUsername = $_POST['mgr_username'] ?? ''; $mgrPassword = $_POST['mgr_password'] ?? '';
    if ($amt > 0 && !empty($reason)) {
        $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?"); $stmt->execute([$mgrUsername]); $mgr = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($mgr && in_array($mgr['role'], ['admin', 'manager', 'dev']) && password_verify($mgrPassword, $mgr['password_hash'])) {
            try {
                $pdo->prepare("INSERT INTO expenses (location_id, user_id, shift_id, amount, reason) VALUES (?, ?, ?, ?, ?)")->execute([$locationId, $userId, $activeShiftId, $amt, $reason . ' (Auth: ' . $mgrUsername . ')']);
                $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Payout logged.";
            } catch(Exception $e) { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage(); }
        } else { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Invalid manager credentials."; }
    }
    header("Location: index.php?page=pos"); exit;
}

$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
$sysSettings = [];
while ($sRow = $settingsStmt->fetch(PDO::FETCH_ASSOC)) { $sysSettings[$sRow['setting_key']] = $sRow['setting_value']; }

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$stmt = $pdo->prepare("SELECT p.*, COALESCE(i.quantity, 0) as stock_qty, (SELECT COUNT(*) FROM product_recipes WHERE parent_product_id = p.id) as is_recipe FROM products p LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = ? WHERE p.type = 'item' AND p.is_active = 1"); $stmt->execute([$locationId]); $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
$services = $pdo->query("SELECT * FROM products WHERE type = 'service' AND is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
$balance = 0; foreach($_SESSION['cart'] as $i) { $balance += $i['price'] * $i['qty']; }

$tabQuery = "SELECT DISTINCT s.* FROM sales s JOIN sale_items si ON s.id = si.sale_id WHERE s.location_id = ? AND si.status NOT IN ('voided', 'refunded') AND (s.payment_status = 'pending' OR si.fulfillment_status = 'uncollected')";
$tabParams = [$locationId];

if (!$isManager) {
    $tabQuery .= " AND s.user_id = ?";
    $tabParams[] = $userId;
}
$tabQuery .= " ORDER BY s.id DESC";

$stmt = $pdo->prepare($tabQuery);
$stmt->execute($tabParams);
$openTabs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$tabItems = []; 
foreach($openTabs as $t) { 
    $stmt = $pdo->prepare("SELECT si.*, COALESCE(p.name, 'Custom Item') as name, c.type as cat_type FROM sale_items si LEFT JOIN products p ON si.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE si.sale_id = ? AND si.status NOT IN ('voided', 'refunded')"); 
    $stmt->execute([$t['id']]); 
    $tabItems[$t['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC); 
}

$stmt = $pdo->prepare("SELECT s.table_id, s.id as sale_id, s.final_total, s.user_id, u.username FROM sales s LEFT JOIN users u ON s.user_id = u.id WHERE s.location_id = ? AND s.payment_status = 'pending' AND s.table_id IS NOT NULL");
$stmt->execute([$locationId]);
$occupiedTablesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
$occupiedTables = [];
foreach($occupiedTablesData as $ot) {
    $occupiedTables[$ot['table_id']] = $ot;
}

if ($tier === 'enterprise') {
    $onlineTabsStmt = $pdo->prepare("SELECT id, total_amount, customer_name, split_group_id as external_id, created_at FROM sales WHERE status = 'pending' AND payment_method = 'Online' ORDER BY created_at DESC");
    $onlineTabsStmt->execute();
    $onlineTabs = $onlineTabsStmt->fetchAll(PDO::FETCH_ASSOC);

    $onlineTabItems = [];
    foreach($onlineTabs as $ot) {
        $stmt = $pdo->prepare("SELECT si.*, COALESCE(p.name, 'Custom Item') as name, c.type as cat_type FROM sale_items si LEFT JOIN products p ON si.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE si.sale_id = ? AND si.status NOT IN ('voided', 'refunded')"); 
        $stmt->execute([$ot['id']]); 
        $onlineTabItems[$ot['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC); 
    }
} else {
    $onlineTabs = [];
    $onlineTabItems = [];
}

$restaurantTables = []; try { $stmt = $pdo->prepare("SELECT * FROM restaurant_tables WHERE location_id = ? ORDER BY zone_name ASC, table_name ASC"); $stmt->execute([$locationId]); $rawTables = $stmt->fetchAll(PDO::FETCH_ASSOC); foreach($rawTables as $t) { $restaurantTables[$t['zone_name']][] = $t; } } catch (Exception $e) { }
?>