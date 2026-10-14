<?php

namespace App\Services;

use App\Core\Database;
use PDO;

final class Training
{
    public function start(int $userId, int $locationId): void
    {
        if ($userId <= 0) {
            throw new \RuntimeException('Sign in before starting training.');
        }
        $this->releaseIfAbandoned();
        $lock = $this->lock();
        $sessionId = session_id();
        if ($lock['user'] > 0 && $lock['session'] !== '' && $lock['session'] !== $sessionId && !$this->stale($lock['at'])) {
            throw new \RuntimeException('Someone else is already using Training Mode.');
        }
        if ($lock['user'] > 0) {
            $this->destroySandbox();
        }

        $prod = Database::production();
        $box = Database::sandbox();
        $this->cloneSchema($prod, $box);
        $locationId = $this->seedLocation($prod, $box, $locationId);
        $this->seedUser($prod, $box, $userId, $locationId);
        $this->seedSettings($prod, $box);
        $this->seedCatalog($box, $locationId);
        $this->seedShift($box, $userId, $locationId);
        $this->writeLock($userId, $sessionId);

        $_SESSION['training_mode'] = 1;
        $_SESSION['training_step'] = 0;
        $_SESSION['training_after_id'] = 0;
        $_SESSION['training_started'] = date('Y-m-d H:i:s');
        $_SESSION['location_id'] = $locationId;
        $_SESSION['pos_location_id'] = $locationId;
        $name = $box->prepare('SELECT name FROM locations WHERE id = ?');
        $name->execute([$locationId]);
        $_SESSION['location_name'] = $name->fetchColumn() ?: 'Training';
        unset($_SESSION['training_msg'], $_SESSION['training_fp']);
    }

