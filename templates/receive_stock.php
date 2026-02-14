<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receive Stock (GRV)</title>
    <link rel="shortcut icon" href="data:image/x-icon;," type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f8f9fa; }
        .header-bar { background-color: #3e2723; color: white; padding: 15px; border-bottom: 4px solid #ffc107; }
        .form-label { font-weight: bold; font-size: 0.9rem; color: #555; }
    </style>
</head>
<body>

<div class="header-bar d-flex justify-content-between align-items-center mb-4">
    <h4 class="m-0"><i class="bi bi-box-seam-fill me-2"></i> Receive Stock (GRV)</h4>
    <div>
        <a href="index.php?page=inventory" class="btn btn-outline-light btn-sm fw-bold">Back to Inventory</a>
        <a href="index.php?page=dashboard" class="btn btn-warning btn-sm fw-bold text-dark">Dashboard</a>
    </div>
</div>

<div class="container-fluid px-4">
    <form method="POST" id="grvForm">
        <input type="hidden" name="process_grv" value="1">
        
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-dark text-white fw-bold">New Goods Received Voucher</div>
            <div class="card-body bg-light">
                <div class="row g-3">
                    
                    <div class="col-md-4">
                        <label class="form-label">Receiving Into (Current Location)</label>
                        <select name="location_id" class="form-select border-success fw-bold" required>
                            <option value="">Select Location...</option>
                            <?php foreach($locations as $l): ?>
                                <option value="<?= $l['id'] ?>" <?= ($currentLocId == $l['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($l['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">Stock will be added to this location.</div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Supplier / Vendor</label>
                        <select name="vendor_id" class="form-select">
                            <option value="">Unknown / None</option>
                            <?php foreach($vendors as $v): ?>
                                <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Invoice / Ref Number</label>
                        <input type="text" name="ref_number" class="form-control" placeholder="e.g. INV-2026-001">
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold text-secondary">Items Received</h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 40%;">Product</th>
                            <th style="width: 20%;">Quantity</th>
                            <th style="width: 20%;">Unit Cost (ZMW)</th>
                            <th style="width: 10%;" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody id="itemsTableBody">
                        </tbody>
                </table>
                <div class="p-3 bg-light border-top">
                    <button type="button" class="btn btn-secondary fw-bold" onclick="addRow()"><i class="bi bi-plus-circle me-1"></i> Add Item</button>
                </div>
            </div>
            <div class="card-footer bg-white p-3 text-end">
                <button type="submit" class="btn btn-warning fw-bold px-5 py-2 shadow-sm">PROCESS GRV</button>
            </div>
        </div>
    </form>
</div>

<script>
    const products = <?= json_encode($products) ?>;
    let rowCount = 0;

    function addRow() {
        const tbody = document.getElementById('itemsTableBody');
        const tr = document.createElement('tr');
        tr.id = `row-${rowCount}`;
        
        let options = '<option value="">Select Product...</option>';
        products.forEach(p => {
            options += `<option value="${p.id}" data-cost="${p.cost_price}">${p.name} (${p.sku})</option>`;
        });

        tr.innerHTML = `
            <td>
                <select name="items[${rowCount}][product_id]" class="form-select border-0" onchange="updateCost(this, ${rowCount})" required>
                    ${options}
                </select>
            </td>
            <td>
                <input type="number" name="items[${rowCount}][qty]" class="form-control border-0" placeholder="0" required>
            </td>
            <td>
                <input type="number" step="0.01" name="items[${rowCount}][cost]" id="cost-${rowCount}" class="form-control border-0" placeholder="0.00">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger border-0" onclick="removeRow(${rowCount})"><i class="bi bi-trash-fill"></i></button>
            </td>
        `;
        tbody.appendChild(tr);
        rowCount++;
    }

    function removeRow(id) {
        document.getElementById(`row-${id}`).remove();
    }

    function updateCost(select, id) {
        const cost = select.options[select.selectedIndex].getAttribute('data-cost');
        document.getElementById(`cost-${id}`).value = cost;
    }

    // Initialize with one row
    document.addEventListener('DOMContentLoaded', addRow);

    // Alerts
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
