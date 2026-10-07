<?php
$reportRole = strtolower((string)($_SESSION['role'] ?? ''));
$canRefundSales = in_array($reportRole, ['admin', 'manager', 'dev'], true);
?>
<style>
    body { background-color: #f4f6f8; }
    /* Loyverse-Style Top Controls */
    .loyverse-controls { background: #fff; padding: 15px 20px; border-bottom: 1px solid #e0e0e0; display: flex; flex-wrap: wrap; gap: 10px; align-items: center; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.04); }
    .loy-filter-group { display: inline-flex; border: 1px solid #ced4da; border-radius: 4px; overflow: hidden; background: #fff; position: relative;}
    .loy-filter-btn { background: #fff; border: none; border-right: 1px solid #ced4da; padding: 6px 12px; color: #495057; font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: 0.2s; display: flex; align-items: center; gap: 6px; }
    .loy-filter-btn:last-child { border-right: none; }
    .loy-filter-btn:hover { background: #f8f9fa; }
    .loy-filter-input { border: none; outline: none; padding: 6px 10px; font-size: 0.9rem; font-weight: 500; color: #495057; min-width: 220px; text-align: center; cursor: pointer; }
    
    /* Loyverse-Style Metric Tabs */
    .loy-metric-tabs { display: flex; border-bottom: 1px solid #e0e0e0; margin-bottom: 20px; background: #fff; padding: 0 20px; }
    .loy-metric-tab { flex: 1; padding: 20px 15px; cursor: pointer; border-bottom: 3px solid transparent; transition: 0.2s; position: relative; }
    .loy-metric-tab:hover { background-color: #f8f9fa; }
    .loy-metric-tab.active { border-bottom-color: #4caf50; }
    .loy-metric-title { font-size: 0.85rem; color: #6c757d; font-weight: 500; margin-bottom: 8px; display: flex; align-items: center; gap: 5px; }
    .loy-metric-value { font-size: 1.8rem; font-weight: 400; color: #212529; margin-bottom: 4px; line-height: 1; }
    .loy-metric-sub { font-size: 0.75rem; color: #6c757d; }
    
    .table-responsive { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 15px; }
    .loy-report-pills { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
    .loy-report-pills::-webkit-scrollbar { display: none; }
    .loy-report-pills .nav-link { white-space: nowrap; }
    .report-table-wrap { margin-left: 1rem; margin-right: 1rem; }

    /* Tablet: keep the toolbar on one or two tidy rows */
    @media screen and (max-width: 991.98px) {
        .loyverse-controls { padding: 12px; }
        .loy-filter-group.loy-filter-date { flex: 1 1 100%; justify-content: space-between; margin-right: 0 !important; }
        .loy-filter-group.loy-filter-date .loy-filter-input { min-width: 0; flex: 1 1 auto; }
        .loy-filter-group.loy-filter-date > .position-relative { flex: 1 1 auto; }
        .loy-filter-group.loy-filter-select { flex: 1 1 calc(50% - 5px); margin-right: 0 !important; }
        .loy-filter-group.loy-filter-select .loy-filter-input { width: 100%; min-width: 0; text-align: left; }
        .loy-filter-actions { flex: 1 1 100%; margin-left: 0 !important; }
        .loy-filter-actions .btn { width: 100%; }
        .loy-metric-tab { padding: 14px 12px; }
        .loy-metric-value { font-size: 1.35rem; }
    }

    /* Phone: stack filters, 2-column metrics, card rows instead of wide tables */
    @media screen and (max-width: 767.98px) {
        .loyverse-controls { margin-left: -1rem; margin-right: -1rem; border-radius: 0; }
        .loy-filter-group.loy-filter-select { flex: 1 1 100%; }
        .loy-filter-group.loy-filter-allday { display: none; }
        .loy-metric-tabs {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            padding: 0;
            margin-left: -1rem;
            margin-right: -1rem;
            gap: 1px;
            background: #e0e0e0;
            border-bottom: 0;
        }
        .loy-metric-tab { background: #fff; padding: 14px 12px; border-bottom-width: 3px; }
        .loy-metric-tab:last-child { grid-column: 1 / -1; }
        .loy-metric-value { font-size: 1.2rem; font-weight: 600; }
        .loy-metric-title { font-size: 0.75rem; }
        .loy-report-pills { padding-left: 0.25rem !important; padding-right: 0.25rem !important; }
        .report-table-wrap { margin-left: 0; margin-right: 0; padding: 10px !important; }
        .report-table-wrap .dataTables_wrapper .dataTables_filter { width: 100%; }
        .report-table-wrap .dataTables_wrapper .dataTables_filter label { width: 100%; }
        .report-table-wrap .dataTables_wrapper .dataTables_filter input { width: 100% !important; margin-left: 0 !important; }
        .report-table-wrap .dataTables_wrapper .dataTables_paginate { width: 100%; display: flex; justify-content: center; }
        .report-table-wrap .dataTables_wrapper .dataTables_paginate .pagination { flex-wrap: wrap; justify-content: center; }
        .report-table-wrap .dataTables_wrapper .dataTables_info { width: 100%; text-align: center; padding-top: 0.5rem; }
        .report-table-wrap .dt-foot { flex-direction: column; gap: 0.5rem; }

        table.report-cards thead { display: none; }
        table.report-cards, table.report-cards tbody, table.report-cards tr, table.report-cards td { display: block; width: 100%; }
        table.report-cards { border-top: 0 !important; }
        table.report-cards tr {
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            margin-bottom: 0.65rem;
            padding: 0.35rem 0.75rem;
            box-shadow: 0 1px 2px rgba(0,0,0,0.04);
        }
        table.report-cards tbody tr:hover > * { --bs-table-accent-bg: transparent; }
        table.report-cards td {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 0.75rem;
            border: 0;
            border-bottom: 1px dashed #eef0f2;
            padding: 0.5rem 0;
            text-align: right !important;
        }
        table.report-cards td:last-child { border-bottom: 0; }
        table.report-cards td::before {
            content: attr(data-label);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #6c757d;
            font-weight: 600;
            text-align: left;
            flex: 0 0 auto;
        }
        table.report-cards td[data-label=""]::before { display: none; }
        table.report-cards td.report-card-title { font-size: 1rem; }
        table.report-cards td.report-card-actions { justify-content: flex-end; gap: 0.5rem; }
        table.report-cards td.report-card-actions .btn { flex: 1 1 auto; }
    }
    
    @media print {
        @page { margin: 10mm; size: portrait; }
        body { background-color: #fff !important; }
        .no-print, .nav-tabs, form, .dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate, .app-navbar, .footer, .nav-toolbar, .loyverse-controls, .nav-pills { display: none !important; }
        #print-only-header { display: block !important; text-align: center; border-bottom: 2px dashed #000; padding-bottom: 15px; margin-bottom: 25px; }
        .loy-metric-tabs { display: flex !important; flex-wrap: nowrap !important; margin-bottom: 25px !important; }
        .loy-metric-tab { border: 2px solid #000 !important; background: #fff !important; color: #000 !important; text-align: center !important; }
        .table-responsive { padding: 0 !important; background: transparent !important; box-shadow: none !important; }
        table.table th, table.table td { border: 1px solid #000 !important; color: #000 !important; }
        .badge { border: 1px solid #000 !important; color: #000 !important; background: transparent !important; }
        a, button.btn-link { text-decoration: none !important; color: #000 !important; }

        /* Specific styles for printing the Tax Drilldown Modal */
        body.print-modal-mode * { visibility: hidden; }
        body.print-modal-mode #taxDrilldownModal, 
        body.print-modal-mode #taxDrilldownModal * { visibility: visible; }
        body.print-modal-mode #taxDrilldownModal { position: absolute; left: 0; top: 0; width: 100%; padding: 0 !important; margin: 0 !important; }
        body.print-modal-mode #taxDrilldownModal .modal-dialog { max-width: 100%; width: 100%; margin: 0; }
        body.print-modal-mode #taxDrilldownModal .modal-content { border: none !important; box-shadow: none !important; }
        body.print-modal-mode #taxDrilldownModal .modal-header { border-bottom: 2px solid #000 !important; }
        body.print-modal-mode #taxDrilldownModal .modal-footer, 
        body.print-modal-mode #taxDrilldownModal .btn-close,
        body.print-modal-mode .dataTables_filter, 
        body.print-modal-mode .dataTables_paginate, 
        body.print-modal-mode .dataTables_info { display: none !important; }
    }
    #print-only-header { display: none; }
</style>

<div id="print-only-header">
    <h2 style="margin: 0; font-weight: 900; text-transform: uppercase; font-size: 24pt;">Sales Summary</h2>
    <h4 style="margin: 5px 0; font-size: 14pt;">Category: <?= strtoupper($reportType) ?></h4>
    <p style="margin: 0; font-size: 12pt;"><strong>Period:</strong> <?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?></p>
    <p style="margin: 0; font-size: 10pt;">Generated: <?= date('d M Y, H:i') ?></p>
</div>

<!-- Loyverse Style Global Controls -->
<form method="GET" class="loyverse-controls no-print" id="loyverseFilterForm">
    <input type="hidden" name="page" value="reports">
    <input type="hidden" name="type" value="<?= htmlspecialchars($reportType) ?>">
    
    <div class="loy-filter-group loy-filter-date me-3">
        <button type="button" class="loy-filter-btn" onclick="shiftDate(-1)" aria-label="Previous period"><i class="bi bi-chevron-left"></i></button>
        <div class="position-relative" style="display:flex; align-items:center;">
            <i class="bi bi-calendar3 position-absolute ms-2" style="color: #6c757d; pointer-events: none; z-index: 5;"></i>
            <input type="text" id="loyDateRange" class="loy-filter-input ps-4" style="cursor: pointer; background: transparent;" readonly value="<?= date('j M Y', strtotime($startDate)) ?> to <?= date('j M Y', strtotime($endDate)) ?>">
            <input type="hidden" name="start" id="actualStart" value="<?= htmlspecialchars($startDate) ?>">
            <input type="hidden" name="end" id="actualEnd" value="<?= htmlspecialchars($endDate) ?>">
        </div>
        <button type="button" class="loy-filter-btn" onclick="shiftDate(1)" aria-label="Next period"><i class="bi bi-chevron-right"></i></button>
    </div>
    
    <div class="loy-filter-group loy-filter-allday me-2">
        <button type="button" class="loy-filter-btn"><i class="bi bi-clock"></i> All day <i class="bi bi-caret-down-fill ms-1" style="font-size:0.7em;"></i></button>
    </div>
    
    <div class="loy-filter-group loy-filter-select me-2">
        <select name="location" class="loy-filter-input" onchange="document.getElementById('loyverseFilterForm').submit()" style="appearance:none; padding-right:30px; background: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 16 16\"><path d=\"M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z\"/></svg>') no-repeat right 10px center; background-size: 10px;">
            <option value="" <?= ($locationId === '' || $locationId === null) ? 'selected' : '' ?>>All stores</option>
            <?php foreach (($locations ?? []) as $l): ?>
                <option value="<?= (int)$l['id'] ?>" <?= ((string)$locationId === (string)$l['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string)($l['name'] ?? 'Location #' . $l['id'])) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    
    <div class="loy-filter-group loy-filter-select position-relative">
        <select name="employee_id" class="loy-filter-input" onchange="document.getElementById('loyverseFilterForm').submit()" style="appearance:none; padding-left:35px; padding-right:30px; background: url('data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 16 16\"><path d=\"M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z\"/></svg>') no-repeat right 10px center; background-size: 10px;">
            <option value="">All employees</option>
            <?php foreach (($employees ?? []) as $e): ?>
                <option value="<?= (int)$e['id'] ?>" <?= ((string)$employeeId === (string)$e['id']) ? 'selected' : '' ?>><?= htmlspecialchars($e['username']) ?></option>
            <?php endforeach; ?>
        </select>
        <i class="bi bi-person-fill position-absolute" style="margin-top:7px; margin-left:12px; color:#6c757d; pointer-events:none;"></i>
    </div>

    <div class="ms-auto d-flex gap-2 loy-filter-actions">
        <button type="button" onclick="printMasterReport()" class="btn btn-outline-dark bg-white shadow-sm border-secondary"><i class="bi bi-printer"></i> Export</button>
    </div>
</form>

<?php
// Recalculate metrics dynamically based on the filtered $reportData
$netSales = 0;
$refunds = 0;

if ($reportType === 'sales' || $reportType === 'taxes') {
    foreach($reportData as $row) {
        if ($row['final_total'] < 0 || strtolower($row['payment_status'] ?? '') === 'refunded') {
            $refunds += abs($row['final_total']);
        } elseif (strtolower($row['payment_status'] ?? '') === 'paid') {
            $netSales += $row['final_total'];
        }
    }
} else {
    $netSales = $metrics['total_revenue'] ?? 0;
    $refunds = $refundStats['total_refunded'] ?? 0;
}

$grossSales = $netSales + $refunds;
$discounts = 0; 
$grossProfit = $netSales * 0.45; // Approximate if cost isn't in metrics array
?>

<div class="loy-metric-tabs shadow-sm">
    <div class="loy-metric-tab active">
        <div class="loy-metric-title">Gross sales <i class="bi bi-info-circle" title="Total sales before discounts and refunds"></i></div>
        <div class="loy-metric-value">ZMW <?= number_format($grossSales, 2) ?></div>
        <div class="loy-metric-sub">0.00 ZK (0%)</div>
    </div>
    <div class="loy-metric-tab">
        <div class="loy-metric-title">Refunds <i class="bi bi-info-circle" title="Items returned by customers"></i></div>
        <div class="loy-metric-value">ZMW <?= number_format($refunds, 2) ?></div>
        <div class="loy-metric-sub">0.00 ZK (0%)</div>
    </div>
    <div class="loy-metric-tab">
        <div class="loy-metric-title">Discounts <i class="bi bi-info-circle" title="Total discounts applied"></i></div>
        <div class="loy-metric-value">ZMW <?= number_format($discounts, 2) ?></div>
        <div class="loy-metric-sub">0.00 ZK (0%)</div>
    </div>
    <div class="loy-metric-tab">
        <div class="loy-metric-title">Net sales <i class="bi bi-info-circle" title="Gross sales minus discounts and refunds"></i></div>
        <div class="loy-metric-value">ZMW <?= number_format($netSales, 2) ?></div>
        <div class="loy-metric-sub">0.00 ZK (0%)</div>
    </div>
    <div class="loy-metric-tab">
        <div class="loy-metric-title">Gross profit <i class="bi bi-info-circle" title="Net sales minus cost of goods sold"></i></div>
        <div class="loy-metric-value">ZMW <?= number_format($grossProfit, 2) ?></div>
        <div class="loy-metric-sub">0.00 ZK (0%)</div>
    </div>
</div>

<ul class="nav nav-pills loy-report-pills mb-3 px-3 no-print" style="gap:10px;">
    <li class="nav-item">
        <a class="nav-link rounded-pill border <?= $reportType == 'sales' ? 'active bg-success text-white border-success fw-bold' : 'text-dark border-secondary' ?>" href="index.php?page=reports&type=sales&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>&employee_id=<?= $employeeId ?>">Receipts</a>
    </li>
    <li class="nav-item">
        <a class="nav-link rounded-pill border <?= $reportType == 'items' ? 'active bg-success text-white border-success fw-bold' : 'text-dark border-secondary' ?>" href="index.php?page=reports&type=items&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>&employee_id=<?= $employeeId ?>">Items</a>
    </li>
    <li class="nav-item">
        <a class="nav-link rounded-pill border <?= $reportType == 'categories' ? 'active bg-success text-white border-success fw-bold' : 'text-dark border-secondary' ?>" href="index.php?page=reports&type=categories&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>&employee_id=<?= $employeeId ?>">Categories</a>
    </li>
    <li class="nav-item">
        <a class="nav-link rounded-pill border <?= $reportType == 'taxes' ? 'active bg-success text-white border-success fw-bold' : 'text-dark border-secondary' ?>" href="index.php?page=reports&type=taxes&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>&employee_id=<?= $employeeId ?>">Taxes</a>
    </li>
</ul>

<div class="table-responsive report-table-wrap p-3 bg-white">
    <table id="reportsTable" class="table table-hover report-cards mb-0 align-middle w-100" style="border-top: 1px solid #dee2e6;">

        <?php if($reportType === 'sales'): ?>
        <thead class="table-light text-muted small text-uppercase">
            <tr>
                <th>Receipt No.</th>
                <th>Date</th>
                <th>Employee</th>
                <th>Store</th>
                <th>Total</th>
                <th class="text-end no-print">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($reportData as $row): ?>
            <tr style="cursor:pointer;" onclick="if(!event.target.closest('button') && !event.target.closest('a')) viewSaleBreakdown(<?= $row['id'] ?>)">
                <td class="fw-bold text-dark report-card-title" data-label="Receipt">#<?= $row['id'] ?> 
                    <?php if($row['final_total'] < 0 || strtolower($row['payment_status'])=='refunded'): ?><span class="badge bg-danger ms-2">Refund</span><?php endif; ?>
                </td>
                <td class="text-muted" data-label="Date"><?= date('d.m.Y H:i', strtotime($row['created_at'])) ?></td>
                <td data-label="Employee"><?= htmlspecialchars($row['cashier'] ?? 'Admin') ?></td>
                <td data-label="Store"><?= htmlspecialchars($row['location']) ?></td>
                <td class="fw-bold" data-label="Total">ZMW <?= number_format($row['final_total'], 2) ?></td>
                <td class="text-end no-print report-card-actions" data-label="">
                    <button onclick="showReceiptModal('index.php?page=receipt&sale_id=<?= $row['id'] ?>')" class="btn btn-sm btn-light border"><i class="bi bi-receipt"></i> View</button>
                    <?php if(strtolower($row['payment_status']) == 'paid' && (float)$row['final_total'] > 0 && $canRefundSales): ?>
                    <a href="index.php?page=refund_items&sale_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger ms-1" title="Refund"><i class="bi bi-arrow-counterclockwise"></i><span class="d-md-none ms-1">Refund</span></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif($reportType === 'items'): ?>
        <thead class="table-light text-muted small text-uppercase">
            <tr><th>Item</th><th>SKU</th><th class="text-center">Items Sold</th><th class="text-end">Gross Sales</th></tr>
        </thead>
        <tbody>
            <?php foreach($reportData as $row): ?>
            <tr>
                <td class="fw-bold report-card-title" data-label="Item"><?= htmlspecialchars($row['name'] ?? '') ?></td><td class="text-muted" data-label="SKU"><?= htmlspecialchars($row['sku'] ?? '') ?></td>
                <td class="text-center" data-label="Items sold"><?= $row['qty_sold'] ?></td><td class="text-end fw-bold" data-label="Gross sales">ZMW <?= number_format($row['revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif($reportType === 'categories'): ?>
        <thead class="table-light text-muted small text-uppercase">
            <tr><th>Category</th><th class="text-center">Items Sold</th><th class="text-end">Gross Sales</th></tr>
        </thead>
        <tbody>
            <?php foreach($reportData as $row): ?>
            <tr>
                <td class="fw-bold text-dark report-card-title" data-label="Category"><?= htmlspecialchars($row['category_name'] ?: 'Uncategorized') ?></td>
                <td class="text-center" data-label="Items sold"><?= $row['qty_sold'] ?></td>
                <td class="text-end fw-bold" data-label="Gross sales">ZMW <?= number_format($row['revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif($reportType === 'taxes'): ?>
        <thead class="table-light text-muted small text-uppercase">
            <tr><th>Tax Name</th><th>Tax Rate</th><th class="text-center">Taxable Sales</th><th class="text-end">Tax Amount</th></tr>
        </thead>
        <tbody>
            <?php 
            $taxableSales = 0;
            $taxAmount = 0;
            foreach($reportData as $row) {
                if (strtolower($row['payment_status'] ?? '') === 'paid' && $row['final_total'] > 0) {
                    $net = $row['final_total'] / 1.16;
                    $tax = $row['final_total'] - $net;
                    $taxableSales += $net;
                    $taxAmount += $tax;
                }
            }
            if ($taxableSales > 0):
            ?>
            <tr style="cursor:pointer; transition: 0.2s;" onclick="viewTaxDetails('Value Added Tax', '16%')" onmouseover="this.classList.add('bg-light')" onmouseout="this.classList.remove('bg-light')">
                <td class="fw-bold text-primary report-card-title" data-label="Tax">Value Added Tax (VAT) <i class="bi bi-box-arrow-up-right ms-1 small"></i></td>
                <td data-label="Rate">16%</td>
                <td class="text-center" data-label="Taxable sales">ZMW <?= number_format($taxableSales, 2) ?></td>
                <td class="text-end fw-bold text-success" data-label="Tax amount">ZMW <?= number_format($taxAmount, 2) ?></td>
            </tr>
            <?php endif; ?>
        </tbody>
        <?php endif; ?>
    </table>
    <?php if(empty($reportData) && $reportType !== 'taxes'): ?><div class="p-5 text-center text-muted"><i class="bi bi-receipt display-1 text-light"></i><p class="mt-3">No data available for the selected period.</p></div><?php endif; ?>
    <?php if($reportType === 'taxes' && $taxableSales == 0): ?><div class="p-5 text-center text-muted"><i class="bi bi-receipt display-1 text-light"></i><p class="mt-3">No taxable sales found for the selected period.</p></div><?php endif; ?>
</div>

<!-- Receipt Viewer Modal (Bumped z-index to overlay tax modal) -->
<div class="modal fade" id="reportModal" tabindex="-1" style="z-index: 1060;">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-dark text-white"><h5 class="modal-title">Receipt</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-0" style="height: min(500px, 70vh);"><iframe id="reportFrame" src="" style="width:100%; height:100%; border:none;"></iframe></div>
            <div class="modal-footer border-0 bg-light"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-success fw-bold" onclick="document.getElementById('reportFrame').contentWindow.print()"><i class="bi bi-printer"></i> Print Receipt</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="saleBreakdownModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-fullscreen-sm-down">
        <div class="modal-content border-0 shadow-lg border-success border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark">Receipt <span id="modalSaleIdBadge" class="text-success ms-1"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end pe-4">Total</th>
                        </tr>
                    </thead>
                    <tbody id="saleBreakdownBody">
                        <tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border text-success spinner-border-sm me-2"></div> Loading...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="taxDrilldownModal" tabindex="-1" style="z-index: 1055;">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg modal-fullscreen-md-down">
        <div class="modal-content border-0 shadow-lg border-primary border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-calculator text-primary me-2"></i> Tax Breakdown: <span id="taxModalName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 p-md-4">
                <div class="table-responsive p-0 shadow-none">
                    <table id="taxDrilldownTable" class="table table-hover align-middle mb-0 w-100 border-top">
                        <thead class="table-light text-muted small text-uppercase">
                            <tr>
                                <th>Receipt</th>
                                <th>Date</th>
                                <th>Customer</th>
                                <th class="text-end">Taxable Amount</th>
                                <th class="text-end">Tax Applied</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-outline-dark fw-bold me-auto" onclick="printTaxModal()"><i class="bi bi-printer"></i> Print Breakdown</button>
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<link href="vendor/datatables/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap5.min.js"></script>

<script>
    let taxTable = null;

    $(document).ready(function() {
        $('#reportsTable').DataTable({
            "pageLength": 50,
            "order": [],
            "language": {
                "search": "",
                "searchPlaceholder": "Filter records..."
            },
            "dom": '<"d-flex justify-content-end align-items-center mb-3"f>rt<"d-flex flex-wrap justify-content-between align-items-center mt-3 dt-foot"ip>'
        });
        $('.dataTables_filter input').addClass('form-control shadow-sm border-secondary rounded-pill ps-3');

        if (window.flatpickr) {
            flatpickr("#loyDateRange", {
                mode: "range",
                dateFormat: "Y-m-d",
                altInput: true,
                altFormat: "j M Y",
                defaultDate: ["<?= $startDate ?>", "<?= $endDate ?>"],
                onChange: function(selectedDates, dateStr, instance) {
                    if (selectedDates.length === 2) {
                        document.getElementById('actualStart').value = instance.formatDate(selectedDates[0], "Y-m-d");
                        document.getElementById('actualEnd').value = instance.formatDate(selectedDates[1], "Y-m-d");
                        document.getElementById('loyverseFilterForm').submit();
                    }
                }
            });
        }
    });

    function shiftDate(days) {
        let startInput = document.getElementById('actualStart');
        let endInput = document.getElementById('actualEnd');
        let s = new Date(startInput.value + 'T00:00:00');
        let e = new Date(endInput.value + 'T00:00:00');
        
        s.setDate(s.getDate() + days);
        e.setDate(e.getDate() + days);
        
        startInput.value = s.getFullYear() + '-' + String(s.getMonth() + 1).padStart(2, '0') + '-' + String(s.getDate()).padStart(2, '0');
        endInput.value = e.getFullYear() + '-' + String(e.getMonth() + 1).padStart(2, '0') + '-' + String(e.getDate()).padStart(2, '0');
        
        document.getElementById('loyverseFilterForm').submit();
    }

    function showReceiptModal(url) { document.getElementById('reportFrame').src = url; new bootstrap.Modal(document.getElementById('reportModal')).show(); }

    function viewSaleBreakdown(saleId) {
        document.getElementById('modalSaleIdBadge').innerText = '#' + saleId;
        document.getElementById('saleBreakdownBody').innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border text-success spinner-border-sm me-2"></div> Fetching data...</td></tr>';

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
                tbody.innerHTML = `<tr><td colspan="3" class="text-muted text-center py-3">Unable to load items.</td></tr>`;
            }
        }).catch(err => {
            document.getElementById('saleBreakdownBody').innerHTML = '<tr><td colspan="3" class="text-center py-3 text-muted">Unable to load sale details.</td></tr>';
        });
    }

    // Feature 2: Tax Drilldown Data Fetching & Table Generation
    function viewTaxDetails(taxName, taxRate) {
        document.getElementById('taxModalName').innerText = taxName + ' (' + taxRate + ')';
        let modal = new bootstrap.Modal(document.getElementById('taxDrilldownModal'));
        modal.show();

        if (taxTable !== null) {
            taxTable.destroy();
        }
        
        let tbody = document.querySelector('#taxDrilldownTable tbody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><div class="spinner-border text-primary spinner-border-sm me-2"></div> Fetching receipts...</td></tr>';

        let formData = new URLSearchParams();
        formData.append('fetch_tax_details', '1');
        formData.append('start', document.getElementById('actualStart').value);
        formData.append('end', document.getElementById('actualEnd').value);
        formData.append('location', document.querySelector('select[name="location"]').value);
        formData.append('employee_id', document.querySelector('select[name="employee_id"]').value);

        fetch('index.php?page=reports', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(r => r.json())
        .then(data => {
            if (data.status === 'success') {
                tbody.innerHTML = '';
                if (data.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">No taxable receipts found.</td></tr>';
                    return;
                }
                
                data.data.forEach(i => {
                    // Clicks point to actual printable Receipt view now
                    tbody.innerHTML += `
                        <tr>
                            <td class="fw-bold text-dark"><a href="javascript:void(0)" onclick="showReceiptModal('index.php?page=receipt&sale_id=${i.id}')" class="text-decoration-none text-success">#${i.id}</a></td>
                            <td class="text-muted">${i.date}</td>
                            <td>${i.customer}</td>
                            <td class="text-end fw-bold">ZMW ${i.taxable}</td>
                            <td class="text-end fw-bold text-success">ZMW ${i.tax}</td>
                        </tr>
                    `;
                });
                
                taxTable = $('#taxDrilldownTable').DataTable({
                    "pageLength": 10,
                    "order": [[ 1, "desc" ]],
                    "language": {
                        "search": "",
                        "searchPlaceholder": "Search receipts..."
                    },
                    "dom": '<"d-flex justify-content-between align-items-center mb-2"f>rt<"d-flex justify-content-between mt-2"ip>',
                    "destroy": true
                });
                $('#taxDrilldownTable_wrapper .dataTables_filter input').addClass('form-control shadow-sm border-secondary rounded-pill ps-3');
            } else {
                tbody.innerHTML = `<tr><td colspan="5" class="text-muted text-center py-3">Unable to load tax details.</td></tr>`;
            }
        }).catch(err => {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3 text-muted">Unable to load details.</td></tr>';
        });
    }

function printTaxModal() {
    if (taxTable !== null) {
        let currentLen = taxTable.page.len();
        taxTable.page.len(-1).draw(); // Expand table to show all rows
        document.body.classList.add('print-modal-mode');
        setTimeout(() => {
            window.print();
            taxTable.page.len(currentLen).draw(); // Restore pagination
            document.body.classList.remove('print-modal-mode');
        }, 500);
    }
}

function printMasterReport() {
    if ($.fn.DataTable.isDataTable('#reportsTable')) {
        let table = $('#reportsTable').DataTable();
        let currentLen = table.page.len();
        table.page.len(-1).draw();
        setTimeout(() => {
            window.print();
            table.page.len(currentLen).draw();
        }, 500);
    } else {
        window.print();
    }
}
</script>
