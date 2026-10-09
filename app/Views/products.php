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
            <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#prodModal" onclick="resetProdForm()"><i class="bi bi-plus-circle"></i> New Product</button>
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
            <?php
            $bulkFormId = 'bulkProducts';
            $bulkAction = 'index.php?page=products';
            $bulkButtons = [
                ['value' => 'activate', 'label' => 'Activate', 'class' => 'btn-success', 'title' => 'Activate the selected products?', 'confirm' => 'Activate', 'color' => '#198754'],
                ['value' => 'deactivate', 'label' => 'Disable', 'class' => 'btn-outline-light', 'title' => 'Disable the selected products?', 'text' => 'They stay in history but leave the POS and the shop.', 'confirm' => 'Disable', 'color' => '#6c757d'],
            ];
            echo '<div class="p-2 pb-0">';
            include __DIR__ . '/partials/bulk_bar.php';
            echo '</div>';
            ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="catalogTable">
                    <thead class="bg-light text-dark">
                        <tr>
                            <th style="width:36px"><input type="checkbox" class="form-check-input bulk-all" data-bulk-form="bulkProducts" title="Select all"></th>
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
                            <th class="text-end no-print">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($products)): ?>
                            <?php foreach ($products as $p): ?>
                                <?php include __DIR__ . '/partials/product_row.php'; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade no-print" id="catModal" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content"><div class="modal-header bg-light"><h5 class="modal-title fw-bold">Add Category</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" action="index.php?page=products" id="catForm"><div class="modal-body"><input type="hidden" name="add_category" value="1"><div class="mb-3"><label class="form-label small fw-bold text-muted">CATEGORY NAME</label><input type="text" name="category_name" class="form-control" required></div></div><div class="modal-footer border-0"><button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">Save Category</button></div></form></div></div>
</div>

