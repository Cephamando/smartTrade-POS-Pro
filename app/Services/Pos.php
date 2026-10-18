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
                $session['swal_msg'] = 'That approval code was not accepted.';
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

        $ajax = !empty($post['ajax'])
            || strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0;

        if (!empty($post['lookup_refund_invoices'])) {
            $productId = (int)($post['product_id'] ?? 0);
            return ['json' => [
                'status' => 'ok',
                'product_id' => $productId,
                'invoices' => \App\Models\Sale::refundableInvoicesForProduct($productId, $locationId),
            ]];
        }
        if (!empty($post['lookup_tip_receipts'])) {
            $rows = \App\Models\Tip::searchReceipts($locationId, (string)($post['q'] ?? ''), $isManager, $userId);
            return ['json' => ['status' => 'ok', 'receipts' => array_map(static function (array $r): array {
                return [
                    'id' => (int)$r['id'],
                    'label' => '#' . $r['id'] . ($r['zra_rcpt_no'] ? ' · ZRA ' . $r['zra_rcpt_no'] : ''),
                    'customer' => (string)($r['customer_name'] ?? ''),
                    'total' => (float)$r['final_total'],
                    'method' => (string)$r['payment_method'],
                    'staff' => (string)($r['full_name'] ?: $r['username']),
                    'staff_id' => (int)$r['user_id'],
                    'time' => date('d M H:i', strtotime((string)$r['created_at'])),
                    'tips_so_far' => (float)$r['tips_so_far'],
                ];
            }, $rows)]];
        }
        if (isset($post['add_tip'])) {
            try {
                $tipId = \App\Models\Tip::record(
                    (int)($post['tip_sale_id'] ?? 0),
                    (float)($post['tip_value'] ?? 0),
                    (string)($post['tip_method'] ?? 'Cash'),
                    $isManager && !empty($post['tip_staff_id']) ? (int)$post['tip_staff_id'] : null,
                    $userId,
                    $activeShiftId ? (int)$activeShiftId : null,
                    $locationId,
                    (string)($post['tip_note'] ?? '')
                );
                $msg = 'Tip of ZMW ' . number_format((float)$post['tip_value'], 2) . ' recorded on receipt #' . (int)$post['tip_sale_id'] . '.';
                if ($ajax) {
                    return ['json' => ['status' => 'ok', 'msg' => $msg, 'tip_id' => $tipId]];
                }
                $session['swal_type'] = 'success';
                $session['swal_msg'] = $msg;
            } catch (\Throwable $e) {
                if ($ajax) {
                    return ['json' => ['status' => 'error', 'msg' => $e->getMessage()]];
                }
                $session['swal_type'] = 'error';
                $session['swal_msg'] = $e->getMessage();
            }
            return ['redirect' => true];
        }

        // Web order already paid online: mark it collected and print ZRA without leaving the POS.
        if (isset($post['complete_prepaid_order'])) {
            $saleId = (int)$post['complete_prepaid_order'];
            $ajax = !empty($post['ajax'])
                || strcasecmp((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''), 'XMLHttpRequest') === 0;
            $stmt = $this->pdo->prepare("SELECT id, payment_status, status FROM sales WHERE id = ? AND payment_method = 'Online'");
            $stmt->execute([$saleId]);
            $sale = $stmt->fetch();
            if (!$sale || $sale['payment_status'] !== 'paid' || $sale['status'] !== 'pending') {
                $msg = 'That order is not a prepaid web order.';
                if ($ajax) {
                    return ['json' => ['status' => 'error', 'msg' => $msg]];
                }
                $session['swal_type'] = 'error';
                $session['swal_msg'] = $msg;
                return ['redirect' => true];
            }
            $this->pdo->prepare("UPDATE sales SET status = 'completed', shift_id = COALESCE(shift_id, ?), user_id = ? WHERE id = ?")
                ->execute([$activeShiftId ?: null, $userId, $saleId]);
            $this->pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected', status = 'ready' WHERE sale_id = ? AND status NOT IN ('voided','refunded')")->execute([$saleId]);
            \App\Models\GuestNote::inviteCompletedSale($saleId);
            OrderWebhook::notify($this->pdo, $saleId, 'handed_over');
            $zra = fiscalizePaidSale($this->pdo, $saleId);
            $msg = 'Web order #' . $saleId . ' handed over.' . $zra;
            if ($ajax) {
                return ['json' => ['status' => 'ok', 'msg' => $msg, 'sale_id' => $saleId]];
            }
            $session['last_sale_id'] = $saleId;
            $session['swal_type'] = 'success';
            $session['swal_msg'] = $msg;
            return ['redirect' => true];
        }

        if (isset($post['add_item'])) {
            if (!$activeShiftId) {
                return $ajax
                    ? ['json' => ['status' => 'error', 'msg' => 'Open a shift before selling.']]
                    : ['redirect' => true];
            }
            try {
                $notice = $this->addItem($post, $session, $tier, $locationId);
                return $this->cartMutationResult($session, $locationId, $tier, $ajax, $notice);
            } catch (\Throwable $e) {
                return $ajax
                    ? ['json' => ['status' => 'error', 'msg' => $e->getMessage() ?: 'Unable to add the item.']]
                    : ['redirect' => true];
            }
        }
        if (isset($post['update_qty'])) {
            $key = $post['cart_key'] ?? '';
            $notice = null;
            try {
                if (isset($session['cart'][$key])) {
                    if (($post['action'] ?? '') === 'inc') {
                        $notice = $this->increaseCartItem($session, $key, $locationId);
                        if ($notice === null) {
                            $session['cart'][$key]['qty']++;
                        }
                    } elseif (($post['action'] ?? '') === 'dec') {
                        $session['cart'][$key]['qty']--;
                        if ($session['cart'][$key]['qty'] <= 0) {
                            unset($session['cart'][$key]);
                        }
                    }
                }
                return $this->cartMutationResult($session, $locationId, $tier, $ajax, $notice);
            } catch (\Throwable $e) {
                return $ajax
                    ? ['json' => ['status' => 'error', 'msg' => $e->getMessage() ?: 'Unable to update the cart.']]
                    : ['redirect' => true];
            }
        }
        if (isset($post['remove_item'])) {
            unset($session['cart'][$post['cart_key'] ?? '']);
            return $this->cartMutationResult($session, $locationId, $tier, $ajax, null);
        }
        if (isset($post['clear_cart'])) {
            $session['cart'] = [];
            unset($session['current_customer']);
            return $this->cartMutationResult($session, $locationId, $tier, $ajax, null);
        }
        if (isset($post['toggle_fulfillment'])) {
            $key = $post['cart_key'] ?? '';
            if (isset($session['cart'][$key]) && !($session['cart'][$key]['is_refund'] ?? false)) {
                if (!in_array(strtolower($session['cart'][$key]['cat_type'] ?? ''), ['food', 'meal'], true)) {
                    $session['cart'][$key]['fulfillment'] = ($session['cart'][$key]['fulfillment'] === 'collected') ? 'uncollected' : 'collected';
                }
            }
            return $this->cartMutationResult($session, $locationId, $tier, $ajax, null);
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
                $session['swal_msg'] = 'That approval code was not accepted.';
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
        $sysSettings = Setting::all();
        $portalOn = ($sysSettings['shop_enabled'] ?? '0') === '1';
        if ($tier === 'enterprise' || $portalOn) {
            $onlineTabs = $this->pdo->query(
                "SELECT s.id, s.total_amount, s.final_total, s.customer_name, s.customer_phone, s.split_group_id as external_id, s.created_at,
                        s.payment_status, s.order_type, s.order_notes, s.order_source, s.gateway_ref, s.table_id,
                        rt.table_name, rt.zone_name
                 FROM sales s
                 LEFT JOIN restaurant_tables rt ON rt.id = s.table_id
                 WHERE s.status = 'pending' AND s.payment_method = 'Online' ORDER BY s.created_at DESC"
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
            'sysSettings' => $sysSettings,
            'surchargeReasons' => \App\Models\SurchargeReason::all(true),
            'tipMethods' => \App\Models\Tip::METHODS,
            'staffUsers' => $isManager ? User::activeStaff() : [],
            'fx' => $this->fxForPos($sysSettings),
            'flwEnabled' => (new Flutterwave($this->pdo, $sysSettings))->enabled(),
            'flwTestMode' => (new Flutterwave($this->pdo, $sysSettings))->isTestMode(),
            'portalOn' => $portalOn,
            'categories' => Category::all(),
            'products' => Product::posCatalog($locationId),
            'services' => Product::activeServices(),
            'balance' => $balance,
            'openTabs' => $openTabs,
            'tabItems' => $tabItems,
            'occupiedTables' => $occupiedTables,
            'onlineTabs' => $onlineTabs,
            'onlineTabItems' => $onlineTabItems,
            'guestNoteOpen' => \App\Models\GuestNote::openAtLocation($locationId),
            'restaurantTables' => RestaurantTable::groupedForLocation($locationId),
            'cartStock' => $this->cartStockMap($session, $locationId),
            'cartQtyByProduct' => $this->cartQtyByProduct($session),
        ];
    }

    /** Rates for the payment window; never block the POS if the rate service is down. */
    private function fxForPos(array $settings): array
    {
        $fx = new Currency($this->pdo, $settings);
        if (!$fx->enabled()) {
            return ['enabled' => false, 'rates' => [], 'updated_at' => null];
        }
        try {
            return ['enabled' => true] + $fx->rates();
        } catch (\Throwable $e) {
            return ['enabled' => true, 'rates' => [], 'updated_at' => null, 'error' => $e->getMessage()];
        }
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
                return ['json' => ['status' => 'redirect_pickup', 'msg' => 'Collect this meal from the pickup screen.']];
            }
            if ($item['fulfillment_status'] === 'collected') {
                return ['json' => ['status' => 'error', 'msg' => 'This item has already been collected.']];
            }
            $this->pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected' WHERE id = ?")->execute([$itemId]);
            $stmtCheck = $this->pdo->prepare(
                "SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND fulfillment_status != 'collected' AND status NOT IN ('voided', 'refunded')"
            );
            $stmtCheck->execute([$item['sale_id']]);
            $done = $stmtCheck->fetchColumn() == 0;
            return ['json' => ['status' => 'success', 'sale_id' => $item['sale_id'], 'tab_completed' => $done, 'print_receipt' => $done]];
        } catch (\Throwable $e) {
            return ['json' => ['status' => 'error', 'msg' => 'Unable to update collection status.']];
        }
    }

    private function voidItem(array $post, int $locationId, int $userId): array
    {
        $mgr = User::verifyManager((string)($post['mgr_user'] ?? ''), (string)($post['mgr_pass'] ?? ''));
        if (!$mgr) {
            return ['json' => ['status' => 'error', 'msg' => 'That approval code was not accepted.']];
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
            throw new \Exception('This item cannot be voided. The table may already be paid.');
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['json' => ['status' => 'error', 'msg' => $e->getMessage()]];
        }
    }

    private function addItem(array $post, array &$session, string $tier, int $locationId): ?array
    {
        $prod = Product::findWithCategory((int)$post['product_id']);
        if (!$prod) {
            return ['kind' => 'info', 'text' => 'Product not found.'];
        }
        $isRefund = isset($post['is_refund']) && $post['is_refund'] === '1';
        if (!$isRefund) {
            $sell = \App\Helpers\Movement::sellOnPos($prod);
            if (!$sell['ok']) {
                return ['kind' => 'info', 'text' => (string)$sell['reason']];
            }
        }
        $pid = (int)$post['product_id'];
        $price = isset($post['custom_price']) ? (float)$post['custom_price'] : (float)$prod['price'];
        $refundSaleItemId = isset($post['refund_sale_item_id']) && $post['refund_sale_item_id'] !== 'undefined'
            ? (int)$post['refund_sale_item_id'] : 0;
        $origSaleId = 0;
        if ($isRefund) {
            if ($refundSaleItemId <= 0) {
                return ['kind' => 'info', 'text' => 'Choose the original sale to refund. Do not tap the product from the grid without picking the ticket.'];
            }
            $origLine = \App\Models\Sale::refundableLine($refundSaleItemId);
            if (!$origLine || (int)($origLine['product_id'] ?? 0) !== $pid) {
                return ['kind' => 'info', 'text' => 'That ticket line cannot be refunded for this item.'];
            }
            $origSaleId = (int)($origLine['sale_id'] ?? 0);
            $mixed = $this->refundCartOriginalSaleId($session);
            if ($mixed > 0 && $origSaleId > 0 && $mixed !== $origSaleId) {
                return ['kind' => 'info', 'text' => 'This refund cart is for sale #' . $mixed . '. Charge it first, then refund another ticket.'];
            }
            $price = -abs((float)$origLine['price']);
        }
        $key = $pid . '_' . md5((string)$price) . ($isRefund ? '_refund_' . $refundSaleItemId : '');
        $fulfillment = 'collected';
        if (!$isRefund && in_array(strtolower((string)($prod['cat_type'] ?? '')), ['food', 'meal'], true) && in_array($tier, ['pro+', 'enterprise'], true)) {
            $fulfillment = 'uncollected';
        }
        $nextQty = (isset($session['cart'][$key]) ? (float)$session['cart'][$key]['qty'] : 0) + 1;
        if ($isRefund) {
            $blocked = $this->refundQtyBlocked($session, $refundSaleItemId, $nextQty, $key);
            if ($blocked) {
                return $blocked;
            }
        } else {
            $shortage = $this->saleShortage($session, $prod, $locationId, $nextQty, $key);
            if ($shortage) {
                return $shortage;
            }
        }
        if (isset($session['cart'][$key])) {
            $session['cart'][$key]['qty']++;
        } else {
            $label = $prod['name'];
            if ($isRefund) {
                $label = '🔙 [RETURN] ' . $prod['name'] . ($origSaleId > 0 ? ' · #' . $origSaleId : '');
            }
            $session['cart'][$key] = [
                'product_id' => $pid,
                'name' => $label,
                'price' => $price,
                'qty' => 1,
                'cat_type' => $prod['cat_type'],
                'fulfillment' => $fulfillment,
                'type' => $prod['type'],
                'is_refund' => $isRefund,
                'tracks_stock' => \App\Helpers\Movement::tracksOnHandStock($prod),
                'refund_sale_item_id' => $refundSaleItemId,
                'refund_sale_id' => $origSaleId,
            ];
        }
        return null;
    }

    private function cartQtyByProduct(array $session, ?string $exceptKey = null): array
    {
        $qty = [];
        foreach ($session['cart'] ?? [] as $key => $item) {
            if ($exceptKey !== null && $key === $exceptKey) {
                continue;
            }
            if (!empty($item['is_refund'])) {
                continue;
            }
            $pid = (int)($item['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $qty[$pid] = ($qty[$pid] ?? 0) + (float)$item['qty'];
        }
        return $qty;
    }

    private function cartStockMap(array $session, int $locationId): array
    {
        $map = [];
        foreach ($session['cart'] ?? [] as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            if ($pid <= 0 || isset($map[$pid])) {
                continue;
            }
            $map[$pid] = \App\Models\Inventory::quantity($pid, $locationId);
        }
        return $map;
    }

    private function saleShortage(array $session, array $product, int $locationId, float $nextLineQty, ?string $exceptKey = null): ?array
    {
        $pid = (int)$product['id'];
        $otherQty = $this->cartQtyByProduct($session, $exceptKey)[$pid] ?? 0;
        $wanted = $otherQty + $nextLineQty;
        $prompt = \App\Helpers\Movement::shortagePrompt($product, $locationId, $wanted);
        if (!$prompt) {
            return null;
        }
        $prompt['kind'] = 'shortage';
        return $prompt;
    }

    private function increaseCartItem(array $session, string $key, int $locationId): ?array
    {
        $item = $session['cart'][$key] ?? null;
        if (!$item) {
            return ['kind' => 'info', 'text' => 'That cart line is gone.'];
        }
        if (!empty($item['is_refund'])) {
            $origId = (int)($item['refund_sale_item_id'] ?? 0);
            if ($origId <= 0) {
                return ['kind' => 'info', 'text' => 'This return is not linked to a sale. Remove it and pick the original ticket.'];
            }
            return $this->refundQtyBlocked($session, $origId, (float)$item['qty'] + 1, $key);
        }
        $prod = Product::findWithCategory((int)$item['product_id']);
        if (!$prod) {
            return null;
        }
        return $this->saleShortage($session, $prod, $locationId, (float)$item['qty'] + 1, $key);
    }

    private function refundCartOriginalSaleId(array $session): int
    {
        foreach ($session['cart'] ?? [] as $item) {
            if (empty($item['is_refund'])) {
                continue;
            }
            $saleId = (int)($item['refund_sale_id'] ?? 0);
            if ($saleId > 0) {
                return $saleId;
            }
        }
        return 0;
    }

    private function refundQtyBlocked(array $session, int $refundSaleItemId, float $nextQty, ?string $exceptKey = null): ?array
    {
        $orig = \App\Models\Sale::refundableLine($refundSaleItemId);
        $remaining = $orig ? (float)$orig['remaining'] : 0.0;
        $inCart = 0.0;
        foreach ($session['cart'] ?? [] as $key => $item) {
            if ($exceptKey !== null && (string)$key === $exceptKey) {
                continue;
            }
            if ((int)($item['refund_sale_item_id'] ?? 0) !== $refundSaleItemId) {
                continue;
            }
            $inCart += (float)($item['qty'] ?? 0);
        }
        if ($inCart + $nextQty > $remaining + 0.0001) {
            $left = max(0, $remaining - $inCart);
            return [
                'kind' => 'info',
                'text' => $left <= 0.0001
                    ? 'Nothing is left to refund on that ticket line.'
                    : 'Only ' . \App\Helpers\Movement::formatQty($left) . ' left to refund on that ticket.',
            ];
        }
        return null;
    }

    private function flashNotice(array &$session, ?array $notice): void
    {
        if (!$notice) {
            return;
        }
        if (($notice['kind'] ?? '') === 'shortage') {
            $session['swal_type'] = 'warning';
            $session['swal_title'] = $notice['title'] ?? 'Not enough stock';
            $session['swal_msg'] = $notice['text'] ?? '';
            $session['swal_html'] = $notice['html'] ?? '';
            $session['swal_confirm'] = 'Stay on POS';
            $session['swal_cancel'] = '';
            $session['swal_url'] = '';
            return;
        }
        $session['swal_type'] = $notice['kind'] === 'info' ? 'info' : 'error';
        $session['swal_msg'] = $notice['text'] ?? '';
    }

    public function cartState(array $session, int $locationId, string $tier): array
    {
        return $this->cartAjaxResponse($session, $locationId, $tier, null);
    }

    public function onHandForSale(int $saleId, int $locationId): array
    {
        $stmt = $this->pdo->prepare('SELECT DISTINCT product_id FROM sale_items WHERE sale_id = ?');
        $stmt->execute([$saleId]);
        $onHand = [];
        foreach ($stmt->fetchAll(\PDO::FETCH_COLUMN) as $productId) {
            $productId = (int)$productId;
            if ($productId <= 0) {
                continue;
            }
            $onHand[(string)$productId] = \App\Models\Inventory::quantity($productId, $locationId);
        }
        return $onHand;
    }

    private function cartMutationResult(array &$session, int $locationId, string $tier, bool $ajax, ?array $notice): array
    {
        if ($ajax) {
            return ['json' => $this->cartAjaxResponse($session, $locationId, $tier, $notice)];
        }
        $this->flashNotice($session, $notice);
        return ['redirect' => true];
    }

    private function cartAjaxResponse(array $session, int $locationId, string $tier, ?array $notice): array
    {
        $cart = $session['cart'] ?? [];
        $balance = 0.0;
        foreach ($cart as $item) {
            $balance += ((float)$item['price']) * ((float)$item['qty']);
        }
        $payload = [
            'status' => ($notice['kind'] ?? '') === 'shortage' ? 'shortage' : (($notice['kind'] ?? '') === 'info' ? 'notice' : 'ok'),
            'cart_html' => $this->renderCartItems($session, $tier),
            'cart_count' => count($cart),
            'balance' => $balance,
            'balance_label' => number_format($balance, 2),
            'empty' => $cart === [],
            'cart_qty' => (object)$this->cartQtyByProduct($session),
            'notice' => $notice,
        ];
        return $payload;
    }

    private function renderCartItems(array $session, string $tier): string
    {
        $cart = $session['cart'] ?? [];
        ob_start();
        include APP_PATH . '/Views/pos_cart_items.php';
        return (string)ob_get_clean();
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
            $session['swal_msg'] = 'Items transferred.';
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $session['swal_type'] = 'error';
            $session['swal_msg'] = 'Unable to transfer items.';
        }
    }

    private function finalizeSplit(array $post, array &$session, int $userId, int $locationId, int $activeShiftId): void
    {
        $splitData = json_decode($post['split_data'], true);
        if (!$splitData) {
            return;
        }
        try {
            \App\Helpers\Movement::assertCartStock($session['cart'] ?? [], $locationId);
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
            $session['swal_msg'] = 'Unable to process split bills.';
        }
    }

    private function addToTab(array $post, array &$session, int $userId, int $locationId, int $activeShiftId): void
    {
        try {
            $this->pdo->beginTransaction();
            \App\Helpers\Movement::assertCartStock($session['cart'] ?? [], $locationId);
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
            $session['swal_msg'] = 'Items added to the tab.';
            $session['last_bill_id'] = $targetId;
            $session['last_added_item_ids'] = implode(',', $new_item_ids);
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            $session['swal_type'] = 'error';
            $session['swal_msg'] = trim($e->getMessage()) !== '' ? $e->getMessage() : 'Unable to add items to the tab.';
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
            $session['swal_msg'] = 'That approval code was not accepted.';
            return;
        }
        try {
            $this->pdo->prepare('INSERT INTO expenses (location_id, user_id, shift_id, amount, reason) VALUES (?, ?, ?, ?, ?)')
                ->execute([$locationId, $userId, $activeShiftId, $amt, $reason . ' (Auth: ' . ($post['mgr_username'] ?? '') . ')']);
            $session['swal_type'] = 'success';
            $session['swal_msg'] = 'Payout logged.';
        } catch (\Throwable $e) {
            $session['swal_type'] = 'error';
            $session['swal_msg'] = 'Unable to log the payout.';
        }
    }
}
