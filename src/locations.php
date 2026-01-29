<?php
// SECURITY: Admins Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD LOCATION
    if (isset($_POST['add_location'])) {
        $name = trim($_POST['name']);
        $type = $_POST['type']; // 'store' or 'warehouse'
        $address = trim($_POST['address']);

        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO locations (name, type, address) VALUES (?, ?, ?)");
            $stmt->execute([$name, $type, $address]);
            
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Location '$name' created successfully.";
        }
    }

    // 2. DELETE LOCATION
    if (isset($_POST['delete_location'])) {
        $id = $_POST['location_id'];
        
        // Safety Check: Don't delete if stock exists
        $checkStock = $pdo->prepare("SELECT id FROM location_stock WHERE location_id = ? AND quantity > 0 LIMIT 1");
        $checkStock->execute([$id]);

        // Safety Check: Don't delete if sales exist
        $checkSales = $pdo->prepare("SELECT id FROM sales WHERE location_id = ? LIMIT 1");
        $checkSales->execute([$id]);
        
        if ($checkStock->rowCount() > 0) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Cannot delete: This location still has stock.";
        } elseif ($checkSales->rowCount() > 0) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Cannot delete: Historical sales data exists for this location.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM locations WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Location deleted.";
        }
    }

    header("Location: index.php?page=locations");
    exit;
}

// --- FETCH DATA ---
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
?>
