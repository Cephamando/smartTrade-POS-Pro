<!DOCTYPE html> 
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>POS - <?= htmlspecialchars($_SESSION['location_name'] ?? 'HQ') ?></title>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <link rel="stylesheet" href="css/pos-keyboard.css?v=5">
    <script src="js/app-alerts.js?v=20260905b"></script>
    <style>
        :root { --pos-main-color: <?= htmlspecialchars($sysSettings['theme_color'] ?? '#2c2c2c') ?>; --pos-accent-color: <?= htmlspecialchars($sysSettings['theme_accent'] ?? '#ffc107') ?>; --pos-cart-color: <?= htmlspecialchars($sysSettings['theme_cart'] ?? '#3e2723') ?>; }
        body { background-color: #f0f2f5; height: 100vh; overflow: hidden; display: flex; flex-direction: column; margin: 0; transition: background-color 0.3s; }
        .header-custom { background-color: var(--pos-main-color) !important; border-bottom: 4px solid var(--pos-accent-color) !important; color: white; flex: 0 0 auto; z-index: 1050; transition: background-color 0.3s, border-color 0.3s; }
        #headerPosLabel, #headerLocIcon { color: var(--pos-accent-color) !important; }
        .workspace { flex: 1 1 auto; display: flex; overflow: hidden; position: relative; min-height: 0; }
        .product-section { flex: 1 1 auto; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        .cat-card { background: #fff; border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px 10px; text-align: center; cursor: pointer; transition: 0.2s; font-weight: bold; color: #333; display: flex; flex-direction: column; align-items: center; justify-content: center; user-select: none; }
        .cat-card:hover { border-color: var(--pos-accent-color) !important; transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.1); }
        .cat-card.active { background: var(--pos-cart-color) !important; border-color: var(--pos-accent-color) !important; color: #fff; }
        .cat-icon { font-size: 2rem; margin-bottom: 8px; }
        .product-list-wrapper { flex: 1 1 auto; display: flex; flex-direction: column; overflow: hidden; }
        .product-list { flex: 1 1 auto; overflow-y: auto; padding: 15px; }
        .pagination-bar { flex: 0 0 auto; background: #fff; border-top: 1px solid #ddd; padding: 10px 15px; display: flex; justify-content: center; align-items: center; gap: 15px; }
        .item-card { background: white; border: 1px solid #e0e0e0; border-radius: 8px; transition: transform 0.1s, box-shadow 0.1s; cursor: pointer; overflow: hidden; position: relative; height: 100%; display: block; width: 100%; text-align: left; }
        .item-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-color: var(--pos-accent-color) !important; }
        .item-card:active { transform: scale(0.98); }
        .item-card:disabled, .item-card[disabled] { opacity: 0.4 !important; filter: grayscale(100%) !important; background-color: #e9ecef !important; cursor: not-allowed !important; pointer-events: none !important; box-shadow: none !important; border-color: #ddd !important; }
        .stock-badge { display: inline-block; font-size: 0.7rem; padding: 3px 6px; border-radius: 4px; font-weight: bold; }
        .bg-low { background-color: #dc3545; color: white; }
        .bg-ok { background-color: #198754; color: white; }
        .bg-recipe { background-color: #0dcaf0; color: #000; }
        .cart-panel { flex: 0 0 400px; width: 400px; background: #fff; border-left: 1px solid #ccc; display: flex; flex-direction: column; box-shadow: -4px 0 15px rgba(0,0,0,0.1); z-index: 1020; height: 100%; min-height: 0; }
        .cart-compact { display: none; }
        .cart-header { padding: 15px; background-color: var(--pos-cart-color) !important; color: white; flex: 0 0 auto; }
        .cart-items { flex: 1 1 auto; overflow-y: auto; padding: 15px; background: #f8f9fa; min-height: 0; }
        .cart-footer { padding: 20px; background: #fff; border-top: 2px solid #eee; flex: 0 0 auto; }
        .cart-item { background: white; border-radius: 6px; padding: 10px; margin-bottom: 10px; border: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .btn-fulfillment { font-size: 0.7rem; font-weight: bold; text-transform: uppercase; padding: 2px 6px; }
        .badge-uncollected { background: #fd7e14; color: white; cursor: pointer; }
        .badge-collected { background: #198754; color: white; cursor: default; }
        .btn-charge { background-color: #fd7e14 !important; border-color: #fd7e14 !important; color: #fff !important; font-size: 1.25rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 6px rgba(253, 126, 20, 0.3); }
        .btn-charge:hover { background-color: #e66b0d !important; }
        .btn-charge:disabled { background-color: #ccc !important; border-color: #ccc !important; box-shadow: none; cursor: not-allowed; }
        .table-box { border-radius: 12px; height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center; border: 2px solid; cursor: pointer; transition: 0.2s; position: relative; overflow: hidden; }
        .table-box:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
        .table-available { background: #e8f5e9; border-color: #4caf50; color: #2e7d32; }
        .table-occupied { background: #ffebee; border-color: #f44336; color: #c62828; }
        .table-capacity { position: absolute; top: 5px; right: 8px; font-size: 0.7rem; font-weight: bold; opacity: 0.7; }
        .tab-radio-label { cursor: pointer; transition: 0.2s; }
        .tab-radio-label:hover { background-color: #f8f9fa; }
        .tab-radio-label.active { background-color: var(--pos-accent-color) !important; color: #000 !important; border-color: var(--pos-accent-color) !important; font-weight: bold; }
        .pos-sticky-tools { flex: 0 0 auto; z-index: 20; }
        .pos-filter-bar { display: flex; align-items: stretch; gap: 8px; flex-wrap: wrap; flex: 0 0 auto; }
        .pos-search { flex: 1 1 220px; min-width: 0; height: 48px; }
        .pos-mode-toggles { display: flex; gap: 6px; flex: 0 0 auto; align-items: stretch; }
        .pos-mode-btn { display: inline-flex; align-items: center; justify-content: center; gap: 6px; margin: 0; height: 48px; padding: 0 14px; font-weight: 800; font-size: 0.8rem; text-transform: uppercase; letter-spacing: 0.3px; white-space: nowrap; }
        .pos-cat-grid { flex: 1 1 auto; overflow-y: auto; min-height: 0; }
        .cart-scrim { display: none; }
        @media (max-width: 991px) { .cart-panel { flex: 0 0 300px; width: 300px; } .pos-header-actions .btn { font-size: 0.8rem; } }
        @media (max-width: 768px) {
            .workspace { flex-direction: column; overflow: hidden; }
            .product-section {
                flex: 1 1 auto;
                min-height: 0;
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
                padding-bottom: 120px;
            }
            .pos-sticky-tools { position: sticky; top: 0; z-index: 20; }
            .pos-filter-bar { position: static; flex-direction: column; flex-wrap: nowrap; gap: 8px; }
            .pos-search { flex: 1 1 auto; width: 100%; font-size: 1rem; height: 44px; }
            .pos-mode-toggles { width: 100%; }
            .pos-mode-btn { flex: 1 1 0; height: 44px; padding: 0 10px; font-size: 0.75rem; }
            .pos-cat-grid { flex: 0 0 auto !important; overflow: visible !important; }
            .product-list-wrapper { flex: 0 0 auto !important; overflow: visible !important; min-height: auto; }
            .product-list { overflow: visible !important; flex: none !important; }
            .cat-card { padding: 16px 8px; }
            .cat-icon { font-size: 1.65rem; }
            .cart-scrim {
                display: none;
                position: absolute;
                inset: 0;
                background: rgba(17, 17, 17, 0.4);
                z-index: 1025;
            }
            .workspace.cart-open .cart-scrim { display: block; }
            .cart-panel {
                position: absolute;
                left: 0; right: 0; bottom: 0;
                width: 100%;
                flex: none;
                height: auto;
                max-height: none;
                min-height: 0;
                border-left: 0;
                border-top: 4px solid var(--pos-cart-color) !important;
                z-index: 1030;
                box-shadow: 0 -10px 28px rgba(0,0,0,0.2);
            }
            .cart-panel.expanded { max-height: min(68vh, 560px); }
            .cart-panel:not(.expanded) .cart-items { display: none; }
            .cart-panel:not(.expanded) .cart-footer .btn-group,
            .cart-panel:not(.expanded) .cart-footer .row,
            .cart-panel:not(.expanded) .cart-total-row { display: none; }
            .cart-panel:not(.expanded) .cart-footer { padding: 8px 12px; }
            .cart-panel:not(.expanded) .cart-footer .d-grid { margin-bottom: 0 !important; }
            .cart-panel:not(.expanded) .btn-charge { padding: 0.55rem !important; font-size: 1rem; }
            .cart-header { padding: 8px 12px; }
            .cart-compact { display: flex; align-items: center; justify-content: space-between; gap: 8px; width: 100%; }
            .cart-compact-title { display: none; }
            .header-custom { padding: 6px 8px !important; }
            #drilldown-header { padding: 8px 12px !important; }
            #drilldown-header h4 { font-size: 1rem; }
            #backBtn { padding: 0.35rem 1rem; }
        }
        #checkoutModal .modal-dialog { max-height: calc(100dvh - 0.75rem); margin: 0.4rem auto; }
        #checkoutModal .modal-content { max-height: calc(100dvh - 0.75rem); display: flex; flex-direction: column; }
        #checkoutModal .modal-body { overflow-y: auto; -webkit-overflow-scrolling: touch; min-height: 0; }
        #checkoutModal .modal-footer { flex: 0 0 auto; }
        @media (max-width: 576px) {
            #checkoutModal .modal-dialog { max-width: 100%; width: 100%; height: 100%; max-height: 100dvh; margin: 0; }
            #checkoutModal .modal-content { height: 100%; max-height: 100dvh; border-radius: 0; }
            #checkoutModal .display-4 { font-size: 2rem; }
        }
        .item-card.is-blocked { opacity: 0.55; filter: grayscale(40%); cursor: not-allowed; pointer-events: none; }
    </style>
</head>
<body>
    <?php if ($locationId == 0): ?><div class="modal fade show" id="compulsoryLocationModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.9); z-index: 1060; position: fixed; top: 0; left: 0; width: 100%; height: 100%;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-warning"><div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold">Select Workstation</h5></div><div class="modal-body bg-light p-4"><form method="POST"><?php foreach($sellableLocations as $loc): ?><button name="set_pos_location" value="<?= $loc['id'] ?>" class="btn btn-white border w-100 mb-2 py-3 fw-bold text-start shadow-sm"><?= htmlspecialchars($loc['name']) ?></button><?php endforeach; ?></form></div></div></div></div><?php endif; ?>
    <?php if ($locationId > 0 && $pendingShift): ?><div class="modal fade show" id="pendingShiftModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.8); z-index: 1060; position: fixed; top: 0; left: 0; width: 100%; height: 100%;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-warning"><div class="modal-header bg-warning text-dark"><h5 class="modal-title fw-bold">Awaiting Manager Approval</h5></div><div class="modal-body text-center p-4"><p>Shift #<?= $pendingShift['id'] ?> is pending approval.</p><form method="POST" class="js-confirm-form" data-swal-title="Approve and start this shift?" data-swal-text="The station will open for sales after manager authorization." data-swal-confirm="Approve" data-swal-cancel="Cancel" data-swal-icon="question" data-swal-confirm-color="#fd7e14"><input type="hidden" name="approve_shift_start" value="1"><input type="hidden" name="pending_shift_id" value="<?= $pendingShift['id'] ?>"><input type="text" name="mgr_username" class="form-control mb-2" placeholder="Manager Username" required><input type="password" name="mgr_password" class="form-control mb-3" placeholder="Manager Password" required><button type="submit" class="btn btn-warning w-100 fw-bold">APPROVE & START</button></form></div></div></div></div><?php endif; ?>
    <?php if ($locationId > 0 && !$activeShiftId && !$pendingShift): ?><div class="modal fade show" id="startShiftModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.8); z-index: 1060; position: fixed; top: 0; left: 0; width: 100%; height: 100%;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-primary"><div class="modal-header bg-primary text-white"><h5 class="modal-title fw-bold">Start New Shift</h5><button type="button" class="btn btn-sm btn-warning text-dark fw-bold ms-auto" data-pos-vk-toggle="1" title="On-screen keyboard"><i class="bi bi-keyboard-fill"></i> Keyboard</button></div><form method="POST" class="js-confirm-form" data-swal-title="Request shift approval?" data-swal-text="The opening float will be sent to a manager to start this station." data-swal-confirm="Request" data-swal-cancel="Cancel" data-swal-icon="question" data-swal-confirm-color="#0d6efd"><div class="modal-body p-4"><input type="hidden" name="request_start_shift" value="1"><label class="fw-bold small text-muted">OPENING FLOAT</label><div class="input-group input-group-lg mb-3"><span class="input-group-text fw-bold">ZMW</span><input type="number" step="0.01" name="starting_cash" class="form-control fw-bold" required placeholder="0.00" inputmode="decimal"></div><button type="submit" class="btn btn-primary w-100 fw-bold py-3 mb-2">REQUEST APPROVAL</button><a href="index.php?page=dashboard" class="btn btn-outline-secondary w-100 fw-bold">GO TO DASHBOARD</a></div></form></div></div></div><?php endif; ?>

    <div class="header-custom p-2 d-flex justify-content-between align-items-center" id="mainHeader">
        <div class="d-flex align-items-center ps-2 flex-wrap">
            <span class="fs-5 fw-bold text-warning me-3" id="headerPosLabel">POS</span>
            <span class="text-light ms-2"><i class="bi bi-geo-alt-fill text-warning" id="headerLocIcon"></i> <?= htmlspecialchars($locationName) ?></span>
            <button type="button" class="btn btn-sm btn-link text-warning ms-1 me-2" data-bs-toggle="modal" data-bs-target="#locationModal" title="Change Location"><i class="bi bi-pencil-square"></i></button>
            <span class="text-light border-start border-secondary ps-3 d-none d-md-inline"><i class="bi bi-person-circle text-info me-1"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
        </div>
        <div class="d-flex gap-2 pe-2 flex-wrap align-items-center pos-header-actions">
            <button type="button" class="btn btn-warning text-dark btn-sm fw-bold" id="posKeyboardToggle" aria-pressed="true" title="Hide on-screen keyboard"><i class="bi bi-keyboard-fill"></i> <span class="d-none d-sm-inline">Keyboard</span></button>

            <div class="d-none d-xl-flex gap-2 flex-wrap">
            <?php if($isManager && in_array($tier, ['pro+', 'enterprise'])): ?>
                <a href="index.php?page=menu" class="btn btn-outline-success btn-sm fw-bold"><i class="bi bi-list-ul"></i> Menu</a>
                <a href="index.php?page=kitchen" class="btn btn-outline-danger btn-sm fw-bold"><i class="bi bi-fire"></i> Produce</a>
            <?php endif; ?>
            <?php if ($activeShiftId): ?>
                <button type="button" class="btn btn-warning text-dark btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="bi bi-cash-stack"></i> Payout</button>
                <button type="button" onclick="showShiftReport(<?= $activeShiftId ?>)" class="btn btn-outline-light btn-sm fw-bold"><i class="bi bi-printer"></i> X-Read</button>
            <?php endif; ?>
            <?php if ($tier === 'enterprise'): ?>
                <button type="button" class="btn btn-outline-primary btn-sm fw-bold position-relative" data-bs-toggle="modal" data-bs-target="#onlineTabsModal">
                    <i class="bi bi-cloud-download"></i> Web Orders
                    <?php if (!empty($onlineTabs)): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= count($onlineTabs) ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>
            <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?>
                <button type="button" class="btn btn-outline-warning btn-sm fw-bold position-relative" onclick="showPickupModal()">
                    <i class="bi bi-bag-check"></i> Pickup <span id="posReadyBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none; font-size: 0.6rem; padding: 0.35em 0.5em;">0</span>
                </button>
            <?php endif; ?>
            <?php if (in_array($tier, ['pro+', 'enterprise'])): ?>
                <button type="button" class="btn btn-outline-info btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#floorplanModal"><i class="bi bi-grid-3x3-gap-fill"></i> Tables</button>
            <?php endif; ?>
            <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?>
                <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#tabsModal"><i class="bi bi-receipt"></i> Tabs</button>
            <?php endif; ?>
            </div>

            <div class="dropdown d-xl-none">
                <button class="btn btn-outline-light btn-sm fw-bold dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-list"></i> Menu
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow">
                    <?php if($isManager && in_array($tier, ['pro+', 'enterprise'])): ?>
                    <li><a class="dropdown-item" href="index.php?page=menu"><i class="bi bi-list-ul"></i> Menu</a></li>
                    <li><a class="dropdown-item" href="index.php?page=kitchen"><i class="bi bi-fire"></i> Produce</a></li>
                    <?php endif; ?>
                    <?php if ($activeShiftId): ?>
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="bi bi-cash-stack"></i> Payout</button></li>
                    <li><button type="button" class="dropdown-item" onclick="showShiftReport(<?= $activeShiftId ?>)"><i class="bi bi-printer"></i> X-Read</button></li>
                    <?php endif; ?>
                    <?php if ($tier === 'enterprise'): ?>
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#onlineTabsModal"><i class="bi bi-cloud-download"></i> Web Orders</button></li>
                    <?php endif; ?>
                    <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?>
                    <li><button type="button" class="dropdown-item" onclick="showPickupModal()"><i class="bi bi-bag-check"></i> Pickup</button></li>
                    <?php endif; ?>
                    <?php if (in_array($tier, ['pro+', 'enterprise'])): ?>
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#floorplanModal"><i class="bi bi-grid-3x3-gap-fill"></i> Tables</button></li>
                    <?php endif; ?>
                    <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?>
                    <li><button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#tabsModal"><i class="bi bi-receipt"></i> Tabs</button></li>
                    <?php endif; ?>
                </ul>
            </div>

            <?php if ($activeShiftId): ?>
                <button type="button" class="btn btn-danger btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#endShiftModal"><i class="bi bi-power"></i> End</button>
            <?php else: ?><span class="badge bg-secondary">LOCKED</span><?php endif; ?>
            
            <?php if($isManager): ?>
            <a href="index.php?page=dashboard" class="btn btn-outline-light btn-sm" title="Dashboard"><i class="bi bi-house"></i></a>
            <?php else: ?>
            <a href="index.php?action=logout" class="btn btn-outline-danger btn-sm fw-bold" title="Logout"><i class="bi bi-power"></i> Exit</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="workspace" style="<?= (!$activeShiftId) ? 'filter: blur(5px); pointer-events: none;' : '' ?>">
        <div class="product-section">
            <div class="pos-sticky-tools">
            <div class="pos-filter-bar bg-white p-2 border-bottom">
                <input type="text" id="search" class="form-control form-control-lg pos-search border-dark shadow-sm" placeholder="Search product..." onkeyup="filter()">
                <div class="pos-mode-toggles" role="group" aria-label="POS modes">
                    <input type="checkbox" class="btn-check" id="refundToggle" autocomplete="off" onchange="toggleRefundMode()">
                    <label class="btn btn-outline-danger pos-mode-btn" for="refundToggle" id="refundToggleContainer"><i class="bi bi-arrow-return-left"></i> Return</label>
                    <input type="checkbox" class="btn-check" id="inStockToggle" autocomplete="off" onchange="applyFilters()">
                    <label class="btn btn-outline-dark pos-mode-btn" for="inStockToggle"><i class="bi bi-box-seam"></i> In-stock</label>
                </div>
            </div>
            <div id="drilldown-header" class="bg-white p-3 border-bottom d-flex align-items-center justify-content-between shadow-sm" style="display:none; flex: 0 0 auto; position: relative; z-index: 10;">
                <button id="backBtn" class="btn btn-dark fw-bold px-4 rounded-pill shadow-sm"><i class="bi bi-arrow-left me-2"></i> BACK</button>
                <h4 class="m-0 fw-bold text-dark text-uppercase" id="currentCategoryLabel">All Items</h4>
            </div>
            </div>
            
            <?php
            $mainCats = [];
            $subCatsByParent = [];
            $catParents = [];
            $catNames = [];
            foreach($categories as $cat) {
                $catNames[$cat['id']] = $cat['name'];
                if (!empty($cat['parent_id'])) {
                    $subCatsByParent[$cat['parent_id']][] = $cat;
                    $catParents[$cat['id']] = $cat['parent_id'];
                } else {
                    $mainCats[] = $cat;
                }
            }
            ?>

            <div id="main-category-grid" class="p-3 bg-white pos-cat-grid">
                <div class="row g-3">
                    <div class="col-4 col-md-3 col-lg-2">
                        <div class="cat-card h-100 shadow-sm border-secondary" onclick="filterItems('all', 'All Items')">
                            <i class="bi bi-grid-fill cat-icon text-secondary"></i>
                            <span class="small text-uppercase">All Items</span>
                        </div>
                    </div>
                    <?php foreach($mainCats as $cat): ?>
                        <?php $hasSubs = isset($subCatsByParent[$cat['id']]); ?>
                        <div class="col-4 col-md-3 col-lg-2">
                            <div class="cat-card h-100 shadow-sm <?= $hasSubs ? 'border-info bg-info bg-opacity-10' : '' ?>" 
                                 onclick="<?= $hasSubs ? "showSubCategories('{$cat['id']}', '".htmlspecialchars(addslashes($cat['name']))."')" : "filterItems('{$cat['id']}', '".htmlspecialchars(addslashes($cat['name']))."')" ?>">
                                <i class="bi <?= $hasSubs ? 'bi-folder-fill text-info' : 'bi-tags text-warning' ?> cat-icon"></i>
                                <span class="small text-uppercase <?= $hasSubs ? 'fw-bold text-info' : '' ?>"><?= htmlspecialchars($cat['name']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div class="col-4 col-md-3 col-lg-2">
                        <div class="cat-card h-100 text-warning border-warning bg-dark shadow-sm" onclick="switchTab('services', 'Services')">
                            <i class="bi bi-stars cat-icon"></i>
                            <span class="small text-uppercase">SERVICES</span>
                        </div>
                    </div>
                </div>
            </div>

            <div id="sub-category-grid" class="p-3 bg-white pos-cat-grid" style="display:none;">
                <div class="row g-3" id="subCategoryContainer"></div>
            </div>

            <div class="product-list-wrapper" id="product-list-wrapper" style="display:none;">
                <div class="product-list">
                    <div id="items-grid" class="row g-2">
                        <?php foreach($products as $p):
                            $hasRecipe = ($p['is_recipe'] > 0);
                            $sellBlock = \App\Helpers\Movement::sellOnPos($p);
                            $notSellable = !$sellBlock['ok'];
                            $pendingZra = \App\Helpers\Movement::requiresZra() && !\App\Helpers\Movement::isZraReady($p);
                            $isRaw = \App\Helpers\Movement::isRawProduct($p);
                            $inCart = (float)(($cartQtyByProduct[(int)$p['id']] ?? 0));
                            $available = $hasRecipe ? (float)$p['stock_qty'] : max(0, (float)$p['stock_qty'] - $inCart);
                            $isOut = ($available <= 0 && !$hasRecipe && !$notSellable);
                            $blocked = $notSellable;
                        ?>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2 item position-relative" data-cat="<?= $p['category_id'] ?>" data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>" data-label="<?= htmlspecialchars($p['name']) ?>" data-out="<?= ($isOut || $blocked) ? '1' : '0' ?>" data-product-id="<?= (int)$p['id'] ?>" data-on-hand="<?= htmlspecialchars((string)(float)$p['stock_qty']) ?>" data-tracks-stock="<?= ($hasRecipe || $notSellable || $pendingZra || $isRaw) ? '0' : '1' ?>">
                            <form method="POST" class="h-100 add-item-form js-pos-cart">
                                <input type="hidden" name="add_item" value="1"><input type="hidden" name="product_id" value="<?= $p['id'] ?>"><input type="hidden" name="is_refund" class="refund-input" value="0">
                                <button type="submit" class="item-card d-flex flex-column text-start p-2 <?= $notSellable ? 'is-blocked' : '' ?>" <?= $blocked ? 'disabled="disabled"' : '' ?>>
                                    <div style="height: 38px; overflow: hidden;" class="fw-bold text-dark mb-1 lh-sm w-100"><?= htmlspecialchars($p['name']) ?></div>
                                    <div class="mb-1 w-100">
                                        <?php if ($pendingZra): ?>
                                            <span class="stock-badge bg-secondary">Pending ZRA</span>
                                        <?php elseif ($isRaw): ?>
                                            <span class="stock-badge bg-secondary">Raw</span>
                                        <?php elseif($hasRecipe): ?><span class="stock-badge bg-recipe">Made to Order</span><?php else: ?><span class="stock-badge <?= $isOut ? 'bg-low' : 'bg-ok' ?>"><?= \App\Helpers\Movement::formatQty($available) ?> in stock</span><?php endif; ?>
                                    </div>
                                    <div class="text-primary fw-bold w-100 mt-auto fs-6">ZMW <?= number_format($p['price'], 2) ?></div>
                                </button>
                            </form>
                            <?php if ($notSellable): ?>
                            <button type="button" class="btn btn-link p-0 position-absolute text-info" style="top:6px;right:10px;z-index:3;" title="<?= htmlspecialchars($sellBlock['reason']) ?>" data-bs-toggle="tooltip" onclick="event.preventDefault(); event.stopPropagation(); AppAlert.notify({icon:'info', title:'Not sold on POS', text: <?= json_encode($sellBlock['reason']) ?>});">
                                <i class="bi bi-info-circle fs-5"></i>
                            </button>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="services-grid" class="row g-2" style="display:none;">
                        <?php foreach($services as $s): ?>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2 item">
                            <div class="item-card w-100 p-2 text-start position-relative border-warning" onclick="addService(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name'])) ?>', <?= $s['price'] ?>, <?= $s['is_open_price'] ?>)">
                                <div style="height: 50px;" class="fw-bold text-dark mb-1 lh-sm"><?= htmlspecialchars($s['name']) ?></div>
                                <div class="text-success fw-bold"><?= $s['is_open_price'] ? 'Adjustable' : 'ZMW '.number_format($s['price'], 2) ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="pagination-bar" id="paginationBar">
                    <button class="btn btn-outline-dark fw-bold btn-sm px-3" onclick="prevPage()"><i class="bi bi-chevron-left"></i> PREV</button>
                    <span id="pageInfo" class="fw-bold text-muted mx-3">Page 1 of 1</span>
                    <button class="btn btn-outline-dark fw-bold btn-sm px-3" onclick="nextPage()">NEXT <i class="bi bi-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <div class="cart-scrim" id="cartScrim" onclick="setCartExpanded(false)"></div>
        <div class="cart-panel" id="cartPanel">
            <div class="cart-header d-flex justify-content-between align-items-center" id="cartHeader">
                <h5 class="m-0 fw-bold cart-compact-title"><i class="bi bi-basket3-fill me-2"></i> Order</h5>
                <button type="button" class="btn btn-sm btn-outline-light cart-compact" onclick="toggleCart()" aria-expanded="false">
                    <span class="fw-bold"><i class="bi bi-basket3-fill me-1"></i> <span id="cartCompactCount"><?= !empty($_SESSION['cart']) ? count($_SESSION['cart']) : 0 ?> items</span></span>
                    <span class="fw-bold" id="cartCompactTotal">ZMW <?= number_format($balance ?? 0, 2) ?></span>
                    <span class="cart-toggle-label"><i class="bi bi-chevron-up"></i> Basket</span>
                </button>
            </div>
            <div class="cart-items" id="cartItems">
                <?php
                    $cart = $_SESSION['cart'] ?? [];
                    include APP_PATH . '/Views/pos_cart_items.php';
                ?>
            </div>
            <div class="cart-footer">
                <div class="cart-total-row d-flex justify-content-between align-items-end mb-3">
                    <span class="text-muted small fw-bold">TOTAL</span>
                    <span class="fs-2 fw-bold <?= $balance < 0 ? 'text-danger' : 'text-dark' ?> lh-1" id="cartGrandTotal">ZMW <?= number_format($balance, 2) ?></span>
                </div>
                <div class="d-grid gap-2 mb-3">
                    <button type="button" id="btnCharge" class="btn w-100 py-3 btn-charge shadow <?= $balance < 0 ? 'bg-danger border-danger' : '' ?>" onclick="initCheckout(false)" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>><?= $balance < 0 ? 'REFUND CASH' : 'CHARGE' ?></button>
                    <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?>
                    <div class="btn-group w-100">
                        <button type="button" id="btnAddToTab" class="btn btn-warning fw-bold py-2" data-bs-toggle="modal" data-bs-target="#addToTabModal" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>ADD TO TAB</button>
                    </div>
                    <?php endif; ?>
                    <div class="row g-2">
                        <div class="col-<?= (in_array($tier, ['pro', 'pro+', 'enterprise'])) ? '6' : '12' ?>"><form method="POST" class="js-confirm-form js-pos-cart" data-swal-title="Clear the cart?" data-swal-text="All items on this order will be removed." data-swal-confirm="Clear" data-swal-cancel="Keep" data-swal-icon="warning"><input type="hidden" name="clear_cart" value="1"><button id="btnClearCart" class="btn btn-outline-danger w-100 btn-sm fw-bold" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>CLEAR</button></form></div>
                        <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?><div class="col-6"><button type="button" id="btnLostStock" class="btn btn-dark w-100 btn-sm fw-bold text-warning" onclick="logWasteAuth()" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>LOST STOCK</button></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="locationModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-warning"><div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold">Change Workstation</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body bg-light p-4"><form method="POST" class="js-confirm-form" data-swal-title="Switch workstation?" data-swal-text="The cart and stock counts will use the location you pick." data-swal-confirm="Switch" data-swal-cancel="Cancel" data-swal-icon="question" data-swal-confirm-color="#fd7e14"><?php foreach($sellableLocations as $loc): ?><button name="set_pos_location" value="<?= $loc['id'] ?>" class="btn btn-white border w-100 mb-2 py-3 fw-bold text-start shadow-sm"><?= htmlspecialchars($loc['name']) ?></button><?php endforeach; ?></form></div></div></div></div>

    <?php if ($tier === 'enterprise'): ?>
    <div class="modal fade" id="onlineTabsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content h-100">
                <div class="modal-header bg-primary text-white border-info border-bottom border-3">
                    <h5 class="modal-title fw-bold"><i class="bi bi-cloud-download me-2"></i> Online / Web Orders</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row h-100">
                        <div class="col-4 border-end overflow-auto">
                            <div class="list-group">
                            <?php if(empty($onlineTabs)): ?>
                                <p class="text-muted text-center mt-4 fw-bold">No pending web orders.</p>
                            <?php else: ?>
                                <?php foreach($onlineTabs as $ot): ?>
                                    <button class="list-group-item list-group-item-action bg-warning-subtle" onclick="showOnlineTabDetails(<?= $ot['id'] ?>)">
                                        <div class="d-flex justify-content-between"><strong><?= htmlspecialchars($ot['customer_name']) ?></strong> <span class="badge bg-danger">PENDING</span></div>
                                        <div class="small text-muted">ID: <?= htmlspecialchars($ot['external_id']) ?></div>
                                        <div class="small fw-bold">ZMW <?= number_format($ot['total_amount'],2) ?></div>
                                    </button>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-8 p-3" id="onlineTabDetailContainer">
                            <p class="text-center text-muted mt-5"><i class="bi bi-cursor fs-1"></i><br>Select an online order to view details.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div id="hiddenOnlineTabTemplates" style="display:none;">
        <?php foreach($onlineTabItems as $tid => $items): ?>
            <?php $tabCustomerName = ''; $tabTotal = 0; foreach($onlineTabs as $t) { if($t['id'] == $tid) { $tabCustomerName = $t['customer_name']; $tabTotal = $t['total_amount']; break; } } ?>
            <div id="online-tab-data-<?= $tid ?>">
                <table class="table align-middle mb-4">
                <?php 
                $allReady = true; // NEW: Track if entire order is finished cooking
                foreach($items as $i): 
                    $statusBadge = ''; 
                    if($i['status']=='pending') { $statusBadge = '<span class="badge bg-danger">KITCHEN</span>'; $allReady = false; } 
                    elseif($i['status']=='cooking') { $statusBadge = '<span class="badge bg-warning text-dark">COOKING</span>'; $allReady = false; } 
                    elseif($i['status']=='ready') { $statusBadge = '<span class="badge bg-info text-dark">READY</span>'; } 
                ?>
                <tr id="online-item-row-<?= $i['id'] ?>">
                    <td><?= $i['quantity'] ?>x <?= htmlspecialchars($i['name']) ?> <?= $statusBadge ?></td>
                    <td class="text-end fw-bold">ZMW <?= number_format($i['price'] * $i['quantity'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
                </table>
                <div class="row g-2 mt-3">
                    <div class="col-6"><button type="button" class="btn btn-outline-dark w-100 fw-bold py-3 shadow-sm text-uppercase" onclick="printTabBill(<?= $tid ?>)"><i class="bi bi-printer"></i> Print Receipt</button></div>
                    <div class="col-6">
                        <?php if ($allReady): ?>
                            <button type="button" class="btn btn-success w-100 fw-bold py-3 shadow-sm text-uppercase" onclick="openOnlineSettleModal(<?= $tid ?>, <?= $tabTotal ?>, '<?= htmlspecialchars(addslashes($tabCustomerName)) ?>')">
                                <i class="bi bi-cash-coin"></i> Checkout Order
                            </button>
                        <?php else: ?>
                            <button type="button" class="btn btn-secondary w-100 fw-bold py-3 shadow-sm text-uppercase" disabled>
                                <i class="bi bi-hourglass-split"></i> Still Cooking
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="addToTabModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg border-warning border-top border-4"><div class="modal-header bg-light"><h5 class="modal-title fw-bold text-dark"><i class="bi bi-plus-square text-warning"></i> Add to Tab</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" class="js-confirm-form" data-swal-title="Add this order to a tab?" data-swal-text="Items will leave the current cart and sit on the selected tab." data-swal-confirm="Add to tab" data-swal-cancel="Cancel" data-swal-icon="question" data-swal-confirm-color="#fd7e14"><div class="modal-body p-4"><input type="hidden" name="add_to_tab_action" value="1"><label class="form-label small fw-bold text-muted mb-2">SELECT DESTINATION</label><div class="list-group mb-3" id="tabSelectionGroup"><label class="list-group-item tab-radio-label active" onclick="highlightTabSelection(this)"><input class="form-check-input me-2" type="radio" name="target_tab_id" value="new" checked><span class="fw-bold">Create New Custom Tab</span></label><?php foreach($openTabs as $t): if($t['payment_status'] !== 'paid'): ?><label class="list-group-item tab-radio-label" onclick="highlightTabSelection(this)"><input class="form-check-input me-2" type="radio" name="target_tab_id" value="<?= $t['id'] ?>"> <strong>Merge into: <?= htmlspecialchars($t['customer_name']) ?></strong></label><?php endif; endforeach; ?></div><div id="newTabNameInput"><label class="form-label small fw-bold text-muted mb-1">NEW CUSTOMER NAME</label><input type="text" name="tab_customer_name" class="form-control mb-2" placeholder="Enter name or walk-in">
        
        <?php if (in_array($tier, ['pro+', 'enterprise'])): ?>
        <label class="form-label small fw-bold text-muted mb-1 mt-2">ASSIGN TO TABLE (OPTIONAL)</label><select name="target_table_id" class="form-select"><option value="">-- No Table (Bar Tab) --</option><?php foreach($restaurantTables as $zone => $tables): ?><optgroup label="<?= htmlspecialchars($zone) ?>"><?php foreach($tables as $tbl): ?><option value="<?= $tbl['id'] ?>"><?= htmlspecialchars($tbl['table_name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select>
        <?php endif; ?>

    </div></div><div class="modal-footer bg-light border-0"><button type="submit" class="btn btn-warning w-100 fw-bold py-3 shadow-sm text-dark">CONFIRM TRANSFER</button></div></form></div></div></div>
    
    <?php if (in_array($tier, ['pro+', 'enterprise'])): ?>
    <div class="modal fade" id="floorplanModal"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content bg-light"><div class="modal-header bg-dark text-white border-warning border-bottom border-3"><h5 class="modal-title fw-bold"><i class="bi bi-grid-3x3-gap-fill me-2 text-warning"></i> Table Floorplan</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><?php if(empty($restaurantTables)): ?><div class="text-center text-muted my-5"><i class="bi bi-info-circle display-4"></i><p class="mt-3">No tables have been configured for this location yet.</p></div><?php else: ?><?php foreach($restaurantTables as $zoneName => $tables): ?><h5 class="fw-bold text-muted border-bottom pb-2 mb-3 mt-4"><?= htmlspecialchars($zoneName) ?></h5><div class="row g-3">
        <?php foreach($tables as $table): 
            $occupancy = $occupiedTables[$table['id']] ?? null;
            $isOccupied = ($occupancy !== null);
        ?>
        <div class="col-6 col-md-4 col-lg-3">
            <?php if($isOccupied): 
                $canAccess = ($isManager || $occupancy['user_id'] == $userId);
                if ($canAccess):
            ?>
                <div class="table-box table-occupied" onclick="switchModal('floorplanModal', 'tabsModal', () => showTabDetails(<?= $occupancy['sale_id'] ?>))">
                    <span class="table-capacity"><i class="bi bi-people-fill"></i> <?= $table['capacity'] ?></span>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($table['table_name']) ?></h5>
                    <div class="small fw-bold">ZMW <?= number_format($occupancy['final_total'], 2) ?></div>
                    <div class="badge bg-danger mt-2">OCCUPIED</div>
                </div>
            <?php else: ?>
                <div class="table-box bg-secondary text-white border-secondary" style="cursor: not-allowed; opacity: 0.8;" onclick="AppAlert.notify({icon: 'error', title: 'Table in use', text: 'This table is assigned to <?= htmlspecialchars(addslashes($occupancy['username'])) ?>.'})">
                    <span class="table-capacity"><i class="bi bi-people-fill"></i> <?= $table['capacity'] ?></span>
                    <h5 class="fw-bold mb-1 text-white"><?= htmlspecialchars($table['table_name']) ?></h5>
                    <div class="small"><i class="bi bi-lock-fill"></i> <?= htmlspecialchars($occupancy['username']) ?></div>
                    <div class="badge bg-dark mt-2">LOCKED</div>
                </div>
            <?php endif; ?>
            <?php else: ?>
                <form method="POST" class="h-100 js-confirm-form"
                    data-swal-title="Open this table?"
                    data-swal-text="Cart items will be parked on <?= htmlspecialchars($table['table_name']) ?>."
                    data-swal-confirm="Open table"
                    data-swal-cancel="Cancel"
                    data-swal-icon="question"
                    data-swal-confirm-color="#198754">
                    <input type="hidden" name="add_to_tab_action" value="1"><input type="hidden" name="target_tab_id" value="new"><input type="hidden" name="target_table_id" value="<?= $table['id'] ?>"><input type="hidden" name="tab_customer_name" value="<?= htmlspecialchars($table['table_name']) ?>">
                    <button type="submit" class="table-box table-available w-100" <?= empty($_SESSION['cart']) ? 'onclick="event.preventDefault(); AppAlert.notify({icon:\'warning\', title:\'Cart is empty\', text:\'Add items before opening a table.\', confirmText:\'OK\'}); return false;"' : '' ?>>
                        <span class="table-capacity"><i class="bi bi-people-fill"></i> <?= $table['capacity'] ?></span>
                        <h5 class="fw-bold mb-1"><?= htmlspecialchars($table['table_name']) ?></h5>
                        <div class="badge bg-success mt-2">AVAILABLE</div>
                    </button>
                </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        </div><?php endforeach; ?><?php endif; ?></div></div></div></div>
    <?php endif; ?>
    
    <div class="modal fade" id="tabsModal"><div class="modal-dialog modal-xl"><div class="modal-content h-100">
        <div class="modal-header bg-dark text-white border-info border-bottom border-3">
            <h5 class="modal-title fw-bold"><i class="bi bi-receipt me-2 text-info"></i> Active Tabs</h5>
            
            <?php if (in_array($tier, ['pro+', 'enterprise'])): ?>
            <button type="button" class="btn btn-sm btn-outline-info ms-auto me-3 fw-bold" onclick="switchModal('tabsModal', 'floorplanModal')"><i class="bi bi-grid-3x3-gap-fill"></i> View Tables</button>
            <?php endif; ?>
            
            <button type="button" class="btn-close btn-close-white <?= (!in_array($tier, ['pro+', 'enterprise'])) ? 'ms-auto' : '' ?>" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body"><div class="row h-100"><div class="col-4 border-end overflow-auto"><div class="list-group"><?php foreach($openTabs as $t): $isPaid = $t['payment_status'] === 'paid'; $bg = $isPaid ? 'bg-success-subtle' : ''; $badge = $isPaid ? '<span class="badge bg-success">PAID</span>' : ''; ?><button class="list-group-item list-group-item-action <?= $bg ?>" onclick="showTabDetails(<?= $t['id'] ?>)"><div class="d-flex justify-content-between"><strong><?= htmlspecialchars($t['customer_name']) ?></strong> <?= $badge ?></div><div class="small text-muted">ZMW <?= number_format($t['final_total'],2) ?></div></button><?php endforeach; ?></div></div><div class="col-8 p-3" id="tabDetailContainer"><p class="text-center text-muted mt-5">Select a tab from the list to view details.</p></div></div></div></div></div></div>

    <div class="modal fade" id="transferModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content border-0 shadow-lg border-info border-top border-4"><div class="modal-header bg-light"><h5 class="modal-title fw-bold text-dark"><i class="bi bi-arrow-left-right text-info me-2"></i> Transfer / Merge Items</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" class="js-confirm-form" data-swal-title="Move these items?" data-swal-text="Selected items will leave this tab and join the destination." data-swal-confirm="Transfer" data-swal-cancel="Cancel" data-swal-icon="question" data-swal-confirm-color="#0dcaf0"><div class="modal-body p-4"><input type="hidden" name="transfer_tab_items" value="1"><input type="hidden" name="source_tab_id" id="transfer_source_id" value=""><div class="row"><div class="col-md-6 border-end"><div class="d-flex justify-content-between align-items-center mb-2"><label class="form-label small fw-bold text-muted m-0">SELECT ITEMS TO MOVE</label><button type="button" class="btn btn-sm btn-link p-0 text-decoration-none fw-bold" onclick="toggleTransferSelectAll()">Select All</button></div><div class="list-group mb-3" id="transferItemsContainer" style="max-height: 300px; overflow-y: auto;"></div></div><div class="col-md-6"><label class="form-label small fw-bold text-muted mb-2">DESTINATION TAB</label><div class="list-group mb-3" id="transferTargetContainer" style="max-height: 300px; overflow-y: auto;"><label class="list-group-item tab-radio-label active" onclick="highlightTransferSelection(this)"><input class="form-check-input me-2" type="radio" name="target_tab_id" value="new" checked><span class="fw-bold">New Custom Tab</span></label><?php foreach($openTabs as $t): if($t['payment_status'] !== 'paid'): ?><label class="list-group-item tab-radio-label transfer-target-item" data-id="<?= $t['id'] ?>" onclick="highlightTransferSelection(this)"><input class="form-check-input me-2" type="radio" name="target_tab_id" value="<?= $t['id'] ?>"> <strong>Merge into: <?= htmlspecialchars($t['customer_name']) ?></strong></label><?php endif; endforeach; ?></div><div id="transferNewTabInput"><label class="form-label small fw-bold text-muted mb-1">NEW CUSTOMER NAME</label><input type="text" name="new_tab_name" class="form-control mb-2" placeholder="Enter name">
        
        <?php if (in_array($tier, ['pro+', 'enterprise'])): ?>
        <label class="form-label small fw-bold text-muted mb-1 mt-2">ASSIGN TO TABLE (OPTIONAL)</label><select name="target_table_id" class="form-select"><option value="">-- No Table (Bar Tab) --</option><?php foreach($restaurantTables as $zone => $tables): ?><optgroup label="<?= htmlspecialchars($zone) ?>"><?php foreach($tables as $tbl): ?><option value="<?= $tbl['id'] ?>"><?= htmlspecialchars($tbl['table_name']) ?></option><?php endforeach; ?></optgroup><?php endforeach; ?></select>
        <?php endif; ?>

    </div></div></div></div><div class="modal-footer bg-light border-0"><button type="submit" class="btn btn-info w-100 fw-bold py-3 shadow-sm text-dark">CONFIRM TRANSFER</button></div></form></div></div></div>

    <div id="hiddenTabTemplates" style="display:none;">
        <?php foreach($tabItems as $tid => $items): ?>
            <?php $tabCustomerName = ''; $tabPaymentStatus = 'pending'; foreach($openTabs as $t) { if($t['id'] == $tid) { $tabCustomerName = $t['customer_name']; $tabPaymentStatus = $t['payment_status']; break; } } ?>
            <div id="tab-data-<?= $tid ?>">
                <table class="table align-middle mb-4">
                <?php foreach($items as $i): $statusBadge = ''; if($i['status']=='pending') $statusBadge = '<span class="badge bg-danger">PENDING</span>'; elseif($i['status']=='cooking') $statusBadge = '<span class="badge bg-warning text-dark">COOKING</span>'; elseif($i['status']=='ready') $statusBadge = '<span class="badge bg-info text-dark">READY</span>'; ?>
                <tr id="item-row-<?= $i['id'] ?>">
                    <td><?= $i['quantity'] ?>x <?= htmlspecialchars($i['name']) ?> <?= $statusBadge ?></td>
                    <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary me-2" onclick="pushToCart(<?= $i['product_id'] ?>, <?= $i['price'] ?>, <?= $i['id'] ?>, '<?= $tabPaymentStatus ?>')" title="Refund / Cart"><i class="bi bi-cart-plus"></i></button>
                        <?php if ($tabPaymentStatus === 'pending'): ?><button type="button" class="btn btn-sm btn-outline-danger me-2" onclick="voidItem(<?= $i['id'] ?>)" title="Void this item"><i class="bi bi-trash"></i></button><?php endif; ?>
                        <?php if($i['fulfillment_status'] == 'uncollected'): ?>
                            <?php if(in_array(strtolower($i['cat_type'] ?? ''), ['food', 'meal']) && in_array($tier, ['pro+', 'enterprise'])): ?>
                                <?php if($i['status'] === 'ready'): ?><span class="badge bg-warning text-dark border shadow-sm px-2 py-1" style="cursor:pointer;" onclick="switchModal('tabsModal', '', showPickupModal)"><i class="bi bi-bag-check"></i> GO TO PICKUP</span><?php else: ?><span class="badge bg-secondary text-white border shadow-sm px-2 py-1" style="cursor:pointer;" onclick="AppAlert.notify({icon: 'info', title: 'Still preparing', text: 'This item is not ready for collection yet.'})"><i class="bi bi-hourglass-split"></i> PREPARING</span><?php endif; ?>
                            <?php else: ?><span class="badge badge-uncollected p-2" style="cursor:pointer;" onclick="markCollected(<?= $i['id'] ?>, <?= $tid ?>)">MARK COLLECTED</span><?php endif; ?>
                        <?php else: ?><span class="badge bg-success">COLLECTED</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </table>
                <?php if($tabPaymentStatus === 'paid'): ?><div class="alert alert-success text-center fw-bold py-3 mb-0 shadow-sm"><i class="bi bi-check-circle-fill"></i> PAID IN FULL</div><?php else: ?><div class="row g-2 mt-3"><div class="col-12"><button type="button" class="btn btn-info w-100 fw-bold py-2 shadow-sm text-dark text-uppercase mb-1" onclick="openTransferModal(<?= $tid ?>)"><i class="bi bi-arrow-left-right"></i> Transfer / Merge Items</button></div><div class="col-5"><button type="button" class="btn btn-outline-dark w-100 fw-bold py-3 shadow-sm text-uppercase" onclick="printTabBill(<?= $tid ?>)"><i class="bi bi-printer"></i> Print Bill</button></div><div class="col-7"><button type="button" class="btn btn-primary w-100 fw-bold py-3 shadow-sm text-uppercase" onclick="openSettleModal(<?= $tid ?>, <?= array_sum(array_map(function($x){ return $x['price']*$x['quantity']; }, $items)) ?>, '<?= htmlspecialchars(addslashes($tabCustomerName)) ?>', <?= $t['amount_tendered'] ?? 0 ?>)"><i class="bi bi-cash-coin"></i> Settle Tab</button></div></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="modal fade" id="openPriceModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-sm"><div class="modal-content shadow-lg border-warning"><div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold" id="op_name">Custom Amount</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><form method="POST" class="js-pos-cart" id="openPriceForm"><div class="modal-body p-4 bg-light"><input type="hidden" name="add_item" value="1"><input type="hidden" name="product_id" id="op_pid"><input type="hidden" name="is_refund" id="op_refund" value="0"><label class="form-label small fw-bold text-muted mb-2">AMOUNT</label><div class="input-group"><span class="input-group-text fw-bold">ZMW</span><input type="number" step="0.01" name="custom_price" id="op_price" class="form-control fw-bold" required placeholder="0.00"></div></div><div class="modal-footer border-0"><button type="submit" class="btn btn-warning w-100 fw-bold py-2 shadow-sm">CONFIRM</button></div></form></div></div></div>
    
    <div class="modal fade" id="checkoutModal">
        <div class="modal-dialog modal-dialog-scrollable modal-fullscreen-sm-down">
            <form method="POST" class="modal-content border-0 js-confirm-form" id="checkoutForm"
                data-swal-title="Complete this sale?"
                data-swal-text="Payment will be recorded and the receipt will be sent to ZRA."
                data-swal-confirm="Complete"
                data-swal-cancel="Cancel"
                data-swal-icon="question"
                data-swal-confirm-color="#fd7e14">
                <div class="modal-header bg-warning text-dark" id="checkoutHeader">
                    <h5 class="modal-title fw-bold">Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="checkout" value="1">
        <input type="hidden" name="settle_tab_id" id="settle_tab_id_input" value="0">
        <?php if(isset($_SESSION['pos_member']) && in_array($tier, ['pro', 'pro+', 'enterprise'])): ?><div class="alert alert-info border-info d-flex align-items-center justify-content-between mb-3 p-2 shadow-sm"><div class="d-flex align-items-center"><i class="bi bi-star-fill text-warning fs-4 me-3"></i><div><div class="fw-bold">Member: <?= htmlspecialchars($_SESSION['pos_member']['name']) ?></div><div class="small text-muted">Eligible for benefits</div></div></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="discountToggle" name="apply_discount" value="1" onchange="toggleDiscount()"><label class="form-check-label fw-bold small" for="discountToggle">10% OFF</label></div></div><?php endif; ?>
        <div class="text-center mb-4"><small class="text-muted text-uppercase fw-bold">Amount To Pay</small><div class="display-4 fw-bold text-dark">ZMW <span id="displayTotalDue">0.00</span></div><small class="text-success fw-bold" id="discountLabel" style="display:none;">(Discount Applied)</small></div>
        <div id="refundAuthSection" class="p-3 bg-dark rounded shadow-sm border border-danger mb-4" style="display:none;">
            <label class="form-label small fw-bold text-danger mb-3 d-block border-bottom border-danger pb-2"><i class="bi bi-shield-lock-fill"></i> MANAGER AUTHORIZATION REQUIRED</label>
            <label class="form-label small fw-bold text-white" for="rfdRsnCd">ZRA refund reason</label>
            <select name="rfd_rsn_cd" id="rfdRsnCd" class="form-select mb-3">
                <?php foreach (\ZraVsdcClient::refundReasonCodes() as $code => $label): ?>
                <option value="<?= htmlspecialchars((string)$code) ?>" <?= $code === '07' ? 'selected' : '' ?>><?= htmlspecialchars((string)$label) ?></option>
                <?php endforeach; ?>
            </select>
            <input type="text" name="mgr_username" id="mgrUserRefund" class="form-control mb-2" placeholder="Manager Username">
            <input type="password" name="mgr_password" id="mgrPassRefund" class="form-control" placeholder="Manager Password">
        </div>
        <div class="input-group mb-3"><span class="input-group-text bg-light fw-bold">Tip</span><input type="number" step="0.01" min="0" name="tip_amount" id="tipInput" class="form-control" placeholder="0.00" oninput="onTipChange()"><button type="button" class="btn btn-outline-secondary" onclick="addTipPercent(0.05)">5%</button><button type="button" class="btn btn-outline-secondary" onclick="addTipPercent(0.10)">10%</button><button type="button" class="btn btn-outline-secondary" onclick="addTipPercent(0.15)">15%</button></div>
        <div class="mb-3"><input type="text" name="customer_name" class="form-control" placeholder="Customer Name" value="<?= $_SESSION['current_customer'] ?? 'Walk-in' ?>" <?= isset($_SESSION['pos_member']) ? 'readonly' : '' ?>></div>
        <div class="btn-group w-100 mb-3 <?= ($tier === 'lite') ? 'd-none' : '' ?>" role="group" id="splitModeGroup"><input type="radio" class="btn-check" name="is_split" id="modeSingle" value="0" checked onchange="toggleMode()"><label class="btn btn-outline-dark fw-bold" for="modeSingle">Single Pay</label><input type="radio" class="btn-check" name="is_split" id="modeSplit" value="1" onchange="toggleMode()"><label class="btn btn-outline-dark fw-bold" for="modeSplit">Split Pay</label></div>
        <div id="singleSection"><div class="mb-3"><select name="payment_method" class="form-select form-select-lg fw-bold"><option value="Cash" selected>Cash</option><option value="Card">Card</option><option value="MTN Money">MTN Money</option><option value="Airtel Money">Airtel Money</option><option value="Zamtel Money">Zamtel Money</option>
        
        <?php if ($tier === 'enterprise'): ?>
        <option value="Online">Online Delivery App</option>
        <?php endif; ?>

        <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?><option value="Pending">Put on Tab</option><?php endif; ?></select></div></div>
        <div id="splitSection" style="display:none;" class="p-3 bg-light rounded border border-secondary mb-3">
            <label class="form-label small fw-bold text-dark mb-2"><i class="bi bi-pie-chart-fill"></i> SPLIT PAYMENT DETAILS</label>
            <div id="splitRows"></div>
            <button type="button" class="btn btn-outline-dark btn-sm w-100 fw-bold mt-2" onclick="addSplitRow()"><i class="bi bi-plus-lg"></i> Add payment method</button>
            <div class="small text-muted mt-2">Choose a method or <strong>Other</strong> and type a custom name (bank transfer, cheque, voucher…).</div>
        </div>
        <div class="card bg-light border-0 p-3 mt-3"><label class="form-label small fw-bold text-muted mb-1">TOTAL TENDERED</label><div class="input-group input-group-lg"><span class="input-group-text bg-white border-end-0 fw-bold">ZMW</span><input type="number" step="0.01" name="amount_tendered" id="tenderedInput" class="form-control border-start-0 fw-bold fs-3 text-success" inputmode="decimal" oninput="tenderedTouched = true; calcResult()" onkeyup="calcResult()"></div><div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top"><div class="small fw-bold text-uppercase text-muted" id="resultLabel">Change Due</div><div class="fs-4 fw-bold text-dark" id="resultValue">ZMW 0.00</div></div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning w-100 fw-bold py-3 shadow-sm" id="btnCheckoutSubmit">COMPLETE TRANSACTION</button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="expenseModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg border-top border-warning border-4"><div class="modal-header bg-light"><h5 class="modal-title fw-bold text-dark"><i class="bi bi-cash-stack text-warning"></i> Log Payout</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST" class="js-confirm-form" data-swal-title="Authorize this payout?" data-swal-text="This amount is deducted from the expected drawer total immediately." data-swal-confirm="Authorize" data-swal-cancel="Cancel" data-swal-icon="warning" data-swal-confirm-color="#fd7e14"><div class="modal-body p-4"><input type="hidden" name="log_expense" value="1"><div class="alert alert-warning small border-warning"><strong><i class="bi bi-info-circle"></i> Note:</strong> This amount is deducted from the expected drawer total immediately.</div><div class="mb-3"><label class="form-label small fw-bold text-muted">Amount Taken</label><div class="input-group input-group-lg"><span class="input-group-text bg-white fw-bold">ZMW</span><input type="number" step="0.01" name="expense_amount" class="form-control fw-bold text-danger" required placeholder="0.00"></div></div><div class="mb-4"><label class="form-label small fw-bold text-muted">Reason</label><input type="text" name="expense_reason" class="form-control" required placeholder="Paid driver..."></div><div class="p-3 bg-dark rounded shadow-sm border border-secondary"><label class="form-label small fw-bold text-warning mb-3 d-block border-bottom border-secondary pb-2"><i class="bi bi-shield-lock-fill"></i> MANAGER AUTHORIZATION</label><input type="text" name="mgr_username" class="form-control mb-2" required placeholder="Manager Username"><input type="password" name="mgr_password" class="form-control" required placeholder="Manager Password"></div></div><div class="modal-footer border-0"><button type="submit" class="btn btn-warning w-100 fw-bold shadow-sm">Authorize Payout</button></div></form></div></div></div>
    
    <div class="modal fade" id="endShiftModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg border-top border-danger border-4">
                <div class="modal-header bg-light text-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-power text-danger"></i> End Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="index.php?page=end_shift_action" method="POST" class="js-confirm-form"
                    data-swal-title="End this shift?"
                    data-swal-text="The drawer will close. You cannot ring sales until a new shift is opened."
                    data-swal-confirm="End shift"
                    data-swal-cancel="Cancel"
                    data-swal-icon="warning">
                    <div class="modal-body p-4">
                        <div class="alert alert-light border border-danger text-center mb-4 shadow-sm">
                            <small class="text-uppercase fw-bold text-muted">Expected Cash In Drawer</small>
                            <div class="h2 fw-bold text-danger m-0">ZMW <?= number_format($expectedShiftCash ?? 0, 2) ?></div>
                        </div>
                        <label class="fw-bold small text-muted">ACTUAL CLOSING CASH</label>
                        <div class="input-group input-group-lg mb-3">
                            <span class="input-group-text fw-bold">ZMW</span>
                            <input type="number" step="0.01" name="closing_cash" class="form-control fw-bold text-primary" required value="<?= $expectedShiftCash ?>">
                        </div>
                        <label class="fw-bold small text-muted">VARIANCE REASON</label>
                        <textarea name="variance_reason" class="form-control mb-3" placeholder="Explain any difference..."></textarea>
                        
                        <div class="p-3 bg-dark rounded shadow-sm border border-secondary mt-2">
                            <label class="fw-bold small text-danger d-block border-bottom border-secondary pb-2 mb-3"><i class="bi bi-shield-lock-fill"></i> MANAGER AUTHORIZATION</label>
                            <input type="text" name="manager_username" class="form-control mb-2" required placeholder="Manager Username">
                            <input type="password" name="manager_password" class="form-control" required placeholder="Manager Password">
                        </div>
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-danger w-100 fw-bold py-3 shadow">CLOSE SHIFT & LOGOUT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="reportModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content border-0 shadow-lg"><div class="modal-header bg-info text-dark"><h5 class="modal-title fw-bold" id="reportTitle"><i class="bi bi-file-earmark-text"></i> X-Read</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0" style="height: 65vh;"><iframe id="reportFrame" src="" style="width:100%; height:100%; border:none;"></iframe></div><div class="modal-footer bg-light"><button type="button" class="btn btn-info fw-bold px-4" onclick="document.getElementById('reportFrame').contentWindow.print()"><i class="bi bi-printer"></i> PRINT X-READ</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <div class="modal fade" id="pickupModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content h-100"><div class="modal-body p-0"><iframe id="pickupFrame" src="" style="width:100%; height:80vh; border:none;"></iframe></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <div class="modal fade" id="refundInvoiceModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-danger shadow-lg">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-receipt-cutoff me-2"></i>Select original invoice</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="refundInvoiceProduct">Choose the sale this return belongs to. ZRA will credit that Smart Invoice.</p>
                    <input type="search" id="refundInvoiceSearch" class="form-control mb-3" placeholder="Filter by sale # or ZRA receipt…">
                    <div id="refundInvoiceList" class="list-group"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="receiptModal" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content"><div class="modal-header bg-success text-white"><h5>Receipt</h5></div><div class="modal-body p-0" style="height:400px;"><iframe id="receiptFrame" src="" style="width:100%; height:100%; border:none;"></iframe></div><div class="modal-footer"><button type="button" class="btn btn-primary" onclick="document.getElementById('receiptFrame').contentWindow.print()">PRINT</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>

    <script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script src="js/pos-keyboard.js?v=5"></script>
    <script>
        window.posCartBalance = <?= json_encode((float)($balance ?? 0)) ?>;
        function formatPosQty(qty) {
            const n = Math.max(0, parseFloat(qty) || 0);
            const s = n.toFixed(4).replace(/0+$/, '').replace(/\.$/, '');
            return s === '' ? '0' : s;
        }
        function applyCartResponse(data) {
            if (!data) return;
            if (data.status === 'error') {
                AppAlert.notify({ icon: 'error', text: data.msg || 'Unable to update the cart.' });
                return;
            }
            const items = document.getElementById('cartItems');
            if (items && typeof data.cart_html === 'string') {
                items.innerHTML = data.cart_html;
            }
            window.posCartBalance = parseFloat(data.balance || 0);
            const count = parseInt(data.cart_count || 0, 10);
            const empty = !!data.empty;
            const countEl = document.getElementById('cartCompactCount');
            if (countEl) countEl.textContent = count + (count === 1 ? ' item' : ' items');
            const compactTotal = document.getElementById('cartCompactTotal');
            if (compactTotal) compactTotal.textContent = 'ZMW ' + (data.balance_label || '0.00');
            const grand = document.getElementById('cartGrandTotal');
            if (grand) {
                grand.textContent = 'ZMW ' + (data.balance_label || '0.00');
                grand.classList.toggle('text-danger', window.posCartBalance < 0);
                grand.classList.toggle('text-dark', window.posCartBalance >= 0);
            }
            const charge = document.getElementById('btnCharge');
            if (charge) {
                charge.disabled = empty;
                charge.textContent = window.posCartBalance < 0 ? 'REFUND CASH' : 'CHARGE';
                charge.classList.toggle('bg-danger', window.posCartBalance < 0);
                charge.classList.toggle('border-danger', window.posCartBalance < 0);
            }
            ['btnAddToTab', 'btnClearCart', 'btnLostStock'].forEach(function (id) {
                const el = document.getElementById(id);
                if (el) el.disabled = empty;
            });
            const qtyMap = data.cart_qty || {};
            document.querySelectorAll('#items-grid .item[data-tracks-stock="1"]').forEach(function (el) {
                const onHand = parseFloat(el.dataset.onHand || '0');
                const inCart = parseFloat(qtyMap[el.dataset.productId] || qtyMap[String(el.dataset.productId)] || 0);
                const available = Math.max(0, onHand - inCart);
                const badge = el.querySelector('.stock-badge');
                if (badge && !badge.classList.contains('bg-recipe') && !badge.classList.contains('bg-secondary')) {
                    badge.textContent = formatPosQty(available) + ' in stock';
                    badge.classList.toggle('bg-ok', available > 0);
                    badge.classList.toggle('bg-low', available <= 0);
                }
                el.dataset.out = available <= 0 ? '1' : '0';
            });
            const notice = data.notice || null;
            if (notice && notice.kind === 'shortage') {
                AppAlert.notify({
                    icon: 'warning',
                    title: notice.title || 'Not enough stock',
                    html: notice.html || notice.text || '',
                    confirmText: 'Stay on POS'
                });
            } else if (notice && notice.text) {
                AppAlert.notify({ icon: notice.kind === 'info' ? 'info' : 'error', text: notice.text });
            }
            if (window.innerWidth <= 768 && !empty) {
                setCartExpanded(true);
            }
        }
        function posCartParse(r) {
            return r.text().then(function (text) {
                const start = text.indexOf('{');
                const payload = start >= 0 ? text.slice(start) : text;
                try {
                    return JSON.parse(payload);
                } catch (err) {
                    throw new Error('bad-json');
                }
            });
        }
        function posCartSubmit(form, submitter) {
            const body = new FormData(form);
            body.set('ajax', '1');
            if (submitter && submitter.name) {
                body.set(submitter.name, submitter.value);
            }
            return fetch('index.php?page=pos', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: body
            }).then(posCartParse).then(function (data) {
                applyCartResponse(data);
                const modalEl = form.closest('.modal');
                if (modalEl && data && data.status !== 'error' && window.bootstrap) {
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                }
            }).catch(function () {
                AppAlert.notify({ icon: 'error', text: 'Unable to update the cart.' });
            });
        }
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!form || !form.classList || !form.classList.contains('js-pos-cart')) {
                return;
            }
            if (form.classList.contains('js-confirm-form') && form.dataset.swalConfirmed !== '1') {
                return;
            }
            if (needsRefundInvoicePick(form)) {
                e.preventDefault();
                e.stopPropagation();
                const pid = (form.querySelector('[name="product_id"]') || {}).value || '';
                const card = form.closest('.item');
                const name = (card && card.getAttribute('data-label')) || (document.getElementById('op_name') ? document.getElementById('op_name').innerText : '');
                openRefundInvoicePicker(pid, name);
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            posCartSubmit(form, e.submitter);
        }, true);
        function isPosRefundMode() {
            const toggle = document.getElementById('refundToggle');
            return !!(toggle && toggle.checked);
        }
        function needsRefundInvoicePick(form) {
            if (!isPosRefundMode()) {
                return false;
            }
            if (!form.classList.contains('add-item-form') && form.id !== 'openPriceForm') {
                return false;
            }
            const orig = form.querySelector('[name="refund_sale_item_id"]');
            return !orig || !parseInt(orig.value, 10);
        }
        let refundInvoiceState = { productId: '', productName: '', invoices: [] };
        function openRefundInvoicePicker(productId, productName) {
            refundInvoiceState = { productId: String(productId || ''), productName: productName || '', invoices: [] };
            const list = document.getElementById('refundInvoiceList');
            const label = document.getElementById('refundInvoiceProduct');
            const search = document.getElementById('refundInvoiceSearch');
            if (label) {
                label.textContent = (productName ? productName + ' — ' : '') + 'Choose the sale this return belongs to. ZRA will credit that Smart Invoice.';
            }
            if (search) search.value = '';
            if (list) list.innerHTML = '<div class="text-muted py-3 text-center">Looking up invoices…</div>';
            safeModalShow('refundInvoiceModal');
            const body = new FormData();
            body.set('ajax', '1');
            body.set('lookup_refund_invoices', '1');
            body.set('product_id', String(productId || ''));
            fetch('index.php?page=pos', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: body
            }).then(posCartParse).then(function (data) {
                refundInvoiceState.invoices = (data && data.invoices) || [];
                renderRefundInvoiceList();
            }).catch(function () {
                if (list) list.innerHTML = '<div class="alert alert-danger mb-0">Unable to load invoices.</div>';
            });
        }
        function renderRefundInvoiceList() {
            const list = document.getElementById('refundInvoiceList');
            const search = document.getElementById('refundInvoiceSearch');
            if (!list) return;
            const q = ((search && search.value) || '').trim().toLowerCase();
            const rows = refundInvoiceState.invoices.filter(function (inv) {
                if (!q) return true;
                const hay = ['#' + inv.sale_id, String(inv.zra_rcpt_no || ''), inv.customer_name || '', inv.location_name || ''].join(' ').toLowerCase();
                return hay.indexOf(q) !== -1;
            });
            if (!refundInvoiceState.invoices.length) {
                list.innerHTML = '<div class="alert alert-warning mb-0">No paid sale of this item is left to refund at any station. Open <strong>Reports</strong> and refund the original ticket.</div>';
                return;
            }
            if (!rows.length) {
                list.innerHTML = '<div class="text-muted py-3 text-center">No invoice matches that search.</div>';
                return;
            }
            list.innerHTML = rows.map(function (inv) {
                const when = inv.created_at ? String(inv.created_at).replace('T', ' ').slice(0, 16) : '';
                const zra = inv.zra_rcpt_no ? ('ZRA #' + inv.zra_rcpt_no) : 'Not on ZRA yet';
                const station = inv.same_station ? 'This station' : (inv.location_name || 'Other station');
                const left = inv.remaining != null ? inv.remaining : '';
                return '<button type="button" class="list-group-item list-group-item-action refund-invoice-pick" data-sale-item-id="' + inv.sale_item_id + '">'
                    + '<div class="d-flex justify-content-between align-items-center">'
                    + '<span class="fw-bold">Sale #' + inv.sale_id + '</span>'
                    + '<span class="badge ' + (inv.same_station ? 'bg-dark' : 'bg-secondary') + '">' + station + '</span>'
                    + '</div>'
                    + '<div class="small text-muted">' + when + ' · ' + (inv.customer_name || 'Walk-in') + ' · ' + zra + '</div>'
                    + '<div class="small">ZMW ' + Number(inv.price || 0).toFixed(2) + ' · ' + left + ' left</div>'
                    + '</button>';
            }).join('');
        }
        document.addEventListener('input', function (e) {
            if (e.target && e.target.id === 'refundInvoiceSearch') {
                renderRefundInvoiceList();
            }
        });
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('.refund-invoice-pick');
            if (!btn) return;
            const saleItemId = parseInt(btn.getAttribute('data-sale-item-id') || '0', 10);
            const inv = refundInvoiceState.invoices.find(function (row) { return parseInt(row.sale_item_id, 10) === saleItemId; });
            if (!inv) return;
            const modalEl = document.getElementById('refundInvoiceModal');
            if (modalEl && window.bootstrap) {
                const modal = bootstrap.Modal.getInstance(modalEl);
                if (modal) modal.hide();
            }
            posCartAdd({
                add_item: '1',
                product_id: String(inv.product_id || refundInvoiceState.productId),
                custom_price: String(inv.price || ''),
                is_refund: '1',
                refund_sale_item_id: String(inv.sale_item_id)
            });
        });
        function posCartAdd(fields) {
            const body = new FormData();
            body.set('ajax', '1');
            Object.keys(fields).forEach(function (k) { body.set(k, fields[k]); });
            fetch('index.php?page=pos', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
                body: body
            }).then(posCartParse).then(applyCartResponse).catch(function () {
                AppAlert.notify({ icon: 'error', text: 'Unable to update the cart.' });
            });
        }
        function showOnlineTabDetails(id) { let template = document.getElementById('online-tab-data-' + id); let container = document.getElementById('onlineTabDetailContainer'); if (template && container) { container.innerHTML = template.innerHTML; } }
        function openOnlineSettleModal(tabId, total, customerName) { document.getElementById('settle_tab_id_input').value = tabId; switchModal('onlineTabsModal', '', function() { initCheckout(true, total, customerName, 0, tabId); let pmSelect = document.querySelector('select[name="payment_method"]'); if(pmSelect) { let onlineOpt = Array.from(pmSelect.options).find(opt => opt.value === 'Online' || opt.value === 'Card'); if(onlineOpt) onlineOpt.selected = true; } }); }
        function escapeJS(str) { if(!str) return ''; return str.toString().replace(/'/g, "\\'").replace(/"/g, '\\"'); }
        let currentCat = 'all';
        const subCategoriesMap = <?= json_encode($subCatsByParent) ?>;
        const catParents = <?= json_encode($catParents) ?>;
        const catNames = <?= json_encode($catNames) ?>;
        const iconMap = {'whiskey bottles': 'bi-droplet-half', 'whiskey tots': 'bi-cup', 'ciders': 'bi-cup-straw', 'wines and creams': 'bi-cup-fill', 'softies': 'bi-cup-straw', 'lagers': 'bi-cup-straw', 'mixers': 'bi-cup-straw', 'mineral water': 'bi-water'};

        function showMainGrid() { document.getElementById('main-category-grid').style.display = 'block'; document.getElementById('sub-category-grid').style.display = 'none'; document.getElementById('product-list-wrapper').style.display = 'none'; document.getElementById('drilldown-header').style.display = 'none'; document.getElementById('search').value = ''; currentCat = 'all'; applyFilters(); }
        function showSubCategories(categoryId, categoryName) { document.getElementById('main-category-grid').style.display = 'none'; document.getElementById('product-list-wrapper').style.display = 'none'; let container = document.getElementById('subCategoryContainer'); let parentOfCurrent = catParents[categoryId] || null; let parentName = parentOfCurrent && catNames[parentOfCurrent] ? escapeJS(catNames[parentOfCurrent]) : ''; let safeCatName = escapeJS(categoryName); let backAction = parentOfCurrent ? `showSubCategories('${parentOfCurrent}', '${parentName}')` : `showMainGrid()`; container.innerHTML = `<div class="col-4 col-md-3 col-lg-2"><div class="cat-card h-100 shadow-sm border-secondary" onclick="filterItems('${categoryId}', 'All ${safeCatName}', true, '${categoryId}', '${safeCatName}')"><i class="bi bi-grid cat-icon text-secondary"></i><span class="small text-uppercase">All ${categoryName}</span></div></div>`; let subs = subCategoriesMap[categoryId] || []; subs.forEach(sub => { let hasSubs = subCategoriesMap[sub.id] && subCategoriesMap[sub.id].length > 0; let safeSubName = escapeJS(sub.name); if (hasSubs) { container.innerHTML += `<div class="col-4 col-md-3 col-lg-2"><div class="cat-card h-100 shadow-sm border-info bg-info bg-opacity-10" onclick="showSubCategories('${sub.id}', '${safeSubName}')"><i class="bi bi-folder-fill cat-icon text-info"></i><span class="small text-uppercase fw-bold text-info">${sub.name}</span></div></div>`; } else { let icon = iconMap[sub.name.toLowerCase()] || 'bi-arrow-return-right'; container.innerHTML += `<div class="col-4 col-md-3 col-lg-2"><div class="cat-card h-100 shadow-sm border-info" onclick="filterItems('${sub.id}', '${safeSubName}', true, '${categoryId}', '${safeCatName}')"><i class="bi ${icon} cat-icon text-info"></i><span class="small text-uppercase">${sub.name}</span></div></div>`; } }); document.getElementById('sub-category-grid').style.display = 'block'; document.getElementById('drilldown-header').style.display = 'flex'; document.getElementById('currentCategoryLabel').innerText = categoryName; document.getElementById('backBtn').setAttribute('onclick', backAction); }
        function filterItems(id, name, fromSub = false, parentId = null, parentName = '') { document.getElementById('main-category-grid').style.display = 'none'; document.getElementById('sub-category-grid').style.display = 'none'; document.getElementById('product-list-wrapper').style.display = 'flex'; document.getElementById('drilldown-header').style.display = 'flex'; document.getElementById('currentCategoryLabel').innerText = name; if (fromSub && parentId) { document.getElementById('backBtn').setAttribute('onclick', `showSubCategories('${parentId}', '${escapeJS(parentName)}')`); } else { document.getElementById('backBtn').setAttribute('onclick', 'showMainGrid()'); } currentCat = id.toString(); document.getElementById('items-grid').style.display = 'flex'; document.getElementById('services-grid').style.display = 'none'; document.getElementById('paginationBar').style.display = 'flex'; applyFilters(); }
        function switchTab(tab, name) { document.getElementById('main-category-grid').style.display = 'none'; document.getElementById('sub-category-grid').style.display = 'none'; document.getElementById('product-list-wrapper').style.display = 'flex'; document.getElementById('drilldown-header').style.display = 'flex'; document.getElementById('currentCategoryLabel').innerText = name; document.getElementById('backBtn').setAttribute('onclick', 'showMainGrid()'); document.getElementById('items-grid').style.display = 'none'; document.getElementById('services-grid').style.display = 'flex'; document.getElementById('paginationBar').style.display = 'none'; }
        function printTabBill(saleId) { document.getElementById("receiptFrame").src = "about:blank"; setTimeout(() => { document.getElementById("receiptFrame").src = "index.php?page=receipt&sale_id=" + saleId + "&is_bill=1&_cb=" + new Date().getTime(); }, 100); safeModalShow("receiptModal"); }
        function highlightTabSelection(selectedLabel) { document.querySelectorAll('.tab-radio-label').forEach(el => el.classList.remove('active')); selectedLabel.classList.add('active'); let radio = selectedLabel.querySelector('input[type="radio"]'); if (radio.value === 'new') { document.getElementById('newTabNameInput').style.display = 'block'; } else { document.getElementById('newTabNameInput').style.display = 'none'; } }
        function toggleRefundMode() { let isRefund = document.getElementById('refundToggle').checked; document.querySelectorAll('.refund-input').forEach(el => el.value = isRefund ? '1' : '0'); let header = document.getElementById('mainHeader'); if (isRefund) { header.classList.remove('bg-dark', 'border-warning'); header.classList.add('bg-danger', 'border-dark'); document.getElementById('headerPosLabel').innerText = 'REFUND MODE'; document.getElementById('headerPosLabel').classList.replace('text-warning', 'text-white'); document.getElementById('headerLocIcon').classList.replace('text-warning', 'text-white'); document.getElementById('cartHeader').classList.replace('bg-dark', 'bg-danger'); AppAlert.notify({ icon: 'info', title: 'Refund mode', text: 'Tap a product, then choose the original invoice. Do not refund from the grid alone.' }); } else { header.classList.add('bg-dark', 'border-warning'); header.classList.remove('bg-danger', 'border-dark'); document.getElementById('headerPosLabel').innerText = 'POS'; document.getElementById('headerPosLabel').classList.replace('text-white', 'text-warning'); document.getElementById('headerLocIcon').classList.replace('text-white', 'text-warning'); document.getElementById('cartHeader').classList.replace('bg-danger', 'bg-dark'); } }
        function pushToCart(productId, price, saleItemId = 0, tabStatus = 'paid') { let isRefund = isPosRefundMode(); if (isRefund && tabStatus === 'pending') { AppAlert.notify({ icon: 'error', title: 'Payment required', text: 'This table has not been paid. Use Void to remove items.' }); return; } if (isRefund && !(parseInt(saleItemId, 10) > 0)) { openRefundInvoicePicker(productId, ''); return; } posCartAdd({ add_item: '1', product_id: productId, custom_price: price, is_refund: isRefund ? '1' : '0', refund_sale_item_id: saleItemId }); }
        function logWasteAuth() { Swal.fire({ title: 'Log waste', html: '<p class="text-muted small">Manager authorization is required.</p><input id="swal-w-user" class="swal2-input" placeholder="Username"><input id="swal-w-pass" type="password" class="swal2-input" placeholder="Password">', icon: 'warning', showCancelButton: true, reverseButtons: true, confirmButtonColor: '#d33', confirmButtonText: 'Authorize', cancelButtonText: 'Cancel', preConfirm: () => { return { user: document.getElementById('swal-w-user').value, pass: document.getElementById('swal-w-pass').value } } }).then((result) => { if (result.isConfirmed) { if(!result.value.user || !result.value.pass) { AppAlert.notify({icon:'error', title:'Credentials required', text:'Enter a manager username and password.'}); return; } let f = document.createElement('form'); f.method = 'POST'; f.innerHTML = `<input type="hidden" name="log_waste" value="1"><input type="hidden" name="mgr_user" value="${result.value.user}"><input type="hidden" name="mgr_pass" value="${result.value.pass}">`; document.body.appendChild(f); f.submit(); } }); }
        function safeModalShow(id) { let el = document.getElementById(id); if (!el) return; try { let m = bootstrap.Modal.getInstance(el); if (!m) { m = new bootstrap.Modal(el); } m.show(); } catch(e) { el.classList.add('show'); el.style.display = 'block'; } }
        function switchModal(closeId, openId, callback = null) { let closeEl = document.getElementById(closeId); let mClose = bootstrap.Modal.getInstance(closeEl); if (mClose) mClose.hide(); else if (closeEl) { closeEl.classList.remove('show'); closeEl.style.display = 'none'; } setTimeout(() => { document.querySelectorAll('.modal-backdrop').forEach(el => el.remove()); document.body.classList.remove('modal-open'); document.body.style = ''; if (callback) callback(); if(openId !== '') { safeModalShow(openId); } }, 350); }
        function showPickupModal() { document.getElementById('pickupFrame').src = "index.php?page=pickup&embedded=1"; safeModalShow('pickupModal'); }
        function openTransferModal(tabId) { document.getElementById('transfer_source_id').value = tabId; document.querySelectorAll('.transfer-target-item').forEach(el => { if(el.dataset.id == tabId) el.style.display = 'none'; else el.style.display = 'block'; }); let firstLabel = document.querySelector('#transferTargetContainer .tab-radio-label'); if(firstLabel) { highlightTransferSelection(firstLabel); firstLabel.querySelector('input').checked = true; } let itemsHtml = ''; document.querySelectorAll(`#tab-data-${tabId} tr[id^="item-row-"]`).forEach(row => { let itemId = row.id.replace('item-row-', ''); let itemText = row.cells[0].innerText; itemsHtml += `<label class="list-group-item d-flex align-items-center border-bottom"><input class="form-check-input me-3 transfer-checkbox border-info" type="checkbox" name="transfer_item_ids[]" value="${itemId}"><span class="small fw-bold">${itemText}</span></label>`; }); document.getElementById('transferItemsContainer').innerHTML = itemsHtml; switchModal('tabsModal', 'transferModal'); }
        function highlightTransferSelection(selectedLabel) { document.querySelectorAll('#transferTargetContainer .tab-radio-label').forEach(el => el.classList.remove('active')); selectedLabel.classList.add('active'); let radio = selectedLabel.querySelector('input[type="radio"]'); if (radio && radio.value === 'new') { document.getElementById('transferNewTabInput').style.display = 'block'; } else { document.getElementById('transferNewTabInput').style.display = 'none'; } }
        function toggleTransferSelectAll() { let checkboxes = document.querySelectorAll('.transfer-checkbox'); let allChecked = Array.from(checkboxes).every(c => c.checked); checkboxes.forEach(c => c.checked = !allChecked); }
        function showTabDetails(id) { let template = document.getElementById('tab-data-' + id); let container = document.getElementById('tabDetailContainer'); if (template && container) { container.innerHTML = template.innerHTML; } }
        function voidItem(itemId) { Swal.fire({ title: 'Void item', html: '<p class="text-muted small">Manager authorization is required.</p><input id="swal-user" class="swal2-input" placeholder="Username"><input id="swal-pass" type="password" class="swal2-input" placeholder="Password">', icon: 'warning', showCancelButton: true, reverseButtons: true, confirmButtonColor: '#d33', confirmButtonText: 'Authorize', cancelButtonText: 'Cancel', preConfirm: () => { return { user: document.getElementById('swal-user').value, pass: document.getElementById('swal-pass').value } } }).then((result) => { if (result.isConfirmed) { if(!result.value.user || !result.value.pass) { AppAlert.notify({icon:'error', title:'Credentials required', text:'Enter a manager username and password.'}); return; } fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'void_item=1&item_id=' + itemId + '&mgr_user=' + encodeURIComponent(result.value.user) + '&mgr_pass=' + encodeURIComponent(result.value.pass) }).then(r => r.json()).then(data => { if (data.status === 'success') { AppAlert.notify({ icon: 'success', title: 'Item voided', text: '', confirmText: 'OK' }).then(() => location.reload()); } else { AppAlert.notify({ icon: 'error', title: 'Unable to void', text: data.msg || 'The item could not be voided.' }); } }); } }); }

        let baseTotal = 0; let currentTotal = 0; let tenderedTouched = false;
        
        function initCheckout(isTabMode = false, tabTotal = 0, tabCustomer = '', pt = 0, forceTabId = 0) { 
            let nameInput = document.querySelector('[name="customer_name"]');
            if (!isTabMode) { baseTotal = window.posCartBalance || 0; document.getElementById('settle_tab_id_input').value = '0'; nameInput.value = <?= json_encode((string)($_SESSION['current_customer'] ?? 'Walk-in')) ?>; nameInput.readOnly = false; } else { baseTotal = parseFloat(tabTotal) - parseFloat(pt); nameInput.value = tabCustomer; nameInput.readOnly = true; if (forceTabId > 0) { document.getElementById('settle_tab_id_input').value = forceTabId; } }
            currentTotal = baseTotal; tenderedTouched = false; document.getElementById('tipInput').value = '';
            resetSplitRows();
            const checkoutForm = document.getElementById('checkoutForm');
            if (checkoutForm) checkoutForm.dataset.swalConfirmed = '';
            if (currentTotal < 0) { document.getElementById('checkoutHeader').classList.replace('bg-warning', 'bg-danger'); document.getElementById('btnCheckoutSubmit').classList.replace('btn-warning', 'btn-danger'); document.getElementById('btnCheckoutSubmit').innerText = 'ISSUE REFUND'; document.getElementById('displayTotalDue').classList.replace('text-dark', 'text-danger'); document.getElementById('refundAuthSection').style.display = 'block'; document.getElementById('mgrUserRefund').required = true; document.getElementById('mgrPassRefund').required = true; let rfdOn = document.getElementById('rfdRsnCd'); if (rfdOn) rfdOn.required = true; if (checkoutForm) { checkoutForm.dataset.swalTitle = 'Issue this refund?'; checkoutForm.dataset.swalText = 'Stock will be restored and a ZRA credit note will be posted against the original invoice.'; checkoutForm.dataset.swalConfirm = 'Issue refund'; checkoutForm.dataset.swalConfirmColor = '#dc3545'; } } else { document.getElementById('checkoutHeader').classList.replace('bg-danger', 'bg-warning'); document.getElementById('btnCheckoutSubmit').classList.replace('btn-danger', 'btn-warning'); document.getElementById('btnCheckoutSubmit').innerText = 'COMPLETE TRANSACTION'; document.getElementById('displayTotalDue').classList.replace('text-danger', 'text-dark'); document.getElementById('refundAuthSection').style.display = 'none'; document.getElementById('mgrUserRefund').required = false; document.getElementById('mgrPassRefund').required = false; let rfdOff = document.getElementById('rfdRsnCd'); if (rfdOff) rfdOff.required = false; if (checkoutForm) { checkoutForm.dataset.swalTitle = 'Complete this sale?'; checkoutForm.dataset.swalText = 'Payment will be recorded and the receipt will be sent to ZRA.'; checkoutForm.dataset.swalConfirm = 'Complete'; checkoutForm.dataset.swalConfirmColor = '#fd7e14'; } }
            if(document.getElementById('discountToggle')) { document.getElementById('discountToggle').checked = false; toggleDiscount(); } else { updateDisplays(); } safeModalShow('checkoutModal'); 
        }

        function openSettleModal(tabId, total, customerName, pt = 0) { document.getElementById('settle_tab_id_input').value = tabId; switchModal('tabsModal', '', function() { initCheckout(true, total, customerName, pt, tabId); }); }
        function markCollected(itemId, saleId) { AppAlert.confirm({ title: 'Mark collected?', text: 'This item will be handed to the customer.', confirmText: 'Collected', cancelText: 'Cancel', icon: 'question', confirmColor: '#198754' }).then(function (ok) { if (!ok) return; fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'mark_collected=1&item_id=' + itemId }).then(r => r.json()).then(data => { if (data.status === 'success') { if (data.print_receipt) { document.getElementById('receiptFrame').src = "index.php?page=receipt&sale_id=" + data.sale_id + "&collection_only=" + data.item_id + "&_t=" + new Date().getTime(); safeModalShow('receiptModal'); } const activeRow = document.getElementById('tabDetailContainer').querySelector('#item-row-' + itemId); if(activeRow) activeRow.querySelector('.text-end').innerHTML = '<span class="badge bg-success">COLLECTED</span>'; const templateRow = document.getElementById('hiddenTabTemplates').querySelector('#item-row-' + itemId); if(templateRow) templateRow.querySelector('.text-end').innerHTML = '<span class="badge bg-success">COLLECTED</span>'; } else if (data.status === 'redirect_pickup') { AppAlert.confirm({ icon: 'info', title: 'Collect at pickup', text: data.msg || 'This order is ready on the pickup screen.', confirmText: 'Open pickup', cancelText: 'Close' }).then((ok) => { if (ok) { switchModal('tabsModal', '', showPickupModal); } }); } else { AppAlert.notify({ icon: 'error', title: 'Unable to collect', text: data.msg || 'This action could not be completed.' }); } }); }
        function currentTip() { return parseFloat(document.getElementById('tipInput').value) || 0; }
        function amountDue() { return currentTotal + currentTip(); }
        function isSplitPay() { let el = document.getElementById('modeSplit'); return !!(el && el.checked); }
        function toggleDiscount() { let chk = document.getElementById('discountToggle'); if(chk && chk.checked) { currentTotal = baseTotal * 0.90; document.getElementById('discountLabel').style.display = 'block'; } else { currentTotal = baseTotal; document.getElementById('discountLabel').style.display = 'none'; } tenderedTouched = false; updateDisplays(); }
        function updateDisplays() {
            let due = amountDue();
            document.getElementById('displayTotalDue').innerText = due.toFixed(2);
            if (!isSplitPay() && !tenderedTouched) {
                document.getElementById('tenderedInput').value = due.toFixed(2);
            }
            calcResult();
        }
        function toggleMode() { let isSplit = isSplitPay(); document.getElementById('singleSection').style.display = isSplit ? 'none' : 'block'; document.getElementById('splitSection').style.display = isSplit ? 'block' : 'none'; if(isSplit) { resetSplitRows(); document.getElementById('tenderedInput').value = "0.00"; tenderedTouched = true; } else { tenderedTouched = false; document.getElementById('tenderedInput').value = amountDue().toFixed(2); } calcResult(); }
        function splitMethodOptions(selected) {
            const opts = ['Cash', 'Card', 'MTN Money', 'Airtel Money', 'Zamtel Money', 'Online'];
            return opts.map(v => `<option value="${v}" ${v === selected ? 'selected' : ''}>${v}</option>`).join('')
                + `<option value="__custom__">Other (custom name)</option>`;
        }
        function addSplitRow(method, amount, custom) {
            const wrap = document.getElementById('splitRows');
            if (!wrap || wrap.children.length >= 8) return;
            method = method || (wrap.children.length === 0 ? 'Card' : 'Cash');
            const row = document.createElement('div');
            row.className = 'split-pay-row mb-2';
            row.innerHTML = `<div class="row g-2 align-items-center">
                <div class="col-5"><select name="split_method[]" class="form-select fw-bold split-method" onchange="onSplitMethodChange(this)">${splitMethodOptions(method)}</select></div>
                <div class="col-5"><div class="input-group"><span class="input-group-text bg-white">ZMW</span><input type="number" step="0.01" min="0" name="split_amount[]" class="form-control fw-bold split-amount" placeholder="0.00" value="${amount ? Number(amount).toFixed(2) : ''}" oninput="sumSplit()"></div></div>
                <div class="col-2"><button type="button" class="btn btn-outline-danger w-100 split-remove" onclick="removeSplitRow(this)" title="Remove">&times;</button></div>
                <div class="col-12 split-custom-wrap" style="display:none;"><input type="text" name="split_custom[]" class="form-control split-custom" maxlength="50" placeholder="Custom method name"></div>
            </div>`;
            wrap.appendChild(row);
            const sel = row.querySelector('.split-method');
            const known = ['Cash','Card','MTN Money','Airtel Money','Zamtel Money','Online'];
            if (method === '__custom__' || (custom && !known.includes(method)) || (method && !known.includes(method))) {
                sel.value = '__custom__';
                row.querySelector('.split-custom').value = custom || method;
            }
            onSplitMethodChange(sel);
            refreshSplitRemoveButtons();
        }
        function resetSplitRows() {
            const wrap = document.getElementById('splitRows');
            if (!wrap) return;
            wrap.innerHTML = '';
            addSplitRow('Card');
            addSplitRow('Cash');
        }
        function removeSplitRow(btn) {
            const wrap = document.getElementById('splitRows');
            if (!wrap || wrap.children.length <= 1) return;
            btn.closest('.split-pay-row').remove();
            refreshSplitRemoveButtons();
            sumSplit();
        }
        function refreshSplitRemoveButtons() {
            const rows = document.querySelectorAll('#splitRows .split-pay-row');
            rows.forEach(row => {
                const b = row.querySelector('.split-remove');
                if (b) b.disabled = rows.length <= 1;
            });
        }
        function onSplitMethodChange(sel) {
            const wrap = sel.closest('.split-pay-row').querySelector('.split-custom-wrap');
            const input = wrap.querySelector('.split-custom');
            const isCustom = sel.value === '__custom__';
            wrap.style.display = isCustom ? 'block' : 'none';
            input.required = isCustom;
            if (!isCustom) input.value = '';
        }
        function sumSplit() {
            let total = 0;
            document.querySelectorAll('#splitRows .split-amount').forEach(el => { total += parseFloat(el.value) || 0; });
            document.getElementById('tenderedInput').value = total.toFixed(2);
            tenderedTouched = true;
            calcResult();
        }
        function addTipPercent(percent) { document.getElementById('tipInput').value = (Math.max(0, currentTotal) * percent).toFixed(2); if (!isSplitPay()) tenderedTouched = false; updateDisplays(); }
        function onTipChange() { updateDisplays(); }
        function calcResult() {
            let due = amountDue();
            document.getElementById('displayTotalDue').innerText = due.toFixed(2);
            let tendered = parseFloat(document.getElementById('tenderedInput').value) || 0;
            let diff = tendered - due;
            let label = document.getElementById('resultLabel');
            let value = document.getElementById('resultValue');
            if (currentTotal < 0) {
                label.innerText = "CASH OUT OF DRAWER";
                label.className = "small fw-bold text-uppercase text-danger";
                value.innerText = "ZMW " + Math.abs(currentTotal).toFixed(2);
                value.className = "fs-4 fw-bold text-danger";
            } else if (diff >= -0.01) {
                label.innerText = "CHANGE DUE";
                label.className = "small fw-bold text-uppercase text-muted";
                value.innerText = "ZMW " + diff.toFixed(2);
                value.className = "fs-4 fw-bold text-dark";
            } else {
                label.innerText = "BALANCE REMAINING";
                label.className = "small fw-bold text-uppercase text-danger";
                value.innerText = "ZMW " + Math.abs(diff).toFixed(2);
                value.className = "fs-4 fw-bold text-danger";
            }
        }

        function getAllDescendants(catId) { let desc = []; let subs = subCategoriesMap[catId] || []; subs.forEach(s => { desc.push(s.id.toString()); desc = desc.concat(getAllDescendants(s.id)); }); return desc; }
        function filter() { document.getElementById('main-category-grid').style.display = 'none'; document.getElementById('sub-category-grid').style.display = 'none'; document.getElementById('product-list-wrapper').style.display = 'flex'; document.getElementById('drilldown-header').style.display = 'flex'; document.getElementById('currentCategoryLabel').innerText = "Search Results"; document.getElementById('backBtn').setAttribute('onclick', 'showMainGrid()'); currentCat = 'all'; applyFilters(); }
        function applyFilters() { let v = document.getElementById('search').value.toLowerCase(); let showInStockOnly = false; let toggleElement = document.getElementById('inStockToggle'); if (toggleElement) { showInStockOnly = toggleElement.checked; localStorage.setItem('posInStockToggle', showInStockOnly); } let allItems = Array.from(document.querySelectorAll('#items-grid .item')); let validCats = [currentCat]; if (currentCat !== 'all') { validCats = validCats.concat(getAllDescendants(currentCat)); } activeItems = allItems.filter(e => { let matchCat = (currentCat === 'all' || validCats.includes(e.dataset.cat.toString())); let matchName = e.dataset.name.includes(v); let matchStock = true; if (showInStockOnly) { matchStock = e.dataset.out === "0"; } return matchCat && matchName && matchStock; }); renderPage(1); }

        let currentPage = 1; const itemsPerPage = 24; let activeItems = [];
        document.addEventListener('DOMContentLoaded', function() { 
            if (window.bootstrap && bootstrap.Tooltip) {
                document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) { new bootstrap.Tooltip(el); });
            }
            if (localStorage.getItem('posInStockToggle') === 'true') { let toggle = document.getElementById('inStockToggle'); if(toggle) toggle.checked = true; }
            if (isMobilePos()) { setCartExpanded(sessionStorage.getItem('posCartExpanded') === '1'); }
            window.addEventListener('resize', function () {
                const panel = document.getElementById('cartPanel');
                const open = !!(panel && panel.classList.contains('expanded'));
                document.querySelector('.workspace')?.classList.toggle('cart-open', open && isMobilePos());
            });
            initPagination(); 
            <?php if(isset($_SESSION["last_sale_id"])): ?> document.getElementById("receiptFrame").src = "about:blank"; setTimeout(() => { document.getElementById("receiptFrame").src = "index.php?page=receipt&sale_id=<?= $_SESSION["last_sale_id"] ?>&_cb=" + new Date().getTime(); }, 100); safeModalShow("receiptModal"); <?php unset($_SESSION["last_sale_id"]); endif; ?> 
            <?php if(isset($_SESSION["last_bill_id"])): ?> document.getElementById("receiptFrame").src = "about:blank"; setTimeout(() => { document.getElementById("receiptFrame").src = "index.php?page=receipt&sale_id=<?= $_SESSION["last_bill_id"] ?>&is_bill=1&items=<?= $_SESSION["last_added_item_ids"] ?? "" ?>&_cb=" + new Date().getTime(); }, 100); safeModalShow("receiptModal"); <?php unset($_SESSION["last_bill_id"], $_SESSION["last_added_item_ids"]); endif; ?>
            let pickupMod = document.getElementById('pickupModal'); if(pickupMod) { pickupMod.addEventListener('hidden.bs.modal', function () { Swal.fire({ title: 'Refreshing tabs…', showConfirmButton: false, timer: 700, timerProgressBar: true }); setTimeout(() => { location.reload(); }, 700); }); } 
        });
        function showShiftReport(shiftId) { document.getElementById("reportTitle").innerText = "X-Read (Open Shift)"; document.getElementById("reportFrame").src = "about:blank"; setTimeout(() => { document.getElementById("reportFrame").src = "index.php?page=print_shift&shift_id=" + shiftId + "&_cb=" + new Date().getTime(); }, 100); safeModalShow("reportModal"); }
        function initPagination() { applyFilters(); }
        function renderPage(page) { currentPage = page; const totalPages = Math.ceil(activeItems.length / itemsPerPage) || 1; if (currentPage > totalPages) currentPage = totalPages; if (currentPage < 1) currentPage = 1; const startIndex = (currentPage - 1) * itemsPerPage; const endIndex = startIndex + itemsPerPage; document.querySelectorAll('#items-grid .item').forEach(el => el.style.display = 'none'); activeItems.slice(startIndex, endIndex).forEach(el => { el.style.display = 'block'; }); document.getElementById('pageInfo').innerText = `Page ${currentPage} of ${totalPages}`; }
        function prevPage() { if(currentPage > 1) renderPage(currentPage - 1); } function nextPage() { const totalPages = Math.ceil(activeItems.length / itemsPerPage); if(currentPage < totalPages) renderPage(currentPage + 1); }
        function isMobilePos() { return window.matchMedia('(max-width: 768px)').matches; }
        function setCartExpanded(open) {
            const panel = document.getElementById('cartPanel');
            if (!panel) return;
            panel.classList.toggle('expanded', !!open);
            document.querySelector('.workspace')?.classList.toggle('cart-open', !!open && isMobilePos());
            if (isMobilePos()) sessionStorage.setItem('posCartExpanded', open ? '1' : '0');
            const btn = panel.querySelector('.cart-compact');
            if (btn) {
                btn.setAttribute('aria-expanded', open ? 'true' : 'false');
                const label = btn.querySelector('.cart-toggle-label');
                if (label) label.innerHTML = open ? '<i class="bi bi-chevron-down"></i> Hide' : '<i class="bi bi-chevron-up"></i> Basket';
            }
        }
        function toggleCart() {
            const panel = document.getElementById('cartPanel');
            setCartExpanded(!(panel && panel.classList.contains('expanded')));
        }
        function addService(id, name, price, isOpen) { if (isPosRefundMode()) { openRefundInvoicePicker(id, name); return; } let isRefund = '0'; if (isOpen) { document.getElementById('op_pid').value = id; document.getElementById('op_name').innerText = name; document.getElementById('op_refund').value = isRefund; safeModalShow('openPriceModal'); } else { posCartAdd({ add_item: '1', product_id: id, is_refund: isRefund }); } }
        <?php if(isset($_SESSION['swal_msg']) || isset($_SESSION['swal_html'])): ?> AppAlert.notify({ icon: <?= json_encode($_SESSION['swal_type'] ?? 'info') ?>, title: <?= json_encode($_SESSION['swal_title'] ?? '') ?>, text: <?= json_encode((string)($_SESSION['swal_msg'] ?? '')) ?>, html: <?= json_encode((string)($_SESSION['swal_html'] ?? '')) ?>, confirmText: <?= json_encode($_SESSION['swal_confirm'] ?? '') ?>, cancelText: <?= json_encode($_SESSION['swal_cancel'] ?? '') ?>, url: <?= json_encode($_SESSION['swal_url'] ?? '') ?> }); <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg'], $_SESSION['swal_title'], $_SESSION['swal_confirm'], $_SESSION['swal_cancel'], $_SESSION['swal_url'], $_SESSION['swal_html']); endif; ?>
        <?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?> function checkPosReadyOrders() { fetch('index.php?action=check_ready_orders').then(r => r.json()).then(data => { let badge = document.getElementById('posReadyBadge'); if(badge && data && data.count > 0) { badge.innerText = data.count; badge.style.display = 'block'; } else if (badge) { badge.style.display = 'none'; } }).catch(e => { console.error('POS Badge Error:', e); }); } checkPosReadyOrders(); setInterval(checkPosReadyOrders, 5000); <?php endif; ?>
    </script>
<script>
    document.addEventListener('focusin', function(e) { if (typeof Swal !== 'undefined' && Swal.isVisible()) { let swalContainer = document.querySelector('.swal2-container'); if (swalContainer && swalContainer.contains(e.target)) { e.stopImmediatePropagation(); } } }, true);
</script>
</body>
</html>
