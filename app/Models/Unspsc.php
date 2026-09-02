<?php

namespace App\Models;

final class Unspsc extends Model
{
    public const BUNDLED_FILENAME = 'UNSPSC-Classification-Codes.xlsx';

    public static function ensureTable(): void
    {
        $pdo = self::db();
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS zra_item_classes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                item_cls_cd VARCHAR(16) NOT NULL,
                item_cls_nm VARCHAR(500) NOT NULL,
                item_cls_lvl INT DEFAULT 0,
                item_cls_path VARCHAR(700) DEFAULT NULL,
                search_text TEXT DEFAULT NULL,
                tax_ty_cd VARCHAR(10) DEFAULT NULL,
                use_yn CHAR(1) DEFAULT 'Y',
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uniq_cls (item_cls_cd),
                KEY idx_cls_lvl (item_cls_lvl)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        self::migrateColumns($pdo);
    }

    public static function bundledFilePath(): ?string
    {
        $candidates = [
            BASE_PATH . '/docker/storage/other/' . self::BUNDLED_FILENAME,
            BASE_PATH . '/storage/other/' . self::BUNDLED_FILENAME,
        ];
        foreach ($candidates as $path) {
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }
        return null;
    }

    public static function count(): int
    {
        self::ensureTable();
        return (int)self::db()->query('SELECT COUNT(*) FROM zra_item_classes')->fetchColumn();
    }

    public static function levelLabel(int $level): string
    {
        return match ($level) {
            1 => 'Segment',
            2 => 'Family',
            3 => 'Class',
            4 => 'Commodity',
            default => 'Level ' . $level,
        };
    }

    public static function search(string $q, int $limit = 30): array
    {
        self::ensureTable();
        $limit = max(1, min(50, $limit));
        $q = trim($q);
        if ($q === '') {
            return [];
        }

        $codeDigits = preg_replace('/\D/', '', $q) ?? '';
        $ft = self::fulltextQuery($q);
        $hasFt = self::hasFulltext() && $ft !== '';

        $where = ["use_yn = 'Y'"];
        $params = [];
        $or = [];
        if ($codeDigits !== '') {
            $or[] = 'item_cls_cd LIKE ?';
            $params[] = $codeDigits . '%';
        }
        if ($hasFt) {
            $or[] = 'MATCH(item_cls_nm, search_text) AGAINST (? IN BOOLEAN MODE)';
            $params[] = $ft;
        } else {
            $or[] = '(item_cls_nm LIKE ? OR search_text LIKE ?)';
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
        }
        $where[] = '(' . implode(' OR ', $or) . ')';

        $order = '(LENGTH(item_cls_cd) = 8) DESC, item_cls_lvl DESC, item_cls_nm ASC';
        $extra = [];
        if ($codeDigits !== '') {
            $order = '(item_cls_cd = ?) DESC, (item_cls_cd LIKE ?) DESC, ' . $order;
            $extra[] = $codeDigits;
            $extra[] = $codeDigits . '%';
        }

        $sql = 'SELECT item_cls_cd, item_cls_nm, item_cls_lvl, item_cls_path, tax_ty_cd
                FROM zra_item_classes
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ' . $order . '
                LIMIT ' . $limit;
        $stmt = self::db()->prepare($sql);
        $stmt->execute(array_merge($params, $extra));
        return array_map([self::class, 'presentRow'], $stmt->fetchAll());
    }

