<?php

namespace App\Services;

use App\Models\Setting;
use PDO;

/**
 * Tells an outside system when an online order is ready or has been handed over.
 * Settings → Customer ordering holds the URL. An empty URL sends nothing.
 */
final class OrderWebhook
{
    public static function notify(PDO $pdo, int $saleId, string $status): void
    {
        if ($saleId <= 0 || !in_array($status, ['ready', 'handed_over'], true)) {
            return;
        }
        try {
            $settings = Setting::all();
            $url = trim((string)($settings['order_webhook_url'] ?? ''));
            if (!self::allowedUrl($url)) {
                return;
            }
            $stmt = $pdo->prepare('SELECT id, split_group_id, payment_status, status, customer_name FROM sales WHERE id = ?');
            $stmt->execute([$saleId]);
            $sale = $stmt->fetch();
            if (!$sale) {
                return;
            }
            $orderId = trim((string)($sale['split_group_id'] ?? ''));
            if ($orderId === '') {
                $orderId = (string)$saleId;
            }
            $body = json_encode([
                'order_id' => $orderId,
                'sale_id' => $saleId,
                'status' => $status,
                'payment_status' => (string)($sale['payment_status'] ?? ''),
                'customer' => (string)($sale['customer_name'] ?? ''),
                'timestamp' => date('c'),
            ], JSON_UNESCAPED_SLASHES);
            if ($body === false) {
                return;
            }
            $headers = ['Content-Type: application/json'];
            $secret = trim((string)($settings['order_webhook_secret'] ?? ''));
            if ($secret !== '') {
                $headers[] = 'X-SmartTrade-Signature: ' . hash_hmac('sha256', $body, $secret);
            }
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => 3,
                CURLOPT_CONNECTTIMEOUT => 2,
            ]);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Throwable $e) {
            // A slow or refused callback must not stop the kitchen or the till.
        }
    }

    public static function allowedUrl(string $url): bool
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }
        $parts = parse_url($url);
        $scheme = strtolower((string)($parts['scheme'] ?? ''));
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($scheme === 'https') {
            return $host !== '';
        }
        return $scheme === 'http' && in_array($host, ['localhost', '127.0.0.1'], true);
    }
}
