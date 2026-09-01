<?php

namespace App\Controllers;

use App\Models\Shift;
use App\Models\User;

final class ShiftsController extends Controller
{
    public function index(): void
    {
        $userId = $this->userId();
        $userRole = $this->role();
        $locId = (int)$_SESSION['location_id'];
        $moneyRoles = ['cashier', 'manager', 'admin', 'dev', 'bartender'];
        $isMoneyRole = in_array($userRole, $moneyRoles, true);
        $isManager = $this->isManager();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                if (isset($_POST['start_shift'])) {
                    $mgrId = User::verifyShiftManager((string)($_POST['manager_password'] ?? ''), $locId, $userId);
                    if ($mgrId === 'SELF_ERROR') {
                        throw new \Exception('⛔ Security: You cannot verify your own shift.');
                    }
                    if (!$mgrId) {
                        throw new \Exception('Incorrect Manager Password.');
                    }
                    if (Shift::userHasOpen($userId)) {
                        throw new \Exception('You already have an open shift.');
                    }
                    $startingCash = $isMoneyRole ? ($_POST['start_amount'] ?? 0) : 0;
                    Shift::start($userId, $locId, (float)$startingCash, (int)$mgrId);
                    $this->flash('success', 'Clock-In Successful!');
                }
                if (isset($_POST['end_shift'])) {
                    $mgrId = User::verifyShiftManager((string)($_POST['manager_password'] ?? ''), $locId, $userId);
                    if ($mgrId === 'SELF_ERROR') {
                        throw new \Exception('⛔ Security: You cannot verify your own shift close.');
                    }
                    if (!$mgrId) {
                        throw new \Exception('Incorrect Manager Password.');
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
                    $this->flash('success', 'Shift Closed Successfully.');
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
        ]);
    }
}
