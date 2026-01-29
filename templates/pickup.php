<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pickup Screen</title>
    <meta http-equiv="refresh" content="15">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    
    <style>
        body { background-color: #212529; color: white; }
        .pickup-card { 
            background-color: #198754; 
            color: white; 
            border: none;
        }
        .order-id { font-size: 3rem; font-weight: bold; }
        .item-name { font-size: 1.5rem; }
    </style>
</head>
<body class="p-4">

    <div class="d-flex justify-content-between align-items-center mb-5 border-bottom border-secondary pb-3">
        <h1 class="display-4 fw-bold text-success">
            <i class="bi bi-bell-fill"></i> Ready for Collection
        </h1>
        <a href="index.php?page=dashboard" class="btn btn-outline-light">Exit Screen</a>
    </div>

    <div class="row g-4">
        
        <?php if (empty($readyItems)): ?>
            <div class="col-12 text-center text-muted mt-5">
                <h1 class="display-1"><i class="bi bi-check-circle"></i></h1>
                <h2>No orders waiting.</h2>
            </div>
        <?php endif; ?>

        <?php foreach ($readyItems as $item): ?>
        <div class="col-md-4 col-sm-6">
            <div class="card shadow pickup-card h-100">
                <div class="card-body text-center d-flex flex-column justify-content-between">
                    <div>
                        <div class="order-id mb-2">#<?= $item['sale_id'] ?></div>
                        <div class="item-name mb-3"><?= htmlspecialchars($item['item_name']) ?></div>
                        
                        <?php $mins = round((time() - strtotime($item['created_at'])) / 60); ?>
                        <div class="mb-4 opacity-75">Ready <?= $mins ?> mins ago</div>
                    </div>

                    <form method="POST" action="index.php?page=pickup">
                        <input type="hidden" name="notif_id" value="<?= $item['id'] ?>">
                        
                        <div class="mb-2">
                            <label class="small mb-1">Who is collecting?</label>
                            <select name="collected_by" class="form-select text-dark fw-bold" required>
                                <?php 
                                    // Auto-select logged in user if possible
                                    $currentUserId = $_SESSION['user_id'];
                                ?>
                                <?php foreach ($staff as $s): ?>
                                    <option value="<?= $s['id'] ?>" <?= $s['id'] == $currentUserId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($s['username']) ?> (<?= ucfirst($s['role']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <button class="btn btn-light btn-lg w-100 fw-bold text-success">
                            <i class="bi bi-check-lg"></i> COLLECTED
                        </button>
                    </form>

                </div>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</body>
</html>