<?php
/** @var array{filters: array<string, mixed>, figures: array<string, float|int>, settlement: array<string, mixed>, locations: list<array<string, mixed>>, employees: list<array<string, mixed>>} $ownerOverview */
$filters = $ownerOverview['filters'];
$figures = $ownerOverview['figures'];
$settlement = $ownerOverview['settlement'];
$money = static function (float $amount): string {
    return 'ZMW ' . number_format($amount, 2);
};
$start = (string)$filters['start'];
$end = (string)$filters['end'];
$daypart = (string)$filters['daypart'];
$locationId = (int)$filters['location'];
$employeeId = (int)$filters['employee'];
$export = 'index.php?page=reports&type=sales&start=' . rawurlencode($start)
    . '&end=' . rawurlencode($end)
    . '&location=' . $locationId
    . '&employee_id=' . $employeeId
    . '&daypart=' . rawurlencode($daypart);
$subId = (string)($settlement['subaccount_id'] ?? '');
$profile = is_array($settlement['profile'] ?? null) ? $settlement['profile'] : [];
$subName = trim((string)($profile['name'] ?? ''));
$tradeFigures = $figures;
$businessValue = $ownerOverview['business'] ?? [];
$figureFilters = $filters;
$settleCards = [
    ['key' => 'due', 'title' => 'Due to the subaccount', 'amount' => (float)($settlement['processed'] ?? 0), 'class' => 'text-primary'],
    ['key' => 'fees', 'title' => 'Fees on subaccount payouts', 'amount' => (float)($settlement['fees'] ?? 0), 'class' => 'text-danger'],
    ['key' => 'waiting', 'title' => 'Waiting on Flutterwave', 'amount' => (float)($settlement['pending'] ?? 0), 'class' => 'text-warning'],
    ['key' => 'paid', 'title' => 'Paid to the subaccount', 'amount' => (float)($settlement['settled'] ?? 0), 'class' => 'text-success'],
];
?>
<style>
    .owner-metric { border: 0; border-bottom: 3px solid transparent; background: #fff; text-align: left; width: 100%; }
    .owner-metric:hover, .owner-metric:focus { background: #f8f9fa; border-bottom-color: #4caf50; }
    .owner-batch { cursor: pointer; }
</style>

<form method="GET" class="d-flex flex-wrap align-items-center gap-2 mb-3" id="ownerFilterForm">
    <input type="hidden" name="page" value="dashboard">
    <div class="btn-group">
        <button type="button" class="btn btn-outline-secondary" id="ownerPrev" aria-label="Previous period"><i class="bi bi-chevron-left"></i></button>
        <input type="date" class="form-control" name="start" id="ownerStart" value="<?= htmlspecialchars($start) ?>" aria-label="From" style="width: auto; border-radius: 0;" onchange="this.form.submit()">
        <input type="date" class="form-control" name="end" id="ownerEnd" value="<?= htmlspecialchars($end) ?>" aria-label="To" style="width: auto; border-radius: 0;" onchange="this.form.submit()">
        <button type="button" class="btn btn-outline-secondary" id="ownerNext" aria-label="Next period"><i class="bi bi-chevron-right"></i></button>
    </div>
    <select name="daypart" class="form-select" style="width: auto;" onchange="this.form.submit()" aria-label="Time of day">
        <option value="" <?= $daypart === '' ? 'selected' : '' ?>>All day</option>
        <option value="morning" <?= $daypart === 'morning' ? 'selected' : '' ?>>Morning</option>
        <option value="afternoon" <?= $daypart === 'afternoon' ? 'selected' : '' ?>>Afternoon</option>
        <option value="evening" <?= $daypart === 'evening' ? 'selected' : '' ?>>Evening</option>
        <option value="night" <?= $daypart === 'night' ? 'selected' : '' ?>>Night</option>
    </select>
    <select name="location" class="form-select" style="width: auto;" onchange="this.form.submit()" aria-label="Store">
        <option value="0">All stores</option>
        <?php foreach ($ownerOverview['locations'] as $location): ?>
            <option value="<?= (int)$location['id'] ?>" <?= $locationId === (int)$location['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)($location['name'] ?? '')) ?></option>
        <?php endforeach; ?>
    </select>
    <select name="employee_id" class="form-select" style="width: auto;" onchange="this.form.submit()" aria-label="Employee">
        <option value="0">All employees</option>
        <?php foreach ($ownerOverview['employees'] as $employee): ?>
            <option value="<?= (int)$employee['id'] ?>" <?= $employeeId === (int)$employee['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string)($employee['full_name'] ?: $employee['username'])) ?></option>
        <?php endforeach; ?>
    </select>
    <a class="btn btn-outline-dark ms-auto" href="<?= htmlspecialchars($export) ?>"><i class="bi bi-printer"></i> Export</a>
</form>

<?php include __DIR__ . '/trade_figures.php'; ?>

<?php if ($subId === ''): ?>
<div class="alert alert-warning">An administrator adds the Flutterwave subaccount under Settings, Payments. These cards follow that subaccount.</div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <?php foreach ($settleCards as $card): ?>
    <div class="col-md-3">
        <button type="button" class="card border-0 shadow-sm h-100 w-100 text-start owner-metric" data-figure="settle" data-metric="<?= htmlspecialchars($card['key']) ?>">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold"><?= htmlspecialchars($card['title']) ?></div>
                <div class="h4 fw-bold mb-0 <?= htmlspecialchars($card['class']) ?>"><?= $money($card['amount']) ?></div>
            </div>
        </button>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($subId !== ''): ?>
<div class="card shadow-sm border-0 mb-3">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-2">
            <h5 class="fw-bold mb-0"><i class="bi bi-bank text-success"></i> Subaccount payout account</h5>
            <div class="text-md-end">
                <div class="fw-bold"><?= htmlspecialchars($subName !== '' ? $subName : $subId) ?></div>
                <div class="small text-muted"><?= htmlspecialchars(trim((string)($profile['bank'] ?? '') . ' ' . (string)($profile['masked'] ?? ''))) ?: 'Bank details appear after Flutterwave returns the subaccount.' ?></div>
            </div>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="small text-muted text-uppercase">Batch</th>
                        <th class="small text-muted text-uppercase">When</th>
                        <th class="text-end small text-muted text-uppercase">Gross</th>
                        <th class="text-end small text-muted text-uppercase">Fee</th>
                        <th class="text-end small text-muted text-uppercase">Net</th>
                        <th class="text-end small text-muted text-uppercase">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach (($settlement['batches'] ?? []) as $batch):
                    $state = strtolower((string)($batch['status'] ?? 'pending'));
                    $badge = $state === 'completed' ? 'bg-success' : ($state === 'flagged' ? 'bg-danger' : 'bg-warning text-dark');
                    $when = (string)($batch['processed_date'] ?: $batch['transaction_date'] ?: '');
                    $batchId = (string)($batch['settlement_id'] ?? '');
                ?>
                    <tr class="owner-batch" data-figure="batch" data-settlement-id="<?= htmlspecialchars($batchId) ?>">
                        <td class="font-monospace small"><?= htmlspecialchars($batchId) ?></td>
                        <td class="small"><?= $when !== '' ? htmlspecialchars(date('d M Y', strtotime($when))) : '—' ?></td>
                        <td class="text-end"><?= $money((float)$batch['gross_amount']) ?></td>
                        <td class="text-end"><?= $money((float)$batch['app_fee']) ?></td>
                        <td class="text-end fw-bold"><?= $money((float)$batch['net_amount']) ?></td>
                        <td class="text-end"><span class="badge <?= $badge ?>"><?= htmlspecialchars(ucfirst($state)) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="POST" action="index.php?page=dashboard" class="mt-3">
            <input type="hidden" name="sync_settlements" value="1">
            <button type="submit" class="btn btn-outline-primary btn-sm fw-bold" <?= empty($settlement['enabled']) ? 'disabled' : '' ?>><i class="bi bi-arrow-repeat"></i> Update from Flutterwave</button>
        </form>
    </div>
</div>
<?php endif; ?>
