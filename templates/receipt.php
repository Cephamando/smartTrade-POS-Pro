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
        .status-box { border: 2px solid <?= $statusColor ?>; color: <?= $statusColor ?>; padding: 5px; text-align: center; margin-top: 15px; font-weight: bold; }
        .copy-label { text-align: center; font-weight: bold; font-size: 1.1em; margin-bottom: 5px; border: 1px solid #000; padding: 2px; }
        .cut-line { border-bottom: 2px dotted #000; margin: 30px 0; position: relative; text-align: center; height: 10px; }
        .cut-line span { background: #fff; padding: 0 10px; position: relative; top: -10px; font-size: 20px; }
        .no-print { margin-top: 20px; text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

    <?php 
    // Determine copies
    $copies = isset($_GET['print_collection']) ? ['CLIENT COPY', 'KITCHEN COPY'] : ['CUSTOMER RECEIPT'];
    
    foreach ($copies as $index => $label): 
    ?>

        <?php if ($index > 0): ?>
            <div class="cut-line"><span>✂</span></div>
        <?php endif; ?>

        <div class="copy-label">*** <?= $label ?> ***</div>

        <div class="text-center">
            <h3 style="margin:0;"><?= htmlspecialchars($sale['location_name'] ?? 'Store') ?></h3>
            <div><?= htmlspecialchars($sale['address'] ?? '') ?></div>
            <div>Tel: <?= htmlspecialchars($sale['phone'] ?? '') ?></div>
        </div>
        
        <div class="line"></div>
        
        <div>Date: <?= $sale['created_at'] ?></div>
        <div>Receipt #: <?= $sale['id'] ?></div>
        <div>Cashier: <?= htmlspecialchars($sale['cashier_name'] ?? 'Staff') ?></div>

        <div class="line"></div>

        <?php foreach ($lineItems as $item): ?>
        <div class="item-row">
            <span><?= $item['quantity'] ?> x <?= htmlspecialchars($item['name']) ?></span>
            <span><?= number_format($item['price_at_sale'] * $item['quantity'], 2) ?></span>
        </div>
        <?php endforeach; ?>

        <div class="line"></div>

        <div class="total-row">
            <span>TOTAL</span>
            <span><?= number_format($sale['final_total'], 2) ?></span>
        </div>
        
        <div class="text-center" style="margin-top:5px;">
            Paid via <?= ucfirst($sale['payment_method']) ?>
        </div>

        <div class="status-box">
            <?php 
                if (empty($sale['collected_by'])) {
                    echo "NOT COLLECTED";
                } else {
                    // CHANGE: Client Copy says 'SERVED BY', Kitchen Copy says 'COLLECTED BY'
                    $prefix = ($label === 'CLIENT COPY') ? 'SERVED BY' : 'COLLECTED BY';
                    echo $prefix . ": " . strtoupper($sale['collected_by']);
                }
            ?>
        </div>

        <div class="text-center" style="font-size: 0.8em; margin-top: 15px;">
            Thank you for your support!<br>
            Software by HODMAS
        </div>

    <?php endforeach; ?>

    <div class="no-print">
        <button onclick="window.print()" style="padding:10px 20px; font-weight:bold; cursor:pointer;">Print Receipt</button>
    </div>

</body>
</html>
