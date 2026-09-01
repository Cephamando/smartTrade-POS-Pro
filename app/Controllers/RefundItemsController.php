<?php

namespace App\Controllers;

use App\Models\Sale;

final class RefundItemsController extends Controller
{
    public function index(): void
    {
        $saleId = (int)($_GET['sale_id'] ?? 0);
        $error = '';
        $sale = Sale::find($saleId);
        $items = $saleId ? Sale::refundableItems($saleId) : [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_refund'])) {
            $refunds = $_POST['refund_qty'] ?? [];
            $totalRefunded = 0;
            $pdo = $this->pdo();
            $pdo->beginTransaction();
            try {
                foreach ($refunds as $itemId => $qty) {
                    $qty = (int)$qty;
                    if ($qty <= 0) {
                        continue;
                    }
                    $stmt = $pdo->prepare('SELECT * FROM sale_items WHERE id = ?');
                    $stmt->execute([$itemId]);
                    $item = $stmt->fetch();
                    if ($qty > $item['quantity']) {
                        throw new \Exception('Cannot refund more than sold qty for ' . $item['name']);
                    }
                    if ($qty == $item['quantity']) {
                        $pdo->prepare("UPDATE sale_items SET status = 'refunded' WHERE id = ?")->execute([$itemId]);
                    } else {
                        $newQty = $item['quantity'] - $qty;
                        $pdo->prepare('UPDATE sale_items SET quantity = ? WHERE id = ?')->execute([$newQty, $itemId]);
                        $pdo->prepare(
                            "INSERT INTO sale_items (sale_id, product_id, quantity, price, status, fulfillment_status) VALUES (?, ?, ?, ?, 'refunded', 'collected')"
                        )->execute([$saleId, $item['product_id'], $qty, $item['price']]);
                    }
                    $pdo->prepare('UPDATE inventory SET quantity = quantity + ? WHERE product_id = ? AND location_id = ?')
                        ->execute([$qty, $item['product_id'], $sale['location_id']]);
                    $totalRefunded += $qty * $item['price'];
                }
                $pdo->prepare('UPDATE sales SET subtotal = subtotal - ?, final_total = final_total - ? WHERE id = ?')
                    ->execute([$totalRefunded, $totalRefunded, $saleId]);
                $pdo->prepare("INSERT INTO refunds (sale_id, manager_id, amount_refunded, reason, created_at) VALUES (?, ?, ?, 'Partial Refund', NOW())")
                    ->execute([$saleId, $this->userId(), $totalRefunded]);
                $pdo->commit();
                $this->flash('success', 'Refund processed successfully.');
                $this->redirect('reports');
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $error = 'Error: ' . $e->getMessage();
            }
        }

        $this->render([
            'saleId' => $saleId,
            'error' => $error,
            'success' => '',
            'sale' => $sale,
            'items' => $items,
        ]);
    }
}
