<?php

/**
 * Compile every application PHP file into the Zend OPcache file cache.
 *
 * Each file is compiled in its own CLI process so top-level side effects,
 * relative includes, and duplicate symbol declarations cannot collide.
 * After a bytecode file is verified, the source is truncated to zero bytes
 * and the original path, owner, mode, and timestamps are restored.
 *
 * Usage: php compile-opcache.php <app-root> <cache-dir>
 */

declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Usage: compile-opcache.php <app-root> <cache-dir>\n");
    exit(1);
}

$root = realpath($argv[1]);
$cache = $argv[2];

if ($root === false || !is_dir($root)) {
    fail('Application root is not a directory.');
}
if (!is_dir($cache) && !mkdir($cache, 0750, true) && !is_dir($cache)) {
    fail("Cannot create the OPcache directory {$cache}.");
}
$cache = realpath($cache);
if ($cache === false) {
    fail('Cannot resolve the OPcache directory.');
}

/**
 * @param array<string, string> $ini
 * @param list<string> $args
 * @return array{0:int,1:string,2:string}
 */
function runPhp(array $ini, string $code, array $args = [], int $timeout = 60): array
{
    $cmd = ['timeout', (string) $timeout, PHP_BINARY];
    foreach ($ini as $key => $value) {
        $cmd[] = '-d';
        $cmd[] = $key . '=' . $value;
    }
    $cmd[] = '-r';
    $cmd[] = $code;
    if ($args !== []) {
        $cmd[] = '--';
        array_push($cmd, ...$args);
    }

    $pipes = [];
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        fail('Unable to start an isolated PHP process.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exit = proc_close($proc);

    return [$exit, $stdout === false ? '' : $stdout, $stderr === false ? '' : $stderr];
}

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

/** @return list<string> */
function binsFor(string $cache, string $realPath): array
{
    // PHP stores the file as {cache}/{32-char system id}{absolute path}.bin
    $pattern = '#^' . preg_quote(rtrim($cache, '/'), '#')
        . '/[0-9a-f]{32}' . preg_quote($realPath, '#') . '\.bin$#';
    $found = [];
    if (!is_dir($cache)) {
        return $found;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $file) {
        if ($file->isFile() && preg_match($pattern, $file->getPathname()) === 1) {
            $found[] = $file->getPathname();
        }
    }
    sort($found);

    return $found;
}

function assertBin(string $cache, string $realPath): string
{
    $matches = binsFor($cache, $realPath);
    if (count($matches) !== 1) {
        fail(
            'No corresponding OPcache bytecode file was created for ' . $realPath
            . ' (found ' . count($matches) . '). The build is aborting.'
        );
    }
    $bin = $matches[0];
    $header = file_get_contents($bin, false, null, 0, 8);
    if ($header !== "OPCACHE\0") {
        fail("Bytecode for {$realPath} is not a Zend OPcache file ({$bin}). The build is aborting.");
    }
    $size = filesize($bin);
    if ($size === false || $size <= 8) {
        fail("Bytecode for {$realPath} is empty. The build is aborting.");
    }

    return $bin;
}

/** @param array<string, string> $ini */
function compileFile(array $ini, string $cache, string $realPath): void
{
    [$exit, $stdout, $stderr] = runPhp(
        $ini,
        'exit(opcache_compile_file($argv[1]) ? 0 : 1);',
        [$realPath]
    );
    if ($exit === 124) {
        fail("OPcache compile timed out after 60 seconds for {$realPath}. The build is aborting.\n{$stderr}");
    }
    if ($exit !== 0) {
        $detail = trim($stderr . "\n" . $stdout);
        fail(
            "OPcache compile failed for {$realPath} (exit {$exit}). The build is aborting."
            . ($detail !== '' ? "\n{$detail}" : '')
        );
    }
    assertBin($cache, $realPath);
}

/**
 * @return array{0:int,1:string,2:string}
 */
function runCommand(array $cmd): array
{
    $pipes = [];
    $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    if (!is_resource($proc)) {
        fail('Unable to start ' . implode(' ', $cmd) . '.');
    }
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($proc), $stdout === false ? '' : $stdout, $stderr === false ? '' : $stderr];
}

function timestampOf(string $path, string $which): string
{
    $format = $which === 'mtime' ? '%y' : '%x';
    [$exit, $stdout, $stderr] = runCommand(['stat', '-c', $format, $path]);
    $value = trim($stdout);
    if ($exit !== 0 || $value === '') {
        fail("Unable to read {$which} for {$path}. {$stderr}");
    }

    return $value;
}

