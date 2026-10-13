<?php
$userRole = strtolower($_SESSION['role'] ?? '');
$tab = in_array($tab ?? 'general', ['general', 'surcharges', 'payments', 'currency', 'shop', 'branding', 'email', 'guide'], true) ? $tab : 'general';
$secretMask = !empty($settings['flw_secret_key']) ? substr((string)$settings['flw_secret_key'], 0, 12) . '••••••••' : '';
$tabs = [
    'general' => ['bi-sliders', 'General'],
    'surcharges' => ['bi-plus-slash-minus', 'POS surcharges'],
    'payments' => ['bi-credit-card-2-front', 'Payments (Flutterwave)'],
    'currency' => ['bi-currency-exchange', 'Currency'],
    'shop' => ['bi-bag-heart', 'Customer ordering'],
    'branding' => ['bi-image', 'Logo & banners'],
    'email' => ['bi-envelope', 'Email'],
    'guide' => ['bi-chat-left-text', 'Guide'],
];
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="mb-0">⚙️ System Settings</h3>
</div>
<ul class="nav nav-tabs mb-4 flex-nowrap overflow-auto">
    <?php foreach ($tabs as $key => [$icon, $label]): ?>
    <li class="nav-item"><a class="nav-link fw-bold text-nowrap <?= $tab === $key ? 'active' : '' ?>" href="index.php?page=settings&tab=<?= $key ?>"><i class="bi <?= $icon ?>"></i> <?= $label ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'general'): ?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm border-0 border-top border-4 border-dark">
            <div class="card-body p-4">
                <form method="POST" action="index.php?page=settings">
                    <input type="hidden" name="tab" value="general">
                    <h5 class="fw-bold mb-3 text-secondary border-bottom pb-2">Business & Receipts</h5>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">BUSINESS NAME</label>
                        <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($settings['business_name'] ?? '') ?>" placeholder="e.g. Odelia POS">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">RECEIPT HEADER (Optional)</label>
                        <textarea name="receipt_header" class="form-control" rows="3" placeholder="Tax Number, Slogan, etc."><?= htmlspecialchars($settings['receipt_header'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">RECEIPT FOOTER</label>
                        <textarea name="receipt_footer" class="form-control" rows="2" placeholder="Thank you for your business!"><?= htmlspecialchars($settings['receipt_footer'] ?? '') ?></textarea>
                    </div>
                    <h5 class="fw-bold mb-3 text-secondary border-bottom pb-2 mt-4">Site inspection</h5>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="vsias_enabled" value="1" id="vsiasEnabled" <?= ($settings['vsias_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="vsiasEnabled">Enable V-SIAS Pro</label>
                        <div class="form-text">When this is off, stock transfers dispatch and receive directly, and the Approvals menu is hidden. Turn it on when store inspections should lock a transfer until V-SIAS signs it.</div>
                    </div>
                    <h5 class="fw-bold mb-3 text-secondary border-bottom pb-2 mt-4">🎨 Color Theme Editor</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Main Header Color</label>
                            <input type="color" name="theme_color" class="form-control form-control-color w-100" value="<?= htmlspecialchars($settings['theme_color'] ?? '#2c2c2c') ?>" title="Choose Header Color">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Accent Color (Borders/Icons)</label>
                            <input type="color" name="theme_accent" class="form-control form-control-color w-100" value="<?= htmlspecialchars($settings['theme_accent'] ?? '#ffc107') ?>" title="Choose Accent Color">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Cart Header Color</label>
                            <input type="color" name="theme_cart" class="form-control form-control-color w-100" value="<?= htmlspecialchars($settings['theme_cart'] ?? '#3e2723') ?>" title="Choose Cart Color">
                        </div>
                    </div>
                    <?php if($userRole === 'dev'): ?>
                    <div class="mt-5 p-4 border border-danger rounded bg-white shadow-sm position-relative">
                        <span class="badge bg-danger position-absolute top-0 start-50 translate-middle px-3 py-2 fs-6 shadow-sm"><i class="bi bi-shield-lock-fill"></i> DEVELOPER CONTROLS</span>
                        <h5 class="fw-bold text-danger mb-3 border-bottom border-danger pb-2"><i class="bi bi-key-fill"></i> License & Trial Management</h5>
                        <div class="alert alert-danger small fw-bold mb-4">
                            <i class="bi bi-exclamation-triangle-fill"></i> A lockout date in the past immediately enables read-only mode.
                        </div>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">LICENSE TIER</label>
                                <select name="license_tier" class="form-select fw-bold border-danger">
                                    <option value="lite" <?= (isset($settings['license_tier']) && $settings['license_tier'] == 'lite') ? 'selected' : '' ?>>Lite (Standard POS)</option>
                                    <option value="pro" <?= (isset($settings['license_tier']) && $settings['license_tier'] == 'pro') ? 'selected' : '' ?>>Pro (Advanced + Tabs)</option>
                                    <option value="pro+" <?= (isset($settings['license_tier']) && in_array($settings['license_tier'], ['pro+', 'hospitality'])) ? 'selected' : '' ?>>Pro+ (Kitchen & Floorplan)</option>
                                    <option value="enterprise" <?= (isset($settings['license_tier']) && $settings['license_tier'] == 'enterprise') ? 'selected' : '' ?>>Enterprise (ZRA & Web Orders)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">SYSTEM LOCKOUT DATE</label>
                                <input type="text" name="lockout_date" class="form-control datepicker fw-bold border-danger text-danger" value="<?= htmlspecialchars($settings['lockout_date'] ?? '') ?>" placeholder="Leave blank for lifetime access" autocomplete="off">
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-dark fw-bold px-5 py-2 shadow-sm"><i class="bi bi-save"></i> Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php elseif ($tab === 'surcharges'): ?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 border-top border-4 border-warning">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-plus-circle text-warning"></i> <span id="scFormTitle">Add a surcharge reason</span></h5>
                <p class="text-muted small">These appear in the <strong>Surcharge</strong> dropdown on the POS payment window — e.g. when a guest decides to pack their food after eating. Set a fixed amount, a percentage of the bill, or leave both at 0 so the cashier types the amount.</p>
                <form method="POST" action="index.php?page=settings" id="scForm">
                    <input type="hidden" name="tab" value="surcharges">
                    <input type="hidden" name="save_surcharge" value="1">
                    <input type="hidden" name="surcharge_id" id="scId" value="">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">REASON SHOWN TO CASHIER</label>
                        <input type="text" name="name" id="scName" class="form-control" required maxlength="120" placeholder="e.g. Take-away packaging">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">FIXED AMOUNT (ZMW)</label>
                            <input type="number" step="0.01" min="0" name="default_amount" id="scAmount" class="form-control" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">OR % OF BILL</label>
                            <input type="number" step="0.01" min="0" max="100" name="default_percent" id="scPercent" class="form-control" value="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-bold small text-muted">SORT ORDER</label>
                            <input type="number" name="sort_order" id="scSort" class="form-control" value="0">
                        </div>
                        <div class="col-6 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" name="is_active" value="1" id="scActive" checked>
                                <label class="form-check-label fw-bold small" for="scActive">Active on POS</label>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button type="submit" class="btn btn-warning fw-bold flex-grow-1"><i class="bi bi-save"></i> Save reason</button>
                        <button type="button" class="btn btn-outline-secondary" onclick="scReset()">Clear</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <?php
                $bulkFormId = 'bulkSurcharges';
                $bulkAction = 'index.php?page=settings';
                $bulkHidden = ['tab' => 'surcharges'];
                $bulkButtons = [
                    ['value' => 'show', 'label' => 'Show on POS', 'class' => 'btn-warning', 'title' => 'Show the selected reasons on the POS?', 'confirm' => 'Show', 'color' => '#ffc107'],
                    ['value' => 'hide', 'label' => 'Hide', 'class' => 'btn-outline-light', 'title' => 'Hide the selected reasons from the POS?', 'confirm' => 'Hide', 'color' => '#6c757d'],
                    ['value' => 'delete', 'label' => 'Remove', 'class' => 'btn-outline-danger', 'title' => 'Remove the selected reasons?', 'text' => 'Past receipts keep the reason text.', 'confirm' => 'Remove', 'color' => '#dc3545'],
                ];
                include __DIR__ . '/partials/bulk_bar.php';
                ?>
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark"><tr><th style="width:36px"><input type="checkbox" class="form-check-input bulk-all" data-bulk-form="bulkSurcharges" title="Select all"></th><th>Reason</th><th class="text-end">Default</th><th class="text-center">Order</th><th class="text-center">Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($surchargeReasons)): ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">No surcharge reasons yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($surchargeReasons as $sr): ?>
                        <tr>
                            <td><input type="checkbox" class="form-check-input bulk-one" name="ids[]" value="<?= (int)$sr['id'] ?>" form="bulkSurcharges"></td>
                            <td class="fw-bold"><?= htmlspecialchars($sr['name']) ?></td>
                            <td class="text-end"><?= (float)$sr['default_percent'] > 0 ? rtrim(rtrim((string)$sr['default_percent'], '0'), '.') . '% of bill' : ((float)$sr['default_amount'] > 0 ? 'ZMW ' . number_format((float)$sr['default_amount'], 2) : '<span class="text-muted">cashier enters</span>') ?></td>
                            <td class="text-center"><?= (int)$sr['sort_order'] ?></td>
                            <td class="text-center"><?= $sr['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Hidden</span>' ?></td>
                            <td class="text-end text-nowrap">
                                <button type="button" class="btn btn-sm btn-outline-primary" onclick='scEdit(<?= json_encode($sr) ?>)'><i class="bi bi-pencil"></i></button>
                                <form method="POST" class="d-inline"><input type="hidden" name="tab" value="surcharges"><input type="hidden" name="toggle_surcharge" value="<?= (int)$sr['id'] ?>"><button class="btn btn-sm btn-outline-secondary" title="Show / hide on POS"><i class="bi bi-eye<?= $sr['is_active'] ? '-slash' : '' ?>"></i></button></form>
                                <form method="POST" class="d-inline js-confirm-form" data-swal-title="Remove this reason?" data-swal-text="Past receipts keep the reason text; it just disappears from the POS dropdown." data-swal-confirm="Remove" data-swal-icon="warning" data-swal-confirm-color="#dc3545"><input type="hidden" name="tab" value="surcharges"><input type="hidden" name="delete_surcharge" value="<?= (int)$sr['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="alert alert-light border small mt-3 mb-0"><i class="bi bi-receipt"></i> Surcharges are added on top of the items (after any member discount), printed on the receipt as "Incl. surcharge (reason)", and sent to ZRA as a standard-rated service line so the Smart Invoice total matches what the customer paid.</div>
    </div>
</div>
<script>
function scEdit(r) { document.getElementById('scFormTitle').textContent = 'Edit surcharge reason'; document.getElementById('scId').value = r.id; document.getElementById('scName').value = r.name; document.getElementById('scAmount').value = r.default_amount; document.getElementById('scPercent').value = r.default_percent; document.getElementById('scSort').value = r.sort_order; document.getElementById('scActive').checked = String(r.is_active) === '1'; document.getElementById('scName').focus(); }
function scReset() { document.getElementById('scFormTitle').textContent = 'Add a surcharge reason'; document.getElementById('scForm').reset(); document.getElementById('scId').value = ''; }
</script>

<?php elseif ($tab === 'payments'): ?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm border-0 border-top border-4 border-primary">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="bi bi-credit-card-2-front text-primary"></i> Flutterwave</h5>
                        <p class="text-muted small mb-3">Adds a <strong>Flutterwave (Card / Mobile Money)</strong> payment method to the POS and online checkout to the customer ordering page. Mobile money prompts go straight to the customer's MTN / Airtel / Zamtel line; cards and bank transfers open the Flutterwave checkout window.</p>
                    </div>
                    <?php if ($flwEnabled): ?><span class="badge <?= $flwTestMode ? 'bg-warning text-dark' : 'bg-success' ?> fs-6"><?= $flwTestMode ? 'TEST MODE' : 'LIVE' ?></span><?php else: ?><span class="badge bg-secondary fs-6">OFF</span><?php endif; ?>
                </div>
                <form method="POST" action="index.php?page=settings">
                    <input type="hidden" name="tab" value="payments">
                    <input type="hidden" name="save_payments" value="1">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="flw_enabled" value="1" id="flwEnabled" <?= ($settings['flw_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="flwEnabled">Enable Flutterwave payments</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">PUBLIC KEY</label>
                        <input type="text" name="flw_public_key" class="form-control font-monospace" value="<?= htmlspecialchars($settings['flw_public_key'] ?? '') ?>" placeholder="FLWPUBK_TEST-xxxxxxxxxxxxxxxx-X" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">SECRET KEY</label>
                        <input type="text" name="flw_secret_key" class="form-control font-monospace" value="<?= htmlspecialchars($secretMask) ?>" placeholder="FLWSECK_TEST-xxxxxxxxxxxxxxxx-X" autocomplete="off">
                        <div class="form-text">Stored server-side only. Leave the masked value as is to keep the current key.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">ENCRYPTION KEY (optional)</label>
                            <input type="text" name="flw_encryption_key" class="form-control font-monospace" value="<?= htmlspecialchars($settings['flw_encryption_key'] ?? '') ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">WEBHOOK SECRET HASH (optional)</label>
                            <input type="text" name="flw_webhook_hash" class="form-control font-monospace" value="<?= htmlspecialchars($settings['flw_webhook_hash'] ?? '') ?>" autocomplete="off">
                            <div class="form-text">Set the same value in the Flutterwave dashboard → Settings → Webhooks. URL: <code><?= htmlspecialchars((!empty($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/')) ?>/api/flutterwave_webhook.php</code></div>
                        </div>
                    </div>
                    <div class="d-flex gap-2 mt-4">
                        <button type="submit" class="btn btn-primary fw-bold px-4"><i class="bi bi-save"></i> Save</button>
                        <button type="button" class="btn btn-outline-primary fw-bold" onclick="flwTest()"><i class="bi bi-plug"></i> Test keys</button>
                        <span id="flwTestResult" class="align-self-center small"></span>
                    </div>
                </form>
                <hr class="my-4">
                <h6 class="fw-bold">How to test on the sandbox</h6>
                <ol class="small text-muted mb-0">
                    <li>In the Flutterwave dashboard switch to <strong>Test mode</strong> and copy the test public and secret keys here. Keys starting with <code>FLWPUBK_TEST</code> / <code>FLWSECK_TEST</code> are detected as sandbox automatically.</li>
                    <li>On the POS choose <strong>Flutterwave</strong> as the payment method. For <em>Card / other</em> use the test card <code>5531 8866 5214 2950</code>, CVV 564, expiry 09/32, PIN 3310, OTP 12345.</li>
                    <li>For <em>Send MoMo prompt</em> enter any Zambian number (e.g. 0977000000); the sandbox opens a simulated approval page instead of pushing to a real handset.</li>
                    <li>The sale only completes once this server verifies the transaction with Flutterwave; the reference is printed on the receipt.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<script>
function flwTest() { const out = document.getElementById('flwTestResult'); out.textContent = 'Testing…'; out.className = 'align-self-center small text-muted'; fetch('index.php?page=flutterwave&action=test', { credentials: 'same-origin' }).then(r => r.json()).then(function (res) { out.textContent = res.msg || ''; out.className = 'align-self-center small fw-bold ' + (res.status === 'ok' ? 'text-success' : 'text-danger'); }).catch(function () { out.textContent = 'Could not reach the server.'; out.className = 'align-self-center small text-danger'; }); }
</script>

<?php elseif ($tab === 'currency'): ?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 border-top border-4 border-success">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-currency-exchange text-success"></i> Currency converter</h5>
                <p class="text-muted small">Live rates against the <strong>Zambian Kwacha (ZMW)</strong> from open.er-api.com, cached and refreshed automatically. The POS shows the amount due in the selected currencies and can accept foreign cash, converting it to kwacha for the drawer and giving change in ZMW.</p>
                <form method="POST" action="index.php?page=settings">
                    <input type="hidden" name="tab" value="currency">
                    <input type="hidden" name="save_currency" value="1">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="fx_enabled" value="1" id="fxEnabled" <?= ($settings['fx_enabled'] ?? '1') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="fxEnabled">Show currency conversion on the POS</label>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">CURRENCIES TO SHOW</label>
                        <select name="fx_display[]" class="form-select" multiple data-placeholder="Pick currencies">
                            <?php foreach ($fxKnown as $code => $name): ?>
                            <option value="<?= $code ?>" data-name="<?= htmlspecialchars($name) ?>" <?= in_array($code, $fxDisplay, true) ? 'selected' : '' ?>><?= $code ?> — <?= htmlspecialchars($name) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">REFRESH EVERY (MINUTES)</label>
                        <input type="number" min="5" name="fx_refresh_minutes" class="form-control" value="<?= (int)($settings['fx_refresh_minutes'] ?? 60) ?>">
                    </div>
                    <label class="form-label fw-bold small text-muted">HOUSE RATES (override the live rate — ZMW per 1 unit)</label>
                    <div id="manualRates">
                        <?php $manualRows = $fxManual ?: ['' => '']; foreach ($manualRows as $code => $rate): ?>
                        <div class="row g-2 mb-2 manual-row">
                            <div class="col-5"><input type="text" name="manual_code[]" class="form-control text-uppercase" maxlength="3" placeholder="USD" value="<?= htmlspecialchars((string)$code) ?>"></div>
                            <div class="col-5"><input type="number" step="0.0001" min="0" name="manual_rate[]" class="form-control" placeholder="e.g. 27.50" value="<?= $rate !== '' ? htmlspecialchars((string)$rate) : '' ?>"></div>
                            <div class="col-2"><button type="button" class="btn btn-outline-danger w-100" onclick="this.closest('.manual-row').remove()"><i class="bi bi-x"></i></button></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="addManualRow()"><i class="bi bi-plus"></i> Add house rate</button>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-success fw-bold px-4"><i class="bi bi-save"></i> Save</button>
                    </div>
                </form>
                <form method="POST" action="index.php?page=settings" class="mt-2">
                    <input type="hidden" name="tab" value="currency"><input type="hidden" name="refresh_rates" value="1">
                    <button class="btn btn-outline-success btn-sm fw-bold"><i class="bi bi-arrow-repeat"></i> Refresh live rates now</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold d-flex justify-content-between align-items-center">
                <span>Current rates (base ZMW)</span>
                <span class="small text-muted"><?= !empty($fxData['updated_at']) ? 'Updated ' . htmlspecialchars((string)$fxData['updated_at']) : 'Not fetched yet' ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($fxData['error'])): ?><div class="alert alert-warning small m-3"><i class="bi bi-wifi-off"></i> <?= htmlspecialchars((string)$fxData['error']) ?>. Showing the last cached rates.</div><?php endif; ?>
                <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light"><tr><th>Currency</th><th class="text-end">1 unit = ZMW</th><th class="text-end">1 ZMW =</th><th>Source</th></tr></thead>
                    <tbody>
                    <?php if (empty($fxData['rates'])): ?><tr><td colspan="4" class="text-center text-muted py-4">No rates yet. Save the settings or refresh.</td></tr><?php endif; ?>
                    <?php foreach ($fxData['rates'] as $code => $r): ?>
                        <tr><td class="fw-bold"><?= htmlspecialchars($code) ?> <span class="fw-normal text-muted small"><?= htmlspecialchars($r['name'] ?? '') ?></span></td><td class="text-end"><?= number_format((float)$r['zmw_per_unit'], 4) ?></td><td class="text-end"><?= number_format((float)$r['rate'], 6) ?> <?= htmlspecialchars($code) ?></td><td><span class="badge <?= $r['source'] === 'manual' ? 'bg-warning text-dark' : 'bg-light text-dark border' ?>"><?= $r['source'] === 'manual' ? 'house rate' : 'live' ?></span></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
            <div class="card-footer bg-white">
                <label class="form-label small fw-bold text-muted mb-1">QUICK CONVERTER</label>
                <div class="input-group flex-wrap">
                    <input type="number" step="0.01" min="0" id="cvZmw" class="form-control" value="100" inputmode="decimal" oninput="cvFromZmw()" placeholder="Kwacha">
                    <span class="input-group-text">ZMW</span>
                    <span class="input-group-text"><i class="bi bi-arrow-left-right"></i></span>
                    <select id="cvCode" class="form-select no-search" style="max-width:110px" onchange="cvFromZmw()"><?php foreach (array_keys($fxData['rates']) as $code): ?><option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($code) ?></option><?php endforeach; ?></select>
                    <input type="number" step="0.01" min="0" id="cvFx" class="form-control" inputmode="decimal" oninput="cvFromFx()" placeholder="Amount">
                </div>
                <div class="small text-muted mt-1">Type either side. The other fills in at the rate above.</div>
            </div>
        </div>
    </div>
</div>
<script>
function addManualRow() { const wrap = document.getElementById('manualRates'); const row = document.createElement('div'); row.className = 'row g-2 mb-2 manual-row'; row.innerHTML = '<div class="col-5"><input type="text" name="manual_code[]" class="form-control text-uppercase" maxlength="3" placeholder="USD"></div><div class="col-5"><input type="number" step="0.0001" min="0" name="manual_rate[]" class="form-control" placeholder="e.g. 27.50"></div><div class="col-2"><button type="button" class="btn btn-outline-danger w-100" onclick="this.closest(\'.manual-row\').remove()"><i class="bi bi-x"></i></button></div>'; wrap.appendChild(row); }
const cvRates = <?= json_encode(array_map(fn($r) => (float)$r['zmw_per_unit'], $fxData['rates'] ?? [])) ?>;
let cvLock = false;
function cvFromZmw() {
    if (cvLock) return;
    const z = parseFloat(cvRates[document.getElementById('cvCode').value]) || 0;
    const zmw = parseFloat(document.getElementById('cvZmw').value) || 0;
    cvLock = true; document.getElementById('cvFx').value = (z > 0 && zmw > 0) ? (Math.round((zmw / z) * 100) / 100).toFixed(2) : ''; cvLock = false;
}
function cvFromFx() {
    if (cvLock) return;
    const z = parseFloat(cvRates[document.getElementById('cvCode').value]) || 0;
    const fx = parseFloat(document.getElementById('cvFx').value) || 0;
    cvLock = true; document.getElementById('cvZmw').value = (z > 0 && fx > 0) ? (Math.round(fx * z * 100) / 100).toFixed(2) : ''; cvLock = false;
}
document.addEventListener('DOMContentLoaded', cvFromZmw);
</script>

<?php elseif ($tab === 'shop'): ?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm border-0 border-top border-4 border-info">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="bi bi-bag-heart text-info"></i> Customer ordering & registration</h5>
                        <p class="text-muted small">A public page where customers register, browse the menu, place pickup or delivery orders and pay online or at the counter. Orders land in the POS under <strong>Web Orders</strong> and on the kitchen screens.</p>
                    </div>
                    <a class="btn btn-outline-info fw-bold text-nowrap" href="index.php?page=shop" target="_blank"><i class="bi bi-box-arrow-up-right"></i> Open page</a>
                </div>
                <form method="POST" action="index.php?page=settings">
                    <input type="hidden" name="tab" value="shop">
                    <input type="hidden" name="save_shop" value="1">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="shop_enabled" value="1" id="shopEnabled" <?= ($settings['shop_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="shopEnabled">Enable the customer ordering page</label>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">ORDERS GO TO LOCATION</label>
                            <select name="shop_location_id" class="form-select">
                                <?php foreach ($locations as $l): ?>
                                <option value="<?= (int)$l['id'] ?>" data-type="<?= htmlspecialchars($l['type'] ?? '') ?>" <?= (int)($settings['shop_location_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?> (<?= htmlspecialchars($l['type'] ?? '') ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">PAGE TITLE</label>
                            <input type="text" name="shop_title" class="form-control" value="<?= htmlspecialchars($settings['shop_title'] ?? '') ?>" placeholder="<?= htmlspecialchars($settings['business_name'] ?? 'Order online') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">WELCOME TEXT</label>
                            <textarea name="shop_intro" class="form-control" rows="2" placeholder="Fresh from our kitchen. Order ahead and skip the queue."><?= htmlspecialchars($settings['shop_intro'] ?? '') ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">CONTACT PHONE SHOWN TO CUSTOMERS</label>
                            <input type="text" name="shop_phone" class="form-control" value="<?= htmlspecialchars($settings['shop_phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">MINIMUM ORDER (ZMW)</label>
                            <input type="number" step="0.01" min="0" name="shop_min_order" class="form-control" value="<?= htmlspecialchars($settings['shop_min_order'] ?? '0') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">DELIVERY FEE (ZMW)</label>
                            <input type="number" step="0.01" min="0" name="shop_delivery_fee" class="form-control" value="<?= htmlspecialchars($settings['shop_delivery_fee'] ?? '0') ?>">
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-4 mt-2">
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="shop_pay_at_counter" value="1" id="shopCounter" <?= ($settings['shop_pay_at_counter'] ?? '1') === '1' ? 'checked' : '' ?>><label class="form-check-label fw-bold small" for="shopCounter">Allow pay at counter / on delivery</label></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="shop_pay_online" value="1" id="shopOnline" <?= ($settings['shop_pay_online'] ?? '1') === '1' ? 'checked' : '' ?>><label class="form-check-label fw-bold small" for="shopOnline">Allow Flutterwave online payment<?= $flwEnabled ? '' : ' <span class="text-danger">(enable Flutterwave first)</span>' ?></label></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="shop_delivery" value="1" id="shopDelivery" <?= ($settings['shop_delivery'] ?? '1') === '1' ? 'checked' : '' ?>><label class="form-check-label fw-bold small" for="shopDelivery">Offer delivery</label></div>
                        </div>
                    </div>
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-info fw-bold px-5 py-2 shadow-sm text-dark"><i class="bi bi-save"></i> Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php elseif ($tab === 'branding'): ?>
<?php
$logoUrl = \App\Helpers\Media::url((string)($settings['logo_path'] ?? ''));
$bannerUrl = \App\Helpers\Media::url((string)($settings['shop_banner_path'] ?? ''));
$promoUrl = \App\Helpers\Media::url((string)($settings['promo_banner_path'] ?? ''));
?>
<div class="row">
    <div class="col-lg-10 mx-auto">
        <?php $libraryImages = $libraryImages ?? []; ?>
        <div class="card shadow-sm border-0 border-top border-4 border-primary mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-images text-primary"></i> Image library</h5>
                <p class="text-muted small mb-3">Upload pictures once, then tick the ones you want. Slideshow can take several. The logo and shop banner use one picture.</p>
                <div class="row g-2 align-items-end">
                    <div class="col-md-8">
                        <label class="form-label fw-bold small text-muted" for="libraryFiles">IMAGES</label>
                        <input type="file" id="libraryFiles" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                        <div class="form-text">Up to 10 images at a time. JPG, PNG, WEBP, or GIF, 2.5 MB each.</div>
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-dark fw-bold w-100" id="libraryUpload"><i class="bi bi-cloud-upload"></i> Upload</button>
                    </div>
                </div>
                <label class="form-check mt-3 mb-0" for="librarySelectAll">
                    <input class="form-check-input" type="checkbox" id="librarySelectAll">
                    <span class="form-check-label small fw-bold" id="librarySelectLabel">Select images</span>
                </label>
                <div id="libraryGrid" class="library-grid mt-2">
                    <?php if (!$libraryImages): ?>
                    <p class="text-muted small mb-0" id="libraryEmpty">No images yet.</p>
                    <?php endif; ?>
                    <?php foreach ($libraryImages as $image): ?>
                    <button type="button" class="library-thumb" data-path="<?= htmlspecialchars($image['path']) ?>"><span class="tick" aria-hidden="true"></span><img src="<?= htmlspecialchars($image['path']) ?>" alt=""></button>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <button type="button" class="btn btn-sm btn-outline-dark" id="libUseLogo">Use as logo</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" id="libUseBanner">Use as shop banner</button>
                    <button type="button" class="btn btn-sm btn-outline-dark" id="libUsePromo">Add to slideshow</button>
                    <button type="button" class="btn btn-sm btn-outline-success" id="libUseItem">Use on menu item</button>
                </div>
                <?php $promoSlides = \App\Helpers\Media::folder('promo'); ?>
                <div id="promoManager" class="border rounded p-3 mt-3 bg-light d-none">
                    <div class="fw-bold small text-muted">SLIDESHOW</div>
                    <p class="form-text mb-2">These pictures scroll across the top of the landing page when no event is published. Remove or crop one here. It saves straight away.</p>
                    <div class="row g-2 align-items-end">
                        <div class="col-md-8">
                            <input type="file" id="promoAddFiles" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/gif" multiple>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-sm btn-dark w-100" id="promoAddBtn">Add pictures</button>
                        </div>
                    </div>
                    <div id="promoSlideGrid" class="d-flex flex-wrap gap-2 mt-3"></div>
                    <div id="promoCropBox" class="d-none mt-3">
                        <div class="rounded overflow-hidden border bg-dark" style="max-height:360px;">
                            <img id="promoCropImage" alt="" style="display:block;max-width:100%;">
                        </div>
                        <div class="d-flex gap-2 mt-2">
                            <button type="button" class="btn btn-sm btn-dark" id="promoCropSave">Save crop</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="promoCropCancel">Cancel</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
            .library-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(96px, 1fr)); gap: 8px; max-height: 380px; overflow: auto; }
            .library-thumb { position: relative; border: 2px solid transparent; border-radius: 12px; padding: 0; background: #f6efe6; overflow: hidden; aspect-ratio: 1; }
            .library-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
            .library-thumb .tick { position: absolute; top: 6px; left: 6px; width: 18px; height: 18px; border-radius: 4px; border: 2px solid #fff; background: rgba(0,0,0,.35); box-shadow: 0 1px 3px rgba(0,0,0,.35); }
            .library-thumb.active { border-color: #111; }
            .library-thumb.active .tick { background: #111; }
            .library-thumb.active .tick::after { content: ""; position: absolute; left: 4px; top: 1px; width: 5px; height: 9px; border: solid #f6c90e; border-width: 0 2px 2px 0; transform: rotate(45deg); }
        </style>
        <div class="card shadow-sm border-0 border-top border-4 border-warning">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-image text-warning"></i> Logo, banners, and shop photos</h5>
                <p class="text-muted small">Administrators and managers can replace these here. Use JPG, PNG, WEBP, or GIF, up to 2.5 MB.</p>
                <form method="POST" action="index.php?page=settings" enctype="multipart/form-data">
                    <input type="hidden" name="tab" value="branding">
                    <input type="hidden" name="save_branding" value="1">
                    <div class="row g-3">
                        <?php foreach ([
                            ['LOGO', 'Menu bar, receipts, sign-in, and the online shop mark.', 'logo', $logoUrl],
                            ['SHOP BANNER', 'The large photo on the shop landing page.', 'shop_banner', $bannerUrl],
                        ] as [$label, $help, $field, $url]): ?>
                        <div class="col-md-4">
                            <div class="border rounded p-3 h-100 bg-white">
                                <label class="form-label fw-bold small text-muted"><?= $label ?></label>
                                <div id="brandPreview-<?= htmlspecialchars($field) ?>" class="mb-2 rounded d-flex align-items-center justify-content-center" style="height:120px;background:#f6efe6;overflow:hidden;">
                                    <?php if ($url !== ''): ?>
                                        <img src="<?= htmlspecialchars($url) ?>" alt="" style="max-width:100%;max-height:120px;object-fit:contain;">
                                    <?php else: ?>
                                        <span class="text-muted small">No image yet</span>
                                    <?php endif; ?>
                                </div>
                                <input type="file" name="<?= $field ?>" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/gif">
                                <div class="form-text"><?= htmlspecialchars($help) ?></div>
                                <?php if ($url !== ''): ?>
                                <div class="form-check mt-2">
                                    <input class="form-check-input" type="checkbox" name="remove_<?= $field ?>" value="1" id="remove_<?= $field ?>">
                                    <label class="form-check-label small" for="remove_<?= $field ?>">Remove current image</label>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="col-12">
                            <p class="form-text mb-0">The landing slideshow is managed with <strong>Add to slideshow</strong> above. Each picture has its own remove and crop buttons.</p>
                        </div>
                    </div>
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-dark fw-bold px-5 py-2 shadow-sm"><i class="bi bi-save"></i> Save images</button>
                    </div>
                </form>
            </div>
        </div>

        <?php
        $menuPhotoMap = [];
        $menuPhotoCatById = [];
        $menuPhotoCats = [];
        foreach ($menuPhotos as $item) {
            $photoId = (string)(int)$item['id'];
            $photoCat = (string)$item['cat_name'];
            $menuPhotoMap[$photoId] = \App\Helpers\Media::url((string)($item['image_path'] ?? ''));
            $menuPhotoCatById[$photoId] = $photoCat;
            $menuPhotoCats[$photoCat] = ($menuPhotoCats[$photoCat] ?? 0) + 1;
        }
        ?>
        <div class="card shadow-sm border-0 border-top border-4 border-success mt-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-image-fill text-success"></i> Menu item photo</h5>
                <p class="text-muted small">Filter the menu, choose a dish or drink, frame the picture, then save. The preview matches the online menu.</p>
                <div class="row g-4">
                    <div class="col-lg-7">
                        <div class="row g-2">
                            <div class="col-sm-7">
                                <label class="form-label fw-bold small text-muted" for="menuPhotoCategory">CATEGORY</label>
                                <select id="menuPhotoCategory" class="form-select">
                                    <option value="">All categories</option>
                                    <?php foreach ($menuPhotoCats as $photoCat => $photoCount): ?>
                                    <option value="<?= htmlspecialchars($photoCat) ?>"><?= htmlspecialchars($photoCat) ?> (<?= (int)$photoCount ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-sm-5">
                                <label class="form-label fw-bold small text-muted" for="menuPhotoStatus">PHOTO</label>
                                <select id="menuPhotoStatus" class="form-select no-search">
                                    <option value="">All items</option>
                                    <option value="yes">Has a photo</option>
                                    <option value="no">Needs a photo</option>
                                </select>
                            </div>
                        </div>
                        <label class="form-label fw-bold small text-muted mt-3" for="menuPhotoItem">MENU ITEM</label>
                        <select id="menuPhotoItem" class="form-select">
                            <option value="">Select a menu item…</option>
                            <?php foreach ($menuPhotos as $item): ?>
                            <option value="<?= (int)$item['id'] ?>"><?= htmlspecialchars((string)$item['cat_name']) ?> — <?= htmlspecialchars((string)$item['name']) ?> · ZMW <?= number_format((float)$item['price'], 2) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$menuPhotos): ?>
                        <div class="form-text">No priced items yet. Add one under Products &amp; Menu first.</div>
                        <?php endif; ?>
                        <label class="form-label fw-bold small text-muted mt-3" for="menuPhotoFile">PHOTO</label>
                        <input type="file" id="menuPhotoFile" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif">
                        <div class="form-text">Drag inside the frame to position the dish. Scroll or use the buttons to zoom.</div>
                        <div id="menuCropTools" class="d-none mt-2">
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="menuCropOut"><i class="bi bi-zoom-out"></i> Zoom out</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="menuCropIn"><i class="bi bi-zoom-in"></i> Zoom in</button>
                            </div>
                        </div>
                        <div id="menuCropStage" class="d-none mt-3 rounded overflow-hidden border" style="background:#1c2128;max-height:420px;">
                            <img id="menuCropImage" alt="" style="display:block;max-width:100%;">
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <label class="form-label fw-bold small text-muted">PREVIEW ON THE MENU</label>
                        <div id="menuPhotoPreview" class="rounded overflow-hidden border d-flex align-items-end" style="aspect-ratio:2/1;background:#f6efe6 center/cover no-repeat;">
                            <span id="menuPhotoEmpty" class="text-muted small p-3">Select an item to see its photo.</span>
                        </div>
                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <button type="button" class="btn btn-success fw-bold" id="menuPhotoSave" disabled><i class="bi bi-save"></i> Save photo</button>
                            <button type="button" class="btn btn-outline-danger" id="menuPhotoRemove" disabled>Remove photo</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<link rel="stylesheet" href="vendor/cropperjs/cropper.min.css">
<script src="vendor/cropperjs/cropper.min.js"></script>
<script>
function pingShopFace() {
    try { new BroadcastChannel('smartrade-shop').postMessage('picture'); } catch (e) {}
}
(function () {
    var photos = <?= json_encode($menuPhotoMap, JSON_UNESCAPED_UNICODE) ?>;
    var itemCats = <?= json_encode($menuPhotoCatById, JSON_UNESCAPED_UNICODE) ?>;
    var select = document.getElementById('menuPhotoItem');
    var categorySelect = document.getElementById('menuPhotoCategory');
    var statusSelect = document.getElementById('menuPhotoStatus');
    var fileInput = document.getElementById('menuPhotoFile');
    var stage = document.getElementById('menuCropStage');
    var image = document.getElementById('menuCropImage');
    var tools = document.getElementById('menuCropTools');
    var preview = document.getElementById('menuPhotoPreview');
    var empty = document.getElementById('menuPhotoEmpty');
    var saveBtn = document.getElementById('menuPhotoSave');
    var removeBtn = document.getElementById('menuPhotoRemove');
    var cropper = null;
    var previewTimer = null;

    function selectedId() {
        return select ? String(select.value || '') : '';
    }

    function itemVisible(id) {
        if (!id) return true;
        var cat = categorySelect ? categorySelect.value : '';
        var status = statusSelect ? statusSelect.value : '';
        if (cat && itemCats[id] !== cat) return false;
        var has = !!photos[id];
        if (status === 'yes' && !has) return false;
        if (status === 'no' && has) return false;
        return true;
    }

    var menuItems = Array.prototype.map.call(select.options, function (opt) {
        return { value: opt.value, text: opt.text };
    });

    function rebuildMenuItems() {
        var current = selectedId();
        var keep = current && itemVisible(current);
        if (select.tomselect) select.tomselect.destroy();
        select.innerHTML = '';
        menuItems.forEach(function (opt) {
            if (opt.value && !itemVisible(opt.value)) return;
            var el = document.createElement('option');
            el.value = opt.value;
            el.textContent = opt.text;
            select.appendChild(el);
        });
        if (keep) {
            select.value = current;
        } else {
            select.value = '';
            if (current) {
                clearCrop();
                showSaved('');
            }
        }
        if (window.SelectSearch) SelectSearch.init(select);
    }

    if (categorySelect) categorySelect.addEventListener('change', rebuildMenuItems);
    if (statusSelect) statusSelect.addEventListener('change', rebuildMenuItems);

    function showSaved(url) {
        if (url) {
            preview.style.backgroundImage = "url('" + String(url).replace(/'/g, '%27') + "')";
            empty.classList.add('d-none');
        } else {
            preview.style.backgroundImage = '';
            empty.textContent = selectedId() ? 'No photo yet. Choose one and frame it.' : 'Select an item to see its photo.';
            empty.classList.remove('d-none');
        }
        removeBtn.disabled = !selectedId() || !url;
    }

    function clearCrop() {
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        image.removeAttribute('src');
        stage.classList.add('d-none');
        tools.classList.add('d-none');
        saveBtn.disabled = true;
        fileInput.value = '';
    }

    function paintLivePreview() {
        if (!cropper) return;
        var canvas = cropper.getCroppedCanvas({ width: 640, height: 320, imageSmoothingQuality: 'high' });
        if (!canvas) return;
        preview.style.backgroundImage = "url('" + canvas.toDataURL('image/jpeg', 0.85) + "')";
        empty.classList.add('d-none');
    }

    select.addEventListener('change', function () {
        clearCrop();
        showSaved(photos[selectedId()] || '');
    });

    fileInput.addEventListener('change', function () {
        var file = fileInput.files && fileInput.files[0];
        if (!file) return;
        if (!selectedId()) {
            fileInput.value = '';
            LiveRow.toast({ status: 'error', msg: 'Select a menu item first.' });
            return;
        }
        if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) {
            fileInput.value = '';
            LiveRow.toast({ status: 'error', msg: 'Use a JPG, PNG, WEBP, or GIF image.' });
            return;
        }
        if (cropper) {
            cropper.destroy();
            cropper = null;
        }
        var reader = new FileReader();
        reader.onload = function () {
            image.onload = function () {
                stage.classList.remove('d-none');
                tools.classList.remove('d-none');
                if (cropper) cropper.destroy();
                cropper = new Cropper(image, {
                    aspectRatio: 2,
                    viewMode: 1,
                    dragMode: 'move',
                    autoCropArea: 1,
                    background: false,
                    responsive: true,
                    crop: function () {
                        clearTimeout(previewTimer);
                        previewTimer = setTimeout(paintLivePreview, 60);
                    }
                });
                saveBtn.disabled = false;
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(file);
    });

    document.getElementById('menuCropIn').addEventListener('click', function () {
        if (cropper) cropper.zoom(0.1);
    });
    document.getElementById('menuCropOut').addEventListener('click', function () {
        if (cropper) cropper.zoom(-0.1);
    });

    function postPhoto(body) {
        saveBtn.disabled = true;
        removeBtn.disabled = true;
        return fetch('index.php?page=settings', {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
        }).then(function (response) { return LiveRow.parseJson(response); }).then(function (data) {
            if (!data || data.status !== 'success') {
                throw new Error((data && (data.msg || data.message)) || 'Unable to save the photo.');
            }
            return data;
        }).finally(function () {
            saveBtn.disabled = !cropper;
            removeBtn.disabled = !selectedId() || !photos[selectedId()];
        });
    }

    saveBtn.addEventListener('click', function () {
        if (!cropper || !selectedId()) return;
        var canvas = cropper.getCroppedCanvas({ width: 1400, height: 700, imageSmoothingQuality: 'high' });
        if (!canvas) return;
        canvas.toBlob(function (blob) {
            if (!blob) {
                LiveRow.toast({ status: 'error', msg: 'The cropped photo could not be prepared.' });
                return;
            }
            var body = new FormData();
            body.set('tab', 'branding');
            body.set('save_product_photo', '1');
            body.set('ajax', '1');
            body.set('product_id', selectedId());
            body.set('product_photo', blob, 'menu-photo.jpg');
            postPhoto(body).then(function (data) {
                photos[String(data.id)] = data.image || '';
                clearCrop();
                showSaved(data.image ? data.image + '?v=' + Date.now() : '');
                rebuildMenuItems();
                if (data.image) document.dispatchEvent(new CustomEvent('library-add', { detail: { path: String(data.image).split('?')[0] } }));
                LiveRow.toast(data);
                pingShopFace();
            }).catch(function (err) {
                LiveRow.toast({ status: 'error', msg: err.message });
            });
        }, 'image/jpeg', 0.9);
    });

    document.addEventListener('menu-photo-assigned', function (event) {
        var detail = event.detail || {};
        var id = String(detail.id || '');
        if (!id) return;
        photos[id] = detail.image || '';
        if (select.tomselect) select.tomselect.setValue(id, true);
        else select.value = id;
        clearCrop();
        showSaved(detail.image ? detail.image + '?v=' + Date.now() : '');
        rebuildMenuItems();
    });

    removeBtn.addEventListener('click', function () {
        if (!selectedId() || !photos[selectedId()]) return;
        AppAlert.confirm({
            title: 'Remove this photo?',
            text: 'The menu item stays. Only the picture is removed.',
            confirmText: 'Remove',
            confirmColor: '#dc3545',
            icon: 'warning'
        }).then(function (ok) {
            if (!ok) return;
            var body = new FormData();
            body.set('tab', 'branding');
            body.set('save_product_photo', '1');
            body.set('ajax', '1');
            body.set('remove_product_photo', '1');
            body.set('product_id', selectedId());
            postPhoto(body).then(function (data) {
                photos[String(data.id)] = '';
                clearCrop();
                showSaved('');
                rebuildMenuItems();
                LiveRow.toast(data);
                pingShopFace();
            }).catch(function (err) {
                LiveRow.toast({ status: 'error', msg: err.message });
            });
        });
    });
})();
</script>
<script>
(function () {
    var grid = document.getElementById('libraryGrid');
    var fileInput = document.getElementById('libraryFiles');
    var uploadBtn = document.getElementById('libraryUpload');
    var selected = [];
    var selectAll = document.getElementById('librarySelectAll');
    var selectLabel = document.getElementById('librarySelectLabel');
    var useButtons = [
        ['libUseLogo', 'logo'],
        ['libUseBanner', 'shop_banner'],
        ['libUsePromo', 'promo_slide'],
        ['libUseItem', 'product']
    ];

    function toast(data) {
        if (window.LiveRow) LiveRow.toast(data);
    }

    function thumbs() {
        return Array.prototype.slice.call(grid.querySelectorAll('.library-thumb'));
    }

    function syncSelection() {
        var boxes = thumbs();
        selected = boxes.filter(function (el) { return el.classList.contains('active'); }).map(function (el) { return el.dataset.path || ''; }).filter(Boolean);
        useButtons.forEach(function (pair) {
            var el = document.getElementById(pair[0]);
            if (el) el.disabled = false;
        });
        if (selectAll) {
            selectAll.checked = boxes.length > 0 && selected.length === boxes.length;
            selectAll.indeterminate = selected.length > 0 && selected.length < boxes.length;
            selectAll.disabled = boxes.length === 0;
        }
        if (selectLabel) {
            selectLabel.textContent = selected.length ? selected.length + ' selected' : 'Select images';
        }
    }

    function toggleThumb(button) {
        button.classList.toggle('active');
        button.setAttribute('aria-pressed', button.classList.contains('active') ? 'true' : 'false');
        syncSelection();
    }

    function thumb(path) {
        var button = document.createElement('button');
        button.type = 'button';
        button.className = 'library-thumb';
        button.dataset.path = path;
        button.setAttribute('aria-pressed', 'false');
        var tick = document.createElement('span');
        tick.className = 'tick';
        tick.setAttribute('aria-hidden', 'true');
        var img = document.createElement('img');
        img.src = path;
        img.alt = '';
        button.appendChild(tick);
        button.appendChild(img);
        button.addEventListener('click', function () { toggleThumb(button); });
        return button;
    }

    function paint(images) {
        var empty = document.getElementById('libraryEmpty');
        if (empty) empty.remove();
        grid.innerHTML = '';
        if (!images.length) {
            grid.innerHTML = '<p class="text-muted small mb-0" id="libraryEmpty">No images yet.</p>';
            syncSelection();
            return;
        }
        images.forEach(function (image) {
            grid.appendChild(thumb(image.path));
        });
        syncSelection();
    }

    function addPath(path) {
        path = String(path || '').split('?')[0];
        if (!path || grid.querySelector('[data-path="' + CSS.escape(path) + '"]')) return;
        var empty = document.getElementById('libraryEmpty');
        if (empty) empty.remove();
        grid.insertBefore(thumb(path), grid.firstChild);
    }

    grid.querySelectorAll('.library-thumb').forEach(function (button) {
        button.addEventListener('click', function () { toggleThumb(button); });
    });
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            var on = selectAll.checked;
            thumbs().forEach(function (button) {
                button.classList.toggle('active', on);
                button.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            syncSelection();
        });
    }
    syncSelection();

    document.addEventListener('library-add', function (event) {
        addPath(event.detail && event.detail.path);
    });

    function post(body) {
        return fetch('index.php?page=settings', {
            method: 'POST',
            body: body,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', Accept: 'application/json' }
        }).then(function (response) { return LiveRow.parseJson(response); }).then(function (data) {
            if (!data || data.status !== 'success') {
                throw new Error((data && (data.msg || data.message)) || 'Unable to save the image.');
            }
            return data;
        });
    }

    uploadBtn.addEventListener('click', function () {
        var files = fileInput.files;
        if (!files || !files.length) {
            toast({ status: 'error', msg: 'Choose at least one image.' });
            return;
        }
        if (files.length > 10) {
            toast({ status: 'error', msg: 'Upload up to 10 images at a time.' });
            return;
        }
        var body = new FormData();
        body.set('tab', 'branding');
        body.set('save_library', '1');
        body.set('ajax', '1');
        Array.prototype.forEach.call(files, function (file) {
            body.append('library_images[]', file);
        });
        uploadBtn.disabled = true;
        post(body).then(function (data) {
            paint(data.images || []);
            fileInput.value = '';
            toast(data);
        }).catch(function (err) {
            toast({ status: 'error', msg: err.message });
        }).finally(function () {
            uploadBtn.disabled = false;
        });
    });

    var promoSlides = <?= json_encode($promoSlides ?? [], JSON_UNESCAPED_UNICODE) ?>;
    var promoPanel = document.getElementById('promoManager');
    var promoGrid = document.getElementById('promoSlideGrid');
    var promoCropBox = document.getElementById('promoCropBox');
    var promoCropImage = document.getElementById('promoCropImage');
    var promoCropper = null;
    var promoCropPath = '';

    function closePromoCrop() {
        if (promoCropper) {
            promoCropper.destroy();
            promoCropper = null;
        }
        promoCropPath = '';
        if (promoCropBox) promoCropBox.classList.add('d-none');
    }

    function paintSlides(slides) {
        promoSlides = slides || [];
        if (!promoGrid) return;
        promoGrid.innerHTML = '';
        if (!promoSlides.length) {
            promoGrid.innerHTML = '<p class="text-muted small mb-0">No slideshow pictures yet.</p>';
            return;
        }
        promoSlides.forEach(function (path) {
            var card = document.createElement('div');
            card.className = 'border rounded p-1 bg-white';
            card.style.width = '148px';
            var img = document.createElement('img');
            img.src = path + '?v=' + Date.now();
            img.alt = '';
            img.style.cssText = 'width:100%;height:84px;object-fit:cover;display:block;border-radius:8px;';
            var actions = document.createElement('div');
            actions.className = 'd-flex gap-1 mt-1';
            var crop = document.createElement('button');
            crop.type = 'button';
            crop.className = 'btn btn-sm btn-outline-dark flex-fill';
            crop.textContent = 'Crop';
            crop.addEventListener('click', function () { startPromoCrop(path); });
            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'btn btn-sm btn-outline-danger flex-fill';
            remove.textContent = 'Remove';
            remove.addEventListener('click', function () { removePromo(path, remove); });
            actions.appendChild(crop);
            actions.appendChild(remove);
            card.appendChild(img);
            card.appendChild(actions);
            promoGrid.appendChild(card);
        });
    }

    function showPromo() {
        if (!promoPanel) return;
        promoPanel.classList.remove('d-none');
        promoPanel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function removePromo(path, button) {
        button.disabled = true;
        var body = new FormData();
        body.set('tab', 'branding');
        body.set('ajax', '1');
        body.set('remove_promo_slide_now', '1');
        body.set('path', path);
        post(body).then(function (data) {
            if (promoCropPath === path) closePromoCrop();
            paintSlides(data.slides || []);
            toast(data);
            pingShopFace();
        }).catch(function (err) {
            button.disabled = false;
            toast({ status: 'error', msg: err.message });
        });
    }

    function startPromoCrop(path) {
        if (!window.Cropper || !promoCropImage) return;
        closePromoCrop();
        promoCropPath = path;
        promoCropImage.src = path + '?v=' + Date.now();
        promoCropBox.classList.remove('d-none');
        promoCropImage.onload = function () {
            promoCropper = new Cropper(promoCropImage, {
                aspectRatio: 16 / 9,
                viewMode: 1,
                dragMode: 'move',
                autoCropArea: 1
            });
        };
    }

    function apply(target) {
        if (target === 'promo_slide') {
            if (!selected.length && !promoSlides.length) {
                showPromo();
                toast({ status: 'error', msg: 'Tick pictures to add, or use Add pictures in the slideshow.' });
                return;
            }
            showPromo();
            if (!selected.length) return;
        } else if (!selected.length) {
            toast({ status: 'error', msg: 'Tick one or more images first.' });
            return;
        }
        if (target !== 'promo_slide' && selected.length > 1) {
            toast({ status: 'error', msg: 'Choose one image for that. The slideshow can take several.' });
            return;
        }
        if (target === 'product') {
            var item = document.getElementById('menuPhotoItem');
            if (!item || !item.value) {
                toast({ status: 'error', msg: 'Select a menu item first.' });
                return;
            }
        }
        var body = new FormData();
        body.set('tab', 'branding');
        body.set('use_library_image', '1');
        body.set('ajax', '1');
        body.set('target', target);
        selected.forEach(function (path) { body.append('image_paths[]', path); });
        if (target === 'product') body.set('product_id', document.getElementById('menuPhotoItem').value);
        post(body).then(function (data) {
            if (target === 'product') {
                document.dispatchEvent(new CustomEvent('menu-photo-assigned', { detail: data }));
            } else {
                var box = document.getElementById('brandPreview-' + (target === 'logo' ? 'logo' : target));
                if (box) {
                    box.innerHTML = '';
                    var img = document.createElement('img');
                    img.src = data.image + '?v=' + Date.now();
                    img.alt = '';
                    img.style.maxWidth = '100%';
                    img.style.maxHeight = '120px';
                    img.style.objectFit = 'contain';
                    box.appendChild(img);
                }
            }
            if (target === 'promo_slide') paintSlides(data.slides || promoSlides);
            toast(data);
            pingShopFace();
        }).catch(function (err) {
            toast({ status: 'error', msg: err.message });
        });
    }

    paintSlides(promoSlides);
    var promoAddBtn = document.getElementById('promoAddBtn');
    var promoAddFiles = document.getElementById('promoAddFiles');
    if (promoAddBtn) {
        promoAddBtn.addEventListener('click', function () {
            var files = promoAddFiles ? promoAddFiles.files : null;
            if (!files || !files.length) {
                toast({ status: 'error', msg: 'Choose at least one image.' });
                return;
            }
            var body = new FormData();
            body.set('tab', 'branding');
            body.set('ajax', '1');
            body.set('add_promo_files', '1');
            Array.prototype.forEach.call(files, function (file) { body.append('promo_files[]', file); });
            promoAddBtn.disabled = true;
            post(body).then(function (data) {
                if (promoAddFiles) promoAddFiles.value = '';
                paintSlides(data.slides || []);
                showPromo();
                toast(data);
                pingShopFace();
            }).catch(function (err) {
                toast({ status: 'error', msg: err.message });
            }).finally(function () {
                promoAddBtn.disabled = false;
            });
        });
    }
    var promoCropSave = document.getElementById('promoCropSave');
    var promoCropCancel = document.getElementById('promoCropCancel');
    if (promoCropCancel) promoCropCancel.addEventListener('click', closePromoCrop);
    if (promoCropSave) {
        promoCropSave.addEventListener('click', function () {
            if (!promoCropper || !promoCropPath) return;
            promoCropper.getCroppedCanvas({ width: 1400, height: 788 }).toBlob(function (blob) {
                if (!blob) {
                    toast({ status: 'error', msg: 'The crop could not be saved.' });
                    return;
                }
                var body = new FormData();
                body.set('tab', 'branding');
                body.set('ajax', '1');
                body.set('crop_promo_slide', '1');
                body.set('path', promoCropPath);
                body.append('promo_crop', blob, 'slide.jpg');
                promoCropSave.disabled = true;
                post(body).then(function (data) {
                    closePromoCrop();
                    paintSlides(data.slides || []);
                    toast(data);
                    pingShopFace();
                }).catch(function (err) {
                    toast({ status: 'error', msg: err.message });
                }).finally(function () {
                    promoCropSave.disabled = false;
                });
            }, 'image/jpeg', 0.9);
        });
    }

    useButtons.forEach(function (pair) {
        var el = document.getElementById(pair[0]);
        if (el) el.addEventListener('click', function () { apply(pair[1]); });
    });
})();
</script>
<?php elseif ($tab === 'email'): ?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm border-0 border-top border-4 border-primary">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-envelope text-primary"></i> Invitation email</h5>
                <p class="text-muted small">Event invitations are sent from this mailbox. Leave the password blank to keep the one already saved. Until the host and from address are filled in, guest links are still created and the send is recorded as not sent.</p>
                <form method="POST" action="index.php?page=settings">
                    <input type="hidden" name="tab" value="email">
                    <input type="hidden" name="save_email" value="1">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted">SMTP HOST</label>
                            <input name="smtp_host" class="form-control" value="<?= htmlspecialchars((string)($settings['smtp_host'] ?? '')) ?>" placeholder="smtp.example.com">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">PORT</label>
                            <input type="number" name="smtp_port" class="form-control" value="<?= htmlspecialchars((string)($settings['smtp_port'] ?? '587')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">USERNAME</label>
                            <input name="smtp_user" class="form-control" value="<?= htmlspecialchars((string)($settings['smtp_user'] ?? '')) ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">PASSWORD</label>
                            <input type="password" name="smtp_pass" class="form-control" value="" placeholder="<?= !empty($settings['smtp_pass']) ? 'Saved. Leave blank to keep it.' : 'Mailbox password' ?>" autocomplete="new-password">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">FROM ADDRESS</label>
                            <input type="email" name="smtp_from" class="form-control" value="<?= htmlspecialchars((string)($settings['smtp_from'] ?? '')) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">FROM NAME</label>
                            <input name="smtp_from_name" class="form-control" value="<?= htmlspecialchars((string)($settings['smtp_from_name'] ?? ($settings['business_name'] ?? ''))) ?>">
                        </div>
                    </div>
                    <div class="text-end mt-4">
                        <button class="btn btn-dark fw-bold px-5"><i class="bi bi-save"></i> Save email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php elseif ($tab === 'guide'):
    $guideModel = (string)($settings['gemini_model'] ?? 'gemini-2.5-flash');
    $guideModels = \App\Services\Guide::models();
    if (!isset($guideModels[$guideModel])) {
        $guideModel = 'gemini-2.5-flash';
    }
?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm border-0 border-top border-4 border-warning">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-chat-left-text text-warning"></i> Staff guide</h5>
                <p class="text-muted small">The Guide button on back-office pages answers how-to questions from the written manuals. The key stays on this server. Leave it blank to keep the one already saved.</p>
                <form method="POST" action="index.php?page=settings">
                    <input type="hidden" name="tab" value="guide">
                    <input type="hidden" name="save_guide" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">GEMINI API KEY</label>
                        <input type="password" name="gemini_api_key" class="form-control" value="" placeholder="<?= !empty($settings['gemini_api_key']) ? 'Saved. Leave blank to keep it.' : 'Paste the Gemini API key' ?>" autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">MODEL</label>
                        <select name="gemini_model" class="form-select">
                            <?php foreach ($guideModels as $id => $label): ?>
                            <option value="<?= htmlspecialchars($id) ?>" <?= $guideModel === $id ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="text-end mt-4">
                        <button class="btn btn-dark fw-bold px-5"><i class="bi bi-save"></i> Save guide</button>
                    </div>
                </form>
                <?php if ($guideReady = \App\Services\Guide::ready($settings)): ?>
                <div class="mt-4 border-top pt-3">
                    <h6 class="fw-bold"><i class="bi bi-lightning-charge text-warning"></i> Context Caching</h6>
                    <p class="text-muted small mb-3">Sync the manuals to Gemini's context cache. This makes answers faster and saves tokens. Sync again when the manuals are updated.</p>
                    <div class="d-flex align-items-center gap-3">
                        <button type="button" class="btn btn-outline-secondary" id="syncGuideBtn">
                            <i class="bi bi-arrow-repeat"></i> Sync Documentation
                        </button>
                        <span id="syncGuideStatus" class="small text-muted">
                            <?= empty($settings['gemini_cache_name']) ? 'Not synced yet.' : 'Cache ID: ' . htmlspecialchars((string)$settings['gemini_cache_name']) ?>
                        </span>
                    </div>
                </div>
                <script>
                document.getElementById('syncGuideBtn').addEventListener('click', function () {
                    var btn = this;
                    var status = document.getElementById('syncGuideStatus');
                    btn.disabled = true;
                    status.textContent = 'Syncing to Gemini...';
                    var body = new FormData();
                    body.set('ajax', '1');
                    body.set('tab', 'guide');
                    body.set('sync_guide', '1');
                    fetch('index.php?page=settings', { method: 'POST', body: body })
                        .then(function (res) { return res.json(); })
                        .then(function (data) {
                            status.textContent = data.msg || 'Sync complete.';
                            status.className = data.status === 'success' ? 'small text-success fw-bold' : 'small text-danger';
                        })
                        .catch(function () {
                            status.textContent = 'Network error during sync.';
                            status.className = 'small text-danger';
                        })
                        .finally(function () {
                            btn.disabled = false;
                        });
                });
                </script>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
