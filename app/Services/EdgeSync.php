<?php

namespace App\Services;

use App\Core\Schema;
use App\Helpers\Media;
use App\Models\Setting;
use PDO;

final class EdgeSync
{
    private array $settings;

    public function __construct(private PDO $pdo, ?array $settings = null)
    {
        $this->settings = $settings ?? Setting::all();
        Schema::ensure($this->pdo, $this->settings);
    }

    public function role(): string
    {
        $envRole = strtolower(trim((string)(getenv('SYNC_NODE_ROLE') ?: ($_ENV['SYNC_NODE_ROLE'] ?? ''))));
        if (in_array($envRole, ['online', 'edge'], true)) {
            return $envRole;
        }
        $dbRole = strtolower(trim((string)($this->settings['sync_node_role'] ?? 'online')));
        return in_array($dbRole, ['online', 'edge'], true) ? $dbRole : 'online';
    }

    public function isEdge(): bool
    {
        return $this->role() === 'edge';
    }

    public function onlineUrl(): string
    {
        $envUrl = trim((string)(getenv('SYNC_ONLINE_URL') ?: ($_ENV['SYNC_ONLINE_URL'] ?? '')));
        $url = $envUrl !== '' ? $envUrl : trim((string)($this->settings['sync_online_url'] ?? ''));
        return rtrim($url, '/');
    }

    public function token(): string
    {
        $envTok = trim((string)(getenv('SYNC_NODE_TOKEN') ?: ($_ENV['SYNC_NODE_TOKEN'] ?? '')));
        return $envTok !== '' ? $envTok : trim((string)($this->settings['sync_node_token'] ?? ''));
    }

    public function locationId(): int
    {
        $envLoc = (int)(getenv('SYNC_LOCATION_ID') ?: ($_ENV['SYNC_LOCATION_ID'] ?? 0));
        if ($envLoc > 0) {
            return $envLoc;
        }
        return (int)($this->settings['sync_location_id'] ?? 0);
    }

    public function configured(): bool
    {
        return $this->token() !== '' && (!$this->isEdge() || $this->onlineUrl() !== '');
    }

    public static function generateToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public function verifyBearer(string $authHeader): bool
    {
        $expected = $this->token();
        if ($expected === '' || strlen($expected) < 16) {
            return false;
        }
        if (!preg_match('/^Bearer\s+([A-Za-z0-9._\-]+)$/i', trim($authHeader), $m)) {
            return false;
        }
        return hash_equals($expected, $m[1]);
    }

    public function ensureLocalUuids(): void
    {
        foreach (['shifts', 'sales', 'sale_tips', 'inventory_logs', 'expenses'] as $table) {
            try {
                $stmt = $this->pdo->query("SELECT id FROM `$table` WHERE sync_uuid IS NULL OR sync_uuid = '' LIMIT 500");
                $ids = $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
                if ($ids) {
                    $upd = $this->pdo->prepare("UPDATE `$table` SET sync_uuid = ? WHERE id = ? AND (sync_uuid IS NULL OR sync_uuid = '')");
                    foreach ($ids as $id) {
                        $upd->execute([self::uuid(), (int)$id]);
                    }
                }
            } catch (\Throwable $e) {
                // Schema may still be upgrading
            }
        }
    }

    public function pendingCount(): int
    {
        $total = 0;
        foreach (['shifts', 'sales', 'sale_tips', 'inventory_logs', 'expenses'] as $table) {
            try {
                $total += (int)$this->pdo->query("SELECT COUNT(*) FROM `$table` WHERE synced_at IS NULL")->fetchColumn();
            } catch (\Throwable $e) {
            }
        }
        return $total;
    }

    public function statusSummary(): array
    {
        $state = [
            'role' => $this->role(),
            'configured' => $this->configured(),
            'online_url' => $this->onlineUrl(),
            'location_id' => $this->locationId(),
            'last_pull_at' => null,
            'last_push_at' => null,
            'last_attempt_at' => null,
            'last_status' => 'idle',
            'last_error' => null,
            'pending_count' => 0,
        ];
        try {
            $row = $this->pdo->query('SELECT * FROM edge_sync_state WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $state['last_pull_at'] = $row['last_pull_at'];
                $state['last_push_at'] = $row['last_push_at'];
                $state['last_attempt_at'] = $row['last_attempt_at'];
                $state['last_status'] = (string)$row['last_status'];
                $state['last_error'] = $row['last_error'];
            }
            if ($this->isEdge()) {
                $state['pending_count'] = $this->pendingCount();
            }
        } catch (\Throwable $e) {
        }
        return $state;
    }

    private function recordState(string $status, ?string $error = null, bool $pushed = false, bool $pulled = false): void
    {
        try {
            $pending = $this->isEdge() ? $this->pendingCount() : 0;
            $now = date('Y-m-d H:i:s');
            $sql = 'UPDATE edge_sync_state SET last_attempt_at = ?, last_status = ?, last_error = ?, pending_count = ?'
                . ($pushed ? ', last_push_at = ' . $this->pdo->quote($now) : '')
                . ($pulled ? ', last_pull_at = ' . $this->pdo->quote($now) : '')
                . ' WHERE id = 1';
            $this->pdo->prepare($sql)->execute([$now, $status, $error !== null ? mb_substr($error, 0, 500) : null, $pending]);
        } catch (\Throwable $e) {
        }
    }

