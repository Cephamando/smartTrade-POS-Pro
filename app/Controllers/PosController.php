<?php

namespace App\Controllers;

use App\Core\Database;
use App\Services\Checkout;
use App\Services\Pos;

final class PosController extends Controller
{
    public function index(): void
    {
        $pdo = Database::connection();
        $userId = $this->userId();
        $locationId = $this->locationId();
        $role = $this->role();
        $tier = defined('LICENSE_TIER') ? LICENSE_TIER : 'lite';
        \App\Models\Inventory::ensureReasonColumn();
        $pos = new Pos($pdo);

        if (isset($_POST['checkout'])) {
            $stmt = $pdo->prepare("SELECT id FROM shifts WHERE user_id = ? AND location_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
            $stmt->execute([$userId, $locationId]);
            $shiftId = $stmt->fetchColumn();
            if ($shiftId) {
                try {
                    (new Checkout($pdo))->complete($_POST, $_SESSION, (int)$shiftId, $locationId, $userId);
                } catch (\Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $msg = trim($e->getMessage());
                    $this->flash('error', $msg !== '' ? $msg : 'Unable to complete checkout.');
                }
            } else {
                $this->flash('error', 'Open a shift before checkout.');
            }
            $this->redirect('pos');
        }

        $handled = $pos->handlePost($_POST, $_SESSION, $userId, $locationId, $role, $tier);
        if (is_array($handled)) {
            if (isset($handled['json'])) {
                $this->jsonExit($handled['json']);
            }
            if (!empty($handled['redirect'])) {
                $this->redirect('pos');
            }
        }

        $this->render($pos->viewData($_SESSION, $userId, $locationId, $role, $tier));
    }
}
