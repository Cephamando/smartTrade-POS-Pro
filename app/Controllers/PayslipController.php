<?php

namespace App\Controllers;

use App\Models\Setting;
use App\Services\Payroll;

final class PayslipController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev', 'owner']);
        $payroll = new Payroll($this->pdo());
        $slips = [];
        if (!empty($_GET['run_id'])) {
            $run = $payroll->run((int)$_GET['run_id']);
            foreach ($run['slips'] ?? [] as $s) {
                $slip = $payroll->payslip((int)$s['id']);
                if ($slip) {
                    $slips[] = $slip;
                }
            }
        } elseif (!empty($_GET['id'])) {
            $slip = $payroll->payslip((int)$_GET['id']);
            if ($slip) {
                $slips[] = $slip;
            }
        }
        if (!$slips) {
            $this->flash('error', 'Payslip not found.');
            $this->redirect('payroll');
        }
        $this->render(['slips' => $slips, 'settings' => Setting::all()]);
    }
}
