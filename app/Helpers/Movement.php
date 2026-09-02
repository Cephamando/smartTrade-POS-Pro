<?php

namespace App\Helpers;

final class Movement
{
    public static function isFrontOfHouse(?array $loc): bool
    {
        if (!$loc) {
            return false;
        }
        return in_array(strtolower((string)($loc['type'] ?? '')), ['store', 'bar'], true);
    }

    public static function isBackOfHouse(?array $loc): bool
    {
        if (!$loc) {
            return false;
        }
        return in_array(strtolower((string)($loc['type'] ?? '')), ['kitchen', 'warehouse'], true);
    }

    public static function isRawProduct(?array $product): bool
    {
        if (!$product) {
            return false;
        }
        $cat = strtolower((string)($product['cat_type'] ?? $product['category_type'] ?? ''));
        if ($cat === 'ingredients') {
            return true;
        }
        $used = (int)($product['used_as_ingredient'] ?? 0);
        $hasRecipe = (int)($product['has_recipe'] ?? $product['is_recipe'] ?? 0);
        $price = (float)($product['price'] ?? 0);
        return $used > 0 && $hasRecipe === 0 && $price <= 0;
    }

    /**
     * @return array{ok:bool, reason:?string}
     */
    public static function transfer(?array $product, ?array $source, ?array $dest): array
    {
        if (!$product) {
            return ['ok' => false, 'reason' => 'Select a product.'];
        }
        if (!$source || !$dest) {
            return ['ok' => false, 'reason' => 'Select source and destination locations.'];
        }
        if ((int)$source['id'] === (int)$dest['id']) {
            return ['ok' => false, 'reason' => 'Source and destination must be different locations.'];
        }
        if (self::isRawProduct($product) && self::isFrontOfHouse($dest)) {
            return [
                'ok' => false,
                'reason' => 'Raw food cannot be transferred to a POS. Keep it in kitchen or warehouse, then produce the finished dish.',
            ];
        }
        return ['ok' => true, 'reason' => null];
    }

    /**
     * @return array{ok:bool, reason:?string}
     */
    public static function receiveAt(?array $product, ?array $location): array
    {
        if (!$product || !$location) {
            return ['ok' => true, 'reason' => null];
        }
        if (self::isRawProduct($product) && self::isFrontOfHouse($location)) {
            return [
                'ok' => false,
                'reason' => 'Raw food must be received into kitchen or warehouse, not a POS bar or store.',
            ];
        }
        return ['ok' => true, 'reason' => null];
    }

    /**
     * @return array{ok:bool, reason:?string}
     */
    public static function produceTo(?array $product, ?array $dest): array
    {
        if (!$product) {
            return ['ok' => false, 'reason' => 'Select a menu item.'];
        }
        if (self::isRawProduct($product)) {
            return [
                'ok' => false,
                'reason' => 'Raw ingredients cannot be sent to POS. Produce a finished recipe item instead.',
            ];
        }
        if (!$dest) {
            return ['ok' => false, 'reason' => 'Select a destination workstation.'];
        }
        return ['ok' => true, 'reason' => null];
    }

    /**
     * @return array{ok:bool, reason:?string}
     */
    public static function sellOnPos(?array $product): array
    {
        if (self::isRawProduct($product)) {
            return [
                'ok' => false,
                'reason' => 'Raw food is not sold on POS. Produce the finished dish first.',
            ];
        }
        return ['ok' => true, 'reason' => null];
    }
}
