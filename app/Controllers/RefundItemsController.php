<?php

namespace App\Controllers;

use App\Models\Sale;
use App\Services\Refund;

final class RefundItemsController extends Controller
{
    public function index(): void
    {
        $this->requireRole(['admin', 'manager', 'dev'], 'reports');
        $saleId = (int)($_GET['sale_id'] ?? $_POST['sale_id'] ?? 0);
        $error = '';
        $sale = $saleId ? Sale::find($saleId) : null;
        $items = $saleId ? Sale::refundableItems($saleId) : [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
            $qtyByItemId = [];
            foreach (($_POST['refund_qty'] ?? []) as $itemId => $qty) {
                $qtyByItemId[(int)$itemId] = (float)$qty;
            }
            try {
                $result = (new Refund($this->pdo()))->process(
                    $saleId,
                    $qtyByItemId,
                    $this->userId(),
                    'Partial refund',
                    (string)($_POST['rfd_rsn_cd'] ?? '07')
                );
                $msg = 'Refund processed. Stock restored.';
                if ($result['zra_msg'] !== '') {
                    $msg .= ' ' . $result['zra_msg'];
                }
                $this->flash('success', $msg);
                $this->redirect('reports');
            } catch (\Throwable $e) {
                $error = $e->getMessage();
                $sale = Sale::find($saleId);
                $items = $saleId ? Sale::refundableItems($saleId) : [];
            }
        }

        $this->render([
            'saleId' => $saleId,
            'error' => $error,
            'sale' => $sale,
            'items' => $items,
            'refundReasons' => \ZraVsdcClient::refundReasonCodes(),
            'selectedReason' => \ZraVsdcClient::normalizeRefundReasonCode((string)($_POST['rfd_rsn_cd'] ?? '07')),
        ]);
    }
}
