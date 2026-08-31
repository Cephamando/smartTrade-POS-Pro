<?php

/**
 * Compact QR Code Model 2 encoder (byte mode, ECC M) that emits SVG.
 * Used so thermal/PDF receipts do not depend on an external QR image host.
 */
class QrSvg
{
    public static function render(string $text, int $modulePx = 4): string
    {
        if ($text === '') {
            return '';
        }
        $matrix = self::matrix($text);
        $n = count($matrix);
        $quiet = 4;
        $size = ($n + $quiet * 2) * $modulePx;
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $size . ' ' . $size . '" width="' . $size . '" height="' . $size . '" shape-rendering="crispEdges">';
        $svg .= '<rect width="100%" height="100%" fill="#fff"/>';
        for ($y = 0; $y < $n; $y++) {
            for ($x = 0; $x < $n; $x++) {
                if (empty($matrix[$y][$x])) {
                    continue;
                }
                $px = ($x + $quiet) * $modulePx;
                $py = ($y + $quiet) * $modulePx;
                $svg .= '<rect x="' . $px . '" y="' . $py . '" width="' . $modulePx . '" height="' . $modulePx . '" fill="#000"/>';
            }
        }
        return $svg . '</svg>';
    }

    private static function matrix(string $text): array
    {
        $data = array_values(unpack('C*', $text) ?: []);
        $ver = 1;
        for ($v = 1; $v <= 10; $v++) {
            $overhead = $v <= 9 ? 2 : 3;
            if (self::dataCapacity($v) >= count($data) + $overhead) {
                $ver = $v;
                break;
            }
            $ver = $v;
        }
        $size = $ver * 4 + 17;
        $bits = self::buildBits($data, $ver);
        $ecc = self::eccBlocks($bits, $ver);
        $modules = array_fill(0, $size, array_fill(0, $size, null));
        self::placeFinders($modules, $size);
        self::placeAlignments($modules, $ver, $size);
        self::placeTiming($modules, $size);
        $modules[8][$size - 8] = true;
        self::placeFormat($modules, $size, 0);
        self::placeVersion($modules, $ver, $size);
        self::placeData($modules, $ecc);
        $bestScore = PHP_INT_MAX;
        $best = $modules;
        for ($mask = 0; $mask < 8; $mask++) {
            $trial = $modules;
            self::applyMask($trial, $mask, $ver);
            self::placeFormat($trial, $size, $mask);
            $score = self::penalty($trial);
            if ($score < $bestScore) {
                $bestScore = $score;
                $best = $trial;
            }
        }
        return $best;
    }

    private static function dataCapacity(int $ver): int
    {
        $total = [0, 26, 44, 70, 100, 134, 172, 196, 242, 292, 346][$ver];
        $ecc = [0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26][$ver];
        $blocks = [0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5][$ver];
        return $total - $ecc * $blocks;
    }

    private static function alignmentCenters(int $ver): array
    {
        return [
            1 => [],
            2 => [6, 18],
            3 => [6, 22],
            4 => [6, 26],
            5 => [6, 30],
            6 => [6, 34],
            7 => [6, 22, 38],
            8 => [6, 24, 42],
            9 => [6, 26, 46],
            10 => [6, 28, 50],
        ][$ver];
    }

    private static function alignmentOverlapsFinder(int $ax, int $ay, int $size): bool
    {
        return ($ax < 9 && $ay < 9)
            || ($ax < 9 && $ay > $size - 10)
            || ($ax > $size - 10 && $ay < 9);
    }

