<?php
// Security: Session is already started by index.php
$userId = $_SESSION['user_id'];
$locId = $_SESSION['location_id'];

// Get Location Name
$stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
$stmt->execute([$locId]);
$locationName = $stmt->fetchColumn() ?: 'Unknown Location';

// Basic Stats (Placeholder for now to verify DB connection)
$stats = [
    'sales' => 0.00,
    'orders' => 0,
    'stock_low' => 0
];
?>
