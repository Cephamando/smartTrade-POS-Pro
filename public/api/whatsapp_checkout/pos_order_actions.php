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

        // Reconcile any ST-FLW-* orders handed over via the native Web Orders UI
        $autoHandedOver = WhatsAppCloudService::reconcileHandedOverWebOrders($pdo);

        $stmt = $pdo->prepare("
            SELECT id, branch_id, tx_ref, flw_transaction_id, customer_name, customer_phone,
                   delivery_type, delivery_address, subtotal_zmw, delivery_fee_zmw, total_zmw,
                   items_json, payment_status, fulfillment_status, zra_fiscal_status,
                   zra_rcpt_no, zra_qr_url, zra_sdc_id, zra_rcpt_sign, created_at
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
            'status'                 => 'success',
            'count'                  => count($orders),
            'reconciled_hand_overs'  => $autoHandedOver,
            'orders'                 => $orders
        ]);
        exit;
    }

    if ( $_SERVER['REQUEST_METHOD'] === 'POST' ) {
        $input  = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
        $action = strtolower(trim((string) ($input['action'] ?? 'update_status')));

        // Action A: Purge simulated webhook test orders from both st_direct_wa_orders and native Web Orders
        if ( $action === 'purge_test_orders' ) {
            $flwConfig    = WhatsAppCloudService::getFlwConfig($pdo);
            $expectedHash = $flwConfig['secret_hash'];
            $providedHash = $_SERVER['HTTP_VERIF_HASH'] ?? (string) ($input['secret_hash'] ?? '');

            if ( $expectedHash !== 'YOUR_FLW_WEBHOOK_SECRET_HASH' && $expectedHash !== '' ) {
                if ( !hash_equals($expectedHash, $providedHash) ) {
                    http_response_code(401);
                    echo json_encode(['status' => 'error', 'message' => 'Unauthorized purge request']);
                    exit;
                }
            }

            $deletedWa     = $pdo->exec("DELETE FROM st_direct_wa_orders WHERE customer_name LIKE '%Webhook Test%' OR flw_transaction_id LIKE '9917%'");
            $deletedNative = WhatsAppCloudService::purgeNativeWebOrderTests($pdo);

            echo json_encode([
                'status'               => 'success',
                'action'               => 'purge_test_orders',
                'deleted_wa_count'     => $deletedWa,
                'deleted_native_count' => $deletedNative
            ]);
            exit;
        }

        // Action B: Direct Hand Over trigger from native Web Orders UI
        if ( in_array($action, ['hand_over', 'handover', 'complete'], true) ) {
            $identifier = $input['tx_ref'] ?? $input['order_id'] ?? $input['web_order_id'] ?? $input['id'] ?? '';
            $res = WhatsAppCloudService::onNativeHandOver((string) $identifier, $pdo);
            http_response_code($res['success'] ? 200 : 404);
            echo json_encode($res);
            exit;
        }

        // Action C: Transition kitchen fulfillment_status, sync native Web Orders Hand Over state, and notify buyer
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

        $nativeSynced = WhatsAppCloudService::syncNativeWebOrderStatus(
            $pdo,
            $txRef,
            $newStatus,
            (string) ($order['flw_transaction_id'] ?? '')
        );

        $waService = new WhatsAppCloudService();
        $waResult  = $waService->sendFulfillmentStatusUpdate($order, $newStatus);

        echo json_encode([
            'status'             => 'success',
            'tx_ref'             => $txRef,
            'fulfillment_status' => $newStatus,
            'native_web_synced'  => $nativeSynced,
            'whatsapp_notified'  => $waResult['success']
        ]);
        exit;
    }

    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);

} catch ( \Throwable $e ) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
