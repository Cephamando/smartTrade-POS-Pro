<?php if (empty($readyOrders)): ?>
    <div class="col-12 text-center mt-5 text-muted opacity-50">
        <i class="bi bi-cup-hot display-1"></i>
        <h2 class="mt-4">No orders ready</h2>
    </div>
<?php else: ?>
    <?php foreach ($readyOrders as $order): ?>
    <div class="col-12 col-md-6 col-lg-4 col-xl-3" id="order-card-<?= $order['sale_id'] ?>">
        <div class="order-card">
            <div class="card-header">
                <span>Order #<?= $order['sale_id'] ?></span>
                <span><i class="bi bi-clock"></i> <?= date('H:i', strtotime($order['created_at'])) ?></span>
            </div>
            <div class="card-body">
                <div class="mb-3 border-bottom pb-2">
                    <small class="text-muted d-block">SERVER</small>
                    <strong><?= htmlspecialchars($order['server'] ?? 'Unknown') ?></strong>
                </div>
                <ul class="list-group list-group-flush mb-3">
                    <?php foreach ($order['items'] as $item): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                        <span><?= htmlspecialchars($item['product_name']) ?></span>
                        <span class="badge bg-secondary rounded-pill"><?= number_format($item['quantity']) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <form onsubmit="confirmCollection(event, <?= $order['sale_id'] ?>)">
                    <input type="hidden" name="collect_order" value="1">
                    <input type="hidden" name="sale_id" value="<?= $order['sale_id'] ?>">
                    <select name="collected_by" class="form-select mb-3 no-search" required>
                        <option value="" selected disabled>Collected By...</option>
                        <?php foreach ($waiters as $w): ?>
                            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-success btn-collect">
                        <i class="bi bi-check-lg"></i> COLLECT
                    </button>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
