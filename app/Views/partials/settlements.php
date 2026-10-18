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
?>
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
    <p class="text-muted small mb-0">Confirmed Flutterwave charges for the whole business. A charge stays pending until Flutterwave reports a completed settlement batch that includes it.</p>
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
                <div class="text-muted small text-uppercase fw-bold">Total Processed</div>
                <div class="h4 fw-bold text-primary mb-1"><?= $money((float)$settlement['processed']) ?></div>
                <div class="small text-muted"><?= (int)$settlement['count'] ?> confirmed charge<?= (int)$settlement['count'] === 1 ? '' : 's' ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-danger">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Service Fee Deducted</div>
                <div class="h4 fw-bold text-danger mb-1"><?= $money((float)$settlement['fees']) ?></div>
                <div class="small text-muted">Flutterwave fee on those charges</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-warning">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Pending Settlement to Bank</div>
                <div class="h4 fw-bold text-warning mb-1"><?= $money((float)$settlement['pending']) ?></div>
                <div class="small text-muted"><?= (int)$settlement['pending_count'] ?> not yet in a completed payout</div>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100 border-start border-4 border-success">
            <div class="card-body">
                <div class="text-muted small text-uppercase fw-bold">Settled to Bank</div>
                <div class="h4 fw-bold text-success mb-1"><?= $money((float)$settlement['settled']) ?></div>
                <div class="small text-muted"><?= (int)$settlement['settled_count'] ?> matched to a completed batch</div>
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
<div class="card shadow-sm border-0 mb-4 border-start border-4 border-danger">
    <div class="card-body">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-send text-danger"></i> Emergency transfer</h5>
                <p class="text-muted small mb-2">Sends from the payout balance to the saved bank account or mobile-money number. Flutterwave does not let this app move a chosen amount from the collection balance into the payout balance. That release happens when Flutterwave settles, and only if the Flutterwave business preference is set to settle into the payout balance.</p>
                <?php if (!empty($payout['test_mode'])): ?><span class="badge bg-warning text-dark">Sandbox keys — this will not pay a real bank</span><?php endif; ?>
            </div>
            <div class="d-flex gap-4 text-md-end">
                <div>
                    <div class="text-muted small text-uppercase fw-bold">Collection balance</div>
                    <div class="h4 fw-bold mb-0"><?= $payout['collection'] === null ? '—' : $money((float)$payout['collection']) ?></div>
                    <div class="small text-muted">Held until Flutterwave settles it</div>
                </div>
                <div>
                    <div class="text-muted small text-uppercase fw-bold">Payout balance</div>
                    <div class="h4 fw-bold mb-0"><?= $payout['balance'] === null ? '—' : $money((float)$payout['balance']) ?></div>
                    <div class="small text-muted">Available to transfer</div>
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
                    <input type="number" name="amount" class="form-control" min="1" step="0.01" <?= $payout['balance'] !== null ? 'max="' . htmlspecialchars((string)$payout['balance']) . '"' : '' ?> required <?= empty($payout['enabled']) || ($payout['balance'] !== null && (float)$payout['balance'] < 1) ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-5">
                    <label class="form-label small fw-bold text-muted">REASON</label>
                    <input type="text" name="narration" class="form-control" maxlength="100" placeholder="Emergency settlement to bank" required <?= empty($payout['enabled']) ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">DEVELOPER</label>
                    <input type="text" name="mgr_username" class="form-control" autocomplete="off" required <?= empty($payout['enabled']) ? 'disabled' : '' ?>>
                </div>
                <div class="col-md-2">
                    <label class="form-label small fw-bold text-muted">PASSWORD</label>
                    <input type="password" name="mgr_password" class="form-control" autocomplete="new-password" required <?= empty($payout['enabled']) ? 'disabled' : '' ?>>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-danger fw-bold" <?= empty($payout['enabled']) || ($payout['balance'] !== null && (float)$payout['balance'] < 1) ? 'disabled' : '' ?>>
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
                    <th class="text-end small text-muted text-uppercase">To bank</th>
                    <th class="text-end pe-4 small text-muted text-uppercase">Settlement</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($settlement['rows'])): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No confirmed Flutterwave charges yet. Totals appear after a card or mobile-money payment is verified.</td></tr>
            <?php else: foreach ($settlement['rows'] as $row):
                $processed = (float)($row['charged_amount'] ?? 0) > 0 ? (float)$row['charged_amount'] : (float)$row['amount'];
                $net = $row['net_amount'] !== null && $row['net_amount'] !== '' ? (float)$row['net_amount'] : (float)$row['amount'];
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
                    <td class="text-end fw-bold"><?= $money($net) ?></td>
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
