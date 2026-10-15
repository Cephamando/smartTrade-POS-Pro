<?php

namespace App\Controllers;

use App\Helpers\Media;
use App\Models\Location;
use App\Models\Product;
use App\Models\Setting;
use App\Models\SurchargeReason;
use App\Services\Currency;
use App\Services\Flutterwave;
use App\Services\Guide;

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
                } elseif (isset($_POST['save_branding'])) {
                    $this->saveBranding();
                } elseif (isset($_POST['save_library'])) {
                    $this->saveLibrary();
                } elseif (isset($_POST['use_library_image'])) {
                    $this->saveLibrary();
                } elseif (isset($_POST['remove_promo_slide_now'])) {
                    $this->removePromoSlide();
                } elseif (isset($_POST['add_promo_files'])) {
                    $this->addPromoFiles();
                } elseif (isset($_POST['crop_promo_slide'])) {
                    $this->cropPromoSlide();
                } elseif (isset($_POST['save_product_photo'])) {
                    $this->saveProductPhoto();
                } elseif (isset($_POST['save_email'])) {
                    $this->saveEmail();
                } elseif (isset($_POST['save_guide'])) {
                    $this->saveGuide();
                } elseif (isset($_POST['sync_guide'])) {
                    $this->syncGuide();
                } elseif (isset($_POST['bulk_action'])) {
                    $this->bulkSurcharges();
                } else {
                    $this->saveGeneral();
                }
            } catch (\Throwable $e) {
                if ($this->wantsAjax()) {
                    $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() ?: 'Unable to save settings.'], 400);
                }
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
            'menuPhotos' => (($_GET['tab'] ?? '') === 'branding') ? Product::menuForPhotos() : [],
            'libraryImages' => (($_GET['tab'] ?? '') === 'branding') ? Media::list() : [],
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
            'vsias_enabled' => isset($_POST['vsias_enabled']) ? '1' : '0',
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
        $secret = trim((string)($_POST['flw_client_secret'] ?? ''));
        $environment = (string)($_POST['flw_environment'] ?? 'live') === 'sandbox' ? 'sandbox' : 'live';
        $pairs = [
            'flw_enabled' => isset($_POST['flw_enabled']) ? '1' : '0',
            'flw_client_id' => trim((string)($_POST['flw_client_id'] ?? '')),
            'flw_webhook_hash' => trim((string)($_POST['flw_webhook_hash'] ?? '')),
            'flw_environment' => $environment,
            'flw_currency' => 'ZMW',
            'flw_access_token' => '',
            'flw_token_exp' => '0',
        ];
        if ($secret !== '' && !str_contains($secret, '••••')) {
            $pairs['flw_client_secret'] = $secret;
        } elseif ($secret === '') {
            $pairs['flw_client_secret'] = '';
        } else {
            $pairs['flw_client_secret'] = (string)($existing['flw_client_secret'] ?? '');
        }
        Setting::save($pairs);
        $this->flash('success', 'Payment gateway settings saved.' . ($pairs['flw_enabled'] === '1' ? ' Use "Test connection" to confirm Flutterwave accepts them.' : ''));
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
            'shop_pay_at_counter' => '0',
            'shop_pay_online' => isset($_POST['shop_pay_online']) ? '1' : '0',
            'shop_delivery' => isset($_POST['shop_delivery']) ? '1' : '0',
            'shop_delivery_fee' => (string)max(0, (float)($_POST['shop_delivery_fee'] ?? 0)),
            'shop_min_order' => (string)max(0, (float)($_POST['shop_min_order'] ?? 0)),
            'order_webhook_url' => $this->orderWebhookUrl((string)($_POST['order_webhook_url'] ?? '')),
            'order_webhook_secret' => $this->orderWebhookSecret((string)($_POST['order_webhook_secret'] ?? '')),
        ]);
        $this->flash('success', 'Customer ordering settings saved.');
    }

    private function orderWebhookUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }
        if (!\App\Services\OrderWebhook::allowedUrl($url)) {
            throw new \InvalidArgumentException('The order webhook must be an https address. Only localhost may use http.');
        }
        return $url;
    }

    private function orderWebhookSecret(string $secret): string
    {
        $secret = trim($secret);
        if (strlen($secret) > 120) {
            throw new \InvalidArgumentException('The order webhook secret is too long.');
        }
        return $secret;
    }

    private function saveBranding(): void
    {
        $pairs = [];
        foreach (['logo_path' => 'logo', 'shop_banner_path' => 'shop_banner', 'promo_banner_path' => 'promo_banner'] as $key => $field) {
            if (!empty($_POST['remove_' . $field])) {
                $pairs[$key] = '';
                continue;
            }
            $stored = Media::store($field, 'library');
            if ($stored !== null) {
                $pairs[$key] = $stored;
            }
        }
        if ($pairs) {
            Setting::save($pairs);
        }
        $names = $_FILES['promo_slides']['name'] ?? null;
        $hasSlides = is_array($names) ? count(array_filter($names, static fn($n) => $n !== '')) > 0 : (is_string($names) && $names !== '');
        if ($hasSlides) {
            Media::storeMany('promo_slides', 10, 'promo');
        }
        $removeSlide = str_replace('\\', '/', (string)($_POST['remove_promo_slide'] ?? ''));
        if ($removeSlide !== '' && str_starts_with($removeSlide, 'uploads/promo/')) {
            Media::delete($removeSlide);
        }
        $this->flash('success', 'Branding images saved. The shop banner is the landing photo. Promo-folder pictures scroll when no event is published.');
    }

    private function saveEmail(): void
    {
        $existing = Setting::all();
        $pass = (string)($_POST['smtp_pass'] ?? '');
        Setting::save([
            'smtp_host' => trim((string)($_POST['smtp_host'] ?? '')),
            'smtp_port' => (string)max(1, (int)($_POST['smtp_port'] ?? 587)),
            'smtp_user' => trim((string)($_POST['smtp_user'] ?? '')),
            'smtp_pass' => $pass !== '' ? $pass : (string)($existing['smtp_pass'] ?? ''),
            'smtp_from' => trim((string)($_POST['smtp_from'] ?? '')),
            'smtp_from_name' => trim((string)($_POST['smtp_from_name'] ?? '')),
        ]);
        $this->flash('success', 'Email settings saved. Invitations use them when the host and from address are filled in.');
    }

    private function saveGuide(): void
    {
        $existing = Setting::all();
        $oldModel = (string)($existing['gemini_model'] ?? 'gemini-2.5-flash');
        $oldKey = (string)($existing['gemini_api_key'] ?? '');
        $key = trim((string)($_POST['gemini_api_key'] ?? ''));
        $model = (string)($_POST['gemini_model'] ?? 'gemini-2.5-flash');
        if (!isset(Guide::models()[$model])) {
            $model = 'gemini-2.5-flash';
        }
        $savedKey = $key !== '' ? $key : $oldKey;
        $updates = [
            'gemini_api_key' => $savedKey,
            'gemini_model' => $model,
        ];
        if ($model !== $oldModel || ($key !== '' && $key !== $oldKey)) {
            $updates['gemini_cache_name'] = '';
        }
        Setting::save($updates);
        $this->flash('success', 'Guide settings saved. Staff can ask how-to questions from the written manuals.');
    }

    private function syncGuide(): void
    {
        $settings = Setting::all();
        if (!Guide::ready($settings)) {
            if ($this->wantsAjax()) {
                $this->jsonExit(['status' => 'error', 'msg' => 'Save a Gemini API key first.']);
            }
            throw new \RuntimeException('Save a Gemini API key first.');
        }
        $cacheName = (new Guide())->sync($settings);
        Setting::save(['gemini_cache_name' => $cacheName]);
        if ($this->wantsAjax()) {
            $this->jsonExit(['status' => 'success', 'msg' => 'Synced. Cache ID: ' . $cacheName]);
        }
        $this->flash('success', 'Documentation synced to Gemini context cache.');
    }

    private function saveLibrary(): void
    {
        $target = (string)($_POST['target'] ?? '');
        $labels = [
            'logo' => ['logo_path', 'Logo updated from the image library.'],
            'shop_banner' => ['shop_banner_path', 'Shop banner updated from the image library.'],
            'promo_banner' => ['promo_banner_path', 'Promo banner updated from the image library.'],
        ];
        $rawPaths = $_POST['image_paths'] ?? null;
        if (!is_array($rawPaths)) {
            $rawPaths = [(string)($_POST['image_path'] ?? '')];
        }
        $paths = [];
        foreach ($rawPaths as $raw) {
            $raw = trim((string)$raw);
            if ($raw === '') {
                continue;
            }
            $paths[] = Media::existing($raw);
        }
        if (!$paths) {
            throw new \RuntimeException('Choose an image from the library.');
        }
        if ($target === 'promo_slide') {
            foreach ($paths as $slidePath) {
                Media::copyInto($slidePath, 'promo');
            }
            $message = count($paths) === 1
                ? 'Added to the shop slideshow.'
                : count($paths) . ' pictures added to the shop slideshow.';
            $id = 0;
            $path = $paths[0];
        } elseif (count($paths) > 1) {
            throw new \RuntimeException('Choose one image for that. The slideshow can take several.');
        } else {
            $path = $paths[0];
        }
        if ($target === 'promo_slide') {
            // already saved above
        } elseif (isset($labels[$target])) {
            Setting::save([$labels[$target][0] => $path]);
            $message = $labels[$target][1];
            $id = 0;
        } elseif ($target === 'product') {
            $id = (int)($_POST['product_id'] ?? 0);
            $row = Product::menuPhotoTarget($id);
            if (!$row) {
                throw new \RuntimeException('Choose a menu item.');
            }
            Product::setImage($id, $path);
            $message = 'Photo saved for ' . $row['name'] . '.';
        } else {
            throw new \RuntimeException('Choose where to use this image.');
        }
        if ($this->wantsAjax()) {
            $this->jsonExit([
                'status' => 'success',
                'message' => $message,
                'target' => $target,
                'id' => $id,
                'image' => $path,
                'slides' => $target === 'promo_slide' ? Media::folder('promo') : [],
            ]);
        }
        $this->flash('success', $message);
    }

    private function promoSlideResponse(string $message): void
    {
        if ($this->wantsAjax()) {
            $this->jsonExit([
                'status' => 'success',
                'message' => $message,
                'slides' => Media::folder('promo'),
            ]);
        }
        $this->flash('success', $message);
    }

    private function removePromoSlide(): void
    {
        $path = str_replace('\\', '/', (string)($_POST['path'] ?? ''));
        if (!str_starts_with($path, 'uploads/promo/')) {
            throw new \RuntimeException('That picture is not in the slideshow.');
        }
        Media::delete($path);
        $this->promoSlideResponse('Removed from the slideshow.');
    }

    private function addPromoFiles(): void
    {
        $saved = Media::storeMany('promo_files', 10, 'promo');
        if (!$saved) {
            throw new \RuntimeException('Choose at least one image.');
        }
        $message = count($saved) === 1 ? 'Added to the shop slideshow.' : count($saved) . ' pictures added to the shop slideshow.';
        $this->promoSlideResponse($message);
    }

    private function cropPromoSlide(): void
    {
        $path = str_replace('\\', '/', (string)($_POST['path'] ?? ''));
        if (!str_starts_with($path, 'uploads/promo/') || Media::url($path) === '') {
            throw new \RuntimeException('That picture is not in the slideshow.');
        }
        $stored = Media::store('promo_crop', 'promo');
        if ($stored === null) {
            throw new \RuntimeException('The framed picture did not upload. Try a smaller crop.');
        }
        Media::delete($path);
        $this->promoSlideResponse('Slideshow picture updated.');
    }

    private function saveProductPhoto(): void
    {
        $id = (int)($_POST['product_id'] ?? 0);
        $row = Product::menuPhotoTarget($id);
        if (!$row) {
            throw new \RuntimeException('Choose a menu item.');
        }
        if (!empty($_POST['remove_product_photo'])) {
            Product::setImage($id, null);
            $message = 'Photo removed from ' . $row['name'] . '.';
            $url = '';
        } else {
            $stored = Media::store('product_photo', 'library');
            if ($stored === null) {
                throw new \RuntimeException('Crop the photo, then save it.');
            }
            Product::setImage($id, $stored);
            $message = 'Photo saved for ' . $row['name'] . '.';
            $url = Media::url($stored);
        }
        if ($this->wantsAjax()) {
            $this->jsonExit([
                'status' => 'success',
                'message' => $message,
                'id' => $id,
                'image' => $url,
            ]);
        }
        $this->flash('success', $message);
    }

    private function bulkSurcharges(): void
    {
        $ids = $this->postedIds();
        if (!$ids) {
            throw new \RuntimeException('Select at least one surcharge reason.');
        }
        $action = (string)($_POST['bulk_action'] ?? '');
        if ($action === 'delete') {
            foreach ($ids as $id) {
                SurchargeReason::delete($id);
            }
            $this->flash('success', count($ids) . ' surcharge reason' . (count($ids) === 1 ? '' : 's') . ' removed.');
            return;
        }
        if ($action === 'show' || $action === 'hide') {
            $flag = $action === 'show' ? 1 : 0;
            $stmt = $this->pdo()->prepare('UPDATE surcharge_reasons SET is_active = ? WHERE id = ?');
            foreach ($ids as $id) {
                $stmt->execute([$flag, $id]);
            }
            $this->flash('success', count($ids) . ' surcharge reason' . (count($ids) === 1 ? '' : 's') . ($flag ? ' shown on the POS.' : ' hidden from the POS.'));
            return;
        }
        throw new \RuntimeException('That bulk action is not available.');
    }
}
