<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OdeliaPOS - Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f4f6f9; }
        
        /* CSS Failsafe: Auto-open dropdowns instantly on mouse hover */
        @media (min-width: 992px) {
            .navbar .dropdown:hover .dropdown-menu { 
                display: block; 
                margin-top: 0; 
            }
        }
    </style>
</head>
<body>
<?php
$role = $_SESSION['role'] ?? 'cashier';
$tier = defined('LICENSE_TIER') ? LICENSE_TIER : 'lite';

// Check if this page is being loaded inside the POS modal iframe
$isEmbedded = isset($_GET['embedded']) && $_GET['embedded'] == '1';
?>

<?php if (!$isEmbedded): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom border-warning border-3 shadow-sm mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-warning" href="index.php?page=dashboard">
            <i class="bi bi-box-seam"></i> OdeliaPOS
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#topNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="topNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php?page=dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>
                <li class="nav-item ms-2">
                    <a class="nav-link fw-bold text-success border border-success rounded px-3" href="index.php?page=pos"><i class="bi bi-cart-check"></i> LAUNCH POS</a>
                </li>
                
                <?php if(in_array($role, ['admin', 'manager', 'dev'])): ?>
                <li class="nav-item dropdown ms-3">
                    <a class="nav-link dropdown-toggle" href="#" id="invDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-boxes"></i> Inventory
                    </a>
                    <ul class="dropdown-menu shadow" aria-labelledby="invDropdown">
                        <li><a class="dropdown-item" href="index.php?page=products"><i class="bi bi-tags"></i> Products & Menu</a></li>
                        <li><a class="dropdown-item" href="index.php?page=categories"><i class="bi bi-folder"></i> Categories</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item fw-bold text-primary" href="index.php?page=receive_stock"><i class="bi bi-box-arrow-in-down"></i> Receive Stock</a></li>
                        <li><a class="dropdown-item" href="index.php?page=vendors"><i class="bi bi-truck"></i> Vendors</a></li>
                        <li><a class="dropdown-item" href="index.php?page=inventory_logs"><i class="bi bi-clock-history"></i> Stock History</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="mgmtDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-briefcase"></i> Management
                    </a>
                    <ul class="dropdown-menu shadow" aria-labelledby="mgmtDropdown">
                        <li><a class="dropdown-item" href="index.php?page=reports"><i class="bi bi-bar-chart"></i> Master Reports</a></li>
                        <li><a class="dropdown-item fw-bold text-success" href="index.php?page=z_read"><i class="bi bi-journal-check"></i> End of Day (Z-Read)</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php?page=users"><i class="bi bi-people"></i> Users & Staff</a></li>
                        <li><a class="dropdown-item" href="index.php?page=locations"><i class="bi bi-geo-alt"></i> Locations</a></li>
                        <li><a class="dropdown-item" href="index.php?page=tables"><i class="bi bi-grid-3x3-gap"></i> Table Settings</a></li>
                    </ul>
                </li>
                <?php endif; ?>

                <?php if (in_array($tier, ['pro', 'hospitality'])): ?>
                <li class="nav-item ms-3">
                    <a class="nav-link text-warning fw-bold" href="index.php?page=kds"><i class="bi bi-fire"></i> Kitchen Display</a>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="d-flex align-items-center">
                <span class="text-light me-4"><i class="bi bi-person-circle text-warning"></i> <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?></span>
                <a href="index.php?action=logout" class="btn btn-outline-danger btn-sm fw-bold"><i class="bi bi-power"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>
<div class="container-fluid px-4">

<?php else: ?>
<div class="container-fluid p-2 pt-3">
<?php endif; ?>
