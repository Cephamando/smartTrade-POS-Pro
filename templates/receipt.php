<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 13px; margin: 0; padding: 10px; background: #fff; }
        .receipt-wrapper { width: 100%; max-width: 80mm; margin: 0 auto; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        td, th { vertical-align: top; padding: 2px 0; }
        .border-top { border-top: 1px dashed #000; margin-top: 5px; padding-top: 5px; }
        .border-bottom { border-bottom: 1px dashed #000; margin-bottom: 5px; padding-bottom: 5px; }
        .kitchen-title { font-size: 18px; font-weight: bold; text-align: center; border: 2px solid #000; padding: 5px; margin-bottom: 10px; }
        .kitchen-item { font-size: 16px; font-weight: bold; margin-bottom: 5px; border-bottom: 1px solid #ccc; padding: 5px 0; }
        .qty-box { display: inline-block; background: #000; color: #fff; width: 25px; text-align: center; border-radius: 3px; margin-right: 5px; }
        .cut-line { border-top: 2px dashed #000; margin: 30px 0; text-align: center; }
        .cut-icon { background: #fff; padding: 0 5px; position: relative; top: -12px; }
        .page-break { page-break-after: always; }
        @media print { .no-print { display: none; } }
        .no-print { text-align: center; margin-bottom: 10px; }
        button { padding: 8px 15px; cursor: pointer; background: #007bff; color: white; border: none; border-radius: 4px; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <div class="no-print">
        <button onclick="window.print()">🖨️ Print Now</button>
    </div>

    <div class="receipt-wrapper">
        <div class="text-center">
            <div class="fw-bold" style="font-size: 16px;">ODELIA POS</div>
            <div><?= htmlspecialchars($sale['location_name']) ?></div>
            <div>------------------------</div>
        </div>
        <div>Sale #: <strong><?= $sale['id'] ?></strong></div>
        <div>Date: <?= date('d/m/Y H:i', strtotime($sale['created_at'])) ?></div>
        <div class="border-bottom">Server: <?= htmlspecialchars($sale['username']) ?></div>

        <table>
            <?php foreach ($items as $item): ?>
            <tr>
                <td width="10%"><?= $item['quantity'] ?></td>
                <td width="60%"><?= htmlspecialchars($item['name']) ?></td>
                <td width="30%" class="text-end"><?= number_format($item['price_at_sale'] * $item['quantity'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>

        <div class="border-top fw-bold">
            <table>
                <tr><td>TOTAL</td><td class="text-end">ZMW <?= number_format($sale['final_total'], 2) ?></td></tr>
            </table>
        </div>
        <div class="text-center" style="margin-top: 15px;">Thank you!</div>
    </div>

    <div class="cut-line"><span class="cut-icon">✂</span></div>
    <!-- <div class="page-break"></div> -->

    <div class="receipt-wrapper">
        <div class="kitchen-title">KITCHEN TICKET</div>
        <div style="font-size: 12px; margin-bottom: 5px;">
            Order #: <strong><?= $sale['id'] ?></strong> | <?= date('H:i') ?><br>
            Waiter: <?= htmlspecialchars($sale['username']) ?>
        </div>
        <div style="border-top: 2px solid #000; margin-bottom: 10px;"></div>
        <?php foreach ($kitchenItems as $item): ?>
        <div class="kitchen-item">
            <span class="qty-box"><?= $item['quantity'] ?></span><?= htmlspecialchars($item['name']) ?>
        </div>
        <?php endforeach; ?>
        <div style="border-top: 2px solid #000; margin-top: 15px; text-align: center;">END OF ORDER</div>
    </div>
    
    </body>
</html>