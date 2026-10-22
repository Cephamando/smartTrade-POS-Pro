<?php

namespace App\Models;

use PDO;

final class Sale extends Model
{
    public static function dashboardKpis(int $locationId): array
    {
        $params = [];
        $locSql = '';
        if ($locationId > 0) {
            $locSql = ' AND location_id = ?';
            $params[] = $locationId;
        }

        $todaySales = self::db()->prepare(
            "SELECT SUM(final_total) FROM sales WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'" . $locSql
        );
        $todaySales->execute($params);

        $todayTransactions = self::db()->prepare(
            "SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'" . $locSql
        );
        $todayTransactions->execute($params);

        $unpaidTabs = self::db()->prepare(
            "SELECT COUNT(*) FROM sales WHERE payment_status = 'pending' AND id IN (SELECT sale_id FROM sale_items WHERE status NOT IN ('voided', 'refunded'))" . $locSql
        );
        $unpaidTabs->execute($params);

        $lowStockSql = ($locationId > 0)
            ? 'SELECT COUNT(*) FROM inventory WHERE location_id = ? AND quantity <= 5'
            : 'SELECT COUNT(*) FROM inventory WHERE quantity <= 5';
        $lowStockStmt = self::db()->prepare($lowStockSql);
        if ($locationId > 0) {
            $lowStockStmt->execute([$locationId]);
        } else {
            $lowStockStmt->execute();
        }

        $dates = [];
        $salesData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dates[] = date('D d', strtotime($date));
            $dSql = "SELECT SUM(final_total) FROM sales WHERE DATE(created_at) = ? AND payment_status = 'paid'" . $locSql;
            $dParams = array_merge([$date], $params);
            $stmt = self::db()->prepare($dSql);
            $stmt->execute($dParams);
            $salesData[] = $stmt->fetchColumn() ?: 0;
        }

        $pmSql = "SELECT payment_method, COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'" . $locSql . ' GROUP BY payment_method';
        $stmt = self::db()->prepare($pmSql);
        $stmt->execute($params);

