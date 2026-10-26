<?php
$money = static function (float $amount): string {
    return 'ZMW ' . number_format($amount, 2);
};
$snapshot = $snapshot ?? [];
?>
<div class="d-flex justify-content-between align-items-center mb-3 gap-2">
    <div>
        <h3 class="fw-bold mb-0"><i class="bi bi-building"></i> Assets</h3>
        <div class="text-muted small">Straight-line depreciation. Business value adds stock, cash, mobile money, bank, and unpaid customer bills, then takes off unpaid supplier bills and unpaid wages.</div>
    </div>
    <button type="button" class="btn btn-dark fw-bold" data-bs-toggle="modal" data-bs-target="#assetModal" onclick="assetForm()">Add asset</button>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Asset cost</div><div class="h4 mb-0"><?= $money((float)($snapshot['asset_cost'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Depreciation</div><div class="h4 mb-0"><?= $money((float)($snapshot['depreciation'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Stock at cost</div><div class="h4 mb-0"><?= $money((float)($snapshot['stock'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Cash</div><div class="h4 mb-0"><?= $money((float)($snapshot['cash'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Mobile money</div><div class="h4 mb-0"><?= $money((float)($snapshot['mobile'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Bank</div><div class="h4 mb-0"><?= $money((float)($snapshot['bank'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Debtors</div><div class="h4 mb-0"><?= $money((float)($snapshot['debtors'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Liabilities</div><div class="h4 mb-0"><?= $money((float)($snapshot['liabilities'] ?? 0)) ?></div></div></div></div>
    <div class="col-md-3"><div class="card border-0 shadow-sm h-100 border-start border-4 border-success"><div class="card-body"><div class="text-muted small text-uppercase fw-bold">Total business value</div><div class="h4 mb-0"><?= $money((float)($snapshot['business'] ?? 0)) ?></div><div class="small text-muted">After liabilities</div></div></div></div>
</div>

<div class="card border-0 shadow-sm">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead class="table-light small text-uppercase text-muted">
                <tr>
                    <th>Asset</th>
                    <th>Store</th>
                    <th>Acquired</th>
                    <th class="text-end">Cost</th>
                    <th class="text-end">Depreciation</th>
                    <th class="text-end">Book value</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php if (!$assets): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No assets yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($assets as $asset):
                $position = $asset['position'];
                $gone = !empty($asset['disposed_on']);
            ?>
                <tr class="<?= $gone ? 'text-muted' : '' ?>">
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars((string)$asset['name']) ?></div>
                        <div class="small"><?= htmlspecialchars(ucfirst((string)$asset['category'])) ?><?= $gone ? ' · disposed ' . htmlspecialchars(date('d M Y', strtotime((string)$asset['disposed_on']))) : '' ?></div>
                    </td>
                    <td><?= htmlspecialchars((string)($asset['location_name'] ?? 'All stores')) ?></td>
                    <td><?= htmlspecialchars(date('d M Y', strtotime((string)$asset['acquired_on']))) ?></td>
                    <td class="text-end"><?= $money((float)$asset['cost']) ?></td>
                    <td class="text-end"><?= $money((float)$position['accumulated']) ?></td>
                    <td class="text-end fw-bold"><?= $money((float)$position['book']) ?></td>
                    <td class="text-end">
                        <?php if (!$gone): ?>
                        <button type="button" class="btn btn-sm btn-outline-dark" onclick='assetForm(<?= json_encode([
                            'id' => (int)$asset['id'],
                            'name' => (string)$asset['name'],
                            'category' => (string)$asset['category'],
                            'location_id' => (int)($asset['location_id'] ?? 0),
                            'acquired_on' => (string)$asset['acquired_on'],
                            'cost' => (float)$asset['cost'],
                            'salvage' => (float)$asset['salvage'],
                            'life_months' => (int)$asset['life_months'],
                            'notes' => (string)($asset['notes'] ?? ''),
                        ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'>Edit</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="assetDispose(<?= (int)$asset['id'] ?>, <?= json_encode((string)$asset['name'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)">Dispose</button>
                        <?php elseif ($position['gain'] !== null): ?>
                        <span class="small"><?= ((float)$position['gain'] >= 0 ? 'Gain ' : 'Loss ') . $money(abs((float)$position['gain'])) ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="assetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" action="index.php?page=assets">
            <div class="modal-header"><h5 class="modal-title fw-bold" id="assetModalTitle">Add asset</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="id" id="assetId" value="0">
                <div class="mb-2"><label class="form-label small fw-bold">Name</label><input class="form-control" name="name" id="assetName" required></div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label small fw-bold">Category</label>
                        <select class="form-select" name="category" id="assetCategory">
                            <?php foreach ($categories as $category): ?>
                            <option value="<?= htmlspecialchars($category['key']) ?>"><?= htmlspecialchars($category['label']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-6"><label class="form-label small fw-bold">Store</label>
                        <select class="form-select" name="location_id" id="assetLocation">
                            <option value="0">All stores</option>
                            <?php foreach ($locations as $location): ?>
                            <option value="<?= (int)$location['id'] ?>"><?= htmlspecialchars((string)$location['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label small fw-bold">Acquired</label><input type="date" class="form-control" name="acquired_on" id="assetAcquired" required></div>
                    <div class="col-6"><label class="form-label small fw-bold">Useful life (months)</label><input type="number" class="form-control" name="life_months" id="assetLife" min="1" max="600" value="36" required></div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-6"><label class="form-label small fw-bold">Cost (ZMW)</label><input type="number" class="form-control" name="cost" id="assetCost" min="0" step="0.01" required></div>
                    <div class="col-6"><label class="form-label small fw-bold">Salvage (ZMW)</label><input type="number" class="form-control" name="salvage" id="assetSalvage" min="0" step="0.01" value="0" required></div>
                </div>
                <div class="mb-2"><label class="form-label small fw-bold">Notes</label><input class="form-control" name="notes" id="assetNotes"></div>
                <p class="small text-muted mb-0">Depreciation each month is the cost minus salvage, spread over the useful life. Book value is the cost minus what has been charged so far.</p>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-dark fw-bold">Save</button></div>
        </form>
    </div>
</div>

<div class="modal fade" id="disposeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" action="index.php?page=assets">
            <div class="modal-header"><h5 class="modal-title fw-bold">Dispose of <span id="disposeName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <input type="hidden" name="dispose_asset" value="1">
                <input type="hidden" name="id" id="disposeId">
                <div class="mb-2"><label class="form-label small fw-bold">Date</label><input type="date" class="form-control" name="disposed_on" required value="<?= date('Y-m-d') ?>"></div>
                <div class="mb-2"><label class="form-label small fw-bold">Proceeds (ZMW)</label><input type="number" class="form-control" name="disposal_proceeds" min="0" step="0.01" value="0" required></div>
                <p class="small text-muted mb-0">The asset leaves the business value. The gain or loss is the proceeds minus the book value on that date.</p>
            </div>
            <div class="modal-footer"><button type="submit" class="btn btn-danger fw-bold">Dispose</button></div>
        </form>
    </div>
</div>
<script>
function assetForm(asset) {
    asset = asset || {};
    document.getElementById('assetModalTitle').textContent = asset.id ? 'Edit asset' : 'Add asset';
    document.getElementById('assetId').value = asset.id || 0;
    document.getElementById('assetName').value = asset.name || '';
    document.getElementById('assetCategory').value = asset.category || 'equipment';
    document.getElementById('assetLocation').value = asset.location_id || 0;
    document.getElementById('assetAcquired').value = asset.acquired_on || '';
    document.getElementById('assetLife').value = asset.life_months || 36;
    document.getElementById('assetCost').value = asset.cost || '';
    document.getElementById('assetSalvage').value = asset.salvage || 0;
    document.getElementById('assetNotes').value = asset.notes || '';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('assetModal')).show();
}
function assetDispose(id, name) {
    document.getElementById('disposeId').value = id;
    document.getElementById('disposeName').textContent = name;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('disposeModal')).show();
}
</script>
