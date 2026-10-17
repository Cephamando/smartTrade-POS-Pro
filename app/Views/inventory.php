<style>
    @media print {
        body * { visibility: hidden; }
        #printable-inventory, #printable-inventory * { visibility: visible; }
        #printable-inventory { position: absolute; left: 0; top: 0; width: 100%;
        /* Hide placeholders on printed paper so boxes are completely blank */
        input::placeholder { color: transparent !important; } margin: 0; padding: 0; }
        .no-print { display: none !important; }
        table th:nth-child(5), table td:nth-child(5), /* Hide Phys Input */
        table th:nth-child(6), table td:nth-child(6), /* Hide Variance */
        table th:nth-child(7), table td:nth-child(7), /* Hide Reason */
        table th:last-child, table td:last-child { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        tr { page-break-inside: avoid; }
        
        /* RESTORE WORKSHEET COLUMNS FOR PRINTING */
        table th:nth-child(5), table td:nth-child(5),
        table th:nth-child(6), table td:nth-child(6),
        table th:nth-child(7), table td:nth-child(7) { display: table-cell !important; }
        
        /* Format inputs to look like writable blank lines on paper */
        input.phys-input, input.reason-input { 
            border: 1px solid #000 !important; 
            background: transparent !important; 
            color: #000 !important; 
            box-shadow: none !important; 
            width: 100%;
        /* Hide placeholders on printed paper so boxes are completely blank */
        input::placeholder { color: transparent !important; } 
        }
        .table-warning { background-color: transparent !important; }
    }
</style>

