<?php

namespace App\Services;

use App\Helpers\Movement;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\ReceivingBay;
use App\Models\Transfer;
use PDO;
use ZraVsdcClient;

final class VsiasBridge
{
    public function __construct(private PDO $pdo)
    {
        $this->ensureSyncLog();
        ReceivingBay::ensureSchema();
        (new ZraVsdcClient($this->pdo))->getConfig();
        if (!defined('LICENSE_TIER')) {
            define('LICENSE_TIER', $this->settingsTier());
        }
    }

    private function settingsTier(): string
    {
        try {
            $stmt = $this->pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'license_tier' LIMIT 1");
            $stmt->execute();
            $tier = strtolower(trim((string)($stmt->fetchColumn() ?: 'lite')));
            return $tier !== '' ? $tier : 'lite';
        } catch (\Throwable $e) {
            return 'lite';
        }
    }

    public function catalog(): array
    {
        $products = $this->pdo->query(
            "SELECT p.id, p.sku, p.name, p.unit, p.tax_class, p.unspsc_code, p.zra_synced, p.zra_item_cd,
                    p.is_active, p.category_id, c.name AS category_name
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.type = 'item'
             ORDER BY p.name ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $locations = $this->pdo->query(
            "SELECT id, name, type, can_sell FROM locations ORDER BY name ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $categories = $this->pdo->query(
            "SELECT id, name, type FROM categories ORDER BY name ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $units = $this->pdo->query(
            "SELECT DISTINCT unit FROM products WHERE unit IS NOT NULL AND unit != '' ORDER BY unit ASC"
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $units = array_values(array_unique(array_merge(['unit', 'Packs', 'Boxes', 'Units'], $units)));

        return [
            'products' => $products,
            'locations' => $locations,
            'categories' => $categories,
            'units' => $units,
        ];
    }

    /**
     * @param array<string,mixed> $payload
     * @return array<string,mixed>
     */
    public function ingest(array $payload): array
    {
        $tracking = trim((string)($payload['tracking_code'] ?? ''));
        if ($tracking === '') {
            return ['status' => 'error', 'message' => 'tracking_code is required', 'lines' => []];
        }

        $type = strtolower(trim((string)($payload['type'] ?? 'inbound')));
        if (in_array($type, ['supplier_inbound', 'in'], true)) {
            $type = 'inbound';
        }
        if (in_array($type, ['clinic_outbound', 'out'], true)) {
            $type = 'outbound';
        }
        if (!in_array($type, ['inbound', 'outbound'], true)) {
            $type = 'inbound';
        }

        $dest = $this->resolveLocation(
            (int)($payload['destination_location_id'] ?? 0),
            (string)($payload['destination_location'] ?? '')
        );
        if ($type === 'inbound' && $dest && !Movement::isBackOfHouse($dest)) {
            $wid = $this->warehouseId();
            $wh = $wid ? Location::find($wid) : null;
            $dest = $wh ?: $dest;
        }
        if ($type === 'inbound' && !$dest) {
            $wid = $this->warehouseId();
            $dest = $wid ? Location::find($wid) : null;
        }
        $source = $this->resolveLocation(
            (int)($payload['source_location_id'] ?? 0),
            (string)($payload['source_location'] ?? '')
        );
        $categoryName = trim((string)($payload['category'] ?? ''));
        $categoryId = $categoryName !== '' && strcasecmp($categoryName, 'Other') !== 0
            ? Category::findIdByName($categoryName)
            : null;

        $userId = $this->systemUserId();
        $vendorId = $this->inspectionVendorId();
        $enterprise = $this->isEnterprise();
        $linesOut = [];
        $inboundBook = [];
        $hasFieldError = false;
        $hasFail = false;

        foreach (array_values($payload['items'] ?? []) as $index => $item) {
            if (!is_array($item)) {
                continue;
            }
            $result = $this->processLine(
                $tracking,
                $type,
                $index,
                $item,
                $source,
                $dest,
                $categoryId,
                $userId,
                $enterprise
            );
            $linesOut[] = $result;
            if (!empty($result['field'])) {
                $hasFieldError = true;
            }
            if (!empty($result['error']) && empty($result['booked']) && empty($result['skipped'])) {
                $hasFail = true;
            }
            if (!empty($result['book_inbound'])) {
                $inboundBook[] = $result;
            }
        }

        if ($hasFieldError) {
            return [
                'status' => 'error',
                'message' => 'One or more lines need a UNSPSC code before inventory can be booked.',
                'tracking_code' => $tracking,
                'lines' => $linesOut,
            ];
        }

        if ($type === 'inbound' && $inboundBook) {
            $this->dockInboundBatch(
                $tracking,
                $inboundBook,
                (string)($payload['source_location'] ?? ''),
                $dest,
                $vendorId,
                $linesOut
            );
        }

        $pending = 0;
        $booked = 0;
        $docked = 0;
        $custom = 0;
        foreach ($linesOut as $line) {
            if (!empty($line['booked'])) {
                $booked++;
            }
            if (!empty($line['docked'])) {
                $docked++;
            }
            if (!empty($line['is_unregistered'])) {
                $custom++;
            }
            if (!empty($line['is_unregistered'])) {
                continue;
            }
            if (!empty($line['error']) || (empty($line['skipped']) && empty($line['zra_synced']) && $enterprise)) {
                if (empty($line['skipped'])) {
                    $pending++;
                }
            }
        }

        $status = 'success';
        $message = $type === 'inbound'
            ? 'Docked in the smartTrade receiving bay. Send it to Goods Received from there.'
            : 'Handoff ingested.';
        if ($custom > 0 && $type === 'inbound') {
            $message = 'Docked in the receiving bay. Custom items are flagged there for catalog registration before Goods Received.';
        }
        if ($hasFail || $pending > 0) {
            $status = 'partial';
            $message = $type === 'inbound'
                ? 'Docked with pending fiscal lines. Finish them from the receiving bay, then send to Goods Received.'
                : 'Handoff saved with pending fiscal or stock lines. Re-approve to retry.';
        }
        if ($booked === 0 && $docked === 0 && $hasFail) {
            $status = 'error';
            $message = 'No lines were docked. Check ZRA, UNSPSC, and location details.';
        }

        return [
            'status' => $status,
            'message' => $message,
            'tracking_code' => $tracking,
            'lines' => $linesOut,
        ];
    }

    /**
     * @param array<string,mixed> $item
     * @param ?array<string,mixed> $source
     * @param ?array<string,mixed> $dest
     * @return array<string,mixed>
     */
    private function processLine(
        string $tracking,
        string $type,
        int $index,
        array $item,
        ?array $source,
        ?array $dest,
        ?int $categoryId,
        int $userId,
        bool $enterprise
    ): array {
        $name = trim((string)($item['item_name'] ?? $item['name'] ?? ''));
        $condition = strtolower(trim((string)($item['condition_status'] ?? 'intact')));
        $qty = (float)($item['quantity_received'] ?? 0);
        $lineKey = $this->lineKey($index, $item);
        $prior = $this->syncRow($tracking, $lineKey);

        $base = [
            'line_index' => $index,
            'line_key' => $lineKey,
            'item_name' => $name,
            'product_id' => $prior['product_id'] ?? null,
            'zra_synced' => false,
            'booked' => !empty($prior['booked']),
            'skipped' => false,
            'error' => null,
            'field' => null,
            'grv_id' => $prior['grv_id'] ?? null,
            'transfer_id' => $prior['transfer_id'] ?? null,
            'dock_id' => $prior['dock_id'] ?? null,
            'docked' => !empty($prior['dock_id']) && empty($prior['booked']),
        ];

        if (in_array($condition, ['compromised', 'missing'], true) || $qty <= 0) {
            $base['skipped'] = true;
            $base['error'] = 'Skipped: compromised, missing, or zero received quantity.';
            $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => 'skipped']);
            return $base;
        }

        if (!empty($prior['booked']) && (!$enterprise || ($prior['zra_status'] ?? '') === 'ok')) {
            $base['zra_synced'] = !$enterprise || ($prior['zra_status'] ?? '') === 'ok';
            return $base;
        }
        if (!empty($prior['dock_id']) && empty($prior['booked'])) {
            $base['zra_synced'] = !$enterprise || ($prior['zra_status'] ?? '') === 'ok';
            $base['docked'] = true;
            $base['dock_id'] = (int)$prior['dock_id'];
            return $base;
        }

        $matched = $this->matchProduct($item);
        $unspsc = preg_replace('/\D/', '', (string)($item['unspsc_code'] ?? '')) ?? '';
        $taxClass = strtoupper(trim((string)($item['tax_class'] ?? '')));
        if (!in_array($taxClass, ['A', 'B', 'C', 'D'], true)) {
            $taxClass = 'A';
        }

        if (!$matched) {
            if ($type === 'inbound') {
                $receiveLoc = $dest;
                if (!$receiveLoc) {
                    $wid = $this->warehouseId();
                    $receiveLoc = $wid ? Location::find($wid) : null;
                }
                if (!$receiveLoc) {
                    $base['error'] = 'Destination location is required.';
                    $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => 'need_register']);
                    return $base;
                }
                $base['book_inbound'] = true;
                $base['is_unregistered'] = true;
                $base['product_id'] = null;
                $base['receive_location_id'] = (int)$receiveLoc['id'];
                $base['qty'] = $qty;
                $base['cost'] = (float)($item['unit_cost'] ?? $item['cost'] ?? 0);
                $base['sku'] = trim((string)($item['sku'] ?? '')) ?: null;
                $base['unspsc_code'] = $unspsc !== '' ? $unspsc : null;
                $base['tax_class'] = $taxClass;
                $base['category_id'] = $categoryId;
                $base['batch_number'] = $item['batch_number'] ?? null;
                $base['expiry_date'] = $item['expiry_date'] ?? null;
                $base['unit'] = trim((string)($item['unit'] ?? 'Units')) ?: 'Units';
                $base['item_name'] = $name !== '' ? $name : ('Custom item ' . ($index + 1));
                $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => 'need_register']);
                return $base;
            }
            if ($unspsc === '') {
                $base['field'] = 'unspsc_code';
                $base['error'] = 'Collect a UNSPSC code before registering this new item.';
                $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => 'need_unspsc']);
                return $base;
            }
            $unit = trim((string)($item['unit'] ?? 'unit')) ?: 'unit';
            $sku = trim((string)($item['sku'] ?? ''));
            $sku = $sku !== '' ? $sku : null;
            $locId = (int)($dest['id'] ?? $source['id'] ?? $this->warehouseId());
            try {
                $productId = Product::create(
                    $name !== '' ? $name : ('Inspection item ' . ($index + 1)),
                    $categoryId,
                    0,
                    (float)($item['unit_cost'] ?? $item['cost'] ?? 0),
                    $unit,
                    $sku,
                    $taxClass,
                    $unspsc,
                    $locId
                );
            } catch (\Throwable $e) {
                $base['error'] = 'Could not create product: ' . $e->getMessage();
                $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => 'error']);
                return $base;
            }
            $matched = Product::movementContext($productId);
        } else {
            $this->backfillClassification((int)$matched['id'], $unspsc, $taxClass);
            $matched = Product::movementContext((int)$matched['id']);
        }

        $base['product_id'] = (int)$matched['id'];
        $productUnspsc = preg_replace('/\D/', '', (string)($matched['unspsc_code'] ?? $unspsc)) ?? '';

        $zraOk = !empty($matched['zra_synced']) && !empty($matched['zra_item_cd']);
        if ($enterprise && !$zraOk) {
            if ($productUnspsc === '') {
                $base['field'] = 'unspsc_code';
                $base['error'] = 'Assign a UNSPSC code before registering the item with ZRA.';
                $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => 'need_unspsc']);
                return $base;
            }
            try {
                $res = (new ZraVsdcClient($this->pdo))->saveItem($matched);
                if (($res['resultCd'] ?? '') === '000') {
                    $zraOk = true;
                    $matched = Product::movementContext((int)$matched['id']) ?: $matched;
                } else {
                    $base['error'] = $res['resultMsg'] ?? 'ZRA registration did not complete.';
                    $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => 'fail']);
                }
            } catch (\Throwable $e) {
                $base['error'] = 'ZRA registration failed: ' . $e->getMessage();
                $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => 'fail']);
            }
        }

        if (!$enterprise) {
            $zraOk = true;
        }
        $base['zra_synced'] = $zraOk;

        if (!empty($prior['booked'])) {
            $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => $zraOk ? 'ok' : 'fail', 'booked' => 1]);
            return $base;
        }

        if ($type === 'inbound') {
            $receiveLoc = $dest;
            if (!$receiveLoc) {
                $wid = $this->warehouseId();
                $receiveLoc = $wid ? Location::find($wid) : null;
            }
            if (!$receiveLoc) {
                $base['error'] = ($base['error'] ? $base['error'] . ' ' : '') . 'Destination location is required.';
                $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => $zraOk ? 'ok' : 'fail']);
                return $base;
            }
            $base['book_inbound'] = true;
            $base['receive_location_id'] = (int)$receiveLoc['id'];
            $base['qty'] = $qty;
            $base['cost'] = (float)($item['unit_cost'] ?? $item['cost'] ?? 0);
            $base['sku'] = $item['sku'] ?? ($matched['sku'] ?? null);
            $base['unspsc_code'] = $productUnspsc !== '' ? $productUnspsc : ($item['unspsc_code'] ?? null);
            $base['batch_number'] = $item['batch_number'] ?? null;
            $base['expiry_date'] = $item['expiry_date'] ?? null;
            $base['unit'] = $item['unit'] ?? ($matched['unit'] ?? 'Units');
            $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => $zraOk ? 'ok' : 'fail']);
            return $base;
        }

        if (!$source || !$dest) {
            $base['error'] = ($base['error'] ? $base['error'] . ' ' : '') . 'Source and destination locations are required for outbound.';
            $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => $zraOk ? 'ok' : 'fail']);
            return $base;
        }
        $move = Movement::transfer($matched, $source, $dest);
        if (!$move['ok']) {
            $base['error'] = ($base['error'] ? $base['error'] . ' ' : '') . $move['reason'];
            $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => $zraOk ? 'ok' : 'fail']);
            return $base;
        }
        try {
            $tid = Transfer::executeImmediate((int)$source['id'], (int)$dest['id'], (int)$matched['id'], $qty, $userId);
            $base['booked'] = true;
            $base['transfer_id'] = $tid;
            $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => $zraOk ? 'ok' : 'fail', 'booked' => 1, 'transfer_id' => $tid]);
        } catch (\Throwable $e) {
            $base['error'] = ($base['error'] ? $base['error'] . ' ' : '') . $e->getMessage();
            $this->upsertSync($tracking, $lineKey, $base + ['zra_status' => $zraOk ? 'ok' : 'fail']);
        }
        return $base;
    }

    /**
     * @param list<array<string,mixed>> $inboundBook
     * @param list<array<string,mixed>> $linesOut
     */
    private function dockInboundBatch(
        string $tracking,
        array $inboundBook,
        string $sourceName,
        ?array $dest,
        int $vendorId,
        array &$linesOut
    ): void {
        $intended = (int)($dest['id'] ?? 0) ?: null;
        if (!$intended) {
            foreach ($inboundBook as $row) {
                $locId = (int)($row['receive_location_id'] ?? 0);
                if ($locId > 0) {
                    $intended = $locId;
                    break;
                }
            }
        }
        try {
            $dockId = ReceivingBay::dock($tracking, $sourceName, $intended, $vendorId, $inboundBook);
            foreach ($linesOut as &$line) {
                foreach ($inboundBook as $row) {
                    if (($line['line_key'] ?? '') === ($row['line_key'] ?? '')) {
                        $line['docked'] = true;
                        $line['dock_id'] = $dockId;
                        unset($line['book_inbound'], $line['receive_location_id'], $line['qty'], $line['cost']);
                        $this->upsertSync($tracking, (string)$line['line_key'], $line + [
                            'zra_status' => !empty($line['zra_synced']) ? 'ok' : 'fail',
                            'booked' => 0,
                            'dock_id' => $dockId,
                        ]);
                    }
                }
            }
            unset($line);
        } catch (\Throwable $e) {
            foreach ($linesOut as &$line) {
                foreach ($inboundBook as $row) {
                    if (($line['line_key'] ?? '') === ($row['line_key'] ?? '')) {
                        $line['error'] = trim(($line['error'] ?? '') . ' ' . $e->getMessage());
                        unset($line['book_inbound']);
                    }
                }
            }
            unset($line);
        }
    }

    /**
     * @param array<string,mixed> $item
     * @return ?array<string,mixed>
     */
    private function matchProduct(array $item): ?array
    {
        $pid = (int)($item['product_id'] ?? 0);
        if ($pid > 0) {
            $row = Product::movementContext($pid);
            if ($row) {
                return $row;
            }
        }
        $sku = trim((string)($item['sku'] ?? ''));
        if ($sku !== '') {
            $stmt = $this->pdo->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
            $stmt->execute([$sku]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id) {
                return Product::movementContext($id);
            }
        }
        $name = trim((string)($item['item_name'] ?? $item['name'] ?? ''));
        if ($name !== '') {
            $stmt = $this->pdo->prepare('SELECT id FROM products WHERE name = ? LIMIT 1');
            $stmt->execute([$name]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id) {
                return Product::movementContext($id);
            }
        }
        return null;
    }

    private function backfillClassification(int $productId, string $unspsc, string $taxClass): void
    {
        if ($unspsc === '' && $taxClass === '') {
            return;
        }
        $row = Product::movementContext($productId);
        if (!$row) {
            return;
        }
        $code = (string)($row['unspsc_code'] ?? '');
        $tax = (string)($row['tax_class'] ?? 'A');
        $newCode = $code === '' && $unspsc !== '' ? $unspsc : $code;
        $newTax = $taxClass !== '' ? $taxClass : $tax;
        if ($newCode !== $code || $newTax !== $tax) {
            $this->pdo->prepare('UPDATE products SET unspsc_code = ?, tax_class = ? WHERE id = ?')
                ->execute([$newCode !== '' ? $newCode : null, $newTax, $productId]);
        }
    }

    /**
     * @return ?array<string,mixed>
     */
    private function resolveLocation(int $id, string $name): ?array
    {
        if ($id > 0) {
            $loc = Location::find($id);
            if ($loc) {
                return $loc;
            }
        }
        $name = trim($name);
        if ($name !== '') {
            $stmt = $this->pdo->prepare('SELECT * FROM locations WHERE name = ? LIMIT 1');
            $stmt->execute([$name]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        }
        return null;
    }

    private function warehouseId(): int
    {
        return Location::productionSource();
    }

    private function systemUserId(): int
    {
        $id = (int)$this->pdo->query('SELECT id FROM users ORDER BY id ASC LIMIT 1')->fetchColumn();
        return $id > 0 ? $id : 1;
    }

    private function inspectionVendorId(): int
    {
        $stmt = $this->pdo->prepare("SELECT id FROM vendors WHERE name = 'V-SIAS Inspection' LIMIT 1");
        $stmt->execute();
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
        $this->pdo->prepare("INSERT INTO vendors (name, contact_person, phone, is_active) VALUES ('V-SIAS Inspection', 'Site inspection', '', 1)")
            ->execute();
        return (int)$this->pdo->lastInsertId();
    }

    private function isEnterprise(): bool
    {
        $tier = defined('LICENSE_TIER') ? LICENSE_TIER : $this->settingsTier();
        return $tier === 'enterprise';
    }

    /**
     * @param array<string,mixed> $item
     */
    private function lineKey(int $index, array $item): string
    {
        $pid = (int)($item['product_id'] ?? 0);
        $name = strtolower(trim((string)($item['item_name'] ?? $item['name'] ?? '')));
        $batch = strtolower(trim((string)($item['batch_number'] ?? '')));
        if ($pid > 0) {
            return 'p' . $pid . ':' . $batch;
        }
        return 'n' . $index . ':' . substr(sha1($name . '|' . $batch), 0, 12);
    }

    /**
     * @return array<string,mixed>
     */
    private function syncRow(string $tracking, string $lineKey): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM vsias_sync_log WHERE tracking_code = ? AND line_key = ? LIMIT 1'
        );
        $stmt->execute([$tracking, $lineKey]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<string,mixed> $row
     */
    private function upsertSync(string $tracking, string $lineKey, array $row): void
    {
        $this->pdo->prepare(
            "INSERT INTO vsias_sync_log
                (tracking_code, line_key, product_id, zra_status, zra_message, booked, grv_id, transfer_id, dock_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                product_id = VALUES(product_id),
                zra_status = VALUES(zra_status),
                zra_message = VALUES(zra_message),
                booked = VALUES(booked),
                grv_id = VALUES(grv_id),
                transfer_id = VALUES(transfer_id),
                dock_id = VALUES(dock_id)"
        )->execute([
            $tracking,
            $lineKey,
            (int)($row['product_id'] ?? 0) ?: null,
            (string)($row['zra_status'] ?? ''),
            (string)($row['error'] ?? $row['zra_message'] ?? ''),
            !empty($row['booked']) ? 1 : 0,
            (int)($row['grv_id'] ?? 0) ?: null,
            (int)($row['transfer_id'] ?? 0) ?: null,
            (int)($row['dock_id'] ?? 0) ?: null,
        ]);
    }

    private function ensureSyncLog(): void
    {
        $this->pdo->exec(
            "CREATE TABLE IF NOT EXISTS vsias_sync_log (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tracking_code VARCHAR(64) NOT NULL,
                line_key VARCHAR(190) NOT NULL,
                product_id INT DEFAULT NULL,
                zra_status VARCHAR(32) DEFAULT NULL,
                zra_message TEXT DEFAULT NULL,
                booked TINYINT(1) NOT NULL DEFAULT 0,
                grv_id INT DEFAULT NULL,
                transfer_id INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_vsias_track_line (tracking_code, line_key),
                KEY idx_vsias_track (tracking_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        try {
            $this->pdo->exec('ALTER TABLE vsias_sync_log ADD COLUMN dock_id INT NULL AFTER transfer_id');
        } catch (\Throwable $e) {
        }
    }
}
