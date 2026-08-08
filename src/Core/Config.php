<?php
namespace App\Core;

class Config
{
    private static array $items = [];
    private static bool $loaded = false;
    public static string $defaultTheme = 'default';

    public static function load(string $path = null): void
    {
        if (self::$loaded) return;
        $path = $path ?? (defined('ROOT_DIR') ? ROOT_DIR . '/.env' : dirname(__DIR__, 2) . '/.env');
        if (!file_exists($path)) {
            $path = dirname(__DIR__, 2) . '/.env.example';
        }
        if (file_exists($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) continue;
                $eq = strpos($line, '=');
                if ($eq === false) continue;
                $key = trim(substr($line, 0, $eq));
                $value = trim(substr($line, $eq + 1));
                $value = trim($value, '"\'');
                $value = str_replace(['\n', '\r'], ["\n", "\r"], $value);
                self::$items[$key] = $value;
                $_ENV[$key] = $value;
                putenv("$key=$value");
            }
        }
        self::$loaded = true;
    }

    public static function get(string $key, $default = null): mixed
    {
        if (!self::$loaded) self::load();
        return self::$items[$key] ?? $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function all(): array
    {
        if (!self::$loaded) self::load();
        return self::$items;
    }

    public static function set(string $key, $value): void
    {
        self::$items[$key] = $value;
        $_ENV[$key] = $value;
    }
}
