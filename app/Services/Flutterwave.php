<?php

namespace App\Services;

use App\Models\Setting;
use PDO;

/**
 * Flutterwave v3 integration (hosted checkout + Zambia mobile money push).
 *
 * Keys live in settings: flw_public_key, flw_secret_key, flw_encryption_key,
 * flw_webhook_hash, flw_enabled. Test keys (FLWPUBK_TEST-…) put the gateway
 * in sandbox mode automatically.
 *
 * Every attempt is recorded in payment_transactions keyed by tx_ref. A sale
 * may only be completed against a transaction that this server has verified
 * (status = successful, amount >= due, currency matches) and not used before.
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
        $payload = [
            'tx_ref' => $txRef,
            'amount' => $amount,
            'currency' => $this->currency(),
            'redirect_url' => $args['redirect_url'],
            'payment_options' => $args['payment_options'] ?? 'card,mobilemoneyzambia,ussd,banktransfer',
            'customer' => [
                'email' => $args['email'] ?: ('customer+' . preg_replace('/\D+/', '', (string)($args['phone'] ?? '0')) . '@' . $this->emailDomain()),
                'phonenumber' => $args['phone'] ?? '',
                'name' => $args['name'] ?: 'Walk-in customer',
            ],
            'customizations' => [
                'title' => $args['title'] ?? (string)($this->settings['business_name'] ?? 'smartTrade POS'),
                'description' => $args['description'] ?? ('Payment ' . $txRef),
            ],
            'meta' => [
                'sale_id' => (int)($args['sale_id'] ?? 0),
                'location_id' => (int)($args['location_id'] ?? 0),
                'source' => $args['prefix'] ?? 'POS',
            ],
        ];
        $res = $this->request('POST', '/payments', $payload);
        $link = $res['data']['link'] ?? null;
        if (($res['status'] ?? '') !== 'success' || !$link) {
            throw new \RuntimeException('Flutterwave: ' . ($res['message'] ?? 'could not create the payment link'));
        }
        $this->insertTransaction($txRef, $amount, [
            'payment_type' => 'hosted',
            'checkout_url' => $link,
            'phone' => $args['phone'] ?? null,
            'email' => $args['email'] ?? null,
            'name' => $args['name'] ?? null,
            'sale_id' => $args['sale_id'] ?? null,
            'location_id' => $args['location_id'] ?? null,
            'user_id' => $args['user_id'] ?? null,
            'customer_id' => $args['customer_id'] ?? null,
        ], $res);
        return ['tx_ref' => $txRef, 'link' => $link];
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
            $phone = '0' . $phone; // local format 09xxxxxxxx expected by Flutterwave for ZM
        } elseif (str_starts_with($phone, '260') && strlen($phone) === 12) {
            $phone = '0' . substr($phone, 3);
        }
        $network = strtoupper((string)($args['network'] ?? 'MTN'));
        if (!in_array($network, ['MTN', 'AIRTEL', 'ZAMTEL'], true)) {
            $network = 'MTN';
        }
        $txRef = self::newTxRef($args['prefix'] ?? 'POS');
        $payload = [
            'tx_ref' => $txRef,
            'amount' => $amount,
            'currency' => 'ZMW',
            'network' => $network,
            'email' => $args['email'] ?: ('customer+' . $phone . '@' . $this->emailDomain()),
            'phone_number' => $phone,
            'fullname' => $args['name'] ?: 'Walk-in customer',
            'redirect_url' => $args['redirect_url'] ?? null,
            'meta' => ['sale_id' => (int)($args['sale_id'] ?? 0), 'source' => $args['prefix'] ?? 'POS'],
        ];
        $res = $this->request('POST', '/charges?type=mobile_money_zambia', $payload);
        if (($res['status'] ?? '') !== 'success') {
            throw new \RuntimeException('Flutterwave: ' . ($res['message'] ?? 'mobile money charge was rejected'));
        }
        $data = $res['data'] ?? [];
        $redirect = $res['meta']['authorization']['redirect'] ?? ($data['meta']['authorization']['redirect'] ?? null);
        $this->insertTransaction($txRef, $amount, [
            'payment_type' => 'mobilemoney_' . strtolower($network),
            'phone' => $phone,
            'email' => $args['email'] ?? null,
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

    /** Verify by tx_ref against Flutterwave and persist the outcome. */
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
        $data = $res['data'] ?? [];
        $remoteStatus = strtolower((string)($data['status'] ?? ''));
        if (($res['status'] ?? '') !== 'success' || $remoteStatus === '') {
            // Not found yet (customer has not paid) → keep pending.
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

    /** Webhook payload handler (public/api/flutterwave_webhook.php). */
    public function handleWebhook(array $payload): void
    {
        $txRef = (string)($payload['data']['tx_ref'] ?? $payload['txRef'] ?? '');
        if ($txRef === '' || !$this->transaction($txRef)) {
            return;
        }
        $this->verify($txRef);
        if (str_starts_with($txRef, 'EVT-')) {
            \App\Models\Event::settleTx($txRef, (string)($this->transaction($txRef)['status'] ?? ''));
        }
    }

    public function webhookHashMatches(string $header): bool
    {
        $hash = trim((string)($this->settings['flw_webhook_hash'] ?? ''));
        return $hash !== '' && hash_equals($hash, $header);
    }

    // ---------------------------------------------------------------- internals

    private function assertEnabled(): void
    {
        if (!$this->enabled()) {
            throw new \RuntimeException('Flutterwave is not enabled. Add the keys under Settings → Payments.');
        }
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
                 payment_type, customer_phone, customer_email, customer_name, checkout_url, raw_response)
             VALUES ("flutterwave", ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending", ?, ?, ?, ?, ?, ?)'
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
            $meta['phone'] ?? null,
            $meta['email'] ?? null,
            $meta['name'] ?? null,
            $meta['checkout_url'] ?? null,
            json_encode($raw, JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function touch(string $txRef, string $status, ?array $data, array $raw): void
    {
        $this->pdo->prepare(
            'UPDATE payment_transactions SET status = ?, provider_tx_id = COALESCE(?, provider_tx_id), flw_ref = COALESCE(?, flw_ref),
                    charged_amount = COALESCE(?, charged_amount), payment_type = COALESCE(?, payment_type),
                    raw_response = ?, verified_at = CASE WHEN ? = "successful" THEN NOW() ELSE verified_at END
             WHERE tx_ref = ?'
        )->execute([
            $status,
            isset($data['id']) ? (string)$data['id'] : null,
            $data['flw_ref'] ?? null,
            isset($data['charged_amount']) ? (float)$data['charged_amount'] : null,
            $data['payment_type'] ?? null,
            json_encode($raw, JSON_UNESCAPED_SLASHES),
            $status,
            $txRef,
        ]);
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
            throw new \RuntimeException('Flutterwave returned HTTP ' . $http . ' with an unreadable body.');
        }
        if ($http === 401) {
            throw new \RuntimeException('Flutterwave rejected the secret key (401). Check Settings → Payments.');
        }
        $json['_http'] = $http;
        return $json;
    }
}
