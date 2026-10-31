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
        
        // Single Source of Truth: The new is_sellable flag
        if (array_key_exists('is_sellable', $product)) {
            return (int)$product['is_sellable'] === 0;
        }
        
        // Legacy Fallback (pre-migration)
        $cat = strtolower((string)($product['cat_type'] ?? $product['category_type'] ?? ''));
        if ($cat === 'ingredients') {
            return true;
        }
        $used = (int)($product['used_as_ingredient'] ?? 0);
        $hasRecipe = (int)($product['has_recipe'] ?? $product['is_recipe'] ?? 0);
        $price = (float)($product['price'] ?? 0);
        return $used > 0 && $hasRecipe === 0 && $price <= 0;
    }

    public static function requiresZra(): bool
    {
        return defined('LICENSE_TIER') && LICENSE_TIER === 'enterprise';
    }

    public static function isZraReady(?array $product): bool
    {
        if (!$product || !array_key_exists('zra_synced', $product)) {
            return true;
        }
        return !empty($product['zra_synced']) && !empty($product['zra_item_cd']);
    }

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
                'reason' => 'Raw material cannot be transferred to a POS. Keep it in kitchen or warehouse.',
            ];
        }
        return ['ok' => true, 'reason' => null];
    }

    public static function receiveAt(?array $product, ?array $location): array
    {
        if (!$product || !$location) {
            return ['ok' => true, 'reason' => null];
        }
        if (self::isRawProduct($product) && self::isFrontOfHouse($location)) {
            return [
                'ok' => false,
                'reason' => 'Raw material must be received into kitchen or warehouse, not a POS bar or store.',
            ];
        }
        return ['ok' => true, 'reason' => null];
    }

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

    public static function sellOnPos(?array $product): array
    {
        if (self::isRawProduct($product)) {
            return [
                'ok' => false,
                'reason' => 'Raw material is not sold on POS. Produce the finished dish first.',
            ];
        }
        return ['ok' => true, 'reason' => null];
    }

    public static function tracksOnHandStock(?array $product): bool
    {
        if (!$product) {
            return false;
        }
        if (strtolower((string)($product['type'] ?? 'item')) === 'service') {
            return false;
        }
        return (int)($product['has_recipe'] ?? $product['is_recipe'] ?? 0) === 0;
    }

    public static function formatQty(float $qty): string
    {
        $formatted = number_format(max(0.0, $qty), 4, '.', '');
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        return $formatted === '' ? '0' : $formatted;
    }

    public static function canSellQty(?array $product, int $locationId, float $qtyWanted): array
    {
        if (!self::tracksOnHandStock($product)) {
            return ['ok' => true, 'on_hand' => 0.0, 'reason' => null];
        }
        $onHand = \App\Models\Inventory::quantity((int)$product['id'], $locationId);
        if ($qtyWanted <= $onHand + 0.0001) {
            return ['ok' => true, 'on_hand' => $onHand, 'reason' => null];
        }
        $name = trim((string)($product['name'] ?? 'This item'));
        return [
            'ok' => false,
            'on_hand' => $onHand,
            'reason' => 'Only ' . self::formatQty($onHand) . ' ' . $name
                . ' on hand at this station. If you have extra physical stock, raise a requisition, receive it, then sell.',
        ];
    }

    public static function requisitionUrl(int $productId, float $qty, int $destLocationId, int $sourceLocationId = 0): string
    {
        $url = 'index.php?page=transfers&open_request=1&product_id=' . $productId
            . '&qty=' . rawurlencode(self::formatQty($qty))
            . '&dest_location_id=' . $destLocationId;
        if ($sourceLocationId > 0) {
            $url .= '&source_location_id=' . $sourceLocationId;
        }
        return $url;
    }

    public static function shortagePrompt(?array $product, int $locationId, float $wanted): ?array
    {
        $check = self::canSellQty($product, $locationId, $wanted);
        if ($check['ok'] || !$product) {
            return null;
        }
        $pid = (int)$product['id'];
        $shortfall = max(0.01, $wanted - (float)$check['on_hand']);
        $locations = [];
        $listHtml = '';
        foreach (\App\Models\Inventory::stockByLocation($pid, $locationId) as $row) {
            $url = self::requisitionUrl($pid, $shortfall, $locationId, (int)$row['location_id']);
            $qtyLabel = self::formatQty((float)$row['quantity']);
            $name = (string)$row['location_name'];
            $locations[] = [
                'id' => (int)$row['location_id'],
                'name' => $name,
                'qty' => (float)$row['quantity'],
                'qty_label' => $qtyLabel,
                'url' => $url,
            ];
            $listHtml .= '<li class="mb-2"><a class="fw-bold" href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">'
                . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</a> — ' . htmlspecialchars($qtyLabel, ENT_QUOTES, 'UTF-8') . ' on hand</li>';
        }
        $html = '<p class="mb-2 text-start">' . htmlspecialchars((string)$check['reason'], ENT_QUOTES, 'UTF-8') . '</p>';
        if ($listHtml !== '') {
            $html .= '<p class="fw-bold mb-1 text-start">These locations have stock, from most to least:</p>'
                . '<ol class="text-start mb-2 ps-3">' . $listHtml . '</ol>'
                . '<p class="small text-muted mb-0 text-start">Click a location to open a stock request from there to this station. Receive it, then sell.</p>';
        } else {
            $fallback = self::requisitionUrl($pid, $shortfall, $locationId);
            $html .= '<p class="mb-2 text-start">No other location currently has this item on hand.</p>'
                . '<p class="mb-0 text-start"><a class="fw-bold" href="' . htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8') . '">Open a stock request anyway</a></p>';
        }
        return [
            'title' => 'Not enough stock',
            'text' => (string)$check['reason'],
            'html' => $html,
            'product_id' => $pid,
            'qty' => $shortfall,
            'dest_location_id' => $locationId,
            'locations' => $locations,
        ];
    }

    public static function assertCartStock(array $cart, int $locationId): void
    {
        $wanted = [];
        foreach ($cart as $item) {
            if (!empty($item['is_refund'])) {
                continue;
            }
            $pid = (int)($item['product_id'] ?? 0);
            if ($pid <= 0) {
                continue;
            }
            $wanted[$pid] = ($wanted[$pid] ?? 0) + (float)$item['qty'];
        }
        foreach ($wanted as $pid => $qty) {
            $product = \App\Models\Product::findWithCategory($pid);
            $check = self::canSellQty($product, $locationId, $qty);
            if (!$check['ok']) {
                throw new \Exception((string)$check['reason']);
            }
        }
    }
}
