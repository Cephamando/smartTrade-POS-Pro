<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];

// --- 1. HANDLE ITEM COLLECTION ---
if (isset($_POST['mark_collected'])) {
    $itemId = $_POST['item_id'];
    $stmt = $pdo->prepare("SELECT sale_id, product_id, quantity, price_at_sale FROM sale_items WHERE id = ?");
    $stmt->execute([$itemId]);
    $itemRow = $stmt->fetch();

    if ($itemRow) {
        $pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected' WHERE id = ?")->execute([$itemId]);
        
        $saleId = $itemRow['sale_id'];
        // Check if Tab is Fully Complete
        $saleState = $pdo->query("SELECT payment_status FROM sales WHERE id = $saleId")->fetch();
        $pendingCount = $pdo->query("SELECT COUNT(*) FROM sale_items WHERE sale_id = $saleId AND fulfillment_status = 'uncollected'")->fetchColumn();
        $isTabComplete = ($saleState['payment_status'] === 'paid' && $pendingCount == 0);

        echo json_encode([
            'status' => 'success', 
            'tab_completed' => $isTabComplete,
            'sale_id' => $saleId,
            'item' => [
                'name' => $pdo->query("SELECT name FROM products WHERE id = " . $itemRow['product_id'])->fetchColumn(),
                'qty' => $itemRow['quantity'],
                'total' => number_format($itemRow['quantity'] * $itemRow['price_at_sale'], 2)
            ]
        ]);
        exit;
    }
    echo json_encode(['status' => 'error']); exit;
}

// --- 2. ADD TO TAB (Full Cart) ---
if (isset($_POST['add_to_tab_action']) && !empty($_SESSION['cart'])) {
    $targetTabId = $_POST['target_tab_id'] ?? 'new'; 
    $customerName = $_POST['tab_customer_name'] ?? 'Walk-in';
    $locId = $_SESSION['pos_location_id'] ?? 0;
    
    // Get active shift ID safely
    $shiftStmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $shiftStmt->execute([$userId]);
    $shift = $shiftStmt->fetch();
    $activeShiftId = $shift['id'] ?? 0;

    $pdo->beginTransaction();
    try {
        $saleId = 0;
        if ($targetTabId === 'new') {
            $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, total_amount, final_total, payment_method, payment_status, customer_name, amount_tendered, change_due, created_at) VALUES (?, ?, ?, 0, 0, 'Pending', 'pending', ?, 0, 0, NOW())")
                ->execute([$userId, $locId, $activeShiftId, $customerName]);
            $saleId = $pdo->lastInsertId();
        } else {
            $saleId = $targetTabId;
        }

        foreach ($_SESSION['cart'] as $pid => $item) {
            $fulfill = $item['fulfillment'] ?? 'collected';
            $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status, fulfillment_status) VALUES (?, ?, ?, ?, 'pending', ?)")
                ->execute([$saleId, $pid, $item['qty'], $item['price'], $fulfill]);

            if (($item['type'] ?? 'item') !== 'service') {
                $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$item['qty'], $pid, $locId]);
                $nq = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locId")->fetchColumn();
                $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id, created_at) VALUES (?, ?, ?, ?, ?, 'sale', ?, NOW())")->execute([$pid, $locId, $userId, -$item['qty'], $nq, $saleId]);
            }
        }

        $newTotal = $pdo->query("SELECT SUM(quantity * price_at_sale) FROM sale_items WHERE sale_id = $saleId")->fetchColumn();
        $pdo->prepare("UPDATE sales SET total_amount = ?, final_total = ? WHERE id = ?")->execute([$newTotal, $newTotal, $saleId]);

        $pdo->commit();
        unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['tab_paid']); 
        $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Items added to Tab #$saleId";

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
    }
    header("Location: index.php?page=pos"); exit;
}

