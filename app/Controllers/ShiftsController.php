<?php

namespace App\Controllers;

use App\Models\Shift;
use App\Models\User;
use App\Models\Setting;
use App\Services\Guide;
use App\Services\ReportNarrator;

final class ShiftsController extends Controller
{
    public function index(): void
    {
        $userId = $this->userId();
        $userRole = $this->role();
        $locId = (int)$_SESSION['location_id'];
        $moneyRoles = ['cashier', 'manager', 'admin', 'dev', 'bartender'];
        $isMoneyRole = in_array($userRole, $moneyRoles, true);
        $isManager = $this->isManager() || $this->role() === 'owner';

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['explain_shifts'])) {
            if (!$isManager) {
                $this->jsonExit(['status' => 'error', 'msg' => 'You do not have permission to explain these figures.']);
            }
            try {
                $filterDate = (string)($_POST['filter_date'] ?? date('Y-m-d'));
                $filterUser = (string)($_POST['filter_user'] ?? '');
                $rows = Shift::filteredForManager($filterDate, $filterUser, $userRole, $locId);
                $narrator = new ReportNarrator();
                $text = $narrator->explain(Setting::all(), $narrator->shiftsBrief($rows, $filterDate, $filterUser !== ''));
                $this->jsonExit(['status' => 'success', 'text' => $text]);
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage() ?: 'The note could not be written.']);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (isset($_POST['start_shift'])) {
                    $mgrId = User::verifyShiftManager((string)($_POST['manager_password'] ?? ''), $locId, $userId);
                    if ($mgrId === 'SELF_ERROR') {
                        throw new \Exception('You cannot authorize your own shift.');
                    }
                    if (!$mgrId) {
                        throw new \Exception('That approval code was not accepted.');
                    }
                    if (Shift::userHasOpen($userId)) {
                        throw new \Exception('You already have an open shift.');
                    }
                    $startingCash = $isMoneyRole ? ($_POST['start_amount'] ?? 0) : 0;
                    Shift::start($userId, $locId, (float)$startingCash, (int)$mgrId);
                    $this->flash('success', 'Shift started.');
                }
                if (isset($_POST['end_shift'])) {
                    $mgrId = User::verifyShiftManager((string)($_POST['manager_password'] ?? ''), $locId, $userId);
                    if ($mgrId === 'SELF_ERROR') {
                        throw new \Exception('You cannot authorize your own shift close.');
                    }
                    if (!$mgrId) {
                        throw new \Exception('That approval code was not accepted.');
                    }
                    $closingCash = 0;
                    $notes = null;
                    $varianceReason = null;
                    $managerCount = 0;
                    if ($isMoneyRole) {
                        $closingCash = $_POST['end_amount'] ?? 0;
                        $managerCount = $_POST['manager_count'] ?? $closingCash;
                        $varianceReason = $_POST['variance_reason'] ?? null;
                    } else {
                        $notes = $_POST['handover_notes'] ?? '';
                    }
                    Shift::close((int)$_POST['shift_id'], (float)$closingCash, (float)$managerCount, $varianceReason, $notes, (int)$mgrId);
                    $this->flash('success', 'Shift closed.');
                }
            } catch (\Throwable $e) {
                $this->flash('error', $e->getMessage());
            }
            $this->redirect('shifts');
        }

        $currentShift = Shift::currentOpen($userId);
        $calculatedSummary = [];
        if ($currentShift && $isMoneyRole) {
            $stats = Shift::cashSummary((int)$currentShift['id'], $userId, $currentShift['start_time']);
            $expected = ($currentShift['starting_cash'] + $stats['cash_sales']) - $stats['expenses'];
            $calculatedSummary = [
                'float' => $currentShift['starting_cash'],
                'cash_sales' => $stats['cash_sales'],
                'expenses' => $stats['expenses'],
                'expected' => $expected,
            ];
        }

        $filterDate = $_GET['filter_date'] ?? date('Y-m-d');
        $filterUser = $_GET['filter_user'] ?? '';

        $this->render([
            'userId' => $userId,
            'userRole' => $userRole,
            'locId' => $locId,
            'isMoneyRole' => $isMoneyRole,
            'isManager' => $isManager,
            'currentShift' => $currentShift,
            'calculatedSummary' => $calculatedSummary,
            'history' => Shift::historyForUser($userId),
            'allShifts' => $isManager ? Shift::filteredForManager($filterDate, $filterUser, $userRole, $locId) : [],
            'staffList' => $isManager ? User::staffForFilter() : [],
            'filterDate' => $filterDate,
            'filterUser' => $filterUser,
            'explainReady' => Guide::ready(Setting::all()),
        ]);
    }
}
