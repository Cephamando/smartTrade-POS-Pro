<?php

namespace App\Helpers;

final class Spreadsheet
{
    public static function read(string $path, string $originalName = ''): array
    {
        $name = $originalName !== '' ? $originalName : $path;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if ($ext === 'xlsx') {
            return self::readXlsx($path);
        }
        if ($ext === 'xls') {
            throw new \Exception('Excel 97-2003 (.xls) is not supported. Save the file as .xlsx or CSV.');
        }
        return self::readCsv($path);
    }

    public static function col(array $row, int $index): string
    {
        if ($index < 0) {
            return '';
        }
        return (string)($row[$index] ?? '');
    }

    public static function mapProductColumns(array $header): array
    {
        $map = [];
        foreach ($header as $i => $label) {
            $key = self::normalize($label);
            if ($key === '') {
                continue;
            }
            if (in_array($key, ['name', 'product', 'productname', 'itemname', 'item'], true) && !isset($map['name'])) {
                $map['name'] = $i;
            } elseif (in_array($key, ['category', 'categoryname', 'cat'], true)) {
                $map['category'] = $i;
            } elseif (in_array($key, ['sku', 'code', 'itemcode', 'barcode'], true)) {
                $map['sku'] = $i;
            } elseif (in_array($key, ['unit', 'uom', 'uomcode'], true)) {
                $map['unit'] = $i;
            } elseif (in_array($key, ['type', 'itemtype', 'producttype'], true)) {
                $map['type'] = $i;
            } elseif (in_array($key, ['cost', 'costprice', 'cost_price', 'buyingprice'], true)) {
                $map['cost'] = $i;
            } elseif (in_array($key, ['price', 'sellingprice', 'selling_price', 'sellprice'], true)) {
                $map['price'] = $i;
            } elseif (in_array($key, ['tax', 'taxclass', 'tax_class', 'zrataxclass'], true)) {
                $map['tax'] = $i;
            } elseif (in_array($key, ['unspsc', 'unspsccode', 'itemclass', 'itemclscd', 'commoditycode', 'commodity'], true)) {
                $map['unspsc'] = $i;
            } elseif (in_array($key, ['status', 'active', 'isactive'], true)) {
                $map['status'] = $i;
            }
        }
        if (!isset($map['name']) && isset($header[0])) {
            $map['name'] = 0;
        }
        return $map;
    }

    public static function mapUnspscColumns(array $header): array
    {
        $map = [];
        $codeKeys = [
            'commoditycode', 'commodity', 'unspsccode', 'unspsc', 'itemclscd',
            'classcode', 'itemclass', 'code', 'class', 'family', 'segment',
        ];
        $nameKeys = [
            'commoditytitle', 'commoditydescription', 'commodityname',
            'classtitle', 'classdescription', 'classname',
            'familytitle', 'familydescription',
            'segmenttitle', 'segmentdescription',
            'itemclsnm', 'title', 'description', 'name',
        ];
        foreach ($header as $i => $label) {
            $key = self::normalize($label);
            if ($key === '') {
                continue;
            }
            if (in_array($key, $codeKeys, true) && !isset($map['code'])) {
                $map['code'] = $i;
            } elseif (in_array($key, $nameKeys, true) && !isset($map['name'])) {
                $map['name'] = $i;
            } elseif (in_array($key, ['level', 'itemclslvl', 'classlevel'], true)) {
                $map['level'] = $i;
            } elseif (in_array($key, ['tax', 'taxtycd', 'taxclass', 'tax_type'], true)) {
                $map['tax'] = $i;
            }
        }
        if (!isset($map['code'])) {
            $map['code'] = 0;
        }
        if (!isset($map['name']) && count($header) > 1) {
            $map['name'] = 1;
        }
        return $map;
    }

