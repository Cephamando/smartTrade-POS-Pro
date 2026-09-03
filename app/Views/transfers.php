<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>🚚 Stock Requisitions & Transfers</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#requestModal">
        <i class="bi bi-plus-circle"></i> New Requisition
    </button>
</div>

<ul class="nav nav-tabs mb-4" id="transferTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#incoming">
            <i class="bi bi-box-seam"></i> Incoming (To Receive)
            <?php if(isset($incomingStock) && count($incomingStock) > 0): ?>
                <span class="badge bg-danger ms-2"><?= count($incomingStock) ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#outgoing">
            <i class="bi bi-truck"></i> Outgoing (To Dispatch)
            <?php if(isset($pendingDispatch) && count($pendingDispatch) > 0): ?>
                <span class="badge bg-warning text-dark ms-2"><?= count($pendingDispatch) ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#requests">
            <i class="bi bi-list-task"></i> My Pending Requests
        </button>
    </li>
</ul>

<div class="tab-content">
    <div class="tab-pane fade show active" id="incoming">
        <div class="card shadow-sm border-success">
            <div class="card-header bg-success text-white">
                <i class="bi bi-arrow-down-circle"></i> Stock Arriving at Your Location
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>From</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Sent At</th>
                            <th class="text-end">Action Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($incomingStock)): foreach ($incomingStock as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['source_name'] ?? '') ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($t['product_name'] ?? '') ?></td>
                            <td class="fs-5"><?= $t['quantity'] ?></td>
                            <td class="small text-muted"><?= date('M d H:i', strtotime($t['dispatched_at'])) ?></td>
                            <td class="text-end">
                                <form method="POST" action="index.php?page=transfers">
                                    <input type="hidden" name="receive_transfer" value="1">
                                    <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                                    <button class="btn btn-success btn-sm fw-bold">
                                        <i class="bi bi-check-lg"></i> Accept Stock
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        <?php if (empty($incomingStock)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No incoming stock transfers.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="outgoing">
        <div class="card shadow-sm border-warning">
            <div class="card-header bg-warning text-dark">
                <i class="bi bi-arrow-up-circle"></i> Requests Awaiting Dispatch (From You)
            </div>
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>Requested By (To)</th>
                            <th>Product</th>
                            <th>Qty Required</th>
                            <th>Requested At</th>
                            <th class="text-end">Action Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($pendingDispatch)): foreach ($pendingDispatch as $t):
                            $ctx = \App\Models\Product::movementContext((int)$t['product_id']);
                            $src = \App\Models\Location::find((int)$t['source_location_id']);
                            $dst = \App\Models\Location::find((int)$t['dest_location_id']);
                            $move = \App\Helpers\Movement::transfer($ctx, $src, $dst);
                        ?>
                        <tr class="<?= $move['ok'] ? '' : 'table-secondary' ?>">
                            <td><?= htmlspecialchars($t['dest_name'] ?? '') ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($t['product_name'] ?? '') ?></td>
                            <td class="fs-5"><?= $t['quantity'] ?></td>
                            <td class="small text-muted"><?= date('M d H:i', strtotime($t['created_at'])) ?></td>
                            <td class="text-end">
                                <?php if ($move['ok']): ?>
                                <form method="POST" action="index.php?page=transfers" class="d-inline">
                                    <input type="hidden" name="dispatch_transfer" value="1">
                                    <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                                    <button class="btn btn-warning btn-sm fw-bold border">
                                        <i class="bi bi-box-seam"></i> Dispatch
                                    </button>
                                </form>
                                <?php else: ?>
                                    <span class="text-muted small d-inline-flex align-items-center gap-1" title="<?= htmlspecialchars($move['reason']) ?>" data-bs-toggle="tooltip">
                                        <i class="bi bi-info-circle text-info"></i> Blocked
                                    </span>
                                    <div class="small text-muted mt-1"><?= htmlspecialchars($move['reason']) ?></div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        <?php if (empty($pendingDispatch)): ?>
                            <tr><td colspan="5" class="text-center py-5 text-muted">No pending requests to dispatch.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="tab-pane fade" id="requests">
        <div class="card shadow-sm">
            <div class="card-header bg-light">
                <i class="bi bi-clock"></i> My Pending Requests
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>Product</th>
                            <th>Qty</th>
                            <th>Status</th>
                            <th>Action Required</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($myRequests)): foreach ($myRequests as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['source_name'] ?? '') ?></td>
                            <td><?= htmlspecialchars($t['product_name'] ?? '') ?></td>
                            <td><?= $t['quantity'] ?></td>
                            <td><span class="badge bg-secondary">Pending Approval</span></td>
                            <td>
                                <form method="POST" action="index.php?page=transfers" class="js-confirm-form"
                                    data-swal-title="Cancel this request?"
                                    data-swal-text="The requisition will be withdrawn."
                                    data-swal-confirm="Cancel request"
                                    data-swal-cancel="Keep"
                                    data-swal-icon="warning">
                                    <input type="hidden" name="cancel_transfer" value="1">
                                    <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                        <?php if (empty($myRequests)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">You have no pending requests.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="requestModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-top border-primary border-4">
            <div class="modal-header bg-light text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle text-primary"></i> New Stock Requisition</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=transfers">
                <div class="modal-body p-4">
                    <input type="hidden" name="create_request" value="1">

                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">1. SELECT PRODUCT</label>
                        <select name="product_id" id="req_product_id" class="form-select form-select-lg fw-bold border-primary shadow-sm" required>
                            <option value="">-- Choose an item --</option>
                            <?php if(isset($products)): foreach($products as $p):
                                $raw = \App\Helpers\Movement::isRawProduct($p);
                                $zraBlocked = \App\Helpers\Movement::requiresZra() && !\App\Helpers\Movement::isZraReady($p);
                                $reason = $zraBlocked
                                    ? 'Not registered with ZRA yet. Keep it in warehouse until ZRA registration completes.'
                                    : ($raw ? 'Raw food cannot be transferred to a POS. Keep it in kitchen or warehouse, then produce the finished dish.' : '');
                            ?>
                                <option value="<?= $p['id'] ?>" data-raw="<?= $raw ? '1' : '0' ?>" data-zra="<?= $zraBlocked ? '1' : '0' ?>" data-reason="<?= htmlspecialchars($reason) ?>">
                                    <?= htmlspecialchars($p['name'] ?? '') ?><?= $zraBlocked ? ' — PENDING ZRA' : ($raw ? ' — RAW' : '') ?>
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-white rounded border shadow-sm h-100">
                                <label class="form-label fw-bold text-danger small"><i class="bi bi-box-arrow-right"></i> SOURCE (FROM)</label>
                                <select name="source_location_id" id="source_loc_id" class="form-select mb-3 border-danger" required>
                                    <option value="">-- Request From --</option>
                                    <?php foreach ($locations as $l): ?>
                                        <option value="<?= $l['id'] ?>" data-type="<?= htmlspecialchars($l['type'] ?? '') ?>" data-pos="<?= \App\Helpers\Movement::isFrontOfHouse($l) ? '1' : '0' ?>">
                                            <?= htmlspecialchars($l['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded">
                                    <span class="small text-muted fw-bold">AVAILABLE STOCK</span>
                                    <span id="source_stock_display" class="fs-5 fw-bold text-dark">0</span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-white rounded border shadow-sm h-100">
                                <label class="form-label fw-bold text-success small"><i class="bi bi-box-arrow-in-left"></i> DESTINATION (TO)</label>
                                <select name="dest_location_id" id="dest_loc_id" class="form-select mb-3 border-success" required>
                                    <option value="">-- Send To --</option>
                                    <?php foreach ($locations as $l): ?>
                                        <option value="<?= $l['id'] ?>" data-type="<?= htmlspecialchars($l['type'] ?? '') ?>" data-pos="<?= \App\Helpers\Movement::isFrontOfHouse($l) ? '1' : '0' ?>" <?= ($l['id'] == ($_SESSION['location_id'] ?? 0)) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($l['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="d-flex justify-content-between align-items-center bg-light p-2 rounded">
                                    <span class="small text-muted fw-bold">CURRENT STOCK</span>
                                    <span id="dest_stock_display" class="fs-5 fw-bold text-dark">0</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="moveHint" class="alert alert-info py-2 small d-none mt-3 mb-0"></div>
                    <div class="mt-4 pt-3 border-top">
                        <label class="form-label fw-bold small text-muted">REQUEST QUANTITY</label>
                        <div class="input-group input-group-lg shadow-sm">
                            <input type="number" name="quantity" class="form-control text-center fw-bold fs-4 text-primary" step="0.01" min="0.01" required placeholder="0.00">
                            <span class="input-group-text bg-white fw-bold">Units</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold px-5 shadow-sm" id="reqSubmitBtn">
                        <i class="bi bi-send"></i> Send Requisition
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    
    const prodSelect = document.getElementById('req_product_id');
    const sourceSelect = document.getElementById('source_loc_id');
    const destSelect = document.getElementById('dest_loc_id');

    prodSelect.addEventListener('change', () => { filterLocations(); updateDualStock(); refreshMoveRules(); });
    sourceSelect.addEventListener('change', () => { filterLocations(); updateDualStock(); refreshMoveRules(); });
    destSelect.addEventListener('change', () => { filterLocations(); updateDualStock(); refreshMoveRules(); });

    filterLocations();
    updateDualStock();
    refreshMoveRules();

    function refreshMoveRules() {
        const opt = prodSelect.options[prodSelect.selectedIndex];
        const raw = opt && opt.dataset.raw === '1';
        const zraBlocked = opt && opt.dataset.zra === '1';
        const foBlocked = raw || zraBlocked;
        const reason = (opt && opt.dataset.reason) || '';
        const hint = document.getElementById('moveHint');
        const submit = document.getElementById('reqSubmitBtn');
        Array.from(destSelect.options).forEach(o => {
            if (!o.value) return;
            const blocked = foBlocked && o.dataset.pos === '1';
            o.disabled = blocked || o.value === sourceSelect.value;
            o.style.color = blocked ? '#aaa' : '';
        });
        const destOpt = destSelect.options[destSelect.selectedIndex];
        const destBlocked = foBlocked && destOpt && destOpt.dataset.pos === '1';
        if (hint) {
            if (foBlocked) {
                hint.classList.remove('d-none');
                hint.innerHTML = '<i class="bi bi-info-circle"></i> ' + (reason || 'This item cannot be sent to a POS location.');
            } else {
                hint.classList.add('d-none');
                hint.innerHTML = '';
            }
        }
        if (destBlocked) {
            destSelect.value = '';
        }
        if (submit) {
            submit.disabled = destBlocked;
            submit.classList.toggle('disabled', destBlocked);
        }
    }

    function filterLocations() {
        const sourceVal = sourceSelect.value;
        const destVal = destSelect.value;

        Array.from(sourceSelect.options).forEach(opt => {
            if (opt.value === "") return;
            opt.disabled = (opt.value === destVal);
        });

        Array.from(destSelect.options).forEach(opt => {
            if (opt.value === "") return;
            opt.disabled = (opt.value === sourceVal);
        });
    }

    async function updateDualStock() {
        const pId = prodSelect.value;
        const sId = sourceSelect.value;
        const dId = destSelect.value;

        const sDisplay = document.getElementById('source_stock_display');
        const dDisplay = document.getElementById('dest_stock_display');

        if (!pId) {
            sDisplay.innerText = '0';
            dDisplay.innerText = '0';
            return;
        }

        // Fetch Source Stock
        if (sId) {
            sDisplay.innerHTML = '<span class="spinner-border spinner-border-sm text-danger" role="status"></span>';
            try {
                let res = await fetch(`index.php?page=transfers&ajax_action=get_stock_level&product_id=${pId}&location_id=${sId}`);
                let text = await res.text();
                // 🔥 JSON SCRUBBER: Cuts off any PHP errors hiding before the bracket
                if (text.includes('{')) {
                    text = text.substring(text.indexOf('{')); 
                }
                try {
                    let data = JSON.parse(text);
                    sDisplay.innerText = data.stock !== undefined ? data.stock : '0';
                } catch (e) {
                    console.error("Parse Error (Source):", text);
                    sDisplay.innerText = 'Err';
                }
            } catch (e) {
                console.error("Network Error (Source):", e);
                sDisplay.innerText = 'Err';
            }
        } else {
            sDisplay.innerText = '0';
        }

        // Fetch Destination Stock
        if (dId) {
            dDisplay.innerHTML = '<span class="spinner-border spinner-border-sm text-success" role="status"></span>';
            try {
                let res = await fetch(`index.php?page=transfers&ajax_action=get_stock_level&product_id=${pId}&location_id=${dId}`);
                let text = await res.text();
                // 🔥 JSON SCRUBBER
                if (text.includes('{')) {
                    text = text.substring(text.indexOf('{')); 
                }
                try {
                    let data = JSON.parse(text);
                    dDisplay.innerText = data.stock !== undefined ? data.stock : '0';
                } catch (e) {
                    console.error("Parse Error (Dest):", text);
                    dDisplay.innerText = 'Err';
                }
            } catch (e) {
                console.error("Network Error (Dest):", e);
                dDisplay.innerText = 'Err';
            }
        } else {
            dDisplay.innerText = '0';
        }
    }
});
</script>
