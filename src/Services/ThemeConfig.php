<?php
namespace App\Services;

use App\Core\Config;
use App\Core\Database;

/**
 * ThemeConfig — configuración editable por theme, schema-driven.
 * El schema vive en `theme.json` (clave "config"); los valores se guardan en
 * `settings` como `theme.<slug>.<campo>` (aislado por theme + site_id).
 */
class ThemeConfig
{
    private static array $cache = [];

    public static function schema(string $slug): array
    {
        if (!preg_match('/^[a-z0-9\-]{2,50}$/', $slug)) return [];
        $f = ROOT_DIR . '/templates/themes/' . $slug . '/theme.json';
        if (!is_file($f)) return [];
        $m = json_decode((string)file_get_contents($f), true) ?: [];
        $cfg = $m['config'] ?? [];
        return is_array($cfg) ? $cfg : [];
    }

    private static function defaults(string $slug): array
    {
        $out = [];
        foreach (self::schema($slug) as $f) {
            if (empty($f['key']) || ($f['type'] ?? '') === 'heading') continue;
            $out[$f['key']] = $f['default'] ?? '';
        }
        return $out;
    }

    public static function all(string $slug): array
    {
        $ck = $slug . '|' . (int)Config::get('SITE_ID', '1');
        if (isset(self::$cache[$ck])) return self::$cache[$ck];
        $vals = self::defaults($slug);
        try {
            $prefix = 'theme.' . $slug . '.';
            $st = Database::instance()->prepare('SELECT `key`,`value` FROM settings WHERE site_id = @site_id AND `key` LIKE ?');
            $st->execute([$prefix . '%']);
            foreach ($st->fetchAll() as $r) {
                $vals[substr($r['key'], strlen($prefix))] = self::decode((string)$r['value']);
            }
        } catch (\Throwable $e) {
        }
        self::$cache[$ck] = $vals;
        return $vals;
    }

    public static function get(string $slug, string $key, $default = null)
    {
        $all = self::all($slug);
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    public static function save(string $slug, array $values): array
    {
        $known = [];
        foreach (self::schema($slug) as $f) {
            if (!empty($f['key'])) $known[$f['key']] = true;
        }
        $db = Database::instance();
        $st = $db->prepare('INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)');
        foreach ($values as $k => $v) {
            if (!isset($known[$k])) continue;
            $st->execute(['theme.' . $slug . '.' . $k, self::encode($v)]);
        }
        self::$cache = [];
        return ['ok' => true];
    }

    private static function encode($v): string
    {
        if (is_array($v)) return (string)json_encode($v, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_bool($v)) return $v ? '1' : '0';
        return (string)$v;
    }

    private static function decode(string $s)
    {
        if ($s === '') return '';
        $c = $s[0];
        if ($c === '[' || $c === '{') {
            $d = json_decode($s, true);
            if ($d !== null) return $d;
        }
        return $s;
    }
}
