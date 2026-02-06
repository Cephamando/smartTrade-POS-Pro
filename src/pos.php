<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];

// --- 1. LOCATION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_pos_location'])) {
    $_SESSION['pos_location_id'] = $_POST['pos_location_id'];
    // Fetch name for display
    $stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
    $stmt->execute([$_POST['pos_location_id']]);
    $_SESSION['pos_location_name'] = $stmt->fetchColumn();
    header("Location: index.php?page=pos"); exit;
}

// Get Location (Default to 0, which is invalid)
$locationId = $_SESSION['pos_location_id'] ?? $_SESSION['location_id'] ?? 0;
$locationName = $_SESSION['pos_location_name'] ?? $_SESSION['location_name'] ?? 'Unknown Station';

$sellableLocations = $pdo->query("SELECT * FROM locations WHERE can_sell = 1 ORDER BY name ASC")->fetchAll();

// --- 2. CHECK SHIFT STATUS (Only if Location is Valid) ---
$activeShiftId = null;
$pendingShift = null;

if ($locationId > 0) {
    $shiftStmt = $pdo->prepare("SELECT s.*, u.full_name as cashier_name FROM shifts s JOIN users u ON s.user_id = u.id WHERE s.user_id = ? AND s.status IN ('open', 'pending_approval') ORDER BY s.id DESC LIMIT 1");
    $shiftStmt->execute([$userId]);
    $currentShift = $shiftStmt->fetch();

    $activeShiftId = ($currentShift && $currentShift['status'] === 'open') ? $currentShift['id'] : null;
    $pendingShift  = ($currentShift && $currentShift['status'] === 'pending_approval') ? $currentShift : null;
}

