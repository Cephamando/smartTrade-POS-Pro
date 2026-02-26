<!DOCTYPE html> 
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>POS - <?= htmlspecialchars($locationName) ?></title>
    <link rel="shortcut icon" href="data:image/x-icon;," type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f0f2f5; height: 100vh; overflow: hidden; display: flex; flex-direction: column; margin: 0; }
        .header-custom { background-color: #2c2c2c; border-bottom: 4px solid #ffc107; color: white; flex: 0 0 auto; z-index: 1050; }
        .workspace { flex: 1 1 auto; display: flex; overflow: hidden; position: relative; min-height: 0; }
        .product-section { flex: 1 1 auto; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        .category-bar { background: #fff; padding: 10px; border-bottom: 1px solid #ddd; white-space: nowrap; overflow-x: auto; flex: 0 0 auto; }
        .cat-pill { display: inline-block; padding: 8px 18px; margin-right: 8px; border-radius: 50px; background: #f8f9fa; border: 1px solid #dee2e6; color: #333; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .cat-pill:hover { background: #e9ecef; }
        .cat-pill.active { background: #3e2723; color: #ffc107; border-color: #3e2723; }
        .product-list-wrapper { flex: 1 1 auto; display: flex; flex-direction: column; overflow: hidden; }
        .product-list { flex: 1 1 auto; overflow-y: auto; padding: 15px; }
        .pagination-bar { flex: 0 0 auto; background: #fff; border-top: 1px solid #ddd; padding: 10px 15px; display: flex; justify-content: center; align-items: center; gap: 15px; }
        .item-card { background: white; border: 1px solid #e0e0e0; border-radius: 8px; transition: transform 0.1s, box-shadow 0.1s; cursor: pointer; overflow: hidden; position: relative; height: 100%; display: block; width: 100%; text-align: left; }
        .item-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-color: #ffc107; }
        .item-card:active { transform: scale(0.98); }
        .item-card:disabled, .item-card[disabled] { opacity: 0.4 !important; filter: grayscale(100%) !important; background-color: #e9ecef !important; cursor: not-allowed !important; pointer-events: none !important; box-shadow: none !important; border-color: #ddd !important; }
        .stock-badge { position: absolute; top: 8px; right: 8px; font-size: 0.75rem; padding: 4px 8px; border-radius: 4px; font-weight: bold; z-index: 2; }
        .bg-low { background-color: #dc3545; color: white; }
        .bg-ok { background-color: #198754; color: white; }
        .bg-recipe { background-color: #0dcaf0; color: #000; }
        .cart-panel { flex: 0 0 400px; width: 400px; background: #fff; border-left: 1px solid #ccc; display: flex; flex-direction: column; box-shadow: -4px 0 15px rgba(0,0,0,0.1); z-index: 1000; height: 100%; }
        .cart-header { padding: 15px; background: #3e2723; color: white; flex: 0 0 auto; }
        .cart-items { flex: 1 1 auto; overflow-y: auto; padding: 15px; background: #f8f9fa; min-height: 0; }
        .cart-footer { padding: 20px; background: #fff; border-top: 2px solid #eee; flex: 0 0 auto; }
        .cart-item { background: white; border-radius: 6px; padding: 10px; margin-bottom: 10px; border: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .btn-fulfillment { font-size: 0.7rem; font-weight: bold; text-transform: uppercase; padding: 2px 6px; }
        .badge-uncollected { background: #fd7e14; color: white; cursor: pointer; }
        .badge-collected { background: #198754; color: white; cursor: default; }
        .btn-charge { background-color: #fd7e14 !important; border-color: #fd7e14 !important; color: #fff !important; font-size: 1.25rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 6px rgba(253, 126, 20, 0.3); }
        .btn-charge:hover { background-color: #e66b0d !important; }
        .btn-charge:disabled { background-color: #ccc !important; border-color: #ccc !important; box-shadow: none; cursor: not-allowed; }
        .split-container { display: flex; height: 500px; gap: 15px; }
        .split-pool { flex: 1; background: #f8f9fa; border: 2px dashed #ccc; border-radius: 8px; padding: 10px; overflow-y: auto; }
        .split-guest-zone { flex: 2; display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; }
        .guest-col { min-width: 250px; background: white; border: 1px solid #ddd; border-radius: 8px; display: flex; flex-direction: column; }
        .guest-header { padding: 10px; background: #3e2723; color: white; border-radius: 8px 8px 0 0; }
        .guest-items { flex: 1; padding: 10px; overflow-y: auto; background: #fff; min-height: 100px; }
        .guest-footer { padding: 10px; border-top: 1px solid #eee; background: #f1f1f1; border-radius: 0 0 8px 8px; }
        .draggable-item { background: white; padding: 8px; margin-bottom: 5px; border: 1px solid #ccc; border-radius: 4px; cursor: grab; font-size: 0.9rem; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .draggable-item:active { cursor: grabbing; opacity: 0.6; }
        @media (max-width: 991px) { .cart-panel { flex: 0 0 340px; width: 340px; } }
        @media (max-width: 768px) { .workspace { flex-direction: column; } .cart-panel { position: absolute; bottom: 0; left: 0; right: 0; width: 100%; height: 70px; max-height: 70px; transition: height 0.3s, max-height 0.3s; border-top: 4px solid #3e2723; flex: 0 0 auto; } .cart-panel.expanded { height: 85vh; max-height: 85vh; } }
    </style>
</head>
<body>
    <?php if ($locationId == 0): ?>
    <div class="modal fade show" id="compulsoryLocationModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.9); z-index: 1060; position: fixed; top: 0; left: 0; width: 100%; height: 100%;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-warning"><div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold">Select Workstation</h5></div><div class="modal-body bg-light p-4"><form method="POST"><?php foreach($sellableLocations as $loc): ?><button name="set_pos_location" value="<?= $loc['id'] ?>" class="btn btn-white border w-100 mb-2 py-3 fw-bold text-start shadow-sm"><?= htmlspecialchars($loc['name']) ?></button><?php endforeach; ?></form></div></div></div></div>
    <?php endif; ?>

    <?php if ($locationId > 0 && $pendingShift): ?>
    <div class="modal fade show" id="pendingShiftModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.8); z-index: 1060; position: fixed; top: 0; left: 0; width: 100%; height: 100%;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-warning"><div class="modal-header bg-warning text-dark"><h5 class="modal-title fw-bold">Awaiting Manager Approval</h5></div><div class="modal-body text-center p-4"><p>Shift #<?= $pendingShift['id'] ?> is pending approval.</p><form method="POST"><input type="hidden" name="approve_shift_start" value="1"><input type="hidden" name="pending_shift_id" value="<?= $pendingShift['id'] ?>"><input type="text" name="mgr_username" class="form-control mb-2" placeholder="Manager Username" required><input type="password" name="mgr_password" class="form-control mb-3" placeholder="Manager Password" required><button type="submit" class="btn btn-warning w-100 fw-bold">APPROVE & START</button></form></div></div></div></div>
    <?php endif; ?>

    <?php if ($locationId > 0 && !$activeShiftId && !$pendingShift): ?>
    <div class="modal fade show" id="startShiftModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.8); z-index: 1060; position: fixed; top: 0; left: 0; width: 100%; height: 100%;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-primary"><div class="modal-header bg-primary text-white"><h5 class="modal-title fw-bold">Start New Shift</h5></div><form method="POST"><div class="modal-body p-4"><input type="hidden" name="request_start_shift" value="1"><label class="fw-bold small text-muted">OPENING FLOAT</label><div class="input-group input-group-lg mb-3"><span class="input-group-text fw-bold">ZMW</span><input type="number" step="0.01" name="starting_cash" class="form-control fw-bold" required placeholder="0.00"></div><button type="submit" class="btn btn-primary w-100 fw-bold py-3 mb-2">REQUEST APPROVAL</button><a href="index.php?page=dashboard" class="btn btn-outline-secondary w-100 fw-bold">GO TO DASHBOARD</a></div></form></div></div></div>
    <?php endif; ?>

    <div class="header-custom p-2 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center ps-2">
            <span class="fs-5 fw-bold text-warning me-3">POS</span>
            <span class="text-light ms-2"><i class="bi bi-geo-alt-fill text-warning"></i> <?= htmlspecialchars($locationName) ?></span>
            <button class="btn btn-sm btn-link text-warning ms-1" data-bs-toggle="modal" data-bs-target="#locationModal"><i class="bi bi-pencil-square"></i></button>
        </div>
        <div class="d-flex gap-2 pe-2">
            <?php if(in_array($_SESSION['role'] ?? '', ['admin','manager','dev','chef','head_chef']) && defined('LICENSE_TIER') && LICENSE_TIER === 'hospitality'): ?>
                <a href="index.php?page=menu" class="btn btn-outline-success btn-sm fw-bold"><i class="bi bi-list-ul"></i> Menu</a>
                <a href="index.php?page=kitchen" class="btn btn-outline-danger btn-sm fw-bold"><i class="bi bi-fire"></i> Produce</a>
            <?php endif; ?>
            
            <?php if ($activeShiftId): ?>
                <button class="btn btn-warning text-dark btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#expenseModal"><i class="bi bi-cash-stack"></i> Payout</button>
                <button onclick="showShiftReport(<?= $activeShiftId ?>)" class="btn btn-outline-info text-white border-white btn-sm fw-bold"><i class="bi bi-printer"></i> X-Read</button>
            <?php endif; ?>

            <?php if (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?>
                <button class="btn btn-outline-warning btn-sm fw-bold position-relative" onclick="showPickupModal()">
                    <i class="bi bi-bag-check"></i> Pickup
                    <span id="posReadyBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none; font-size: 0.6rem; padding: 0.35em 0.5em;">0</span>
                </button>
                <button class="btn btn-outline-light btn-sm" onclick="new bootstrap.Modal(document.getElementById('tabsModal')).show()"><i class="bi bi-receipt"></i> Tabs</button>
            <?php endif; ?>

            <?php if ($activeShiftId): ?>
                <button class="btn btn-danger btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#endShiftModal"><i class="bi bi-power"></i> End</button>
            <?php else: ?>
                <span class="badge bg-secondary">LOCKED</span>
            <?php endif; ?>
            <a href="index.php?page=dashboard" class="btn btn-outline-light btn-sm"><i class="bi bi-house"></i></a>
        </div>
    </div>

    <div class="workspace" style="<?= (!$activeShiftId) ? 'filter: blur(5px); pointer-events: none;' : '' ?>">
        <div class="product-section">
            <div class="bg-white p-2 border-bottom d-flex align-items-center gap-3">
                <input type="text" id="search" class="form-control form-control-lg flex-grow-1" placeholder="Search..." onkeyup="filter()">
                <div class="form-check form-switch fs-5">
                    <input class="form-check-input border-dark" type="checkbox" id="inStockToggle" onchange="filter()">
                    <label class="form-check-label fw-bold text-dark small mt-1" for="inStockToggle">In-Stock Only</label>
                </div>
            </div>
            <div class="category-bar">
                <div class="cat-pill active" onclick="filterCat('all', this)">ALL CATEGORIES</div>
                <?php foreach($categories as $cat): ?>
                    <div class="cat-pill" onclick="filterCat('<?= $cat['id'] ?>', this)"><?= htmlspecialchars($cat['name']) ?></div>
                <?php endforeach; ?>
                <div style="width: 1px; height: 20px; background: #ddd; display: inline-block; vertical-align: middle; margin: 0 10px;"></div>
                <div class="cat-pill text-warning border-warning bg-dark" onclick="switchTab('services', this)"><i class="bi bi-stars"></i> SERVICES</div>
            </div>
            
            <div class="product-list-wrapper">
                <div class="product-list">
                    <div id="items-grid" class="row g-2">
                        <?php foreach($products as $p): 
                            $hasRecipe = ($p['is_recipe'] > 0);
                            $isOut = ($p['stock_qty'] <= 0 && !$hasRecipe); 
                        ?>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2 item" data-cat="<?= $p['category_id'] ?>" data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>" data-out="<?= $isOut ? '1' : '0' ?>">
                            <form method="POST" class="h-100">
                                <input type="hidden" name="add_item" value="1">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="item-card position-relative p-2" <?= $isOut ? 'disabled="disabled"' : '' ?>>
                                    <?php if($hasRecipe): ?>
                                        <span class="stock-badge bg-recipe">Made to Order</span>
                                    <?php else: ?>
                                        <span class="stock-badge <?= $isOut ? 'bg-low' : 'bg-ok' ?>"><?= $p['stock_qty'] ?></span>
                                    <?php endif; ?>
                                    
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
            <div class="cart-header d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-bold"><i class="bi bi-basket3-fill me-2"></i> Order</h5>
                <button class="btn btn-sm btn-outline-light d-md-none" onclick="toggleCart()">Toggle</button>
            </div>
            <div class="cart-items">
                <?php if (empty($_SESSION['cart'])): ?>
                    <div class="text-center mt-5 text-muted"><p>Cart is empty</p></div>
                <?php else: foreach ($_SESSION['cart'] as $key => $item): ?>
                <div class="cart-item">
                    <div class="flex-grow-1 me-2 overflow-hidden">
                        <div class="fw-bold text-truncate"><?= htmlspecialchars($item['name']) ?></div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <small class="text-muted">@ ZMW <?= number_format($item['price'], 2) ?></small>
                            
                            <?php 
                            $isFood = in_array(strtolower($item['cat_type'] ?? ''), ['food', 'meal']);
                            if ($isFood && defined('LICENSE_TIER') && LICENSE_TIER === 'hospitality'): 
                            ?>
                                <span class="badge bg-warning text-dark border shadow-sm" style="font-size:0.7rem;"><i class="bi bi-fire"></i> Kitchen</span>
                            <?php else: ?>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="toggle_fulfillment" value="1"><input type="hidden" name="cart_key" value="<?= $key ?>">
                                    <?php if (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?>
                                        <button class="btn btn-sm btn-fulfillment <?= ($item['fulfillment']??'collected')=='collected'?'btn-outline-success':'btn-warning' ?>"><?= ($item['fulfillment']??'collected')=='collected' ? 'Got It' : 'Later' ?></button>
                                    <?php else: ?>
                                        <span class="badge bg-success">Collected</span>
                                    <?php endif; ?>
                                </form>
                            <?php endif; ?>

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
                    <span class="fs-2 fw-bold text-dark lh-1">ZMW <?= number_format($balance, 2) ?></span>
                </div>
                <div class="d-grid gap-2 mb-3">
                    <button class="btn w-100 py-3 btn-charge shadow" data-bs-toggle="modal" data-bs-target="#checkoutModal" onclick="initCheckout()" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>CHARGE</button>
                    
                    <?php if (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?>
                    <div class="btn-group w-100">
                        <button class="btn btn-warning fw-bold py-2" onclick="new bootstrap.Modal(document.getElementById('addToTabModal')).show()" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>ADD TO TAB</button>
                        <button class="btn btn-outline-dark fw-bold py-2" onclick="openSplitModal()" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>SPLIT</button>
                    </div>
                    <?php endif; ?>

                    <div class="row g-2">
                        <div class="col-<?= (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])) ? '6' : '12' ?>">
                            <form method="POST" onsubmit="confirmAction(event, 'Clear Cart?', 'Empty order?')">
                                <input type="hidden" name="clear_cart" value="1">
                                <button class="btn btn-outline-danger w-100 btn-sm fw-bold" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>CLEAR</button>
                            </form>
                        </div>
                        <?php if (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?>
                        <div class="col-6">
                            <form method="POST" onsubmit="confirmAction(event, 'Log Waste?', 'Deduct as loss?')">
                                <input type="hidden" name="log_waste" value="1">
                                <button class="btn btn-dark w-100 btn-sm fw-bold text-warning" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>LOST STOCK</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="expenseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg border-top border-warning border-4">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-cash-stack text-warning"></i> Log Petty Cash / Payout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <input type="hidden" name="log_expense" value="1">
                        <div class="alert alert-warning small border-warning">
                            <strong><i class="bi bi-info-circle"></i> Note:</strong> This will instantly deduct cash from your Expected Drawer Total.
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Amount Taken (ZMW)</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white fw-bold border-end-0">ZMW</span>
                                <input type="number" step="0.01" name="expense_amount" class="form-control fw-bold border-start-0 text-danger" required placeholder="0.00">
                            </div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Reason / Paid To</label>
                            <input type="text" name="expense_reason" class="form-control" required placeholder="e.g. Paid delivery driver, bought ice...">
                        </div>
                        
                        <div class="p-3 bg-dark rounded shadow-sm border border-secondary">
                            <label class="form-label small fw-bold text-warning mb-3 d-block border-bottom border-secondary pb-2">
                                <i class="bi bi-shield-lock-fill"></i> MANAGER AUTHORIZATION REQUIRED
                            </label>
                            <input type="text" name="mgr_username" class="form-control mb-2" required placeholder="Manager Username">
                            <input type="password" name="mgr_password" class="form-control" required placeholder="Manager Password">
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0">
                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning fw-bold shadow-sm px-4">Authorize Payout</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="reportModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content border-0 shadow-lg"><div class="modal-header bg-info text-dark"><h5 class="modal-title fw-bold" id="reportTitle"><i class="bi bi-file-earmark-text"></i> X-Read</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0" style="height: 65vh;"><iframe id="reportFrame" src="" style="width:100%; height:100%; border:none;"></iframe></div><div class="modal-footer bg-light"><button type="button" class="btn btn-info fw-bold px-4" onclick="document.getElementById('reportFrame').contentWindow.print()"><i class="bi bi-printer"></i> PRINT X-READ</button><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    
    <div class="modal fade" id="tabsModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content h-100"><div class="modal-header bg-dark text-white"><h5>Active Tabs</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="row h-100"><div class="col-4 border-end overflow-auto"><div class="list-group">
    <?php foreach($openTabs as $t): 
        $isPaid = $t['payment_status'] === 'paid'; 
        $bg = $isPaid ? 'bg-success-subtle' : ''; 
        $badge = $isPaid ? '<span class="badge bg-success">PAID</span>' : '';
    ?>
    <button class="list-group-item list-group-item-action <?= $bg ?>" onclick="showTabDetails(<?= $t['id'] ?>)">
        <div class="d-flex justify-content-between"><strong><?= htmlspecialchars($t['customer_name']) ?></strong> <?= $badge ?></div>
        <div class="small text-muted">ZMW <?= number_format($t['final_total'],2) ?></div>
    </button>
    <?php endforeach; ?>
    </div></div><div class="col-8 p-3" id="tabDetailContainer"><p class="text-center text-muted mt-5">Select a tab</p>
    <?php foreach($tabItems as $tid => $items): ?><div id="tab-data-<?= $tid ?>" class="d-none"><table class="table align-middle">
    <?php foreach($items as $i): 
        $statusBadge = '';
        if($i['status']=='pending') $statusBadge = '<span class="badge bg-danger">PENDING</span>';
        elseif($i['status']=='cooking') $statusBadge = '<span class="badge bg-warning text-dark">COOKING</span>';
        elseif($i['status']=='ready') $statusBadge = '<span class="badge bg-info text-dark">READY</span>';
    ?>
    <tr id="item-row-<?= $i['id'] ?>">
        <td><?= $i['quantity'] ?>x <?= htmlspecialchars($i['name']) ?> <?= $statusBadge ?></td>
        <td class="text-end">
            <?php if($i['fulfillment_status'] == 'uncollected'): ?>
                <span class="badge badge-uncollected" onclick="markCollected(<?= $i['id'] ?>, <?= $tid ?>)">MARK COLLECTED</span>
            <?php else: ?><span class="badge bg-success">COLLECTED</span><?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?></table><form method="POST"><input type="hidden" name="recall_tab" value="1"><input type="hidden" name="sale_id" value="<?= $tid ?>"><button class="btn btn-primary w-100">PAY / EDIT</button></form></div><?php endforeach; ?></div></div></div></div></div></div>

    <div class="modal fade" id="pickupModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content h-100"><div class="modal-body p-0"><iframe id="pickupFrame" src="" style="width:100%; height:80vh; border:none;"></iframe></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <div class="modal fade" id="receiptModal" tabindex="-1"><div class="modal-dialog modal-sm"><div class="modal-content"><div class="modal-header bg-success text-white"><h5>Receipt</h5></div><div class="modal-body p-0" style="height:400px;"><iframe id="receiptFrame" src="" style="width:100%; height:100%; border:none;"></iframe></div><div class="modal-footer"><button class="btn btn-primary" onclick="document.getElementById('receiptFrame').contentWindow.print()">PRINT</button><button class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div></div></div></div>
    <div class="modal fade" id="addToTabModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-warning text-dark"><h5 class="modal-title fw-bold">Add to Tab</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="POST"><input type="hidden" name="add_to_tab_action" value="1"><div class="list-group mb-3"><label class="list-group-item list-group-item-action active"><input class="form-check-input me-1" type="radio" name="target_tab_id" value="new" checked> Create New Tab</label><?php foreach($openTabs as $t): if($t['payment_status'] !== 'paid'): ?><label class="list-group-item list-group-item-action"><input class="form-check-input me-1" type="radio" name="target_tab_id" value="<?= $t['id'] ?>"> <strong><?= htmlspecialchars($t['customer_name']) ?></strong></label><?php endif; endforeach; ?></div><input type="text" name="tab_customer_name" class="form-control" placeholder="Customer Name"><button class="btn btn-warning w-100 fw-bold mt-3">CONFIRM</button></form></div></div></div></div>
    
    <div class="modal fade" id="checkoutModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0"><div class="modal-header bg-warning text-dark"><h5 class="modal-title fw-bold">Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><form method="POST"><div class="modal-body"><input type="hidden" name="checkout" value="1">
        <?php if(isset($_SESSION['pos_member']) && defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?><div class="alert alert-info border-info d-flex align-items-center justify-content-between mb-3 p-2 shadow-sm"><div class="d-flex align-items-center"><i class="bi bi-star-fill text-warning fs-4 me-3"></i><div><div class="fw-bold">Member: <?= htmlspecialchars($_SESSION['pos_member']['name']) ?></div><div class="small text-muted">Eligible for benefits</div></div></div><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="discountToggle" name="apply_discount" value="1" onchange="toggleDiscount()"><label class="form-check-label fw-bold small" for="discountToggle">10% OFF</label></div></div><?php endif; ?>
        <div class="text-center mb-4"><small class="text-muted text-uppercase fw-bold">Amount To Pay</small><div class="display-4 fw-bold text-dark">ZMW <span id="displayTotalDue"><?= number_format($balance, 2) ?></span></div><small class="text-success fw-bold" id="discountLabel" style="display:none;">(Discount Applied)</small></div>
        <div class="input-group mb-3"><span class="input-group-text bg-light fw-bold">Tip</span><input type="number" step="0.01" name="tip_amount" id="tipInput" class="form-control" placeholder="0.00" onkeyup="calcResult()"><button type="button" class="btn btn-outline-secondary" onclick="addTipPercent(0.05)">5%</button><button type="button" class="btn btn-outline-secondary" onclick="addTipPercent(0.10)">10%</button><button type="button" class="btn btn-outline-secondary" onclick="addTipPercent(0.15)">15%</button></div>
        <div class="mb-3"><input type="text" name="customer_name" class="form-control" placeholder="Customer Name" value="<?= $_SESSION['current_customer'] ?? 'Walk-in' ?>" <?= isset($_SESSION['pos_member']) ? 'readonly' : '' ?>></div>
        
        <div class="btn-group w-100 mb-3 <?= (defined('LICENSE_TIER') && LICENSE_TIER === 'lite') ? 'd-none' : '' ?>" role="group"><input type="radio" class="btn-check" name="is_split" id="modeSingle" value="0" checked onchange="toggleMode()"><label class="btn btn-outline-dark fw-bold" for="modeSingle">Single Pay</label><input type="radio" class="btn-check" name="is_split" id="modeSplit" value="1" onchange="toggleMode()"><label class="btn btn-outline-dark fw-bold" for="modeSplit">Split Pay</label></div>
        
        <div id="singleSection">
            <div class="mb-3">
                <select name="payment_method" class="form-select form-select-lg fw-bold">
                    <option value="Cash" selected>Cash</option>
                    <option value="Card">Card</option>
                    <option value="MTN Money">MTN Money</option>
                    <option value="Airtel Money">Airtel Money</option>
                    <option value="Zamtel Money">Zamtel Money</option>
                    <?php if (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?>
                    <option value="Pending">Put on Tab</option>
                    <?php endif; ?>
                </select>
            </div>
        </div>
        
        <div id="splitSection" style="display:none;"><div class="row g-2 mb-2"><div class="col-5"><select name="method_1" class="form-select fw-bold"><option value="Cash">Cash</option><option value="Card">Card</option><option value="MTN Money">MTN</option></select></div><div class="col-7"><div class="input-group"><span class="input-group-text">ZMW</span><input type="number" step="0.01" name="amount_1" id="splitInput1" class="form-control fw-bold" placeholder="0.00" onkeyup="sumSplit()"></div></div></div><div class="row g-2 mb-3"><div class="col-5"><select name="method_2" class="form-select fw-bold"><option value="Card" selected>Card</option><option value="Cash">Cash</option><option value="Airtel Money">Airtel</option></select></div><div class="col-7"><div class="input-group"><span class="input-group-text">ZMW</span><input type="number" step="0.01" name="amount_2" id="splitInput2" class="form-control fw-bold" placeholder="0.00" onkeyup="sumSplit()"></div></div></div></div>
        
        <div class="card bg-light border-0 p-3 mt-3"><label class="form-label small fw-bold text-muted mb-1">TOTAL TENDERED</label><div class="input-group input-group-lg"><span class="input-group-text bg-white border-end-0 fw-bold">ZMW</span><input type="number" step="0.01" name="amount_tendered" id="tenderedInput" class="form-control border-start-0 fw-bold fs-3 text-success" value="<?= $balance ?>" oninput="calcResult()" onkeyup="calcResult()"></div><div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top"><div class="small fw-bold text-uppercase text-muted" id="resultLabel">Change Due</div><div class="fs-4 fw-bold text-dark" id="resultValue">ZMW 0.00</div></div></div></div><div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning w-100 fw-bold py-3 shadow-sm">COMPLETE TRANSACTION</button></div></form></div></div></div>
    
    <div class="modal fade" id="splitBillModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header bg-dark text-white d-flex justify-content-between"><div><h5 class="modal-title"><i class="bi bi-layout-split me-2"></i> Split Bill</h5><small class="text-muted" id="splitTotalDisplay">Total: ZMW 0.00</small></div><div><div class="btn-group me-3"><button class="btn btn-sm btn-outline-light active" id="btnSplitItem" onclick="setSplitMode('item')">By Item</button><button class="btn btn-sm btn-outline-light" id="btnSplitEven" onclick="setSplitMode('even')">Split Evenly</button></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div></div><div class="modal-body bg-light"><div class="split-container"><div class="split-pool" id="unassignedPool" ondrop="drop(event)" ondragover="allowDrop(event)"><h6 class="text-muted fw-bold text-center mb-3">Unassigned Items</h6></div><div class="split-guest-zone" id="guestZone"></div><button class="btn btn-outline-primary" style="height: 50px; align-self: center;" onclick="addGuest()"><i class="bi bi-plus-lg"></i></button></div></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" id="splitForm"><input type="hidden" name="finalize_split" value="1"><input type="hidden" name="split_type" id="splitTypeInput" value="item"><input type="hidden" name="split_data" id="splitDataInput"><button type="button" class="btn btn-success fw-bold px-4" onclick="submitSplit()">FINALIZE & PAY</button></form></div></div></div></div>
    <div class="modal fade" id="openPriceModal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Enter Amount</h5></div><div class="modal-body"><form method="POST" id="openPriceForm"><input type="hidden" name="add_item" value="1"><input type="hidden" name="product_id" id="op_pid"><div class="mb-3"><label id="op_name" class="form-label fw-bold"></label><input type="number" step="0.01" name="custom_price" class="form-control form-control-lg fw-bold text-center" autofocus required></div><button class="btn btn-primary w-100 fw-bold">Add to Cart</button></form></div></div></div></div>
    
    <div class="modal fade" id="endShiftModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg"><div class="modal-header bg-danger text-white"><h5 class="modal-title fw-bold">End Shift</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><form action="index.php?page=end_shift_action" method="POST"><div class="modal-body p-4"><div class="alert alert-light border text-center mb-4"><small class="text-uppercase fw-bold text-muted">Expected Cash</small><div class="h2 fw-bold text-dark m-0">ZMW <?= number_format($expectedShiftCash ?? 0, 2) ?></div></div><label class="fw-bold small text-muted">ACTUAL CLOSING CASH</label><div class="input-group input-group-lg mb-3"><span class="input-group-text fw-bold">ZMW</span><input type="number" step="0.01" name="closing_cash" class="form-control fw-bold text-primary" required value="<?= $expectedShiftCash ?>"></div><label class="fw-bold small text-muted">VARIANCE REASON</label><textarea name="variance_reason" class="form-control mb-3" placeholder="Explain any difference..."></textarea><label class="fw-bold small text-danger mt-2">MANAGER PASSWORD</label><input type="password" name="manager_password" class="form-control" required placeholder="Required for verification"></div><div class="modal-footer border-0"><button type="submit" class="btn btn-danger w-100 fw-bold py-3 shadow">CLOSE SHIFT</button></div></form></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // --- PAGINATION & FILTER LOGIC ---
        let currentPage = 1;
        const itemsPerPage = 24;
        let activeItems = [];
        let currentCat = 'all';

        document.addEventListener('DOMContentLoaded', function() {
            if (localStorage.getItem('posInStockToggle') === 'true') {
                let toggle = document.getElementById('inStockToggle');
                if(toggle) toggle.checked = true;
            }
            initPagination();
            
            <?php if(isset($_SESSION['last_sale_id'])): ?>
                document.getElementById('receiptFrame').src = "index.php?page=receipt&sale_id=<?= $_SESSION['last_sale_id'] ?>";
                new bootstrap.Modal(document.getElementById('receiptModal')).show();
            <?php unset($_SESSION['last_sale_id']); endif; ?>
        });

        function initPagination() {
            applyFilters();
        }

        function applyFilters() {
            let v = document.getElementById('search').value.toLowerCase();
            let showInStockOnly = false;
            let toggleElement = document.getElementById('inStockToggle');
            
            if (toggleElement) {
                showInStockOnly = toggleElement.checked;
                localStorage.setItem('posInStockToggle', showInStockOnly);
            }
            
            let allItems = Array.from(document.querySelectorAll('#items-grid .item'));
            
            activeItems = allItems.filter(e => {
                let matchCat = (currentCat === 'all' || e.dataset.cat === currentCat);
                let matchName = e.dataset.name.includes(v);
                let matchStock = true;
                
                if (showInStockOnly) {
                    matchStock = e.dataset.out === "0";
                }
                
                return matchCat && matchName && matchStock;
            });
            renderPage(1);
        }

        function filterCat(id, btn) { 
            if(btn) {
                document.querySelectorAll('.cat-pill').forEach(e=>e.classList.remove('active')); 
                btn.classList.add('active'); 
            }
            currentCat = id;
            switchTab('items'); 
            applyFilters();
        }

        function filter() { applyFilters(); }

        function switchTab(tab, btn) { 
            if(btn) {
                document.querySelectorAll('.cat-pill').forEach(e=>e.classList.remove('active')); 
                btn.classList.add('active'); 
            }
            if (tab === 'services') { 
                document.getElementById('items-grid').style.display = 'none'; 
                document.getElementById('services-grid').style.display = 'flex'; 
                document.getElementById('paginationBar').style.display = 'none';
            } else { 
                document.getElementById('items-grid').style.display = 'flex'; 
                document.getElementById('services-grid').style.display = 'none'; 
                document.getElementById('paginationBar').style.display = 'flex';
                applyFilters();
            } 
        }

        function renderPage(page) {
            currentPage = page;
            const totalPages = Math.ceil(activeItems.length / itemsPerPage) || 1;
            if (currentPage > totalPages) currentPage = totalPages;
            if (currentPage < 1) currentPage = 1;

            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;

            document.querySelectorAll('#items-grid .item').forEach(el => el.style.display = 'none');
            activeItems.slice(startIndex, endIndex).forEach(el => { el.style.display = 'block'; });

            document.getElementById('pageInfo').innerText = `Page ${currentPage} of ${totalPages}`;
        }

        function prevPage() { if(currentPage > 1) renderPage(currentPage - 1); }
        function nextPage() { const totalPages = Math.ceil(activeItems.length / itemsPerPage); if(currentPage < totalPages) renderPage(currentPage + 1); }

        // --- CART & MODALS LOGIC ---
        function toggleCart() { document.getElementById('cartPanel').classList.toggle('expanded'); }
        function addService(id, name, price, isOpen) { if (isOpen) { document.getElementById('op_pid').value = id; document.getElementById('op_name').innerText = name; new bootstrap.Modal(document.getElementById('openPriceModal')).show(); } else { let f = document.createElement('form'); f.method = 'POST'; f.innerHTML = `<input type="hidden" name="add_item" value="1"><input type="hidden" name="product_id" value="${id}">`; document.body.appendChild(f); f.submit(); } }
        function showPickupModal() { document.getElementById('pickupFrame').src = "index.php?page=pickup&embedded=1"; new bootstrap.Modal(document.getElementById('pickupModal')).show(); }
        function showTabDetails(id) { document.getElementById('tabDetailContainer').innerHTML = document.getElementById('tab-data-' + id).innerHTML; }

        function markCollected(itemId, saleId) {
            fetch(window.location.href, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: 'mark_collected=1&item_id=' + itemId })
            .then(r => r.json())
            .then(data => {
                if (data.status === 'success') {
                    if (data.print_receipt) {
                        document.getElementById('receiptFrame').src = "index.php?page=receipt&sale_id=" + data.sale_id + "&collection_only=" + data.item_id;
                        new bootstrap.Modal(document.getElementById('receiptModal')).show();
                    }
                    if (data.tab_completed) { Swal.fire({ icon: 'success', title: 'Tab Completed', timer: 1500, showConfirmButton: false }).then(() => location.reload()); } 
                    else { const row = document.getElementById('item-row-' + itemId); if(row) row.querySelector('.text-end').innerHTML = '<span class="badge bg-success">COLLECTED</span>'; }
                } else if (data.status === 'redirect_pickup') {
                    Swal.fire({ icon: 'info', title: 'Collect at Pickup', text: data.msg, showCancelButton: true, confirmButtonText: 'Open Pickup Screen' }).then((result) => { if (result.isConfirmed) { showPickupModal(); } });
                } else { Swal.fire({ icon: 'error', title: 'Action Blocked', text: data.msg }); }
            });
        }
        
        function showShiftReport(shiftId) { document.getElementById('reportTitle').innerText = "X-Read (Open Shift)"; document.getElementById('reportFrame').src = "index.php?page=print_shift&shift_id=" + shiftId; new bootstrap.Modal(document.getElementById('reportModal')).show(); }
        
        let baseTotal = <?= $balance ?? 0 ?>; let currentTotal = baseTotal;
        function initCheckout() { 
            currentTotal = baseTotal; 
            if(document.getElementById('discountToggle')) { document.getElementById('discountToggle').checked = false; toggleDiscount(); } 
            else { updateDisplays(); } 
            document.getElementById('modeSingle').checked = true; toggleMode(); 
            new bootstrap.Modal(document.getElementById('checkoutModal')).show(); 
        }
        function toggleDiscount() { let chk = document.getElementById('discountToggle'); if(chk && chk.checked) { currentTotal = baseTotal * 0.90; document.getElementById('discountLabel').style.display = 'block'; } else { currentTotal = baseTotal; document.getElementById('discountLabel').style.display = 'none'; } updateDisplays(); }
        function updateDisplays() { document.getElementById('displayTotalDue').innerText = currentTotal.toFixed(2); document.getElementById('tenderedInput').value = currentTotal.toFixed(2); calcResult(); }
        function toggleMode() { let isSplit = document.getElementById('modeSplit').checked; document.getElementById('singleSection').style.display = isSplit ? 'none' : 'block'; document.getElementById('splitSection').style.display = isSplit ? 'block' : 'none'; if(isSplit) { document.getElementById('splitInput1').value = ""; document.getElementById('splitInput2').value = ""; document.getElementById('tenderedInput').value = "0.00"; } else { document.getElementById('tenderedInput').value = currentTotal.toFixed(2); } calcResult(); }
        function sumSplit() { let val1 = parseFloat(document.getElementById('splitInput1').value) || 0; let val2 = parseFloat(document.getElementById('splitInput2').value) || 0; document.getElementById('tenderedInput').value = (val1 + val2).toFixed(2); calcResult(); }
        function addTipPercent(percent) { let tip = currentTotal * percent; document.getElementById('tipInput').value = tip.toFixed(2); calcResult(); }
        function calcResult() { 
            let tendered = parseFloat(document.getElementById('tenderedInput').value) || 0;
            let tip = parseFloat(document.getElementById('tipInput').value) || 0;
            let diff = tendered - (currentTotal + tip);
            let label = document.getElementById('resultLabel'); let value = document.getElementById('resultValue'); 
            if(diff >= -0.01) { label.innerText = "CHANGE DUE"; label.className = "small fw-bold text-uppercase text-muted"; value.innerText = "ZMW " + diff.toFixed(2); value.className = "fs-4 fw-bold text-dark"; } 
            else { label.innerText = "BALANCE REMAINING"; label.className = "small fw-bold text-uppercase text-danger"; value.innerText = "ZMW " + Math.abs(diff).toFixed(2); value.className = "fs-4 fw-bold text-danger"; } 
        }

        function confirmAction(event, title, text, confirmBtn='Yes') { event.preventDefault(); Swal.fire({ title: title, text: text, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: confirmBtn }).then((result) => { if (result.isConfirmed) { event.target.submit(); } }); }
        
        let cartItems = <?= json_encode($_SESSION['cart'] ?? []) ?>;
        let guests = [];
        let unassigned = [];
        let splitMode = 'item'; 

        function openSplitModal() {
            unassigned = [];
            guests = [{ id: 1, name: "Guest 1", items: [] }, { id: 2, name: "Guest 2", items: [] }];
            Object.keys(cartItems).forEach(key => {
                let item = cartItems[key];
                for(let i=0; i<item.qty; i++) {
                     unassigned.push({ id: item.product_id, name: item.name, price: item.price, type: item.type, uid: key + '-' + i });
                }
            });
            renderSplitUI();
            new bootstrap.Modal(document.getElementById('splitBillModal')).show();
        }

        function setSplitMode(mode) {
            splitMode = mode;
            document.getElementById('splitTypeInput').value = mode;
            document.getElementById('btnSplitItem').classList.toggle('active', mode === 'item');
            document.getElementById('btnSplitEven').classList.toggle('active', mode === 'even');
            renderSplitUI();
        }

        function addGuest() { guests.push({ id: guests.length + 1, name: "Guest " + (guests.length + 1), items: [] }); renderSplitUI(); }

        function renderSplitUI() {
            const pool = document.getElementById('unassignedPool');
            const zone = document.getElementById('guestZone');
            const totalDisplay = document.getElementById('splitTotalDisplay');
            let grandTotal = 0;

            pool.innerHTML = '<h6 class="text-muted fw-bold text-center mb-3">Unassigned Items</h6>';
            zone.innerHTML = '';

            if (splitMode === 'item') {
                unassigned.forEach(item => {
                    grandTotal += parseFloat(item.price);
                    pool.appendChild(createDragEl(item, 'pool'));
                });
            } else {
                pool.innerHTML = '<div class="text-center text-muted mt-5"><i>Even Split Mode Active<br>Items are auto-distributed</i></div>';
            }

            guests.forEach((g, index) => {
                let gTotal = 0;
                if (splitMode === 'item') { g.items.forEach(item => gTotal += parseFloat(item.price)); } 
                else { let cartTotal = 0; Object.values(cartItems).forEach(i => cartTotal += (i.price * i.qty)); gTotal = cartTotal / guests.length; grandTotal = cartTotal; }

                let col = document.createElement('div');
                col.className = 'guest-col';
                col.innerHTML = `
                <div class="guest-header p-2">
                    <input type="text" class="form-control form-control-sm fw-bold text-center" value="${g.name}" onchange="guests[${index}].name = this.value" placeholder="Guest Name">
                    <div class="small mt-1">ZMW ${gTotal.toFixed(2)}</div>
                </div>
                <div class="guest-items" ondrop="drop(event, ${index})" ondragover="allowDrop(event)">
                    ${splitMode === 'item' ? '' : '<small class="text-muted d-block text-center mt-4">1/' + guests.length + ' Share</small>'}
                </div>
                <div class="guest-footer">
                    <div class="input-group input-group-sm mb-2"><span class="input-group-text">Tip</span><input type="number" step="0.01" class="form-control" placeholder="0.00" oninput="guests[${index}].tip = this.value"></div>
                    <select class="form-select form-select-sm mb-2" onchange="guests[${index}].method = this.value">
                        <option value="Cash">Cash</option><option value="Card">Card</option><option value="Pending">On Tab</option>
                    </select>
                </div>`;
                
                if (splitMode === 'item') { g.items.forEach(item => { col.querySelector('.guest-items').appendChild(createDragEl(item, 'guest', index)); }); }
                g.method = g.method || 'Cash'; 
                zone.appendChild(col);
            });
            
            totalDisplay.innerText = "Cart Total: ZMW " + grandTotal.toFixed(2);
        }

        function createDragEl(item, source, guestIdx = null) {
            let d = document.createElement('div');
            d.className = 'draggable-item';
            d.draggable = (splitMode === 'item');
            d.innerHTML = `<div class="d-flex justify-content-between"><span>${item.name}</span><span class="fw-bold">${parseFloat(item.price).toFixed(2)}</span></div>`;
            d.id = item.uid;
            d.ondragstart = (e) => { e.dataTransfer.setData("text", JSON.stringify({ uid: item.uid, source: source, guestIdx: guestIdx })); };
            return d;
        }

        function allowDrop(ev) { ev.preventDefault(); }

        function drop(ev, targetGuestIdx = null) {
            ev.preventDefault();
            if (splitMode !== 'item') return;
            let data = JSON.parse(ev.dataTransfer.getData("text"));
            let item;
            if (data.source === 'pool') { let idx = unassigned.findIndex(i => i.uid === data.uid); item = unassigned.splice(idx, 1)[0]; } 
            else { let idx = guests[data.guestIdx].items.findIndex(i => i.uid === data.uid); item = guests[data.guestIdx].items.splice(idx, 1)[0]; }
            if (targetGuestIdx !== null) { guests[targetGuestIdx].items.push(item); } else { unassigned.push(item); }
            renderSplitUI();
        }

        function submitSplit() {
            if (splitMode === 'item' && unassigned.length > 0) { Swal.fire({ icon: 'warning', title: 'Unassigned Items', text: 'Please assign all items.' }); return; }
            let payload = guests.map(g => {
                let items = [];
                let total = 0;
                if (splitMode === 'item') {
                    let grouped = {};
                    g.items.forEach(i => { if (!grouped[i.id]) grouped[i.id] = { id: i.id, qty: 0, price: i.price, type: i.type }; grouped[i.id].qty++; total += parseFloat(i.price); });
                    items = Object.values(grouped);
                } else {
                    let cartTotal = 0; Object.values(cartItems).forEach(i => cartTotal += (i.price * i.qty));
                    total = cartTotal / guests.length;
                }
                return { name: g.name, method: g.method, tip: g.tip || 0, total: total, items: items };
            });
            document.getElementById('splitDataInput').value = JSON.stringify(payload);
            document.getElementById('splitForm').submit();
        }

        <?php if(isset($_SESSION['swal_msg'])): ?>
        Swal.fire({ icon: '<?= addslashes($_SESSION['swal_type']) ?>', title: '<?= addslashes($_SESSION['swal_msg']) ?>', timer: 1500, showConfirmButton: false });
        <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
        <?php endif; ?>

        <?php if (defined('LICENSE_TIER') && in_array(LICENSE_TIER, ['pro', 'hospitality'])): ?>
        function checkPosReadyOrders() {
            fetch('index.php?action=check_ready_orders')
            .then(r => r.json())
            .then(data => {
                let badge = document.getElementById('posReadyBadge');
                if(badge && data && data.count > 0) { 
                    badge.innerText = data.count; 
                    badge.style.display = 'block'; 
                } else if (badge) { 
                    badge.style.display = 'none'; 
                }
            }).catch(e => { console.error('POS Badge Error:', e); });
        }
        checkPosReadyOrders();
        setInterval(checkPosReadyOrders, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
