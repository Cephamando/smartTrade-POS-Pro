<?php

namespace App\Models;

final class User extends Model
{
    public static function findActiveByUsername(string $username): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT u.id, u.username, u.password_hash, u.role, u.location_id, u.force_password_change, u.session_rev, u.totp_secret, l.name as location_name
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
        $stmt = self::db()->prepare("SELECT id, password_hash, approval_code_hash, role FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if (!$user || !in_array((string)$user['role'], ['admin', 'manager', 'dev'], true)) {
            return null;
        }
        return self::approvalMatches($user, $password) ? $user : null;
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

    public static function changePassword(int $id, string $newHash): int
    {
        self::db()->prepare('UPDATE users SET password_hash = ?, force_password_change = 0, session_rev = session_rev + 1 WHERE id = ?')
            ->execute([$newHash, $id]);
        return self::sessionRev($id);
    }

    public static function activeStaff(): array
    {
        return self::db()->query(
            "SELECT u.id, u.username, u.full_name, u.role, u.location_id, l.name as location_name,
                    (u.approval_code_hash IS NOT NULL AND u.approval_code_hash <> '') AS has_approval
             FROM users u
             LEFT JOIN locations l ON u.location_id = l.id
             WHERE COALESCE(u.is_active, 1) = 1
             ORDER BY FIELD(u.role, 'dev', 'admin', 'manager', 'head_chef', 'chef', 'bartender', 'waiter', 'cashier'), u.full_name ASC"
        )->fetchAll();
    }

    /** Staff row for a payroll link, including an inactive login already on file. */
    public static function staffMember(int $id): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT u.id, u.username, u.full_name, u.role, u.location_id, l.name as location_name
             FROM users u
             LEFT JOIN locations l ON u.location_id = l.id
             WHERE u.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Roles that exist on staff logins. Labels match the Staff screen.
     * @return list<array{key: string, label: string}>
     */
    public static function staffRoles(): array
    {
        $labels = [
            'dev' => 'Developer',
            'admin' => 'Administrator',
            'manager' => 'Manager',
            'head_chef' => 'Head Chef',
            'chef' => 'Chef',
            'bartender' => 'Bartender',
            'cashier' => 'Cashier',
            'waiter' => 'Waiter',
        ];
        $found = self::db()->query(
            "SELECT DISTINCT role FROM users WHERE role IS NOT NULL AND TRIM(role) <> ''"
        )->fetchAll(\PDO::FETCH_COLUMN);
        $keys = array_values(array_unique(array_map('strval', $found ?: [])));
        if (!$keys) {
            $keys = array_keys($labels);
        }
        $order = array_keys($labels);
        usort($keys, static function (string $a, string $b) use ($order): int {
            $ia = array_search($a, $order, true);
            $ib = array_search($b, $order, true);
            $ia = $ia === false ? 99 : $ia;
            $ib = $ib === false ? 99 : $ib;
            return $ia <=> $ib ?: strcasecmp($a, $b);
        });
        $out = [];
        foreach ($keys as $key) {
            $out[] = [
                'key' => $key,
                'label' => $labels[$key] ?? ucwords(str_replace('_', ' ', $key)),
            ];
        }
        return $out;
    }

    public static function locationIdOrNull(int $locationId): ?int
    {
        return $locationId > 0 ? $locationId : null;
    }

    public static function create(string $username, string $fullName, string $passwordHash, string $role, int $locationId): int
    {
        self::db()->prepare('INSERT INTO users (username, full_name, password_hash, role, location_id, is_active) VALUES (?, ?, ?, ?, ?, 1)')
            ->execute([$username, $fullName, $passwordHash, $role, self::locationIdOrNull($locationId)]);
        return (int)self::db()->lastInsertId();
    }

