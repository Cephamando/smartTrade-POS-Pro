<?php
$userRole = strtolower($_SESSION['role'] ?? '');
$allowedTabs = ['general', 'receipt', 'surcharges', 'currency', 'shop', 'branding', 'email', 'guide', 'edge_sync'];
if ($userRole === 'dev') {
    $allowedTabs[] = 'payments';
    $allowedTabs[] = 'developer';
}
$tab = in_array($tab ?? 'general', $allowedTabs, true) ? $tab : 'general';
$secretMask = !empty($settings['flw_secret_key']) ? substr((string)$settings['flw_secret_key'], 0, 12) . '••••••••' : '';
$tabs = [
    'general' => ['bi-sliders', 'General'],
    'receipt' => ['bi-receipt', 'Receipt'],
    'surcharges' => ['bi-plus-slash-minus', 'POS surcharges'],
];
if ($userRole === 'dev') {
    $tabs['payments'] = ['bi-credit-card-2-front', 'Payments (Flutterwave)'];
}
$tabs += [
    'currency' => ['bi-currency-exchange', 'Currency'],
    'shop' => ['bi-bag-heart', 'Customer ordering'],
    'branding' => ['bi-image', 'Logo & banners'],
    'email' => ['bi-envelope', 'Email'],
    'guide' => ['bi-chat-left-text', 'Guide'],
    'edge_sync' => ['bi-cloud-arrow-up', 'Edge Sync'],
];
if ($userRole === 'dev') {
    $tabs['developer'] = ['bi-wrench', 'Developer'];
}
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
                    <h5 class="fw-bold mb-3 text-secondary border-bottom pb-2">Business</h5>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">BUSINESS NAME</label>
                        <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($settings['business_name'] ?? '') ?>" placeholder="e.g. Odelia POS">
                        <div class="form-text">The menu bar and sign-in use this name. The till slip has its own name, logo, and lines under Receipt.</div>
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

