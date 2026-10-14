<?php

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    public const SANDBOX = 'smartTradePOS_sandbox';

    private static ?PDO $production = null;
    private static ?PDO $sandbox = null;
    private static bool $trainingAborted = false;

    public static function trainingAborted(): bool
    {
        return self::$trainingAborted;
    }

    public static function connection(): PDO
    {
        if (self::$trainingAborted) {
            return self::production();
        }
        if (!empty($_SESSION['training_mode'])) {
            if (self::sandboxReady()) {
                return self::sandbox();
            }
            self::leaveBrokenTraining();
            return self::production();
        }
        return self::production();
    }

    public static function production(): PDO
    {
        if (self::$production instanceof PDO) {
            return self::$production;
        }
        $config = self::config();
        self::$production = self::open($config, (string)$config['name']);
        return self::$production;
    }

    public static function sandbox(): PDO
    {
        if (self::$sandbox instanceof PDO) {
            return self::$sandbox;
        }
        self::$sandbox = self::open(self::config(), self::SANDBOX);
        return self::$sandbox;
    }

    private static function sandboxReady(): bool
    {
        try {
            $stmt = self::sandbox()->query("SHOW TABLES LIKE 'sales'");
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private static function leaveBrokenTraining(): void
    {
        self::$trainingAborted = true;
        unset($_SESSION['cart']);
        foreach ([
            'training_mode',
            'training_course',
            'training_step',
            'training_after_id',
            'training_started',
            'training_marks',
            'training_msg',
            'training_fp',
        ] as $key) {
            unset($_SESSION[$key]);
        }
        $_SESSION['swal_type'] = 'error';
        $_SESSION['swal_msg'] = 'Training stopped because the practice data was missing. Live sales were not changed.';
        $path = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'smartrade-training.lock';
        if (is_file($path)) {
            unlink($path);
        }
    }

    private static function config(): array
    {
        $config = require BASE_PATH . '/config/database.php';
        if (!is_array($config)) {
            throw new PDOException('Database config is missing.');
        }
        return $config;
    }

    private static function open(array $config, string $name): PDO
    {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $name,
            $config['charset'] ?? 'utf8mb4'
        );

        return new PDO($dsn, $config['user'], $config['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
}
