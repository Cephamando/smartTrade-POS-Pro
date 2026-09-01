<?php

namespace App\Models;

final class Inventory extends Model
{
    public static function ensureReasonColumn(): void
    {
        try {
            self::db()->exec("ALTER TABLE inventory_logs ADD COLUMN reason VARCHAR(255) DEFAULT NULL");
        } catch (\Throwable $e) {
            // Column already exists.
        }
    }

    public static function quantity(int $productId, int $locationId): float
    {
        $stmt = self::db()->prepare('SELECT quantity FROM inventory WHERE product_id = ? AND location_id = ?');
        $stmt->execute([$productId, $locationId]);
        $qty = $stmt->fetchColumn();
        return $qty !== false ? (float)$qty : 0.0;
    }

    public static function filtered(string $locFilter, string $catFilter, string $search): array
    {
        $sql = "
            SELECT
                i.location_id, i.id AS inventory_id, p.id AS product_id, p.name AS product_name, p.sku, p.price, p.cost_price, p.category_id,
                c.name AS category_name, l.name AS location_name, i.quantity, (i.quantity * p.price) AS stock_value
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
                $sysQty = (float)$sysQtys[$i];
                $variance = $physQty - $sysQty;
                if ($variance == 0) {
                    continue;
                }
                $pdo->prepare('UPDATE inventory SET quantity = ? WHERE id = ?')->execute([$physQty, (int)$invIds[$i]]);
                $pdo->prepare(
                    "INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reason, created_at)
                     VALUES (?, ?, ?, ?, ?, 'stock_take', ?, NOW())"
                )->execute([(int)$prodIds[$i], (int)$locIds[$i], $userId, $variance, $physQty, trim($reasons[$i] ?? 'Physical Count Adjustment')]);
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
        $pdo = self::db();
        $pdo->prepare('UPDATE inventory SET quantity = ? WHERE id = ?')->execute([$qty, $inventoryId]);
        $pdo->prepare('UPDATE products p JOIN inventory i ON i.product_id = p.id SET p.price = ? WHERE i.id = ?')
            ->execute([$price, $inventoryId]);
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

    public static function produce(int $productId, float $qty, int $targetLocId, int $userId): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('SELECT id, quantity FROM inventory WHERE product_id = ? AND location_id = ?');
            $stmt->execute([$productId, $targetLocId]);
            $inv = $stmt->fetch();
            if ($inv) {
                $pdo->prepare('UPDATE inventory SET quantity = quantity + ? WHERE id = ?')->execute([$qty, $inv['id']]);
                $newQty = $inv['quantity'] + $qty;
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
