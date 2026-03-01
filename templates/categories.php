<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>📂 Category Management</h3>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addCatModal">
        <i class="bi bi-folder-plus"></i> Add Category
    </button>
</div>

<div class="row">
    <div class="col-md-9">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Category Name</th>
                            <th>Type</th>
                            <th>Parent Folder</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                        <tr>
                            <td class="fw-bold text-dark">
                                <?php if($c['parent_id']): ?><i class="bi bi-arrow-return-right text-muted me-2"></i><?php endif; ?>
                                <?= htmlspecialchars($c['name']) ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars(ucfirst($c['type'] ?? 'other')) ?></span></td>
                            <td>
                                <?php if($c['parent_name']): ?>
                                    <span class="badge bg-info text-dark"><i class="bi bi-folder-fill"></i> <?= htmlspecialchars($c['parent_name']) ?></span>
                                <?php else: ?>
                                    <em class="text-muted small">Master Category</em>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-outline-primary btn-sm" 
                                    onclick="editCategory(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>', '<?= $c['type'] ?>', '<?= $c['parent_id'] ?>')">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <form method="POST" action="index.php?page=categories" class="d-inline ms-1" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($c['name'])) ?>?');">
                                    <input type="hidden" name="delete_category" value="1">
                                    <input type="hidden" name="category_id" value="<?= $c['id'] ?>">
                                    <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($categories)): ?>
                            <tr><td colspan="4" class="text-center py-4 text-muted">No categories found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="alert alert-info shadow-sm border-info">
            <i class="bi bi-info-circle-fill"></i> <strong>Hierarchy Tip:</strong>
            <p class="mb-0 mt-2 small">You can nest categories by assigning a "Parent Folder". For example, nest "Lagers" under the Master "Beverages" category to create the Drill-Down menu on the POS!</p>
        </div>
    </div>
</div>

<div class="modal fade" id="addCatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-primary border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-folder-plus text-primary"></i> Add Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=categories">
                <div class="modal-body p-4">
                    <input type="hidden" name="add_category" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">CATEGORY NAME</label>
                        <input type="text" name="name" class="form-control form-control-lg fw-bold" required placeholder="e.g. Beverages">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">ROUTING TYPE</label>
                        <select name="type" class="form-select">
                            <option value="drink">Drink / Beverage</option>
                            <option value="food">Food / Meal (Goes to Kitchen)</option>
                            <option value="ingredients">Raw Ingredients</option>
                            <option value="other">Other / General</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">NEST UNDER (PARENT FOLDER)</label>
                        <select name="parent_id" class="form-select">
                            <option value="">-- Make this a Master Category --</option>
                            <?php foreach($parents as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">CREATE CATEGORY</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editCatModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-success border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-success"></i> Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=categories">
                <div class="modal-body p-4">
                    <input type="hidden" name="edit_category" value="1">
                    <input type="hidden" name="category_id" id="editCatId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">CATEGORY NAME</label>
                        <input type="text" name="name" id="editCatName" class="form-control form-control-lg fw-bold" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">ROUTING TYPE</label>
                        <select name="type" id="editCatType" class="form-select">
                            <option value="drink">Drink / Beverage</option>
                            <option value="food">Food / Meal (Goes to Kitchen)</option>
                            <option value="ingredients">Raw Ingredients</option>
                            <option value="other">Other / General</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-muted small">NEST UNDER (PARENT FOLDER)</label>
                        <select name="parent_id" id="editCatParent" class="form-select">
                            <option value="">-- Make this a Master Category --</option>
                            <?php foreach($parents as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-success w-100 fw-bold py-2 shadow-sm">SAVE CHANGES</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCategory(id, name, type, parentId) {
    document.getElementById('editCatId').value = id;
    document.getElementById('editCatName').value = name;
    
    let typeSelect = document.getElementById('editCatType');
    if(type) typeSelect.value = type;
    else typeSelect.value = 'other';

    let parentSelect = document.getElementById('editCatParent');
    if(parentId && parentId !== 'null' && parentId !== '') parentSelect.value = parentId;
    else parentSelect.value = '';

    var myModal = new bootstrap.Modal(document.getElementById('editCatModal'));
    myModal.show();
}
</script>
