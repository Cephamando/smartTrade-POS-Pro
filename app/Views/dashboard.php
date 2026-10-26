<?php
$tier = defined('LICENSE_TIER') ? LICENSE_TIER : 'lite';
$topColSize = in_array($tier, ['pro', 'pro+', 'enterprise']) ? 'col-md-3' : 'col-md-4';
$midColSize = in_array($tier, ['pro+', 'enterprise']) ? 'col-md-4' : 'col-md-6';
$activeShifts = $activeShifts ?? [];
$explainReady = !empty($explainReady);
$dates = $dates ?? [];
$salesData =$salesData ?? [];
$pmData =$pmData ?? [];

// The header defines $currentLocationCanSell. Fall back to true if undefined.
$canSell =$currentLocationCanSell ?? true;

$vsiasPendingUrl = $vsiasPendingUrl ?? \App\Helpers\Vsias::pendingApprovalsUrl();
?>
<script src="vendor/chartjs/chart.umd.min.js"></script>

<style>
    .hover-card-zoom { transition: transform 0.2s ease, box-shadow 0.2s ease; cursor: pointer; }
    .hover-card-zoom:hover { transform: scale(1.03); box-shadow: 0 10px 20px rgba(25,135,84,0.15) !important; z-index: 10; }
    
    /* Horizontal Scrolling for Financial Cards on Mobile */
    .dashboard-stats-wrapper {
        display: flex;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        padding-bottom: 0.5rem;
        margin-bottom: 1rem;
        scrollbar-width: thin;
    }
    .dashboard-stats-wrapper::-webkit-scrollbar { height: 6px; }
    .dashboard-stats-wrapper::-webkit-scrollbar-thumb { background-color: #cbd5e1; border-radius: 10px; }
    .stat-card-col {
        flex: 0 0 auto;
        width: 75%;
        max-width: 300px;
        margin-right: 0.75rem;
    }
    @media (min-width: 768px) {
        .dashboard-stats-wrapper {
            flex-wrap: wrap;
            overflow-x: visible;
            padding-bottom: 0;
            margin-right: -0.5rem;
            margin-left: -0.5rem;
        }
        .stat-card-col {
            flex: 1 0 0%;
            width: auto;
            max-width: none;
            padding-right: 0.5rem;
            padding-left: 0.5rem;
            margin-right: 0;
        }
    }
</style>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
    <div>
        <h3 class="fw-bold mb-0"><i class="bi bi-speedometer2"></i> <?= (($_SESSION['role'] ?? '') === 'owner') ? 'Business overview' : 'Dashboard' ?></h3>
        <span class="text-muted small">Overview for <strong class="text-dark"><?= htmlspecialchars($dashLocName ?? 'All') ?></strong></span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?>
        <button class="btn btn-warning fw-bold position-relative flex-grow-1 flex-md-grow-0" onclick="showPickupModal()">
            <i class="bi bi-tv"></i> Pickup Screen
            <span id="readyBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" data-live-count="pickup" style="display:none;">0</span>
        </button>
        <?php endif; ?>
        <?php if (!empty($canSeeVsiasApprovals) && \App\Helpers\Vsias::enabled()): ?>
        <a href="#" class="btn btn-info fw-bold position-relative text-white flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#vsiasInspectModal" data-vsias-url="<?= htmlspecialchars($vsiasPendingUrl) ?>" data-title="Pending V-SIAS approvals" title="Pending V-SIAS store approvals">
            <i class="bi bi-clipboard-check"></i> Approvals
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" data-live-count="approvals" style="<?= !empty($vsiasPending) ? '' : 'display:none' ?>"><?= (int)($vsiasPending ?? 0) ?></span>
        </a>
        <?php endif; ?>

        <?php if (($_SESSION['role'] ?? '') !== 'owner'): ?>
            <?php if ($canSell): ?>
            <a href="index.php?page=pos" class="btn btn-primary fw-bold flex-grow-1 flex-md-grow-0"><i class="bi bi-cart4"></i> Go to POS</a>
            <?php else: ?>
            <button type="button" class="btn btn-secondary fw-bold flex-grow-1 flex-md-grow-0" disabled title="POS disabled for this location"><i class="bi bi-cart-x"></i> POS Disabled</button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php $dashTab = ($dashTab ?? '') === 'settlements' ? 'settlements' : 'overview'; ?>
<?php $isOwnerDash = (($_SESSION['role'] ?? '') === 'owner') && is_array($ownerOverview ?? null); ?>
<?php if (!$isOwnerDash): ?>
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link<?= $dashTab === 'overview' ? ' active fw-bold' : '' ?>" href="index.php?page=dashboard">Overview</a>
    </li>
    <li class="nav-item">
        <a class="nav-link<?= $dashTab === 'settlements' ? ' active fw-bold' : '' ?>" href="index.php?page=dashboard&tab=settlements"><i class="bi bi-bank"></i> Settlements &amp; Payouts</a>
    </li>
</ul>
<?php endif; ?>

<?php if ($dashTab === 'settlements'): ?>
<?php include __DIR__ . '/partials/settlements.php'; ?>
<?php else: ?>
<div class="dashboard-stats-wrapper">
    <div class="stat-card-col">
        <a href="index.php?page=reports" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.75rem;">Today's Sales</div>
                    <div class="h4 fw-bold text-success mb-0">ZMW <?= number_format($todaySales ?? 0, 2) ?></div>
                </div>
            </div>
        </a>
    </div>
    <div class="stat-card-col">
        <a href="index.php?page=reports" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.75rem;">Transactions</div>
                    <div class="h4 fw-bold text-primary mb-0"><?= number_format($todayTransactions ?? 0) ?></div>
                </div>
            </div>
        </a>
    </div>

    <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?>
    <div class="stat-card-col">
        <?php if ($canSell): ?>
        <a href="index.php?page=pos" class="text-decoration-none">
        <?php else: ?>
        <div class="text-decoration-none" style="cursor:not-allowed;">
        <?php endif; ?>
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
                <div class="card-body p-3 position-relative">
                    <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.75rem;">Unpaid Tabs</div>
                    <div class="h4 fw-bold text-warning mb-0"><?= number_format($unpaidTabs ?? 0) ?></div>
                </div>
            </div>
        <?php if ($canSell): ?>
        </a>
        <?php else: ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!$isOwnerDash): ?>
    <div class="stat-card-col">
        <a href="index.php?page=inventory" class="text-decoration-none">
            <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
                <div class="card-body p-3">
                    <div class="text-muted small text-uppercase fw-bold" style="font-size: 0.75rem;">Low Stock</div>
                    <div class="h4 fw-bold text-danger mb-0"><?= number_format($lowStockCount ?? 0) ?></div>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($isOwnerDash): ?>
