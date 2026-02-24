<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'manager', 'dev', 'chef', 'head_chef'])) {
    die("<h1>Access Denied</h1><p>Only kitchen staff and managers can access Production.</p>");
}

$locationId = $_SESSION['location_id'] ?? 0;

// Handle Batch Production Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['produce_item'])) {
    $productId = (int)$_POST['product_id'];
    $qty = (float)$_POST['quantity'];
    $targetLocId = (int)$_POST['target_location_id']; // The POS workstation getting the food
    
    if ($qty > 0 && $targetLocId > 0) {
        try {
            $pdo->beginTransaction();
            
            // Add to the Target POS inventory so the cashier can see it immediately
            $stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE product_id = ? AND location_id = ?");
            $stmt->execute([$productId, $targetLocId]);
            $inv = $stmt->fetch();
            
            if ($inv) {
                $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE id = ?")->execute([$qty, $inv['id']]);
                $newQty = $inv['quantity'] + $qty;
            } else {
                $pdo->prepare("INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?)")->execute([$productId, $targetLocId, $qty]);
                $newQty = $qty;
            }
            
            // Log the production action mapped to the target location
            $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type) VALUES (?, ?, ?, ?, ?, 'produce')")
                ->execute([$productId, $targetLocId, $_SESSION['user_id'], $qty, $newQty]);
                
            $pdo->commit();
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Batch sent to POS!";
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Database Error: " . $e->getMessage();
        }
    }
    header("Location: index.php?page=kitchen");
    exit;
}

// Fetch all F&B items
$stmt = $pdo->prepare("
    SELECT p.*, c.name as cat_name
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE c.type IN ('food', 'meal') AND p.is_active = 1
    ORDER BY p.name ASC
");
$stmt->execute();
$meals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch locations where cashiers actually sell from
$sellableLocs = $pdo->query("SELECT id, name FROM locations WHERE can_sell = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
