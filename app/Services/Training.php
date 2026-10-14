<?php

namespace App\Services;

use App\Core\Database;
use PDO;

final class Training
{
    public function catalog(string $role): array
    {
        $role = strtolower($role);
        $list = [];
        foreach ($this->courseDefinitions() as $key => $course) {
            if (!in_array($role, $course['roles'], true)) {
                continue;
            }
            $steps = $this->stepsFor($course, $role);
            $list[$key] = [
                'title' => $course['title'],
                'summary' => $course['summary'],
                'audience' => $course['audience'],
                'steps' => array_map(static fn(array $step): string => $step['title'], $steps),
            ];
        }
        return $list;
    }

    public function start(int $userId, int $locationId, string $courseKey, string $role): array
    {
        if ($userId <= 0) {
            throw new \RuntimeException('Sign in before starting training.');
        }
        $role = strtolower($role);
        $courseKey = strtolower(preg_replace('/[^a-z_]/', '', $courseKey) ?? '');
        $courses = $this->courseDefinitions();
        $course = $courses[$courseKey] ?? null;
        if (!$course || !in_array($role, $course['roles'], true)) {
            throw new \RuntimeException('Choose a training that matches your role.');
        }
        $steps = $this->stepsFor($course, $role);
        if ($steps === []) {
            throw new \RuntimeException('That training has no steps for your role.');
        }

        $this->releaseIfAbandoned();
        $lock = $this->lock();
        $sessionId = session_id();
        if ($lock['user'] > 0 && $lock['session'] !== '' && $lock['session'] !== $sessionId && !$this->stale($lock['at'])) {
            throw new \RuntimeException('Someone else is already using Training Mode.');
        }
        $this->clearPracticeFlag();
        if ($lock['user'] > 0) {
            $this->destroySandbox();
        }

        $prod = Database::production();
        $box = Database::sandbox();
        $this->cloneSchema($prod, $box);
        $locationId = $this->seedLocation($prod, $box, $locationId);
        $storeId = $this->seedStore($box);
        $this->seedUser($prod, $box, $userId, $locationId);
        $this->seedPracticeManager($box, $locationId);
        $this->seedSettings($prod, $box);
        $this->seedSurcharge($box);
        $ids = $this->seedCatalog($box, $locationId, $storeId);
        $shiftId = $this->seedShift($box, $userId, $locationId);
        $tableId = $this->seedTable($box, $locationId);
        if ($courseKey === 'kitchen') {
            $this->seedKitchenTicket($box, $locationId, $userId, $shiftId, $tableId, (int)$ids['cake']);
        }
        $this->writeLock($userId, $sessionId);

        $_SESSION['training_mode'] = 1;
        $_SESSION['training_course'] = $courseKey;
        $_SESSION['training_step'] = 0;
        $_SESSION['training_after_id'] = 0;
        $_SESSION['training_started'] = date('Y-m-d H:i:s');
        $_SESSION['training_marks'] = [
            'locations' => $this->countSql('SELECT COUNT(*) FROM locations'),
        ];
        $_SESSION['location_id'] = $locationId;
        $_SESSION['pos_location_id'] = $locationId;
        $name = $box->prepare('SELECT name FROM locations WHERE id = ?');
        $name->execute([$locationId]);
        $_SESSION['location_name'] = $name->fetchColumn() ?: 'Training';
        unset($_SESSION['training_msg'], $_SESSION['training_fp']);

        $first = $steps[0];
        return [
            'page' => $first['page'],
            'query' => $first['query'] ?? [],
            'title' => $course['title'],
        ];
    }

    public function end(): void
    {
        $lock = $this->lock();
        $owns = !empty($_SESSION['training_mode'])
            || $lock['session'] === session_id()
            || ($lock['user'] > 0 && $lock['user'] === (int)($_SESSION['user_id'] ?? 0));
        $this->clearPracticeFlag();
        if ($owns) {
            $this->destroySandbox();
            $this->clearLock();
        }
    }

    private function clearPracticeFlag(): void
    {
        unset(
            $_SESSION['training_mode'],
            $_SESSION['training_course'],
            $_SESSION['training_step'],
            $_SESSION['training_after_id'],
            $_SESSION['training_started'],
            $_SESSION['training_marks'],
            $_SESSION['training_msg'],
            $_SESSION['training_fp']
        );
    }

    public function releaseIfAbandoned(): void
    {
        if (!empty($_SESSION['training_mode'])) {
            return;
        }
        $lock = $this->lock();
        if ($lock['user'] <= 0 || !$this->stale($lock['at'])) {
            return;
        }
        $this->destroySandbox();
        $this->clearLock();
    }

