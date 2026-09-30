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
        $b = ['logo' => '', 'logo_dark' => '', 'logo_height' => 40, 'show_text' => true, 'text' => 'WONTIA', 'alt' => 'Logo', 'favicon' => '', 'favicon_touch' => '', 'theme_color' => ''];
        try {
            $st = Database::instance()->prepare("SELECT `key`,`value` FROM settings WHERE site_id = @site_id AND `key` IN ('logo_image','logo_dark','logo_height','logo_show_text','logo_text','logo_alt','favicon','favicon_touch','theme_color')");
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
        } catch (\Throwable $e) {
        }
        return $b;
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
            $out .= '<link rel="icon" type="' . $type . '" href="' . htmlspecialchars($b['favicon']) . '">' . "\n";
        }
        if ($b['favicon_touch'] !== '') {
            $out .= '<link rel="apple-touch-icon" href="' . htmlspecialchars($b['favicon_touch']) . '">' . "\n";
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
        return '<img src="' . htmlspecialchars($b['logo']) . '" alt="' . htmlspecialchars($b['alt']) . '" style="height:' . $h . 'px;width:auto;max-width:100%;display:block"/>';
    }
}
