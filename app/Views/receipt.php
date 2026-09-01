<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (empty($sale) || !isset($items)) {
    die("Sale not found.");
}

$displayTotal = 0;
foreach($items as $i) { $displayTotal += ($i['price'] * $i['quantity']); }
$previousBalance = $sale['final_total'] - $displayTotal;
$tipAmt = (float)($sale['tip_amount'] ?? 0);
$calcDisc = ($displayTotal + $tipAmt) - (float)$sale['final_total'];
if ($calcDisc < 0.01) {
    $calcDisc = 0;
}
$vatSplit = [
    'excl' => 0.0,
    'vat' => 0.0,
    'zero' => 0.0,
    'exempt' => 0.0,
];
$itemIncl = max(0.0, (float)$displayTotal);
$scale = ($calcDisc > 0.01 && $itemIncl > 0) ? max(0.0, ($itemIncl - $calcDisc) / $itemIncl) : 1.0;
$stdIncl = 0.0;
foreach ($items as $i) {
    $incl = ((float)$i['price'] * (float)$i['quantity']) * $scale;
    $cls = strtoupper((string)($i['tax_class'] ?? 'A'));
    if ($cls === 'D' || $cls === '' || $cls === 'A') {
        $stdIncl += $incl;
    } elseif ($cls === 'B' || $cls === 'C3') {
        $vatSplit['zero'] += $incl;
    } else {
        $vatSplit['exempt'] += $incl;
    }
}
$stdIncl += $tipAmt;
$vatSplit['vat'] = round($stdIncl - ($stdIncl / 1.16), 2);
$vatSplit['excl'] = round($stdIncl - $vatSplit['vat'] + $vatSplit['zero'] + $vatSplit['exempt'], 2);
$vatSplit['zero'] = round($vatSplit['zero'], 2);
$vatSplit['exempt'] = round($vatSplit['exempt'], 2);
$splitLegs = saleSplitLegs($sale);

