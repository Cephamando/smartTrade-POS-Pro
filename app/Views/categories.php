<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>📂 Category Management</h3>
    <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addCatModal">
        <i class="bi bi-folder-plus"></i> Add Category
    </button>
</div>

<div class="row">
    <div class="col-md-9">
        <?php
        $bulkFormId = 'bulkCategories';
        $bulkAction = 'index.php?page=categories';
        $bulkButtons = [
            ['value' => 'delete', 'label' => 'Delete selected', 'class' => 'btn-outline-danger', 'title' => 'Delete the selected categories?', 'text' => 'Categories that still have products or subcategories are kept.', 'confirm' => 'Delete', 'color' => '#dc3545'],
        ];
        include __DIR__ . '/partials/bulk_bar.php';
        ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0" id="categoryTable">
                    <thead class="bg-light">
                        <tr>
                            <th style="width:36px"><input type="checkbox" class="form-check-input bulk-all" data-bulk-form="bulkCategories" title="Select all"></th>
                            <th>Category Name</th>
                            <th>Type</th>
                            <th>Parent Folder</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $c): ?>
                            <?php include __DIR__ . '/partials/category_row.php'; ?>
                        <?php endforeach; ?>
                        <?php if (empty($categories)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No categories found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="alert alert-info shadow-sm border-info">
            <i class="bi bi-info-circle-fill"></i> <strong>Category hierarchy</strong>
            <p class="mb-0 mt-2 small">Nest a subcategory under a parent to build the POS drill-down menu. Example: Lagers under Beverages.</p>
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
            <form method="POST" action="index.php?page=categories" id="addCatForm">
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
            <form method="POST" action="index.php?page=categories" id="editCatForm">
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
function syncCatSelect(id, value) {
    const el = document.getElementById(id);
    if (!el) return;
    el.value = value == null ? '' : String(value);
    if (el.tomselect) el.tomselect.setValue(el.value, true);
}

function editCategory(id, name, type, parentId) {
    document.getElementById('editCatId').value = id;
    document.getElementById('editCatName').value = name;
    syncCatSelect('editCatType', type || 'other');
    syncCatSelect('editCatParent', parentId && parentId !== 'null' ? parentId : '');
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editCatModal')).show();
}

function rememberParentOption(id, name) {
    document.querySelectorAll('select[name="parent_id"]').forEach(function (sel) {
        const value = String(id);
        let found = false;
        Array.prototype.forEach.call(sel.options, function (opt) {
            if (opt.value === value) {
                opt.textContent = name;
                found = true;
            }
        });
        if (!found) sel.add(new Option(name, value));
        if (sel.tomselect) {
            if (sel.tomselect.options[value]) sel.tomselect.updateOption(value, { value: value, text: name });
            else sel.tomselect.addOption({ value: value, text: name });
            sel.tomselect.refreshOptions(false);
        }
    });
}

document.getElementById('categoryTable').addEventListener('click', function (event) {
    const btn = event.target.closest('.js-edit-category');
    if (!btn) return;
    editCategory(btn.dataset.id, btn.dataset.name || '', btn.dataset.type || 'other', btn.dataset.parent || '');
});

function bindCategoryForm(form) {
    form.addEventListener('submit', function (event) {
        event.preventDefault();
        LiveRow.postForm(form).then(function (data) {
            LiveRow.upsertRow(data.html, data.id, '#categoryTable tbody');
            rememberParentOption(data.id, data.name);
            form.reset();
            LiveRow.closeModal(form);
            LiveRow.toast(data);
        }).catch(function (err) {
            LiveRow.toast({ status: 'error', msg: err.message });
        });
    });
}
bindCategoryForm(document.getElementById('addCatForm'));
bindCategoryForm(document.getElementById('editCatForm'));
</script>
