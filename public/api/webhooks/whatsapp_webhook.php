<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../app/Services/WhatsAppCloudService.php';

use SmartTrade\Services\WhatsAppCloudService;

// 1. GET Request: Meta WhatsApp Cloud API Webhook Verification Challenge
if ( $_SERVER['REQUEST_METHOD'] === 'GET' ) {
    $mode        = $_GET['hub_mode'] ?? ($_GET['hub.mode'] ?? '');
    $verifyToken = $_GET['hub_verify_token'] ?? ($_GET['hub.verify_token'] ?? '');
    $challenge   = $_GET['hub_challenge'] ?? ($_GET['hub.challenge'] ?? '');

    $expectedToken = WhatsAppCloudService::env('WA_WEBHOOK_VERIFY_TOKEN', 'smarttrade_wa_verify_2026');

    if ( $mode === 'subscribe' && hash_equals($expectedToken, (string) $verifyToken) ) {
        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(200);
        echo (string) $challenge;
        exit;
    }

    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Webhook verification failed']);
    exit;
}

// 2. POST Request: Inbound Customer WhatsApp Messages (Auto-Menu & Order Status)
if ( $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$rawBody = file_get_contents('php://input');
$payload = json_decode((string) $rawBody, true);

if ( !is_array($payload) ) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
    exit;
}

$entry    = $payload['entry'][0]['changes'][0]['value'] ?? [];
$messages = $entry['messages'] ?? [];

if ( empty($messages) || !is_array($messages) ) {
    http_response_code(200);
    echo json_encode(['status' => 'status_event_ignored']);
    exit;
}

$msgObj      = $messages[0];
$senderPhone = (string) ($msgObj['from'] ?? '');
$textBody    = trim((string) ($msgObj['text']['body'] ?? ''));

if ( $senderPhone === '' || $textBody === '' ) {
    http_response_code(200);
    echo json_encode(['status' => 'empty_message_ignored']);
    exit;
}

$storeTitle = WhatsAppCloudService::env('STORE_BRAND_NAME', 'smartTrade Express Store');
$publicUrl  = rtrim(WhatsAppCloudService::env('APP_URL', 'https://smartrade.odeliazm.com'), '/');
$storeLink  = $publicUrl . '/storefront/index.php?branch=1';
$waService  = new WhatsAppCloudService();

try {
    if ( preg_match('/(ST-FLW-[A-Z0-9]+-\d+)/i', $textBody, $matches) ) {
        $lookupRef = strtoupper($matches[1]);
        $pdo       = WhatsAppCloudService::getDb();
        $stmt      = $pdo->prepare("SELECT * FROM st_direct_wa_orders WHERE tx_ref = :txref LIMIT 1");
        $stmt->execute([':txref' => $lookupRef]);
        $order     = $stmt->fetch();

        if ( $order ) {
            $reply  = "📦 *ORDER STATUS ({$order['tx_ref']})*\n\n";
            $reply .= "• *Customer:* {$order['customer_name']}\n";
            $reply .= "• *Payment:* {$order['payment_status']} (ZMW " . number_format((float) $order['total_zmw'], 2) . ")\n";
            $reply .= "• *Kitchen Status:* {$order['fulfillment_status']}\n";
            if ( !empty($order['zra_rcpt_no']) ) {
                $reply .= "• *ZRA Smart Invoice:* {$order['zra_rcpt_no']}\n";
            }
            if ( !empty($order['zra_qr_url']) ) {
                $reply .= "• *Verify Receipt:* {$order['zra_qr_url']}\n";
            }
        } else {
            $reply = "🔍 We could not find an order matching *{$lookupRef}*. Tap below to place a new order:\n{$storeLink}";
        }

        $waService->sendTextMessage($senderPhone, $reply);

        http_response_code(200);
        echo json_encode(['status' => 'order_lookup_replied', 'tx_ref' => $lookupRef]);
        exit;
    }

    $welcomeMsg  = "👋 Welcome to *{$storeTitle}*!\n\n";
    $welcomeMsg .= "🛒 *Tap the link below to browse our live menu & pay instantly with Airtel Money, MTN MoMo, or Card:*\n";
    $welcomeMsg .= "{$storeLink}\n\n";
    $welcomeMsg .= "🧾 _Every paid order automatically issues an official ZRA Smart Invoice receipt._\n\n";
    $welcomeMsg .= "💡 *Tip:* Already ordered? Reply with your Order Reference (e.g., `ST-FLW-...`) to check your live kitchen status!";

    $waService->sendTextMessage($senderPhone, $welcomeMsg);

    http_response_code(200);
    echo json_encode(['status' => 'menu_link_sent']);

} catch ( Exception $e ) {
    http_response_code(200);
    echo json_encode(['status' => 'error_handled', 'message' => $e->getMessage()]);
}
