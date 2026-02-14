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
                        <th>Unit</th>
                        <th class="text-end">Cost (ZMW)</th>
                        <th class="text-end">Price (ZMW)</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($p['name']) ?></td>
                        <td>
                            <?php if($p['category_name']): ?>
                                <span class="badge bg-secondary"><?= htmlspecialchars($p['category_name']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><small class="text-muted"><?= htmlspecialchars($p['unit']) ?></small></td>
                        <td class="text-end text-muted"><?= number_format($p['cost_price'], 2) ?></td>
                        <td class="text-end fw-bold text-success"><?= number_format($p['price'], 2) ?></td>
                        <td>
                            <?php if($p['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Disabled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

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
                    <label>Category Name</label>
                    <input type="text" name="category_name" class="form-control" required>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
                        <label>Product Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label>Category</label>
                            <select name="category_id" class="form-select">
                                <option value="">-- None --</option>
                                <?php foreach($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label>Unit (e.g., kg, bottle)</label>
                            <input type="text" name="unit" class="form-control" value="unit">
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-6">
                            <label>Cost Price (Buying)</label>
                            <input type="number" step="0.01" name="cost_price" class="form-control" placeholder="0.00">
                        </div>
                        <div class="col-6">
                            <label>Selling Price</label>
                            <input type="number" step="0.01" name="price" class="form-control" placeholder="0.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>