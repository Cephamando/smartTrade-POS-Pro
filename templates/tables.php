<div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
        <h5 class="m-0 fw-bold"><i class="bi bi-grid-3x3-gap text-warning me-2"></i> Table Settings</h5>
        <button class="btn btn-warning fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addTableModal"><i class="bi bi-plus-lg"></i> Add New Table</button>
    </div>
    <div class="card-body bg-light">
        <div class="table-responsive bg-white rounded border shadow-sm p-3">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="py-3">Location</th>
                        <th class="py-3">Zone (e.g. Patio)</th>
                        <th class="py-3">Table Name</th>
                        <th class="py-3 text-center">Capacity</th>
                        <th class="py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tables as $t): ?>
                    <tr>
                        <td class="py-3"><span class="badge bg-secondary"><?= htmlspecialchars($t['location_name']) ?></span></td>
                        <td class="py-3 fw-bold text-muted text-uppercase"><?= htmlspecialchars($t['zone_name']) ?></td>
                        <td class="py-3 fw-bold fs-5 text-dark"><?= htmlspecialchars($t['table_name']) ?></td>
                        <td class="py-3 text-center"><i class="bi bi-people-fill text-info me-1"></i> <?= $t['capacity'] ?> Seats</td>
                        <td class="py-3 text-end">
                            <form method="POST" onsubmit="return confirm('Are you sure you want to delete this table?');">
                                <input type="hidden" name="delete_table" value="1">
                                <input type="hidden" name="table_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($tables)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-info-circle display-4 d-block mb-3"></i>
                            No tables configured yet. Click "Add New Table" to build your floorplan!
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addTableModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg border-warning border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-plus-square text-warning me-2"></i> Add New Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="add_table" value="1">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">SELECT LOCATION</label>
                        <select name="location_id" class="form-select form-select-lg" required>
                            <?php foreach($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">ZONE NAME</label>
                            <input type="text" name="zone_name" class="form-control form-control-lg" placeholder="e.g., Main Floor" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">TABLE NAME</label>
                            <input type="text" name="table_name" class="form-control form-control-lg" placeholder="e.g., Table 1" required>
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-muted">SEATING CAPACITY</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white"><i class="bi bi-people-fill text-info"></i></span>
                            <input type="number" name="capacity" class="form-control" placeholder="4" min="1" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold shadow-sm px-4">Save Table</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    <?php if(isset($_SESSION['swal_msg'])): ?>
    Swal.fire({ icon: '<?= addslashes($_SESSION['swal_type']) ?>', title: '<?= addslashes($_SESSION['swal_msg']) ?>', timer: 1500, showConfirmButton: false });
    <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); endif; ?>
</script>
<?php include 'footer.php'; ?>