    public static function isUnspscWorkbook(string $path): bool
    {
        $names = self::xlsxSheetNames($path);
        if (!$names) {
            return false;
        }
        $upper = array_map(static fn($n) => strtoupper(trim((string)$n)), $names);
        $hasCommodity = in_array('COMMODITY', $upper, true);
        $hasHierarchy = (bool)array_intersect($upper, ['SEGMENT', 'FAMILY', 'CLASS', 'UNSPSC CODES']);
        return $hasCommodity && $hasHierarchy;
    }

    /**
     * @return list<string>
     */
    public static function xlsxSheetNames(string $path): array
    {
        return array_keys(self::xlsxSheetTargets($path));
    }

    /**
     * Extract worksheet XML + shared strings for the named sheets (or the first sheet).
     *
     * @param list<string>|null $sheetFilter Case-insensitive sheet names. Null = first sheet only.
     * @return array{path:string,tmp:string,sheets:array<string,string>,shared:array<int,string>}
     */
    public static function openXlsx(string $path, ?array $sheetFilter = null): array
    {
        if (!class_exists(\ZipArchive::class)) {
            throw new \Exception('Excel (.xlsx) upload requires Zip support. Save the file as CSV and try again.');
        }
        if (!class_exists(\XMLReader::class)) {
            throw new \Exception('Excel (.xlsx) upload requires XML support.');
        }
        $targets = self::xlsxSheetTargets($path);
        if (!$targets) {
            throw new \Exception('The Excel workbook has no worksheets.');
        }

        $wanted = [];
        if ($sheetFilter) {
            $filter = array_map(static fn($n) => strtoupper(trim((string)$n)), $sheetFilter);
            foreach ($targets as $name => $zipPath) {
                if (in_array(strtoupper(trim($name)), $filter, true)) {
                    $wanted[$name] = $zipPath;
                }
            }
        } else {
            $first = array_key_first($targets);
            $wanted[$first] = $targets[$first];
        }
        if (!$wanted) {
            throw new \Exception('The Excel workbook does not contain the expected UNSPSC sheets.');
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \Exception('Unable to open the Excel file. Save it as CSV and try again.');
        }

        $tmp = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'unspsc_xlsx_' . bin2hex(random_bytes(6));
        if (!mkdir($tmp, 0700) && !is_dir($tmp)) {
            $zip->close();
            throw new \Exception('Unable to create a temporary folder for the Excel import.');
        }

        $extract = [];
        foreach (array_merge(['xl/sharedStrings.xml'], array_values($wanted)) as $zipPath) {
            $actual = self::zipLocate($zip, $zipPath);
            if ($actual !== null) {
                $extract[] = $actual;
            }
        }
        if (!$extract) {
            $zip->close();
            self::removeDir($tmp);
            throw new \Exception('The Excel workbook is missing worksheet data.');
        }
        if (!$zip->extractTo($tmp, $extract)) {
            $zip->close();
            self::removeDir($tmp);
            throw new \Exception('Unable to unpack the Excel worksheets.');
        }
        $zip->close();

        $sharedFile = $tmp . '/xl/sharedStrings.xml';
        $shared = is_file($sharedFile) ? self::readSharedStrings($sharedFile) : [];

        $absSheets = [];
        foreach ($wanted as $name => $zipPath) {
            $abs = $tmp . '/' . str_replace('\\', '/', $zipPath);
            if (!is_file($abs)) {
                self::removeDir($tmp);
                throw new \Exception('Missing worksheet in the Excel file: ' . $name);
            }
            $absSheets[$name] = $abs;
        }

        return [
            'path' => $path,
            'tmp' => $tmp,
            'sheets' => $absSheets,
            'shared' => $shared,
        ];
    }

    public static function closeXlsx(array $workbook): void
    {
        if (!empty($workbook['tmp']) && is_dir((string)$workbook['tmp'])) {
            self::removeDir((string)$workbook['tmp']);
        }
    }

