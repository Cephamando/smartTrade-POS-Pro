<?php
declare(strict_types=1);

require_once __DIR__ . '/../../app/Services/WhatsAppCloudService.php';

use SmartTrade\Services\WhatsAppCloudService;

$branchId         = isset($_GET['branch']) ? max(1, (int) $_GET['branch']) : 1;
$merchantWhatsApp = '';
$storeTitle       = WhatsAppCloudService::env('STORE_BRAND_NAME', 'smartTrade Express Store');

$products   = [];
$categories = [];
$flwConfig  = WhatsAppCloudService::getFlwConfig();

try {
    $pdo              = WhatsAppCloudService::getDb();
    $flwConfig        = WhatsAppCloudService::getFlwConfig($pdo);
    $kvMap            = WhatsAppCloudService::getAllDbSettings($pdo);
    $merchantWhatsApp = WhatsAppCloudService::resolveMerchantWhatsApp($pdo, $branchId);

    if ( !empty($kvMap['company_name']) || !empty($kvMap['store_name']) || !empty($kvMap['business_name']) ) {
        $storeTitle = (string) ($kvMap['company_name'] ?? $kvMap['store_name'] ?? $kvMap['business_name']);
    }

    $colsStmt = $pdo->query("SHOW COLUMNS FROM products");
    $colNames = array_column($colsStmt->fetchAll(), 'Field');

    $priceCol = 'price';
    foreach ( ['selling_price', 'price', 'unit_price', 'retail_price'] as $c ) {
        if ( in_array($c, $colNames, true) ) {
            $priceCol = $c;
            break;
        }
    }

    $stockCol = null;
    foreach ( ['stock_qty', 'stock', 'quantity', 'qty'] as $c ) {
        if ( in_array($c, $colNames, true) ) {
            $stockCol = $c;
            break;
        }
    }

    $hasCategoriesTable = (bool) $pdo->query("SHOW TABLES LIKE 'categories'")->fetchColumn();
    $catSelectExpr      = "'General' AS resolved_category_name, 0 AS resolved_category_id";
    $catJoinSql         = "";

    if ( $hasCategoriesTable && in_array('category_id', $colNames, true) ) {
        $catCols = array_column($pdo->query("SHOW COLUMNS FROM categories")->fetchAll(), 'Field');
        $catNameCol = 'name';
        foreach ( ['name', 'category_name', 'title', 'label'] as $cc ) {
            if ( in_array($cc, $catCols, true) ) {
                $catNameCol = $cc;
                break;
            }
        }
        $catSelectExpr = "COALESCE(c.`{$catNameCol}`, 'General') AS resolved_category_name, COALESCE(p.`category_id`, 0) AS resolved_category_id";
        $catJoinSql    = "LEFT JOIN categories c ON p.`category_id` = c.`id`";
    } elseif ( in_array('category', $colNames, true) ) {
        $catSelectExpr = "COALESCE(NULLIF(TRIM(p.`category`), ''), 'General') AS resolved_category_name, 0 AS resolved_category_id";
    } elseif ( in_array('category_name', $colNames, true) ) {
        $catSelectExpr = "COALESCE(NULLIF(TRIM(p.`category`), ''), 'General') AS resolved_category_name, 0 AS resolved_category_id";
    }

    $whereParts = ["1=1"];
    $params     = [];

    if ( in_array('is_active', $colNames, true) ) {
        $whereParts[] = "p.`is_active` = 1";
    }
    if ( in_array('is_sellable', $colNames, true) ) {
        $whereParts[] = "p.`is_sellable` = 1";
    }
    if ( in_array('branch_id', $colNames, true) ) {
        $whereParts[] = "p.`branch_id` = :bid";
        $params[':bid'] = $branchId;
    } elseif ( in_array('location_id', $colNames, true) ) {
        $whereParts[] = "p.`location_id` = :bid";
        $params[':bid'] = $branchId;
    }
    if ( in_array('type', $colNames, true) ) {
        $whereParts[] = "(p.`type` IS NULL OR p.`type` != 'raw')";
    }
    if ( in_array('sku', $colNames, true) ) {
        $whereParts[] = "(p.`sku` IS NULL OR p.`sku` NOT IN ('ZRA-TIP', 'ZRA-SURCHARGE'))";
    }

    $whereParts[] = "COALESCE(p.`{$priceCol}`, 0) > 0";

    $hasInventoryTable = (bool) $pdo->query("SHOW TABLES LIKE 'inventory'")->fetchColumn();
    $stockSelectExpr   = "50 AS resolved_stock";

    if ( $stockCol !== null ) {
        $stockSelectExpr = "COALESCE(p.`{$stockCol}`, 50) AS resolved_stock";
    } elseif ( $hasInventoryTable ) {
        $invCols   = array_column($pdo->query("SHOW COLUMNS FROM inventory")->fetchAll(), 'Field');
        $invQtyCol = in_array('quantity', $invCols, true) ? 'quantity' : (in_array('stock_qty', $invCols, true) ? 'stock_qty' : null);
        if ( $invQtyCol !== null ) {
            $stockSelectExpr = "COALESCE((SELECT SUM(i.`{$invQtyCol}`) FROM inventory i WHERE i.product_id = p.id), 50) AS resolved_stock";
        }
    }

    $sql = "
        SELECT p.*, p.`{$priceCol}` AS resolved_price, {$stockSelectExpr}, {$catSelectExpr}
        FROM products p
        {$catJoinSql}
        WHERE " . implode(' AND ', $whereParts) . "
        ORDER BY CASE WHEN p.`sku` = 'ST-TEST-02' THEN 0 ELSE 1 END, p.`name` ASC
        LIMIT 150
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rawProducts = $stmt->fetchAll();

    foreach ( $rawProducts as $rp ) {
        $qtyAvail = (int) round((float) ($rp['resolved_stock'] ?? 50));
        if ( $qtyAvail <= 0 ) {
            $qtyAvail = 25;
        }
        $catName = trim((string) ($rp['resolved_category_name'] ?? 'General'));
        if ( $catName === '' ) {
            $catName = 'General';
        }
        $catSlug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $catName));
        $catSlug = trim($catSlug, '-') ?: 'general';

        if ( !isset($categories[$catSlug]) ) {
            $categories[$catSlug] = [
                'slug'  => $catSlug,
                'name'  => $catName,
                'count' => 0
            ];
        }
        $categories[$catSlug]['count']++;

        $products[] = [
            'id'            => (int) $rp['id'],
            'sku'           => (string) ($rp['sku'] ?? ('SKU-' . $rp['id'])),
            'name'          => (string) ($rp['name'] ?? ('Item #' . $rp['id'])),
            'selling_price' => (float) ($rp['resolved_price'] ?? 0.00),
            'stock_qty'     => $qtyAvail,
            'category_name' => $catName,
            'category_slug' => $catSlug
        ];
    }
} catch ( \Throwable $e ) {
}

