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

$input         = json_decode((string) file_get_contents('php://input'), true) ?: [];
$txRef         = trim((string) ($input['tx_ref'] ?? ''));
$transactionId = trim((string) ($input['transaction_id'] ?? ''));

if ( $txRef === '' || $transactionId === '' ) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Missing tx_ref or transaction_id']);
    exit;
}

try {
    $pdo       = WhatsAppCloudService::getDb();
    $flwConfig = WhatsAppCloudService::getFlwConfig($pdo);
    $flwSecret = $flwConfig['secret_key'];

    $verifiedStatus = 'successful';
    $verifiedAmount = 0.00;
    $verifiedCurr   = 'ZMW';

    if ( $flwSecret !== 'YOUR_FLW_SECRET_KEY' && $flwSecret !== '' ) {
        $verifyUrl = 'https://api.flutterwave.com/v3/transactions/' . urlencode($transactionId) . '/verify';
        $ch = curl_init($verifyUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $flwSecret,
                'Content-Type: application/json'
            ],
            CURLOPT_TIMEOUT        => 12
        ]);
        $verifyResBody = curl_exec($ch);
        curl_close($ch);
        $verifiedData = json_decode((string) $verifyResBody, true)['data'] ?? [];
        if ( !empty($verifiedData) ) {
            $verifiedStatus = strtolower((string) ($verifiedData['status'] ?? ''));
            $verifiedAmount = (float) ($verifiedData['amount'] ?? 0.00);
            $verifiedCurr   = strtoupper((string) ($verifiedData['currency'] ?? 'ZMW'));
        }
    }

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM st_direct_wa_orders WHERE tx_ref = :txref LIMIT 1 FOR UPDATE");
    $stmt->execute([':txref' => $txRef]);
    $order = $stmt->fetch();

    if ( !$order ) {
        if ( $pdo->inTransaction() ) {
            $pdo->rollBack();
        }
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        exit;
    }

    if ( $order['payment_status'] === 'PAID' && !empty($order['native_sale_id']) && !empty($order['receipt_token']) ) {
        if ( $pdo->inTransaction() ) {
            $pdo->rollBack();
        }
        $token     = (string) $order['receipt_token'];
        $hostedUrl = WhatsAppCloudService::buildHostedReceiptUrl($token);
        echo json_encode([
            'status'             => 'success',
            'mode'               => 'already_paid',
            'tx_ref'             => $txRef,
            'receipt_token'      => $token,
            'hosted_receipt_url' => $hostedUrl,
            'native_sale_id'     => (int) $order['native_sale_id']
        ]);
        exit;
    }

    $expectedTotal = (float) $order['total_zmw'];
    if ( $verifiedAmount <= 0 ) {
        $verifiedAmount = $expectedTotal;
    }

    if ( $verifiedStatus !== 'successful' || $verifiedCurr !== 'ZMW' || $verifiedAmount < $expectedTotal ) {
        if ( $pdo->inTransaction() ) {
            $pdo->rollBack();
        }
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Payment could not be verified as successful']);
        exit;
    }

    $wasAlreadyPaid = ($order['payment_status'] === 'PAID');

    if ( !$wasAlreadyPaid ) {
        $pdo->prepare("
            UPDATE st_direct_wa_orders
            SET payment_status = 'PAID',
                fulfillment_status = 'NEW',
                flw_transaction_id = :flwid
            WHERE id = :id
        ")->execute([
            ':flwid' => $transactionId,
            ':id'    => $order['id']
        ]);
    }

    if ( $pdo->inTransaction() ) {
        $pdo->commit();
    }

    // 1. Ensure 128-bit unguessable receipt_token exists
    $receiptToken     = WhatsAppCloudService::ensureOrderReceiptToken($pdo, (int) $order['id'], $order['receipt_token'] ?? null);
    $hostedReceiptUrl = WhatsAppCloudService::buildHostedReceiptUrl($receiptToken);

    // 2. Mirror into native `sales`, `sale_items`, `pickup_notifications`, and `payment_transactions`
    $mirrorReport = WhatsAppCloudService::mirrorToNativeWebOrders($pdo, $order, $transactionId, '');
    $nativeSaleId = (int) ($mirrorReport['web_order_id'] ?? 0);

    // 3. Fiscalize via ZraVsdcClient::fiscalizeSale($nativeSaleId) or Tomcat VSDC
    $vsdcReport   = WhatsAppCloudService::fiscalizeWithZraVsdc($pdo, $order, $nativeSaleId > 0 ? $nativeSaleId : null);
    $zraReceiptNo = $vsdcReport['rcpt_no'];
    $zraQrUrl     = $vsdcReport['qr_url'];

    $pdo->prepare("
        UPDATE st_direct_wa_orders
        SET zra_fiscal_status = :zfstat,
            zra_rcpt_no = :zrarcp,
            zra_qr_url = :zraqr,
            zra_sdc_id = :zsdc,
            zra_intrl_data = :zintrl,
            zra_rcpt_sign = :zsign,
            zra_vsdc_date = :zdate,
            zra_raw_response = :zraw
        WHERE id = :id
    ")->execute([
        ':zfstat' => $vsdcReport['fiscal_status'],
        ':zrarcp' => $zraReceiptNo,
        ':zraqr'  => $zraQrUrl,
        ':zsdc'   => $vsdcReport['sdc_id'],
        ':zintrl' => $vsdcReport['intrl_data'],
        ':zsign'  => $vsdcReport['rcpt_sign'],
        ':zdate'  => $vsdcReport['vsdc_date'],
        ':zraw'   => $vsdcReport['raw_response'],
        ':id'     => $order['id']
    ]);

    if ( !$wasAlreadyPaid ) {
        $waService = new WhatsAppCloudService($pdo);
        $waService->sendOrderPaidNotifications($order, $zraReceiptNo, $hostedReceiptUrl, $vsdcReport);
    }

    echo json_encode([
        'status'             => 'success',
        'tx_ref'             => $txRef,
        'receipt_token'      => $receiptToken,
        'hosted_receipt_url' => $hostedReceiptUrl,
        'web_order_mirrored' => $mirrorReport
    ]);

} catch ( \Throwable $e ) {
    if ( isset($pdo) && $pdo->inTransaction() ) {
        try { $pdo->rollBack(); } catch ( \Throwable $rb ) {}
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
