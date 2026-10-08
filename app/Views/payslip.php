<?php $money = fn($v) => number_format((float)$v, 2); $biz = $settings['business_name'] ?? 'smartTrade POS'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip<?= count($slips) > 1 ? 's' : '' ?> · <?= htmlspecialchars($biz) ?></title>
    <link rel="stylesheet" href="vendor/bootstrap/bootstrap.min.css">
    <style>
        body { background: #eee; font-family: Arial, Helvetica, sans-serif; }
        .slip { background: #fff; width: 190mm; margin: 10mm auto; padding: 12mm 14mm; box-shadow: 0 0 6px rgba(0,0,0,.15); page-break-after: always; font-size: 13px; }
        .slip:last-child { page-break-after: auto; }
        .slip table td, .slip table th { padding: 3px 6px; }
        .amt { text-align: right; white-space: nowrap; }
        .total { border-top: 2px solid #000; font-weight: bold; }
        .net { background: #198754; color: #fff; font-size: 18px; font-weight: bold; padding: 10px 14px; border-radius: 6px; }
        @media print { body { background: #fff; } .slip { box-shadow: none; margin: 0 auto; width: auto; } .no-print { display: none !important; } }
    </style>
</head>
<body>
<div class="no-print text-center py-3"><button class="btn btn-dark fw-bold" onclick="window.print()">Print</button> <button class="btn btn-outline-secondary" onclick="window.close()">Close</button></div>
<?php foreach ($slips as $s): ?>
<div class="slip">
    <div class="d-flex justify-content-between align-items-start border-bottom pb-2 mb-3">
        <div>
            <div class="fs-4 fw-bold"><?= htmlspecialchars($biz) ?></div>
            <div class="text-muted small"><?= nl2br(htmlspecialchars((string)($settings['receipt_header'] ?? ''))) ?></div>
        </div>
        <div class="text-end">
            <div class="fs-5 fw-bold">PAYSLIP</div>
            <div class="small"><?= htmlspecialchars($s['title']) ?></div>
            <div class="small text-muted"><?= date('d M Y', strtotime($s['period_start'])) ?> – <?= date('d M Y', strtotime($s['period_end'])) ?></div>
            <?php if ($s['pay_date']): ?><div class="small text-muted">Pay date <?= date('d M Y', strtotime($s['pay_date'])) ?></div><?php endif; ?>
            <?php if ($s['run_status'] !== 'paid'): ?><span class="badge bg-warning text-dark"><?= strtoupper($s['run_status']) ?></span><?php endif; ?>
        </div>
    </div>
    <div class="row mb-3 small">
        <div class="col-6">
            <div><strong><?= htmlspecialchars($s['full_name']) ?></strong><?= $s['employee_no'] ? ' · ' . htmlspecialchars($s['employee_no']) : '' ?></div>
            <div><?= htmlspecialchars($s['position'] ?? '') ?><?= $s['department'] ? ' · ' . htmlspecialchars($s['department']) : '' ?></div>
            <div class="text-muted">NRC <?= htmlspecialchars($s['nrc'] ?: '—') ?> · TPIN <?= htmlspecialchars($s['tpin'] ?: '—') ?></div>
            <div class="text-muted">NAPSA <?= htmlspecialchars($s['napsa_no'] ?: '—') ?> · NHIMA <?= htmlspecialchars($s['nhima_no'] ?: '—') ?></div>
        </div>
        <div class="col-6 text-end text-muted">
            <div>Pay type: <?= htmlspecialchars(ucfirst($s['pay_type'])) ?><?= $s['days_worked'] !== null ? ' · ' . rtrim(rtrim((string)$s['days_worked'], '0'), '.') . ' days' : '' ?><?= $s['hours_worked'] !== null ? ' · ' . rtrim(rtrim((string)$s['hours_worked'], '0'), '.') . ' hrs' : '' ?></div>
            <div>Paid by: <?= htmlspecialchars(ucfirst($s['pay_method'])) ?><?= $s['pay_method'] === 'bank' && $s['bank_name'] ? ' · ' . htmlspecialchars($s['bank_name']) . ' ' . htmlspecialchars($s['bank_account'] ?? '') : '' ?><?= $s['pay_method'] === 'mobile' && $s['mobile_money'] ? ' · ' . htmlspecialchars($s['mobile_money']) : '' ?></div>
        </div>
    </div>
    <div class="row g-3">
        <div class="col-6">
            <table class="table table-sm mb-0">
                <thead><tr class="table-light"><th>EARNINGS</th><th class="amt">ZMW</th></tr></thead>
                <tbody>
                    <tr><td>Basic pay</td><td class="amt"><?= $money($s['basic_pay']) ?></td></tr>
                    <?php foreach ($s['lines'] as $ln): if ($ln['kind'] !== 'earning') continue; ?>
                    <tr><td><?= htmlspecialchars($ln['name']) ?><?= empty($ln['taxable']) ? ' <span class="text-muted small">(non-taxable)</span>' : '' ?></td><td class="amt"><?= $money($ln['amount']) ?></td></tr>
                    <?php endforeach; ?>
                    <?php if ((float)$s['tips'] > 0): ?><tr><td>Tips (POS)</td><td class="amt"><?= $money($s['tips']) ?></td></tr><?php endif; ?>
                    <tr class="total"><td>Gross pay</td><td class="amt"><?= $money($s['gross_pay']) ?></td></tr>
                </tbody>
            </table>
        </div>
        <div class="col-6">
            <table class="table table-sm mb-0">
                <thead><tr class="table-light"><th>DEDUCTIONS</th><th class="amt">ZMW</th></tr></thead>
                <tbody>
                    <tr><td>PAYE <span class="text-muted small">(taxable <?= $money($s['taxable_pay']) ?>)</span></td><td class="amt"><?= $money($s['paye']) ?></td></tr>
                    <tr><td>NAPSA (employee)</td><td class="amt"><?= $money($s['napsa_employee']) ?></td></tr>
                    <tr><td>NHIMA (employee)</td><td class="amt"><?= $money($s['nhima_employee']) ?></td></tr>
                    <?php foreach ($s['lines'] as $ln): if ($ln['kind'] !== 'deduction') continue; ?>
                    <tr><td><?= htmlspecialchars($ln['name']) ?></td><td class="amt"><?= $money($ln['amount']) ?></td></tr>
                    <?php endforeach; ?>
                    <tr class="total"><td>Total deductions</td><td class="amt"><?= $money($s['paye'] + $s['napsa_employee'] + $s['nhima_employee'] + $s['other_deductions']) ?></td></tr>
                </tbody>
            </table>
        </div>
    </div>
    <div class="d-flex justify-content-between align-items-center mt-3">
        <div class="small text-muted">Employer contributions: NAPSA <?= $money($s['napsa_employer']) ?> · NHIMA <?= $money($s['nhima_employer']) ?></div>
        <div class="net">NET PAY &nbsp; K<?= $money($s['net_pay']) ?></div>
    </div>
    <div class="d-flex justify-content-between mt-5 small text-muted">
        <div>Prepared by: ______________________</div>
        <div>Received by: ______________________</div>
    </div>
</div>
<?php endforeach; ?>
</body>
</html>