<div id="printable-inventory">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="bi bi-boxes"></i> Inventory Management</h3>

        <div class="d-flex align-items-center gap-3">
            <div class="no-print">
                <button type="submit" form="stockTakeForm" class="btn btn-warning btn-sm fw-bold shadow-sm">
                    <i class="bi bi-check-circle-fill"></i> FINISH & UPDATE STOCK
                </button>
                <button type="button" onclick="downloadInventoryCSV()" class="btn btn-success btn-sm fw-bold shadow-sm ms-2" title="Generate & Download CSV File"><i class="bi bi-file-earmark-arrow-down"></i> Download CSV</button>
                <button onclick="window.print()" class="btn btn-dark btn-sm fw-bold shadow-sm ms-2">
                    <i class="bi bi-printer"></i> Print Stock
                </button>
                <a href="index.php?page=products" class="btn btn-outline-primary btn-sm fw-bold ms-2 shadow-sm">
                    <i class="bi bi-plus-circle"></i> Add Product
                </a>
            </div>

            <div class="bg-light p-2 rounded border">
                <span class="text-muted small fw-bold text-uppercase me-2">Total Stock Value:</span>
                <span class="fs-5 fw-bold text-success" id="stockTotalValue">ZMW <?= number_format($totalValue, 2) ?></span>
            </div>
        </div>
    </div>

    <div class="alert alert-info border-0 shadow-sm no-print">
        <div class="fw-bold mb-1"><i class="bi bi-info-circle-fill me-1"></i> Count while the till stays open</div>
        <div class="small mb-0">
            Enter the <strong>physical count</strong> and submit. Variances are applied to live stock so sales during the count are kept. Quantities never go below zero.
        </div>
    </div>

    <?php
    $explainPage = 'inventory';
    $explainAction = 'explain_stock';
    $explainFields = [
        'location_id' => $locFilter,
        'category_id' => $catFilter,
        'search' => $search,
    ];
    include __DIR__ . '/partials/explain_figures.php';
    ?>

    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body bg-light">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="page" value="inventory">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Location</label>
                    <select name="location_id" class="form-select form-select-sm">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= ($locFilter == $l['id']) ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Category</label>
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($catFilter == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Product name or SKU..." value="<?= htmlspecialchars($search) ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm w-100 fw-bold"><i class="bi bi-filter"></i> Filter</button>
                </div>
            </form>
        </div>
    </div>

    <form method="POST" action="index.php?page=inventory" id="stockTakeForm" class="js-confirm-form"
        data-swal-title="Post this stock take?"
        data-swal-text="Physical counts will overwrite on-hand quantities for every line you filled in."
        data-swal-confirm="Update stock"
        data-swal-cancel="Cancel"
        data-swal-icon="warning">
        <input type="hidden" name="bulk_stock_take" value="1">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Location</th>
                                <th class="text-center">System Stock</th>
                                <th class="text-center" style="width:130px;">Physical Count</th>
                                <th class="text-center">Variance</th>
                                <th class="text-start" style="width:200px;">Reason</th>
                                <th class="text-end">Selling Price</th>
                                <th class="text-end no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($inventory)): ?>
                                <tr><td colspan="9" class="text-center py-5 text-muted">No inventory records found.</td></tr>
                            <?php endif; ?>

                            <?php foreach ($inventory as $item): ?>
                                <tr class="js-stock-row<?= ($item['quantity'] <= 5) ? ' table-warning' : '' ?>" data-inv-id="<?= (int)$item['inventory_id'] ?>" data-product-id="<?= (int)$item['product_id'] ?>" data-qty="<?= htmlspecialchars((string)$item['quantity']) ?>" data-price="<?= htmlspecialchars((string)$item['price']) ?>">
                                    <td>
                                        <div class="fw-bold"><?= htmlspecialchars($item['product_name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($item['sku'] ?? '-') ?></small>
                                    </td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></span></td>
                                    <td><?= htmlspecialchars($item['location_name']) ?></td>
                                    <td class="text-center fw-bold fs-5 text-secondary js-sys"><?= number_format($item['quantity'], 2) ?></td>
                                    
                                    <td>
                                        <input type="hidden" name="inv_id[]" value="<?= $item['inventory_id'] ?>">
                                        <input type="hidden" name="product_id[]" value="<?= $item['product_id'] ?>">
                                        <input type="hidden" name="location_id[]" value="<?= $item['location_id'] ?? 0 ?>">
                                        <input type="hidden" name="sys_qty[]" value="<?= $item['quantity'] ?>">
                                        
                                        <input type="number" min="0" step="0.01" class="form-control text-center fw-bold border-dark phys-input" name="physical_qty[]" data-sys="<?= $item['quantity'] ?>" placeholder="Count">
                                    </td>
                                    
                                    <td class="text-center fw-bold fs-5 variance-cell text-muted">--</td>
                                    
                                    <td>
                                        <input type="text" class="form-control form-control-sm reason-input" name="reason[]" placeholder="Why the variance?" disabled>
                                    </td>

                                    <td class="text-end text-muted js-price">ZMW <?= number_format($item['price'], 2) ?></td>
                                    <td class="text-end no-print">
                                        <button type="button" class="btn btn-sm btn-outline-primary js-edit-stock" data-bs-toggle="modal" data-bs-target="#editProductModal" data-inv-id="<?= (int)$item['inventory_id'] ?>" data-name="<?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>" data-qty="<?= htmlspecialchars((string)$item['quantity']) ?>" data-price="<?= htmlspecialchars((string)$item['price']) ?>" data-location="<?= htmlspecialchars($item['location_name'], ENT_QUOTES) ?>">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </form>
</div>

<div class="modal fade no-print" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Edit Inventory</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=inventory" id="editStockForm">
                <div class="modal-body">
                    <input type="hidden" name="update_product" value="1">
                    <input type="hidden" name="inventory_id" id="edit_inv_id">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">PRODUCT</label>
                        <input type="text" id="edit_name" class="form-control-plaintext fw-bold fs-5" readonly>
                        <small class="text-muted" id="edit_location"></small>
                    </div>
                    <div class="row g-3">
                        <div class="col-6">
                            <label class="form-label fw-bold">System Stock</label>
                            <input type="number" min="0" step="0.01" name="quantity" id="edit_qty" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold">Selling Price (ZMW)</label>
                            <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Live Physical Stock Variance Calculator
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll('.phys-input').forEach(input => {
        input.addEventListener('input', function() {
            let tr = this.closest('tr');
            let sysQty = parseFloat(this.dataset.sys);
            let physQty = parseFloat(this.value);
            let varCell = tr.querySelector('.variance-cell');
            let reasonInput = tr.querySelector('.reason-input');

            if (isNaN(physQty)) {
                varCell.innerText = '--';
                varCell.className = 'text-center fw-bold fs-5 variance-cell text-muted';
                reasonInput.disabled = true;
                reasonInput.required = false;
                reasonInput.value = '';
            } else {
                if (physQty < 0) {
                    this.value = '0';
                    physQty = 0;
                }
                let variance = physQty - sysQty;
                let sign = variance > 0 ? '+' : '';
                varCell.innerText = sign + variance.toFixed(2);
                
                if (variance > 0) {
                    varCell.className = 'text-center fw-bold fs-5 variance-cell text-success';
                } else if (variance < 0) {
                    varCell.className = 'text-center fw-bold fs-5 variance-cell text-danger';
                } else {
                    varCell.className = 'text-center fw-bold fs-5 variance-cell text-dark';
                }

                // If physical != system, force them to write a reason!
                if (variance !== 0) {
                    reasonInput.disabled = false;
                    reasonInput.required = true;
                } else {
                    reasonInput.disabled = true;
                    reasonInput.required = false;
                    reasonInput.value = '';
                }
            }
        });
    });
});

function stockFixed(n) {
    var x = Math.round(Number(n) * 100) / 100;
    var neg = x < 0;
    var parts = Math.abs(x).toFixed(2).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    return (neg ? '-' : '') + parts.join('.');
}

function stockPulse(tr) {
    var low = parseFloat(tr.dataset.qty) <= 5;
    tr.classList.add('table-warning');
    window.setTimeout(function () {
        tr.classList.toggle('table-warning', low);
    }, 1600);
}

function paintStockTotal() {
    var total = 0;
    document.querySelectorAll('tr.js-stock-row').forEach(function (tr) {
        total += parseFloat(tr.dataset.qty || '0') * parseFloat(tr.dataset.price || '0');
    });
    var el = document.getElementById('stockTotalValue');
    if (el) el.textContent = 'ZMW ' + stockFixed(total);
}

function applyStockQty(tr, qty) {
    tr.dataset.qty = String(qty);
    var sys = tr.querySelector('.js-sys');
    if (sys) sys.textContent = stockFixed(qty);
    var hidden = tr.querySelector('input[name="sys_qty[]"]');
    if (hidden) hidden.value = String(qty);
    var phys = tr.querySelector('.phys-input');
    if (phys) {
        phys.dataset.sys = String(qty);
        phys.value = '';
    }
    var reason = tr.querySelector('.reason-input');
    if (reason) {
        reason.value = '';
        reason.disabled = true;
        reason.required = false;
    }
    var cell = tr.querySelector('.variance-cell');
    if (cell) {
        cell.textContent = '--';
        cell.className = 'text-center fw-bold fs-5 variance-cell text-muted';
    }
    var btn = tr.querySelector('.js-edit-stock');
    if (btn) btn.dataset.qty = String(qty);
    stockPulse(tr);
}

function applyStockPrice(tr, price) {
    tr.dataset.price = String(price);
    var cell = tr.querySelector('.js-price');
    if (cell) cell.textContent = 'ZMW ' + stockFixed(price);
    var btn = tr.querySelector('.js-edit-stock');
    if (btn) btn.dataset.price = String(price);
}

document.querySelectorAll('.js-edit-stock').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.getElementById('edit_inv_id').value = btn.dataset.invId || '';
        document.getElementById('edit_name').value = btn.dataset.name || '';
        document.getElementById('edit_qty').value = btn.dataset.qty || '';
        document.getElementById('edit_price').value = btn.dataset.price || '';
        document.getElementById('edit_location').innerText = 'Location: ' + (btn.dataset.location || '');
    });
});

