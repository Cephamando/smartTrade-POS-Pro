<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Users</title>
    <link rel="shortcut icon" href="data:image/x-icon;," type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>body { background-color: #f8f9fa; }</style>
</head>
<body class="p-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-people-fill"></i> Staff Management</h3>
    <a href="index.php?page=dashboard" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Dashboard</a>
</div>

<div class="row">
    <div class="col-md-4">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                <span id="formTitle">Add New User</span>
                <button type="button" class="btn btn-sm btn-outline-light d-none" id="btnCancelEdit" onclick="resetForm()">Cancel</button>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="save_user" value="1">
                    <input type="hidden" name="user_id" id="userId">
                    
                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="full_name" id="fullName" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" name="username" id="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Role</label>
                        <select name="role" id="role" class="form-select">
                            <option value="cashier">Cashier</option>
                            <option value="manager">Manager</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Password <small class="text-muted" id="passHelp">(Required for new users)</small></label>
                        <input type="password" name="password" class="form-control" placeholder="******">
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary fw-bold" id="btnSubmit">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Name</th>
                            <th>Username</th>
                            <th>Role</th>
                            <th class="text-end pe-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $u): ?>
                        <tr>
                            <td class="ps-3 fw-bold"><?= htmlspecialchars($u['full_name']) ?></td>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><span class="badge bg-<?= $u['role'] == 'admin' ? 'danger' : ($u['role'] == 'manager' ? 'primary' : 'secondary') ?>"><?= strtoupper($u['role']) ?></span></td>
                            <td class="text-end pe-3">
                                <button class="btn btn-sm btn-outline-primary" onclick='editUser(<?= json_encode($u) ?>)'><i class="bi bi-pencil"></i></button>
                                
                                <?php if($u['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" class="d-inline" onsubmit="return confirmDeactivate(event)">
                                    <input type="hidden" name="delete_user" value="1">
                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger" title="Deactivate"><i class="bi bi-trash"></i></button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editUser(u) {
    document.getElementById('userId').value = u.id;
    document.getElementById('fullName').value = u.full_name;
    document.getElementById('username').value = u.username;
    document.getElementById('role').value = u.role;
    
    // UI Updates
    document.getElementById('formTitle').innerText = "Edit User";
    document.getElementById('btnSubmit').innerText = "Update User";
    document.getElementById('btnSubmit').classList.replace('btn-primary', 'btn-warning');
    document.getElementById('passHelp').innerText = "(Leave blank to keep current)";
    document.getElementById('btnCancelEdit').classList.remove('d-none');
}

function resetForm() {
    document.getElementById('userId').value = '';
    document.getElementById('fullName').value = '';
    document.getElementById('username').value = '';
    document.getElementById('role').value = 'cashier';
    
    // UI Reset
    document.getElementById('formTitle').innerText = "Add New User";
    document.getElementById('btnSubmit').innerText = "Create User";
    document.getElementById('btnSubmit').classList.replace('btn-warning', 'btn-primary');
    document.getElementById('passHelp').innerText = "(Required for new users)";
    document.getElementById('btnCancelEdit').classList.add('d-none');
}

function confirmDeactivate(event) {
    event.preventDefault();
    const form = event.target;
    Swal.fire({
        title: 'Deactivate User?',
        text: "They will no longer be able to log in.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes, Deactivate'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
}

<?php if(isset($_SESSION['swal_msg'])): ?>
Swal.fire({
    icon: <?= json_encode($_SESSION['swal_type']) ?>,
    title: <?= json_encode($_SESSION['swal_msg']) ?>,
    showConfirmButton: false, timer: 1500
});
<?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
<?php endif; ?>
</script>

</body>
</html>
