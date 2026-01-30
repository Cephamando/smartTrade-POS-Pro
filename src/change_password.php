<?php
// SECURITY: Logged in users only
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php?page=login");
    exit;
}

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = $_POST['current_password'];
    $newPass = $_POST['new_password'];
    $confirmPass = $_POST['confirm_password'];

    // 1. Basic Validation
    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = "All fields are required.";
    } elseif ($newPass !== $confirmPass) {
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = "New passwords do not match.";
    } else {
        // 2. Verify Old Password
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($currentPass, $user['password_hash'])) {
            // 3. Update Password & Clear 'Force Change' Flag
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            
            $update = $pdo->prepare("UPDATE users SET password_hash = ?, force_password_change = 0 WHERE id = ?");
            $update->execute([$newHash, $userId]);

            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = "Password changed successfully!";
            
            // Redirect to dashboard (or wherever)
            header("Location: index.php?page=dashboard");
            exit;
        } else {
            $_SESSION['swal_type'] = 'error';
            $_SESSION['swal_msg'] = "Current password is incorrect.";
        }
    }
}
?>
