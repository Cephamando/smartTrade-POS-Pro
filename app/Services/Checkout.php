<?php

namespace App\Services;

use App\Helpers\Movement;
use App\Models\User;
use PDO;

final class Checkout
{
    public function __construct(private PDO $pdo)
    {
    }

    public function complete(array $post, array &$session, int $shiftId, int $locationId, int $userId): void
    {
        $this->assertCartProducts($session);
        new \ZraVsdcClient($this->pdo);
        ensureSaleSplitPaymentsColumn($this->pdo);
        $this->pdo->beginTransaction();
        $fiscalizeAfterCommit = 0;
        $clearCartAfterCommit = false;
        $settleTabId = (int)($post['settle_tab_id'] ?? 0);
        $tip = (float)($post['tip_amount'] ?? 0);
        $pm = $post['payment_method'] ?? 'Cash';
        $isSplit = $post['is_split'] ?? '0';
        $status = ($pm === 'Pending') ? 'pending' : 'paid';
        $splitLegs = [];
        if ($isSplit == '1') {
            $pm = 'Split';
            $splitLegs = collectPostedSplitPayments($post);
        }
        [$sm1, $sa1, $sm2, $sa2] = splitLegacyPair($splitLegs);
        $splitJson = $splitLegs ? json_encode($splitLegs) : null;
        $amountTendered = isset($post['amount_tendered']) ? (float)$post['amount_tendered'] : 0;
        [$surcharge, $surchargeReason] = $this->resolveSurcharge($post);
        $gatewayTxRef = trim((string)($post['gateway_tx_ref'] ?? ''));
        $gateway = null;
        $flwDue = 0.0;

        $checkTotal = 0;
        if ($settleTabId > 0) {
            $stmt = $this->pdo->prepare("SELECT SUM(price * quantity) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')");
            $stmt->execute([$settleTabId]);
            $checkTotal = (float)$stmt->fetchColumn();
        } else {
            foreach ($session['cart'] ?? [] as $item) {
                $checkTotal += $item['price'] * $item['qty'];
            }
        }
        if (isset($post['apply_discount']) && $post['apply_discount'] == '1') {
            $checkTotal *= 0.9;
        }
        if ($checkTotal < 0) {
            $surcharge = 0.0;
            $surchargeReason = null;
        }
        $checkTotal += $tip + $surcharge;

        if ($pm === 'Flutterwave') {
            $flwDue = max(0, $checkTotal);
        } elseif ($isSplit == '1') {
            foreach ($splitLegs as $leg) {
                if (strcasecmp((string)$leg['method'], 'Flutterwave') === 0) {
                    $flwDue += (float)$leg['amount'];
                }
            }
        }
        if ($flwDue > 0.004) {
            if ($gatewayTxRef === '') {
                throw new \Exception('Take the Flutterwave payment first, then complete the sale.');
            }
            $gateway = new Flutterwave($this->pdo);
        }

        if ($gateway !== null) {
            $tx = $gateway->verify($gatewayTxRef);
            if (($tx['status'] ?? '') !== 'successful') {
                throw new \Exception('Flutterwave has not confirmed payment ' . $gatewayTxRef . ' yet (' . ($tx['status'] ?? 'unknown') . '). Wait for the customer to approve, then try again.');
            }
            if ((float)$tx['amount'] + 0.005 < $flwDue) {
                throw new \Exception('The Flutterwave payment (ZMW ' . number_format((float)$tx['amount'], 2) . ') is less than the Flutterwave portion (ZMW ' . number_format($flwDue, 2) . ').');
            }
            if ($isSplit != '1') {
                $amountTendered = $checkTotal;
            }
        }

        $rfdRsnCd = $checkTotal < 0
            ? \ZraVsdcClient::normalizeRefundReasonCode((string)($post['rfd_rsn_cd'] ?? '07'))
            : '';

        if ($checkTotal < 0) {
            $mgr = User::verifyManager((string)($post['mgr_username'] ?? ''), (string)($post['mgr_password'] ?? ''));
            if (!$mgr) {
                throw new \Exception('An approval code is required for a cash refund.');
            }
            if ($settleTabId <= 0) {
                $this->prepareOriginalInvoicesForRefund($session);
            }
        }

        if ($settleTabId <= 0) {
            Movement::assertCartStock($session['cart'] ?? [], $locationId);
        }

        if ($settleTabId > 0) {
            $this->settleTab($post, $session, $shiftId, $settleTabId, $tip, $pm, $amountTendered, $sm1, $sa1, $sm2, $sa2, $splitJson, $fiscalizeAfterCommit, $surcharge, $surchargeReason);
        } else {
            $this->newSale($post, $session, $shiftId, $locationId, $userId, $tip, $pm, $amountTendered, $sm1, $sa1, $sm2, $sa2, $splitJson, $fiscalizeAfterCommit, $clearCartAfterCommit, $surcharge, $surchargeReason);
        }

        $savedSaleId = (int)($session['completed_sale_id'] ?? 0);
        if ($savedSaleId > 0) {
            $this->storeTenderExtras($post, $savedSaleId, $gateway, $gatewayTxRef, $flwDue > 0.004 ? $flwDue : $checkTotal);
        }

        if ($rfdRsnCd !== '' && $fiscalizeAfterCommit > 0) {
            try {
                $this->pdo->prepare('UPDATE sales SET zra_rfd_rsn_cd = ? WHERE id = ?')
                    ->execute([$rfdRsnCd, $fiscalizeAfterCommit]);
            } catch (\Throwable $e) {
                // column added by ZraVsdcClient
            }
        }

        $this->pdo->commit();
        $loggedSaleId = $fiscalizeAfterCommit > 0 ? $fiscalizeAfterCommit : $savedSaleId;
        if ($loggedSaleId > 0) {
            Activity::log($this->pdo, $checkTotal < 0 ? 'refund' : 'sale', 'ok', [
                'user_id' => $userId,
                'location_id' => $locationId,
                'subject' => ($checkTotal < 0 ? 'Refund #' : 'Sale #') . $loggedSaleId,
                'detail' => 'K' . number_format((float)$checkTotal, 2) . ' · ' . (string)$pm,
            ]);
        }
        if ($clearCartAfterCommit) {
            $session['cart'] = [];
            $session['current_customer'] = '';
        }
        if ($fiscalizeAfterCommit > 0) {
            $opts = $rfdRsnCd !== '' ? ['rfdRsnCd' => $rfdRsnCd] : [];
            $session['swal_msg'] .= fiscalizePaidSale($this->pdo, $fiscalizeAfterCommit, $opts);
        }
    }

