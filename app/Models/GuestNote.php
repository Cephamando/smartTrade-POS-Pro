<?php

namespace App\Models;

use App\Services\Mailer;
use PDOException;

final class GuestNote extends Model
{
    public static function reviewUrl(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (strlen($raw) > 300 || preg_match('/\s/', $raw) || !preg_match('#\Ahttps://#i', $raw)) {
            throw new \RuntimeException('The Google review link must be an https address, with no spaces, up to 300 characters.');
        }
        return $raw;
    }

    public static function forSale(int $saleId): ?array
    {
        if ($saleId <= 0) {
            return null;
        }
        $stmt = self::db()->prepare('SELECT * FROM guest_notes WHERE sale_id = ? LIMIT 1');
        $stmt->execute([$saleId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function record(int $locationId, string $mood, string $note, string $phone, string $source, ?int $saleId, ?int $tableId, ?int $customerId): void
    {
        $mood = $mood === 'glad' ? 'glad' : 'off';
        $note = trim($note);
        if (strlen($note) > 500) {
            $note = substr($note, 0, 500);
        }
        if ($mood === 'off' && strlen($note) < 3) {
            throw new \RuntimeException('Tell us what happened.');
        }
        $phone = substr((string)preg_replace('/[^0-9+]/', '', $phone), 0, 30);
        $source = $source === 'shop' ? 'shop' : 'table';
        try {
            self::db()->prepare(
                'INSERT INTO guest_notes (location_id, sale_id, table_id, customer_id, source, mood, note, contact_phone)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $locationId,
                $saleId && $saleId > 0 ? $saleId : null,
                $tableId && $tableId > 0 ? $tableId : null,
                $customerId && $customerId > 0 ? $customerId : null,
                $source,
                $mood,
                $note !== '' ? $note : null,
                $phone !== '' ? $phone : null,
            ]);
        } catch (PDOException $e) {
            if ($saleId && str_contains($e->getMessage(), 'uq_guest_sale')) {
                throw new \RuntimeException('This visit was already answered.');
            }
            throw $e;
        }
    }

    public static function list(): array
    {
        return self::db()->query(
            "SELECT g.*, l.name AS location_name, rt.table_name, rt.zone_name
             FROM guest_notes g
             LEFT JOIN locations l ON l.id = g.location_id
             LEFT JOIN restaurant_tables rt ON rt.id = g.table_id
             ORDER BY (g.mood = 'off') DESC, (g.handled_at IS NULL) DESC, g.created_at DESC
             LIMIT 200"
        )->fetchAll() ?: [];
    }

    public static function markHandled(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        self::db()->prepare('UPDATE guest_notes SET handled_at = NOW() WHERE id = ? AND handled_at IS NULL')->execute([$id]);
    }

    public static function openCount(): int
    {
        try {
            return (int)self::db()->query(
                "SELECT COUNT(*) FROM guest_notes WHERE mood = 'off' AND handled_at IS NULL"
            )->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }

    /** @return array{count: int, line: string} */
    public static function openAtLocation(int $locationId): array
    {
        $empty = ['count' => 0, 'line' => ''];
        if ($locationId <= 0) {
            return $empty;
        }
        try {
            $pdo = self::db();
            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM guest_notes
                 WHERE location_id = ? AND mood = 'off' AND handled_at IS NULL
                   AND created_at >= (NOW() - INTERVAL 1 DAY)"
            );
            $countStmt->execute([$locationId]);
            $count = (int)$countStmt->fetchColumn();
            if ($count <= 0) {
                return $empty;
            }
            $latest = $pdo->prepare(
                "SELECT g.note, rt.table_name, rt.zone_name
                 FROM guest_notes g
                 LEFT JOIN restaurant_tables rt ON rt.id = g.table_id
                 WHERE g.location_id = ? AND g.mood = 'off' AND g.handled_at IS NULL
                   AND g.created_at >= (NOW() - INTERVAL 1 DAY)
                 ORDER BY g.created_at DESC LIMIT 1"
            );
            $latest->execute([$locationId]);
            $row = $latest->fetch() ?: [];
            $where = trim((string)($row['zone_name'] ?? '') . ' ' . (string)($row['table_name'] ?? ''));
            $snippet = trim((string)($row['note'] ?? ''));
            if (strlen($snippet) > 80) {
                $snippet = substr($snippet, 0, 77) . '...';
            }
            $line = $count === 1 ? 'A guest wants a word' : ($count . ' guests want a word from the last day');
            if ($where !== '') {
                $line .= ' · ' . $where;
            }
            if ($snippet !== '') {
                $line .= ': ' . $snippet;
            }
            return ['count' => $count, 'line' => $line];
        } catch (\Throwable) {
            return $empty;
        }
    }

    public static function inviteCompletedSale(int $saleId): void
    {
        if ($saleId <= 0) {
            return;
        }
        try {
            $pdo = self::db();
            $stmt = $pdo->prepare(
                "SELECT s.id, s.order_source, s.status, s.visit_invited_at, s.visit_token, s.location_id,
                        c.email, c.name AS customer_name, l.name AS location_name
                 FROM sales s
                 LEFT JOIN customers c ON c.id = s.customer_id
                 LEFT JOIN locations l ON l.id = s.location_id
                 WHERE s.id = ?"
            );
            $stmt->execute([$saleId]);
            $sale = $stmt->fetch();
            if (!$sale || ($sale['order_source'] ?? '') !== 'portal' || ($sale['status'] ?? '') !== 'completed') {
                return;
            }
            if (!empty($sale['visit_invited_at']) || self::forSale($saleId)) {
                return;
            }
            $email = trim((string)($sale['email'] ?? ''));
            $settings = Setting::all();
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !Mailer::ready($settings)) {
                return;
            }
            $token = trim((string)($sale['visit_token'] ?? ''));
            if ($token === '') {
                $token = bin2hex(random_bytes(8));
                $pdo->prepare('UPDATE sales SET visit_token = ? WHERE id = ? AND (visit_token IS NULL OR visit_token = \'\')')
                    ->execute([$token, $saleId]);
            }
            $url = self::publicBase() . '/index.php?page=shop&view=visit&sale=' . $saleId . '&token=' . rawurlencode($token);
            $store = trim((string)($sale['location_name'] ?? '')) ?: 'the restaurant';
            $name = trim((string)($sale['customer_name'] ?? ''));
            $hello = $name !== '' ? 'Hello ' . $name . ',' : 'Hello,';
            $business = trim((string)($settings['business_name'] ?? '')) ?: $store;
            $text = $hello . "\n\n"
                . 'How was your visit at ' . $store . "?\n\n"
                . $url . "\n\n"
                . "It was lovely opens this store's Google review. Something was off sends a private note so the team can make it right. The Google link stays on the page either way.\n\n"
                . $business;
            Mailer::send($settings, $email, 'How was your visit?', $text);
            $pdo->prepare('UPDATE sales SET visit_invited_at = NOW() WHERE id = ? AND visit_invited_at IS NULL')->execute([$saleId]);
        } catch (\Throwable) {
            // A mail problem must not block handing the order over.
        }
    }

    private static function publicBase(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/');
        if ($dir === '.' || $dir === '/') {
            $dir = '';
        }
        return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $dir;
    }
}
