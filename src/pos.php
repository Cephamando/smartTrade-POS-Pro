<?php
// SECURITY: Cashiers, Managers, Admins Only
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }
if (!in_array($_SESSION['role'], ['cashier', 'shopkeeper', 'bartender', 'manager', 'admin', 'dev'])) {
    $_SESSION['swal_type'] = 'error';
    $_SESSION['swal_msg'] = "Access Denied: POS is for Cashiers only.";
    header("Location: index.php?page=dashboard");
    exit;
}

$userId = $_SESSION['user_id'];
$locId  = $_SESSION['location_id'];

// --- AJAX: CHECK READY COUNT ---
if (isset($_GET['ajax_ready_count'])) {
    $stmt = $pdo->prepare("SELECT COUNT(DISTINCT si.sale_id) FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE si.status = 'ready' AND s.location_id = ?");
    $stmt->execute([$locId]);
    echo $stmt->fetchColumn();
    exit; 
}

// 1. CHECK FOR OPEN SHIFT
$stmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open' LIMIT 1");
$stmt->execute([$userId]);
$shift = $stmt->fetch();
if (!$shift) {
    $_SESSION['swal_type'] = 'warning';
    $_SESSION['swal_msg'] = 'You must Clock In before making sales.';
    header("Location: index.php?page=shifts");
    exit;
}
$shiftId = $shift['id'];

// 2. SELF-HEAL CART
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) { $_SESSION['cart'] = []; }

// 3. HANDLE ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ADD ITEM (WITH STOCK CHECK)
    if (isset($_POST['add_item'])) {
        $pid = $_POST['product_id'];
        
        // A. FETCH PRODUCT DETAILS & CURRENT STOCK
        $stmt = $pdo->prepare("
            SELECT p.id, p.name, p.price, p.category_id, c.name as category_name,
                   COALESCE(ls.quantity, 0) as current_stock
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN location_stock ls ON (p.id = ls.product_id AND ls.location_id = ?)
            WHERE p.id = ?
        ");
        $stmt->execute([$locId, $pid]);
        $prod = $stmt->fetch();

        if ($prod) {
            // B. CALCULATE REQUESTED QTY
            $currentCartQty = isset($_SESSION['cart'][$pid]) ? $_SESSION['cart'][$pid]['qty'] : 0;
            $newQty = $currentCartQty + 1;

            // C. VALIDATE STOCK
            if ($newQty > $prod['current_stock']) {
                $_SESSION['swal_type'] = 'warning';
                // HTML in SweetAlert for the Requisition Link
                $_SESSION['swal_msg'] = "Out of Stock! Available: " . floatval($prod['current_stock']) . ". <br><br>Please create a <a href='index.php?page=transfers' style='color: #0d6efd; font-weight: bold;'>Stock Requisition</a>.";
            } else {
                // D. ADD TO CART
                if (isset($_SESSION['cart'][$pid])) {
                    $_SESSION['cart'][$pid]['qty']++;
                } else {
                    $_SESSION['cart'][$pid] = [
                        'name' => $prod['name'], 
                        'price' => floatval($prod['price']), 
                        'qty' => 1, 
                        'cat_id' => $prod['category_id'],
                        'cat_name' => $prod['category_name']
                    ];
                }
            }
        }
    }

    // REMOVE & CLEAR ITEMS
    if (isset($_POST['remove_item'])) unset($_SESSION['cart'][$_POST['product_id']]);
    if (isset($_POST['clear_cart'])) $_SESSION['cart'] = [];

    // CHECKOUT
    if (isset($_POST['checkout'])) {
        if (empty($_SESSION['cart'])) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Cart is empty.";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Final Stock Re-Check (Race condition prevention)
                foreach ($_SESSION['cart'] as $pid => $item) {
                    $chk = $pdo->prepare("SELECT quantity FROM location_stock WHERE location_id = ? AND product_id = ?");
                    $chk->execute([$locId, $pid]);
                    $avail = $chk->fetchColumn() ?: 0;
                    if ($avail < $item['qty']) {
                        throw new Exception("Stock changed for {$item['name']}. Only $avail remaining.");
                    }
                }

                $total = 0;
                foreach ($_SESSION['cart'] as $item) $total += ($item['price'] * $item['qty']);
                $payMethod = $_POST['payment_method'];

                $sql = "INSERT INTO sales (location_id, user_id, shift_id, total_amount, final_total, payment_method, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$locId, $userId, $shiftId, $total, $total, $payMethod]);
                $saleId = $pdo->lastInsertId();

                $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status) VALUES (?, ?, ?, ?, ?)");
                $stockStmt = $pdo->prepare("UPDATE location_stock SET quantity = quantity - ? WHERE location_id = ? AND product_id = ?");

                $kitchenCats = ['meal', 'meals', 'food', 'snack', 'snacks', 'starter', 'starters', 'kitchen', 'dessert', 'main course'];

                foreach ($_SESSION['cart'] as $pid => $item) {
                    $catCheck = strtolower(trim($item['cat_name'] ?? ''));
                    $status = in_array($catCheck, $kitchenCats) ? 'pending' : 'served';
                    
                    $itemStmt->execute([$saleId, $pid, $item['qty'], $item['price'], $status]);
                    $stockStmt->execute([$item['qty'], $locId, $pid]);
                }

                $pdo->commit();
                $_SESSION['cart'] = [];
                $_SESSION['last_sale_id'] = $saleId;
                
                header("Location: index.php?page=pos");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = "Transaction Failed: " . $e->getMessage();
            }
        }
    }
    header("Location: index.php?page=pos");
    exit;
}

// 4. FETCH PRODUCTS FOR GRID
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1 AND p.price > 0
        AND (c.name IS NULL OR c.name NOT IN ('Ingredients', 'Raw Materials'))
        ORDER BY p.category_id ASC, p.name ASC";
$products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); 
?>
