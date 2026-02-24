<?php
session_start();
$locationId = $_SESSION['pos_location_id'] ?? $_SESSION['location_id'] ?? 0;

// CRITICAL FIX: Unlock the session immediately so the user can navigate the app without waiting!
session_write_close(); 

require_once dirname(__DIR__) . '/src/config.php';

try {
    // Only check if it's actually a hospitality tier location that cares about kitchen pickups
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM sale_items si 
        JOIN sales s ON si.sale_id = s.id 
        WHERE s.location_id = ? AND si.status = 'ready' AND si.fulfillment_status = 'uncollected'
    ");
    $stmt->execute([$locationId]);
    echo json_encode(['count' => (int)$stmt->fetchColumn()]);
} catch(Exception $e) {
    echo json_encode(['count' => 0]);
}
?>
