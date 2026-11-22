<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-people-fill text-primary"></i> User Management</h3>
    <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#userModal" onclick="resetUserForm()"><i class="bi bi-person-plus-fill"></i> Add New User</button>
</div>

<?php if (($_SESSION['role'] ?? '') === 'dev'): ?>
<?php
$signedInPlaces = [];
foreach ($signedIn as $open) {
    $signedInPlaces[(int)$open['location_id']] = (string)$open['location_name'];
}
?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-header bg-white py-3">
        <div class="fw-bold"><i class="bi bi-person-check text-success"></i> Signed in now</div>
        <div class="small text-muted">Each open browser on this server. Signing someone out does not close their shift. They sign in again to continue.</div>
    </div>
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4">Person</th>
                    <th>Workstation</th>
                    <th>Signed in</th>
                    <th>Last seen</th>
                    <th class="text-end pe-4">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$signedIn): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">Nobody is signed in.</td></tr>
                <?php endif; ?>
                <?php foreach ($signedIn as $open): ?>
                <tr>
                    <td class="ps-4">
                        <span class="fw-bold"><?= htmlspecialchars((string)$open['who']) ?></span>
                        <?php if (!empty($open['yours'])): ?><span class="badge bg-success ms-1">You</span><?php endif; ?>
                        <span class="d-block small text-muted">@<?= htmlspecialchars((string)$open['username']) ?> · <?= htmlspecialchars(str_replace('_', ' ', (string)$open['role'])) ?></span>
                    </td>
                    <td><?= htmlspecialchars((string)$open['location_name']) ?></td>
                    <td class="text-nowrap"><?= htmlspecialchars((string)$open['started_label']) ?></td>
                    <td>
                        <span class="text-nowrap"><?= htmlspecialchars((string)$open['seen_label']) ?></span>
                        <?php if (!empty($open['ip'])): ?><span class="d-block small text-muted"><?= htmlspecialchars((string)$open['ip']) ?></span><?php endif; ?>
                    </td>
                    <td class="text-end pe-4">
                        <form method="POST" class="d-inline js-confirm-form"
                            data-swal-title="<?= !empty($open['yours']) ? 'Sign yourself out?' : 'Sign out ' . htmlspecialchars((string)$open['who']) . '?' ?>"
                            data-swal-text="<?= !empty($open['yours']) ? 'You will leave this page and need to sign in again.' : 'They will need to sign in again. Their shift stays open.' ?>"
                            data-swal-confirm="Sign out"
                            data-swal-cancel="Cancel"
                            data-swal-icon="warning"
                            data-swal-confirm-color="#dc3545">
                            <input type="hidden" name="sign_out_session" value="1">
                            <input type="hidden" name="session_id" value="<?= (int)$open['id'] ?>">
                            <button class="btn btn-sm btn-outline-danger" type="submit">Sign out</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($signedIn): ?>
    <div class="card-footer bg-white d-flex flex-wrap gap-2 align-items-center">
        <form method="POST" class="d-flex flex-wrap gap-2 align-items-center js-confirm-form"
            data-swal-title="Sign out this workstation?"
            data-swal-text="Everyone signed in at the workstation you chose is signed out, except you. Their shifts stay open."
            data-swal-confirm="Sign out"
            data-swal-cancel="Cancel"
            data-swal-icon="warning"
            data-swal-confirm-color="#dc3545">
            <input type="hidden" name="sign_out_location" value="1">
            <select name="location_id" class="form-select form-select-sm no-search" style="width:auto" required>
                <option value="">Choose a workstation</option>
                <?php foreach ($signedInPlaces as $placeId => $placeName): ?>
                <option value="<?= (int)$placeId ?>"><?= htmlspecialchars($placeName) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn btn-sm btn-outline-dark" type="submit">Sign out this workstation</button>
        </form>
        <form method="POST" class="ms-auto js-confirm-form"
            data-swal-title="Sign everyone else out?"
            data-swal-text="Every open browser except yours is signed out. Their shifts stay open."
            data-swal-confirm="Sign everyone out"
            data-swal-cancel="Cancel"
            data-swal-icon="warning"
            data-swal-confirm-color="#dc3545">
            <input type="hidden" name="sign_out_everyone" value="1">
            <button class="btn btn-sm btn-danger" type="submit">Sign out everyone else</button>
        </form>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if (($_SESSION['role'] ?? '') === 'dev'): ?>
<?php include __DIR__ . '/partials/open_shifts.php'; ?>
<?php endif; ?>

