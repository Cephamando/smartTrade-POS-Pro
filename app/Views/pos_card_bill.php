<?php
/** @var list<array{name:string,qty:float,total:float}> $lines */
/** @var float $sum */
/** @var float $due */
/** @var string $bizName */
/** @var string $logoUrl */
/** @var string $receiptHeader */
/** @var string $receiptFooter */
/** @var string $cashier */
/** @var string $customer */
/** @var string $location */
/** @var bool $training */
$adjust = round($due - $sum, 2);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bill</title>
    <link rel="stylesheet" href="css/thermal-slip.css?v=2">
    <style>
        .text-center { text-align: center; }
        .border-bottom { border-bottom: 1px dashed #000; padding-bottom: 4px; margin-bottom: 4px; }
        .border-top { border-top: 1px dashed #000; padding-top: 4px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        th, td { text-align: left; padding: 1px 0; vertical-align: top; }
        .amount { text-align: right; white-space: nowrap; }
    </style>
</head>
<body>
    <div class="text-center border-bottom">
        <?php if ($logoUrl !== ''): ?><img src="<?= htmlspecialchars($logoUrl) ?>" alt="" style="max-height:52px;max-width:160px;object-fit:contain;margin-bottom:4px;"><?php endif; ?>
        <h2 style="margin:0; font-size: 16px; font-weight: 700;"><?= htmlspecialchars($bizName) ?></h2>
        <?php if ($receiptHeader !== ''): ?>
        <div style="font-size: 13px; margin: 5px 0;"><?= nl2br(htmlspecialchars($receiptHeader)) ?></div>
        <?php endif; ?>
        <?php if ($location !== ''): ?>
        <div style="font-size: 13px;"><?= htmlspecialchars($location) ?></div>
        <?php endif; ?>
        <?php if ($training): ?>
        <div style="border: 2px solid #000; margin: 8px 0; padding: 4px;">TRAINING SAMPLE<br>Not filed with ZRA</div>
        <?php endif; ?>
        <p style="margin:5px 0;">
            <span style="font-size: 16px; border: 2px solid #000; padding: 3px 6px; display: inline-block;">CLIENT PROFORMA</span><br>
            <span style="font-size: 14px;">PROFORMA BILL (UNPAID)</span><br>
            <?= date('d M Y h:i A') ?>
        </p>
    </div>
    <p style="font-size: 13px; margin: 5px 0;">
        <?php if ($cashier !== ''): ?>Cashier: <?= htmlspecialchars($cashier) ?><br><?php endif; ?>
        Customer: <?= htmlspecialchars($customer !== '' ? $customer : 'Walk-in') ?>
    </p>
    <table class="border-bottom">
        <thead>
            <tr><th>Item</th><th style="text-align:center">Qty</th><th class="amount">Total</th></tr>
        </thead>
        <tbody>
        <?php foreach ($lines as $line): ?>
            <tr>
                <td><?= htmlspecialchars($line['name']) ?></td>
                <td style="text-align:center"><?= htmlspecialchars(rtrim(rtrim(number_format($line['qty'], 2, '.', ''), '0'), '.')) ?></td>
                <td class="amount"><?= number_format($line['total'], 2) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if (abs($adjust) > 0.009): ?>
    <table>
        <tr><td>Items:</td><td class="amount"><?= number_format($sum, 2) ?></td></tr>
        <tr><td>Tip / discount:</td><td class="amount"><?= number_format($adjust, 2) ?></td></tr>
    </table>
    <?php endif; ?>
    <table>
        <tr style="font-size: 17px;">
            <td>AMOUNT DUE:</td><td class="amount">ZMW <?= number_format($due, 2) ?></td>
        </tr>
    </table>
    <div class="thank-you">Thank you</div>
    <?php if ($receiptFooter !== ''): ?>
    <div class="text-center border-top" style="margin-top: 15px; font-size: 13px;"><?= nl2br(htmlspecialchars($receiptFooter)) ?></div>
    <?php endif; ?>
    <script src="js/thermal-slip.js?v=2"></script>
    <script>
        window.onload = function () {
            setTimeout(function () {
                if (window.printThermalSlip) window.printThermalSlip();
                else window.print();
            }, 200);
        };
    </script>
</body>
</html>
