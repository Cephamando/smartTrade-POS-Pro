<!DOCTYPE html>
<html>
<head>
    <title>Receipt #<?= $sale['id'] ?></title>
    <style>
        body { font-family: 'Courier New', monospace; width: 300px; margin: 0 auto; padding: 10px; background: #fff; }
        .text-center { text-align: center; }
        .bold { font-weight: bold; }
        .line { border-bottom: 1px dashed #000; margin: 10px 0; }
        .item-row { display: flex; justify-content: space-between; }
        .total-row { display: flex; justify-content: space-between; font-weight: bold; font-size: 1.2em; margin-top: 10px; }
        .cut-line { border-bottom: 2px dotted #000; margin: 30px 0; position: relative; text-align: center; height: 10px; }
        .cut-line span { background: #fff; padding: 0 10px; position: relative; top: -10px; font-size: 20px; }
        .no-print { margin-top: 20px; text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

    <?php 
    $copies = (isset($_GET['mode']) && $_GET['mode'] === 'double') ? ['CLIENT COPY', 'KITCHEN/STORE COPY'] : ['CUSTOMER RECEIPT'];
    foreach ($copies as $index => $label): 
    ?>

        <?php if ($index > 0): ?><div class="cut-line"><span>✂</span></div><?php endif; ?>
        <div class="text-center bold">*** <?= $label ?> ***</div>
        <div class="text-center">
            <h3 style="margin:0;"><?= htmlspecialchars($sale['location_name'] ?? 'Store') ?></h3>
            <div>Date: <?= date('d/m/y H:i', strtotime($sale['created_at'])) ?></div>
            <div>Receipt #: <?= $sale['id'] ?></div>
            <div>Cashier: <?= htmlspecialchars($sale['cashier_name'] ?? 'Staff') ?></div>
        </div>
        <div class="line"></div>

        <?php foreach ($lineItems as $item): ?>
        <div class="item-row">
            <span><?= $item['quantity'] ?> x <?= htmlspecialchars($item['name']) ?></span>
            <span><?= number_format($item['price_at_sale'] * $item['quantity'], 2) ?></span>
        </div>
        <?php endforeach; ?>

        <div class="line"></div>

        <?php if ($sale['points_redeemed'] > 0): ?>
            <div class="item-row"><span>Subtotal:</span> <span><?= number_format($sale['total_amount'], 2) ?></span></div>
            <div class="item-row"><span>Points Used:</span> <span>-<?= number_format($sale['points_redeemed'], 2) ?></span></div>
        <?php endif; ?>

        <div class="total-row">
            <span>TOTAL</span>
            <span><?= number_format($sale['final_total'], 2) ?></span>
        </div>
        
        <div class="text-center" style="margin-top:5px; font-size: 0.9em;">
            Paid via <?= ucfirst($sale['payment_method']) ?>
        </div>

        <?php if ($label === 'CLIENT COPY' || $label === 'CUSTOMER RECEIPT'): ?>
            <?php if ($sale['member_id']): ?>
                <div class="line"></div>
                <div style="font-size: 0.9em;">
                    <div class="bold">LOYALTY SUMMARY</div>
                    <div class="item-row"><span>Points Earned:</span> <span><?= number_format($sale['points_earned'], 2) ?></span></div>
                    <?php 
                        // Note: To show "New Balance", we would ideally fetch from Member DB, 
                        // but showing "Earned" is sufficient for the transaction record.
                    ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="text-center" style="margin-top: 15px; font-size: 0.8em;">
            Thank you for your support!<br>Software by HODMAS
        </div>

    <?php endforeach; ?>

    <div class="no-print">
        <button onclick="window.print()" style="padding:10px 20px; font-weight:bold; cursor:pointer;">Print Receipt</button>
    </div>

</body>
</html>
