<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// Ensure only authorized staff can access
if (!in_array($_SESSION['role'], ['admin', 'manager', 'dev', 'chef', 'head_chef'])) { 
    die("Access Denied: Kitchen & Menu Management Only."); 
}

$userId = $_SESSION['user_id'];

// Fetch categories for food/meals
$foodCategories = $pdo->query("SELECT * FROM categories WHERE type IN ('food', 'meal')")->fetchAll();
// Fetch sellable locations (where meals will be sold)
$sellableLocations = $pdo->query("SELECT * FROM locations WHERE can_sell = 1 ORDER BY name ASC")->fetchAll();

$targetLocId = $_GET['loc'] ?? ($sellableLocations[0]['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ACTION: Create New Meal
    if (isset($_POST['add_meal'])) {
        $name = trim($_POST['name']);
        $price = floatval($_POST['price']);
        $catId = intval($_POST['category_id']);
        $qty = intval($_POST['initial_qty']);
        $targetLoc = intval($_POST['target_location']);
        
        $pdo->beginTransaction();
        try {
            // Insert Product
            $stmt = $pdo->prepare("INSERT INTO products (name, price, category_id, type, is_active) VALUES (?, ?, ?, 'item', 1)");
            $stmt->execute([$name, $price, $catId]);
            $pid = $pdo->lastInsertId();
            
            // Initialize stock for all locations to 0
            $locs = $pdo->query("SELECT id FROM locations")->fetchAll(PDO::FETCH_COLUMN);
            foreach($locs as $lid) {
                $pdo->prepare("INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, 0)")->execute([$pid, $lid]);
            }

            // Set specific quantity for the chosen selling location
            if ($qty > 0) {
                $pdo->prepare("UPDATE inventory SET quantity = ? WHERE product_id = ? AND location_id = ?")->execute([$qty, $pid, $targetLoc]);
            }
            
            $pdo->commit();
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Meal Added to Menu.";
        } catch(Exception $e) {
            $pdo->rollBack();
            $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
        }
        header("Location: index.php?page=menu&loc=" . $targetLoc); exit;
    }

    // ACTION: Set Meal Quantity
    if (isset($_POST['update_stock'])) {
        $pid = intval($_POST['product_id']);
        $qty = intval($_POST['quantity']);
        $targetLoc = intval($_POST['target_location']);

        // Update Inventory directly
        $stmt = $pdo->prepare("INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = ?");
        $stmt->execute([$pid, $targetLoc, $qty, $qty]);
        
        // Log the manual update for auditing
        $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, created_at) VALUES (?,?,?,?,?, 'menu_update', NOW())")
            ->execute([$pid, $targetLoc, $userId, 0, $qty]); 

        $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Portions Updated.";
        header("Location: index.php?page=menu&loc=" . $targetLoc); exit;
    }
}

// Fetch all food/meal products and their stock at the target location
$meals = [];
if ($targetLocId) {
    $stmt = $pdo->prepare("
        SELECT p.*, c.name as category_name, COALESCE(i.quantity, 0) as stock_qty 
        FROM products p 
        JOIN categories c ON p.category_id = c.id 
        LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = ?
        WHERE c.type IN ('food', 'meal') AND p.is_active = 1
        ORDER BY c.name ASC, p.name ASC
    ");
    $stmt->execute([$targetLocId]);
    $meals = $stmt->fetchAll();
}
?>
