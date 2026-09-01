<?php

namespace App\Models;

final class Category extends Model
{
    public static function all(): array
    {
        return self::db()->query('SELECT * FROM categories ORDER BY name ASC')->fetchAll();
    }

    public static function allWithParents(): array
    {
        return self::db()->query(
            "SELECT c.*, p.name as parent_name
             FROM categories c
             LEFT JOIN categories p ON c.parent_id = p.id
             ORDER BY c.parent_id IS NOT NULL, p.name ASC, c.name ASC"
        )->fetchAll();
    }

    public static function findIdByName(string $name): ?int
    {
        $stmt = self::db()->prepare('SELECT id FROM categories WHERE name = ?');
        $stmt->execute([$name]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    public static function create(string $name, string $type = 'other', ?int $parentId = null): int
    {
        $stmt = self::db()->prepare('INSERT INTO categories (name, type, parent_id) VALUES (?, ?, ?)');
        $stmt->execute([$name, $type, $parentId]);
        return (int)self::db()->lastInsertId();
    }

    public static function update(int $id, string $name, string $type, ?int $parentId): void
    {
        if ($parentId === $id) {
            $parentId = null;
        }
        $stmt = self::db()->prepare('UPDATE categories SET name = ?, type = ?, parent_id = ? WHERE id = ?');
        $stmt->execute([$name, $type, $parentId, $id]);
    }

    public static function nameTaken(string $name, ?int $exceptId = null): bool
    {
        if ($exceptId) {
            $stmt = self::db()->prepare('SELECT id FROM categories WHERE name = ? AND id != ?');
            $stmt->execute([$name, $exceptId]);
        } else {
            $stmt = self::db()->prepare('SELECT id FROM categories WHERE name = ?');
            $stmt->execute([$name]);
        }
        return (bool)$stmt->fetchColumn();
    }

    public static function delete(int $id): string
    {
        $pdo = self::db();
        $checkProds = $pdo->prepare('SELECT id FROM products WHERE category_id = ? LIMIT 1');
        $checkProds->execute([$id]);
        if ($checkProds->fetchColumn()) {
            return 'products';
        }
        $checkSubs = $pdo->prepare('SELECT id FROM categories WHERE parent_id = ? LIMIT 1');
        $checkSubs->execute([$id]);
        if ($checkSubs->fetchColumn()) {
            return 'children';
        }
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
        return 'ok';
    }

    public static function byTypes(array $types): array
    {
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $stmt = self::db()->prepare("SELECT * FROM categories WHERE type IN ($placeholders) ORDER BY name ASC");
        $stmt->execute($types);
        return $stmt->fetchAll();
    }
}
