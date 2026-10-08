<?php

namespace App\Models;

use App\Helpers\Movement;

final class Purchase extends Model
{
    public static function ensureSchema(): void
    {
        $pdo = self::db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS purchase_inquiries (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vendor_id INT DEFAULT NULL,
                location_id INT NOT NULL,
                user_id INT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'draft',
                notes TEXT DEFAULT NULL,
                needed_by DATE DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_pi_status (status),
                KEY idx_pi_vendor (vendor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS purchase_inquiry_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                inquiry_id INT NOT NULL,
                product_id INT NOT NULL,
                quantity DECIMAL(12,4) NOT NULL DEFAULT 0,
                notes VARCHAR(255) DEFAULT NULL,
                KEY idx_pii_inquiry (inquiry_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS purchase_orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                inquiry_id INT DEFAULT NULL,
                vendor_id INT NOT NULL,
                location_id INT NOT NULL,
                user_id INT NOT NULL,
                reference_no VARCHAR(50) DEFAULT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'pending',
                notes TEXT DEFAULT NULL,
                total_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_po_status (status),
                KEY idx_po_vendor (vendor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS purchase_order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                product_id INT NOT NULL,
                quantity DECIMAL(12,4) NOT NULL DEFAULT 0,
                received_qty DECIMAL(12,4) NOT NULL DEFAULT 0,
                unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                KEY idx_poi_order (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        try {
            $cols = $pdo->query('SHOW COLUMNS FROM grvs')->fetchAll();
            $names = array_map(static fn($c) => $c['Field'] ?? '', $cols);
            if (!in_array('purchase_order_id', $names, true)) {
                $pdo->exec('ALTER TABLE grvs ADD COLUMN purchase_order_id INT DEFAULT NULL AFTER vendor_id');
            }
        } catch (\Throwable $e) {
        }
    }

    public static function inquiries(): array
    {
        self::ensureSchema();
        return self::db()->query(
            "SELECT i.*, v.name AS vendor_name, l.name AS location_name, u.username,
                    (SELECT COUNT(*) FROM purchase_inquiry_items x WHERE x.inquiry_id = i.id) AS item_count
             FROM purchase_inquiries i
             LEFT JOIN vendors v ON v.id = i.vendor_id
             JOIN locations l ON l.id = i.location_id
             LEFT JOIN users u ON u.id = i.user_id
             ORDER BY i.id DESC"
        )->fetchAll();
    }

    public static function inquiry(int $id): ?array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare(
            "SELECT i.*, v.name AS vendor_name, l.name AS location_name
             FROM purchase_inquiries i
             LEFT JOIN vendors v ON v.id = i.vendor_id
             JOIN locations l ON l.id = i.location_id
             WHERE i.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $items = self::db()->prepare(
            "SELECT x.*, p.name AS product_name, p.unit, p.cost_price
             FROM purchase_inquiry_items x
             JOIN products p ON p.id = x.product_id
             WHERE x.inquiry_id = ?"
        );
        $items->execute([$id]);
        $row['items'] = $items->fetchAll();
        return $row;
    }

    public static function saveInquiry(
        ?int $id,
        ?int $vendorId,
        int $locationId,
        int $userId,
        string $status,
        ?string $neededBy,
        string $notes,
        array $items
    ): int {
        self::ensureSchema();
        $pdo = self::db();
        if ($id) {
            $pdo->prepare(
                'UPDATE purchase_inquiries SET vendor_id=?, location_id=?, status=?, needed_by=?, notes=? WHERE id=?'
            )->execute([$vendorId ?: null, $locationId, $status, $neededBy ?: null, $notes, $id]);
            $pdo->prepare('DELETE FROM purchase_inquiry_items WHERE inquiry_id = ?')->execute([$id]);
        } else {
            $pdo->prepare(
                'INSERT INTO purchase_inquiries (vendor_id, location_id, user_id, status, needed_by, notes) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$vendorId ?: null, $locationId, $userId, $status, $neededBy ?: null, $notes]);
            $id = (int)$pdo->lastInsertId();
        }
        $ins = $pdo->prepare(
            'INSERT INTO purchase_inquiry_items (inquiry_id, product_id, quantity, notes) VALUES (?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            $qty = (float)($item['qty'] ?? 0);
            if ($pid > 0 && $qty > 0) {
                $ins->execute([$id, $pid, $qty, trim((string)($item['notes'] ?? ''))]);
            }
        }
        return $id;
    }

    public static function setInquiryStatus(int $id, string $status): void
    {
        self::ensureSchema();
        self::db()->prepare('UPDATE purchase_inquiries SET status = ? WHERE id = ?')->execute([$status, $id]);
    }

    public static function orders(): array
    {
        self::ensureSchema();
        return self::db()->query(
            "SELECT o.*, v.name AS vendor_name, l.name AS location_name, u.username,
                    (SELECT COUNT(*) FROM purchase_order_items x WHERE x.order_id = o.id) AS item_count
             FROM purchase_orders o
             JOIN vendors v ON v.id = o.vendor_id
             JOIN locations l ON l.id = o.location_id
             LEFT JOIN users u ON u.id = o.user_id
             ORDER BY o.id DESC"
        )->fetchAll();
    }

    public static function order(int $id): ?array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare(
            "SELECT o.*, v.name AS vendor_name, l.name AS location_name
             FROM purchase_orders o
             JOIN vendors v ON v.id = o.vendor_id
             JOIN locations l ON l.id = o.location_id
             WHERE o.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $items = self::db()->prepare(
            "SELECT x.*, p.name AS product_name, p.unit
             FROM purchase_order_items x
             JOIN products p ON p.id = x.product_id
             WHERE x.order_id = ?"
        );
        $items->execute([$id]);
        $row['items'] = $items->fetchAll();
        return $row;
    }

    public static function saveOrder(
        ?int $id,
        ?int $inquiryId,
        int $vendorId,
        int $locationId,
        int $userId,
        string $ref,
        string $notes,
        array $items
    ): int {
        self::ensureSchema();
        if ($vendorId <= 0) {
            throw new \Exception('Select a vendor for the purchase order.');
        }
        $total = 0;
        $clean = [];
        foreach ($items as $item) {
            $line = Grv::normaliseLine((array)$item);
            if ($line) {
                $clean[] = $line;
                $total += $line['line_total'];
            }
        }
        if (!$clean) {
            throw new \Exception('Add at least one order line.');
        }
        $pdo = self::db();
        if ($id) {
            $pdo->prepare(
                'UPDATE purchase_orders SET vendor_id=?, location_id=?, reference_no=?, notes=?, total_cost=? WHERE id=? AND status IN (\'pending\',\'partial\')'
            )->execute([$vendorId, $locationId, $ref !== '' ? $ref : null, $notes, $total, $id]);
            $pdo->prepare('DELETE FROM purchase_order_items WHERE order_id = ?')->execute([$id]);
        } else {
            $pdo->prepare(
                'INSERT INTO purchase_orders (inquiry_id, vendor_id, location_id, user_id, reference_no, status, notes, total_cost)
                 VALUES (?, ?, ?, ?, ?, \'pending\', ?, ?)'
            )->execute([$inquiryId ?: null, $vendorId, $locationId, $userId, $ref !== '' ? $ref : null, $notes, $total]);
            $id = (int)$pdo->lastInsertId();
        }
        $ins = $pdo->prepare(
            'INSERT INTO purchase_order_items (order_id, product_id, quantity, received_qty, unit_cost, unit, pack_size, pack_qty, pack_cost, line_total) VALUES (?, ?, ?, 0, ?, ?, ?, ?, ?, ?)'
        );
        foreach ($clean as $l) {
            $ins->execute([$id, $l['product_id'], $l['qty'], $l['cost'], $l['unit'], $l['pack_size'], $l['pack_qty'], $l['pack_cost'], $l['line_total']]);
        }
        if ($inquiryId) {
            self::setInquiryStatus($inquiryId, 'converted');
        }
        return $id;
    }

    public static function convertInquiry(int $inquiryId, int $userId): int
    {
        $inquiry = self::inquiry($inquiryId);
        if (!$inquiry) {
            throw new \Exception('Inquiry not found.');
        }
        if (empty($inquiry['vendor_id'])) {
            throw new \Exception('Assign a vendor before converting this inquiry to an order.');
        }
        $items = [];
        foreach ($inquiry['items'] as $row) {
            $items[] = [
                'product_id' => $row['product_id'],
                'qty' => $row['quantity'],
                'cost' => $row['cost_price'] ?? 0,
            ];
        }
        return self::saveOrder(
            null,
            $inquiryId,
            (int)$inquiry['vendor_id'],
            (int)$inquiry['location_id'],
            $userId,
            'PO-' . date('Ymd') . '-' . $inquiryId,
            (string)($inquiry['notes'] ?? ''),
            $items
        );
    }

    public static function cancelOrder(int $id): void
    {
        self::ensureSchema();
        self::db()->prepare("UPDATE purchase_orders SET status = 'cancelled' WHERE id = ? AND status IN ('pending','partial')")
            ->execute([$id]);
    }

    public static function openOrders(): array
    {
        self::ensureSchema();
        return self::db()->query(
            "SELECT o.id, o.reference_no, o.status, v.name AS vendor_name
             FROM purchase_orders o
             JOIN vendors v ON v.id = o.vendor_id
             WHERE o.status IN ('pending','partial')
             ORDER BY o.id DESC"
        )->fetchAll();
    }

    public static function receive(
        int $vendorId,
        int $locId,
        int $userId,
        string $ref,
        array $items,
        ?int $orderId = null,
        string $notes = ''
    ): int {
        self::ensureSchema();
        $location = Location::find($locId);
        $lines = [];
        foreach ($items as $item) {
            $line = Grv::normaliseLine((array)$item);
            if (!$line) {
                continue;
            }
            $pid = $line['product_id'];
            $check = Movement::receiveAt(Product::movementContext($pid), $location);
            if (!$check['ok']) {
                $name = Product::movementContext($pid)['name'] ?? ('#' . $pid);
                throw new \Exception($name . ': ' . $check['reason']);
            }
            $lines[] = $line;
        }
        $grvId = Grv::process($vendorId, $locId, $userId, $ref, $lines, $notes);
        if ($orderId) {
            self::db()->prepare('UPDATE grvs SET purchase_order_id = ? WHERE id = ?')->execute([$orderId, $grvId]);
            self::applyReceiptToOrder($orderId, $lines);
        }
        return $grvId;
    }

    private static function applyReceiptToOrder(int $orderId, array $items): void
    {
        $pdo = self::db();
        $upd = $pdo->prepare(
            'UPDATE purchase_order_items SET received_qty = received_qty + ?
             WHERE order_id = ? AND product_id = ?'
        );
        foreach ($items as $item) {
            $pid = (int)($item['product_id'] ?? 0);
            $qty = (float)($item['qty'] ?? 0);
            if ($pid > 0 && $qty > 0) {
                $upd->execute([$qty, $orderId, $pid]);
            }
        }
        $open = $pdo->prepare(
            'SELECT COUNT(*) FROM purchase_order_items WHERE order_id = ? AND received_qty < quantity'
        );
        $open->execute([$orderId]);
        $status = ((int)$open->fetchColumn() > 0) ? 'partial' : 'received';
        $pdo->prepare('UPDATE purchase_orders SET status = ? WHERE id = ?')->execute([$status, $orderId]);
    }
}
