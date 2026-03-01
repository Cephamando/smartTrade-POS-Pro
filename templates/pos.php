<!DOCTYPE html> 
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>POS - <?= htmlspecialchars($_SESSION['location_name'] ?? 'HQ') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* DYNAMIC THEME SETTINGS */
        :root {
            --pos-main-color: <?= htmlspecialchars($sysSettings['theme_color'] ?? '#2c2c2c') ?>;
            --pos-accent-color: <?= htmlspecialchars($sysSettings['theme_accent'] ?? '#ffc107') ?>;
            --pos-cart-color: <?= htmlspecialchars($sysSettings['theme_cart'] ?? '#3e2723') ?>;
        }
        
        /* CORE LAYOUT */
        body { background-color: #f0f2f5; height: 100vh; overflow: hidden; display: flex; flex-direction: column; margin: 0; transition: background-color 0.3s; }
        .header-custom { background-color: var(--pos-main-color) !important; border-bottom: 4px solid var(--pos-accent-color) !important; color: white; flex: 0 0 auto; z-index: 1050; transition: background-color 0.3s, border-color 0.3s; }
        #headerPosLabel, #headerLocIcon { color: var(--pos-accent-color) !important; }
        .workspace { flex: 1 1 auto; display: flex; overflow: hidden; position: relative; min-height: 0; }
        .product-section { flex: 1 1 auto; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        
        /* DRILL-DOWN CARDS */
        .cat-card { background: #fff; border: 2px solid #e0e0e0; border-radius: 12px; padding: 20px 10px; text-align: center; cursor: pointer; transition: 0.2s; font-weight: bold; color: #333; display: flex; flex-direction: column; align-items: center; justify-content: center; user-select: none; }
        .cat-card:hover { border-color: var(--pos-accent-color) !important; transform: translateY(-3px); box-shadow: 0 6px 12px rgba(0,0,0,0.1); }
        .cat-card.active { background: var(--pos-cart-color) !important; border-color: var(--pos-accent-color) !important; color: #fff; }
        .cat-icon { font-size: 2rem; margin-bottom: 8px; }

        /* PRODUCT GRID */
        .product-list-wrapper { flex: 1 1 auto; display: flex; flex-direction: column; overflow: hidden; }
        .product-list { flex: 1 1 auto; overflow-y: auto; padding: 15px; }
        .pagination-bar { flex: 0 0 auto; background: #fff; border-top: 1px solid #ddd; padding: 10px 15px; display: flex; justify-content: center; align-items: center; gap: 15px; }
        .item-card { background: white; border: 1px solid #e0e0e0; border-radius: 8px; transition: transform 0.1s, box-shadow 0.1s; cursor: pointer; overflow: hidden; position: relative; height: 100%; display: block; width: 100%; text-align: left; }
        .item-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-color: var(--pos-accent-color) !important; }
        .item-card:active { transform: scale(0.98); }
        .item-card:disabled, .item-card[disabled] { opacity: 0.4 !important; filter: grayscale(100%) !important; background-color: #e9ecef !important; cursor: not-allowed !important; pointer-events: none !important; box-shadow: none !important; border-color: #ddd !important; }
        .stock-badge { position: absolute; top: 8px; right: 8px; font-size: 0.75rem; padding: 4px 8px; border-radius: 4px; font-weight: bold; z-index: 2; }
        .bg-low { background-color: #dc3545; color: white; }
        .bg-ok { background-color: #198754; color: white; }
        .bg-recipe { background-color: #0dcaf0; color: #000; }
        
        /* CART PANEL */
        .cart-panel { flex: 0 0 400px; width: 400px; background: #fff; border-left: 1px solid #ccc; display: flex; flex-direction: column; box-shadow: -4px 0 15px rgba(0,0,0,0.1); z-index: 1000; height: 100%; }
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
        
        /* TABLES */
        .table-box { border-radius: 12px; height: 120px; display: flex; flex-direction: column; justify-content: center; align-items: center; border: 2px solid; cursor: pointer; transition: 0.2s; position: relative; overflow: hidden; }
        .table-box:hover { transform: translateY(-3px); box-shadow: 0 5px 15px rgba(0,0,0,0.15); }
        .table-available { background: #e8f5e9; border-color: #4caf50; color: #2e7d32; }
        .table-occupied { background: #ffebee; border-color: #f44336; color: #c62828; }
        .table-capacity { position: absolute; top: 5px; right: 8px; font-size: 0.7rem; font-weight: bold; opacity: 0.7; }
        .tab-radio-label { cursor: pointer; transition: 0.2s; }
        .tab-radio-label:hover { background-color: #f8f9fa; }
        .tab-radio-label.active { background-color: var(--pos-accent-color) !important; color: #000 !important; border-color: var(--pos-accent-color) !important; font-weight: bold; }
        
        @media (max-width: 991px) { .cart-panel { flex: 0 0 340px; width: 340px; } }
        @media (max-width: 768px) { .workspace { flex-direction: column; } .cart-panel { position: absolute; bottom: 0; left: 0; right: 0; width: 100%; height: 70px; max-height: 70px; transition: height 0.3s, max-height 0.3s; border-top: 4px solid var(--pos-cart-color) !important; flex: 0 0 auto; } .cart-panel.expanded { height: 85vh; max-height: 85vh; } }
    </style>
</head>
<body>
    <?php if ($locationId == 0): ?><div class="modal fade show" id="compulsoryLocationModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.9); z-index: 1060; position: fixed; top: 0; left: 0; width: 100%; height: 100%;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-warning"><div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold">Select Workstation</h5></div><div class="modal-body bg-light p-4"><form method="POST"><?php foreach($sellableLocations as $loc): ?><button name="set_pos_location" value="<?= $loc['id'] ?>" class="btn btn-white border w-100 mb-2 py-3 fw-bold text-start shadow-sm"><?= htmlspecialchars($loc['name']) ?></button><?php endforeach; ?></form></div></div></div></div><?php endif; ?>
    <?php if ($locationId > 0 && $pendingShift): ?><div class="modal fade show" id="pendingShiftModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.8); z-index: 1060; position: fixed; top: 0; left: 0; width: 100%; height: 100%;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-warning"><div class="modal-header bg-warning text-dark"><h5 class="modal-title fw-bold">Awaiting Manager Approval</h5></div><div class="modal-body text-center p-4"><p>Shift #<?= $pendingShift['id'] ?> is pending approval.</p><form method="POST"><input type="hidden" name="approve_shift_start" value="1"><input type="hidden" name="pending_shift_id" value="<?= $pendingShift['id'] ?>"><input type="text" name="mgr_username" class="form-control mb-2" placeholder="Manager Username" required><input type="password" name="mgr_password" class="form-control mb-3" placeholder="Manager Password" required><button type="submit" class="btn btn-warning w-100 fw-bold">APPROVE & START</button></form></div></div></div></div><?php endif; ?>
    <?php if ($locationId > 0 && !$activeShiftId && !$pendingShift): ?><div class="modal fade show" id="startShiftModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.8); z-index: 1060; position: fixed; top: 0; left: 0; width: 100%; height: 100%;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-primary"><div class="modal-header bg-primary text-white"><h5 class="modal-title fw-bold">Start New Shift</h5></div><form method="POST"><div class="modal-body p-4"><input type="hidden" name="request_start_shift" value="1"><label class="fw-bold small text-muted">OPENING FLOAT</label><div class="input-group input-group-lg mb-3"><span class="input-group-text fw-bold">ZMW</span><input type="number" step="0.01" name="starting_cash" class="form-control fw-bold" required placeholder="0.00"></div><button type="submit" class="btn btn-primary w-100 fw-bold py-3 mb-2">REQUEST APPROVAL</button><a href="index.php?page=dashboard" class="btn btn-outline-secondary w-100 fw-bold">GO TO DASHBOARD</a></div></form></div></div></div><?php endif; ?>

    <div class="header-custom p-2 d-flex justify-content-between align-items-center" id="mainHeader">
        <div class="d-flex align-items-center ps-2">
            <span class="fs-5 fw-bold text-warning me-3" id="headerPosLabel">POS</span>
            <span class="text-light ms-2"><i class="bi bi-geo-alt-fill text-warning" id="headerLocIcon"></i> <?= htmlspecialchars($locationName) ?></span>
            <button type="button" class="btn btn-sm btn-link text-warning ms-1 me-2" data-bs-toggle="modal" data-bs-target="#locationModal" title="Change Location"><i class="bi bi-pencil-square"></i></button>
            <span class="text-light border-start border-secondary ps-3"><i class="bi bi-person-circle text-info me-1"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
        </div>
        <div class="d-flex gap-2 pe-2">
            <?php if(in_array($_SESSION['role'] ?? '', ['admin','manager','dev','chef','head_chef']) && defined('LICENSE_TIER') && LICENSE_TIER === 'hospitality'): ?>
                <a href="index.php?page=menu" class="btn btn-outline-success btn-sm fw-bold"><i class="bi bi-list-ul"></i> Menu</a>
                <a href="index.php?page=kitchen" class="btn btn-outline-danger btn-sm fw-bold"><i class="bi bi-fire"></i> Produce</a>
            <?php endif; ?>
            
            <?php if ($activeShiftId): ?>
                <button type="button" class="btn btn-warning text-dark btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="bi bi-cash-stack"></i> Payout</button>
                <button type="button" onclick="showShiftReport(<?= $activeShiftId ?>)" class="btn btn-outline-light btn-sm fw-bold"><i class="bi bi-printer"></i> X-Read</button>
            <?php endif; ?>

            <?php if (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?>
                <button type="button" class="btn btn-outline-warning btn-sm fw-bold position-relative" onclick="showPickupModal()">
                    <i class="bi bi-bag-check"></i> Pickup <span id="posReadyBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none; font-size: 0.6rem; padding: 0.35em 0.5em;">0</span>
                </button>
                <button type="button" class="btn btn-outline-info btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#floorplanModal"><i class="bi bi-grid-3x3-gap-fill"></i> Tables</button>
                <button type="button" class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#tabsModal"><i class="bi bi-receipt"></i> Tabs</button>
            <?php endif; ?>

            <?php if ($activeShiftId): ?>
                <button type="button" class="btn btn-danger btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#endShiftModal"><i class="bi bi-power"></i> End</button>
            <?php else: ?><span class="badge bg-secondary">LOCKED</span><?php endif; ?>
            
            <?php if(in_array($_SESSION['role'] ?? '', ['admin','manager','dev'])): ?>
            <a href="index.php?page=dashboard" class="btn btn-outline-light btn-sm" title="Dashboard"><i class="bi bi-house"></i></a>
            <?php else: ?>
            <a href="index.php?action=logout" class="btn btn-outline-danger btn-sm fw-bold" title="Logout"><i class="bi bi-power"></i> Exit</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="workspace" style="<?= (!$activeShiftId) ? 'filter: blur(5px); pointer-events: none;' : '' ?>">
        <div class="product-section">
            <div class="bg-white p-2 border-bottom d-flex align-items-center gap-3">
                <input type="text" id="search" class="form-control form-control-lg flex-grow-1 border-dark shadow-sm" placeholder="Search product..." onkeyup="filter()">
                <div class="form-check form-switch fs-5 bg-danger-subtle border border-danger rounded px-3 py-1 ms-2" id="refundToggleContainer" style="display: flex; align-items: center; justify-content: center; height: 100%; transition: 0.3s;">
                    <input class="form-check-input border-danger me-2" type="checkbox" id="refundToggle" onchange="toggleRefundMode()" style="cursor:pointer; transform: scale(1.2);">
                    <label class="form-check-label fw-bold text-danger small text-uppercase" for="refundToggle" style="cursor:pointer; letter-spacing: 0.5px;">Return Mode</label>
                </div>
                <div class="form-check form-switch fs-5 ms-3">
                    <input class="form-check-input border-dark" type="checkbox" id="inStockToggle" onchange="applyFilters()">
                    <label class="form-check-label fw-bold text-dark small mt-1" for="inStockToggle">In-Stock Only</label>
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

            <div id="main-category-grid" class="p-3 bg-white" style="flex: 1 1 auto; overflow-y: auto;">
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

            <div id="sub-category-grid" class="p-3 bg-white" style="display:none; flex: 1 1 auto; overflow-y: auto;">
                <div class="row g-3" id="subCategoryContainer">
                    </div>
            </div>

            <div id="drilldown-header" class="bg-white p-3 border-bottom d-flex align-items-center justify-content-between shadow-sm" style="display:none; flex: 0 0 auto; position: relative; z-index: 10;">
                <button id="backBtn" class="btn btn-dark fw-bold px-4 rounded-pill shadow-sm"><i class="bi bi-arrow-left me-2"></i> BACK</button>
                <h4 class="m-0 fw-bold text-dark text-uppercase" id="currentCategoryLabel">All Items</h4>
            </div>
            
            <div class="product-list-wrapper" id="product-list-wrapper" style="display:none;">
                <div class="product-list">
                    <div id="items-grid" class="row g-2">
                        <?php foreach($products as $p): $hasRecipe = ($p['is_recipe'] > 0); $isOut = ($p['stock_qty'] <= 0 && !$hasRecipe); ?>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2 item" data-cat="<?= $p['category_id'] ?>" data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>" data-out="<?= $isOut ? '1' : '0' ?>">
                            <form method="POST" class="h-100 add-item-form">
                                <input type="hidden" name="add_item" value="1"><input type="hidden" name="product_id" value="<?= $p['id'] ?>"><input type="hidden" name="is_refund" class="refund-input" value="0">
                                <button type="submit" class="item-card position-relative p-2" <?= $isOut ? 'disabled="disabled"' : '' ?>>
                                    <?php if($hasRecipe): ?><span class="stock-badge bg-recipe">Made to Order</span><?php else: ?><span class="stock-badge <?= $isOut ? 'bg-low' : 'bg-ok' ?>"><?= $p['stock_qty'] ?></span><?php endif; ?>
                                    <div style="height: 50px; overflow: hidden;" class="fw-bold text-dark mb-1 lh-sm"><?= htmlspecialchars($p['name']) ?></div>
                                    <div class="text-primary fw-bold">ZMW <?= number_format($p['price'], 2) ?></div>
                                </button>
                            </form>
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

        <div class="cart-panel" id="cartPanel">
            <div class="cart-header d-flex justify-content-between align-items-center" id="cartHeader">
                <h5 class="m-0 fw-bold"><i class="bi bi-basket3-fill me-2"></i> Order</h5>
                <button class="btn btn-sm btn-outline-light d-md-none" onclick="toggleCart()">Toggle</button>
            </div>
            <div class="cart-items">
                <?php if (empty($_SESSION['cart'])): ?><div class="text-center mt-5 text-muted"><p>Cart is empty</p></div><?php else: foreach ($_SESSION['cart'] as $key => $item): $isRefundItem = $item['is_refund'] ?? false; ?>
                <div class="cart-item <?= $isRefundItem ? 'border-danger border-2' : '' ?>">
                    <div class="flex-grow-1 me-2 overflow-hidden">
                        <div class="fw-bold text-truncate <?= $isRefundItem ? 'text-danger' : '' ?>"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <small class="text-muted fw-bold <?= $isRefundItem ? 'text-danger' : '' ?>">@ ZMW <?= number_format($item['price'], 2) ?></small>
                            <?php if (!$isRefundItem): ?>
                                <?php $isFood = in_array(strtolower($item['cat_type'] ?? ''), ['food', 'meal']); if ($isFood && defined('LICENSE_TIER') && LICENSE_TIER === 'hospitality'): ?>
                                    <span class="badge bg-warning text-dark border shadow-sm" style="font-size:0.7rem;"><i class="bi bi-fire"></i> Kitchen</span>
                                <?php else: ?>
                                    <form method="POST" class="d-inline"><input type="hidden" name="toggle_fulfillment" value="1"><input type="hidden" name="cart_key" value="<?= $key ?>"><?php if (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?><button class="btn btn-sm btn-fulfillment <?= ($item['fulfillment']??'collected')=='collected'?'btn-outline-success':'btn-warning' ?>"><?= ($item['fulfillment']??'collected')=='collected' ? 'Got It' : 'Later' ?></button><?php else: ?><span class="badge bg-success">Collected</span><?php endif; ?></form>
                                <?php endif; ?>
                            <?php else: ?><span class="badge bg-danger">RETURN</span><?php endif; ?>
                        </div>
                    </div>
                    <div class="d-flex align-items-center bg-light rounded border">
                        <form method="POST" class="d-flex m-0"><input type="hidden" name="cart_key" value="<?= $key ?>"><input type="hidden" name="update_qty" value="1"><button name="action" value="dec" class="btn btn-sm text-danger px-2 fw-bold">-</button><span class="px-2 fw-bold"><?= $item['qty'] ?></span><button name="action" value="inc" class="btn btn-sm text-success px-2 fw-bold">+</button></form>
                        <form method="POST" class="d-inline ms-1"><input type="hidden" name="remove_item" value="1"><input type="hidden" name="cart_key" value="<?= $key ?>"><button class="btn btn-sm text-secondary"><i class="bi bi-trash"></i></button></form>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="cart-footer">
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <span class="text-muted small fw-bold">TOTAL</span>
                    <span class="fs-2 fw-bold <?= $balance < 0 ? 'text-danger' : 'text-dark' ?> lh-1">ZMW <?= number_format($balance, 2) ?></span>
                </div>
                <div class="d-grid gap-2 mb-3">
                    <button type="button" class="btn w-100 py-3 btn-charge shadow <?= $balance < 0 ? 'bg-danger border-danger' : '' ?>" onclick="initCheckout(false)" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>><?= $balance < 0 ? 'REFUND CASH' : 'CHARGE' ?></button>
                    <?php if (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?>
                    <div class="btn-group w-100">
                        <button type="button" class="btn btn-warning fw-bold py-2" data-bs-toggle="modal" data-bs-target="#addToTabModal" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>ADD TO TAB</button>
                    </div>
                    <?php endif; ?>
                    <div class="row g-2">
                        <div class="col-<?= (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])) ? '6' : '12' ?>"><form method="POST" onsubmit="confirmAction(event, 'Clear Cart?', 'Empty order?')"><input type="hidden" name="clear_cart" value="1"><button class="btn btn-outline-danger w-100 btn-sm fw-bold" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>CLEAR</button></form></div>
                        <?php if (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?><div class="col-6"><button type="button" class="btn btn-dark w-100 btn-sm fw-bold text-warning" onclick="logWasteAuth()" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>LOST STOCK</button></div><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="locationModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-warning"><div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold">Change Workstation</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body bg-light p-4"><form method="POST"><?php foreach($sellableLocations as $loc): ?><button name="set_pos_location" value="<?= $loc['id'] ?>" class="btn btn-white border w-100 mb-2 py-3 fw-bold text-start shadow-sm"><?= htmlspecialchars($loc['name']) ?></button><?php endforeach; ?></form></div></div></div></div>

    <div class="modal fade" id="addToTabModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg border-warning border-top border-4"><div class="modal-header bg-light"><h5 class="modal-title fw-bold text-dark"><i class="bi bi-plus-square text-warning"></i> Add to Tab / Table</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body p-4"><input type="hidden" name="add_to_tab_action" value="1"><label class="form-label small fw-bold text-muted mb-2">SELECT DESTINATION</label><div class="list-group mb-3" id="tabSelectionGroup"><label class="list-group-item tab-radio-label active" onclick="highlightTabSelection(this)"><input class="form-check-input me-2" type="radio" name="target_tab_id" value="new" checked><span class="fw-bold">Create New Custom Tab</span></label><?php foreach($openTabs as $t): if($t['payment_status'] !== 'paid'): ?><label class="list-group-item tab-radio-label" onclick="highlightTabSelection(this)"><input class="form-check-input me-2" type="radio" name="target_tab_id" value="<?= $t['id'] ?>"> <strong>Merge into: <?= htmlspecialchars($t['customer_name']) ?></strong></label><?php endif; endforeach; ?></div><div id="newTabNameInput"><label class="form-label small fw-bold text-muted mb-1">NEW CUSTOMER NAME</label><input type="text" name="tab_customer_name" class="form-control" placeholder="Enter name or walk-in"></div></div><div class="modal-footer bg-light border-0"><button type="submit" class="btn btn-warning w-100 fw-bold py-3 shadow-sm text-dark">CONFIRM TRANSFER</button></div></form></div></div></div>

    <div class="modal fade" id="floorplanModal" tabindex="-1"><div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content bg-light"><div class="modal-header bg-dark text-white border-warning border-bottom border-3"><h5 class="modal-title fw-bold"><i class="bi bi-grid-3x3-gap-fill me-2 text-warning"></i> Table Floorplan</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-4"><?php if(empty($restaurantTables)): ?><div class="text-center text-muted my-5"><i class="bi bi-info-circle display-4"></i><p class="mt-3">No tables have been configured for this location yet.</p></div><?php else: ?><?php foreach($restaurantTables as $zoneName => $tables): ?><h5 class="fw-bold text-muted border-bottom pb-2 mb-3 mt-4"><?= htmlspecialchars($zoneName) ?></h5><div class="row g-3"><?php foreach($tables as $table): $activeTab = null; foreach($openTabs as $t) { if ($t['table_id'] == $table['id']) { $activeTab = $t; break; } } $isOccupied = ($activeTab !== null); ?><div class="col-6 col-md-4 col-lg-3"><?php if($isOccupied): ?><div class="table-box table-occupied" onclick="switchModal('floorplanModal', 'tabsModal', () => showTabDetails(<?= $activeTab['id'] ?>))"><span class="table-capacity"><i class="bi bi-people-fill"></i> <?= $table['capacity'] ?></span><h5 class="fw-bold mb-1"><?= htmlspecialchars($table['table_name']) ?></h5><div class="small fw-bold">ZMW <?= number_format($activeTab['final_total'], 2) ?></div><div class="badge bg-danger mt-2">OCCUPIED</div></div><?php else: ?><form method="POST" class="h-100"><input type="hidden" name="add_to_tab_action" value="1"><input type="hidden" name="target_tab_id" value="new"><input type="hidden" name="target_table_id" value="<?= $table['id'] ?>"><input type="hidden" name="tab_customer_name" value="<?= htmlspecialchars($table['table_name']) ?>"><button type="submit" class="table-box table-available w-100" <?= empty($_SESSION['cart']) ? 'onclick="alert(\'Add items to the cart first to open a table!\'); return false;"' : '' ?>><span class="table-capacity"><i class="bi bi-people-fill"></i> <?= $table['capacity'] ?></span><h5 class="fw-bold mb-1"><?= htmlspecialchars($table['table_name']) ?></h5><div class="badge bg-success mt-2">AVAILABLE</div></button></form><?php endif; ?></div><?php endforeach; ?></div><?php endforeach; ?><?php endif; ?></div></div></div></div>

    <div class="modal fade" id="tabsModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content h-100"><div class="modal-header bg-dark text-white border-info border-bottom border-3"><h5 class="modal-title fw-bold"><i class="bi bi-receipt me-2 text-info"></i> Active Tabs</h5><button type="button" class="btn btn-sm btn-outline-info ms-auto me-3 fw-bold" onclick="switchModal('tabsModal', 'floorplanModal')"><i class="bi bi-grid-3x3-gap-fill"></i> View Tables</button><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row h-100"><div class="col-4 border-end overflow-auto"><div class="list-group"><?php foreach($openTabs as $t): $isPaid = $t['payment_status'] === 'paid'; $bg = $isPaid ? 'bg-success-subtle' : ''; $badge = $isPaid ? '<span class="badge bg-success">PAID</span>' : ''; ?><button class="list-group-item list-group-item-action <?= $bg ?>" onclick="showTabDetails(<?= $t['id'] ?>)"><div class="d-flex justify-content-between"><strong><?= htmlspecialchars($t['customer_name']) ?></strong> <?= $badge ?></div><div class="small text-muted">ZMW <?= number_format($t['final_total'],2) ?></div></button><?php endforeach; ?></div></div><div class="col-8 p-3" id="tabDetailContainer"><p class="text-center text-muted mt-5">Select a tab from the list to view details.</p></div></div></div></div></div></div>

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
                            <?php if(in_array(strtolower($i['cat_type'] ?? ''), ['food', 'meal']) && defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?>
                                <?php if($i['status'] === 'ready'): ?><span class="badge bg-warning text-dark border shadow-sm px-2 py-1" style="cursor:pointer;" onclick="switchModal('tabsModal', '', showPickupModal)"><i class="bi bi-bag-check"></i> GO TO PICKUP</span><?php else: ?><span class="badge bg-secondary text-white border shadow-sm px-2 py-1" style="cursor:pointer;" onclick="Swal.fire({icon: 'info', title: 'Still Cooking', text: 'Preparing!', timer: 2000, showConfirmButton: false})"><i class="bi bi-hourglass-split"></i> PREPARING</span><?php endif; ?>
                            <?php else: ?><span class="badge badge-uncollected p-2" style="cursor:pointer;" onclick="markCollected(<?= $i['id'] ?>, <?= $tid ?>)">MARK COLLECTED</span><?php endif; ?>
                        <?php else: ?><span class="badge bg-success">COLLECTED</span><?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </table>
                <?php if($tabPaymentStatus === 'paid'): ?><div class="alert alert-success text-center fw-bold py-3 mb-0 shadow-sm"><i class="bi bi-check-circle-fill"></i> PAID IN FULL</div><?php else: ?><div class="row g-2 mt-3"><div class="col-5"><button type="button" class="btn btn-outline-dark w-100 fw-bold py-3 shadow-sm text-uppercase" onclick="printTabBill(<?= $tid ?>)"><i class="bi bi-printer"></i> Print Bill</button></div><div class="col-7"><button type="button" class="btn btn-primary w-100 fw-bold py-3 shadow-sm text-uppercase" onclick="openSettleModal(<?= $tid ?>, <?= array_sum(array_map(function($x){ return $x['price']*$x['quantity']; }, $items)) ?>, '<?= htmlspecialchars(addslashes($tabCustomerName)) ?>')"><i class="bi bi-cash-coin"></i> Settle Tab</button></div></div><?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="modal fade" id="checkoutModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0"><div class="modal-header bg-warning text-dark" id="checkoutHeader"><h5 class="modal-title fw-bold">Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="checkout" value="1"><input type="hidden" name="settle_tab_id" id="settle_tab_id_input" value="0">
        <?php if(isset($_SESSION['pos_member']) && defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?><div class="alert alert-info border-info d-flex align-items-center justify-content-between mb-3 p-2 shadow-sm"><div class="d-flex align-items-center"><i class="bi bi-star-fill text-warning fs-4 me-3"></i><div><div class="fw-bold">Member: <?= htmlspecialchars($_SESSION['pos_member']['name']) ?></div><div class="small text-muted">Eligible for benefits</div></div></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="discountToggle" name="apply_discount" value="1" onchange="toggleDiscount()"><label class="form-check-label fw-bold small" for="discountToggle">10% OFF</label></div></div><?php endif; ?>
        <div class="text-center mb-4"><small class="text-muted text-uppercase fw-bold">Amount To Pay</small><div class="display-4 fw-bold text-dark">ZMW <span id="displayTotalDue">0.00</span></div><small class="text-success fw-bold" id="discountLabel" style="display:none;">(Discount Applied)</small></div>
        <div id="refundAuthSection" class="p-3 bg-dark rounded shadow-sm border border-danger mb-4" style="display:none;"><label class="form-label small fw-bold text-danger mb-3 d-block border-bottom border-danger pb-2"><i class="bi bi-shield-lock-fill"></i> MANAGER AUTHORIZATION REQUIRED</label><input type="text" name="mgr_username" id="mgrUserRefund" class="form-control mb-2" placeholder="Manager Username"><input type="password" name="mgr_password" id="mgrPassRefund" class="form-control" placeholder="Manager Password"></div>
        <div class="input-group mb-3"><span class="input-group-text bg-light fw-bold">Tip</span><input type="number" step="0.01" name="tip_amount" id="tipInput" class="form-control" placeholder="0.00" onkeyup="calcResult()"><button type="button" class="btn btn-outline-secondary" onclick="addTipPercent(0.05)">5%</button><button type="button" class="btn btn-outline-secondary" onclick="addTipPercent(0.10)">10%</button><button type="button" class="btn btn-outline-secondary" onclick="addTipPercent(0.15)">15%</button></div>
        <div class="mb-3"><input type="text" name="customer_name" class="form-control" placeholder="Customer Name" value="<?= $_SESSION['current_customer'] ?? 'Walk-in' ?>" <?= isset($_SESSION['pos_member']) ? 'readonly' : '' ?>></div>
        <div class="btn-group w-100 mb-3 <?= (defined('LICENSE_TIER') && LICENSE_TIER === 'lite') ? 'd-none' : '' ?>" role="group" id="splitModeGroup"><input type="radio" class="btn-check" name="is_split" id="modeSingle" value="0" checked onchange="toggleMode()"><label class="btn btn-outline-dark fw-bold" for="modeSingle">Single Pay</label><input type="radio" class="btn-check" name="is_split" id="modeSplit" value="1" onchange="toggleMode()"><label class="btn btn-outline-dark fw-bold" for="modeSplit">Split Pay</label></div>
        <div id="singleSection"><div class="mb-3"><select name="payment_method" class="form-select form-select-lg fw-bold"><option value="Cash" selected>Cash</option><option value="Card">Card</option><option value="MTN Money">MTN Money</option><option value="Airtel Money">Airtel Money</option><option value="Zamtel Money">Zamtel Money</option><?php if (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?><option value="Pending">Put on Tab</option><?php endif; ?></select></div></div>
        <div class="card bg-light border-0 p-3 mt-3"><label class="form-label small fw-bold text-muted mb-1">TOTAL TENDERED</label><div class="input-group input-group-lg"><span class="input-group-text bg-white border-end-0 fw-bold">ZMW</span><input type="number" step="0.01" name="amount_tendered" id="tenderedInput" class="form-control border-start-0 fw-bold fs-3 text-success" oninput="calcResult()" onkeyup="calcResult()"></div><div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top"><div class="small fw-bold text-uppercase text-muted" id="resultLabel">Change Due</div><div class="fs-4 fw-bold text-dark" id="resultValue">ZMW 0.00</div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning w-100 fw-bold py-3 shadow-sm" id="btnCheckoutSubmit">COMPLETE TRANSACTION</button></div></form></div></div></div>

    <div class="modal fade" id="expenseModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg border-top border-warning border-4"><div class="modal-header bg-light"><h5 class="modal-title fw-bold text-dark"><i class="bi bi-cash-stack text-warning"></i> Log Payout</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body p-4"><input type="hidden" name="log_expense" value="1"><div class="alert alert-warning small border-warning"><strong><i class="bi bi-info-circle"></i> Note:</strong> This instantly deducts cash from Expected Drawer Total.</div><div class="mb-3"><label class="form-label small fw-bold text-muted">Amount Taken</label><div class="input-group input-group-lg"><span class="input-group-text bg-white fw-bold">ZMW</span><input type="number" step="0.01" name="expense_amount" class="form-control fw-bold text-danger" required placeholder="0.00"></div></div><div class="mb-4"><label class="form-label small fw-bold text-muted">Reason</label><input type="text" name="expense_reason" class="form-control" required placeholder="Paid driver..."></div><div class="p-3 bg-dark rounded shadow-sm border border-secondary"><label class="form-label small fw-bold text-warning mb-3 d-block border-bottom border-secondary pb-2"><i class="bi bi-shield-lock-fill"></i> MANAGER AUTHORIZATION</label><input type="text" name="mgr_username" class="form-control mb-2" required placeholder="Manager Username"><input type="password" name="mgr_password" class="form-control" required placeholder="Manager Password"></div></div><div class="modal-footer border-0"><button type="submit" class="btn btn-warning w-100 fw-bold shadow-sm">Authorize Payout</button></div></form></div></div></div>
    
    <div class="modal fade" id="endShiftModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg border-top border-danger border-4">
                <div class="modal-header bg-light text-dark">
                    <h5 class="modal-title fw-bold"><i class="bi bi-power text-danger"></i> End Shift</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="index.php?page=end_shift_action" method="POST">
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
    <div class="modal fade" id="receiptModal" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content"><div class="modal-header bg-success text-white"><h5>Receipt</h5></div><div class="modal-body p-0" style="height:400px;"><iframe id="receiptFrame" src="" style="width:100%; height:100%; border:none;"></iframe></div><div class="modal-footer"><button type="button" class="btn btn-primary" onclick="document.getElementById('receiptFrame').contentWindow.print()">PRINT</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function escapeJS(str) { if(!str) return ''; return str.toString().replace(/'/g, "\\'").replace(/"/g, '\\"'); }

        let currentCat = 'all';
        const subCategoriesMap = <?= json_encode($subCatsByParent) ?>;
        const catParents = <?= json_encode($catParents) ?>;
        const catNames = <?= json_encode($catNames) ?>;
        const iconMap = {'whiskey bottles': 'bi-droplet-half', 'whiskey tots': 'bi-cup', 'ciders': 'bi-cup-straw', 'wines and creams': 'bi-cup-fill', 'softies': 'bi-cup-straw', 'lagers': 'bi-cup-straw', 'mixers': 'bi-cup-straw', 'mineral water': 'bi-water'};

        function showMainGrid() {
            document.getElementById('main-category-grid').style.display = 'block';
            document.getElementById('sub-category-grid').style.display = 'none';
            document.getElementById('product-list-wrapper').style.display = 'none';
            document.getElementById('drilldown-header').style.display = 'none';
            document.getElementById('search').value = '';
            currentCat = 'all';
            applyFilters();
        }

        function showSubCategories(categoryId, categoryName) {
            document.getElementById('main-category-grid').style.display = 'none';
            document.getElementById('product-list-wrapper').style.display = 'none';
            
            let container = document.getElementById('subCategoryContainer');
            
            let parentOfCurrent = catParents[categoryId] || null;
            let parentName = parentOfCurrent && catNames[parentOfCurrent] ? escapeJS(catNames[parentOfCurrent]) : '';
            let safeCatName = escapeJS(categoryName);
            
            let backAction = parentOfCurrent ? `showSubCategories('${parentOfCurrent}', '${parentName}')` : `showMainGrid()`;

            container.innerHTML = `
                <div class="col-4 col-md-3 col-lg-2">
                    <div class="cat-card h-100 shadow-sm border-secondary" onclick="filterItems('${categoryId}', 'All ${safeCatName}', true, '${categoryId}', '${safeCatName}')">
                        <i class="bi bi-grid cat-icon text-secondary"></i>
                        <span class="small text-uppercase">All ${categoryName}</span>
                    </div>
                </div>
            `;
            
            let subs = subCategoriesMap[categoryId] || [];
            subs.forEach(sub => {
                let hasSubs = subCategoriesMap[sub.id] && subCategoriesMap[sub.id].length > 0;
                let safeSubName = escapeJS(sub.name);
                
                if (hasSubs) {
                    container.innerHTML += `
                        <div class="col-4 col-md-3 col-lg-2">
                            <div class="cat-card h-100 shadow-sm border-info bg-info bg-opacity-10" onclick="showSubCategories('${sub.id}', '${safeSubName}')">
                                <i class="bi bi-folder-fill cat-icon text-info"></i>
                                <span class="small text-uppercase fw-bold text-info">${sub.name}</span>
                            </div>
                        </div>
                    `;
                } else {
                    let icon = iconMap[sub.name.toLowerCase()] || 'bi-arrow-return-right';
                    container.innerHTML += `
                        <div class="col-4 col-md-3 col-lg-2">
                            <div class="cat-card h-100 shadow-sm border-info" onclick="filterItems('${sub.id}', '${safeSubName}', true, '${categoryId}', '${safeCatName}')">
                                <i class="bi ${icon} cat-icon text-info"></i>
                                <span class="small text-uppercase">${sub.name}</span>
                            </div>
                        </div>
                    `;
                }
            });

            document.getElementById('sub-category-grid').style.display = 'block';
            document.getElementById('drilldown-header').style.display = 'flex';
            document.getElementById('currentCategoryLabel').innerText = categoryName;
            document.getElementById('backBtn').setAttribute('onclick', backAction);
        }

        function filterItems(id, name, fromSub = false, parentId = null, parentName = '') {
            document.getElementById('main-category-grid').style.display = 'none';
            document.getElementById('sub-category-grid').style.display = 'none';
            document.getElementById('product-list-wrapper').style.display = 'flex';
            document.getElementById('drilldown-header').style.display = 'flex';
            
            document.getElementById('currentCategoryLabel').innerText = name;
            
            if (fromSub && parentId) {
                document.getElementById('backBtn').setAttribute('onclick', `showSubCategories('${parentId}', '${escapeJS(parentName)}')`);
            } else {
                document.getElementById('backBtn').setAttribute('onclick', 'showMainGrid()');
            }

            currentCat = id.toString();
            document.getElementById('items-grid').style.display = 'flex';
            document.getElementById('services-grid').style.display = 'none';
            document.getElementById('paginationBar').style.display = 'flex';
            applyFilters();
        }

        function switchTab(tab, name) {
            document.getElementById('main-category-grid').style.display = 'none';
            document.getElementById('sub-category-grid').style.display = 'none';
            document.getElementById('product-list-wrapper').style.display = 'flex';
            document.getElementById('drilldown-header').style.display = 'flex';
            document.getElementById('currentCategoryLabel').innerText = name;
            document.getElementById('backBtn').setAttribute('onclick', 'showMainGrid()');
            
            document.getElementById('items-grid').style.display = 'none'; 
            document.getElementById('services-grid').style.display = 'flex'; 
            document.getElementById('paginationBar').style.display = 'none'; 
        }

        function printTabBill(saleId) { document.getElementById("receiptFrame").src = "about:blank"; setTimeout(() => { document.getElementById("receiptFrame").src = "index.php?page=receipt&sale_id=" + saleId + "&is_bill=1&_cb=" + new Date().getTime(); }, 100); safeModalShow("receiptModal"); }
        function highlightTabSelection(selectedLabel) { document.querySelectorAll('.tab-radio-label').forEach(el => el.classList.remove('active')); selectedLabel.classList.add('active'); let radio = selectedLabel.querySelector('input[type="radio"]'); if (radio.value === 'new') { document.getElementById('newTabNameInput').style.display = 'block'; } else { document.getElementById('newTabNameInput').style.display = 'none'; } }

        function toggleRefundMode() {
            let isRefund = document.getElementById('refundToggle').checked;
            document.querySelectorAll('.refund-input').forEach(el => el.value = isRefund ? '1' : '0');
            let header = document.getElementById('mainHeader');
            if (isRefund) {
                header.classList.remove('bg-dark', 'border-warning'); header.classList.add('bg-danger', 'border-dark');
                document.getElementById('headerPosLabel').innerText = 'REFUND MODE'; document.getElementById('headerPosLabel').classList.replace('text-warning', 'text-white'); document.getElementById('headerLocIcon').classList.replace('text-warning', 'text-white'); document.getElementById('cartHeader').classList.replace('bg-dark', 'bg-danger');
            } else {
                header.classList.add('bg-dark', 'border-warning'); header.classList.remove('bg-danger', 'border-dark');
                document.getElementById('headerPosLabel').innerText = 'POS'; document.getElementById('headerPosLabel').classList.replace('text-white', 'text-warning'); document.getElementById('headerLocIcon').classList.replace('text-white', 'text-warning'); document.getElementById('cartHeader').classList.replace('bg-danger', 'bg-dark');
            }
        }

        function pushToCart(productId, price, saleItemId = 0, tabStatus = 'paid') {
            let isRefund = document.getElementById('refundToggle') && document.getElementById('refundToggle').checked ? '1' : '0';
            if (isRefund === '1' && tabStatus === 'pending') { Swal.fire({ icon: 'error', title: 'Stop!', text: 'This table has not paid yet! Use the red Void button to remove the item.' }); return; }
            let f = document.createElement('form'); f.method = 'POST'; f.innerHTML = `<input type="hidden" name="add_item" value="1"><input type="hidden" name="product_id" value="${productId}"><input type="hidden" name="custom_price" value="${price}"><input type="hidden" name="is_refund" value="${isRefund}"><input type="hidden" name="refund_sale_item_id" value="${saleItemId}">`; document.body.appendChild(f); f.submit();
        }

        function logWasteAuth() { Swal.fire({ title: 'Log Waste', html: '<p class="text-muted small">Enter manager credentials</p><input id="swal-w-user" class="swal2-input" placeholder="Username"><input id="swal-w-pass" type="password" class="swal2-input" placeholder="Password">', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Authorize Waste', preConfirm: () => { return { user: document.getElementById('swal-w-user').value, pass: document.getElementById('swal-w-pass').value } } }).then((result) => { if (result.isConfirmed) { if(!result.value.user || !result.value.pass) { Swal.fire({icon:'error', title:'Required', text:'Credentials cannot be empty.'}); return; } let f = document.createElement('form'); f.method = 'POST'; f.innerHTML = `<input type="hidden" name="log_waste" value="1"><input type="hidden" name="mgr_user" value="${result.value.user}"><input type="hidden" name="mgr_pass" value="${result.value.pass}">`; document.body.appendChild(f); f.submit(); } }); }
        function safeModalShow(id) { let el = document.getElementById(id); if (!el) return; try { let m = bootstrap.Modal.getInstance(el); if (!m) { m = new bootstrap.Modal(el); } m.show(); } catch(e) { el.classList.add('show'); el.style.display = 'block'; } }
        function switchModal(closeId, openId, callback = null) { let closeEl = document.getElementById(closeId); let mClose = bootstrap.Modal.getInstance(closeEl); if (mClose) mClose.hide(); else if (closeEl) { closeEl.classList.remove('show'); closeEl.style.display = 'none'; } setTimeout(() => { document.querySelectorAll('.modal-backdrop').forEach(el => el.remove()); document.body.classList.remove('modal-open'); document.body.style = ''; if (callback) callback(); if(openId !== '') { safeModalShow(openId); } }, 350); }
        function showPickupModal() { document.getElementById('pickupFrame').src = "index.php?page=pickup&embedded=1"; safeModalShow('pickupModal'); }
        function showTabDetails(id) { let template = document.getElementById('tab-data-' + id); let container = document.getElementById('tabDetailContainer'); if (template && container) { container.innerHTML = template.innerHTML; } }

        function voidItem(itemId) { Swal.fire({ title: 'Void Item', html: '<p class="text-muted small">Enter manager credentials</p><input id="swal-user" class="swal2-input" placeholder="Username"><input id="swal-pass" type="password" class="swal2-input" placeholder="Password">', icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Authorize Void', preConfirm: () => { return { user: document.getElementById('swal-user').value, pass: document.getElementById('swal-pass').value } } }).then((result) => { if (result.isConfirmed) { if(!result.value.user || !result.value.pass) { Swal.fire({icon:'error', title:'Required', text:'Credentials cannot be empty.'}); return; } fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'void_item=1&item_id=' + itemId + '&mgr_user=' + encodeURIComponent(result.value.user) + '&mgr_pass=' + encodeURIComponent(result.value.pass) }).then(r => r.json()).then(data => { if (data.status === 'success') { Swal.fire({ icon: 'success', title: 'Voided', timer: 1200, showConfirmButton: false }).then(() => location.reload()); } else { Swal.fire({ icon: 'error', title: 'Error', text: data.msg }); } }); } }); }

        let baseTotal = 0; let currentTotal = 0;
        function initCheckout(isTabMode = false, tabTotal = 0, tabCustomer = '') { 
            let nameInput = document.querySelector('[name="customer_name"]');
            if (!isTabMode) { baseTotal = <?= $balance ?? 0 ?>; document.getElementById('settle_tab_id_input').value = '0'; nameInput.value = '<?= $_SESSION['current_customer'] ?? 'Walk-in' ?>'; nameInput.readOnly = false; } else { baseTotal = parseFloat(tabTotal); nameInput.value = tabCustomer; nameInput.readOnly = true; }
            currentTotal = baseTotal; document.getElementById('tipInput').value = '';
            if (currentTotal < 0) { document.getElementById('checkoutHeader').classList.replace('bg-warning', 'bg-danger'); document.getElementById('btnCheckoutSubmit').classList.replace('btn-warning', 'btn-danger'); document.getElementById('btnCheckoutSubmit').innerText = 'ISSUE REFUND'; document.getElementById('displayTotalDue').classList.replace('text-dark', 'text-danger'); document.getElementById('refundAuthSection').style.display = 'block'; document.getElementById('mgrUserRefund').required = true; document.getElementById('mgrPassRefund').required = true; } else { document.getElementById('checkoutHeader').classList.replace('bg-danger', 'bg-warning'); document.getElementById('btnCheckoutSubmit').classList.replace('btn-danger', 'btn-warning'); document.getElementById('btnCheckoutSubmit').innerText = 'COMPLETE TRANSACTION'; document.getElementById('displayTotalDue').classList.replace('text-danger', 'text-dark'); document.getElementById('refundAuthSection').style.display = 'none'; document.getElementById('mgrUserRefund').required = false; document.getElementById('mgrPassRefund').required = false; }
            if(document.getElementById('discountToggle')) { document.getElementById('discountToggle').checked = false; toggleDiscount(); } else { updateDisplays(); } safeModalShow('checkoutModal'); 
        }

        function openSettleModal(tabId, total, customerName) { document.getElementById('settle_tab_id_input').value = tabId; switchModal('tabsModal', '', function() { initCheckout(true, total, customerName); }); }
        function markCollected(itemId, saleId) { fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'mark_collected=1&item_id=' + itemId }).then(r => r.json()).then(data => { if (data.status === 'success') { if (data.print_receipt) { document.getElementById('receiptFrame').src = "index.php?page=receipt&sale_id=" + data.sale_id + "&collection_only=" + data.item_id + "&_t=" + new Date().getTime(); safeModalShow('receiptModal'); } const activeRow = document.getElementById('tabDetailContainer').querySelector('#item-row-' + itemId); if(activeRow) activeRow.querySelector('.text-end').innerHTML = '<span class="badge bg-success">COLLECTED</span>'; const templateRow = document.getElementById('hiddenTabTemplates').querySelector('#item-row-' + itemId); if(templateRow) templateRow.querySelector('.text-end').innerHTML = '<span class="badge bg-success">COLLECTED</span>'; } else if (data.status === 'redirect_pickup') { Swal.fire({ icon: 'info', title: 'Collect at Pickup', text: data.msg, showCancelButton: true, confirmButtonText: 'Open Pickup Screen' }).then((result) => { if (result.isConfirmed) { switchModal('tabsModal', '', showPickupModal); } }); } else { Swal.fire({ icon: 'error', title: 'Action Blocked', text: data.msg }); } }); }
        function toggleDiscount() { let chk = document.getElementById('discountToggle'); if(chk && chk.checked) { currentTotal = baseTotal * 0.90; document.getElementById('discountLabel').style.display = 'block'; } else { currentTotal = baseTotal; document.getElementById('discountLabel').style.display = 'none'; } updateDisplays(); }
        function updateDisplays() { document.getElementById('displayTotalDue').innerText = currentTotal.toFixed(2); document.getElementById('tenderedInput').value = currentTotal.toFixed(2); calcResult(); }
        function toggleMode() { let isSplit = document.getElementById('modeSplit').checked; document.getElementById('singleSection').style.display = isSplit ? 'none' : 'block'; document.getElementById('splitSection').style.display = isSplit ? 'block' : 'none'; if(isSplit) { document.getElementById('splitInput1').value = ""; document.getElementById('splitInput2').value = ""; document.getElementById('tenderedInput').value = "0.00"; } else { document.getElementById('tenderedInput').value = currentTotal.toFixed(2); } calcResult(); }
        function sumSplit() { let val1 = parseFloat(document.getElementById('splitInput1').value) || 0; let val2 = parseFloat(document.getElementById('splitInput2').value) || 0; document.getElementById('tenderedInput').value = (val1 + val2).toFixed(2); calcResult(); }
        function addTipPercent(percent) { let tip = currentTotal * percent; document.getElementById('tipInput').value = tip.toFixed(2); calcResult(); }
        function calcResult() { let tendered = parseFloat(document.getElementById('tenderedInput').value) || 0; let tip = parseFloat(document.getElementById('tipInput').value) || 0; let diff = tendered - (currentTotal + tip); let label = document.getElementById('resultLabel'); let value = document.getElementById('resultValue'); if (currentTotal < 0) { label.innerText = "CASH OUT OF DRAWER"; label.className = "small fw-bold text-uppercase text-danger"; value.innerText = "ZMW " + Math.abs(currentTotal).toFixed(2); value.className = "fs-4 fw-bold text-danger"; } else { if(diff >= -0.01) { label.innerText = "CHANGE DUE"; label.className = "small fw-bold text-uppercase text-muted"; value.innerText = "ZMW " + diff.toFixed(2); value.className = "fs-4 fw-bold text-dark"; } else { label.innerText = "BALANCE REMAINING"; label.className = "small fw-bold text-uppercase text-danger"; value.innerText = "ZMW " + Math.abs(diff).toFixed(2); value.className = "fs-4 fw-bold text-danger"; } } }

        function getAllDescendants(catId) {
            let desc = [];
            let subs = subCategoriesMap[catId] || [];
            subs.forEach(s => {
                desc.push(s.id.toString());
                desc = desc.concat(getAllDescendants(s.id)); 
            });
            return desc;
        }

        function filter() { 
            document.getElementById('main-category-grid').style.display = 'none'; 
            document.getElementById('sub-category-grid').style.display = 'none'; 
            document.getElementById('product-list-wrapper').style.display = 'flex'; 
            document.getElementById('drilldown-header').style.display = 'flex'; 
            document.getElementById('currentCategoryLabel').innerText = "Search Results"; 
            document.getElementById('backBtn').setAttribute('onclick', 'showMainGrid()'); 
            currentCat = 'all'; 
            applyFilters(); 
        }

        function applyFilters() { 
            let v = document.getElementById('search').value.toLowerCase(); 
            let showInStockOnly = false; 
            let toggleElement = document.getElementById('inStockToggle'); 
            if (toggleElement) { showInStockOnly = toggleElement.checked; localStorage.setItem('posInStockToggle', showInStockOnly); } 
            
            let allItems = Array.from(document.querySelectorAll('#items-grid .item')); 
            
            let validCats = [currentCat];
            if (currentCat !== 'all') {
                validCats = validCats.concat(getAllDescendants(currentCat));
            }

            activeItems = allItems.filter(e => { 
                let matchCat = (currentCat === 'all' || validCats.includes(e.dataset.cat.toString())); 
                let matchName = e.dataset.name.includes(v); 
                let matchStock = true; 
                if (showInStockOnly) { matchStock = e.dataset.out === "0"; } 
                return matchCat && matchName && matchStock; 
            }); 
            renderPage(1); 
        }

        let currentPage = 1; const itemsPerPage = 24; let activeItems = [];
        document.addEventListener('DOMContentLoaded', function() { 
            if (localStorage.getItem('posInStockToggle') === 'true') { let toggle = document.getElementById('inStockToggle'); if(toggle) toggle.checked = true; } 
            initPagination(); 
            <?php if(isset($_SESSION["last_sale_id"])): ?> document.getElementById("receiptFrame").src = "about:blank"; setTimeout(() => { document.getElementById("receiptFrame").src = "index.php?page=receipt&sale_id=<?= $_SESSION["last_sale_id"] ?>&_cb=" + new Date().getTime(); }, 100); safeModalShow("receiptModal"); <?php unset($_SESSION["last_sale_id"]); endif; ?> 
            <?php if(isset($_SESSION["last_bill_id"])): ?> document.getElementById("receiptFrame").src = "about:blank"; setTimeout(() => { document.getElementById("receiptFrame").src = "index.php?page=receipt&sale_id=<?= $_SESSION["last_bill_id"] ?>&is_bill=1&items=<?= $_SESSION["last_added_item_ids"] ?? "" ?>&_cb=" + new Date().getTime(); }, 100); safeModalShow("receiptModal"); <?php unset($_SESSION["last_bill_id"], $_SESSION["last_added_item_ids"]); endif; ?>
            let pickupMod = document.getElementById('pickupModal'); if(pickupMod) { pickupMod.addEventListener('hidden.bs.modal', function () { Swal.fire({ title: 'Syncing Tabs...', showConfirmButton: false, timer: 700, timerProgressBar: true }); setTimeout(() => { location.reload(); }, 700); }); } 
        });
        function showShiftReport(shiftId) { document.getElementById('reportTitle').innerText = "X-Read (Open Shift)"; document.getElementById('reportFrame').src = "index.php?page=print_shift&shift_id=" + shiftId; safeModalShow('reportModal'); }
        function initPagination() { applyFilters(); }
        function renderPage(page) { currentPage = page; const totalPages = Math.ceil(activeItems.length / itemsPerPage) || 1; if (currentPage > totalPages) currentPage = totalPages; if (currentPage < 1) currentPage = 1; const startIndex = (currentPage - 1) * itemsPerPage; const endIndex = startIndex + itemsPerPage; document.querySelectorAll('#items-grid .item').forEach(el => el.style.display = 'none'); activeItems.slice(startIndex, endIndex).forEach(el => { el.style.display = 'block'; }); document.getElementById('pageInfo').innerText = `Page ${currentPage} of ${totalPages}`; }
        function prevPage() { if(currentPage > 1) renderPage(currentPage - 1); } function nextPage() { const totalPages = Math.ceil(activeItems.length / itemsPerPage); if(currentPage < totalPages) renderPage(currentPage + 1); }
        function toggleCart() { document.getElementById('cartPanel').classList.toggle('expanded'); }
        function addService(id, name, price, isOpen) { let isRefund = document.getElementById('refundToggle') && document.getElementById('refundToggle').checked ? '1' : '0'; if (isOpen) { document.getElementById('op_pid').value = id; document.getElementById('op_name').innerText = name; document.getElementById('op_refund').value = isRefund; safeModalShow('openPriceModal'); } else { let f = document.createElement('form'); f.method = 'POST'; f.innerHTML = `<input type="hidden" name="add_item" value="1"><input type="hidden" name="product_id" value="${id}"><input type="hidden" name="is_refund" value="${isRefund}">`; document.body.appendChild(f); f.submit(); } }
        function confirmAction(event, title, text, confirmBtn='Yes') { event.preventDefault(); Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: confirmBtn }).then((result) => { if (result.isConfirmed) { event.target.submit(); } }); }
        <?php if(isset($_SESSION['swal_msg'])): ?> Swal.fire({ icon: '<?= addslashes($_SESSION['swal_type']) ?>', title: '<?= addslashes($_SESSION['swal_msg']) ?>', timer: 1500, showConfirmButton: false }); <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); endif; ?>
        <?php if (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?> function checkPosReadyOrders() { fetch('index.php?action=check_ready_orders').then(r => r.json()).then(data => { let badge = document.getElementById('posReadyBadge'); if(badge && data && data.count > 0) { badge.innerText = data.count; badge.style.display = 'block'; } else if (badge) { badge.style.display = 'none'; } }).catch(e => { console.error('POS Badge Error:', e); }); } checkPosReadyOrders(); setInterval(checkPosReadyOrders, 5000); <?php endif; ?>
    </script>
</body>
</html>