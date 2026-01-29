<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow border-warning">
            <div class="card-header bg-warning text-dark">
                <h4 class="mb-0"><i class="bi bi-arrow-left-right"></i> Internal Stock Transfer</h4>
            </div>
            <div class="card-body">
                
                <?php if(empty($availableProducts)): ?>
                    <div class="alert alert-warning">
                        You have no stock in this location to transfer. <a href="index.php?page=receive">Receive some stock first</a>.
                    </div>
                <?php else: ?>

                <form method="POST" action="index.php?page=transfers">
                    <input type="hidden" name="create_transfer" value="1">

                    <div class="mb-4">
                        <label class="form-label fw-bold">Destination Location</label>
                        <select name="destination_id" class="form-select" required>
                            <option value="">-- Select Destination --</option>
                            <?php foreach ($destinations as $d): ?>
                                <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?> (<?= ucfirst($d['type']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <h5 class="border-bottom pb-2">Items to Transfer</h5>
                    <table class="table table-sm" id="transferTable">
                        <thead>
                            <tr>
                                <th>Product (Available Qty)</th>
                                <th width="150">Transfer Qty</th>
                                <th width="50"></th>
                            </tr>
                        </thead>
                        <tbody id="transferBody">
                            </tbody>
                    </table>

                    <button type="button" class="btn btn-outline-dark btn-sm mb-3" onclick="addTransferRow()">
                        + Add Line
                    </button>

                    <div class="d-grid">
                        <button type="submit" class="btn btn-dark btn-lg">Process Transfer</button>
                    </div>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
const stockItems = <?= json_encode($availableProducts) ?>;

function addTransferRow() {
    let rowId = Date.now();
    let options = stockItems.map(p => `<option value="${p.id}">${p.name} (Avail: ${parseFloat(p.quantity)})</option>`).join('');
    
    let html = `
    <tr id="row_${rowId}">
        <td>
            <select name="product_ids[]" class="form-select form-select-sm" required>
                <option value="">Select Product...</option>
                ${options}
            </select>
        </td>
        <td>
            <input type="number" step="0.01" name="quantities[]" class="form-control form-control-sm" placeholder="Qty" required>
        </td>
        <td>
            <button type="button" class="btn btn-danger btn-sm" onclick="document.getElementById('row_${rowId}').remove()">&times;</button>
        </td>
    </tr>`;
    
    document.getElementById('transferBody').insertAdjacentHTML('beforeend', html);
}

// Add one row initially
if (stockItems.length > 0) {
    document.addEventListener("DOMContentLoaded", function() { addTransferRow(); });
}
</script>