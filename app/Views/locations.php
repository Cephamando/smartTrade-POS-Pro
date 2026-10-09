<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">📍 Location Management</h3>
        <p class="text-muted small">Manage operational capabilities across physical branches.</p>
    </div>
    <button class="btn btn-primary fw-bold shadow-sm" onclick="openLocationModal()">
        <i class="bi bi-geo-alt-fill"></i> Add Location
    </button>
</div>

<?php if ($userRole === 'dev' && $pendingCount > 0): ?>
<div class="alert alert-warning border-warning shadow-sm d-flex align-items-center mb-4">
    <i class="bi bi-exclamation-circle-fill fs-3 me-3 text-warning"></i>
    <div>
        <strong class="d-block">Pending Edits Require Your Approval</strong>
        There are <?= $pendingCount ?> location modifications submitted by administrators waiting for your review.
    </div>
</div>
<?php endif; ?>

<?php
$bulkFormId = 'bulkLocations';
$bulkAction = 'index.php?page=locations';
$bulkHidden = ['dev_username' => '', 'dev_password' => ''];
$bulkButtons = [
    ['value' => 'delete', 'label' => 'Delete selected', 'class' => 'btn-outline-danger', 'title' => 'Delete the selected locations?', 'text' => 'Locations that still have stock or sales are kept.', 'confirm' => 'Delete', 'color' => '#dc3545', 'auth' => ($userRole ?? '') === 'dev' ? '' : 'dev'],
];
include __DIR__ . '/partials/bulk_bar.php';
?>
<div class="card shadow-sm border-0 border-top border-primary border-4">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-dark">
                <tr>
                    <th style="width:36px"><input type="checkbox" class="form-check-input bulk-all" data-bulk-form="bulkLocations" title="Select all"></th>
                    <th>Location Name</th>
                    <th>Type</th>
                    <th>Capabilities</th>
                    <th>Address / Notes</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($locations as $l): ?>
                <tr class="<?= !empty($l['pending']) ? 'table-warning bg-opacity-10' : '' ?>">
                    <td><input type="checkbox" class="form-check-input bulk-one" name="ids[]" value="<?= (int)$l['id'] ?>" form="bulkLocations"></td>
                    <td>
                        <span class="fw-bold d-block"><?= htmlspecialchars($l['name']) ?></span>
                        <?php if (!empty($l['pending'])): ?>
                            <span class="badge bg-warning text-dark mt-1"><i class="bi bi-hourglass-split"></i> Edit Pending (<?= htmlspecialchars($l['pending']['requested_by']) ?>)</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($l['type'] === 'warehouse'): ?>
                            <span class="badge bg-secondary">Warehouse</span>
                        <?php elseif ($l['type'] === 'kitchen'): ?>
                            <span class="badge bg-warning text-dark">Kitchen</span>
                        <?php else: ?>
                            <span class="badge bg-success">Store</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($l['can_sell'])): ?>
                            <span class="badge bg-info text-dark shadow-sm me-1"><i class="bi bi-cart"></i> POS</span>
                        <?php endif; ?>
                        <?php if (!empty($l['can_receive'])): ?>
                            <span class="badge bg-primary shadow-sm"><i class="bi bi-box-arrow-in-down"></i> Receiver</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($l['address'] ?? '-') ?></td>
                    <td class="text-end text-nowrap">
                        <?php if (!empty($l['can_sell'])): ?>
                            <form method="POST" action="index.php?page=pos" class="d-inline">
                                <input type="hidden" name="set_pos_location" value="<?= $l['id'] ?>">
                                <button type="submit" class="btn btn-outline-success btn-sm me-1" title="Launch POS"><i class="bi bi-shop"></i></button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm me-1" disabled title="POS disabled"><i class="bi bi-shop"></i></button>
                        <?php endif; ?>

                        <?php if (!empty($l['pending']) && $userRole === 'dev'): ?>
                            <button class="btn btn-warning btn-sm me-1 fw-bold text-dark" onclick="reviewLocation(<?= htmlspecialchars(json_encode($l, JSON_UNESCAPED_UNICODE)) ?>)">
                                <i class="bi bi-eye"></i> Review
                            </button>
                        <?php else: ?>
                            <button class="btn btn-outline-primary btn-sm me-1" onclick="editLocation(<?= htmlspecialchars(json_encode($l, JSON_UNESCAPED_UNICODE)) ?>)">
                                <i class="bi bi-pencil"></i> <?= !empty($l['pending']) ? 'Overwrite Edit' : 'Edit' ?>
                            </button>
                        <?php endif; ?>

                        <form method="POST" action="index.php?page=locations" class="d-inline" id="deleteForm_<?= $l['id'] ?>">
                            <input type="hidden" name="delete_location" value="1">
                            <input type="hidden" name="location_id" value="<?= $l['id'] ?>">
                            <?php if ($userRole !== 'dev'): ?>
                                <input type="hidden" name="dev_username" class="auth-user-<?= $l['id'] ?>">
                                <input type="hidden" name="dev_password" class="auth-pass-<?= $l['id'] ?>">
                            <?php endif; ?>
                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="promptDelete(<?= $l['id'] ?>, '<?= htmlspecialchars(addslashes($l['name'])) ?>')" title="Delete location">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal for Admin/Dev to Edit Location -->
