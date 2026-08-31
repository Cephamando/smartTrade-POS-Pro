<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

$env = parse_ini_file(__DIR__ . '/../.env');
$pdo = new PDO("mysql:host={$env['DB_HOST']};dbname={$env['DB_NAME']};charset=utf8mb4", $env['DB_USER'], $env['DB_PASS'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);

// 1. Get VSDC configuration
$settings = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key LIKE 'vsdc_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
if (empty($settings['vsdc_sdcId'])) {
    die("<h2 style='color:orange;'>Waiting for Initialization...</h2><p>You cannot push a sale until ZRA Sandbox comes online and provides your SDC ID.</p>");
}

// 2. Fetch a specific completed sale (e.g., Sale ID #2)
$saleId = 2;
$sale = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
$sale->execute([$saleId]);
$saleData = $sale->fetch();

if (!$saleData) die("Sale not found.");

// Fetch the items for this sale
$itemsStmt = $pdo->prepare("
    SELECT si.*, p.name, p.tax_class, p.unspsc_code, p.unit
    FROM sale_items si 
    JOIN products p ON si.product_id = p.id 
    WHERE si.sale_id = ?
");
$itemsStmt->execute([$saleId]);
$saleItems = $itemsStmt->fetchAll();

$nextInvcNo = (int)($settings['vsdc_last_sale_no'] ?? 0) + 1;
$currentDate = date('YmdHis');
$shortDate = date('Ymd');

$itemList = [];
$totTaxblAmt = 0;
$totTaxAmt = 0;
$seq = 1;

foreach ($saleItems as $item) {
    $qty = (float)$item['quantity'];
    $price = (float)$item['price']; // Tax inclusive price
    $splyAmt = $qty * $price;       // Total supply amount[cite: 7]
    
    // Reverse-calculate VAT (Assuming 16% Category A)
    $taxblAmt = round($splyAmt / 1.16, 4);
    $vatAmt = round($splyAmt - $taxblAmt, 4);
    
    $totTaxblAmt += $taxblAmt;
    $totTaxAmt += $vatAmt;

    $itemList[] = [
        "itemSeq" => $seq++,
        "itemCd" => (string)$item['product_id'],
        "itemClsCd" => $item['unspsc_code'] ?: "50202201",
        "itemNm" => $item['name'],
        "bcd" => null,
        "pkgUnitCd" => "EA",       // Standard packaging code[cite: 7]
        "pkg" => 0,
        "qtyUnitCd" => "U",        // Standard unit code[cite: 7]
        "qty" => $qty,
        "prc" => $price,
        "splyAmt" => $splyAmt,
        "dcRt" => 0,
        "dcAmt" => 0,
        "vatCatCd" => "A",         // 16% VAT[cite: 7]
        "exciseTxCatCd" => null,
        "vatTaxblAmt" => $taxblAmt,
        "exciseTaxblAmt" => 0,
        "vatAmt" => $vatAmt,
        "exciseTxAmt" => 0,
        "totAmt" => $splyAmt
    ];
}

// 3. Construct the Payload[cite: 7]
$payload = [
    "tpin" => $env['ZRA_TPIN'],
    "bhfId" => $env['ZRA_BHF_ID'],
    "orgInvcNo" => 0,
    "cisInvcNo" => "CIS-INV-" . str_pad($saleId, 6, '0', STR_PAD_LEFT),
    "custNm" => "Walk-in Customer",
    "salesTyCd" => "N",         // Normal Sale[cite: 7]
    "rcptTyCd" => "S",          // Sale Receipt[cite: 7]
    "pmtTyCd" => "01",          // 01 = Cash[cite: 7]
    "salesSttsCd" => "02",      // 02 = Approved[cite: 7]
    "cfmDt" => $currentDate,
    "salesDt" => $shortDate,
    "stockRlsDt" => null,
    "cnclReqDt" => null,
    "cnclDt" => null,
    "rfdDt" => null,
    "rfdRsnCd" => null,
    "totItemCnt" => count($itemList),
    "taxblAmtA" => round($totTaxblAmt, 4),
    "taxblAmtB" => 0,
    "taxblAmtC1" => 0,
    "taxblAmtC2" => 0,
    "taxblAmtC3" => 0,
    "taxblAmtD" => 0,
    "taxblAmtRvat" => 0,
    "taxblAmtE" => 0,
    "taxblAmtF" => 0,
    "taxblAmtIpl1" => 0,
    "taxblAmtIpl2" => 0,
    "taxblAmtTl" => 0,
    "taxblAmtEcm" => 0,
    "taxblAmtExeeg" => 0,
    "taxblAmtTot" => 0,
    "taxRtA" => 16,
    "taxRtB" => 16,
    "taxRtC1" => 0,
    "taxRtC2" => 0,
    "taxRtC3" => 0,
    "taxRtD" => 0,
    "taxRtRvat" => 16,
    "taxRtE" => 0,
    "taxRtF" => 10,
    "taxRtIpl1" => 5,
    "taxRtIpl2" => 0,
    "taxRtTl" => 1.5,
    "taxRtEcm" => 5,
    "taxRtExeeg" => 3,
    "taxRtTot" => 0,
    "taxAmtA" => round($totTaxAmt, 4),
    "taxAmtB" => 0,
    "taxAmtC1" => 0,
    "taxAmtC2" => 0,
    "taxAmtC3" => 0,
    "taxAmtD" => 0,
    "taxAmtRvat" => 0,
    "taxAmtE" => 0,
    "taxAmtF" => 0,
    "taxAmtIpl1" => 0,
    "taxAmtIpl2" => 0,
    "taxAmtTl" => 0,
    "taxAmtEcm" => 0,
    "taxAmtExeeg" => 0,
    "taxAmtTot" => 0,
    "totTaxblAmt" => round($totTaxblAmt, 4),
    "totTaxAmt" => round($totTaxAmt, 4),
    "cashDcRt" => 0,
    "cashDcAmt" => 0,
    "totAmt" => (float)$saleData['final_total'],
    "prchrAcptcYn" => "N",
    "remark" => "",
    "regrId" => "Admin",
    "regrNm" => "Admin",
    "modrId" => "Admin",
    "modrNm" => "Admin",
    "saleCtyCd" => "1",
    "lpoNumber" => null,
    "currencyTyCd" => "ZMW",
    "exchangeRt" => 1,
    "destnCountryCd" => "",
    "dbtRsnCd" => "",
    "invcAdjustReason" => "",
    "itemList" => $itemList
];

$vsdcUrl = rtrim($env['ZRA_VSDC_URL'], '/') . '/trnsSales/saveSales';
$ch = curl_init($vsdcUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_TIMEOUT        => 30
]);

$response = curl_exec($ch);
$res = json_decode($response, true);
curl_close($ch);

echo "<h2>ZRA Sales Test</h2>";
if (($res['resultCd'] ?? '') === "000") {
    $data = $res['data'];
    
    // Save the ZRA signature to your database[cite: 7]
    $stmt = $pdo->prepare("UPDATE sales SET zra_rcpt_no = ?, zra_intrl_data = ?, zra_rcpt_sign = ?, zra_sdc_id = ?, zra_vsdc_rcpt_pbct_date = ?, zra_qr_code_url = ? WHERE id = ?");
    $stmt->execute([
        $data['rcptNo'], $data['intrlData'], $data['rcptSign'], $data['sdcId'], $data['vsdcRcptPbctDate'], $data['qrCodeUrl'], $saleId
    ]);

    // Increment Counter
    $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = 'vsdc_last_sale_no'")->execute([$nextInvcNo]);

    echo "<h3 style='color:green;'>✔️ Sale successfully synced to ZRA!</h3>";
    echo "<b>Receipt No:</b> " . htmlspecialchars($data['rcptNo']) . "<br>";
    echo "<b>Signature:</b> " . htmlspecialchars($data['rcptSign']) . "<br>";
    echo "<b>QR Code URL:</b> <a href='" . htmlspecialchars($data['qrCodeUrl']) . "' target='_blank'>View Receipt</a>";
} else {
    echo "<h3 style='color:red;'>⚠️ Failed to sync sale</h3>";
    echo "<b>Code:</b> " . htmlspecialchars($res['resultCd'] ?? 'N/A') . "<br>";
    echo "<b>Message:</b> " . htmlspecialchars($res['resultMsg'] ?? 'Unknown Error') . "<br>";
}
?>
