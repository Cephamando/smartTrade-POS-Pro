<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$locationId = $_SESSION['pos_location_id'] ?? $_SESSION['location_id'] ?? 0;
$locationName = $_SESSION['location_name'] ?? 'HQ';
$userId = $_SESSION['user_id'];
$tier = defined('LICENSE_TIER') ? LICENSE_TIER : 'lite';

// --- ⚙️ HELPER: INVENTORY DEDUCTION ENGINE ⚙️ ---
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

// --- 📡 AJAX HANDLERS (KITCHEN SECURITY) ---
if (isset($_POST['mark_collected'])) {
    header('Content-Type: application/json');
    $itemId = (int)$_POST['item_id'];
    try {
        $stmt = $pdo->prepare("SELECT si.sale_id, si.fulfillment_status, c.type as cat_type FROM sale_items si LEFT JOIN products p ON si.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE si.id = ?");
        $stmt->execute([$itemId]);
        $item = $stmt->fetch();
        
        if ($item) {
            if (in_array(strtolower($item['cat_type'] ?? ''), ['food', 'meal']) && in_array($tier, ['pro', 'hospitality'])) {
                echo json_encode(['status' => 'redirect_pickup', 'msg' => 'Meals must be processed through the Kitchen/Pickup screen.']);
                exit;
            }

            if ($item['fulfillment_status'] !== 'collected') {
                $pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected' WHERE id = ?")->execute([$itemId]);
                $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND fulfillment_status != 'collected'");
                $stmtCheck->execute([$item['sale_id']]);
                $uncollectedCount = $stmtCheck->fetchColumn();
                echo json_encode(['status' => 'success', 'sale_id' => $item['sale_id'], 'tab_completed' => ($uncollectedCount == 0), 'print_receipt' => ($uncollectedCount == 0)]);
            } else {
                echo json_encode(['status' => 'error', 'msg' => 'Item already collected.']);
            }
        } else {
            echo json_encode(['status' => 'error', 'msg' => 'Item not found.']);
        }
    } catch(Exception $e) { echo json_encode(['status' => 'error', 'msg' => $e->getMessage()]); }
    exit;
}

// --- 📍 LOCATION & SHIFT CHECK ---
if (isset($_POST['set_pos_location'])) {
    $newLocId = (int)$_POST['set_pos_location'];
    $_SESSION['pos_location_id'] = $newLocId;
    $stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
    $stmt->execute([$newLocId]);
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
    $stmtCash = $pdo->prepare("SELECT COALESCE(SUM(final_total), 0) FROM sales WHERE shift_id = ? AND payment_method = 'Cash' AND payment_status = 'paid'");
    $stmtCash->execute([$activeShiftId]);
    $expectedShiftCash += (float)$stmtCash->fetchColumn();

    $stmtExp = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE shift_id = ?");
    $stmtExp->execute([$activeShiftId]);
    $expectedShiftCash -= (float)$stmtExp->fetchColumn();
}

$pendingShift = null;
if (!$activeShiftId) {
    $stmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND location_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId, $locationId]);
    $pendingShift = $stmt->fetch(PDO::FETCH_ASSOC);
}

