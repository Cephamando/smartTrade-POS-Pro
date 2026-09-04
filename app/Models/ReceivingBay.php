<?php

namespace App\Models;

final class ReceivingBay extends Model
{
    public static function ensureSchema(): void
    {
        $pdo = self::db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS receiving_docks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                tracking_code VARCHAR(64) NOT NULL,
                source_name VARCHAR(150) NOT NULL DEFAULT '',
                intended_location_id INT NULL,
                vendor_id INT NULL,
                status ENUM('docked','received','cancelled') NOT NULL DEFAULT 'docked',
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                received_at DATETIME NULL,
                received_by INT NULL,
                grv_id INT NULL,
                UNIQUE KEY uniq_receiving_track (tracking_code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS receiving_dock_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                dock_id INT NOT NULL,
                product_id INT NULL,
                is_unregistered TINYINT(1) NOT NULL DEFAULT 0,
                sku VARCHAR(80) NULL,
                unspsc_code VARCHAR(16) NULL,
                tax_class VARCHAR(8) NULL,
                category_id INT NULL,
                item_name VARCHAR(200) NOT NULL DEFAULT '',
                batch_number VARCHAR(100) NULL,
                expiry_date DATE NULL,
                quantity DECIMAL(12,3) NOT NULL DEFAULT 0,
                unit VARCHAR(50) NOT NULL DEFAULT 'Units',
                unit_cost DECIMAL(12,4) NOT NULL DEFAULT 0,
                line_key VARCHAR(190) NULL,
                KEY idx_dock_items (dock_id),
                CONSTRAINT fk_dock_items FOREIGN KEY (dock_id) REFERENCES receiving_docks(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        foreach ([
            'is_unregistered' => 'TINYINT(1) NOT NULL DEFAULT 0 AFTER product_id',
            'tax_class' => 'VARCHAR(8) NULL AFTER unspsc_code',
            'category_id' => 'INT NULL AFTER tax_class',
        ] as $col => $ddl) {
            try {
                $pdo->exec("ALTER TABLE receiving_dock_items ADD COLUMN {$col} {$ddl}");
            } catch (\Throwable $e) {
            }
        }
        try {
            $pdo->exec('ALTER TABLE receiving_dock_items MODIFY product_id INT NULL');
        } catch (\Throwable $e) {
        }
    }

    public static function dockedCount(): int
    {
        self::ensureSchema();
        return (int)self::db()->query("SELECT COUNT(*) FROM receiving_docks WHERE status = 'docked'")->fetchColumn();
    }

    public static function list(?string $status = null): array
    {
        self::ensureSchema();
        $sql = "SELECT d.*, l.name AS intended_location_name, v.name AS vendor_name,
                       (SELECT COUNT(*) FROM receiving_dock_items i WHERE i.dock_id = d.id) AS item_count,
                       (SELECT COALESCE(SUM(quantity),0) FROM receiving_dock_items i WHERE i.dock_id = d.id) AS total_qty,
                       (SELECT COUNT(*) FROM receiving_dock_items i WHERE i.dock_id = d.id AND i.is_unregistered = 1) AS unregistered_count
                FROM receiving_docks d
                LEFT JOIN locations l ON l.id = d.intended_location_id
                LEFT JOIN vendors v ON v.id = d.vendor_id";
        $params = [];
        if ($status) {
            $sql .= ' WHERE d.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY (d.status = \'docked\') DESC, d.id DESC';
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll() ?: [];
    }

    public static function find(int $id): ?array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare(
            "SELECT d.*, l.name AS intended_location_name, v.name AS vendor_name
             FROM receiving_docks d
             LEFT JOIN locations l ON l.id = d.intended_location_id
             LEFT JOIN vendors v ON v.id = d.vendor_id
             WHERE d.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function findByTracking(string $tracking): ?array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare('SELECT * FROM receiving_docks WHERE tracking_code = ? LIMIT 1');
        $stmt->execute([$tracking]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function items(int $dockId): array
    {
        $stmt = self::db()->prepare(
            "SELECT i.*, p.name AS product_name, p.zra_synced, p.zra_item_cd
             FROM receiving_dock_items i
             LEFT JOIN products p ON p.id = i.product_id
             WHERE i.dock_id = ?
             ORDER BY i.id ASC"
        );
        $stmt->execute([$dockId]);
        return $stmt->fetchAll() ?: [];
    }

    /**
     * @param list<array<string,mixed>> $rows
     */
    public static function dock(
        string $tracking,
        string $sourceName,
        ?int $intendedLocationId,
        int $vendorId,
        array $rows
    ): int {
        self::ensureSchema();
        $existing = self::findByTracking($tracking);
        if ($existing) {
            if (($existing['status'] ?? '') === 'received') {
                return (int)$existing['id'];
            }
            if (($existing['status'] ?? '') === 'docked') {
                return (int)$existing['id'];
            }
        }

        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            if ($existing && ($existing['status'] ?? '') === 'cancelled') {
                $dockId = (int)$existing['id'];
                $pdo->prepare(
                    'UPDATE receiving_docks SET source_name = ?, intended_location_id = ?, vendor_id = ?, status = \'docked\', notes = NULL, received_at = NULL, received_by = NULL, grv_id = NULL WHERE id = ?'
                )->execute([$sourceName, $intendedLocationId, $vendorId ?: null, $dockId]);
                $pdo->prepare('DELETE FROM receiving_dock_items WHERE dock_id = ?')->execute([$dockId]);
            } else {
                $pdo->prepare(
                    'INSERT INTO receiving_docks (tracking_code, source_name, intended_location_id, vendor_id, status) VALUES (?, ?, ?, ?, \'docked\')'
                )->execute([$tracking, $sourceName, $intendedLocationId, $vendorId ?: null]);
                $dockId = (int)$pdo->lastInsertId();
            }

            $ins = $pdo->prepare(
                'INSERT INTO receiving_dock_items
                    (dock_id, product_id, is_unregistered, sku, unspsc_code, tax_class, category_id, item_name, batch_number, expiry_date, quantity, unit, unit_cost, line_key)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            foreach ($rows as $row) {
                $pid = (int)($row['product_id'] ?? 0);
                $qty = (float)($row['qty'] ?? 0);
                $custom = !empty($row['is_unregistered']) || $pid <= 0;
                $name = trim((string)($row['item_name'] ?? ''));
                if ($qty <= 0) {
                    continue;
                }
                if (!$custom && $pid <= 0) {
                    continue;
                }
                if ($custom && $name === '') {
                    continue;
                }
                $expiry = $row['expiry_date'] ?? null;
                if ($expiry === '') {
                    $expiry = null;
                }
                $ins->execute([
                    $dockId,
                    $pid > 0 ? $pid : null,
                    $custom ? 1 : 0,
                    $row['sku'] ?? null,
                    $row['unspsc_code'] ?? null,
                    $row['tax_class'] ?? 'A',
                    !empty($row['category_id']) ? (int)$row['category_id'] : null,
                    $name,
                    $row['batch_number'] ?? null,
                    $expiry,
                    $qty,
                    (string)($row['unit'] ?? 'Units'),
                    (float)($row['cost'] ?? $row['unit_cost'] ?? 0),
                    $row['line_key'] ?? null,
                ]);
            }
            $pdo->commit();
            return $dockId;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * @param array<int,array<string,mixed>> $edits keyed by dock item id
     */
    public static function applyLineEdits(int $dockId, array $edits): void
    {
        $stmt = self::db()->prepare(
            'UPDATE receiving_dock_items
             SET quantity = ?, unit_cost = ?, item_name = COALESCE(NULLIF(?, ""), item_name),
                 sku = ?, unspsc_code = ?, tax_class = COALESCE(NULLIF(?, ""), tax_class)
             WHERE id = ? AND dock_id = ?'
        );
        foreach ($edits as $itemId => $row) {
            if (!is_array($row)) {
                continue;
            }
            $id = (int)$itemId;
            if (array_key_exists('item_name', $row) || array_key_exists('sku', $row) || array_key_exists('unspsc', $row) || array_key_exists('unspsc_code', $row)) {
                $sku = trim((string)($row['sku'] ?? ''));
                $unspsc = preg_replace('/\D/', '', (string)($row['unspsc'] ?? $row['unspsc_code'] ?? '')) ?: null;
                $stmt->execute([
                    (float)($row['qty'] ?? 0),
                    (float)($row['cost'] ?? 0),
                    trim((string)($row['item_name'] ?? '')),
                    $sku !== '' ? $sku : null,
                    $unspsc,
                    strtoupper(trim((string)($row['tax_class'] ?? ''))),
                    $id,
                    $dockId,
                ]);
            } else {
                self::db()->prepare(
                    'UPDATE receiving_dock_items SET quantity = ?, unit_cost = ? WHERE id = ? AND dock_id = ?'
                )->execute([(float)($row['qty'] ?? 0), (float)($row['cost'] ?? 0), $id, $dockId]);
            }
        }
    }

    public static function registerCustomItems(int $dockId, int $locationId): void
    {
        $dock = self::find($dockId);
        if (!$dock || ($dock['status'] ?? '') !== 'docked') {
            throw new \Exception('This consignment is not waiting in the receiving bay.');
        }
        $location = Location::find($locationId);
        if (!$location || !\App\Helpers\Movement::isBackOfHouse($location)) {
            throw new \Exception('Vendor stock can only be received into warehouse or kitchen.');
        }
        $upd = self::db()->prepare(
            'UPDATE receiving_dock_items SET product_id = ?, is_unregistered = 0, sku = COALESCE(?, sku) WHERE id = ?'
        );
        foreach (self::items($dockId) as $line) {
            $pid = (int)($line['product_id'] ?? 0);
            if ($pid > 0 && empty($line['is_unregistered'])) {
                continue;
            }
            $name = trim((string)($line['item_name'] ?: $line['product_name'] ?: ''));
            if ($name === '') {
                throw new \Exception('Give each unregistered line a product name before sending it to Goods Received.');
            }
            $sku = trim((string)($line['sku'] ?? '')) ?: null;
            $unspsc = preg_replace('/\D/', '', (string)($line['unspsc_code'] ?? '')) ?: null;
            $tax = strtoupper(trim((string)($line['tax_class'] ?? 'A'))) ?: 'A';
            if (!in_array($tax, ['A', 'B', 'C', 'D'], true)) {
                $tax = 'A';
            }
            $unit = trim((string)($line['unit'] ?? 'Units')) ?: 'Units';
            $cost = (float)($line['unit_cost'] ?? 0);
            $categoryId = !empty($line['category_id']) ? (int)$line['category_id'] : null;
            $newId = self::findOrCreateProduct($name, $sku, $unspsc, $tax, $unit, $cost, $categoryId, $locationId);
            $upd->execute([$newId, $sku, (int)$line['id']]);
        }
    }

    private static function findOrCreateProduct(
        string $name,
        ?string $sku,
        ?string $unspsc,
        string $tax,
        string $unit,
        float $cost,
        ?int $categoryId,
        int $locationId
    ): int {
        if ($sku) {
            $stmt = self::db()->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
            $stmt->execute([$sku]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }
        $stmt = self::db()->prepare('SELECT id FROM products WHERE name = ? LIMIT 1');
        $stmt->execute([$name]);
        $id = (int)($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }
        try {
            return Product::create($name, $categoryId, 0, $cost, $unit, $sku, $tax, $unspsc, $locationId);
        } catch (\Throwable $e) {
            $stmt = self::db()->prepare('SELECT id FROM products WHERE name = ? OR (sku IS NOT NULL AND sku = ?) LIMIT 1');
            $stmt->execute([$name, $sku]);
            $id = (int)($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
            throw new \Exception('Could not register "' . $name . '": ' . $e->getMessage());
        }
    }

    /**
     * @return array{vendor_id:int,location_id:int,ref:string,items:list<array{product_id:int,qty:float,cost:float}>}
     */
    public static function grvPrefill(int $dockId, ?int $locationId = null): array
    {
        $dock = self::find($dockId);
        if (!$dock) {
            throw new \Exception('Consignment not found.');
        }
        $vendorId = (int)($dock['vendor_id'] ?? 0);
        $named = Vendor::findIdByName((string)($dock['source_name'] ?? ''));
        if ($named > 0) {
            $vendorId = $named;
        }
        $loc = $locationId ?: (int)($dock['intended_location_id'] ?? 0);
        $items = [];
        foreach (self::items($dockId) as $line) {
            $pid = (int)($line['product_id'] ?? 0);
            $qty = (float)$line['quantity'];
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            $items[] = [
                'product_id' => $pid,
                'qty' => $qty,
                'cost' => (float)$line['unit_cost'],
            ];
        }
        return [
            'vendor_id' => $vendorId,
            'location_id' => $loc,
            'ref' => (string)$dock['tracking_code'],
            'items' => $items,
            'tracking_code' => (string)$dock['tracking_code'],
            'source_name' => (string)($dock['source_name'] ?? ''),
            'unregistered_count' => (int)($dock['unregistered_count'] ?? 0),
            'status' => (string)($dock['status'] ?? ''),
        ];
    }

    public static function markReceived(int $dockId, int $grvId, int $userId, int $locationId): void
    {
        $dock = self::find($dockId);
        if (!$dock || ($dock['status'] ?? '') !== 'docked') {
            return;
        }
        self::db()->prepare(
            "UPDATE receiving_docks SET status = 'received', received_at = NOW(), received_by = ?, grv_id = ?, intended_location_id = COALESCE(intended_location_id, ?) WHERE id = ?"
        )->execute([$userId, $grvId, $locationId, $dockId]);
        try {
            self::db()->prepare(
                'UPDATE vsias_sync_log SET booked = 1, grv_id = ? WHERE tracking_code = ?'
            )->execute([$grvId, (string)$dock['tracking_code']]);
        } catch (\Throwable $e) {
        }
    }

    /**
     * @param array<int,array{qty?:float,cost?:float}> $qtyOverrides keyed by dock item id
     */
    public static function receiveIntoInventory(int $dockId, int $locationId, int $userId, array $qtyOverrides = []): int
    {
        self::ensureSchema();
        $dock = self::find($dockId);
        if (!$dock || ($dock['status'] ?? '') !== 'docked') {
            throw new \Exception('This consignment is not waiting in the receiving bay.');
        }
        $location = Location::find($locationId);
        if (!$location || !\App\Helpers\Movement::isBackOfHouse($location)) {
            throw new \Exception('Vendor stock can only be received into warehouse or kitchen.');
        }
        $lines = self::items($dockId);
        $items = [];
        foreach ($lines as $line) {
            $id = (int)$line['id'];
            $qty = array_key_exists($id, $qtyOverrides)
                ? (float)($qtyOverrides[$id]['qty'] ?? $line['quantity'])
                : (float)$line['quantity'];
            $cost = array_key_exists($id, $qtyOverrides) && isset($qtyOverrides[$id]['cost'])
                ? (float)$qtyOverrides[$id]['cost']
                : (float)$line['unit_cost'];
            if ($qty <= 0) {
                continue;
            }
            $items[] = [
                'product_id' => (int)$line['product_id'],
                'qty' => $qty,
                'cost' => $cost,
            ];
        }
        if (!$items) {
            throw new \Exception('Enter a received quantity for at least one line.');
        }

        $vendorId = (int)($dock['vendor_id'] ?? 0);
        if ($vendorId <= 0) {
            $vendorId = (int)(self::db()->query("SELECT id FROM vendors WHERE name = 'V-SIAS Inspection' LIMIT 1")->fetchColumn() ?: 0);
        }
        if ($vendorId <= 0) {
            throw new \Exception('No vendor is assigned to this dock.');
        }
        $ref = (string)$dock['tracking_code'];
        $exists = self::db()->prepare('SELECT COUNT(*) FROM grvs WHERE reference_no = ?');
        $exists->execute([$ref]);
        if ((int)$exists->fetchColumn() > 0) {
            $ref .= '-R' . time();
        }
        $grvId = Purchase::receive($vendorId, $locationId, $userId, $ref, $items);

        self::db()->prepare(
            "UPDATE receiving_docks SET status = 'received', received_at = NOW(), received_by = ?, grv_id = ?, intended_location_id = COALESCE(intended_location_id, ?) WHERE id = ?"
        )->execute([$userId, $grvId, $locationId, $dockId]);

        try {
            self::db()->prepare(
                'UPDATE vsias_sync_log SET booked = 1, grv_id = ? WHERE tracking_code = ?'
            )->execute([$grvId, $ref]);
        } catch (\Throwable $e) {
        }

        return $grvId;
    }

    public static function cancel(int $dockId): void
    {
        $dock = self::find($dockId);
        if (!$dock || ($dock['status'] ?? '') !== 'docked') {
            throw new \Exception('Only docked consignments can be sent back.');
        }
        self::db()->prepare("UPDATE receiving_docks SET status = 'cancelled' WHERE id = ?")->execute([$dockId]);
    }
}
