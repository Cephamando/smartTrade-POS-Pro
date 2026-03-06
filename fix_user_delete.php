<?php
require_once 'src/config.php';

echo "<h3>User Deletion Patch</h3>";

// 1. Database Upgrade
try {
    $pdo->exec("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1");
    echo "<p style='color:green;'>✅ Added 'is_active' column to users table.</p>";
} catch (Exception $e) {
    echo "<p style='color:blue;'>ℹ️ 'is_active' column already exists.</p>";
}

// 2. Patch login.php to block deactivated users
$loginFile = 'src/login.php';
if (file_exists($loginFile)) {
    $content = file_get_contents($loginFile);
    if (strpos($content, 'u.is_active = 1') === false) {
        $content = str_replace(
            "WHERE u.username = ?", 
            "WHERE u.username = ? AND (u.is_active = 1 OR u.is_active IS NULL)", 
            $content
        );
        file_put_contents($loginFile, $content);
        echo "<p style='color:green;'>✅ login.php patched: Deactivated users can no longer log in.</p>";
    }
}

// 3. Patch users.php to handle Soft Deletes
$usersFile = 'src/users.php';
if (file_exists($usersFile)) {
    $content = file_get_contents($usersFile);
    
    // Find the DELETE statement and replace it with a try/catch block
    $pattern = '/\$pdo->prepare\(\s*["\']DELETE FROM users WHERE id\s*=\s*\?["\']\s*\)->execute\(\[\s*(.*?)\s*\]\);/is';
    
    $replacement = <<< 'PHP'
try {
            $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$1]);
            $_SESSION['swal_type'] = 'success';
            $_SESSION['swal_msg'] = 'User deleted permanently.';
        } catch (PDOException $e) {
            if ($e->getCode() == '23000') {
                $pdo->prepare("UPDATE users SET is_active = 0 WHERE id = ?")->execute([$1]);
                $_SESSION['swal_type'] = 'info';
                $_SESSION['swal_msg'] = 'User has historical data. Account deactivated instead.';
            } else {
                $_SESSION['swal_type'] = 'error';
                $_SESSION['swal_msg'] = 'Database Error: ' . $e->getMessage();
            }
        }
PHP;

    $newContent = preg_replace($pattern, $replacement, $content);
    
    // Filter the users list to only show active users
    $newContent = str_replace(
        "SELECT u.*, l.name as loc_name FROM users u",
        "SELECT u.*, l.name as loc_name FROM users u WHERE u.is_active = 1 OR u.is_active IS NULL",
        $newContent
    );
    // Alternative SQL fallback just in case
    $newContent = str_replace(
        "SELECT * FROM users",
        "SELECT * FROM users WHERE is_active = 1 OR is_active IS NULL",
        $newContent
    );

    file_put_contents($usersFile, $newContent);
    echo "<p style='color:green;'>✅ users.php patched: Smart Soft-Deletion enabled.</p>";
}

echo "<br><p><strong>Patch complete! You can close this window.</strong></p>";
?>
