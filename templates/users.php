<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>👥 Staff Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#userModal">
        <i class="bi bi-person-plus-fill"></i> Add New Staff
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <table class="table table-hover align-middle mb-0">
            <thead class="bg-light">
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Assigned Location</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td class="fw-bold">
                        <i class="bi bi-person-circle text-muted me-2"></i><?= htmlspecialchars($u['username']) ?>
                    </td>
                    <td>
                        <?php 
                            $badge = match($u['role']) {
                                'admin', 'dev' => 'bg-danger',
                                'manager' => 'bg-warning text-dark',
                                'chef' => 'bg-info text-dark',
                                default => 'bg-secondary'
                            };
                        ?>
                        <span class="badge <?= $badge ?>"><?= ucfirst($u['role']) ?></span>
                    </td>
                    <td><?= htmlspecialchars($u['location_name'] ?? 'All Locations') ?></td>
                    <td>
                        <?php if($u['force_password_change']): ?>
                            <span class="badge bg-warning text-dark">Reset Pending</span>
                        <?php else: ?>
                            <span class="badge bg-success">Active</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-outline-warning me-1" 
                                onclick="resetPass(<?= $u['id'] ?>, '<?= htmlspecialchars($u['username']) ?>')">
                            <i class="bi bi-key"></i>
                        </button>
                        
                        <form method="POST" action="index.php?page=users" class="d-inline" onsubmit="return confirm('Permanently delete <?= $u['username'] ?>?');">
                            <input type="hidden" name="delete_user" value="1">
                            <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Staff</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=users">
                <div class="modal-body">
                    <input type="hidden" name="add_user" value="1">
                    
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Initial Password</label>
                        <input type="text" name="password" class="form-control" value="password123" required>
                    </div>
                    
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="cashier">Cashier</option>
                                <option value="waiter">Waiter</option>
                                <option value="bartender">Bartender</option>
                                <option value="chef">Chef</option>
                                <option value="manager">Manager</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Location</label>
                            <select name="location_id" class="form-select" required>
                                <?php foreach($locations as $l): ?>
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

<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title text-dark">Reset Password</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="index.php?page=users">
                <div class="modal-body">
                    <input type="hidden" name="reset_password" value="1">
                    <input type="hidden" name="user_id" id="resetUserId">
                    <p>Reset password for <strong id="resetUserName"></strong>?</p>
                    
                    <label>New Temporary Password</label>
                    <input type="text" name="new_password" class="form-control" value="reset123" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-warning">Confirm Reset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetPass(id, name) {
    document.getElementById('resetUserId').value = id;
    document.getElementById('resetUserName').innerText = name;
    new bootstrap.Modal(document.getElementById('resetModal')).show();
}
</script>
