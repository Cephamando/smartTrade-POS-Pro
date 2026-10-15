<?php

namespace App\Services;

use App\Models\Setting;
use PDO;

/**
 * Flutterwave v4 (live by default).
 *
 * Settings → Payments stores the v4 client ID, client secret, and webhook
 * secret hash. The client secret is exchanged for a short-lived access token.
 * Live calls go to the production host. Sandbox is a separate switch.
 *
 * Every attempt is recorded in payment_transactions keyed by our reference.
 * A sale completes only after this server has verified the charge
 * (succeeded, amount >= due, currency matches) and it has not been used before.
 */
final class Flutterwave
{
    private const TOKEN_URL = 'https://idp.flutterwave.com/realms/flutterwave/protocol/openid-connect/token';
    private const LIVE_API = 'https://f4bexperience.flutterwave.com';
    private const SANDBOX_API = 'https://developersandbox-api.flutterwave.com';
    /** Charges reject http and localhost. This https page is only the browser return when the till itself is not public. */
    private const PUBLIC_RETURN = 'https://queenbeesrestaurants.com/';

    public function __construct(private PDO $pdo, private ?array $settings = null)
    {
        $this->settings ??= Setting::all();
    }

    public function enabled(): bool
    {
        return ($this->settings['flw_enabled'] ?? '0') === '1' && $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    public function isTestMode(): bool
    {
        return ($this->settings['flw_environment'] ?? 'live') !== 'live';
    }

    /**
     * Confirms the saved client ID and secret against the selected environment.
     * Sandbox keys are rejected by the live host, so a live failure is checked
     * once against the sandbox host before the message is returned.
     *
     * @return array{status: string, msg: string, test_mode?: bool}
     */
    public function testConnection(): array
    {
        $this->assertEnabled();
        $why = 'Flutterwave did not accept the keys.';
        try {
            $res = $this->request('GET', '/customers?page=1');
            if (($res['status'] ?? '') === 'success') {
                return [
                    'status' => 'ok',
                    'msg' => 'Keys accepted. ' . ($this->isTestMode() ? 'Sandbox mode.' : 'LIVE mode — real money.'),
                    'test_mode' => $this->isTestMode(),
                ];
            }
            $why = $this->apiMessage($res, $why);
        } catch (\Throwable $e) {
            $why = $e->getMessage() !== '' ? $e->getMessage() : $why;
        }
        if (!$this->isTestMode()) {
            try {
                $sandbox = $this->request('GET', '/customers?page=1', null, null, false, self::SANDBOX_API);
                if (($sandbox['status'] ?? '') === 'success') {
                    return [
                        'status' => 'error',
                        'msg' => 'These keys are from the Flutterwave sandbox. Switch Environment to Sandbox and save. Live keeps rejecting them until the client ID and secret come from a Flutterwave business account.',
                    ];
                }
            } catch (\Throwable $e) {
                // The live failure is the one to show.
            }
        }
        return ['status' => 'error', 'msg' => $why];
    }

    public function currency(): string
    {
        return strtoupper((string)($this->settings['flw_currency'] ?? 'ZMW')) ?: 'ZMW';
    }

    private function clientId(): string
    {
        return trim((string)($this->settings['flw_client_id'] ?? ''));
    }

    private function clientSecret(): string
    {
        return trim((string)($this->settings['flw_client_secret'] ?? ''));
    }

    private function apiBase(): string
    {
        return $this->isTestMode() ? self::SANDBOX_API : self::LIVE_API;
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
        $email = $this->payerEmail((string)($args['email'] ?? ''), (string)($args['phone'] ?? ''));
        $customerId = $this->ensureCustomer($email, (string)($args['name'] ?? ''), (string)($args['phone'] ?? ''));
        $redirect = $this->payableRedirect((string)$args['redirect_url']);
        $res = $this->request('POST', '/checkout/sessions', [
            'amount' => $amount,
            'currency' => $this->currency(),
            'customer_id' => $customerId,
            'redirect_url' => $redirect ?? (string)$args['redirect_url'],
            'reference' => $txRef,
        ], $txRef);
        if ($this->redirectRejected($res)) {
            $txRef = self::newTxRef($args['prefix'] ?? 'POS');
            $res = $this->request('POST', '/checkout/sessions', [
                'amount' => $amount,
                'currency' => $this->currency(),
                'customer_id' => $customerId,
                'redirect_url' => self::PUBLIC_RETURN,
                'reference' => $txRef,
            ], $txRef);
        }
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $link = $this->checkoutLink($res);
        $sessionId = trim((string)($data['id'] ?? ''));
        if ($link === '' && str_starts_with($sessionId, 'che_') && ($res['status'] ?? '') === 'success') {
            $fetched = $this->request('GET', '/checkout/sessions/' . rawurlencode($sessionId));
            $link = $this->checkoutLink($fetched);
            if ($link !== '') {
                $data = is_array($fetched['data'] ?? null) ? $fetched['data'] : $data;
                $res = $fetched;
            }
        }
        if (($res['status'] ?? '') !== 'success') {
            throw new \RuntimeException('Flutterwave: ' . $this->apiMessage($res, 'could not open checkout'));
        }
        if ($link === '' && $this->isTestMode()) {
            $phone = preg_replace('/\D+/', '', (string)($args['phone'] ?? '')) ?? '';
            if (strlen($phone) >= 9) {
                $charged = $this->chargeMobileMoney($args + ['network' => $args['network'] ?? $this->zmNetwork($phone)]);
                return [
                    'tx_ref' => $charged['tx_ref'],
                    'link' => (string)($charged['redirect'] ?? ''),
                    'message' => (string)($charged['message'] ?? ''),
                ];
            }
        }
        if ($link === '') {
            throw new \RuntimeException('Flutterwave did not return a payment page.');
        }
        $this->insertTransaction($txRef, $amount, [
            'payment_type' => 'hosted',
            'checkout_url' => $link,
            'phone' => $args['phone'] ?? null,
            'email' => $email,
            'name' => $args['name'] ?? null,
            'sale_id' => $args['sale_id'] ?? null,
            'location_id' => $args['location_id'] ?? null,
            'user_id' => $args['user_id'] ?? null,
            'customer_id' => $args['customer_id'] ?? null,
            'flw_ref' => isset($data['id']) ? (string)$data['id'] : null,
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
        return $this->createPaymentLink($args);
    }

    /** @param array<string, mixed> $tx @param array<string, mixed> $args @return array<string, mixed> */
    public function inlinePayload(array $tx, array $args): array
    {
        $link = trim((string)($tx['checkout_url'] ?? ''));
        if ($link === '') {
            return $this->createPaymentLink($args);
        }
        return ['tx_ref' => (string)$tx['tx_ref'], 'link' => $link];
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
        $national = $phone;
        if (str_starts_with($national, '260') && strlen($national) >= 12) {
            $national = substr($national, 3);
        }
        $national = ltrim($national, '0');
        if (strlen($national) < 7 || strlen($national) > 10) {
            throw new \InvalidArgumentException('Enter a Zambian mobile money number.');
        }
        $network = strtoupper((string)($args['network'] ?? 'MTN'));
        if (!in_array($network, ['MTN', 'AIRTEL', 'ZAMTEL'], true)) {
            $network = 'MTN';
        }
        $txRef = self::newTxRef($args['prefix'] ?? 'POS');
        $email = $this->payerEmail((string)($args['email'] ?? ''), $phone);
        $customerId = $this->ensureCustomer($email, (string)($args['name'] ?? ''), $phone);
        $redirect = $this->payableRedirect((string)($args['redirect_url'] ?? ''));
        if ($redirect === null && $this->isTestMode()) {
            $redirect = self::PUBLIC_RETURN;
        }
        $method = $this->request('POST', '/payment-methods', [
            'type' => 'mobile_money',
            'customer_id' => $customerId,
            'mobile_money' => [
                'country_code' => '260',
                'network' => $network,
                'phone_number' => $national,
            ],
        ], $txRef . '-momo');
        $methodId = (string)($method['data']['id'] ?? '');
        if (($method['status'] ?? '') !== 'success' || $methodId === '') {
            throw new \RuntimeException('Flutterwave: ' . $this->apiMessage($method, 'mobile money was rejected'));
        }
        $charge = [
            'currency' => 'ZMW',
            'customer_id' => $customerId,
            'payment_method_id' => $methodId,
            'amount' => $amount,
            'reference' => $txRef,
        ];
        if ($redirect !== null) {
            $charge['redirect_url'] = $redirect;
        }
        $scenario = $this->isTestMode() ? ['X-Scenario-Key: scenario:auth_redirect'] : [];
        $res = $this->request('POST', '/charges', $charge, $txRef, false, null, $scenario);
        if (($res['status'] ?? '') !== 'success') {
            throw new \RuntimeException('Flutterwave: ' . $this->apiMessage($res, 'mobile money charge was rejected'));
        }
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $next = is_array($data['next_action'] ?? null) ? $data['next_action'] : [];
        $redirect = (string)($next['redirect_url']['url'] ?? '');
        $note = (string)($next['payment_instruction']['note'] ?? '');
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
            'flw_ref' => isset($data['flw_ref']) ? (string)$data['flw_ref'] : null,
            'checkout_url' => $redirect,
        ], $res);
        return [
            'tx_ref' => $txRef,
            'status' => $this->mapRemoteStatus((string)($data['status'] ?? 'pending'), true),
            'redirect' => $redirect !== '' ? $redirect : null,
            'message' => $note !== '' ? $note : 'Prompt sent to the customer\'s phone.',
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
        $data = $this->fetchCharge($local);
        if ($data === null) {
            $this->touch($txRef, 'pending', null, ['status' => 'pending']);
            return $this->transaction($txRef);
        }
        $remoteStatus = strtolower((string)($data['status'] ?? ''));
        $amountOk = strtoupper((string)($data['currency'] ?? '')) === strtoupper((string)$local['currency'])
            && (float)($data['amount'] ?? 0) + 0.005 >= (float)$local['amount'];
        $status = $this->mapRemoteStatus($remoteStatus, $amountOk);
        $this->touch($txRef, $status, $data, ['status' => 'success', 'data' => $data]);
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
        $this->pdo->prepare(
            'UPDATE payment_transactions SET status = ?, provider_tx_id = COALESCE(?, provider_tx_id), flw_ref = COALESCE(?, flw_ref),
                    charged_amount = COALESCE(?, charged_amount), payment_type = COALESCE(?, payment_type),
                    network = COALESCE(?, network), network_tx_id = COALESCE(?, network_tx_id),
                    customer_phone = COALESCE(?, customer_phone),
                    raw_response = ?, verified_at = CASE WHEN ? = "successful" THEN NOW() ELSE verified_at END
             WHERE tx_ref = ?'
        )->execute([
            $status,
            isset($data['id']) ? (string)$data['id'] : null,
            $data['flw_ref'] ?? null,
            isset($data['charged_amount']) ? (float)$data['charged_amount'] : null,
            $remoteType,
            $network !== null ? $network : null,
            $networkTx !== '' ? $networkTx : null,
            $phone,
            json_encode($raw, JSON_UNESCAPED_SLASHES),
            $status,
            $txRef,
        ]);
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

    /** @param array<string, mixed> $local @return array<string, mixed>|null */
    private function fetchCharge(array $local): ?array
    {
        $id = trim((string)($local['provider_tx_id'] ?? ''));
        if (str_starts_with($id, 'chg_')) {
            $res = $this->request('GET', '/charges/' . rawurlencode($id));
            $data = is_array($res['data'] ?? null) ? $res['data'] : null;
            if (($res['status'] ?? '') === 'success' && is_array($data) && isset($data['status'])) {
                return $data;
            }
        }
        $ref = rawurlencode((string)$local['tx_ref']);
        $listed = $this->request('GET', '/charges?reference=' . $ref . '&size=20');
        $match = $this->chargeMatching((string)$local['tx_ref'], $listed);
        return $match;
    }

    /** @param array<string, mixed> $res @return array<string, mixed>|null */
    private function chargeMatching(string $txRef, array $res): ?array
    {
        $data = $res['data'] ?? null;
        $rows = [];
        if (is_array($data) && isset($data['status'], $data['reference'])) {
            $rows[] = $data;
        } elseif (is_array($data)) {
            foreach ($data as $row) {
                if (is_array($row)) {
                    $rows[] = $row;
                }
            }
        }
        foreach ($rows as $row) {
            if ((string)($row['reference'] ?? '') === $txRef) {
                return $row;
            }
        }
        return null;
    }

    private function mapRemoteStatus(string $remote, bool $amountOk): string
    {
        $remote = strtolower($remote);
        if (in_array($remote, ['succeeded', 'successful', 'success'], true)) {
            return $amountOk ? 'successful' : 'failed';
        }
        if ($remote === '' || in_array($remote, ['pending', 'processing', 'requires_action'], true)) {
            return 'pending';
        }
        return 'failed';
    }

    private function payerEmail(string $email, string $phone): string
    {
        $email = trim($email);
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $email;
        }
        $digits = preg_replace('/\D+/', '', $phone) ?: '0';
        return 'customer+' . $digits . '@' . $this->emailDomain();
    }

    private function ensureCustomer(string $email, string $name, string $phone): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = $this->personName((string)($parts[0] ?? 'Customer'));
        $last = $this->personName(count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : 'Guest');
        $body = [
            'email' => $email,
            'name' => ['first' => $first, 'last' => $last],
        ];
        $national = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($national, '260') && strlen($national) >= 12) {
            $national = substr($national, 3);
        }
        $national = ltrim($national, '0');
        if (strlen($national) >= 7 && strlen($national) <= 10) {
            $body['phone'] = ['country_code' => '260', 'number' => $national];
        }
        $res = $this->request('POST', '/customers', $body, 'cus-' . substr(hash('sha256', strtolower($email)), 0, 24));
        $id = (string)($res['data']['id'] ?? '');
        if ($id !== '') {
            return $id;
        }
        $found = $this->request('GET', '/customers?email=' . rawurlencode($email) . '&size=10');
        $rows = is_array($found['data'] ?? null) ? $found['data'] : [];
        if (isset($rows['id'])) {
            $rows = [$rows];
        }
        foreach ($rows as $row) {
            if (is_array($row) && strcasecmp((string)($row['email'] ?? ''), $email) === 0 && !empty($row['id'])) {
                return (string)$row['id'];
            }
        }
        throw new \RuntimeException('Flutterwave: ' . $this->apiMessage($res, 'could not create the customer'));
    }

