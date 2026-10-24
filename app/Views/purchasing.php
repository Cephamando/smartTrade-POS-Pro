<?php
$tab = $tab ?? 'inquiry';
$bayPrefill = is_array($bayPrefill ?? null) ? $bayPrefill : [];

$grvPoId = 0;
if (!empty($bayPrefill['purchase_order_id'])) {
    $grvPoId = (int)$bayPrefill['purchase_order_id'];
} elseif (!empty($receiveOrder['id'])) {
    $grvPoId = (int)$receiveOrder['id'];
}

$grvVendorId = 0;
if (!empty($bayPrefill['vendor_id'])) {
    $grvVendorId = (int)$bayPrefill['vendor_id'];
} elseif (!empty($receiveOrder['vendor_id'])) {
    $grvVendorId = (int)$receiveOrder['vendor_id'];
}

$grvLocationId = 0;
if (!empty($bayPrefill['location_id'])) {
    $grvLocationId = (int)$bayPrefill['location_id'];
} elseif (!empty($receiveOrder['location_id'])) {
    $grvLocationId = (int)$receiveOrder['location_id'];
} elseif (!empty($currentLocId)) {
    $grvLocationId = (int)$currentLocId;
}

$grvRef = '';
if (!empty($bayPrefill['ref'])) {
    $grvRef = (string)$bayPrefill['ref'];
} elseif (!empty($receiveOrder['reference_no'])) {
    $grvRef = (string)$receiveOrder['reference_no'];
}

$productsJson = [];
foreach ($products as $p) {
    $raw = \App\Helpers\Movement::isRawProduct($p);
    $productsJson[] = [
        'id' => (int)$p['id'],
        'name' => $p['name'],
        'sku' => $p['sku'] ?? '',
        'unit' => $p['unit'] ?? '',
        'barcode' => $p['barcode'] ?? '',
        'cost' => (float)($p['cost_price'] ?? 0),
        'is_raw' => $raw,
        'reason' => $raw ? 'Raw food must stay in kitchen or warehouse, not a POS bar or store.' : '',
    ];
}
$locationsJson = [];
foreach ($locations as $l) {
    $locationsJson[] = [
        'id' => (int)$l['id'],
        'name' => $l['name'],
        'type' => $l['type'] ?? '',
        'is_pos' => \App\Helpers\Movement::isFrontOfHouse($l),
    ];
}
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1"><i class="bi bi-clipboard-check text-primary"></i> Purchasing</h3>
        <p class="text-muted mb-0">Inquiry → order → goods received from vendors.</p>
    </div>
    <a href="index.php?page=vendors" class="btn btn-outline-secondary fw-bold"><i class="bi bi-truck"></i> Vendors</a>
</div>

<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'inquiry' ? 'active' : '' ?>" href="index.php?page=purchasing&tab=inquiry">
            <i class="bi bi-question-circle"></i> Purchase Inquiry
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'orders' ? 'active' : '' ?>" href="index.php?page=purchasing&tab=orders">
            <i class="bi bi-receipt"></i> Orders
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link fw-bold <?= $tab === 'received' ? 'active' : '' ?>" href="index.php?page=purchasing&tab=received">
            <i class="bi bi-box-seam"></i> Goods Received
        </a>
    </li>
</ul>

