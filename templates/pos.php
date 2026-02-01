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
            --theme-cream: #fff8e1;
        }

        /* 1. APP-LIKE LAYOUT RESET */
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; background: #fcfbf9; }
        body { display: flex; flex-direction: column; }

        /* 2. HEADER (Fixed Top) */
        .header-custom { flex: 0 0 auto; background-color: var(--theme-brown); border-bottom: 3px solid var(--theme-gold); z-index: 1000; }
        
        /* 3. MAIN WORKSPACE (Fills remaining space) */
        .workspace { flex: 1; display: flex; overflow: hidden; position: relative; }
        
        /* LEFT: PRODUCTS (Flexible width) */
        .product-section { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        .category-bar { flex: 0 0 auto; overflow-x: auto; white-space: nowrap; padding: 10px; background: #fff; border-bottom: 1px solid #ddd; -webkit-overflow-scrolling: touch; }
        .search-bar { flex: 0 0 auto; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #eee; }
        .product-list { flex: 1; overflow-y: auto; padding: 15px; background: #fcfbf9; }
        
        /* RIGHT: CART (Fixed width on Desktop) */
        .cart-panel { 
            width: 400px; /* Fixed width for desktop stability */
            display: flex; flex-direction: column; 
            background: #fff; border-left: 2px solid var(--theme-brown); 
            box-shadow: -5px 0 15px rgba(0,0,0,0.05); z-index: 900;
        }
        .cart-header { flex: 0 0 auto; padding: 10px; border-bottom: 1px solid #eee; background: #fff; }
        .cart-items { flex: 1; overflow-y: auto; background-color: var(--theme-cream); padding: 10px; }
        .cart-footer { flex: 0 0 auto; padding: 15px; background: #fff; border-top: 1px solid #dee2e6; }

        /* UI ELEMENTS */
        .cat-pill { display: inline-block; padding: 8px 16px; margin-right: 8px; border-radius: 20px; border: 1px solid #ddd; background: #fff; cursor: pointer; font-weight: 600; color: var(--theme-brown); transition: 0.2s; }
        .cat-pill.active { background: var(--theme-brown); color: var(--theme-gold); border-color: var(--theme-brown); }
        .item-card { border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.1s; height: 100%; }
        .item-card:active { transform: scale(0.98); }
        .badge-stock { position: absolute; top: 8px; right: 8px; font-size: 0.7rem; }
        .btn-theme-orange { background-color: var(--theme-orange); color: white; font-weight: bold; border: none; }
        .btn-theme-orange:hover { background-color: #e66b0d; color: white; }

        /* MOBILE RESPONSIVENESS */
        @media (max-width: 991px) {
            .cart-panel { width: 340px; }
        }
        @media (max-width: 768px) {
            .workspace { flex-direction: column; } /* Stack vertically if needed, but usually we overlay cart */
            .cart-panel {
                position: absolute; bottom: 0; left: 0; right: 0; width: 100%;
                height: auto; max-height: 55px; /* Collapsed state */
                border-left: none; border-top: 4px solid var(--theme-brown);
                transition: max-height 0.3s ease-out;
            }
            .cart-panel.expanded { max-height: 85vh; }
            .cart-items { display: none; } /* Hide items when collapsed */
            .cart-panel.expanded .cart-items { display: block; }
            
            /* Add padding to product list so it scrolls behind the collapsed cart */
            .product-list { padding-bottom: 70px; }
            
            .header-extras { display: none; }
            .item-card .btn { padding: 0.5rem; } /* Compact cards */
        }
    </style>
</head>
<body>

    <div class="header-custom text-white p-2 d-flex justify-content-between align-items-center shadow">
        <div class="fw-bold ms-2 text-truncate">
            <i class="bi bi-shop text-warning"></i> <?= htmlspecialchars($locationName) ?>
            <button class="btn btn-sm btn-outline-warning ms-1 border-0" data-bs-toggle="modal" data-bs-target="#locationModal"><i class="bi bi-arrow-repeat"></i></button>
        </div>
        <div class="header-extras">
            <button class="btn btn-outline-warning btn-sm me-1" data-bs-toggle="modal" data-bs-target="#tabsModal">Tabs</button>
            <a href="index.php?page=pickup" class="btn btn-outline-light btn-sm me-1">Pickup</a>
            <button onclick="openShiftReport()" class="btn btn-danger btn-sm me-1">Shift</button>
            <a href="index.php?page=dashboard" class="btn btn-outline-light btn-sm">Exit</a>
        </div>
        <div class="d-md-none">
            <button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="collapse" data-bs-target="#mobileMenu"><i class="bi bi-list"></i></button>
        </div>
    </div>

    <div class="collapse header-custom p-2 d-md-none" id="mobileMenu">
        <div class="d-grid gap-2">
            <button class="btn btn-outline-warning btn-sm" data-bs-toggle="modal" data-bs-target="#tabsModal">Open Tabs</button>
            <a href="index.php?page=pickup" class="btn btn-outline-light btn-sm">Pickup Screen</a>
            <button onclick="openShiftReport()" class="btn btn-danger btn-sm">End Shift</button>
            <a href="index.php?page=dashboard" class="btn btn-light btn-sm">Exit</a>
        </div>
    </div>

    <div class="workspace">
        
        <div class="product-section">
            <div class="category-bar">
                <span class="cat-pill active" onclick="filterCat('all', this)">ALL</span>
                <?php foreach($categories as $c): ?>
                    <span class="cat-pill" onclick="filterCat('<?= $c['id'] ?>', this)"><?= strtoupper($c['name']) ?></span>
                <?php endforeach; ?>
            </div>

            <div class="search-bar">
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" id="search" class="form-control border-start-0" placeholder="Search products..." onkeyup="filter()">
                </div>
            </div>

            <div class="product-list">
                <div class="row g-2">
                    <?php foreach($products as $p): 
                        $isLow = $p['stock_qty'] > 0 && $p['stock_qty'] <= 5;
                        $isOut = $p['stock_qty'] <= 0;
                    ?>
                    <div class="col-6 col-md-4 col-lg-3 item" data-name="<?= strtolower($p['name']) ?>" data-cat="<?= $p['category_id'] ?>">
                        <form method="POST" class="h-100">
                            <button name="add_item" class="card item-card w-100 p-2 text-start bg-white position-relative">
                                <span class="badge badge-stock rounded-pill text-white" style="background-color: <?= $isOut ? '#dc3545' : ($isLow ? '#ffc107' : '#3e2723') ?>">
                                    <?= $p['stock_qty'] ?>
                                </span>
                                <div class="fw-bold text-dark mb-1" style="font-size: 0.95rem; line-height:1.2; padding-right: 15px;"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="mt-auto fw-bold text-warning">ZMW <?= number_format($p['price'], 2) ?></div>
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="cart-panel" id="cartPanel">
            <div class="d-md-none text-white text-center py-2 fw-bold d-flex justify-content-between px-3 align-items-center" style="background-color: var(--theme-brown); cursor: pointer;" onclick="toggleCart()">
                <span><i class="bi bi-chevron-up me-2"></i> Current Order</span>
                <span class="badge bg-warning text-dark"><?= count($_SESSION['cart'] ?? []) ?></span>
            </div>

            <div class="cart-header">
                <?php if (isset($_SESSION['pos_member'])): ?>
                    <div class="d-flex justify-content-between align-items-center alert alert-success m-0 py-1 px-2 small">
                        <div><i class="bi bi-person-check-fill"></i> <b><?= htmlspecialchars($_SESSION['pos_member']['name']) ?></b></div>
                        <form method="POST"><button name="detach_member" class="btn btn-xs text-danger p-0 border-0"><i class="bi bi-x-circle-fill"></i></button></form>
                    </div>
                <?php else: ?>
                    <form method="POST" class="input-group input-group-sm">
                        <input type="text" name="member_search" class="form-control" placeholder="Member Name/Phone...">
                        <button type="submit" name="search_member" class="btn btn-secondary"><i class="bi bi-search"></i></button>
                    </form>
                <?php endif; ?>
            </div>

            <div class="cart-items">
                <?php $total = 0; if(!empty($_SESSION['cart'])): foreach($_SESSION['cart'] as $id => $item): $total += ($item['price'] * $item['qty']); ?>
                <div class="card mb-2 border-0 shadow-sm">
                    <div class="card-body p-2 d-flex justify-content-between align-items-center">
                        <div style="flex: 1; min-width: 0;">
                            <div class="fw-bold text-dark text-truncate"><?= htmlspecialchars($item['name']) ?></div>
                            <div class="small text-muted">@ <?= number_format($item['price'], 2) ?></div>
                        </div>
                        <div class="d-flex align-items-center bg-light rounded px-1 mx-2">
                            <form method="POST" class="d-inline"><input type="hidden" name="update_qty" value="1"><input type="hidden" name="action" value="dec"><input type="hidden" name="product_id" value="<?= $id ?>"><button class="btn btn-sm btn-link text-danger px-1 py-0 text-decoration-none fw-bold" style="font-size: 1.2rem;">-</button></form>
                            <span class="fw-bold mx-2"><?= $item['qty'] ?></span>
                            <form method="POST" class="d-inline"><input type="hidden" name="update_qty" value="1"><input type="hidden" name="action" value="inc"><input type="hidden" name="product_id" value="<?= $id ?>"><button class="btn btn-sm btn-link text-success px-1 py-0 text-decoration-none fw-bold" style="font-size: 1.2rem;">+</button></form>
                        </div>
                        <div class="text-end">
                            <div class="fw-bold text-brown">ZMW <?= number_format($item['price'] * $item['qty'], 2) ?></div>
                            <form method="POST"><input type="hidden" name="remove_item" value="1"><input type="hidden" name="product_id" value="<?= $id ?>"><button class="btn btn-xs text-muted p-0 border-0"><i class="bi bi-trash"></i></button></form>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
                <?php if(empty($_SESSION['cart'])): ?>
                    <div class="text-center text-muted mt-5"><i class="bi bi-cart-x display-1 opacity-25"></i><br>Empty Cart</div>
                <?php endif; ?>
            </div>

            <div class="cart-footer">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="fw-bold text-secondary">Total</span>
                    <h2 class="fw-bold m-0 text-dark">ZMW <?= number_format($total, 2) ?></h2>
                </div>
                <button class="btn btn-theme-orange w-100 py-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#checkoutModal" <?= $total <= 0 ? 'disabled' : '' ?>>
                    PAY NOW <i class="bi bi-arrow-right-circle ms-2"></i>
                </button>
                <form method="POST" class="mt-2"><button name="clear_cart" class="btn btn-light btn-sm w-100 text-danger border">Clear Order</button></form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="locationModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0">
                <div class="modal-header text-white" style="background-color: var(--theme-brown);"><h5 class="modal-title">Select Station</h5></div>
                <form method="POST">
                    <div class="modal-body p-4 d-grid gap-2">
                        <input type="hidden" name="set_pos_location" value="1">
                        <?php foreach($sellableLocations as $loc): ?>
                            <button type="submit" name="pos_location_id" value="<?= $loc['id'] ?>" class="btn btn-outline-dark btn-lg"><?= htmlspecialchars($loc['name']) ?></button>
                        <?php endforeach; ?>
                    </div>
                    <div class="modal-footer justify-content-center pt-0 border-0">
                        <?php if (isset($_SESSION['pos_location_id'])): ?><button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cancel</button><?php else: ?><a href="index.php?page=dashboard" class="btn btn-secondary w-100">Exit</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <form method="POST" class="modal-dialog">
            <div class="modal-content border-0">
                <div class="modal-header text-white" style="background-color: var(--theme-orange);"><h5>Finalize Sale</h5></div>
                <div class="modal-body">
                    <input type="hidden" name="checkout" value="1">
                    <label class="small fw-bold text-muted">CUSTOMER</label>
                    <input type="text" name="customer_name" class="form-control mb-3 fw-bold" required value="<?= isset($_SESSION['pos_member']) ? htmlspecialchars($_SESSION['pos_member']['name']) : ($_SESSION['current_customer'] ?? 'Walk-in') ?>">
                    
                    <?php if (isset($_SESSION['pos_member']) && $_SESSION['pos_member']['points_balance'] > 0): ?>
                    <div class="alert alert-warning py-2 d-flex align-items-center mb-3">
                        <input class="form-check-input me-2" type="checkbox" name="redeem_points" value="1" id="redeemCheck" onchange="updateTotal()">
                        <label class="form-check-label small" for="redeemCheck">Use Points (Max: <?= number_format(min($total, $_SESSION['pos_member']['points_balance']), 2) ?>)</label>
                    </div>
                    <?php endif; ?>

                    <label class="small fw-bold text-muted">PAYMENT METHOD</label>
                    <select name="payment_method" id="payMethod" class="form-select mb-3 form-select-lg" onchange="toggleMomo(); toggleAmountRequired();">
                        <option value="cash">Cash</option>
                        <option value="card">Bank Card</option>
                        <option value="mobile_money">Mobile Money</option>
                        <?php if (!isset($_SESSION['current_tab_id'])): ?><option value="pending">Add to Tab (Pay Later)</option><?php endif; ?>
                    </select>

                    <div id="momoOptions" style="display:none;" class="mb-3 p-3 border rounded bg-light text-center">
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="momo_provider" id="mtn" value="MTN" checked><label class="btn btn-outline-warning" for="mtn">MTN</label>
                            <input type="radio" class="btn-check" name="momo_provider" id="airtel" value="Airtel"><label class="btn btn-outline-danger" for="airtel">Airtel</label>
                            <input type="radio" class="btn-check" name="momo_provider" id="zamtel" value="Zamtel"><label class="btn btn-outline-success" for="zamtel">Zamtel</label>
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded border">
                        <div class="d-flex justify-content-between mb-2"><span class="small fw-bold">TOTAL AMOUNT</span><span class="fw-bold fs-4 text-dark" id="displayTotal">ZMW <?= number_format($total, 2) ?></span></div>
                        <label class="small fw-bold text-muted">AMOUNT TENDERED</label>
                        <input type="number" step="0.01" name="amount_tendered" id="tendered" class="form-control form-control-lg fw-bold" required oninput="calcChange()">
                        <div class="mt-2 text-center small text-muted">Change: <span id="changeDue" class="fw-bold text-dark fs-5">0.00</span></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-theme-orange w-100 fw-bold btn-lg">CONFIRM PAYMENT</button></div>
            </div>
        </form>
    </div>
    
    <div class="modal fade" id="tabsModal" tabindex="-1"><div class="modal-dialog modal-xl modal-fullscreen-md-down"><div class="modal-content"><div class="modal-header bg-warning"><h5 class="modal-title">Tabs</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Time</th><th>Loc</th><th>Cashier</th><th>Customer</th><th>Total</th><th></th></tr></thead><tbody><?php foreach($openTabs as $t): ?><tr><td><?= date('H:i', strtotime($t['created_at'])) ?></td><td><?= htmlspecialchars($t['loc_name']) ?></td><td><?= htmlspecialchars($t['cashier']) ?></td><td class="fw-bold"><?= htmlspecialchars($t['customer_name']) ?></td><td class="text-danger fw-bold"><?= number_format($t['final_total'], 2) ?></td><td><form method="POST"><input type="hidden" name="sale_id" value="<?= $t['id'] ?>"><button name="recall_tab" class="btn btn-primary btn-sm">Pay</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div>
    
    <div class="modal fade" id="reportModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><iframe id="reportFrame" src="" style="width:100%; height:85vh; border:none;"></iframe></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let originalTotal = <?= $total ?>;
        
        function toggleCart() { document.getElementById('cartPanel').classList.toggle('expanded'); }
        
        function filterCat(catId, btn) {
            document.querySelectorAll('.cat-pill').forEach(el => el.classList.remove('active'));
            btn.classList.add('active');
            
            document.querySelectorAll('.item').forEach(el => {
                if (catId === 'all' || el.getAttribute('data-cat') == catId) {
                    el.style.display = 'block';
                } else {
                    el.style.display = 'none';
                }
            });
        }

        function updateTotal() {
            let currentTotal = originalTotal;
            if (document.getElementById('redeemCheck')?.checked) { <?php if(isset($_SESSION['pos_member'])): ?>currentTotal -= <?= min($total, $_SESSION['pos_member']['points_balance']) ?>;<?php endif; ?> }
            document.getElementById('displayTotal').innerText = 'ZMW ' + currentTotal.toFixed(2);
            calcChange(currentTotal);
        }
        function filter() {
            let val = document.getElementById('search').value.toLowerCase();
            document.querySelectorAll('.item').forEach(el => { 
                el.style.display = el.getAttribute('data-name').includes(val) ? 'block' : 'none'; 
            });
        }
        function toggleMomo() { document.getElementById('momoOptions').style.display = (document.getElementById('payMethod').value === 'mobile_money') ? 'block' : 'none'; }
        function toggleAmountRequired() {
            const input = document.getElementById('tendered');
            if (document.getElementById('payMethod').value === 'pending') input.removeAttribute('required'); else input.setAttribute('required', 'required');
        }
        function calcChange(baseTotal = null) {
            let total = baseTotal !== null ? baseTotal : parseFloat(document.getElementById('displayTotal').innerText.replace('ZMW ', ''));
            let tendered = parseFloat(document.getElementById('tendered').value) || 0;
            document.getElementById('changeDue').innerText = Math.max(0, tendered - total).toFixed(2);
        }
        function openShiftReport() {
            document.getElementById('reportFrame').src = 'index.php?page=pos&action=close_shift_report';
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        }
        <?php if (isset($_SESSION['last_sale_id'])): ?>
            document.addEventListener("DOMContentLoaded", function() {
                document.getElementById('reportFrame').src = 'index.php?page=receipt&sale_id=<?= $_SESSION['last_sale_id'] ?>&mode=double';
                new bootstrap.Modal(document.getElementById('reportModal')).show();
            });
            <?php unset($_SESSION['last_sale_id']); ?>
        <?php endif; ?>
        <?php if (!isset($_SESSION['pos_location_id'])): ?>
            document.addEventListener("DOMContentLoaded", function() { new bootstrap.Modal(document.getElementById('locationModal')).show(); });
        <?php endif; ?>
        <?php if (isset($_GET['view_tabs'])): ?>
            document.addEventListener("DOMContentLoaded", function() { new bootstrap.Modal(document.getElementById('tabsModal')).show(); });
        <?php endif; ?>
        toggleAmountRequired();
    </script>
</body>
</html>
