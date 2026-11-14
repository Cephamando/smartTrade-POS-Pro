<?php
declare(strict_types=1);

/**
 * Unified Flutterwave Webhook Router
 * Endpoint: https://smartrade.odeliazm.com/api/flutterwave_webhook.php
 */

$rawWebhookBody = file_get_contents('php://input');
$decodedPayload = json_decode((string) $rawWebhookBody, true);

$txRef = '';
if ( is_array($decodedPayload) ) {
    $eventData = $decodedPayload['data'] ?? $decodedPayload;
    if ( is_array($eventData) ) {
        $txRef = trim((string) ($eventData['tx_ref'] ?? $eventData['txRef'] ?? $decodedPayload['tx_ref'] ?? $decodedPayload['txRef'] ?? ''));
    }
}

// 1. Route WhatsApp Express Orders (ST-FLW-*) to the dedicated WhatsApp handler
if ( $txRef !== '' && str_starts_with(strtoupper($txRef), 'ST-FLW-') ) {
    require __DIR__ . '/webhooks/flutterwave_webhook.php';
    exit;
}

// 2. Route standard Web Orders / Till / Event transactions to the native handler
$nativeHandler = __DIR__ . '/flutterwave_webhook_native.php';
if ( is_file($nativeHandler) ) {
    require $nativeHandler;
    exit;
}

// 3. Fallback if native handler file is not present in a standalone test container
require __DIR__ . '/webhooks/flutterwave_webhook.php';
exit;
