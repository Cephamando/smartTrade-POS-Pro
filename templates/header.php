<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php $tier = defined('LICENSE_TIER') ? LICENSE_TIER : 'lite'; ?>
    <title><?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'OdeliaPOS') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root { 
            --theme-primary: <?= htmlspecialchars(defined('THEME_COLOR') ? THEME_COLOR : '#3e2723') ?>; 
            --theme-gold: #ffc107; 
            --theme-orange: #fd7e14; 
            --theme-bg: #f8f9fa; 
        }
        body { background-color: var(--theme-bg); color: #333; }
        .navbar-custom { background-color: var(--theme-primary); border-bottom: 3px solid var(--theme-gold); }
        .navbar-custom .navbar-brand { color: var(--theme-gold) !important; font-weight: bold; }
        .navbar-custom .nav-link { color: rgba(255,255,255,0.8) !important; }
        .navbar-custom .nav-link:hover { color: var(--theme-gold) !important; }
        .btn-theme-orange { background-color: var(--theme-orange); color: white; font-weight: bold; border: none; }
        .btn-theme-orange:hover { background-color: #e66b0d; color: white; }
        @media print { .no-print { display: none !important; } }
    </style>
</head>
<body>

    <?php
    $canReceive = false;
    if (isset($_SESSION['location_id']) && isset($pdo)) {
        $hStmt = $pdo->prepare("SELECT type, can_receive_from_vendor FROM locations WHERE id = ?");
        $hStmt->execute([$_SESSION['location_id']]);
        $hLoc = $hStmt->fetch();
        if ($hLoc && ($hLoc['type'] === 'warehouse' || $hLoc['type'] === 'kitchen' || $hLoc['can_receive_from_vendor'] == 1)) {
            $canReceive = true;
        }
    }
    ?>

    <?php if (!isset($_GET['embedded'])): ?>
    <nav class="navbar navbar-expand-lg navbar-custom mb-4 shadow-sm no-print">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php?page=dashboard"><i class="bi bi-grid-fill"></i> <?= htmlspecialchars(defined('APP_NAME') ? APP_NAME : 'OdeliaPOS') ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav"><span class="navbar-toggler-icon"></span></button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="index.php?page=dashboard">Dashboard</a></li>
                    <li class="nav-item"><a class="nav-link" href="index.php?page=pos">POS</a></li>
                    
                    <?php if (in_array($tier, ['pro', 'hospitality'])): ?>
                    <li class="nav-item">
                        <a class="nav-link" href="index.php?page=pickup">Pickup <span id="navReadyBadge" class="badge bg-danger rounded-pill" style="display:none; font-size:0.65rem; margin-left: 2px;">0</span></a>
                    </li>
                    <?php endif; ?>
                    
                    <?php if ($tier === 'hospitality'): ?>
                    <li class="nav-item"><a class="nav-link" href="index.php?page=kds">Kitchen</a></li>
                    <?php endif; ?>
                    
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Inventory</a>
                        <ul class="dropdown-menu shadow">
                            <li><a class="dropdown-item" href="index.php?page=inventory">Stock Levels</a></li>
                            <li><a class="dropdown-item" href="index.php?page=categories">Categories</a></li>
                            <?php if($canReceive && in_array($tier, ['pro', 'hospitality'])): ?>
                                <li><a class="dropdown-item" href="index.php?page=receive_stock">Receive Stock (GRV)</a></li>
                            <?php endif; ?>
                            <?php if (in_array($tier, ['pro', 'hospitality'])): ?>
                            <li><a class="dropdown-item" href="index.php?page=transfers">Transfers</a></li>
                            <?php endif; ?>
                        </ul>
                    </li>

                    <?php if (in_array($tier, ['pro', 'hospitality'])): ?>
                    <li class="nav-item"><a class="nav-link" href="index.php?page=members">Members</a></li>
                    <?php endif; ?>
                    
                    <li class="nav-item"><a class="nav-link text-warning" href="index.php?page=print_shift">Shift Report</a></li>

                    <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'manager', 'dev'])): ?>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">Management</a>
                            <ul class="dropdown-menu shadow">
                                <li><a class="dropdown-item" href="index.php?page=reports">Reports</a></li>
                                <li><a class="dropdown-item text-info fw-bold" href="index.php?page=report_consumption"><i class="bi bi-moisture"></i> Recipe Consumption</a></li>
                                <?php if (in_array($tier, ['pro', 'hospitality'])): ?>
                                <li><a class="dropdown-item" href="index.php?page=audit">Audit Trail</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="index.php?page=users">Users</a></li>
                                <?php if (in_array($tier, ['pro', 'hospitality'])): ?>
                                <li><a class="dropdown-item" href="index.php?page=locations">Locations</a></li>
                                <?php endif; ?>
                                <?php if(isset($_SESSION['role']) && $_SESSION['role'] === 'dev'): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item fw-bold text-danger" href="index.php?page=settings"><i class="bi bi-gear-fill"></i> System Settings</a></li>
                                <?php endif; ?>
                            </ul>
                        </li>
                    <?php endif; ?>
                </ul>
                <div class="d-flex align-items-center">
                    <span class="text-white me-3 small d-flex align-items-center">
                        <i class="bi bi-geo-alt-fill text-gold me-1"></i> 
                        <span class="fw-bold"><?= htmlspecialchars($_SESSION['location_name'] ?? 'HQ') ?></span>
                        <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dev'])): ?>
                            <a href="#" class="text-warning ms-2" data-bs-toggle="modal" data-bs-target="#adminSwitchModal" title="Switch Location"><i class="bi bi-arrow-repeat"></i></a>
                        <?php endif; ?>
                        <span class="mx-2">|</span> <?= htmlspecialchars($_SESSION['username'] ?? 'User') ?>
                    </span>
                    <a href="index.php?action=logout" class="btn btn-sm btn-outline-warning ms-3">Logout</a>
                </div>
            </div>
        </div>
    </nav>

    <?php if(isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'dev'])): ?>
    <div class="modal fade" id="adminSwitchModal" tabindex="-1">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white py-2">
                    <h6 class="modal-title small text-uppercase">Admin Switch</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="index.php?page=dashboard">
                        <input type="hidden" name="global_switch_location" value="1">
                        <label class="form-label small fw-bold">Select Active Location</label>
                        <select name="target_location_id" class="form-select mb-3">
                            <?php 
                            if (!isset($allLocations)) {
                                global $pdo;
                                $allLocations = $pdo->query("SELECT * FROM locations ORDER BY name ASC")->fetchAll();
                            }
                            foreach($allLocations as $loc): 
                            ?>
                                <option value="<?= $loc['id'] ?>" <?= ($loc['id'] == $_SESSION['location_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($loc['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary w-100 btn-sm">Switch Now</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <div class="container-fluid <?= isset($_GET['embedded']) ? 'p-1' : 'px-4' ?>">

    <?php if (in_array($tier, ['pro', 'hospitality']) && !isset($_GET['embedded'])): ?>
    <script>
    function checkNavReadyOrders() {
        fetch('index.php?action=check_ready_orders')
        .then(r => r.json())
        .then(data => {
            let badge = document.getElementById('navReadyBadge');
            if(badge && data && data.count > 0) { 
                badge.innerText = data.count; 
                badge.style.display = 'inline-block'; 
            } else if (badge) { 
                badge.style.display = 'none'; 
            }
        }).catch(e => console.error("Nav Badge Error:", e));
    }
    document.addEventListener('DOMContentLoaded', function() {
        checkNavReadyOrders();
        setInterval(checkNavReadyOrders, 5000);
    });
    </script>
    <?php endif; ?>