    public static function resolveSheetName(array $workbook, string $want): ?string
    {
        $want = strtoupper(trim($want));
        foreach (array_keys($workbook['sheets'] ?? []) as $name) {
            if (strtoupper(trim((string)$name)) === $want) {
                return (string)$name;
            }
        }
        return null;
    }

    /**
     * @param callable(array<int,string>, int):void $onRow  Zero-based row index including header
     */
    public static function eachSheetRow(array $workbook, string $sheetName, callable $onRow): void
    {
        $resolved = self::resolveSheetName($workbook, $sheetName) ?? $sheetName;
        $file = $workbook['sheets'][$resolved] ?? null;
        if (!$file) {
            throw new \Exception('Worksheet not found: ' . $sheetName);
        }
        $shared = $workbook['shared'] ?? [];
        $reader = new \XMLReader();
        if (!$reader->open($file, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \Exception('Unable to parse the Excel sheet: ' . $sheetName);
        }
        $index = 0;
        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }
            $line = self::readSheetRow($reader, $shared);
            if ($line === []) {
                continue;
            }
            $onRow($line, $index);
            $index++;
        }
        $reader->close();
    }

    private static function normalize(string $label): string
    {
        return strtolower(preg_replace('/[^a-z0-9]+/i', '', $label) ?? '');
    }

    private static function readCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \Exception('Failed to read the uploaded file.');
        }
        $rows = [];
        while (($data = fgetcsv($handle, 0, ',')) !== false) {
            if ($data === [null] || $data === false) {
                continue;
            }
            $rows[] = array_map(static fn($v) => trim((string)$v), $data);
        }
        fclose($handle);
        return $rows;
    }

    private static function readXlsx(string $path): array
    {
        $workbook = self::openXlsx($path, null);
        try {
            $rows = [];
            $first = array_key_first($workbook['sheets']);
            self::eachSheetRow($workbook, (string)$first, static function (array $row) use (&$rows): void {
                $rows[] = $row;
            });
            return $rows;
        } finally {
            self::closeXlsx($workbook);
        }
    }

    /**
     * @return array<string,string> sheet name => zip-internal worksheet path
     */
    private static function xlsxSheetTargets(string $path): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \Exception('Unable to open the Excel file. Save it as CSV and try again.');
        }
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        $zip->close();
        if (!$workbookXml || !$relsXml) {
            return [];
        }

        $ridToTarget = [];
        $rels = @simplexml_load_string($relsXml);
        if ($rels) {
            foreach ($rels->Relationship as $rel) {
                $id = (string)$rel['Id'];
                $target = str_replace('\\', '/', (string)$rel['Target']);
                $type = (string)$rel['Type'];
                if ($id === '' || !str_contains($type, '/worksheet')) {
                    continue;
                }
                $target = ltrim($target, '/');
                if (!str_starts_with($target, 'xl/')) {
                    $target = 'xl/' . $target;
                }
                $ridToTarget[$id] = $target;
            }
        }

        $out = [];
        if (preg_match_all('/<sheet\b[^>]*>/i', $workbookXml, $sheetTags)) {
            foreach ($sheetTags[0] as $tag) {
                if (!preg_match('/\bname="([^"]+)"/', $tag, $nm)) {
                    continue;
                }
                $rid = '';
                if (preg_match('/\br:id="([^"]+)"/', $tag, $idm)) {
                    $rid = $idm[1];
                } elseif (preg_match('/\bid="([^"]+)"/', $tag, $idm)) {
                    $rid = $idm[1];
                }
                $name = html_entity_decode($nm[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
                if ($name === '' || $rid === '' || !isset($ridToTarget[$rid])) {
                    continue;
                }
                $out[$name] = $ridToTarget[$rid];
            }
        }
        if ($out) {
            return $out;
        }

        $wb = @simplexml_load_string($workbookXml);
        if (!$wb) {
            return [];
        }
        $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $sheets = $wb->xpath('//m:sheets/m:sheet') ?: [];
        if (!$sheets && isset($wb->sheets->sheet)) {
            $sheets = $wb->sheets->sheet;
        }
        foreach ($sheets as $sheet) {
            $name = trim((string)$sheet['name']);
            $rNs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = trim((string)($rNs['id'] ?? $sheet['id'] ?? ''));
            if ($name === '' || $rid === '' || !isset($ridToTarget[$rid])) {
                continue;
            }
            $out[$name] = $ridToTarget[$rid];
        }
        return $out;
    }

    /**
     * @return array<int,string>
     */
    private static function readSharedStrings(string $path): array
    {
        $shared = [];
        $reader = new \XMLReader();
        if (!$reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            return [];
        }
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 'si') {
                $shared[] = self::readSiText($reader);
            }
        }
        $reader->close();
        return $shared;
    }

    private static function readSiText(\XMLReader $reader): string
    {
        $text = '';
        $depth = $reader->depth;
        if ($reader->isEmptyElement) {
            return '';
        }
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'si' && $reader->depth === $depth) {
                break;
            }
            if ($reader->nodeType === \XMLReader::ELEMENT && $reader->localName === 't') {
                $text .= $reader->readString();
            }
        }
        return trim(html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8'));
    }

    /**
     * @param array<int,string> $shared
     * @return array<int,string>
     */
    private static function readSheetRow(\XMLReader $reader, array $shared): array
    {
        $cells = [];
        $rowDepth = $reader->depth;
        if ($reader->isEmptyElement) {
            return [];
        }
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'row' && $reader->depth === $rowDepth) {
                break;
            }
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'c') {
                continue;
            }
            $ref = (string)$reader->getAttribute('r');
            $type = (string)$reader->getAttribute('t');
            $col = self::columnIndexFromRef($ref);
            $cells[$col] = self::readCellValue($reader, $type, $shared);
        }
        if (!$cells) {
            return [];
        }
        ksort($cells);
        $max = max(array_keys($cells));
        $line = [];
        for ($i = 0; $i <= $max; $i++) {
            $line[] = $cells[$i] ?? '';
        }
        return $line;
    }

    /**
     * @param array<int,string> $shared
     */
    private static function readCellValue(\XMLReader $reader, string $type, array $shared): string
    {
        $depth = $reader->depth;
        if ($reader->isEmptyElement) {
            return '';
        }
        $val = '';
        $inline = '';
        while ($reader->read()) {
            if ($reader->nodeType === \XMLReader::END_ELEMENT && $reader->localName === 'c' && $reader->depth === $depth) {
                break;
            }
            if ($reader->nodeType !== \XMLReader::ELEMENT) {
                continue;
            }
            if ($reader->localName === 'v') {
                $val = $reader->readString();
            } elseif ($reader->localName === 't') {
                $inline .= $reader->readString();
            }
        }
        if ($type === 's') {
            return trim((string)($shared[(int)$val] ?? ''));
        }
        if ($type === 'inlineStr') {
            return trim($inline !== '' ? $inline : $val);
        }
        return trim($val);
    }

    private static function zipLocate(\ZipArchive $zip, string $name): ?string
    {
        $candidates = [
            $name,
            ltrim(str_replace('\\', '/', $name), '/'),
        ];
        foreach ($candidates as $candidate) {
            $idx = $zip->locateName($candidate, \ZipArchive::FL_NOCASE);
            if ($idx !== false) {
                return $zip->getNameIndex($idx) ?: $candidate;
            }
        }
        return null;
    }

    private static function columnIndexFromRef(string $ref): int
    {
        $letters = strtoupper(preg_replace('/[^A-Z]/', '', $ref) ?? '');
        $n = 0;
        for ($i = 0; $i < strlen($letters); $i++) {
            $n = $n * 26 + (ord($letters[$i]) - 64);
        }
        return max(0, $n - 1);
    }

    private static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        if ($items === false) {
            return;
        }
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                self::removeDir($path);
            } else {
                @unlink($path);
            }
        }
        @rmdir($dir);
    }
}
