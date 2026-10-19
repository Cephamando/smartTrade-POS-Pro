<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\Event;
use App\Models\GuestNote;
use App\Models\Location;
use App\Models\RestaurantTable;
use App\Models\Setting;
use App\Services\Currency;
use App\Services\Flutterwave;
use PDO;

/**
 * Public customer ordering & registration page (index.php?page=shop).
 * Session keys are prefixed shop_* so a staff login on the same browser is unaffected.
 */
final class ShopController extends Controller
{
    private array $settings;
    private int $shopLoc = 0;
    /** @var array<int, array<string, mixed>> */
    private array $shopLocations = [];

    public function index(): void
    {
        $this->settings = Setting::all();
        $view = (string)($_GET['view'] ?? 'home');
        $eventView = in_array($view, ['events', 'event', 'rsvp', 'visit', 'pay_return', 'thanks'], true);
        if (!$eventView && ($this->settings['shop_enabled'] ?? '0') !== '1' && !$this->isManager()) {
            $this->render(['view' => 'closed', 'settings' => $this->settings, 'customer' => null, 'cart' => [], 'cartTotal' => 0, 'flash' => null]);
            return;
        }
        $this->applyShopLocation();
        $view = (string)($_GET['view'] ?? 'home');
        if ($view === 'face') {
            $this->shopFace();
        }
        $customer = !empty($_SESSION['shop_customer_id']) ? Customer::find((int)$_SESSION['shop_customer_id']) : null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost($customer);
        }
        if ($view === 'pay_return') {
            $this->payReturn($customer);
        }
        if ($view === 'pay_status') {
            $this->jsonExit($this->shopPaymentStatus($customer, (int)($_GET['id'] ?? 0)));
        }

