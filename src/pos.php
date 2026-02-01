<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];

// --- LOCATION HANDLING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_pos_location'])) {
    $_SESSION['pos_location_id'] = $_POST['pos_location_id'];
    header("Location: index.php?page=pos"); exit;
}
$locationId = $_SESSION['pos_location_id'] ?? $_SESSION['location_id'];
$sellableLocations = $pdo->query("SELECT * FROM locations WHERE can_sell = 1 ORDER BY name ASC")->fetchAll();

// --- AJAX: CHECK FOR READY ORDERS ---
if (isset($_GET['ajax_ready_count'])) {
    $sql = "SELECT COUNT(DISTINCT s.id) FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id JOIN categories c ON p.category_id = c.id WHERE si.status = 'ready' AND s.location_id = ? AND c.type IN ('food', 'meal')";
    $stmt = $pdo->prepare($sql); $stmt->execute([$locationId]); echo $stmt->fetchColumn() ?: 0; exit;
}

// --- SHIFT REPORT LOGIC (UPDATED FOR DRILL-DOWN) ---
if (isset($_GET['action']) && $_GET['action'] === 'close_shift_report') {
    $targetShiftId = null;
    $isDrillDown = false;

    // 1. Admin Drill-Down Mode
    if (isset($_GET['shift_id']) && in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
        $targetShiftId = $_GET['shift_id'];
        $isDrillDown = true;
    } else {
        // 2. Standard User Mode (Own Shift)
        $shiftStmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $shiftStmt->execute([$userId]);
        $targetShiftId = $shiftStmt->fetchColumn();
    }

    if ($targetShiftId) {
        $meta = $pdo->prepare("SELECT s.*, u.username FROM shifts s JOIN users u ON s.user_id = u.id WHERE s.id = ?");
        $meta->execute([$targetShiftId]);
        $shiftMeta = $meta->fetch();

        // Calculate Totals
        $sql = "SELECT p.name as product_name, p.price as standard_price, SUM(si.quantity) as qty_sold, SUM(si.quantity * si.price_at_sale) as actual_revenue FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id WHERE s.shift_id = ? AND s.payment_status = 'paid' GROUP BY p.id";
        $sales = $pdo->prepare($sql); $sales->execute([$targetShiftId]); $salesData = $sales->fetchAll();
        
        $totals = $pdo->prepare("SELECT payment_method, SUM(final_total) as total FROM sales WHERE shift_id = ? AND payment_status = 'paid' GROUP BY payment_method");
        $totals->execute([$targetShiftId]); $totalsData = $totals->fetchAll();
        
        // Pass data to template
        $_SESSION['shift_report'] = [
            'user' => $shiftMeta['username'], 
            'start' => $shiftMeta['start_time'], 
            'end' => date('Y-m-d H:i:s'), 
            'sales' => $salesData, 
            'totals' => $totalsData,
            'is_drill_down' => $isDrillDown // Flag to hide "Print & Close" button if just viewing
        ];
        require_once '/var/www/html/templates/shift_summary.php'; 
    } else {
        echo "<div class='alert alert-danger'>No active shift found.</div>";
    }
    exit; 
}

