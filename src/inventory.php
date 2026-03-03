<?php
// SECURITY: Allow Admins, Managers, Devs, AND Chefs
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev', 'chef', 'head_chef'], true)) {
    header("Location: index.php?page=dashboard");
    exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// AUTO-PATCH: Ensure the inventory_logs table has a 'reason' column
try {
    $pdo->exec("ALTER TABLE inventory_logs ADD COLUMN reason VARCHAR(255) DEFAULT NULL");
} catch(Exception $e) { /* Column already exists, ignore */ }

// --------------------------------------------------
// FILTER INPUTS
// --------------------------------------------------
$locFilter = $_GET['location_id'] ?? '';
$catFilter = $_GET['category_id'] ?? '';
$search    = trim($_GET['search'] ?? '');

// --------------------------------------------------
// HANDLE BULK STOCK TAKE (Physical Count)
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_stock_take'])) {
    if (in_array($_SESSION['role'], ['chef', 'head_chef'])) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg']  = 'Access Denied: Chefs have View-Only access to stock levels.';
        header("Location: index.php?page=inventory");
        exit;
    }

    try {
        $pdo->beginTransaction();
        $invIds = $_POST['inv_id'] ?? [];
        $physQtys = $_POST['physical_qty'] ?? [];
        $reasons = $_POST['reason'] ?? [];
        $prodIds = $_POST['product_id'] ?? [];
        $locIds = $_POST['location_id'] ?? [];
        $sysQtys = $_POST['sys_qty'] ?? [];
        $userId = $_SESSION['user_id'] ?? 1;
        $processedCount = 0;

        for ($i = 0; $i < count($invIds); $i++) {
            if ($physQtys[$i] === '' || $physQtys[$i] === null) continue; // Skip empty rows
            
            $physQty = (float)$physQtys[$i];
            $sysQty = (float)$sysQtys[$i];
            $variance = $physQty - $sysQty;

            // Only update if there is an actual variance
            if ($variance != 0) {
                $invId = (int)$invIds[$i];
                $prodId = (int)$prodIds[$i];
                $locId = (int)$locIds[$i];
                $reason = trim($reasons[$i] ?? 'Physical Count Adjustment');

                // Update physical stock
                $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?")->execute([$physQty, $invId]);

                // Log the variance
                $stmt = $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reason, created_at) VALUES (?, ?, ?, ?, ?, 'stock_take', ?, NOW())");
                $stmt->execute([$prodId, $locId, $userId, $variance, $physQty, $reason]);
                $processedCount++;
            }
        }
        $pdo->commit();
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg']  = "Stock Take Complete! $processedCount items updated.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg']  = 'Database Error: ' . $e->getMessage();
    }
    header("Location: index.php?page=inventory");
    exit;
}

// --------------------------------------------------
// HANDLE SINGLE INVENTORY UPDATE
// --------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    if (in_array($_SESSION['role'], ['chef', 'head_chef'])) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg']  = 'Access Denied.';
        header("Location: index.php?page=inventory");
        exit;
    }
    try {
        $invId   = (int)$_POST['inventory_id'];
        $qty     = (float)$_POST['quantity'];
        $price   = (float)$_POST['price'];

        $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?")->execute([$qty, $invId]);
        $pdo->prepare("UPDATE products p JOIN inventory i ON i.product_id = p.id SET p.price = ? WHERE i.id = ?")->execute([$price, $invId]);

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
        i.location_id, i.id AS inventory_id, p.id AS product_id, p.name AS product_name, p.sku, p.price, p.cost_price, p.category_id,
        c.name AS category_name, l.name AS location_name, i.quantity, (i.quantity * p.price) AS stock_value
    FROM inventory i
    JOIN products p ON p.id = i.product_id
    JOIN locations l ON l.id = i.location_id
    LEFT JOIN categories c ON c.id = p.category_id
    WHERE 1=1
";

$params = [];
if ($locFilter !== '') { $sql .= " AND i.location_id = ?"; $params[] = $locFilter; }
if ($catFilter !== '') { $sql .= " AND p.category_id = ?"; $params[] = $catFilter; }
if ($search !== '') { $sql .= " AND (p.name LIKE ? OR p.sku LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }

$sql .= " ORDER BY p.name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventory = $stmt->fetchAll(PDO::FETCH_ASSOC);

$totalValue = 0.0;
foreach ($inventory as $item) { $totalValue += (float)$item['stock_value']; }
?>