<div class="modal fade" id="locModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-primary border-top border-4 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="locModalTitle">Add New Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=locations" id="locForm">
                <div class="modal-body p-4">
                    <input type="hidden" name="add_location" id="locAction" value="1">
                    <input type="hidden" name="location_id" id="locId" value="">
                    
                    <?php if ($userRole !== 'dev'): ?>
                    <div class="alert alert-info py-2 small mb-3 border-info" id="editNotice" style="display:none;">
                        <i class="bi bi-info-circle-fill"></i> Edits will be queued for developer approval.
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">LOCATION NAME</label>
                        <input type="text" name="name" id="locName" class="form-control fw-bold" required placeholder="e.g. Main Bar">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">PRIMARY TYPE</label>
                        <select name="type" id="locType" class="form-select">
                            <option value="store">Store (Sales Point)</option>
                            <option value="kitchen">Kitchen (Production)</option>
                            <option value="warehouse">Warehouse (Storage)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">OPERATIONAL CAPABILITIES</label>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" name="can_sell" id="locCanSell" value="1">
                            <label class="form-check-label fw-bold" for="locCanSell">POS Enabled (Can ring up sales)</label>
                        </div>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" name="can_receive" id="locCanReceive" value="1">
                            <label class="form-check-label fw-bold" for="locCanReceive">Goods Receiver (Can receive vendor GRVs)</label>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">ADDRESS / NOTES</label>
                        <textarea name="address" id="locAddress" class="form-control" rows="2"></textarea>
                    </div>

                    <?php if ($userRole !== 'dev'): ?>
                    <div class="p-3 bg-dark rounded shadow-sm border border-danger mt-4" id="devAuthSection">
                        <label class="form-label small fw-bold text-danger mb-3 d-block border-bottom border-danger pb-2">
                            <i class="bi bi-shield-lock-fill"></i> DEVELOPER AUTHORIZATION REQUIRED
                        </label>
                        <input type="text" name="dev_username" id="devUser" class="form-control mb-2" placeholder="Developer Username">
                        <input type="password" name="dev_password" id="devPass" class="form-control" placeholder="Developer Password">
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-0">
                    <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm" id="locSaveBtn">Create Location</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal for Developer to Review Pending Edits -->
