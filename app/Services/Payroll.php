<?php

namespace App\Services;

use App\Models\Setting;
use PDO;

/**
 * Payroll engine with Zambian statutory deductions.
 *
 * Defaults (2024/2025 ZRA PAYE bands, monthly):
 *   0 – 5,100            0%
 *   5,100.01 – 7,100    20%
 *   7,100.01 – 9,200    30%
 *   above 9,200         37%
 * NAPSA: 5% employee + 5% employer on gross, each capped (default K1,708.20 / month).
 * NHIMA: 1% employee + 1% employer on basic pay.
 *
 * Everything is configurable under settings (payroll_* keys) so the admin can
 * update bands when the budget changes without a code release.
 */
final class Payroll
{
    public const STATUSES = ['draft', 'approved', 'paid'];

    public function __construct(private PDO $pdo, private ?array $settings = null)
    {
        $this->settings ??= Setting::all();
    }

    /** @return array<int, array{upto: ?float, rate: float}> */
    public function payeBands(): array
    {
        $json = (string)($this->settings['payroll_paye_bands'] ?? '');
        $bands = json_decode($json, true);
        if (!is_array($bands) || !$bands) {
            $bands = [
                ['upto' => 5100, 'rate' => 0],
                ['upto' => 7100, 'rate' => 20],
                ['upto' => 9200, 'rate' => 30],
                ['upto' => null, 'rate' => 37],
            ];
        }
        return array_values(array_map(fn($b) => ['upto' => isset($b['upto']) && $b['upto'] !== '' && $b['upto'] !== null ? (float)$b['upto'] : null, 'rate' => (float)($b['rate'] ?? 0)], $bands));
    }

    public function config(): array
    {
        return [
            'paye_bands' => $this->payeBands(),
            'napsa_rate' => (float)($this->settings['payroll_napsa_rate'] ?? 5),
            'napsa_cap' => (float)($this->settings['payroll_napsa_cap'] ?? 1708.20),
            'nhima_rate' => (float)($this->settings['payroll_nhima_rate'] ?? 1),
            'working_days' => (int)($this->settings['payroll_working_days'] ?? 26),
            'include_tips' => ($this->settings['payroll_include_tips'] ?? '1') === '1',
            'tips_taxable' => ($this->settings['payroll_tips_taxable'] ?? '0') === '1',
        ];
    }

    public function paye(float $taxable): float
    {
        $tax = 0.0;
        $lower = 0.0;
        foreach ($this->payeBands() as $band) {
            $upper = $band['upto'];
            if ($upper !== null && $taxable <= $lower) {
                break;
            }
            $slice = $upper === null ? max(0, $taxable - $lower) : max(0, min($taxable, $upper) - $lower);
            $tax += $slice * $band['rate'] / 100;
            if ($upper === null) {
                break;
            }
            $lower = $upper;
        }
        return round(max(0, $tax), 2);
    }

