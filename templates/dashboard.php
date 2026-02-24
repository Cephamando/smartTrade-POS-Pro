<?php if (!isset($todaySales)) { include_once 'src/dashboard.php'; } ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="fw-bold mb-0"><i class="bi bi-speedometer2"></i> Dashboard</h3>
        <span class="text-muted small">Overview for <strong class="text-dark"><?= htmlspecialchars($dashLocName ?? 'All') ?></strong></span>
    </div>
    <div class="d-flex gap-2">
        <button class="btn btn-warning fw-bold position-relative" onclick="showPickupModal()">
            <i class="bi bi-tv"></i> Pickup Screen
            <span id="readyBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">0</span>
        </button>
        <a href="index.php?page=pos" class="btn btn-primary fw-bold"><i class="bi bi-cart4"></i> Go to POS</a>
    </div>
</div>

<div class="row g-2 mb-3">
    <div class="col-md-3 col-6">
        <a href="index.php?page=reports" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.75rem;">Today's Sales</div>
                    <div class="h4 fw-bold text-success mb-0">ZMW <?= number_format($todaySales ?? 0, 2) ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="index.php?page=reports" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.75rem;">Transactions</div>
                    <div class="h4 fw-bold text-primary mb-0"><?= number_format($todayTransactions ?? 0) ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
        <a href="index.php?page=pos" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-body p-3 position-relative">
                    <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.75rem;">Unpaid Tabs</div>
                    <div class="h4 fw-bold text-warning mb-0"><?= number_format($unpaidTabs ?? 0) ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-3 col-6">
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
    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white fw-bold py-2"><i class="bi bi-box-seam"></i> Inventory</div>
            <div class="list-group list-group-flush small fw-bold">
                <a href="index.php?page=receive_stock" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Receive Stock <i class="bi bi-chevron-right text-muted"></i></a>
                <a href="index.php?page=inventory" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Manage Products <i class="bi bi-chevron-right text-muted"></i></a>
                <a href="index.php?page=audit" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Stock Audit <i class="bi bi-chevron-right text-muted"></i></a>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="card shadow-sm h-100 border-warning">
            <div class="card-header bg-warning text-dark fw-bold py-2"><i class="bi bi-fire"></i> Kitchen & Menu</div>
            <div class="list-group list-group-flush small fw-bold">
                <a href="index.php?page=kds" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Kitchen Display (KDS) <i class="bi bi-chevron-right text-muted"></i></a>
                <a href="index.php?page=kitchen" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Meal Production <i class="bi bi-chevron-right text-muted"></i></a>
                <a href="index.php?page=menu" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Menu Builder <i class="bi bi-chevron-right text-muted"></i></a>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white fw-bold py-2"><i class="bi bi-people"></i> Staff & Users</div>
            <div class="list-group list-group-flush small fw-bold">
                <a href="index.php?page=users" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Manage Users <i class="bi bi-chevron-right text-muted"></i></a>
                <a href="index.php?page=Shifts" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">View Shifts <i class="bi bi-chevron-right text-muted"></i></a>
                <a href="index.php?page=members" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Members <i class="bi bi-chevron-right text-muted"></i></a>
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

setInterval(function() {
    fetch('api/check_ready_orders.php').then(r => r.json()).then(data => {
        const badge = document.getElementById('readyBadge');
        if(data && data.count > 0) { badge.innerText = data.count; badge.style.display = 'block'; } 
        else { badge.style.display = 'none'; }
    }).catch(e => console.log('Pickup poller inactive'));
}, 5000);

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
</script>
