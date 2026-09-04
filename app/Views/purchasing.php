<?php
$tab = $tab ?? 'inquiry';
$bayPrefill = is_array($bayPrefill ?? null) ? $bayPrefill : [];
$grvVendorId = (int)($bayPrefill['vendor_id'] ?? $receiveOrder['vendor_id'] ?? 0);
$grvLocationId = (int)($bayPrefill['location_id'] ?? $receiveOrder['location_id'] ?? $currentLocId ?? 0);
$grvRef = (string)($bayPrefill['ref'] ?? $receiveOrder['reference_no'] ?? '');
$productsJson = [];
foreach ($products as $p) {
    $raw = \App\Helpers\Movement::isRawProduct($p);
    $productsJson[] = [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'sku' => $p['sku'] ?? '',
        'cost' => (float)($p['cost_price'] ?? 0),
        'is_raw' => $raw,
        'reason' => $raw ? 'Raw food must stay in kitchen or warehouse, not a POS bar or store.' : '',
    ];
}
$locationsJson = [];
foreach ($locations as $l) {
    $locationsJson[] = [
        'id' => (int)$l['id'],
        'name' => $l['name'],
        'type' => $l['type'] ?? '',
        'is_pos' => \App\Helpers\Movement::isFrontOfHouse($l),
    ];
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1"><i class="bi bi-clipboard-check text-primary"></i> Purchasing</h3>
        <p class="text-muted mb-0">Inquiry → order → goods received from vendors.</p>
    </div>
    <a href="index.php?page=vendors" class="btn btn-outline-secondary fw-bold"><i class="bi bi-truck"></i> Vendors</a>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'inquiry' ? 'active' : '' ?>" href="index.php?page=purchasing&tab=inquiry">
            <i class="bi bi-question-circle"></i> Purchase Inquiry
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'orders' ? 'active' : '' ?>" href="index.php?page=purchasing&tab=orders">
            <i class="bi bi-receipt"></i> Orders
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'received' ? 'active' : '' ?>" href="index.php?page=purchasing&tab=received">
            <i class="bi bi-box-seam"></i> Goods Received
        </a>
    </li>
</ul>

<?php if ($tab === 'inquiry'): ?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 border-top border-primary border-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><?= $editInquiry ? 'Edit inquiry #' . (int)$editInquiry['id'] : 'New purchase inquiry' ?></h5>
                <form method="POST">
                    <input type="hidden" name="save_inquiry" value="1">
                    <input type="hidden" name="inquiry_id" value="<?= (int)($editInquiry['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">VENDOR</label>
                        <select name="vendor_id" class="form-select">
                            <option value="">To be assigned</option>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= ((int)($editInquiry['vendor_id'] ?? 0) === (int)$v['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label small fw-bold text-muted">RECEIVE INTO</label>
                            <select name="location_id" class="form-select" required>
                                <?php foreach ($locations as $l): ?>
                                    <option value="<?= (int)$l['id'] ?>" <?= ((int)($editInquiry['location_id'] ?? $currentLocId) === (int)$l['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($l['name']) ?> (<?= htmlspecialchars($l['type'] ?? '') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold text-muted">NEEDED BY</label>
                            <input type="text" name="needed_by" class="form-control datepicker" value="<?= htmlspecialchars($editInquiry['needed_by'] ?? '') ?>" autocomplete="off">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">NOTES</label>
                        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editInquiry['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <label class="form-label small fw-bold text-muted mb-0">ITEMS</label>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addLine('inquiryLines')"><i class="bi bi-plus"></i> Line</button>
                    </div>
                    <div id="inquiryLines"></div>
                    <div class="d-flex gap-2 mt-3">
                        <button name="status" value="draft" class="btn btn-outline-secondary fw-bold flex-fill">Save draft</button>
                        <button name="status" value="sent" class="btn btn-primary fw-bold flex-fill">Send inquiry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light"><tr><th>Inquiry</th><th>Vendor</th><th>Items</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($inquiries as $row): ?>
                        <tr>
                            <td class="fw-bold">#<?= (int)$row['id'] ?><div class="small text-muted"><?= htmlspecialchars($row['location_name']) ?></div></td>
                            <td><?= htmlspecialchars($row['vendor_name'] ?: 'Unassigned') ?></td>
                            <td><?= (int)$row['item_count'] ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['status']) ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="index.php?page=purchasing&tab=inquiry&inquiry_id=<?= (int)$row['id'] ?>">Open</a>
                                <?php if (!in_array($row['status'], ['converted', 'cancelled'], true)): ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="convert_inquiry" value="1">
                                    <input type="hidden" name="inquiry_id" value="<?= (int)$row['id'] ?>">
                                    <button class="btn btn-sm btn-success">To order</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($inquiries)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No inquiries yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'orders'): ?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 border-top border-success border-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><?= $editOrder ? 'Edit order #' . (int)$editOrder['id'] : 'New purchase order' ?></h5>
                <form method="POST">
                    <input type="hidden" name="save_order" value="1">
                    <input type="hidden" name="order_id" value="<?= (int)($editOrder['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">VENDOR</label>
                        <select name="vendor_id" class="form-select" required>
                            <option value="">Select vendor…</option>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" <?= ((int)($editOrder['vendor_id'] ?? 0) === (int)$v['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label small fw-bold text-muted">DELIVER TO</label>
                            <select name="location_id" class="form-select" required>
                                <?php foreach ($locations as $l): ?>
                                    <option value="<?= (int)$l['id'] ?>" <?= ((int)($editOrder['location_id'] ?? $currentLocId) === (int)$l['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($l['name']) ?> (<?= htmlspecialchars($l['type'] ?? '') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold text-muted">REFERENCE</label>
                            <input type="text" name="reference_no" class="form-control" value="<?= htmlspecialchars($editOrder['reference_no'] ?? '') ?>" placeholder="PO-…">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">NOTES</label>
                        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editOrder['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <label class="form-label small fw-bold text-muted mb-0">ORDER LINES</label>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="addLine('orderLines', true)"><i class="bi bi-plus"></i> Line</button>
                    </div>
                    <div id="orderLines"></div>
                    <button class="btn btn-success w-100 fw-bold mt-3">Save order</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light"><tr><th>Order</th><th>Vendor</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $row): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($row['reference_no'] ?: ('#' . $row['id'])) ?>
                                <div class="small text-muted"><?= htmlspecialchars($row['location_name']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($row['vendor_name']) ?></td>
                            <td>ZMW <?= number_format((float)$row['total_cost'], 2) ?></td>
                            <td><span class="badge <?= $row['status'] === 'received' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                            <td class="text-end">
                                <?php if (in_array($row['status'], ['pending', 'partial'], true)): ?>
                                    <a class="btn btn-sm btn-primary" href="index.php?page=purchasing&tab=received&receive_order=<?= (int)$row['id'] ?>">Receive</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No purchase orders yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'received'): ?>
<div class="card shadow-sm border-0 border-top border-warning border-4">
    <div class="card-body">
        <h5 class="fw-bold mb-3">Goods received from vendor</h5>
        <?php if (!empty($bayPrefill['tracking_code'])): ?>
        <div class="alert alert-warning small">
            From receiving bay <strong><?= htmlspecialchars((string)$bayPrefill['tracking_code']) ?></strong>
            <?php if (!empty($bayPrefill['source_name'])): ?>
                · <?= htmlspecialchars((string)$bayPrefill['source_name']) ?>
            <?php endif; ?>
            . Review the lines, then Process GRV to book stock.
        </div>
        <?php endif; ?>
        <div class="alert alert-info small">
            Raw food can only be received into kitchen or warehouse. POS bars/stores are blocked for those items.
            <i class="bi bi-info-circle" title="Finished goods may still go to a POS location."></i>
        </div>
        <form method="POST" id="grvForm">
            <input type="hidden" name="process_grv" value="1">
            <?php if (!empty($bayPrefill['tracking_code'])): ?>
            <input type="hidden" name="dock_id" value="<?= (int)($_GET['dock_id'] ?? 0) ?>">
            <?php endif; ?>
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">PURCHASE ORDER</label>
                    <select name="purchase_order_id" id="poSelect" class="form-select">
                        <option value="">Walk-in / no PO</option>
                        <?php foreach ($openOrders as $o): ?>
                            <option value="<?= (int)$o['id'] ?>" <?= ((int)($receiveOrder['id'] ?? 0) === (int)$o['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($o['reference_no'] ?: ('#' . $o['id'])) . ' — ' . $o['vendor_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">VENDOR</label>
                    <select name="vendor_id" id="grvVendor" class="form-select" required>
                        <option value="">Select vendor…</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= (int)$v['id'] ?>" <?= ($grvVendorId === (int)$v['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">RECEIVE INTO</label>
                    <select name="location_id" id="grvLocation" class="form-select" required>
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= (int)$l['id'] ?>" data-type="<?= htmlspecialchars($l['type'] ?? '') ?>" <?= ($grvLocationId === (int)$l['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['name']) ?> (<?= htmlspecialchars($l['type'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">INVOICE / REF</label>
                    <input type="text" name="ref_number" class="form-control" value="<?= htmlspecialchars($grvRef) ?>">
                </div>
            </div>
            <div id="moveWarn" class="alert alert-warning d-none py-2 small"></div>
            <div class="row g-2 mb-1 small fw-bold text-muted">
                <div class="col-6">Product</div>
                <div class="col-3">Qty</div>
                <div class="col-2">Unit cost</div>
            </div>
            <div id="grvLines"></div>
            <button type="button" class="btn btn-outline-secondary fw-bold mt-2" onclick="addLine('grvLines', true)"><i class="bi bi-plus"></i> Add item</button>
            <button class="btn btn-warning fw-bold float-end">Process GRV</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
const catalog = <?= json_encode($productsJson) ?>;
const locationsMeta = <?= json_encode($locationsJson) ?>;
const inquiryPrefill = <?= json_encode($editInquiry['items'] ?? []) ?>;
const orderPrefill = <?= json_encode($editOrder['items'] ?? $receiveOrder['items'] ?? []) ?>;
const bayPrefillItems = <?= json_encode(($bayPrefill ?? [])['items'] ?? []) ?>;

function productOptions(selected, withCost) {
    return catalog.map(function (p) {
        const sel = String(p.id) === String(selected) ? ' selected' : '';
        const raw = p.is_raw ? ' data-raw="1" data-reason="' + (p.reason || '').replace(/"/g, '&quot;') + '"' : '';
        const label = p.name + (p.is_raw ? ' — RAW' : '');
        const cost = withCost ? ' data-cost="' + p.cost + '"' : '';
        return '<option value="' + p.id + '"' + sel + raw + cost + '>' + label + '</option>';
    }).join('');
}

function addLine(containerId, withCost, preset) {
    const wrap = document.getElementById(containerId);
    if (!wrap) return;
    const idx = wrap.children.length;
    const row = document.createElement('div');
    row.className = 'row g-2 mb-2 align-items-center purchase-line';
    const pid = preset ? (preset.product_id || '') : '';
    const qty = preset ? (preset.quantity || preset.qty || '') : '';
    const cost = preset ? (preset.unit_cost || preset.cost_price || preset.cost || '') : '';
    let html = '<div class="' + (withCost ? 'col-6' : 'col-8') + '"><select name="items[' + idx + '][product_id]" class="form-select form-select-sm js-prod" required><option value="">Select…</option>'
        + productOptions(pid, withCost) + '</select></div>';
    html += '<div class="col-3"><input type="number" step="0.01" min="0.01" name="items[' + idx + '][qty]" class="form-control form-control-sm" required placeholder="Qty" value="' + qty + '"></div>';
    if (withCost) {
        html += '<div class="col-2"><input type="number" step="0.01" name="items[' + idx + '][cost]" class="form-control form-control-sm js-cost" placeholder="Cost" value="' + cost + '"></div>';
    }
    html += '<div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'.purchase-line\').remove()"><i class="bi bi-trash"></i></button></div>';
    html += '<div class="col-12 small text-muted js-line-info"></div>';
    row.innerHTML = html;
    wrap.appendChild(row);
    row.querySelector('.js-prod').addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const costEl = row.querySelector('.js-cost');
        if (costEl && opt && opt.dataset.cost) costEl.value = opt.dataset.cost;
        refreshMovementHint();
    });
    refreshMovementHint();
}

function locIsPos(id) {
    const loc = locationsMeta.find(function (l) { return String(l.id) === String(id); });
    return !!(loc && loc.is_pos);
}

function refreshMovementHint() {
    const locEl = document.getElementById('grvLocation') || document.querySelector('select[name="location_id"]');
    const warn = document.getElementById('moveWarn');
    if (!locEl) return;
    const pos = locIsPos(locEl.value);
    document.querySelectorAll('.js-prod').forEach(function (sel) {
        const opt = sel.options[sel.selectedIndex];
        const info = sel.closest('.purchase-line, tr') ? sel.closest('.purchase-line, tr').querySelector('.js-line-info') : null;
        const raw = opt && opt.dataset.raw === '1';
        Array.from(sel.options).forEach(function (o) {
            if (!o.value) return;
            const blocked = pos && o.dataset.raw === '1';
            o.disabled = blocked;
            o.style.color = blocked ? '#aaa' : '';
        });
        if (info) {
            if (raw && pos) {
                info.innerHTML = '<i class="bi bi-info-circle text-info"></i> ' + (opt.dataset.reason || 'Raw food cannot go to a POS location.');
            } else if (raw) {
                info.innerHTML = '<i class="bi bi-info-circle text-info"></i> Raw item — keep in kitchen/warehouse.';
            } else {
                info.innerHTML = '';
            }
        }
        if (raw && pos) sel.value = '';
    });
    if (warn) {
        warn.classList.toggle('d-none', !pos);
        warn.innerHTML = pos
            ? '<i class="bi bi-info-circle"></i> This location is a POS. Raw food lines are disabled. Receive those into kitchen or warehouse instead.'
            : '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const inquiryBox = document.getElementById('inquiryLines');
    const orderBox = document.getElementById('orderLines');
    const grvBox = document.getElementById('grvLines');
    if (inquiryBox) {
        if (inquiryPrefill.length) inquiryPrefill.forEach(function (r) { addLine('inquiryLines', false, r); });
        else addLine('inquiryLines');
    }
    if (orderBox) {
        if (orderPrefill.length && <?= json_encode($tab === 'orders') ?>) orderPrefill.forEach(function (r) { addLine('orderLines', true, r); });
        else if (<?= json_encode($tab === 'orders') ?>) addLine('orderLines', true);
    }
    if (grvBox) {
        if (bayPrefillItems.length) {
            bayPrefillItems.forEach(function (r) {
                addLine('grvLines', true, { product_id: r.product_id, quantity: r.qty, unit_cost: r.cost });
            });
        } else if (orderPrefill.length && <?= json_encode($tab === 'received') ?>) {
            orderPrefill.forEach(function (r) {
                const remain = Math.max(0, parseFloat(r.quantity || 0) - parseFloat(r.received_qty || 0));
                if (remain > 0) addLine('grvLines', true, { product_id: r.product_id, quantity: remain, unit_cost: r.unit_cost });
            });
        } else {
            addLine('grvLines', true);
        }
    }
    const loc = document.getElementById('grvLocation') || document.querySelector('select[name="location_id"]');
    if (loc) loc.addEventListener('change', refreshMovementHint);
    refreshMovementHint();
});
</script>
