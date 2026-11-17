<?php
namespace App\Services;

use App\Core\Config;

class QuoteCrypto
{
    private const PREFIX = 'enc:v1:';

    public static function encrypt(string $plain): string
    {
        if (!function_exists('openssl_encrypt')) return $plain;
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) return $plain;
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(string $value): string
    {
        if (!str_starts_with($value, self::PREFIX)) return $value;
        $raw = base64_decode(substr($value, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 28) return '';
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }

    public static function isEncrypted(string $value): bool
    {
        return str_starts_with($value, self::PREFIX);
    }

    private static function key(): string
    {
        $secret = (string)(Config::get('QUOTE_ENCRYPTION_KEY', '') ?: Config::get('JWT_SECRET', '') ?: 'sonika-quote-local-key');
        return hash('sha256', 'sonika-quote|' . $secret, true);
    }
}
