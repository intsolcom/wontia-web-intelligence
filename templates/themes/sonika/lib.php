<?php
use App\Core\Config;
use App\Services\SeoService;
use App\Services\CookieConsentService;
use App\Services\AnalyticsService;

require_once __DIR__ . '/../_shared/brand.php';
require_once __DIR__ . '/../_shared/capabilities.php';

if (!function_exists('sonika_esc')) {
    function sonika_esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('sonika_cop')) {
    function sonika_cop($value): string { return (new \App\Services\CurrencyService())->format((float)$value); }
}
if (!function_exists('sonika_whatsapp')) {
    function sonika_whatsapp(): string {
        $n = trim((string)wwi_setting('store_whatsapp', ''));
        if ($n === '') $n = trim((string)wwi_tc('sonika', 'contact_phone'));
        $d = preg_replace('/\D+/', '', $n);
        if ($d !== '' && strlen($d) === 10 && substr($d, 0, 2) !== '57') $d = '57' . $d;
        return $d;
    }
}
if (!function_exists('sonika_safe_html')) {
    function sonika_safe_html(string $html): string {
        $html = preg_replace('#<\s*script\b[^>]*>.*?<\s*/\s*script\s*>#is', '', $html);
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        $html = preg_replace('#(href|src)\s*=\s*("|\')\s*javascript:[^"\']*("|\')#i', '$1="#"', $html);
        return $html;
    }
}
if (!function_exists('sonika_json')) {
    function sonika_json(string $url, int $ttl = 600): array {
        $cache = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'sonika_' . md5($url) . '.json';
        if (is_file($cache) && (time() - filemtime($cache)) < $ttl) {
            $c = json_decode((string)@file_get_contents($cache), true);
            if (is_array($c)) return $c;
        }
        $raw = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'SonikaCatalog/1.0 (+https://sonika.com.co)']);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
        if (!$raw && ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: SonikaCatalog/1.0\r\n"]]);
            $raw = @file_get_contents($url, false, $ctx);
        }
        if (!is_string($raw) || $raw === '') return [];
        @file_put_contents($cache, $raw);
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
}
if (!function_exists('sonika_font')) {
    /** Familia configurada (Settings → Tipografía) o 'Sora'. Saneada. */
    function sonika_font(string $role): string {
        $key = ($role === 'display') ? 'font_family_display' : 'font_family_body';
        $v = trim((string)wwi_setting($key, ''));
        if ($v === '' || !preg_match('/^[A-Za-z0-9 \-\']+$/', $v)) $v = 'Sora';
        return $v;
    }
}
if (!function_exists('sonika_font_stack')) {
    function sonika_font_stack(string $role): string {
        return "'" . sonika_font($role) . "',system-ui,sans-serif";
    }
}
if (!function_exists('sonika_fonts_url')) {
    function sonika_fonts_url(): string {
        $fb = sonika_font('body');
        $fd = sonika_font('display');
        $fams = array_values(array_unique(array_filter([$fb, $fd, function_exists('sonika_nav_font') ? sonika_nav_font() : 'Sora'])));
        $parts = [];
        foreach ($fams as $f) $parts[] = 'family=' . str_replace(' ', '+', $f) . ':wght@400;500;600;700;800';
        return 'https://fonts.googleapis.com/css2?' . implode('&', $parts) . '&display=swap';
    }
}
if (!function_exists('sonika_nav_style')) {
    function sonika_nav_style(): array {
        static $s = null;
        if ($s !== null) return $s;
        $d = json_decode((string)wwi_setting('nav_menu_style', ''), true);
        $d = is_array($d) ? $d : [];
        $hex = fn($v, $f) => preg_match('/^#[0-9a-f]{6}$/i', (string)$v) ? (string)$v : $f;
        $font = trim((string)($d['font'] ?? 'Sora'));
        if ($font === '' || !preg_match('/^[A-Za-z0-9 \-\']+$/', $font)) $font = 'Sora';
        $s = [
            'align' => in_array((string)($d['align'] ?? 'left'), ['left', 'center', 'right'], true) ? (string)$d['align'] : 'left',
            'bg' => $hex($d['bg'] ?? '', '#111111'),
            'color' => $hex($d['color'] ?? '', '#ffffff'),
            'hover_bg' => $hex($d['hover_bg'] ?? '', '#1f1f1f'),
            'accent' => $hex($d['accent'] ?? '', '#F20D16'),
            'position' => in_array((string)($d['position'] ?? 'below'), ['above', 'below'], true) ? (string)$d['position'] : 'below',
            'font' => $font,
            'size' => max(11, min(22, (int)($d['size'] ?? 14))),
        ];
        return $s;
    }
}
if (!function_exists('sonika_nav_font')) {
    function sonika_nav_font(): string { $s = sonika_nav_style(); return (string)$s['font']; }
}
if (!function_exists('sonika_nav_style_head')) {
    function sonika_nav_style_head(): string {
        $s = sonika_nav_style();
        $just = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'][$s['align']] ?? 'flex-start';
        $e = fn($x) => htmlspecialchars((string)$x, ENT_QUOTES, 'UTF-8');
        $css = '.mainnav{background:' . $e($s['bg']) . '}'
            . '.mainnav ul{justify-content:' . $just . '}'
            . '.mainnav li>a{color:' . $e($s['color']) . ';font-family:\'' . $e($s['font']) . '\',var(--font-body);font-size:' . (int)$s['size'] . 'px}'
            . '.mainnav li>a:hover,.mainnav li>a:focus-visible{background:' . $e($s['hover_bg']) . ';color:' . $e($s['color']) . '}'
            . '.mainnav li>a.hot{border-bottom-color:' . $e($s['accent']) . '}';
        return '<style>' . $css . '</style>';
    }
}
if (!function_exists('sonika_catalog')) {
    function sonika_catalog(): array {
        static $c = null;
        if ($c !== null) return $c;
        // 1) Catálogo SINCRONIZADO (persistente, refrescado por cron) — tiene prioridad
        $synced = dirname(__DIR__, 3) . '/public/assets/uploads/_catalog/catalog.json';
        if (is_file($synced)) {
            $d = json_decode((string)@file_get_contents($synced), true);
            if (is_array($d) && !empty($d['products'])) { $c = $d; return $c; }
        }
        // 2) Snapshot empaquetado del tema
        $f = __DIR__ . '/catalog.json';
        if (is_file($f)) {
            $c = json_decode((string)@file_get_contents($f), true) ?: [];
        } else {
            $c = sonika_json('https://www.miche.com.co/collections/all/products.json?limit=250');
        }
        return $c;
    }
}
if (!function_exists('sonika_store_local')) {
    /** Productos de la Tienda WWI (store_products) alojados en el sitio, mapeados al formato del catálogo. */
    function sonika_store_local(): array {
        static $out = null;
        if ($out !== null) return $out;
        $out = [];
        try {
            $db = \App\Core\Database::instance();
            $st = $db->query("SELECT * FROM store_products WHERE site_id = @site_id AND status = 'active'");
            foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) {
                $imgs = [];
                foreach (['images', 'image'] as $k) {
                    if (empty($r[$k])) continue;
                    $v = $r[$k];
                    if (is_string($v) && $v !== '' && $v[0] === '[') {
                        $j = json_decode($v, true);
                        if (is_array($j)) { foreach ($j as $x) { $imgs[] = is_string($x) ? $x : (string)($x['url'] ?? ($x['src'] ?? '')); } continue; }
                    }
                    if (is_string($v) && $v !== '') $imgs[] = $v;
                }
                $imgs = array_values(array_filter($imgs));
                $price = (float)((int)($r['price_cents'] ?? 0)) / 100;
                $cmp = (float)((int)($r['compare_cents'] ?? 0)) / 100;
                $stock = (int)($r['stock'] ?? 0);
                $pt = trim((string)($r['product_type'] ?? '')) ?: 'Tienda';
                $out[] = [
                    'handle' => (string)($r['slug'] ?: $r['id']),
                    'title' => (string)($r['name'] ?? ''),
                    'vendor' => (string)($r['vendor'] ?? ''),
                    'product_type' => $pt,
                    'body_html' => (string)($r['description'] ?? ''),
                    'published_at' => (string)($r['created_at'] ?? ''),
                    'images' => array_map(fn($u) => ['src' => $u], $imgs),
                    'tags' => ['1) Categoría_' . $pt],
                    'variants' => [['price' => $price, 'compare_at_price' => $cmp, 'available' => $stock > 0, 'sku' => (string)($r['sku'] ?? '')]],
                    '_local' => true,
                ];
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }
}
if (!function_exists('sonika_products')) {
    function sonika_products(): array {
        $c = sonika_catalog();
        $ps = $c['products'] ?? [];
        $local = function_exists('sonika_store_local') ? sonika_store_local() : [];
        if ($local) {
            $seen = [];
            foreach ($ps as $p) $seen[(string)($p['handle'] ?? '')] = true;
            foreach ($local as $p) { if (!isset($seen[$p['handle']])) $ps[] = $p; }
        }
        return $ps;
    }
}
if (!function_exists('sonika_find')) {
    function sonika_find(string $handle): ?array {
        foreach (sonika_products() as $p) { if (($p['handle'] ?? '') === $handle) return $p; }
        return null;
    }
}
if (!function_exists('sonika_variant')) {
    function sonika_variant(array $p): array { return $p['variants'][0] ?? []; }
}
if (!function_exists('sonika_price')) {
    function sonika_price(array $p): float { return (float)(sonika_variant($p)['price'] ?? 0); }
}
if (!function_exists('sonika_compare')) {
    function sonika_compare(array $p): float { return (float)(sonika_variant($p)['compare_at_price'] ?? 0); }
}
if (!function_exists('sonika_available')) {
    function sonika_available(array $p): bool { return !empty(sonika_variant($p)['available']); }
}
if (!function_exists('sonika_img')) {
    function sonika_img(array $p, int $w = 480): string {
        $src = $p['images'][0]['src'] ?? '';
        return $src ? $src . (str_contains($src, '?') ? '&' : '?') . 'width=' . $w : '';
    }
}
if (!function_exists('sonika_categories')) {
    function sonika_categories(): array {
        return [
            ['slug' => 'percusion',     'name' => 'Percusión',   'icon' => 'drum', 'tags' => 'Baterías · Redoblantes · Cajones', 'kw' => ['PERCUS','BATER','REDOBL','PLATILL','CONGA','BONGO','DJEMBE','CAJON','CAJA','TIMBAL','MARAC','CAMPAN','PANDER','TAMBOR','GUIRO','CLAVE','CASTAN','XILOF','METALOF','PARCHE','BAQUETA','SHAKER','GUACHE']],
            ['slug' => 'guitarras',     'name' => 'Cuerdas',     'icon' => 'guitar', 'tags' => 'Guitarras · Bajos · Ukeleles',    'kw' => ['GUITAR','BAJO','UKELELE','VIOLIN','VIOLA','VIOLONCHELO','CUERDA','PICK','CORREA','CAPODASTRO','ENCOR','PUENTE','CLAVIJERO']],
            ['slug' => 'acordeon',      'name' => 'Vientos',     'icon' => 'accordion', 'tags' => 'Acordeones · Saxos · Trompetas','kw' => ['ACORDE','ARMONICA','CLARINET','FLAUTA','GAITA','MELODICA','SAXOF','SAX','TROMB','TROMPET','CORNO','OBOE','FAGOT','OCARINA']],
            ['slug' => 'pianos',        'name' => 'Teclados',    'icon' => 'piano', 'tags' => 'Pianos · Sintetizadores',        'kw' => ['PIANO','TECLADO','SYNTH','SINTETIZ','CONTROLADOR','CASIO','CELVIANO','PRIVIA']],
            ['slug' => 'amplificadores', 'name' => 'Audio',       'icon' => 'speaker', 'tags' => 'Amplificadores · Mixers · Parlantes','kw' => ['AMPLIF','CABINA','MIXER','PARLANT','PLANTA','PROCESAD','INTERFAZ','INTERFACE','MONITOR','AUDIFONO','CONSOLA','POTENCIA','CROSSOVER','ECUALIZ','DI ']],
            ['slug' => 'microfono-miche', 'name' => 'Micrófonos', 'icon' => 'mic', 'tags' => 'Shure · Inalámbricos',          'kw' => ['MICROFON','MICRO','SHURE','INALAMBR']],
            ['slug' => 'cabeza-movil',  'name' => 'Iluminación',  'icon' => 'light', 'tags' => 'Cabezas móviles · Par LED',     'kw' => ['CABEZA','PAR LED','PARLED','LED','HUMO','TRUSS','TENSOR','DIMMER','STROBO','MOVIL','LUZ','LUCE','BLINDER','REFLECTOR']],
            ['slug' => 'cuerdas-guitarras', 'name' => 'Accesorios','icon' => 'box', 'tags' => 'Cuerdas · Correas · Forros',   'kw' => ['FORRO','FUNDA','SOPORTE','STAND','SILLA','ATRIL','RACK','CLAMP','CABLE','ACOPLE','LIMPIADOR','PROTECT','EMPUNADURA','AFINADOR','SORDINA','SLIDE','BANQUETA']],
        ];
    }
}
if (!function_exists('sonika_category')) {
    function sonika_category(string $slug): ?array {
        foreach (sonika_categories() as $c) { if ($c['slug'] === $slug) return $c; }
        return null;
    }
}
if (!function_exists('sonika_icon')) {
    /** Librería de iconos flat/outline (un solo color, hereda currentColor). Nombre → SVG; si no hay, devuelve el valor tal cual (emoji). */
    function sonika_icon(string $name): string {
        $name = trim($name);
        if (str_starts_with($name, '<svg')) {
            $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $name);
            $svg = preg_replace('/\bon\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string)$svg);
            $svg = preg_replace('/javascript:/i', '', (string)$svg);
            return (string)$svg;
        }
        $ic = [
            'drum'      => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M6 5.6v12.8M18 5.6v12.8"/>',
            'guitar'    => '<path d="M9 3l3 3M15 3l-3 3"/><path d="M12 6c-1.2 2-3 3.7-5 4.7A4.6 4.6 0 0 0 9.6 19h4.8A4.6 4.6 0 0 0 17 10.7C15 9.7 13.2 8 12 6z"/><circle cx="12" cy="14.5" r="1.7"/>',
            'accordion' => '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M9 6v12M15 6v12M3 12h6M15 12h6"/>',
            'piano'     => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 5v6M12 5v6M16 5v6M3 14h18"/>',
            'speaker'   => '<rect x="4" y="4" width="16" height="16" rx="2"/><circle cx="12" cy="14" r="3.2"/><path d="M8.5 8h7"/>',
            'mic'       => '<rect x="9" y="3" width="6" height="11" rx="3"/><path d="M6 11a6 6 0 0 0 12 0M12 17v4M9 21h6"/>',
            'light'     => '<path d="M12 3l7 5v6l-7 5-7-5V8z"/><path d="M12 3v18M5 8l14 6"/>',
            'box'       => '<rect x="4" y="7" width="16" height="12" rx="2"/><path d="M8 7V5a4 4 0 0 1 8 0v2M4 12h16"/>',
            'truck'     => '<path d="M3 7h11v8H3zM14 10h4l3 3v2h-7z"/><circle cx="7" cy="18" r="1.6"/><circle cx="17" cy="18" r="1.6"/>',
            'shield'    => '<path d="M12 3l7 3v6c0 4-3 7-7 9-4-2-7-5-7-9V6z"/><path d="M9 12l2 2 4-4"/>',
            'headset'   => '<path d="M4 13a8 8 0 0 1 16 0"/><rect x="3" y="13" width="4" height="6" rx="1"/><rect x="17" y="13" width="4" height="6" rx="1"/>',
            'card'      => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18M7 15h4"/>',
            'lock'      => '<rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V8a4 4 0 0 1 8 0v2"/>',
            'tag'       => '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
            'star'      => '<path d="M12 3l2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8L7.8 19l1-5.9L4.5 9.2l5.9-.8z"/>',
            'check'     => '<path d="M4 12l5 5L20 6"/>',
            'chevron'   => '<path d="M9 6l6 6-6 6"/>',
            'info'      => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
        ];
        if (isset($ic[$name])) return '<svg class="si" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $ic[$name] . '</svg>';
        return htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('sonika_category_image')) {
    /** Imagen representativa de la categoría: config (slug → handle/URL) o primer producto con foto. */
    function sonika_category_image(string $slug, int $w = 700): string {
        $imgs = sonika_category_images($slug, $w);
        return $imgs[0] ?? '';
    }
}
if (!function_exists('sonika_image_audit')) {
    /** Auditor de calidad de imagen (0–100): resolución + fondo blanco en bordes. Cacheado en disco. */
    function sonika_image_audit(string $url): int {
        $url = trim($url);
        if ($url === '') return 0;
        static $cache = null;
        $f = dirname(__DIR__, 3) . '/public/assets/uploads/_catalog/img_audit.json';
        if ($cache === null) {
            $j = is_file($f) ? json_decode((string)@file_get_contents($f), true) : null;
            $cache = is_array($j) ? $j : [];
        }
        $key = md5($url);
        if (isset($cache[$key])) return (int)$cache[$key];
        $score = 0;
        $bin = @file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: Mozilla/5.0\r\n"]]));
        if ($bin !== false) {
            $tmp = tempnam(sys_get_temp_dir(), 'img');
            if ($tmp !== false) {
                @file_put_contents($tmp, $bin);
                $i = @getimagesize($tmp);
                if ($i) {
                    $w = (int)$i[0];
                    $score += $w >= 1200 ? 30 : ($w >= 800 ? 25 : ($w >= 500 ? 15 : 5));
                    if (function_exists('imagecreatefromstring')) {
                        $im = @imagecreatefromstring($bin);
                        if ($im) {
                            $pw = imagesx($im); $ph = imagesy($im);
                            $samples = 0; $white = 0;
                            $step = max(1, (int)($pw / 24));
                            for ($x = 0; $x < $pw; $x += $step) {
                                foreach ([0, $ph - 1] as $y) {
                                    $c = imagecolorat($im, $x, $y);
                                    $r = ($c >> 16) & 255; $g = ($c >> 8) & 255; $b = $c & 255;
                                    $samples++;
                                    if ($r > 232 && $g > 232 && $b > 232) $white++;
                                }
                            }
                            $ratio = $samples ? $white / $samples : 0;
                            $score += (int)round($ratio * 55) + 10;
                            imagedestroy($im);
                        }
                    }
                }
                @unlink($tmp);
            }
        }
        $score = max(0, min(100, $score));
        $cache[$key] = $score;
        $dir = dirname($f);
        if (!is_dir($dir)) @mkdir($dir, 0777, true);
        @file_put_contents($f, json_encode($cache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $score;
    }
}
if (!function_exists('sonika_category_images')) {
    /** Lista de imágenes de la categoría: config (varias por slug) o la mejor del catálogo según el auditor. */
    function sonika_category_images(string $slug, int $w = 700): array {
        $cfg = [];
        $rows = function_exists('wwi_tc') ? (array)wwi_tc('sonika', 'home_cat_images', []) : [];
        foreach ($rows as $r) {
            if (!is_array($r) || strtolower(trim((string)($r['slug'] ?? ''))) !== strtolower($slug)) continue;
            $v = trim((string)($r['image'] ?? ''));
            if ($v === '') continue;
            if (str_starts_with($v, 'http') || str_starts_with($v, '/')) { $cfg[] = $v; continue; }
            $p = function_exists('sonika_find') ? sonika_find($v) : null;
            if ($p) { $u = sonika_img($p, $w); if ($u !== '') $cfg[] = $u; }
        }
        $cat = [];
        foreach (sonika_category_products($slug) as $p) { $u = sonika_img($p, $w); if ($u === '') continue; $cat[] = $u; if (count($cat) >= 6) break; }
        if (!$cfg && $cat) {
            $min = (int)(function_exists('wwi_tc') ? wwi_tc('sonika', 'home_cat_quality', 55) : 55);
            $best = ''; $bs = -1;
            foreach ($cat as $u) { $s = sonika_image_audit($u); if ($s > $bs) { $bs = $s; $best = $u; } }
            if ($best !== '' && $bs >= $min) $cfg = [$best];
        }
        return array_values(array_unique(array_merge($cfg, $cat)));
    }
}
if (!function_exists('sonika_category_badges')) {
    /** Badges de la categoría: NUEVO (producto reciente) y OFERTA (descuento). */
    function sonika_category_badges(string $slug): array {
        $new = false; $offer = false; $now = time();
        foreach (sonika_category_products($slug) as $p) {
            $pa = (string)($p['published_at'] ?? '');
            $ts = $pa !== '' ? (int)strtotime($pa) : 0;
            if ($ts && ($now - $ts) <= 90 * 86400) $new = true;
            $cmp = sonika_compare($p); $pr = sonika_price($p);
            if ($cmp > $pr && $pr > 0) $offer = true;
            if ($new && $offer) break;
        }
        return ['new' => $new, 'offer' => $offer];
    }
}
if (!function_exists('sonika_subcategories')) {
    /** Subcategorías AUTOMÁTICAS: se derivan del catálogo (product_type), opcionalmente dentro de una categoría. */
    function sonika_subcategories(?string $catSlug = null): array {
        $cat = ($catSlug !== null && $catSlug !== '') ? sonika_category($catSlug) : null;
        $out = [];
        foreach (sonika_products() as $p) {
            if ($cat && !sonika_match($p, $cat['kw'])) continue;
            $t = trim((string)($p['product_type'] ?? ''));
            if ($t !== '') $out[$t] = ($out[$t] ?? 0) + 1;
        }
        arsort($out);
        return $out;
    }
}
if (!function_exists('sonika_fold')) {
    function sonika_fold(string $s): string {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $s = strtr($s, ['á' => 'a', 'à' => 'a', 'ä' => 'a', 'â' => 'a', 'ã' => 'a', 'é' => 'e', 'è' => 'e', 'ë' => 'e', 'ê' => 'e', 'í' => 'i', 'ì' => 'i', 'ï' => 'i', 'î' => 'i', 'ó' => 'o', 'ò' => 'o', 'ö' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ù' => 'u', 'ü' => 'u', 'û' => 'u', 'ñ' => 'n', 'ç' => 'c']);
        return trim(preg_replace('/\s+/u', ' ', $s) ?: '');
    }
}
if (!function_exists('sonika_product_tags')) {
    function sonika_product_tags(array $p): array {
        $out = [];
        foreach ((array)($p['tags'] ?? []) as $tag) {
            if (!is_string($tag)) continue;
            if (preg_match('/^(\d+)\)\s*[^_]+_(.+)$/u', trim($tag), $m)) $out[(int)$m[1]] = trim($m[2]);
        }
        return $out;
    }
}
if (!function_exists('sonika_match')) {
    function sonika_match(array $p, array $kw): bool {
        $hay = strtoupper(($p['title'] ?? '') . ' ' . ($p['product_type'] ?? '') . ' ' . ($p['vendor'] ?? ''));
        foreach ($kw as $k) { if (str_contains($hay, $k)) return true; }
        return false;
    }
}
if (!function_exists('sonika_legacy_slug')) {
    function sonika_legacy_slug(string $tagCat): string {
        if ($tagCat === '' || !class_exists('\\App\\Services\\CatalogReconciliationService')) return '';
        return \App\Services\CatalogReconciliationService::legacySlug($tagCat);
    }
}
if (!function_exists('sonika_effective_category')) {
    /** Categoría efectiva: tag del catálogo o, si falta, el override aprobado (auto-clasificador IA). */
    function sonika_effective_category(array $p): string {
        $tags = sonika_product_tags($p);
        $tag = (string)($tags[1] ?? '');
        if ($tag !== '') return $tag;
        $h = (string)($p['handle'] ?? '');
        if ($h !== '' && class_exists('\\App\\Services\\CatalogClassifierService')) {
            $ov = (new \App\Services\CatalogClassifierService())->overrides();
            if (isset($ov[$h])) return (string)$ov[$h];
        }
        return '';
    }
}
if (!function_exists('sonika_products_effective')) {
    /** Productos con el override inyectado como tag de categoría (para el Mega Menú). */
    function sonika_products_effective(): array {
        $ps = sonika_products();
        if (!class_exists('\\App\\Services\\CatalogClassifierService')) return $ps;
        static $ov = null;
        if ($ov === null) $ov = (new \App\Services\CatalogClassifierService())->overrides();
        if (!$ov) return $ps;
        foreach ($ps as &$p) {
            $h = (string)($p['handle'] ?? '');
            if ($h === '' || !isset($ov[$h])) continue;
            $has = false;
            foreach ((array)($p['tags'] ?? []) as $t) { if (preg_match('/^1\)\s*[^_]+_/u', trim((string)$t))) { $has = true; break; } }
            if (!$has) $p['tags'] = array_merge((array)($p['tags'] ?? []), ['1) Categoría_' . $ov[$h]]);
        }
        unset($p);
        return $ps;
    }
}
if (!function_exists('sonika_guitar_subs')) {
    /** Subcategorías CURADAS de "Cuerdas" (híbrida: lista curada + tags reales del catálogo). */
    function sonika_guitar_subs(): array {
        return [
            ['slug' => 'electricas',       'name' => 'Guitarras Eléctricas', 'tipos' => ['electrica', 'guitarra electrica'], 'labels' => 'Eléctrica · Guitarra Eléctrica'],
            ['slug' => 'electroacusticas', 'name' => 'Electroacústicas',     'tipos' => ['electroacustica'],                  'labels' => 'Electroacústica'],
            ['slug' => 'acusticas',        'name' => 'Acústicas',            'tipos' => ['acustica', 'guitarra acustica'],    'labels' => 'Acústica'],
            ['slug' => 'clasicas',         'name' => 'Clásicas',             'tipos' => ['guitarra clasica', 'clasica'],      'labels' => 'Guitarra Clásica'],
            ['slug' => 'bajos',            'name' => 'Bajos',                'tipos' => ['bajo', 'bajo electrico'],           'labels' => 'Bajo · Bajo Eléctrico'],
        ];
    }
}
if (!function_exists('sonika_guitar_sub_of')) {
    /** Subcategoría curada de un producto (por tags de Tipo + respaldo por título), o '' si no aplica. */
    function sonika_guitar_sub_of(array $p): string {
        $types = [];
        foreach ((array)($p['tags'] ?? []) as $tg) {
            if (!is_string($tg)) continue;
            if (preg_match('/^2\)\s*[^_]+_(.+)$/u', trim($tg), $m)) $types[] = sonika_fold(trim($m[1]));
        }
        if ($types) {
            $bySlug = [];
            foreach (sonika_guitar_subs() as $sub) $bySlug[$sub['slug']] = $sub;
            foreach (['clasicas', 'electricas', 'electroacusticas', 'acusticas', 'bajos'] as $pslug) {
                if (empty($bySlug[$pslug])) continue;
                foreach ($bySlug[$pslug]['tipos'] as $t) { if (in_array($t, $types, true)) return $pslug; }
            }
        }
        $hay = strtoupper((string)($p['title'] ?? '') . ' ' . (string)($p['product_type'] ?? ''));
        if (str_contains($hay, 'ELECTRIC') && str_contains($hay, 'GUITARRA')) return 'electricas';
        if (str_contains($hay, 'ELECTROACUST')) return 'electroacusticas';
        if (str_contains($hay, 'CLASIC')) return 'clasicas';
        if (str_contains($hay, 'ACUSTIC')) return 'acusticas';
        if (str_contains($hay, 'BAJO') || str_contains($hay, 'BASS')) return 'bajos';
        return '';
    }
}
if (!function_exists('sonika_guitar_subs_used')) {
    /** Subcategorías curadas con su conteo real (indexado padre→hija). */
    function sonika_guitar_subs_used(?array $products = null): array {
        $products = $products ?? sonika_category_products('guitarras');
        $counts = [];
        $img = [];
        foreach ($products as $p) {
            $s = sonika_guitar_sub_of($p);
            if ($s !== '') {
                $counts[$s] = ($counts[$s] ?? 0) + 1;
                if (empty($img[$s])) { $u = function_exists('sonika_img') ? sonika_img($p, 600) : ''; if ($u !== '') $img[$s] = $u; }
            }
        }
        $out = [];
        foreach (sonika_guitar_subs() as $sub) { $sub['count'] = $counts[$sub['slug']] ?? 0; $sub['thumb'] = $img[$sub['slug']] ?? ''; $out[] = $sub; }
        return $out;
    }
}
if (!function_exists('sonika_category_products')) {
    function sonika_category_products(string $slug, string $sub = ''): array {
        $cat = sonika_category($slug);
        if (!$cat) return [];
        $out = [];
        foreach (sonika_products() as $p) {
            $tagCat = sonika_effective_category($p);
            if ($tagCat !== '') {
                if (sonika_legacy_slug($tagCat) === $slug) $out[] = $p;
            } elseif (sonika_match($p, $cat['kw'])) {
                $out[] = $p; // respaldo: sin tag ni override
            }
        }
        if ($sub !== '' && $slug === 'guitarras') {
            $valid = array_column(sonika_guitar_subs(), 'slug');
            if (in_array($sub, $valid, true)) {
                $out = array_values(array_filter($out, fn($p) => sonika_guitar_sub_of($p) === $sub));
            }
        }
        return $out;
    }
}
if (!function_exists('sonika_search')) {
    function sonika_search(string $q): array {
        $q = trim(mb_strtolower($q));
        if ($q === '') return [];
        $terms = array_values(array_filter(preg_split('/\s+/', $q) ?: [], fn($t) => $t !== ''));
        $syn = [
            'bateria' => ['bateria', 'batería', 'percusion', 'percusión'],
            'batería' => ['bateria', 'batería'],
            'guitarra' => ['guitarra', 'guitarras'],
            'bajo' => ['bajo', 'bajos'],
            'parlante' => ['parlante', 'altavoz', 'bafle'],
            'bafle' => ['bafle', 'parlante', 'altavoz'],
            'consola' => ['consola', 'mixer', 'mezcladora'],
            'mixer' => ['mixer', 'consola', 'mezcladora'],
            'audifonos' => ['audifonos', 'audífonos', 'auriculares'],
            'microfono' => ['microfono', 'micrófono'],
            'acordeon' => ['acordeon', 'acordeón'],
            'teclado' => ['teclado', 'piano', 'sintetizador'],
            'cuerda' => ['cuerda', 'cuerdas'],
        ];
        $products = sonika_products();
        $out = [];
        foreach ($products as $p) {
            $hay = mb_strtolower(($p['title'] ?? '') . ' ' . ($p['vendor'] ?? '') . ' ' . ($p['product_type'] ?? ''));
            $ok = true;
            foreach ($terms as $t) {
                if ($t === '') continue;
                $hit = false;
                foreach (($syn[$t] ?? [$t]) as $c) { if (str_contains($hay, $c)) { $hit = true; break; } }
                if (!$hit) { $ok = false; break; }
            }
            if ($ok) $out[] = $p;
        }
        if (empty($out) && count($terms) <= 3) {
            foreach ($products as $p) {
                $hay = mb_strtolower(($p['title'] ?? '') . ' ' . ($p['vendor'] ?? ''));
                $tokens = preg_split('/[^a-z0-9áéíóúñ]+/u', $hay) ?: [];
                $matched = false;
                foreach ($terms as $t) {
                    if (mb_strlen($t) < 4) continue;
                    foreach ($tokens as $tok) {
                        if ($tok === '') continue;
                        if (levenshtein($t, $tok) <= 1) { $matched = true; break 2; }
                    }
                }
                if ($matched) $out[] = $p;
            }
        }
        usort($out, function ($a, $b) use ($terms) {
            $score = function (array $p) use ($terms): int {
                $t = mb_strtolower((string)($p['title'] ?? ''));
                $ty = mb_strtolower((string)($p['product_type'] ?? ''));
                $acc = ['accesorio', 'amplificador', 'sonido', 'forro', 'funda', 'correa', 'cable', 'adaptador', 'stand', 'soporte', 'pedal', 'estuche', 'atril', 'clavijero', 'capodastro', 'baqueta', 'herraje', 'repuesto'];
                $isAcc = false;
                foreach ($acc as $x) { if (str_contains($ty, $x) || str_contains($t, $x)) { $isAcc = true; break; } }
                $s = 0;
                foreach ($terms as $w) {
                    if ($w === '') continue;
                    if (str_contains($ty, $w)) $s += 5;
                    if (str_starts_with($t, $w)) $s += 4;
                    elseif (str_contains($t, $w)) $s += 1;
                }
                if ($isAcc) $s -= 4;
                return $s;
            };
            return $score($b) <=> $score($a);
        });
        return $out;
    }
}
if (!function_exists('sonika_filter_products')) {
    /** Filtra + ordena el catálogo según parámetros GET (usado por /buscar y /buscar/live). */
    function sonika_filter_products(array $gp): array {
        $q = trim((string)($gp['q'] ?? ''));
        $min = (float)($gp['min'] ?? 0); $max = (float)($gp['max'] ?? 0);
        $marca = trim((string)($gp['marca'] ?? '')); $tipo = trim((string)($gp['tipo'] ?? '')); $sub = trim((string)($gp['sub'] ?? ''));
        $cat = trim((string)($gp['cat'] ?? '')); $desc = (float)($gp['desc'] ?? 0);
        $orden = trim((string)($gp['orden'] ?? ''));
        $oferta = !empty($gp['oferta']); $nuevo = !empty($gp['nuevo']); $disp = !empty($gp['disp']);
        $tax = trim((string)($gp['tax'] ?? '')); $want = [];
        if ($tax !== '') { foreach (explode(',', $tax) as $__part) { if (preg_match('/^\s*(\d+):(.+)$/u', $__part, $__m)) $want[(int)$__m[1]] = trim($__m[2]); } }
        $newDays = 90;
        $has = ($q !== '' || $min > 0 || $max > 0 || $marca !== '' || $tipo !== '' || $sub !== '' || $cat !== '' || $desc > 0 || $oferta || $nuevo || $disp || $tax !== '');
        if (!$has) return [];
        if ($cat !== '' && sonika_category($cat)) $all = sonika_category_products($cat);
        elseif ($q !== '') $all = sonika_search($q);
        else $all = sonika_products();
        $__curSubs = array_column(sonika_guitar_subs(), 'slug');
        $all = array_values(array_filter($all, function ($p) use ($min, $max, $marca, $tipo, $sub, $desc, $oferta, $nuevo, $disp, $newDays, $want, $__curSubs) {
            $price = sonika_price($p); $cmp = sonika_compare($p);
            if ($min > 0 && $price < $min) return false;
            if ($max > 0 && $price > $max) return false;
            if ($marca !== '' && strcasecmp((string)($p['vendor'] ?? ''), $marca) !== 0) return false;
            if ($tipo !== '' && strcasecmp((string)($p['product_type'] ?? ''), $tipo) !== 0) return false;
            if ($sub !== '') {
                if (in_array($sub, $__curSubs, true)) { if (sonika_guitar_sub_of($p) !== $sub) return false; }
                elseif (strcasecmp((string)($p['product_type'] ?? ''), $sub) !== 0) return false;
            }
            if ($oferta && !($cmp > $price && $price > 0)) return false;
            if ($desc > 0) { $d = ($cmp > 0) ? (($cmp - $price) / $cmp * 100) : 0; if ($d < $desc) return false; }
            if ($nuevo) { $pa = (string)($p['published_at'] ?? ''); if ($pa !== '' && (time() - strtotime($pa)) > $newDays * 86400) return false; }
            if ($disp && !sonika_available($p)) return false;
            if ($want) { $pt = sonika_product_tags($p); foreach ($want as $lv => $vv) { if (sonika_fold((string)($pt[$lv] ?? '')) !== sonika_fold((string)$vv)) return false; } }
            return true;
        }));
        if ($orden === 'precio_asc') usort($all, fn($a, $b) => sonika_price($a) <=> sonika_price($b));
        elseif ($orden === 'precio_desc') usort($all, fn($a, $b) => sonika_price($b) <=> sonika_price($a));
        elseif ($orden === 'descuento') { $dsc = fn($p) => sonika_compare($p) > 0 ? ((sonika_compare($p) - sonika_price($p)) / sonika_compare($p) * 100) : 0; usort($all, fn($a, $b) => $dsc($b) <=> $dsc($a)); }
        elseif ($orden === 'nuevo' && isset($all[0]['published_at'])) usort($all, fn($a, $b) => strcmp((string)($b['published_at'] ?? ''), (string)($a['published_at'] ?? '')));
        return $all;
    }
}
if (!function_exists('sonika_related')) {
    function sonika_related(array $p, int $n = 4): array {
        $type = strtoupper($p['product_type'] ?? '');
        $out = [];
        foreach (sonika_products() as $q) {
            if ($q['handle'] === ($p['handle'] ?? '')) continue;
            if ($type !== '' && strtoupper($q['product_type'] ?? '') === $type) { $out[] = $q; if (count($out) >= $n) break; }
        }
        if (count($out) < $n) {
            $vendor = strtoupper($p['vendor'] ?? '');
            foreach (sonika_products() as $q) {
                if ($q['handle'] === ($p['handle'] ?? '')) continue;
                if (strtoupper($q['vendor'] ?? '') === $vendor && !in_array($q, $out, true)) { $out[] = $q; if (count($out) >= $n) break; }
            }
        }
        return array_slice($out, 0, $n);
    }
}

if (!function_exists('sonika_logo_tag')) {
    function sonika_logo_tag(): string {
        $img = wwi_logo_img(150);
        $root = defined('ROOT_DIR') ? ROOT_DIR : dirname(__DIR__, 3);
        if ($img !== '' && is_file($root . '/public/assets/themes/sonika/logo-s.png') && is_file($root . '/public/assets/themes/sonika/logo-nika.png')) {
            $b = wwi_brand();
            $h = max(12, min(150, (int)($b['logo_height'] ?: 100)));
            $ow = round($h * 155 / 288, 2);
            $specs = [[0, 35.8, 15.3, 20.1], [19.7, 27.8, 15.3, 35.8], [40.1, 18.4, 17.8, 53.8], [63.1, 27.8, 15.3, 35.8], [83.4, 35.8, 15.3, 20.5]];
            $bars = '';
            foreach ($specs as $sp) $bars .= '<i style="left:' . $sp[0] . '%;top:' . $sp[1] . '%;width:' . $sp[2] . '%;height:' . $sp[3] . '%"></i>';
            return '<style>.sonika-logo{display:inline-flex;align-items:center;height:' . $h . 'px}.sonika-logo img{height:100%;width:auto;display:block}.sonika-logo .sl-o{position:relative;height:100%;width:' . $ow . 'px}.sonika-logo .sl-o i{position:absolute;background:var(--brand,#F20D16);border-radius:2px;transform-origin:center;animation:logoEq 2.6s ease-in-out infinite}.sonika-logo .sl-o i:nth-child(2){animation-delay:.25s}.sonika-logo .sl-o i:nth-child(3){animation-delay:.5s}.sonika-logo .sl-o i:nth-child(4){animation-delay:.25s}.sonika-logo .sl-o i:nth-child(5){animation-delay:.7s}.sonika-logo:hover .sl-o i{animation-duration:1.1s}@keyframes logoEq{0%,100%{transform:scaleY(1)}50%{transform:scaleY(1.14)}}@media(prefers-reduced-motion:reduce){.sonika-logo .sl-o i{animation:none}}</style>'
                . '<span class="sonika-logo" role="img" aria-label="SONIKA">'
                . '<img src="/assets/themes/sonika/logo-s.png" alt="">'
                . '<span class="sl-o" aria-hidden="true">' . $bars . '</span>'
                . '<img src="/assets/themes/sonika/logo-nika.png" alt="">'
                . '</span>';
        }
        if ($img !== '') return $img;
        if (is_file($root . '/public/assets/themes/sonika/logo-transparent.png')) {
            return '<img class="logo-img" src="/assets/themes/sonika/logo-transparent.png" alt="SONIKA" />';
        }
        return '<span class="mark">S</span>SONIKA';
    }
}
if (!function_exists('sonika_site_head')) {
    function sonika_site_head(string $title, string $desc = ''): void {
        if (!empty($_GET['ref']) && preg_match('/^[a-zA-Z0-9_-]{4,64}$/', (string)$_GET['ref'])) {
            $__rc = (string)$_GET['ref'];
            @setcookie('sonika_aff', $__rc, ['expires' => time() + 30 * 86400, 'path' => '/', 'samesite' => 'Lax']);
            $_COOKIE['sonika_aff'] = $__rc;
        }
        $pageMeta = [
            'title' => $title,
            'meta_title' => $title,
            'meta_description' => $desc ?: 'SONIKA — Instrumentos musicales, audio e iluminación. Envíos a toda Colombia.',
        ];
        ?><!DOCTYPE html>
<html lang="<?= sonika_esc(\App\Core\Lang::current()) ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?= SeoService::metaTags($pageMeta) ?>
    <?php if (($__fav = wwi_favicon_links()) !== '') { echo $__fav; } else { echo '<link rel="icon" type="image/png" href="/assets/themes/sonika/isotipo.png">'; } ?>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="<?= sonika_fonts_url() ?>" rel="stylesheet" />
    <style><?= sonika_css() ?></style>
    <style>:root{--font-body:<?= sonika_font_stack('body') ?>;--font-display:<?= sonika_font_stack('display') ?>}</style>
    <?= sonika_nav_style_head() ?>
    <?= wwi_cap_head() ?>
    <style><?= wwi_cap_css() ?></style>
</head>
<body>
<?= wwi_cap_notice() ?>
<div class="topbar"<?php
      $__bnSvc = new \App\Services\BannerService();
      $__bnCfg = $__bnSvc->headerConfig();
      if (!$__bnCfg || empty($__bnCfg['items'])) $__bnCfg = $__bnSvc->defaults();
      $__ok = fn($c, $d) => preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', (string)$c) ? (string)$c : $d;
      $__bg = $__ok($__bnCfg['bg_color'] ?? '', '#111111');
      $__bg2 = $__ok($__bnCfg['bg_color2'] ?? '', '');
      $__fg = $__ok($__bnCfg['text_color'] ?? '', '#ffffff');
      $__img = (string)($__bnCfg['bg_image'] ?? '');
      $__ov = max(0, min(100, (int)($__bnCfg['overlay'] ?? 55))) / 100;
      $__st = 'color:' . $__fg . ';';
      if ($__img !== '' && preg_match('~^(https?://|/)~i', $__img)) {
          $__st .= 'background-image:linear-gradient(rgba(0,0,0,' . $__ov . '),rgba(0,0,0,' . $__ov . ')),url(\'' . htmlspecialchars($__img, ENT_QUOTES) . '\');background-size:cover;background-position:center;';
      } elseif ($__bg2 !== '') {
          $__st .= 'background:linear-gradient(135deg,' . $__bg . ' 0%,' . $__bg2 . ' 100%);';
      } else {
          $__st .= 'background:' . $__bg . ';';
      }
      echo ' style="' . $__st . '"';
      ?>><div class="topbar__inner"><span class="topbar__benefit"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="1" y="6" width="13" height="10" rx="1"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="17.5" cy="18.5" r="2"/></svg><span>BENEFICIO SONIKA</span></span><span class="topbar__msg"><?php
      $__bnCfg['bare'] = true;
      $__bnCfg['dismissible'] = false;
      $__bnCfg['mode'] = 'bar';
      echo \App\Widgets\WidgetRegistry::render('banner', $__bnCfg);
      ?></span><?php
$__act = ''; $__actL = '';
foreach ((array)($__bnCfg['items'] ?? []) as $__it) {
    if (!empty($__it['link'])) { $__act = (string)$__it['link']; $__actL = trim((string)($__it['link_label'] ?? '')) ?: 'Ver más'; break; }
}
if ($__act !== '' && preg_match('~^(https?://|/)~i', $__act)):
?><a class="topbar__act" href="<?= htmlspecialchars($__act, ENT_QUOTES) ?>"><?= sonika_esc($__actL) ?> &rarr;</a><?php endif; ?></div><?php
$__cs = new \App\Services\CurrencyService();
$__cur = $__cs->currencies();
if (count($__cur) > 1 && (string)wwi_setting('store_currency_switcher', '') === '1'):
    $__act = $__cs->active();
?><select aria-label="Moneda" onchange="document.cookie='wwi_currency='+this.value+';path=/;max-age=31536000';location.reload()" style="margin-left:12px;background:transparent;color:inherit;border:1px solid rgba(255,255,255,.4);border-radius:6px;padding:2px 6px;font:inherit"><?php foreach ($__cur as $__c): ?><option value="<?= sonika_esc($__c['code']) ?>"<?= $__c['code'] === $__act ? ' selected' : '' ?>><?= sonika_esc($__c['label']) ?></option><?php endforeach; ?></select><?php endif; ?></div>
<header class="site">
<?php
$__navAbove = ((string)(sonika_nav_style()['position'] ?? 'below') === 'above');
ob_start();
?>
  <nav class="mainnav" aria-label="Menú principal">
    <div class="wrap">
      <ul>
        <?php foreach (wwi_nav('primary') as $it): $hot = (mb_stripos((string)$it['label'], 'oferta') !== false); ?>
        <li<?= !empty($it['children']) ? ' class="has-sub"' : '' ?>>
          <a href="<?= sonika_esc($it['url']) ?>"<?= $hot ? ' class="hot"' : '' ?>><?= sonika_esc($it['label']) ?><?= !empty($it['children']) ? ' <span aria-hidden="true">&#9662;</span>' : '' ?></a>
          <?php if (!empty($it['children'])): ?>
          <div class="submenu">
            <?php foreach ($it['children'] as $ch): ?>
              <a href="<?= sonika_esc($ch['url']) ?>"><?= sonika_esc($ch['label']) ?></a>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
        </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </nav>
<?php $__mainnav = ob_get_clean(); ?>
  <?php if ($__navAbove) echo $__mainnav; ?>
  <div class="wrap">
    <div class="nav">
      <?= \App\Widgets\WidgetRegistry::render('mega-menu', ['categories' => sonika_categories(), 'guitar_subs' => sonika_guitar_subs_used()]) ?>
      <a class="logo" href="/"><?= sonika_logo_tag() ?></a>
      <?php
      $__advBrands = []; $__advTypes = []; $__advMax = 0.0;
      foreach (sonika_products() as $__p) {
          $__v = trim((string)($__p['vendor'] ?? '')); if ($__v !== '') $__advBrands[$__v] = ($__advBrands[$__v] ?? 0) + 1;
          $__t = trim((string)($__p['product_type'] ?? '')); if ($__t !== '') $__advTypes[$__t] = ($__advTypes[$__t] ?? 0) + 1;
          $__pr = sonika_price($__p); if ($__pr > $__advMax) $__advMax = $__pr;
      }
      arsort($__advBrands); arsort($__advTypes);
      $__advBrands = array_slice($__advBrands, 0, 120, true);
      $__advTypes = array_slice($__advTypes, 0, 120, true);
      $__advCats = [];
      foreach (sonika_categories() as $__cc) { $__n = count(sonika_category_products($__cc['slug'])); if ($__n > 0) $__advCats[] = [$__cc['slug'], $__cc['name'] . ' (' . $__n . ')']; }
      $__advSubs = [];
      foreach (sonika_guitar_subs_used() as $__s) { if ((int)$__s['count'] > 0) $__advSubs[$__s['slug']] = $__s['name'] . ' (' . (int)$__s['count'] . ')'; }
      $__guitarTypes = ['Eléctrica', 'Electrica', 'Guitarra Eléctrica', 'Acústica', 'Acustica', 'Electroacústica', 'Electroacustica', 'Guitarra Clásica', 'Clasica', 'Bajo', 'Bajo Eléctrico'];
      foreach (sonika_subcategories() as $__t => $__cn) { if (in_array($__t, $__guitarTypes, true)) continue; $__advSubs[$__t] = $__t . ' (' . (int)$__cn . ')'; }
      echo \App\Widgets\WidgetRegistry::render('advance-search-product', [
          'brands' => $__advBrands,
          'types' => $__advTypes,
          'subcats' => $__advSubs,
          'show_subcategory' => true,
          'show_type' => true,
          'show_brand' => true,
          'show_category' => true,
          'categories' => $__advCats,
          'max_price' => $__advMax,
          'new_days' => 90,
          'placeholder' => '¿Qué instrumento estás buscando?',
      ]);
      ?>
      <div class="nav-actions">
        <?= \App\Widgets\WidgetRegistry::render('account', ['label' => \App\Core\Lang::get('my_account', 'Mi cuenta'), 'url' => '/cuenta']) ?>
        <?= \App\Widgets\WidgetRegistry::render('cart', ['storage_key' => 'sonika_cart', 'cart_url' => '/carrito', 'checkout_url' => '/checkout']) ?>
        <?= \App\Widgets\WidgetRegistry::render('language-switcher') ?>
      </div>
    </div>
  </div>
  <?php if (!$__navAbove) echo $__mainnav; ?>
</header>
<main>
<?php
    }
}
if (!function_exists('sonika_breadcrumb')) {
    function sonika_breadcrumb(array $items): void {
        echo '<nav class="crumb wrap"><a href="/">Inicio</a>';
        foreach ($items as $label => $href) {
            echo ' <span>/</span> ';
            echo $href ? '<a href="' . sonika_esc($href) . '">' . sonika_esc($label) . '</a>' : '<span>' . sonika_esc($label) . '</span>';
        }
        echo '</nav>';
    }
}
if (!function_exists('sonika_product_card')) {
    function sonika_product_card(array $p): void {
        $img = sonika_img($p);
        $avail = sonika_available($p);
        $price = sonika_price($p);
        $cmp = sonika_compare($p);
        ?>
        <div class="card">
          <a class="thumb" href="/producto/<?= sonika_esc($p['handle']) ?>">
            <?php if ($img): ?><img src="<?= sonika_esc($img) ?>" alt="<?= sonika_esc($p['title']) ?>" loading="lazy" /><?php endif; ?>
            <span class="badge <?= $avail ? '' : 'out' ?>"><?= $avail ? 'Disponible' : 'Agotado' ?></span>
            <?php if ($cmp > $price && $price > 0): ?><span class="badge off">Oferta</span><?php endif; ?>
          </a>
          <div class="card-body">
            <span class="vendor"><?= sonika_esc($p['vendor'] ?? '') ?></span>
            <a class="ptitle" href="/producto/<?= sonika_esc($p['handle']) ?>"><?= sonika_esc($p['title']) ?></a>
            <div class="pricebox">
              <span class="price"><?= sonika_cop($price) ?></span>
              <?php if ($cmp > $price && $price > 0): ?><span class="cmp"><?= sonika_cop($cmp) ?></span><?php endif; ?>
            </div>
            <button class="add" type="button" data-add="<?= sonika_esc($p['handle']) ?>" data-title="<?= sonika_esc($p['title']) ?>" data-price="<?= (float)$price ?>" data-img="<?= sonika_esc($img) ?>"><?= sonika_esc((string)wwi_tc('sonika', 'card_add_label', 'Agregar')) ?></button>
          </div>
        </div>
        <?php
    }
}
if (!function_exists('sonika_site_footer')) {
    function sonika_site_footer(): void {
        ?>
</main>
<?php echo \App\Widgets\WidgetRegistry::render('sonika-footer', []); ?>
<?= CookieConsentService::render() ?>
<script><?= sonika_js() ?></script>
</body>
</html>
<?php
    }
}
if (!function_exists('sonika_css')) {
    function sonika_css(): string {
        return <<<'CSS'
        *{box-sizing:border-box;margin:0;padding:0}
        :root{--ink:#111111;--muted:#777777;--line:#E6E6E6;--soft:#F5F5F5;--brand:#F20D16;--brand2:#C70A12;--accent:#F20D16;--ok:#0E9F6E;--radius:12px;--font-body:'Sora',system-ui,sans-serif;--font-display:'Sora',system-ui,sans-serif}
        body{font-family:var(--font-body);color:var(--ink);background:#fff;-webkit-font-smoothing:antialiased}
        a{color:inherit;text-decoration:none}
        .wrap{max-width:1180px;margin:0 auto;padding:0 22px}
        .topbar{position:relative;isolation:isolate;width:100%;min-width:0;box-sizing:border-box;background:#ed101b!important;color:#fff;overflow:hidden}
.topbar__inner{display:grid;grid-template-columns:minmax(145px,.8fr) minmax(0,2.4fr) minmax(160px,.8fr);align-items:center;gap:20px;min-height:54px;padding:10px clamp(20px,4.8vw,72px);line-height:1.35;font-family:inherit}
.topbar__benefit{min-width:0;justify-self:start;display:inline-flex;align-items:center;gap:8px;color:#fff;font-size:11px;font-weight:800;letter-spacing:.075em;line-height:1.3;text-transform:uppercase}
.topbar__benefit svg{flex:0 0 auto;width:15px;height:15px;color:#fff}
.topbar__msg{min-width:0;justify-self:center;display:flex;align-items:center;justify-content:center;gap:10px;color:#fff;font-size:13px;font-weight:650;line-height:1.4;text-align:center;overflow-wrap:anywhere}
.topbar__msg .wwi-bn{font-size:13px;font-weight:650;color:#fff}
.topbar__msg .wwi-bn__cta{display:none}
.topbar__act{min-width:0;justify-self:end;display:inline-flex;align-items:center;justify-content:flex-end;gap:7px;color:#fff;font-size:12px;font-weight:750;line-height:1.4;text-align:right;text-decoration:none;transition:opacity 180ms ease,transform 180ms ease}
.topbar__act:hover{color:#fff;opacity:.86}
@media(prefers-reduced-motion:no-preference){.topbar__act:hover{transform:translateX(2px)}}
.topbar__act:focus-visible{outline:2px solid #fff;outline-offset:4px;border-radius:2px}
.topbar__msg .wwi-bn__dots{display:inline-flex;align-items:center;justify-content:center;gap:5px;margin-inline-start:2px}
.topbar__msg .wwi-bn__dot{width:5px;height:5px;border-radius:50%;opacity:.55;background:currentColor;border:0;padding:0}
.topbar__msg .wwi-bn__dot.is-on{opacity:1}
@media(max-width:900px){.topbar__inner{grid-template-columns:minmax(105px,.7fr) minmax(0,2fr) minmax(130px,.8fr);gap:12px;padding-inline:22px}.topbar__msg,.topbar__msg .wwi-bn{font-size:12px}.topbar__act{font-size:11px}}
@media(max-width:600px){.topbar__inner{grid-template-columns:minmax(0,1fr) auto;gap:5px 12px;min-height:0;padding:10px 14px}.topbar__benefit{grid-column:1;grid-row:1;font-size:9px}.topbar__msg{grid-column:1/-1;grid-row:2;justify-self:start;justify-content:flex-start;font-size:11px;text-align:left}.topbar__act{grid-column:2;grid-row:1;justify-self:end;font-size:10px;white-space:nowrap}}
@media(prefers-reduced-motion:reduce){.topbar__act{transition:none}}
        header.site{position:sticky;top:0;z-index:80;background:rgba(255,255,255,.92);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
        .nav{display:flex;align-items:center;gap:20px;padding:14px 0}
        .logo{display:flex;align-items:center;gap:10px;font-family:var(--font-display);font-weight:800;font-size:21px;letter-spacing:-.02em}
        .logo .mark{width:36px;height:36px;border-radius:11px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:flex;align-items:center;justify-content:center;color:#fff;font-size:19px;box-shadow:0 6px 18px rgba(242,13,22,.35)}
        .logo-img{height:34px;width:auto;display:block}
        .search{flex:1;display:flex;align-items:center;gap:8px;background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:10px 14px;min-width:140px}
        .search input{flex:1;border:none;background:none;outline:none;font-size:14px;font-family:inherit}
        .search button{border:none;background:var(--brand);color:#fff;border-radius:9px;padding:8px 14px;font-weight:600;cursor:pointer;font-size:13px}
        .nav-actions{display:flex;align-items:center;gap:8px}
        .icon-btn{width:42px;height:42px;border-radius:11px;border:1px solid var(--line);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;position:relative}
        .cart-badge{position:absolute;top:-6px;right:-6px;background:var(--accent);color:#fff;font-size:10px;font-weight:800;border-radius:20px;padding:2px 6px}
        .cats{display:flex;gap:18px;padding:10px 0;overflow-x:auto;font-size:13.5px;font-weight:500;color:var(--muted)}
        .cats a{white-space:nowrap;padding:6px 2px;border-bottom:2px solid transparent}
        .cats a:hover{color:var(--ink);border-color:var(--brand)}
        .hero{overflow:hidden;background:radial-gradient(1200px 500px at 80% -10%,#FFEAEA 0%,#fff 55%);border-bottom:1px solid var(--line)}
        .hero-in{display:grid;grid-template-columns:1.1fr .9fr;gap:40px;align-items:center;padding:64px 0}
        .hero h1{font-family:var(--font-display);font-size:48px;line-height:1.05;letter-spacing:-.03em;font-weight:800}
        .hero h1 em{font-style:normal;background:linear-gradient(120deg,var(--brand),var(--brand2));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
        .hero p{margin:18px 0 26px;color:var(--muted);font-size:17px;max-width:520px;line-height:1.6}
        .cta{display:inline-flex;align-items:center;gap:10px;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;padding:15px 30px;border-radius:13px;font-weight:700;box-shadow:0 12px 30px rgba(242,13,22,.32)}
        .cta.sec{background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:none;margin-left:10px}
        .hero-art{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .art{border-radius:18px;min-height:130px;display:flex;align-items:flex-end;padding:14px;color:#fff;font-weight:700;font-size:15px}
        .art.a{background:linear-gradient(135deg,#F20D16,#C70A12)}.art.b{background:linear-gradient(135deg,#111111,#3F3F46)}.art.c{background:linear-gradient(135deg,#F20D16,#F59E0B)}.art.d{background:linear-gradient(135deg,#111827,#374151)}
        .trust{display:flex;flex-wrap:wrap;gap:22px;justify-content:center;padding:20px 0;border-bottom:1px solid var(--line);color:var(--muted);font-size:13px;font-weight:600}
        .trust b{color:var(--ink)}
        section.pad{padding:48px 0}
        .head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:26px;gap:12px;flex-wrap:wrap}
        .head h1,.head h2{font-family:var(--font-display);font-size:30px;letter-spacing:-.02em}
        .head a{color:var(--brand);font-weight:600;font-size:14px}
        .crumb{font-size:13px;color:var(--muted);padding:18px 22px 0;max-width:1180px;margin:0 auto}
        .crumb a:hover{color:var(--brand)}.crumb span{color:#9CA3AF}
        .cat-grid{display:grid;grid-template-columns:repeat(var(--cat-cols,3),1fr);gap:16px}
        .cat{position:relative;display:block;border-radius:18px;overflow:hidden;background:#111;color:#fff;text-decoration:none;aspect-ratio:3/4;isolation:isolate;box-shadow:0 10px 28px rgba(17,24,39,.10);transition:transform .25s ease,box-shadow .25s ease}
        .cat:hover,.cat:focus-visible{transform:translateY(-4px);box-shadow:0 18px 44px rgba(17,24,39,.18);outline:none}
        .cat:focus-visible{box-shadow:0 0 0 3px rgba(242,13,22,.5),0 18px 44px rgba(17,24,39,.18)}
        .cat__img{position:absolute;inset:0;z-index:0;width:100%;height:100%;object-fit:cover;transition:transform .6s cubic-bezier(.22,1,.36,1),opacity .25s ease}
        .cat:hover .cat__img,.cat:focus-visible .cat__img{transform:scale(1.07)}
        .cat__scrim{position:absolute;inset:0;z-index:1;background:linear-gradient(180deg,rgba(10,10,10,0) 32%,rgba(10,10,10,.55) 66%,rgba(10,10,10,.93) 100%)}
        .cat__body{position:absolute;left:0;right:0;bottom:0;z-index:2;padding:16px 18px;display:flex;flex-direction:column;gap:3px}
        .cat__kicker{font-size:10.5px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;opacity:.82;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
        .cat__ttl{font-family:var(--font-display,Sora),sans-serif;font-size:20px;font-weight:800;line-height:1.1}
        .cat__arrow{position:absolute;top:12px;right:12px;z-index:3;width:34px;height:34px;border-radius:50%;background:var(--brand,#F20D16);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;transition:transform .2s ease}
        .cat:hover .cat__arrow,.cat:focus-visible .cat__arrow{transform:translateX(2px)}
        .cat__badge{position:absolute;top:12px;left:12px;z-index:3;font-size:10px;font-weight:800;letter-spacing:.05em;padding:3px 8px;border-radius:6px;text-transform:uppercase}
        .cat__badge--new{background:#111;color:#fff}
        .cat__badge--off{background:var(--brand,#F20D16);color:#fff}
        .cat__badge--new + .cat__badge--off{left:auto;right:12px;top:52px}
        @media(prefers-reduced-motion:reduce){.cat,.cat__img,.cat__arrow{transition:none}}
        .prod-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
        .card{border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;background:#fff;display:flex;flex-direction:column;transition:transform .2s,box-shadow .2s}
        .card:hover{transform:translateY(-4px);box-shadow:0 16px 36px rgba(17,24,39,.10)}
        .thumb{position:relative;aspect-ratio:1;background:#F3F4F6;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .thumb img{width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply;transition:transform .4s}
        .card:hover .thumb img{transform:scale(1.06)}
        .badge{position:absolute;top:10px;left:10px;background:var(--ok);color:#fff;font-size:10.5px;font-weight:700;padding:4px 9px;border-radius:20px}
        .badge.out{background:#6B7280}.badge.off{background:var(--accent);left:auto;right:10px}
        .card-body{padding:14px;display:flex;flex-direction:column;gap:6px;flex:1}
        .vendor{font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--brand);text-transform:uppercase}
        .ptitle{font-size:13.5px;font-weight:600;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:38px}
        .pricebox{display:flex;align-items:baseline;gap:8px;margin-top:auto}
        .price{font-size:17px;font-weight:800}.cmp{font-size:12.5px;color:#9CA3AF;text-decoration:line-through}
        .add{margin-top:8px;border:none;background:var(--ink);color:#fff;border-radius:10px;padding:10px;font-weight:600;cursor:pointer;font-size:13px;font-family:inherit}
        .add:hover{background:var(--brand)}
        .empty{padding:40px;text-align:center;color:var(--muted);border:1px dashed var(--line);border-radius:var(--radius)}
        .band{background:linear-gradient(120deg,#111111,#C70A12);color:#fff;border-radius:22px;padding:42px;display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap}
        .band h2{font-family:var(--font-display);font-size:28px;letter-spacing:-.02em}.band p{color:#FFD9DB;margin-top:8px;max-width:520px}
        .pdp{display:grid;grid-template-columns:1.05fr .95fr;gap:44px;padding:28px 0 10px}
        .pdp-gallery{border:1px solid var(--line);border-radius:20px;overflow:hidden;background:#F8F8FB;aspect-ratio:1;display:flex;align-items:center;justify-content:center}
        .pdp-gallery img{width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply}
        .pdp-vendor{font-size:12px;font-weight:700;letter-spacing:.08em;color:var(--brand);text-transform:uppercase}
        .pdp h1{font-family:var(--font-display);font-size:32px;line-height:1.15;letter-spacing:-.02em;margin:8px 0 14px}
        .pdp .price{font-size:30px}
        .pdp .meta{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0 20px}
        .chip{font-size:12px;font-weight:600;padding:6px 12px;border-radius:20px;background:var(--soft);border:1px solid var(--line)}
        .chip.ok{background:#ECFDF5;color:#047857;border-color:#A7F3D0}
        .chip.no{background:#F3F4F6;color:#6B7280}
        .qty{display:inline-flex;align-items:center;border:1px solid var(--line);border-radius:12px;overflow:hidden;margin-right:12px}
        .qty button{width:40px;height:46px;border:none;background:#fff;font-size:18px;cursor:pointer}
        .qty input{width:46px;height:46px;border:none;text-align:center;font-size:15px;font-family:inherit}
        .btn-buy{background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;border:none;border-radius:12px;padding:14px 34px;font-weight:700;font-size:15px;cursor:pointer;box-shadow:0 10px 26px rgba(242,13,22,.3)}
        .pdp-desc{margin-top:34px;padding-top:26px;border-top:1px solid var(--line)}
        .pdp-desc h3{font-family:var(--font-display);font-size:18px;margin-bottom:12px}
        .pdp-desc p{margin:10px 0;line-height:1.7;color:#374151}
        .pdp-desc ul{margin:10px 0 10px 20px;line-height:1.7;color:#374151}
        .toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:18px}
        .resultcount{color:var(--muted);font-size:14px}
        .filters{display:flex;gap:8px;flex-wrap:wrap}
        .filters a{font-size:13px;font-weight:600;padding:8px 14px;border:1px solid var(--line);border-radius:20px;background:#fff}
        .filters a.on,.filters a:hover{border-color:var(--brand);color:var(--brand)}
        .cart-wrap{display:grid;grid-template-columns:1.6fr 1fr;gap:30px;padding:24px 0 40px;align-items:start}
        .cart-item{display:grid;grid-template-columns:80px 1fr auto;gap:14px;align-items:center;padding:14px 0;border-bottom:1px solid var(--line)}
        .cart-item img{width:80px;height:80px;object-fit:contain;background:#F3F4F6;border-radius:12px;mix-blend-mode:multiply}
        .summary{border:1px solid var(--line);border-radius:18px;padding:22px;position:sticky;top:120px}
        .summary .row{display:flex;justify-content:space-between;padding:8px 0;font-size:14px}
        .summary .total{font-size:20px;font-weight:800;border-top:1px solid var(--line);margin-top:10px;padding-top:14px}
        footer.site{background:#111111;color:#BBBBBB;padding:48px 0 26px;margin-top:20px}
        .fgrid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:28px}
        footer.site h4{color:#fff;font-size:14px;margin-bottom:12px}
        footer.site a{display:block;padding:4px 0;font-size:13px}footer.site a:hover{color:#fff}
        .fbot{border-top:1px solid #262626;margin-top:30px;padding-top:18px;font-size:12px;text-align:center}
        .toast{position:fixed;bottom:24px;right:24px;background:#111827;color:#fff;padding:14px 20px;border-radius:12px;font-size:14px;font-weight:600;box-shadow:0 12px 30px rgba(0,0,0,.25);opacity:0;transform:translateY(10px);transition:.3s;z-index:9998}
        .toast.show{opacity:1;transform:translateY(0)}
        .pager{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;align-items:center;margin:34px 0 6px}
        .pg{display:inline-flex;align-items:center;justify-content:center;min-width:38px;height:38px;padding:0 12px;border:1px solid var(--line);border-radius:10px;background:#fff;font-size:13.5px;font-weight:600;color:var(--ink)}
        .pg:hover{border-color:var(--brand);color:var(--brand)}
        .pg.on{background:var(--brand);border-color:var(--brand);color:#fff}
        .pgap{padding:0 6px;color:#9CA3AF}
        /* Menú principal (fila negra full-width) */
        .mainnav{background:#111111;border-bottom:1px solid #000}
        .mainnav ul{display:flex;align-items:center;gap:2px;list-style:none;padding:0;margin:0;overflow-x:auto;scrollbar-width:none}
        .mainnav ul::-webkit-scrollbar{display:none}
        .mainnav li{position:relative}
        .mainnav li>a{display:block;padding:13px 14px;color:#fff;font-size:13.5px;font-weight:600;white-space:nowrap}
        .mainnav li>a:hover,.mainnav li>a:focus-visible{background:#1f1f1f;color:#fff;border-bottom:2px solid var(--brand);outline:none}
        .mainnav li>a.hot{color:#fff;background:rgba(242,13,22,.14);border-bottom:2px solid var(--brand)}
        .mainnav .submenu{position:absolute;left:0;top:100%;min-width:210px;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 18px 40px rgba(0,0,0,.14);padding:8px;display:none;z-index:120}
        .mainnav li.has-sub:hover .submenu,.mainnav li.has-sub:focus-within .submenu{display:block}
        .mainnav .submenu a{display:block;padding:9px 12px;border-radius:8px;color:var(--ink);font-size:13px;font-weight:500}
        .mainnav .submenu a:hover{background:var(--soft);color:var(--brand)}
        /* Buscador inteligente + sugerencias */
        .search{position:relative}
        .search .s-ic{position:absolute;left:13px;top:50%;transform:translateY(-50%);font-size:15px;opacity:.55;pointer-events:none}
        .search input{padding-left:38px}
        .sug{position:absolute;left:0;right:0;top:calc(100% + 8px);background:#fff;border:1px solid var(--line);border-radius:14px;box-shadow:0 24px 60px rgba(0,0,0,.16);overflow:hidden;z-index:130;max-height:420px;overflow-y:auto}
        .sug a{display:flex;align-items:center;gap:12px;padding:10px 12px;border-bottom:1px solid var(--line)}
        .sug a:last-child{border-bottom:none}
        .sug a:hover,.sug a.on{background:var(--soft)}
        .sug img{width:46px;height:46px;object-fit:contain;background:#F3F4F6;border-radius:9px;mix-blend-mode:multiply;flex-shrink:0}
        .sug .s-t{font-size:13.5px;font-weight:600;color:var(--ink);line-height:1.3}
        .sug .s-v{font-size:11px;color:var(--muted)}
        .sug .s-p{margin-left:auto;font-weight:800;font-size:13.5px;color:var(--brand);white-space:nowrap}
        .sug .s-empty{padding:14px;font-size:13px;color:var(--muted);text-align:center}
        .icon-btn{width:auto;padding:0 12px;gap:7px}
        .icon-btn .lbl{font-size:13px;font-weight:600}
        @media(max-width:960px){.hero-in,.pdp,.cart-wrap{grid-template-columns:1fr}.cat-grid,.prod-grid{grid-template-columns:repeat(2,1fr)}.fgrid{grid-template-columns:1fr 1fr}.summary{position:static}}
        @media(max-width:600px){.hero h1{font-size:34px}.nav{flex-wrap:wrap}.search{order:9;flex-basis:100%;margin-top:8px}.icon-btn .lbl{display:none}.cat-grid,.prod-grid{grid-template-columns:repeat(2,1fr);gap:12px}.topbar__msg .wwi-bn{font-size:11px}}
        .hero{position:relative;overflow:hidden}
        .hero-bg-video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;opacity:.4}
        .hero>.wrap{position:relative;z-index:1}
        @media(prefers-reduced-motion:reduce){.hero-bg-video{display:none}}
CSS;
    }
}
if (!function_exists('sonika_js')) {
    function sonika_js(): string {
        return 'window.SONIKA_WA=' . json_encode(sonika_whatsapp()) . ';window.SONIKA_CARTKEY="sonika_cart";window.SONIKA_STORE="SONIKA";' . <<<'JS'
(function(){function gc(n){var m=document.cookie.match(new RegExp('(?:^|; )'+n+'=([^;]*)'));return m?decodeURIComponent(m[1]):''}var rf=gc('sonika_aff');if(rf){var sk=/^\/(api|assets|admin|r|_)/;document.querySelectorAll('a[href^="/"]').forEach(function(a){var h=a.getAttribute('href');if(sk.test(h)||/[?&]ref=/.test(h))return;a.setAttribute('href',h+(h.indexOf('?')>=0?'&':'?')+'ref='+encodeURIComponent(rf))})}})();
(function(){
  var SEARCH=document.getElementById('skSearch'), SUG=document.getElementById('skSug'), sTimer=null, sIdx=-1, sData=[];
  function sClose(){if(SUG){SUG.hidden=true;SUG.innerHTML='';}sIdx=-1;sData=[]}
  function sRender(list){
    if(!SUG)return;
    if(!list.length){SUG.innerHTML='<div class="s-empty">Sin coincidencias. Prueba con otra palabra.</div>';SUG.hidden=false;return}
    SUG.innerHTML=list.map(function(r,i){return '<a href="'+r.url+'" data-i="'+i+'">'+(r.thumb?'<img src="'+r.thumb+'" alt="" loading="lazy">':'')+'<span><span class="s-t">'+esc(r.title)+'</span><span class="s-v">'+esc(r.vendor||'')+'</span></span><span class="s-p">'+money(r.price)+'</span></a>';}).join('');
    SUG.hidden=false;sData=list;
    SUG.querySelectorAll('a').forEach(function(a){a.addEventListener('mousedown',function(e){e.preventDefault()})});
  }
  if(SEARCH){
    SEARCH.addEventListener('input',function(){
      var q=SEARCH.value.trim();
      clearTimeout(sTimer);
      if(q.length<2){sClose();return}
      sTimer=setTimeout(function(){
        fetch('/buscar/sugerencias?q='+encodeURIComponent(q)).then(function(r){return r.json()}).then(function(d){sRender(d.results||[])}).catch(function(){sClose()});
      },180);
    });
    SEARCH.addEventListener('keydown',function(e){
      if(SUG&&!SUG.hidden){
        var items=SUG.querySelectorAll('a');
        if(e.key==='ArrowDown'){e.preventDefault();sIdx=Math.min(sIdx+1,items.length-1)}
        else if(e.key==='ArrowUp'){e.preventDefault();sIdx=Math.max(sIdx-1,0)}
        else if(e.key==='Enter'&&sIdx>=0){e.preventDefault();if(items[sIdx])items[sIdx].click();return}
        else if(e.key==='Escape'){sClose();return}
        items.forEach(function(a,i){a.classList.toggle('on',i===sIdx)});
      }
    });
    document.addEventListener('click',function(e){if(SUG&&!SUG.contains(e.target)&&e.target!==SEARCH)sClose()});
    SEARCH.addEventListener('blur',function(){setTimeout(sClose,200)});
  }
  var CART='sonika_cart';
  function read(){try{return JSON.parse(localStorage.getItem(CART))||[]}catch(e){return []}}
  function count(){return read().reduce(function(a,i){return a+(i.qty||0)},0)}
  function paint(){document.querySelectorAll('#cartCount').forEach(function(el){el.textContent=count()})}
  function write(c){localStorage.setItem(CART,JSON.stringify(c));paint();renderCart()}
  function money(n){return '$'+Number(n||0).toLocaleString('es-CO')}
  function esc(s){var d=document.createElement('div');d.textContent=(s==null?'':String(s));return d.innerHTML}
  function toast(m){var t=document.createElement('div');t.className='toast';t.textContent=m;document.body.appendChild(t);requestAnimationFrame(function(){t.classList.add('show')});setTimeout(function(){t.classList.remove('show');setTimeout(function(){t.remove()},300)},1800)}
  window.SonikaCart={
    add:function(item,qty){qty=qty||1;var c=read();var f=c.find(function(i){return i.handle===item.handle});if(f){f.qty+=qty}else{item.qty=qty;c.push(item)}write(c);toast('Agregado al carrito')},
    set:function(h,q){write(read().map(function(i){if(i.handle===h)i.qty=q;return i}).filter(function(i){return i.qty>0}))},
    remove:function(h){write(read().filter(function(i){return i.handle!==h}))},
    clear:function(){write([])},
    items:read, count:count
  };
  window.sonikaWhatsapp=function(extra){
    if(!window.SONIKA_WA){alert('WhatsApp no configurado.');return}
    var items=read();if(!items.length){alert('Tu carrito está vacío.');return}
    var t='Hola '+(window.SONIKA_STORE||'')+', quiero hacer este pedido:';var st=0;
    items.forEach(function(i){var l=(parseFloat(i.price)||0)*(parseInt(i.qty)||1);st+=l;t+='\n• '+(i.title||i.handle)+' x'+(parseInt(i.qty)||1)+' — '+money(l)});
    t+='\n\nTotal: '+money(st);
    if(extra)t+='\n'+extra;
    window.open('https://wa.me/'+window.SONIKA_WA+'?text='+encodeURIComponent(t),'_blank');
  };
  window.sonikaWhatsappFromCart=function(){window.sonikaWhatsapp('')};
  function renderCart(){
    var wrap=document.getElementById('cartItems'); if(!wrap) return;
    var sum=document.getElementById('cartSummary');
    var items=read();
    if(!items.length){wrap.innerHTML='<div class="empty">Tu carrito está vacío. <a href="/tienda" style="color:var(--brand);font-weight:600">Explorar tienda →</a></div>'; if(sum) sum.innerHTML=''; return;}
    var subtotal=0;
    wrap.innerHTML=items.map(function(i){subtotal+=(i.price||0)*(i.qty||0);var q=i.qty||1;
      return '<div class="cart-item"><img src="'+esc(i.img||'')+'" alt=""><div><a href="/producto/'+esc(i.handle)+'" style="font-weight:600">'+esc(i.title||'')+'</a><div style="color:var(--muted);font-size:14px;margin-top:4px">'+money(i.price)+'</div></div><div style="text-align:right"><div class="qty"><button type="button" onclick="SonikaCart.set(\''+esc(i.handle)+'\','+(q-1)+')">−</button><input value="'+q+'" readonly><button type="button" onclick="SonikaCart.set(\''+esc(i.handle)+'\','+(q+1)+')">+</button></div><a href="#" onclick="event.preventDefault();SonikaCart.remove(\''+esc(i.handle)+'\')" style="display:block;margin-top:8px;color:#DC2626;font-size:13px">Eliminar</a></div></div>';
    }).join('');
    if(sum) sum.innerHTML='<h3 style="font-family:Sora,sans-serif;margin-bottom:10px">Resumen</h3><div class="row"><span>Subtotal</span><span>'+money(subtotal)+'</span></div><div class="row"><span>Envío</span><span>Calculado al pagar</span></div><div class="row total"><span>Total</span><span>'+money(subtotal)+'</span></div><a class="btn-buy" style="width:100%;margin-top:16px;display:block;text-align:center" href="/checkout">Finalizar compra</a>'+(window.SONIKA_WA?'<a class="cta" style="width:100%;margin:10px 0 0;justify-content:center;background:#25D366;color:#fff;border:0" href="#" onclick="event.preventDefault();window.sonikaWhatsappFromCart&&window.sonikaWhatsappFromCart()">Pedir por WhatsApp</a>':'')+'<a class="cta sec" href="/tienda" style="width:100%;margin:10px 0 0;justify-content:center">Seguir comprando</a>';
  }
  paint();
  document.querySelectorAll('[data-add]').forEach(function(b){
    b.addEventListener('click',function(e){e.preventDefault();
      var q=b.dataset.qtyTarget?parseInt((document.getElementById(b.dataset.qtyTarget)||{}).value||'1',10):1;
      if(!(q>0)) q=1;
      window.SonikaCart.add({handle:b.dataset.add,title:b.dataset.title,price:parseFloat(b.dataset.price),img:b.dataset.img},q);
      var o=b.textContent;b.textContent='Agregado ✓';b.style.background='#059669';setTimeout(function(){b.textContent=o;b.style.background=''},1200);
    });
  });
  renderCart();
})();
JS;
    }
}
