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

                    <div class="text-end">
                        <button type="submit" class="btn btn-dark fw-bold px-5 py-2 shadow-sm"><i class="bi bi-save"></i> Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
