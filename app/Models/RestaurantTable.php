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
