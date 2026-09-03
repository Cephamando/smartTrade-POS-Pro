<?php

namespace App\Controllers;

use App\Models\Inventory;

final class AuditController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-7 days'));
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $actionFilter = $_GET['action_type'] ?? '';
        try {
            $logs = Inventory::logs($startDate, $endDate, $actionFilter);
            $actionTypes = Inventory::actionTypes();
        } catch (\Throwable $e) {
            $logs = [];
            $actionTypes = [];
        }
        $this->render([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'actionFilter' => $actionFilter,
            'logs' => $logs,
            'actionTypes' => $actionTypes,
        ]);
    }
}
