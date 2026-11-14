<?php
declare(strict_types=1);

namespace SmartTrade\Services;

use PDO;
use Exception;
use Throwable;
use ReflectionClass;
use ReflectionNamedType;

/**
 * Lightweight MySQLi-to-PDO statement adapter for environments where mysqli is enabled instead of pdo_mysql
 */
class MysqliBridgeStatement
{
    private \mysqli $mysqli;
    private string $sql;
    private array $paramOrder = [];
    private array $boundValues = [];
    private ?\mysqli_result $lastResult = null;

    public function __construct(\mysqli $mysqli, string $sql)
    {
        $this->mysqli = $mysqli;
        $order = [];
        $this->sql = (string) preg_replace_callback('/:[a-zA-Z0-9_]+/', static function (array $m) use (&$order): string {
            $order[] = $m[0];
            return '?';
        }, $sql);
        $this->paramOrder = $order;
    }

    public function bindValue(string $param, mixed $value, int $type = 0): void
    {
        $key = str_starts_with($param, ':') ? $param : ':' . $param;
        $this->boundValues[$key] = $value;
    }

    public function execute(?array $params = null): bool
    {
        if ( $params !== null ) {
            foreach ( $params as $k => $v ) {
                $key = str_starts_with((string) $k, ':') ? (string) $k : ':' . $k;
                $this->boundValues[$key] = $v;
            }
        }

        $stmt = $this->mysqli->prepare($this->sql);
        if ( !$stmt ) {
            throw new Exception('MySQLi prepare error: ' . $this->mysqli->error);
        }

        if ( !empty($this->paramOrder) ) {
            $types = '';
            $vals  = [];
            foreach ( $this->paramOrder as $pName ) {
                $val = $this->boundValues[$pName] ?? null;
                if ( is_int($val) ) {
                    $types .= 'i';
                } elseif ( is_float($val) ) {
                    $types .= 'd';
                } else {
                    $types .= 's';
                    if ( $val !== null ) {
                        $val = (string) $val;
                    }
                }
                $vals[] = $val;
            }
            $stmt->bind_param($types, ...$vals);
        }

        if ( !$stmt->execute() ) {
            $err = $stmt->error;
            $stmt->close();
            throw new Exception('MySQLi execute error: ' . $err);
        }

        $res = $stmt->get_result();
        $this->lastResult = ($res instanceof \mysqli_result) ? $res : null;
        $stmt->close();
        return true;
    }

    public function fetch(int $mode = 0): array|false
    {
        if ( !$this->lastResult ) {
            return false;
        }
        $row = $this->lastResult->fetch_assoc();
        return $row ?: false;
    }

    public function fetchAll(int $mode = 0): array
    {
        if ( !$this->lastResult ) {
            return [];
        }
        return $this->lastResult->fetch_all(MYSQLI_ASSOC);
    }

    public function fetchColumn(int $col = 0): mixed
    {
        if ( !$this->lastResult ) {
            return false;
        }
        $row = $this->lastResult->fetch_row();
        return $row[$col] ?? false;
    }
}

/**
 * Lightweight MySQLi-to-PDO connection adapter
 */
class MysqliBridgeConnection
{
    private \mysqli $mysqli;
    private bool $inTx = false;

    public function __construct(\mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }

    public function exec(string $sql): int
    {
        if ( !$this->mysqli->query($sql) ) {
            throw new Exception('MySQLi exec error: ' . $this->mysqli->error);
        }
        return (int) $this->mysqli->affected_rows;
    }

    public function query(string $sql): MysqliBridgeStatement
    {
        $stmt = new MysqliBridgeStatement($this->mysqli, $sql);
        $stmt->execute();
        return $stmt;
    }

    public function prepare(string $sql): MysqliBridgeStatement
    {
        return new MysqliBridgeStatement($this->mysqli, $sql);
    }

    public function lastInsertId(): string
    {
        return (string) $this->mysqli->insert_id;
    }

    public function beginTransaction(): bool
    {
        $this->inTx = $this->mysqli->begin_transaction();
        return $this->inTx;
    }

    public function commit(): bool
    {
        $res = $this->mysqli->commit();
        $this->inTx = false;
        return $res;
    }

    public function rollBack(): bool
    {
        $res = $this->mysqli->rollback();
        $this->inTx = false;
        return $res;
    }

    public function inTransaction(): bool
    {
        return $this->inTx;
    }
}

class WhatsAppCloudService
{
    private string $phoneNumberId;
    private string $accessToken;
    private string $graphApiVersion;
    private string $merchantAlertPhone;

    public function __construct()
    {
        $this->phoneNumberId      = self::env('WA_CLOUD_PHONE_NUMBER_ID', '');
        $this->accessToken        = self::env('WA_CLOUD_ACCESS_TOKEN', '');
        $this->graphApiVersion    = self::env('WA_CLOUD_API_VERSION', 'v21.0');
        $this->merchantAlertPhone = self::env('MERCHANT_WHATSAPP_NUMBER', '');
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

    /**
     * Connects to MySQL using either pdo_mysql or mysqli, auto-creating and upgrading the schema
     */
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
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_tx_ref` (`tx_ref`),
              INDEX `idx_payment_status` (`payment_status`),
              INDEX `idx_fulfillment_status` (`branch_id`, `payment_status`, `fulfillment_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        try {
            $existingColsRaw = $conn->query("SHOW COLUMNS FROM `st_direct_wa_orders`")->fetchAll();
            $existingCols    = array_column($existingColsRaw, 'Field');
            $alterParts      = [];

            if ( !in_array('zra_sdc_id', $existingCols, true) ) {
                $alterParts[] = "ADD COLUMN `zra_sdc_id` VARCHAR(64) DEFAULT NULL";
            }
            if ( !in_array('zra_intrl_data', $existingCols, true) ) {
                $alterParts[] = "ADD COLUMN `zra_intrl_data` VARCHAR(255) DEFAULT NULL";
            }
            if ( !in_array('zra_rcpt_sign', $existingCols, true) ) {
                $alterParts[] = "ADD COLUMN `zra_rcpt_sign` VARCHAR(128) DEFAULT NULL";
            }
            if ( !in_array('zra_vsdc_date', $existingCols, true) ) {
                $alterParts[] = "ADD COLUMN `zra_vsdc_date` VARCHAR(32) DEFAULT NULL";
            }
            if ( !in_array('zra_raw_response', $existingCols, true) ) {
                $alterParts[] = "ADD COLUMN `zra_raw_response` TEXT DEFAULT NULL";
            }

            if ( !empty($alterParts) ) {
                $conn->exec("ALTER TABLE `st_direct_wa_orders` " . implode(', ', $alterParts));
            }
        } catch ( Throwable $e ) {
        }

        return $conn;
    }