/** @return array{mode:int,uid:int,gid:int,mtime:string,atime:string} */
function fileMeta(string $realPath): array
{
    $stat = stat($realPath);
    if ($stat === false) {
        fail("Cannot stat {$realPath}. The build is aborting.");
    }

    return [
        'mode' => $stat['mode'] & 0777,
        'uid' => $stat['uid'],
        'gid' => $stat['gid'],
        'mtime' => timestampOf($realPath, 'mtime'),
        'atime' => timestampOf($realPath, 'atime'),
    ];
}

/**
 * @param array{mode:int,uid:int,gid:int,mtime:string,atime:string} $meta
 */
function wipeSource(string $realPath, array $meta): void
{
    $handle = fopen($realPath, 'wb');
    if ($handle === false) {
        fail("Unable to wipe source file {$realPath}. The build is aborting.");
    }
    fflush($handle);
    fclose($handle);

    if (!chmod($realPath, $meta['mode'])) {
        fail("Unable to restore permissions on {$realPath}. The build is aborting.");
    }
    [$mtimeExit, , $mtimeErr] = runCommand(['touch', '-m', '-d', $meta['mtime'], $realPath]);
    if ($mtimeExit !== 0) {
        fail("Unable to restore the modification time on {$realPath}. {$mtimeErr}");
    }
    [$atimeExit, , $atimeErr] = runCommand(['touch', '-a', '-d', $meta['atime'], $realPath]);
    if ($atimeExit !== 0) {
        fail("Unable to restore the access time on {$realPath}. {$atimeErr}");
    }

    clearstatcache(true, $realPath);
    $stat = stat($realPath);
    if ($stat === false || $stat['size'] !== 0) {
        fail("Source file {$realPath} is not zero bytes after wipe. The build is aborting.");
    }
    $mtime = timestampOf($realPath, 'mtime');
    $atime = timestampOf($realPath, 'atime');
    if ($mtime !== $meta['mtime'] || $atime !== $meta['atime']) {
        fail(
            "Timestamps were not preserved for {$realPath} "
            . "(mtime {$meta['mtime']} -> {$mtime}, atime {$meta['atime']} -> {$atime}). "
            . 'The build is aborting.'
        );
    }
    if ($stat['uid'] !== $meta['uid'] || $stat['gid'] !== $meta['gid']) {
        fail("Ownership was not preserved for {$realPath}. The build is aborting.");
    }
    if (($stat['mode'] & 0777) !== $meta['mode']) {
        fail("Permissions were not preserved for {$realPath}. The build is aborting.");
    }
}

$compileIni = [
    'display_errors' => 'stderr',
    'error_reporting' => 'E_ALL',
    'log_errors' => '1',
    'memory_limit' => '512M',
    'opcache.enable' => '1',
    'opcache.enable_cli' => '1',
    'opcache.jit' => 'disable',
    'opcache.jit_buffer_size' => '0',
    'opcache.file_cache' => $cache,
    'opcache.file_cache_only' => '0',
    'opcache.validate_timestamps' => '1',
    'opcache.revalidate_freq' => '0',
    'opcache.save_comments' => '0',
    'opcache.enable_file_override' => '1',
    'opcache.file_update_protection' => '0',
    'opcache.file_cache_consistency_checks' => '1',
    'opcache.validate_permission' => '0',
    'opcache.max_accelerated_files' => '10000',
];

$runtimeIni = $compileIni;
$runtimeIni['opcache.file_cache_only'] = '1';
$runtimeIni['opcache.validate_timestamps'] = '0';
$runtimeIni['opcache.validate_permission'] = '1';

$self = sys_get_temp_dir() . '/client-opcache-selftest-' . getmypid();
if (!mkdir($self, 0700, true) && !is_dir($self)) {
    fail('Unable to create the compiler self-test directory.');
}

$marker = $self . '/side-effect.txt';
$probe = $self . '/probe.php';
$dupA = $self . '/dup-a.php';
$dupB = $self . '/dup-b.php';
file_put_contents(
    $probe,
    "<?php\n/** SECRET_COMMENT_MARKER */\nfile_put_contents(" . var_export($marker, true) . ", 'executed');\n"
    . "function client_opcache_probe(): string { return 'bytecode-ok'; }\nreturn 'from-bytecode';\n"
);
file_put_contents($dupA, "<?php\nfunction client_opcache_dup(): int { return 1; }\n");
file_put_contents($dupB, "<?php\nfunction client_opcache_dup(): int { return 2; }\n");

