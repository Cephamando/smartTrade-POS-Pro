<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-0"><i class="bi bi-list-ul text-success"></i> Menu Builder</h3>
            <span class="text-muted small">Manage meal availability and pricing</span>
        </div>
        <a href="index.php?page=pos" class="btn btn-dark fw-bold shadow-sm"><i class="bi bi-cart4"></i> Go to POS</a>
    </div>

    <div class="row g-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-success text-white fw-bold py-3">
                    <i class="bi bi-plus-circle me-2"></i> Create New Meal
                </div>
                <div class="card-body bg-light">
                    <form method="POST">
                        <input type="hidden" name="add_meal" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">MEAL NAME</label>
                            <input type="text" name="name" class="form-control fw-bold" required placeholder="e.g., Spicy T-Bone">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">CATEGORY</label>
                            <select name="category_id" class="form-select fw-bold" required>
                                <?php foreach($foodCategories as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">SELLING PRICE (ZMW)</label>
                            <input type="number" step="0.01" name="price" class="form-control fw-bold text-primary" required placeholder="0.00">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">AVAILABLE AT STATION</label>
                            <select name="target_location" class="form-select fw-bold" required>
                                <?php foreach($sellableLocations as $l): ?>
                                <option value="<?= $l['id'] ?>" <?= $l['id'] == $targetLocId ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">INITIAL PORTIONS PREPARED</label>
                            <input type="number" name="initial_qty" class="form-control fw-bold" value="0" min="0" required>
                            <small class="text-muted" style="font-size:0.75rem;">Set to 0 if not yet ready for sale.</small>
                        </div>
                        
                        <button class="btn btn-success w-100 fw-bold py-2 shadow-sm">ADD TO MENU</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3 border-bottom">
                    <h5 class="fw-bold m-0"><i class="bi bi-sliders"></i> Adjust Portions</h5>
                    
                    <form method="GET" class="d-flex align-items-center m-0">
                        <input type="hidden" name="page" value="menu">
                        <label class="me-2 small fw-bold text-muted text-nowrap">Viewing Station:</label>
                        <select name="loc" class="form-select form-select-sm fw-bold border-secondary" onchange="this.form.submit()" style="width: auto;">
                            <?php foreach($sellableLocations as $l): ?>
                            <option value="<?= $l['id'] ?>" <?= $l['id'] == $targetLocId ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
                
                <div class="table-responsive" style="max-height: 600px; overflow-y: auto;">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light position-sticky top-0" style="z-index: 1;">
                            <tr>
                                <th class="text-uppercase small text-muted">Meal Item</th>
                                <th class="text-uppercase small text-muted">Category</th>
                                <th class="text-uppercase small text-muted">Price</th>
                                <th class="text-uppercase small text-muted text-end" style="width: 220px;">Available Portions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($meals as $m): $isOut = ($m['stock_qty'] <= 0); ?>
                            <tr class="<?= $isOut ? 'table-warning' : '' ?>">
                                <td class="fw-bold <?= $isOut ? 'text-muted' : 'text-dark' ?>">
                                    <?= htmlspecialchars($m['name']) ?>
                                    <?php if($isOut): ?><span class="badge bg-danger ms-2" style="font-size:0.6rem;">SOLD OUT</span><?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary"><?= htmlspecialchars($m['category_name']) ?></span></td>
                                <td class="fw-bold text-success">ZMW <?= number_format($m['price'], 2) ?></td>
                                <td>
                                    <form method="POST" class="d-flex justify-content-end">
                                        <input type="hidden" name="update_stock" value="1">
                                        <input type="hidden" name="product_id" value="<?= $m['id'] ?>">
                                        <input type="hidden" name="target_location" value="<?= $targetLocId ?>">
                                        
                                        <div class="input-group input-group-sm w-100 shadow-sm">
                                            <input type="number" name="quantity" class="form-control text-center fw-bold <?= $isOut ? 'text-danger' : '' ?>" value="<?= $m['stock_qty'] ?>" min="0">
                                            <button class="btn <?= $isOut ? 'btn-danger' : 'btn-primary' ?> fw-bold px-3">SET</button>
                                        </div>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            
                            <?php if(empty($meals)): ?>
                            <tr>
                                <td colspan="4" class="text-center p-5 text-muted">
                                    <i class="bi bi-cup-hot display-4 d-block mb-3 opacity-25"></i>
                                    No meals found for this station. Add a new meal using the form on the left.
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
