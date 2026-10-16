<?php
$money = fn($v) => number_format((float)$v, 2);
$hoursText = function ($v): string {
    $text = rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.');
    return $text === '' ? '0' : $text;
};
$tabs = ['runs' => ['bi-calendar2-check', 'Pay runs'], 'employees' => ['bi-person-badge', 'Employees'], 'components' => ['bi-list-check', 'Pay components'], 'rules' => ['bi-bank', 'Statutory rules']];
$earnings = array_values(array_filter($components, fn($c) => $c['kind'] === 'earning'));
$deductions = array_values(array_filter($components, fn($c) => $c['kind'] === 'deduction'));
?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h3 class="mb-0"><i class="bi bi-cash-coin text-success"></i> Payroll</h3>
        <p class="text-muted mb-0 small">Employees, allowances and deductions, Zambian PAYE / NAPSA / NHIMA, payslips.</p>
    </div>
    <?php if ($tab === 'runs' && empty($run)): ?>
    <button class="btn btn-success fw-bold" data-bs-toggle="modal" data-bs-target="#newRunModal"><i class="bi bi-plus-circle"></i> New pay run</button>
    <?php endif; ?>
</div>
<ul class="nav nav-tabs mb-4 flex-nowrap overflow-auto">
    <?php foreach ($tabs as $key => [$icon, $label]): ?>
    <li class="nav-item"><a class="nav-link fw-bold text-nowrap <?= $tab === $key ? 'active' : '' ?>" href="index.php?page=payroll&tab=<?= $key ?>"><i class="bi <?= $icon ?>"></i> <?= $label ?></a></li>
    <?php endforeach; ?>
</ul>

<?php if ($tab === 'runs' && !empty($run)): ?>
<?php $isDraft = $run['status'] === 'draft'; ?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <a href="index.php?page=payroll&tab=runs" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left"></i> All runs</a>
        <span class="fs-5 fw-bold ms-2"><?= htmlspecialchars($run['title']) ?></span>
        <span class="badge <?= $run['status'] === 'paid' ? 'bg-success' : ($run['status'] === 'approved' ? 'bg-primary' : 'bg-warning text-dark') ?> ms-1"><?= strtoupper($run['status']) ?></span>
        <div class="small text-muted"><?= date('d M Y', strtotime($run['period_start'])) ?> – <?= date('d M Y', strtotime($run['period_end'])) ?><?= $run['pay_date'] ? ' · pay date ' . date('d M Y', strtotime($run['pay_date'])) : '' ?></div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-outline-dark fw-bold" target="_blank" href="index.php?page=payslip&run_id=<?= (int)$run['id'] ?>"><i class="bi bi-printer"></i> Print all payslips</a>
        <?php if ($isDraft): ?>
            <form method="POST" class="d-inline js-confirm-form" data-swal-title="Approve this pay run?" data-swal-text="Figures are locked after approval." data-swal-confirm="Approve" data-swal-icon="question"><input type="hidden" name="run_id" value="<?= (int)$run['id'] ?>"><input type="hidden" name="set_status" value="approved"><button class="btn btn-primary fw-bold"><i class="bi bi-check2-circle"></i> Approve</button></form>
            <form method="POST" class="d-inline js-confirm-form" data-swal-title="Delete this draft run?" data-swal-confirm="Delete" data-swal-icon="warning" data-swal-confirm-color="#dc3545"><input type="hidden" name="delete_run" value="<?= (int)$run['id'] ?>"><button class="btn btn-outline-danger"><i class="bi bi-trash"></i></button></form>
        <?php elseif ($run['status'] === 'approved'): ?>
            <form method="POST" class="d-inline js-confirm-form" data-swal-title="Mark as paid?" data-swal-text="Net salaries (K<?= $money($run['total_net']) ?>) will be booked as a Salaries expense." data-swal-confirm="Mark paid" data-swal-icon="question"><input type="hidden" name="run_id" value="<?= (int)$run['id'] ?>"><input type="hidden" name="set_status" value="paid"><button class="btn btn-success fw-bold"><i class="bi bi-cash-stack"></i> Mark paid</button></form>
            <form method="POST" class="d-inline"><input type="hidden" name="run_id" value="<?= (int)$run['id'] ?>"><input type="hidden" name="set_status" value="draft"><button class="btn btn-outline-secondary"><i class="bi bi-unlock"></i> Reopen</button></form>
        <?php endif; ?>
    </div>
</div>
<div class="row g-3 mb-3">
    <?php foreach ([['Gross', $run['total_gross'], 'dark'], ['PAYE', $run['total_paye'], 'danger'], ['NAPSA (both)', $run['total_napsa'], 'warning'], ['NHIMA (both)', $run['total_nhima'], 'info'], ['Net pay', $run['total_net'], 'success']] as [$l, $v, $c]): ?>
    <div class="col-6 col-md"><div class="card border-0 shadow-sm border-start border-4 border-<?= $c ?>"><div class="card-body py-2"><div class="small text-muted fw-bold"><?= $l ?></div><div class="fs-5 fw-bold">K<?= $money($v) ?></div></div></div></div>
    <?php endforeach; ?>