    /**
     * Immediate single-sale push + remote ZRA fiscalization called during Edge checkout.
     * Falls back gracefully if internet or Online HQ is unreachable.
     */
    public function pushAndFiscalizeSale(int $saleId, array $opts = []): array
    {
        if (!$this->isEdge() || !$this->configured() || $saleId <= 0) {
            return ['ok' => false, 'msg' => 'Edge sync not configured'];
        }
        try {
            $this->ensureLocalUuids();
            $payload = $this->collectPushPayload($saleId, $opts);
            $res = $this->postToOnline('push', $payload, 6);
            if (($res['status'] ?? '') !== 'ok') {
                $err = (string)($res['msg'] ?? 'Online HQ unreachable');
                $this->recordState('offline', $err);
                return ['ok' => false, 'msg' => $err];
            }
            $this->applyPushAck($res);
            $this->recordState('ok', null, true, false);
            $saleRow = $this->pdo->prepare('SELECT zra_rcpt_sign, zra_status, zra_last_error FROM sales WHERE id = ?');
            $saleRow->execute([$saleId]);
            $s = $saleRow->fetch(PDO::FETCH_ASSOC) ?: [];
            if (!empty($s['zra_rcpt_sign'])) {
                return ['ok' => true, 'fiscalized' => true, 'msg' => 'Posted to ZRA via Online HQ.'];
            }
            return ['ok' => true, 'fiscalized' => false, 'msg' => (string)($s['zra_last_error'] ?? 'Synced to Online HQ; ZRA pending.')];
        } catch (\Throwable $e) {
            $this->recordState('offline', $e->getMessage());
            return ['ok' => false, 'msg' => $e->getMessage()];
        }
    }

    /**
     * Full two-step Edge synchronization (Push local transactions -> Pull HQ master catalog & web orders).
     */
    public function syncNow(bool $force = false): array
    {
        if (!$this->isEdge()) {
            return ['status' => 'skip', 'msg' => 'This node is Online HQ (Master).'];
        }
        if (!$this->configured()) {
            return ['status' => 'error', 'msg' => 'Configure the Online HQ URL and Edge Sync Token under Settings → Edge Sync.'];
        }

        if (!$force) {
            $st = $this->statusSummary();
            $last = !empty($st['last_attempt_at']) ? strtotime((string)$st['last_attempt_at']) : 0;
            if ($last > 0 && (time() - $last) < 15) {
                return ['status' => 'throttled', 'summary' => $st];
            }
        }

        try {
            $this->ensureLocalUuids();

            // 1. PUSH local unsynced records to Online HQ
            $pushPayload = $this->collectPushPayload();
            $pushedAny = false;
            if (
                !empty($pushPayload['shifts'])
                || !empty($pushPayload['sales'])
                || !empty($pushPayload['inventory_logs'])
                || !empty($pushPayload['sale_tips'])
                || !empty($pushPayload['expenses'])
            ) {
                $pushRes = $this->postToOnline('push', $pushPayload, 15);
                if (($pushRes['status'] ?? '') !== 'ok') {
                    $err = (string)($pushRes['msg'] ?? 'Push to Online HQ failed.');
                    $this->recordState('offline', $err);
                    return ['status' => 'offline', 'msg' => $err, 'summary' => $this->statusSummary()];
                }
                $this->applyPushAck($pushRes);
                $pushedAny = true;
            }

            // 2. PULL master catalog, staff, settings, web orders & media from Online HQ
            $st = $this->statusSummary();
            $pullRes = $this->postToOnline('pull', [
                'since' => $st['last_pull_at'],
                'location_id' => $this->locationId(),
            ], 20);
            if (($pullRes['status'] ?? '') !== 'ok') {
                $err = (string)($pullRes['msg'] ?? 'Pull from Online HQ failed.');
                $this->recordState('offline', $err, $pushedAny, false);
                return ['status' => 'offline', 'msg' => $err, 'summary' => $this->statusSummary()];
            }
            $this->applyPullPayload($pullRes['data'] ?? []);
            $this->recordState('ok', null, $pushedAny, true);

            return [
                'status' => 'ok',
                'msg' => 'Synchronized with Online HQ.',
                'summary' => $this->statusSummary(),
            ];
        } catch (\Throwable $e) {
            $this->recordState('offline', $e->getMessage());
            return [
                'status' => 'offline',
                'msg' => $e->getMessage(),
                'summary' => $this->statusSummary(),
            ];
        }
    }

