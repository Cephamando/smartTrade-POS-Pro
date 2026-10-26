<?php

namespace App\Models;

/**
 * Equipment and other assets, depreciated in a straight line.
 * Total business value is the book value still owned, plus stock, cash, mobile money,
 * bank, and unpaid customer bills, minus unpaid supplier bills and unpaid wages.
 */
final class Asset extends Model
{
    /** @return list<array{key:string,label:string}> */
    public static function categories(): array
    {
        return [
            ['key' => 'equipment', 'label' => 'Equipment'],
            ['key' => 'furniture', 'label' => 'Furniture'],
            ['key' => 'vehicle', 'label' => 'Vehicle'],
            ['key' => 'building', 'label' => 'Building'],
            ['key' => 'other', 'label' => 'Other'],
        ];
    }

    /** @return array<string, float|int> */
    public static function snapshot(int $locationId = 0): array
    {
        $owned = self::owned($locationId);
        $cost = 0.0;
        $depreciation = 0.0;
        $book = 0.0;
        foreach ($owned as $asset) {
            $position = self::position($asset);
            $cost += (float)$asset['cost'];
            $depreciation += $position['accumulated'];
            $book += $position['book'];
        }
        $stock = self::stockValue($locationId);
        $money = self::moneyPosition($locationId);
        $business = $book + $stock + $money['cash'] + $money['mobile'] + $money['bank'] + $money['debtors'] - $money['liabilities'];
        return [
            'asset_cost' => round($cost, 2),
            'depreciation' => round($depreciation, 2),
            'book' => round($book, 2),
            'stock' => round($stock, 2),
            'cash' => $money['cash'],
            'mobile' => $money['mobile'],
            'bank' => $money['bank'],
            'debtors' => $money['debtors'],
            'liabilities' => $money['liabilities'],
            'business' => round($business, 2),
            'asset_count' => count($owned),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function owned(int $locationId = 0): array
    {
        $sql = 'SELECT a.*, l.name AS location_name FROM assets a LEFT JOIN locations l ON l.id = a.location_id WHERE a.disposed_on IS NULL';
        $params = [];
        if ($locationId > 0) {
            $sql .= ' AND (a.location_id IS NULL OR a.location_id = ?)';
            $params[] = $locationId;
        }
        $sql .= ' ORDER BY a.name ASC';
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    /** @return list<array<string, mixed>> */
    public static function all(): array
    {
        return self::db()->query(
            'SELECT a.*, l.name AS location_name FROM assets a LEFT JOIN locations l ON l.id = a.location_id ORDER BY a.disposed_on IS NOT NULL, a.name ASC'
        )->fetchAll() ?: [];
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT a.*, l.name AS location_name FROM assets a LEFT JOIN locations l ON l.id = a.location_id WHERE a.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @param array<string, mixed> $post */
    public static function save(array $post): void
    {
        $id = (int)($post['id'] ?? 0);
        $name = trim((string)($post['name'] ?? ''));
        if ($name === '') {
            throw new \InvalidArgumentException('The asset needs a name.');
        }
        $categories = array_column(self::categories(), 'key');
        $category = (string)($post['category'] ?? 'equipment');
        if (!in_array($category, $categories, true)) {
            $category = 'equipment';
        }
        $acquired = (string)($post['acquired_on'] ?? '');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $acquired) !== 1) {
            throw new \InvalidArgumentException('Choose the date the asset was acquired.');
        }
        $cost = round((float)($post['cost'] ?? 0), 2);
        $salvage = round((float)($post['salvage'] ?? 0), 2);
        if ($cost < 0 || $salvage < 0 || $salvage > $cost) {
            throw new \InvalidArgumentException('Salvage cannot be more than the cost.');
        }
        $life = (int)($post['life_months'] ?? 36);
        if ($life < 1 || $life > 600) {
            throw new \InvalidArgumentException('Useful life should be between 1 and 600 months.');
        }
        $locationId = (int)($post['location_id'] ?? 0);
        $notes = trim((string)($post['notes'] ?? ''));
        $params = [$name, $category, $locationId > 0 ? $locationId : null, $acquired, $cost, $salvage, $life, $notes !== '' ? $notes : null];
        if ($id > 0) {
            $params[] = $id;
            self::db()->prepare(
                'UPDATE assets SET name = ?, category = ?, location_id = ?, acquired_on = ?, cost = ?, salvage = ?, life_months = ?, notes = ? WHERE id = ?'
            )->execute($params);
            return;
        }
        self::db()->prepare(
            'INSERT INTO assets (name, category, location_id, acquired_on, cost, salvage, life_months, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute($params);
    }

    public static function dispose(int $id, string $date, float $proceeds): void
    {
        $asset = self::find($id);
        if (!$asset || $asset['disposed_on']) {
            throw new \InvalidArgumentException('That asset is not on the register.');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1 || $date < (string)$asset['acquired_on']) {
            throw new \InvalidArgumentException('The disposal date must be on or after the acquired date.');
        }
        if ($proceeds < 0) {
            throw new \InvalidArgumentException('The proceeds cannot be negative.');
        }
        self::db()->prepare('UPDATE assets SET disposed_on = ?, disposal_proceeds = ? WHERE id = ?')
            ->execute([$date, round($proceeds, 2), $id]);
    }

    /**
     * @param array<string, mixed> $asset
     * @return array{months:int,monthly:float,accumulated:float,book:float,gain:float|null}
     */
    public static function position(array $asset, ?string $asOf = null): array
    {
        $asOf = $asOf ?: date('Y-m-d');
        $end = (string)($asset['disposed_on'] ?: $asOf);
        $months = self::monthsBetween((string)$asset['acquired_on'], $end);
        $cost = (float)$asset['cost'];
        $salvage = (float)$asset['salvage'];
        $life = max(1, (int)$asset['life_months']);
        $depreciable = max(0, $cost - $salvage);
        $monthly = $depreciable / $life;
        $used = min($life, $months);
        $accumulated = round(min($depreciable, $monthly * $used), 2);
        $book = round($cost - $accumulated, 2);
        $gain = null;
        if (!empty($asset['disposed_on'])) {
            $gain = round((float)$asset['disposal_proceeds'] - $book, 2);
            $book = 0.0;
        }
        return [
            'months' => $used,
            'monthly' => round($monthly, 2),
            'accumulated' => $accumulated,
            'book' => $book,
            'gain' => $gain,
        ];
    }

    /** @param array<string, mixed> $post @return array<string, mixed> */
    public static function drill(array $post): array
    {
        $level = (string)($post['level'] ?? '');
        $metric = (string)($post['metric'] ?? '');
        $locationId = max(0, (int)($post['location'] ?? 0));
        if ($level === 'asset') {
            return self::schedule((int)($post['asset_id'] ?? 0));
        }
        if ($level === 'stock' || ($level === 'value' && $metric === 'stock')) {
            return self::stockRows($locationId);
        }
        if ($level === 'value' && $metric === 'business') {
            return self::valueComponents($locationId);
        }
        if ($level === 'value' && in_array($metric, ['assets', 'depreciation'], true)) {
            return self::assetRows($locationId, $metric);
        }
        if ($level === 'value' && in_array($metric, ['cash', 'mobile', 'bank', 'debtors', 'liabilities', 'cash_takings', 'cash_payouts', 'cash_count', 'mobile_in', 'mobile_out'], true)) {
            return self::moneyDrill($locationId, $metric);
        }
        return ['status' => 'error', 'msg' => 'That figure is not on this page.'];
    }

    /** @return array<string, mixed> */
    private static function valueComponents(int $locationId): array
    {
        $snap = self::snapshot($locationId);
        $rows = [
            self::valueRow('Asset book value', 'Cost still on the register after depreciation', 'assets', (float)$snap['book']),
            self::valueRow('Stock at cost', 'Goods on hand at their cost price', 'stock', (float)$snap['stock']),
            self::valueRow('Cash', 'Takings left after till payouts and the drawer count', 'cash', (float)$snap['cash']),
            self::valueRow('Mobile money', 'Flutterwave money still belonging to the shop', 'mobile', (float)$snap['mobile']),
            self::valueRow('Bank', 'Paid transfers, cheques, and delivery-app takings', 'bank', (float)$snap['bank']),
            self::valueRow('Debtors', 'Customer bills that are not paid yet', 'debtors', (float)$snap['debtors']),
            self::valueRow('Liabilities', 'Unpaid supplier bills and wages. Taken off the total', 'liabilities', -1 * (float)$snap['liabilities']),
        ];
        return self::ok(
            'Total business value',
            'Book value, plus stock, cash, mobile money, bank, and unpaid customer bills, minus unpaid supplier bills and unpaid wages. This is the position today, not the dates in the filter. Open a part to see what is inside it.',
            [
                ['key' => 'component', 'label' => 'Component'],
                ['key' => 'detail', 'label' => 'What it is'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'end'],
            ],
            $rows
        );
    }

    /** @return array<string, mixed> */
    private static function valueRow(string $name, string $detail, string $metric, float $amount): array
    {
        return [
            'cells' => [
                'component' => $name,
                'detail' => $detail,
                'amount' => self::money($amount),
            ],
            'drill' => ['level' => 'value', 'metric' => $metric],
        ];
    }

    /** @return array<string, mixed> */
    private static function assetRows(int $locationId, string $metric): array
    {
        $rows = [];
        foreach (self::owned($locationId) as $asset) {
            $position = self::position($asset);
            $amount = $metric === 'depreciation' ? $position['accumulated'] : (float)$asset['cost'];
            $rows[] = [
                'cells' => [
                    'name' => (string)$asset['name'],
                    'store' => (string)($asset['location_name'] ?? 'All stores'),
                    'acquired' => date('d M Y', strtotime((string)$asset['acquired_on'])),
                    'monthly' => self::money($position['monthly']),
                    'amount' => self::money($amount),
                    'book' => self::money($position['book']),
                ],
                'drill' => ['level' => 'asset', 'asset_id' => (int)$asset['id']],
            ];
        }
        $title = $metric === 'depreciation' ? 'Depreciation' : 'Asset cost';
        $note = $metric === 'depreciation'
            ? 'Each amount is the depreciation charged so far. Open an asset to see the month by month.'
            : 'Open an asset to see its cost and the depreciation month by month.';
        return self::ok($title, $note, [
            ['key' => 'name', 'label' => 'Asset'],
            ['key' => 'store', 'label' => 'Store'],
            ['key' => 'acquired', 'label' => 'Acquired'],
            ['key' => 'monthly', 'label' => 'Each month', 'align' => 'end'],
            ['key' => 'amount', 'label' => $metric === 'depreciation' ? 'So far' : 'Cost', 'align' => 'end'],
            ['key' => 'book', 'label' => 'Book value', 'align' => 'end'],
        ], $rows);
    }

    /** @return array<string, mixed> */
    private static function schedule(int $id): array
    {
        $asset = self::find($id);
        if (!$asset) {
            return ['status' => 'error', 'msg' => 'That asset is not on the register.'];
        }
        $position = self::position($asset);
        $life = max(1, (int)$asset['life_months']);
        $start = new \DateTimeImmutable((string)$asset['acquired_on']);
        $rows = [];
        $accumulated = 0.0;
        $depreciable = max(0, (float)$asset['cost'] - (float)$asset['salvage']);
        $show = min($life, max($position['months'], 0));
        for ($i = 1; $i <= $show; $i++) {
            $charge = min($position['monthly'], round($depreciable - $accumulated, 2));
            $accumulated = round($accumulated + $charge, 2);
            $month = $start->modify('+' . $i . ' months');
            $rows[] = [
                'cells' => [
                    'month' => $month->format('M Y'),
                    'charge' => self::money($charge),
                    'accumulated' => self::money($accumulated),
                    'book' => self::money(round((float)$asset['cost'] - $accumulated, 2)),
                ],
                'drill' => null,
            ];
        }
        $facts = [
            ['label' => 'Category', 'value' => ucfirst((string)$asset['category'])],
            ['label' => 'Store', 'value' => (string)($asset['location_name'] ?? 'All stores')],
            ['label' => 'Acquired', 'value' => date('d M Y', strtotime((string)$asset['acquired_on']))],
            ['label' => 'Cost', 'value' => self::money((float)$asset['cost'])],
            ['label' => 'Salvage', 'value' => self::money((float)$asset['salvage'])],
            ['label' => 'Useful life', 'value' => $life . ' months'],
            ['label' => 'Each month', 'value' => self::money($position['monthly'])],
            ['label' => 'Depreciation so far', 'value' => self::money($position['accumulated'])],
            ['label' => 'Book value', 'value' => self::money((float)$asset['disposed_on'] ? 0 : $position['book'])],
        ];
        if (!empty($asset['disposed_on'])) {
            $facts[] = ['label' => 'Disposed', 'value' => date('d M Y', strtotime((string)$asset['disposed_on']))];
            $facts[] = ['label' => 'Proceeds', 'value' => self::money((float)$asset['disposal_proceeds'])];
            $facts[] = ['label' => 'Gain or loss', 'value' => self::money((float)$position['gain'])];
        }
        $note = $rows === []
            ? 'Depreciation starts after the first full month. These facts are the last detail until then.'
            : 'Each row is one month of straight-line depreciation. This is the last detail for the asset.';
        return self::ok((string)$asset['name'], $note, [
            ['key' => 'month', 'label' => 'Month'],
            ['key' => 'charge', 'label' => 'Charge', 'align' => 'end'],
            ['key' => 'accumulated', 'label' => 'So far', 'align' => 'end'],
            ['key' => 'book', 'label' => 'Book value', 'align' => 'end'],
        ], $rows, $facts);
    }

    /** @return array<string, mixed> */
    private static function stockRows(int $locationId): array
    {
        $sql = 'SELECT p.name, p.cost_price, SUM(i.quantity) AS qty, SUM(i.quantity * p.cost_price) AS value
                FROM inventory i
                JOIN products p ON p.id = i.product_id
                WHERE i.quantity > 0';
        $params = [];
        if ($locationId > 0) {
            $sql .= ' AND i.location_id = ?';
            $params[] = $locationId;
        }
        $sql .= ' GROUP BY p.id, p.name, p.cost_price HAVING value <> 0 ORDER BY value DESC LIMIT 200';
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $item) {
            $rows[] = [
                'cells' => [
                    'item' => (string)$item['name'],
                    'qty' => rtrim(rtrim(number_format((float)$item['qty'], 2, '.', ''), '0'), '.'),
                    'cost' => self::money((float)$item['cost_price']),
                    'amount' => self::money((float)$item['value']),
                ],
                'drill' => null,
            ];
        }
        return self::ok(
            'Stock at cost',
            'Each line is a product on hand at its cost price. This is the last detail for stock.',
            [
                ['key' => 'item', 'label' => 'Item'],
                ['key' => 'qty', 'label' => 'On hand', 'align' => 'end'],
                ['key' => 'cost', 'label' => 'Cost price', 'align' => 'end'],
                ['key' => 'amount', 'label' => 'Value', 'align' => 'end'],
            ],
            $rows
        );
    }

    private static function stockValue(int $locationId): float
    {
        $sql = 'SELECT COALESCE(SUM(i.quantity * p.cost_price), 0) FROM inventory i JOIN products p ON p.id = i.product_id WHERE i.quantity > 0';
        $params = [];
        if ($locationId > 0) {
            $sql .= ' AND i.location_id = ?';
            $params[] = $locationId;
        }
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    /** @return array{cash:float,mobile:float,bank:float,debtors:float,liabilities:float} */
    private static function moneyPosition(int $locationId): array
    {
        $tenders = self::tenders($locationId, false);
        $tips = self::tipTotals($locationId);
        $payouts = self::expenseTotal($locationId);
        $count = self::drawerTotal($locationId);
        $mobile = self::mobileNet($locationId);
        return [
            'cash' => round($tenders['cash'] + $tips['cash'] - $payouts + $count, 2),
            'mobile' => $mobile,
            'bank' => round($tenders['bank'] + $tips['bank'], 2),
            'debtors' => self::debtorTotal($locationId),
            'liabilities' => round(self::supplierOwed($locationId) + self::wagesOwed($locationId), 2),
        ];
    }

    /** @return array<string, mixed> */
    private static function moneyDrill(int $locationId, string $metric): array
    {
        $tenders = self::tenders($locationId, true);
        $tips = self::tipTotals($locationId);
        $payouts = self::expenseTotal($locationId);
        $count = self::drawerTotal($locationId);
        if ($metric === 'cash') {
            $rows = [
                self::valueRow('Cash takings', 'Cash paid on receipts, after change', 'cash_takings', $tenders['cash']),
                self::valueRow('Till payouts', 'Expenses taken from the drawer, including net wages once they are paid', 'cash_payouts', -1 * $payouts),
                self::valueRow('Drawer count', 'Over or short when a shift was closed', 'cash_count', $count),
            ];
            if (abs($tips['cash']) >= 0.004) {
                $rows[] = [
                    'cells' => [
                        'component' => 'Cash tips',
                        'detail' => 'Tips recorded as cash after the receipt',
                        'amount' => self::money($tips['cash']),
                    ],
                    'drill' => null,
                ];
            }
            return self::ok('Cash', 'Cash is what the till has taken in cash, minus payouts, plus or minus the drawer count. An opening float is not added again. Money later moved to a bank is still here until it is recorded as a bank tender. This is the position today.', [
                ['key' => 'component', 'label' => 'Component'],
                ['key' => 'detail', 'label' => 'What it is'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'end'],
            ], $rows);
        }
        if ($metric === 'cash_takings') {
            return self::tenderRows('Cash takings', 'Each row is the cash on one receipt. Open it for the items.', $tenders['cash_lines']);
        }
        if ($metric === 'cash_payouts') {
            return self::expenseRows($locationId);
        }
        if ($metric === 'cash_count') {
            return self::drawerRows($locationId);
        }
        if ($metric === 'bank') {
            $note = 'Paid bank transfers, cheques, and delivery-app takings. Flutterwave card and mobile money are under Mobile money. Open a receipt for the items.';
            if (abs($tips['bank']) >= 0.004) {
                $note .= ' Bank tips of ' . self::money($tips['bank']) . ' are in the total and are not listed as receipts.';
            }
            return self::tenderRows('Bank', $note, $tenders['bank_lines']);
        }
        if ($metric === 'mobile') {
            [$received, $sent] = self::mobileParts($locationId);
            $note = 'Received is the shop’s share of Flutterwave charges after the fee. Sent out is money transferred from that balance. A supplier paid with the shop’s own card or phone is not taken off this figure, because that money never sat in the balance. This is the position today, not a live wallet balance.';
            if ($locationId > 0) {
                $note .= ' Transfers out are included only when All stores is selected.';
            }
            return self::ok('Mobile money', $note, [
                ['key' => 'component', 'label' => 'Component'],
                ['key' => 'detail', 'label' => 'What it is'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'end'],
            ], [
                self::valueRow('Received', 'Shop share of Flutterwave charges, after fees', 'mobile_in', $received),
                self::valueRow('Sent out', 'Transfers that left the Flutterwave balance', 'mobile_out', -1 * $sent),
            ]);
        }
        if ($metric === 'mobile_in') {
            return self::mobileInRows($locationId);
        }
        if ($metric === 'mobile_out') {
            return self::mobileOutRows($locationId);
        }
        if ($metric === 'debtors') {
            return self::debtorRows($locationId);
        }
        return self::liabilityRows($locationId);
    }

    /** @return array{cash:float,bank:float,cash_lines:list<array<string,mixed>>,bank_lines:list<array<string,mixed>>} */
    private static function tenders(int $locationId, bool $lines): array
    {
        if (function_exists('ensureSaleSplitPaymentsColumn')) {
            ensureSaleSplitPaymentsColumn(self::db());
        }
        $params = [];
        $loc = self::loc('s', $locationId, $params);
        $stmt = self::db()->prepare(
            "SELECT s.id, s.created_at, s.payment_method, s.final_total, s.change_due, s.customer_name,
                    s.split_method_1, s.split_amount_1, s.split_method_2, s.split_amount_2, s.split_payments
             FROM sales s
             WHERE s.payment_status = 'paid' AND s.final_total <> 0 {$loc}
             ORDER BY s.created_at DESC, s.id DESC"
        );
        $stmt->execute($params);
        $cash = 0.0;
        $bank = 0.0;
        $cashLines = [];
        $bankLines = [];
        foreach ($stmt->fetchAll() ?: [] as $sale) {
            foreach (self::legs($sale) as $leg) {
                $amount = (float)$leg['amount'];
                if (abs($amount) < 0.004) {
                    continue;
                }
                $purse = self::purse((string)$leg['method']);
                if ($purse === 'mobile') {
                    continue;
                }
                if ($purse === 'cash') {
                    $cash += $amount;
                } else {
                    $bank += $amount;
                }
                if (!$lines) {
                    continue;
                }
                $row = [
                    'cells' => [
                        'when' => date('d M Y H:i', strtotime((string)$sale['created_at'])),
                        'receipt' => '#' . (int)$sale['id'],
                        'method' => (string)$leg['method'],
                        'amount' => self::money($amount),
                    ],
                    'drill' => ['level' => 'sale', 'sale_id' => (int)$sale['id']],
                    'sort' => (string)$sale['created_at'],
                ];
                if ($purse === 'cash') {
                    $cashLines[] = $row;
                } else {
                    $bankLines[] = $row;
                }
            }
        }
        return [
            'cash' => round($cash, 2),
            'bank' => round($bank, 2),
            'cash_lines' => $cashLines,
            'bank_lines' => $bankLines,
        ];
    }

    /** @param array<string, mixed> $sale @return list<array{method:string,amount:float}> */
    private static function legs(array $sale): array
    {
        if (($sale['payment_method'] ?? '') === 'Split' && function_exists('saleSplitLegs')) {
            $legs = saleSplitLegs($sale);
            if (function_exists('applySplitChangeToCash')) {
                $legs = applySplitChangeToCash($legs, (float)($sale['change_due'] ?? 0));
            }
            $sum = 0.0;
            foreach ($legs as $leg) {
                $sum += (float)$leg['amount'];
            }
            if ((float)$sale['final_total'] < 0 && $sum > 0) {
                foreach ($legs as $i => $leg) {
                    $legs[$i]['amount'] = -1 * (float)$leg['amount'];
                }
            }
            return $legs;
        }
        return [['method' => (string)($sale['payment_method'] ?: 'Cash'), 'amount' => (float)($sale['final_total'] ?? 0)]];
    }

    private static function purse(string $method): string
    {
        $method = strtolower(trim($method));
        if ($method === '' || str_contains($method, 'cash')) {
            return 'cash';
        }
        if (
            str_contains($method, 'flutter') || str_contains($method, 'momo') || str_contains($method, 'mobile')
            || str_contains($method, 'airtel') || str_contains($method, 'mtn') || str_contains($method, 'zamtel')
        ) {
            return 'mobile';
        }
        return 'bank';
    }

    /** @return array{cash:float,bank:float} */
    private static function tipTotals(int $locationId): array
    {
        $params = [];
        $loc = self::loc('t', $locationId, $params);
        try {
            $stmt = self::db()->prepare(
                "SELECT payment_method, COALESCE(SUM(amount), 0) AS amount FROM sale_tips t WHERE 1 = 1 {$loc} GROUP BY payment_method"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return ['cash' => 0.0, 'bank' => 0.0];
        }
        $cash = 0.0;
        $bank = 0.0;
        foreach ($rows as $row) {
            $purse = self::purse((string)$row['payment_method']);
            if ($purse === 'cash') {
                $cash += (float)$row['amount'];
            } elseif ($purse === 'bank') {
                $bank += (float)$row['amount'];
            }
        }
        return ['cash' => round($cash, 2), 'bank' => round($bank, 2)];
    }

    private static function expenseTotal(int $locationId): float
    {
        $params = [];
        $loc = self::loc('e', $locationId, $params);
        $stmt = self::db()->prepare("SELECT COALESCE(SUM(e.amount), 0) FROM expenses e WHERE 1 = 1 {$loc}");
        $stmt->execute($params);
        return round((float)$stmt->fetchColumn(), 2);
    }

    private static function drawerTotal(int $locationId): float
    {
        $params = [];
        $loc = self::loc('sh', $locationId, $params);
        $stmt = self::db()->prepare("SELECT COALESCE(SUM(sh.variance), 0) FROM shifts sh WHERE sh.status = 'closed' {$loc}");
        $stmt->execute($params);
        return round((float)$stmt->fetchColumn(), 2);
    }

    private static function mobileNet(int $locationId): float
    {
        [$received, $sent] = self::mobileParts($locationId);
        return round($received - $sent, 2);
    }

    /** @return array{0:float,1:float} */
    private static function mobileParts(int $locationId): array
    {
        $params = [];
        $loc = self::loc('pt', $locationId, $params);
        $stmt = self::db()->prepare(
            "SELECT COALESCE(SUM(COALESCE(pt.subaccount_amount, pt.net_amount, pt.amount - COALESCE(pt.app_fee, 0))), 0)
             FROM payment_transactions pt
             WHERE pt.provider = 'flutterwave' AND pt.status = 'successful' AND pt.tx_ref NOT LIKE 'SUP-%' {$loc}"
        );
        $stmt->execute($params);
        $received = round((float)$stmt->fetchColumn(), 2);
        $sent = 0.0;
        if ($locationId <= 0) {
            $out = self::db()->query(
                "SELECT COALESCE(SUM(t.amount + COALESCE(t.fee, 0)), 0)
                 FROM flutterwave_transfers t
                 WHERE t.status = 'successful'
                   AND NOT EXISTS (
                       SELECT 1 FROM payment_transactions pt
                       WHERE pt.tx_ref = t.reference AND pt.tx_ref LIKE 'SUP-%'
                   )"
            );
            $sent = round((float)$out->fetchColumn(), 2);
        }
        return [$received, $sent];
    }

    private static function debtorTotal(int $locationId): float
    {
        $params = [];
        $loc = self::loc('s', $locationId, $params);
        $stmt = self::db()->prepare(
            "SELECT COALESCE(SUM(s.final_total), 0)
             FROM sales s
             WHERE s.payment_status IN ('pending', 'unpaid') AND s.final_total > 0
               AND EXISTS (
                   SELECT 1 FROM sale_items si
                   WHERE si.sale_id = s.id AND si.status NOT IN ('voided', 'refunded')
               ) {$loc}"
        );
        $stmt->execute($params);
        return round((float)$stmt->fetchColumn(), 2);
    }

    private static function supplierOwed(int $locationId): float
    {
        $params = [];
        $loc = self::loc('o', $locationId, $params);
        $stmt = self::db()->prepare(
            "SELECT o.total_cost,
                    (SELECT COALESCE(SUM(t.amount), 0) FROM flutterwave_transfers t
                     WHERE t.purchase_order_id = o.id AND t.status = 'successful') AS paid
             FROM purchase_orders o
             WHERE o.status IN ('received', 'partial') {$loc}"
        );
        $stmt->execute($params);
        $owed = 0.0;
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $left = round((float)$row['total_cost'] - (float)$row['paid'], 2);
            if ($left > 0.004) {
                $owed += $left;
            }
        }
        return round($owed, 2);
    }

    private static function wagesOwed(int $locationId): float
    {
        try {
            if ($locationId > 0) {
                $stmt = self::db()->prepare(
                    "SELECT COALESCE(SUM(p.net_pay), 0)
                     FROM payslips p
                     JOIN payroll_runs r ON r.id = p.run_id
                     JOIN employees e ON e.id = p.employee_id
                     WHERE r.status = 'approved' AND e.location_id = ?"
                );
                $stmt->execute([$locationId]);
            } else {
                $stmt = self::db()->query("SELECT COALESCE(SUM(total_net), 0) FROM payroll_runs WHERE status = 'approved'");
            }
            return round((float)$stmt->fetchColumn(), 2);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }

    /** @param list<array<string, mixed>> $lines @return array<string, mixed> */
    private static function tenderRows(string $title, string $note, array $lines): array
    {
        $count = count($lines);
        $rows = array_slice($lines, 0, 200);
        foreach ($rows as &$row) {
            unset($row['sort']);
        }
        unset($row);
        $shown = $note;
        if ($count > count($rows)) {
            $shown .= ' Showing the latest ' . count($rows) . ' of ' . $count . '.';
        }
        return self::ok($title, $shown, [
            ['key' => 'when', 'label' => 'When'],
            ['key' => 'receipt', 'label' => 'Receipt'],
            ['key' => 'method', 'label' => 'Method'],
            ['key' => 'amount', 'label' => 'Amount', 'align' => 'end'],
        ], $rows);
    }

    /** @return array<string, mixed> */
    private static function expenseRows(int $locationId): array
    {
        $params = [];
        $loc = self::loc('e', $locationId, $params);
        $countStmt = self::db()->prepare("SELECT COUNT(*) FROM expenses e WHERE 1 = 1 {$loc}");
        $countStmt->execute($params);
        $count = (int)$countStmt->fetchColumn();
        $stmt = self::db()->prepare(
            "SELECT e.created_at, e.amount, e.reason, e.description
             FROM expenses e
             WHERE 1 = 1 {$loc}
             ORDER BY e.created_at DESC, e.id DESC
             LIMIT 200"
        );
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $why = trim((string)($row['reason'] ?: $row['description'] ?: 'Payout'));
            $rows[] = [
                'cells' => [
                    'when' => date('d M Y H:i', strtotime((string)$row['created_at'])),
                    'detail' => $why,
                    'amount' => self::money(-1 * (float)$row['amount']),
                ],
                'drill' => null,
            ];
        }
        $note = 'Each payout left the drawer. This is the last detail for till payouts.';
        if ($count > count($rows)) {
            $note .= ' Showing the latest ' . count($rows) . ' of ' . $count . '.';
        }
        return self::ok('Till payouts', $note, [
            ['key' => 'when', 'label' => 'When'],
            ['key' => 'detail', 'label' => 'Reason'],
            ['key' => 'amount', 'label' => 'Amount', 'align' => 'end'],
        ], $rows);
    }

    /** @return array<string, mixed> */
    private static function drawerRows(int $locationId): array
    {
        $params = [];
        $loc = self::loc('sh', $locationId, $params);
        $stmt = self::db()->prepare(
            "SELECT sh.end_time, sh.variance, l.name AS location_name, u.full_name
             FROM shifts sh
             LEFT JOIN locations l ON l.id = sh.location_id
             LEFT JOIN users u ON u.id = sh.user_id
             WHERE sh.status = 'closed' AND ABS(sh.variance) >= 0.004 {$loc}
             ORDER BY sh.end_time DESC
             LIMIT 200"
        );
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'cells' => [
                    'when' => $row['end_time'] ? date('d M Y H:i', strtotime((string)$row['end_time'])) : '',
                    'store' => (string)($row['location_name'] ?? ''),
                    'who' => (string)($row['full_name'] ?? ''),
                    'amount' => self::money((float)$row['variance']),
                ],
                'drill' => null,
            ];
        }
        return self::ok('Drawer count', 'A positive amount was over. A negative amount was short. This is the last detail for the drawer count.', [
            ['key' => 'when', 'label' => 'Closed'],
            ['key' => 'store', 'label' => 'Store'],
            ['key' => 'who', 'label' => 'Cashier'],
            ['key' => 'amount', 'label' => 'Over or short', 'align' => 'end'],
        ], $rows);
    }

    /** @return array<string, mixed> */
    private static function mobileInRows(int $locationId): array
    {
        $params = [];
        $loc = self::loc('pt', $locationId, $params);
        $countStmt = self::db()->prepare(
            "SELECT COUNT(*) FROM payment_transactions pt
             WHERE pt.provider = 'flutterwave' AND pt.status = 'successful' AND pt.tx_ref NOT LIKE 'SUP-%' {$loc}"
        );
        $countStmt->execute($params);
        $count = (int)$countStmt->fetchColumn();
        $stmt = self::db()->prepare(
            "SELECT pt.id, pt.created_at, pt.tx_ref, pt.payment_type, pt.network,
                    COALESCE(pt.subaccount_amount, pt.net_amount, pt.amount - COALESCE(pt.app_fee, 0)) AS shop_share
             FROM payment_transactions pt
             WHERE pt.provider = 'flutterwave' AND pt.status = 'successful' AND pt.tx_ref NOT LIKE 'SUP-%' {$loc}
             ORDER BY pt.created_at DESC, pt.id DESC
             LIMIT 200"
        );
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $tx) {
            $rows[] = [
                'cells' => [
                    'when' => date('d M Y H:i', strtotime((string)$tx['created_at'])),
                    'ref' => (string)$tx['tx_ref'],
                    'type' => trim((string)($tx['payment_type'] ?? '') . ' ' . (string)($tx['network'] ?? '')),
                    'amount' => self::money((float)$tx['shop_share']),
                ],
                'drill' => ['level' => 'charge', 'tx_id' => (int)$tx['id']],
            ];
        }
        $note = 'Each charge is the shop’s share after the Flutterwave fee. Open it for the payment and the receipt.';
        if ($count > count($rows)) {
            $note .= ' Showing the latest ' . count($rows) . ' of ' . $count . '.';
        }
        return self::ok('Received', $note, [
            ['key' => 'when', 'label' => 'When'],
            ['key' => 'ref', 'label' => 'Reference'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'amount', 'label' => 'Shop share', 'align' => 'end'],
        ], $rows);
    }

