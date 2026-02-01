<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-people-fill"></i> Membership Management</h3>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMemberModal">
        <i class="bi bi-person-plus"></i> Register Member
    </button>
</div>

<div class="row">
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-primary">
            <div class="card-body text-center">
                <h6 class="text-muted text-uppercase">Total Members</h6>
                <h2 class="display-4 fw-bold text-primary"><?= count($members) ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Member Name</th>
                        <th>Phone Number</th>
                        <th>Points Balance</th>
                        <th>Joined</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($members as $m): ?>
                    <tr>
                        <td class="fw-bold"><?= htmlspecialchars($m['name']) ?></td>
                        <td><?= htmlspecialchars($m['phone']) ?></td>
                        <td>
                            <span class="badge bg-warning text-dark rounded-pill fs-6">
                                <?= number_format($m['points_balance'], 2) ?> pts
                            </span>
                        </td>
                        <td><?= date('d M Y', strtotime($m['created_at'])) ?></td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-secondary" onclick="editMember(<?= $m['id'] ?>)">Edit</button>
                            <button class="btn btn-sm btn-outline-info" onclick="viewHistory(<?= $m['id'] ?>, '<?= htmlspecialchars($m['name']) ?>')">History</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addMemberModal" tabindex="-1">
    <form method="POST" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">Register New Member</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="save_member" value="1">
                <div class="mb-3">
                    <label class="form-label fw-bold">Full Name</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Phone Number (ID)</label>
                    <input type="text" name="phone" class="form-control" required placeholder="e.g. 097...">
                </div>
                <div class="mb-3">
                    <label class="form-label">Email (Optional)</label>
                    <input type="email" name="email" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary w-100">Register Member</button>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="editMemberModal" tabindex="-1">
    <form method="POST" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white">
                <h5 class="modal-title">Edit Member</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="update_member" value="1">
                <input type="hidden" name="member_id" id="edit_id">
                <div class="mb-3">
                    <label class="form-label fw-bold">Full Name</label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Phone Number</label>
                    <input type="text" name="phone" id="edit_phone" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" id="edit_email" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-secondary w-100">Save Changes</button>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-clock-history"></i> Sales History: <span id="history_name"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Points Earned</th>
                            <th>Points Used</th>
                        </tr>
                    </thead>
                    <tbody id="history_body">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function editMember(id) {
    fetch('index.php?page=members&ajax_get_member=1&id=' + id)
        .then(res => res.json())
        .then(data => {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_phone').value = data.phone;
            document.getElementById('edit_email').value = data.email;
            new bootstrap.Modal(document.getElementById('editMemberModal')).show();
        });
}

function viewHistory(id, name) {
    document.getElementById('history_name').innerText = name;
    const tbody = document.getElementById('history_body');
    tbody.innerHTML = '<tr><td colspan="5" class="text-center">Loading...</td></tr>';
    
    new bootstrap.Modal(document.getElementById('historyModal')).show();

    fetch('index.php?page=members&ajax_member_history=1&id=' + id)
        .then(res => res.json())
        .then(data => {
            tbody.innerHTML = '';
            if (data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No purchase history found.</td></tr>';
                return;
            }
            data.forEach(row => {
                let html = `
                    <tr>
                        <td>${new Date(row.created_at).toLocaleDateString()}</td>
                        <td>Receipt #${row.id}</td>
                        <td class="fw-bold">ZMW ${parseFloat(row.final_total).toFixed(2)}</td>
                        <td class="text-success">+${parseFloat(row.points_earned).toFixed(2)}</td>
                        <td class="text-danger">-${parseFloat(row.points_redeemed).toFixed(2)}</td>
                    </tr>
                `;
                tbody.innerHTML += html;
            });
        });
}
</script>