</div>
<?php if ($isDraft): ?>
<?php
$bulkFormId = 'bulkSlips';
$bulkAction = 'index.php?page=payroll';
$bulkHidden = ['tab' => 'runs', 'run_id' => (int)$run['id']];
$bulkButtons = [
    ['value' => 'remove_slips', 'label' => 'Remove from run', 'class' => 'btn-outline-danger', 'title' => 'Remove the selected employees from this draft?', 'confirm' => 'Remove', 'color' => '#dc3545'],
];
include __DIR__ . '/partials/bulk_bar.php';
?>
<?php endif; ?>
<form method="POST" id="runForm">
    <input type="hidden" name="tab" value="runs">
    <input type="hidden" name="recalculate_run" value="<?= (int)$run['id'] ?>">
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead class="table-dark small">
                    <tr>
                        <?php if ($isDraft): ?><th style="width:36px"><input type="checkbox" class="form-check-input bulk-all" data-bulk-form="bulkSlips" title="Select all"></th><?php endif; ?>
                        <th>Employee</th>
                        <th class="text-end" style="width:90px">Days</th>
                        <th class="text-end" style="width:90px">Hours</th>
                        <th class="text-end" style="width:110px">Basic</th>
                        <th class="text-end">Allowances</th>
                        <th class="text-end" style="width:100px">Tips</th>
                        <th class="text-end">Gross</th>
                        <th class="text-end">PAYE</th>
                        <th class="text-end">NAPSA</th>
                        <th class="text-end">NHIMA</th>
                        <th class="text-end">Other ded.</th>
                        <th class="text-end">Net pay</th>
                        <th style="width:90px"></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$run['slips']): ?><tr><td colspan="<?= $isDraft ? 14 : 13 ?>" class="text-center text-muted py-4">No employees on this run. Add active employees and click Recalculate.</td></tr><?php endif; ?>
                <?php foreach ($run['slips'] as $s): $eid = (int)$s['employee_id']; ?>
                    <tr>
                        <?php if ($isDraft): ?><td><input type="checkbox" class="form-check-input bulk-one" name="ids[]" value="<?= $eid ?>" form="bulkSlips"></td><?php endif; ?>
                        <td>
                            <div class="fw-bold"><?= htmlspecialchars($s['full_name']) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($s['position'] ?? '') ?> · <?= htmlspecialchars($s['pay_type']) ?><?= $s['pay_type'] === 'hourly' ? ' · K' . $money($s['hourly_rate'] ?? 0) . '/hr' : '' ?><?= $s['employee_no'] ? ' · ' . htmlspecialchars($s['employee_no']) : '' ?></div>
                            <?php
                            $workedHours = ($s['hours_worked'] !== null && $s['hours_worked'] !== '')
                                ? (float)$s['hours_worked']
                                : (float)($s['attendance']['hours'] ?? 0);
                            $workedDays = ($s['days_worked'] !== null && $s['days_worked'] !== '')
                                ? $s['days_worked']
                                : ($s['attendance']['days'] ?? null);
                            $manualSub = '';
                            $manualLabel = '';
                            foreach ($s['lines'] as $ln) {
                                if (!empty($ln['manual']) && ($ln['kind'] ?? '') === 'deduction') {
                                    $manualSub = number_format((float)$ln['amount'], 2, '.', '');
                                    $manualLabel = (string)$ln['name'];
                                    break;
                                }
                            }
                            ?>
                            <div class="small fw-bold text-dark">Hours worked: <?= $hoursText($workedHours) ?></div>
                            <?php if (!empty($s['attendance']['open'])): ?>
                                <div class="small text-muted"><?php foreach ($s['attendance']['open'] as $op): $openDays = (int)($op['days'] ?? 1); ?>Still open since <?= date('d M H:i', strtotime($op['start'])) ?>, counted as <?= $openDays ?> <?= $openDays === 1 ? 'day' : 'days' ?> (<?= $hoursText($op['hours']) ?> hours). <?php endforeach; ?></div>
                            <?php endif; ?>
                            <?php if (!empty($s['attendance']['excluded'])): ?>
                                <div class="small text-warning"><?php foreach ($s['attendance']['excluded'] as $ex): ?>Shift <?= date('d M H:i', strtotime($ex['start'])) ?>–<?= date('d M H:i', strtotime($ex['end'])) ?> (<?= $hoursText($ex['hours']) ?> hrs) was left out. Type the hours. <?php endforeach; ?></div>
                            <?php endif; ?>
                            <input type="hidden" name="slip[<?= $eid ?>][subtraction]" value="<?= htmlspecialchars($manualSub) ?>">
                            <input type="hidden" name="slip[<?= $eid ?>][subtraction_label]" value="<?= htmlspecialchars($manualLabel) ?>">
                            <?php if ($s['lines']): ?><div class="small text-muted"><?php foreach ($s['lines'] as $ln): ?><span class="badge bg-light text-dark border me-1"><?= htmlspecialchars($ln['name']) ?> <?= $ln['kind'] === 'deduction' ? '−' : '+' ?><?= $money($ln['amount']) ?></span><?php endforeach; ?></div><?php endif; ?>
                        </td>
                        <td class="text-end"><?php if ($isDraft): ?><input type="number" step="0.5" min="0" name="slip[<?= $eid ?>][days_worked]" class="form-control form-control-sm text-end" value="<?= $workedDays !== null && $workedDays !== '' ? htmlspecialchars(rtrim(rtrim(number_format((float)$workedDays, 2, '.', ''), '0'), '.')) : '' ?>" placeholder="full"><?php else: ?><?= $workedDays !== null && $workedDays !== '' ? rtrim(rtrim(number_format((float)$workedDays, 2, '.', ''), '0'), '.') : '—' ?><?php endif; ?></td>
                        <td class="text-end"><?php if ($isDraft): ?><input type="number" step="0.01" min="0" name="slip[<?= $eid ?>][hours_worked]" class="form-control form-control-sm text-end" value="<?= $s['hours_worked'] !== null && $s['hours_worked'] !== '' ? htmlspecialchars($hoursText($s['hours_worked'])) : htmlspecialchars($hoursText($workedHours)) ?>"><?php else: ?><?= $hoursText($workedHours) ?><?php endif; ?></td>
                        <td class="text-end"><?php if ($isDraft): ?><input type="number" step="0.01" min="0" name="slip[<?= $eid ?>][basic]" class="form-control form-control-sm text-end" value="<?= number_format((float)$s['basic_pay'], 2, '.', '') ?>"><?php else: ?><?= $money($s['basic_pay']) ?><?php endif; ?></td>
                        <td class="text-end"><?= $money($s['allowances']) ?></td>
                        <td class="text-end"><?php if ($isDraft): ?><input type="number" step="0.01" min="0" name="slip[<?= $eid ?>][tips]" class="form-control form-control-sm text-end" value="<?= number_format((float)$s['tips'], 2, '.', '') ?>"><?php else: ?><?= $money($s['tips']) ?><?php endif; ?></td>
                        <td class="text-end fw-bold"><?= $money($s['gross_pay']) ?></td>
                        <td class="text-end text-danger"><?= (int)($s['paye_exempt'] ?? 0) === 1 ? '<span class="text-muted">Exempt</span>' : $money($s['paye']) ?></td>
                        <td class="text-end"><?= (int)($s['napsa_exempt'] ?? 0) === 1 ? '<span class="text-muted">Exempt</span>' : $money($s['napsa_employee']) ?></td>
                        <td class="text-end"><?= (int)($s['nhima_exempt'] ?? 0) === 1 ? '<span class="text-muted">Exempt</span>' : $money($s['nhima_employee']) ?></td>
                        <td class="text-end"><?= $money($s['other_deductions']) ?></td>
                        <td class="text-end fw-bold text-success">K<?= $money($s['net_pay']) ?></td>
                        <td class="text-end text-nowrap">
                            <a class="btn btn-sm btn-outline-dark" target="_blank" title="Payslip" href="index.php?page=payslip&id=<?= (int)$s['id'] ?>"><i class="bi bi-receipt"></i></a>
                            <?php if ($isDraft): ?>
                            <button type="button" class="btn btn-sm btn-outline-primary" title="One-off adjustments" onclick="openExtra(<?= $eid ?>, <?= htmlspecialchars(json_encode($s['full_name']), ENT_QUOTES) ?>)"><i class="bi bi-plus-slash-minus"></i></button>
                            <button type="submit" form="removeSlip<?= $eid ?>" class="btn btn-sm btn-outline-danger" title="Remove from run"><i class="bi bi-x"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($isDraft): ?>
        <div class="card-footer bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="form-check"><input class="form-check-input" type="checkbox" name="add_missing" value="1" id="addMissing" checked><label class="form-check-label small" for="addMissing">Include active employees not yet on this run</label></div>
            <div id="extraInputs"></div>
            <button class="btn btn-warning fw-bold"><i class="bi bi-arrow-repeat"></i> Recalculate payslips</button>
        </div>
        <?php endif; ?>
    </div>
