<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3>👋 Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></h3>
        <p class="text-muted">Location: <?= htmlspecialchars($user['location_name'] ?? 'Unknown') ?></p>
    </div>
    <div>
        <a href="index.php?page=pickup" target="_blank" class="btn btn-outline-warning position-relative me-2">
            <i class="bi bi-bell-fill"></i> Pickup Screen
            <span id="dashPickupBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">
                0
            </span>
        </a>
        
        <a href="index.php?page=pos" class="btn btn-primary btn-lg shadow-sm">
            <i class="bi bi-cart4"></i> Open POS
        </a>
    </div>
</div>

<div class="row mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-primary h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small">My Shift Sales</h6>
                <h3 class="fw-bold text-primary">ZMW <?= number_format($myStats['shift_total'] ?? 0, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-success h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small">Transactions</h6>
                <h3 class="fw-bold text-success"><?= number_format($myStats['txn_count'] ?? 0) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-warning h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small">Top Seller</h6>
                <h5 class="fw-bold text-dark text-truncate"><?= htmlspecialchars($myStats['top_item'] ?? '-') ?></h5>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-info h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase small">Current Shift</h6>
                <h5 class="fw-bold text-info"><?= ucfirst($myStats['shift_status'] ?? 'None') ?></h5>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <a href="index.php?page=kds" target="_blank" class="text-decoration-none">
            <div class="card shadow-sm h-100 hover-shadow">
                <div class="card-body text-center py-4">
                    <i class="bi bi-display display-4 text-warning"></i>
                    <h5 class="mt-3 text-dark">Kitchen Display (KDS)</h5>
                    <p class="text-muted small">View incoming food orders</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="index.php?page=inventory" class="text-decoration-none">
            <div class="card shadow-sm h-100 hover-shadow">
                <div class="card-body text-center py-4">
                    <i class="bi bi-boxes display-4 text-success"></i>
                    <h5 class="mt-3 text-dark">Inventory</h5>
                    <p class="text-muted small">Check stock levels</p>
                </div>
            </div>
        </a>
    </div>
    <?php if (in_array($_SESSION['role'], ['admin', 'manager'])): ?>
    <div class="col-md-4">
        <a href="index.php?page=reports" class="text-decoration-none">
            <div class="card shadow-sm h-100 hover-shadow">
                <div class="card-body text-center py-4">
                    <i class="bi bi-graph-up-arrow display-4 text-primary"></i>
                    <h5 class="mt-3 text-dark">Reports</h5>
                    <p class="text-muted small">View sales performance</p>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>

<style>
.hover-shadow:hover { transform: translateY(-3px); transition: 0.2s; box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
</style>

<script>
function checkPickupCount() {
    // We use the POS page endpoint because it already handles the logic
    fetch('index.php?page=pos&ajax_ready_count=1')
        .then(response => response.text())
        .then(count => {
            let badge = document.getElementById('dashPickupBadge');
            if (parseInt(count) > 0) {
                badge.innerText = count;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(err => console.error('Badge Error:', err));
}
setInterval(checkPickupCount, 5000); // Check every 5 seconds
checkPickupCount(); // Run immediately
</script>
