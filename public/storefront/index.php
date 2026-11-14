<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/WhatsAppCloudService.php';

use SmartTrade\Services\WhatsAppCloudService;

$branchId         = isset($_GET['branch']) ? max(1, (int) $_GET['branch']) : 1;
$merchantWhatsApp = WhatsAppCloudService::env('MERCHANT_WHATSAPP_NUMBER', '260970000000');
$storeTitle       = WhatsAppCloudService::env('STORE_BRAND_NAME', 'smartTrade Express Store');

$products  = [];
$flwConfig = WhatsAppCloudService::getFlwConfig();

try {
    $pdo       = WhatsAppCloudService::getDb();
    $flwConfig = WhatsAppCloudService::getFlwConfig($pdo);

    $stmt = $pdo->prepare("SELECT id, sku, name, selling_price, stock_qty FROM products WHERE branch_id = :bid AND is_active = 1 AND stock_qty > 0 ORDER BY name ASC LIMIT 100");
    $stmt->execute([':bid' => $branchId]);
    $products = $stmt->fetchAll();
} catch ( Exception $e ) {
    $products = [
        ['id' => 1, 'sku' => 'GRL-001', 'name' => 'Full Flame-Grilled Chicken & Chips', 'selling_price' => 185.00, 'stock_qty' => 20],
        ['id' => 2, 'sku' => 'GRL-002', 'name' => 'T-Bone Steak (350g) + Nshima & Relish', 'selling_price' => 210.00, 'stock_qty' => 15],
        ['id' => 3, 'sku' => 'BVG-004', 'name' => 'Fresh Fruit Smoothie (500ml)', 'selling_price' => 55.00, 'stock_qty' => 40]
    ];
}

