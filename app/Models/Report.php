<?php

namespace App\Models;

final class Report extends Model
{
    public static function metrics(string $start, string $end, $locationId, string $daypart = ''): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId, $daypart);
        $stmt = self::db()->prepare(
            "SELECT SUM(final_total) as total_revenue, SUM(tip_amount) as total_tips, COUNT(*) as trans_count, AVG(final_total) as avg_ticket
             FROM sales s WHERE created_at BETWEEN ? AND ?" . $locSql . " AND payment_status = 'paid'"
        );
        $stmt->execute($params);
        return $stmt->fetch() ?: [];
    }

    /**
     * Tips per staff member (on the receipt + after payment) and surcharges by reason.
     * @return array{staff: array, surcharges: array, totals: array}
     */
    public static function tipsAndSurcharges(string $start, string $end, $locationId, string $daypart = ''): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId, $daypart);
        $staff = [];
        $stmt = self::db()->prepare(
            "SELECT s.user_id, COALESCE(u.full_name, u.username, 'Unknown') AS staff, COUNT(*) AS receipts,
                    COALESCE(SUM(s.tip_amount), 0) AS receipt_tips
             FROM sales s LEFT JOIN users u ON u.id = s.user_id
             WHERE s.created_at BETWEEN ? AND ?" . $locSql . " AND s.payment_status = 'paid'
             GROUP BY s.user_id, u.full_name, u.username"
        );
        $stmt->execute($params);
        foreach ($stmt->fetchAll() as $r) {
            $staff[(int)$r['user_id']] = ['staff' => $r['staff'], 'receipts' => (int)$r['receipts'], 'receipt_tips' => (float)$r['receipt_tips'], 'post_tips' => 0.0, 'post_count' => 0, 'post_cash' => 0.0];
        }
        $tips = Tip::forRange(substr($start, 0, 10), substr($end, 0, 10), (int)$locationId);
        foreach ($tips['rows'] as $t) {
            $uid = (int)($t['staff_user_id'] ?? 0);
            if (!isset($staff[$uid])) {
                $staff[$uid] = ['staff' => $t['staff_name'] ?? ($t['staff_username'] ?? 'Unassigned'), 'receipts' => 0, 'receipt_tips' => 0.0, 'post_tips' => 0.0, 'post_count' => 0, 'post_cash' => 0.0];
            }
            $staff[$uid]['post_tips'] += (float)$t['amount'];
            $staff[$uid]['post_count']++;
            if (strtolower((string)$t['payment_method']) === 'cash') {
                $staff[$uid]['post_cash'] += (float)$t['amount'];
            }
        }
        foreach ($staff as &$row) {
            $row['total'] = $row['receipt_tips'] + $row['post_tips'];
        }
        unset($row);
        usort($staff, fn($a, $b) => $b['total'] <=> $a['total']);

        $sc = self::db()->prepare(
            "SELECT COALESCE(NULLIF(surcharge_reason, ''), 'Unspecified') AS reason, COUNT(*) AS cnt, SUM(surcharge_amount) AS total
             FROM sales s WHERE created_at BETWEEN ? AND ?" . $locSql . " AND payment_status = 'paid' AND surcharge_amount > 0
             GROUP BY reason ORDER BY total DESC"
        );
        $sc->execute($params);
        $surcharges = $sc->fetchAll();

        return [
            'staff' => $staff,
            'surcharges' => $surcharges,
            'totals' => [
                'receipt_tips' => array_sum(array_column($staff, 'receipt_tips')),
                'post_tips' => $tips['total'],
                'post_cash' => $tips['cash'],
                'surcharges' => array_sum(array_map(fn($r) => (float)$r['total'], $surcharges)),
                'surcharge_count' => array_sum(array_map(fn($r) => (int)$r['cnt'], $surcharges)),
            ],
        ];
    }

    public static function refundStats(string $start, string $end, $locationId, string $daypart = ''): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId, $daypart);
        $stmt = self::db()->prepare(
            "SELECT SUM(ABS(final_total)) as total_refunded, COUNT(*) as refund_count
             FROM sales s WHERE created_at BETWEEN ? AND ?" . $locSql . " AND final_total < 0"
        );
        $stmt->execute($params);
        return $stmt->fetch() ?: [];
    }

    public static function voidStats(string $start, string $end, $locationId, string $daypart = ''): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId, $daypart);
        $stmt = self::db()->prepare(
            "SELECT SUM(si.price * si.quantity) as total_voided, COUNT(si.id) as void_count
             FROM sale_items si JOIN sales s ON si.sale_id = s.id
             WHERE s.created_at BETWEEN ? AND ?" . $locSql . " AND si.status = 'voided'"
        );
        $stmt->execute($params);
        return $stmt->fetch() ?: [];
    }

    public static function salesRows(string $start, string $end, $locationId, string $daypart = ''): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId, $daypart);
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

    public static function itemizedRows(string $start, string $end, $locationId, string $daypart = ''): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId, $daypart);
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

    public static function productRows(string $start, string $end, $locationId, string $daypart = ''): array
    {
        [$params, $locSql] = self::rangeParams($start, $end, $locationId, $daypart);
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
            "SELECT COUNT(id) as total_transactions, SUM(subtotal) as net_sales, SUM(tip_amount) as total_tips, SUM(final_total) as total_collected,
                    COALESCE(SUM(surcharge_amount), 0) as total_surcharges
             FROM sales WHERE created_at >= ? AND created_at <= ? AND location_id = ? AND payment_status = 'paid'"
        );
        $stmt->execute($params);
        $metrics = $stmt->fetch();
        $postTips = Tip::forRange(substr((string)$params[0], 0, 10), substr((string)$params[1], 0, 10), (int)$params[2]);
        $metrics['post_tips'] = $postTips['total'];
        $metrics['post_tips_cash'] = $postTips['cash'];

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
            'postTips' => $postTips,
            'gatewayPayments' => self::gatewayPayments('s.created_at >= ? AND s.created_at <= ? AND s.location_id = ? AND s.payment_status = \'paid\'', $params),
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

        $productBreakdown = $stmt->fetchAll();

        $surcharges = ['total' => 0.0, 'count' => 0, 'by_reason' => []];
        try {
            $sc = self::db()->prepare(
                "SELECT COALESCE(surcharge_reason, 'Surcharge') AS reason, SUM(surcharge_amount) AS amount, COUNT(*) AS cnt
                 FROM sales WHERE shift_id = ? AND payment_status = 'paid' AND surcharge_amount > 0 GROUP BY surcharge_reason ORDER BY amount DESC"
            );
            $sc->execute([$shiftId]);
            foreach ($sc->fetchAll() as $row) {
                $surcharges['by_reason'][] = $row;
                $surcharges['total'] += (float)$row['amount'];
                $surcharges['count'] += (int)$row['cnt'];
            }
        } catch (\Throwable $e) {
        }

        return [
            'paymentBreakdown' => $paymentBreakdown,
            'grandTotal' => $grandTotal,
            'cashSales' => $cashSales,
            'expenses' => $expenses,
            'totalExpenses' => $totalExpenses,
            'categoriesBreakdown' => $categoriesBreakdown,
            'productBreakdown' => $productBreakdown,
            'postTips' => Tip::forShift($shiftId),
            'surcharges' => $surcharges,
            'gatewayPayments' => self::gatewayPayments('s.shift_id = ? AND s.payment_status = \'paid\'', [$shiftId]),
        ];
    }

    /** @param list<mixed> $params @return list<array<string, mixed>> */
    private static function gatewayPayments(string $whereSql, array $params): array
    {
        try {
            $stmt = self::db()->prepare(
                "SELECT pt.*, s.id AS sale_id
                 FROM payment_transactions pt
                 JOIN sales s ON s.id = pt.sale_id
                 WHERE pt.provider = 'flutterwave' AND pt.status = 'successful' AND $whereSql
                 ORDER BY s.id, pt.id DESC"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        $seen = [];
        foreach ($rows as $row) {
            $saleId = (int)$row['sale_id'];
            if (isset($seen[$saleId])) {
                continue;
            }
            $detail = \App\Services\Flutterwave::present($row);
            if ($detail === null) {
                continue;
            }
            $seen[$saleId] = true;
            $detail['sale_id'] = $saleId;
            $detail['amount'] = (float)$row['amount'];
            $out[] = $detail;
        }
        return $out;
    }

    /**
     * Every money movement in the period: sales, refunds, events, expenses,
     * paid payroll, goods received, and card or mobile-money fees.
     * A Flutterwave transfer is listed but not subtracted again, because the sale was already counted.
     *
     * @return array{rows: list<array<string, mixed>>, totals: array<string, float>}
     */
    public static function financial(string $start, string $end, $locationId, int $employeeId = 0): array
    {
        $from = $start . ' 00:00:00';
        $to = $end . ' 23:59:59';
        $loc = (int)$locationId;
        $emp = $employeeId > 0 ? $employeeId : 0;
        $rows = array_merge(
            self::financeSales($from, $to, $loc, $emp),
            self::financeEvents($from, $to, $loc, $emp),
            self::financeExpenses($from, $to, $loc, $emp),
            self::financePayroll($start, $end, $loc, $emp),
            self::financePurchases($from, $to, $loc, $emp),
            self::financeFees($from, $to, $loc, $emp),
            self::financeTransfers($from, $to, $loc, $emp)
        );
        usort($rows, fn($a, $b) => strcmp((string)$b['at'], (string)$a['at']));
        $totals = [
            'in' => 0.0, 'out' => 0.0, 'sales' => 0.0, 'refunds' => 0.0, 'events' => 0.0,
            'discounts' => 0.0, 'tips' => 0.0, 'surcharges' => 0.0, 'tax' => 0.0,
            'expenses' => 0.0, 'payroll' => 0.0, 'statutory' => 0.0, 'purchases' => 0.0,
            'fees' => 0.0, 'transfers' => 0.0,
        ];
        foreach ($rows as $row) {
            $totals[$row['bucket']] = ($totals[$row['bucket']] ?? 0) + (float)$row['amount'];
            if (!empty($row['counts'])) {
                if ($row['direction'] === 'in') {
                    $totals['in'] += (float)$row['amount'];
                } else {
                    $totals['out'] += (float)$row['amount'];
                }
            }
            $totals['discounts'] += (float)($row['discount'] ?? 0);
            $totals['tips'] += (float)($row['tip'] ?? 0);
            $totals['surcharges'] += (float)($row['surcharge'] ?? 0);
            $totals['tax'] += (float)($row['tax'] ?? 0);
            $totals['statutory'] += (float)($row['statutory'] ?? 0);
        }
        $totals['result'] = round($totals['in'] - $totals['out'], 2);
        foreach ($totals as $key => $value) {
            $totals[$key] = round((float)$value, 2);
        }
        return ['rows' => $rows, 'totals' => $totals];
    }

    /**
     * VAT is separate. These are payroll amounts on paid runs whose pay date falls in the period.
     * They are not Smart Invoice documents.
     *
     * @return array{rows: list<array<string, mixed>>, total: float}
     */
    public static function statutory(string $start, string $end, $locationId, int $employeeId = 0): array
    {
        $sql = "SELECT p.id AS slip_id, p.run_id, e.full_name, r.title,
                       COALESCE(r.pay_date, DATE(r.paid_at)) AS pay_date,
                       p.paye, p.napsa_employee, p.napsa_employer, p.nhima_employee, p.nhima_employer
                FROM payslips p
                JOIN payroll_runs r ON r.id = p.run_id
                JOIN employees e ON e.id = p.employee_id
                WHERE r.status = 'paid'
                  AND COALESCE(r.pay_date, DATE(r.paid_at)) BETWEEN ? AND ?";
        $params = [$start, $end];
        if ((int)$locationId > 0) {
            $sql .= ' AND e.location_id = ?';
            $params[] = (int)$locationId;
        }
        if ($employeeId > 0) {
            $sql .= ' AND e.user_id = ?';
            $params[] = $employeeId;
        }
        $sql .= ' ORDER BY pay_date DESC, e.full_name ASC';
        try {
            $stmt = self::db()->prepare($sql);
            $stmt->execute($params);
            $slips = $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            $slips = [];
        }

        $settings = Setting::all();
        $napsaRate = self::percentText((float)($settings['payroll_napsa_rate'] ?? 5));
        $nhimaRate = self::percentText((float)($settings['payroll_nhima_rate'] ?? 1));
        $groups = [
            'paye' => ['PAYE', 'ZRA employer return', 'Deducted from the worker', ['paye']],
            'napsa' => ['NAPSA', 'NAPSA', $napsaRate . '% of gross, worker and business', ['napsa_employee', 'napsa_employer']],
            'nhima' => ['NHIMA', 'NHIMA', $nhimaRate . '% of basic, worker and business', ['nhima_employee', 'nhima_employer']],
        ];
        $rows = [];
        foreach ($groups as $key => [$name, $office, $side, $parts]) {
            $amount = 0.0;
            $count = 0;
            foreach ($slips as $slip) {
                $value = 0.0;
                foreach ($parts as $part) {
                    $value += (float)($slip[$part] ?? 0);
                }
                if ($value == 0.0) {
                    continue;
                }
                $amount += $value;
                $count++;
            }
            $rows[] = [
                'key' => $key,
                'name' => $name,
                'office' => $office,
                'side' => $side,
                'amount' => round($amount, 2),
                'slips' => $count,
            ];
        }
        return [
            'rows' => $rows,
            'total' => round(array_sum(array_column($rows, 'amount')), 2),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function statutoryLines(string $start, string $end, $locationId, int $employeeId, string $key): array
    {
        $parts = [
            'paye' => ['paye'],
            'napsa' => ['napsa_employee', 'napsa_employer'],
            'nhima' => ['nhima_employee', 'nhima_employer'],
        ][$key] ?? null;
        if ($parts === null) {
            return [];
        }
        $select = implode(', ', array_map(static fn(string $col): string => 'p.' . $col, $parts));
        $either = implode(' OR ', array_map(static fn(string $col): string => 'p.' . $col . ' <> 0', $parts));
        $sql = "SELECT p.id AS slip_id, p.run_id, e.full_name, r.title,
                       COALESCE(r.pay_date, DATE(r.paid_at)) AS pay_date,
                       {$select}
                FROM payslips p
                JOIN payroll_runs r ON r.id = p.run_id
                JOIN employees e ON e.id = p.employee_id
                WHERE r.status = 'paid'
                  AND COALESCE(r.pay_date, DATE(r.paid_at)) BETWEEN ? AND ?
                  AND ({$either})";
        $params = [$start, $end];
        if ((int)$locationId > 0) {
            $sql .= ' AND e.location_id = ?';
            $params[] = (int)$locationId;
        }
        if ($employeeId > 0) {
            $sql .= ' AND e.user_id = ?';
            $params[] = $employeeId;
        }
        $sql .= ' ORDER BY pay_date DESC, e.full_name ASC';
        try {
            $stmt = self::db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function percentText(float $rate): string
    {
        $text = rtrim(rtrim(number_format($rate, 2, '.', ''), '0'), '.');
        return $text === '' ? '0' : $text;
    }

    /** @return list<array<string, mixed>> */
    private static function financeSales(string $from, string $to, int $loc, int $emp): array
    {
        $sql = "SELECT s.id, s.created_at, s.final_total, s.discount, s.total_tax, s.tip_amount, s.surcharge_amount,
                       s.payment_method, s.payment_status, s.customer_name, l.name AS location_name, u.username AS cashier
                FROM sales s
                LEFT JOIN locations l ON l.id = s.location_id
                LEFT JOIN users u ON u.id = s.user_id
                WHERE s.created_at BETWEEN ? AND ?
                  AND (s.payment_status = 'paid' OR s.final_total < 0 OR s.payment_status = 'refunded')";
        $params = [$from, $to];
        if ($loc > 0) {
            $sql .= ' AND s.location_id = ?';
            $params[] = $loc;
        }
        if ($emp > 0) {
            $sql .= ' AND s.user_id = ?';
            $params[] = $emp;
        }
        $out = [];
        foreach (self::financeFetch($sql, $params) as $s) {
            $amount = (float)$s['final_total'];
            $refund = $amount < 0 || strtolower((string)$s['payment_status']) === 'refunded';
            $out[] = self::financeRow(
                (string)$s['created_at'],
                $refund ? 'Refund' : 'Sale',
                '#' . (int)$s['id'],
                trim((string)($s['customer_name'] ?: 'Walk-in') . ' · ' . (string)$s['payment_method'] . ($s['cashier'] ? ' · ' . $s['cashier'] : '')),
                (string)($s['location_name'] ?? ''),
                abs($amount),
                $refund ? 'out' : 'in',
                $refund ? 'refunds' : 'sales',
                true,
                [
                    'discount' => $refund ? 0 : (float)$s['discount'],
                    'tip' => $refund ? 0 : (float)$s['tip_amount'],
                    'surcharge' => $refund ? 0 : (float)$s['surcharge_amount'],
                    'tax' => $refund ? 0 : (float)$s['total_tax'],
                ]
            );
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function financeEvents(string $from, string $to, int $loc, int $emp): array
    {
        if ($emp > 0) {
            return [];
        }
        $sql = "SELECT ep.id, ep.created_at, ep.amount, ep.purpose, ep.method, ev.title, l.name AS location_name
                FROM event_payments ep
                JOIN events ev ON ev.id = ep.event_id
                LEFT JOIN locations l ON l.id = ev.location_id
                WHERE ep.status = 'paid' AND ep.created_at BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($loc > 0) {
            $sql .= ' AND ev.location_id = ?';
            $params[] = $loc;
        }
        $out = [];
        foreach (self::financeFetch($sql, $params) as $r) {
            $out[] = self::financeRow(
                (string)$r['created_at'],
                'Event',
                '#' . (int)$r['id'],
                (string)$r['title'] . ' · ' . (string)$r['purpose'] . ' · ' . (string)$r['method'],
                (string)($r['location_name'] ?? ''),
                (float)$r['amount'],
                'in',
                'events'
            );
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function financeExpenses(string $from, string $to, int $loc, int $emp): array
    {
        $sql = "SELECT e.id, e.created_at, e.amount, e.reason, e.description, l.name AS location_name, u.username
                FROM expenses e
                LEFT JOIN locations l ON l.id = e.location_id
                LEFT JOIN users u ON u.id = e.user_id
                WHERE e.created_at BETWEEN ? AND ?
                  AND COALESCE(e.reason, '') NOT LIKE 'Payroll:%'
                  AND COALESCE(e.description, '') NOT LIKE 'Payroll:%'";
        $params = [$from, $to];
        if ($loc > 0) {
            $sql .= ' AND e.location_id = ?';
            $params[] = $loc;
        }
        if ($emp > 0) {
            $sql .= ' AND e.user_id = ?';
            $params[] = $emp;
        }
        $out = [];
        foreach (self::financeFetch($sql, $params) as $r) {
            $why = trim((string)($r['reason'] ?: $r['description'] ?: 'Expense'));
            $out[] = self::financeRow(
                (string)$r['created_at'],
                'Expense',
                '#' . (int)$r['id'],
                $why . ($r['username'] ? ' · ' . $r['username'] : ''),
                (string)($r['location_name'] ?? ''),
                (float)$r['amount'],
                'out',
                'expenses'
            );
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function financePayroll(string $start, string $end, int $loc, int $emp): array
    {
        $sql = "SELECT p.id, r.title, r.pay_date, r.paid_at, e.full_name, l.name AS location_name,
                       p.net_pay, p.paye, p.napsa_employee, p.napsa_employer, p.nhima_employee, p.nhima_employer
                FROM payslips p
                JOIN payroll_runs r ON r.id = p.run_id
                JOIN employees e ON e.id = p.employee_id
                LEFT JOIN locations l ON l.id = e.location_id
                WHERE r.status = 'paid' AND COALESCE(r.pay_date, DATE(r.paid_at)) BETWEEN ? AND ?";
        $params = [$start, $end];
        if ($loc > 0) {
            $sql .= ' AND e.location_id = ?';
            $params[] = $loc;
        }
        if ($emp > 0) {
            $sql .= ' AND e.user_id = ?';
            $params[] = $emp;
        }
        $out = [];
        foreach (self::financeFetch($sql, $params) as $r) {
            $statutory = (float)$r['paye'] + (float)$r['napsa_employee'] + (float)$r['napsa_employer'] + (float)$r['nhima_employee'] + (float)$r['nhima_employer'];
            $when = (string)($r['pay_date'] ?: $r['paid_at']);
            $out[] = self::financeRow(
                strlen($when) === 10 ? $when . ' 00:00:00' : $when,
                'Payroll',
                'Slip #' . (int)$r['id'],
                (string)$r['full_name'] . ' · ' . (string)$r['title'] . ' · net ' . number_format((float)$r['net_pay'], 2) . ' · statutory ' . number_format($statutory, 2),
                (string)($r['location_name'] ?? ''),
                (float)$r['net_pay'] + $statutory,
                'out',
                'payroll',
                true,
                ['statutory' => $statutory]
            );
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function financePurchases(string $from, string $to, int $loc, int $emp): array
    {
        $sql = "SELECT g.id, g.created_at, g.total_cost, g.reference_no, v.name AS vendor, l.name AS location_name
                FROM grvs g
                LEFT JOIN vendors v ON v.id = g.vendor_id
                LEFT JOIN locations l ON l.id = g.location_id
                WHERE g.created_at BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($loc > 0) {
            $sql .= ' AND g.location_id = ?';
            $params[] = $loc;
        }
        if ($emp > 0) {
            $sql .= ' AND g.received_by = ?';
            $params[] = $emp;
        }
        $out = [];
        foreach (self::financeFetch($sql, $params) as $r) {
            $out[] = self::financeRow(
                (string)$r['created_at'],
                'Purchase',
                (string)($r['reference_no'] ?: ('GRV #' . (int)$r['id'])),
                (string)($r['vendor'] ?: 'Supplier'),
                (string)($r['location_name'] ?? ''),
                (float)$r['total_cost'],
                'out',
                'purchases'
            );
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function financeFees(string $from, string $to, int $loc, int $emp): array
    {
        $sql = "SELECT id, created_at, sale_id, COALESCE(app_fee, 0) + COALESCE(merchant_fee, 0) AS fee
                FROM payment_transactions
                WHERE created_at BETWEEN ? AND ?
                  AND LOWER(status) IN ('successful', 'success', 'completed', 'paid')
                  AND (COALESCE(app_fee, 0) + COALESCE(merchant_fee, 0)) > 0";
        $params = [$from, $to];
        if ($loc > 0) {
            $sql .= ' AND location_id = ?';
            $params[] = $loc;
        }
        if ($emp > 0) {
            $sql .= ' AND user_id = ?';
            $params[] = $emp;
        }
        $out = [];
        foreach (self::financeFetch($sql, $params) as $r) {
            $out[] = self::financeRow(
                (string)$r['created_at'],
                'Service fee',
                $r['sale_id'] ? ('Sale #' . (int)$r['sale_id']) : ('#' . (int)$r['id']),
                'Flutterwave fee taken before settlement',
                '',
                (float)$r['fee'],
                'out',
                'fees'
            );
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function financeTransfers(string $from, string $to, int $loc, int $emp): array
    {
        if ($loc > 0) {
            return [];
        }
        $sql = "SELECT id, created_at, amount, fee, narration, beneficiary_name
                FROM flutterwave_transfers
                WHERE created_at BETWEEN ? AND ?
                  AND LOWER(status) IN ('successful', 'success', 'completed')";
        $params = [$from, $to];
        if ($emp > 0) {
            $sql .= ' AND user_id = ?';
            $params[] = $emp;
        }
        $out = [];
        foreach (self::financeFetch($sql, $params) as $r) {
            $out[] = self::financeRow(
                (string)$r['created_at'],
                'Transfer',
                '#' . (int)$r['id'],
                trim((string)($r['beneficiary_name'] ?: 'Payout') . ($r['narration'] ? ' · ' . $r['narration'] : '')),
                'All stores',
                (float)$r['amount'] + (float)$r['fee'],
                'out',
                'transfers',
                false
            );
        }
        return $out;
    }

    /** @return list<array<string, mixed>> */
    private static function financeFetch(string $sql, array $params): array
    {
        try {
            $stmt = self::db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            error_log('Finance report query failed: ' . $e->getMessage());
            return [];
        }
    }

    /** @param array<string, float> $extra */
    private static function financeRow(string $at, string $kind, string $ref, string $detail, string $location, float $amount, string $direction, string $bucket, bool $counts = true, array $extra = []): array
    {
        return [
            'at' => $at,
            'kind' => $kind,
            'ref' => $ref,
            'detail' => $detail,
            'location' => $location,
            'amount' => round($amount, 2),
            'direction' => $direction,
            'bucket' => $bucket,
            'counts' => $counts,
        ] + $extra;
    }

    public static function daypartClause(string $daypart): string
    {
        return match ($daypart) {
            'morning' => ' AND HOUR(s.created_at) BETWEEN 6 AND 11',
            'afternoon' => ' AND HOUR(s.created_at) BETWEEN 12 AND 16',
            'evening' => ' AND HOUR(s.created_at) BETWEEN 17 AND 21',
            'night' => ' AND (HOUR(s.created_at) >= 22 OR HOUR(s.created_at) < 6)',
            default => '',
        };
    }

    private static function rangeParams(string $start, string $end, $locationId, string $daypart = ''): array
    {
        $params = [$start . ' 00:00:00', $end . ' 23:59:59'];
        $locSql = ($locationId) ? ' AND s.location_id = ?' : '';
        if ($locationId) {
            $params[] = $locationId;
        }
        return [$params, $locSql . self::daypartClause($daypart)];
    }
}
