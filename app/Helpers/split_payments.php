<?php

function ensureSaleSplitPaymentsColumn(PDO $pdo): void
{
    static $ready = false;
    if ($ready) {
        return;
    }
    try {
        $hit = $pdo->query("SHOW COLUMNS FROM sales LIKE 'split_payments'")->fetch();
        if (!$hit) {
            $pdo->exec("ALTER TABLE sales ADD COLUMN split_payments TEXT NULL");
        }
    } catch (Throwable $e) {
        // Column add is best-effort; checkout still writes split_method_1/2.
    }
    $ready = true;
}

function collectPostedSplitPayments(array $post): array
{
    $methods = $post['split_method'] ?? [];
    $customs = $post['split_custom'] ?? [];
    $amounts = $post['split_amount'] ?? [];
    if (!is_array($methods)) {
        $methods = [];
    }
    if (!is_array($customs)) {
        $customs = [];
    }
    if (!is_array($amounts)) {
        $amounts = [];
    }

    $legs = [];
    $n = max(count($methods), count($amounts), count($customs));
    for ($i = 0; $i < $n; $i++) {
        $method = trim((string)($methods[$i] ?? ''));
        $custom = trim((string)($customs[$i] ?? ''));
        $amount = round((float)($amounts[$i] ?? 0), 2);
        if ($method === '__custom__' || strcasecmp($method, 'Other') === 0) {
            $method = $custom;
        }
        $method = preg_replace('/\s+/', ' ', $method) ?? '';
        if ($method === '' || $amount <= 0) {
            continue;
        }
        $legs[] = [
            'method' => mb_substr($method, 0, 50),
            'amount' => $amount,
        ];
    }
    return $legs;
}

function saleSplitLegs(array $sale): array
{
    $raw = $sale['split_payments'] ?? '';
    if (is_string($raw) && $raw !== '') {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $legs = [];
            foreach ($decoded as $row) {
                $method = trim((string)($row['method'] ?? ''));
                $amount = (float)($row['amount'] ?? 0);
                if ($method !== '' && $amount > 0) {
                    $legs[] = ['method' => $method, 'amount' => $amount];
                }
            }
            if ($legs) {
                return $legs;
            }
        }
    }

    $legs = [];
    if (!empty($sale['split_method_1']) && (float)($sale['split_amount_1'] ?? 0) > 0) {
        $legs[] = ['method' => (string)$sale['split_method_1'], 'amount' => (float)$sale['split_amount_1']];
    }
    if (!empty($sale['split_method_2']) && (float)($sale['split_amount_2'] ?? 0) > 0) {
        $legs[] = ['method' => (string)$sale['split_method_2'], 'amount' => (float)$sale['split_amount_2']];
    }
    return $legs;
}

function applySplitChangeToCash(array $legs, float $change): array
{
    if ($change <= 0) {
        return $legs;
    }
    foreach ($legs as $i => $leg) {
        if (strcasecmp((string)$leg['method'], 'Cash') !== 0) {
            continue;
        }
        $deduct = min((float)$leg['amount'], $change);
        $legs[$i]['amount'] = round((float)$leg['amount'] - $deduct, 2);
        $change = round($change - $deduct, 2);
        if ($change <= 0) {
            break;
        }
    }
    return array_values(array_filter($legs, static fn($leg) => (float)$leg['amount'] > 0.004));
}

function splitLegacyPair(array $legs): array
{
    return [
        $legs[0]['method'] ?? null,
        (float)($legs[0]['amount'] ?? 0),
        $legs[1]['method'] ?? null,
        (float)($legs[1]['amount'] ?? 0),
    ];
}
