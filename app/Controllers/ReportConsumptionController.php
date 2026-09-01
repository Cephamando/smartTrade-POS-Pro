<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Report;

final class ReportConsumptionController extends Controller
{
    public function index(): void
    {
        if (!in_array($this->role(), ['admin', 'manager', 'dev', 'head_chef'], true)) {
            $this->redirect('dashboard');
        }
        $startDate = $_GET['start_date'] ?? date('Y-m-d');
        $endDate = $_GET['end_date'] ?? date('Y-m-d');
        $locId = isset($_GET['location_id']) ? (int)$_GET['location_id'] : 0;
        $this->render([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'locId' => $locId,
            'locations' => Location::names(),
            'consumption' => Report::consumption($startDate, $endDate, $locId),
        ]);
    }
}