<?php include __DIR__ . '/partials/owner_overview.php'; ?>
<?php endif; ?>

<div class="row g-3 mb-3">
    <?php if (!$isOwnerDash): ?>
    <div class="<?= $midColSize ?>">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white fw-bold py-2"><i class="bi bi-box-seam"></i> Inventory</div>
            <div class="list-group list-group-flush small fw-bold">
                <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?>
                <a href="index.php?page=receiving_bay" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Receiving Bay <i class="bi bi-chevron-right text-muted"></i></a>
                <a href="index.php?page=purchasing&tab=received" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Goods Received <i class="bi bi-chevron-right text-muted"></i></a>
                <?php endif; ?>
                <a href="index.php?page=inventory" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Manage Products <i class="bi bi-chevron-right text-muted"></i></a>
                <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?>
                <a href="index.php?page=audit" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Stock Audit <i class="bi bi-chevron-right text-muted"></i></a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (in_array($tier, ['pro+', 'enterprise'])): ?>
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
    <?php endif; ?>

    <div class="<?= $isOwnerDash ? 'col-md-4' : $midColSize ?>">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-dark text-white fw-bold py-2"><i class="bi bi-people"></i> Staff & Users</div>
            <div class="list-group list-group-flush small fw-bold">
                <a href="index.php?page=users" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">Manage Users <i class="bi bi-chevron-right text-muted"></i></a>
                <a href="index.php?page=shifts" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">View Shifts <i class="bi bi-chevron-right text-muted"></i></a>
                <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?>
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
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <h6 class="card-title text-muted small fw-bold mb-0">Sales trend</h6>
                    <span class="d-flex gap-1">
                        <button type="button" class="btn btn-sm btn-outline-dark py-0 js-trend-explain" <?= empty($explainReady) ? 'disabled' : '' ?> title="<?= empty($explainReady) ? 'Save a Gemini key under Settings, Guide' : 'Explain this chart' ?>"><i class="bi bi-stars"></i> Explain</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary py-0" id="trendExpand">Expand</button>
                    </span>
                </div>
                <div class="d-flex flex-wrap gap-1 align-items-center mb-2">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Sales period">
                        <?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year', 'custom' => 'Custom'] as $key => $label): ?>
                        <button type="button" class="btn <?= $key === 'week' ? 'btn-success' : 'btn-outline-secondary' ?> js-trend-range" data-range="<?= $key ?>"><?= $label ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Chart style">
                        <button type="button" class="btn btn-success js-trend-style" data-style="line">Line</button>
                        <button type="button" class="btn btn-outline-secondary js-trend-style" data-style="bar">Bar</button>
                        <button type="button" class="btn btn-outline-secondary js-trend-style" data-style="both">Both</button>
                    </div>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside">Items</button>
                        <div class="dropdown-menu p-2 small js-trend-items" style="max-height: 240px; overflow: auto; min-width: 220px;"></div>
                    </div>
                </div>
                <div class="js-trend-custom d-none flex-wrap gap-1 mb-2">
                    <input type="date" class="form-control form-control-sm js-trend-start" style="width: auto;" aria-label="From">
                    <input type="date" class="form-control form-control-sm js-trend-end" style="width: auto;" aria-label="To">
                </div>
                <div id="trendPlot" style="height: 180px; cursor: pointer;" title="Open a larger chart">
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
                            <th class="small text-muted text-uppercase d-none d-sm-table-cell">Workstation</th>
                            <th class="small text-muted text-uppercase">Time Online</th>
                            <th class="text-end pe-4 small text-muted text-uppercase">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($activeShifts)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted fst-italic">No active shifts currently open.</td></tr>
                        <?php else: foreach($activeShifts as$as):
                            $start = new DateTime($as['start_time']);
                            $now = new DateTime();$diff = $start->diff($now);
                            $duration =$diff->format('%h hr %i min');
                        ?>
                        <tr>
                            <td class="ps-4 fw-bold text-primary">
                                <i class="bi bi-circle-fill text-success small me-2 border border-2 border-white rounded-circle shadow-sm"></i>
                                <?= htmlspecialchars($as['cashier_name']) ?>
                            </td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars(strtoupper($as['role'])) ?></span></td>
                            <td class="d-none d-sm-table-cell"><?= htmlspecialchars($as['location_name']) ?></td>
                            <td><span class="text-dark fw-bold"><i class="bi bi-clock-history text-muted"></i> <?= $duration ?></span></td>
                            <td class="text-end pe-4">
                                <button class="btn btn-sm btn-outline-primary fw-bold px-3 shadow-sm" onclick='showShiftDetails(<?= json_encode($as) ?>)'>View</button>
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
                                <small class="text-dark text-uppercase fw-bold" style="font-size:0.7rem;">Expected Cash Drawer</small>
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

