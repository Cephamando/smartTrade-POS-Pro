<?php 
// FIXED: Explicitly redefine the lowercase roles for the frontend template
$tmplUserRole = strtolower($_SESSION['role'] ?? '');
$tmplIsManager = in_array($tmplUserRole, ['admin', 'manager', 'dev']);
$tmplIsMoneyRole = in_array($tmplUserRole, ['cashier', 'manager', 'admin', 'dev', 'bartender']);
?>
<div class="d-flex justify-content-between align-items-center mb-4">
    <h3><i class="bi bi-clock-history"></i> Shift Management</h3>
</div>

<?php if ($tmplIsManager): ?>
<ul class="nav nav-tabs mb-4 border-bottom border-warning border-2" id="shiftTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active fw-bold text-dark" data-bs-toggle="tab" data-bs-target="#adminView">
            <i class="bi bi-journal-text text-primary"></i> Master Shift Reports (X-Reads)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link fw-bold text-dark" data-bs-toggle="tab" data-bs-target="#myShift">
            <i class="bi bi-person-badge text-success"></i> My Personal Shift
        </button>
    </li>
</ul>
<?php endif; ?>

<div class="tab-content">
    
    <?php if ($tmplIsManager): ?>
    <div class="tab-pane fade show active" id="adminView">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body bg-light rounded">
                <form method="GET" action="index.php" class="row g-3 align-items-end">
                    <input type="hidden" name="page" value="shifts">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Filter Date</label>
                        <input type="date" name="filter_date" class="form-control fw-bold border-secondary shadow-sm" value="<?= htmlspecialchars($filterDate ?? date('Y-m-d')) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Staff Member</label>
                        <select name="filter_user" class="form-select fw-bold border-secondary shadow-sm">
                            <option value="">-- All Staff --</option>
                            <?php if(!empty($staffList)): foreach ($staffList as $staff): ?>
                                <option value="<?= $staff['id'] ?>" <?= (isset($filterUser) && $filterUser == $staff['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($staff['full_name']) ?> (<?= strtoupper($staff['role']) ?>)
                                </option>
                            <?php endforeach; endif; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100 fw-bold shadow-sm"><i class="bi bi-funnel-fill"></i> Apply Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow-sm border-top border-primary border-4">
            <div class="card-body p-0 table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light text-muted small text-uppercase">
                        <tr>
                            <th class="ps-4">ID</th>
                            <th>Staff</th>
                            <th>Workstation</th>
                            <th>Time Log</th>
                            <th class="text-end">Sales (ZMW)</th>
                            <th class="text-center">Status</th>
                            <th class="text-end pe-4">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allShifts)): ?>
                            <tr><td colspan="7" class="text-center py-5 text-muted fst-italic">No shifts found for this criteria.</td></tr>
                        <?php else: foreach ($allShifts as $s): 
                            $isOpen = ($s['status'] === 'open');
                        ?>
                            <tr>
                                <td class="ps-4 fw-bold">#<?= $s['id'] ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= htmlspecialchars($s['cashier_name']) ?></div>
                                    <span class="badge bg-secondary" style="font-size: 0.65rem;"><?= strtoupper($s['role']) ?></span>
                                </td>
                                <td><?= htmlspecialchars($s['location_name']) ?></td>
                                <td>
                                    <div class="small fw-bold text-success"><i class="bi bi-box-arrow-in-right"></i> <?= date('H:i', strtotime($s['start_time'])) ?></div>
                                    <?php if (!$isOpen): ?>
                                    <div class="small fw-bold text-danger"><i class="bi bi-box-arrow-left"></i> <?= date('H:i', strtotime($s['end_time'])) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-primary fs-5"><?= number_format($s['total_sales'], 2) ?></td>
                                <td class="text-center">
                                    <?php if ($isOpen): ?>
                                        <span class="badge bg-success rounded-pill px-3 shadow-sm">ACTIVE</span>
                                    <?php else: ?>
                                        <span class="badge bg-dark rounded-pill px-3 shadow-sm">CLOSED</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <button class="btn btn-sm btn-outline-dark fw-bold px-3 shadow-sm" onclick="showShiftReport(<?= $s['id'] ?>)">
                                        <i class="bi bi-printer"></i> Print X-Read
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>


    <div class="tab-pane fade <?= !$tmplIsManager ? 'show active' : '' ?>" id="myShift">
        <div class="row mt-4">
            <div class="col-md-6 mx-auto">
                <h4 class="mb-4 text-center fw-bold text-muted">
                    <?= $tmplIsMoneyRole ? '💰 My Cashier Shift' : '⏱️ My Staff Clock-In' ?>
                </h4>

                <?php if (!$currentShift): ?>
                    <div class="card shadow border-success mb-4">
                        <div class="card-header bg-success text-white fw-bold">
                            <i class="bi bi-play-circle-fill"></i> Start New Shift
                        </div>
                        <div class="card-body">
                            <form method="POST" action="index.php?page=shifts">
                                <input type="hidden" name="start_shift" value="1">

                                <?php if ($tmplIsMoneyRole): ?>
                                    <div class="alert alert-light border">
                                        <label class="form-label fw-bold">Opening Float Amount (ZMW)</label>
                                        <input type="number" step="0.01" name="start_amount" class="form-control form-control-lg fw-bold text-success" placeholder="0.00" required>
                                        <div class="form-text text-muted">Count the cash in the drawer before starting.</div>
                                    </div>
                                <?php else: ?>
                                     <div class="alert alert-info border-info">
                                        <i class="bi bi-info-circle"></i> Please ensure your station is clean before clocking in.
                                     </div>
                                <?php endif; ?>

                                <div class="mb-4 mt-3 bg-dark p-3 rounded border border-secondary shadow-sm">
                                    <label class="form-label text-warning fw-bold small"><i class="bi bi-shield-lock-fill"></i> Manager Verification Required</label>
                                    <input type="password" name="manager_password" class="form-control border-warning" placeholder="Enter Manager Password" required autocomplete="new-password">
                                </div>

                                <button type="submit" class="btn btn-success w-100 btn-lg fw-bold shadow">Clock In</button>
                            </form>
                        </div>
                    </div>

                <?php else: ?>
                    <div class="card shadow border-danger mb-4">
                        <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                            <span class="fw-bold"><i class="bi bi-stop-circle-fill"></i> Active Shift #<?= $currentShift['id'] ?></span>
                            <span class="badge bg-white text-danger shadow-sm fs-6"><?= date('H:i', strtotime($currentShift['start_time'])) ?></span>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="index.php?page=shifts">
                                <input type="hidden" name="end_shift" value="1">
                                <input type="hidden" name="shift_id" value="<?= $currentShift['id'] ?>">

                                <?php if ($tmplIsMoneyRole): ?>
                                    <div class="bg-light p-3 rounded border border-danger mb-3 shadow-sm">
                                        <div class="d-flex justify-content-between mb-1 small text-muted fw-bold">
                                            <span>Opening Float:</span>
                                            <span><?= number_format($calculatedSummary['float'] ?? 0, 2) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1 small fw-bold text-success">
                                            <span>+ Cash Sales:</span>
                                            <span><?= number_format($calculatedSummary['cash_sales'] ?? 0, 2) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-2 small fw-bold text-danger border-bottom pb-2">
                                            <span>- Expenses (Payouts):</span>
                                            <span><?= number_format($calculatedSummary['expenses'] ?? 0, 2) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between fs-5 text-dark fw-bold mt-2">
                                            <span>Expected Cash:</span>
                                            <span>ZMW <?= number_format($calculatedSummary['expected'] ?? 0, 2) ?></span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Actual Closing Cash Count (ZMW)</label>
                                        <input type="number" step="0.01" name="end_amount" class="form-control form-control-lg fw-bold text-primary shadow-sm" value="<?= $calculatedSummary['expected'] ?? 0 ?>" required>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label fw-bold small text-muted">Variance Reason (if any)</label>
                                        <textarea name="variance_reason" class="form-control shadow-sm" rows="2" placeholder="Explain missing or extra cash..."></textarea>
                                    </div>

                                <?php else: ?>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Handover Notes</label>
                                        <textarea name="handover_notes" class="form-control shadow-sm" rows="3" required placeholder="Issues, low stock, repairs..."></textarea>
                                    </div>
                                <?php endif; ?>

                                <div class="mb-4 mt-3 bg-dark p-3 rounded border border-secondary shadow-sm">
                                    <label class="form-label text-danger fw-bold small"><i class="bi bi-shield-lock-fill"></i> Manager Verification Required</label>
                                    <input type="password" name="manager_password" class="form-control border-danger" placeholder="Enter Manager Password" required autocomplete="new-password">
                                </div>

                                <button type="submit" class="btn btn-danger w-100 btn-lg fw-bold shadow">Clock Out & Close Shift</button>
                            </form>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm border-0 border-top border-secondary border-3">
                    <div class="card-header bg-white fw-bold text-muted">My Recent History</div>
                    <div class="card-body p-0">
                        <table class="table table-hover mb-0 text-center align-middle">
                            <thead class="small text-muted text-uppercase table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Time</th>
                                    <th>Info</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(!empty($history)): foreach ($history as $h): ?>
                                <tr>
                                    <td class="fw-bold"><?= date('d M', strtotime($h['start_time'])) ?></td>
                                    <td><?= date('H:i', strtotime($h['start_time'])) ?></td>
                                    <td>
                                        <?php if ($tmplIsMoneyRole): ?>
                                            <span class="badge bg-light text-dark border shadow-sm">ZMW <?= number_format($h['starting_cash'], 2) ?></span>
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
                                <?php endforeach; endif; ?>
                                <?php if (empty($history)): ?>
                                    <tr><td colspan="4" class="text-muted py-4 fst-italic">No personal shifts recorded yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>

<div class="modal fade" id="reportModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow-lg border-info border-top border-4">
            <div class="modal-header bg-light text-dark">
                <h5 class="modal-title fw-bold" id="reportTitle"><i class="bi bi-receipt text-info"></i> Shift X-Read</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-secondary" style="height: 65vh;">
                <iframe id="reportFrame" src="" style="width:100%; height:100%; border:none; background: #525659;"></iframe>
            </div>
            <div class="modal-footer bg-light border-0">
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-info fw-bold px-4 shadow-sm text-dark" onclick="document.getElementById('reportFrame').contentWindow.print()">
                    <i class="bi bi-printer"></i> Print Document
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    function showShiftReport(shiftId) {
        document.getElementById('reportFrame').src = 'about:blank';
        setTimeout(() => {
            document.getElementById('reportFrame').src = 'index.php?page=print_shift&shift_id=' + shiftId + '&_t=' + new Date().getTime();
        }, 100);
        new bootstrap.Modal(document.getElementById('reportModal')).show();
    }
</script>
