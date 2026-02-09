<!DOCTYPE html> 
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>POS - <?= htmlspecialchars($locationName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        /* [PRESERVED STYLES] */
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
        .cart-item { background: white; border-radius: 6px; padding: 10px; margin-bottom: 10px; border: 1px solid #dee2e6; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 2px rgba(0,0,0,0.05); }
        .btn-charge { background-color: #fd7e14 !important; border-color: #fd7e14 !important; color: #fff !important; font-size: 1.25rem; font-weight: 800; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 6px rgba(253, 126, 20, 0.3); }
        .btn-charge:hover { background-color: #e66b0d !important; }
        .btn-charge:disabled { background-color: #ccc !important; border-color: #ccc !important; box-shadow: none; cursor: not-allowed; }
        @media (max-width: 991px) { .cart-panel { width: 340px; } }
        @media (max-width: 768px) { .workspace { flex-direction: column; } .cart-panel { position: absolute; bottom: 0; left: 0; right: 0; width: 100%; height: auto; max-height: 70px; transition: max-height 0.3s; border-top: 4px solid #3e2723; } .cart-panel.expanded { max-height: 80vh; } }
    </style>
</head>
<body>

    <?php if ($pendingShift): ?>
    <div class="modal fade show" id="pendingShiftModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.8);">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-warning"><div class="modal-header bg-warning text-dark"><h5 class="modal-title fw-bold"><i class="bi bi-hourglass-split"></i> Awaiting Manager Approval</h5></div><div class="modal-body text-center p-4"><p>Shift #<?= $pendingShift['id'] ?> is pending approval.</p><form method="POST"><input type="hidden" name="approve_shift_start" value="1"><input type="hidden" name="pending_shift_id" value="<?= $pendingShift['id'] ?>"><input type="text" name="mgr_username" class="form-control mb-2" placeholder="Manager Username" required><input type="password" name="mgr_password" class="form-control mb-3" placeholder="Manager Password" required><button type="submit" class="btn btn-warning w-100 fw-bold">APPROVE & START</button></form></div></div></div>
    </div>
    <?php endif; ?>

    <?php if (!$activeShiftId && !$pendingShift): ?>
    <div class="modal fade show" id="startShiftModal" data-bs-backdrop="static" style="display: block; background: rgba(0,0,0,0.8);">
        <div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg border-primary"><div class="modal-header bg-primary text-white"><h5 class="modal-title fw-bold"><i class="bi bi-shield-lock-fill"></i> Start New Shift</h5></div><form method="POST"><div class="modal-body p-4"><input type="hidden" name="request_start_shift" value="1"><label class="fw-bold small text-muted">OPENING FLOAT</label><div class="input-group input-group-lg mb-3"><span class="input-group-text fw-bold">ZMW</span><input type="number" step="0.01" name="starting_cash" class="form-control fw-bold" required placeholder="0.00"></div><button type="submit" class="btn btn-primary w-100 fw-bold py-3">REQUEST APPROVAL</button></div></form></div></div>
    </div>
    <?php endif; ?>

    <div class="header-custom p-2 d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center ps-2">
            <span class="fs-5 fw-bold text-warning me-3"><i class="bi bi-grid-fill"></i> POS</span>
            <span class="d-flex align-items-center border-start border-secondary ps-3 text-light">
                <i class="bi bi-geo-alt-fill text-warning me-2"></i> <?= htmlspecialchars($locationName) ?>
            </span>
            <button class="btn btn-sm btn-link text-warning ms-1" data-bs-toggle="modal" data-bs-target="#locationModal"><i class="bi bi-pencil-square"></i></button>
            
            <?php if(isset($_SESSION['pos_member'])): ?>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="remove_member" value="1">
                    <span class="badge bg-success ms-3 p-2 border border-light" title="Member Active">
                        <i class="bi bi-person-check-fill me-1"></i> <?= htmlspecialchars($_SESSION['pos_member']['name']) ?>
                        <button type="submit" class="btn-close btn-close-white ms-2" style="font-size: 0.7em; vertical-align: middle;"></button>
                    </span>
                </form>
            <?php endif; ?>
        </div>
        
        <div class="d-flex gap-2 pe-2">
            <?php if ($activeShiftId): ?>
                <button onclick="showShiftReport(<?= $activeShiftId ?>)" class="btn btn-outline-info text-white border-white btn-sm fw-bold"><i class="bi bi-printer"></i> X-Read</button>
            <?php endif; ?>
            <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#membersModal"><i class="bi bi-person-lines-fill"></i> Members</button>
            <a href="index.php?page=pickup" class="btn btn-outline-warning btn-sm fw-bold"><i class="bi bi-bag-check"></i> Pickup</a>
            <button class="btn btn-outline-light btn-sm" data-bs-toggle="modal" data-bs-target="#tabsModal"><i class="bi bi-receipt"></i> Tabs</button>
            <?php if ($activeShiftId): ?>
                <button class="btn btn-danger btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#endShiftModal"><i class="bi bi-power"></i> End</button>
            <?php else: ?>
                <span class="badge bg-secondary d-flex align-items-center">LOCKED</span>
            <?php endif; ?>
            <a href="index.php?page=dashboard" class="btn btn-outline-light btn-sm"><i class="bi bi-house"></i></a>
        </div>
    </div>

    <div class="workspace" style="<?= (!$activeShiftId) ? 'filter: blur(5px); pointer-events: none;' : '' ?>">
        <div class="product-section">
            <div class="bg-white p-2 border-bottom">
                <input type="text" id="search" class="form-control form-control-lg" placeholder="Search items..." onkeyup="filter()">
            </div>
            <div class="category-bar">
                <div class="cat-pill active" onclick="filterCat('all', this)">ALL</div>
                <?php foreach($categories as $cat): ?>
                    <div class="cat-pill" onclick="filterCat('<?= $cat['id'] ?>', this)"><?= htmlspecialchars($cat['name']) ?></div>
                <?php endforeach; ?>
            </div>
            <div class="product-list">
                <div class="row g-2">
                    <?php if(empty($products)): ?>
                        <div class="col-12 text-center mt-5"><h3 class="text-muted opacity-50"><i class="bi bi-box-seam"></i> No Items Found</h3></div>
                    <?php else: ?>
                        <?php foreach($products as $p): $isOut = ($p['stock_qty'] <= 0); ?>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2 item" data-cat="<?= $p['category_id'] ?>" data-name="<?= strtolower($p['name']) ?>">
                            <form method="POST" class="h-100">
                                <input type="hidden" name="add_item" value="1">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="item-card w-100 p-2 text-start position-relative" <?= $isOut ? 'disabled' : '' ?>>
                                    <span class="stock-badge <?= $isOut ? 'bg-low' : 'bg-ok' ?>"><?= $p['stock_qty'] ?></span>
                                    <div style="height: 50px; overflow: hidden;" class="fw-bold text-dark mb-1 lh-sm"><?= htmlspecialchars($p['name']) ?></div>
                                    <div class="text-primary fw-bold">ZMW <?= number_format($p['price'], 2) ?></div>
                                </button>
                            </form>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="cart-panel" id="cartPanel">
            <div class="cart-header d-flex justify-content-between align-items-center">
                <h5 class="m-0 fw-bold"><i class="bi bi-basket3-fill"></i> Current Order</h5>
                <button class="btn btn-sm btn-outline-light d-md-none" onclick="toggleCart()">Hide</button>
            </div>
            <div class="cart-items">
                <?php if (empty($_SESSION['cart'])): ?>
                    <div class="text-center mt-5 text-muted"><i class="bi bi-cart-x display-1 opacity-25"></i><p class="mt-3 fw-bold">Cart is empty</p></div>
                <?php else: ?>
                    <?php foreach ($_SESSION['cart'] as $pid => $item): ?>
                    <div class="cart-item">
                        <div class="me-2 overflow-hidden">
                            <div class="fw-bold text-truncate"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="text-muted small">@ ZMW <?= number_format($item['price'], 2) ?></div>
                        </div>
                        <div class="d-flex align-items-center bg-light rounded border">
                            <form method="POST" class="d-flex m-0">
                                <input type="hidden" name="product_id" value="<?= $pid ?>">
                                <input type="hidden" name="update_qty" value="1">
                                <button name="action" value="dec" class="btn btn-sm text-danger px-2 fw-bold border-end hover-bg">-</button>
                                <span class="px-2 fw-bold" style="min-width:30px; text-align:center; line-height:30px;"><?= $item['qty'] ?></span>
                                <button name="action" value="inc" class="btn btn-sm text-success px-2 fw-bold border-start hover-bg">+</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="cart-footer">
                <?php 
                $tabPaid = $_SESSION['tab_paid'] ?? 0;
                $total = $total ?? 0;
                $balance = $total - $tabPaid;
                ?>
                <?php if($tabPaid > 0): ?>
                    <div class="d-flex justify-content-between mb-1 small text-muted"><span>Subtotal:</span><span><?= number_format($total, 2) ?></span></div>
                    <div class="d-flex justify-content-between mb-2 small text-success fw-bold"><span>Already Paid:</span><span>-<?= number_format($tabPaid, 2) ?></span></div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-end mb-3">
                    <span class="text-muted small fw-bold text-uppercase">Total Due</span>
                    <span class="fs-2 fw-bold text-dark lh-1">ZMW <?= number_format($balance, 2) ?></span>
                </div>
                
                <div class="d-grid gap-2 mb-3">
                    <button class="btn w-100 py-3 btn-charge shadow" data-bs-toggle="modal" data-bs-target="#checkoutModal" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?> onclick="initCheckout()">CHARGE</button>
                    <div class="row g-2">
                        <div class="col-6">
                            <form method="POST" onsubmit="return confirm('Clear cart?');"><input type="hidden" name="clear_cart" value="1"><button class="btn btn-outline-danger w-100 btn-sm fw-bold">CLEAR</button></form>
                        </div>
                        <div class="col-6">
                            <button onclick="promptHold()" class="btn btn-outline-secondary w-100 btn-sm fw-bold" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>HOLD ORDER</button>
                        </div>
                    </div>
                </div>
                <div class="text-center">
                    <button class="btn btn-outline-primary w-100 btn-sm" data-bs-toggle="modal" data-bs-target="#tabsModal"><i class="bi bi-receipt"></i> OPEN TABS</button>
                </div>
            </div>
        </div>
    </div>

    <form id="holdForm" method="POST" style="display:none;">
        <input type="hidden" name="hold_order" value="1">
        <input type="hidden" name="hold_name" id="holdNameInput">
    </form>

    <div class="modal fade" id="membersModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white"><h5 class="modal-title"><i class="bi bi-people-fill"></i> Select Member</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <input type="text" id="memberSearch" class="form-control mb-3" placeholder="Search members..." onkeyup="filterMembers()">
                    <div style="max-height: 400px; overflow-y: auto;">
                        <ul class="list-group" id="membersList">
                            <?php foreach($members as $m): ?>
                            <li class="list-group-item d-flex justify-content-between align-items-center member-item" data-search="<?= strtolower($m['name'] . ' ' . $m['phone']) ?>">
                                <div><div class="fw-bold"><?= htmlspecialchars($m['name']) ?></div><small class="text-muted"><?= htmlspecialchars($m['phone']) ?></small></div>
                                <form method="POST"><input type="hidden" name="select_member" value="1"><input type="hidden" name="member_id" value="<?= $m['id'] ?>"><input type="hidden" name="member_name" value="<?= htmlspecialchars($m['name']) ?>"><input type="hidden" name="member_phone" value="<?= htmlspecialchars($m['phone']) ?>"><button class="btn btn-sm btn-primary">Select</button></form>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
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
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-star-fill text-warning fs-4 me-3"></i>
                                    <div>
                                        <div class="fw-bold">Member: <?= htmlspecialchars($_SESSION['pos_member']['name']) ?></div>
                                        <div class="small text-muted">Eligible for benefits</div>
                                    </div>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="discountToggle" name="apply_discount" value="1" onchange="toggleDiscount()">
                                    <label class="form-check-label fw-bold small" for="discountToggle">10% OFF</label>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="text-center mb-4">
                            <small class="text-muted text-uppercase fw-bold">Amount To Pay</small>
                            <div class="display-4 fw-bold text-dark">ZMW <span id="displayTotalDue"><?= number_format($balance, 2) ?></span></div>
                            <small class="text-success fw-bold" id="discountLabel" style="display:none;">(Discount Applied)</small>
                        </div>

                        <div class="mb-3">
                            <input type="text" name="customer_name" class="form-control" placeholder="Customer Name" value="<?= $_SESSION['current_customer'] ?? 'Walk-in' ?>" <?= isset($_SESSION['pos_member']) ? 'readonly' : '' ?>>
                        </div>

                        <div class="btn-group w-100 mb-3" role="group">
                            <input type="radio" class="btn-check" name="is_split" id="modeSingle" value="0" checked onchange="toggleMode()">
                            <label class="btn btn-outline-dark fw-bold" for="modeSingle">Single Pay</label>
                            <input type="radio" class="btn-check" name="is_split" id="modeSplit" value="1" onchange="toggleMode()">
                            <label class="btn btn-outline-dark fw-bold" for="modeSplit">Split Pay</label>
                        </div>

                        <div id="singleSection">
                            <div class="mb-3">
                                <select name="payment_method" class="form-select form-select-lg fw-bold"><option value="Cash" selected>Cash</option><option value="Card">Card</option><option value="MTN Money">MTN Money</option><option value="Airtel Money">Airtel Money</option><option value="Zamtel Money">Zamtel Money</option><option value="Pending">Put on Tab</option></select>
                            </div>
                        </div>

                        <div id="splitSection" style="display:none;">
                            <div class="row g-2 mb-2">
                                <div class="col-5"><select name="method_1" class="form-select fw-bold"><option value="Cash">Cash</option><option value="Card">Card</option><option value="MTN Money">MTN</option><option value="Airtel Money">Airtel</option></select></div>
                                <div class="col-7"><div class="input-group"><span class="input-group-text">ZMW</span><input type="number" step="0.01" name="amount_1" id="splitInput1" class="form-control fw-bold" placeholder="0.00" onkeyup="sumSplit()"></div></div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-5"><select name="method_2" class="form-select fw-bold"><option value="Card" selected>Card</option><option value="Cash">Cash</option><option value="MTN Money">MTN</option><option value="Airtel Money">Airtel</option></select></div>
                                <div class="col-7"><div class="input-group"><span class="input-group-text">ZMW</span><input type="number" step="0.01" name="amount_2" id="splitInput2" class="form-control fw-bold" placeholder="0.00" onkeyup="sumSplit()"></div></div>
                            </div>
                        </div>

                        <div class="card bg-light border-0 p-3 mt-3">
                            <label class="form-label small fw-bold text-muted mb-1">TOTAL TENDERED</label>
                            <div class="input-group input-group-lg">
                                <span class="input-group-text bg-white border-end-0 fw-bold">ZMW</span>
                                <input type="number" step="0.01" name="amount_tendered" id="tenderedInput" class="form-control border-start-0 fw-bold fs-3 text-success" 
                                       value="<?= $balance ?>" oninput="calcResult()" onkeyup="calcResult()">
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

    <div class="modal fade" id="endShiftModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0 shadow-lg"><div class="modal-header bg-danger text-white"><h5 class="modal-title fw-bold">End Shift</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><form action="index.php?page=end_shift_action" method="POST"><div class="modal-body p-4"><div class="alert alert-light border text-center mb-4"><small class="text-uppercase fw-bold text-muted">Expected Cash</small><div class="h2 fw-bold text-dark m-0">ZMW <?= number_format($expectedShiftCash ?? 0, 2) ?></div></div><label class="fw-bold small text-muted">ACTUAL CLOSING CASH</label><div class="input-group input-group-lg mb-3"><span class="input-group-text fw-bold">ZMW</span><input type="number" step="0.01" name="closing_cash" class="form-control fw-bold text-primary" required value="<?= $expectedShiftCash ?>"></div><label class="fw-bold small text-muted">VARIANCE REASON</label><textarea name="variance_reason" class="form-control mb-3" placeholder="Explain any difference..."></textarea><label class="fw-bold small text-danger mt-2">MANAGER PASSWORD</label><input type="password" name="manager_password" class="form-control" required placeholder="Required for verification"></div><div class="modal-footer border-0"><button type="submit" class="btn btn-danger w-100 fw-bold py-3 shadow">CLOSE SHIFT</button></div></form></div></div></div>
    
    <div class="modal fade" id="tabsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content border-0"><div class="modal-header bg-dark text-white"><h5 class="modal-title"><i class="bi bi-receipt me-2"></i> Open Tabs</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><div class="table-responsive"><table class="table table-striped mb-0 align-middle"><thead class="table-dark"><tr><th class="ps-4">Time</th><th>Customer</th><th>Total</th><th>Paid</th><th class="text-end pe-4">Action</th></tr></thead><tbody><?php if(empty($openTabs)): ?><tr><td colspan="5" class="text-center p-5 text-muted">No open tabs found.</td></tr><?php endif; ?><?php foreach($openTabs as $t): ?><tr><td class="ps-4 text-muted small"><?= date('H:i', strtotime($t['created_at'])) ?></td><td class="fw-bold text-dark"><?= htmlspecialchars($t['customer_name']) ?></td><td class="fw-bold text-danger">ZMW <?= number_format($t['final_total'], 2) ?></td><td class="text-success">ZMW <?= number_format($t['amount_tendered'], 2) ?></td><td class="text-end pe-4"><form method="POST"><input type="hidden" name="sale_id" value="<?= $t['id'] ?>"><button name="recall_tab" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm">PAY / EDIT</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div>
    
    <div class="modal fade" id="reportModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-xl"><div class="modal-content h-100 border-0 shadow-lg"><div class="modal-header bg-info text-white"><h5 class="modal-title fw-bold" id="reportTitle"><i class="bi bi-file-text me-2"></i> Shift Report</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0" style="height: 80vh; background: #525659;"><iframe id="reportFrame" src="" style="width:100%; height:100%; border:none;"></iframe></div><div class="modal-footer bg-light"><button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Close</button><button type="button" class="btn btn-primary px-4 fw-bold shadow-sm" onclick="document.getElementById('reportFrame').contentWindow.print()"><i class="bi bi-printer me-2"></i> Print</button></div></div></div></div>
    
    <div class="modal fade" id="locationModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content shadow-lg"><div class="modal-header bg-dark text-white"><h5 class="modal-title">Switch Station</h5></div><div class="modal-body bg-light"><form method="POST"><?php foreach($sellableLocations as $loc): ?><button name="set_pos_location" value="1" class="btn btn-white border w-100 mb-2 py-3 fw-bold text-start shadow-sm d-flex justify-content-between align-items-center hover-shadow"><?= htmlspecialchars($loc['name']) ?> <i class="bi bi-chevron-right text-muted"></i> <input type="hidden" name="pos_location_id" value="<?= $loc['id'] ?>"></button><?php endforeach; ?></form></div></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterCat(id, btn) { document.querySelectorAll('.cat-pill').forEach(e=>e.classList.remove('active')); btn.classList.add('active'); document.querySelectorAll('.item').forEach(e=>{ e.style.display = (id=='all'||e.dataset.cat==id)?'block':'none'; }); }
        function filter() { let v=document.getElementById('search').value.toLowerCase(); document.querySelectorAll('.item').forEach(e=>{ e.style.display = e.dataset.name.includes(v)?'block':'none'; }); }
        function filterMembers() { let v=document.getElementById('memberSearch').value.toLowerCase(); document.querySelectorAll('.member-item').forEach(e=>{ e.style.display = e.dataset.search.includes(v)?'flex':'none'; }); }
        function toggleCart() { document.getElementById('cartPanel').classList.toggle('expanded'); }
        
        // HOLD ORDER FUNCTION
        function promptHold() {
            let name = prompt("Enter a name/reference for this order:");
            if (name) {
                document.getElementById('holdNameInput').value = name;
                document.getElementById('holdForm').submit();
            }
        }
        
        let baseTotal = <?= $balance ?? 0 ?>;
        let currentTotal = baseTotal;
        
        function initCheckout() {
            currentTotal = baseTotal;
            if(document.getElementById('discountToggle')) {
                document.getElementById('discountToggle').checked = false;
                toggleDiscount();
            } else {
                updateDisplays();
            }
            
            document.getElementById('modeSingle').checked = true;
            toggleMode();
        }

        function toggleDiscount() {
            let chk = document.getElementById('discountToggle');
            let isDiscount = chk ? chk.checked : false;
            
            if(isDiscount) {
                currentTotal = baseTotal * 0.90; // 10% Off
                document.getElementById('discountLabel').style.display = 'block';
            } else {
                currentTotal = baseTotal;
                document.getElementById('discountLabel').style.display = 'none';
            }
            updateDisplays();
        }

        function updateDisplays() {
            document.getElementById('displayTotalDue').innerText = currentTotal.toFixed(2);
            document.getElementById('tenderedInput').value = currentTotal.toFixed(2);
            calcResult();
        }

        function toggleMode() {
            let isSplit = document.getElementById('modeSplit').checked;
            document.getElementById('singleSection').style.display = isSplit ? 'none' : 'block';
            document.getElementById('splitSection').style.display = isSplit ? 'block' : 'none';
            if(isSplit) {
                document.getElementById('splitInput1').value = "";
                document.getElementById('splitInput2').value = "";
                document.getElementById('tenderedInput').value = "0.00";
            } else {
                document.getElementById('tenderedInput').value = currentTotal.toFixed(2);
            }
            calcResult();
        }

        function sumSplit() {
            let val1 = parseFloat(document.getElementById('splitInput1').value) || 0;
            let val2 = parseFloat(document.getElementById('splitInput2').value) || 0;
            document.getElementById('tenderedInput').value = (val1 + val2).toFixed(2);
            calcResult();
        }

        function calcResult() {
            let tendered = parseFloat(document.getElementById('tenderedInput').value) || 0;
            let diff = tendered - currentTotal;
            let label = document.getElementById('resultLabel');
            let value = document.getElementById('resultValue');
            
            if(diff >= -0.01) {
                label.innerText = "CHANGE DUE";
                label.className = "small fw-bold text-uppercase text-muted";
                value.innerText = "ZMW " + diff.toFixed(2);
                value.className = "fs-4 fw-bold text-dark";
            } else {
                label.innerText = "BALANCE REMAINING (ON TAB)";
                label.className = "small fw-bold text-uppercase text-danger";
                value.innerText = "ZMW " + Math.abs(diff).toFixed(2);
                value.className = "fs-4 fw-bold text-danger";
            }
        }
        
        let reportModal;
        document.addEventListener('DOMContentLoaded', function() {
            reportModal = new bootstrap.Modal(document.getElementById('reportModal'));
            
            <?php if (isset($_GET['closed_shift_id'])): ?>
                document.getElementById('reportTitle').innerText = "Z-Report (Closed Shift)";
                document.getElementById('reportFrame').src = "index.php?page=print_shift&shift_id=<?= $_GET['closed_shift_id'] ?>";
                reportModal.show();
                document.getElementById('reportModal').addEventListener('hidden.bs.modal', function () { window.location.href = 'index.php?page=pos'; });
            <?php endif; ?>
            <?php if (isset($_SESSION['last_sale_id'])): ?>
                document.getElementById('reportTitle').innerText = "Transaction Receipt";
                document.getElementById('reportFrame').src = "index.php?page=receipt&sale_id=<?= $_SESSION['last_sale_id'] ?>&mode=double";
                reportModal.show();
            <?php unset($_SESSION['last_sale_id']); ?>
            <?php endif; ?>
        });

        function showShiftReport(shiftId) {
            document.getElementById('reportTitle').innerText = "X-Read (Open Shift)";
            document.getElementById('reportFrame').src = "index.php?page=print_shift&shift_id=" + shiftId;
            reportModal.show();
        }
    </script>
</body>
</html>