var stockTakeForm = document.getElementById('stockTakeForm');
if (stockTakeForm) {
    stockTakeForm.addEventListener('submit', function (e) {
        if (stockTakeForm.dataset.swalConfirmed !== '1') return;
        e.preventDefault();
        e.stopPropagation();
        delete stockTakeForm.dataset.swalConfirmed;
        var finishBtn = document.querySelector('[form="stockTakeForm"]');
        if (finishBtn) finishBtn.disabled = true;
        LiveRow.postForm(stockTakeForm).then(function (data) {
            (data.lines || []).forEach(function (line) {
                var tr = document.querySelector('tr.js-stock-row[data-inv-id="' + String(line.inventory_id).replace(/[^0-9]/g, '') + '"]');
                if (tr) applyStockQty(tr, line.quantity);
            });
            document.querySelectorAll('.phys-input').forEach(function (input) {
                if (input.value === '') return;
                var tr = input.closest('tr');
                var sys = parseFloat(input.dataset.sys);
                var phys = parseFloat(input.value);
                if (!tr || isNaN(phys) || Math.abs(phys - sys) > 0.001) return;
                input.value = '';
                var reason = tr.querySelector('.reason-input');
                if (reason) {
                    reason.value = '';
                    reason.disabled = true;
                    reason.required = false;
                }
                var cell = tr.querySelector('.variance-cell');
                if (cell) {
                    cell.textContent = '--';
                    cell.className = 'text-center fw-bold fs-5 variance-cell text-muted';
                }
            });
            paintStockTotal();
            LiveRow.toast(data);
        }).catch(function (err) {
            LiveRow.toast({ status: 'error', msg: err.message });
        }).finally(function () {
            if (finishBtn) finishBtn.disabled = false;
        });
    });
}

