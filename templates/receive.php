<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-box-seam"></i> Receive Stock (GRV)</h4>
            </div>
            <div class="card-body">
                <form method="POST" action="index.php?page=receive">
                    <input type="hidden" name="process_grv" value="1">

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Vendor</label>
                            <select name="vendor_id" class="form-select" required>
                                <option value="">-- Select Vendor --</option>
                                <?php foreach ($vendors as $v): ?>
                                    <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Invoice Ref No.</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="e.g. INV-2024-001">
                        </div>
                    </div>

                    <table class="table table-bordered" id="grvTable">
                        <thead class="bg-light">
                            <tr>
                                <th width="40%">Product</th>
                                <th width="20%">Qty</th>
                                <th width="30%">Unit Cost</th>
                                <th width="10%"></th>
                            </tr>
                        </thead>
                        <tbody id="grvBody">
                            </tbody>
                    </table>

                    <button type="button" class="btn btn-outline-secondary btn-sm mb-3" onclick="addRow()">
                        <i class="bi bi-plus-circle"></i> Add Item
                    </button>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-success btn-lg">Complete Receiving</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Pass PHP products array to JS
const products = <?= json_encode($products) ?>;

function addRow() {
    let rowId = Date.now();
    let options = products.map(p => `<option value="${p.id}">${p.name} (${p.unit})</option>`).join('');
    
    let html = `
    <tr id="row_${rowId}">
        <td>
            <select name="product_ids[]" class="form-select form-select-sm" required>
                <option value="">Select...</option>
                ${options}
            </select>
        </td>
        <td>
            <input type="number" step="0.01" name="quantities[]" class="form-control form-control-sm" placeholder="0" required>
        </td>
        <td>
            <input type="number" step="0.01" name="costs[]" class="form-control form-control-sm" placeholder="0.00" required>
        </td>
        <td class="text-center">
            <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('row_${rowId}').remove()">
                &times;
            </button>
        </td>
    </tr>`;
    
    document.getElementById('grvBody').insertAdjacentHTML('beforeend', html);
}

// Add first row on load
document.addEventListener("DOMContentLoaded", function() { addRow(); });
</script>