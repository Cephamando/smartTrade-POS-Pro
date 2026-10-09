<?php if (!empty($fragment)) { require __DIR__ . '/partials/pickup_board.php'; return; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickup Screen</title>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <script src="js/app-alerts.js?v=20260905b"></script>
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
            <?php require __DIR__ . '/partials/pickup_board.php'; ?>
        </div>
    </div>

    <iframe id="receiptFrame" style="display:none;"></iframe>

    <script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
    <script>
        setInterval(() => { document.getElementById('clock').innerText = new Date().toLocaleTimeString(); }, 1000);
        
        function refreshPickupBoard() {
            const active = document.activeElement;
            if (active && active.closest && active.closest('#orders-container') && /^(INPUT|SELECT|TEXTAREA)$/.test(active.tagName)) {
                return;
            }
            fetch('index.php?page=pickup&fragment=1', { credentials: 'same-origin' })
                .then(function (r) { return r.text(); })
                .then(function (html) {
                    const box = document.getElementById('orders-container');
                    if (box && (html.indexOf('order-card-') !== -1 || html.indexOf('No orders ready') !== -1)) {
                        box.innerHTML = html;
                    }
                })
                .catch(function () {});
        }
        setInterval(refreshPickupBoard, 12000);

        function confirmCollection(event, saleId) {
            event.preventDefault();
            const form = event.target;
            const collectedBy = form.querySelector('[name="collected_by"]');
            if (!collectedBy || !collectedBy.value) {
                AppAlert.notify({ icon: 'warning', title: 'Staff required', text: 'Select who collected this order.', confirmText: 'OK' });
                return;
            }

            AppAlert.confirm({
                title: 'Confirm collection?',
                text: 'This order will be marked collected and a receipt will print.',
                confirmText: 'Collect',
                cancelText: 'Cancel',
                icon: 'question',
                confirmColor: '#198754'
            }).then(function (ok) {
                if (!ok) {
                    return;
                }
                const btn = form.querySelector('button');
                const originalText = btn.innerHTML;
                btn.disabled = true;
                btn.innerHTML = 'Processing...';

                fetch('index.php?page=pickup', { method: 'POST', body: new FormData(form) })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        const frame = document.getElementById('receiptFrame');
                        frame.src = 'index.php?page=receipt&sale_id=' + saleId;
                        frame.onload = function() { frame.contentWindow.print(); };
                        document.getElementById('order-card-' + saleId).remove();
                    } else {
                        AppAlert.notify({ icon: 'error', title: 'Collection failed', text: 'Unable to complete collection. Try again.', confirmText: 'OK' });
                        btn.disabled = false;
                        btn.innerHTML = originalText;
                    }
                })
                .catch(err => {
                    console.error(err);
                    AppAlert.notify({ icon: 'error', title: 'Collection failed', text: 'Unable to complete collection. Try again.', confirmText: 'OK' });
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                });
            });
        }
    </script>
</body>
</html>