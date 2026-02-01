<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>POS - Terminal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        body { background: #f8f9fa; height: 100vh; overflow: hidden; }
        .product-grid { height: calc(100vh - 150px); overflow-y: auto; }
        .cart-panel { height: calc(100vh - 60px); background: white; border-left: 1px solid #dee2e6; }
        .low-stock { border: 2px solid #ffc107 !important; background: #fff9e6; }
        .out-of-stock { opacity: 0.5; pointer-events: none; }
    </style>
</head>
<body class="d-flex flex-column">

    <div class="bg-dark text-white p-2 d-flex justify-content-between align-items-center shadow">
        <div class="fw-bold ms-2"><i class="bi bi-shop text-warning"></i> Odelia POS</div>
        <div>
            <button class="btn btn-outline-info btn-sm me-2" data-bs-toggle="modal" data-bs-target="#tabsModal">
                <i class="bi bi-clipboard-data"></i> Open Tabs
            </button>
            <a href="index.php?page=pickup" class="btn btn-outline-warning btn-sm me-2">
                <i class="bi bi-bell"></i> Pickup Screen
            </a>
            <button onclick="openShiftReport()" class="btn btn-danger btn-sm me-2">
                <i class="bi bi-file-earmark-text"></i> End Shift Report
            </button>
            <a href="index.php?page=dashboard" class="btn btn-outline-light btn-sm">Exit</a>
        </div>
    </div>

    <div class="container-fluid flex-grow-1">
        <div class="row h-100">
            <div class="col-md-8 p-3">
                <input type="text" id="search" class="form-control mb-3" placeholder="Search products..." onkeyup="filter()">
                <div class="row g-2 product-grid" id="grid">
                    <?php foreach($products as $p): 
                        $isLow = $p['stock_qty'] > 0 && $p['stock_qty'] <= 5;
                        $isOut = $p['stock_qty'] <= 0;
                    ?>
                    <div class="col-md-3 item" data-name="<?= strtolower($p['name']) ?>">
                        <form method="POST" class="card h-100 <?= $isLow ? 'low-stock' : '' ?> <?= $isOut ? 'out-of-stock' : '' ?>">
                            <button name="add_item" class="btn p-3 text-start h-100">
                                <div class="fw-bold"><?= htmlspecialchars($p['name']) ?></div>
                                <div class="text-primary fw-bold">ZMW <?= number_format($p['price'], 2) ?></div>
                                <span class="badge bg-<?= $isOut ? 'danger' : ($isLow ? 'warning text-dark' : 'success') ?>">
                                    <?= $p['stock_qty'] ?> in stock
                                </span>
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-md-4 cart-panel d-flex flex-column p-0">
                <div class="p-3 bg-primary text-white">
                    <h5 class="mb-0"><?= $_SESSION['current_customer'] ?? 'Current Order' ?></h5>
                    <?php if(isset($_SESSION['current_tab_id'])): ?>
                        <span class="badge bg-warning text-dark">Recalled Tab</span>
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1 overflow-auto p-3">
                    <?php $total = 0; if(!empty($_SESSION['cart'])): foreach($_SESSION['cart'] as $id => $item): $total += ($item['price'] * $item['qty']); ?>
                    <div class="d-flex justify-content-between mb-2 border-bottom pb-1">
                        <div>
                            <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                            <small class="text-muted"><?= $item['qty'] ?> x <?= number_format($item['price'], 2) ?></small>
                        </div>
                        <div class="fw-bold">ZMW <?= number_format($item['price'] * $item['qty'], 2) ?></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
                <div class="p-3 bg-light border-top">
                    <h2 class="text-center text-success fw-bold">ZMW <?= number_format($total, 2) ?></h2>
                    <button class="btn btn-success btn-lg w-100 py-3 mt-2" data-bs-toggle="modal" data-bs-target="#checkoutModal" <?= $total <= 0 ? 'disabled' : '' ?>>
                        PROCESS PAYMENT
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <form method="POST" class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white"><h5>Finalize Sale</h5></div>
                <div class="modal-body">
                    <input type="hidden" name="checkout" value="1">
                    <label class="small fw-bold">Customer Name / Table</label>
                    <input type="text" name="customer_name" class="form-control mb-3" required value="<?= $_SESSION['current_customer'] ?? 'Walk-in' ?>">
                    
                    <label class="small fw-bold">Payment Method</label>
                    <select name="payment_method" id="payMethod" class="form-select mb-3" onchange="toggleMomo(); toggleAmountRequired();">
                        <option value="cash">Cash</option>
                        <option value="card">Bank Card</option>
                        <option value="mobile_money">Mobile Money</option>
                        
                        <?php if (!isset($_SESSION['current_tab_id'])): ?>
                            <option value="pending">Add to Tab (Pay Later)</option>
                        <?php endif; ?>
                    </select>

                    <div id="momoOptions" style="display:none;" class="mb-3 p-3 border rounded bg-light text-center">
                        <label class="d-block mb-2 small fw-bold">Select Provider</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="momo_provider" id="mtn" value="MTN" checked>
                            <label class="btn btn-outline-warning" for="mtn">MTN</label>
                            <input type="radio" class="btn-check" name="momo_provider" id="airtel" value="Airtel">
                            <label class="btn btn-outline-danger" for="airtel">Airtel</label>
                            <input type="radio" class="btn-check" name="momo_provider" id="zamtel" value="Zamtel">
                            <label class="btn btn-outline-success" for="zamtel">Zamtel</label>
                        </div>
                    </div>

                    <div class="bg-light p-3 rounded border">
                        <label class="small fw-bold">Amount Tendered</label>
                        <input type="number" step="0.01" name="amount_tendered" id="tendered" class="form-control form-control-lg fw-bold" required oninput="calcChange()">
                        <div class="mt-3 h4 text-center">Change: <span id="changeDue" class="text-danger">0.00</span></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-success btn-lg w-100">CONFIRM TRANSACTION</button></div>
            </div>
        </form>
    </div>

    <div class="modal fade" id="tabsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header"><h5>Open Tabs</h5></div>
                <div class="modal-body">
                    <table class="table table-hover">
                        <thead><tr><th>Customer</th><th>Total</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach($openTabs as $t): ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($t['customer_name']) ?></td>
                                <td>ZMW <?= number_format($t['final_total'], 2) ?></td>
                                <td><form method="POST"><input type="hidden" name="sale_id" value="<?= $t['id'] ?>"><button name="recall_tab" class="btn btn-primary btn-sm">Recall</button></form></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="reportModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <iframe id="reportFrame" src="" style="width:100%; height:85vh; border:none;"></iframe>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function filter() {
            let val = document.getElementById('search').value.toLowerCase();
            document.querySelectorAll('.item').forEach(el => { el.style.display = el.getAttribute('data-name').includes(val) ? 'block' : 'none'; });
        }
        function toggleMomo() {
            document.getElementById('momoOptions').style.display = (document.getElementById('payMethod').value === 'mobile_money') ? 'block' : 'none';
        }
        
        // NEW: Toggle 'required' on amount field
        function toggleAmountRequired() {
            const method = document.getElementById('payMethod').value;
            const input = document.getElementById('tendered');
            if (method === 'pending') {
                input.removeAttribute('required');
            } else {
                input.setAttribute('required', 'required');
            }
        }

        function calcChange() {
            let total = parseFloat(<?= $total ?>);
            let tendered = parseFloat(document.getElementById('tendered').value) || 0;
            document.getElementById('changeDue').innerText = Math.max(0, tendered - total).toFixed(2);
        }
        function openShiftReport() {
            document.getElementById('reportFrame').src = 'index.php?page=pos&action=close_shift_report';
            new bootstrap.Modal(document.getElementById('reportModal')).show();
        }

        <?php if (isset($_SESSION['last_sale_id'])): ?>
            document.addEventListener("DOMContentLoaded", function() {
                document.getElementById('reportFrame').src = 'index.php?page=receipt&sale_id=<?= $_SESSION['last_sale_id'] ?>&mode=double';
                new bootstrap.Modal(document.getElementById('reportModal')).show();
            });
            <?php unset($_SESSION['last_sale_id']); ?>
        <?php endif; ?>
        
        // Initialize required check on load
        toggleAmountRequired();
    </script>
</body>
</html>
