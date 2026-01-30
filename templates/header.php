<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Odelia POS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <style>
        body { background-color: #f8f9fa; }
        .navbar-brand { font-weight: bold; letter-spacing: 1px; }
        .nav-link.active { font-weight: bold; color: #0d6efd !important; }
    </style>
</head>
<body>

<?php
$role = $_SESSION['role'] ?? 'cashier';
$isAdmin = in_array($role, ['admin', 'dev']);
$isManager = in_array($role, ['admin', 'manager', 'dev', 'head_chef']);
$isStaff = in_array($role, ['cashier', 'waiter', 'bartender']);
$isKitchen = in_array($role, ['chef', 'admin', 'head_chef', 'dev']);
$isChef = in_array($_SESSION['role'] ?? '', ['chef', 'head_chef', 'admin', 'dev']);
// Allow basically everyone except maybe Chef to see Pickup
$canPickup = in_array($role, ['admin', 'manager', 'dev', 'cashier', 'waiter', 'bartender']);

function isActive($p) { global $page; return $page === $p ? 'active' : ''; }
?>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4 shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php?page=dashboard">
            <i class="bi bi-layer-group"></i> Odelia<span class="text-primary">POS</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item"><a class="nav-link <?= isActive('dashboard') ?>" href="index.php?page=dashboard">Dashboard</a></li>

                <?php if ($isStaff || $isManager): ?>
                <li class="nav-item"><a class="nav-link <?= isActive('pos') ?>" href="index.php?page=pos"><i class="bi bi-cart4"></i> POS</a></li>
                <?php endif; ?>

                <?php if ($isChef): ?>
                    <li class="nav-item">
                        <a class="nav-link <?= isActive('menu') ?>" href="index.php?page=menu"><i class="bi bi-egg-fried"></i> Menu Manager</a>
                    </li>
                <?php endif; ?>

                <?php if ($isKitchen): ?>
                <li class="nav-item"><a class="nav-link <?= isActive('kds') ?>" href="index.php?page=kds"><i class="bi bi-fire"></i> Kitchen</a></li>
                <?php endif; ?>

                <?php if ($canPickup): ?>
                <li class="nav-item"><a class="nav-link <?= isActive('pickup') ?>" href="index.php?page=pickup"><i class="bi bi-bell"></i> Pickup</a></li>
                <?php endif; ?>

                <li class="nav-item"><a class="nav-link <?= isActive('shifts') ?>" href="index.php?page=shifts"><i class="bi bi-clock-history"></i> Shifts</a></li>
                <?php if ($isManager): ?><li class="nav-item"><a class="nav-link <?= isActive('reports') ?>" href="index.php?page=reports"><i class="bi bi-graph-up"></i> Reports</a></li><?php endif; ?>
                <?php if ($isManager): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Inventory</a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="index.php?page=products">Products</a></li>
                        <li><a class="dropdown-item" href="index.php?page=categories">Categories</a></li>
                        <li><a class="dropdown-item" href="index.php?page=inventory">Stock Levels</a></li>
                        <li><a class="dropdown-item" href="index.php?page=vendors">Manage Vendors</a></li><li><a class="dropdown-item" href="index.php?page=receive">Receive Stock (GRV)</a></li>
                        <li><a class="dropdown-item" href="index.php?page=transfers">Transfers</a></li><li><hr class="dropdown-divider"></li><li><a class="dropdown-item" href="index.php?page=locations">Locations</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a class="nav-link <?= isActive('users') ?>" href="index.php?page=users">Users</a></li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle text-light" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i> <?= htmlspecialchars($_SESSION['username']) ?> 
                        <span class="badge bg-primary rounded-pill"><?= ucfirst($role) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="index.php?page=change_password">Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="index.php?action=logout">Logout</a></li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">