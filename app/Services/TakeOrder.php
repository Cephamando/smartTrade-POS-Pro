<?php

namespace App\Services;

use App\Models\Sale;
use App\Models\Setting;
use PDO;

/**
 * Take App is the menu link. This till sends the Flutterwave mobile-money
 * prompt, then deducts stock and files the ZRA invoice when that prompt is paid.
 */
final class TakeOrder
{
    private const API = 'https://take.app/api/v2';

    public static function verify(string $raw, string $header, string $secret): bool
    {
        $secret = trim($secret);
        $header = trim($header);
        if ($secret === '' || $header === '' || $raw === '') {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $piece) {
            $eq = strpos($piece, '=');
            if ($eq === false) {
                continue;
            }
            $parts[trim(substr($piece, 0, $eq))] = trim(substr($piece, $eq + 1));
        }
        $timestamp = $parts['t'] ?? '';
        $received = $parts['v1'] ?? '';
        if ($timestamp === '' || $received === '' || !ctype_digit($timestamp)) {
            return false;
        }
        if (abs(time() - (int)$timestamp) > 300) {
            return false;
        }
        $expected = hash_hmac('sha256', $timestamp . '.' . $raw, $secret);
        return hash_equals($expected, $received);
    }

    public static function network(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '260') && strlen($digits) >= 12) {
            $digits = '0' . substr($digits, 3);
        }
        if (strlen($digits) === 9) {
            $digits = '0' . $digits;
        }
        $prefix = substr($digits, 0, 3);
        if (in_array($prefix, ['096', '076'], true)) {
            return 'AIRTEL';
        }
        if (in_array($prefix, ['095', '075'], true)) {
            return 'ZAMTEL';
        }
        return 'MTN';
    }

    public static function money(mixed $minor): float
    {
        return round(((float)$minor) / 100, 2);
    }

    /**
     * @param array<string, string> $headers lower-case header names
     * @return array{ok:bool,status:int,message:string}
     */
    public static function accept(PDO $pdo, array $settings, string $raw, array $headers): array
    {
        $secret = trim((string)($settings['take_webhook_secret'] ?? ''));
        $signature = (string)($headers['x-take-signature'] ?? '');
        if (!self::verify($raw, $signature, $secret)) {
            return ['ok' => false, 'status' => 401, 'message' => 'Bad signature'];
        }
        $delivery = trim((string)($headers['x-take-delivery-id'] ?? ''));
        if ($delivery !== '') {
            $ins = $pdo->prepare('INSERT IGNORE INTO take_deliveries (delivery_id) VALUES (?)');
            $ins->execute([substr($delivery, 0, 80)]);
            if ($ins->rowCount() === 0) {
                return ['ok' => true, 'status' => 200, 'message' => 'Duplicate ignored'];
            }
        }
        $payload = json_decode($raw, true);
        if (!is_array($payload)) {
            return ['ok' => true, 'status' => 200, 'message' => 'Ignored'];
        }
        $type = strtolower((string)($payload['type'] ?? ($headers['x-take-event'] ?? '')));
        if (!in_array($type, ['order.created', 'order.updated'], true)) {
            return ['ok' => true, 'status' => 200, 'message' => 'Ignored'];
        }
        $order = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        try {
            self::import($pdo, $settings, $order);
        } catch (\Throwable $e) {
            self::miss($pdo, (string)($order['id'] ?? ''), $e->getMessage());
        }
        return ['ok' => true, 'status' => 200, 'message' => 'ok'];
    }

    /** @param array<string, mixed> $order */
    public static function import(PDO $pdo, array $settings, array $order): void
    {
        $takeId = substr(trim((string)($order['id'] ?? '')), 0, 80);
        if ($takeId === '') {
            throw new \RuntimeException('Take App order has no id.');
        }
        $existing = $pdo->prepare('SELECT id FROM sales WHERE take_order_id = ? LIMIT 1');
        $existing->execute([$takeId]);
        if ($existing->fetchColumn()) {
            return;
        }
        $currency = strtoupper(trim((string)($order['currency'] ?? 'ZMW')));
        if ($currency !== '' && $currency !== 'ZMW') {
            throw new \RuntimeException('Take App order ' . $takeId . ' is ' . $currency . ', not ZMW.');
        }
        $customer = is_array($order['customer'] ?? null) ? $order['customer'] : [];
        $name = trim((string)($customer['name'] ?? ''));
        if ($name === '') {
            $name = 'Take App';
        }
        $phone = trim((string)($customer['phone'] ?? ''));
        $built = self::lines($pdo, $order);
        $loc = (int)($settings['shop_location_id'] ?? 0);
        if ($loc <= 0) {
            $loc = (int)$pdo->query('SELECT id FROM locations ORDER BY id LIMIT 1')->fetchColumn();
        }
        $tableId = self::tableId($pdo, $loc, $order);
        $address = self::address($order);
        $notes = self::notes($order, $tableId === null, $built['notes']);
        $orderType = $tableId ? 'dine-in' : ($address !== '' ? 'delivery' : 'pickup');
        $itemsSum = $built['sum'];
        $orderTotal = self::money($order['total_amount'] ?? 0);
        if ($orderTotal <= 0) {
            $orderTotal = $itemsSum;
        }
        $surcharge = round($orderTotal - $itemsSum, 2);
        if ($surcharge < 0) {
            self::trimLastLine($built['lines'], abs($surcharge));
            $surcharge = 0.0;
            $itemsSum = 0.0;
            foreach ($built['lines'] as $line) {
                $itemsSum += round($line['price'] * $line['qty'], 2);
            }
            $itemsSum = round($itemsSum, 2);
        }
        $final = round($itemsSum + $surcharge, 2);
        if ($final <= 0) {
            throw new \RuntimeException('Take App order ' . $takeId . ' has nothing to charge.');
        }
        $shift = $pdo->prepare("SELECT id FROM shifts WHERE location_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
        $shift->execute([$loc]);
        $shiftId = $shift->fetchColumn() ?: null;
        $userId = (int)($pdo->query("SELECT id FROM users WHERE role IN ('admin','dev') ORDER BY id LIMIT 1")->fetchColumn() ?: 1);
        $alreadyPaid = strtolower((string)($order['payment_status'] ?? '')) === 'paid';
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO sales (location_id, table_id, user_id, shift_id, subtotal, total_amount, final_total, surcharge_amount, surcharge_reason, status, payment_status, payment_method,
                                    split_group_id, customer_name, customer_phone, order_source, order_type, order_notes, take_order_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', 'Online', ?, ?, ?, 'take', ?, ?, ?, NOW())"
            )->execute([
                $loc,
                $tableId,
                $userId,
                $shiftId,
                $itemsSum,
                $itemsSum,
                $final,
                $surcharge > 0 ? $surcharge : 0,
                $surcharge > 0 ? 'Delivery' : null,
                $takeId,
                mb_substr('TAKE: ' . $name, 0, 100),
                $phone !== '' ? $phone : null,
                $orderType,
                $notes !== '' ? mb_substr($notes, 0, 500) : null,
                $takeId,
            ]);
            $saleId = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare(
                "INSERT INTO sale_items (sale_id, product_id, quantity, price, price_at_sale, status, fulfillment_status) VALUES (?, ?, ?, ?, ?, 'held', 'uncollected')"
            );
            foreach ($built['lines'] as $line) {
                $ins->execute([$saleId, $line['id'], $line['qty'], $line['price'], $line['price']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        if ($alreadyPaid) {
            self::markPaidWithoutPrompt($pdo, $saleId, $takeId);
            return;
        }
        if ($phone === '') {
            self::miss($pdo, $takeId, 'Order #' . $saleId . ' has no phone number, so the mobile-money prompt was not sent.');
            return;
        }
        self::sendPrompt($pdo, $settings, $saleId);
    }

    public static function resend(PDO $pdo, int $saleId): string
    {
        $stmt = $pdo->prepare("SELECT id, payment_status, status, order_source, customer_phone, gateway_ref FROM sales WHERE id = ?");
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sale || ($sale['order_source'] ?? '') !== 'take') {
            throw new \RuntimeException('That is not a Take App order.');
        }
        if (($sale['status'] ?? '') !== 'pending') {
            throw new \RuntimeException('This order can no longer be prompted.');
        }
        if (($sale['payment_status'] ?? '') === 'paid') {
            return 'This order is already paid.';
        }
        $ref = trim((string)($sale['gateway_ref'] ?? ''));
        if ($ref !== '') {
            $tx = $pdo->prepare('SELECT status FROM payment_transactions WHERE tx_ref = ?');
            $tx->execute([$ref]);
            $status = (string)($tx->fetchColumn() ?: '');
            if ($status === 'pending') {
                return 'The mobile-money prompt is still waiting on the customer\'s phone.';
            }
        }
        self::sendPrompt($pdo, Setting::all(), $saleId);
        return 'Mobile-money prompt sent for order #' . $saleId . '.';
    }

    /** @param array<string, mixed> $settings */
    public static function sendPrompt(PDO $pdo, array $settings, int $saleId): void
    {
        $stmt = $pdo->prepare('SELECT id, final_total, customer_name, customer_phone, location_id, user_id, payment_status FROM sales WHERE id = ? AND order_source = \'take\'');
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sale || ($sale['payment_status'] ?? '') === 'paid') {
            return;
        }
        $phone = trim((string)($sale['customer_phone'] ?? ''));
        if ($phone === '') {
            throw new \RuntimeException('This order has no phone number.');
        }
        $flw = new Flutterwave($pdo, $settings);
        $made = $flw->chargeMobileMoney([
            'amount' => (float)$sale['final_total'],
            'phone' => $phone,
            'network' => self::network($phone),
            'name' => (string)$sale['customer_name'],
            'sale_id' => $saleId,
            'location_id' => (int)$sale['location_id'],
            'user_id' => (int)$sale['user_id'],
            'prefix' => 'TAKE',
        ]);
        $pdo->prepare('UPDATE sales SET gateway_ref = ? WHERE id = ?')->execute([(string)$made['tx_ref'], $saleId]);
        if (in_array((string)($made['status'] ?? ''), ['failed', 'cancelled', 'canceled'], true)) {
            $pdo->prepare("UPDATE payment_transactions SET status = 'failed' WHERE tx_ref = ? AND status = 'pending'")
                ->execute([(string)$made['tx_ref']]);
        }
    }

    public static function afterPaid(PDO $pdo, int $saleId): void
    {
        $stmt = $pdo->prepare("SELECT order_source, zra_rcpt_sign FROM sales WHERE id = ?");
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sale || ($sale['order_source'] ?? '') !== 'take' || !empty($sale['zra_rcpt_sign'])) {
            return;
        }
        fiscalizePaidSale($pdo, $saleId);
    }

    /** @return array{sale_id:int} */
    public static function printQueue(PDO $pdo): array
    {
        try {
            self::settleOpen($pdo);
        } catch (\Throwable $e) {
        }
        try {
            $row = $pdo->query(
                "SELECT id, zra_rcpt_sign FROM sales
                 WHERE order_source = 'take' AND payment_status = 'paid' AND status = 'pending' AND receipt_printed_at IS NULL
                 ORDER BY id ASC LIMIT 1"
            )->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return ['sale_id' => 0];
        }
        if (!$row) {
            return ['sale_id' => 0];
        }
        $saleId = (int)$row['id'];
        if (empty($row['zra_rcpt_sign'])) {
            fiscalizePaidSale($pdo, $saleId);
            try {
                $again = $pdo->prepare('SELECT zra_rcpt_sign, zra_status FROM sales WHERE id = ?');
                $again->execute([$saleId]);
                $fresh = $again->fetch(PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                return ['sale_id' => 0];
            }
            if (empty($fresh['zra_rcpt_sign']) && ($fresh['zra_status'] ?? '') !== 'skipped') {
                return ['sale_id' => 0];
            }
        }
        return ['sale_id' => $saleId];
    }

    public static function markPrinted(PDO $pdo, int $saleId): void
    {
        if ($saleId <= 0) {
            return;
        }
        $pdo->prepare("UPDATE sales SET receipt_printed_at = NOW() WHERE id = ? AND order_source = 'take' AND receipt_printed_at IS NULL")
            ->execute([$saleId]);
    }

    /** @return list<array{take_order_id:?string,note:string,created_at:string}> */
    public static function recentMisses(PDO $pdo): array
    {
        try {
            return $pdo->query('SELECT take_order_id, note, created_at FROM take_misses ORDER BY id DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** @param array<string, mixed> $settings @return array{created:int,updated:int,listed:bool} */
    public static function copyMenu(PDO $pdo, array $settings): array
    {
        $key = trim((string)($settings['take_api_key'] ?? ''));
        if ($key === '') {
            throw new \RuntimeException('Add the Take App API key first.');
        }
        $categoryId = self::menuCategory($key);
        $remote = [];
        $cursor = '';
        for ($page = 0; $page < 20; $page++) {
            $query = 'limit=100' . ($cursor !== '' ? '&cursor=' . rawurlencode($cursor) : '');
            $list = self::api($key, 'GET', '/products?' . $query);
            foreach ((array)($list['data'] ?? []) as $product) {
                if (!is_array($product)) {
                    continue;
                }
                $sku = trim((string)($product['sku'] ?? ''));
                $ref = trim((string)($product['reference_id'] ?? ''));
                if ($sku !== '') {
                    $remote[$sku] = (string)$product['id'];
                }
                if ($ref !== '') {
                    $remote[$ref] = (string)$product['id'];
                }
            }
            if (empty($list['has_more']) || empty($list['next_cursor'])) {
                break;
            }
            $cursor = (string)$list['next_cursor'];
        }
        \App\Models\Product::ensureSchema();
        $products = $pdo->query(
            'SELECT id, name, price FROM products WHERE is_active = 1 AND is_sellable = 1 AND price >= 0 ORDER BY name ASC'
        )->fetchAll(PDO::FETCH_ASSOC);
        $created = 0;
        $updated = 0;
        foreach ($products as $product) {
            $id = (int)$product['id'];
            $body = [
                'name' => (string)$product['name'],
                'sku' => (string)$id,
                'reference_id' => 'pos-' . $id,
                'price' => (int)round(((float)$product['price']) * 100),
                'status' => 'VISIBLE',
                'type' => 'PHYSICAL',
            ];
            if ($categoryId !== '') {
                $body['category_ids'] = [$categoryId];
            }
            $match = $remote[(string)$id] ?? ($remote['pos-' . $id] ?? '');
            if ($match !== '') {
                unset($body['category_ids']);
                self::api($key, 'PATCH', '/products/' . rawurlencode($match), $body);
                $updated++;
                continue;
            }
            $made = self::api($key, 'POST', '/products', $body);
            $created++;
            if (!empty($made['id'])) {
                $remote[(string)$id] = (string)$made['id'];
            }
        }
        return ['created' => $created, 'updated' => $updated, 'listed' => $categoryId !== ''];
    }

    private static function settleOpen(PDO $pdo): void
    {
        $rows = $pdo->query(
            "SELECT id, gateway_ref FROM sales
             WHERE order_source = 'take' AND status = 'pending' AND payment_status <> 'paid'
               AND gateway_ref LIKE 'TAKE-%'
             ORDER BY id ASC LIMIT 3"
        )->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return;
        }
        $flw = new Flutterwave($pdo, Setting::all());
        if (!$flw->enabled()) {
            return;
        }
        foreach ($rows as $row) {
            try {
                $tx = $flw->verify((string)$row['gateway_ref']);
            } catch (\Throwable $e) {
                continue;
            }
            if (($tx['status'] ?? '') !== 'successful') {
                continue;
            }
            $flw->claimForSale((string)$row['gateway_ref'], (float)($tx['amount'] ?? 0), (int)$row['id']);
            if (Sale::markOnlinePaid($pdo, (int)$row['id'], (string)$row['gateway_ref'])) {
                self::afterPaid($pdo, (int)$row['id']);
            } else {
                Sale::ensureOnlineReleased($pdo, (int)$row['id']);
                self::afterPaid($pdo, (int)$row['id']);
            }
        }
    }

    private static function markPaidWithoutPrompt(PDO $pdo, int $saleId, string $takeId): void
    {
        $pdo->prepare(
            "UPDATE sales SET payment_status = 'paid', amount_tendered = final_total, change_due = 0, gateway_ref = ?
             WHERE id = ? AND payment_status <> 'paid'"
        )->execute(['TAKE-PAID-' . substr($takeId, 0, 40), $saleId]);
        Sale::ensureOnlineReleased($pdo, $saleId);
        self::afterPaid($pdo, $saleId);
    }

    /**
     * @param array<string, mixed> $order
     * @return array{lines:list<array{id:int,qty:int,price:float}>,sum:float,notes:string}
     */
    private static function lines(PDO $pdo, array $order): array
    {
        $items = is_array($order['line_items'] ?? null) ? $order['line_items'] : [];
        if ($items === []) {
            throw new \RuntimeException('Take App order has no lines.');
        }
        $lines = [];
        $notes = [];
        $missing = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            $sku = trim((string)($item['sku'] ?? ''));
            $product = self::productForSku($pdo, $sku);
            if (!$product) {
                $missing[] = trim((string)($item['name'] ?? $sku) ?: 'item');
                continue;
            }
            $qtyFloat = (float)($item['quantity_float'] ?? 0);
            if ($qtyFloat <= 0) {
                $qtyFloat = (float)($item['quantity'] ?? 0);
            }
            $qty = max(1, (int)round($qtyFloat > 0 ? $qtyFloat : 1));
            $lineTotal = self::money($item['total_amount'] ?? 0);
            $unit = $lineTotal > 0 ? round($lineTotal / $qty, 2) : self::money($item['price'] ?? 0);
            $lines[] = ['id' => (int)$product['id'], 'qty' => $qty, 'price' => $unit];
            foreach ((array)($item['options'] ?? []) as $option) {
                if (!is_array($option)) {
                    continue;
                }
                $answers = array_filter(array_map('strval', (array)($option['answers'] ?? [])));
                if ($answers === []) {
                    continue;
                }
                $label = trim((string)($option['question_text'] ?? ''));
                $notes[] = ($label !== '' ? $label . ': ' : '') . implode(', ', $answers);
            }
        }
        if ($missing !== []) {
            throw new \RuntimeException('No POS product for SKU on: ' . implode(', ', $missing) . '. Copy the menu so each Take App SKU is the POS product id.');
        }
        $sum = 0.0;
        foreach ($lines as $line) {
            $sum += round($line['price'] * $line['qty'], 2);
        }
        return ['lines' => $lines, 'sum' => round($sum, 2), 'notes' => implode('. ', $notes)];
    }

    /** @param list<array{id:int,qty:int,price:float}> $lines */
    private static function trimLastLine(array &$lines, float $amount): void
    {
        if ($lines === [] || $amount <= 0) {
            return;
        }
        $last = count($lines) - 1;
        $qty = $lines[$last]['qty'] > 0 ? $lines[$last]['qty'] : 1;
        $lines[$last]['price'] = round(max(0, $lines[$last]['price'] - ($amount / $qty)), 2);
    }

    /** @return array{id:int,name:string}|null */
    private static function productForSku(PDO $pdo, string $sku): ?array
    {
        if ($sku === '') {
            return null;
        }
        if (ctype_digit($sku)) {
            $stmt = $pdo->prepare('SELECT id, name FROM products WHERE id = ? AND is_active = 1');
            $stmt->execute([(int)$sku]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }
        $stmt = $pdo->prepare('SELECT id, name FROM products WHERE sku = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @param array<string, mixed> $order */
    private static function tableId(PDO $pdo, int $locationId, array $order): ?int
    {
        $table = is_array($order['table'] ?? null) ? $order['table'] : [];
        $name = trim((string)($table['name'] ?? ''));
        if ($name === '' || $locationId <= 0) {
            return null;
        }
        $stmt = $pdo->prepare('SELECT id FROM restaurant_tables WHERE location_id = ? AND table_name = ? LIMIT 1');
        $stmt->execute([$locationId, $name]);
        $id = (int)$stmt->fetchColumn();
        return $id > 0 ? $id : null;
    }

    /** @param array<string, mixed> $order */
    private static function address(array $order): string
    {
        foreach (['shipping_address', 'delivery_address', 'address'] as $key) {
            $value = $order[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
            if (!is_array($value)) {
                continue;
            }
            $parts = [];
            foreach (['line1', 'address', 'line2', 'city', 'area'] as $part) {
                $bit = trim((string)($value[$part] ?? ''));
                if ($bit !== '') {
                    $parts[] = $bit;
                }
            }
            $lat = $value['latitude'] ?? $value['lat'] ?? null;
            $lng = $value['longitude'] ?? $value['lng'] ?? null;
            if ($lat !== null && $lng !== null && $lat !== '' && $lng !== '') {
                $parts[] = 'pin ' . $lat . ',' . $lng;
            }
            if ($parts !== []) {
                return implode(', ', $parts);
            }
        }
        return '';
    }

    /** @param array<string, mixed> $order */
    private static function notes(array $order, bool $unmatchedTable, string $optionNotes): string
    {
        $bits = [];
        if ($unmatchedTable) {
            $table = is_array($order['table'] ?? null) ? $order['table'] : [];
            $name = trim((string)($table['name'] ?? ''));
            if ($name !== '') {
                $bits[] = 'Table ' . $name;
            }
        }
        $address = self::address($order);
        if ($address !== '') {
            $bits[] = 'Deliver to: ' . $address;
        }
        foreach (['note', 'notes', 'remarks', 'comment'] as $key) {
            $text = trim((string)($order[$key] ?? ''));
            if ($text !== '') {
                $bits[] = $text;
            }
        }
        if ($optionNotes !== '') {
            $bits[] = $optionNotes;
        }
        return implode('. ', $bits);
    }

    private static function miss(PDO $pdo, string $takeId, string $note): void
    {
        try {
            $pdo->prepare('INSERT INTO take_misses (take_order_id, note) VALUES (?, ?)')
                ->execute([$takeId !== '' ? substr($takeId, 0, 80) : null, mb_substr($note, 0, 500)]);
        } catch (\Throwable $e) {
        }
    }

    private static function menuCategory(string $key): string
    {
        try {
            $list = self::api($key, 'GET', '/categories?limit=100');
        } catch (\Throwable $e) {
            return '';
        }
        foreach ((array)($list['data'] ?? []) as $category) {
            if (is_array($category) && strcasecmp((string)($category['name'] ?? ''), 'Menu') === 0) {
                return (string)($category['id'] ?? '');
            }
        }
        try {
            $made = self::api($key, 'POST', '/categories', ['name' => 'Menu']);
        } catch (\Throwable $e) {
            return '';
        }
        return (string)($made['id'] ?? '');
    }

    /** @param array<string, mixed>|null $body @return array<string, mixed> */
    private static function api(string $key, string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init(self::API . $path);
        $headers = [
            'Authorization: Bearer ' . $key,
            'Accept: application/json',
        ];
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
        }
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err !== '') {
            throw new \RuntimeException('Take App unreachable: ' . $err);
        }
        $json = json_decode((string)$raw, true);
        if ($http >= 400 || !is_array($json)) {
            $message = is_array($json) ? (string)($json['error']['message'] ?? '') : '';
            throw new \RuntimeException('Take App rejected the menu copy' . ($message !== '' ? ': ' . $message : '.'));
        }
        return $json;
    }
}
