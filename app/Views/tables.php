<div class="card shadow-sm border-0 mb-4 mt-4">
    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
        <h5 class="m-0 fw-bold"><i class="bi bi-grid-3x3-gap text-warning me-2"></i> Table Settings</h5>
        <button class="btn btn-warning fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addTableModal"><i class="bi bi-plus-lg"></i> Add New Table</button>
    </div>
    <div class="card-body bg-light">
        <div class="table-responsive bg-white rounded border shadow-sm p-3">
            <?php
            $bulkFormId = 'bulkTables';
            $bulkAction = 'index.php?page=tables';
            $bulkButtons = [
                ['value' => 'delete', 'label' => 'Delete selected', 'class' => 'btn-outline-danger', 'title' => 'Delete the selected tables?', 'confirm' => 'Delete', 'color' => '#dc3545'],
            ];
            include __DIR__ . '/partials/bulk_bar.php';
            ?>
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark">
                    <tr>
                        <th class="py-3" style="width:36px"><input type="checkbox" class="form-check-input bulk-all" data-bulk-form="bulkTables" title="Select all"></th>
                        <th class="py-3">Location</th>
                        <th class="py-3">Zone (e.g. Patio)</th>
                        <th class="py-3">Table Name</th>
                        <th class="py-3 text-center">Capacity</th>
                        <th class="py-3 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($tables as $t): ?>
                    <tr>
                        <td class="py-3"><input type="checkbox" class="form-check-input bulk-one" name="ids[]" value="<?= (int)$t['id'] ?>" form="bulkTables"></td>
                        <td class="py-3"><span class="badge bg-secondary"><?= htmlspecialchars($t['location_name']) ?></span></td>
                        <td class="py-3 fw-bold text-muted text-uppercase"><?= htmlspecialchars($t['zone_name']) ?></td>
                        <td class="py-3 fw-bold fs-5 text-dark"><?= htmlspecialchars($t['table_name']) ?></td>
                        <td class="py-3 text-center"><i class="bi bi-people-fill text-info me-1"></i> <?= $t['capacity'] ?> Seats</td>
                        <td class="py-3 text-end">
                            <form method="POST" class="js-confirm-form"
                                data-swal-title="Delete this table?"
                                data-swal-text="This cannot be undone."
                                data-swal-confirm="Delete"
                                data-swal-cancel="Cancel"
                                data-swal-icon="warning">
                                <input type="hidden" name="delete_table" value="1">
                                <input type="hidden" name="table_id" value="<?= $t['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($tables)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-5">
                            <i class="bi bi-info-circle display-4 d-block mb-3"></i>
                            No tables configured. Add a table to build the floor plan.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="addTableModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg border-warning border-top border-4">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-plus-square text-warning me-2"></i> Add New Table</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="add_table" value="1">
                    <div class="mb-4">
                        <label class="form-label fw-bold small text-muted">SELECT LOCATION</label>
                        <select name="location_id" class="form-select form-select-lg" required>
                            <?php foreach($locations as $loc): ?>
                            <option value="<?= $loc['id'] ?>"><?= htmlspecialchars($loc['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">ZONE NAME</label>
                            <input type="text" name="zone_name" class="form-control form-control-lg" placeholder="e.g., Main Floor" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">TABLE NAME</label>
                            <input type="text" name="table_name" class="form-control form-control-lg" placeholder="e.g., Table 1" required>
                        </div>
                    </div>
                    <div>
                        <label class="form-label fw-bold small text-muted">SEATING CAPACITY</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text bg-white"><i class="bi bi-people-fill text-info"></i></span>
                            <input type="number" name="capacity" class="form-control" placeholder="4" min="1" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold shadow-sm px-4">Save Table</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4" id="menuQrCard">
    <div class="card-header bg-dark text-white py-3">
        <h5 class="m-0 fw-bold"><i class="bi bi-qr-code text-warning me-2"></i> Menu QR codes</h5>
    </div>
    <div class="card-body bg-light">
        <p class="text-muted small">This code opens the online menu. In the bag, a guest picks a store and then a free table. Reserving that table adds the surcharge saved here. A guest can also pay this fee on its own to hold a table, with no food on the order. The hold starts when Flutterwave confirms the payment, and the table is released when the time is up. The refundable share is what the till should return if that hold is cancelled. Use 0 when the fee is non-refundable. The payment itself is refunded in Flutterwave.</p>
        <form method="POST" class="row g-3 align-items-end mb-4">
            <input type="hidden" name="save_reserve" value="1">
            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted">RESERVATION NAME</label>
                <input type="text" name="shop_reserve_label" class="form-control" maxlength="80" value="<?= htmlspecialchars($reserveLabel ?? 'Table reservation') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">FEE (ZMW)</label>
                <input type="number" name="shop_reserve_amount" class="form-control" min="0" max="100000" step="0.01" value="<?= htmlspecialchars(number_format((float)($reserveAmount ?? 0), 2, '.', '')) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">HOLD (MINUTES)</label>
                <input type="number" name="shop_reserve_minutes" class="form-control" min="15" max="10080" step="1" value="<?= (int)($reserveMinutes ?? 120) ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold small text-muted">REFUNDABLE %</label>
                <input type="number" name="shop_reserve_refund_percent" class="form-control" min="0" max="100" step="1" value="<?= (int)($reserveRefundPercent ?? 100) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label fw-bold small text-muted">MENU ADDRESS</label>
                <input type="url" name="shop_menu_url" class="form-control font-monospace" value="<?= htmlspecialchars($menuUrl ?? '') ?>">
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-warning fw-bold">Save preset</button>
            </div>
        </form>
        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                <div class="small text-muted text-break" id="qrUrl"></div>
                <div class="d-flex gap-2 mt-3">
                    <button type="button" class="btn btn-dark fw-bold" id="qrPrint">Print</button>
                    <button type="button" class="btn btn-outline-dark fw-bold" id="qrCopy">Copy link</button>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="bg-white border rounded p-3 text-center" id="qrSheet">
                    <div class="small text-muted text-uppercase fw-bold">Scan to order</div>
                    <div class="fw-bold fs-5">Online menu</div>
                    <div id="qrBox" class="d-flex justify-content-center my-2"></div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="js/qrcode.min.js"></script>
<script>
(function () {
    var current = <?= json_encode($menuUrl ?? 'https://queenbeesrestaurants.com/index.php?page=shop&view=menu', JSON_UNESCAPED_SLASHES) ?>;
    var urlEl = document.getElementById('qrUrl');
    var box = document.getElementById('qrBox');
    if (urlEl) urlEl.textContent = current;
    if (box && typeof qrcode === 'function') {
        var qr = qrcode(0, 'M');
        qr.addData(current, 'Byte');
        qr.make();
        box.innerHTML = qr.createImgTag(6, 8);
    } else if (box) {
        box.textContent = 'QR library did not load.';
    }
    var copyBtn = document.getElementById('qrCopy');
    var printBtn = document.getElementById('qrPrint');
    if (copyBtn) copyBtn.addEventListener('click', function () {
        if (navigator.clipboard) navigator.clipboard.writeText(current);
    });
    if (printBtn) printBtn.addEventListener('click', function () {
        var img = box && box.querySelector('img');
        if (!img) return;
        var w = window.open('', 'menuqr', 'width=480,height=720');
        if (!w) return;
        var esc = function (s) { return String(s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); };
        w.document.write('<!DOCTYPE html><html><head><title>Menu QR</title><style>body{font-family:sans-serif;text-align:center;padding:24px}img{width:280px;height:280px}p{word-break:break-all;color:#555}</style></head><body><p>Scan to order</p><h1>Online menu</h1>' + img.outerHTML + '<p>' + esc(current) + '</p></body></html>');
        w.document.close();
        w.focus();
        w.print();
    });
})();
</script>
