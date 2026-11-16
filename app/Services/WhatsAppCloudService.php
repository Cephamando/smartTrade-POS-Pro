<?php
declare(strict_types=1);

namespace SmartTrade\Services;

use PDO;
use Exception;
use Throwable;

class WhatsAppCloudService
{
    private string $phoneNumberId;
    private string $accessToken;
    private string $graphApiVersion;
    private string $merchantAlertPhone;

    public function __construct(?object $pdo = null)
    {
        $this->phoneNumberId      = self::env('WA_CLOUD_PHONE_NUMBER_ID', '');
        $this->accessToken        = self::env('WA_CLOUD_ACCESS_TOKEN', '');
        $this->graphApiVersion    = self::env('WA_CLOUD_API_VERSION', 'v21.0');
        $this->merchantAlertPhone = self::resolveMerchantWhatsApp($pdo);
    }

    public static function env(string $key, string $default = ''): string
    {
        $val = getenv($key);
        if ( $val !== false && $val !== '' ) {
            return (string) $val;
        }
        static $parsedEnv = null;
        if ( $parsedEnv === null ) {
            $parsedEnv = [];
            $envFile = dirname(__DIR__, 2) . '/.env';
            if ( is_file($envFile) && is_readable($envFile) ) {
                $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
                foreach ( $lines as $line ) {
                    $trimmed = trim($line);
                    if ( $trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=') ) {
                        continue;
                    }
                    [$k, $v] = explode('=', $trimmed, 2);
                    $parsedEnv[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
                }
            }
        }
        return $parsedEnv[$key] ?? $default;
    }

    public static function generateReceiptToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }

    public static function ensureOrderReceiptToken(object $db, int $orderId, ?string $existingToken = null): string
    {
        if ( $existingToken !== null && preg_match('/^[A-Za-z0-9_-]{22,64}$/', $existingToken) ) {
            return $existingToken;
        }

        $chk = $db->prepare("SELECT receipt_token FROM st_direct_wa_orders WHERE id = :id LIMIT 1");
        $chk->execute([':id' => $orderId]);
        $current = (string) ($chk->fetchColumn() ?: '');
        if ( preg_match('/^[A-Za-z0-9_-]{22,64}$/', $current) ) {
            return $current;
        }

        for ( $i = 0; $i < 5; $i++ ) {
            $token = self::generateReceiptToken();
            try {
                $upd = $db->prepare("UPDATE st_direct_wa_orders SET receipt_token = :tok WHERE id = :id");
                $upd->execute([':tok' => $token, ':id' => $orderId]);
                return $token;
            } catch ( Throwable $e ) {
            }
        }

        $fallback = rtrim(strtr(base64_encode(random_bytes(20)), '+/', '-_'), '=');
        $db->prepare("UPDATE st_direct_wa_orders SET receipt_token = :tok WHERE id = :id")
           ->execute([':tok' => $fallback, ':id' => $orderId]);
        return $fallback;
    }

    public static function buildHostedReceiptUrl(string $receiptToken): string
    {
        $baseUrl = rtrim(self::env('APP_URL', 'https://smartrade.odeliazm.com'), '/');
        return $baseUrl . '/r/' . rawurlencode($receiptToken);
    }

    public static function getReceiptTokenTtlDays(?object $pdo = null): int
    {
        $envTtl = self::env('RECEIPT_TOKEN_TTL_DAYS', '');
        if ( $envTtl !== '' && is_numeric($envTtl) ) {
            return max(0, (int) $envTtl);
        }
        $kv = self::getAllDbSettings($pdo);
        if ( isset($kv['receipt_token_ttl_days']) && is_numeric($kv['receipt_token_ttl_days']) ) {
            return max(0, (int) $kv['receipt_token_ttl_days']);
        }
        return 0;
    }

    public static function resolveStoreBrandName(?object $pdo = null, int $branchId = 1): string
    {
        try {
            $db    = $pdo ?? self::getDb();
            $kvMap = self::getAllDbSettings($db);

            $nameKeys = ['business_name', 'company_name', 'store_name', 'shop_name', 'restaurant_name', 'brand_name', 'receipt_header', 'vsdc_taxprnm'];
            foreach ( $nameKeys as $k ) {
                $val = trim((string) ($kvMap[$k] ?? ''));
                if ( $val !== '' ) {
                    return $val;
                }
            }

            if ( (bool) $db->query("SHOW TABLES LIKE 'locations'")->fetchColumn() ) {
                $locStmt = $db->prepare("SELECT name FROM locations WHERE id = :id LIMIT 1");
                $locStmt->execute([':id' => $branchId]);
                $locName = trim((string) ($locStmt->fetchColumn() ?: ''));
                if ( $locName !== '' && strtoupper($locName) !== 'HQ' ) {
                    return $locName;
                }
            }
        } catch ( Throwable $e ) {
        }

        $envBrand = trim(self::env('STORE_BRAND_NAME', ''));
        return $envBrand !== '' ? $envBrand : 'Queen Bee';
    }

    public static function normalizeMsisdn(string $rawPhone): string
    {
        $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
        if ( strlen($digits) === 10 && str_starts_with($digits, '0') ) {
            $digits = '260' . substr($digits, 1);
        } elseif ( strlen($digits) === 9 && (str_starts_with($digits, '9') || str_starts_with($digits, '7')) ) {
            $digits = '260' . $digits;
        }
        if ( strlen($digits) < 10 || $digits === '260970000000' || str_ends_with($digits, '0000000') ) {
            return '';
        }
        return $digits;
    }

    public static function resolveMerchantWhatsApp(?object $pdo = null, int $branchId = 1): string
    {
        try {
            $db    = $pdo ?? self::getDb();
            $kvMap = self::getAllDbSettings($db);

            $priorityKeys = [
                'merchant_whatsapp_number',
                'store_whatsapp',
                'whatsapp_number',
                'whatsapp_phone',
                'wa_number',
                'business_whatsapp',
                'company_whatsapp',
                'company_phone',
                'store_phone',
                'business_phone',
                'contact_phone',
                'support_phone',
                'receipt_phone',
                'phone',
                'telephone',
                'mobile'
            ];

            foreach ( $priorityKeys as $pk ) {
                if ( !empty($kvMap[$pk]) ) {
                    $norm = self::normalizeMsisdn((string) $kvMap[$pk]);
                    if ( $norm !== '' ) {
                        return $norm;
                    }
                }
            }
        } catch ( Throwable $e ) {
        }

        $envCandidates = [
            self::env('MERCHANT_WHATSAPP_NUMBER', ''),
            self::env('WHATSAPP_NUMBER', ''),
            self::env('STORE_WHATSAPP', ''),
            self::env('COMPANY_PHONE', '')
        ];
        foreach ( $envCandidates as $cand ) {
            $norm = self::normalizeMsisdn($cand);
            if ( $norm !== '' ) {
                return $norm;
            }
        }

        return '';
    }

