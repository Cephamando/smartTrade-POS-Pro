<?php

namespace App\Models;

final class User extends Model
{
    public static function findActiveByUsername(string $username): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT u.id, u.username, u.password_hash, u.role, u.location_id, u.force_password_change, l.name as location_name
             FROM users u
             LEFT JOIN locations l ON u.location_id = l.id
             WHERE u.username = ? AND (u.is_active = 1 OR u.is_active IS NULL)"
        );
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function verifyManager(string $username, string $password): ?array
    {
        $stmt = self::db()->prepare("SELECT id, password_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (
            !$user
            || !in_array($user['role'], ['admin', 'manager', 'dev'], true)
            || !password_verify($password, $user['password_hash'])
        ) {
            return null;
        }
        return $user;
    }

    public static function findById(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function passwordHash(int $id): ?string
    {
        $stmt = self::db()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $hash = $stmt->fetchColumn();
        return $hash !== false ? (string)$hash : null;
    }

    public static function changePassword(int $id, string $newHash): void
    {
        self::db()->prepare('UPDATE users SET password_hash = ?, force_password_change = 0 WHERE id = ?')
            ->execute([$newHash, $id]);
    }

    public static function activeStaff(): array
    {
        return self::db()->query(
            "SELECT u.id, u.username, u.full_name, u.role, u.location_id, l.name as location_name
             FROM users u
             LEFT JOIN locations l ON u.location_id = l.id
             WHERE COALESCE(u.is_active, 1) = 1
             ORDER BY FIELD(u.role, 'dev', 'admin', 'manager', 'head_chef', 'chef', 'bartender', 'waiter', 'cashier'), u.full_name ASC"
        )->fetchAll();
    }

    public static function create(string $username, string $fullName, string $passwordHash, string $role, int $locationId): void
    {
        self::db()->prepare('INSERT INTO users (username, full_name, password_hash, role, location_id, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([$username, $fullName, $passwordHash, $role, $locationId]);
    }

    public static function update(int $id, string $username, string $fullName, string $role, int $locationId, ?string $passwordHash = null): void
    {
        if ($passwordHash) {
            self::db()->prepare('UPDATE users SET username = ?, full_name = ?, role = ?, location_id = ?, password_hash = ? WHERE id = ?')
                ->execute([$username, $fullName, $role, $locationId, $passwordHash, $id]);
            return;
        }
        self::db()->prepare('UPDATE users SET username = ?, full_name = ?, role = ?, location_id = ? WHERE id = ?')
            ->execute([$username, $fullName, $role, $locationId, $id]);
    }

    public static function deleteOrDeactivate(int $id): string
    {
        try {
            self::db()->prepare('DELETE FROM users WHERE id = ?')->execute([$id]);
            return 'deleted';
        } catch (\PDOException $e) {
            if ($e->getCode() == '23000') {
                self::db()->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$id]);
                return 'deactivated';
            }
            throw $e;
        }
    }

    public static function waiters(): array
    {
        return self::db()->query(
            "SELECT id, full_name FROM users WHERE role IN ('waiter', 'cashier', 'manager', 'admin') ORDER BY full_name ASC"
        )->fetchAll();
    }

    public static function staffForFilter(): array
    {
        return self::db()->query(
            'SELECT id, full_name, role FROM users WHERE COALESCE(is_active, 1) = 1 ORDER BY full_name ASC'
        )->fetchAll();
    }

    /**
     * @return int|string|false manager id, SELF_ERROR, or false
     */
    public static function verifyShiftManager(string $password, int $locationId, int $currentUserId)
    {
        $stmt = self::db()->prepare(
            "SELECT id, password_hash FROM users
             WHERE (role IN ('admin', 'head_chef','dev') OR (role = 'manager' AND location_id = ?))"
        );
        $stmt->execute([$locationId]);
        foreach ($stmt->fetchAll() as $mgr) {
            if (password_verify($password, $mgr['password_hash'])) {
                if ((int)$mgr['id'] === $currentUserId) {
                    return 'SELF_ERROR';
                }
                return (int)$mgr['id'];
            }
        }
        return false;
    }
}
