<?php

namespace App\Models;

/** Customers who register on the public ordering page (separate from staff users). */
final class Customer extends Model
{
    public static function normalisePhone(string $phone): string
    {
        $p = preg_replace('/\D+/', '', $phone);
        if (str_starts_with($p, '260') && strlen($p) === 12) {
            $p = '0' . substr($p, 3);
        } elseif (strlen($p) === 9) {
            $p = '0' . $p;
        }
        return $p;
    }

    public static function findByPhone(string $phone): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM customers WHERE phone = ?');
        $stmt->execute([self::normalisePhone($phone)]);
        return $stmt->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM customers WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public static function register(string $name, string $phone, string $email, string $password, string $address = ''): int
    {
        $name = trim($name);
        $phone = self::normalisePhone($phone);
        if ($name === '' || strlen($name) < 2) {
            throw new \InvalidArgumentException('Please enter your name.');
        }
        if (!preg_match('/^0[79]\d{8}$/', $phone)) {
            throw new \InvalidArgumentException('Enter a valid Zambian mobile number, e.g. 097 123 4567.');
        }
        if (strlen($password) < 4) {
            throw new \InvalidArgumentException('Choose a PIN or password of at least 4 characters.');
        }
        if (self::findByPhone($phone)) {
            throw new \InvalidArgumentException('That number is already registered. Please sign in.');
        }
        $email = trim($email);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('That email address does not look right.');
        }
        // Link to the loyalty member with the same phone if one exists.
        $memberId = null;
        try {
            $m = self::db()->prepare('SELECT id FROM members WHERE phone = ? LIMIT 1');
            $m->execute([$phone]);
            $memberId = $m->fetchColumn() ?: null;
        } catch (\Throwable $e) {
        }
        self::db()->prepare('INSERT INTO customers (name, phone, email, password_hash, address, member_id) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$name, $phone, $email !== '' ? $email : null, password_hash($password, PASSWORD_DEFAULT), trim($address) ?: null, $memberId]);
        return (int)self::db()->lastInsertId();
    }

    public static function authenticate(string $phone, string $password): ?array
    {
        $c = self::findByPhone($phone);
        if (!$c || !$c['is_active'] || !password_verify($password, $c['password_hash'])) {
            return null;
        }
        self::db()->prepare('UPDATE customers SET last_login_at = NOW() WHERE id = ?')->execute([$c['id']]);
        return $c;
    }

    public static function updateProfile(int $id, string $name, string $email, string $address, string $newPassword = ''): void
    {
        $sql = 'UPDATE customers SET name = ?, email = ?, address = ?';
        $params = [trim($name), trim($email) ?: null, trim($address) ?: null];
        if ($newPassword !== '') {
            if (strlen($newPassword) < 4) {
                throw new \InvalidArgumentException('New PIN/password must be at least 4 characters.');
            }
            $sql .= ', password_hash = ?';
            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        self::db()->prepare($sql)->execute($params);
    }

    public static function orders(int $customerId, int $limit = 30): array
    {
        $stmt = self::db()->prepare(
            'SELECT s.id, s.created_at, s.final_total, s.status, s.payment_status, s.payment_method, s.order_type, s.order_notes, s.gateway_ref, s.zra_rcpt_no, s.surcharge_amount,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id AND si.status NOT IN ("voided","refunded")) AS item_count,
                    (SELECT COUNT(*) FROM sale_items si WHERE si.sale_id = s.id AND si.status NOT IN ("voided","refunded","ready")) AS items_cooking
             FROM sales s WHERE s.customer_id = ? ORDER BY s.id DESC LIMIT ' . (int)$limit
        );
        $stmt->execute([$customerId]);
        return $stmt->fetchAll();
    }

    public static function order(int $customerId, int $saleId): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM sales WHERE id = ? AND customer_id = ?');
        $stmt->execute([$saleId, $customerId]);
        $sale = $stmt->fetch();
        if (!$sale) {
            return null;
        }
        $items = self::db()->prepare(
            'SELECT si.quantity, si.price, si.status, COALESCE(p.name, "Item") AS name
             FROM sale_items si LEFT JOIN products p ON p.id = si.product_id
             WHERE si.sale_id = ? AND si.status NOT IN ("voided","refunded")'
        );
        $items->execute([$saleId]);
        $sale['items'] = $items->fetchAll();
        return $sale;
    }
}
