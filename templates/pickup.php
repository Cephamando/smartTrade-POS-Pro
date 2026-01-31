<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickup Screen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #212529; color: white; }
        .pickup-card {
            border: 4px solid #198754;
            background-color: #000;
            color: #198754;
            transition: transform 0.2s;
        }
        .pickup-card:hover { transform: scale(1.02); }
        .ticket-number { font-size: 5rem; font-weight: bold; line-height: 1; }
        .server-name { font-size: 1.2rem; color: #adb5bd; }
        .blink { animation: blinker 2s linear infinite; }
        @keyframes blinker { 50% { opacity: 0.5; } }
    </style>
</head>
<body class="p-4">

    <div class="d-flex justify-content-between align-items-center mb-5 border-bottom border-secondary pb-3">
        <h1 class="display-4 fw-bold text-white">
            <i class="bi bi-bag-check-fill text-success"></i> READY FOR PICKUP
        </h1>
        <h2 class="font-monospace text-warning" id="clock"><?= date('H:i') ?></h2>
    </div>

    <div class="row g-4">
        <?php if (!empty($readyOrders)): ?>
            <?php foreach ($readyOrders as $order): ?>
            <div class="col-md-4 col-lg-3">
                <div class="card pickup-card h-100 shadow">
                    <div class="card-body text-center d-flex flex-column justify-content-center py-5">
                        <div class="text-white small text-uppercase mb-2">ORDER NUMBER</div>
                        <div class="ticket-number blink">#<?= $order['sale_id'] ?></div>
                        <div class="server-name mt-3"><i class="bi bi-person"></i> <?= htmlspecialchars($order['server']) ?></div>
                    </div>
                    <div class="card-footer bg-success border-0 p-0">
                        <form method="POST" action="index.php?page=pickup" class="d-grid">
                            <input type="hidden" name="collect_order" value="1">
                            <input type="hidden" name="sale_id" value="<?= $order['sale_id'] ?>">
                            <button class="btn btn-success btn-lg fw-bold rounded-0" style="height: 60px;">
                                <i class="bi bi-check-circle-fill"></i> COLLECTED
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center mt-5 opacity-25">
                <i class="bi bi-cup-hot display-1"></i>
                <h2 class="mt-3">No orders waiting</h2>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Update Clock
        setInterval(() => {
            const now = new Date();
            document.getElementById('clock').innerText = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }, 1000);

        // Auto Refresh every 5 seconds to check for new ready food
        setTimeout(() => { location.reload(); }, 5000);
    </script>
</body>
</html>
