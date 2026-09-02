<?php
if (isset($_POST['change_global_location']) && isset($_SESSION['user_id']) && isset($pdo)) {
    $newLocId = (int)$_POST['change_global_location'];
    $stmt = $pdo->prepare("SELECT name FROM locations WHERE id = ?");
    $stmt->execute([$newLocId]);
    $newLocName = $stmt->fetchColumn();
    if ($newLocName) {
        $_SESSION['location_id'] = $newLocId;
        $_SESSION['location_name'] = $newLocName;
        $_SESSION['pos_location_id'] = $newLocId; 
    }
    header("Location: " . $_SERVER['REQUEST_URI']);
    exit;
}

$allGlobalLocations = [];
if (isset($pdo)) {
    $allGlobalLocations = $pdo->query("SELECT id, name FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars(APP_NAME ?? 'OdeliaPOS') ?></title>
    <link href="vendor/bootstrap/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="vendor/bootstrap-icons/bootstrap-icons.css">
    <script src="vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <script src="js/app-alerts.js"></script>
    <style>
        body { background-color: #f4f6f9; }
        @media (min-width: 768px) { .navbar .dropdown:hover .dropdown-menu { display: block; margin-top: 0; } }
        .hover-location:hover { background-color: rgba(255, 193, 7, 0.1); border-radius: 5px; }
        .navbar-toggler { border: 2px solid #ffc107; color: #ffc107; font-weight: 700; }
        .navbar-toggler:focus { box-shadow: 0 0 0 0.2rem rgba(255,193,7,0.35); }
        .navbar-nav { flex-wrap: wrap; }
        @media (max-width: 767.98px) {
            .navbar .container-fluid { padding-left: 0.75rem; padding-right: 0.75rem; }
            .navbar-collapse { padding-top: 0.5rem; }
            .navbar-nav .dropdown-menu { position: static; float: none; width: 100%; box-shadow: none; border: 0; background: #2b3035; }
            .navbar-nav .dropdown-item { color: #fff; }
            .navbar-nav .dropdown-item:hover { background: #ffc107; color: #000; }
            .navbar .d-flex.align-items-center { flex-wrap: wrap; gap: 0.5rem !important; padding: 0.75rem 0 0.25rem; }
            .navbar .d-flex.align-items-center .me-4 { margin-right: 0.5rem !important; }
            .container-fluid.px-4 { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
        }
        @media (min-width: 768px) and (max-width: 1199.98px) {
            .navbar-nav .nav-link { padding-left: 0.5rem; padding-right: 0.5rem; }
            .navbar-brand { font-size: 1rem; }
        }
    </style>
</head>
<body>

<?php if(defined('SYSTEM_LOCKED') && SYSTEM_LOCKED && $_SESSION['role'] !== 'dev'): ?>
<div class="bg-danger text-white text-center fw-bold p-2 mb-3 shadow-sm" style="font-size:1.1em; letter-spacing:1px; z-index: 1000; position:relative;">
    <i class="bi bi-exclamation-triangle-fill me-2"></i> License expired — the system is in read-only mode. <i class="bi bi-exclamation-triangle-fill ms-2"></i>
</div>
<?php endif; ?>

<?php
$role = $_SESSION['role'] ?? 'cashier';
$tier = defined('LICENSE_TIER') ? LICENSE_TIER : 'lite';
$isEmbedded = isset($_GET['embedded']) && $_GET['embedded'] == '1';
?>

<?php if (!$isEmbedded): ?>

<div class="modal fade" id="globalLocationModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content shadow-lg border-warning">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold">Select Workstation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-3 bg-light">
                <form method="POST">
                    <?php foreach($allGlobalLocations as $loc): $isActive = (($_SESSION['location_id'] ?? 0) == $loc['id']); ?>
                        <button name="change_global_location" value="<?= $loc['id'] ?>" class="btn btn-white border w-100 mb-2 py-2 fw-bold text-start shadow-sm <?= $isActive ? 'border-warning bg-warning bg-opacity-10' : '' ?>">
                            <i class="bi bi-geo-alt-fill <?= $isActive ? 'text-warning' : 'text-secondary' ?> me-2"></i> <?= htmlspecialchars($loc['name']) ?>
                        </button>
                    <?php endforeach; ?>
                </form>
            </div>
        </div>
    </div>
</div>

<nav class="navbar navbar-expand-md navbar-dark bg-dark border-bottom border-warning border-3 shadow-sm mb-4">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-warning d-flex align-items-center" href="index.php">
            <i class="bi bi-box-seam me-2"></i> <?= htmlspecialchars(APP_NAME ?? 'OdeliaPOS') ?>
            <span class="badge bg-danger ms-2 border border-light shadow-sm" style="font-size: 0.6rem; vertical-align: middle; letter-spacing: 1px;"><?= strtoupper($tier) ?></span>
        </a>
        
        <button class="navbar-toggler d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#topNav" aria-controls="topNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
            <span class="text-warning">Menu</span>
        </button>
        <div class="collapse navbar-collapse" id="topNav">
        
        <?php if(in_array($role, ['admin', 'manager', 'dev'])): ?>
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="index.php?page=dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>                
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="invDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-boxes"></i> Inventory
                    </a>
                    <ul class="dropdown-menu shadow">
                        <li><a class="dropdown-item" href="index.php?page=products"><i class="bi bi-tags"></i> Products & Menu</a></li>
                        <li><a class="dropdown-item" href="index.php?page=unspsc"><i class="bi bi-upc-scan"></i> UNSPSC Codes</a></li>
                        <li><a class="dropdown-item" href="index.php?page=categories"><i class="bi bi-folder"></i> Categories</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item fw-bold" href="index.php?page=inventory"><i class="bi bi-bar-chart-line"></i> Stock Levels</a></li>
                        <li><a class="dropdown-item fw-bold text-primary" href="index.php?page=purchasing&tab=received"><i class="bi bi-box-arrow-in-down"></i> Goods Received</a></li>
                        <li><a class="dropdown-item fw-bold text-info" href="index.php?page=transfers"><i class="bi bi-arrow-left-right"></i> Transfer Stock</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="index.php?page=purchasing&tab=inquiry"><i class="bi bi-question-circle"></i> Purchase Inquiry</a></li>
                        <li><a class="dropdown-item" href="index.php?page=purchasing&tab=orders"><i class="bi bi-receipt"></i> Purchase Orders</a></li>
                        <li><a class="dropdown-item" href="index.php?page=vendors"><i class="bi bi-truck"></i> Vendors</a></li>
                        <li><a class="dropdown-item" href="index.php?page=inventory_logs"><i class="bi bi-clock-history"></i> Stock History</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="mgmtDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-briefcase"></i> Management
                    </a>
                    <ul class="dropdown-menu shadow">
                        <li><a class="dropdown-item" href="index.php?page=reports"><i class="bi bi-bar-chart"></i> Master Reports</a></li>
                        <li><a class="dropdown-item text-danger fw-bold" href="index.php?page=report_consumption"><i class="bi bi-pie-chart"></i> Recipe Consumption</a></li>
                        <li><a class="dropdown-item fw-bold text-success" href="index.php?page=z_read"><i class="bi bi-journal-check"></i> End of Day (Z-Read)</a></li>
                        <?php if (in_array($tier, ['enterprise']) || in_array($role, ['admin', 'dev'])): ?>
                        <li><a class="dropdown-item fw-bold text-success" href="index.php?page=zra"><i class="bi bi-shield-check"></i> ZRA Smart Invoice</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item fw-bold" href="index.php?page=shifts"><i class="bi bi-clock-history"></i> Shifts & X-Reads</a></li>
                        <li><a class="dropdown-item" href="index.php?page=users"><i class="bi bi-people"></i> Users & Staff</a></li>
                        <li><a class="dropdown-item" href="index.php?page=locations"><i class="bi bi-geo-alt"></i> Locations</a></li>
                        
                        <?php if (in_array($tier, ['pro+', 'enterprise'])): ?>
                        <li><a class="dropdown-item" href="index.php?page=tables"><i class="bi bi-grid-3x3-gap"></i> Table Settings</a></li>
                        <?php endif; ?>

                        <?php if($role === 'dev'): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item fw-bold text-danger" href="index.php?page=settings"><i class="bi bi-gear-fill"></i> System Settings</a></li>
                        <?php endif; ?>
                    </ul>
                </li>

                <?php if (in_array($tier, ['pro+', 'enterprise'])): ?>
                <li class="nav-item">
                    <a class="nav-link text-warning fw-bold" href="index.php?page=kds"><i class="bi bi-fire"></i> Kitchen Display</a>
                </li>
                <?php endif; ?>
                <li class="nav-item">
                    <a class="nav-link fw-bold text-success border border-success rounded px-3 my-1" href="index.php?page=pos"><i class="bi bi-cart-check"></i> LAUNCH POS</a>
                </li>
            </ul>
        
        <?php else: ?>
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <?php if (in_array($role, ['chef', 'head_chef']) && in_array($tier, ['pro+', 'enterprise'])): ?>
                <li class="nav-item">
                    <a class="nav-link text-warning fw-bold fs-5" href="index.php?page=kds"><i class="bi bi-display"></i> Live KDS Screen</a>
                </li>
                <li class="nav-item ms-3">
                    <a class="nav-link text-light fw-bold fs-5" href="index.php?page=kitchen"><i class="bi bi-journal-text"></i> Recipe/Prep Log</a>
                </li>
                <li class="nav-item ms-3">
                    <a class="nav-link text-success fw-bold fs-5" href="index.php?page=menu"><i class="bi bi-list-ul"></i> Menu Builder</a>
                </li>
                <li class="nav-item ms-3">
                    <a class="nav-link text-info fw-bold fs-5" href="index.php?page=inventory"><i class="bi bi-bar-chart-line"></i> Stock Levels</a>
                </li>
                <?php endif; ?>
            </ul>
        <?php endif; ?>

            <div class="d-flex align-items-center ms-auto">
                <span class="text-light me-4 fw-bold p-2 hover-location" style="cursor:pointer; transition:0.2s;" data-bs-toggle="modal" data-bs-target="#globalLocationModal" title="Change Workstation">
                    <i class="bi bi-geo-alt-fill text-warning me-1"></i> <?= htmlspecialchars($_SESSION['location_name'] ?? 'HQ') ?>
                    <i class="bi bi-pencil-square text-secondary ms-1" style="font-size:0.8em;"></i>
                </span>
                <span class="text-light me-4 fw-bold border-start border-secondary ps-4">
                    <i class="bi bi-person-badge text-info me-1"></i> <?= htmlspecialchars(strtoupper($role)) ?>: <?= htmlspecialchars($_SESSION['username'] ?? '') ?>
                </span>
                <a href="index.php?action=logout" class="btn btn-outline-danger btn-sm fw-bold"><i class="bi bi-power"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>
<div class="container-fluid px-4">
<?php else: ?>
<div class="container-fluid p-2 pt-3">
<?php endif; ?>
