<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-shield-lock"></i> Change Password</h4>
            </div>
            <div class="card-body p-4">
                
                <?php if (!empty($_SESSION['force_change'])): ?>
                    <div class="alert alert-warning border-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <strong>Password update required.</strong><br>
                        Change your password before continuing.
                    </div>
                <?php endif; ?>

                <form method="POST" action="index.php?page=change_password">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required autofocus placeholder="Enter temporary password">
                    </div>

                    <hr class="my-4">

                    <div class="mb-3">
                        <label class="form-label fw-bold text-primary">New Password</label>
                        <input type="password" name="new_password" class="form-control" required minlength="6">
                        <div class="form-text">Must be at least 6 characters long.</div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-bold text-primary">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Update Password</button>
                        
                        <?php if (empty($_SESSION['force_change'])): ?>
                            <a href="index.php?page=dashboard" class="btn btn-outline-secondary">Cancel</a>
                        <?php else: ?>
                            <a href="index.php?action=logout" class="btn btn-outline-danger btn-sm mt-3">Logout</a>
                        <?php endif; ?>
                    </div>
                </form>

                <?php if (!empty($canApprove)): ?>
                <hr class="my-4">
                <h5 class="fw-bold">Till approval code</h5>
                <p class="text-muted small">Used for voids, refunds, payouts, and shift approval. It is not the login password.<?= empty($hasApproval) ? ' Set one before the till will accept an authorization.' : '' ?></p>
                <form method="POST" action="index.php?page=change_password">
                    <input type="hidden" name="save_approval" value="1">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current password</label>
                        <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Approval code</label>
                        <input type="password" name="approval_code" class="form-control" required minlength="4" maxlength="12" autocomplete="new-password">
                        <div class="form-text">4 to 12 characters, and different from the login password.</div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary">Save approval code</button>
                </form>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>