// --- 3. FINALIZE SPLIT (THE FIX FOR SPLITTING ITEMS) ---
if (isset($_POST['finalize_split'])) {
    $splitData = json_decode($_POST['split_data'], true);
    $locId = $_SESSION['pos_location_id'] ?? 0;
    
    // Get active shift
    $shiftStmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $shiftStmt->execute([$userId]);
    $shift = $shiftStmt->fetch();
    $activeShiftId = $shift['id'] ?? 0;

    if (!empty($splitData)) {
        $pdo->beginTransaction();
        try {
            foreach ($splitData as $guest) {
                $total = $guest['total'];
                $method = $guest['method']; // Cash, Card, Pending
                // If Pending, it becomes a TAB. If Cash/Card, it's PAID.
                $status = ($method === 'Pending') ? 'pending' : 'paid';
                $customer = $guest['name']; 
                $tendered = ($status === 'paid') ? $total : 0;

                // Create Sale for this split group
                $stmt = $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, total_amount, final_total, payment_method, payment_status, customer_name, amount_tendered, change_due, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, NOW())");
                $stmt->execute([$userId, $locId, $activeShiftId, $total, $total, $method, $status, $customer, $tendered]);
                $saleId = $pdo->lastInsertId();

                // Add Items
                foreach ($guest['items'] as $item) {
                    // Item comes as {id: product_id, qty: count, price: price}
                    $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status, fulfillment_status) VALUES (?, ?, ?, ?, 'pending', 'collected')")
                        ->execute([$saleId, $item['id'], $item['qty'], $item['price']]);

                    // Deduct Inventory
                    if (($item['type'] ?? 'item') !== 'service') {
                        $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$item['qty'], $item['id'], $locId]);
                        // Log (optional, skipping for brevity but recommended)
                    }
                }
            }
            $pdo->commit();
            unset($_SESSION['cart']); 
            $_SESSION['swal_type'] = 'success'; 
            $_SESSION['swal_msg'] = "Split Transaction Completed Successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['swal_type'] = 'error'; 
            $_SESSION['swal_msg'] = "Split Error: " . $e->getMessage();
        }
    }
    header("Location: index.php?page=pos"); exit;
}

// --- STANDARD POS LOGIC ---
$activeShiftId = null; 
$pendingShift = null; 
$expectedShiftCash = 0.00; 
$locationName = 'Unknown Station'; 
$locationId = 0; 

// A. Check for Active Shift (Highest Priority)
$shiftStmt = $pdo->prepare("SELECT s.*, u.full_name as cashier_name FROM shifts s JOIN users u ON s.user_id = u.id WHERE s.user_id = ? AND s.status IN ('open', 'pending_approval') ORDER BY s.id DESC LIMIT 1"); 
$shiftStmt->execute([$userId]);
$currentShift = $shiftStmt->fetch();

if ($currentShift) {
    $locationId = $currentShift['location_id'];
    $_SESSION['pos_location_id'] = $locationId; 
    
    $activeShiftId = ($currentShift['status'] === 'open') ? $currentShift['id'] : null;
    $pendingShift  = ($currentShift['status'] === 'pending_approval') ? $currentShift : null;
    
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
} elseif (isset($_SESSION['pos_location_id']) && $_SESSION['pos_location_id'] > 0) {
    $locationId = $_SESSION['pos_location_id'];
}

if ($locationId > 0) {
    $locationName = $pdo->query("SELECT name FROM locations WHERE id = $locationId")->fetchColumn();
}

$sellableLocations = $pdo->query("SELECT * FROM locations WHERE can_sell = 1 ORDER BY name ASC")->fetchAll();

