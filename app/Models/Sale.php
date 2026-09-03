<?php

namespace App\Models;

final class Sale extends Model
{
    public static function dashboardKpis(int $locationId): array
    {
        $params = [];
        $locSql = '';
        if ($locationId > 0) {
            $locSql = ' AND location_id = ?';
            $params[] = $locationId;
        }

        $todaySales = self::db()->prepare(
            "SELECT SUM(final_total) FROM sales WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'" . $locSql
        );
        $todaySales->execute($params);

        $todayTransactions = self::db()->prepare(
            "SELECT COUNT(*) FROM sales WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'" . $locSql
        );
        $todayTransactions->execute($params);

        $unpaidTabs = self::db()->prepare(
            "SELECT COUNT(*) FROM sales WHERE payment_status = 'pending' AND id IN (SELECT sale_id FROM sale_items WHERE status NOT IN ('voided', 'refunded'))" . $locSql
        );
        $unpaidTabs->execute($params);

        $lowStockSql = ($locationId > 0)
            ? 'SELECT COUNT(*) FROM inventory WHERE location_id = ? AND quantity <= 5'
            : 'SELECT COUNT(*) FROM inventory WHERE quantity <= 5';
        $lowStockStmt = self::db()->prepare($lowStockSql);
        if ($locationId > 0) {
            $lowStockStmt->execute([$locationId]);
        } else {
            $lowStockStmt->execute();
        }

        $dates = [];
        $salesData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days"));
            $dates[] = date('D d', strtotime($date));
            $dSql = "SELECT SUM(final_total) FROM sales WHERE DATE(created_at) = ? AND payment_status = 'paid'" . $locSql;
            $dParams = array_merge([$date], $params);
            $stmt = self::db()->prepare($dSql);
            $stmt->execute($dParams);
            $salesData[] = $stmt->fetchColumn() ?: 0;
        }

        $pmSql = "SELECT payment_method, COUNT(*) as count FROM sales WHERE DATE(created_at) = CURDATE() AND payment_status = 'paid'" . $locSql . ' GROUP BY payment_method';
        $stmt = self::db()->prepare($pmSql);
        $stmt->execute($params);

        return [
            'todaySales' => $todaySales->fetchColumn() ?: 0.00,
            'todayTransactions' => $todayTransactions->fetchColumn() ?: 0,
            'unpaidTabs' => $unpaidTabs->fetchColumn() ?: 0,
            'lowStockCount' => $lowStockStmt->fetchColumn(),
            'dates' => $dates,
            'salesData' => $salesData,
            'pmData' => $stmt->fetchAll(\PDO::FETCH_KEY_PAIR),
        ];
    }

    public static function pendingFiscal(int $limit = 15): array
    {
        return self::db()->query(
            "SELECT id, created_at, customer_name, final_total, payment_method, COALESCE(zra_status, 'pending') AS zra_status, zra_last_error
             FROM sales
             WHERE payment_status = 'paid'
               AND (zra_rcpt_sign IS NULL OR zra_rcpt_sign = '')
               AND COALESCE(zra_status, 'pending') IN ('pending', 'failed')
               AND ABS(final_total) > 0
               AND EXISTS (
                   SELECT 1 FROM sale_items si
                   WHERE si.sale_id = sales.id AND si.status NOT IN ('voided')
               )
             ORDER BY id DESC
             LIMIT " . (int)$limit
        )->fetchAll();
    }

    public static function recentSynced(int $limit = 8): array
    {
        $limit = max(1, min(50, $limit));
        return self::db()->query(
            "SELECT id, created_at, customer_name, final_total, zra_rcpt_no, zra_sdc_id, zra_qr_code_url, zra_rcpt_sign
             FROM sales
             WHERE zra_rcpt_sign IS NOT NULL AND zra_rcpt_sign <> ''
             ORDER BY id DESC
             LIMIT {$limit}"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM sales WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function receiptSale(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT s.*, u.username, l.name as loc_name FROM sales s JOIN users u ON s.user_id = u.id LEFT JOIN locations l ON s.location_id = l.id WHERE s.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function receiptItems(int $saleId, array $specificItems = []): array
    {
        if ($specificItems) {
            $inClause = implode(',', array_map('intval', $specificItems));
            $stmt = self::db()->prepare(
                "SELECT COALESCE(p.name, 'Custom Item') as name, si.price, SUM(si.quantity) as quantity,
                        COALESCE(p.tax_class, 'A') as tax_class
                 FROM sale_items si
                 LEFT JOIN products p ON si.product_id = p.id
                 WHERE si.sale_id = ? AND si.status != 'voided' AND si.id IN ($inClause)
                 GROUP BY p.name, si.price, p.tax_class"
            );
            $stmt->execute([$saleId]);
            return $stmt->fetchAll();
        }
        $stmt = self::db()->prepare(
            "SELECT COALESCE(p.name, 'Custom Item') as name, si.price, SUM(si.quantity) as quantity,
                    COALESCE(p.tax_class, 'A') as tax_class
             FROM sale_items si
             LEFT JOIN products p ON si.product_id = p.id
             WHERE si.sale_id = ? AND si.status != 'voided'
             GROUP BY p.name, si.price, p.tax_class"
        );
        $stmt->execute([$saleId]);
        return $stmt->fetchAll();
    }

    public static function refundableItems(int $saleId): array
    {
        $stmt = self::db()->prepare(
            "SELECT si.*, p.name FROM sale_items si JOIN products p ON si.product_id = p.id WHERE si.sale_id = ? AND si.status != 'refunded'"
        );
        $stmt->execute([$saleId]);
        return $stmt->fetchAll();
    }

    public static function saleItemsForModal(int $saleId): array
    {
        $stmt = self::db()->prepare(
            "SELECT si.*, COALESCE(p.name, 'Custom Item') as name, c.type as cat_type
             FROM sale_items si
             LEFT JOIN products p ON si.product_id = p.id
             LEFT JOIN categories c ON p.category_id = c.id
             WHERE si.sale_id = ? AND si.status != 'voided'"
        );
        $stmt->execute([$saleId]);
        return $stmt->fetchAll();
    }
}
