<?php

namespace App\Models;

final class SurchargeReason extends Model
{
    public static function all(bool $activeOnly = false): array
    {
        $sql = 'SELECT * FROM surcharge_reasons' . ($activeOnly ? ' WHERE is_active = 1' : '') . ' ORDER BY sort_order ASC, name ASC';
        try {
            return self::db()->query($sql)->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM surcharge_reasons WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function save(?int $id, string $name, float $amount, float $percent, bool $active, int $sort): int
    {
        $name = trim($name);
        if ($name === '') {
            throw new \InvalidArgumentException('Give the surcharge a name.');
        }
        if ($id) {
            self::db()->prepare(
                'UPDATE surcharge_reasons SET name = ?, default_amount = ?, default_percent = ?, is_active = ?, sort_order = ? WHERE id = ?'
            )->execute([$name, $amount, $percent, $active ? 1 : 0, $sort, $id]);
            return $id;
        }
        self::db()->prepare(
            'INSERT INTO surcharge_reasons (name, default_amount, default_percent, is_active, sort_order) VALUES (?, ?, ?, ?, ?)'
        )->execute([$name, $amount, $percent, $active ? 1 : 0, $sort]);
        return (int)self::db()->lastInsertId();
    }

    public static function toggle(int $id): void
    {
        self::db()->prepare('UPDATE surcharge_reasons SET is_active = 1 - is_active WHERE id = ?')->execute([$id]);
    }

    public static function delete(int $id): void
    {
        self::db()->prepare('DELETE FROM surcharge_reasons WHERE id = ?')->execute([$id]);
    }
}
