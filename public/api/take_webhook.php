<?php
/**
 * Take App webhook.
 * Take App → Settings → Apps → Webhooks
 * URL: https://smartrade.odeliazm.com/api/take_webhook.php
 * Events: order.created, order.updated
 * Signing secret: Settings → Customer ordering → Take App signing secret
 */
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Core\Schema;
use App\Models\Setting;
use App\Services\TakeOrder;

$headers = array_change_key_case(function_exists('getallheaders') ? getallheaders() : [], CASE_LOWER);
foreach ([
    'x-take-signature' => 'HTTP_X_TAKE_SIGNATURE',
    'x-take-delivery-id' => 'HTTP_X_TAKE_DELIVERY_ID',
    'x-take-event' => 'HTTP_X_TAKE_EVENT',
] as $name => $server) {
    if (($headers[$name] ?? '') === '' && !empty($_SERVER[$server])) {
        $headers[$name] = (string)$_SERVER[$server];
    }
}
$raw = (string)file_get_contents('php://input');

try {
    $settings = Setting::all();
    Schema::ensure($pdo, $settings);
    $result = TakeOrder::accept($pdo, $settings, $raw, $headers);
    http_response_code($result['status']);
    echo json_encode(['status' => $result['ok'] ? 'ok' : 'error', 'message' => $result['message']]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Could not accept the order']);
}