<div class="modal fade" id="shiftSaleBreakdownModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg border-info border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-receipt text-info me-2"></i> Sale Breakdown <span id="shiftModalSaleIdBadge" class="badge bg-secondary ms-2"></span></h5>
                <button type="button" class="btn-close" onclick="backToShiftSales()"></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-4">Item</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end pe-4">Price</th>
                        </tr>
                    </thead>
                    <tbody id="shiftSaleBreakdownBody">
                    </tbody>
                </table>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-outline-secondary w-100 fw-bold" onclick="backToShiftSales()">Back to Sales List</button>
            </div>
        </div>
    </div>
</div>

<?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?>
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

</script>
<?php endif; ?>

<div class="modal fade" id="trendExplainModal" tabindex="-1" style="z-index: 1080;">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Explain these figures</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="small" style="white-space: pre-wrap;" id="trendExplainText"></div>
                <p class="small text-muted mb-0 mt-3">Written from the totals on this chart. This is not a forecast.</p>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="trendModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header">
                <h5 class="modal-title fw-bold me-auto">Sales trend</h5>
                <button type="button" class="btn btn-sm btn-outline-dark js-trend-explain" <?= empty($explainReady) ? 'disabled' : '' ?> title="<?= empty($explainReady) ? 'Save a Gemini key under Settings, Guide' : 'Explain this chart' ?>"><i class="bi bi-stars"></i> Explain</button>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex flex-wrap gap-1 align-items-center mb-2">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Sales period">
                        <?php foreach (['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'year' => 'Year', 'custom' => 'Custom'] as $key => $label): ?>
                        <button type="button" class="btn <?= $key === 'week' ? 'btn-success' : 'btn-outline-secondary' ?> js-trend-range" data-range="<?= $key ?>"><?= $label ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="btn-group btn-group-sm" role="group" aria-label="Chart style">
                        <button type="button" class="btn btn-success js-trend-style" data-style="line">Line</button>
                        <button type="button" class="btn btn-outline-secondary js-trend-style" data-style="bar">Bar</button>
                        <button type="button" class="btn btn-outline-secondary js-trend-style" data-style="both">Both</button>
                    </div>
                    <div class="dropdown">
                        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" data-bs-auto-close="outside">Items</button>
                        <div class="dropdown-menu p-2 small js-trend-items" style="max-height: 280px; overflow: auto; min-width: 240px;"></div>
                    </div>
                </div>
                <div class="js-trend-custom d-none flex-wrap gap-1 mb-2">
                    <input type="date" class="form-control form-control-sm js-trend-start" style="width: auto;" aria-label="From">
                    <input type="date" class="form-control form-control-sm js-trend-end" style="width: auto;" aria-label="To">
                </div>
                <div style="height: min(62vh, 560px);">
                    <canvas id="salesChartLarge"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const trendColors = ['#0d6efd', '#fd7e14', '#6f42c1', '#d63384', '#20c997', '#dc3545', '#0dcaf0', '#6c757d'];
    const trendState = {
        range: 'week',
        style: 'line',
        start: '',
        end: '',
        items: [],
        payload: {
            labels: <?= json_encode($dates) ?>,
            total: <?= json_encode(array_map('floatval', $salesData)) ?>,
            series: [],
            choices: []
        },
        card: null,
        large: null
    };

    function trendDatasets(payload, style) {
        const hasItems = (payload.series || []).length > 0;
        const datasets = [{
            type: style === 'line' ? 'line' : 'bar',
            label: 'Total',
            data: payload.total || [],
            borderColor: '#198754',
            backgroundColor: style === 'line' ? 'rgba(25, 135, 84, 0.12)' : 'rgba(25, 135, 84, 0.8)',
            fill: style === 'line',
            tension: 0.3,
            borderWidth: 2,
            order: 2
        }];
        (payload.series || []).forEach(function (series, index) {
            const color = trendColors[index % trendColors.length];
            const asLine = style !== 'bar';
            datasets.push({
                type: asLine ? 'line' : 'bar',
                label: series.name,
                data: series.data || [],
                borderColor: color,
                backgroundColor: asLine ? 'transparent' : color,
                fill: false,
                tension: 0.3,
                borderWidth: 2,
                pointRadius: (payload.labels || []).length > 24 ? 0 : 3,
                order: 1
            });
        });
        return { datasets: datasets, legend: hasItems };
    }

    function drawTrend(canvas, compact) {
        if (!canvas || typeof Chart === 'undefined') return null;
        const built = trendDatasets(trendState.payload, trendState.style);
        const existing = Chart.getChart(canvas);
        if (existing) existing.destroy();
        return new Chart(canvas.getContext('2d'), {
            type: trendState.style === 'line' ? 'line' : 'bar',
            data: { labels: trendState.payload.labels || [], datasets: built.datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: built.legend, position: compact ? 'bottom' : 'top' },
                    tooltip: {
                        callbacks: {
                            label: function (item) {
                                const value = Number(item.parsed.y || 0);
                                return item.dataset.label + ': K' + value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            }
                        }
                    }
                },
                scales: {
                    y: { beginAtZero: true },
                    x: { ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: compact ? 8 : 16 } }
                }
            }
        });
    }

    function paintTrends() {
        trendState.card = drawTrend(document.getElementById('salesChart'), true);
        const large = document.getElementById('salesChartLarge');
        if (large && document.getElementById('trendModal').classList.contains('show')) {
            trendState.large = drawTrend(large, false);
        }
    }

    function renderItemMenus() {
        document.querySelectorAll('.js-trend-items').forEach(function (menu) {
            menu.innerHTML = '';
            const choices = trendState.payload.choices || [];
            if (!choices.length) {
                menu.innerHTML = '<div class="text-muted px-1">No items in this period.</div>';
                return;
            }
            const note = document.createElement('div');
            note.className = 'text-muted px-1 mb-1';
            note.textContent = 'Up to 8 items. Each one is drawn with the total.';
            menu.appendChild(note);
            choices.forEach(function (choice) {
                const row = document.createElement('label');
                row.className = 'd-flex align-items-center gap-2 py-1 px-1';
                const box = document.createElement('input');
                box.type = 'checkbox';
                box.className = 'form-check-input m-0';
                box.value = String(choice.id);
                box.checked = trendState.items.indexOf(String(choice.id)) !== -1;
                box.addEventListener('change', function () {
                    const id = String(choice.id);
                    if (box.checked) {
                        if (trendState.items.length >= 8) {
                            box.checked = false;
                            return;
                        }
                        trendState.items.push(id);
                    } else {
                        trendState.items = trendState.items.filter(function (kept) { return kept !== id; });
                    }
                    document.querySelectorAll('.js-trend-items input[value="' + id + '"]').forEach(function (other) {
                        other.checked = box.checked;
                    });
                    loadTrend();
                });
                const text = document.createElement('span');
                text.textContent = choice.name;
                row.appendChild(box);
                row.appendChild(text);
                menu.appendChild(row);
            });
        });
    }

    function syncTrendButtons() {
        document.querySelectorAll('.js-trend-range').forEach(function (button) {
            const on = button.dataset.range === trendState.range;
            button.classList.toggle('btn-success', on);
            button.classList.toggle('btn-outline-secondary', !on);
        });
        document.querySelectorAll('.js-trend-style').forEach(function (button) {
            const on = button.dataset.style === trendState.style;
            button.classList.toggle('btn-success', on);
            button.classList.toggle('btn-outline-secondary', !on);
        });
        document.querySelectorAll('.js-trend-custom').forEach(function (box) {
            box.classList.toggle('d-none', trendState.range !== 'custom');
            box.classList.toggle('d-flex', trendState.range === 'custom');
        });
    }

    function loadTrend() {
        const body = new URLSearchParams();
        body.set('sales_trend', '1');
        body.set('range', trendState.range);
        body.set('start', trendState.start);
        body.set('end', trendState.end);
        trendState.items.forEach(function (id) { body.append('items[]', id); });
        fetch('index.php?page=dashboard', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (!data || data.status !== 'success') return;
            trendState.payload = data;
            if (data.from) {
                trendState.start = data.from;
                document.querySelectorAll('.js-trend-start').forEach(function (input) { input.value = data.from; });
            }
            if (data.to) {
                trendState.end = data.to;
                document.querySelectorAll('.js-trend-end').forEach(function (input) { input.value = data.to; });
            }
            renderItemMenus();
            paintTrends();
        })
        .catch(function () {});
    }

    function openTrend() {
        const modalEl = document.getElementById('trendModal');
        const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        modal.show();
    }
    document.getElementById('trendModal').addEventListener('shown.bs.modal', function () {
        trendState.large = drawTrend(document.getElementById('salesChartLarge'), false);
    });

    document.querySelectorAll('.js-trend-range').forEach(function (button) {
        button.addEventListener('click', function () {
            trendState.range = button.dataset.range;
            syncTrendButtons();
            if (trendState.range !== 'custom' || (trendState.start && trendState.end)) loadTrend();
        });
    });
    document.querySelectorAll('.js-trend-style').forEach(function (button) {
        button.addEventListener('click', function () {
            trendState.style = button.dataset.style;
            syncTrendButtons();
            paintTrends();
            if (document.getElementById('trendModal').classList.contains('show')) {
                trendState.large = drawTrend(document.getElementById('salesChartLarge'), false);
            }
        });
    });
    document.querySelectorAll('.js-trend-start, .js-trend-end').forEach(function (input) {
        input.addEventListener('change', function () {
            const start = input.classList.contains('js-trend-start');
            document.querySelectorAll(start ? '.js-trend-start' : '.js-trend-end').forEach(function (other) {
                other.value = input.value;
            });
            trendState.start = (document.querySelector('.js-trend-start') || {}).value || '';
            trendState.end = (document.querySelector('.js-trend-end') || {}).value || '';
            trendState.range = 'custom';
            syncTrendButtons();
            if (trendState.start && trendState.end) loadTrend();
        });
    });
    function explainTrend(button) {
        if (!button || button.disabled) return;
        const text = document.getElementById('trendExplainText');
        const modalEl = document.getElementById('trendExplainModal');
        if (!text || !modalEl || !window.bootstrap) return;
        const label = button.innerHTML;
        document.querySelectorAll('.js-trend-explain').forEach(function (el) { el.disabled = true; });
        text.classList.remove('text-danger');
        text.textContent = 'Writing a note from the points on this chart…';
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
        const body = new URLSearchParams();
        body.set('explain_trend', '1');
        body.set('range', trendState.range);
        body.set('start', trendState.start);
        body.set('end', trendState.end);
        trendState.items.forEach(function (id) { body.append('items[]', id); });
        fetch('index.php?page=dashboard', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString()
        })
        .then(function (response) { return response.json(); })
        .then(function (data) {
            if (data && data.status === 'success') {
                text.textContent = data.text || '';
            } else {
                text.classList.add('text-danger');
                text.textContent = (data && data.msg) ? data.msg : 'The note could not be written.';
            }
        })
        .catch(function () {
            text.classList.add('text-danger');
            text.textContent = 'The note could not be written. Try again in a moment.';
        })
        .finally(function () {
            document.querySelectorAll('.js-trend-explain').forEach(function (el) {
                el.disabled = <?= empty($explainReady) ? 'true' : 'false' ?>;
                if (el === button) el.innerHTML = label;
            });
        });
    }
    document.querySelectorAll('.js-trend-explain').forEach(function (button) {
        button.addEventListener('click', function () { explainTrend(button); });
    });

    const plot = document.getElementById('trendPlot');
    if (plot) plot.addEventListener('click', openTrend);
    const expand = document.getElementById('trendExpand');
    if (expand) expand.addEventListener('click', openTrend);
    paintTrends();
    loadTrend();

    const payCanvas = document.getElementById('paymentChart');
    if (!payCanvas) return;
    const payData = <?= json_encode($pmData) ?>;

    new Chart(payCanvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: Object.keys(payData),
            datasets: [{
                label: 'Total (ZMW)',
                data: Object.values(payData),
                backgroundColor: ['#0d6efd', '#ffc107', '#dc3545', '#6c757d', '#198754'],
                borderRadius: 4
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                x: { beginAtZero: true }
            }
        }
    });
});

