<?php
$tier = defined('LICENSE_TIER') ? LICENSE_TIER : 'lite';
?>
<style>
    @media print {
        body * { visibility: hidden; }
        #printable-catalog, #printable-catalog * { visibility: visible; }
        #printable-catalog { position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; }
        .no-print, .dataTables_length, .dataTables_info, .dataTables_paginate, .custom-filters { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        tr { page-break-inside: avoid; }
    }
</style>

<div id="printable-catalog">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3>📦 Product Catalog 
            <?php if ($tier === 'enterprise'): ?>
            <span class="badge bg-primary fs-6 ms-2 shadow-sm"><i class="bi bi-shield-check"></i> ZRA Ready</span>
            <?php endif; ?>
        </h3>
        <div class="no-print">
            <button class="btn btn-warning fw-bold me-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload"></i> Import CSV</button>
            <button onclick="exportToExcel()" class="btn btn-success fw-bold me-2 shadow-sm"><i class="bi bi-file-earmark-excel"></i> Export Excel</button>
            <button onclick="printCatalog()" class="btn btn-dark fw-bold me-2 shadow-sm"><i class="bi bi-printer"></i> Print Catalog</button>
            <button class="btn btn-outline-primary fw-bold me-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#catModal"><i class="bi bi-folder-plus"></i> New Category</button>
            <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#prodModal"><i class="bi bi-plus-circle"></i> New Product</button>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3 bg-white custom-filters">
        <div class="card-body py-3">
            <div class="row g-2">
                <div class="col-md-2">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-primary text-primary fw-bold"><i class="bi bi-search"></i></span>
                        <input type="text" id="customSearch" class="form-control border-primary" placeholder="Search...">
                    </div>
                </div>
                <div class="col-md-2">
                    <select id="filterCategory" class="form-select form-select-sm border-secondary">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= htmlspecialchars($c['name']) ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <?php if ($tier === 'enterprise'): ?>
                <div class="col-md-2">
                    <select id="filterTax" class="form-select form-select-sm border-secondary">
                        <option value="">All Tax Classes</option>
                        <option value="Class A">Class A (16%)</option>
                        <option value="Class B">Class B (0%)</option>
                        <option value="Class C">Class C (Exempt)</option>
                        <option value="Class D">Class D (Excise)</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <input type="text" id="filterUNSPSC" class="form-control form-control-sm border-secondary" placeholder="UNSPSC Code...">
                </div>
                <?php else: ?>
                <div class="col-md-4"></div>
                <?php endif; ?>

                <div class="col-md-2">
                    <select id="filterStatus" class="form-select form-select-sm border-secondary">
                        <option value="">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Disabled">Disabled</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button id="applyFilters" class="btn btn-sm btn-primary flex-grow-1 fw-bold"><i class="bi bi-funnel"></i> Filter</button>
                    <button id="clearFilters" class="btn btn-sm btn-outline-danger flex-grow-1 fw-bold"><i class="bi bi-x-circle"></i> Clear</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 border-top border-primary border-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="catalogTable">
                    <thead class="bg-light text-dark">
                        <tr>
                            <th>Name</th>
                            <th>Category</th>
                            <th>SKU</th>
                            <?php if ($tier === 'enterprise'): ?>
                            <th>Tax Class</th>
                            <th>UNSPSC</th>
                            <?php endif; ?>
                            <th class="text-end">Cost (ZMW)</th>
                            <th class="text-end">Price (ZMW)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $p): ?>
                                <tr>
                                    <td class="fw-bold"><?= htmlspecialchars($p['name']) ?></td>
                                    <td>
                                        <?php if (!empty($p['category_name'])): ?>
                                            <span class="badge bg-secondary"><?= htmlspecialchars($p['category_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($p['sku'])): ?>
                                            <small class="text-muted"><?= htmlspecialchars($p['sku']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    
                                    <?php if ($tier === 'enterprise'): ?>
                                    <td><span class="badge bg-info text-dark">Class <?= htmlspecialchars($p['tax_class'] ?? 'A') ?></span></td>
                                    <td><small class="text-primary fw-bold"><?= htmlspecialchars($p['unspsc_code'] ?? 'None') ?></small></td>
                                    <?php endif; ?>

                                    <td class="text-end text-muted"><?= number_format((float)$p['cost_price'], 2) ?></td>
                                    <td class="text-end fw-bold text-success"><?= number_format((float)$p['price'], 2) ?></td>
                                    <td>
                                        <?php if (!empty($p['is_active'])): ?>
                                            <span class="badge bg-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Disabled</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade no-print" id="catModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-light"><h5 class="modal-title fw-bold">Add Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" action="index.php?page=products"><div class="modal-body"><input type="hidden" name="add_category" value="1"><div class="mb-3"><label class="form-label small fw-bold text-muted">CATEGORY NAME</label><input type="text" name="category_name" class="form-control" required></div></div><div class="modal-footer border-0"><button class="btn btn-primary w-100 fw-bold py-2 shadow-sm">Save Category</button></div></form></div></div>
</div>

<div class="modal fade no-print" id="prodModal" tabindex="-1">
    <div class="modal-dialog modal-lg"><div class="modal-content border-primary border-top border-4"><div class="modal-header bg-light"><h5 class="modal-title fw-bold">Add Product</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" action="index.php?page=products"><div class="modal-body"><input type="hidden" name="save_product" value="1"><div class="row mb-3"><div class="col-8"><label class="form-label small fw-bold text-muted">PRODUCT NAME</label><input type="text" name="name" class="form-control fw-bold" required></div><div class="col-4"><label class="form-label small fw-bold text-muted">SKU (Optional)</label><input type="text" name="sku" class="form-control" placeholder="e.g. DRINK-001"></div></div><div class="row mb-3"><div class="col-6"><label class="form-label small fw-bold text-muted">CATEGORY</label><select name="category_id" class="form-select"><option value="">-- None --</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select></div><div class="col-6"><label class="form-label small fw-bold text-muted">UNIT (e.g., kg, bottle)</label><input type="text" name="unit" class="form-control" value="unit"></div></div>
        
        <?php if ($tier === 'enterprise'): ?>
        <hr class="text-muted"><div class="row mb-3"><div class="col-6"><label class="form-label small fw-bold text-muted">ZRA TAX CLASS</label><select name="tax_class" class="form-select border-primary shadow-sm bg-primary bg-opacity-10 fw-bold"><option value="A" selected>Class A - Standard (16%)</option><option value="B">Class B - Zero Rated (0%)</option><option value="C">Class C - Exempt</option><option value="D">Class D - Excise</option></select></div><div class="col-6"><label class="form-label small fw-bold text-muted">UNSPSC CODE (Optional)</label><input type="text" name="unspsc_code" class="form-control border-primary shadow-sm" placeholder="e.g. 50191500"></div></div>
        <?php else: ?>
        <input type="hidden" name="tax_class" value="A">
        <input type="hidden" name="unspsc_code" value="">
        <?php endif; ?>

        <div class="row mb-3"><div class="col-6"><label class="form-label small fw-bold text-muted">COST PRICE (ZMW)</label><input type="number" step="0.01" name="cost_price" class="form-control text-danger" placeholder="0.00"></div><div class="col-6"><label class="form-label small fw-bold text-muted">SELLING PRICE (ZMW)</label><input type="number" step="0.01" name="price" class="form-control text-success fw-bold" placeholder="0.00"></div></div></div><div class="modal-footer border-0"><button class="btn btn-primary w-100 fw-bold py-2 shadow-sm"><i class="bi bi-save"></i> Save Product</button></div></form></div></div>
</div>

<div class="modal fade no-print" id="importModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content border-warning border-top border-4"><div class="modal-header bg-light"><h5 class="modal-title fw-bold"><i class="bi bi-cloud-upload"></i> Import Products (CSV)</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" action="index.php?page=products" enctype="multipart/form-data"><div class="modal-body"><input type="hidden" name="import_csv" value="1"><div class="alert alert-info small"><strong>Expected CSV Columns (in exact order):</strong><br>1. Name<br>2. Category<br>3. SKU<br>4. Unit (e.g., unit, kg, bottle)<br>5. Cost Price<br>6. Selling Price<br>
        
        <?php if ($tier === 'enterprise'): ?>
        <span class="text-primary fw-bold">7. ZRA Tax Class (A, B, C, or D)</span><br><span class="text-primary fw-bold">8. UNSPSC Code (8-digit number)</span><br>
        <?php endif; ?>

        <br><em>Note: The system will automatically create missing categories and update existing products to avoid duplicates!</em></div><div class="mb-3"><label class="form-label fw-bold text-muted small">SELECT CSV FILE</label><input type="file" name="csv_file" class="form-control" accept=".csv" required></div></div><div class="modal-footer border-0"><button class="btn btn-warning w-100 fw-bold py-2 shadow-sm">Upload & Process Data</button></div></form></div></div>
</div>

<link href="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.0/dist/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net@1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/datatables.net-bs5@1.13.6/js/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#catalogTable')) {
            $('#catalogTable').DataTable().destroy();
        }

        var table = $('#catalogTable').DataTable({
            "pageLength": 25,
            "lengthMenu": [[25, 50, 100, 250, 500, -1], [25, 50, 100, 250, 500, "All"]],
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6">>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            "language": {
                "emptyTable": "No products found. Add your first product or use the Import CSV button."
            }
        });
        
        $('.dataTables_length select').addClass('form-select form-select-sm d-inline-block w-auto border-secondary shadow-sm');
        
        $('#applyFilters').on('click', function(e) {
            e.preventDefault();
            var searchVal = $('#customSearch').val();
            var catVal = $('#filterCategory').val();
            var statusVal = $('#filterStatus').val();

            table.search(searchVal);
            table.column(1).search(catVal);
            
            // Adjust column indices based on tier
            <?php if ($tier === 'enterprise'): ?>
            var taxVal = $('#filterTax').val();
            var unspscVal = $('#filterUNSPSC').val();
            table.column(3).search(taxVal);
            table.column(4).search(unspscVal);
            table.column(7).search(statusVal);
            <?php else: ?>
            table.column(5).search(statusVal);
            <?php endif; ?>
            
            table.draw();
        });

        $('.custom-filters input').on('keypress', function(e) {
            if (e.which === 13) { 
                e.preventDefault();
                $('#applyFilters').click();
            }
        });

        $('#clearFilters').on('click', function(e) {
            e.preventDefault();
            $('#customSearch, #filterUNSPSC').val('');
            $('#filterCategory, #filterTax, #filterStatus').val('');
            table.search('').columns().search('').draw(); 
        });
    });

    function printCatalog() {
        var table = $('#catalogTable').DataTable();
        var currentPageLength = table.page.len(); 
        table.page.len(-1).draw(); 
        setTimeout(function() {
            window.print();
            table.page.len(currentPageLength).draw(); 
        }, 300);
    }

    function exportToExcel() {
        let table = $('#catalogTable').DataTable();
        let headers = [];
        $('#catalogTable thead th').each(function() {
            headers.push('"' + $(this).text().trim() + '"');
        });
        let csv = [headers.join(',')];
        
        table.rows({ search: 'applied' }).every(function() {
            let rowData = [];
            let node = this.node(); 
            $(node).find('td').each(function() {
                let text = $(this).text().trim().replace(/"/g, '""'); 
                rowData.push('"' + text + '"');
            });
            csv.push(rowData.join(','));
        });
        
        let csvContent = csv.join('\n');
        let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        let url = URL.createObjectURL(blob);
        let link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", "Product_Catalog_" + new Date().toISOString().slice(0,10) + ".csv");
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
</script>
