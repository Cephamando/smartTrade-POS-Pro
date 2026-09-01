<?php

namespace App\Models;

final class Product extends Model
{
    public static function allWithCategory(): array
    {
        return self::db()->query(
            "SELECT p.*, c.name as category_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             ORDER BY p.name ASC"
        )->fetchAll();
    }

    public static function activeSellable(): array
    {
        return self::db()->query(
            "SELECT id, name, sku, cost_price FROM products WHERE is_active = 1 ORDER BY name ASC"
        )->fetchAll();
    }

    public static function activeItems(): array
    {
        return self::db()->query(
            "SELECT * FROM products WHERE is_active = 1 AND type = 'item' ORDER BY name ASC"
        )->fetchAll();
    }

    public static function nameExists(string $name): bool
    {
        $stmt = self::db()->prepare('SELECT id FROM products WHERE name = ?');
        $stmt->execute([$name]);
        return (bool)$stmt->fetchColumn();
    }

    public static function create(
        string $name,
        $categoryId,
        $price,
        $cost,
        string $unit,
        ?string $sku,
        string $taxClass,
        ?string $unspscCode,
        int $locationId
    ): int {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO products (name, category_id, price, cost_price, unit, sku, tax_class, unspsc_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $categoryId, $price, $cost, $unit, $sku, $taxClass, $unspscCode]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO location_stock (location_id, product_id, quantity) VALUES (?, ?, 0)')
                ->execute([$locationId, $id]);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function importCsv(string $tmpName, int $locationId): array
    {
        $handle = fopen($tmpName, 'r');
        if ($handle === false) {
            throw new \Exception('Failed to read the uploaded CSV file.');
        }
        fgetcsv($handle, 1000, ',');
        $added = 0;
        $updated = 0;
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $name = trim($data[0] ?? '');
                if ($name === '') {
                    continue;
                }
                $categoryName = trim($data[1] ?? '');
                $skuRaw = trim($data[2] ?? '');
                $sku = $skuRaw === '' ? null : $skuRaw;
                $unit = trim($data[3] ?? 'unit');
                $costPrice = (float)($data[4] ?? 0);
                $price = (float)($data[5] ?? 0);
                $taxClass = trim($data[6] ?? 'A');
                if (!in_array($taxClass, ['A', 'B', 'C', 'D'], true)) {
                    $taxClass = 'A';
                }
                $unspscRaw = trim($data[7] ?? '');
                $unspscCode = $unspscRaw === '' ? null : $unspscRaw;

                $catId = null;
                if ($categoryName !== '') {
                    $catId = Category::findIdByName($categoryName);
                    if (!$catId) {
                        $catId = Category::create($categoryName);
                    }
                }

                $prodId = null;
                if ($sku) {
                    $checkSku = $pdo->prepare('SELECT id FROM products WHERE sku = ?');
                    $checkSku->execute([$sku]);
                    $prodId = $checkSku->fetchColumn();
                }
                if (!$prodId) {
                    $checkName = $pdo->prepare('SELECT id FROM products WHERE name = ?');
                    $checkName->execute([$name]);
                    $prodId = $checkName->fetchColumn();
                }

                if ($prodId) {
                    $pdo->prepare('UPDATE products SET category_id=?, cost_price=?, price=?, unit=?, sku=?, tax_class=?, unspsc_code=? WHERE id=?')
                        ->execute([$catId, $costPrice, $price, $unit, $sku, $taxClass, $unspscCode, $prodId]);
                    $updated++;
                } else {
                    $pdo->prepare('INSERT INTO products (name, category_id, cost_price, price, unit, sku, tax_class, unspsc_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$name, $catId, $costPrice, $price, $unit, $sku, $taxClass, $unspscCode]);
                    $newProdId = $pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO location_stock (location_id, product_id, quantity) VALUES (?, ?, 0)')
                        ->execute([$locationId, $newProdId]);
                    $added++;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            fclose($handle);
            throw new \Exception('Import Failed: ' . $e->getMessage());
        }
        fclose($handle);
        return ['added' => $added, 'updated' => $updated];
    }

    public static function posCatalog(int $locationId): array
    {
        $stmt = self::db()->prepare(
            "SELECT p.*, COALESCE(i.quantity, 0) as stock_qty,
                    (SELECT COUNT(*) FROM product_recipes WHERE parent_product_id = p.id) as is_recipe
             FROM products p
             LEFT JOIN inventory i ON p.id = i.product_id AND i.location_id = ?
             WHERE p.type = 'item' AND p.is_active = 1"
        );
        $stmt->execute([$locationId]);
        return $stmt->fetchAll();
    }

    public static function activeServices(): array
    {
        return self::db()->query("SELECT * FROM products WHERE type = 'service' AND is_active = 1")->fetchAll();
    }

    public static function findWithCategory(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT p.*, c.type as cat_type FROM products p JOIN categories c ON p.category_id = c.id WHERE p.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function meals(): array
    {
        $stmt = self::db()->prepare(
            "SELECT p.*, c.name as cat_name
             FROM products p
             JOIN categories c ON p.category_id = c.id
             WHERE c.type IN ('food', 'meal') AND p.is_active = 1
             ORDER BY p.name ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function menuItems(array $types): array
    {
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $stmt = self::db()->prepare(
            "SELECT p.*, c.name as cat_name
             FROM products p
             JOIN categories c ON p.category_id = c.id
             WHERE c.type IN ($placeholders) AND p.is_active = 1
             ORDER BY c.name ASC, p.name ASC"
        );
        $stmt->execute($types);
        return $stmt->fetchAll();
    }

    public static function saveMenuItem(?int $id, string $name, float $price, float $cost, int $categoryId): void
    {
        if ($id) {
            self::db()->prepare('UPDATE products SET name=?, price=?, cost_price=?, category_id=? WHERE id=?')
                ->execute([$name, $price, $cost, $categoryId, $id]);
            return;
        }
        self::db()->prepare("INSERT INTO products (name, price, cost_price, category_id, type, is_active) VALUES (?, ?, ?, ?, 'item', 1)")
            ->execute([$name, $price, $cost, $categoryId]);
    }

    public static function deactivate(int $id): void
    {
        self::db()->prepare('UPDATE products SET is_active = 0 WHERE id = ?')->execute([$id]);
    }

    public static function allIngredients(): array
    {
        return self::db()->query('SELECT id, name, unit FROM products WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
    }
}
