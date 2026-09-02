<?php

namespace App\Controllers;

use App\Models\Shift;
use App\Models\User;

final class EndShiftActionController extends Controller
{
    public function index(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('pos');
        }

        $closingCash = (float)($_POST['closing_cash'] ?? 0);
        $varianceReason = trim((string)($_POST['variance_reason'] ?? ''));
        $mgrUsername = trim((string)($_POST['manager_username'] ?? ''));
        $mgrPassword = (string)($_POST['manager_password'] ?? '');

        try {
            $mgr = User::verifyManager($mgrUsername, $mgrPassword);
            if (!$mgr) {
                throw new \Exception('Manager credentials are invalid. The shift was not closed.');
            }
            $shift = Shift::openForUser($this->userId(), $this->locationId());
            if (!$shift) {
                throw new \Exception('No open shift was found.');
            }
            $shiftId = (int)$shift['id'];
            $expectedCash = Shift::expectedCash($shiftId, (float)$shift['starting_cash']);
            $variance = $closingCash - $expectedCash;
            $finalReason = $varianceReason !== ''
                ? $varianceReason . ' (Auth: ' . $mgrUsername . ')'
                : 'Auth: ' . $mgrUsername;
            Shift::closeFromPos($shiftId, $closingCash, $expectedCash, $variance, $finalReason);
            unset($_SESSION['cart'], $_SESSION['pos_member']);

            if (!$this->isManager()) {
                header('Location: index.php?action=logout');
                exit;
            }
            $this->flash('success', 'Shift closed. Variance: ZMW ' . number_format($variance, 2));
            $this->redirect('dashboard');
        } catch (\Throwable $e) {
            $this->flash('error', $e->getMessage());
            $this->redirect('pos');
        }
    }
}
