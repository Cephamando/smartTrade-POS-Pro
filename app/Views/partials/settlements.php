<?php
/** @var array{processed: float, fees: float, pending: float, settled: float, count: int, pending_count: int, settled_count: int, rows: list<array<string, mixed>>, enabled: bool} $settlement */
$settlement = $settlement ?? [
    'processed' => 0.0,
    'fees' => 0.0,
    'pending' => 0.0,
    'settled' => 0.0,
    'count' => 0,
    'pending_count' => 0,
    'settled_count' => 0,
    'rows' => [],
    'enabled' => false,
];
$money = static function (float $amount): string {
    return 'ZMW ' . number_format($amount, 2);
};
$subId = (string)($settlement['subaccount_id'] ?? '');
$profile = is_array($settlement['profile'] ?? null) ? $settlement['profile'] : null;
$subName = trim((string)($profile['name'] ?? ''));
?>
<?php if ($subId === ''): ?>
<div class="alert alert-warning">Add the Flutterwave subaccount ID under <a href="index.php?page=settings&amp;tab=payments">Settings → Payments</a>. This tab follows that subaccount, not the main Flutterwave wallet.</div>
<?php endif; ?>
<div class="d-flex justify-content-end mb-3">
    <form method="POST" action="index.php?page=dashboard&amp;tab=settlements" class="flex-shrink-0">
        <input type="hidden" name="sync_settlements" value="1">
        <button type="submit" class="btn btn-primary fw-bold" <?= empty($settlement['enabled']) ? 'disabled title="Add the Flutterwave keys under Settings, Payments"' : '' ?>>
            <i class="bi bi-arrow-repeat"></i> Update from Flutterwave
        </button>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-primary">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Due to the subaccount</div>
                <div class="h4 fw-bold text-primary mb-0" id="settleDue"><?= $money((float)$settlement['processed']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Fees on subaccount payouts</div>
                <div class="h4 fw-bold text-danger mb-0" id="settleFees"><?= $money((float)$settlement['fees']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Waiting on Flutterwave</div>
                <div class="h4 fw-bold text-warning mb-0" id="settleWaiting"><?= $money((float)$settlement['pending']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Paid to the subaccount</div>
                <div class="h4 fw-bold text-success mb-0" id="settlePaid"><?= $money((float)$settlement['settled']) ?></div>
            </div>
        </div>
    </div>
</div>

<?php
$payout = $payout ?? [
    'enabled' => false,
    'test_mode' => false,
    'balance' => null,
    'collection' => null,
    'balance_error' => null,
    'destination' => ['ready' => false, 'bank_name' => '', 'name' => '', 'masked' => ''],
    'rows' => [],
];
$dest = is_array($payout['destination'] ?? null) ? $payout['destination'] : [];
?>
<?php if ($subId !== ''): ?>
<div class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
                <h5 class="fw-bold mb-0"><i class="bi bi-bank text-success"></i> Subaccount payout account</h5>
            </div>
            <div class="text-md-end">
                <div class="fw-bold"><?= htmlspecialchars($subName !== '' ? $subName : $subId) ?></div>
                <div class="small text-muted">
                    <?= htmlspecialchars(trim((string)($profile['bank'] ?? '') . ' ' . (string)($profile['masked'] ?? ''))) ?: 'Bank details appear after Flutterwave returns the subaccount.' ?>
                </div>
            </div>
        </div>
        <div class="table-responsive mt-3">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="small text-muted text-uppercase">Batch</th>
                        <th class="small text-muted text-uppercase">When</th>
                        <th class="text-end small text-muted text-uppercase">Gross</th>
                        <th class="text-end small text-muted text-uppercase d-none d-sm-table-cell">Fee</th>
                        <th class="text-end small text-muted text-uppercase">Net</th>
                        <th class="text-end small text-muted text-uppercase">Status</th>
                    </tr>
                </thead>
                <tbody id="settleBatches">
                <?php foreach (($settlement['batches'] ?? []) as $batch):
                    $state = strtolower((string)($batch['status'] ?? 'pending'));
                    $badge = $state === 'completed' ? 'bg-success' : ($state === 'flagged' ? 'bg-danger' : 'bg-warning text-dark');
                    $when = (string)($batch['processed_date'] ?: $batch['transaction_date'] ?: '');
                ?>
                    <tr>
                        <td class="font-monospace small"><?= htmlspecialchars((string)$batch['settlement_id']) ?></td>
                        <td class="small"><?= $when !== '' ? htmlspecialchars(date('d M Y', strtotime($when))) : '—' ?></td>
                        <td class="text-end"><?= $money((float)$batch['gross_amount']) ?></td>
                        <td class="text-end d-none d-sm-table-cell"><?= $money((float)$batch['app_fee']) ?></td>
                        <td class="text-end fw-bold"><?= $money((float)$batch['net_amount']) ?></td>
                        <td class="text-end"><span class="badge <?= $badge ?>"><?= htmlspecialchars(ucfirst($state)) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (strtolower((string)($_SESSION['role'] ?? '')) === 'dev'): ?>
<div class="card shadow-sm border-0 mb-4 border-start border-4 border-danger">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
                <h5 class="fw-bold mb-0"><i class="bi bi-send text-danger"></i> Subaccount transfer</h5>
                <?php if (!empty($payout['test_mode'])): ?><span class="badge bg-warning text-dark mt-2">Sandbox keys — this will not pay a real bank</span><?php endif; ?>
            </div>
            <div class="d-flex gap-4 text-md-end">
                <div>
                    <div class="text-muted small text-uppercase fw-bold">Collection balance</div>
                    <div class="h4 fw-bold mb-0" id="settleCollection"><?= $payout['collection'] === null ? '—' : $money((float)$payout['collection']) ?></div>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-bold">Payout balance</div>
                    <div class="h4 fw-bold mb-0" id="settlePayout"><?= $payout['balance'] === null ? '—' : $money((float)$payout['balance']) ?></div>
                </div>
            </div>
        </div>
        <?php if (!empty($payout['balance_error'])): ?><div class="small text-danger mt-2"><?= htmlspecialchars((string)$payout['balance_error']) ?></div><?php endif; ?>
        <?php if (strtolower((string)($_SESSION['role'] ?? '')) !== 'dev'): ?>
            <div class="alert alert-light border small mb-0 mt-3">A developer sends an emergency transfer and keeps the payout account.</div>
        <?php elseif (empty($dest['ready'])): ?>
            <div class="alert alert-warning small mb-0 mt-3">Save a bank account or a mobile-money number under <a href="index.php?page=settings&amp;tab=payments">Settings → Payments</a> before an emergency transfer can be sent.</div>
        <?php else: ?>
            <div class="small mt-3 mb-3">Pays <strong><?= htmlspecialchars((string)($dest['name'] ?? '')) ?></strong>, <?= htmlspecialchars((string)(($dest['bank_name'] ?? '') !== '' ? $dest['bank_name'] : 'saved bank')) ?> <?= htmlspecialchars((string)($dest['masked'] ?? '')) ?>.</div>
            <form method="POST" action="index.php?page=dashboard&amp;tab=settlements" class="row g-3">
                <input type="hidden" name="emergency_transfer" value="1">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-muted">AMOUNT (ZMW)</label>
                    <input type="number" name="amount" class="form-control" min="1" step="0.01" <?= $payout['balance'] !== null ? 'max="' . htmlspecialchars((string)$payout['balance']) . '"' : '' ?> required <?= empty($payout['enabled']) || $payout['balance'] === null || (float)$payout['balance'] < 1 ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted">REASON</label>
                    <input type="text" name="narration" class="form-control" maxlength="100" placeholder="Emergency settlement to bank" required <?= empty($payout['enabled']) || $payout['balance'] === null || (float)$payout['balance'] < 1 ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">DEVELOPER</label>
                    <input type="text" name="mgr_username" class="form-control" autocomplete="off" required <?= empty($payout['enabled']) || $payout['balance'] === null || (float)$payout['balance'] < 1 ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">PASSWORD</label>
                    <input type="password" name="mgr_password" class="form-control" autocomplete="new-password" required <?= empty($payout['enabled']) || $payout['balance'] === null || (float)$payout['balance'] < 1 ? 'disabled' : '' ?>>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-danger fw-bold" <?= empty($payout['enabled']) || $payout['balance'] === null || (float)$payout['balance'] < 1 ? 'disabled' : '' ?>>
                        <i class="bi bi-send"></i> Send emergency transfer
                    </button>
                </div>
            </form>
        <?php endif; ?>
        <?php if (!empty($payout['rows'])): ?>
        <div class="table-responsive mt-4">
            <table class="table table-sm align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="small text-muted text-uppercase">When</th>
                        <th class="text-end small text-muted text-uppercase">Amount</th>
                        <th class="text-end small text-muted text-uppercase d-none d-sm-table-cell">Fee</th>
                        <th class="small text-muted text-uppercase">To</th>
                        <th class="small text-muted text-uppercase d-none d-md-table-cell">Reason</th>
                        <th class="text-end small text-muted text-uppercase">Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($payout['rows'] as $transfer):
                    $state = (string)($transfer['status'] ?? 'pending');
                    $badge = $state === 'successful' ? 'bg-success' : ($state === 'failed' ? 'bg-danger' : 'bg-warning text-dark');
                ?>
                    <tr>
                        <td class="small"><?= htmlspecialchars(date('d M Y H:i', strtotime((string)$transfer['created_at']))) ?></td>
                        <td class="text-end fw-bold"><?= $money((float)$transfer['amount']) ?></td>
                        <td class="text-end d-none d-sm-table-cell"><?= $transfer['fee'] === null || $transfer['fee'] === '' ? '—' : $money((float)$transfer['fee']) ?></td>
                        <td class="small"><?= htmlspecialchars(trim((string)($transfer['bank_name'] ?? '') . ' ' . (string)($transfer['account_masked'] ?? ''))) ?></td>
                        <td class="small d-none d-md-table-cell"><?= htmlspecialchars((string)$transfer['narration']) ?></td>
                        <td class="text-end">
                            <span class="badge <?= $badge ?>"><?= htmlspecialchars(ucfirst($state)) ?></span>
                            <?php if (!empty($transfer['complete_message']) && $state !== 'successful'): ?><div class="small text-muted"><?= htmlspecialchars((string)$transfer['complete_message']) ?></div><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm border-0">
    <div class="card-header bg-dark text-white fw-bold py-3">Recent confirmed charges</div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="ps-4 small text-muted text-uppercase">When</th>
                    <th class="small text-muted text-uppercase">Reference</th>
                    <th class="small text-muted text-uppercase d-none d-md-table-cell">Method</th>
                    <th class="text-end small text-muted text-uppercase">Processed</th>
                    <th class="text-end small text-muted text-uppercase d-none d-sm-table-cell">Fee</th>
                    <th class="text-end small text-muted text-uppercase">To subaccount</th>
                    <th class="text-end pe-4 small text-muted text-uppercase">Settlement</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($settlement['rows'])): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No confirmed Flutterwave charges yet. Totals appear after a card or mobile-money payment is verified.</td></tr>
            <?php else: foreach ($settlement['rows'] as $row):
                $processed = (float)($row['charged_amount'] ?? 0) > 0 ? (float)$row['charged_amount'] : (float)$row['amount'];
                $net = $row['subaccount_amount'] !== null && $row['subaccount_amount'] !== '' ? (float)$row['subaccount_amount'] : null;
                $settled = ($row['settlement_status'] ?? '') === 'settled';
                $method = trim((string)($row['network'] ?? ''));
                if ($method === '') {
                    $method = (string)($row['payment_type'] ?? '');
                }
            ?>
                <tr>
                    <td class="ps-4 small"><?= htmlspecialchars(date('d M Y H:i', strtotime((string)$row['created_at']))) ?></td>
                    <td>
                        <span class="font-monospace small"><?= htmlspecialchars((string)$row['tx_ref']) ?></span>
                        <?php if (!empty($row['sale_id'])): ?>
                            <div><a class="small" href="index.php?page=receipt&amp;sale_id=<?= (int)$row['sale_id'] ?>">Receipt #<?= (int)$row['sale_id'] ?></a></div>
                        <?php endif; ?>
                    </td>
                    <td class="d-none d-md-table-cell small"><?= htmlspecialchars($method !== '' ? $method : 'Flutterwave') ?></td>
                    <td class="text-end"><?= $money($processed) ?></td>
                    <td class="text-end d-none d-sm-table-cell"><?= $row['app_fee'] === null || $row['app_fee'] === '' ? '—' : $money((float)$row['app_fee']) ?></td>
                    <td class="text-end fw-bold"><?= $net === null ? '—' : $money($net) ?></td>
                    <td class="text-end pe-4">
                        <?php if ($settled): ?>
                            <span class="badge bg-success">Settled</span>
                            <?php if (!empty($row['settled_at'])): ?><div class="small text-muted"><?= htmlspecialchars(date('d M Y', strtotime((string)$row['settled_at']))) ?></div><?php endif; ?>
                        <?php else: ?>
                            <span class="badge bg-warning text-dark">Pending</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
(function () {
    var due = document.getElementById('settleDue');
    if (!due) return;
    function money(amount) {
        var value = Number(amount);
        if (!isFinite(value)) return '—';
        return 'ZMW ' + value.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function text(id, value) {
        var node = document.getElementById(id);
        if (node) node.textContent = value;
    }
    function badge(status) {
        var state = String(status || 'pending').toLowerCase();
        var cls = state === 'completed' ? 'bg-success' : (state === 'flagged' ? 'bg-danger' : 'bg-warning text-dark');
        var label = state.replace(/-/g, ' ');
        label = label.charAt(0).toUpperCase() + label.slice(1);
        var span = document.createElement('span');
        span.className = 'badge ' + cls;
        span.textContent = label;
        return span;
    }
    function cell(textValue, className) {
        var td = document.createElement('td');
        if (className) td.className = className;
        td.textContent = textValue;
        return td;
    }
    function paint(data) {
        text('settleDue', money(data.due));
        text('settleFees', money(data.fees));
        text('settleWaiting', money(data.waiting));
        text('settlePaid', money(data.paid));
        if (Object.prototype.hasOwnProperty.call(data, 'collection')) {
            text('settleCollection', data.collection === null || data.collection === '' ? '—' : money(data.collection));
        }
        if (Object.prototype.hasOwnProperty.call(data, 'payout')) {
            text('settlePayout', data.payout === null || data.payout === '' ? '—' : money(data.payout));
        }
        var body = document.getElementById('settleBatches');
        if (!body || !Array.isArray(data.batches)) return;
        body.replaceChildren();
        data.batches.forEach(function (batch) {
            var tr = document.createElement('tr');
            tr.appendChild(cell(batch.id || '', 'font-monospace small'));
            var when = batch.when ? new Date(String(batch.when).replace(' ', 'T')) : null;
            var whenText = when && !isNaN(when.getTime())
                ? when.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' })
                : '—';
            tr.appendChild(cell(whenText, 'small'));
            tr.appendChild(cell(money(batch.gross), 'text-end'));
            tr.appendChild(cell(money(batch.fee), 'text-end d-none d-sm-table-cell'));
            tr.appendChild(cell(money(batch.net), 'text-end fw-bold'));
            var status = document.createElement('td');
            status.className = 'text-end';
            status.appendChild(badge(batch.status));
            tr.appendChild(status);
            body.appendChild(tr);
        });
    }
    function refresh() {
        fetch('index.php?page=dashboard&tab=settlements&live=1', { headers: { 'Accept': 'application/json' }, credentials: 'same-origin' })
            .then(function (res) { return res.ok ? res.json() : null; })
            .then(function (data) { if (data && data.status === 'ok') paint(data); })
            .catch(function () {});
    }
    setInterval(refresh, 30000);
})();
</script>
