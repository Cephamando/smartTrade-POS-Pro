<?php
// SECURITY: Strictly Chefs Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['chef', 'head_chef', 'admin', 'dev'])) {
    $_SESSION['swal_type'] = 'error';
    $_SESSION['swal_msg'] = "Access Denied. Only Chefs can manage the Menu.";
    header("Location: index.php?page=dashboard");
    exit;
}

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD NEW DISH
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name']);
        $catId = $_POST['category_id'];
        $price = floatval($_POST['price']);

        if (empty($name) || $price < 0) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Invalid Name or Price.";
        } else {
            // Default stock to 1000 for non-tracked items or 0
            // For a restaurant, 'quantity' in location_stock matters, but here we define the catalog.
            // We insert into products. Location stock is managed in Transfers/Receiving.
            $stmt = $pdo->prepare("INSERT INTO products (name, category_id, price, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$name, $catId, $price]);
            
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Dish '$name' added to Menu.";
        }
    }

    // 2. EDIT DISH
    if (isset($_POST['edit_product'])) {
        $id = $_POST['product_id'];
        $name = trim($_POST['name']);
        $catId = $_POST['category_id'];
        $price = floatval($_POST['price']);
        
        $stmt = $pdo->prepare("UPDATE products SET name = ?, category_id = ?, price = ? WHERE id = ?");
        $stmt->execute([$name, $catId, $price, $id]);
        
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Menu item updated.";
    }

    // 3. TOGGLE AVAILABILITY (The "86" Button)
    if (isset($_POST['toggle_status'])) {
        $id = $_POST['product_id'];
        $currentStatus = $_POST['current_status'];
        $newStatus = ($currentStatus == 1) ? 0 : 1;
        
        $stmt = $pdo->prepare("UPDATE products SET is_active = ? WHERE id = ?");
        $stmt->execute([$newStatus, $id]);
        
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = ($newStatus == 1) ? "Item is now Available." : "Item removed from POS.";
    }

    header("Location: index.php?page=menu");
    exit;
}

// --- FETCH DATA ---
$products = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    ORDER BY c.name ASC, p.name ASC
")->fetchAll();

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>
