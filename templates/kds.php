<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KDS - Kitchen Display</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        /* SOFTER DARK BACKGROUND */
        body { 
            background-color: #343a40; /* Slate Grey */
            color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* TICKET CARD STYLING */
        .ticket-card {
            background-color: #ffffff; /* White Paper Look */
            color: #212529; /* Dark text for readability */
            border-radius: 8px;
            border-top: 5px solid #6c757d; /* Default Grey Top Border */
            box-shadow: 0 4px 6px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }

        /* HEADER STYLES */
        .ticket-header {
            background-color: #f8f9fa;
            border-bottom: 2px dashed #dee2e6; /* Perforated line look */
            padding: 10px 15px;
            border-radius: 8px 8px 0 0;
        }

        /* SCROLLABLE BODY - Fixes "Too Long" issue */
        .ticket-body {
            max-height: 45vh; /* Takes up about half the screen height max */
            overflow-y: auto;
            padding: 0;
        }

        /* ITEM ROWS */
        .ticket-item {
            border-bottom: 1px solid #f1f1f1;
            padding: 12px 15px;
        }
        .ticket-item:last-child { border-bottom: none; }

        /* BUTTONS */
        .btn-action { width: 100px; font-weight: bold; border-radius: 20px; }
        
        /* TIMER COLORS */
        .timer-safe { color: #198754; font-weight: bold; }   /* Green */
        .timer-warn { color: #fd7e14; font-weight: bold; }   /* Orange */
        .timer-danger { color: #dc3545; font-weight: bold; animation: pulse 2s infinite; } /* Red */

        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.6; } 100% { opacity: 1; } }

        /* SCROLLBAR CLEANUP */
        .ticket-body::-webkit-scrollbar { width: 6px; }
        .ticket-body::-webkit-scrollbar-track { background: #f1f1f1; }
        .ticket-body::-webkit-scrollbar-thumb { background: #adb5bd; border-radius: 3px; }
    </style>
</head>
<body class="d-flex flex-column p-3 vh-100 overflow-hidden">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="m-0 fw-bold"><i class="bi bi-grid-3x3-gap-fill text-warning"></i> KITCHEN DISPLAY</h4>
        <div class="fs-4 font-monospace bg-dark px-3 py-1 rounded text-warning" id="clock"><?= date('H:i') ?></div>
    </div>

    <div class="d-flex flex-nowrap overflow-auto gap-3 h-100 pb-2">
        
        <?php foreach ($orders as $order): ?>
        <?php 
            // Calculate time elapsed
            $mins = round((time() - strtotime($order['time'])) / 60);
            $timerClass = ($mins > 20) ? 'timer-danger' : (($mins > 10) ? 'timer-warn' : 'timer-safe');
            $borderClass = ($mins > 20) ? 'border-danger' : (($mins > 10) ? 'border-warning' : 'border-secondary');
        ?>
        
        <div style="min-width: 350px; max-width: 350px;">
            <div class="ticket-card d-flex flex-column h-100" style="border-top-color: var(--bs-<?= explode('-', $borderClass)[1] ?>);">
                
                <div class="ticket-header d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="m-0 fw-bold">#<?= $order['id'] ?></h4>
                        <small class="text-muted text-uppercase fw-bold"><?= htmlspecialchars($order['waiter']) ?></small>
                    </div>
                    <div class="text-end">
                        <div class="fs-4 <?= $timerClass ?>"><?= $mins ?>m</div>
                        <small class="text-muted"><?= date('H:i', strtotime($order['time'])) ?></small>
                    </div>
                </div>

                <div class="ticket-body flex-grow-1">
                    <?php foreach ($order['items'] as $item): ?>
                    <div class="ticket-item d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center" style="max-width: 60%;">
                            <span class="badge bg-dark rounded-pill me-2 fs-6"><?= $item['qty'] ?></span>
                            <span class="fw-bold" style="line-height: 1.2;"><?= htmlspecialchars($item['name']) ?></span>
                        </div>
                        
                        <?php if ($item['status'] === 'pending'): ?>
                            <button onclick="updateStatus(<?= $item['id'] ?>, 'cooking', this)" class="btn btn-sm btn-outline-dark btn-action">
                                <i class="bi bi-fire"></i> COOK
                            </button>
                        <?php elseif ($item['status'] === 'cooking'): ?>
                            <button onclick="updateStatus(<?= $item['id'] ?>, 'ready', this)" class="btn btn-sm btn-success btn-action text-white">
                                <i class="bi bi-check-lg"></i> READY
                            </button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="p-2 bg-light rounded-bottom text-center small text-muted border-top">
                    <?= count($order['items']) ?> items
                </div>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if (empty($orders)): ?>
        <div class="w-100 d-flex flex-column justify-content-center align-items-center text-muted opacity-50">
            <i class="bi bi-check-circle display-1"></i>
            <h2 class="mt-3">All Clear</h2>
        </div>
        <?php endif; ?>

    </div>

    <script>
        // Clock
        setInterval(() => {
            const now = new Date();
            document.getElementById('clock').innerText = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        }, 1000);

        // Auto Refresh
        setTimeout(() => { location.reload(); }, 15000);

        // AJAX Update
        function updateStatus(itemId, newStatus, btn) {
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
            
            let formData = new FormData();
            formData.append('item_id', itemId);
            formData.append('status', newStatus);

            fetch('index.php?page=kds', { method: 'POST', body: formData })
            .then(() => location.reload())
            .catch(() => { btn.disabled = false; alert("Connection Error"); });
        }
    </script>
</body>
</html>
