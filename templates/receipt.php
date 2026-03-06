<?php
// FORCE BROWSER TO NEVER CACHE THIS FILE
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_GET['sale_id'])) die("Invalid Sale ID");
$saleId = (int)$_GET['sale_id'];
$isBill = isset($_GET['is_bill']) && $_GET['is_bill'] == '1';

// Check if we were passed specific item IDs (Incremental Addition)
$specificItems = isset($_GET['items']) && !empty($_GET['items']) ? array_filter(array_map('intval', explode(',', $_GET['items']))) : [];
$isIncremental = (!empty($specificItems) && $isBill);

// Fetch Settings
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('business_name', 'receipt_header', 'receipt_footer')");
$sysSettings = [];
while ($sRow = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $sysSettings[$sRow['setting_key']] = $sRow['setting_value'];
}
$bizName = !empty($sysSettings['business_name']) ? $sysSettings['business_name'] : 'OdeliaPOS';
$receiptHeader = $sysSettings['receipt_header'] ?? '';
$receiptFooter = $sysSettings['receipt_footer'] ?? 'Thank you for your business!';

// Fetch Sale
$stmt = $pdo->prepare("SELECT s.*, u.username, l.name as loc_name FROM sales s JOIN users u ON s.user_id = u.id LEFT JOIN locations l ON s.location_id = l.id WHERE s.id = ?");
$stmt->execute([$saleId]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) die("Sale not found.");

// Fetch Items (Grouped by product and price to consolidate quantities cleanly)
if ($isIncremental) {
    $inClause = implode(',', $specificItems);
    $stmt = $pdo->prepare("
        SELECT COALESCE(p.name, 'Custom Item') as name, si.price, SUM(si.quantity) as quantity 
        FROM sale_items si 
        LEFT JOIN products p ON si.product_id = p.id 
        WHERE si.sale_id = ? AND si.status != 'voided' AND si.id IN ($inClause) 
        GROUP BY p.name, si.price
    ");
    $stmt->execute([$saleId]);
} else {
    $stmt = $pdo->prepare("
        SELECT COALESCE(p.name, 'Custom Item') as name, si.price, SUM(si.quantity) as quantity 
        FROM sale_items si 
        LEFT JOIN products p ON si.product_id = p.id 
        WHERE si.sale_id = ? AND si.status != 'voided' 
        GROUP BY p.name, si.price
    ");
    $stmt->execute([$saleId]);
}
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$displayTotal = 0;
foreach($items as $i) { $displayTotal += ($i['price'] * $i['quantity']); }
$previousBalance = $sale['final_total'] - $displayTotal;

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
        @media print { body { font-weight: bold !important; color: #000 !important; } * { font-weight: bold !important; color: #000 !important; } }
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
                        <tr><td>Subtotal:</td><td class="amount"><?= number_format($displayTotal, 2) ?></td></tr>
                        
                        <?php if($sale['tip_amount'] > 0): ?>
                        <tr><td>Tip/Service:</td><td class="amount"><?= number_format($sale['tip_amount'], 2) ?></td></tr>
                        <?php endif; ?>
                        
                        <?php 
                            $calcDisc = ($displayTotal + $sale['tip_amount']) - $sale['final_total'];
                            if($calcDisc > 0.01):
                        ?>
                        <tr><td>Discount:</td><td class="amount">-ZMW <?= number_format($calcDisc, 2) ?></td></tr>
                        <?php endif; ?>

                        <tr class="fw-bold" style="font-size: 17px;">
                            <td><?= $isBill ? 'TAB BALANCE' : 'TOTAL PAID' ?>:</td><td class="amount">ZMW <?= number_format($sale['final_total'], 2) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
                
                <?php if(!$isBill): ?>
                <table>
                    <?php if($sale['payment_method'] === 'Split'): ?>
                    <tr><td colspan="2" class="fw-bold">Paid Via Split:</td></tr>
                    <?php if(!empty($sale['split_method_1']) && $sale['split_amount_1'] > 0): ?>
                    <tr><td>- <?= htmlspecialchars($sale['split_method_1']) ?></td><td class="amount fw-bold">ZMW <?= number_format($sale['split_amount_1'], 2) ?></td></tr>
                    <?php endif; ?>
                    <?php if(!empty($sale['split_method_2']) && $sale['split_amount_2'] > 0): ?>
                    <tr><td>- <?= htmlspecialchars($sale['split_method_2']) ?></td><td class="amount fw-bold">ZMW <?= number_format($sale['split_amount_2'], 2) ?></td></tr>
                    <?php endif; ?>
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
                
                <div class="text-center border-top" style="margin-top: 15px; font-size: 13px;">
                    <p><?= nl2br(htmlspecialchars($receiptFooter)) ?></p>
                </div>
            </div>
        <?php endif; ?>

    <?php endforeach; ?>

    <script>
        window.onload = function() { setTimeout(function() { window.print(); }, 500); };
    </script>
</body>
</html>
