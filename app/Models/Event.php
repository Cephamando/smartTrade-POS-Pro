<?php

namespace App\Models;

use App\Helpers\Media;
use PDO;

final class Event extends Model
{
    private static bool $ready = false;

    public static function ensureSchema(): void
    {
        if (self::$ready) {
            return;
        }
        $pdo = self::db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                location_id INT NULL,
                title VARCHAR(160) NOT NULL,
                slug VARCHAR(180) NOT NULL,
                summary VARCHAR(500) NOT NULL DEFAULT '',
                story MEDIUMTEXT NULL,
                cover_path VARCHAR(255) NULL,
                starts_at DATETIME NOT NULL,
                ends_at DATETIME NOT NULL,
                venue_note VARCHAR(255) NOT NULL DEFAULT '',
                visibility ENUM('private','published') NOT NULL DEFAULT 'private',
                status ENUM('draft','open','closed','cancelled') NOT NULL DEFAULT 'draft',
                capacity INT NOT NULL DEFAULT 0,
                deposit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_events_slug (slug),
                KEY idx_events_when (starts_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS event_options (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                group_name VARCHAR(80) NOT NULL DEFAULT 'Choice',
                name VARCHAR(160) NOT NULL,
                price DECIMAL(12,2) NOT NULL DEFAULT 0,
                capacity INT NOT NULL DEFAULT 0,
                sort_order INT NOT NULL DEFAULT 0,
                KEY idx_event_options (event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS event_guests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                name VARCHAR(160) NOT NULL,
                email VARCHAR(180) NOT NULL DEFAULT '',
                phone VARCHAR(40) NOT NULL DEFAULT '',
                party_size INT NOT NULL DEFAULT 1,
                token CHAR(32) NOT NULL,
                source ENUM('invited','applied') NOT NULL DEFAULT 'invited',
                status ENUM('invited','applied','accepted','waitlisted','declined','cancelled') NOT NULL DEFAULT 'invited',
                checked_in TINYINT(1) NOT NULL DEFAULT 0,
                note VARCHAR(500) NOT NULL DEFAULT '',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_guest_token (token),
                KEY idx_event_guests (event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS event_selections (
                id INT AUTO_INCREMENT PRIMARY KEY,
                guest_id INT NOT NULL,
                option_id INT NOT NULL,
                qty INT NOT NULL DEFAULT 1,
                UNIQUE KEY uq_guest_option (guest_id, option_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS event_payments (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                guest_id INT NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                purpose ENUM('deposit','balance') NOT NULL DEFAULT 'deposit',
                method ENUM('flutterwave','cash') NOT NULL,
                tx_ref VARCHAR(64) NULL,
                status ENUM('pending','paid','failed') NOT NULL DEFAULT 'pending',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_event_pay_tx (tx_ref),
                KEY idx_event_pay_guest (guest_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS event_messages (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                guest_id INT NULL,
                email VARCHAR(180) NOT NULL,
                subject VARCHAR(200) NOT NULL,
                body MEDIUMTEXT NOT NULL,
                status ENUM('queued','sent','failed') NOT NULL DEFAULT 'queued',
                error_text VARCHAR(255) NOT NULL DEFAULT '',
                sent_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                KEY idx_event_messages (event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS event_tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                title VARCHAR(200) NOT NULL,
                due_at DATETIME NULL,
                done TINYINT(1) NOT NULL DEFAULT 0,
                KEY idx_event_tasks (event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS event_agenda (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_id INT NOT NULL,
                starts_at DATETIME NOT NULL,
                title VARCHAR(200) NOT NULL,
                notes VARCHAR(500) NOT NULL DEFAULT '',
                KEY idx_event_agenda (event_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        self::$ready = true;
    }

    public static function save(?int $id, array $data): int
    {
        self::ensureSchema();
        $title = trim((string)($data['title'] ?? ''));
        if ($title === '') {
            throw new \RuntimeException('Give the event a name.');
        }
        $starts = self::dateTime((string)($data['starts_at'] ?? ''));
        $ends = self::dateTime((string)($data['ends_at'] ?? ''));
        if ($ends < $starts) {
            throw new \RuntimeException('The event must end after it starts.');
        }
        $visibility = ($data['visibility'] ?? '') === 'published' ? 'published' : 'private';
        $status = in_array($data['status'] ?? '', ['draft', 'open', 'closed', 'cancelled'], true) ? $data['status'] : 'draft';
        $cover = Media::url((string)($data['cover_path'] ?? ''));
        $cover = $cover === '' ? null : $cover;
        $fields = [
            (int)($data['location_id'] ?? 0) ?: null,
            $title,
            mb_substr(trim((string)($data['summary'] ?? '')), 0, 500),
            trim((string)($data['story'] ?? '')),
            $cover,
            $starts,
            $ends,
            mb_substr(trim((string)($data['venue_note'] ?? '')), 0, 255),
            $visibility,
            $status,
            max(0, (int)($data['capacity'] ?? 0)),
            max(0, round((float)($data['deposit_amount'] ?? 0), 2)),
        ];
        if ($id) {
            $fields[] = $id;
            self::db()->prepare(
                'UPDATE events SET location_id=?, title=?, summary=?, story=?, cover_path=?, starts_at=?, ends_at=?,
                    venue_note=?, visibility=?, status=?, capacity=?, deposit_amount=? WHERE id=?'
            )->execute($fields);
            return $id;
        }
        $fields[] = self::uniqueSlug($title);
        $fields[] = (int)($data['created_by'] ?? 0) ?: null;
        self::db()->prepare(
            'INSERT INTO events (location_id, title, summary, story, cover_path, starts_at, ends_at, venue_note,
                visibility, status, capacity, deposit_amount, slug, created_by)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute($fields);
        return (int)self::db()->lastInsertId();
    }

    public static function find(int $id): ?array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare(
            'SELECT e.*, l.name AS location_name FROM events e LEFT JOIN locations l ON l.id = e.location_id WHERE e.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function publicBySlug(string $slug): ?array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare(
            "SELECT e.*, l.name AS location_name FROM events e
             LEFT JOIN locations l ON l.id = e.location_id
             WHERE e.slug = ? AND e.visibility = 'published' AND e.status = 'open'"
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /** @return list<array<string,mixed>> */
    public static function all(): array
    {
        self::ensureSchema();
        return self::db()->query(
            'SELECT e.*, l.name AS location_name,
                    (SELECT COALESCE(SUM(party_size),0) FROM event_guests g WHERE g.event_id = e.id AND g.status = "accepted") AS accepted_size
             FROM events e LEFT JOIN locations l ON l.id = e.location_id
             ORDER BY e.starts_at DESC'
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function inMonth(string $month): array
    {
        self::ensureSchema();
        $start = $month . '-01 00:00:00';
        $end = date('Y-m-t 23:59:59', strtotime($start));
        $stmt = self::db()->prepare(
            'SELECT * FROM events WHERE starts_at <= ? AND ends_at >= ? ORDER BY starts_at ASC'
        );
        $stmt->execute([$end, $start]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function publishedOpen(): array
    {
        self::ensureSchema();
        return self::db()->query(
            "SELECT e.*, l.name AS location_name FROM events e
             LEFT JOIN locations l ON l.id = e.location_id
             WHERE e.visibility = 'published' AND e.status = 'open'
             ORDER BY e.starts_at ASC"
        )->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function options(int $eventId): array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare('SELECT * FROM event_options WHERE event_id = ? ORDER BY group_name, sort_order, name');
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    public static function saveOption(int $eventId, string $group, string $name, float $price, int $capacity): void
    {
        self::ensureSchema();
        $name = trim($name);
        $group = trim($group) ?: 'Choice';
        if ($name === '') {
            throw new \RuntimeException('Name the choice.');
        }
        self::db()->prepare(
            'INSERT INTO event_options (event_id, group_name, name, price, capacity) VALUES (?,?,?,?,?)'
        )->execute([$eventId, mb_substr($group, 0, 80), mb_substr($name, 0, 160), max(0, round($price, 2)), max(0, $capacity)]);
    }

    public static function deleteOption(int $eventId, int $optionId): void
    {
        self::ensureSchema();
        self::db()->prepare('DELETE FROM event_selections WHERE option_id = ?')->execute([$optionId]);
        self::db()->prepare('DELETE FROM event_options WHERE id = ? AND event_id = ?')->execute([$optionId, $eventId]);
    }

    /** @return list<array<string,mixed>> */
    public static function guests(int $eventId): array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare('SELECT * FROM event_guests WHERE event_id = ? ORDER BY created_at DESC');
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    public static function guest(int $id): ?array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare('SELECT * FROM event_guests WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function guestByToken(string $token): ?array
    {
        self::ensureSchema();
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            return null;
        }
        $stmt = self::db()->prepare('SELECT * FROM event_guests WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function addGuest(int $eventId, string $name, string $email, string $phone, int $party, string $source, string $status, string $note = ''): array
    {
        self::ensureSchema();
        $name = trim($name);
        if ($name === '') {
            throw new \RuntimeException('Enter the guest name.');
        }
        if (!in_array($source, ['invited', 'applied'], true)) {
            $source = 'invited';
        }
        if (!in_array($status, ['invited', 'applied', 'accepted', 'waitlisted', 'declined'], true)) {
            $status = $source === 'applied' ? 'applied' : 'invited';
        }
        $token = bin2hex(random_bytes(16));
        self::db()->prepare(
            'INSERT INTO event_guests (event_id, name, email, phone, party_size, token, source, status, note)
             VALUES (?,?,?,?,?,?,?,?,?)'
        )->execute([
            $eventId,
            mb_substr($name, 0, 160),
            mb_substr(trim($email), 0, 180),
            mb_substr(trim($phone), 0, 40),
            max(1, min(50, $party)),
            $token,
            $source,
            $status,
            mb_substr(trim($note), 0, 500),
        ]);
        return self::guest((int)self::db()->lastInsertId()) ?? [];
    }

    public static function updateGuestDetails(int $eventId, int $guestId, int $party, string $note): void
    {
        self::ensureSchema();
        self::db()->prepare('UPDATE event_guests SET party_size = ?, note = ? WHERE id = ? AND event_id = ?')->execute([
            max(1, min(50, $party)),
            mb_substr(trim($note), 0, 500),
            $guestId,
            $eventId,
        ]);
    }

    public static function setGuestStatus(int $eventId, int $guestId, string $status): void
    {
        self::ensureSchema();
        if (!in_array($status, ['invited', 'applied', 'accepted', 'waitlisted', 'declined', 'cancelled'], true)) {
            throw new \RuntimeException('Choose a valid reply.');
        }
        self::db()->prepare('UPDATE event_guests SET status = ? WHERE id = ? AND event_id = ?')->execute([$status, $guestId, $eventId]);
    }

    public static function setCheckedIn(int $eventId, int $guestId, bool $in): void
    {
        self::ensureSchema();
        self::db()->prepare('UPDATE event_guests SET checked_in = ? WHERE id = ? AND event_id = ?')->execute([$in ? 1 : 0, $guestId, $eventId]);
    }

    /** @return list<int> */
    public static function selectionIds(int $guestId): array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare('SELECT option_id FROM event_selections WHERE guest_id = ?');
        $stmt->execute([$guestId]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** @param list<int> $optionIds */
    public static function saveSelections(int $guestId, int $eventId, array $optionIds): void
    {
        self::ensureSchema();
        $allowed = [];
        foreach (self::options($eventId) as $option) {
            $allowed[(int)$option['id']] = $option;
        }
        $chosen = [];
        $seenGroups = [];
        foreach ($optionIds as $id) {
            $id = (int)$id;
            if (!isset($allowed[$id])) {
                continue;
            }
            $group = (string)$allowed[$id]['group_name'];
            if (isset($seenGroups[$group])) {
                continue;
            }
            $seenGroups[$group] = true;
            $cap = (int)$allowed[$id]['capacity'];
            if ($cap > 0 && self::optionTaken($id, $guestId) >= $cap) {
                throw new \RuntimeException($allowed[$id]['name'] . ' is already full.');
            }
            $chosen[] = $id;
        }
        $pdo = self::db();
        $pdo->prepare('DELETE FROM event_selections WHERE guest_id = ?')->execute([$guestId]);
        $ins = $pdo->prepare('INSERT INTO event_selections (guest_id, option_id, qty) VALUES (?,?,1)');
        foreach ($chosen as $id) {
            $ins->execute([$guestId, $id]);
        }
    }

    public static function optionTaken(int $optionId, int $exceptGuestId = 0): int
    {
        self::ensureSchema();
        $stmt = self::db()->prepare(
            'SELECT COUNT(*) FROM event_selections s
             JOIN event_guests g ON g.id = s.guest_id
             WHERE s.option_id = ? AND g.id <> ? AND g.status IN ("accepted","applied","invited")'
        );
        $stmt->execute([$optionId, $exceptGuestId]);
        return (int)$stmt->fetchColumn();
    }

    public static function acceptedSize(int $eventId): int
    {
        self::ensureSchema();
        $stmt = self::db()->prepare('SELECT COALESCE(SUM(party_size),0) FROM event_guests WHERE event_id = ? AND status = "accepted"');
        $stmt->execute([$eventId]);
        return (int)$stmt->fetchColumn();
    }

    /** @return list<array<string,mixed>> */
    public static function payments(int $eventId): array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare(
            'SELECT p.*, g.name AS guest_name FROM event_payments p
             JOIN event_guests g ON g.id = p.guest_id
             WHERE p.event_id = ? ORDER BY p.created_at DESC'
        );
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function paymentsForGuest(int $guestId): array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare('SELECT * FROM event_payments WHERE guest_id = ? ORDER BY id DESC');
        $stmt->execute([$guestId]);
        return $stmt->fetchAll();
    }

    public static function paidTotal(int $guestId): float
    {
        self::ensureSchema();
        $stmt = self::db()->prepare('SELECT COALESCE(SUM(amount),0) FROM event_payments WHERE guest_id = ? AND status = "paid"');
        $stmt->execute([$guestId]);
        return (float)$stmt->fetchColumn();
    }

    public static function selectionTotal(int $guestId): float
    {
        self::ensureSchema();
        $stmt = self::db()->prepare(
            'SELECT COALESCE(SUM(o.price),0) FROM event_selections s JOIN event_options o ON o.id = s.option_id WHERE s.guest_id = ?'
        );
        $stmt->execute([$guestId]);
        return (float)$stmt->fetchColumn();
    }

    /** @return array{amount: float, purpose: string} */
    public static function nextCharge(array $event, int $guestId): array
    {
        $deposit = (float)$event['deposit_amount'];
        $target = max($deposit, self::selectionTotal($guestId));
        $paid = self::paidTotal($guestId);
        $due = max(0, round($target - $paid, 2));
        $depositLeft = max(0, round($deposit - $paid, 2));
        if ($depositLeft > 0) {
            return ['amount' => min($due, $depositLeft), 'purpose' => 'deposit'];
        }
        return ['amount' => $due, 'purpose' => 'balance'];
    }

    public static function addCashPayment(int $eventId, int $guestId, float $amount, string $purpose): void
    {
        self::ensureSchema();
        $guest = self::guest($guestId);
        if (!$guest || (int)$guest['event_id'] !== $eventId) {
            throw new \RuntimeException('Choose a guest.');
        }
        if ($amount <= 0) {
            throw new \RuntimeException('Enter an amount greater than zero.');
        }
        $purpose = $purpose === 'balance' ? 'balance' : 'deposit';
        self::db()->prepare(
            'INSERT INTO event_payments (event_id, guest_id, amount, purpose, method, status) VALUES (?,?,?,?, "cash", "paid")'
        )->execute([$eventId, $guestId, round($amount, 2), $purpose]);
    }

    public static function openOnlinePayment(int $eventId, int $guestId, float $amount, string $purpose): int
    {
        self::ensureSchema();
        $purpose = $purpose === 'balance' ? 'balance' : 'deposit';
        self::db()->prepare(
            'INSERT INTO event_payments (event_id, guest_id, amount, purpose, method, status) VALUES (?,?,?,?, "flutterwave", "pending")'
        )->execute([$eventId, $guestId, round($amount, 2), $purpose]);
        return (int)self::db()->lastInsertId();
    }

    public static function attachTx(int $paymentId, string $txRef): void
    {
        self::ensureSchema();
        self::db()->prepare('UPDATE event_payments SET tx_ref = ? WHERE id = ?')->execute([$txRef, $paymentId]);
    }

    public static function settleTx(string $txRef, string $gatewayStatus): void
    {
        self::ensureSchema();
        if (!str_starts_with($txRef, 'EVT-')) {
            return;
        }
        $status = $gatewayStatus === 'successful' ? 'paid' : ($gatewayStatus === 'failed' ? 'failed' : 'pending');
        if ($status === 'pending') {
            return;
        }
        self::db()->prepare('UPDATE event_payments SET status = ? WHERE tx_ref = ? AND status = "pending"')->execute([$status, $txRef]);
    }

    /** @return list<array<string,mixed>> */
    public static function messages(int $eventId): array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare('SELECT * FROM event_messages WHERE event_id = ? ORDER BY id DESC LIMIT 40');
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    public static function logMessage(int $eventId, ?int $guestId, string $email, string $subject, string $body, string $status, string $error = ''): void
    {
        self::ensureSchema();
        if (!in_array($status, ['queued', 'sent', 'failed'], true)) {
            $status = 'failed';
        }
        self::db()->prepare(
            'INSERT INTO event_messages (event_id, guest_id, email, subject, body, status, error_text, sent_at)
             VALUES (?,?,?,?,?,?,?,?)'
        )->execute([
            $eventId,
            $guestId,
            mb_substr($email, 0, 180),
            mb_substr($subject, 0, 200),
            $body,
            $status,
            mb_substr($error, 0, 255),
            $status === 'sent' ? date('Y-m-d H:i:s') : null,
        ]);
    }

    /** @return list<array<string,mixed>> */
    public static function guestsForMail(int $eventId, string $audience): array
    {
        self::ensureSchema();
        $sql = 'SELECT * FROM event_guests WHERE event_id = ? AND email <> ""';
        $params = [$eventId];
        if ($audience !== 'all') {
            $sql .= ' AND status = ?';
            $params[] = $audience;
        } else {
            $sql .= ' AND status NOT IN ("declined","cancelled")';
        }
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /** @return list<array<string,mixed>> */
    public static function tasks(int $eventId): array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare('SELECT * FROM event_tasks WHERE event_id = ? ORDER BY done ASC, due_at IS NULL, due_at ASC, id ASC');
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    public static function addTask(int $eventId, string $title, ?string $due): void
    {
        self::ensureSchema();
        $title = trim($title);
        if ($title === '') {
            throw new \RuntimeException('Name the task.');
        }
        $dueAt = $due ? self::dateTime($due) : null;
        self::db()->prepare('INSERT INTO event_tasks (event_id, title, due_at) VALUES (?,?,?)')->execute([$eventId, mb_substr($title, 0, 200), $dueAt]);
    }

    public static function toggleTask(int $eventId, int $taskId): void
    {
        self::ensureSchema();
        self::db()->prepare('UPDATE event_tasks SET done = 1 - done WHERE id = ? AND event_id = ?')->execute([$taskId, $eventId]);
    }

    /** @return list<array<string,mixed>> */
    public static function agenda(int $eventId): array
    {
        self::ensureSchema();
        $stmt = self::db()->prepare('SELECT * FROM event_agenda WHERE event_id = ? ORDER BY starts_at ASC');
        $stmt->execute([$eventId]);
        return $stmt->fetchAll();
    }

    public static function addAgenda(int $eventId, string $when, string $title, string $notes): void
    {
        self::ensureSchema();
        $title = trim($title);
        if ($title === '') {
            throw new \RuntimeException('Name the agenda item.');
        }
        self::db()->prepare('INSERT INTO event_agenda (event_id, starts_at, title, notes) VALUES (?,?,?,?)')
            ->execute([$eventId, self::dateTime($when), mb_substr($title, 0, 200), mb_substr(trim($notes), 0, 500)]);
    }

    public static function deleteAgenda(int $eventId, int $id): void
    {
        self::ensureSchema();
        self::db()->prepare('DELETE FROM event_agenda WHERE id = ? AND event_id = ?')->execute([$id, $eventId]);
    }

    private static function dateTime(string $value): string
    {
        $value = str_replace('T', ' ', trim($value));
        $ts = strtotime($value);
        if ($ts === false) {
            throw new \RuntimeException('Enter a valid date and time.');
        }
        return date('Y-m-d H:i:s', $ts);
    }

    private static function uniqueSlug(string $title): string
    {
        $base = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? '', '-'));
        $base = $base !== '' ? $base : 'event';
        $base = substr($base, 0, 150);
        $slug = $base;
        $n = 2;
        $stmt = self::db()->prepare('SELECT id FROM events WHERE slug = ?');
        while (true) {
            $stmt->execute([$slug]);
            if (!$stmt->fetch()) {
                return $slug;
            }
            $slug = $base . '-' . $n;
            $n++;
        }
    }
}
