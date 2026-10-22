<?php

namespace App\Models;

use PDO;

/**
 * Books of account, built on demand from the till, stock, and purchasing records.
 */
final class Books extends Model
{
    /** @return array<string, array{title: string, blurb: string}> */
    public static function catalog(): array
    {
        return [
            'sales' => [
                'title' => 'Daily sales register',
                'blurb' => 'Each day’s takings by payment method, tax class, and mobile-money network.',
            ],
            'cash' => [
                'title' => 'Three-column cash book',
                'blurb' => 'Cash, bank, and discount. Card and mobile money sit in the bank columns.',
            ],
            'petty' => [
                'title' => 'Petty cash book',
                'blurb' => 'Till payouts. Each line is a voucher that left the drawer.',
            ],
            'yield' => [
                'title' => 'Recipe and yield book',
                'blurb' => 'Portions sold, ingredients used, and the cost of those ingredients.',
            ],
            'stock' => [
                'title' => 'Stock and inventory register',
                'blurb' => 'Every stock movement in the period, with the balance after the movement.',
            ],
            'wastage' => [
                'title' => 'Wastage, spoilage, and voids',
                'blurb' => 'Waste, stock written off, and items voided off a bill.',
            ],
            'purchases' => [
                'title' => 'Purchases day book',
                'blurb' => 'Purchase orders beside the goods received note and what was actually received.',
            ],
        ];
    }

    /** @return array{title: string, note: string, sections: list<array<string, mixed>>} */
    public static function open(string $book, string $start, string $end, int $locationId): array
    {
        $catalog = self::catalog();
        $meta = $catalog[$book] ?? $catalog['sales'];
        $from = $start . ' 00:00:00';
        $to = $end . ' 23:59:59';
        $sections = match ($book) {
            'cash' => self::cashBook($from, $to, $locationId),
            'petty' => self::pettyBook($from, $to, $locationId),
            'yield' => self::yieldBook($from, $to, $locationId),
            'stock' => self::stockBook($from, $to, $locationId),
            'wastage' => self::wastageBook($from, $to, $locationId),
            'purchases' => self::purchasesBook($from, $to, $locationId),
            default => self::salesBook($from, $to, $locationId),
        };
        return [
            'title' => $meta['title'],
            'note' => $meta['blurb'],
            'sections' => $sections,
        ];
    }

    /**
     * Catalog keys that have at least one line for this period, in catalog order.
     *
     * @return list<string>
     */
    public static function filled(string $start, string $end, int $locationId): array
    {
        $from = $start . ' 00:00:00';
        $to = $end . ' 23:59:59';
        $has = [];
        if (self::probe(
            "SELECT 1 FROM sales s WHERE s.created_at >= ? AND s.created_at <= ? AND s.payment_status = 'paid' AND s.final_total >= 0",
            's',
            $from,
            $to,
            $locationId
        )) {
            $has['sales'] = true;
        }
        if (self::probe(
            "SELECT 1 FROM sales s WHERE s.created_at >= ? AND s.created_at <= ? AND s.payment_status = 'paid' AND s.final_total <> 0",
            's',
            $from,
            $to,
            $locationId
        ) || self::probe(
            'SELECT 1 FROM expenses e WHERE e.created_at >= ? AND e.created_at <= ?',
            'e',
            $from,
            $to,
            $locationId
        ) || self::probe(
            "SELECT 1 FROM shifts sh WHERE sh.status = 'closed' AND sh.end_time >= ? AND sh.end_time <= ? AND ABS(sh.variance) >= 0.004",
            'sh',
            $from,
            $to,
            $locationId
        )) {
            $has['cash'] = true;
        }
        if (self::probe(
            'SELECT 1 FROM expenses e WHERE e.created_at >= ? AND e.created_at <= ?',
            'e',
            $from,
            $to,
            $locationId
        )) {
            $has['petty'] = true;
        }
        if (self::probe(
            "SELECT 1 FROM sale_items si JOIN sales s ON s.id = si.sale_id JOIN product_recipes r ON r.parent_product_id = si.product_id WHERE s.created_at >= ? AND s.created_at <= ? AND s.payment_status = 'paid' AND si.status NOT IN ('voided', 'refunded')",
            's',
            $from,
            $to,
            $locationId
        )) {
            $has['yield'] = true;
        }
        if (self::probe(
            'SELECT 1 FROM inventory_logs il WHERE il.created_at >= ? AND il.created_at <= ?',
            'il',
            $from,
            $to,
            $locationId
        )) {
            $has['stock'] = true;
        }
        if (self::probe(
            "SELECT 1 FROM inventory_logs il WHERE il.created_at >= ? AND il.created_at <= ? AND (il.action_type IN ('waste', 'void_return') OR (il.action_type IN ('adjustment', 'stock_take') AND il.change_qty < 0))",
            'il',
            $from,
            $to,
            $locationId
        )) {
            $has['wastage'] = true;
        }
        if (self::probe(
            'SELECT 1 FROM purchase_orders o WHERE o.created_at >= ? AND o.created_at <= ?',
            'o',
            $from,
            $to,
            $locationId
        ) || self::probe(
            'SELECT 1 FROM grvs g WHERE g.created_at >= ? AND g.created_at <= ? AND (g.purchase_order_id IS NULL OR g.purchase_order_id = 0)',
            'g',
            $from,
            $to,
            $locationId
        )) {
            $has['purchases'] = true;
        }

        $filled = [];
        foreach (array_keys(self::catalog()) as $key) {
            if (!empty($has[$key])) {
                $filled[] = $key;
            }
        }
        return $filled;
    }