<?php elseif ($tab === 'receipt'):
$receiptFace = \App\Models\Setting::receiptFace($settings);
$receiptLogoUrl = \App\Helpers\Media::url($receiptFace['logo']);
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="card shadow-sm border-0 border-top border-4 border-dark">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-receipt"></i> Till slip</h5>
                <p class="text-muted small">This name, logo, and text are printed on the sale receipt and the card bill. They do not change the menu bar, sign-in, or the online shop.</p>
                <form method="POST" action="index.php?page=settings" enctype="multipart/form-data">
                    <input type="hidden" name="tab" value="receipt">
                    <input type="hidden" name="save_receipt" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">NAME ON THE SLIP</label>
                        <input type="text" name="receipt_name" class="form-control" value="<?= htmlspecialchars($settings['receipt_name'] ?? '') ?>" placeholder="<?= htmlspecialchars($settings['business_name'] ?? '') ?>">
                        <div class="form-text">Leave this blank to print the business name.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">HEADER</label>
                        <textarea name="receipt_header" class="form-control" rows="3" placeholder="TPIN, address, slogan"><?= htmlspecialchars($settings['receipt_header'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">FOOTER</label>
                        <textarea name="receipt_footer" class="form-control" rows="2" placeholder="Thank you!"><?= htmlspecialchars($settings['receipt_footer'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">RECEIPT LOGO</label>
                        <div id="brandPreview-receipt_logo" class="mb-2 rounded d-flex align-items-center justify-content-center" style="height:88px;width:180px;background:#f6efe6;overflow:hidden;">
                            <?php if ($receiptLogoUrl !== ''): ?>
                                <img src="<?= htmlspecialchars($receiptLogoUrl) ?>" alt="" style="max-width:100%;max-height:88px;object-fit:contain;">
                            <?php else: ?>
                                <span class="text-muted small">No logo on the slip</span>
                            <?php endif; ?>
                        </div>
                        <input type="file" name="receipt_logo" class="form-control form-control-sm" accept="image/jpeg,image/png,image/webp,image/gif">
                        <div class="form-text">JPG, PNG, WEBP, or GIF, up to 2.5 MB. The image library can also set this with Use as receipt logo.</div>
                        <?php if ($receiptLogoUrl !== ''): ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" name="remove_receipt_logo" value="1" id="remove_receipt_logo">
                            <label class="form-check-label small" for="remove_receipt_logo">Remove the receipt logo</label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-dark fw-bold px-5 py-2 shadow-sm"><i class="bi bi-save"></i> Save receipt</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card shadow-sm border-0">
            <div class="card-body p-4">
                <h6 class="fw-bold text-muted">Saved slip</h6>
                <div class="border rounded bg-white p-3 mx-auto" style="width:72mm;font-family:'Courier New',Courier,monospace;color:#000;">
                    <div class="text-center border-bottom pb-2 mb-2">
                        <?php if ($receiptLogoUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($receiptLogoUrl) ?>" alt="" style="max-height:52px;max-width:160px;object-fit:contain;margin-bottom:4px;">
                        <?php endif; ?>
                        <div style="font-weight:900;font-size:18px;"><?= htmlspecialchars($receiptFace['name']) ?></div>
                        <?php if (trim($receiptFace['header']) !== ''): ?>
                        <div style="font-size:13px;margin-top:4px;"><?= nl2br(htmlspecialchars($receiptFace['header'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="text-center text-muted" style="font-size:12px;">Items and totals print here</div>
                    <?php if (trim($receiptFace['footer']) !== ''): ?>
                    <div class="text-center border-top pt-2 mt-2" style="font-size:13px;"><?= nl2br(htmlspecialchars($receiptFace['footer'])) ?></div>
                    <?php endif; ?>
                </div>
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
                        <p class="text-muted small mb-3">The till and the website switch separately. Cards open the Flutterwave window. Mobile money prompts go to the customer's MTN, Airtel, or Zamtel line. With the till off, the cashier types the transaction id from the customer's own phone message. A subaccount id sends the split already saved on that subaccount. Keys that start with <code>FLWPUBK_TEST</code> or <code>FLWSECK_TEST</code> are sandbox.</p>
                    </div>
                    <?php if (!empty($flwTestMode) && !empty($flwEnabled)): ?><span class="badge bg-warning text-dark fs-6">TEST MODE</span><?php elseif (!empty($flwEnabled)): ?><span class="badge bg-success fs-6">LIVE</span><?php else: ?><span class="badge bg-secondary fs-6">OFF</span><?php endif; ?>
                </div>
                <form method="POST" action="index.php?page=settings">
                    <input type="hidden" name="tab" value="payments">
                    <input type="hidden" name="save_payments" value="1">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" name="flw_till_enabled" value="1" id="flwTillEnabled" <?= !empty($flwTillOn) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="flwTillEnabled">Flutterwave on the till</label>
                        <div class="form-text">The cashier's card window and mobile-money prompt. Off: the customer pays from their phone, and the till asks for the transaction id.</div>
                    </div>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="flw_web_enabled" value="1" id="flwWebEnabled" <?= !empty($flwWebOn) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-bold" for="flwWebEnabled">Flutterwave on the website</label>
                        <div class="form-text">The online shop, event payments, and Take App prompts. Turning this off leaves the till as it is.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">PUBLIC KEY</label>
                        <input type="text" name="flw_public_key" class="form-control font-monospace" value="<?= htmlspecialchars($settings['flw_public_key'] ?? '') ?>" placeholder="FLWPUBK_TEST-xxxxxxxxxxxxxxxx-X" autocomplete="off">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">SECRET KEY</label>
                        <input type="text" name="flw_secret_key" class="form-control font-monospace" value="<?= htmlspecialchars($secretMask) ?>" placeholder="FLWSECK_TEST-xxxxxxxxxxxxxxxx-X" autocomplete="off">
                        <div class="form-text">Stored on the server only. Leave the masked value as it is to keep the current key.</div>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">ENCRYPTION KEY (OPTIONAL)</label>
                            <input type="text" name="flw_encryption_key" class="form-control font-monospace" value="<?= htmlspecialchars($settings['flw_encryption_key'] ?? '') ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">WEBHOOK SECRET HASH</label>
                            <input type="text" name="flw_webhook_hash" class="form-control font-monospace" value="<?= htmlspecialchars($settings['flw_webhook_hash'] ?? '') ?>" autocomplete="off">
                        </div>
                    </div>
                    <?php
                    $flwHost = strtolower((string)preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
                    $flwLocal = in_array($flwHost, ['localhost', '127.0.0.1', '::1'], true);
                    $flwScheme = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')) ? 'https' : 'http';
                    $flwWebhookUrl = $flwLocal
                        ? 'https://smartrade.odeliazm.com/api/flutterwave_webhook.php'
                        : $flwScheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/'))), '/') . '/api/flutterwave_webhook.php';
                    ?>
                    <div class="form-text mb-3"><?php if ($flwLocal): ?>Flutterwave rejects <code>localhost</code>. Paste this public address into Flutterwave → Settings → Webhooks, with the same secret hash: <?php else: ?>Webhook URL: <?php endif; ?><code><?= htmlspecialchars($flwWebhookUrl) ?></code>. A confirmed charge marks the shop order paid even if the customer closed the browser. It stays on Web Orders until Hand Over.</div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">SUBACCOUNT ID</label>
                        <input type="text" name="flw_subaccount_id" class="form-control font-monospace" value="<?= htmlspecialchars($settings['flw_subaccount_id'] ?? '') ?>" placeholder="RS_..." autocomplete="off">
                        <div class="form-text">Paste the ID from Flutterwave → Subaccounts. It starts with <code>RS_</code>. Card and mobile-money payments use the split saved on that subaccount, and Dashboard → Settlements &amp; Payouts follows this subaccount rather than the main Flutterwave wallet.</div>
                    </div>
                    <hr class="my-4">
                    <h6 class="fw-bold mb-1"><i class="bi bi-bank"></i> Emergency payout account</h6>
                    <p class="text-muted small">A developer can send from the subaccount payout balance to this account. Flutterwave must have this server's IP on the transfer whitelist. A company name may include &amp; and commas.</p>
                    <?php
                    $payoutBanks = $flwBanks ?? [];
                    $payoutBranches = $flwBranches ?? [];
                    $payoutMethod = (($settings['flw_payout_method'] ?? '') === 'momo' || strtoupper(trim((string)($settings['flw_payout_bank'] ?? ''))) === 'MPS') ? 'momo' : 'bank';
                    ?>
                    <?php if (!empty($flwBanksError)): ?>
                        <div class="alert alert-warning small"><?= htmlspecialchars((string)$flwBanksError) ?><?php if ($payoutMethod === 'bank'): ?> You can type the bank code and name instead.<?php endif; ?></div>
                    <?php endif; ?>
                    <?php
                    $payoutNetwork = strtoupper((string)($settings['flw_payout_network'] ?? 'MTN'));
                    if (!in_array($payoutNetwork, ['MTN', 'AIRTEL', 'ZAMTEL'], true)) {
                        $payoutNetwork = 'MTN';
                    }
                    ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">PAY INTO</label>
                            <select name="flw_payout_method" id="flwPayoutMethod" class="form-select no-search" onchange="flwPayoutMethodChanged(this.value)">
                                <option value="bank" <?= $payoutMethod === 'bank' ? 'selected' : '' ?>>Bank account</option>
                                <option value="momo" <?= $payoutMethod === 'momo' ? 'selected' : '' ?>>Mobile money</option>
                            </select>
                        </div>
                        <div class="col-md-6<?= $payoutMethod === 'momo' ? '' : ' d-none' ?>" id="flwMomoFields">
                            <label class="form-label fw-bold small text-muted">NETWORK</label>
                            <select name="flw_payout_network" id="flwPayoutNetwork" class="form-select no-search" <?= $payoutMethod === 'momo' ? '' : 'disabled' ?>>
                                <?php foreach (['MTN' => 'MTN', 'AIRTEL' => 'Airtel', 'ZAMTEL' => 'Zamtel'] as $code => $label): ?>
                                    <option value="<?= $code ?>" <?= $payoutNetwork === $code ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6<?= $payoutMethod === 'bank' ? '' : ' d-none' ?>" id="flwBankFields">
                            <label class="form-label fw-bold small text-muted">BANK</label>
                            <?php if ($payoutBanks !== []): ?>
                                <select name="flw_payout_bank" id="flwPayoutBank" class="form-select" onchange="flwBankChanged(this)">
                                    <option value="">No emergency account</option>
                                    <?php foreach ($payoutBanks as $bank): ?>
                                        <option value="<?= htmlspecialchars($bank['code']) ?>" data-id="<?= htmlspecialchars($bank['id']) ?>" data-name="<?= htmlspecialchars($bank['name']) ?>" data-branches="<?= $bank['has_branches'] ? '1' : '0' ?>" <?= (string)($settings['flw_payout_bank'] ?? '') === $bank['code'] ? 'selected' : '' ?>><?= htmlspecialchars($bank['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="hidden" name="flw_payout_bank_name" id="flwPayoutBankName" value="<?= htmlspecialchars($settings['flw_payout_bank_name'] ?? '') ?>">
                            <?php else: ?>
                                <input type="text" name="flw_payout_bank" class="form-control font-monospace" value="<?= htmlspecialchars($settings['flw_payout_bank'] ?? '') ?>" placeholder="Bank code" autocomplete="off">
                            <?php endif; ?>
                        </div>
                        <?php if ($payoutBanks === [] && $payoutMethod === 'bank'): ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">BANK NAME</label>
                            <input type="text" name="flw_payout_bank_name" class="form-control" value="<?= htmlspecialchars($settings['flw_payout_bank_name'] ?? '') ?>" autocomplete="off">
                        </div>
                        <?php endif; ?>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted" id="flwPayoutAccountLabel"><?= $payoutMethod === 'momo' ? 'MOBILE NUMBER' : 'ACCOUNT NUMBER' ?></label>
                            <input type="text" name="flw_payout_account" id="flwPayoutAccount" class="form-control font-monospace" value="<?= htmlspecialchars($settings['flw_payout_account'] ?? '') ?>" inputmode="numeric" autocomplete="off" placeholder="<?= $payoutMethod === 'momo' ? '0971234567' : '' ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">NAME ON THE ACCOUNT</label>
                            <input type="text" name="flw_payout_name" class="form-control" value="<?= htmlspecialchars($settings['flw_payout_name'] ?? '') ?>" autocomplete="off">
                        </div>
                        <div class="col-md-6<?= $payoutBranches === [] ? ' d-none' : '' ?>" id="flwBranchWrap">
                            <label class="form-label fw-bold small text-muted">BRANCH</label>
                            <select name="flw_payout_branch" id="flwPayoutBranch" class="form-select">
                                <option value="">Choose a branch</option>
                                <?php foreach ($payoutBranches as $branch): ?>
                                    <option value="<?= htmlspecialchars($branch['code']) ?>" <?= (string)($settings['flw_payout_branch'] ?? '') === $branch['code'] ? 'selected' : '' ?>><?= htmlspecialchars($branch['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
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
                    <li>In the Flutterwave dashboard switch to <strong>Test mode</strong> and copy the public key and secret key here. Keys starting with <code>FLWPUBK_TEST</code> or <code>FLWSECK_TEST</code> are detected as sandbox.</li>
                    <li>Paste the subaccount id if the payment should follow that subaccount's split.</li>
                    <li>On the POS choose <strong>Flutterwave</strong>. For card, the sandbox test card is <code>5531 8866 5214 2950</code>, CVV 564, expiry 09/32, PIN 3310, OTP 12345.</li>
                    <li>For <em>Send MoMo prompt</em> enter any Zambian number. The sandbox opens a simulated approval page.</li>
                    <li>The sale completes only after this server verifies the charge with Flutterwave.</li>
                </ol>
            </div>
        </div>
    </div>
</div>
<script>
function flwTest() { const out = document.getElementById('flwTestResult'); out.textContent = 'Testing…'; out.className = 'align-self-center small text-muted'; fetch('index.php?page=flutterwave&action=test', { credentials: 'same-origin' }).then(r => r.json()).then(function (res) { out.textContent = res.msg || ''; out.className = 'align-self-center small fw-bold ' + (res.status === 'ok' ? 'text-success' : 'text-danger'); }).catch(function () { out.textContent = 'Could not reach the server.'; out.className = 'align-self-center small text-danger'; }); }
function flwPayoutMethodChanged(method) {
    var momo = document.getElementById('flwMomoFields');
    var bank = document.getElementById('flwBankFields');
    var branch = document.getElementById('flwBranchWrap');
    var network = document.getElementById('flwPayoutNetwork');
    var label = document.getElementById('flwPayoutAccountLabel');
    var account = document.getElementById('flwPayoutAccount');
    var isMomo = method === 'momo';
    if (momo) momo.classList.toggle('d-none', !isMomo);
    if (bank) bank.classList.toggle('d-none', isMomo);
    if (network) network.disabled = !isMomo;
    if (bank) bank.querySelectorAll('input, select').forEach(function (el) { el.disabled = isMomo; });
    if (branch) {
        branch.querySelectorAll('select').forEach(function (el) { el.disabled = isMomo; });
        if (isMomo) {
            branch.classList.add('d-none');
        } else {
            var bankSel = document.getElementById('flwPayoutBank');
            var opt = bankSel && bankSel.selectedOptions ? bankSel.selectedOptions[0] : null;
            if (opt && opt.dataset.branches === '1') branch.classList.remove('d-none');
        }
    }
    if (label) label.textContent = isMomo ? 'MOBILE NUMBER' : 'ACCOUNT NUMBER';
    if (account) account.placeholder = isMomo ? '0971234567' : '';
}
function flwBankChanged(sel) {
    var opt = sel.options[sel.selectedIndex];
    var name = document.getElementById('flwPayoutBankName');
    if (name) name.value = opt && opt.dataset.name ? opt.dataset.name : '';
    var wrap = document.getElementById('flwBranchWrap');
    var branch = document.getElementById('flwPayoutBranch');
    if (!wrap || !branch) return;
    var needs = opt && opt.dataset.branches === '1' && opt.dataset.id;
    if (!needs) {
        wrap.classList.add('d-none');
        if (branch.tomselect) branch.tomselect.clear(true);
        else branch.value = '';
        return;
    }
    wrap.classList.remove('d-none');
    fetch('index.php?page=flutterwave&action=branches&bank_id=' + encodeURIComponent(opt.dataset.id), { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            var rows = Array.isArray(res.branches) ? res.branches : [];
            if (branch.tomselect) {
                branch.tomselect.clear(true);
                branch.tomselect.clearOptions();
                rows.forEach(function (b) { branch.tomselect.addOption({ value: b.code, text: b.name }); });
                branch.tomselect.refreshOptions(false);
                return;
            }
            branch.innerHTML = '<option value="">Choose a branch</option>';
            rows.forEach(function (b) {
                var o = document.createElement('option');
                o.value = b.code;
                o.textContent = b.name;
                branch.appendChild(o);
            });
        })
        .catch(function () {});
}
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
                        <p class="text-muted small">A public page where customers register, browse the menu, and place pickup or delivery orders. Online orders are paid with Flutterwave only. Orders land in the POS under <strong>Web Orders</strong> and on the kitchen screens.</p>
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
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted">ORDER WEBHOOK URL</label>
                            <input type="url" name="order_webhook_url" class="form-control font-monospace" value="<?= htmlspecialchars($settings['order_webhook_url'] ?? '') ?>" placeholder="https://example.com/orders" autocomplete="off">
                            <div class="form-text">We POST here when an online order is <code>ready</code> (nothing left to cook, including a drinks-only order) and again when it is <code>handed_over</code>. The body is <code>order_id</code>, <code>sale_id</code>, <code>status</code>, <code>payment_status</code>, <code>customer</code>, and <code>timestamp</code>. Leave this blank to send nothing.</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">WEBHOOK SECRET</label>
                            <input type="text" name="order_webhook_secret" class="form-control font-monospace" value="<?= htmlspecialchars($settings['order_webhook_secret'] ?? '') ?>" autocomplete="off">
                            <div class="form-text">Sent as <code>X-SmartTrade-Signature</code>, the HMAC-SHA256 of the raw body. Optional.</div>
                        </div>
                        <div class="col-12 d-flex flex-wrap gap-4 mt-2">
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="shop_pay_online" value="1" id="shopOnline" <?= !empty($flwWebOn) ? 'checked' : '' ?>><label class="form-check-label fw-bold small" for="shopOnline">Take payment with Flutterwave on this website</label><div class="form-text">Does not change the till. The same switch is under Payments.</div></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="shop_delivery" value="1" id="shopDelivery" <?= ($settings['shop_delivery'] ?? '1') === '1' ? 'checked' : '' ?>><label class="form-check-label fw-bold small" for="shopDelivery">Offer delivery</label></div>
                        </div>
                    </div>
                    <hr class="my-4">
                    <h6 class="fw-bold mb-1">Take App</h6>
                    <p class="text-muted small">The menu link, WhatsApp, Instagram, Facebook, and table QR. Leave payments off inside Take App. While website Flutterwave is on, this till sends the mobile-money prompt, then deducts stock and files the ZRA invoice when the customer approves it.</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">WEBHOOK URL</label>
                            <input type="text" class="form-control font-monospace" value="https://smartrade.odeliazm.com/api/take_webhook.php" readonly>
                            <div class="form-text">Paste this in Take App → Settings → Apps → Webhooks. Events: <code>order.created</code> and <code>order.updated</code>.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">SIGNING SECRET</label>
                            <input type="text" name="take_webhook_secret" class="form-control font-monospace" value="<?= htmlspecialchars($settings['take_webhook_secret'] ?? '') ?>" autocomplete="off">
                            <div class="form-text">The same secret Take App uses to sign <code>X-Take-Signature</code>.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">API KEY</label>
                            <input type="text" name="take_api_key" class="form-control font-monospace" value="<?= ($settings['take_api_key'] ?? '') !== '' ? '••••••••••••' : '' ?>" autocomplete="off" placeholder="Optional, for Copy menu">
                            <div class="form-text">Used only to copy the POS menu. Leave the dots to keep the saved key. Clear the field to remove it.</div>
                        </div>
                    </div>
                    <div class="text-end mt-4">
                        <button type="submit" class="btn btn-info fw-bold px-5 py-2 shadow-sm text-dark"><i class="bi bi-save"></i> Save</button>
                    </div>
                </form>
                <form method="POST" action="index.php?page=settings" class="mt-3">
                    <input type="hidden" name="tab" value="shop">
                    <input type="hidden" name="copy_take_menu" value="1">
                    <button type="submit" class="btn btn-outline-info fw-bold" <?= ($settings['take_api_key'] ?? '') === '' ? 'disabled' : '' ?>><i class="bi bi-cloud-upload"></i> Copy menu</button>
                    <span class="form-text ms-2">Creates or updates Take App products with SKU equal to the POS product id, plus the name and price. Save the API key first.</span>
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
                <p class="text-muted small mb-3">Upload pictures once. Tick one picture to choose the menu item it belongs to. That picture then shows on the online menu. The slideshow can take several.</p>
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
                    <button type="button" class="btn btn-sm btn-outline-dark" id="libUseReceiptLogo">Use as receipt logo</button>
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
            .pick-row { display: flex; align-items: center; gap: 10px; width: 100%; text-align: left; border: 0; border-bottom: 1px solid #f3edd4; background: #fff; padding: 8px; }
            .pick-row.is-on { background: #fff6d8; }
            .pick-row img, .pick-row .pick-empty { width: 46px; height: 46px; border-radius: 10px; object-fit: cover; background: #f6efe6; flex: 0 0 auto; }
            .pick-row .pick-empty { display: inline-flex; align-items: center; justify-content: center; color: #8a7b62; }
        </style>
        <div class="modal fade" id="menuPhotoPick" tabindex="-1" aria-labelledby="menuPhotoPickTitle" aria-hidden="true">
            <div class="modal-dialog modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="menuPhotoPickTitle">Choose a menu item</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="d-flex gap-3 align-items-center mb-3">
                            <img id="menuPhotoPickPreview" alt="" style="width:88px;height:88px;object-fit:cover;border-radius:14px;background:#f6efe6;">
                            <div>
                                <div class="fw-bold">This picture will show on the online menu.</div>
                                <div class="text-muted small">Search for the dish or drink, then apply it.</div>
                            </div>
                        </div>
                        <label class="visually-hidden" for="menuPhotoPickSearch">Search menu items</label>
                        <input type="search" id="menuPhotoPickSearch" class="form-control" placeholder="Name or category">
                        <div id="menuPhotoPickList" class="border rounded mt-2" style="max-height:360px;overflow:auto;"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-dark fw-bold" id="menuPhotoPickApply" disabled>Apply to menu item</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card shadow-sm border-0 border-top border-4 border-warning">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-image text-warning"></i> Logo, banners, and shop photos</h5>
                <p class="text-muted small">Administrators and managers can replace these here. Use JPG, PNG, WEBP, or GIF, up to 2.5 MB.</p>
                <form method="POST" action="index.php?page=settings" enctype="multipart/form-data">
                    <input type="hidden" name="tab" value="branding">
                    <input type="hidden" name="save_branding" value="1">
                    <div class="row g-3">
                        <?php foreach ([
                            ['LOGO', 'Menu bar, sign-in, and the online shop mark. The till slip uses the logo under Receipt.', 'logo', $logoUrl],
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
        ['libUseReceiptLogo', 'receipt_logo'],
        ['libUseBanner', 'shop_banner'],
        ['libUsePromo', 'promo_slide']
    ];
    var menuPickItems = <?= json_encode(array_map(static function (array $item): array {
        return [
            'id' => (int)$item['id'],
            'name' => (string)$item['name'],
            'cat' => (string)$item['cat_name'],
            'price' => number_format((float)$item['price'], 2),
            'image' => \App\Helpers\Media::url((string)($item['image_path'] ?? '')),
        ];
    }, $menuPhotos), JSON_UNESCAPED_UNICODE) ?>;
    var pickPath = '';
    var pickId = '';

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
        var on = !button.classList.contains('active');
        button.classList.toggle('active', on);
        button.setAttribute('aria-pressed', on ? 'true' : 'false');
        syncSelection();
        if (on && selected.length === 1) openItemPicker(button.dataset.path || '');
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
            if (selected.length !== 1) {
                toast({ status: 'error', msg: selected.length ? 'Choose one image for a menu item.' : 'Tick one image first.' });
                return;
            }
            openItemPicker(selected[0]);
            return;
        }
        var body = new FormData();
        body.set('tab', 'branding');
        body.set('use_library_image', '1');
        body.set('ajax', '1');
        body.set('target', target);
        selected.forEach(function (path) { body.append('image_paths[]', path); });
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

    function paintPickList(query) {
        var list = document.getElementById('menuPhotoPickList');
        var applyBtn = document.getElementById('menuPhotoPickApply');
        if (!list) return;
        var words = String(query || '').toLowerCase().trim().split(/\s+/).filter(Boolean);
        list.innerHTML = '';
        var shown = 0;
        var lastCat = '';
        menuPickItems.forEach(function (item) {
            var hay = (item.cat + ' ' + item.name).toLowerCase();
            if (words.length && !words.every(function (w) { return hay.indexOf(w) !== -1; })) return;
            if (item.cat !== lastCat) {
                var head = document.createElement('div');
                head.className = 'px-2 pt-2 small fw-bold text-muted';
                head.textContent = item.cat;
                list.appendChild(head);
                lastCat = item.cat;
            }
            var row = document.createElement('button');
            row.type = 'button';
            row.className = 'pick-row' + (String(item.id) === String(pickId) ? ' is-on' : '');
            if (item.image) {
                var img = document.createElement('img');
                img.src = item.image;
                img.alt = '';
                row.appendChild(img);
            } else {
                var empty = document.createElement('span');
                empty.className = 'pick-empty';
                empty.innerHTML = '<i class="bi bi-image"></i>';
                row.appendChild(empty);
            }
            var copy = document.createElement('span');
            var title = document.createElement('span');
            title.className = 'fw-bold d-block';
            title.textContent = item.name;
            var meta = document.createElement('span');
            meta.className = 'small text-muted';
            meta.textContent = 'ZMW ' + item.price + (item.image ? ' · Has a photo' : ' · No photo yet');
            copy.appendChild(title);
            copy.appendChild(meta);
            row.appendChild(copy);
            row.addEventListener('click', function () {
                pickId = String(item.id);
                if (applyBtn) applyBtn.disabled = false;
                paintPickList(document.getElementById('menuPhotoPickSearch').value);
            });
            list.appendChild(row);
            shown++;
        });
        if (!shown) list.innerHTML = '<p class="text-muted small m-2 mb-0">No menu item matches.</p>';
    }

    function openItemPicker(path) {
        path = String(path || '').split('?')[0];
        if (!path) return;
        pickPath = path;
        pickId = '';
        var preview = document.getElementById('menuPhotoPickPreview');
        if (preview) preview.src = path;
        var search = document.getElementById('menuPhotoPickSearch');
        if (search) search.value = '';
        var applyBtn = document.getElementById('menuPhotoPickApply');
        if (applyBtn) applyBtn.disabled = true;
        paintPickList('');
        var modalEl = document.getElementById('menuPhotoPick');
        if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }

    function applyPickedItem() {
        if (!pickPath || !pickId) return;
        var applyBtn = document.getElementById('menuPhotoPickApply');
        if (applyBtn) applyBtn.disabled = true;
        var body = new FormData();
        body.set('tab', 'branding');
        body.set('use_library_image', '1');
        body.set('ajax', '1');
        body.set('target', 'product');
        body.set('product_id', pickId);
        body.append('image_paths[]', pickPath);
        post(body).then(function (data) {
            menuPickItems.forEach(function (item) {
                if (String(item.id) === String(data.id)) item.image = data.image || pickPath;
            });
            document.dispatchEvent(new CustomEvent('menu-photo-assigned', { detail: data }));
            var modalEl = document.getElementById('menuPhotoPick');
            if (modalEl && window.bootstrap) bootstrap.Modal.getOrCreateInstance(modalEl).hide();
            toast(data);
            pingShopFace();
        }).catch(function (err) {
            if (applyBtn) applyBtn.disabled = false;
            toast({ status: 'error', msg: err.message });
        });
    }

    var pickSearch = document.getElementById('menuPhotoPickSearch');
    if (pickSearch) pickSearch.addEventListener('input', function () { paintPickList(pickSearch.value); });
    var pickApply = document.getElementById('menuPhotoPickApply');
    if (pickApply) pickApply.addEventListener('click', applyPickedItem);
    var useItem = document.getElementById('libUseItem');
    if (useItem) useItem.addEventListener('click', function () { apply('product'); });

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
    $guideModels = \App\Services\Guide::models();
    $guideModel = \App\Services\Guide::model((string)($settings['gemini_model'] ?? ''));
?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm border-0 border-top border-4 border-warning">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-chat-left-text text-warning"></i> Staff guide</h5>
                <p class="text-muted small">The Guide button and the training coach answer from the written manuals. Choose Offline to use Qwen through Ollama on this computer, or a Gemini model when a key is saved. Leave the key blank to keep the one already saved.</p>
                <form method="POST" action="index.php?page=settings">
                    <input type="hidden" name="tab" value="guide">
                    <input type="hidden" name="save_guide" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">GEMINI API KEY</label>
                        <input type="password" name="gemini_api_key" class="form-control" value="" placeholder="<?= !empty($settings['gemini_api_key']) ? 'Saved. Leave blank to keep it.' : 'Paste the Gemini API key' ?>" autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">MODEL</label>
                        <select name="gemini_model" id="guideModel" class="form-select">
                            <?php foreach ($guideModels as $id => $label): ?>
                            <option value="<?= htmlspecialchars($id) ?>" <?= $guideModel === $id ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3" id="ollamaAddress">
                        <label class="form-label fw-bold small text-muted">OLLAMA ADDRESS</label>
                        <input type="text" name="ollama_url" class="form-control" value="<?= htmlspecialchars((string)($settings['ollama_url'] ?? '')) ?>" placeholder="http://host.docker.internal:11434" autocomplete="off">
                        <div class="form-text">Used only for Offline. Ollama has to be running and listening on every address (<code>OLLAMA_HOST=0.0.0.0:11434</code>), with <code>qwen3.5:4b</code> pulled. The shop container reaches the computer through <code>host.docker.internal</code>.</div>
                    </div>
                    <div class="text-end mt-4">
                        <button class="btn btn-dark fw-bold px-5"><i class="bi bi-save"></i> Save guide</button>
                    </div>
                </form>
                <?php $guideOffline = \App\Services\Guide::offline($settings); ?>
                <?php if (!$guideOffline && ($guideReady = \App\Services\Guide::ready($settings))): ?>
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
<?php elseif ($tab === 'edge_sync'):
    $es = $edgeSyncStatus ?? [];
    $esRole = (string)($es['role'] ?? ($settings['sync_node_role'] ?? 'online'));
    $esOnlineUrl = (string)($es['online_url'] ?? ($settings['sync_online_url'] ?? ''));
    $esLocId = (int)($es['location_id'] ?? ($settings['sync_location_id'] ?? 0));
    $esToken = (string)($settings['sync_node_token'] ?? '');
    $esTokenMasked = $esToken !== '' ? substr($esToken, 0, 8) . '••••••••••••••••' . substr($esToken, -6) : '';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $thisServerUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<div class="row g-4">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm border-0 border-top border-4 border-info">
            <div class="card-body p-4">
                <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
                    <div>
                        <h5 class="fw-bold mb-1"><i class="bi bi-cloud-arrow-up text-info"></i> Live + Offline Edge Synchronization</h5>
                        <p class="text-muted small mb-0">Pair a live Online HQ server with an offline sealed shop PC. The shop PC pulls products, prices, staff, and paid web orders from Online HQ, keeps working locally during internet outages, and pushes sales, shifts, tips, expenses, and stock deductions back once online.</p>
                    </div>
                    <span class="badge <?= $esRole === 'edge' ? 'bg-warning text-dark' : 'bg-primary' ?> px-3 py-2">
                        <?= $esRole === 'edge' ? 'Offline Shop Edge' : 'Online HQ (Master)' ?>
                    </span>
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-sm-4">
                        <div class="p-3 rounded bg-light border h-100">
                            <div class="text-muted small fw-bold">LAST PUSH</div>
                            <div class="fw-bold mt-1"><?= !empty($es['last_push_at']) ? htmlspecialchars((string)$es['last_push_at']) : 'Never' ?></div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 rounded bg-light border h-100">
                            <div class="text-muted small fw-bold">LAST PULL</div>
                            <div class="fw-bold mt-1"><?= !empty($es['last_pull_at']) ? htmlspecialchars((string)$es['last_pull_at']) : 'Never' ?></div>
                        </div>
                    </div>
                    <div class="col-sm-4">
                        <div class="p-3 rounded bg-light border h-100">
                            <div class="text-muted small fw-bold">PENDING QUEUE</div>
                            <div class="fw-bold mt-1 <?= ((int)($es['pending_count'] ?? 0) > 0) ? 'text-warning' : 'text-success' ?>">
                                <?= (int)($es['pending_count'] ?? 0) ?> record(s)
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($es['last_error'])): ?>
                <div class="alert alert-warning small">
                    <strong>Last sync notice:</strong> <?= htmlspecialchars((string)$es['last_error']) ?>
                </div>
                <?php endif; ?>

                <form method="POST" action="index.php?page=settings">
                    <input type="hidden" name="tab" value="edge_sync">
                    <input type="hidden" name="save_edge_sync" value="1">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">THIS NODE ROLE</label>
                            <select name="sync_node_role" class="form-select fw-bold" id="syncNodeRoleSelect">
                                <option value="online" <?= $esRole === 'online' ? 'selected' : '' ?>>Online HQ (Master Server & ZRA VSDC)</option>
                                <option value="edge" <?= $esRole === 'edge' ? 'selected' : '' ?>>Offline Shop Edge (Local Sealed POS)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">PRIMARY STORE LOCATION</label>
                            <select name="sync_location_id" class="form-select">
                                <option value="0">All Locations</option>
                                <?php foreach ($locations as $l): ?>
                                <option value="<?= (int)$l['id'] ?>" <?= $esLocId === (int)$l['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string)$l['name']) ?> (<?= htmlspecialchars((string)($l['type'] ?? 'store')) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">ONLINE HQ BASE URL</label>
                            <input type="url" name="sync_online_url" class="form-control" value="<?= htmlspecialchars($esOnlineUrl !== '' ? $esOnlineUrl : ($esRole === 'online' ? $thisServerUrl : '')) ?>" placeholder="https://smartrade.odeliazm.com">
                            <div class="form-text">On the Offline Shop PC, enter the public HTTPS address of your Online HQ server.</div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-bold small text-muted">SHARED EDGE SYNC TOKEN</label>
                            <div class="input-group">
                                <input type="text" name="sync_node_token" id="edgeSyncTokenInput" class="form-control font-monospace" value="" placeholder="<?= $esTokenMasked !== '' ? 'Saved (' . htmlspecialchars($esTokenMasked) . '). Leave blank to keep.' : 'Generate on Online HQ and paste on Offline Edge' ?>" autocomplete="off">
                                <button type="button" class="btn btn-outline-secondary fw-bold" id="genEdgeTokenBtn">
                                    <i class="bi bi-key"></i> Generate New Token
                                </button>
                            </div>
                            <div class="form-text">Both the Online HQ and the Offline Shop PC must share this exact token.</div>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <div>
                            <?php if ($esRole === 'edge'): ?>
                            <button type="submit" name="run_edge_sync_now" value="1" class="btn btn-outline-primary fw-bold">
                                <i class="bi bi-arrow-repeat"></i> Sync with Online HQ Now
                            </button>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-dark fw-bold px-5"><i class="bi bi-save"></i> Save Edge Sync</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    var genBtn = document.getElementById('genEdgeTokenBtn');
    var tokInput = document.getElementById('edgeSyncTokenInput');
    if (genBtn && tokInput) {
        genBtn.addEventListener('click', function () {
            genBtn.disabled = true;
            var body = new FormData();
            body.set('ajax', '1');
            body.set('tab', 'edge_sync');
            body.set('generate_edge_token', '1');
            fetch('index.php?page=settings', { method: 'POST', body: body })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    if (d && d.token) {
                        tokInput.value = d.token;
                        tokInput.select();
                    }
                })
                .finally(function () { genBtn.disabled = false; });
        });
    }
})();
</script>
<?php endif; ?>

<?php if ($tab === 'developer' && $userRole === 'dev'): ?>
<div class="row g-4">
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 border-top border-4 border-warning">
            <div class="card-body p-4">
                <h5 class="fw-bold">Clear sales and stock</h5>
                <p class="small text-muted">Removes sales, refunds, tips, payments, shifts, end-of-day closures, expenses, purchase orders, goods received, stock transfers, and stock on hand. Products, UNSPSC classes, staff, settings, and locations stay.</p>
                <form method="POST" action="index.php?page=settings" class="js-confirm-form" data-swal-title="Clear every sale and the stock on hand?" data-swal-text="Products and UNSPSC classes are kept. This cannot be undone." data-swal-confirm="Clear" data-swal-icon="warning">
                    <input type="hidden" name="tab" value="developer">
                    <input type="hidden" name="clean_slate" value="operations">
                    <label class="form-label small fw-bold text-muted">TYPE RESET</label>
                    <input type="text" name="confirm_phrase" class="form-control mb-3" autocomplete="off" required>
                    <button class="btn btn-warning fw-bold">Clear sales and stock</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="card shadow-sm border-0 border-top border-4 border-danger">
            <div class="card-body p-4">
                <h5 class="fw-bold text-danger">Wipe products and UNSPSC</h5>
                <p class="small text-muted">Does the clear above, then deletes every product, recipe, and UNSPSC class. Categories, staff, settings, and locations stay. The menu will be empty until you enter it again.</p>
                <form method="POST" action="index.php?page=settings" class="js-confirm-form" data-swal-title="Delete the product catalogue and UNSPSC classes?" data-swal-text="Sales and stock go with them. This cannot be undone." data-swal-confirm="Wipe catalogue" data-swal-icon="warning">
                    <input type="hidden" name="tab" value="developer">
                    <input type="hidden" name="clean_slate" value="catalogue">
                    <label class="form-label small fw-bold text-muted">TYPE WIPE PRODUCTS</label>
                    <input type="text" name="confirm_phrase" class="form-control mb-3" autocomplete="off" required>
                    <button class="btn btn-danger fw-bold">Wipe products and UNSPSC</button>
                </form>
            </div>
        </div>
    </div>
</div>
<p class="small text-muted mt-3">Sign-ins, sales, and these resets are listed under <a href="index.php?page=activity">Office → Activity log</a>. Stock movements stay on stock history.</p>
<?php endif; ?>
