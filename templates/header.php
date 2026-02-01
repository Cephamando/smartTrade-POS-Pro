<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OdeliaPOS - Premium</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --theme-brown: #3e2723;
            --theme-brown-light: #5d4037;
            --theme-gold: #ffc107;
            --theme-gold-dark: #d39e00;
            --theme-orange: #fd7e14;
            --theme-bg: #fff8e1; /* Light Cream */
        }
        body { background-color: var(--theme-bg); color: var(--theme-brown); }
        
        /* Navbar Overrides */
        .navbar-custom { background-color: var(--theme-brown); border-bottom: 3px solid var(--theme-gold); }
        .navbar-custom .navbar-brand { color: var(--theme-gold) !important; font-weight: bold; }
        .navbar-custom .nav-link { color: rgba(255,255,255,0.8) !important; }
        .navbar-custom .nav-link:hover { color: var(--theme-gold) !important; }
        
        /* Card & Button Overrides */
        .card { border: 1px solid rgba(62, 39, 35, 0.1); box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .btn-primary { background-color: var(--theme-brown); border-color: var(--theme-brown); }
        .btn-primary:hover { background-color: var(--theme-brown-light); border-color: var(--theme-brown-light); }
        .btn-warning { background-color: var(--theme-gold); border-color: var(--theme-gold); color: #3e2723; }
        .btn-outline-primary { color: var(--theme-brown); border-color: var(--theme-brown); }
        .btn-outline-primary:hover { background-color: var(--theme-brown); color: var(--theme-gold); }
        
        /* Text Colors */
        .text-brown { color: var(--theme-brown); }
        .text-gold { color: var(--theme-gold-dark); }
        .text-orange { color: var(--theme-orange); }

        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-custom mb-4 shadow-sm no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php?page=dashboard">
                <i class="bi bi-grid-fill"></i> OdeliaPOS
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php?page=dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php?page=pos">POS</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php?page=pickup" target="_blank">Pickup</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php?page=kds" target="_blank">Kitchen</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php?page=inventory">Inventory</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php?page=members">Members</a></li>
                    
                    <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager', 'dev'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Management</a>
                            <ul class="dropdown-menu shadow">
                                <li><a class="dropdown-item" href="index.php?page=reports">Reports</a></li>
                                <li><a class="dropdown-item" href="index.php?page=transfers">Transfers</a></li>
                                <li><a class="dropdown-item" href="index.php?page=audit">Audit Trail</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="index.php?page=users">Users</a></li>
                                <li><a class="dropdown-item" href="index.php?page=locations">Locations</a></li>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3 small">
                        <i class="bi bi-geo-alt-fill text-gold"></i> <?= htmlspecialchars($_SESSION['location_name'] ?? 'HQ') ?>
                        <span class="mx-1">|</span>
                        <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                    </span>
                    <a href="index.php?action=logout" class="btn btn-sm btn-outline-warning">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid px-4">
