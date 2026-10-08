<?php
$title = ($settings['shop_title'] ?? '') ?: ($settings['business_name'] ?? 'Order online');
$accent = $settings['theme_accent'] ?? '#ffc107';
$dark = $settings['theme_color'] ?? '#2c2c2c';
$onAccent = (function (string $hex): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        return '#1c140f';
    }
    $l = 0.299 * hexdec(substr($hex, 0, 2)) + 0.587 * hexdec(substr($hex, 2, 2)) + 0.114 * hexdec(substr($hex, 4, 2));
    return $l > 165 ? '#1c140f' : '#fffaf4';
})($accent);
$shopIcon = function (string $cat): string {
    $c = strtolower($cat);
    foreach ([
        'beer' => 'bi-cup-straw', 'cider' => 'bi-cup-straw', 'wine' => 'bi-cup', 'champagne' => 'bi-cup',
        'cocktail' => 'bi-tropical-storm', 'mocktail' => 'bi-tropical-storm', 'shot' => 'bi-droplet',
        'whisky' => 'bi-moisture', 'vodka' => 'bi-moisture', 'gin' => 'bi-moisture', 'rum' => 'bi-moisture',
        'brandy' => 'bi-moisture', 'cognac' => 'bi-moisture', 'liqueur' => 'bi-moisture',
        'food' => 'bi-egg-fried', 'meal' => 'bi-egg-fried', 'burger' => 'bi-egg-fried',
        'juice' => 'bi-cup-hot', 'soda' => 'bi-cup-hot', 'soft' => 'bi-cup-hot', 'mixer' => 'bi-cup-hot',
        'shisha' => 'bi-wind', 'coffee' => 'bi-cup-hot',
    ] as $word => $icon) {
        if (str_contains($c, $word)) {
            return $icon;
        }
    }
    return 'bi-basket3';
};
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
        :root { --accent: <?= htmlspecialchars($accent) ?>; --dark: <?= htmlspecialchars($dark) ?>; --on-accent: <?= htmlspecialchars($onAccent) ?>; --ink: #241810; --paper: #f6efe6; --card: #fffdf9; }
        * { -webkit-tap-highlight-color: transparent; }
        body { margin: 0; background: var(--paper); color: var(--ink); font-family: "Segoe UI", system-ui, sans-serif; padding-bottom: 110px; }
        body::before { content: ""; position: fixed; inset: 0; pointer-events: none; background: radial-gradient(900px 420px at 110% -10%, color-mix(in srgb, var(--accent) 35%, transparent), transparent 60%), radial-gradient(700px 360px at -10% 0%, color-mix(in srgb, var(--dark) 12%, transparent), transparent 55%); }
        a { color: var(--dark); }
        .shop-nav { position: sticky; top: 0; z-index: 1040; background: color-mix(in srgb, var(--dark) 92%, #000); color: #fffaf4; backdrop-filter: blur(8px); box-shadow: 0 8px 24px rgba(40, 18, 6, .18); }
        .shop-nav a { color: #fffaf4; text-decoration: none; }
        .brand-mark { width: 38px; height: 38px; border-radius: 12px; background: var(--accent); color: var(--on-accent); display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }
        .nav-link-soft { opacity: .9; font-weight: 600; }
        .nav-link-soft:hover { opacity: 1; }
        .btn-accent { background: var(--accent); border: 0; color: var(--on-accent); font-weight: 700; }
        .btn-accent:hover, .btn-accent:focus { filter: brightness(1.06); color: var(--on-accent); }
        .shop-wrap { position: relative; max-width: 1040px; }
        .shop-hero { margin: 18px 0 16px; padding: 28px 26px; border-radius: 28px; color: #fffaf4; background: linear-gradient(135deg, var(--dark) 0%, color-mix(in srgb, var(--dark) 62%, var(--accent)) 100%); box-shadow: 0 16px 40px rgba(40, 18, 6, .18); overflow: hidden; position: relative; }
        .shop-hero::after { content: ""; position: absolute; right: -40px; top: -50px; width: 180px; height: 180px; border-radius: 50%; background: color-mix(in srgb, var(--accent) 55%, transparent); }
        .shop-hero .eyebrow { letter-spacing: .18em; text-transform: uppercase; font-size: .72rem; font-weight: 700; opacity: .8; }
        .shop-hero h1 { font-weight: 800; letter-spacing: -.03em; margin: 6px 0 8px; position: relative; }
        .shop-hero p { margin: 0; max-width: 36rem; opacity: .9; position: relative; }
        .search-pill { background: var(--card); border-radius: 999px; box-shadow: 0 8px 24px rgba(40, 18, 6, .08); overflow: hidden; }
        .search-pill .form-control, .search-pill .input-group-text { border: 0; background: transparent; }
        .search-pill .form-control:focus { box-shadow: none; }
        .cat-chip { white-space: nowrap; border-radius: 999px; border: 1px solid color-mix(in srgb, var(--dark) 18%, #fff); background: var(--card); color: var(--ink); font-weight: 650; padding: .35rem .85rem; }
        .cat-chip.active { background: var(--dark); color: #fffaf4; border-color: var(--dark); }
        #catChips { scrollbar-width: none; }
        #catChips::-webkit-scrollbar { display: none; }
        .item-card { border: 0; border-radius: 22px; background: var(--card); box-shadow: 0 10px 28px rgba(40, 18, 6, .07); overflow: hidden; }
        .item-card.unavailable { opacity: .62; }
        .item-face { height: 92px; display: flex; align-items: flex-end; padding: 12px 14px; color: var(--on-accent); background: linear-gradient(145deg, var(--accent), color-mix(in srgb, var(--dark) 35%, var(--accent))); position: relative; }
        .item-face i { position: absolute; right: 10px; top: 8px; font-size: 3.2rem; opacity: .22; }
        .item-face span { font-size: .72rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .price { color: var(--dark); font-weight: 800; }
        .cart-bar { position: fixed; left: 50%; transform: translateX(-50%); bottom: 14px; width: min(680px, calc(100% - 20px)); background: var(--dark); color: #fffaf4; border-radius: 999px; padding: 8px 8px 8px 18px; box-shadow: 0 16px 40px rgba(40, 18, 6, .28); z-index: 1030; }
        .status-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
        .step { width: 36px; height: 36px; border-radius: 50%; background: #efe6dc; color: var(--ink); display: inline-flex; align-items: center; justify-content: center; font-weight: 800; }
        .step.done { background: var(--accent); color: var(--on-accent); }
        .form-control, .form-select { border-radius: 14px; border-color: #eadfd2; background: #fff; }
        .form-control:focus, .form-select:focus { border-color: var(--accent); box-shadow: 0 0 0 .2rem color-mix(in srgb, var(--accent) 25%, transparent); }
        .page-title { font-weight: 800; letter-spacing: -.03em; }
        .shop-foot { position: relative; text-align: center; color: #8a7564; font-size: .85rem; padding: 8px 16px 28px; }
        .list-group-item { border-color: #f0e6da; }
        @media (max-width: 576px) { .shop-hero { padding: 22px 18px; border-radius: 22px; } .shop-hero h1 { font-size: 1.7rem; } }
    </style>
</head>
<body>
<nav class="shop-nav py-2">
    <div class="container shop-wrap d-flex justify-content-between align-items-center gap-2">
        <a href="<?= $u('menu') ?>" class="d-flex align-items-center gap-2 text-truncate">
            <span class="brand-mark"><?= htmlspecialchars(strtoupper(substr($title, 0, 1))) ?></span>
            <span class="fw-bold text-truncate"><?= htmlspecialchars($title) ?></span>
        </a>
        <div class="d-flex align-items-center gap-3 small flex-shrink-0">
            <a href="<?= $u('cart') ?>" class="nav-link-soft position-relative"><i class="bi bi-bag"></i><?php if ($cartCount): ?> <span class="badge rounded-pill" style="background:var(--accent);color:var(--on-accent)"><?= (int)$cartCount ?></span><?php endif; ?></a>
            <?php if ($customer): ?>
                <a href="<?= $u('orders') ?>" class="nav-link-soft"><i class="bi bi-receipt"></i> <span class="d-none d-sm-inline">Orders</span></a>
                <a href="<?= $u('account') ?>" class="nav-link-soft"><i class="bi bi-person-circle"></i> <span class="d-none d-sm-inline"><?= htmlspecialchars(explode(' ', $customer['name'])[0]) ?></span></a>
            <?php else: ?>
                <a href="<?= $u('login') ?>" class="nav-link-soft">Sign in</a>
                <a href="<?= $u('register') ?>" class="btn btn-accent btn-sm rounded-pill px-3">Join</a>
            <?php endif; ?>
        </div>
    </div>
</nav>
<div class="container py-2 shop-wrap">
<?php if ($flash): ?>
    <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?> shadow-sm"><?= htmlspecialchars($flash['msg']) ?></div>
<?php endif; ?>

<?php if ($view === 'closed'): ?>
    <section class="shop-hero text-center">
        <div class="eyebrow">Tonight's kitchen</div>
        <h1>Online ordering is closed</h1>
        <p class="mx-auto"><?= !empty($settings['shop_phone']) ? 'Come and see us in store, or call ' . htmlspecialchars($settings['shop_phone']) . '.' : 'Come and see us in store. We will be taking orders here again soon.' ?></p>
    </section>

<?php elseif ($view === 'menu'): ?>
    <section class="shop-hero">
        <div class="eyebrow">Order ahead</div>
        <h1><?= htmlspecialchars($title) ?></h1>
        <p><?= htmlspecialchars(($settings['shop_intro'] ?? '') ?: 'Fresh from our kitchen. Order ahead and skip the queue.') ?></p>
    </section>
    <div class="input-group search-pill mb-3">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="search" id="menuSearch" class="form-control" placeholder="Search the menu…" oninput="filterMenu()">
    </div>
    <div class="d-flex gap-2 overflow-auto pb-2 mb-3" id="catChips">
        <button type="button" class="cat-chip active" data-cat="" onclick="pickCat(this)">All</button>
        <?php foreach ($categories as $cat => $n): ?>
        <button type="button" class="cat-chip" data-cat="<?= htmlspecialchars($cat) ?>" onclick="pickCat(this)"><?= htmlspecialchars($cat) ?> <span class="opacity-75"><?= (int)$n ?></span></button>
        <?php endforeach; ?>
    </div>
    <div class="row g-3" id="menuGrid">
        <?php if (!$products): ?><div class="col-12 text-center text-muted py-5">The menu is being updated. Please check back shortly.</div><?php endif; ?>
        <?php foreach ($products as $p): ?>
        <div class="col-6 col-md-4 menu-item" data-cat="<?= htmlspecialchars($p['category']) ?>" data-name="<?= htmlspecialchars(strtolower($p['name'] . ' ' . $p['category'] . ' ' . $p['sku'])) ?>">
            <div class="card item-card h-100 <?= $p['available'] ? '' : 'unavailable' ?>">
                <div class="item-face"><i class="bi <?= $shopIcon((string)$p['category']) ?>"></i><span><?= htmlspecialchars($p['category'] ?: 'Menu') ?></span></div>
                <div class="card-body d-flex flex-column pt-3">
                    <div class="fw-bold flex-grow-1"><?= htmlspecialchars($p['name']) ?></div>
                    <div class="d-flex justify-content-between align-items-center mt-3">
                        <span class="price"><?= $money($p['price']) ?></span>
                        <?php if ($p['available']): ?>
                        <button class="btn btn-accent btn-sm rounded-pill px-3" onclick="addToCart(<?= (int)$p['id'] ?>, this)"><i class="bi bi-plus-lg"></i> Add</button>
                        <?php else: ?><span class="badge rounded-pill text-bg-secondary">Sold out</span><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="cart-bar <?= $cartCount ? '' : 'd-none' ?>" id="cartBar">
        <div class="d-flex justify-content-between align-items-center">
            <div><span class="badge rounded-pill" style="background:var(--accent);color:var(--on-accent)" id="cartBarCount"><?= (int)$cartCount ?></span> in your bag · <strong id="cartBarTotal"><?= $money($cartTotal) ?></strong></div>
            <a href="<?= $u('cart') ?>" class="btn btn-accent rounded-pill px-4">View bag <i class="bi bi-arrow-right"></i></a>
        </div>
    </div>
    <script>
    function pickCat(btn) { document.querySelectorAll('.cat-chip').forEach(function (b) { b.classList.remove('active'); }); btn.classList.add('active'); filterMenu(); }
    function filterMenu() { const cat = (document.querySelector('.cat-chip.active') || {}).dataset ? document.querySelector('.cat-chip.active').dataset.cat : ''; const q = (document.getElementById('menuSearch').value || '').toLowerCase().trim(); document.querySelectorAll('.menu-item').forEach(function (el) { const okCat = !cat || el.dataset.cat === cat; const okQ = !q || el.dataset.name.indexOf(q) !== -1; el.classList.toggle('d-none', !(okCat && okQ)); }); }
    function addToCart(pid, btn) { btn.disabled = true; const body = new URLSearchParams({ cart_add: pid, qty: 1, ajax: 1 }); fetch('index.php?page=shop&view=menu', { method: 'POST', body: body, credentials: 'same-origin' }).then(r => r.json()).then(function (res) { btn.disabled = false; if (res.status !== 'ok') { alert(res.msg || 'Could not add'); return; } document.getElementById('cartBar').classList.remove('d-none'); document.getElementById('cartBarCount').textContent = res.count; document.getElementById('cartBarTotal').textContent = 'K' + Number(res.total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); btn.innerHTML = '<i class="bi bi-check-lg"></i> Added'; setTimeout(function () { btn.innerHTML = '<i class="bi bi-plus-lg"></i> Add'; }, 900); }).catch(function () { btn.disabled = false; }); }
    </script>

<?php elseif ($view === 'cart'): ?>
    <h4 class="page-title mb-3">Your bag</h4>
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
    <h4 class="page-title mb-3">Checkout</h4>
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
    <h4 class="page-title mb-3">My orders</h4>
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
<footer class="shop-foot"><?= htmlspecialchars($settings['business_name'] ?? $title) ?><?= !empty($settings['shop_phone']) ? ' · ' . htmlspecialchars($settings['shop_phone']) : '' ?></footer>
<script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
</body>
</html>
