<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>X-Read Shift #<?= $shift['id'] ?></title>
    <style>
        html, body { width: 72mm; margin: 0; padding: 2mm; }
        body { font-family: 'Courier New', Courier, monospace; color: #000; background: #fff; font-size: 13px; }
        html { background: #fff; color-scheme: light; }
        h2, h3, h4, h5 { text-align: center; margin: 5px 0; }
        .line { border-top: 1px dashed #000; margin: 10px 0; }
        .flex { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .bold { font-weight: bold; }
    </style>

    <style id="force-thermal-bold">
        body, table, th, td, p, span, div, li, h1, h2, h3, h4, h5, h6, * { 
            font-family: "Courier New", Courier, monospace !important; 
            font-weight: 900 !important; 
            color: #000 !important; 
            background-color: #fff !important;
        }
        .border-bottom { border-bottom: 2px dashed #000 !important; }
        .border-top { border-top: 2px dashed #000 !important; }
        hr { border-top: 2px dashed #000 !important; border-bottom: none !important; opacity: 1 !important; }
        
        @media print {
            html, body { width: 72mm; margin: 0; padding: 1mm; height: auto; }
            * { font-weight: 900 !important; color: #000 !important; }
        }
    </style>
</head>
<body>
    <h2 style="margin-bottom: 2px;"><?= htmlspecialchars(APP_NAME ?? 'OdeliaPOS') ?></h2>
    <h5 style="margin-top: 0; font-weight: normal; color: #333;"><?= htmlspecialchars($shift['loc_name']) ?></h5>
    
    <h3>X-READ REPORT</h3>
    <div class="line"></div>
    <div class="flex"><span>Shift ID:</span> <span>#<?= $shift['id'] ?></span></div>
    <div class="flex"><span>Cashier:</span> <span><?= htmlspecialchars($shift['username']) ?></span></div>
    <div class="flex"><span>Opened:</span> <span><?= date('d M, H:i', strtotime($shift['start_time'])) ?></span></div>
    <div class="flex"><span>Status:</span> <span><?= strtoupper($shift['status']) ?></span></div>
    <div class="line"></div>
    
    <h4>SALES BREAKDOWN</h4>
    <?php if(empty($paymentBreakdown)): ?>
        <div class="flex text-center" style="justify-content: center;"><span>No sales recorded yet.</span></div>
    <?php else: foreach($paymentBreakdown as $pb): ?>
        <div class="flex">
            <span><?= htmlspecialchars($pb['payment_method']) ?> (<?= $pb['tx_count'] ?>)</span>
            <span><?= number_format($pb['total'], 2) ?></span>
        </div>
        <?php if($pb['total_tips'] > 0): ?>
        <div class="flex"><small>  - Includes Tip:</small> <small><?= number_format($pb['total_tips'], 2) ?></small></div>
        <?php endif; ?>
    <?php endforeach; endif; ?>
    <?php if(!empty($surcharges['total'])): ?>
    <div class="flex"><small>  - Includes surcharges (<?= (int)$surcharges['count'] ?>):</small> <small><?= number_format($surcharges['total'], 2) ?></small></div>
    <?php endif; ?>
    <div class="line"></div>
    <div class="flex bold"><span>GRAND TOTAL:</span> <span>ZMW <?= number_format($grandTotal, 2) ?></span></div>

    <?php if (!empty($gatewayPayments)): ?>
    <div class="line"></div>
    <h4>FLUTTERWAVE</h4>
    <?php foreach ($gatewayPayments as $gp): ?>
        <div class="flex bold"><span>#<?= (int)$gp['sale_id'] ?></span><span><?= number_format((float)$gp['amount'], 2) ?></span></div>
        <?php foreach ($gp['lines'] as $line): ?>
        <div class="flex"><span><?= htmlspecialchars($line['label']) ?></span><span><?= htmlspecialchars($line['value']) ?></span></div>
        <?php endforeach; ?>
    <?php endforeach; ?>
    <?php endif; ?>

    <?php $pt = $postTips ?? ['total' => 0, 'cash' => 0, 'by_method' => [], 'by_staff' => []]; if(($pt['total'] ?? 0) > 0): ?>
    <div class="line"></div>
    <h4>TIPS AFTER PAYMENT</h4>
    <?php foreach($pt['by_method'] as $m => $amt): ?>
    <div class="flex"><span><?= htmlspecialchars((string)$m) ?></span> <span><?= number_format($amt, 2) ?></span></div>
    <?php endforeach; ?>
    <div class="flex bold"><span>Total tips:</span> <span><?= number_format($pt['total'], 2) ?></span></div>
    <?php foreach($pt['by_staff'] as $staff => $row): ?>
    <div class="flex"><small>  - <?= htmlspecialchars((string)$staff) ?> (<?= (int)$row['count'] ?>)</small> <small><?= number_format($row['amount'], 2) ?></small></div>
    <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="line"></div>
    <h4>CASH MANAGEMENT</h4>
    <div class="flex"><span>Opening Float:</span> <span><?= number_format($shift['starting_cash'], 2) ?></span></div>
    <div class="flex"><span>Cash Sales:</span> <span>+ <?= number_format($cashSales, 2) ?></span></div>
    <?php if(($pt['cash'] ?? 0) > 0): ?>
    <div class="flex"><span>Cash tips in till:</span> <span>+ <?= number_format($pt['cash'], 2) ?></span></div>
    <?php endif; ?>
    
    <?php if(!empty($expenses)): ?>
        <br>
        <div class="bold" style="text-align:center;">PAYOUTS / EXPENSES</div>
        <?php foreach($expenses as $ex): ?>
            <div class="flex"><small><?= htmlspecialchars($ex['reason']) ?></small> <small>- <?= number_format($ex['amount'], 2) ?></small></div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="flex"><span>Total Payouts:</span> <span>- <?= number_format($totalExpenses ?? 0, 2) ?></span></div>
    <div class="line"></div>
    <div class="flex bold"><span>EXPECTED IN TILL:</span> <span>ZMW <?= number_format($expectedCash, 2) ?></span></div>
    
    <?php if($shift['status'] === 'closed'): ?>
        <div class="line"></div>
        <div class="flex bold"><span>ACTUAL CASH:</span> <span>ZMW <?= number_format($shift['closing_cash'], 2) ?></span></div>
        <div class="flex bold"><span>VARIANCE:</span> <span>ZMW <?= number_format($shift['variance'], 2) ?></span></div>
    <?php endif; ?>

    <div class="line"></div>
    <h4>CATEGORY BREAKDOWN</h4>
    <?php if(empty($categoriesBreakdown)): ?>
        <div class="flex text-center" style="justify-content: center;"><span>No categories recorded.</span></div>
    <?php else: foreach($categoriesBreakdown as $cat): ?>
        <div class="flex">
            <span><?= htmlspecialchars($cat['category_name'] ?: 'Uncategorized') ?> (<?= $cat['qty'] ?>)</span>
            <span><?= number_format($cat['amount'], 2) ?></span>
        </div>
    <?php endforeach; endif; ?>

    <div class="line"></div>
    <h4>ITEMIZED SALES</h4>
    <?php if(empty($productBreakdown)): ?>
        <div class="flex text-center" style="justify-content: center;"><span>No items recorded.</span></div>
    <?php else: foreach($productBreakdown as $prod): ?>
        <div class="flex">
            <span><?= $prod['qty'] ?>x <?= htmlspecialchars($prod['name']) ?></span>
            <span><?= number_format($prod['amount'], 2) ?></span>
        </div>
    <?php endforeach; endif; ?>

    <div class="line"></div>
    <div style="text-align: center; font-size: 0.8em; margin-top: 10px;">End of Report</div>
    <script>
        function fitThermalPage() {
            var px = document.body.offsetHeight;
            var mm = Math.ceil(px * 25.4 / 96) + 2;
            if (mm < 40) mm = 40;
            var style = document.getElementById('thermal-page-size');
            if (!style) {
                style = document.createElement('style');
                style.id = 'thermal-page-size';
                document.head.appendChild(style);
            }
            style.textContent = '@page { size: 80mm ' + mm + 'mm; margin: 0; } html, body { height: auto; min-height: 0; }';
        }
        window.addEventListener('beforeprint', fitThermalPage);
    </script>
</body>
</html>
