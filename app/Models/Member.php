<?php

namespace App\Models;

final class Member extends Model
{
    public static function all(): array
    {
        return self::db()->query('SELECT * FROM members ORDER BY name ASC')->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM members WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function history(int $id): array
    {
        $stmt = self::db()->prepare(
            "SELECT s.*, u.username as cashier
             FROM sales s
             JOIN users u ON s.user_id = u.id
             WHERE s.member_id = ?
             ORDER BY s.created_at DESC LIMIT 20"
        );
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }

    public static function create(string $name, string $phone, string $email): void
    {
        self::db()->prepare('INSERT INTO members (name, phone, email) VALUES (?, ?, ?)')
            ->execute([$name, $phone, $email]);
    }

    public static function update(int $id, string $name, string $phone, string $email): void
    {
        self::db()->prepare('UPDATE members SET name = ?, phone = ?, email = ? WHERE id = ?')
            ->execute([$name, $phone, $email, $id]);
    }
}
