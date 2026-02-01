<?php
// SECURITY: Strictly Chefs/Admins
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['chef', 'head_chef', 'admin', 'dev'])) {
    $_SESSION['swal_type'] = 'error';
    $_SESSION['swal_msg'] = "Access Denied. Only Chefs can manage the Menu.";
    header("Location: index.php?page=dashboard");
    exit;
}

// --- HANDLE ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_product'])) {
        $name = trim($_POST['name']);
        $catId = $_POST['category_id'];
        $price = floatval($_POST['price']);

        if (empty($name) || $price < 0) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Invalid Name or Price.";
        } else {
            $stmt = $pdo->prepare("INSERT INTO products (name, category_id, price, is_active) VALUES (?, ?, ?, 1)");
            $stmt->execute([$name, $catId, $price]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Dish '$name' added to Kitchen Menu.";
        }
    }

    if (isset($_POST['edit_product'])) {
        $id = $_POST['product_id'];
        $name = trim($_POST['name']);
        $catId = $_POST['category_id'];
        $price = floatval($_POST['price']);
        $pdo->prepare("UPDATE products SET name = ?, category_id = ?, price = ? WHERE id = ?")->execute([$name, $catId, $price, $id]);
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Menu item updated.";
    }

    if (isset($_POST['toggle_status'])) {
        $id = $_POST['product_id'];
        $newStatus = ($_POST['current_status'] == 1) ? 0 : 1;
        $pdo->prepare("UPDATE products SET is_active = ? WHERE id = ?")->execute([$newStatus, $id]);
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = ($newStatus == 1) ? "Item is now Available." : "Item removed from POS (86'd).";
    }

    header("Location: index.php?page=menu");
    exit;
}

// --- FETCH DATA FILTERED BY DB ENUM TYPE ---
$categories = $pdo->query("SELECT * FROM categories WHERE type IN ('food', 'meal') ORDER BY name ASC")->fetchAll();

$products = $pdo->query("
    SELECT p.*, c.name as category_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE c.type IN ('food', 'meal')
    ORDER BY c.name ASC, p.name ASC
")->fetchAll();
?>
