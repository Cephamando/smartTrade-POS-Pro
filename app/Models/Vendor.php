<?php

namespace App\Models;

final class Vendor extends Model
{
    public static function all(): array
    {
        return self::db()->query('SELECT * FROM vendors ORDER BY name ASC')->fetchAll();
    }

    public static function active(): array
    {
        return self::db()->query('SELECT * FROM vendors WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
    }

    public static function findIdByName(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $stmt = self::db()->prepare('SELECT id FROM vendors WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    public static function create(string $name, string $contact, string $phone): void
    {
        self::db()->prepare('INSERT INTO vendors (name, contact_person, phone) VALUES (?, ?, ?)')
            ->execute([$name, $contact, $phone]);
    }

    public static function delete(int $id): bool
    {
        $check = self::db()->prepare('SELECT id FROM grvs WHERE vendor_id = ? LIMIT 1');
        $check->execute([$id]);
        if ($check->fetchColumn()) {
            return false;
        }
        self::db()->prepare('DELETE FROM vendors WHERE id = ?')->execute([$id]);
        return true;
    }
}
