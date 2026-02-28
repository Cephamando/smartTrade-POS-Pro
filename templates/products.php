<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>📦 Product Catalog</h3>
    <div>
        <button class="btn btn-outline-primary me-2" data-bs-toggle="modal" data-bs-target="#catModal">
            + New Category
        </button>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#prodModal">
            + New Product
        </button>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light">
                    <tr>
                        <th>Name</th>
                        <th>Category</th>
                        <th>SKU</th>
                        <th>Unit</th>
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
                                <td><small class="text-muted"><?= htmlspecialchars($p['unit']) ?></small></td>
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
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center text-muted py-3">
                                No products found. Add your first product.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=products">
                <div class="modal-body">
                    <input type="hidden" name="add_category" value="1">
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="category_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Product Modal -->
<div class="modal fade" id="prodModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=products">
                <div class="modal-body">
                    <input type="hidden" name="save_product" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">-- None --</option>
                                <?php foreach ($categories as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Unit (e.g., kg, bottle)</label>
                            <input type="text" name="unit" class="form-control" value="unit">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label class="form-label">Cost Price (Buying)</label>
                            <input type="number" step="0.01" name="cost_price" class="form-control" placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Selling Price</label>
                            <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">SKU (optional, must be unique)</label>
                        <input type="text" name="sku" class="form-control" placeholder="e.g. DRINK-001">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        // Find the catalog table and apply the pagination engine
        if (!$.fn.DataTable.isDataTable('table')) {
            $('table').DataTable({
                "pageLength": 25, // Default items per page
                "lengthMenu": [25, 50, 100, 250, 500], // Options for the dropdown
                "language": {
                    "search": "",
                    "searchPlaceholder": "Quick filter products..."
                },
                "stateSave": true // Remembers the page you were on after editing an item!
            });
            
            // Clean up the styling to match Bootstrap 5
            $('.dataTables_filter input').addClass('form-control d-inline-block w-auto ms-2 border-primary shadow-sm');
            $('.dataTables_length select').addClass('form-select d-inline-block w-auto border-primary shadow-sm');
        }
    });
</script>