var editStockForm = document.getElementById('editStockForm');
if (editStockForm) {
    editStockForm.addEventListener('submit', function (e) {
        e.preventDefault();
        LiveRow.postForm(editStockForm).then(function (data) {
            var line = data.line || {};
            document.querySelectorAll('tr.js-stock-row[data-product-id="' + String(line.product_id).replace(/[^0-9]/g, '') + '"]').forEach(function (tr) {
                applyStockPrice(tr, line.price);
                if (tr.dataset.invId === String(line.inventory_id)) applyStockQty(tr, line.quantity);
            });
            paintStockTotal();
            LiveRow.closeModal(editStockForm);
            LiveRow.toast(data);
        }).catch(function (err) {
            LiveRow.toast({ status: 'error', msg: err.message });
        });
    });
}

// --- FILE MANAGER: EXPORT TO CSV ---
function downloadInventoryCSV() {
    let table = document.querySelector(".table");
    let rows = table.querySelectorAll("tr");
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        
        // Skip empty DataTables/PHP placeholder rows
        if (cols.length <= 1) continue; 
        
        for (let j = 0; j < cols.length - 1; j++) { // Skip the 'Actions' column completely
            let cellData = "";
            let input = cols[j].querySelector("input.phys-input, input.reason-input");
            
            // If the cell has a text box, grab what the user typed inside it
            if (input) {
                cellData = input.value;
            } else {
                // Otherwise, grab the standard text and clean up messy line breaks
                cellData = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ").trim();
            }
            
            // Escape double quotes to prevent CSV corruption
            cellData = cellData.replace(/"/g, '""');
            row.push('"' + cellData + '"');
        }
        csv.push(row.join(","));
    }
    
    // Generate the file in memory
    let csvContent = csv.join("\n");
    let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    let url = URL.createObjectURL(blob);
    
    // Trigger the save prompt
    let link = document.createElement("a");
    let date = new Date().toISOString().split('T')[0];
    link.setAttribute("href", url);
    link.setAttribute("download", "Inventory_Stock_Take_" + date + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php if(in_array($_SESSION['role'], ['chef', 'head_chef'])): ?>
<style>
    button[data-bs-target*="#edit"], .btn-edit, .btn-warning, .phys-input, .reason-input, .btn-primary, .btn-outline-primary { display: none !important; }
</style>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        let header = document.querySelector("h3") || document.querySelector("h4");
        if(header) header.innerHTML += ' <span class="badge bg-secondary ms-2" style="font-size:0.5em;">Read-Only Mode</span>';
    });

// --- FILE MANAGER: EXPORT TO CSV ---
function downloadInventoryCSV() {
    let table = document.querySelector(".table");
    let rows = table.querySelectorAll("tr");
    let csv = [];
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        
        // Skip empty DataTables/PHP placeholder rows
        if (cols.length <= 1) continue; 
        
        for (let j = 0; j < cols.length - 1; j++) { // Skip the 'Actions' column completely
            let cellData = "";
            let input = cols[j].querySelector("input.phys-input, input.reason-input");
            
            // If the cell has a text box, grab what the user typed inside it
            if (input) {
                cellData = input.value;
            } else {
                // Otherwise, grab the standard text and clean up messy line breaks
                cellData = cols[j].innerText.replace(/(\r\n|\n|\r)/gm, " ").replace(/\s+/g, " ").trim();
            }
            
            // Escape double quotes to prevent CSV corruption
            cellData = cellData.replace(/"/g, '""');
            row.push('"' + cellData + '"');
        }
        csv.push(row.join(","));
    }
    
    // Generate the file in memory
    let csvContent = csv.join("\n");
    let blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    let url = URL.createObjectURL(blob);
    
    // Trigger the save prompt
    let link = document.createElement("a");
    let date = new Date().toISOString().split('T')[0];
    link.setAttribute("href", url);
    link.setAttribute("download", "Inventory_Stock_Take_" + date + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
<?php endif; ?>