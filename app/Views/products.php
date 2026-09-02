<?php
$tier = defined('LICENSE_TIER') ? LICENSE_TIER : 'lite';
$isEnt = $tier === 'enterprise';
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
            <?php if ($isEnt): ?>
            <span class="badge bg-primary fs-6 ms-2 shadow-sm"><i class="bi bi-shield-check"></i> ZRA Ready</span>
            <?php endif; ?>
        </h3>
        <div class="no-print">
            <a href="index.php?page=unspsc" class="btn btn-outline-primary fw-bold me-2 shadow-sm"><i class="bi bi-upc-scan"></i> UNSPSC Codes</a>
            <button class="btn btn-warning fw-bold me-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#importModal"><i class="bi bi-upload"></i> Import CSV / Excel</button>
            <a href="index.php?page=products&export=csv" class="btn btn-success fw-bold me-2 shadow-sm"><i class="bi bi-file-earmark-excel"></i> Export Excel</a>
            <button onclick="printCatalog()" class="btn btn-dark fw-bold me-2 shadow-sm"><i class="bi bi-printer"></i> Print Catalog</button>
            <button class="btn btn-outline-primary fw-bold me-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#catModal"><i class="bi bi-folder-plus"></i> New Category</button>
            <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#prodModal"><i class="bi bi-plus-circle"></i> New Product</button>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-3 bg-white custom-filters no-print">
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
                <div class="col-md-2">
                    <select id="filterType" class="form-select form-select-sm border-secondary">
                        <option value="">All Types</option>
                        <option value="item">Item</option>
                        <option value="service">Service</option>
                    </select>
                </div>
                <?php if ($isEnt): ?>
                <div class="col-md-2">
                    <select id="filterTax" class="form-select form-select-sm border-secondary">
                        <option value="">All Tax Classes</option>
                        <option value="Class A">Class A (16%)</option>
                        <option value="Class B">Class B (0%)</option>
                        <option value="Class C">Class C (Exempt)</option>
                        <option value="Class D">Class D (Excise)</option>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-2">
                    <input type="text" id="filterUNSPSC" class="form-control form-control-sm border-secondary" placeholder="UNSPSC...">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <select id="filterStatus" class="form-select form-select-sm border-secondary">
                        <option value="">All Statuses</option>
                        <option value="Active">Active</option>
                        <option value="Disabled">Disabled</option>
                    </select>
                    <button id="applyFilters" class="btn btn-sm btn-primary fw-bold"><i class="bi bi-funnel"></i></button>
                    <button id="clearFilters" class="btn btn-sm btn-outline-danger fw-bold"><i class="bi bi-x"></i></button>
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
                            <th>Unit</th>
                            <th>Type</th>
                            <?php if ($isEnt): ?><th>Tax Class</th><?php endif; ?>
                            <th>UNSPSC</th>
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
                                    <td><small class="text-muted"><?= htmlspecialchars($p['sku'] ?? '-') ?></small></td>
                                    <td><?= htmlspecialchars($p['unit'] ?: 'unit') ?></td>
                                    <td><?= htmlspecialchars($p['type'] ?? 'item') ?></td>
                                    <?php if ($isEnt): ?>
                                    <td><span class="badge bg-info text-dark">Class <?= htmlspecialchars($p['tax_class'] ?? 'A') ?></span></td>
                                    <?php endif; ?>
                                    <td>
                                        <button type="button" class="btn btn-link btn-sm p-0 text-start unspsc-pick no-print"
                                            data-product-id="<?= (int)$p['id'] ?>"
                                            data-product-name="<?= htmlspecialchars($p['name'], ENT_QUOTES) ?>">
                                            <?php if (!empty($p['unspsc_code'])): ?>
                                                <span class="fw-bold"><?= htmlspecialchars($p['unspsc_code']) ?></span>
                                                <?php if (!empty($p['unspsc_name'])): ?>
                                                    <div class="small text-muted"><?= htmlspecialchars($p['unspsc_name']) ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-muted">Assign code</span>
                                            <?php endif; ?>
                                        </button>
                                    </td>
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
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-primary border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=products">
                <div class="modal-body">
                    <input type="hidden" name="save_product" value="1">
                    <div class="row mb-3">
                        <div class="col-8"><label class="form-label small fw-bold text-muted">PRODUCT NAME</label><input type="text" name="name" class="form-control fw-bold" required></div>
                        <div class="col-4"><label class="form-label small fw-bold text-muted">SKU (Optional)</label><input type="text" name="sku" class="form-control" placeholder="e.g. DRINK-001"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4">
                            <label class="form-label small fw-bold text-muted">CATEGORY</label>
                            <select name="category_id" class="form-select"><option value="">-- None --</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="col-4"><label class="form-label small fw-bold text-muted">UNIT</label><input type="text" name="unit" class="form-control" value="unit"></div>
                        <div class="col-4">
                            <label class="form-label small fw-bold text-muted">TYPE</label>
                            <select name="type" class="form-select">
                                <option value="item">Item</option>
                                <option value="service">Service</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <?php if ($isEnt): ?>
                        <div class="col-4">
                            <label class="form-label small fw-bold text-muted">ZRA TAX CLASS</label>
                            <select name="tax_class" class="form-select">
                                <option value="A" selected>Class A - Standard (16%)</option>
                                <option value="B">Class B - Zero Rated (0%)</option>
                                <option value="C">Class C - Exempt</option>
                                <option value="D">Class D - Excise</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="tax_class" value="A">
                        <?php endif; ?>
                        <div class="<?= $isEnt ? 'col-8' : 'col-12' ?>">
                            <label class="form-label small fw-bold text-muted">UNSPSC CODE</label>
                            <input type="hidden" name="unspsc_code" id="newUnspscCode" value="">
                            <input type="search" class="form-control" id="newUnspscSearch" placeholder="Search title, class, or 8-digit code…" autocomplete="off">
                            <div id="newUnspscResults" class="list-group mt-2" style="max-height: 180px; overflow-y: auto;"></div>
                            <div class="small text-muted mt-1" id="newUnspscChosen">Optional — search and select a code.</div>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-6"><label class="form-label small fw-bold text-muted">COST PRICE (ZMW)</label><input type="number" step="0.01" name="cost_price" class="form-control text-danger" placeholder="0.00"></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">SELLING PRICE (ZMW)</label><input type="number" step="0.01" name="price" class="form-control text-success fw-bold" placeholder="0.00"></div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button class="btn btn-primary w-100 fw-bold py-2 shadow-sm"><i class="bi bi-save"></i> Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade no-print" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-warning border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-cloud-upload"></i> Import Products</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=products" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="import_csv" value="1">
                    <div class="alert alert-info small">
                        Use the same columns as <strong>Export Excel</strong>. Header row required:
                        <div class="font-monospace mt-2">Name, Category, SKU, Unit, Type, Cost Price, Selling Price, Tax Class, UNSPSC, Status</div>
                        Type is <code>item</code> or <code>service</code>. Status is <code>Active</code> or <code>Disabled</code>.
                        Existing SKU or name rows are updated. Missing categories are created.
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">SELECT CSV OR EXCEL FILE</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv,.xlsx,.xls" required>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button class="btn btn-warning w-100 fw-bold py-2 shadow-sm">Upload &amp; process</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade no-print" id="unspscAssignModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-primary border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Assign UNSPSC</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">Linking code for <strong id="assignProductName"></strong></p>
                <input type="hidden" id="assignProductId">
                <input type="search" class="form-control" id="assignUnspscSearch" placeholder="Search title, class, or 8-digit code…" autocomplete="off">
                <div id="assignUnspscResults" class="list-group mt-2" style="max-height: 260px; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

