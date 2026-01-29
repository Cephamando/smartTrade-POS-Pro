<?php
// SECURITY: Admins Only (or Managers if you prefer)
// Strict: Only Admins can manage users to prevent takeover
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'dev'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD USER
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $password = $_POST['password']; // Will be hashed
        $role = $_POST['role'];
        $locId = $_POST['location_id'];

        // Basic Validation
        if (empty($username) || empty($password)) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Username and Password required.";
        } else {
            // Check Duplicate
            $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $check->execute([$username]);
            
            if ($check->rowCount() > 0) {
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = "Username '$username' already taken.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password_hash, role, location_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                $stmt->execute([$username, $hash, $role, $locId]);
                
                $_SESSION['swal_type'] = 'success';
                $_SESSION['swal_msg'] = "User '$username' created!";
            }
        }
    }

    // 2. DELETE USER
    if (isset($_POST['delete_user'])) {
        $id = $_POST['user_id'];
        
        // Prevent deleting self
        if ($id == $_SESSION['user_id']) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "You cannot delete yourself.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "User deleted.";
        }
    }
    
    // 3. PASSWORD RESET (Simple Overwrite)
    if (isset($_POST['reset_password'])) {
        $id = $_POST['user_id'];
        $newPass = $_POST['new_password'];
        
        $hash = password_hash($newPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = 1 WHERE id = ?");
        $stmt->execute([$hash, $id]);
        
        $_SESSION['swal_type'] = 'success';
        $_SESSION['swal_msg'] = "Password reset. User will be asked to change it on login.";
    }

    header("Location: index.php?page=users");
    exit;
}

// --- FETCH DATA ---
$users = $pdo->query("
    SELECT u.*, l.name as location_name 
    FROM users u 
    LEFT JOIN locations l ON u.location_id = l.id 
    ORDER BY u.role ASC, u.username ASC
")->fetchAll();

$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
?>