    public function pulse(): array
    {
        if (empty($_SESSION['training_mode'])) {
            return ['active' => false, 'message' => ''];
        }
        if (empty($_SESSION['training_course'])) {
            $_SESSION['training_course'] = 'cashier';
        }
        $course = $this->courseDefinitions()[(string)$_SESSION['training_course']] ?? null;
        if (!$course) {
            return ['active' => false, 'message' => ''];
        }
        $role = strtolower((string)($_SESSION['role'] ?? ''));
        $steps = $this->stepsFor($course, $role);
        $step = (int)($_SESSION['training_step'] ?? 0);
        if ($step >= count($steps)) {
            return $this->finished($course['title']);
        }
        $state = $this->snapshot();
        $saleId = 0;
        $passed = $this->passed($steps[$step], $state, $saleId);
        $justPassed = false;
        if ($passed) {
            $justPassed = true;
            if ($saleId > 0) {
                $_SESSION['training_after_id'] = $saleId;
            }
            $step++;
            $_SESSION['training_step'] = $step;
            unset($_SESSION['training_fp'], $_SESSION['training_msg']);
            if ($step >= count($steps)) {
                return $this->finished($course['title']);
            }
            $state = $this->snapshot();
        }
        $current = $steps[$step];
        $fp = $step . '|v3|' . json_encode($state);
        if (($_SESSION['training_fp'] ?? '') === $fp && isset($_SESSION['training_msg'])) {
            $message = (string)$_SESSION['training_msg'];
        } else {
            $message = $this->coach($course, $current, $state, false);
            $_SESSION['training_fp'] = $fp;
            $_SESSION['training_msg'] = $message;
        }
        if ($justPassed) {
            $message = 'That step is recorded. ' . $message;
        }
        return [
            'active' => true,
            'done' => false,
            'course' => $course['title'],
            'step' => $step + 1,
            'of' => count($steps),
            'title' => $current['title'],
            'message' => $message,
            'page' => $current['page'],
            'query' => $current['query'] ?? [],
        ];
    }

    private function finished(string $title): array
    {
        return [
            'active' => true,
            'done' => true,
            'course' => $title,
            'title' => 'Training complete',
            'message' => 'You finished ' . $title . '. Choose End Training to clear the practice data.',
        ];
    }

