<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Kitchen Display System</title>
    <?php $pwaStaffPart = 'head'; require APP_PATH . '/Views/partials/pwa_staff.php'; ?>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <script src="js/app-alerts.js?v=20260905b"></script>
    <style>
        body { background-color: #212529; color: #fff; padding: 20px; }
        .kds-header { display: flex; justify-content: space-between; align-items: center; mb-4; border-bottom: 2px solid #444; padding-bottom: 10px; }
        .ticket-card { background: #fff; color: #000; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 6px rgba(0,0,0,0.3); transition: transform 0.2s; height: 100%; }
        .ticket-header { padding: 10px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .ticket-header.new { background-color: #dc3545; color: white; } /* Red for Pending */
        .ticket-header.working { background-color: #ffc107; color: black; } /* Yellow for Cooking */
        .ticket-body { padding: 0; }
        .list-group-item { border-left: none; border-right: none; font-size: 1.1rem; font-weight: 500; display: flex; justify-content: space-between; align-items: center; }
        .btn-action { width: 100%; border-radius: 0; font-weight: bold; text-transform: uppercase; padding: 15px; }
        .timer-badge { font-family: monospace; font-size: 1rem; background: rgba(0,0,0,0.2); padding: 2px 6px; border-radius: 4px; }
        .qty-badge { background: #000; color: #fff; width: 30px; height: 30px; display: inline-flex; align-items: center; justify-content: center; border-radius: 50%; margin-right: 10px; font-weight: bold; }
    </style>
</head>
<body>
<?php $pwaStaffPart = 'bar'; require APP_PATH . '/Views/partials/pwa_staff.php'; ?>

    <div class="container-fluid">
        <div class="kds-header mb-4">
            <h2 class="fw-bold"><i class="bi bi-fire text-danger"></i> KITCHEN DISPLAY</h2>
            <div id="clock" class="fs-4 fw-bold font-monospace text-warning">--:--:--</div>
        </div>

        <div class="row g-3" id="ordersContainer">
            <div class="col-12 text-center mt-5 text-muted">
                <div class="spinner-border text-light" role="status"></div>
                <p class="mt-2">Connecting to kitchen…</p>
            </div>
        </div>
    </div>

    <script>
        // --- 1. CLOCK ---
        setInterval(() => {
            const now = new Date();
            document.getElementById('clock').innerText = now.toLocaleTimeString();
        }, 1000);

        // --- 2. POLLING & RENDERING ---
        function fetchOrders() {
            fetch('index.php?page=kds&ajax_poll=1')
                .then(response => response.json())
                .then(data => renderTickets(data))
                .catch(err => console.error("KDS Error:", err));
        }

        function renderTickets(orders) {
            const container = document.getElementById('ordersContainer');
            
            if (orders.length === 0) {
                container.innerHTML = '<div class="col-12 text-center mt-5 opacity-50"><i class="bi bi-check-circle display-1 text-success"></i><h3 class="mt-3">All Orders Clear</h3></div>';
                return;
            }

            container.innerHTML = orders.map(order => {
                // Check if any item is cooking to decide header color
                const isCooking = order.items.some(i => i.status === 'cooking');
                const headerClass = isCooking ? 'working' : 'new';
                
                // Calculate elapsed time
                const startTime = new Date(order.time).getTime();
                const now = new Date().getTime();
                const elapsedMinutes = Math.floor((now - startTime) / 60000);

                let itemsHtml = order.items.map(item => {
                    let btnHtml = '';
                    const safeName = escapeHtml(item.name || 'this item');
                    if (item.status === 'pending') {
                        btnHtml = `<button type="button" class="btn btn-sm btn-outline-dark fw-bold js-kds-action" data-item-id="${item.id}" data-status="cooking" data-name="${safeName}">COOK</button>`;
                    } else if (item.status === 'cooking') {
                        btnHtml = `<button type="button" class="btn btn-sm btn-success fw-bold js-kds-action" data-item-id="${item.id}" data-status="ready" data-name="${safeName}">DONE</button>`;
                    }
                    
                    return `
                        <li class="list-group-item">
                            <div class="d-flex align-items-center">
                                <span class="qty-badge">${item.qty}</span>
                                <span>${item.name}</span>
                            </div>
                            ${btnHtml}
                        </li>
                    `;
                }).join('');

                return `
                    <div class="col-12 col-md-6 col-lg-4 col-xl-3">
                        <div class="ticket-card">
                            <div class="ticket-header ${headerClass}">
                                <span>#${order.id} - ${order.waiter}</span>
                                <span class="timer-badge">${elapsedMinutes} min</span>
                            </div>
                            <ul class="list-group list-group-flush ticket-body">
                                ${itemsHtml}
                            </ul>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function escapeHtml(value) {
            return String(value || '').replace(/[&<>"']/g, function (ch) {
                return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
            });
        }

        function updateStatus(itemId, newStatus, itemName) {
            const cooking = newStatus === 'cooking';
            AppAlert.confirm({
                icon: 'question',
                title: cooking ? 'Start cooking?' : 'Mark this item done?',
                text: cooking
                    ? 'Send ' + (itemName || 'this item') + ' to the cook line.'
                    : (itemName || 'This item') + ' will be marked ready for pickup.',
                confirmText: cooking ? 'Cook' : 'Done',
                cancelText: 'Cancel',
                confirmColor: cooking ? '#212529' : '#198754'
            }).then(function (ok) {
                if (!ok) {
                    return;
                }
                const formData = new FormData();
                formData.append('item_id', itemId);
                formData.append('status', newStatus);
                fetch('index.php?page=kds', { method: 'POST', body: formData })
                    .then(() => fetchOrders())
                    .catch(() => AppAlert.notify({ icon: 'error', title: 'Update failed', text: 'Unable to update this ticket. Try again.', confirmText: 'OK' }));
            });
        }

        document.getElementById('ordersContainer').addEventListener('click', function (e) {
            const btn = e.target.closest('.js-kds-action');
            if (!btn) {
                return;
            }
            updateStatus(parseInt(btn.getAttribute('data-item-id'), 10), btn.getAttribute('data-status'), btn.getAttribute('data-name'));
        });

        // Initial Load & Loop
        fetchOrders();
        setInterval(fetchOrders, 5000); // Poll every 5 seconds
    </script>
<script src="pwa/register.js"></script>
</body>
</html>
