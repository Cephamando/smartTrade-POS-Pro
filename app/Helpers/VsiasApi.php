<?php

namespace App\Helpers;

final class VsiasApi
{
    public static function requireToken(): void
    {
        $headers = [];
        if (function_exists('getallheaders')) {
            $headers = array_change_key_case(getallheaders() ?: [], CASE_LOWER);
        }
        $auth = (string)($headers['authorization']
            ?? $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '');
        $token = self::bridgeToken();
        $expected = 'Bearer ' . $token;
        if ($token === '' || !hash_equals($expected, $auth)) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            exit;
        }
    }

    private static function bridgeToken(): string
    {
        $token = trim((string)(getenv('VSIAS_BRIDGE_TOKEN') ?: ($_ENV['VSIAS_BRIDGE_TOKEN'] ?? $_SERVER['VSIAS_BRIDGE_TOKEN'] ?? '')));
        if ($token !== '') {
            return $token;
        }
        $path = dirname(__DIR__, 2) . '/.env';
        if (!is_readable($path)) {
            return '';
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'VSIAS_BRIDGE_TOKEN=')) {
                return trim(substr($line, strlen('VSIAS_BRIDGE_TOKEN=')));
            }
        }
        return '';
    }

    public static function json(array $payload, int $code = 200): void
    {
        http_response_code($code);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }
}
