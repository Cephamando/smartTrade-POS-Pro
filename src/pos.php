<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];

// --- 1. HANDLE LOCATION SWITCHING ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['set_pos_location'])) {
    $_SESSION['pos_location_id'] = $_POST['pos_location_id'];
    header("Location: index.php?page=pos");
    exit;
}

// --- 2. DETERMINE WORKING LOCATION ---
// Use the selected POS location if set, otherwise default to user's assigned location
// but flag that we need to show the selector modal.
if (isset($_SESSION['pos_location_id'])) {
    $locationId = $_SESSION['pos_location_id'];
    $showLocationModal = false;
} else {
    $locationId = $_SESSION['location_id']; // Fallback for initial load
    $showLocationModal = true;
}

// Fetch all sellable locations for the selector
$sellableLocations = $pdo->query("SELECT * FROM locations WHERE can_sell = 1 ORDER BY name ASC")->fetchAll();

// --- AJAX HANDLERS ---
if (isset($_GET['ajax_ready_count'])) {
    $sql = "SELECT COUNT(DISTINCT s.id) FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id JOIN categories c ON p.category_id = c.id WHERE si.status = 'ready' AND s.location_id = ? AND c.type IN ('food', 'meal')";
    $stmt = $pdo->prepare($sql); $stmt->execute([$locationId]); echo $stmt->fetchColumn() ?: 0; exit;
}

// --- ACTION: CLOSE SHIFT REPORT ---
if (isset($_GET['action']) && $_GET['action'] === 'close_shift_report') {
    $shiftStmt = $pdo->prepare("SELECT id, start_time FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $shiftStmt->execute([$userId]);
    $currentShift = $shiftStmt->fetch();

    if ($currentShift) {
        $sql = "SELECT p.name as product_name, p.price as standard_price, SUM(si.quantity) as qty_sold, SUM(si.quantity * si.price_at_sale) as actual_revenue FROM sale_items si JOIN sales s ON si.sale_id = s.id JOIN products p ON si.product_id = p.id WHERE s.shift_id = ? AND s.payment_status = 'paid' GROUP BY p.id";
        $sales = $pdo->prepare($sql); $sales->execute([$currentShift['id']]); $salesData = $sales->fetchAll();
        $totals = $pdo->prepare("SELECT payment_method, SUM(final_total) as total FROM sales WHERE shift_id = ? AND payment_status = 'paid' GROUP BY payment_method");
        $totals->execute([$currentShift['id']]); $totalsData = $totals->fetchAll();
        $_SESSION['shift_report'] = ['user' => $_SESSION['username'], 'start' => $currentShift['start_time'], 'end' => date('Y-m-d H:i:s'), 'sales' => $salesData, 'totals' => $totalsData];
        require_once '/var/www/html/templates/shift_summary.php'; 
    } else {
        echo "<div class='alert alert-danger'>No active shift found. Please clock in first.</div>";
    }
    exit; 
}

// --- HANDLE CART & CHECKOUT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    
    if (isset($_POST['recall_tab'])) {
        $saleId = $_POST['sale_id'];
        $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND location_id = ?");
        $stmt->execute([$saleId, $locationId]);
        $sale = $stmt->fetch();

        if ($sale) {
            $itemStmt = $pdo->prepare("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
            $itemStmt->execute([$saleId]);
            $items = $itemStmt->fetchAll();

            $_SESSION['cart'] = [];
            foreach ($items as $i) {
                $_SESSION['cart'][$i['product_id']] = ['name' => $i['name'], 'price' => $i['price_at_sale'], 'qty' => $i['quantity']];
            }
            $_SESSION['current_tab_id'] = $saleId;
            $_SESSION['current_customer'] = $sale['customer_name'];
        }
    }

    if (isset($_POST['checkout'])) {
        $shiftStmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $shiftStmt->execute([$userId]);
        $sid = $shiftStmt->fetchColumn();
        
        if (!$sid) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Error: You must Start a Shift first.";
            header("Location: index.php?page=pos"); exit;
        }

        $total = 0; foreach ($_SESSION['cart'] as $item) { $total += $item['price'] * $item['qty']; }
        $tendered = floatval($_POST['amount_tendered'] ?? $total);
        $method = $_POST['payment_method'];
        if ($method === 'mobile_money' && !empty($_POST['momo_provider'])) { $method = "mobile_money (" . $_POST['momo_provider'] . ")"; }
        
        if (isset($_SESSION['current_tab_id']) && $method === 'pending') {
             $_SESSION['swal_type'] = 'error';
             $_SESSION['swal_msg'] = "Cannot select 'Pay Later' on an Open Tab. You must settle the debt.";
             header("Location: index.php?page=pos"); exit;
        }

        $status = ($method === 'pending') ? 'pending' : 'paid';
        $collectedBy = ($status === 'paid') ? $_SESSION['username'] : null;

        if (isset($_SESSION['current_tab_id'])) {
            $saleId = $_SESSION['current_tab_id'];
            $stmt = $pdo->prepare("UPDATE sales SET final_total = ?, payment_method = ?, payment_status = ?, customer_name = ?, amount_tendered = ?, change_due = ?, collected_by = ? WHERE id = ?");
            $stmt->execute([$total, $method, $status, $_POST['customer_name'], $tendered, ($tendered - $total), $collectedBy, $saleId]);
            $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$saleId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO sales (user_id, location_id, shift_id, total_amount, final_total, payment_method, payment_status, customer_name, amount_tendered, change_due, collected_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $locationId, $sid, $total, $total, $method, $status, $_POST['customer_name'], $tendered, ($tendered - $total), $collectedBy]);
            $saleId = $pdo->lastInsertId();
        }

        foreach ($_SESSION['cart'] as $pid => $item) {
            $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale) VALUES (?, ?, ?, ?)")->execute([$saleId, $pid, $item['qty'], $item['price']]);
            if ($status === 'paid') {
                $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?")->execute([$item['qty'], $pid, $locationId]);
                $newQty = $pdo->query("SELECT quantity FROM inventory WHERE product_id = $pid AND location_id = $locationId")->fetchColumn();
                $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id) VALUES (?, ?, ?, ?, ?, 'sale', ?)")
                    ->execute([$pid, $locationId, $userId, -$item['qty'], $newQty, $saleId]);
            }
        }
        
        if ($status === 'paid') {
            $_SESSION['last_sale_id'] = $saleId;
        }

        unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer']);
    }
    
    if (isset($_POST['clear_cart'])) { unset($_SESSION['cart'], $_SESSION['current_tab_id'], $_SESSION['current_customer']); }
    
    header("Location: index.php?page=pos"); exit;
}

// --- FETCH DISPLAY DATA ---
$locStmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?"); 
$locStmt->execute([$locationId]); 
$locationName = $locStmt->fetchColumn() ?: 'Unknown Location';

$products = $pdo->query("SELECT p.*, COALESCE(i.quantity, 0) as stock_qty FROM products p LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = $locationId WHERE p.is_active = 1 ORDER BY p.name ASC")->fetchAll();
$openTabs = $pdo->query("SELECT id, customer_name, final_total FROM sales WHERE payment_status = 'pending' AND location_id = $locationId ORDER BY created_at DESC")->fetchAll();
?>
