<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>POS - <?= htmlspecialchars($locationName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root { --theme-brown: #3e2723; --theme-gold: #ffc107; --theme-orange: #fd7e14; --theme-cream: #fff8e1; }
        html, body { height: 100%; margin: 0; padding: 0; overflow: hidden; background: #fcfbf9; }
        body { display: flex; flex-direction: column; }
        .header-custom { flex: 0 0 auto; background-color: var(--theme-brown); border-bottom: 3px solid var(--theme-gold); z-index: 1000; }
        .btn-header { border: 1px solid rgba(255,255,255,0.2); color: white; padding: 5px 12px; font-size: 0.9rem; transition: 0.2s; }
        .btn-header:hover { background: rgba(255,255,255,0.1); border-color: white; }
        .btn-header-danger { background: #dc3545; border: none; color: white; font-weight: bold; }
        .btn-header-danger:hover { background: #c82333; }
        .workspace { flex: 1; display: flex; overflow: hidden; position: relative; }
        .product-section { flex: 1; display: flex; flex-direction: column; overflow: hidden; min-width: 0; }
        .category-bar { flex: 0 0 auto; overflow-x: auto; white-space: nowrap; padding: 10px; background: #fff; border-bottom: 1px solid #ddd; -webkit-overflow-scrolling: touch; }
        .search-bar { flex: 0 0 auto; padding: 10px; background: #f8f9fa; border-bottom: 1px solid #eee; }
        .product-list { flex: 1; overflow-y: auto; padding: 15px; background: #fcfbf9; }
        .cart-panel { width: 400px; display: flex; flex-direction: column; background: #fff; border-left: 2px solid var(--theme-brown); box-shadow: -5px 0 15px rgba(0,0,0,0.05); z-index: 900; }
        .cart-header { flex: 0 0 auto; padding: 10px; border-bottom: 1px solid #eee; background: #fff; }
        .cart-items { flex: 1; overflow-y: auto; background-color: var(--theme-cream); padding: 10px; }
        .cart-footer { flex: 0 0 auto; padding: 15px; background: #fff; border-top: 1px solid #dee2e6; }
        .cat-pill { display: inline-block; padding: 8px 16px; margin-right: 8px; border-radius: 20px; border: 1px solid #ddd; background: #fff; cursor: pointer; font-weight: 600; color: var(--theme-brown); transition: 0.2s; }
        .cat-pill.active { background: var(--theme-brown); color: var(--theme-gold); border-color: var(--theme-brown); }
        .item-card { border: none; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: transform 0.1s; height: 100%; }
        .item-card:active { transform: scale(0.98); }
        .item-card:disabled { opacity: 0.6; cursor: not-allowed; filter: grayscale(1); }
        .badge-stock { position: absolute; top: 8px; right: 8px; font-size: 0.7rem; }
        .btn-theme-orange { background-color: var(--theme-orange); color: white; font-weight: bold; border: none; }
        .btn-theme-orange:hover { background-color: #e66b0d; color: white; }
        @media (max-width: 991px) { .cart-panel { width: 340px; } }
        @media (max-width: 768px) {
            .workspace { flex-direction: column; }
            .cart-panel { position: absolute; bottom: 0; left: 0; right: 0; width: 100%; height: auto; max-height: 55px; border-left: none; border-top: 4px solid var(--theme-brown); transition: max-height 0.3s ease-out; }
            .cart-panel.expanded { max-height: 85vh; }
            .cart-items { display: none; }
            .cart-panel.expanded .cart-items { display: block; }
            .product-list { padding-bottom: 70px; }
            .header-extras { display: none; }
            .item-card .btn { padding: 0.5rem; }
        }
    </style>
</head>
<body>

    <div class="header-custom text-white p-2 d-flex justify-content-between align-items-center shadow">
        <div class="fw-bold ms-2 text-truncate d-flex align-items-center">
            <i class="bi bi-shop text-warning me-2"></i> <?= htmlspecialchars($locationName) ?>
            <button class="btn btn-sm btn-link text-warning ms-1 p-0 border-0" data-bs-toggle="modal" data-bs-target="#locationModal"><i class="bi bi-pencil-square"></i></button>
        </div>
        <div class="header-extras d-flex gap-2">
            <button class="btn btn-header" data-bs-toggle="modal" data-bs-target="#tabsModal"><i class="bi bi-receipt"></i> Tabs</button>
            <a href="index.php?page=pickup" class="btn btn-header"><i class="bi bi-bell"></i> Pickup</a>
            <?php if ($activeShiftId): ?>
                <button onclick="openShiftReport()" class="btn btn-header-danger rounded px-3"><i class="bi bi-power"></i> End Shift</button>
            <?php else: ?>
                <button class="btn btn-secondary fw-bold px-3 disabled"><i class="bi bi-lock"></i> REGISTER LOCKED</button>
            <?php endif; ?>
            <a href="index.php?page=dashboard" class="btn btn-header"><i class="bi bi-house"></i> Exit</a>
        </div>
        <div class="d-md-none"><button class="btn btn-sm btn-outline-warning" type="button" data-bs-toggle="collapse" data-bs-target="#mobileMenu"><i class="bi bi-list"></i></button></div>
    </div>

    <?php if (!$activeShiftId && !$pendingShift && $locationId > 0): ?>
    <div class="modal fade show" tabindex="-1" data-bs-backdrop="static" style="display:block; background: rgba(0,0,0,0.85); z-index: 2000;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-clock-fill"></i> Start New Shift</h5>
                </div>
                <form method="POST">
                    <div class="modal-body p-4 text-center">
                        <?php if(isset($_SESSION['swal_type']) && $_SESSION['swal_type'] == 'error'): ?>
                            <div class="alert alert-danger shadow-sm text-start">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= htmlspecialchars($_SESSION['swal_msg']) ?>
                            </div>
                            <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
                        <?php endif; ?>

                        <i class="bi bi-cash-coin display-1 text-success opacity-50 mb-3"></i>
                        <p class="mb-4">Enter starting float. A Manager must approve this.</p>
                        
                        <div class="form-floating mb-3">
                            <input type="text" inputmode="decimal" name="starting_cash" id="float" class="form-control form-control-lg fw-bold text-center fs-2" value="0.00" required>
                            <label for="float" class="text-center w-100">Cash Float (ZMW)</label>
                        </div>
                        <input type="hidden" name="request_start_shift" value="1">
                    </div>
                    <div class="modal-footer justify-content-center pb-4 pt-0 border-0">
                        <a href="index.php?page=dashboard" class="btn btn-outline-secondary px-4">Exit</a>
                        <button type="submit" class="btn btn-success fw-bold px-5 btn-lg">REQUEST START</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($pendingShift): ?>
    <div class="modal fade show" tabindex="-1" data-bs-backdrop="static" style="display:block; background: rgba(50, 20, 0, 0.9); z-index: 2000;">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg border-top border-5 border-warning">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-shield-lock-fill text-warning"></i> Manager Approval Required</h5>
                </div>
                <form method="POST">
                    <div class="modal-body p-4">
                        <?php if(isset($_SESSION['swal_type']) && $_SESSION['swal_type'] == 'error'): ?>
                            <div class="alert alert-danger shadow-sm">
                                <i class="bi bi-exclamation-octagon-fill me-2"></i> <?= htmlspecialchars($_SESSION['swal_msg']) ?>
                            </div>
                            <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
                        <?php endif; ?>

                        <div class="alert alert-warning d-flex align-items-center">
                            <i class="bi bi-person-badge display-4 me-3"></i>
                            <div>
                                <strong>Employee:</strong> <?= htmlspecialchars($pendingShift['cashier_name']) ?><br>
                                <strong>Opening Balance:</strong> ZMW <?= number_format($pendingShift['starting_cash'], 2) ?>
                            </div>
                        </div>
                        
                        <input type="hidden" name="pending_shift_id" value="<?= $pendingShift['id'] ?>">
                        <input type="hidden" name="approve_shift_start" value="1">

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">MANAGER USERNAME</label>
                            <input type="text" name="mgr_username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">MANAGER PASSWORD</label>
                            <input type="password" name="mgr_password" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer justify-content-between border-0 pt-0 pb-4 px-4">
                        <a href="index.php?page=dashboard" class="btn btn-outline-secondary">Cancel</a>
                        <button type="submit" class="btn btn-warning fw-bold px-4">VERIFY & OPEN</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($locationId == 0): ?>
    <div class="modal fade show" tabindex="-1" data-bs-backdrop="static" style="display:block; background: rgba(0,0,0,0.8);">
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
                        <a href="index.php?page=dashboard" class="btn btn-secondary w-100">Exit</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="modal fade" id="locationModal" tabindex="-1" data-bs-backdrop="static"><div class="modal-dialog modal-dialog-centered"><div class="modal-content border-0"><div class="modal-header text-white" style="background-color: var(--theme-brown);"><h5 class="modal-title">Select Station</h5></div><form method="POST"><div class="modal-body p-4 d-grid gap-2"><input type="hidden" name="set_pos_location" value="1"><?php foreach($sellableLocations as $loc): ?><button type="submit" name="pos_location_id" value="<?= $loc['id'] ?>" class="btn btn-outline-dark btn-lg"><?= htmlspecialchars($loc['name']) ?></button><?php endforeach; ?></div><div class="modal-footer justify-content-center pt-0 border-0"><?php if (isset($_SESSION['pos_location_id'])): ?><button type="button" class="btn btn-secondary w-100" data-bs-dismiss="modal">Cancel</button><?php else: ?><a href="index.php?page=dashboard" class="btn btn-secondary w-100">Exit</a><?php endif; ?></div></form></div></div></div>
    <div class="modal fade" id="checkoutModal" tabindex="-1"><form method="POST" class="modal-dialog"><div class="modal-content border-0"><div class="modal-header text-white" style="background-color: var(--theme-orange);"><h5>Finalize Sale</h5></div><div class="modal-body"><input type="hidden" name="checkout" value="1"><label class="small fw-bold text-muted">CUSTOMER</label><input type="text" name="customer_name" class="form-control mb-3 fw-bold" required value="<?= isset($_SESSION['pos_member']) ? htmlspecialchars($_SESSION['pos_member']['name']) : ($_SESSION['current_customer'] ?? 'Walk-in') ?>"><?php if (isset($_SESSION['pos_member']) && $_SESSION['pos_member']['points_balance'] > 0): ?><div class="alert alert-warning py-2 d-flex align-items-center mb-3"><input class="form-check-input me-2" type="checkbox" name="redeem_points" value="1" id="redeemCheck" onchange="updateTotal()"><label class="form-check-label small" for="redeemCheck">Use Points (Max: <?= number_format(min($total, $_SESSION['pos_member']['points_balance']), 2) ?>)</label></div><?php endif; ?><label class="small fw-bold text-muted">PAYMENT METHOD</label><select name="payment_method" id="payMethod" class="form-select mb-3 form-select-lg" onchange="toggleMomo(); toggleAmountRequired();"><option value="cash">Cash</option><option value="card">Bank Card</option><option value="mobile_money">Mobile Money</option><?php if (!isset($_SESSION['current_tab_id'])): ?><option value="pending">Add to Tab (Pay Later)</option><?php endif; ?></select><div id="momoOptions" style="display:none;" class="mb-3 p-3 border rounded bg-light text-center"><div class="btn-group w-100" role="group"><input type="radio" class="btn-check" name="momo_provider" id="mtn" value="MTN" checked><label class="btn btn-outline-warning" for="mtn">MTN</label><input type="radio" class="btn-check" name="momo_provider" id="airtel" value="Airtel"><label class="btn btn-outline-danger" for="airtel">Airtel</label><input type="radio" class="btn-check" name="momo_provider" id="zamtel" value="Zamtel"><label class="btn btn-outline-success" for="zamtel">Zamtel</label></div></div><div class="bg-light p-3 rounded border"><div class="d-flex justify-content-between mb-2"><span class="small fw-bold">TOTAL AMOUNT</span><span class="fw-bold fs-4 text-dark" id="displayTotal">ZMW <?= number_format($total, 2) ?></span></div><label class="small fw-bold text-muted">AMOUNT TENDERED</label><input type="number" step="0.01" name="amount_tendered" id="tendered" class="form-control form-control-lg fw-bold" required oninput="calcChange()"><div class="mt-2 text-center small text-muted">Change: <span id="changeDue" class="fw-bold text-dark fs-5">0.00</span></div></div></div><div class="modal-footer"><button type="submit" class="btn btn-theme-orange w-100 fw-bold btn-lg">CONFIRM PAYMENT</button></div></div></form></div>
    <div class="modal fade" id="tabsModal" tabindex="-1"><div class="modal-dialog modal-xl modal-fullscreen-md-down"><div class="modal-content"><div class="modal-header bg-warning"><h5 class="modal-title">Tabs</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body p-0"><div class="table-responsive"><table class="table table-striped mb-0"><thead><tr><th>Time</th><th>Loc</th><th>Cashier</th><th>Customer</th><th>Total</th><th></th></tr></thead><tbody><?php foreach($openTabs as $t): ?><tr><td><?= date('H:i', strtotime($t['created_at'])) ?></td><td><?= htmlspecialchars($t['loc_name']) ?></td><td><?= htmlspecialchars($t['cashier']) ?></td><td class="fw-bold"><?= htmlspecialchars($t['customer_name']) ?></td><td class="text-danger fw-bold"><?= number_format($t['final_total'], 2) ?></td><td><form method="POST"><input type="hidden" name="sale_id" value="<?= $t['id'] ?>"><button name="recall_tab" class="btn btn-primary btn-sm">Pay</button></form></td></tr><?php endforeach; ?></tbody></table></div></div></div></div></div>
    <div class="modal fade" id="reportModal" tabindex="-1"><div class="modal-dialog modal-xl"><div class="modal-content"><div class="modal-header bg-dark text-white"><h5 class="modal-title">Shift Report</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button></div><div class="modal-body p-0" style="height: 80vh;"><iframe id="reportFrame" src="" style="width:100%; height:100%; border:none;"></iframe></div></div></div></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let originalTotal = <?= $total ?>;
        function toggleCart() { document.getElementById('cartPanel').classList.toggle('expanded'); }
        function filterCat(catId, btn) { document.querySelectorAll('.cat-pill').forEach(el => el.classList.remove('active')); btn.classList.add('active'); document.querySelectorAll('.item').forEach(el => { if (catId === 'all' || el.getAttribute('data-cat') == catId) { el.style.display = 'block'; } else { el.style.display = 'none'; } }); }
        function updateTotal() { let currentTotal = originalTotal; if (document.getElementById('redeemCheck')?.checked) { <?php if(isset($_SESSION['pos_member'])): ?>currentTotal -= <?= min($total, $_SESSION['pos_member']['points_balance']) ?>;<?php endif; ?> } document.getElementById('displayTotal').innerText = 'ZMW ' + currentTotal.toFixed(2); calcChange(currentTotal); }
        function filter() { let val = document.getElementById('search').value.toLowerCase(); document.querySelectorAll('.item').forEach(el => { el.style.display = el.getAttribute('data-name').includes(val) ? 'block' : 'none'; }); }
        function toggleMomo() { document.getElementById('momoOptions').style.display = (document.getElementById('payMethod').value === 'mobile_money') ? 'block' : 'none'; }
        function toggleAmountRequired() { const input = document.getElementById('tendered'); if (document.getElementById('payMethod').value === 'pending') input.removeAttribute('required'); else input.setAttribute('required', 'required'); }
        function calcChange(baseTotal = null) { let total = baseTotal !== null ? baseTotal : parseFloat(document.getElementById('displayTotal').innerText.replace('ZMW ', '')); let tendered = parseFloat(document.getElementById('tendered').value) || 0; document.getElementById('changeDue').innerText = Math.max(0, tendered - total).toFixed(2); }
        
        function openShiftReport() { document.getElementById('reportFrame').src = 'index.php?page=print_shift'; new bootstrap.Modal(document.getElementById('reportModal')).show(); }
        
        <?php if (isset($_SESSION['last_sale_id'])): ?>
            document.addEventListener("DOMContentLoaded", function() { document.getElementById('reportFrame').src = 'index.php?page=receipt&sale_id=<?= $_SESSION['last_sale_id'] ?>&mode=double'; new bootstrap.Modal(document.getElementById('reportModal')).show(); });
            <?php unset($_SESSION['last_sale_id']); ?>
        <?php endif; ?>
        <?php if (isset($_GET['view_tabs'])): ?>
            document.addEventListener("DOMContentLoaded", function() { new bootstrap.Modal(document.getElementById('tabsModal')).show(); });
        <?php endif; ?>
        toggleAmountRequired();
    </script>
</body>
</html>
