<?php

namespace App\Models;

use PDO;

/**
 * Commission on Visa/Mastercard taken on the shop's Stanbic machine.
 * That money settles to the shop's bank, so the 2.5% is written here and
 * collected from the next Flutterwave mobile-money charge.
 *
 * The charge override uses transaction_charge_type "flat". Flutterwave's own
 * example credits that flat amount to the main account in full, and takes
 * its fee from what is left for the subaccount. "flat_subaccount" does the
 * opposite: the fee comes out of the main account, which would lose ngwee.
 */
final class CommissionLedger extends Model
{
    public const RATE = 0.025;
    public const METHOD = 'Stanbic card';

    /** Leave at least this much, or 3% of the charge, for the shop so Flutterwave's fee can come off that side. */
    private const MIN_SUBACCOUNT = 1.0;
    private const FEE_ROOM = 0.03;

    public static function ngwee(float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }
        return ceil($amount * 100 - 1.0e-9) / 100;
    }

    /**
     * @return array{override:bool,commission:float,recovery:float,main:float,subaccount:float}
     */
    public static function plan(float $charge, float $balance): array
    {
        $charge = round($charge, 2);
        $balance = round($balance, 2);
        $commission = self::ngwee($charge * self::RATE);
        $plain = [
            'override' => false,
            'commission' => $commission,
            'recovery' => 0.0,
            'main' => $commission,
            'subaccount' => round(max(0, $charge - $commission), 2),
        ];
        $room = max(self::MIN_SUBACCOUNT, round($charge * self::FEE_ROOM, 2));
        if ($balance < 0.01 || $charge <= $room) {
            return $plain;
        }
        $maxMain = round($charge - $room, 2);
        if ($maxMain + 0.001 < $commission) {
            return $plain;
        }
        $main = min(round($commission + $balance, 2), $maxMain);
        $recovery = round($main - $commission, 2);
        if ($recovery < 0.01) {
            return $plain;
        }
        return [
            'override' => true,
            'commission' => $commission,
            'recovery' => $recovery,
            'main' => $main,
            'subaccount' => round($charge - $main, 2),
        ];
    }

    public static function balance(PDO $pdo): float
    {
        try {
            $row = $pdo->query(
                "SELECT
                    COALESCE(SUM(CASE WHEN kind = 'owe' THEN amount WHEN kind IN ('recovered', 'refund') THEN -amount ELSE 0 END), 0)
                    - COALESCE(SUM(CASE WHEN kind = 'reserve' THEN amount ELSE 0 END), 0) AS bal
                 FROM commission_ledger"
            )->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return 0.0;
        }
        return round((float)($row['bal'] ?? 0), 2);
    }

    /** @return array{due:float,held:float} */
    public static function summary(PDO $pdo): array
    {
        try {
            $row = $pdo->query(
                "SELECT
                    COALESCE(SUM(CASE WHEN kind = 'owe' THEN amount WHEN kind IN ('recovered', 'refund') THEN -amount ELSE 0 END), 0) AS gross,
                    COALESCE(SUM(CASE WHEN kind = 'reserve' THEN amount ELSE 0 END), 0) AS held
                 FROM commission_ledger"
            )->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return ['due' => 0.0, 'held' => 0.0];
        }
        $held = round((float)($row['held'] ?? 0), 2);
        $gross = round((float)($row['gross'] ?? 0), 2);
        return ['due' => round($gross - $held, 2), 'held' => $held];
    }

    /** @param array<string, mixed> $post */
    public static function recordSale(PDO $pdo, int $saleId, array $post): void
    {
        $stmt = $pdo->prepare('SELECT final_total, payment_status, payment_method, split_payments FROM sales WHERE id = ?');
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sale) {
            return;
        }
        $portion = self::portion($sale);
        if (abs($portion) < 0.01) {
            return;
        }
        $rrn = strtoupper(preg_replace('/\s+/', '', (string)($post['stanbic_rrn'] ?? '')) ?? '');
        if (!preg_match('/^[A-Z0-9]{6,20}$/', $rrn)) {
            throw new \InvalidArgumentException('Enter the Stanbic RRN from the card machine.');
        }
        $pdo->prepare('UPDATE sales SET stanbic_rrn = ?, stanbic_auth = NULL WHERE id = ?')->execute([$rrn, $saleId]);
        if (($sale['payment_status'] ?? '') !== 'paid') {
            return;
        }
        $already = $pdo->prepare('SELECT 1 FROM commission_ledger WHERE sale_id = ? AND kind IN (\'owe\', \'refund\') LIMIT 1');
        $already->execute([$saleId]);
        if ($already->fetchColumn()) {
            return;
        }
        if ($portion > 0) {
            $owed = self::ngwee($portion * self::RATE);
            if ($owed < 0.01) {
                return;
            }
            self::insert($pdo, 'owe', $owed, $saleId, null, $rrn, null, 'Stanbic card ZMW ' . number_format($portion, 2));
            return;
        }
        $back = self::ngwee(abs($portion) * self::RATE);
        if ($back < 0.01) {
            return;
        }
        self::lock($pdo);
        $open = max(0, self::balance($pdo));
        $back = min($back, $open);
        if ($back < 0.01) {
            return;
        }
        self::insert($pdo, 'refund', $back, $saleId, null, $rrn, null, 'Stanbic refund ZMW ' . number_format(abs($portion), 2));
    }

    /**
     * Hold the recovery for one mobile-money charge so a second charge cannot take it too.
     *
     * @return array{override:bool,commission:float,recovery:float,main:float,subaccount:float}
     */
    public static function reserveForCharge(PDO $pdo, string $txRef, float $charge): array
    {
        $own = !$pdo->inTransaction();
        if ($own) {
            $pdo->beginTransaction();
        }
        try {
            self::lock($pdo);
            $plan = self::plan($charge, max(0, self::balance($pdo)));
            if ($plan['recovery'] >= 0.01) {
                self::insert($pdo, 'reserve', $plan['recovery'], null, $txRef, null, null, 'Held for ' . $txRef);
            }
            if ($own) {
                $pdo->commit();
            }
            return $plan;
        } catch (\Throwable $e) {
            if ($own && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function release(PDO $pdo, string $txRef): void
    {
        if ($txRef === '') {
            return;
        }
        try {
            $pdo->prepare("DELETE FROM commission_ledger WHERE tx_ref = ? AND kind = 'reserve'")->execute([$txRef]);
        } catch (\Throwable $e) {
        }
    }

    public static function settleCharge(PDO $pdo, string $txRef, string $status): void
    {
        if ($txRef === '' || str_starts_with($txRef, 'SUP-')) {
            return;
        }
        try {
            if ($status === 'successful') {
                $pdo->prepare("UPDATE commission_ledger SET kind = 'recovered', note = CONCAT('Collected on ', tx_ref) WHERE tx_ref = ? AND kind = 'reserve'")
                    ->execute([$txRef]);
                return;
            }
            if ($status === 'failed') {
                self::release($pdo, $txRef);
            }
        } catch (\Throwable $e) {
        }
    }

    /** @param array<string, mixed> $sale */
    private static function portion(array $sale): float
    {
        $method = (string)($sale['payment_method'] ?? '');
        if (strcasecmp($method, self::METHOD) === 0) {
            return round((float)$sale['final_total'], 2);
        }
        if ($method !== 'Split') {
            return 0.0;
        }
        $raw = $sale['split_payments'] ?? '';
        $legs = is_string($raw) && $raw !== '' ? json_decode($raw, true) : [];
        if (!is_array($legs)) {
            return 0.0;
        }
        $sum = 0.0;
        foreach ($legs as $leg) {
            if (!is_array($leg) || strcasecmp((string)($leg['method'] ?? ''), self::METHOD) !== 0) {
                continue;
            }
            $sum += (float)($leg['amount'] ?? 0);
        }
        $total = (float)$sale['final_total'];
        if ($total < 0 && $sum > 0) {
            $sum = -$sum;
        }
        return round($sum, 2);
    }

    private static function insert(PDO $pdo, string $kind, float $amount, ?int $saleId, ?string $txRef, ?string $rrn, ?string $auth, string $note): void
    {
        $pdo->prepare(
            'INSERT INTO commission_ledger (kind, amount, sale_id, tx_ref, reference, auth_code, note) VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $kind,
            round($amount, 2),
            $saleId,
            $txRef,
            $rrn,
            $auth,
            mb_substr($note, 0, 255),
        ]);
    }

    private static function lock(PDO $pdo): void
    {
        $pdo->prepare('SELECT id FROM commission_state WHERE id = 1 FOR UPDATE')->execute();
    }
}
