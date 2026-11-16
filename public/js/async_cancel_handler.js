/**
 * Intercepts POS Online Web Order cancellations to execute them asynchronously via AJAX
 * without refreshing the page.
 */
document.addEventListener('click', function (e) {
    const cancelBtn = e.target.closest('#onlineTabDetailContainer button[name="cancel_online_order"], #onlineTabDetailContainer form.js-confirm-form button[type="submit"]');
    if (!cancelBtn) return;

    const form = cancelBtn.closest('form');
    if (!form) return;

    // Check if this is a web order view
    const detailContainer = document.getElementById('onlineTabDetailContainer');
    if (!detailContainer) return;

    const txRefInput = detailContainer.querySelector('input[name="tx_ref"]') || form.querySelector('input[name="sale_id"], input[name="order_id"]');
    if (!txRefInput && !form.dataset.saleId) return;

    e.preventDefault();
    e.stopPropagation();

    const identifier = txRefInput ? txRefInput.value : form.dataset.saleId;
    if (!identifier) return;

    cancelBtn.disabled = true;
    cancelBtn.textContent = 'Cancelling...';

    fetch('/api/whatsapp_checkout/cancel_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tx_ref: identifier })
    })
    .then(r => r.json())
    .then(data => {
        if (data && data.status === 'success') {
            // Remove item from sidebar list
            document.querySelectorAll('#onlineTabsModal .list-group-item').forEach(row => {
                if ((row.getAttribute('onclick') || '').includes(identifier)) {
                    row.remove();
                }
            });
            detailContainer.innerHTML = '<p class="text-center text-muted mt-5"><i class="bi bi-x-circle fs-1 text-danger"></i><br>Order successfully cancelled.</p>';
            
            // Refresh counts
            document.querySelectorAll('[data-live-count="web"]').forEach(badge => {
                const n = Math.max(0, (parseInt(badge.textContent, 10) || 0) - 1);
                badge.textContent = String(n);
                badge.style.display = n ? '' : 'none';
            });
        } else {
            alert((data && data.message) || 'Failed to cancel order.');
            cancelBtn.disabled = false;
            cancelBtn.textContent = 'Cancel Order';
        }
    })
    .catch(() => {
        alert('Network error while cancelling order.');
        cancelBtn.disabled = false;
        cancelBtn.textContent = 'Cancel Order';
    });
}, true);
