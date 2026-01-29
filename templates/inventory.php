<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>📊 Stock Levels</h3>
    
    <?php if(!empty($allLocations)): ?>
    <form method="GET" class="d-flex align-items-center">
        <input type="hidden" name="page" value="inventory">
        <select name="loc" class="form-select me-2" onchange="this.form.submit()">
            <?php foreach($allLocations as $l): ?>
                <option value="<?= $l['id'] ?>" <?= $l['id'] == $selectedLoc ? 'selected' : '' ?>>
                    <?= htmlspecialchars($l['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>
    <?php else: ?>
        <span class="badge bg-primary fs-6"><?= htmlspecialchars($locName) ?></span>
    <?php endif; ?>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead class="bg-dark text-white">
                <tr>
                    <th>Product</th>
                    <th>Category</th>
                    <th class="text-end">Quantity On Hand</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($stock as $item): ?>
                <tr>
                    <td>
                        <span class="fw-bold"><?= htmlspecialchars($item['name']) ?></span>
                        <?php if($item['sku']): ?>
                            <br><small class="text-muted">SKU: <?= htmlspecialchars($item['sku']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><small class="text-muted"><?= htmlspecialchars($item['category'] ?? '-') ?></small></td>
                    
                    <td class="text-end">
                        <?php 
                            $qty = floatval($item['qty']);
                            $class = $qty <= 5 ? 'text-danger fw-bold' : 'text-dark';
                            if ($qty == 0) $class = 'text-muted opacity-50';
                        ?>
                        <span class="<?= $class ?> fs-5">
                            <?= $qty + 0 ?> 
                        </span>
                        <small class="text-muted"><?= htmlspecialchars($item['unit']) ?></small>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>