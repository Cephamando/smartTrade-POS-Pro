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
        if (Database::trainingAborted() && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            $msg = 'Training stopped because the practice data was missing. The sale was not recorded. Start the lesson again.';
            $this->flash('error', $msg);
            $ajax = !empty($_POST['ajax'])
                || strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0;
            if ($ajax) {
                $this->jsonExit(['status' => 'error', 'msg' => $msg]);
            }
            $this->redirect('pos');
        }
        if (isset($_GET['card_bill'])) {
            $this->cardBill();
        }
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
                    if ($msg === '' || str_contains($msg, 'SQLSTATE')) {
                        $msg = 'The sale was not recorded.';
                    }
                    $this->flash('error', $msg);
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

    private function cardBill(): never
    {
        $cart = is_array($_SESSION['cart'] ?? null) ? $_SESSION['cart'] : [];
        $lines = [];
        $sum = 0.0;
        foreach ($cart as $item) {
            if (!is_array($item)) {
                continue;
            }
            $qty = (float)($item['qty'] ?? 0);
            $total = round($qty * (float)($item['price'] ?? 0), 2);
            $sum += $total;
            $lines[] = [
                'name' => (string)($item['name'] ?? 'Item'),
                'qty' => $qty,
                'total' => $total,
            ];
        }
        $sum = round($sum, 2);
        $due = round((float)($_GET['due'] ?? $sum), 2);
        $settings = [];
        $stmt = $this->pdo()->query(
            "SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('business_name', 'receipt_header', 'receipt_footer')"
        );
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
        $bizName = (string)($settings['business_name'] ?? 'Shop');
        $receiptHeader = (string)($settings['receipt_header'] ?? '');
        $receiptFooter = (string)($settings['receipt_footer'] ?? '');
        $cashier = (string)($_SESSION['username'] ?? '');
        $customer = (string)($_SESSION['current_customer'] ?? 'Walk-in');
        $location = (string)($_SESSION['location_name'] ?? '');
        $training = !empty($_SESSION['training_mode']);
        require APP_PATH . '/Views/pos_card_bill.php';
        exit;
    }
}
