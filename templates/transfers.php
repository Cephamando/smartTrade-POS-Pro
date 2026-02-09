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
                        <?php if (isset($pendingDispatch)): foreach ($pendingDispatch as $t): ?>
                        <tr>
                            <td><?= htmlspecialchars($t['dest_name'] ?? '') ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($t['product_name'] ?? '') ?></td>
                            <td class="fs-5"><?= $t['quantity'] ?></td>
                            <td class="small text-muted"><?= date('M d H:i', strtotime($t['created_at'])) ?></td>
                            <td class="text-end">
                                <form method="POST" action="index.php?page=transfers" class="d-inline">
                                    <input type="hidden" name="dispatch_transfer" value="1">
                                    <input type="hidden" name="transfer_id" value="<?= $t['id'] ?>">
                                    <button class="btn btn-warning btn-sm fw-bold border">
                                        <i class="bi bi-box-seam"></i> Dispatch
                                    </button>
                                </form>
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
                                <form method="POST" action="index.php?page=transfers" onsubmit="return confirm('Cancel this request?');">
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
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">New Stock Requisition</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=transfers">
                <div class="modal-body">
                    <input type="hidden" name="create_request" value="1">
                    <input type="hidden" name="dest_location_id" id="dest_loc_id" value="<?= $_SESSION['location_id'] ?? 0 ?>">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Product</label>
                        <select name="product_id" id="req_product_id" class="form-select" required onchange="updateDualStock()">
                            <option value="">-- Select Product --</option>
                            <?php if(isset($products)): foreach($products as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name'] ?? '') ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded border">
                                <label class="form-label fw-bold text-danger">Source (Request From)</label>
                                <select name="source_location_id" id="source_loc_id" class="form-select mb-2" required onchange="updateDualStock()">
                                    <?php if(isset($locations)): foreach($locations as $l): ?>
                                        <?php if($l['id'] != ($_SESSION['location_id'] ?? 0)): ?>
                                            <option value="<?= $l['id'] ?>" <?= stripos($l['name'] ?? '', 'Main') !== false ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($l['name'] ?? '') ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; endif; ?>
                                </select>
                                <div class="small text-muted">Stock at Source: <span id="source_stock_display" class="fw-bold text-dark">0</span></div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded border">
                                <label class="form-label fw-bold text-success">Destination (Your Stock)</label>
                                <div class="form-control-plaintext fw-bold ps-1"><?= htmlspecialchars($_SESSION['location_name'] ?? 'Current Location') ?></div>
                                <div class="small text-muted">Currently in your location: <span id="dest_stock_display" class="fw-bold text-dark">0</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-bold">Quantity Needed</label>
                        <input type="number" name="quantity" class="form-control form-control-lg text-center" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Requisition</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateDualStock() {
    const pId = document.getElementById('req_product_id').value;
    const sId = document.getElementById('source_loc_id').value;
    const dId = document.getElementById('dest_loc_id').value;

    if (!pId) return;

    fetch(`index.php?action=get_stock_level&product_id=${pId}&location_id=${sId}`)
        .then(r => r.json()).then(d => {
            document.getElementById('source_stock_display').innerText = d.stock || 0;
        });

    fetch(`index.php?action=get_stock_level&product_id=${pId}&location_id=${dId}`)
        .then(r => r.json()).then(d => {
            document.getElementById('dest_stock_display').innerText = d.stock || 0;
        });
}
</script>
