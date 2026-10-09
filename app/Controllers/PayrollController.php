<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Setting;
use App\Models\User;
use App\Services\Payroll;

final class PayrollController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        $payroll = new Payroll($this->pdo());
        $tab = (string)($_GET['tab'] ?? 'runs');

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $tab = (string)($_POST['tab'] ?? $tab);
            $q = ['tab' => $tab];
            try {
                if (($_POST['bulk_action'] ?? '') === 'activate' || ($_POST['bulk_action'] ?? '') === 'deactivate') {
                    $active = $_POST['bulk_action'] === 'activate' ? 1 : 0;
                    $stmt = $this->pdo()->prepare('UPDATE employees SET is_active = ? WHERE id = ?');
                    foreach ($this->postedIds() as $id) {
                        $stmt->execute([$active, $id]);
                    }
                    $this->flash('success', 'Selected employees ' . ($active ? 'activated.' : 'deactivated.'));
                } elseif (($_POST['bulk_action'] ?? '') === 'delete_components') {
                    foreach ($this->postedIds() as $id) {
                        $payroll->deleteComponent($id);
                    }
                    $this->flash('success', 'Selected pay components removed.');
                } elseif (($_POST['bulk_action'] ?? '') === 'remove_slips') {
                    $runId = (int)($_POST['run_id'] ?? 0);
                    foreach ($this->postedIds() as $id) {
                        $payroll->removeSlip($runId, $id);
                    }
                    $this->flash('success', 'Selected employees removed from this run.');
                    $q = ['tab' => 'runs', 'run_id' => $runId];
                } elseif (isset($_POST['save_employee'])) {
                    $id = $payroll->saveEmployee(!empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : null, $_POST, (array)($_POST['comp'] ?? []));
                    $this->flash('success', 'Employee #' . $id . ' saved.');
                } elseif (isset($_POST['toggle_employee'])) {
                    $this->pdo()->prepare('UPDATE employees SET is_active = 1 - is_active WHERE id = ?')->execute([(int)$_POST['toggle_employee']]);
                    $this->flash('success', 'Employee status updated.');
                } elseif (isset($_POST['save_component'])) {
                    $payroll->saveComponent(!empty($_POST['component_id']) ? (int)$_POST['component_id'] : null, $_POST);
                    $this->flash('success', 'Pay component saved.');
                } elseif (isset($_POST['delete_component'])) {
                    $payroll->deleteComponent((int)$_POST['delete_component']);
                    $this->flash('success', 'Pay component removed.');
                } elseif (isset($_POST['save_rules'])) {
                    $this->saveRules();
                    $this->flash('success', 'Statutory rules saved. Draft pay runs will use them when recalculated.');
                } elseif (isset($_POST['create_run'])) {
                    $runId = $payroll->createRun(
                        trim((string)($_POST['title'] ?? '')),
                        (string)($_POST['period_start'] ?? ''),
                        (string)($_POST['period_end'] ?? ''),
                        (string)($_POST['pay_date'] ?? '') ?: null,
                        $this->userId(),
                        trim((string)($_POST['notes'] ?? ''))
                    );
                    $this->flash('success', 'Draft pay run created. Review each payslip, then approve.');
                    $q = ['tab' => 'runs', 'run_id' => $runId];
                } elseif (isset($_POST['recalculate_run'])) {
                    $runId = (int)$_POST['recalculate_run'];
                    $inputs = [];
                    foreach ((array)($_POST['slip'] ?? []) as $eid => $row) {
                        $inputs[(int)$eid] = [
                            'days_worked' => $row['days_worked'] ?? null,
                            'hours_worked' => $row['hours_worked'] ?? null,
                            'tips' => $row['tips'] ?? '',
                            'basic' => $row['basic'] ?? '',
                            'extra' => (array)($row['extra'] ?? []),
                        ];
                    }
                    $payroll->recalculate($runId, $inputs, isset($_POST['add_missing']));
                    $this->flash('success', 'Payslips recalculated.');
                    $q = ['tab' => 'runs', 'run_id' => $runId];
                } elseif (isset($_POST['remove_slip'])) {
                    $runId = (int)$_POST['run_id'];
                    $payroll->removeSlip($runId, (int)$_POST['remove_slip']);
                    $this->flash('success', 'Employee removed from this run.');
                    $q = ['tab' => 'runs', 'run_id' => $runId];
                } elseif (isset($_POST['set_status'])) {
                    $runId = (int)$_POST['run_id'];
                    $payroll->setStatus($runId, (string)$_POST['set_status'], $this->userId());
                    $this->flash('success', 'Pay run marked ' . $_POST['set_status'] . '.');
                    $q = ['tab' => 'runs', 'run_id' => $runId];
                } elseif (isset($_POST['delete_run'])) {
                    $payroll->deleteRun((int)$_POST['delete_run']);
                    $this->flash('success', 'Draft pay run deleted.');
                    $q = ['tab' => 'runs'];
                }
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage());
            }
            $this->redirect('payroll', $q);
        }

        if (!in_array($tab, ['runs', 'employees', 'components', 'rules'], true)) {
            $tab = 'runs';
        }
        $this->render([
            'tab' => $tab,
            'payroll' => $payroll,
            'config' => $payroll->config(),
            'employees' => $payroll->employees(),
            'editEmployee' => !empty($_GET['employee_id']) ? $payroll->employee((int)$_GET['employee_id']) : null,
            'components' => $payroll->components(),
            'runs' => $payroll->runs(),
            'run' => !empty($_GET['run_id']) ? $payroll->run((int)$_GET['run_id']) : null,
            'locations' => Location::all(),
            'staffUsers' => User::activeStaff(),
            'settings' => Setting::all(),
        ]);
    }

    private function saveRules(): void
    {
        $bands = [];
        $uptos = (array)($_POST['band_upto'] ?? []);
        $rates = (array)($_POST['band_rate'] ?? []);
        foreach ($rates as $i => $rate) {
            $upto = trim((string)($uptos[$i] ?? ''));
            $bands[] = ['upto' => $upto === '' ? null : (float)$upto, 'rate' => (float)$rate];
        }
        usort($bands, fn($a, $b) => ($a['upto'] ?? INF) <=> ($b['upto'] ?? INF));
        Setting::save([
            'payroll_paye_bands' => json_encode($bands),
            'payroll_napsa_rate' => (string)(float)($_POST['napsa_rate'] ?? 5),
            'payroll_napsa_cap' => (string)(float)($_POST['napsa_cap'] ?? 1708.20),
            'payroll_nhima_rate' => (string)(float)($_POST['nhima_rate'] ?? 1),
            'payroll_working_days' => (string)max(1, (int)($_POST['working_days'] ?? 26)),
            'payroll_include_tips' => isset($_POST['include_tips']) ? '1' : '0',
            'payroll_tips_taxable' => isset($_POST['tips_taxable']) ? '1' : '0',
        ]);
    }
}
