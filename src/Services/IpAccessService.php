<?php
namespace App\Services;

use App\Core\Session;

/**
 * Visibilidad de productos por IP y protecciones de acceso.
 * No reemplaza la autorización del panel: es una capa de publicación.
 */
class IpAccessService
{
    /** IP real del cliente. Reutiliza la lógica robusta de proxy de SEO. */
    public static function clientIp(): string
    {
        try {
            $ip = \App\Services\SeoInternalTrafficService::clientIp();
            if (is_string($ip) && $ip !== '') return $ip;
        } catch (\Throwable $e) {
        }
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $raw = trim((string)($_SERVER[$key] ?? ''));
            if ($raw === '') continue;
            foreach (explode(',', $raw) as $candidate) {
                $candidate = trim($candidate);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) return $candidate;
            }
        }
        return '127.0.0.1';
    }

    /** ¿La sesión actual es de un administrador del CMS? */
    public static function isAdminSession(): bool
    {
        try {
            $user = Session::user();
            if (!is_array($user)) return false;
            $role = strtolower(trim((string)($user['role'] ?? '')));
            return in_array($role, ['admin', 'superadmin', 'owner'], true);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public static function isPrivate(array $product): bool
    {
        return strtolower(trim((string)($product['visibility'] ?? 'public'))) === 'private';
    }

    /** Convierte “1.2.3.4, 10.0.0.0/8, 192.168.1.*” en reglas limpias. */
    public static function normalizeRules(string $raw): array
    {
        $raw = str_replace(["\r", "\n", ';'], ',', $raw);
        $out = [];
        foreach (explode(',', $raw) as $rule) {
            $rule = trim($rule);
            if ($rule === '' || strlen($rule) > 45) continue;
            if (!preg_match('/^[0-9a-fA-F:.\\/\\*]+$/', $rule)) continue;
            if (!in_array($rule, $out, true)) $out[] = $rule;
        }
        return $out;
    }

    public static function ipMatches(string $ip, string $rule): bool
    {
        $ip = trim($ip);
        $rule = trim($rule);
        if ($ip === '' || $rule === '') return false;

        if (str_contains($rule, '/')) {
            [$subnet, $bits] = array_pad(explode('/', $rule, 2), 2, '');
            $bits = (int)$bits;
            if ($subnet === '' || $bits < 0 || $bits > 32) return false;
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
            if (!filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) return false;
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            if ($ipLong === false || $subnetLong === false) return false;
            $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));
            return ($ipLong & $mask) === ($subnetLong & $mask);
        }

        if (str_contains($rule, '*')) {
            $regex = '/^' . str_replace('\\*', '[0-9]{1,3}', preg_quote($rule, '/')) . '$/';
            return (bool)preg_match($regex, $ip);
        }

        return hash_equals(strtolower($rule), strtolower($ip));
    }

    /** Regla central: un producto privado solo lo ven admins o IPs autorizadas. */
    public static function canViewProduct(array $product): bool
    {
        if (!self::isPrivate($product)) return true;
        if (self::isAdminSession()) return true;
        $rules = self::normalizeRules((string)($product['allowed_ips'] ?? ''));
        if (!$rules) return false;
        $ip = self::clientIp();
        foreach ($rules as $rule) {
            if (self::ipMatches($ip, $rule)) return true;
        }
        return false;
    }

    public static function filterVisible(array $products): array
    {
        return array_values(array_filter($products, static fn($p) => self::canViewProduct((array)$p)));
    }

    /** Texto seguro para mostrar en el admin (nunca la IP completa al público). */
    public static function maskIp(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $ip);
            if (count($parts) === 4) return $parts[0] . '.' . $parts[1] . '.x.x';
        }
        return $ip !== '' ? substr($ip, 0, 8) . '…' : '';
    }
}