    private function collectPushPayload(?int $onlySaleId = null, array $opts = []): array
    {
        $shifts = $this->pdo->query(
            'SELECT * FROM shifts WHERE synced_at IS NULL OR (end_time IS NOT NULL AND synced_at < end_time) ORDER BY id ASC LIMIT 50'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($onlySaleId !== null && $onlySaleId > 0) {
            $stmt = $this->pdo->prepare('SELECT * FROM sales WHERE id = ?');
            $stmt->execute([$onlySaleId]);
            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $sales = $this->pdo->query(
                "SELECT * FROM sales WHERE synced_at IS NULL OR (payment_status = 'paid' AND COALESCE(zra_status, 'pending') <> 'synced' AND synced_at < DATE_SUB(NOW(), INTERVAL 2 MINUTE)) ORDER BY id ASC LIMIT 40"
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }

        $shiftUuidById = [];
        foreach ($shifts as $sh) {
            $shiftUuidById[(int)$sh['id']] = (string)$sh['sync_uuid'];
        }

        $salePayloads = [];
        foreach ($sales as $sale) {
            $sid = (int)$sale['id'];
            $shiftId = (int)($sale['shift_id'] ?? 0);
            if ($shiftId > 0 && !isset($shiftUuidById[$shiftId])) {
                $shStmt = $this->pdo->prepare('SELECT sync_uuid FROM shifts WHERE id = ?');
                $shStmt->execute([$shiftId]);
                $shiftUuidById[$shiftId] = (string)$shStmt->fetchColumn();
            }
            $sale['shift_sync_uuid'] = $shiftId > 0 ? ($shiftUuidById[$shiftId] ?? null) : null;
            if ($onlySaleId === $sid && !empty($opts['rfdRsnCd'])) {
                $sale['zra_rfd_rsn_cd'] = (string)$opts['rfdRsnCd'];
            }
            $itStmt = $this->pdo->prepare('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id ASC');
            $itStmt->execute([$sid]);
            $sale['items'] = $itStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $salePayloads[] = $sale;
        }

        $logs = $this->pdo->query(
            'SELECT * FROM inventory_logs WHERE synced_at IS NULL ORDER BY id ASC LIMIT 200'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $tips = $this->pdo->query(
            'SELECT t.*, s.sync_uuid AS sale_sync_uuid, sh.sync_uuid AS shift_sync_uuid
             FROM sale_tips t
             LEFT JOIN sales s ON s.id = t.sale_id
             LEFT JOIN shifts sh ON sh.id = t.shift_id
             WHERE t.synced_at IS NULL
             ORDER BY t.id ASC LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $expenses = $this->pdo->query(
            'SELECT e.*, sh.sync_uuid AS shift_sync_uuid
             FROM expenses e
             LEFT JOIN shifts sh ON sh.id = e.shift_id
             WHERE e.synced_at IS NULL
             ORDER BY e.id ASC LIMIT 100'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'shifts' => $shifts,
            'sales' => $salePayloads,
            'inventory_logs' => $logs,
            'sale_tips' => $tips,
            'expenses' => $expenses,
        ];
    }

    private function applyPushAck(array $res): void
    {
        $now = date('Y-m-d H:i:s');
        foreach (['shifts', 'inventory_logs', 'sale_tips', 'expenses'] as $table) {
            $uuids = $res['acked_' . $table] ?? [];
            if (!is_array($uuids) || !$uuids) {
                continue;
            }
            $upd = $this->pdo->prepare("UPDATE `$table` SET synced_at = ? WHERE sync_uuid = ?");
            foreach ($uuids as $u) {
                $upd->execute([$now, (string)$u]);
            }
        }

        $ackedSales = $res['acked_sales'] ?? [];
        if (is_array($ackedSales)) {
            $updSale = $this->pdo->prepare('UPDATE sales SET synced_at = ? WHERE sync_uuid = ?');
            $updZra = $this->pdo->prepare(
                'UPDATE sales SET
                    synced_at = ?,
                    zra_rcpt_no = COALESCE(?, zra_rcpt_no),
                    zra_intrl_data = COALESCE(?, zra_intrl_data),
                    zra_rcpt_sign = COALESCE(?, zra_rcpt_sign),
                    zra_vsdc_rcpt_pbct_date = COALESCE(?, zra_vsdc_rcpt_pbct_date),
                    zra_sdc_id = COALESCE(?, zra_sdc_id),
                    zra_mrc_no = COALESCE(?, zra_mrc_no),
                    zra_qr_code_url = COALESCE(?, zra_qr_code_url),
                    zra_cis_invc_no = COALESCE(?, zra_cis_invc_no),
                    zra_status = COALESCE(?, zra_status),
                    zra_last_error = ?
                 WHERE sync_uuid = ?'
            );
            foreach ($ackedSales as $uuid => $info) {
                if (is_array($info) && !empty($info['zra'])) {
                    $z = $info['zra'];
                    $updZra->execute([
                        $now,
                        $z['zra_rcpt_no'] ?? null,
                        $z['zra_intrl_data'] ?? null,
                        $z['zra_rcpt_sign'] ?? null,
                        $z['zra_vsdc_rcpt_pbct_date'] ?? null,
                        $z['zra_sdc_id'] ?? null,
                        $z['zra_mrc_no'] ?? null,
                        $z['zra_qr_code_url'] ?? null,
                        $z['zra_cis_invc_no'] ?? null,
                        $z['zra_status'] ?? null,
                        $z['zra_last_error'] ?? null,
                        (string)$uuid,
                    ]);
                } else {
                    $updSale->execute([$now, is_int($uuid) ? (string)$info : (string)$uuid]);
                }
            }
        }
    }

    /**
     * Runs on Online HQ: receives batches pushed by an Edge node, records them idempotently,
     * applies inventory deltas, and fiscalizes paid sales on the Master ZRA VSDC.
     */
    public function handleIncomingPush(array $payload): array
    {
        $ackedShifts = [];
        $ackedSales = [];
        $ackedLogs = [];
        $ackedTips = [];
        $ackedExpenses = [];
        $toFiscalize = [];

        $this->pdo->beginTransaction();
        try {
            // 1. Shifts
            foreach ((array)($payload['shifts'] ?? []) as $sh) {
                $uuid = trim((string)($sh['sync_uuid'] ?? ''));
                if ($uuid === '') {
                    continue;
                }
                $chk = $this->pdo->prepare('SELECT id FROM shifts WHERE sync_uuid = ? LIMIT 1');
                $chk->execute([$uuid]);
                $existingId = (int)$chk->fetchColumn();
                if ($existingId > 0) {
                    $this->pdo->prepare(
                        'UPDATE shifts SET end_time = COALESCE(?, end_time), closing_cash = ?, expected_cash = ?, manager_closing_cash = ?, status = ?, variance_reason = ?, handover_notes = ?, end_verified_by = ?, end_verified_at = ?, variance = ?, synced_at = NOW() WHERE id = ?'
                    )->execute([
                        $sh['end_time'] ?? null,
                        (float)($sh['closing_cash'] ?? 0),
                        (float)($sh['expected_cash'] ?? 0),
                        (float)($sh['manager_closing_cash'] ?? 0),
                        (string)($sh['status'] ?? 'open'),
                        $sh['variance_reason'] ?? null,
                        $sh['handover_notes'] ?? null,
                        !empty($sh['end_verified_by']) ? (int)$sh['end_verified_by'] : null,
                        $sh['end_verified_at'] ?? null,
                        (float)($sh['variance'] ?? 0),
                        $existingId,
                    ]);
                } else {
                    $this->pdo->prepare(
                        'INSERT INTO shifts (user_id, location_id, start_time, end_time, starting_cash, closing_cash, expected_cash, manager_closing_cash, status, variance_reason, handover_notes, start_verified_by, start_verified_at, end_verified_by, end_verified_at, variance, sync_uuid, synced_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    )->execute([
                        (int)($sh['user_id'] ?? 1),
                        (int)($sh['location_id'] ?? 1),
                        $sh['start_time'] ?? date('Y-m-d H:i:s'),
                        $sh['end_time'] ?? null,
                        (float)($sh['starting_cash'] ?? 0),
                        (float)($sh['closing_cash'] ?? 0),
                        (float)($sh['expected_cash'] ?? 0),
                        (float)($sh['manager_closing_cash'] ?? 0),
                        (string)($sh['status'] ?? 'open'),
                        $sh['variance_reason'] ?? null,
                        $sh['handover_notes'] ?? null,
                        !empty($sh['start_verified_by']) ? (int)$sh['start_verified_by'] : null,
                        $sh['start_verified_at'] ?? null,
                        !empty($sh['end_verified_by']) ? (int)$sh['end_verified_by'] : null,
                        $sh['end_verified_at'] ?? null,
                        (float)($sh['variance'] ?? 0),
                        $uuid,
                    ]);
                }
                $ackedShifts[] = $uuid;
            }

            // 2. Sales & Sale Items
            foreach ((array)($payload['sales'] ?? []) as $sale) {
                $uuid = trim((string)($sale['sync_uuid'] ?? ''));
                if ($uuid === '') {
                    continue;
                }
                $shiftId = null;
                if (!empty($sale['shift_sync_uuid'])) {
                    $sStmt = $this->pdo->prepare('SELECT id FROM shifts WHERE sync_uuid = ? LIMIT 1');
                    $sStmt->execute([(string)$sale['shift_sync_uuid']]);
                    $foundShift = (int)$sStmt->fetchColumn();
                    if ($foundShift > 0) {
                        $shiftId = $foundShift;
                    }
                }

                $chk = $this->pdo->prepare('SELECT id, zra_rcpt_sign FROM sales WHERE sync_uuid = ? LIMIT 1');
                $chk->execute([$uuid]);
                $existing = $chk->fetch(PDO::FETCH_ASSOC);

                if ($existing) {
                    $onlineSaleId = (int)$existing['id'];
                    $this->pdo->prepare(
                        'UPDATE sales SET shift_id = COALESCE(?, shift_id), status = ?, subtotal = ?, tip_amount = ?, surcharge_amount = ?, surcharge_reason = ?, final_total = ?, payment_method = ?, payment_status = ?, amount_tendered = ?, change_due = ?, split_method_1 = ?, split_amount_1 = ?, split_method_2 = ?, split_amount_2 = ?, split_payments = ?, collected_by = COALESCE(?, collected_by), synced_at = NOW() WHERE id = ?'
                    )->execute([
                        $shiftId,
                        (string)($sale['status'] ?? 'completed'),
                        (float)($sale['subtotal'] ?? 0),
                        (float)($sale['tip_amount'] ?? 0),
                        (float)($sale['surcharge_amount'] ?? 0),
                        $sale['surcharge_reason'] ?? null,
                        (float)($sale['final_total'] ?? 0),
                        (string)($sale['payment_method'] ?? 'Cash'),
                        (string)($sale['payment_status'] ?? 'paid'),
                        (float)($sale['amount_tendered'] ?? 0),
                        (float)($sale['change_due'] ?? 0),
                        $sale['split_method_1'] ?? null,
                        (float)($sale['split_amount_1'] ?? 0),
                        $sale['split_method_2'] ?? null,
                        (float)($sale['split_amount_2'] ?? 0),
                        $sale['split_payments'] ?? null,
                        $sale['collected_by'] ?? null,
                        $onlineSaleId,
                    ]);
                    if ((string)($sale['status'] ?? '') === 'completed') {
                        $this->pdo->prepare("UPDATE sale_items SET fulfillment_status = 'collected', status = 'ready' WHERE sale_id = ?")
                            ->execute([$onlineSaleId]);
                    }
                } else {
                    $ins = $this->pdo->prepare(
                        'INSERT INTO sales (location_id, table_id, user_id, shift_id, subtotal, total_amount, discount, total_tax, tip, final_total, payment_method, status, created_at, collected_by, payment_status, customer_name, amount_tendered, change_due, tip_amount, surcharge_amount, surcharge_reason, split_method_1, split_amount_1, split_method_2, split_amount_2, split_payments, tender_currency, tender_fx_rate, tender_fx_amount, gateway_ref, order_source, order_type, order_notes, customer_id, customer_phone, zra_org_invc_no, zra_rfd_rsn_cd, sync_uuid, synced_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $ins->execute([
                        (int)($sale['location_id'] ?? 1),
                        !empty($sale['table_id']) ? (int)$sale['table_id'] : null,
                        (int)($sale['user_id'] ?? 1),
                        $shiftId,
                        (float)($sale['subtotal'] ?? 0),
                        (float)($sale['total_amount'] ?? ($sale['subtotal'] ?? 0)),
                        (float)($sale['discount'] ?? 0),
                        (float)($sale['total_tax'] ?? 0),
                        (float)($sale['tip'] ?? 0),
                        (float)($sale['final_total'] ?? 0),
                        (string)($sale['payment_method'] ?? 'Cash'),
                        (string)($sale['status'] ?? 'completed'),
                        $sale['created_at'] ?? date('Y-m-d H:i:s'),
                        $sale['collected_by'] ?? null,
                        (string)($sale['payment_status'] ?? 'paid'),
                        (string)($sale['customer_name'] ?? 'Walk-in'),
                        (float)($sale['amount_tendered'] ?? 0),
                        (float)($sale['change_due'] ?? 0),
                        (float)($sale['tip_amount'] ?? 0),
                        (float)($sale['surcharge_amount'] ?? 0),
                        $sale['surcharge_reason'] ?? null,
                        $sale['split_method_1'] ?? null,
                        (float)($sale['split_amount_1'] ?? 0),
                        $sale['split_method_2'] ?? null,
                        (float)($sale['split_amount_2'] ?? 0),
                        $sale['split_payments'] ?? null,
                        $sale['tender_currency'] ?? null,
                        !empty($sale['tender_fx_rate']) ? (float)$sale['tender_fx_rate'] : null,
                        !empty($sale['tender_fx_amount']) ? (float)$sale['tender_fx_amount'] : null,
                        $sale['gateway_ref'] ?? null,
                        (string)($sale['order_source'] ?? 'pos'),
                        $sale['order_type'] ?? null,
                        $sale['order_notes'] ?? null,
                        !empty($sale['customer_id']) ? (int)$sale['customer_id'] : null,
                        $sale['customer_phone'] ?? null,
                        !empty($sale['zra_org_invc_no']) ? (int)$sale['zra_org_invc_no'] : null,
                        $sale['zra_rfd_rsn_cd'] ?? null,
                        $uuid,
                    ]);
                    $onlineSaleId = (int)$this->pdo->lastInsertId();

                    $insItem = $this->pdo->prepare(
                        'INSERT INTO sale_items (sale_id, product_id, quantity, price, price_at_sale, cost_at_sale, status, fulfillment_status, is_refund)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
                    );
                    foreach ((array)($sale['items'] ?? []) as $item) {
                        $insItem->execute([
                            $onlineSaleId,
                            (int)$item['product_id'],
                            (float)$item['quantity'],
                            (float)$item['price'],
                            (float)($item['price_at_sale'] ?? $item['price']),
                            (float)($item['cost_at_sale'] ?? 0),
                            (string)($item['status'] ?? 'ready'),
                            (string)($item['fulfillment_status'] ?? 'collected'),
                            !empty($item['is_refund']) ? 1 : 0,
                        ]);
                    }
                }

                if ((string)($sale['payment_status'] ?? '') === 'paid' && empty($existing['zra_rcpt_sign'])) {
                    $toFiscalize[$uuid] = [
                        'id' => $onlineSaleId,
                        'rfdRsnCd' => (string)($sale['zra_rfd_rsn_cd'] ?? ''),
                    ];
                }
                $ackedSales[$uuid] = ['online_id' => $onlineSaleId];
            }

            // 3. Inventory Logs (apply net delta once per sync_uuid)
            foreach ((array)($payload['inventory_logs'] ?? []) as $log) {
                $uuid = trim((string)($log['sync_uuid'] ?? ''));
                if ($uuid === '') {
                    continue;
                }
                $chk = $this->pdo->prepare('SELECT id FROM inventory_logs WHERE sync_uuid = ? LIMIT 1');
                $chk->execute([$uuid]);
                if (!$chk->fetchColumn()) {
                    $pid = (int)($log['product_id'] ?? 0);
                    $locId = (int)($log['location_id'] ?? 0);
                    $delta = (float)($log['change_qty'] ?? 0);
                    if ($pid > 0 && $locId > 0) {
                        $invStmt = $this->pdo->prepare('SELECT id, quantity FROM inventory WHERE product_id = ? AND location_id = ? FOR UPDATE');
                        $invStmt->execute([$pid, $locId]);
                        $inv = $invStmt->fetch(PDO::FETCH_ASSOC);
                        if ($inv) {
                            $after = max(0.0, (float)$inv['quantity'] + $delta);
                            $this->pdo->prepare('UPDATE inventory SET quantity = ? WHERE id = ?')->execute([$after, (int)$inv['id']]);
                        } else {
                            $after = max(0.0, $delta);
                            $this->pdo->prepare('INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?)')->execute([$pid, $locId, $after]);
                        }
                        $this->pdo->prepare(
                            'INSERT INTO inventory_logs (product_id, location_id, user_id, change_qty, after_qty, action_type, reason, created_at, sync_uuid, synced_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                        )->execute([
                            $pid,
                            $locId,
                            (int)($log['user_id'] ?? 1),
                            $delta,
                            $after,
                            (string)($log['action_type'] ?? 'sale'),
                            $log['reason'] ?? null,
                            $log['created_at'] ?? date('Y-m-d H:i:s'),
                            $uuid,
                        ]);
                    }
                }
                $ackedLogs[] = $uuid;
            }

            // 4. Tips
            foreach ((array)($payload['sale_tips'] ?? []) as $tip) {
                $uuid = trim((string)($tip['sync_uuid'] ?? ''));
                if ($uuid === '') {
                    continue;
                }
                $chk = $this->pdo->prepare('SELECT id FROM sale_tips WHERE sync_uuid = ? LIMIT 1');
                $chk->execute([$uuid]);
                if (!$chk->fetchColumn()) {
                    $saleId = 0;
                    if (!empty($tip['sale_sync_uuid'])) {
                        $sStmt = $this->pdo->prepare('SELECT id FROM sales WHERE sync_uuid = ? LIMIT 1');
                        $sStmt->execute([(string)$tip['sale_sync_uuid']]);
                        $saleId = (int)$sStmt->fetchColumn();
                    }
                    $shiftId = null;
                    if (!empty($tip['shift_sync_uuid'])) {
                        $shStmt = $this->pdo->prepare('SELECT id FROM shifts WHERE sync_uuid = ? LIMIT 1');
                        $shStmt->execute([(string)$tip['shift_sync_uuid']]);
                        $shiftId = (int)$shStmt->fetchColumn() ?: null;
                    }
                    if ($saleId > 0) {
                        $this->pdo->prepare(
                            'INSERT INTO sale_tips (sale_id, shift_id, location_id, staff_user_id, recorded_by, amount, payment_method, note, created_at, sync_uuid, synced_at)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                        )->execute([
                            $saleId,
                            $shiftId,
                            (int)($tip['location_id'] ?? 1),
                            !empty($tip['staff_user_id']) ? (int)$tip['staff_user_id'] : null,
                            (int)($tip['recorded_by'] ?? 1),
                            (float)($tip['amount'] ?? 0),
                            (string)($tip['payment_method'] ?? 'Cash'),
                            $tip['note'] ?? null,
                            $tip['created_at'] ?? date('Y-m-d H:i:s'),
                            $uuid,
                        ]);
                    }
                }
                $ackedTips[] = $uuid;
            }

            // 5. Expenses
            foreach ((array)($payload['expenses'] ?? []) as $exp) {
                $uuid = trim((string)($exp['sync_uuid'] ?? ''));
                if ($uuid === '') {
                    continue;
                }
                $chk = $this->pdo->prepare('SELECT id FROM expenses WHERE sync_uuid = ? LIMIT 1');
                $chk->execute([$uuid]);
                if (!$chk->fetchColumn()) {
                    $shiftId = 0;
                    if (!empty($exp['shift_sync_uuid'])) {
                        $shStmt = $this->pdo->prepare('SELECT id FROM shifts WHERE sync_uuid = ? LIMIT 1');
                        $shStmt->execute([(string)$exp['shift_sync_uuid']]);
                        $shiftId = (int)$shStmt->fetchColumn();
                    }
                    $this->pdo->prepare(
                        'INSERT INTO expenses (location_id, user_id, shift_id, amount, reason, description, created_at, sync_uuid, synced_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    )->execute([
                        (int)($exp['location_id'] ?? 1),
                        (int)($exp['user_id'] ?? 1),
                        $shiftId,
                        (float)($exp['amount'] ?? 0),
                        (string)($exp['reason'] ?? 'Expense'),
                        (string)($exp['description'] ?? 'Expense'),
                        $exp['created_at'] ?? date('Y-m-d H:i:s'),
                        $uuid,
                    ]);
                }
                $ackedExpenses[] = $uuid;
            }

            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }

