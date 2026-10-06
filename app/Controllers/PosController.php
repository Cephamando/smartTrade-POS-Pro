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
            $ajax = !empty($_POST['ajax'])
                || strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0;
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
            if ($ajax) {
                $type = (string)($_SESSION['swal_type'] ?? 'success');
                $msg = (string)($_SESSION['swal_msg'] ?? '');
                $saleId = (int)($_SESSION['last_sale_id'] ?? 0);
                $stockSaleId = (int)($_SESSION['completed_sale_id'] ?? $saleId);
                unset(
                    $_SESSION['swal_type'],
                    $_SESSION['swal_msg'],
                    $_SESSION['swal_title'],
                    $_SESSION['swal_html'],
                    $_SESSION['swal_confirm'],
                    $_SESSION['swal_cancel'],
                    $_SESSION['swal_url'],
                    $_SESSION['last_sale_id'],
                    $_SESSION['completed_sale_id']
                );
                if ($type === 'error') {
                    $this->jsonExit(['status' => 'error', 'msg' => $msg !== '' ? $msg : 'Unable to complete checkout.']);
                }
                $payload = $pos->cartState($_SESSION, $locationId, $tier);
                $payload['msg'] = $msg;
                $payload['swal_type'] = 'success';
                if ($saleId > 0) {
                    $payload['sale_id'] = $saleId;
                }
                if ($stockSaleId > 0) {
                    $payload['on_hand'] = $pos->onHandForSale($stockSaleId, $locationId);
                }
                $this->jsonExit($payload);
            }
            unset($_SESSION['completed_sale_id']);
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
