<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];

// --- 1. HANDLE MANUAL LOCATION SELECTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_pos_location'])) {
    $_SESSION['pos_location_id'] = intval($_POST['pos_location_id']);
    $stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
    $stmt->execute([$_SESSION['pos_location_id']]);
    $_SESSION['pos_location_name'] = $stmt->fetchColumn();
    header("Location: index.php?page=pos"); exit;
}

// --- 2. DETERMINE CONTEXT (Strict Isolation) ---
$activeShiftId = null;
$pendingShift = null;
$expectedShiftCash = 0.00;
$locationName = 'Unknown Station';
$locationId = 0; // Default to 0 to force selection

// Check for ANY active or pending shift for this user
$shiftStmt = $pdo->prepare("SELECT s.*, u.full_name as cashier_name FROM shifts s JOIN users u ON s.user_id = u.id WHERE s.user_id = ? AND s.status IN ('open', 'pending_approval') ORDER BY s.id DESC LIMIT 1");
$shiftStmt->execute([$userId]);
$currentShift = $shiftStmt->fetch();

if ($currentShift) {
    // SCENARIO A: Active Shift Found
    // We MUST use the location defined in the shift.
    $locationId = $currentShift['location_id'];
    
    // Fetch Name
    $lNameStmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
    $lNameStmt->execute([$locationId]);
    $locationName = $lNameStmt->fetchColumn();

    // CLEANUP: If a session variable exists, clear it. 
    // This ensures that when this shift eventually closes, the session is empty, 
    // forcing the user to select a location again.
    if (isset($_SESSION['pos_location_id'])) {
        unset($_SESSION['pos_location_id']);
        unset($_SESSION['pos_location_name']);
    }
    
    $activeShiftId = ($currentShift['status'] === 'open') ? $currentShift['id'] : null;
    $pendingShift  = ($currentShift['status'] === 'pending_approval') ? $currentShift : null;

    // Calculate Expected Cash if open
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
} else {
    // SCENARIO B: No Active Shift
    // We strictly rely on the manually set POS location from the modal.
    // We do NOT fall back to the user's default location.
    $locationId = $_SESSION['pos_location_id'] ?? 0;
    $locationName = $_SESSION['pos_location_name'] ?? 'Select Station';
}

$sellableLocations = $pdo->query("SELECT * FROM locations WHERE can_sell = 1 ORDER BY name ASC")->fetchAll();

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

