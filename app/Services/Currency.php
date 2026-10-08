<?php

namespace App\Services;

use App\Models\Setting;
use PDO;

/**
 * Live exchange rates with ZMW as the base currency.
 *
 * Source: open.er-api.com (free, no key, ZMW supported, refreshed daily
 * upstream). Rates are cached in fx_rates and refreshed at most once per
 * `fx_refresh_minutes`. Manual overrides in settings (fx_manual_rates JSON)
 * always win, so a shop can pin a house rate for foreign cash.
 */
final class Currency
{
    public const BASE = 'ZMW';
    public const DEFAULT_DISPLAY = ['USD', 'ZAR', 'EUR', 'GBP'];
    public const KNOWN = [
        'USD' => 'US Dollar', 'ZAR' => 'South African Rand', 'EUR' => 'Euro', 'GBP' => 'British Pound',
        'CNY' => 'Chinese Yuan', 'BWP' => 'Botswana Pula', 'MWK' => 'Malawian Kwacha', 'TZS' => 'Tanzanian Shilling',
        'KES' => 'Kenyan Shilling', 'ZWL' => 'Zimbabwe Dollar', 'AED' => 'UAE Dirham', 'INR' => 'Indian Rupee',
        'NAD' => 'Namibian Dollar', 'MZN' => 'Mozambican Metical', 'AOA' => 'Angolan Kwanza', 'CDF' => 'Congolese Franc',
    ];

    public function __construct(private PDO $pdo, private ?array $settings = null)
    {
        $this->settings ??= Setting::all();
    }

    public function enabled(): bool
    {
        return ($this->settings['fx_enabled'] ?? '1') === '1';
    }

    /** @return string[] ISO codes shown on the POS */
    public function displayCurrencies(): array
    {
        $raw = (string)($this->settings['fx_display'] ?? implode(',', self::DEFAULT_DISPLAY));
        $codes = array_values(array_filter(array_map(fn($c) => strtoupper(trim($c)), explode(',', $raw)), fn($c) => preg_match('/^[A-Z]{3}$/', $c) && $c !== self::BASE));
        return $codes ?: self::DEFAULT_DISPLAY;
    }

    public function manualRates(): array
    {
        $json = (string)($this->settings['fx_manual_rates'] ?? '');
        $arr = json_decode($json, true);
        $out = [];
        if (is_array($arr)) {
            foreach ($arr as $code => $rate) {
                $code = strtoupper((string)$code);
                if (preg_match('/^[A-Z]{3}$/', $code) && (float)$rate > 0) {
                    $out[$code] = (float)$rate; // ZMW per 1 unit of foreign currency
                }
            }
        }
        return $out;
    }

    /**
     * @return array{rates: array<string, array{rate: float, zmw_per_unit: float, source: string, fetched_at: string}>, updated_at: ?string, stale: bool, error: ?string}
     * rate         = foreign units per 1 ZMW
     * zmw_per_unit = ZMW per 1 foreign unit (what a cashier wants to see)
     */
    public function rates(bool $forceRefresh = false): array
    {
        $codes = $this->displayCurrencies();
        $manual = $this->manualRates();
        foreach (array_keys($manual) as $c) {
            if (!in_array($c, $codes, true)) {
                $codes[] = $c;
            }
        }
        $cached = $this->cached();
        $minutes = max(5, (int)($this->settings['fx_refresh_minutes'] ?? 60));
        $newest = null;
        foreach ($cached as $row) {
            if ($row['source'] !== 'manual' && ($newest === null || $row['fetched_at'] > $newest)) {
                $newest = $row['fetched_at'];
            }
        }
        $error = null;
        $needsFetch = $forceRefresh || $newest === null || strtotime($newest) < time() - $minutes * 60;
        if ($needsFetch) {
            try {
                $this->fetchAndStore();
                $cached = $this->cached();
                $newest = date('Y-m-d H:i:s');
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        }
        $out = [];
        foreach ($codes as $code) {
            if (isset($manual[$code])) {
                $out[$code] = [
                    'rate' => $manual[$code] > 0 ? round(1 / $manual[$code], 8) : 0,
                    'zmw_per_unit' => round($manual[$code], 4),
                    'source' => 'manual',
                    'fetched_at' => '',
                    'name' => self::KNOWN[$code] ?? $code,
                ];
                continue;
            }
            if (isset($cached[$code])) {
                $r = (float)$cached[$code]['rate'];
                $out[$code] = [
                    'rate' => $r,
                    'zmw_per_unit' => $r > 0 ? round(1 / $r, 4) : 0,
                    'source' => $cached[$code]['source'],
                    'fetched_at' => $cached[$code]['fetched_at'],
                    'name' => self::KNOWN[$code] ?? $code,
                ];
            }
        }
        return [
            'base' => self::BASE,
            'rates' => $out,
            'updated_at' => $newest,
            'stale' => $newest === null || strtotime($newest) < time() - 24 * 3600,
            'error' => $error,
        ];
    }

    /** Convert an amount between any two supported codes (via ZMW). */
    public function convert(float $amount, string $from, string $to): ?float
    {
        $from = strtoupper($from);
        $to = strtoupper($to);
        if ($from === $to) {
            return round($amount, 2);
        }
        $rates = $this->rates()['rates'];
        $toZmw = $from === self::BASE ? 1.0 : ($rates[$from]['zmw_per_unit'] ?? null);
        $fromZmw = $to === self::BASE ? 1.0 : (isset($rates[$to]['zmw_per_unit']) && $rates[$to]['zmw_per_unit'] > 0 ? 1 / $rates[$to]['zmw_per_unit'] : null);
        if ($toZmw === null || $fromZmw === null) {
            return null;
        }
        return round($amount * $toZmw * $fromZmw, 2);
    }

    private function cached(): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT quote_code, rate, source, fetched_at FROM fx_rates WHERE base_code = ?');
            $stmt->execute([self::BASE]);
            $out = [];
            foreach ($stmt->fetchAll() as $row) {
                $out[$row['quote_code']] = $row;
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function fetchAndStore(): void
    {
        $url = 'https://open.er-api.com/v6/latest/' . self::BASE;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        $err = curl_error($ch);
        $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($err) {
            throw new \RuntimeException('Rate service unreachable: ' . $err);
        }
        $json = json_decode((string)$body, true);
        if ($http !== 200 || !is_array($json) || ($json['result'] ?? '') !== 'success' || empty($json['rates'])) {
            throw new \RuntimeException('Rate service returned HTTP ' . $http);
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO fx_rates (base_code, quote_code, rate, source, fetched_at) VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE rate = VALUES(rate), source = VALUES(source), fetched_at = NOW()'
        );
        $wanted = array_unique(array_merge($this->displayCurrencies(), array_keys(self::KNOWN)));
        foreach ($json['rates'] as $code => $rate) {
            $code = strtoupper((string)$code);
            if (!in_array($code, $wanted, true) || (float)$rate <= 0) {
                continue;
            }
            $stmt->execute([self::BASE, $code, (float)$rate, 'open.er-api.com']);
        }
    }
}