    private function settleTab(
        array $post,
        array &$session,
        int $shiftId,
        int $settleTabId,
        float $tip,
        string $pm,
        float $amountTendered,
        $sm1,
        $sa1,
        $sm2,
        $sa2,
        $splitJson,
        int &$fiscalizeAfterCommit,
        float $surcharge = 0.0,
        ?string $surchargeReason = null
    ): void {
        $stmtOrig = $this->pdo->prepare("SELECT split_group_id FROM sales WHERE id = ?");
        $stmtOrig->execute([$settleTabId]);
        $extOrderId = $stmtOrig->fetchColumn();
        $wasOnlineOrder = !empty($extOrderId);

        $stmt = $this->pdo->prepare("SELECT SUM(price * quantity) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')");
        $stmt->execute([$settleTabId]);
        $total = (float)$stmt->fetchColumn();
        if (isset($post['apply_discount']) && $post['apply_discount'] == '1') {
            $total *= 0.9;
        }
        $finalTotal = $total + $tip + $surcharge;
        $stmtPT = $this->pdo->prepare("SELECT amount_tendered FROM sales WHERE id = ?");
        $stmtPT->execute([$settleTabId]);
        $prevTendered = (float)$stmtPT->fetchColumn();
        $cumulativeTendered = $prevTendered + $amountTendered;

        $status = ($cumulativeTendered >= $finalTotal) ? 'paid' : 'pending';
        $saleStatus = ($status === 'paid') ? 'completed' : 'pending';
        if ($wasOnlineOrder) {
            // Payment does not mean the food has been handed over.
            $saleStatus = 'pending';
            $pm = 'Online';
        }
        $changeDue = ($cumulativeTendered > $finalTotal) ? ($cumulativeTendered - $finalTotal) : 0;

        $this->pdo->prepare("UPDATE sales SET shift_id = ?, status = ?, subtotal = ?, tip_amount = ?, surcharge_amount = ?, surcharge_reason = ?, final_total = ?, payment_method = ?, payment_status = ?, amount_tendered = ?, change_due = ?, split_method_1 = ?, split_amount_1 = ?, split_method_2 = ?, split_amount_2 = ?, split_payments = ? WHERE id = ?")
            ->execute([$shiftId, $saleStatus, $total, $tip, $surcharge, $surchargeReason, $finalTotal, $pm, $status, $cumulativeTendered, $changeDue, $sm1, $sa1, $sm2, $sa2, $splitJson, $settleTabId]);

        if ($saleStatus === 'completed') {
            $this->pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected' WHERE sale_id = ?")->execute([$settleTabId]);
        }

        $session['completed_sale_id'] = $settleTabId;
        if ($status === 'paid' && !$wasOnlineOrder) {
            $session['last_sale_id'] = $settleTabId;
            $fiscalizeAfterCommit = $settleTabId;
        }
        $session['swal_type'] = 'success';
        $session['swal_msg'] = ($wasOnlineOrder && $status === 'paid')
            ? 'Order #' . $settleTabId . ' is paid. Hand it over when the kitchen is ready.'
            : 'Tab settled.';
    }

