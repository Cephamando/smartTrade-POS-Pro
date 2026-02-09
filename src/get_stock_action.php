<?php
require_once 'config.php';
header('Content-Type: application/json');

$product_id = $_GET['product_id'] ?? 0;
$location_id = $_GET['location_id'] ?? 0;

try {
    // Querying the primary 'inventory' table instead of 'location_stock'
    $stmt = $pdo->prepare("SELECT SUM(quantity) as stock FROM inventory WHERE product_id = ? AND location_id = ?");
    $stmt->execute([$product_id, $location_id]);
    $result = $stmt->fetch();
    
    echo json_encode(['stock' => (int)($result['stock'] ?? 0)]);
} catch (Exception $e) {
    echo json_encode(['stock' => 0, 'error' => $e->getMessage()]);
}
