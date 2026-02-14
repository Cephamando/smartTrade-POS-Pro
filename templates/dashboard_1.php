<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="shortcut icon" href="data:image/x-icon;," type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f0f2f5; }
        .dashboard-card { transition: transform 0.2s; cursor: pointer; border: none; }
        .dashboard-card:hover { transform: translateY(-5px); }
        .icon-box { width: 60px; height: 60px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; margin-bottom: 15px; }
    </style>
</head>
<body class="p-4">

<div class="d-flex justify-content-between align-items-center mb-5">
    <div>
        <h2 class="fw-bold mb-0">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? '') ?></h2>
        <p class="text-muted">Select a module to proceed</p>
    </div>
    <a href="index.php?page=logout" class="btn btn-outline-danger fw-bold"><i class="bi bi-box-arrow-right"></i> Logout</a>
</div>

<h5 class="text-muted fw-bold text-uppercase mb-3 small">Daily Operations</h5>
<div class="row g-4 mb-5">
    <div class="col-md-4 col-lg-3">
        <a href="index.php?page=pos" class="text-decoration-none">
            <div class="card dashboard-card shadow-sm h-100 p-3">
                <div class="card-body">
                    <div class="icon-box bg-warning text-dark"><i class="bi bi-grid-fill"></i></div>
                    <h5 class="fw-bold text-dark">POS Terminal</h5>
                    <p class="text-muted small mb-0">Process sales, tabs, and shifts</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="index.php?page=pickup" class="text-decoration-none">
            <div class="card dashboard-card shadow-sm h-100 p-3">
                <div class="card-body">
                    <div class="icon-box bg-primary text-white position-relative">
                        <i class="bi bi-bag-check-fill"></i>
                        <span id="readyCount" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger border border-light" style="font-size: 0.5em; display:none;">0</span>
                    </div>
                    <h5 class="fw-bold text-dark">Order Pickup</h5>
                    <p class="text-muted small mb-0">View ready orders for collection</p>
                </div>
            </div>
        </a>
    </div>
</div>

<?php if(in_array($_SESSION['role'], ['admin','manager','dev'])): ?>
<h5 class="text-muted fw-bold text-uppercase mb-3 small">Administration & Management</h5>
<div class="row g-4">
    <div class="col-md-4 col-lg-3">
        <a href="index.php?page=reports" class="text-decoration-none">
            <div class="card dashboard-card shadow-sm h-100 p-3">
                <div class="card-body">
                    <div class="icon-box bg-success text-white"><i class="bi bi-bar-chart-line-fill"></i></div>
                    <h5 class="fw-bold text-dark">Reports</h5>
                    <p class="text-muted small mb-0">Sales analytics & Audit trails</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="index.php?page=inventory" class="text-decoration-none">
            <div class="card dashboard-card shadow-sm h-100 p-3">
                <div class="card-body">
                    <div class="icon-box bg-info text-white"><i class="bi bi-box-seam-fill"></i></div>
                    <h5 class="fw-bold text-dark">Inventory</h5>
                    <p class="text-muted small mb-0">Products, Stock & Vendors</p>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-4 col-lg-3">
        <a href="index.php?page=vendors" class="text-decoration-none">
            <div class="card dashboard-card shadow-sm h-100 p-3">
                <div class="card-body">
                    <div class="icon-box bg-dark text-white"><i class="bi bi-people-fill"></i></div>
                    <h5 class="fw-bold text-dark">Suppliers</h5>
                    <p class="text-muted small mb-0">Manage Vendor Database</p>
                </div>
            </div>
        </a>
    </div>
    
    <?php if($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'dev'): ?>
    <div class="col-md-4 col-lg-3">
        <a href="index.php?page=users" class="text-decoration-none">
            <div class="card dashboard-card shadow-sm h-100 p-3">
                <div class="card-body">
                    <div class="icon-box bg-secondary text-white"><i class="bi bi-person-badge-fill"></i></div>
                    <h5 class="fw-bold text-dark">Staff</h5>
                    <p class="text-muted small mb-0">Manage Users & Access</p>
                </div>
            </div>
        </a>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
    // Poll for ready orders count
    function checkReadyOrders() {
        fetch('api/check_ready_orders.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.getElementById('readyCount');
                if (data.count > 0) {
                    badge.innerText = data.count;
                    badge.style.display = 'block';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(err => console.log('Poll error', err));
    }
    setInterval(checkReadyOrders, 10000); // Check every 10s
    checkReadyOrders(); // Initial check
</script>

</body>
</html>
