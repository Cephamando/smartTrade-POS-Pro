<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-people-fill text-primary"></i> User Management</h3>
    <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUserForm()"><i class="bi bi-person-plus-fill"></i> Add New User</button>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Name</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Primary Workstation</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($users as $u): ?>
                <tr>
                    <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($u['full_name']) ?></td>
                    <td class="text-muted">@<?= htmlspecialchars($u['username']) ?></td>
                    <td>
                        <?php if($u['role'] === 'dev'): ?>
                            <span class="badge bg-danger text-uppercase"><i class="bi bi-shield-lock-fill"></i> Developer</span>
                        <?php else: ?>
                            <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($u['role']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['location_name'] ?? 'All Locations (HQ)') ?></td>
                    <td class="text-end pe-4">
                        <?php if ($u['role'] === 'dev'): ?>
                            <?php if ($_SESSION['role'] === 'dev'): ?>
                                <button class="btn btn-sm btn-outline-primary" onclick='editUser(<?= json_encode($u) ?>)'><i class="bi bi-pencil"></i> Edit Profile</button>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border py-2"><i class="bi bi-lock-fill"></i> System Locked</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-primary" onclick='editUser(<?= json_encode($u) ?>)'><i class="bi bi-pencil"></i></button>
                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('WARNING: Are you sure you want to permanently delete this user?');">
                                <input type="hidden" name="delete_user" value="1">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="userModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold" id="userModalTitle">Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="add_user" id="userAction" value="1">
                    <input type="hidden" name="user_id" id="userId">
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Full Name</label>
                        <input type="text" name="full_name" id="userFullName" class="form-control fw-bold" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">Login Username</label>
                        <input type="text" name="username" id="userUsername" class="form-control fw-bold" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">System Role</label>
                            <select name="role" id="userRole" class="form-select fw-bold" required>
                                <?php if ($_SESSION['role'] === 'dev'): ?>
                                    <option value="dev">Developer (Root)</option>
                                <?php endif; ?>
                                <option value="admin">Administrator</option>
                                <option value="manager">Manager</option>
                                <option value="head_chef">Head Chef</option>
                                <option value="chef">Chef</option>
                                <option value="bartender">Bartender</option>
                                <option value="cashier">Cashier</option>
                                <option value="waiter">Waiter</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold text-muted">Primary Workstation</label>
                            <select name="location_id" id="userLocation" class="form-select fw-bold" required>
                                <option value="0">All Locations (HQ)</option>
                                <?php foreach($locations as $l): ?>
                                    <option value="<?= $l['id'] ?>"><?= htmlspecialchars($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted">Password</label>
                        <input type="password" name="password" id="userPassword" class="form-control" placeholder="Enter password (leave blank to keep current)">
                        <div class="form-text" id="passwordHelp" style="display:none;">Leave empty if you do not want to change the password.</div>
                    </div>
                </div>
                <div class="modal-footer border-0 bg-white">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold shadow-sm" id="userSubmitBtn">Save User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function resetUserForm() {
    document.getElementById('userModalTitle').innerText = "Add New User";
    document.getElementById('userAction').name = "add_user";
    document.getElementById('userSubmitBtn').innerText = "Create User";
    document.getElementById('userSubmitBtn').className = "btn btn-primary fw-bold shadow-sm";
    document.getElementById('passwordHelp').style.display = "none";
    document.getElementById('userPassword').required = true;
    
    document.getElementById('userId').value = "";
    document.getElementById('userFullName').value = "";
    document.getElementById('userUsername').value = "";
    document.getElementById('userRole').value = "cashier";
    document.getElementById('userLocation').value = "0";
    document.getElementById('userPassword').value = "";
}

function editUser(user) {
    document.getElementById('userModalTitle').innerText = "Edit User Profile";
    document.getElementById('userAction').name = "edit_user";
    document.getElementById('userSubmitBtn').innerText = "Update User";
    document.getElementById('userSubmitBtn').className = "btn btn-warning fw-bold shadow-sm";
    document.getElementById('passwordHelp').style.display = "block";
    document.getElementById('userPassword').required = false;
    
    document.getElementById('userId').value = user.id;
    document.getElementById('userFullName').value = user.full_name;
    document.getElementById('userUsername').value = user.username;
    document.getElementById('userRole').value = user.role;
    document.getElementById('userLocation').value = user.location_id;
    document.getElementById('userPassword').value = "";
    
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

<?php if(isset($_SESSION['swal_msg'])): ?>
Swal.fire({
    icon: '<?= $_SESSION['swal_type'] ?>',
    title: '<?= $_SESSION['swal_msg'] ?>',
    timer: 2000,
    showConfirmButton: false
});
<?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
<?php endif; ?>
</script>
