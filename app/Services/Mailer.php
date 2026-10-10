<?php

namespace App\Services;

/**
 * Plain SMTP sender. No extra package: the app does not use Composer for mail.
 */
final class Mailer
{
    public static function ready(array $settings): bool
    {
        return trim((string)($settings['smtp_host'] ?? '')) !== ''
            && filter_var(trim((string)($settings['smtp_from'] ?? '')), FILTER_VALIDATE_EMAIL);
    }

    public static function send(array $settings, string $to, string $subject, string $text): void
    {
        $to = trim($to);
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new \RuntimeException('That email address is not valid.');
        }
        if (!self::ready($settings)) {
            throw new \RuntimeException('Email is not set up yet. The guest link is ready.');
        }

        $host = trim((string)$settings['smtp_host']);
        $port = (int)($settings['smtp_port'] ?? 587);
        if ($port <= 0) {
            $port = 587;
        }
        $user = (string)($settings['smtp_user'] ?? '');
        $pass = (string)($settings['smtp_pass'] ?? '');
        $from = trim((string)$settings['smtp_from']);
        $fromName = trim((string)($settings['smtp_from_name'] ?? ''));
        $remote = ($port === 465 ? 'ssl://' : 'tcp://') . $host . ':' . $port;
        $fp = @stream_socket_client($remote, $errno, $errstr, 20, STREAM_CLIENT_CONNECT);
        if (!$fp) {
            throw new \RuntimeException('Could not reach the mail server.');
        }
        stream_set_timeout($fp, 20);

        $read = static function () use ($fp): string {
            $data = '';
            while (($line = fgets($fp, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return $data;
        };
        $expect = static function (string $command, array $ok) use ($fp, $read): void {
            fwrite($fp, $command . "\r\n");
            $resp = $read();
            $code = (int)substr($resp, 0, 3);
            if (!in_array($code, $ok, true)) {
                throw new \RuntimeException('The mail server refused the message (' . $code . ').');
            }
        };

        $greet = $read();
        if ((int)substr($greet, 0, 3) !== 220) {
            fclose($fp);
            throw new \RuntimeException('The mail server did not answer.');
        }
        $expect('EHLO smartrade', [250]);
        if ($port !== 465) {
            $expect('STARTTLS', [220]);
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($fp);
                throw new \RuntimeException('Could not start a secure mail connection.');
            }
            $expect('EHLO smartrade', [250]);
        }
        if ($user !== '') {
            $expect('AUTH LOGIN', [334]);
            $expect(base64_encode($user), [334]);
            $expect(base64_encode($pass), [235]);
        }
        $expect('MAIL FROM:<' . $from . '>', [250]);
        $expect('RCPT TO:<' . $to . '>', [250, 251]);
        $expect('DATA', [354]);

        $name = $fromName !== '' ? '=?UTF-8?B?' . base64_encode($fromName) . '?= ' : '';
        $headers = 'From: ' . $name . '<' . $from . ">\r\n"
            . 'To: <' . $to . ">\r\n"
            . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=UTF-8\r\n";
        $body = str_replace(["\r\n", "\r"], "\n", $text);
        $body = str_replace("\n", "\r\n", $body);
        $body = preg_replace('/^\./m', '..', $body) ?? $body;
        fwrite($fp, $headers . "\r\n" . $body . "\r\n.\r\n");
        $done = $read();
        fwrite($fp, "QUIT\r\n");
        fclose($fp);
        if ((int)substr($done, 0, 3) !== 250) {
            throw new \RuntimeException('The message was not accepted.');
        }
    }
}
