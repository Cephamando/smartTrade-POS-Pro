<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-0">📍 Location Management</h3>
        <p class="text-muted small">Manage operational capabilities across physical branches.</p>
    </div>
    <button class="btn btn-primary fw-bold shadow-sm" onclick="openLocationModal()">
        <i class="bi bi-geo-alt-fill"></i> Add Location
    </button>
</div>

<div class="card shadow-sm border-0 border-top border-primary border-4">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light text-dark">
                <tr>
                    <th>Location Name</th>
                    <th>Type</th>
                    <th>Capabilities</th>
                    <th>Address / Notes</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($locations as $l): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($l['name']) ?></td>
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
                    <td class="text-end">
                        <?php if (!empty($l['can_sell'])): ?>
                            <form method="POST" action="index.php?page=pos" class="d-inline">
                                <input type="hidden" name="set_pos_location" value="<?= $l['id'] ?>">
                                <button type="submit" class="btn btn-outline-success btn-sm me-1" title="Launch POS"><i class="bi bi-shop"></i> Launch POS</button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn btn-outline-secondary btn-sm me-1" disabled title="POS is disabled for this location"><i class="bi bi-shop"></i> Launch POS</button>
                        <?php endif; ?>

                        <button class="btn btn-outline-primary btn-sm me-1" onclick="editLocation(<?= htmlspecialchars(json_encode($l, JSON_UNESCAPED_UNICODE)) ?>)">
                            <i class="bi bi-pencil"></i> Edit
                        </button>
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
            document.getElementById('devAuthSection').style.display = 'block';
            document.getElementById('devUser').required = true;
            document.getElementById('devPass').required = true;
        }
        
        new bootstrap.Modal(document.getElementById('locModal')).show();
    }

    function editLocation(loc) {
        document.getElementById('locForm').reset();
        document.getElementById('locModalTitle').innerText = 'Edit Location';
        document.getElementById('locSaveBtn').innerText = 'Update Location';
        document.getElementById('locAction').name = 'edit_location';
        document.getElementById('locId').value = loc.id;
        
        document.getElementById('locName').value = loc.name;
        document.getElementById('locType').value = loc.type;
        document.getElementById('locAddress').value = loc.address || '';
        document.getElementById('locCanSell').checked = parseInt(loc.can_sell || 0) === 1;
        document.getElementById('locCanReceive').checked = parseInt(loc.can_receive || 0) === 1;

        if (!isDev) {
            document.getElementById('devAuthSection').style.display = 'none';
            document.getElementById('devUser').required = false;
            document.getElementById('devPass').required = false;
        }

        new bootstrap.Modal(document.getElementById('locModal')).show();
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
                html: '<p class="text-muted small">Deleting a location requires developer authorization.</p>' +
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