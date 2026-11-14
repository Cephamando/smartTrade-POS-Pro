<?php
declare(strict_types=1);

require_once __DIR__ . '/../app/Services/WhatsAppCloudService.php';

use SmartTrade\Services\WhatsAppCloudService;

$rcptNo    = trim((string) ($_GET['rcpt'] ?? ''));
$txRef     = trim((string) ($_GET['tx_ref'] ?? ''));
$autoPrint = !empty($_GET['print']);

$order      = null;
$errorMsg   = null;
$kvSettings = [];

try {
    $pdo        = WhatsAppCloudService::getDb();
    $kvSettings = WhatsAppCloudService::getAllDbSettings($pdo);

    if ( $rcptNo !== '' ) {
        $stmt = $pdo->prepare("SELECT * FROM st_direct_wa_orders WHERE zra_rcpt_no = :rcpt LIMIT 1");
        $stmt->execute([':rcpt' => $rcptNo]);
        $order = $stmt->fetch();
    } elseif ( $txRef !== '' ) {
        $stmt = $pdo->prepare("SELECT * FROM st_direct_wa_orders WHERE tx_ref = :txref LIMIT 1");
        $stmt->execute([':txref' => $txRef]);
        $order = $stmt->fetch();
    } else {
        $order = $pdo->query("SELECT * FROM st_direct_wa_orders WHERE payment_status = 'PAID' ORDER BY id DESC LIMIT 1")->fetch();
    }
} catch ( \Throwable $e ) {
    $errorMsg = $e->getMessage();
}

$storeName  = $kvSettings['company_name'] ?? $kvSettings['store_name'] ?? WhatsAppCloudService::env('STORE_BRAND_NAME', 'smartTrade Express Store');
$storeTpin  = $kvSettings['zra_tpin'] ?? $kvSettings['tpin'] ?? WhatsAppCloudService::env('ZRA_TPIN', '1002345678');
$storePhone = $kvSettings['company_phone'] ?? $kvSettings['phone'] ?? WhatsAppCloudService::env('MERCHANT_WHATSAPP_NUMBER', '260970000000');

$items       = [];
$subtotal    = 0.00;
$deliveryFee = 0.00;
$totalZmw    = 0.00;
$vatAmount   = 0.00;
$netExclVat  = 0.00;
$taxRegime   = 'TOT';

