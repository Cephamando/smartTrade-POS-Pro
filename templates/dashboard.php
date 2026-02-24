<?php 
if (!isset($todaySales)) { include_once 'src/dashboard.php'; } 
$tier = defined('LICENSE_TIER') ? LICENSE_TIER : 'lite';
$topColSize = in_array($tier, ['pro', 'hospitality']) ? 'col-md-3' : 'col-md-4';
$midColSize = ($tier === 'hospitality') ? 'col-md-4' : 'col-md-6';

global $pdo;
$activeShiftsStmt = $pdo->query("
    SELECT s.id, s.start_time, s.starting_cash, u.full_name as cashier_name, u.role, l.name as location_name,
           (SELECT SUM(final_total) FROM sales WHERE shift_id = s.id AND payment_status = 'paid' AND payment_method LIKE '%Cash%') as current_cash_sales,
           (SELECT SUM(amount) FROM expenses WHERE user_id = s.user_id AND created_at >= s.start_time) as expenses
    FROM shifts s
    JOIN users u ON s.user_id = u.id
    JOIN locations l ON s.location_id = l.id
    WHERE s.status = 'open'
    ORDER BY s.start_time DESC
");
$activeShifts = $activeShiftsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
    .hover-card-zoom { transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer; }
    .hover-card-zoom:hover { transform: scale(1.03); box-shadow: 0 10px 20px rgba(25,135,84,0.15) !important; z-index: 10; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="fw-bold mb-0"><i class="bi bi-speedometer2"></i> Dashboard</h3>
        <span class="text-muted small">Overview for <strong class="text-dark"><?= htmlspecialchars($dashLocName ?? 'All') ?></strong></span>
    </div>
    <div class="d-flex gap-2">
        <?php if (in_array($tier, ['pro', 'hospitality'])): ?>
        <button class="btn btn-warning fw-bold position-relative" onclick="showPickupModal()">
            <i class="bi bi-tv"></i> Pickup Screen
            <span id="readyBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">0</span>
        </button>
        <?php endif; ?>
        <a href="index.php?page=pos" class="btn btn-primary fw-bold"><i class="bi bi-cart4"></i> Go to POS</a>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="<?= $topColSize ?> col-6">
        <a href="index.php?page=reports" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.75rem;">Today's Sales</div>
                    <div class="h4 fw-bold text-success mb-0">ZMW <?= number_format($todaySales ?? 0, 2) ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="<?= $topColSize ?> col-6">
        <a href="index.php?page=reports" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.75rem;">Transactions</div>
                    <div class="h4 fw-bold text-primary mb-0"><?= number_format($todayTransactions ?? 0) ?></div>
                </div>
            </div>
        </a>
    </div>
    
    <?php if (in_array($tier, ['pro', 'hospitality'])): ?>
    <div class="<?= $topColSize ?> col-6">
        <a href="index.php?page=pos" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-body p-3 position-relative">
                    <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.75rem;">Unpaid Tabs</div>
                    <div class="h4 fw-bold text-warning mb-0"><?= number_format($unpaidTabs ?? 0) ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>

    <div class="<?= $topColSize ?> col-6">
        <a href="index.php?page=inventory" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.75rem;">Low Stock</div>
                    <div class="h4 fw-bold text-danger mb-0"><?= number_format($lowStockCount ?? 0) ?></div>
                </div>
            </div>
        </a>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="<?= $midColSize ?>">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white fw-bold py-2"><i class="bi bi-box-seam"></i> Inventory</div>
            <div class="list-group list-group-flush small fw-bold">
                <?php if (in_array($tier, ['pro', 'hospitality'])): ?>
                <a href="index.php?page=receive_stock" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Receive Stock <i class="bi bi-chevron-right text-muted"></i></a>
                <?php endif; ?>
                <a href="index.php?page=inventory" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Manage Products <i class="bi bi-chevron-right text-muted"></i></a>
                <?php if (in_array($tier, ['pro', 'hospitality'])): ?>
                <a href="index.php?page=audit" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Stock Audit <i class="bi bi-chevron-right text-muted"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if ($tier === 'hospitality'): ?>
    <div class="<?= $midColSize ?>">
        <div class="card shadow-sm h-100 border-warning">
            <div class="card-header bg-warning text-dark fw-bold py-2"><i class="bi bi-fire"></i> Kitchen & Menu</div>
            <div class="list-group list-group-flush small fw-bold">
                <a href="index.php?page=kds" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Kitchen Display (KDS) <i class="bi bi-chevron-right text-muted"></i></a>
                <a href="index.php?page=kitchen" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Meal Production <i class="bi bi-chevron-right text-muted"></i></a>
                <a href="index.php?page=menu" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Menu Builder <i class="bi bi-chevron-right text-muted"></i></a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="<?= $midColSize ?>">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white fw-bold py-2"><i class="bi bi-people"></i> Staff & Users</div>
            <div class="list-group list-group-flush small fw-bold">
                <a href="index.php?page=users" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Manage Users <i class="bi bi-chevron-right text-muted"></i></a>
                <a href="index.php?page=Shifts" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">View Shifts <i class="bi bi-chevron-right text-muted"></i></a>
                <?php if (in_array($tier, ['pro', 'hospitality'])): ?>
                <a href="index.php?page=members" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Members <i class="bi bi-chevron-right text-muted"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-2">
                <h6 class="card-title text-muted small fw-bold mb-2">7-Day Sales Trend</h6>
                <div style="height: 180px;">
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body p-2">
                <h6 class="card-title text-muted small fw-bold mb-2">Payment Methods (Today)</h6>
                <div style="height: 180px;">
                    <canvas id="paymentChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-dark text-white fw-bold py-3 d-flex justify-content-between align-items-center">
                <span><i class="bi bi-person-badge"></i> Active Employee Shifts</span>
                <span class="badge bg-success rounded-pill"><?= count($activeShifts) ?> Online</span>
            </div>
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4 small text-muted text-uppercase">Employee</th>
                            <th class="small text-muted text-uppercase">Role</th>
                            <th class="small text-muted text-uppercase">Workstation</th>
                            <th class="small text-muted text-uppercase">Time Online</th>
                            <th class="text-end pe-4 small text-muted text-uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($activeShifts)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted fst-italic">No active shifts currently open.</td></tr>
                        <?php else: foreach($activeShifts as $as): 
                            $start = new DateTime($as['start_time']);
                            $now = new DateTime();
                            $diff = $start->diff($now);
                            $duration = $diff->format('%h hr %i min');
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary">
                                <i class="bi bi-circle-fill text-success small me-2 border border-2 border-white rounded-circle shadow-sm"></i>
                                <?= htmlspecialchars($as['cashier_name']) ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars(strtoupper($as['role'])) ?></span></td>
                            <td><?= htmlspecialchars($as['location_name']) ?></td>
                            <td><span class="text-dark fw-bold"><i class="bi bi-clock-history text-muted"></i> <?= $duration ?></span></td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary fw-bold px-3 shadow-sm" onclick='showShiftDetails(<?= json_encode($as) ?>)'>View Status</button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="shiftDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-activity"></i> Real-Time Shift Status</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="d-flex align-items-center mb-4 pb-3 border-bottom border-secondary border-opacity-25">
                    <div class="bg-white p-3 rounded-circle shadow-sm me-3 border">
                        <i class="bi bi-person-bounding-box fs-2 text-primary"></i>
                    </div>
                    <div>
                        <h4 class="mb-1 fw-bold text-dark" id="sdName">Name</h4>
                        <span class="badge bg-dark" id="sdRole">Role</span> 
                        <span class="text-muted small ms-2 fw-bold" id="sdLoc"><i class="bi bi-geo-alt-fill text-warning"></i> Location</span>
                    </div>
                </div>
                
                <div class="row g-3">
                    <div class="col-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-3 text-center">
                                <small class="text-muted text-uppercase fw-bold" style="font-size:0.7rem;">Shift Started</small>
                                <div class="fw-bold text-dark mt-1" id="sdStart">--:--</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="card border-0 shadow-sm h-100">
                            <div class="card-body p-3 text-center">
                                <small class="text-muted text-uppercase fw-bold" style="font-size:0.7rem;">Opening Float</small>
                                <div class="fw-bold text-primary mt-1" id="sdFloat">ZMW 0.00</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6">
                        <div class="card border-0 shadow-sm h-100 border-bottom border-success border-4 hover-card-zoom" onclick="loadShiftSales()">
                            <div class="card-body p-3 text-center bg-white rounded">
                                <small class="text-success text-uppercase fw-bold" style="font-size:0.7rem;">Total Sales <i class="bi bi-box-arrow-up-right ms-1"></i></small>
                                <div class="fw-bold text-success fs-5 mt-1" id="sdSales">ZMW 0.00</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-6">
                        <div class="card border-0 shadow-sm h-100 border-bottom border-warning border-4">
                            <div class="card-body p-3 text-center bg-warning bg-opacity-10">
                                <small class="text-dark text-uppercase fw-bold" style="font-size:0.7rem;">Expected In Drawer</small>
                                <div class="fw-bold text-dark fs-5 mt-1" id="sdExpected">ZMW 0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 bg-white">
                <button type="button" class="btn btn-secondary w-100 fw-bold py-2 shadow-sm" data-bs-dismiss="modal">Close Status</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="shiftSalesModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="bi bi-receipt"></i> Shift Sales Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th class="ps-3 small text-muted text-uppercase">Time</th>
                            <th class="small text-muted text-uppercase">Order</th>
                            <th class="text-end pe-3 small text-muted text-uppercase">Amount</th>
                        </tr>
                    </thead>
                    <tbody id="shiftSalesTableBody">
                    </tbody>
                </table>
            </div>
            <div class="modal-footer border-0 bg-light">
                <button type="button" class="btn btn-outline-secondary w-100 fw-bold" onclick="backToShiftStatus()">Back to Status Overview</button>
            </div>
        </div>
    </div>
</div>

<?php if (in_array($tier, ['pro', 'hospitality'])): ?>
<div class="modal fade" id="pickupModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content h-100">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-bag-check-fill"></i> Orders Ready for Pickup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height: 80vh;">
                <iframe id="pickupFrame" src="" style="width:100%; height:100%; border:none;"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function showPickupModal() {
    document.getElementById('pickupFrame').src = "index.php?page=pickup&embedded=1";
    new bootstrap.Modal(document.getElementById('pickupModal')).show();
}

function checkDashboardReadyOrders() {
    fetch('index.php?action=check_ready_orders')
    .then(r => r.json())
    .then(data => {
        const badge = document.getElementById('readyBadge');
        if(badge && data && data.count > 0) { 
            badge.innerText = data.count; 
            badge.style.display = 'block'; 
        } else if (badge) { 
            badge.style.display = 'none'; 
        }
    }).catch(e => console.error('Dashboard Badge Error:', e));
}
checkDashboardReadyOrders();
setInterval(checkDashboardReadyOrders, 5000);
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    new Chart(document.getElementById('salesChart').getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= json_encode($dates) ?>,
            datasets: [{ label: 'Revenue (ZMW)', data: <?= json_encode($salesData) ?>, borderColor: '#198754', backgroundColor: 'rgba(25, 135, 84, 0.1)', fill: true, tension: 0.3 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    const payData = <?= json_encode($pmData) ?>;
    new Chart(document.getElementById('paymentChart').getContext('2d'), {
        type: 'doughnut',
        data: {
            labels: Object.keys(payData),
            datasets: [{ data: Object.values(payData), backgroundColor: ['#0d6efd', '#ffc107', '#dc3545', '#6c757d'] }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right' } } }
    });
});

let currentShiftData = null;

function showShiftDetails(shift) {
    currentShiftData = shift;
    
    document.getElementById('sdName').innerText = shift.cashier_name;
    document.getElementById('sdRole').innerText = shift.role.toUpperCase();
    document.getElementById('sdLoc').innerText = shift.location_name;
    
    let d = new Date(shift.start_time);
    document.getElementById('sdStart').innerText = d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    
    let startCash = parseFloat(shift.starting_cash) || 0;
    let sales = parseFloat(shift.current_cash_sales) || 0;
    let expenses = parseFloat(shift.expenses) || 0;
    let expected = (startCash + sales) - expenses;
    
    document.getElementById('sdFloat').innerText = 'ZMW ' + startCash.toFixed(2);
    document.getElementById('sdSales').innerText = 'ZMW ' + sales.toFixed(2);
    document.getElementById('sdExpected').innerText = 'ZMW ' + expected.toFixed(2);
    
    new bootstrap.Modal(document.getElementById('shiftDetailsModal')).show();
}

function loadShiftSales() {
    if(!currentShiftData) return;
    
    let mainModal = bootstrap.Modal.getInstance(document.getElementById('shiftDetailsModal'));
    if(mainModal) mainModal.hide();
    
    let tbody = document.getElementById('shiftSalesTableBody');
    tbody.innerHTML = '<tr><td colspan="3" class="text-center py-5"><div class="spinner-border text-success" role="status"></div></td></tr>';
    
    new bootstrap.Modal(document.getElementById('shiftSalesModal')).show();
    
    fetch('index.php?action=get_shift_sales&shift_id=' + currentShiftData.id)
        .then(r => {
            if (!r.ok) throw new Error('Network response was not ok');
            return r.json();
        })
        .then(data => {
            if (data.error) {
                console.error('Server Error:', data.error);
                tbody.innerHTML = `<tr><td colspan="3" class="text-center text-danger py-4">Database Error: ${data.error}</td></tr>`;
                return;
            }
            
            tbody.innerHTML = '';
            if(data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted fst-italic">No paid transactions recorded yet.</td></tr>';
            } else {
                data.forEach(sale => {
                    let d = new Date(sale.created_at);
                    let time = d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                    let pmColor = sale.payment_method.includes('Cash') ? 'text-success' : 'text-primary';
                    
                    tbody.innerHTML += `
                    <tr>
                        <td class="ps-3"><small class="fw-bold text-muted">${time}</small></td>
                        <td>
                            <span class="badge bg-light text-dark border">#${sale.id}</span>
                            <div class="small text-muted text-truncate" style="max-width:100px;">${sale.customer_name}</div>
                        </td>
                        <td class="text-end pe-3">
                            <div class="fw-bold text-dark">ZMW ${parseFloat(sale.final_total).toFixed(2)}</div>
                            <div style="font-size: 0.65rem;" class="fw-bold text-uppercase ${pmColor}">${sale.payment_method}</div>
                        </td>
                    </tr>`;
                });
            }
        })
        .catch(err => {
            console.error('Fetch Error:', err);
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-danger py-4">Failed to load data. See console.</td></tr>';
        });
}

function backToShiftStatus() {
    let salesModal = bootstrap.Modal.getInstance(document.getElementById('shiftSalesModal'));
    if(salesModal) salesModal.hide();
    
    setTimeout(() => {
        new bootstrap.Modal(document.getElementById('shiftDetailsModal')).show();
    }, 400);
}
</script>