compileFile($compileIni, $cache, $probe);
compileFile($compileIni, $cache, $dupA);
compileFile($compileIni, $cache, $dupB);
if (is_file($marker)) {
    fail('Compiler self-test executed a top-level side effect. opcache_compile_file must not run the script.');
}
$probeBin = assertBin($cache, $probe);
$probeBinText = file_get_contents($probeBin);
if ($probeBinText !== false && str_contains($probeBinText, 'SECRET_COMMENT_MARKER')) {
    fail('Compiler self-test left a documentation comment in the bytecode file.');
}

wipeSource($probe, fileMeta($probe));

[$exit, $stdout, $stderr] = runPhp(
    $runtimeIni,
    'if ((require $argv[1]) !== "from-bytecode" || client_opcache_probe() !== "bytecode-ok") { fwrite(STDERR, "probe mismatch\n"); exit(1); }'
    . 'if (is_file($argv[1]) !== true) { fwrite(STDERR, "is_file failed\n"); exit(1); }',
    [$probe]
);
if ($exit !== 0) {
    fail("Compiler self-test could not execute wiped source from the file cache (exit {$exit}).\n{$stderr}{$stdout}");
}
$markerBody = is_file($marker) ? file_get_contents($marker) : false;
if ($markerBody !== 'executed') {
    fail('Wiped probe did not run its original top-level code from bytecode.');
}

foreach ([$probe, $dupA, $dupB] as $path) {
    foreach (binsFor($cache, $path) as $bin) {
        if (!unlink($bin)) {
            fail("Unable to remove self-test bytecode {$bin}.");
        }
    }
}
unlink($probe);
unlink($dupA);
unlink($dupB);
@rmdir($self);

/** @var array<string, array{mode:int,uid:int,gid:int,mtime:string,atime:string}> $files */
$files = [];
$directory = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($directory as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'php') {
        continue;
    }
    $real = realpath($file->getPathname());
    if ($real === false) {
        fail('Cannot resolve ' . $file->getPathname() . '. The build is aborting.');
    }
    if ($real !== $root && !str_starts_with($real, $root . DIRECTORY_SEPARATOR)) {
        fail("Refusing to compile {$real}, which is outside {$root}.");
    }
    $files[$real] = fileMeta($real);
}
ksort($files);

if ($files === []) {
    fail("No PHP files were found under {$root}. The build is aborting.");
}

fwrite(STDOUT, 'Compiling ' . count($files) . " PHP files in isolated processes.\n");
foreach ($files as $real => $_meta) {
    compileFile($compileIni, $cache, $real);
}

foreach ($files as $real => $meta) {
    wipeSource($real, $meta);
    assertBin($cache, $real);
}

$bins = [];
$binIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS)
);
foreach ($binIterator as $file) {
    if ($file->isFile() && str_ends_with($file->getPathname(), '.bin')) {
        $bins[] = $file->getPathname();
    }
}
sort($bins);

if (count($bins) !== count($files)) {
    fail(
        'Bytecode count does not match the PHP file count ('
        . count($bins) . ' binaries, ' . count($files) . ' scripts). The build is aborting.'
    );
}

$systemDirs = glob($cache . '/*', GLOB_ONLYDIR);
if ($systemDirs === false || count($systemDirs) !== 1) {
    fail('Expected one OPcache system-id directory, found ' . count($systemDirs ?: []) . '. The build is aborting.');
}

foreach ($files as $real => $_meta) {
    $matches = binsFor($cache, $real);
    if (count($matches) !== 1) {
        fail('Bytecode mapping for ' . $real . ' is ' . count($matches) . ', expected 1. The build is aborting.');
    }
}

$binaryExt = ['woff', 'woff2', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'xlsx', 'xls', 'zip', 'ttf', 'eot', 'ico', 'war'];
$leak = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($leak as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $ext = strtolower($file->getExtension());
    if ($ext === 'php' || in_array($ext, $binaryExt, true)) {
        continue;
    }
    $body = file_get_contents($file->getPathname());
    if ($body === false || str_contains($body, "\0")) {
        continue;
    }
    if (str_contains($body, '<?php') || str_contains($body, '<?=')) {
        fail('Human-readable PHP source remains in ' . $file->getPathname() . '. The build is aborting.');
    }
}

fwrite(STDOUT, 'Compiled and wiped ' . count($files) . " PHP files into {$cache}.\n");
