<?php
// Unlock session immediately so we don't cause UI lag
session_start();
session_write_close(); 

require_once dirname(__DIR__) . '/src/config.php';
header('Content-Type: application/json');

$shiftId = (int)($_GET['shift_id'] ?? 0);

if ($shiftId > 0) {
    try {
        // Fetch all PAID sales for this shift, newest first
        $stmt = $pdo->prepare("SELECT id, created_at, customer_name, final_total, payment_method FROM sales WHERE shift_id = ? AND payment_status = 'paid' ORDER BY created_at DESC");
        $stmt->execute([$shiftId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } catch (Exception $e) {
        echo json_encode([]);
    }
} else {
    echo json_encode([]);
}
?>
