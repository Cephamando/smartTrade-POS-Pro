<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>📂 Category Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#catModal">
        <i class="bi bi-folder-plus"></i> Add Category
    </button>
</div>

<div class="row">
    <div class="col-md-8">
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Category Name</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($c['name']) ?></td>
                            <td class="text-end">
                                <form method="POST" action="index.php?page=categories" class="d-inline" onsubmit="return confirm('Delete <?= $c['name'] ?>?');">
                                    <input type="hidden" name="delete_category" value="1">
                                    <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($categories)): ?>
                            <tr><td colspan="2" class="text-center py-4 text-muted">No categories found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="alert alert-info">
            <i class="bi bi-info-circle-fill"></i> <strong>Tip:</strong>
            <p class="mb-0 mt-2">Categories help route orders to the Kitchen. For example, ensure all food items are in a "Meals" or "Food" category.</p>
        </div>
    </div>
</div>

<div class="modal fade" id="catModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=categories">
                <div class="modal-body">
                    <input type="hidden" name="add_category" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Beverages">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
