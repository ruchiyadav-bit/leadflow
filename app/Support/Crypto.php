<?php
declare(strict_types=1);

namespace LeadFlow\Support;

use LeadFlow\Core\Config;

/** AES-256-GCM encryption keyed from APP_KEY. */
final class Crypto
{
    private static function key(): string
    {
        $appKey = (string)Config::env('APP_KEY', '');
        if ($appKey === '' || str_starts_with($appKey, 'change-me')) {
            throw new \RuntimeException('APP_KEY must be set to a long random string before storing encrypted data');
        }
        return hash('sha256', $appKey, true);
    }

    public static function encrypt(string $plain): string
    {
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) throw new \RuntimeException('Encryption failed');
        return 'v1:' . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $enc): ?string
    {
        if ($enc === null || $enc === '' || !str_starts_with($enc, 'v1:')) return null;
        $raw = base64_decode(substr($enc, 3), true);
        if ($raw === false || strlen($raw) < 29) return null;
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }
}
