<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>👥 User Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="bi bi-person-plus-fill"></i> Add New User
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Location</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <?php 
                    // LOGIC: Is this row "untouchable"?
                    // It is untouchable if Target is 'dev' AND I am NOT 'dev'
                    $isUntouchable = ($u['role'] === 'dev' && $_SESSION['role'] !== 'dev');
                    $disabledAttr = $isUntouchable ? 'disabled style="pointer-events: none; opacity: 0.5;"' : '';
                ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($u['full_name'] ?? '') ?></div>
                        <div class="text-muted small">@<?= htmlspecialchars($u['username'] ?? '') ?></div>
                    </td>
                    <td>
                        <span class="badge bg-secondary text-uppercase"><?= str_replace('_', ' ', $u['role']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($u['location_name'] ?? 'All Locations') ?></td>
                    <td>
                        <?php if ($u['force_password_change']): ?>
                            <span class="badge bg-warning text-dark">Reset Pending</span>
                        <?php else: ?>
                            <span class="badge bg-success">Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        
                        <button class="btn btn-sm btn-outline-primary me-1 edit-btn" 
                                <?= $disabledAttr ?>
                                data-id="<?= $u['id'] ?>"
                                data-fullname="<?= htmlspecialchars($u['full_name'] ?? '') ?>"
                                data-username="<?= htmlspecialchars($u['username'] ?? '') ?>"
                                data-role="<?= $u['role'] ?>"
                                data-location="<?= $u['location_id'] ?? '' ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        
                        <form method="POST" action="index.php?page=users" class="d-inline">
                            <input type="hidden" name="reset_password_default" value="1">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-outline-warning text-dark me-1" 
                                    <?= $disabledAttr ?>
                                    onclick="confirmReset(event, '<?= htmlspecialchars($u['username'] ?? '') ?>')" 
                                    title="Reset Password">
                                <i class="bi bi-key-fill"></i>
                            </button>
                        </form>

                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <form method="POST" action="index.php?page=users" class="d-inline" onsubmit="return confirm('Delete <?= $u['username'] ?>?');">
                            <input type="hidden" name="delete_user" value="1">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" <?= $disabledAttr ?>>
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Create New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=users">
                <div class="modal-body">
                    <input type="hidden" name="add_user" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <input type="text" name="full_name" class="form-control" required placeholder="e.g. John Phiri">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Username</label>
                            <input type="text" name="username" class="form-control" required placeholder="e.g. jphiri">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Role</label>
                            <select name="role" class="form-select text-capitalize" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r ?>"><?= str_replace('_', ' ', $r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Location</label>
                            <select name="location_id" class="form-select" required>
                                <?php foreach ($locations as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title">Edit User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=users">
                <div class="modal-body">
                    <input type="hidden" name="edit_user" value="1">
                    <input type="hidden" name="user_id" id="editId">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Full Name</label>
                        <input type="text" name="full_name" id="editFullName" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Username</label>
                        <input type="text" name="username" id="editUsername" class="form-control" required>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Role</label>
                            <select name="role" id="editRole" class="form-select text-capitalize" required>
                                <?php foreach ($roles as $r): ?>
                                    <option value="<?= $r ?>"><?= str_replace('_', ' ', $r) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Location</label>
                            <select name="location_id" id="editLocation" class="form-select" required>
                                <?php foreach ($locations as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <hr>
                    <div class="mb-3">
                        <label class="form-label text-muted small">New Password (leave blank to keep current)</label>
                        <input type="password" name="password" class="form-control" placeholder="******">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const editButtons = document.querySelectorAll('.edit-btn');
    const editModalEl = document.getElementById('editUserModal');
    const editModal = new bootstrap.Modal(editModalEl);

    editButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Check if disabled (double check for JS)
            if (this.hasAttribute('disabled')) return;

            document.getElementById('editId').value       = this.getAttribute('data-id');
            document.getElementById('editFullName').value = this.getAttribute('data-fullname');
            document.getElementById('editUsername').value = this.getAttribute('data-username');
            document.getElementById('editRole').value     = this.getAttribute('data-role');
            document.getElementById('editLocation').value = this.getAttribute('data-location');
            editModal.show();
        });
    });
});

function confirmReset(e, username) {
    e.preventDefault(); 
    // Check if disabled (just in case)
    if (e.target.closest('button').hasAttribute('disabled')) return;

    const form = e.target.closest('form');
    Swal.fire({
        title: 'Reset Password?',
        text: `Reset password for ${username} to 'pos123'?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        confirmButtonText: 'Yes, Reset'
    }).then((result) => {
        if (result.isConfirmed) form.submit();
    });
}
</script>