</form>
<?php if ($isDraft): foreach ($run['slips'] as $s): ?>
<form method="POST" id="removeSlip<?= (int)$s['employee_id'] ?>" class="js-confirm-form" data-swal-title="Remove <?= htmlspecialchars($s['full_name']) ?> from this run?" data-swal-confirm="Remove" data-swal-icon="warning"><input type="hidden" name="tab" value="runs"><input type="hidden" name="run_id" value="<?= (int)$run['id'] ?>"><input type="hidden" name="remove_slip" value="<?= (int)$s['employee_id'] ?>"></form>
<?php endforeach; endif; ?>
<div class="alert alert-light border small mt-3"><i class="bi bi-info-circle"></i> Hours worked comes from that person's shifts in the pay period. A shift still open counts as 8 hours for each day from the day it opened through today, or through the end of the pay period. On a daily rate each of those days counts as 1 day. A closed shift longer than 16 hours is named on the row and left out. One-off adjustments can set the hours, the days, and a subtraction for this run only. Clear Hours or Days and recalculate to read the shifts again. A Basic amount you change is kept.</div>

<!-- one-off adjustments modal -->
<div class="modal fade" id="extraModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title fw-bold">One-off adjustments · <span id="extraName"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <p class="small text-muted">These apply to this pay run only. Standing allowances on the employee are unchanged.</p>
        <div class="row g-2 mb-3">
            <div class="col-6"><label class="form-label small fw-bold text-muted">DAYS WORKED</label><input type="number" step="0.5" min="0" class="form-control form-control-sm" id="extraDays"></div>
            <div class="col-6"><label class="form-label small fw-bold text-muted">HOURS WORKED</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" id="extraHours"></div>
        </div>
        <div class="row g-2 mb-3">
            <div class="col-7"><label class="form-label small fw-bold text-muted">SUBTRACTION</label><input type="text" class="form-control form-control-sm" id="extraSubLabel" maxlength="80" placeholder="Reason, e.g. advance, shortage"></div>
            <div class="col-5"><label class="form-label small fw-bold text-muted">AMOUNT</label><input type="number" step="0.01" min="0" class="form-control form-control-sm" id="extraSub" placeholder="0.00"></div>
        </div>
        <?php if ($components): ?><div class="small fw-bold text-muted mb-2">OTHER ONE-OFF AMOUNTS</div><?php endif; ?>
        <?php foreach ($components as $c): ?>
        <div class="row g-2 align-items-center mb-2">
            <div class="col-7"><span class="badge <?= $c['kind'] === 'deduction' ? 'bg-danger' : 'bg-success' ?> me-1"><?= $c['kind'] === 'deduction' ? '−' : '+' ?></span><?= htmlspecialchars($c['name']) ?></div>
            <div class="col-5"><input type="number" step="0.01" min="0" class="form-control form-control-sm js-extra" data-cid="<?= (int)$c['id'] ?>" placeholder="0.00"></div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-warning fw-bold" onclick="applyExtra()">Apply & recalculate</button></div>
