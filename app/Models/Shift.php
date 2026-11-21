<?php

namespace App\Models;

final class Shift extends Model
{
    public static function openWithStats(): array
    {
        return self::db()->query(
            "SELECT s.id, s.start_time, s.starting_cash, u.full_name as cashier_name, u.role, l.name as location_name,
                    (SELECT SUM(final_total) FROM sales WHERE shift_id = s.id AND payment_status = 'paid') as total_sales,
                    (SELECT SUM(final_total) FROM sales WHERE shift_id = s.id AND payment_status = 'paid' AND payment_method LIKE '%Cash%') as current_cash_sales,
                    (SELECT SUM(amount) FROM expenses WHERE user_id = s.user_id AND created_at >= s.start_time) as expenses
             FROM shifts s
             JOIN users u ON s.user_id = u.id
             JOIN locations l ON s.location_id = l.id
             WHERE s.status = 'open'
             ORDER BY s.start_time DESC"
        )->fetchAll();
    }

    public static function openForUser(int $userId, int $locationId): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM shifts WHERE user_id = ? AND location_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $locationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function openId(int $userId, int $locationId): ?int
    {
        $stmt = self::db()->prepare(
            "SELECT id FROM shifts WHERE user_id = ? AND location_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $locationId]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : null;
    }

    public static function pendingForUser(int $userId, int $locationId): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT id FROM shifts WHERE user_id = ? AND location_id = ? AND status = 'pending' ORDER BY id DESC LIMIT 1"
        );
        $stmt->execute([$userId, $locationId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function currentOpen(int $userId): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT * FROM shifts WHERE user_id = ? AND status = 'open' ORDER BY start_time DESC LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function userHasOpen(int $userId): bool
    {
        $stmt = self::db()->prepare("SELECT id FROM shifts WHERE user_id = ? AND status = 'open'");
        $stmt->execute([$userId]);
        return (bool)$stmt->fetchColumn();
    }

    public static function start(int $userId, int $locationId, float $startingCash, int $mgrId): void
    {
        self::db()->prepare(
            "INSERT INTO shifts (user_id, location_id, start_time, starting_cash, status, start_verified_by, start_verified_at)
             VALUES (?, ?, NOW(), ?, 'open', ?, NOW())"
        )->execute([$userId, $locationId, $startingCash, $mgrId]);
    }

    public static function close(
        int $shiftId,
        float $closingCash,
        float $managerCount,
        ?string $varianceReason,
        ?string $notes,
        int $mgrId
    ): void {
        self::db()->prepare(
            "UPDATE shifts SET
                end_time = NOW(),
                closing_cash = ?,
                manager_closing_cash = ?,
                variance_reason = ?,
                handover_notes = ?,
                status = 'closed',
                end_verified_by = ?,
                end_verified_at = NOW()
             WHERE id = ?"
        )->execute([$closingCash, $managerCount, $varianceReason, $notes, $mgrId, $shiftId]);
    }

    public static function closeFromPos(
        int $shiftId,
        float $closingCash,
        float $expectedCash,
        float $variance,
        string $reason
    ): void {
        self::db()->prepare(
            'UPDATE shifts SET status = \'closed\', end_time = NOW(), closing_cash = ?, expected_cash = ?, variance = ?, variance_reason = ? WHERE id = ?'
        )->execute([$closingCash, $expectedCash, $variance, $reason, $shiftId]);
    }

    public static function cashSummary(int $shiftId, int $userId, string $startTime): array
    {
        $sStmt = self::db()->prepare("SELECT SUM(final_total) FROM sales WHERE shift_id = ? AND payment_method = 'cash' AND status != 'refunded'");
        $sStmt->execute([$shiftId]);
        $cashSales = $sStmt->fetchColumn() ?: 0.00;
        $eStmt = self::db()->prepare('SELECT SUM(amount) FROM expenses WHERE user_id = ? AND created_at >= ?');
        $eStmt->execute([$userId, $startTime]);
        $expenses = $eStmt->fetchColumn() ?: 0.00;
        return [
            'cash_sales' => $cashSales,
            'expenses' => $expenses,
        ];
    }

    public static function historyForUser(int $userId): array
    {
        $stmt = self::db()->prepare(
            "SELECT s.*, u1.username as start_mgr, u2.username as end_mgr
             FROM shifts s
             LEFT JOIN users u1 ON s.start_verified_by = u1.id
             LEFT JOIN users u2 ON s.end_verified_by = u2.id
             WHERE s.user_id = ?
             ORDER BY s.start_time DESC LIMIT 10"
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public static function filteredForManager(string $date, string $userFilter, string $role, int $locationId): array
    {
        $query = "
            SELECT s.*, u.full_name as cashier_name, u.role, l.name as location_name,
                   (SELECT COALESCE(SUM(final_total), 0) FROM sales WHERE shift_id = s.id AND payment_status = 'paid') as total_sales,
                   (SELECT COALESCE(SUM(final_total), 0) FROM sales WHERE shift_id = s.id AND payment_method LIKE '%Cash%' AND payment_status = 'paid') as cash_sales,
                   (SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE shift_id = s.id) as total_expenses
            FROM shifts s
            JOIN users u ON s.user_id = u.id
            JOIN locations l ON s.location_id = l.id
            WHERE DATE(s.start_time) = :date
        ";
        $params = [':date' => $date];
        if ($userFilter !== '') {
            $query .= ' AND s.user_id = :uid';
            $params[':uid'] = $userFilter;
        }
        if ($role === 'manager') {
            $query .= ' AND s.location_id = :loc';
            $params[':loc'] = $locationId;
        }
        $query .= ' ORDER BY s.start_time DESC';
        $stmt = self::db()->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function withStaff(int $shiftId): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT s.*, u.username, l.name as loc_name FROM shifts s JOIN users u ON s.user_id = u.id JOIN locations l ON s.location_id = l.id WHERE s.id = ?'
        );
        $stmt->execute([$shiftId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function requestStart(int $userId, int $locationId, $startingCash): void
    {
        self::db()->prepare("INSERT INTO shifts (user_id, location_id, starting_cash, status) VALUES (?, ?, ?, 'pending')")
            ->execute([$userId, $locationId, $startingCash]);
    }

    public static function approveStart(int $shiftId): void
    {
        self::db()->prepare("UPDATE shifts SET status = 'open', start_time = NOW() WHERE id = ?")->execute([$shiftId]);
    }

    public static function expectedCash(int $shiftId, float $startingCash): float
    {
        $stmtCash = self::db()->prepare(
            "SELECT COALESCE(SUM(final_total), 0) FROM sales WHERE shift_id = ? AND payment_method = 'Cash' AND payment_status = 'paid'"
        );
        $stmtCash->execute([$shiftId]);
        $expected = $startingCash + (float)$stmtCash->fetchColumn();
        $stmtExp = self::db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE shift_id = ?');
        $stmtExp->execute([$shiftId]);
        $expected -= (float)$stmtExp->fetchColumn();
        // Cash tips recorded after payment go into the same drawer.
        try {
            $stmtTips = self::db()->prepare("SELECT COALESCE(SUM(amount), 0) FROM sale_tips WHERE shift_id = ? AND payment_method = 'Cash'");
            $stmtTips->execute([$shiftId]);
            $expected += (float)$stmtTips->fetchColumn();
        } catch (\Throwable $e) {
        }
        return $expected;
    }

    /**
     * Every shift that is still open, with the cash the drawer should hold.
     *
     * @return list<array<string, mixed>>
     */
    public static function openNow(): array
    {
        $rows = self::db()->query(
            "SELECT s.id, s.user_id, s.location_id, s.start_time, s.starting_cash,
                    COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) AS who, u.username, u.role,
                    l.name AS location_name
             FROM shifts s
             JOIN users u ON u.id = s.user_id
             JOIN locations l ON l.id = s.location_id
             WHERE s.status = 'open'
             ORDER BY l.name ASC, s.start_time ASC"
        )->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int)$row['id'];
            $row['user_id'] = (int)$row['user_id'];
            $row['location_id'] = (int)$row['location_id'];
            $row['expected'] = self::expectedCash($row['id'], (float)$row['starting_cash']);
            $ts = strtotime((string)$row['start_time']);
            $row['started_label'] = $ts === false
                ? ''
                : (date('Y-m-d', $ts) === date('Y-m-d') ? 'Today, ' : date('j M, ', $ts)) . date('H:i', $ts);
        }
        unset($row);
        return $rows;
    }

    /**
     * Closes one open shift without a drawer count. No variance is stored, so the
     * books do not treat the till as balanced or short. Returns who and where, or null.
     *
     * @return array{who: string, location: string}|null
     */
    public static function closeUncounted(int $shiftId, int $developerId): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT s.id, s.starting_cash, l.name AS location_name,
                    COALESCE(NULLIF(TRIM(u.full_name), ''), u.username) AS who
             FROM shifts s
             JOIN users u ON u.id = s.user_id
             JOIN locations l ON l.id = s.location_id
             WHERE s.id = ? AND s.status = 'open'"
        );
        $stmt->execute([$shiftId]);
        $shift = $stmt->fetch();
        if (!$shift) {
            return null;
        }
        $expected = self::expectedCash($shiftId, (float)$shift['starting_cash']);
        $reason = 'Closed by the developer without a drawer count.';
        $update = self::db()->prepare(
            "UPDATE shifts SET
                end_time = NOW(),
                status = 'closed',
                expected_cash = ?,
                closing_cash = NULL,
                manager_closing_cash = NULL,
                variance = NULL,
                variance_reason = ?,
                handover_notes = ?,
                end_verified_by = ?,
                end_verified_at = NOW()
             WHERE id = ? AND status = 'open'"
        );
        $update->execute([$expected, $reason, $reason, $developerId > 0 ? $developerId : null, $shiftId]);
        if ($update->rowCount() < 1) {
            return null;
        }
        return ['who' => (string)$shift['who'], 'location' => (string)$shift['location_name']];
    }

    /**
     * Closes every open shift, or only those at one workstation.
     *
     * @return list<array{who: string, location: string}>
     */
    public static function closeUncountedGroup(?int $locationId, int $developerId): array
    {
        $sql = "SELECT id FROM shifts WHERE status = 'open'";
        $params = [];
        if ($locationId !== null) {
            $sql .= ' AND location_id = ?';
            $params[] = $locationId;
        }
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $ids = array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $closed = [];
            foreach ($ids as $id) {
                $row = self::closeUncounted($id, $developerId);
                if ($row) {
                    $closed[] = $row;
                }
            }
            $pdo->commit();
            return $closed;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
