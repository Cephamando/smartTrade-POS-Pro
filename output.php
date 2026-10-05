mando@DESKTOP-BJEA018:~/Odelia/Projects/smartTrade/smartTrade-POS-Pro$ echo "=== PURCHASING VIEW ==="
cat app/Views/purchasing.php

echo "=== PURCHASING CONTROLLER ==="
cat app/Controllers/PurchasingController.php 2>/dev/null

echo "=== REPORTS VIEW ==="
cat app/Views/reports.php

echo "=== REPORTS CONTROLLER ==="
cat app/Controllers/ReportsController.php 2>/dev/null
=== PURCHASING VIEW ===
<?php
$tab = $tab ?? 'inquiry';
$bayPrefill = is_array($bayPrefill ?? null) ? $bayPrefill : [];

$grvPoId = 0;
if (!empty($bayPrefill['purchase_order_id'])) {
    $grvPoId = (int)$bayPrefill['purchase_order_id'];
} elseif (!empty($receiveOrder['id'])) {
    $grvPoId = (int)$receiveOrder['id'];
}

$grvVendorId = 0;
if (!empty($bayPrefill['vendor_id'])) {
    $grvVendorId = (int)$bayPrefill['vendor_id'];
} elseif (!empty($receiveOrder['vendor_id'])) {
    $grvVendorId = (int)$receiveOrder['vendor_id'];
}

$grvLocationId = 0;
if (!empty($bayPrefill['location_id'])) {
    $grvLocationId = (int)$bayPrefill['location_id'];
} elseif (!empty($receiveOrder['location_id'])) {
    $grvLocationId = (int)$receiveOrder['location_id'];
} elseif (!empty($currentLocId)) {
    $grvLocationId = (int)$currentLocId;
}

$grvRef = '';
if (!empty($bayPrefill['ref'])) {
    $grvRef = (string)$bayPrefill['ref'];
} elseif (!empty($receiveOrder['reference_no'])) {
    $grvRef = (string)$receiveOrder['reference_no'];
}

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
                                <form method="POST" class="d-inline js-confirm-form"
                                    data-swal-title="Convert inquiry #<?= (int)$row['id'] ?> to an order?"
                                    data-swal-text="This inquiry will become a purchase order."
                                    data-swal-confirm="Convert"
                                    data-swal-cancel="Cancel"
                                    data-swal-icon="question"
                                    data-swal-confirm-color="#198754">
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
                    <select name="purchase_order_id" id="poSelect" class="form-select" onchange="updatePoSelection(this.value)">
                        <option value="">Walk-in / no PO</option>
                        <?php foreach ($openOrders as $o): ?>
                            <option value="<?= (int)$o['id'] ?>" <?= ($grvPoId === (int)$o['id']) ? 'selected' : '' ?>>
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

function updatePoSelection(poId) {
    let url = new URL(window.location.href);
    url.searchParams.set('tab', 'received');
    if (poId) {
        url.searchParams.set('receive_order', poId);
    } else {
        url.searchParams.delete('receive_order');
    }
    window.location.href = url.toString();
}

function toggleSellable(pid, currentIsSellable) {
    if (!pid) return;
    const newStatus = currentIsSellable ? 0 : 1;
    const text = newStatus
        ? "Mark this product as SELLABLE on the POS?"
        : "Mark this product as RAW MATERIAL (greyed out on POS)?";

    Swal.fire({
        title: 'Update POS Visibility',
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, update it',
        confirmButtonColor: newStatus ? '#198754' : '#dc3545'
    }).then(result => {
        if (result.isConfirmed) {
            fetch('index.php?page=purchasing', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'toggle_sellable=1&product_id=' + pid + '&is_sellable=' + newStatus
            }).then(r => r.json()).then(res => {
                if (res.status === 'success') {
                    const p = catalog.find(item => String(item.id) === String(pid));
                    if (p) {
                        p.is_raw = (newStatus === 0);
                        p.is_sellable = newStatus;
                    }
                    document.querySelectorAll('.js-prod').forEach(sel => {
                        Array.from(sel.options).forEach(opt => {
                            if (String(opt.value) === String(pid)) {
                                opt.dataset.raw = (newStatus === 0) ? '1' : '0';
                                opt.text = (p ? p.name : opt.text.replace(' — RAW', '')) + (newStatus === 0 ? ' — RAW' : '');
                            }
                        });
                    });
                    refreshMovementHint();
                    AppAlert.notify({icon: 'success', text: 'POS visibility updated!'});
                } else {
                    AppAlert.notify({icon: 'error', text: res.message ? res.message : 'Failed to update.'});
                }
            });
        }
    });
}

