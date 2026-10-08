<?php

namespace App\Models;

/**
 * Tips given after a receipt has already been paid.
 *
 * They are stored on their own so the fiscalised sale (and its ZRA invoice)
 * is never modified. Cash tips count towards the drawer of the shift that
 * recorded them; all tips roll up to the staff member who served the table.
 */
final class Tip extends Model
{
    public const METHODS = ['Cash', 'Card', 'MTN Money', 'Airtel Money', 'Zamtel Money', 'Other'];

    /**
     * Receipts a cashier/waiter may attach a tip to.
     * Managers see every paid receipt at the location; staff see their own.
     */
    public static function searchReceipts(int $locationId, string $q, bool $isManager, int $userId, int $days = 7, int $limit = 25): array
    {
        $pdo = self::db();
        $q = trim($q);
        $sql = "SELECT s.id, s.created_at, s.customer_name, s.final_total, s.payment_method, s.user_id, s.zra_rcpt_no,
                       u.username, u.full_name,
                       COALESCE((SELECT SUM(t.amount) FROM sale_tips t WHERE t.sale_id = s.id), 0) AS tips_so_far
                FROM sales s
                LEFT JOIN users u ON u.id = s.user_id
                WHERE s.location_id = ? AND s.payment_status = 'paid' AND s.final_total > 0
                  AND s.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)";
        $params = [$locationId, $days];
        if (!$isManager) {
            $sql .= ' AND s.user_id = ?';
            $params[] = $userId;
        }
        if ($q !== '') {
            if (ctype_digit($q)) {
                $sql .= ' AND (s.id = ? OR s.zra_rcpt_no = ? OR s.final_total = ? OR s.customer_name LIKE ?)';
                array_push($params, (int)$q, (int)$q, (float)$q, '%' . $q . '%');
            } elseif (is_numeric($q)) {
                $sql .= ' AND (s.final_total = ? OR s.customer_name LIKE ?)';
                array_push($params, (float)$q, '%' . $q . '%');
            } else {
                $sql .= ' AND (s.customer_name LIKE ? OR u.username LIKE ? OR u.full_name LIKE ? OR s.payment_method LIKE ?)';
                $like = '%' . $q . '%';
                array_push($params, $like, $like, $like, $like);
            }
        }
        $sql .= ' ORDER BY s.id DESC LIMIT ' . (int)$limit;
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function record(int $saleId, float $amount, string $method, ?int $staffUserId, int $recordedBy, ?int $shiftId, int $locationId, string $note = ''): int
    {
        $pdo = self::db();
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Enter a tip amount greater than zero.');
        }
        if (!in_array($method, self::METHODS, true)) {
            $method = 'Other';
        }
        $stmt = $pdo->prepare("SELECT id, user_id, payment_status FROM sales WHERE id = ?");
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch();
        if (!$sale) {
            throw new \InvalidArgumentException('Receipt #' . $saleId . ' was not found.');
        }
        if (($sale['payment_status'] ?? '') !== 'paid') {
            throw new \InvalidArgumentException('Only paid receipts can take a tip. Settle the tab first.');
        }
        if (!$staffUserId) {
            $staffUserId = (int)$sale['user_id'] ?: null;
        }
        $pdo->prepare(
            'INSERT INTO sale_tips (sale_id, shift_id, location_id, staff_user_id, recorded_by, amount, payment_method, note)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$saleId, $shiftId, $locationId, $staffUserId, $recordedBy, round($amount, 2), $method, mb_substr(trim($note), 0, 255)]);
        return (int)$pdo->lastInsertId();
    }

    /** @return array{total: float, cash: float, by_method: array, by_staff: array, rows: array} */
    public static function forShift(int $shiftId): array
    {
        return self::summarise('t.shift_id = ?', [$shiftId]);
    }

    public static function forRange(string $start, string $end, int $locationId = 0): array
    {
        $where = 't.created_at >= ? AND t.created_at <= ?';
        $params = [$start . ' 00:00:00', $end . ' 23:59:59'];
        if ($locationId > 0) {
            $where .= ' AND t.location_id = ?';
            $params[] = $locationId;
        }
        return self::summarise($where, $params);
    }

    public static function forSale(int $saleId): array
    {
        $stmt = self::db()->prepare(
            'SELECT t.*, u.username AS staff_username FROM sale_tips t LEFT JOIN users u ON u.id = t.staff_user_id WHERE t.sale_id = ? ORDER BY t.id'
        );
        $stmt->execute([$saleId]);
        return $stmt->fetchAll();
    }

    private static function summarise(string $where, array $params): array
    {
        $empty = ['total' => 0.0, 'cash' => 0.0, 'by_method' => [], 'by_staff' => [], 'rows' => []];
        try {
            $stmt = self::db()->prepare(
                "SELECT t.*, u.username AS staff_username, u.full_name AS staff_name
                 FROM sale_tips t LEFT JOIN users u ON u.id = t.staff_user_id
                 WHERE $where ORDER BY t.id DESC"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            return $empty;
        }
        $out = $empty;
        $out['rows'] = $rows;
        foreach ($rows as $r) {
            $amt = (float)$r['amount'];
            $out['total'] += $amt;
            if (strcasecmp((string)$r['payment_method'], 'Cash') === 0) {
                $out['cash'] += $amt;
            }
            $m = (string)$r['payment_method'];
            $out['by_method'][$m] = ($out['by_method'][$m] ?? 0) + $amt;
            $staff = $r['staff_name'] ?: ($r['staff_username'] ?: 'Unassigned');
            if (!isset($out['by_staff'][$staff])) {
                $out['by_staff'][$staff] = ['amount' => 0.0, 'count' => 0];
            }
            $out['by_staff'][$staff]['amount'] += $amt;
            $out['by_staff'][$staff]['count']++;
        }
        arsort($out['by_method']);
        uasort($out['by_staff'], fn($a, $b) => $b['amount'] <=> $a['amount']);
        return $out;
    }
}