        return [
            'todaySales' => $todaySales->fetchColumn() ?: 0.00,
            'todayTransactions' => $todayTransactions->fetchColumn() ?: 0,
            'unpaidTabs' => $unpaidTabs->fetchColumn() ?: 0,
            'lowStockCount' => $lowStockStmt->fetchColumn(),
            'dates' => $dates,
            'salesData' => $salesData,
            'pmData' => $stmt->fetchAll(\PDO::FETCH_KEY_PAIR),
        ];
    }

    public static function pendingFiscal(int $limit = 15): array
    {
        return self::db()->query(
            "SELECT id, created_at, customer_name, final_total, payment_method, COALESCE(zra_status, 'pending') AS zra_status, zra_last_error
             FROM sales
             WHERE payment_status = 'paid'
               AND (zra_rcpt_sign IS NULL OR zra_rcpt_sign = '')
               AND COALESCE(zra_status, 'pending') IN ('pending', 'failed')
               AND ABS(final_total) > 0
               AND EXISTS (
                   SELECT 1 FROM sale_items si
                   WHERE si.sale_id = sales.id AND si.status NOT IN ('voided')
               )
             ORDER BY id DESC
             LIMIT " . (int)$limit
        )->fetchAll();
    }

    public static function recentSynced(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        return self::db()->query(
            "SELECT id, created_at, customer_name, final_total, zra_rcpt_no, zra_sdc_id, zra_qr_code_url, zra_rcpt_sign
             FROM sales
             WHERE zra_rcpt_sign IS NOT NULL AND zra_rcpt_sign <> ''
             ORDER BY id DESC
             LIMIT {$limit}"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM sales WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function receiptSale(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT s.*, u.username, l.name as loc_name FROM sales s JOIN users u ON s.user_id = u.id LEFT JOIN locations l ON s.location_id = l.id WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function receiptItems(int $saleId, array $specificItems = []): array
    {
        if ($specificItems) {
            $inClause = implode(',', array_map('intval', $specificItems));
            $stmt = self::db()->prepare(
                "SELECT COALESCE(p.name, 'Custom Item') as name, si.price, SUM(si.quantity) as quantity,
                        COALESCE(p.tax_class, 'A') as tax_class
                 FROM sale_items si
                 LEFT JOIN products p ON si.product_id = p.id
                 WHERE si.sale_id = ? AND si.status != 'voided' AND si.id IN ($inClause)
                 GROUP BY p.name, si.price, p.tax_class"
            );
            $stmt->execute([$saleId]);
            return $stmt->fetchAll();
        }
        $stmt = self::db()->prepare(
            "SELECT COALESCE(p.name, 'Custom Item') as name, si.price, SUM(si.quantity) as quantity,
                    COALESCE(p.tax_class, 'A') as tax_class
             FROM sale_items si
             LEFT JOIN products p ON si.product_id = p.id
             WHERE si.sale_id = ? AND si.status != 'voided'
             GROUP BY p.name, si.price, p.tax_class"
        );
        $stmt->execute([$saleId]);
        return $stmt->fetchAll();
    }

    public static function refundableItems(int $saleId): array
    {
        return array_values((new \App\Services\Refund(self::db()))->remainingByItem($saleId));
    }

    /**
     * Paid original lines of a product that still have quantity left to credit.
     * This station's tickets are listed first so cashiers pick a real invoice
     * instead of returning against whatever ZRA last signed for the SKU.
     *
     * @return list<array<string, mixed>>
     */
    public static function refundableInvoicesForProduct(int $productId, int $locationId): array
    {
        if ($productId <= 0) {
            return [];
        }
        $stmt = self::db()->prepare(
            "SELECT *
             FROM (
                SELECT si.id AS sale_item_id, si.quantity, si.price, si.product_id,
                        s.id AS sale_id, s.created_at, s.customer_name, s.location_id,
                        s.zra_rcpt_no, s.zra_sdc_id, s.zra_rcpt_sign, s.payment_method,
                        l.name AS location_name,
                        GREATEST(
                            0,
                            si.quantity - COALESCE((
                                SELECT SUM(r.quantity)
                                FROM sale_items r
                                WHERE r.refund_sale_item_id = si.id
                                  AND r.is_refund = 1
                                  AND r.status <> 'voided'
                            ), 0)
                        ) AS remaining
                 FROM sale_items si
                 JOIN sales s ON s.id = si.sale_id
                 LEFT JOIN locations l ON l.id = s.location_id
                 WHERE si.product_id = ?
                   AND s.payment_status = 'paid'
                   AND s.final_total > 0
                   AND COALESCE(si.is_refund, 0) = 0
                   AND si.status <> 'voided'
                   AND si.price >= 0
             ) refundable
             WHERE remaining > 0.0001
             ORDER BY (location_id = ?) DESC, sale_id DESC
             LIMIT 40"
        );
        $stmt->execute([$productId, $locationId]);
        $refund = new \App\Services\Refund(self::db());
        $remainingBySale = [];
        $out = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $saleId = (int)$row['sale_id'];
            if (!isset($remainingBySale[$saleId])) {
                $remainingBySale[$saleId] = $refund->remainingByItem($saleId);
            }
            $live = $remainingBySale[$saleId][(int)$row['sale_item_id']] ?? null;
            if (!$live) {
                continue;
            }
            $out[] = [
                'sale_item_id' => (int)$row['sale_item_id'],
                'sale_id' => $saleId,
                'product_id' => (int)$row['product_id'],
                'created_at' => (string)$row['created_at'],
                'customer_name' => trim((string)($row['customer_name'] ?? '')) ?: 'Walk-in',
                'location_id' => (int)$row['location_id'],
                'location_name' => (string)($row['location_name'] ?? ''),
                'price' => abs((float)$row['price']),
                'remaining' => (float)$live['remaining'],
                'payment_method' => (string)($row['payment_method'] ?? ''),
                'zra_rcpt_no' => (int)($row['zra_rcpt_no'] ?? 0),
                'zra_signed' => trim((string)($row['zra_rcpt_sign'] ?? '')) !== '',
                'same_station' => (int)$row['location_id'] === $locationId,
            ];
        }
        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function refundableLine(int $saleItemId): ?array
    {
        if ($saleItemId <= 0) {
            return null;
        }
        $stmt = self::db()->prepare('SELECT sale_id, product_id FROM sale_items WHERE id = ? LIMIT 1');
        $stmt->execute([$saleItemId]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $available = (new \App\Services\Refund(self::db()))->remainingByItem((int)$row['sale_id']);
        return $available[$saleItemId] ?? null;
    }

    public static function saleItemsForModal(int $saleId): array
    {
        $stmt = self::db()->prepare(
            "SELECT si.*, COALESCE(p.name, 'Custom Item') as name, c.type as cat_type
             FROM sale_items si
             LEFT JOIN products p ON si.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE si.sale_id = ? AND si.status != 'voided'"
        );
        $stmt->execute([$saleId]);
        return $stmt->fetchAll();
    }

    /**
     * Paid sales for the dashboard trend, with optional item series.
     *
     * @param list<int> $productIds
     * @return array<string, mixed>
     */
    public static function salesTrend(int $locationId, string $range, string $start, string $end, array $productIds): array
    {
        $window = self::trendWindow($range, $start, $end);
        $locSql = $locationId > 0 ? ' AND s.location_id = ?' : '';
        $params = [$window['from'], $window['to']];
        if ($locationId > 0) {
            $params[] = $locationId;
        }
        $bucketSql = self::trendBucketSql($window['bucket']);
        $empty = [
            'status' => 'success',
            'range' => $window['range'],
            'from' => substr($window['from'], 0, 10),
            'to' => substr($window['to'], 0, 10),
            'labels' => array_column($window['buckets'], 'label'),
            'total' => array_fill(0, count($window['buckets']), 0),
            'series' => [],
            'choices' => [],
        ];
        try {
            $totals = self::db()->prepare(
                "SELECT {$bucketSql} AS bucket, SUM(s.final_total) AS amount
                 FROM sales s
                 WHERE s.payment_status = 'paid' AND s.created_at BETWEEN ? AND ?{$locSql}
                 GROUP BY {$bucketSql}"
            );
            $totals->execute($params);
            $totalMap = [];
            foreach ($totals->fetchAll() as $row) {
                $totalMap[(string)$row['bucket']] = round((float)$row['amount'], 2);
            }
            $choices = self::db()->prepare(
                "SELECT COALESCE(p.id, 0) AS id, COALESCE(p.name, 'Custom item') AS name,
                        SUM(si.price * si.quantity) AS amount
                 FROM sale_items si
                 JOIN sales s ON s.id = si.sale_id
                 LEFT JOIN products p ON p.id = si.product_id
                 WHERE s.payment_status = 'paid'
                   AND s.created_at BETWEEN ? AND ?
                   AND si.status NOT IN ('voided', 'refunded'){$locSql}
                 GROUP BY COALESCE(p.id, 0), COALESCE(p.name, 'Custom item')
                 ORDER BY amount DESC
                 LIMIT 40"
            );
            $choices->execute($params);
            $choiceRows = [];
            foreach ($choices->fetchAll() as $row) {
                $choiceRows[] = ['id' => (int)$row['id'], 'name' => (string)$row['name']];
            }
            $series = [];
            $wanted = array_values(array_unique(array_map('intval', $productIds)));
            $wanted = array_slice($wanted, 0, 8);
            if ($wanted !== []) {
                $marks = implode(',', array_fill(0, count($wanted), '?'));
                $itemSql = self::db()->prepare(
                    "SELECT {$bucketSql} AS bucket, COALESCE(p.id, 0) AS id, COALESCE(p.name, 'Custom item') AS name,
                            SUM(si.price * si.quantity) AS amount
                     FROM sale_items si
                     JOIN sales s ON s.id = si.sale_id
                     LEFT JOIN products p ON p.id = si.product_id
                     WHERE s.payment_status = 'paid'
                       AND s.created_at BETWEEN ? AND ?
                       AND si.status NOT IN ('voided', 'refunded')
                       AND COALESCE(p.id, 0) IN ({$marks}){$locSql}
                     GROUP BY {$bucketSql}, COALESCE(p.id, 0), COALESCE(p.name, 'Custom item')"
                );
                $itemParams = [$window['from'], $window['to']];
                foreach ($wanted as $id) {
                    $itemParams[] = $id;
                }
                if ($locationId > 0) {
                    $itemParams[] = $locationId;
                }
                $itemSql->execute($itemParams);
                $byId = [];
                foreach ($itemSql->fetchAll() as $row) {
                    $id = (int)$row['id'];
                    $byId[$id]['name'] = (string)$row['name'];
                    $byId[$id]['points'][(string)$row['bucket']] = round((float)$row['amount'], 2);
                }
                foreach ($wanted as $id) {
                    if (!isset($byId[$id])) {
                        continue;
                    }
                    $data = [];
                    foreach ($window['buckets'] as $bucket) {
                        $data[] = $byId[$id]['points'][$bucket['key']] ?? 0;
                    }
                    $series[] = ['id' => $id, 'name' => $byId[$id]['name'], 'data' => $data];
                }
            }
            $total = [];
            foreach ($window['buckets'] as $bucket) {
                $total[] = $totalMap[$bucket['key']] ?? 0;
            }
            $empty['total'] = $total;
            $empty['series'] = $series;
            $empty['choices'] = $choiceRows;
            return $empty;
        } catch (\Throwable $e) {
            $empty['status'] = 'error';
            return $empty;
        }
    }

    /** @return array{range: string, bucket: string, from: string, to: string, buckets: list<array{key: string, label: string}>} */
    private static function trendWindow(string $range, string $start, string $end): array
    {
        $range = in_array($range, ['day', 'week', 'month', 'year', 'custom'], true) ? $range : 'week';
        $today = new \DateTimeImmutable('today');
        if ($range === 'day') {
            $fromDay = $today;
            $toDay = $today;
            $bucket = 'hour';
        } elseif ($range === 'month') {
            $fromDay = $today->modify('first day of this month');
            $toDay = $today->modify('last day of this month');
            $bucket = 'day';
        } elseif ($range === 'year') {
            $fromDay = $today->setDate((int)$today->format('Y'), 1, 1);
            $toDay = $today->setDate((int)$today->format('Y'), 12, 31);
            $bucket = 'month';
        } elseif ($range === 'custom') {
            $fromDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $start) ?: $today->modify('-6 days');
            $toDay = \DateTimeImmutable::createFromFormat('!Y-m-d', $end) ?: $today;
            if ($fromDay > $toDay) {
                [$fromDay, $toDay] = [$toDay, $fromDay];
            }
            if ((int)$fromDay->diff($toDay)->days > 366) {
                $fromDay = $toDay->modify('-366 days');
            }
            $span = (int)$fromDay->diff($toDay)->days;
            $bucket = $span === 0 ? 'hour' : ($span <= 62 ? 'day' : ($span <= 180 ? 'week' : 'month'));
        } else {
            $fromDay = $today->modify('-6 days');
            $toDay = $today;
            $bucket = 'day';
            $range = 'week';
        }
        return [
            'range' => $range,
            'bucket' => $bucket,
            'from' => $fromDay->format('Y-m-d') . ' 00:00:00',
            'to' => $toDay->format('Y-m-d') . ' 23:59:59',
            'buckets' => self::trendBuckets($bucket, $fromDay, $toDay),
        ];
    }

    /** @return list<array{key: string, label: string}> */
    private static function trendBuckets(string $bucket, \DateTimeImmutable $from, \DateTimeImmutable $to): array
    {
        $out = [];
        if ($bucket === 'hour') {
            for ($hour = 0; $hour < 24; $hour++) {
                $out[] = ['key' => (string)$hour, 'label' => sprintf('%02d:00', $hour)];
            }
            return $out;
        }
        if ($bucket === 'month') {
            $cursor = $from->modify('first day of this month');
            $last = $to->modify('first day of this month');
            while ($cursor <= $last) {
                $out[] = ['key' => $cursor->format('Y-m'), 'label' => $cursor->format('M Y')];
                $cursor = $cursor->modify('+1 month');
            }
            return $out;
        }
        if ($bucket === 'week') {
            $cursor = $from->modify('-' . $from->format('N') . ' days +1 day');
            $last = $to->modify('-' . $to->format('N') . ' days +1 day');
            while ($cursor <= $last) {
                $out[] = ['key' => $cursor->format('Y-m-d'), 'label' => $cursor->format('d M')];
                $cursor = $cursor->modify('+7 days');
            }
            return $out;
        }
        $cursor = $from;
        while ($cursor <= $to) {
            $out[] = ['key' => $cursor->format('Y-m-d'), 'label' => $cursor->format('D j')];
            $cursor = $cursor->modify('+1 day');
        }
        return $out;
    }

    private static function trendBucketSql(string $bucket): string
    {
        if ($bucket === 'hour') {
            return 'HOUR(s.created_at)';
        }
        if ($bucket === 'month') {
            return "DATE_FORMAT(s.created_at, '%Y-%m')";
        }
        if ($bucket === 'week') {
            return 'DATE(DATE_SUB(s.created_at, INTERVAL WEEKDAY(s.created_at) DAY))';
        }
        return 'DATE(s.created_at)';
    }

    /** Paid online orders still on Web Orders: open tickets, and cancellations waiting for the Flutterwave payout. */
    public static function openWebOrderWhere(string $alias = 's'): string
    {
        return $alias . ".payment_method = 'Online' AND " . $alias . ".payment_status = 'paid' AND (
            " . $alias . ".status = 'pending'
            OR (
                " . $alias . ".status = 'cancelled'
                AND " . $alias . ".cancel_source IN ('customer', 'till')
                AND NOT EXISTS (
                    SELECT 1 FROM payment_transactions pt
                    WHERE pt.sale_id = " . $alias . ".id AND pt.settlement_status = 'settled'
                )
            )
        )";
    }

    /**
     * Stop a pending online order.
     * A paid order stays on Web Orders, labelled by who cancelled it, until Flutterwave pays the funds out.
     *
     * @return array{paid: bool, source: string, note: string}
     */
    public static function cancelWebOrder(int $saleId, ?int $customerId, bool $allowPaid, int $staffUserId = 0): array
    {
        if ($saleId <= 0) {
            throw new \RuntimeException('That order was not found.');
        }
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT id, customer_id, location_id, user_id, status, payment_status, payment_method FROM sales WHERE id = ? FOR UPDATE");
            $stmt->execute([$saleId]);
            $sale = $stmt->fetch();
            if (!$sale || ($sale['payment_method'] ?? '') !== 'Online') {
                throw new \RuntimeException('That order was not found.');
            }
            if ($customerId !== null && (int)$sale['customer_id'] !== $customerId) {
                throw new \RuntimeException('That order was not found.');
            }
            if (($sale['status'] ?? '') !== 'pending') {
                throw new \RuntimeException('This order can no longer be cancelled.');
            }
            $paid = ($sale['payment_status'] ?? '') === 'paid';
            if ($paid && !$allowPaid) {
                throw new \RuntimeException('This order is already paid. An approval code is required to cancel it.');
            }
            if (!in_array((string)($sale['payment_status'] ?? ''), ['pending', 'paid'], true)) {
                throw new \RuntimeException('This order can no longer be cancelled.');
            }
            $source = $customerId !== null ? 'customer' : 'till';
            if ($paid) {
                $pdo->prepare("UPDATE sale_items SET status = 'cancelled', fulfillment_status = 'voided' WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')")->execute([$saleId]);
                $pdo->prepare("UPDATE sales SET status = 'cancelled', cancel_source = ? WHERE id = ? AND status = 'pending'")->execute([$source, $saleId]);
            } else {
                $items = $pdo->prepare("SELECT product_id, quantity, status FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')");
                $items->execute([$saleId]);
                $actor = (int)($sale['user_id'] ?? 0) ?: 1;
                foreach ($items->fetchAll() as $line) {
                    if (($line['status'] ?? '') === 'held') {
                        continue;
                    }
                    deductStock($pdo, (int)$line['product_id'], -1 * (float)$line['quantity'], (int)$sale['location_id'], $actor, 'void_return');
                }
                $pdo->prepare("UPDATE sale_items SET status = 'voided', fulfillment_status = 'voided' WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')")->execute([$saleId]);
                $pdo->prepare("UPDATE sales SET status = 'cancelled', cancel_source = ?, payment_status = 'voided' WHERE id = ? AND status = 'pending'")->execute([$source, $saleId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $note = '';
        if ($paid) {
            $actor = $staffUserId > 0 ? $staffUserId : ((int)($sale['user_id'] ?? 0) ?: 1);
            $note = self::issueWebCreditNote($pdo, $saleId, $actor, $source);
        }
        return ['paid' => $paid, 'source' => $source, 'note' => $note];
    }

    /** File the ZRA credit note for a paid web order that has already been cancelled. */
    public static function issueWebCreditNote(PDO $pdo, int $saleId, int $userId, string $source = ''): string
    {
        $stmt = $pdo->prepare('SELECT id, status, payment_status, cancel_source, credit_note_id, final_total FROM sales WHERE id = ?');
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch();
        if (!$sale || ($sale['payment_status'] ?? '') !== 'paid' || ($sale['status'] ?? '') !== 'cancelled') {
            return '';
        }
        if ((int)($sale['credit_note_id'] ?? 0) > 0) {
            return 'Credit note #' . (int)$sale['credit_note_id'] . ' is already on file.';
        }
        $who = $source !== '' ? $source : (string)($sale['cancel_source'] ?? 'till');
        $reason = $who === 'customer' ? 'Cancelled by customer' : 'Cancelled by till';
        try {
            $refund = new \App\Services\Refund($pdo);
            $result = $refund->refundAll($saleId, $userId > 0 ? $userId : 1, $reason, '07', true);
            $creditId = (int)($result['credit_sale_id'] ?? 0);
            if ($creditId > 0) {
                $pdo->prepare('UPDATE sales SET credit_note_id = ? WHERE id = ? AND credit_note_id IS NULL')->execute([$creditId, $saleId]);
            }
            $posted = trim((string)($result['zra_msg'] ?? ''));
            return 'Credit note #' . $creditId . ' filed.' . ($posted !== '' ? ' ' . $posted : '');
        } catch (\Throwable $e) {
            return 'The ZRA credit note was not filed: ' . $e->getMessage();
        }
    }

    /** Record a successful online payment once, then release the kitchen ticket or the table hold. */
    public static function markOnlinePaid(PDO $pdo, int $saleId, string $txRef): bool
    {
        $upd = $pdo->prepare(
            "UPDATE sales SET payment_status = 'paid', amount_tendered = final_total, change_due = 0, gateway_ref = ?
             WHERE id = ? AND status = 'pending' AND payment_status <> 'paid'"
        );
        $upd->execute([$txRef, $saleId]);
        if ($upd->rowCount() !== 1) {
            return false;
        }
        self::ensureOnlineReleased($pdo, $saleId);
        return true;
    }

    /** Send a paid online order to the kitchen, or start a reservation hold. Safe to call again. */
    public static function ensureOnlineReleased(PDO $pdo, int $saleId): void
    {
        $saleStmt = $pdo->prepare('SELECT id, location_id, user_id, order_type, status, payment_status FROM sales WHERE id = ?');
        $saleStmt->execute([$saleId]);
        $sale = $saleStmt->fetch();
        if (!$sale || ($sale['status'] ?? '') !== 'pending' || ($sale['payment_status'] ?? '') !== 'paid') {
            return;
        }
        $actor = (int)($sale['user_id'] ?? 0) ?: 1;
        $lines = $pdo->prepare(
            "SELECT si.id, si.product_id, si.quantity, c.type AS cat_type
             FROM sale_items si
             LEFT JOIN products p ON p.id = si.product_id
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE si.sale_id = ? AND si.status = 'held'"
        );
        $lines->execute([$saleId]);
        $kitchen = false;
        $released = 0;
        foreach ($lines->fetchAll() as $line) {
            deductStock($pdo, (int)$line['product_id'], (float)$line['quantity'], (int)$sale['location_id'], $actor, 'online_sale');
            $cat = strtolower((string)($line['cat_type'] ?? ''));
            $next = (str_contains($cat, 'food') || str_contains($cat, 'meal') || str_contains($cat, 'kitchen')) ? 'pending' : 'ready';
            if ($next === 'pending') {
                $kitchen = true;
            }
            $pdo->prepare("UPDATE sale_items SET status = ? WHERE id = ? AND status = 'held'")->execute([$next, (int)$line['id']]);
            $released++;
        }
        if (($sale['order_type'] ?? '') === 'reservation') {
            $minutes = \App\Controllers\TablesController::reserveMinutes(Setting::all());
            $pdo->prepare('UPDATE sales SET reserve_until = DATE_ADD(NOW(), INTERVAL ' . $minutes . ' MINUTE) WHERE id = ? AND reserve_until IS NULL')->execute([$saleId]);
            return;
        }
        if ($released > 0 && !$kitchen) {
            \App\Services\OrderWebhook::notify($pdo, $saleId, 'ready');
        }
    }
}