let currentShiftData = null;

function showShiftDetails(shift) {
    currentShiftData = shift;

    document.getElementById('sdName').innerText = shift.cashier_name;
    document.getElementById('sdRole').innerText = shift.role.toUpperCase();
    document.getElementById('sdLoc').innerText = shift.location_name;

    let d = new Date(shift.start_time);
    document.getElementById('sdStart').innerText = d.toLocaleDateString([], {month: 'short', day: '2-digit', year: 'numeric'}) + ' - ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

    let startCash = parseFloat(shift.starting_cash) || 0;
    let totalSales = parseFloat(shift.total_sales) || 0;
    let cashSales = parseFloat(shift.current_cash_sales) || 0;
    let expenses = parseFloat(shift.expenses) || 0;

    let expected = (startCash + cashSales) - expenses;

    document.getElementById('sdFloat').innerText = 'ZMW ' + startCash.toFixed(2);
    document.getElementById('sdSales').innerText = 'ZMW ' + totalSales.toFixed(2);
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
                            <button type="button" onclick="viewShiftSaleBreakdown(${sale.id})" class="btn btn-link p-0 fw-bold text-decoration-underline text-primary shadow-none" style="font-size: 1rem;">ZMW ${parseFloat(sale.final_total).toFixed(2)}</button>
                            <div style="font-size: 0.65rem;" class="fw-bold text-uppercase ${pmColor} mt-1">${sale.payment_method}</div>
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

function viewShiftSaleBreakdown(saleId) {
    let salesModal = bootstrap.Modal.getInstance(document.getElementById('shiftSalesModal'));
    if(salesModal) salesModal.hide();

    document.getElementById('shiftModalSaleIdBadge').innerText = '#' + saleId;
    document.getElementById('shiftSaleBreakdownBody').innerHTML = '<tr><td colspan="3" class="text-center py-4 text-muted"><div class="spinner-border text-info spinner-border-sm me-2"></div> Fetching data...</td></tr>';

    setTimeout(() => {
        new bootstrap.Modal(document.getElementById('shiftSaleBreakdownModal')).show();
    }, 400);

    fetch('index.php?page=reports', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'fetch_sale_details=1&sale_id=' + saleId
    })
    .then(r => r.json())
    .then(data => {
        let tbody = document.getElementById('shiftSaleBreakdownBody');
        if (data.status === 'success') {
            tbody.innerHTML = '';
            if (data.items.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted py-3">No items found for this sale.</td></tr>';
                return;
            }
            data.items.forEach(i => {
                let statusBadge = '';
                if (i.status === 'voided') statusBadge = ' <span class="badge bg-danger">VOID</span>';
                else if (i.status === 'refunded') statusBadge = ' <span class="badge bg-warning text-dark">REFUNDED</span>';

                let priceClass = parseFloat(i.price) < 0 ? 'text-danger' : 'text-dark';

                tbody.innerHTML += `
                    <tr>
                        <td class="ps-4 fw-bold text-secondary">${i.name} ${statusBadge}</td>
                        <td class="text-center fw-bold">${i.quantity}</td>
                        <td class="text-end pe-4 fw-bold ${priceClass}">ZMW ${parseFloat(i.price * i.quantity).toFixed(2)}</td>
                    </tr>
                `;
            });
        } else {
            tbody.innerHTML = `<tr><td colspan="3" class="text-danger text-center py-3">Error: ${data.msg}</td></tr>`;
        }
    }).catch(err => {
        document.getElementById('shiftSaleBreakdownBody').innerHTML = '<tr><td colspan="3" class="text-danger text-center py-3">Network error loading details.</td></tr>';
    });
}

function backToShiftSales() {
    let breakdownModal = bootstrap.Modal.getInstance(document.getElementById('shiftSaleBreakdownModal'));
    if(breakdownModal) breakdownModal.hide();

    setTimeout(() => {
        new bootstrap.Modal(document.getElementById('shiftSalesModal')).show();
    }, 400);
}
</script>
<?php endif; ?>
