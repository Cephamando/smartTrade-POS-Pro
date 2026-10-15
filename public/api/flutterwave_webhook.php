<?php
/**
 * Flutterwave webhook receiver.
 * Dashboard → Settings → Webhooks → URL: https://<host>/api/flutterwave_webhook.php
 * Secret hash must match Settings → Payments → "Webhook secret hash".
 */
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Services\Flutterwave;

$headers = array_change_key_case(function_exists('getallheaders') ? getallheaders() : [], CASE_LOWER);
$raw = (string)file_get_contents('php://input');
$signature = (string)($headers['flutterwave-signature'] ?? $headers['verif-hash'] ?? $_SERVER['HTTP_FLUTTERWAVE_SIGNATURE'] ?? $_SERVER['HTTP_VERIF_HASH'] ?? '');

try {
    $flw = new Flutterwave($pdo);
    if (!$flw->webhookHashMatches($raw, $signature)) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Bad signature']);
        exit;
    }
    $payload = json_decode($raw, true);
    if (!is_array($payload)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid JSON']);
        exit;
    }
    $flw->handleWebhook($payload);
    echo json_encode(['status' => 'ok']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
