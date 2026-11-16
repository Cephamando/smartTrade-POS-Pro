<?php
declare(strict_types=1);

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

require_once __DIR__ . '/../../app/Services/WhatsAppCloudService.php';

use SmartTrade\Services\WhatsAppCloudService;

function renderGeneric404(): never
{
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>404 Not Found</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f8fafc; color: #334155; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .box { text-align: center; max-width: 360px; padding: 24px; }
            h1 { font-size: 1.25rem; margin-bottom: 8px; color: #0f172a; }
            p { font-size: 0.9rem; color: #64748b; margin: 0; }
        </style>
    </head>
    <body>
        <div class="box">
            <h1>404 — Page Not Found</h1>
            <p>The requested receipt link is invalid or no longer available.</p>
        </div>
    </body>
    </html>
    <?php
    exit;
}

$clientIp = $_SERVER['HTTP_CF_CONNECTING_IP']
    ?? $_SERVER['HTTP_X_FORWARDED_FOR']
    ?? $_SERVER['REMOTE_ADDR']
    ?? '0.0.0.0';
$clientIp = trim(explode(',', (string) $clientIp)[0]);

$rlDir = sys_get_temp_dir() . '/st_rcpt_rl';
if ( !is_dir($rlDir) ) {
    @mkdir($rlDir, 0700, true);
}
$rlFile = $rlDir . '/' . hash('sha256', $clientIp) . '.json';
$nowTs  = time();
$window = 60;
$maxReq = 30;

$hits = [];
if ( is_file($rlFile) ) {
    $rawHits = @ json_decode((string) @file_get_contents($rlFile), true);
    if ( is_array($rawHits) ) {
        foreach ( $rawHits as $ts ) {
            if ( is_int($ts) && ($nowTs - $ts) < $window ) {
                $hits[] = $ts;
            }
        }
    }
}
if ( count($hits) >= $maxReq ) {
    http_response_code(429);
    header('Retry-After: 60');
    echo 'Too Many Requests. Please wait a moment and try again.';
    exit;
}
$hits[] = $nowTs;
@file_put_contents($rlFile, json_encode($hits), LOCK_EX);

$token = trim((string) ($_GET['t'] ?? ''));
if ( $token === '' ) {
    $reqPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
    if ( preg_match('#/r/([A-Za-z0-9_-]+)#', $reqPath, $m) ) {
        $token = $m[1];
    }
}

if ( !preg_match('/^[A-Za-z0-9_-]{22,64}$/', $token) ) {
    renderGeneric404();
}

$order        = null;
$kvSettings   = [];
$ttlDays      = 0;
$businessName = 'Queen Bee';

try {
    $pdo        = WhatsAppCloudService::getDb();
    $kvSettings = WhatsAppCloudService::getAllDbSettings($pdo);
    $ttlDays    = WhatsAppCloudService::getReceiptTokenTtlDays($pdo);

    $stmt = $pdo->prepare("
        SELECT *
        FROM st_direct_wa_orders
        WHERE receipt_token = :tok
          AND payment_status = 'PAID'
        LIMIT 1
    ");
    $stmt->execute([':tok' => $token]);
    $order = $stmt->fetch();

    if ( is_array($order) ) {
        $businessName = WhatsAppCloudService::resolveStoreBrandName($pdo, (int) ($order['branch_id'] ?? 1));
    }
} catch ( \Throwable $e ) {
    renderGeneric404();
}

if ( !is_array($order) || empty($order['tx_ref']) ) {
    renderGeneric404();
}

if ( $ttlDays > 0 && !empty($order['created_at']) ) {
    $createdTs = strtotime((string) $order['created_at']);
    if ( $createdTs !== false && ($nowTs - $createdTs) > ($ttlDays * 86400) ) {
        renderGeneric404();
    }
}

$storeTpin = trim((string) ($kvSettings['zra_tpin'] ?? $kvSettings['tpin'] ?? ''));
if ( $storeTpin === '' ) {
    $storeTpin = WhatsAppCloudService::env('ZRA_TPIN', '1004784338');
}

$txRef           = (string) $order['tx_ref'];
$subtotal        = (float) ($order['subtotal_zmw'] ?? 0.00);
$deliveryFee     = (float) ($order['delivery_fee_zmw'] ?? 0.00);
$totalZmw        = (float) ($order['total_zmw'] ?? 0.00);
$amountFormatted = 'ZMW ' . number_format($totalZmw, 2);

$items     = json_decode((string) ($order['items_json'] ?? '[]'), true) ?: [];
$rawVsdc   = json_decode((string) ($order['zra_raw_response'] ?? '{}'), true) ?: [];
$taxRegime = strtoupper((string) ($rawVsdc['_tax_regime'] ?? 'TOT'));

if ( $taxRegime === 'VAT' ) {
    $netExclVat = round($totalZmw / 1.16, 2);
    $vatAmount  = round($totalZmw - $netExclVat, 2);
} else {
    $netExclVat = $totalZmw;
    $vatAmount  = 0.00;
}

$rawNameParts = preg_split('/\s+/', trim((string) ($order['customer_name'] ?? 'Customer'))) ?: ['Customer'];
$maskedBuyer  = $rawNameParts[0];
if ( count($rawNameParts) > 1 ) {
    $maskedBuyer .= ' ' . strtoupper(substr((string) end($rawNameParts), 0, 1)) . '.';
}

$baseUrl      = rtrim(WhatsAppCloudService::env('APP_URL', 'https://smartrade.odeliazm.com'), '/');
$canonicalUrl = $baseUrl . '/r/' . rawurlencode($token);
$ogLogoUrl    = $baseUrl . '/assets/receipt-og-logo.png';

$zraVerifyUrl = !empty($order['zra_qr_url'])
    ? (string) $order['zra_qr_url']
    : $canonicalUrl;
$qrImageUrl   = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . rawurlencode($zraVerifyUrl);

$ogTitle       = 'Receipt from ' . $businessName;
$ogDescription = 'Payment of ' . $amountFormatted . ' received. Ref ' . $txRef . '.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= htmlspecialchars($ogTitle) ?> — <?= htmlspecialchars($txRef) ?></title>

    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($ogLogoUrl) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:type" content="website">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($ogDescription) ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($ogLogoUrl) ?>">

    <style>
        :root {
            --brand-green: #075E54;
            --brand-teal: #128C7E;
            --bg-light: #f1f5f9;
            --text-dark: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 16px 12px 40px;
            background-color: var(--bg-light);
            color: var(--text-dark);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            -webkit-font-smoothing: antialiased;
        }
        .action-bar {
            max-width: 420px;
            margin: 0 auto 14px;
            display: flex;
            gap: 8px;
        }
        .btn-action {
            flex: 1;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 11px 14px;
            border-radius: 10px;
            font-size: 0.88rem;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: 1px solid transparent;
            transition: opacity 0.15s ease;
        }
        .btn-primary-pdf {
            background-color: var(--brand-green);
            color: #ffffff;
        }
        .btn-secondary-print {
            background-color: #ffffff;
            color: var(--text-dark);
            border-color: #cbd5e1;
        }
        .receipt-card {
            max-width: 420px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(15, 23, 42, 0.08);
            overflow: hidden;
        }
        .receipt-banner {
            background: linear-gradient(135deg, var(--brand-green), var(--brand-teal));
            color: #ffffff;
            padding: 20px 18px;
            text-align: center;
        }
        .paid-pill {
            display: inline-block;
            background: rgba(255, 255, 255, 0.2);
            color: #ffffff;
            font-size: 0.72rem;
            font-weight: 700;
            letter-spacing: 0.6px;
            padding: 3px 10px;
            border-radius: 999px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .store-title {
            font-size: 1.2rem;
            font-weight: 700;
            margin: 0 0 2px;
        }
        .store-sub {
            font-size: 0.78rem;
            opacity: 0.88;
            margin: 0;
        }
        .receipt-body {
            padding: 18px;
        }
        .amount-hero {
            text-align: center;
            padding-bottom: 14px;
            border-bottom: 1px dashed var(--border-color);
            margin-bottom: 14px;
        }
        .amount-label {
            font-size: 0.78rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .amount-value {
            font-size: 1.75rem;
            font-weight: 800;
            color: var(--brand-green);
            margin: 2px 0 0;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px 12px;
            font-size: 0.82rem;
            padding-bottom: 14px;
            border-bottom: 1px dashed var(--border-color);
            margin-bottom: 14px;
        }
        .meta-item span {
            display: block;
            color: var(--text-muted);
            font-size: 0.73rem;
        }
        .meta-item strong {
            color: var(--text-dark);
            word-break: break-all;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.85rem;
            margin-bottom: 14px;
        }
        .items-table th {
            text-align: left;
            font-size: 0.73rem;
            text-transform: uppercase;
            color: var(--text-muted);
            padding-bottom: 6px;
            border-bottom: 1px solid var(--border-color);
        }
        .items-table td {
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            vertical-align: top;
        }
        .item-qty {
            text-align: center;
            width: 48px;
            font-weight: 600;
        }
        .item-total {
            text-align: right;
            width: 90px;
            font-weight: 600;
        }
        .totals-block {
            font-size: 0.84rem;
            padding-bottom: 14px;
            border-bottom: 1px dashed var(--border-color);
            margin-bottom: 14px;
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 4px;
        }
        .totals-row.grand {
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-top: 6px;
            padding-top: 6px;
            border-top: 1px solid var(--border-color);
        }
        .zra-box {
            background: #f8fafc;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 12px;
            font-family: "Courier New", Courier, monospace;
            font-size: 0.75rem;
            line-height: 1.45;
            margin-bottom: 12px;
        }
        .zra-box-title {
            font-weight: 700;
            text-align: center;
            margin-bottom: 6px;
            color: var(--text-dark);
        }
        .qr-wrap {
            text-align: center;
            padding-top: 6px;
        }
        .qr-wrap img {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 4px;
            background: #fff;
        }
        .qr-caption {
            font-size: 0.74rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        .receipt-footer {
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted);
            padding-top: 8px;
        }
        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { background: #fff; padding: 0; }
            .no-print { display: none !important; }
            .receipt-card {
                max-width: 78mm;
                box-shadow: none;
                border-radius: 0;
            }
            .receipt-banner {
                background: #fff !important;
                color: #000 !important;
                padding: 8px 4px;
                border-bottom: 1px dashed #000;
            }
            .paid-pill { border: 1px solid #000; color: #000; }
        }
    </style>
</head>
<body>

<div class="action-bar no-print">
    <button type="button" class="btn-action btn-primary-pdf" id="downloadPdfBtn" onclick="downloadReceiptPdf()">
        Download PDF
    </button>
    <button type="button" class="btn-action btn-secondary-print" onclick="window.print()">
        Print Receipt
    </button>
</div>

<div class="receipt-card" id="receiptPrintable">
    <div class="receipt-banner">
        <div class="paid-pill">Payment Confirmed</div>
        <h1 class="store-title"><?= htmlspecialchars($businessName) ?></h1>
        <p class="store-sub">TPIN: <?= htmlspecialchars($storeTpin) ?> &bull; ZRA Smart Invoice</p>
    </div>

    <div class="receipt-body">
        <div class="amount-hero">
            <div class="amount-label">Total Paid</div>
            <div class="amount-value"><?= htmlspecialchars($amountFormatted) ?></div>
        </div>

        <div class="meta-grid">
            <div class="meta-item">
                <span>Reference</span>
                <strong><?= htmlspecialchars($txRef) ?></strong>
            </div>
            <div class="meta-item">
                <span>Date &amp; Time</span>
                <strong><?= htmlspecialchars((string) $order['created_at']) ?></strong>
            </div>
            <div class="meta-item">
                <span>Customer</span>
                <strong><?= htmlspecialchars($maskedBuyer) ?></strong>
            </div>
            <div class="meta-item">
                <span>Fulfillment</span>
                <strong><?= htmlspecialchars((string) $order['delivery_type']) ?></strong>
            </div>
        </div>

        <table class="items-table">
            <thead>
                <tr>
                    <th>Description</th>
                    <th class="item-qty">Qty</th>
                    <th class="item-total">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $items as $item ): ?>
                    <tr>
                        <td>
                            <div style="font-weight: 600;"><?= htmlspecialchars((string) ($item['name'] ?? 'Item')) ?></div>
                            <div style="font-size: 0.74rem; color: #64748b;">@ ZMW <?= number_format((float) ($item['unit_price'] ?? 0), 2) ?></div>
                        </td>
                        <td class="item-qty"><?= (int) ($item['qty'] ?? 1) ?></td>
                        <td class="item-total">ZMW <?= number_format((float) ($item['line_total'] ?? 0), 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totals-block">
            <div class="totals-row">
                <span>Subtotal</span>
                <span>ZMW <?= number_format($subtotal, 2) ?></span>
            </div>
            <?php if ( $deliveryFee > 0 ): ?>
                <div class="totals-row">
                    <span>Delivery Fee</span>
                    <span>ZMW <?= number_format($deliveryFee, 2) ?></span>
                </div>
            <?php endif; ?>
            <?php if ( $taxRegime === 'VAT' ): ?>
                <div class="totals-row" style="color: #64748b; font-size: 0.78rem;">
                    <span>Taxable (16% VAT Incl.)</span>
                    <span>ZMW <?= number_format($netExclVat, 2) ?></span>
                </div>
                <div class="totals-row" style="color: #64748b; font-size: 0.78rem;">
                    <span>VAT Amount (16%)</span>
                    <span>ZMW <?= number_format($vatAmount, 2) ?></span>
                </div>
            <?php else: ?>
                <div class="totals-row" style="color: #64748b; font-size: 0.78rem;">
                    <span>Tax Regime</span>
                    <span>Turnover Tax (TOT)</span>
                </div>
            <?php endif; ?>
            <div class="totals-row grand">
                <span>Total Paid</span>
                <span><?= htmlspecialchars($amountFormatted) ?></span>
            </div>
        </div>

        <div class="zra-box">
            <div class="zra-box-title">ZRA FISCAL VERIFICATION</div>
            <div><strong>SDC ID     :</strong> <?= htmlspecialchars((string) ($order['zra_sdc_id'] ?? 'SDC0010000001')) ?></div>
            <div><strong>INVOICE NO :</strong> <?= htmlspecialchars((string) ($order['zra_rcpt_no'] ?? 'VERIFIED')) ?></div>
            <?php if ( !empty($order['zra_rcpt_sign']) ): ?>
                <div><strong>RCPT SIGN  :</strong> <?= htmlspecialchars((string) $order['zra_rcpt_sign']) ?></div>
            <?php endif; ?>
            <?php if ( !empty($order['zra_intrl_data']) ): ?>
                <div><strong>INTRL DATA :</strong> <?= htmlspecialchars((string) $order['zra_intrl_data']) ?></div>
            <?php endif; ?>
        </div>

        <div class="qr-wrap">
            <img src="<?= htmlspecialchars($qrImageUrl) ?>" alt="ZRA Verification QR" width="136" height="136" crossorigin="anonymous">
            <div class="qr-caption">Scan QR to verify on ZRA Portal</div>
        </div>

        <div class="receipt-footer">
            Thank you for your business!
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
<script>
function downloadReceiptPdf() {
    const btn = document.getElementById('downloadPdfBtn');
    const element = document.getElementById('receiptPrintable');
    const filename = <?= json_encode('Receipt-' . $txRef . '.pdf') ?>;

    if (typeof html2pdf === 'undefined') {
        window.print();
        return;
    }

    const origText = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'Generating PDF...';

    const opt = {
        margin: [6, 6, 6, 6],
        filename: filename,
        image: { type: 'jpeg', quality: 0.98 },
        html2canvas: { scale: 2, useCORS: true, logging: false },
        jsPDF: { unit: 'mm', format: [105, 210], orientation: 'portrait' }
    };

    html2pdf().set(opt).from(element).save().then(function () {
        btn.disabled = false;
        btn.textContent = origText;
    }).catch(function () {
        btn.disabled = false;
        btn.textContent = origText;
        window.print();
    });
}
</script>
</body>
</html>
