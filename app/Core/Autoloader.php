<?php

namespace App\Core;

final class Autoloader
{
    public static function register(): void
    {
        spl_autoload_register(static function (string $class): void {
            if ($class === 'ZraVsdcClient') {
                $file = APP_PATH . '/Services/ZraVsdcClient.php';
                if (is_file($file)) {
                    require_once $file;
                }
                return;
            }

            $prefix = 'App\\';
            if (!str_starts_with($class, $prefix)) {
                return;
            }

            $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = APP_PATH . '/' . $relative . '.php';
            if (is_file($file)) {
                require $file;
            }
        });
    }
}
