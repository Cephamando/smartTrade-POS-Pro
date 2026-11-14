<?php
declare(strict_types=1);

/**
 * Drop-in Hand Over Hook for Native Web Orders
 *
 * Usage 1 (Included directly inside native PHP Hand Over controller):
 *   $handOverOrderRef = $txRefOrWebOrderId;
 *   require __DIR__ . '/whatsapp_checkout/handover_hook.php';
 *
 * Usage 2 (Called via POST/GET HTTP request):
 *   POST /api/whatsapp_checkout/handover_hook.php {"order_id": 123} or {"tx_ref": "ST-FLW-..."}
 */

require_once __DIR__ . '/../../../app/Services/WhatsAppCloudService.php';

use SmartTrade\Services\WhatsAppCloudService;

// Mode 1: Included inside an existing PHP controller script
if ( isset($handOverOrderRef) && $handOverOrderRef !== '' ) {
    $waHandOverResult = WhatsAppCloudService::onNativeHandOver($handOverOrderRef);
    return;
}

// Mode 2: Standalone HTTP Endpoint
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo   = WhatsAppCloudService::getDb();
    $input = json_decode((string) file_get_contents('php://input'), true) ?: $_REQUEST;

    $identifier = trim((string) (
        $input['tx_ref']
        ?? $input['order_ref']
        ?? $input['order_number']
        ?? $input['reference']
        ?? $input['web_order_id']
        ?? $input['order_id']
        ?? $input['id']
        ?? ''
    ));

    if ( $identifier !== '' ) {
        $result = WhatsAppCloudService::onNativeHandOver($identifier, $pdo);
        http_response_code($result['success'] ? 200 : 404);
        echo json_encode($result);
        exit;
    }

    // If called with no specific order ID, reconcile all newly handed-over Web Orders
    $reconciled = WhatsAppCloudService::reconcileHandedOverWebOrders($pdo);
    http_response_code(200);
    echo json_encode([
        'status'                => 'success',
        'reconciled_count'      => count($reconciled),
        'reconciled_hand_overs' => $reconciled
    ]);
} catch ( \Throwable $e ) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage()
    ]);
}