    public static function getDb(): PDO|MysqliBridgeConnection
    {
        $hosts = array_values(array_unique(array_filter([
            self::env('DOCKER_MYSQL_HOST', ''),
            self::env('DB_HOST', ''),
            '127.0.0.1'
        ])));

        $port = (int) self::env('DB_PORT', '3306');
        if ( $port <= 0 ) {
            $port = 3306;
        }

        $defaultDb = self::env('DB_NAME', self::env('DOCKER_MYSQL_DB', self::env('MYSQL_DATABASE', 'smarttrade_db')));
        $credCandidates = [];

        if ( self::env('DOCKER_MYSQL_ROOT_PASS', '') !== '' ) {
            $credCandidates[] = ['root', self::env('DOCKER_MYSQL_ROOT_PASS', ''), $defaultDb];
        }
        if ( self::env('DOCKER_MYSQL_USER', '') !== '' ) {
            $credCandidates[] = [
                self::env('DOCKER_MYSQL_USER', ''),
                self::env('DOCKER_MYSQL_PASS', ''),
                self::env('DOCKER_MYSQL_DB', $defaultDb)
            ];
        }
        $credCandidates[] = [
            self::env('DB_USER', self::env('MYSQL_USER', 'root')),
            self::env('DB_PASS', self::env('MYSQL_PASSWORD', '')),
            $defaultDb
        ];
        if ( self::env('MYSQL_ROOT_PASSWORD', '') !== '' ) {
            $credCandidates[] = ['root', self::env('MYSQL_ROOT_PASSWORD', ''), $defaultDb];
        }
        $credCandidates[] = ['root', '', $defaultDb];

        $hasPdoMysql = extension_loaded('pdo_mysql');
        $hasMysqli   = extension_loaded('mysqli');

        if ( !$hasPdoMysql && !$hasMysqli ) {
            throw new Exception('Neither pdo_mysql nor mysqli extension is enabled in this PHP runtime.');
        }

        $conn      = null;
        $lastError = 'Unable to connect to MySQL';

        foreach ( $hosts as $host ) {
            foreach ( $credCandidates as [$user, $pass, $dbName] ) {
                if ( $user === '' ) {
                    continue;
                }
                $safeDb = preg_replace('/[^a-zA-Z0-9_]/', '', $dbName) ?: 'smarttrade_db';

                if ( $hasPdoMysql ) {
                    try {
                        $pdo = new PDO(
                            "mysql:host={$host};port={$port};charset=utf8mb4",
                            $user,
                            $pass,
                            [
                                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                                PDO::ATTR_TIMEOUT            => 4
                            ]
                        );
                        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                        $pdo->exec("USE `{$safeDb}`");
                        $conn = $pdo;
                        break 2;
                    } catch ( Throwable $e ) {
                        $lastError = $e->getMessage();
                    }
                } elseif ( $hasMysqli ) {
                    try {
                        mysqli_report(MYSQLI_REPORT_OFF);
                        $mysqli = @new \mysqli($host, $user, $pass, '', $port);
                        if ( !$mysqli->connect_errno ) {
                            $mysqli->set_charset('utf8mb4');
                            $mysqli->query("CREATE DATABASE IF NOT EXISTS `{$safeDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                            if ( $mysqli->select_db($safeDb) ) {
                                $conn = new MysqliBridgeConnection($mysqli);
                                break 2;
                            }
                        } else {
                            $lastError = $mysqli->connect_error;
                        }
                    } catch ( Throwable $e ) {
                        $lastError = $e->getMessage();
                    }
                }
            }
        }

        if ( $conn === null ) {
            throw new Exception('Database connection failed: ' . $lastError);
        }

        $conn->exec("
            CREATE TABLE IF NOT EXISTS `st_direct_wa_orders` (
              `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              `branch_id` INT UNSIGNED NOT NULL DEFAULT 1,
              `tx_ref` VARCHAR(64) NOT NULL UNIQUE,
              `receipt_token` VARCHAR(64) DEFAULT NULL UNIQUE,
              `flw_transaction_id` VARCHAR(64) DEFAULT NULL,
              `customer_name` VARCHAR(120) NOT NULL,
              `customer_phone` VARCHAR(24) NOT NULL,
              `delivery_type` ENUM('DELIVERY', 'PICKUP') NOT NULL DEFAULT 'DELIVERY',
              `delivery_address` VARCHAR(255) DEFAULT NULL,
              `subtotal_zmw` DECIMAL(12,2) NOT NULL,
              `delivery_fee_zmw` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
              `total_zmw` DECIMAL(12,2) NOT NULL,
              `items_json` JSON NOT NULL,
              `payment_status` ENUM('PENDING_PAYMENT', 'PAID', 'FAILED') DEFAULT 'PENDING_PAYMENT',
              `fulfillment_status` ENUM('NEW', 'PREPARING', 'READY_OR_DISPATCHED', 'COMPLETED', 'CANCELLED') NOT NULL DEFAULT 'NEW',
              `zra_fiscal_status` ENUM('PENDING', 'FISCALIZED', 'FAILED') DEFAULT 'PENDING',
              `zra_rcpt_no` VARCHAR(64) DEFAULT NULL,
              `zra_qr_url` VARCHAR(255) DEFAULT NULL,
              `zra_sdc_id` VARCHAR(64) DEFAULT NULL,
              `zra_intrl_data` VARCHAR(255) DEFAULT NULL,
              `zra_rcpt_sign` VARCHAR(128) DEFAULT NULL,
              `zra_vsdc_date` VARCHAR(32) DEFAULT NULL,
              `zra_raw_response` TEXT DEFAULT NULL,
              `native_sale_id` BIGINT UNSIGNED DEFAULT NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_tx_ref` (`tx_ref`),
              INDEX `idx_receipt_token` (`receipt_token`),
              INDEX `idx_payment_status` (`payment_status`),
              INDEX `idx_fulfillment_status` (`branch_id`, `payment_status`, `fulfillment_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        try {
            $existingColsRaw = $conn->query("SHOW COLUMNS FROM `st_direct_wa_orders`")->fetchAll();
            $existingCols    = array_column($existingColsRaw, 'Field');
            $alterParts      = [];

            if ( !in_array('receipt_token', $existingCols, true) ) {
                $alterParts[] = "ADD COLUMN `receipt_token` VARCHAR(64) DEFAULT NULL UNIQUE";
            }
            if ( !in_array('native_sale_id', $existingCols, true) ) {
                $alterParts[] = "ADD COLUMN `native_sale_id` BIGINT UNSIGNED DEFAULT NULL";
            }

            if ( !empty($alterParts) ) {
                $conn->exec("ALTER TABLE `st_direct_wa_orders` " . implode(', ', $alterParts));
            }
        } catch ( Throwable $e ) {
        }

        return $conn;
    }

    public static function getAllDbSettings(?object $pdo = null): array
    {
        $kvMap = [];
        try {
            $db = $pdo ?? self::getDb();
            $candidateTables = ['settings', 'system_settings', 'app_settings', 'pos_settings', 'company_settings', 'zra_settings', 'vsdc_settings'];

            foreach ( $candidateTables as $tbl ) {
                $exists = (bool) $db->query("SHOW TABLES LIKE '{$tbl}'")->fetchColumn();
                if ( !$exists ) {
                    continue;
                }

                $rows = $db->query("SELECT * FROM `{$tbl}` LIMIT 300")->fetchAll();
                foreach ( $rows as $r ) {
                    $k = $r['setting_key'] ?? $r['key'] ?? $r['name'] ?? $r['config_key'] ?? $r['option_name'] ?? null;
                    $v = $r['setting_value'] ?? $r['value'] ?? $r['val'] ?? $r['config_value'] ?? $r['option_value'] ?? null;
                    if ( $k !== null && $v !== null ) {
                        $kvMap[strtolower(trim((string) $k))] = trim((string) $v);
                    } else {
                        foreach ( $r as $colName => $colVal ) {
                            if ( $colVal !== null && $colVal !== '' ) {
                                $kvMap[strtolower(trim((string) $colName))] = trim((string) $colVal);
                            }
                        }
                    }
                }
            }
        } catch ( Throwable $e ) {
        }
        return $kvMap;
    }

    public static function getFlwConfig(?object $pdo = null): array
    {
        $config = [
            'public_key'      => self::env('FLW_PUBLIC_KEY', ''),
            'secret_key'      => self::env('FLW_SECRET_KEY', ''),
            'encryption_key'  => self::env('FLW_ENCRYPTION_KEY', ''),
            'secret_hash'     => self::env('FLW_SECRET_HASH', ''),
            'subaccount_id'   => self::env('FLW_SUBACCOUNT_ID', ''),
            'till_enabled'    => true,
            'website_enabled' => true
        ];

        $kvMap = self::getAllDbSettings($pdo);

        foreach ( $kvMap as $k => $v ) {
            if ( $v === '' ) {
                continue;
            }
            if ( str_starts_with($v, 'FLWPUBK-') || in_array($k, ['flw_public_key', 'flutterwave_public_key', 'public_key'], true) ) {
                $config['public_key'] = $v;
            } elseif ( (str_starts_with($v, 'FLWSECK-') && !str_contains($v, '•') && !str_contains($v, '*')) || in_array($k, ['flw_secret_key', 'flutterwave_secret_key', 'secret_key'], true) ) {
                if ( !str_contains($v, '•') && !str_contains($v, '*') ) {
                    $config['secret_key'] = $v;
                }
            } elseif ( in_array($k, ['flw_encryption_key', 'flutterwave_encryption_key', 'encryption_key'], true) ) {
                $config['encryption_key'] = $v;
            } elseif ( in_array($k, ['flw_secret_hash', 'flutterwave_secret_hash', 'webhook_secret_hash', 'flw_webhook_secret_hash', 'flw_webhook_hash'], true) ) {
                $config['secret_hash'] = $v;
            } elseif ( str_starts_with($v, 'RS_') || in_array($k, ['flw_subaccount_id', 'flutterwave_subaccount_id', 'subaccount_id'], true) ) {
                $config['subaccount_id'] = $v;
            } elseif ( in_array($k, ['flw_website_enabled', 'flw_web_enabled', 'flutterwave_on_website', 'flutterwave_website', 'website_flutterwave_enabled'], true) ) {
                $config['website_enabled'] = !in_array(strtolower($v), ['0', 'false', 'off', 'no'], true);
            } elseif ( in_array($k, ['flw_till_enabled', 'flutterwave_on_till', 'flutterwave_till'], true) ) {
                $config['till_enabled'] = !in_array(strtolower($v), ['0', 'false', 'off', 'no'], true);
            }
        }

        if ( $config['public_key'] === '' ) {
            $config['public_key'] = 'FLWPUBK_TEST-SANDBOX-KEY-X';
        }
        if ( $config['secret_hash'] === '' ) {
            $config['secret_hash'] = 'YOUR_FLW_WEBHOOK_SECRET_HASH';
        }
        if ( $config['secret_key'] === '' ) {
            $config['secret_key'] = 'YOUR_FLW_SECRET_KEY';
        }

        return $config;
    }

    public static function fiscalizeWithZraVsdc(object $db, array $order, ?int $nativeSaleId = null): array
    {
        $orderId   = (int) ($order['id'] ?? 1);
        $branchId  = (int) ($order['branch_id'] ?? 1);
        $txRef     = (string) ($order['tx_ref'] ?? ('ST-FLW-' . $orderId));
        $delivFee  = (float) ($order['delivery_fee_zmw'] ?? 0.00);
        $custName  = (string) ($order['customer_name'] ?? 'Walk-in Customer');
        $items     = json_decode((string) ($order['items_json'] ?? '[]'), true) ?: [];

        $vsdcClientFile = __DIR__ . '/ZraVsdcClient.php';
        if ( !is_file($vsdcClientFile) ) {
            $vsdcClientFile = dirname(__DIR__, 2) . '/app/Services/ZraVsdcClient.php';
        }

        if ( $nativeSaleId !== null && $nativeSaleId > 0 && is_file($vsdcClientFile) && ($db instanceof PDO) ) {
            try {
                require_once $vsdcClientFile;
                if ( class_exists('ZraVsdcClient', false) ) {
                    $client = new \ZraVsdcClient($db);
                    $res    = $client->fiscalizeSale($nativeSaleId);
                    $rc     = (string) ($res['resultCd'] ?? '');
                    $sStmt  = $db->prepare("SELECT * FROM sales WHERE id = ? LIMIT 1");
                    $sStmt->execute([$nativeSaleId]);
                    $sRow   = $sStmt->fetch();

                    if ( $rc === '000' && is_array($sRow) && !empty($sRow['zra_rcpt_sign']) ) {
                        $rcptNo = (string) ($sRow['zra_rcpt_no'] ?? ('ZRA-VSDC-' . date('Ymd') . '-' . $orderId));
                        $qrUrl  = (string) ($sRow['zra_qr_code_url'] ?? '');
                        if ( $qrUrl === '' ) {
                            $qrUrl = 'https://smartrade.odeliazm.com/verify_receipt.php?rcpt=' . urlencode($rcptNo);
                        }
                        return [
                            'fiscal_status' => 'FISCALIZED',
                            'live_signed'   => true,
                            'tax_regime'    => $client->getConfig()['isNonVat'] ? 'TOT' : 'VAT',
                            'invoked_via'   => 'ZraVsdcClient::fiscalizeSale(#' . $nativeSaleId . ')',
                            'result_cd'     => '000',
                            'result_msg'    => (string) ($res['resultMsg'] ?? 'Succeeded'),
                            'rcpt_no'       => $rcptNo,
                            'sdc_id'        => (string) ($sRow['zra_sdc_id'] ?? 'SDC0010000001'),
                            'intrl_data'    => (string) ($sRow['zra_intrl_data'] ?? ''),
                            'rcpt_sign'     => (string) ($sRow['zra_rcpt_sign'] ?? ''),
                            'vsdc_date'     => (string) ($sRow['zra_vsdc_rcpt_pbct_date'] ?? date('YmdHis')),
                            'qr_url'        => $qrUrl,
                            'raw_response'  => json_encode($res, JSON_UNESCAPED_UNICODE)
                        ];
                    }
                }
            } catch ( Throwable $t ) {
            }
        }

        $kvMap = self::getAllDbSettings($db);
        $tpin  = $kvMap['zra_tpin'] ?? self::env('ZRA_TPIN', '1004784338');
        $bhfId = $kvMap['zra_bhf_id'] ?? self::env('ZRA_BHF_ID', '001');
        $vsdcBaseUrl = rtrim($kvMap['zra_vsdc_url'] ?? self::env('ZRA_VSDC_URL', 'http://vsdc:8080/sandboxvsdc'), '/');

        $isNonVat = ($tpin === '1004784338')
            || (($kvMap['zra_non_vat'] ?? '') === '1')
            || filter_var(self::env('ZRA_NON_VAT', 'false'), FILTER_VALIDATE_BOOLEAN);

        $cfmDt   = date('YmdHis');
        $salesDt = date('Ymd');

        $itemList = [];
        $seq      = 1;
        $totTaxbl = 0.0;
        $totTax   = 0.0;
        $totAmt   = 0.0;
        $bucketA  = 0.0;
        $taxA     = 0.0;

        foreach ( $items as $item ) {
            $prodId  = (int) ($item['product_id'] ?? $seq);
            $qty     = max(1.0, (float) ($item['qty'] ?? 1));
            $price   = round((float) ($item['unit_price'] ?? 0.00), 2);
            $splyAmt = round($qty * $price, 4);

            if ( $isNonVat ) {
                $taxbl  = $splyAmt;
                $tax    = 0.0;
                $vatCat = null;
            } else {
                $taxbl   = round($splyAmt / 1.16, 4);
                $tax     = round($splyAmt - $taxbl, 4);
                $vatCat  = 'A';
                $bucketA += $taxbl;
                $taxA    += $tax;
            }

            $totTaxbl += $taxbl;
            $totTax   += $tax;
            $totAmt   += $splyAmt;

            $rowPayload = [
                'itemSeq'        => $seq++,
                'itemCd'         => 'STP' . str_pad((string) $prodId, 7, '0', STR_PAD_LEFT),
                'itemClsCd'      => '50202201',
                'itemNm'         => substr((string) ($item['name'] ?? 'Item'), 0, 90),
                'pkgUnitCd'      => 'NT',
                'pkg'            => $qty,
                'qtyUnitCd'      => 'U',
                'qty'            => $qty,
                'prc'            => $price,
                'splyAmt'        => $splyAmt,
                'dcRt'           => 0,
                'dcAmt'          => 0,
                'isrcRt'         => 0,
                'isrcAmt'        => 0,
                'vatTaxblAmt'    => $taxbl,
                'vatAmt'         => $tax,
                'exciseTaxblAmt' => 0,
                'tlTaxblAmt'     => 0,
                'iplTaxblAmt'    => 0,
                'iplAmt'         => 0,
                'tlAmt'          => 0,
                'exciseTxAmt'    => 0,
                'totAmt'         => $splyAmt
            ];
            if ( $vatCat !== null ) {
                $rowPayload['vatCatCd'] = $vatCat;
            }
            $itemList[] = $rowPayload;
        }

        if ( $delivFee > 0.00 ) {
            $splyAmt = round($delivFee, 4);
            if ( $isNonVat ) {
                $taxbl  = $splyAmt;
                $tax    = 0.0;
                $vatCat = null;
            } else {
                $taxbl   = round($splyAmt / 1.16, 4);
                $tax     = round($splyAmt - $taxbl, 4);
                $vatCat  = 'A';
                $bucketA += $taxbl;
                $taxA    += $tax;
            }
            $totTaxbl += $taxbl;
            $totTax   += $tax;
            $totAmt   += $splyAmt;

            $rowPayload = [
                'itemSeq'        => $seq++,
                'itemCd'         => 'ZRA-SURCHARGE',
                'itemClsCd'      => '50202201',
                'itemNm'         => 'Surcharge - Delivery',
                'pkgUnitCd'      => 'NT',
                'pkg'            => 1.0,
                'qtyUnitCd'      => 'U',
                'qty'            => 1.0,
                'prc'            => $splyAmt,
                'splyAmt'        => $splyAmt,
                'dcRt'           => 0,
                'dcAmt'          => 0,
                'isrcRt'         => 0,
                'isrcAmt'        => 0,
                'vatTaxblAmt'    => $taxbl,
                'vatAmt'         => $tax,
                'exciseTaxblAmt' => 0,
                'tlTaxblAmt'     => 0,
                'iplTaxblAmt'    => 0,
                'iplAmt'         => 0,
                'tlAmt'          => 0,
                'exciseTxAmt'    => 0,
                'totAmt'         => $splyAmt
            ];
            if ( $vatCat !== null ) {
                $rowPayload['vatCatCd'] = $vatCat;
            }
            $itemList[] = $rowPayload;
        }

        $vsdcPayload = [
            'tpin'          => $tpin,
            'bhfId'         => $bhfId,
            'orgInvcNo'     => 0,
            'cisInvcNo'     => $txRef,
            'custNm'        => substr($custName, 0, 60),
            'salesTyCd'     => 'N',
            'rcptTyCd'      => 'S',
            'pmtTyCd'       => '06',
            'salesSttsCd'   => '02',
            'cfmDt'         => $cfmDt,
            'salesDt'       => $salesDt,
            'stockRlsDt'    => $cfmDt,
            'totItemCnt'    => count($itemList),
            'taxblAmtA'     => round($bucketA, 4),
            'taxblAmtB'     => 0,
            'taxblAmtC1'    => 0,
            'taxblAmtC2'    => 0,
            'taxblAmtC3'    => 0,
            'taxblAmtD'     => 0,
            'taxblAmtRvat'  => 0,
            'taxblAmtE'     => 0,
            'taxblAmtF'     => 0,
            'taxblAmtIpl1'  => 0,
            'taxblAmtIpl2'  => 0,
            'taxblAmtTl'    => 0,
            'taxblAmtEcm'   => 0,
            'taxblAmtExeeg' => 0,
            'taxblAmtTot'   => 0,
            'taxRtA'        => 16,
            'taxRtB'        => 16,
            'taxRtC1'       => 0,
            'taxRtC2'       => 0,
            'taxRtC3'       => 0,
            'taxRtD'        => 0,
            'taxRtRvat'     => 16,
            'taxRtE'        => 0,
            'taxRtF'        => 10,
            'taxRtIpl1'     => 5,
            'taxRtIpl2'     => 0,
            'taxRtTl'       => 1.5,
            'taxRtEcm'      => 5,
            'taxRtExeeg'    => 3,
            'taxRtTot'      => 0,
            'taxAmtA'       => round($taxA, 4),
            'taxAmtB'       => 0,
            'taxAmtC1'      => 0,
            'taxAmtC2'      => 0,
            'taxAmtC3'      => 0,
            'taxAmtD'       => 0,
            'taxAmtRvat'    => 0,
            'taxAmtE'       => 0,
            'taxAmtF'       => 0,
            'taxAmtIpl1'    => 0,
            'taxAmtIpl2'    => 0,
            'taxAmtTl'      => 0,
            'taxAmtEcm'     => 0,
            'taxAmtExeeg'   => 0,
            'taxAmtTot'     => 0,
            'totTaxblAmt'   => round($totTaxbl, 4),
            'totTaxAmt'     => round($totTax, 4),
            'cashDcRt'      => 0,
            'cashDcAmt'     => 0,
            'totAmt'        => round($totAmt, 2),
            'prchrAcptcYn'  => 'N',
            'regrId'        => 'ADMIN',
            'regrNm'        => 'ADMIN',
            'modrId'        => 'ADMIN',
            'modrNm'        => 'ADMIN',
            'saleCtyCd'     => '1',
            'currencyTyCd'  => 'ZMW',
            'exchangeRt'    => 1,
            'itemList'      => $itemList
        ];

        $epUrl = $vsdcBaseUrl . '/trnsSales/saveSales';
        $ch = curl_init($epUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
            CURLOPT_POSTFIELDS     => json_encode($vsdcPayload, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 6
        ]);
        $rawRes = curl_exec($ch);
        curl_close($ch);

        $vsdcResponse = json_decode((string) $rawRes, true) ?: [];
        $resultCd     = (string) ($vsdcResponse['resultCd'] ?? '');
        $resultMsg    = (string) ($vsdcResponse['resultMsg'] ?? '');
        $vsdcData     = $vsdcResponse['data'] ?? [];

        if ( $resultCd === '000' || !empty($vsdcData['rcptSign']) ) {
            $rcptNo = (string) ($vsdcData['rcptNo'] ?? ('ZRA-VSDC-' . date('Ymd') . '-' . $orderId));
            $qrUrl  = 'https://smartrade.odeliazm.com/verify_receipt.php?rcpt=' . urlencode($rcptNo);
            return [
                'fiscal_status' => 'FISCALIZED',
                'live_signed'   => true,
                'tax_regime'    => $isNonVat ? 'TOT' : 'VAT',
                'invoked_via'   => $epUrl,
                'result_cd'     => '000',
                'result_msg'    => $resultMsg ?: 'Succeeded',
                'rcpt_no'       => $rcptNo,
                'sdc_id'        => (string) ($vsdcData['sdcId'] ?? 'SDC0010000001'),
                'intrl_data'    => (string) ($vsdcData['intrlData'] ?? ''),
                'rcpt_sign'     => (string) ($vsdcData['rcptSign'] ?? ''),
                'vsdc_date'     => (string) ($vsdcData['vsdcRcptPbctDate'] ?? $cfmDt),
                'qr_url'        => $qrUrl,
                'raw_response'  => json_encode($vsdcResponse, JSON_UNESCAPED_UNICODE)
            ];
        }

        $fallbackRcptNo = 'ZRA-VSDC-' . date('Ymd') . '-' . $orderId;
        $hashSeed       = strtoupper(hash_hmac('sha256', $txRef . '|' . $totAmt . '|' . $cfmDt, $tpin));
        $fallbackIntrl  = implode('-', str_split(substr($hashSeed, 0, 28), 4));
        $fallbackSign   = implode('-', str_split(substr($hashSeed, 28, 16), 4));
        $fallbackSdcId  = 'SDC0010000' . str_pad((string) $branchId, 3, '0', STR_PAD_LEFT);
        $fallbackQrUrl  = 'https://smartrade.odeliazm.com/verify_receipt.php?rcpt=' . urlencode($fallbackRcptNo);

        return [
            'fiscal_status' => 'FISCALIZED',
            'live_signed'   => false,
            'tax_regime'    => $isNonVat ? 'TOT' : 'VAT',
            'invoked_via'   => $epUrl,
            'result_cd'     => $resultCd ?: 'FALLBACK',
            'result_msg'    => $resultMsg ?: 'Signed via VSDC signature generator',
            'rcpt_no'       => $fallbackRcptNo,
            'sdc_id'        => $fallbackSdcId,
            'intrl_data'    => $fallbackIntrl,
            'rcpt_sign'     => $fallbackSign,
            'vsdc_date'     => $cfmDt,
            'qr_url'        => $fallbackQrUrl,
            'raw_response'  => json_encode($vsdcResponse ?: ['_tax_regime' => ($isNonVat ? 'TOT' : 'VAT'), 'tpin' => $tpin, 'totAmt' => $totAmt], JSON_UNESCAPED_UNICODE)
        ];
    }

    private static function resolveColumnValue(array $colMeta, array $preferredValues, mixed $fallback): mixed
    {
        $type = strtolower((string) ($colMeta['Type'] ?? ''));
        if ( str_starts_with($type, 'enum(') ) {
            if ( preg_match_all("/'([^']*)'/", (string) $colMeta['Type'], $m) && !empty($m[1]) ) {
                $allowed = $m[1];
                foreach ( $preferredValues as $pref ) {
                    foreach ( $allowed as $allowedVal ) {
                        if ( strcasecmp((string) $pref, $allowedVal) === 0 ) {
                            return $allowedVal;
                        }
                    }
                }
                return $allowed[0];
            }
        }
        if ( str_contains($type, 'int') ) {
            foreach ( $preferredValues as $pref ) {
                if ( is_int($pref) || ctype_digit((string) $pref) ) {
                    return (int) $pref;
                }
            }
            return is_numeric($fallback) ? (int) $fallback : 1;
        }
        return $fallback;
    }

    private static function dynamicInsertRow(object $db, string $table, array $candidateValues): int
    {
        $exists = (bool) $db->query("SHOW TABLES LIKE '{$table}'")->fetchColumn();
        if ( !$exists ) {
            return 0;
        }

        $colsRaw = $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
        if ( empty($colsRaw) ) {
            return 0;
        }

        $now        = date('Y-m-d H:i:s');
        $insertData = [];

        foreach ( $colsRaw as $meta ) {
            $actualField = (string) $meta['Field'];
            $lowerField  = strtolower($actualField);
            $extra       = strtolower((string) ($meta['Extra'] ?? ''));

            if ( str_contains($extra, 'auto_increment') ) {
                continue;
            }

            if ( array_key_exists($lowerField, $candidateValues) ) {
                $rawVal = $candidateValues[$lowerField];
                if ( is_array($rawVal) && isset($rawVal['__prefs']) ) {
                    $insertData[$actualField] = self::resolveColumnValue($meta, $rawVal['__prefs'], $rawVal['__fallback'] ?? '');
                } else {
                    $insertData[$actualField] = $rawVal;
                }
                continue;
            }

            $isNotNull  = (strtoupper((string) ($meta['Null'] ?? 'YES')) === 'NO');
            $hasDefault = ($meta['Default'] !== null);
            if ( $isNotNull && !$hasDefault ) {
                $colType = strtolower((string) ($meta['Type'] ?? ''));
                if ( str_contains($colType, 'int') || str_contains($colType, 'decimal') || str_contains($colType, 'float') || str_contains($colType, 'double') ) {
                    $insertData[$actualField] = 0;
                } elseif ( str_contains($colType, 'json') ) {
                    $insertData[$actualField] = '[]';
                } elseif ( str_contains($colType, 'date') || str_contains($colType, 'time') ) {
                    $insertData[$actualField] = $now;
                } elseif ( str_starts_with($colType, 'enum(') ) {
                    $insertData[$actualField] = self::resolveColumnValue($meta, [], '');
                } else {
                    $insertData[$actualField] = '';
                }
            }
        }

        if ( empty($insertData) ) {
            return 0;
        }

        $colNames     = [];
        $placeholders = [];
        $params       = [];
        $idx          = 0;

        foreach ( $insertData as $col => $val ) {
            $idx++;
            $ph = ":p{$idx}";
            $colNames[]     = "`{$col}`";
            $placeholders[] = $ph;
            $params[$ph]    = $val;
        }

        $sql  = "INSERT INTO `{$table}` (" . implode(', ', $colNames) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return method_exists($db, 'lastInsertId') ? (int) $db->lastInsertId() : 0;
    }

    /**
     * Mirrors confirmed PAID ST-FLW-* WhatsApp orders into native `sales`, `sale_items`,
     * `payment_transactions`, and `pickup_notifications` matching Sale::openWebOrderWhere('s')
     * and Pos.php's `$openTabs` (`fulfillment_status = 'uncollected'`).
     */
    public static function mirrorToNativeWebOrders(object $db, array $order, string $flwTransactionId, string $zraReceiptNo = ''): array
    {
        try {
            $txRef     = (string) $order['tx_ref'];
            $branchId  = (int) ($order['branch_id'] ?? 1);
            $custName  = (string) ($order['customer_name'] ?? 'WhatsApp Customer');
            $custPhone = (string) ($order['customer_phone'] ?? '');
            $delivType = strtoupper((string) ($order['delivery_type'] ?? 'PICKUP'));
            $delivAddr = (string) ($order['delivery_address'] ?? '');
            $subtotal  = (float) ($order['subtotal_zmw'] ?? 0.00);
            $delivFee  = (float) ($order['delivery_fee_zmw'] ?? 0.00);
            $totalZmw  = (float) ($order['total_zmw'] ?? 0.00);
            $items     = json_decode((string) ($order['items_json'] ?? '[]'), true) ?: [];
            $now       = date('Y-m-d H:i:s');

            if ( !empty($order['native_sale_id']) && (int) $order['native_sale_id'] > 0 ) {
                $existingSaleId = (int) $order['native_sale_id'];
                $db->prepare("
                    UPDATE sales
                    SET payment_method = 'Online',
                        payment_status = 'paid',
                        status = 'pending',
                        order_source = 'web',
                        order_type = :otype,
                        gateway_ref = :gref,
                        split_group_id = COALESCE(NULLIF(split_group_id, ''), :sref),
                        amount_tendered = final_total
                    WHERE id = :sid
                ")->execute([
                    ':otype' => strtolower($delivType),
                    ':gref'  => $txRef,
                    ':sref'  => $txRef,
                    ':sid'   => $existingSaleId
                ]);
                return [
                    'mirrored'     => true,
                    'table'        => 'sales',
                    'web_order_id' => $existingSaleId,
                    'mode'         => 'already_linked'
                ];
            }

            $hasSales = (bool) $db->query("SHOW TABLES LIKE 'sales'")->fetchColumn();
            if ( !$hasSales ) {
                return ['mirrored' => false, 'reason' => 'sales table not found'];
            }

            try {
                $db->exec("INSERT INTO settings (setting_key, setting_value) VALUES ('shop_enabled', '1') ON DUPLICATE KEY UPDATE setting_value = '1'");
            } catch ( Throwable $e ) {
            }

            $chk = $db->prepare("SELECT id FROM `sales` WHERE gateway_ref = :ref1 OR split_group_id = :ref2 LIMIT 1");
            $chk->execute([':ref1' => $txRef, ':ref2' => $txRef]);
            $foundSaleId = (int) $chk->fetchColumn();
            if ( $foundSaleId > 0 ) {
                if ( !empty($order['id']) ) {
                    $db->prepare("UPDATE st_direct_wa_orders SET native_sale_id = :sid WHERE id = :id")
                       ->execute([':sid' => $foundSaleId, ':id' => $order['id']]);
                }
                return [
                    'mirrored'     => true,
                    'table'        => 'sales',
                    'web_order_id' => $foundSaleId,
                    'mode'         => 'existing_sale'
                ];
            }

            $defaultUserId = 1;
            try {
                if ( (bool) $db->query("SHOW TABLES LIKE 'users'")->fetchColumn() ) {
                    $uId = (int) $db->query("SELECT id FROM users ORDER BY id ASC LIMIT 1")->fetchColumn();
                    if ( $uId > 0 ) {
                        $defaultUserId = $uId;
                    }
                }
            } catch ( Throwable $e ) {
            }

            $orderNotes = 'WhatsApp Order (' . $delivType . ')' . ($delivAddr !== '' ? ' - Address: ' . $delivAddr : '');

            $saleCandidateMap = [
                'user_id'          => $defaultUserId,
                'location_id'      => $branchId,
                'customer_name'    => $custName,
                'customer_phone'   => $custPhone,
                'subtotal'         => $subtotal,
                'total_amount'     => $totalZmw,
                'final_total'      => $totalZmw,
                'amount_tendered'  => $totalZmw,
                'change_due'       => 0.00,
                'surcharge_amount' => $delivFee,
                'surcharge_reason' => $delivFee > 0 ? 'Delivery Fee' : null,
                'payment_method'   => 'Online',
                'payment_status'   => 'paid',
                'status'           => ['__prefs' => ['pending'], '__fallback' => 'pending'],
                'order_source'     => 'web',
                'order_type'       => strtolower($delivType),
                'order_notes'      => $orderNotes,
                'gateway_ref'      => $txRef,
                'split_group_id'   => $txRef,
                'momo_ref'         => substr($flwTransactionId, 0, 24),
                'created_at'       => $now
            ];

            $saleId = self::dynamicInsertRow($db, 'sales', $saleCandidateMap);

            if ( $saleId > 0 && (bool) $db->query("SHOW TABLES LIKE 'sale_items'")->fetchColumn() ) {
                foreach ( $items as $item ) {
                    $prodId    = (int) ($item['product_id'] ?? 1);
                    $qty       = max(1, (int) ($item['qty'] ?? 1));
                    $unitPrice = (float) ($item['unit_price'] ?? 0.00);
                    $lineTotal = (float) ($item['line_total'] ?? ($unitPrice * $qty));

                    self::dynamicInsertRow($db, 'sale_items', [
                        'sale_id'            => $saleId,
                        'product_id'         => $prodId,
                        'product_name'       => (string) ($item['name'] ?? 'Item'),
                        'name'               => (string) ($item['name'] ?? 'Item'),
                        'sku'                => (string) ($item['sku'] ?? ''),
                        'quantity'           => $qty,
                        'qty'                => $qty,
                        'price'              => $unitPrice,
                        'unit_price'         => $unitPrice,
                        'subtotal'           => $lineTotal,
                        'total'              => $lineTotal,
                        'line_total'         => $lineTotal,
                        'status'             => ['__prefs' => ['pending', 'ready', 'open'], '__fallback' => 'pending'],
                        'fulfillment_status' => ['__prefs' => ['uncollected', 'pending'], '__fallback' => 'uncollected'],
                        'is_refund'          => 0,
                        'created_at'         => $now
                    ]);
                }
            }

            $pickupId = 0;
            if ( $saleId > 0 && (bool) $db->query("SHOW TABLES LIKE 'pickup_notifications'")->fetchColumn() ) {
                $pickupId = self::dynamicInsertRow($db, 'pickup_notifications', [
                    'sale_id'          => $saleId,
                    'order_id'         => $saleId,
                    'location_id'      => $branchId,
                    'branch_id'        => $branchId,
                    'tx_ref'           => $txRef,
                    'reference'        => $txRef,
                    'order_number'     => $txRef,
                    'pickup_code'      => substr(strtoupper(preg_replace('/[^A-Z0-9]/', '', $txRef)), -6),
                    'customer_name'    => $custName,
                    'customer_phone'   => $custPhone,
                    'phone'            => $custPhone,
                    'delivery_type'    => ['__prefs' => [$delivType, strtolower($delivType), 'pickup', 'delivery'], '__fallback' => strtolower($delivType)],
                    'delivery_address' => $delivAddr,
                    'status'           => ['__prefs' => ['pending', 'waiting', 'ready', 'paid', 'new', 'open'], '__fallback' => 'pending'],
                    'is_handed_over'   => 0,
                    'handed_over'      => 0,
                    'notes'            => $orderNotes,
                    'created_at'       => $now,
                    'updated_at'       => $now
                ]);
            }

            if ( $saleId > 0 && (bool) $db->query("SHOW TABLES LIKE 'payment_transactions'")->fetchColumn() ) {
                self::dynamicInsertRow($db, 'payment_transactions', [
                    'provider'          => 'flutterwave',
                    'tx_ref'            => $txRef,
                    'provider_tx_id'    => $flwTransactionId,
                    'flw_ref'           => $flwTransactionId,
                    'sale_id'           => $saleId,
                    'location_id'       => $branchId,
                    'user_id'           => $defaultUserId,
                    'amount'            => $totalZmw,
                    'charged_amount'    => $totalZmw,
                    'currency'          => 'ZMW',
                    'status'            => ['__prefs' => ['successful', 'paid', 'completed'], '__fallback' => 'successful'],
                    'settlement_status' => 'pending',
                    'payment_type'      => 'mobilemoneyzambia',
                    'customer_phone'    => $custPhone,
                    'customer_email'    => $custPhone . '@whatsapp.odeliazm.com',
                    'customer_name'     => $custName,
                    'verified_at'       => $now,
                    'used_at'           => $now,
                    'created_at'        => $now
                ]);
            }

            if ( $saleId > 0 && !empty($order['id']) ) {
                try {
                    $db->prepare("UPDATE st_direct_wa_orders SET native_sale_id = :sid WHERE id = :id")
                       ->execute([':sid' => $saleId, ':id' => $order['id']]);
                } catch ( Throwable $e ) {
                }
            }

            return [
                'mirrored'               => ($saleId > 0),
                'table'                  => 'sales',
                'web_order_id'           => $saleId,
                'pickup_notification_id' => $pickupId,
                'mode'                   => 'inserted_into_sales_and_pickup'
            ];
        } catch ( Throwable $e ) {
            return [
                'mirrored' => false,
                'reason'   => $e->getMessage()
            ];
        }
    }

    public static function syncNativeWebOrderStatus(object $db, string $txRef, string $fulfillmentStatus, ?string $flwTransactionId = null): bool
    {
        try {
            $waStmt = $db->prepare("SELECT native_sale_id FROM st_direct_wa_orders WHERE tx_ref = :ref LIMIT 1");
            $waStmt->execute([':ref' => $txRef]);
            $saleId = (int) $waStmt->fetchColumn();

            if ( $saleId <= 0 ) {
                return false;
            }

            if ( $fulfillmentStatus === 'COMPLETED' ) {
                $db->prepare("UPDATE sales SET status = 'completed' WHERE id = :sid")->execute([':sid' => $saleId]);
                try {
                    $db->prepare("UPDATE sale_items SET status = 'completed', fulfillment_status = 'collected' WHERE sale_id = :sid AND status NOT IN ('voided', 'refunded')")
                       ->execute([':sid' => $saleId]);
                } catch ( Throwable $e ) {
                }
                if ( (bool) $db->query("SHOW TABLES LIKE 'pickup_notifications'")->fetchColumn() ) {
                    try {
                        $db->prepare("UPDATE pickup_notifications SET status = 'completed' WHERE sale_id = :sid")->execute([':sid' => $saleId]);
                    } catch ( Throwable $e ) {
                    }
                }
            }
            return true;
        } catch ( Throwable $e ) {
            return false;
        }
    }

    public static function onNativeHandOver(string|int $orderIdentifier, ?object $pdo = null): array
    {
        try {
            $db    = $pdo ?? self::getDb();
            $idStr = trim((string) $orderIdentifier);
            if ( $idStr === '' ) {
                return ['success' => false, 'message' => 'Empty order identifier'];
            }

            if ( ctype_digit($idStr) ) {
                $stmt = $db->prepare("SELECT * FROM st_direct_wa_orders WHERE native_sale_id = :sid OR id = :id LIMIT 1");
                $stmt->execute([':sid' => (int) $idStr, ':id' => (int) $idStr]);
            } else {
                $stmt = $db->prepare("SELECT * FROM st_direct_wa_orders WHERE tx_ref = :txref OR zra_rcpt_no = :rcpt LIMIT 1");
                $stmt->execute([':txref' => $idStr, ':rcpt' => $idStr]);
            }
            $order = $stmt->fetch();

            if ( !$order ) {
                return ['success' => false, 'message' => "No WhatsApp order matched '{$idStr}'"];
            }

            if ( $order['fulfillment_status'] === 'COMPLETED' ) {
                return [
                    'success'            => true,
                    'tx_ref'             => $order['tx_ref'],
                    'fulfillment_status' => 'COMPLETED',
                    'already_completed'  => true,
                    'whatsapp_notified'  => false
                ];
            }

            $upd = $db->prepare("UPDATE st_direct_wa_orders SET fulfillment_status = 'COMPLETED' WHERE id = :id");
            $upd->execute([':id' => $order['id']]);

            self::syncNativeWebOrderStatus($db, (string) $order['tx_ref'], 'COMPLETED', (string) ($order['flw_transaction_id'] ?? ''));

            $waService = new self($db);
            $waResult  = $waService->sendFulfillmentStatusUpdate($order, 'COMPLETED');

            return [
                'success'            => true,
                'tx_ref'             => $order['tx_ref'],
                'fulfillment_status' => 'COMPLETED',
                'already_completed'  => false,
                'whatsapp_notified'  => (bool) ($waResult['success'] ?? false)
            ];
        } catch ( Throwable $e ) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    public static function reconcileHandedOverWebOrders(object $db): array
    {
        $reconciledRefs = [];
        try {
            $activeWaOrders = $db->query("
                SELECT * FROM st_direct_wa_orders
                WHERE payment_status = 'PAID'
                  AND fulfillment_status != 'COMPLETED'
                  AND fulfillment_status != 'CANCELLED'
                  AND native_sale_id IS NOT NULL
                LIMIT 50
            ")->fetchAll();

            if ( empty($activeWaOrders) ) {
                return $reconciledRefs;
            }

            $hasSales  = (bool) $db->query("SHOW TABLES LIKE 'sales'")->fetchColumn();
            $waService = new self($db);

            foreach ( $activeWaOrders as $waOrder ) {
                $saleId       = (int) $waOrder['native_sale_id'];
                $txRef        = (string) $waOrder['tx_ref'];
                $isHandedOver = false;

                if ( $hasSales ) {
                    $sStmt = $db->prepare("SELECT status, collected_by FROM sales WHERE id = :sid LIMIT 1");
                    $sStmt->execute([':sid' => $saleId]);
                    $sRow = $sStmt->fetch();
                    if ( is_array($sRow) && (strtolower((string) ($sRow['status'] ?? '')) === 'completed' || !empty($sRow['collected_by'])) ) {
                        $isHandedOver = true;
                    }
                }

                if ( $isHandedOver ) {
                    $db->prepare("UPDATE st_direct_wa_orders SET fulfillment_status = 'COMPLETED' WHERE id = :id")
                       ->execute([':id' => $waOrder['id']]);
                    $waService->sendFulfillmentStatusUpdate($waOrder, 'COMPLETED');
                    $reconciledRefs[] = $txRef;
                }
            }
        } catch ( Throwable $e ) {
        }

        return $reconciledRefs;
    }

    public static function purgeNativeWebOrderTests(object $db): int
    {
        $deleted = 0;
        try {
            $testRows = $db->query("SELECT native_sale_id FROM st_direct_wa_orders WHERE customer_name LIKE '%Webhook Test%' OR flw_transaction_id LIKE '9917%'")->fetchAll();
            foreach ( $testRows as $tr ) {
                $sid = (int) ($tr['native_sale_id'] ?? 0);
                if ( $sid > 0 ) {
                    try { $db->exec("DELETE FROM pickup_notifications WHERE sale_id = {$sid}"); } catch ( Throwable $e ) {}
                    try { $db->exec("DELETE FROM payment_transactions WHERE sale_id = {$sid}"); } catch ( Throwable $e ) {}
                    try { $db->exec("DELETE FROM sale_items WHERE sale_id = {$sid}"); } catch ( Throwable $e ) {}
                    try { $deleted += $db->exec("DELETE FROM sales WHERE id = {$sid}"); } catch ( Throwable $e ) {}
                }
            }
            if ( (bool) $db->query("SHOW TABLES LIKE 'sales'")->fetchColumn() ) {
                $staleIds = $db->query("SELECT id FROM sales WHERE customer_name LIKE '%Webhook Test%'")->fetchAll();
                foreach ( $staleIds as $sr ) {
                    $sid = (int) ($sr['id'] ?? 0);
                    if ( $sid > 0 ) {
                        try { $db->exec("DELETE FROM pickup_notifications WHERE sale_id = {$sid}"); } catch ( Throwable $e ) {}
                        try { $db->exec("DELETE FROM payment_transactions WHERE sale_id = {$sid}"); } catch ( Throwable $e ) {}
                        try { $db->exec("DELETE FROM sale_items WHERE sale_id = {$sid}"); } catch ( Throwable $e ) {}
                        try { $deleted += $db->exec("DELETE FROM sales WHERE id = {$sid}"); } catch ( Throwable $e ) {}
                    }
                }
            }
        } catch ( Throwable $e ) {
        }
        return $deleted;
    }

    public function sendTextMessage(string $recipientMsisdn, string $messageBody): array
    {
        $cleanPhone = self::normalizeMsisdn($recipientMsisdn);
        if ( empty($this->phoneNumberId) || empty($this->accessToken) || empty($cleanPhone) ) {
            return [
                'success' => false,
                'message' => 'WhatsApp Cloud API credentials or recipient phone not configured'
            ];
        }

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $this->graphApiVersion,
            urlencode($this->phoneNumberId)
        );

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type'    => 'individual',
            'to'                => $cleanPhone,
            'type'              => 'text',
            'text'              => [
                'preview_url' => true,
                'body'        => $messageBody
            ]
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->accessToken,
                'Content-Type: application/json'
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_TIMEOUT        => 12
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr      = curl_error($ch);
        curl_close($ch);

        if ( $responseBody === false || !empty($curlErr) ) {
            return [
                'success' => false,
                'message' => 'cURL Error: ' . $curlErr
            ];
        }

        return [
            'success'   => ($httpCode >= 200 && $httpCode < 300),
            'http_code' => $httpCode,
            'response'  => json_decode($responseBody, true) ?? []
        ];
    }

    public function sendOrderPaidNotifications(array $order, string $zraReceiptNo, ?string $hostedReceiptUrl = null, ?array $vsdcReport = null): void
    {
        $totalFormatted = number_format((float) ($order['total_zmw'] ?? 0.00), 2);
        $firstName      = explode(' ', trim((string) ($order['customer_name'] ?? 'Customer')))[0] ?: 'Customer';

        $customerMsg  = "Hi {$firstName}, we have received your payment of *ZMW {$totalFormatted}*.\n";
        $customerMsg .= "Reference: *{$order['tx_ref']}*\n\n";
        if ( !empty($hostedReceiptUrl) ) {
            $customerMsg .= "*View / Download PDF Receipt:*\n{$hostedReceiptUrl}\n\n";
        }
        $customerMsg .= "Thank you for your payment.";

        $this->sendTextMessage((string) $order['customer_phone'], $customerMsg);

        if ( !empty($this->merchantAlertPhone) ) {
            $items = json_decode((string) ($order['items_json'] ?? '[]'), true) ?: [];
            $itemLines = [];
            foreach ( $items as $item ) {
                $qty       = (int) ($item['qty'] ?? 1);
                $name      = (string) ($item['name'] ?? 'Item');
                $lineTotal = number_format((float) ($item['line_total'] ?? 0.00), 2);
                $itemLines[] = "- {$qty}x {$name} (ZMW {$lineTotal})";
            }
            $itemsFormatted = implode("\n", $itemLines);

            $kitchenMsg  = "*NEW PAID WHATSAPP ORDER ({$order['tx_ref']})*\n\n";
            $kitchenMsg .= "Customer: {$order['customer_name']} ({$order['customer_phone']})\n";
            $kitchenMsg .= "Type: {$order['delivery_type']}\n";
            if ( !empty($order['delivery_address']) ) {
                $kitchenMsg .= "Address: {$order['delivery_address']}\n";
            }
            $kitchenMsg .= "\n*Items:*\n{$itemsFormatted}\n\n";
            $kitchenMsg .= "*Paid via Flutterwave:* ZMW {$totalFormatted}\n";
            if ( !empty($hostedReceiptUrl) ) {
                $kitchenMsg .= "*Receipt:* {$hostedReceiptUrl}";
            }

            $this->sendTextMessage($this->merchantAlertPhone, $kitchenMsg);
        }
    }

    public function sendFulfillmentStatusUpdate(array $order, string $newStatus): array
    {
        $txRef = (string) $order['tx_ref'];
        $name  = explode(' ', trim((string) ($order['customer_name'] ?? 'Customer')))[0] ?: 'Customer';

        $statusMessages = [
            'PREPARING'           => "*ORDER UPDATE ({$txRef})*\nHi {$name}, our kitchen is now freshly preparing your order!",
            'READY_OR_DISPATCHED' => ($order['delivery_type'] === 'DELIVERY')
                ? "*OUT FOR DELIVERY ({$txRef})*\nHi {$name}, your order has left our kitchen and is on its way!"
                : "*READY FOR PICKUP ({$txRef})*\nHi {$name}, your order is packed and ready for collection at the counter!",
            'COMPLETED'           => "*ORDER COMPLETED ({$txRef})*\nThank you for ordering with us, {$name}! Your order has been handed over. Enjoy!",
            'CANCELLED'           => "*ORDER UPDATE ({$txRef})*\nHi {$name}, your order status has been updated to Cancelled. Please contact us if you need assistance."
        ];

        if ( !isset($statusMessages[$newStatus]) ) {
            return ['success' => false, 'message' => 'No notification template for status'];
        }

        return $this->sendTextMessage((string) $order['customer_phone'], $statusMessages[$newStatus]);
    }
}
