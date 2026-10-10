<?php

namespace App\Helpers;

final class Media
{
    private const MAX_BYTES = 2500000;

    /** @var array<string,string> */
    private const TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    /**
     * Store an uploaded image under public/uploads/{folder}.
     * Returns the web path, or null when no file was chosen.
     */
    public static function store(string $field, string $folder): ?string
    {
        if (!isset($_FILES[$field])) {
            return null;
        }
        $files = self::fileList($_FILES[$field]);
        if (!$files) {
            return null;
        }
        return self::storeFile($files[0], $folder);
    }

    /**
     * Store up to $max images from one file input into the shared library.
     *
     * @return list<string>
     */
    public static function storeMany(string $field, int $max = 10): array
    {
        $files = isset($_FILES[$field]) ? self::fileList($_FILES[$field]) : [];
        if (!$files) {
            throw new \RuntimeException('Choose at least one image.');
        }
        if (count($files) > $max) {
            throw new \RuntimeException('Upload up to ' . $max . ' images at a time.');
        }
        $saved = [];
        foreach ($files as $file) {
            $saved[] = self::storeFile($file, 'library');
        }
        return $saved;
    }

    /**
     * Images already saved for the library, branding, and menu items.
     *
     * @return list<array{path:string,label:string,mtime:int}>
     */
    public static function list(): array
    {
        $root = dirname(__DIR__, 2) . '/public/uploads';
        $items = [];
        foreach (['library', 'branding', 'products'] as $folder) {
            $dir = $root . '/' . $folder;
            if (!is_dir($dir)) {
                continue;
            }
            foreach (scandir($dir) ?: [] as $name) {
                if (!preg_match('/^[a-z0-9][a-z0-9_-]{0,80}\.(jpg|png|webp|gif)$/', $name)) {
                    continue;
                }
                $full = $dir . '/' . $name;
                $items[] = [
                    'path' => 'uploads/' . $folder . '/' . $name,
                    'label' => self::labelFromFile($name, (int)(@filemtime($full) ?: 0)),
                    'mtime' => (int)(@filemtime($full) ?: 0),
                ];
            }
        }
        usort($items, static fn(array $a, array $b): int => $b['mtime'] <=> $a['mtime']);
        return $items;
    }

    public static function existing(string $relative): string
    {
        $url = self::url($relative);
        if ($url === '') {
            throw new \RuntimeException('That image is not in the library.');
        }
        return $url;
    }

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     */
    private static function storeFile(array $file, string $folder): string
    {
        if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file((string)($file['tmp_name'] ?? ''))) {
            throw new \RuntimeException('The image did not upload. Try a smaller file.');
        }
        if ((int)($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new \RuntimeException('Images must be 2.5 MB or smaller.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']) ?: '';
        if (!isset(self::TYPES[$mime])) {
            throw new \RuntimeException('Use a JPG, PNG, WEBP, or GIF image.');
        }
        $folder = preg_replace('/[^a-z0-9_-]/', '', $folder) ?: 'library';
        $dir = dirname(__DIR__, 2) . '/public/uploads/' . $folder;
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('The server could not store the image. The uploads folder is not writable.');
        }
        $name = self::uniqueName($dir, self::readableBase((string)($file['name'] ?? '')), self::TYPES[$mime]);
        if (!move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException('The image could not be saved.');
        }
        return 'uploads/' . $folder . '/' . $name;
    }

    /**
     * @param array<string,mixed> $file
     * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    private static function fileList(array $file): array
    {
        if (!is_array($file['name'] ?? null)) {
            if ((int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                return [];
            }
            return [[
                'name' => (string)($file['name'] ?? ''),
                'type' => (string)($file['type'] ?? ''),
                'tmp_name' => (string)($file['tmp_name'] ?? ''),
                'error' => (int)($file['error'] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int)($file['size'] ?? 0),
            ]];
        }
        $list = [];
        foreach ($file['name'] as $i => $name) {
            if ((int)($file['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $list[] = [
                'name' => (string)$name,
                'type' => (string)($file['type'][$i] ?? ''),
                'tmp_name' => (string)($file['tmp_name'][$i] ?? ''),
                'error' => (int)($file['error'][$i] ?? UPLOAD_ERR_NO_FILE),
                'size' => (int)($file['size'][$i] ?? 0),
            ];
        }
        return $list;
    }

    public static function delete(?string $relative): void
    {
        $relative = str_replace('\\', '/', (string)$relative);
        if (!preg_match('#^uploads/[a-z0-9_-]+/[a-z0-9][a-z0-9_-]{0,80}\.(jpg|png|webp|gif)$#', $relative)) {
            return;
        }
        $full = dirname(__DIR__, 2) . '/public/' . $relative;
        if (is_file($full)) {
            @unlink($full);
        }
    }

    public static function url(?string $relative): string
    {
        $relative = str_replace('\\', '/', (string)$relative);
        if (!preg_match('#^uploads/[a-z0-9_-]+/[a-z0-9][a-z0-9_-]{0,80}\.(jpg|png|webp|gif)$#', $relative)) {
            return '';
        }
        $full = dirname(__DIR__, 2) . '/public/' . $relative;
        return is_file($full) ? $relative : '';
    }

    private static function readableBase(string $original): string
    {
        $base = strtolower(pathinfo($original, PATHINFO_FILENAME));
        $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
        $base = trim($base, '-');
        if ($base === '' || preg_match('/^[a-f0-9]{12,}$/', $base)) {
            $base = 'photo';
        }
        return substr($base, 0, 60);
    }

    private static function uniqueName(string $dir, string $base, string $ext): string
    {
        $name = $base . '.' . $ext;
        $n = 2;
        while (is_file($dir . '/' . $name)) {
            $name = $base . '-' . $n . '.' . $ext;
            $n++;
            if ($n > 50) {
                return $base . '-' . bin2hex(random_bytes(2)) . '.' . $ext;
            }
        }
        return $name;
    }

    private static function labelFromFile(string $name, int $mtime = 0): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        if (preg_match('/^[a-f0-9]{12,}$/', $base)) {
            return $mtime > 0 ? date('j M Y, H:i', $mtime) : 'Saved photo';
        }
        $base = preg_replace('/-\d+$/', '', $base) ?? $base;
        return ucwords(str_replace('-', ' ', $base));
    }
}