    private function newSale(
        array $post,
        array &$session,
        int $shiftId,
        int $locationId,
        int $userId,
        float $tip,
        string $pm,
        float $amountTendered,
        $sm1,
        $sa1,
        $sm2,
        $sa2,
        $splitJson,
        int &$fiscalizeAfterCommit,
        bool &$clearCartAfterCommit,
        float $surcharge = 0.0,
        ?string $surchargeReason = null
    ): void {
        if (empty($session['cart']) || !is_array($session['cart'])) {
            throw new \Exception("The cart is empty.");
        }
        $total = 0;
        foreach ($session['cart'] as $item) {
            $total += $item['price'] * $item['qty'];
        }
        if (isset($post['apply_discount']) && $post['apply_discount'] == '1') {
            $total *= 0.9;
        }
        $finalTotal = $total + $tip + $surcharge;
        $customerName = $post['customer_name'] ?? 'Walk-in';

        $status = ($amountTendered >= $finalTotal && $pm !== 'Pending') ? 'paid' : 'pending';
        $changeDue = ($amountTendered > $finalTotal) ? ($amountTendered - $finalTotal) : 0;
        $stmt = $this->pdo->prepare("INSERT INTO sales (location_id, user_id, shift_id, subtotal, tip_amount, surcharge_amount, surcharge_reason, final_total, payment_method, payment_status, customer_name, amount_tendered, change_due, split_method_1, split_amount_1, split_method_2, split_amount_2, split_payments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$locationId, $userId, $shiftId, $total, $tip, $surcharge, $surchargeReason, $finalTotal, $pm, $status, $customerName, $amountTendered, $changeDue, $sm1, $sa1, $sm2, $sa2, $splitJson]);
        $saleId = $this->pdo->lastInsertId();

        foreach ($session['cart'] as $item) {
            $fulfillment = $item['fulfillment'] ?? 'collected';
            $itemStatus = ($fulfillment === 'uncollected') ? 'pending' : 'ready';
            $this->pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, fulfillment_status, status, refund_sale_item_id, is_refund) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")->execute([
                $saleId,
                $item['product_id'],
                $item['qty'],
                $item['price'],
                $fulfillment,
                $itemStatus,
                !empty($item['refund_sale_item_id']) ? (int)$item['refund_sale_item_id'] : null,
                !empty($item['is_refund']) ? 1 : 0,
            ]);

