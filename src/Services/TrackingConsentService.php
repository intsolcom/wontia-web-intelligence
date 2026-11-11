<?php
namespace App\Services;

/**
 * Consentimiento de tracking del ecosistema WWI.
 * Compatible con el valor legado `all` / `necessary` y con el JSON granular:
 * {"necessary":true,"analytics":true,"marketing":false}
 */
class TrackingConsentService
{
    public const COOKIE = 'sonika_cookie_consent';
    public const LOCAL_KEY = 'wontia_cookie_consent';

    public static function parse(?string $raw): array
    {
        $out = ['necessary' => true, 'analytics' => false, 'marketing' => false];
        $raw = trim((string)$raw);
        if ($raw === '') return $out;
        if ($raw === 'all') {
            $out['analytics'] = true;
            $out['marketing'] = true;
            return $out;
        }
        if ($raw === 'necessary') return $out;

        $data = json_decode($raw, true);
        if (!is_array($data)) return $out;
        if (array_key_exists('analytics', $data)) $out['analytics'] = !empty($data['analytics']);
        if (array_key_exists('marketing', $data)) $out['marketing'] = !empty($data['marketing']);
        return $out;
    }

    public static function current(): array
    {
        return self::parse((string)($_COOKIE[self::COOKIE] ?? ''));
    }

    public static function allows(string $category): bool
    {
        if (!in_array($category, ['analytics', 'marketing'], true)) return false;
        $consent = self::current();
        return !empty($consent[$category]);
    }

    public static function encode(bool $analytics, bool $marketing): string
    {
        return (string)json_encode([
            'necessary' => true,
            'analytics' => $analytics,
            'marketing' => $marketing,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public static function snapshot(): string
    {
        $consent = self::current();
        return ($consent['analytics'] ? 'A' : '-') . ($consent['marketing'] ? 'M' : '-');
    }
}