        // Fiscalize newly pushed paid sales on the Online HQ Master VSDC after commit
        foreach ($toFiscalize as $uuid => $meta) {
            $onlineSaleId = (int)$meta['id'];
            $opts = $meta['rfdRsnCd'] !== '' ? ['rfdRsnCd' => $meta['rfdRsnCd']] : [];
            try {
                fiscalizePaidSale($this->pdo, $onlineSaleId, $opts);
            } catch (\Throwable $e) {
            }
            $zStmt = $this->pdo->prepare(
                'SELECT zra_rcpt_no, zra_intrl_data, zra_rcpt_sign, zra_vsdc_rcpt_pbct_date, zra_sdc_id, zra_mrc_no, zra_qr_code_url, zra_cis_invc_no, zra_status, zra_last_erro
                 FROM sales WHERE id = ?'
            );
            $zStmt->execute([$onlineSaleId]);
            $ackedSales[$uuid]['zra'] = $zStmt->fetch(PDO::FETCH_ASSOC) ?: [];
        }

        $this->recordState('ok', null, true, false);

        return [
            'status' => 'ok',
            'acked_shifts' => $ackedShifts,
            'acked_sales' => $ackedSales,
            'acked_inventory_logs' => $ackedLogs,
            'acked_sale_tips' => $ackedTips,
            'acked_expenses' => $ackedExpenses,
        ];
    }

    /**
     * Runs on Online HQ: builds the master catalog, staff, settings, web-order, and media snapshot for an Edge node.
     */
    public function buildPullPayload(array $req): array
    {
        $this->ensureLocalUuids();
        $locId = (int)($req['location_id'] ?? 0);

        $categories = $this->pdo->query('SELECT * FROM categories ORDER BY COALESCE(parent_id, 0) ASC, id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $products = $this->pdo->query('SELECT * FROM products ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $recipes = $this->pdo->query('SELECT * FROM product_recipes ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $surcharges = $this->pdo->query('SELECT * FROM surcharge_reasons ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $locations = $this->pdo->query('SELECT * FROM locations ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $tables = $this->pdo->query('SELECT * FROM restaurant_tables ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $users = $this->pdo->query(
            'SELECT id, username, full_name, password_hash, role, location_id, created_at, force_password_change, is_active, session_rev, approval_code_hash, totp_secret, totp_recovery
             FROM users ORDER BY id ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $safeSettingKeys = [
            'business_name', 'license_tier', 'lockout_date', 'receipt_header', 'receipt_footer',
            'theme_color', 'theme_accent', 'theme_cart', 'vsias_enabled', 'logo_path',
            'fx_enabled', 'fx_display', 'fx_refresh_minutes', 'fx_manual_rates', 'fx_cached_rates', 'fx_cached_at',
            'vsdc_taxprNm', 'vsdc_bhfNm', 'vsdc_sdcId', 'vsdc_mrcNo', 'vsdc_default_unspsc',
        ];
        $placeholders = implode(',', array_fill(0, count($safeSettingKeys), '?'));
        $sStmt = $this->pdo->prepare("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ($placeholders)");
        $sStmt->execute($safeSettingKeys);
        $settingsRows = $sStmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

        // Media files referenced by products or logo
        $mediaFiles = [];
        if (!empty($settingsRows['logo_path'])) {
            $u = Media::url((string)$settingsRows['logo_path']);
            if ($u !== '') {
                $mediaFiles[$u] = $u;
            }
        }
        foreach ($products as $p) {
            if (!empty($p['image_path'])) {
                $u = Media::url((string)$p['image_path']);
                if ($u !== '') {
                    $mediaFiles[$u] = $u;
                }
            }
        }

        // Paid web orders for the store (last 7 days) so KDS and POS on the Edge node see them
        $webSql = "SELECT * FROM sales WHERE (order_source = 'shop' OR payment_method = 'Online') AND payment_status = 'paid' AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        $webParams = [];
        if ($locId > 0) {
            $webSql .= ' AND location_id = ?';
            $webParams[] = $locId;
        }
        $webSql .= ' ORDER BY id DESC LIMIT 60';
        $wStmt = $this->pdo->prepare($webSql);
        $wStmt->execute($webParams);
        $webOrders = $wStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $itStmt = $this->pdo->prepare('SELECT * FROM sale_items WHERE sale_id = ? ORDER BY id ASC');
        foreach ($webOrders as &$wo) {
            $itStmt->execute([(int)$wo['id']]);
            $wo['items'] = $itStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        unset($wo);

        // Baseline inventory for products that have no local Edge row yet
        $inventory = $this->pdo->query('SELECT product_id, location_id, quantity FROM inventory')->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $this->recordState('ok', null, false, true);

        return [
            'categories' => $categories,
            'products' => $products,
            'product_recipes' => $recipes,
            'surcharge_reasons' => $surcharges,
            'locations' => $locations,
            'restaurant_tables' => $tables,
            'users' => $users,
            'settings' => $settingsRows,
            'media_files' => array_values($mediaFiles),
            'web_orders' => $webOrders,
            'inventory_seed' => $inventory,
        ];
    }

    /**
     * Runs on Edge node: applies master catalog, staff, settings, web orders, and downloads missing media.
     */
    private function applyPullPayload(array $data): void
    {
        if (!$data) {
            return;
        }
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 0');

            foreach ((array)($data['locations'] ?? []) as $loc) {
                $this->pdo->prepare(
                    'INSERT INTO locations (id, name, type, can_sell, can_receive_from_vendor, address, phone, can_receive, google_review_url)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), type = VALUES(type), can_sell = VALUES(can_sell), can_receive_from_vendor = VALUES(can_receive_from_vendor), address = VALUES(address), phone = VALUES(phone), can_receive = VALUES(can_receive), google_review_url = VALUES(google_review_url)'
                )->execute([
                    (int)$loc['id'],
                    (string)$loc['name'],
                    (string)($loc['type'] ?? 'store'),
                    (int)($loc['can_sell'] ?? 1),
                    (int)($loc['can_receive_from_vendor'] ?? 0),
                    $loc['address'] ?? '',
                    $loc['phone'] ?? '',
                    (int)($loc['can_receive'] ?? 0),
                    $loc['google_review_url'] ?? null,
                ]);
            }

            foreach ((array)($data['categories'] ?? []) as $cat) {
                $this->pdo->prepare(
                    'INSERT INTO categories (id, name, description, parent_id, type)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), parent_id = VALUES(parent_id), type = VALUES(type)'
                )->execute([
                    (int)$cat['id'],
                    (string)$cat['name'],
                    $cat['description'] ?? null,
                    !empty($cat['parent_id']) ? (int)$cat['parent_id'] : null,
                    (string)($cat['type'] ?? 'other'),
                ]);
            }

            foreach ((array)($data['products'] ?? []) as $p) {
                $this->pdo->prepare(
                    'INSERT INTO products (id, name, sku, price, cost_price, unit, category_id, is_active, type, is_open_price, tax_class, unspsc_code, zra_synced, zra_item_cd, is_sellable, image_path)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), sku = VALUES(sku), price = VALUES(price), cost_price = VALUES(cost_price), unit = VALUES(unit), category_id = VALUES(category_id), is_active = VALUES(is_active), type = VALUES(type), is_open_price = VALUES(is_open_price), tax_class = VALUES(tax_class), unspsc_code = VALUES(unspsc_code), zra_synced = VALUES(zra_synced), zra_item_cd = VALUES(zra_item_cd), is_sellable = VALUES(is_sellable), image_path = COALESCE(VALUES(image_path), image_path)'
                )->execute([
                    (int)$p['id'],
                    (string)$p['name'],
                    $p['sku'] ?? null,
                    (float)($p['price'] ?? 0),
                    (float)($p['cost_price'] ?? 0),
                    (string)($p['unit'] ?? 'unit'),
                    !empty($p['category_id']) ? (int)$p['category_id'] : null,
                    (int)($p['is_active'] ?? 1),
                    (string)($p['type'] ?? 'item'),
                    (int)($p['is_open_price'] ?? 0),
                    (string)($p['tax_class'] ?? 'A'),
                    $p['unspsc_code'] ?? null,
                    (int)($p['zra_synced'] ?? 0),
                    $p['zra_item_cd'] ?? null,
                    (int)($p['is_sellable'] ?? 1),
                    $p['image_path'] ?? null,
                ]);
            }

            if (isset($data['product_recipes']) && is_array($data['product_recipes'])) {
                $this->pdo->exec('DELETE FROM product_recipes');
                $insRec = $this->pdo->prepare('INSERT INTO product_recipes (id, parent_product_id, ingredient_product_id, quantity) VALUES (?, ?, ?, ?)');
                foreach ($data['product_recipes'] as $r) {
                    $insRec->execute([
                        (int)$r['id'],
                        (int)$r['parent_product_id'],
                        (int)$r['ingredient_product_id'],
                        (float)$r['quantity'],
                    ]);
                }
            }

            foreach ((array)($data['surcharge_reasons'] ?? []) as $sr) {
                $this->pdo->prepare(
                    'INSERT INTO surcharge_reasons (id, name, default_amount, default_percent, is_active, sort_order)
                     VALUES (?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE name = VALUES(name), default_amount = VALUES(default_amount), default_percent = VALUES(default_percent), is_active = VALUES(is_active), sort_order = VALUES(sort_order)'
                )->execute([
                    (int)$sr['id'],
                    (string)$sr['name'],
                    (float)($sr['default_amount'] ?? 0),
                    (float)($sr['default_percent'] ?? 0),
                    (int)($sr['is_active'] ?? 1),
                    (int)($sr['sort_order'] ?? 0),
                ]);
            }

            foreach ((array)($data['restaurant_tables'] ?? []) as $tb) {
                $this->pdo->prepare(
                    'INSERT INTO restaurant_tables (id, location_id, zone_name, table_name, capacity)
                     VALUES (?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE location_id = VALUES(location_id), zone_name = VALUES(zone_name), table_name = VALUES(table_name), capacity = VALUES(capacity)'
                )->execute([
                    (int)$tb['id'],
                    (int)($tb['location_id'] ?? 0),
                    (string)($tb['zone_name'] ?? 'Main Dining'),
                    (string)$tb['table_name'],
                    (int)($tb['capacity'] ?? 4),
                ]);
            }

            foreach ((array)($data['users'] ?? []) as $u) {
                $this->pdo->prepare(
                    'INSERT INTO users (id, username, full_name, password_hash, role, location_id, created_at, force_password_change, is_active, session_rev, approval_code_hash, totp_secret, totp_recovery)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                     ON DUPLICATE KEY UPDATE username = VALUES(username), full_name = VALUES(full_name), password_hash = VALUES(password_hash), role = VALUES(role), location_id = VALUES(location_id), force_password_change = VALUES(force_password_change), is_active = VALUES(is_active), session_rev = VALUES(session_rev), approval_code_hash = VALUES(approval_code_hash), totp_secret = VALUES(totp_secret), totp_recovery = VALUES(totp_recovery)'
                )->execute([
                    (int)$u['id'],
                    (string)$u['username'],
                    $u['full_name'] ?? null,
                    (string)$u['password_hash'],
                    (string)($u['role'] ?? 'cashier'),
                    !empty($u['location_id']) ? (int)$u['location_id'] : null,
                    $u['created_at'] ?? date('Y-m-d H:i:s'),
                    (int)($u['force_password_change'] ?? 0),
                    (int)($u['is_active'] ?? 1),
                    (int)($u['session_rev'] ?? 0),
                    $u['approval_code_hash'] ?? null,
                    $u['totp_secret'] ?? null,
                    $u['totp_recovery'] ?? null,
                ]);
            }

            if (!empty($data['settings']) && is_array($data['settings'])) {
                $sUpd = $this->pdo->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
                foreach ($data['settings'] as $k => $v) {
                    $sUpd->execute([(string)$k, (string)$v]);
                }
            }

            // Seed missing inventory rows (only when there are no pending local stock deltas)
            $hasUnsyncedLogs = (int)$this->pdo->query('SELECT COUNT(*) FROM inventory_logs WHERE synced_at IS NULL')->fetchColumn() > 0;
            if (!$hasUnsyncedLogs && !empty($data['inventory_seed']) && is_array($data['inventory_seed'])) {
                $invUpd = $this->pdo->prepare(
                    'INSERT INTO inventory (product_id, location_id, quantity) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
                );
                foreach ($data['inventory_seed'] as $inv) {
                    $invUpd->execute([(int)$inv['product_id'], (int)$inv['location_id'], (float)$inv['quantity']]);
                }
            }

            // Paid Web Orders from Online HQ -> Edge POS/KDS
            foreach ((array)($data['web_orders'] ?? []) as $wo) {
                $uuid = trim((string)($wo['sync_uuid'] ?? ''));
                if ($uuid === '') {
                    continue;
                }
                $chk = $this->pdo->prepare('SELECT id, status FROM sales WHERE sync_uuid = ? LIMIT 1');
                $chk->execute([$uuid]);
                $existing = $chk->fetch(PDO::FETCH_ASSOC);
                if (!$existing) {
                    $ins = $this->pdo->prepare(
                        'INSERT INTO sales (location_id, user_id, subtotal, total_amount, final_total, payment_method, status, created_at, payment_status, customer_name, amount_tendered, split_group_id, order_source, order_type, order_notes, customer_id, customer_phone, zra_rcpt_no, zra_intrl_data, zra_rcpt_sign, zra_vsdc_rcpt_pbct_date, zra_sdc_id, zra_mrc_no, zra_qr_code_url, zra_status, zra_cis_invc_no, sync_uuid, synced_at)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
                    );
                    $ins->execute([
                        (int)($wo['location_id'] ?? 1),
                        (int)($wo['user_id'] ?? 1),
                        (float)($wo['subtotal'] ?? 0),
                        (float)($wo['total_amount'] ?? 0),
                        (float)($wo['final_total'] ?? 0),
                        (string)($wo['payment_method'] ?? 'Online'),
                        (string)($wo['status'] ?? 'pending'),
                        $wo['created_at'] ?? date('Y-m-d H:i:s'),
                        (string)($wo['payment_status'] ?? 'paid'),
                        (string)($wo['customer_name'] ?? 'Guest'),
                        (float)($wo['amount_tendered'] ?? 0),
                        $wo['split_group_id'] ?? null,
                        (string)($wo['order_source'] ?? 'shop'),
                        $wo['order_type'] ?? null,
                        $wo['order_notes'] ?? null,
                        !empty($wo['customer_id']) ? (int)$wo['customer_id'] : null,
                        $wo['customer_phone'] ?? null,
                        $wo['zra_rcpt_no'] ?? null,
                        $wo['zra_intrl_data'] ?? null,
                        $wo['zra_rcpt_sign'] ?? null,
                        $wo['zra_vsdc_rcpt_pbct_date'] ?? null,
                        $wo['zra_sdc_id'] ?? null,
                        $wo['zra_mrc_no'] ?? null,
                        $wo['zra_qr_code_url'] ?? null,
                        (string)($wo['zra_status'] ?? 'pending'),
                        $wo['zra_cis_invc_no'] ?? null,
                        $uuid,
                    ]);
                    $localSaleId = (int)$this->pdo->lastInsertId();
                    $insIt = $this->pdo->prepare(
                        'INSERT INTO sale_items (sale_id, product_id, quantity, price, price_at_sale, status, fulfillment_status)
                         VALUES (?, ?, ?, ?, ?, ?, ?)'
                    );
                    foreach ((array)($wo['items'] ?? []) as $it) {
                        $insIt->execute([
                            $localSaleId,
                            (int)$it['product_id'],
                            (float)$it['quantity'],
                            (float)$it['price'],
                            (float)($it['price_at_sale'] ?? $it['price']),
                            (string)($it['status'] ?? 'pending'),
                            (string)($it['fulfillment_status'] ?? 'uncollected'),
                        ]);
                    }
                }
            }

            $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            $this->pdo->commit();
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            try {
                $this->pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
            } catch (\Throwable $ignored) {
            }
            throw $e;
        }

        // Download any missing product or branding media files outside the DB transaction
        $this->downloadMissingMedia((array)($data['media_files'] ?? []));
    }

    private function downloadMissingMedia(array $relativePaths): void
    {
        $base = $this->onlineUrl();
        if ($base === '') {
            return;
        }
        $pubRoot = dirname(__DIR__, 2) . '/public/';
        foreach ($relativePaths as $rel) {
            $rel = str_replace('\\', '/', trim((string)$rel));
            if (!preg_match('#^uploads/[a-z0-9_-]+/[a-z0-9][a-z0-9_-]{0,80}\.(jpg|png|webp|gif)$#', $rel)) {
                continue;
            }
            $localFile = $pubRoot . $rel;
            if (is_file($localFile) && filesize($localFile) > 0) {
                continue;
            }
            $dir = dirname($localFile);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $url = $base . '/' . ltrim($rel, '/');
            $ctx = stream_context_create([
                'http' => [
                    'method' => 'GET',
                    'timeout' => 8,
                    'ignore_errors' => true,
                ],
            ]);
            $bytes = @file_get_contents($url, false, $ctx);
            if ($bytes !== false && strlen($bytes) > 64 && strlen($bytes) <= 2500000) {
                @file_put_contents($localFile, $bytes);
            }
        }
    }

    private function postToOnline(string $action, array $data, int $timeout = 15): array
    {
        $endpoint = $this->onlineUrl() . '/api/edge_sync.php?action=' . urlencode($action);
        $body = json_encode($data, JSON_UNESCAPED_UNICODE);
        $ctx = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\n"
                    . 'Authorization: Bearer ' . $this->token() . "\r\n",
                'content' => (string)$body,
                'timeout' => $timeout,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($endpoint, false, $ctx);
        if ($raw === false || $raw === '') {
            throw new \RuntimeException('Could not reach Online HQ at ' . $this->onlineUrl());
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \RuntimeException('Unexpected response from Online HQ.');
        }
        return $decoded;
    }
}
