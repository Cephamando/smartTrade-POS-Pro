<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-shield-check"></i> Inventory Audit Trail</h3>
    <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
        <i class="bi bi-printer"></i> Print Log
    </button>
</div>

<div class="card shadow-sm mb-4 no-print">
    <div class="card-body bg-light">
        <form method="GET" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="audit">
            
            <div class="col-md-3">
                <label class="form-label fw-bold small">Start Date</label>
                <input type="date" name="start_date" class="form-control" value="<?= $startDate ?>">
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold small">End Date</label>
                <input type="date" name="end_date" class="form-control" value="<?= $endDate ?>">
            </div>

            <div class="col-md-3">
                <label class="form-label fw-bold small">Action Type</label>
                <select name="action_type" class="form-select">
                    <option value="">All Actions</option>
                    <?php foreach ($actionTypes as $type): ?>
                        <option value="<?= $type ?>" <?= $actionFilter == $type ? 'selected' : '' ?>>
                            <?= ucfirst(str_replace('_', ' ', $type)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Date & Time</th>
                        <th>Location</th>
                        <th>Product</th>
                        <th>Action</th>
                        <th class="text-center">Change</th>
                        <th class="text-center">Balance After</th>
                        <th>Staff</th>
                        <th>Ref#</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                    <tr>
                        <td class="small"><?= date('d M y, H:i', strtotime($log['created_at'])) ?></td>
                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['loc_name']) ?></span></td>
                        <td class="fw-bold"><?= htmlspecialchars($log['product_name']) ?></td>
                        <td>
                            <?php 
                                $badgeClass = 'bg-secondary';
                                if($log['action_type'] == 'sale') $badgeClass = 'bg-danger';
                                if($log['action_type'] == 'grv') $badgeClass = 'bg-success';
                                if(strpos($log['action_type'], 'transfer') !== false) $badgeClass = 'bg-info text-dark';
                            ?>
                            <span class="badge <?= $badgeClass ?> text-uppercase" style="font-size: 0.7rem;">
                                <?= str_replace('_', ' ', $log['action_type']) ?>
                            </span>
                        </td>
                        <td class="text-center fw-bold <?= $log['change_qty'] > 0 ? 'text-success' : 'text-danger' ?>">
                            <?= ($log['change_qty'] > 0 ? '+' : '') . ($log['change_qty'] + 0) ?>
                        </td>
                        <td class="text-center bg-light fw-bold"><?= $log['after_qty'] + 0 ?></td>
                        <td><small class="text-muted">@<?= htmlspecialchars($log['staff_name']) ?></small></td>
                        <td><small class="text-muted">#<?= $log['reference_id'] ?: '-' ?></small></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($logs)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No audit logs found for this period.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
@media print {
    .no-print, .navbar, .btn { display: none !important; }
    .card { border: none !important; }
    .table-dark { background-color: #fff !important; color: #000 !important; border-bottom: 2px solid #000; }
}
</style>
