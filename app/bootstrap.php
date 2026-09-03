<?php

use App\Core\Autoloader;
use App\Core\Database;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}
if (!defined('APP_PATH')) {
    define('APP_PATH', BASE_PATH . '/app');
}

require_once APP_PATH . '/Core/Autoloader.php';
Autoloader::register();

require_once APP_PATH . '/Helpers/split_payments.php';
require_once APP_PATH . '/Helpers/inventory.php';

$pdo = Database::connection();
