<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kitchen Display - <?= htmlspecialchars($locationName ?? 'KDS') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background-color: #2c2c2c; color: white; }
        .order-card { background: #fff; color: #333; border-radius: 8px; overflow: hidden; height: 100%; }
        .order-header { padding: 10px; font-weight: bold; display: flex; justify-content: space-between; align-items: center; }
        .bg-pending { background-color: #ffc107; }
        .bg-paid { background-color: #198754; color: white; }
        .order-items { padding: 10px; font-size: 0.95rem; }
        .order-footer { padding: 10px; background: #f8f9fa; border-top: 1px solid #eee; }
    </style>
</head>
<body>

<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold"><i class="bi bi-fire text-danger"></i> Kitchen Display System</h3>
        <div>
            <button class="btn btn-outline-warning fw-bold me-2" onclick="showPickupModal()">
                <i class="bi bi-tv"></i> Pickup Screen
            </button>
            <a href="index.php?page=dashboard" class="btn btn-outline-light btn-sm">Dashboard</a>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach($orders as $o): ?>
        <div class="col-md-4 col-lg-3">
            <div class="order-card shadow">
                <div class="order-header <?= $o['payment_status'] == 'paid' ? 'bg-paid' : 'bg-pending' ?>">
                    <span>#<?= $o['id'] ?> <?= htmlspecialchars($o['customer_name']) ?></span>
                    <small><?= date('H:i', strtotime($o['created_at'])) ?></small>
                </div>
                <div class="order-items">
                    <ul class="list-unstyled mb-0">
                        <?php 
                        $items = $pdo->query("SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = {$o['id']}")->fetchAll();
                        foreach($items as $i): 
                        ?>
                        <li class="d-flex justify-content-between border-bottom py-1">
                            <span><?= $i['quantity'] ?>x <?= htmlspecialchars($i['name']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="order-footer text-center">
                    <button class="btn btn-success btn-sm w-100 fw-bold">MARK READY</button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal fade" id="pickupModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content h-100">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title fw-bold"><i class="bi bi-bag-check-fill"></i> Orders Ready for Pickup</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0" style="height: 80vh;">
                <iframe id="pickupFrame" src="" style="width:100%; height:100%; border:none;"></iframe>
            </div>
            <div class="modal-footer bg-dark border-top-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showPickupModal() {
    document.getElementById('pickupFrame').src = "index.php?page=pickup&embedded=1";
    new bootstrap.Modal(document.getElementById('pickupModal')).show();
}
</script>
</body>
</html>