    public static function page(string $q, int $page, int $perPage = 50): array
    {
        self::ensureTable();
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;
        $where = "WHERE use_yn = 'Y'";
        $params = [];
        if ($q !== '') {
            $ors = ['c.item_cls_nm LIKE ?', 'IFNULL(c.search_text, \'\') LIKE ?'];
            $like = '%' . $q . '%';
            $params[] = $like;
            $params[] = $like;
            $digits = preg_replace('/\D/', '', $q) ?? '';
            if ($digits !== '') {
                $ors[] = 'c.item_cls_cd LIKE ?';
                $params[] = '%' . $digits . '%';
            }
            $where .= ' AND (' . implode(' OR ', $ors) . ')';
        }
        $countStmt = self::db()->prepare("SELECT COUNT(*) FROM zra_item_classes c $where");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $sql = "SELECT c.item_cls_cd, c.item_cls_nm, c.item_cls_lvl, c.item_cls_path, c.tax_ty_cd,
                       (SELECT COUNT(*) FROM products p WHERE p.unspsc_code = c.item_cls_cd) AS linked_count
                FROM zra_item_classes c
                $where
                ORDER BY (LENGTH(c.item_cls_cd) = 8) DESC, c.item_cls_lvl DESC, c.item_cls_nm ASC
                LIMIT $perPage OFFSET $offset";
        $stmt = self::db()->prepare($sql);
        $stmt->execute($params);
        $rows = array_map([self::class, 'presentRow'], $stmt->fetchAll());
        return [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => max(1, (int)ceil($total / $perPage)),
        ];
    }

