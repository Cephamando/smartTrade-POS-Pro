<?php
// Prevent direct access to this file if not via index.php
if (!defined('PDO::ATTR_DRIVER_NAME')) {
    // If PDO isn't defined, we probably aren't in the router.
    // A safer check is usually looking for a constant defined in index.php, 
    // but for now, we assume the router handles traffic.
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password.";
    } else {
        // Fetch User
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            
            // 1. REGENERATE ID (Security: Prevent Session Fixation)
            session_regenerate_id(true);

            // 2. SET SESSION
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            
            // 3. SET LOCATION
            // Devs default to their DB location but can switch later.
            // Everyone else is hard-locked.
            $_SESSION['location_id'] = $user['location_id'];

            // 4. CHECK FORCE PASSWORD CHANGE
            if ($user['force_password_change'] == 1) {
                header("Location: index.php?page=change_password");
                exit;
            }

            // 5. SUCCESS REDIRECT
            header("Location: index.php?page=dashboard");
            exit;

        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
