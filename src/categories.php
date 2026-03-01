<?php
// SECURITY: Managers/Admins Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD CATEGORY
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $type = $_POST['type'] ?? 'other';
        $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;

        if ($name) {
            $check = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $check->execute([$name]);
            if ($check->rowCount() > 0) {
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = "Category '$name' already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name, type, parent_id) VALUES (?, ?, ?)");
                $stmt->execute([$name, $type, $parent_id]);
                $_SESSION['swal_type'] = 'success';
                $_SESSION['swal_msg'] = "Category '$name' created.";
            }
        }
    }

    // 2. EDIT CATEGORY
    if (isset($_POST['edit_category'])) {
        $id = $_POST['category_id'];
        $name = trim($_POST['name']);
        $type = $_POST['type'] ?? 'other';
        $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;

        // Prevent a category from being set as its own parent
        if ($id == $parent_id) { $parent_id = null; }

        if ($name) {
            $check = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
            $check->execute([$name, $id]);
            
            if ($check->rowCount() > 0) {
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = "Category name '$name' already in use.";
            } else {
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, type = ?, parent_id = ? WHERE id = ?");
                $stmt->execute([$name, $type, $parent_id, $id]);
                $_SESSION['swal_type'] = 'success';
                $_SESSION['swal_msg'] = "Category updated.";
            }
        }
    }

    // 3. DELETE CATEGORY
    if (isset($_POST['delete_category'])) {
        $id = $_POST['category_id'];
        
        $checkProds = $pdo->prepare("SELECT id FROM products WHERE category_id = ? LIMIT 1");
        $checkProds->execute([$id]);
        
        $checkSubs = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ? LIMIT 1");
        $checkSubs->execute([$id]);

        if ($checkProds->rowCount() > 0) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Cannot delete: Products are linked to this category.";
        } elseif ($checkSubs->rowCount() > 0) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Cannot delete: This category contains subcategories.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Category deleted.";
        }
    }

    header("Location: index.php?page=categories");
    exit;
}

// --- FETCH DATA ---
$categories = $pdo->query("
    SELECT c.*, p.name as parent_name 
    FROM categories c 
    LEFT JOIN categories p ON c.parent_id = p.id 
    ORDER BY c.parent_id IS NOT NULL, p.name ASC, c.name ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Master Categories for the dropdowns
$parents = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