    /**
     * Compute one payslip.
     *
     * @param array $employee  employees row
     * @param array $components [{name, kind, amount, is_taxable}] resolved per employee for this run
     * @param array $input      {days_worked?, hours_worked?, tips?, overrides?: {basic?}}
     */
    public function compute(array $employee, array $components, array $input = []): array
    {
        $cfg = $this->config();
        $payType = $employee['pay_type'] ?? 'monthly';
        $days = isset($input['days_worked']) && $input['days_worked'] !== '' ? (float)$input['days_worked'] : null;
        $hours = isset($input['hours_worked']) && $input['hours_worked'] !== '' ? (float)$input['hours_worked'] : null;

        if (isset($input['basic']) && $input['basic'] !== '') {
            $basic = (float)$input['basic'];
        } elseif ($payType === 'daily') {
            $basic = (float)$employee['daily_rate'] * ($days ?? $cfg['working_days']);
        } elseif ($payType === 'hourly') {
            $basic = (float)$employee['hourly_rate'] * ($hours ?? 0);
        } else {
            $basic = (float)$employee['basic_salary'];
            if ($days !== null && $cfg['working_days'] > 0 && $days < $cfg['working_days']) {
                $basic = $basic * $days / $cfg['working_days']; // pro-rata for part month
            }
        }
        $basic = round($basic, 2);

        $allowances = 0.0;
        $taxableAllow = 0.0;
        $deductions = 0.0;
        $lines = [];
        foreach ($components as $c) {
            $amt = (float)($c['amount'] ?? 0);
            if (($c['calc'] ?? 'fixed') === 'percent') {
                $amt = round($basic * $amt / 100, 2);
            }
            if ($amt == 0.0) {
                continue;
            }
            if (($c['kind'] ?? 'earning') === 'deduction') {
                $deductions += $amt;
                $lines[] = ['name' => $c['name'], 'kind' => 'deduction', 'amount' => round($amt, 2)];
            } else {
                $allowances += $amt;
                if (!empty($c['is_taxable'])) {
                    $taxableAllow += $amt;
                }
                $lines[] = ['name' => $c['name'], 'kind' => 'earning', 'amount' => round($amt, 2), 'taxable' => !empty($c['is_taxable'])];
            }
        }
        $tips = $cfg['include_tips'] ? round((float)($input['tips'] ?? 0), 2) : 0.0;

        $gross = round($basic + $allowances + $tips, 2);
        $taxable = $basic + $taxableAllow + ($cfg['tips_taxable'] ? $tips : 0);

        $napsaEmp = $napsaEr = 0.0;
        if (empty($employee['napsa_exempt'])) {
            $napsaEmp = min(round($gross * $cfg['napsa_rate'] / 100, 2), $cfg['napsa_cap']);
            $napsaEr = $napsaEmp;
        }
        $nhimaEmp = $nhimaEr = 0.0;
        if (empty($employee['nhima_exempt'])) {
            $nhimaEmp = round($basic * $cfg['nhima_rate'] / 100, 2);
            $nhimaEr = $nhimaEmp;
        }
        // NAPSA employee contribution is deductible before PAYE.
        $taxable = max(0, round($taxable - $napsaEmp, 2));
        $paye = empty($employee['paye_exempt']) ? $this->paye($taxable) : 0.0;

        $net = round($gross - $paye - $napsaEmp - $nhimaEmp - $deductions, 2);

        return [
            'days_worked' => $days,
            'hours_worked' => $hours,
            'basic_pay' => $basic,
            'allowances' => round($allowances, 2),
            'tips' => $tips,
            'gross_pay' => $gross,
            'taxable_pay' => $taxable,
            'paye' => $paye,
            'napsa_employee' => $napsaEmp,
            'napsa_employer' => $napsaEr,
            'nhima_employee' => $nhimaEmp,
            'nhima_employer' => $nhimaEr,
            'other_deductions' => round($deductions, 2),
            'net_pay' => $net,
            'breakdown' => $lines,
        ];
    }

    // ------------------------------------------------------------------ data access

    public function employees(bool $activeOnly = false): array
    {
        $sql = 'SELECT e.*, l.name AS location_name, u.username
                FROM employees e
                LEFT JOIN locations l ON l.id = e.location_id
                LEFT JOIN users u ON u.id = e.user_id';
        if ($activeOnly) {
            $sql .= ' WHERE e.is_active = 1';
        }
        return $this->pdo->query($sql . ' ORDER BY e.is_active DESC, e.full_name')->fetchAll();
    }

