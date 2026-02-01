<!DOCTYPE html>
<html>
<head>
    <title>Shift Summary Report</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #fff; font-size: 14px; }
        .report-header { border-bottom: 2px solid #000; margin-bottom: 20px; padding-bottom: 10px; }
        .table-summary th { background: #f8f9fa; }
        .text-danger { color: #dc3545 !important; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body class="p-4">
    <?php $data = $_SESSION['shift_report']; ?>
    
    <div class="report-header d-flex justify-content-between align-items-end">
        <div>
            <h2 class="mb-0">SHIFT SUMMARY</h2>
            <p class="text-muted mb-0">Staff: <strong><?= $data['user'] ?></strong> | Period: <?= date('H:i', strtotime($data['start'])) ?> - <?= date('H:i', strtotime($data['end'])) ?></p>
        </div>
        <div class="text-end">
            <h5 class="mb-0">Date: <?= date('d M Y') ?></h5>
        </div>
    </div>

    <div class="row mb-4">
        <?php foreach ($data['totals'] as $pay): ?>
        <div class="col">
            <div class="border p-2 text-center">
                <small class="text-uppercase text-muted"><?= $pay['payment_method'] ?> Total</small>
                <div class="fw-bold fs-5">ZMW <?= number_format($pay['total'], 2) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <table class="table table-bordered table-striped table-summary">
        <thead>
            <tr>
                <th>Product Name</th>
                <th class="text-end">Price</th>
                <th class="text-center">Qty Sold</th>
                <th class="text-end">Revenue</th>
                <th class="text-end">Adjustment</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $gTotalRev = 0; $gTotalAdj = 0;
            foreach ($data['sales'] as $row): 
                $standardRev = $row['standard_price'] * $row['qty_sold'];
                $actualRev = $row['actual_revenue'];
                $adjustment = $actualRev - $standardRev;
                $gTotalRev += $actualRev;
                $gTotalAdj += $adjustment;
            ?>
            <tr>
                <td><?= htmlspecialchars($row['product_name']) ?></td>
                <td class="text-end"><?= number_format($row['standard_price'], 2) ?></td>
                <td class="text-center"><?= $row['qty_sold'] ?></td>
                <td class="text-end"><?= number_format($actualRev, 2) ?></td>
                <td class="text-end fw-bold <?= $adjustment < 0 ? 'text-danger' : '' ?>">
                    <?= number_format($adjustment, 2) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <tfoot class="table-dark">
            <tr>
                <td colspan="3">GRAND TOTALS</td>
                <td class="text-end">ZMW <?= number_format($gTotalRev, 2) ?></td>
                <td class="text-end">ZMW <?= number_format($gTotalAdj, 2) ?></td>
            </tr>
        </tfoot>
    </table>

    <div class="mt-5 d-flex justify-content-between">
        <div style="width: 200px; border-top: 1px solid #000; text-align: center; padding-top: 5px;">Cashier Signature</div>
        <div style="width: 200px; border-top: 1px solid #000; text-align: center; padding-top: 5px;">Manager Signature</div>
    </div>

    <div class="no-print mt-4 text-center">
        <button onclick="window.print()" class="btn btn-primary btn-lg"><i class="bi bi-printer"></i> Print Report</button>
    </div>
</body>
</html>
