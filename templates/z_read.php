<style>
    @media print {
        body * { visibility: hidden; }
        #printableZRead, #printableZRead * { visibility: visible; }
        #printableZRead { position: absolute; left: 0; top: 0; width: 100%; }
        .no-print { display: none !important; }
    }
</style>

<div class="d-flex justify-content-between align-items-center mb-4 mt-3 no-print">
    <h3 class="fw-bold m-0"><i class="bi bi-journal-check text-success me-2"></i> End of Day (Z-Read)</h3>
    <button onclick="window.print()" class="btn btn-dark fw-bold shadow-sm"><i class="bi bi-printer"></i> Print Z-Read</button>
</div>

<div class="card shadow-sm border-0 mb-4 border-top border-success border-4 no-print">
    <div class="card-body bg-light p-4">
        <form method="GET" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="z_read">
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">REPORT DATE</label>
                <input type="date" name="date" class="form-control fw-bold" value="<?= htmlspecialchars($date) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">WORKSTATION</label>
                <select name="location_id" class="form-select fw-bold">
                    <?php foreach($locations as $loc): ?>
                        <option value="<?= $loc['id'] ?>" <?= ($locationId == $loc['id']) ? 'selected' : '' ?>><?= htmlspecialchars($loc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <button type="submit" class="btn btn-success w-100 fw-bold py-2 shadow-sm"><i class="bi bi-search"></i> GENERATE</button>
            </div>
        </form>
    </div>
</div>

<div id="printableZRead" class="bg-white p-4 rounded shadow border border-2">
    <div class="text-center border-bottom pb-3 mb-4">
        <h2 class="fw-bold mb-1"><?= htmlspecialchars(APP_NAME ?? 'OdeliaPOS') ?></h2>
        <h4 class="text-muted mb-2">End of Day (Z-Read) Report</h4>
        <div class="fw-bold fs-5">Date: <?= date('d F Y', strtotime($date)) ?></div>
        <div class="text-muted small mt-1">Generated: <?= date('d M Y, H:i') ?></div>
    </div>

    <div class="row g-4 mb-4 border-bottom pb-4">
        <div class="col-6">
            <div class="border border-success p-3 rounded bg-success bg-opacity-10 text-center h-100 shadow-sm">
                <small class="fw-bold text-success text-uppercase">Gross Sales</small>
                <h3 class="fw-bold text-success m-0 mt-2">ZMW <?= number_format($metrics['gross_sales'] ?? 0, 2) ?></h3>
            </div>
        </div>
        <div class="col-6">
            <div class="border p-3 rounded bg-light text-center h-100 shadow-sm">
                <small class="fw-bold text-muted text-uppercase">Total Transactions</small>
                <h3 class="fw-bold text-dark m-0 mt-2"><?= number_format($metrics['total_transactions'] ?? 0) ?></h3>
            </div>
        </div>
        <div class="col-4">
            <div class="border p-3 rounded text-center h-100 bg-light">
                <small class="fw-bold text-muted text-uppercase">Total Refunds</small>
                <h4 class="fw-bold text-danger m-0 mt-2">ZMW <?= number_format($metrics['total_refunded'] ?? 0, 2) ?></h4>
            </div>
        </div>
        <div class="col-4">
            <div class="border p-3 rounded text-center h-100 bg-light">
                <small class="fw-bold text-muted text-uppercase">Tips Collected</small>
                <h4 class="fw-bold text-info m-0 mt-2">ZMW <?= number_format($metrics['total_tips'] ?? 0, 2) ?></h4>
            </div>
        </div>
        <div class="col-4">
            <div class="border border-warning p-3 rounded text-center h-100 bg-warning bg-opacity-10">
                <small class="fw-bold text-dark text-uppercase">Petty Cash / Payouts</small>
                <h4 class="fw-bold text-danger m-0 mt-2">ZMW <?= number_format($expenses['total_expenses'] ?? 0, 2) ?></h4>
                <small class="text-muted fw-bold"><?= $expenses['expense_count'] ?> Transactions</small>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <h5 class="fw-bold text-primary mb-3"><i class="bi bi-wallet2"></i> Payment Methods</h5>
            <table class="table table-sm table-hover align-middle border">
                <thead class="table-light"><tr><th class="ps-2">Method</th><th class="text-center">Count</th><th class="text-end pe-2">Amount</th></tr></thead>
                <tbody>
                    <?php $totalMethods = 0; foreach($paymentMethods as $pm): $totalMethods += $pm['amount']; ?>
                    <tr>
                        <td class="fw-bold text-secondary ps-2"><?= htmlspecialchars($pm['payment_method']) ?></td>
                        <td class="text-center"><?= $pm['count'] ?></td>
                        <td class="text-end fw-bold text-dark pe-2">ZMW <?= number_format($pm['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr><td colspan="2" class="fw-bold text-end">Total Collected:</td><td class="text-end fw-bold text-primary pe-2">ZMW <?= number_format($totalMethods, 2) ?></td></tr>
                </tfoot>
            </table>
        </div>

        <div class="col-md-6">
            <h5 class="fw-bold text-success mb-3"><i class="bi bi-tags"></i> Sales by Category</h5>
            <table class="table table-sm table-hover align-middle border">
                <thead class="table-light"><tr><th class="ps-2">Category</th><th class="text-center">Qty</th><th class="text-end pe-2">Amount</th></tr></thead>
                <tbody>
                    <?php $totalCats = 0; foreach($categoriesBreakdown as $cat): $totalCats += $cat['amount']; ?>
                    <tr>
                        <td class="fw-bold text-secondary ps-2"><?= htmlspecialchars($cat['category_name'] ?: 'Uncategorized') ?></td>
                        <td class="text-center"><?= $cat['qty'] ?></td>
                        <td class="text-end fw-bold text-dark pe-2">ZMW <?= number_format($cat['amount'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr><td colspan="2" class="fw-bold text-end">Categorized Total:</td><td class="text-end fw-bold text-success pe-2">ZMW <?= number_format($totalCats, 2) ?></td></tr>
                </tfoot>
            </table>
        </div>
    </div>

    <div>
        <h5 class="fw-bold text-info mb-3"><i class="bi bi-box-seam"></i> Itemized Sales</h5>
        <table class="table table-sm table-hover table-striped align-middle border">
            <thead class="table-dark">
                <tr>
                    <th class="ps-3 py-2">Product Name</th>
                    <th class="text-center py-2">Total Sold</th>
                    <th class="text-end pe-3 py-2">Revenue Generated</th>
                </tr>
            </thead>
            <tbody>
                <?php if(empty($productBreakdown)): ?>
                    <tr><td colspan="3" class="text-center text-muted py-3">No products sold on this date.</td></tr>
                <?php else: foreach($productBreakdown as $prod): ?>
                    <tr>
                        <td class="ps-3 fw-bold text-secondary"><?= htmlspecialchars($prod['name']) ?></td>
                        <td class="text-center fw-bold fs-6"><?= $prod['qty'] ?></td>
                        <td class="text-end pe-3 fw-bold text-dark">ZMW <?= number_format($prod['amount'], 2) ?></td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <div class="text-center text-muted small mt-5 pt-3 border-top">
        *** END OF Z-READ REPORT ***
    </div>
</div>

<?php include 'footer.php'; ?>
