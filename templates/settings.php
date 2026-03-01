<div class="container mt-4">
    <h3><i class="bi bi-gear-fill text-danger me-2"></i> Developer System Settings</h3>
    <hr>
    <div class="card shadow-sm border-danger border-top border-4 mb-5">
        <div class="card-body p-4">
            <form method="POST">
                <input type="hidden" name="save_settings" value="1">
                
                <h5 class="fw-bold mb-3 text-secondary">White Labeling</h5>
                <div class="mb-3">
                    <label class="form-label fw-bold">Business Name (Appears on Receipts & Navbar)</label>
                    <input type="text" name="business_name" class="form-control form-control-lg fw-bold text-primary" value="<?= htmlspecialchars($sysSettings['business_name'] ?? 'OdeliaPOS') ?>">
                </div>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Receipt Header (TPIN, Address, etc.)</label>
                        <textarea name="receipt_header" class="form-control" rows="3"><?= htmlspecialchars($sysSettings['receipt_header'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Receipt Footer (Return policy, Wifi, etc.)</label>
                        <textarea name="receipt_footer" class="form-control" rows="3"><?= htmlspecialchars($sysSettings['receipt_footer'] ?? 'Thank you!') ?></textarea>
                    </div>
                </div>

                <hr class="my-4">
                <h5 class="fw-bold mb-3 text-danger"><i class="bi bi-shield-lock-fill"></i> Licensing & Kill Switch</h5>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">License Tier Configuration</label>
                        <select name="license_tier" class="form-select form-select-lg fw-bold">
                            <option value="lite" <?= ($sysSettings['license_tier'] ?? '') === 'lite' ? 'selected' : '' ?>>Lite (Standard Retail)</option>
                            <option value="pro" <?= ($sysSettings['license_tier'] ?? '') === 'pro' ? 'selected' : '' ?>>Pro (Advanced Retail)</option>
                            <option value="hospitality" <?= ($sysSettings['license_tier'] ?? '') === 'hospitality' ? 'selected' : '' ?>>Hospitality (Kitchen & KDS)</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold text-danger">Software Lockout Date (Kill Switch)</label>
                        <input type="datetime-local" name="lockout_date" class="form-control form-control-lg fw-bold border-danger text-danger" value="<?= htmlspecialchars($sysSettings['lockout_date'] ?? '') ?>">
                        <small class="text-muted fw-bold">If past this date, the system locks into Read-Only Mode for clients.</small>
                    </div>
                </div>

                <button type="submit" class="btn btn-danger w-100 fw-bold mt-4 py-3 shadow-sm fs-5"><i class="bi bi-cpu-fill"></i> SAVE SYSTEM CONFIGURATION</button>
            </form>
        </div>
    </div>
</div>
<?php include 'footer.php'; ?>
