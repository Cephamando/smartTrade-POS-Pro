<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Report;

final class ZReadController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        ensureSaleSplitPaymentsColumn($this->pdo());

        $date = $_GET['date'] ?? date('Y-m-d');
        $timeFrom = $_GET['time_from'] ?? '00:00';
        $timeTo = $_GET['time_to'] ?? '23:59';
        $locationId = $_GET['location_id'] ?? ($_SESSION['location_id'] ?? 0);
        $startDateTime = $date . ' ' . $timeFrom . ':00';
        $endDateTime = $date . ' ' . $timeTo . ':59';

        $this->render(array_merge(Report::zRead($startDateTime, $endDateTime, $locationId), [
            'date' => $date,
            'timeFrom' => $timeFrom,
            'timeTo' => $timeTo,
            'locationId' => $locationId,
            'locations' => Location::names(),
        ]));
    }
}