function productOptions(selected, withCost) {
    return catalog.map(function (p) {
        const sel = String(p.id) === String(selected) ? ' selected' : '';
        const pReason = p.reason ? p.reason : '';
        const raw = p.is_raw ? ' data-raw="1" data-reason="' + pReason.replace(/"/g, '&quot;') + '"' : '';
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

    const pid = preset ? (preset.product_id ? preset.product_id : '') : '';
    const qty = preset ? (preset.quantity ? preset.quantity : (preset.qty ? preset.qty : '')) : '';
    const cost = preset ? (preset.unit_cost ? preset.unit_cost : (preset.cost_price ? preset.cost_price : (preset.cost ? preset.cost : ''))) : '';

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
        if (costEl) {
            if (opt) {
                if (opt.dataset.cost) {
                    costEl.value = opt.dataset.cost;
                }
            }
        }
        refreshMovementHint();
    });
    refreshMovementHint();
}

function locIsPos(id) {
    const loc = locationsMeta.find(function (l) { return String(l.id) === String(id); });
    return loc && loc.is_pos ? true : false;
}

function refreshMovementHint() {
    let locEl = document.getElementById('grvLocation');
    if (!locEl) {
        locEl = document.querySelector('select[name="location_id"]');
    }
    const warn = document.getElementById('moveWarn');
    if (!locEl) return;
    const pos = locIsPos(locEl.value);
    document.querySelectorAll('.js-prod').forEach(function (sel) {
        const opt = sel.options[sel.selectedIndex];
        let info = null;
        if (sel.closest('.purchase-line')) {
            info = sel.closest('.purchase-line').querySelector('.js-line-info');
        } else if (sel.closest('tr')) {
            info = sel.closest('tr').querySelector('.js-line-info');
        }

        const raw = opt && opt.dataset.raw === '1' ? true : false;
        const pid = opt && opt.value ? opt.value : null;

        Array.from(sel.options).forEach(function (o) {
            if (!o.value) return;
            const blocked = pos && o.dataset.raw === '1';
            o.disabled = blocked;
            o.style.color = blocked ? '#aaa' : '';
        });
        if (info) {
            const toggleLnk = pid ? ` <a href="javascript:void(0)" onclick="toggleSellable(${pid}, ${raw ? 0 : 1})" class="text-decoration-none fw-bold ms-3 border-start ps-3"><i class="bi bi-arrow-repeat"></i> Flip POS Visibility</a>` : '';

            if (raw && pos) {
                const rsn = opt.dataset.reason ? opt.dataset.reason : 'Raw food cannot go to a POS location.';
                info.innerHTML = '<span class="badge bg-danger">RAW</span> <i class="bi bi-info-circle text-info ms-1"></i> ' + rsn + toggleLnk;
            } else if (raw) {
                info.innerHTML = '<span class="badge bg-secondary">RAW</span> <i class="bi bi-info-circle text-info ms-1"></i> Raw item — keep in kitchen/warehouse.' + toggleLnk;
            } else if (pid) {
                info.innerHTML = '<span class="badge bg-success">SELLABLE</span> <i class="bi bi-check-circle text-success ms-1"></i> Available for checkout on POS.' + toggleLnk;
            } else {
                info.innerHTML = '';
            }
        }
        if (raw && pos) sel.value = '';
    });
    if (warn) {
        if (pos) {
            warn.classList.remove('d-none');
        } else {
            warn.classList.add('d-none');
        }
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
        if (inquiryPrefill.length) {
            inquiryPrefill.forEach(function (r) { addLine('inquiryLines', false, r); });
        } else {
            addLine('inquiryLines');
        }
    }
    if (orderBox) {
        if (orderPrefill.length) {
            if (<?= json_encode($tab === 'orders') ?>) {
                orderPrefill.forEach(function (r) { addLine('orderLines', true, r); });
            }
        } else {
            if (<?= json_encode($tab === 'orders') ?>) {
                addLine('orderLines', true);
            }
        }
    }
    if (grvBox) {
        if (bayPrefillItems.length) {
            bayPrefillItems.forEach(function (r) {
                addLine('grvLines', true, { product_id: r.product_id, quantity: r.qty, unit_cost: r.cost });
            });
        } else if (orderPrefill.length) {
            if (<?= json_encode($tab === 'received') ?>) {
                orderPrefill.forEach(function (r) {
                    const rQty = r.quantity ? r.quantity : 0;
                    const rRec = r.received_qty ? r.received_qty : 0;
                    const remain = Math.max(0, parseFloat(rQty) - parseFloat(rRec));
                    if (remain > 0) {
                        addLine('grvLines', true, { product_id: r.product_id, quantity: remain, unit_cost: r.unit_cost });
                    }
                });
            }
        } else {
            addLine('grvLines', true);
        }
    }
    let loc = document.getElementById('grvLocation');
    if (!loc) {
        loc = document.querySelector('select[name="location_id"]');
    }
    if (loc) {
        loc.addEventListener('change', refreshMovementHint);
    }
    refreshMovementHint();
});
</script>
=== PURCHASING CONTROLLER ===
<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\ReceivingBay;
use App\Models\Vendor;

