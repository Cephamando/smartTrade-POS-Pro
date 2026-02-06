<div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold text-brown"><i class="bi bi-box-seam-fill text-gold"></i> Receive Stock (GRV)</h3>
    <a href="index.php?page=inventory" class="btn btn-outline-dark">Back to Inventory</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-brown text-white" style="background-color: var(--theme-brown);">
        <h5 class="mb-0">New Goods Received Voucher</h5>
    </div>
    <div class="card-body">
        <form method="POST" id="grvForm">
            <input type="hidden" name="receive_stock" value="1">
            
            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <label class="form-label fw-bold">Receiving Into (Current Location)</label>
                    <input type="text" class="form-control bg-light fw-bold text-success" value="<?= htmlspecialchars($_SESSION['location_name'] ?? '') ?>" readonly>
                    <small class="text-muted">You can only receive stock into your active location.</small>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold">Supplier / Vendor</label>
                    <select name="vendor_id" class="form-select" required>
                        <option value="">Select Vendor...</option>
                        <?php foreach($vendors as $v): ?>
                            <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Invoice / Ref Number</label>
                    <input type="text" name="reference_no" class="form-control" placeholder="e.g. INV-2026-001">
                </div>
            </div>

            <h5 class="border-bottom pb-2 mb-3 text-muted">Items Received</h5>

            <table class="table table-bordered" id="grvTable">
                <thead class="table-light">
                    <tr>
                        <th width="40%">Product</th>
                        <th width="20%">Quantity</th>
                        <th width="20%">Unit Cost (ZMW)</th>
                        <th width="10%">Action</th>
                    </tr>
                </thead>
                <tbody id="grvBody">
                    <tr>
                        <td>
                            <select name="products[]" class="form-select" required>
                                <option value="">Select Product...</option>
                                <?php foreach($products as $p): ?>
                                    <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> (<?= $p['unit'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" name="quantities[]" class="form-control" min="1" step="0.01" required></td>
                        <td><input type="number" name="unit_costs[]" class="form-control" min="0" step="0.01" required></td>
                        <td class="text-center"><button type="button" class="btn btn-danger btn-sm remove-row"><i class="bi bi-trash"></i></button></td>
                    </tr>
                </tbody>
            </table>

            <div class="d-flex justify-content-between mt-3">
                <button type="button" class="btn btn-secondary" id="addRow"><i class="bi bi-plus-lg"></i> Add Item</button>
                <button type="submit" class="btn btn-theme-orange px-5">PROCESS GRV</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('addRow').addEventListener('click', function() {
    const table = document.getElementById('grvBody');
    const newRow = table.rows[0].cloneNode(true);
    newRow.querySelectorAll('input').forEach(input => input.value = '');
    newRow.querySelectorAll('select').forEach(select => select.value = '');
    table.appendChild(newRow);
});
document.addEventListener('click', function(e) {
    if (e.target && (e.target.classList.contains('remove-row') || e.target.parentElement.classList.contains('remove-row'))) {
        const table = document.getElementById('grvBody');
        if (table.rows.length > 1) { e.target.closest('tr').remove(); } else { alert("At least one item is required."); }
    }
});
</script>
