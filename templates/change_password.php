<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-5">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h4 class="mb-0"><i class="bi bi-shield-lock"></i> Change Password</h4>
            </div>
            <div class="card-body p-4">
                
                <form method="POST" action="index.php?page=change_password">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">Current Password</label>
                        <input type="password" name="current_password" class="form-control" required autofocus>
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
                        <a href="index.php?page=dashboard" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>
