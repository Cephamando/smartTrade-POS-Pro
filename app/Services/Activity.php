<?php

namespace App\Services;

use App\Core\Database;
use PDO;

/** Staff sign-in, sales, and other actions a developer can review. */
final class Activity
{
    public static function log(?PDO $pdo, string $action, string $outcome = 'ok', array $extra = []): void
    {
        try {
            $pdo = $pdo ?: Database::connection();
            $userId = array_key_exists('user_id', $extra) ? $extra['user_id'] : ($_SESSION['user_id'] ?? null);
            $userId = (int)$userId > 0 ? (int)$userId : null;
            $username = trim((string)($extra['username'] ?? ($_SESSION['username'] ?? '')));
            $locationId = array_key_exists('location_id', $extra) ? $extra['location_id'] : ($_SESSION['location_id'] ?? null);
            $locationId = (int)$locationId > 0 ? (int)$locationId : null;
            $locationName = trim((string)($extra['location_name'] ?? ($_SESSION['location_name'] ?? '')));
            $stmt = $pdo->prepare(
                'INSERT INTO activity_log (user_id, username, action, outcome, location_id, location_name, subject, detail, ip)
                 VALUES (?,?,?,?,?,?,?,?,?)'
            );
            $stmt->execute([
                $userId,
                $username !== '' ? mb_substr($username, 0, 80) : null,
                mb_substr($action, 0, 40),
                mb_substr($outcome, 0, 20),
                $locationId,
                $locationName !== '' ? mb_substr($locationName, 0, 120) : null,
                self::clip($extra['subject'] ?? '', 160),
                self::clip($extra['detail'] ?? '', 500),
                self::clip($_SERVER['REMOTE_ADDR'] ?? '', 45),
            ]);
        } catch (\Throwable $e) {
            error_log('activity log: ' . $e->getMessage());
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function search(PDO $pdo, string $start, string $end, int $userId, string $action, string $q): array
    {
        $sql = 'SELECT * FROM activity_log WHERE created_at BETWEEN ? AND ?';
        $params = [$start . ' 00:00:00', $end . ' 23:59:59'];
        if ($userId > 0) {
            $sql .= ' AND user_id = ?';
            $params[] = $userId;
        }
        if ($action !== '') {
            $sql .= ' AND action = ?';
            $params[] = $action;
        }
        if ($q !== '') {
            $sql .= ' AND (username LIKE ? OR subject LIKE ? OR detail LIKE ? OR location_name LIKE ?)';
            $like = '%' . $q . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY id DESC LIMIT 300';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<string> */
    public static function actions(PDO $pdo): array
    {
        return $pdo->query('SELECT DISTINCT action FROM activity_log ORDER BY action')->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    private static function clip(string $value, int $max): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return mb_substr($value, 0, $max);
    }
}
