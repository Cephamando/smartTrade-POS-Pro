<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// Only Admin/Manager can manage users
if (!in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    echo "Access Denied"; exit;
}

// --- 1. ADD / EDIT USER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    // FIX: Use null coalescing (??) to prevent "Undefined array key" warnings
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $role = $_POST['role'] ?? 'cashier'; // Default to cashier if missing
    $pass = $_POST['password'] ?? '';
    $id = $_POST['user_id'] ?? '';

    try {
        if ($id) {
            // Update Existing
            $sql = "UPDATE users SET username=?, full_name=?, role=? WHERE id=?";
            $params = [$username, $fullName, $role, $id];
            
            if (!empty($pass)) {
                $sql = "UPDATE users SET username=?, full_name=?, role=?, password_hash=? WHERE id=?";
                $params = [$username, $fullName, $role, password_hash($pass, PASSWORD_DEFAULT), $id];
            }
            $pdo->prepare($sql)->execute($params);
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "User updated successfully.";
        } else {
            // Create New
            $stmt = $pdo->prepare("INSERT INTO users (username, full_name, role, password_hash, is_active) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$username, $fullName, $role, password_hash($pass, PASSWORD_DEFAULT)]);
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "User created successfully.";
        }
    } catch (PDOException $e) {
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: " . $e->getMessage();
    }
    header("Location: index.php?page=users"); exit;
}

// --- 2. SOFT DELETE USER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $id = $_POST['user_id'] ?? 0;
    
    // Prevent deleting self
    if ($id == $_SESSION['user_id']) {
        $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "You cannot delete yourself.";
    } else {
        try {
            // SOFT DELETE: Just mark as inactive
            $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$id]);
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "User deactivated successfully.";
        } catch (PDOException $e) {
            $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Database Error: " . $e->getMessage();
        }
    }
    header("Location: index.php?page=users"); exit;
}

// --- 3. FETCH ACTIVE USERS ---
$users = $pdo->query("SELECT * FROM users WHERE is_active = 1 ORDER BY role, username")->fetchAll();
?>