<div class="modal fade no-print" id="prodModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-primary border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="prodModalTitle">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=products" id="prodForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="save_product" value="1">
                    <input type="hidden" name="product_id" id="productId" value="">
                    <div class="row mb-3">
                        <div class="col-8"><label class="form-label small fw-bold text-muted">PRODUCT NAME</label><input type="text" name="name" id="prodName" class="form-control fw-bold" required></div>
                        <div class="col-4"><label class="form-label small fw-bold text-muted">SKU (Optional)</label><input type="text" name="sku" id="prodSku" class="form-control" placeholder="e.g. DRINK-001"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-4">
                            <label class="form-label small fw-bold text-muted">CATEGORY</label>
                            <select name="category_id" id="prodCategory" class="form-select"><option value="">-- None --</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option><?php endforeach; ?></select>
                        </div>
                        <div class="col-4"><label class="form-label small fw-bold text-muted">UNIT</label><input type="text" name="unit" id="prodUnit" class="form-control" value="unit"></div>
                        <div class="col-4">
                            <label class="form-label small fw-bold text-muted">TYPE</label>
                            <select name="type" id="prodType" class="form-select">
                                <option value="item">Item</option>
                                <option value="service">Service</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-3">
                        <?php if ($isEnt): ?>
                        <div class="col-4">
                            <label class="form-label small fw-bold text-muted">ZRA TAX CLASS</label>
                            <select name="tax_class" id="prodTaxClass" class="form-select">
                                <option value="A" selected>Class A - Standard (16%)</option>
                                <option value="B">Class B - Zero Rated (0%)</option>
                                <option value="C">Class C - Exempt</option>
                                <option value="D">Class D - Excise</option>
                            </select>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="tax_class" id="prodTaxClass" value="A">
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
                        <div class="col-6"><label class="form-label small fw-bold text-muted">COST PRICE (ZMW)</label><input type="number" step="0.01" name="cost_price" id="prodCost" class="form-control text-danger" placeholder="0.00"></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">SELLING PRICE (ZMW)</label><input type="number" step="0.01" name="price" id="prodPrice" class="form-control text-success fw-bold" placeholder="0.00"></div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-8">
                            <label class="form-label small fw-bold text-muted">SHOP / MENU PHOTO</label>
                            <input type="file" name="image" id="prodImage" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                            <div class="form-text">Shown on the online shop. Leave empty to keep the current photo.</div>
                        </div>
                        <div class="col-4">
                            <img id="prodImagePreview" alt="" class="rounded border d-none" style="max-height:72px;max-width:100%;object-fit:cover;">
                            <div class="form-check mt-2 d-none" id="prodImageRemoveWrap">
                                <input class="form-check-input" type="checkbox" name="remove_image" value="1" id="prodImageRemove">
                                <label class="form-check-label small" for="prodImageRemove">Remove photo</label>
                            </div>
                        </div>
                    </div>
                    <div class="row mb-0">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">POS VISIBILITY</label>
                            <select name="is_sellable" id="prodSellable" class="form-select">
                                <option value="1">Sellable on POS</option>
                                <option value="0">Raw Material (Hidden)</option>
                            </select>
                        </div>
                        <div class="col-6 d-none" id="prodStatusCol">
                            <label class="form-label small fw-bold text-muted">STATUS</label>
                            <select name="is_active" id="prodStatus" class="form-select">
                                <option value="1">Active</option>
                                <option value="0">Disabled</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" id="prodSaveBtn"><i class="bi bi-save"></i> Save Product</button>
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
            "language": { "emptyTable": "No products found. Add a product or import a CSV/Excel file." },
            "columnDefs": [{ "orderable": false, "targets": -1 }]
        });
        $('.dataTables_length select').addClass('form-select form-select-sm d-inline-block w-auto border-secondary shadow-sm');

        function applyCatalogFilters() {
            table.search($('#customSearch').val() || '');
            table.columns().search('');
            table.column(2).search($('#filterCategory').val() || '');
            table.column(5).search($('#filterType').val() || '');
            <?php if ($isEnt): ?>
            table.column(6).search($('#filterTax').val() || '');
            table.column(7).search($('#filterUNSPSC').val() || '');
            table.column(10).search($('#filterStatus').val() || '');
            <?php else: ?>
            table.column(6).search($('#filterUNSPSC').val() || '');
            table.column(9).search($('#filterStatus').val() || '');
            <?php endif; ?>
            table.draw();
        }
        var filterTimer = null;
        function queueCatalogFilters() {
            clearTimeout(filterTimer);
            filterTimer = setTimeout(applyCatalogFilters, 120);
        }
        $('#applyFilters').on('click', function(e) {
            e.preventDefault();
            applyCatalogFilters();
        });
        $('#customSearch, #filterUNSPSC').on('input', queueCatalogFilters);
        $('#filterCategory, #filterType, #filterTax, #filterStatus').on('change', queueCatalogFilters);
        $('.custom-filters input').on('keydown', function(e) {
            if (e.which === 13) { e.preventDefault(); }
        });
        $('#clearFilters').on('click', function(e) {
            e.preventDefault();
            $('#customSearch, #filterUNSPSC').val('');
            ['filterCategory', 'filterTax', 'filterStatus', 'filterType'].forEach(function (id) {
                var el = document.getElementById(id);
                if (!el) return;
                if (el.tomselect) { el.tomselect.clear(true); }
                el.value = '';
            });
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
        function runSearch(q) {
            clearTimeout(timer);
            timer = setTimeout(function () {
                fetch('index.php?page=unspsc&ajax=search&q=' + encodeURIComponent(q))
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        const items = data.results || [];
                        resultsEl.innerHTML = '';
                        if (!items.length) {
                            resultsEl.innerHTML = '<div class="list-group-item small text-muted">' + (q ? 'No matching codes. Import the UNSPSC Excel file first.' : 'No codes used yet. Search and assign one, and it will stay here.') + '</div>';
                            return;
                        }
                        if (!q && items.every(function (item) { return item.favourite; })) {
                            const head = document.createElement('div');
                            head.className = 'list-group-item small fw-bold text-muted';
                            head.textContent = 'Favourites · previously used';
                            resultsEl.appendChild(head);
                        }
                        items.forEach(function (item) {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.className = 'list-group-item list-group-item-action';
                            const path = item.item_cls_path
                                ? '<div class="small text-muted">' + escapeUnspsc(item.item_cls_path) + '</div>'
                                : '';
                            const badge = item.item_cls_lvl_label
                                ? '<span class="badge ' + (item.item_cls_lvl >= 4 ? 'bg-primary' : 'bg-secondary') + '">' + escapeUnspsc(item.item_cls_lvl_label) + '</span>'
                                : '';
                            const used = item.favourite
                                ? '<span class="badge bg-warning text-dark">Used' + (item.uses ? ' ' + escapeUnspsc(item.uses) : '') + '</span>'
                                : '';
                            btn.innerHTML = '<div class="d-flex justify-content-between gap-2 align-items-start"><div><strong class="font-monospace">'
                                + escapeUnspsc(item.item_cls_cd) + '</strong><div>' + escapeUnspsc(item.item_cls_nm || '') + '</div>'
                                + path + '</div><div class="d-flex flex-column gap-1 align-items-end">' + used + badge + '</div></div>';
                            btn.addEventListener('click', function () { onPick(item); });
                            resultsEl.appendChild(btn);
                        });
                    });
            }, q ? 250 : 0);
        }
        inputEl.addEventListener('focus', function () {
            if (!inputEl.value.trim()) runSearch('');
        });
        inputEl.addEventListener('input', function () {
            runSearch(inputEl.value.trim());
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
    document.getElementById('catalogTable').addEventListener('click', function (event) {
        const pick = event.target.closest('.unspsc-pick');
        if (pick) {
            document.getElementById('assignProductId').value = pick.dataset.productId;
            document.getElementById('assignProductName').textContent = pick.dataset.productName;
            document.getElementById('assignUnspscSearch').value = '';
            document.getElementById('assignUnspscResults').innerHTML = '';
            bootstrap.Modal.getOrCreateInstance(assignModal).show();
            document.getElementById('assignUnspscSearch').focus();
            return;
        }
        const edit = event.target.closest('.js-edit-product');
        if (edit) {
            openProductEditor(edit);
        }
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
            fetch('index.php?page=unspsc', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' },
                body: body.toString()
            })
                .then(function (r) { return LiveRow.parseJson(r); })
                .then(function (data) {
                    if (data.status !== 'success') {
                        throw new Error(data.msg || 'Unable to assign the code.');
                    }
                    paintCatalogUnspsc(data.product_id, data.unspsc_code, data.unspsc_name);
                    const modal = bootstrap.Modal.getInstance(assignModal);
                    if (modal) modal.hide();
                    LiveRow.toast(data);
                })
                .catch(function (err) {
                    AppAlert.notify({ icon: 'error', text: err.message || 'Unable to assign the code.', confirmText: 'OK' });
                });
        }
    );

    function resetProdForm() {
        const form = document.getElementById('prodForm');
        if (form) {
            form.reset();
        }
        document.getElementById('productId').value = '';
        document.getElementById('prodModalTitle').textContent = 'Add Product';
        document.getElementById('prodSaveBtn').innerHTML = '<i class="bi bi-save"></i> Save Product';
        document.getElementById('prodStatusCol').classList.add('d-none');
        document.getElementById('prodSellable').value = '1';
        document.getElementById('newUnspscCode').value = '';
        document.getElementById('newUnspscChosen').textContent = 'Optional — search and select a code.';
        document.getElementById('newUnspscSearch').value = '';
        document.getElementById('newUnspscResults').innerHTML = '';
        const preview = document.getElementById('prodImagePreview');
        const removeWrap = document.getElementById('prodImageRemoveWrap');
        const removeBox = document.getElementById('prodImageRemove');
        if (preview) { preview.classList.add('d-none'); preview.removeAttribute('src'); }
        if (removeWrap) { removeWrap.classList.add('d-none'); }
        if (removeBox) { removeBox.checked = false; }
        syncSelect('prodCategory', '');
        syncSelect('prodType', 'item');
        syncSelect('prodSellable', '1');
        syncSelect('prodStatus', '1');
        const tax = document.getElementById('prodTaxClass');
        if (tax && tax.tagName === 'SELECT') {
            syncSelect('prodTaxClass', 'A');
        }
    }

    function setProdUnspsc(code, name) {
        document.getElementById('newUnspscCode').value = code || '';
        document.getElementById('newUnspscResults').innerHTML = '';
        if (code) {
            const label = name ? (code + ' — ' + name) : code;
            document.getElementById('newUnspscChosen').textContent = 'Selected: ' + label;
            document.getElementById('newUnspscSearch').value = name ? (code + ' ' + name) : code;
        } else {
            document.getElementById('newUnspscChosen').textContent = 'Optional — search and select a code.';
            document.getElementById('newUnspscSearch').value = '';
        }
    }

    function syncSelect(id, value) {
        const el = document.getElementById(id);
        if (!el) return;
        const next = value == null ? '' : String(value);
        el.value = next;
        if (el.tomselect) {
            el.tomselect.setValue(next, true);
        }
    }

    function rememberCategory(cat) {
        const filter = document.getElementById('filterCategory');
        if (filter && !Array.prototype.some.call(filter.options, function (opt) { return opt.value === cat.name; })) {
            filter.add(new Option(cat.name, cat.name));
            if (filter.tomselect) {
                filter.tomselect.addOption({ value: cat.name, text: cat.name });
                filter.tomselect.refreshOptions(false);
            }
        }
        const prod = document.getElementById('prodCategory');
        const id = String(cat.id);
        if (prod && !prod.querySelector('option[value="' + id + '"]')) {
            prod.add(new Option(cat.name, id));
            if (prod.tomselect) {
                prod.tomselect.addOption({ value: id, text: cat.name });
                prod.tomselect.refreshOptions(false);
            }
        }
    }

    function paintCatalogUnspsc(productId, code, name) {
        const btn = document.querySelector('.unspsc-pick[data-product-id="' + String(productId).replace(/[^0-9]/g, '') + '"]');
        if (!btn) return;
        if (code) {
            btn.innerHTML = '<span class="fw-bold">' + escapeUnspsc(code) + '</span>'
                + (name ? '<div class="small text-muted">' + escapeUnspsc(name) + '</div>' : '');
        } else {
            btn.innerHTML = '<span class="text-muted">Assign code</span>';
        }
        const edit = btn.closest('tr') && btn.closest('tr').querySelector('.js-edit-product');
        if (edit) {
            let product = {};
            try { product = JSON.parse(edit.getAttribute('data-product') || '{}'); } catch (e) { product = {}; }
            product.unspsc_code = code || '';
            product.unspsc_name = name || '';
            edit.setAttribute('data-product', JSON.stringify(product));
        }
        if (window.jQuery && $.fn.DataTable.isDataTable('#catalogTable')) {
            $('#catalogTable').DataTable().cell(btn.closest('td')).invalidate('dom').draw(false);
        }
        LiveRow.flash(btn.closest('tr'));
    }

    function openProductEditor(btn) {
        let product = {};
        try {
            product = JSON.parse(btn.getAttribute('data-product') || '{}');
        } catch (e) {
            return;
        }
        document.getElementById('prodModalTitle').textContent = 'Edit Product';
        document.getElementById('prodSaveBtn').innerHTML = '<i class="bi bi-save"></i> Update Product';
        document.getElementById('productId').value = product.id || '';
        document.getElementById('prodName').value = product.name || '';
        document.getElementById('prodSku').value = product.sku || '';
        syncSelect('prodCategory', product.category_id ? String(product.category_id) : '');
        document.getElementById('prodUnit').value = product.unit || 'unit';
        syncSelect('prodType', product.type || 'item');
        const tax = document.getElementById('prodTaxClass');
        if (tax) {
            syncSelect('prodTaxClass', product.tax_class || 'A');
        }
        document.getElementById('prodCost').value = product.cost_price != null ? product.cost_price : '';
        document.getElementById('prodPrice').value = product.price != null ? product.price : '';
        syncSelect('prodStatus', String(product.is_active === 0 ? 0 : 1));
        document.getElementById('prodStatusCol').classList.remove('d-none');
        syncSelect('prodSellable', product.is_sellable !== undefined ? String(product.is_sellable) : '1');
        setProdUnspsc(product.unspsc_code || '', product.unspsc_name || '');
        const preview = document.getElementById('prodImagePreview');
        const removeWrap = document.getElementById('prodImageRemoveWrap');
        if (preview && product.image_path) {
            preview.src = product.image_path;
            preview.classList.remove('d-none');
            if (removeWrap) { removeWrap.classList.remove('d-none'); }
        }
        bootstrap.Modal.getOrCreateInstance(prodModalEl).show();
    }

    const prodModalEl = document.getElementById('prodModal');
    if (prodModalEl) {
        prodModalEl.addEventListener('hidden.bs.modal', resetProdForm);
    }

    document.getElementById('prodForm').addEventListener('submit', function (event) {
        event.preventDefault();
        const form = this;
        LiveRow.postForm(form).then(function (data) {
            LiveRow.upsertRow(data.html, data.id, '#catalogTable tbody');
            LiveRow.closeModal(form);
            LiveRow.toast(data);
        }).catch(function (err) {
            LiveRow.toast({ status: 'error', msg: err.message });
        });
    });

    document.getElementById('catForm').addEventListener('submit', function (event) {
        event.preventDefault();
        const form = this;
        LiveRow.postForm(form).then(function (data) {
            if (data.category) rememberCategory(data.category);
            form.reset();
            LiveRow.closeModal(form);
            LiveRow.toast(data);
        }).catch(function (err) {
            LiveRow.toast({ status: 'error', msg: err.message });
        });
    });
</script>
