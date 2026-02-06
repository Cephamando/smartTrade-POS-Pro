<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>POS - <?= htmlspecialchars($locationName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root { 
            --theme-brown: #3e2723; 
            --theme-gold: #ffc107; 
            --theme-orange: #fd7e14; 
            --theme-orange-dark: #e66b0d;
            --theme-cream: #f8f5f2; 
            --theme-dark-text: #2c2c2c;
        }
        
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; background: #fcfbf9; }
        body { display: flex; flex-direction: column; color: var(--theme-dark-text); }
        
        /* HEADER */
        .header-custom { flex: 0 0 auto; background-color: var(--theme-brown); border-bottom: 4px solid var(--theme-gold); z-index: 1000; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        
        /* LAYOUT */
        .workspace { flex: 1; display: flex; overflow: hidden; position: relative; }
        .product-section { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; background: #f0f2f5; }
        
        /* CATEGORY BAR */
        .category-bar { flex: 0 0 auto; overflow-x: auto; white-space: nowrap; padding: 12px; background: #fff; border-bottom: 1px solid #ddd; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .cat-pill { display: inline-block; padding: 10px 20px; margin-right: 10px; border-radius: 25px; border: 1px solid #ddd; background: #fff; cursor: pointer; font-weight: 700; color: var(--theme-brown); transition: all 0.2s; }
        .cat-pill:hover { background: #f8f9fa; transform: translateY(-1px); }
        .cat-pill.active { background: var(--theme-brown); color: var(--theme-gold); border-color: var(--theme-brown); box-shadow: 0 2px 5px rgba(62, 39, 35, 0.4); }
        
        /* PRODUCT GRID */
        .product-list { flex: 1; overflow-y: auto; padding: 20px; }
        .item-card { border: none; border-radius: 12px; box-shadow: 0 3px 6px rgba(0,0,0,0.08); transition: all 0.15s; height: 100%; cursor: pointer; position: relative; background: white; overflow: hidden; }
        .item-card:active { transform: scale(0.96); }
        .item-card:disabled { opacity: 0.7; filter: grayscale(0.8); cursor: not-allowed; background: #f0f0f0; }
        .badge-stock { position: absolute; top: 10px; right: 10px; font-size: 0.8rem; padding: 5px 8px; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.2); }
        
        /* CART PANEL */
        .cart-panel { width: 420px; display: flex; flex-direction: column; background: #fff; border-left: 1px solid #ccc; box-shadow: -5px 0 20px rgba(0,0,0,0.1); z-index: 900; }
        .cart-header { flex: 0 0 auto; padding: 15px; background: #fff; border-bottom: 1px solid #eee; }
        .cart-items { flex: 1; overflow-y: auto; background-color: var(--theme-cream); padding: 15px; }
        .cart-footer { flex: 0 0 auto; padding: 20px; background: #fff; border-top: 1px solid #ddd; box-shadow: 0 -2px 10px rgba(0,0,0,0.05); }
        
        /* BUTTONS & COLORS */
        .btn-theme-orange { background-color: var(--theme-orange); color: white; border: none; transition: 0.2s; }
        .btn-theme-orange:hover { background-color: var(--theme-orange-dark); color: white; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(253, 126, 20, 0.3); }
        .btn-theme-orange:active { transform: translateY(0); }
        .text-theme-orange { color: var(--theme-orange-dark) !important; }
        
        /* CART ITEM */
        .cart-item-card { border: none; border-radius: 8px; background: white; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 10px; border-left: 4px solid var(--theme-brown); }
        
        @media (max-width: 992px) { .cart-panel { width: 350px; } }
        @media (max-width: 768px) {
            .workspace { flex-direction: column; }
            .cart-panel { position: absolute; bottom: 0; left: 0; right: 0; width: 100%; height: auto; max-height: 60px; border-left: none; border-top: 4px solid var(--theme-brown); transition: max-height 0.3s ease-out; }
            .cart-panel.expanded { max-height: 85vh; }
        }
    </style>
</head>
<body>

    <div class="header-custom text-white p-2 d-flex justify-content-between align-items-center shadow">
        <div class="fw-bold ms-3 d-flex align-items-center fs-5">
            <i class="bi bi-grid-fill text-warning me-2"></i> OdeliaPOS
            <span class="mx-2 opacity-50">|</span>
            <i class="bi bi-shop text-warning me-2"></i> <?= htmlspecialchars($locationName) ?>
            <button class="btn btn-sm btn-link text-warning ms-1 p-0 border-0" data-bs-toggle="modal" data-bs-target="#locationModal"><i class="bi bi-pencil-square"></i></button>
        </div>
        <div class="d-flex gap-2 me-2">
            <?php if ($activeShiftId): ?>
                <button type="button" class="btn btn-sm btn-outline-info text-white fw-bold" onclick="showShiftReport(<?= $activeShiftId ?>)">
                    <i class="bi bi-printer"></i> X-Read
                </button>
            <?php endif; ?>

            <button class="btn btn-sm btn-outline-light" data-bs-toggle="modal" data-bs-target="#tabsModal"><i class="bi bi-receipt"></i> Tabs</button>
            
            <?php if ($activeShiftId): ?>
                <button type="button" data-bs-toggle="modal" data-bs-target="#endShiftModal" class="btn btn-sm btn-danger fw-bold shadow-sm"><i class="bi bi-power"></i> End Shift</button>
            <?php else: ?>
                <button class="btn btn-sm btn-secondary fw-bold disabled"><i class="bi bi-lock"></i> LOCKED</button>
            <?php endif; ?>
            <a href="index.php?page=dashboard" class="btn btn-sm btn-outline-light"><i class="bi bi-house"></i> Exit</a>
        </div>
    </div>

    <div class="workspace">
        <div class="product-section">
            <div class="category-bar">
                <div class="cat-pill active" onclick="filterCat('all', this)">All Items</div>
                <?php foreach($categories as $cat): ?>
                    <div class="cat-pill" onclick="filterCat('<?= $cat['id'] ?>', this)"><?= htmlspecialchars($cat['name']) ?></div>
                <?php endforeach; ?>
            </div>
            
            <div class="bg-white p-3 border-bottom shadow-sm">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                    <input type="text" id="search" class="form-control border-start-0" placeholder="Search products..." onkeyup="filter()">
                </div>
            </div>

            <div class="product-list">
                <div class="row g-3">
                    <?php if(empty($products)): ?>
                        <div class="col-12 text-center mt-5 text-muted">
                            <h4><i class="bi bi-box-seam display-4"></i></h4>
                            <p>No products available.</p>
                            <?php if($locationId == 0): ?><p class="text-danger">Select a station first.</p><?php endif; ?>
                        </div>
                    <?php else: ?>
                        <?php foreach($products as $p): 
                            $isOutOfStock = ($p['stock_qty'] <= 0);
                            $bgClass = $isOutOfStock ? 'bg-light' : 'bg-white';
                            $textClass = $isOutOfStock ? 'text-muted' : 'text-dark';
                            $stockBadge = $isOutOfStock ? 'bg-danger' : 'bg-success';
                        ?>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2 item" data-cat="<?= $p['category_id'] ?>" data-name="<?= strtolower($p['name']) ?>">
                            <form method="POST">
                                <input type="hidden" name="add_item" value="1">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <button type="submit" class="card w-100 text-start <?= $bgClass ?> item-card" <?= $isOutOfStock ? 'disabled' : '' ?>>
                                    <div class="card-body p-3 d-flex flex-column justify-content-between h-100">
                                        <div>
                                            <span class="badge <?= $stockBadge ?> badge-stock"><?= $p['stock_qty'] ?></span>
                                            <h6 class="fw-bold mb-1 <?= $textClass ?>"><?= htmlspecialchars($p['name']) ?></h6>
                                        </div>
                                        <div class="mt-2 fw-bold text-theme-orange fs-5">ZMW <?= number_format($p['price'], 2) ?></div>
                                    </div>
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
                <h5 class="m-0 fw-bold text-theme-dark-text"><i class="bi bi-cart3"></i> Current Order</h5>
                <button class="btn btn-sm btn-outline-danger d-md-none" onclick="toggleCart()">Close</button>
            </div>

            <div class="cart-items">
                <?php if (empty($_SESSION['cart'])): ?>
                    <div class="text-center mt-5 text-muted opacity-50">
                        <i class="bi bi-basket display-1" style="font-size: 4rem;"></i>
                        <p class="mt-3 fw-bold">Cart is empty</p>
                        <small>Select items to add</small>
                    </div>
                <?php else: ?>
                    <?php foreach ($_SESSION['cart'] as $pid => $item): ?>
                    <div class="cart-item-card p-2 d-flex align-items-center justify-content-between">
                        <div style="flex: 1;">
                            <div class="fw-bold text-dark text-truncate"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="small text-muted">ZMW <?= number_format($item['price'], 2) ?></div>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <form method="POST" class="d-flex align-items-center">
                                <input type="hidden" name="product_id" value="<?= $pid ?>">
                                <input type="hidden" name="update_qty" value="1">
                                <button name="action" value="dec" class="btn btn-sm btn-light border fw-bold text-danger px-2">-</button>
                                <span class="mx-2 fw-bold fs-5 text-dark"><?= $item['qty'] ?></span>
                                <button name="action" value="inc" class="btn btn-sm btn-light border fw-bold text-success px-2">+</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="cart-footer">
                <div class="d-flex justify-content-between mb-3 align-items-end">
                    <span class="h6 m-0 text-muted">Total Amount</span>
                    <span class="h2 m-0 fw-bold text-dark">ZMW <?= number_format($total ?? 0, 2) ?></span>
                </div>
                
                <?php if ($activeShiftId): ?>
                    <button class="btn btn-theme-orange w-100 py-3 fw-bold fs-5 text-white shadow" data-bs-toggle="modal" data-bs-target="#checkoutModal" <?= empty($_SESSION['cart']) ? 'disabled' : '' ?>>
                        <i class="bi bi-credit-card-2-front me-2"></i> CHARGE
                    </button>
                <?php else: ?>
                    <button class="btn btn-secondary w-100 py-3 fw-bold" disabled>
                        <i class="bi bi-lock-fill me-2"></i> START SHIFT TO SELL
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title fw-bold">Checkout: <span id="modalTotalDisplay">ZMW <?= number_format($total ?? 0, 2) ?></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="checkout" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">CUSTOMER NAME</label>
                            <input type="text" name="customer_name" class="form-control form-control-lg" value="<?= $_SESSION['current_customer'] ?? 'Walk-in' ?>" required>
                        </div>
                        
                        <div class="form-check form-switch mb-3 bg-light p-3 rounded border">
                            <input class="form-check-input ms-0 me-2" type="checkbox" id="splitToggle" name="is_split" value="1" onchange="toggleSplit()">
                            <label class="form-check-label fw-bold" for="splitToggle">Split Payment (Pay with 2 modes)</label>
                        </div>

                        <div id="singlePayment">
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">PAYMENT METHOD</label>
                                <select name="payment_method" class="form-select form-select-lg fw-bold">
                                    <option value="Cash">Cash</option>
                                    <option value="Card">Card</option>
                                    <option value="MTN Money">MTN Money</option>
                                    <option value="Airtel Money">Airtel Money</option>
                                    <option value="Zamtel Money">Zamtel Money</option>
                                    <?php if (!isset($_SESSION['current_tab_id'])): ?>
                                        <option value="Pending">Put on Tab</option>
                                    <?php endif; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold small text-muted">AMOUNT TENDERED</label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text fw-bold">ZMW</span>
                                    <input type="number" step="0.01" name="amount_tendered" id="tenderedSingle" class="form-control fw-bold text-success" value="<?= $total ?>" oninput="calcChange()">
                                </div>
                            </div>
                        </div>

                        <div id="splitPayment" style="display:none;">
                            <div class="row g-2 mb-2">
                                <div class="col-6">
                                    <label class="small fw-bold text-muted">Method 1</label>
                                    <select name="method_1" class="form-select">
                                        <option value="Cash">Cash</option>
                                        <option value="Card">Card</option>
                                        <option value="MTN Money">MTN</option>
                                        <option value="Airtel Money">Airtel</option>
                                        <option value="Zamtel Money">Zamtel</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="small fw-bold text-muted">Amount 1</label>
                                    <input type="number" step="0.01" name="amount_1" id="amount1" class="form-control fw-bold" placeholder="0.00" oninput="calcChange()">
                                </div>
                            </div>
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <label class="small fw-bold text-muted">Method 2</label>
                                    <select name="method_2" class="form-select">
                                        <option value="Cash" selected>Cash</option>
                                        <option value="Card">Card</option>
                                        <option value="MTN Money">MTN</option>
                                        <option value="Airtel Money">Airtel</option>
                                        <option value="Zamtel Money">Zamtel</option>
                                    </select>
                                </div>
                                <div class="col-6">
                                    <label class="small fw-bold text-muted">Amount 2</label>
                                    <input type="number" step="0.01" name="amount_2" id="amount2" class="form-control fw-bold" placeholder="0.00" oninput="calcChange()">
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-light text-center border shadow-sm">
                            <small class="text-uppercase fw-bold text-muted">Change Due</small>
                            <div class="h2 fw-bold m-0 text-dark" id="changeDisplay">ZMW 0.00</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning w-100 fw-bold py-3 shadow-sm">CONFIRM PAYMENT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (!$activeShiftId && !$pendingShift && !isset($_GET['closed_shift_id'])): ?>
    <div class="modal fade show" tabindex="-1" style="display:block; background:rgba(0,0,0,0.85); z-index:2000;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white"><h5 class="modal-title fw-bold">Start New Shift</h5></div>
                <form method="POST">
                    <div class="modal-body p-4 text-center">
                        <h1 class="display-4 mb-3 text-success"><i class="bi bi-cash-coin"></i></h1>
                        <p class="text-muted fw-bold">Enter Opening Float Amount</p>
                        <input type="hidden" name="request_start_shift" value="1">
                        <div class="input-group input-group-lg mb-3">
                            <span class="input-group-text fw-bold">ZMW</span>
                            <input type="number" step="0.01" name="starting_cash" class="form-control text-center fw-bold" value="0.00" required>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-center border-0">
                        <a href="index.php?page=dashboard" class="btn btn-outline-secondary">Exit</a>
                        <button type="submit" class="btn btn-success px-5 fw-bold shadow">START SHIFT</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <?php if ($pendingShift): ?>
    <div class="modal fade show" tabindex="-1" style="display:block; background:rgba(0,0,0,0.9); z-index:2000;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-top border-5 border-warning shadow-lg">
                <div class="modal-header bg-white"><h5 class="modal-title fw-bold text-warning">Manager Approval Needed</h5></div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <div class="d-flex align-items-center mb-3">
                            <div class="bg-warning text-white rounded-circle p-3 me-3"><i class="bi bi-lock-fill fs-3"></i></div>
                            <div>
                                <div class="text-muted small text-uppercase fw-bold">Cashier</div>
                                <div class="fs-5 fw-bold"><?= htmlspecialchars($pendingShift['cashier_name']) ?></div>
                                <div class="text-muted small text-uppercase fw-bold mt-1">Float</div>
                                <div class="fs-5 fw-bold">ZMW <?= $pendingShift['starting_cash'] ?></div>
                            </div>
                        </div>
                        <input type="hidden" name="pending_shift_id" value="<?= $pendingShift['id'] ?>">
                        <input type="hidden" name="approve_shift_start" value="1">
                        <input type="text" name="mgr_username" class="form-control mb-2" placeholder="Manager Username" required>
                        <input type="password" name="mgr_password" class="form-control" placeholder="Password" required>
                    </div>
                    <div class="modal-footer bg-light"><a href="index.php?page=dashboard" class="btn btn-link text-muted text-decoration-none">Cancel</a><button class="btn btn-warning fw-bold px-4 shadow-sm">APPROVE & OPEN</button></div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="endShiftModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-danger text-white"><h5 class="modal-title fw-bold">End Shift</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
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
                    <div class="modal-footer border-0"><button type="submit" class="btn btn-danger w-100 fw-bold py-3 shadow">CLOSE SHIFT</button></div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tabsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content border-0">
                <div class="modal-header bg-dark text-white"><h5 class="modal-title"><i class="bi bi-receipt me-2"></i> Open Tabs</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0 align-middle">
                            <thead class="table-dark"><tr><th class="ps-4">Time</th><th>Customer</th><th>Total</th><th class="text-end pe-4">Action</th></tr></thead>
                            <tbody>
                                <?php if(empty($openTabs)): ?><tr><td colspan="4" class="text-center p-5 text-muted">No open tabs found.</td></tr><?php endif; ?>
                                <?php foreach($openTabs as $t): ?>
                                <tr>
                                    <td class="ps-4 text-muted small"><?= date('H:i', strtotime($t['created_at'])) ?></td>
                                    <td class="fw-bold text-dark"><?= htmlspecialchars($t['customer_name']) ?></td>
                                    <td class="fw-bold text-danger">ZMW <?= number_format($t['final_total'], 2) ?></td>
                                    <td class="text-end pe-4"><form method="POST"><input type="hidden" name="sale_id" value="<?= $t['id'] ?>"><button name="recall_tab" class="btn btn-primary btn-sm fw-bold px-3 shadow-sm">PAY / EDIT</button></form></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="reportModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl">
            <div class="modal-content h-100 border-0 shadow-lg">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold" id="reportTitle"><i class="bi bi-file-text me-2"></i> Shift Report</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh; background: #525659;">
                    <iframe id="reportFrame" src="" style="width:100%; height:100%; border:none;"></iframe>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary px-4 fw-bold" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary px-4 fw-bold shadow-sm" onclick="document.getElementById('reportFrame').contentWindow.print()"><i class="bi bi-printer me-2"></i> Print</button>
                </div>
            </div>
        </div>
    </div>
    
    <div class="modal fade" id="locationModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
             <div class="modal-content shadow-lg"><div class="modal-header bg-dark text-white"><h5 class="modal-title">Switch Station</h5></div><div class="modal-body bg-light"><form method="POST"><?php foreach($sellableLocations as $loc): ?><button name="set_pos_location" value="1" class="btn btn-white border w-100 mb-2 py-3 fw-bold text-start shadow-sm d-flex justify-content-between align-items-center hover-shadow"><?= htmlspecialchars($loc['name']) ?> <i class="bi bi-chevron-right text-muted"></i> <input type="hidden" name="pos_location_id" value="<?= $loc['id'] ?>"></button><?php endforeach; ?></form></div></div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filterCat(id, btn) { document.querySelectorAll('.cat-pill').forEach(e=>e.classList.remove('active')); btn.classList.add('active'); document.querySelectorAll('.item').forEach(e=>{ e.style.display = (id=='all'||e.dataset.cat==id)?'block':'none'; }); }
        function filter() { let v=document.getElementById('search').value.toLowerCase(); document.querySelectorAll('.item').forEach(e=>{ e.style.display = e.dataset.name.includes(v)?'block':'none'; }); }
        function toggleCart() { document.getElementById('cartPanel').classList.toggle('expanded'); }
        
        let total = <?= $total ?? 0 ?>;
        
        function toggleSplit() {
            let isSplit = document.getElementById('splitToggle').checked;
            document.getElementById('singlePayment').style.display = isSplit ? 'none' : 'block';
            document.getElementById('splitPayment').style.display = isSplit ? 'block' : 'none';
            if(isSplit) {
                document.getElementById('amount1').value = (total / 2).toFixed(2);
                document.getElementById('amount2').value = (total / 2).toFixed(2);
            }
            calcChange();
        }

        function calcChange() {
            let isSplit = document.getElementById('splitToggle').checked;
            let tendered = 0;
            if (isSplit) {
                let a1 = parseFloat(document.getElementById('amount1').value) || 0;
                let a2 = parseFloat(document.getElementById('amount2').value) || 0;
                tendered = a1 + a2;
            } else {
                tendered = parseFloat(document.getElementById('tenderedSingle').value) || 0;
            }
            let change = tendered - total;
            document.getElementById('changeDisplay').innerText = 'ZMW ' + (change > 0 ? change.toFixed(2) : '0.00');
            if(change < 0) {
                 document.getElementById('changeDisplay').classList.add('text-danger');
                 document.getElementById('changeDisplay').classList.remove('text-dark');
            } else {
                 document.getElementById('changeDisplay').classList.remove('text-danger');
                 document.getElementById('changeDisplay').classList.add('text-dark');
            }
        }
        
        const reportModal = new bootstrap.Modal(document.getElementById('reportModal'));
        const reportFrame = document.getElementById('reportFrame');
        const reportTitle = document.getElementById('reportTitle');

        function showShiftReport(shiftId) {
            reportTitle.innerText = "X-Read (Open Shift)";
            reportFrame.src = "index.php?page=print_shift&shift_id=" + shiftId;
            reportModal.show();
        }

        <?php if (isset($_GET['closed_shift_id'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                reportTitle.innerText = "Z-Report (Closed Shift)";
                reportFrame.src = "index.php?page=print_shift&shift_id=<?= $_GET['closed_shift_id'] ?>";
                reportModal.show();
                
                document.getElementById('reportModal').addEventListener('hidden.bs.modal', function () {
                    window.location.href = 'index.php?page=pos';
                });
            });
        <?php endif; ?>

        <?php if (isset($_SESSION['last_sale_id'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                reportTitle.innerText = "Transaction Receipt";
                reportFrame.src = "index.php?page=receipt&sale_id=<?= $_SESSION['last_sale_id'] ?>&mode=double";
                reportModal.show();
            });
            <?php unset($_SESSION['last_sale_id']); ?>
        <?php endif; ?>
    </script>
</body>
</html>
