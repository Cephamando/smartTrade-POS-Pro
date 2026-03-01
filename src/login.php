<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill in all fields.";
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password_hash, role, location_id, force_password_change FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['location_id'] = $user['location_id'];
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
?>