    private function courseDefinitions(): array
    {
        $auth = 'When a manager password is required, use train_mgr and the password train. A manager, administrator, or developer can use their own password instead.';
        return [
            'cashier' => [
                'title' => 'Cashier and bartender',
                'audience' => 'Cashiers and bartenders',
                'summary' => 'Ring cash, another tender, a tip, a surcharge, and a discount, then log a payout and a return.',
                'roles' => ['cashier', 'bartender', 'manager', 'admin', 'dev'],
                'steps' => [
                    ['title' => 'Ring a cash sale', 'page' => 'pos', 'check' => 'cash', 'ask' => 'On the Point of Sale, sell Training Cake, take cash, and complete the sale. Do not add a discount, tip, or surcharge.', 'rule' => 'A completed cash sale with no discount.'],
                    ['title' => 'Take another tender', 'page' => 'pos', 'check' => 'tender', 'ask' => 'Ring a new sale and take card, mobile money, or a split payment.', 'rule' => 'A completed sale paid by something other than cash alone.'],
                    ['title' => 'Add a tip', 'page' => 'pos', 'check' => 'tip', 'ask' => 'Ring another sale and add a tip before you complete it, or use the Tip button on a paid receipt.', 'rule' => 'A tip on a sale or a tip recorded after payment.'],
                    ['title' => 'Add a surcharge', 'page' => 'pos', 'check' => 'surcharge', 'ask' => 'Ring another sale, choose the surcharge Takeaway packaging, and complete it.', 'rule' => 'A completed sale with a surcharge.'],
                    ['title' => 'Apply a discount', 'page' => 'pos', 'check' => 'discount', 'ask' => 'Sell Training Juice, switch on 10% OFF, and complete the sale.', 'rule' => 'A completed sale whose subtotal is below the line total.'],
                    ['title' => 'Log a payout', 'page' => 'pos', 'check' => 'payout', 'ask' => 'Open Payout, enter an amount and a reason, and authorize it. ' . $auth, 'rule' => 'An expense row exists.'],
                    ['title' => 'Return an item', 'page' => 'pos', 'check' => 'refund', 'ask' => 'Switch on Refund Mode, choose the juice receipt, and issue the refund. ' . $auth, 'rule' => 'A refund or a returned sale item.'],
                ],
            ],
            'waiter' => [
                'title' => 'Waiter',
                'audience' => 'Waiters',
                'summary' => 'Open a table tab, send food to the kitchen, and settle the tab.',
                'roles' => ['waiter', 'manager', 'admin', 'dev'],
                'steps' => [
                    ['title' => 'Open a table tab', 'page' => 'pos', 'check' => 'tab', 'ask' => 'Add Training Cake, choose Add to Tab, start a new tab, and assign table Training 1.', 'rule' => 'A pending sale on a table.'],
                    ['title' => 'Settle the tab', 'page' => 'pos', 'check' => 'settle', 'ask' => 'Open the Training 1 tab and take payment until it is paid.', 'rule' => 'A paid sale that still belongs to a table.'],
                ],
            ],
            'kitchen' => [
                'title' => 'Kitchen',
                'audience' => 'Chefs and head chefs',
                'summary' => 'Work a live ticket. Head chefs also save a recipe and produce a batch.',
                'roles' => ['chef', 'head_chef', 'manager', 'admin', 'dev'],
                'steps' => [
                    ['title' => 'Start the ticket', 'page' => 'kds', 'check' => 'cook', 'ask' => 'On the Kitchen Display, press Cook on the Training Cake ticket.', 'rule' => 'A kitchen item is cooking or ready.'],
                    ['title' => 'Mark it ready', 'page' => 'kds', 'check' => 'ready', 'ask' => 'Press Done on that ticket so it is ready for pickup.', 'rule' => 'A kitchen item is ready.'],
                    ['title' => 'Save a recipe', 'page' => 'menu', 'check' => 'recipe', 'when' => 'lead', 'ask' => 'On Menu Builder, open Training Cake and save a recipe that uses 1 Training Flour.', 'rule' => 'A recipe row exists.'],
                    ['title' => 'Produce a batch', 'page' => 'kitchen', 'check' => 'produce', 'when' => 'lead', 'ask' => 'On Meal Production, produce 1 Training Cake and send the batch to your station.', 'rule' => 'A production log exists.'],
                ],
            ],
            'stock' => [
                'title' => 'Stock and purchasing',
                'audience' => 'Managers and administrators',
                'summary' => 'Add a vendor and a product, receive goods, and transfer stock to Training Store.',
                'roles' => ['manager', 'admin', 'dev'],
                'steps' => [
                    ['title' => 'Add a vendor', 'page' => 'vendors', 'check' => 'vendor', 'ask' => 'Open Vendors and add a practice supplier.', 'rule' => 'A vendor row exists.'],
                    ['title' => 'Add a product', 'page' => 'products', 'check' => 'product', 'ask' => 'Open Products and add a new item. Training Cake, Training Juice, and Training Flour do not count.', 'rule' => 'A product outside the practice catalog exists.'],
                    ['title' => 'Receive goods', 'page' => 'purchasing', 'query' => ['tab' => 'received'], 'check' => 'grv', 'ask' => 'Open Goods Received and receive Training Juice from the vendor you added.', 'rule' => 'A goods-received note exists.'],
                    ['title' => 'Transfer stock', 'page' => 'transfers', 'check' => 'transfer', 'ask' => 'Open Transfer Stock and send Training Juice from your station to Training Store.', 'rule' => 'A stock transfer exists.'],
                ],
            ],
            'people' => [
                'title' => 'People and administration',
                'audience' => 'Managers and administrators',
                'summary' => 'Add a table, a member, a staff login, an event, and a payroll employee.',
                'roles' => ['manager', 'admin', 'dev'],
                'steps' => [
                    ['title' => 'Add a table', 'page' => 'tables', 'check' => 'table', 'ask' => 'Open Table Settings and add a table. Training 1 is already there and does not count.', 'rule' => 'A table other than Training 1 exists.'],
                    ['title' => 'Add a member', 'page' => 'members', 'check' => 'member', 'ask' => 'Open Members and register a practice member.', 'rule' => 'A member row exists.'],
                    ['title' => 'Add a staff login', 'page' => 'users', 'check' => 'staff', 'ask' => 'Open Users and Staff and add a cashier. Your own login and train_mgr do not count.', 'rule' => 'A new user row exists.'],
                    ['title' => 'Create an event', 'page' => 'events', 'check' => 'event', 'ask' => 'Open Events and save a practice event.', 'rule' => 'An event row exists.'],
                    ['title' => 'Add a payroll employee', 'page' => 'payroll', 'query' => ['tab' => 'employees'], 'check' => 'employee', 'ask' => 'Open Payroll, Employees, and save a practice employee.', 'rule' => 'An employee row exists.'],
                ],
            ],
            'developer' => [
                'title' => 'Developer',
                'audience' => 'Developers',
                'summary' => 'Add a location, then ring and return a sale so you can read the sample Smart Invoice.',
                'roles' => ['dev'],
                'steps' => [
                    ['title' => 'Add a location', 'page' => 'locations', 'check' => 'location', 'ask' => 'Open Locations and add a practice station. Your current station and Training Store do not count.', 'rule' => 'A location beyond the seeded stations exists.'],
                    ['title' => 'Ring a sample invoice', 'page' => 'pos', 'check' => 'cash', 'ask' => 'On the Point of Sale, sell Training Cake for cash. The receipt is a practice Smart Invoice and is not filed with ZRA.', 'rule' => 'A completed cash sale.'],
                    ['title' => 'Issue a sample credit note', 'page' => 'pos', 'check' => 'refund', 'ask' => 'Refund that cake. The credit note is a sample and is not filed with ZRA. ' . $auth, 'rule' => 'A refund exists.'],
                ],
            ],
        ];
    }

