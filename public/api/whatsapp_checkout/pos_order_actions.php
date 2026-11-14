<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../app/Services/WhatsAppCloudService.php';

use SmartTrade\Services\WhatsAppCloudService;

try {
    $pdo = WhatsAppCloudService::getDb();

    if ( $_SERVER['REQUEST_METHOD'] === 'GET' ) {
        $branchId      = isset($_GET['branch_id']) ? max(1, (int) $_GET['branch_id']) : 1;
        $paymentFilter = $_GET['payment_status'] ?? 'PAID';

        $stmt = $pdo->prepare("
            SELECT id, branch_id, tx_ref, flw_transaction_id, customer_name, customer_phone,
                   delivery_type, delivery_address, subtotal_zmw, delivery_fee_zmw, total_zmw,
                   items_json, payment_status, fulfillment_status, zra_fiscal_status,
                   zra_rcpt_no, zra_qr_url, created_at
            FROM st_direct_wa_orders
            WHERE branch_id = :bid
              AND payment_status = :pstatus
              AND fulfillment_status != 'COMPLETED'
            ORDER BY created_at ASC
            LIMIT 50
        ");
        $stmt->execute([
            ':bid'     => $branchId,
            ':pstatus' => $paymentFilter
        ]);

        $orders = $stmt->fetchAll();
        foreach ( $orders as &$row ) {
            $row['items'] = json_decode((string) $row['items_json'], true) ?: [];
            unset($row['items_json']);
        }

        echo json_encode([
            'status' => 'success',
            'count'  => count($orders),
            'orders' => $orders
        ]);
        exit;
    }

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        $input     = json_decode((string) file_get_contents('php://input'), true) ?: [];
        $txRef     = trim((string) ($input['tx_ref'] ?? ''));
        $newStatus = strtoupper(trim((string) ($input['fulfillment_status'] ?? '')));

        $allowedStatuses = ['NEW', 'PREPARING', 'READY_OR_DISPATCHED', 'COMPLETED', 'CANCELLED'];
        if ( empty($txRef) || !in_array($newStatus, $allowedStatuses, true) ) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'Valid tx_ref and fulfillment_status required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM st_direct_wa_orders WHERE tx_ref = :txref LIMIT 1");
        $stmt->execute([':txref' => $txRef]);
        $order = $stmt->fetch();

        if ( !$order ) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Order not found']);
            exit;
        }

        $upd = $pdo->prepare("UPDATE st_direct_wa_orders SET fulfillment_status = :fstatus WHERE id = :id");
        $upd->execute([
            ':fstatus' => $newStatus,
            ':id'      => $order['id']
        ]);

        $waService = new WhatsAppCloudService();
        $waResult  = $waService->sendFulfillmentStatusUpdate($order, $newStatus);

        echo json_encode([
            'status'             => 'success',
            'tx_ref'             => $txRef,
            'fulfillment_status' => $newStatus,
            'whatsapp_notified'  => $waResult['success']
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);

} catch ( Exception $e ) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