// --- FINAL PERFECTED PRINTING LOGIC ---
if ($isIncremental) {
    $copies = ['BARMAN SLIP'];
    $subType = 'ORDER SLIP (UNPAID)';
} elseif ($isBill) {
    $copies = ['CLIENT PROFORMA'];
    $subType = 'PROFORMA BILL (UNPAID)';
} else {
    $copies = ['OFFICIAL RECEIPT'];
    $subType = 'FINAL BILL';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= $isBill ? 'Bill' : 'Receipt' ?> #<?= $saleId ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 14px; width: 300px; margin: 0 auto; padding: 10px; color: #000; font-weight: bold; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: 900; }
        .border-bottom { border-bottom: 2px dashed #000; padding-bottom: 5px; margin-bottom: 5px; }
        .border-top { border-top: 2px dashed #000; padding-top: 5px; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; margin-bottom: 5px; }
        th, td { text-align: left; padding: 4px 0; vertical-align: top; font-weight: bold; }
        .amount { text-align: right; }
        .cut-line { border-top: 2px dashed #000; margin: 25px 0; position: relative; text-align: center; }
        .cut-line span { background: #fff; padding: 0 10px; position: relative; top: -10px; font-size: 12px; color: #000; font-weight: bold; }
        .zra-qr { margin: 8px auto 0; text-align: center; }
        .zra-qr img {
            display: block;
            margin: 0 auto;
            image-rendering: pixelated;
            image-rendering: crisp-edges;
        }
        @media print {
            body { font-weight: bold !important; color: #000 !important; }
            * { font-weight: bold !important; color: #000 !important; }
            .zra-qr, .zra-qr img {
                color: unset !important;
                font-weight: normal !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }
        }
    </style>
</head>
<body>
    
    <?php foreach($copies as $index => $copyName): ?>
        
        <?php if($index > 0): ?>
            <div class="cut-line"><span>✂️ CUT HERE ✂️</span></div>
        <?php endif; ?>

        <?php if ($copyName === 'BARMAN SLIP'): ?>
            <div class="receipt-section text-center">
                <h2 style="margin:0; font-weight: 900; font-size: 18px;"><?= htmlspecialchars($bizName) ?></h2>
                <div style="font-size: 14px; font-weight: 900; margin-top: 5px; border: 2px solid #000; display: inline-block; padding: 2px 8px;"><?= $copyName ?></div>
                
                <div style="font-size: 60px; font-weight: 900; border-top: 3px solid #000; border-bottom: 3px solid #000; margin: 15px 0; padding: 5px 0; line-height: 1;">
                    #<?= $sale['id'] ?>
                </div>
                
                <p style="font-size: 16px; margin: 5px 0; text-transform: uppercase;">
                    <strong><?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></strong>
                </p>
                
                <table style="width: 90%; margin: 15px auto; border: none;">
                    <?php foreach($items as $i): ?>
                    <tr>
                        <td style="text-align: right; width: 25%; padding-right: 15px; font-size: 16px;"><?= $i['quantity'] ?>x</td>
                        <td style="text-align: left; font-size: 16px;"><?= htmlspecialchars($i['name']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                
                <p style="font-size: 12px; margin-top: 25px;">
                    <?= date('d M Y h:i A', strtotime($sale['created_at'])) ?><br>
                    <?= htmlspecialchars($sale['username']) ?>
                </p>
            </div>

        <?php else: ?>
            <div class="receipt-section">
                <div class="text-center border-bottom">
                    <h2 style="margin:0; font-weight: 900; font-size: 20px;"><?= htmlspecialchars($bizName) ?></h2>
                    
                    <?php if (!empty($receiptHeader)): ?>
                    <div style="font-size: 13px; margin: 5px 0;">
                        <?= nl2br(htmlspecialchars($receiptHeader)) ?>
                    </div>
                    <?php endif; ?>
                    <?php if ($zraTpin !== '' && !str_contains((string)$receiptHeader, $zraTpin)): ?>
                    <div style="font-size: 13px;">TPIN: <?= htmlspecialchars($zraTpin) ?></div>
                    <?php endif; ?>
                    
                    <div style="font-size: 13px; margin: 5px 0;">
                        <?= htmlspecialchars($sale['loc_name'] ?? '') ?>
                    </div>

                    <p style="margin:5px 0; font-size: 13px;">
                        <span style="font-size: 16px; font-weight: 900; border: 2px solid #000; padding: 3px 6px; display: inline-block; margin-bottom: 5px;">
                            <?= $copyName ?>
                        </span><br>
                        <span style="font-size: 14px; font-weight: bold; margin-bottom: 5px; display: inline-block;">
                            <?= $subType ?>
                        </span><br>
                        Order #<?= $sale['id'] ?><br>
                        <?= date('d M Y h:i A', strtotime($sale['created_at'])) ?>
                    </p>
                </div>
                
                <p style="font-size: 13px; margin: 5px 0;">
                    Cashier: <?= htmlspecialchars($sale['username']) ?><br>
                    Customer: <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?>
                </p>
                
                <table class="border-bottom">
                    <thead>
                        <tr><th><?= $isIncremental ? 'NEW ITEMS' : 'Item' ?></th><th style="text-align:center">Qty</th><th class="amount">Total</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $i): ?>
                        <tr>
                            <td><?= htmlspecialchars($i['name']) ?></td>
                            <td style="text-align:center"><?= $i['quantity'] ?></td>
                            <td class="amount"><?= number_format($i['price'] * $i['quantity'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <table class="border-bottom">
                    <?php if($isIncremental): ?>
                        <tr><td>Added Now:</td><td class="amount"><?= number_format($displayTotal, 2) ?></td></tr>
                        <tr><td>Prev Balance:</td><td class="amount"><?= number_format($previousBalance, 2) ?></td></tr>
                        <tr class="fw-bold" style="font-size: 17px; border-top: 1px dashed #000;">
                            <td>NEW BALANCE:</td><td class="amount">ZMW <?= number_format($sale['final_total'], 2) ?></td>
                        </tr>
                    <?php else: ?>
                        <tr><td>Amount excl. VAT:</td><td class="amount"><?= number_format($vatSplit['excl'], 2) ?></td></tr>
                        <tr><td>VAT 16%:</td><td class="amount"><?= number_format($vatSplit['vat'], 2) ?></td></tr>
                        <?php if ($vatSplit['zero'] > 0.004): ?>
                        <tr><td class="small">Incl. zero-rated:</td><td class="amount"><?= number_format($vatSplit['zero'], 2) ?></td></tr>
                        <?php endif; ?>
                        <?php if ($vatSplit['exempt'] > 0.004): ?>
                        <tr><td class="small">Incl. exempt:</td><td class="amount"><?= number_format($vatSplit['exempt'], 2) ?></td></tr>
                        <?php endif; ?>
                        <?php if($tipAmt > 0): ?>
                        <tr><td>Incl. tip/service:</td><td class="amount"><?= number_format($tipAmt, 2) ?></td></tr>
                        <?php endif; ?>
                        <?php if($calcDisc > 0.01): ?>
                        <tr><td>Incl. discount:</td><td class="amount">-ZMW <?= number_format($calcDisc, 2) ?></td></tr>
                        <?php endif; ?>
                        <tr class="fw-bold" style="font-size: 17px;">
                            <td><?= $isBill ? 'TAB BALANCE' : 'TOTAL PAID' ?>:</td><td class="amount">ZMW <?= number_format($sale['final_total'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
                
                <?php if(!$isBill): ?>
                <table>
                    <?php if($sale['payment_method'] === 'Split' || $splitLegs): ?>
                    <tr><td colspan="2" class="fw-bold">Paid Via Split:</td></tr>
                    <?php foreach ($splitLegs as $leg): ?>
                    <tr><td>- <?= htmlspecialchars($leg['method']) ?></td><td class="amount fw-bold">ZMW <?= number_format($leg['amount'], 2) ?></td></tr>
                    <?php endforeach; ?>
                    <?php else: ?>
                    <tr><td>Paid Via:</td><td class="amount fw-bold"><?= htmlspecialchars($sale['payment_method']) ?></td></tr>
                    <?php endif; ?>
                    <?php if(isset($sale['amount_tendered']) && $sale['amount_tendered'] > 0 && (stripos($sale['payment_method'], 'cash') !== false || $sale['payment_method'] === 'Split')): ?>
                    <tr><td>Tendered:</td><td class="amount">ZMW <?= number_format($sale['amount_tendered'], 2) ?></td></tr>
                    <tr><td>Change:</td><td class="amount fw-bold">ZMW <?= number_format($sale['change_due'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if($sale['payment_status'] === 'pending' && $sale['amount_tendered'] > 0): ?>
                    <tr><td>Paid So Far:</td><td class="amount">ZMW <?= number_format($sale['amount_tendered'], 2) ?></td></tr>
                    <tr class="fw-bold" style="font-size:16px;"><td>BALANCE DUE:</td><td class="amount">ZMW <?= number_format($sale['final_total'] - $sale['amount_tendered'], 2) ?></td></tr>
                    <?php endif; ?>
                    <tr><td>Status:</td><td class="amount"><?= strtoupper($sale['payment_status']) ?></td></tr>
                </table>
                <?php else: ?>
                <div class="text-center fw-bold" style="font-size: 16px; margin-top: 10px; padding: 5px; border: 2px solid #000;">
                    <?= $isIncremental ? 'ITEMS ADDED TO TAB' : 'PLEASE PAY THIS AMOUNT' ?>
                </div>
                <?php endif; ?>
                
                <?php if (!$isBill && !empty($sale['zra_rcpt_sign'])): ?>
                <div class="text-center border-top" style="margin-top: 12px; font-size: 12px;">
                    <p style="margin: 6px 0; font-weight: 900;">ZRA SMART INVOICE</p>
                    <div>SDC: <?= htmlspecialchars($sale['zra_sdc_id'] ?? '') ?></div>
                    <?php if (!empty($sale['zra_mrc_no'])): ?>
                    <div>MRC: <?= htmlspecialchars($sale['zra_mrc_no']) ?></div>
                    <?php endif; ?>
                    <div>Rcpt No: <?= htmlspecialchars((string)($sale['zra_rcpt_no'] ?? '')) ?></div>
                    <?php if (!empty($sale['zra_vsdc_rcpt_pbct_date'])): ?>
                    <div><?= htmlspecialchars($sale['zra_vsdc_rcpt_pbct_date']) ?></div>
                    <?php endif; ?>
                    <div style="word-break: break-all; margin-top: 6px; font-size: 11px;">
                        <?= htmlspecialchars($sale['zra_rcpt_sign']) ?>
                    </div>
                    <?php
                    $zraQrUrl = $zraClient->receiptQrUrl(
                        (string)$sale['zra_rcpt_sign'],
                        $sale['zra_qr_code_url'] ?? null
                    );
                    ?>
                    <?php if (!empty($zraQrUrl)): ?>
                    <div class="zra-qr" data-payload="<?= htmlspecialchars($zraQrUrl, ENT_QUOTES) ?>"></div>
                    <?php endif; ?>
                </div>
                <?php elseif (!$isBill && empty($sale['zra_rcpt_sign']) && ($sale['payment_status'] ?? '') === 'paid'): ?>
                <div class="text-center border-top" style="margin-top: 12px; font-size: 12px;">
                    ZRA: pending sync
                    <?php if (!empty($sale['zra_last_error'])): ?>
                    <div style="margin-top: 4px; font-size: 11px;"><?= htmlspecialchars($sale['zra_last_error']) ?></div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <div class="text-center border-top" style="margin-top: 15px; font-size: 13px;">
                    <p><?= nl2br(htmlspecialchars($receiptFooter)) ?></p>
                </div>
            </div>
        <?php endif; ?>

    <?php endforeach; ?>

    <script src="js/qrcode.min.js"></script>
    <script>
        (function () {
            if (typeof qrcode !== 'function') {
                return;
            }
            document.querySelectorAll('.zra-qr[data-payload]').forEach(function (el) {
                var data = el.getAttribute('data-payload');
                if (!data) {
                    return;
                }
                var qr = qrcode(0, 'M');
                qr.addData(data, 'Byte');
                qr.make();
                el.innerHTML = qr.createImgTag(4, 4);
            });
        })();
        window.onload = function() { setTimeout(function() { window.print(); }, 500); };
    </script>
</body>
</html>