final class PurchasingController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        Purchase::ensureSchema();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            // Inline POS Visibility Toggle endpoint
            if (isset($_POST['toggle_sellable'])) {
                try {
                    $pid = (int)$_POST['product_id'];
                    $sellable = (int)$_POST['is_sellable'];

                    // Auto-migrate schema on first use
                    $stmt = $this->pdo()->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'is_sellable'");
                    if ((int)$stmt->fetchColumn() === 0) {
                        $this->pdo()->exec("ALTER TABLE products ADD COLUMN is_sellable TINYINT(1) NOT NULL DEFAULT 1");
                        $this->pdo()->exec("UPDATE products SET is_sellable = 0 WHERE category_type = 'ingredients' OR (used_as_ingredient = 1 AND has_recipe = 0 AND price <= 0)");
                    }

                    $update = $this->pdo()->prepare("UPDATE products SET is_sellable = ? WHERE id = ?");
                    $update->execute([$sellable, $pid]);
                    echo json_encode(['status' => 'success']);
                } catch (\Throwable $e) {
                    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                }
                exit;
            }

            try {
                if (isset($_POST['save_inquiry'])) {
                    $id = Purchase::saveInquiry(
                        !empty($_POST['inquiry_id']) ? (int)$_POST['inquiry_id'] : null,
                        !empty($_POST['vendor_id']) ? (int)$_POST['vendor_id'] : null,
                        (int)$_POST['location_id'],
                        $this->userId(),
                        (string)($_POST['status'] ?? 'draft'),
                        (string)($_POST['needed_by'] ?? ''),
                        trim((string)($_POST['notes'] ?? '')),
                        $_POST['items'] ?? []
                    );
                    $this->flash('success', 'Purchase inquiry #' . $id . ' saved.');
                }
                if (isset($_POST['convert_inquiry'])) {
                    $orderId = Purchase::convertInquiry((int)$_POST['inquiry_id'], $this->userId());
                    $this->flash('success', 'Inquiry converted to purchase order #' . $orderId . '.');
                    $this->redirect('purchasing', ['tab' => 'orders']);
                }
                if (isset($_POST['cancel_inquiry'])) {
                    Purchase::setInquiryStatus((int)$_POST['inquiry_id'], 'cancelled');
                    $this->flash('success', 'Inquiry cancelled.');
                }
                if (isset($_POST['save_order'])) {
                    $id = Purchase::saveOrder(
                        !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null,
                        !empty($_POST['inquiry_id']) ? (int)$_POST['inquiry_id'] : null,
                        (int)($_POST['vendor_id'] ?? 0),
                        (int)$_POST['location_id'],
                        $this->userId(),
                        trim((string)($_POST['reference_no'] ?? '')),
                        trim((string)($_POST['notes'] ?? '')),
                        $_POST['items'] ?? []
                    );
                    $this->flash('success', 'Purchase order #' . $id . ' saved.');
                    $this->redirect('purchasing', ['tab' => 'orders']);
                }
                if (isset($_POST['cancel_order'])) {
                    Purchase::cancelOrder((int)$_POST['order_id']);
                    $this->flash('success', 'Purchase order cancelled.');
                    $this->redirect('purchasing', ['tab' => 'orders']);
                }
                if (isset($_POST['process_grv'])) {
                    $items = $_POST['items'] ?? [];
                    $locId = (int)($_POST['location_id'] ?? 0);
                    $vendorId = (int)($_POST['vendor_id'] ?? 0);
                    $orderId = !empty($_POST['purchase_order_id']) ? (int)$_POST['purchase_order_id'] : null;
                    if ($locId <= 0 || $vendorId <= 0) {
                        throw new \Exception('Select a vendor and receiving location.');
                    }
                    $grvId = Purchase::receive(
                        $vendorId,
                        $locId,
                        $this->userId(),
                        (string)($_POST['ref_number'] ?? ''),
                        $items,
                        $orderId
                    );
                    $dockId = (int)($_POST['dock_id'] ?? 0);
                    if ($dockId > 0) {
                        ReceivingBay::markReceived($dockId, $grvId, $this->userId(), $locId);
                    }
                    $msg = 'Goods received (GRV #' . $grvId . ').';
                    try {
                        $zraRes = (new \ZraVsdcClient($this->pdo()))->syncGrv($grvId);
                        if (($zraRes['resultCd'] ?? '') === '000') {
                            $msg .= ' Posted to ZRA.';
                        }
                    } catch (\Throwable $e) {
                    }
                    $this->flash('success', $msg);
                    $this->redirect('purchasing', ['tab' => 'received']);
                }
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage());
            }
            $tab = (string)($_GET['tab'] ?? 'inquiry');
            $q = ['tab' => $tab];
            if (isset($_POST['process_grv']) && !empty($_POST['dock_id'])) {
                $q['dock_id'] = (int)$_POST['dock_id'];
                if (!empty($_POST['location_id'])) {
                    $q['location_id'] = (int)$_POST['location_id'];
                }
            }
            $this->redirect('purchasing', $q);
        }

        $tab = (string)($_GET['tab'] ?? 'inquiry');
        if (!in_array($tab, ['inquiry', 'orders', 'received'], true)) {
            $tab = 'inquiry';
        }

        $editInquiry = !empty($_GET['inquiry_id']) ? Purchase::inquiry((int)$_GET['inquiry_id']) : null;
        $editOrder = !empty($_GET['order_id']) ? Purchase::order((int)$_GET['order_id']) : null;
        $receiveOrder = !empty($_GET['receive_order']) ? Purchase::order((int)$_GET['receive_order']) : $editOrder;

        $bayPrefill = null;
        $dockId = (int)($_GET['dock_id'] ?? 0);
        if ($tab === 'received' && $dockId > 0) {
            $dock = ReceivingBay::find($dockId);
            if ($dock && ($dock['status'] ?? '') === 'docked') {
                $locId = (int)($_GET['location_id'] ?? $dock['intended_location_id'] ?? 0);
                try {
                    if ($locId > 0) {
                        ReceivingBay::registerCustomItems($dockId, $locId);
                    }
                    $bayPrefill = ReceivingBay::grvPrefill($dockId, $locId ?: null);
                } catch (\Throwable $e) {
                    $this->flash('error', $e->getMessage());
                }
            }
        }

        $this->render([
            'tab' => $tab,
            'inquiries' => Purchase::inquiries(),
            'orders' => Purchase::orders(),
            'openOrders' => Purchase::openOrders(),
            'editInquiry' => $editInquiry,
            'editOrder' => $editOrder,
            'receiveOrder' => $receiveOrder,
            'bayPrefill' => $bayPrefill,
            'vendors' => Vendor::active(),
            'locations' => Location::all(),
            'products' => Product::activeItems(),
            'currentLocId' => $this->locationId(),
        ]);
    }
}
=== REPORTS VIEW ===
<?php
$reportRole = strtolower((string)($_SESSION['role'] ?? ''));
$canRefundSales = in_array($reportRole, ['admin', 'manager', 'dev'], true);
?>
<style>
    .metric-card { border: none; border-radius: 10px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.2s; }
    .metric-card:hover { transform: translateY(-3px); }
    .table-responsive { background: white; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); padding: 15px; }
    #reportLocation {
        color: #212529 !important;
        background-color: #fff !important;
        min-height: 38px;
    }
    @media print {
        @page { margin: 10mm; size: portrait; }
        body { background-color: #fff !important; }
        .no-print, .nav-tabs, form, .dataTables_length, .dataTables_filter, .dataTables_info, .dataTables_paginate, .app-navbar, .footer, .nav-toolbar { display: none !important; }
        #print-only-header { display: block !important; text-align: center; border-bottom: 2px dashed #000; padding-bottom: 15px; margin-bottom: 25px; }
        .row.g-3.mb-4 { display: flex !important; flex-wrap: nowrap !important; margin-bottom: 25px !important; }
        .col-md-3 { flex: 0 0 25% !important; max-width: 25% !important; padding: 0 5px !important; }
        .metric-card { border: 2px solid #000 !important; background: #fff !important; color: #000 !important; text-align: center !important; box-shadow: none !important; border-radius: 0 !important; }
        .metric-card * { color: #000 !important; }
        .table-responsive { padding: 0 !important; background: transparent !important; box-shadow: none !important; }
        table.table th, table.table td { border: 1px solid #000 !important; color: #000 !important; }
        .badge { border: 1px solid #000 !important; color: #000 !important; background: transparent !important; }
        a, button.btn-link { text-decoration: none !important; color: #000 !important; }
    }
    #print-only-header { display: none; }
</style>
<div id="print-only-header">
    <h2 style="margin: 0; font-weight: 900; text-transform: uppercase; font-size: 24pt;">Master Report</h2>
    <h4 style="margin: 5px 0; font-size: 14pt;">Category: <?= strtoupper($reportType) ?></h4>
    <p style="margin: 0; font-size: 12pt;"><strong>Period:</strong> <?= date('d M Y', strtotime($startDate)) ?> to <?= date('d M Y', strtotime($endDate)) ?></p>
    <p style="margin: 0; font-size: 10pt;">Generated: <?= date('d M Y, H:i') ?></p>
</div>

<div class="d-flex justify-content-between align-items-center mb-4 no-print">
    <div>
        <h3 class="fw-bold"><i class="bi bi-bar-chart-line-fill text-primary"></i> Comprehensive Reports</h3>
        <p class="text-muted mb-0">Analysis from <strong><?= date('M d', strtotime($startDate)) ?></strong> to <strong><?= date('M d', strtotime($endDate)) ?></strong></p>
    </div>
    <div class="d-flex gap-2">
        <a href="index.php?page=dashboard" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Dashboard</a>
        <button onclick="printMasterReport()" class="btn btn-dark"><i class="bi bi-printer"></i> Print Report</button>
    </div>
</div>

<div class="card p-3 mb-4 border-0 shadow-sm no-print">
    <form method="GET" class="row g-3 align-items-end">
        <input type="hidden" name="page" value="reports">
        <input type="hidden" name="type" value="<?= htmlspecialchars($reportType) ?>">
        <div class="col-md-3">
            <label class="form-label small fw-bold">Start Date</label>
                <input type="text" name="start" class="form-control datepicker" value="<?= $startDate ?>" autocomplete="off">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold">End Date</label>
                <input type="text" name="end" class="form-control datepicker" value="<?= $endDate ?>" autocomplete="off">
        </div>
        <div class="col-md-3">
            <label class="form-label small fw-bold text-dark" for="reportLocation">Location</label>
            <select name="location" id="reportLocation" class="form-select bg-white text-dark border">
                <option value="" <?= ($locationId === '' || $locationId === null) ? 'selected' : '' ?>>All Locations</option>
                <?php foreach (($locations ?? []) as $l): ?>
                    <option value="<?= (int)$l['id'] ?>" <?= ((string)$locationId === (string)$l['id']) ? 'selected' : '' ?>><?= htmlspecialchars((string)($l['name'] ?? 'Location #' . $l['id'])) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <button type="submit" class="btn btn-primary w-100 fw-bold"><i class="bi bi-filter"></i> Apply Filters</button>
        </div>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card metric-card bg-success text-white h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75 fw-bold">Total Revenue</div>
                <div class="display-6 fw-bold">ZMW <?= number_format($metrics['total_revenue'] ?? 0, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card metric-card bg-danger text-white h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75 fw-bold">Refunds (<?= $refundStats['refund_count'] ?? 0 ?>)</div>
                <div class="display-6 fw-bold">ZMW <?= number_format($refundStats['total_refunded'] ?? 0, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card metric-card bg-info text-white h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75 fw-bold">Total Tips</div>
                <div class="display-6 fw-bold">ZMW <?= number_format($metrics['total_tips'] ?? 0, 2) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card metric-card bg-dark text-white h-100">
            <div class="card-body">
                <div class="small text-uppercase opacity-75 fw-bold">Voided Items (<?= $voidStats['void_count'] ?? 0 ?>)</div>
                <div class="display-6 fw-bold">ZMW <?= number_format($voidStats['total_voided'] ?? 0, 2) ?></div>
            </div>
        </div>
    </div>
</div>

<ul class="nav nav-tabs mb-3 no-print">
    <li class="nav-item">
        <a class="nav-link <?= $reportType == 'sales' ? 'active fw-bold' : '' ?>" href="index.php?page=reports&type=sales&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>">Sales Ledger</a>
    </li>
    <li class="nav-item">
        <a class="nav-link text-primary <?= $reportType == 'itemized' ? 'active fw-bold' : '' ?>" href="index.php?page=reports&type=itemized&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>"><i class="bi bi-list-columns-reverse"></i> Live Itemized Sales</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportType == 'product' ? 'active fw-bold' : '' ?>" href="index.php?page=reports&type=product&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>">Product Performance</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $reportType == 'audit' ? 'active fw-bold' : '' ?>" href="index.php?page=reports&type=audit&start=<?= $startDate ?>&end=<?= $endDate ?>&location=<?= $locationId ?>">Audit & Refunds</a>
    </li>
</ul>

<div class="table-responsive p-3 bg-white">
    <table id="reportsTable" class="table table-striped table-hover mb-0 align-middle w-100">

        <?php if($reportType === 'sales'): ?>
        <thead class="table-dark">
            <tr>
                <th>ID</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Station</th>
                <th>Method</th>
                <th>Status</th>
                <th class="text-end">Tips</th>
                <th class="text-end">Total</th>
                <th class="text-end no-print">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($reportData as $row): ?>
            <tr>
                <td>#<?= $row['id'] ?></td>
                <td><?= date('M d, H:i', strtotime($row['created_at'])) ?></td>
                <td class="fw-bold"><?= htmlspecialchars($row['customer_name']) ?></td>
                <td><?= htmlspecialchars($row['location']) ?></td>
                <td><span class="badge bg-secondary"><?= $row['payment_method'] ?></span></td>
                <td>
                    <?php if($row['final_total'] < 0 || strtolower($row['payment_status'])=='refunded'): ?><span class="badge bg-danger">REFUNDED</span>
                    <?php elseif($row['final_total'] == 0 || strtolower($row['payment_status'])=='voided'): ?><span class="badge bg-dark">VOIDED</span>
                    <?php elseif(strtolower($row['payment_status'])=='paid'): ?><span class="badge bg-success">PAID</span>
                    <?php else: ?><span class="badge bg-warning text-dark">PENDING</span><?php endif; ?>
                </td>
                <td class="text-end text-muted"><?= $row['tip_amount'] > 0 ? number_format($row['tip_amount'], 2) : '-' ?></td>
                <td class="text-end fw-bold">
                    <button type="button" onclick="viewSaleBreakdown(<?= $row['id'] ?>)" class="btn btn-link p-0 fw-bold text-decoration-underline text-primary fs-6 shadow-none" title="View Item Breakdown">
                        ZMW <?= number_format($row['final_total'], 2) ?>
                    </button>
                </td>
                <td class="text-end no-print">
                    <button onclick="showReceiptModal('index.php?page=receipt&sale_id=<?= $row['id'] ?>')" class="btn btn-sm btn-outline-primary"><i class="bi bi-printer"></i></button>

                    <?php if(strtolower($row['payment_status']) == 'paid' && (float)$row['final_total'] > 0 && in_array($_SESSION['role'], ['admin', 'manager', 'dev'])): ?>
                    <a href="index.php?page=refund_items&sale_id=<?= $row['id'] ?>" class="btn btn-sm btn-outline-danger ms-1" title="Refund"><i class="bi bi-arrow-counterclockwise"></i></a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif($reportType === 'itemized'): ?>
        <thead class="table-dark">
            <tr>
                <th>Time</th>
                <th>Receipt</th>
                <th>Product</th>
                <th>Cashier</th>
                <th>Station</th>
                <th class="text-center">Qty</th>
                <th class="text-end">Line Total</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($reportData as $row): ?>
            <tr>
                <td data-sort="<?= $row['created_at'] ?>"><?= date('M d, H:i:s', strtotime($row['created_at'])) ?></td>
                <td>
                    <button type="button" onclick="viewSaleBreakdown(<?= $row['sale_id'] ?>)" class="btn btn-link p-0 fw-bold text-decoration-underline shadow-none">
                        #<?= $row['sale_id'] ?>
                    </button>
                </td>
                <td class="fw-bold text-primary"><?= htmlspecialchars($row['product_name']) ?></td>
                <td><?= htmlspecialchars($row['cashier']) ?></td>
                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['location_name']) ?></span></td>
                <td class="text-center fw-bold"><?= $row['quantity'] ?></td>
                <td class="text-end fw-bold <?= $row['line_total'] < 0 ? 'text-danger' : 'text-success' ?>">ZMW <?= number_format($row['line_total'], 2) ?></td>
                <td>
                    <?php if($row['item_status'] == 'voided' || (isset($row['line_total']) && $row['line_total'] == 0)): ?> <span class="badge bg-dark">VOID</span>
                    <?php elseif($row['item_status'] == 'refunded' || (isset($row['line_total']) && $row['line_total'] < 0)): ?> <span class="badge bg-danger">REFUNDED</span>
                    <?php elseif(strtolower($row['payment_status']) == 'paid'): ?> <span class="badge bg-success">PAID</span>
                    <?php else: ?> <span class="badge bg-warning text-dark">PENDING</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif($reportType === 'product'): ?>
        <thead class="table-dark">
            <tr><th>Product Name</th><th>SKU</th><th class="text-center">Qty Sold</th><th class="text-end">Revenue</th></tr>
        </thead>
        <tbody>
            <?php foreach($reportData as $row): ?>
            <tr>
                <td class="fw-bold"><?= htmlspecialchars($row['name'] ?? '') ?></td><td class="text-muted"><?= htmlspecialchars($row['sku'] ?? '') ?></td>
                <td class="text-center fs-5"><?= $row['qty_sold'] ?></td><td class="text-end fw-bold text-success">ZMW <?= number_format($row['revenue'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>

        <?php elseif($reportType === 'audit'): ?>
        <thead class="table-dark">
            <tr><th>Time</th><th>Product</th><th>User</th><th>Action</th><th class="text-end">Qty Change</th><th>Reason/Ref</th><th class="text-end no-print">Actions</th></tr>
        </thead>
        <tbody>
            <?php foreach($reportData as $row): ?>
            <tr>
                <td><?= date('M d, H:i', strtotime($row['created_at'])) ?></td>
                <td class="fw-bold"><?= htmlspecialchars($row['product_name']) ?></td>
                <td><?= htmlspecialchars($row['username']) ?></td>
                <td><span class="badge bg-<?= $row['action_type']=='sale'?'success':($row['action_type']=='adjustment'?'warning':($row['action_type']=='refund'?'danger':'info')) ?>"><?= strtoupper($row['action_type']) ?></span></td>
                <td class="text-end fw-bold <?= $row['change_qty'] < 0 ? 'text-danger' : 'text-success' ?>"><?= $row['change_qty'] > 0 ? '+'.$row['change_qty'] : $row['change_qty'] ?></td>
                <td class="small text-muted"><?= ($row['action_type']=='sale') ? 'Sale #'.$row['reference_id'] : (($row['action_type']=='refund') ? 'Refund #'.$row['reference_id'] : 'Manual Adj') ?></td>
                <td class="text-end no-print">
                    <?php if(in_array($row['action_type'], ['sale','refund']) && !empty($row['sale_id'])): ?>
                        <button onclick="viewSaleBreakdown(<?= (int)$row['sale_id'] ?>)" class="btn btn-sm btn-outline-secondary" title="View breakdown"><i class="bi bi-list-ul"></i></button>
                        <button onclick="showReceiptModal('index.php?page=receipt&sale_id=<?= (int)$row['sale_id'] ?>')" class="btn btn-sm btn-outline-primary ms-1" title="Print"><i class="bi bi-printer"></i></button>
                        <?php if($canRefundSales && strtolower($row['payment_status'] ?? '') === 'paid' && $row['action_type'] === 'sale'): ?>
                        <a href="index.php?page=refund_items&sale_id=<?= (int)$row['sale_id'] ?>" class="btn btn-sm btn-outline-danger ms-1" title="Refund"><i class="bi bi-arrow-counterclockwise"></i></a>
                        <?php endif; ?>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
        <?php endif; ?>
    </table>
    <?php if(empty($reportData)): ?><div class="p-5 text-center text-muted">No records found.</div><?php endif; ?>
</div>

<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white"><h5 class="modal-title">Receipt View</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
            <div class="modal-body p-0" style="height: 500px;"><iframe id="reportFrame" src="" style="width:100%; height:100%; border:none;"></iframe></div>
            <div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button><button class="btn btn-primary" onclick="document.getElementById('reportFrame').contentWindow.print()">Print</button></div>
        </div>
    </div>
</div>

<div class="modal fade" id="saleBreakdownModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg border-info border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-receipt text-info me-2"></i> Sale Breakdown <span id="modalSaleIdBadge" class="badge bg-secondary ms-2"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end pe-4">Price</th>
                        </tr>
                    </thead>
                    <tbody id="saleBreakdownBody">
                        <tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border text-info spinner-border-sm me-2"></div> Loading items...</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>


<link href="vendor/datatables/dataTables.bootstrap5.min.css" rel="stylesheet">
<script src="vendor/datatables/jquery.dataTables.min.js"></script>
<script src="vendor/datatables/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        $('#reportsTable').DataTable({
            "pageLength": 50,
            "order": [], // Use native sorting from backend (Newest first)
            "language": {
                "search": "",
                "searchPlaceholder": "Live filter..."
            },
            "dom": '<"d-flex justify-content-between align-items-center mb-3"<"me-auto"l><"ms-auto"f>>rt<"d-flex justify-content-between mt-3"ip>'
        });
        $('.dataTables_filter input').addClass('form-control shadow-sm border-secondary');
        $('.dataTables_length select').addClass('form-select shadow-sm border-secondary');
    });

    function showReceiptModal(url) { document.getElementById('reportFrame').src = url; new bootstrap.Modal(document.getElementById('reportModal')).show(); }

    // AJAX Function to fetch and display the item breakdown
    function viewSaleBreakdown(saleId) {
        document.getElementById('modalSaleIdBadge').innerText = '#' + saleId;
        document.getElementById('saleBreakdownBody').innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border text-info spinner-border-sm me-2"></div> Fetching data...</td></tr>';

        let modal = new bootstrap.Modal(document.getElementById('saleBreakdownModal'));
        modal.show();

        fetch('index.php?page=reports', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'fetch_sale_details=1&sale_id=' + saleId
        })
        .then(r => r.json())
        .then(data => {
            let tbody = document.getElementById('saleBreakdownBody');
            if (data.status === 'success') {
                tbody.innerHTML = '';
                if (data.items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No items found for this sale.</td></tr>';
                    return;
                }
                data.items.forEach(i => {
                    let statusBadge = '';
                    if (i.status === 'voided') statusBadge = ' <span class="badge bg-danger">VOID</span>';
                    else if (i.status === 'refunded') statusBadge = ' <span class="badge bg-warning text-dark">REFUNDED</span>';

                    let priceClass = parseFloat(i.price) < 0 ? 'text-danger' : 'text-dark';

                    tbody.innerHTML += `
                        <tr>
                            <td class="ps-4 fw-bold text-secondary">${i.name} ${statusBadge}</td>
                            <td class="text-center fw-bold">${i.quantity}</td>
                            <td class="text-end pe-4 fw-bold ${priceClass}">ZMW ${parseFloat(i.price * i.quantity).toFixed(2)}</td>
                        </tr>
                    `;
                });
            } else {
                tbody.innerHTML = `<tr><td colspan="3" class="text-muted text-center py-3">Unable to load items.</td></tr>`;
            }
        }).catch(err => {
            document.getElementById('saleBreakdownBody').innerHTML = '<tr><td colspan="3" class="text-center py-3 text-muted">Unable to load sale details.</td></tr>';
        });
    }

// --- SMART PRINT FUNCTION ---
function printMasterReport() {
    if ($.fn.DataTable.isDataTable('#reportsTable')) {
        let table = $('#reportsTable').DataTable();
        let currentLen = table.page.len();

        // Expand the table to show ALL rows before printing
        table.page.len(-1).draw();

        // Wait a split second for the browser to render the long table, then print
        setTimeout(() => {
            window.print();
            // Restore the pagination back to normal after printing is done
            table.page.len(currentLen).draw();
        }, 500);
    } else {
        window.print();
    }
}
</script>
=== REPORTS CONTROLLER ===
<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Report;
use App\Models\Sale;

final class ReportsController extends Controller
{
    public function index(): void
    {
        if (isset($_POST['fetch_sale_details'])) {
            try {
                $this->jsonExit(['status' => 'success', 'items' => Sale::saleItemsForModal((int)$_POST['sale_id'])]);
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage()]);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_sale'])) {
            if (!$this->isManager()) {
                $this->flash('error', 'You do not have permission to process refunds.');
                $this->redirect('reports');
            }
            try {
                $result = (new \App\Services\Refund($this->pdo()))->refundAll(
                    (int)$_POST['sale_id'],
                    $this->userId(),
                    'Full refund',
                    (string)($_POST['rfd_rsn_cd'] ?? '07')
                );
                $msg = 'Refund processed and stock restored.';
                if ($result['zra_msg'] !== '') {
                    $msg .= ' ' . $result['zra_msg'];
                }
                $this->flash('success', $msg);
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage() ?: 'Unable to process the refund.');
            }
            $this->redirect('reports');
        }

        $startDate = $_GET['start'] ?? date('Y-m-d');
        $endDate = $_GET['end'] ?? date('Y-m-d');
        $locationId = $_GET['location'] ?? '';
        $reportType = $_GET['type'] ?? 'sales';

        $reportData = [];
        if ($reportType === 'sales') {
            $reportData = Report::salesRows($startDate, $endDate, $locationId);
        } elseif ($reportType === 'itemized') {
            $reportData = Report::itemizedRows($startDate, $endDate, $locationId);
        } elseif ($reportType === 'product') {
            $reportData = Report::productRows($startDate, $endDate, $locationId);
        } elseif ($reportType === 'audit') {
            $reportData = Report::auditRows($startDate, $endDate, $locationId);
        }

        $this->render([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'locationId' => $locationId,
            'reportType' => $reportType,
            'metrics' => Report::metrics($startDate, $endDate, $locationId),
            'refundStats' => Report::refundStats($startDate, $endDate, $locationId),
            'voidStats' => Report::voidStats($startDate, $endDate, $locationId),
            'reportData' => $reportData,
            'locations' => Location::all(),
        ]);
    }
}