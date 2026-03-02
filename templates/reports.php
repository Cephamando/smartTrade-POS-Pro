<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reports & Analytics</title>
    <link rel="shortcut icon" href="data:image/x-icon;," type="image/x-icon"> 
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11">
// --- SMART PRINT FUNCTION ---
function printMasterReport() {
    if ($.fn.DataTable.isDataTable('#reportsTable')) {
        let table = $('#reportsTable').DataTable();
        let currentLen = table.page.len();
        
        // Expand the table to show ALL rows before printing
        table.page.len(-1).draw(); 
        
        // Wait a split second for the browser to render the long table, then print
        setTimeout(() => {
            window.print();
            // Restore the pagination back to normal after printing is done
            table.page.len(currentLen).draw(); 
        }, 500);
    } else {
        window.print();
    }
}
</script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr">
// --- SMART PRINT FUNCTION ---
function printMasterReport() {
    if ($.fn.DataTable.isDataTable('#reportsTable')) {
        let table = $('#reportsTable').DataTable();
        let currentLen = table.page.len();
        
        // Expand the table to show ALL rows before printing
        table.page.len(-1).draw(); 
        
        // Wait a split second for the browser to render the long table, then print
        setTimeout(() => {
            window.print();
            // Restore the pagination back to normal after printing is done
            table.page.len(currentLen).draw(); 
        }, 500);
    } else {
        window.print();
    }
}
</script>
    
    <style>
        body { background-color: #f8f9fa; }
        .metric-card { border: none; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s; }
        .metric-card:hover { transform: translateY(-3px); }
        .table-responsive { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 15px; }
            @media print {
        @page { margin: 10mm; size: portrait; }
        body { background-color: #fff !important; font-family: 'Courier New', Courier, monospace !important; color: #000 !important; }
        
        /* Hide all UI elements, navigation, and DataTables controls */
        .no-print, .nav-tabs, form, .dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate { display: none !important; }
        
        /* Reveal the dedicated print header */
        #print-only-header { display: block !important; text-align: center; border-bottom: 2px dashed #000; padding-bottom: 15px; margin-bottom: 25px; }
        
        /* Reformat the 4 Metric Cards into a formal grid */
        .row.g-3.mb-4 { display: flex !important; flex-wrap: nowrap !important; margin-bottom: 25px !important; }
        .col-md-3 { flex: 0 0 25% !important; max-width: 25% !important; padding: 0 5px !important; }
        .metric-card { border: 2px solid #000 !important; background: #fff !important; color: #000 !important; text-align: center !important; padding: 15px 5px !important; box-shadow: none !important; border-radius: 0 !important; }
        .metric-card * { color: #000 !important; }
        .metric-card .display-6 { font-size: 16pt !important; font-weight: 900 !important; margin-top: 5px; }
        .metric-card .small { font-size: 10pt !important; font-weight: bold !important; text-transform: uppercase; }
        
        /* Reformat the Table for paper */
        .table-responsive { padding: 0 !important; background: transparent !important; box-shadow: none !important; }
        table.table { width: 100% !important; border-collapse: collapse !important; border: 2px solid #000 !important; }
        table.table th, table.table td { border: 1px solid #000 !important; padding: 8px !important; color: #000 !important; font-size: 10pt !important; }
        table.table thead th { background-color: #f0f0f0 !important; -webkit-print-color-adjust: exact; color: #000 !important; font-weight: bold !important; border-bottom: 2px solid #000 !important; }
        
        /* Format status badges into clean text tags */
        .badge { border: 1px solid #000 !important; color: #000 !important; background: transparent !important; padding: 2px 6px !important; font-size: 9pt !important; text-transform: uppercase; font-weight: bold !important; }
        
        /* Strip interactive styling from links */
        a, button.btn-link { text-decoration: none !important; color: #000 !important; }
    }
    #print-only-header { display: none; }
    </style>
</head>
<body class="p-3">
<div id="print-only-header">
    <h2 style="margin: 0; font-weight: 900; text-transform: uppercase; font-size: 24pt;">Master Report</h2>
    <h4 style="margin: 5px 0; font-size: 14pt;">Category: <?= strtoupper($reportType) ?></h4>
    <p style="margin: 0; font-size: 12pt;"><strong>Period:</strong> <?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?></p>
    <p style="margin: 0; font-size: 10pt;">Generated: <?= date('d M Y, H:i') ?></p>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h3 class="fw-bold"><i class="bi bi-bar-chart-line-fill text-primary"></i> Comprehensive Reports</h3>
        <p class="text-muted mb-0">Analysis from <strong><?= date('M d', strtotime($startDate)) ?></strong> to <strong><?= date('M d', strtotime($endDate)) ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?page=dashboard" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Dashboard</a>
        <button onclick="printMasterReport()" class="btn btn-dark"><i class="bi bi-printer"></i> Print Report</button>
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
        <div class="card metric-card bg-dark text-white h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75 fw-bold">Voided Items (<?= $voidStats['void_count'] ?? 0 ?>)</div>
                <div class="display-6 fw-bold">ZMW <?= number_format($voidStats['total_voided'] ?? 0, 2) ?></div>
            </div>
        </div>
    </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3 no-print">
    <li class="nav-item">
        <a class="nav-link <?= $reportType == 'sales' ? 'active fw-bold' : '' ?>" href="index.php?page=reports&type=sales&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>">Sales Ledger</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-primary <?= $reportType == 'itemized' ? 'active fw-bold' : '' ?>" href="index.php?page=reports&type=itemized&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>"><i class="bi bi-list-columns-reverse"></i> Live Itemized Sales</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportType == 'product' ? 'active fw-bold' : '' ?>" href="index.php?page=reports&type=product&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>">Product Performance</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportType == 'audit' ? 'active fw-bold' : '' ?>" href="index.php?page=reports&type=audit&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>">Audit & Refunds</a>
    </li>
</ul>

<div class="table-responsive p-3 bg-white">
    <table id="reportsTable" class="table table-striped table-hover mb-0 align-middle w-100">
        
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
                    <?php if($row['final_total'] < 0 || strtolower($row['payment_status'])=='refunded'): ?><span class="badge bg-danger">REFUNDED</span>
                    <?php elseif($row['final_total'] == 0 || strtolower($row['payment_status'])=='voided'): ?><span class="badge bg-dark">VOIDED</span>
                    <?php elseif(strtolower($row['payment_status'])=='paid'): ?><span class="badge bg-success">PAID</span>
                    <?php else: ?><span class="badge bg-warning text-dark">PENDING</span><?php endif; ?>
                </td>
                <td class="text-end text-muted"><?= $row['tip_amount'] > 0 ? number_format($row['tip_amount'], 2) : '-' ?></td>
                <td class="text-end fw-bold">
                    <button type="button" onclick="viewSaleBreakdown(<?= $row['id'] ?>)" class="btn btn-link p-0 fw-bold text-decoration-underline text-primary fs-6 shadow-none" title="View Item Breakdown">
                        ZMW <?= number_format($row['final_total'], 2) ?>
                    </button>
                </td>
                <td class="text-end no-print">
                    <button onclick="showReceiptModal('index.php?page=receipt&sale_id=<?= $row['id'] ?>')" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i></button>
                    
                    <?php if(strtolower($row['payment_status']) == 'paid' && in_array($_SESSION['role'], ['admin', 'manager', 'dev'])): ?>
                    <a href="index.php?page=refund_items&sale_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger ms-1" title="Refund"><i class="bi bi-arrow-counterclockwise"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif($reportType === 'itemized'): ?>
        <thead class="table-dark">
            <tr>
                <th>Time</th>
                <th>Receipt</th>
                <th>Product</th>
                <th>Cashier</th>
                <th>Station</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Line Total</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($reportData as $row): ?>
            <tr>
                <td data-sort="<?= $row['created_at'] ?>"><?= date('M d, H:i:s', strtotime($row['created_at'])) ?></td>
                <td>
                    <button type="button" onclick="viewSaleBreakdown(<?= $row['sale_id'] ?>)" class="btn btn-link p-0 fw-bold text-decoration-underline shadow-none">
                        #<?= $row['sale_id'] ?>
                    </button>
                </td>
                <td class="fw-bold text-primary"><?= htmlspecialchars($row['product_name']) ?></td>
                <td><?= htmlspecialchars($row['cashier']) ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['location_name']) ?></span></td>
                <td class="text-center fw-bold"><?= $row['quantity'] ?></td>
                <td class="text-end fw-bold <?= $row['line_total'] < 0 ? 'text-danger' : 'text-success' ?>">ZMW <?= number_format($row['line_total'], 2) ?></td>
                <td>
                    <?php if($row['item_status'] == 'voided' || (isset($row['line_total']) && $row['line_total'] == 0)): ?> <span class="badge bg-dark">VOID</span>
                    <?php elseif($row['item_status'] == 'refunded' || (isset($row['line_total']) && $row['line_total'] < 0)): ?> <span class="badge bg-danger">REFUNDED</span>
                    <?php elseif(strtolower($row['payment_status']) == 'paid'): ?> <span class="badge bg-success">PAID</span>
                    <?php else: ?> <span class="badge bg-warning text-dark">PENDING</span>
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

<div class="modal fade" id="saleBreakdownModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg border-info border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-receipt text-info me-2"></i> Sale Breakdown <span id="modalSaleIdBadge" class="badge bg-secondary ms-2"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end pe-4">Price</th>
                        </tr>
                    </thead>
                    <tbody id="saleBreakdownBody">
                        <tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border text-info spinner-border-sm me-2"></div> Loading items...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js">
// --- SMART PRINT FUNCTION ---
function printMasterReport() {
    if ($.fn.DataTable.isDataTable('#reportsTable')) {
        let table = $('#reportsTable').DataTable();
        let currentLen = table.page.len();
        
        // Expand the table to show ALL rows before printing
        table.page.len(-1).draw(); 
        
        // Wait a split second for the browser to render the long table, then print
        setTimeout(() => {
            window.print();
            // Restore the pagination back to normal after printing is done
            table.page.len(currentLen).draw(); 
        }, 500);
    } else {
        window.print();
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js">
// --- SMART PRINT FUNCTION ---
function printMasterReport() {
    if ($.fn.DataTable.isDataTable('#reportsTable')) {
        let table = $('#reportsTable').DataTable();
        let currentLen = table.page.len();
        
        // Expand the table to show ALL rows before printing
        table.page.len(-1).draw(); 
        
        // Wait a split second for the browser to render the long table, then print
        setTimeout(() => {
            window.print();
            // Restore the pagination back to normal after printing is done
            table.page.len(currentLen).draw(); 
        }, 500);
    } else {
        window.print();
    }
}
</script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js">
// --- SMART PRINT FUNCTION ---
function printMasterReport() {
    if ($.fn.DataTable.isDataTable('#reportsTable')) {
        let table = $('#reportsTable').DataTable();
        let currentLen = table.page.len();
        
        // Expand the table to show ALL rows before printing
        table.page.len(-1).draw(); 
        
        // Wait a split second for the browser to render the long table, then print
        setTimeout(() => {
            window.print();
            // Restore the pagination back to normal after printing is done
            table.page.len(currentLen).draw(); 
        }, 500);
    } else {
        window.print();
    }
}
</script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js">
// --- SMART PRINT FUNCTION ---
function printMasterReport() {
    if ($.fn.DataTable.isDataTable('#reportsTable')) {
        let table = $('#reportsTable').DataTable();
        let currentLen = table.page.len();
        
        // Expand the table to show ALL rows before printing
        table.page.len(-1).draw(); 
        
        // Wait a split second for the browser to render the long table, then print
        setTimeout(() => {
            window.print();
            // Restore the pagination back to normal after printing is done
            table.page.len(currentLen).draw(); 
        }, 500);
    } else {
        window.print();
    }
}
</script>

<script>
    flatpickr(".datepicker", { dateFormat: "Y-m-d", allowInput: true });
    
    $(document).ready(function() {
        $('#reportsTable').DataTable({
            "pageLength": 50,
            "order": [], // Use native sorting from backend (Newest first)
            "language": {
                "search": "",
                "searchPlaceholder": "Live filter..."
            },
            "dom": '<"d-flex justify-content-between align-items-center mb-3"<"me-auto"l><"ms-auto"f>>rt<"d-flex justify-content-between mt-3"ip>'
        });
        $('.dataTables_filter input').addClass('form-control shadow-sm border-secondary');
        $('.dataTables_length select').addClass('form-select shadow-sm border-secondary');
    });

    function showReceiptModal(url) { document.getElementById('reportFrame').src = url; new bootstrap.Modal(document.getElementById('reportModal')).show(); }
    
    // AJAX Function to fetch and display the item breakdown
    function viewSaleBreakdown(saleId) {
        document.getElementById('modalSaleIdBadge').innerText = '#' + saleId;
        document.getElementById('saleBreakdownBody').innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border text-info spinner-border-sm me-2"></div> Fetching data...</td></tr>';
        
        let modal = new bootstrap.Modal(document.getElementById('saleBreakdownModal'));
        modal.show();

        fetch('index.php?page=reports', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'fetch_sale_details=1&sale_id=' + saleId
        })
        .then(r => r.json())
        .then(data => {
            let tbody = document.getElementById('saleBreakdownBody');
            if (data.status === 'success') {
                tbody.innerHTML = '';
                if (data.items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No items found for this sale.</td></tr>';
                    return;
                }
                data.items.forEach(i => {
                    let statusBadge = '';
                    if (i.status === 'voided') statusBadge = ' <span class="badge bg-danger">VOID</span>';
                    else if (i.status === 'refunded') statusBadge = ' <span class="badge bg-warning text-dark">REFUNDED</span>';
                    
                    let priceClass = parseFloat(i.price) < 0 ? 'text-danger' : 'text-dark';
                    
                    tbody.innerHTML += `
                        <tr>
                            <td class="ps-4 fw-bold text-secondary">${i.name} ${statusBadge}</td>
                            <td class="text-center fw-bold">${i.quantity}</td>
                            <td class="text-end pe-4 fw-bold ${priceClass}">ZMW ${parseFloat(i.price * i.quantity).toFixed(2)}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="3" class="text-danger text-center py-3">Error: ${data.msg}</td></tr>`;
            }
        }).catch(err => {
            document.getElementById('saleBreakdownBody').innerHTML = '<tr><td colspan="3" class="text-danger text-center py-3">Network error loading details.</td></tr>';
        });
    }

    <?php if(isset($_SESSION['swal_msg'])): ?>
    Swal.fire({
        icon: <?= json_encode($_SESSION['swal_type']) ?>,
        title: <?= json_encode($_SESSION['swal_msg']) ?>,
        showConfirmButton: false,
        timer: 2000
    });
    <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
    <?php endif; ?>

// --- SMART PRINT FUNCTION ---
function printMasterReport() {
    if ($.fn.DataTable.isDataTable('#reportsTable')) {
        let table = $('#reportsTable').DataTable();
        let currentLen = table.page.len();
        
        // Expand the table to show ALL rows before printing
        table.page.len(-1).draw(); 
        
        // Wait a split second for the browser to render the long table, then print
        setTimeout(() => {
            window.print();
            // Restore the pagination back to normal after printing is done
            table.page.len(currentLen).draw(); 
        }, 500);
    } else {
        window.print();
    }
}
</script>
</body>
</html>
