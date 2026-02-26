<div class="d-print-none d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="fw-bold mb-0"><i class="bi bi-journal-check text-success"></i> Master Z-Read (Daily Closure)</h3>
        <span class="text-muted small">Aggregate all shifts and lock the day's financials.</span>
    </div>
    <button onclick="window.print()" class="btn btn-outline-dark fw-bold shadow-sm"><i class="bi bi-printer"></i> Print Z-Read</button>
</div>

<div class="card shadow-sm border-0 mb-4 d-print-none">
    <div class="card-body bg-light">
        <form method="GET" action="index.php" class="row g-3 align-items-end">
            <input type="hidden" name="page" value="z_read">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted">Target Date</label>
                <input type="date" name="target_date" class="form-control fw-bold" value="<?= htmlspecialchars($selectedDate) ?>" required>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-bold text-muted">Filter Workstation</label>
                <select name="location_id" class="form-select fw-bold">
                    <?php foreach($locations as $l): ?>
                        <option value="<?= $l['id'] ?>" <?= ($l['id'] == $selectedLoc) ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-dark w-100 fw-bold"><i class="bi bi-search"></i> Load Day</button>
            </div>
        </form>
    </div>
</div>

<div id="printArea" class="mx-auto" style="max-width: 600px;">
    
    <?php if ($isClosed): ?>
        <div class="alert alert-success border-success text-center fw-bold shadow-sm d-print-none">
            <i class="bi bi-lock-fill"></i> This day was officially CLOSED and locked by a Manager.
        </div>
    <?php elseif ($openShiftsCount > 0): ?>
        <div class="alert alert-danger border-danger text-center fw-bold shadow-sm d-print-none">
            <i class="bi bi-exclamation-triangle-fill"></i> Warning: There are <?= $openShiftsCount ?> open shifts! You cannot run a Z-Read until all cashiers end their shifts.
        </div>
    <?php endif; ?>

    <div class="card border-dark shadow-sm">
        <div class="card-header bg-dark text-white text-center py-3">
            <h4 class="mb-0 fw-bold">END OF DAY REPORT (Z-READ)</h4>
            <div class="small text-warning"><?= date('l, F j, Y', strtotime($selectedDate)) ?></div>
        </div>
        <div class="card-body p-4">
            
            <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">DIGITAL TRANSACTIONS</h6>
            <div class="d-flex justify-content-between mb-2">
                <span class="fw-bold">Card Sales:</span>
                <span class="text-dark">ZMW <?= number_format($totals['Card'], 2) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-4">
                <span class="fw-bold">Mobile Money (MTN/Airtel/Zamtel):</span>
                <span class="text-dark">ZMW <?= number_format($totals['Mobile'], 2) ?></span>
            </div>

            <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">CASH RECONCILIATION</h6>
            <div class="d-flex justify-content-between mb-2">
                <span>Total Opening Floats:</span>
                <span>ZMW <?= number_format($totalStartingCash, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>Total Cash Sales:</span>
                <span>+ ZMW <?= number_format($totals['Cash'], 2) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span>Total Payouts / Petty Cash:</span>
                <span class="text-danger">- ZMW <?= number_format($totalExpenses, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-4 bg-light p-2 rounded border">
                <span class="fw-bold">EXPECTED CASH IN SAFE:</span>
                <span class="fw-bold fs-5">ZMW <?= number_format($expectedCash, 2) ?></span>
            </div>

            <h6 class="fw-bold text-muted border-bottom pb-2 mb-3">MANAGER AUDIT</h6>
            <div class="d-flex justify-content-between mb-2">
                <span>Actual Cash Declared by Cashiers:</span>
                <span class="fw-bold <?= ($totalActualCash < $expectedCash) ? 'text-danger' : 'text-success' ?>">ZMW <?= number_format($totalActualCash, 2) ?></span>
            </div>
            <div class="d-flex justify-content-between mb-4">
                <span class="fw-bold">DAILY VARIANCE:</span>
                <span class="fw-bold <?= ($dailyVariance < 0) ? 'text-danger' : 'text-success' ?>">ZMW <?= number_format($dailyVariance, 2) ?></span>
            </div>

            <div class="text-center mt-5 border-top pt-3 small text-muted">
                <div>Printed on: <?= date('Y-m-d H:i:s') ?></div>
                <div class="mt-4 border-bottom w-50 mx-auto pb-4">Manager Signature</div>
            </div>
        </div>
        
        <?php if (!$isClosed && $openShiftsCount == 0 && $expectedCash > 0): ?>
            <div class="card-footer bg-white p-3 d-print-none">
                <form method="POST">
                    <input type="hidden" name="execute_z_read" value="1">
                    <input type="hidden" name="location_id" value="<?= $selectedLoc ?>">
                    <input type="hidden" name="target_date" value="<?= $selectedDate ?>">
                    <button type="submit" class="btn btn-danger w-100 fw-bold py-3 shadow" onclick="return confirm('Are you sure you want to lock today\'s financials? This cannot be undone.')">
                        <i class="bi bi-lock-fill"></i> LOCK DAY & EXECUTE Z-READ
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    @media print {
        body { background-color: white !important; }
        .d-print-none { display: none !important; }
        .card { border: none !important; box-shadow: none !important; }
        .card-header { background-color: white !important; color: black !important; border-bottom: 2px solid black !important; }
        .bg-light { background-color: white !important; border: 1px dashed #ccc !important; }
    }
</style>

<script>
    <?php if(isset($_SESSION['swal_msg'])): ?>
        Swal.fire({ 
            icon: '<?= addslashes($_SESSION['swal_type']) ?>', 
            title: '<?= addslashes($_SESSION['swal_msg']) ?>', 
            timer: 3000, 
            showConfirmButton: <?= $_SESSION['swal_type'] === 'error' ? 'true' : 'false' ?> 
        });
        <?php unset($_SESSION['swal_type'], $_SESSION['swal_msg']); ?>
    <?php endif; ?>
</script>
