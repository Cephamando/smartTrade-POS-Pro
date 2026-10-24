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
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS purchase_quotations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                inquiry_id INT NOT NULL,
                vendor_id INT NOT NULL,
                status VARCHAR(20) NOT NULL DEFAULT 'submitted',
                delivery_date DATE DEFAULT NULL,
                valid_until DATE DEFAULT NULL,
                notes TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_quote_inquiry (inquiry_id),
                KEY idx_pq_vendor (vendor_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS purchase_quotation_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                quotation_id INT NOT NULL,
                inquiry_item_id INT NOT NULL,
                product_id INT NOT NULL,
                quantity DECIMAL(12,4) NOT NULL DEFAULT 0,
                unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
                can_supply TINYINT(1) NOT NULL DEFAULT 1,
                notes VARCHAR(255) DEFAULT NULL,
                KEY idx_pqi_quote (quotation_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        try {
            $orderCols = array_column($pdo->query('SHOW COLUMNS FROM purchase_orders')->fetchAll() ?: [], 'Field');
            if (!in_array('vendor_status', $orderCols, true)) {
                $pdo->exec("ALTER TABLE purchase_orders ADD COLUMN vendor_status VARCHAR(20) NOT NULL DEFAULT 'awaiting' AFTER status");
            }
            if (!in_array('vendor_delivery_ref', $orderCols, true)) {
                $pdo->exec('ALTER TABLE purchase_orders ADD COLUMN vendor_delivery_ref VARCHAR(80) DEFAULT NULL');
            }
            if (!in_array('vendor_status_at', $orderCols, true)) {
                $pdo->exec('ALTER TABLE purchase_orders ADD COLUMN vendor_status_at TIMESTAMP NULL DEFAULT NULL');
            }
        } catch (\Throwable $e) {
        }
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
        Vendor::ensurePortal();
        return self::db()->query(
            "SELECT o.*, v.name AS vendor_name, l.name AS location_name, u.username,
                    v.payout_method, v.payout_network, v.payout_bank, v.payout_bank_name,
                    v.payout_account, v.payout_name, v.payout_branch, v.payout_has_branches, v.payout_subaccount_id,
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

    /** @param list<array<string, mixed>> $lines */
    public static function orderFromOfferings(int $vendorId, int $locationId, int $userId, string $ref, string $notes, array $lines): int
    {
        if ($vendorId <= 0) {
            throw new \InvalidArgumentException('Select a supplier.');
        }
        if ($locationId <= 0) {
            throw new \InvalidArgumentException('Select where the goods should be delivered.');
        }
        $items = [];
        foreach ($lines as $line) {
            if (empty($line['pick'])) {
                continue;
            }
            $productId = (int)($line['product_id'] ?? 0);
            $qty = (float)($line['qty'] ?? 0);
            if ($qty <= 0) {
                throw new \InvalidArgumentException('Enter a quantity for each ticked line.');
            }
            $cost = Vendor::offeringCost($vendorId, $productId);
            if ($cost === null) {
                throw new \InvalidArgumentException('One of the ticked lines is not on that supplier’s price list.');
            }
            $unit = trim((string)($line['unit'] ?? ''));
            $items[] = [
                'product_id' => $productId,
                'qty' => $qty,
                'cost' => $cost,
                'unit' => $unit,
            ];
        }
        if (!$items) {
            throw new \InvalidArgumentException('Tick at least one priced line.');
        }
        return self::saveOrder(null, null, $vendorId, $locationId, $userId, $ref, $notes, $items);
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

    /** @return list<array<string, mixed>> */
    public static function vendorInquiries(int $vendorId): array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare(
            "SELECT i.*, l.name AS location_name,
                    (SELECT COUNT(*) FROM purchase_inquiry_items x WHERE x.inquiry_id = i.id) AS item_count,
                    q.id AS quotation_id, q.status AS quote_status
             FROM purchase_inquiries i
             JOIN locations l ON l.id = i.location_id
             LEFT JOIN purchase_quotations q ON q.inquiry_id = i.id
             WHERE i.vendor_id = ? AND i.status IN ('sent', 'quoted', 'converted')
             ORDER BY i.id DESC"
        );
        $stmt->execute([$vendorId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function vendorInquiry(int $vendorId, int $inquiryId): ?array
    {
        $row = self::inquiry($inquiryId);
        if (!$row || (int)($row['vendor_id'] ?? 0) !== $vendorId) {
            return null;
        }
        if (!in_array((string)$row['status'], ['sent', 'quoted', 'converted'], true)) {
            return null;
        }
        $row['quotation'] = self::quotationForInquiry($inquiryId);
        return $row;
    }

    public static function quotationForInquiry(int $inquiryId): ?array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare('SELECT * FROM purchase_quotations WHERE inquiry_id = ?');
        $stmt->execute([$inquiryId]);
        $quote = $stmt->fetch();
        if (!$quote) {
            return null;
        }
        $items = self::db()->prepare(
            "SELECT x.*, p.name AS product_name, p.unit
             FROM purchase_quotation_items x
             JOIN products p ON p.id = x.product_id
             WHERE x.quotation_id = ?
             ORDER BY x.id"
        );
        $items->execute([(int)$quote['id']]);
        $quote['items'] = $items->fetchAll() ?: [];
        return $quote;
    }

    public static function submitQuotation(
        int $vendorId,
        int $inquiryId,
        ?string $deliveryDate,
        ?string $validUntil,
        string $notes,
        array $lines
    ): int {
        self::ensureSchema();
        $inquiry = self::vendorInquiry($vendorId, $inquiryId);
        if (!$inquiry || !in_array((string)$inquiry['status'], ['sent', 'quoted'], true)) {
            throw new \Exception('This inquiry is not open for a quotation.');
        }
        $byItem = [];
        foreach ($inquiry['items'] as $item) {
            $byItem[(int)$item['id']] = $item;
        }
        $clean = [];
        foreach ($lines as $line) {
            $itemId = (int)($line['inquiry_item_id'] ?? 0);
            if (!isset($byItem[$itemId])) {
                continue;
            }
            $source = $byItem[$itemId];
            $qty = (float)($line['quantity'] ?? $source['quantity']);
            if ($qty < 0) {
                $qty = 0;
            }
            $cost = round((float)($line['unit_cost'] ?? 0), 2);
            if ($cost < 0) {
                throw new \Exception('A price cannot be negative.');
            }
            $clean[] = [
                'inquiry_item_id' => $itemId,
                'product_id' => (int)$source['product_id'],
                'quantity' => $qty,
                'unit_cost' => $cost,
                'can_supply' => empty($line['cannot_supply']) ? 1 : 0,
            ];
        }
        if ($clean === []) {
            throw new \Exception('Price the lines on this inquiry.');
        }
        $pdo = self::db();
        $existing = self::quotationForInquiry($inquiryId);
        if ($existing && (string)$existing['status'] === 'accepted') {
            throw new \Exception('This quotation has already been accepted.');
        }
        if ($existing) {
            $quoteId = (int)$existing['id'];
            $pdo->prepare(
                'UPDATE purchase_quotations SET delivery_date = ?, valid_until = ?, notes = ?, status = \'submitted\' WHERE id = ?'
            )->execute([$deliveryDate ?: null, $validUntil ?: null, $notes, $quoteId]);
            $pdo->prepare('DELETE FROM purchase_quotation_items WHERE quotation_id = ?')->execute([$quoteId]);
        } else {
            $pdo->prepare(
                'INSERT INTO purchase_quotations (inquiry_id, vendor_id, status, delivery_date, valid_until, notes)
                 VALUES (?, ?, \'submitted\', ?, ?, ?)'
            )->execute([$inquiryId, $vendorId, $deliveryDate ?: null, $validUntil ?: null, $notes]);
            $quoteId = (int)$pdo->lastInsertId();
        }
        $ins = $pdo->prepare(
            'INSERT INTO purchase_quotation_items (quotation_id, inquiry_item_id, product_id, quantity, unit_cost, can_supply)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($clean as $line) {
            $ins->execute([$quoteId, $line['inquiry_item_id'], $line['product_id'], $line['quantity'], $line['unit_cost'], $line['can_supply']]);
        }
        self::setInquiryStatus($inquiryId, 'quoted');
        return $quoteId;
    }

    public static function acceptQuotation(int $inquiryId, int $userId): int
    {
        $inquiry = self::inquiry($inquiryId);
        if (!$inquiry) {
            throw new \Exception('Inquiry not found.');
        }
        $quote = self::quotationForInquiry($inquiryId);
        if (!$quote || (string)$quote['status'] !== 'submitted') {
            throw new \Exception('There is no quotation to accept.');
        }
        $items = [];
        foreach ($quote['items'] as $line) {
            if (empty($line['can_supply']) || (float)$line['quantity'] <= 0) {
                continue;
            }
            $items[] = [
                'product_id' => $line['product_id'],
                'qty' => $line['quantity'],
                'cost' => $line['unit_cost'],
                'unit' => $line['unit'] ?? '',
            ];
        }
        if ($items === []) {
            throw new \Exception('The supplier cannot supply any of these lines.');
        }
        $notes = trim((string)($quote['notes'] ?? ''));
        if ($notes === '') {
            $notes = (string)($inquiry['notes'] ?? '');
        }
        $orderId = self::saveOrder(
            null,
            $inquiryId,
            (int)$quote['vendor_id'],
            (int)$inquiry['location_id'],
            $userId,
            'PO-' . date('Ymd') . '-' . $inquiryId,
            $notes,
            $items
        );
        self::db()->prepare("UPDATE purchase_quotations SET status = 'accepted' WHERE id = ?")->execute([(int)$quote['id']]);
        return $orderId;
    }

    /** @return list<array<string, mixed>> */
    public static function vendorOrders(int $vendorId): array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare(
            "SELECT o.*, l.name AS location_name
             FROM purchase_orders o
             JOIN locations l ON l.id = o.location_id
             WHERE o.vendor_id = ?
             ORDER BY o.id DESC"
        );
        $stmt->execute([$vendorId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function vendorOrder(int $vendorId, int $orderId): ?array
    {
        $order = self::order($orderId);
        if (!$order || (int)$order['vendor_id'] !== $vendorId) {
            return null;
        }
        $order['receipts'] = self::receiptsForOrder($orderId);
        return $order;
    }

    /** @return list<array<string, mixed>> */
    public static function receiptsForOrder(int $orderId): array
    {
        self::ensureSchema();
        try {
            $stmt = self::db()->prepare(
                'SELECT id, reference_no, notes, total_cost, created_at
                 FROM grvs WHERE purchase_order_id = ? ORDER BY id DESC'
            );
            $stmt->execute([$orderId]);
            return $stmt->fetchAll() ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function setVendorResponse(int $orderId, int $vendorId, string $action, string $deliveryRef = ''): void
    {
        self::ensureSchema();
        $order = self::vendorOrder($vendorId, $orderId);
        if (!$order) {
            throw new \Exception('That order is not yours.');
        }
        if (in_array((string)$order['status'], ['cancelled', 'received', 'declined'], true)) {
            throw new \Exception('This order is already closed.');
        }
        $pdo = self::db();
        if ($action === 'confirm') {
            $pdo->prepare(
                "UPDATE purchase_orders SET vendor_status = 'confirmed', vendor_status_at = NOW() WHERE id = ? AND vendor_id = ?"
            )->execute([$orderId, $vendorId]);
            return;
        }
        if ($action === 'decline') {
            if ((string)$order['status'] === 'partial') {
                throw new \Exception('Part of this order is already received, so it cannot be declined.');
            }
            $pdo->prepare(
                "UPDATE purchase_orders
                 SET vendor_status = 'declined', status = 'declined', vendor_status_at = NOW()
                 WHERE id = ? AND vendor_id = ? AND status = 'pending'"
            )->execute([$orderId, $vendorId]);
            return;
        }
        if ($action === 'dispatch') {
            $deliveryRef = trim($deliveryRef);
            if ($deliveryRef === '' || strlen($deliveryRef) > 80) {
                throw new \Exception('Enter the delivery reference.');
            }
            if ((string)($order['vendor_status'] ?? '') === 'declined') {
                throw new \Exception('This order was declined.');
            }
            $pdo->prepare(
                "UPDATE purchase_orders
                 SET vendor_status = 'dispatched', vendor_delivery_ref = ?, vendor_status_at = NOW()
                 WHERE id = ? AND vendor_id = ?"
            )->execute([$deliveryRef, $orderId, $vendorId]);
            return;
        }
        throw new \Exception('That action is not available.');
    }

    /** @return array{inquiries: int, quotes: int, awaiting: int, to_dispatch: int} */
    public static function vendorHome(int $vendorId): array
    {
        $inquiries = 0;
        $quotes = 0;
        $awaiting = 0;
        $toDispatch = 0;
        foreach (self::vendorInquiries($vendorId) as $row) {
            if ((string)$row['status'] === 'sent' && empty($row['quotation_id'])) {
                $inquiries++;
            }
            if ((string)($row['quote_status'] ?? '') === 'submitted') {
                $quotes++;
            }
        }
        foreach (self::vendorOrders($vendorId) as $row) {
            $state = (string)($row['vendor_status'] ?? 'awaiting');
            if (in_array((string)$row['status'], ['cancelled', 'declined', 'received'], true)) {
                continue;
            }
            if ($state === 'awaiting') {
                $awaiting++;
            }
            if (in_array($state, ['awaiting', 'confirmed'], true)) {
                $toDispatch++;
            }
        }
        return [
            'inquiries' => $inquiries,
            'quotes' => $quotes,
            'awaiting' => $awaiting,
            'to_dispatch' => $toDispatch,
        ];
    }
}
