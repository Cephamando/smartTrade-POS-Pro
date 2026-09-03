<?php

namespace App\Models;

final class Grv extends Model
{
    public static function process(int $vendorId, int $locId, int $userId, string $ref, array $items): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $totalCost = 0;
            foreach ($items as $item) {
                $calcQty = isset($item['qty']) ? (float)$item['qty'] : 0;
                $calcCost = isset($item['cost']) ? (float)$item['cost'] : 0;
                if (!empty($item['product_id']) && $calcQty > 0) {
                    $totalCost += ($calcQty * $calcCost);
                }
            }

            $stmtGrv = $pdo->prepare(
                'INSERT INTO grvs (vendor_id, location_id, received_by, total_cost, reference_no, created_at) VALUES (?, ?, ?, ?, ?, NOW())'
            );
            $stmtGrv->execute([$vendorId, $locId, $userId, $totalCost, $ref]);
            $grvId = (int)$pdo->lastInsertId();

            $stmtGrvItem = $pdo->prepare('INSERT INTO grv_items (grv_id, product_id, quantity, unit_cost) VALUES (?, ?, ?, ?)');
            $stmtUpsert = $pdo->prepare(
                'INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
            );
            $stmtCost = $pdo->prepare('UPDATE products SET cost_price = ? WHERE id = ?');
            $stmtLog = $pdo->prepare(
                "INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id, created_at)
                 VALUES (?, ?, ?, ?, ?, 'restock', ?, NOW())"
            );
            $stmtGetQty = $pdo->prepare('SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?');

            foreach ($items as $item) {
                $qty = isset($item['qty']) ? (float)$item['qty'] : 0;
                $cost = isset($item['cost']) ? (float)$item['cost'] : 0;
                if (empty($item['product_id']) || $qty <= 0) {
                    continue;
                }
                $pid = $item['product_id'];
                $stmtGrvItem->execute([$grvId, $pid, $qty, $cost]);
                $stmtUpsert->execute([$pid, $locId, $qty]);
                if ($cost > 0) {
                    $stmtCost->execute([$cost, $pid]);
                }
                $stmtGetQty->execute([$pid, $locId]);
                $newQty = $stmtGetQty->fetchColumn();
                $stmtLog->execute([$pid, $locId, $userId, $qty, $newQty, $grvId]);
            }

            $pdo->commit();
            return $grvId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