    public static function importFile(string $tmpName, string $originalName): array
    {
        self::ensureTable();
        @set_time_limit(0);
        ini_set('memory_limit', '512M');

        $ext = strtolower(pathinfo($originalName !== '' ? $originalName : $tmpName, PATHINFO_EXTENSION));
        if ($ext === 'xlsx' && \App\Helpers\Spreadsheet::isUnspscWorkbook($tmpName)) {
            return self::importOfficialWorkbook($tmpName);
        }

        $rows = \App\Helpers\Spreadsheet::read($tmpName, $originalName);
        if (!$rows) {
            throw new \Exception('The file is empty or could not be read.');
        }
        $header = array_shift($rows);
        $map = \App\Helpers\Spreadsheet::mapUnspscColumns($header);
        $pdo = self::db();
        $batch = [];
        $imported = 0;
        $pdo->beginTransaction();
        try {
            foreach ($rows as $row) {
                $code = self::normalizeCode(\App\Helpers\Spreadsheet::col($row, $map['code'] ?? 0));
                if ($code === '' || strlen($code) < 6) {
                    continue;
                }
                $name = trim(\App\Helpers\Spreadsheet::col($row, $map['name'] ?? 1));
                if ($name === '') {
                    $name = $code;
                }
                $level = (int)preg_replace('/\D/', '', \App\Helpers\Spreadsheet::col($row, $map['level'] ?? -1));
                if ($level <= 0) {
                    $level = self::levelFromCode($code);
                }
                $tax = trim(\App\Helpers\Spreadsheet::col($row, $map['tax'] ?? -1));
                $batch[] = [$code, $name, $level, '', $name, $tax === '' ? null : $tax];
                if (count($batch) >= 400) {
                    self::insertBatch($pdo, $batch);
                    $imported += count($batch);
                    $batch = [];
                }
            }
            if ($batch) {
                self::insertBatch($pdo, $batch);
                $imported += count($batch);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
        return ['imported' => $imported, 'total' => self::count()];
    }

    public static function assignToProduct(int $productId, string $code): void
    {
        $digits = self::normalizeCode($code);
        if ($digits === '') {
            throw new \Exception('Select a UNSPSC code.');
        }
        $pdo = self::db();
        $exists = $pdo->prepare('SELECT id FROM products WHERE id = ?');
        $exists->execute([$productId]);
        if (!$exists->fetchColumn()) {
            throw new \Exception('Product not found.');
        }
        $pdo->prepare('UPDATE products SET unspsc_code = ? WHERE id = ?')->execute([$digits, $productId]);
    }

    private static function importOfficialWorkbook(string $path): array
    {
        $workbook = \App\Helpers\Spreadsheet::openXlsx($path, ['SEGMENT', 'FAMILY', 'CLASS', 'COMMODITY']);
        $pdo = self::db();
        $imported = 0;
        $batch = [];
        $flush = static function () use (&$batch, &$imported, $pdo): void {
            if (!$batch) {
                return;
            }
            self::insertBatch($pdo, $batch);
            $imported += count($batch);
            $batch = [];
        };

        try {
            $segments = self::twoColMap($workbook, 'SEGMENT');
            $families = self::twoColMap($workbook, 'FAMILY');
            $classes = self::twoColMap($workbook, 'CLASS');
            if (\App\Helpers\Spreadsheet::resolveSheetName($workbook, 'COMMODITY') === null) {
                throw new \Exception('The workbook has no COMMODITY sheet.');
            }

            foreach ($segments as $code => $name) {
                $batch[] = [$code, $name, 1, '', $name, null];
                if (count($batch) >= 400) {
                    $flush();
                }
            }
            foreach ($families as $code => $name) {
                $parents = self::parentCodes($code);
                $segName = $segments[$parents['segment']] ?? '';
                $batch[] = [$code, $name, 2, $segName, trim($name . ' ' . $segName), null];
                if (count($batch) >= 400) {
                    $flush();
                }
            }
            foreach ($classes as $code => $name) {
                $parents = self::parentCodes($code);
                $segName = $segments[$parents['segment']] ?? '';
                $famName = $families[$parents['family']] ?? '';
                $path = implode(' › ', array_filter([$segName, $famName], static fn($v) => $v !== ''));
                $batch[] = [$code, $name, 3, $path, trim($name . ' ' . $famName . ' ' . $segName), null];
                if (count($batch) >= 400) {
                    $flush();
                }
            }
            $flush();

            \App\Helpers\Spreadsheet::eachSheetRow($workbook, 'COMMODITY', static function (array $row, int $i) use (
                &$batch,
                $flush,
                $segments,
                $families,
                $classes
            ): void {
                if ($i === 0) {
                    return;
                }
                $code = self::normalizeCode($row[0] ?? '');
                if (strlen($code) < 8) {
                    return;
                }
                $name = trim((string)($row[1] ?? ''));
                if ($name === '') {
                    $name = $code;
                }
                $parents = self::parentCodes($code);
                $segName = $segments[$parents['segment']] ?? '';
                $famName = $families[$parents['family']] ?? '';
                $clsName = $classes[$parents['class']] ?? '';
                $path = implode(' › ', array_filter([$segName, $famName, $clsName], static fn($v) => $v !== ''));
                $search = trim($name . ' ' . $clsName . ' ' . $famName . ' ' . $segName);
                $batch[] = [$code, $name, 4, $path, $search, null];
                if (count($batch) >= 400) {
                    $flush();
                }
            });
            $flush();
        } finally {
            \App\Helpers\Spreadsheet::closeXlsx($workbook);
        }

        return ['imported' => $imported, 'total' => self::count()];
    }

    /**
     * @return array<string,string>
     */
    private static function twoColMap(array $workbook, string $sheetName): array
    {
        if (\App\Helpers\Spreadsheet::resolveSheetName($workbook, $sheetName) === null) {
            return [];
        }
        $map = [];
        \App\Helpers\Spreadsheet::eachSheetRow($workbook, $sheetName, static function (array $row, int $i) use (&$map): void {
            if ($i === 0) {
                return;
            }
            $code = self::normalizeCode($row[0] ?? '');
            $name = trim((string)($row[1] ?? ''));
            if ($code === '' || $name === '') {
                return;
            }
            $map[$code] = $name;
        });
        return $map;
    }

    /**
     * @param list<array{0:string,1:string,2:int,3:string,4:string,5:?string}> $batch
     */
    private static function insertBatch(\PDO $pdo, array $batch): void
    {
        if (!$batch) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($batch), '(?, ?, ?, ?, ?, ?, \'Y\')'));
        $sql = "INSERT INTO zra_item_classes
                    (item_cls_cd, item_cls_nm, item_cls_lvl, item_cls_path, search_text, tax_ty_cd, use_yn)
                VALUES $placeholders
                ON DUPLICATE KEY UPDATE
                    item_cls_nm = VALUES(item_cls_nm),
                    item_cls_lvl = VALUES(item_cls_lvl),
                    item_cls_path = VALUES(item_cls_path),
                    search_text = VALUES(search_text),
                    tax_ty_cd = COALESCE(VALUES(tax_ty_cd), tax_ty_cd),
                    use_yn = 'Y'";
        $params = [];
        foreach ($batch as $row) {
            $params[] = $row[0];
            $params[] = mb_substr($row[1], 0, 500);
            $params[] = $row[2];
            $params[] = mb_substr($row[3], 0, 700);
            $params[] = $row[4];
            $params[] = $row[5];
        }
        $pdo->prepare($sql)->execute($params);
    }

