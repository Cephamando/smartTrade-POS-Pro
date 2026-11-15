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

$rawBody = isset($rawWebhookBody) ? (string) $rawWebhookBody : (string) file_get_contents('php://input');
$pdo     = null;

try {
    $pdo       = WhatsAppCloudService::getDb();
    $flwConfig = WhatsAppCloudService::getFlwConfig($pdo);
} catch ( \Throwable $e ) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB Init Error: ' . $e->getMessage()]);
    exit;
}

$secretHash = $flwConfig['secret_hash'];
$flwSecret  = $flwConfig['secret_key'];

$verifHashHeader = $_SERVER['HTTP_VERIF_HASH'] ?? '';
$flwSigHeader    = $_SERVER['HTTP_FLUTTERWAVE_SIGNATURE'] ?? '';

if ( $secretHash !== 'YOUR_FLW_WEBHOOK_SECRET_HASH' && $secretHash !== '' ) {
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
if ( !str_starts_with($flwRef, 'FLW-ZM-MOCK-') && $flwSecret !== 'YOUR_FLW_SECRET_KEY' && $flwSecret !== '' ) {
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

    if ( $order['payment_status'] === 'PAID' ) {
        if ( $pdo->inTransaction() ) {
            $pdo->rollBack();
        }
        // Ensure native sales & pickup_notifications mirror exists even if complete_order.php ran first
        if ( empty($order['native_sale_id']) ) {
            WhatsAppCloudService::mirrorToNativeWebOrders($pdo, $order, $transactionId, (string) ($order['zra_rcpt_no'] ?? ''));
        }
        http_response_code(200);
        echo json_encode(['status' => 'already_processed']);
        exit;
    }

    $expectedTotal = (float) $order['total_zmw'];

    if ( $verifiedStatus === 'successful' && $verifiedCurr === 'ZMW' && $verifiedAmount >= $expectedTotal ) {
        $lockUpd = $pdo->prepare("
            UPDATE st_direct_wa_orders
            SET payment_status = 'PAID',
                fulfillment_status = 'NEW',
                flw_transaction_id = :flwid
            WHERE id = :id
        ");
        $lockUpd->execute([
            ':flwid' => $transactionId,
            ':id'    => $order['id']
        ]);

        // Deduct stock from products or inventory table
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

            $items = json_decode((string) $order['items_json'], true) ?: [];
            if ( $stockCol !== null ) {
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
            } elseif ( (bool) $pdo->query("SHOW TABLES LIKE 'inventory'")->fetchColumn() ) {
                $deductInv = $pdo->prepare("
                    UPDATE inventory
                    SET quantity = GREATEST(0, quantity - :qty)
                    WHERE product_id = :pid AND location_id = :loc
                ");
                foreach ( $items as $item ) {
                    $deductInv->execute([
                        ':qty' => (int) $item['qty'],
                        ':pid' => (int) $item['product_id'],
                        ':loc' => (int) $order['branch_id']
                    ]);
                }
            }
        }

        if ( $pdo->inTransaction() ) {
            $pdo->commit();
        }

        // Mirror into native `sales`, `sale_items`, `pickup_notifications`, and `payment_transactions` first
        $mirrorReport = WhatsAppCloudService::mirrorToNativeWebOrders($pdo, $order, $transactionId, '');
        $nativeSaleId = (int) ($mirrorReport['web_order_id'] ?? 0);

        // Fiscalize via ZraVsdcClient::fiscalizeSale($nativeSaleId) or Tomcat VSDC
        $vsdcReport   = WhatsAppCloudService::fiscalizeWithZraVsdc($pdo, $order, $nativeSaleId > 0 ? $nativeSaleId : null);
        $zraReceiptNo = $vsdcReport['rcpt_no'];
        $zraQrUrl     = $vsdcReport['qr_url'];

        $upd = $pdo->prepare("
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
        ");
        $upd->execute([
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

        $waService = new WhatsAppCloudService($pdo);
        $waService->sendOrderPaidNotifications($order, $zraReceiptNo, $zraQrUrl, $vsdcReport);

        http_response_code(200);
        echo json_encode([
            'status'             => 'success',
            'tx_ref'             => $txRef,
            'zra_receipt_no'     => $zraReceiptNo,
            'zra_qr_url'         => $zraQrUrl,
            'zra_vsdc'           => [
                'live_signed' => $vsdcReport['live_signed'],
                'tax_regime'  => $vsdcReport['tax_regime'] ?? 'TOT',
                'invoked_via' => $vsdcReport['invoked_via'],
                'result_cd'   => $vsdcReport['result_cd'],
                'result_msg'  => $vsdcReport['result_msg'],
                'sdc_id'      => $vsdcReport['sdc_id'],
                'intrl_data'  => $vsdcReport['intrl_data'],
                'rcpt_sign'   => $vsdcReport['rcpt_sign'],
                'vsdc_date'   => $vsdcReport['vsdc_date']
            ],
            'web_order_mirrored' => $mirrorReport
        ]);
        exit;
    } else {
        $upd = $pdo->prepare("UPDATE st_direct_wa_orders SET payment_status = 'FAILED', flw_transaction_id = :flwid WHERE id = :id");
        $upd->execute([
            ':flwid' => $transactionId,
            ':id'    => $order['id']
        ]);
        if ( $pdo->inTransaction() ) {
            $pdo->commit();
        }

        http_response_code(200);
        echo json_encode(['status' => 'payment_failed_recorded']);
        exit;
    }

} catch ( \Throwable $e ) {
    if ( isset($pdo) && $pdo->inTransaction() ) {
        try {
            $pdo->rollBack();
        } catch ( \Throwable $rbErr ) {
        }
    }
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => $e->getMessage(),
        'file'    => basename($e->getFile()),
        'line'    => $e->getLine()
    ]);
}