<?php if ($userRole === 'dev'): ?>
<div class="modal fade" id="reviewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content border-warning border-top border-4 shadow-lg">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-ui-checks"></i> Review Pending Edits</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <p class="text-muted small mb-4 pb-2 border-bottom">Requested by <strong class="text-dark" id="revRequestedBy"></strong> on <span id="revRequestedAt"></span></p>
                
                <div class="row g-2 mb-2">
                    <div class="col-6 fw-bold text-muted small text-uppercase">Current State</div>
                    <div class="col-6 fw-bold text-primary small text-uppercase">Requested Changes</div>
                </div>
                
                <div class="row g-2 mb-3 bg-white p-2 rounded border shadow-sm">
                    <div class="col-6 border-end" id="currName"></div>
                    <div class="col-6 fw-bold text-primary" id="reqName"></div>
                </div>
                <div class="row g-2 mb-3 bg-white p-2 rounded border shadow-sm">
                    <div class="col-6 border-end" id="currType"></div>
                    <div class="col-6 fw-bold text-primary" id="reqType"></div>
                </div>
                <div class="row g-2 mb-3 bg-white p-2 rounded border shadow-sm">
                    <div class="col-6 border-end">POS: <span id="currPos"></span><br>Receiver: <span id="currRec"></span></div>
                    <div class="col-6 fw-bold text-primary">POS: <span id="reqPos"></span><br>Receiver: <span id="reqRec"></span></div>
                </div>
                <div class="row g-2 bg-white p-2 rounded border shadow-sm">
                    <div class="col-6 small text-muted border-end" id="currAddr"></div>
                    <div class="col-6 small fw-bold text-primary" id="reqAddr"></div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-light">
                <form method="POST" action="index.php?page=locations" class="w-100 d-flex gap-2 mb-0">
                    <input type="hidden" name="location_id" id="revLocId" value="">
                    <button type="submit" name="reject_edit" value="1" class="btn btn-outline-danger w-50 fw-bold shadow-sm">Reject Edits</button>
                    <button type="submit" name="approve_edit" value="1" class="btn btn-success w-50 fw-bold shadow-sm">Approve & Apply</button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    const isDev = <?= json_encode($userRole === 'dev') ?>;

    function openLocationModal() {
        document.getElementById('locForm').reset();
        document.getElementById('locModalTitle').innerText = 'Add New Location';
        document.getElementById('locSaveBtn').innerText = 'Create Location';
        document.getElementById('locAction').name = 'add_location';
        
        document.getElementById('locCanSell').checked = true;
        document.getElementById('locCanReceive').checked = false;
        
        if (!isDev) {
            document.getElementById('editNotice').style.display = 'none';
            document.getElementById('devAuthSection').style.display = 'block';
            document.getElementById('devUser').required = true;
            document.getElementById('devPass').required = true;
        }
        
        new bootstrap.Modal(document.getElementById('locModal')).show();
    }

    function editLocation(loc) {
        document.getElementById('locForm').reset();
        document.getElementById('locModalTitle').innerText = loc.pending ? 'Overwrite Pending Edit' : 'Edit Location';
        document.getElementById('locSaveBtn').innerText = isDev ? 'Apply Updates' : 'Submit for Approval';
        document.getElementById('locAction').name = 'edit_location';
        document.getElementById('locId').value = loc.id;
        
        document.getElementById('locName').value = loc.name;
        document.getElementById('locType').value = loc.type;
        document.getElementById('locAddress').value = loc.address || '';
        document.getElementById('locCanSell').checked = parseInt(loc.can_sell || 0) === 1;
        document.getElementById('locCanReceive').checked = parseInt(loc.can_receive || 0) === 1;

        if (!isDev) {
            document.getElementById('editNotice').style.display = 'block';
            document.getElementById('devAuthSection').style.display = 'none';
            document.getElementById('devUser').required = false;
            document.getElementById('devPass').required = false;
        }

        new bootstrap.Modal(document.getElementById('locModal')).show();
    }

    function reviewLocation(loc) {
        if (!loc.pending) return;
        
        document.getElementById('revLocId').value = loc.id;
        document.getElementById('revRequestedBy').innerText = loc.pending.requested_by || 'Unknown';
        document.getElementById('revRequestedAt').innerText = loc.pending.requested_at || 'Recently';
        
        document.getElementById('currName').innerText = loc.name;
        document.getElementById('reqName').innerText = loc.pending.name;
        
        document.getElementById('currType').innerText = loc.type.toUpperCase();
        document.getElementById('reqType').innerText = loc.pending.type.toUpperCase();
        
        document.getElementById('currPos').innerHTML = parseInt(loc.can_sell) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>';
        document.getElementById('reqPos').innerHTML = parseInt(loc.pending.can_sell) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>';
        
        document.getElementById('currRec').innerHTML = parseInt(loc.can_receive) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>';
        document.getElementById('reqRec').innerHTML = parseInt(loc.pending.can_receive) ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-x-circle text-danger"></i>';
        
        document.getElementById('currAddr').innerText = loc.address || 'None';
        document.getElementById('reqAddr').innerText = loc.pending.address || 'None';
        
        new bootstrap.Modal(document.getElementById('reviewModal')).show();
    }

    function promptDelete(id, name) {
        if (isDev) {
            AppAlert.confirm({
                title: 'Delete ' + name + '?',
                text: 'This location will be removed if it has no stock or sales history.',
                icon: 'warning',
                confirmText: 'Delete',
                confirmColor: '#dc3545'
            }).then((result) => {
                if (result) document.getElementById('deleteForm_' + id).submit();
            });
        } else {
            Swal.fire({
                title: 'Developer Auth Required',
                html: '<p class="text-muted small">Deleting a location requires immediate developer authorization.</p>' +
                      '<input id="swal-dev-user" class="swal2-input" placeholder="Developer Username">' +
                      '<input id="swal-dev-pass" type="password" class="swal2-input" placeholder="Developer Password">',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Authorize Delete',
                preConfirm: () => {
                    return {
                        user: document.getElementById('swal-dev-user').value,
                        pass: document.getElementById('swal-dev-pass').value
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    if (!result.value.user || !result.value.pass) {
                        AppAlert.notify({ icon: 'error', text: 'Credentials required.' });
                        return;
                    }
                    document.querySelector('.auth-user-' + id).value = result.value.user;
                    document.querySelector('.auth-pass-' + id).value = result.value.pass;
                    document.getElementById('deleteForm_' + id).submit();
                }
            });
        }
    }
</script>
