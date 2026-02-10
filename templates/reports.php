<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports & Analytics</title>
    <link rel="shortcut icon" href="data:image/x-icon;," type="image/x-icon"> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    
    <style>
        body { background-color: #f8f9fa; }
        .metric-card { border: none; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .metric-card:hover { transform: translateY(-3px); }
        .table-responsive { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        @media print {
            .no-print { display: none !important; }
            .card, .table-responsive { box-shadow: none !important; border: 1px solid #ddd !important; }
        }
    </style>
</head>
<body class="p-3">

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h3 class="fw-bold"><i class="bi bi-bar-chart-line-fill text-primary"></i> Comprehensive Reports</h3>
        <p class="text-muted mb-0">Analysis from <strong><?= date('M d', strtotime($startDate)) ?></strong> to <strong><?= date('M d', strtotime($endDate)) ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?page=dashboard" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Dashboard</a>
        <button onclick="window.print()" class="btn btn-dark"><i class="bi bi-printer"></i> Print Report</button>
    </div>
</div>

<div class="card p-3 mb-4 border-0 shadow-sm no-print">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="page" value="reports">
        <input type="hidden" name="type" value="<?= htmlspecialchars($reportType) ?>">
        <div class="col-md-3">
            <label class="form-label small fw-bold">Start Date</label>
            <input type="text" name="start" class="form-control datepicker" value="<?= $startDate ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">End Date</label>
            <input type="text" name="end" class="form-control datepicker" value="<?= $endDate ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">Location</label>
            <select name="location" class="form-select">
                <option value="">All Locations</option>
                <?php foreach($locations as $l): ?>
                    <option value="<?= $l['id'] ?>" <?= ($locationId == $l['id']) ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="bi bi-filter"></i> Apply Filters</button>
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card metric-card bg-success text-white h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75 fw-bold">Total Revenue</div>
                <div class="display-6 fw-bold">ZMW <?= number_format($metrics['total_revenue'] ?? 0, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card metric-card bg-danger text-white h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75 fw-bold">Refunds (<?= $refundStats['refund_count'] ?? 0 ?>)</div>
                <div class="display-6 fw-bold">ZMW <?= number_format($refundStats['total_refunded'] ?? 0, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card metric-card bg-info text-white h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75 fw-bold">Total Tips</div>
                <div class="display-6 fw-bold">ZMW <?= number_format($metrics['total_tips'] ?? 0, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card metric-card bg-secondary text-white h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75 fw-bold">Avg. Ticket</div>
                <div class="display-6 fw-bold">ZMW <?= number_format($metrics['avg_ticket'] ?? 0, 2) ?></div>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3 no-print">
    <li class="nav-item">
        <a class="nav-link <?= $reportType == 'sales' ? 'active fw-bold' : '' ?>" href="index.php?page=reports&type=sales&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>">Sales Ledger</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportType == 'product' ? 'active fw-bold' : '' ?>" href="index.php?page=reports&type=product&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>">Product Performance</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportType == 'audit' ? 'active fw-bold' : '' ?>" href="index.php?page=reports&type=audit&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>">Audit & Refunds</a>
    </li>
</ul>

<div class="table-responsive p-0">
    <table class="table table-striped table-hover mb-0 align-middle">
        
        <?php if($reportType === 'sales'): ?>
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Station</th>
                <th>Method</th>
                <th>Status</th>
                <th class="text-end">Tips</th>
                <th class="text-end">Total</th>
                <th class="text-end no-print">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($reportData as $row): ?>
            <tr>
                <td>#<?= $row['id'] ?></td>
                <td><?= date('M d, H:i', strtotime($row['created_at'])) ?></td>
                <td class="fw-bold"><?= htmlspecialchars($row['customer_name']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><span class="badge bg-secondary"><?= $row['payment_method'] ?></span></td>
                <td>
                    <?php if(strtolower($row['payment_status'])=='paid'): ?><span class="badge bg-success">PAID</span>
                    <?php elseif(strtolower($row['payment_status'])=='refunded'): ?><span class="badge bg-danger">REFUNDED</span>
                    <?php else: ?><span class="badge bg-warning text-dark">PENDING</span><?php endif; ?>
                </td>
                <td class="text-end text-muted"><?= $row['tip_amount'] > 0 ? number_format($row['tip_amount'], 2) : '-' ?></td>
                <td class="text-end fw-bold">ZMW <?= number_format($row['final_total'], 2) ?></td>
                <td class="text-end no-print">
                    <button onclick="showReceiptModal('index.php?page=receipt&sale_id=<?= $row['id'] ?>')" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i></button>
                    
                    <?php if(strtolower($row['payment_status']) == 'paid' && in_array($_SESSION['role'], ['admin', 'manager', 'dev'])): ?>
                    <form method="POST" class="d-inline" onsubmit="confirmRefund(event)">
                        <input type="hidden" name="refund_sale" value="1">
                        <input type="hidden" name="sale_id" value="<?= $row['id'] ?>">
                        <button class="btn btn-sm btn-outline-danger ms-1" title="Refund"><i class="bi bi-arrow-counterclockwise"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif($reportType === 'product'): ?>
        <thead class="table-dark">
            <tr><th>Product Name</th><th>SKU</th><th class="text-center">Qty Sold</th><th class="text-end">Revenue</th></tr>
        </thead>
        <tbody>
            <?php foreach($reportData as $row): ?>
            <tr>
                <td class="fw-bold"><?= htmlspecialchars($row['name'] ?? '') ?></td><td class="text-muted"><?= htmlspecialchars($row['sku'] ?? '') ?></td>
                <td class="text-center fs-5"><?= $row['qty_sold'] ?></td><td class="text-end fw-bold text-success">ZMW <?= number_format($row['revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif($reportType === 'audit'): ?>
        <thead class="table-dark">
            <tr><th>Time</th><th>Product</th><th>User</th><th>Action</th><th class="text-end">Qty Change</th><th>Reason/Ref</th></tr>
        </thead>
        <tbody>
            <?php foreach($reportData as $row): ?>
            <tr>
                <td><?= date('M d, H:i', strtotime($row['created_at'])) ?></td>
                <td class="fw-bold"><?= htmlspecialchars($row['product_name']) ?></td><td><?= htmlspecialchars($row['username']) ?></td>
                <td><span class="badge bg-<?= $row['action_type']=='sale'?'success':($row['action_type']=='adjustment'?'warning':($row['action_type']=='refund'?'danger':'info')) ?>"><?= strtoupper($row['action_type']) ?></span></td>
                <td class="text-end fw-bold <?= $row['change_qty'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $row['change_qty'] > 0 ? '+'.$row['change_qty'] : $row['change_qty'] ?></td>
                <td class="small text-muted"><?= ($row['action_type']=='sale') ? 'Sale #'.$row['reference_id'] : (($row['action_type']=='refund') ? 'Refund #'.$row['reference_id'] : 'Manual Adj') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <?php endif; ?>
    </table>
    <?php if(empty($reportData)): ?><div class="p-5 text-center text-muted">No records found.</div><?php endif; ?>
</div>

<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white"><h5 class="modal-title">Receipt View</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-0" style="height: 500px;"><iframe id="reportFrame" src="" style="width:100%; height:100%; border:none;"></iframe></div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" onclick="document.getElementById('reportFrame').contentWindow.print()">Print</button></div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // 1. Init Flatpickr
    flatpickr(".datepicker", { dateFormat: "Y-m-d", allowInput: true });

    // 2. Receipt Modal
    function showReceiptModal(url) { document.getElementById('reportFrame').src = url; new bootstrap.Modal(document.getElementById('reportModal')).show(); }

    // 3. SweetAlert Confirmation for Refunds
    function confirmRefund(event) {
        event.preventDefault();
        const form = event.target;
        Swal.fire({
            title: 'Confirm Refund?',
            text: "This will mark the sale as refunded and restore items to inventory.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Yes, Refund it!'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    }

    // 4. Session Flash Messages
    <?php if(isset($_SESSION['swal_msg'])): ?>
    Swal.fire({
        icon: <?= json_encode($_SESSION['swal_type']) ?>,
        title: <?= json_encode($_SESSION['swal_msg']) ?>,
        showConfirmButton: false,
        timer: 2000
    });
    <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
    <?php endif; ?>
</script>
</body>
</html>
