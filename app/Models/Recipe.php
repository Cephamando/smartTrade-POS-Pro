<?php

namespace App\Models;

final class Recipe extends Model
{
    public static function grouped(): array
    {
        $rows = self::db()->query(
            'SELECT pr.parent_product_id, pr.ingredient_product_id, pr.quantity, p.name as ingredient_name, p.unit
             FROM product_recipes pr JOIN products p ON pr.ingredient_product_id = p.id'
        )->fetchAll();
        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r['parent_product_id']][] = $r;
        }
        return $grouped;
    }

    public static function save(int $parentId, array $ingIds, array $qtys): void
    {
        $pdo = self::db();
        $pdo->beginTransaction();
        try {
            $pdo->prepare('DELETE FROM product_recipes WHERE parent_product_id = ?')->execute([$parentId]);
            $insertStmt = $pdo->prepare(
                'INSERT INTO product_recipes (parent_product_id, ingredient_product_id, quantity) VALUES (?, ?, ?)'
            );
            for ($i = 0; $i < count($ingIds); $i++) {
                $iId = (int)$ingIds[$i];
                $qty = (float)$qtys[$i];
                if ($iId > 0 && $qty > 0) {
                    $insertStmt->execute([$parentId, $iId, $qty]);
                }
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