if (isset($_POST['request_start_shift'])) {
    $pdo->prepare("INSERT INTO shifts (user_id, location_id, starting_cash, status) VALUES (?, ?, ?, 'pending')")->execute([$userId, $locationId, $_POST['starting_cash']]);
    header("Location: index.php?page=pos"); exit;
}
if (isset($_POST['approve_shift_start'])) {
    $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
    $stmt->execute([$_POST['mgr_username']]);
    $mgr = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($mgr && in_array($mgr['role'], ['admin', 'manager', 'dev']) && password_verify($_POST['mgr_password'], $mgr['password_hash'])) {
        $pdo->prepare("UPDATE shifts SET status = 'open', start_time = NOW() WHERE id = ?")->execute([$_POST['pending_shift_id']]);
        $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Shift started.";
    } else { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Invalid credentials."; }
    header("Location: index.php?page=pos"); exit;
}

// --- 🛒 CART MANAGEMENT ---
if (!isset($_SESSION['cart'])) { $_SESSION['cart'] = []; }

if (isset($_POST['add_item']) && $activeShiftId) {
    $pid = (int)$_POST['product_id'];
    $stmt = $pdo->prepare("SELECT p.*, c.type as cat_type FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?");
    $stmt->execute([$pid]);
    $prod = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($prod) {
        $price = isset($_POST['custom_price']) ? (float)$_POST['custom_price'] : (float)$prod['price'];
        $key = $pid . '_' . md5($price);
        $fulfillment = 'collected';
        if (in_array(strtolower($prod['cat_type']), ['food', 'meal']) && in_array($tier, ['pro', 'hospitality'])) { $fulfillment = 'uncollected'; }
        if (isset($_SESSION['cart'][$key])) { $_SESSION['cart'][$key]['qty']++; } 
        else { $_SESSION['cart'][$key] = ['product_id' => $pid, 'name' => $prod['name'], 'price' => $price, 'qty' => 1, 'cat_type' => $prod['cat_type'], 'fulfillment' => $fulfillment, 'type' => $prod['type']]; }
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
    if (isset($_SESSION['cart'][$key])) {
        if (!in_array(strtolower($_SESSION['cart'][$key]['cat_type']), ['food', 'meal'])) { 
            $_SESSION['cart'][$key]['fulfillment'] = ($_SESSION['cart'][$key]['fulfillment'] === 'collected') ? 'uncollected' : 'collected'; 
        }
    }
    header("Location: index.php?page=pos"); exit;
}

if (isset($_POST['log_waste']) && $activeShiftId) {
    foreach($_SESSION['cart'] as $item) { deductStock($pdo, $item['product_id'], $item['qty'], $locationId, $userId, 'waste'); }
    $_SESSION['cart'] = []; $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Waste logged.";
    header("Location: index.php?page=pos"); exit;
}

// --- 💳 MASTER CHECKOUT LOGIC (HANDLES BOTH CART AND TABS) ---
if (isset($_POST['checkout']) && $activeShiftId) {
    try {
        $pdo->beginTransaction();
        
        $settleTabId = (int)($_POST['settle_tab_id'] ?? 0);
        $tip = (float)($_POST['tip_amount'] ?? 0);
        $pm = $_POST['payment_method'] ?? 'Cash';
        $isSplit = $_POST['is_split'] ?? '0';
        $status = ($pm === 'Pending') ? 'pending' : 'paid';
        
        if ($isSplit == '1') { $pm = 'Split'; }

        if ($settleTabId > 0) {
            // SCENARIO 1: SETTLING AN EXISTING TAB/TABLE
            $stmt = $pdo->prepare("SELECT SUM(price * quantity) FROM sale_items WHERE sale_id = ?");
            $stmt->execute([$settleTabId]);
            $total = (float)$stmt->fetchColumn();
            
            if (isset($_POST['apply_discount']) && $_POST['apply_discount'] == '1') { $total *= 0.9; }
            $finalTotal = $total + $tip;
            
            $pdo->prepare("UPDATE sales SET subtotal = ?, tip_amount = ?, final_total = ?, payment_method = ?, payment_status = ? WHERE id = ?")
                ->execute([$total, $tip, $finalTotal, $pm, $status, $settleTabId]);
            
            if ($status === 'paid') { $_SESSION['last_sale_id'] = $settleTabId; }
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Tab settled successfully.";

        } else {
            // SCENARIO 2: NORMAL CART CHECKOUT
            $total = 0; foreach($_SESSION['cart'] as $item) { $total += $item['price'] * $item['qty']; }
            if (isset($_POST['apply_discount']) && $_POST['apply_discount'] == '1') { $total *= 0.9; }
            $finalTotal = $total + $tip;
            $customerName = $_POST['customer_name'] ?? 'Walk-in';

            $stmt = $pdo->prepare("INSERT INTO sales (location_id, user_id, shift_id, subtotal, tip_amount, final_total, payment_method, payment_status, customer_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$locationId, $userId, $activeShiftId, $total, $tip, $finalTotal, $pm, $status, $customerName]);
            $saleId = $pdo->lastInsertId();

            foreach($_SESSION['cart'] as $item) {
                $fulfillment = $item['fulfillment'] ?? 'collected';
                $itemStatus = ($fulfillment === 'uncollected') ? 'pending' : 'ready';
                $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, fulfillment_status, status) VALUES (?, ?, ?, ?, ?, ?)")->execute([$saleId, $item['product_id'], $item['qty'], $item['price'], $fulfillment, $itemStatus]);
                
                deductStock($pdo, $item['product_id'], $item['qty'], $locationId, $userId); 
            }

            $_SESSION['cart'] = []; $_SESSION['current_customer'] = '';
            if ($status === 'paid') { $_SESSION['last_sale_id'] = $saleId; }
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Transaction complete.";
        }

        $pdo->commit();
    } catch(Exception $e) { 
        $pdo->rollBack(); 
        $_SESSION['swal_type'] = 'error'; 
        $_SESSION['swal_msg'] = "Error: " . $e->getMessage(); 
    }
    header("Location: index.php?page=pos"); exit;
}

// --- 🧾 SPLIT BILL LOGIC (CART ONLY) ---
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
            if ($_POST['split_type'] === 'even') {
                foreach($_SESSION['cart'] as $item) { deductStock($pdo, $item['product_id'], $item['qty'], $locationId, $userId); }
            }
            $pdo->commit();
            $_SESSION['cart'] = []; $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Split bills processed.";
        } catch (Exception $e) { $pdo->rollBack(); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage(); }
    }
    header("Location: index.php?page=pos"); exit;
}

