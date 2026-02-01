<?php
// SECURITY: Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit;
}

$userId = $_SESSION['user_id'];
$locationId = $_SESSION['location_id'];

// --- GET LOCATION NAME ---
// We fetch the name so we can display "POS - KITCHEN" or "POS - BAR"
$locStmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
$locStmt->execute([$locationId]);
$locationName = $locStmt->fetchColumn() ?: 'Unknown Location';

// --- ACTION: ADD TO CART (STRICT LOCATION CHECK) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $pid = $_POST['product_id'];
    $catId = $_POST['category_id'];
    
    // 1. Get Current Stock FOR THIS LOCATION ONLY
    $stockStmt = $pdo->prepare("SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?");
    $stockStmt->execute([$pid, $locationId]);
    $stockData = $stockStmt->fetch();
    $realStock = $stockData ? $stockData['quantity'] : 0;

    // 2. Get Current Cart Quantity
    $currentCartQty = isset($_SESSION['cart'][$pid]) ? $_SESSION['cart'][$pid]['qty'] : 0;

    // 3. Validate
    if (($currentCartQty + 1) > $realStock) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = "Out of Stock at $locationName! Only $realStock available.";
        header("Location: index.php?page=pos");
        exit;
    }

    // 4. Proceed
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$pid]);
    $product = $stmt->fetch();

    if ($product) {
        if (!isset($_SESSION['cart'])) $_SESSION['cart'] = [];
        
        if (isset($_SESSION['cart'][$pid])) {
            $_SESSION['cart'][$pid]['qty']++;
        } else {
            $_SESSION['cart'][$pid] = [
                'name' => $product['name'],
                'price' => $product['price'],
                'qty' => 1,
                'category_id' => $catId,
                'category_name' => $_POST['category_name']
            ];
        }
    }
    header("Location: index.php?page=pos");
    exit;
}

// --- ACTION: CLEAR CART ---
if (isset($_POST['clear_cart'])) {
    unset($_SESSION['cart']);
    unset($_SESSION['current_tab_id']); 
    header("Location: index.php?page=pos");
    exit;
}

// --- ACTION: REMOVE ITEM ---
if (isset($_POST['remove_item'])) {
    $pid = $_POST['product_id'];
    unset($_SESSION['cart'][$pid]);
    header("Location: index.php?page=pos");
    exit;
}

// --- ACTION: CHECKOUT ---
if (isset($_POST['checkout'])) {
    if (empty($_SESSION['cart'])) {
        header("Location: index.php?page=pos");
        exit;
    }

    $paymentMethod = $_POST['payment_method']; 
    $customerName = !empty($_POST['customer_name']) ? $_POST['customer_name'] : 'Walk-in';
    $tendered = isset($_POST['amount_tendered']) && $_POST['amount_tendered'] !== '' ? (float)$_POST['amount_tendered'] : 0.00;
    
    $total = 0;
    foreach ($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['qty'];
    }
    
    $change = ($paymentMethod !== 'pending') ? ($tendered - $total) : 0;
    $status = ($paymentMethod === 'pending') ? 'pending' : 'paid';

    if (isset($_SESSION['current_tab_id'])) {
        $saleId = $_SESSION['current_tab_id'];
        $stmt = $pdo->prepare("UPDATE sales SET final_total = ?, payment_method = ?, payment_status = ?, customer_name = ?, amount_tendered = ?, change_due = ? WHERE id = ?");
        $stmt->execute([$total, $paymentMethod, $status, $customerName, $tendered, $change, $saleId]);
        $pdo->prepare("DELETE FROM sale_items WHERE sale_id = ?")->execute([$saleId]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO sales (user_id, location_id, total_amount, discount, final_total, payment_method, payment_status, customer_name, amount_tendered, change_due) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $locationId, $total, $total, $paymentMethod, $status, $customerName, $tendered, $change]);
        $saleId = $pdo->lastInsertId();
    }

    $stmtItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status) VALUES (?, ?, ?, ?, 'pending')");
    // CRITICAL: Deduct stock ONLY from current location
    $stmtStock = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?");

    foreach ($_SESSION['cart'] as $pid => $item) {
        $stmtItem->execute([$saleId, $pid, $item['qty'], $item['price']]);
        if (!isset($_SESSION['current_tab_id'])) {
            $stmtStock->execute([$item['qty'], $pid, $locationId]);
        }
    }

    unset($_SESSION['cart']);
    unset($_SESSION['current_tab_id']);

    if ($status === 'paid') {
        $_SESSION['last_sale_id'] = $saleId;
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Sale Completed! Change: " . number_format($change, 2);
    } else {
        $_SESSION['swal_type'] = 'info';
        $_SESSION['swal_msg'] = "Order Parked for $customerName";
    }

    header("Location: index.php?page=pos");
    exit;
}

