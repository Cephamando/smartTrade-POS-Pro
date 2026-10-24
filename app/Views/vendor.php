<?php
$screen = $screen ?? 'login';
$vendor = $vendor ?? null;
$flash = $flash ?? ['type' => '', 'msg' => ''];
$home = $home ?? ['inquiries' => 0, 'quotes' => 0, 'awaiting' => 0, 'to_dispatch' => 0];
$inquiries = $inquiries ?? [];
$orders = $orders ?? [];
$offerings = $offerings ?? [];
$stockItems = $stockItems ?? [];
$u = static fn(string $view, array $q = []): string => 'index.php?page=vendor&view=' . $view . ($q ? '&' . http_build_query($q) : '');
$money = static fn($n): string => 'ZMW ' . number_format((float)$n, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111111">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="Vendor portal">
    <link rel="manifest" href="vendor.webmanifest">
    <link rel="apple-touch-icon" href="pwa/icon-192.png">
    <title>Vendor portal<?= $vendor ? ' · ' . htmlspecialchars((string)$vendor['name']) : '' ?></title>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.css">
    <style>
        :root { --ink: #141414; --sun: #e2b04a; --paper: #fff8e4; }
        body { margin: 0; background: var(--paper); color: var(--ink); font-family: "Segoe UI", sans-serif; padding-bottom: 88px; }
        .vp-bar { background: #111; color: #fff; padding: 14px 16px; display: flex; justify-content: space-between; align-items: center; }
        .vp-bar a { color: #fff; text-decoration: none; font-weight: 700; }
        .vp-wrap { max-width: 720px; margin: 0 auto; padding: 16px; }
        .vp-card { background: #fff; border-radius: 16px; padding: 16px; box-shadow: 0 8px 24px rgba(0,0,0,.06); margin-bottom: 12px; }
        .vp-nav { position: fixed; left: 0; right: 0; bottom: 0; background: #111; display: flex; justify-content: space-around; padding: 8px 4px calc(8px + env(safe-area-inset-bottom)); z-index: 5; }
        .vp-nav a { color: rgba(255,255,255,.72); text-decoration: none; font-size: .75rem; text-align: center; min-width: 64px; }
        .vp-nav a.on, .vp-nav a:hover { color: var(--sun); }
        .vp-nav i { display: block; font-size: 1.15rem; }
        .stat { font-size: 1.4rem; font-weight: 800; }
        .line { display: flex; justify-content: space-between; gap: 12px; padding: 8px 0; border-bottom: 1px solid #f0e6cc; }
        #pwaInstall { background: #111; color: #fff; border-radius: 12px; padding: 12px; margin-bottom: 12px; }
        #pwaInstall button { margin-right: 8px; }
    </style>
</head>
<body>
<div class="vp-bar">
    <a href="<?= $vendor ? $u('home') : 'index.php?page=vendor' ?>">Vendor portal</a>
    <?php if ($vendor): ?><span class="small"><?= htmlspecialchars((string)$vendor['name']) ?></span><?php endif; ?>
</div>
<div class="vp-wrap">
    <div id="pwaInstall" data-dismiss="vendor-pwa-dismiss" hidden>
        <div class="small mb-2">Add this portal to your home screen.</div>
        <button type="button" class="btn btn-warning btn-sm fw-bold" data-install>Install</button>
        <button type="button" class="btn btn-outline-light btn-sm" data-dismiss-install>Not now</button>
    </div>
    <?php if (($flash['msg'] ?? '') !== ''): ?>
        <div class="alert <?= ($flash['type'] ?? '') === 'error' ? 'alert-danger' : 'alert-success' ?>"><?= htmlspecialchars((string)$flash['msg']) ?></div>
    <?php endif; ?>

    <?php if ($screen === 'login' || !$vendor): ?>
        <div class="vp-card">
            <h1 class="h4 fw-bold">Sign in</h1>
            <p class="text-muted small">Use the email and password the shop gave you.</p>
            <form method="POST" action="index.php?page=vendor">
                <input type="hidden" name="vendor_login" value="1">
                <label class="form-label small fw-bold">EMAIL</label>
                <input type="email" name="email" class="form-control mb-3" required autocomplete="username">
                <label class="form-label small fw-bold">PASSWORD</label>
                <input type="password" name="password" class="form-control mb-3" required autocomplete="current-password">
                <button class="btn btn-dark w-100 fw-bold">Sign in</button>
            </form>
        </div>
    <?php elseif ($screen === 'home'): ?>
        <div class="row g-2">
            <div class="col-6"><a class="vp-card d-block text-decoration-none text-dark" href="<?= $u('inquiries') ?>"><div class="small text-muted">Inquiries to quote</div><div class="stat"><?= (int)$home['inquiries'] ?></div></a></div>
            <div class="col-6"><a class="vp-card d-block text-decoration-none text-dark" href="<?= $u('inquiries') ?>"><div class="small text-muted">Quotes with the shop</div><div class="stat"><?= (int)$home['quotes'] ?></div></a></div>
            <div class="col-6"><a class="vp-card d-block text-decoration-none text-dark" href="<?= $u('orders') ?>"><div class="small text-muted">Orders to confirm</div><div class="stat"><?= (int)$home['awaiting'] ?></div></a></div>
            <div class="col-6"><a class="vp-card d-block text-decoration-none text-dark" href="<?= $u('orders') ?>"><div class="small text-muted">Still to dispatch</div><div class="stat"><?= (int)$home['to_dispatch'] ?></div></a></div>
        </div>
    <?php elseif ($screen === 'products'): ?>
        <?php
        $priced = [];
        foreach ($offerings as $row) {
            $priced[(int)$row['product_id']] = true;
        }
        ?>
        <form method="POST" class="vp-card">
            <input type="hidden" name="save_offering" value="1">
            <h1 class="h5 fw-bold">Your prices</h1>
            <p class="small text-muted">Choose a shop item and the price you charge. The shop orders from this list.</p>
            <label class="form-label small">Item</label>
            <input type="search" class="form-control mb-2" placeholder="Find an item" oninput="var q=this.value.toLowerCase(); document.querySelectorAll('#vendorItem option').forEach(function(o){ o.hidden = q !== '' && o.text.toLowerCase().indexOf(q) === -1; });">
            <select name="product_id" id="vendorItem" class="form-select mb-2" required>
                <option value="">Choose an item</option>
                <?php foreach ($stockItems as $item): if (!empty($priced[(int)$item['id']])) continue; ?>
                    <option value="<?= (int)$item['id'] ?>"><?= htmlspecialchars((string)$item['name']) ?><?= ($item['unit'] ?? '') !== '' ? ' (' . htmlspecialchars((string)$item['unit']) . ')' : '' ?></option>
                <?php endforeach; ?>
            </select>
            <label class="form-label small">Your price (ZMW)</label>
            <input type="number" name="unit_cost" class="form-control mb-3" min="0.01" step="0.01" required inputmode="decimal">
            <button class="btn btn-dark w-100 fw-bold">Save price</button>
        </form>
        <?php if (!$offerings): ?><div class="vp-card text-muted">No prices yet.</div><?php endif; ?>
        <?php foreach ($offerings as $row): ?>
            <div class="vp-card">
                <div class="d-flex justify-content-between gap-2">
                    <div>
                        <strong><?= htmlspecialchars((string)$row['name']) ?></strong>
                        <?php if (($row['unit'] ?? '') !== ''): ?><div class="small text-muted"><?= htmlspecialchars((string)$row['unit']) ?></div><?php endif; ?>
                    </div>
                    <div class="fw-bold"><?= $money($row['unit_cost']) ?></div>
                </div>
                <form method="POST" class="d-flex gap-2 mt-2">
                    <input type="hidden" name="save_offering" value="1">
                    <input type="hidden" name="product_id" value="<?= (int)$row['product_id'] ?>">
                    <input type="number" name="unit_cost" class="form-control form-control-sm" min="0.01" step="0.01" value="<?= htmlspecialchars(number_format((float)$row['unit_cost'], 2, '.', '')) ?>" required>
                    <button class="btn btn-dark btn-sm">Update</button>
                </form>
                <form method="POST" class="mt-2">
                    <input type="hidden" name="remove_offering" value="1">
                    <input type="hidden" name="product_id" value="<?= (int)$row['product_id'] ?>">
                    <button class="btn btn-outline-danger btn-sm">Remove</button>
                </form>
            </div>
        <?php endforeach; ?>
    <?php elseif ($screen === 'inquiries'): ?>
        <?php if (!$inquiries): ?><div class="vp-card text-muted">No inquiries have been sent to you yet.</div><?php endif; ?>
        <?php foreach ($inquiries as $row): ?>
            <a class="vp-card d-block text-decoration-none text-dark" href="<?= $u('inquiry', ['inquiry_id' => (int)$row['id']]) ?>">
                <div class="d-flex justify-content-between"><strong>Inquiry #<?= (int)$row['id'] ?></strong><span class="badge bg-dark"><?= htmlspecialchars((string)$row['status']) ?></span></div>
                <div class="small text-muted"><?= htmlspecialchars((string)$row['location_name']) ?> · <?= (int)$row['item_count'] ?> lines<?= !empty($row['needed_by']) ? ' · needed ' . htmlspecialchars((string)$row['needed_by']) : '' ?></div>
            </a>
        <?php endforeach; ?>
    <?php elseif ($screen === 'inquiry'): ?>
        <?php if (!$inquiry): ?>
            <div class="vp-card">That inquiry is not available.</div>
        <?php else: $quote = $inquiry['quotation'] ?? null; ?>
            <div class="vp-card">
                <h1 class="h5 fw-bold">Inquiry #<?= (int)$inquiry['id'] ?></h1>
                <div class="small text-muted mb-2"><?= htmlspecialchars((string)$inquiry['location_name']) ?><?= !empty($inquiry['needed_by']) ? ' · needed by ' . htmlspecialchars((string)$inquiry['needed_by']) : '' ?></div>
                <?php if (!empty($inquiry['notes'])): ?><p><?= htmlspecialchars((string)$inquiry['notes']) ?></p><?php endif; ?>
                <?php if ((string)$inquiry['status'] === 'converted'): ?>
                    <p class="mb-0">The shop accepted this and raised a purchase order.</p>
                <?php endif; ?>
            </div>
            <?php if (in_array((string)$inquiry['status'], ['sent', 'quoted'], true)): ?>
            <form method="POST" action="index.php?page=vendor&amp;view=inquiry&amp;inquiry_id=<?= (int)$inquiry['id'] ?>" class="vp-card">
                <input type="hidden" name="submit_quote" value="1">
                <input type="hidden" name="inquiry_id" value="<?= (int)$inquiry['id'] ?>">
                <h2 class="h6 fw-bold">Quotation</h2>
                <?php
                $quoted = [];
                foreach (($quote['items'] ?? []) as $line) {
                    $quoted[(int)$line['inquiry_item_id']] = $line;
                }
                foreach ($inquiry['items'] as $i => $item):
                    $prev = $quoted[(int)$item['id']] ?? null;
                ?>
                    <div class="line">
                        <div>
                            <strong><?= htmlspecialchars((string)$item['product_name']) ?></strong>
                            <div class="small text-muted">Asked <?= htmlspecialchars((string)$item['quantity']) ?> <?= htmlspecialchars((string)($item['unit'] ?? '')) ?></div>
                            <input type="hidden" name="lines[<?= $i ?>][inquiry_item_id]" value="<?= (int)$item['id'] ?>">
                            <label class="small">Quantity you can supply</label>
                            <input type="number" step="0.01" min="0" name="lines[<?= $i ?>][quantity]" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($prev['quantity'] ?? $item['quantity'])) ?>">
                            <label class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="lines[<?= $i ?>][cannot_supply]" value="1" <?= !empty($prev) && empty($prev['can_supply']) ? 'checked' : '' ?>> Cannot supply</label>
                        </div>
                        <div style="width:120px">
                            <label class="small">Unit price</label>
                            <input type="number" step="0.01" min="0" name="lines[<?= $i ?>][unit_cost]" class="form-control form-control-sm" value="<?= htmlspecialchars((string)($prev['unit_cost'] ?? '0')) ?>">
                        </div>
                    </div>
                <?php endforeach; ?>
                <div class="row g-2 mt-2">
                    <div class="col-6">
                        <label class="form-label small">Delivery date</label>
                        <input type="date" name="delivery_date" class="form-control" value="<?= htmlspecialchars((string)($quote['delivery_date'] ?? '')) ?>">
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Valid until</label>
                        <input type="date" name="valid_until" class="form-control" value="<?= htmlspecialchars((string)($quote['valid_until'] ?? '')) ?>">
                    </div>
                </div>
                <label class="form-label small mt-2">Note</label>
                <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars((string)($quote['notes'] ?? '')) ?></textarea>
                <button class="btn btn-dark w-100 fw-bold mt-3">Send quotation</button>
            </form>
            <?php elseif ($quote): ?>
            <div class="vp-card">
                <h2 class="h6 fw-bold">Your quotation</h2>
                <?php foreach ($quote['items'] as $line): ?>
                    <div class="line">
                        <span><?= htmlspecialchars((string)$line['product_name']) ?></span>
                        <span><?= empty($line['can_supply']) ? 'Cannot supply' : ($money($line['unit_cost']) . ' × ' . htmlspecialchars((string)$line['quantity'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php endif; ?>
    <?php elseif ($screen === 'orders'): ?>
        <?php if (!$orders): ?><div class="vp-card text-muted">No purchase orders yet.</div><?php endif; ?>
        <?php foreach ($orders as $row): ?>
            <a class="vp-card d-block text-decoration-none text-dark" href="<?= $u('order', ['order_id' => (int)$row['id']]) ?>">
                <div class="d-flex justify-content-between"><strong><?= htmlspecialchars((string)($row['reference_no'] ?: ('Order #' . $row['id']))) ?></strong><span class="badge bg-dark"><?= htmlspecialchars((string)($row['vendor_status'] ?? 'awaiting')) ?></span></div>
                <div class="small text-muted"><?= htmlspecialchars((string)$row['location_name']) ?> · <?= $money($row['total_cost']) ?></div>
            </a>
        <?php endforeach; ?>
    <?php elseif ($screen === 'order'): ?>
        <?php if (!$order): ?>
            <div class="vp-card">That order is not available.</div>
        <?php else: ?>
            <div class="vp-card">
                <h1 class="h5 fw-bold"><?= htmlspecialchars((string)($order['reference_no'] ?: ('Order #' . $order['id']))) ?></h1>
                <div class="small text-muted mb-2"><?= htmlspecialchars((string)$order['location_name']) ?> · <?= htmlspecialchars((string)$order['status']) ?> · <?= htmlspecialchars((string)($order['vendor_status'] ?? 'awaiting')) ?></div>
                <?php if (!empty($order['notes'])): ?><p><?= htmlspecialchars((string)$order['notes']) ?></p><?php endif; ?>
                <?php foreach ($order['items'] as $line): ?>
                    <div class="line">
                        <div>
                            <strong><?= htmlspecialchars((string)$line['product_name']) ?></strong>
                            <div class="small text-muted">Received <?= htmlspecialchars((string)$line['received_qty']) ?> of <?= htmlspecialchars((string)$line['quantity']) ?></div>
                        </div>
                        <div><?= $money($line['unit_cost']) ?></div>
                    </div>
                <?php endforeach; ?>
                <div class="fw-bold text-end mt-2"><?= $money($order['total_cost']) ?></div>
            </div>
            <?php if (!in_array((string)$order['status'], ['cancelled', 'received', 'declined'], true)): ?>
            <div class="vp-card">
                <?php if ((string)($order['vendor_status'] ?? 'awaiting') === 'awaiting'): ?>
                <form method="POST" class="d-grid gap-2">
                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                    <button name="confirm_order" value="1" class="btn btn-dark fw-bold">Confirm order</button>
                    <button name="decline_order" value="1" class="btn btn-outline-danger fw-bold">Decline</button>
                </form>
                <?php endif; ?>
                <?php if ((string)($order['vendor_status'] ?? '') !== 'declined' && (string)($order['vendor_status'] ?? '') !== 'dispatched'): ?>
                <form method="POST" class="mt-3">
                    <input type="hidden" name="dispatch_order" value="1">
                    <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                    <label class="form-label small fw-bold">DELIVERY REFERENCE</label>
                    <input type="text" name="delivery_ref" class="form-control mb-2" maxlength="80" required value="<?= htmlspecialchars((string)($order['vendor_delivery_ref'] ?? '')) ?>">
                    <button class="btn btn-warning w-100 fw-bold">Mark dispatched</button>
                </form>
                <?php elseif (!empty($order['vendor_delivery_ref'])): ?>
                    <div class="small">Delivery reference <?= htmlspecialchars((string)$order['vendor_delivery_ref']) ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="vp-card">
                <h2 class="h6 fw-bold">Received by the shop</h2>
                <?php if (empty($order['receipts'])): ?>
                    <p class="text-muted small mb-0">Nothing has been booked in yet. The received quantities above update when the shop posts a goods-received note.</p>
                <?php else: foreach ($order['receipts'] as $grv): ?>
                    <div class="line">
                        <div>
                            <strong><?= htmlspecialchars((string)($grv['reference_no'] ?: ('GRV #' . $grv['id']))) ?></strong>
                            <div class="small text-muted"><?= htmlspecialchars((string)$grv['created_at']) ?></div>
                        </div>
                        <div><?= $money($grv['total_cost']) ?></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($screen === 'account'): ?>
        <form method="POST" class="vp-card">
            <input type="hidden" name="save_profile" value="1">
            <h1 class="h5 fw-bold"><?= htmlspecialchars((string)$vendor['name']) ?></h1>
            <p class="small text-muted"><?= htmlspecialchars((string)($vendor['portal_email'] ?? '')) ?></p>
            <label class="form-label small">Contact person</label>
            <input type="text" name="contact_person" class="form-control mb-2" value="<?= htmlspecialchars((string)($vendor['contact_person'] ?? '')) ?>">
            <label class="form-label small">Phone</label>
            <input type="text" name="phone" class="form-control mb-2" value="<?= htmlspecialchars((string)($vendor['phone'] ?? '')) ?>">
            <label class="form-label small">New password</label>
            <input type="password" name="new_password" class="form-control mb-3" autocomplete="new-password" placeholder="Leave blank to keep it">
            <button class="btn btn-dark w-100 fw-bold">Save account</button>
        </form>
        <form method="POST">
            <input type="hidden" name="vendor_logout" value="1">
            <button class="btn btn-outline-dark w-100">Sign out</button>
        </form>
    <?php endif; ?>
</div>
<?php if ($vendor): ?>
<nav class="vp-nav">
    <a class="<?= $screen === 'home' ? 'on' : '' ?>" href="<?= $u('home') ?>"><i class="bi bi-house"></i>Home</a>
    <a class="<?= $screen === 'products' ? 'on' : '' ?>" href="<?= $u('products') ?>"><i class="bi bi-tags"></i>Products</a>
    <a class="<?= in_array($screen, ['inquiries', 'inquiry'], true) ? 'on' : '' ?>" href="<?= $u('inquiries') ?>"><i class="bi bi-inboxes"></i>Inquiries</a>
    <a class="<?= in_array($screen, ['orders', 'order'], true) ? 'on' : '' ?>" href="<?= $u('orders') ?>"><i class="bi bi-receipt"></i>Orders</a>
    <a class="<?= $screen === 'account' ? 'on' : '' ?>" href="<?= $u('account') ?>"><i class="bi bi-person"></i>Account</a>
</nav>
<?php endif; ?>
<script src="pwa/register.js"></script>
</body>
</html>