    private function stepsFor(array $course, string $role): array
    {
        $lead = in_array($role, ['head_chef', 'admin', 'manager', 'dev'], true);
        $steps = [];
        foreach ($course['steps'] as $step) {
            if (($step['when'] ?? 'any') === 'lead' && !$lead) {
                continue;
            }
            $steps[] = $step;
        }
        return $steps;
    }

    private function snapshot(): array
    {
        $after = (int)($_SESSION['training_after_id'] ?? 0);
        $username = (string)($_SESSION['username'] ?? '');
        return [
            'sales' => $this->salesAfter($after),
            'expenses' => $this->countSql('SELECT COUNT(*) FROM expenses'),
            'refunds' => $this->countSql('SELECT COUNT(*) FROM refunds'),
            'voids' => $this->countSql('SELECT COUNT(*) FROM sale_items WHERE COALESCE(is_refund, 0) = 1'),
            'refunded_sales' => $this->countSql("SELECT COUNT(*) FROM sales WHERE status = 'refunded'"),
            'pending_tabs' => $this->countSql("SELECT COUNT(*) FROM sales WHERE payment_status = 'pending' AND table_id IS NOT NULL"),
            'uncollected' => $this->countSql("SELECT COUNT(*) FROM sale_items WHERE fulfillment_status = 'uncollected' AND status IN ('pending', 'cooking', 'ready')"),
            'cooking' => $this->countSql("SELECT COUNT(*) FROM sale_items WHERE status IN ('cooking', 'ready')"),
            'ready' => $this->countSql("SELECT COUNT(*) FROM sale_items WHERE status = 'ready'"),
            'settled_tabs' => $this->countSql("SELECT COUNT(*) FROM sales WHERE table_id IS NOT NULL AND payment_status IN ('paid', 'completed')"),
            'vendors' => $this->countSql('SELECT COUNT(*) FROM vendors'),
            'new_products' => $this->countSql("SELECT COUNT(*) FROM products WHERE sku NOT IN ('TRAIN-CAKE', 'TRAIN-JUICE', 'TRAIN-FLOUR')"),
            'grvs' => $this->countSql('SELECT COUNT(*) FROM grvs'),
            'transfers' => $this->countSql('SELECT COUNT(*) FROM inventory_transfers'),
            'tables' => $this->countSql("SELECT COUNT(*) FROM restaurant_tables WHERE table_name <> 'Training 1'"),
            'members' => $this->countSql('SELECT COUNT(*) FROM members'),
            'staff' => $this->countSql('SELECT COUNT(*) FROM users WHERE username NOT IN (?, ?)', [$username, 'train_mgr']),
            'recipes' => $this->countSql('SELECT COUNT(*) FROM product_recipes'),
            'produced' => $this->countSql("SELECT COUNT(*) FROM inventory_logs WHERE action_type = 'produce'"),
            'events' => $this->countSql('SELECT COUNT(*) FROM events'),
            'employees' => $this->countSql('SELECT COUNT(*) FROM employees'),
            'sale_tips' => $this->countSql('SELECT COUNT(*) FROM sale_tips'),
            'locations' => $this->countSql('SELECT COUNT(*) FROM locations'),
            'location_mark' => (int)(($_SESSION['training_marks']['locations'] ?? 0)),
        ];
    }

