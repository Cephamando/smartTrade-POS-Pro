<?php

namespace App\Services;

use PDO;

/**
 * Developer reset. Operations clears sales and stock on hand.
 * The catalogue clear also removes products and UNSPSC classes.
 */
final class CleanSlate
{
    /** Sales, payments, stock movements, and quantities. Products stay. */
    private const OPERATIONS = [
        'sale_items',
        'sale_tips',
        'sales',
        'refunds',
        'refund_requests',
        'payment_transactions',
        'flutterwave_transfers',
        'pickup_notifications',
        'inventory_logs',
        'inventory',
        'location_stock',
        'inventory_transfers',
        'stock_transfer_items',
        'stock_transfers',
        'transfers',
        'grv_items',
        'grvs',
        'purchase_inquiry_items',
        'purchase_inquiries',
        'purchase_order_items',
        'purchase_orders',
        'receiving_dock_items',
        'receiving_docks',
        'shifts',
        'daily_closures',
        'expenses',
        'event_payments',
        'zra_sync_log',
    ];

    /** Product catalogue and the UNSPSC class list. */
    private const CATALOGUE = [
        'product_recipes',
        'products',
        'zra_item_classes',
    ];

    public function clearOperations(PDO $pdo): int
    {
        return $this->wipe($pdo, self::OPERATIONS);
    }

    public function clearCatalogue(PDO $pdo): int
    {
        return $this->wipe($pdo, array_merge(self::OPERATIONS, self::CATALOGUE));
    }

    /** @param list<string> $tables */
    private function wipe(PDO $pdo, array $tables): int
    {
        $existing = array_flip($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) ?: []);
        $cleared = 0;
        $pdo->exec('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($tables as $table) {
                if (!isset($existing[$table]) || !preg_match('/^[a-z_]+$/', $table)) {
                    continue;
                }
                $pdo->exec('DELETE FROM `' . $table . '`');
                try {
                    $pdo->exec('ALTER TABLE `' . $table . '` AUTO_INCREMENT = 1');
                } catch (\Throwable $e) {
                    // Some tables have no auto-increment column.
                }
                $cleared++;
            }
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS=1');
        }
        return $cleared;
    }
}
