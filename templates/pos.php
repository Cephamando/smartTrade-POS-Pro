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
        }
        body { background: #fcfbf9; height: 100vh; overflow: hidden; display: flex; flex-direction: column; }
        .product-grid { height: calc(100vh - 140px); overflow-y: auto; padding-bottom: 50px; }
        .cart-panel { height: calc(100vh - 60px); background: white; border-left: 2px solid var(--theme-brown); display: flex; flex-direction: column; }
        .cart-items { flex-grow: 1; overflow-y: auto; background-color: #fff8e1; } /* Light Cream Cart */
        
        .header-custom { background-color: var(--theme-brown); border-bottom: 3px solid var(--theme-gold); }
        .btn-theme-gold { background-color: var(--theme-gold); color: #3e2723; border: none; font-weight: bold; }
        .btn-theme-orange { background-color: var(--theme-orange); color: white; border: none; font-weight: bold; }
        
        /* Mobile Layout */
        @media (max-width: 768px) {
            body { height: auto; overflow: auto; display: block; padding-bottom: 80px; }
            .product-grid { height: auto; overflow: visible; padding-bottom: 0; }
            .cart-panel { 
                position: fixed; bottom: 0; left: 0; right: 0; 
                height: auto; max-height: 50vh; 
                border-top: 3px solid var(--theme-brown); z-index: 1000;
                box-shadow: 0 -5px 20px rgba(0,0,0,0.2);
            }
            .cart-items { display: none; }
            .cart-panel.expanded { height: 80vh; }
            .cart-panel.expanded .cart-items { display: block; }
            .header-extras { display: none; }
        }
    </style>
</head>
<body>

    <div class="header-custom text-white p-2 d-flex justify-content-between align-items-center shadow sticky-top">
        <div class="fw-bold ms-2 text-truncate">
            <i class="bi bi-shop text-warning"></i> <?= htmlspecialchars($locationName) ?>
            <button class="btn btn-sm btn-outline-warning ms-1" data-bs-toggle="modal" data-bs-target="#locationModal"><i class="bi bi-arrow-repeat"></i></button>
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

    <div class="container-fluid flex-grow-1">
        <div class="row h-100">
            <div class="col-md-8 p-3">
                <input type="text" id="search" class="form-control mb-3 border-secondary" placeholder="Search products..." onkeyup="filter()">
                <div class="row g-2 product-grid" id="grid">
                    <?php foreach($products as $p): 
                        $isLow = $p['stock_qty'] > 0 && $p['stock_qty'] <= 5;
                        $isOut = $p['stock_qty'] <= 0;
                    ?>
                    <div class="col-6 col-md-3 item" data-name="<?= strtolower($p['name']) ?>">
                        <form method="POST" class="card h-100 shadow-sm border-0">
                            <button name="add_item" class="btn p-2 text-start h-100 w-100 border-0" style="background-color: #fff;">
                                <div class="fw-bold text-truncate text-dark"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="fw-bold" style="color: var(--theme-brown);">ZMW <?= number_format($p['price'], 2) ?></div>
                                <span class="badge" style="background-color: <?= $isOut ? '#dc3545' : ($isLow ? '#ffc107' : '#3e2723') ?>">
                                    <?= $p['stock_qty'] ?>
                                </span>
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-md-4 cart-panel p-0" id="cartPanel">
                <div class="d-md-none text-white text-center py-1" style="background-color: var(--theme-brown);" onclick="toggleCart()">
                    <i class="bi bi-chevron-up"></i> View Cart <i class="bi bi-chevron-up"></i>
                </div>

                <div class="p-2 border-bottom" style="background-color: #efebe9;">
                    <?php if (isset($_SESSION['pos_member'])): ?>
                        <div class="d-flex justify-content-between align-items-center small">
                            <div class="text-dark"><i class="bi bi-person-check-fill text-success"></i> <b><?= htmlspecialchars($_SESSION['pos_member']['name']) ?></b></div>
                            <form method="POST"><button name="detach_member" class="btn btn-xs btn-outline-danger"><i class="bi bi-x"></i></button></form>
                        </div>
                    <?php else: ?>
                        <form method="POST" class="input-group input-group-sm">
                            <input type="text" name="member_search" class="form-control" placeholder="Member...">
                            <button type="submit" name="search_member" class="btn btn-secondary"><i class="bi bi-search"></i></button>
                        </form>
                    <?php endif; ?>
                </div>

                <div class="cart-items p-3">
                    <?php $total = 0; if(!empty($_SESSION['cart'])): foreach($_SESSION['cart'] as $id => $item): $total += ($item['price'] * $item['qty']); ?>
                    <div class="d-flex justify-content-between mb-2 border-bottom pb-1 border-secondary">
                        <div><strong class="text-dark"><?= htmlspecialchars($item['name']) ?></strong><br><small class="text-muted"><?= $item['qty'] ?> x <?= number_format($item['price'], 2) ?></small></div>
                        <div class="fw-bold" style="color: var(--theme-brown);">ZMW <?= number_format($item['price'] * $item['qty'], 2) ?></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                
                <div class="p-3 border-top" style="background-color: #fff;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-secondary">Total</span>
                        <h3 class="fw-bold m-0" style="color: var(--theme-orange);">ZMW <?= number_format($total, 2) ?></h3>
                    </div>
                    <button class="btn btn-theme-orange btn-lg w-100" data-bs-toggle="modal" data-bs-target="#checkoutModal" <?= $total <= 0 ? 'disabled' : '' ?>>PAY</button>
                    <form method="POST" class="mt-2"><button name="clear_cart" class="btn btn-outline-danger btn-sm w-100">Clear</button></form>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="locationModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-white" style="background-color: var(--theme-brown);"><h5 class="modal-title">Station</h5></div>
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
            <div class="modal-content">
                <div class="modal-header text-white" style="background-color: var(--theme-orange);"><h5>Finalize Sale</h5></div>
                <div class="modal-body">
                    <input type="hidden" name="checkout" value="1">
                    <label class="small fw-bold">Customer</label>
                    <input type="text" name="customer_name" class="form-control mb-3" required value="<?= isset($_SESSION['pos_member']) ? htmlspecialchars($_SESSION['pos_member']['name']) : ($_SESSION['current_customer'] ?? 'Walk-in') ?>">
                    
                    <?php if (isset($_SESSION['pos_member']) && $_SESSION['pos_member']['points_balance'] > 0): ?>
                    <div class="alert alert-info py-2 d-flex align-items-center mb-3">
                        <input class="form-check-input me-2" type="checkbox" name="redeem_points" value="1" id="redeemCheck" onchange="updateTotal()">
                        <label class="form-check-label small" for="redeemCheck">Use Points (Max: <?= number_format(min($total, $_SESSION['pos_member']['points_balance']), 2) ?>)</label>
                    </div>
                    <?php endif; ?>

                    <label class="small fw-bold">Method</label>
                    <select name="payment_method" id="payMethod" class="form-select mb-3" onchange="toggleMomo(); toggleAmountRequired();">
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
                        <div class="d-flex justify-content-between mb-2"><span class="small fw-bold">Amount To Pay:</span><span class="fw-bold fs-5 text-dark" id="displayTotal">ZMW <?= number_format($total, 2) ?></span></div>
                        <label class="small fw-bold">Tendered</label>
                        <input type="number" step="0.01" name="amount_tendered" id="tendered" class="form-control form-control-lg fw-bold" required oninput="calcChange()">
                        <div class="mt-2 text-center small text-muted">Change: <span id="changeDue" class="fw-bold text-dark">0.00</span></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-theme-orange w-100 fw-bold">CONFIRM PAYMENT</button></div>
            </div>
        </form>
    </div>
    
    <div class="modal fade" id="tabsModal" tabindex="-1"><div class="modal-dialog modal-xl modal-fullscreen-md-down"><div class="modal-content"><div class="modal-header bg-warning"><h5 class="modal-title">Tabs</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Time</th><th>Loc</th><th>Cashier</th><th>Customer</th><th>Total</th><th></th></tr></thead><tbody><?php foreach($openTabs as $t): ?><tr><td><?= date('H:i', strtotime($t['created_at'])) ?></td><td><?= htmlspecialchars($t['loc_name']) ?></td><td><?= htmlspecialchars($t['cashier']) ?></td><td class="fw-bold"><?= htmlspecialchars($t['customer_name']) ?></td><td class="text-danger fw-bold"><?= number_format($t['final_total'], 2) ?></td><td><form method="POST"><input type="hidden" name="sale_id" value="<?= $t['id'] ?>"><button name="recall_tab" class="btn btn-primary btn-sm">Pay</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div>
    <div class="modal fade" id="reportModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><iframe id="reportFrame" src="" style="width:100%; height:85vh; border:none;"></iframe></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let originalTotal = <?= $total ?>;
        function toggleCart() { document.getElementById('cartPanel').classList.toggle('expanded'); }
        function updateTotal() {
            let currentTotal = originalTotal;
            if (document.getElementById('redeemCheck')?.checked) { <?php if(isset($_SESSION['pos_member'])): ?>currentTotal -= <?= min($total, $_SESSION['pos_member']['points_balance']) ?>;<?php endif; ?> }
            document.getElementById('displayTotal').innerText = 'ZMW ' + currentTotal.toFixed(2);
            calcChange(currentTotal);
        }
        function filter() {
            let val = document.getElementById('search').value.toLowerCase();
            document.querySelectorAll('.item').forEach(el => { el.style.display = el.getAttribute('data-name').includes(val) ? 'block' : 'none'; });
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
