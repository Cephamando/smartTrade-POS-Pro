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

// ==========================================
// 🛡️ RECIPE-AWARE STOCK DEDUCTION FUNCTIONS
// ==========================================
function executeDeduction($pdo, $pId, $qty, $locId, $uId, $actionType) {
    $stmt = $pdo->prepare("SELECT id, quantity FROM inventory WHERE product_id = ? AND location_id = ?");
    $stmt->execute([$pId, $locId]);
    $inv = $stmt->fetch();
    if ($inv) {
        $pdo->prepare("UPDATE inventory SET quantity = quantity - ? WHERE id = ?")->execute([$qty, $inv['id']]);
        $newQty = $inv['quantity'] - $qty;
    } else {
        $pdo->prepare("INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?)")->execute([$pId, $locId, -$qty]);
        $newQty = -$qty;
    }
    $pdo->prepare("INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type) VALUES (?, ?, ?, ?, ?, ?)")
        ->execute([$pId, $locId, $uId, -$qty, $newQty, $actionType]);
}

function deductStock($pdo, $productId, $qty, $locId, $uId, $actionOverride = 'sale') {
    $stmt = $pdo->prepare("SELECT ingredient_product_id, quantity FROM product_recipes WHERE parent_product_id = ?");
    $stmt->execute([$productId]);
    $recipe = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($recipe) > 0) {
        foreach($recipe as $ing) {
            $deductQty = $ing['quantity'] * $qty;
            $act = ($actionOverride === 'sale') ? 'recipe_deduction' : $actionOverride;
            executeDeduction($pdo, $ing['ingredient_product_id'], $deductQty, $locId, $uId, $act);
        }
    } else {
        executeDeduction($pdo, $productId, $qty, $locId, $uId, $actionOverride);
    }
}
// ==========================================

try {
    $pdo->beginTransaction();

    // 🛡️ IDEMPOTENCY CHECK
    $extOrderId = $orderData['external_order_id'] ?? null;
    if ($extOrderId) {
        $checkDuplicate = $pdo->prepare("SELECT id FROM sales WHERE split_group_id = ? AND payment_method = 'Online' LIMIT 1");
        $checkDuplicate->execute([$extOrderId]);
        $existingSaleId = $checkDuplicate->fetchColumn();

        if ($existingSaleId) {
            $pdo->rollBack();
            echo json_encode([
                "status" => "success", 
                "message" => "Duplicate webhook ignored.", 
                "order_id" => $existingSaleId
            ]);
            exit;
        }
    }

    $targetLocation = 1; // You can make this dynamic if needed
    $validatedItems = [];
    $calculatedTotal = 0; 

    // 1. SMART STOCK VALIDATION
    foreach ($orderData['items'] as $item) {
        // Now checks if the item is a recipe (made to order)
        $checkStmt = $pdo->prepare("
            SELECT p.id, p.name, p.price as db_price, c.type, i.quantity, 
            (SELECT COUNT(*) FROM product_recipes WHERE parent_product_id = p.id) as is_recipe 
            FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = ? 
            WHERE p.id = ?
        ");
        $checkStmt->execute([$targetLocation, $item['product_id']]);
        $product = $checkStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            throw new Exception("Error: Product ID " . $item['product_id'] . " does not exist.");
        }

        $isRecipe = $product['is_recipe'] > 0;
        $stockQty = (float)$product['quantity'];
        $reqQty = (float)$item['quantity'];

        // Only enforce strict stock limits if it's a standard retail item (no recipe)
        if (!$isRecipe && $stockQty < $reqQty) {
            throw new Exception("Stock Error: " . $product['name'] . " is unavailable.");
        }
        
        $item['category_type'] = $product['type'] ?? 'item';
        $item['verified_price'] = $product['db_price']; 
        $calculatedTotal += ((float)$product['db_price'] * $reqQty);
        
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
    $insertSale->execute([$targetLocation, $shiftId, $calculatedTotal, $calculatedTotal, $calculatedTotal, $extOrderId, $displayLabel]);
    $saleId = $pdo->lastInsertId();

    // 4. Insert Items & Process Ingredients
    $insertItem = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, price_at_sale, status, fulfillment_status) VALUES (?, ?, ?, ?, ?, ?, 'uncollected')");

    foreach ($validatedItems as $item) {
        $kdsStatus = (strpos(strtolower($item['category_type']), 'food') !== false || strpos(strtolower($item['category_type']), 'meal') !== false) ? 'pending' : 'ready';
        $insertItem->execute([$saleId, $item['product_id'], $item['quantity'], $item['verified_price'], $item['verified_price'], $kdsStatus]);
        
        // Smart Deduction: Will deduct the Beer, OR deduct the Burger's ingredients!
        deductStock($pdo, $item['product_id'], $item['quantity'], $targetLocation, 1, 'online_sale');
    }

    $pdo->commit();

    // 🔔 AUTO-FIRE WEBHOOK FOR DRINKS/RETAIL 
    $checkAllReady = $pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND status != 'ready' AND status NOT IN ('voided', 'refunded')");
    $checkAllReady->execute([$saleId]);
    $remaining = $checkAllReady->fetchColumn();

    if ($remaining == 0 && !empty($extOrderId)) {
        $webhookUrl = "https://webhook.site/8f7d9a2b-test-ready-notif"; 
        $payload = json_encode([
            "order_id" => $extOrderId,
            "status" => "ready_for_collection",
            "store_id" => "MAIN_COUNTER_001",
            "timestamp" => date('c')
        ]);
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3); 
        curl_exec($ch);
        curl_close($ch);
    }

    echo json_encode(["status" => "success", "message" => "Order verified and saved", "order_id" => $saleId, "calculated_total" => $calculatedTotal]);

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => $e->getMessage()]);
}