        $cart = $this->cart();
        $cartTotal = array_sum(array_map(fn($l) => $l['price'] * $l['qty'], $cart));
        $data = [
            'view' => $view,
            'settings' => $this->settings,
            'customer' => $customer,
            'cart' => $cart,
            'cartTotal' => $cartTotal,
            'cartCount' => array_sum(array_column($cart, 'qty')),
            'flash' => $this->takeFlash(),
            'flwGateway' => (new Flutterwave($this->pdo(), $this->settings))->enabled(),
            'flwOn' => ($this->settings['shop_pay_online'] ?? '1') === '1' && (new Flutterwave($this->pdo(), $this->settings))->enabled(),
            'deliveryOn' => ($this->settings['shop_delivery'] ?? '1') === '1',
            'deliveryFee' => (float)($this->settings['shop_delivery_fee'] ?? 0),
            'minOrder' => (float)($this->settings['shop_min_order'] ?? 0),
            'shopLocations' => $this->shopLocations,
            'shopLocationId' => $this->shopLocationId(),
            'fx' => $this->shopRates(),
        ];
        $shopTable = $this->shopTable();
        $reserve = $this->reservePreset();
        $data['shopTable'] = $shopTable;
        $data['shopTables'] = RestaurantTable::freeForLocation($this->shopLocationId(), $view === 'reserve' ? 0 : (int)($shopTable['id'] ?? 0));
        $data['reserveOn'] = $shopTable && !empty($_SESSION['shop_table_reserve']);
        $data['reserveAmount'] = $reserve['amount'];
        $data['reserveLabel'] = $reserve['label'];
        if ($view === 'home' || $view === 'menu') {
            [$data['categories'], $data['products']] = $this->menu();
        }
        if ($view === 'home') {
            $data['events'] = Event::publishedOpen();
        } elseif ($view === 'events') {
            $data['events'] = Event::publishedOpen();
        } elseif ($view === 'event') {
            $data['event'] = Event::publicBySlug((string)($_GET['slug'] ?? ''));
            $data['options'] = $data['event'] ? Event::options((int)$data['event']['id']) : [];
            $data['tiers'] = $data['event'] ? Event::tiers((int)$data['event']['id']) : [];
        } elseif ($view === 'rsvp') {
            $guest = Event::guestByToken((string)($_GET['token'] ?? ''));
            $data['guest'] = $guest;
            $data['event'] = $guest ? Event::find((int)$guest['event_id']) : null;
            $data['options'] = $data['event'] ? Event::options((int)$data['event']['id']) : [];
            $data['tiers'] = $data['event'] ? Event::tiers((int)$data['event']['id']) : [];
            $data['picked'] = $guest ? Event::selectionIds((int)$guest['id']) : [];
            $data['payments'] = $guest ? Event::paymentsForGuest((int)$guest['id']) : [];
            $data['charge'] = ($guest && $data['event']) ? Event::nextCharge($data['event'], (int)$guest['id']) : ['amount' => 0, 'purpose' => 'deposit'];
            $data['paid'] = $guest ? Event::paidTotal((int)$guest['id']) : 0;
            if ($guest) {
                $this->refreshEventPayments($guest);
                $data['payments'] = Event::paymentsForGuest((int)$guest['id']);
                $data['charge'] = Event::nextCharge($data['event'], (int)$guest['id']);
                $data['paid'] = Event::paidTotal((int)$guest['id']);
            }
        } elseif ($view === 'orders' && $customer) {
            $data['orders'] = Customer::orders((int)$customer['id']);
        } elseif ($view === 'thanks' && $customer) {
            $data['order'] = Customer::order((int)$customer['id'], (int)($_GET['id'] ?? 0));
            if (!$data['order'] || ($data['order']['payment_status'] ?? '') !== 'paid') {
                $this->flashShop('error', 'That order is not paid yet.');
                $this->go($data['order'] ? 'order' : 'orders', $data['order'] ? ['id' => (int)$data['order']['id']] : []);
            }
        } elseif ($view === 'order' && $customer) {
            $data['order'] = Customer::order((int)$customer['id'], (int)($_GET['id'] ?? 0));
            if (!$data['order']) {
                $this->go('orders');
            }
            $data['visitAnswered'] = GuestNote::forSale((int)$data['order']['id']) !== null;
            if (($data['order']['payment_status'] ?? '') !== 'paid' && trim((string)($data['order']['gateway_ref'] ?? '')) !== '') {
                $settled = $this->confirmShopPayment((int)$data['order']['id'], (string)$data['order']['gateway_ref']);
                if ($settled === 'paid') {
                    $this->flashShop('success', 'Payment received. Your order #' . (int)$data['order']['id'] . ' is being prepared.');
                    $this->go('thanks', ['id' => (int)$data['order']['id']]);
                }
            }
        } elseif ($view === 'pay' && $customer) {
            $saleId = (int)($_GET['id'] ?? 0);
            $order = Customer::order((int)$customer['id'], $saleId);
            if (!$order) {
                $this->go('orders');
            }
            if (($order['payment_status'] ?? '') === 'paid') {
                $this->flashShop('success', 'This order is already paid.');
                $this->go('order', ['id' => $saleId]);
            }
            if (($order['status'] ?? '') !== 'pending') {
                $this->flashShop('error', 'This order can no longer be paid online.');
                $this->go('order', ['id' => $saleId]);
            }
            try {
                $data['checkout'] = $this->startOrderPayment($customer, $saleId, (float)$order['final_total'], (int)$order['location_id']);
            } catch (\Throwable $e) {
                $this->flashShop('error', $e->getMessage());
                $this->go('order', ['id' => $saleId]);
            }
            $data['order'] = $order;
        } elseif (in_array($view, ['orders', 'order', 'account', 'checkout', 'pay', 'thanks'], true) && !$customer) {
            $_SESSION['shop_after_login'] = $view;
            $this->go('login');
        }
        if ($view === 'visit') {
            $ctx = $this->visitContext($customer);
            $data['visitLocation'] = $ctx['location'];
            $data['visitSale'] = $ctx['sale'];
            $data['visitTable'] = $ctx['table'];
            $data['visitGoogle'] = $ctx['google'];
            $data['visitAnswered'] = $ctx['answered'];
            $data['visitMood'] = $ctx['mood'];
            $data['visitProblem'] = $ctx['problem'];
            $data['visitQuery'] = $ctx['query'];
            $data['visitStores'] = $ctx['stores'];
            $data['visitStep'] = (string)($_GET['step'] ?? '');
        }
        $this->render($data);
    }

    private function shopFace(): never
    {
        [, $products] = $this->menu();
        $items = [];
        $menuSlides = [];
        foreach ($products as $p) {
            $photo = $this->faceFile((string)($p['image_path'] ?? ''));
            $items[] = ['id' => (int)$p['id'], 'src' => $photo['src'], 'v' => $photo['v']];
            if ($photo['src'] === '') {
                continue;
            }
            $menuSlides[] = [
                'id' => (int)$p['id'],
                'src' => $photo['src'],
                'v' => $photo['v'],
                'name' => (string)$p['name'],
                'cat' => (string)$p['category'],
            ];
        }
        $slides = $this->promoSlides() ?: $menuSlides;
        $events = [];
        foreach (Event::publishedOpen() as $ev) {
            $cover = $this->faceFile((string)($ev['cover_path'] ?? ''));
            $events[] = [
                'slug' => (string)$ev['slug'],
                'title' => (string)$ev['title'],
                'src' => $cover['src'],
                'v' => $cover['v'],
            ];
        }
        $logo = $this->faceFile((string)($this->settings['logo_path'] ?? ''));
        $banner = $this->faceFile((string)($this->settings['shop_banner_path'] ?? ''));
        $this->jsonExit([
            'status' => 'ok',
            'logo' => $logo,
            'banner' => $banner,
            'slides' => $slides,
            'items' => $items,
            'events' => $events,
        ]);
    }

    /** @return list<array{id: string, src: string, v: int, name: string, cat: string}> */
    private function promoSlides(): array
    {
        $slides = [];
        foreach (\App\Helpers\Media::folder('promo') as $src) {
            $photo = $this->faceFile($src);
            if ($photo['src'] === '') {
                continue;
            }
            $slides[] = [
                'id' => $photo['src'],
                'src' => $photo['src'],
                'v' => $photo['v'],
                'name' => 'Slideshow',
                'cat' => '',
            ];
        }
        return $slides;
    }

    /** @return array{src: string, v: int} */
    private function faceFile(string $relative): array
    {
        $src = \App\Helpers\Media::url($relative);
        if ($src === '') {
            return ['src' => '', 'v' => 0];
        }
        $file = dirname(__DIR__, 2) . '/public/' . $src;
        return ['src' => $src, 'v' => is_file($file) ? (int)filemtime($file) : 0];
    }

    // ------------------------------------------------------------------ POST actions

    private function handlePost(?array $customer): void
    {
        $p = $_POST;
        try {
            if (isset($p['visit_mood']) || isset($p['visit_save'])) {
                $this->saveVisit($p, $customer);
            }
            if (isset($p['event_apply']) || isset($p['event_reply']) || isset($p['event_pay'])) {
                $this->handleEventPost($p);
            }
            if (isset($p['register'])) {
                $id = Customer::register((string)$p['name'], (string)$p['phone'], (string)($p['email'] ?? ''), (string)$p['password'], (string)($p['address'] ?? ''));
                $_SESSION['shop_customer_id'] = $id;
                $this->flashShop('success', 'Welcome! Your account is ready.');
                $this->go($this->afterLogin());
            }
            if (isset($p['login'])) {
                $c = Customer::authenticate((string)$p['phone'], (string)$p['password']);
                if (!$c) {
                    throw new \RuntimeException('Phone number or PIN is incorrect.');
                }
                $_SESSION['shop_customer_id'] = (int)$c['id'];
                $this->flashShop('success', 'Signed in as ' . $c['name'] . '.');
                $this->go($this->afterLogin());
            }
            if (isset($p['logout'])) {
                unset($_SESSION['shop_customer_id'], $_SESSION['shop_cart']);
                $this->flashShop('success', 'Signed out.');
                $this->go('menu');
            }
            if (isset($p['cart_add'])) {
                $this->cartAdd((int)$p['cart_add'], max(1, (int)($p['qty'] ?? 1)));
                if (!empty($p['ajax'])) {
                    $cart = $this->cart();
                    $this->jsonExit(['status' => 'ok', 'count' => array_sum(array_column($cart, 'qty')), 'total' => array_sum(array_map(fn($l) => $l['price'] * $l['qty'], $cart))]);
                }
                $this->go('menu');
            }
            if (isset($p['cart_clear'])) {
                $_SESSION['shop_cart'] = [];
                $this->go('menu');
            }
            if (isset($p['choose_seat'])) {
                $this->chooseSeat($p);
            }
            if (isset($p['cart_update'])) {
                $cart = $this->cart();
                foreach ((array)($p['qty'] ?? []) as $pid => $qty) {
                    $pid = (int)$pid;
                    $qty = max(0, (int)$qty);
                    if ($qty === 0) {
                        unset($cart[$pid]);
                    } elseif (isset($cart[$pid])) {
                        $cart[$pid]['qty'] = $qty;
                    }
                }
                $_SESSION['shop_cart'] = $cart;
                $this->go('cart');
            }
            if (isset($p['update_profile']) && $customer) {
                Customer::updateProfile((int)$customer['id'], (string)$p['name'], (string)($p['email'] ?? ''), (string)($p['address'] ?? ''), (string)($p['new_password'] ?? ''));
                $this->flashShop('success', 'Details updated.');
                $this->go('account');
            }
            if (isset($p['place_order'])) {
                if (!$customer) {
                    $_SESSION['shop_after_login'] = 'checkout';
                    $this->go('login');
                }
                $this->placeOrder($customer, $p);
            }
            if (isset($p['reserve_table'])) {
                if (!$customer) {
                    $_SESSION['shop_after_login'] = 'reserve';
                    $this->go('login');
                }
                $this->placeReservation($customer, $p);
            }
            if (isset($p['cancel_order'])) {
                if (!$customer) {
                    $_SESSION['shop_after_login'] = 'orders';
                    $this->go('login');
                }
                $saleId = (int)($p['sale_id'] ?? 0);
                $result = \App\Models\Sale::cancelWebOrder($saleId, (int)$customer['id'], true);
                $this->flashShop('success', $result['paid']
                    ? 'Order #' . $saleId . ' is cancelled. It stays with the store until Flutterwave pays the funds out.'
                    : 'Order #' . $saleId . ' is cancelled. Nothing was charged.');
                $this->go('order', ['id' => $saleId]);
            }
            if (isset($p['pay_order'])) {
                if (!$customer) {
                    $_SESSION['shop_after_login'] = 'orders';
                    $this->go('login');
                }
                $this->payExistingOrder($customer, (int)$p['pay_order']);
            }
        } catch (\Throwable $e) {
            if (!empty($p['ajax'])) {
                $this->jsonExit(['status' => 'error', 'msg' => $e->getMessage()]);
            }
            $this->flashShop('error', $e->getMessage());
            $view = (string)($_GET['view'] ?? 'home');
            $extra = [];
            if ($view === 'event') {
                $extra['slug'] = (string)($_GET['slug'] ?? $p['slug'] ?? '');
            } elseif ($view === 'rsvp') {
                $extra['token'] = (string)($_GET['token'] ?? $p['token'] ?? '');
            } elseif ($view === 'order') {
                $extra['id'] = (int)($p['sale_id'] ?? $_GET['id'] ?? 0);
            }
            $this->go($view === 'pay_return' ? 'orders' : $view, $extra);
        }
    }

    // ------------------------------------------------------------------ catalogue & cart

    private function applyShopLocation(): void
    {
        $this->shopLocations = Location::sellable();
        $allowed = array_map('intval', array_column($this->shopLocations, 'id'));
        $pick = (string)($_GET['view'] ?? '') === 'visit' ? 0 : (int)($_GET['loc'] ?? 0);
        if ($pick <= 0) {
            $pick = (int)($_SESSION['shop_location_id'] ?? 0);
        }
        if (!in_array($pick, $allowed, true)) {
            $pick = (int)($this->settings['shop_location_id'] ?? 0);
        }
        if (!in_array($pick, $allowed, true)) {
            $pick = $allowed[0] ?? 1;
        }
        $prev = (int)($_SESSION['shop_location_id'] ?? 0);
        if ($prev > 0 && $prev !== $pick && !empty($_SESSION['shop_cart'])) {
            $_SESSION['shop_cart'] = [];
            $this->flashShop('success', 'Basket cleared because you changed store.');
        }
        $_SESSION['shop_location_id'] = $pick;
        $this->shopLoc = $pick;
    }

    private function chooseSeat(array $p): never
    {
        $allowed = array_map('intval', array_column($this->shopLocations, 'id'));
        $loc = (int)($p['seat_location'] ?? 0);
        if (in_array($loc, $allowed, true) && $loc !== $this->shopLocationId()) {
            $_SESSION['shop_location_id'] = $loc;
            $this->shopLoc = $loc;
            unset($_SESSION['shop_table_id'], $_SESSION['shop_table_reserve']);
            $this->go('cart');
        }
        $tableId = (int)($p['seat_table'] ?? 0);
        if ($tableId <= 0) {
            unset($_SESSION['shop_table_id'], $_SESSION['shop_table_reserve']);
            $this->go('cart');
        }
        $freeIds = array_map('intval', array_column(RestaurantTable::freeForLocation($this->shopLocationId()), 'id'));
        $table = RestaurantTable::find($tableId);
        if (!$table || (int)$table['location_id'] !== $this->shopLocationId() || !in_array($tableId, $freeIds, true)) {
            throw new \RuntimeException('That table is not free.');
        }
        $_SESSION['shop_table_id'] = $tableId;
        $_SESSION['shop_table_reserve'] = (!empty($p['reserve_table']) && $this->reservePreset()['amount'] > 0) ? 1 : 0;
        $this->go('cart');
    }

    private function shopTable(): ?array
    {
        $id = (int)($_SESSION['shop_table_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }
        $table = RestaurantTable::find($id);
        if (!$table || (int)$table['location_id'] !== $this->shopLocationId()) {
            unset($_SESSION['shop_table_id'], $_SESSION['shop_table_reserve']);
            return null;
        }
        return $table;
    }

    /** @return array{label: string, amount: float} */
    private function reservePreset(): array
    {
        return [
            'label' => TablesController::reserveLabel($this->settings),
            'amount' => round(max(0, (float)($this->settings['shop_reserve_amount'] ?? 0)), 2),
        ];
    }

    private function shopLocationId(): int
    {
        if ($this->shopLoc > 0) {
            return $this->shopLoc;
        }
        return (int)($_SESSION['shop_location_id'] ?? 1) ?: 1;
    }

    /** @return array{0: array, 1: array} categories, sellable products with availability */
    private function menu(): array
    {
        \App\Models\Product::ensureSchema();
        $loc = $this->shopLocationId();
        $stmt = $this->pdo()->prepare(
            "SELECT p.id, p.name, p.sku, p.price, p.unit, p.category_id, p.image_path, c.name AS category, cp.name AS parent_name, c.type AS cat_type,
                    COALESCE(i.quantity, 0) AS on_hand,
                    (SELECT COUNT(*) FROM product_recipes r WHERE r.parent_product_id = p.id) AS is_recipe
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             LEFT JOIN categories cp ON cp.id = c.parent_id
             LEFT JOIN inventory i ON i.product_id = p.id AND i.location_id = ?
             WHERE p.is_active = 1 AND p.type = 'item' AND COALESCE(p.is_sellable, 1) = 1 AND p.price > 0 AND COALESCE(p.is_open_price, 0) = 0
             ORDER BY c.name, p.name"
        );
        $stmt->execute([$loc]);
        $products = [];
        $cats = [];
        foreach ($stmt->fetchAll() as $r) {
            $r['available'] = $r['is_recipe'] > 0 || (float)$r['on_hand'] > 0;
            $r['category'] = $r['category'] ?: 'Other';
            $cats[$r['category']] = ($cats[$r['category']] ?? 0) + 1;
            $products[] = $r;
        }
        return [$cats, $products];
    }

    private function cart(): array
    {
        return is_array($_SESSION['shop_cart'] ?? null) ? $_SESSION['shop_cart'] : [];
    }

    private function cartAdd(int $pid, int $qty): void
    {
        $stmt = $this->pdo()->prepare("SELECT id, name, price FROM products WHERE id = ? AND is_active = 1 AND COALESCE(is_sellable,1) = 1 AND price > 0");
        $stmt->execute([$pid]);
        $p = $stmt->fetch();
        if (!$p) {
            throw new \RuntimeException('That item is not available right now.');
        }
        $cart = $this->cart();
        $cart[$pid] = ['id' => $pid, 'name' => $p['name'], 'price' => (float)$p['price'], 'qty' => ($cart[$pid]['qty'] ?? 0) + $qty];
        $_SESSION['shop_cart'] = $cart;
    }

    // ------------------------------------------------------------------ ordering

    /** @param array<string, mixed> $customer
     *  @param array<string, mixed> $p */
    private function placeReservation(array $customer, array $p): void
    {
        $preset = $this->reservePreset();
        if ($preset['amount'] <= 0) {
            throw new \RuntimeException('This store has not set a reservation fee.');
        }
        if (($this->settings['shop_pay_online'] ?? '1') !== '1' || !(new Flutterwave($this->pdo(), $this->settings))->enabled()) {
            throw new \RuntimeException('Online payment is not available at the moment.');
        }
        $allowed = array_map('intval', array_column($this->shopLocations, 'id'));
        $loc = (int)($p['seat_location'] ?? 0);
        if (!in_array($loc, $allowed, true)) {
            throw new \RuntimeException('Choose a store.');
        }
        $tableId = (int)($p['seat_table'] ?? 0);
        $table = RestaurantTable::find($tableId);
        if (!$table || (int)$table['location_id'] !== $loc) {
            throw new \RuntimeException('Choose a free table.');
        }
        $freeIds = array_map(static fn(array $row): int => (int)$row['id'], RestaurantTable::freeForLocation($loc, 0));
        if (!in_array($tableId, $freeIds, true)) {
            throw new \RuntimeException('That table is already reserved.');
        }
        $minutes = TablesController::reserveMinutes($this->settings);
        $percent = TablesController::reserveRefundPercent($this->settings);
        $notes = $preset['label'] . '. Held for ' . $minutes . ' minutes after payment. ' . TablesController::reserveRefundText($preset['amount'], $percent);
        $fee = $preset['amount'];
        $pdo = $this->pdo();
        $pdo->beginTransaction();
        try {
            $shift = $pdo->prepare("SELECT id FROM shifts WHERE location_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
            $shift->execute([$loc]);
            $shiftId = $shift->fetchColumn() ?: null;
            $sysUser = (int)($pdo->query("SELECT id FROM users WHERE role IN ('admin','dev') ORDER BY id LIMIT 1")->fetchColumn() ?: 1);
            $extId = 'RSV-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            $pdo->prepare(
                "INSERT INTO sales (location_id, table_id, user_id, shift_id, subtotal, total_amount, final_total, surcharge_amount, surcharge_reason, status, payment_status, payment_method,
                                    split_group_id, customer_name, customer_id, customer_phone, order_source, order_type, order_notes, member_id, created_at)
                 VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?, 'pending', 'pending', 'Online', ?, ?, ?, ?, 'portal', 'reservation', ?, ?, NOW())"
            )->execute([
                $loc, $tableId, $sysUser, $shiftId, $fee, $fee, $fee, $preset['label'],
                $extId, 'ONLINE: ' . $customer['name'], (int)$customer['id'], $customer['phone'], substr($notes, 0, 500),
                $customer['member_id'] ?: null,
            ]);
            $saleId = (int)$pdo->lastInsertId();
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        try {
            $this->startOrderPayment($customer, $saleId, $fee, $loc);
        } catch (\Throwable $e) {
            $this->flashShop('error', 'The payment window did not open. Reservation #' . $saleId . ' is saved. Open it and try Pay now.');
            $this->go('order', ['id' => $saleId]);
        }
        $this->go('pay', ['id' => $saleId]);
    }

    private function placeOrder(array $customer, array $p): void
    {
        $cart = $this->cart();
        if (!$cart) {
            throw new \RuntimeException('Your basket is empty.');
        }
        $pdo = $this->pdo();
        $loc = $this->shopLocationId();
        $table = $this->shopTable();
        $orderType = ($p['order_type'] ?? 'pickup') === 'delivery' && ($this->settings['shop_delivery'] ?? '1') === '1' && !$table ? 'delivery' : 'pickup';
        if (($this->settings['shop_pay_online'] ?? '1') !== '1' || !(new Flutterwave($pdo, $this->settings))->enabled()) {
            throw new \RuntimeException('Online payment is not available at the moment.');
        }
        $address = trim((string)($p['address'] ?? ''));
        if ($orderType === 'delivery' && $address === '') {
            throw new \RuntimeException('Please give a delivery address.');
        }
        $notes = trim((string)($p['notes'] ?? ''));
        $when = trim((string)($p['pickup_time'] ?? ''));
        $wantReserve = $table && !empty($p['reserve_table']);
        $_SESSION['shop_table_reserve'] = $wantReserve ? 1 : 0;
        $preset = $this->reservePreset();
        $reserveFee = $wantReserve ? $preset['amount'] : 0.0;
        $tableNote = '';
        if ($table) {
            $tableNote = 'Table: ' . $table['zone_name'] . ' · ' . $table['table_name'] . '.';
            if ($wantReserve) {
                $tableNote .= ' ' . $preset['label'] . '.';
            }
        }
        $fullNotes = trim($tableNote . ' ' . ($when !== '' ? 'Time: ' . $when . '. ' : '') . ($orderType === 'delivery' ? 'Deliver to: ' . $address . '. ' : '') . $notes);

        $pdo->beginTransaction();
        try {
            $subtotal = 0.0;
            $lines = [];
            $check = $pdo->prepare(
                "SELECT p.id, p.name, p.price, c.type AS cat_type, COALESCE(i.quantity,0) AS on_hand,
                        (SELECT COUNT(*) FROM product_recipes r WHERE r.parent_product_id = p.id) AS is_recipe
                 FROM products p LEFT JOIN categories c ON c.id = p.category_id
                 LEFT JOIN inventory i ON i.product_id = p.id AND i.location_id = ?
                 WHERE p.id = ? AND p.is_active = 1"
            );
            foreach ($cart as $line) {
                $check->execute([$loc, (int)$line['id']]);
                $prod = $check->fetch();
                if (!$prod) {
                    throw new \RuntimeException($line['name'] . ' is no longer available.');
                }
                if (!$prod['is_recipe'] && (float)$prod['on_hand'] < (int)$line['qty']) {
                    throw new \RuntimeException('Sorry, ' . $prod['name'] . ' is out of stock.');
                }
                $price = (float)$prod['price'];
                $subtotal += $price * (int)$line['qty'];
                $lines[] = ['id' => (int)$prod['id'], 'qty' => (int)$line['qty'], 'price' => $price, 'cat_type' => (string)($prod['cat_type'] ?? '')];
            }
            $minOrder = (float)($this->settings['shop_min_order'] ?? 0);
            if ($minOrder > 0 && $subtotal < $minOrder) {
                throw new \RuntimeException('Minimum order is K' . number_format($minOrder, 2) . '.');
            }
            $deliveryFee = $orderType === 'delivery' ? (float)($this->settings['shop_delivery_fee'] ?? 0) : 0.0;
            $surcharge = round($deliveryFee + $reserveFee, 2);
            $reasons = [];
            if ($deliveryFee > 0) {
                $reasons[] = 'Delivery';
            }
            if ($reserveFee > 0) {
                $reasons[] = $preset['label'];
            }
            $total = round($subtotal + $surcharge, 2);

            $shift = $pdo->prepare("SELECT id FROM shifts WHERE location_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
            $shift->execute([$loc]);
            $shiftId = $shift->fetchColumn() ?: null;
            $sysUser = (int)($pdo->query("SELECT id FROM users WHERE role IN ('admin','dev') ORDER BY id LIMIT 1")->fetchColumn() ?: 1);
            $extId = 'WEB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

            $pdo->prepare(
                "INSERT INTO sales (location_id, table_id, user_id, shift_id, subtotal, total_amount, final_total, surcharge_amount, surcharge_reason, status, payment_status, payment_method,
                                    split_group_id, customer_name, customer_id, customer_phone, order_source, order_type, order_notes, member_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', 'Online', ?, ?, ?, ?, 'portal', ?, ?, ?, NOW())"
            )->execute([
                $loc, $table ? (int)$table['id'] : null, $sysUser, $shiftId, $subtotal, $subtotal, $total, $surcharge, $reasons ? implode(', ', $reasons) : null,
                $extId, 'ONLINE: ' . $customer['name'], (int)$customer['id'], $customer['phone'], $orderType, $fullNotes !== '' ? substr($fullNotes, 0, 500) : null,
                $customer['member_id'] ?: null,
            ]);
            $saleId = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, price_at_sale, status, fulfillment_status) VALUES (?, ?, ?, ?, ?, 'held', 'uncollected')");
            foreach ($lines as $l) {
                $ins->execute([$saleId, $l['id'], $l['qty'], $l['price'], $l['price']]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $_SESSION['shop_cart'] = [];
        try {
            $this->startOrderPayment($customer, $saleId, $total, $loc);
        } catch (\Throwable $e) {
            $this->flashShop('error', 'The payment window did not open. Order #' . $saleId . ' is saved. Open the order and try Pay now.');
            $this->go('order', ['id' => $saleId]);
        }
        $this->go('pay', ['id' => $saleId]);
    }

    /** @param array<string, mixed> $customer */
    private function payExistingOrder(array $customer, int $saleId): never
    {
        $order = Customer::order((int)$customer['id'], $saleId);
        if (!$order) {
            throw new \RuntimeException('Order not found.');
        }
        if (($order['payment_status'] ?? '') === 'paid') {
            $this->flashShop('success', 'This order is already paid.');
            $this->go('order', ['id' => $saleId]);
        }
        $this->go('pay', ['id' => $saleId]);
    }

    /** @param array<string, mixed> $customer @return array<string, mixed> */
    private function startOrderPayment(array $customer, int $saleId, float $total, int $loc): array
    {
        $flw = new Flutterwave($this->pdo(), $this->settings);
        if (($this->settings['shop_pay_online'] ?? '1') !== '1' || !$flw->enabled()) {
            throw new \RuntimeException('Online payment is not available at the moment.');
        }
        $title = (string)(($this->settings['shop_title'] ?? '') ?: ($this->settings['business_name'] ?? 'Order'));
        $args = [
            'amount' => $total,
            'email' => (string)($customer['email'] ?? ''),
            'phone' => $customer['phone'],
            'name' => $customer['name'],
            'redirect_url' => $this->baseUrl() . '/index.php?page=shop&view=pay_return&order=' . $saleId,
            'title' => $title,
            'description' => 'Order ' . $saleId,
            'sale_id' => $saleId,
            'location_id' => $loc,
            'customer_id' => (int)$customer['id'],
            'prefix' => 'WEB',
            'payment_options' => 'card,mobilemoneyzambia',
        ];
        $refStmt = $this->pdo()->prepare('SELECT gateway_ref FROM sales WHERE id = ?');
        $refStmt->execute([$saleId]);
        $existingRef = (string)($refStmt->fetchColumn() ?: '');
        $existing = $existingRef !== '' ? $flw->transaction($existingRef) : null;
        if (
            $existing
            && ($existing['status'] ?? '') === 'pending'
            && trim((string)($existing['checkout_url'] ?? '')) !== ''
            && abs((float)$existing['amount'] - $total) < 0.01
        ) {
            return $flw->inlinePayload($existing, $args);
        }
        $made = $flw->beginInline($args);
        $this->pdo()->prepare('UPDATE sales SET gateway_ref = ? WHERE id = ?')->execute([$made['tx_ref'], $saleId]);
        return $made;
    }

    /** Flutterwave sends the customer back here after paying. */
    private function payReturn(?array $customer): void
    {
        $saleId = (int)($_GET['order'] ?? 0);
        $txRef = (string)($_GET['tx_ref'] ?? $_GET['reference'] ?? '');
        $pdo = $this->pdo();
        $stmt = $pdo->prepare('SELECT id, final_total, gateway_ref, payment_status, customer_id FROM sales WHERE id = ?');
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch();
        if (!$sale) {
            $this->flashShop('error', 'Order not found.');
            $this->go('menu');
        }
        $txRef = $txRef !== '' ? $txRef : (string)$sale['gateway_ref'];
        $chargeId = trim((string)($_GET['charge_id'] ?? $_GET['transaction_id'] ?? ''));
        if ($txRef !== '' && str_starts_with($chargeId, 'chg_')) {
            (new Flutterwave($pdo, $this->settings))->rememberCharge($txRef, $chargeId);
        }
        $paid = ($sale['payment_status'] ?? '') === 'paid';
        if (!$paid) {
            $settled = $this->confirmShopPayment($saleId, $txRef);
            $paid = $settled === 'paid';
            if ($settled === 'pending') {
                $this->flashShop('warning', 'Payment is still pending with Flutterwave. You can try Pay now again.');
            } elseif ($settled === 'failed') {
                $this->flashShop('error', 'Payment was not completed. Open the order and try Pay now again.');
            } elseif ($settled !== 'paid') {
                $this->flashShop('error', 'Could not confirm the payment. Open the order and try Pay now again.');
            }
        }
        if ($customer && (int)$sale['customer_id'] === (int)$customer['id']) {
            $this->go($paid ? 'thanks' : 'order', ['id' => $saleId]);
        }
        $this->go('menu');
    }

    /** @param array<string, mixed>|null $customer @return array{payment_status: string} */
    private function shopPaymentStatus(?array $customer, int $saleId): array
    {
        if (!$customer || $saleId <= 0) {
            return ['payment_status' => 'unpaid'];
        }
        $order = Customer::order((int)$customer['id'], $saleId);
        if (!$order) {
            return ['payment_status' => 'unpaid'];
        }
        if (($order['payment_status'] ?? '') === 'paid') {
            return ['payment_status' => 'paid'];
        }
        $ref = trim((string)($order['gateway_ref'] ?? ''));
        if ($ref === '') {
            return ['payment_status' => 'unpaid'];
        }
        return ['payment_status' => $this->confirmShopPayment($saleId, $ref) === 'paid' ? 'paid' : 'pending'];
    }

    /** Ask Flutterwave whether this order is paid, and record it when it is. Does not fiscalize. */
    private function confirmShopPayment(int $saleId, string $txRef): string
    {
        if ($txRef === '') {
            return 'pending';
        }
        try {
            $flw = new Flutterwave($this->pdo(), $this->settings);
            $tx = $flw->verify($txRef);
            $status = (string)($tx['status'] ?? '');
            if ($status === 'successful') {
                $stmt = $this->pdo()->prepare('SELECT final_total, payment_status, status FROM sales WHERE id = ?');
                $stmt->execute([$saleId]);
                $sale = $stmt->fetch();
                if (!$sale) {
                    return 'pending';
                }
                if (($sale['status'] ?? '') !== 'pending') {
                    return (string)($sale['payment_status'] ?? 'pending');
                }
                if (($sale['payment_status'] ?? '') === 'paid') {
                    \App\Models\Sale::ensureOnlineReleased($this->pdo(), $saleId);
                    return 'paid';
                }
                if ((float)$tx['amount'] + 0.005 < (float)$sale['final_total']) {
                    return 'pending';
                }
                $flw->claimForSale($txRef, (float)$sale['final_total'], $saleId);
                if (!\App\Models\Sale::markOnlinePaid($this->pdo(), $saleId, $txRef)) {
                    \App\Models\Sale::ensureOnlineReleased($this->pdo(), $saleId);
                }
                return 'paid';
            }
            return $status === 'failed' ? 'failed' : 'pending';
        } catch (\Throwable) {
            return 'error';
        }
    }

    // ------------------------------------------------------------------ events

    /** @param array<string, mixed> $p */
    private function handleEventPost(array $p): never
    {
        if (isset($p['event_apply'])) {
            $event = Event::publicBySlug((string)($p['slug'] ?? ''));
            if (!$event) {
                throw new \RuntimeException('This event is not open.');
            }
            $copy = Event::signupCopy((string)($event['signup_mode'] ?? 'apply'));
            $tiers = Event::tiers((int)$event['id']);
            $tierId = (int)($p['tier_id'] ?? 0);
            if ($tiers && $tierId <= 0) {
                throw new \RuntimeException('Choose a payment tier.');
            }
            $guest = Event::addGuest(
                (int)$event['id'],
                (string)($p['name'] ?? ''),
                (string)($p['email'] ?? ''),
                (string)($p['phone'] ?? ''),
                (int)($p['party_size'] ?? 1),
                'applied',
                'applied',
                (string)($p['note'] ?? ''),
                $tierId
            );
            $this->flashShop('success', $copy['done']);
            $this->go('rsvp', ['token' => $guest['token']]);
        }

        $guest = Event::guestByToken((string)($p['token'] ?? ''));
        $event = $guest ? Event::find((int)$guest['event_id']) : null;
        if (!$guest || !$event) {
            throw new \RuntimeException('This invitation link is not valid.');
        }
        if (($event['status'] ?? '') === 'cancelled') {
            throw new \RuntimeException('This event was cancelled.');
        }

        if (isset($p['event_reply'])) {
            if (($event['status'] ?? '') !== 'open') {
                throw new \RuntimeException('Replies are closed for this event.');
            }
            $reply = (string)($p['reply'] ?? '');
            if (!in_array($reply, ['accepted', 'declined'], true)) {
                throw new \RuntimeException('Choose whether you can attend.');
            }
            Event::setGuestStatus((int)$event['id'], (int)$guest['id'], $reply);
            Event::updateGuestDetails((int)$event['id'], (int)$guest['id'], (int)($p['party_size'] ?? 1), (string)($p['note'] ?? ''));
            if ($reply === 'accepted') {
                Event::saveSelections((int)$guest['id'], (int)$event['id'], array_map('intval', (array)($p['option_id'] ?? [])));
                if (Event::tiers((int)$event['id'])) {
                    Event::assignTier((int)$event['id'], (int)$guest['id'], (int)($p['tier_id'] ?? 0));
                }
            }
            $this->flashShop('success', $reply === 'accepted' ? 'Your reply and choices are saved.' : 'You are marked as unable to attend.');
            $this->go('rsvp', ['token' => $guest['token']]);
        }

        $charge = Event::nextCharge($event, (int)$guest['id']);
        if ($charge['amount'] <= 0) {
            throw new \RuntimeException('Nothing is due on this invitation.');
        }
        $flw = new Flutterwave($this->pdo(), $this->settings);
        if (!$flw->enabled()) {
            throw new \RuntimeException('Online payment is not available at the moment.');
        }
        $made = $flw->createPaymentLink([
            'amount' => $charge['amount'],
            'email' => (string)($guest['email'] ?? ''),
            'phone' => (string)($guest['phone'] ?? ''),
            'name' => (string)$guest['name'],
            'redirect_url' => $this->baseUrl() . '/index.php?page=shop&view=rsvp&token=' . rawurlencode((string)$guest['token']),
            'title' => (string)$event['title'],
            'description' => ucfirst((string)$charge['purpose']) . ' for ' . $event['title'],
            'prefix' => 'EVT',
            'location_id' => (int)($event['location_id'] ?? 0),
        ]);
        $paymentId = Event::openOnlinePayment((int)$event['id'], (int)$guest['id'], (float)$charge['amount'], (string)$charge['purpose']);
        Event::attachTx($paymentId, (string)$made['tx_ref']);
        header('Location: ' . $made['link']);
        exit;
    }

    /** @param array<string, mixed> $guest */
    private function refreshEventPayments(array $guest): void
    {
        $flw = new Flutterwave($this->pdo(), $this->settings);
        if (!$flw->enabled()) {
            return;
        }
        foreach (Event::paymentsForGuest((int)$guest['id']) as $payment) {
            $ref = (string)($payment['tx_ref'] ?? '');
            if (($payment['status'] ?? '') !== 'pending' || !str_starts_with($ref, 'EVT-')) {
                continue;
            }
            try {
                $row = $flw->verify($ref);
                Event::settleTx($ref, (string)($row['status'] ?? ''));
            } catch (\Throwable) {
                // Still pending or the gateway has not confirmed it yet.
            }
        }
    }

    // ------------------------------------------------------------------ helpers

    private function saveVisit(array $p, ?array $customer): never
    {
        $ctx = $this->visitContext($customer, $p);
        if ($ctx['problem'] !== '') {
            $this->flashShop('error', $ctx['problem']);
            $this->go('visit', $ctx['query']);
        }
        if (!$ctx['location']) {
            $this->flashShop('error', 'Choose a store first.');
            $this->go('visit');
        }
        if ($ctx['answered']) {
            $this->go('visit', $ctx['query']);
        }
        if (($p['visit_mood'] ?? '') === 'off' && !isset($p['visit_save'])) {
            $this->go('visit', $ctx['query'] + ['step' => 'off']);
        }
        $mood = isset($p['visit_save']) ? 'off' : 'glad';
        try {
            GuestNote::record(
                (int)$ctx['location']['id'],
                $mood,
                (string)($p['note'] ?? ''),
                (string)($p['phone'] ?? ''),
                $ctx['sale'] ? 'shop' : 'table',
                $ctx['sale'] ? (int)$ctx['sale']['id'] : null,
                $ctx['table'] ? (int)$ctx['table']['id'] : null,
                $customer ? (int)$customer['id'] : null
            );
        } catch (\RuntimeException $e) {
            $this->flashShop('error', $e->getMessage());
            $this->go('visit', $ctx['query'] + ($mood === 'off' ? ['step' => 'off'] : []));
        }
        if (!$ctx['sale']) {
            $_SESSION['shop_visit_done'] = 1;
        }
        $_SESSION['shop_visit_mood'] = $mood;
        if ($mood === 'glad' && $ctx['google'] !== '') {
            header('Location: ' . $ctx['google']);
            exit;
        }
        $this->flashShop('success', $mood === 'glad' ? 'Thank you. Glad you came.' : 'The team has your note.');
        $this->go('visit', $ctx['query']);
    }

    /** @param array<string, mixed> $src
     *  @return array{location: ?array, sale: ?array, table: ?array, google: string, answered: bool, mood: string, problem: string, query: array<string, int|string>, stores: array}
     */
    private function visitContext(?array $customer, array $src = []): array
    {
        $saleId = (int)($src['sale'] ?? $_GET['sale'] ?? 0);
        $token = trim((string)($src['token'] ?? $_GET['token'] ?? ''));
        $locId = (int)($src['loc'] ?? $_GET['loc'] ?? 0);
        $sale = null;
        $problem = '';
        if ($saleId > 0) {
            $look = $this->saleForVisit($saleId, $customer, $token);
            $sale = $look['sale'];
            $problem = $look['problem'];
            if ($sale) {
                $locId = (int)$sale['location_id'];
            }
        }
        if ($locId <= 0) {
            $locId = $this->shopLocationId();
        }
        $location = $locId > 0 ? Location::find($locId) : null;
        if ($location && $problem === '' && $saleId > 0 && !$sale) {
            $location = null;
        }
        $table = null;
        if ($sale && !empty($sale['table_id'])) {
            $found = RestaurantTable::find((int)$sale['table_id']);
            if ($found && (int)$found['location_id'] === (int)($location['id'] ?? 0)) {
                $table = $found;
            }
        } elseif ($location) {
            $sessionTable = $this->shopTable();
            if ($sessionTable && (int)$sessionTable['location_id'] === (int)$location['id']) {
                $table = $sessionTable;
            }
        }
        $existing = $sale ? GuestNote::forSale((int)$sale['id']) : null;
        $answered = $existing !== null || (!$sale && $problem === '' && !empty($_SESSION['shop_visit_done']));
        $mood = '';
        if ($existing) {
            $mood = (string)$existing['mood'];
        } elseif ($answered) {
            $mood = (string)($_SESSION['shop_visit_mood'] ?? '');
        }
        $google = '';
        if ($location) {
            try {
                $google = GuestNote::reviewUrl((string)($location['google_review_url'] ?? ''));
            } catch (\Throwable) {
                $google = '';
            }
        }
        $query = [];
        if ($sale) {
            $query['sale'] = (int)$sale['id'];
            if ($token !== '') {
                $query['token'] = $token;
            }
        } elseif ($location && $problem === '') {
            $query['loc'] = (int)$location['id'];
        }
        return [
            'location' => $problem === '' ? $location : null,
            'sale' => $sale,
            'table' => $table,
            'google' => $google,
            'answered' => $answered,
            'mood' => $mood,
            'problem' => $problem,
            'query' => $query,
            'stores' => Location::sellable(),
        ];
    }

    /** @return array{sale: ?array, problem: string} */
    private function saleForVisit(int $id, ?array $customer, string $token): array
    {
        $stmt = $this->pdo()->prepare(
            'SELECT id, location_id, customer_id, table_id, payment_status, status, visit_token FROM sales WHERE id = ?'
        );
        $stmt->execute([$id]);
        $sale = $stmt->fetch();
        if (!$sale) {
            return ['sale' => null, 'problem' => 'Open this from your finished order, or from the link we emailed.'];
        }
        if (($sale['payment_status'] ?? '') !== 'paid' || ($sale['status'] ?? '') === 'pending') {
            return ['sale' => null, 'problem' => 'This order is not finished yet.'];
        }
        $owns = $customer && (int)$customer['id'] === (int)$sale['customer_id'];
        $stored = (string)($sale['visit_token'] ?? '');
        $tokenOk = $token !== '' && $stored !== '' && strlen($stored) === strlen($token) && hash_equals($stored, $token);
        if (!$owns && !$tokenOk) {
            return ['sale' => null, 'problem' => 'Open this from your finished order, or from the link we emailed.'];
        }
        return ['sale' => $sale, 'problem' => ''];
    }

    private function afterLogin(): string
    {
        $v = (string)($_SESSION['shop_after_login'] ?? 'menu');
        unset($_SESSION['shop_after_login']);
        return in_array($v, ['checkout', 'orders', 'account', 'cart', 'thanks', 'reserve'], true) ? $v : 'menu';
    }

    private function baseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
    }

    /** @return array{enabled: bool, rates: array<string, array<string, mixed>>} */
    private function shopRates(): array
    {
        try {
            $fx = new Currency($this->pdo(), $this->settings);
            if (!$fx->enabled()) {
                return ['enabled' => false, 'rates' => []];
            }
            return ['enabled' => true] + $fx->rates();
        } catch (\Throwable) {
            return ['enabled' => false, 'rates' => []];
        }
    }

    private function flashShop(string $type, string $msg): void
    {
        $_SESSION['shop_flash'] = ['type' => $type, 'msg' => $msg];
    }

    private function takeFlash(): ?array
    {
        $f = $_SESSION['shop_flash'] ?? null;
        unset($_SESSION['shop_flash']);
        return is_array($f) ? $f : null;
    }

    private function go(string $view, array $extra = []): never
    {
        $this->redirect('shop', ['view' => $view] + $extra);
    }
}
