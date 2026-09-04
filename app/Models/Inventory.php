<?php

namespace App\Models;

use App\Helpers\Movement;

final class Inventory extends Model
{
    public static function ensureReasonColumn(): void
    {
        $pdo = self::db();
        try {
            $pdo->exec("ALTER TABLE inventory_logs ADD COLUMN reason VARCHAR(255) DEFAULT NULL");
        } catch (\Throwable $e) {
            // Column already exists.
        }
        try {
            $pdo->exec('ALTER TABLE inventory MODIFY quantity DECIMAL(12,4) NOT NULL DEFAULT 0');
        } catch (\Throwable $e) {
            // Column already decimal or locked.
        }
    }

    public static function quantity(int $productId, int $locationId): float
    {
        $stmt = self::db()->prepare('SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?');
        $stmt->execute([$productId, $locationId]);
        $qty = $stmt->fetchColumn();
        return $qty !== false ? max(0.0, (float)$qty) : 0.0;
    }

    /**
     * Locations that currently hold this product, most stock first.
     *
     * @return list<array{location_id:int,location_name:string,quantity:float}>
     */
    public static function stockByLocation(int $productId, int $excludeLocationId = 0): array
    {
        $sql = "SELECT l.id AS location_id, l.name AS location_name, GREATEST(i.quantity, 0) AS quantity
                FROM inventory i
                JOIN locations l ON l.id = i.location_id
                WHERE i.product_id = ? AND i.quantity > 0";
        $params = [$productId];
        if ($excludeLocationId > 0) {
            $sql .= ' AND i.location_id != ?';
            $params[] = $excludeLocationId;
        }
        $sql .= ' ORDER BY i.quantity DESC, l.name ASC';
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll() ?: [];
        foreach ($rows as &$row) {
            $row['location_id'] = (int)$row['location_id'];
            $row['quantity'] = (float)$row['quantity'];
        }
        unset($row);
        return $rows;
    }

