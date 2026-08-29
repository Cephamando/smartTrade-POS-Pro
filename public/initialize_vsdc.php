<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Custom safe .env loader that avoids parse_ini_file() reserved character errors
 */
function loadCustomEnv(string $path): array {
    if (!file_exists($path)) {
        return [];
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $data = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            // Strip outer quotes if present
            if ((str_starts_with($val, '"') && str_ends_with($val, '"')) ||
                (str_starts_with($val, "'") && str_ends_with($val, "'"))) {
                $val = substr($val, 1, -1);
            }
            $data[$key] = $val;
        }
    }
    return $data;
}

$envPath = dirname(__DIR__) . '/.env';
$fileEnv = loadCustomEnv($envPath);

// Resolve configuration values with container environment fallbacks
$dbHost      = getenv('DB_HOST') ?: ($fileEnv['DB_HOST'] ?? 'db');
$dbName      = getenv('DB_NAME') ?: ($fileEnv['DB_NAME'] ?? 'pos_db');
$dbUser      = getenv('DB_USER') ?: ($fileEnv['DB_USER'] ?? 'root');
$dbPass      = getenv('DB_PASS') !== false ? getenv('DB_PASS') : ($fileEnv['DB_PASS'] ?? '');
$vsdcBaseUrl = getenv('ZRA_VSDC_URL') ?: ($fileEnv['ZRA_VSDC_URL'] ?? 'http://vsdc:8080/sandboxvsdc');
$zraTpin     = getenv('ZRA_TPIN') ?: ($fileEnv['ZRA_TPIN'] ?? '1004784338');
$zraBhfId    = getenv('ZRA_BHF_ID') ?: ($fileEnv['ZRA_BHF_ID'] ?? '000');
$zraDvcSrlNo = getenv('ZRA_DVC_SRL_NO') ?: ($fileEnv['ZRA_DVC_SRL_NO'] ?? 'STPOS001V2');

// Database Connection
try {
    $dsn = "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("<div style='font-family:sans-serif;padding:20px;border:2px solid red;'>" .
        "<h2 style='color:red;'>Database Connection Failed</h2>" .
        "<p>" . htmlspecialchars($e->getMessage()) . "</p>" .
        "</div>");
}

// Check if already initialized
$stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'vsdc_sdcId'");
$stmt->execute();
$existingSdc = $stmt->fetchColumn();

if (!empty($existingSdc)) {
    die("<div style='font-family:sans-serif;padding:20px;border:2px solid orange;'>" .
        "<h2 style='color:orange;'>Device Already Initialized</h2>" .
        "<p>VSDC SDC ID: <b>" . htmlspecialchars($existingSdc) . "</b></p>" .
        "<p>Initialization endpoint should only be executed once per device registration.</p>" .
        "</div>");
}

$vsdcUrl = rtrim($vsdcBaseUrl, '/') . '/initializer/selectInitInfo';

$payload = json_encode([
    "tpin"     => $zraTpin,
    "bhfId"    => $zraBhfId,
    "dvcSrlNo" => $zraDvcSrlNo
]);

$ch = curl_init($vsdcUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_TIMEOUT        => 60
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($curlError) {
    die("<div style='font-family:sans-serif;padding:20px;border:2px solid red;'>" .
        "<h2 style='color:red;'>VSDC Container Unreachable</h2>" .
        "<p>CURL Error: " . htmlspecialchars($curlError) . "</p>" .
        "<p>Target URL: <code>" . htmlspecialchars($vsdcUrl) . "</code></p>" .
        "</div>");
}

$res = json_decode($response, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ZRA VSDC Initialization</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; padding: 30px; background: #f8fafc; }
        .card { background: white; padding: 24px; border-radius: 8px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); max-width: 650px; margin: 0 auto; }
        .success { color: #15803d; border-left: 4px solid #16a34a; background: #f0fdf4; padding: 15px; border-radius: 4px; }
        .error { color: #b91c1c; border-left: 4px solid #dc2626; background: #fef2f2; padding: 15px; border-radius: 4px; }
        pre { background: #1e293b; color: #f8fafc; padding: 12px; border-radius: 6px; overflow-x: auto; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #e2e8f0; }
    </style>
</head>
<body>
<div class="card">
    <h2>ZRA Smart Invoice VSDC Handshake</h2>

    <?php if (($res['resultCd'] ?? '') === "000" && !empty($res['data']['info'])): ?>
        <?php
            $info = $res['data']['info'];
            $settings = [
                'vsdc_sdcId'        => $info['sdcId'] ?? '',
                'vsdc_mrcNo'        => $info['mrcNo'] ?? '',
                'vsdc_taxprNm'      => $info['taxprNm'] ?? '',
                'vsdc_last_sale_no' => (string)($info['lastSaleInvcNo'] ?? 0),
                'vsdc_last_invc_no' => (string)($info['lastInvcNo'] ?? 0)
            ];

            $update = $pdo->prepare("REPLACE INTO settings (setting_key, setting_value) VALUES (?, ?)");
            foreach ($settings as $key => $val) {
                $update->execute([$key, (string)$val]);
            }
        ?>
        <div class="success">
            <h3>✔️ Handshake Succeeded</h3>
            <table>
                <tr><th>Device SDC ID</th><td><?= htmlspecialchars($info['sdcId'] ?? '') ?></td></tr>
                <tr><th>MRC Serial No</th><td><?= htmlspecialchars($info['mrcNo'] ?? '') ?></td></tr>
                <tr><th>Registered Name</th><td><?= htmlspecialchars($info['taxprNm'] ?? '') ?></td></tr>
                <tr><th>Branch</th><td><?= htmlspecialchars($info['bhfNm'] ?? '') ?> (<?= htmlspecialchars($info['bhfId'] ?? '') ?>)</td></tr>
                <tr><th>Last Sale Invoice #</th><td><?= htmlspecialchars((string)($info['lastSaleInvcNo'] ?? 0)) ?></td></tr>
            </table>
        </div>
    <?php else: ?>
        <div class="error">
            <h3>⚠️ Initialization Response (Code: <?= htmlspecialchars($res['resultCd'] ?? (string)$httpCode) ?>)</h3>
            <p><?= htmlspecialchars($res['resultMsg'] ?? 'No response message received from VSDC.') ?></p>
            <?php if (($res['resultCd'] ?? '') === '894'): ?>
                <p><em>Note: Code 894 indicates that the VSDC service is running locally, but the external ZRA Sandbox gateway is currently unreachable.</em></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <h4>Raw VSDC Response</h4>
    <pre><?= htmlspecialchars(json_encode($res, JSON_PRETTY_PRINT)) ?></pre>
</div>
</body>
</html>
