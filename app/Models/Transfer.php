<?php

namespace App\Models;

final class Transfer extends Model
{
    public static function createRequest(int $sourceId, int $destId, int $productId, float $qty, int $userId): void
    {
        self::db()->prepare(
            "INSERT INTO inventory_transfers (source_location_id, dest_location_id, product_id, quantity, user_id, status, created_at)
             VALUES (?, ?, ?, ?, ?, 'pending', NOW())"
        )->execute([$sourceId, $destId, $productId, $qty, $userId]);
    }

    public static function executeImmediate(int $sourceId, int $destId, int $productId, float $qty, int $userId): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $check = $pdo->prepare('SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?');
            $check->execute([$productId, $sourceId]);
            $currentQty = (float)($check->fetchColumn() ?: 0);
            if ($currentQty < $qty) {
                throw new \Exception('Insufficient stock at the source location.');
            }
            $pdo->prepare(
                "INSERT INTO inventory_transfers
                    (source_location_id, dest_location_id, product_id, quantity, user_id, status, created_at, dispatched_at, received_at)
                 VALUES (?, ?, ?, ?, ?, 'completed', NOW(), NOW(), NOW())"
            )->execute([$sourceId, $destId, $productId, $qty, $userId]);
            $id = (int)$pdo->lastInsertId();

            $pdo->prepare('UPDATE inventory SET quantity = GREATEST(0, quantity - ?) WHERE product_id = ? AND location_id = ?')
                ->execute([$qty, $productId, $sourceId]);
            $pdo->prepare(
                "INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id)
                 VALUES (?, ?, ?, ?, ?, 'transfer_out', ?)"
            )->execute([$productId, $sourceId, $userId, -$qty, $currentQty - $qty, $id]);

            $pdo->prepare(
                'INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
            )->execute([$productId, $destId, $qty]);
            $destQty = $pdo->prepare('SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?');
            $destQty->execute([$productId, $destId]);
            $pdo->prepare(
                "INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id)
                 VALUES (?, ?, ?, ?, ?, 'transfer_in', ?)"
            )->execute([$productId, $destId, $userId, $qty, $destQty->fetchColumn(), $id]);

            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM inventory_transfers WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function dispatch(int $id, int $userId): string
    {
        $pdo = self::db();
        $transfer = self::find($id);
        if (!$transfer || $transfer['status'] !== 'pending') {
            return 'missing';
        }
        $pdo->beginTransaction();
        try {
            $check = $pdo->prepare('SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?');
            $check->execute([$transfer['product_id'], $transfer['source_location_id']]);
            $currentQty = $check->fetchColumn() ?: 0;
            if ($currentQty < $transfer['quantity']) {
                $pdo->rollBack();
                return 'stock';
            }
            $pdo->prepare('UPDATE inventory SET quantity = GREATEST(0, quantity - ?) WHERE product_id = ? AND location_id = ?')
                ->execute([$transfer['quantity'], $transfer['product_id'], $transfer['source_location_id']]);
            $newQty = $currentQty - $transfer['quantity'];
            $pdo->prepare(
                "INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id)
                 VALUES (?, ?, ?, ?, ?, 'transfer_out', ?)"
            )->execute([$transfer['product_id'], $transfer['source_location_id'], $userId, -$transfer['quantity'], $newQty, $id]);
            $pdo->prepare("UPDATE inventory_transfers SET status = 'in_transit', dispatched_at = NOW() WHERE id = ?")->execute([$id]);
            $pdo->commit();
            return 'ok';
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function receive(int $id, int $userId): void
    {
        $pdo = self::db();
        $transfer = self::find($id);
        if (!$transfer || $transfer['status'] !== 'in_transit') {
            return;
        }
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                'INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)'
            )->execute([$transfer['product_id'], $transfer['dest_location_id'], $transfer['quantity']]);
            $check = $pdo->prepare('SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?');
            $check->execute([$transfer['product_id'], $transfer['dest_location_id']]);
            $newQty = $check->fetchColumn();
            $pdo->prepare(
                "INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reference_id)
                 VALUES (?, ?, ?, ?, ?, 'transfer_in', ?)"
            )->execute([$transfer['product_id'], $transfer['dest_location_id'], $userId, $transfer['quantity'], $newQty, $id]);
            $pdo->prepare("UPDATE inventory_transfers SET status = 'completed', received_at = NOW() WHERE id = ?")->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function cancel(int $id): void
    {
        self::db()->prepare("UPDATE inventory_transfers SET status = 'cancelled' WHERE id = ? AND status = 'pending'")->execute([$id]);
    }

    public static function setQuantity(int $id, float $qty): void
    {
        if ($qty <= 0) {
            return;
        }
        self::db()->prepare(
            "UPDATE inventory_transfers SET quantity = ? WHERE id = ? AND status IN ('pending', 'in_transit')"
        )->execute([$qty, $id]);
    }

    public static function listByStatus(string $status, string $role, int $userLoc, string $locColumn): array
    {
        $sql = "SELECT t.*, p.name AS product_name, p.sku, p.unspsc_code, p.unit, p.tax_class,
                       p.category_id, c.name AS category_name, l1.name AS source_name, l2.name AS dest_name
                FROM inventory_transfers t
                JOIN products p ON t.product_id = p.id
                LEFT JOIN categories c ON c.id = p.category_id
                JOIN locations l1 ON t.source_location_id = l1.id
                JOIN locations l2 ON t.dest_location_id = l2.id
                WHERE t.status = ?";
        $params = [$status];
        if ($role !== 'admin' && $role !== 'dev') {
            $sql .= " AND t.{$locColumn} = ?";
            $params[] = $userLoc;
        }
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }
}
