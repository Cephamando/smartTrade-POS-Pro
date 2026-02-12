<?php
// Simple JSON endpoint for dashboard polling
require_once '../config/db.php'; // Adjust path if your DB config is elsewhere, or paste connection here
header('Content-Type: application/json');

try {
    // Database connection (Standard PDO)
    $host = 'pos_db'; $db = 'pos_db'; $user = 'root'; $pass = 'posRoot123!';
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    
    // Count orders that have uncollected items AND are paid
    $sql = "SELECT COUNT(DISTINCT s.id) as count 
            FROM sale_items si 
            JOIN sales s ON si.sale_id = s.id 
            WHERE si.fulfillment_status = 'uncollected' 
            AND s.payment_status = 'paid'";
            
    $count = $pdo->query($sql)->fetchColumn();
    echo json_encode(['count' => $count]);
} catch (Exception $e) {
    echo json_encode(['count' => 0]);
}
?>
