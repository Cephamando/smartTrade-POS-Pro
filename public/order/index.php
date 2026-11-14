<?php
declare(strict_types=1);

/**
 * Clean Short-Link Entrypoint: https://smartrade.odeliazm.com/order
 * Ensures /order works even when Apache AllowOverride / mod_rewrite is disabled.
 */
require __DIR__ . '/../storefront/index.php';
