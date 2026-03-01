<style>
    @media print {
        /* Hide everything on the page globally */
        body * {
            visibility: hidden;
        }
        /* Make only the inventory section visible */
        #printable-inventory, #printable-inventory * {
            visibility: visible;
        }
        /* Stretch the printable area to the top-left of the paper */
        #printable-inventory {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            margin: 0;
            padding: 0;
        }
        /* Hide web-only elements like buttons and forms */
        .no-print {
            display: none !important;
        }
        /* Automatically hide the 'Actions' column in the table when printing */
        table th:last-child, table td:last-child {
            display: none !important;
        }
        /* Clean up borders and shadows for paper */
        .card {
            border: none !important;
            box-shadow: none !important;
        }
        /* Prevent table rows from awkwardly splitting across pages */
        tr { 
            page-break-inside: avoid; 
        }
    }
</style>

<div id="printable-inventory">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="bi bi-boxes"></i> Inventory Management</h3>

        <div class="d-flex align-items-center gap-3">
            <div class="no-print">
                <button onclick="window.print()" class="btn btn-dark btn-sm fw-bold shadow-sm">
                    <i class="bi bi-printer"></i> Print Stock
                </button>
                <a href="index.php?page=products" class="btn btn-outline-primary btn-sm fw-bold ms-2 shadow-sm">
                    <i class="bi bi-plus-circle"></i> Add Product
                </a>
            </div>

            <div class="bg-light p-2 rounded border">
                <span class="text-muted small fw-bold text-uppercase me-2">Total Stock Value:</span>
                <span class="fs-5 fw-bold text-success">
                    ZMW <?= number_format($totalValue, 2) ?>
                </span>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4 no-print">
        <div class="card-body bg-light">
            <form method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="page" value="inventory">

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Location</label>
                    <select name="location_id" class="form-select form-select-sm">
                        <option value="">All Locations</option>
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= ($locFilter == $l['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Category</label>
                    <select name="category_id" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= ($catFilter == $c['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4">
                    <label class="form-label small fw-bold">Search</label>
                    <input type="text" name="search" class="form-control form-control-sm"
                           placeholder="Product name or SKU..."
                           value="<?= htmlspecialchars($search) ?>">
                </div>

                <div class="col-md-2">
                    <button class="btn btn-primary btn-sm w-100 fw-bold">
                        <i class="bi bi-filter"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Product</th>
                            <th>Category</th>
                            <th>Location</th>
                            <th class="text-center">Stock Qty</th>
                            <th class="text-end">Selling Price</th>
                            <th class="text-end">Stock Value</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($inventory)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    No inventory records found.
                                </td>
                            </tr>
                        <?php endif; ?>

                        <?php foreach ($inventory as $item): ?>
                            <tr class="<?= ($item['quantity'] <= 5) ? 'table-warning' : '' ?>">
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($item['product_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($item['sku'] ?? '-') ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($item['location_name']) ?></td>
                                <td class="text-center fw-bold fs-5"><?= number_format($item['quantity'], 0) ?></td>
                                <td class="text-end">ZMW <?= number_format($item['price'], 2) ?></td>
                                <td class="text-end fw-bold text-success">
                                    ZMW <?= number_format($item['stock_value'], 2) ?>
                                </td>
                                <td class="text-end">
                                    <button class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editProductModal"
                                            onclick="populateEdit(
                                                '<?= $item['inventory_id'] ?>',
                                                '<?= addslashes($item['product_name']) ?>',
                                                '<?= $item['quantity'] ?>',
                                                '<?= $item['price'] ?>',
                                                '<?= addslashes($item['location_name']) ?>'
                                            )">
                                        <i class="bi bi-pencil"></i> Edit
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade no-print" id="editProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Edit Inventory</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" action="index.php?page=inventory">
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
                            <label class="form-label fw-bold">Stock Quantity</label>
                            <input type="number" step="0.01" name="quantity" id="edit_qty" class="form-control" required>
                        </div>

                        <div class="col-6">
                            <label class="form-label fw-bold">Selling Price (ZMW)</label>
                            <input type="number" step="0.01" name="price" id="edit_price" class="form-control" required>
                            <small class="text-danger" style="font-size: 0.7rem;">
                                *Updates price for all locations
                            </small>
                        </div>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                </div>
            </form>
<script>
function populateEdit(id, name, qty, price, loc) {
    document.getElementById('edit_inv_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_qty').value = qty;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_location').innerText = "Location: " + loc;
}
</script>

        </div>
    </div>
</div>

<?php if(in_array($_SESSION['role'], ['chef', 'head_chef'])): ?>
<style>
    /* Hide all Edit/Update buttons, Data-Targets, and Modal Triggers */
    button[data-bs-target*="#edit"], 
    button[data-bs-target*="#update"], 
    button[data-bs-target*="Modal"],
    .btn-edit, 
    .btn-warning,
    .btn-primary[data-bs-toggle="modal"],
    .btn-outline-primary {
        display: none !important;
    }
</style>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        let rows = document.querySelectorAll("table tbody tr");
        rows.forEach(row => {
            row.removeAttribute("onclick");
            row.style.cursor = "default";
        });
        
        let header = document.querySelector("h3") || document.querySelector("h4");
        if(header) {
            header.innerHTML += ' <span class="badge bg-secondary ms-2" style="font-size:0.5em; vertical-align:middle;">Read-Only Mode</span>';
        }
    });
</script>
<?php endif; ?>
