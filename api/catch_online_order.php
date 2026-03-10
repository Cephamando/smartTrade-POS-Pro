<?php
header('Content-Type: application/json');
require_once '../../src/config.php';

$allHeaders = array_change_key_case(getallheaders(), CASE_LOWER);
$authHeader = $allHeaders['authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';

$validToken = "Bearer pos_token_8f7d9a2b4c6e1mando99384"; 

if ($authHeader !== $validToken) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized"]);
    exit;
}

$rawPostData = file_get_contents('php://input');
$orderData = json_decode($rawPostData, true);

if (!$orderData || !isset($orderData['items'])) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid JSON payload"]);
    exit;
}

try {
    $pdo->beginTransaction();

    // ==========================================
    // 🛡️ IDEMPOTENCY CHECK (BLOCK DUPLICATES)
    // ==========================================
    $extOrderId = $orderData['external_order_id'] ?? null;
    if ($extOrderId) {
        $checkDuplicate = $pdo->prepare("SELECT id FROM sales WHERE split_group_id = ? AND payment_method = 'Online' LIMIT 1");
        $checkDuplicate->execute([$extOrderId]);
        $existingSaleId = $checkDuplicate->fetchColumn();

        if ($existingSaleId) {
            $pdo->rollBack();
            // Tell the external platform 'success' so they stop retrying, but do nothing internally.
            echo json_encode([
                "status" => "success", 
                "message" => "Duplicate webhook ignored. Order already processed.", 
                "order_id" => $existingSaleId
            ]);
            exit;
        }
    }
    // ==========================================

    $targetLocation = 1; 
    $validatedItems = [];
    $calculatedTotal = 0; 

    // 1. Stock Validation & Real Price Fetching
    foreach ($orderData['items'] as $item) {
        $checkStmt = $pdo->prepare("SELECT p.id, p.name, p.price as db_price, c.type, i.quantity FROM products p LEFT JOIN categories c ON p.category_id = c.id LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = ? WHERE p.id = ?");
        $checkStmt->execute([$targetLocation, $item['product_id']]);
        $product = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || (float)$product['quantity'] < (float)$item['quantity']) {
            throw new Exception("Stock Error: " . ($product['name'] ?? 'Unknown Product') . " is unavailable.");
        }
        
        $item['category_type'] = $product['type'] ?? 'item';
        $item['verified_price'] = $product['db_price']; 
        $calculatedTotal += ((float)$product['db_price'] * (int)$item['quantity']);
        
        $validatedItems[] = $item;
    }

    // 2. Find Active Shift
    $shiftStmt = $pdo->prepare("SELECT id FROM shifts WHERE location_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
    $shiftStmt->execute([$targetLocation]);
    $activeShift = $shiftStmt->fetch(PDO::FETCH_ASSOC);
    $shiftId = $activeShift ? $activeShift['id'] : null;

    // 3. Create the Sale Record 
    $insertSale = $pdo->prepare("
        INSERT INTO sales 
        (location_id, user_id, shift_id, subtotal, total_amount, final_total, status, payment_status, payment_method, split_group_id, customer_name) 
        VALUES (?, 1, ?, ?, ?, ?, 'pending', 'pending', 'Online', ?, ?)
    ");
    
    $displayLabel = "ONLINE: " . ($orderData['customer_name'] ?? 'Guest');
    
    $insertSale->execute([
        $targetLocation, 
        $shiftId,
        $calculatedTotal, 
        $calculatedTotal, 
        $calculatedTotal, 
        $extOrderId, 
        $displayLabel
    ]);
    
    $saleId = $pdo->lastInsertId();

    // 4. Insert Items
    $insertItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, price_at_sale, status) VALUES (?, ?, ?, ?, ?, ?)");
    $deductStock = $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE product_id = ? AND location_id = ?");

    foreach ($validatedItems as $item) {
        $kdsStatus = (strpos(strtolower($item['category_type']), 'food') !== false || strpos(strtolower($item['category_type']), 'meal') !== false) ? 'pending' : 'ready';
        $insertItem->execute([$saleId, $item['product_id'], $item['quantity'], $item['verified_price'], $item['verified_price'], $kdsStatus]);
        $deductStock->execute([$item['quantity'], $item['product_id'], $targetLocation]);
    }

    $pdo->commit();
    echo json_encode(["status" => "success", "message" => "Order verified and saved", "order_id" => $saleId, "calculated_total" => $calculatedTotal]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
