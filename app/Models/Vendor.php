<?php

namespace App\Models;

final class Vendor extends Model
{
    public static function ensurePortal(): void
    {
        static $ready = false;
        if ($ready) {
            return;
        }
        $ready = true;
        $pdo = self::db();
        $names = array_column($pdo->query('SHOW COLUMNS FROM vendors')->fetchAll() ?: [], 'Field');
        foreach ([
            'portal_email' => 'VARCHAR(190) DEFAULT NULL',
            'portal_password_hash' => 'VARCHAR(255) DEFAULT NULL',
            'portal_active' => 'TINYINT(1) NOT NULL DEFAULT 0',
        ] as $column => $definition) {
            if (!in_array($column, $names, true)) {
                $pdo->exec('ALTER TABLE vendors ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        try {
            $pdo->exec('CREATE UNIQUE INDEX uniq_vendor_portal_email ON vendors (portal_email)');
        } catch (\Throwable $e) {
        }
        foreach ([
            'payout_method' => 'VARCHAR(10) DEFAULT NULL',
            'payout_network' => 'VARCHAR(10) DEFAULT NULL',
            'payout_bank' => 'VARCHAR(20) DEFAULT NULL',
            'payout_bank_id' => 'VARCHAR(40) DEFAULT NULL',
            'payout_bank_name' => 'VARCHAR(160) DEFAULT NULL',
            'payout_account' => 'VARCHAR(30) DEFAULT NULL',
            'payout_name' => 'VARCHAR(120) DEFAULT NULL',
            'payout_branch' => 'VARCHAR(40) DEFAULT NULL',
            'payout_has_branches' => 'TINYINT(1) NOT NULL DEFAULT 0',
            'payout_subaccount_id' => 'VARCHAR(40) DEFAULT NULL',
        ] as $column => $definition) {
            if (!in_array($column, $names, true)) {
                $pdo->exec('ALTER TABLE vendors ADD COLUMN ' . $column . ' ' . $definition);
            }
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS vendor_offerings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                vendor_id INT NOT NULL,
                product_id INT NOT NULL,
                unit_cost DECIMAL(12,4) NOT NULL,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_vendor_offering (vendor_id, product_id),
                KEY idx_vendor_offering_product (product_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    public static function all(): array
    {
        self::ensurePortal();
        return self::db()->query('SELECT * FROM vendors ORDER BY name ASC')->fetchAll();
    }

    public static function active(): array
    {
        self::ensurePortal();
        return self::db()->query('SELECT * FROM vendors WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
    }

    public static function findIdByName(string $name): int
    {
        $name = trim($name);
        if ($name === '') {
            return 0;
        }
        $stmt = self::db()->prepare('SELECT id FROM vendors WHERE LOWER(name) = LOWER(?) LIMIT 1');
        $stmt->execute([$name]);
        return (int)($stmt->fetchColumn() ?: 0);
    }

    public static function create(string $name, string $contact, string $phone): void
    {
        self::db()->prepare('INSERT INTO vendors (name, contact_person, phone) VALUES (?, ?, ?)')
            ->execute([$name, $contact, $phone]);
    }

    public static function delete(int $id): bool
    {
        $check = self::db()->prepare('SELECT id FROM grvs WHERE vendor_id = ? LIMIT 1');
        $check->execute([$id]);
        if ($check->fetchColumn()) {
            return false;
        }
        self::ensurePortal();
        self::db()->prepare('DELETE FROM vendor_offerings WHERE vendor_id = ?')->execute([$id]);
        self::db()->prepare('DELETE FROM vendors WHERE id = ?')->execute([$id]);
        return true;
    }

    public static function find(int $id): ?array
    {
        self::ensurePortal();
        $stmt = self::db()->prepare('SELECT * FROM vendors WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return array{id: int, name: string, contact_person: string, phone: string, portal_email: string}|null */
    public static function authenticate(string $email, string $password): ?array
    {
        self::ensurePortal();
        $email = strtolower(trim($email));
        if ($email === '' || $password === '') {
            return null;
        }
        $stmt = self::db()->prepare(
            'SELECT id, name, contact_person, phone, portal_email, portal_password_hash
             FROM vendors
             WHERE portal_email = ? AND portal_active = 1 AND COALESCE(is_active, 1) = 1
             LIMIT 1'
        );
        $stmt->execute([$email]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($password, (string)($row['portal_password_hash'] ?? ''))) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'name' => (string)$row['name'],
            'contact_person' => (string)($row['contact_person'] ?? ''),
            'phone' => (string)($row['phone'] ?? ''),
            'portal_email' => (string)$row['portal_email'],
        ];
    }

    /** Returns a generated password when one was created, so the office can hand it over once. */
    public static function setPortal(int $id, string $email, string $password, bool $active): ?string
    {
        self::ensurePortal();
        $vendor = self::find($id);
        if (!$vendor) {
            throw new \InvalidArgumentException('Vendor not found.');
        }
        $email = strtolower(trim($email));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new \InvalidArgumentException('Enter a valid supplier email.');
        }
        if ($active && $email === '') {
            throw new \InvalidArgumentException('Enter the supplier email before turning the portal on.');
        }
        if ($email !== '') {
            $dup = self::db()->prepare('SELECT id FROM vendors WHERE portal_email = ? AND id <> ? LIMIT 1');
            $dup->execute([$email, $id]);
            if ($dup->fetchColumn()) {
                throw new \InvalidArgumentException('That email is already used by another vendor.');
            }
        }
        $generated = null;
        $hash = null;
        if ($password !== '') {
            if (strlen($password) < 8) {
                throw new \InvalidArgumentException('The portal password needs at least 8 characters.');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
        } elseif ($active && trim((string)($vendor['portal_password_hash'] ?? '')) === '') {
            $generated = bin2hex(random_bytes(4));
            $hash = password_hash($generated, PASSWORD_DEFAULT);
        }
        if ($hash !== null) {
            self::db()->prepare('UPDATE vendors SET portal_email = ?, portal_password_hash = ?, portal_active = ? WHERE id = ?')
                ->execute([$email !== '' ? $email : null, $hash, $active ? 1 : 0, $id]);
        } else {
            self::db()->prepare('UPDATE vendors SET portal_email = ?, portal_active = ? WHERE id = ?')
                ->execute([$email !== '' ? $email : null, $active ? 1 : 0, $id]);
        }
        return $generated;
    }

    public static function updatePortalProfile(int $id, string $contact, string $phone, string $newPassword): void
    {
        self::ensurePortal();
        $contact = trim($contact);
        $phone = trim($phone);
        if ($newPassword !== '' && strlen($newPassword) < 8) {
            throw new \InvalidArgumentException('The new password needs at least 8 characters.');
        }
        if ($newPassword !== '') {
            self::db()->prepare('UPDATE vendors SET contact_person = ?, phone = ?, portal_password_hash = ? WHERE id = ?')
                ->execute([$contact, $phone, password_hash($newPassword, PASSWORD_DEFAULT), $id]);
            return;
        }
        self::db()->prepare('UPDATE vendors SET contact_person = ?, phone = ? WHERE id = ?')
            ->execute([$contact, $phone, $id]);
    }

    /** @return list<array{id: int, name: string, unit: string}> */
    public static function stockItems(): array
    {
        self::ensurePortal();
        return self::db()->query(
            "SELECT id, name, COALESCE(unit, '') AS unit
             FROM products
             WHERE is_active = 1 AND type = 'item'
             ORDER BY name ASC"
        )->fetchAll();
    }

    /** @return list<array{product_id: int, name: string, unit: string, unit_cost: float}> */
    public static function offerings(int $vendorId): array
    {
        self::ensurePortal();
        $stmt = self::db()->prepare(
            "SELECT o.product_id, o.unit_cost, p.name, COALESCE(p.unit, '') AS unit
             FROM vendor_offerings o
             JOIN products p ON p.id = o.product_id
             WHERE o.vendor_id = ? AND p.is_active = 1 AND p.type = 'item'
             ORDER BY p.name ASC"
        );
        $stmt->execute([$vendorId]);
        return $stmt->fetchAll();
    }

    public static function offeringCost(int $vendorId, int $productId): ?float
    {
        self::ensurePortal();
        $stmt = self::db()->prepare(
            "SELECT o.unit_cost
             FROM vendor_offerings o
             JOIN products p ON p.id = o.product_id
             WHERE o.vendor_id = ? AND o.product_id = ? AND p.is_active = 1 AND p.type = 'item'"
        );
        $stmt->execute([$vendorId, $productId]);
        $cost = $stmt->fetchColumn();
        return $cost === false ? null : (float)$cost;
    }

    public static function saveOffering(int $vendorId, int $productId, float $cost): void
    {
        self::ensurePortal();
        if ($vendorId <= 0 || !self::find($vendorId)) {
            throw new \InvalidArgumentException('Vendor not found.');
        }
        if ($cost <= 0) {
            throw new \InvalidArgumentException('Enter a price greater than zero.');
        }
        $product = self::db()->prepare("SELECT id FROM products WHERE id = ? AND is_active = 1 AND type = 'item'");
        $product->execute([$productId]);
        if (!$product->fetchColumn()) {
            throw new \InvalidArgumentException('Choose a stock item from the shop list.');
        }
        self::db()->prepare(
            'INSERT INTO vendor_offerings (vendor_id, product_id, unit_cost) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE unit_cost = VALUES(unit_cost)'
        )->execute([$vendorId, $productId, round($cost, 4)]);
    }

    public static function removeOffering(int $vendorId, int $productId): void
    {
        self::ensurePortal();
        self::db()->prepare('DELETE FROM vendor_offerings WHERE vendor_id = ? AND product_id = ?')
            ->execute([$vendorId, $productId]);
    }

    /** @param array<string, string>|null $pairs Settings-shaped payout fields, or null to clear. */
    public static function savePayout(int $id, ?array $pairs): void
    {
        self::ensurePortal();
        if (!self::find($id)) {
            throw new \InvalidArgumentException('Vendor not found.');
        }
        if ($pairs === null) {
            self::db()->prepare(
                'UPDATE vendors SET payout_method = NULL, payout_network = NULL, payout_bank = NULL,
                    payout_bank_id = NULL, payout_bank_name = NULL, payout_account = NULL,
                    payout_name = NULL, payout_branch = NULL, payout_has_branches = 0, payout_subaccount_id = NULL
                 WHERE id = ?'
            )->execute([$id]);
            return;
        }
        self::db()->prepare(
            'UPDATE vendors SET payout_method = ?, payout_network = ?, payout_bank = ?, payout_bank_id = ?,
                payout_bank_name = ?, payout_account = ?, payout_name = ?, payout_branch = ?, payout_has_branches = ?
             WHERE id = ?'
        )->execute([
            $pairs['flw_payout_method'],
            $pairs['flw_payout_network'] !== '' ? $pairs['flw_payout_network'] : null,
            $pairs['flw_payout_bank'] !== '' ? $pairs['flw_payout_bank'] : null,
            $pairs['flw_payout_bank_id'] !== '' ? $pairs['flw_payout_bank_id'] : null,
            $pairs['flw_payout_bank_name'] !== '' ? $pairs['flw_payout_bank_name'] : null,
            $pairs['flw_payout_account'],
            $pairs['flw_payout_name'],
            $pairs['flw_payout_branch'] !== '' ? $pairs['flw_payout_branch'] : null,
            (int)$pairs['flw_payout_has_branches'],
            $id,
        ]);
    }

    public static function saveSubaccountId(int $id, string $subaccountId): void
    {
        self::ensurePortal();
        self::db()->prepare('UPDATE vendors SET payout_subaccount_id = ? WHERE id = ?')
            ->execute([$subaccountId !== '' ? $subaccountId : null, $id]);
    }
    public static function payoutDestination(array $vendor): array
    {
        $method = (string)($vendor['payout_method'] ?? '');
        $bank = trim((string)($vendor['payout_bank'] ?? ''));
        if ($method !== 'momo' && $method !== 'bank') {
            $method = strtoupper($bank) === 'MPS' ? 'momo' : 'bank';
        }
        $account = preg_replace('/\D+/', '', (string)($vendor['payout_account'] ?? '')) ?? '';
        $name = trim((string)($vendor['payout_name'] ?? ''));
        $branch = trim((string)($vendor['payout_branch'] ?? ''));
        $network = strtoupper(trim((string)($vendor['payout_network'] ?? '')));
        $hasBranches = (int)($vendor['payout_has_branches'] ?? 0) === 1;
        if ($method === 'momo') {
            $bank = 'MPS';
            $ready = $name !== '' && preg_match('/^260[0-9]{9}$/', $account) === 1;
        } else {
            $ready = $bank !== '' && $bank !== 'MPS' && $name !== '' && preg_match('/^[0-9]{6,20}$/', $account) === 1 && (!$hasBranches || $branch !== '');
        }
        $masked = $account === '' ? '' : (strlen($account) <= 4 ? $account : str_repeat('•', strlen($account) - 4) . substr($account, -4));
        return [
            'method' => $method,
            'network' => $network,
            'bank' => $bank,
            'bank_name' => trim((string)($vendor['payout_bank_name'] ?? '')),
            'account' => $account,
            'name' => $name,
            'branch' => $branch,
            'ready' => $ready,
            'masked' => $masked,
        ];
    }
}
