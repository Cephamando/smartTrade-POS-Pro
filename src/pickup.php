<?php
// SECURITY
$allowed = ['admin', 'manager', 'dev', 'waiter', 'bartender', 'cashier'];
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $allowed)) {
    header("Location: index.php?page=dashboard");
    exit;
}

// --- HANDLE "COLLECTED" ACTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notifId = $_POST['notif_id'];
    $collectedBy = $_POST['collected_by']; // User ID selected in dropdown
    
    if ($notifId && $collectedBy) {
        $stmt = $pdo->prepare("UPDATE pickup_notifications SET status = 'collected', collected_by = ? WHERE id = ?");
        $stmt->execute([$collectedBy, $notifId]);
    }
    
    header("Location: index.php?page=pickup");
    exit;
}

// --- FETCH DATA ---
// 1. Ready Orders
$sql = "SELECT * FROM pickup_notifications WHERE status = 'ready' ORDER BY created_at ASC";
$readyItems = $pdo->query($sql)->fetchAll();

// 2. Staff List (For Dropdown)
// We list anyone who might pick up food (Waiters, Bartenders, Cashiers, even Managers)
$uSql = "SELECT id, username, role FROM users WHERE role IN ('waiter', 'bartender', 'cashier', 'manager', 'admin') ORDER BY username ASC";
$staff = $pdo->query($uSql)->fetchAll();
?>