    private static function buildBits(array $data, int $ver): array
    {
        $cap = self::dataCapacity($ver);
        $out = [0, 1, 0, 0];
        $lenBits = $ver <= 9 ? 8 : 16;
        $len = count($data);
        for ($i = $lenBits - 1; $i >= 0; $i--) {
            $out[] = ($len >> $i) & 1;
        }
        foreach ($data as $b) {
            for ($i = 7; $i >= 0; $i--) {
                $out[] = ($b >> $i) & 1;
            }
        }
        $limit = $cap * 8;
        foreach ([0, 0, 0, 0] as $padBit) {
            if (count($out) >= $limit) {
                break;
            }
            $out[] = $padBit;
        }
        while (count($out) % 8 !== 0 && count($out) < $limit) {
            $out[] = 0;
        }
        $pads = [0xEC, 0x11];
        $p = 0;
        while (count($out) + 8 <= $limit) {
            $b = $pads[$p++ % 2];
            for ($i = 7; $i >= 0; $i--) {
                $out[] = ($b >> $i) & 1;
            }
        }
        $out = array_slice($out, 0, $limit);
        $bytes = [];
        for ($i = 0; $i < count($out); $i += 8) {
            $v = 0;
            for ($j = 0; $j < 8; $j++) {
                $v = ($v << 1) | $out[$i + $j];
            }
            $bytes[] = $v;
        }
        return $bytes;
    }

    private static function eccBlocks(array $data, int $ver): array
    {
        $eccLen = [0, 10, 16, 26, 18, 24, 16, 18, 22, 22, 26][$ver];
        $blockCount = [0, 1, 1, 1, 2, 2, 4, 4, 4, 5, 5][$ver];
        $total = [0, 26, 44, 70, 100, 134, 172, 196, 242, 292, 346][$ver];
        $dataLen = $total - $eccLen * $blockCount;
        $shortBlocks = $blockCount - ($dataLen % $blockCount);
        $shortLen = intdiv($dataLen, $blockCount);
        $blocks = [];
        $offset = 0;
        for ($i = 0; $i < $blockCount; $i++) {
            $n = $shortLen + ($i < $shortBlocks ? 0 : 1);
            $chunk = array_slice($data, $offset, $n);
            $offset += $n;
            $blocks[] = ['d' => $chunk, 'e' => self::rs($chunk, $eccLen)];
        }
        $inter = [];
        $maxD = max(array_map(fn($b) => count($b['d']), $blocks));
        for ($i = 0; $i < $maxD; $i++) {
            foreach ($blocks as $b) {
                if (isset($b['d'][$i])) {
                    $inter[] = $b['d'][$i];
                }
            }
        }
        for ($i = 0; $i < $eccLen; $i++) {
            foreach ($blocks as $b) {
                $inter[] = $b['e'][$i];
            }
        }
        return $inter;
    }

    private static function rs(array $data, int $ecclen): array
    {
        $gen = self::rsGenerator($ecclen);
        $ecc = array_fill(0, $ecclen, 0);
        foreach ($data as $b) {
            $factor = $b ^ $ecc[0];
            array_shift($ecc);
            $ecc[] = 0;
            if ($factor === 0) {
                continue;
            }
            $logf = self::gfLog($factor);
            for ($i = 0; $i < $ecclen; $i++) {
                $ecc[$i] ^= self::gfExp($logf + self::gfLog($gen[$i]));
            }
        }
        return $ecc;
    }

    private static function rsGenerator(int $degree): array
    {
        $poly = [1];
        for ($i = 0; $i < $degree; $i++) {
            $next = array_fill(0, count($poly) + 1, 0);
            $root = self::gfExp($i);
            for ($j = 0; $j < count($poly); $j++) {
                $next[$j] ^= $poly[$j];
                $next[$j + 1] ^= self::gfMul($poly[$j], $root);
            }
            $poly = $next;
        }
        array_shift($poly);
        return $poly;
    }

    private static function gfExp(int $n): int
    {
        static $exp = null;
        if ($exp === null) {
            $exp = [];
            $x = 1;
            for ($i = 0; $i < 256; $i++) {
                $exp[$i] = $x;
                $x <<= 1;
                if ($x & 0x100) {
                    $x ^= 0x11d;
                }
            }
            for ($i = 255; $i < 512; $i++) {
                $exp[$i] = $exp[$i - 255];
            }
        }
        return $exp[$n % 255];
    }

