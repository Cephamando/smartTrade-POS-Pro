<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Sale;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Services\Flutterwave;
use App\Services\Guide;
use App\Services\ReportNarrator;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $tab = (string)($_GET['tab'] ?? '') === 'settlements' ? 'settlements' : 'overview';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['explain_trend'])) {
            if (!in_array($this->role(), ['admin', 'manager', 'dev'], true)) {
                $this->jsonExit(['status' => 'error', 'msg' => 'You do not have permission to explain this chart.']);
            }
            try {
                $ids = [];
                foreach ((array)($_POST['items'] ?? []) as $id) {
                    $ids[] = (int)$id;
                }
                $trend = Sale::salesTrend(
                    $this->locationId(),
                    (string)($_POST['range'] ?? 'week'),
                    (string)($_POST['start'] ?? ''),
                    (string)($_POST['end'] ?? ''),
                    $ids
                );
                $narrator = new ReportNarrator();
                $text = $narrator->explain(Setting::all(), $narrator->trendBrief($trend, $this->locationId()));
                $this->jsonExit(['status' => 'success', 'text' => $text]);
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() !== '' ? $e->getMessage() : 'The note could not be written.']);
            }
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['sales_trend'])) {
            $ids = [];
            foreach ((array)($_POST['items'] ?? []) as $id) {
                $ids[] = (int)$id;
            }
            $this->jsonExit(Sale::salesTrend(
                $this->locationId(),
                (string)($_POST['range'] ?? 'week'),
                (string)($_POST['start'] ?? ''),
                (string)($_POST['end'] ?? ''),
                $ids
            ));
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['sync_settlements'])) {
            $this->requireRole(['admin', 'manager', 'dev']);
            try {
                $result = (new Flutterwave($this->pdo()))->syncSettlements();
                $this->flash($result['status'] === 'ok' ? 'success' : 'error', $result['msg']);
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'Could not check Flutterwave settlements.');
            }
            $this->redirect('dashboard', ['tab' => 'settlements']);
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['emergency_transfer'])) {
            $this->requireRole(['dev']);
            try {
                $mgr = User::verifyDeveloper((string)($_POST['mgr_username'] ?? ''), (string)($_POST['mgr_password'] ?? ''));
                if ($mgr === null) {
                    throw new \RuntimeException('Developer authorization failed.');
                }
                $result = (new Flutterwave($this->pdo()))->sendEmergencyTransfer(
                    (float)($_POST['amount'] ?? 0),
                    (string)($_POST['narration'] ?? ''),
                    (int)$mgr['id']
                );
                $this->flash('success', $result['msg']);
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage() !== '' ? $e->getMessage() : 'The emergency transfer was not sent.');
            }
            $this->redirect('dashboard', ['tab' => 'settlements']);
        }

        $dashLocId = $this->locationId();
        $kpis = Sale::dashboardKpis($dashLocId);
        $dashLocName = 'All Locations';
        if ($dashLocId > 0) {
            $dashLocName = Location::nameById($dashLocId);
        }
        $flw = new Flutterwave($this->pdo());
        $settlement = $tab === 'settlements' ? $flw->settlementBoard() : null;
        $payout = ($tab === 'settlements' && strtolower((string)($_SESSION['role'] ?? '')) === 'dev') ? $flw->transferBoard() : null;
        $this->render(array_merge($kpis, [
            'dashLocId' => $dashLocId,
            'dashLocName' => $dashLocName,
            'dashTab' => $tab,
            'settlement' => $settlement,
            'payout' => $payout,
            'explainReady' => Guide::ready(Setting::all()),
            'activeShifts' => Shift::openWithStats(),
            'tier' => defined('LICENSE_TIER') ? LICENSE_TIER : 'lite',
        ]));
    }
}
