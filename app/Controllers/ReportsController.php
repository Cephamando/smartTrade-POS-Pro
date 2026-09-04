<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Report;
use App\Models\Sale;

final class ReportsController extends Controller
{
    public function index(): void
    {
        if (isset($_POST['fetch_sale_details'])) {
            try {
                $this->jsonExit(['status' => 'success', 'items' => Sale::saleItemsForModal((int)$_POST['sale_id'])]);
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage()]);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_sale'])) {
            if (!$this->isManager()) {
                $this->flash('error', 'You do not have permission to process refunds.');
                $this->redirect('reports');
            }
            try {
                $result = (new \App\Services\Refund($this->pdo()))->refundAll(
                    (int)$_POST['sale_id'],
                    $this->userId(),
                    'Full refund',
                    (string)($_POST['rfd_rsn_cd'] ?? '07')
                );
                $msg = 'Refund processed and stock restored.';
                if ($result['zra_msg'] !== '') {
                    $msg .= ' ' . $result['zra_msg'];
                }
                $this->flash('success', $msg);
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage() ?: 'Unable to process the refund.');
            }
            $this->redirect('reports');
        }

        $startDate = $_GET['start'] ?? date('Y-m-d');
        $endDate = $_GET['end'] ?? date('Y-m-d');
        $locationId = $_GET['location'] ?? '';
        $reportType = $_GET['type'] ?? 'sales';

        $reportData = [];
        if ($reportType === 'sales') {
            $reportData = Report::salesRows($startDate, $endDate, $locationId);
        } elseif ($reportType === 'itemized') {
            $reportData = Report::itemizedRows($startDate, $endDate, $locationId);
        } elseif ($reportType === 'product') {
            $reportData = Report::productRows($startDate, $endDate, $locationId);
        } elseif ($reportType === 'audit') {
            $reportData = Report::auditRows($startDate, $endDate, $locationId);
        }

        $this->render([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'locationId' => $locationId,
            'reportType' => $reportType,
            'metrics' => Report::metrics($startDate, $endDate, $locationId),
            'refundStats' => Report::refundStats($startDate, $endDate, $locationId),
            'voidStats' => Report::voidStats($startDate, $endDate, $locationId),
            'reportData' => $reportData,
            'locations' => Location::all(),
        ]);
    }
}
