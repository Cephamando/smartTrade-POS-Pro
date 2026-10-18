<?php

/**
 * Fail the image build unless the runtime is executing precompiled bytecode
 * and the human-readable PHP source is gone.
 *
 * The process must already be using client/php/opcache-sealed.ini.
 */

declare(strict_types=1);

$root = '/var/www/html';
$cache = '/var/www/opcache';

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: {$message}\n");
    exit(1);
}

function iniOn(string $key): bool
{
    return filter_var(ini_get($key), FILTER_VALIDATE_BOOLEAN);
}

$expectedOn = [
    'opcache.enable',
    'opcache.enable_cli',
    'opcache.file_cache_only',
    'opcache.file_cache_consistency_checks',
    'opcache.enable_file_override',
    'opcache.validate_permission',
];
foreach ($expectedOn as $key) {
    if (!iniOn($key)) {
        fail("{$key} must be enabled in the sealed runtime (got " . var_export(ini_get($key), true) . ').');
    }
}
if (iniOn('opcache.validate_timestamps')) {
    fail('opcache.validate_timestamps must be disabled.');
}
if ((string) ini_get('opcache.revalidate_freq') !== '0') {
    fail('opcache.revalidate_freq must be 0 (got ' . ini_get('opcache.revalidate_freq') . ').');
}
if (iniOn('opcache.save_comments')) {
    fail('opcache.save_comments must be disabled so documentation comments are stripped.');
}
if (rtrim((string) ini_get('opcache.file_cache'), '/') !== $cache) {
    fail('opcache.file_cache must be ' . $cache . ' (got ' . ini_get('opcache.file_cache') . ').');
}
if ((string) ini_get('date.timezone') !== 'Africa/Lusaka') {
    fail('date.timezone must be Africa/Lusaka (got ' . ini_get('date.timezone') . ').');
}
if ((string) ini_get('memory_limit') !== '512M') {
    fail('memory_limit must stay 512M to match the application image.');
}

foreach (['mysqli', 'pdo_mysql', 'zip', 'gd', 'mbstring', 'exif', 'pcntl', 'bcmath'] as $extension) {
    if (!extension_loaded($extension)) {
        fail("Required PHP extension {$extension} is not loaded.");
    }
}
if (!extension_loaded('Zend OPcache')) {
    fail('Zend OPcache is not loaded.');
}

$forbidden = [
    '.git',
    '.env',
    '.env.example',
    '.env.save',
    'docker',
    'client',
    'docker-compose.yml',
    'Dockerfile',
    'output.php',
    'README.md',
    'docs',
    'scripts',
    'api',
    'app/Actions/src_pos.txt',
    'app/Views/templates_pos.txt',
];
foreach ($forbidden as $relative) {
    if (file_exists($root . '/' . $relative)) {
        fail("Build artifact still present in the runtime tree: {$relative}");
    }
}

$phpFiles = [];
$binaryExt = ['woff', 'woff2', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'pdf', 'xlsx', 'xls', 'zip', 'ttf', 'eot', 'ico', 'war'];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);
foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $path = $file->getPathname();
    $ext = strtolower($file->getExtension());
    if ($ext === 'php') {
        $phpFiles[] = $path;
        if ($file->getSize() !== 0) {
            fail("PHP file {$path} is not zero bytes.");
        }
        $owner = fileowner($path);
        if ($owner !== 33) {
            fail("PHP file {$path} is owned by uid {$owner}, expected www-data (33).");
        }
        continue;
    }
    if (in_array($ext, $binaryExt, true)) {
        continue;
    }
    $body = file_get_contents($path);
    if ($body === false || str_contains($body, "\0")) {
        continue;
    }
    if (str_contains($body, '<?php') || str_contains($body, '<?=')) {
        fail("Human-readable PHP source remains in {$path}.");
    }
}

if ($phpFiles === []) {
    fail('The runtime tree has no PHP files.');
}

$bins = [];
$binIterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($cache, FilesystemIterator::SKIP_DOTS)
);
foreach ($binIterator as $file) {
    if (!$file->isFile() || !str_ends_with($file->getPathname(), '.bin')) {
        continue;
    }
    $path = $file->getPathname();
    // The verifier itself may be compiled into the cache while this process runs.
    if (!str_contains($path, $root . '/')) {
        continue;
    }
    $bins[] = $path;
    $header = file_get_contents($path, false, null, 0, 8);
    if ($header !== "OPCACHE\0") {
        fail('Cache entry is not Zend OPcache bytecode: ' . $path);
    }
    if (fileowner($path) !== 33) {
        fail('Bytecode is not owned by www-data: ' . $path);
    }
    if ((fileperms($path) & 0777) !== 0400) {
        fail('Bytecode must be mode 0400: ' . $path);
    }
}

if (count($bins) !== count($phpFiles)) {
    fail('Runtime bytecode count ' . count($bins) . ' does not match PHP file count ' . count($phpFiles) . '.');
}
foreach ($phpFiles as $path) {
    $real = realpath($path);
    if ($real === false) {
        fail("Cannot resolve {$path}.");
    }
    $pattern = '#^' . preg_quote($cache, '#') . '/[0-9a-f]{32}' . preg_quote($real, '#') . '\.bin$#';
    $matches = 0;
    foreach ($bins as $bin) {
        if (preg_match($pattern, $bin) === 1) {
            $matches++;
        }
    }
    if ($matches !== 1) {
        fail("No sealed bytecode for {$real} (found {$matches}).");
    }
}

$config = require $root . '/config/database.php';
if (!is_array($config) || ($config['host'] ?? '') !== 'db' || ($config['charset'] ?? '') !== 'utf8mb4') {
    fail('CLI require of config/database.php did not return the compiled config array.');
}

require $root . '/app/Core/Autoloader.php';
if (!class_exists(App\Core\Autoloader::class, false)) {
    fail('CLI require of Autoloader.php did not define the compiled class.');
}

$routes = require $root . '/app/Config/routes.php';
if (!is_array($routes) || !isset($routes['login'])) {
    fail('CLI require of routes.php did not return the compiled route table.');
}

$cmd = [
    PHP_BINARY,
    '-r',
    'fwrite(STDOUT, json_encode(require $argv[1]));',
    '--',
    $root . '/config/database.php',
];
$pipes = [];
$proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
if (!is_resource($proc)) {
    fail('Unable to launch a nested PHP CLI process.');
}
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($proc);
$decoded = json_decode($stdout === false ? '' : $stdout, true);
if ($exit !== 0 || !is_array($decoded) || ($decoded['host'] ?? '') !== 'db') {
    fail("Nested PHP CLI invocation did not execute precompiled bytecode (exit {$exit}).\n{$stderr}");
}

fwrite(STDOUT, 'Sealed runtime verified: ' . count($phpFiles) . " zero-byte PHP files, CLI bytecode execution ok.\n");