if ( $order ) {
    $items       = json_decode((string) ($order['items_json'] ?? '[]'), true) ?: [];
    $rawVsdc     = json_decode((string) ($order['zra_raw_response'] ?? '{}'), true) ?: [];
    $taxRegime   = strtoupper((string) ($rawVsdc['_tax_regime'] ?? 'TOT'));
    $subtotal    = (float) ($order['subtotal_zmw'] ?? 0.00);
    $deliveryFee = (float) ($order['delivery_fee_zmw'] ?? 0.00);
    $totalZmw    = (float) ($order['total_zmw'] ?? 0.00);

    if ( $taxRegime === 'VAT' ) {
        $netExclVat = round($totalZmw / 1.16, 2);
        $vatAmount  = round($totalZmw - $netExclVat, 2);
    } else {
        $netExclVat = $totalZmw;
        $vatAmount  = 0.00;
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host   = $_SERVER['HTTP_HOST'] ?? 'smartrade.odeliazm.com';
$qrTargetUrl = !empty($order['zra_qr_url'])
    ? (string) $order['zra_qr_url']
    : ($scheme . '://' . $host . '/verify_receipt.php?rcpt=' . urlencode((string) ($order['zra_rcpt_no'] ?? $rcptNo)));
$qrImageUrl  = 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($qrTargetUrl);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ZRA Smart Invoice Receipt <?= htmlspecialchars((string) ($order['zra_rcpt_no'] ?? $rcptNo)) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #eef1f5;
            font-family: 'Courier New', Courier, monospace;
        }
        .thermal-slip {
            width: 302px;
            margin: 24px auto;
            background: #fff;
            padding: 14px 12px;
            color: #000;
            box-shadow: 0 4px 14px rgba(0,0,0,0.12);
            font-size: 12px;
            line-height: 1.35;
        }
        .thermal-dashed {
            border-top: 1px dashed #000;
            margin: 8px 0;
        }
        .font-b-condensed {
            font-size: 11px;
            letter-spacing: -0.3px;
            word-break: break-all;
        }
        @media print {
            @page {
                size: 80mm auto;
                margin: 0;
            }
            body {
                background: #fff;
                margin: 0;
                padding: 0;
            }
            .no-print {
                display: none !important;
            }
            .thermal-slip {
                width: 100%;
                max-width: 78mm;
                margin: 0;
                padding: 4mm 2mm;
                box-shadow: none;
            }
        }
    </style>
</head>
<body>

<div class="container py-2 no-print text-center">
    <div class="btn-group shadow-sm mt-2">
        <button type="button" class="btn btn-sm btn-dark fw-bold" onclick="window.print()">
            🖨️ Print 80mm Thermal Slip
        </button>
        <a href="/storefront/kds.php" class="btn btn-sm btn-outline-secondary">
            📋 Back to Kitchen Queue
        </a>
        <a href="/storefront/index.php" class="btn btn-sm btn-outline-success">
            🛒 Storefront
        </a>
    </div>
</div>

<?php if ( !$order ): ?>
    <div class="container mt-4">
        <div class="alert alert-warning text-center mx-auto" style="max-width: 420px;">
            <h6 class="fw-bold mb-1">Receipt Not Found</h6>
            <p class="small mb-0">No paid WhatsApp order matched receipt reference <code><?= htmlspecialchars($rcptNo ?: $txRef) ?></code>.</p>
        </div>
    </div>
<?php else: ?>
    <div class="thermal-slip">
        <div class="text-center">
            <div class="fw-bold fs-6"><?= htmlspecialchars(strtoupper((string) $storeName)) ?></div>
            <div class="font-b-condensed">TPIN: <?= htmlspecialchars((string) $storeTpin) ?> | BRANCH #<?= (int) $order['branch_id'] ?></div>
            <div class="font-b-condensed">TEL/WA: +<?= htmlspecialchars((string) $storePhone) ?></div>
            <div class="fw-bold mt-1">*** ZRA SMART INVOICE (VSDC) ***</div>
        </div>

        <div class="thermal-dashed"></div>

        <div class="font-b-condensed">
            <div><strong>ORDER REF :</strong> <?= htmlspecialchars((string) $order['tx_ref']) ?></div>
            <div><strong>ZRA RCPT  :</strong> <?= htmlspecialchars((string) ($order['zra_rcpt_no'] ?? 'PENDING')) ?></div>
            <div><strong>DATE/TIME :</strong> <?= htmlspecialchars((string) $order['created_at']) ?></div>
            <div><strong>CUSTOMER  :</strong> <?= htmlspecialchars((string) $order['customer_name']) ?></div>
            <div><strong>MSISDN    :</strong> +<?= htmlspecialchars((string) $order['customer_phone']) ?></div>
            <div><strong>TYPE      :</strong> <?= htmlspecialchars((string) $order['delivery_type']) ?></div>
            <?php if ( !empty($order['delivery_address']) ): ?>
                <div><strong>ADDRESS   :</strong> <?= htmlspecialchars((string) $order['delivery_address']) ?></div>
            <?php endif; ?>
        </div>

        <div class="thermal-dashed"></div>

        <table class="w-100 font-b-condensed">
            <thead>
                <tr class="border-bottom border-dark">
                    <th class="text-start">ITEM / SKU</th>
                    <th class="text-center">QTY</th>
                    <th class="text-end">TOTAL</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $items as $item ): ?>
                    <tr>
                        <td class="pt-1">
                            <div class="fw-bold"><?= htmlspecialchars((string) ($item['name'] ?? 'Item')) ?></div>
                            <div class="text-muted"><?= htmlspecialchars((string) ($item['sku'] ?? '')) ?> @ K<?= number_format((float) ($item['unit_price'] ?? 0), 2) ?></div>
                        </td>
                        <td class="text-center align-top pt-1"><?= (int) ($item['qty'] ?? 1) ?></td>
                        <td class="text-end align-top pt-1 fw-bold">K<?= number_format((float) ($item['line_total'] ?? 0), 2) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="thermal-dashed"></div>

        <div class="font-b-condensed">
            <div class="d-flex justify-content-between">
                <span>SUBTOTAL:</span>
                <span>ZMW <?= number_format($subtotal, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span>DELIVERY FEE:</span>
                <span>ZMW <?= number_format($deliveryFee, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between fw-bold fs-6 mt-1">
                <span>TOTAL PAID:</span>
                <span>ZMW <?= number_format($totalZmw, 2) ?></span>
            </div>
        </div>

        <div class="thermal-dashed"></div>

        <div class="font-b-condensed">
            <?php if ( $taxRegime === 'VAT' ): ?>
                <div class="fw-bold">TAX BREAKDOWN (VAT 16% INCL.)</div>
                <div class="d-flex justify-content-between">
                    <span>TAXABLE AMOUNT (A-16%):</span>
                    <span>ZMW <?= number_format($netExclVat, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>VAT AMOUNT (16%):</span>
                    <span>ZMW <?= number_format($vatAmount, 2) ?></span>
                </div>
            <?php else: ?>
                <div class="fw-bold">TAX REGIME: TURNOVER TAX (TOT)</div>
                <div class="d-flex justify-content-between">
                    <span>TAXABLE AMOUNT (TOT):</span>
                    <span>ZMW <?= number_format($netExclVat, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>VAT AMOUNT (NON-VAT):</span>
                    <span>ZMW 0.00</span>
                </div>
            <?php endif; ?>
            <div class="d-flex justify-content-between mt-1">
                <span>PAYMENT:</span>
                <span class="fw-bold"><?= htmlspecialchars((string) $order['payment_status']) ?> (FLW #<?= htmlspecialchars((string) ($order['flw_transaction_id'] ?? 'N/A')) ?>)</span>
            </div>
        </div>

        <div class="thermal-dashed"></div>

        <div class="font-b-condensed">
            <div class="fw-bold text-center mb-1">--- SDC FISCAL INFORMATION ---</div>
            <div><strong>SDC ID      :</strong> <?= htmlspecialchars((string) ($order['zra_sdc_id'] ?? 'SDC0010000001')) ?></div>
            <div><strong>VSDC DATE   :</strong> <?= htmlspecialchars((string) ($order['zra_vsdc_date'] ?? $order['created_at'])) ?></div>
            <?php if ( !empty($order['zra_intrl_data']) ): ?>
                <div><strong>INTRL DATA  :</strong> <?= htmlspecialchars((string) $order['zra_intrl_data']) ?></div>
            <?php endif; ?>
            <?php if ( !empty($order['zra_rcpt_sign']) ): ?>
                <div><strong>RCPT SIGN   :</strong> <?= htmlspecialchars((string) $order['zra_rcpt_sign']) ?></div>
            <?php endif; ?>
        </div>

        <div class="thermal-dashed"></div>

        <div class="text-center my-2">
            <img src="<?= htmlspecialchars($qrImageUrl) ?>" alt="ZRA VSDC QR Code" width="135" height="135" class="border p-1">
            <div class="font-b-condensed mt-1 fw-bold">FISCAL STATUS: <?= htmlspecialchars((string) $order['zra_fiscal_status']) ?></div>
            <div class="font-b-condensed">Scan QR to verify ZRA Smart Invoice</div>
        </div>

        <div class="thermal-dashed"></div>

        <div class="text-center font-b-condensed">
            <div>Thank you for shopping with us!</div>
            <div>Powered by smartTrade POS Pro &bull; Odelia Enterprise</div>
        </div>
    </div>
<?php endif; ?>

<?php if ( $autoPrint && $order ): ?>
<script>
    window.addEventListener('load', function () {
        setTimeout(function () { window.print(); }, 350);
    });
</script>
<?php endif; ?>

</body>
</html>
