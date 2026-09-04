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
$bayWaiting = 0;
if (isset($pdo)) {
    $allGlobalLocations = $pdo->query("SELECT id, name FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    try {
        $bayWaiting = \App\Models\ReceivingBay::dockedCount();
    } catch (\Throwable $e) {
        $bayWaiting = 0;
    }
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
    <link rel="stylesheet" href="vendor/flatpickr/flatpickr.min.css">
    <script src="vendor/jquery/jquery.min.js"></script>
    <script src="vendor/sweetalert2/sweetalert2.all.min.js"></script>
    <script src="js/app-alerts.js"></script>
    <style>
        body { background-color: #f4f6f9; }
        .app-navbar {
            background: linear-gradient(180deg, #1c2128 0%, #161a20 100%);
            border-bottom: 3px solid #ffc107;
            padding-top: 0.4rem;
            padding-bottom: 0.4rem;
            box-shadow: 0 4px 18px rgba(0,0,0,0.28);
        }
        .app-navbar .navbar-brand {
            letter-spacing: 0.03em;
            font-size: 1.08rem;
            padding-right: 0.85rem;
            margin-right: 0.35rem;
            border-right: 1px solid rgba(255,193,7,0.28);
        }
        .app-navbar .nav-link {
            color: rgba(255,255,255,0.82) !important;
            font-weight: 600;
            font-size: 0.92rem;
            padding: 0.45rem 0.75rem !important;
            border-radius: 0.45rem;
            margin-right: 0.15rem;
        }
        .app-navbar .nav-link:hover,
        .app-navbar .nav-link:focus,
        .app-navbar .nav-link.show {
            color: #fff !important;
            background: rgba(255,255,255,0.08);
        }
        .app-navbar .dropdown-menu {
            border: 0;
            border-radius: 0.65rem;
            padding: 0.45rem;
            margin-top: 0.35rem;
        }
        .app-navbar .dropdown-item {
            border-radius: 0.4rem;
            padding: 0.45rem 0.75rem;
            font-weight: 600;
        }
        .app-navbar .dropdown-item:hover { background: #fff8e1; }
        @media (min-width: 768px) {
            .navbar .dropdown:hover .dropdown-menu { display: block; margin-top: 0; }
            .app-navbar .navbar-nav { flex-wrap: nowrap; align-items: center; }
        }
        .hover-location:hover { background-color: rgba(255, 193, 7, 0.12); }
        .navbar-toggler { border: 2px solid #ffc107; color: #ffc107; font-weight: 700; }
        .navbar-toggler:focus { box-shadow: 0 0 0 0.2rem rgba(255,193,7,0.35); }
        .nav-pos-btn {
            background: #198754;
            color: #fff !important;
            border-radius: 2rem !important;
            padding: 0.4rem 1rem !important;
            border: 0 !important;
            margin-left: 0.35rem;
        }
        .nav-pos-btn:hover { background: #157347 !important; color: #fff !important; }
        .nav-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 2rem;
            padding: 0.28rem 0.75rem;
            color: #fff;
            font-size: 0.82rem;
            font-weight: 600;
            white-space: nowrap;
        }
        .nav-chip:hover { background: rgba(255, 193, 7, 0.15); border-color: rgba(255,193,7,0.45); color: #fff; }
        a.nav-chip { color: #fff; }
        a.nav-chip:hover { color: #fff; }
        @media (max-width: 767.98px) {
            .navbar .container-fluid { padding-left: 0.75rem; padding-right: 0.75rem; }
            .navbar-collapse { padding-top: 0.5rem; }
            .navbar-nav .dropdown-menu { position: static; float: none; width: 100%; box-shadow: none; border: 0; background: #2b3035; }
            .navbar-nav .dropdown-item { color: #fff; }
            .navbar-nav .dropdown-item:hover { background: #ffc107; color: #000; }
            .app-navbar .nav-toolbar { flex-wrap: wrap; gap: 0.5rem !important; padding: 0.75rem 0 0.25rem; }
            .container-fluid.px-4 { padding-left: 0.75rem !important; padding-right: 0.75rem !important; }
            .nav-pos-btn { margin-left: 0; margin-top: 0.35rem; }
        }
        @media (min-width: 768px) and (max-width: 1199.98px) {
            .navbar-nav .nav-link { padding-left: 0.45rem; padding-right: 0.45rem; }
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
$canSeeVsiasApprovals = in_array($role, ['admin', 'manager', 'dev'], true);
$vsiasPending = 0;
$vsiasPendingUrl = \App\Helpers\Vsias::pendingApprovalsUrl();
if ($canSeeVsiasApprovals && !$isEmbedded) {
    try {
        $vsiasPending = \App\Helpers\Vsias::pendingApprovalCount();
    } catch (\Throwable $e) {
        $vsiasPending = 0;
    }
}
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

<nav class="navbar navbar-expand-md navbar-dark app-navbar shadow-sm mb-4 sticky-top">
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
                        <li><a class="dropdown-item fw-bold text-warning" href="index.php?page=receiving_bay"><i class="bi bi-box-arrow-in-down"></i> Receiving Bay<?php if ($bayWaiting > 0): ?> <span class="badge bg-warning text-dark ms-1"><?= (int)$bayWaiting ?></span><?php endif; ?></a></li>
                        <li><a class="dropdown-item fw-bold text-primary" href="index.php?page=purchasing&tab=received"><i class="bi bi-box-seam"></i> Goods Received</a></li>
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
                        <?php $vsiasUrl = rtrim((string)(getenv('VSIAS_URL') ?: 'http://localhost:8095'), '/'); ?>
                        <li>
                            <a class="dropdown-item fw-bold text-info js-vsias-inspect" href="<?= htmlspecialchars($vsiasPendingUrl) ?>" data-title="Pending V-SIAS approvals">
                                <i class="bi bi-clipboard-check"></i> Site inspection (V-SIAS Pro)
                                <?php if ($canSeeVsiasApprovals && $vsiasPending > 0): ?>
                                <span class="badge bg-danger ms-1"><?= (int)$vsiasPending ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
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
                    <a class="nav-link nav-pos-btn fw-bold" href="index.php?page=pos"><i class="bi bi-cart-check"></i> Launch POS</a>
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

            <div class="d-flex align-items-center ms-auto gap-2 nav-toolbar">
                <?php if ($canSeeVsiasApprovals): ?>
                <a href="<?= htmlspecialchars($vsiasPendingUrl) ?>" class="nav-chip position-relative text-decoration-none js-vsias-inspect" data-title="Pending V-SIAS approvals" title="Pending V-SIAS store approvals">
                    <i class="bi bi-bell-fill text-warning"></i>
                    Approvals
                    <?php if ($vsiasPending > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"><?= (int)$vsiasPending ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>
                <button type="button" class="nav-chip btn hover-location" data-bs-toggle="modal" data-bs-target="#globalLocationModal" title="Change workstation">
                    <i class="bi bi-geo-alt-fill text-warning"></i>
                    <?= htmlspecialchars($_SESSION['location_name'] ?? 'HQ') ?>
                </button>
                <span class="nav-chip d-none d-lg-inline-flex">
                    <i class="bi bi-person-badge text-info"></i>
                    <?= htmlspecialchars($_SESSION['username'] ?? '') ?>
                    <span class="text-white-50">· <?= htmlspecialchars(strtoupper($role)) ?></span>
                </span>
                <a href="index.php?action=logout" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold">Logout</a>
            </div>
        </div>
    </div>
</nav>
<?php if (!$isEmbedded): ?>
<div class="modal fade" id="vsiasInspectModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 96vw; width: 96vw; margin: 2vh auto;">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="height: 92vh;">
            <div class="modal-header py-2 bg-dark text-white flex-shrink-0">
                <h5 class="modal-title fw-bold" id="vsiasInspectTitle"><i class="bi bi-clipboard-check text-warning me-2"></i>V-SIAS inspection</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 position-relative" style="height: calc(92vh - 56px);">
                <div id="vsiasInspectLoading" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-white text-muted">
                    <div class="text-center">
                        <div class="spinner-border text-warning mb-2" role="status"></div>
                        <div class="fw-bold">Opening V-SIAS…</div>
                    </div>
                </div>
                <iframe id="vsiasInspectFrame" title="V-SIAS inspection" allow="camera; microphone; clipboard-write" referrerpolicy="origin" style="position:relative; width:100%; height:100%; min-height:calc(92vh - 56px); border:0; background:#fff;"></iframe>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<div class="container-fluid px-4">
<?php else: ?>
<div class="container-fluid p-2 pt-3">
<?php endif; ?>
