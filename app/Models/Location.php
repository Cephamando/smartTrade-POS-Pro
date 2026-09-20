<?php

namespace App\Models;

final class Location extends Model
{
    public static function ensureSchema(): void
    {
        $pdo = self::db();
        
        // Auto-migrate POS flag
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'locations' AND COLUMN_NAME = 'can_sell'");
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE locations ADD COLUMN can_sell TINYINT(1) NOT NULL DEFAULT 1");
            $pdo->exec("UPDATE locations SET can_sell = 0 WHERE type IN ('warehouse', 'kitchen')");
        }

        // Auto-migrate Goods Receiver flag
        $stmt2 = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'locations' AND COLUMN_NAME = 'can_receive'");
        if ((int)$stmt2->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE locations ADD COLUMN can_receive TINYINT(1) NOT NULL DEFAULT 0");
            $pdo->exec("UPDATE locations SET can_receive = 1 WHERE type IN ('warehouse', 'kitchen')");
        }
    }

    public static function all(): array
    {
        return self::db()->query('SELECT * FROM locations ORDER BY name ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM locations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function names(): array
    {
        return self::db()->query('SELECT id, name FROM locations ORDER BY name ASC')->fetchAll();
    }

    public static function productionSource(?int $preferredId = null): int
    {
        $pdo = self::db();
        if ($preferredId) {
            $stmt = $pdo->prepare("SELECT id, type FROM locations WHERE id = ?");
            $stmt->execute([$preferredId]);
            $row = $stmt->fetch();
            if ($row && in_array($row['type'], ['kitchen', 'warehouse'], true)) {
                return (int)$row['id'];
            }
        }
        $id = $pdo->query(
            "SELECT id FROM locations WHERE type IN ('kitchen', 'warehouse') ORDER BY FIELD(type, 'kitchen', 'warehouse'), id ASC LIMIT 1"
        )->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        return (int)($preferredId ?: 0);
    }

    public static function sellable(): array
    {
        return self::db()->query('SELECT id, name FROM locations WHERE can_sell = 1 ORDER BY name ASC')->fetchAll();
    }

    /** The sites that are authorized to receive vendor stock. */
    public static function vendorReceiveSites(): array
    {
        return self::db()->query(
            "SELECT * FROM locations WHERE can_receive = 1 ORDER BY name ASC"
        )->fetchAll() ?: [];
    }

    public static function nameById(int $id): string
    {
        $stmt = self::db()->prepare('SELECT name FROM locations WHERE id = ?');
        $stmt->execute([$id]);
        return (string)($stmt->fetchColumn() ?: 'Unknown');
    }

    public static function create(string $name, string $type, string $address, int $canSell, int $canReceive): void
    {
        self::db()->prepare('INSERT INTO locations (name, type, address, can_sell, can_receive) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $type, $address, $canSell, $canReceive]);
    }

    public static function update(int $id, string $name, string $type, string $address, int $canSell, int $canReceive): void
    {
        self::db()->prepare('UPDATE locations SET name=?, type=?, address=?, can_sell=?, can_receive=? WHERE id=?')
            ->execute([$name, $type, $address, $canSell, $canReceive, $id]);
    }

    public static function delete(int $id): string
    {
        $pdo = self::db();
        $checkStock = $pdo->prepare('SELECT id FROM location_stock WHERE location_id = ? AND quantity > 0 LIMIT 1');
        $checkStock->execute([$id]);
        if ($checkStock->fetchColumn()) {
            return 'stock';
        }
        $checkSales = $pdo->prepare('SELECT id FROM sales WHERE location_id = ? LIMIT 1');
        $checkSales->execute([$id]);
        if ($checkSales->fetchColumn()) {
            return 'sales';
        }
        $pdo->prepare('DELETE FROM locations WHERE id = ?')->execute([$id]);
        return 'ok';
    }
}