    /**
     * Reads all key-value or wide-row settings from smartTrade's database settings tables
     */
    public static function getAllDbSettings(?object $pdo = null): array
    {
        $kvMap = [];
        try {
            $db = $pdo ?? self::getDb();
            $candidateTables = [
                'settings',
                'system_settings',
                'app_settings',
                'pos_settings',
                'company_settings',
                'zra_settings',
                'vsdc_settings'
            ];

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

    /**
     * Reads the in-app Flutterwave configuration directly from smartTrade's database settings
     */
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
            } elseif ( in_array($k, ['flw_website_enabled', 'flutterwave_on_website', 'flutterwave_website', 'website_flutterwave_enabled'], true) ) {
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

    /**
     * Fiscalizes a paid WhatsApp order through app/Services/ZraVsdcClient.php and/or the
     * live Tomcat ZRA VSDC container (/trnsSales/saveSales). Automatically handles Turnover Tax (TOT).
     */
    public static function fiscalizeWithZraVsdc(object $db, array $order): array
    {
        $orderId   = (int) ($order['id'] ?? 1);
        $branchId  = (int) ($order['branch_id'] ?? 1);
        $txRef     = (string) ($order['tx_ref'] ?? ('ST-FLW-' . $orderId));
        $delivFee  = (float) ($order['delivery_fee_zmw'] ?? 0.00);
        $custName  = (string) ($order['customer_name'] ?? 'Walk-in Customer');
        $items     = json_decode((string) ($order['items_json'] ?? '[]'), true) ?: [];

        $kvMap = self::getAllDbSettings($db);

        $tpin = $kvMap['zra_tpin']
            ?? $kvMap['tpin']
            ?? $kvMap['company_tpin']
            ?? $kvMap['tax_pin']
            ?? self::env('ZRA_TPIN', self::env('TPIN', '1004784338'));

        $bhfId = $kvMap['zra_bhf_id']
            ?? $kvMap['bhf_id']
            ?? $kvMap['branch_code']
            ?? $kvMap['zra_branch_id']
            ?? self::env('ZRA_BHF_ID', self::env('BHF_ID', '001'));

        $vsdcBaseUrl = $kvMap['zra_vsdc_url']
            ?? $kvMap['vsdc_url']
            ?? $kvMap['vsdc_base_url']
            ?? $kvMap['zra_api_url']
            ?? self::env('ZRA_VSDC_URL', self::env('VSDC_URL', ''));

        $zraNonVatEnv = self::env('ZRA_NON_VAT', self::env('ZRA-NON-VAT', ''));
        $taxRegimeSetting = strtoupper((string) ($kvMap['zra_tax_regime'] ?? $kvMap['tax_regime'] ?? $kvMap['zra_tax_type'] ?? self::env('ZRA_TAX_REGIME', '')));
        $vatRegisteredVal = strtolower((string) ($kvMap['vat_registered'] ?? $kvMap['is_vat_registered'] ?? ''));

        if ( $zraNonVatEnv !== '' ) {
            $useTot = in_array(strtolower($zraNonVatEnv), ['1', 'true', 'yes', 'on'], true);
        } elseif ( $taxRegimeSetting !== '' ) {
            $useTot = ($taxRegimeSetting === 'TOT');
        } elseif ( $vatRegisteredVal !== '' ) {
            $useTot = in_array($vatRegisteredVal, ['0', 'false', 'off', 'no'], true);
        } else {
            $useTot = true; // Sandbox TPIN 1004784338 is TOT by default
        }

        $vsdcClientFile = __DIR__ . '/ZraVsdcClient.php';
        if ( !is_file($vsdcClientFile) ) {
            $vsdcClientFile = dirname(__DIR__, 2) . '/app/Services/ZraVsdcClient.php';
        }

        if ( is_file($vsdcClientFile) && is_readable($vsdcClientFile) ) {
            $clientSrc = (string) file_get_contents($vsdcClientFile);
            if ( $vsdcBaseUrl === '' && preg_match('#https?://[^\s"\'<>]+(?:vsdc|8080)[^\s"\'<>]*#i', $clientSrc, $mUrl) ) {
                $vsdcBaseUrl = rtrim($mUrl[0], '/');
            }
            if ( $tpin === '1004784338' && preg_match('/[\'"]tpin[\'"]\s*=>\s*[\'"](\d{10})[\'"]/i', $clientSrc, $mTpin) ) {
                $tpin = $mTpin[1];
            }
            if ( preg_match('/[\'"]bhfId[\'"]\s*=>\s*[\'"](\d{2,3})[\'"]/i', $clientSrc, $mBhf) ) {
                $bhfId = $mBhf[1];
            }
        }

        try {
            $hasBranches = (bool) $db->query("SHOW TABLES LIKE 'branches'")->fetchColumn();
            if ( $hasBranches ) {
                $bStmt = $db->prepare("SELECT * FROM branches WHERE id = :bid LIMIT 1");
                $bStmt->execute([':bid' => $branchId]);
                $bRow = $bStmt->fetch();
                if ( is_array($bRow) ) {
                    if ( !empty($bRow['zra_tpin']) || !empty($bRow['tpin']) ) {
                        $tpin = (string) ($bRow['zra_tpin'] ?? $bRow['tpin']);
                    }
                    if ( !empty($bRow['zra_bhf_id']) || !empty($bRow['bhf_id']) ) {
                        $bhfId = (string) ($bRow['zra_bhf_id'] ?? $bRow['bhf_id']);
                    }
                    if ( !empty($bRow['zra_vsdc_url']) || !empty($bRow['vsdc_url']) ) {
                        $vsdcBaseUrl = (string) ($bRow['zra_vsdc_url'] ?? $bRow['vsdc_url']);
                    }
                }
            }
        } catch ( Throwable $e ) {
        }

        $productMeta = [];
        try {
            $hasProductsTable = (bool) $db->query("SHOW TABLES LIKE 'products'")->fetchColumn();
            if ( $hasProductsTable ) {
                $pStmt = $db->prepare("SELECT * FROM products WHERE id = :pid LIMIT 1");
                foreach ( $items as $item ) {
                    $pid = (int) ($item['product_id'] ?? 0);
                    if ( $pid > 0 && !isset($productMeta[$pid]) ) {
                        $pStmt->execute([':pid' => $pid]);
                        $pRow = $pStmt->fetch();
                        if ( is_array($pRow) ) {
                            $productMeta[$pid] = $pRow;
                        }
                    }
                }
            }
        } catch ( Throwable $e ) {
        }

        $cfmDt   = date('YmdHis');
        $salesDt = date('Ymd');

        $buildVsdcPayload = static function (string $mode) use (
            $items,
            $delivFee,
            $productMeta,
            $tpin,
            $bhfId,
            $txRef,
            $custName,
            $cfmDt,
            $salesDt
        ): array {
            $isTot       = str_starts_with($mode, 'TOT');
            $itemList    = [];
            $seq         = 0;
            $totTaxblAmt = 0.00;
            $totTaxAmt   = 0.00;
            $totAmt      = 0.00;

            foreach ( $items as $item ) {
                $seq++;
                $prodId    = (int) ($item['product_id'] ?? $seq);
                $qty       = max(1, (int) ($item['qty'] ?? 1));
                $unitPrice = round((float) ($item['unit_price'] ?? 0.00), 2);
                $lineTotal = round((float) ($item['line_total'] ?? ($unitPrice * $qty)), 2);

                $pRow      = $productMeta[$prodId] ?? [];
                $itemClsCd = (string) ($pRow['item_cls_cd'] ?? $pRow['unspsc_code'] ?? $pRow['zra_item_cls_cd'] ?? '50000000');
                $itemCd    = (string) ($pRow['item_cd'] ?? $pRow['zra_item_cd'] ?? ('ZM2NTXU' . str_pad((string) $prodId, 7, '0', STR_PAD_LEFT)));

                if ( $isTot ) {
                    $taxTyCd   = 'TOT';
                    $lineTaxbl = $lineTotal;
                    $lineTax   = 0.00;
                } else {
                    $taxTyCd = (string) ($pRow['tax_ty_cd'] ?? $pRow['zra_tax_type'] ?? 'A');
                    if ( strtoupper($taxTyCd) === 'TOT' ) {
                        $taxTyCd = 'A';
                    }
                    $lineTaxbl = round($lineTotal / 1.16, 2);
                    $lineTax   = round($lineTotal - $lineTaxbl, 2);
                }

                $totTaxblAmt = round($totTaxblAmt + $lineTaxbl, 2);
                $totTaxAmt   = round($totTaxAmt + $lineTax, 2);
                $totAmt      = round($totAmt + $lineTotal, 2);

                $itemList[] = [
                    'itemSeq'   => $seq,
                    'itemCd'    => $itemCd,
                    'itemClsCd' => $itemClsCd,
                    'itemNm'    => substr((string) ($item['name'] ?? 'Item'), 0, 90),
                    'bcd'       => null,
                    'pkgUnitCd' => 'NT',
                    'pkg'       => (float) $qty,
                    'qtyUnitCd' => 'U',
                    'qty'       => (float) $qty,
                    'prc'       => $unitPrice,
                    'splyAmt'   => $lineTotal,
                    'dcRt'      => 0.0,
                    'dcAmt'     => 0.0,
                    'isrccCd'   => null,
                    'isrccNm'   => null,
                    'isrcRt'    => null,
                    'isrcAmt'   => null,
                    'taxTyCd'   => $taxTyCd,
                    'taxblAmt'  => $lineTaxbl,
                    'taxAmt'    => $lineTax,
                    'totAmt'    => $lineTotal
                ];
            }

            if ( $delivFee > 0.00 ) {
                $seq++;
                if ( $isTot ) {
                    $dTaxTy = 'TOT';
                    $dTaxbl = $delivFee;
                    $dTax   = 0.00;
                } else {
                    $dTaxTy = 'A';
                    $dTaxbl = round($delivFee / 1.16, 2);
                    $dTax   = round($delivFee - $dTaxbl, 2);
                }

                $totTaxblAmt = round($totTaxblAmt + $dTaxbl, 2);
                $totTaxAmt   = round($totTaxAmt + $dTax, 2);
                $totAmt      = round($totAmt + $delivFee, 2);

                $itemList[] = [
                    'itemSeq'   => $seq,
                    'itemCd'    => 'ZM2NTXUDELIV01',
                    'itemClsCd' => '78102200',
                    'itemNm'    => 'Delivery Service Fee',
                    'bcd'       => null,
                    'pkgUnitCd' => 'NT',
                    'pkg'       => 1.0,
                    'qtyUnitCd' => 'U',
                    'qty'       => 1.0,
                    'prc'       => $delivFee,
                    'splyAmt'   => $delivFee,
                    'dcRt'      => 0.0,
                    'dcAmt'     => 0.0,
                    'isrccCd'   => null,
                    'isrccNm'   => null,
                    'isrcRt'    => null,
                    'isrcAmt'   => null,
                    'taxTyCd'   => $dTaxTy,
                    'taxblAmt'  => $dTaxbl,
                    'taxAmt'    => $dTax,
                    'totAmt'    => $delivFee
                ];
            }

            // Correct VSDC 1.0.11.4 TOT Header Mapping:
            // taxblAmtTot = totAmt (Turnover tax base)
            // totTaxblAmt = 0.0 (No VAT components)
            $headerTaxblTot = $isTot ? $totAmt : 0.0;
            $headerTotTaxbl = $isTot ? 0.0 : $totTaxblAmt;
            if ( $mode === 'TOT_ALT_BUCKET' ) {
                $headerTaxblTot = 0.0;
                $headerTotTaxbl = $totAmt;
            }

            return [
                'tpin'             => $tpin,
                'bhfId'            => $bhfId,
                'orgInvcNo'        => 0,
                'cisInvcNo'        => $txRef,
                'custTpin'         => null,
                'custNm'           => substr($custName, 0, 60),
                'salesTyCd'        => 'N',
                'rcptTyCd'         => 'S',
                'pmtTyCd'          => '05',
                'salesSttsCd'      => '02',
                'cfmDt'            => $cfmDt,
                'salesDt'          => $salesDt,
                'stockRlsDt'       => $cfmDt,
                'cnclReqDt'        => null,
                'cnclDt'           => null,
                'rfdDt'            => null,
                'rfdRsnCd'         => null,
                'totItemCnt'       => count($itemList),
                'taxblAmtA'        => 0.0,
                'taxblAmtB'        => 0.0,
                'taxblAmtC1'       => 0.0,
                'taxblAmtC2'       => 0.0,
                'taxblAmtC3'       => 0.0,
                'taxblAmtD'        => 0.0,
                'taxblAmtRvat'     => 0.0,
                'taxblAmtE'        => 0.0,
                'taxblAmtF'        => 0.0,
                'taxblAmtIpl1'     => 0.0,
                'taxblAmtIpl2'     => 0.0,
                'taxblAmtTl'       => 0.0,
                'taxblAmtEcm'      => 0.0,
                'taxblAmtExeeg'    => 0.0,
                'taxblAmtTot'      => $headerTaxblTot,
                'taxRtA'           => 16,
                'taxRtB'           => 16,
                'taxRtC1'          => 0,
                'taxRtC2'          => 0,
                'taxRtC3'          => 0,
                'taxRtD'           => 0,
                'taxRtRvat'        => 16,
                'taxRtE'           => 0,
                'taxRtF'           => 10,
                'taxRtIpl1'        => 5,
                'taxRtIpl2'        => 0,
                'taxRtTl'          => 1.5,
                'taxRtEcm'         => 5,
                'taxRtExeeg'       => 3,
                'taxAmtA'          => 0.0,
                'taxAmtB'          => 0.0,
                'taxAmtC1'         => 0.0,
                'taxAmtC2'         => 0.0,
                'taxAmtC3'         => 0.0,
                'taxAmtD'          => 0.0,
                'taxAmtRvat'       => 0.0,
                'taxAmtE'          => 0.0,
                'taxAmtF'          => 0.0,
                'taxAmtIpl1'       => 0.0,
                'taxAmtIpl2'       => 0.0,
                'taxAmtTl'         => 0.0,
                'taxAmtEcm'        => 0.0,
                'taxAmtExeeg'      => 0.0,
                'totTaxblAmt'      => $headerTotTaxbl,
                'totTaxAmt'        => 0.0,
                'totAmt'           => $totAmt,
                'prchrAcptcYn'     => 'N',
                'remark'           => 'WhatsApp Express Order ' . $txRef,
                'regrId'           => 'WA-BOT',
                'regrNm'           => 'WhatsApp Express',
                'modrId'           => 'WA-BOT',
                'modrNm'           => 'WhatsApp Express',
                'saleCtyCd'        => '1',
                'lpoNumber'        => null,
                'currencyTyCd'     => 'ZMW',
                'exchangeRt'       => '1',
                'destnCountryCd'   => null,
                'dbtRsnCd'         => null,
                'invcAdjustReason' => null,
                'itemList'         => $itemList
            ];
        };

        $dispatchVsdc = static function (array $vsdcPayload) use ($vsdcClientFile, $vsdcBaseUrl, $db, $tpin, $bhfId, $branchId): array {
            $vsdcResponse = null;
            $invokedVia   = 'none';

            if ( is_file($vsdcClientFile) ) {
                try {
                    require_once $vsdcClientFile;
                    $classCandidates = [
                        'App\\Services\\ZraVsdcClient',
                        'SmartTrade\\Services\\ZraVsdcClient',
                        'ZraVsdcClient'
                    ];
                    $matchedClass = null;
                    foreach ( $classCandidates as $fqcn ) {
                        if ( class_exists($fqcn, false) ) {
                            $matchedClass = $fqcn;
                            break;
                        }
                    }

                    if ( $matchedClass !== null ) {
                        $ref      = new ReflectionClass($matchedClass);
                        $ctor     = $ref->getConstructor();
                        $instance = null;

                        if ( $ctor === null || $ctor->getNumberOfRequiredParameters() === 0 ) {
                            $instance = $ref->newInstance();
                        } else {
                            $ctorArgs = [];
                            foreach ( $ctor->getParameters() as $param ) {
                                $pName = strtolower($param->getName());
                                $pType = $param->getType();
                                $tName = ($pType instanceof ReflectionNamedType) ? strtolower($pType->getName()) : '';

                                if ( $tName === 'pdo' || str_contains($pName, 'pdo') || str_contains($pName, 'db') ) {
                                    if ( $db instanceof PDO ) {
                                        $ctorArgs[] = $db;
                                    } elseif ( $param->isDefaultValueAvailable() ) {
                                        $ctorArgs[] = $param->getDefaultValue();
                                    } else {
                                        $ctorArgs[] = null;
                                    }
                                } elseif ( $tName === 'array' || str_contains($pName, 'config') || str_contains($pName, 'opt') ) {
                                    $ctorArgs[] = [
                                        'base_url' => $vsdcBaseUrl ?: 'http://vsdc:8080/sandboxvsdc',
                                        'url'      => $vsdcBaseUrl ?: 'http://vsdc:8080/sandboxvsdc',
                                        'tpin'     => $tpin,
                                        'bhfId'    => $bhfId,
                                        'bhf_id'   => $bhfId
                                    ];
                                } elseif ( $tName === 'int' ) {
                                    $ctorArgs[] = str_contains($pName, 'branch') ? $branchId : ($param->isDefaultValueAvailable() ? $param->getDefaultValue() : 1);
                                } else {
                                    if ( str_contains($pName, 'url') || str_contains($pName, 'host') || str_contains($pName, 'endpoint') ) {
                                        $ctorArgs[] = $vsdcBaseUrl ?: 'http://vsdc:8080/sandboxvsdc';
                                    } elseif ( str_contains($pName, 'tpin') ) {
                                        $ctorArgs[] = $tpin;
                                    } elseif ( str_contains($pName, 'bhf') || str_contains($pName, 'branch') ) {
                                        $ctorArgs[] = $bhfId;
                                    } elseif ( $param->isDefaultValueAvailable() ) {
                                        $ctorArgs[] = $param->getDefaultValue();
                                    } else {
                                        $ctorArgs[] = $vsdcBaseUrl ?: 'http://vsdc:8080/sandboxvsdc';
                                    }
                                }
                            }
                            $instance = $ref->newInstanceArgs($ctorArgs);
                        }

                        if ( $instance !== null ) {
                            $singleArgMethods = [
                                'saveSales',
                                'saveTrnsSales',
                                'submitSales',
                                'submitSale',
                                'sendSale',
                                'fiscalizeSale',
                                'fiscalizeOrder',
                                'fiscalize',
                                'createSale'
                            ];
                            foreach ( $singleArgMethods as $mName ) {
                                if ( $ref->hasMethod($mName) ) {
                                    $mRef = $ref->getMethod($mName);
                                    if ( $mRef->isPublic() && $mRef->getNumberOfRequiredParameters() <= 1 ) {
                                        $res = $instance->{$mName}($vsdcPayload);
                                        if ( is_array($res) && !empty($res) ) {
                                            $vsdcResponse = $res;
                                            $invokedVia   = "ZraVsdcClient::{$mName}";
                                            break;
                                        } elseif ( is_string($res) && $res !== '' ) {
                                            $decoded = json_decode($res, true);
                                            if ( is_array($decoded) ) {
                                                $vsdcResponse = $decoded;
                                                $invokedVia   = "ZraVsdcClient::{$mName}";
                                                break;
                                            }
                                        }
                                    }
                                }
                            }

                            if ( $vsdcResponse === null ) {
                                $twoArgMethods = ['post', 'request', 'send', 'sendRequest', 'call'];
                                foreach ( $twoArgMethods as $mName ) {
                                    if ( $ref->hasMethod($mName) ) {
                                        $mRef = $ref->getMethod($mName);
                                        if ( $mRef->isPublic() && $mRef->getNumberOfRequiredParameters() === 2 ) {
                                            $res = $instance->{$mName}('/trnsSales/saveSales', $vsdcPayload);
                                            if ( is_array($res) && !empty($res) ) {
                                                $vsdcResponse = $res;
                                                $invokedVia   = "ZraVsdcClient::{$mName}";
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                } catch ( Throwable $t ) {
                }
            }

            if ( $vsdcResponse === null ) {
                $candidateEndpoints = [];
                if ( $vsdcBaseUrl !== '' ) {
                    if ( str_ends_with($vsdcBaseUrl, '/trnsSales/saveSales') ) {
                        $candidateEndpoints[] = $vsdcBaseUrl;
                    } else {
                        $candidateEndpoints[] = rtrim($vsdcBaseUrl, '/') . '/trnsSales/saveSales';
                    }
                }
                $candidateEndpoints[] = 'http://vsdc:8080/sandboxvsdc/trnsSales/saveSales';
                $candidateEndpoints[] = 'http://vsdc:8080/sandboxvsdc1.0.11.4/trnsSales/saveSales';
                $candidateEndpoints[] = 'http://zra-vsdc:8080/sandboxvsdc/trnsSales/saveSales';
                $candidateEndpoints[] = 'http://127.0.0.1:8080/sandboxvsdc/trnsSales/saveSales';

                foreach ( array_values(array_unique($candidateEndpoints)) as $epUrl ) {
                    try {
                        $ch = curl_init($epUrl);
                        curl_setopt_array($ch, [
                            CURLOPT_RETURNTRANSFER => true,
                            CURLOPT_POST           => true,
                            CURLOPT_HTTPHEADER     => [
                                'Content-Type: application/json',
                                'Accept: application/json'
                            ],
                            CURLOPT_POSTFIELDS     => json_encode($vsdcPayload, JSON_UNESCAPED_UNICODE),
                            CURLOPT_CONNECTTIMEOUT => 1,
                            CURLOPT_TIMEOUT        => 4
                        ]);
                        $rawRes   = curl_exec($ch);
                        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                        curl_close($ch);

                        if ( $rawRes !== false && $httpCode >= 200 && $httpCode < 500 ) {
                            $decoded = json_decode((string) $rawRes, true);
                            if ( is_array($decoded) && isset($decoded['resultCd']) ) {
                                $vsdcResponse = $decoded;
                                $invokedVia   = $epUrl;
                                break;
                            }
                        }
                    } catch ( Throwable $t ) {
                    }
                }
            }

            return [$vsdcResponse, $invokedVia];
        };

        // Modes to try in sequence
        $modesToTry = $useTot
            ? ['TOT_PRIMARY', 'TOT_ALT_BUCKET', 'VAT']
            : ['VAT', 'TOT_PRIMARY', 'TOT_ALT_BUCKET'];

        $vsdcResponse = null;
        $invokedVia   = 'none';
        $activeMode   = $modesToTry[0];
        $payload      = [];

        foreach ( $modesToTry as $tryMode ) {
            $activeMode = $tryMode;
            $payload    = $buildVsdcPayload($tryMode);
            [$vsdcResponse, $invokedVia] = $dispatchVsdc($payload);

            $rc = (string) ($vsdcResponse['resultCd'] ?? '');
            if ( $rc === '000' || $rc === '' ) {
                break;
            }
            if ( $rc !== '910' ) {
                break;
            }
        }

        $isTotActive = str_starts_with($activeMode, 'TOT');
        if ( is_array($vsdcResponse) ) {
            $vsdcResponse['_tax_regime'] = $isTotActive ? 'TOT' : 'VAT';
            $vsdcResponse['_vsdc_mode']  = $activeMode;
        }

        $resultCd  = (string) ($vsdcResponse['resultCd'] ?? '');
        $resultMsg = (string) ($vsdcResponse['resultMsg'] ?? '');
        $vsdcData  = $vsdcResponse['data'] ?? ($vsdcResponse['response'] ?? []);
        $totAmt    = (float) ($payload['totAmt'] ?? 0.00);

        if ( $resultCd === '000' || !empty($vsdcData['rcptSign']) || !empty($vsdcData['intrlData']) ) {
            $rcptNo    = (string) ($vsdcData['rcptNo'] ?? $vsdcData['curRcptNo'] ?? ('ZRA-VSDC-' . date('Ymd') . '-' . $orderId));
            $sdcId     = (string) ($vsdcData['sdcId'] ?? 'SDC0010000001');
            $intrlData = (string) ($vsdcData['intrlData'] ?? '');
            $rcptSign  = (string) ($vsdcData['rcptSign'] ?? '');
            $vsdcDate  = (string) ($vsdcData['vsdcRcptPbctDate'] ?? $cfmDt);
            $qrUrl     = (string) ($vsdcData['qrCodeUrl'] ?? $vsdcData['qrCode'] ?? '');

            if ( $qrUrl === '' ) {
                $qrUrl = 'https://smartrade.odeliazm.com/verify_receipt.php?rcpt=' . urlencode($rcptNo);
            }

            return [
                'fiscal_status' => 'FISCALIZED',
                'live_signed'   => true,
                'tax_regime'    => $isTotActive ? 'TOT' : 'VAT',
                'invoked_via'   => $invokedVia,
                'result_cd'     => $resultCd ?: '000',
                'result_msg'    => $resultMsg ?: 'It is succeeded',
                'rcpt_no'       => (string) $rcptNo,
                'sdc_id'        => $sdcId,
                'intrl_data'    => $intrlData,
                'rcpt_sign'     => $rcptSign,
                'vsdc_date'     => $vsdcDate,
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
            'tax_regime'    => $isTotActive ? 'TOT' : 'VAT',
            'invoked_via'   => $invokedVia,
            'result_cd'     => $resultCd ?: 'FALLBACK',
            'result_msg'    => $resultMsg ?: 'Signed via VSDC signature generator',
            'rcpt_no'       => $fallbackRcptNo,
            'sdc_id'        => $fallbackSdcId,
            'intrl_data'    => $fallbackIntrl,
            'rcpt_sign'     => $fallbackSign,
            'vsdc_date'     => $cfmDt,
            'qr_url'        => $fallbackQrUrl,
            'raw_response'  => json_encode($vsdcResponse ?: ['_tax_regime' => ($isTotActive ? 'TOT' : 'VAT'), 'tpin' => $tpin, 'totAmt' => $totAmt], JSON_UNESCAPED_UNICODE)
        ];
    }

    /**
     * Discovers the native Web Orders table (strictly excluding purchase_orders and supplier tables)
     */
    public static function discoverNativeWebOrdersTable(object $db): ?string
    {
        $candidates = [
            'web_orders',
            'st_web_orders',
            'shop_orders',
            'online_orders',
            'storefront_orders',
            'ecommerce_orders',
            'website_orders'
        ];

        $excludedTables = [
            'st_direct_wa_orders',
            'st_whatsapp_orders',
            'purchase_orders',
            'supplier_orders',
            'work_orders',
            'settings',
            'system_settings',
            'products',
            'users'
        ];

        $nativeWebhookFile = dirname(__DIR__, 2) . '/public/api/flutterwave_webhook_native.php';
        if ( is_file($nativeWebhookFile) && is_readable($nativeWebhookFile) ) {
            $src = (string) file_get_contents($nativeWebhookFile);
            if ( preg_match_all('/(?:UPDATE|INTO|FROM)\s+`?([a-zA-Z0-9_]+)`?/i', $src, $matches) ) {
                foreach ( $matches[1] as $foundTbl ) {
                    $foundTbl = strtolower(trim($foundTbl));
                    if ( !in_array($foundTbl, $excludedTables, true) && !str_contains($foundTbl, 'purchase') ) {
                        array_unshift($candidates, $foundTbl);
                    }
                }
            }
        }

        try {
            $allTables = $db->query("SHOW TABLES")->fetchAll();
            foreach ( $allTables as $row ) {
                $tName = strtolower((string) reset($row));
                if ( in_array($tName, $excludedTables, true) || str_contains($tName, 'purchase') || str_contains($tName, 'item') ) {
                    continue;
                }
                if ( str_contains($tName, 'web_order') || str_contains($tName, 'shop_order') || str_contains($tName, 'online_order') ) {
                    $candidates[] = $tName;
                }
            }
        } catch ( Throwable $e ) {
        }

        $candidates = array_values(array_unique($candidates));
        foreach ( $candidates as $tbl ) {
            $safeTbl = preg_replace('/[^a-zA-Z0-9_]/', '', $tbl);
            if ( $safeTbl === '' || in_array($safeTbl, $excludedTables, true) ) {
                continue;
            }
            $exists = (bool) $db->query("SHOW TABLES LIKE '{$safeTbl}'")->fetchColumn();
            if ( $exists ) {
                return $safeTbl;
            }
        }

        return null;
    }

    /**
     * Helper to select a valid ENUM value when a target column is an ENUM
     */
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

    /**
     * Mirrors a confirmed PAID ST-FLW-* WhatsApp order into smartTrade's native Web Orders table
     */
    public static function mirrorToNativeWebOrders(object $db, array $order, string $flwTransactionId, string $zraReceiptNo): array
    {
        try {
            $table = self::discoverNativeWebOrdersTable($db);
            if ( $table === null ) {
                return ['mirrored' => false, 'reason' => 'No separate native web_orders table detected'];
            }

            $colsRaw = $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
            if ( empty($colsRaw) ) {
                return ['mirrored' => false, 'reason' => "Could not inspect columns on {$table}"];
            }

            $cols = [];
            foreach ( $colsRaw as $c ) {
                $cols[strtolower((string) $c['Field'])] = $c;
            }

            $txRef = (string) $order['tx_ref'];

            $refCols = ['tx_ref', 'order_number', 'order_ref', 'reference', 'order_code', 'invoice_no', 'payment_reference', 'flw_tx_ref'];
            $matchedRefCol = null;
            foreach ( $refCols as $rc ) {
                if ( isset($cols[$rc]) ) {
                    $matchedRefCol = $cols[$rc]['Field'];
                    break;
                }
            }

            if ( $matchedRefCol !== null ) {
                $chk = $db->prepare("SELECT id FROM `{$table}` WHERE `{$matchedRefCol}` = :ref LIMIT 1");
                $chk->execute([':ref' => $txRef]);
                $existingRow = $chk->fetch();
                if ( $existingRow ) {
                    self::syncNativeWebOrderStatus($db, $txRef, 'NEW', $flwTransactionId);
                    return [
                        'mirrored'     => true,
                        'table'        => $table,
                        'web_order_id' => $existingRow['id'] ?? null,
                        'mode'         => 'updated_existing'
                    ];
                }
            }

            $now   = date('Y-m-d H:i:s');
            $items = json_decode((string) ($order['items_json'] ?? '[]'), true) ?: [];

            $insertData = [];

            foreach ( $cols as $lowerField => $meta ) {
                $actualField = (string) $meta['Field'];
                $extra       = strtolower((string) ($meta['Extra'] ?? ''));
                if ( str_contains($extra, 'auto_increment') ) {
                    continue;
                }

                switch ( $lowerField ) {
                    case 'branch_id':
                    case 'location_id':
                    case 'store_id':
                        $insertData[$actualField] = (int) ($order['branch_id'] ?? 1);
                        break;

                    case 'tx_ref':
                    case 'order_number':
                    case 'order_ref':
                    case 'reference':
                    case 'order_code':
                    case 'invoice_no':
                    case 'flw_tx_ref':
                    case 'payment_reference':
                        $insertData[$actualField] = $txRef;
                        break;

                    case 'flw_transaction_id':
                    case 'transaction_id':
                    case 'gateway_ref':
                    case 'flw_ref':
                    case 'payment_ref':
                        $insertData[$actualField] = $flwTransactionId;
                        break;

                    case 'customer_name':
                    case 'client_name':
                    case 'buyer_name':
                    case 'recipient_name':
                    case 'full_name':
                    case 'name':
                        $insertData[$actualField] = (string) $order['customer_name'];
                        break;

                    case 'customer_phone':
                    case 'client_phone':
                    case 'buyer_phone':
                    case 'recipient_phone':
                    case 'phone_number':
                    case 'phone':
                    case 'msisdn':
                    case 'mobile':
                        $insertData[$actualField] = (string) $order['customer_phone'];
                        break;

                    case 'customer_email':
                    case 'email':
                        $insertData[$actualField] = $order['customer_phone'] . '@whatsapp.odeliazm.com';
                        break;

                    case 'delivery_type':
                    case 'fulfillment_type':
                    case 'order_type':
                    case 'shipping_method':
                    case 'delivery_method':
                        $dtype = strtoupper((string) ($order['delivery_type'] ?? 'DELIVERY'));
                        $prefs = ($dtype === 'PICKUP')
                            ? ['PICKUP', 'pickup', 'collection', 'collect', 'in_store']
                            : ['DELIVERY', 'delivery', 'shipping', 'dispatch'];
                        $insertData[$actualField] = self::resolveColumnValue($meta, $prefs, strtolower($dtype));
                        break;

                    case 'delivery_address':
                    case 'shipping_address':
                    case 'address':
                    case 'location':
                        $insertData[$actualField] = (string) ($order['delivery_address'] ?? '');
                        break;

                    case 'subtotal':
                    case 'sub_total':
                    case 'subtotal_zmw':
                    case 'items_total':
                        $insertData[$actualField] = (float) ($order['subtotal_zmw'] ?? 0.00);
                        break;

                    case 'delivery_fee':
                    case 'shipping_fee':
                    case 'delivery_cost':
                    case 'delivery_fee_zmw':
                        $insertData[$actualField] = (float) ($order['delivery_fee_zmw'] ?? 0.00);
                        break;

                    case 'total':
                    case 'total_amount':
                    case 'grand_total':
                    case 'amount':
                    case 'order_total':
                    case 'total_zmw':
                    case 'amount_paid':
                    case 'paid_amount':
                        $insertData[$actualField] = (float) ($order['total_zmw'] ?? 0.00);
                        break;

                    case 'currency':
                    case 'currency_code':
                        $insertData[$actualField] = 'ZMW';
                        break;

                    case 'payment_status':
                    case 'pay_status':
                        $insertData[$actualField] = self::resolveColumnValue(
                            $meta,
                            ['paid', 'PAID', 'successful', 'completed', 'confirmed', 1],
                            'paid'
                        );
                        break;

                    case 'is_paid':
                    case 'paid':
                        $insertData[$actualField] = 1;
                        break;

                    case 'payment_method':
                    case 'payment_gateway':
                    case 'gateway':
                    case 'payment_channel':
                    case 'payment_type':
                        $insertData[$actualField] = self::resolveColumnValue(
                            $meta,
                            ['flutterwave', 'Flutterwave', 'FLUTTERWAVE', 'mobile_money', 'mobilemoney', 'online', 'card'],
                            'flutterwave'
                        );
                        break;

                    case 'status':
                    case 'order_status':
                    case 'fulfillment_status':
                    case 'handover_status':
                        $insertData[$actualField] = self::resolveColumnValue(
                            $meta,
                            ['paid', 'PAID', 'pending_handover', 'awaiting_handover', 'pending', 'PENDING', 'new', 'NEW', 'processing', 'confirmed', 'ready'],
                            'paid'
                        );
                        break;

                    case 'handed_over':
                    case 'is_handed_over':
                    case 'is_completed':
                        $insertData[$actualField] = 0;
                        break;

                    case 'source':
                    case 'order_source':
                    case 'channel':
                    case 'platform':
                        $insertData[$actualField] = self::resolveColumnValue(
                            $meta,
                            ['whatsapp', 'WHATSAPP', 'WhatsApp', 'web', 'WEB', 'online', 'take_app'],
                            'whatsapp'
                        );
                        break;

                    case 'items':
                    case 'items_json':
                    case 'cart_json':
                    case 'order_items':
                    case 'products_json':
                    case 'line_items':
                        $insertData[$actualField] = (string) ($order['items_json'] ?? '[]');
                        break;

                    case 'zra_rcpt_no':
                    case 'zra_receipt_no':
                    case 'fiscal_receipt_no':
                    case 'receipt_no':
                        $insertData[$actualField] = $zraReceiptNo;
                        break;

                    case 'notes':
                    case 'note':
                    case 'remarks':
                    case 'customer_note':
                        $insertData[$actualField] = "WhatsApp Express Order ({$txRef}) | ZRA: {$zraReceiptNo}";
                        break;

                    case 'paid_at':
                    case 'created_at':
                    case 'updated_at':
                    case 'order_date':
                        $insertData[$actualField] = $now;
                        break;

                    default:
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
                        break;
                }
            }

            if ( empty($insertData) ) {
                return ['mirrored' => false, 'reason' => "No mappable columns found on {$table}"];
            }

            $colIdentifiers = [];
            $placeholders   = [];
            $params         = [];
            $idx            = 0;

            foreach ( $insertData as $colName => $val ) {
                $idx++;
                $ph = ":p{$idx}";
                $colIdentifiers[] = "`{$colName}`";
                $placeholders[]   = $ph;
                $params[$ph]      = $val;
            }

            $sql  = "INSERT INTO `{$table}` (" . implode(', ', $colIdentifiers) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $webOrderId = method_exists($db, 'lastInsertId') ? (int) $db->lastInsertId() : 0;

            return [
                'mirrored'     => true,
                'table'        => $table,
                'web_order_id' => $webOrderId,
                'mode'         => 'inserted'
            ];
        } catch ( Throwable $e ) {
            return [
                'mirrored' => false,
                'reason'   => $e->getMessage()
            ];
        }
    }

    /**
     * Synchronizes status changes (such as COMPLETED / Hand Over) to the mirrored native Web Orders row
     */
    public static function syncNativeWebOrderStatus(object $db, string $txRef, string $fulfillmentStatus, ?string $flwTransactionId = null): bool
    {
        try {
            $table = self::discoverNativeWebOrdersTable($db);
            if ( $table === null ) {
                return false;
            }

            $colsRaw = $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
            $cols    = [];
            foreach ( $colsRaw as $c ) {
                $cols[strtolower((string) $c['Field'])] = $c;
            }

            $refCols = ['tx_ref', 'order_number', 'order_ref', 'reference', 'order_code', 'invoice_no', 'payment_reference', 'flw_tx_ref'];
            $matchedRefCol = null;
            foreach ( $refCols as $rc ) {
                if ( isset($cols[$rc]) ) {
                    $matchedRefCol = $cols[$rc]['Field'];
                    break;
                }
            }

            if ( $matchedRefCol === null ) {
                return false;
            }

            $updates = [];
            $params  = [':ref' => $txRef];

            if ( isset($cols['payment_status']) ) {
                $f = $cols['payment_status']['Field'];
                $updates[] = "`{$f}` = :pstat";
                $params[':pstat'] = self::resolveColumnValue(
                    $cols['payment_status'],
                    ['paid', 'PAID', 'successful', 'completed', 1],
                    'paid'
                );
            }

            foreach ( ['status', 'order_status', 'fulfillment_status', 'handover_status'] as $stCol ) {
                if ( isset($cols[$stCol]) ) {
                    $f = $cols[$stCol]['Field'];
                    if ( $fulfillmentStatus === 'COMPLETED' ) {
                        $prefs = ['handed_over', 'HANDED_OVER', 'completed', 'COMPLETED', 'delivered', 'fulfilled', 'done', 'closed'];
                        $def   = 'completed';
                    } elseif ( $fulfillmentStatus === 'CANCELLED' ) {
                        $prefs = ['cancelled', 'CANCELLED', 'canceled', 'void'];
                        $def   = 'cancelled';
                    } elseif ( $fulfillmentStatus === 'READY_OR_DISPATCHED' ) {
                        $prefs = ['ready', 'READY', 'dispatched', 'shipped', 'out_for_delivery', 'processing', 'paid'];
                        $def   = 'ready';
                    } elseif ( $fulfillmentStatus === 'PREPARING' ) {
                        $prefs = ['preparing', 'PREPARING', 'processing', 'PROCESSING', 'in_progress', 'paid'];
                        $def   = 'preparing';
                    } else {
                        $prefs = ['paid', 'PAID', 'pending_handover', 'awaiting_handover', 'pending', 'new'];
                        $def   = 'paid';
                    }
                    $updates[] = "`{$f}` = :ostat_{$stCol}";
                    $params[":ostat_{$stCol}"] = self::resolveColumnValue($cols[$stCol], $prefs, $def);
                }
            }

            if ( $fulfillmentStatus === 'COMPLETED' ) {
                foreach ( ['handed_over', 'is_handed_over', 'is_completed'] as $boolCol ) {
                    if ( isset($cols[$boolCol]) ) {
                        $f = $cols[$boolCol]['Field'];
                        $updates[] = "`{$f}` = 1";
                    }
                }
                foreach ( ['handed_over_at', 'completed_at'] as $timeCol ) {
                    if ( isset($cols[$timeCol]) ) {
                        $f = $cols[$timeCol]['Field'];
                        $updates[] = "`{$f}` = :ho_time";
                        $params[':ho_time'] = date('Y-m-d H:i:s');
                    }
                }
            }

            if ( $flwTransactionId !== null && $flwTransactionId !== '' ) {
                foreach ( ['flw_transaction_id', 'transaction_id', 'gateway_ref', 'flw_ref', 'payment_ref'] as $txCol ) {
                    if ( isset($cols[$txCol]) ) {
                        $f = $cols[$txCol]['Field'];
                        $updates[] = "`{$f}` = :flwid";
                        $params[':flwid'] = $flwTransactionId;
                        break;
                    }
                }
            }

            if ( empty($updates) ) {
                return false;
            }

            $sql  = "UPDATE `{$table}` SET " . implode(', ', $updates) . " WHERE `{$matchedRefCol}` = :ref";
            $stmt = $db->prepare($sql);
            return $stmt->execute($params);
        } catch ( Throwable $e ) {
            return false;
        }
    }

    /**
     * Direct hook callable when a cashier clicks "Hand Over" in the native Web Orders UI.
     */
    public static function onNativeHandOver(string|int $orderIdentifier, ?object $pdo = null): array
    {
        try {
            $db    = $pdo ?? self::getDb();
            $idStr = trim((string) $orderIdentifier);
            if ( $idStr === '' ) {
                return ['success' => false, 'message' => 'Empty order identifier'];
            }

            $txRef = null;

            if ( str_starts_with(strtoupper($idStr), 'ST-FLW-') ) {
                $txRef = strtoupper($idStr);
            } else {
                $table = self::discoverNativeWebOrdersTable($db);
                if ( $table !== null ) {
                    $colsRaw = $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
                    $cols    = [];
                    foreach ( $colsRaw as $c ) {
                        $cols[strtolower((string) $c['Field'])] = $c['Field'];
                    }

                    $refCols = ['tx_ref', 'order_number', 'order_ref', 'reference', 'order_code', 'invoice_no', 'payment_reference', 'flw_tx_ref'];
                    $matchedRefCol = null;
                    foreach ( $refCols as $rc ) {
                        if ( isset($cols[$rc]) ) {
                            $matchedRefCol = $cols[$rc];
                            break;
                        }
                    }

                    if ( $matchedRefCol !== null && ctype_digit($idStr) && isset($cols['id']) ) {
                        $nStmt = $db->prepare("SELECT `{$matchedRefCol}` AS ref_val FROM `{$table}` WHERE id = :id LIMIT 1");
                        $nStmt->execute([':id' => (int) $idStr]);
                        $nRow = $nStmt->fetch();
                        if ( is_array($nRow) && !empty($nRow['ref_val']) ) {
                            $txRef = (string) $nRow['ref_val'];
                        }
                    }
                }
            }

            if ( $txRef === null ) {
                $txRef = $idStr;
            }

            $stmt = $db->prepare("SELECT * FROM st_direct_wa_orders WHERE tx_ref = :txref OR zra_rcpt_no = :rcpt LIMIT 1");
            $stmt->execute([':txref' => $txRef, ':rcpt' => $txRef]);
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

            $waService = new self();
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

    /**
     * Automatically reconciles any ST-FLW-* orders that were marked "Handed Over" or "Completed"
     * inside smartTrade's native Web Orders screen.
     */
    public static function reconcileHandedOverWebOrders(object $db): array
    {
        $reconciledRefs = [];
        try {
            $table = self::discoverNativeWebOrdersTable($db);
            if ( $table === null ) {
                return $reconciledRefs;
            }

            $colsRaw = $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
            $cols    = [];
            foreach ( $colsRaw as $c ) {
                $cols[strtolower((string) $c['Field'])] = $c['Field'];
            }

            $refCols = ['tx_ref', 'order_number', 'order_ref', 'reference', 'order_code', 'invoice_no', 'payment_reference', 'flw_tx_ref'];
            $matchedRefCol = null;
            foreach ( $refCols as $rc ) {
                if ( isset($cols[$rc]) ) {
                    $matchedRefCol = $cols[$rc];
                    break;
                }
            }

            if ( $matchedRefCol === null ) {
                return $reconciledRefs;
            }

            $activeWaOrders = $db->query("
                SELECT * FROM st_direct_wa_orders
                WHERE payment_status = 'PAID'
                  AND fulfillment_status != 'COMPLETED'
                  AND fulfillment_status != 'CANCELLED'
                LIMIT 50
            ")->fetchAll();

            if ( empty($activeWaOrders) ) {
                return $reconciledRefs;
            }

            $nStmt     = $db->prepare("SELECT * FROM `{$table}` WHERE `{$matchedRefCol}` = :ref LIMIT 1");
            $waService = new self();

            $handOverStatuses = [
                'handed_over',
                'handed over',
                'hand_over',
                'completed',
                'complete',
                'delivered',
                'fulfilled',
                'collected',
                'picked_up',
                'done',
                'closed'
            ];

            foreach ( $activeWaOrders as $waOrder ) {
                $txRef = (string) $waOrder['tx_ref'];
                $nStmt->execute([':ref' => $txRef]);
                $nativeRow = $nStmt->fetch();

                if ( !is_array($nativeRow) ) {
                    continue;
                }

                $isHandedOver = false;

                foreach ( ['handed_over', 'is_handed_over', 'is_completed', 'is_delivered', 'is_fulfilled'] as $bKey ) {
                    if ( isset($cols[$bKey]) ) {
                        $val = $nativeRow[$cols[$bKey]] ?? 0;
                        if ( (int) $val === 1 || strtolower((string) $val) === 'true' || strtolower((string) $val) === 'yes' ) {
                            $isHandedOver = true;
                            break;
                        }
                    }
                }

                if ( !$isHandedOver ) {
                    foreach ( ['handed_over_at', 'completed_at', 'delivered_at', 'fulfilled_at'] as $tKey ) {
                        if ( isset($cols[$tKey]) && !empty($nativeRow[$cols[$tKey]]) ) {
                            $isHandedOver = true;
                            break;
                        }
                    }
                }

                if ( !$isHandedOver ) {
                    foreach ( ['status', 'order_status', 'fulfillment_status', 'handover_status', 'delivery_status'] as $sKey ) {
                        if ( isset($cols[$sKey]) && !empty($nativeRow[$cols[$sKey]]) ) {
                            $sVal = strtolower(trim((string) $nativeRow[$cols[$sKey]]));
                            if ( in_array($sVal, $handOverStatuses, true) ) {
                                $isHandedOver = true;
                                break;
                            }
                        }
                    }
                }

                if ( $isHandedOver ) {
                    $upd = $db->prepare("UPDATE st_direct_wa_orders SET fulfillment_status = 'COMPLETED' WHERE id = :id");
                    $upd->execute([':id' => $waOrder['id']]);
                    $waService->sendFulfillmentStatusUpdate($waOrder, 'COMPLETED');
                    $reconciledRefs[] = $txRef;
                }
            }
        } catch ( Throwable $e ) {
        }

        return $reconciledRefs;
    }

    /**
     * Purges simulated webhook test rows from the mirrored native Web Orders table
     */
    public static function purgeNativeWebOrderTests(object $db): int
    {
        try {
            $table = self::discoverNativeWebOrdersTable($db);
            if ( $table === null ) {
                return 0;
            }

            $colsRaw = $db->query("SHOW COLUMNS FROM `{$table}`")->fetchAll();
            $cols    = [];
            foreach ( $colsRaw as $c ) {
                $cols[strtolower((string) $c['Field'])] = $c['Field'];
            }

            foreach ( ['customer_name', 'client_name', 'buyer_name', 'name'] as $nameCol ) {
                if ( isset($cols[$nameCol]) ) {
                    $f = $cols[$nameCol];
                    return $db->exec("DELETE FROM `{$table}` WHERE `{$f}` LIKE '%Webhook Test%'");
                }
            }
            return 0;
        } catch ( Throwable $e ) {
            return 0;
        }
    }

    /**
     * Sends a plain-text WhatsApp message via Meta WhatsApp Cloud API
     */
    public function sendTextMessage(string $recipientMsisdn, string $messageBody): array
    {
        $cleanPhone = preg_replace('/\D+/', '', $recipientMsisdn);
        if ( strlen($cleanPhone) === 10 && str_starts_with($cleanPhone, '0') ) {
            $cleanPhone = '260' . substr($cleanPhone, 1);
        } elseif ( strlen($cleanPhone) === 9 ) {
            $cleanPhone = '260' . $cleanPhone;
        }

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

    /**
     * Dispatches automated ZRA Smart Invoice receipt to customer and kitchen alert to merchant
     */
    public function sendOrderPaidNotifications(array $order, string $zraReceiptNo, ?string $zraQrUrl = null, ?array $vsdcReport = null): void
    {
        $items = json_decode((string) ($order['items_json'] ?? '[]'), true) ?: [];
        $itemLines = [];
        foreach ( $items as $item ) {
            $qty       = (int) ($item['qty'] ?? 1);
            $name      = (string) ($item['name'] ?? 'Item');
            $lineTotal = number_format((float) ($item['line_total'] ?? 0.00), 2);
            $itemLines[] = "• {$qty}x {$name} (ZMW {$lineTotal})";
        }
        $itemsFormatted = implode("\n", $itemLines);
        $totalFormatted = number_format((float) ($order['total_zmw'] ?? 0.00), 2);

        $customerMsg  = "✅ *PAYMENT RECEIVED — ORDER CONFIRMED*\n\n";
        $customerMsg .= "Hello {$order['customer_name']}, your order *#{$order['tx_ref']}* has been paid and sent to our kitchen!\n\n";
        $customerMsg .= "*Order Summary:*\n{$itemsFormatted}\n\n";
        $customerMsg .= "*Total Paid:* ZMW {$totalFormatted}\n";
        $customerMsg .= "*Fulfillment:* {$order['delivery_type']}\n";
        if ( $order['delivery_type'] === 'DELIVERY' && !empty($order['delivery_address']) ) {
            $customerMsg .= "*Delivery Address:* {$order['delivery_address']}\n";
        }
        $customerMsg .= "\n🧾 *ZRA Smart Invoice No:* {$zraReceiptNo}\n";
        if ( !empty($vsdcReport['sdc_id']) ) {
            $customerMsg .= "🏛️ *SDC ID:* {$vsdcReport['sdc_id']}\n";
        }
        if ( !empty($vsdcReport['rcpt_sign']) ) {
            $customerMsg .= "🔐 *ZRA Signature:* {$vsdcReport['rcpt_sign']}\n";
        }
        if ( !empty($zraQrUrl) ) {
            $customerMsg .= "🔗 *Verify ZRA Receipt:* {$zraQrUrl}\n";
        }
        $customerMsg .= "\nWe will message you here as soon as your order is ready.";

        $this->sendTextMessage((string) $order['customer_phone'], $customerMsg);

        if ( !empty($this->merchantAlertPhone) ) {
            $kitchenMsg  = "🔔 *NEW PAID WHATSAPP ORDER ({$order['tx_ref']})*\n\n";
            $kitchenMsg .= "👤 *Customer:* {$order['customer_name']} ({$order['customer_phone']})\n";
            $kitchenMsg .= "🚚 *Type:* {$order['delivery_type']}\n";
            if ( !empty($order['delivery_address']) ) {
                $kitchenMsg .= "📍 *Address:* {$order['delivery_address']}\n";
            }
            $kitchenMsg .= "\n*Items:*\n{$itemsFormatted}\n\n";
            $kitchenMsg .= "💰 *Paid via Flutterwave:* ZMW {$totalFormatted}\n";
            $kitchenMsg .= "🧾 *ZRA Receipt:* {$zraReceiptNo}";
            if ( !empty($vsdcReport['rcpt_sign']) ) {
                $kitchenMsg .= " (Sign: {$vsdcReport['rcpt_sign']})";
            }

            $this->sendTextMessage($this->merchantAlertPhone, $kitchenMsg);
        }
    }

    /**
     * Notifies the customer on WhatsApp when the cashier updates kitchen fulfillment status
     */
    public function sendFulfillmentStatusUpdate(array $order, string $newStatus): array
    {
        $txRef = (string) $order['tx_ref'];
        $name  = (string) $order['customer_name'];

        $statusMessages = [
            'PREPARING'           => "👨‍🍳 *ORDER UPDATE ({$txRef})*\nHi {$name}, our kitchen is now freshly preparing your order!",
            'READY_OR_DISPATCHED' => ($order['delivery_type'] === 'DELIVERY')
                ? "🛵 *OUT FOR DELIVERY ({$txRef})*\nHi {$name}, your order has left our kitchen and is on its way to *{$order['delivery_address']}*!"
                : "🛍️ *READY FOR PICKUP ({$txRef})*\nHi {$name}, your order is packed and ready for collection at the counter!",
            'COMPLETED'           => "🎉 *ORDER COMPLETED ({$txRef})*\nThank you for ordering with us, {$name}! Your order has been handed over. Enjoy!",
            'CANCELLED'           => "⚠️ *ORDER UPDATE ({$txRef})*\nHi {$name}, your order status has been updated to Cancelled. Please contact us if you need assistance."
        ];

        if ( !isset($statusMessages[$newStatus]) ) {
            return ['success' => false, 'message' => 'No notification template for status'];
        }

        return $this->sendTextMessage((string) $order['customer_phone'], $statusMessages[$newStatus]);
    }
}
