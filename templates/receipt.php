<?php
if (!isset($_SESSION['user_id'])) { exit; }

$saleId = $_GET['sale_id'] ?? 0;
$sale = $pdo->query("SELECT s.*, u.full_name as cashier, l.name as location_name, l.address, l.phone 
                     FROM sales s 
                     JOIN users u ON s.user_id = u.id 
                     JOIN locations l ON s.location_id = l.id 
                     WHERE s.id = $saleId")->fetch();

if (!$sale) die("Sale not found.");

$items = $pdo->query("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = $saleId")->fetchAll();

// MATH FIX: Ensure Change is calculated correctly
$subTotal = $sale['final_total']; // Items total (after discount)
$tip = $sale['tip_amount'];
$grandTotal = $subTotal + $tip;
$tendered = $sale['amount_tendered'];
$change = $tendered - $grandTotal;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Receipt #<?= $sale['id'] ?></title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 12px; max-width: 300px; margin: 0 auto; padding: 10px; background: #fff; color: #000; }
        .text-center { text-align: center; }
        .text-end { text-align: right; }
        .fw-bold { font-weight: bold; }
        .header { border-bottom: 1px dashed #000; padding-bottom: 10px; margin-bottom: 10px; }
        .item-row { display: flex; justify-content: space-between; margin-bottom: 4px; }
        .totals { border-top: 1px dashed #000; margin-top: 10px; padding-top: 5px; }
        .totals-row { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .footer { margin-top: 20px; text-align: center; font-size: 10px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <div class="header text-center">
        <h3 style="margin:0;"><?= htmlspecialchars($sale['location_name'] ?? '') ?></h3>
        <p style="margin:5px 0;"><?= htmlspecialchars($sale['address'] ?? '') ?><br>Tel: <?= htmlspecialchars($sale['phone'] ??'') ?></p>
        <p style="margin:0;">Rcpt: #<?= $sale['id'] ?> &bull; <?= date('d/m/y H:i', strtotime($sale['created_at'])) ?></p>
        <p style="margin:0;">Staff: <?= htmlspecialchars($sale['cashier']) ?></p>
    </div>

    <div class="items">
        <?php foreach($items as $i): ?>
        <div class="item-row">
            <span><?= $i['quantity'] ?> x <?= htmlspecialchars($i['name']) ?></span>
            <span class="fw-bold"><?= number_format($i['price_at_sale'] * $i['quantity'], 2) ?></span>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="totals">
        <div class="totals-row"><span>Subtotal:</span><span><?= number_format($subTotal, 2) ?></span></div>
        
        <?php if($tip > 0): ?>
        <div class="totals-row"><span>Tip:</span><span><?= number_format($tip, 2) ?></span></div>
        <?php endif; ?>
        
        <div class="totals-row fw-bold" style="font-size: 1.2em; margin: 5px 0;">
            <span>TOTAL:</span><span><?= number_format($grandTotal, 2) ?></span>
        </div>
        
        <div class="totals-row"><span>Paid (<?= $sale['payment_method'] ?>):</span><span><?= number_format($tendered, 2) ?></span></div>
        
        <?php if($change > 0): ?>
        <div class="totals-row"><span>Change Due:</span><span><?= number_format($change, 2) ?></span></div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <p>Thank you for your support!</p>
        <?php if($sale['customer_name'] !== 'Walk-in'): ?>
        <p>Guest: <?= htmlspecialchars($sale['customer_name']) ?></p>
        <?php endif; ?>
    </div>

    <button class="no-print" style="width:100%; padding:10px; background:#000; color:#fff; cursor:pointer; margin-top:10px;" onclick="window.print()">PRINT RECEIPT</button>
</body>
</html>