    private function passed(array $step, array $state, int &$saleId): bool
    {
        $saleId = 0;
        $check = (string)$step['check'];
        if ($check === 'cash') {
            $saleId = $this->matchSale($state['sales'], fn(array $s): bool => $this->paid($s) && !$this->discounted($s) && strtolower((string)$s['payment_method']) === 'cash' && (float)$s['line_total'] > 0 && (float)$s['tip_amount'] <= 0 && (float)$s['surcharge_amount'] <= 0);
            return $saleId > 0;
        }
        if ($check === 'tender') {
            $saleId = $this->matchSale($state['sales'], fn(array $s): bool => $this->paid($s) && (float)$s['line_total'] > 0 && $this->otherTender($s));
            return $saleId > 0;
        }
        if ($check === 'tip') {
            $saleId = $this->matchSale($state['sales'], fn(array $s): bool => $this->paid($s) && (float)$s['tip_amount'] > 0);
            return $saleId > 0 || (int)$state['sale_tips'] > 0;
        }
        if ($check === 'surcharge') {
            $saleId = $this->matchSale($state['sales'], fn(array $s): bool => $this->paid($s) && (float)$s['surcharge_amount'] > 0);
            return $saleId > 0;
        }
        if ($check === 'discount') {
            $saleId = $this->matchSale($state['sales'], fn(array $s): bool => $this->paid($s) && $this->discounted($s));
            return $saleId > 0;
        }
        return match ($check) {
            'payout' => (int)$state['expenses'] > 0,
            'refund' => (int)$state['refunds'] > 0 || (int)$state['voids'] > 0 || (int)$state['refunded_sales'] > 0,
            'tab' => (int)$state['pending_tabs'] > 0,
            'settle' => (int)$state['settled_tabs'] > 0,
            'cook' => (int)$state['cooking'] > 0,
            'ready' => (int)$state['ready'] > 0,
            'recipe' => (int)$state['recipes'] > 0,
            'produce' => (int)$state['produced'] > 0,
            'vendor' => (int)$state['vendors'] > 0,
            'product' => (int)$state['new_products'] > 0,
            'grv' => (int)$state['grvs'] > 0,
            'transfer' => (int)$state['transfers'] > 0,
            'table' => (int)$state['tables'] > 0,
            'member' => (int)$state['members'] > 0,
            'staff' => (int)$state['staff'] > 0,
            'event' => (int)$state['events'] > 0,
            'employee' => (int)$state['employees'] > 0,
            'location' => (int)$state['locations'] > (int)$state['location_mark'],
            default => false,
        };
    }

    private function matchSale(array $sales, callable $ok): int
    {
        foreach ($sales as $sale) {
            if ($ok($sale)) {
                return (int)$sale['id'];
            }
        }
        return 0;
    }

    private function paid(array $sale): bool
    {
        return in_array(strtolower((string)($sale['payment_status'] ?? '')), ['paid', 'completed'], true)
            || in_array(strtolower((string)($sale['status'] ?? '')), ['completed', 'paid'], true);
    }

    private function discounted(array $sale): bool
    {
        return (float)$sale['line_total'] > 0 && (float)$sale['subtotal'] < ((float)$sale['line_total'] - 0.05);
    }

    private function otherTender(array $sale): bool
    {
        $method = strtolower(trim((string)($sale['payment_method'] ?? '')));
        $split = trim((string)($sale['split_method_2'] ?? '')) !== '';
        $extra = trim((string)($sale['split_payments'] ?? ''));
        if ($extra !== '' && $extra !== '[]' && $extra !== 'null') {
            $split = true;
        }
        return $split || ($method !== '' && $method !== 'cash' && $method !== 'pending');
    }

    private function salesAfter(int $afterId): array
    {
        $queries = [
            "SELECT s.id, s.payment_method, COALESCE(s.subtotal, 0) AS subtotal,
                    COALESCE(s.final_total, s.total_amount, 0) AS final_total,
                    s.status, s.payment_status,
                    COALESCE(s.tip_amount, 0) AS tip_amount,
                    COALESCE(s.surcharge_amount, 0) AS surcharge_amount,
                    COALESCE(s.split_method_2, '') AS split_method_2,
                    COALESCE(s.split_payments, '') AS split_payments,
                    (SELECT COALESCE(SUM(price * quantity), 0) FROM sale_items
                      WHERE sale_id = s.id AND COALESCE(is_refund, 0) = 0) AS line_total
             FROM sales s WHERE s.id > ? ORDER BY s.id ASC LIMIT 12",
            "SELECT s.id, s.payment_method, COALESCE(s.subtotal, 0) AS subtotal,
                    COALESCE(s.final_total, s.total_amount, 0) AS final_total,
                    s.status, s.payment_status,
                    0 AS tip_amount, 0 AS surcharge_amount, '' AS split_method_2, '' AS split_payments,
                    (SELECT COALESCE(SUM(price * quantity), 0) FROM sale_items
                      WHERE sale_id = s.id AND COALESCE(is_refund, 0) = 0) AS line_total
             FROM sales s WHERE s.id > ? ORDER BY s.id ASC LIMIT 12",
        ];
        foreach ($queries as $sql) {
            try {
                $stmt = Database::sandbox()->prepare($sql);
                $stmt->execute([$afterId]);
                return $stmt->fetchAll() ?: [];
            } catch (\Throwable $e) {
                continue;
            }
        }
        return [];
    }

