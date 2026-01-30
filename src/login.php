<?php
// START SESSION (if not already started)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// IF ALREADY LOGGED IN
if (isset($_SESSION['user_id'])) {
    header("Location: index.php?page=dashboard");
    exit;
}

// HANDLE LOGIN POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        // Fetch user AND the force_change flag
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role, location_id, force_password_change FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            // SUCCESS: Set Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['location_id'] = $user['location_id'];
            
            // CRITICAL: Store the Force Change Flag
            $_SESSION['force_change'] = $user['force_password_change'];

            // Redirect based on flag
            if ($user['force_password_change'] == 1) {
                 header("Location: index.php?page=change_password");
            } else {
                 header("Location: index.php?page=dashboard");
            }
            exit;
        } else {
            $error = "Invalid credentials.";
        }
    }
}
?>
