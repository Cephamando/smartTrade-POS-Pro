<?php

namespace App\Services;

use App\Models\Sale;
use PDO;

final class Refund
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * Book a credit-note sale, restore stock, and post it to ZRA.
     * The original Smart Invoice is left signed; ZRA only accepts a new rcptTyCd=R document.
     *
     * @param array<int,float> $qtyByItemId original sale_item id => qty to return
     * @return array{credit_sale_id:int, amount:float, zra_msg:string}
     */
    public function process(int $originalSaleId, array $qtyByItemId, int $userId, string $reason = 'Refund', string $rfdRsnCd = '07'): array
    {
        new \ZraVsdcClient($this->pdo);
        $rfdRsnCd = \ZraVsdcClient::normalizeRefundReasonCode($rfdRsnCd);
        $reasonLabel = \ZraVsdcClient::refundReasonCodes()[$rfdRsnCd] ?? 'Other';

        $sale = Sale::find($originalSaleId);
        if (!$sale) {
            throw new \Exception('Sale not found.');
        }
        $pay = strtolower((string)($sale['payment_status'] ?? ''));
        if ($pay === 'refunded' || (float)$sale['final_total'] < 0) {
            throw new \Exception($pay === 'refunded'
                ? 'This sale has already been refunded.'
                : 'This ticket is already a refund.');
        }
        if ($pay !== 'paid') {
            throw new \Exception('Unpaid sales cannot be refunded.');
        }

        $available = $this->remainingByItem($originalSaleId);
        $lines = [];
        $amount = 0.0;
        foreach ($qtyByItemId as $itemId => $qty) {
            $itemId = (int)$itemId;
            $qty = round((float)$qty, 4);
            if ($itemId <= 0 || $qty <= 0) {
                continue;
            }
            if (!isset($available[$itemId])) {
                throw new \Exception('One of the selected lines cannot be refunded.');
            }
            $row = $available[$itemId];
            if ($qty > (float)$row['remaining'] + 0.0001) {
                throw new \Exception('Refund quantity exceeds sold quantity for ' . $row['name'] . '.');
            }
            $price = abs((float)$row['price']);
            $lines[] = [
                'item' => $row,
                'qty' => $qty,
                'price' => $price,
            ];
            $amount += $price * $qty;
        }
        if ($lines === []) {
            throw new \Exception('Select a quantity to refund.');
        }

        $zraMsg = '';
        if (\App\Helpers\Movement::requiresZra()) {
            if (empty($sale['zra_rcpt_sign'])) {
                $zraMsg .= fiscalizePaidSale($this->pdo, $originalSaleId);
                $sale = Sale::find($originalSaleId) ?: $sale;
            }
            if (empty($sale['zra_rcpt_sign'])) {
                throw new \Exception(
                    'The original sale is not on ZRA yet. Fiscalize it from the ZRA page, then refund.'
                    . ($zraMsg !== '' ? ' ' . trim($zraMsg) : '')
                );
            }
        } elseif (empty($sale['zra_rcpt_sign'])) {
            $zraMsg .= fiscalizePaidSale($this->pdo, $originalSaleId);
            $sale = Sale::find($originalSaleId) ?: $sale;
        }

        $creditSaleId = 0;
        $this->pdo->beginTransaction();
        try {
            $creditSaleId = $this->insertCreditSale($sale, $userId, $amount);
            foreach ($lines as $line) {
                $orig = $line['item'];
                $qty = $line['qty'];
                $this->pdo->prepare(
                    "INSERT INTO sale_items
                        (sale_id, product_id, quantity, price, fulfillment_status, status, refund_sale_item_id, is_refund)
                     VALUES (?, ?, ?, ?, 'collected', 'ready', ?, 1)"
                )->execute([
                    $creditSaleId,
                    (int)$orig['product_id'],
                    $qty,
                    -abs($line['price']),
                    (int)$orig['id'],
                ]);
                deductStock(
                    $this->pdo,
                    (int)$orig['product_id'],
                    -$qty,
                    (int)$sale['location_id'],
                    $userId,
                    'refund'
                );
                $left = (float)$orig['remaining'] - $qty;
                if ($left <= 0.0001) {
                    $this->pdo->prepare(
                        "UPDATE sale_items SET status = 'refunded', fulfillment_status = 'refunded' WHERE id = ?"
                    )->execute([(int)$orig['id']]);
                }
            }

            try {
                $this->pdo->prepare(
                    "INSERT INTO refunds (sale_id, manager_id, amount_refunded, reason, created_at)
                     VALUES (?, ?, ?, ?, NOW())"
                )->execute([$originalSaleId, $userId, $amount, $reason . ' · ' . $reasonLabel]);
            } catch (\Throwable $e) {
                // refunds table is optional on older databases
            }

            $this->copyOriginalCustomerTpin((int)$sale['id'], $creditSaleId);
            try {
                $this->pdo->prepare('UPDATE sales SET zra_rfd_rsn_cd = ? WHERE id = ?')
                    ->execute([$rfdRsnCd, $creditSaleId]);
            } catch (\Throwable $e) {
                // column added by ZraVsdcClient
            }
            if (!empty($sale['zra_rcpt_no'])) {
                try {
                    $this->pdo->prepare('UPDATE sales SET zra_org_invc_no = ? WHERE id = ?')
                        ->execute([(int)$sale['zra_rcpt_no'], $creditSaleId]);
                } catch (\Throwable $e) {
                    // column added by ZraVsdcClient
                }
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        $zraMsg .= fiscalizePaidSale($this->pdo, $creditSaleId, ['rfdRsnCd' => $rfdRsnCd]);

        return [
            'credit_sale_id' => $creditSaleId,
            'amount' => $amount,
            'zra_msg' => trim($zraMsg),
        ];
    }

    public function refundAll(int $originalSaleId, int $userId, string $reason = 'Full refund', string $rfdRsnCd = '07'): array
    {
        $qty = [];
        foreach ($this->remainingByItem($originalSaleId) as $itemId => $row) {
            $qty[$itemId] = (float)$row['remaining'];
        }
        return $this->process($originalSaleId, $qty, $userId, $reason, $rfdRsnCd);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function remainingByItem(int $saleId): array
    {
        new \ZraVsdcClient($this->pdo);
        $stmt = $this->pdo->prepare(
            "SELECT si.*, p.name,
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
             JOIN products p ON p.id = si.product_id
             WHERE si.sale_id = ?
               AND si.status <> 'voided'
               AND COALESCE(si.is_refund, 0) = 0
               AND si.price >= 0"
        );
        $stmt->execute([$saleId]);
        $out = [];
        foreach ($stmt->fetchAll() ?: [] as $row) {
            if ((float)$row['remaining'] <= 0.0001) {
                continue;
            }
            $out[(int)$row['id']] = $row;
        }
        return $out;
    }

    private function insertCreditSale(array $sale, int $userId, float $amount): int
    {
        $neg = -abs($amount);
        $customer = trim((string)($sale['customer_name'] ?? 'Walk-in'));
        if ($customer === '') {
            $customer = 'Walk-in';
        }
        $stmt = $this->pdo->prepare(
            "INSERT INTO sales
                (location_id, user_id, shift_id, subtotal, tip_amount, final_total, payment_method,
                 payment_status, customer_name, amount_tendered, change_due)
             VALUES (?, ?, ?, ?, 0, ?, ?, 'paid', ?, ?, 0)"
        );
        $stmt->execute([
            (int)$sale['location_id'],
            $userId,
            $sale['shift_id'] ?: null,
            $neg,
            $neg,
            $sale['payment_method'] ?: 'Cash',
            'Refund of sale #' . (int)$sale['id'] . ' · ' . $customer,
            abs($amount),
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function copyOriginalCustomerTpin(int $originalSaleId, int $creditSaleId): void
    {
        try {
            $stmt = $this->pdo->prepare('SELECT customer_tpin FROM sales WHERE id = ?');
            $stmt->execute([$originalSaleId]);
            $tpin = $stmt->fetchColumn();
            if ($tpin) {
                $this->pdo->prepare('UPDATE sales SET customer_tpin = ? WHERE id = ?')
                    ->execute([$tpin, $creditSaleId]);
            }
        } catch (\Throwable $e) {
            // customer_tpin may not exist yet
        }
    }
}
