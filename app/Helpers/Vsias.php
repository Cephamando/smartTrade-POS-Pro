<?php

namespace App\Helpers;

final class Vsias
{
    public static function baseUrl(): string
    {
        return rtrim((string)(getenv('VSIAS_URL') ?: 'http://localhost:8095'), '/');
    }

    /**
     * @param array<string,mixed> $transfer
     */
    public static function transferInspectionUrl(array $transfer, string $intent): string
    {
        $intent = $intent === 'receive' ? 'receive' : 'dispatch';
        $query = [
            'intent' => $intent,
            'transfer_id' => (int)($transfer['id'] ?? 0),
            'type' => $intent === 'receive' ? 'inbound' : 'outbound',
            'source_location_id' => (int)($transfer['source_location_id'] ?? 0),
            'destination_location_id' => (int)($transfer['dest_location_id'] ?? 0),
            'source_location' => (string)($transfer['source_name'] ?? ''),
            'destination_location' => (string)($transfer['dest_name'] ?? ''),
            'product_id' => (int)($transfer['product_id'] ?? 0),
            'item_name' => (string)($transfer['product_name'] ?? ''),
            'sku' => (string)($transfer['sku'] ?? ''),
            'unspsc' => (string)($transfer['unspsc_code'] ?? ''),
            'unit' => (string)($transfer['unit'] ?? 'Units'),
            'qty' => (string)($transfer['quantity'] ?? ''),
            'tax_class' => (string)($transfer['tax_class'] ?? 'A'),
            'category' => (string)($transfer['category_name'] ?? ''),
            'category_id' => (int)($transfer['category_id'] ?? 0),
        ];

        return self::baseUrl() . '/handoffs/create?' . http_build_query($query);
    }

    public static function pendingApprovalsUrl(): string
    {
        return self::baseUrl() . '/handoffs?status=pending_review';
    }

    /**
     * @return array{count:int, dispatch:list<int>, receive:list<int>}
     */
    public static function snapshot(): array
    {
        static $memo = null;
        if (is_array($memo)) {
            return $memo;
        }
        $empty = ['count' => 0, 'dispatch' => [], 'receive' => []];
        $url = rtrim(self::internalUrl(), '/') . '/api/pending-approvals';
        $token = self::bridgeToken();
        if ($token === '') {
            $memo = $empty;
            return $memo;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 3,
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
        ]);
        $raw = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false || $code !== 200) {
            $memo = $empty;
            return $memo;
        }
        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            $memo = $empty;
            return $memo;
        }
        $dispatch = [];
        $receive = [];
        foreach ($decoded['transfers'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int)($row['transfer_id'] ?? 0);
            $intent = strtolower(trim((string)($row['intent'] ?? $row['transfer_intent'] ?? '')));
            if ($id <= 0) {
                continue;
            }
            if ($intent === 'receive') {
                $receive[] = $id;
            } elseif ($intent === 'dispatch') {
                $dispatch[] = $id;
            } else {
                $dispatch[] = $id;
                $receive[] = $id;
            }
        }
        $memo = [
            'count' => max(0, (int)($decoded['count'] ?? 0)),
            'dispatch' => array_values(array_unique($dispatch)),
            'receive' => array_values(array_unique($receive)),
        ];
        return $memo;
    }

    public static function pendingApprovalCount(): int
    {
        return (int)self::snapshot()['count'];
    }

    /**
     * @return array{dispatch:list<int>, receive:list<int>}
     */
    public static function pendingInspections(): array
    {
        $snap = self::snapshot();
        return [
            'dispatch' => $snap['dispatch'],
            'receive' => $snap['receive'],
        ];
    }

    public static function hasPendingInspection(int $transferId, string $intent): bool
    {
        if ($transferId <= 0) {
            return false;
        }
        $intent = $intent === 'receive' ? 'receive' : 'dispatch';
        return in_array($transferId, self::snapshot()[$intent] ?? [], true);
    }

    public static function internalUrl(): string
    {
        $internal = rtrim((string)(getenv('VSIAS_INTERNAL_URL') ?: ''), '/');
        if ($internal !== '') {
            return $internal;
        }
        $browser = self::baseUrl();
        $host = (string)(parse_url($browser, PHP_URL_HOST) ?: '');
        if (in_array(strtolower($host), ['localhost', '127.0.0.1'], true)) {
            $port = (int)(parse_url($browser, PHP_URL_PORT) ?: 8095);
            return 'http://host.docker.internal:' . $port;
        }
        return $browser;
    }

    private static function bridgeToken(): string
    {
        $token = trim((string)(getenv('VSIAS_BRIDGE_TOKEN') ?: ($_ENV['VSIAS_BRIDGE_TOKEN'] ?? $_SERVER['VSIAS_BRIDGE_TOKEN'] ?? '')));
        return $token !== '' ? $token : 'vsias_pro_bridge_change_me';
    }

    public static function origin(): string
    {
        $parts = parse_url(self::baseUrl());
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return 'http://localhost:8095';
        }
        $origin = $parts['scheme'] . '://' . $parts['host'];
        if (!empty($parts['port'])) {
            $origin .= ':' . $parts['port'];
        }
        return $origin;
    }
}
