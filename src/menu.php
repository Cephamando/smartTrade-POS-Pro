<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'manager', 'dev', 'head_chef'])) {
    die("<h1>Access Denied</h1><p>Only Head Chefs and Management can edit the Menu.</p>");
}

// Add or Edit Menu Item Blueprint
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_menu_item'])) {
    $id = $_POST['item_id'] ?? '';
    $name = trim($_POST['name']);
    $price = floatval($_POST['price']);
    $cost = floatval($_POST['cost_price'] ?? 0);
    $catId = (int)$_POST['category_id'];
    
    try {
        if ($id) {
            $pdo->prepare("UPDATE products SET name=?, price=?, cost_price=?, category_id=? WHERE id=?")->execute([$name, $price, $cost, $catId, $id]);
            $_SESSION['swal_msg'] = "Menu item updated.";
        } else {
            // STRICT RULE: Only creates the product. Does NOT create inventory. The Chef must use "Produce" for that.
            $pdo->prepare("INSERT INTO products (name, price, cost_price, category_id, type, is_active) VALUES (?, ?, ?, ?, 'item', 1)")->execute([$name, $price, $cost, $catId]);
            $_SESSION['swal_msg'] = "Menu item created. (Stock is currently 0. Use the Produce screen to make it sellable).";
        }
        $_SESSION['swal_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
    }
    header("Location: index.php?page=menu");
    exit;
}

// Delete (Deactivate) Menu Item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?")->execute([$_POST['item_id']]);
    $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Item removed from menu.";
    header("Location: index.php?page=menu");
    exit;
}

// Fetch only Food/Meal Categories for the dropdown
$foodCategories = $pdo->query("SELECT * FROM categories WHERE type IN ('food', 'meal') ORDER BY name ASC")->fetchAll();

// Fetch Menu Items
$menuItems = $pdo->query("
    SELECT p.*, c.name as cat_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE c.type IN ('food', 'meal') AND p.is_active = 1 
    ORDER BY c.name ASC, p.name ASC
")->fetchAll();
?>
