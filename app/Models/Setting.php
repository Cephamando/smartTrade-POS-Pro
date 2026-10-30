<?php

namespace App\Models;

final class Setting extends Model
{
    public static function defaults(): array
    {
        return [
            'license_tier' => 'lite',
            'business_name' => 'OdeliaPOS',
            'theme_color' => '#3e2723',
            'receipt_name' => '',
            'receipt_header' => '',
            'receipt_footer' => 'Thank you!',
            'lockout_date' => '',
        ];
    }

    public static function all(): array
    {
        $settings = self::defaults();
        try {
            $pdo = self::db();
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'settings'");
            if ($tableCheck && $tableCheck->rowCount() > 0) {
                $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
                while ($row = $stmt->fetch()) {
                    $settings[$row['setting_key']] = $row['setting_value'];
                }
            }
        } catch (\Throwable $e) {
            // Fresh databases may not have settings yet.
        }

        return $settings;
    }

    /** Printed slip: its own name and logo. A blank name uses the business name. A missing receipt logo is copied once from the app logo; a saved blank stays blank. */
    public static function receiptFace(array $settings): array
    {
        $name = trim((string)($settings['receipt_name'] ?? ''));
        if ($name === '') {
            $name = trim((string)($settings['business_name'] ?? ''));
        }
        if ($name === '') {
            $name = 'OdeliaPOS';
        }
        if (array_key_exists('receipt_logo_path', $settings)) {
            $logo = trim((string)$settings['receipt_logo_path']);
        } else {
            $logo = trim((string)($settings['logo_path'] ?? ''));
        }

        return [
            'name' => $name,
            'logo' => $logo,
            'header' => (string)($settings['receipt_header'] ?? ''),
            'footer' => (string)($settings['receipt_footer'] ?? 'Thank you!'),
        ];
    }

    public static function save(array $pairs): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?'
            );
            foreach ($pairs as $key => $val) {
                $stmt->execute([$key, $val, $val]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
