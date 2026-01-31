<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KDS - Kitchen Display</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body { background-color: #343a40; color: #f8f9fa; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .ticket-card {
            background-color: #ffffff; 
            color: #212529; 
            border-radius: 8px;
            border-top: 5px solid #6c757d; 
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
        }
        .ticket-header { background-color: #f8f9fa; border-bottom: 2px dashed #dee2e6; padding: 10px 15px; border-radius: 8px 8px 0 0; }
        .ticket-body { 
            max-height: 50vh; 
            overflow-y: auto; 
            padding: 0; 
        }
        .ticket-item { border-bottom: 1px solid #f1f1f1; padding: 10px 15px; }
        .ticket-item:last-child { border-bottom: none; }
        .btn-action { width: 100%; border-radius: 4px; font-weight: bold; }
        .timer-safe { color: #198754; } .timer-warn { color: #fd7e14; } .timer-danger { color: #dc3545; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }
    </style>
</head>
<body class="d-flex flex-column p-3 vh-100">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0 fw-bold"><i class="bi bi-grid-3x3-gap-fill text-warning"></i> KITCHEN DISPLAY</h4>
        <div class="d-flex align-items-center gap-3">
            <div class="fs-4 font-monospace bg-dark px-3 py-1 rounded text-warning" id="clock"><?= date('H:i') ?></div>
            <a href="index.php?page=dashboard" class="btn btn-outline-light">
                Exit to Dashboard
            </a>
        </div>
    </div>

    <div class="d-flex flex-nowrap overflow-auto gap-3 h-100 pb-2 align-items-start">
        
        <?php foreach ($orders as $order): ?>
        <?php 
            $mins = round((time() - strtotime($order['time'])) / 60);
            $timerClass = ($mins > 20) ? 'timer-danger' : (($mins > 10) ? 'timer-warn' : 'timer-safe');
            $borderClass = ($mins > 20) ? 'border-danger' : (($mins > 10) ? 'border-warning' : 'border-secondary');
        ?>
        
        <div style="min-width: 300px; max-width: 300px;">
            <div class="ticket-card d-flex flex-column" style="border-top-color: var(--bs-<?= explode('-', $borderClass)[1] ?>);">
                <div class="ticket-header d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="m-0 fw-bold">#<?= $order['id'] ?></h5>
                        <small class="text-muted text-uppercase fw-bold"><?= htmlspecialchars($order['waiter']) ?></small>
                    </div>
                    <div class="text-end">
                        <div class="fs-5 fw-bold <?= $timerClass ?>"><?= $mins ?>m</div>
                        <small class="text-muted"><?= date('H:i', strtotime($order['time'])) ?></small>
                    </div>
                </div>

                <div class="ticket-body">
                    <?php foreach ($order['items'] as $item): ?>
                    <div class="ticket-item">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="fw-bold"><?= htmlspecialchars($item['name']) ?></span>
                            <span class="badge bg-dark rounded-pill"><?= $item['qty'] ?></span>
                        </div>
                        <?php if ($item['status'] === 'pending'): ?>
                            <button onclick="updateStatus(<?= $item['id'] ?>, 'cooking', this)" class="btn btn-sm btn-outline-dark btn-action">START COOKING</button>
                        <?php elseif ($item['status'] === 'cooking'): ?>
                            <button onclick="updateStatus(<?= $item['id'] ?>, 'ready', this)" class="btn btn-sm btn-success btn-action text-white">MARK READY</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($orders)): ?>
        <div class="w-100 d-flex flex-column justify-content-center align-items-center text-muted opacity-50 mt-5">
            <i class="bi bi-check-circle display-1"></i><h3 class="mt-3">All Clear</h3>
        </div>
        <?php endif; ?>

    </div>

    <script>
        setInterval(() => { document.getElementById('clock').innerText = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}); }, 1000);
        setTimeout(() => { location.reload(); }, 15000);
        function updateStatus(itemId, newStatus, btn) {
            btn.disabled = true; btn.innerText = '...';
            let formData = new FormData(); formData.append('item_id', itemId); formData.append('status', newStatus);
            fetch('index.php?page=kds', { method: 'POST', body: formData }).then(() => location.reload());
        }
    </script>
</body>
</html>
