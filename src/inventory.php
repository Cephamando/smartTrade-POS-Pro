<?php
// SECURITY: Only Managers/Admins/Dev
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'], true)) {
    header("Location: index.php?page=dashboard");
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// --------------------------------------------------
// FILTER INPUTS
// --------------------------------------------------
$locFilter = $_GET['location_id'] ?? '';
$catFilter = $_GET['category_id'] ?? '';
$search    = trim($_GET['search'] ?? '');

// --------------------------------------------------
// HANDLE INVENTORY UPDATE
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    try {
        $invId   = (int)$_POST['inventory_id'];
        $qty     = (float)$_POST['quantity'];
        $price   = (float)$_POST['price'];

        $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?")
            ->execute([$qty, $invId]);

        $pdo->prepare("
            UPDATE products p
            JOIN inventory i ON i.product_id = p.id
            SET p.price = ?
            WHERE i.id = ?
        ")->execute([$price, $invId]);

        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg']  = 'Inventory updated successfully.';
    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg']  = $e->getMessage();
    }

    header("Location: index.php?page=inventory");
    exit;
}

// --------------------------------------------------
// FETCH FILTER DATA
// --------------------------------------------------
$locations  = $pdo->query("SELECT id, name FROM locations ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// BUILD INVENTORY QUERY
// --------------------------------------------------
$sql = "
    SELECT 
        i.id AS inventory_id,
        p.id AS product_id,
        p.name AS product_name,
        p.sku,
        p.price,
        p.cost_price,
        p.category_id,
        c.name AS category_name,
        l.name AS location_name,
        i.quantity,
        (i.quantity * p.cost_price) AS stock_value
    FROM inventory i
    JOIN products p ON p.id = i.product_id
    JOIN locations l ON l.id = i.location_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE 1=1
";


$params = [];

if ($locFilter !== '') {
    $sql .= " AND i.location_id = ?";
    $params[] = $locFilter;
}

if ($catFilter !== '') {
    $sql .= " AND p.category_id = ?";
    $params[] = $catFilter;
}

if ($search !== '') {
    $sql .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.name ASC";


$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --------------------------------------------------
// CALCULATE TOTAL STOCK VALUE
// --------------------------------------------------
$totalValue = 0.0;

foreach ($inventory as $item) {
    $totalValue += (float)$item['stock_value'];
}
