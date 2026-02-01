<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>POS - <?= htmlspecialchars($locationName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background-color: #f8f9fa; height: 100vh; overflow: hidden; }
        .product-card { cursor: pointer; transition: 0.1s; }
        .product-card:active { transform: scale(0.98); }
        .stock-badge { position: absolute; top: 10px; right: 10px; font-size: 0.8em; }
        .out-of-stock {
            opacity: 0.6;
            pointer-events: none;
            background-color: #e9ecef !important;
            border-color: #dee2e6 !important;
        }
    </style>
</head>
<body class="d-flex flex-column">

    <div class="bg-dark text-white p-2 d-flex justify-content-between align-items-center shadow-sm">
        <div class="d-flex align-items-center">
            <div class="fw-bold ms-3 fs-5">
                <i class="bi bi-geo-alt-fill text-warning"></i> 
                <?= htmlspecialchars($locationName) ?>
            </div>
            <span class="text-white-50 ms-3 border-start ps-3 small">POS Terminal</span>
        </div>
        
        <div>
            <button class="btn btn-outline-info btn-sm me-2" data-bs-toggle="modal" data-bs-target="#tabsModal">
                <i class="bi bi-clipboard-data"></i> Open Tabs (<?= count($openTabs) ?>)
            </button>
            <button onclick="openShiftReport()" class="btn btn-danger btn-sm me-2">
                <i class="bi bi-door-closed"></i> End Shift
            </button>
            <a href="index.php?page=dashboard" class="btn btn-outline-light btn-sm">Exit</a>
        </div>
    </div>

    <div class="container-fluid flex-grow-1 p-3 overflow-hidden">
        <div class="row h-100">
            <div class="col-md-8 h-100 overflow-auto">
                <input type="text" id="searchBox" class="form-control mb-3" placeholder="Search products..." onkeyup="filterProducts()">
                <div class="row g-3" id="productGrid">
                    <?php foreach ($products as $p): 
                        $isOutOfStock = $p['stock_qty'] <= 0;
                        $btnClass = $isOutOfStock ? "out-of-stock" : "";
                    ?>
                    <div class="col-md-3 col-6 product-card" data-name="<?= strtolower($p['name']) ?>">
                        <form method="POST" action="index.php?page=pos" class="h-100">
                            <input type="hidden" name="add_item" value="1">
                            <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            <button type="submit" class="btn btn-white border w-100 h-100 p-3 text-start shadow-sm position-relative <?= $btnClass ?>" <?= $isOutOfStock ? 'disabled' : '' ?>>
                                
                                <span class="badge bg-<?= $p['stock_qty'] > 10 ? 'success' : ($p['stock_qty'] > 0 ? 'warning' : 'danger') ?> stock-badge">
                                    <?= $p['stock_qty'] ?> left
                                </span>
                                
                                <div class="fw-bold mt-2"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="text-primary fw-bold">ZMW <?= number_format($p['price'], 2) ?></div>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-md-4 h-100 d-flex flex-column">
                <div class="card h-100 shadow border-0">
                    <div class="card-header bg-primary text-white d-flex justify-content-between">
                        <span class="fw-bold">
                            <?= isset($_SESSION['current_customer']) ? "Tab: " . htmlspecialchars($_SESSION['current_customer']) : "New Order" ?>
                        </span>
                        <form method="POST"><button name="clear_cart" class="btn btn-sm btn-light text-primary"><i class="bi bi-trash"></i></button></form>
                    </div>
                    <div class="card-body overflow-auto bg-white p-0">
                        <table class="table table-striped mb-0">
                            <?php 
                            $total = 0;
                            if (!empty($_SESSION['cart'])): 
                                foreach ($_SESSION['cart'] as $pid => $item): 
                                    $total += $item['price'] * $item['qty'];
                            ?>
                            <tr>
                                <td class="ps-3">
                                    <div><?= htmlspecialchars($item['name']) ?></div>
                                    <small class="text-muted">x<?= $item['qty'] ?></small>
                                </td>
                                <td class="text-end pe-3">
                                    <?= number_format($item['price'] * $item['qty'], 2) ?>
                                    <form method="POST" class="d-inline ms-2">
                                        <input type="hidden" name="remove_item" value="1">
                                        <input type="hidden" name="product_id" value="<?= $pid ?>">
                                        <button class="btn btn-sm text-danger p-0"><i class="bi bi-x-circle"></i></button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; endif; ?>
                        </table>
                    </div>
                    <div class="card-footer bg-light p-3">
                        <h2 class="text-center text-success fw-bold">ZMW <?= number_format($total, 2) ?></h2>
                        <button class="btn btn-success w-100 py-3 fw-bold fs-5" data-bs-toggle="modal" data-bs-target="#checkoutModal" <?= $total == 0 ? 'disabled' : '' ?>>
                            PAY / TAB
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">Complete Payment</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="index.php?page=pos">
                    <div class="modal-body">
                        <input type="hidden" name="checkout" value="1">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Customer Name / Table #</label>
                            <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($_SESSION['current_customer'] ?? '') ?>" placeholder="e.g. Table 5 or John">
                        </div>
                        <div class="row mb-3 bg-light p-2 rounded mx-0">
                            <div class="col-6">
                                <label class="form-label small text-muted">Total Due</label>
                                <div class="fs-4 fw-bold" id="totalDueVal"><?= $total ?></div>
                            </div>
                            <div class="col-6">
                                <label class="form-label small text-muted">Amount Tendered</label>
                                <input type="number" step="0.01" name="amount_tendered" id="amountTendered" class="form-control form-control-lg" placeholder="0.00" oninput="calcChange()">
                            </div>
                            <div class="col-12 mt-2 text-end">
                                <span class="text-muted">Change: </span>
                                <span class="fs-4 fw-bold text-danger" id="changeDisplay">0.00</span>
                            </div>
                        </div>
                        <div class="d-grid gap-2">
                            <div class="btn-group" role="group">
                                <input type="radio" class="btn-check" name="payment_method" id="payCash" value="cash" checked>
                                <label class="btn btn-outline-success" for="payCash"><i class="bi bi-cash"></i> Cash</label>
                                <input type="radio" class="btn-check" name="payment_method" id="payCard" value="card">
                                <label class="btn btn-outline-primary" for="payCard"><i class="bi bi-credit-card"></i> Card</label>
                                <input type="radio" class="btn-check" name="payment_method" id="payLater" value="pending">
                                <label class="btn btn-outline-warning" for="payLater"><i class="bi bi-clock"></i> Pay Later</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="submit" class="btn btn-lg btn-success w-100">CONFIRM</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="tabsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Open Tabs at <?= htmlspecialchars($locationName) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <table class="table table-hover">
                        <thead><tr><th>Time</th><th>Customer</th><th>Total</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach ($openTabs as $tab): ?>
                            <tr>
                                <td><?= date('H:i', strtotime($tab['created_at'])) ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($tab['customer_name']) ?></td>
                                <td><?= number_format($tab['final_total'], 2) ?></td>
                                <td>
                                    <form method="POST">
                                        <input type="hidden" name="recall_tab" value="1">
                                        <input type="hidden" name="sale_id" value="<?= $tab['id'] ?>">
                                        <button class="btn btn-primary btn-sm">Pay / Edit</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title"><i class="bi bi-printer"></i> Preview</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0" style="height: 500px;">
                    <iframe id="receiptFrame" src="" style="width:100%; height:100%; border:none;"></iframe>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" onclick="document.getElementById('receiptFrame').contentWindow.print()">
                        <i class="bi bi-printer"></i> Print
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
            for (let card of cards) {
                card.style.display = card.getAttribute("data-name").includes(input) ? "block" : "none";
            }
        }
        function calcChange() {
            let total = parseFloat(document.getElementById('totalDueVal').innerText);
            let tendered = parseFloat(document.getElementById('amountTendered').value) || 0;
            let change = tendered - total;
            document.getElementById('changeDisplay').innerText = change >= 0 ? change.toFixed(2) : "0.00";
        }
        function openShiftReport() {
            var modal = new bootstrap.Modal(document.getElementById('receiptModal'));
            document.getElementById('receiptFrame').src = 'index.php?page=pos&action=close_shift_report';
            modal.show();
        }
        <?php if (isset($_SESSION['last_sale_id'])): ?>
            var receiptModal = new bootstrap.Modal(document.getElementById('receiptModal'));
            document.getElementById('receiptFrame').src = 'index.php?page=receipt&sale_id=<?= $_SESSION['last_sale_id'] ?>';
            receiptModal.show();
            <?php unset($_SESSION['last_sale_id']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['swal_type'])): ?>
        Swal.fire({
            icon: '<?= $_SESSION['swal_type'] ?>',
            title: '<?= ucfirst($_SESSION['swal_type']) ?>',
            text: '<?= $_SESSION['swal_msg'] ?>',
            timer: 2000
        });
        <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); endif; ?>
    </script>
</body>
</html>
