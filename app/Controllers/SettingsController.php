<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Setting;
use App\Models\SurchargeReason;
use App\Services\Currency;
use App\Services\Flutterwave;

final class SettingsController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tab = (string)($_POST['tab'] ?? 'general');
            try {
                if (isset($_POST['save_surcharge'])) {
                    $this->saveSurcharge();
                } elseif (isset($_POST['toggle_surcharge'])) {
                    SurchargeReason::toggle((int)$_POST['toggle_surcharge']);
                    $this->flash('success', 'Surcharge updated.');
                } elseif (isset($_POST['delete_surcharge'])) {
                    SurchargeReason::delete((int)$_POST['delete_surcharge']);
                    $this->flash('success', 'Surcharge removed.');
                } elseif (isset($_POST['save_payments'])) {
                    $this->savePayments();
                } elseif (isset($_POST['save_currency'])) {
                    $this->saveCurrency();
                } elseif (isset($_POST['refresh_rates'])) {
                    $res = (new Currency($this->pdo()))->rates(true);
                    $this->flash($res['error'] ? 'error' : 'success', $res['error'] ? 'Rates not refreshed: ' . $res['error'] : 'Exchange rates refreshed.');
                } elseif (isset($_POST['save_shop'])) {
                    $this->saveShop();
                } else {
                    $this->saveGeneral();
                }
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage() ?: 'Unable to save settings.');
            }
            $this->redirect('settings', $tab !== 'general' ? ['tab' => $tab] : []);
        }

        $settings = Setting::all();
        $fx = new Currency($this->pdo(), $settings);
        $fxData = ['rates' => [], 'updated_at' => null, 'error' => null];
        try {
            $fxData = $fx->rates();
        } catch (\Throwable $e) {
            $fxData['error'] = $e->getMessage();
        }
        $this->render([
            'settings' => $settings,
            'tab' => (string)($_GET['tab'] ?? 'general'),
            'surchargeReasons' => SurchargeReason::all(),
            'fxData' => $fxData,
            'fxDisplay' => $fx->displayCurrencies(),
            'fxManual' => $fx->manualRates(),
            'fxKnown' => Currency::KNOWN,
            'flwEnabled' => (new Flutterwave($this->pdo(), $settings))->enabled(),
            'flwTestMode' => (new Flutterwave($this->pdo(), $settings))->isTestMode(),
            'locations' => Location::all(),
        ]);
    }

    private function saveGeneral(): void
    {
        $settingsToSave = [
            'business_name' => $_POST['business_name'] ?? '',
            'receipt_header' => $_POST['receipt_header'] ?? '',
            'receipt_footer' => $_POST['receipt_footer'] ?? '',
            'theme_color' => $_POST['theme_color'] ?? '#2c2c2c',
            'theme_accent' => $_POST['theme_accent'] ?? '#ffc107',
            'theme_cart' => $_POST['theme_cart'] ?? '#3e2723',
        ];
        if ($this->role() === 'dev') {
            if (isset($_POST['license_tier'])) {
                $tier = strtolower((string)$_POST['license_tier']);
                if ($tier === 'hospitality') {
                    $tier = 'pro+';
                }
                $settingsToSave['license_tier'] = $tier;
            }
            if (isset($_POST['lockout_date'])) {
                $settingsToSave['lockout_date'] = $_POST['lockout_date'];
            }
        }
        Setting::save($settingsToSave);
        $this->flash('success', 'Settings saved.');
    }

    private function saveSurcharge(): void
    {
        $id = SurchargeReason::save(
            !empty($_POST['surcharge_id']) ? (int)$_POST['surcharge_id'] : null,
            (string)($_POST['name'] ?? ''),
            (float)($_POST['default_amount'] ?? 0),
            (float)($_POST['default_percent'] ?? 0),
            !isset($_POST['is_active']) || $_POST['is_active'] === '1',
            (int)($_POST['sort_order'] ?? 0)
        );
        $this->flash('success', 'Surcharge reason #' . $id . ' saved. It is now available on the POS payment window.');
    }

    private function savePayments(): void
    {
        $existing = Setting::all();
        $secret = trim((string)($_POST['flw_secret_key'] ?? ''));
        $pairs = [
            'flw_enabled' => isset($_POST['flw_enabled']) ? '1' : '0',
            'flw_public_key' => trim((string)($_POST['flw_public_key'] ?? '')),
            'flw_encryption_key' => trim((string)($_POST['flw_encryption_key'] ?? '')),
            'flw_webhook_hash' => trim((string)($_POST['flw_webhook_hash'] ?? '')),
            'flw_currency' => 'ZMW',
        ];
        // Leave the stored secret untouched when the masked field is submitted unchanged.
        if ($secret !== '' && !str_contains($secret, '••••')) {
            $pairs['flw_secret_key'] = $secret;
        } elseif ($secret === '') {
            $pairs['flw_secret_key'] = '';
        } else {
            $pairs['flw_secret_key'] = (string)($existing['flw_secret_key'] ?? '');
        }
        Setting::save($pairs);
        $this->flash('success', 'Payment gateway settings saved.' . ($pairs['flw_enabled'] === '1' ? ' Use "Test keys" to confirm Flutterwave accepts them.' : ''));
    }

    private function saveCurrency(): void
    {
        $display = [];
        foreach ((array)($_POST['fx_display'] ?? []) as $code) {
            $code = strtoupper(trim((string)$code));
            if (preg_match('/^[A-Z]{3}$/', $code) && $code !== Currency::BASE) {
                $display[] = $code;
            }
        }
        $manual = [];
        $codes = (array)($_POST['manual_code'] ?? []);
        $rates = (array)($_POST['manual_rate'] ?? []);
        foreach ($codes as $i => $code) {
            $code = strtoupper(trim((string)$code));
            $rate = (float)($rates[$i] ?? 0);
            if (preg_match('/^[A-Z]{3}$/', $code) && $rate > 0) {
                $manual[$code] = $rate;
            }
        }
        Setting::save([
            'fx_enabled' => isset($_POST['fx_enabled']) ? '1' : '0',
            'fx_display' => implode(',', $display ?: Currency::DEFAULT_DISPLAY),
            'fx_refresh_minutes' => (string)max(5, (int)($_POST['fx_refresh_minutes'] ?? 60)),
            'fx_manual_rates' => json_encode($manual),
        ]);
        $this->flash('success', 'Currency settings saved.');
    }

    private function saveShop(): void
    {
        Setting::save([
            'shop_enabled' => isset($_POST['shop_enabled']) ? '1' : '0',
            'shop_location_id' => (string)(int)($_POST['shop_location_id'] ?? 0),
            'shop_title' => trim((string)($_POST['shop_title'] ?? '')),
            'shop_intro' => trim((string)($_POST['shop_intro'] ?? '')),
            'shop_phone' => trim((string)($_POST['shop_phone'] ?? '')),
            'shop_pay_at_counter' => isset($_POST['shop_pay_at_counter']) ? '1' : '0',
            'shop_pay_online' => isset($_POST['shop_pay_online']) ? '1' : '0',
            'shop_delivery' => isset($_POST['shop_delivery']) ? '1' : '0',
            'shop_delivery_fee' => (string)max(0, (float)($_POST['shop_delivery_fee'] ?? 0)),
            'shop_min_order' => (string)max(0, (float)($_POST['shop_min_order'] ?? 0)),
        ]);
        $this->flash('success', 'Customer ordering settings saved.');
    }
}