// HANDLERS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_start_shift'])) {
    if ($locationId > 0) { 
        $pdo->prepare("INSERT INTO shifts (user_id, location_id, start_time, starting_cash, status) VALUES (?, ?, NOW(), ?, 'pending_approval')")->execute([$userId, $locationId, floatval($_POST['starting_cash'] ?? 0)]); 
        header("Location: index.php?page=pos"); exit; 
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_pos_location'])) { 
    $_SESSION['pos_location_id'] = $_POST['set_pos_location']; 
    header("Location: index.php?page=pos"); exit; 
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['approve_shift_start'])) {
    $mgr = $pdo->prepare("SELECT * FROM users WHERE username = ?"); $mgr->execute([$_POST['mgr_username'] ?? '']); $m = $mgr->fetch();
    if ($m && password_verify($_POST['mgr_password'] ?? '', $m['password_hash']) && in_array($m['role'], ['admin','manager','dev'])) { 
        $pdo->prepare("UPDATE shifts SET status='open' WHERE id=?")->execute([$_POST['pending_shift_id']]); 
    } 
    header("Location: index.php?page=pos"); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeShiftId) {
    if (isset($_POST['add_item'])) {
        $pid = $_POST['product_id']; $p = $pdo->query("SELECT * FROM products WHERE id = $pid")->fetch();
        $price = ($p['is_open_price'] && isset($_POST['custom_price'])) ? $_POST['custom_price'] : $p['price'];
        if(isset($_SESSION['cart'][$pid])) { $_SESSION['cart'][$pid]['qty']++; } else { $_SESSION['cart'][$pid] = ['name'=>$p['name'], 'price'=>$price, 'qty'=>1, 'type'=>$p['type'], 'fulfillment'=>'collected']; } header("Location: index.php?page=pos"); exit;
    }
    if (isset($_POST['toggle_fulfillment'])) {
        $pid = $_POST['product_id'];
        if(isset($_SESSION['cart'][$pid])) { $_SESSION['cart'][$pid]['fulfillment'] = ($_SESSION['cart'][$pid]['fulfillment'] === 'collected') ? 'uncollected' : 'collected'; } header("Location: index.php?page=pos"); exit;
    }
    if (isset($_POST['update_qty'])) {
        $pid = $_POST['product_id']; 
        if ($_POST['action'] === 'inc') $_SESSION['cart'][$pid]['qty']++; elseif ($_POST['action'] === 'dec') { $_SESSION['cart'][$pid]['qty']--; if($_SESSION['cart'][$pid]['qty']<=0) unset($_SESSION['cart'][$pid]); } header("Location: index.php?page=pos"); exit;
    }
    if (isset($_POST['remove_item'])) unset($_SESSION['cart'][$_POST['product_id']]);
    if (isset($_POST['clear_cart'])) unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['tab_paid'], $_SESSION['pos_member']);
    
    if (isset($_POST['recall_tab'])) {
        $saleId = $_POST['sale_id']; $sale = $pdo->query("SELECT * FROM sales WHERE id = $saleId")->fetch();
        $_SESSION['current_tab_id'] = $saleId; $_SESSION['current_customer'] = $sale['customer_name']; $_SESSION['tab_paid'] = $sale['amount_tendered']; $_SESSION['cart'] = [];
        $items = $pdo->query("SELECT * FROM sale_items WHERE sale_id = $saleId")->fetchAll();
        foreach($items as $i) { 
            $_SESSION['cart'][$i['product_id']] = ['name'=>'Product', 'price'=>$i['price_at_sale'], 'qty'=>$i['quantity'], 'fulfillment'=>$i['fulfillment_status']]; 
            $n = $pdo->query("SELECT name, type FROM products WHERE id={$i['product_id']}")->fetch();
            if($n) { $_SESSION['cart'][$i['product_id']]['name'] = $n['name']; $_SESSION['cart'][$i['product_id']]['type'] = $n['type']; }
        }
        header("Location: index.php?page=pos"); exit;
    }

    if (isset($_POST['checkout']) && !empty($_SESSION['cart'])) {
        $total = 0; foreach ($_SESSION['cart'] as $i) $total += $i['price'] * $i['qty'];
        $disc = (isset($_POST['apply_discount']) && isset($_SESSION['pos_member'])) ? $total * 0.10 : 0;
        $final = $total - $disc;
        $isSplit = isset($_POST['is_split']) && $_POST['is_split'] == 1;
        $method = $isSplit ? ($_POST['method_1'] ?? 'Cash') . " & " . ($_POST['method_2'] ?? 'Card') : ($_POST['payment_method'] ?? 'Cash');
        $tendered = ($method === 'Pending' && !$isSplit) ? 0 : floatval($_POST['amount_tendered'] ?? 0);
        $tip = floatval($_POST['tip_amount'] ?? 0);
        $totalPaid = $tendered; 
        $status = ($method === 'Pending' || $totalPaid < ($final + $tip - 0.01)) ? 'pending' : 'paid';
        $change = $totalPaid - ($final + $tip);
        $cust = $_POST['customer_name'] ?? 'Walk-in';
        $memId = $_SESSION['pos_member']['id'] ?? null;

        $pdo->beginTransaction();
        try {
            $sid = 0;
            if (isset($_SESSION['current_tab_id'])) {
                $sid = $_SESSION['current_tab_id'];
                $pdo->prepare("UPDATE sales SET total_amount=?, final_total=?, payment_method=?, payment_status=?, customer_name=?, member_id=?, amount_tendered=?, change_due=?, tip_amount=? WHERE id=?")->execute([$total, $final, $method, $status, $cust, $memId, $totalPaid, $change, $tip, $sid]);
                $pdo->query("DELETE FROM sale_items WHERE sale_id = $sid");
            } else {
                $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, total_amount, final_total, payment_method, payment_status, customer_name, member_id, amount_tendered, change_due, tip_amount, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?, NOW())")->execute([$userId, $locationId, $activeShiftId, $total, $final, $method, $status, $cust, $memId, $totalPaid, $change, $tip]);
                $sid = $pdo->lastInsertId();
            }
            foreach ($_SESSION['cart'] as $pid => $item) {
                $fulfill = $item['fulfillment'] ?? 'collected';
                $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status, fulfillment_status) VALUES (?,?,?,?,'pending',?)")->execute([$sid, $pid, $item['qty'], $item['price'], $fulfill]);
                if (!isset($_SESSION['current_tab_id']) && ($item['type'] ?? 'item') !== 'service') { 
                    $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$item['qty'], $pid, $locationId]);
                    $nq = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn();
                    $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id) VALUES (?,?,?,?,?,'sale',?)")->execute([$pid, $locationId, $userId, -$item['qty'], $nq, $sid]);
                }
            }
            $pdo->commit();
            $_SESSION['last_sale_id'] = $sid;
            unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['tab_paid'], $_SESSION['pos_member']);
        } catch(Exception $e) { $pdo->rollBack(); }
        header("Location: index.php?page=pos"); exit;
    }
}