// --- 🍻 ADD TO TAB / TABLE LOGIC ---
if (isset($_POST['add_to_tab_action']) && $activeShiftId) {
    try {
        $pdo->beginTransaction();
        $targetId = $_POST['target_tab_id'];
        
        $tableId = isset($_POST['target_table_id']) && $_POST['target_table_id'] !== '' ? (int)$_POST['target_table_id'] : null;

        if ($targetId === 'new') {
            $name = $_POST['tab_customer_name'] ?: 'Tab ' . rand(100,999);
            $stmt = $pdo->prepare("INSERT INTO sales (location_id, table_id, user_id, shift_id, subtotal, final_total, payment_method, payment_status, customer_name) VALUES (?, ?, ?, ?, 0, 0, 'Pending', 'pending', ?)");
            $stmt->execute([$locationId, $tableId, $userId, $activeShiftId, $name]);
            $targetId = $pdo->lastInsertId();
        } else {
            if ($tableId) {
                $pdo->prepare("UPDATE sales SET table_id = ? WHERE id = ?")->execute([$tableId, $targetId]);
            }
        }
        
        foreach($_SESSION['cart'] as $item) {
            $fulfillment = $item['fulfillment'] ?? 'collected';
            $itemStatus = ($fulfillment === 'uncollected') ? 'pending' : 'ready';
            $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, fulfillment_status, status) VALUES (?, ?, ?, ?, ?, ?)")->execute([$targetId, $item['product_id'], $item['qty'], $item['price'], $fulfillment, $itemStatus]);
            deductStock($pdo, $item['product_id'], $item['qty'], $locationId, $userId);
        }
        $pdo->prepare("UPDATE sales SET subtotal = (SELECT SUM(price*quantity) FROM sale_items WHERE sale_id = ?), final_total = (SELECT SUM(price*quantity) FROM sale_items WHERE sale_id = ?) WHERE id = ?")->execute([$targetId, $targetId, $targetId]);
        $pdo->commit();
        $_SESSION['cart'] = []; $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Added to tab/table.";
    } catch(Exception $e) { $pdo->rollBack(); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage(); }
    header("Location: index.php?page=pos"); exit;
}

// --- 💸 PETTY CASH / PAYOUT LOGIC ---
if (isset($_POST['log_expense']) && $activeShiftId) {
    $amt = (float)$_POST['expense_amount'];
    $reason = trim($_POST['expense_reason']);
    $mgrUsername = $_POST['mgr_username'] ?? '';
    $mgrPassword = $_POST['mgr_password'] ?? '';

    if ($amt > 0 && !empty($reason)) {
        $stmt = $pdo->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$mgrUsername]);
        $mgr = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($mgr && in_array($mgr['role'], ['admin', 'manager', 'dev']) && password_verify($mgrPassword, $mgr['password_hash'])) {
            try {
                $auditReason = $reason . ' (Auth: ' . $mgrUsername . ')';
                $pdo->prepare("INSERT INTO expenses (location_id, user_id, shift_id, amount, reason) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$locationId, $userId, $activeShiftId, $amt, $auditReason]);
                $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Payout authorized and logged.";
            } catch(Exception $e) {
                $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
            }
        } else {
            $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Authorization failed! Invalid manager credentials.";
        }
    }
    header("Location: index.php?page=pos"); exit;
}

// --- 🖥️ UI DATA FETCHING ---
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT p.*, COALESCE(i.quantity, 0) as stock_qty,
           (SELECT COUNT(*) FROM product_recipes WHERE parent_product_id = p.id) as is_recipe
    FROM products p 
    LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = ? 
    WHERE p.type = 'item' AND p.is_active = 1
");
$stmt->execute([$locationId]);
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$services = $pdo->query("SELECT * FROM products WHERE type = 'service' AND is_active = 1")->fetchAll(PDO::FETCH_ASSOC);

$balance = 0; foreach($_SESSION['cart'] as $i) { $balance += $i['price'] * $i['qty']; }

$openTabs = $pdo->query("SELECT * FROM sales WHERE payment_status = 'pending' AND location_id = $locationId")->fetchAll(PDO::FETCH_ASSOC);
$tabItems = [];
foreach($openTabs as $t) {
    $stmt = $pdo->prepare("SELECT si.*, COALESCE(p.name, 'Custom Item') as name, c.type as cat_type FROM sale_items si LEFT JOIN products p ON si.product_id = p.id LEFT JOIN categories c ON p.category_id = c.id WHERE si.sale_id = ?");
    $stmt->execute([$t['id']]);
    $tabItems[$t['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$restaurantTables = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM restaurant_tables WHERE location_id = ? ORDER BY zone_name ASC, table_name ASC");
    $stmt->execute([$locationId]);
    $rawTables = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($rawTables as $t) {
        $restaurantTables[$t['zone_name']][] = $t;
    }
} catch (Exception $e) { }
?>
