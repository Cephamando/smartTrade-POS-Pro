<?php

namespace App\Services;

/** Time-based one-time codes (RFC 6238) with a SHA-1 HMAC and a 30-second step. */
final class Totp
{
    public static function secret(): string
    {
        return self::base32(random_bytes(20));
    }

    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (!preg_match('/^\d{6}$/', $code)) {
            return false;
        }
        $key = self::unbase32($secret);
        if ($key === '') {
            return false;
        }
        $slice = intdiv(time(), 30);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::codeAt($key, $slice + $i), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function uri(string $secret, string $account, string $issuer): string
    {
        $issuer = $issuer !== '' ? $issuer : 'smartTrade';
        $label = rawurlencode($issuer . ':' . $account);
        return 'otpauth://totp/' . $label
            . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer)
            . '&digits=6&period=30';
    }

    /** @return list<string> */
    public static function recoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = strtoupper(substr(bin2hex(random_bytes(5)), 0, 10));
        }
        return $codes;
    }

    private static function codeAt(string $key, int $slice): string
    {
        $counter = pack('N', 0) . pack('N', $slice);
        $hash = hash_hmac('sha1', $counter, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $unpacked = unpack('N', substr($hash, $offset, 4));
        $value = ($unpacked[1] ?? 0) & 0x7fffffff;
        return str_pad((string)($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function base32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        $length = strlen($bytes);
        for ($i = 0; $i < $length; $i++) {
            $bits .= str_pad(decbin(ord($bytes[$i])), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            }
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }

    private static function unbase32(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper(preg_replace('/[^A-Z2-7]/', '', $secret) ?? '');
        if ($secret === '') {
            return '';
        }
        $bits = '';
        $length = strlen($secret);
        for ($i = 0; $i < $length; $i++) {
            $pos = strpos($alphabet, $secret[$i]);
            if ($pos === false) {
                return '';
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) < 8) {
                continue;
            }
            $out .= chr(bindec($chunk));
        }
        return $out;
    }
}