<?php
$bulkFormId = 'bulkUsers';
$bulkAction = 'index.php?page=users';
$bulkButtons = [
    ['value' => 'delete', 'label' => 'Delete selected', 'class' => 'btn-outline-danger', 'title' => 'Delete the selected users?', 'text' => 'Accounts with sales history are deactivated instead. The signed-in account and the developer account are skipped.', 'confirm' => 'Delete', 'color' => '#dc3545'],
];
include __DIR__ . '/partials/bulk_bar.php';
?>
<div class="card shadow-sm border-0">
    <div class="card-body p-0 table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th style="width:36px" class="ps-3"><input type="checkbox" class="form-check-input bulk-all" data-bulk-form="bulkUsers" title="Select all"></th>
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
                    <td class="ps-3"><?php if ($u['role'] !== 'dev' && $u['id'] !== $_SESSION['user_id']): ?><input type="checkbox" class="form-check-input bulk-one" name="ids[]" value="<?= (int)$u['id'] ?>" form="bulkUsers"><?php endif; ?></td>
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
                                <form method="POST" class="d-inline js-confirm-form"
                                    data-swal-title="Reset the sign-in code?"
                                    data-swal-text="The next sign-in sets a new authenticator code. Open browsers for this account are signed out."
                                    data-swal-confirm="Reset"
                                    data-swal-cancel="Cancel"
                                    data-swal-icon="warning">
                                    <input type="hidden" name="reset_sign_in" value="1">
                                    <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                    <button class="btn btn-sm btn-outline-secondary" type="submit">Reset code</button>
                                </form>
                            <?php else: ?>
                                <span class="badge bg-light text-muted border py-2"><i class="bi bi-lock-fill"></i> System Locked</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn btn-sm btn-outline-primary" onclick='editUser(<?= json_encode($u) ?>)'><i class="bi bi-pencil"></i></button>
                            <?php if (in_array($u['role'], ['admin', 'manager', 'dev', 'owner'], true)): ?>
                            <form method="POST" class="d-inline js-confirm-form"
                                data-swal-title="Reset the sign-in code?"
                                data-swal-text="The next sign-in sets a new authenticator code. Open browsers for this account are signed out."
                                data-swal-confirm="Reset"
                                data-swal-cancel="Cancel"
                                data-swal-icon="warning">
                                <input type="hidden" name="reset_sign_in" value="1">
                                <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                <button class="btn btn-sm btn-outline-secondary" type="submit">Reset code</button>
                            </form>
                            <?php endif; ?>
                            <?php if ($u['id'] !== $_SESSION['user_id']): ?>
                            <form method="POST" class="d-inline js-confirm-form"
                                data-swal-title="Delete this user?"
                                data-swal-text="The account will be removed. Users with history are deactivated instead."
                                data-swal-confirm="Delete"
                                data-swal-cancel="Cancel"
                                data-swal-icon="warning">
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
                                <option value="owner">Owner</option>
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
                        <input type="password" name="password" id="userPassword" class="form-control" minlength="6" placeholder="Enter password (leave blank to keep current)">
                        <div class="form-text" id="passwordHelp" style="display:none;">Leave empty if you do not want to change the password. At least 6 characters.</div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold text-muted">Till approval code</label>
                        <input type="password" name="approval_code" id="userApproval" class="form-control" minlength="4" maxlength="12" placeholder="Needed for a manager, admin, developer, or head chef" autocomplete="new-password">
                        <div class="form-text">4 to 12 characters, and different from the login password. Leave blank when editing to keep the current code.</div>
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
    document.getElementById('userApproval').value = "";
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
    const locId = parseInt(user.location_id, 10);
    document.getElementById('userLocation').value = (locId > 0) ? String(locId) : '0';
    document.getElementById('userPassword').value = "";
    document.getElementById('userApproval').value = "";
    
    new bootstrap.Modal(document.getElementById('userModal')).show();
}

// End shift runs in the background. The confirm step in app-alerts.js submits the
// form a second time once the developer agrees, and that second submit is sent here.
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!form || !form.classList || !form.classList.contains('js-end-shift-form')) return;
    if (form.dataset.swalConfirmed !== '1' || !window.fetch) return;
    e.preventDefault();
    // Cleared after this event finishes, so the confirm step does not ask again.
    setTimeout(function () { delete form.dataset.swalConfirmed; }, 0);

    const card = document.getElementById('openShiftsCard');
    const body = new FormData(form);
    body.append('ajax', '1');
    const buttons = card ? card.querySelectorAll('button') : [];
    buttons.forEach(function (b) { b.disabled = true; });

    fetch('index.php?page=users', {
        method: 'POST',
        body: body,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
    }).then(function (res) {
        return res.json();
    }).then(function (data) {
        const current = document.getElementById('openShiftsCard');
        if (current && data.html) {
            current.outerHTML = data.html;
        } else {
            buttons.forEach(function (b) { b.disabled = false; });
        }
        AppAlert.notify({ icon: data.status || 'info', text: data.msg || '' });
    }).catch(function () {
        // A signed-out session answers with the login page instead of JSON.
        window.location.href = 'index.php?page=users';
    });
});
</script>
