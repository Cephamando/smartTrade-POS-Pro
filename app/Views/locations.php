<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>📍 Location Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#locModal">
        <i class="bi bi-geo-alt-fill"></i> Add Location
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Location Name</th>
                    <th>Type</th>
                    <th>Address/Notes</th>
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
                        <?php else: ?>
                            <span class="badge bg-success">Store/Outlet</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= htmlspecialchars($l['address'] ?? '-') ?></td>
                    <td class="text-end">
                        <form method="POST" action="index.php?page=locations" class="d-inline" onsubmit="return confirm('Delete <?= $l['name'] ?>?');">
                            <input type="hidden" name="delete_location" value="1">
                            <input type="hidden" name="location_id" value="<?= $l['id'] ?>">
                            <button class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
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
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Location</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=locations">
                <div class="modal-body">
                    <input type="hidden" name="add_location" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Location Name</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Main Bar">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-select">
                            <option value="store">Store (Sales Point)</option>
                            <option value="warehouse">Warehouse (Storage)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Address / Notes</label>
                        <textarea name="address" class="form-control" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Location</button>
                </div>
            </form>
        </div>
    </div>
</div>
