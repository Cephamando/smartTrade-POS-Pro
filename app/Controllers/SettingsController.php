<?php

namespace App\Controllers;

use App\Models\Setting;

final class SettingsController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
            try {
                Setting::save($settingsToSave);
                $this->flash('success', 'Settings saved successfully.');
            } catch (\Throwable $e) {
                $this->flash('error', 'Error saving settings: ' . $e->getMessage());
            }
            $this->redirect('settings');
        }

        $this->render(['settings' => Setting::all()]);
    }
}
