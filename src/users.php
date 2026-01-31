<?php
// SECURITY: Admin, Dev, and Manager Only
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'dev', 'manager'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

$currentUserRole = $_SESSION['role'];

// --- HELPER: FETCH ENUM ROLES FROM DB ---
function getDbRoles($pdo) {
    $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
    $row = $stmt->fetch();
    $type = substr($row['Type'], 6, -2);
    return explode("','", $type);
}

// --- HANDLE POST REQUESTS ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD USER
    if (isset($_POST['add_user'])) {
        $fullName = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $password = $_POST['password'];
        $role = $_POST['role'];
        $locationId = $_POST['location_id'];

        // SECURITY: Only Dev can create Dev
        if ($role === 'dev' && $currentUserRole !== 'dev') {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Access Denied: Only Developers can create Dev accounts.";
        } 
        elseif (empty($fullName) || empty($username) || empty($password)) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Full Name, Username, and Password are required.";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = "Username '$username' is already taken.";
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (full_name, username, password_hash, role, location_id, force_password_change) VALUES (?, ?, ?, ?, ?, 1)");
                $stmt->execute([$fullName, $username, $hash, $role, $locationId]);

                $_SESSION['swal_type'] = 'success';
                $_SESSION['swal_msg'] = "User created successfully.";
            }
        }
    }

    // 2. EDIT USER
    if (isset($_POST['edit_user'])) {
        $id = $_POST['user_id'];
        $fullName = trim($_POST['full_name']);
        $username = trim($_POST['username']);
        $role = $_POST['role']; // New Role
        $locationId = $_POST['location_id'];
        
        // Fetch Current Role of the user being edited
        $target = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $target->execute([$id]);
        $targetRole = $target->fetchColumn();

        // SECURITY CHECKS
        if ($targetRole === 'dev' && $currentUserRole !== 'dev') {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Access Denied: You cannot edit a Developer account.";
        }
        elseif ($role === 'dev' && $currentUserRole !== 'dev') {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Access Denied: You cannot promote a user to Developer.";
        }
        else {
            $passSql = "";
            $params = [$fullName, $username, $role, $locationId];
            
            if (!empty($_POST['password'])) {
                $passSql = ", password_hash = ?, force_password_change = 1";
                $params[] = password_hash($_POST['password'], PASSWORD_DEFAULT);
            }
            
            $params[] = $id;

            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, username = ?, role = ?, location_id = ? $passSql WHERE id = ?");
            $stmt->execute($params);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "User updated successfully.";
        }
    }

    // 3. DELETE USER
    if (isset($_POST['delete_user'])) {
        $id = $_POST['user_id'];
        
        // Fetch Target Role
        $target = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $target->execute([$id]);
        $targetRole = $target->fetchColumn();

        if ($id == $_SESSION['user_id']) {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "You cannot delete yourself.";
        }
        elseif ($targetRole === 'dev' && $currentUserRole !== 'dev') {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Access Denied: You cannot delete a Developer.";
        }
        else {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "User deleted.";
        }
    }
    
    // 4. RESET PASSWORD
    if (isset($_POST['reset_password_default'])) {
        $id = $_POST['user_id'];
        
        // Fetch Target Role
        $target = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $target->execute([$id]);
        $targetRole = $target->fetchColumn();

        if ($targetRole === 'dev' && $currentUserRole !== 'dev') {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Access Denied: You cannot reset a Developer's password.";
        } else {
            $defaultHash = password_hash('pos123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = 1 WHERE id = ?");
            $stmt->execute([$defaultHash, $id]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Password reset to 'pos123'.";
        }
    }

    header("Location: index.php?page=users");
    exit;
}

// --- FETCH DATA ---
$users = $pdo->query("SELECT u.*, l.name as location_name FROM users u LEFT JOIN locations l ON u.location_id = l.id ORDER BY u.role ASC, u.username ASC")->fetchAll();
$locations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();

// Fetch Roles & FILTER OUT 'dev' if not authorized
$roles = getDbRoles($pdo);
if ($currentUserRole !== 'dev') {
    $roles = array_diff($roles, ['dev']); // Remove 'dev' from array
}
?>
