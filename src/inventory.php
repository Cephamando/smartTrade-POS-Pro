<?php
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userId = $_SESSION['user_id'];

// --- 1. HANDLE PRODUCT UPDATE ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_product'])) {
    $invId = $_POST['inventory_id'];
    $newQty = floatval($_POST['quantity']);
    $newPrice = floatval($_POST['price']);
    
    // Get current state for logging
    $stmt = $pdo->prepare("SELECT i.quantity, i.product_id, i.location_id FROM inventory i WHERE i.id = ?");
    $stmt->execute([$invId]);
    $current = $stmt->fetch();
    
    if ($current) {
        $pdo->beginTransaction();
        try {
            // 1. Update Inventory Quantity
            $pdo->prepare("UPDATE inventory SET quantity = ? WHERE id = ?")->execute([$newQty, $invId]);
            
            // 2. Update Product Price (Global)
            $pdo->prepare("UPDATE products SET price = ? WHERE id = ?")->execute([$newPrice, $current['product_id']]);
            
            // 3. Log the Adjustment if quantity changed
            $diff = $newQty - $current['quantity'];
            if ($diff != 0) {
                $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, created_at) VALUES (?, ?, ?, ?, ?, 'adjustment', NOW())")
                    ->execute([$current['product_id'], $current['location_id'], $userId, $diff, $newQty]);
            }
            
            $pdo->commit();
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Product updated successfully.";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Update failed: " . $e->getMessage();
        }
    }
    header("Location: index.php?page=inventory"); exit;
}

// --- 2. FILTERS & SEARCH ---
$locFilter = $_GET['location_id'] ?? '';
$catFilter = $_GET['category_id'] ?? '';
$search = $_GET['search'] ?? '';

// --- 3. BUILD QUERY ---
$sql = "SELECT i.id as inventory_id, i.quantity, i.location_id,
               p.id as product_id, p.name as product_name, p.sku, p.price, p.unit, 
               c.name as category_name, 
               l.name as location_name, 
               (i.quantity * p.price) as stock_value 
        FROM inventory i 
        JOIN products p ON i.product_id = p.id 
        JOIN locations l ON i.location_id = l.id 
        LEFT JOIN categories c ON p.category_id = c.id 
        WHERE 1=1";

$params = [];

if ($locFilter) { $sql .= " AND i.location_id = ?"; $params[] = $locFilter; }
if ($catFilter) { $sql .= " AND p.category_id = ?"; $params[] = $catFilter; }
if ($search) {
    $sql .= " AND (p.name LIKE ? OR p.sku LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$sql .= " ORDER BY p.name ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$inventory = $stmt->fetchAll();

// Fetch Data for Dropdowns
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Calculate Total Value
$totalValue = 0;
foreach ($inventory as $item) $totalValue += $item['stock_value'];
?>
