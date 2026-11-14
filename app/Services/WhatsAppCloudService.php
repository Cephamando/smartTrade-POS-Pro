<?php
declare(strict_types=1);

namespace SmartTrade\Services;

use PDO;
use Exception;

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
     * Connects to MySQL using either pdo_mysql or mysqli, auto-creating the schema if missing
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
                    } catch ( Exception $e ) {
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
                    } catch ( Exception $e ) {
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
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              INDEX `idx_tx_ref` (`tx_ref`),
              INDEX `idx_payment_status` (`payment_status`),
              INDEX `idx_fulfillment_status` (`branch_id`, `payment_status`, `fulfillment_status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        return $conn;
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
    public function sendOrderPaidNotifications(array $order, string $zraReceiptNo, ?string $zraQrUrl = null): void
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
            'COMPLETED'           => "🎉 *ORDER COMPLETED ({$txRef})*\nThank you for ordering with us, {$name}! Enjoy your meal.",
            'CANCELLED'           => "⚠️ *ORDER UPDATE ({$txRef})*\nHi {$name}, your order status has been updated to Cancelled. Please contact us if you need assistance."
        ];

        if ( !isset($statusMessages[$newStatus]) ) {
            return ['success' => false, 'message' => 'No notification template for status'];
        }

        return $this->sendTextMessage((string) $order['customer_phone'], $statusMessages[$newStatus]);
    }
}