    public function end(): void
    {
        $this->destroySandbox();
        $this->clearLock();
        unset(
            $_SESSION['training_mode'],
            $_SESSION['training_step'],
            $_SESSION['training_after_id'],
            $_SESSION['training_started'],
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
        if ($lock['user'] <= 0) {
            return;
        }
        if ($lock['session'] === session_id() && !$this->stale($lock['at'])) {
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
        $step = (int)($_SESSION['training_step'] ?? 0);
        if ($step > 0 && $this->saleCount() === 0) {
            $step = 0;
            $_SESSION['training_step'] = 0;
            $_SESSION['training_after_id'] = 0;
            unset($_SESSION['training_fp'], $_SESSION['training_msg']);
        }
        $steps = $this->steps();
        if ($step >= count($steps)) {
            return [
                'active' => true,
                'done' => true,
                'title' => 'Training complete',
                'message' => 'You finished the practice sale, the discount, and the void. Choose End Training to leave the practice data.',
            ];
        }
        $state = $this->snapshot((int)($_SESSION['training_after_id'] ?? 0));
        $passed = $this->passed($step, $state);
        $justPassed = false;
        if ($passed) {
            $justPassed = true;
            $_SESSION['training_after_id'] = (int)$state['latest_id'];
            $step++;
            $_SESSION['training_step'] = $step;
            unset($_SESSION['training_fp'], $_SESSION['training_msg']);
            if ($step >= count($steps)) {
                return [
                    'active' => true,
                    'done' => true,
                    'title' => 'Training complete',
                    'message' => $this->coach($steps[$step - 1], $state, true),
                ];
            }
        }
        $current = $steps[$step];
        $fp = $step . '|v2|' . json_encode($state);
        if (($_SESSION['training_fp'] ?? '') === $fp && isset($_SESSION['training_msg'])) {
            $message = (string)$_SESSION['training_msg'];
        } else {
            $message = $this->coach($current, $state, false);
            $_SESSION['training_fp'] = $fp;
            $_SESSION['training_msg'] = $message;
        }
        if ($justPassed) {
            $message = 'That step is recorded. ' . $message;
        }
        return [
            'active' => true,
            'done' => false,
            'step' => $step + 1,
            'of' => count($steps),
            'title' => $current['title'],
            'message' => $message,
        ];
    }

    private function steps(): array
    {
        return [
            [
                'title' => 'Ring a cash sale',
                'ask' => 'On the Point of Sale, sell Training Cake, take cash, and complete the sale. Do not add a discount.',
                'rule' => 'A completed cash sale with no discount.',
            ],
            [
                'title' => 'Apply a discount',
                'ask' => 'Sell Training Juice and enter a discount before you complete the sale.',
                'rule' => 'A completed sale whose discount is greater than zero.',
            ],
            [
                'title' => 'Void the item',
                'ask' => 'Refund the juice sale and return the item, which voids it.',
                'rule' => 'A refund or a voided sale item.',
            ],
        ];
    }

    private function saleCount(): int
    {
        try {
            return (int)Database::sandbox()->query('SELECT COUNT(*) FROM sales')->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function snapshot(int $afterId): array
    {
        $box = Database::sandbox();
        $sale = [
            'id' => 0, 'payment_method' => '', 'subtotal' => 0, 'final_total' => 0,
            'status' => '', 'payment_status' => '', 'line_total' => 0,
        ];
        try {
            $stmt = $box->prepare(
                "SELECT s.id, s.payment_method, COALESCE(s.subtotal, 0) AS subtotal,
                        COALESCE(s.final_total, s.total_amount, 0) AS final_total,
                        s.status, s.payment_status,
                        (SELECT COALESCE(SUM(price * quantity), 0) FROM sale_items
                          WHERE sale_id = s.id AND COALESCE(is_refund, 0) = 0) AS line_total
                 FROM sales s WHERE s.id > ? ORDER BY s.id DESC LIMIT 1"
            );
            $stmt->execute([$afterId]);
            $row = $stmt->fetch();
            if ($row) {
                $sale = $row;
            }
        } catch (\Throwable $e) {
            $sale = $sale;
        }
        $refunds = 0;
        try {
            $stmt = $box->prepare('SELECT COUNT(*) FROM refunds WHERE sale_id > ?');
            $stmt->execute([$afterId]);
            $refunds = (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            $refunds = 0;
        }
        $voids = 0;
        try {
            $stmt = $box->prepare(
                "SELECT COUNT(*) FROM sale_items WHERE is_refund = 1 AND sale_id > ?"
            );
            $stmt->execute([$afterId]);
            $voids = (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            $voids = 0;
        }
        return [
            'latest_id' => (int)($sale['id'] ?? 0),
            'payment_method' => (string)($sale['payment_method'] ?? ''),
            'subtotal' => (float)($sale['subtotal'] ?? 0),
            'line_total' => (float)($sale['line_total'] ?? 0),
            'final_total' => (float)($sale['final_total'] ?? 0),
            'status' => (string)($sale['status'] ?? ''),
            'payment_status' => (string)($sale['payment_status'] ?? ''),
            'refunds' => $refunds,
            'voids' => $voids,
        ];
    }

    private function passed(int $step, array $state): bool
    {
        if ((int)$state['latest_id'] <= 0 && $step < 2) {
            return false;
        }
        $paid = in_array(strtolower((string)$state['payment_status']), ['paid', 'completed'], true)
            || in_array(strtolower((string)$state['status']), ['completed', 'paid'], true);
        $discounted = $state['line_total'] > 0 && $state['subtotal'] < ($state['line_total'] - 0.05);
        if ($step === 0) {
            return $paid
                && $state['line_total'] > 0
                && !$discounted
                && strtolower($state['payment_method']) === 'cash';
        }
        if ($step === 1) {
            return $paid && $discounted;
        }
        return $state['refunds'] > 0 || $state['voids'] > 0 || $state['status'] === 'refunded';
    }

    private function coach(array $step, array $state, bool $passed): string
    {
        $fallback = $passed
            ? 'That step is recorded. ' . ($step['ask'] ?? '')
            : (string)($step['ask'] ?? '');
        if ($passed && ($step['title'] ?? '') === 'Void the item') {
            $fallback = 'The void is recorded in the practice sale. Choose End Training when you are finished.';
        }
        $key = $this->apiKey();
        if ($key === '') {
            return $fallback;
        }
        $prompt = $passed
            ? 'The cashier completed this step: ' . $step['title'] . '. In one or two plain sentences, confirm it and introduce the next action if one is included.'
            : 'The cashier has not finished this step yet. In one or two plain sentences, tell them what to do next. If their latest practice sale does not match the rule, say what to change.';
        $body = [
            'system_instruction' => [
                'parts' => [[
                    'text' => 'You are the smartTrade POS training coach. Use only the staff manuals and the practice-sale facts you are given. Speak in plain sentences. Do not show code, markdown, or web addresses.',
                ]],
            ],
            'contents' => [[
                'role' => 'user',
                'parts' => [[
                    'text' => $prompt
                        . "\n\nStep: " . $step['title']
                        . "\nInstruction: " . $step['ask']
                        . "\nRule: " . $step['rule']
                        . "\nLatest practice sale: " . json_encode($state)
                        . "\n\nManual notes:\n" . $this->manualNotes(),
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

    private function manualNotes(): string
    {
        $chunks = [];
        foreach ([BASE_PATH . '/user_guide.md', BASE_PATH . '/TRAINING_MANUAL.md'] as $path) {
            if (!is_file($path)) {
                continue;
            }
            $text = (string)file_get_contents($path);
            if (preg_match('/.{0,400}discount.{0,400}/is', $text, $m)) {
                $chunks[] = trim($m[0]);
            }
            if (preg_match('/.{0,400}(refund|void).{0,400}/is', $text, $m)) {
                $chunks[] = trim($m[0]);
            }
        }
        $notes = trim(implode("\n", $chunks));
        if (strlen($notes) > 2000) {
            $notes = substr($notes, 0, 2000);
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
            ]);
            return (int)$box->lastInsertId();
        }
        $row['can_sell'] = 1;
        $this->insert($box, 'locations', $row);
        return (int)$row['id'];
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

    private function seedSettings(PDO $prod, PDO $box): void
    {
        $rows = $prod->query('SELECT * FROM settings')->fetchAll();
        foreach ($rows as $row) {
            $this->insert($box, 'settings', $row);
        }
    }

    private function seedCatalog(PDO $box, int $locationId): void
    {
        $this->insert($box, 'categories', [
            'name' => 'Training',
            'type' => 'food',
        ]);
        $categoryId = (int)$box->lastInsertId();
        $items = [
            ['Training Cake', 'TRAIN-CAKE', 25],
            ['Training Juice', 'TRAIN-JUICE', 15],
        ];
        foreach ($items as [$name, $sku, $price]) {
            $this->insert($box, 'products', [
                'name' => $name,
                'sku' => $sku,
                'price' => $price,
                'cost_price' => 5,
                'unit' => 'EA',
                'category_id' => $categoryId,
                'is_active' => 1,
                'type' => 'item',
                'is_sellable' => 1,
                'tax_class' => 'A',
                'unspsc_code' => '50181901',
                'zra_synced' => 1,
                'zra_item_cd' => $sku,
            ]);
            $productId = (int)$box->lastInsertId();
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
        }
    }

    private function seedShift(PDO $box, int $userId, int $locationId): void
    {
        $this->insert($box, 'shifts', [
            'user_id' => $userId,
            'location_id' => $locationId,
            'starting_cash' => 100,
            'status' => 'open',
            'start_verified_by' => $userId,
            'start_verified_at' => date('Y-m-d H:i:s'),
        ]);
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
