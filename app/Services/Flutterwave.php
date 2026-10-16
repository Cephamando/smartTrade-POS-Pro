<?php

namespace App\Services;

use App\Models\Setting;
use PDO;

/**
 * Flutterwave v3 (hosted checkout + Zambia mobile money push).
 *
 * Settings → Payments stores the public key, secret key, webhook secret hash,
 * and the RS_ subaccount id. Test keys (FLWPUBK_TEST / FLWSECK_TEST) put the
 * gateway in sandbox mode. Card checkout and mobile-money charges send only
 * that subaccount id, so the percentage or flat split saved on the subaccount
 * is the one Flutterwave applies.
 *
 * Every attempt is recorded in payment_transactions keyed by tx_ref. A sale
 * completes only after this server has verified the charge (successful,
 * amount >= due, currency matches) and it has not been used before.
 */
final class Flutterwave
{
    private const API = 'https://api.flutterwave.com/v3';

    public function __construct(private PDO $pdo, private ?array $settings = null)
    {
        $this->settings ??= Setting::all();
    }

    public function enabled(): bool
    {
        return ($this->settings['flw_enabled'] ?? '0') === '1' && $this->secretKey() !== '' && $this->publicKey() !== '';
    }

    public function publicKey(): string
    {
        return trim((string)($this->settings['flw_public_key'] ?? ''));
    }

    public function isTestMode(): bool
    {
        return str_starts_with($this->publicKey(), 'FLWPUBK_TEST') || str_starts_with($this->secretKey(), 'FLWSECK_TEST');
    }

    /**
     * Confirms the saved secret key with a small authenticated call.
     *
     * @return array{status: string, msg: string, test_mode?: bool}
     */
    public function testConnection(): array
    {
        $this->assertEnabled();
        try {
            $res = $this->request('GET', '/banks/ZM');
            if (($res['status'] ?? '') === 'success') {
                return [
                    'status' => 'ok',
                    'msg' => 'Keys accepted. ' . ($this->isTestMode() ? 'Sandbox (test) mode.' : 'LIVE mode — real money.'),
                    'test_mode' => $this->isTestMode(),
                ];
            }
            return ['status' => 'error', 'msg' => (string)($res['message'] ?? 'Flutterwave did not accept the keys.')];
        } catch (\Throwable $e) {
            $why = $e->getMessage();
            return ['status' => 'error', 'msg' => $why !== '' ? $why : 'Flutterwave did not accept the keys.'];
        }
    }

    public function currency(): string
    {
        return strtoupper((string)($this->settings['flw_currency'] ?? 'ZMW')) ?: 'ZMW';
    }

    private function secretKey(): string
    {
        return trim((string)($this->settings['flw_secret_key'] ?? ''));
    }

