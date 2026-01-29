<div class="row mb-4">
    <div class="col-12">
        <h2 class="text-dark">
            Welcome, <strong><?= htmlspecialchars($_SESSION['username']) ?></strong>!
        </h2>
        <p class="text-muted">
            You are logged in at: <span class="badge bg-primary fs-6"><?= htmlspecialchars($locationName) ?></span>
        </p>
    </div>
</div>

<div class="row g-4">
    <div class="col-md-4">
        <div class="card shadow-sm border-start border-4 border-success h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase">System Status</h6>
                <h3 class="text-success fw-bold">Online</h3>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-start border-4 border-warning h-100">
            <div class="card-body">
                <h6 class="text-muted text-uppercase">Current Role</h6>
                <h3 class="text-dark fw-bold"><?= ucfirst($_SESSION['role']) ?></h3>
            </div>
        </div>
    </div>
</div>
