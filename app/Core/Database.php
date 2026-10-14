<?php

namespace App\Core;

use PDO;
use PDOException;

final class Database
{
    public const SANDBOX = 'smartTradePOS_sandbox';

    private static ?PDO $production = null;
    private static ?PDO $sandbox = null;

    public static function connection(): PDO
    {
        if (!empty($_SESSION['training_mode'])) {
            return self::sandbox();
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
