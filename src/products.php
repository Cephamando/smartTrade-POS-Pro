<?php
// SECURITY: Only Managers/Admins/Devs
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// Ensure we have a location to attach stock to
$userLocId = $_SESSION['location_id'] ?? null;
if (!$userLocId) {
    die("Error: User location not set.");
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
            $catId = !empty($_POST['category_id']) ? $_POST['category_id'] : null;
            $price = !empty($_POST['price']) ? $_POST['price'] : 0;
            $cost = !empty($_POST['cost_price']) ? $_POST['cost_price'] : 0;
            $unit = !empty($_POST['unit']) ? $_POST['unit'] : 'unit';
            
            // Safe SKU handling
            $skuRaw = $_POST['sku'] ?? '';
            $sku = trim($skuRaw);
            $sku = $sku === '' ? null : $sku; // Convert empty string to NULL for DB unique constraint
            
            // Basic Duplicate Check on Name
            $check = $pdo->prepare("SELECT id FROM products WHERE name = ?");
            $check->execute([$name]);
            if ($check->rowCount() > 0) throw new Exception("A product with this name already exists.");

            // Use Transaction for Data Integrity
            $pdo->beginTransaction();

            // A. Insert Product
            $sql = "INSERT INTO products (name, category_id, price, cost_price, unit, sku) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$name, $catId, $price, $cost, $unit, $sku]);
            
            $newProdId = $pdo->lastInsertId();

            // B. Initialize Stock for Current Location (Crucial step!)
            $stockSql = "INSERT INTO location_stock (location_id, product_id, quantity) VALUES (?, ?, 0)";
            $stockStmt = $pdo->prepare($stockSql);
            $stockStmt->execute([$userLocId, $newProdId]);

            $pdo->commit();

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Product '$name' saved successfully.";
        }

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = $e->getMessage();
    }

    // PRG Redirect
    header("Location: index.php?page=products");
    exit;
}

// FETCH DATA FOR VIEW
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Fetch products with their category names
$sql = "SELECT p.*, c.name as category_name 
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        ORDER BY p.name ASC";
$products = $pdo->query($sql)->fetchAll();
?>