<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3>Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></h3>
        <p class="text-muted">Location: <?= htmlspecialchars($userData['location_name'] ?? 'Unknown') ?></p>
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

<?php if(!empty($lowStockItems)): ?>
<div class="alert alert-danger shadow-sm border-start border-5 border-danger alert-dismissible fade show">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <h5 class="alert-heading fw-bold"><i class="bi bi-exclamation-triangle-fill"></i> CRITICAL STOCK ALERT</h5>
    <ul class="mb-0 small">
        <?php foreach($lowStockItems as $item): ?>
            <li><strong><?= htmlspecialchars($item['name']) ?></strong> is low (Qty: <?= $item['quantity'] ?>). <em>Auto-request sent.</em></li>
        <?php endforeach; ?>
    </ul>
    <hr>
    <a href="index.php?page=inventory" class="btn btn-sm btn-danger">Manage Inventory</a>
</div>
<?php endif; ?>

<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm border-warning h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase text-muted mb-2">Unpaid Invoices (Tabs)</h6>
                    <h2 class="mb-0 fw-bold text-dark">ZMW <?= number_format($pendingTabs['total'], 2) ?></h2>
                    <span class="badge bg-warning text-dark mt-2"><?= $pendingTabs['count'] ?> Open Orders</span>
                </div>
                <div class="text-end">
                    <i class="bi bi-receipt-cutoff display-4 text-warning opacity-50"></i><br>
                    <a href="index.php?page=pos" class="btn btn-sm btn-outline-dark mt-2 stretched-link">View Tabs <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm border-info h-100">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase text-muted mb-2">Pending Requisitions</h6>
                    <h2 class="mb-0 fw-bold text-dark"><?= $pendingReqs ?></h2>
                    <span class="badge bg-info text-dark mt-2">Active Transfers</span>
                </div>
                <div class="text-end">
                    <i class="bi bi-truck display-4 text-info opacity-50"></i><br>
                    <a href="index.php?page=transfers" class="btn btn-sm btn-outline-dark mt-2 stretched-link">Manage Stock <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<audio id="notificationSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>

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
    <?php if (in_array($_SESSION['role'], ['admin', 'manager', 'dev'])): ?>
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
let lastPickupCount = 0;

function checkPickupCount() {
    fetch('index.php?page=pos&ajax_ready_count=1')
        .then(response => response.text())
        .then(countStr => {
            const currentCount = parseInt(countStr) || 0;
            let badge = document.getElementById('dashPickupBadge');
            
            // If count increased, play sound
            if (currentCount > lastPickupCount) {
                document.getElementById('notificationSound').play().catch(e => console.log("Audio play blocked until user interaction."));
            }
            
            lastPickupCount = currentCount;

            if (currentCount > 0) {
                badge.innerText = currentCount;
                badge.style.display = 'inline-block';
            } else {
                badge.style.display = 'none';
            }
        })
        .catch(err => console.error('Badge Error:', err));
}

setInterval(checkPickupCount, 5000);
checkPickupCount();
</script>
