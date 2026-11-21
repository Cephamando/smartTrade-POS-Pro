<?php

namespace App\Models;

use App\Helpers\Movement;

final class Location extends Model
{
    public static function ensureSchema(): void
    {
        $pdo = self::db();
        
        // Auto-migrate POS flag
        $stmt = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'locations' AND COLUMN_NAME = 'can_sell'");
        if ((int)$stmt->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE locations ADD COLUMN can_sell TINYINT(1) NOT NULL DEFAULT 1");
            $pdo->exec("UPDATE locations SET can_sell = 0 WHERE type IN ('warehouse', 'kitchen')");
        }

        // Auto-migrate Goods Receiver flag
        $stmt2 = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'locations' AND COLUMN_NAME = 'can_receive'");
        if ((int)$stmt2->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE locations ADD COLUMN can_receive TINYINT(1) NOT NULL DEFAULT 0");
            $pdo->exec("UPDATE locations SET can_receive = 1 WHERE type IN ('warehouse', 'kitchen')");
        }
    }

    /**
     * The locations staff can pick. A disabled location is left out of every
     * list here; find() and nameById() still resolve it so history keeps its name.
     */
    public static function all(): array
    {
        return self::db()->query('SELECT * FROM locations WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
    }

    /** Every location for the Locations page, disabled ones last, with the stock each still holds. */
    public static function allWithDisabled(): array
    {
        return self::db()->query(
            'SELECT l.*,
                    (SELECT COUNT(*) FROM inventory i WHERE i.location_id = l.id AND i.quantity > 0) AS stock_lines
             FROM locations l
             ORDER BY l.is_active DESC, l.name ASC'
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = self::db()->prepare('SELECT * FROM locations WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function names(): array
    {
        return self::db()->query('SELECT id, name FROM locations WHERE is_active = 1 ORDER BY name ASC')->fetchAll();
    }

    public static function productionSource(?int $preferredId = null): int
    {
        $pdo = self::db();
        if ($preferredId) {
            $stmt = $pdo->prepare("SELECT id, type FROM locations WHERE id = ? AND is_active = 1");
            $stmt->execute([$preferredId]);
            $row = $stmt->fetch();
            if ($row && in_array($row['type'], ['kitchen', 'warehouse'], true)) {
                return (int)$row['id'];
            }
        }
        $id = $pdo->query(
            "SELECT id FROM locations WHERE type IN ('kitchen', 'warehouse') AND is_active = 1 ORDER BY FIELD(type, 'kitchen', 'warehouse'), id ASC LIMIT 1"
        )->fetchColumn();
        if ($id) {
            return (int)$id;
        }
        return (int)($preferredId ?: 0);
    }

    public static function sellable(): array
    {
        return self::db()->query('SELECT id, name FROM locations WHERE can_sell = 1 AND is_active = 1 ORDER BY name ASC')->fetchAll();
    }

    /** The sites that are authorized to receive vendor stock. */
    public static function vendorReceiveSites(): array
    {
        return self::db()->query(
            "SELECT * FROM locations WHERE can_receive = 1 AND is_active = 1 ORDER BY name ASC"
        )->fetchAll() ?: [];
    }

    public static function nameById(int $id): string
    {
        $stmt = self::db()->prepare('SELECT name FROM locations WHERE id = ?');
        $stmt->execute([$id]);
        return (string)($stmt->fetchColumn() ?: 'Unknown');
    }

    public static function create(string $name, string $type, string $address, int $canSell, int $canReceive, string $reviewUrl = ''): void
    {
        self::db()->prepare('INSERT INTO locations (name, type, address, can_sell, can_receive, google_review_url) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([$name, $type, $address, $canSell, $canReceive, $reviewUrl !== '' ? $reviewUrl : null]);
    }

    public static function update(int $id, string $name, string $type, string $address, int $canSell, int $canReceive, string $reviewUrl = ''): void
    {
        self::db()->prepare('UPDATE locations SET name=?, type=?, address=?, can_sell=?, can_receive=?, google_review_url=? WHERE id=?')
            ->execute([$name, $type, $address, $canSell, $canReceive, $reviewUrl !== '' ? $reviewUrl : null, $id]);
    }

    public static function delete(int $id): string
    {
        $pdo = self::db();
        $checkStock = $pdo->prepare('SELECT id FROM location_stock WHERE location_id = ? AND quantity > 0 LIMIT 1');
        $checkStock->execute([$id]);
        if ($checkStock->fetchColumn()) {
            return 'stock';
        }
        $checkSales = $pdo->prepare('SELECT id FROM sales WHERE location_id = ? LIMIT 1');
        $checkSales->execute([$id]);
        if ($checkSales->fetchColumn()) {
            return 'sales';
        }
        $pdo->prepare('DELETE FROM locations WHERE id = ?')->execute([$id]);
        return 'ok';
    }

    /**
     * Takes a location out of use. With a destination, everything it has on
     * hand is booked across as finished transfers first, and the whole thing
     * rolls back if any line cannot go.
     *
     * @return array{name:string,dest:string,lines:int}
     */
    public static function disable(int $id, int $destId, int $userId): array
    {
        $pdo = self::db();
        $loc = self::find($id);
        if (!$loc) {
            throw new \Exception('That location no longer exists.');
        }
        $name = (string)$loc['name'];
        if ((int)$loc['is_active'] !== 1) {
            throw new \Exception($name . ' is already disabled.');
        }

        $others = $pdo->prepare('SELECT COUNT(*) FROM locations WHERE is_active = 1 AND id <> ?');
        $others->execute([$id]);
        if ((int)$others->fetchColumn() === 0) {
            throw new \Exception($name . ' is the only active location. Add or enable another one first.');
        }

        $shifts = $pdo->prepare("SELECT COUNT(*) FROM shifts WHERE location_id = ? AND status = 'open'");
        $shifts->execute([$id]);
        $openShifts = (int)$shifts->fetchColumn();
        if ($openShifts > 0) {
            throw new \Exception(
                $name . ' still has ' . $openShifts . ' open shift' . ($openShifts === 1 ? '' : 's') . '. Close '
                . ($openShifts === 1 ? 'it' : 'them') . ' first.'
            );
        }

        $moving = $pdo->prepare(
            "SELECT COUNT(*) FROM inventory_transfers
             WHERE status IN ('pending', 'in_transit') AND (source_location_id = ? OR dest_location_id = ?)"
        );
        $moving->execute([$id, $id]);
        $openTransfers = (int)$moving->fetchColumn();
        if ($openTransfers > 0) {
            throw new \Exception(
                $name . ' has ' . $openTransfers . ' stock transfer' . ($openTransfers === 1 ? '' : 's')
                . ' still waiting or on the way. Finish or cancel ' . ($openTransfers === 1 ? 'it' : 'them') . ' first.'
            );
        }

        $dest = null;
        if ($destId > 0) {
            $dest = self::find($destId);
            if (!$dest || $destId === $id || (int)$dest['is_active'] !== 1) {
                throw new \Exception('Choose an active location to receive the stock.');
            }
        }

        $lines = 0;
        $pdo->beginTransaction();
        try {
            if ($dest) {
                $rows = $pdo->prepare('SELECT product_id, quantity FROM inventory WHERE location_id = ? AND quantity > 0 FOR UPDATE');
                $rows->execute([$id]);
                $stock = [];
                $refused = [];
                foreach ($rows->fetchAll() as $row) {
                    $product = Product::movementContext((int)$row['product_id']);
                    if (!$product) {
                        continue;
                    }
                    if (!Movement::transfer($product, $loc, $dest)['ok']) {
                        $refused[] = (string)$product['name'];
                        continue;
                    }
                    $stock[] = $row;
                }
                if ($refused) {
                    $shown = implode(', ', array_slice($refused, 0, 5));
                    $more = count($refused) - 5;
                    throw new \Exception(
                        (string)$dest['name'] . ' is a sales point and cannot hold raw material: ' . $shown
                        . ($more > 0 ? ' and ' . $more . ' more' : '')
                        . '. Send the stock to a kitchen or warehouse, or leave it where it is. Nothing was moved.'
                    );
                }
                $transferIds = [];
                foreach ($stock as $row) {
                    $transferIds[] = Transfer::moveNow($id, $destId, (int)$row['product_id'], (float)$row['quantity'], $userId);
                    $lines++;
                }
                if ($transferIds) {
                    $marks = implode(',', array_fill(0, count($transferIds), '?'));
                    $pdo->prepare(
                        "UPDATE inventory_logs SET reason = ?
                         WHERE action_type IN ('transfer_out', 'transfer_in') AND reference_id IN ($marks)"
                    )->execute(array_merge(
                        [$name . ' disabled, stock sent to ' . $dest['name']],
                        $transferIds
                    ));
                }
            }
            $pdo->prepare('UPDATE locations SET is_active = 0 WHERE id = ?')->execute([$id]);
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'name' => $name,
            'dest' => $dest ? (string)$dest['name'] : '',
            'lines' => $lines,
        ];
    }

    /** Puts a disabled location back into every picker. Returns its name, or '' when there was nothing to do. */
    public static function enable(int $id): string
    {
        $loc = self::find($id);
        if (!$loc || (int)$loc['is_active'] === 1) {
            return '';
        }
        self::db()->prepare('UPDATE locations SET is_active = 1 WHERE id = ?')->execute([$id]);
        return (string)$loc['name'];
    }
}
