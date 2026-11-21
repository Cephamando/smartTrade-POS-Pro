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

$input  = json_decode((string) file_get_contents('php://input'), true) ?: [];
$name   = trim((string) ($input['attendee_name'] ?? ''));
$email  = trim((string) ($input['attendee_email'] ?? ''));
$phone  = trim((string) ($input['attendee_phone'] ?? ''));
$tier   = trim((string) ($input['ticket_tier'] ?? 'Standard Pass'));
$amount = round((float) ($input['amount_zmw'] ?? 150.00), 2);

if ($name === '' || $email === '' || $phone === '') {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Missing required registration fields']);
    exit;
}

try {
    $db = TeamsRegistrationService::getDb();
    $txRef = 'TEAMS-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    $receiptToken = TeamsRegistrationService::generateToken();

    $stmt = $db->prepare("
        INSERT INTO teams_registrations
        (tx_ref, receipt_token, attendee_name, attendee_email, attendee_phone, ticket_tier, amount_zmw, payment_status)
        VALUES (:txref, :tok, :name, :email, :phone, :tier, :amt, 'PENDING')
    ");
    $stmt->execute([
        ':txref' => $txRef,
        ':tok'   => $receiptToken,
        ':name'  => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':tier'  => $tier,
        ':amt'   => $amount
    ]);

    echo json_encode([
        'status'        => 'success',
        'tx_ref'        => $txRef,
        'receipt_token' => $receiptToken,
        'amount_zmw'    => $amount
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