    private function personName(string $name): string
    {
        $name = preg_replace("/[^A-Za-z ,.'-]/", '', $name) ?? '';
        $name = trim($name);
        if (strlen($name) < 2) {
            return 'Guest';
        }
        return substr($name, 0, 50);
    }

    /** @param array<string, mixed> $res */
    private function apiMessage(array $res, string $fallback): string
    {
        $error = is_array($res['error'] ?? null) ? $res['error'] : [];
        $message = trim((string)($error['message'] ?? $res['message'] ?? ''));
        return $message !== '' ? $message : $fallback;
    }

    private function accessToken(bool $force = false): string
    {
        $exp = (int)($this->settings['flw_token_exp'] ?? 0);
        $cached = trim((string)($this->settings['flw_access_token'] ?? ''));
        if (!$force && $cached !== '' && $exp > time() + 60) {
            return $cached;
        }
        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);
        $ch = curl_init(self::TOKEN_URL);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded', 'Accept: application/json'],
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err) {
            throw new \RuntimeException('Flutterwave sign-in unreachable: ' . $err);
        }
        $json = json_decode((string)$raw, true);
        $token = is_array($json) ? trim((string)($json['access_token'] ?? '')) : '';
        if ($token === '') {
            throw new \RuntimeException('Flutterwave rejected the client ID or client secret. Check Settings → Payments.');
        }
        $ttl = max(60, (int)($json['expires_in'] ?? 600));
        $until = time() + $ttl;
        Setting::save([
            'flw_access_token' => $token,
            'flw_token_exp' => (string)$until,
        ]);
        $this->settings['flw_access_token'] = $token;
        $this->settings['flw_token_exp'] = (string)$until;
        return $token;
    }

    /** https URLs on a public host. Flutterwave charges reject http://localhost. */
    private function payableRedirect(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (!is_array($parts) || strtolower((string)($parts['scheme'] ?? '')) !== 'https') {
            return null;
        }
        $host = strtolower((string)($parts['host'] ?? ''));
        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.localhost')) {
            return null;
        }
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }
        return trim($url);
    }

    private function redirectRejected(array $res): bool
    {
        if (($res['status'] ?? '') === 'success') {
            return false;
        }
        $message = strtolower($this->apiMessage($res, ''));
        return str_contains($message, 'redirect') && str_contains($message, 'invalid');
    }

    private function checkoutLink(array $res): string
    {
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $next = is_array($data['next_action'] ?? null) ? $data['next_action'] : [];
        foreach ([
            $data['checkout_url'] ?? null,
            $data['link'] ?? null,
            $data['url'] ?? null,
            $next['redirect_url']['url'] ?? null,
        ] as $candidate) {
            if (is_string($candidate) && str_starts_with($candidate, 'https://')) {
                return $candidate;
            }
        }
        return '';
    }

    /** MTN 096/076, Zamtel 095/075, Airtel 097/077. */
    private function zmNetwork(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '260') && strlen($digits) > 9) {
            $digits = substr($digits, 3);
        }
        $digits = ltrim($digits, '0');
        return match (substr($digits, 0, 2)) {
            '96', '76' => 'MTN',
            '95', '75' => 'ZAMTEL',
            default => 'AIRTEL',
        };
    }

    /** @param list<string> $extraHeaders */
    private function request(string $method, string $path, ?array $body = null, ?string $idempotency = null, bool $retried = false, ?string $base = null, array $extraHeaders = []): array
    {
        $headers = [
            'Authorization: Bearer ' . $this->accessToken($retried),
            'Content-Type: application/json',
            'Accept: application/json',
            'X-Trace-Id: ' . bin2hex(random_bytes(8)),
        ];
        foreach ($extraHeaders as $header) {
            $headers[] = $header;
        }
        if ($body !== null) {
            $key = $idempotency !== null && strlen($idempotency) >= 12 ? $idempotency : bin2hex(random_bytes(8));
            $headers[] = 'X-Idempotency-Key: ' . substr($key, 0, 255);
        }
        $ch = curl_init(($base ?? $this->apiBase()) . $path);
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
        if ($http === 401 && !$retried) {
            return $this->request($method, $path, $body, $idempotency, true, $base, $extraHeaders);
        }
        if ($http === 401) {
            throw new \RuntimeException('Flutterwave rejected the client secret (401). Check Settings → Payments.');
        }
        if ($http >= 200 && $http < 300 && ($json['status'] ?? '') === '') {
            $json['status'] = 'success';
        }
        $json['_http'] = $http;
        return $json;
    }
}
