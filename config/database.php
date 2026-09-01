<?php

return [
    'host' => getenv('DB_HOST') ?: 'db',
    'name' => getenv('DB_NAME') ?: 'smartTradePOS_db',
    'user' => getenv('DB_USER') ?: 'smartTradePOSUser',
    'pass' => getenv('DB_PASS') ?: 'smartTradePOS123!',
    'charset' => 'utf8mb4',
];