<?php if ($tab === 'inquiry'): ?>
<div class="row g-3">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 border-top border-primary border-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><?= $editInquiry ? 'Edit inquiry #' . (int)$editInquiry['id'] : 'New purchase inquiry' ?></h5>
                <form method="POST">
                    <input type="hidden" name="save_inquiry" value="1">
                    <input type="hidden" name="inquiry_id" value="<?= (int)($editInquiry['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">VENDOR</label>
                        <select name="vendor_id" class="form-select" onchange="toggleCustomVendor(this)">
                            <option value="">To be assigned</option>
                            <option value="custom" class="fw-bold text-primary">+ New Informal / Once-off Vendor</option>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" data-contact="<?= htmlspecialchars((string)($v['contact_person'] ?? '')) ?>" data-phone="<?= htmlspecialchars((string)($v['phone'] ?? '')) ?>" <?= ((int)($editInquiry['vendor_id'] ?? 0) === (int)$v['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="custom_vendor_name" class="form-control mt-2" placeholder="Enter custom vendor name..." style="display:none;" id="customVendorInput_inquiry">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label small fw-bold text-muted">RECEIVE INTO</label>
                            <select name="location_id" class="form-select" required>
                                <?php foreach ($locations as $l): ?>
                                    <option value="<?= (int)$l['id'] ?>" <?= ((int)($editInquiry['location_id'] ?? $currentLocId) === (int)$l['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($l['name']) ?> (<?= htmlspecialchars($l['type'] ?? '') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold text-muted">NEEDED BY</label>
                            <input type="text" name="needed_by" class="form-control datepicker" value="<?= htmlspecialchars($editInquiry['needed_by'] ?? '') ?>" autocomplete="off">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">NOTES</label>
                        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editInquiry['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-2 d-flex justify-content-between align-items-center">
                        <label class="form-label small fw-bold text-muted mb-0">ITEMS</label>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="addLine('inquiryLines')"><i class="bi bi-plus"></i> Line</button>
                    </div>
                    <div id="inquiryLines"></div>
                    <div class="d-flex gap-2 mt-3">
                        <button name="status" value="draft" class="btn btn-outline-secondary fw-bold flex-fill">Save draft</button>
                        <button name="status" value="sent" class="btn btn-primary fw-bold flex-fill">Send inquiry</button>
                    </div>
                </form>
            </div>
        </div>
        <?php if (!empty($inquiryQuote)): ?>
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-body">
                <h6 class="fw-bold">Quotation <?= htmlspecialchars((string)$inquiryQuote['status']) ?></h6>
                <div class="small text-muted mb-2">
                    <?php if (!empty($inquiryQuote['delivery_date'])): ?>Deliver <?= htmlspecialchars((string)$inquiryQuote['delivery_date']) ?>. <?php endif; ?>
                    <?php if (!empty($inquiryQuote['valid_until'])): ?>Valid until <?= htmlspecialchars((string)$inquiryQuote['valid_until']) ?>.<?php endif; ?>
                </div>
                <?php if (!empty($inquiryQuote['notes'])): ?><p class="small mb-2"><?= htmlspecialchars((string)$inquiryQuote['notes']) ?></p><?php endif; ?>
                <ul class="list-unstyled small mb-3">
                    <?php foreach ($inquiryQuote['items'] as $line): ?>
                        <li>
                            <?= htmlspecialchars((string)$line['product_name']) ?>
                            × <?= htmlspecialchars((string)$line['quantity']) ?>
                            <?php if (empty($line['can_supply'])): ?>
                                <span class="text-danger">Cannot supply</span>
                            <?php else: ?>
                                at ZMW <?= number_format((float)$line['unit_cost'], 2) ?>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <?php if ((string)$inquiryQuote['status'] === 'submitted'): ?>
                <form method="POST" class="js-confirm-form"
                    data-swal-title="Accept this quotation?"
                    data-swal-text="A purchase order is created at the quoted prices."
                    data-swal-confirm="Accept quote"
                    data-swal-cancel="Cancel"
                    data-swal-icon="question"
                    data-swal-confirm-color="#198754">
                    <input type="hidden" name="accept_quote" value="1">
                    <input type="hidden" name="inquiry_id" value="<?= (int)$editInquiry['id'] ?>">
                    <button class="btn btn-success fw-bold w-100">Accept quote</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light"><tr><th>Inquiry</th><th>Vendor</th><th>Items</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($inquiries as $row): ?>
                        <tr>
                            <td class="fw-bold">#<?= (int)$row['id'] ?><div class="small text-muted"><?= htmlspecialchars($row['location_name']) ?></div></td>
                            <td><?= htmlspecialchars($row['vendor_name'] ?: 'Unassigned') ?></td>
                            <td><?= (int)$row['item_count'] ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($row['status']) ?></span></td>
                            <td class="text-end">
                                <a class="btn btn-sm btn-outline-primary" href="index.php?page=purchasing&tab=inquiry&inquiry_id=<?= (int)$row['id'] ?>">Open</a>
                                <?php if (!in_array($row['status'], ['converted', 'cancelled'], true)): ?>
                                <form method="POST" class="d-inline js-confirm-form"
                                    data-swal-title="Convert inquiry #<?= (int)$row['id'] ?> to an order?"
                                    data-swal-text="This inquiry will become a purchase order."
                                    data-swal-confirm="Convert"
                                    data-swal-cancel="Cancel"
                                    data-swal-icon="question"
                                    data-swal-confirm-color="#198754">
                                    <input type="hidden" name="convert_inquiry" value="1">
                                    <input type="hidden" name="inquiry_id" value="<?= (int)$row['id'] ?>">
                                    <button class="btn btn-sm btn-success">To order</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($inquiries)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No inquiries yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'orders'): ?>
<?php if (!empty($_SESSION['supplier_checkout_url'])):
    $supplierCheckout = (string)$_SESSION['supplier_checkout_url'];
    unset($_SESSION['supplier_checkout_url']);
?>
<div class="alert alert-info">
    <a class="alert-link" href="<?= htmlspecialchars($supplierCheckout) ?>" target="_blank" rel="noopener">Continue the Flutterwave payment</a>
</div>
<script>window.open(<?= json_encode($supplierCheckout) ?>, '_blank', 'noopener');</script>
<?php endif; ?>
<div class="row g-3">
    <div class="col-12">
        <div class="card shadow-sm border-0 border-top border-primary border-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Order from supplier list</h5>
                <form method="GET" class="mb-3">
                    <input type="hidden" name="page" value="purchasing">
                    <input type="hidden" name="tab" value="orders">
                    <label class="form-label small fw-bold text-muted">SUPPLIER</label>
                    <select name="catalog_vendor" class="form-select" onchange="this.form.submit()">
                        <option value="">Choose a supplier…</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= (int)$v['id'] ?>" <?= (int)($catalogVendorId ?? 0) === (int)$v['id'] ? 'selected' : '' ?>><?= htmlspecialchars($v['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
                <?php if ((int)($catalogVendorId ?? 0) > 0 && empty($catalogOfferings)): ?>
                    <p class="text-muted small mb-0">This supplier has not priced any shop items yet.</p>
                <?php elseif (!empty($catalogOfferings)): ?>
                <form method="POST">
                    <input type="hidden" name="order_from_catalog" value="1">
                    <input type="hidden" name="vendor_id" value="<?= (int)$catalogVendorId ?>">
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label small fw-bold text-muted">DELIVER TO</label>
                            <select name="location_id" class="form-select" required>
                                <?php foreach ($locations as $l): ?>
                                    <option value="<?= (int)$l['id'] ?>" <?= ((int)$currentLocId === (int)$l['id']) ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold text-muted">REFERENCE</label>
                            <input type="text" name="reference_no" class="form-control" placeholder="PO-…">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-3">
                            <thead class="table-light small text-muted"><tr><th></th><th>Item</th><th>Price</th><th style="width:8rem">Qty</th></tr></thead>
                            <tbody>
                            <?php foreach ($catalogOfferings as $offer): ?>
                                <tr>
                                    <td><input type="checkbox" class="form-check-input" name="lines[<?= (int)$offer['product_id'] ?>][pick]" value="1"></td>
                                    <td>
                                        <?= htmlspecialchars((string)$offer['name']) ?>
                                        <?php if (($offer['unit'] ?? '') !== ''): ?><div class="small text-muted"><?= htmlspecialchars((string)$offer['unit']) ?></div><?php endif; ?>
                                        <input type="hidden" name="lines[<?= (int)$offer['product_id'] ?>][product_id]" value="<?= (int)$offer['product_id'] ?>">
                                        <input type="hidden" name="lines[<?= (int)$offer['product_id'] ?>][unit]" value="<?= htmlspecialchars((string)($offer['unit'] ?? '')) ?>">
                                    </td>
                                    <td>ZMW <?= number_format((float)$offer['unit_cost'], 2) ?></td>
                                    <td><input type="number" name="lines[<?= (int)$offer['product_id'] ?>][qty]" class="form-control form-control-sm" min="0.01" step="0.01" value="1"></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <button class="btn btn-primary fw-bold">Create order from ticked lines</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm border-0 border-top border-success border-4">
            <div class="card-body">
                <h5 class="fw-bold mb-3"><?= $editOrder ? 'Edit order #' . (int)$editOrder['id'] : 'New purchase order' ?></h5>
                <form method="POST">
                    <input type="hidden" name="save_order" value="1">
                    <input type="hidden" name="order_id" value="<?= (int)($editOrder['id'] ?? 0) ?>">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">VENDOR</label>
                        <select name="vendor_id" class="form-select" required onchange="toggleCustomVendor(this)">
                            <option value="">Select vendor…</option>
                            <option value="custom" class="fw-bold text-primary">+ New Informal / Once-off Vendor</option>
                            <?php foreach ($vendors as $v): ?>
                                <option value="<?= (int)$v['id'] ?>" data-contact="<?= htmlspecialchars((string)($v['contact_person'] ?? '')) ?>" data-phone="<?= htmlspecialchars((string)($v['phone'] ?? '')) ?>" <?= ((int)($editOrder['vendor_id'] ?? 0) === (int)$v['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($v['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="text" name="custom_vendor_name" class="form-control mt-2" placeholder="Enter custom vendor name..." style="display:none;" id="customVendorInput_order">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-7">
                            <label class="form-label small fw-bold text-muted">DELIVER TO</label>
                            <select name="location_id" class="form-select" required>
                                <?php foreach ($locations as $l): ?>
                                    <option value="<?= (int)$l['id'] ?>" <?= ((int)($editOrder['location_id'] ?? $currentLocId) === (int)$l['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($l['name']) ?> (<?= htmlspecialchars($l['type'] ?? '') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold text-muted">REFERENCE</label>
                            <input type="text" name="reference_no" class="form-control" value="<?= htmlspecialchars($editOrder['reference_no'] ?? '') ?>" placeholder="PO-…">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-muted">NOTES</label>
                        <textarea name="notes" class="form-control" rows="2"><?= htmlspecialchars($editOrder['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-2 d-flex justify-content-between">
                        <label class="form-label small fw-bold text-muted mb-0">ORDER LINES</label>
                        <button type="button" class="btn btn-sm btn-outline-success" onclick="addLine('orderLines', true)"><i class="bi bi-plus"></i> Line</button>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-1 purchase-table">
                            <thead class="table-light small text-muted">
                                <tr><th style="min-width:220px">Product</th><th style="min-width:88px">Unit</th><th style="min-width:110px">Pack size</th><th style="min-width:110px">Packs</th><th style="min-width:120px">Qty</th><th style="min-width:130px">Pack price</th><th style="min-width:130px">Unit price</th><th style="min-width:110px" class="text-end">Total</th><th style="width:44px"></th></tr>
                            </thead>
                            <tbody id="orderLines"></tbody>
                            <tfoot class="fw-bold"><tr><td colspan="4" class="text-end text-muted small">TOTALS</td><td><span class="js-total-units" data-for="orderLines">0</span></td><td colspan="2" class="text-end text-muted small">ZMW</td><td class="text-end"><span class="js-total-amount" data-for="orderLines">0.00</span></td><td></td></tr></tfoot>
                        </table>
                    </div>
                    <button class="btn btn-success w-100 fw-bold mt-3">Save order</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <table class="table table-hover align-middle mb-0">
                    <thead class="bg-light"><tr><th>Order</th><th>Vendor</th><th>Total</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($orders as $row): ?>
                        <tr>
                            <td class="fw-bold"><?= htmlspecialchars($row['reference_no'] ?: ('#' . $row['id'])) ?>
                                <div class="small text-muted"><?= htmlspecialchars($row['location_name']) ?></div>
                            </td>
                            <td><?= htmlspecialchars($row['vendor_name']) ?></td>
                            <td>ZMW <?= number_format((float)$row['total_cost'], 2) ?></td>
                            <td>
                                <span class="badge <?= $row['status'] === 'received' ? 'bg-success' : ($row['status'] === 'declined' || $row['status'] === 'cancelled' ? 'bg-danger' : 'bg-warning text-dark') ?>"><?= htmlspecialchars($row['status']) ?></span>
                                <?php if (!empty($row['vendor_status']) && $row['vendor_status'] !== 'awaiting'): ?>
                                    <div class="small text-muted"><?= htmlspecialchars((string)$row['vendor_status']) ?><?= !empty($row['vendor_delivery_ref']) ? ' · ' . htmlspecialchars((string)$row['vendor_delivery_ref']) : '' ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php
                                $payInfo = $supplierPayments[(int)$row['id']] ?? ['paid' => 0, 'pending' => false];
                                $payDest = \App\Models\Vendor::payoutDestination($row);
                                $unpaid = round((float)$row['total_cost'] - (float)$payInfo['paid'], 2);
                                $canPay = ($role ?? '') === 'dev'
                                    && !in_array($row['status'], ['cancelled', 'declined'], true)
                                    && ($row['vendor_status'] ?? '') !== 'declined'
                                    && $unpaid >= 1
                                    && empty($payInfo['pending']);
                                ?>
                                <?php if ((float)$payInfo['paid'] > 0): ?>
                                    <div class="small text-muted">Paid ZMW <?= number_format((float)$payInfo['paid'], 2) ?></div>
                                <?php endif; ?>
                                <?php if (!empty($payInfo['pending'])): ?>
                                    <div class="small text-warning">Payment pending</div>
                                <?php endif; ?>
                                <?php if ($canPay && !empty($payDest['ready'])): ?>
                                    <button type="button" class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#supplierPayModal"
                                        data-id="<?= (int)$row['id'] ?>"
                                        data-label="<?= htmlspecialchars((string)($row['reference_no'] ?: ('#' . $row['id'])), ENT_QUOTES) ?>"
                                        data-left="<?= htmlspecialchars(number_format($unpaid, 2, '.', '')) ?>"
                                        data-dest="<?= htmlspecialchars(trim($payDest['bank_name'] . ' ' . $payDest['masked'])) ?>">Pay</button>
                                <?php elseif ($canPay): ?>
                                    <div class="small text-muted">No payment account</div>
                                <?php endif; ?>
                                <?php if (in_array($row['status'], ['pending', 'partial'], true)): ?>
                                    <a class="btn btn-sm btn-primary" href="index.php?page=purchasing&tab=received&receive_order=<?= (int)$row['id'] ?>">Receive</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (empty($orders)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No purchase orders yet.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php if (($role ?? '') === 'dev'): ?>
<div class="modal fade" id="supplierPayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="js-confirm-form"
                data-swal-title="Pay this supplier?"
                data-swal-text="The shop pays by card or mobile money, or from a released payout balance. Flutterwave sends a card or mobile-money payment to the supplier's onboarded account."
                data-swal-confirm="Send payment"
                data-swal-cancel="Cancel"
                data-swal-icon="warning">
                <div class="modal-header">
                    <h5 class="modal-title">Pay supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="pay_supplier" value="1">
                    <input type="hidden" name="order_id" id="supplierPayOrder" value="">
                    <p class="small mb-1" id="supplierPayLabel"></p>
                    <p class="small text-muted" id="supplierPayDest"></p>
                    <label class="form-label small">Pay from</label>
                    <select name="pay_source" id="supplierPaySource" class="form-select mb-2 no-search" onchange="supplierPaySourceChanged(this.value)">
                        <option value="card">Shop card</option>
                        <option value="momo">Shop mobile money</option>
                        <option value="balance">Released payout balance</option>
                    </select>
                    <div id="supplierPayMomo" class="d-none">
                        <label class="form-label small">Shop network</label>
                        <select name="shop_network" class="form-select mb-2 no-search">
                            <option value="MTN">MTN</option>
                            <option value="AIRTEL">Airtel</option>
                            <option value="ZAMTEL">Zamtel</option>
                        </select>
                        <label class="form-label small">Shop mobile number</label>
                        <input type="text" name="shop_phone" class="form-control mb-2" inputmode="numeric" placeholder="0971234567" autocomplete="off">
                    </div>
                    <label class="form-label small">Amount the supplier receives (ZMW)</label>
                    <input type="number" name="amount" id="supplierPayAmount" class="form-control mb-2" min="1" step="0.01" required oninput="supplierPayRefresh()">
                    <p class="small text-muted d-none" id="supplierPayGross"></p>
                    <label class="form-label small">Reason</label>
                    <input type="text" name="narration" class="form-control mb-2" maxlength="100" required placeholder="Payment for purchase order">
                    <label class="form-label small">Developer username</label>
                    <input type="text" name="mgr_username" class="form-control mb-2" required autocomplete="username">
                    <label class="form-label small">Developer password</label>
                    <input type="password" name="mgr_password" class="form-control" required autocomplete="current-password">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="btn btn-dark">Send payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function supplierShopCharge(amount) {
    var n = Math.round(parseFloat(amount) * 100) / 100;
    if (!(n > 0)) return 0;
    return Math.ceil((n / 0.975) * 100 - 1e-9) / 100;
}
function supplierPayRefresh() {
    var sourceEl = document.getElementById('supplierPaySource');
    var source = sourceEl ? sourceEl.value : 'card';
    var momo = document.getElementById('supplierPayMomo');
    if (momo) momo.classList.toggle('d-none', source !== 'momo');
    var form = document.querySelector('#supplierPayModal form');
    var gross = document.getElementById('supplierPayGross');
    var due = parseFloat(document.getElementById('supplierPayAmount')?.value || '');
    var text = 'The shop pays by card. The amount is grossed up so the supplier is due the figure entered.';
    if (gross) gross.classList.add('d-none');
    if (source === 'balance') {
        text = 'Flutterwave will be asked to send this amount from the released payout balance. Money still on hold cannot be sent.';
    } else if (due > 0) {
        var charge = supplierShopCharge(due);
        var line = 'The shop pays ZMW ' + charge.toFixed(2) + ' so the supplier is due ZMW ' + due.toFixed(2) + '. The relationship account keeps 2.5%. Flutterwave’s processing fee still comes off the settlement.';
        if (gross) {
            gross.textContent = line;
            gross.classList.remove('d-none');
        }
        text = source === 'momo'
            ? 'The shop mobile money will be charged ZMW ' + charge.toFixed(2) + ' so the supplier is due ZMW ' + due.toFixed(2) + '.'
            : 'The shop card will be charged ZMW ' + charge.toFixed(2) + ' so the supplier is due ZMW ' + due.toFixed(2) + '.';
    } else if (source === 'momo') {
        text = 'The shop pays from its mobile money. The amount is grossed up so the supplier is due the figure entered.';
    }
    if (form) form.setAttribute('data-swal-text', text);
}
function supplierPaySourceChanged(source) {
    supplierPayRefresh();
}
document.getElementById('supplierPayModal')?.addEventListener('show.bs.modal', function (event) {
    var btn = event.relatedTarget;
    if (!btn) return;
    document.getElementById('supplierPayOrder').value = btn.getAttribute('data-id') || '';
    document.getElementById('supplierPayLabel').textContent = btn.getAttribute('data-label') || '';
    document.getElementById('supplierPayDest').textContent = btn.getAttribute('data-dest') || '';
    var left = btn.getAttribute('data-left') || '';
    var amount = document.getElementById('supplierPayAmount');
    amount.value = left;
    amount.max = left;
    supplierPayRefresh();
});
</script>
<?php endif; ?>
<?php endif; ?>

<?php if ($tab === 'received'): ?>
<div class="card shadow-sm border-0 border-top border-warning border-4">
    <div class="card-body">
        <h5 class="fw-bold mb-3">Goods received from vendor</h5>
        <?php if (!empty($bayPrefill['tracking_code'])): ?>
        <div class="alert alert-warning small">
            From receiving bay <strong><?= htmlspecialchars((string)$bayPrefill['tracking_code']) ?></strong>
            <?php if (!empty($bayPrefill['source_name'])): ?>
                · <?= htmlspecialchars((string)$bayPrefill['source_name']) ?>
            <?php endif; ?>
            . Review the lines, then Process GRV to book stock.
        </div>
        <?php endif; ?>
        <div class="alert alert-info small">
            Raw food can only be received into kitchen or warehouse. POS bars/stores are blocked for those items.
            <i class="bi bi-info-circle" title="Finished goods may still go to a POS location."></i>
        </div>
        <form method="POST" id="grvForm">
            <input type="hidden" name="process_grv" value="1">
            <?php if (!empty($bayPrefill['tracking_code'])): ?>
            <input type="hidden" name="dock_id" value="<?= (int)($_GET['dock_id'] ?? 0) ?>">
            <?php endif; ?>
            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">PURCHASE ORDER</label>
                    <select name="purchase_order_id" id="poSelect" class="form-select" onchange="updatePoSelection(this.value)">
                        <option value="">Walk-in / no PO</option>
                        <?php foreach ($openOrders as $o): ?>
                            <option value="<?= (int)$o['id'] ?>" <?= ($grvPoId === (int)$o['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($o['reference_no'] ?: ('#' . $o['id'])) . ' — ' . $o['vendor_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">VENDOR</label>
                    <select name="vendor_id" id="grvVendor" class="form-select" required onchange="toggleCustomVendor(this)">
                        <option value="">Select vendor…</option>
                        <option value="custom" class="fw-bold text-primary">+ New Informal / Once-off Vendor</option>
                        <?php foreach ($vendors as $v): ?>
                            <option value="<?= (int)$v['id'] ?>" data-contact="<?= htmlspecialchars((string)($v['contact_person'] ?? '')) ?>" data-phone="<?= htmlspecialchars((string)($v['phone'] ?? '')) ?>" <?= ($grvVendorId === (int)$v['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($v['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text" name="custom_vendor_name" class="form-control mt-2" placeholder="Enter custom vendor name..." style="display:none;" id="customVendorInput_received">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">RECEIVE INTO</label>
                    <select name="location_id" id="grvLocation" class="form-select" required>
                        <?php foreach ($locations as $l): ?>
                            <option value="<?= (int)$l['id'] ?>" data-type="<?= htmlspecialchars($l['type'] ?? '') ?>" <?= ($grvLocationId === (int)$l['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($l['name']) ?> (<?= htmlspecialchars($l['type'] ?? '') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">INVOICE / REF</label>
                    <input type="text" name="ref_number" class="form-control" value="<?= htmlspecialchars($grvRef) ?>">
                </div>
            </div>
            <div id="moveWarn" class="alert alert-warning d-none py-2 small"></div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-1 purchase-table">
                    <thead class="table-light small text-muted">
                        <tr>
                            <th style="min-width:240px">Product</th>
                            <th style="min-width:88px">Unit</th>
                            <th style="min-width:110px" title="Units in one pack, e.g. 24 for a crate">Pack size</th>
                            <th style="min-width:110px" title="Number of packs received">Packs</th>
                            <th style="min-width:120px">Qty (units)</th>
                            <th style="min-width:130px" title="Price of one pack — unit price is worked out automatically">Pack price</th>
                            <th style="min-width:130px">Unit price</th>
                            <th style="min-width:120px" class="text-end">Line total</th>
                            <th style="width:44px"></th>
                        </tr>
                    </thead>
                    <tbody id="grvLines"></tbody>
                    <tfoot class="fw-bold">
                        <tr>
                            <td colspan="4" class="text-end text-muted small">TOTALS</td>
                            <td><span class="js-total-units" data-for="grvLines">0</span></td>
                            <td colspan="2" class="text-end text-muted small">ZMW</td>
                            <td class="text-end fs-6"><span class="js-total-amount" data-for="grvLines">0.00</span></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="row g-3 align-items-end">
                <div class="col-md-6">
                    <button type="button" class="btn btn-outline-secondary fw-bold" onclick="addLine('grvLines', true)"><i class="bi bi-plus"></i> Add item</button>
                </div>
                <div class="col-md-6">
                    <input type="text" name="grv_notes" class="form-control form-control-sm" maxlength="500" placeholder="Notes (damaged cartons, short delivery, …)">
                </div>
            </div>
            <div class="text-end mt-3">
                <button class="btn btn-warning fw-bold px-4">Process GRV</button>
            </div>
        </form>
    </div>
</div>

<?php if (!empty($grvView)): ?>
<div class="card shadow-sm border-0 mt-4" id="grvDetail">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold">GRV #<?= (int)$grvView['id'] ?> · <?= htmlspecialchars($grvView['vendor_name'] ?? '') ?> → <?= htmlspecialchars($grvView['location_name'] ?? '') ?></span>
        <span class="small text-muted"><?= htmlspecialchars((string)$grvView['created_at']) ?> · <?= htmlspecialchars($grvView['username'] ?? '') ?> <?= $grvView['reference_no'] ? '· Ref ' . htmlspecialchars($grvView['reference_no']) : '' ?></span>
    </div>
    <div class="table-responsive">
        <table class="table table-sm mb-0">
            <thead class="table-light small"><tr><th>Product</th><th>Unit</th><th class="text-end">Pack size</th><th class="text-end">Packs</th><th class="text-end">Qty</th><th class="text-end">Pack price</th><th class="text-end">Unit price</th><th class="text-end">Line total</th></tr></thead>
            <tbody>
            <?php foreach ($grvView['items'] as $gi): ?>
                <tr>
                    <td><?= htmlspecialchars($gi['product_name']) ?> <span class="text-muted small"><?= htmlspecialchars($gi['sku'] ?? '') ?></span></td>
                    <td><?= htmlspecialchars($gi['unit'] ?: ($gi['product_unit'] ?? '')) ?></td>
                    <td class="text-end"><?= (float)$gi['pack_size'] != 1 ? rtrim(rtrim(number_format((float)$gi['pack_size'], 4, '.', ''), '0'), '.') : '—' ?></td>
                    <td class="text-end"><?= $gi['pack_qty'] !== null ? rtrim(rtrim(number_format((float)$gi['pack_qty'], 4, '.', ''), '0'), '.') : '—' ?></td>
                    <td class="text-end"><?= rtrim(rtrim(number_format((float)$gi['quantity'], 4, '.', ''), '0'), '.') ?></td>
                    <td class="text-end"><?= $gi['pack_cost'] !== null ? number_format((float)$gi['pack_cost'], 2) : '—' ?></td>
                    <td class="text-end"><?= number_format((float)$gi['unit_cost'], 2) ?></td>
                    <td class="text-end fw-bold"><?= number_format((float)($gi['line_total'] ?: $gi['quantity'] * $gi['unit_cost']), 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="fw-bold"><tr><td colspan="7" class="text-end">TOTAL</td><td class="text-end">ZMW <?= number_format((float)$grvView['total_cost'], 2) ?></td></tr></tfoot>
        </table>
    </div>
    <?php if (!empty($grvView['notes'])): ?><div class="card-footer small text-muted"><i class="bi bi-chat-left-text"></i> <?= htmlspecialchars($grvView['notes']) ?></div><?php endif; ?>
</div>
<?php endif; ?>

<div class="card shadow-sm border-0 mt-4">
    <div class="card-header bg-white fw-bold"><i class="bi bi-clock-history"></i> Recent goods received</div>
    <div class="table-responsive">
        <table class="table table-hover table-sm align-middle mb-0">
            <thead class="table-light small"><tr><th>#</th><th>Date</th><th>Vendor</th><th>Into</th><th>Ref</th><th class="text-end">Lines</th><th class="text-end">Units</th><th class="text-end">Total</th><th>By</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($grvHistory)): ?><tr><td colspan="10" class="text-center text-muted py-3">No goods received yet.</td></tr><?php endif; ?>
            <?php foreach ($grvHistory as $g): ?>
                <tr>
                    <td class="fw-bold">#<?= (int)$g['id'] ?></td>
                    <td class="text-nowrap small"><?= htmlspecialchars(date('d M Y H:i', strtotime((string)$g['created_at']))) ?></td>
                    <td><?= htmlspecialchars($g['vendor_name'] ?? '') ?></td>
                    <td><?= htmlspecialchars($g['location_name'] ?? '') ?></td>
                    <td class="small"><?= htmlspecialchars($g['reference_no'] ?? '') ?><?= $g['purchase_order_id'] ? ' <span class="badge bg-light text-dark border">PO #' . (int)$g['purchase_order_id'] . '</span>' : '' ?></td>
                    <td class="text-end"><?= (int)$g['line_count'] ?></td>
                    <td class="text-end"><?= rtrim(rtrim(number_format((float)$g['unit_count'], 2, '.', ''), '0'), '.') ?></td>
                    <td class="text-end fw-bold"><?= number_format((float)$g['total_cost'], 2) ?></td>
                    <td class="small"><?= htmlspecialchars($g['username'] ?? '') ?></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="index.php?page=purchasing&tab=received&grv_id=<?= (int)$g['id'] ?>#grvDetail"><i class="bi bi-eye"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<script>
const catalog = <?= json_encode($productsJson) ?>;
const locationsMeta = <?= json_encode($locationsJson) ?>;
const inquiryPrefill = <?= json_encode($editInquiry['items'] ?? []) ?>;
const orderPrefill = <?= json_encode($editOrder['items'] ?? $receiveOrder['items'] ?? []) ?>;
const bayPrefillItems = <?= json_encode(($bayPrefill ?? [])['items'] ?? []) ?>;

// Custom Vendor Field Toggle
function toggleCustomVendor(selectObj) {
    const inputId = selectObj.name === 'vendor_id' ? (selectObj.closest('form').querySelector('input[name="custom_vendor_name"]').id) : '';
    if (!inputId) return;
    const inputEl = document.getElementById(inputId);
    if (selectObj.value === 'custom') {
        inputEl.style.display = 'block';
        inputEl.setAttribute('required', 'required');
    } else {
        inputEl.style.display = 'none';
        inputEl.removeAttribute('required');
        inputEl.value = '';
    }
}

function updatePoSelection(poId) {
    let url = new URL(window.location.href);
    url.searchParams.set('tab', 'received');
    if (poId) {
        url.searchParams.set('receive_order', poId);
    } else {
        url.searchParams.delete('receive_order');
    }
    window.location.href = url.toString();
}

function toggleSellable(pid, currentIsSellable) {
    if (!pid) return;
    const newStatus = currentIsSellable ? 0 : 1;
    const text = newStatus
        ? "Mark this product as SELLABLE on the POS?"
        : "Mark this product as RAW MATERIAL (greyed out on POS)?";

    Swal.fire({
        title: 'Update POS Visibility',
        text: text,
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Yes, update it',
        confirmButtonColor: newStatus ? '#198754' : '#dc3545'
    }).then(result => {
        if (result.isConfirmed) {
            fetch('index.php?page=purchasing', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'toggle_sellable=1&product_id=' + pid + '&is_sellable=' + newStatus
            }).then(r => r.json()).then(res => {
                if (res.status === 'success') {
                    const p = catalog.find(item => String(item.id) === String(pid));
                    if (p) {
                        p.is_raw = (newStatus === 0);
                        p.is_sellable = newStatus;
                    }
                    document.querySelectorAll('.js-prod').forEach(sel => {
                        Array.from(sel.options).forEach(opt => {
                            if (String(opt.value) === String(pid)) {
                                opt.dataset.raw = (newStatus === 0) ? '1' : '0';
                                opt.text = (p ? p.name : opt.text.replace(' — RAW', '')) + (newStatus === 0 ? ' — RAW' : '');
                            }
                        });
                        if (window.SelectSearch) SelectSearch.refresh(sel);
                    });
                    refreshMovementHint();
                    AppAlert.notify({icon: 'success', text: 'POS visibility updated!'});
                } else {
                    AppAlert.notify({icon: 'error', text: res.message ? res.message : 'Failed to update.'});
                }
            });
        }
    });
}

function escAttr(s) { return String(s === undefined || s === null ? '' : s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;'); }

function productOptions(selected, withCost) {
    return catalog.map(function (p) {
        const sel = String(p.id) === String(selected) ? ' selected' : '';
        const pReason = p.reason ? p.reason : '';
        const raw = p.is_raw ? ' data-raw="1" data-reason="' + escAttr(pReason) + '"' : '';
        const label = p.name + (p.is_raw ? ' — RAW' : '');
        const cost = withCost ? ' data-cost="' + p.cost + '"' : '';
        // data-sku / data-barcode / data-unit are picked up by the searchable dropdown as extra filters.
        return '<option value="' + p.id + '"' + sel + raw + cost + ' data-unit="' + escAttr(p.unit) + '" data-sku="' + escAttr(p.sku) + '" data-barcode="' + escAttr(p.barcode) + '">' + label + '</option>';
    }).join('');
}

let lineSeq = 0;
function num(v) { const n = parseFloat(v); return isFinite(n) ? n : 0; }
function fmt(n, d) { return Number(n || 0).toLocaleString(undefined, { minimumFractionDigits: d === undefined ? 2 : d, maximumFractionDigits: d === undefined ? 2 : d }); }
function trimNum(n) { return String(Math.round(num(n) * 10000) / 10000); }

/**
 * Receiving / order line with auto-calculations:
 *   packs × pack size → qty · pack price ÷ pack size → unit price · qty × unit price → line total
 */
function addLine(containerId, withCost, preset) {
    const wrap = document.getElementById(containerId);
    if (!wrap) return;
    const idx = lineSeq++;
    preset = preset || {};
    const pid = preset.product_id ? preset.product_id : '';
    const qty = preset.quantity ? preset.quantity : (preset.qty ? preset.qty : '');
    const cost = preset.unit_cost ? preset.unit_cost : (preset.cost_price ? preset.cost_price : (preset.cost ? preset.cost : ''));
    const packSize = preset.pack_size && num(preset.pack_size) > 0 ? preset.pack_size : 1;
    const packQty = preset.pack_qty ? preset.pack_qty : '';
    const packCost = preset.pack_cost ? preset.pack_cost : '';
    const unit = preset.unit ? preset.unit : (pid ? ((catalog.find(function (p) { return String(p.id) === String(pid); }) || {}).unit || '') : '');
    const n = 'items[' + idx + ']';

    if (!withCost) {
        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-center purchase-line';
        row.innerHTML = '<div class="col-8"><select name="' + n + '[product_id]" class="form-select form-select-sm js-prod" required><option value="">Select…</option>' + productOptions(pid, false) + '</select></div>'
            + '<div class="col-3"><input type="number" step="0.01" min="0.01" name="' + n + '[qty]" class="form-control form-control-sm" required placeholder="Qty" value="' + escAttr(qty) + '"></div>'
            + '<div class="col-1"><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest(\'.purchase-line\').remove()"><i class="bi bi-trash"></i></button></div>'
            + '<div class="col-12 small text-muted js-line-info"></div>';
        wrap.appendChild(row);
        row.querySelector('.js-prod').addEventListener('change', refreshMovementHint);
        refreshMovementHint();
        return;
    }

    const tr = document.createElement('tr');
    tr.className = 'purchase-line';
    tr.innerHTML =
        '<td><select name="' + n + '[product_id]" class="form-select form-select-sm js-prod" required><option value="">Select…</option>' + productOptions(pid, true) + '</select><div class="small text-muted js-line-info"></div></td>'
        + '<td><input type="text" name="' + n + '[unit]" class="form-control form-control-sm js-unit" maxlength="20" placeholder="ea" value="' + escAttr(unit) + '"></td>'
        + '<td><input type="number" step="any" min="0.0001" name="' + n + '[pack_size]" class="form-control form-control-sm js-pack-size" value="' + escAttr(packSize) + '"></td>'
        + '<td><input type="number" step="any" min="0" name="' + n + '[pack_qty]" class="form-control form-control-sm js-pack-qty" placeholder="—" value="' + escAttr(packQty) + '"></td>'
        + '<td><input type="number" step="any" min="0.0001" name="' + n + '[qty]" class="form-control form-control-sm js-qty fw-bold" required placeholder="0" value="' + escAttr(qty) + '"></td>'
        + '<td><input type="number" step="0.01" min="0" name="' + n + '[pack_cost]" class="form-control form-control-sm js-pack-cost" placeholder="—" value="' + escAttr(packCost) + '"></td>'
        + '<td><input type="number" step="0.0001" min="0" name="' + n + '[cost]" class="form-control form-control-sm js-cost" placeholder="0.00" value="' + escAttr(cost) + '"></td>'
        + '<td class="text-end fw-bold text-nowrap js-line-total">0.00</td>'
        + '<td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger" title="Remove line"><i class="bi bi-trash"></i></button></td>';
    wrap.appendChild(tr);

    const el = function (c) { return tr.querySelector(c); };
    const recalcTotal = function () {
        const total = num(el('.js-qty').value) * num(el('.js-cost').value);
        el('.js-line-total').textContent = fmt(total);
        recalcTotals(containerId);
    };
    el('.js-prod').addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        if (opt && opt.value) {
            if (opt.dataset.cost && num(opt.dataset.cost) > 0 && !num(el('.js-cost').value)) {
                el('.js-cost').value = opt.dataset.cost;
            }
            if (opt.dataset.unit && !el('.js-unit').value) {
                el('.js-unit').value = opt.dataset.unit;
            }
            if (num(el('.js-pack-cost').value) > 0 && num(el('.js-pack-size').value) > 0) {
                el('.js-cost').value = trimNum(num(el('.js-pack-cost').value) / num(el('.js-pack-size').value));
            }
        }
        recalcTotal();
        refreshMovementHint();
    });
    // packs or pack size → qty; pack price or pack size → unit price
    const fromPacks = function () {
        const ps = num(el('.js-pack-size').value) || 1;
        const pq = num(el('.js-pack-qty').value);
        const pc = num(el('.js-pack-cost').value);
        if (pq > 0) el('.js-qty').value = trimNum(pq * ps);
        if (pc > 0) el('.js-cost').value = trimNum(pc / ps);
        recalcTotal();
    };
    el('.js-pack-size').addEventListener('input', fromPacks);
    el('.js-pack-qty').addEventListener('input', fromPacks);
    el('.js-pack-cost').addEventListener('input', fromPacks);
    // qty typed directly → packs; unit price typed directly → pack price
    el('.js-qty').addEventListener('input', function () {
        const ps = num(el('.js-pack-size').value) || 1;
        if ((el('.js-pack-qty').value !== '' || ps > 1) && ps > 0) el('.js-pack-qty').value = trimNum(num(this.value) / ps);
        recalcTotal();
    });
    el('.js-cost').addEventListener('input', function () {
        const ps = num(el('.js-pack-size').value) || 1;
        if (el('.js-pack-cost').value !== '' || ps > 1) el('.js-pack-cost').value = trimNum(num(this.value) * ps);
        recalcTotal();
    });
    el('button').addEventListener('click', function () { tr.remove(); recalcTotals(containerId); refreshMovementHint(); });

    recalcTotal();
    refreshMovementHint();
}

function recalcTotals(containerId) {
    let units = 0, amount = 0;
    document.querySelectorAll('#' + containerId + ' tr.purchase-line').forEach(function (tr) {
        const q = num(tr.querySelector('.js-qty').value);
        units += q;
        amount += q * num(tr.querySelector('.js-cost').value);
    });
    document.querySelectorAll('.js-total-units[data-for="' + containerId + '"]').forEach(function (e) { e.textContent = fmt(units, units % 1 ? 2 : 0); });
    document.querySelectorAll('.js-total-amount[data-for="' + containerId + '"]').forEach(function (e) { e.textContent = fmt(amount); });
}

function locIsPos(id) {
    const loc = locationsMeta.find(function (l) { return String(l.id) === String(id); });
    return loc && loc.is_pos ? true : false;
}

function refreshMovementHint() {
    let locEl = document.getElementById('grvLocation');
    if (!locEl) {
        locEl = document.querySelector('select[name="location_id"]');
    }
    const warn = document.getElementById('moveWarn');
    if (!locEl) return;
    const pos = locIsPos(locEl.value);
    document.querySelectorAll('.js-prod').forEach(function (sel) {
        const opt = sel.options[sel.selectedIndex];
        let info = null;
        if (sel.closest('.purchase-line')) {
            info = sel.closest('.purchase-line').querySelector('.js-line-info');
        } else if (sel.closest('tr')) {
            info = sel.closest('tr').querySelector('.js-line-info');
        }

        const raw = opt && opt.dataset.raw === '1' ? true : false;
        const pid = opt && opt.value ? opt.value : null;

        Array.from(sel.options).forEach(function (o) {
            if (!o.value) return;
            const blocked = pos && o.dataset.raw === '1';
            o.disabled = blocked;
            o.style.color = blocked ? '#aaa' : '';
        });
        if (info) {
            const toggleLnk = pid ? ` <a href="javascript:void(0)" onclick="toggleSellable(${pid}, ${raw ? 0 : 1})" class="text-decoration-none fw-bold ms-3 border-start ps-3"><i class="bi bi-arrow-repeat"></i> Flip POS Visibility</a>` : '';

            if (raw && pos) {
                const rsn = opt.dataset.reason ? opt.dataset.reason : 'Raw food cannot go to a POS location.';
                info.innerHTML = '<span class="badge bg-danger">RAW</span> <i class="bi bi-info-circle text-info ms-1"></i> ' + rsn + toggleLnk;
            } else if (raw) {
                info.innerHTML = '<span class="badge bg-secondary">RAW</span> <i class="bi bi-info-circle text-info ms-1"></i> Raw item — keep in kitchen/warehouse.' + toggleLnk;
            } else if (pid) {
                info.innerHTML = '<span class="badge bg-success">SELLABLE</span> <i class="bi bi-check-circle text-success ms-1"></i> Available for checkout on POS.' + toggleLnk;
            } else {
                info.innerHTML = '';
            }
        }
        if (raw && pos) sel.value = '';
        if (window.SelectSearch) SelectSearch.refresh(sel);
    });
    if (warn) {
        if (pos) {
            warn.classList.remove('d-none');
        } else {
            warn.classList.add('d-none');
        }
        warn.innerHTML = pos
            ? '<i class="bi bi-info-circle"></i> This location is a POS. Raw food lines are disabled. Receive those into kitchen or warehouse instead.'
            : '';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const inquiryBox = document.getElementById('inquiryLines');
    const orderBox = document.getElementById('orderLines');
    const grvBox = document.getElementById('grvLines');
    if (inquiryBox) {
        if (inquiryPrefill.length) {
            inquiryPrefill.forEach(function (r) { addLine('inquiryLines', false, r); });
        } else {
            addLine('inquiryLines');
        }
    }
    if (orderBox) {
        if (orderPrefill.length) {
            if (<?= json_encode($tab === 'orders') ?>) {
                orderPrefill.forEach(function (r) { addLine('orderLines', true, r); });
            }
        } else {
            if (<?= json_encode($tab === 'orders') ?>) {
                addLine('orderLines', true);
            }
        }
    }
    if (grvBox) {
        if (bayPrefillItems.length) {
            bayPrefillItems.forEach(function (r) {
                addLine('grvLines', true, { product_id: r.product_id, quantity: r.qty, unit_cost: r.cost });
            });
        } else if (orderPrefill.length) {
            if (<?= json_encode($tab === 'received') ?>) {
                orderPrefill.forEach(function (r) {
                    const rQty = r.quantity ? r.quantity : 0;
                    const rRec = r.received_qty ? r.received_qty : 0;
                    const remain = Math.max(0, parseFloat(rQty) - parseFloat(rRec));
                    if (remain > 0) {
                        const ps = num(r.pack_size) > 0 ? num(r.pack_size) : 1;
                        addLine('grvLines', true, { product_id: r.product_id, quantity: remain, unit_cost: r.unit_cost, unit: r.unit, pack_size: ps, pack_qty: ps !== 1 ? trimNum(remain / ps) : '', pack_cost: r.pack_cost });
                    }
                });
            }
        } else {
            addLine('grvLines', true);
        }
    }
    let loc = document.getElementById('grvLocation');
    if (!loc) {
        loc = document.querySelector('select[name="location_id"]');
    }
    if (loc) {
        loc.addEventListener('change', refreshMovementHint);
    }
    refreshMovementHint();
});
</script>
