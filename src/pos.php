<?php
// SECURITY: Logged in users only
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];
$locId  = $_SESSION['location_id'];

// --- AJAX: CHECK READY COUNT ---
if (isset($_GET['ajax_ready_count'])) {
    // Count distinct orders that have items marked as 'ready' for this location
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT si.sale_id) 
        FROM sale_items si 
        JOIN sales s ON si.sale_id = s.id 
        WHERE si.status = 'ready' AND s.location_id = ?
    ");
    $stmt->execute([$locId]);
    echo $stmt->fetchColumn();
    exit; // Stop here, don't load the whole page
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

// 2. INITIALIZE & SELF-HEAL CART
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}
foreach ($_SESSION['cart'] as $pid => $item) {
    if (!is_array($item) || !isset($item['name']) || !isset($item['price']) || !isset($item['qty'])) {
        $_SESSION['cart'] = []; 
        break;
    }
}

// 3. HANDLE ACTIONS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ADD ITEM
    if (isset($_POST['add_item'])) {
        $pid = $_POST['product_id'];
        $name = $_POST['name'];
        $price = floatval($_POST['price']);
        $catId = $_POST['category_id'];
        
        if (isset($_SESSION['cart'][$pid])) {
            $_SESSION['cart'][$pid]['qty']++;
        } else {
            $_SESSION['cart'][$pid] = [
                'name' => $name, 'price' => $price, 'qty' => 1, 'cat_id' => $catId
            ];
        }
    }

    // REMOVE ITEM
    if (isset($_POST['remove_item'])) {
        unset($_SESSION['cart'][$_POST['product_id']]);
    }

    // CLEAR CART
    if (isset($_POST['clear_cart'])) {
        $_SESSION['cart'] = [];
    }

    // CHECKOUT
    if (isset($_POST['checkout'])) {
        if (empty($_SESSION['cart'])) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Cart is empty.";
        } else {
            try {
                $pdo->beginTransaction();
                
                $total = 0;
                foreach ($_SESSION['cart'] as $item) $total += ($item['price'] * $item['qty']);
                
                $payMethod = $_POST['payment_method'];

                // Sale Header
                $sql = "INSERT INTO sales (location_id, user_id, shift_id, total_amount, final_total, payment_method, status, created_at) 
                        VALUES (?, ?, ?, ?, ?, ?, 'completed', NOW())";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$locId, $userId, $shiftId, $total, $total, $payMethod]);
                $saleId = $pdo->lastInsertId();

                // Sale Items & Stock
                $itemStmt = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price_at_sale, status) VALUES (?, ?, ?, ?, ?)");
                $stockStmt = $pdo->prepare("UPDATE location_stock SET quantity = quantity - ? WHERE location_id = ? AND product_id = ?");

                foreach ($_SESSION['cart'] as $pid => $item) {
                    $status = ($item['cat_id'] == 1) ? 'pending' : 'served'; // If Food (Cat 1) -> Pending
                    $itemStmt->execute([$saleId, $pid, $item['qty'], $item['price'], $status]);
                    $stockStmt->execute([$item['qty'], $locId, $pid]);
                }

                $pdo->commit();
                $_SESSION['cart'] = [];
                $_SESSION['last_sale_id'] = $saleId;

                // Redirect to POS (Triggers Modal)
                header("Location: index.php?page=pos");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = "Transaction Failed: " . $e->getMessage();
            }
        }
    }
    
    // Default Redirect if not checkout
    header("Location: index.php?page=pos");
    exit;
}

// 4. FETCH PRODUCTS (Flat List)
$sql = "SELECT p.*, c.name as category 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1 
        ORDER BY p.category_id ASC, p.name ASC";
$products = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); 
?>
