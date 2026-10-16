<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Sale;
use App\Models\Shift;
use App\Models\User;
use App\Services\Flutterwave;

final class DashboardController extends Controller
{
    public function index(): void
    {
        $tab = (string)($_GET['tab'] ?? '') === 'settlements' ? 'settlements' : 'overview';
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
            $this->requireRole(['admin', 'manager', 'dev']);
            try {
                $mgr = User::verifyManager((string)($_POST['mgr_username'] ?? ''), (string)($_POST['mgr_password'] ?? ''));
                if ($mgr === null) {
                    throw new \RuntimeException('Manager authorization failed.');
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
        $payout = $tab === 'settlements' ? $flw->transferBoard() : null;
        $this->render(array_merge($kpis, [
            'dashLocId' => $dashLocId,
            'dashLocName' => $dashLocName,
            'dashTab' => $tab,
            'settlement' => $settlement,
            'payout' => $payout,
            'activeShifts' => Shift::openWithStats(),
            'tier' => defined('LICENSE_TIER') ? LICENSE_TIER : 'lite',
        ]));
    }
}
