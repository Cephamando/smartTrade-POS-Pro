<?php

namespace App\Models;

final class Vendor extends Model
{
    public static function ensurePortal(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        $pdo = self::db();
        $names = array_column($pdo->query('SHOW COLUMNS FROM vendors')->fetchAll() ?: [], 'Field');
        foreach ([
            'portal_email' => 'VARCHAR(190) DEFAULT NULL',
            'portal_password_hash' => 'VARCHAR(255) DEFAULT NULL',
            'portal_active' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ] as $column => $definition) {
            if (!in_array($column, $names, true)) {
                $pdo->exec('ALTER TABLE vendors ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        try {
            $pdo->exec('CREATE UNIQUE INDEX uniq_vendor_portal_email ON vendors (portal_email)');
        } catch (\Throwable $e) {
        }
    }

    public static function all(): array
    {
        self::ensurePortal();
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

    public static function find(int $id): ?array
    {
        self::ensurePortal();
        $stmt = self::db()->prepare('SELECT * FROM vendors WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array{id: int, name: string, contact_person: string, phone: string, portal_email: string}|null */
    public static function authenticate(string $email, string $password): ?array
    {
        self::ensurePortal();
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            return null;
        }
        $stmt = self::db()->prepare(
            'SELECT id, name, contact_person, phone, portal_email, portal_password_hash
             FROM vendors
             WHERE portal_email = ? AND portal_active = 1 AND COALESCE(is_active, 1) = 1
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, (string)($row['portal_password_hash'] ?? ''))) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'contact_person' => (string)($row['contact_person'] ?? ''),
            'phone' => (string)($row['phone'] ?? ''),
            'portal_email' => (string)$row['portal_email'],
        ];
    }

    /** Returns a generated password when one was created, so the office can hand it over once. */
    public static function setPortal(int $id, string $email, string $password, bool $active): ?string
    {
        self::ensurePortal();
        $vendor = self::find($id);
        if (!$vendor) {
            throw new \InvalidArgumentException('Vendor not found.');
        }
        $email = strtolower(trim($email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Enter a valid supplier email.');
        }
        if ($active && $email === '') {
            throw new \InvalidArgumentException('Enter the supplier email before turning the portal on.');
        }
        if ($email !== '') {
            $dup = self::db()->prepare('SELECT id FROM vendors WHERE portal_email = ? AND id <> ? LIMIT 1');
            $dup->execute([$email, $id]);
            if ($dup->fetchColumn()) {
                throw new \InvalidArgumentException('That email is already used by another vendor.');
            }
        }
        $generated = null;
        $hash = null;
        if ($password !== '') {
            if (strlen($password) < 8) {
                throw new \InvalidArgumentException('The portal password needs at least 8 characters.');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
        } elseif ($active && trim((string)($vendor['portal_password_hash'] ?? '')) === '') {
            $generated = bin2hex(random_bytes(4));
            $hash = password_hash($generated, PASSWORD_DEFAULT);
        }
        if ($hash !== null) {
            self::db()->prepare('UPDATE vendors SET portal_email = ?, portal_password_hash = ?, portal_active = ? WHERE id = ?')
                ->execute([$email !== '' ? $email : null, $hash, $active ? 1 : 0, $id]);
        } else {
            self::db()->prepare('UPDATE vendors SET portal_email = ?, portal_active = ? WHERE id = ?')
                ->execute([$email !== '' ? $email : null, $active ? 1 : 0, $id]);
        }
        return $generated;
    }

    public static function updatePortalProfile(int $id, string $contact, string $phone, string $newPassword): void
    {
        self::ensurePortal();
        $contact = trim($contact);
        $phone = trim($phone);
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            throw new \InvalidArgumentException('The new password needs at least 8 characters.');
        }
        if ($newPassword !== '') {
            self::db()->prepare('UPDATE vendors SET contact_person = ?, phone = ?, portal_password_hash = ? WHERE id = ?')
                ->execute([$contact, $phone, password_hash($newPassword, PASSWORD_DEFAULT), $id]);
            return;
        }
        self::db()->prepare('UPDATE vendors SET contact_person = ?, phone = ? WHERE id = ?')
            ->execute([$contact, $phone, $id]);
    }
}
