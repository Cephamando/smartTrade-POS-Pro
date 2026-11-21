<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/TeamsRegistrationService.php';

use SmartTrade\Services\TeamsRegistrationService;

$token = trim((string)($_GET['t'] ?? ''));
if ($token === '') {
    $path = (string)parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if (preg_match('#/teams/join/([A-Za-z0-9_-]+)#', $path, $m)) {
        $token = $m[1];
    }
}

if (!preg_match('/^[A-Za-z0-9_-]{22,64}$/', $token)) {
    http_response_code(404);
    echo "404 - Invalid or Expired Meeting Access Token";
    exit;
}

try {
    $db = TeamsRegistrationService::getDb();
    $stmt = $db->prepare("SELECT teams_join_url FROM teams_registrations WHERE receipt_token = :tok AND payment_status = 'PAID' LIMIT 1");
    $stmt->execute([':tok' => $token]);
    $joinUrl = (string)($stmt->fetchColumn() ?: '');

    if ($joinUrl !== '') {
        header('Location: ' . $joinUrl, true, 302);
        exit;
    }
} catch (Throwable $e) {}

http_response_code(404);
echo "404 - Meeting link not found or payment pending.";
exit;