// --- 3. HANDLE REQUEST START ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_start_shift'])) {
    if ($locationId > 0) {
        $startCash = floatval($_POST['starting_cash']);
        try {
            $pdo->prepare("INSERT INTO shifts (user_id, location_id, start_time, starting_cash, status) VALUES (?, ?, NOW(), ?, 'pending_approval')")
                ->execute([$userId, $locationId, $startCash]);
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

// --- AJAX ---
if (isset($_GET['ajax_ready_count'])) {
    if ($locationId > 0) {
        $sql = "SELECT COUNT(DISTINCT s.id) FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id JOIN categories c ON p.category_id = c.id WHERE si.status = 'ready' AND s.location_id = ? AND c.type IN ('food', 'meal')";
        $stmt = $pdo->prepare($sql); $stmt->execute([$locationId]); echo $stmt->fetchColumn() ?: 0;
    } else { echo 0; }
    exit;
}

// --- POS LOGIC (Only if Active) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeShiftId) {
    if (isset($_POST['search_member'])) {
        $term = trim($_POST['member_search']);
        $stmt = $pdo->prepare("SELECT * FROM members WHERE phone LIKE ? OR name LIKE ? LIMIT 1");
        $stmt->execute(["%$term%", "%$term%"]);
        $member = $stmt->fetch();
        if ($member) { $_SESSION['pos_member'] = $member; $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Attached: " . $member['name']; } 
        else { unset($_SESSION['pos_member']); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Member not found."; }
    }
    if (isset($_POST['detach_member'])) { unset($_SESSION['pos_member']); }

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
                if (isset($_SESSION['cart'][$pid])) { 
                    if ($_SESSION['cart'][$pid]['qty'] < $currentStock) { $_SESSION['cart'][$pid]['qty']++; } 
                    else { $_SESSION['swal_type'] = 'warning'; $_SESSION['swal_msg'] = "Max stock reached!"; }
                } else { $_SESSION['cart'][$pid] = ['name' => $product['name'], 'price' => $product['price'], 'qty' => 1]; }
            }
        } else { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Out of Stock!"; }
    }

    if (isset($_POST['update_qty'])) {
        $pid = $_POST['product_id'];
        $action = $_POST['action']; 
        $limitReached = false;
        if ($action === 'inc') {
            $stockCheck = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?");
            $stockCheck->execute([$pid, $locationId]);
            $max = $stockCheck->fetchColumn() ?: 0;
            if (isset($_SESSION['cart'][$pid]) && $_SESSION['cart'][$pid]['qty'] >= $max) { $limitReached = true; }
        }
        if (isset($_SESSION['cart'][$pid])) {
            if ($action === 'inc' && !$limitReached) { $_SESSION['cart'][$pid]['qty']++; }
            elseif ($action === 'dec') { $_SESSION['cart'][$pid]['qty']--; if ($_SESSION['cart'][$pid]['qty'] <= 0) unset($_SESSION['cart'][$pid]); }
        }
    }
    if (isset($_POST['remove_item'])) { unset($_SESSION['cart'][$_POST['product_id']]); }
    if (isset($_POST['clear_cart'])) { unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['pos_member']); }

    if (isset($_POST['checkout'])) {
        if (empty($_SESSION['cart'])) { header("Location: index.php?page=pos"); exit; }
        if (!$activeShiftId) { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Start a Shift first."; header("Location: index.php?page=pos"); exit; }

        $cartTotal = 0; foreach ($_SESSION['cart'] as $item) { $cartTotal += $item['price'] * $item['qty']; }
        $pointsRedeemed = 0; $finalTotal = $cartTotal; $memberId = null;

        if (isset($_SESSION['pos_member'])) {
            $memberId = $_SESSION['pos_member']['id'];
            if (isset($_POST['redeem_points']) && $_POST['redeem_points'] == '1') {
                $pointsRedeemed = min($_SESSION['pos_member']['points_balance'], $cartTotal); $finalTotal = $cartTotal - $pointsRedeemed;
            }
        }

        $method = $_POST['payment_method'];
        $tendered = floatval($_POST['amount_tendered'] ?? $finalTotal);
        if ($method === 'mobile_money' && !empty($_POST['momo_provider'])) { $method = "mobile_money (" . $_POST['momo_provider'] . ")"; }

        if ($pointsRedeemed > 0 && $method === 'pending') { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Cannot redeem points on Tab."; header("Location: index.php?page=pos"); exit; }
        if (isset($_SESSION['current_tab_id']) && $method === 'pending') { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Cannot Pay Later on Open Tab."; header("Location: index.php?page=pos"); exit; }

        $status = ($method === 'pending') ? 'pending' : 'paid'; $collectedBy = ($status === 'paid') ? $_SESSION['username'] : null;
        $pointsEarned = ($status === 'paid' && $memberId && $finalTotal > 0) ? ($finalTotal * 0.05) : 0;

        $pdo->beginTransaction();
        try {
            if (isset($_SESSION['current_tab_id'])) {
                $saleId = $_SESSION['current_tab_id'];
                $stmt = $pdo->prepare("UPDATE sales SET final_total = ?, payment_method = ?, payment_status = ?, customer_name = ?, amount_tendered = ?, change_due = ?, collected_by = ?, member_id = ?, points_earned = ?, points_redeemed = ? WHERE id = ?");
                $stmt->execute([$finalTotal, $method, $status, $_POST['customer_name'], $tendered, ($tendered - $finalTotal), $collectedBy, $memberId, $pointsEarned, $pointsRedeemed, $saleId]);
                $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$saleId]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, total_amount, final_total, payment_method, payment_status, customer_name, amount_tendered, change_due, collected_by, member_id, points_earned, points_redeemed) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$userId, $locationId, $activeShiftId, $cartTotal, $finalTotal, $method, $status, $_POST['customer_name'], $tendered, ($tendered - $finalTotal), $collectedBy, $memberId, $pointsEarned, $pointsRedeemed]);
                $saleId = $pdo->lastInsertId();
            }
            foreach ($_SESSION['cart'] as $pid => $item) {
                $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale) VALUES (?, ?, ?, ?)")->execute([$saleId, $pid, $item['qty'], $item['price']]);
                if ($status === 'paid') {
                    $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$item['qty'], $pid, $locationId]);
                    $newQty = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn();
                    $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id) VALUES (?, ?, ?, ?, ?, 'sale', ?)")->execute([$pid, $locationId, $userId, -$item['qty'], $newQty, $saleId]);
                }
            }
            if ($status === 'paid' && $memberId) {
                $netPoints = $pointsEarned - $pointsRedeemed;
                if ($netPoints != 0) $pdo->prepare("UPDATE members SET points_balance = points_balance + ? WHERE id = ?")->execute([$netPoints, $memberId]);
            }
            $pdo->commit();
            if ($status === 'paid') $_SESSION['last_sale_id'] = $saleId;
            unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['pos_member']);
        } catch (Exception $e) { $pdo->rollBack(); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Transaction Failed: " . $e->getMessage(); }
    }
    
    header("Location: index.php?page=pos"); exit;
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Get Products (Only if Location Valid)
if ($locationId > 0) {
    $prodStmt = $pdo->prepare("SELECT p.*, COALESCE(i.quantity, 0) as stock_qty FROM products p LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = ? WHERE p.is_active = 1 ORDER BY p.name ASC");
    $prodStmt->execute([$locationId]);
    $products = $prodStmt->fetchAll();
    
    $openTabs = $pdo->query("SELECT s.id, s.customer_name, s.final_total, s.created_at, u.username as cashier, l.name as loc_name FROM sales s JOIN users u ON s.user_id = u.id JOIN locations l ON s.location_id = l.id WHERE s.payment_status = 'pending' ORDER BY s.created_at DESC")->fetchAll();
} else {
    $products = [];
    $openTabs = [];
}
?>
