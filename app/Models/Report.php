<?php

namespace App\Models;

final class Report extends Model
{
    public static function metrics(string $start, string $end, $locationId): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId);
        $stmt = self::db()->prepare(
            "SELECT SUM(final_total) as total_revenue, SUM(tip_amount) as total_tips, COUNT(*) as trans_count, AVG(final_total) as avg_ticket
             FROM sales s WHERE created_at BETWEEN ? AND ?" . $locSql . " AND payment_status = 'paid'"
        );
        $stmt->execute($params);
        return $stmt->fetch() ?: [];
    }

    public static function refundStats(string $start, string $end, $locationId): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId);
        $stmt = self::db()->prepare(
            "SELECT SUM(ABS(final_total)) as total_refunded, COUNT(*) as refund_count
             FROM sales s WHERE created_at BETWEEN ? AND ?" . $locSql . " AND final_total < 0"
        );
        $stmt->execute($params);
        return $stmt->fetch() ?: [];
    }

    public static function voidStats(string $start, string $end, $locationId): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId);
        $stmt = self::db()->prepare(
            "SELECT SUM(si.price * si.quantity) as total_voided, COUNT(si.id) as void_count
             FROM sale_items si JOIN sales s ON si.sale_id = s.id
             WHERE s.created_at BETWEEN ? AND ?" . $locSql . " AND si.status = 'voided'"
        );
        $stmt->execute($params);
        return $stmt->fetch() ?: [];
    }

    public static function salesRows(string $start, string $end, $locationId): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId);
        $stmt = self::db()->prepare(
            "SELECT s.*, u.username as cashier, l.name as location
             FROM sales s
             LEFT JOIN users u ON s.user_id = u.id
             LEFT JOIN locations l ON s.location_id = l.id
             WHERE s.created_at BETWEEN ? AND ? $locSql
             ORDER BY s.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function itemizedRows(string $start, string $end, $locationId): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId);
        $stmt = self::db()->prepare(
            "SELECT si.id as item_id, s.id as sale_id, s.created_at, COALESCE(p.name, 'Custom Item') as product_name,
                    si.quantity, si.price, (si.price * si.quantity) as line_total,
                    s.payment_status, l.name as location_name, u.username as cashier, si.status as item_status
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             LEFT JOIN products p ON si.product_id = p.id
             LEFT JOIN locations l ON s.location_id = l.id
             LEFT JOIN users u ON s.user_id = u.id
             WHERE s.created_at BETWEEN ? AND ? $locSql
             ORDER BY s.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function productRows(string $start, string $end, $locationId): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId);
        $stmt = self::db()->prepare(
            "SELECT p.name, p.sku, SUM(si.quantity) as qty_sold, SUM(si.price * si.quantity) as revenue
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             JOIN products p ON si.product_id = p.id
             WHERE s.created_at BETWEEN ? AND ? $locSql AND s.payment_status = 'paid' AND si.status NOT IN ('voided', 'refunded')
             GROUP BY p.id ORDER BY qty_sold DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function auditRows(string $start, string $end, $locationId): array
    {
        $params = [$start . ' 00:00:00', $end . ' 23:59:59'];
        $locSql = ($locationId) ? ' AND il.location_id = ?' : '';
        if ($locationId) {
            $params[] = $locationId;
        }
        $stmt = self::db()->prepare(
            "SELECT il.*, COALESCE(p.name, 'Unknown Product') as product_name, COALESCE(u.username, 'Unknown User') as username,
                    s.id as sale_id, s.payment_status
             FROM inventory_logs il
             LEFT JOIN products p ON il.product_id = p.id
             LEFT JOIN users u ON il.user_id = u.id
             LEFT JOIN sales s ON il.action_type IN ('sale','refund') AND s.id = il.reference_id
             WHERE il.created_at BETWEEN ? AND ? $locSql
             ORDER BY il.created_at DESC"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function consumption(string $startDate, string $endDate, int $locId): array
    {
        $locQuery = $locId > 0 ? 'AND il.location_id = ' . $locId : '';
        $stmt = self::db()->prepare(
            "SELECT p.name as ingredient_name, l.name as location_name, SUM(ABS(il.change_qty)) as total_consumed
             FROM inventory_logs il
             JOIN products p ON il.product_id = p.id
             JOIN locations l ON il.location_id = l.id
             WHERE il.action_type = 'recipe_deduction'
             AND DATE(il.created_at) >= ? AND DATE(il.created_at) <= ?
             $locQuery
             GROUP BY p.id, l.id
             ORDER BY total_consumed DESC"
        );
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll();
    }

    public static function zRead(string $startDateTime, string $endDateTime, $locationId): array
    {
        $pdo = self::db();
        $params = [$startDateTime, $endDateTime, $locationId];

        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(r.amount_refunded), 0) FROM refunds r JOIN sales s ON r.sale_id = s.id WHERE r.created_at >= ? AND r.created_at <= ? AND s.location_id = ?'
        );
        $stmt->execute($params);
        $tableRefunds = $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COALESCE(SUM(ABS(final_total)), 0) FROM sales WHERE created_at >= ? AND created_at <= ? AND location_id = ? AND final_total < 0 AND payment_status = 'paid'"
        );
        $stmt->execute($params);
        $posRefunds = $stmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT COALESCE(p.name, 'Custom Item') as name, SUM(si.quantity) as qty, SUM(si.price * si.quantity) as amount
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             LEFT JOIN products p ON si.product_id = p.id
             WHERE s.created_at >= ? AND s.created_at <= ? AND s.location_id = ? AND s.payment_status = 'paid' AND si.status NOT IN ('voided', 'refunded')
             GROUP BY p.id, p.name
             ORDER BY qty DESC"
        );
        $stmt->execute($params);
        $productBreakdown = $stmt->fetchAll();
        $grossItemSales = array_sum(array_column($productBreakdown, 'amount'));

        $stmt = $pdo->prepare(
            "SELECT COUNT(id) as total_transactions, SUM(subtotal) as net_sales, SUM(tip_amount) as total_tips, SUM(final_total) as total_collected
             FROM sales WHERE created_at >= ? AND created_at <= ? AND location_id = ? AND payment_status = 'paid'"
        );
        $stmt->execute($params);
        $metrics = $stmt->fetch();

        $stmt = $pdo->prepare(
            'SELECT payment_method, final_total, change_due, split_method_1, split_amount_1, split_method_2, split_amount_2, split_payments
             FROM sales WHERE created_at >= ? AND created_at <= ? AND location_id = ? AND payment_status = \'paid\''
        );
        $stmt->execute($params);
        $salesData = $stmt->fetchAll();

        $pmAssoc = [];
        foreach ($salesData as $s) {
            if ($s['payment_method'] === 'Split') {
                $legs = applySplitChangeToCash(saleSplitLegs($s), (float)($s['change_due'] ?? 0));
                foreach ($legs as $leg) {
                    $m = $leg['method'];
                    if (!isset($pmAssoc[$m])) {
                        $pmAssoc[$m] = ['payment_method' => $m, 'amount' => 0, 'count' => 0];
                    }
                    $pmAssoc[$m]['amount'] += (float)$leg['amount'];
                    $pmAssoc[$m]['count'] += 1 / max(1, count($legs));
                }
            } else {
                $pm = $s['payment_method'];
                if (!isset($pmAssoc[$pm])) {
                    $pmAssoc[$pm] = ['payment_method' => $pm, 'amount' => 0, 'count' => 0];
                }
                $pmAssoc[$pm]['amount'] += (float)$s['final_total'];
                $pmAssoc[$pm]['count'] += 1;
            }
        }
        $paymentMethods = array_values($pmAssoc);
        foreach ($paymentMethods as &$pmData) {
            $pmData['count'] = ceil($pmData['count']);
        }
        unset($pmData);

        $stmt = $pdo->prepare(
            "SELECT c.name as category_name, SUM(si.quantity) as qty, SUM(si.price * si.quantity) as amount
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             LEFT JOIN products p ON si.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE s.created_at >= ? AND s.created_at <= ? AND s.location_id = ? AND s.payment_status = 'paid' AND si.status NOT IN ('voided', 'refunded')
             GROUP BY c.id
             ORDER BY amount DESC"
        );
        $stmt->execute($params);
        $categoriesBreakdown = $stmt->fetchAll();

        $stmt = $pdo->prepare(
            'SELECT COALESCE(SUM(amount), 0) as total_expenses, COUNT(id) as expense_count FROM expenses WHERE created_at >= ? AND created_at <= ? AND location_id = ?'
        );
        $stmt->execute($params);
        $expenses = $stmt->fetch();

        return [
            'tableRefunds' => $tableRefunds,
            'posRefunds' => $posRefunds,
            'totalRefunded' => $tableRefunds + $posRefunds,
            'productBreakdown' => $productBreakdown,
            'grossItemSales' => $grossItemSales,
            'metrics' => $metrics,
            'totalDiscounts' => max(0, $grossItemSales - (float)($metrics['net_sales'] ?? 0)),
            'paymentMethods' => $paymentMethods,
            'categoriesBreakdown' => $categoriesBreakdown,
            'expenses' => $expenses,
        ];
    }

    public static function printShift(int $shiftId): array
    {
        ensureSaleSplitPaymentsColumn(self::db());
        $stmt = self::db()->prepare(
            'SELECT payment_method, final_total, tip_amount, change_due, split_method_1, split_amount_1, split_method_2, split_amount_2, split_payments
             FROM sales WHERE shift_id = ? AND payment_status = \'paid\''
        );
        $stmt->execute([$shiftId]);
        $sales = $stmt->fetchAll();

        $paymentBreakdownAssoc = [];
        $grandTotal = 0;
        $cashSales = 0;
        foreach ($sales as $s) {
            $grandTotal += $s['final_total'];
            if ($s['payment_method'] === 'Split') {
                $legs = applySplitChangeToCash(saleSplitLegs($s), (float)($s['change_due'] ?? 0));
                $n = max(1, count($legs));
                foreach ($legs as $leg) {
                    $m = $leg['method'];
                    if (!isset($paymentBreakdownAssoc[$m])) {
                        $paymentBreakdownAssoc[$m] = ['payment_method' => $m, 'tx_count' => 0, 'total' => 0, 'total_tips' => 0];
                    }
                    $paymentBreakdownAssoc[$m]['total'] += (float)$leg['amount'];
                    $paymentBreakdownAssoc[$m]['tx_count'] += 1 / $n;
                    if (strcasecmp($m, 'Cash') === 0) {
                        $cashSales += (float)$leg['amount'];
                    }
                }
            } else {
                $pm = $s['payment_method'];
                if (!isset($paymentBreakdownAssoc[$pm])) {
                    $paymentBreakdownAssoc[$pm] = ['payment_method' => $pm, 'tx_count' => 0, 'total' => 0, 'total_tips' => 0];
                }
                $paymentBreakdownAssoc[$pm]['total'] += $s['final_total'];
                $paymentBreakdownAssoc[$pm]['total_tips'] += $s['tip_amount'];
                $paymentBreakdownAssoc[$pm]['tx_count'] += 1;
                if ($pm === 'Cash') {
                    $cashSales += $s['final_total'];
                }
            }
        }
        $paymentBreakdown = array_values($paymentBreakdownAssoc);
        foreach ($paymentBreakdown as &$pb) {
            $pb['tx_count'] = ceil($pb['tx_count']);
        }
        unset($pb);

        $expenses = [];
        $totalExpenses = 0;
        try {
            $stmt = self::db()->prepare('SELECT * FROM expenses WHERE shift_id = ?');
            $stmt->execute([$shiftId]);
            $expenses = $stmt->fetchAll();
            foreach ($expenses as $ex) {
                $totalExpenses += $ex['amount'];
            }
        } catch (\Throwable $e) {
        }

        $stmt = self::db()->prepare(
            "SELECT c.name as category_name, SUM(si.quantity) as qty, SUM(si.price * si.quantity) as amount
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             LEFT JOIN products p ON si.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE s.shift_id = ? AND s.payment_status = 'paid' AND si.status NOT IN ('voided', 'refunded')
             GROUP BY c.id
             ORDER BY amount DESC"
        );
        $stmt->execute([$shiftId]);
        $categoriesBreakdown = $stmt->fetchAll();

        $stmt = self::db()->prepare(
            "SELECT COALESCE(p.name, 'Custom Item') as name, SUM(si.quantity) as qty, SUM(si.price * si.quantity) as amount
             FROM sale_items si
             JOIN sales s ON si.sale_id = s.id
             LEFT JOIN products p ON si.product_id = p.id
             WHERE s.shift_id = ? AND s.payment_status = 'paid' AND si.status NOT IN ('voided', 'refunded')
             GROUP BY p.id, p.name
             ORDER BY qty DESC"
        );
        $stmt->execute([$shiftId]);

        return [
            'paymentBreakdown' => $paymentBreakdown,
            'grandTotal' => $grandTotal,
            'cashSales' => $cashSales,
            'expenses' => $expenses,
            'totalExpenses' => $totalExpenses,
            'categoriesBreakdown' => $categoriesBreakdown,
            'productBreakdown' => $stmt->fetchAll(),
        ];
    }

    private static function rangeParams(string $start, string $end, $locationId): array
    {
        $params = [$start . ' 00:00:00', $end . ' 23:59:59'];
        $locSql = ($locationId) ? ' AND s.location_id = ?' : '';
        if ($locationId) {
            $params[] = $locationId;
        }
        return [$params, $locSql];
    }
}
