<?php
// SECURITY: Only Managers/Admins
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// HANDLE POST REQUESTS
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // 1. ADD CATEGORY
        if (isset($_POST['add_category'])) {
            $name = trim($_POST['category_name']);
            if (empty($name)) throw new Exception("Category Name is required");

            $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
            $stmt->execute([$name]);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Category '$name' created!";
        }

        // 2. ADD/EDIT PRODUCT
        if (isset($_POST['save_product'])) {
            $name = trim($_POST['name']);
            $catId = $_POST['category_id'] ?: null;
            $price = $_POST['price'] ?: 0;
            $cost = $_POST['cost_price'] ?: 0;
            $unit = $_POST['unit'] ?: 'unit';
            $sku  = trim($_POST['sku']) ?: null; // Optional SKU
            
            // Basic Duplicate Check on Name
            $check = $pdo->prepare("SELECT id FROM products WHERE name = ?");
            $check->execute([$name]);
            if ($check->rowCount() > 0) throw new Exception("A product with this name already exists.");

            $sql = "INSERT INTO products (name, category_id, price, cost_price, unit, sku) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $catId, $price, $cost, $unit, $sku]);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Product '$name' saved successfully.";
        }

    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = $e->getMessage();
    }

    // PRG Redirect
    header("Location: index.php?page=products");
    exit;
}

// FETCH DATA FOR VIEW
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.name ASC";
$products = $pdo->query($sql)->fetchAll();
?>