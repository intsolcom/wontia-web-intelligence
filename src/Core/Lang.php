<?php
namespace App\Core;

/**
 * Lang — internacionalización ligera (ES/EN) del frontend.
 * Locale: cookie 'wwi_lang' → Accept-Language → default 'es'.
 * Los textos viven en /lang/<locale>.php (clave => texto).
 */
class Lang
{
    private static string $locale = 'es';
    private static array $strings = [];
    private static array $available = ['es', 'en'];

    public static function init(?string $requested = null): void
    {
        $loc = $requested ?: (string)($_COOKIE['wwi_lang'] ?? '');
        if ($loc === '') $loc = self::fromHeader((string)($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        if (!in_array($loc, self::$available, true)) $loc = 'es';
        self::$locale = $loc;

        $base = dirname(__DIR__, 2) . '/lang/es.php';
        $strings = is_file($base) ? (include $base) : [];
        if ($loc !== 'es') {
            $f = dirname(__DIR__, 2) . '/lang/' . $loc . '.php';
            if (is_file($f)) $strings = array_merge($strings, (include $f));
        }
        self::$strings = is_array($strings) ? $strings : [];
    }

    public static function current(): string
    {
        return self::$locale;
    }

    public static function available(): array
    {
        return self::$available;
    }

    public static function label(string $code): string
    {
        return ['es' => 'Español', 'en' => 'English'][$code] ?? strtoupper($code);
    }

    public static function get(string $key, ?string $default = null): string
    {
        if (!self::$strings) self::init();
        return (string)(self::$strings[$key] ?? ($default ?? $key));
    }

    private static function fromHeader(string $header): string
    {
        foreach (explode(',', strtolower($header)) as $part) {
            $l = substr(trim(explode(';', $part)[0]), 0, 2);
            if (in_array($l, self::$available, true)) return $l;
        }
        return '';
    }
}