    private static function gfLog(int $n): int
    {
        static $log = null;
        if ($log === null) {
            $log = array_fill(0, 256, 0);
            for ($i = 0; $i < 255; $i++) {
                $log[self::gfExp($i)] = $i;
            }
        }
        return $log[$n];
    }

    private static function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) {
            return 0;
        }
        return self::gfExp(self::gfLog($a) + self::gfLog($b));
    }

    private static function placeFinders(array &$m, int $size): void
    {
        foreach ([[0, 0], [0, $size - 7], [$size - 7, 0]] as [$r, $c]) {
            for ($y = -1; $y <= 7; $y++) {
                for ($x = -1; $x <= 7; $x++) {
                    $yy = $r + $y;
                    $xx = $c + $x;
                    if ($yy < 0 || $xx < 0 || $yy >= $size || $xx >= $size) {
                        continue;
                    }
                    $dark = ($x >= 0 && $x <= 6 && $y >= 0 && $y <= 6)
                        && ($x === 0 || $x === 6 || $y === 0 || $y === 6 || ($x >= 2 && $x <= 4 && $y >= 2 && $y <= 4));
                    $m[$yy][$xx] = $dark;
                }
            }
        }
    }

    private static function placeAlignments(array &$m, int $ver, int $size): void
    {
        $pos = self::alignmentCenters($ver);
        foreach ($pos as $y) {
            foreach ($pos as $x) {
                if (self::alignmentOverlapsFinder($x, $y, $size)) {
                    continue;
                }
                for ($dy = -2; $dy <= 2; $dy++) {
                    for ($dx = -2; $dx <= 2; $dx++) {
                        $m[$y + $dy][$x + $dx] = abs($dx) === 2 || abs($dy) === 2 || ($dx === 0 && $dy === 0);
                    }
                }
            }
        }
    }

    private static function placeTiming(array &$m, int $size): void
    {
        for ($i = 8; $i < $size - 8; $i++) {
            if ($m[6][$i] === null) {
                $m[6][$i] = $i % 2 === 0;
            }
            if ($m[$i][6] === null) {
                $m[$i][6] = $i % 2 === 0;
            }
        }
    }

    private static function placeVersion(array &$m, int $ver, int $size): void
    {
        if ($ver < 7) {
            return;
        }
        $d = $ver << 12;
        $rem = $d;
        for ($i = 17; $i >= 12; $i--) {
            if (($rem >> $i) & 1) {
                $rem ^= 0x1F25 << ($i - 12);
            }
        }
        $bits = ($ver << 12) | $rem;
        for ($i = 0; $i < 6; $i++) {
            for ($j = 0; $j < 3; $j++) {
                $dark = (($bits >> ($i * 3 + $j)) & 1) === 1;
                $m[$i][$size - 11 + $j] = $dark;
                $m[$size - 11 + $j][$i] = $dark;
            }
        }
    }

    private static function placeFormat(array &$m, int $size, int $mask): void
    {
        $data = (0b00 << 3) | $mask; // ECC M = 00
        $rem = $data << 10;
        for ($i = 14; $i >= 10; $i--) {
            if (($rem >> $i) & 1) {
                $rem ^= 0x537 << ($i - 10);
            }
        }
        $bits = ($data << 10 | $rem) ^ 0x5412;
        $coords = [
            [0, 8], [1, 8], [2, 8], [3, 8], [4, 8], [5, 8], [7, 8], [8, 8],
            [8, 7], [8, 5], [8, 4], [8, 3], [8, 2], [8, 1], [8, 0],
        ];
        $coords2 = [
            [8, $size - 1], [8, $size - 2], [8, $size - 3], [8, $size - 4], [8, $size - 5], [8, $size - 6], [8, $size - 7],
            [$size - 8, 8], [$size - 7, 8], [$size - 6, 8], [$size - 5, 8], [$size - 4, 8], [$size - 3, 8], [$size - 2, 8], [$size - 1, 8],
        ];
        for ($i = 0; $i < 15; $i++) {
            $bit = (($bits >> (14 - $i)) & 1) === 1;
            [$y, $x] = $coords[$i];
            $m[$y][$x] = $bit;
            [$y2, $x2] = $coords2[$i];
            $m[$y2][$x2] = $bit;
        }
    }

    private static function placeData(array &$m, array $bytes): void
    {
        $size = count($m);
        $bits = [];
        foreach ($bytes as $b) {
            for ($i = 7; $i >= 0; $i--) {
                $bits[] = ($b >> $i) & 1;
            }
        }
        $i = 0;
        $dir = -1;
        $row = $size - 1;
        for ($col = $size - 1; $col > 0; $col -= 2) {
            if ($col === 6) {
                $col--;
            }
            for ($n = 0; $n < $size; $n++) {
                for ($c = 0; $c < 2; $c++) {
                    $x = $col - $c;
                    if ($m[$row][$x] === null) {
                        $m[$row][$x] = !empty($bits[$i]);
                        $i++;
                    }
                }
                $row += $dir;
                if ($row < 0 || $row >= $size) {
                    $dir = -$dir;
                    $row += $dir;
                    break;
                }
            }
        }
    }

    private static function applyMask(array &$m, int $mask, int $ver): void
    {
        $size = count($m);
        for ($y = 0; $y < $size; $y++) {
            for ($x = 0; $x < $size; $x++) {
                if (self::isFunction($x, $y, $size, $ver)) {
                    continue;
                }
                $flip = match ($mask) {
                    0 => ($x + $y) % 2 === 0,
                    1 => $y % 2 === 0,
                    2 => $x % 3 === 0,
                    3 => ($x + $y) % 3 === 0,
                    4 => (intdiv($y, 2) + intdiv($x, 3)) % 2 === 0,
                    5 => ($x * $y) % 2 + ($x * $y) % 3 === 0,
                    6 => (($x * $y) % 2 + ($x * $y) % 3) % 2 === 0,
                    default => (($x + $y) % 2 + ($x * $y) % 3) % 2 === 0,
                };
                if ($flip) {
                    $m[$y][$x] = !$m[$y][$x];
                }
            }
        }
    }

    private static function isFunction(int $x, int $y, int $size, int $ver): bool
    {
        if ($y < 9 && ($x < 9 || $x >= $size - 8)) {
            return true;
        }
        if ($x < 9 && $y >= $size - 8) {
            return true;
        }
        if ($y === 6 || $x === 6) {
            return true;
        }
        if ($ver >= 7) {
            if ($x < 6 && $y >= $size - 11 && $y < $size - 8) {
                return true;
            }
            if ($y < 6 && $x >= $size - 11 && $x < $size - 8) {
                return true;
            }
        }
        foreach (self::alignmentCenters($ver) as $ay) {
            foreach (self::alignmentCenters($ver) as $ax) {
                if (self::alignmentOverlapsFinder($ax, $ay, $size)) {
                    continue;
                }
                if (abs($x - $ax) <= 2 && abs($y - $ay) <= 2) {
                    return true;
                }
            }
        }
        return false;
    }

    private static function penalty(array $m): int
    {
        $size = count($m);
        $score = 0;
        for ($y = 0; $y < $size; $y++) {
            $run = 1;
            for ($x = 1; $x < $size; $x++) {
                if ($m[$y][$x] === $m[$y][$x - 1]) {
                    $run++;
                } else {
                    if ($run >= 5) {
                        $score += $run - 2;
                    }
                    $run = 1;
                }
            }
            if ($run >= 5) {
                $score += $run - 2;
            }
        }
        return $score;
    }
}