    private static function probe(string $sql, string $alias, string $from, string $to, int $locationId): bool
    {
        $params = [$from, $to];
        $loc = self::loc($alias, $locationId, $params);
        return self::fetch($sql . $loc . ' LIMIT 1', $params) !== [];
    }

    /** @return list<array<string, mixed>> */
    private static function salesBook(string $from, string $to, int $locationId): array
    {
        $params = [$from, $to];
        $loc = self::loc('s', $locationId, $params);
        $rows = self::fetch(
            "SELECT DATE(s.created_at) AS day, COALESCE(NULLIF(s.payment_method, ''), 'Unspecified') AS method,
                    COUNT(*) AS tickets, COALESCE(SUM(s.final_total), 0) AS amount
             FROM sales s
             WHERE s.created_at >= ? AND s.created_at <= ? AND s.payment_status = 'paid' AND s.final_total >= 0
             {$loc}
             GROUP BY DATE(s.created_at), s.payment_method
             ORDER BY day DESC, amount DESC",
            $params
        );
        $classParams = [$from, $to];
        $classLoc = self::loc('s', $locationId, $classParams);
        $classes = self::fetch(
            "SELECT DATE(s.created_at) AS day, COALESCE(NULLIF(p.tax_class, ''), '—') AS tax_class,
                    SUM(si.quantity) AS qty, COALESCE(SUM(si.price * si.quantity), 0) AS amount
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             LEFT JOIN products p ON p.id = si.product_id
             WHERE s.created_at >= ? AND s.created_at <= ? AND s.payment_status = 'paid'
               AND si.status NOT IN ('voided', 'refunded')
             {$classLoc}
             GROUP BY DATE(s.created_at), p.tax_class
             ORDER BY day DESC, amount DESC",
            $classParams
        );
        $netParams = [$from, $to];
        $netLoc = self::loc('pt', $locationId, $netParams);
        $networks = self::fetch(
            "SELECT DATE(pt.created_at) AS day, COALESCE(NULLIF(pt.network, ''), 'Not named') AS network,
                    COUNT(*) AS tickets, COALESCE(SUM(pt.amount), 0) AS amount,
                    SUM(CASE WHEN pt.settlement_status = 'settled' THEN 1 ELSE 0 END) AS settled
             FROM payment_transactions pt
             WHERE pt.created_at >= ? AND pt.created_at <= ? AND pt.status = 'successful'
             {$netLoc}
             GROUP BY DATE(pt.created_at), pt.network
             ORDER BY day DESC, amount DESC",
            $netParams
        );

        return [
            self::section('Takings by payment method', [
                ['key' => 'day', 'label' => 'Date'],
                ['key' => 'method', 'label' => 'Payment'],
                ['key' => 'tickets', 'label' => 'Receipts', 'num' => true],
                ['key' => 'amount', 'label' => 'Amount', 'money' => true],
            ], $rows, ['amount']),
            self::section('Tax class', [
                ['key' => 'day', 'label' => 'Date'],
                ['key' => 'tax_class', 'label' => 'Class'],
                ['key' => 'qty', 'label' => 'Qty', 'num' => true],
                ['key' => 'amount', 'label' => 'Amount', 'money' => true],
            ], $classes, ['amount']),
            self::section('Mobile money and card networks', [
                ['key' => 'day', 'label' => 'Date'],
                ['key' => 'network', 'label' => 'Network'],
                ['key' => 'tickets', 'label' => 'Charges', 'num' => true],
                ['key' => 'settled', 'label' => 'Paid out', 'num' => true],
                ['key' => 'amount', 'label' => 'Amount', 'money' => true],
            ], $networks, ['amount']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function cashBook(string $from, string $to, int $locationId): array
    {
        $days = [];
        $touch = static function (array &$days, string $day): array {
            if (!isset($days[$day])) {
                $days[$day] = [
                    'day' => $day,
                    'particulars' => '',
                    'cash_in' => 0.0,
                    'cash_out' => 0.0,
                    'bank_in' => 0.0,
                    'bank_out' => 0.0,
                    'discount' => 0.0,
                ];
            }
            return $days[$day];
        };

        if (function_exists('ensureSaleSplitPaymentsColumn')) {
            ensureSaleSplitPaymentsColumn(self::db());
        }
        $params = [$from, $to];
        $loc = self::loc('s', $locationId, $params);
        $sales = self::fetch(
            "SELECT DATE(s.created_at) AS day, s.payment_method, s.final_total, s.change_due,
                    s.split_method_1, s.split_amount_1, s.split_method_2, s.split_amount_2, s.split_payments
             FROM sales s
             WHERE s.created_at >= ? AND s.created_at <= ? AND s.payment_status = 'paid' AND s.final_total <> 0
             {$loc}",
            $params
        );
        foreach ($sales as $sale) {
            $day = (string)$sale['day'];
            $legs = self::legs($sale);
            foreach ($legs as $leg) {
                $amount = (float)$leg['amount'];
                if (abs($amount) < 0.004) {
                    continue;
                }
                $bucket = self::isCash((string)$leg['method']) ? 'cash' : 'bank';
                $side = $amount >= 0 ? 'in' : 'out';
                $key = $bucket . '_' . $side;
                if (!isset($days[$day])) {
                    $touch($days, $day);
                }
                $days[$day][$key] += abs($amount);
                $label = (string)$leg['method'];
                if ($days[$day]['particulars'] === '') {
                    $days[$day]['particulars'] = 'Takings';
                }
                if (!str_contains($days[$day]['particulars'], $label)) {
                    $days[$day]['particulars'] .= ($days[$day]['particulars'] === 'Takings' ? ': ' : ', ') . $label;
                }
            }
        }

        $discParams = [$from, $to];
        $discLoc = self::loc('s', $locationId, $discParams);
        $gross = self::fetch(
            "SELECT DATE(s.created_at) AS day, COALESCE(SUM(si.price * si.quantity), 0) AS gross
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             WHERE s.created_at >= ? AND s.created_at <= ? AND s.payment_status = 'paid'
               AND si.status NOT IN ('voided', 'refunded')
             {$discLoc}
             GROUP BY DATE(s.created_at)",
            $discParams
        );
        $netParams = [$from, $to];
        $netLoc = self::loc('s', $locationId, $netParams);
        $nets = self::fetch(
            "SELECT DATE(created_at) AS day, COALESCE(SUM(subtotal), 0) AS net
             FROM sales s
             WHERE s.created_at >= ? AND s.created_at <= ? AND s.payment_status = 'paid' AND s.final_total >= 0
             {$netLoc}
             GROUP BY DATE(created_at)",
            $netParams
        );
        $grossByDay = [];
        foreach ($gross as $row) {
            $grossByDay[(string)$row['day']] = (float)$row['gross'];
        }
        foreach ($nets as $row) {
            $day = (string)$row['day'];
            $discount = round(($grossByDay[$day] ?? 0) - (float)$row['net'], 2);
            if ($discount > 0.004) {
                $touch($days, $day);
                $days[$day]['discount'] = $discount;
            }
        }

        $expParams = [$from, $to];
        $expLoc = self::loc('e', $locationId, $expParams);
        foreach (self::fetch(
            "SELECT DATE(e.created_at) AS day, COALESCE(SUM(e.amount), 0) AS amount
             FROM expenses e
             WHERE e.created_at >= ? AND e.created_at <= ? {$expLoc}
             GROUP BY DATE(e.created_at)",
            $expParams
        ) as $row) {
            $day = (string)$row['day'];
            $touch($days, $day);
            $days[$day]['cash_out'] += (float)$row['amount'];
            $days[$day]['particulars'] = trim($days[$day]['particulars'] . ($days[$day]['particulars'] === '' ? 'Payouts' : ' · payouts'));
        }

        $shiftParams = [$from, $to];
        $shiftLoc = self::loc('sh', $locationId, $shiftParams);
        foreach (self::fetch(
            "SELECT DATE(sh.end_time) AS day, COALESCE(SUM(sh.variance), 0) AS variance
             FROM shifts sh
             WHERE sh.status = 'closed' AND sh.end_time >= ? AND sh.end_time <= ? {$shiftLoc}
             GROUP BY DATE(sh.end_time)",
            $shiftParams
        ) as $row) {
            $variance = round((float)$row['variance'], 2);
            if (abs($variance) < 0.004) {
                continue;
            }
            $day = (string)$row['day'];
            $touch($days, $day);
            if ($variance > 0) {
                $days[$day]['cash_in'] += $variance;
            } else {
                $days[$day]['cash_out'] += abs($variance);
            }
            $note = 'drawer ' . ($variance > 0 ? 'over' : 'short');
            $days[$day]['particulars'] = trim($days[$day]['particulars'] . ($days[$day]['particulars'] === '' ? ucfirst($note) : ' · ' . $note));
        }

        krsort($days);
        $rows = [];
        foreach ($days as $row) {
            foreach (['cash_in', 'cash_out', 'bank_in', 'bank_out', 'discount'] as $key) {
                $row[$key] = round((float)$row[$key], 2);
            }
            $rows[] = $row;
        }

        return [
            self::section('Cash, bank, and discount', [
                ['key' => 'day', 'label' => 'Date'],
                ['key' => 'particulars', 'label' => 'Particulars'],
                ['key' => 'cash_in', 'label' => 'Cash in', 'money' => true],
                ['key' => 'cash_out', 'label' => 'Cash out', 'money' => true],
                ['key' => 'bank_in', 'label' => 'Bank in', 'money' => true],
                ['key' => 'bank_out', 'label' => 'Bank out', 'money' => true],
                ['key' => 'discount', 'label' => 'Discount', 'money' => true],
            ], $rows, ['cash_in', 'cash_out', 'bank_in', 'bank_out', 'discount']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function pettyBook(string $from, string $to, int $locationId): array
    {
        $params = [$from, $to];
        $loc = self::loc('e', $locationId, $params);
        $rows = self::fetch(
            "SELECT e.created_at, e.amount, e.reason, e.reason AS voucher,
                    u.username, l.name AS location_name, e.shift_id
             FROM expenses e
             LEFT JOIN users u ON u.id = e.user_id
             LEFT JOIN locations l ON l.id = e.location_id
             WHERE e.created_at >= ? AND e.created_at <= ? {$loc}
             ORDER BY e.created_at DESC",
            $params
        );
        $balance = 0.0;
        $ordered = array_reverse($rows);
        foreach ($ordered as &$row) {
            $balance += (float)$row['amount'];
            $row['balance'] = round($balance, 2);
            $row['when'] = date('d M Y H:i', strtotime((string)$row['created_at']));
            $row['who'] = (string)($row['username'] ?? '');
            $row['where'] = (string)($row['location_name'] ?? '');
            $row['ref'] = trim((string)$row['reason']);
        }
        unset($row);
        $rows = array_reverse($ordered);

        return [
            self::section('Vouchers', [
                ['key' => 'when', 'label' => 'When'],
                ['key' => 'where', 'label' => 'Store'],
                ['key' => 'who', 'label' => 'By'],
                ['key' => 'ref', 'label' => 'Voucher'],
                ['key' => 'shift_id', 'label' => 'Shift', 'num' => true],
                ['key' => 'amount', 'label' => 'Paid out', 'money' => true],
                ['key' => 'balance', 'label' => 'Running total', 'money' => true],
            ], $rows, ['amount']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function yieldBook(string $from, string $to, int $locationId): array
    {
        $params = [$from, $to];
        $loc = self::loc('s', $locationId, $params);
        $lines = self::fetch(
            "SELECT p.name AS dish, i.name AS ingredient, COALESCE(i.unit, '') AS unit,
                    SUM(si.quantity) AS portions,
                    SUM(si.quantity * r.quantity) AS used_qty,
                    SUM(si.price * si.quantity) AS sales_amount,
                    SUM(si.quantity * r.quantity * COALESCE(i.cost_price, 0)) AS ingredient_cost
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             JOIN products p ON p.id = si.product_id
             JOIN product_recipes r ON r.parent_product_id = p.id
             JOIN products i ON i.id = r.ingredient_product_id
             WHERE s.created_at >= ? AND s.created_at <= ? AND s.payment_status = 'paid'
               AND si.status NOT IN ('voided', 'refunded')
             {$loc}
             GROUP BY p.id, i.id
             ORDER BY p.name, i.name",
            $params
        );
        $dishes = [];
        foreach ($lines as $line) {
            $dish = (string)$line['dish'];
            if (!isset($dishes[$dish])) {
                $dishes[$dish] = [
                    'dish' => $dish,
                    'portions' => (float)$line['portions'],
                    'sales_amount' => (float)$line['sales_amount'],
                    'ingredient_cost' => 0.0,
                ];
            }
            $dishes[$dish]['ingredient_cost'] += (float)$line['ingredient_cost'];
        }
        $summary = [];
        foreach ($dishes as $row) {
            $sales = round((float)$row['sales_amount'], 2);
            $cost = round((float)$row['ingredient_cost'], 2);
            $summary[] = [
                'dish' => $row['dish'],
                'portions' => round((float)$row['portions'], 2),
                'sales_amount' => $sales,
                'ingredient_cost' => $cost,
                'margin' => round($sales - $cost, 2),
            ];
        }
        foreach ($lines as &$line) {
            $line['portions'] = round((float)$line['portions'], 2);
            $line['used_qty'] = round((float)$line['used_qty'], 4);
            $line['ingredient_cost'] = round((float)$line['ingredient_cost'], 2);
        }
        unset($line);

        return [
            self::section('Yield by dish', [
                ['key' => 'dish', 'label' => 'Dish'],
                ['key' => 'portions', 'label' => 'Portions', 'num' => true],
                ['key' => 'sales_amount', 'label' => 'Sales', 'money' => true],
                ['key' => 'ingredient_cost', 'label' => 'Ingredient cost', 'money' => true],
                ['key' => 'margin', 'label' => 'Margin', 'money' => true],
            ], $summary, ['sales_amount', 'ingredient_cost', 'margin']),
            self::section('Ingredients used', [
                ['key' => 'dish', 'label' => 'Dish'],
                ['key' => 'ingredient', 'label' => 'Ingredient'],
                ['key' => 'used_qty', 'label' => 'Used', 'num' => true],
                ['key' => 'unit', 'label' => 'Unit'],
                ['key' => 'ingredient_cost', 'label' => 'Cost', 'money' => true],
            ], $lines, ['ingredient_cost']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function stockBook(string $from, string $to, int $locationId): array
    {
        $params = [$from, $to];
        $loc = self::loc('il', $locationId, $params);
        $rows = self::fetch(
            "SELECT il.created_at, p.name AS product, l.name AS location_name, il.action_type,
                    il.change_qty, il.after_qty, COALESCE(il.reason, '') AS reason, u.username
             FROM inventory_logs il
             JOIN products p ON p.id = il.product_id
             LEFT JOIN locations l ON l.id = il.location_id
             LEFT JOIN users u ON u.id = il.user_id
             WHERE il.created_at >= ? AND il.created_at <= ? {$loc}
             ORDER BY il.created_at DESC
             LIMIT 800",
            $params
        );
        $labels = Inventory::actionTypes();
        foreach ($rows as &$row) {
            $row['when'] = date('d M Y H:i', strtotime((string)$row['created_at']));
            $row['action'] = $labels[(string)$row['action_type']] ?? (string)$row['action_type'];
            $row['change_qty'] = round((float)$row['change_qty'], 2);
            $row['after_qty'] = round((float)$row['after_qty'], 2);
        }
        unset($row);

        return [
            self::section('Movements', [
                ['key' => 'when', 'label' => 'When'],
                ['key' => 'location_name', 'label' => 'Store'],
                ['key' => 'product', 'label' => 'Product'],
                ['key' => 'action', 'label' => 'Movement'],
                ['key' => 'change_qty', 'label' => 'Change', 'num' => true],
                ['key' => 'after_qty', 'label' => 'Balance', 'num' => true],
                ['key' => 'reason', 'label' => 'Reason'],
                ['key' => 'username', 'label' => 'By'],
            ], $rows, []),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function wastageBook(string $from, string $to, int $locationId): array
    {
        $params = [$from, $to];
        $loc = self::loc('il', $locationId, $params);
        $rows = self::fetch(
            "SELECT il.created_at, p.name AS product, l.name AS location_name, il.action_type,
                    il.change_qty, COALESCE(il.reason, '') AS reason, u.username,
                    COALESCE(p.cost_price, 0) AS cost_price
             FROM inventory_logs il
             JOIN products p ON p.id = il.product_id
             LEFT JOIN locations l ON l.id = il.location_id
             LEFT JOIN users u ON u.id = il.user_id
             WHERE il.created_at >= ? AND il.created_at <= ?
               AND (
                    il.action_type IN ('waste', 'void_return')
                    OR (il.action_type IN ('adjustment', 'stock_take') AND il.change_qty < 0)
               )
             {$loc}
             ORDER BY il.created_at DESC",
            $params
        );
        $labels = [
            'waste' => 'Waste',
            'void_return' => 'Voided off a bill',
            'adjustment' => 'Written off',
            'stock_take' => 'Stock take short',
        ];
        foreach ($rows as &$row) {
            $qty = abs((float)$row['change_qty']);
            $row['when'] = date('d M Y H:i', strtotime((string)$row['created_at']));
            $row['kind'] = $labels[(string)$row['action_type']] ?? (string)$row['action_type'];
            $row['qty'] = round($qty, 2);
            $row['cost'] = round($qty * (float)$row['cost_price'], 2);
        }
        unset($row);

        return [
            self::section('Losses', [
                ['key' => 'when', 'label' => 'When'],
                ['key' => 'location_name', 'label' => 'Store'],
                ['key' => 'product', 'label' => 'Product'],
                ['key' => 'kind', 'label' => 'Kind'],
                ['key' => 'qty', 'label' => 'Qty', 'num' => true],
                ['key' => 'cost', 'label' => 'Cost', 'money' => true],
                ['key' => 'reason', 'label' => 'Reason'],
                ['key' => 'username', 'label' => 'By'],
            ], $rows, ['cost']),
        ];
    }

    /** @return list<array<string, mixed>> */
    private static function purchasesBook(string $from, string $to, int $locationId): array
    {
        try {
            Purchase::ensureSchema();
        } catch (\Throwable $e) {
        }
        $params = [$from, $to];
        $loc = self::loc('o', $locationId, $params);
        $orders = self::fetch(
            "SELECT o.id, o.created_at, o.reference_no, o.status, o.total_cost, v.name AS vendor, l.name AS location_name,
                    (SELECT COALESCE(SUM(quantity), 0) FROM purchase_order_items i WHERE i.order_id = o.id) AS ordered_qty,
                    (SELECT COALESCE(SUM(received_qty), 0) FROM purchase_order_items i WHERE i.order_id = o.id) AS received_qty,
                    g.id AS grv_id, g.reference_no AS grv_ref, g.total_cost AS grv_total, g.created_at AS received_at
             FROM purchase_orders o
             LEFT JOIN vendors v ON v.id = o.vendor_id
             LEFT JOIN locations l ON l.id = o.location_id
             LEFT JOIN grvs g ON g.purchase_order_id = o.id
             WHERE o.created_at >= ? AND o.created_at <= ? {$loc}
             ORDER BY o.created_at DESC",
            $params
        );
        foreach ($orders as &$row) {
            $ordered = (float)$row['ordered_qty'];
            $received = (float)$row['received_qty'];
            if (empty($row['grv_id'])) {
                $match = 'Awaiting goods received';
            } elseif ($ordered > 0 && $received + 0.0001 < $ordered) {
                $match = 'Short';
            } else {
                $match = 'Matched';
            }
            $row['when'] = date('d M Y', strtotime((string)$row['created_at']));
            $row['po'] = (string)($row['reference_no'] ?: ('PO ' . $row['id']));
            $row['grn'] = empty($row['grv_id']) ? '' : (string)($row['grv_ref'] ?: ('GRN ' . $row['grv_id']));
            $row['match'] = $match;
            $row['ordered_qty'] = round($ordered, 2);
            $row['received_qty'] = round($received, 2);
            $row['invoice'] = round((float)($row['grv_total'] ?? 0), 2);
        }
        unset($row);

        $grnParams = [$from, $to];
        $grnLoc = self::loc('g', $locationId, $grnParams);
        $orphans = self::fetch(
            "SELECT g.created_at, g.reference_no, g.total_cost, v.name AS vendor, l.name AS location_name
             FROM grvs g
             LEFT JOIN vendors v ON v.id = g.vendor_id
             LEFT JOIN locations l ON l.id = g.location_id
             WHERE g.created_at >= ? AND g.created_at <= ?
               AND (g.purchase_order_id IS NULL OR g.purchase_order_id = 0)
             {$grnLoc}
             ORDER BY g.created_at DESC",
            $grnParams
        );
        foreach ($orphans as &$row) {
            $row['when'] = date('d M Y', strtotime((string)$row['created_at']));
            $row['grn'] = (string)($row['reference_no'] ?: 'GRN');
            $row['total_cost'] = round((float)$row['total_cost'], 2);
        }
        unset($row);

        return [
            self::section('Orders and goods received', [
                ['key' => 'when', 'label' => 'Date'],
                ['key' => 'location_name', 'label' => 'Store'],
                ['key' => 'vendor', 'label' => 'Supplier'],
                ['key' => 'po', 'label' => 'Order'],
                ['key' => 'status', 'label' => 'Status'],
                ['key' => 'ordered_qty', 'label' => 'Ordered', 'num' => true],
                ['key' => 'received_qty', 'label' => 'Received', 'num' => true],
                ['key' => 'grn', 'label' => 'GRN'],
                ['key' => 'match', 'label' => 'Match'],
                ['key' => 'invoice', 'label' => 'GRN value', 'money' => true],
            ], $orders, ['invoice']),
            self::section('Goods received without an order', [
                ['key' => 'when', 'label' => 'Date'],
                ['key' => 'location_name', 'label' => 'Store'],
                ['key' => 'vendor', 'label' => 'Supplier'],
                ['key' => 'grn', 'label' => 'GRN'],
                ['key' => 'total_cost', 'label' => 'Value', 'money' => true],
            ], $orphans, ['total_cost']),
        ];
    }

    /** @param list<array<string, mixed>> $rows @param list<string> $totalKeys @param list<array<string, mixed>> $columns */
    private static function section(string $heading, array $columns, array $rows, array $totalKeys): array
    {
        $totals = [];
        foreach ($totalKeys as $key) {
            $totals[$key] = 0.0;
            foreach ($rows as $row) {
                $totals[$key] += (float)($row[$key] ?? 0);
            }
            $totals[$key] = round($totals[$key], 2);
        }
        return [
            'heading' => $heading,
            'columns' => $columns,
            'rows' => $rows,
            'totals' => $totals,
        ];
    }

    /** @param array<string, mixed> $sale @return list<array{method: string, amount: float}> */
    private static function legs(array $sale): array
    {
        if (($sale['payment_method'] ?? '') === 'Split' && function_exists('saleSplitLegs')) {
            $legs = saleSplitLegs($sale);
            if (function_exists('applySplitChangeToCash')) {
                $legs = applySplitChangeToCash($legs, (float)($sale['change_due'] ?? 0));
            }
            $out = [];
            foreach ($legs as $leg) {
                $out[] = ['method' => (string)($leg['method'] ?? 'Split'), 'amount' => (float)($leg['amount'] ?? 0)];
            }
            return $out;
        }
        return [['method' => (string)($sale['payment_method'] ?: 'Unspecified'), 'amount' => (float)($sale['final_total'] ?? 0)]];
    }

    private static function isCash(string $method): bool
    {
        $method = strtolower(trim($method));
        return $method === '' || str_contains($method, 'cash');
    }

    /** @param list<mixed> $params */
    private static function loc(string $alias, int $locationId, array &$params): string
    {
        if ($locationId <= 0) {
            return '';
        }
        $params[] = $locationId;
        return ' AND ' . $alias . '.location_id = ?';
    }

    /** @param list<mixed> $params @return list<array<string, mixed>> */
    private static function fetch(string $sql, array $params): array
    {
        try {
            $stmt = self::db()->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}
