<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/app/bootstrap.php';

use App\Services\EdgeSync;

// CLI mode: triggered by cron or edge-sync-cron.sh on the Offline Shop PC
if (PHP_SAPI === 'cli' && in_array('--cli', $argv ?? [], true)) {
    $sync = new EdgeSync($pdo);
    $force = in_array('--force', $argv ?? [], true);
    $res = $sync->syncNow($force);
    echo json_encode($res, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit(in_array($res['status'] ?? '', ['ok', 'skip', 'throttled'], true) ? 0 : 1);
}

header('Content-Type: application/json');

$sync = new EdgeSync($pdo);
$headers = function_exists('getallheaders') ? array_change_key_case((array)getallheaders(), CASE_LOWER) : [];
$auth = (string)($headers['authorization'] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? ($_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '')));

if (!$sync->verifyBearer($auth)) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'msg' => 'Unauthorized Edge Sync token.']);
    exit;
}

$action = (string)($_GET['action'] ?? '');
$rawBody = file_get_contents('php://input');
$payload = is_string($rawBody) && $rawBody !== '' ? (json_decode($rawBody, true) ?: []) : [];

try {
    if ($action === 'push') {
        $result = $sync->handleIncomingPush(is_array($payload) ? $payload : []);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'pull') {
        $data = $sync->buildPullPayload(is_array($payload) ? $payload : []);
        echo json_encode(['status' => 'ok', 'data' => $data], JSON_UNESCAPED_UNICODE);
        exit;
    }
    if ($action === 'ping') {
        echo json_encode(['status' => 'ok', 'summary' => $sync->statusSummary()], JSON_UNESCAPED_UNICODE);
        exit;
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'msg' => 'Unknown sync action.']);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'msg' => $e->getMessage() ?: 'Sync failure.']);
}