// --- HANDLE POST REQUESTS (Member, Cart, Checkout) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. MEMBER SEARCH
    if (isset($_POST['search_member'])) {
        $term = trim($_POST['member_search']);
        $stmt = $pdo->prepare("SELECT * FROM members WHERE phone LIKE ? OR name LIKE ? LIMIT 1");
        $stmt->execute(["%$term%", "%$term%"]);
        $member = $stmt->fetch();
        
        if ($member) {
            $_SESSION['pos_member'] = $member;
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Attached: " . $member['name'];
        } else {
            unset($_SESSION['pos_member']);
            $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Member not found.";
        }
    }

    if (isset($_POST['detach_member'])) { unset($_SESSION['pos_member']); }

    // 2. ADD ITEM
    if (isset($_POST['add_item'])) {
        $pid = $_POST['product_id'];
        $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?"); $stmt->execute([$pid]);
        $product = $stmt->fetch();
        if ($product) {
            if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
            if (isset($_SESSION['cart'][$pid])) { $_SESSION['cart'][$pid]['qty']++; } 
            else { $_SESSION['cart'][$pid] = ['name' => $product['name'], 'price' => $product['price'], 'qty' => 1]; }
        }
    }
    
    // 3. RECALL TAB
    if (isset($_POST['recall_tab'])) {
        $saleId = $_POST['sale_id'];
        $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?"); 
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch();

        if ($sale) {
            $items = $pdo->prepare("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
            $items->execute([$saleId]);
            $_SESSION['cart'] = [];
            foreach ($items->fetchAll() as $i) {
                $_SESSION['cart'][$i['product_id']] = ['name' => $i['name'], 'price' => $i['price_at_sale'], 'qty' => $i['quantity']];
            }
            $_SESSION['current_tab_id'] = $saleId;
            $_SESSION['current_customer'] = $sale['customer_name'];
            if ($sale['member_id']) {
                $m = $pdo->prepare("SELECT * FROM members WHERE id = ?");
                $m->execute([$sale['member_id']]);
                $_SESSION['pos_member'] = $m->fetch();
            }
        }
    }

    // 4. CHECKOUT
    if (isset($_POST['checkout'])) {
        if (empty($_SESSION['cart'])) { header("Location: index.php?page=pos"); exit; }
        
        $shiftStmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $shiftStmt->execute([$userId]);
        $sid = $shiftStmt->fetchColumn();
        
        if (!$sid) { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Start a Shift first."; header("Location: index.php?page=pos"); exit; }

        $cartTotal = 0; foreach ($_SESSION['cart'] as $item) { $cartTotal += $item['price'] * $item['qty']; }
        $pointsRedeemed = 0; $finalTotal = $cartTotal; $memberId = null;

        if (isset($_SESSION['pos_member'])) {
            $memberId = $_SESSION['pos_member']['id'];
            if (isset($_POST['redeem_points']) && $_POST['redeem_points'] == '1') {
                $pointsRedeemed = min($_SESSION['pos_member']['points_balance'], $cartTotal);
                $finalTotal = $cartTotal - $pointsRedeemed;
            }
        }

        $method = $_POST['payment_method'];
        $tendered = floatval($_POST['amount_tendered'] ?? $finalTotal);
        if ($method === 'mobile_money' && !empty($_POST['momo_provider'])) { $method = "mobile_money (" . $_POST['momo_provider'] . ")"; }

        if ($pointsRedeemed > 0 && $method === 'pending') { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Cannot redeem points on Tab."; header("Location: index.php?page=pos"); exit; }
        if (isset($_SESSION['current_tab_id']) && $method === 'pending') { $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Cannot Pay Later on Open Tab."; header("Location: index.php?page=pos"); exit; }

        $status = ($method === 'pending') ? 'pending' : 'paid';
        $collectedBy = ($status === 'paid') ? $_SESSION['username'] : null;
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
                $stmt->execute([$userId, $locationId, $sid, $cartTotal, $finalTotal, $method, $status, $_POST['customer_name'], $tendered, ($tendered - $finalTotal), $collectedBy, $memberId, $pointsEarned, $pointsRedeemed]);
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
        } catch (Exception $e) {
            $pdo->rollBack(); $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Transaction Failed: " . $e->getMessage();
        }
    }
    
    if (isset($_POST['clear_cart'])) { unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer'], $_SESSION['pos_member']); }
    header("Location: index.php?page=pos"); exit;
}

$locStmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?"); $locStmt->execute([$locationId]); $locationName = $locStmt->fetchColumn() ?: 'Unknown';
$products = $pdo->query("SELECT p.*, COALESCE(i.quantity, 0) as stock_qty FROM products p LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = $locationId WHERE p.is_active = 1 ORDER BY p.name ASC")->fetchAll();
$openTabs = $pdo->query("SELECT s.id, s.customer_name, s.final_total, s.created_at, u.username as cashier, l.name as loc_name FROM sales s JOIN users u ON s.user_id = u.id JOIN locations l ON s.location_id = l.id WHERE s.payment_status = 'pending' ORDER BY s.created_at DESC")->fetchAll();
?>
