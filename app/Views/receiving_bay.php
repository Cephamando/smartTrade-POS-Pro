<?php
$docks = $docks ?? [];
$open = $open ?? null;
$openItems = $openItems ?? [];
$locations = $locations ?? [];
$dockedCount = (int)($dockedCount ?? 0);
$currentLocId = (int)($currentLocId ?? 0);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1"><i class="bi bi-box-arrow-in-down text-warning"></i> Receiving bay</h3>
        <p class="text-muted mb-0">V-SIAS inspections dock here. Custom items are flagged, then you send the consignment to Goods Received to generate the GRV.</p>
    </div>
    <span class="badge bg-warning text-dark fs-6"><?= $dockedCount ?> waiting</span>
</div>

<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white fw-bold py-2">Docked consignments</div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light small">
                        <tr>
                            <th>Tracking</th>
                            <th>From</th>
                            <th>Lines</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($docks as $row): ?>
                        <tr class="<?= $open && (int)$open['id'] === (int)$row['id'] ? 'table-warning' : '' ?>">
                            <td>
                                <a class="fw-bold" href="index.php?page=receiving_bay&id=<?= (int)$row['id'] ?>">
                                    <?= htmlspecialchars((string)$row['tracking_code']) ?>
                                </a>
                                <div class="small text-muted"><?= htmlspecialchars(date('d M H:i', strtotime((string)$row['created_at']))) ?></div>
                            </td>
                            <td class="small"><?= htmlspecialchars((string)($row['source_name'] ?: '—')) ?></td>
                            <td><?= (int)$row['item_count'] ?></td>
                            <td>
                                <?php if ($row['status'] === 'docked'): ?>
                                    <span class="badge bg-warning text-dark">Docked</span>
                                    <?php if ((int)($row['unregistered_count'] ?? 0) > 0): ?>
                                        <span class="badge bg-danger"><?= (int)$row['unregistered_count'] ?> custom</span>
                                    <?php endif; ?>
                                <?php elseif ($row['status'] === 'received'): ?>
                                    <span class="badge bg-success">Received</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Returned</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($docks)): ?>
                        <tr><td colspan="4" class="text-center text-muted py-4">Nothing in the bay. Approved V-SIAS inspections will appear here.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <?php if (!$open): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body text-muted py-5 text-center">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                Select a docked consignment. Custom items are registered here, then the Goods Received form generates the GRV.
            </div>
        </div>
        <?php else: ?>
        <div class="card shadow-sm border-0 border-top border-warning border-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div>
                        <div class="small text-muted text-uppercase">Consignment</div>
                        <h4 class="fw-bold mb-0"><?= htmlspecialchars((string)$open['tracking_code']) ?></h4>
                        <div class="small text-muted mt-1">
                            From <?= htmlspecialchars((string)($open['source_name'] ?: '—')) ?>
                            <?php if (!empty($open['intended_location_name'])): ?>
                                · Suggested: <?= htmlspecialchars((string)$open['intended_location_name']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <span class="badge <?= $open['status'] === 'docked' ? 'bg-warning text-dark' : ($open['status'] === 'received' ? 'bg-success' : 'bg-secondary') ?>">
                        <?= htmlspecialchars((string)$open['status']) ?>
                    </span>
                </div>

                <?php if ($open['status'] === 'received' && !empty($open['grv_id'])): ?>
                <div class="alert alert-success small">Already received as GRV #<?= (int)$open['grv_id'] ?>.</div>
                <?php endif; ?>
                <?php
                $customWaiting = 0;
                foreach ($openItems as $it) {
                    if (!empty($it['is_unregistered']) || (int)($it['product_id'] ?? 0) <= 0) {
                        $customWaiting++;
                    }
                }
                ?>
                <?php if ($open['status'] === 'docked' && $customWaiting > 0): ?>
                <div class="alert alert-danger small">
                    <?= $customWaiting ?> unregistered line<?= $customWaiting === 1 ? '' : 's' ?> will be added to the product catalog when you send this consignment to Goods Received.
                </div>
                <?php endif; ?>

                <form method="POST" class="js-confirm-form"
                    data-swal-title="Send this consignment to Goods Received?"
                    data-swal-text="Unregistered lines will be added to the catalog. Stock is not booked until GRV is processed."
                    data-swal-confirm="Send"
                    data-swal-cancel="Cancel"
                    data-swal-icon="question"
                    data-swal-confirm-color="#fd7e14">
                    <input type="hidden" name="dock_id" value="<?= (int)$open['id'] ?>">
                    <?php if ($open['status'] === 'docked'): ?>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">RECEIVE INTO</label>
                        <select name="location_id" class="form-select" required>
                            <option value="">Select location…</option>
                            <?php foreach ($locations as $l): ?>
                                <option value="<?= (int)$l['id'] ?>" <?= ((int)($open['intended_location_id'] ?: $currentLocId) === (int)$l['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$l['name']) ?> (<?= htmlspecialchars((string)($l['type'] ?? '')) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">This location is sent through to the Goods Received form. Vendor stock can only go to warehouse or kitchen.</div>
                    </div>
                    <?php endif; ?>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered align-middle">
                            <thead class="table-light small">
                                <tr>
                                    <th>Item</th>
                                    <th>Batch</th>
                                    <th>UNSPSC</th>
                                    <th>Qty</th>
                                    <th>Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($openItems as $item): ?>
                                <?php $custom = !empty($item['is_unregistered']) || (int)($item['product_id'] ?? 0) <= 0; ?>
                                <tr class="<?= $custom ? 'table-danger' : '' ?>">
                                    <td>
                                        <?php if ($open['status'] === 'docked' && $custom): ?>
                                        <span class="badge bg-danger mb-1">Unregistered</span>
                                        <input type="text" class="form-control form-control-sm"
                                               name="items[<?= (int)$item['id'] ?>][item_name]"
                                               value="<?= htmlspecialchars((string)$item['item_name']) ?>"
                                               placeholder="Product name" required>
                                        <input type="text" class="form-control form-control-sm mt-1"
                                               name="items[<?= (int)$item['id'] ?>][sku]"
                                               value="<?= htmlspecialchars((string)($item['sku'] ?? '')) ?>"
                                               placeholder="SKU (optional)">
                                        <?php else: ?>
                                        <div class="fw-semibold"><?= htmlspecialchars((string)($item['product_name'] ?: $item['item_name'])) ?></div>
                                        <div class="small text-muted"><?= htmlspecialchars((string)($item['sku'] ?: '')) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><code><?= htmlspecialchars((string)($item['batch_number'] ?: '—')) ?></code></td>
                                    <td>
                                        <?php if ($open['status'] === 'docked' && $custom): ?>
                                        <input type="text" class="form-control form-control-sm"
                                               name="items[<?= (int)$item['id'] ?>][unspsc]"
                                               value="<?= htmlspecialchars((string)($item['unspsc_code'] ?? '')) ?>"
                                               placeholder="8-digit" maxlength="16">
                                        <?php else: ?>
                                        <code><?= htmlspecialchars((string)($item['unspsc_code'] ?: '—')) ?></code>
                                        <?php endif; ?>
                                    </td>
                                    <td style="width: 7rem;">
                                        <?php if ($open['status'] === 'docked'): ?>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                               name="items[<?= (int)$item['id'] ?>][qty]" value="<?= htmlspecialchars((string)$item['quantity']) ?>">
                                        <?php else: ?>
                                        <?= htmlspecialchars((string)$item['quantity']) ?> <?= htmlspecialchars((string)$item['unit']) ?>
                                        <?php endif; ?>
                                    </td>
                                    <td style="width: 7rem;">
                                        <?php if ($open['status'] === 'docked'): ?>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                               name="items[<?= (int)$item['id'] ?>][cost]" value="<?= htmlspecialchars((string)$item['unit_cost']) ?>">
                                        <?php else: ?>
                                        <?= number_format((float)$item['unit_cost'], 2) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php if (empty($openItems)): ?>
                                <tr><td colspan="5" class="text-muted text-center">No lines.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($open['status'] === 'docked'): ?>
                    <div class="d-flex justify-content-between">
                        <button type="submit" name="send_to_grv" value="1" class="btn btn-warning fw-bold ms-auto">
                            Send to Goods Received
                        </button>
                    </div>
                    <?php endif; ?>
                </form>
                <?php if ($open['status'] === 'docked'): ?>
                <form method="POST" class="mt-2 js-confirm-form"
                    data-swal-title="Return this consignment?"
                    data-swal-text="It will leave the bay without adding stock."
                    data-swal-confirm="Return"
                    data-swal-cancel="Keep"
                    data-swal-icon="warning">
                    <input type="hidden" name="dock_id" value="<?= (int)$open['id'] ?>">
                    <button type="submit" name="cancel_dock" value="1" class="btn btn-outline-secondary">
                        Return from bay
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
