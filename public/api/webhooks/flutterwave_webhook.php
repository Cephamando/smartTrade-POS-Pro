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

$rawBody    = file_get_contents('php://input');
$secretHash = WhatsAppCloudService::env('FLW_SECRET_HASH', 'YOUR_FLW_WEBHOOK_SECRET_HASH');
$flwSecret  = WhatsAppCloudService::env('FLW_SECRET_KEY', 'YOUR_FLW_SECRET_KEY');

$verifHashHeader = $_SERVER['HTTP_VERIF_HASH'] ?? '';
$flwSigHeader    = $_SERVER['HTTP_FLUTTERWAVE_SIGNATURE'] ?? '';

if ( $secretHash !== 'YOUR_FLW_WEBHOOK_SECRET_HASH' ) {
    $isValidSig = false;
    if ( !empty($verifHashHeader) && hash_equals($secretHash, $verifHashHeader) ) {
        $isValidSig = true;
    } elseif ( !empty($flwSigHeader) ) {
        $computedHmac = base64_encode(hash_hmac('sha256', $rawBody, $secretHash, true));
        if ( hash_equals($computedHmac, $flwSigHeader) ) {
            $isValidSig = true;
        }
    }

    if ( !$isValidSig ) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid Flutterwave webhook signature']);
        exit;
    }
}

$payload = json_decode($rawBody, true);
if ( !is_array($payload) ) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Malformed JSON payload']);
    exit;
}

$eventData     = $payload['data'] ?? $payload;
$transactionId = (string) ($eventData['id'] ?? '');
$txRef         = (string) ($eventData['tx_ref'] ?? $eventData['txRef'] ?? '');
$flwRef        = (string) ($eventData['flw_ref'] ?? '');
$webhookStatus = strtolower((string) ($eventData['status'] ?? ''));

if ( empty($txRef) || empty($transactionId) ) {
    http_response_code(200);
    echo json_encode(['status' => 'ignored_missing_reference']);
    exit;
}

$verifiedData = [];
if ( !str_starts_with($flwRef, 'FLW-ZM-MOCK-') && $flwSecret !== 'YOUR_FLW_SECRET_KEY' ) {
    $verifyUrl = 'https://api.flutterwave.com/v3/transactions/' . urlencode($transactionId) . '/verify';
    $ch = curl_init($verifyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $flwSecret,
            'Content-Type: application/json'
        ],
        CURLOPT_TIMEOUT        => 15
    ]);
    $verifyResBody = curl_exec($ch);
    curl_close($ch);
    $verifiedData = json_decode((string) $verifyResBody, true)['data'] ?? [];
}

$verifiedStatus = strtolower((string) ($verifiedData['status'] ?? $webhookStatus));
$verifiedAmount = (float) ($verifiedData['amount'] ?? ($eventData['amount'] ?? 0.00));
$verifiedCurr   = strtoupper((string) ($verifiedData['currency'] ?? ($eventData['currency'] ?? 'ZMW')));

try {
    $pdo = WhatsAppCloudService::getDb();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT * FROM st_direct_wa_orders WHERE tx_ref = :txref LIMIT 1 FOR UPDATE");
    $stmt->execute([':txref' => $txRef]);
    $order = $stmt->fetch();

    if ( !$order ) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Order not found']);
        exit;
    }

    if ( $order['payment_status'] === 'PAID' ) {
        $pdo->rollBack();
        http_response_code(200);
        echo json_encode(['status' => 'already_processed']);
        exit;
    }

    $expectedTotal = (float) $order['total_zmw'];

    if ( $verifiedStatus === 'successful' && $verifiedCurr === 'ZMW' && $verifiedAmount >= $expectedTotal ) {
        $hasProductsTable = (bool) $pdo->query("SHOW TABLES LIKE 'products'")->fetchColumn();
        if ( $hasProductsTable ) {
            $colsStmt = $pdo->query("SHOW COLUMNS FROM products");
            $colNames = array_column($colsStmt->fetchAll(), 'Field');
            $stockCol = null;
            foreach ( ['stock_qty', 'stock', 'quantity'] as $candidateCol ) {
                if ( in_array($candidateCol, $colNames, true) ) {
                    $stockCol = $candidateCol;
                    break;
                }
            }

            if ( $stockCol !== null ) {
                $items = json_decode((string) $order['items_json'], true) ?: [];
                $deductStmt = $pdo->prepare("
                    UPDATE products
                    SET `{$stockCol}` = GREATEST(0, `{$stockCol}` - :qty)
                    WHERE id = :pid
                ");
                foreach ( $items as $item ) {
                    $deductStmt->execute([
                        ':qty' => (int) $item['qty'],
                        ':pid' => (int) $item['product_id']
                    ]);
                }
            }
        }

        $zraReceiptNo = 'ZRA-VSDC-' . date('Ymd') . '-' . $order['id'];
        $zraQrUrl     = 'https://smarTrade.odeliazm.com/verify_receipt.php?rcpt=' . urlencode($zraReceiptNo);

        $upd = $pdo->prepare("
            UPDATE st_direct_wa_orders
            SET payment_status = 'PAID',
                fulfillment_status = 'NEW',
                flw_transaction_id = :flwid,
                zra_fiscal_status = 'FISCALIZED',
                zra_rcpt_no = :zrarcp,
                zra_qr_url = :zraqr
            WHERE id = :id
        ");
        $upd->execute([
            ':flwid'  => $transactionId,
            ':zrarcp' => $zraReceiptNo,
            ':zraqr'  => $zraQrUrl,
            ':id'     => $order['id']
        ]);

        $pdo->commit();

        $waService = new WhatsAppCloudService();
        $waService->sendOrderPaidNotifications($order, $zraReceiptNo, $zraQrUrl);

        http_response_code(200);
        echo json_encode([
            'status'         => 'success',
            'tx_ref'         => $txRef,
            'zra_receipt_no' => $zraReceiptNo,
            'zra_qr_url'     => $zraQrUrl
        ]);
        exit;
    } else {
        $upd = $pdo->prepare("UPDATE st_direct_wa_orders SET payment_status = 'FAILED', flw_transaction_id = :flwid WHERE id = :id");
        $upd->execute([
            ':flwid' => $transactionId,
            ':id'    => $order['id']
        ]);
        $pdo->commit();

        http_response_code(200);
        echo json_encode(['status' => 'payment_failed_recorded']);
        exit;
    }

} catch ( Exception $e ) {
    if ( isset($pdo) && $pdo->inTransaction() ) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