            $isRefund = $item['is_refund'] ?? false;
            $dbQty = $isRefund ? -$item['qty'] : $item['qty'];
            $stockAction = $isRefund ? 'refund' : 'sale';
            deductStock($this->pdo, $item['product_id'], $dbQty, $locationId, $userId, $stockAction);

            if ($isRefund && !empty($item['refund_sale_item_id'])) {
                $origItemId = (int)$item['refund_sale_item_id'];
                $leftStmt = $this->pdo->prepare(
                    "SELECT GREATEST(
                        0,
                        si.quantity - COALESCE((
                            SELECT SUM(r.quantity)
                            FROM sale_items r
                            WHERE r.refund_sale_item_id = si.id
                              AND r.is_refund = 1
                              AND r.status <> 'voided'
                        ), 0)
                    )
                     FROM sale_items si WHERE si.id = ?"
                );
                $leftStmt->execute([$origItemId]);
                $left = (float)$leftStmt->fetchColumn();
                if ($left <= 0.0001) {
                    $this->pdo->prepare("UPDATE sale_items SET status = 'refunded', fulfillment_status = 'refunded' WHERE id = ?")->execute([$origItemId]);
                }
                $stmt = $this->pdo->prepare("SELECT sale_id FROM sale_items WHERE id = ?");
                $stmt->execute([$origItemId]);
                $origSaleId = $stmt->fetchColumn();
                if ($origSaleId) {
                    $stmt2 = $this->pdo->prepare("SELECT payment_status FROM sales WHERE id = ?");
                    $stmt2->execute([$origSaleId]);
                    if ($stmt2->fetchColumn() === 'pending') {
                        $this->pdo->prepare("UPDATE sales SET subtotal = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')), final_total = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')) WHERE id = ?")->execute([$origSaleId, $origSaleId, $origSaleId]);
                    }
                    try {
                        $rcpt = $this->pdo->prepare('SELECT zra_rcpt_no FROM sales WHERE id = ?');
                        $rcpt->execute([$origSaleId]);
                        $orgInvc = (int)$rcpt->fetchColumn();
                        if ($orgInvc > 0) {
                            $this->pdo->prepare('UPDATE sales SET zra_org_invc_no = ? WHERE id = ?')
                                ->execute([$orgInvc, $saleId]);
                        }
                    } catch (\Throwable $e) {
                        // column added by ZraVsdcClient
                    }
                }
            }
        }
        $clearCartAfterCommit = true;
        $session['completed_sale_id'] = (int)$saleId;
        if ($status === 'paid') {
            $session['last_sale_id'] = $saleId;
            $fiscalizeAfterCommit = (int)$saleId;
        }
        $session['swal_type'] = 'success';
        $session['swal_msg'] = "Transaction complete.";
    }

    private function assertCartProducts(array $session): void
    {
        foreach ($session['cart'] ?? [] as $item) {
            $productId = (int)($item['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $stmt = $this->pdo->prepare('SELECT 1 FROM products WHERE id = ?');
            $stmt->execute([$productId]);
            if (!$stmt->fetchColumn()) {
                throw new \Exception('That item is not available on this register. The sale was not recorded.');
            }
        }
    }

    /** @return array{0: float, 1: ?string} */
    private function resolveSurcharge(array $post): array
    {
        $amount = round(max(0, (float)($post['surcharge_amount'] ?? 0)), 2);
        if ($amount <= 0) {
            return [0.0, null];
        }
        $reason = '';
        $reasonId = (int)($post['surcharge_reason_id'] ?? 0);
        if ($reasonId > 0) {
            $row = \App\Models\SurchargeReason::find($reasonId);
            $reason = $row ? (string)$row['name'] : '';
        }
        if ($reason === '') {
            $reason = trim((string)($post['surcharge_reason_other'] ?? $post['surcharge_reason'] ?? ''));
        }
        if ($reason === '') {
            throw new \Exception('Choose a reason for the surcharge.');
        }
        return [$amount, mb_substr($reason, 0, 120)];
    }

    private function storeTenderExtras(array $post, int $saleId, ?Flutterwave $gateway, string $gatewayTxRef, float $due): void
    {
        $cur = strtoupper(trim((string)($post['tender_currency'] ?? '')));
        $fxAmount = (float)($post['tender_fx_amount'] ?? 0);
        $fxRate = (float)($post['tender_fx_rate'] ?? 0);
        $gatewayRef = null;
        if ($gateway !== null && $gatewayTxRef !== '') {
            $tx = $gateway->claimForSale($gatewayTxRef, $due, $saleId);
            $gatewayRef = $gatewayTxRef . (!empty($tx['flw_ref']) ? ' / ' . $tx['flw_ref'] : '');
        }
        $hasFx = preg_match('/^[A-Z]{3}$/', $cur) && $cur !== 'ZMW' && $fxAmount > 0 && $fxRate > 0;
        if (!$hasFx && $gatewayRef === null) {
            return;
        }
        try {
            $this->pdo->prepare(
                'UPDATE sales SET tender_currency = ?, tender_fx_amount = ?, tender_fx_rate = ?, gateway_ref = COALESCE(?, gateway_ref) WHERE id = ?'
            )->execute([
                $hasFx ? $cur : null,
                $hasFx ? round($fxAmount, 2) : null,
                $hasFx ? round($fxRate, 6) : null,
                $gatewayRef,
                $saleId,
            ]);
        } catch (\Throwable $e) {
            // columns are added by Schema::ensure on the next request
        }
    }

    private function prepareOriginalInvoicesForRefund(array $session): void
    {
        $origSaleIds = [];
        foreach ($session['cart'] ?? [] as $item) {
            if (empty($item['is_refund'])) {
                continue;
            }
            $lineId = (int)($item['refund_sale_item_id'] ?? 0);
            if ($lineId <= 0) {
                throw new \Exception('Choose the original sale to refund before issuing the credit note.');
            }
            $line = \App\Models\Sale::refundableLine($lineId);
            if (!$line) {
                throw new \Exception('That ticket line is no longer refundable.');
            }
            $saleId = (int)($line['sale_id'] ?? 0);
            if ($saleId > 0) {
                $origSaleIds[$saleId] = $saleId;
            }
        }
        if ($origSaleIds === []) {
            throw new \Exception('Choose the original sale to refund before issuing the credit note.');
        }
        $zraMsg = '';
        if (!empty($_SESSION['training_mode'])) {
            return;
        }
        $edge = new EdgeSync($this->pdo);
        $isRemoteEdge = $edge->isEdge() && strtolower(trim((string)(getenv('ZRA_VSDC_MODE') ?: ($_ENV['ZRA_VSDC_MODE'] ?? '')))) !== 'local';
        foreach ($origSaleIds as $saleId) {
            $sale = \App\Models\Sale::find($saleId);
            if (!$sale) {
                throw new \Exception('Original sale #' . $saleId . ' was not found.');
            }
            if (empty($sale['zra_rcpt_sign'])) {
                $zraMsg .= fiscalizePaidSale($this->pdo, $saleId);
                $sale = \App\Models\Sale::find($saleId) ?: $sale;
            }
            if (!$isRemoteEdge && Movement::requiresZra() && empty($sale['zra_rcpt_sign'])) {
                throw new \Exception(
                    'Sale #' . $saleId . ' is not on ZRA yet. Fiscalize it from the ZRA page, then refund.'
                    . ($zraMsg !== '' ? ' ' . trim($zraMsg) : '')
                );
            }
        }
    }
}
