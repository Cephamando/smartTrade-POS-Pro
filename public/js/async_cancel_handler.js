/**
 * Fully robust AJAX cancellation handler for POS Online / Web Orders
 */
document.addEventListener('click', function (e) {
    const trigger = e.target.closest('button, input[type="submit"]');
    if (!trigger) return;

    const text = (trigger.textContent || trigger.value || '').toLowerCase();
    if (!text.includes('cancel')) return;

    const form = trigger.closest('form');
    const detailContainer = document.getElementById('onlineTabDetailContainer');
    if (!detailContainer) return;

    e.preventDefault();
    e.stopPropagation();

    // Extract tx_ref or sale identifier from the active details pane or form
    let identifier = '';
    const refEl = detailContainer.querySelector('.badge, strong, .small');
    if (refEl) {
        const m = detailContainer.textContent.match(/ST-FLW-[A-Z0-9-]+/i);
        if (m) identifier = m[0];
    }
    if (!identifier && form) {
        const input = form.querySelector('input[name="sale_id"], input[name="order_id"], input[name="tx_ref"]');
        if (input) identifier = input.value;
    }
    if (!identifier) {
        // Fallback: grab first active item reference in the list
        const activeItem = document.querySelector('#onlineTabsModal .list-group-item.active, #onlineTabsModal .list-group-item');
        if (activeItem) {
            const m = activeItem.textContent.match(/ST-FLW-[A-Z0-9-]+/i);
            if (m) identifier = m[0];
        }
    }

    if (!identifier) {
        alert('Could not determine order reference for cancellation.');
        return;
    }

    trigger.disabled = true;
    trigger.textContent = 'Cancelling...';

    fetch('/api/whatsapp_checkout/cancel_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tx_ref: identifier })
    })
    .then(r => r.json())
    .then(data => {
        if (data && data.status === 'success') {
            document.querySelectorAll('#onlineTabsModal .list-group-item').forEach(row => {
                if (row.textContent.includes(identifier)) {
                    row.classList.remove('bg-success-subtle', 'bg-warning-subtle');
                    row.classList.add('bg-danger-subtle');
                    const badge = row.querySelector('.badge');
                    if (badge) {
                        badge.className = 'badge bg-danger';
                        badge.textContent = 'CANCELLED BY TILL';
                    }
                }
            });
            detailContainer.innerHTML = '<p class="text-center text-muted mt-5"><i class="bi bi-x-circle fs-1 text-danger"></i><br>Order successfully cancelled.</p>';
            
            // Decrement badge counts
            document.querySelectorAll('[data-live-count="web"]').forEach(badge => {
                const n = Math.max(0, (parseInt(badge.textContent, 10) || 0) - 1);
                badge.textContent = String(n);
                badge.style.display = n ? '' : 'none';
            });
        } else {
            alert((data && data.message) || 'Failed to cancel order.');
            trigger.disabled = false;
            trigger.textContent = 'Cancel Order';
        }
    })
    .catch(() => {
        alert('Network error while cancelling order.');
        trigger.disabled = false;
        trigger.textContent = 'Cancel Order';
    });
}, true);
