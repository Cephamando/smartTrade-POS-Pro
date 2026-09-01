<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use PDO;

final class Pos
{
    public function __construct(private PDO $pdo)
    {
    }

    public function handlePost(array $post, array &$session, int $userId, int $locationId, string $role, string $tier): ?array
    {
        $isManager = in_array($role, ['admin', 'manager', 'dev'], true);
        $activeShift = Shift::openForUser($userId, $locationId);
        $activeShiftId = $activeShift['id'] ?? null;

        if (isset($post['mark_collected'])) {
            return $this->markCollected($post, $tier);
        }
        if (isset($post['void_item'])) {
            return $this->voidItem($post, $locationId, $userId);
        }
        if (isset($post['set_pos_location'])) {
            $session['pos_location_id'] = (int)$post['set_pos_location'];
            $session['location_name'] = Location::nameById($session['pos_location_id']);
            return ['redirect' => true];
        }
        if (isset($post['request_start_shift'])) {
            Shift::requestStart($userId, $locationId, $post['starting_cash']);
            return ['redirect' => true];
        }
        if (isset($post['approve_shift_start'])) {
            $mgr = User::verifyManager((string)$post['mgr_username'], (string)$post['mgr_password']);
            if ($mgr) {
                Shift::approveStart((int)$post['pending_shift_id']);
                $session['swal_type'] = 'success';
                $session['swal_msg'] = 'Shift started.';
            } else {
                $session['swal_type'] = 'error';
                $session['swal_msg'] = 'Invalid credentials.';
            }
            return ['redirect' => true];
        }
        if (isset($post['transfer_tab_items']) && $activeShiftId) {
            $this->transferTabItems($post, $session, $userId, $locationId, (int)$activeShiftId);
            return ['redirect' => true];
        }

        if (!isset($session['cart'])) {
            $session['cart'] = [];
        }

        if (isset($post['add_item']) && $activeShiftId) {
            $this->addItem($post, $session, $tier);
            return ['redirect' => true];
        }
        if (isset($post['update_qty'])) {
            $key = $post['cart_key'];
            if (isset($session['cart'][$key])) {
                if ($post['action'] === 'inc') {
                    $session['cart'][$key]['qty']++;
                } elseif ($post['action'] === 'dec') {
                    $session['cart'][$key]['qty']--;
                    if ($session['cart'][$key]['qty'] <= 0) {
                        unset($session['cart'][$key]);
                    }
                }
            }
            return ['redirect' => true];
        }
        if (isset($post['remove_item'])) {
            unset($session['cart'][$post['cart_key']]);
            return ['redirect' => true];
        }
        if (isset($post['clear_cart'])) {
            $session['cart'] = [];
            unset($session['current_customer']);
            return ['redirect' => true];
        }
        if (isset($post['toggle_fulfillment'])) {
            $key = $post['cart_key'];
            if (isset($session['cart'][$key]) && !($session['cart'][$key]['is_refund'] ?? false)) {
                if (!in_array(strtolower($session['cart'][$key]['cat_type']), ['food', 'meal'], true)) {
                    $session['cart'][$key]['fulfillment'] = ($session['cart'][$key]['fulfillment'] === 'collected') ? 'uncollected' : 'collected';
                }
            }
            return ['redirect' => true];
        }
        if (isset($post['log_waste']) && $activeShiftId) {
            $mgr = User::verifyManager((string)($post['mgr_user'] ?? ''), (string)($post['mgr_pass'] ?? ''));
            if ($mgr) {
                foreach ($session['cart'] as $item) {
                    deductStock($this->pdo, $item['product_id'], $item['qty'], $locationId, $userId, 'waste');
                }
                $session['cart'] = [];
                $session['swal_type'] = 'success';
                $session['swal_msg'] = 'Waste logged.';
            } else {
                $session['swal_type'] = 'error';
                $session['swal_msg'] = 'Manager Authorization Failed.';
            }
            return ['redirect' => true];
        }
        if (isset($post['finalize_split']) && $activeShiftId) {
            $this->finalizeSplit($post, $session, $userId, $locationId, (int)$activeShiftId);
            return ['redirect' => true];
        }
        if (isset($post['add_to_tab_action']) && $activeShiftId) {
            $this->addToTab($post, $session, $userId, $locationId, (int)$activeShiftId);
            return ['redirect' => true];
        }
        if (isset($post['log_expense']) && $activeShiftId) {
            $this->logExpense($post, $session, $userId, $locationId, (int)$activeShiftId);
            return ['redirect' => true];
        }

        return null;
    }

