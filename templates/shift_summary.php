<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shift Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fff; padding: 20px; font-family: monospace; }
        .total-box { border: 2px solid #000; padding: 10px; margin-bottom: 20px; background: #f8f9fa; }
        .table-sm td, .table-sm th { font-size: 0.9rem; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>

    <div class="text-center mb-4">
        <h3 class="fw-bold">SHIFT REPORT (X-READ)</h3>
        <div>User: <?= htmlspecialchars($_SESSION['shift_report']['user']) ?></div>
        <div>Start: <?= date('d/m/y H:i', strtotime($_SESSION['shift_report']['start'])) ?></div>
        <div>End: <?= date('d/m/y H:i') ?></div>
    </div>

    <div class="row">
        <div class="col-6">
            <h5 class="border-bottom">Payment Methods</h5>
            <table class="table table-sm">
                <?php 
                $grandTotal = 0;
                foreach ($_SESSION['shift_report']['totals'] as $row): 
                    $grandTotal += $row['total'];
                ?>
                <tr>
                    <td><?= ucwords(str_replace('_', ' ', $row['payment_method'])) ?></td>
                    <td class="text-end fw-bold"><?= number_format($row['total'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <div class="col-6">
            <div class="total-box text-center">
                <small class="text-muted">TOTAL REVENUE</small>
                <h2 class="m-0 fw-bold">ZMW <?= number_format($grandTotal, 2) ?></h2>
            </div>
        </div>
    </div>

    <h5 class="border-bottom mt-4">Product Breakdown</h5>
    <table class="table table-sm table-striped">
        <thead>
            <tr>
                <th>Item</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Value</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($_SESSION['shift_report']['sales'] as $sale): ?>
            <tr>
                <td><?= htmlspecialchars($sale['product_name']) ?></td>
                <td class="text-center"><?= $sale['qty_sold'] ?></td>
                <td class="text-end"><?= number_format($sale['actual_revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="d-grid gap-2 mt-5 no-print">
        <button onclick="window.print()" class="btn btn-outline-dark"><i class="bi bi-printer"></i> Print Report</button>
        
        <?php if (!$_SESSION['shift_report']['is_drill_down']): ?>
            <form method="POST" action="index.php?page=end_shift_action" onsubmit="return confirm('Are you sure you want to close this shift? This cannot be undone.');">
                <button type="submit" class="btn btn-danger w-100 py-3 fw-bold">CLOSE SHIFT & LOGOUT</button>
            </form>
        <?php endif; ?>
    </div>

</body>
</html>
