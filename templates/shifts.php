<div class="row">
    <div class="col-md-6 mx-auto">
        
        <h3 class="mb-4 text-center">
            <?= $isMoneyRole ? '💰 Cashier Shift Management' : '👨‍🍳 Staff Clock-In' ?>
        </h3>

        <?php if (!$currentShift): ?>
            <div class="card shadow border-success mb-4">
                <div class="card-header bg-success text-white fw-bold">
                    <i class="bi bi-play-circle-fill"></i> Start New Shift
                </div>
                <div class="card-body">
                    <form method="POST" action="index.php?page=shifts">
                        <input type="hidden" name="start_shift" value="1">

                        <?php if ($isMoneyRole): ?>
                            <div class="alert alert-light border">
                                <label class="form-label fw-bold">Opening Float Amount (ZMW)</label>
                                <input type="number" step="0.01" name="start_amount" class="form-control form-control-lg fw-bold" placeholder="0.00" required>
                                <div class="form-text text-muted">Count the cash in the drawer before starting.</div>
                            </div>
                        <?php else: ?>
                             <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> Please ensure your station is clean before clocking in.
                             </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label text-danger fw-bold">Manager Verification</label>
                            <input type="password" name="manager_password" class="form-control" placeholder="Enter Manager Password" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-success w-100 btn-lg fw-bold">Clock In</button>
                    </form>
                </div>
            </div>

        <?php else: ?>
            <div class="card shadow border-danger mb-4">
                <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-stop-circle-fill"></i> Active Shift #<?= $currentShift['id'] ?></span>
                    <span class="badge bg-white text-danger"><?= date('H:i', strtotime($currentShift['start_time'])) ?></span>
                </div>
                <div class="card-body">
                    <form method="POST" action="index.php?page=shifts">
                        <input type="hidden" name="end_shift" value="1">
                        <input type="hidden" name="shift_id" value="<?= $currentShift['id'] ?>">

                        <?php if ($isMoneyRole): ?>
                            <div class="bg-light p-3 rounded border mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <span>Opening Float:</span>
                                    <strong><?= number_format($calculatedSummary['float'], 2) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-1 text-success">
                                    <span>+ Cash Sales:</span>
                                    <strong><?= number_format($calculatedSummary['cash_sales'], 2) ?></strong>
                                </div>
                                <div class="d-flex justify-content-between mb-2 text-danger">
                                    <span>- Expenses:</span>
                                    <strong><?= number_format($calculatedSummary['expenses'], 2) ?></strong>
                                </div>
                                <hr class="my-1">
                                <div class="d-flex justify-content-between fs-5 text-primary">
                                    <span>Expected Cash:</span>
                                    <strong>ZMW <?= number_format($calculatedSummary['expected'], 2) ?></strong>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label fw-bold">Closing Cash Count (ZMW)</label>
                                <input type="number" step="0.01" name="end_amount" class="form-control form-control-lg fw-bold text-primary" 
                                       value="<?= $calculatedSummary['expected'] ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Variance Reason (if any)</label>
                                <textarea name="variance_reason" class="form-control" rows="1" placeholder="Optional notes..."></textarea>
                            </div>

                        <?php else: ?>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Handover Notes</label>
                                <textarea name="handover_notes" class="form-control" rows="3" required placeholder="Issues, low stock, repairs..."></textarea>
                            </div>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label text-danger fw-bold">Manager Verification</label>
                            <input type="password" name="manager_password" class="form-control" placeholder="Enter Manager Password" required autocomplete="new-password">
                        </div>

                        <button type="submit" class="btn btn-danger w-100 btn-lg fw-bold">Clock Out & Close</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm">
            <div class="card-header bg-light fw-bold">Recent History</div>
            <div class="card-body p-0">
                <table class="table table-striped mb-0 text-center">
                    <thead class="small text-muted text-uppercase">
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Info</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $h): ?>
                        <tr>
                            <td><?= date('d M', strtotime($h['start_time'])) ?></td>
                            <td><?= date('H:i', strtotime($h['start_time'])) ?></td>
                            <td>
                                <?php if ($isMoneyRole): ?>
                                    <span class="badge bg-secondary"><?= number_format($h['starting_cash'], 2) ?></span>
                                <?php else: ?>
                                    <small class="text-muted">General</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($h['status'] === 'open'): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Closed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($history)): ?>
                            <tr><td colspan="4" class="text-muted py-3">No shifts recorded yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>