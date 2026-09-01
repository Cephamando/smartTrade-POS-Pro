<?php $userRole = strtolower($_SESSION['role'] ?? ''); ?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3>⚙️ System Settings</h3>
</div>
<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0 border-top border-4 border-dark">
            <div class="card-body p-4">
                <form method="POST" action="index.php?page=settings">
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
                            <i class="bi bi-exclamation-triangle-fill"></i> WARNING: Setting a Lockout Date in the past will instantly trigger the System Kill Switch.
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
                                <input type="date" name="lockout_date" class="form-control fw-bold border-danger text-danger" value="<?= htmlspecialchars($settings['lockout_date'] ?? '') ?>" placeholder="Leave blank for lifetime access">
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
