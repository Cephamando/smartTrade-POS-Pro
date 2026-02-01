<div class="d-flex flex-column gap-4">
    
    <div class="d-flex justify-content-between align-items-end border-bottom pb-3" style="border-color: #e0e0e0;">
        <div>
            <h6 class="text-muted text-uppercase small mb-1">Command Center</h6>
            <h2 class="text-brown fw-bold m-0">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></h2>
            <div class="d-flex align-items-center mt-2 text-secondary">
                <i class="bi bi-geo-alt-fill text-warning me-2"></i>
                <span class="fw-bold text-dark"><?= htmlspecialchars($userData['location_name'] ?? 'Unknown Location') ?></span>
                <span class="mx-2 opacity-25">|</span>
                <span id="liveClock" class="font-monospace">--:--</span>
            </div>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?page=pickup" target="_blank" class="btn btn-outline-dark d-flex align-items-center position-relative">
                <i class="bi bi-bell-fill me-2 text-warning"></i> Pickup Screen
                <span id="dashPickupBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow-sm" style="display:none;">0</span>
            </a>
            <a href="index.php?page=pos" class="btn btn-theme-orange text-white shadow fw-bold px-4 d-flex align-items-center">
                <i class="bi bi-cart4 me-2"></i> OPEN POS
            </a>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100 position-relative overflow-hidden">
                <div class="card-body">
                    <h6 class="text-uppercase text-muted small fw-bold">My Sales</h6>
                    <h3 class="fw-bold text-brown mb-0">ZMW <?= number_format($myStats['shift_total'] ?? 0, 2) ?></h3>
                    <i class="bi bi-cash-stack position-absolute bottom-0 end-0 p-3 display-4 text-warning opacity-25"></i>
                </div>
                <div class="progress" style="height: 4px;"><div class="progress-bar bg-warning" style="width: 75%"></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100 position-relative overflow-hidden">
                <div class="card-body">
                    <h6 class="text-uppercase text-muted small fw-bold">Transactions</h6>
                    <h3 class="fw-bold text-dark mb-0"><?= number_format($myStats['txn_count'] ?? 0) ?></h3>
                    <i class="bi bi-receipt position-absolute bottom-0 end-0 p-3 display-4 text-secondary opacity-25"></i>
                </div>
                <div class="progress" style="height: 4px;"><div class="progress-bar bg-secondary" style="width: 50%"></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100 position-relative overflow-hidden">
                <div class="card-body">
                    <h6 class="text-uppercase text-muted small fw-bold">Top Seller</h6>
                    <h4 class="fw-bold text-brown text-truncate mb-0" title="<?= htmlspecialchars($myStats['top_item'] ?? '-') ?>">
                        <?= htmlspecialchars($myStats['top_item'] ?? '-') ?>
                    </h4>
                    <i class="bi bi-star-fill position-absolute bottom-0 end-0 p-3 display-4 text-warning opacity-25"></i>
                </div>
                <div class="progress" style="height: 4px;"><div class="progress-bar bg-danger" style="width: 100%"></div></div>
            </div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card shadow-sm border-0 h-100 bg-dark text-white position-relative overflow-hidden">
                <div class="card-body">
                    <h6 class="text-uppercase text-white-50 small fw-bold">Shift Status</h6>
                    <h3 class="fw-bold text-warning mb-0"><?= ucfirst($myStats['shift_status'] ?? 'Active') ?></h3>
                    <i class="bi bi-clock-history position-absolute bottom-0 end-0 p-3 display-4 text-white opacity-25"></i>
                </div>
                <div class="progress" style="height: 4px;"><div class="progress-bar bg-success" style="width: 100%"></div></div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <?php if(!empty($lowStockItems)): ?>
        <div class="col-12">
            <div class="alert alert-danger shadow-sm border-0 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center">
                    <i class="bi bi-exclamation-octagon-fill display-6 me-3"></i>
                    <div>
                        <h5 class="alert-heading fw-bold m-0">Critical Stock Warning</h5>
                        <small><?= count($lowStockItems) ?> items are below minimum levels. Auto-requisitions have been generated.</small>
                    </div>
                </div>
                <a href="index.php?page=inventory" class="btn btn-light text-danger fw-bold">Review Stock</a>
            </div>
        </div>
        <?php endif; ?>

        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-bold text-uppercase text-muted"><i class="bi bi-wallet2 text-warning me-2"></i> Unpaid Tabs</h6>
                </div>
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="fw-bold text-dark mb-0">ZMW <?= number_format($pendingTabs['total'], 2) ?></h2>
                        <span class="badge bg-warning text-dark mt-2"><?= $pendingTabs['count'] ?> Open Orders</span>
                    </div>
                    <a href="index.php?page=pos&view_tabs=1" class="btn btn-outline-dark rounded-pill px-4">View & Pay <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <h6 class="fw-bold text-uppercase text-muted"><i class="bi bi-box-seam text-primary me-2"></i> Stock Transfers</h6>
                </div>
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <h2 class="fw-bold text-dark mb-0"><?= $pendingReqs ?></h2>
                        <span class="badge bg-primary mt-2">Pending Movement</span>
                    </div>
                    <a href="index.php?page=transfers" class="btn btn-outline-dark rounded-pill px-4">Manage <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <?php if (!empty($activeStaff)): ?>
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold m-0"><i class="bi bi-people-fill text-brown me-2"></i> Active Staff Monitor</h6>
                    <span class="badge bg-success bg-opacity-10 text-success border border-success"><?= count($activeStaff) ?> Online</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light"><tr><th class="ps-3">Staff</th><th>Location</th><th>Clocked In</th><th class="text-end pe-3">Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($activeStaff as $staff): $hours = round((time() - strtotime($staff['start_time']))/3600, 1); ?>
                            <tr>
                                <td class="ps-3 fw-bold text-dark">
                                    <div class="d-flex align-items-center">
                                        <div class="rounded-circle bg-success me-2" style="width:8px; height:8px;"></div>
                                        <?= htmlspecialchars($staff['username']) ?>
                                    </div>
                                </td>
                                <td class="text-muted small"><?= htmlspecialchars($staff['location_name']) ?></td>
                                <td class="font-monospace text-muted small"><?= date('H:i', strtotime($staff['start_time'])) ?> (<?= $hours ?>h)</td>
                                <td class="text-end pe-3">
                                    <button class="btn btn-sm btn-link text-decoration-none text-warning fw-bold" onclick="viewShiftDetails(<?= $staff['shift_id'] ?>)">VIEW LIVE</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php else: ?>
                <div class="card shadow-sm border-0 h-100 d-flex align-items-center justify-content-center p-5 text-muted">
                    <i class="bi bi-people display-4 opacity-25"></i>
                    <p class="mt-2">No active staff shifts found.</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="d-grid gap-3 h-100">
                <a href="index.php?page=kds" target="_blank" class="card shadow-sm border-0 text-decoration-none hover-card h-100 bg-dark text-white">
                    <div class="card-body d-flex align-items-center">
                        <div class="bg-warning bg-opacity-25 rounded p-3 me-3"><i class="bi bi-display text-warning h4 m-0"></i></div>
                        <div>
                            <h6 class="fw-bold mb-0">Kitchen Display</h6>
                            <small class="text-white-50">Manage incoming orders</small>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-white-50"></i>
                    </div>
                </a>
                <a href="index.php?page=inventory" class="card shadow-sm border-0 text-decoration-none hover-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="bg-success bg-opacity-10 rounded p-3 me-3"><i class="bi bi-boxes text-success h4 m-0"></i></div>
                        <div class="text-dark">
                            <h6 class="fw-bold mb-0">Inventory</h6>
                            <small class="text-muted">Stock & Adjustments</small>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                    </div>
                </a>
                <?php if (in_array($_SESSION['role'], ['admin', 'manager', 'dev'])): ?>
                <a href="index.php?page=reports" class="card shadow-sm border-0 text-decoration-none hover-card h-100">
                    <div class="card-body d-flex align-items-center">
                        <div class="bg-primary bg-opacity-10 rounded p-3 me-3"><i class="bi bi-graph-up text-primary h4 m-0"></i></div>
                        <div class="text-dark">
                            <h6 class="fw-bold mb-0">Reports</h6>
                            <small class="text-muted">Sales & Audits</small>
                        </div>
                        <i class="bi bi-chevron-right ms-auto text-muted"></i>
                    </div>
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="drillDownModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><iframe id="drillDownFrame" src="" style="width:100%; height:85vh; border:none;"></iframe></div></div></div>
<audio id="notificationSound" src="https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3" preload="auto"></audio>

