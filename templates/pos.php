<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - Point of Sale</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        body { background-color: #f0f2f5; height: 100vh; overflow: hidden; }
        .product-card { cursor: pointer; transition: transform 0.1s; }
        .product-card:active { transform: scale(0.98); }
        .hover-shadow:hover { box-shadow: 0 4px 8px rgba(0,0,0,0.1) !important; border-color: #0d6efd !important; }
        ::-webkit-scrollbar { width: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; }
        ::-webkit-scrollbar-thumb { background: #ccc; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #aaa; }
    </style>
</head>
<body class="d-flex flex-column">

    <div class="bg-dark text-white p-2 d-flex justify-content-between align-items-center shadow-sm" style="height: 50px;">
        <div class="fw-bold ms-3">
            <i class="bi bi-cart4 text-primary"></i> POS Terminal
        </div>
        <div>
            <a href="index.php?page=pickup" target="_blank" class="btn btn-outline-warning btn-sm me-3 position-relative">
                <i class="bi bi-bell-fill"></i> Pickup Screen
                <span id="pickupBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="display:none;">
                    0
                </span>
            </a>

            <a href="index.php?page=dashboard" class="btn btn-outline-light btn-sm me-2">Exit to Dashboard</a>
        </div>
    </div>

    <div class="container-fluid flex-grow-1 p-3" style="overflow: hidden;">
        <div class="row h-100">
            <div class="col-md-8 h-100 d-flex flex-column">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white p-3 border-bottom">
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchBox" class="form-control border-start-0" placeholder="Scan barcode or type product name..." onkeyup="filterProducts()" autofocus>
                        </div>
                    </div>
                    <div class="card-body bg-light overflow-auto p-3">
                        <div class="row g-3" id="productGrid">
                            <?php foreach ($products as $p): ?>
                            <div class="col-md-3 col-6 product-card" data-name="<?= strtolower($p['name'] ?? '') ?>">
                                <form method="POST" action="index.php?page=pos" class="h-100">
                                    <input type="hidden" name="add_item" value="1">
                                    <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                    <input type="hidden" name="name" value="<?= htmlspecialchars($p['name']) ?>">
                                    <input type="hidden" name="price" value="<?= $p['price'] ?>">
                                    <input type="hidden" name="category_id" value="<?= $p['category_id'] ?>">
                                    <button type="submit" class="btn btn-white border w-100 h-100 p-3 text-start shadow-sm hover-shadow d-flex flex-column justify-content-between">
                                        <div class="fw-bold text-dark mb-1" style="line-height: 1.2;"><?= htmlspecialchars($p['name']) ?></div>
                                        <div>
                                            <div class="text-primary fw-bold fs-5">ZMW <?= number_format((float)$p['price'], 2) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($p['category'] ?? '-') ?></small>
                                        </div>
                                    </button>
                                </form>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4 h-100">
                <div class="card h-100 shadow border-0 d-flex flex-column">
                    <div class="card-header bg-primary text-white p-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="bi bi-basket3"></i> Current Order</h5>
                        <form method="POST" action="index.php?page=pos" class="d-inline">
                            <button type="submit" name="clear_cart" class="btn btn-sm btn-light text-primary fw-bold">
                                <i class="bi bi-trash"></i> Clear
                            </button>
                        </form>
                    </div>
                    <div class="card-body p-0 overflow-auto flex-grow-1 bg-white">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="bg-light sticky-top" style="z-index: 10;">
                                <tr>
                                    <th class="ps-3">Item</th>
                                    <th class="text-center" width="60">Qty</th>
                                    <th class="text-end pe-3">Total</th>
                                    <th width="40"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $grandTotal = 0;
                                if (!empty($_SESSION['cart'])): 
                                    foreach ($_SESSION['cart'] as $pid => $item): 
                                        $lineTotal = $item['price'] * $item['qty'];
                                        $grandTotal += $lineTotal;
                                ?>
                                <tr>
                                    <td class="ps-3 align-middle">
                                        <div class="fw-bold"><?= htmlspecialchars($item['name']) ?></div>
                                        <small class="text-muted">@ <?= number_format((float)$item['price'], 2) ?></small>
                                    </td>
                                    <td class="align-middle text-center">
                                        <span class="badge bg-secondary fs-6"><?= $item['qty'] ?></span>
                                    </td>
                                    <td class="text-end align-middle pe-3 fw-bold"><?= number_format($lineTotal, 2) ?></td>
                                    <td class="align-middle text-end">
                                        <form method="POST" action="index.php?page=pos">
                                            <input type="hidden" name="remove_item" value="1">
                                            <input type="hidden" name="product_id" value="<?= $pid ?>">
                                            <button class="btn btn-link text-danger p-0 btn-lg"><i class="bi bi-x"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="4" class="text-center text-muted py-5 mt-5">Empty Cart</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer bg-light p-3 border-top">
                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted fw-bold text-uppercase">Total Amount</span>
                            <span class="display-6 fw-bold text-success">
                                <span class="fs-4 text-muted align-top">ZMW</span> <?= number_format($grandTotal, 2) ?>
                            </span>
                        </div>
                        <form method="POST" action="index.php?page=pos">
                            <input type="hidden" name="checkout" value="1">
                            <div class="mb-3">
                                <div class="btn-group w-100" role="group">
                                    <input type="radio" class="btn-check" name="payment_method" id="payCash" value="cash" checked>
                                    <label class="btn btn-outline-success py-2 fw-bold" for="payCash"><i class="bi bi-cash"></i> Cash</label>
                                    <input type="radio" class="btn-check" name="payment_method" id="payCard" value="card">
                                    <label class="btn btn-outline-primary py-2 fw-bold" for="payCard"><i class="bi bi-credit-card"></i> Card</label>
                                    <input type="radio" class="btn-check" name="payment_method" id="payMobile" value="mobile_money">
                                    <label class="btn btn-outline-warning py-2 fw-bold" for="payMobile"><i class="bi bi-phone"></i> Mobile</label>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 py-3 fw-bold fs-5 shadow" <?= $grandTotal == 0 ? 'disabled' : '' ?>>
                                <i class="bi bi-check-circle-fill me-2"></i> COMPLETE SALE
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-printer"></i> Printing Receipt...</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="height: 500px;">
                    <iframe id="receiptFrame" src="" style="width:100%; height:100%; border:none;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close & New Sale</button>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('receiptFrame').contentWindow.print()">
                        <i class="bi bi-printer"></i> Print Again
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function filterProducts() {
        let input = document.getElementById("searchBox").value.toLowerCase();
        let cards = document.getElementsByClassName("product-card");
        for (let i = 0; i < cards.length; i++) {
            let name = cards[i].getAttribute("data-name");
            cards[i].style.display = name.includes(input) ? "block" : "none";
        }
    }
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById("searchBox").focus();
    });

    // --- LIVE PICKUP BADGE UPDATE ---
    function checkPickupCount() {
        fetch('index.php?page=pos&ajax_ready_count=1')
            .then(response => response.text())
            .then(count => {
                let badge = document.getElementById('pickupBadge');
                if (parseInt(count) > 0) {
                    badge.innerText = count;
                    badge.style.display = 'inline-block';
                } else {
                    badge.style.display = 'none';
                }
            })
            .catch(err => console.error('Badge Error:', err));
    }
    // Poll every 5 seconds
    setInterval(checkPickupCount, 5000);
    // Check immediately on load
    checkPickupCount();


    // CHECK FOR RECEIPT TRIGGER
    <?php if (isset($_SESSION['last_sale_id'])): ?>
        var receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
        document.getElementById('receiptFrame').src = 'index.php?page=receipt&sale_id=<?= $_SESSION['last_sale_id'] ?>';
        receiptModal.show();
        <?php unset($_SESSION['last_sale_id']); ?>
    <?php endif; ?>

    // SUCCESS ALERTS
    <?php if (isset($_SESSION['swal_type'])): ?>
    document.addEventListener("DOMContentLoaded", function() {
        Swal.fire({
            icon: '<?= $_SESSION['swal_type'] ?>',
            title: '<?= $_SESSION['swal_type'] === "success" ? "Success" : "Notice" ?>',
            text: '<?= addslashes($_SESSION['swal_msg']) ?>',
            timer: 2000,
            showConfirmButton: false
        });
    });
    <?php unset($_SESSION['swal_type']); unset($_SESSION['swal_msg']); ?>
    <?php endif; ?>
    </script>
</body>
</html>
