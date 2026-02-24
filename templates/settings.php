<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-gear-fill text-danger"></i> System Configuration</h3>
    <span class="badge bg-danger fs-6">Developer Only</span>
</div>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm border-0 border-top border-danger border-4">
            <div class="card-body p-4">
                <form method="POST">
                    <input type="hidden" name="save_settings" value="1">
                    
                    <h5 class="fw-bold mb-3 text-dark border-bottom pb-2">Business & Branding</h5>
                    <div class="row mb-3">
                        <div class="col-md-8">
                            <label class="form-label fw-bold small text-muted">Business Name (Appears in top navbar)</label>
                            <input type="text" name="business_name" class="form-control" value="<?= htmlspecialchars($currentSettings['business_name'] ?? 'OdeliaPOS') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Theme Color</label>
                            <input type="color" name="theme_color" class="form-control form-control-color w-100" value="<?= htmlspecialchars($currentSettings['theme_color'] ?? '#3e2723') ?>" title="Choose your color">
                        </div>
                    </div>

                    <h5 class="fw-bold mb-3 mt-4 text-dark border-bottom pb-2">Feature Control</h5>
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">OdeliaPOS License Tier</label>
                        <select name="license_tier" class="form-select form-select-lg fw-bold border-dark">
                            <option value="lite" <?= ($currentSettings['license_tier'] ?? '') === 'lite' ? 'selected' : '' ?>>Lite (Simple POS & Reports)</option>
                            <option value="pro" <?= ($currentSettings['license_tier'] ?? '') === 'pro' ? 'selected' : '' ?>>Pro (Advanced Inventory & Stock Transfers)</option>
                            <option value="hospitality" <?= ($currentSettings['license_tier'] ?? '') === 'hospitality' ? 'selected' : '' ?>>Hospitality (Kitchen KDS & Menu Builder)</option>
                        </select>
                        <div class="form-text text-danger"><i class="bi bi-exclamation-triangle-fill"></i> Changing this will instantly hide or show features globally.</div>
                    </div>

                    <h5 class="fw-bold mb-3 mt-4 text-dark border-bottom pb-2">Receipt Configuration</h5>
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">Receipt Header (e.g., Address, Tax Payer Name)</label>
                        <textarea name="receipt_header" class="form-control" rows="3"><?= htmlspecialchars($currentSettings['receipt_header'] ?? '') ?></textarea>
                    </div>
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">Receipt Footer (e.g., Return policy, WiFi Password)</label>
                        <textarea name="receipt_footer" class="form-control" rows="2"><?= htmlspecialchars($currentSettings['receipt_footer'] ?? '') ?></textarea>
                    </div>

                    <div class="d-grid mt-4">
                        <button type="submit" class="btn btn-danger btn-lg fw-bold shadow-sm">Save Global Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    <?php if(isset($_SESSION['swal_msg'])): ?>
    Swal.fire({
        icon: '<?= $_SESSION['swal_type'] ?>',
        title: '<?= $_SESSION['swal_msg'] ?>',
        timer: 1500,
        showConfirmButton: false
    });
    <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
    <?php endif; ?>
</script>
