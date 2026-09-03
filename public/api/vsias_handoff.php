<?php
header('Content-Type: application/json');
require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Helpers\VsiasApi;
use App\Services\VsiasBridge;

VsiasApi::requireToken();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    VsiasApi::json(['status' => 'error', 'message' => 'POST required'], 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode((string)$raw, true);
if (!is_array($payload) || empty($payload['tracking_code']) || empty($payload['items']) || !is_array($payload['items'])) {
    VsiasApi::json(['status' => 'error', 'message' => 'Invalid JSON payload'], 400);
}

try {
    $bridge = new VsiasBridge($pdo);
    $result = $bridge->ingest($payload);
    $code = ($result['status'] ?? '') === 'error' ? 422 : 200;
    VsiasApi::json($result, $code);
} catch (Throwable $e) {
    VsiasApi::json(['status' => 'error', 'message' => $e->getMessage()], 500);
}
