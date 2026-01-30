<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>📊 Sales Reports</h3>
    <button class="btn btn-outline-secondary" onclick="window.print()">
        <i class="bi bi-printer"></i> Print Report
    </button>
</div>

<div class="card shadow-sm mb-4 no-print">
    <div class="card-body bg-light">
        <form method="GET" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="reports">
            
            <div class="col-md-3">
                <label class="form-label fw-bold">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold">Location</label>
                <select name="location_id" class="form-select">
                    <option value="">All Locations</option>
                    <?php foreach ($locs as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= $locationId == $l['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($l['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100 fw-bold">
                    <i class="bi bi-filter"></i> Apply Filters
                </button>
            </div>
        </form>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-primary h-100">
            <div class="card-body text-center">
                <h6 class="text-muted text-uppercase">Total Revenue</h6>
                <h2 class="display-6 fw-bold text-primary">ZMW <?= number_format($kpi['total_revenue'] ?? 0, 2) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-success h-100">
            <div class="card-body text-center">
                <h6 class="text-muted text-uppercase">Transactions</h6>
                <h2 class="display-6 fw-bold text-success"><?= number_format($kpi['total_txns'] ?? 0) ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-info h-100">
            <div class="card-body text-center">
                <h6 class="text-muted text-uppercase">Avg Basket Size</h6>
                <h2 class="display-6 fw-bold text-info">ZMW <?= number_format($kpi['avg_basket'] ?? 0, 2) ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white">Payment Methods</div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item d-flex justify-content-between">
                    <span>💵 Cash</span>
                    <strong>ZMW <?= number_format($payments['cash'] ?? 0, 2) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>💳 Card</span>
                    <strong>ZMW <?= number_format($payments['card'] ?? 0, 2) ?></strong>
                </li>
                <li class="list-group-item d-flex justify-content-between">
                    <span>📱 Mobile</span>
                    <strong>ZMW <?= number_format($payments['mobile_money'] ?? 0, 2) ?></strong>
                </li>
            </ul>
        </div>
    </div>

    <div class="col-md-8 mb-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white">🔥 Top 5 Best Sellers</div>
            <table class="table mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Product</th>
                        <th>Qty Sold</th>
                        <th class="text-end">Revenue Generated</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($topProducts as $p): ?>
                    <tr>
                        <td><?= htmlspecialchars($p['name']) ?></td>
                        <td class="fw-bold"><?= $p['qty_sold'] ?></td>
                        <td class="text-end">ZMW <?= number_format($p['revenue'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($topProducts)): ?>
                        <tr><td colspan="3" class="text-center text-muted">No sales data yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white fw-bold">Recent Transactions (Last 100)</div>
    <div class="card-body p-0">
        <table class="table table-striped table-hover mb-0">
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Date/Time</th>
                    <th>Location</th>
                    <th>Cashier</th>
                    <th>Method</th>
                    <th class="text-end">Amount</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $t): ?>
                <tr>
                    <td>#<?= $t['id'] ?></td>
                    <td><?= date('d M H:i', strtotime($t['created_at'])) ?></td>
                    <td><?= htmlspecialchars($t['loc_name']) ?></td>
                    <td><?= htmlspecialchars($t['username']) ?></td>
                    <td><span class="badge bg-secondary"><?= ucfirst($t['payment_method']) ?></span></td>
                    <td class="text-end fw-bold">ZMW <?= number_format($t['final_total'], 2) ?></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="viewReceipt(<?= $t['id'] ?>)">
                            <i class="bi bi-receipt"></i> View
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="reportReceiptModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Receipt View</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height: 500px;">
                <iframe id="reportReceiptFrame" src="" style="width:100%; height:100%; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>

<script>
function viewReceipt(saleId) {
    var modal = new bootstrap.Modal(document.getElementById('reportReceiptModal'));
    document.getElementById('reportReceiptFrame').src = 'index.php?page=receipt&sale_id=' + saleId;
    modal.show();
}
</script>

<style>
@media print {
    .no-print, .navbar, .btn { display: none !important; }
    .card { border: 1px solid #ccc; }
}
</style>
