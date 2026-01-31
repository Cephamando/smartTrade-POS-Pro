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
        <div class="d-flex align-items-center gap-3">
            <h2 class="font-monospace text-warning m-0" id="clock"><?= date('H:i') ?></h2>
            <a href="index.php?page=dashboard" class="btn btn-outline-light btn-lg">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </div>
    </div>

    <div class="row g-4" id="ordersContainer">
        <?php if (!empty($readyOrders)): ?>
            <?php foreach ($readyOrders as $order): ?>
            <div class="col-md-4 col-lg-3 order-card" id="card_<?= $order['sale_id'] ?>">
                <div class="card pickup-card h-100 shadow">
                    <div class="card-body text-center d-flex flex-column justify-content-center pt-4">
                        <div class="text-white small text-uppercase mb-2">ORDER NUMBER</div>
                        <div class="ticket-number blink">#<?= $order['sale_id'] ?></div>
                        <div class="server-name mt-3"><i class="bi bi-person"></i> Server: <?= htmlspecialchars($order['server']) ?></div>
                    </div>
                    
                    <div class="card-footer bg-dark border-0 p-3">
                        <form onsubmit="processCollection(event, <?= $order['sale_id'] ?>)">
                            <div class="form-floating text-dark mb-2">
                                <select class="form-select fw-bold" id="collector_<?= $order['sale_id'] ?>" required onchange="enableBtn(<?= $order['sale_id'] ?>)">
                                    <option value="" selected disabled>Select Collector...</option>
                                    <?php foreach ($waiters as $w): ?>
                                        <option value="<?= htmlspecialchars($w) ?>"><?= htmlspecialchars($w) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Collected By</label>
                            </div>
                            <button type="submit" id="btn_<?= $order['sale_id'] ?>" class="btn btn-success btn-lg w-100 fw-bold" disabled>
                                <i class="bi bi-check-circle-fill"></i> CONFIRM
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

    <div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content bg-dark border-secondary">
                <div class="modal-header border-secondary">
                    <h5 class="modal-title text-white"><i class="bi bi-printer"></i> Receipt Preview</h5>
                    <button type="button" class="btn-close btn-close-white" onclick="location.reload()"></button>
                </div>
                <div class="modal-body p-0" style="height: 500px; background: white;">
                    <iframe id="receiptFrame" src="" style="width:100%; height:100%; border:none;"></iframe>
                </div>
                <div class="modal-footer border-secondary">
                    <button type="button" class="btn btn-secondary" onclick="location.reload()">Close & Next</button>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('receiptFrame').contentWindow.print()">
                        <i class="bi bi-printer"></i> Print Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Clock
        setInterval(() => {
            document.getElementById('clock').innerText = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }, 1000);

        // Auto Refresh (only if no modal is open)
        setInterval(() => { 
            if (!document.getElementById('receiptModal').classList.contains('show')) {
                location.reload(); 
            }
        }, 10000);

        function enableBtn(id) {
            document.getElementById('btn_' + id).disabled = false;
        }

        function processCollection(e, saleId) {
            e.preventDefault();
            const btn = document.getElementById('btn_' + saleId);
            const collector = document.getElementById('collector_' + saleId).value;
            
            btn.disabled = true;
            btn.innerText = 'Processing...';

            let formData = new FormData();
            formData.append('collect_order', '1');
            formData.append('ajax', '1');
            formData.append('sale_id', saleId);
            formData.append('collected_by', collector);

            fetch('index.php?page=pickup', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    // Open Modal
                    const receiptUrl = 'index.php?page=receipt&print_collection=1&sale_id=' + saleId;
                    document.getElementById('receiptFrame').src = receiptUrl;
                    new bootstrap.Modal(document.getElementById('receiptModal')).show();
                    
                    // Remove card from background immediately
                    const card = document.getElementById('card_' + saleId);
                    if(card) card.remove();
                }
            })
            .catch(err => {
                alert('Error processing collection');
                btn.disabled = false;
                btn.innerText = 'CONFIRM';
            });
        }
    </script>
</body>
</html>
