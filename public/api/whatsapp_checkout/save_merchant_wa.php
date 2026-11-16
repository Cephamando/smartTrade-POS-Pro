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

$input     = json_decode((string) file_get_contents('php://input'), true) ?: $_POST;
$rawPhone  = trim((string) ($input['merchant_whatsapp_number'] ?? $input['whatsapp_number'] ?? ''));
$normPhone = WhatsAppCloudService::normalizeMsisdn($rawPhone);

if ( $rawPhone !== '' && $normPhone === '' ) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => 'Invalid phone number format. Enter a valid Zambian number (e.g., 0977123456).']);
    exit;
}

try {
    $pdo = WhatsAppCloudService::getDb();
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value) VALUES ('merchant_whatsapp_number', ?)
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
    ");
    $stmt->execute([$normPhone]);

    echo json_encode([
        'status'                   => 'success',
        'merchant_whatsapp_number' => $normPhone,
        'message'                  => 'Merchant WhatsApp number saved successfully.'
    ]);
} catch ( \Throwable $e ) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
