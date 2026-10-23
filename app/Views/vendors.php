<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>🏢 Vendor Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorModal">
        <i class="bi bi-plus-lg"></i> Add New Vendor
    </button>
</div>

<?php
$bulkFormId = 'bulkVendors';
$bulkAction = 'index.php?page=vendors';
$bulkButtons = [
    ['value' => 'delete', 'label' => 'Delete selected', 'class' => 'btn-outline-danger', 'title' => 'Delete the selected vendors?', 'text' => 'Vendors with goods-received records are kept.', 'confirm' => 'Delete', 'color' => '#dc3545'],
];
include __DIR__ . '/partials/bulk_bar.php';
?>
<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th style="width:36px"><input type="checkbox" class="form-check-input bulk-all" data-bulk-form="bulkVendors" title="Select all"></th>
                    <th>Vendor Name</th>
                    <th>Contact Person</th>
                    <th>Phone Number</th>
                    <th>Portal</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $v): ?>
                <tr>
                    <td><input type="checkbox" class="form-check-input bulk-one" name="ids[]" value="<?= (int)$v['id'] ?>" form="bulkVendors"></td>
                    <td class="fw-bold"><?= htmlspecialchars($v['name']) ?></td>
                    <td><?= htmlspecialchars($v['contact_person'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($v['phone'] ?? '-') ?></td>
                    <td>
                        <?php if (!empty($v['portal_active']) && !empty($v['portal_email'])): ?>
                            <span class="badge bg-success">On</span>
                            <div class="small text-muted"><?= htmlspecialchars((string)$v['portal_email']) ?></div>
                        <?php else: ?>
                            <span class="badge bg-secondary">Off</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#portalModal"
                            data-id="<?= (int)$v['id'] ?>"
                            data-name="<?= htmlspecialchars((string)$v['name'], ENT_QUOTES) ?>"
                            data-email="<?= htmlspecialchars((string)($v['portal_email'] ?? ''), ENT_QUOTES) ?>"
                            data-active="<?= !empty($v['portal_active']) ? '1' : '0' ?>">Portal</button>
                        <form method="POST" action="index.php?page=vendors" class="d-inline js-confirm-form"
                            data-swal-title="Delete this vendor?"
                            data-swal-text="This cannot be undone."
                            data-swal-confirm="Delete"
                            data-swal-cancel="Cancel"
                            data-swal-icon="warning">
                            <input type="hidden" name="delete_vendor" value="1">
                            <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($vendors)): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No vendors found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="vendorModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Vendor</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=vendors">
                <div class="modal-body">
                    <input type="hidden" name="add_vendor" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Vendor Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Zambia Beef Ltd">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Contact Person</label>
                        <input type="text" name="contact_person" class="form-control" placeholder="e.g. Mr. Phiri">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="text" name="phone" class="form-control" placeholder="097...">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Vendor</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="portalModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Vendor portal</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=vendors">
                <div class="modal-body">
                    <input type="hidden" name="save_portal" value="1">
                    <input type="hidden" name="vendor_id" id="portalVendorId" value="">
                    <p class="small text-muted mb-3" id="portalVendorName"></p>
                    <p class="small">The supplier signs in at <a href="index.php?page=vendor" target="_blank">the vendor portal</a>. Leave the password blank to keep the current one. A new login with no password is given one, shown once.</p>
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="portal_email" id="portalEmail" class="form-control" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="text" name="portal_password" class="form-control" autocomplete="new-password" placeholder="At least 8 characters">
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="portal_active" value="1" id="portalActive">
                        <label class="form-check-label" for="portalActive">Portal login on</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save login</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('portalModal')?.addEventListener('show.bs.modal', function (event) {
    var btn = event.relatedTarget;
    if (!btn) return;
    document.getElementById('portalVendorId').value = btn.getAttribute('data-id') || '';
    document.getElementById('portalVendorName').textContent = btn.getAttribute('data-name') || '';
    document.getElementById('portalEmail').value = btn.getAttribute('data-email') || '';
    document.getElementById('portalActive').checked = btn.getAttribute('data-active') === '1';
});
</script>
