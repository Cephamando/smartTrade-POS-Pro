<?php

namespace App\Controllers;

use App\Models\Customer;
use App\Models\Event;
use App\Models\Location;
use App\Models\Setting;
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
        $eventView = in_array($view, ['events', 'event', 'rsvp'], true);
        if (!$eventView && ($this->settings['shop_enabled'] ?? '0') !== '1' && !$this->isManager()) {
            $this->render(['view' => 'closed', 'settings' => $this->settings, 'customer' => null, 'cart' => [], 'cartTotal' => 0, 'flash' => null]);
            return;
        }
        $this->applyShopLocation();
        $view = (string)($_GET['view'] ?? 'home');
        $customer = !empty($_SESSION['shop_customer_id']) ? Customer::find((int)$_SESSION['shop_customer_id']) : null;

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handlePost($customer);
        }
        if ($view === 'pay_return') {
            $this->payReturn($customer);
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
            'flwOn' => ($this->settings['shop_pay_online'] ?? '1') === '1' && (new Flutterwave($this->pdo(), $this->settings))->enabled(),
            'counterOn' => ($this->settings['shop_pay_at_counter'] ?? '1') === '1',
            'deliveryOn' => ($this->settings['shop_delivery'] ?? '1') === '1',
            'deliveryFee' => (float)($this->settings['shop_delivery_fee'] ?? 0),
            'minOrder' => (float)($this->settings['shop_min_order'] ?? 0),
            'shopLocations' => $this->shopLocations,
            'shopLocationId' => $this->shopLocationId(),
        ];
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
        } elseif ($view === 'order' && $customer) {
            $data['order'] = Customer::order((int)$customer['id'], (int)($_GET['id'] ?? 0));
            if (!$data['order']) {
                $this->go('orders');
            }
        } elseif (in_array($view, ['orders', 'order', 'account', 'checkout'], true) && !$customer) {
            $_SESSION['shop_after_login'] = $view;
            $this->go('login');
        }
        $this->render($data);
    }

    // ------------------------------------------------------------------ POST actions

    private function handlePost(?array $customer): void
    {
        $p = $_POST;
        try {
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
            }
            $this->go($view === 'pay_return' ? 'orders' : $view, $extra);
        }
    }

    // ------------------------------------------------------------------ catalogue & cart

    private function applyShopLocation(): void
    {
        $this->shopLocations = Location::sellable();
        $allowed = array_map('intval', array_column($this->shopLocations, 'id'));
        $pick = (int)($_GET['loc'] ?? 0);
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

    private function placeOrder(array $customer, array $p): void
    {
        $cart = $this->cart();
        if (!$cart) {
            throw new \RuntimeException('Your basket is empty.');
        }
        $pdo = $this->pdo();
        $loc = $this->shopLocationId();
        $orderType = ($p['order_type'] ?? 'pickup') === 'delivery' && ($this->settings['shop_delivery'] ?? '1') === '1' ? 'delivery' : 'pickup';
        $payMode = ($p['pay_mode'] ?? 'counter') === 'online' ? 'online' : 'counter';
        if ($payMode === 'online' && (($this->settings['shop_pay_online'] ?? '1') !== '1' || !(new Flutterwave($pdo, $this->settings))->enabled())) {
            throw new \RuntimeException('Online payment is not available at the moment. Choose pay at counter.');
        }
        if ($payMode === 'counter' && ($this->settings['shop_pay_at_counter'] ?? '1') !== '1') {
            throw new \RuntimeException('Please pay online to confirm your order.');
        }
        $address = trim((string)($p['address'] ?? ''));
        if ($orderType === 'delivery' && $address === '') {
            throw new \RuntimeException('Please give a delivery address.');
        }
        $notes = trim((string)($p['notes'] ?? ''));
        $when = trim((string)($p['pickup_time'] ?? ''));
        $fullNotes = trim(($when !== '' ? 'Time: ' . $when . '. ' : '') . ($orderType === 'delivery' ? 'Deliver to: ' . $address . '. ' : '') . $notes);

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
            $total = round($subtotal + $deliveryFee, 2);

            $shift = $pdo->prepare("SELECT id FROM shifts WHERE location_id = ? AND status = 'open' ORDER BY id DESC LIMIT 1");
            $shift->execute([$loc]);
            $shiftId = $shift->fetchColumn() ?: null;
            $sysUser = (int)($pdo->query("SELECT id FROM users WHERE role IN ('admin','dev') ORDER BY id LIMIT 1")->fetchColumn() ?: 1);
            $extId = 'WEB-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));

            $pdo->prepare(
                "INSERT INTO sales (location_id, user_id, shift_id, subtotal, total_amount, final_total, surcharge_amount, surcharge_reason, status, payment_status, payment_method,
                                    split_group_id, customer_name, customer_id, customer_phone, order_source, order_type, order_notes, member_id, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', 'Online', ?, ?, ?, ?, 'portal', ?, ?, ?, NOW())"
            )->execute([
                $loc, $sysUser, $shiftId, $subtotal, $subtotal, $total, $deliveryFee, $deliveryFee > 0 ? 'Delivery' : null,
                $extId, 'ONLINE: ' . $customer['name'], (int)$customer['id'], $customer['phone'], $orderType, $fullNotes !== '' ? substr($fullNotes, 0, 500) : null,
                $customer['member_id'] ?: null,
            ]);
            $saleId = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare("INSERT INTO sale_items (sale_id, product_id, quantity, price, price_at_sale, status, fulfillment_status) VALUES (?, ?, ?, ?, ?, ?, 'uncollected')");
            foreach ($lines as $l) {
                $ct = strtolower($l['cat_type']);
                $kds = (str_contains($ct, 'food') || str_contains($ct, 'meal') || str_contains($ct, 'kitchen')) ? 'pending' : 'ready';
                $ins->execute([$saleId, $l['id'], $l['qty'], $l['price'], $l['price'], $kds]);
                deductStock($pdo, $l['id'], $l['qty'], $loc, $sysUser, 'online_sale');
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        $_SESSION['shop_cart'] = [];

        if ($payMode === 'online') {
            $flw = new Flutterwave($pdo, $this->settings);
            $res = $flw->createPaymentLink([
                'amount' => $total,
                'email' => (string)($customer['email'] ?? ''),
                'phone' => $customer['phone'],
                'name' => $customer['name'],
                'redirect_url' => $this->baseUrl() . '/index.php?page=shop&view=pay_return&order=' . $saleId,
                'title' => (string)(($this->settings['shop_title'] ?? '') ?: ($this->settings['business_name'] ?? 'Order')),
                'description' => 'Order #' . $saleId,
                'sale_id' => $saleId,
                'location_id' => $loc,
                'customer_id' => (int)$customer['id'],
                'prefix' => 'WEB',
            ]);
            $pdo->prepare('UPDATE sales SET gateway_ref = ? WHERE id = ?')->execute([$res['tx_ref'], $saleId]);
            header('Location: ' . $res['link']);
            exit;
        }
        $this->flashShop('success', 'Order #' . $saleId . ' placed! Pay at the counter when you collect.');
        $this->go('order', ['id' => $saleId]);
    }

    /** Flutterwave sends the customer back here after paying. */
    private function payReturn(?array $customer): void
    {
        $saleId = (int)($_GET['order'] ?? 0);
        $txRef = (string)($_GET['tx_ref'] ?? '');
        $pdo = $this->pdo();
        $stmt = $pdo->prepare('SELECT id, final_total, gateway_ref, payment_status, customer_id FROM sales WHERE id = ?');
        $stmt->execute([$saleId]);
        $sale = $stmt->fetch();
        if (!$sale) {
            $this->flashShop('error', 'Order not found.');
            $this->go('menu');
        }
        $txRef = $txRef !== '' ? $txRef : (string)$sale['gateway_ref'];
        if ($sale['payment_status'] !== 'paid') {
            try {
                $flw = new Flutterwave($pdo, $this->settings);
                $tx = $flw->verify($txRef);
                if ($tx['status'] === 'successful' && (float)$tx['amount'] + 0.005 >= (float)$sale['final_total']) {
                    $flw->claimForSale($txRef, (float)$sale['final_total'], $saleId);
                    $pdo->prepare("UPDATE sales SET payment_status = 'paid', amount_tendered = final_total, change_due = 0, gateway_ref = ? WHERE id = ?")->execute([$txRef, $saleId]);
                    $this->flashShop('success', 'Payment received. Your order #' . $saleId . ' is being prepared.');
                } elseif ($tx['status'] === 'pending') {
                    $this->flashShop('warning', 'Payment is still pending with Flutterwave. Refresh in a moment or pay at the counter.');
                } else {
                    $this->flashShop('error', 'Payment was not completed (' . $tx['status'] . '). You can pay at the counter instead.');
                }
            } catch (\Throwable $e) {
                $this->flashShop('error', 'Could not confirm the payment: ' . $e->getMessage());
            }
        }
        if ($customer && (int)$sale['customer_id'] === (int)$customer['id']) {
            $this->go('order', ['id' => $saleId]);
        }
        $this->go('menu');
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

    private function afterLogin(): string
    {
        $v = (string)($_SESSION['shop_after_login'] ?? 'menu');
        unset($_SESSION['shop_after_login']);
        return in_array($v, ['checkout', 'orders', 'account', 'cart'], true) ? $v : 'menu';
    }

    private function baseUrl(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $dir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\');
        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
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