// --- 4. POS LOGIC (Cart, Member, Checkout) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Member Select
    if (isset($_POST['select_member'])) {
        $_SESSION['pos_member'] = ['id' => $_POST['member_id'], 'name' => $_POST['member_name'], 'phone' => $_POST['member_phone']];
        $_SESSION['current_customer'] = $_POST['member_name']; 
        header("Location: index.php?page=pos"); exit;
    }
    // Member Remove
    if (isset($_POST['remove_member'])) {
        unset($_SESSION['pos_member']);
        $_SESSION['current_customer'] = 'Walk-in'; 
        header("Location: index.php?page=pos"); exit;
    }
    // Tab Recall
    if (isset($_POST['recall_tab'])) {
        $saleId = $_POST['sale_id'];
        $sale = $pdo->query("SELECT * FROM sales WHERE id = $saleId")->fetch();
        if ($sale) {
            $_SESSION['current_tab_id'] = $sale['id'];
            $_SESSION['current_customer'] = $sale['customer_name'];
            $_SESSION['tab_paid'] = floatval($sale['amount_tendered']);
            $_SESSION['cart'] = [];
            if ($sale['member_id']) {
                 $m = $pdo->query("SELECT * FROM members WHERE id = {$sale['member_id']}")->fetch();
                 if($m) $_SESSION['pos_member'] = ['id'=>$m['id'], 'name'=>$m['name'], 'phone'=>$m['phone']];
            }
            $items = $pdo->query("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = $saleId")->fetchAll();
            foreach ($items as $item) $_SESSION['cart'][$item['product_id']] = ['name' => $item['name'], 'price' => $item['price_at_sale'], 'qty' => $item['quantity']];
        }
        header("Location: index.php?page=pos"); exit;
    }
    // Add Item
    if ($activeShiftId && isset($_POST['add_item'])) {
        $pid = $_POST['product_id'];
        $qty = $_SESSION['cart'][$pid]['qty'] ?? 0;
        $stock = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn() ?: 0;
        if (($qty + 1) <= $stock) {
            $p = $pdo->query("SELECT * FROM products WHERE id = $pid")->fetch();
            if(isset($_SESSION['cart'][$pid])) $_SESSION['cart'][$pid]['qty']++; 
            else $_SESSION['cart'][$pid] = ['name' => $p['name'], 'price' => $p['price'], 'qty' => 1];
        } else { $_SESSION['swal_type'] = 'warning'; $_SESSION['swal_msg'] = "Max stock reached."; }
        header("Location: index.php?page=pos"); exit;
    }
    // Update Qty
    if ($activeShiftId && isset($_POST['update_qty'])) {
        $pid = $_POST['product_id'];
        $stock = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn() ?: 0;
        if ($_POST['action'] === 'inc' && $_SESSION['cart'][$pid]['qty'] < $stock) $_SESSION['cart'][$pid]['qty']++;
        elseif ($_POST['action'] === 'dec') { $_SESSION['cart'][$pid]['qty']--; if($_SESSION['cart'][$pid]['qty']<=0) unset($_SESSION['cart'][$pid]); }
        header("Location: index.php?page=pos"); exit;
    }
    // Clear/Remove
    if (isset($_POST['remove_item'])) unset($_SESSION['cart'][$_POST['product_id']]);
    if (isset($_POST['clear_cart'])) unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['tab_paid'], $_SESSION['pos_member']);
    
    // Log Waste
    if (isset($_POST['log_waste']) && !empty($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $pid => $item) {
            $pdo->query("UPDATE inventory SET quantity = quantity - {$item['qty']} WHERE product_id = $pid AND location_id = $locationId");
            $newQty = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn();
            $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, created_at) VALUES (?, ?, ?, ?, ?, 'adjustment', NOW())")->execute([$pid, $locationId, $userId, -$item['qty'], $newQty]);
        }
        unset($_SESSION['cart']);
        header("Location: index.php?page=pos"); exit;
    }

    // Checkout
    if ($activeShiftId && isset($_POST['checkout']) && !empty($_SESSION['cart'])) {
        $total = 0; foreach ($_SESSION['cart'] as $i) $total += $i['price'] * $i['qty'];
        $disc = (isset($_POST['apply_discount']) && isset($_SESSION['pos_member'])) ? $total * 0.10 : 0;
        $final = $total - $disc;
        
        $isSplit = isset($_POST['is_split']) && $_POST['is_split'] == 1;
        $method = $isSplit ? "{$_POST['method_1']} & {$_POST['method_2']}" : $_POST['payment_method'];
        $tendered = ($method === 'Pending' && !$isSplit) ? 0 : floatval($_POST['amount_tendered']);
        $prevPaid = $_SESSION['tab_paid'] ?? 0;
        $totalPaid = $prevPaid + $tendered;
        $status = ($method === 'Pending' || $totalPaid < ($final - 0.01)) ? 'pending' : 'paid';
        $change = $totalPaid - $final;
        $cust = $_POST['customer_name'] ?: 'Walk-in';
        $memId = $_SESSION['pos_member']['id'] ?? null;

        $pdo->beginTransaction();
        try {
            if (isset($_SESSION['current_tab_id'])) {
                $sid = $_SESSION['current_tab_id'];
                $pdo->prepare("UPDATE sales SET total_amount=?, final_total=?, payment_method=?, payment_status=?, customer_name=?, member_id=?, amount_tendered=?, change_due=? WHERE id=?")->execute([$total, $final, $method, $status, $cust, $memId, $totalPaid, $change, $sid]);
                $pdo->query("DELETE FROM sale_items WHERE sale_id = $sid");
            } else {
                $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, total_amount, final_total, payment_method, payment_status, customer_name, member_id, amount_tendered, change_due) VALUES (?,?,?,?,?,?,?,?,?,?,?)")->execute([$userId, $locationId, $activeShiftId, $total, $final, $method, $status, $cust, $memId, $totalPaid, $change]);
                $sid = $pdo->lastInsertId();
            }
            foreach ($_SESSION['cart'] as $pid => $item) {
                $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status) VALUES (?,?,?,?,'pending')")->execute([$sid, $pid, $item['qty'], $item['price']]);
                if ($status !== 'pending_but_no_deduct') { // Logic: We usually deduct stock even for pending
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

// Data Fetch
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$members = $pdo->query("SELECT * FROM members ORDER BY name ASC")->fetchAll();
$products = ($locationId > 0) ? $pdo->query("SELECT p.*, COALESCE(i.quantity, 0) as stock_qty FROM products p LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = $locationId WHERE p.is_active = 1 ORDER BY p.name ASC")->fetchAll() : [];
$openTabs = ($locationId > 0) ? $pdo->query("SELECT s.id, s.customer_name, s.final_total, s.amount_tendered, s.created_at FROM sales s WHERE s.payment_status = 'pending' ORDER BY s.created_at DESC")->fetchAll() : [];

$total = 0; if(isset($_SESSION['cart'])) foreach($_SESSION['cart'] as $i) $total += $i['price'] * $i['qty'];
?>