    public static function newTxRef(string $prefix = 'POS'): string
    {
        return strtoupper($prefix) . '-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /**
     * Hosted checkout: returns a link the customer opens (popup on POS, redirect on the shop).
     * @param array{amount: float, email?: string, phone?: string, name?: string, redirect_url: string, title?: string, description?: string, sale_id?: int, location_id?: int, user_id?: int, customer_id?: int, prefix?: string, payment_options?: string} $args
     */
    public function createPaymentLink(array $args): array
    {
        $this->assertEnabled();
        $amount = round((float)$args['amount'], 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }
        $txRef = self::newTxRef($args['prefix'] ?? 'POS');
        $phone = (string)($args['phone'] ?? '');
        $email = trim((string)($args['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $digits = preg_replace('/\D+/', '', $phone) ?: '0';
            $email = 'customer+' . $digits . '@' . $this->emailDomain();
        }
        $payload = [
            'tx_ref' => $txRef,
            'amount' => $amount,
            'currency' => $this->currency(),
            'redirect_url' => (string)$args['redirect_url'],
            'payment_options' => $args['payment_options'] ?? 'card,mobilemoneyzambia,ussd,banktransfer',
            'customer' => [
                'email' => $email,
                'phonenumber' => $phone,
                'name' => ($args['name'] ?? '') !== '' ? (string)$args['name'] : 'Walk-in customer',
            ],
            'customizations' => [
                'title' => $this->hostedText($args['title'] ?? (string)($this->settings['business_name'] ?? 'smartTrade POS')),
                'description' => $this->hostedText($args['description'] ?? ('Payment ' . $txRef)),
            ],
            'meta' => [
                'sale_id' => (int)($args['sale_id'] ?? 0),
                'location_id' => (int)($args['location_id'] ?? 0),
                'source' => $args['prefix'] ?? 'POS',
            ],
        ];
        $subaccounts = $this->subaccounts();
        if ($subaccounts !== []) {
            $payload['subaccounts'] = $subaccounts;
        }
        $res = $this->request('POST', '/payments', $payload);
        $link = $res['data']['link'] ?? null;
        if (($res['status'] ?? '') !== 'success' || !$link) {
            throw new \RuntimeException('Flutterwave: ' . ($res['message'] ?? 'could not create the payment link'));
        }
        $this->insertTransaction($txRef, $amount, [
            'payment_type' => 'hosted',
            'checkout_url' => $link,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email,
            'name' => $args['name'] ?? null,
            'sale_id' => $args['sale_id'] ?? null,
            'location_id' => $args['location_id'] ?? null,
            'user_id' => $args['user_id'] ?? null,
            'customer_id' => $args['customer_id'] ?? null,
        ], $res);
        return ['tx_ref' => $txRef, 'link' => $link];
    }

    /**
     * Record a pending charge and return the config for Flutterwave's checkout
     * script. The storefront uses this instead of the hosted sandbox page, which
     * stays on a spinner and never opens the payment window.
     *
     * @param array{amount: float, email?: string, phone?: string, name?: string, redirect_url: string, title?: string, description?: string, sale_id?: int, location_id?: int, user_id?: int, customer_id?: int, prefix?: string, payment_options?: string} $args
     * @return array<string, mixed>
     */
    public function beginInline(array $args): array
    {
        $this->assertEnabled();
        $amount = round((float)$args['amount'], 2);
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }
        $txRef = self::newTxRef($args['prefix'] ?? 'WEB');
        $phone = (string)($args['phone'] ?? '');
        $email = trim((string)($args['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $digits = preg_replace('/\D+/', '', $phone) ?: '0';
            $email = 'customer+' . $digits . '@' . $this->emailDomain();
        }
        $this->insertTransaction($txRef, $amount, [
            'payment_type' => 'inline',
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email,
            'name' => $args['name'] ?? null,
            'sale_id' => $args['sale_id'] ?? null,
            'location_id' => $args['location_id'] ?? null,
            'user_id' => $args['user_id'] ?? null,
            'customer_id' => $args['customer_id'] ?? null,
        ], ['status' => 'pending', 'mode' => 'inline']);
        $tx = $this->transaction($txRef);
        if (!$tx) {
            throw new \RuntimeException('The payment could not be started.');
        }
        return $this->inlinePayload($tx, $args);
    }

    /** @param array<string, mixed> $tx @param array<string, mixed> $args @return array<string, mixed> */
    public function inlinePayload(array $tx, array $args): array
    {
        $payload = [
            'public_key' => $this->publicKey(),
            'tx_ref' => (string)$tx['tx_ref'],
            'amount' => round((float)$tx['amount'], 2),
            'currency' => (string)(($tx['currency'] ?? '') !== '' ? $tx['currency'] : $this->currency()),
            'payment_options' => (string)($args['payment_options'] ?? 'card,mobilemoneyzambia'),
            'redirect_url' => (string)($args['redirect_url'] ?? ''),
            'customer' => [
                'email' => (string)($tx['customer_email'] ?? ''),
                'phone_number' => (string)($tx['customer_phone'] ?? ''),
                'name' => (string)(($tx['customer_name'] ?? '') !== '' ? $tx['customer_name'] : 'Customer'),
            ],
            'customizations' => [
                'title' => $this->hostedText((string)($args['title'] ?? ($this->settings['business_name'] ?? 'Payment'))),
                'description' => $this->hostedText((string)($args['description'] ?? ('Payment ' . $tx['tx_ref']))),
            ],
        ];
        $subaccounts = $this->subaccounts();
        if ($subaccounts !== []) {
            $payload['subaccounts'] = $subaccounts;
        }
        return $payload;
    }

    /**
     * The subaccount's own percentage or flat split is used.
     * Passing only the id avoids overriding the split saved in the Flutterwave dashboard.
     *
     * @return list<array{id: string}>
     */
    private function subaccounts(): array
    {
        $id = strtoupper(trim((string)($this->settings['flw_subaccount_id'] ?? '')));
        if ($id === '' || !preg_match('/^RS_[A-Z0-9]+$/', $id)) {
            return [];
        }
        return [['id' => $id]];
    }

    /**
     * Zambia mobile money push (MTN / Airtel / Zamtel). The customer approves on the handset;
     * poll status() until successful or failed. In sandbox Flutterwave usually returns a
     * redirect URL for a simulated approval page: pass it back to the UI.
     */
    public function chargeMobileMoney(array $args): array
    {
        $this->assertEnabled();
        $amount = round((float)$args['amount'], 2);
        $phone = preg_replace('/\D+/', '', (string)($args['phone'] ?? ''));
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Amount must be greater than zero.');
        }
        if (strlen($phone) < 9) {
            throw new \InvalidArgumentException('Enter the customer\'s mobile money number.');
        }
        if (strlen($phone) === 9) {
            $phone = '0' . $phone;
        } elseif (str_starts_with($phone, '260') && strlen($phone) === 12) {
            $phone = '0' . substr($phone, 3);
        }
        $network = strtoupper((string)($args['network'] ?? 'MTN'));
        if (!in_array($network, ['MTN', 'AIRTEL', 'ZAMTEL'], true)) {
            $network = 'MTN';
        }
        $txRef = self::newTxRef($args['prefix'] ?? 'POS');
        $email = trim((string)($args['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $email = 'customer+' . $phone . '@' . $this->emailDomain();
        }
        $payload = [
            'tx_ref' => $txRef,
            'amount' => $amount,
            'currency' => 'ZMW',
            'network' => $network,
            'email' => $email,
            'phone_number' => $phone,
            'fullname' => ($args['name'] ?? '') !== '' ? (string)$args['name'] : 'Walk-in customer',
            'redirect_url' => $args['redirect_url'] ?? null,
            'meta' => ['sale_id' => (int)($args['sale_id'] ?? 0), 'source' => $args['prefix'] ?? 'POS'],
        ];
        $subaccounts = $this->subaccounts();
        if ($subaccounts !== []) {
            $payload['subaccounts'] = $subaccounts;
        }
        $res = $this->request('POST', '/charges?type=mobile_money_zambia', $payload);
        if (($res['status'] ?? '') !== 'success') {
            throw new \RuntimeException('Flutterwave: ' . ($res['message'] ?? 'mobile money charge was rejected'));
        }
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $redirect = $res['meta']['authorization']['redirect'] ?? ($data['meta']['authorization']['redirect'] ?? null);
        $this->insertTransaction($txRef, $amount, [
            'payment_type' => 'mobilemoney_' . strtolower($network),
            'network' => $network,
            'phone' => $phone,
            'email' => $email,
            'name' => $args['name'] ?? null,
            'sale_id' => $args['sale_id'] ?? null,
            'location_id' => $args['location_id'] ?? null,
            'user_id' => $args['user_id'] ?? null,
            'customer_id' => $args['customer_id'] ?? null,
            'provider_tx_id' => isset($data['id']) ? (string)$data['id'] : null,
            'flw_ref' => $data['flw_ref'] ?? null,
            'checkout_url' => $redirect,
        ], $res);
        return [
            'tx_ref' => $txRef,
            'status' => strtolower((string)($data['status'] ?? 'pending')),
            'redirect' => $redirect,
            'message' => $data['processor_response'] ?? ($res['message'] ?? 'Prompt sent to the customer\'s phone.'),
        ];
    }

    /** Store the charge id Flutterwave returns on the way back, so verify can fetch it. */
    public function rememberCharge(string $txRef, string $chargeId): void
    {
        $chargeId = trim($chargeId);
        if ($txRef === '' || !str_starts_with($chargeId, 'chg_') || !$this->transaction($txRef)) {
            return;
        }
        $this->pdo->prepare(
            'UPDATE payment_transactions SET provider_tx_id = ? WHERE tx_ref = ? AND (provider_tx_id IS NULL OR provider_tx_id = "" OR provider_tx_id NOT LIKE "chg_%")'
        )->execute([$chargeId, $txRef]);
    }

    /** Verify by our reference against Flutterwave and persist the outcome. */
    public function verify(string $txRef): array
    {
        $this->assertEnabled();
        $local = $this->transaction($txRef);
        if (!$local) {
            throw new \RuntimeException('Unknown payment reference ' . $txRef);
        }
        if ($local['status'] === 'successful' && $local['verified_at']) {
            return $local;
        }
        $res = $this->request('GET', '/transactions/verify_by_reference?tx_ref=' . rawurlencode($txRef));
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $remoteStatus = strtolower((string)($data['status'] ?? ''));
        if (($res['status'] ?? '') !== 'success' || $remoteStatus === '') {
            $this->touch($txRef, 'pending', null, $res);
            return $this->transaction($txRef);
        }
        $ok = $remoteStatus === 'successful'
            && strtoupper((string)($data['currency'] ?? '')) === strtoupper((string)$local['currency'])
            && (float)($data['amount'] ?? 0) + 0.005 >= (float)$local['amount'];
        $status = $ok ? 'successful' : ($remoteStatus === 'pending' ? 'pending' : 'failed');
        $this->touch($txRef, $status, $data, $res);
        return $this->transaction($txRef);
    }

    /**
     * Payer detail for receipts and reports.
     * Network and the handset number come from the charge we sent and from Flutterwave's
     * confirmation. The network transaction id is the reference the mobile network returns
     * (the one on the customer's SMS). When that is absent, Txn ID is Flutterwave's own id.
     *
     * @return array{network:string,phone:string,network_tx_id:string,lines:list<array{label:string,value:string}>,summary:string}|null
     */
    public static function present(?array $row): ?array
    {
        if (!$row) {
            return null;
        }
        $raw = json_decode((string)($row['raw_response'] ?? ''), true);
        $data = [];
        if (is_array($raw)) {
            $data = is_array($raw['data'] ?? null) ? $raw['data'] : $raw;
        }
        $network = self::networkOf($row, $data);
        $phone = self::phoneOf($row, $data);
        $networkTx = trim((string)($row['network_tx_id'] ?? ''));
        if ($networkTx === '') {
            $networkTx = self::networkTxOf($data);
        }
        $flwId = trim((string)($row['provider_tx_id'] ?? ''));
        if ($flwId === '' && isset($data['id']) && $data['id'] !== '') {
            $flwId = (string)$data['id'];
        }
        $card = self::cardOf($data);
        $lines = [];
        if ($network !== '') {
            $lines[] = ['label' => 'Network', 'value' => $network];
        }
        if ($phone !== '' && $network !== '') {
            $lines[] = ['label' => 'Number', 'value' => $phone];
        }
        if ($card !== '' && $network === '') {
            $lines[] = ['label' => 'Card', 'value' => $card];
        }
        if ($networkTx !== '') {
            $lines[] = ['label' => 'Network txn', 'value' => $networkTx];
        } elseif ($flwId !== '') {
            $lines[] = ['label' => 'Txn ID', 'value' => $flwId];
        }
        if ($lines === []) {
            return null;
        }
        $summaryBits = [];
        foreach ($lines as $line) {
            $summaryBits[] = $line['value'];
        }
        return [
            'network' => $network,
            'phone' => $network !== '' ? $phone : '',
            'card' => $card,
            'network_tx_id' => $networkTx !== '' ? $networkTx : $flwId,
            'lines' => $lines,
            'summary' => implode(' · ', $summaryBits),
        ];
    }

    /** @param list<int> $saleIds @return array<int, array{network:string,phone:string,network_tx_id:string,lines:list<array{label:string,value:string}>,summary:string}> */
    public static function detailsForSales(PDO $pdo, array $saleIds): array
    {
        $saleIds = array_values(array_unique(array_filter(array_map('intval', $saleIds))));
        if ($saleIds === []) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($saleIds), '?'));
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM payment_transactions
                 WHERE provider = 'flutterwave' AND status = 'successful' AND sale_id IN ($placeholders)
                 ORDER BY id DESC"
            );
            $stmt->execute($saleIds);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        $map = [];
        foreach ($rows as $row) {
            $saleId = (int)($row['sale_id'] ?? 0);
            if ($saleId <= 0 || isset($map[$saleId])) {
                continue;
            }
            $detail = self::present($row);
            if ($detail !== null) {
                $detail['amount'] = (float)($row['amount'] ?? 0);
                $map[$saleId] = $detail;
            }
        }
        return $map;
    }

    /** @param list<array<string, mixed>> $rows */
    public static function attach(PDO $pdo, array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            if (!empty($row['id'])) {
                $ids[] = (int)$row['id'];
            }
        }
        $map = self::detailsForSales($pdo, $ids);
        foreach ($rows as &$row) {
            $row['gateway'] = $map[(int)($row['id'] ?? 0)] ?? null;
        }
        unset($row);
        return $rows;
    }

    public function transaction(string $txRef): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payment_transactions WHERE tx_ref = ?');
        $stmt->execute([$txRef]);
        return $stmt->fetch() ?: null;
    }