<style>
    .hover-card { transition: transform 0.2s, box-shadow 0.2s; }
    .hover-card:hover { transform: translateY(-3px); box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; }
    .btn-theme-orange { background-color: #fd7e14; border: none; }
    .btn-theme-orange:hover { background-color: #e66b0d; }
</style>

<script>
    function viewShiftDetails(shiftId) {
        document.getElementById('drillDownFrame').src = 'index.php?page=pos&action=close_shift_report&shift_id=' + shiftId;
        new bootstrap.Modal(document.getElementById('drillDownModal')).show();
    }
    
    // Live Clock
    setInterval(() => {
        const now = new Date();
        document.getElementById('liveClock').innerText = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
    }, 1000);

    // Live Badge
    let lastPickupCount = 0;
    function checkPickupCount() {
        fetch('index.php?page=pos&ajax_ready_count=1').then(r=>r.text()).then(c=>{
            let count = parseInt(c)||0;
            let badge = document.getElementById('dashPickupBadge');
            if(count>lastPickupCount) document.getElementById('notificationSound').play().catch(()=>{});
            lastPickupCount=count;
            badge.innerText=count; badge.style.display=count>0?'inline-block':'none';
        });
    }
    setInterval(checkPickupCount, 5000); checkPickupCount();
</script>
