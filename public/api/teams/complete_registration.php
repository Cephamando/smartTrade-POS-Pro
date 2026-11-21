<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../app/Services/WhatsAppCloudService.php';
require_once __DIR__ . '/../../../app/Services/TeamsRegistrationService.php';

use SmartTrade\Services\WhatsAppCloudService;
use SmartTrade\Services\TeamsRegistrationService;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method Not Allowed']);
    exit;
}

$input         = json_decode((string) file_get_contents('php://input'), true) ?: [];
$txRef         = trim((string) ($input['tx_ref'] ?? ''));
$transactionId = trim((string) ($input['transaction_id'] ?? ''));

if ($txRef === '' || $transactionId === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Missing tx_ref or transaction_id']);
    exit;
}

try {
    $db = TeamsRegistrationService::getDb();
    $flwConfig = WhatsAppCloudService::getFlwConfig($db);
    $flwSecret = $flwConfig['secret_key'];

    // 1. Verify charge with Flutterwave
    $verifiedAmount = 0.00;
    $verifiedStatus = 'successful';
    if ($flwSecret !== 'YOUR_FLW_SECRET_KEY' && $flwSecret !== '') {
        $ch = curl_init('https://api.flutterwave.com/v3/transactions/' . urlencode($transactionId) . '/verify');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $flwSecret, 'Content-Type: application/json'],
            CURLOPT_TIMEOUT => 10
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);
        $vData = json_decode((string)$resp, true)['data'] ?? [];
        if (!empty($vData)) {
            $verifiedStatus = strtolower((string)($vData['status'] ?? ''));
            $verifiedAmount = (float)($vData['amount'] ?? 0.00);
        }
    }

    $stmt = $db->prepare("SELECT * FROM teams_registrations WHERE tx_ref = :txref LIMIT 1");
    $stmt->execute([':txref' => $txRef]);
    $reg = $stmt->fetch();

    if (!$reg) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Registration record not found']);
        exit;
    }

    if ($verifiedStatus !== 'successful' || $verifiedAmount < (float)$reg['amount_zmw']) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Payment verification failed']);
        exit;
    }

    // 2. Fetch Microsoft Teams Join URL via Graph API (or fallback)
    $teamsJoinUrl = TeamsRegistrationService::fetchMicrosoftTeamsJoinUrl((string)$reg['attendee_name'], (string)$reg['attendee_email']);

    // 3. Mark paid & store join URL
    $db->prepare("
        UPDATE teams_registrations
        SET payment_status = 'PAID',
            flw_transaction_id = :flwid,
            teams_join_url = :url
        WHERE id = :id
    ")->execute([
        ':flwid' => $transactionId,
        ':url'   => $teamsJoinUrl,
        ':id'    => $reg['id']
    ]);

    $hostedJoinRoute = WhatsAppCloudService::env('APP_URL', 'https://smartrade.odeliazm.com') . '/teams/join/' . $reg['receipt_token'];

    echo json_encode([
        'status'         => 'success',
        'tx_ref'         => $txRef,
        'teams_join_url' => $hostedJoinRoute
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
