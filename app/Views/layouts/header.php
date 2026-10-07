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
$currentLocationCanSell = true;
$pendingLocEdits = 0;

if (isset($pdo)) {
    $allGlobalLocations = $pdo->query("SELECT id, name, can_sell FROM locations ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
    $currentLocId = $_SESSION['location_id'] ?? 0;
    if ($currentLocId) {
        $stmtLoc = $pdo->prepare("SELECT can_sell FROM locations WHERE id = ?");
        $stmtLoc->execute([$currentLocId]);
        $val = $stmtLoc->fetchColumn();
        if ($val !== false) {
            $currentLocationCanSell = (int)$val === 1;
        }
    }
    try {
        $bayWaiting = \App\Models\ReceivingBay::dockedCount();
    } catch (\Throwable $e) {
        $bayWaiting = 0;
    }

    $role = $_SESSION['role'] ?? 'cashier';
    if ($role === 'dev') {
        try {
            $stmtPending = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'locations' AND COLUMN_NAME = 'pending_edits'");
            $stmtPending->execute();
            if ((int)$stmtPending->fetchColumn() > 0) {
                $pendingLocEdits = (int)$pdo->query("SELECT COUNT(*) FROM locations WHERE pending_edits IS NOT NULL")->fetchColumn();
            }
        } catch (\Throwable $e) {}
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
    <script src="js/app-alerts.js?v=20260905b"></script>
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
            color: rgba(255,255,255,0.85) !important;
            font-weight: 600;
            font-size: 0.9rem;
            padding: 0.45rem 0.65rem !important;
            border-radius: 0.45rem;
            margin-right: 0.15rem;
            white-space: nowrap;
        }
        .app-navbar .nav-link:hover,
        .app-navbar .nav-link:focus,
        .app-navbar .nav-link.show {
            color: #fff !important;
            background: rgba(255,255,255,0.08);
        }
        .app-navbar .navbar-nav > .nav-item > .nav-link.active {
            color: #ffc107 !important;
            background: rgba(255,193,7,0.14);
            box-shadow: inset 0 -2px 0 #ffc107;
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
        .app-navbar .dropdown-item.active,
        .app-navbar .dropdown-item.active:hover {
            background: #ffc107 !important;
            color: #000 !important;
        }
        .app-navbar .dropdown-item.active .badge { background: #000 !important; color: #ffc107 !important; }
        .nav-pos-mini {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            background: #198754;
            color: #fff !important;
            border-radius: 2rem;
            padding: 0.35rem 0.85rem;
            font-weight: 700;
            font-size: 0.85rem;
            text-decoration: none;
            white-space: nowrap;
            margin-right: 0.5rem;
        }
        .nav-pos-mini:hover { background: #157347; color: #fff; }
        .nav-pos-mini.is-disabled { background: #6c757d; opacity: 0.65; cursor: not-allowed; }
        @media (min-width: 1200px) {
            .navbar .dropdown:hover .dropdown-menu { display: block; margin-top: 0; }
            .app-navbar .navbar-nav { flex-wrap: nowrap; align-items: center; }
        }
        .hover-location:hover { background-color: rgba(255, 193, 7, 0.12); }
        .navbar-toggler {
            border: 2px solid #ffc107;
            color: #ffc107;
            font-weight: 700;
            padding: 0.25rem 0.6rem;
            border-radius: 0.5rem;
        }
        .navbar-toggler:focus { box-shadow: 0 0 0 0.2rem rgba(255,193,7,0.35); }
        .nav-pos-btn {
            background: #198754;
            color: #fff !important;
            border-radius: 2rem !important;
            padding: 0.4rem 1.1rem !important;
            border: 0 !important;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
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
        a.nav-chip { color: #fff; text-decoration: none; }
        a.nav-chip:hover { color: #fff; }
        
        /* Mobile & Tablet (< 1200px) Layout Rules */
        @media (max-width: 1199.98px) {
            .navbar .container-fluid { padding-left: 0.75rem; padding-right: 0.75rem; }
            .navbar-collapse {
                padding-top: 0.75rem;
                max-height: calc(100vh - 72px);
                max-height: calc(100dvh - 72px);
                overflow-y: auto;
                -webkit-overflow-scrolling: touch;
            }
            .app-navbar .nav-link {
                padding: 0.7rem 0.85rem !important;
                font-size: 0.95rem;
                margin-right: 0;
                margin-bottom: 0.15rem;
                white-space: normal;
            }
            .app-navbar .navbar-nav > .nav-item > .nav-link.active {
                box-shadow: inset 3px 0 0 #ffc107;
            }
            .app-navbar .nav-link.dropdown-toggle { display: flex; align-items: center; }
            .app-navbar .nav-link.dropdown-toggle::after { margin-left: auto; transition: transform 0.2s; }
            .app-navbar .nav-link.dropdown-toggle.show::after { transform: rotate(180deg); }
            .navbar-nav .dropdown-menu {
                position: static; float: none; width: 100%; box-shadow: none; border: 0;
                background: #2b3035; margin: 0 0 0.5rem; padding: 0.35rem;
                border-left: 3px solid rgba(255,193,7,0.45);
            }
            .navbar-nav .dropdown-item { color: #fff; padding: 0.65rem 0.85rem; white-space: normal; }
            .navbar-nav .dropdown-item:hover { background: rgba(255,193,7,0.2); color: #fff; }
            .navbar-nav .dropdown-divider { border-color: rgba(255,255,255,0.12); }
            .app-navbar .nav-toolbar {
                flex-wrap: wrap;
                gap: 0.5rem !important;
                padding: 0.75rem 0 0.5rem;
                border-top: 1px solid rgba(255,255,255,0.1);
                margin-top: 0.5rem;
                width: 100%;
            }
            .app-navbar .nav-toolbar .nav-chip { flex: 1 1 auto; justify-content: center; min-height: 2.5rem; }
            .app-navbar .nav-toolbar .btn-outline-light { flex: 1 1 auto; min-height: 2.5rem; }
            .navbar-brand { border-right: none; margin-right: auto; padding-right: 0; }
        }
        @media (max-width: 575.98px) {
            .app-navbar .navbar-brand { font-size: 1rem; }
            .nav-pos-mini { padding: 0.35rem 0.65rem; }
            .nav-pos-mini .nav-pos-mini-label { display: none; }
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
$navPage = basename((string)($_GET['page'] ?? 'dashboard'));
$navTab = (string)($_GET['tab'] ?? '');
$navInventoryPages = ['products', 'unspsc', 'categories', 'inventory', 'receiving_bay', 'purchasing', 'transfers', 'vendors', 'inventory_logs'];
$navManagementPages = ['reports', 'report_consumption', 'z_read', 'zra', 'shifts', 'users', 'locations', 'tables', 'settings'];
$navItem = static function (string $page, ?string $tab = null) use ($navPage, $navTab): string {
    if ($navPage !== $page) {
        return '';
    }
    if ($tab !== null && $navTab !== $tab) {
        return '';
    }
    return ' active" aria-current="page';
};
$navGroup = static fn(array $pages): string => in_array($navPage, $pages, true) ? ' active' : '';
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
                <form method="POST" class="js-confirm-form"
                    data-swal-title="Switch workstation?"
                    data-swal-text="Stock, sales, and reports will use the location you pick."
                    data-swal-confirm="Switch"
                    data-swal-cancel="Cancel"
                    data-swal-icon="question"
                    data-swal-confirm-color="#fd7e14">
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

<nav class="navbar navbar-expand-xl navbar-dark app-navbar shadow-sm mb-4 sticky-top">
    <div class="container-fluid px-3 px-xl-4">
        <a class="navbar-brand fw-bold text-warning d-flex align-items-center" href="index.php">
            <i class="bi bi-box-seam me-2"></i> <?= htmlspecialchars(APP_NAME ?? 'OdeliaPOS') ?>
            <span class="badge bg-danger ms-2 border border-light shadow-sm d-none d-sm-inline" style="font-size: 0.6rem; vertical-align: middle; letter-spacing: 1px;"><?= strtoupper($tier) ?></span>
        </a>

        <!-- Mobile quick POS button: one tap without opening the menu -->
        <?php if (!in_array($role, ['chef', 'head_chef'], true)): ?>
            <?php if ($currentLocationCanSell): ?>
                <a class="nav-pos-mini d-xl-none" href="index.php?page=pos" title="Launch POS"><i class="bi bi-cart-check"></i><span class="nav-pos-mini-label">POS</span></a>
            <?php else: ?>
                <span class="nav-pos-mini is-disabled d-xl-none" title="POS is disabled at this workstation"><i class="bi bi-cart-x"></i><span class="nav-pos-mini-label">POS</span></span>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Menu button is explicitly hidden on screens 1200px and up -->
        <button class="navbar-toggler d-flex d-xl-none align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#topNav" aria-controls="topNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="text-warning small text-uppercase">Menu</span>
            <span class="navbar-toggler-icon" style="width: 1.1em; height: 1.1em;"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="topNav">
        <?php if(in_array($role, ['admin', 'manager', 'dev'])): ?>
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <li class="nav-item">
                    <a class="nav-link<?= $navItem('dashboard') ?>" href="index.php?page=dashboard"><i class="bi bi-speedometer2"></i> Dashboard</a>
                </li>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle<?= $navGroup($navInventoryPages) ?>" href="#" id="invDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-boxes"></i> Inventory
                    </a>
                    <ul class="dropdown-menu shadow">
                        <li><a class="dropdown-item<?= $navItem('products') ?>" href="index.php?page=products"><i class="bi bi-tags"></i> Products & Menu</a></li>
                        <li><a class="dropdown-item<?= $navItem('unspsc') ?>" href="index.php?page=unspsc"><i class="bi bi-upc-scan"></i> UNSPSC Codes</a></li>
                        <li><a class="dropdown-item<?= $navItem('categories') ?>" href="index.php?page=categories"><i class="bi bi-folder"></i> Categories</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item fw-bold<?= $navItem('inventory') ?>" href="index.php?page=inventory"><i class="bi bi-bar-chart-line"></i> Stock Levels</a></li>
                        <li><a class="dropdown-item fw-bold text-warning<?= $navItem('receiving_bay') ?>" href="index.php?page=receiving_bay"><i class="bi bi-box-arrow-in-down"></i> Receiving Bay<?php if ($bayWaiting > 0): ?> <span class="badge bg-warning text-dark ms-1"><?= (int)$bayWaiting ?></span><?php endif; ?></a></li>
                        <li><a class="dropdown-item fw-bold text-primary<?= $navItem('purchasing', 'received') ?>" href="index.php?page=purchasing&tab=received"><i class="bi bi-box-seam"></i> Goods Received</a></li>
                        <li><a class="dropdown-item fw-bold text-info<?= $navItem('transfers') ?>" href="index.php?page=transfers"><i class="bi bi-arrow-left-right"></i> Transfer Stock</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item<?= $navItem('purchasing', 'inquiry') ?>" href="index.php?page=purchasing&tab=inquiry"><i class="bi bi-question-circle"></i> Purchase Inquiry</a></li>
                        <li><a class="dropdown-item<?= $navItem('purchasing', 'orders') ?>" href="index.php?page=purchasing&tab=orders"><i class="bi bi-receipt"></i> Purchase Orders</a></li>
                        <li><a class="dropdown-item<?= $navItem('vendors') ?>" href="index.php?page=vendors"><i class="bi bi-truck"></i> Vendors</a></li>
                        <li><a class="dropdown-item<?= $navItem('inventory_logs') ?>" href="index.php?page=inventory_logs"><i class="bi bi-clock-history"></i> Stock History</a></li>
                    </ul>
                </li>

                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle<?= $navGroup($navManagementPages) ?>" href="#" id="mgmtDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="bi bi-briefcase"></i> Management
                        <?php if (!empty($pendingLocEdits)): ?>
                            <span class="badge bg-warning text-dark ms-1" title="Pending Developer Approval"><i class="bi bi-exclamation-circle-fill"></i></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu shadow">
                        <li><a class="dropdown-item<?= $navItem('reports') ?>" href="index.php?page=reports"><i class="bi bi-bar-chart"></i> Master Reports</a></li>
                        <li><a class="dropdown-item text-danger fw-bold<?= $navItem('report_consumption') ?>" href="index.php?page=report_consumption"><i class="bi bi-pie-chart"></i> Recipe Consumption</a></li>
                        <li><a class="dropdown-item fw-bold text-success<?= $navItem('z_read') ?>" href="index.php?page=z_read"><i class="bi bi-journal-check"></i> End of Day (Z-Read)</a></li>
                        <?php if (in_array($tier, ['enterprise']) || in_array($role, ['admin', 'dev'])): ?>
                        <li><a class="dropdown-item fw-bold text-success<?= $navItem('zra') ?>" href="index.php?page=zra"><i class="bi bi-shield-check"></i> ZRA Smart Invoice</a></li>
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
                        <li><a class="dropdown-item fw-bold<?= $navItem('shifts') ?>" href="index.php?page=shifts"><i class="bi bi-clock-history"></i> Shifts & X-Reads</a></li>
                        <li><a class="dropdown-item<?= $navItem('users') ?>" href="index.php?page=users"><i class="bi bi-people"></i> Users & Staff</a></li>

                        <li>
                            <a class="dropdown-item<?= $navItem('locations') ?>" href="index.php?page=locations">
                                <i class="bi bi-geo-alt"></i> Locations
                                <?php if (!empty($pendingLocEdits)): ?>
                                    <span class="badge bg-warning text-dark ms-1"><?= $pendingLocEdits ?> Pending</span>
                                <?php endif; ?>
                            </a>
                        </li>

                        <?php if (in_array($tier, ['pro+', 'enterprise'])): ?>
                        <li><a class="dropdown-item<?= $navItem('tables') ?>" href="index.php?page=tables"><i class="bi bi-grid-3x3-gap"></i> Table Settings</a></li>
                        <?php endif; ?>

                        <?php if($role === 'dev'): ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item fw-bold text-danger<?= $navItem('settings') ?>" href="index.php?page=settings"><i class="bi bi-gear-fill"></i> System Settings</a></li>
                        <?php endif; ?>
                    </ul>
                </li>

                <?php if (in_array($tier, ['pro+', 'enterprise'])): ?>
                <li class="nav-item">
                    <a class="nav-link text-warning fw-bold<?= $navItem('kds') ?>" href="index.php?page=kds"><i class="bi bi-fire"></i> Kitchen Display</a>
                </li>
                <?php endif; ?>
            </ul>

        <?php else: ?>
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <?php if (!in_array($role, ['chef', 'head_chef'], true)): ?>
                <li class="nav-item">
                    <a class="nav-link fw-bold<?= $navItem('transfers') ?>" href="index.php?page=transfers"><i class="bi bi-arrow-left-right"></i> Stock request</a>
                </li>
                <?php endif; ?>
                <?php if (in_array($role, ['chef', 'head_chef']) && in_array($tier, ['pro+', 'enterprise'])): ?>
                <li class="nav-item">
                    <a class="nav-link text-warning fw-bold fs-5<?= $navItem('kds') ?>" href="index.php?page=kds"><i class="bi bi-display"></i> Live KDS Screen</a>
                </li>
                <li class="nav-item ms-xl-3">
                    <a class="nav-link text-light fw-bold fs-5<?= $navItem('kitchen') ?>" href="index.php?page=kitchen"><i class="bi bi-journal-text"></i> Recipe/Prep Log</a>
                </li>
                <li class="nav-item ms-xl-3">
                    <a class="nav-link text-success fw-bold fs-5<?= $navItem('menu') ?>" href="index.php?page=menu"><i class="bi bi-list-ul"></i> Menu Builder</a>
                </li>
                <li class="nav-item ms-xl-3">
                    <a class="nav-link text-info fw-bold fs-5<?= $navItem('inventory') ?>" href="index.php?page=inventory"><i class="bi bi-bar-chart-line"></i> Stock Levels</a>
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
                <span class="nav-chip">
                    <i class="bi bi-person-badge text-info"></i>
                    <?= htmlspecialchars($_SESSION['username'] ?? '') ?>
                    <span class="text-white-50">· <?= htmlspecialchars(strtoupper($role)) ?></span>
                </span>
                <a href="index.php?action=logout" class="btn btn-sm btn-outline-light rounded-pill px-3 fw-bold">Logout</a>

                <!-- Desktop Launch POS (Only on >= 1200px) -->
                <?php if (!in_array($role, ['chef', 'head_chef'], true)): ?>
                <div class="d-none d-xl-block ms-2">
                    <?php if ($currentLocationCanSell): ?>
                        <a class="nav-link nav-pos-btn fw-bold" href="index.php?page=pos"><i class="bi bi-cart-check me-1"></i> Launch POS</a>
                    <?php else: ?>
                        <span class="nav-link nav-pos-btn fw-bold bg-secondary text-white-50" style="opacity: 0.65; cursor: not-allowed;" title="POS is disabled"><i class="bi bi-cart-x"></i> Disabled</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<script>
    // Mobile menu: close the panel after picking a page, and keep only one
    // dropdown group open at a time so the list stays short on phones.
    document.addEventListener('DOMContentLoaded', function () {
        var panel = document.getElementById('topNav');
        if (!panel || !window.bootstrap || !bootstrap.Collapse) {
            return;
        }
        panel.addEventListener('click', function (e) {
            var link = e.target.closest('a.nav-link, a.dropdown-item, a.btn');
            if (!link || link.getAttribute('data-bs-toggle') || link.classList.contains('js-vsias-inspect')) {
                return;
            }
            if (window.innerWidth < 1200 && panel.classList.contains('show')) {
                bootstrap.Collapse.getOrCreateInstance(panel).hide();
            }
        });
        panel.addEventListener('show.bs.dropdown', function (e) {
            if (window.innerWidth >= 1200) {
                return;
            }
            panel.querySelectorAll('.dropdown-toggle.show').forEach(function (toggle) {
                if (toggle !== e.target) {
                    bootstrap.Dropdown.getOrCreateInstance(toggle).hide();
                }
            });
        });
    });
</script>
<?php if (!$isEmbedded): ?>
<div class="modal fade" id="vsiasInspectModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" style="max-width: 96vw; width: 96vw; margin: 2vh auto;">
        <div class="modal-content border-0 shadow-lg overflow-hidden" style="height: 92vh;">
            <div class="modal-header py-2 bg-dark text-white flex-shrink-0">
                <h5 class="modal-title fw-bold" id="vsiasInspectTitle"><i class="bi bi-clipboard-check text-warning me-2"></i>V-SIAS inspection</h5>
                <a id="vsiasInspectNewTab" class="btn btn-sm btn-outline-warning fw-bold me-2" href="#" target="_blank" rel="noopener">Open in new tab</a>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 position-relative" style="height: calc(92vh - 56px);">
                <div id="vsiasInspectLoading" class="position-absolute top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center bg-white text-muted">
                    <div class="text-center px-3">
                        <div class="spinner-border text-warning mb-2" role="status"></div>
                        <div class="fw-bold">Opening V-SIAS…</div>
                        <div class="js-vsias-frame-hint small mt-2 d-none">If this stays empty, use <strong>Open in new tab</strong>.</div>
                    </div>
                </div>
                <iframe id="vsiasInspectFrame" title="V-SIAS inspection" allow="camera; microphone; clipboard-write" referrerpolicy="origin" style="position:relative; width:100%; height:100%; min-height:calc(92vh - 56px); border:0; background:#fff;"></iframe>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
<div class="container-fluid px-3 px-md-4">
<?php else: ?>
<div class="container-fluid p-2 pt-3">
<?php endif; ?>
