<?php
// SECURITY: Managers/Admins Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD VENDOR
    if (isset($_POST['add_vendor'])) {
        $name = trim($_POST['name']);
        $contact = trim($_POST['contact_person']);
        $phone = trim($_POST['phone']);

        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO vendors (name, contact_person, phone) VALUES (?, ?, ?)");
            $stmt->execute([$name, $contact, $phone]);
            
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Vendor '$name' added successfully.";
        }
    }

    // 2. DELETE VENDOR
    if (isset($_POST['delete_vendor'])) {
        $id = $_POST['vendor_id'];
        
        // Check if used in GRVs
        $check = $pdo->prepare("SELECT id FROM grvs WHERE vendor_id = ? LIMIT 1");
        $check->execute([$id]);
        
        if ($check->rowCount() > 0) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Cannot delete vendor. They have linked GRV records.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM vendors WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Vendor deleted.";
        }
    }

    // Redirect
    header("Location: index.php?page=vendors");
    exit;
}

// --- FETCH DATA ---
$vendors = $pdo->query("SELECT * FROM vendors ORDER BY name ASC")->fetchAll();
?>
