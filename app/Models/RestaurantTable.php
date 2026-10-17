<?php

namespace App\Models;

final class RestaurantTable extends Model
{
    public static function allWithLocation(): array
    {
        return self::db()->query(
            "SELECT rt.*, l.name as location_name
             FROM restaurant_tables rt
             JOIN locations l ON rt.location_id = l.id
             ORDER BY l.name ASC, rt.zone_name ASC, rt.table_name ASC"
        )->fetchAll();
    }

    public static function create(int $locId, string $zone, string $name, int $cap): void
    {
        self::db()->prepare('INSERT INTO restaurant_tables (location_id, zone_name, table_name, capacity) VALUES (?, ?, ?, ?)')
            ->execute([$locId, $zone, $name, $cap]);
    }

    public static function delete(int $id): void
    {
        self::db()->prepare('DELETE FROM restaurant_tables WHERE id = ?')->execute([$id]);
    }

    /** Tables at this store that are not held by an unpaid bill. The current choice stays listed. */
    public static function freeForLocation(int $locationId, int $keepId = 0): array
    {
        $stmt = self::db()->prepare(
            'SELECT rt.* FROM restaurant_tables rt
             WHERE rt.location_id = ?
               AND (
                 rt.id = ?
                 OR NOT EXISTS (
                   SELECT 1 FROM sales s
                   WHERE s.table_id = rt.id AND s.location_id = ? AND s.payment_status = "pending"
                 )
               )
             ORDER BY rt.zone_name ASC, rt.table_name ASC'
        );
        $stmt->execute([$locationId, $keepId, $locationId]);
        return $stmt->fetchAll() ?: [];
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT rt.*, l.name AS location_name
             FROM restaurant_tables rt
             JOIN locations l ON l.id = rt.location_id
             WHERE rt.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function groupedForLocation(int $locationId): array
    {
        $restaurantTables = [];
        try {
            $stmt = self::db()->prepare(
                'SELECT * FROM restaurant_tables WHERE location_id = ? ORDER BY zone_name ASC, table_name ASC'
            );
            $stmt->execute([$locationId]);
            foreach ($stmt->fetchAll() as $t) {
                $restaurantTables[$t['zone_name']][] = $t;
            }
        } catch (\Throwable $e) {
        }
        return $restaurantTables;
    }
}