    private function coach(array $course, array $step, array $state, bool $passed): string
    {
        $fallback = $passed
            ? 'That step is recorded.'
            : (string)($step['ask'] ?? '');
        $key = $this->apiKey();
        if ($key === '') {
            return $fallback;
        }
        $prompt = $passed
            ? 'The staff member completed this step. In one or two plain sentences, confirm it.'
            : 'The staff member has not finished this step yet. In one or two plain sentences, tell them what to do next. If the practice records do not match the rule, say what to change.';
        $body = [
            'system_instruction' => [
                'parts' => [[
                    'text' => 'You are the smartTrade training coach. Use only the staff manuals and the practice facts you are given. Speak in plain sentences. Do not show code, markdown, or web addresses.',
                ]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => $prompt
                        . "\n\nCourse: " . $course['title']
                        . "\nStep: " . $step['title']
                        . "\nInstruction: " . $step['ask']
                        . "\nRule: " . $step['rule']
                        . "\nPractice records: " . json_encode($this->evidence($step, $state))
                        . "\n\nManual notes:\n" . $this->manualNotes($step),
                ]],
            ]],
            'generationConfig' => [
                'temperature' => 0.2,
                'maxOutputTokens' => 1024,
                'thinkingConfig' => ['thinkingLevel' => 'low'],
            ],
        ];
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.8-flash:generateContent';
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-goog-api-key: ' . $key,
            ],
            CURLOPT_POSTFIELDS => json_encode($body),
        ]);
        $raw = curl_exec($ch);
        curl_close($ch);
        $data = json_decode((string)$raw, true);
        $text = '';
        foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
            if (!empty($part['text']) && empty($part['thought'])) {
                $text .= $part['text'];
            }
        }
        $text = trim(preg_replace('/[`*_#]+/', '', $text) ?? '');
        if ($text === '' || !preg_match('/[.!?]$/', $text)) {
            return $fallback;
        }
        return $text;
    }

    private function evidence(array $step, array $state): array
    {
        $check = (string)$step['check'];
        $sales = [];
        foreach ($state['sales'] as $sale) {
            $sales[] = [
                'id' => (int)$sale['id'],
                'method' => (string)$sale['payment_method'],
                'subtotal' => (float)$sale['subtotal'],
                'lines' => (float)$sale['line_total'],
                'tip' => (float)$sale['tip_amount'],
                'surcharge' => (float)$sale['surcharge_amount'],
                'status' => (string)$sale['payment_status'],
            ];
        }
        return [
            'check' => $check,
            'sales_since_last_step' => $sales,
            'expenses' => (int)$state['expenses'],
            'refunds' => (int)$state['refunds'] + (int)$state['voids'],
            'pending_tabs' => (int)$state['pending_tabs'],
            'uncollected' => (int)$state['uncollected'],
            'ready' => (int)$state['ready'],
            'vendors' => (int)$state['vendors'],
            'new_products' => (int)$state['new_products'],
            'receipts_received' => (int)$state['grvs'],
            'transfers' => (int)$state['transfers'],
            'extra_tables' => (int)$state['tables'],
            'members' => (int)$state['members'],
            'new_staff' => (int)$state['staff'],
            'recipes' => (int)$state['recipes'],
            'batches' => (int)$state['produced'],
            'events' => (int)$state['events'],
            'employees' => (int)$state['employees'],
            'locations' => (int)$state['locations'],
            'locations_at_start' => (int)$state['location_mark'],
        ];
    }

    private function manualNotes(array $step): string
    {
        $needle = strtolower((string)($step['title'] ?? 'sale'));
        $chunks = [];
        foreach ([BASE_PATH . '/user_guide.md', BASE_PATH . '/TRAINING_MANUAL.md'] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            if (preg_match('/.{0,280}' . preg_quote($needle, '/') . '.{0,280}/is', $text, $m)) {
                $chunks[] = trim($m[0]);
            }
        }
        $notes = trim(implode("\n", $chunks));
        if (strlen($notes) > 1200) {
            $notes = substr($notes, 0, 1200);
        }
        return $notes;
    }

    private function apiKey(): string
    {
        try {
            $stmt = Database::production()->prepare(
                "SELECT setting_value FROM settings WHERE setting_key = 'gemini_api_key' LIMIT 1"
            );
            $stmt->execute();
            return trim((string)$stmt->fetchColumn());
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function cloneSchema(PDO $prod, PDO $box): void
    {
        $box->exec('SET FOREIGN_KEY_CHECKS=0');
        $existing = $box->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        foreach ($existing as $row) {
            $name = str_replace('`', '', (string)$row[0]);
            $box->exec('DROP TABLE IF EXISTS `' . $name . '`');
        }
        $tables = $prod->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
        foreach ($tables as $row) {
            $name = (string)$row[0];
            $create = $prod->query('SHOW CREATE TABLE `' . str_replace('`', '', $name) . '`')->fetch();
            $sql = (string)($create['Create Table'] ?? '');
            if ($sql === '') {
                continue;
            }
            $box->exec($sql);
        }
        $box->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function seedLocation(PDO $prod, PDO $box, int $locationId): int
    {
        $row = null;
        if ($locationId > 0) {
            $stmt = $prod->prepare('SELECT * FROM locations WHERE id = ?');
            $stmt->execute([$locationId]);
            $row = $stmt->fetch() ?: null;
        }
        if (!$row) {
            $row = $prod->query("SELECT * FROM locations WHERE can_sell = 1 ORDER BY id LIMIT 1")->fetch() ?: null;
        }
        if (!$row) {
            $this->insert($box, 'locations', [
                'name' => 'Training Counter',
                'type' => 'store',
                'can_sell' => 1,
                'can_receive' => 1,
            ]);
            return (int)$box->lastInsertId();
        }
        $row['can_sell'] = 1;
        $this->insert($box, 'locations', $row);
        return (int)$row['id'];
    }

    private function seedStore(PDO $box): int
    {
        $this->insert($box, 'locations', [
            'name' => 'Training Store',
            'type' => 'warehouse',
            'can_sell' => 0,
            'can_receive' => 1,
        ]);
        return (int)$box->lastInsertId();
    }

    private function seedUser(PDO $prod, PDO $box, int $userId, int $locationId): void
    {
        $stmt = $prod->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([$userId]);
        $row = $stmt->fetch();
        if (!$row) {
            throw new \RuntimeException('Your user record could not be copied into training.');
        }
        $row['location_id'] = $locationId;
        $this->insert($box, 'users', $row);
    }

    private function seedPracticeManager(PDO $box, int $locationId): void
    {
        if (strtolower((string)($_SESSION['username'] ?? '')) === 'train_mgr') {
            return;
        }
        $hash = password_hash('train', PASSWORD_DEFAULT);
        $this->insert($box, 'users', [
            'username' => 'train_mgr',
            'full_name' => 'Training Manager',
            'password_hash' => $hash,
            'password' => $hash,
            'role' => 'manager',
            'location_id' => $locationId,
            'is_active' => 1,
        ]);
    }

    private function seedSettings(PDO $prod, PDO $box): void
    {
        $rows = $prod->query('SELECT * FROM settings')->fetchAll();
        foreach ($rows as $row) {
            $this->insert($box, 'settings', $row);
        }
    }

    private function seedSurcharge(PDO $box): void
    {
        $this->insert($box, 'surcharge_reasons', [
            'name' => 'Takeaway packaging',
            'default_amount' => 5,
            'default_percent' => 0,
            'is_active' => 1,
            'sort_order' => 1,
        ]);
    }

    private function seedCatalog(PDO $box, int $locationId, int $storeId): array
    {
        $this->insert($box, 'categories', [
            'name' => 'Training',
            'type' => 'food',
        ]);
        $categoryId = (int)$box->lastInsertId();
        $this->insert($box, 'categories', [
            'name' => 'Ingredients',
            'type' => 'ingredients',
        ]);
        $ingredientCategory = (int)$box->lastInsertId();
        $items = [
            ['Training Cake', 'TRAIN-CAKE', 25, 1, $categoryId, 'cake'],
            ['Training Juice', 'TRAIN-JUICE', 15, 1, $categoryId, 'juice'],
            ['Training Flour', 'TRAIN-FLOUR', 0, 0, $ingredientCategory, 'flour'],
        ];
        $ids = ['cake' => 0, 'juice' => 0, 'flour' => 0];
        foreach ($items as [$name, $sku, $price, $sellable, $cat, $key]) {
            $this->insert($box, 'products', [
                'name' => $name,
                'sku' => $sku,
                'price' => $price,
                'cost_price' => 5,
                'unit' => 'EA',
                'category_id' => $cat,
                'is_active' => 1,
                'type' => 'item',
                'is_sellable' => $sellable,
                'tax_class' => 'A',
                'unspsc_code' => '50181901',
                'zra_synced' => $sellable,
                'zra_item_cd' => $sellable ? $sku : '',
            ]);
            $productId = (int)$box->lastInsertId();
            $ids[$key] = $productId;
            $this->insert($box, 'inventory', [
                'product_id' => $productId,
                'location_id' => $locationId,
                'quantity' => 40,
            ]);
            $this->insert($box, 'location_stock', [
                'location_id' => $locationId,
                'product_id' => $productId,
                'quantity' => 40,
            ]);
            if ($key === 'flour' && $storeId > 0) {
                $this->insert($box, 'inventory', [
                    'product_id' => $productId,
                    'location_id' => $storeId,
                    'quantity' => 40,
                ]);
                $this->insert($box, 'location_stock', [
                    'location_id' => $storeId,
                    'product_id' => $productId,
                    'quantity' => 40,
                ]);
            }
        }
        return $ids;
    }

    private function seedShift(PDO $box, int $userId, int $locationId): int
    {
        $this->insert($box, 'shifts', [
            'user_id' => $userId,
            'location_id' => $locationId,
            'starting_cash' => 100,
            'status' => 'open',
            'start_verified_by' => $userId,
            'start_verified_at' => date('Y-m-d H:i:s'),
        ]);
        return (int)$box->lastInsertId();
    }

    private function seedTable(PDO $box, int $locationId): int
    {
        $this->insert($box, 'restaurant_tables', [
            'location_id' => $locationId,
            'zone_name' => 'Floor',
            'table_name' => 'Training 1',
            'capacity' => 4,
        ]);
        return (int)$box->lastInsertId();
    }

    private function seedKitchenTicket(PDO $box, int $locationId, int $userId, int $shiftId, int $tableId, int $productId): void
    {
        if ($productId <= 0) {
            return;
        }
        $this->insert($box, 'sales', [
            'location_id' => $locationId,
            'table_id' => $tableId,
            'user_id' => $userId,
            'shift_id' => $shiftId,
            'subtotal' => 25,
            'final_total' => 25,
            'total_amount' => 25,
            'payment_method' => 'Pending',
            'payment_status' => 'pending',
            'status' => 'pending',
            'customer_name' => 'Training 1',
        ]);
        $saleId = (int)$box->lastInsertId();
        $this->insert($box, 'sale_items', [
            'sale_id' => $saleId,
            'product_id' => $productId,
            'quantity' => 1,
            'price' => 25,
            'fulfillment_status' => 'uncollected',
            'status' => 'pending',
        ]);
    }

    private function countSql(string $sql, array $params = []): int
    {
        try {
            $stmt = Database::sandbox()->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function insert(PDO $pdo, string $table, array $row): void
    {
        $columns = [];
        $stmt = $pdo->query('SHOW COLUMNS FROM `' . str_replace('`', '', $table) . '`');
        foreach ($stmt->fetchAll() as $col) {
            $columns[$col['Field']] = true;
        }
        $data = [];
        foreach ($row as $key => $value) {
            if (isset($columns[$key])) {
                $data[$key] = $value;
            }
        }
        if ($data === []) {
            return;
        }
        $names = array_keys($data);
        $sql = 'INSERT INTO `' . str_replace('`', '', $table) . '` (`'
            . implode('`,`', $names) . '`) VALUES ('
            . implode(',', array_fill(0, count($names), '?')) . ')';
        $pdo->prepare($sql)->execute(array_values($data));
    }

    private function destroySandbox(): void
    {
        try {
            $box = Database::sandbox();
        } catch (\Throwable $e) {
            return;
        }
        $box->exec('SET FOREIGN_KEY_CHECKS=0');
        $existing = $box->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM);
        foreach ($existing as $row) {
            $name = str_replace('`', '', (string)$row[0]);
            $box->exec('DROP TABLE IF EXISTS `' . $name . '`');
        }
        $box->exec('SET FOREIGN_KEY_CHECKS=1');
    }

    private function lockPath(): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'smartrade-training.lock';
    }

    private function lock(): array
    {
        $path = $this->lockPath();
        if (!is_file($path) || !is_readable($path)) {
            return ['user' => 0, 'session' => '', 'at' => ''];
        }
        $data = json_decode((string)file_get_contents($path), true);
        return [
            'user' => (int)($data['user'] ?? 0),
            'session' => (string)($data['session'] ?? ''),
            'at' => (string)($data['at'] ?? ''),
        ];
    }

    private function writeLock(int $userId, string $sessionId): void
    {
        $written = file_put_contents($this->lockPath(), json_encode([
            'user' => $userId,
            'session' => $sessionId,
            'at' => date('Y-m-d H:i:s'),
        ]), LOCK_EX);
        if ($written === false) {
            throw new \RuntimeException('Training could not start. The practice session could not be saved.');
        }
    }

    private function clearLock(): void
    {
        $path = $this->lockPath();
        if (is_file($path)) {
            unlink($path);
        }
    }

    private function stale(string $at): bool
    {
        $time = strtotime($at);
        return $time === false || $time < time() - 8 * 3600;
    }
}
