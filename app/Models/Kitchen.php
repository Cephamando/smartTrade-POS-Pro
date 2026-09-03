<?php

namespace App\Models;

final class Kitchen extends Model
{
    public static function kdsOrders(): array
    {
        $sql = "
            SELECT s.id as sale_id, s.created_at, u.username as server,
                   si.id as item_id, si.quantity, si.status, p.name as product_name,
                   COALESCE(c.name, 'Uncategorized') as category
            FROM sale_items si
            JOIN sales s ON si.sale_id = s.id
            JOIN products p ON si.product_id = p.id
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN users u ON s.user_id = u.id
            WHERE si.status IN ('pending', 'cooking')
            ORDER BY si.status DESC, s.created_at ASC
        ";
        $rows = self::db()->query($sql)->fetchAll();
        $orders = [];
        foreach ($rows as $r) {
            $sid = $r['sale_id'];
            if (!isset($orders[$sid])) {
                $orders[$sid] = [
                    'id' => $sid,
                    'time' => $r['created_at'],
                    'waiter' => ucfirst($r['server'] ?? 'Online'),
                    'items' => [],
                ];
            }
            $orders[$sid]['items'][] = [
                'id' => $r['item_id'],
                'name' => $r['product_name'],
                'qty' => (int)$r['quantity'],
                'status' => $r['status'],
            ];
        }
        return $orders;
    }

    public static function updateItemStatus(int $itemId, string $newStatus): void
    {
        $pdo = self::db();
        $pdo->prepare('UPDATE sale_items SET status = ? WHERE id = ?')->execute([$newStatus, $itemId]);
        if ($newStatus !== 'ready') {
            return;
        }
        $info = $pdo->prepare(
            "SELECT si.sale_id, p.name, s.split_group_id, s.payment_method
             FROM sale_items si
             JOIN products p ON si.product_id = p.id
             JOIN sales s ON si.sale_id = s.id
             WHERE si.id = ?"
        );
        $info->execute([$itemId]);
        $item = $info->fetch();
        if (!$item) {
            return;
        }
        $check = $pdo->prepare("SELECT id FROM pickup_notifications WHERE sale_id = ? AND item_name = ? AND status = 'ready'");
        $check->execute([$item['sale_id'], $item['name']]);
        if (!$check->fetch()) {
            $pdo->prepare("INSERT INTO pickup_notifications (sale_id, item_name, status) VALUES (?, ?, 'ready')")
                ->execute([$item['sale_id'], $item['name']]);
        }
        if (empty($item['split_group_id']) || $item['payment_method'] !== 'Online') {
            return;
        }
        $checkAllReady = $pdo->prepare(
            "SELECT COUNT(*) FROM sale_items WHERE sale_id = ? AND status != 'ready' AND status NOT IN ('voided', 'refunded')"
        );
        $checkAllReady->execute([$item['sale_id']]);
        if ((int)$checkAllReady->fetchColumn() !== 0) {
            return;
        }
        $webhookUrl = 'https://webhook.site/b97db7ed-2317-469d-952c-0e9badfd7a03';
        $payload = json_encode([
            'order_id' => $item['split_group_id'],
            'status' => 'ready_for_collection',
            'store_id' => 'MAIN_KITCHEN_001',
            'timestamp' => date('c'),
        ]);
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        curl_exec($ch);
        curl_close($ch);
    }

    public static function readyPickupOrders(int $locId): array
    {
        $sql = "SELECT
                    s.id as sale_id,
                    s.customer_name,
                    s.created_at,
                    u.full_name as server_name,
                    si.id as item_id,
                    si.quantity,
                    p.name as product_name
                FROM sale_items si
                JOIN sales s ON si.sale_id = s.id
                JOIN products p ON si.product_id = p.id
                LEFT JOIN users u ON s.user_id = u.id
                WHERE si.status = 'ready'
                AND si.fulfillment_status = 'uncollected'
                AND (s.split_group_id IS NULL OR s.split_group_id = '')
                " . ($locId ? 'AND s.location_id = ' . (int)$locId : '') . '
                ORDER BY s.created_at ASC';
        $rawItems = self::db()->query($sql)->fetchAll();
        $readyOrders = [];
        foreach ($rawItems as $row) {
            $sid = $row['sale_id'];
            if (!isset($readyOrders[$sid])) {
                $readyOrders[$sid] = [
                    'sale_id' => $sid,
                    'customer' => $row['customer_name'],
                    'created_at' => $row['created_at'],
                    'server' => $row['server_name'],
                    'items' => [],
                ];
            }
            $readyOrders[$sid]['items'][] = $row;
        }
        return $readyOrders;
    }

    public static function collectOrder(int $saleId, $collectedBy): void
    {
        $pdo = self::db();
        $pdo->prepare(
            "UPDATE sale_items
             SET fulfillment_status = 'collected', status = 'served'
             WHERE sale_id = ?
             AND fulfillment_status = 'uncollected'
             AND status = 'ready'"
        )->execute([$saleId]);
        $pdo->prepare('UPDATE pickup_notifications SET status = \'collected\', collected_by = ? WHERE sale_id = ?')
            ->execute([$collectedBy, $saleId]);
    }
}