</div></div></div>
<script>
let extraEid = 0;
function slipField(eid, field) { return document.querySelector('#runForm [name="slip[' + eid + '][' + field + ']"]'); }
function openExtra(eid, name) {
    extraEid = eid;
    document.getElementById('extraName').textContent = name;
    document.querySelectorAll('.js-extra').forEach(function (i) {
        const ex = document.querySelector('#runForm [name="slip[' + eid + '][extra][' + i.dataset.cid + ']"]');
        i.value = ex ? ex.value : '';
    });
    const days = slipField(eid, 'days_worked');
    const hours = slipField(eid, 'hours_worked');
    const sub = slipField(eid, 'subtraction');
    const subLabel = slipField(eid, 'subtraction_label');
    document.getElementById('extraDays').value = days ? days.value : '';
    document.getElementById('extraHours').value = hours ? hours.value : '';
    document.getElementById('extraSub').value = sub ? sub.value : '';
    document.getElementById('extraSubLabel').value = subLabel ? subLabel.value : '';
    new bootstrap.Modal(document.getElementById('extraModal')).show();
}
function applyExtra() {
    const holder = document.getElementById('extraInputs');
    document.querySelectorAll('.js-extra').forEach(function (i) {
        const name = 'slip[' + extraEid + '][extra][' + i.dataset.cid + ']';
        let h = holder.querySelector('input[name="' + name + '"]');
        if (!h) { h = document.createElement('input'); h.type = 'hidden'; h.name = name; holder.appendChild(h); }
        h.value = i.value;
    });
    const days = slipField(extraEid, 'days_worked');
    const hours = slipField(extraEid, 'hours_worked');
    const sub = slipField(extraEid, 'subtraction');
    const subLabel = slipField(extraEid, 'subtraction_label');
    if (days) days.value = document.getElementById('extraDays').value;
    if (hours) hours.value = document.getElementById('extraHours').value;
    if (sub) sub.value = document.getElementById('extraSub').value;
    if (subLabel) subLabel.value = document.getElementById('extraSubLabel').value;
    document.getElementById('runForm').submit();
}
</script>

<?php elseif ($tab === 'runs'): ?>
<div class="card shadow-sm border-0">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-dark"><tr><th>Pay run</th><th>Period</th><th>Pay date</th><th class="text-center">Staff</th><th class="text-end">Gross</th><th class="text-end">PAYE</th><th class="text-end">Net</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (!$runs): ?><tr><td colspan="9" class="text-center text-muted py-5">No pay runs yet. Add employees, then click <strong>New pay run</strong>.</td></tr><?php endif; ?>
            <?php foreach ($runs as $r): ?>
                <tr>
                    <td class="fw-bold"><?= htmlspecialchars($r['title']) ?><div class="small text-muted fw-normal">by <?= htmlspecialchars($r['created_by_name'] ?? '') ?></div></td>
                    <td class="text-nowrap small"><?= date('d M', strtotime($r['period_start'])) ?> – <?= date('d M Y', strtotime($r['period_end'])) ?></td>
                    <td class="small"><?= $r['pay_date'] ? date('d M Y', strtotime($r['pay_date'])) : '—' ?></td>
                    <td class="text-center"><?= (int)$r['slip_count'] ?></td>
                    <td class="text-end"><?= $money($r['total_gross']) ?></td>
                    <td class="text-end text-danger"><?= $money($r['total_paye']) ?></td>
                    <td class="text-end fw-bold text-success">K<?= $money($r['total_net']) ?></td>
                    <td><span class="badge <?= $r['status'] === 'paid' ? 'bg-success' : ($r['status'] === 'approved' ? 'bg-primary' : 'bg-warning text-dark') ?>"><?= strtoupper($r['status']) ?></span></td>
                    <td class="text-end"><a class="btn btn-sm btn-outline-dark" href="index.php?page=payroll&tab=runs&run_id=<?= (int)$r['id'] ?>">Open</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<div class="modal fade" id="newRunModal" tabindex="-1"><div class="modal-dialog"><div class="modal-content">
    <form method="POST">
        <input type="hidden" name="tab" value="runs"><input type="hidden" name="create_run" value="1">
        <div class="modal-header"><h5 class="modal-title fw-bold">New pay run</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-3"><label class="form-label small fw-bold text-muted">TITLE</label><input type="text" name="title" class="form-control" placeholder="Payroll <?= date('F Y') ?>"></div>
            <div class="row g-2">
                <div class="col-6"><label class="form-label small fw-bold text-muted">PERIOD START</label><input type="date" name="period_start" class="form-control" value="<?= date('Y-m-01') ?>" required></div>
                <div class="col-6"><label class="form-label small fw-bold text-muted">PERIOD END</label><input type="date" name="period_end" class="form-control" value="<?= date('Y-m-t') ?>" required></div>
                <div class="col-6"><label class="form-label small fw-bold text-muted">PAY DATE</label><input type="date" name="pay_date" class="form-control" value="<?= date('Y-m-t') ?>"></div>
            </div>
            <div class="mt-3"><label class="form-label small fw-bold text-muted">NOTES</label><input type="text" name="notes" class="form-control" maxlength="500"></div>
            <div class="form-text mt-2">Every active employee is added with a full-period payslip. You can then adjust days, tips and one-off items before approving.</div>
        </div>
        <div class="modal-footer"><button class="btn btn-success fw-bold">Create draft run</button></div>
    </form>
</div></div></div>

