<?php
$title = $settings['shop_title'] ?: ($settings['business_name'] ?? 'Order online');
$accent = $settings['theme_accent'] ?? '#ffc107';
$dark = $settings['theme_color'] ?? '#2c2c2c';
$money = fn($v) => 'K' . number_format((float)$v, 2);
$cartCount = $cartCount ?? 0;
$u = fn(string $v, array $q = []) => 'index.php?page=shop&view=' . $v . ($q ? '&' . http_build_query($q) : '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.css">
    <style>
        :root { --accent: <?= htmlspecialchars($accent) ?>; --dark: <?= htmlspecialchars($dark) ?>; }
        body { background: #f6f6f6; padding-bottom: 90px; }
        .shop-nav { background: var(--dark); color: #fff; }
        .shop-nav a { color: #fff; text-decoration: none; }
        .btn-accent { background: var(--accent); border-color: var(--accent); color: #212529; font-weight: 700; }
        .btn-accent:hover { filter: brightness(.95); color: #212529; }
        .cat-chip { white-space: nowrap; border-radius: 999px; }
        .item-card { border: 0; border-radius: 14px; box-shadow: 0 1px 4px rgba(0,0,0,.08); transition: transform .1s; }
        .item-card:active { transform: scale(.98); }
        .item-card.unavailable { opacity: .55; }
        .price { color: var(--dark); font-weight: 800; }
        .cart-bar { position: fixed; left: 0; right: 0; bottom: 0; background: var(--dark); color: #fff; padding: 12px 16px; box-shadow: 0 -2px 10px rgba(0,0,0,.2); z-index: 1030; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .step { width: 36px; height: 36px; border-radius: 50%; background: #e9ecef; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
        .step.done { background: var(--accent); }
        .form-control, .form-select { border-radius: 10px; }
    </style>
</head>
<body>
<nav class="shop-nav py-2 sticky-top">
    <div class="container d-flex justify-content-between align-items-center">
        <a href="<?= $u('menu') ?>" class="fw-bold fs-5 text-truncate"><i class="bi bi-shop"></i> <?= htmlspecialchars($title) ?></a>
        <div class="d-flex align-items-center gap-3 small">
            <?php if ($customer): ?>
                <a href="<?= $u('orders') ?>"><i class="bi bi-receipt"></i> <span class="d-none d-sm-inline">My orders</span></a>
                <a href="<?= $u('account') ?>"><i class="bi bi-person-circle"></i> <span class="d-none d-sm-inline"><?= htmlspecialchars(explode(' ', $customer['name'])[0]) ?></span></a>
            <?php else: ?>
                <a href="<?= $u('login') ?>"><i class="bi bi-box-arrow-in-right"></i> Sign in</a>
                <a href="<?= $u('register') ?>" class="btn btn-accent btn-sm">Register</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<div class="container py-3" style="max-width: 980px">
<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> shadow-sm"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<?php if ($view === 'closed'): ?>
    <div class="text-center py-5"><i class="bi bi-moon-stars fs-1 text-muted"></i><h4 class="mt-3">Online ordering is currently closed</h4><p class="text-muted"><?= !empty($settings['shop_phone']) ? 'Call us on ' . htmlspecialchars($settings['shop_phone']) : 'Please visit us in store.' ?></p></div>

<?php elseif ($view === 'menu'): ?>
    <?php if (!empty($settings['shop_intro'])): ?><p class="text-muted mb-3"><?= htmlspecialchars($settings['shop_intro']) ?></p><?php endif; ?>
    <div class="input-group mb-3 shadow-sm">
        <span class="input-group-text bg-white border-0"><i class="bi bi-search"></i></span>
        <input type="search" id="menuSearch" class="form-control border-0" placeholder="Search the menu…" oninput="filterMenu()">
    </div>
    <div class="d-flex gap-2 overflow-auto pb-2 mb-3" id="catChips">
        <button class="btn btn-sm btn-dark cat-chip active" data-cat="" onclick="pickCat(this)">All</button>
        <?php foreach ($categories as $cat => $n): ?>
        <button class="btn btn-sm btn-outline-dark cat-chip" data-cat="<?= htmlspecialchars($cat) ?>" onclick="pickCat(this)"><?= htmlspecialchars($cat) ?> <span class="badge bg-secondary ms-1"><?= (int)$n ?></span></button>
        <?php endforeach; ?>
    </div>
    <div class="row g-3" id="menuGrid">
        <?php if (!$products): ?><div class="col-12 text-center text-muted py-5">The menu is being updated. Please check back shortly.</div><?php endif; ?>
        <?php foreach ($products as $p): ?>
        <div class="col-6 col-md-4 menu-item" data-cat="<?= htmlspecialchars($p['category']) ?>" data-name="<?= htmlspecialchars(strtolower($p['name'] . ' ' . $p['category'] . ' ' . $p['sku'])) ?>">
            <div class="card item-card h-100 <?= $p['available'] ? '' : 'unavailable' ?>">
                <div class="card-body d-flex flex-column">
                    <div class="small text-muted"><?= htmlspecialchars($p['category']) ?></div>
                    <div class="fw-bold flex-grow-1"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <span class="price"><?= $money($p['price']) ?></span>
                        <?php if ($p['available']): ?>
                        <button class="btn btn-accent btn-sm rounded-pill px-3" onclick="addToCart(<?= (int)$p['id'] ?>, this)"><i class="bi bi-plus-lg"></i> Add</button>
                        <?php else: ?><span class="badge bg-secondary">Sold out</span><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="cart-bar <?= $cartCount ? '' : 'd-none' ?>" id="cartBar">
        <div class="container d-flex justify-content-between align-items-center" style="max-width:980px">
            <div><span class="badge bg-light text-dark" id="cartBarCount"><?= (int)$cartCount ?></span> item(s) · <strong id="cartBarTotal"><?= $money($cartTotal) ?></strong></div>
            <a href="<?= $u('cart') ?>" class="btn btn-accent rounded-pill px-4">View basket <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
    <script>
    function pickCat(btn) { document.querySelectorAll('.cat-chip').forEach(function (b) { b.classList.remove('active', 'btn-dark'); b.classList.add('btn-outline-dark'); }); btn.classList.add('active', 'btn-dark'); btn.classList.remove('btn-outline-dark'); filterMenu(); }
    function filterMenu() { const cat = (document.querySelector('.cat-chip.active') || {}).dataset ? document.querySelector('.cat-chip.active').dataset.cat : ''; const q = (document.getElementById('menuSearch').value || '').toLowerCase().trim(); document.querySelectorAll('.menu-item').forEach(function (el) { const okCat = !cat || el.dataset.cat === cat; const okQ = !q || el.dataset.name.indexOf(q) !== -1; el.classList.toggle('d-none', !(okCat && okQ)); }); }
    function addToCart(pid, btn) { btn.disabled = true; const body = new URLSearchParams({ cart_add: pid, qty: 1, ajax: 1 }); fetch('index.php?page=shop&view=menu', { method: 'POST', body: body, credentials: 'same-origin' }).then(r => r.json()).then(function (res) { btn.disabled = false; if (res.status !== 'ok') { alert(res.msg || 'Could not add'); return; } document.getElementById('cartBar').classList.remove('d-none'); document.getElementById('cartBarCount').textContent = res.count; document.getElementById('cartBarTotal').textContent = 'K' + Number(res.total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); btn.innerHTML = '<i class="bi bi-check-lg"></i> Added'; setTimeout(function () { btn.innerHTML = '<i class="bi bi-plus-lg"></i> Add'; }, 900); }).catch(function () { btn.disabled = false; }); }
    </script>

<?php elseif ($view === 'cart'): ?>
    <h4 class="fw-bold mb-3"><i class="bi bi-basket"></i> Your basket</h4>
    <?php if (!$cart): ?>
        <div class="text-center py-5 text-muted"><i class="bi bi-basket fs-1"></i><p class="mt-2">Nothing here yet.</p><a href="<?= $u('menu') ?>" class="btn btn-accent rounded-pill px-4">Browse the menu</a></div>
    <?php else: ?>
    <form method="POST" action="<?= $u('cart') ?>">
        <input type="hidden" name="cart_update" value="1">
        <div class="card item-card mb-3">
            <ul class="list-group list-group-flush">
            <?php foreach ($cart as $line): ?>
                <li class="list-group-item d-flex align-items-center gap-3">
                    <div class="flex-grow-1"><div class="fw-bold"><?= htmlspecialchars($line['name']) ?></div><div class="small text-muted"><?= $money($line['price']) ?> each</div></div>
                    <input type="number" min="0" name="qty[<?= (int)$line['id'] ?>]" value="<?= (int)$line['qty'] ?>" class="form-control text-center" style="width:80px" onchange="this.form.submit()">
                    <div class="price text-end" style="min-width:90px"><?= $money($line['price'] * $line['qty']) ?></div>
                </li>
            <?php endforeach; ?>
            </ul>
            <div class="card-footer bg-white d-flex justify-content-between fw-bold fs-5"><span>Subtotal</span><span><?= $money($cartTotal) ?></span></div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= $u('menu') ?>" class="btn btn-outline-dark rounded-pill px-4"><i class="bi bi-plus"></i> Add more</a>
            <button type="submit" class="btn btn-outline-secondary rounded-pill">Update</button>
            <button type="submit" name="cart_clear" value="1" class="btn btn-outline-danger rounded-pill" formnovalidate>Clear</button>
            <a href="<?= $u('checkout') ?>" class="btn btn-accent rounded-pill px-4 ms-auto">Checkout <i class="bi bi-arrow-right"></i></a>
        </div>
    </form>
    <?php if ($minOrder > 0 && $cartTotal < $minOrder): ?><div class="alert alert-warning mt-3 small">Minimum order is <?= $money($minOrder) ?>.</div><?php endif; ?>
    <?php endif; ?>

<?php elseif ($view === 'checkout'): ?>
    <h4 class="fw-bold mb-3"><i class="bi bi-bag-check"></i> Checkout</h4>
    <?php if (!$cart): ?><div class="alert alert-warning">Your basket is empty. <a href="<?= $u('menu') ?>">Back to menu</a></div>
    <?php else: ?>
    <form method="POST" action="<?= $u('checkout') ?>" class="row g-3">
        <input type="hidden" name="place_order" value="1">
        <div class="col-lg-7">
            <div class="card item-card mb-3"><div class="card-body">
                <label class="form-label fw-bold small text-muted">HOW WOULD YOU LIKE IT?</label>
                <div class="d-flex gap-2 mb-3">
                    <input type="radio" class="btn-check" name="order_type" id="otPickup" value="pickup" checked onchange="toggleDelivery()"><label class="btn btn-outline-dark rounded-pill flex-fill" for="otPickup"><i class="bi bi-bag"></i> Collect in store</label>
                    <?php if ($deliveryOn): ?><input type="radio" class="btn-check" name="order_type" id="otDelivery" value="delivery" onchange="toggleDelivery()"><label class="btn btn-outline-dark rounded-pill flex-fill" for="otDelivery"><i class="bi bi-bicycle"></i> Delivery<?= $deliveryFee > 0 ? ' (+' . $money($deliveryFee) . ')' : '' ?></label><?php endif; ?>
                </div>
                <div id="deliveryBox" class="d-none mb-3">
                    <label class="form-label fw-bold small text-muted">DELIVERY ADDRESS</label>
                    <textarea name="address" class="form-control" rows="2" placeholder="Street, area, landmarks"><?= htmlspecialchars($customer['address'] ?? '') ?></textarea>
                </div>
                <div class="row g-2">
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">WHEN</label><input type="text" name="pickup_time" class="form-control" placeholder="ASAP or e.g. 13:30"></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">PHONE</label><input type="text" class="form-control" value="<?= htmlspecialchars($customer['phone']) ?>" disabled></div>
                    <div class="col-12"><label class="form-label fw-bold small text-muted">NOTES FOR THE KITCHEN</label><input type="text" name="notes" class="form-control" maxlength="300" placeholder="No onions, extra chilli…"></div>
                </div>
            </div></div>
            <div class="card item-card"><div class="card-body">
                <label class="form-label fw-bold small text-muted">PAYMENT</label>
                <?php if ($flwOn): ?><div class="form-check mb-2"><input class="form-check-input" type="radio" name="pay_mode" id="pmOnline" value="online" <?= !$counterOn ? 'checked' : '' ?>><label class="form-check-label" for="pmOnline"><i class="bi bi-phone"></i> Pay now — Mobile Money or Card <span class="badge bg-light text-dark border">Flutterwave</span></label></div><?php endif; ?>
                <?php if ($counterOn): ?><div class="form-check"><input class="form-check-input" type="radio" name="pay_mode" id="pmCounter" value="counter" checked><label class="form-check-label" for="pmCounter"><i class="bi bi-cash"></i> Pay at the counter / on delivery</label></div><?php endif; ?>
                <?php if (!$flwOn && !$counterOn): ?><div class="text-danger small">No payment option is enabled. Please contact the store.</div><?php endif; ?>
            </div></div>
        </div>
        <div class="col-lg-5">
            <div class="card item-card"><div class="card-body">
                <h6 class="fw-bold">Order summary</h6>
                <?php foreach ($cart as $line): ?><div class="d-flex justify-content-between small"><span><?= (int)$line['qty'] ?> × <?= htmlspecialchars($line['name']) ?></span><span><?= $money($line['price'] * $line['qty']) ?></span></div><?php endforeach; ?>
                <hr>
                <div class="d-flex justify-content-between"><span>Subtotal</span><span><?= $money($cartTotal) ?></span></div>
                <div class="d-flex justify-content-between text-muted d-none" id="feeRow"><span>Delivery</span><span><?= $money($deliveryFee) ?></span></div>
                <div class="d-flex justify-content-between fw-bold fs-5 mt-2"><span>Total</span><span id="grandTotal" data-sub="<?= $cartTotal ?>" data-fee="<?= $deliveryFee ?>"><?= $money($cartTotal) ?></span></div>
                <button class="btn btn-accent w-100 rounded-pill py-2 mt-3" <?= (!$flwOn && !$counterOn) ? 'disabled' : '' ?>>Place order</button>
                <a href="<?= $u('cart') ?>" class="btn btn-link w-100 text-muted small">Back to basket</a>
            </div></div>
        </div>
    </form>
    <script>
    function toggleDelivery() { const d = document.getElementById('otDelivery'); const on = d && d.checked; document.getElementById('deliveryBox').classList.toggle('d-none', !on); document.getElementById('feeRow').classList.toggle('d-none', !on); const g = document.getElementById('grandTotal'); const t = parseFloat(g.dataset.sub) + (on ? parseFloat(g.dataset.fee) : 0); g.textContent = 'K' + t.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    </script>
    <?php endif; ?>

<?php elseif ($view === 'orders'): ?>
    <h4 class="fw-bold mb-3"><i class="bi bi-receipt"></i> My orders</h4>
    <?php if (empty($orders)): ?><div class="text-center text-muted py-5">No orders yet. <a href="<?= $u('menu') ?>">Order something tasty</a>.</div><?php endif; ?>
    <div class="list-group shadow-sm">
    <?php foreach ($orders ?? [] as $o): $live = $o['status'] === 'pending'; ?>
        <a href="<?= $u('order', ['id' => $o['id']]) ?>" class="list-group-item list-group-item-action py-3">
            <div class="d-flex justify-content-between"><strong>Order #<?= (int)$o['id'] ?></strong><span class="price"><?= $money($o['final_total']) ?></span></div>
            <div class="small text-muted"><?= date('d M Y H:i', strtotime($o['created_at'])) ?> · <?= (int)$o['item_count'] ?> item(s) · <?= strtoupper(htmlspecialchars($o['order_type'] ?? 'pickup')) ?></div>
            <div class="mt-1"><span class="badge <?= $live ? ((int)$o['items_cooking'] > 0 ? 'bg-warning text-dark' : 'bg-info text-dark') : ($o['status'] === 'completed' ? 'bg-success' : 'bg-secondary') ?>"><?= $live ? ((int)$o['items_cooking'] > 0 ? 'Being prepared' : 'Ready for collection') : ucfirst($o['status']) ?></span> <span class="badge <?= $o['payment_status'] === 'paid' ? 'bg-success' : 'bg-light text-dark border' ?>"><?= $o['payment_status'] === 'paid' ? 'Paid' : 'Pay at counter' ?></span></div>
        </a>
    <?php endforeach; ?>
    </div>

<?php elseif ($view === 'order' && !empty($order)): ?>
    <?php $o = $order; $cooking = count(array_filter($o['items'], fn($i) => $i['status'] !== 'ready')); $done = $o['status'] !== 'pending'; ?>
    <a href="<?= $u('orders') ?>" class="small text-muted"><i class="bi bi-arrow-left"></i> All orders</a>
    <div class="card item-card mt-2 mb-3"><div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div><h4 class="fw-bold mb-0">Order #<?= (int)$o['id'] ?></h4><div class="small text-muted"><?= date('d M Y H:i', strtotime($o['created_at'])) ?> · <?= strtoupper(htmlspecialchars($o['order_type'] ?? 'pickup')) ?></div></div>
            <span class="badge fs-6 <?= $o['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?>"><?= $o['payment_status'] === 'paid' ? 'PAID' : 'PAY AT COUNTER' ?></span>
        </div>
        <div class="d-flex align-items-center gap-2 my-4">
            <span class="step done">1</span><div class="flex-grow-1 border-top <?= $cooking === 0 || $done ? 'border-warning border-2' : '' ?>"></div>
            <span class="step <?= $cooking === 0 || $done ? 'done' : '' ?>">2</span><div class="flex-grow-1 border-top <?= $done ? 'border-warning border-2' : '' ?>"></div>
            <span class="step <?= $done ? 'done' : '' ?>">3</span>
        </div>
        <div class="d-flex justify-content-between small text-muted mb-3"><span>Received</span><span><?= $cooking === 0 || $done ? 'Ready' : 'Being prepared' ?></span><span><?= $done ? ucfirst($o['status']) : 'Collected' ?></span></div>
        <table class="table table-sm mb-0">
            <?php foreach ($o['items'] as $i): ?><tr><td><?= (int)$i['quantity'] ?> × <?= htmlspecialchars($i['name']) ?> <?php if (!$done): ?><span class="status-dot <?= $i['status'] === 'ready' ? 'bg-success' : 'bg-warning' ?>" title="<?= htmlspecialchars($i['status']) ?>"></span><?php endif; ?></td><td class="text-end"><?= $money($i['price'] * $i['quantity']) ?></td></tr><?php endforeach; ?>
            <?php if ((float)$o['surcharge_amount'] > 0): ?><tr><td class="text-muted"><?= htmlspecialchars($o['surcharge_reason'] ?: 'Fee') ?></td><td class="text-end"><?= $money($o['surcharge_amount']) ?></td></tr><?php endif; ?>
            <tr class="fw-bold fs-5"><td>Total</td><td class="text-end"><?= $money($o['final_total']) ?></td></tr>
        </table>
        <?php if ($o['order_notes']): ?><div class="small text-muted mt-2"><i class="bi bi-chat-left-text"></i> <?= htmlspecialchars($o['order_notes']) ?></div><?php endif; ?>
        <?php if ($o['gateway_ref']): ?><div class="small text-muted">Payment ref <?= htmlspecialchars($o['gateway_ref']) ?></div><?php endif; ?>
        <?php if ($o['zra_rcpt_no']): ?><div class="small text-muted">ZRA receipt no <?= htmlspecialchars($o['zra_rcpt_no']) ?></div><?php endif; ?>
    </div></div>
    <?php if (!$done): ?><p class="text-center small text-muted">This page refreshes automatically.</p><script>setTimeout(function () { location.reload(); }, 30000);</script><?php endif; ?>
    <?php if (!empty($settings['shop_phone'])): ?><p class="text-center small">Questions? Call <a href="tel:<?= htmlspecialchars($settings['shop_phone']) ?>"><?= htmlspecialchars($settings['shop_phone']) ?></a></p><?php endif; ?>

<?php elseif ($view === 'login'): ?>
    <div class="col-md-6 col-lg-5 mx-auto">
        <div class="card item-card"><div class="card-body p-4">
            <h4 class="fw-bold mb-3">Sign in</h4>
            <form method="POST" action="<?= $u('login') ?>">
                <input type="hidden" name="login" value="1">
                <div class="mb-3"><label class="form-label fw-bold small text-muted">MOBILE NUMBER</label><input type="tel" name="phone" class="form-control form-control-lg" placeholder="097 123 4567" required autofocus></div>
                <div class="mb-3"><label class="form-label fw-bold small text-muted">PIN / PASSWORD</label><input type="password" name="password" class="form-control form-control-lg" required></div>
                <button class="btn btn-accent w-100 rounded-pill py-2">Sign in</button>
            </form>
            <p class="text-center small mt-3 mb-0">New here? <a href="<?= $u('register') ?>">Create an account</a></p>
        </div></div>
    </div>

<?php elseif ($view === 'register'): ?>
    <div class="col-md-7 col-lg-6 mx-auto">
        <div class="card item-card"><div class="card-body p-4">
            <h4 class="fw-bold mb-1">Create your account</h4>
            <p class="text-muted small">Just your name and mobile number — we'll use it to let you know when your order is ready.</p>
            <form method="POST" action="<?= $u('register') ?>">
                <input type="hidden" name="register" value="1">
                <div class="row g-2">
                    <div class="col-12"><label class="form-label fw-bold small text-muted">FULL NAME</label><input type="text" name="name" class="form-control form-control-lg" required></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">MOBILE NUMBER</label><input type="tel" name="phone" class="form-control form-control-lg" placeholder="097 123 4567" required></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">EMAIL (optional)</label><input type="email" name="email" class="form-control form-control-lg"></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">CHOOSE A PIN / PASSWORD</label><input type="password" name="password" class="form-control form-control-lg" minlength="4" required></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">DELIVERY ADDRESS (optional)</label><input type="text" name="address" class="form-control form-control-lg"></div>
                </div>
                <button class="btn btn-accent w-100 rounded-pill py-2 mt-3">Register</button>
            </form>
            <p class="text-center small mt-3 mb-0">Already registered? <a href="<?= $u('login') ?>">Sign in</a></p>
        </div></div>
    </div>

<?php elseif ($view === 'account' && $customer): ?>
    <div class="col-md-7 col-lg-6 mx-auto">
        <div class="card item-card"><div class="card-body p-4">
            <h4 class="fw-bold mb-3"><i class="bi bi-person-circle"></i> My details</h4>
            <form method="POST" action="<?= $u('account') ?>">
                <input type="hidden" name="update_profile" value="1">
                <div class="row g-2">
                    <div class="col-12"><label class="form-label fw-bold small text-muted">NAME</label><input type="text" name="name" class="form-control" value="<?= htmlspecialchars($customer['name']) ?>" required></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">MOBILE</label><input type="text" class="form-control" value="<?= htmlspecialchars($customer['phone']) ?>" disabled></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">EMAIL</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($customer['email'] ?? '') ?>"></div>
                    <div class="col-12"><label class="form-label fw-bold small text-muted">DELIVERY ADDRESS</label><input type="text" name="address" class="form-control" value="<?= htmlspecialchars($customer['address'] ?? '') ?>"></div>
                    <div class="col-12"><label class="form-label fw-bold small text-muted">NEW PIN / PASSWORD (leave blank to keep)</label><input type="password" name="new_password" class="form-control" minlength="4"></div>
                </div>
                <button class="btn btn-accent w-100 rounded-pill py-2 mt-3">Save</button>
            </form>
            <form method="POST" action="<?= $u('account') ?>" class="mt-2"><input type="hidden" name="logout" value="1"><button class="btn btn-outline-secondary w-100 rounded-pill">Sign out</button></form>
        </div></div>
    </div>
<?php endif; ?>
</div>
<footer class="text-center text-muted small py-4"><?= htmlspecialchars($settings['business_name'] ?? '') ?><?= !empty($settings['shop_phone']) ? ' · ' . htmlspecialchars($settings['shop_phone']) : '' ?></footer>
<script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
