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

        if ($name) {
            // Check Duplicate
            $check = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
            $check->execute([$name]);
            
            if ($check->rowCount() > 0) {
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = "Category '$name' already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO categories (name) VALUES (?)");
                $stmt->execute([$name]);
                
                $_SESSION['swal_type'] = 'success';
                $_SESSION['swal_msg'] = "Category '$name' created.";
            }
        }
    }

    // 2. DELETE CATEGORY
    if (isset($_POST['delete_category'])) {
        $id = $_POST['category_id'];
        
        // Safety Check: Don't delete if products exist
        $check = $pdo->prepare("SELECT id FROM products WHERE category_id = ? LIMIT 1");
        $check->execute([$id]);
        
        if ($check->rowCount() > 0) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Cannot delete: Products are linked to this category.";
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
$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
?>
