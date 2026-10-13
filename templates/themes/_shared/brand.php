<?php
/* WWI Brand — parcial compartido por todos los temas.
   Lee la marca (logo/favicon) desde settings del sitio (@site_id) y expone helpers.
   Uso en el tema: require_once .../brand.php; luego wwi_favicon_links() y wwi_logo_img(). */
use App\Core\Database;

if (!function_exists('wwi_brand')) {
    function wwi_brand(): array
    {
        static $b = null;
        if ($b !== null) return $b;
        $b = ['logo' => '', 'logo_dark' => '', 'logo_height' => 40, 'show_text' => true, 'text' => 'WONTIA', 'alt' => 'Logo', 'favicon' => '', 'favicon_touch' => '', 'theme_color' => '', 'asset_version' => ''];
        try {
            $st = Database::instance()->prepare("SELECT `key`,`value` FROM settings WHERE site_id = @site_id AND `key` IN ('logo_image','logo_dark','logo_height','logo_show_text','logo_text','logo_alt','favicon','favicon_touch','theme_color','asset_version')");
            $st->execute();
            $s = [];
            foreach ($st->fetchAll() as $r) $s[$r['key']] = $r['value'];
            if (!empty($s['logo_image'])) $b['logo'] = trim((string)$s['logo_image']);
            if (!empty($s['logo_dark'])) $b['logo_dark'] = trim((string)$s['logo_dark']);
            if (isset($s['logo_height']) && (int)$s['logo_height'] > 0) $b['logo_height'] = max(12, min(200, (int)$s['logo_height']));
            if (isset($s['logo_show_text'])) $b['show_text'] = ((string)$s['logo_show_text']) !== '0';
            if (!empty($s['logo_text'])) $b['text'] = trim((string)$s['logo_text']);
            if (!empty($s['logo_alt'])) $b['alt'] = trim((string)$s['logo_alt']);
            if (isset($s['favicon'])) $b['favicon'] = trim((string)$s['favicon']);
            if (isset($s['favicon_touch'])) $b['favicon_touch'] = trim((string)$s['favicon_touch']);
            if (isset($s['theme_color'])) $b['theme_color'] = trim((string)$s['theme_color']);
            if (isset($s['asset_version'])) $b['asset_version'] = trim((string)$s['asset_version']);
        } catch (\Throwable $e) {
        }
        return $b;
    }
}

if (!function_exists('wwi_ver')) {
    function wwi_ver(string $url): string
    {
        $url = trim($url);
        $v = trim((string)(wwi_brand()['asset_version'] ?? ''));
        if ($url === '' || $v === '' || strpos($url, 'data:') === 0) return $url;
        return $url . (strpos($url, '?') !== false ? '&' : '?') . 'v=' . rawurlencode($v);
    }
}

if (!function_exists('wwi_favicon_links')) {
    function wwi_favicon_links(): string
    {
        $b = wwi_brand();
        $out = '';
        if ($b['favicon'] !== '') {
            $path = parse_url($b['favicon'], PHP_URL_PATH) ?: '';
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $type = $ext === 'ico' ? 'image/x-icon' : ($ext === 'png' ? 'image/png' : ($ext === 'svg' ? 'image/svg+xml' : 'image/png'));
            $out .= '<link rel="icon" type="' . $type . '" href="' . htmlspecialchars(wwi_ver($b['favicon'])) . '">' . "\n";
        }
        if ($b['favicon_touch'] !== '') {
            $out .= '<link rel="apple-touch-icon" href="' . htmlspecialchars(wwi_ver($b['favicon_touch'])) . '">' . "\n";
        }
        if ($b['theme_color'] !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $b['theme_color'])) {
            $out .= '<meta name="theme-color" content="' . htmlspecialchars($b['theme_color']) . '">' . "\n";
        }
        return $out;
    }
}

if (!function_exists('wwi_logo_img')) {
    function wwi_logo_img(int $max = 160): string
    {
        $b = wwi_brand();
        if ($b['logo'] === '') return '';
        $h = max(12, min($max, (int)$b['logo_height']));
        return '<img src="' . htmlspecialchars(wwi_ver($b['logo'])) . '" alt="' . htmlspecialchars($b['alt']) . '" style="height:' . $h . 'px;width:auto;max-width:100%;display:block"/>';
    }
}

if (!function_exists('wwi_setting')) {
    /** Lee un setting del sitio actual (con caché por request). */
    function wwi_setting(string $key, $default = '')
    {
        static $cache = [];
        if (array_key_exists($key, $cache)) return $cache[$key];
        $v = $default;
        try {
            $st = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1");
            $st->execute([$key]);
            $row = $st->fetch();
            if ($row && $row['value'] !== null && $row['value'] !== '') $v = $row['value'];
        } catch (\Throwable $e) {
        }
        $cache[$key] = $v;
        return $v;
    }
}

if (!function_exists('wwi_nav')) {
    /** Navegación administrable por ubicación (primary, footer_shop, footer_help, footer_legal). */
    function wwi_nav(string $location = 'primary', bool $visibleOnly = true): array
    {
        try {
            return (new \App\Services\NavService())->tree($location, $visibleOnly);
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('wwi_tc')) {
    /** Config de theme (schema en theme.json 'config'). Uso: wwi_tc('sonika','hero_title','default'). */
    function wwi_tc(string $slug, string $key, $default = null)
    {
        try {
            $v = \App\Services\ThemeConfig::get($slug, $key, $default);
            return ($v === '' || $v === null) ? $default : $v;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('t')) {
    /** Traducción i18n. Uso: t('add_to_cart'). Locale en \App\Core\Lang. */
    function t(string $key, ?string $default = null): string
    {
        try {
            return \App\Core\Lang::get($key, $default);
        } catch (\Throwable $e) {
            return (string)($default ?? $key);
        }
    }
}

if (!function_exists('wwi_site_name')) {
    function wwi_site_name(string $fallback = 'Wontia'): string
    {
        $n = (string)wwi_setting('site_name', '');
        if ($n !== '') return $n;
        $b = wwi_brand();
        return (string)($b['text'] ?? $fallback) ?: $fallback;
    }
}