    public function employee(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM employees WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $c = $this->pdo->prepare('SELECT component_id, amount FROM employee_components WHERE employee_id = ?');
            $c->execute([$id]);
            $row['components'] = array_column($c->fetchAll(), 'amount', 'component_id');
        }
        return $row ?: null;
    }

    public function saveEmployee(?int $id, array $d, array $componentAmounts): int
    {
        $name = trim((string)($d['full_name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Employee name is required.');
        }
        $cols = [
            'user_id' => !empty($d['user_id']) ? (int)$d['user_id'] : null,
            'employee_no' => trim((string)($d['employee_no'] ?? '')) ?: null,
            'full_name' => $name,
            'nrc' => trim((string)($d['nrc'] ?? '')) ?: null,
            'tpin' => trim((string)($d['tpin'] ?? '')) ?: null,
            'napsa_no' => trim((string)($d['napsa_no'] ?? '')) ?: null,
            'nhima_no' => trim((string)($d['nhima_no'] ?? '')) ?: null,
            'phone' => trim((string)($d['phone'] ?? '')) ?: null,
            'email' => trim((string)($d['email'] ?? '')) ?: null,
            'position' => trim((string)($d['position'] ?? '')) ?: null,
            'department' => trim((string)($d['department'] ?? '')) ?: null,
            'location_id' => !empty($d['location_id']) ? (int)$d['location_id'] : null,
            'hire_date' => !empty($d['hire_date']) ? $d['hire_date'] : null,
            'termination_date' => !empty($d['termination_date']) ? $d['termination_date'] : null,
            'pay_type' => in_array($d['pay_type'] ?? '', ['monthly', 'daily', 'hourly'], true) ? $d['pay_type'] : 'monthly',
            'basic_salary' => (float)($d['basic_salary'] ?? 0),
            'daily_rate' => (float)($d['daily_rate'] ?? 0),
            'hourly_rate' => (float)($d['hourly_rate'] ?? 0),
            'pay_method' => in_array($d['pay_method'] ?? '', ['bank', 'cash', 'mobile'], true) ? $d['pay_method'] : 'bank',
            'bank_name' => trim((string)($d['bank_name'] ?? '')) ?: null,
            'bank_account' => trim((string)($d['bank_account'] ?? '')) ?: null,
            'mobile_money' => trim((string)($d['mobile_money'] ?? '')) ?: null,
            'napsa_exempt' => !empty($d['napsa_exempt']) ? 1 : 0,
            'nhima_exempt' => !empty($d['nhima_exempt']) ? 1 : 0,
            'paye_exempt' => !empty($d['paye_exempt']) ? 1 : 0,
            'is_active' => isset($d['is_active']) ? (int)(bool)$d['is_active'] : 1,
        ];
        if ($id) {
            $set = implode(', ', array_map(fn($k) => "$k = ?", array_keys($cols)));
            $this->pdo->prepare("UPDATE employees SET $set WHERE id = ?")->execute([...array_values($cols), $id]);
        } else {
            $this->pdo->prepare('INSERT INTO employees (' . implode(', ', array_keys($cols)) . ') VALUES (' . rtrim(str_repeat('?, ', count($cols)), ', ') . ')')
                ->execute(array_values($cols));
            $id = (int)$this->pdo->lastInsertId();
        }
        $this->pdo->prepare('DELETE FROM employee_components WHERE employee_id = ?')->execute([$id]);
        $ins = $this->pdo->prepare('INSERT INTO employee_components (employee_id, component_id, amount) VALUES (?, ?, ?)');
        foreach ($componentAmounts as $cid => $amount) {
            if ((float)$amount != 0.0) {
                $ins->execute([$id, (int)$cid, (float)$amount]);
            }
        }
        return $id;
    }

    public function components(bool $activeOnly = false): array
    {
        return $this->pdo->query('SELECT * FROM payroll_components' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY kind DESC, sort_order, name')->fetchAll();
    }

    public function saveComponent(?int $id, array $d): int
    {
        $name = trim((string)($d['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('Component name is required.');
        }
        $vals = [
            $name,
            ($d['kind'] ?? 'earning') === 'deduction' ? 'deduction' : 'earning',
            ($d['calc'] ?? 'fixed') === 'percent' ? 'percent' : 'fixed',
            (float)($d['amount'] ?? 0),
            !empty($d['is_taxable']) ? 1 : 0,
            isset($d['is_active']) ? (int)(bool)$d['is_active'] : 1,
            (int)($d['sort_order'] ?? 0),
        ];
        if ($id) {
            $this->pdo->prepare('UPDATE payroll_components SET name=?, kind=?, calc=?, amount=?, is_taxable=?, is_active=?, sort_order=? WHERE id=?')->execute([...$vals, $id]);
            return $id;
        }
        $this->pdo->prepare('INSERT INTO payroll_components (name, kind, calc, amount, is_taxable, is_active, sort_order) VALUES (?,?,?,?,?,?,?)')->execute($vals);
        return (int)$this->pdo->lastInsertId();
    }

    public function deleteComponent(int $id): void
    {
        $this->pdo->prepare('DELETE FROM employee_components WHERE component_id = ?')->execute([$id]);
        $this->pdo->prepare('DELETE FROM payroll_components WHERE id = ?')->execute([$id]);
    }

    public function runs(): array
    {
        return $this->pdo->query(
            'SELECT r.*, u.username AS created_by_name,
                    (SELECT COUNT(*) FROM payslips p WHERE p.run_id = r.id) AS slip_count
             FROM payroll_runs r LEFT JOIN users u ON u.id = r.created_by ORDER BY r.period_start DESC, r.id DESC'
        )->fetchAll();
    }

    public function run(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM payroll_runs WHERE id = ?');
        $stmt->execute([$id]);
        $run = $stmt->fetch();
        if (!$run) {
            return null;
        }
        $slips = $this->pdo->prepare(
            'SELECT p.*, e.full_name, e.employee_no, e.position, e.pay_type, e.pay_method, e.bank_name, e.bank_account, e.mobile_money, e.user_id
             FROM payslips p JOIN employees e ON e.id = p.employee_id WHERE p.run_id = ? ORDER BY e.full_name'
        );
        $slips->execute([$id]);
        $run['slips'] = array_map(function ($s) {
            $s['lines'] = json_decode((string)$s['breakdown'], true) ?: [];
            return $s;
        }, $slips->fetchAll());
        return $run;
    }

    public function payslip(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT p.*, e.*, p.id AS id, e.id AS employee_id, r.title, r.period_start, r.period_end, r.pay_date, r.status AS run_status
             FROM payslips p JOIN employees e ON e.id = p.employee_id JOIN payroll_runs r ON r.id = p.run_id WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $row['lines'] = json_decode((string)$row['breakdown'], true) ?: [];
        }
        return $row ?: null;
    }

    /** Tips earned by linked POS users in the period (receipt tips + tips after payment). */
    public function tipsForPeriod(string $start, string $end): array
    {
        $out = [];
        $s = $this->pdo->prepare(
            "SELECT user_id, COALESCE(SUM(tip_amount),0) AS t FROM sales
             WHERE payment_status = 'paid' AND created_at BETWEEN ? AND ? GROUP BY user_id"
        );
        $s->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
        foreach ($s->fetchAll() as $r) {
            $out[(int)$r['user_id']] = ($out[(int)$r['user_id']] ?? 0) + (float)$r['t'];
        }
        try {
            $t = $this->pdo->prepare('SELECT staff_user_id, COALESCE(SUM(amount),0) AS t FROM sale_tips WHERE created_at BETWEEN ? AND ? GROUP BY staff_user_id');
            $t->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
            foreach ($t->fetchAll() as $r) {
                $out[(int)$r['staff_user_id']] = ($out[(int)$r['staff_user_id']] ?? 0) + (float)$r['t'];
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }

    /**
     * Create a draft run and compute a payslip for every active employee.
     * @param array $inputs employee_id => {days_worked, hours_worked, tips, basic}
     */
    public function createRun(string $title, string $start, string $end, ?string $payDate, int $userId, string $notes, array $inputs = []): int
    {
        if (strtotime($start) === false || strtotime($end) === false || $start > $end) {
            throw new \InvalidArgumentException('Choose a valid pay period.');
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->prepare('INSERT INTO payroll_runs (title, period_start, period_end, pay_date, status, created_by, notes) VALUES (?,?,?,?,"draft",?,?)')
                ->execute([$title !== '' ? $title : ('Payroll ' . date('M Y', strtotime($start))), $start, $end, $payDate ?: null, $userId, $notes ?: null]);
            $runId = (int)$this->pdo->lastInsertId();
            $this->recalculate($runId, $inputs, true);
            $this->pdo->commit();
            return $runId;
        } catch (\Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /** Recompute all slips of a draft run (optionally adding employees missing from it). */
    public function recalculate(int $runId, array $inputs = [], bool $addMissing = false): void
    {
        $run = $this->run($runId);
        if (!$run) {
            throw new \RuntimeException('Pay run not found.');
        }
        if ($run['status'] !== 'draft') {
            throw new \RuntimeException('Only draft pay runs can be recalculated.');
        }
        $tips = $this->config()['include_tips'] ? $this->tipsForPeriod($run['period_start'], $run['period_end']) : [];
        $components = [];
        foreach ($this->components(true) as $c) {
            $components[(int)$c['id']] = $c;
        }
        $existing = [];
        foreach ($run['slips'] as $s) {
            $existing[(int)$s['employee_id']] = $s;
        }
        $employees = $addMissing ? $this->employees(true) : array_filter($this->employees(), fn($e) => isset($existing[(int)$e['id']]));
        $upsert = $this->pdo->prepare(
            'INSERT INTO payslips (run_id, employee_id, days_worked, hours_worked, basic_pay, allowances, tips, gross_pay, taxable_pay, paye, napsa_employee, napsa_employer, nhima_employee, nhima_employer, other_deductions, net_pay, breakdown)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE days_worked=VALUES(days_worked), hours_worked=VALUES(hours_worked), basic_pay=VALUES(basic_pay), allowances=VALUES(allowances), tips=VALUES(tips),
               gross_pay=VALUES(gross_pay), taxable_pay=VALUES(taxable_pay), paye=VALUES(paye), napsa_employee=VALUES(napsa_employee), napsa_employer=VALUES(napsa_employer),
               nhima_employee=VALUES(nhima_employee), nhima_employer=VALUES(nhima_employer), other_deductions=VALUES(other_deductions), net_pay=VALUES(net_pay), breakdown=VALUES(breakdown)'
        );
        $empComp = $this->pdo->prepare('SELECT component_id, amount FROM employee_components WHERE employee_id = ?');
        foreach ($employees as $e) {
            $eid = (int)$e['id'];
            $in = $inputs[$eid] ?? [];
            $prev = $existing[$eid] ?? null;
            if ($prev && !isset($in['days_worked'])) {
                $in['days_worked'] = $prev['days_worked'];
            }
            if ($prev && !isset($in['hours_worked'])) {
                $in['hours_worked'] = $prev['hours_worked'];
            }
            if (!isset($in['tips']) || $in['tips'] === '') {
                $in['tips'] = $e['user_id'] ? ($tips[(int)$e['user_id']] ?? 0) : 0;
            }
            $empComp->execute([$eid]);
            $resolved = [];
            foreach ($empComp->fetchAll() as $ec) {
                $c = $components[(int)$ec['component_id']] ?? null;
                if ($c) {
                    $c['amount'] = (float)$ec['amount'];
                    $resolved[] = $c;
                }
            }
            // one-off adjustments for this run (e.g. overtime, advance)
            foreach ((array)($in['extra'] ?? []) as $cid => $amt) {
                $c = $components[(int)$cid] ?? null;
                if ($c && (float)$amt != 0.0) {
                    $c['amount'] = (float)$amt;
                    $c['calc'] = 'fixed';
                    $resolved[] = $c;
                }
            }
            $calc = $this->compute($e, $resolved, $in);
            $upsert->execute([
                $runId, $eid, $calc['days_worked'], $calc['hours_worked'], $calc['basic_pay'], $calc['allowances'], $calc['tips'], $calc['gross_pay'], $calc['taxable_pay'],
                $calc['paye'], $calc['napsa_employee'], $calc['napsa_employer'], $calc['nhima_employee'], $calc['nhima_employer'], $calc['other_deductions'], $calc['net_pay'],
                json_encode($calc['breakdown']),
            ]);
        }
        $this->refreshTotals($runId);
    }

    public function removeSlip(int $runId, int $employeeId): void
    {
        $this->pdo->prepare('DELETE p FROM payslips p JOIN payroll_runs r ON r.id = p.run_id WHERE p.run_id = ? AND p.employee_id = ? AND r.status = "draft"')->execute([$runId, $employeeId]);
        $this->refreshTotals($runId);
    }

    private function refreshTotals(int $runId): void
    {
        $this->pdo->prepare(
            'UPDATE payroll_runs r SET
                total_gross = (SELECT COALESCE(SUM(gross_pay),0) FROM payslips WHERE run_id = r.id),
                total_net = (SELECT COALESCE(SUM(net_pay),0) FROM payslips WHERE run_id = r.id),
                total_paye = (SELECT COALESCE(SUM(paye),0) FROM payslips WHERE run_id = r.id),
                total_napsa = (SELECT COALESCE(SUM(napsa_employee + napsa_employer),0) FROM payslips WHERE run_id = r.id),
                total_nhima = (SELECT COALESCE(SUM(nhima_employee + nhima_employer),0) FROM payslips WHERE run_id = r.id)
             WHERE r.id = ?'
        )->execute([$runId]);
    }

    public function setStatus(int $runId, string $status, int $userId): void
    {
        $run = $this->run($runId);
        if (!$run) {
            throw new \RuntimeException('Pay run not found.');
        }
        if ($status === 'approved' && $run['status'] === 'draft') {
            if (!$run['slips']) {
                throw new \RuntimeException('Add at least one employee before approving.');
            }
            $this->pdo->prepare('UPDATE payroll_runs SET status = "approved", approved_by = ?, approved_at = NOW() WHERE id = ?')->execute([$userId, $runId]);
        } elseif ($status === 'paid' && $run['status'] === 'approved') {
            $this->pdo->prepare('UPDATE payroll_runs SET status = "paid", paid_at = NOW() WHERE id = ?')->execute([$runId]);
            $this->pdo->prepare('UPDATE payslips SET paid_at = NOW() WHERE run_id = ?')->execute([$runId]);
            $this->bookExpense($run);
        } elseif ($status === 'draft' && $run['status'] === 'approved') {
            $this->pdo->prepare('UPDATE payroll_runs SET status = "draft", approved_by = NULL, approved_at = NULL WHERE id = ?')->execute([$runId]);
        } else {
            throw new \RuntimeException('That status change is not allowed from "' . $run['status'] . '".');
        }
    }

    public function deleteRun(int $runId): void
    {
        $this->pdo->prepare('DELETE FROM payslips WHERE run_id = ? AND run_id IN (SELECT id FROM (SELECT id FROM payroll_runs WHERE status = "draft") x)')->execute([$runId]);
        $this->pdo->prepare('DELETE FROM payroll_runs WHERE id = ? AND status = "draft"')->execute([$runId]);
    }

    /** Record net salaries as an expense so the P&L in reports picks them up. */
    private function bookExpense(array $run): void
    {
        try {
            $cols = $this->pdo->query("SHOW COLUMNS FROM expenses")->fetchAll(PDO::FETCH_COLUMN);
            if (!in_array('amount', $cols, true)) {
                return;
            }
            $data = [
                'amount' => (float)$run['total_net'],
                'created_at' => date('Y-m-d H:i:s'),
            ];
            foreach (['reason', 'description'] as $textCol) {
                if (in_array($textCol, $cols, true)) {
                    $data[$textCol] = 'Payroll: ' . $run['title'] . ' (net salaries)';
                    break;
                }
            }
            if (in_array('shift_id', $cols, true)) {
                $data['shift_id'] = null;
            }
            if (in_array('category', $cols, true)) {
                $data['category'] = 'Salaries';
            }
            if (in_array('user_id', $cols, true)) {
                $data['user_id'] = (int)($_SESSION['user_id'] ?? $run['created_by']);
            }
            if (in_array('location_id', $cols, true)) {
                $data['location_id'] = (int)($_SESSION['pos_location_id'] ?? $_SESSION['location_id'] ?? 1);
            }
            if (in_array('expense_date', $cols, true)) {
                $data['expense_date'] = $run['pay_date'] ?: date('Y-m-d');
            }
            $this->pdo->prepare('INSERT INTO expenses (' . implode(', ', array_keys($data)) . ') VALUES (' . rtrim(str_repeat('?, ', count($data)), ', ') . ')')
                ->execute(array_values($data));
        } catch (\Throwable $e) {
            // expenses table shape differs — payroll still marked paid
        }
    }
}
