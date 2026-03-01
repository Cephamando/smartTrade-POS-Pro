<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>X-Read Shift #<?= $shift['id'] ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 300px; margin: 0 auto; padding: 10px; color: #000; font-size: 13px; }
        h2, h3, h4, h5 { text-align: center; margin: 5px 0; }
        .line { border-top: 1px dashed #000; margin: 10px 0; }
        .flex { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .bold { font-weight: bold; }
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
    <div class="line"></div>
    <div class="flex bold"><span>GRAND TOTAL:</span> <span>ZMW <?= number_format($grandTotal, 2) ?></span></div>
    
    <div class="line"></div>
    <h4>CASH MANAGEMENT</h4>
    <div class="flex"><span>Opening Float:</span> <span><?= number_format($shift['starting_cash'], 2) ?></span></div>
    <div class="flex"><span>Cash Sales:</span> <span>+ <?= number_format($cashSales, 2) ?></span></div>
    
    <?php if(count($expenses) > 0): ?>
        <br>
        <div class="bold" style="text-align:center;">PAYOUTS / EXPENSES</div>
        <?php foreach($expenses as $ex): ?>
            <div class="flex"><small><?= htmlspecialchars($ex['reason']) ?></small> <small>- <?= number_format($ex['amount'], 2) ?></small></div>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <div class="flex"><span>Total Payouts:</span> <span>- <?= number_format($totalExpenses, 2) ?></span></div>
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
</body>
</html>