    /**
     * Called by Checkout: make sure the reference is good for this amount and not reused.
     */
    public function claimForSale(string $txRef, float $due, int $saleId): array
    {
        $tx = $this->transaction($txRef);
        if (!$tx) {
            throw new \RuntimeException('Flutterwave reference not found. Take the payment again.');
        }
        if ($tx['status'] !== 'successful') {
            $tx = $this->verify($txRef);
        }
        if ($tx['status'] !== 'successful') {
            throw new \RuntimeException('Flutterwave has not confirmed this payment yet (' . $tx['status'] . ').');
        }
        if (!empty($tx['used_at']) && (int)$tx['sale_id'] !== $saleId) {
            throw new \RuntimeException('This Flutterwave payment was already used on receipt #' . $tx['sale_id'] . '.');
        }
        if ((float)$tx['amount'] + 0.005 < $due) {
            throw new \RuntimeException('Flutterwave payment (' . number_format((float)$tx['amount'], 2) . ') is less than the amount due.');
        }
        $this->pdo->prepare('UPDATE payment_transactions SET sale_id = ?, used_at = NOW() WHERE tx_ref = ?')->execute([$saleId, $txRef]);
        return $tx;
    }

    /**
     * Flutterwave calls this when a charge finishes, including when the customer
     * closed the browser before the return page loaded.
     * A shop order is marked paid and left pending for hand-over. It is not collected
     * and it is not sent to ZRA from here.
     */
    public function handleWebhook(array $payload): void
    {
        $type = strtolower((string)($payload['type'] ?? $payload['event'] ?? ''));
        if (str_contains($type, 'transfer')) {
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
            $this->applyTransferWebhook($data);
            return;
        }
        if ($type !== '' && $type !== 'charge.completed') {
            return;
        }
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $txRef = (string)($data['reference'] ?? $data['tx_ref'] ?? $payload['txRef'] ?? '');
        if ($txRef === '' || !$this->transaction($txRef)) {
            return;
        }
        $chargeId = (string)($data['id'] ?? '');
        if (str_starts_with($chargeId, 'chg_')) {
            $this->rememberCharge($txRef, $chargeId);
        }
        $tx = $this->verify($txRef);
        if (str_starts_with($txRef, 'EVT-')) {
            \App\Models\Event::settleTx($txRef, (string)($tx['status'] ?? ''));
            return;
        }
        if (($tx['status'] ?? '') !== 'successful') {
            return;
        }
        $this->markLinkedSalePaid($txRef, $tx);
    }

    /** @param array<string, mixed> $tx */
    private function markLinkedSalePaid(string $txRef, array $tx): void
    {
        $saleId = (int)($tx['sale_id'] ?? 0);
        if ($saleId <= 0) {
            $found = $this->pdo->prepare('SELECT id FROM sales WHERE gateway_ref = ? LIMIT 1');
            $found->execute([$txRef]);
            $saleId = (int)$found->fetchColumn();
        }
        if ($saleId <= 0) {
            return;
        }
        $stmt = $this->pdo->prepare('SELECT id, final_total, payment_status FROM sales WHERE id = ?');
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch();
        if (!$sale || ($sale['payment_status'] ?? '') === 'paid') {
            return;
        }
        if ((float)$tx['amount'] + 0.005 < (float)$sale['final_total']) {
            return;
        }
        $this->claimForSale($txRef, (float)$sale['final_total'], $saleId);
        $this->pdo->prepare(
            "UPDATE sales SET payment_status = 'paid', amount_tendered = final_total, change_due = 0, gateway_ref = ? WHERE id = ? AND payment_status <> 'paid'"
        )->execute([$txRef, $saleId]);
    }

