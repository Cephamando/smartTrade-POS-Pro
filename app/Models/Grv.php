<?php

namespace App\Models;

final class Grv extends Model
{
    /**
     * Normalise a posted receiving line.
     *
     * Accepted keys (all optional except product_id and a quantity):
     *   qty        units received, in the product's stock unit
     *   cost       price per unit
     *   unit       unit label captured at receipt (kg, ltr, each, ...)
     *   pack_size  units per pack (crate of 24 → 24)
     *   pack_qty   number of packs received
     *   pack_cost  price per pack
     * Missing values are derived: qty = pack_qty × pack_size, cost = pack_cost ÷ pack_size,
     * line_total = qty × cost.
     *
     * @return array{product_id:int, qty:float, cost:float, unit:?string, pack_size:float, pack_qty:?float, pack_cost:?float, line_total:float}|null
     */
    public static function normaliseLine(array $item): ?array
    {
        $pid = (int)($item['product_id'] ?? 0);
        if ($pid <= 0) {
            return null;
        }
        $packSize = isset($item['pack_size']) && (float)$item['pack_size'] > 0 ? (float)$item['pack_size'] : 1.0;
        $packQty = isset($item['pack_qty']) && $item['pack_qty'] !== '' ? (float)$item['pack_qty'] : null;
        $packCost = isset($item['pack_cost']) && $item['pack_cost'] !== '' ? (float)$item['pack_cost'] : null;
        $qty = isset($item['qty']) && $item['qty'] !== '' ? (float)$item['qty'] : (float)($item['quantity'] ?? 0);
        if ($qty <= 0 && $packQty !== null && $packQty > 0) {
            $qty = $packQty * $packSize;
        }
        $cost = isset($item['cost']) && $item['cost'] !== '' ? (float)$item['cost'] : (float)($item['unit_cost'] ?? 0);
        if ($cost <= 0 && $packCost !== null && $packCost > 0) {
            $cost = $packCost / $packSize;
        }
        if ($qty <= 0) {
            return null;
        }
        $unit = isset($item['unit']) ? trim((string)$item['unit']) : '';
        return [
            'product_id' => $pid,
            'qty' => round($qty, 4),
            'cost' => round($cost, 4),
            'unit' => $unit !== '' ? substr($unit, 0, 20) : null,
            'pack_size' => $packSize,
            'pack_qty' => $packQty,
            'pack_cost' => $packCost !== null ? round($packCost, 4) : null,
            'line_total' => round($qty * $cost, 2),
        ];
    }

    public static function process(int $vendorId, int $locId, int $userId, string $ref, array $items, string $notes = ''): int
    {
        $pdo = self::db();
        $lines = [];
        foreach ($items as $item) {
            $line = self::normaliseLine((array)$item);
            if ($line) {
                $lines[] = $line;
            }
        }
        if (!$lines) {
            throw new \Exception('Add at least one received line with a quantity.');
        }
        $totalCost = array_sum(array_column($lines, 'line_total'));

        $pdo->beginTransaction();
        try {
            $stmtGrv = $pdo->prepare(
                'INSERT INTO grvs (vendor_id, location_id, received_by, total_cost, subtotal, reference_no, notes, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
            );
            $stmtGrv->execute([$vendorId, $locId, $userId, $totalCost, $totalCost, $ref, $notes !== '' ? substr($notes, 0, 500) : null]);
            $grvId = (int)$pdo->lastInsertId();

            $stmtGrvItem = $pdo->prepare(
                'INSERT INTO grv_items (grv_id, product_id, quantity, unit_cost, unit, pack_size, pack_qty, pack_cost, line_total) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmtUpsert = $pdo->prepare(
                'INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
            );
            $stmtCost = $pdo->prepare('UPDATE products SET cost_price = ? WHERE id = ?');
            $stmtLog = $pdo->prepare(
                "INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id, created_at)
                 VALUES (?, ?, ?, ?, ?, 'restock', ?, NOW())"
            );
            $stmtGetQty = $pdo->prepare('SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?');

            foreach ($lines as $l) {
                $stmtGrvItem->execute([$grvId, $l['product_id'], $l['qty'], $l['cost'], $l['unit'], $l['pack_size'], $l['pack_qty'], $l['pack_cost'], $l['line_total']]);
                $stmtUpsert->execute([$l['product_id'], $locId, $l['qty']]);
                if ($l['cost'] > 0) {
                    $stmtCost->execute([$l['cost'], $l['product_id']]);
                }
                $stmtGetQty->execute([$l['product_id'], $locId]);
                $newQty = $stmtGetQty->fetchColumn();
                $stmtLog->execute([$l['product_id'], $locId, $userId, $l['qty'], $newQty, $grvId]);
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

    /** Recent GRVs with line counts for the history list. */
    public static function recent(int $limit = 30): array
    {
        $stmt = self::db()->prepare(
            "SELECT g.id, g.reference_no, g.total_cost, g.created_at, g.purchase_order_id,
                    v.name AS vendor_name, l.name AS location_name, u.username,
                    (SELECT COUNT(*) FROM grv_items gi WHERE gi.grv_id = g.id) AS line_count,
                    (SELECT COALESCE(SUM(gi.quantity),0) FROM grv_items gi WHERE gi.grv_id = g.id) AS unit_count
             FROM grvs g
             LEFT JOIN vendors v ON v.id = g.vendor_id
             LEFT JOIN locations l ON l.id = g.location_id
             LEFT JOIN users u ON u.id = g.received_by
             ORDER BY g.id DESC LIMIT " . (int)$limit
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT g.*, v.name AS vendor_name, l.name AS location_name, u.username
             FROM grvs g
             LEFT JOIN vendors v ON v.id = g.vendor_id
             LEFT JOIN locations l ON l.id = g.location_id
             LEFT JOIN users u ON u.id = g.received_by
             WHERE g.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $items = self::db()->prepare(
            "SELECT gi.*, p.name AS product_name, p.sku, p.unit AS product_unit
             FROM grv_items gi JOIN products p ON p.id = gi.product_id
             WHERE gi.grv_id = ? ORDER BY gi.id"
        );
        $items->execute([$id]);
        $row['items'] = $items->fetchAll();
        return $row;
    }
}
