<?php
if (!isset($_GET['sale_id'])) die("Invalid Sale ID");
$saleId = (int)$_GET['sale_id'];
$isBill = isset($_GET['is_bill']) && $_GET['is_bill'] == '1';

// Fetch Custom System Settings for Receipts
$settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('business_name', 'receipt_header', 'receipt_footer')");
$sysSettings = [];
while ($sRow = $settingsStmt->fetch(PDO::FETCH_ASSOC)) {
    $sysSettings[$sRow['setting_key']] = $sRow['setting_value'];
}
$bizName = !empty($sysSettings['business_name']) ? $sysSettings['business_name'] : 'OdeliaPOS';
$receiptHeader = $sysSettings['receipt_header'] ?? '';
$receiptFooter = $sysSettings['receipt_footer'] ?? 'Thank you for your business!';

$stmt = $pdo->prepare("
    SELECT s.*, u.username, l.name as loc_name 
    FROM sales s 
    JOIN users u ON s.user_id = u.id 
    LEFT JOIN locations l ON s.location_id = l.id 
    WHERE s.id = ?
");
$stmt->execute([$saleId]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sale) die("Sale not found.");

$stmt = $pdo->prepare("
    SELECT si.*, p.name 
    FROM sale_items si 
    LEFT JOIN products p ON si.product_id = p.id 
    WHERE si.sale_id = ? AND si.status != 'voided'
");
$stmt->execute([$saleId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= $isBill ? 'Bill' : 'Receipt' ?> #<?= $saleId ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 14px; width: 300px; margin: 0 auto; padding: 10px; color: #000; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .fw-bold { font-weight: bold; }
        .border-bottom { border-bottom: 1px dashed #000; padding-bottom: 5px; margin-bottom: 5px; }
        .border-top { border-top: 1px dashed #000; padding-top: 5px; margin-top: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 2px 0; vertical-align: top; }
        .amount { text-align: right; }
    </style>
</head>
<body>
    <div class="text-center border-bottom">
        <h2 style="margin:0;"><?= htmlspecialchars($bizName) ?></h2>
        
        <?php if (!empty($receiptHeader)): ?>
        <div style="font-size: 12px; margin: 5px 0;">
            <?= nl2br(htmlspecialchars($receiptHeader)) ?>
        </div>
        <?php endif; ?>
        
        <div style="font-size: 12px; margin: 5px 0;">
            <strong><?= htmlspecialchars($sale['loc_name'] ?? '') ?></strong>
        </div>

        <p style="margin:5px 0;">
            <span style="font-size: 16px; font-weight: bold;"><?= $isBill ? '--- PROFORMA BILL ---' : 'RECEIPT' ?></span><br>
            Order #<?= $sale['id'] ?><br>
            <?= date('d M Y h:i A', strtotime($sale['created_at'])) ?>
        </p>
    </div>
    <p><strong>Cashier:</strong> <?= htmlspecialchars($sale['username']) ?><br>
    <strong>Customer:</strong> <?= htmlspecialchars($sale['customer_name'] ?? 'Walk-in') ?></p>
    
    <table class="border-bottom">
        <thead>
            <tr><th>Item</th><th style="text-align:center">Qty</th><th class="amount">Total</th></tr>
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
        <tr><td>Subtotal:</td><td class="amount"><?= number_format($sale['subtotal'], 2) ?></td></tr>
        <?php if($sale['tip_amount'] > 0): ?>
        <tr><td>Tip/Service:</td><td class="amount"><?= number_format($sale['tip_amount'], 2) ?></td></tr>
        <?php endif; ?>
        <tr class="fw-bold" style="font-size: 16px;"><td>TOTAL:</td><td class="amount">ZMW <?= number_format($sale['final_total'], 2) ?></td></tr>
    </table>
    
    <?php if(!$isBill): ?>
    <table>
        <tr><td>Paid Via:</td><td class="amount fw-bold"><?= htmlspecialchars($sale['payment_method']) ?></td></tr>
        <tr><td>Status:</td><td class="amount"><?= strtoupper($sale['payment_status']) ?></td></tr>
    </table>
    <?php else: ?>
    <div class="text-center fw-bold" style="font-size: 15px; margin-top: 10px; padding: 5px; border: 1px solid #000;">
        PLEASE PAY THIS AMOUNT
    </div>
    <?php endif; ?>
    
    <div class="text-center border-top" style="margin-top: 15px;">
        <p><?= nl2br(htmlspecialchars($receiptFooter)) ?></p>
    </div>
</body>
</html>