    public function viewData(array &$session, int $userId, int $locationId, string $role, string $tier): array
    {
        $isManager = in_array($role, ['admin', 'manager', 'dev'], true);
        if (empty($session['location_name']) && $locationId > 0) {
            $session['location_name'] = Location::nameById($locationId);
        }
        if (!isset($session['cart'])) {
            $session['cart'] = [];
        }

        $activeShift = Shift::openForUser($userId, $locationId);
        $activeShiftId = $activeShift['id'] ?? null;
        $expectedShiftCash = $activeShift['starting_cash'] ?? 0;
        if ($activeShiftId) {
            $expectedShiftCash = Shift::expectedCash((int)$activeShiftId, (float)$expectedShiftCash);
        }

        $balance = 0;
        foreach ($session['cart'] as $i) {
            $balance += $i['price'] * $i['qty'];
        }

        $tabQuery = "SELECT DISTINCT s.* FROM sales s JOIN sale_items si ON s.id = si.sale_id WHERE s.location_id = ? AND si.status NOT IN ('voided', 'refunded') AND (s.payment_status = 'pending' OR si.fulfillment_status = 'uncollected')";
        $tabParams = [$locationId];
        if (!$isManager) {
            $tabQuery .= ' AND s.user_id = ?';
            $tabParams[] = $userId;
        }
        $tabQuery .= ' ORDER BY s.id DESC';
        $stmt = $this->pdo->prepare($tabQuery);
        $stmt->execute($tabParams);
        $openTabs = $stmt->fetchAll();

        $tabItems = [];
        foreach ($openTabs as $t) {
            $stmt = $this->pdo->prepare(
                "SELECT si.*, COALESCE(p.name, 'Custom Item') as name, c.type as cat_type
                 FROM sale_items si
                 LEFT JOIN products p ON si.product_id = p.id
                 LEFT JOIN categories c ON p.category_id = c.id
                 WHERE si.sale_id = ? AND si.status NOT IN ('voided', 'refunded')"
            );
            $stmt->execute([$t['id']]);
            $tabItems[$t['id']] = $stmt->fetchAll();
        }

        $stmt = $this->pdo->prepare(
            "SELECT s.table_id, s.id as sale_id, s.final_total, s.user_id, u.username
             FROM sales s LEFT JOIN users u ON s.user_id = u.id
             WHERE s.location_id = ? AND s.payment_status = 'pending' AND s.table_id IS NOT NULL"
        );
        $stmt->execute([$locationId]);
        $occupiedTables = [];
        foreach ($stmt->fetchAll() as $ot) {
            $occupiedTables[$ot['table_id']] = $ot;
        }

        $onlineTabs = [];
        $onlineTabItems = [];
        if ($tier === 'enterprise') {
            $onlineTabs = $this->pdo->query(
                "SELECT id, total_amount, customer_name, split_group_id as external_id, created_at
                 FROM sales WHERE status = 'pending' AND payment_method = 'Online' ORDER BY created_at DESC"
            )->fetchAll();
            foreach ($onlineTabs as $ot) {
                $stmt = $this->pdo->prepare(
                    "SELECT si.*, COALESCE(p.name, 'Custom Item') as name, c.type as cat_type
                     FROM sale_items si
                     LEFT JOIN products p ON si.product_id = p.id
                     LEFT JOIN categories c ON p.category_id = c.id
                     WHERE si.sale_id = ? AND si.status NOT IN ('voided', 'refunded')"
                );
                $stmt->execute([$ot['id']]);
                $onlineTabItems[$ot['id']] = $stmt->fetchAll();
            }
        }

        return [
            'locationId' => $locationId,
            'locationName' => $session['location_name'] ?? 'HQ',
            'userId' => $userId,
            'tier' => $tier,
            'userRole' => $role,
            'isManager' => $isManager,
            'sellableLocations' => Location::sellable(),
            'activeShift' => $activeShift,
            'activeShiftId' => $activeShiftId,
            'expectedShiftCash' => $expectedShiftCash,
            'pendingShift' => $activeShiftId ? null : Shift::pendingForUser($userId, $locationId),
            'sysSettings' => Setting::all(),
            'categories' => Category::all(),
            'products' => Product::posCatalog($locationId),
            'services' => Product::activeServices(),
            'balance' => $balance,
            'openTabs' => $openTabs,
            'tabItems' => $tabItems,
            'occupiedTables' => $occupiedTables,
            'onlineTabs' => $onlineTabs,
            'onlineTabItems' => $onlineTabItems,
            'restaurantTables' => RestaurantTable::groupedForLocation($locationId),
        ];
    }

