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
        if (!isset($_FILES[$field]) || (int)($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        $file = $_FILES[$field];
        if ((int)$file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file((string)$file['tmp_name'])) {
            throw new \RuntimeException('The image did not upload. Try a smaller file.');
        }
        if ((int)$file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException('Images must be 2.5 MB or smaller.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file((string)$file['tmp_name']) ?: '';
        if (!isset(self::TYPES[$mime])) {
            throw new \RuntimeException('Use a JPG, PNG, WEBP, or GIF image.');
        }
        $folder = preg_replace('/[^a-z0-9_-]/', '', $folder) ?: 'misc';
        $dir = dirname(__DIR__, 2) . '/public/uploads/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('The server could not store the image.');
        }
        $name = bin2hex(random_bytes(8)) . '.' . self::TYPES[$mime];
        if (!move_uploaded_file((string)$file['tmp_name'], $dir . '/' . $name)) {
            throw new \RuntimeException('The image could not be saved.');
        }
        return 'uploads/' . $folder . '/' . $name;
    }

    public static function delete(?string $relative): void
    {
        $relative = str_replace('\\', '/', (string)$relative);
        if (!preg_match('#^uploads/[a-z0-9_-]+/[a-f0-9]{16}\.(jpg|png|webp|gif)$#', $relative)) {
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
        if (!preg_match('#^uploads/[a-z0-9_-]+/[a-f0-9]+\.(jpg|png|webp|gif)$#', $relative)) {
            return '';
        }
        $full = dirname(__DIR__, 2) . '/public/' . $relative;
        return is_file($full) ? $relative : '';
    }
}