<link href="vendor/datatables/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap5.min.js"></script>

<script>
    $(document).ready(function() {
        if ($.fn.DataTable.isDataTable('#catalogTable')) {
            $('#catalogTable').DataTable().destroy();
        }
        var table = $('#catalogTable').DataTable({
            "pageLength": 25,
            "lengthMenu": [[25, 50, 100, 250, 500, -1], [25, 50, 100, 250, 500, "All"]],
            "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6">>rt<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
            "language": { "emptyTable": "No products found. Add a product or import a CSV/Excel file." }
        });
        $('.dataTables_length select').addClass('form-select form-select-sm d-inline-block w-auto border-secondary shadow-sm');

        $('#applyFilters').on('click', function(e) {
            e.preventDefault();
            table.search($('#customSearch').val());
            table.column(1).search($('#filterCategory').val());
            table.column(4).search($('#filterType').val());
            <?php if ($isEnt): ?>
            table.column(5).search($('#filterTax').val());
            table.column(6).search($('#filterUNSPSC').val());
            table.column(9).search($('#filterStatus').val());
            <?php else: ?>
            table.column(5).search($('#filterUNSPSC').val());
            table.column(8).search($('#filterStatus').val());
            <?php endif; ?>
            table.draw();
        });
        $('.custom-filters input').on('keypress', function(e) {
            if (e.which === 13) { e.preventDefault(); $('#applyFilters').click(); }
        });
        $('#clearFilters').on('click', function(e) {
            e.preventDefault();
            $('#customSearch, #filterUNSPSC').val('');
            $('#filterCategory, #filterTax, #filterStatus, #filterType').val('');
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

    function escapeUnspsc(value) {
        const d = document.createElement('div');
        d.textContent = value == null ? '' : String(value);
        return d.innerHTML;
    }

    function bindUnspscSearch(inputEl, resultsEl, onPick) {
        let timer = null;
        inputEl.addEventListener('input', function () {
            clearTimeout(timer);
            const q = inputEl.value.trim();
            if (!q) {
                resultsEl.innerHTML = '';
                return;
            }
            timer = setTimeout(function () {
                fetch('index.php?page=unspsc&ajax=search&q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        resultsEl.innerHTML = '';
                        (data.results || []).forEach(function (item) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action';
                            const path = item.item_cls_path
                                ? '<div class="small text-muted">' + escapeUnspsc(item.item_cls_path) + '</div>'
                                : '';
                            const badge = item.item_cls_lvl_label
                                ? '<span class="badge ' + (item.item_cls_lvl >= 4 ? 'bg-primary' : 'bg-secondary') + '">' + escapeUnspsc(item.item_cls_lvl_label) + '</span>'
                                : '';
                            btn.innerHTML = '<div class="d-flex justify-content-between gap-2 align-items-start"><div><strong class="font-monospace">'
                                + escapeUnspsc(item.item_cls_cd) + '</strong><div>' + escapeUnspsc(item.item_cls_nm || '') + '</div>'
                                + path + '</div>' + badge + '</div>';
                            btn.addEventListener('click', function () { onPick(item); });
                            resultsEl.appendChild(btn);
                        });
                        if (!(data.results || []).length) {
                            resultsEl.innerHTML = '<div class="list-group-item small text-muted">No matching codes. Import the UNSPSC Excel file first.</div>';
                        }
                    });
            }, 250);
        });
    }

    bindUnspscSearch(
        document.getElementById('newUnspscSearch'),
        document.getElementById('newUnspscResults'),
        function (item) {
            document.getElementById('newUnspscCode').value = item.item_cls_cd;
            document.getElementById('newUnspscChosen').textContent = 'Selected: ' + item.item_cls_cd + ' — ' + (item.item_cls_nm || '');
            document.getElementById('newUnspscResults').innerHTML = '';
            document.getElementById('newUnspscSearch').value = item.item_cls_cd + ' ' + (item.item_cls_nm || '');
        }
    );

    const assignModal = document.getElementById('unspscAssignModal');
    document.querySelectorAll('.unspsc-pick').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('assignProductId').value = btn.dataset.productId;
            document.getElementById('assignProductName').textContent = btn.dataset.productName;
            document.getElementById('assignUnspscSearch').value = '';
            document.getElementById('assignUnspscResults').innerHTML = '';
            bootstrap.Modal.getOrCreateInstance(assignModal).show();
            document.getElementById('assignUnspscSearch').focus();
        });
    });
    bindUnspscSearch(
        document.getElementById('assignUnspscSearch'),
        document.getElementById('assignUnspscResults'),
        function (item) {
            const body = new URLSearchParams();
            body.set('assign_unspsc', '1');
            body.set('ajax', '1');
            body.set('product_id', document.getElementById('assignProductId').value);
            body.set('unspsc_code', item.item_cls_cd);
            fetch('index.php?page=unspsc', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: body.toString() })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.status === 'success') {
                        location.reload();
                    } else {
                        AppAlert.notify({ icon: 'error', text: data.msg || 'Unable to assign the code.', confirmText: 'OK' });
                    }
                })
                .catch(function () {
                    AppAlert.notify({ icon: 'error', text: 'Unable to assign the code.', confirmText: 'OK' });
                });
        }
    );
</script>
