<?php
$canRegisterZra = (defined('LICENSE_TIER') && LICENSE_TIER === 'enterprise')
    || (($_SESSION['role'] ?? '') === 'dev');
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-book-half text-success"></i> Menu & Recipe Builder</h3>
    <div>
        <a href="index.php?page=kitchen" class="btn btn-danger fw-bold"><i class="bi bi-fire"></i> Go to Produce</a>
        <a href="index.php?page=pos" class="btn btn-outline-secondary"><i class="bi bi-cart4"></i> POS</a>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-top border-success border-4 mb-3">
            <div class="card-body">
                <h5 class="card-title fw-bold" id="formTitle">Add Menu Item</h5>
                <p class="small text-muted mb-4">Create the sellable item here first.</p>
                <form method="POST">
                    <input type="hidden" name="save_menu_item" value="1">
                    <input type="hidden" name="item_id" id="itemId">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Item Name</label>
                        <input type="text" name="name" id="itemName" class="form-control fw-bold" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Category</label>
                        <select name="category_id" id="itemCat" class="form-select fw-bold" required>
                            <?php foreach($foodCategories as $fc): ?>
                                <option value="<?= $fc['id'] ?>"><?= htmlspecialchars($fc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Selling Price (ZMW)</label>
                            <input type="number" step="0.01" name="price" id="itemPrice" class="form-control text-success fw-bold" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Cost Price (ZMW)</label>
                            <input type="number" step="0.01" name="cost_price" id="itemCost" class="form-control text-danger fw-bold">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">UNSPSC CODE</label>
                        <input type="hidden" name="unspsc_code" id="menuUnspscCode" value="">
                        <input type="search" class="form-control" id="menuUnspscSearch" placeholder="Search commodity title or 8-digit code…" autocomplete="off">
                        <div id="menuUnspscResults" class="list-group mt-2" style="max-height: 180px; overflow-y: auto;"></div>
                        <div class="small text-muted mt-1" id="menuUnspscChosen">Optional — required for ZRA registration.</div>
                    </div>
                    <?php if ($canRegisterZra): ?>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="register_zra" value="1" id="menuRegisterZra">
                        <label class="form-check-label small" for="menuRegisterZra">Register this item with ZRA Smart Invoice</label>
                    </div>
                    <?php endif; ?>
                    <button type="submit" class="btn btn-success w-100 fw-bold" id="btnSubmit">Save Item</button>
                    <button type="button" class="btn btn-outline-secondary w-100 mt-2 d-none" id="btnCancel" onclick="resetForm()">Cancel Edit</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <div class="row g-2">
                    <div class="col-md-7">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="menuSearch" class="form-control border-start-0 ps-0" placeholder="Search menu items..." onkeyup="filterMenu()">
                        </div>
                    </div>
                    <div class="col-md-5">
                        <select id="menuCategoryFilter" class="form-select fw-bold text-muted" onchange="filterMenu()">
                            <option value="all">All Categories</option>
                            <?php foreach($foodCategories as $fc): ?>
                                <option value="<?= htmlspecialchars($fc['name']) ?>"><?= htmlspecialchars($fc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="card-body p-0 table-responsive" style="max-height: 70vh; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="ps-3 text-uppercase small text-muted">Menu Item</th>
                            <th class="text-uppercase small text-muted">Category</th>
                            <th class="text-end text-uppercase small text-muted">Price</th>
                            <th class="text-end pe-3 text-uppercase small text-muted">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="menuTableBody">
                        <?php foreach($menuItems as $m): 
                            $hasRecipe = isset($recipesGrouped[$m['id']]) && count($recipesGrouped[$m['id']]) > 0;
                        ?>
                        <tr class="menu-row" data-name="<?= htmlspecialchars(strtolower($m['name'])) ?>" data-category="<?= htmlspecialchars($m['cat_name']) ?>">
                            <td class="ps-3 fw-bold">
                                <?= htmlspecialchars($m['name']) ?>
                                <?php if($hasRecipe): ?><span class="badge bg-info text-dark ms-2" style="font-size:0.6rem;"><i class="bi bi-diagram-3"></i> RECIPE</span><?php endif; ?>
                                <?php if (!empty($m['unspsc_code'])): ?>
                                    <div class="small text-muted font-monospace"><?= htmlspecialchars($m['unspsc_code']) ?><?= !empty($m['unspsc_name']) ? ' · ' . htmlspecialchars($m['unspsc_name']) : '' ?></div>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($m['cat_name']) ?></span></td>
                            <td class="text-end text-success fw-bold">ZMW <?= number_format($m['price'], 2) ?></td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-info fw-bold me-1" onclick="openRecipeBuilder(<?= (int)$m['id'] ?>, '<?= htmlspecialchars(addslashes($m['name'])) ?>', '<?= htmlspecialchars(addslashes((string)($m['unspsc_code'] ?? ''))) ?>', '<?= htmlspecialchars(addslashes((string)($m['unspsc_name'] ?? ''))) ?>')" title="Manage Recipe">
                                    <i class="bi bi-cup-straw"></i> Recipe
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick='editItem(<?= json_encode($m) ?>)'><i class="bi bi-pencil"></i></button>
                                <form method="POST" class="d-inline js-confirm-form"
                                    data-swal-title="Remove from menu?"
                                    data-swal-text="This item will no longer appear on the POS."
                                    data-swal-confirm="Remove"
                                    data-swal-cancel="Cancel"
                                    data-swal-icon="warning">
                                    <input type="hidden" name="delete_item" value="1">
                                    <input type="hidden" name="item_id" value="<?= $m['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="noResultsRow" style="display: none;">
                            <td colspan="4" class="text-center py-4 text-muted fst-italic">No menu items match your search.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="recipeModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow-lg border-0 border-top border-info border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-diagram-3 text-info"></i> Recipe Builder: <span id="recipeItemName" class="text-primary"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="save_recipe" value="1">
                    <input type="hidden" name="parent_product_id" id="recipeParentId">
                    
                    <div class="alert alert-info py-2 small">
                        <strong><i class="bi bi-info-circle"></i> Recipe:</strong> Add ingredients and quantities. Each sale deducts those amounts from inventory.
                    </div>
                    <div class="mb-3 p-3 bg-light rounded border">
                        <label class="form-label small fw-bold text-muted">UNSPSC CODE FOR THIS MENU ITEM</label>
                        <input type="hidden" name="unspsc_code" id="recipeUnspscCode" value="">
                        <input type="search" class="form-control" id="recipeUnspscSearch" placeholder="Search commodity title or 8-digit code…" autocomplete="off">
                        <div id="recipeUnspscResults" class="list-group mt-2" style="max-height: 180px; overflow-y: auto;"></div>
                        <div class="small text-muted mt-1" id="recipeUnspscChosen">Search and select a commodity code, then optionally register it.</div>
                        <?php if ($canRegisterZra): ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="register_zra" value="1" id="recipeRegisterZra">
                            <label class="form-check-label small" for="recipeRegisterZra">Register this item with ZRA Smart Invoice</label>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div id="recipeRowsContainer">
                        </div>

                    <button type="button" class="btn btn-outline-primary btn-sm fw-bold mt-2" onclick="addRecipeRow()"><i class="bi bi-plus-circle"></i> Add Ingredient</button>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-info fw-bold shadow-sm"><i class="bi bi-check2-circle"></i> Save Recipe Configuration</button>
                </div>
            </form>
        </div>
    </div>
</div>

<template id="recipeRowTemplate">
    <div class="row g-2 mb-2 align-items-center recipe-row border p-2 bg-white rounded shadow-sm">
        <div class="col-7">
            <label class="small text-muted fw-bold mb-1">Raw Ingredient</label>
            <select name="ingredient_id[]" class="form-select fw-bold" required>
                <option value="">Select ingredient from inventory...</option>
                <?php foreach($allIngredients as $ing): ?>
                    <option value="<?= $ing['id'] ?>" data-unit="<?= htmlspecialchars((string)$ing['unit']) ?>" data-sku="<?= htmlspecialchars((string)($ing['sku'] ?? '')) ?>"><?= htmlspecialchars($ing['name']) ?> (<?= htmlspecialchars($ing['unit']) ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-4">
            <label class="small text-muted fw-bold mb-1">Qty to Deduct</label>
            <input type="number" step="0.0001" name="ingredient_qty[]" class="form-control fw-bold text-danger text-center" placeholder="e.g. 0.05" required>
        </div>
        <div class="col-1 text-end mt-4">
            <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="this.closest('.recipe-row').remove()"><i class="bi bi-trash fs-5"></i></button>
        </div>
    </div>
</template>

<script src="js/unspsc-picker.js"></script>
<script>
// Load grouped recipes from PHP into Javascript
const existingRecipes = <?= json_encode($recipesGrouped) ?>;

function filterMenu() {
    let search = document.getElementById('menuSearch').value.toLowerCase();
    let category = document.getElementById('menuCategoryFilter').value;
    let rows = document.querySelectorAll('.menu-row');
    let visibleCount = 0;

    rows.forEach(row => {
        let name = row.getAttribute('data-name');
        let cat = row.getAttribute('data-category');
        
        let matchesSearch = name.includes(search);
        let matchesCategory = (category === 'all' || cat === category);
        
        if (matchesSearch && matchesCategory) {
            row.style.display = '';
            visibleCount++;
        } else {
            row.style.display = 'none';
        }
    });

    document.getElementById('noResultsRow').style.display = (visibleCount === 0) ? '' : 'none';
}

function editItem(m) {
    document.getElementById('itemId').value = m.id;
    document.getElementById('itemName').value = m.name;
    document.getElementById('itemCat').value = m.category_id;
    document.getElementById('itemPrice').value = m.price;
    document.getElementById('itemCost').value = m.cost_price;
    document.getElementById('menuUnspscCode').value = m.unspsc_code || '';
    document.getElementById('menuUnspscSearch').value = m.unspsc_code
        ? (m.unspsc_code + (m.unspsc_name ? ' ' + m.unspsc_name : ''))
        : '';
    document.getElementById('menuUnspscChosen').textContent = m.unspsc_code
        ? ('Selected: ' + m.unspsc_code + (m.unspsc_name ? ' — ' + m.unspsc_name : ''))
        : 'Optional — required for ZRA registration.';
    
    document.getElementById('formTitle').innerText = "Edit Menu Item";
    document.getElementById('btnSubmit').innerText = "Update Item";
    document.getElementById('btnSubmit').classList.replace('btn-success', 'btn-warning');
    document.getElementById('btnCancel').classList.remove('d-none');
}

function resetForm() {
    document.getElementById('itemId').value = '';
    document.getElementById('itemName').value = '';
    document.getElementById('itemPrice').value = '';
    document.getElementById('itemCost').value = '';
    document.getElementById('menuUnspscCode').value = '';
    document.getElementById('menuUnspscSearch').value = '';
    document.getElementById('menuUnspscChosen').textContent = 'Optional — required for ZRA registration.';
    
    document.getElementById('formTitle').innerText = "Add Menu Item";
    document.getElementById('btnSubmit').innerText = "Save Item";
    document.getElementById('btnSubmit').classList.replace('btn-warning', 'btn-success');
    document.getElementById('btnCancel').classList.add('d-none');
}

function openRecipeBuilder(productId, productName, unspscCode = '', unspscName = '') {
    document.getElementById('recipeParentId').value = productId;
    document.getElementById('recipeItemName').innerText = productName;
    document.getElementById('recipeUnspscCode').value = unspscCode || '';
    document.getElementById('recipeUnspscSearch').value = unspscCode
        ? (unspscCode + (unspscName ? ' ' + unspscName : ''))
        : '';
    document.getElementById('recipeUnspscChosen').textContent = unspscCode
        ? ('Selected: ' + unspscCode + (unspscName ? ' — ' + unspscName : ''))
        : 'Search and select a commodity code, then optionally register it.';
    
    const container = document.getElementById('recipeRowsContainer');
    container.innerHTML = ''; 
    
    if (existingRecipes[productId] && existingRecipes[productId].length > 0) {
        existingRecipes[productId].forEach(ing => {
            addRecipeRow(ing.ingredient_product_id, ing.quantity);
        });
    } else {
        addRecipeRow();
    }
    
    new bootstrap.Modal(document.getElementById('recipeModal')).show();
}

function addRecipeRow(ingId = '', qty = '') {
    const template = document.getElementById('recipeRowTemplate').content.cloneNode(true);
    if (ingId) {
        template.querySelector('select').value = ingId;
        template.querySelector('input').value = qty;
    }
    document.getElementById('recipeRowsContainer').appendChild(template);
}

bindUnspscPicker(
    document.getElementById('menuUnspscSearch'),
    document.getElementById('menuUnspscResults'),
    document.getElementById('menuUnspscCode'),
    document.getElementById('menuUnspscChosen'),
    'index.php?page=menu&ajax=unspsc_search'
);
bindUnspscPicker(
    document.getElementById('recipeUnspscSearch'),
    document.getElementById('recipeUnspscResults'),
    document.getElementById('recipeUnspscCode'),
    document.getElementById('recipeUnspscChosen'),
    'index.php?page=menu&ajax=unspsc_search'
);
</script>