    public function webhookHashMatches(string $rawBody, string $signature): bool
    {
        $hash = trim((string)($this->settings['flw_webhook_hash'] ?? ''));
        $signature = trim($signature);
        if ($hash === '' || $signature === '') {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $rawBody, $hash, true));
        if (hash_equals($expected, $signature)) {
            return true;
        }
        return hash_equals($hash, $signature);
    }

    // ---------------------------------------------------------------- internals

    private function assertEnabled(): void
    {
        if (!$this->enabled()) {
            throw new \RuntimeException('Flutterwave is not enabled. Add the keys under Settings → Payments.');
        }
    }

    /** Cloudflare in front of Flutterwave rejects a "#" in the hosted-checkout text. */
    private function hostedText(string $value): string
    {
        $value = str_replace('#', '', $value);
        $value = trim(preg_replace('/\s+/', ' ', $value) ?? $value);
        return $value !== '' ? $value : 'Payment';
    }

    private function emailDomain(): string
    {
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'pos.local');
        $host = preg_replace('/:\d+$/', '', $host) ?: 'pos.local';
        return in_array($host, ['localhost', '127.0.0.1'], true) ? 'pos.local' : $host;
    }

    private function insertTransaction(string $txRef, float $amount, array $meta, array $raw): void
    {
        $this->pdo->prepare(
            'INSERT INTO payment_transactions
                (provider, tx_ref, provider_tx_id, flw_ref, sale_id, location_id, user_id, customer_id, amount, currency, status,
                 payment_type, network, customer_phone, customer_email, customer_name, checkout_url, raw_response)
             VALUES ("flutterwave", ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $txRef,
            $meta['provider_tx_id'] ?? null,
            $meta['flw_ref'] ?? null,
            !empty($meta['sale_id']) ? (int)$meta['sale_id'] : null,
            !empty($meta['location_id']) ? (int)$meta['location_id'] : null,
            !empty($meta['user_id']) ? (int)$meta['user_id'] : null,
            !empty($meta['customer_id']) ? (int)$meta['customer_id'] : null,
            $amount,
            $this->currency(),
            $meta['payment_type'] ?? null,
            self::namedNetwork((string)($meta['network'] ?? '')) ?: self::namedNetwork((string)($meta['payment_type'] ?? '')),
            $meta['phone'] ?? null,
            $meta['email'] ?? null,
            $meta['name'] ?? null,
            $meta['checkout_url'] ?? null,
            json_encode($raw, JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function touch(string $txRef, string $status, ?array $data, array $raw): void
    {
        $local = $this->transaction($txRef) ?: [];
        $data = $data ?? [];
        $remoteType = isset($data['payment_type']) ? (string)$data['payment_type'] : null;
        if ($remoteType !== null && strtolower($remoteType) === 'mobilemoneyzm' && self::namedNetwork((string)($local['payment_type'] ?? '')) !== null) {
            $remoteType = null;
        }
        $network = self::namedNetwork(self::networkOf($local, $data));
        $phone = self::phoneOf(['customer_phone' => null], $data);
        if ($phone === '') {
            $phone = null;
        }
        $networkTx = self::networkTxOf($data);
        $figures = self::figuresFromData($data, (float)($local['amount'] ?? 0));
        $this->pdo->prepare(
            'UPDATE payment_transactions SET status = ?, provider_tx_id = COALESCE(?, provider_tx_id), flw_ref = COALESCE(?, flw_ref),
                    charged_amount = COALESCE(?, charged_amount), app_fee = COALESCE(?, app_fee), merchant_fee = COALESCE(?, merchant_fee),
                    net_amount = COALESCE(?, net_amount),
                    settlement_status = CASE WHEN ? = "successful" THEN COALESCE(settlement_status, "pending") ELSE settlement_status END,
                    payment_type = COALESCE(?, payment_type),
                    network = COALESCE(?, network), network_tx_id = COALESCE(?, network_tx_id),
                    customer_phone = COALESCE(?, customer_phone),
                    raw_response = ?, verified_at = CASE WHEN ? = "successful" THEN NOW() ELSE verified_at END
             WHERE tx_ref = ?'
        )->execute([
            $status,
            isset($data['id']) ? (string)$data['id'] : null,
            $data['flw_ref'] ?? null,
            $figures['charged'],
            $figures['fee'],
            $figures['merchant_fee'],
            $figures['net'],
            $status,
            $remoteType,
            $network !== null ? $network : null,
            $networkTx !== '' ? $networkTx : null,
            $phone,
            json_encode($raw, JSON_UNESCAPED_SLASHES),
            $status,
            $txRef,
        ]);
    }

    /**
     * Totals for the dashboard Settlements tab, from confirmed Flutterwave charges.
     *
     * @return array{processed: float, fees: float, pending: float, settled: float, count: int, pending_count: int, settled_count: int, rows: list<array<string, mixed>>, enabled: bool}
     */
    public function settlementBoard(): array
    {
        $this->backfillSettlementFigures();
        $sum = $this->pdo->query(
            "SELECT COUNT(*) AS tx_count,
                    COALESCE(SUM(CASE WHEN charged_amount > 0 THEN charged_amount ELSE amount END), 0) AS processed,
                    COALESCE(SUM(app_fee), 0) AS fees,
                    COALESCE(SUM(CASE WHEN settlement_status = 'settled' THEN 0 ELSE COALESCE(net_amount, amount) END), 0) AS pending_net,
                    COALESCE(SUM(CASE WHEN settlement_status = 'settled' THEN COALESCE(net_amount, 0) ELSE 0 END), 0) AS settled_net,
                    COALESCE(SUM(CASE WHEN settlement_status = 'settled' THEN 1 ELSE 0 END), 0) AS settled_count
             FROM payment_transactions
             WHERE provider = 'flutterwave' AND status = 'successful'"
        )->fetch() ?: [];
        $count = (int)($sum['tx_count'] ?? 0);
        $settledCount = (int)($sum['settled_count'] ?? 0);
        $rows = $this->pdo->query(
            "SELECT id, tx_ref, sale_id, amount, charged_amount, app_fee, net_amount, currency, payment_type, network,
                    settlement_status, settlement_id, settled_at, created_at
             FROM payment_transactions
             WHERE provider = 'flutterwave' AND status = 'successful'
             ORDER BY id DESC
             LIMIT 40"
        )->fetchAll();
        return [
            'processed' => round((float)($sum['processed'] ?? 0), 2),
            'fees' => round((float)($sum['fees'] ?? 0), 2),
            'pending' => round((float)($sum['pending_net'] ?? 0), 2),
            'settled' => round((float)($sum['settled_net'] ?? 0), 2),
            'count' => $count,
            'pending_count' => max(0, $count - $settledCount),
            'settled_count' => $settledCount,
            'rows' => $rows,
            'enabled' => $this->enabled(),
        ];
    }

    /**
     * Ask Flutterwave which settlement batches have been paid out, and mark the
     * matching local charges settled. A completed batch is the payout to the bank
     * or Flutterwave wallet. Pending and flagged batches are left pending.
     *
     * @return array{status: string, msg: string, marked: int}
     */
    public function syncSettlements(): array
    {
        $this->assertEnabled();
        $from = date('Y-m-d', strtotime('-90 days'));
        $to = date('Y-m-d', strtotime('+1 day'));
        $page = 1;
        $pages = 1;
        $marked = 0;
        $completed = 0;
        do {
            $res = $this->request('GET', '/settlements?page=' . $page . '&from=' . rawurlencode($from) . '&to=' . rawurlencode($to));
            if (($res['status'] ?? '') !== 'success') {
                $msg = (string)($res['message'] ?? 'Flutterwave did not return settlements.');
                if (stripos($msg, 'no settlement') !== false) {
                    return ['status' => 'ok', 'msg' => 'Flutterwave has no settlement batches yet. Successful charges stay pending until a batch is paid out.', 'marked' => 0];
                }
                return ['status' => 'error', 'msg' => $msg, 'marked' => 0];
            }
            $batches = is_array($res['data'] ?? null) ? $res['data'] : [];
            if ($batches === [] && $page === 1) {
                return ['status' => 'ok', 'msg' => 'Flutterwave has no settlement batches in the last 90 days. Successful charges stay pending until a batch is paid to the bank.', 'marked' => 0];
            }
            foreach ($batches as $batch) {
                if (!is_array($batch) || strtolower((string)($batch['status'] ?? '')) !== 'completed') {
                    continue;
                }
                $id = trim((string)($batch['id'] ?? ''));
                if ($id === '') {
                    continue;
                }
                $completed++;
                $detail = $this->request('GET', '/settlements/' . rawurlencode($id));
                $body = is_array($detail['data'] ?? null) ? $detail['data'] : [];
                $txs = is_array($body['transactions'] ?? null) ? $body['transactions'] : [];
                $when = $body['processed_date'] ?? $batch['processed_date'] ?? null;
                $marked += $this->markSettledTransactions($id, $txs, is_string($when) ? $when : null);
            }
            $pages = max(1, (int)($res['meta']['page_info']['total_pages'] ?? 1));
            $page++;
        } while ($page <= $pages && $page <= 8);

        if ($marked > 0) {
            $msg = $marked . ' payment' . ($marked === 1 ? '' : 's') . ' marked settled from Flutterwave.';
        } elseif ($completed === 0) {
            $msg = 'Flutterwave has settlement batches, but none are completed yet. These charges stay pending.';
        } else {
            $msg = 'Checked Flutterwave. No completed payout matched these charges yet.';
        }
        return ['status' => 'ok', 'msg' => $msg, 'marked' => $marked];
    }

    /** @param list<array<string, mixed>> $txs */
    private function markSettledTransactions(string $settlementId, array $txs, ?string $when): int
    {
        $at = $when !== null && strtotime($when) !== false ? date('Y-m-d H:i:s', strtotime($when)) : date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            'UPDATE payment_transactions
             SET settlement_status = "settled", settlement_id = ?, settled_at = COALESCE(settled_at, ?),
                 net_amount = COALESCE(?, net_amount), app_fee = COALESCE(?, app_fee)
             WHERE provider = "flutterwave" AND status = "successful"
               AND (settlement_status IS NULL OR settlement_status <> "settled")
               AND (
                    (? <> "" AND tx_ref = ?)
                    OR (? <> "" AND flw_ref = ?)
                    OR (? <> "" AND provider_tx_id = ?)
               )'
        );
        $n = 0;
        foreach ($txs as $tx) {
            if (!is_array($tx)) {
                continue;
            }
            $txRef = trim((string)($tx['tx_ref'] ?? ''));
            $flwRef = trim((string)($tx['flw_ref'] ?? ''));
            $id = trim((string)($tx['id'] ?? ''));
            if ($txRef === '' && $flwRef === '' && $id === '') {
                continue;
            }
            $net = isset($tx['settlement_amount']) ? round((float)$tx['settlement_amount'], 2) : null;
            $fee = isset($tx['app_fee']) ? round((float)$tx['app_fee'], 2) : null;
            $stmt->execute([$settlementId, $at, $net, $fee, $txRef, $txRef, $flwRef, $flwRef, $id, $id]);
            $n += $stmt->rowCount();
        }
        return $n;
    }

    private function backfillSettlementFigures(): void
    {
        $rows = $this->pdo->query(
            "SELECT id, amount, raw_response FROM payment_transactions
             WHERE provider = 'flutterwave' AND status = 'successful' AND (app_fee IS NULL OR net_amount IS NULL)
             ORDER BY id DESC
             LIMIT 500"
        )->fetchAll();
        $upd = $this->pdo->prepare(
            'UPDATE payment_transactions
             SET charged_amount = COALESCE(charged_amount, ?), app_fee = COALESCE(app_fee, ?),
                 merchant_fee = COALESCE(merchant_fee, ?), net_amount = COALESCE(net_amount, ?),
                 settlement_status = COALESCE(NULLIF(settlement_status, ""), "pending")
             WHERE id = ?'
        );
        foreach ($rows as $row) {
            $figures = self::figuresFromRaw((string)($row['raw_response'] ?? ''), (float)$row['amount']);
            $upd->execute([
                $figures['charged'],
                $figures['fee'],
                $figures['merchant_fee'],
                $figures['net'],
                (int)$row['id'],
            ]);
        }
        $this->pdo->exec(
            "UPDATE payment_transactions
             SET settlement_status = 'pending'
             WHERE provider = 'flutterwave' AND status = 'successful' AND (settlement_status IS NULL OR settlement_status = '')"
        );
    }

    /** @return array{charged: ?float, fee: ?float, merchant_fee: ?float, net: ?float} */
    private static function figuresFromRaw(string $raw, float $amount): array
    {
        $json = json_decode($raw, true);
        $data = [];
        if (is_array($json)) {
            $data = is_array($json['data'] ?? null) ? $json['data'] : $json;
        }
        return self::figuresFromData($data, $amount);
    }

    /**
     * @param array<string, mixed> $data
     * @return array{charged: ?float, fee: ?float, merchant_fee: ?float, net: ?float}
     */
    private static function figuresFromData(array $data, float $amount): array
    {
        $charged = isset($data['charged_amount']) ? round((float)$data['charged_amount'], 2) : null;
        $fee = array_key_exists('app_fee', $data) ? round((float)$data['app_fee'], 2) : null;
        $merchant = array_key_exists('merchant_fee', $data) ? round((float)$data['merchant_fee'], 2) : null;
        if (isset($data['amount_settled'])) {
            $net = round((float)$data['amount_settled'], 2);
        } elseif ($fee !== null) {
            $gross = ($charged !== null && $charged > 0) ? $charged : $amount;
            $net = round(max(0, $gross - $fee - (float)$merchant), 2);
        } else {
            $net = null;
        }
        return ['charged' => $charged, 'fee' => $fee, 'merchant_fee' => $merchant, 'net' => $net];
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $data */
    private static function networkOf(array $row, array $data): string
    {
        $stored = self::namedNetwork((string)($row['network'] ?? ''));
        if ($stored !== null) {
            return $stored;
        }
        $fromType = self::namedNetwork((string)($row['payment_type'] ?? ''));
        if ($fromType !== null) {
            return $fromType;
        }
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $account = is_array($data['account'] ?? null) ? $data['account'] : [];
        $method = is_array($data['payment_method'] ?? null) ? $data['payment_method'] : [];
        $details = is_array($data['payment_method_details'] ?? null) ? $data['payment_method_details'] : [];
        $momo = is_array($method['mobile_money'] ?? null) ? $method['mobile_money'] : [];
        $momoDetails = is_array($details['mobile_money'] ?? null) ? $details['mobile_money'] : [];
        $candidates = [
            $momo['network'] ?? null,
            $momoDetails['network'] ?? null,
            $data['network'] ?? null,
            $meta['network'] ?? null,
            $meta['MOMO_NETWORK'] ?? null,
            $meta['bankname'] ?? null,
            $account['bank_name'] ?? null,
            $account['account_bank'] ?? null,
            $account['bank'] ?? null,
        ];
        foreach ($meta as $key => $value) {
            if (is_string($key) && str_contains(strtoupper($key), 'NETWORK')) {
                $candidates[] = $value;
            }
        }
        foreach ($candidates as $candidate) {
            $named = self::namedNetwork((string)$candidate);
            if ($named !== null) {
                return $named;
            }
        }
        $type = strtolower((string)($data['payment_type'] ?? $row['payment_type'] ?? ''));
        return str_contains($type, 'mobilemoney') ? 'Mobile money' : '';
    }

    private static function namedNetwork(string $value): ?string
    {
        $v = strtoupper($value);
        if ($v === '') {
            return null;
        }
        if (str_contains($v, 'AIRTEL')) {
            return 'Airtel';
        }
        if (str_contains($v, 'ZAMTEL')) {
            return 'Zamtel';
        }
        if (str_contains($v, 'MTN')) {
            return 'MTN';
        }
        return null;
    }

    /** @param array<string, mixed> $row @param array<string, mixed> $data */
    private static function phoneOf(array $row, array $data): string
    {
        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $account = is_array($data['account'] ?? null) ? $data['account'] : [];
        $customer = is_array($data['customer'] ?? null) ? $data['customer'] : [];
        $method = is_array($data['payment_method'] ?? null) ? $data['payment_method'] : [];
        $details = is_array($data['payment_method_details'] ?? null) ? $data['payment_method_details'] : [];
        $momo = is_array($method['mobile_money'] ?? null) ? $method['mobile_money'] : [];
        $momoDetails = is_array($details['mobile_money'] ?? null) ? $details['mobile_money'] : [];
        $customerPhone = is_array($customer['phone'] ?? null) ? $customer['phone'] : [];
        $candidates = [
            $momo['phone_number'] ?? null,
            $momoDetails['phone_number'] ?? null,
            isset($customerPhone['number']) ? (($customerPhone['country_code'] ?? '') . $customerPhone['number']) : null,
            $customer['phone_number'] ?? null,
            $data['phone_number'] ?? null,
            $meta['originatoraccountnumber'] ?? null,
            $meta['phone_number'] ?? null,
            $account['account_number'] ?? null,
            $row['customer_phone'] ?? null,
        ];
        foreach ($candidates as $candidate) {
            $phone = self::localPhone((string)$candidate);
            if ($phone !== '') {
                return $phone;
            }
        }
        return '';
    }

    private static function localPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '260') && strlen($digits) >= 12) {
            $digits = '0' . substr($digits, 3);
        }
        return strlen($digits) >= 9 ? $digits : '';
    }

    /** @param array<string, mixed> $data */
    private static function networkTxOf(array $data): string
    {
        $wanted = [
            'network_transaction_id', 'networktransactionid', 'financial_transaction_id', 'financialtransactionid',
            'momo_transaction_id', 'provider_transaction_id', 'external_reference', 'transaction_reference',
            'transactionreference', 'network_reference', 'provider_reference', 'third_party_reference',
        ];
        $found = self::findValue($data, $wanted, 0);
        if ($found !== '') {
            return $found;
        }
        $procRaw = $data['processor_response'] ?? '';
        $proc = trim(is_array($procRaw) ? (string)($procRaw['type'] ?? '') : (string)$procRaw);
        if (preg_match('/(?:transaction|txn|financial)\s*(?:id|reference|ref)\s*[:#\-]?\s*([A-Za-z0-9][A-Za-z0-9.\-]{4,})/i', $proc, $match)) {
            $id = $match[1];
            if (!in_array(strtolower($id), ['successful', 'approved', 'success', 'pending', 'failed'], true)) {
                return $id;
            }
        }
        return '';
    }

    /** @param array<string, mixed> $data */
    private static function cardOf(array $data): string
    {
        $method = is_array($data['payment_method'] ?? null) ? $data['payment_method'] : [];
        $details = is_array($data['payment_method_details'] ?? null) ? $data['payment_method_details'] : [];
        $card = $data['card'] ?? $method['card'] ?? $details['card'] ?? null;
        if (!is_array($card)) {
            return '';
        }
        $brand = trim((string)($card['type'] ?? $card['network'] ?? $card['issuer'] ?? ''));
        $last4 = preg_replace('/\D+/', '', (string)($card['last_4digits'] ?? $card['last4'] ?? '')) ?? '';
        if ($last4 === '') {
            return $brand;
        }
        $masked = '••••' . substr($last4, -4);
        return trim($brand . ' ' . $masked);
    }

    /** @param array<string, mixed> $node @param list<string> $wanted */
    private static function findValue(array $node, array $wanted, int $depth): string
    {
        if ($depth > 4) {
            return '';
        }
        foreach ($node as $key => $value) {
            if (is_array($value)) {
                $nested = self::findValue($value, $wanted, $depth + 1);
                if ($nested !== '') {
                    return $nested;
                }
                continue;
            }
            if (!is_string($key) || !in_array(strtolower($key), $wanted, true)) {
                continue;
            }
            $text = trim((string)$value);
            if ($text !== '' && !in_array(strtolower($text), ['successful', 'approved', 'success', 'pending', 'failed', 'n/a'], true)) {
                return $text;
            }
        }
        return '';
    }


    /**
     * Wallet balance, saved payout account, and recent emergency transfers.
     *
     * @return array{enabled: bool, test_mode: bool, balance: ?float, collection: ?float, balance_error: ?string, destination: array<string, mixed>, rows: list<array<string, mixed>>}
     */
    public function transferBoard(): array
    {
        $destination = $this->payoutDestination();
        $balance = null;
        $collection = null;
        $balanceError = null;
        $rows = [];
        if ($this->enabled()) {
            try {
                $wallets = $this->walletBalances();
                $balance = $wallets['payout'];
                $collection = $wallets['collection'];
            } catch (\Throwable $e) {
                $balanceError = $e->getMessage();
            }
        }
        try {
            if ($this->enabled()) {
                $this->refreshPendingTransfers();
            }
            $rows = $this->recentTransfers();
        } catch (\Throwable $e) {
            if ($balanceError === null) {
                $balanceError = $e->getMessage();
            }
        }
        return [
            'enabled' => $this->enabled(),
            'test_mode' => $this->isTestMode(),
            'balance' => $balance,
            'collection' => $collection,
            'balance_error' => $balanceError,
            'destination' => $destination,
            'rows' => $rows,
        ];
    }

    /** @return list<array{id: string, code: string, name: string, has_branches: bool}> */
    public function zmBanks(): array
    {
        $this->assertEnabled();
        $res = $this->request('GET', '/banks/ZM');
        if (($res['status'] ?? '') !== 'success' || !is_array($res['data'] ?? null)) {
            throw new \RuntimeException((string)($res['message'] ?? 'Flutterwave did not return Zambian banks.'));
        }
        $out = [];
        foreach ($res['data'] as $bank) {
            if (!is_array($bank)) {
                continue;
            }
            $code = trim((string)($bank['code'] ?? ''));
            $name = trim((string)($bank['name'] ?? ''));
            if ($code === '' || $name === '') {
                continue;
            }
            $out[] = [
                'id' => trim((string)($bank['id'] ?? '')),
                'code' => $code,
                'name' => $name,
                'has_branches' => !empty($bank['has_branches']) || !empty($bank['hasBranches']),
            ];
        }
        usort($out, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        return $out;
    }

    /** @return list<array{code: string, name: string}> */
    public function bankBranches(string $bankId): array
    {
        $this->assertEnabled();
        if (!preg_match('/^[A-Za-z0-9_-]+$/', $bankId)) {
            throw new \InvalidArgumentException('Choose a bank from the list.');
        }
        $res = $this->request('GET', '/banks/' . rawurlencode($bankId) . '/branches');
        if (($res['status'] ?? '') !== 'success' || !is_array($res['data'] ?? null)) {
            throw new \RuntimeException((string)($res['message'] ?? 'Flutterwave did not return branches for that bank.'));
        }
        $out = [];
        foreach ($res['data'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $code = trim((string)($row['branch_code'] ?? $row['code'] ?? ''));
            $name = trim((string)($row['branch_name'] ?? $row['name'] ?? $code));
            if ($code === '') {
                continue;
            }
            $out[] = ['code' => $code, 'name' => $name !== '' ? $name : $code];
        }
        return $out;
    }

    /**
     * Queue a ZMW bank transfer from the Flutterwave balance to the account saved
     * under Settings → Payments. The destination is not taken from this request.
     *
     * @return array{status: string, msg: string}
     */
    public function sendEmergencyTransfer(float $amount, string $narration, int $userId): array
    {
        $this->assertEnabled();
        $dest = $this->payoutDestination();
        if (empty($dest['ready'])) {
            throw new \RuntimeException('Save the emergency payout account under Settings → Payments first.');
        }
        $amount = round($amount, 2);
        if ($amount < 1) {
            throw new \InvalidArgumentException('Enter an amount of at least ZMW 1.00.');
        }
        $narration = trim(preg_replace('/\s+/', ' ', $narration) ?? '');
        if (!preg_match('/^[A-Za-z0-9 .,' . "\\-']{3,100}$/", $narration)) {
            throw new \InvalidArgumentException('The reason should be 3 to 100 letters, numbers, or simple punctuation.');
        }
        $balance = $this->availableBalance();
        if ($amount - $balance > 0.001) {
            throw new \RuntimeException('The Flutterwave balance is ZMW ' . number_format($balance, 2) . ', which is less than this transfer.');
        }
        $dupe = $this->pdo->prepare(
            "SELECT id FROM flutterwave_transfers
             WHERE account_number = ? AND amount = ? AND status IN ('pending', 'unknown')
               AND created_at > (NOW() - INTERVAL 3 MINUTE)
             LIMIT 1"
        );
        $dupe->execute([$dest['account'], $amount]);
        if ($dupe->fetchColumn()) {
            throw new \RuntimeException('A transfer for this amount is already in progress. Wait for it to finish before sending another.');
        }

        $reference = 'EMG-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));
        $this->pdo->prepare(
            'INSERT INTO flutterwave_transfers
                (reference, user_id, amount, currency, account_bank, bank_name, account_number, beneficiary_name, branch_code, narration, status)
             VALUES (?, ?, ?, "ZMW", ?, ?, ?, ?, ?, ?, "pending")'
        )->execute([
            $reference,
            $userId > 0 ? $userId : null,
            $amount,
            $dest['bank'],
            $dest['bank_name'],
            $dest['account'],
            $dest['name'],
            $dest['branch'] !== '' ? $dest['branch'] : null,
            $narration,
        ]);

        $payload = [
            'account_bank' => $dest['bank'],
            'account_number' => $dest['account'],
            'amount' => $amount,
            'narration' => $narration,
            'currency' => 'ZMW',
            'debit_currency' => 'ZMW',
            'beneficiary_name' => $dest['name'],
            'reference' => $reference,
            'callback_url' => $this->webhookUrl(),
        ];
        if ($dest['branch'] !== '') {
            $payload['destination_branch_code'] = $dest['branch'];
        }

        try {
            $res = $this->request('POST', '/transfers', $payload);
        } catch (\Throwable $e) {
            $this->storeTransferResult($reference, 'unknown', null, null, $e->getMessage(), null);
            throw new \RuntimeException('Flutterwave did not answer. The transfer was not marked failed, because it may still have been queued. Check the list before sending it again.');
        }

        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $message = (string)($res['message'] ?? 'Flutterwave did not queue the transfer.');
        if (($res['_http'] ?? 200) >= 400 || ($res['status'] ?? '') !== 'success') {
            $this->storeTransferResult($reference, 'failed', null, null, $message, $res);
            throw new \RuntimeException($message);
        }
        $fee = isset($data['fee']) ? round((float)$data['fee'], 2) : null;
        $providerId = trim((string)($data['id'] ?? ''));
        $status = $this->transferStatus($data);
        $complete = trim((string)($data['complete_message'] ?? ''));
        $this->storeTransferResult($reference, $status, $providerId !== '' ? $providerId : null, $fee, $complete !== '' ? $complete : null, $res);
        $where = $dest['bank_name'] !== '' ? $dest['bank_name'] : 'the saved account';
        return [
            'status' => 'ok',
            'msg' => 'Emergency transfer of ZMW ' . number_format($amount, 2) . ' to ' . $where . ' was queued. It stays pending until Flutterwave marks it successful.',
        ];
    }

    /** @return array{bank: string, bank_id: string, bank_name: string, account: string, name: string, branch: string, has_branches: bool, ready: bool, masked: string} */
    public function payoutDestination(): array
    {
        $bank = trim((string)($this->settings['flw_payout_bank'] ?? ''));
        $account = preg_replace('/\D+/', '', (string)($this->settings['flw_payout_account'] ?? '')) ?? '';
        $name = trim((string)($this->settings['flw_payout_name'] ?? ''));
        $branch = trim((string)($this->settings['flw_payout_branch'] ?? ''));
        $hasBranches = ($this->settings['flw_payout_has_branches'] ?? '0') === '1';
        $ready = $bank !== '' && $name !== '' && preg_match('/^[0-9]{6,20}$/', $account) === 1 && (!$hasBranches || $branch !== '');
        $masked = $account === '' ? '' : (strlen($account) <= 4 ? $account : str_repeat('•', strlen($account) - 4) . substr($account, -4));
        return [
            'bank' => $bank,
            'bank_id' => trim((string)($this->settings['flw_payout_bank_id'] ?? '')),
            'bank_name' => trim((string)($this->settings['flw_payout_bank_name'] ?? '')),
            'account' => $account,
            'name' => $name,
            'branch' => $branch,
            'has_branches' => $hasBranches,
            'ready' => $ready,
            'masked' => $masked,
        ];
    }

    public function webhookUrl(): string
    {
        $host = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
        if (in_array($host, ['localhost', '127.0.0.1', '::1', ''], true)) {
            return 'https://smartrade.odeliazm.com/api/flutterwave_webhook.php';
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $dir = rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/');
        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir . '/api/flutterwave_webhook.php';
    }

    /** @return array{payout: float, collection: float} */
    private function walletBalances(): array
    {
        $this->assertEnabled();
        $currency = $this->currency();
        $res = $this->request('GET', '/balances/' . rawurlencode($currency));
        if (($res['status'] ?? '') !== 'success') {
            throw new \RuntimeException((string)($res['message'] ?? 'Flutterwave did not return the wallet balance.'));
        }
        $data = $res['data'] ?? null;
        $row = null;
        if (is_array($data) && isset($data['available_balance'])) {
            $row = $data;
        } elseif (is_array($data)) {
            foreach ($data as $item) {
                if (is_array($item) && strtoupper((string)($item['currency'] ?? '')) === $currency) {
                    $row = $item;
                    break;
                }
            }
        }
        if ($row === null) {
            throw new \RuntimeException('Flutterwave did not return a ' . $currency . ' balance.');
        }
        return [
            'payout' => round((float)($row['available_balance'] ?? 0), 2),
            'collection' => round((float)($row['ledger_balance'] ?? 0), 2),
        ];
    }

    private function availableBalance(): float
    {
        return $this->walletBalances()['payout'];
    }

    private function refreshPendingTransfers(): void
    {
        $rows = $this->pdo->query(
            "SELECT reference, provider_id FROM flutterwave_transfers
             WHERE status IN ('pending', 'unknown') AND provider_id IS NOT NULL AND provider_id <> ''
             ORDER BY id DESC
             LIMIT 8"
        )->fetchAll();
        foreach ($rows as $row) {
            try {
                $res = $this->request('GET', '/transfers/' . rawurlencode((string)$row['provider_id']));
                $data = is_array($res['data'] ?? null) ? $res['data'] : [];
                if (($res['status'] ?? '') !== 'success' || $data === []) {
                    continue;
                }
                $fee = isset($data['fee']) ? round((float)$data['fee'], 2) : null;
                $complete = trim((string)($data['complete_message'] ?? ''));
                $this->storeTransferResult(
                    (string)$row['reference'],
                    $this->transferStatus($data),
                    (string)$row['provider_id'],
                    $fee,
                    $complete !== '' ? $complete : null,
                    $res
                );
            } catch (\Throwable $e) {
                continue;
            }
        }
    }

    /** @param array<string, mixed> $data */
    private function applyTransferWebhook(array $data): void
    {
        $reference = trim((string)($data['reference'] ?? ''));
        if ($reference === '' || !str_starts_with($reference, 'EMG-')) {
            return;
        }
        $known = $this->pdo->prepare('SELECT id FROM flutterwave_transfers WHERE reference = ? LIMIT 1');
        $known->execute([$reference]);
        if (!$known->fetchColumn()) {
            return;
        }
        $fee = isset($data['fee']) ? round((float)$data['fee'], 2) : null;
        $providerId = trim((string)($data['id'] ?? ''));
        $complete = trim((string)($data['complete_message'] ?? ''));
        $this->storeTransferResult(
            $reference,
            $this->transferStatus($data),
            $providerId !== '' ? $providerId : null,
            $fee,
            $complete !== '' ? $complete : null,
            ['data' => $data]
        );
    }

    /** @param array<string, mixed> $data */
    private function transferStatus(array $data): string
    {
        $status = strtoupper(trim((string)($data['status'] ?? '')));
        $complete = strtolower(trim((string)($data['complete_message'] ?? '')));
        if (str_contains($complete, 'unsuccess') || str_contains($complete, 'fail') || in_array($status, ['FAILED', 'FAILURE', 'REVERSED'], true)) {
            return 'failed';
        }
        if (preg_match('/\bsuccess/', $complete) === 1 || in_array($status, ['SUCCESSFUL', 'SUCCESS', 'COMPLETED'], true)) {
            return 'successful';
        }
        return 'pending';
    }

    private function storeTransferResult(string $reference, string $status, ?string $providerId, ?float $fee, ?string $message, ?array $raw): void
    {
        $this->pdo->prepare(
            'UPDATE flutterwave_transfers
             SET status = ?, provider_id = COALESCE(?, provider_id), fee = COALESCE(?, fee),
                 complete_message = COALESCE(?, complete_message), raw_response = COALESCE(?, raw_response), updated_at = NOW()
             WHERE reference = ?'
        )->execute([
            $status,
            $providerId,
            $fee,
            $message,
            $raw !== null ? json_encode($raw, JSON_UNESCAPED_SLASHES) : null,
            $reference,
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function recentTransfers(): array
    {
        $rows = $this->pdo->query(
            "SELECT t.id, t.reference, t.amount, t.fee, t.currency, t.bank_name, t.account_number, t.beneficiary_name,
                    t.narration, t.status, t.complete_message, t.created_at, u.username
             FROM flutterwave_transfers t
             LEFT JOIN users u ON u.id = t.user_id
             ORDER BY t.id DESC
             LIMIT 20"
        )->fetchAll();
        foreach ($rows as &$row) {
            $account = (string)($row['account_number'] ?? '');
            $row['account_masked'] = $account === '' ? '' : (strlen($account) <= 4 ? $account : str_repeat('•', strlen($account) - 4) . substr($account, -4));
            unset($row['account_number']);
        }
        unset($row);
        return $rows;
    }

    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init(self::API . $path);
        $headers = [
            'Authorization: Bearer ' . $this->secretKey(),
            'Content-Type: application/json',
            'Accept: application/json',
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_SLASHES));
        }
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) {
            throw new \RuntimeException('Flutterwave unreachable: ' . $err);
        }
        $json = json_decode((string)$raw, true);
        if (!is_array($json)) {
            $blocked = stripos((string)$raw, 'cloudflare') !== false;
            throw new \RuntimeException($blocked
                ? 'Flutterwave\'s firewall blocked the payment request (HTTP ' . $http . ').'
                : 'Flutterwave returned HTTP ' . $http . ' with an unreadable body.');
        }
        if ($http === 401) {
            throw new \RuntimeException('Flutterwave rejected the secret key (401). Check Settings → Payments.');
        }
        $json['_http'] = $http;
        return $json;
    }
}
