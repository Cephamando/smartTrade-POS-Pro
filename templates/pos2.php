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
        body { background-color: #f0f2f5; height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        .header-custom { background-color: #2c2c2c; border-bottom: 4px solid #ffc107; color: white; flex: 0 0 auto; z-index: 1050; }
        .workspace { flex: 1; display: flex; overflow: hidden; position: relative; }
        .product-section { flex: 1; display: flex; flex-direction: column; overflow: hidden; }
        .category-bar { background: #fff; padding: 10px; border-bottom: 1px solid #ddd; white-space: nowrap; overflow-x: auto; flex: 0 0 auto; }
        .cat-pill { display: inline-block; padding: 8px 18px; margin-right: 8px; border-radius: 50px; background: #f8f9fa; border: 1px solid #dee2e6; color: #333; font-weight: 600; cursor: pointer; transition: 0.2s; }
        .cat-pill:hover { background: #e9ecef; }
        .cat-pill.active { background: #3e2723; color: #ffc107; border-color: #3e2723; }
        .product-list { padding: 15px; overflow-y: auto; flex: 1; }
        .item-card { background: white; border: 1px solid #e0e0e0; border-radius: 8px; transition: transform 0.1s, box-shadow 0.1s; cursor: pointer; overflow: hidden; position: relative; height: 100%; }
        .item-card:hover { transform: translateY(-2px); box-shadow: 0 4px 8px rgba(0,0,0,0.1); border-color: #ffc107; }
        .item-card:active { transform: scale(0.98); }
        .item-card:disabled { opacity: 0.6; filter: grayscale(1); background: #eee; cursor: not-allowed; }
        .stock-badge { position: absolute; top: 8px; right: 8px; font-size: 0.75rem; padding: 4px 8px; border-radius: 4px; font-weight: bold; z-index: 2; }
        .bg-low { background-color: #dc3545; color: white; }
        .bg-ok { background-color: #198754; color: white; }
        .cart-panel { width: 400px; background: #fff; border-left: 1px solid #ccc; display: flex; flex-direction: column; box-shadow: -4px 0 15px rgba(0,0,0,0.1); z-index: 1000; }
        .cart-header { padding: 15px; background: #3e2723; color: white; flex: 0 0 auto; }
        .cart-items { flex: 1; overflow-y: auto; padding: 15px; background: #f8f9fa; }
        .cart-footer { padding: 20px; background: #fff; border-top: 2px solid #eee; flex: 0 0 auto; }
        .cart-item { background: white; border-radius: 6px; padding: 10px; margin-bottom: 10px; border: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; }
        .btn-fulfillment { font-size: 0.7rem; font-weight: bold; text-transform: uppercase; padding: 2px 6px; }
        .badge-uncollected { background: #fd7e14; color: white; cursor: pointer; }
        .badge-collected { background: #198754; color: white; cursor: default; }
        
        .btn-charge { background-color: #fd7e14 !important; border-color: #fd7e14 !important; color: #fff !important; font-size: 1.25rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 6px rgba(253, 126, 20, 0.3); }
        .btn-charge:hover { background-color: #e66b0d !important; }
        .btn-charge:disabled { background-color: #ccc !important; border-color: #ccc !important; box-shadow: none; cursor: not-allowed; }
        
        /* Drag & Drop Split Styling */
        .split-container { display: flex; height: 500px; gap: 15px; }
        .split-pool { flex: 1; background: #f8f9fa; border: 2px dashed #ccc; border-radius: 8px; padding: 10px; overflow-y: auto; }
        .split-guest-zone { flex: 2; display: flex; gap: 10px; overflow-x: auto; padding-bottom: 5px; }
        .guest-col { min-width: 200px; background: white; border: 1px solid #ddd; border-radius: 8px; display: flex; flex-direction: column; }
        .guest-header { padding: 10px; background: #3e2723; color: white; text-align: center; border-radius: 8px 8px 0 0; }
        .guest-items { flex: 1; padding: 10px; overflow-y: auto; background: #fff; min-height: 100px; }
        .guest-footer { padding: 10px; border-top: 1px solid #eee; background: #f1f1f1; border-radius: 0 0 8px 8px; }
        .draggable-item { background: white; padding: 8px; margin-bottom: 5px; border: 1px solid #ccc; border-radius: 4px; cursor: grab; font-size: 0.9rem; box-shadow: 0 1px 2px rgba(0,0,0,0.1); }
        .draggable-item:active { cursor: grabbing; opacity: 0.6; }

        @media (max-width: 991px) { .cart-panel { width: 340px; } }
        @media (max-width: 768px) { .workspace { flex-direction: column; } .cart-panel { position: absolute; bottom: 0; left: 0; right: 0; width: 100%; height: auto; max-height: 70px; transition: max-height 0.3s; border-top: 4px solid #3e2723; } .cart-panel.expanded { max-height: 80vh; } }
    </style>
</head>
<body>

    <?php if ($locationId == 0): ?>
    <div class="modal fade show" id="compulsoryLocationModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.9); z-index: 1060;"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-warning"><div class="modal-header bg-dark text-white"><h5 class="modal-title fw-bold"><i class="bi bi-geo-alt-fill text-warning"></i> Select Workstation</h5></div><div class="modal-body bg-light p-4"><p class="text-muted fw-bold mb-3">Please select your current station to initialize the system.</p><form method="POST"><?php foreach($sellableLocations as $loc): ?><button name="set_pos_location" value="<?= $loc['id'] ?>" class="btn btn-white border w-100 mb-2 py-3 fw-bold text-start shadow-sm d-flex justify-content-between align-items-center hover-shadow"><?= htmlspecialchars($loc['name']) ?> <i class="bi bi-chevron-right text-muted"></i></button><?php endforeach; ?><div class="mt-4 text-center"><a href="index.php?page=dashboard" class="btn btn-link text-muted fw-bold text-decoration-none"><i class="bi bi-arrow-left"></i> Back to Dashboard</a></div></form></div></div></div></div>
    <?php endif; ?>

    <?php if ($locationId > 0 && $pendingShift): ?>
    <div class="modal fade show" id="pendingShiftModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.8);"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-warning"><div class="modal-header bg-warning text-dark"><h5 class="modal-title fw-bold">Awaiting Manager Approval</h5></div><div class="modal-body text-center p-4"><p>Shift #<?= $pendingShift['id'] ?> is pending approval.</p><form method="POST"><input type="hidden" name="approve_shift_start" value="1"><input type="hidden" name="pending_shift_id" value="<?= $pendingShift['id'] ?>"><input type="text" name="mgr_username" class="form-control mb-2" placeholder="Manager Username" required><input type="password" name="mgr_password" class="form-control mb-3" placeholder="Manager Password" required><button type="submit" class="btn btn-warning w-100 fw-bold">APPROVE & START</button></form></div></div></div></div>
    <?php endif; ?>

    <?php if ($locationId > 0 && !$activeShiftId && !$pendingShift): ?>
    <div class="modal fade show" id="startShiftModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.8);"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-primary"><div class="modal-header bg-primary text-white"><h5 class="modal-title fw-bold">Start New Shift</h5></div><form method="POST"><div class="modal-body p-4"><input type="hidden" name="request_start_shift" value="1"><label class="fw-bold small text-muted">OPENING FLOAT</label><div class="input-group input-group-lg mb-3"><span class="input-group-text fw-bold">ZMW</span><input type="number" step="0.01" name="starting_cash" class="form-control fw-bold" required placeholder="0.00"></div><button type="submit" class="btn btn-primary w-100 fw-bold py-3 mb-2">REQUEST APPROVAL</button><a href="index.php?page=dashboard" class="btn btn-outline-secondary w-100 fw-bold">GO TO DASHBOARD</a></div></form></div></div></div>
    <?php endif; ?>

    <div class="header-custom p-2 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center ps-2">
            <span class="fs-5 fw-bold text-warning me-3"><i class="bi bi-grid-fill"></i> POS</span>
            <span class="d-flex align-items-center border-start border-secondary ps-3 text-light"><i class="bi bi-geo-alt-fill text-warning me-2"></i> <?= htmlspecialchars($locationName) ?></span>
            <button class="btn btn-sm btn-link text-warning ms-1" data-bs-toggle="modal" data-bs-target="#locationModal"><i class="bi bi-pencil-square"></i></button>
            <?php if(isset($_SESSION['pos_member'])): ?><form method="POST" class="d-inline"><input type="hidden" name="remove_member" value="1"><span class="badge bg-success ms-3 p-2 border border-light" title="Member Active"><i class="bi bi-person-check-fill me-1"></i> <?= htmlspecialchars($_SESSION['pos_member']['name']) ?><button type="submit" class="btn-close btn-close-white ms-2" style="font-size: 0.7em; vertical-align: middle;"></button></span></form><?php endif; ?>
        </div>
        <div class="d-flex gap-2 pe-2">
            <?php if(in_array($_SESSION['role'] ?? '', ['admin','manager','dev'])): ?>
                <button class="btn btn-sm btn-dark border border-secondary fw-bold text-warning" data-bs-toggle="modal" data-bs-target="#serviceManagerModal"><i class="bi bi-stars"></i> Services</button>
            <?php endif; ?>
            <?php if ($activeShiftId): ?>
                <button onclick="showShiftReport(<?= $activeShiftId ?>)" class="btn btn-outline-info text-white border-white btn-sm fw-bold"><i class="bi bi-printer"></i> X-Read</button>
            <?php endif; ?>
            <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#membersModal"><i class="bi bi-person-lines-fill"></i></button>
            <button class="btn btn-outline-warning btn-sm fw-bold" onclick="showPickupModal()"><i class="bi bi-bag-check"></i> Pickup</button>
            <button class="btn btn-outline-light btn-sm" onclick="new bootstrap.Modal(document.getElementById('tabsModal')).show()"><i class="bi bi-receipt"></i> Tabs</button>
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
            <div class="bg-white p-2 border-bottom d-flex gap-2">
                <input type="text" id="search" class="form-control form-control-lg" placeholder="Search..." onkeyup="filter()">
            </div>
            <div class="category-bar">
                <div class="cat-pill active" onclick="switchTab('items', this)">PRODUCTS</div>
                <div class="cat-pill text-warning border-warning bg-dark" onclick="switchTab('services', this)"><i class="bi bi-stars"></i> SERVICES</div>
                <div style="width: 1px; height: 20px; background: #ddd; display: inline-block; vertical-align: middle; margin: 0 10px;"></div>
                <div class="cat-pill" onclick="filterCat('all', this)">ALL CATEGORIES</div>
                <?php foreach($categories as $cat): ?><div class="cat-pill" onclick="filterCat('<?= $cat['id'] ?>', this)"><?= htmlspecialchars($cat['name']) ?></div><?php endforeach; ?>
            </div>
            <div class="product-list">
                <div id="items-grid" class="row g-2"><?php foreach($products as $p): $isOut = ($p['stock_qty'] <= 0); ?><div class="col-6 col-md-4 col-lg-3 col-xl-2 item" data-cat="<?= $p['category_id'] ?>" data-name="<?= strtolower($p['name']) ?>"><form method="POST" class="h-100"><input type="hidden" name="add_item" value="1"><input type="hidden" name="product_id" value="<?= $p['id'] ?>"><button type="submit" class="item-card w-100 p-2 text-start position-relative" <?= $isOut ? 'disabled' : '' ?>><span class="stock-badge <?= $isOut ? 'bg-low' : 'bg-ok' ?>"><?= $p['stock_qty'] ?></span><div style="height: 50px; overflow: hidden;" class="fw-bold text-dark mb-1 lh-sm"><?= htmlspecialchars($p['name']) ?></div><div class="text-primary fw-bold">ZMW <?= number_format($p['price'], 2) ?></div></button></form></div><?php endforeach; ?></div>
                <div id="services-grid" class="row g-2" style="display:none;"><?php foreach($services as $s): ?><div class="col-6 col-md-4 col-lg-3 col-xl-2 item"><div class="item-card w-100 p-2 text-start position-relative border-warning" onclick="addService(<?= $s['id'] ?>, '<?= $s['name'] ?>', <?= $s['price'] ?>, <?= $s['is_open_price'] ?>)"><div style="height: 50px;" class="fw-bold text-dark mb-1 lh-sm"><?= htmlspecialchars($s['name']) ?></div><div class="text-success fw-bold"><?= $s['is_open_price'] ? 'Adjustable' : 'ZMW '.number_format($s['price'], 2) ?></div><span class="position-absolute top-0 end-0 badge bg-warning text-dark m-1">Service</span></div></div><?php endforeach; ?></div>
            </div>
        </div>

        <div class="cart-panel" id="cartPanel">
            <div class="cart-header d-flex justify-content-between align-items-center"><h5 class="m-0 fw-bold"><i class="bi bi-basket3-fill"></i> Order</h5><button class="btn btn-sm btn-outline-light d-md-none" onclick="toggleCart()">Hide</button></div>
            <div class="cart-items">
                <?php if (empty($_SESSION['cart'])): ?><div class="text-center mt-5 text-muted"><i class="bi bi-cart-x display-1 opacity-25"></i><p class="mt-3 fw-bold">Cart is empty</p></div><?php else: foreach ($_SESSION['cart'] as $pid => $item): ?>
                <div class="cart-item">
                    <div class="flex-grow-1 me-2 overflow-hidden">
                        <div class="fw-bold text-truncate"><?= htmlspecialchars($item['name']) ?> <span class="badge bg-secondary text-white" style="font-size:0.6em;"><?= isset($item['type']) && $item['type'] == 'service' ? 'SVC' : '' ?></span></div>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <small class="text-muted">@ ZMW <?= number_format($item['price'], 2) ?></small>
                            <form method="POST" class="d-inline">
                                <input type="hidden" name="toggle_fulfillment" value="1"><input type="hidden" name="product_id" value="<?= $pid ?>">
                                <button class="btn btn-sm btn-fulfillment <?= ($item['fulfillment']??'collected')=='collected'?'btn-outline-success':'btn-warning' ?>" title="Toggle: Collected / Later">
                                    <?= ($item['fulfillment']??'collected')=='collected' ? '<i class="bi bi-check-circle"></i> Got It' : '<i class="bi bi-clock"></i> Later' ?>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="d-flex align-items-center bg-light rounded border"><form method="POST" class="d-flex m-0"><input type="hidden" name="product_id" value="<?= $pid ?>"><input type="hidden" name="update_qty" value="1"><button name="action" value="dec" class="btn btn-sm text-danger px-2 fw-bold border-end hover-bg">-</button><span class="px-2 fw-bold" style="min-width:30px; text-align:center; line-height:30px;"><?= $item['qty'] ?></span><button name="action" value="inc" class="btn btn-sm text-success px-2 fw-bold border-start hover-bg">+</button></form></div>
                </div>
                <?php endforeach; endif; ?>
            </div>
            <div class="cart-footer">
                <?php $tabPaid = $_SESSION['tab_paid'] ?? 0; $total = $total ?? 0; $balance = $total - $tabPaid; ?>
                <?php if($tabPaid > 0): ?><div class="d-flex justify-content-between mb-1 small text-muted"><span>Subtotal:</span><span><?= number_format($total, 2) ?></span></div><div class="d-flex justify-content-between mb-2 small text-success fw-bold"><span>Already Paid:</span><span>-<?= number_format($tabPaid, 2) ?></span></div><?php endif; ?>
                <div class="d-flex justify-content-between align-items-end mb-3"><span class="text-muted small fw-bold text-uppercase">Total Due</span><span class="fs-2 fw-bold text-dark lh-1">ZMW <?= number_format($balance, 2) ?></span></div>
                <div class="d-grid gap-2 mb-3">
                    <button class="btn w-100 py-3 btn-charge shadow" data-bs-toggle="modal" data-bs-target="#checkoutModal" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?> onclick="initCheckout()">CHARGE</button>
                    <div class="btn-group w-100">
                        <button class="btn btn-warning fw-bold py-2" onclick="openAddToTabModal()" <?= empty($_SESSION['cart'])?'disabled':'' ?>><i class="bi bi-plus-circle"></i> ADD TO TAB</button>
                        <button class="btn btn-outline-dark fw-bold py-2" onclick="openSplitModal()" <?= empty($_SESSION['cart'])?'disabled':'' ?>><i class="bi bi-layout-split"></i> SPLIT</button>
                    </div>
                    <div class="row g-2">
                        <div class="col-6"><form method="POST" onsubmit="confirmAction(event, 'Clear Cart?', 'This will empty the order.')"><input type="hidden" name="clear_cart" value="1"><button class="btn btn-outline-danger w-100 btn-sm fw-bold">CLEAR</button></form></div>
                        <div class="col-6"><form method="POST" onsubmit="confirmAction(event, 'Log Waste?', 'Items will be deducted from stock as lost.', 'Yes, Log Waste')"><input type="hidden" name="log_waste" value="1"><button class="btn btn-dark w-100 btn-sm fw-bold text-warning" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>LOST STOCK</button></form></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="addToTabModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark"><h5 class="modal-title fw-bold">Add to Tab</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">Select an active tab to append items, or create a new one.</p>
                    <form method="POST" id="addToTabForm">
                        <input type="hidden" name="add_to_tab_action" value="1">
                        <div class="list-group mb-3" style="max-height: 200px; overflow-y: auto;">
                            <label class="list-group-item list-group-item-action active" onclick="toggleTabInput(true)">
                                <input class="form-check-input me-1" type="radio" name="target_tab_id" value="new" checked>
                                <i class="bi bi-star-fill me-2"></i> Create New Tab
                            </label>
                            
                            <?php foreach($openTabs as $t): if($t['payment_status'] !== 'paid'): ?>
                            <label class="list-group-item list-group-item-action" onclick="toggleTabInput(false)">
                                <input class="form-check-input me-1" type="radio" name="target_tab_id" value="<?= $t['id'] ?>">
                                <strong><?= htmlspecialchars($t['customer_name']) ?></strong> 
                                <span class="float-end text-muted small">ZMW <?= number_format($t['final_total'],2) ?></span>
                            </label>
                            <?php endif; endforeach; ?>
                        </div>
                        
                        <div id="newTabInputDiv">
                            <label class="form-label fw-bold">Customer Name / Table</label>
                            <input type="text" name="tab_customer_name" class="form-control" placeholder="e.g., Table 5 or John Doe">
                        </div>
                        <button class="btn btn-warning w-100 fw-bold mt-3">CONFIRM & ADD</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="endShiftModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold">End Shift</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="index.php?page=end_shift_action" method="POST">
                    <div class="modal-body p-4">
                        <div class="alert alert-light border text-center mb-4">
                            <small class="text-uppercase fw-bold text-muted">Expected Cash</small>
                            <div class="h2 fw-bold text-dark m-0">ZMW <?= number_format($expectedShiftCash ?? 0, 2) ?></div>
                        </div>
                        <label class="fw-bold small text-muted">ACTUAL CLOSING CASH</label>
                        <div class="input-group input-group-lg mb-3">
                            <span class="input-group-text fw-bold">ZMW</span>
                            <input type="number" step="0.01" name="closing_cash" class="form-control fw-bold text-primary" required value="<?= $expectedShiftCash ?>">
                        </div>
                        <label class="fw-bold small text-muted">VARIANCE REASON</label>
                        <textarea name="variance_reason" class="form-control mb-3" placeholder="Explain any difference..."></textarea>
                        
                        <label class="fw-bold small text-danger mt-2">MANAGER PASSWORD</label>
                        <input type="password" name="manager_password" class="form-control" required placeholder="Required for verification">
                    </div>
                    <div class="modal-footer border-0">
                        <button type="submit" class="btn btn-danger w-100 fw-bold py-3 shadow">CLOSE SHIFT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tabsModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content h-100">
                <div class="modal-header bg-dark text-white"><h5 class="modal-title">Active Tabs & Collections</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body bg-light p-0">
                    <div class="row g-0 h-100">
                        <div class="col-4 border-end bg-white overflow-auto" style="max-height: 70vh;">
                            <div class="list-group list-group-flush" id="tabsListGroup">
                                <form method="POST" id="mergeForm">
                                <input type="hidden" name="merge_bills" value="1">
                                <?php foreach($openTabs as $t): 
                                    $isPaid = ($t['payment_status'] === 'paid');
                                    $bgClass = $isPaid ? 'border-start border-5 border-success bg-light' : 'border-start border-5 border-warning';
                                ?>
                                <div id="tab-link-<?= $t['id'] ?>" class="list-group-item list-group-item-action <?= $bgClass ?> p-3" onclick="showTabDetails(<?= $t['id'] ?>)">
                                    <div class="d-flex w-100 justify-content-between align-items-center mb-1">
                                        <div class="d-flex align-items-center">
                                            <?php if(!$isPaid): ?>
                                                <input type="checkbox" name="merge_ids[]" value="<?= $t['id'] ?>" class="form-check-input me-2" onclick="event.stopPropagation()">
                                            <?php endif; ?>
                                            <h6 class="mb-0 fw-bold"><?= htmlspecialchars($t['customer_name']) ?></h6>
                                        </div>
                                        <?php if($isPaid): ?>
                                            <span class="badge bg-success">PAID</span>
                                        <?php else: ?>
                                            <small class="text-danger fw-bold">ZMW <?= number_format($t['final_total'], 2) ?></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small class="text-muted"><?= date('H:i', strtotime($t['created_at'])) ?> &bull; #<?= $t['id'] ?></small>
                                        <?php if($t['uncollected_count'] > 0): ?>
                                            <span class="badge bg-warning text-dark" style="font-size:0.7rem"><i class="bi bi-basket"></i> <?= $t['uncollected_count'] ?> Pending</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                </form>
                            </div>
                            <div class="p-2 border-top text-center">
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="submitMerge()">Merge Selected (Unpaid Only)</button>
                            </div>
                        </div>
                        <div class="col-8 p-3 overflow-auto" style="max-height: 70vh;">
                            <div id="tabDetailContainer" class="text-center text-muted mt-5">
                                <i class="bi bi-arrow-left-circle fs-1"></i><p>Select a tab to view details.</p>
                            </div>
                            <?php foreach($tabItems as $tid => $items): 
                                $thisTab = array_filter($openTabs, fn($x) => $x['id'] == $tid);
                                $thisTab = reset($thisTab);
                                $isPaid = ($thisTab['payment_status'] === 'paid');
                                
                                $pendingItems = array_filter($items, fn($i) => $i['fulfillment_status'] == 'uncollected' || !$isPaid);
                                $completedItems = array_filter($items, fn($i) => $i['fulfillment_status'] == 'collected' && $isPaid);
                            ?>
                            <div id="tab-data-<?= $tid ?>" class="d-none">
                                <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-3">
                                    <h5 class="fw-bold m-0"><?= htmlspecialchars($thisTab['customer_name']) ?></h5>
                                    <?php if($isPaid): ?><span class="badge bg-success">PAID</span><?php else: ?><span class="badge bg-warning text-dark">UNPAID</span><?php endif; ?>
                                </div>

                                <h6 class="text-muted text-uppercase small fw-bold mt-3">Pending Actions</h6>
                                <table class="table table-sm align-middle mb-0" id="pending-table-<?= $tid ?>">
                                    <tbody>
                                    <?php foreach($pendingItems as $i): ?>
                                    <tr id="item-row-<?= $i['id'] ?>">
                                        <td><?= $i['quantity'] ?>x <?= htmlspecialchars($i['name']) ?></td>
                                        <td class="text-end">
                                            <span class="badge badge-uncollected" onclick="markCollected(<?= $i['id'] ?>, <?= $tid ?>)">
                                                <?= $i['fulfillment_status'] == 'uncollected' ? 'UNCOLLECTED' : 'COLLECTED (Unpaid)' ?>
                                            </span>
                                        </td>
                                        <td class="text-end fw-bold"><?= number_format($i['price_at_sale']*$i['quantity'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                                
                                <?php if(empty($pendingItems)): ?><div class="text-center p-2 text-muted small border rounded bg-white" id="pending-empty-<?= $tid ?>">Nothing pending.</div><?php endif; ?>

                                <?php if(!$isPaid): ?>
                                    <div class="mt-3 text-end">
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="recall_tab" value="1"><input type="hidden" name="sale_id" value="<?= $tid ?>">
                                            <button class="btn btn-primary fw-bold w-100 py-2">PAY / ADD ITEMS</button>
                                        </form>
                                    </div>
                                <?php endif; ?>

                                <h6 class="text-muted text-uppercase small fw-bold mt-4 border-top pt-3">Completed History</h6>
                                <table class="table table-sm align-middle text-muted" style="font-size:0.9em;" id="history-table-<?= $tid ?>">
                                    <tbody>
                                    <?php foreach($completedItems as $i): ?>
                                    <tr>
                                        <td><?= $i['quantity'] ?>x <?= htmlspecialchars($i['name']) ?></td>
                                        <td class="text-end"><i class="bi bi-check-all text-success"></i> Done</td>
                                        <td class="text-end"><?= number_format($i['price_at_sale']*$i['quantity'], 2) ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="pickupModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content h-100">
                <div class="modal-header bg-warning text-dark"><h5 class="modal-title fw-bold"><i class="bi bi-bag-check-fill"></i> Orders Ready for Pickup</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-0" style="height: 80vh;"><iframe id="pickupFrame" src="" style="width:100%; height:100%; border:none;"></iframe></div>
                <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button></div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-warning text-dark"><h5 class="modal-title fw-bold">Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="checkout" value="1">
                        <?php if(isset($_SESSION['pos_member'])): ?>
                        <div class="alert alert-info border-info d-flex align-items-center justify-content-between mb-3 p-2 shadow-sm">
                            <div class="d-flex align-items-center"><i class="bi bi-star-fill text-warning fs-4 me-3"></i><div><div class="fw-bold">Member: <?= htmlspecialchars($_SESSION['pos_member']['name']) ?></div><div class="small text-muted">Eligible for benefits</div></div></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="discountToggle" name="apply_discount" value="1" onchange="toggleDiscount()"><label class="form-check-label fw-bold small" for="discountToggle">10% OFF</label></div>
                        </div>
                        <?php endif; ?>
                        <div class="text-center mb-4">
                            <small class="text-muted text-uppercase fw-bold">Amount To Pay</small>
                            <div class="display-4 fw-bold text-dark">ZMW <span id="displayTotalDue"><?= number_format($balance, 2) ?></span></div>
                            <small class="text-success fw-bold" id="discountLabel" style="display:none;">(Discount Applied)</small>
                        </div>
                        <div class="input-group mb-3">
                            <span class="input-group-text bg-light fw-bold">Tip</span>
                            <input type="number" step="0.01" name="tip_amount" id="tipInput" class="form-control" placeholder="0.00" onkeyup="calcResult()">
                            <button type="button" class="btn btn-outline-secondary" onclick="addTipPercent(0.05)">5%</button>
                            <button type="button" class="btn btn-outline-secondary" onclick="addTipPercent(0.10)">10%</button>
                            <button type="button" class="btn btn-outline-secondary" onclick="addTipPercent(0.15)">15%</button>
                        </div>
                        <div class="mb-3"><input type="text" name="customer_name" class="form-control" placeholder="Customer Name" value="<?= $_SESSION['current_customer'] ?? 'Walk-in' ?>" <?= isset($_SESSION['pos_member']) ? 'readonly' : '' ?>></div>
                        <div class="btn-group w-100 mb-3" role="group">
                            <input type="radio" class="btn-check" name="is_split" id="modeSingle" value="0" checked onchange="toggleMode()">
                            <label class="btn btn-outline-dark fw-bold" for="modeSingle">Single Pay</label>
                            <input type="radio" class="btn-check" name="is_split" id="modeSplit" value="1" onchange="toggleMode()">
                            <label class="btn btn-outline-dark fw-bold" for="modeSplit">Split Pay</label>
                        </div>
                        <div id="singleSection">
                            <div class="mb-3">
                                <select name="payment_method" class="form-select form-select-lg fw-bold">
                                    <option value="Cash" selected>Cash</option>
                                    <option value="Card">Card</option>
                                    <option value="MTN Money">MTN Money</option>
                                    <option value="Airtel Money">Airtel Money</option>
                                    <option value="Zamtel Money">Zamtel Money</option>
                                    <option value="Pending">Put on Tab</option>
                                </select>
                            </div>
                        </div>
                        <div id="splitSection" style="display:none;">
                            <div class="row g-2 mb-2">
                                <div class="col-5"><select name="method_1" class="form-select fw-bold"><option value="Cash">Cash</option><option value="Card">Card</option><option value="MTN Money">MTN</option></select></div>
                                <div class="col-7"><div class="input-group"><span class="input-group-text">ZMW</span><input type="number" step="0.01" name="amount_1" id="splitInput1" class="form-control fw-bold" placeholder="0.00" onkeyup="sumSplit()"></div></div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-5"><select name="method_2" class="form-select fw-bold"><option value="Card" selected>Card</option><option value="Cash">Cash</option><option value="Airtel Money">Airtel</option></select></div>
                                <div class="col-7"><div class="input-group"><span class="input-group-text">ZMW</span><input type="number" step="0.01" name="amount_2" id="splitInput2" class="form-control fw-bold" placeholder="0.00" onkeyup="sumSplit()"></div></div>
                            </div>
                        </div>
                        <div class="card bg-light border-0 p-3 mt-3">
                            <label class="form-label small fw-bold text-muted mb-1">TOTAL TENDERED</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white border-end-0 fw-bold">ZMW</span>
                                <input type="number" step="0.01" name="amount_tendered" id="tenderedInput" class="form-control border-start-0 fw-bold fs-3 text-success" value="<?= $balance ?>" oninput="calcResult()" onkeyup="calcResult()">
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2 border-top">
                                <div class="small fw-bold text-uppercase text-muted" id="resultLabel">Change Due</div>
                                <div class="fs-4 fw-bold text-dark" id="resultValue">ZMW 0.00</div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-warning w-100 fw-bold py-3 shadow-sm">COMPLETE TRANSACTION</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="splitBillModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header bg-dark text-white d-flex justify-content-between"><div><h5 class="modal-title"><i class="bi bi-layout-split me-2"></i> Split Bill</h5><small class="text-muted" id="splitTotalDisplay">Total: ZMW 0.00</small></div><div><div class="btn-group me-3"><button class="btn btn-sm btn-outline-light active" id="btnSplitItem" onclick="setSplitMode('item')">By Item</button><button class="btn btn-sm btn-outline-light" id="btnSplitEven" onclick="setSplitMode('even')">Split Evenly</button></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div></div><div class="modal-body bg-light"><div class="split-container"><div class="split-pool" id="unassignedPool" ondrop="drop(event)" ondragover="allowDrop(event)"><h6 class="text-muted fw-bold text-center mb-3">Unassigned Items</h6></div><div class="split-guest-zone" id="guestZone"></div><button class="btn btn-outline-primary" style="height: 50px; align-self: center;" onclick="addGuest()"><i class="bi bi-plus-lg"></i></button></div></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" id="splitForm"><input type="hidden" name="finalize_split" value="1"><input type="hidden" name="split_type" id="splitTypeInput" value="item"><input type="hidden" name="split_data" id="splitDataInput"><button type="button" class="btn btn-success fw-bold px-4" onclick="submitSplit()">FINALIZE & PAY</button></form></div></div></div></div>

    <div class="modal fade" id="serviceManagerModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><div class="modal-header bg-dark text-white"><h5 class="modal-title"><i class="bi bi-stars text-warning"></i> Manage Services</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><form method="POST" class="row g-2 mb-4 bg-light p-3 rounded border"><input type="hidden" name="save_service" value="1"><div class="col-md-5"><input type="text" name="name" class="form-control" placeholder="Service Name" required></div><div class="col-md-3"><input type="number" step="0.01" name="price" class="form-control" placeholder="Default Price" required></div><div class="col-md-2 d-flex align-items-center"><div class="form-check"><input class="form-check-input" type="checkbox" name="is_open_price" value="1" id="isOpen"><label class="form-check-label small" for="isOpen">Adjustable?</label></div></div><div class="col-md-2"><button class="btn btn-success w-100 fw-bold">Add New</button></div></form><div class="table-responsive"><table class="table table-hover align-middle"><thead class="table-secondary"><tr><th>Name</th><th>Price</th><th>Type</th><th>Action</th></tr></thead><tbody><?php foreach($services as $s): ?><tr><td class="fw-bold"><?= htmlspecialchars($s['name']) ?></td><td><?= $s['is_open_price'] ? 'Adj.' : number_format($s['price'], 2) ?></td><td>Svc</td><td><form method="POST" onsubmit="return confirm('Delete?');"><input type="hidden" name="delete_service" value="1"><input type="hidden" name="service_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div>
    <div class="modal fade" id="openPriceModal" tabindex="-1"><div class="modal-dialog modal-sm modal-dialog-centered"><div class="modal-content"><div class="modal-header bg-primary text-white"><h5 class="modal-title">Enter Amount</h5></div><div class="modal-body"><form method="POST" id="openPriceForm"><input type="hidden" name="add_item" value="1"><input type="hidden" name="product_id" id="op_pid"><div class="mb-3"><label id="op_name" class="form-label fw-bold"></label><input type="number" step="0.01" name="custom_price" class="form-control form-control-lg fw-bold text-center" autofocus required></div><button class="btn btn-primary w-100 fw-bold">Add to Cart</button></form></div></div></div></div>
    <div class="modal fade" id="locationModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg"><div class="modal-header bg-dark text-white"><h5 class="modal-title">Switch Station</h5></div><div class="modal-body bg-light"><form method="POST"><?php foreach($sellableLocations as $loc): ?><button name="set_pos_location" value="<?= $loc['id'] ?>" class="btn btn-white border w-100 mb-2 py-3 fw-bold text-start shadow-sm d-flex justify-content-between align-items-center hover-shadow"><?= htmlspecialchars($loc['name']) ?> <i class="bi bi-chevron-right text-muted"></i></button><?php endforeach; ?></form></div></div></div></div>
    <div class="modal fade" id="membersModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered modal-lg"><div class="modal-content"><div class="modal-header bg-dark text-white"><h5 class="modal-title"><i class="bi bi-people-fill"></i> Select Member</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="text" id="memberSearch" class="form-control mb-3" placeholder="Search members..." onkeyup="filterMembers()"><div style="max-height: 400px; overflow-y: auto;"><ul class="list-group" id="membersList"><?php foreach($members as $m): ?><li class="list-group-item d-flex justify-content-between align-items-center member-item" data-search="<?= strtolower($m['name'] . ' ' . $m['phone']) ?>"><div><div class="fw-bold"><?= htmlspecialchars($m['name']) ?></div><small class="text-muted"><?= htmlspecialchars($m['phone']) ?></small></div><form method="POST"><input type="hidden" name="select_member" value="1"><input type="hidden" name="member_id" value="<?= $m['id'] ?>"><input type="hidden" name="member_name" value="<?= htmlspecialchars($m['name']) ?>"><input type="hidden" name="member_phone" value="<?= htmlspecialchars($m['phone']) ?>"><button class="btn btn-sm btn-primary">Select</button></form></li><?php endforeach; ?></ul></div></div></div></div></div>
    <div class="modal fade" id="reportModal" tabindex="-1" data-bs-backdrop="static" style="z-index: 1080;"><div class="modal-dialog modal-xl"><div class="modal-content h-100 border-0 shadow-lg"><div class="modal-header bg-info text-white"><h5 class="modal-title fw-bold" id="reportTitle"><i class="bi bi-file-text me-2"></i> Shift Report</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0" style="height: 80vh; background: #525659;"><iframe id="reportFrame" src="" style="width:100%; height:100%; border:none;"></iframe></div><div class="modal-footer bg-light"><button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary px-4 fw-bold shadow-sm" onclick="document.getElementById('reportFrame').contentWindow.print()"><i class="bi bi-printer me-2"></i> Print</button></div></div></div></div>

    <div class="modal fade" id="splitBillModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header bg-dark text-white d-flex justify-content-between"><div><h5 class="modal-title"><i class="bi bi-layout-split me-2"></i> Split Bill</h5><small class="text-muted" id="splitTotalDisplay">Total: ZMW 0.00</small></div><div><div class="btn-group me-3"><button class="btn btn-sm btn-outline-light active" id="btnSplitItem" onclick="setSplitMode('item')">By Item</button><button class="btn btn-sm btn-outline-light" id="btnSplitEven" onclick="setSplitMode('even')">Split Evenly</button></div><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div></div><div class="modal-body bg-light"><div class="split-container"><div class="split-pool" id="unassignedPool" ondrop="drop(event)" ondragover="allowDrop(event)"><h6 class="text-muted fw-bold text-center mb-3">Unassigned Items</h6></div><div class="split-guest-zone" id="guestZone"></div><button class="btn btn-outline-primary" style="height: 50px; align-self: center;" onclick="addGuest()"><i class="bi bi-plus-lg"></i></button></div></div><div class="modal-footer"><button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button><form method="POST" id="splitForm"><input type="hidden" name="finalize_split" value="1"><input type="hidden" name="split_type" id="splitTypeInput" value="item"><input type="hidden" name="split_data" id="splitDataInput"><button type="button" class="btn btn-success fw-bold px-4" onclick="submitSplit()">FINALIZE & PAY</button></form></div></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterCat(id, btn) { document.querySelectorAll('.cat-pill').forEach(e=>e.classList.remove('active')); btn.classList.add('active'); switchTab('items', btn); document.querySelectorAll('.item').forEach(e=>{ e.style.display = (id=='all'||e.dataset.cat==id)?'block':'none'; }); }
        function filter() { let v=document.getElementById('search').value.toLowerCase(); document.querySelectorAll('.item').forEach(e=>{ e.style.display = e.dataset.name.includes(v)?'block':'none'; }); }
        function filterMembers() { let v=document.getElementById('memberSearch').value.toLowerCase(); document.querySelectorAll('.member-item').forEach(e=>{ e.style.display = e.dataset.search.includes(v)?'flex':'none'; }); }
        function toggleCart() { document.getElementById('cartPanel').classList.toggle('expanded'); }
        function switchTab(tab, btn) { document.querySelectorAll('.cat-pill').forEach(e=>e.classList.remove('active')); btn.classList.add('active'); if (tab === 'services') { document.getElementById('items-grid').style.display = 'none'; document.getElementById('services-grid').style.display = 'flex'; } else { document.getElementById('items-grid').style.display = 'flex'; document.getElementById('services-grid').style.display = 'none'; } }
        function addService(id, name, price, isOpen) { if (isOpen) { document.getElementById('op_pid').value = id; document.getElementById('op_name').innerText = name; new bootstrap.Modal(document.getElementById('openPriceModal')).show(); } else { let f = document.createElement('form'); f.method = 'POST'; f.innerHTML = `<input type="hidden" name="add_item" value="1"><input type="hidden" name="product_id" value="${id}">`; document.body.appendChild(f); f.submit(); } }
        
        function openAddToTabModal() { new bootstrap.Modal(document.getElementById('addToTabModal')).show(); }
        function toggleTabInput(isNew) { document.getElementById('newTabInputDiv').style.display = isNew ? 'block' : 'none'; }

        function showTabDetails(id) {
            const container = document.getElementById('tabDetailContainer');
            const content = document.getElementById('tab-data-' + id);
            if (content) { container.innerHTML = content.innerHTML; container.className = "text-start"; }
        }

        // --- NEW LOGIC: Move Rows & Close Tabs ---
        function markCollected(itemId, saleId) {
            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'mark_collected=1&item_id=' + itemId
            }).then(r => r.json()).then(data => {
                if(data.status === 'success') {
                    if (data.tab_completed) {
                        const tabLink = document.getElementById('tab-link-' + saleId);
                        if(tabLink) tabLink.remove();
                        document.getElementById('tabDetailContainer').innerHTML = `<div class="text-center mt-5 text-success"><i class="bi bi-check-circle-fill display-1"></i><h3 class="mt-3">Tab Completed</h3></div>`;
                        Swal.fire({ icon: 'success', title: 'Tab Completed', text: 'All items collected and paid.', timer: 2000, showConfirmButton: false });
                    } else {
                        const row = document.getElementById('item-row-' + itemId);
                        if (row) {
                            row.remove(); 
                            const pendTable = document.getElementById('pending-table-' + saleId);
                            if (pendTable.getElementsByTagName('tr').length === 0) { document.getElementById('pending-empty-' + saleId).style.display = 'block'; }
                            const histTable = document.getElementById('history-table-' + saleId).getElementsByTagName('tbody')[0];
                            const newRow = histTable.insertRow();
                            newRow.innerHTML = `<td>${data.item.qty}x ${data.item.name}</td><td class="text-end"><i class="bi bi-check-all text-success"></i> Done</td><td class="text-end">${data.item.total}</td>`;
                        }
                    }
                }
            });
        }
        
        function showShiftReport(shiftId) { document.getElementById('reportTitle').innerText = "X-Read (Open Shift)"; document.getElementById('reportFrame').src = "index.php?page=print_shift&shift_id=" + shiftId; new bootstrap.Modal(document.getElementById('reportModal')).show(); }
        function showPickupModal() { document.getElementById('pickupFrame').src = "index.php?page=pickup&embedded=1"; new bootstrap.Modal(document.getElementById('pickupModal')).show(); }
        
        // --- CHECKOUT LOGIC ---
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
        
        // --- SPLIT BILL JS ---
        let cartItems = <?= json_encode($_SESSION['cart'] ?? []) ?>;
        let guests = [];
        let unassigned = [];
        let splitMode = 'item'; 

        function openSplitModal() {
            unassigned = [];
            guests = [{ id: 1, name: "Guest 1", items: [] }, { id: 2, name: "Guest 2", items: [] }];
            for (const [pid, item] of Object.entries(cartItems)) {
                for (let i = 0; i < item.qty; i++) {
                    unassigned.push({ id: pid, name: item.name, price: item.price, type: item.type, uid: pid + '-' + i });
                }
            }
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
                if (splitMode === 'item') {
                    g.items.forEach(item => gTotal += parseFloat(item.price));
                } else {
                    let cartTotal = 0; Object.values(cartItems).forEach(i => cartTotal += (i.price * i.qty));
                    gTotal = cartTotal / guests.length;
                    grandTotal = cartTotal;
                }

                let col = document.createElement('div');
                col.className = 'guest-col';
                col.innerHTML = `<div class="guest-header"><div class="fw-bold">${g.name}</div><div class="small">ZMW ${gTotal.toFixed(2)}</div></div><div class="guest-items" ondrop="drop(event, ${index})" ondragover="allowDrop(event)">${splitMode === 'item' ? '' : '<small class="text-muted d-block text-center mt-4">1/' + guests.length + ' Share</small>'}</div><div class="guest-footer"><div class="input-group input-group-sm mb-2"><span class="input-group-text">Tip</span><input type="number" step="0.01" class="form-control" placeholder="0.00" oninput="guests[${index}].tip = this.value"></div><select class="form-select form-select-sm mb-2" onchange="guests[${index}].method = this.value"><option value="Cash">Cash</option><option value="Card">Card</option><option value="Pending">Pending</option></select></div>`;
                
                if (splitMode === 'item') {
                    g.items.forEach(item => { col.querySelector('.guest-items').appendChild(createDragEl(item, 'guest', index)); });
                }
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
        
        function submitMerge() {
            const form = document.getElementById('mergeForm');
            const checkboxes = form.querySelectorAll('input[name="merge_ids[]"]:checked');
            if (checkboxes.length < 2) { Swal.fire({ icon: 'warning', title: 'Select Tabs', text: 'Please select at least 2 bills to merge.' }); return; }
            Swal.fire({ title: 'Merge Bills?', text: "Combine " + checkboxes.length + " tabs?", icon: 'question', showCancelButton: true, confirmButtonText: 'Yes, Merge' }).then((result) => { if (result.isConfirmed) { form.submit(); } });
        }

        <?php if(isset($_SESSION['swal_msg'])): ?>
        Swal.fire({ icon: '<?= $_SESSION['swal_type'] ?>', title: '<?= $_SESSION['swal_msg'] ?>', timer: 1500, showConfirmButton: false });
        <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
