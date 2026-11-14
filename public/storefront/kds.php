<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/WhatsAppCloudService.php';

use SmartTrade\Services\WhatsAppCloudService;

$branchId   = isset($_GET['branch']) ? max(1, (int) $_GET['branch']) : 1;
$storeTitle = WhatsAppCloudService::env('STORE_BRAND_NAME', 'smartTrade Express Store');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($storeTitle) ?> — WhatsApp POS &amp; Kitchen Queue</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #121820; color: #f8f9fa; }
        .kds-navbar { background: #1b2430; border-bottom: 1px solid #2d3846; }
        .kds-card { background: #1e2936; border: 1px solid #2e3d4f; border-radius: 12px; color: #fff; }
        .kds-card.status-NEW { border-left: 6px solid #20c997; }
        .kds-card.status-PREPARING { border-left: 6px solid #ffc107; }
        .kds-card.status-READY_OR_DISPATCHED { border-left: 6px solid #0dcaf0; }
    </style>
</head>
<body>

<nav class="navbar kds-navbar py-3 mb-4">
    <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 fw-bold text-white">👨‍🍳 <?= htmlspecialchars($storeTitle) ?> — WhatsApp Kitchen &amp; Cashier Queue</h5>
            <small class="text-secondary">Branch #<?= $branchId ?> &bull; Auto-polling every 8s &bull; ZRA VSDC Integrated</small>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-success fs-6" id="activeCountBadge">0 Active Paid</span>
            <button type="button" class="btn btn-sm btn-outline-light" onclick="fetchOrders()">🔄 Refresh Now</button>
            <a href="/storefront/index.php?branch=<?= $branchId ?>" target="_blank" class="btn btn-sm btn-success fw-semibold">
                🛒 Open Customer Storefront
            </a>
        </div>
    </div>
</nav>

<main class="container-fluid px-4">
    <div class="row g-3" id="kdsGrid">
        <div class="col-12 text-center py-5 text-secondary">
            Loading active paid WhatsApp orders...
        </div>
    </div>
</main>

<script>
const BRANCH_ID = <?= $branchId ?>;
let knownOrderRefs = new Set();
let isFirstLoad = true;

function playNewOrderChime() {
    try {
        const ctx = new (window.AudioContext || window.webkitAudioContext)();
        const osc = ctx.createOscillator();
        const gain = ctx.createGain();
        osc.type = 'sine';
        osc.frequency.setValueAtTime(880, ctx.currentTime);
        osc.frequency.setValueAtTime(1174.66, ctx.currentTime + 0.15);
        gain.gain.setValueAtTime(0.2, ctx.currentTime);
        gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.5);
        osc.connect(gain);
        gain.connect(ctx.destination);
        osc.start();
        osc.stop(ctx.currentTime + 0.5);
    } catch (e) {}
}

async function fetchOrders() {
    try {
        const res = await fetch(`../api/whatsapp_checkout/pos_order_actions.php?branch_id=${BRANCH_ID}&payment_status=PAID`);
        const data = await res.json();
        if (data.status !== 'success') return;

        const orders = data.orders || [];
        document.getElementById('activeCountBadge').textContent = `${orders.length} Active Paid`;

        let hasNewArrival = false;
        orders.forEach(o => {
            if (!knownOrderRefs.has(o.tx_ref)) {
                knownOrderRefs.add(o.tx_ref);
                if (!isFirstLoad) {
                    hasNewArrival = true;
                }
            }
        });
        isFirstLoad = false;

        if (hasNewArrival) {
            playNewOrderChime();
        }

        renderOrders(orders);
    } catch (err) {
        console.error('Failed to poll KDS orders:', err);
    }
}

function renderOrders(orders) {
    const grid = document.getElementById('kdsGrid');
    if (orders.length === 0) {
        grid.innerHTML = `
            <div class="col-12 text-center py-5 text-secondary">
                <h5>No active paid WhatsApp orders in the queue</h5>
                <p class="small mb-0">New paid Mobile Money &amp; Card orders will appear here automatically.</p>
            </div>
        `;
        return;
    }

    grid.innerHTML = orders.map(order => {
        const itemsHtml = (order.items || []).map(i => `
            <li class="d-flex justify-content-between py-1 border-bottom border-secondary border-opacity-25 small">
                <span><strong>${i.qty}x</strong> ${i.name} <code class="text-info">${i.sku || ''}</code></span>
                <span>ZMW ${parseFloat(i.line_total || 0).toFixed(2)}</span>
            </li>
        `).join('');

        const badgeMap = {
            'NEW': '<span class="badge bg-success">NEW PAID</span>',
            'PREPARING': '<span class="badge bg-warning text-dark">PREPARING</span>',
            'READY_OR_DISPATCHED': '<span class="badge bg-info text-dark">READY / DISPATCHED</span>'
        };

        const zraRcpt = order.zra_rcpt_no || '';
        const printUrl = `../verify_receipt.php?rcpt=${encodeURIComponent(zraRcpt)}&tx_ref=${encodeURIComponent(order.tx_ref)}&print=1`;

        return `
            <div class="col-12 col-md-6 col-xl-4">
                <div class="card kds-card status-${order.fulfillment_status} h-100 p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="fw-bold text-info">${order.tx_ref}</span>
                                <div class="small text-secondary">${order.created_at}</div>
                            </div>
                            ${badgeMap[order.fulfillment_status] || `<span class="badge bg-secondary">${order.fulfillment_status}</span>`}
                        </div>

                        <div class="mb-2 p-2 rounded bg-dark bg-opacity-50 small">
                            <div><strong>👤 ${order.customer_name}</strong> (+${order.customer_phone})</div>
                            <div><strong>🚚 ${order.delivery_type}</strong> ${order.delivery_address ? '— ' + order.delivery_address : ''}</div>
                            <div class="text-success fw-semibold mt-1">🧾 ZRA: ${zraRcpt || 'Pending'}</div>
                        </div>

                        <ul class="list-unstyled mb-3">
                            ${itemsHtml}
                        </ul>
                    </div>

                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="small text-secondary">Total Paid (Flutterwave):</span>
                            <strong class="fs-5 text-success">ZMW ${parseFloat(order.total_zmw).toFixed(2)}</strong>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="${printUrl}" target="_blank" class="btn btn-sm btn-light fw-bold">
                                🖨️ 80mm Slip
                            </a>
                            ${order.fulfillment_status === 'NEW' ? `
                                <button class="btn btn-sm btn-warning fw-bold flex-grow-1" onclick="updateStatus('${order.tx_ref}', 'PREPARING')">
                                    👨‍🍳 Start Preparing
                                </button>
                            ` : ''}
                            ${order.fulfillment_status === 'PREPARING' ? `
                                <button class="btn btn-sm btn-info fw-bold flex-grow-1" onclick="updateStatus('${order.tx_ref}', 'READY_OR_DISPATCHED')">
                                    🛵 Ready / Dispatch
                                </button>
                            ` : ''}
                            <button class="btn btn-sm btn-success fw-bold flex-grow-1" onclick="updateStatus('${order.tx_ref}', 'COMPLETED')">
                                ✅ Complete
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

async function updateStatus(txRef, newStatus) {
    try {
        const res = await fetch('../api/whatsapp_checkout/pos_order_actions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                tx_ref: txRef,
                fulfillment_status: newStatus
            })
        });
        const data = await res.json();
        if (data.status === 'success') {
            Swal.fire({
                toast: true,
                position: 'top-end',
                icon: 'success',
                title: `Order ${txRef} -> ${newStatus}`,
                text: data.whatsapp_notified ? 'Customer notified on WhatsApp' : 'Status updated in POS queue',
                showConfirmButton: false,
                timer: 2500
            });
            fetchOrders();
        } else {
            throw new Error(data.message || 'Status update failed');
        }
    } catch (err) {
        Swal.fire('Error', err.message, 'error');
    }
}

fetchOrders();
setInterval(fetchOrders, 8000);
</script>
</body>
</html>