    /** @return array<string, mixed> */
    private static function mobileOutRows(int $locationId): array
    {
        if ($locationId > 0) {
            return self::ok('Sent out', 'Transfers leave the Flutterwave balance for the whole business. Choose All stores to see them.', [
                ['key' => 'when', 'label' => 'When'],
                ['key' => 'who', 'label' => 'Paid to'],
                ['key' => 'detail', 'label' => 'Reason'],
                ['key' => 'amount', 'label' => 'Amount', 'align' => 'end'],
            ], []);
        }
        $stmt = self::db()->query(
            "SELECT t.created_at, t.amount, t.fee, t.beneficiary_name, t.narration
             FROM flutterwave_transfers t
             WHERE t.status = 'successful'
               AND NOT EXISTS (
                   SELECT 1 FROM payment_transactions pt
                   WHERE pt.tx_ref = t.reference AND pt.tx_ref LIKE 'SUP-%'
               )
             ORDER BY t.created_at DESC, t.id DESC
             LIMIT 200"
        );
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $rows[] = [
                'cells' => [
                    'when' => date('d M Y H:i', strtotime((string)$row['created_at'])),
                    'who' => (string)$row['beneficiary_name'],
                    'detail' => (string)$row['narration'],
                    'amount' => self::money(-1 * ((float)$row['amount'] + (float)$row['fee'])),
                ],
                'drill' => null,
            ];
        }
        return self::ok('Sent out', 'These transfers left the Flutterwave balance. A supplier paid with the shop’s own card or phone is not in this list. This is the last detail.', [
            ['key' => 'when', 'label' => 'When'],
            ['key' => 'who', 'label' => 'Paid to'],
            ['key' => 'detail', 'label' => 'Reason'],
            ['key' => 'amount', 'label' => 'Amount', 'align' => 'end'],
        ], $rows);
    }

    /** @return array<string, mixed> */
    private static function debtorRows(int $locationId): array
    {
        $params = [];
        $loc = self::loc('s', $locationId, $params);
        $stmt = self::db()->prepare(
            "SELECT s.id, s.created_at, s.final_total, s.customer_name, l.name AS location_name
             FROM sales s
             LEFT JOIN locations l ON l.id = s.location_id
             WHERE s.payment_status IN ('pending', 'unpaid') AND s.final_total > 0
               AND EXISTS (
                   SELECT 1 FROM sale_items si
                   WHERE si.sale_id = s.id AND si.status NOT IN ('voided', 'refunded')
               ) {$loc}
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT 200"
        );
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $sale) {
            $rows[] = [
                'cells' => [
                    'when' => date('d M Y H:i', strtotime((string)$sale['created_at'])),
                    'receipt' => '#' . (int)$sale['id'],
                    'who' => (string)($sale['customer_name'] ?: 'Walk-in'),
                    'store' => (string)($sale['location_name'] ?? ''),
                    'amount' => self::money((float)$sale['final_total']),
                ],
                'drill' => ['level' => 'sale', 'sale_id' => (int)$sale['id']],
            ];
        }
        return self::ok('Debtors', 'These customer bills are not paid. Open one for the items. This is money owed to the shop.', [
            ['key' => 'when', 'label' => 'When'],
            ['key' => 'receipt', 'label' => 'Receipt'],
            ['key' => 'who', 'label' => 'Customer'],
            ['key' => 'store', 'label' => 'Store'],
            ['key' => 'amount', 'label' => 'Owed', 'align' => 'end'],
        ], $rows);
    }

    /** @return array<string, mixed> */
    private static function liabilityRows(int $locationId): array
    {
        $params = [];
        $loc = self::loc('o', $locationId, $params);
        $stmt = self::db()->prepare(
            "SELECT o.reference_no, o.total_cost, o.status, v.name AS vendor,
                    (SELECT COALESCE(SUM(t.amount), 0) FROM flutterwave_transfers t
                     WHERE t.purchase_order_id = o.id AND t.status = 'successful') AS paid
             FROM purchase_orders o
             LEFT JOIN vendors v ON v.id = o.vendor_id
             WHERE o.status IN ('received', 'partial') {$loc}
             ORDER BY o.id DESC"
        );
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            $left = round((float)$row['total_cost'] - (float)$row['paid'], 2);
            if ($left <= 0.004) {
                continue;
            }
            $rows[] = [
                'cells' => [
                    'kind' => 'Supplier',
                    'name' => (string)($row['vendor'] ?: 'Supplier'),
                    'detail' => trim((string)($row['reference_no'] ?: 'Purchase order') . ' · ' . (string)$row['status']),
                    'amount' => self::money($left),
                ],
                'drill' => null,
            ];
        }
        try {
            if ($locationId > 0) {
                $wages = self::db()->prepare(
                    "SELECT r.title, r.pay_date, COALESCE(SUM(p.net_pay), 0) AS net
                     FROM payroll_runs r
                     JOIN payslips p ON p.run_id = r.id
                     JOIN employees e ON e.id = p.employee_id
                     WHERE r.status = 'approved' AND e.location_id = ?
                     GROUP BY r.id, r.title, r.pay_date
                     HAVING net > 0"
                );
                $wages->execute([$locationId]);
                $wageRows = $wages->fetchAll() ?: [];
            } else {
                $wageRows = self::db()->query(
                    "SELECT title, pay_date, total_net AS net FROM payroll_runs WHERE status = 'approved' AND total_net > 0"
                )->fetchAll() ?: [];
            }
            foreach ($wageRows as $row) {
                $rows[] = [
                    'cells' => [
                        'kind' => 'Wages',
                        'name' => (string)$row['title'],
                        'detail' => $row['pay_date'] ? ('Pay date ' . date('d M Y', strtotime((string)$row['pay_date']))) : 'Approved, not paid',
                        'amount' => self::money((float)$row['net']),
                    ],
                    'drill' => null,
                ];
            }
        } catch (\Throwable $e) {
        }
        return self::ok('Liabilities', 'Unpaid supplier bills are for goods already received. Wages are pay runs that are approved and not paid yet. Both are taken off the business value. This is the last detail.', [
            ['key' => 'kind', 'label' => 'Kind'],
            ['key' => 'name', 'label' => 'Who'],
            ['key' => 'detail', 'label' => 'Detail'],
            ['key' => 'amount', 'label' => 'Owed', 'align' => 'end'],
        ], $rows);
    }

    private static function loc(string $alias, int $locationId, array &$params): string
    {
        if ($locationId <= 0) {
            return '';
        }
        $params[] = $locationId;
        return ' AND ' . $alias . '.location_id = ?';
    }

    private static function monthsBetween(string $from, string $to): int
    {
        if ($to < $from) {
            return 0;
        }
        $start = new \DateTimeImmutable($from);
        $end = new \DateTimeImmutable($to);
        $months = ((int)$end->format('Y') - (int)$start->format('Y')) * 12 + ((int)$end->format('n') - (int)$start->format('n'));
        if ((int)$end->format('j') < (int)$start->format('j')) {
            $months--;
        }
        return max(0, $months);
    }

    private static function money(float $amount): string
    {
        return 'ZMW ' . number_format($amount, 2);
    }

    /**
     * @param list<array{key:string,label:string,align?:string}> $columns
     * @param list<array<string, mixed>> $rows
     * @param list<array{label:string,value:string}> $facts
     * @return array<string, mixed>
     */
    private static function ok(string $title, string $note, array $columns, array $rows, array $facts = []): array
    {
        return [
            'status' => 'ok',
            'title' => $title,
            'note' => $note,
            'columns' => $columns,
            'rows' => $rows,
            'facts' => $facts,
            'count' => count($rows),
        ];
    }
}
