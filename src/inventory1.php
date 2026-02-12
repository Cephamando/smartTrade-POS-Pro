<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// --- 1. PRODUCT ACTIONS (RESTORED) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $id = $_POST['product_id'] ?? '';
    $name = trim($_POST['name']);
    $sku = trim($_POST['sku']);
    $price = floatval($_POST['price']);
    $cost = floatval($_POST['cost']);
    $catId = $_POST['category_id'];
    // Default to 'item' if not specified
    $type = $_POST['type'] ?? 'item'; 
    $isOpen = isset($_POST['is_open_price']) ? 1 : 0;

    try {
        if ($id) {
            // Update Existing
            $pdo->prepare("UPDATE products SET name=?, sku=?, price=?, cost_price=?, category_id=?, type=?, is_open_price=? WHERE id=?")
                ->execute([$name, $sku, $price, $cost, $catId, $type, $isOpen, $id]);
            $_SESSION['swal_msg'] = "Product updated successfully.";
        } else {
            // Create New
            $pdo->prepare("INSERT INTO products (name, sku, price, cost_price, category_id, type, is_open_price, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, 1)")
                ->execute([$name, $sku, $price, $cost, $catId, $type, $isOpen]);
            $_SESSION['swal_msg'] = "Product created successfully.";
        }
        $_SESSION['swal_type'] = 'success';
    } catch (PDOException $e) {
        $_SESSION['swal_type'] = 'error'; 
        $_SESSION['swal_msg'] = "Database Error: " . $e->getMessage();
    }
    header("Location: index.php?page=inventory"); exit;
}

// --- 2. VENDOR ACTIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vendor'])) {
    $id = $_POST['vendor_id'] ?? '';
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact_person']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);

    try {
        if ($id) {
            $pdo->prepare("UPDATE vendors SET name=?, contact_person=?, phone=?, email=? WHERE id=?")
                ->execute([$name, $contact, $phone, $email, $id]);
            $_SESSION['swal_msg'] = "Vendor updated.";
        } else {
            $pdo->prepare("INSERT INTO vendors (name, contact_person, phone, email, is_active) VALUES (?, ?, ?, ?, 1)")
                ->execute([$name, $contact, $phone, $email]);
            $_SESSION['swal_msg'] = "Vendor added.";
        }
        $_SESSION['swal_type'] = 'success';
    } catch (PDOException $e) { 
        $_SESSION['swal_type'] = 'error'; 
        $_SESSION['swal_msg'] = "Error: " . $e->getMessage(); 
    }
    header("Location: index.php?page=inventory&tab=vendors"); exit;
}

// --- 3. DELETE VENDOR ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_vendor'])) {
    $id = $_POST['vendor_id'];
    $pdo->prepare("UPDATE vendors SET is_active = 0 WHERE id = ?")->execute([$id]);
    $_SESSION['swal_type'] = 'success'; 
    $_SESSION['swal_msg'] = "Vendor removed.";
    header("Location: index.php?page=inventory&tab=vendors"); exit;
}

// --- 4. FETCH DATA & FILTERS ---
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
$vendors = $pdo->query("SELECT * FROM vendors WHERE is_active = 1 ORDER BY name ASC")->fetchAll();

// Filter Inputs
$locFilter = $_GET['loc'] ?? '';
$catFilter = $_GET['cat'] ?? '';
$search    = $_GET['q'] ?? '';

// Build Product Query
$params = [];
$sql = "SELECT p.*, c.name as category_name,
        (SELECT COALESCE(SUM(quantity), 0) FROM inventory WHERE product_id = p.id " . ($locFilter ? "AND location_id = :loc1" : "") . ") as stock_qty
        FROM products p 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE p.is_active = 1";

if ($locFilter) $params['loc1'] = $locFilter;

if ($catFilter) {
    $sql .= " AND p.category_id = :cat";
    $params['cat'] = $catFilter;
}

if ($search) {
    $sql .= " AND (p.name LIKE :search OR p.sku LIKE :search)";
    $params['search'] = "%$search%";
}

$sql .= " ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

// --- 5. CALCULATE TOTAL VALUE ---
$totalStockValue = 0;
foreach ($products as $p) {
    $val = floatval($p['stock_qty']) * floatval($p['cost_price']);
    $totalStockValue += $val;
}
?>
