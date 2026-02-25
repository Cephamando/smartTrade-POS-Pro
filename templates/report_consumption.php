<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0"><i class="bi bi-moisture text-info"></i> Recipe Consumption & Variance</h3>
        <span class="text-muted small">Track automatic ingredient deductions and backflushed stock.</span>
    </div>
    <a href="index.php?page=receive_stock" class="btn btn-primary fw-bold shadow-sm"><i class="bi bi-box-arrow-in-down"></i> Restock Inventory</a>
</div>

<div class="card shadow-sm border-0 mb-4">
    <div class="card-body bg-light">
        <form method="POST" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">Start Date</label>
                <input type="date" name="start_date" class="form-control fw-bold" value="<?= htmlspecialchars($filterStart) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted">End Date</label>
                <input type="date" name="end_date" class="form-control fw-bold" value="<?= htmlspecialchars($filterEnd) ?>" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">Filter Workstation</label>
                <select name="location_id" class="form-select fw-bold">
                    <?php foreach($locations as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= ($l['id'] == $selectedLoc) ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-dark w-100 fw-bold"><i class="bi bi-funnel-fill"></i> Generate</button>
            </div>
        </form>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-5">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-header bg-dark text-white fw-bold">
                <i class="bi bi-cup-straw"></i> Cocktails & Meals Sold
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 small text-muted text-uppercase">Menu Item</th>
                            <th class="text-end pe-3 small text-muted text-uppercase">Qty Sold</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cocktailsSold)): ?>
                            <tr><td colspan="2" class="text-center text-muted py-4 fst-italic">No recipe items sold in this period.</td></tr>
                        <?php else: foreach($cocktailsSold as $c): ?>
                            <tr>
                                <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($c['cocktail_name']) ?></td>
                                <td class="text-end pe-3 fw-bold fs-5"><?= number_format($c['total_sold']) ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="card shadow-sm border-0 border-top border-info border-4 h-100">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span><i class="bi bi-droplet-half text-info"></i> Raw Ingredients Depleted</span>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3 small text-muted text-uppercase">Raw Ingredient</th>
                            <th class="small text-muted text-uppercase">Total Consumed</th>
                            <th class="text-end pe-3 small text-muted text-uppercase">Current Stock Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($ingredientsConsumed)): ?>
                            <tr><td colspan="3" class="text-center text-muted py-4 fst-italic">No raw ingredients deducted.</td></tr>
                        <?php else: foreach($ingredientsConsumed as $ing): 
                            $isNegative = ($ing['current_stock'] < 0);
                        ?>
                            <tr class="<?= $isNegative ? 'table-danger' : '' ?>">
                                <td class="ps-3 fw-bold"><?= htmlspecialchars($ing['raw_name']) ?></td>
                                <td class="text-danger fw-bold">
                                    <i class="bi bi-arrow-down-right"></i> <?= number_format($ing['theoretical_usage'], 4) ?> <span class="small text-muted"><?= htmlspecialchars($ing['unit']) ?></span>
                                </td>
                                <td class="text-end pe-3">
                                    <?php if ($isNegative): ?>
                                        <div class="text-danger fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> <?= number_format($ing['current_stock'], 4) ?></div>
                                        <span class="badge bg-danger mt-1">Needs Receiving</span>
                                    <?php else: ?>
                                        <div class="text-success fw-bold"><?= number_format($ing['current_stock'], 4) ?></div>
                                        <span class="badge bg-success mt-1">Stock OK</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