// --- ACTION: RECALL TAB ---
if (isset($_POST['recall_tab'])) {
    $saleId = $_POST['sale_id'];
    // Strict location check on tab recall
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ? AND location_id = ?");
    $stmt->execute([$saleId, $locationId]);
    $sale = $stmt->fetch();

    if ($sale) {
        $stmt = $pdo->prepare("SELECT si.*, p.name, p.category_id FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ?");
        $stmt->execute([$saleId]);
        $items = $stmt->fetchAll();

        $_SESSION['cart'] = [];
        foreach ($items as $item) {
            $_SESSION['cart'][$item['product_id']] = [
                'name' => $item['name'],
                'price' => $item['price_at_sale'],
                'qty' => $item['quantity'],
                'category_id' => $item['category_id'],
                'category_name' => 'Restored' 
            ];
        }
        $_SESSION['current_tab_id'] = $saleId;
        $_SESSION['current_customer'] = $sale['customer_name'];
    }
    header("Location: index.php?page=pos");
    exit;
}

// --- ACTION: CLOSE SHIFT REPORT ---
if (isset($_GET['action']) && $_GET['action'] === 'close_shift_report') {
    
    $stmt = $pdo->prepare("SELECT created_at FROM expenses WHERE description = 'SHIFT_CLOSE' AND user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$userId]);
    $lastClose = $stmt->fetch();
    $startTime = $lastClose['created_at'] ?? date('Y-m-d 00:00:00'); 

    $salesParams = [$userId, $startTime];
    // Filter sales by Current Location implicitly via User ID (users are tied to locations)
    $sql = "
        SELECT 
            p.name as product_name, 
            p.price as standard_price,
            SUM(si.quantity) as qty_sold, 
            SUM(si.quantity * si.price_at_sale) as actual_revenue
        FROM sale_items si
        JOIN sales s ON si.sale_id = s.id
        JOIN products p ON si.product_id = p.id
        WHERE s.user_id = ? AND s.created_at >= ? AND s.payment_status = 'paid'
        GROUP BY p.id
    ";
    $sales = $pdo->prepare($sql);
    $sales->execute($salesParams);
    $salesData = $sales->fetchAll();

    $totals = $pdo->prepare("SELECT payment_method, SUM(final_total) as total FROM sales WHERE user_id = ? AND created_at >= ? AND payment_status = 'paid' GROUP BY payment_method");
    $totals->execute($salesParams);
    $totalsData = $totals->fetchAll();

    $_SESSION['shift_report'] = [
        'user' => $_SESSION['username'],
        'location' => $locationName,
        'start' => $startTime,
        'end' => date('Y-m-d H:i:s'),
        'sales' => $salesData,
        'totals' => $totalsData
    ];

    require_once '/var/www/html/templates/shift_summary.php';
    exit; 
}

// --- FETCH PRODUCTS (JOIN INVENTORY FOR THIS LOCATION) ---
$sql = "
    SELECT p.*, c.name as category_name, COALESCE(i.quantity, 0) as stock_qty 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = ? 
    ORDER BY p.name ASC
";
$stmt = $pdo->prepare($sql);
$stmt->execute([$locationId]);
$products = $stmt->fetchAll();

$tabsStmt = $pdo->prepare("SELECT id, customer_name, final_total, created_at FROM sales WHERE payment_status = 'pending' AND location_id = ? ORDER BY created_at DESC");
$tabsStmt->execute([$locationId]);
$openTabs = $tabsStmt->fetchAll();
?>
