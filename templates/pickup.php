<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Pickup Screen</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #2c3e50; color: #ecf0f1; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .pickup-header { background: #34495e; padding: 20px; border-bottom: 4px solid #27ae60; margin-bottom: 30px; box-shadow: 0 4px 6px rgba(0,0,0,0.3); }
        .order-card { background: #fff; color: #2c3e50; border-radius: 10px; overflow: hidden; height: 100%; box-shadow: 0 10px 20px rgba(0,0,0,0.2); transition: transform 0.3s; animation: popIn 0.5s ease-out; }
        .order-card:hover { transform: translateY(-5px); }
        .card-header { background: #27ae60; color: white; padding: 15px; font-weight: bold; font-size: 1.2rem; display: flex; justify-content: space-between; align-items: center; }
        .card-body { padding: 20px; }
        .waiter-name { font-size: 0.9rem; text-transform: uppercase; letter-spacing: 1px; color: #7f8c8d; margin-bottom: 15px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
        .time-badge { background: rgba(0,0,0,0.2); padding: 5px 10px; border-radius: 20px; font-size: 0.9rem; }
        .btn-collect { width: 100%; padding: 15px; font-size: 1.1rem; font-weight: bold; text-transform: uppercase; border-radius: 0 0 10px 10px; }
        
        @keyframes popIn { 0% { opacity: 0; transform: scale(0.9); } 100% { opacity: 1; transform: scale(1); } }
        .new-order { animation: pulseGreen 2s infinite; }
        @keyframes pulseGreen { 0% { box-shadow: 0 0 0 0 rgba(39, 174, 96, 0.7); } 70% { box-shadow: 0 0 0 15px rgba(39, 174, 96, 0); } 100% { box-shadow: 0 0 0 0 rgba(39, 174, 96, 0); } }
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
                    <h2 class="mt-4">No orders ready for pickup</h2>
                </div>
            <?php else: ?>
                <?php foreach ($readyOrders as $order): ?>
                <div class="col-12 col-md-6 col-lg-4 col-xl-3" id="order-card-<?= $order['sale_id'] ?>">
                    <div class="order-card new-order">
                        <div class="card-header">
                            <span>Order #<?= $order['sale_id'] ?></span>
                            <span class="time-badge"><i class="bi bi-clock"></i> <?= date('H:i', strtotime($order['created_at'])) ?></span>
                        </div>
                        <div class="card-body">
                            <div class="waiter-name"><i class="bi bi-person-badge"></i> Server: <?= htmlspecialchars($order['server'] ?? 'Unknown') ?></div>
                            
                            <form onsubmit="confirmCollection(event, <?= $order['sale_id'] ?>)">
                                <input type="hidden" name="collect_order" value="1">
                                <input type="hidden" name="sale_id" value="<?= $order['sale_id'] ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label small fw-bold text-muted">COLLECTED BY</label>
                                    <select name="collected_by" class="form-select form-select-lg fw-bold" required>
                                        <option value="" selected disabled>Select Staff...</option>
                                        <?php foreach ($waiters as $w): ?>
                                            <option value="<?= $w['id'] ?>"><?= htmlspecialchars($w['full_name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <button type="submit" class="btn btn-success btn-collect shadow-sm">
                                    <i class="bi bi-check-lg me-2"></i> CONFIRM PICKUP
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="modal fade text-dark" id="receiptModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content h-100">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-receipt"></i> Pickup Receipt</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="height: 80vh; background: #525659;">
                    <iframe id="receiptFrame" src="" style="width:100%; height:100%; border:none;"></iframe>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary fw-bold" onclick="document.getElementById('receiptFrame').contentWindow.print()"><i class="bi bi-printer"></i> Print</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        setInterval(() => { document.getElementById('clock').innerText = new Date().toLocaleTimeString(); }, 1000);

        // Initialize Modal
        let receiptModal;
        document.addEventListener('DOMContentLoaded', () => {
            receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
        });

        function confirmCollection(event, saleId) {
            event.preventDefault();
            const form = event.target;
            const btn = form.querySelector('button');
            const originalText = btn.innerHTML;
            const formData = new FormData(form);

            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Processing...';

            fetch('index.php?page=pickup', { method: 'POST', body: formData })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    // 1. Remove card visually
                    const card = document.getElementById('order-card-' + saleId);
                    if (card) {
                        card.style.transition = "all 0.5s ease";
                        card.style.opacity = "0";
                        card.style.transform = "scale(0.8)";
                        setTimeout(() => card.remove(), 500);
                    }
                    
                    // 2. Open Receipt Modal with sale_id from response
                    const frame = document.getElementById('receiptFrame');
                    // Mode=double implies printing client + kitchen copy, usually helpful for reconciliation
                    frame.src = 'index.php?page=receipt&sale_id=' + data.sale_id + '&mode=double';
                    receiptModal.show();

                } else {
                    alert("Error: " + (data.message || "Unknown error"));
                    btn.disabled = false;
                    btn.innerHTML = originalText;
                }
            })
            .catch(err => {
                console.error(err);
                alert("Network Error");
                btn.disabled = false;
                btn.innerHTML = originalText;
            });
        }
        
        // Reload every 15s to fetch new orders, but only if modal is NOT open
        setInterval(() => {
            const modalEl = document.getElementById('receiptModal');
            // Check if modal is visible
            if (!modalEl.classList.contains('show')) {
                window.location.reload();
            }
        }, 15000); 
    </script>
</body>
</html>
