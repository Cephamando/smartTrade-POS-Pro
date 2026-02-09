<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-boxes"></i> Inventory Management</h3>
    <div class="bg-light p-2 rounded border">
        <span class="text-muted small fw-bold text-uppercase me-2">Total Stock Value:</span>
        <span class="fs-5 fw-bold text-success">ZMW <?= number_format($totalValue, 2) ?></span>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light">
        <form method="GET" class="row g-2 align-items-end">
            <input type="hidden" name="page" value="inventory">
            <div class="col-md-3">
                <label class="form-label small fw-bold">Location</label>
                <select name="location_id" class="form-select form-select-sm">
                    <option value="">All Locations</option>
                    <?php foreach($locations as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= ($locFilter == $l['id']) ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold">Category</label>
                <select name="category_id" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php foreach($categories as $c): ?>
                        <option value="<?= $c['id'] ?>" <?= ($catFilter == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold">Search</label>
                <input type="text" name="search" class="form-control form-control-sm" placeholder="Product name or SKU..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary btn-sm w-100 fw-bold"><i class="bi bi-filter"></i> Filter</button>
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
                    <?php if(empty($inventory)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No inventory records found.</td></tr>
                    <?php endif; ?>
                    
                    <?php foreach ($inventory as $item): ?>
                    <tr class="<?= ($item['quantity'] <= 5) ? 'table-warning' : '' ?>">
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($item['product_name']) ?></div>
                            <small class="text-muted"><?= htmlspecialchars($item['sku'] ?? '-') ?></small>
                        </td>
                        <td><span class="badge bg-secondary text-light"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized') ?></span></td>
                        <td><?= htmlspecialchars($item['location_name']) ?></td>
                        <td class="text-center fw-bold fs-5"><?= $item['quantity'] ?></td>
                        <td class="text-end">ZMW <?= number_format($item['price'], 2) ?></td>
                        <td class="text-end fw-bold text-success">ZMW <?= number_format($item['stock_value'], 2) ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
