<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-boxes"></i> Inventory Management</h3>
    <div>
        <button class="btn btn-outline-primary" onclick="window.print()">Print List</button>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Product Name</th>
                        <th>Category</th>
                        <th>Price (ZMW)</th>
                        <th class="text-center">Current Stock</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($inventory as $item): ?>
                    <tr class="<?= $item['quantity'] < 5 ? 'table-warning' : '' ?>">
                        <td class="fw-bold"><?= htmlspecialchars($item['name']) ?></td>
                        <td><span class="badge bg-secondary"><?= htmlspecialchars($item['category_name']) ?></span></td>
                        <td><?= number_format($item['price'], 2) ?></td>
                        <td class="text-center">
                            <span class="badge rounded-pill bg-<?= $item['quantity'] > 10 ? 'success' : ($item['quantity'] > 0 ? 'warning text-dark' : 'danger') ?> fs-6">
                                <?= $item['quantity'] ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-success" 
                                        onclick="openUpdateModal(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name']) ?>')">
                                    <i class="bi bi-plus-slash-minus"></i> Adjust
                                </button>
                                
                                <button class="btn btn-sm btn-outline-info" 
                                        onclick="viewHistory(<?= $item['id'] ?>, '<?= htmlspecialchars($item['name']) ?>')">
                                    <i class="bi bi-clock-history"></i> History
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="updateStockModal" tabindex="-1">
    <form method="POST" class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Adjust Stock: <span id="modalProdName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="update_stock" value="1">
                <input type="hidden" name="product_id" id="modalProdId">
                
                <div class="mb-3">
                    <label class="form-label">Quantity Change (+ to add, - to remove)</label>
                    <input type="number" step="0.01" name="quantity_change" class="form-control" required placeholder="e.g. 10 or -5">
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Reason</label>
                    <select name="reason" class="form-select">
                        <option value="restock">Restock / GRV</option>
                        <option value="damage">Damaged / Expired</option>
                        <option value="correction">Inventory Count Correction</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Save Adjustment</button>
            </div>
        </div>
    </form>
</div>

<div class="modal fade" id="historyModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="bi bi-clock-history"></i> Stock Card: <span id="historyProdName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-striped mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Action</th>
                            <th>Change</th>
                            <th>Balance</th>
                            <th>User</th>
                        </tr>
                    </thead>
                    <tbody id="historyTableBody">
                        </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
    function openUpdateModal(id, name) {
        document.getElementById('modalProdId').value = id;
        document.getElementById('modalProdName').innerText = name;
        new bootstrap.Modal(document.getElementById('updateStockModal')).show();
    }

    function viewHistory(id, name) {
        document.getElementById('historyProdName').innerText = name;
        const tbody = document.getElementById('historyTableBody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3">Loading history...</td></tr>';
        
        new bootstrap.Modal(document.getElementById('historyModal')).show();

        fetch(`index.php?page=inventory&ajax_history=1&product_id=${id}`)
            .then(res => res.json())
            .then(data => {
                tbody.innerHTML = '';
                if (data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center py-3">No movements recorded yet.</td></tr>';
                    return;
                }
                
                data.forEach(row => {
                    let color = row.change_qty > 0 ? 'text-success' : 'text-danger';
                    let sign = row.change_qty > 0 ? '+' : '';
                    
                    let html = `
                        <tr>
                            <td>${new Date(row.created_at).toLocaleString()}</td>
                            <td><span class="badge bg-secondary text-uppercase">${row.action_type.replace('_', ' ')}</span></td>
                            <td class="fw-bold ${color}">${sign}${parseFloat(row.change_qty)}</td>
                            <td class="fw-bold">${parseFloat(row.after_qty)}</td>
                            <td><small>${row.username}</small></td>
                        </tr>
                    `;
                    tbody.innerHTML += html;
                });
            })
            .catch(err => {
                tbody.innerHTML = '<tr><td colspan="5" class="text-danger text-center">Error loading data.</td></tr>';
            });
    }
</script>
