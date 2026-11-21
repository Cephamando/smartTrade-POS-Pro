<?php
declare(strict_types=1);

namespace SmartTrade\Services;

use PDO;
use Exception;
use Throwable;

class TeamsRegistrationService
{
    /**
     * Ensures the teams_registrations table exists with all required columns
     */
    public static function getDb(): PDO
    {
        $db = WhatsAppCloudService::getDb();
        if (!($db instanceof PDO)) {
            // If mysqli bridge is used, establish direct PDO for teams module
            $host = WhatsAppCloudService::env('DB_HOST', '127.0.0.1');
            $port = WhatsAppCloudService::env('DB_PORT', '3306');
            $dbName = WhatsAppCloudService::env('DB_NAME', 'smarttrade_db');
            $user = WhatsAppCloudService::env('DB_USER', 'root');
            $pass = WhatsAppCloudService::env('DB_PASS', '');
            $db = new PDO("mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4", $user, $pass, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        }

        $db->exec("
            CREATE TABLE IF NOT EXISTS `teams_registrations` (
              `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `tx_ref` VARCHAR(64) NOT NULL UNIQUE,
              `receipt_token` VARCHAR(64) DEFAULT NULL UNIQUE,
              `flw_transaction_id` VARCHAR(64) DEFAULT NULL,
              `attendee_name` VARCHAR(120) NOT NULL,
              `attendee_email` VARCHAR(120) NOT NULL,
              `attendee_phone` VARCHAR(24) NOT NULL,
              `ticket_tier` VARCHAR(64) NOT NULL DEFAULT 'Standard Pass',
              `amount_zmw` DECIMAL(12,2) NOT NULL,
              `payment_status` ENUM('PENDING', 'PAID', 'FAILED') DEFAULT 'PENDING',
              `teams_meeting_id` VARCHAR(255) DEFAULT NULL,
              `teams_join_url` TEXT DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_receipt_token` (`receipt_token`),
              INDEX `idx_tx_ref` (`tx_ref`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        return $db;
    }

    /**
     * Generates a 128-bit URL-safe token
     */
    public static function generateToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    /**
     * Calls Microsoft Graph API to generate or fetch an authorized meeting join URL / bypass token
     */
    public static function fetchMicrosoftTeamsJoinUrl(string $attendeeName, string $attendeeEmail): string
    {
        $tenantId     = WhatsAppCloudService::env('MS_TEAMS_TENANT_ID', '');
        $clientId     = WhatsAppCloudService::env('MS_TEAMS_CLIENT_ID', '');
        $clientSecret = WhatsAppCloudService::env('MS_TEAMS_CLIENT_SECRET', '');
        $meetingId    = WhatsAppCloudService::env('MS_TEAMS_DEFAULT_MEETING_ID', '');

        // Fallback default meeting link if Azure Graph credentials are not yet entered in .env
        $fallbackUrl = WhatsAppCloudService::env('MS_TEAMS_DEFAULT_JOIN_URL', 'https://teams.microsoft.com/l/meetup-join/default-meeting-link');

        if ($tenantId === '' || $clientId === '' || $clientSecret === '' || $meetingId === '') {
            return $fallbackUrl;
        }

        try {
            // 1. Acquire OAuth2 Token from Azure AD
            $tokenUrl = "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token";
            $ch = curl_init($tokenUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query([
                    'client_id' => $clientId,
                    'scope' => 'https://graph.microsoft.com/.default',
                    'client_secret' => $clientSecret,
                    'grant_type' => 'client_credentials'
                ]),
                CURLOPT_TIMEOUT => 10
            ]);
            $resp = curl_exec($ch);
            curl_close($ch);
            $tokenData = json_decode((string)$resp, true);
            $accessToken = $tokenData['access_token'] ?? '';

            if ($accessToken === '') {
                return $fallbackUrl;
            }

            // 2. Register meeting registrant via Microsoft Graph API
            $regUrl = "https://graph.microsoft.com/v1.0/users/me/onlineMeetings/{$meetingId}/registrants";
            $ch = curl_init($regUrl);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Bearer ' . $accessToken,
                    'Content-Type: application/json'
                ],
                CURLOPT_POSTFIELDS => json_encode([
                    'email' => $attendeeEmail,
                    'registrantName' => $attendeeName
                ]),
                CURLOPT_TIMEOUT => 10
            ]);
            $regResp = curl_exec($ch);
            curl_close($ch);
            $regData = json_decode((string)$regResp, true);

            return (string)($regData['joinWebUrl'] ?? $fallbackUrl);
        } catch (Throwable $e) {
            return $fallbackUrl;
        }
    }
}
