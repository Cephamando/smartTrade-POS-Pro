<?php

namespace App\Services;

use App\Models\User;
use PDO;

final class Checkout
{
    public function __construct(private PDO $pdo)
    {
    }

    public function complete(array $post, array &$session, int $shiftId, int $locationId, int $userId): void
    {
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

        if ($settleTabId > 0) {
            $stmtCheckOnline = $this->pdo->prepare("SELECT split_group_id FROM sales WHERE id = ?");
            $stmtCheckOnline->execute([$settleTabId]);
            $extIdCheck = $stmtCheckOnline->fetchColumn();
            if (!empty($extIdCheck)) {
                $stmtCheckKds = $this->pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND status IN ('pending', 'cooking') AND status NOT IN ('voided', 'refunded')");
                $stmtCheckKds->execute([$settleTabId]);
                if ($stmtCheckKds->fetchColumn() > 0) {
                    throw new \Exception("Cannot process checkout: The Kitchen is still preparing items for this Web Order.");
                }
            }
        }

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
        $checkTotal += $tip;

        if ($checkTotal < 0) {
            $mgr = User::verifyManager((string)($post['mgr_username'] ?? ''), (string)($post['mgr_password'] ?? ''));
            if (!$mgr) {
                throw new \Exception("Manager Authorization required for Cash Refunds.");
            }
        }

        if ($settleTabId > 0) {
            $this->settleTab($post, $session, $shiftId, $settleTabId, $tip, $pm, $amountTendered, $sm1, $sa1, $sm2, $sa2, $splitJson, $fiscalizeAfterCommit);
        } else {
            $this->newSale($post, $session, $shiftId, $locationId, $userId, $tip, $pm, $amountTendered, $sm1, $sa1, $sm2, $sa2, $splitJson, $fiscalizeAfterCommit, $clearCartAfterCommit);
        }

        $this->pdo->commit();
        if ($clearCartAfterCommit) {
            $session['cart'] = [];
            $session['current_customer'] = '';
        }
        if ($fiscalizeAfterCommit > 0) {
            $session['swal_msg'] .= fiscalizePaidSale($this->pdo, $fiscalizeAfterCommit);
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
        int &$fiscalizeAfterCommit
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
        $finalTotal = $total + $tip;
        $stmtPT = $this->pdo->prepare("SELECT amount_tendered FROM sales WHERE id = ?");
        $stmtPT->execute([$settleTabId]);
        $prevTendered = (float)$stmtPT->fetchColumn();
        $cumulativeTendered = $prevTendered + $amountTendered;

        $status = ($cumulativeTendered >= $finalTotal) ? 'paid' : 'pending';
        $saleStatus = ($status === 'paid') ? 'completed' : 'pending';
        $changeDue = ($cumulativeTendered > $finalTotal) ? ($cumulativeTendered - $finalTotal) : 0;

        $this->pdo->prepare("UPDATE sales SET shift_id = ?, status = ?, subtotal = ?, tip_amount = ?, final_total = ?, payment_method = ?, payment_status = ?, amount_tendered = ?, change_due = ?, split_method_1 = ?, split_amount_1 = ?, split_method_2 = ?, split_amount_2 = ?, split_payments = ? WHERE id = ?")
            ->execute([$shiftId, $saleStatus, $total, $tip, $finalTotal, $pm, $status, $cumulativeTendered, $changeDue, $sm1, $sa1, $sm2, $sa2, $splitJson, $settleTabId]);

        if ($saleStatus === 'completed') {
            $this->pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected' WHERE sale_id = ?")->execute([$settleTabId]);
            if ($wasOnlineOrder && $extOrderId) {
                $webhookUrl = "https://webhook.site/b97db7ed-2317-469d-952c-0e9badfd7a03";
                $payload = json_encode([
                    "order_id" => $extOrderId,
                    "status" => "completed",
                    "store_id" => "MAIN_COUNTER_001",
                    "timestamp" => date('c'),
                ]);
                $ch = curl_init($webhookUrl);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($payload),
                ]);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 3);
                curl_exec($ch);
                curl_close($ch);
            }
        }

        if ($status === 'paid') {
            $session['last_sale_id'] = $settleTabId;
            $fiscalizeAfterCommit = $settleTabId;
        }
        $session['swal_type'] = 'success';
        $session['swal_msg'] = 'Tab settled.';
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
        bool &$clearCartAfterCommit
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
        $finalTotal = $total + $tip;
        $customerName = $post['customer_name'] ?? 'Walk-in';

        $status = ($amountTendered >= $finalTotal && $pm !== 'Pending') ? 'paid' : 'pending';
        $changeDue = ($amountTendered > $finalTotal) ? ($amountTendered - $finalTotal) : 0;
        $stmt = $this->pdo->prepare("INSERT INTO sales (location_id, user_id, shift_id, subtotal, tip_amount, final_total, payment_method, payment_status, customer_name, amount_tendered, change_due, split_method_1, split_amount_1, split_method_2, split_amount_2, split_payments) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$locationId, $userId, $shiftId, $total, $tip, $finalTotal, $pm, $status, $customerName, $amountTendered, $changeDue, $sm1, $sa1, $sm2, $sa2, $splitJson]);
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
                $stmt = $this->pdo->prepare("SELECT sale_id FROM sale_items WHERE id = ?");
                $stmt->execute([$item['refund_sale_item_id']]);
                $origSaleId = $stmt->fetchColumn();
                if ($origSaleId) {
                    $this->pdo->prepare("UPDATE sale_items SET status = 'refunded', fulfillment_status = 'refunded' WHERE id = ?")->execute([$item['refund_sale_item_id']]);
                    $stmt2 = $this->pdo->prepare("SELECT payment_status FROM sales WHERE id = ?");
                    $stmt2->execute([$origSaleId]);
                    if ($stmt2->fetchColumn() === 'pending') {
                        $this->pdo->prepare("UPDATE sales SET subtotal = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')), final_total = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')) WHERE id = ?")->execute([$origSaleId, $origSaleId, $origSaleId]);
                    }
                }
            }
        }
        $clearCartAfterCommit = true;
        if ($status === 'paid') {
            $session['last_sale_id'] = $saleId;
            $fiscalizeAfterCommit = (int)$saleId;
        }
        $session['swal_type'] = 'success';
        $session['swal_msg'] = "Transaction complete.";
    }
}