    public static function update(int $id, string $username, string $fullName, string $role, int $locationId, ?string $passwordHash = null): void
    {
        $locationId = self::locationIdOrNull($locationId);
        if ($passwordHash) {
            self::db()->prepare('UPDATE users SET username = ?, full_name = ?, role = ?, location_id = ?, password_hash = ?, session_rev = session_rev + 1 WHERE id = ?')
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
            "SELECT id, password_hash, approval_code_hash FROM users
             WHERE (role IN ('admin', 'head_chef','dev') OR (role = 'manager' AND location_id = ?))"
        );
        $stmt->execute([$locationId]);
        foreach ($stmt->fetchAll() as $mgr) {
            if (!self::approvalMatches($mgr, $password)) {
                continue;
            }
            if ((int)$mgr['id'] === $currentUserId) {
                return 'SELF_ERROR';
            }
            return (int)$mgr['id'];
        }
        return false;
    }

    public static function officeRole(string $role): bool
    {
        return in_array($role, ['admin', 'manager', 'dev'], true);
    }

    public static function approverRole(string $role): bool
    {
        return in_array($role, ['admin', 'manager', 'dev', 'head_chef'], true);
    }

    public static function passwordOk(string $password): bool
    {
        return strlen($password) >= 6;
    }

    public static function approvalCodeError(string $code, string $passwordHash): ?string
    {
        $code = trim($code);
        if (strlen($code) < 4 || strlen($code) > 12) {
            return 'The approval code must be 4 to 12 characters.';
        }
        if ($passwordHash !== '' && password_verify($code, $passwordHash)) {
            return 'The approval code must not be the login password.';
        }
        return null;
    }

    public static function setApprovalCode(int $id, string $code): void
    {
        self::db()->prepare('UPDATE users SET approval_code_hash = ? WHERE id = ?')
            ->execute([password_hash(trim($code), PASSWORD_DEFAULT), $id]);
    }

    public static function sessionRev(int $id): int
    {
        $stmt = self::db()->prepare('SELECT session_rev FROM users WHERE id = ?');
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn();
    }

    /** @return array{id: int, role: string, session_rev: int, is_active: int}|null */
    public static function sessionStamp(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT id, role, session_rev, COALESCE(is_active, 1) AS is_active FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function verifyDeveloper(string $username, string $password): ?array
    {
        $user = self::findActiveByUsername($username);
        if (
            !$user
            || (string)$user['role'] !== 'dev'
            || !password_verify($password, (string)$user['password_hash'])
        ) {
            return null;
        }
        return $user;
    }

    public static function saveSignInCode(int $id, string $secret, array $recoveryHashes): void
    {
        self::db()->prepare('UPDATE users SET totp_secret = ?, totp_recovery = ? WHERE id = ?')
            ->execute([$secret, json_encode(array_values($recoveryHashes)), $id]);
    }

    public static function clearSignInCode(int $id): void
    {
        self::db()->prepare('UPDATE users SET totp_secret = NULL, totp_recovery = NULL, session_rev = session_rev + 1 WHERE id = ?')
            ->execute([$id]);
    }

    public static function consumeRecoveryCode(int $id, string $code): bool
    {
        $code = strtoupper(preg_replace('/\s+/', '', $code) ?? '');
        if ($code === '') {
            return false;
        }
        $stmt = self::db()->prepare('SELECT totp_recovery FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $raw = $stmt->fetchColumn();
        $hashes = json_decode((string)$raw, true);
        if (!is_array($hashes)) {
            return false;
        }
        foreach ($hashes as $i => $hash) {
            if (is_string($hash) && password_verify($code, $hash)) {
                unset($hashes[$i]);
                self::db()->prepare('UPDATE users SET totp_recovery = ? WHERE id = ?')
                    ->execute([json_encode(array_values($hashes)), $id]);
                return true;
            }
        }
        return false;
    }

    /** The till code is accepted only when it is not the login password. */
    private static function approvalMatches(array $user, string $code): bool
    {
        $code = trim($code);
        $passwordHash = (string)($user['password_hash'] ?? '');
        $approvalHash = (string)($user['approval_code_hash'] ?? '');
        if ($code === '' || $approvalHash === '' || $passwordHash === '') {
            return false;
        }
        if (password_verify($code, $passwordHash)) {
            return false;
        }
        return password_verify($code, $approvalHash);
    }
}
