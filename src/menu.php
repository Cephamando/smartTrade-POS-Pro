<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

$userRole = $_SESSION['role'] ?? '';
if (!in_array($userRole, ['admin', 'manager', 'dev', 'head_chef'])) {
    die("<h1>Access Denied</h1><p>Only Head Chefs and Management can edit the Menu.</p>");
}

// 1. ADD/EDIT MENU BLUEPRINT
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
            $pdo->prepare("INSERT INTO products (name, price, cost_price, category_id, type, is_active) VALUES (?, ?, ?, ?, 'item', 1)")->execute([$name, $price, $cost, $catId]);
            $_SESSION['swal_msg'] = "Menu item created.";
        }
        $_SESSION['swal_type'] = 'success';
    } catch (Exception $e) {
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
    }
    header("Location: index.php?page=menu"); exit;
}

// 2. SAVE RECIPE (BILL OF MATERIALS)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_recipe'])) {
    $parentId = (int)$_POST['parent_product_id'];
    $ingIds = $_POST['ingredient_id'] ?? [];
    $qtys = $_POST['ingredient_qty'] ?? [];

    try {
        $pdo->beginTransaction();
        // Clear old recipe
        $pdo->prepare("DELETE FROM product_recipes WHERE parent_product_id = ?")->execute([$parentId]);
        
        // Insert new ingredients
        $insertStmt = $pdo->prepare("INSERT INTO product_recipes (parent_product_id, ingredient_product_id, quantity) VALUES (?, ?, ?)");
        for ($i = 0; $i < count($ingIds); $i++) {
            $iId = (int)$ingIds[$i];
            $qty = (float)$qtys[$i];
            if ($iId > 0 && $qty > 0) {
                $insertStmt->execute([$parentId, $iId, $qty]);
            }
        }
        $pdo->commit();
        $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Recipe saved successfully.";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Database Error: " . $e->getMessage();
    }
    header("Location: index.php?page=menu"); exit;
}

// 3. DELETE (DEACTIVATE) ITEM
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $pdo->prepare("UPDATE products SET is_active = 0 WHERE id = ?")->execute([$_POST['item_id']]);
    $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "Item removed from menu.";
    header("Location: index.php?page=menu"); exit;
}

// FETCH DATA FOR THE UI
// Expanded to include drinks and cocktails for the bartender workflow
$foodCategories = $pdo->query("SELECT * FROM categories WHERE type IN ('food', 'meal', 'drink', 'beverage', 'cocktail') ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

$menuItems = $pdo->query("
    SELECT p.*, c.name as cat_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    WHERE c.type IN ('food', 'meal', 'drink', 'beverage', 'cocktail') AND p.is_active = 1 
    ORDER BY c.name ASC, p.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active products to act as potential raw ingredients
$allIngredients = $pdo->query("SELECT id, name, unit FROM products WHERE is_active = 1 ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch existing recipes and group them by parent ID so JS can load them instantly
$recipesRaw = $pdo->query("SELECT pr.parent_product_id, pr.ingredient_product_id, pr.quantity, p.name as ingredient_name, p.unit FROM product_recipes pr JOIN products p ON pr.ingredient_product_id = p.id")->fetchAll(PDO::FETCH_ASSOC);
$recipesGrouped = [];
foreach($recipesRaw as $r) {
    $recipesGrouped[$r['parent_product_id']][] = $r;
}
?>