    public static function filtered(string $locFilter, string $catFilter, string $search): array
    {
        $sql = "
            SELECT
                i.location_id, i.id AS inventory_id, p.id AS product_id, p.name AS product_name, p.sku, p.price, p.cost_price, p.category_id,
                c.name AS category_name, l.name AS location_name, GREATEST(i.quantity, 0) AS quantity, (GREATEST(i.quantity, 0) * p.price) AS stock_value
            FROM inventory i
            JOIN products p ON p.id = i.product_id
            JOIN locations l ON l.id = i.location_id
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE 1=1
        ";
        $params = [];
        if ($locFilter !== '') {
            $sql .= ' AND i.location_id = ?';
            $params[] = $locFilter;
        }
        if ($catFilter !== '') {
            $sql .= ' AND p.category_id = ?';
            $params[] = $catFilter;
        }
        if ($search !== '') {
            $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ?)';
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        $sql .= ' ORDER BY p.name ASC';
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function applyStockTake(array $post, int $userId): int
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $invIds = $post['inv_id'] ?? [];
            $physQtys = $post['physical_qty'] ?? [];
            $reasons = $post['reason'] ?? [];
            $prodIds = $post['product_id'] ?? [];
            $locIds = $post['location_id'] ?? [];
            $sysQtys = $post['sys_qty'] ?? [];
            $processedCount = 0;
            for ($i = 0; $i < count($invIds); $i++) {
                if ($physQtys[$i] === '' || $physQtys[$i] === null) {
                    continue;
                }
                $physQty = (float)$physQtys[$i];
                if ($physQty < 0) {
                    throw new \Exception('Physical count cannot be negative.');
                }
                $sysQty = (float)$sysQtys[$i];
                $variance = $physQty - $sysQty;
                if ($variance == 0) {
                    continue;
                }
                $invId = (int)$invIds[$i];
                $lock = $pdo->prepare('SELECT quantity FROM inventory WHERE id = ? FOR UPDATE');
                $lock->execute([$invId]);
                $live = max(0.0, (float)$lock->fetchColumn());
                $newQty = max(0.0, $live + $variance);
                $pdo->prepare('UPDATE inventory SET quantity = ? WHERE id = ?')->execute([$newQty, $invId]);
                $pdo->prepare(
                    "INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reason, created_at)
                     VALUES (?, ?, ?, ?, ?, 'stock_take', ?, NOW())"
                )->execute([
                    (int)$prodIds[$i],
                    (int)$locIds[$i],
                    $userId,
                    $newQty - $live,
                    $newQty,
                    trim($reasons[$i] ?? 'Physical Count Adjustment'),
                ]);
                $processedCount++;
            }
            $pdo->commit();
            return $processedCount;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function updateQtyAndPrice(int $inventoryId, float $qty, float $price): void
    {
        if ($qty < 0) {
            throw new \Exception('Stock cannot be negative.');
        }
        $pdo = self::db();
        $pdo->prepare('UPDATE inventory SET quantity = GREATEST(0, ?) WHERE id = ?')->execute([$qty, $inventoryId]);
        $pdo->prepare('UPDATE products p JOIN inventory i ON i.product_id = p.id SET p.price = ? WHERE i.id = ?')
            ->execute([$price, $inventoryId]);
    }

    public static function clampNegatives(int $userId): int
    {
        $pdo = self::db();
        $rows = $pdo->query('SELECT id, product_id, location_id, quantity FROM inventory WHERE quantity < 0')->fetchAll();
        if (!$rows) {
            return 0;
        }
        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE inventory SET quantity = 0 WHERE id = ?');
            $log = $pdo->prepare(
                "INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reason, created_at)
                 VALUES (?, ?, ?, ?, 0, 'adjustment', 'Cleared negative stock', NOW())"
            );
            foreach ($rows as $row) {
                $update->execute([$row['id']]);
                $log->execute([$row['product_id'], $row['location_id'], $userId, -(float)$row['quantity']]);
            }
            $pdo->commit();
            return count($rows);
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function logs(string $startDate, string $endDate, string $actionFilter = ''): array
    {
        $sql = "
            SELECT il.*, p.name as product_name, u.username as staff_name, l.name as loc_name
            FROM inventory_logs il
            JOIN products p ON il.product_id = p.id
            JOIN users u ON il.user_id = u.id
            JOIN locations l ON il.location_id = l.id
            WHERE DATE(il.created_at) BETWEEN ? AND ?
        ";
        $params = [$startDate, $endDate];
        if ($actionFilter !== '') {
            $sql .= ' AND il.action_type = ?';
            $params[] = $actionFilter;
        }
        $sql .= ' ORDER BY il.created_at DESC LIMIT 500';
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function actionTypes(): array
    {
        try {
            $typeStmt = self::db()->query("SHOW COLUMNS FROM inventory_logs LIKE 'action_type'");
            if ($typeRow = $typeStmt->fetch()) {
                preg_match("/^enum\(\'(.*)\'\)$/", $typeRow['Type'], $matches);
                return explode("','", $matches[1] ?? '');
            }
        } catch (\Throwable $e) {
        }
        return ['sale', 'grv', 'transfer_in', 'transfer_out', 'adjustment'];
    }

    public static function produce(int $productId, float $qty, int $targetLocId, int $userId, ?int $sourceLocId = null): void
    {
        $check = Movement::produceTo(Product::movementContext($productId), Location::find($targetLocId));
        if (!$check['ok']) {
            throw new \Exception($check['reason'] ?? 'This production move is not allowed.');
        }
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $sourceLocId = Location::productionSource($sourceLocId);
            $recipe = recipeLines($pdo, $productId);
            if ($recipe && $sourceLocId > 0) {
                foreach ($recipe as $ing) {
                    consumeProduct(
                        $pdo,
                        (int)$ing['ingredient_product_id'],
                        (float)$ing['quantity'] * $qty,
                        $sourceLocId,
                        $userId,
                        'recipe_deduction'
                    );
                }
            }
            $stmt = $pdo->prepare('SELECT id, quantity FROM inventory WHERE product_id = ? AND location_id = ? FOR UPDATE');
            $stmt->execute([$productId, $targetLocId]);
            $inv = $stmt->fetch();
            if ($inv) {
                $newQty = max(0.0, (float)$inv['quantity']) + $qty;
                $pdo->prepare('UPDATE inventory SET quantity = ? WHERE id = ?')->execute([$newQty, $inv['id']]);
            } else {
                $pdo->prepare('INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?)')
                    ->execute([$productId, $targetLocId, $qty]);
                $newQty = $qty;
            }
            $pdo->prepare('INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type) VALUES (?, ?, ?, ?, ?, \'produce\')')
                ->execute([$productId, $targetLocId, $userId, $qty, $newQty]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
