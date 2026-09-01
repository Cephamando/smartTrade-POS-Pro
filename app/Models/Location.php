<?php

namespace App\Models;

final class Location extends Model
{
    public static function all(): array
    {
        return self::db()->query('SELECT * FROM locations ORDER BY name ASC')->fetchAll();
    }

    public static function names(): array
    {
        return self::db()->query('SELECT id, name FROM locations ORDER BY name ASC')->fetchAll();
    }

    public static function sellable(): array
    {
        return self::db()->query('SELECT id, name FROM locations WHERE can_sell = 1 ORDER BY name ASC')->fetchAll();
    }

    public static function nameById(int $id): string
    {
        $stmt = self::db()->prepare('SELECT name FROM locations WHERE id = ?');
        $stmt->execute([$id]);
        return (string)($stmt->fetchColumn() ?: 'Unknown');
    }

    public static function create(string $name, string $type, string $address): void
    {
        self::db()->prepare('INSERT INTO locations (name, type, address) VALUES (?, ?, ?)')
            ->execute([$name, $type, $address]);
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
