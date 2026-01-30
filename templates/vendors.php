<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>🏢 Vendor Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#vendorModal">
        <i class="bi bi-plus-lg"></i> Add New Vendor
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Vendor Name</th>
                    <th>Contact Person</th>
                    <th>Phone Number</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $v): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($v['name']) ?></td>
                    <td><?= htmlspecialchars($v['contact_person'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($v['phone'] ?? '-') ?></td>
                    <td class="text-end">
                        <form method="POST" action="index.php?page=vendors" class="d-inline" onsubmit="return confirm('Delete this vendor?');">
                            <input type="hidden" name="delete_vendor" value="1">
                            <input type="hidden" name="vendor_id" value="<?= $v['id'] ?>">
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($vendors)): ?>
                    <tr><td colspan="4" class="text-center py-4 text-muted">No vendors found.</td></tr>
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
