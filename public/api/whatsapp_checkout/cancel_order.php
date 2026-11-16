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

$input      = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
$identifier = trim((string) ($input['tx_ref'] ?? $input['order_id'] ?? $input['sale_id'] ?? $input['id'] ?? ''));

if ( $identifier === '' ) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Missing order identifier for cancellation']);
    exit;
}

try {
    $pdo = WhatsAppCloudService::getDb();

    if ( ctype_digit($identifier) ) {
        $stmt = $pdo->prepare("SELECT * FROM st_direct_wa_orders WHERE native_sale_id = :sid OR id = :id LIMIT 1");
        $stmt->execute([':sid' => (int) $identifier, ':id' => (int) $identifier]);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM st_direct_wa_orders WHERE tx_ref = :txref OR zra_rcpt_no = :rcpt LIMIT 1");
        $stmt->execute([':txref' => $identifier, ':rcpt' => $identifier]);
    }
    $order = $stmt->fetch();

    if ( !$order ) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        exit;
    }

    $pdo->prepare("UPDATE st_direct_wa_orders SET fulfillment_status = 'CANCELLED', payment_status = 'FAILED' WHERE id = :id")
        ->execute([':id' => $order['id']]);

    $saleId = (int) ($order['native_sale_id'] ?? 0);
    if ( $saleId > 0 ) {
        $pdo->prepare("UPDATE sales SET status = 'cancelled', payment_status = 'voided' WHERE id = :sid")
            ->execute([':sid' => $saleId]);
        $pdo->prepare("UPDATE sale_items SET status = 'voided', fulfillment_status = 'voided' WHERE sale_id = :sid")
            ->execute([':sid' => $saleId]);
        if ( (bool) $pdo->query("SHOW TABLES LIKE 'pickup_notifications'")->fetchColumn() ) {
            $pdo->prepare("UPDATE pickup_notifications SET status = 'cancelled' WHERE sale_id = :sid")
                ->execute([':sid' => $saleId]);
        }
    }

    $waService = new WhatsAppCloudService($pdo);
    $waService->sendFulfillmentStatusUpdate($order, 'CANCELLED');

    echo json_encode([
        'status'             => 'success',
        'tx_ref'             => $order['tx_ref'],
        'fulfillment_status' => 'CANCELLED',
        'message'            => 'Order cancelled successfully via AJAX.'
    ]);
} catch ( \Throwable $e ) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
