<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Shift Report #<?= $_SESSION['shift_report']['meta']['id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #fff; padding: 20px; font-family: monospace; color: #000; }
        .total-box { border: 2px solid #000; padding: 10px; margin-bottom: 20px; background: #f8f9fa; }
        .table-sm td, .table-sm th { font-size: 0.9rem; }
        .signature-line { border-top: 1px solid #000; width: 80%; margin: 0 auto; padding-top: 5px; }
        @media print { 
            .no-print { display: none !important; } 
            body { padding: 0; }
            .card { border: none !important; }
        }
    </style>
</head>
<body>

    <?php 
    $meta = $_SESSION['shift_report']['meta'];
    $isClosed = $_SESSION['shift_report']['is_closed'];
    ?>

    <div class="text-center mb-4">
        <h3 class="fw-bold"><?= $isClosed ? 'Z-REPORT (CLOSED SHIFT)' : 'X-READ (OPEN SHIFT)' ?></h3>
        <div class="fs-5">Shift #<?= $meta['id'] ?></div>
        <div>Cashier: <strong><?= htmlspecialchars($meta['full_name'] ?? $meta['username']) ?></strong></div>
        <div>Start: <?= date('d/m/y H:i', strtotime($meta['start_time'])) ?></div>
        <?php if($isClosed): ?>
            <div>End: <?= date('d/m/y H:i', strtotime($meta['end_time'])) ?></div>
        <?php else: ?>
            <div>Printed: <?= date('d/m/y H:i') ?></div>
        <?php endif; ?>
    </div>

    <div class="row">
        <div class="col-6">
            <h5 class="border-bottom fw-bold">Payment Methods</h5>
            <table class="table table-sm table-borderless">
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
                <tr class="border-top border-dark">
                    <td class="fw-bold">TOTAL SALES</td>
                    <td class="text-end fw-bold"><?= number_format($grandTotal, 2) ?></td>
                </tr>
            </table>
        </div>
        
        <div class="col-6">
            <h5 class="border-bottom fw-bold">Cash Reconciliation</h5>
            <?php if ($isClosed): ?>
                <table class="table table-sm table-borderless">
                    <tr>
                        <td>Opening Float:</td>
                        <td class="text-end"><?= number_format($meta['starting_cash'], 2) ?></td>
                    </tr>
                    <tr>
                        <td>Calculated Cash:</td>
                        <td class="text-end"><?= number_format($meta['expected_cash'], 2) ?></td>
                    </tr>
                    <tr class="fw-bold fs-5">
                        <td>Actual Closing:</td>
                        <td class="text-end"><?= number_format($meta['closing_cash'], 2) ?></td>
                    </tr>
                    <?php 
                    $variance = $meta['closing_cash'] - $meta['expected_cash']; 
                    $varColor = $variance < 0 ? 'text-danger' : ($variance > 0 ? 'text-success' : 'text-dark');
                    ?>
                    <tr class="<?= $varColor ?>">
                        <td>Variance:</td>
                        <td class="text-end"><?= number_format($variance, 2) ?></td>
                    </tr>
                    <?php if(!empty($meta['variance_reason'])): ?>
                    <tr>
                        <td colspan="2" class="small text-muted fst-italic pt-2">
                            "<?= htmlspecialchars($meta['variance_reason']) ?>"
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            <?php else: ?>
                <div class="alert alert-warning py-2 text-center small">
                    Reconciliation available after closing.
                </div>
                <div class="d-flex justify-content-between">
                    <span>Opening Float:</span>
                    <span class="fw-bold"><?= number_format($meta['starting_cash'], 2) ?></span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <h5 class="border-bottom mt-3 fw-bold">Product Sales</h5>
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

    <div class="row mt-5 pt-4">
        <div class="col-6 text-center">
            <div class="signature-line"></div>
            <div class="small fw-bold mt-1">Cashier Signature</div>
        </div>
        <div class="col-6 text-center">
            <div class="signature-line"></div>
            <div class="small fw-bold mt-1">Manager Signature</div>
        </div>
    </div>

    <div class="d-grid gap-2 mt-5 no-print">
        <button onclick="window.print()" class="btn btn-dark btn-lg"><i class="bi bi-printer-fill"></i> PRINT REPORT</button>
        
        <a href="index.php?page=dashboard" class="btn btn-outline-secondary" target="_top">Back to Dashboard</a>

        <?php if (!$isClosed): ?>
            <div class="card bg-light border-danger mt-4">
                <div class="card-header bg-danger text-white fw-bold">
                    <i class="bi bi-lock-fill"></i> Manager Close Approval
                </div>
                <div class="card-body">
                    <form method="POST" action="index.php?page=end_shift_action" target="_top">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Closing Cash Count (ZMW)</label>
                            <input type="number" step="0.01" name="closing_cash" class="form-control form-control-lg fw-bold" required placeholder="0.00">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Variance Reason</label>
                            <input type="text" name="variance_reason" class="form-control" placeholder="Optional...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-danger">Manager Password</label>
                            <input type="password" name="manager_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-danger w-100 py-3 fw-bold" onclick="return confirm('Close shift now?');">
                            VERIFY & CLOSE SHIFT
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

</body>
</html>
