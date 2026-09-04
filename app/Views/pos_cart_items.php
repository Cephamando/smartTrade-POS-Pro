<?php
$cart = $cart ?? [];
$tier = $tier ?? (defined('LICENSE_TIER') ? LICENSE_TIER : 'lite');
if (empty($cart)): ?>
<div class="text-center mt-5 text-muted"><p>Cart is empty</p></div>
<?php else: foreach ($cart as $key => $item):
    $isRefundItem = $item['is_refund'] ?? false;
?>
<div class="cart-item <?= $isRefundItem ? 'border-danger border-2' : '' ?>">
    <div class="flex-grow-1 me-2 overflow-hidden">
        <div class="fw-bold text-truncate <?= $isRefundItem ? 'text-danger' : '' ?>"><?= htmlspecialchars($item['name']) ?></div>
        <div class="d-flex align-items-center gap-2 mt-1">
            <small class="text-muted fw-bold <?= $isRefundItem ? 'text-danger' : '' ?>">@ ZMW <?= number_format($item['price'], 2) ?></small>
            <?php if (!$isRefundItem): ?>
                <?php $isFood = in_array(strtolower($item['cat_type'] ?? ''), ['food', 'meal']); if ($isFood && in_array($tier, ['pro+', 'enterprise'])): ?>
                    <span class="badge bg-warning text-dark border shadow-sm" style="font-size:0.7rem;"><i class="bi bi-fire"></i> Kitchen</span>
                <?php else: ?>
                    <form method="POST" class="d-inline js-pos-cart"><input type="hidden" name="toggle_fulfillment" value="1"><input type="hidden" name="cart_key" value="<?= htmlspecialchars((string)$key) ?>"><?php if (in_array($tier, ['pro', 'pro+', 'enterprise'])): ?><button class="btn btn-sm btn-fulfillment <?= ($item['fulfillment']??'collected')=='collected'?'btn-outline-success':'btn-warning' ?>"><?= ($item['fulfillment']??'collected')=='collected' ? 'Got It' : 'Later' ?></button><?php else: ?><span class="badge bg-success">Collected</span><?php endif; ?></form>
                <?php endif; ?>
            <?php else: ?><span class="badge bg-danger">RETURN</span><?php endif; ?>
        </div>
    </div>
    <div class="d-flex align-items-center bg-light rounded border">
        <form method="POST" class="d-flex m-0 js-pos-cart"><input type="hidden" name="cart_key" value="<?= htmlspecialchars((string)$key) ?>"><input type="hidden" name="update_qty" value="1"><button name="action" value="dec" class="btn btn-sm text-danger px-2 fw-bold">-</button><span class="px-2 fw-bold"><?= $item['qty'] ?></span><button name="action" value="inc" class="btn btn-sm text-success px-2 fw-bold">+</button></form>
        <form method="POST" class="d-inline ms-1 js-pos-cart"><input type="hidden" name="remove_item" value="1"><input type="hidden" name="cart_key" value="<?= htmlspecialchars((string)$key) ?>"><button class="btn btn-sm text-secondary"><i class="bi bi-trash"></i></button></form>
    </div>
</div>
<?php endforeach; endif; ?>