    private static function normalizeCode(string $raw): string
    {
        $raw = trim($raw);
        if ($raw === '') {
            return '';
        }
        if (preg_match('/^(\d+)\.0+$/', $raw, $m)) {
            $raw = $m[1];
        } elseif (is_numeric($raw) && stripos($raw, 'e') !== false) {
            $raw = sprintf('%.0f', (float)$raw);
        }
        return preg_replace('/\D/', '', $raw) ?? '';
    }

    /**
     * @return array{segment:string,family:string,class:string}
     */
    private static function parentCodes(string $code): array
    {
        $code = str_pad(substr($code, 0, 8), 8, '0');
        return [
            'segment' => substr($code, 0, 2) . '000000',
            'family' => substr($code, 0, 4) . '0000',
            'class' => substr($code, 0, 6) . '00',
        ];
    }

    private static function levelFromCode(string $code): int
    {
        $code = str_pad(substr($code, 0, 8), 8, '0');
        if (substr($code, 2) === '000000') {
            return 1;
        }
        if (substr($code, 4) === '0000') {
            return 2;
        }
        if (substr($code, 6) === '00') {
            return 3;
        }
        return 4;
    }

    private static function presentRow(array $row): array
    {
        $level = (int)($row['item_cls_lvl'] ?? 0);
        $row['item_cls_lvl_label'] = self::levelLabel($level);
        $row['item_cls_path'] = (string)($row['item_cls_path'] ?? '');
        return $row;
    }

    private static function fulltextQuery(string $q): string
    {
        $words = preg_split('/[^a-zA-Z0-9]+/', $q, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = [];
        foreach ($words as $word) {
            if (strlen($word) < 3 || preg_match('/^\d+$/', $word)) {
                continue;
            }
            $clean = preg_replace('/[^a-zA-Z0-9]/', '', $word) ?? '';
            if ($clean === '') {
                continue;
            }
            $parts[] = '+' . $clean . '*';
        }
        return implode(' ', $parts);
    }

    private static function hasFulltext(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }
        try {
            $stmt = self::db()->query('SHOW INDEX FROM zra_item_classes');
            $has = false;
            while ($row = $stmt->fetch()) {
                if (($row['Key_name'] ?? '') === 'ft_unspsc_search') {
                    $has = true;
                    break;
                }
            }
        } catch (\Throwable $e) {
            $has = false;
        }
        return $has;
    }

    private static function migrateColumns(\PDO $pdo): void
    {
        try {
            $pdo->exec('ALTER TABLE zra_item_classes MODIFY item_cls_cd VARCHAR(16) NOT NULL');
        } catch (\Throwable $e) {
        }
        try {
            $pdo->exec('ALTER TABLE zra_item_classes MODIFY item_cls_nm VARCHAR(500) NOT NULL');
        } catch (\Throwable $e) {
        }
        self::addColumn($pdo, 'item_cls_path', 'VARCHAR(700) DEFAULT NULL AFTER item_cls_lvl');
        self::addColumn($pdo, 'search_text', 'TEXT DEFAULT NULL AFTER item_cls_path');
        try {
            $pdo->exec('ALTER TABLE zra_item_classes ADD KEY idx_cls_lvl (item_cls_lvl)');
        } catch (\Throwable $e) {
        }
        try {
            $pdo->exec('ALTER TABLE zra_item_classes ADD FULLTEXT KEY ft_unspsc_search (item_cls_nm, search_text)');
        } catch (\Throwable $e) {
        }
    }

    private static function addColumn(\PDO $pdo, string $column, string $definition): void
    {
        if (self::hasColumn($pdo, $column)) {
            return;
        }
        $safe = str_replace('`', '', $column);
        $pdo->exec("ALTER TABLE zra_item_classes ADD COLUMN `$safe` $definition");
    }

    private static function hasColumn(\PDO $pdo, string $column): bool
    {
        $stmt = $pdo->query('SHOW COLUMNS FROM zra_item_classes');
        while ($row = $stmt->fetch()) {
            if (strcasecmp((string)($row['Field'] ?? ''), $column) === 0) {
                return true;
            }
        }
        return false;
    }
}
