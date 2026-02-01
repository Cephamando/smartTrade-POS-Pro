<?php
if (!isset($_SESSION['shift_report'])) {
    die("No report data found. Please close shift from POS first.");
}
$data = $_SESSION['shift_report'];

$grandTotalRevenue = 0;
$grandTotalSold = 0;
$grandTotalAdj = 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>End Shift Report</title>
    <style>
        /* WEB VIEW STYLES (Screen) */
        body { 
            font-family: 'Segoe UI', Tahoma, sans-serif; 
            background: #555; /* Dark background to show the 'paper' contrast */
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
        }

        /* THE PAGE (A4 Dimensions) */
        .page {
            background: white;
            width: 210mm;
            min-height: 297mm;
            padding: 20mm;
            box-shadow: 0 0 10px rgba(0,0,0,0.5);
            box-sizing: border-box;
            position: relative;
        }

        /* TYPOGRAPHY */
        h2 { text-transform: uppercase; border-bottom: 2px solid #000; padding-bottom: 10px; margin-top: 0; margin-bottom: 20px; font-size: 24px; }
        .meta { display: flex; justify-content: space-between; margin-bottom: 30px; border-bottom: 1px solid #ccc; padding-bottom: 10px; font-size: 14px; }
        
        /* TABLE STYLES */
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th { background: #eee; border-bottom: 2px solid #000; padding: 8px; text-align: left; text-transform: uppercase; font-weight: bold; }
        td { border-bottom: 1px solid #ddd; padding: 8px; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: bold; }

        /* SUMMARY BOXES */
        .summary-grid { display: flex; gap: 20px; margin-bottom: 30px; }
        .summary-box { flex: 1; border: 1px solid #000; padding: 15px; text-align: center; background: #f9f9f9; }
        .summary-val { font-size: 20px; font-weight: bold; display: block; }
        .summary-label { font-size: 12px; text-transform: uppercase; color: #666; letter-spacing: 1px; }

        /* PRINT SETTINGS (A4 Enforcement) */
        @page { size: A4; margin: 0; }
        @media print {
            body { background: white; margin: 0; padding: 0; display: block; }
            .page { width: 100%; margin: 0; box-shadow: none; padding: 20mm; min-height: auto; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

    <div class="page">
        <div class="meta">
            <div><strong>User:</strong> <?= htmlspecialchars($data['user']) ?></div>
            <div><strong>Period:</strong> <?= date('H:i', strtotime($data['start'])) ?> - <?= date('H:i', strtotime($data['end'])) ?></div>
            <div><strong>Date:</strong> <?= date('d M Y') ?></div>
        </div>

        <h2>End Shift Report</h2>

        <?php 
        $totalCash = 0;
        foreach ($data['totals'] as $t) { $totalCash += $t['total']; }
        ?>
        <div class="summary-grid">
            <div class="summary-box">
                <span class="summary-val">ZMW <?= number_format($totalCash, 2) ?></span>
                <span class="summary-label">Total Revenue</span>
            </div>
            <div class="summary-box">
                <span class="summary-val"><?= count($data['sales']) ?></span>
                <span class="summary-label">Products Sold</span>
            </div>
            <div class="summary-box">
                <span class="summary-val"><?= date('H:i') ?></span>
                <span class="summary-label">Generated At</span>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="width: 40%;">Product</th>
                    <th class="text-right">Price</th>
                    <th class="text-center">Sold (Qty)</th>
                    <th class="text-right">Revenue</th>
                    <th class="text-right">Adj (Loss/Gain)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($data['sales'] as $row): 
                    $standardRev = $row['standard_price'] * $row['qty_sold'];
                    $actualRev = $row['actual_revenue'];
                    $adjustment = $actualRev - $standardRev;
                    
                    $grandTotalRevenue += $actualRev;
                    $grandTotalSold += $row['qty_sold'];
                    $grandTotalAdj += $adjustment;
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['product_name']) ?></td>
                    <td class="text-right"><?= number_format($row['standard_price'], 2) ?></td>
                    <td class="text-center"><?= $row['qty_sold'] ?></td>
                    <td class="text-right"><?= number_format($actualRev, 2) ?></td>
                    <td class="text-right fw-bold" style="color: <?= $adjustment < 0 ? 'red' : 'black' ?>;">
                        <?= number_format($adjustment, 2) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="background-color: #f0f0f0; font-weight: bold;">
                    <td colspan="2">TOTALS</td>
                    <td class="text-center"><?= $grandTotalSold ?></td>
                    <td class="text-right"><?= number_format($grandTotalRevenue, 2) ?></td>
                    <td class="text-right" style="color: <?= $grandTotalAdj < 0 ? 'red' : 'black' ?>;">
                        <?= number_format($grandTotalAdj, 2) ?>
                    </td>
                </tr>
            </tfoot>
        </table>

        <div style="margin-top: 50px; display: flex; justify-content: space-between; border-top: 1px solid #ccc; padding-top: 20px;">
            <div style="width: 45%; text-align: center;">
                <br>__________________________<br>
                <strong>Cashier Signature</strong>
            </div>
            <div style="width: 45%; text-align: center;">
                <br>__________________________<br>
                <strong>Manager Signature</strong>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px; font-size: 10px; color: #999;">
            System Generated Report • HODMAS POS
        </div>
    </div>

</body>
</html>
