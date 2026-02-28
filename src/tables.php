<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// Security check: Only admins/managers can edit the floorplan
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'manager', 'dev'])) {
    header("Location: index.php?page=dashboard"); exit;
}

if (isset($_POST['add_table'])) {
    $locId = (int)$_POST['location_id'];
    $zone = trim($_POST['zone_name']);
    $name = trim($_POST['table_name']);
    $cap = (int)$_POST['capacity'];
    
    $stmt = $pdo->prepare("INSERT INTO restaurant_tables (location_id, zone_name, table_name, capacity) VALUES (?, ?, ?, ?)");
    $stmt->execute([$locId, $zone, $name, $cap]);
    $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = 'Table added successfully!';
    header("Location: index.php?page=tables"); exit;
}

if (isset($_POST['delete_table'])) {
    $id = (int)$_POST['table_id'];
    // We don't hard-delete if it's tied to an active sale, but for simplicity we remove it here.
    $pdo->prepare("DELETE FROM restaurant_tables WHERE id = ?")->execute([$id]);
    $_SESSION['swal_type'] = 'success'; $_SESSION['swal_msg'] = 'Table deleted.';
    header("Location: index.php?page=tables"); exit;
}

$locations = $pdo->query("SELECT id, name FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
$tables = $pdo->query("
    SELECT rt.*, l.name as location_name 
    FROM restaurant_tables rt 
    JOIN locations l ON rt.location_id = l.id 
    ORDER BY l.name ASC, rt.zone_name ASC, rt.table_name ASC
")->fetchAll(PDO::FETCH_ASSOC);
?>