    private function markCollected(array $post, string $tier): array
    {
        $itemId = (int)$post['item_id'];
        try {
            $stmt = $this->pdo->prepare(
                'SELECT si.sale_id, si.fulfillment_status, c.type as cat_type
                 FROM sale_items si
                 LEFT JOIN products p ON si.product_id = p.id
                 LEFT JOIN categories c ON p.category_id = c.id
                 WHERE si.id = ?'
            );
            $stmt->execute([$itemId]);
            $item = $stmt->fetch();
            if (!$item) {
                return ['json' => ['status' => 'error', 'msg' => 'Item not found.']];
            }
            if (in_array(strtolower($item['cat_type'] ?? ''), ['food', 'meal'], true) && in_array($tier, ['pro+', 'enterprise'], true)) {
                return ['json' => ['status' => 'redirect_pickup', 'msg' => 'Meals must be processed through the Kitchen screen.']];
            }
            if ($item['fulfillment_status'] === 'collected') {
                return ['json' => ['status' => 'error', 'msg' => 'Item already collected.']];
            }
            $this->pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected' WHERE id = ?")->execute([$itemId]);
            $stmtCheck = $this->pdo->prepare(
                "SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND fulfillment_status != 'collected' AND status NOT IN ('voided', 'refunded')"
            );
            $stmtCheck->execute([$item['sale_id']]);
            $done = $stmtCheck->fetchColumn() == 0;
            return ['json' => ['status' => 'success', 'sale_id' => $item['sale_id'], 'tab_completed' => $done, 'print_receipt' => $done]];
        } catch (\Throwable $e) {
            return ['json' => ['status' => 'error', 'msg' => $e->getMessage()]];
        }
    }

