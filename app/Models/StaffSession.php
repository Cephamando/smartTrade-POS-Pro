<?php

namespace App\Models;

/**
 * One row per open browser. A developer ends a row to sign that browser out
 * on its next request. The row is not the session itself.
 */
final class StaffSession extends Model
{
    public static function begin(int $userId, string $role, int $locationId, string $locationName): string
    {
        $token = bin2hex(random_bytes(16));
        $now = date('Y-m-d H:i:s');
        self::db()->prepare(
            'INSERT INTO staff_sessions (user_id, token, role, location_id, location_name, ip, started_at, last_seen_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $userId,
            $token,
            $role,
            self::locationId($locationId),
            self::locationName($locationId, $locationName),
            self::ip(),
            $now,
            $now,
        ]);
        return $token;
    }

    public static function touch(string $token, string $role, int $locationId, string $locationName): void
    {
        if ($token === '') {
            return;
        }
        self::db()->prepare(
            'UPDATE staff_sessions
             SET role = ?, location_id = ?, location_name = ?, ip = ?, last_seen_at = ?
             WHERE token = ?'
        )->execute([
            $role,
            self::locationId($locationId),
            self::locationName($locationId, $locationName),
            self::ip(),
            date('Y-m-d H:i:s'),
            $token,
        ]);
    }

    /** False only when the table answers and this sign-in was ended. A failed check stays signed in. */
    public static function alive(string $token): bool
    {
        if ($token === '') {
            return true;
        }
        try {
            $stmt = self::db()->prepare('SELECT id FROM staff_sessions WHERE token = ?');
            $stmt->execute([$token]);
            return (bool)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            error_log('staff session: ' . $e->getMessage());
            return true;
        }
    }

    public static function forget(string $token): void
    {
        if ($token === '') {
            return;
        }
        try {
            self::db()->prepare('DELETE FROM staff_sessions WHERE token = ?')->execute([$token]);
        } catch (\Throwable $e) {
            error_log('staff session: ' . $e->getMessage());
        }
    }

    /**
     * Open browsers, newest activity first. Idle ones are dropped first.
     *
     * @return list<array<string, mixed>>
     */
    public static function online(): array
    {
        self::prune();
        $rows = self::db()->query(
            "SELECT s.id, s.user_id, s.token, s.location_id, s.location_name, s.ip, s.started_at, s.last_seen_at,
                    COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) AS who, u.username, u.role
             FROM staff_sessions s
             JOIN users u ON u.id = s.user_id
             ORDER BY s.last_seen_at DESC, s.id DESC"
        )->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['location_id'] = $row['location_id'] !== null ? (int)$row['location_id'] : 0;
            $row['started_label'] = self::label((string)$row['started_at']);
            $row['seen_label'] = self::label((string)$row['last_seen_at']);
        }
        unset($row);
        return $rows;
    }

    /** @return array{who: string, token: string}|null */
    public static function signOutOne(int $id): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT s.token, COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) AS who
             FROM staff_sessions s
             JOIN users u ON u.id = s.user_id
             WHERE s.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        self::db()->prepare('DELETE FROM staff_sessions WHERE id = ?')->execute([$id]);
        return ['who' => (string)$row['who'], 'token' => (string)$row['token']];
    }

    /**
     * Ends every open browser except the one asking, optionally only at one workstation.
     * A location id of 0 is the "No workstation" group.
     *
     * @return list<string>
     */
    public static function signOutGroup(string $keepToken, ?int $locationId): array
    {
        $sql = "SELECT s.id, COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) AS who
                FROM staff_sessions s
                JOIN users u ON u.id = s.user_id
                WHERE s.token <> ?";
        $params = [$keepToken];
        if ($locationId !== null) {
            if ($locationId > 0) {
                $sql .= ' AND s.location_id = ?';
                $params[] = $locationId;
            } else {
                $sql .= ' AND (s.location_id IS NULL OR s.location_id = 0)';
            }
        }
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        if (!$rows) {
            return [];
        }
        $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
        $marks = implode(',', array_fill(0, count($ids), '?'));
        self::db()->prepare("DELETE FROM staff_sessions WHERE id IN ($marks)")->execute($ids);
        return array_map(static fn(array $row): string => (string)$row['who'], $rows);
    }

    /** Office accounts idle out after 30 minutes. A till stays for 8 hours. */
    public static function prune(): void
    {
        self::db()->prepare(
            "DELETE FROM staff_sessions
             WHERE (role IN ('admin', 'manager', 'dev', 'owner') AND last_seen_at < ?)
                OR (role NOT IN ('admin', 'manager', 'dev', 'owner') AND last_seen_at < ?)"
        )->execute([
            date('Y-m-d H:i:s', time() - 1800),
            date('Y-m-d H:i:s', time() - 28800),
        ]);
    }

    private static function locationId(int $locationId): ?int
    {
        return $locationId > 0 ? $locationId : null;
    }

    private static function locationName(int $locationId, string $locationName): string
    {
        $name = trim($locationName);
        if ($locationId <= 0) {
            return 'No workstation';
        }
        return $name !== '' ? $name : 'Workstation';
    }

    private static function ip(): ?string
    {
        $ip = trim((string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($ip === '') {
            return null;
        }
        return substr($ip, 0, 45);
    }

    private static function label(string $at): string
    {
        $ts = strtotime($at);
        if ($ts === false) {
            return '';
        }
        $day = date('Y-m-d', $ts) === date('Y-m-d') ? 'Today' : date('j M', $ts);
        return $day . ', ' . date('H:i', $ts);
    }
}
