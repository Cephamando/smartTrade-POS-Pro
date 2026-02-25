<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['user_id'])) { header("Location: index.php?page=login"); exit; }

// Only Admin and Dev can access the user management page
if (!in_array($_SESSION['role'], ['admin', 'dev'])) {
    die("<h1>Access Denied</h1><p>You do not have permission to manage users.</p>");
}

// --- HANDLE FORM SUBMISSIONS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD USER
    if (isset($_POST['add_user'])) {
        $username = trim($_POST['username']);
        $fullName = trim($_POST['full_name']);
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        $role = $_POST['role'];
        $locationId = (int)$_POST['location_id'];

        // SECURITY: Only a Dev can create another Dev. Downgrade unauthorized attempts.
        if ($role === 'dev' && $_SESSION['role'] !== 'dev') {
            $role = 'admin'; 
        }

        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, full_name, password, role, location_id) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $fullName, $password, $role, $locationId]);
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "User created successfully.";
        } catch (Exception $e) {
            $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error: Username might already be taken.";
        }
        header("Location: index.php?page=users"); exit;
    }

    // 2. EDIT USER
    if (isset($_POST['edit_user'])) {
        $userId = (int)$_POST['user_id'];
        $username = trim($_POST['username']);
        $fullName = trim($_POST['full_name']);
        $role = $_POST['role'];
        $locationId = (int)$_POST['location_id'];
        
        // Fetch the current role of the person being edited
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetRole = $stmt->fetchColumn();

        // SECURITY: Block Admins from editing the Developer account
        if ($targetRole === 'dev' && $_SESSION['role'] !== 'dev') {
            $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Access Denied: You cannot modify the Developer account.";
            header("Location: index.php?page=users"); exit;
        }

        // SECURITY: Prevent the Developer from accidentally removing their own Dev status
        if ($targetRole === 'dev' && $role !== 'dev') {
            $role = 'dev'; 
        }

        try {
            if (!empty($_POST['password'])) {
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, location_id = ?, password = ? WHERE id = ?");
                $stmt->execute([$username, $fullName, $role, $locationId, $password, $userId]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, role = ?, location_id = ? WHERE id = ?");
                $stmt->execute([$username, $fullName, $role, $locationId, $userId]);
            }
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "User updated successfully.";
        } catch (Exception $e) {
            $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Error updating user.";
        }
        header("Location: index.php?page=users"); exit;
    }

    // 3. DELETE USER
    if (isset($_POST['delete_user'])) {
        $userId = (int)$_POST['user_id'];
        
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $targetRole = $stmt->fetchColumn();

        // SECURITY: NO ONE CAN DELETE THE DEV ACCOUNT (Not even the Dev, to prevent locking out the system)
        if ($targetRole === 'dev') {
            $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "Critical: The Developer account is protected and cannot be deleted.";
        } 
        // SECURITY: Prevent deleting the account you are currently logged into
        else if ($userId === $_SESSION['user_id']) {
            $_SESSION['swal_type'] = 'error'; $_SESSION['swal_msg'] = "You cannot delete your active session.";
        } else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$userId]);
            $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = "User deleted.";
        }
        header("Location: index.php?page=users"); exit;
    }
}

// Fetch all users and sort them so Devs/Admins show up at the top
$users = $pdo->query("
    SELECT u.id, u.username, u.full_name, u.role, u.location_id, l.name as location_name 
    FROM users u 
    LEFT JOIN locations l ON u.location_id = l.id 
    ORDER BY FIELD(u.role, 'dev', 'admin', 'manager', 'head_chef', 'chef', 'bartender', 'waiter', 'cashier'), u.full_name ASC
")->fetchAll(PDO::FETCH_ASSOC);

$locations = $pdo->query("SELECT id, name FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