    private function voidItem(array $post, int $locationId, int $userId): array
    {
        $mgr = User::verifyManager((string)($post['mgr_user'] ?? ''), (string)($post['mgr_pass'] ?? ''));
        if (!$mgr) {
            return ['json' => ['status' => 'error', 'msg' => 'Manager Authorization Failed.']];
        }
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                'SELECT si.*, s.payment_status FROM sale_items si JOIN sales s ON si.sale_id = s.id WHERE si.id = ?'
            );
            $stmt->execute([(int)$post['item_id']]);
            $item = $stmt->fetch();
            if ($item && $item['payment_status'] === 'pending' && !in_array($item['status'], ['voided', 'refunded'], true)) {
                $this->pdo->prepare("UPDATE sale_items SET status = 'voided', fulfillment_status = 'voided' WHERE id = ?")
                    ->execute([$post['item_id']]);
                $saleId = $item['sale_id'];
                $this->pdo->prepare(
                    "UPDATE sales SET subtotal = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')), final_total = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')) WHERE id = ?"
                )->execute([$saleId, $saleId, $saleId]);
                $this->pdo->prepare("UPDATE sales SET payment_status = 'voided' WHERE id = ? AND final_total <= 0")->execute([$saleId]);
                deductStock($this->pdo, $item['product_id'], -$item['quantity'], $locationId, $userId, 'void_return');
                $this->pdo->commit();
                return ['json' => ['status' => 'success']];
            }
            throw new \Exception('Cannot void this item. Table might already be paid.');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['json' => ['status' => 'error', 'msg' => $e->getMessage()]];
        }
    }

    private function addItem(array $post, array &$session, string $tier): void
    {
        $prod = Product::findWithCategory((int)$post['product_id']);
        if (!$prod) {
            return;
        }
        $pid = (int)$post['product_id'];
        $price = isset($post['custom_price']) ? (float)$post['custom_price'] : (float)$prod['price'];
        $isRefund = isset($post['is_refund']) && $post['is_refund'] === '1';
        if ($isRefund) {
            $price = -abs($price);
        }
        $refundSaleItemId = isset($post['refund_sale_item_id']) && $post['refund_sale_item_id'] !== 'undefined'
            ? (int)$post['refund_sale_item_id'] : 0;
        $key = $pid . '_' . md5((string)$price) . ($isRefund ? '_refund' : '');
        $fulfillment = 'collected';
        if (!$isRefund && in_array(strtolower($prod['cat_type']), ['food', 'meal'], true) && in_array($tier, ['pro+', 'enterprise'], true)) {
            $fulfillment = 'uncollected';
        }
        if (isset($session['cart'][$key])) {
            $session['cart'][$key]['qty']++;
        } else {
            $session['cart'][$key] = [
                'product_id' => $pid,
                'name' => ($isRefund ? '🔙 [RETURN] ' : '') . $prod['name'],
                'price' => $price,
                'qty' => 1,
                'cat_type' => $prod['cat_type'],
                'fulfillment' => $fulfillment,
                'type' => $prod['type'],
                'is_refund' => $isRefund,
                'refund_sale_item_id' => $refundSaleItemId,
            ];
        }
    }

    private function transferTabItems(array $post, array &$session, int $userId, int $locationId, int $activeShiftId): void
    {
        try {
            $this->pdo->beginTransaction();
            $sourceTabId = (int)$post['source_tab_id'];
            $targetTabId = $post['target_tab_id'];
            $selectedItems = $post['transfer_item_ids'] ?? [];
            if (empty($selectedItems)) {
                throw new \Exception('No items selected for transfer.');
            }
            if ($targetTabId === 'new') {
                $targetTableId = !empty($post['target_table_id']) ? (int)$post['target_table_id'] : null;
                $name = trim((string)($post['new_tab_name'] ?? ''));
                if ($name === '') {
                    if ($targetTableId) {
                        $stmtTbl = $this->pdo->prepare('SELECT table_name FROM restaurant_tables WHERE id = ?');
                        $stmtTbl->execute([$targetTableId]);
                        $name = $stmtTbl->fetchColumn() ?: 'Table ' . $targetTableId;
                    } else {
                        $name = 'Transferred ' . rand(100, 999);
                    }
                }
                $stmt = $this->pdo->prepare(
                    "INSERT INTO sales (location_id, table_id, user_id, shift_id, subtotal, final_total, payment_method, payment_status, customer_name) VALUES (?, ?, ?, ?, 0, 0, 'Pending', 'pending', ?)"
                );
                $stmt->execute([$locationId, $targetTableId, $userId, $activeShiftId, $name]);
                $targetTabId = $this->pdo->lastInsertId();
            } else {
                $targetTabId = (int)$targetTabId;
                if ($targetTabId === $sourceTabId) {
                    throw new \Exception('Cannot transfer to the same tab.');
                }
            }
            $inClause = implode(',', array_map('intval', $selectedItems));
            $this->pdo->prepare("UPDATE sale_items SET sale_id = ? WHERE id IN ($inClause) AND sale_id = ?")
                ->execute([$targetTabId, $sourceTabId]);
            $recalc = $this->pdo->prepare(
                "UPDATE sales SET subtotal = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')), final_total = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')) WHERE id = ?"
            );
            $recalc->execute([$sourceTabId, $sourceTabId, $sourceTabId]);
            $recalc->execute([$targetTabId, $targetTabId, $targetTabId]);
            $checkEmpty = $this->pdo->prepare("SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')");
            $checkEmpty->execute([$sourceTabId]);
            if ($checkEmpty->fetchColumn() == 0) {
                $this->pdo->prepare("UPDATE sales SET payment_status = 'voided' WHERE id = ?")->execute([$sourceTabId]);
            }
            $this->pdo->commit();
            $session['swal_type'] = 'success';
            $session['swal_msg'] = 'Items transferred successfully.';
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $session['swal_type'] = 'error';
            $session['swal_msg'] = 'Transfer Error: ' . $e->getMessage();
        }
    }

    private function finalizeSplit(array $post, array &$session, int $userId, int $locationId, int $activeShiftId): void
    {
        $splitData = json_decode($post['split_data'], true);
        if (!$splitData) {
            return;
        }
        try {
            $this->pdo->beginTransaction();
            $splitSaleIds = [];
            foreach ($splitData as $guest) {
                if ($guest['total'] <= 0) {
                    continue;
                }
                $status = ($guest['method'] === 'Pending') ? 'pending' : 'paid';
                $stmt = $this->pdo->prepare(
                    'INSERT INTO sales (location_id, user_id, shift_id, subtotal, tip_amount, final_total, payment_method, payment_status, customer_name) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([$locationId, $userId, $activeShiftId, $guest['total'], $guest['tip'], $guest['total'] + $guest['tip'], $guest['method'], $status, $guest['name']]);
                $saleId = $this->pdo->lastInsertId();
                if ($status === 'paid') {
                    $splitSaleIds[] = (int)$saleId;
                }
                if ($post['split_type'] === 'item') {
                    foreach ($guest['items'] as $item) {
                        $this->pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, fulfillment_status, status) VALUES (?, ?, ?, ?, 'collected', 'ready')")
                            ->execute([$saleId, $item['id'], $item['qty'], $item['price']]);
                        deductStock($this->pdo, $item['id'], $item['qty'], $locationId, $userId);
                    }
                } else {
                    $this->pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, fulfillment_status, status) VALUES (?, 0, 1, ?, 'collected', 'ready')")
                        ->execute([$saleId, $guest['total']]);
                }
            }
            if ($post['split_type'] === 'even') {
                foreach ($session['cart'] as $item) {
                    deductStock($this->pdo, $item['product_id'], $item['qty'], $locationId, $userId);
                }
            }
            $this->pdo->commit();
            $session['cart'] = [];
            $session['swal_type'] = 'success';
            $session['swal_msg'] = 'Split bills processed.';
            foreach ($splitSaleIds as $fiscalId) {
                $session['swal_msg'] .= fiscalizePaidSale($this->pdo, $fiscalId);
            }
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $session['swal_type'] = 'error';
            $session['swal_msg'] = 'Error: ' . $e->getMessage();
        }
    }

    private function addToTab(array $post, array &$session, int $userId, int $locationId, int $activeShiftId): void
    {
        try {
            $this->pdo->beginTransaction();
            $targetId = $post['target_tab_id'];
            $tableId = isset($post['target_table_id']) && $post['target_table_id'] !== '' ? (int)$post['target_table_id'] : null;
            if ($targetId === 'new') {
                $name = $post['tab_customer_name'] ?: 'Tab ' . rand(100, 999);
                $stmt = $this->pdo->prepare(
                    "INSERT INTO sales (location_id, table_id, user_id, shift_id, subtotal, final_total, payment_method, payment_status, customer_name) VALUES (?, ?, ?, ?, 0, 0, 'Pending', 'pending', ?)"
                );
                $stmt->execute([$locationId, $tableId, $userId, $activeShiftId, $name]);
                $targetId = $this->pdo->lastInsertId();
            } elseif ($tableId) {
                $this->pdo->prepare('UPDATE sales SET table_id = ? WHERE id = ?')->execute([$tableId, $targetId]);
            }
            $new_item_ids = [];
            foreach ($session['cart'] as $item) {
                $fulfillment = $item['fulfillment'] ?? 'collected';
                $itemStatus = ($fulfillment === 'uncollected') ? 'pending' : 'ready';
                $this->pdo->prepare('INSERT INTO sale_items (sale_id, product_id, quantity, price, fulfillment_status, status) VALUES (?, ?, ?, ?, ?, ?)')
                    ->execute([$targetId, $item['product_id'], $item['qty'], $item['price'], $fulfillment, $itemStatus]);
                $new_item_ids[] = $this->pdo->lastInsertId();
                $isRefund = $item['is_refund'] ?? false;
                $dbQty = $isRefund ? -$item['qty'] : $item['qty'];
                deductStock($this->pdo, $item['product_id'], $dbQty, $locationId, $userId, $isRefund ? 'refund' : 'sale');
            }
            $this->pdo->prepare(
                "UPDATE sales SET subtotal = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')), final_total = (SELECT COALESCE(SUM(price*quantity), 0) FROM sale_items WHERE sale_id = ? AND status NOT IN ('voided', 'refunded')) WHERE id = ?"
            )->execute([$targetId, $targetId, $targetId]);
            $this->pdo->commit();
            $session['cart'] = [];
            $session['swal_type'] = 'success';
            $session['swal_msg'] = 'Added to tab/table.';
            $session['last_bill_id'] = $targetId;
            $session['last_added_item_ids'] = implode(',', $new_item_ids);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $session['swal_type'] = 'error';
            $session['swal_msg'] = 'Error: ' . $e->getMessage();
        }
    }

    private function logExpense(array $post, array &$session, int $userId, int $locationId, int $activeShiftId): void
    {
        $amt = (float)$post['expense_amount'];
        $reason = trim((string)$post['expense_reason']);
        if ($amt <= 0 || $reason === '') {
            return;
        }
        $mgr = User::verifyManager((string)($post['mgr_username'] ?? ''), (string)($post['mgr_password'] ?? ''));
        if (!$mgr) {
            $session['swal_type'] = 'error';
            $session['swal_msg'] = 'Invalid manager credentials.';
            return;
        }
        try {
            $this->pdo->prepare('INSERT INTO expenses (location_id, user_id, shift_id, amount, reason) VALUES (?, ?, ?, ?, ?)')
                ->execute([$locationId, $userId, $activeShiftId, $amt, $reason . ' (Auth: ' . ($post['mgr_username'] ?? '') . ')']);
            $session['swal_type'] = 'success';
            $session['swal_msg'] = 'Payout logged.';
        } catch (\Throwable $e) {
            $session['swal_type'] = 'error';
            $session['swal_msg'] = 'Error: ' . $e->getMessage();
        }
    }
}
