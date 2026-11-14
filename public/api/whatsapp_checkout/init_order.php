<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../app/Services/WhatsAppCloudService.php';

use SmartTrade\Services\WhatsAppCloudService;

if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$rawInput = file_get_contents('php://input');
$payload  = json_decode($rawInput, true);

if ( !is_array($payload) ) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON body']);
    exit;
}

$branchId        = (int) ($payload['branch_id'] ?? 1);
$customerName    = trim((string) ($payload['customer_name'] ?? ''));
$customerPhone   = preg_replace('/\D+/', '', (string) ($payload['customer_phone'] ?? ''));
$deliveryType    = in_array($payload['delivery_type'] ?? '', ['DELIVERY', 'PICKUP'], true) ? $payload['delivery_type'] : 'DELIVERY';
$deliveryAddress = trim((string) ($payload['delivery_address'] ?? ''));
$cartItems       = $payload['items'] ?? [];

if ( empty($customerName) || strlen($customerPhone) < 9 || empty($cartItems) || !is_array($cartItems) ) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Please provide customer name, valid phone number, and cart items.']);
    exit;
}

if ( strlen($customerPhone) === 10 && str_starts_with($customerPhone, '0') ) {
    $customerPhone = '260' . substr($customerPhone, 1);
} elseif ( strlen($customerPhone) === 9 ) {
    $customerPhone = '260' . $customerPhone;
}

try {
    $pdo = WhatsAppCloudService::getDb();

    $hasProductsTable = (bool) $pdo->query("SHOW TABLES LIKE 'products'")->fetchColumn();

    $subtotalZmw   = 0.00;
    $verifiedItems = [];

    foreach ( $cartItems as $item ) {
        $prodId = (int) ($item['product_id'] ?? 0);
        $qty    = max(1, (int) ($item['qty'] ?? 1));

        if ( $prodId <= 0 ) {
            continue;
        }

        $prod = null;
        if ( $hasProductsTable ) {
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $prodId]);
            $prod = $stmt->fetch();

            if ( !$prod ) {
                $prod = $pdo->query("SELECT * FROM products LIMIT 1")->fetch();
            }
        }

        if ( $prod ) {
            $availStock = (int) ($prod['stock_qty'] ?? $prod['stock'] ?? $prod['quantity'] ?? 100);
            if ( $availStock < $qty ) {
                throw new Exception("Insufficient stock for " . ($prod['name'] ?? "Product #{$prodId}") . " (Available: {$availStock}).");
            }
            $unitPrice = (float) ($prod['selling_price'] ?? $prod['price'] ?? $prod['unit_price'] ?? 185.00);
            if ( $unitPrice <= 0 ) {
                $unitPrice = 185.00;
            }
            $resolvedId   = (int) ($prod['id'] ?? $prodId);
            $resolvedSku  = (string) ($prod['sku'] ?? $prod['code'] ?? ('SKU-' . $resolvedId));
            $resolvedName = (string) ($prod['name'] ?? $prod['product_name'] ?? ('Product #' . $resolvedId));
        } else {
            $resolvedId   = $prodId;
            $resolvedSku  = 'GRL-001';
            $resolvedName = 'Full Flame-Grilled Chicken & Chips';
            $unitPrice    = 185.00;
        }

        $lineTotal    = round($unitPrice * $qty, 2);
        $subtotalZmw += $lineTotal;

        $verifiedItems[] = [
            'product_id' => $resolvedId,
            'sku'        => $resolvedSku,
            'name'       => $resolvedName,
            'qty'        => $qty,
            'unit_price' => $unitPrice,
            'line_total' => $lineTotal
        ];
    }

    if ( empty($verifiedItems) || $subtotalZmw <= 0 ) {
        throw new Exception('No valid items in cart.');
    }

    $deliveryFeeZmw = ($deliveryType === 'DELIVERY') ? 35.00 : 0.00;
    $totalZmw       = round($subtotalZmw + $deliveryFeeZmw, 2);
    $txRef          = 'ST-FLW-' . strtoupper(bin2hex(random_bytes(4))) . '-' . time();

    $ins = $pdo->prepare("
        INSERT INTO st_direct_wa_orders (
            branch_id, tx_ref, customer_name, customer_phone,
            delivery_type, delivery_address, subtotal_zmw,
            delivery_fee_zmw, total_zmw, items_json, payment_status
        ) VALUES (
            :bid, :txref, :cname, :cphone,
            :dtype, :daddr, :sub,
            :dfee, :tot, :items, 'PENDING_PAYMENT'
        )
    ");

    $ins->execute([
        ':bid'    => $branchId,
        ':txref'  => $txRef,
        ':cname'  => $customerName,
        ':cphone' => $customerPhone,
        ':dtype'  => $deliveryType,
        ':daddr'  => $deliveryAddress,
        ':sub'    => $subtotalZmw,
        ':dfee'   => $deliveryFeeZmw,
        ':tot'    => $totalZmw,
        ':items'  => json_encode($verifiedItems, JSON_UNESCAPED_UNICODE)
    ]);

    echo json_encode([
        'status'           => 'success',
        'tx_ref'           => $txRef,
        'amount_zmw'       => $totalZmw,
        'subtotal_zmw'     => $subtotalZmw,
        'delivery_fee_zmw' => $deliveryFeeZmw,
        'customer_name'    => $customerName,
        'customer_phone'   => $customerPhone,
        'verified_items'   => $verifiedItems
    ]);

} catch ( Exception $e ) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