// FETCH DATA
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$members = $pdo->query("SELECT * FROM members ORDER BY name ASC")->fetchAll();
$products = ($locationId > 0) ? $pdo->query("SELECT p.*, COALESCE(i.quantity, 0) as stock_qty FROM products p LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = $locationId WHERE p.is_active = 1 AND p.type = 'item' ORDER BY p.name ASC")->fetchAll() : [];
$services = $pdo->query("SELECT * FROM products WHERE type = 'service' AND is_active = 1 ORDER BY name ASC")->fetchAll();

$openTabs = ($locationId > 0) ? $pdo->query("
    SELECT s.id, s.customer_name, s.final_total, s.amount_tendered, s.created_at, s.payment_status,
           (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id AND si.fulfillment_status = 'uncollected') as uncollected_count
    FROM sales s 
    WHERE (s.payment_status = 'pending' 
       OR (s.payment_status = 'paid' AND EXISTS (SELECT 1 FROM sale_items si WHERE si.sale_id = s.id AND si.fulfillment_status = 'uncollected')))
    AND s.location_id = $locationId
    ORDER BY s.created_at DESC
")->fetchAll() : [];

$tabItems = [];
if (!empty($openTabs)) {
    $ids = implode(',', array_column($openTabs, 'id'));
    $rawItems = $pdo->query("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id IN ($ids)")->fetchAll();
    foreach($rawItems as $r) { $tabItems[$r['sale_id']][] = $r; }
}

$total = 0; if(isset($_SESSION['cart'])) foreach($_SESSION['cart'] as $i) $total += $i['price'] * $i['qty'];
$balance = $total - ($_SESSION['tab_paid'] ?? 0);
?>