$flwPublicKey    = $flwConfig['public_key'];
$flwSubaccountId = $flwConfig['subaccount_id'];
$websiteEnabled  = (bool) $flwConfig['website_enabled'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($storeTitle) ?> — WhatsApp Express Order</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://checkout.flutterwave.com/v3.js"></script>
    <style>
        body { background-color: #f4f6f8; padding-bottom: 90px; }
        .wa-header { background: linear-gradient(135deg, #075E54, #128C7E); color: #fff; }
        .product-card { border: 0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); }
        .sticky-cart-bar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1030; background: #fff; box-shadow: 0 -4px 12px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<header class="wa-header py-3 mb-3 shadow-sm">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0 fw-bold"><?= htmlspecialchars($storeTitle) ?></h5>
            <small class="opacity-75">Instant Mobile Money Checkout &bull; ZRA Smart Invoice</small>
        </div>
        <span class="badge bg-light text-dark">Branch #<?= $branchId ?></span>
    </div>
</header>

<main class="container">
    <?php if ( !$websiteEnabled ): ?>
        <div class="alert alert-warning shadow-sm">
            <strong>Online Payments Paused:</strong> Online Flutterwave checkout is currently turned off in Office settings.
        </div>
    <?php endif; ?>

    <div class="row g-3" id="catalogGrid">
        <?php foreach ( $products as $p ): ?>
            <div class="col-12 col-md-6 col-lg-4">
                <div class="card product-card h-100 p-3 d-flex flex-column justify-content-between">
                    <div>
                        <span class="badge bg-secondary-subtle text-secondary mb-1"><?= htmlspecialchars((string) $p['sku']) ?></span>
                        <h6 class="fw-bold mb-1"><?= htmlspecialchars((string) $p['name']) ?></h6>
                        <p class="text-success fw-bold fs-5 mb-2">ZMW <?= number_format((float) $p['selling_price'], 2) ?></p>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2">
                        <small class="text-muted"><?= (int) $p['stock_qty'] ?> in stock</small>
                        <button type="button"
                                class="btn btn-sm btn-success px-3 fw-semibold"
                                onclick="addToCart(<?= (int) $p['id'] ?>, '<?= htmlspecialchars(addslashes((string) $p['name'])) ?>', <?= (float) $p['selling_price'] ?>)">
                            + Add
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<div class="sticky-cart-bar p-3">
    <div class="container d-flex justify-content-between align-items-center">
        <div>
            <span class="text-muted small d-block">Your Cart (<span id="cartCount">0</span> items)</span>
            <strong class="fs-5 text-dark">ZMW <span id="cartTotal">0.00</span></strong>
        </div>
        <button type="button" class="btn btn-success fw-bold px-4" data-bs-toggle="modal" data-bs-target="#checkoutModal" id="openCheckoutBtn" disabled>
            Checkout &amp; Pay
        </button>
    </div>
</div>

<div class="modal fade" id="checkoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header wa-header">
                <h6 class="modal-title fw-bold">Complete WhatsApp Order</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="list-group list-group-flush mb-3" id="cartItemList"></ul>

                <div class="mb-2">
                    <label class="form-label small fw-bold">Your Full Name</label>
                    <input type="text" id="custName" class="form-control" placeholder="e.g., Mutale Banda" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Airtel / MTN / Zamtel Phone Number</label>
                    <input type="tel" id="custPhone" class="form-control" placeholder="e.g., 0977123456" required>
                </div>
                <div class="mb-2">
                    <label class="form-label small fw-bold">Fulfillment Option</label>
                    <select id="deliveryType" class="form-select" onchange="renderCart()">
                        <option value="DELIVERY">Delivery (+ZMW 35.00)</option>
                        <option value="PICKUP">In-Store Pickup (Free)</option>
                    </select>
                </div>
                <div class="mb-3" id="addressGroup">
                    <label class="form-label small fw-bold">Delivery Address / Area</label>
                    <input type="text" id="deliveryAddress" class="form-control" placeholder="Street name, plot number, or landmark">
                </div>

                <div class="alert alert-light border small mb-0">
                    <div class="d-flex justify-content-between">
                        <span>Subtotal:</span>
                        <strong>ZMW <span id="modalSubtotal">0.00</span></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Delivery Fee:</span>
                        <strong>ZMW <span id="modalDelivery">35.00</span></strong>
                    </div>
                    <hr class="my-1">
                    <div class="d-flex justify-content-between fs-6">
                        <span class="fw-bold">Total to Pay:</span>
                        <strong class="text-success">ZMW <span id="modalGrandTotal">0.00</span></strong>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success w-100 fw-bold py-2" id="payNowBtn" onclick="startFlutterwaveCheckout()" <?= !$websiteEnabled ? 'disabled' : '' ?>>
                    Pay with Mobile Money / Card
                </button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const BRANCH_ID = <?= $branchId ?>;
const FLW_PUBLIC_KEY = <?= json_encode($flwPublicKey) ?>;
const FLW_SUBACCOUNT_ID = <?= json_encode($flwSubaccountId) ?>;
const WEBSITE_ENABLED = <?= json_encode($websiteEnabled) ?>;
const MERCHANT_WA = <?= json_encode($merchantWhatsApp) ?>;
const STORE_TITLE = <?= json_encode($storeTitle) ?>;

let cart = {};

function addToCart(id, name, price) {
    if (!cart[id]) {
        cart[id] = { product_id: id, name: name, price: parseFloat(price), qty: 0 };
    }
    cart[id].qty += 1;
    renderCart();
}

function updateQty(id, delta) {
    if (!cart[id]) return;
    cart[id].qty += delta;
    if (cart[id].qty <= 0) {
        delete cart[id];
    }
    renderCart();
}

function renderCart() {
    const items = Object.values(cart);
    let count = 0;
    let subtotal = 0;

    const listEl = document.getElementById('cartItemList');
    listEl.innerHTML = '';

    items.forEach(item => {
        count += item.qty;
        const lineTotal = item.qty * item.price;
        subtotal += lineTotal;

        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center px-0';
        li.innerHTML = `
            <div>
                <div class="fw-semibold small">${item.name}</div>
                <small class="text-muted">ZMW ${item.price.toFixed(2)} x ${item.qty}</small>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="btn btn-sm btn-outline-secondary py-0 px-2" onclick="updateQty(${item.product_id}, -1)">-</button>
                <span class="fw-bold small">${item.qty}</span>
                <button class="btn btn-sm btn-outline-success py-0 px-2" onclick="updateQty(${item.product_id}, 1)">+</button>
            </div>
        `;
        listEl.appendChild(li);
    });

    const deliveryType = document.getElementById('deliveryType').value;
    const deliveryFee = (deliveryType === 'DELIVERY' && count > 0) ? 35.00 : 0.00;
    document.getElementById('addressGroup').style.display = (deliveryType === 'DELIVERY') ? 'block' : 'none';

    const grandTotal = subtotal + deliveryFee;

    document.getElementById('cartCount').textContent = count;
    document.getElementById('cartTotal').textContent = subtotal.toFixed(2);
    document.getElementById('openCheckoutBtn').disabled = (count === 0 || !WEBSITE_ENABLED);

    document.getElementById('modalSubtotal').textContent = subtotal.toFixed(2);
    document.getElementById('modalDelivery').textContent = deliveryFee.toFixed(2);
    document.getElementById('modalGrandTotal').textContent = grandTotal.toFixed(2);
}

async function startFlutterwaveCheckout() {
    if (!WEBSITE_ENABLED) {
        alert('Online Flutterwave payments are currently disabled in Office settings.');
        return;
    }

    const customerName = document.getElementById('custName').value.trim();
    const customerPhone = document.getElementById('custPhone').value.trim();
    const deliveryType = document.getElementById('deliveryType').value;
    const deliveryAddress = document.getElementById('deliveryAddress').value.trim();
    const items = Object.values(cart);

    if (!customerName || !customerPhone || items.length === 0) {
        alert('Please enter your name and Mobile Money phone number.');
        return;
    }

    const btn = document.getElementById('payNowBtn');
    btn.disabled = true;
    btn.textContent = 'Securing Order...';

    try {
        const res = await fetch('../api/whatsapp_checkout/init_order.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                branch_id: BRANCH_ID,
                customer_name: customerName,
                customer_phone: customerPhone,
                delivery_type: deliveryType,
                delivery_address: deliveryAddress,
                items: items
            })
        });

        const orderData = await res.json();
        if (orderData.status !== 'success') {
            throw new Error(orderData.message || 'Could not initialize order.');
        }

        const flwPayload = {
            public_key: FLW_PUBLIC_KEY,
            tx_ref: orderData.tx_ref,
            amount: orderData.amount_zmw,
            currency: 'ZMW',
            payment_options: 'mobilemoneyzambia, card',
            customer: {
                email: orderData.customer_phone + '@whatsapp.odeliazm.com',
                phone_number: orderData.customer_phone,
                name: orderData.customer_name
            },
            customizations: {
                title: STORE_TITLE,
                description: 'WhatsApp Express Order #' + orderData.tx_ref
            },
            callback: function (payment) {
                let msgLines = [
                    `*NEW PAID ORDER (${orderData.tx_ref})*`,
                    `Customer: ${orderData.customer_name}`,
                    `Phone: ${orderData.customer_phone}`,
                    `Fulfillment: ${deliveryType}`,
                ];
                if (deliveryType === 'DELIVERY' && deliveryAddress) {
                    msgLines.push(`Address: ${deliveryAddress}`);
                }
                msgLines.push(`---`);
                orderData.verified_items.forEach(i => {
                    msgLines.push(`${i.qty}x ${i.name} - ZMW ${i.line_total.toFixed(2)}`);
                });
                msgLines.push(`---`);
                msgLines.push(`*Total Paid: ZMW ${orderData.amount_zmw.toFixed(2)}*`);
                msgLines.push(`Flutterwave Ref: #${payment.transaction_id}`);

                const waUrl = `https://wa.me/${MERCHANT_WA}?text=${encodeURIComponent(msgLines.join('\n'))}`;
                window.location.href = waUrl;
            },
            onclose: function() {
                btn.disabled = false;
                btn.textContent = 'Pay with Mobile Money / Card';
            }
        };

        // Automatically attach Subaccount ID (RS_...) configured in Office -> Flutterwave settings
        if (FLW_SUBACCOUNT_ID && FLW_SUBACCOUNT_ID.startsWith('RS_')) {
            flwPayload.subaccounts = [{ id: FLW_SUBACCOUNT_ID }];
        }

        FlutterwaveCheckout(flwPayload);

    } catch (err) {
        alert(err.message);
        btn.disabled = false;
        btn.textContent = 'Pay with Mobile Money / Card';
    }
}
</script>
</body>
</html>
