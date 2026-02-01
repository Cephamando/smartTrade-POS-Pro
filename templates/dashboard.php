<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="text-brown fw-bold">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'Guest') ?></h3>
        <p class="text-muted">Location: <?= htmlspecialchars($userData['location_name'] ?? 'Unknown') ?></p>
    </div>
    <div>
        <a href="index.php?page=pickup" target="_blank" class="btn btn-outline-warning position-relative me-2 text-dark border-warning">
            <i class="bi bi-bell-fill"></i> Pickup
            <span id="dashPickupBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">0</span>
        </a>
        <a href="index.php?page=pos" class="btn btn-warning shadow-sm fw-bold">
            <i class="bi bi-cart4"></i> Open POS
        </a>
    </div>
</div>

<?php if(!empty($lowStockItems)): ?>
<div class="alert alert-warning shadow-sm border-start border-5 border-warning alert-dismissible fade show mb-4">
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    <h5 class="alert-heading fw-bold text-brown"><i class="bi bi-exclamation-triangle-fill"></i> Stock Alert</h5>
    <ul class="mb-0 small text-brown">
        <?php foreach($lowStockItems as $item): ?>
            <li><strong><?= htmlspecialchars($item['name']) ?></strong> is low (Qty: <?= $item['quantity'] ?>).</li>
        <?php endforeach; ?>
    </ul>
    <hr>
    <a href="index.php?page=inventory" class="btn btn-sm btn-outline-brown text-brown border-brown">Manage Inventory</a>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-md-6">
        <div class="card shadow-sm h-100 border-start border-4" style="border-color: var(--theme-orange);">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase text-muted mb-2">Unpaid Tabs</h6>
                    <h2 class="mb-0 fw-bold text-orange">ZMW <?= number_format($pendingTabs['total'], 2) ?></h2>
                    <span class="badge bg-warning text-dark mt-2"><?= $pendingTabs['count'] ?> Open</span>
                </div>
                <div class="text-end">
                    <i class="bi bi-receipt-cutoff display-4 text-orange opacity-25"></i><br>
                    <a href="index.php?page=pos&view_tabs=1" class="btn btn-sm btn-outline-dark mt-2 stretched-link">View Details</a>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm h-100 border-start border-4" style="border-color: var(--theme-brown);">
            <div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <h6 class="text-uppercase text-muted mb-2">Requisitions</h6>
                    <h2 class="mb-0 fw-bold text-brown"><?= $pendingReqs ?></h2>
                    <span class="badge bg-secondary mt-2">Active</span>
                </div>
                <div class="text-end">
                    <i class="bi bi-truck display-4 text-brown opacity-25"></i><br>
                    <a href="index.php?page=transfers" class="btn btn-sm btn-outline-dark mt-2 stretched-link">Manage</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($activeStaff)): ?>
<div class="card shadow-sm mb-4 border-0">
    <div class="card-header text-white fw-bold d-flex justify-content-between align-items-center" style="background-color: var(--theme-brown);">
        <span><i class="bi bi-people-fill text-gold"></i> Active Staff</span>
        <span class="badge bg-warning text-brown"><?= count($activeStaff) ?> Online</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
                <thead class="table-light"><tr><th>Staff Name</th><th>Location</th><th>Time</th><th></th></tr></thead>
                <tbody>
                    <?php foreach ($activeStaff as $staff): $hours = round((time() - strtotime($staff['start_time']))/3600, 1); ?>
                    <tr>
                        <td class="fw-bold"><i class="bi bi-circle-fill text-success small me-1"></i> <?= htmlspecialchars($staff['username']) ?></td>
                        <td><?= htmlspecialchars($staff['location_name']) ?></td>
                        <td><?= date('H:i', strtotime($staff['start_time'])) ?> <small class="text-muted">(<?= $hours ?> hrs)</small></td>
                        <td class="text-end"><button class="btn btn-sm btn-outline-brown" onclick="viewShiftDetails(<?= $staff['shift_id'] ?>)">Details</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-top border-4" style="border-color: var(--theme-gold);">
            <div class="card-body">
                <h6 class="text-muted small text-uppercase">My Sales</h6>
                <h3 class="fw-bold text-gold">ZMW <?= number_format($myStats['shift_total'] ?? 0, 2) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-top border-4" style="border-color: var(--theme-orange);">
            <div class="card-body">
                <h6 class="text-muted small text-uppercase">Txns</h6>
                <h3 class="fw-bold text-orange"><?= number_format($myStats['txn_count'] ?? 0) ?></h3>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-top border-4" style="border-color: var(--theme-brown);">
            <div class="card-body">
                <h6 class="text-muted small text-uppercase">Top Item</h6>
                <h5 class="fw-bold text-brown text-truncate"><?= htmlspecialchars($myStats['top_item'] ?? '-') ?></h5>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card shadow-sm border-top border-4 bg-light">
            <div class="card-body">
                <h6 class="text-muted small text-uppercase">Status</h6>
                <h5 class="fw-bold text-success">Active</h5>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <a href="index.php?page=kds" target="_blank" class="text-decoration-none">
            <div class="card shadow-sm h-100 hover-shadow bg-dark text-white">
                <div class="card-body text-center py-4">
                    <i class="bi bi-display display-4 text-warning"></i>
                    <h5 class="mt-3 text-white">KDS</h5>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="index.php?page=inventory" class="text-decoration-none">
            <div class="card shadow-sm h-100 hover-shadow bg-warning text-dark">
                <div class="card-body text-center py-4">
                    <i class="bi bi-boxes display-4 text-brown"></i>
                    <h5 class="mt-3 text-brown">Inventory</h5>
                </div>
            </div>
        </a>
    </div>
    <?php if (in_array($_SESSION['role'], ['admin', 'manager', 'dev'])): ?>
    <div class="col-md-4">
        <a href="index.php?page=reports" class="text-decoration-none">
            <div class="card shadow-sm h-100 hover-shadow bg-brown text-white" style="background-color: var(--theme-brown);">
                <div class="card-body text-center py-4">
                    <i class="bi bi-graph-up-arrow display-4 text-gold"></i>
                    <h5 class="mt-3 text-white">Reports</h5>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="modal fade" id="drillDownModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><iframe id="drillDownFrame" src="" style="width:100%; height:85vh; border:none;"></iframe></div></div></div>
<audio id="notificationSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>

<script>
function viewShiftDetails(shiftId) {
    document.getElementById('drillDownFrame').src = 'index.php?page=pos&action=close_shift_report&shift_id=' + shiftId;
    new bootstrap.Modal(document.getElementById('drillDownModal')).show();
}
let lastPickupCount = 0;
function checkPickupCount() {
    fetch('index.php?page=pos&ajax_ready_count=1').then(r=>r.text()).then(c=>{
        let count = parseInt(c)||0;
        let badge = document.getElementById('dashPickupBadge');
        if(count>lastPickupCount) document.getElementById('notificationSound').play().catch(e=>{});
        lastPickupCount=count;
        badge.innerText=count; badge.style.display=count>0?'inline-block':'none';
    });
}
setInterval(checkPickupCount, 5000); checkPickupCount();
</script>
