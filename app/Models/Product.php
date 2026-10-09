<?php

namespace App\Models;

use App\Helpers\Spreadsheet;

final class Product extends Model
{
    public static function ensureSchema(): void
    {
        $pdo = self::db();
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'is_sellable'");
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE products ADD COLUMN is_sellable TINYINT(1) NOT NULL DEFAULT 1");
            $pdo->exec("UPDATE products SET is_sellable = 0 WHERE category_type = 'ingredients' OR (used_as_ingredient = 1 AND has_recipe = 0 AND price <= 0)");
        }
        $image = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'image_path'");
        if ((int)$image->fetchColumn() === 0) {
            $pdo->exec('ALTER TABLE products ADD COLUMN image_path VARCHAR(255) NULL');
        }
    }

    public static function setImage(int $id, ?string $path): void
    {
        $stmt = self::db()->prepare('SELECT image_path FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $old = (string)$stmt->fetchColumn();
        self::db()->prepare('UPDATE products SET image_path = ? WHERE id = ?')->execute([$path, $id]);
        if ($old !== '' && $old !== (string)$path) {
            \App\Helpers\Media::delete($old);
        }
    }

    public static function allWithCategory(): array
    {
        Unspsc::ensureTable();
        return self::db()->query(
            "SELECT p.*, c.name as category_name, z.item_cls_nm as unspsc_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN zra_item_classes z ON z.item_cls_cd = p.unspsc_code
             ORDER BY p.name ASC"
        )->fetchAll();
    }

    public static function catalogRow(int $id): ?array
    {
        Unspsc::ensureTable();
        $stmt = self::db()->prepare(
            "SELECT p.*, c.name as category_name, z.item_cls_nm as unspsc_name
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN zra_item_classes z ON z.item_cls_cd = p.unspsc_code
             WHERE p.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function activeSellable(): array
    {
        return self::db()->query(
            "SELECT id, name, sku, cost_price FROM products WHERE is_active = 1 AND is_sellable = 1 ORDER BY name ASC"
        )->fetchAll();
    }

    public static function activeItems(): array
    {
        return self::db()->query(
            "SELECT p.*, c.type as cat_type,
                    (SELECT COUNT(*) FROM product_recipes WHERE parent_product_id = p.id) as has_recipe,
                    (SELECT COUNT(*) FROM product_recipes WHERE ingredient_product_id = p.id) as used_as_ingredient
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.is_active = 1 AND p.type = 'item'
             ORDER BY p.name ASC"
        )->fetchAll();
    }

    public static function nameExists(string $name, ?int $excludeId = null): bool
    {
        if ($excludeId) {
            $stmt = self::db()->prepare('SELECT id FROM products WHERE name = ? AND id != ?');
            $stmt->execute([$name, $excludeId]);
        } else {
            $stmt = self::db()->prepare('SELECT id FROM products WHERE name = ?');
            $stmt->execute([$name]);
        }
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
        int $locationId,
        string $type = 'item',
        int $isSellable = 1
    ): int {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $type = $type === 'service' ? 'service' : 'item';
            $stmt = $pdo->prepare(
                'INSERT INTO products (name, category_id, price, cost_price, unit, sku, tax_class, unspsc_code, type, is_sellable) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$name, $categoryId, $price, $cost, $unit, $sku, $taxClass, $unspscCode, $type, $isSellable]);
            $id = (int)$pdo->lastInsertId();
            $pdo->prepare('INSERT INTO location_stock (location_id, product_id, quantity) VALUES (?, ?, 0)')
                ->execute([$locationId, $id]);
            $pdo->prepare('INSERT IGNORE INTO inventory (product_id, location_id, quantity) VALUES (?, ?, 0)')
                ->execute([$id, $locationId]);
            $pdo->commit();
            return $id;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    public static function update(
        int $id,
        string $name,
        $categoryId,
        $price,
        $cost,
        string $unit,
        ?string $sku,
        string $taxClass,
        ?string $unspscCode,
        string $type = 'item',
        int $isActive = 1,
        int $isSellable = 1
    ): void {
        $type = $type === 'service' ? 'service' : 'item';
        $isActive = $isActive ? 1 : 0;
        self::db()->prepare(
            'UPDATE products SET name=?, category_id=?, price=?, cost_price=?, unit=?, sku=?, tax_class=?, unspsc_code=?, type=?, is_active=?, is_sellable=? WHERE id=?'
        )->execute([$name, $categoryId, $price, $cost, $unit, $sku, $taxClass, $unspscCode, $type, $isActive, $isSellable, $id]);
    }

    public static function csvHeaders(): array
    {
        return ['Name', 'Category', 'SKU', 'Unit', 'Type', 'Cost Price', 'Selling Price', 'Tax Class', 'UNSPSC', 'Status'];
    }

    public static function exportCsv(): void
    {
        $rows = self::allWithCategory();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="Product_Catalog_' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
        fputcsv($out, self::csvHeaders());
        foreach ($rows as $p) {
            fputcsv($out, [
                $p['name'] ?? '',
                $p['category_name'] ?? '',
                $p['sku'] ?? '',
                $p['unit'] ?? 'unit',
                $p['type'] ?? 'item',
                number_format((float)($p['cost_price'] ?? 0), 2, '.', ''),
                number_format((float)($p['price'] ?? 0), 2, '.', ''),
                $p['tax_class'] ?? 'A',
                $p['unspsc_code'] ?? '',
                !empty($p['is_active']) ? 'Active' : 'Disabled',
            ]);
        }
        fclose($out);
        exit;
    }

    public static function importCsv(string $tmpName, int $locationId, string $originalName = ''): array
    {
        $rows = Spreadsheet::read($tmpName, $originalName);
        if (!$rows) {
            throw new \Exception('The file is empty or could not be read.');
        }
        $header = array_shift($rows);
        $map = Spreadsheet::mapProductColumns($header);
        if (!isset($map['name'])) {
            throw new \Exception('The file must include a Name column.');
        }

        $added = 0;
        $updated = 0;
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            foreach ($rows as $data) {
                $name = trim((string)Spreadsheet::col($data, $map['name'] ?? -1));
                if ($name === '') {
                    continue;
                }
                $categoryName = trim((string)Spreadsheet::col($data, $map['category'] ?? -1));
                $skuRaw = trim((string)Spreadsheet::col($data, $map['sku'] ?? -1));
                $sku = $skuRaw === '' ? null : $skuRaw;
                $unit = trim((string)Spreadsheet::col($data, $map['unit'] ?? -1));
                if ($unit === '') {
                    $unit = 'unit';
                }
                $typeRaw = strtolower(trim((string)Spreadsheet::col($data, $map['type'] ?? -1)));
                $type = $typeRaw === 'service' ? 'service' : 'item';
                $costPrice = (float)preg_replace('/[^\d.\-]/', '', (string)Spreadsheet::col($data, $map['cost'] ?? -1));
                $price = (float)preg_replace('/[^\d.\-]/', '', (string)Spreadsheet::col($data, $map['price'] ?? -1));
                $taxClass = strtoupper(trim((string)Spreadsheet::col($data, $map['tax'] ?? -1)));
                if (!in_array($taxClass, ['A', 'B', 'C', 'D'], true)) {
                    $taxClass = 'A';
                }
                $unspscRaw = preg_replace('/\D/', '', (string)Spreadsheet::col($data, $map['unspsc'] ?? -1));
                $unspscCode = $unspscRaw === '' ? null : $unspscRaw;
                $statusRaw = strtolower(trim((string)Spreadsheet::col($data, $map['status'] ?? -1)));
                $isActive = !in_array($statusRaw, ['disabled', 'inactive', '0', 'no'], true) ? 1 : 0;

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
                    $pdo->prepare('UPDATE products SET category_id=?, cost_price=?, price=?, unit=?, sku=?, tax_class=?, unspsc_code=?, type=?, is_active=? WHERE id=?')
                        ->execute([$catId, $costPrice, $price, $unit, $sku, $taxClass, $unspscCode, $type, $isActive, $prodId]);
                    $updated++;
                } else {
                    $pdo->prepare('INSERT INTO products (name, category_id, cost_price, price, unit, sku, tax_class, unspsc_code, type, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                        ->execute([$name, $catId, $costPrice, $price, $unit, $sku, $taxClass, $unspscCode, $type, $isActive]);
                    $newProdId = (int)$pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO location_stock (location_id, product_id, quantity) VALUES (?, ?, 0)')
                        ->execute([$locationId, $newProdId]);
                    $pdo->prepare('INSERT IGNORE INTO inventory (product_id, location_id, quantity) VALUES (?, ?, 0)')
                        ->execute([$newProdId, $locationId]);
                    $added++;
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw new \Exception('Import failed. ' . $e->getMessage());
        }
        return ['added' => $added, 'updated' => $updated];
    }

    public static function posCatalog(int $locationId): array
    {
        $stmt = self::db()->prepare(
            "SELECT p.*, c.type as cat_type,
                    GREATEST(COALESCE(i.quantity, 0), 0) as stock_qty,
                    (SELECT COUNT(*) FROM product_recipes WHERE parent_product_id = p.id) as is_recipe,
                    (SELECT COUNT(*) FROM product_recipes WHERE ingredient_product_id = p.id) as used_as_ingredient
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
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
        return self::movementContext($id);
    }

    public static function movementContext(int $id): ?array
    {
        $stmt = self::db()->prepare(
            "SELECT p.*, c.type as cat_type, c.name as category_name,
                    (SELECT COUNT(*) FROM product_recipes WHERE parent_product_id = p.id) as has_recipe,
                    (SELECT COUNT(*) FROM product_recipes WHERE parent_product_id = p.id) as is_recipe,
                    (SELECT COUNT(*) FROM product_recipes WHERE ingredient_product_id = p.id) as used_as_ingredient
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE p.id = ?"
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
            "SELECT p.*, c.name as cat_name, z.item_cls_nm as unspsc_name
             FROM products p
             JOIN categories c ON p.category_id = c.id
             LEFT JOIN zra_item_classes z ON z.item_cls_cd = p.unspsc_code
             WHERE c.type IN ($placeholders) AND p.is_active = 1
             ORDER BY c.name ASC, p.name ASC"
        );
        $stmt->execute($types);
        return $stmt->fetchAll();
    }

    public static function menuRow(int $id): ?array
    {
        Unspsc::ensureTable();
        $stmt = self::db()->prepare(
            "SELECT p.*, c.name as cat_name, z.item_cls_nm as unspsc_name,
                    (SELECT COUNT(*) FROM product_recipes WHERE parent_product_id = p.id) AS recipe_count
             FROM products p
             LEFT JOIN categories c ON p.category_id = c.id
             LEFT JOIN zra_item_classes z ON z.item_cls_cd = p.unspsc_code
             WHERE p.id = ?"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function saveMenuItem(?int $id, string $name, float $price, float $cost, int $categoryId, ?string $unspscCode = null): int
    {
        $code = $unspscCode !== null && $unspscCode !== '' ? preg_replace('/\D/', '', $unspscCode) : null;
        $code = $code === '' ? null : $code;
        if ($id) {
            self::db()->prepare('UPDATE products SET name=?, price=?, cost_price=?, category_id=?, unspsc_code=? WHERE id=?')
                ->execute([$name, $price, $cost, $categoryId, $code, $id]);
            return $id;
        }
        self::db()->prepare("INSERT INTO products (name, price, cost_price, category_id, type, is_active, unspsc_code) VALUES (?, ?, ?, ?, 'item', 1, ?)")
            ->execute([$name, $price, $cost, $categoryId, $code]);
        return (int)self::db()->lastInsertId();
    }

    public static function deactivate(int $id): void
    {
        self::db()->prepare('UPDATE products SET is_active = 0 WHERE id = ?')->execute([$id]);
    }

    public static function allIngredients(): array
    {
        return self::db()->query('SELECT id, name, unit FROM products WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
    }

    /** Active food and drink items that appear on the menu and the online shop. */
    public static function menuForPhotos(): array
    {
        self::ensureSchema();
        $types = ['food', 'meal', 'drink', 'beverage', 'cocktail'];
        $placeholders = implode(',', array_fill(0, count($types), '?'));
        $stmt = self::db()->prepare(
            "SELECT p.id, p.name, p.price, p.image_path, c.name AS cat_name
             FROM products p
             JOIN categories c ON p.category_id = c.id
             WHERE c.type IN ($placeholders) AND p.is_active = 1
             ORDER BY c.name ASC, p.name ASC"
        );
        $stmt->execute($types);
        return $stmt->fetchAll();
    }

    public static function menuPhotoTarget(int $id): ?array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare(
            "SELECT p.id, p.name, p.image_path
             FROM products p
             JOIN categories c ON p.category_id = c.id
             WHERE p.id = ? AND p.is_active = 1
               AND c.type IN ('food', 'meal', 'drink', 'beverage', 'cocktail')"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
