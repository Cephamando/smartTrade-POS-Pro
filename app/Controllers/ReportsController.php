<?php

namespace App\Controllers;

use App\Models\Location;
use App\Models\Report;
use App\Models\Sale;

final class ReportsController extends Controller
{
    public function index(): void
    {
        if (isset($_POST['fetch_sale_details'])) {
            try {
                $this->jsonExit(['status' => 'success', 'items' => Sale::saleItemsForModal((int)$_POST['sale_id'])]);
            } catch (\Throwable $e) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage()]);
            }
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_sale'])) {
            if (!$this->isManager()) {
                $this->flash('error', 'You do not have permission to process refunds.');
                $this->redirect('reports');
            }
            $saleId = (int)$_POST['sale_id'];
            $sale = Sale::find($saleId);
            if (!$sale) {
                $this->flash('error', 'Sale not found.');
            } elseif (strtolower($sale['payment_status']) === 'refunded') {
                $this->flash('warning', 'This sale has already been refunded.');
            } elseif (strtolower($sale['payment_status']) !== 'paid') {
                $this->flash('error', 'Unpaid sales cannot be refunded.');
            } else {
                $pdo = $this->pdo();
                $pdo->beginTransaction();
                try {
                    $pdo->prepare("UPDATE sales SET payment_status = 'refunded' WHERE id = ?")->execute([$saleId]);
                    $itemsStmt = $pdo->prepare('SELECT * FROM sale_items WHERE sale_id = ?');
                    $itemsStmt->execute([$saleId]);
                    foreach ($itemsStmt->fetchAll() as $item) {
                        $typeStmt = $pdo->prepare('SELECT type FROM products WHERE id = ?');
                        $typeStmt->execute([$item['product_id']]);
                        if ($typeStmt->fetchColumn() === 'service') {
                            continue;
                        }
                        $pdo->prepare('UPDATE inventory SET quantity = quantity + ? WHERE product_id = ? AND location_id = ?')
                            ->execute([$item['quantity'], $item['product_id'], $sale['location_id']]);
                        $qtyStmt = $pdo->prepare('SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?');
                        $qtyStmt->execute([$item['product_id'], $sale['location_id']]);
                        $newQty = $qtyStmt->fetchColumn();
                        $pdo->prepare(
                            "INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id, created_at)
                             VALUES (?, ?, ?, ?, ?, 'refund', ?, NOW())"
                        )->execute([$item['product_id'], $sale['location_id'], $this->userId(), $item['quantity'], $newQty, $saleId]);
                    }
                    $pdo->commit();
                    $this->flash('success', 'Refund processed and stock restored.');
                } catch (\Throwable $e) {
                    $pdo->rollBack();
                    $this->flash('error', 'Unable to process the refund.');
                }
            }
            $this->redirect('reports');
        }

        $startDate = $_GET['start'] ?? date('Y-m-d');
        $endDate = $_GET['end'] ?? date('Y-m-d');
        $locationId = $_GET['location'] ?? '';
        $reportType = $_GET['type'] ?? 'sales';

        $reportData = [];
        if ($reportType === 'sales') {
            $reportData = Report::salesRows($startDate, $endDate, $locationId);
        } elseif ($reportType === 'itemized') {
            $reportData = Report::itemizedRows($startDate, $endDate, $locationId);
        } elseif ($reportType === 'product') {
            $reportData = Report::productRows($startDate, $endDate, $locationId);
        } elseif ($reportType === 'audit') {
            $reportData = Report::auditRows($startDate, $endDate, $locationId);
        }

        $this->render([
            'startDate' => $startDate,
            'endDate' => $endDate,
            'locationId' => $locationId,
            'reportType' => $reportType,
            'metrics' => Report::metrics($startDate, $endDate, $locationId),
            'refundStats' => Report::refundStats($startDate, $endDate, $locationId),
            'voidStats' => Report::voidStats($startDate, $endDate, $locationId),
            'reportData' => $reportData,
            'locations' => Location::all(),
        ]);
    }
}
