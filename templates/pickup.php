<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickup Screen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #2c3e50; color: #ecf0f1; font-family: sans-serif; }
        .pickup-header { background: #34495e; padding: 20px; border-bottom: 4px solid #27ae60; margin-bottom: 30px; }
        .order-card { background: #fff; color: #2c3e50; border-radius: 10px; overflow: hidden; height: 100%; box-shadow: 0 10px 20px rgba(0,0,0,0.2); animation: popIn 0.5s ease-out; }
        .card-header { background: #27ae60; color: white; padding: 15px; font-weight: bold; font-size: 1.2rem; display: flex; justify-content: space-between; }
        .card-body { padding: 20px; }
        .btn-collect { width: 100%; padding: 15px; font-size: 1.1rem; font-weight: bold; text-transform: uppercase; border-radius: 0 0 10px 10px; }
        @keyframes popIn { 0% { transform: scale(0.9); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }
    </style>
</head>
<body>

    <div class="pickup-header d-flex justify-content-between align-items-center">
        <h1 class="m-0 fw-bold"><i class="bi bi-bell-fill text-warning me-3"></i> READY FOR PICKUP</h1>
        <div class="fs-4 font-monospace" id="clock">00:00:00</div>
    </div>

    <div class="container-fluid px-4">
        <div class="row g-4" id="orders-container">
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
                                <?php foreach($order['items'] as $item): ?>
                                <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                    <span><?= htmlspecialchars($item['product_name']) ?></span>
                                    <span class="badge bg-secondary rounded-pill"><?= number_format($item['quantity']) ?></span>
                                </li>
                                <?php endforeach; ?>
                            </ul>

                            <form onsubmit="confirmCollection(event, <?= $order['sale_id'] ?>)">
                                <input type="hidden" name="collect_order" value="1">
                                <input type="hidden" name="sale_id" value="<?= $order['sale_id'] ?>">
                                
                                <select name="collected_by" class="form-select mb-3" required>
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
        </div>
    </div>

    <iframe id="receiptFrame" style="display:none;"></iframe>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        setInterval(() => { document.getElementById('clock').innerText = new Date().toLocaleTimeString(); }, 1000);
        
        // Auto-refresh ONLY if we haven't clicked anything recently (simple implementation)
        setTimeout(() => { window.location.reload(); }, 15000);

        function confirmCollection(event, saleId) {
            event.preventDefault();
            const form = event.target;
            const btn = form.querySelector('button');
            const originalText = btn.innerHTML;
            
            btn.disabled = true;
            btn.innerHTML = 'Processing...';

            const formData = new FormData(form);

            fetch('index.php?page=pickup', { method: 'POST', body: formData })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    // Print Receipt
                    const frame = document.getElementById('receiptFrame');
                    frame.src = 'index.php?page=receipt&sale_id=' + saleId;
                    frame.onload = function() { frame.contentWindow.print(); };
                    
                    // Remove Card
                    document.getElementById('order-card-' + saleId).remove();
                } else {
                    alert("Error processing collection");
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(err => {
                console.error(err);
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }
    </script>
</body>
</html>