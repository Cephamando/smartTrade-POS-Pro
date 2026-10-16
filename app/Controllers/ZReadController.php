<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Report;
use App\Models\Setting;
use App\Services\Guide;
use App\Services\ReportNarrator;

final class ZReadController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev']);
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['explain_zread'])) {
            try {
                $narrator = new ReportNarrator();
                $text = $narrator->explain(Setting::all(), $narrator->zReadBrief($_POST));
                $this->jsonExit(['status' => 'success', 'text' => $text]);
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() ?: 'The note could not be written.']);
            }
        }
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
            'explainReady' => Guide::ready(Setting::all()),
        ]));
    }
}