<?php elseif ($tab === 'employees'): ?>
<?php $e = $editEmployee ?? []; $ec = $e['components'] ?? []; ?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm border-0 border-top border-4 border-success">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="bi bi-person-plus text-success"></i> <?= $e ? 'Edit employee' : 'Add employee' ?></h5>
                <form method="POST">
                    <input type="hidden" name="tab" value="employees"><input type="hidden" name="save_employee" value="1">
                    <input type="hidden" name="employee_id" value="<?= (int)($e['id'] ?? 0) ?>">
                    <div class="row g-2">
                        <div class="col-8"><label class="form-label small fw-bold text-muted">EMPLOYEE</label>
                            <select name="user_id" id="empStaff" class="form-select" required data-placeholder="Choose a staff member">
                                <option value="">Choose a staff member</option>
                                <?php foreach ($staffUsers as $u): $staffLabel = $u['full_name'] ?: $u['username']; ?>
                                <option value="<?= (int)$u['id'] ?>" data-role="<?= htmlspecialchars((string)$u['role']) ?>" data-name="<?= htmlspecialchars($staffLabel) ?>" data-location="<?= (int)($u['location_id'] ?? 0) ?>" <?= (int)($e['user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>><?= htmlspecialchars($staffLabel) ?> (<?= htmlspecialchars($u['username']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">The name and the tips come from this staff login.</div>
                        </div>
                        <div class="col-4"><label class="form-label small fw-bold text-muted">EMP NO</label><input type="text" name="employee_no" class="form-control" value="<?= htmlspecialchars($e['employee_no'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">ROLE</label>
                            <?php $posNow = strtolower(trim((string)($e['position'] ?? ''))); $roleMatched = false; ?>
                            <select name="position" id="empRole" class="form-select" required data-placeholder="Choose a role">
                                <option value="">Choose a role</option>
                                <?php foreach ($staffRoles as $role):
                                    $roleOn = $posNow !== '' && ($posNow === strtolower($role['key']) || $posNow === strtolower($role['label']));
                                    if ($roleOn) { $roleMatched = true; }
                                ?>
                                <option value="<?= htmlspecialchars($role['key']) ?>" data-role="<?= htmlspecialchars($role['key']) ?>" <?= $roleOn ? 'selected' : '' ?>><?= htmlspecialchars($role['label']) ?></option>
                                <?php endforeach; ?>
                                <?php if ($e && !$roleMatched && trim((string)($e['position'] ?? '')) !== ''): ?>
                                <option value="<?= htmlspecialchars($e['position']) ?>" selected><?= htmlspecialchars($e['position']) ?></option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">DEPARTMENT</label><input type="text" name="department" class="form-control" value="<?= htmlspecialchars($e['department'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">LOCATION</label>
                            <select name="location_id" class="form-select"><option value="">—</option><?php foreach ($locations as $l): ?><option value="<?= (int)$l['id'] ?>" data-type="<?= htmlspecialchars($l['type'] ?? '') ?>" <?= (int)($e['location_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>><?= htmlspecialchars($l['name']) ?></option><?php endforeach; ?></select></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">PHONE</label><input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($e['phone'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">EMAIL</label><input type="email" name="email" class="form-control" value="<?= htmlspecialchars($e['email'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">NRC</label><input type="text" name="nrc" class="form-control" value="<?= htmlspecialchars($e['nrc'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">TPIN</label><input type="text" name="tpin" class="form-control" value="<?= htmlspecialchars($e['tpin'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">NAPSA NO</label><input type="text" name="napsa_no" class="form-control" value="<?= htmlspecialchars($e['napsa_no'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">NHIMA NO</label><input type="text" name="nhima_no" class="form-control" value="<?= htmlspecialchars($e['nhima_no'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">HIRE DATE</label><input type="date" name="hire_date" class="form-control" value="<?= htmlspecialchars($e['hire_date'] ?? '') ?>"></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">TERMINATION</label><input type="date" name="termination_date" class="form-control" value="<?= htmlspecialchars($e['termination_date'] ?? '') ?>"></div>
                    </div>
                    <hr>
                    <div class="row g-2">
                        <div class="col-4"><label class="form-label small fw-bold text-muted">PAY TYPE</label>
                            <select name="pay_type" class="form-select no-search" id="payType"><?php foreach (['monthly' => 'Monthly', 'daily' => 'Daily rate', 'hourly' => 'Hourly rate'] as $k => $v): ?><option value="<?= $k ?>" <?= ($e['pay_type'] ?? 'monthly') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
                        <div class="col-8 pay-field" data-for="monthly"><label class="form-label small fw-bold text-muted">BASIC SALARY / MONTH</label><input type="number" step="0.01" min="0" name="basic_salary" class="form-control" value="<?= htmlspecialchars((string)($e['basic_salary'] ?? '')) ?>"></div>
                        <div class="col-8 pay-field" data-for="daily"><label class="form-label small fw-bold text-muted">DAILY RATE</label><input type="number" step="0.01" min="0" name="daily_rate" class="form-control" value="<?= htmlspecialchars((string)($e['daily_rate'] ?? '')) ?>"></div>
                        <div class="col-8 pay-field" data-for="hourly"><label class="form-label small fw-bold text-muted">HOURLY RATE</label><input type="number" step="0.01" min="0" name="hourly_rate" class="form-control" value="<?= htmlspecialchars((string)($e['hourly_rate'] ?? '')) ?>"></div>
                        <div class="col-4"><label class="form-label small fw-bold text-muted">PAID BY</label>
                            <select name="pay_method" class="form-select no-search"><?php foreach (['bank' => 'Bank', 'cash' => 'Cash', 'mobile' => 'Mobile money'] as $k => $v): ?><option value="<?= $k ?>" <?= ($e['pay_method'] ?? 'bank') === $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?></select></div>
                        <div class="col-4"><label class="form-label small fw-bold text-muted">BANK</label><input type="text" name="bank_name" class="form-control" value="<?= htmlspecialchars($e['bank_name'] ?? '') ?>"></div>
                        <div class="col-4"><label class="form-label small fw-bold text-muted">ACCOUNT / MOMO</label><input type="text" name="bank_account" class="form-control" value="<?= htmlspecialchars($e['bank_account'] ?? '') ?>" placeholder="Acc no"><input type="text" name="mobile_money" class="form-control mt-1" value="<?= htmlspecialchars($e['mobile_money'] ?? '') ?>" placeholder="MoMo no"></div>
                    </div>
                    <hr>
                    <label class="form-label small fw-bold text-muted">STANDING ALLOWANCES & DEDUCTIONS</label>
                    <?php foreach ($components as $c): if (!$c['is_active']) continue; ?>
                    <div class="row g-2 align-items-center mb-1">
                        <div class="col-7 small"><span class="badge <?= $c['kind'] === 'deduction' ? 'bg-danger' : 'bg-success' ?> me-1"><?= $c['kind'] === 'deduction' ? '−' : '+' ?></span><?= htmlspecialchars($c['name']) ?> <span class="text-muted"><?= $c['calc'] === 'percent' ? '(% of basic)' : '' ?></span></div>
                        <div class="col-5"><input type="number" step="0.01" min="0" name="comp[<?= (int)$c['id'] ?>]" class="form-control form-control-sm" value="<?= isset($ec[$c['id']]) ? htmlspecialchars((string)$ec[$c['id']]) : '' ?>" placeholder="<?= (float)$c['amount'] > 0 ? 'default ' . $c['amount'] : '0' ?>"></div>
                    </div>
                    <?php endforeach; ?>
                    <div class="mt-3">
                        <label class="form-label small fw-bold text-muted mb-1">STATUTORY DEDUCTIONS</label>
                        <div class="d-flex flex-wrap gap-3 small">
                            <?php $deductOn = empty($e['id']) || (int)($e['paye_exempt'] ?? 0) !== 1; ?>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="deduct_paye" value="1" id="payeEx" <?= $deductOn ? 'checked' : '' ?>><label class="form-check-label" for="payeEx">PAYE</label></div>
                            <?php $deductOn = empty($e['id']) || (int)($e['napsa_exempt'] ?? 0) !== 1; ?>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="deduct_napsa" value="1" id="napsaEx" <?= $deductOn ? 'checked' : '' ?>><label class="form-check-label" for="napsaEx">NAPSA</label></div>
                            <?php $deductOn = empty($e['id']) || (int)($e['nhima_exempt'] ?? 0) !== 1; ?>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="deduct_nhima" value="1" id="nhimaEx" <?= $deductOn ? 'checked' : '' ?>><label class="form-check-label" for="nhimaEx">NHIMA</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="empActive" <?= ($e['is_active'] ?? 1) ? 'checked' : '' ?>><label class="form-check-label" for="empActive">Active</label></div>
                        </div>
                        <div class="form-text">Ticked items are calculated on every pay run. Untick one when that worker is not registered for it.</div>
                    </div>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-success fw-bold flex-grow-1"><i class="bi bi-save"></i> Save employee</button>
                        <?php if ($e): ?><a href="index.php?page=payroll&tab=employees" class="btn btn-outline-secondary">New</a><?php endif; ?>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <?php
        $bulkFormId = 'bulkEmployees';
        $bulkAction = 'index.php?page=payroll';
        $bulkHidden = ['tab' => 'employees'];
        $bulkButtons = [
            ['value' => 'activate', 'label' => 'Activate', 'class' => 'btn-success', 'title' => 'Activate the selected employees?', 'confirm' => 'Activate', 'color' => '#198754'],
            ['value' => 'deactivate', 'label' => 'Deactivate', 'class' => 'btn-outline-light', 'title' => 'Deactivate the selected employees?', 'text' => 'They stay on file but drop out of new pay runs.', 'confirm' => 'Deactivate', 'color' => '#6c757d'],
        ];
        include __DIR__ . '/partials/bulk_bar.php';
        ?>
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-dark"><tr><th style="width:36px"><input type="checkbox" class="form-check-input bulk-all" data-bulk-form="bulkEmployees" title="Select all"></th><th>Employee</th><th>Position</th><th>Pay</th><th>Statutory</th><th class="text-center">Status</th><th></th></tr></thead>
                    <tbody>
                    <?php if (!$employees): ?><tr><td colspan="7" class="text-center text-muted py-4">No employees yet.</td></tr><?php endif; ?>
                    <?php foreach ($employees as $row): ?>
                        <tr class="<?= $row['is_active'] ? '' : 'text-muted' ?>">
                            <td><input type="checkbox" class="form-check-input bulk-one" name="ids[]" value="<?= (int)$row['id'] ?>" form="bulkEmployees"></td>
                            <td><div class="fw-bold"><?= htmlspecialchars($row['full_name']) ?></div><div class="small text-muted"><?= htmlspecialchars($row['employee_no'] ?? '') ?><?= $row['username'] ? ' · POS: ' . htmlspecialchars($row['username']) : '' ?></div></td>
                            <td class="small"><?= htmlspecialchars($row['position'] ?? '') ?><br><span class="text-muted"><?= htmlspecialchars($row['location_name'] ?? '') ?></span></td>
                            <td class="small"><?= $row['pay_type'] === 'monthly' ? 'K' . $money($row['basic_salary']) . '/mo' : ($row['pay_type'] === 'daily' ? 'K' . $money($row['daily_rate']) . '/day' : 'K' . $money($row['hourly_rate']) . '/hr') ?><br><span class="text-muted"><?= htmlspecialchars($row['pay_method']) ?></span></td>
                            <td class="small"><?= $row['tpin'] ? 'TPIN ' . htmlspecialchars($row['tpin']) : '<span class="text-danger">no TPIN</span>' ?><br><?= $row['napsa_no'] ? 'NAPSA ' . htmlspecialchars($row['napsa_no']) : '<span class="text-danger">no NAPSA</span>' ?></td>
                            <td class="text-center"><?= $row['is_active'] ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-secondary">Inactive</span>' ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-primary" href="index.php?page=payroll&tab=employees&employee_id=<?= (int)$row['id'] ?>"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline"><input type="hidden" name="tab" value="employees"><input type="hidden" name="toggle_employee" value="<?= (int)$row['id'] ?>"><button class="btn btn-sm btn-outline-secondary" title="Activate / deactivate"><i class="bi bi-power"></i></button></form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    const sel = document.getElementById('payType');
    const upd = function () { document.querySelectorAll('.pay-field').forEach(function (f) { f.classList.toggle('d-none', f.dataset.for !== sel.value); }); };
    sel.addEventListener('change', upd);
    upd();
    function setSelect(el, value) {
        if (!el) return;
        if (el.tomselect) el.tomselect.setValue(value || '', true);
        else el.value = value || '';
    }
    const staff = document.getElementById('empStaff');
    staff.addEventListener('change', function () {
        const opt = staff.options[staff.selectedIndex];
        if (!opt || !opt.value) return;
        const roleKey = opt.getAttribute('data-role') || '';
        const roleSel = document.getElementById('empRole');
        let match = '';
        Array.prototype.forEach.call(roleSel.options, function (o) {
            if (o.getAttribute('data-role') === roleKey || o.value === roleKey) match = o.value;
        });
        if (match) setSelect(roleSel, match);
        const locId = opt.getAttribute('data-location') || '0';
        setSelect(document.querySelector('select[name="location_id"]'), locId === '0' ? '' : locId);
    });
})();
</script>

<?php elseif ($tab === 'components'): ?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 border-top border-4 border-primary">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><span id="compTitle">Add pay component</span></h5>
                <p class="small text-muted">Allowances (earnings) and deductions available to every employee. Set a default and override per employee.</p>
                <form method="POST" id="compForm">
                    <input type="hidden" name="tab" value="components"><input type="hidden" name="save_component" value="1"><input type="hidden" name="component_id" id="compId" value="">
                    <div class="mb-2"><label class="form-label small fw-bold text-muted">NAME</label><input type="text" name="name" id="compName" class="form-control" required></div>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small fw-bold text-muted">TYPE</label><select name="kind" id="compKind" class="form-select no-search"><option value="earning">Earning / allowance</option><option value="deduction">Deduction</option></select></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">CALCULATED AS</label><select name="calc" id="compCalc" class="form-select no-search"><option value="fixed">Fixed amount</option><option value="percent">% of basic</option></select></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">DEFAULT</label><input type="number" step="0.01" min="0" name="amount" id="compAmount" class="form-control" value="0"></div>
                        <div class="col-6"><label class="form-label small fw-bold text-muted">SORT</label><input type="number" name="sort_order" id="compSort" class="form-control" value="0"></div>
                    </div>
                    <div class="d-flex gap-3 mt-3 small">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_taxable" value="1" id="compTax" checked><label class="form-check-label" for="compTax">Taxable (PAYE)</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="compActive" checked><label class="form-check-label" for="compActive">Active</label></div>
                    </div>
                    <div class="d-flex gap-2 mt-3"><button class="btn btn-primary fw-bold flex-grow-1">Save</button><button type="button" class="btn btn-outline-secondary" onclick="compReset()">Clear</button></div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <?php
        $bulkFormId = 'bulkComponents';
        $bulkAction = 'index.php?page=payroll';
        $bulkHidden = ['tab' => 'components'];
        $bulkButtons = [
            ['value' => 'delete_components', 'label' => 'Remove selected', 'class' => 'btn-outline-danger', 'title' => 'Remove the selected pay components?', 'text' => 'They are removed from all employees. Past payslips are unchanged.', 'confirm' => 'Remove', 'color' => '#dc3545'],
        ];
        include __DIR__ . '/partials/bulk_bar.php';
        ?>
        <div class="card shadow-sm border-0">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-dark"><tr><th style="width:36px"><input type="checkbox" class="form-check-input bulk-all" data-bulk-form="bulkComponents" title="Select all"></th><th>Component</th><th>Type</th><th>Calc</th><th class="text-end">Default</th><th class="text-center">Taxable</th><th class="text-center">Active</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($components as $c): ?>
                    <tr>
                        <td><input type="checkbox" class="form-check-input bulk-one" name="ids[]" value="<?= (int)$c['id'] ?>" form="bulkComponents"></td>
                        <td class="fw-bold"><?= htmlspecialchars($c['name']) ?></td>
                        <td><span class="badge <?= $c['kind'] === 'deduction' ? 'bg-danger' : 'bg-success' ?>"><?= $c['kind'] ?></span></td>
                        <td class="small"><?= $c['calc'] === 'percent' ? '% of basic' : 'fixed' ?></td>
                        <td class="text-end"><?= $c['calc'] === 'percent' ? rtrim(rtrim((string)$c['amount'], '0'), '.') . '%' : $money($c['amount']) ?></td>
                        <td class="text-center"><?= $c['is_taxable'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-dash text-muted"></i>' ?></td>
                        <td class="text-center"><?= $c['is_active'] ? '<i class="bi bi-check-circle text-success"></i>' : '<i class="bi bi-x-circle text-muted"></i>' ?></td>
                        <td class="text-end text-nowrap">
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick='compEdit(<?= json_encode($c) ?>)'><i class="bi bi-pencil"></i></button>
                            <form method="POST" class="d-inline js-confirm-form" data-swal-title="Remove this component?" data-swal-text="It is removed from all employees. Past payslips are unchanged." data-swal-confirm="Remove" data-swal-icon="warning" data-swal-confirm-color="#dc3545"><input type="hidden" name="tab" value="components"><input type="hidden" name="delete_component" value="<?= (int)$c['id'] ?>"><button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<script>
function compEdit(c) { document.getElementById('compTitle').textContent = 'Edit pay component'; document.getElementById('compId').value = c.id; document.getElementById('compName').value = c.name; document.getElementById('compKind').value = c.kind; document.getElementById('compCalc').value = c.calc; document.getElementById('compAmount').value = c.amount; document.getElementById('compSort').value = c.sort_order; document.getElementById('compTax').checked = String(c.is_taxable) === '1'; document.getElementById('compActive').checked = String(c.is_active) === '1'; }
function compReset() { document.getElementById('compTitle').textContent = 'Add pay component'; document.getElementById('compForm').reset(); document.getElementById('compId').value = ''; }
</script>

<?php elseif ($tab === 'rules'): ?>
<div class="row">
    <div class="col-lg-8 mx-auto">
        <div class="card shadow-sm border-0 border-top border-4 border-dark">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-1"><i class="bi bi-bank"></i> Zambian statutory rules</h5>
                <p class="small text-muted">Defaults follow the current ZRA PAYE monthly bands, NAPSA 5% + 5% (capped) and NHIMA 1% + 1%. Update here when the national budget changes them.</p>
                <form method="POST">
                    <input type="hidden" name="tab" value="rules"><input type="hidden" name="save_rules" value="1">
                    <label class="form-label small fw-bold text-muted">PAYE BANDS (monthly, cumulative — leave the last "up to" blank for "and above")</label>
                    <div id="bands">
                        <?php foreach ($config['paye_bands'] as $b): ?>
                        <div class="row g-2 mb-2 band-row">
                            <div class="col-6"><div class="input-group input-group-sm"><span class="input-group-text">Up to K</span><input type="number" step="0.01" min="0" name="band_upto[]" class="form-control" value="<?= $b['upto'] !== null ? htmlspecialchars((string)$b['upto']) : '' ?>" placeholder="and above"></div></div>
                            <div class="col-4"><div class="input-group input-group-sm"><input type="number" step="0.01" min="0" max="100" name="band_rate[]" class="form-control" value="<?= htmlspecialchars((string)$b['rate']) ?>" required><span class="input-group-text">%</span></div></div>
                            <div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest('.band-row').remove()"><i class="bi bi-x"></i></button></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary mb-3" onclick="addBand()"><i class="bi bi-plus"></i> Add band</button>
                    <div class="row g-3">
                        <div class="col-md-4"><label class="form-label small fw-bold text-muted">NAPSA RATE (each side)</label><div class="input-group"><input type="number" step="0.01" name="napsa_rate" class="form-control" value="<?= $config['napsa_rate'] ?>"><span class="input-group-text">%</span></div></div>
                        <div class="col-md-4"><label class="form-label small fw-bold text-muted">NAPSA MONTHLY CAP (each side)</label><div class="input-group"><span class="input-group-text">K</span><input type="number" step="0.01" name="napsa_cap" class="form-control" value="<?= $config['napsa_cap'] ?>"></div></div>
                        <div class="col-md-4"><label class="form-label small fw-bold text-muted">NHIMA RATE (each side)</label><div class="input-group"><input type="number" step="0.01" name="nhima_rate" class="form-control" value="<?= $config['nhima_rate'] ?>"><span class="input-group-text">%</span></div></div>
                        <div class="col-md-4"><label class="form-label small fw-bold text-muted">WORKING DAYS / MONTH</label><input type="number" min="1" max="31" name="working_days" class="form-control" value="<?= $config['working_days'] ?>"></div>
                        <div class="col-md-8 d-flex flex-column justify-content-end gap-2">
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="include_tips" value="1" id="incTips" <?= $config['include_tips'] ? 'checked' : '' ?>><label class="form-check-label small fw-bold" for="incTips">Add POS tips to payslips automatically</label></div>
                            <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="tips_taxable" value="1" id="taxTips" <?= $config['tips_taxable'] ? 'checked' : '' ?>><label class="form-check-label small fw-bold" for="taxTips">Tips are subject to PAYE</label></div>
                        </div>
                    </div>
                    <div class="text-end mt-4"><button class="btn btn-dark fw-bold px-4"><i class="bi bi-save"></i> Save rules</button></div>
                </form>
                <hr>
                <h6 class="fw-bold">Quick PAYE check</h6>
                <div class="input-group" style="max-width:420px">
                    <span class="input-group-text">Taxable K</span>
                    <input type="number" step="0.01" id="payeIn" class="form-control" value="10000" oninput="payeCalc()">
                    <span class="input-group-text fw-bold" id="payeOut">—</span>
                </div>
            </div>
        </div>
    </div>
</div>
<script>
const bands = <?= json_encode($config['paye_bands']) ?>;
function addBand() { const w = document.getElementById('bands'); const d = document.createElement('div'); d.className = 'row g-2 mb-2 band-row'; d.innerHTML = '<div class="col-6"><div class="input-group input-group-sm"><span class="input-group-text">Up to K</span><input type="number" step="0.01" min="0" name="band_upto[]" class="form-control" placeholder="and above"></div></div><div class="col-4"><div class="input-group input-group-sm"><input type="number" step="0.01" min="0" max="100" name="band_rate[]" class="form-control" value="0" required><span class="input-group-text">%</span></div></div><div class="col-2"><button type="button" class="btn btn-sm btn-outline-danger w-100" onclick="this.closest(\'.band-row\').remove()"><i class="bi bi-x"></i></button></div>'; w.appendChild(d); }
function payeCalc() { let t = parseFloat(document.getElementById('payeIn').value) || 0, tax = 0, lower = 0; for (const b of bands) { const upper = b.upto === null ? null : parseFloat(b.upto); if (upper !== null && t <= lower) break; const slice = upper === null ? Math.max(0, t - lower) : Math.max(0, Math.min(t, upper) - lower); tax += slice * parseFloat(b.rate) / 100; if (upper === null) break; lower = upper; } document.getElementById('payeOut').textContent = 'PAYE K' + tax.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
payeCalc();
</script>
<?php endif; ?>
