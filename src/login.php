<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// --- 🛡️ OWASP: GENERATE CSRF TOKEN ---
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // --- 🛡️ OWASP: VERIFY CSRF TOKEN ---
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = "Security token mismatch. Please refresh the page and try again.";
    } else {
        $username = trim($_POST['username']);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error = "Please fill in all fields.";
        } else {
            $stmt = $pdo->prepare("SELECT u.id, u.username, u.password_hash, u.role, u.location_id, u.force_password_change, l.name as location_name FROM users u LEFT JOIN locations l ON u.location_id = l.id WHERE u.username = ? AND (u.is_active = 1 OR u.is_active IS NULL)");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                
                // --- 🛡️ OWASP: PREVENT SESSION FIXATION ---
                session_regenerate_id(true);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['location_id'] = $user['location_id'];
                $_SESSION['location_name'] = $user['location_name'];
                $_SESSION['force_change'] = $user['force_password_change'];

                if ($user['force_password_change'] == 1) {
                     header("Location: index.php?page=change_password");
                } else {
                     // Smart Redirect
                     if (in_array($user['role'], ['admin', 'manager', 'dev'])) {
                         header("Location: index.php?page=dashboard");
                     } elseif (in_array($user['role'], ['chef', 'head_chef'])) {
                         header("Location: index.php?page=kitchen");
                     } else {
                         header("Location: index.php?page=pos");
                     }
                }
                exit;
            } else {
                $error = "Invalid credentials.";
            }
        }
    }
}
?>
