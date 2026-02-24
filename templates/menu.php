<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-book-half text-success"></i> Menu Builder</h3>
    <div>
        <a href="index.php?page=kitchen" class="btn btn-danger fw-bold"><i class="bi bi-fire"></i> Go to Produce</a>
        <a href="index.php?page=pos" class="btn btn-outline-secondary"><i class="bi bi-cart4"></i> POS</a>
    </div>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0 border-top border-success border-4">
            <div class="card-body">
                <h5 class="card-title fw-bold" id="formTitle">Add Menu Blueprint</h5>
                <p class="small text-muted mb-4">Create the item here, then use the <strong>Produce</strong> screen to add stock.</p>
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
                    <div class="row g-2 mb-4">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Selling Price (ZMW)</label>
                            <input type="number" step="0.01" name="price" id="itemPrice" class="form-control text-success fw-bold" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Cost Price (ZMW)</label>
                            <input type="number" step="0.01" name="cost_price" id="itemCost" class="form-control text-danger fw-bold">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-success w-100 fw-bold" id="btnSubmit">Save Blueprint</button>
                    <button type="button" class="btn btn-outline-secondary w-100 mt-2 d-none" id="btnCancel" onclick="resetForm()">Cancel Edit</button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 text-uppercase small text-muted">Menu Item</th>
                            <th class="text-uppercase small text-muted">Category</th>
                            <th class="text-end text-uppercase small text-muted">Cost</th>
                            <th class="text-end text-uppercase small text-muted">Price</th>
                            <th class="text-end pe-3 text-uppercase small text-muted">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($menuItems as $m): ?>
                        <tr>
                            <td class="ps-3 fw-bold"><?= htmlspecialchars($m['name']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($m['cat_name']) ?></span></td>
                            <td class="text-end text-danger small">ZMW <?= number_format($m['cost_price'], 2) ?></td>
                            <td class="text-end text-success fw-bold">ZMW <?= number_format($m['price'], 2) ?></td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-primary" onclick='editItem(<?= json_encode($m) ?>)'><i class="bi bi-pencil"></i></button>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Remove from menu?');">
                                    <input type="hidden" name="delete_item" value="1">
                                    <input type="hidden" name="item_id" value="<?= $m['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editItem(m) {
    document.getElementById('itemId').value = m.id;
    document.getElementById('itemName').value = m.name;
    document.getElementById('itemCat').value = m.category_id;
    document.getElementById('itemPrice').value = m.price;
    document.getElementById('itemCost').value = m.cost_price;
    
    document.getElementById('formTitle').innerText = "Edit Blueprint";
    document.getElementById('btnSubmit').innerText = "Update Blueprint";
    document.getElementById('btnSubmit').classList.replace('btn-success', 'btn-warning');
    document.getElementById('btnCancel').classList.remove('d-none');
}
function resetForm() {
    document.getElementById('itemId').value = '';
    document.getElementById('itemName').value = '';
    document.getElementById('itemPrice').value = '';
    document.getElementById('itemCost').value = '';
    
    document.getElementById('formTitle').innerText = "Add Menu Blueprint";
    document.getElementById('btnSubmit').innerText = "Save Blueprint";
    document.getElementById('btnSubmit').classList.replace('btn-warning', 'btn-success');
    document.getElementById('btnCancel').classList.add('d-none');
}

<?php if(isset($_SESSION['swal_msg'])): ?>
Swal.fire({ icon: '<?= $_SESSION['swal_type'] ?>', title: '<?= $_SESSION['swal_msg'] ?>', timer: 2000, showConfirmButton: false });
<?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
<?php endif; ?>
</script>
