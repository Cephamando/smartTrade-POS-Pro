<?php

namespace App\Controllers;

use App\Models\Report;
use App\Models\Shift;

final class PrintShiftController extends Controller
{
    public function index(): void
    {
        $shiftId = (int)($_GET['shift_id'] ?? 0);
        $shift = Shift::withStaff($shiftId);
        if (!$shift) {
            die('Shift not found.');
        }
        $data = Report::printShift($shiftId);
        $data['expectedCash'] = (float)$shift['starting_cash'] + (float)$data['cashSales'] - (float)$data['totalExpenses']
            + (float)($data['postTips']['cash'] ?? 0);
        $this->render(array_merge($data, [
            'shiftId' => $shiftId,
            'shift' => $shift,
        ]));
    }
}