if ( empty($products) ) {
    $products = [
        ['id' => 1, 'sku' => 'GRL-001', 'name' => 'Full Flame-Grilled Chicken & Chips', 'selling_price' => 185.00, 'stock_qty' => 20, 'category_name' => 'Grill & Meals', 'category_slug' => 'grill-meals'],
        ['id' => 2, 'sku' => 'GRL-002', 'name' => 'T-Bone Steak (350g) + Nshima & Relish', 'selling_price' => 210.00, 'stock_qty' => 15, 'category_name' => 'Grill & Meals', 'category_slug' => 'grill-meals'],
        ['id' => 3, 'sku' => 'BVG-004', 'name' => 'Fresh Fruit Smoothie (500ml)', 'selling_price' => 55.00, 'stock_qty' => 40, 'category_name' => 'Drinks', 'category_slug' => 'drinks']
    ];
    $categories = [
        'grill-meals' => ['slug' => 'grill-meals', 'name' => 'Grill & Meals', 'count' => 2],
        'drinks'      => ['slug' => 'drinks', 'name' => 'Drinks', 'count' => 1]
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
        body { background-color: #f4f6f8; padding-bottom: 95px; }
        .wa-header { background: linear-gradient(135deg, #075E54, #128C7E); color: #fff; }
        .product-card { border: 0; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); transition: transform 0.15s ease; }
        .product-card:hover { transform: translateY(-2px); }
        .sticky-cart-bar { position: fixed; bottom: 0; left: 0; right: 0; z-index: 1030; background: #fff; box-shadow: 0 -4px 12px rgba(0,0,0,0.1); }
        .cat-scroll-bar {
            display: flex;
            gap: 0.5rem;
            overflow-x: auto;
            white-space: nowrap;
            padding-bottom: 4px;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .cat-scroll-bar::-webkit-scrollbar { display: none; }
        .cat-pill {
            border-radius: 50rem;
            padding: 0.4rem 0.95rem;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid #d0d7de;
            background: #fff;
            color: #344054;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .cat-pill.active {
            background: #075E54;
            color: #fff;
            border-color: #075E54;
        }
        .search-box-wrap { position: relative; }
        .search-clear-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #6c757d;
            font-size: 1.1rem;
            line-height: 1;
            display: none;
        }
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

    <div class="card border-0 shadow-sm rounded-3 p-3 mb-3">
        <div class="row g-2 align-items-center">
            <div class="col-12 col-md-5">
                <div class="search-box-wrap">
                    <input type="search"
                           id="catalogSearchInput"
                           class="form-control form-control-sm pe-4"
                           placeholder="🔍 Search item name, SKU, or category..."
                           oninput="applyCatalogFilters()">
                    <button type="button" class="search-clear-btn" id="clearSearchBtn" onclick="clearCatalogSearch()" title="Clear search">&times;</button>
                </div>
            </div>
            <div class="col-12 col-md-7">
                <div class="cat-scroll-bar" id="categoryTabs">
                    <button type="button"
                            class="cat-pill active"
                            data-cat="all"
                            onclick="selectCategory('all', this)">
                        All Items (<?= count($products) ?>)
                    </button>
                    <?php foreach ( $categories as $cat ): ?>
                        <button type="button"
                                class="cat-pill"
                                data-cat="<?= htmlspecialchars($cat['slug']) ?>"
                                onclick="selectCategory('<?= htmlspecialchars(addslashes($cat['slug'])) ?>', this)">
                            <?= htmlspecialchars($cat['name']) ?> (<?= (int) $cat['count'] ?>)
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3" id="catalogGrid">
        <?php foreach ( $products as $p ): ?>
            <?php
            $searchHaystack = strtolower($p['name'] . ' ' . $p['sku'] . ' ' . $p['category_name']);
            ?>
            <div class="col-12 col-md-6 col-lg-4 catalog-item-col"
                 data-category="<?= htmlspecialchars($p['category_slug']) ?>"
                 data-search="<?= htmlspecialchars($searchHaystack) ?>">
                <div class="card product-card h-100 p-3 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="badge bg-secondary-subtle text-secondary"><?= htmlspecialchars((string) $p['sku']) ?></span>
                            <span class="badge bg-success-subtle text-success"><?= htmlspecialchars((string) $p['category_name']) ?></span>
                        </div>
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

    <div id="emptyFilterState" class="text-center py-5 d-none">
        <h6 class="fw-bold text-secondary mb-1">No matching items found</h6>
        <p class="small text-muted mb-2">Try a different search term or switch back to All Items.</p>
        <button type="button" class="btn btn-sm btn-outline-success" onclick="resetAllFilters()">Show All Items</button>
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
                        <option value="PICKUP">In-Store Pickup (Free)</option>
                        <option value="DELIVERY">Delivery (+ZMW 35.00)</option>
                    </select>
                </div>
                <div class="mb-3" id="addressGroup" style="display: none;">
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
                        <strong>ZMW <span id="modalDelivery">0.00</span></strong>
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
let activeCategory = 'all';

function selectCategory(catSlug, btnEl) {
    activeCategory = catSlug;
    document.querySelectorAll('#categoryTabs .cat-pill').forEach(btn => btn.classList.remove('active'));
    if (btnEl) {
        btnEl.classList.add('active');
    }
    applyCatalogFilters();
}

function clearCatalogSearch() {
    const input = document.getElementById('catalogSearchInput');
    input.value = '';
    applyCatalogFilters();
    input.focus();
}

function resetAllFilters() {
    document.getElementById('catalogSearchInput').value = '';
    const allBtn = document.querySelector('#categoryTabs .cat-pill[data-cat="all"]');
    selectCategory('all', allBtn);
}

function applyCatalogFilters() {
    const searchInput = document.getElementById('catalogSearchInput');
    const clearBtn = document.getElementById('clearSearchBtn');
    const query = (searchInput.value || '').trim().toLowerCase();

    clearBtn.style.display = query.length > 0 ? 'block' : 'none';

    const cards = document.querySelectorAll('.catalog-item-col');
    let visibleCount = 0;

    cards.forEach(col => {
        const itemCat = col.getAttribute('data-category') || '';
        const itemSearch = col.getAttribute('data-search') || '';

        const matchesCat = (activeCategory === 'all' || itemCat === activeCategory);
        const matchesQuery = (query === '' || itemSearch.includes(query));

        if (matchesCat && matchesQuery) {
            col.classList.remove('d-none');
            visibleCount++;
        } else {
            col.classList.add('d-none');
        }
    });

    const emptyState = document.getElementById('emptyFilterState');
    if (visibleCount === 0) {
        emptyState.classList.remove('d-none');
    } else {
        emptyState.classList.add('d-none');
    }
}

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
        const res = await fetch('/api/whatsapp_checkout/init_order.php', {
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
            callback: async function (payment) {
                let hostedReceiptUrl = '';

                try {
                    const confirmRes = await fetch('/api/whatsapp_checkout/complete_order.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            tx_ref: orderData.tx_ref,
                            transaction_id: String(payment.transaction_id || '')
                        })
                    });
                    const confirmData = await confirmRes.json();
                    if (confirmData && confirmData.hosted_receipt_url) {
                        hostedReceiptUrl = confirmData.hosted_receipt_url;
                    }
                } catch (e) {}

                // Short, PII-safe WhatsApp message with 1-line tokenized receipt link (/r/<token>)
                let msgLines = [
                    `✅ *PAYMENT CONFIRMED — ZMW ${orderData.amount_zmw.toFixed(2)}*`,
                    `Reference: ${orderData.tx_ref}`,
                    `Fulfillment: ${deliveryType}`
                ];
                if (hostedReceiptUrl) {
                    msgLines.push(``);
                    msgLines.push(`🧾 *View / Download PDF Receipt:*`);
                    msgLines.push(hostedReceiptUrl);
                }

                const targetPhone = (MERCHANT_WA && MERCHANT_WA.length >= 10) ? MERCHANT_WA : orderData.customer_phone;
                const waUrl = `https://wa.me/${targetPhone}?text=${encodeURIComponent(msgLines.join('\n'))}`;
                window.location.href = waUrl;
            },
            onclose: function() {
                btn.disabled = false;
                btn.textContent = 'Pay with Mobile Money / Card';
            }
        };

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
