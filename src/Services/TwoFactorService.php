<?php
namespace App\Services;

use App\Core\Config;

/**
 * TwoFactorService — TOTP (RFC 6238) para Google Authenticator.
 * Implementación en PHP puro (sin dependencias): base32, HOTP/TOTP,
 * códigos de recuperación, cifrado del secreto en reposo (AES-256-GCM).
 */
class TwoFactorService
{
    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    public const PERIOD = 30;
    public const DIGITS = 6;

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function base32Encode(string $data): string
    {
        $bits = '';
        foreach (str_split($data) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
            $out .= self::BASE32[bindec($chunk)];
        }
        return $out;
    }

    public static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $secret));
        $bits = '';
        foreach (str_split($secret) as $c) {
            $pos = strpos(self::BASE32, $c);
            if ($pos === false) continue;
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) $out .= chr(bindec($byte));
        }
        return $out;
    }

    /** HOTP para un contador dado. */
    public static function hotp(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);
        if ($key === '') return '';
        $data = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $data, $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);
        $mod = (int)(10 ** self::DIGITS);
        return str_pad((string)($value % $mod), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /** Verifica un código TOTP con ventana de tolerancia (±$window pasos). */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) return false;
        $counter = (int)floor(time() / self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            $candidate = self::hotp($secret, $counter + $i);
            if ($candidate !== '' && hash_equals($candidate, $code)) return true;
        }
        return false;
    }

    public static function otpauthUrl(string $secret, string $account, string $issuer = 'Wontia WWI'): string
    {
        $label = rawurlencode($issuer . ':' . $account);
        $params = http_build_query([
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => 'SHA1',
            'digits' => self::DIGITS,
            'period' => self::PERIOD,
        ]);
        return 'otpauth://totp/' . $label . '?' . $params;
    }

    /** Códigos de recuperación (one-time). Devuelve [plainCodes, storedJson]. */
    public static function generateRecoveryCodes(int $count = 8, int $groups = 2, int $groupLen = 4): array
    {
        $plain = [];
        $stored = [];
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        for ($i = 0; $i < $count; $i++) {
            $parts = [];
            for ($g = 0; $g < $groups; $g++) {
                $s = '';
                for ($c = 0; $c < $groupLen; $c++) {
                    $s .= $alphabet[random_int(0, strlen($alphabet) - 1)];
                }
                $parts[] = $s;
            }
            $code = implode('-', $parts);
            $plain[] = $code;
            $stored[] = ['hash' => password_hash($code, PASSWORD_BCRYPT, ['cost' => 10]), 'used' => false, 'used_at' => null];
        }
        return [$plain, json_encode($stored)];
    }

    public static function verifyRecoveryCode(string $storedJson, string $code): array
    {
        $code = strtoupper(trim($code));
        $list = json_decode($storedJson ?: '[]', true);
        if (!is_array($list)) return ['ok' => false];
        foreach ($list as $i => $item) {
            if (!empty($item['used'])) continue;
            if (isset($item['hash']) && password_verify($code, $item['hash'])) {
                $list[$i]['used'] = true;
                $list[$i]['used_at'] = date('c');
                return ['ok' => true, 'remaining' => json_encode($list)];
            }
        }
        return ['ok' => false];
    }

    public static function remainingRecoveryCodes(string $storedJson): int
    {
        $list = json_decode($storedJson ?: '[]', true);
        if (!is_array($list)) return 0;
        return count(array_filter($list, fn($x) => empty($x['used'])));
    }

    public static function encrypt(string $plain): string
    {
        if ($plain === '') return '';
        if (!function_exists('openssl_encrypt')) return 'plain:' . base64_encode($plain);
        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) return 'plain:' . base64_encode($plain);
        return 'enc1:' . base64_encode($iv . $tag . $ct);
    }

    public static function decrypt(string $stored): string
    {
        if ($stored === '') return '';
        if (str_starts_with($stored, 'enc1:')) {
            if (!function_exists('openssl_decrypt')) return '';
            $raw = base64_decode(substr($stored, 5)) ?: '';
            if (strlen($raw) < 29) return '';
            $iv = substr($raw, 0, 12);
            $tag = substr($raw, 12, 16);
            $ct = substr($raw, 28);
            $pt = openssl_decrypt($ct, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
            return $pt === false ? '' : $pt;
        }
        if (str_starts_with($stored, 'plain:')) {
            return (string)(base64_decode(substr($stored, 6)) ?: '');
        }
        return $stored;
    }

    private static function key(): string
    {
        $secret = (string)Config::get('JWT_SECRET', '');
        if ($secret === '') $secret = 'wwi-fallback-2fa-key';
        return hash('sha256', 'wwi-2fa|' . $secret, true);
    }
}
