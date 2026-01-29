<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KDS - Kitchen Display</title>
    
    <meta http-equiv="refresh" content="15">

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

    <style>
        body { 
            background-color: #212529; 
            color: #fff; 
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .ticket-card { 
            background: #fff; 
            color: #000; 
            min-height: 300px; 
            border: none;
        }
        /* Status Indicators */
        .status-pending { border-top: 5px solid #dc3545; }
        .status-cooking { border-top: 5px solid #ffc107; background-color: #fffbf0; }
        
        /* Action Buttons */
        .btn-action { width: 40px; height: 40px; display: flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="p-3">

    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom border-secondary pb-3">
        <h3 class="text-white m-0">
            <i class="bi bi-fire text-warning"></i> Kitchen Display System
        </h3>
        <div>
            <a href="index.php?page=dashboard" class="btn btn-outline-light btn-sm me-2">Exit</a>
            <a href="index.php?page=kds" class="btn btn-warning btn-sm fw-bold">
                <i class="bi bi-arrow-clockwise"></i> Refresh
            </a>
        </div>
    </div>

    <div class="row overflow-auto flex-nowrap pb-4" style="min-height: 80vh;">
        
        <?php if (empty($orders)): ?>
            <div class="col-12 text-center text-muted mt-5 pt-5">
                <h1 style="font-size: 4rem;">👨‍🍳</h1>
                <h3>All caught up! No active orders.</h3>
            </div>
        <?php endif; ?>

        <?php foreach ($orders as $order): ?>
        <div class="col-md-3 col-sm-6 mb-3" style="min-width: 300px;">
            <div class="card shadow ticket-card">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold fs-5">#<?= $order['id'] ?></span>
                    <span class="badge bg-secondary"><?= date('H:i', strtotime($order['time'])) ?></span>
                </div>
                
                <div class="card-body p-0">
                    <div class="p-2 bg-light border-bottom">
                        <small class="text-muted fw-bold text-uppercase">Waiter</small>
                        <div class="fw-bold text-dark"><?= htmlspecialchars($order['waiter']) ?></div>
                    </div>
                    
                    <ul class="list-group list-group-flush">
                        <?php foreach ($order['items'] as $item): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center 
                            <?= $item['item_status'] == 'cooking' ? 'bg-warning-subtle' : '' ?>">
                            
                            <div class="d-flex align-items-center">
                                <span class="badge bg-dark rounded-pill me-2 fs-6"><?= $item['quantity'] ?></span>
                                <span class="fw-bold" style="font-size: 1.1rem;"><?= htmlspecialchars($item['product_name']) ?></span>
                            </div>

                            <div class="btn-group">
                                <?php if ($item['item_status'] === 'pending'): ?>
                                    <form method="POST" action="index.php?page=kds">
                                        <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                        <input type="hidden" name="new_status" value="cooking">
                                        <button class="btn btn-outline-danger btn-action rounded-circle" title="Start Cooking">
                                            <i class="bi bi-fire"></i>
                                        </button>
                                    </form>
                                <?php elseif ($item['item_status'] === 'cooking'): ?>
                                    <form method="POST" action="index.php?page=kds">
                                        <input type="hidden" name="item_id" value="<?= $item['item_id'] ?>">
                                        <input type="hidden" name="new_status" value="ready">
                                        <button class="btn btn-success btn-action rounded-circle" title="Mark Ready">
                                            <i class="bi bi-check-lg fs-4"></i>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                
                <div class="card-footer text-center">
                    <?php 
                        $mins = round((time() - strtotime($order['time'])) / 60);
                        $alertClass = $mins > 20 ? 'text-danger fw-bold' : 'text-muted';
                    ?>
                    <small class="<?= $alertClass ?>"><i class="bi bi-clock"></i> <?= $mins ?> mins ago</small>
                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>

</body>
</html>