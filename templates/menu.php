<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3>🧑‍🍳 Chef's Menu Manager</h3>
        <p class="text-muted">Create dishes and manage availability.</p>
    </div>
    <button class="btn btn-primary btn-lg" data-bs-toggle="modal" data-bs-target="#addDishModal">
        <i class="bi bi-plus-circle-fill"></i> New Dish
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Dish Name</th>
                    <th>Category</th>
                    <th>Price (ZMW)</th>
                    <th>Status on POS</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $p): ?>
                <tr class="<?= $p['is_active'] ? '' : 'bg-light text-muted' ?>">
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($p['name']) ?></div>
                    </td>
                    <td><span class="badge bg-secondary"><?= htmlspecialchars($p['category_name'] ?? 'Uncategorized') ?></span></td>
                    <td class="fw-bold"><?= number_format($p['price'], 2) ?></td>
                    <td>
                        <form method="POST" action="index.php?page=menu">
                            <input type="hidden" name="toggle_status" value="1">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <input type="hidden" name="current_status" value="<?= $p['is_active'] ?>">
                            
                            <?php if ($p['is_active']): ?>
                                <button class="btn btn-success btn-sm badge rounded-pill border-0">
                                    <i class="bi bi-check-circle"></i> Active
                                </button>
                            <?php else: ?>
                                <button class="btn btn-danger btn-sm badge rounded-pill border-0">
                                    <i class="bi bi-slash-circle"></i> Hidden (86'd)
                                </button>
                            <?php endif; ?>
                        </form>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-primary" 
                                onclick="editDish(<?= $p['id'] ?>, '<?= htmlspecialchars(addslashes($p['name'])) ?>', <?= $p['category_id'] ?>, <?= $p['price'] ?>)">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addDishModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Create New Menu Item</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=menu">
                <div class="modal-body">
                    <input type="hidden" name="add_product" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Dish Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. T-Bone Steak">
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">Category</label>
                            <select name="category_id" class="form-select" required>
                                <?php foreach($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">Selling Price</label>
                            <div class="input-group">
                                <span class="input-group-text">ZMW</span>
                                <input type="number" name="price" class="form-control" step="0.01" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add to Menu</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editDishModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Edit Dish</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=menu">
                <div class="modal-body">
                    <input type="hidden" name="edit_product" value="1">
                    <input type="hidden" name="product_id" id="editId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Dish Name</label>
                        <input type="text" name="name" id="editName" class="form-control" required>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">Category</label>
                            <select name="category_id" id="editCat" class="form-select" required>
                                <?php foreach($categories as $c): ?>
                                    <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label fw-bold">Price</label>
                            <input type="number" name="price" id="editPrice" class="form-control" step="0.01" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editDish(id, name, catId, price) {
    document.getElementById('editId').value = id;
    document.getElementById('editName').value = name;
    document.getElementById('editCat').value = catId;
    document.getElementById('editPrice').value = price;
    new bootstrap.Modal(document.getElementById('editDishModal')).show();
}
</script>
