<?php

namespace App\Services;

use App\Models\Report;
use PDO;

/**
 * Figures for the owner dashboard, and the rows behind each card.
 * A card opens the receipts or batches that make the figure.
 * A receipt or batch opens its lines. That is the last detail.
 */
final class OwnerOverview
{
    private const LIMIT = 200;

    public function __construct(private PDO $pdo)
    {
    }

    /** @param array<string, mixed> $query @return array{start:string,end:string,daypart:string,location:int,employee:int} */
    public static function filters(array $query): array
    {
        $today = date('Y-m-d');
        $start = self::date((string)($query['start'] ?? ''), $today);
        $end = self::date((string)($query['end'] ?? ''), $today);
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }
        $daypart = (string)($query['daypart'] ?? '');
        if (!in_array($daypart, ['morning', 'afternoon', 'evening', 'night'], true)) {
            $daypart = '';
        }
        return [
            'start' => $start,
            'end' => $end,
            'daypart' => $daypart,
            'location' => max(0, (int)($query['location'] ?? 0)),
            'employee' => max(0, (int)($query['employee_id'] ?? 0)),
        ];
    }

    /** @param array{start:string,end:string,daypart:string,location:int,employee:int} $filters @return array<string, float|int> */
    public function figures(array $filters): array
    {
        [$where, $params] = $this->bounds($filters);
        $netWhere = $where . " AND s.payment_status = 'paid' AND s.final_total >= 0";
        $refundWhere = $where . " AND (s.final_total < 0 OR s.payment_status = 'refunded')";
        $net = $this->pair("SELECT COALESCE(SUM(s.final_total), 0), COUNT(*) FROM sales s WHERE {$netWhere}", $params);
        $refund = $this->pair("SELECT COALESCE(SUM(ABS(s.final_total)), 0), COUNT(*) FROM sales s WHERE {$refundWhere}", $params);
        $discount = $this->pair(
            "SELECT COALESCE(SUM(s.discount), 0), COALESCE(SUM(s.discount > 0), 0) FROM sales s WHERE {$netWhere}",
            $params
        );
        $costPrice = $this->one(
            "SELECT COALESCE(SUM(si.cost_at_sale * si.quantity), 0)
             FROM sale_items si
             JOIN sales s ON s.id = si.sale_id
             WHERE {$netWhere}
               AND COALESCE(si.is_refund, 0) = 0
               AND si.status NOT IN ('voided', 'refunded')",
            $params
        );
        $tax = $this->one("SELECT COALESCE(SUM(s.total_tax), 0) FROM sales s WHERE {$netWhere}", $params);
        $fees = $this->feeTotal($filters);
        $netAmount = $net[0];
        return [
            'gross' => round($netAmount + $refund[0], 2),
            'gross_count' => $net[1] + $refund[1],
            'refunds' => round($refund[0], 2),
            'refund_count' => $refund[1],
            'discounts' => round($discount[0], 2),
            'discount_count' => $discount[1],
            'net' => round($netAmount, 2),
            'net_count' => $net[1],
            'cost_price' => round($costPrice, 2),
            'tax' => round($tax, 2),
            'fees' => round($fees, 2),
            'cost' => round($costPrice + $tax + $fees, 2),
            'profit' => round($netAmount - $costPrice, 2),
        ];
    }

    /**
     * @param array<string, mixed> $post
     * @return array<string, mixed>
     */
    public function drill(array $post): array
    {
        $filters = self::filters($post);
        $level = (string)($post['level'] ?? '');
        $metric = (string)($post['metric'] ?? '');
        if ($level === 'metric') {
            if ($metric === 'cost') {
                return $this->costComponents($filters);
            }
            if ($metric === 'fees') {
                return $this->feeRows($filters);
            }
            return $this->metric($metric, $filters);
        }
        if (in_array($level, ['value', 'asset', 'stock'], true)) {
            return \App\Models\Asset::drill($post);
        }
        if ($level === 'sale') {
            return $this->sale((int)($post['sale_id'] ?? 0));
        }
        if ($level === 'settle') {
            return $this->batches($metric);
        }
        if ($level === 'batch') {
            return $this->batch((string)($post['settlement_id'] ?? ''));
        }
        if ($level === 'charge') {
            return $this->charge((int)($post['tx_id'] ?? 0));
        }
        return ['status' => 'error', 'msg' => 'That detail is not available.'];
    }

    /** @param array{start:string,end:string,daypart:string,location:int,employee:int} $filters @return array<string, mixed> */
    private function metric(string $metric, array $filters): array
    {
        [$where, $params] = $this->bounds($filters);
        $titles = [
            'gross' => 'Gross sales',
            'refunds' => 'Refunds',
            'discounts' => 'Discounts',
            'net' => 'Net sales',
            'profit' => 'Gross profit',
            'cost_price' => 'Cost price',
            'tax' => 'Tax',
        ];
        if (!isset($titles[$metric])) {
            return ['status' => 'error', 'msg' => 'That figure is not on this dashboard.'];
        }
        $extra = match ($metric) {
            'refunds' => " AND (s.final_total < 0 OR s.payment_status = 'refunded')",
            'discounts' => " AND s.payment_status = 'paid' AND s.final_total >= 0 AND s.discount > 0",
            'tax' => " AND s.payment_status = 'paid' AND s.final_total >= 0 AND s.total_tax <> 0",
            'gross' => " AND (s.payment_status = 'paid' OR s.final_total < 0 OR s.payment_status = 'refunded')",
            default => " AND s.payment_status = 'paid' AND s.final_total >= 0",
        };
        $count = (int)$this->one("SELECT COUNT(*) FROM sales s WHERE {$where}{$extra}", $params);
        $stmt = $this->pdo->prepare(
            "SELECT s.id, s.created_at, s.final_total, s.discount, s.total_tax, s.payment_method, s.payment_status, s.customer_name,
                    l.name AS location_name, u.full_name AS employee,
                    COALESCE((
                        SELECT SUM(si.cost_at_sale * si.quantity)
                        FROM sale_items si
                        WHERE si.sale_id = s.id AND COALESCE(si.is_refund, 0) = 0 AND si.status NOT IN ('voided', 'refunded')
                    ), 0) AS line_cost
             FROM sales s
             LEFT JOIN locations l ON l.id = s.location_id
             LEFT JOIN users u ON u.id = s.user_id
             WHERE {$where}{$extra}
             ORDER BY s.created_at DESC, s.id DESC
             LIMIT " . self::LIMIT
        );
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $sale) {
            $total = (float)$sale['final_total'];
            $refund = $total < 0 || strtolower((string)$sale['payment_status']) === 'refunded';
            $cost = (float)$sale['line_cost'];
            $shown = match ($metric) {
                'discounts' => (float)$sale['discount'],
                'tax' => (float)$sale['total_tax'],
                'cost_price' => $cost,
                'profit' => $total - $cost,
                'refunds' => abs($total),
                default => $refund ? abs($total) : $total,
            };
            $cells = [
                'when' => $this->when((string)$sale['created_at']),
                'receipt' => '#' . (int)$sale['id'] . ($refund ? ' refund' : ''),
                'store' => (string)($sale['location_name'] ?? ''),
                'employee' => (string)($sale['employee'] ?? ''),
                'detail' => trim((string)($sale['customer_name'] ?: 'Walk-in') . ' · ' . (string)$sale['payment_method']),
            ];
            if ($metric === 'profit') {
                $cells['cost'] = $this->money($cost);
            }
            if ($metric === 'discounts') {
                $cells['receipt_total'] = $this->money($total);
            }
            $cells['amount'] = $this->money($shown);
            $rows[] = [
                'cells' => $cells,
                'drill' => ['level' => 'sale', 'sale_id' => (int)$sale['id']],
            ];
        }
        $columns = [
            ['key' => 'when', 'label' => 'When'],
            ['key' => 'receipt', 'label' => 'Receipt'],
            ['key' => 'store', 'label' => 'Store'],
            ['key' => 'employee', 'label' => 'Employee'],
            ['key' => 'detail', 'label' => 'Customer'],
        ];
        if ($metric === 'profit') {
            $columns[] = ['key' => 'cost', 'label' => 'Cost', 'align' => 'end'];
        }
        if ($metric === 'discounts') {
            $columns[] = ['key' => 'receipt_total', 'label' => 'Receipt total', 'align' => 'end'];
        }
        $amountLabel = match ($metric) {
            'profit' => 'Profit',
            'tax' => 'Tax',
            'cost_price' => 'Cost price',
            default => 'Amount',
        };
        $columns[] = ['key' => 'amount', 'label' => $amountLabel, 'align' => 'end'];
        $note = 'Open a receipt to see each item.';
        if ($metric === 'profit') {
            $note = 'Profit on a receipt is its total minus the cost price recorded when it was sold. Open a receipt to see each item.';
        } elseif ($metric === 'cost_price') {
            $note = 'Cost price is what the items cost when they were sold. Open a receipt to see each item.';
        } elseif ($metric === 'tax') {
            $note = 'Tax is the amount already on the receipt. Open a receipt to see each item.';
        }
        return $this->ok($titles[$metric], $note, $columns, $rows, $count);
    }

    /** @return array<string, mixed> */
    private function sale(int $saleId): array
    {
        if ($saleId <= 0) {
            return ['status' => 'error', 'msg' => 'That receipt is not available.'];
        }
        $stmt = $this->pdo->prepare(
            "SELECT s.*, l.name AS location_name, u.full_name AS employee
             FROM sales s
             LEFT JOIN locations l ON l.id = s.location_id
             LEFT JOIN users u ON u.id = s.user_id
             WHERE s.id = ?"
        );
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch();
        if (!$sale) {
            return ['status' => 'error', 'msg' => 'That receipt is not available.'];
        }
        $items = $this->pdo->prepare(
            "SELECT COALESCE(p.name, 'Custom item') AS name, si.quantity, si.price, si.cost_at_sale, si.status
             FROM sale_items si
             LEFT JOIN products p ON p.id = si.product_id
             WHERE si.sale_id = ?
             ORDER BY si.id"
        );
        $items->execute([$saleId]);
        $rows = [];
        foreach ($items->fetchAll() ?: [] as $item) {
            $qty = (float)$item['quantity'];
            $price = (float)$item['price'];
            $cost = (float)$item['cost_at_sale'];
            $rows[] = [
                'cells' => [
                    'item' => (string)$item['name'],
                    'qty' => $this->qty($qty),
                    'price' => $this->money($price),
                    'cost' => $this->money($cost),
                    'line' => $this->money($price * $qty),
                    'profit' => $this->money(($price - $cost) * $qty),
                    'status' => (string)($item['status'] ?? ''),
                ],
                'drill' => null,
            ];
        }
        $facts = [
            ['label' => 'When', 'value' => $this->when((string)$sale['created_at'])],
            ['label' => 'Store', 'value' => (string)($sale['location_name'] ?? '')],
            ['label' => 'Employee', 'value' => (string)($sale['employee'] ?? '')],
            ['label' => 'Customer', 'value' => (string)($sale['customer_name'] ?: 'Walk-in')],
            ['label' => 'Payment', 'value' => (string)$sale['payment_method']],
            ['label' => 'Status', 'value' => (string)$sale['payment_status']],
            ['label' => 'Subtotal', 'value' => $this->money((float)$sale['subtotal'])],
            ['label' => 'Discount', 'value' => $this->money((float)$sale['discount'])],
            ['label' => 'Tax', 'value' => $this->money((float)$sale['total_tax'])],
            ['label' => 'Tip', 'value' => $this->money((float)($sale['tip_amount'] ?? 0))],
            ['label' => 'Surcharge', 'value' => $this->money((float)($sale['surcharge_amount'] ?? 0))],
            ['label' => 'Total', 'value' => $this->money((float)$sale['final_total'])],
        ];
        return $this->ok(
            'Receipt #' . $saleId,
            'Each line is the item as it was sold. This is the last detail for the receipt.',
            [
                ['key' => 'item', 'label' => 'Item'],
                ['key' => 'qty', 'label' => 'Qty', 'align' => 'end'],
                ['key' => 'price', 'label' => 'Price', 'align' => 'end'],
                ['key' => 'cost', 'label' => 'Cost', 'align' => 'end'],
                ['key' => 'line', 'label' => 'Line', 'align' => 'end'],
                ['key' => 'profit', 'label' => 'Profit', 'align' => 'end'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            $rows,
            count($rows),
            $facts
        );
    }

    /** @return array<string, mixed> */
    private function batches(string $metric): array
    {
        $titles = [
            'due' => 'Due to the subaccount',
            'fees' => 'Fees on subaccount payouts',
            'waiting' => 'Waiting on Flutterwave',
            'paid' => 'Paid to the subaccount',
        ];
        if (!isset($titles[$metric])) {
            return ['status' => 'error', 'msg' => 'That settlement figure is not on this dashboard.'];
        }
        $subId = $this->subaccountId();
        if ($subId === '') {
            return $this->ok($titles[$metric], 'An administrator adds the Flutterwave subaccount under Settings, Payments.', [], [], 0);
        }
        $extra = match ($metric) {
            'waiting' => ' AND status <> "completed"',
            'paid' => ' AND status = "completed"',
            'fees' => ' AND app_fee <> 0',
            default => '',
        };
        $count = (int)$this->one(
            "SELECT COUNT(*) FROM flutterwave_settlements WHERE subaccount_id = ?{$extra}",
            [$subId]
        );
        $stmt = $this->pdo->prepare(
            "SELECT settlement_id, status, gross_amount, app_fee, net_amount, transaction_count, transaction_date, processed_date
             FROM flutterwave_settlements
             WHERE subaccount_id = ?{$extra}
             ORDER BY COALESCE(processed_date, transaction_date, updated_at) DESC
             LIMIT " . self::LIMIT
        );
        $stmt->execute([$subId]);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $batch) {
            $id = (string)$batch['settlement_id'];
            $when = (string)($batch['processed_date'] ?: $batch['transaction_date'] ?: '');
            $amount = match ($metric) {
                'fees' => (float)$batch['app_fee'],
                'due' => (float)$batch['gross_amount'],
                default => (float)$batch['net_amount'],
            };
            $rows[] = [
                'cells' => [
                    'batch' => $id,
                    'when' => $when !== '' ? date('d M Y', strtotime($when)) : '—',
                    'gross' => $this->money((float)$batch['gross_amount']),
                    'fee' => $this->money((float)$batch['app_fee']),
                    'net' => $this->money((float)$batch['net_amount']),
                    'count' => (string)(int)$batch['transaction_count'],
                    'status' => ucfirst((string)$batch['status']),
                    'amount' => $this->money($amount),
                ],
                'drill' => ['level' => 'batch', 'settlement_id' => $id],
            ];
        }
        return $this->ok(
            $titles[$metric],
            'Open a batch to see the charges Flutterwave put in it.',
            [
                ['key' => 'batch', 'label' => 'Batch'],
                ['key' => 'when', 'label' => 'When'],
                ['key' => 'gross', 'label' => 'Gross', 'align' => 'end'],
                ['key' => 'fee', 'label' => 'Fee', 'align' => 'end'],
                ['key' => 'net', 'label' => 'Net', 'align' => 'end'],
                ['key' => 'count', 'label' => 'Charges', 'align' => 'end'],
                ['key' => 'status', 'label' => 'Status'],
            ],
            $rows,
            $count
        );
    }

    /** @return array<string, mixed> */
    private function batch(string $settlementId): array
    {
        $settlementId = trim($settlementId);
        if ($settlementId === '' || strlen($settlementId) > 80) {
            return ['status' => 'error', 'msg' => 'That batch is not available.'];
        }
        $subId = $this->subaccountId();
        $head = $this->pdo->prepare(
            'SELECT settlement_id, status, gross_amount, app_fee, net_amount, transaction_count, transaction_date, processed_date, destination, settlement_account
             FROM flutterwave_settlements WHERE subaccount_id = ? AND settlement_id = ? LIMIT 1'
        );
        $head->execute([$subId, $settlementId]);
        $batch = $head->fetch();
        if (!$batch) {
            return ['status' => 'error', 'msg' => 'That batch is not available.'];
        }
        $count = (int)$this->one(
            "SELECT COUNT(*) FROM payment_transactions WHERE provider = 'flutterwave' AND settlement_id = ? AND tx_ref NOT LIKE 'SUP-%'",
            [$settlementId]
        );
        $stmt = $this->pdo->prepare(
            "SELECT id, tx_ref, sale_id, amount, charged_amount, app_fee, net_amount, subaccount_amount, payment_type, created_at
             FROM payment_transactions
             WHERE provider = 'flutterwave' AND settlement_id = ? AND tx_ref NOT LIKE 'SUP-%'
             ORDER BY id DESC
             LIMIT " . self::LIMIT
        );
        $stmt->execute([$settlementId]);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $tx) {
            $charged = (float)($tx['charged_amount'] ?? 0) > 0 ? (float)$tx['charged_amount'] : (float)$tx['amount'];
            $rows[] = [
                'cells' => [
                    'when' => $this->when((string)$tx['created_at']),
                    'ref' => (string)$tx['tx_ref'],
                    'type' => (string)($tx['payment_type'] ?? ''),
                    'charged' => $this->money($charged),
                    'fee' => $this->money((float)$tx['app_fee']),
                    'net' => $this->money((float)($tx['subaccount_amount'] !== null && $tx['subaccount_amount'] !== '' ? $tx['subaccount_amount'] : $tx['net_amount'])),
                    'receipt' => (int)$tx['sale_id'] > 0 ? '#' . (int)$tx['sale_id'] : '—',
                ],
                'drill' => ['level' => 'charge', 'tx_id' => (int)$tx['id']],
            ];
        }
        $when = (string)($batch['processed_date'] ?: $batch['transaction_date'] ?: '');
        $facts = [
            ['label' => 'Status', 'value' => ucfirst((string)$batch['status'])],
            ['label' => 'When', 'value' => $when !== '' ? date('d M Y', strtotime($when)) : '—'],
            ['label' => 'Gross', 'value' => $this->money((float)$batch['gross_amount'])],
            ['label' => 'Fee', 'value' => $this->money((float)$batch['app_fee'])],
            ['label' => 'Net', 'value' => $this->money((float)$batch['net_amount'])],
            ['label' => 'Charges in the batch', 'value' => (string)(int)$batch['transaction_count']],
            ['label' => 'Destination', 'value' => trim((string)($batch['destination'] ?? '') . ' ' . (string)($batch['settlement_account'] ?? ''))],
        ];
        $note = $count === 0
            ? 'No charge on this till has been matched to the batch yet.'
            : 'Open a charge to see the payment and, when it belongs to a receipt, the items on that receipt.';
        return $this->ok('Batch ' . $settlementId, $note, [
            ['key' => 'when', 'label' => 'When'],
            ['key' => 'ref', 'label' => 'Reference'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'charged', 'label' => 'Charged', 'align' => 'end'],
            ['key' => 'fee', 'label' => 'Fee', 'align' => 'end'],
            ['key' => 'net', 'label' => 'To subaccount', 'align' => 'end'],
            ['key' => 'receipt', 'label' => 'Receipt'],
        ], $rows, $count, $facts);
    }

    /** @return array<string, mixed> */
    private function charge(int $txId): array
    {
        if ($txId <= 0) {
            return ['status' => 'error', 'msg' => 'That charge is not available.'];
        }
        $stmt = $this->pdo->prepare(
            "SELECT id, tx_ref, sale_id, amount, charged_amount, app_fee, net_amount, subaccount_amount, currency,
                    payment_type, network, customer_phone, settlement_status, settlement_id, created_at
             FROM payment_transactions
             WHERE id = ? AND provider = 'flutterwave' AND tx_ref NOT LIKE 'SUP-%'"
        );
        $stmt->execute([$txId]);
        $tx = $stmt->fetch();
        if (!$tx) {
            return ['status' => 'error', 'msg' => 'That charge is not available.'];
        }
        $charged = (float)($tx['charged_amount'] ?? 0) > 0 ? (float)$tx['charged_amount'] : (float)$tx['amount'];
        $facts = [
            ['label' => 'Reference', 'value' => (string)$tx['tx_ref']],
            ['label' => 'When', 'value' => $this->when((string)$tx['created_at'])],
            ['label' => 'Type', 'value' => trim((string)($tx['payment_type'] ?? '') . ' ' . (string)($tx['network'] ?? ''))],
            ['label' => 'Phone', 'value' => (string)($tx['customer_phone'] ?? '')],
            ['label' => 'Charged', 'value' => $this->money($charged)],
            ['label' => 'Fee', 'value' => $this->money((float)$tx['app_fee'])],
            ['label' => 'To the subaccount', 'value' => $this->money((float)($tx['subaccount_amount'] !== null && $tx['subaccount_amount'] !== '' ? $tx['subaccount_amount'] : $tx['net_amount']))],
            ['label' => 'Settlement', 'value' => trim((string)($tx['settlement_status'] ?? '') . ' ' . (string)($tx['settlement_id'] ?? ''))],
        ];
        $saleId = (int)$tx['sale_id'];
        if ($saleId <= 0) {
            return $this->ok('Charge ' . (string)$tx['tx_ref'], 'This charge is not linked to a receipt. These fields are the last detail.', [], [], 0, $facts);
        }
        $detail = $this->sale($saleId);
        if (($detail['status'] ?? '') !== 'ok') {
            return $detail;
        }
        $detail['title'] = 'Charge ' . (string)$tx['tx_ref'];
        $detail['facts'] = array_merge($facts, [['label' => 'Receipt', 'value' => '#' . $saleId]], $detail['facts'] ?? []);
        $detail['note'] = 'The payment is above. The lines are the items on the receipt. This is the last detail.';
        return $detail;
    }

    /** @param array{start:string,end:string,daypart:string,location:int,employee:int} $filters @return array<string, mixed> */
    private function costComponents(array $filters): array
    {
        $figures = $this->figures($filters);
        $rows = [
            ['component' => 'Cost price', 'detail' => 'What the items cost when they were sold', 'metric' => 'cost_price', 'amount' => (float)$figures['cost_price']],
            ['component' => 'Tax', 'detail' => 'Tax on the paid receipts', 'metric' => 'tax', 'amount' => (float)$figures['tax']],
            ['component' => 'Fees', 'detail' => 'Flutterwave fee on card and mobile money', 'metric' => 'fees', 'amount' => (float)$figures['fees']],
        ];
        $out = [];
        foreach ($rows as $row) {
            $out[] = [
                'cells' => [
                    'component' => $row['component'],
                    'detail' => $row['detail'],
                    'amount' => $this->money($row['amount']),
                ],
                'drill' => ['level' => 'metric', 'metric' => $row['metric']],
            ];
        }
        return $this->ok('Cost', 'Open a component to see the receipts or charges inside it.', [
            ['key' => 'component', 'label' => 'Component'],
            ['key' => 'detail', 'label' => 'What it is'],
            ['key' => 'amount', 'label' => 'Amount', 'align' => 'end'],
        ], $out, count($out));
    }

    /** @param array{start:string,end:string,daypart:string,location:int,employee:int} $filters */
    private function feeTotal(array $filters): float
    {
        [$where, $params] = $this->feeBounds($filters);
        return $this->one("SELECT COALESCE(SUM(pt.app_fee), 0) FROM payment_transactions pt WHERE {$where}", $params);
    }

    /** @param array{start:string,end:string,daypart:string,location:int,employee:int} $filters @return array<string, mixed> */
    private function feeRows(array $filters): array
    {
        [$where, $params] = $this->feeBounds($filters);
        $count = (int)$this->one("SELECT COUNT(*) FROM payment_transactions pt WHERE {$where}", $params);
        $stmt = $this->pdo->prepare(
            "SELECT id, tx_ref, sale_id, amount, charged_amount, app_fee, payment_type, created_at
             FROM payment_transactions pt
             WHERE {$where}
             ORDER BY pt.created_at DESC, pt.id DESC
             LIMIT " . self::LIMIT
        );
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() ?: [] as $tx) {
            $rows[] = [
                'cells' => [
                    'when' => $this->when((string)$tx['created_at']),
                    'ref' => (string)$tx['tx_ref'],
                    'type' => (string)($tx['payment_type'] ?? ''),
                    'charged' => $this->money((float)($tx['charged_amount'] ?? 0) > 0 ? $tx['charged_amount'] : $tx['amount']),
                    'amount' => $this->money((float)$tx['app_fee']),
                    'receipt' => (int)$tx['sale_id'] > 0 ? '#' . (int)$tx['sale_id'] : '—',
                ],
                'drill' => ['level' => 'charge', 'tx_id' => (int)$tx['id']],
            ];
        }
        return $this->ok('Fees', 'Open a charge to see the payment and, when it belongs to a receipt, the items.', [
            ['key' => 'when', 'label' => 'When'],
            ['key' => 'ref', 'label' => 'Reference'],
            ['key' => 'type', 'label' => 'Type'],
            ['key' => 'charged', 'label' => 'Charged', 'align' => 'end'],
            ['key' => 'amount', 'label' => 'Fee', 'align' => 'end'],
            ['key' => 'receipt', 'label' => 'Receipt'],
        ], $rows, $count);
    }

    /** @param array{start:string,end:string,daypart:string,location:int,employee:int} $filters @return array{0:string,1:list<int|string>} */
    private function feeBounds(array $filters): array
    {
        $params = [$filters['start'] . ' 00:00:00', $filters['end'] . ' 23:59:59'];
        $sql = "pt.provider = 'flutterwave' AND pt.status = 'successful' AND pt.tx_ref NOT LIKE 'SUP-%' AND pt.created_at BETWEEN ? AND ?";
        if ($filters['location'] > 0) {
            $sql .= ' AND pt.location_id = ?';
            $params[] = $filters['location'];
        }
        if ($filters['employee'] > 0) {
            $sql .= ' AND pt.user_id = ?';
            $params[] = $filters['employee'];
        }
        $sql .= str_replace('s.created_at', 'pt.created_at', Report::daypartClause($filters['daypart']));
        return [$sql, $params];
    }

    /** @param array{start:string,end:string,daypart:string,location:int,employee:int} $filters @return array{0:string,1:list<int|string>} */
    private function bounds(array $filters): array
    {
        $params = [$filters['start'] . ' 00:00:00', $filters['end'] . ' 23:59:59'];
        $sql = 's.created_at BETWEEN ? AND ?';
        if ($filters['location'] > 0) {
            $sql .= ' AND s.location_id = ?';
            $params[] = $filters['location'];
        }
        if ($filters['employee'] > 0) {
            $sql .= ' AND s.user_id = ?';
            $params[] = $filters['employee'];
        }
        $sql .= Report::daypartClause($filters['daypart']);
        return [$sql, $params];
    }

    private function subaccountId(): string
    {
        $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'flw_subaccount_id' LIMIT 1");
        $stmt->execute();
        return trim((string)$stmt->fetchColumn());
    }

    /** @param list<int|string> $params @return array{0:float,1:int} */
    private function pair(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(\PDO::FETCH_NUM) ?: [0, 0];
        return [(float)$row[0], (int)$row[1]];
    }

    /** @param list<int|string> $params */
    private function one(string $sql, array $params): float
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (float)$stmt->fetchColumn();
    }

    private function money(float $amount): string
    {
        return 'ZMW ' . number_format($amount, 2);
    }

    private function qty(float $qty): string
    {
        $text = rtrim(rtrim(number_format($qty, 2, '.', ''), '0'), '.');
        return $text === '' ? '0' : $text;
    }

    private function when(string $stamp): string
    {
        $time = strtotime($stamp);
        return $time ? date('d M Y H:i', $time) : $stamp;
    }

    private static function date(string $value, string $fallback): string
    {
        $value = trim($value);
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : $fallback;
    }

    /**
     * @param list<array{key:string,label:string,align?:string}> $columns
     * @param list<array<string, mixed>> $rows
     * @param list<array{label:string,value:string}> $facts
     * @return array<string, mixed>
     */
    private function ok(string $title, string $note, array $columns, array $rows, int $count, array $facts = []): array
    {
        if ($count > count($rows) && $rows !== []) {
            $note .= ' Showing the latest ' . count($rows) . ' of ' . $count . '.';
        }
        return [
            'status' => 'ok',
            'title' => $title,
            'note' => $note,
            'columns' => $columns,
            'rows' => $rows,
            'facts' => $facts,
            'count' => $count,
        ];
    }
}
