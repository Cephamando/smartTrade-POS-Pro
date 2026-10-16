<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Report;
use App\Models\Setting;
use App\Services\Guide;
use App\Services\ReportNarrator;

final class ReportConsumptionController extends Controller
{
    public function index(): void
    {
        if (!in_array($this->role(), ['admin', 'manager', 'dev', 'head_chef'], true)) {
            $this->redirect('dashboard');
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['explain_consumption'])) {
            try {
                $startDate = (string)($_POST['start_date'] ?? '');
                $endDate = (string)($_POST['end_date'] ?? '');
                $locId = (int)($_POST['location_id'] ?? 0);
                $rows = Report::consumption($startDate, $endDate, $locId);
                $store = '';
                if ($locId > 0) {
                    $loc = Location::find($locId);
                    $store = (string)($loc['name'] ?? '');
                }
                $narrator = new ReportNarrator();
                $text = $narrator->explain(Setting::all(), $narrator->consumptionBrief($rows, $startDate, $endDate, $store));
                $this->jsonExit(['status' => 'success', 'text' => $text]);
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() ?: 'The note could not be written.']);
            }
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
            'explainReady' => Guide::ready(Setting::all()),
        ]);
    }
}
