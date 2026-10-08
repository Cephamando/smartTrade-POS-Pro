<?php
$userRole = strtolower($_SESSION['role'] ?? '');
$tab = in_array($tab ?? 'general', ['general', 'surcharges', 'payments', 'currency', 'shop'], true) ? $tab : 'general';
$secretMask = !empty($settings['flw_secret_key']) ? substr((string)$settings['flw_secret_key'], 0, 12) . '••••••••' : '';
$tabs = [
    'general' => ['bi-sliders', 'General'],
    'surcharges' => ['bi-plus-slash-minus', 'POS surcharges'],
    'payments' => ['bi-credit-card-2-front', 'Payments (Flutterwave)'],
    'currency' => ['bi-currency-exchange', 'Currency'],
    'shop' => ['bi-bag-heart', 'Customer ordering'],
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
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark"><tr><th>Reason</th><th class="text-end">Default</th><th class="text-center">Order</th><th class="text-center">Status</th><th class="text-end">Actions</th></tr></thead>
                    <tbody>
                    <?php if (empty($surchargeReasons)): ?>
                        <tr><td colspan="5" class="text-center text-muted py-4">No surcharge reasons yet.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($surchargeReasons as $sr): ?>
                        <tr>
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
                    <input type="number" step="0.01" id="cvAmount" class="form-control" value="100" oninput="cvRun()">
                    <select id="cvFrom" class="form-select no-search" style="max-width:110px" onchange="cvRun()"><option value="ZMW">ZMW</option><?php foreach (array_keys($fxData['rates']) as $code): ?><option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($code) ?></option><?php endforeach; ?></select>
                    <span class="input-group-text">→</span>
                    <select id="cvTo" class="form-select no-search" style="max-width:110px" onchange="cvRun()"><?php foreach (array_keys($fxData['rates']) as $code): ?><option value="<?= htmlspecialchars($code) ?>"><?= htmlspecialchars($code) ?></option><?php endforeach; ?><option value="ZMW">ZMW</option></select>
                    <span class="input-group-text fw-bold" id="cvResult">—</span>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
function addManualRow() { const wrap = document.getElementById('manualRates'); const row = document.createElement('div'); row.className = 'row g-2 mb-2 manual-row'; row.innerHTML = '<div class="col-5"><input type="text" name="manual_code[]" class="form-control text-uppercase" maxlength="3" placeholder="USD"></div><div class="col-5"><input type="number" step="0.0001" min="0" name="manual_rate[]" class="form-control" placeholder="e.g. 27.50"></div><div class="col-2"><button type="button" class="btn btn-outline-danger w-100" onclick="this.closest(\'.manual-row\').remove()"><i class="bi bi-x"></i></button></div>'; wrap.appendChild(row); }
function cvRun() { const a = document.getElementById('cvAmount').value || 0; const f = document.getElementById('cvFrom').value; const t = document.getElementById('cvTo').value; fetch('index.php?page=currency&action=convert&amount=' + encodeURIComponent(a) + '&from=' + f + '&to=' + t, { credentials: 'same-origin' }).then(r => r.json()).then(function (res) { document.getElementById('cvResult').textContent = res.status === 'ok' ? (t + ' ' + Number(res.result).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })) : (res.msg || 'n/a'); }); }
document.addEventListener('DOMContentLoaded', cvRun);
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
<?php endif; ?>
