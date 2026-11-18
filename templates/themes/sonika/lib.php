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
if (!function_exists('sonika_strip_miche_domain')) {
    function sonika_strip_miche_domain(string $text): string {
        $text = preg_replace('~<a\b[^>]*href\s*=\s*("[^"]*miche\.com\.co[^"]*"|\'[^\']*miche\.com\.co[^\']*\')[^>]*>(.*?)</a>~is', '$2', $text) ?? $text;
        $text = preg_replace('~(?:https?://)?(?:www\.)?miche\.com\.co/?~i', '', $text) ?? $text;
        $text = preg_replace('~<(p|span|div)>\s*</\1>~i', '', $text) ?? $text;
        return trim($text);
    }
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
            $st = $db->query("SELECT * FROM store_products WHERE site_id = @site_id ORDER BY id DESC");
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
                $cmp = (float)((int)($r['compare_price_cents'] ?? 0)) / 100;
                $stock = (int)($r['stock'] ?? 0);
                $pt = trim((string)($r['product_type'] ?? '')) ?: 'Tienda';
                $tags = json_decode((string)($r['tags'] ?? ''), true) ?: [];
                if (!$tags) $tags = ['1) Categoria_' . $pt];
                $out[] = [
                    'id' => (int)($r['id'] ?? 0),
                    'product_id' => (int)($r['id'] ?? 0),
                    'handle' => (string)($r['slug'] ?: $r['id']),
                    'title' => (string)($r['name'] ?? ''),
                    'vendor' => (string)($r['vendor'] ?? ''),
                    'product_type' => $pt,
                    'body_html' => sonika_strip_miche_domain((string)($r['description'] ?? '')),
                    '_seo_title' => trim((string)($r['seo_title'] ?? '')),
                    '_seo_description' => trim((string)($r['seo_description'] ?? '')),
                    'published_at' => (string)($r['created_at'] ?? ''),
                    'images' => array_map(fn($u) => ['src' => $u], $imgs),
                    'tags' => $tags,
                    'variants' => [['price' => $price, 'compare_at_price' => $cmp, 'available' => $stock > 0, 'sku' => (string)($r['sku'] ?? '')]],
                    'status' => (string)($r['status'] ?? 'draft'),
                    '_featured' => (int)($r['is_featured'] ?? 0),
                    '_catalog_handle' => (string)($r['catalog_handle'] ?? ''),
                    '_source' => (string)($r['source'] ?? 'local'),
                    '_local' => true,
                    'free_shipping' => (int)($r['free_shipping'] ?? 0),
                    'tax_exempt' => (int)($r['tax_exempt'] ?? 0),
                    'visibility' => (string)($r['visibility'] ?? 'public'),
                    'allowed_ips' => (string)($r['allowed_ips'] ?? ''),
                    '_free_shipping' => (int)($r['free_shipping'] ?? 0),
                    '_tax_exempt' => (int)($r['tax_exempt'] ?? 0),
                    '_visibility' => (string)($r['visibility'] ?? 'public'),
                    '_allowed_ips' => (string)($r['allowed_ips'] ?? ''),
                ];
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }
}
if (!function_exists('sonika_products')) {
    function sonika_products(): array {
        static $cached = null;
        if ($cached !== null) return $cached;
        $c = sonika_catalog();
        $ps = $c['products'] ?? [];
        $normalizeVendors = function (array $products): array {
            foreach ($products as &$product) {
                if (isset($product['vendor'])) $product['vendor'] = sonika_brand_name((string)$product['vendor']);
                if (isset($product['title'])) $product['title'] = sonika_clean_brand_text((string)$product['title']);
                if (isset($product['body_html'])) $product['body_html'] = sonika_clean_brand_text((string)$product['body_html']);
                if (isset($product['product_type'])) $product['product_type'] = sonika_clean_brand_text((string)$product['product_type']);
                if (isset($product['tags']) && is_array($product['tags'])) {
                    foreach ($product['tags'] as &$tag) {
                        if (!is_string($tag)) continue;
                        $trimmed = trim($tag);
                        if (preg_match('/^(\d+)\)\s*([^_]+)_(.+)$/u', $trimmed, $tagMatch)) {
                            $level = (int)$tagMatch[1];
                            $kind = trim($tagMatch[2]);
                            $value = trim($tagMatch[3]);
                            if ($level === 3 || mb_stripos($kind, 'marca') !== false || mb_stripos($kind, 'brand') !== false) {
                                $value = sonika_brand_name($value);
                            } else {
                                $value = sonika_clean_brand_text($value);
                            }
                            $tag = $tagMatch[1] . ') ' . $kind . '_' . $value;
                        } else {
                            $tag = sonika_clean_brand_text($tag);
                        }
                    }
                    unset($tag);
                }
                if (isset($product['images']) && is_array($product['images'])) {
                    foreach ($product['images'] as &$image) {
                        if (is_array($image) && isset($image['alt'])) $image['alt'] = sonika_clean_brand_text((string)$image['alt']);
                    }
                    unset($image);
                }
            }
            unset($product);
            return $products;
        };
        $visible = static fn(array $products): array => array_values(array_filter($products, static fn($p) => \App\Services\IpAccessService::canViewProduct((array)$p)));
        $local = function_exists('sonika_store_local') ? sonika_store_local() : [];
        if (!$local) return $cached = $visible($normalizeVendors($ps));
        $byHandle = [];
        foreach ($local as $p) {
            $key = trim((string)($p['_catalog_handle'] ?? '')) ?: (string)($p['handle'] ?? '');
            if ($key !== '') $byHandle[$key] = $p;
        }
        $out = [];
        $used = [];
        foreach ($ps as $p) {
            $handle = (string)($p['handle'] ?? '');
            if ($handle === '') continue;
            if (!isset($byHandle[$handle])) { $out[] = $p; continue; }
            $l = $byHandle[$handle];
            $used[$handle] = true;
            if (($l['status'] ?? 'draft') !== 'active') continue;
            $p['handle'] = trim((string)($l['handle'] ?? '')) ?: $handle;
            $p['id'] = (int)($l['id'] ?? 0);
            $p['product_id'] = (int)($l['id'] ?? 0);
            $p['_catalog_handle'] = (string)($l['_catalog_handle'] ?? '') ?: $handle;
            $p['title'] = trim((string)($l['title'] ?? '')) ?: (string)($p['title'] ?? '');
            $p['vendor'] = trim((string)($l['vendor'] ?? '')) ?: (string)($p['vendor'] ?? '');
            $p['product_type'] = trim((string)($l['product_type'] ?? '')) ?: (string)($p['product_type'] ?? '');
            $p['body_html'] = sonika_strip_miche_domain(trim((string)($l['body_html'] ?? '')) ?: (string)($p['body_html'] ?? ''));
            if (!empty($l['images'])) $p['images'] = $l['images'];
            if (!empty($l['tags'])) $p['tags'] = $l['tags'];
            if (!empty($l['variants'][0])) $p['variants'][0] = $l['variants'][0];
            $p['_local'] = true;
            $p['_featured'] = (int)($l['_featured'] ?? 0);
            $p['free_shipping'] = (int)($l['free_shipping'] ?? 0);
            $p['_free_shipping'] = (int)($l['free_shipping'] ?? 0);
            $p['tax_exempt'] = (int)($l['tax_exempt'] ?? 0);
            $p['_tax_exempt'] = (int)($l['tax_exempt'] ?? 0);
            $p['visibility'] = (string)($l['visibility'] ?? 'public');
            $p['_visibility'] = (string)($l['visibility'] ?? 'public');
            $p['allowed_ips'] = (string)($l['allowed_ips'] ?? '');
            $p['_allowed_ips'] = (string)($l['allowed_ips'] ?? '');
            $out[] = $p;
        }
        $extras = [];
        foreach ($local as $p) {
            $key = trim((string)($p['_catalog_handle'] ?? '')) ?: (string)($p['handle'] ?? '');
            if ($key !== '' && isset($used[$key])) continue;
            if (($p['status'] ?? 'draft') === 'active') $extras[] = $p;
        }
        return $cached = $visible($normalizeVendors(array_merge($extras, $out)));
    }
}
if (!function_exists('sonika_find')) {
    function sonika_find(string $handle): ?array {
        foreach (sonika_products() as $p) { if (($p['handle'] ?? '') === $handle) return $p; }
        return null;
    }
}
if (!function_exists('sonika_normalize_handle')) {
    function sonika_normalize_handle(string $s): string {
        $s = trim($s);
        if ($s === '') return '';
        if (function_exists('iconv')) {
            $t = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if (is_string($t) && trim($t) !== '') $s = $t;
        }
        $s = strtolower($s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s) ?? '';
        return trim($s, '-');
    }
}
if (!function_exists('sonika_find_fuzzy')) {
    /** Busca un producto aunque el slug tenga espacios, mayúsculas o acentos. */
    function sonika_find_fuzzy(string $handle): ?array {
        $needle = sonika_normalize_handle($handle);
        if ($needle === '') return null;
        foreach (sonika_products() as $p) {
            $h = (string)($p['handle'] ?? '');
            if ($h === '') continue;
            if (sonika_normalize_handle($h) === $needle) return $p;
        }
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
if (!function_exists('sonika_taxonomy')) {
    /** Servicio único de taxonomía (o null si el despliegue aún no lo tiene). */
    function sonika_taxonomy(): ?\App\Services\CatalogTaxonomyService {
        static $svc = null;
        static $checked = false;
        if (!$checked) {
            $checked = true;
            if (class_exists('\\App\\Services\\CatalogTaxonomyService')) $svc = new \App\Services\CatalogTaxonomyService();
        }
        return $svc;
    }
}
if (!function_exists('sonika_categories')) {
    /** Categorías padre canónicas (Cuerdas, Percusión, Vientos...). */
    function sonika_categories(): array {
        static $cached = null;
        if ($cached !== null) return $cached;
        $svc = sonika_taxonomy();
        if ($svc) {
            $out = [];
            foreach ($svc->topLevel() as $top) {
                $top['url'] = '/' . $top['path'];
                $top['children'] = $svc->children($top['key']);
                $out[] = $top;
            }
            return $cached = $out;
        }
        return $cached = [
            ['slug' => 'percusion',   'path' => 'percusion',   'name' => 'Percusión',   'icon' => 'drum',      'tags' => 'Baterías · Platillos · Congas · Parches', 'kw' => ['PERCUS','BATER','REDOBL','PLATILL','CONGA','BONGO','DJEMBE','CAJON','CAJA','TIMBAL','MARAC','CAMPAN','PANDER','TAMBOR','GUIRO','CLAVE','CASTAN','XILOF','METALOF','PARCHE','BAQUETA','SHAKER','GUACHE'], 'url' => '/percusion', 'children' => []],
            ['slug' => 'cuerdas',     'path' => 'cuerdas',     'name' => 'Cuerdas',     'icon' => 'guitar',    'tags' => 'Guitarras · Bajos · Ukeleles · Violines',     'kw' => ['GUITAR','BAJO','BASS','UKELELE','VIOLIN','VIOLA','VIOLONCHELO','CONTRABAJO','ARPA','CUATRO','TIPLE','ENCORDADO','CUERDA'], 'url' => '/cuerdas', 'children' => []],
            ['slug' => 'vientos',     'path' => 'vientos',     'name' => 'Vientos',     'icon' => 'accordion', 'tags' => 'Acordeones · Saxos · Trompetas · Flautas',    'kw' => ['ACORDE','ARMONICA','CLARINET','FLAUTA','GAITA','MELODICA','SAXOF','TROMB','TROMPET','CORNO','OBOE','FAGOT','OCARINA','BOQUILLA','CAÑA'], 'url' => '/vientos', 'children' => []],
            ['slug' => 'teclados',    'path' => 'teclados',    'name' => 'Teclados',    'icon' => 'piano',     'tags' => 'Pianos · Sintetizadores · Controladores',     'kw' => ['PIANO','TECLADO','SYNTH','SINTETIZ','CONTROLADOR','MIDI','PAD','CASIO','CELVIANO','PRIVIA'], 'url' => '/teclados', 'children' => []],
            ['slug' => 'audio',       'path' => 'audio',       'name' => 'Audio',       'icon' => 'speaker',   'tags' => 'Amplificadores · Cabinas · Mixers · Audífonos', 'kw' => ['AMPLIF','CABINA','MIXER','PARLANT','PLANTA','PROCESAD','INTERFAZ','INTERFACE','MONITOR','AUDIFONO','CONSOLA','POTENCIA','CROSSOVER','ECUALIZ','DI ','SPEAKER','TOCADISCOS','REPRODUCTOR'], 'url' => '/audio', 'children' => []],
            ['slug' => 'microfonos',  'path' => 'microfonos',  'name' => 'Micrófonos',  'icon' => 'mic',       'tags' => 'Dinámicos · Condensador · Inalámbricos',       'kw' => ['MICROFON','MICRO','SHURE','INALAMBR','CONDENSADOR','DINAMICO'], 'url' => '/microfonos', 'children' => []],
            ['slug' => 'iluminacion', 'path' => 'iluminacion', 'name' => 'Iluminación', 'icon' => 'light',     'tags' => 'Cabezas móviles · Par LED · Truss · Láseres', 'kw' => ['CABEZA','PAR LED','PARLED','LED','HUMO','TRUSS','TENSOR','DIMMER','STROBO','MOVIL','LUZ','LUCE','BLINDER','REFLECTOR','LASER'], 'url' => '/iluminacion', 'children' => []],
            ['slug' => 'accesorios',  'path' => 'accesorios',  'name' => 'Accesorios',  'icon' => 'box',       'tags' => 'Soportes · Correas · Forros · Cables · Picks', 'kw' => ['FORRO','FUNDA','SOPORTE','STAND','SILLA','ATRIL','RACK','CLAMP','CABLE','ACOPLE','LIMPIADOR','PROTECT','EMPUNADURA','AFINADOR','SORDINA','SLIDE','BANQUETA','CORREA','PICK','CAPODASTRO','REPUESTO','PEDAL','KIT','BAQUETERO','CLAVIJERO'], 'url' => '/accesorios', 'children' => []],
        ];
    }
}
if (!function_exists('sonika_category')) {
    /** Categoría por path (`cuerdas/guitarras/electricas`), alias legacy (`guitarras`) o slug único. */
    function sonika_category(string $slug): ?array {
        $slug = trim(str_replace('\\', '/', $slug), '/');
        if ($slug === '') return null;
        $svc = sonika_taxonomy();
        if ($svc) {
            $node = $svc->find($slug);
            if ($node) {
                $node['children'] = $svc->children($node['key']);
                return $node;
            }
        }
        foreach (sonika_categories() as $c) {
            if ((string)$c['slug'] === $slug) return $c;
        }
        return null;
    }
}
if (!function_exists('sonika_category_url')) {
    function sonika_category_url(string $slug): string {
        $cat = sonika_category($slug);
        return $cat ? (string)$cat['url'] : '/' . ltrim($slug, '/');
    }
}
if (!function_exists('sonika_category_keys')) {
    /** Paths del nodo y sus descendientes (cacheado por request). */
    function sonika_category_keys(string $key): array {
        static $cache = [];
        if (isset($cache[$key])) return $cache[$key];
        $svc = sonika_taxonomy();
        return $cache[$key] = $svc ? $svc->descendants($key) : [$key];
    }
}
if (!function_exists('sonika_product_taxonomy')) {
    function sonika_product_taxonomy(array $p): array {
        $svc = sonika_taxonomy();
        return $svc ? $svc->forProduct($p) : [];
    }
}
if (!function_exists('sonika_product_in_category')) {
    function sonika_product_in_category(array $p, string $key): bool {
        $keys = sonika_category_keys($key);
        if (!$keys) return false;
        $set = array_fill_keys($keys, true);
        $svc = sonika_taxonomy();
        if (!$svc) {
            $legacy = function_exists('sonika_legacy_slug') ? sonika_legacy_slug((string)sonika_effective_category($p)) : '';
            return $legacy !== '' && isset($set[$legacy]);
        }
        foreach ($svc->productKeys($p) as $pk) {
            if (isset($set[$pk])) return true;
        }
        return false;
    }
}
if (!function_exists('sonika_category_ancestors')) {
    function sonika_category_ancestors(string $slug): array {
        $cat = sonika_category($slug);
        $svc = sonika_taxonomy();
        if (!$cat || !$svc) return [];
        return $svc->ancestors((string)$cat['key']);
    }
}
if (!function_exists('sonika_category_count')) {
    function sonika_category_count(string $slug): int {
        static $cache = [];
        $cat = sonika_category($slug);
        $svc = sonika_taxonomy();
        if (!$cat || !$svc) return 0;
        $key = (string)$cat['key'];
        if (isset($cache[$key])) return $cache[$key];
        $set = array_fill_keys($svc->descendants($key), true);
        $n = 0;
        foreach (sonika_products() as $p) {
            foreach ($svc->productKeys($p) as $pk) {
                if (isset($set[$pk])) { $n++; break; }
            }
        }
        return $cache[$key] = $n;
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
        $alias = ['guitarras' => 'cuerdas', 'acordeon' => 'vientos', 'pianos' => 'teclados', 'amplificadores' => 'audio', 'microfono-miche' => 'microfonos', 'cabeza-movil' => 'iluminacion', 'cuerdas-guitarras' => 'accesorios'];
        $want = $alias[strtolower(trim($slug))] ?? strtolower(trim($slug));
        $rows = function_exists('wwi_tc') ? (array)wwi_tc('sonika', 'home_cat_images', []) : [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $rowSlug = strtolower(trim((string)($r['slug'] ?? '')));
            $rowSlug = $alias[$rowSlug] ?? $rowSlug;
            if ($rowSlug !== $want) continue;
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
if (!function_exists('sonika_brand_aliases')) {
    function sonika_brand_aliases(): array {
        static $aliases = null;
        if ($aliases !== null) return $aliases;
        $aliases = [];
        try {
            $db = \App\Core\Database::instance();
            $st = $db->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1");
            $st->execute(['brick_config_shop-filters']);
            $raw = (string)$st->fetchColumn();
            $cfg = $raw !== '' ? json_decode($raw, true) : null;
            $text = is_array($cfg) ? (string)($cfg['brand_aliases'] ?? '') : '';
            if ($text !== '') {
                foreach (preg_split('/\R/u', $text) as $line) {
                    $line = trim($line);
                    if ($line === '' || !str_contains($line, '=')) continue;
                    [$from, $to] = array_map('trim', explode('=', $line, 2));
                    if ($from === '' || $to === '') continue;
                    $aliases[sonika_fold($from)] = $to;
                }
            }
        } catch (\Throwable $e) {
        }
        return $aliases;
    }
}
if (!function_exists('sonika_brand_name')) {
    function sonika_brand_name(string $vendor): string {
        $vendor = trim(preg_replace('/\s+/u', ' ', $vendor) ?: '');
        if ($vendor === '') return '';
        static $cache = [];
        if (isset($cache[$vendor])) return $cache[$vendor];
        $aliases = sonika_brand_aliases();
        $key = sonika_fold($vendor);
        if (isset($aliases[$key])) return $cache[$vendor] = $aliases[$key];
        if (preg_match('/^miche(?:tiendas?|\b)/u', $key)) return $cache[$vendor] = 'Miche';
        if (preg_match('/^mc[\s-]*art$/u', $key)) return $cache[$vendor] = 'MC-ART';
        return $cache[$vendor] = $vendor;
    }
}
if (!function_exists('sonika_clean_brand_text')) {
    function sonika_clean_brand_text(string $text): string {
        if ($text === '' || (stripos($text, 'miche') === false && stripos($text, 'mc') === false)) return $text;
        static $cache = [];
        $key = md5($text);
        if (isset($cache[$key])) return $cache[$key];
        $text = preg_replace(['/\bMC\s*-\s*ART\b/iu', '/\bMC\s+ART\b/iu'], 'MC-ART', $text);
        $patterns = [
            '/\bMiche\s*-\s*Tiendas?\s+Musical(?:es)?\b/iu',
            '/\bMiche\s+Tiendas?\s+Online\b/iu',
            '/\bMiche\s+Tiendas?\b/iu',
        ];
        $clean = preg_replace($patterns, 'SONIKA', $text);
        return $cache[$key] = is_string($clean) ? $clean : $text;
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
if (!function_exists('sonika_banner_instruments')) {
    /**
     * Imágenes reales del catálogo para la rotación del Announcement Banner.
     * Devuelve, como máximo, una imagen válida por categoría disponible.
     */
    function sonika_banner_instruments(int $limit = 10): array {
        static $picked = null;
        if ($picked === null) {
            $specs = [
                'guitarra' => [
                    'label' => 'Guitarras eléctricas',
                    'cat' => 'Guitarra',
                    'title' => '/^GUITARRA ELECTR[IÍ]C[OA]\b/iu',
                    'prefer' => '/FENDER|GRETSCH|JACKSON|IBANEZ/iu',
                ],
                'timbal' => [
                    'label' => 'Timbales',
                    'cat' => 'Timbal',
                    'title' => '/^TIMBAL\b/iu',
                    'prefer' => '/NEGRO|BLACK/iu',
                    'focus' => 'top',
                ],
                'bateria' => [
                    'label' => 'Baterías',
                    'cat' => 'Batería',
                    'title' => '/^BATER[IÍ]A\b/iu',
                    'prefer' => '/LUDWIG|AC[UÚ]STIC/iu',
                ],
                'acordeon' => [
                    'label' => 'Acordeones',
                    'cat' => 'Acordeón',
                    'title' => '/^ACORDE[OÓ]N\b/iu',
                    'prefer' => '/HOHNER/iu',
                ],
                'piano' => [
                    'label' => 'Pianos',
                    'cat' => 'Piano',
                    'title' => '/^PIANO\b/iu',
                    'prefer' => '/NEGRO|MARR[OÓ]N/iu',
                ],
                'tumbadora' => [
                    'label' => 'Tumbadoras',
                    'cat' => 'Conga',
                    'type' => 'tumba',
                    'title' => '/\bTUMBA\b/iu',
                    'prefer' => '/RED|ROJO|CHERRY/iu',
                    'focus' => 'head',
                ],
                'bongo' => [
                    'label' => 'Bongós',
                    'cat' => 'Bongo',
                    'title' => '/^BONGO\b/iu',
                    'prefer' => '/LPA601|ASPIRE/iu',
                ],
                'campana' => [
                    'label' => 'Campanas',
                    'cat' => 'Campana',
                    'title' => '/^CAMPANA\b/iu',
                    'prefer' => '/SALSERA/iu',
                ],
                'mixer' => [
                    'label' => 'Consolas de audio',
                    'cat' => 'Mixer',
                    'title' => '/^(MIXER|CONSOLA)\b/iu',
                    'prefer' => '/ZED|XENYX|BEHRINGER/iu',
                ],
                'cabina' => [
                    'label' => 'Cabinas de sonido',
                    'cat' => 'Cabina',
                    'title' => '/^CABINA\b/iu',
                    'prefer' => '/EVERSE|EVOLVE/iu',
                ],
            ];
            $exclude = '/FORRO|ESTUCHE|ADAPTADOR|SOPORTE|STAND\b|CABLE|BAQUETA|PARCHE|AMPLIFICADOR|CORREA|AFINADOR|PICK\b|BANCO|SILLA|ATRIL|PARTITURA|AUD[IÍ]FONO|MICR[OÓ]FONO|PEDAL|COMBO\b|CAJ[OÓ]N\b/iu';
            $badSrc = '/APLICA_SOLO|FORMATODECREACIONART|placeholder|no-image/iu';
            $used = [];
            $picks = [];
            $order = 0;

            foreach (sonika_products() as $p) {
                $order++;
                $title = trim((string)($p['title'] ?? ''));
                if ($title === '' || preg_match($exclude, $title)) continue;

                $src = trim((string)($p['images'][0]['src'] ?? ''));
                if ($src === '' || !preg_match('~^(https?://|/)~i', $src) || preg_match($badSrc, $src)) continue;
                $imgSrc = function_exists('sonika_img') ? sonika_img($p, 640) : $src;
                if ($imgSrc === '') $imgSrc = $src;

                $handle = (string)($p['handle'] ?? '');
                $cat = (string)(sonika_product_tags($p)[1] ?? '');
                $types = [];
                foreach ((array)($p['tags'] ?? []) as $tag) {
                    if (is_string($tag) && preg_match('/^2\)\s*[^_]+_(.+)$/u', trim($tag), $m)) $types[] = sonika_fold($m[1]);
                }

                $w = (int)($p['images'][0]['width'] ?? 0);
                $h = (int)($p['images'][0]['height'] ?? 0);
                $ratio = $h > 0 ? $w / $h : 1.0;
                $available = !empty($p['variants'][0]['available']);

                foreach ($specs as $key => $spec) {
                    if (isset($used[$handle])) continue;
                    if (($spec['cat'] ?? '') !== '' && $cat !== $spec['cat']) continue;
                    if (($spec['type'] ?? '') !== '' && !in_array($spec['type'], $types, true)) continue;
                    if (!preg_match((string)$spec['title'], $title)) continue;

                    $score = 0;
                    if ($cat === ($spec['cat'] ?? '')) $score += 100;
                    if (($spec['type'] ?? '') !== '' && in_array($spec['type'], $types, true)) $score += 40;
                    if (preg_match('/\.png(\?|$)/i', $src)) $score += 40;
                    elseif (preg_match('/\.webp(\?|$)/i', $src)) $score += 20;
                    if ($w >= 800 && $h >= 800) $score += 30;
                    if ($ratio >= 0.7 && $ratio <= 1.5) $score += 25;
                    elseif ($ratio > 1.5 && $ratio <= 2.2) $score += 15;
                    elseif ($ratio < 0.7) $score += 5;
                    if ($available) $score += 15;
                    if (mb_strlen($title) < 100) $score += 5;
                    if (($spec['prefer'] ?? '') !== '' && preg_match((string)$spec['prefer'], $title)) $score += 35;

                    if (isset($picks[$key]) && ($score < $picks[$key]['_score'] || ($score === $picks[$key]['_score'] && $order >= $picks[$key]['_order']))) continue;
                    if (isset($picks[$key])) unset($used[$picks[$key]['handle']]);
                    $picks[$key] = [
                        'key' => $key,
                        'label' => (string)$spec['label'],
                        'src' => $imgSrc,
                        'title' => $title,
                        'focus' => in_array(($spec['focus'] ?? ''), ['top', 'head'], true) ? (string)$spec['focus'] : 'center',
                        'handle' => $handle,
                        '_score' => $score,
                        '_order' => $order,
                    ];
                    $used[$handle] = true;
                }
            }

            $ordered = [];
            foreach (array_keys($specs) as $key) {
                if (!isset($picks[$key])) continue;
                unset($picks[$key]['_score'], $picks[$key]['_order']);
                $ordered[] = $picks[$key];
            }
            $picked = $ordered;
        }
        return array_slice($picked, 0, max(1, min(10, $limit)));
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
    /** Subcategorías reales de Cuerdas > Guitarras. */
    function sonika_guitar_subs(): array {
        $svc = sonika_taxonomy();
        $out = [];
        if ($svc) {
            foreach ($svc->children('cuerdas/guitarras') as $child) {
                $out[] = [
                    'slug' => (string)$child['slug'],
                    'path' => (string)$child['path'],
                    'url' => (string)$child['url'],
                    'name' => (string)$child['name'],
                    'tipos' => [],
                    'labels' => (string)$child['name'],
                ];
            }
        }
        return $out;
    }
}
if (!function_exists('sonika_guitar_sub_of')) {
    /** Subcategoría de guitarra (slug local: electricas, acusticas...), o '' si no es guitarra. */
    function sonika_guitar_sub_of(array $p): string {
        $tx = sonika_product_taxonomy($p);
        $type = (string)($tx['type'] ?? '');
        $prefix = 'cuerdas/guitarras/';
        if (str_starts_with($type, $prefix)) return substr($type, strlen($prefix));
        return '';
    }
}
if (!function_exists('sonika_guitar_subs_used')) {
    /** Subcategorías de guitarra con conteo real e imagen. */
    function sonika_guitar_subs_used(?array $products = null): array {
        $products = $products ?? sonika_category_products('cuerdas/guitarras');
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
        foreach (sonika_guitar_subs() as $sub) {
            $sub['count'] = $counts[$sub['slug']] ?? 0;
            $sub['thumb'] = $img[$sub['slug']] ?? '';
            $out[] = $sub;
        }
        return $out;
    }
}
if (!function_exists('sonika_category_products')) {
    function sonika_category_products(string $slug, string $sub = ''): array {
        $cat = sonika_category($slug);
        if (!$cat) return [];
        $svc = sonika_taxonomy();
        if ($svc) {
            $rootKey = (string)$cat['key'];
            if ($sub !== '') {
                $subNode = $svc->find($sub);
                if ($subNode) $rootKey = (string)$subNode['key'];
            }
            $set = array_fill_keys($svc->descendants($rootKey), true);
            $out = [];
            foreach (sonika_products() as $p) {
                foreach ($svc->productKeys($p) as $pk) {
                    if (isset($set[$pk])) { $out[] = $p; break; }
                }
            }
            return $out;
        }
        // Fallback legacy (solo si el servicio de taxonomía no está disponible).
        $out = [];
        foreach (sonika_products() as $p) {
            $tagCat = sonika_effective_category($p);
            if ($tagCat !== '') {
                if (sonika_legacy_slug($tagCat) === $slug) $out[] = $p;
            } elseif (sonika_match($p, $cat['kw'])) {
                $out[] = $p;
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
if (!function_exists('sonika_brand_slug')) {
    function sonika_brand_slug(string $name): string {
        $name = sonika_brand_name($name);
        $name = sonika_fold($name);
        $name = preg_replace('/[^a-z0-9]+/', '-', $name) ?: '';
        return trim($name, '-');
    }
}
if (!function_exists('sonika_brand_display')) {
    function sonika_brand_display(string $name): string {
        $name = sonika_brand_name($name);
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?: '');
        if ($name === '') return '';
        if ($name === 'MC-ART') return $name;
        if (mb_strtoupper($name) === $name && mb_strlen($name) > 4) {
            return mb_convert_case(mb_strtolower($name), MB_CASE_TITLE, 'UTF-8');
        }
        return $name;
    }
}
if (!function_exists('sonika_brands')) {
    function sonika_brands(): array {
        static $brands = null;
        if ($brands !== null) return $brands;
        $map = [];
        foreach (sonika_products() as $product) {
            $raw = trim((string)($product['vendor'] ?? ''));
            if ($raw === '') continue;
            $slug = sonika_brand_slug($raw);
            if ($slug === '') continue;
            if (!isset($map[$slug])) {
                $map[$slug] = [
                    'name' => sonika_brand_display($raw),
                    'slug' => $slug,
                    'count' => 0,
                    'types' => [],
                ];
            }
            $map[$slug]['count']++;
            $type = trim((string)($product['product_type'] ?? ''));
            if ($type !== '') $map[$slug]['types'][$type] = ($map[$slug]['types'][$type] ?? 0) + 1;
        }
        foreach ($map as &$brand) {
            arsort($brand['types']);
            $brand['top_types'] = array_slice(array_keys($brand['types']), 0, 3);
            unset($brand['types']);
        }
        unset($brand);
        $brands = array_values($map);
        usort($brands, fn($a, $b) => strcasecmp(sonika_fold($a['name']), sonika_fold($b['name'])));
        return $brands;
    }
}
if (!function_exists('sonika_brand_by_slug')) {
    function sonika_brand_by_slug(string $slug): ?array {
        foreach (sonika_brands() as $brand) {
            if ($brand['slug'] === $slug) return $brand;
        }
        return null;
    }
}
if (!function_exists('sonika_brand_products')) {
    function sonika_brand_products(string $brand): array {
        $slug = sonika_brand_slug($brand);
        if ($slug === '') return [];
        $out = [];
        foreach (sonika_products() as $product) {
            if (sonika_brand_slug((string)($product['vendor'] ?? '')) === $slug) $out[] = $product;
        }
        return $out;
    }
}
if (!function_exists('sonika_featured_brands')) {
    function sonika_featured_brands(array $brands, int $limit = 12): array {
        $preferred = ['fender', 'yamaha', 'roland', 'shure', 'jbl', 'hohner', 'sabian', 'zildjian', 'pearl', 'casio', 'korg', 'audio-technica', 'behringer', 'boss', 'ibanez', 'epiphone', 'gibson', 'tama', 'meinl', 'sennheiser', 'akg', 'focusrite', 'presonus', 'pioneer-dj', 'numark'];
        $indexed = [];
        foreach ($brands as $brand) $indexed[$brand['slug']] = $brand;
        $out = [];
        foreach ($preferred as $slug) {
            if (!isset($indexed[$slug])) continue;
            $out[] = $indexed[$slug];
            unset($indexed[$slug]);
            if (count($out) >= $limit) return $out;
        }
        $rest = array_values($indexed);
        usort($rest, fn($a, $b) => $b['count'] <=> $a['count']);
        foreach ($rest as $brand) {
            $out[] = $brand;
            if (count($out) >= $limit) break;
        }
        return $out;
    }
}
if (!function_exists('sonika_catalog_sort_options')) {
    function sonika_catalog_sort_options(): array {
        return [
            'guitars_amps' => ['label' => 'Guitarras y amplificadores', 'groups' => ['guitar', 'amp'], 'interleaved' => true],
            'pianos_amps' => ['label' => 'Pianos y amplificadores', 'groups' => ['piano', 'amp'], 'interleaved' => true],
            'basses_amps' => ['label' => 'Bajos y amplificadores', 'groups' => ['bass', 'amp'], 'interleaved' => true],
            'gpb_interleaved' => ['label' => 'Guitarras, pianos y bajos intercalados', 'groups' => ['guitar', 'piano', 'bass'], 'interleaved' => true],
            'guitars_basses' => ['label' => 'Guitarras y bajos', 'groups' => ['guitar', 'bass'], 'interleaved' => true],
            'guitars_basses_amps' => ['label' => 'Guitarras, bajos y amplificadores', 'groups' => ['guitar', 'bass', 'amp'], 'interleaved' => true],
            'guitars_pianos_basses' => ['label' => 'Guitarras, pianos y bajos', 'groups' => ['guitar', 'piano', 'bass'], 'interleaved' => true],
            'guitars_pianos_basses_amps' => ['label' => 'Guitarras, pianos, bajos y amplificadores', 'groups' => ['guitar', 'piano', 'bass', 'amp'], 'interleaved' => true],
            'guitars_amps_interleaved' => ['label' => 'Guitarras y amplificadores intercalados', 'groups' => ['guitar', 'amp'], 'interleaved' => true],
            'band' => ['label' => 'Banda completa: guitarras, bajos, pianos, batería y audio', 'groups' => ['guitar', 'bass', 'piano', 'drums', 'mic', 'audio'], 'interleaved' => true],
            'stage' => ['label' => 'Sonido para escenario', 'groups' => ['guitar', 'bass', 'amp', 'drums', 'mic', 'audio'], 'interleaved' => true],
            'studio' => ['label' => 'Estudio y grabación', 'groups' => ['piano', 'audio', 'mic', 'accessory'], 'interleaved' => true],
            'acoustic' => ['label' => 'Acústicos y clásicos', 'groups' => ['acoustic', 'piano', 'wind'], 'interleaved' => true],
            'percussion_audio' => ['label' => 'Percusión y audio', 'groups' => ['drums', 'audio', 'mic'], 'interleaved' => true],
            'winds_accordions' => ['label' => 'Vientos y acordeones', 'groups' => ['wind', 'accordion'], 'interleaved' => true],
            'dj_production' => ['label' => 'DJ y producción', 'groups' => ['piano', 'audio', 'lighting'], 'interleaved' => true],
        ];
    }
}
if (!function_exists('sonika_product_family')) {
    function sonika_product_family(array $product): string {
        $hay = sonika_fold((string)($product['title'] ?? '') . ' ' . (string)($product['product_type'] ?? ''));
        $cat = sonika_fold((string)sonika_effective_category($product));
        if (preg_match('/\b(accesorio|funda|forro|estuche|correa|cable|pedal|soporte|atril|baqueta|baquetero|repuesto|herraje|stand|cuerda|capo|capodastro|pua|afinador)/u', $hay)) return 'accessory';
        if (preg_match('/\b(amplificador|amplifier|preamplificador|preamp|cabina|cabinete|gabinete)/u', $hay)) return 'amp';
        if (preg_match('/(acustic|acoustic|clasic|espanol)/u', $hay) && preg_match('/\b(guitarra|guitar)/u', $hay)) return 'acoustic';
        if (preg_match('/\b(acordeon|accordion)/u', $hay)) return 'accordion';
        if (preg_match('/\b(bajo|bass|contrabajo)/u', $hay)) return 'bass';
        if (preg_match('/\b(guitarra|guitar)/u', $hay)) return 'guitar';
        if (preg_match('/\b(piano|teclado|keyboard|sintetizador|synthesizer|organo)/u', $hay)) return 'piano';
        if (preg_match('/\b(bateria|percusion|tambor|timbal|conga|bongo|djembe|cajon|redoblante|tarola|platillo|cymbal|drum)/u', $hay)) return 'drums';
        if (preg_match('/\b(audio|auricular|audifono|parlante|bafle|altavoz|consola|mezcladora|mixer|monitor|subwoofer|interfaz|multicore)/u', $hay)) return 'audio';
        if (preg_match('/\b(iluminacion|luz|luces|led|laser|spot|reflector|cabezal movil)/u', $hay)) return 'lighting';
        if (preg_match('/\b(microfono|microfone|microphone|mic)/u', $hay)) return 'mic';
        if (preg_match('/\b(saxo|saxofon|trompeta|clarinete|flauta|trombon|corneta|vientos|tuba|oboe|fagot)/u', $hay)) return 'wind';
        if (str_contains($cat, 'accesorio')) return 'accessory';
        if (str_contains($cat, 'bajo')) return 'bass';
        if (str_contains($cat, 'guitarra')) return 'guitar';
        if (str_contains($cat, 'piano') || str_contains($cat, 'teclado')) return 'piano';
        if (str_contains($cat, 'percusion') || str_contains($cat, 'bateria')) return 'drums';
        return 'other';
    }
}
if (!function_exists('sonika_catalog_curate')) {
    function sonika_catalog_curate(array $products, string $sort): array {
        $options = sonika_catalog_sort_options();
        if (!isset($options[$sort])) return $products;
        $groups = (array)$options[$sort]['groups'];
        $buckets = array_fill_keys($groups, []);
        $rest = [];
        foreach ($products as $product) {
            $family = sonika_product_family($product);
            if (isset($buckets[$family])) $buckets[$family][] = $product;
            else $rest[] = $product;
        }
        $ordered = [];
        if (!empty($options[$sort]['interleaved'])) {
            $remaining = true;
            while ($remaining) {
                $remaining = false;
                foreach ($groups as $group) {
                    if (!empty($buckets[$group])) {
                        $ordered[] = array_shift($buckets[$group]);
                        $remaining = true;
                    }
                }
            }
        } else {
            foreach ($groups as $group) {
                foreach ($buckets[$group] as $product) $ordered[] = $product;
            }
        }
        return array_merge($ordered, $rest);
    }
}
if (!function_exists('sonika_catalog_settings')) {
    function sonika_catalog_settings(): array {
        static $cache = null;
        if ($cache !== null) return $cache;
        $settings = (new \App\Services\StoreService())->settings();
        $sort = (string)($settings['store_catalog_sort'] ?? 'catalog');
        if (!in_array($sort, array_merge(['catalog', 'name_asc', 'name_desc', 'price_asc', 'price_desc', 'brand'], array_keys(sonika_catalog_sort_options())), true)) $sort = 'catalog';
        $mode = (string)($settings['store_catalog_mode'] ?? 'all');
        if (!in_array($mode, ['all', 'offers', 'brands', 'mix'], true)) $mode = 'all';
        $brands = json_decode((string)($settings['store_catalog_brands'] ?? '[]'), true);
        $brands = is_array($brands) ? array_values(array_filter(array_map(fn($v) => trim((string)$v), $brands), fn($v) => $v !== '')) : [];
        $brands = array_slice(array_values(array_unique($brands)), 0, 10);
        $salesCategories = json_decode((string)($settings['store_show_sales_categories'] ?? '[]'), true);
        $salesCategories = is_array($salesCategories) ? array_values(array_filter(array_map(fn($v) => trim((string)$v), $salesCategories), fn($v) => $v !== '')) : [];
        $salesCategories = array_values(array_unique($salesCategories));
        return $cache = [
            'sort' => $sort,
            'mode' => $mode,
            'brands' => $brands,
            'hide_out_of_stock' => (string)($settings['store_catalog_hide_out_of_stock'] ?? '0') === '1',
            'show_tags' => (string)($settings['store_catalog_show_tags'] ?? '1') !== '0',
            'disable_oos_add' => (string)($settings['store_oos_disable_add'] ?? '1') !== '0',
            'show_sales' => (string)($settings['store_show_sales'] ?? '1') !== '0',
            'sales_categories' => $salesCategories,
        ];
    }
}
if (!function_exists('sonika_show_sales_for_product')) {
    function sonika_show_sales_for_product(array $p): bool {
        $config = sonika_catalog_settings();
        if (empty($config['show_sales'])) return false;
        $selected = (array)($config['sales_categories'] ?? []);
        if (!$selected) return true;
        foreach (sonika_categories() as $category) {
            $isSelected = in_array((string)$category['slug'], $selected, true);
            if (!$isSelected) {
                foreach ($selected as $sel) {
                    $selCat = sonika_category((string)$sel);
                    if ($selCat && (string)$selCat['key'] === (string)$category['key']) { $isSelected = true; break; }
                }
            }
            if (!$isSelected) continue;
            if (sonika_match($p, (array)($category['kw'] ?? []))) return true;
        }
        return false;
    }
}
if (!function_exists('sonika_catalog_brand_tags')) {
    function sonika_catalog_brand_tags(): array {
        $out = [];
        foreach (sonika_catalog_settings()['brands'] as $name) {
            $brand = sonika_brand_by_slug(sonika_brand_slug($name));
            if ($brand) $out[$brand['slug']] = $brand;
        }
        return array_values($out);
    }
}
if (!function_exists('sonika_catalog_products')) {
    function sonika_catalog_products(): array {
        $config = sonika_catalog_settings();
        $selected = array_map(fn($brand) => $brand['slug'], sonika_catalog_brand_tags());
        $brands = array_fill_keys($selected, true);
        $mode = $config['mode'];
        if ($mode === 'brands' && !$brands) $mode = 'all';
        if ($mode === 'mix' && !$brands) $mode = 'offers';
        $products = [];
        foreach (sonika_products() as $product) {
            if ($config['hide_out_of_stock'] && !sonika_available($product)) continue;
            $price = sonika_price($product);
            $offer = sonika_compare($product) > $price && $price > 0;
            $isBrand = isset($brands[sonika_brand_slug((string)($product['vendor'] ?? ''))]);
            if ($mode === 'offers' && !$offer) continue;
            if ($mode === 'brands' && !$isBrand) continue;
            if ($mode === 'mix' && !$offer && !$isBrand) continue;
            $products[] = $product;
        }
        if ($config['sort'] === 'name_asc') usort($products, fn($a, $b) => strcasecmp(sonika_fold((string)($a['title'] ?? '')), sonika_fold((string)($b['title'] ?? ''))));
        elseif ($config['sort'] === 'name_desc') usort($products, fn($a, $b) => strcasecmp(sonika_fold((string)($b['title'] ?? '')), sonika_fold((string)($a['title'] ?? ''))));
        elseif ($config['sort'] === 'price_asc') usort($products, fn($a, $b) => sonika_price($a) <=> sonika_price($b));
        elseif ($config['sort'] === 'price_desc') usort($products, fn($a, $b) => sonika_price($b) <=> sonika_price($a));
        elseif ($config['sort'] === 'brand') usort($products, function ($a, $b) {
            $brandCmp = strcasecmp(sonika_fold((string)($a['vendor'] ?? '')), sonika_fold((string)($b['vendor'] ?? '')));
            return $brandCmp !== 0 ? $brandCmp : strcasecmp(sonika_fold((string)($a['title'] ?? '')), sonika_fold((string)($b['title'] ?? '')));
        });
        if (isset(sonika_catalog_sort_options()[$config['sort']])) return sonika_catalog_curate($products, $config['sort']);
        return $products;
    }
}
if (!function_exists('sonika_brand_url')) {
    function sonika_brand_url(string $slug): string {
        return '/marcas/' . rawurlencode($slug);
    }
}
if (!function_exists('sonika_brand_tile')) {
    function sonika_brand_tile(array $brand, bool $featured = false): void {
        $name = (string)($brand['name'] ?? '');
        $slug = (string)($brand['slug'] ?? '');
        $count = (int)($brand['count'] ?? 0);
        if ($name === '' || $slug === '') return;
        echo '<a class="brand-tile' . ($featured ? ' brand-tile--featured' : '') . '" href="' . sonika_esc(sonika_brand_url($slug)) . '" data-brand-name="' . sonika_esc(sonika_fold($name) . ' ' . $slug) . '" aria-label="' . sonika_esc($name . ': ' . $count . ' productos') . '">'
            . '<span class="brand-mark" aria-hidden="true">' . sonika_esc(sonika_brand_initials($name)) . '</span>'
            . '<span class="brand-info"><strong>' . sonika_esc($name) . '</strong><small>' . $count . ' ' . ($count === 1 ? 'producto' : 'productos') . '</small></span>'
            . '</a>';
    }
}
if (!function_exists('sonika_brand_initials')) {
    function sonika_brand_initials(string $name): string {
        $parts = preg_split('/[^A-Za-z0-9ÁÉÍÓÚÜÑáéíóúüñ]+/u', $name) ?: [];
        $parts = array_values(array_filter($parts, fn($part) => $part !== ''));
        if (!$parts) return '?';
        if (count($parts) === 1) return mb_strtoupper(mb_substr($parts[0], 0, 2));
        return mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[1], 0, 1));
    }
}
if (!function_exists('sonika_brands_css')) {
    function sonika_brands_css(): string {
        return <<<'CSS'
<style id="sonika-brands-css">
.brands-page{padding:18px 0 56px;color:#151515}
.brands-hero{background:radial-gradient(800px 300px at 90% 0,#fff0f0,transparent 65%),linear-gradient(135deg,#fff,#fafafa);border:1px solid #ececec;border-radius:22px;padding:34px 30px;margin-bottom:20px}
.brands-eyebrow{font-size:11px;font-weight:800;letter-spacing:.13em;text-transform:uppercase;color:#F20D16;margin:0 0 9px}
.brands-hero h1{font-family:Sora,system-ui,sans-serif;font-size:clamp(28px,4vw,46px);line-height:1.08;letter-spacing:-.03em;margin:0 0 12px;max-width:900px}
.brands-hero p{font-size:15px;line-height:1.7;color:#5b6470;margin:0;max-width:760px}
.brands-stats{display:flex;gap:8px 22px;flex-wrap:wrap;margin:20px 0 6px;color:#6b7280;font-size:12px;font-weight:650}
.brands-stats b{color:#111;font-size:15px}
.brands-cats{display:flex;gap:8px;flex-wrap:wrap;margin-top:18px}
.brands-cat{display:inline-flex;align-items:center;min-height:36px;padding:7px 13px;border:1px solid #e2e2e2;border-radius:999px;background:#fff;color:#333333;font-size:12px;font-weight:750;text-decoration:none;transition:.16s}
.brands-cat:hover{border-color:#F20D16;color:#F20D16;background:#fff7f7}
.brands-section{margin:22px 0}
.brands-head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 13px}
.brands-head h2{font-family:Sora,system-ui,sans-serif;font-size:21px;margin:0}
.brands-head a{font-size:12px;color:#6b7280;text-decoration:underline}
.brands-grid{display:grid;grid-template-columns:repeat(6,minmax(0,1fr));gap:9px}
.brand-tile{display:flex;align-items:center;gap:9px;min-width:0;min-height:66px;padding:9px 10px;border:1px solid #e8e8e8;border-radius:12px;background:#fff;text-decoration:none;transition:border-color .16s,box-shadow .16s,transform .16s}
.brand-tile:hover{border-color:#F20D16;box-shadow:0 7px 20px rgba(242,13,22,.08);transform:translateY(-1px)}
.brand-mark{display:grid;place-items:center;width:35px;height:35px;flex:0 0 35px;border-radius:9px;background:#f5f5f5;color:#333333;font-family:Sora,system-ui,sans-serif;font-size:11px;font-weight:800;letter-spacing:.02em}
.brand-tile:hover .brand-mark{background:#F20D16;color:#fff}
.brand-info{display:flex;flex-direction:column;min-width:0;gap:2px}
.brand-info strong{display:block;font-size:11.5px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#333333}
.brand-info small{font-size:9.5px;color:#333333;white-space:nowrap}
.brand-tile--featured .brand-mark{background:#fff0f0;color:#F20D16}
.brand-tile--featured .brand-info strong{font-size:12.5px}
.brands-toolbar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 12px}
.brands-search{width:min(100%,300px);padding:9px 12px;border:1px solid #dedede;border-radius:10px;font:inherit;font-size:13px;background:#fff;color:#222}
.brands-search:focus{outline:2px solid rgba(242,13,22,.28);border-color:#F20D16}
.brands-az{display:flex;gap:4px;flex-wrap:wrap;margin:0 0 16px}
.brands-az a{display:grid;place-items:center;width:27px;height:27px;border:1px solid #e7e7e7;border-radius:7px;color:#333333;text-decoration:none;font-size:10.5px;font-weight:800;background:#fff}
.brands-az a:hover,.brands-az a.on{border-color:#F20D16;color:#F20D16}
.brands-letter{display:flex;align-items:center;gap:10px;margin:18px 0 8px;scroll-margin-top:100px}
.brands-letter b{display:grid;place-items:center;width:24px;height:24px;border-radius:7px;background:#111;color:#fff;font-size:11px}
.brands-letter:after{content:"";height:1px;flex:1;background:#ececec}
.brands-empty{display:none;padding:28px;text-align:center;color:#7a7f87;border:1px dashed #d9d9d9;border-radius:12px;background:#fafafa;font-size:13px}
.brands-seo{display:grid;grid-template-columns:1.15fr .85fr;gap:26px;margin-top:34px;padding-top:26px;border-top:1px solid #ececec}
.brands-seo h2{font-family:Sora,system-ui,sans-serif;font-size:22px;margin:0 0 12px}
.brands-seo h3{font-family:Sora,system-ui,sans-serif;font-size:16px;margin:20px 0 7px}
.brands-seo p{font-size:13.5px;line-height:1.75;color:#5b6470;margin:8px 0}
.brands-seo ul{margin:8px 0 0 18px;padding:0;color:#5b6470;font-size:13px;line-height:1.8}
.brands-faq{display:grid;gap:8px}
.brands-faq details{border:1px solid #e8e8e8;border-radius:11px;background:#fff;padding:11px 13px}
.brands-faq summary{cursor:pointer;font-size:12.5px;font-weight:750;color:#222}
.brands-faq p{margin:8px 0 0;font-size:12.5px}
.brands-brand-page .brands-hero{background:linear-gradient(135deg,#fff,#fff7f7)}
.brands-back{display:inline-flex;margin-bottom:13px;color:#6b7280;font-size:12px;text-decoration:underline}
.brands-types{display:flex;gap:6px;flex-wrap:wrap;margin-top:10px}
.brands-type{display:inline-block;padding:4px 9px;border-radius:999px;background:#f6f6f6;color:#333333;font-size:10.5px;font-weight:700;text-transform:capitalize}
.brands-pager{margin-top:20px}
@media(max-width:1100px){.brands-grid{grid-template-columns:repeat(4,minmax(0,1fr))}}
@media(max-width:820px){.brands-grid{grid-template-columns:repeat(3,minmax(0,1fr))}.brands-seo{grid-template-columns:1fr}.brands-hero{padding:26px 20px}}
@media(max-width:560px){.brands-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}.brand-tile{min-height:62px;padding:8px;gap:7px}.brand-mark{width:31px;height:31px;flex-basis:31px;font-size:10px}.brand-info strong{font-size:10.5px}.brand-info small{font-size:9px}.brands-hero h1{font-size:29px}.brands-page{padding-top:8px}.brands-search{width:100%}}
</style>
CSS;
    }
}
if (!function_exists('sonika_filter_values')) {
    /** Normaliza un parámetro GET que puede venir como string o como array de checkboxes. */
    function sonika_filter_values($value): array {
        $raw = is_array($value) ? $value : preg_split('/\s*,\s*/', (string)$value);
        $out = [];
        foreach ((array)$raw as $item) {
            $item = trim((string)$item);
            if ($item !== '') $out[] = $item;
        }
        return array_values(array_unique($out));
    }
}
if (!function_exists('sonika_filter_collection')) {
    /** Filtra + ordena una colección ya delimitada (tienda, categoría, búsqueda). */
    function sonika_filter_collection(array $products, array $gp): array {
        $min = (float)($gp['min'] ?? 0); $max = (float)($gp['max'] ?? 0);
        $marcas = array_map(fn($m) => function_exists('sonika_brand_name') ? sonika_brand_name((string)$m) : (string)$m, sonika_filter_values($gp['marca'] ?? []));
        $tipos = sonika_filter_values($gp['tipo'] ?? []);
        $subs = sonika_filter_values($gp['sub'] ?? []);
        $cats = sonika_filter_values($gp['cat'] ?? []);
        $desc = (float)($gp['desc'] ?? 0);
        $oferta = !empty($gp['oferta']); $nuevo = !empty($gp['nuevo']); $disp = !empty($gp['disp']);
        $orden = trim((string)($gp['orden'] ?? ''));
        $tax = trim((string)($gp['tax'] ?? '')); $want = [];
        if ($tax !== '') { foreach (explode(',', $tax) as $part) { if (preg_match('/^\s*(\d+):(.+)$/u', $part, $m)) $want[(int)$m[1]] = trim($m[2]); } }
        $newDays = 90;
        $curSubs = array_column(sonika_guitar_subs(), 'slug');
        $out = array_values(array_filter($products, function ($p) use ($min, $max, $marcas, $tipos, $subs, $cats, $desc, $oferta, $nuevo, $disp, $newDays, $want, $curSubs) {
            $price = sonika_price($p); $cmp = sonika_compare($p);
            if ($min > 0 && $price < $min) return false;
            if ($max > 0 && $price > $max) return false;
            if ($marcas) { $vendor = (string)($p['vendor'] ?? ''); $ok = false; foreach ($marcas as $m) { if (strcasecmp($vendor, $m) === 0) { $ok = true; break; } } if (!$ok) return false; }
            if ($tipos) { $type = (string)($p['product_type'] ?? ''); $ok = false; foreach ($tipos as $m) { if (strcasecmp($type, $m) === 0) { $ok = true; break; } } if (!$ok) return false; }
            if ($subs) {
                $ok = false;
                foreach ($subs as $s) {
                    if (in_array($s, $curSubs, true)) { if (sonika_guitar_sub_of($p) === $s) { $ok = true; break; } }
                    elseif (strcasecmp((string)($p['product_type'] ?? ''), $s) === 0) { $ok = true; break; }
                }
                if (!$ok) return false;
            }
            if ($cats) {
                $ok = false;
                foreach ($cats as $cs) {
                    $c = sonika_category($cs); if (!$c) continue;
                    if (sonika_product_in_category($p, (string)$c['key'])) { $ok = true; break; }
                }
                if (!$ok) return false;
            }
            if ($oferta && !($cmp > $price && $price > 0)) return false;
            if ($desc > 0) { $d = ($cmp > 0) ? (($cmp - $price) / $cmp * 100) : 0; if ($d < $desc) return false; }
            if ($nuevo) { $pa = (string)($p['published_at'] ?? ''); if ($pa !== '' && (time() - strtotime($pa)) > $newDays * 86400) return false; }
            if ($disp && !sonika_available($p)) return false;
            if ($want) { $pt = sonika_product_tags($p); foreach ($want as $lv => $vv) { if (sonika_fold((string)($pt[$lv] ?? '')) !== sonika_fold((string)$vv)) return false; } }
            return true;
        }));
        if ($orden === 'precio_asc') usort($out, fn($a, $b) => sonika_price($a) <=> sonika_price($b));
        elseif ($orden === 'precio_desc') usort($out, fn($a, $b) => sonika_price($b) <=> sonika_price($a));
        elseif ($orden === 'descuento') { $dsc = fn($p) => sonika_compare($p) > 0 ? ((sonika_compare($p) - sonika_price($p)) / sonika_compare($p) * 100) : 0; usort($out, fn($a, $b) => $dsc($b) <=> $dsc($a)); }
        elseif ($orden === 'nuevo') usort($out, fn($a, $b) => strcmp((string)($b['published_at'] ?? ''), (string)($a['published_at'] ?? '')));
        return $out;
    }
}
if (!function_exists('sonika_filter_products')) {
    /** Filtra + ordena el catálogo según parámetros GET (usado por /buscar y /buscar/live). */
    function sonika_filter_products(array $gp): array {
        $q = trim((string)($gp['q'] ?? ''));
        $cat = trim((string)($gp['cat'] ?? ''));
        $min = (float)($gp['min'] ?? 0); $max = (float)($gp['max'] ?? 0);
        $desc = (float)($gp['desc'] ?? 0);
        $oferta = !empty($gp['oferta']); $nuevo = !empty($gp['nuevo']); $disp = !empty($gp['disp']);
        $tax = trim((string)($gp['tax'] ?? ''));
        $has = ($q !== '' || $min > 0 || $max > 0 || !empty($gp['marca']) || !empty($gp['tipo']) || !empty($gp['sub']) || !empty($gp['cat']) || $desc > 0 || $oferta || $nuevo || $disp || $tax !== '');
        if (!$has) return [];
        if ($cat !== '' && sonika_category($cat)) $all = sonika_category_products($cat);
        elseif ($q !== '') $all = sonika_search($q);
        else $all = sonika_products();
        return sonika_filter_collection($all, $gp);
    }
}
if (!function_exists('sonika_shop_normalize_brands')) {
    function sonika_shop_normalize_brands(array $gp): array {
        if (empty($gp['marca'])) return $gp;
        $values = function_exists('sonika_filter_values') ? sonika_filter_values($gp['marca']) : (array)$gp['marca'];
        $out = [];
        foreach ($values as $value) {
            $name = function_exists('sonika_brand_name') ? sonika_brand_name((string)$value) : (string)$value;
            if ($name !== '' && !in_array($name, $out, true)) $out[] = $name;
        }
        if ($out) $gp['marca'] = $out;
        else unset($gp['marca']);
        return $gp;
    }
}
if (!function_exists('sonika_shop_filter_url')) {
    /** Conserva los filtros actuales y elimina uno o varios valores concretos. */
    function sonika_shop_filter_url(string $base, array $gp, array $remove = []): string {
        $q = sonika_shop_normalize_brands($gp); unset($q['page']);
        $q = array_filter($q, function ($v) {
            if (is_array($v)) { foreach ($v as $x) { if (trim((string)$x) !== '') return true; } return false; }
            return !is_string($v) || trim($v) !== '';
        });
        if (isset($remove['marca']) && is_string($remove['marca']) && function_exists('sonika_brand_name')) {
            $remove['marca'] = sonika_brand_name($remove['marca']);
        }
        foreach ($remove as $key => $val) {
            if (!array_key_exists($key, $q)) continue;
            if ($val === true) { unset($q[$key]); continue; }
            if (is_array($q[$key])) {
                $q[$key] = array_values(array_filter($q[$key], fn($x) => strcasecmp((string)$x, (string)$val) !== 0));
                if (!$q[$key]) unset($q[$key]);
            } elseif (strcasecmp((string)$q[$key], (string)$val) === 0) unset($q[$key]);
        }
        $qs = http_build_query($q);
        return $base . ($qs !== '' ? '?' . $qs : '');
    }
}
if (!function_exists('sonika_shop_hidden_fields')) {
    function sonika_shop_hidden_fields(array $gp, array $exclude = []): string {
        $gp = sonika_shop_normalize_brands($gp);
        $h = '';
        foreach ($gp as $k => $v) {
            if ($k === 'page' || in_array($k, $exclude, true)) continue;
            if (is_array($v)) { foreach ($v as $vv) $h .= '<input type="hidden" name="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '[]" value="' . htmlspecialchars((string)$vv, ENT_QUOTES, 'UTF-8') . '">'; }
            else $h .= '<input type="hidden" name="' . htmlspecialchars((string)$k, ENT_QUOTES, 'UTF-8') . '" value="' . htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8') . '">';
        }
        return $h;
    }
}
if (!function_exists('sonika_shop_selected')) {
    function sonika_shop_selected(string $value, array $selected): bool {
        foreach ($selected as $item) { if (strcasecmp($value, (string)$item) === 0) return true; }
        return false;
    }
}
if (!function_exists('sonika_shop_filter_data')) {
    function sonika_shop_filter_data(array $products, array $gp, array $opts = []): array {
        $brands = []; $types = []; $prices = []; $categories = [];
        foreach ($products as $p) {
            $vendor = trim((string)($p['vendor'] ?? ''));
            if ($vendor !== '') {
                $bk = strtoupper($vendor);
                if (!isset($brands[$bk])) $brands[$bk] = ['name' => $vendor, 'count' => 0];
                $brands[$bk]['count']++;
            }
            $type = trim((string)($p['product_type'] ?? ''));
            if ($type !== '') {
                $tk = strtoupper($type);
                if (!isset($types[$tk])) $types[$tk] = ['name' => $type, 'count' => 0];
                $types[$tk]['count']++;
            }
            $price = sonika_price($p);
            if ($price > 0) $prices[] = $price;
        }
        if (!empty($opts['show_categories'])) {
            foreach (sonika_categories() as $cat) {
                $count = 0;
                foreach ($products as $p) {
                    if (sonika_product_in_category($p, (string)$cat['key'])) $count++;
                }
                if ($count > 0) $categories[] = ['slug' => $cat['slug'], 'path' => $cat['path'], 'url' => $cat['url'], 'name' => $cat['name'], 'count' => $count];
            }
        }
        $subs = [];
        if (!empty($opts['show_subs'])) {
            foreach (sonika_guitar_subs_used($products) as $sub) { if ((int)$sub['count'] > 0) $subs[] = $sub; }
        }
        uasort($brands, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        uasort($types, fn($a, $b) => $b['count'] <=> $a['count'] ?: strcasecmp($a['name'], $b['name']));
        $minPrice = $prices ? (int)floor(min($prices) / 1000) * 1000 : 0;
        $maxPrice = $prices ? (int)ceil(max($prices) / 1000) * 1000 : 1000000;
        if ($maxPrice <= $minPrice) $maxPrice = $minPrice + 1000;
        $curMin = (isset($gp['min']) && $gp['min'] !== '') ? max($minPrice, (float)$gp['min']) : $minPrice;
        $curMax = (isset($gp['max']) && $gp['max'] !== '') ? min($maxPrice, (float)$gp['max']) : $maxPrice;
        if ($curMin > $curMax) { $curMin = $minPrice; $curMax = $maxPrice; }
        $step = max(1000, (int)round(($maxPrice - $minPrice) / 40 / 1000) * 1000);
        return [
            'brands' => $brands, 'types' => $types, 'categories' => $categories, 'subs' => $subs,
            'min' => $minPrice, 'max' => $maxPrice, 'cur_min' => (int)$curMin, 'cur_max' => (int)$curMax, 'step' => $step,
            'selected' => [
                'marca' => array_map(fn($m) => function_exists('sonika_brand_name') ? sonika_brand_name((string)$m) : (string)$m, sonika_filter_values($gp['marca'] ?? [])), 'tipo' => sonika_filter_values($gp['tipo'] ?? []),
                'sub' => sonika_filter_values($gp['sub'] ?? []), 'cat' => sonika_filter_values($gp['cat'] ?? []),
            ],
            'oferta' => !empty($gp['oferta']), 'disp' => !empty($gp['disp']), 'nuevo' => !empty($gp['nuevo']),
            'desc' => (int)($gp['desc'] ?? 0), 'orden' => trim((string)($gp['orden'] ?? '')),
        ];
    }
}
if (!function_exists('sonika_shop_filter_sidebar')) {
    function sonika_shop_filter_sidebar(array $products, array $gp, string $action, array $opts = []): string {
        $opts = array_merge([
            'show_price' => true, 'show_categories' => false, 'show_subs' => false,
            'show_brands' => true, 'show_types' => true, 'show_promotion' => true, 'show_discount' => true,
            'show_counts' => true, 'auto_apply' => true, 'sticky' => true,
            'accent' => '#F20D16', 'title' => 'Filtros', 'live_text' => 'Se aplica al instante', 'clear_label' => 'Limpiar',
        ], $opts);
        $data = sonika_shop_filter_data($products, $gp, $opts);
        $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
        $accent = preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', (string)$opts['accent']) ? (string)$opts['accent'] : '#F20D16';
        $auto = !empty($opts['auto_apply']);
        $showCounts = !empty($opts['show_counts']);
        $sel = $data['selected'];
        $h = '<aside class="shop-side' . (!empty($opts['sticky']) ? ' is-sticky' : '') . '" id="shop-side" style="--shop-accent:' . $esc($accent) . '"><form class="shop-filter' . ($auto ? ' shop-filter--auto' : '') . '" method="get" action="' . $esc($action) . '" id="shop-filter" data-shop-auto="' . ($auto ? '1' : '0') . '">';
        $h .= '<div class="shop-filter__head"><span class="shop-filter__title">' . $esc((string)$opts['title']) . '</span>' . ($auto ? '<span class="shop-filter__live"><i></i>' . $esc((string)$opts['live_text']) . '</span>' : '') . '</div>';
        if (!empty($opts['show_price']) && $data['max'] > $data['min']) {
            $minHidden = (isset($_GET['min']) && $_GET['min'] !== '' && (float)$_GET['min'] > (float)$data['min']) ? (int)$data['cur_min'] : '';
            $maxHidden = (isset($_GET['max']) && $_GET['max'] !== '' && (float)$_GET['max'] < (float)$data['max']) ? (int)$data['cur_max'] : '';
            $h .= '<details class="shop-fgroup" open><summary>Precio</summary><div class="shop-price" data-shop-price data-min="' . (int)$data['min'] . '" data-max="' . (int)$data['max'] . '" data-step="' . (int)$data['step'] . '">';
            $h .= '<input type="hidden" name="min" data-shop-hidden-min value="' . $minHidden . '"><input type="hidden" name="max" data-shop-hidden-max value="' . $maxHidden . '">';
            $h .= '<div class="shop-price-values"><label>Desde<input type="number" data-shop-min value="' . (int)$data['cur_min'] . '" min="' . (int)$data['min'] . '" max="' . (int)$data['max'] . '" step="' . (int)$data['step'] . '" inputmode="numeric"></label><span>—</span><label>Hasta<input type="number" data-shop-max value="' . (int)$data['cur_max'] . '" min="' . (int)$data['min'] . '" max="' . (int)$data['max'] . '" step="' . (int)$data['step'] . '" inputmode="numeric"></label></div>';
            $h .= '<div class="shop-price-track"><span class="shop-price-fill"></span></div><div class="shop-price-sliders"><input type="range" data-shop-range="min" min="' . (int)$data['min'] . '" max="' . (int)$data['max'] . '" step="' . (int)$data['step'] . '" value="' . (int)$data['cur_min'] . '" aria-label="Precio mínimo"><input type="range" data-shop-range="max" min="' . (int)$data['min'] . '" max="' . (int)$data['max'] . '" step="' . (int)$data['step'] . '" value="' . (int)$data['cur_max'] . '" aria-label="Precio máximo"></div></div></details>';
        }
        if (!empty($opts['show_categories']) && $data['categories']) {
            $h .= '<details class="shop-fgroup" open><summary>Categoría</summary><div class="shop-opt-list">';
            foreach ($data['categories'] as $cat) {
                $h .= '<label class="shop-opt"><input type="checkbox" name="cat[]" value="' . $esc($cat['slug']) . '"' . (sonika_shop_selected($cat['slug'], $sel['cat']) ? ' checked' : '') . '><span>' . $esc($cat['name']) . '</span>' . ($showCounts ? '<small>' . (int)$cat['count'] . '</small>' : '') . '</label>';
            }
            $h .= '</div></details>';
        }
        if (!empty($opts['show_subs']) && $data['subs']) {
            $h .= '<details class="shop-fgroup" open><summary>Subcategoría</summary><div class="shop-opt-list">';
            foreach ($data['subs'] as $sub) {
                $h .= '<label class="shop-opt"><input type="checkbox" name="sub[]" value="' . $esc($sub['slug']) . '"' . (sonika_shop_selected($sub['slug'], $sel['sub']) ? ' checked' : '') . '><span>' . $esc($sub['name']) . '</span>' . ($showCounts ? '<small>' . (int)$sub['count'] . '</small>' : '') . '</label>';
            }
            $h .= '</div></details>';
        }
        if (!empty($opts['show_brands']) && $data['brands']) {
            $h .= '<details class="shop-fgroup" open><summary>Marca</summary><div class="shop-opt-list">';
            foreach ($data['brands'] as $brand) {
                $name = (string)$brand['name']; $count = (int)$brand['count'];
                $h .= '<label class="shop-opt"><input type="checkbox" name="marca[]" value="' . $esc($name) . '"' . (sonika_shop_selected($name, $sel['marca']) ? ' checked' : '') . '><span>' . $esc($name) . '</span>' . ($showCounts ? '<small>' . $count . '</small>' : '') . '</label>';
            }
            $h .= '</div></details>';
        }
        if (!empty($opts['show_types']) && $data['types']) {
            $h .= '<details class="shop-fgroup"><summary>Tipo / característica</summary><div class="shop-opt-list">';
            foreach ($data['types'] as $type) {
                $name = (string)$type['name']; $count = (int)$type['count'];
                $h .= '<label class="shop-opt"><input type="checkbox" name="tipo[]" value="' . $esc($name) . '"' . (sonika_shop_selected($name, $sel['tipo']) ? ' checked' : '') . '><span>' . $esc($name) . '</span>' . ($showCounts ? '<small>' . $count . '</small>' : '') . '</label>';
            }
            $h .= '</div></details>';
        }
        if (!empty($opts['show_promotion']) || !empty($opts['show_discount'])) {
            $h .= '<details class="shop-fgroup" open><summary>Promoción y disponibilidad</summary>';
            if (!empty($opts['show_promotion'])) {
                $h .= '<label class="shop-opt"><input type="checkbox" name="oferta" value="1"' . ($data['oferta'] ? ' checked' : '') . '><span>Solo en promoción</span></label>';
                $h .= '<label class="shop-opt"><input type="checkbox" name="disp" value="1"' . ($data['disp'] ? ' checked' : '') . '><span>Solo disponibles</span></label>';
                $h .= '<label class="shop-opt"><input type="checkbox" name="nuevo" value="1"' . ($data['nuevo'] ? ' checked' : '') . '><span>Novedades</span></label>';
            }
            if (!empty($opts['show_discount'])) {
                $h .= '<label class="shop-opt shop-opt--select"><span>Descuento mínimo</span><select name="desc" class="shop-select"><option value="">Cualquiera</option>';
                foreach ([10, 20, 30, 50] as $d) $h .= '<option value="' . $d . '"' . ((int)$data['desc'] === $d ? ' selected' : '') . '>≥ ' . $d . '%</option>';
                $h .= '</select></label>';
            }
            $h .= '</details>';
        }
        if ($data['orden'] !== '') $h .= '<input type="hidden" name="orden" value="' . $esc($data['orden']) . '">';
        $h .= '<div class="shop-filter-actions">';
        if (!$auto) $h .= '<button type="submit" class="shop-apply">Aplicar filtros</button>';
        else $h .= '<noscript><button type="submit" class="shop-apply">Aplicar filtros</button></noscript>';
        $h .= '<button type="button" class="shop-clear-btn" data-shop-clear>' . $esc((string)$opts['clear_label']) . '</button></div></form></aside>';
        $h .= sonika_shop_js();
        return $h;
    }
}
if (!function_exists('sonika_shop_active_filters')) {
    function sonika_shop_active_filters(array $gp, string $base): string {
        $gp = sonika_shop_normalize_brands($gp);
        $chips = [];
        $add = function (string $label, array $remove, string $key = '', string $value = '') use (&$chips, $gp, $base) {
            $chips[] = '<a class="shop-chip" href="' . htmlspecialchars(sonika_shop_filter_url($base, $gp, $remove), ENT_QUOTES, 'UTF-8') . '" data-filter-key="' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" data-filter-value="' . htmlspecialchars($value, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . ' <span aria-hidden="true">×</span></a>';
        };
        foreach (sonika_filter_values($gp['marca'] ?? []) as $m) $add($m, ['marca' => $m], 'marca', $m);
        foreach (sonika_filter_values($gp['tipo'] ?? []) as $t) $add($t, ['tipo' => $t], 'tipo', $t);
        foreach (sonika_filter_values($gp['sub'] ?? []) as $s) {
            $name = $s; foreach (sonika_guitar_subs() as $sub) { if ($sub['slug'] === $s) { $name = $sub['name']; break; } }
            $add($name, ['sub' => $s], 'sub', $s);
        }
        foreach (sonika_filter_values($gp['cat'] ?? []) as $c) {
            $name = $c; $cat = sonika_category($c); if ($cat) $name = $cat['name'];
            $add($name, ['cat' => $c], 'cat', $c);
        }
        if (!empty($gp['min']) || !empty($gp['max'])) {
            $lo = (float)($gp['min'] ?? 0); $hi = (float)($gp['max'] ?? 0);
            $label = $lo > 0 && $hi > 0 ? ('Precio: ' . sonika_cop($lo) . ' – ' . sonika_cop($hi)) : ($lo > 0 ? ('Desde ' . sonika_cop($lo)) : ('Hasta ' . sonika_cop($hi)));
            $add($label, ['min' => true, 'max' => true], 'price', '');
        }
        if (!empty($gp['oferta'])) $add('En promoción', ['oferta' => true], 'oferta', '1');
        if (!empty($gp['disp'])) $add('Disponibles', ['disp' => true], 'disp', '1');
        if (!empty($gp['nuevo'])) $add('Novedades', ['nuevo' => true], 'nuevo', '1');
        if (!empty($gp['desc'])) $add('Descuento ≥ ' . (int)$gp['desc'] . '%', ['desc' => true], 'desc', (string)(int)$gp['desc']);
        if (!$chips) return '';
        return '<div class="shop-chips"><span class="shop-chips__label">Filtros activos</span>' . implode('', $chips) . '</div>';
    }
}
if (!function_exists('sonika_shop_sort_bar')) {
    function sonika_shop_sort_bar(array $gp, string $action, int $total): string {
        $orden = trim((string)($gp['orden'] ?? ''));
        $options = ['' => 'Relevancia', 'precio_asc' => 'Precio: menor a mayor', 'precio_desc' => 'Precio: mayor a menor', 'descuento' => 'Mayor descuento', 'nuevo' => 'Novedades'];
        $h = '<div class="shop-bar"><div class="shop-bar__left"><button type="button" class="shop-filter-toggle" aria-controls="shop-side" onclick="var s=document.getElementById(\'shop-side\');if(s)s.classList.toggle(\'is-open\')"><svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4"/></svg>Filtros</button><span class="shop-count" role="status" aria-live="polite" aria-atomic="true">' . (int)$total . ' producto(s)</span></div><form class="shop-sort" method="get" action="' . htmlspecialchars($action, ENT_QUOTES, 'UTF-8') . '">';
        $h .= sonika_shop_hidden_fields($gp, ['orden', 'page']);
        $h .= '<label>Ordenar por <select name="orden" data-shop-sort>';
        foreach ($options as $val => $label) $h .= '<option value="' . htmlspecialchars($val, ENT_QUOTES, 'UTF-8') . '"' . ($orden === $val ? ' selected' : '') . '>' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</option>';
        $h .= '</select></label></form></div>';
        return $h;
    }
}
if (!function_exists('sonika_shop_ajax_payload')) {
    function sonika_shop_ajax_payload(array $pg, array $gp, string $baseUrl, string $pagerUrl): array {
        $grid = '';
        if (!empty($pg['items'])) {
            ob_start(); if (function_exists('sonika_grid')) sonika_grid($pg['items']); $grid = ob_get_clean();
        } else {
            $grid = '<div class="empty">No encontramos productos con esos filtros. <a href="' . htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') . '" style="color:#F20D16;font-weight:700">Limpiar filtros</a></div>';
        }
        $chips = sonika_shop_active_filters($gp, $baseUrl);
        $pager = '';
        if (function_exists('sonika_pager')) {
            ob_start(); sonika_pager((int)$pg['total'], (int)$pg['perPage'], (int)$pg['page'], $pagerUrl); $pager = ob_get_clean();
        }
        $url = sonika_shop_filter_url($baseUrl, $gp, ['page' => true, 'shop_ajax' => true]);
        if ((int)$pg['page'] > 1) $url .= (strpos($url, '?') !== false ? '&' : '?') . 'page=' . (int)$pg['page'];
        $pages = max(1, (int)ceil(((int)$pg['total']) / max(1, (int)$pg['perPage'])));
        return ['ok' => true, 'grid' => $grid, 'chips' => $chips, 'pager' => $pager, 'count' => (int)$pg['total'], 'count_label' => ((int)$pg['total']) . ' producto(s)', 'url' => $url, 'page' => (int)$pg['page'], 'pages' => $pages];
    }
}
if (!function_exists('sonika_shop_css')) {
    function sonika_shop_css(): string {
        static $done = false; if ($done) return ''; $done = true;
        return <<<'CSS'
<style>
.shop-layout{display:grid;grid-template-columns:260px minmax(0,1fr);gap:26px;align-items:start}
.shop-layout--full{grid-template-columns:1fr}
.shop-side{position:sticky;top:96px;min-width:0}
.shop-filter{background:#fff;border:1px solid var(--line);border-radius:16px;padding:16px;box-shadow:0 12px 34px rgba(16,24,40,.05)}
.shop-filter__head{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:4px}
.shop-filter__title{font:800 14px/1.2 var(--font-display,Sora),sans-serif;color:#111}
.shop-filter__clear{font-size:11px;font-weight:700;color:#F20D16;text-decoration:none}
.shop-filter__clear:hover{text-decoration:underline}
.shop-fgroup{border-top:1px solid var(--line);padding:12px 0}
.shop-fgroup summary{display:flex;align-items:center;justify-content:space-between;gap:8px;cursor:pointer;list-style:none;font-size:11px;font-weight:800;letter-spacing:.04em;text-transform:uppercase;color:#222}
.shop-fgroup summary::-webkit-details-marker{display:none}
.shop-fgroup summary::after{content:"+";font-size:16px;line-height:1;color:#777;font-weight:500}
.shop-fgroup[open] summary::after{content:"−"}
.shop-opt{display:flex;align-items:center;gap:8px;padding:5px 0;font-size:12px;line-height:1.35;color:#333;cursor:pointer}
.shop-opt input[type=checkbox]{flex:0 0 auto;width:15px;height:15px;accent-color:#F20D16;margin:0}
.shop-opt span{min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.shop-opt small{margin-left:auto;flex:0 0 auto;color:#9a9a9a;font-size:10px}
.shop-opt-list{max-height:190px;overflow:auto;padding:6px 4px 0 0;scrollbar-width:thin}
.shop-price-values{display:flex;align-items:flex-end;gap:7px;margin:10px 0 8px}
.shop-price-values label{flex:1;min-width:0;font-size:9.5px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#888}
.shop-price-values input{display:block;width:100%;margin-top:4px;border:1px solid var(--line);border-radius:8px;padding:7px 8px;font:600 12px/1.2 Sora,sans-serif;color:#111;background:#fff;outline:none}
.shop-price-values input:focus{border-color:#F20D16;box-shadow:0 0 0 3px rgba(242,13,22,.08)}
.shop-price-values>span{font-size:11px;color:#999;padding-bottom:7px}
.shop-price-track{position:relative;height:4px;border-radius:999px;background:#ececec;margin:15px 3px 0}
.shop-price-fill{position:absolute;left:0;top:0;height:100%;border-radius:999px;background:#F20D16}
.shop-price-sliders{position:relative;height:22px;margin-top:-13px}
.shop-price-sliders input[type=range]{position:absolute;left:0;top:0;width:100%;height:22px;margin:0;background:transparent;pointer-events:none;-webkit-appearance:none;appearance:none}
.shop-price-sliders input[type=range]::-webkit-slider-runnable-track{height:4px;background:transparent}
.shop-price-sliders input[type=range]::-webkit-slider-thumb{-webkit-appearance:none;pointer-events:auto;width:17px;height:17px;margin-top:-6.5px;border-radius:50%;background:#fff;border:3px solid #F20D16;box-shadow:0 2px 6px rgba(0,0,0,.18);cursor:pointer}
.shop-price-sliders input[type=range]::-moz-range-track{height:4px;background:transparent}
.shop-price-sliders input[type=range]::-moz-range-thumb{pointer-events:auto;width:11px;height:11px;border-radius:50%;background:#fff;border:3px solid #F20D16;box-shadow:0 2px 6px rgba(0,0,0,.18);cursor:pointer}
.shop-opt--select{align-items:center;justify-content:space-between;gap:8px;padding-top:7px}
.shop-opt--select>span{font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:#888;overflow:visible;white-space:normal}
.shop-select{border:1px solid var(--line);border-radius:8px;padding:6px 7px;font:600 11px Sora,sans-serif;color:#111;background:#fff;outline:none}
.shop-filter-actions{display:flex;align-items:center;gap:9px;padding-top:13px;border-top:1px solid var(--line)}
.shop-apply{flex:1;border:0;border-radius:9px;background:#F20D16;color:#fff;padding:10px 12px;font:800 11px Sora,sans-serif;cursor:pointer;transition:background .15s}
.shop-apply:hover{background:#d80b15}
.shop-clear{font-size:11px;font-weight:700;color:#777;text-decoration:none}
.shop-clear:hover{color:#F20D16}
.shop-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin:0 0 12px}
.shop-bar__left{display:flex;align-items:center;gap:12px}
.shop-count{font-size:12px;color:#777}
.shop-sort{display:flex;align-items:center;gap:7px;font-size:11px;color:#777}
.shop-sort select{border:1px solid var(--line);border-radius:8px;padding:7px 9px;font:600 11px Sora,sans-serif;color:#111;background:#fff;outline:none}
.shop-chips{display:flex;flex-wrap:wrap;align-items:center;gap:6px;margin:0 0 14px}
.shop-chips__label{font-size:9px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#999;margin-right:2px}
.shop-chip{display:inline-flex;align-items:center;gap:6px;padding:5px 8px;border:1px solid #e5e5e5;border-radius:999px;background:#fff;color:#333;font-size:10.5px;font-weight:600;text-decoration:none;line-height:1.2}
.shop-chip:hover{border-color:#F20D16;color:#F20D16}
.shop-chip span{font-size:13px;line-height:.8;color:#999}
.shop-chip--clear{border-style:dashed;color:#F20D16}
.shop-filter-toggle{display:none;align-items:center;gap:6px;border:1px solid var(--line);background:#fff;color:#111;border-radius:9px;padding:8px 11px;font:700 11px Sora,sans-serif;cursor:pointer}
.shop-filter-toggle svg{color:#F20D16}
@media(max-width:980px){.shop-layout{grid-template-columns:1fr}.shop-side{position:static;display:none}.shop-side.is-open{display:block}.shop-filter-toggle{display:inline-flex}.shop-filter{margin-bottom:6px}}
@media(max-width:600px){.shop-bar{gap:8px}.shop-sort{margin-left:auto}.shop-chips{gap:5px}.shop-chip{font-size:10px;padding:4px 7px}.shop-filter{padding:14px;border-radius:13px}}
@media(prefers-reduced-motion:reduce){.shop-apply,.shop-clear-btn{transition:none}.shop-filter__live.is-loading i{animation:none}}
/* Acentos, limpiar y estado en vivo */
.shop-side{--shop-accent:#F20D16}
.shop-side:not(.is-sticky){position:static;top:auto}
.shop-filter__head{align-items:center}
.shop-filter__live{display:inline-flex;align-items:center;gap:5px;font-size:9px;font-weight:700;letter-spacing:.02em;color:#999;white-space:nowrap}
.shop-filter__live i{display:block;width:5px;height:5px;border-radius:50%;background:var(--shop-accent,#F20D16);box-shadow:0 0 7px var(--shop-accent,#F20D16)}
.shop-filter__live.is-loading{color:var(--shop-accent,#F20D16)}
.shop-filter__live.is-loading i{animation:shopLive .7s ease-in-out infinite}
@keyframes shopLive{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.35;transform:scale(.7)}}
.shop-side .shop-opt input[type=checkbox]{accent-color:var(--shop-accent,#F20D16)}
.shop-side .shop-price-fill{background:var(--shop-accent,#F20D16)}
.shop-side .shop-price-values input:focus{border-color:var(--shop-accent,#F20D16);box-shadow:0 0 0 3px color-mix(in srgb,var(--shop-accent,#F20D16) 12%,transparent)}
.shop-side .shop-price-sliders input[type=range]::-webkit-slider-thumb{border-color:var(--shop-accent,#F20D16)}
.shop-side .shop-price-sliders input[type=range]::-moz-range-thumb{border-color:var(--shop-accent,#F20D16)}
.shop-apply{background:var(--shop-accent,#F20D16)}
.shop-clear-btn{flex:1;width:100%;min-height:36px;border:0;border-radius:9px;background:var(--shop-accent,#F20D16);color:#fff;padding:9px 12px;font:800 11px Sora,sans-serif;cursor:pointer;transition:filter .15s,background .15s}
.shop-clear-btn:hover{filter:brightness(.94)}
.shop-clear-btn:focus-visible{outline:2px solid #111;outline-offset:3px}
.shop-chip:hover,.shop-chip--clear{color:var(--shop-accent,#F20D16);border-color:var(--shop-accent,#F20D16)}
.shop-results{transition:opacity .18s ease}
.shop-results.is-loading{opacity:.55;pointer-events:none}
</style>
CSS;
    }
}
if (!function_exists('sonika_shop_js')) {
    function sonika_shop_js(): string {
        static $done = false; if ($done) return ''; $done = true;
        return <<<'HTML'
<script>
(function(){
 function initShop(){
 if(window.__sonikaShopUi)return;window.__sonikaShopUi=1;
 function initPrice(root){
  var min=root.querySelector('[data-shop-min]'),max=root.querySelector('[data-shop-max]');
  var rmin=root.querySelector('[data-shop-range="min"]'),rmax=root.querySelector('[data-shop-range="max"]');
  var hmin=root.querySelector('[data-shop-hidden-min]'),hmax=root.querySelector('[data-shop-hidden-max]');
  var fill=root.querySelector('.shop-price-fill');if(!min||!max||!rmin||!rmax||!fill)return;
  var lo=parseFloat(root.getAttribute('data-min'))||0,hi=parseFloat(root.getAttribute('data-max'))||lo+1000;
  if(hi<=lo)hi=lo+1000;
  function sync(source){
   var a=parseFloat(min.value),b=parseFloat(max.value);
   if(isNaN(a))a=lo;if(isNaN(b))b=hi;
   if(a<lo)a=lo;if(b>hi)b=hi;
   if(a>b){if(source==='min')b=a;else a=b}
   if(a>b)a=b;if(b<a)b=a;
   min.value=Math.round(a);max.value=Math.round(b);rmin.value=a;rmax.value=b;
   if(hmin)hmin.value=(a<=lo)?'':Math.round(a);
   if(hmax)hmax.value=(b>=hi)?'':Math.round(b);
   var l=(a-lo)/(hi-lo)*100,r=(b-lo)/(hi-lo)*100;
   fill.style.left=l+'%';fill.style.width=Math.max(0,r-l)+'%';
  }
  min.addEventListener('input',function(){sync('min')});
  max.addEventListener('input',function(){sync('max')});
  rmin.addEventListener('input',function(){min.value=rmin.value;sync('min')});
  rmax.addEventListener('input',function(){max.value=rmax.value;sync('max')});
  sync();
 }
 document.querySelectorAll('[data-shop-price]').forEach(initPrice);
 function escSel(v){return String(v).replace(/["\\]/g,'\\$&')}
 function formQuery(form){var fd=new FormData(form),q=new URLSearchParams();fd.forEach(function(v,k){if(String(v).trim()!=='')q.append(k,v)});return q}
 function navigateForm(form){var q=formQuery(form),action=form.getAttribute('action')||location.pathname;location.href=action+(q.toString()?'?'+q.toString():'')}
 function renderResult(d){
  if(!d||!d.ok)return;
  var g=document.getElementById('shop-grid');if(g)g.innerHTML=d.grid||'';
  var c=document.getElementById('shop-chips');if(c)c.innerHTML=d.chips||'';
  var p=document.getElementById('shop-pager');if(p)p.innerHTML=d.pager||'';
  var n=document.querySelector('.shop-count');if(n)n.textContent=d.count_label||((d.count||0)+' producto(s)');
  if(d.url){try{history.replaceState(null,'',d.url)}catch(e){}}
 }
 var reqSeq=0,reqCtrl=null;
 function applyUrl(url){
  var box=document.getElementById('shop-results');
  var live=document.querySelector('.shop-filter__live');
  var seq=++reqSeq;
  if(reqCtrl){try{reqCtrl.abort()}catch(e){}}
  reqCtrl=window.AbortController?new AbortController():null;
  if(box){box.classList.add('is-loading');box.setAttribute('aria-busy','true')}
  if(live)live.classList.add('is-loading');
  if(!window.fetch){location.href=url;return}
  fetch(url,{headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'},credentials:'same-origin',signal:reqCtrl?reqCtrl.signal:undefined}).then(function(r){return r.json()}).then(function(d){if(seq===reqSeq)renderResult(d)}).catch(function(){}).finally(function(){if(seq===reqSeq){if(box){box.classList.remove('is-loading');box.removeAttribute('aria-busy')}if(live)live.classList.remove('is-loading')}});
 }
 function fetchForm(form){var q=formQuery(form);q.set('shop_ajax','1');var action=form.getAttribute('action')||location.pathname;applyUrl(action+(action.indexOf('?')>=0?'&':'?')+q.toString())}
 var form=document.querySelector('form.shop-filter--auto');
 if(form){
  var timer=null;
  function later(ms){clearTimeout(timer);timer=setTimeout(function(){fetchForm(form)},ms)}
  function resetPrice(){var root=form.querySelector('[data-shop-price]');if(!root)return;var mn=root.querySelector('[data-shop-min]'),mx=root.querySelector('[data-shop-max]');if(mn){mn.value=root.getAttribute('data-min');mn.dispatchEvent(new Event('input',{bubbles:true}))}if(mx){mx.value=root.getAttribute('data-max');mx.dispatchEvent(new Event('input',{bubbles:true}))}}
  function clearAll(){form.querySelectorAll('input[type=checkbox]').forEach(function(i){i.checked=false});form.querySelectorAll('select').forEach(function(s){s.value=''});resetPrice()}
  form.addEventListener('change',function(){later(0)});
  form.addEventListener('input',function(e){if(e.target&&e.target.type==='range')later(260)});
  form.addEventListener('submit',function(e){e.preventDefault();fetchForm(form)});
  var clear=form.querySelector('[data-shop-clear]');
  if(clear)clear.addEventListener('click',function(){clearAll();later(0)});
  var chips=document.getElementById('shop-chips');
  if(chips)chips.addEventListener('click',function(e){var a=e.target.closest?e.target.closest('a.shop-chip'):null;if(!a)return;e.preventDefault();var key=a.getAttribute('data-filter-key')||'',val=a.getAttribute('data-filter-value')||'';if(key==='all'){clearAll()}else if(key==='price'){resetPrice()}else if(key==='marca'||key==='tipo'||key==='sub'||key==='cat'){var cb=form.querySelector('input[name="'+key+'[]"][value="'+escSel(val)+'"]');if(cb)cb.checked=false}else if(key==='desc'){var sel=form.querySelector('select[name="desc"]');if(sel)sel.value=''}else{var inp=form.querySelector('input[name="'+key+'"][value="'+escSel(val)+'"]');if(inp)inp.checked=false}later(0)});
  var sort=document.querySelector('.shop-sort select');
  if(sort)sort.addEventListener('change',function(){var h=form.querySelector('input[name="orden"]');if(!h){h=document.createElement('input');h.type='hidden';h.name='orden';form.appendChild(h)}h.value=sort.value;later(0)});
  var pager=document.getElementById('shop-pager');
  if(pager)pager.addEventListener('click',function(e){var a=e.target.closest?e.target.closest('a'):null;if(!a)return;var href=a.getAttribute('href')||'';if(!href||href.charAt(0)==='#')return;e.preventDefault();applyUrl(href+(href.indexOf('?')>=0?'&':'?')+'shop_ajax=1')});
 }
 if(!form)document.querySelectorAll('form.shop-filter').forEach(function(f){f.addEventListener('submit',function(e){e.preventDefault();navigateForm(f)})});
 document.querySelectorAll('form.shop-sort').forEach(function(f){f.addEventListener('submit',function(e){e.preventDefault();navigateForm(f)})});
 var manualClear=document.querySelector('[data-shop-clear]');
 if(manualClear&&!form)manualClear.addEventListener('click',function(){var f=manualClear.closest('form');if(!f)return;f.querySelectorAll('input[type=checkbox]').forEach(function(i){i.checked=false});f.querySelectorAll('select').forEach(function(s){s.value=''});var root=f.querySelector('[data-shop-price]');if(root){var mn=root.querySelector('[data-shop-min]'),mx=root.querySelector('[data-shop-max]');if(mn){mn.value=root.getAttribute('data-min');mn.dispatchEvent(new Event('input',{bubbles:true}))}if(mx){mx.value=root.getAttribute('data-max');mx.dispatchEvent(new Event('input',{bubbles:true}))}}navigateForm(f);});
 var sortFallback=document.querySelector('.shop-sort select');
 if(sortFallback&&!form)sortFallback.addEventListener('change',function(){if(sortFallback.form)navigateForm(sortFallback.form)});
 }
 if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',initShop);else initShop();
})();
</script>
HTML;
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
    function sonika_site_head(string $title, string $desc = '', ?string $canonicalUrl = null, bool $noIndex = false, string $ogImage = ''): void {
        if (!empty($_GET['ref']) && preg_match('/^[a-zA-Z0-9_-]{4,64}$/', (string)$_GET['ref'])) {
            $__rc = (string)$_GET['ref'];
            $__rcSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
            @setcookie('sonika_aff', $__rc, ['expires' => time() + 30 * 86400, 'path' => '/', 'secure' => $__rcSecure, 'httponly' => false, 'samesite' => 'Lax']);
            $_COOKIE['sonika_aff'] = $__rc;
        }
        $__seoPath = trim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
        $pageMeta = [
            'title' => $title,
            'meta_title' => $title,
            'meta_description' => $desc ?: 'SONIKA - Instrumentos musicales, audio e iluminación. Envíos a toda Colombia.',
            'slug' => $__seoPath,
        ];
        if ($canonicalUrl !== null && $canonicalUrl !== '') $pageMeta['canonical_url'] = $canonicalUrl;
        if ($ogImage !== '') $pageMeta['og_image'] = $ogImage;
        if ($noIndex) $pageMeta['no_index'] = true;
        try {
            $__seoKeywords = (new \App\Services\SeoKeywordService())->keywordsForPath($__seoPath);
            if ($__seoKeywords) $pageMeta['meta_keywords'] = implode(', ', $__seoKeywords);
        } catch (\Throwable $e) {
        }
        $__directoryContext = ((string)($_GET['from'] ?? '') === 'directorio') || !empty($_COOKIE['sonika_from_directory']);
        if ((string)($_GET['from'] ?? '') === 'directorio') {
            @setcookie('sonika_from_directory', '1', ['expires' => time() + 3600, 'path' => '/', 'samesite' => 'Lax']);
            $_COOKIE['sonika_from_directory'] = '1';
        }
        $__directoryPath = (string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $__directoryHide = (bool)preg_match('#^/(checkout|pago|carrito)#', $__directoryPath);
        ?><!DOCTYPE html>
<html lang="<?= sonika_esc(\App\Core\Lang::current()) ?>">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="msvalidate.01" content="25076A35963875AD7C4B0B8F4E4ECCA1" />
    <?= SeoService::metaTags($pageMeta) ?>
    <?php if (($__fav = wwi_favicon_links()) !== '') { echo $__fav; } else { echo '<link rel="icon" type="image/png" href="/assets/themes/sonika/isotipo.png">'; } ?>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="<?= sonika_fonts_url() ?>" rel="stylesheet" />
    <style><?= sonika_css() ?></style>
    <style>:root{--font-body:<?= sonika_font_stack('body') ?>;--font-display:<?= sonika_font_stack('display') ?>}</style>
    <?= sonika_nav_style_head() ?>
    <link rel="stylesheet" href="/assets/themes/sonika/payments.css?v=<?= (int)(@filemtime(ROOT_DIR . '/public/assets/themes/sonika/payments.css') ?: 1) ?>" />
    <link rel="stylesheet" href="/assets/themes/sonika/dialogs.css?v=<?= (int)(@filemtime(ROOT_DIR . '/public/assets/themes/sonika/dialogs.css') ?: 1) ?>" />
    <link rel="stylesheet" href="/assets/themes/sonika/stock-notifications.css?v=<?= (int)(@filemtime(ROOT_DIR . '/public/assets/themes/sonika/stock-notifications.css') ?: 1) ?>" />
    <link rel="stylesheet" href="/assets/themes/sonika/social-proof.css?v=<?= (int)(@filemtime(ROOT_DIR . '/public/assets/themes/sonika/social-proof.css') ?: 1) ?>" />
    <?php sonika_attribution_assets(); ?>
    <?= wwi_cap_head() ?>
    <style><?= wwi_cap_css() ?></style>
</head>
<body>
<?= wwi_cap_notice() ?>
<div class="topbar<?php
      $__bnSvc = new \App\Services\BannerService();
      $__bnCfg = $__bnSvc->headerConfig();
      if (!$__bnCfg || empty($__bnCfg['items'])) $__bnCfg = $__bnSvc->defaults();
      $__bnImg = '';
      foreach ((array)($__bnCfg['items'] ?? []) as $__bnIt) {
          $__bnU = trim((string)($__bnIt['image'] ?? ''));
          if ($__bnU !== '' && preg_match('~^(https?://|/)~i', $__bnU)) { $__bnImg = $__bnU; break; }
      }
      $__bnSlides = function_exists('sonika_banner_instruments') ? sonika_banner_instruments(10) : [];
      $__bnImgs = [];
      foreach ($__bnSlides as $__bnSlide) {
          $__bnU = trim((string)($__bnSlide['src'] ?? ''));
          if ($__bnU === '' || !preg_match('~^(https?://|/)~i', $__bnU) || in_array($__bnU, array_column($__bnImgs, 'src'), true)) continue;
          $__bnFocus = (string)($__bnSlide['focus'] ?? 'center');
          if (!in_array($__bnFocus, ['center', 'top', 'head'], true)) $__bnFocus = 'center';
          $__bnImgs[] = ['src' => $__bnU, 'focus' => $__bnFocus];
      }
      if (!$__bnImgs && $__bnImg !== '') $__bnImgs[] = ['src' => $__bnImg, 'focus' => 'center'];
      if ($__bnImgs) $__bnImg = (string)$__bnImgs[0]['src'];
      $__bnHasMedia = $__bnImg !== '';
      $__bnHasRot = count($__bnImgs) > 1;
      echo $__bnHasMedia ? ' topbar--has-media' : '';
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
          $__st .= 'background:linear-gradient(105deg,' . $__bg . ' 0%,' . $__bg . ' 55%,' . $__bg2 . ' 100%);';
      } else {
          $__st .= 'background:' . $__bg . ';';
      }
      echo '" style="' . $__st . '"';
      ?>><div class="topbar__inner"><?php if ($__bnHasMedia): ?><span class="topbar__media"<?= $__bnHasRot ? ' data-topbar-media data-interval="10000"' : '' ?> data-images="<?= sonika_esc(json_encode($__bnImgs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)) ?>" aria-hidden="true"><?php foreach (array_slice($__bnImgs, 0, 2) as $__bnI => $__bnItem): ?><img class="topbar__media-img<?= $__bnI === 0 ? ' is-active' : '' ?>" data-focus="<?= sonika_esc((string)$__bnItem['focus']) ?>" src="<?= htmlspecialchars((string)$__bnItem['src'], ENT_QUOTES) ?>" alt="" loading="eager" decoding="async"<?= $__bnI === 0 ? ' fetchpriority="high"' : '' ?>><?php endforeach; ?></span><?php endif; ?><span class="topbar__benefit"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="1" y="6" width="13" height="10" rx="1"/><path d="M14 9h4l3 3v4h-7z"/><circle cx="5.5" cy="18.5" r="2"/><circle cx="17.5" cy="18.5" r="2"/></svg><span><?= sonika_esc((string)($__bnCfg['_name'] ?? 'BENEFICIO SONIKA')) ?></span></span><span class="topbar__msg"><?php
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
<?php if ($__bnHasRot): ?><script>
(function(){
  'use strict';
  var root=document.querySelector('[data-topbar-media]');
  if(!root||root.getAttribute('data-media-ready')==='1')return;
  root.setAttribute('data-media-ready','1');
  var layers=root.querySelectorAll('.topbar__media-img');
  if(layers.length<2)return;
  var slides=[];
  try{slides=JSON.parse(root.getAttribute('data-images')||'[]');}catch(e){return;}
  if(!Array.isArray(slides))return;
  slides=slides.filter(function(slide){return slide&&typeof slide.src==='string'&&slide.src!=='';});
  if(slides.length<2)return;
  var delay=parseInt(root.getAttribute('data-interval')||'10000',10);
  if(!(delay>0))delay=10000;
  var current=0,active=0,timer=null,inView=true;
  function preload(i){var img=new Image();img.decoding='async';img.src=slides[(i+slides.length)%slides.length].src;}
  function canRun(){return root.isConnected&&!document.hidden&&inView;}
  function stop(){if(timer!==null){clearInterval(timer);timer=null;}}
  function start(){if(timer!==null||!canRun())return;timer=setInterval(function(){if(!canRun()){stop();return;}advance();},delay);}
  function advance(){
    var next=(current+1)%slides.length;
    var incoming=layers[1-active];
    var outgoing=layers[active];
    var src=slides[next].src;
    var done=false;
    function cleanup(){incoming.removeEventListener('load',onload);incoming.removeEventListener('error',onerror);}
    function onload(){
      if(done)return;done=true;cleanup();
      incoming.classList.add('is-active');
      outgoing.classList.remove('is-active');
      active=1-active;current=next;
      preload(current+1);
    }
    function onerror(){if(done)return;done=true;cleanup();current=next;}
    var focus=slides[next].focus;if(focus!=='top'&&focus!=='head')focus='center';
    incoming.setAttribute('data-focus',focus);
    incoming.addEventListener('load',onload);
    incoming.addEventListener('error',onerror);
    if(incoming.getAttribute('src')!==src)incoming.setAttribute('src',src);
    if(incoming.complete&&incoming.naturalWidth>0){cleanup();onload();}
  }
  if('IntersectionObserver' in window){
    var io=new IntersectionObserver(function(entries){inView=entries[0]?entries[0].isIntersecting:true;if(inView)start();else stop();},{threshold:0.01});
    io.observe(root);
  }
  document.addEventListener('visibilitychange',function(){if(document.hidden)stop();else start();});
  window.addEventListener('pagehide',stop);
  window.addEventListener('pageshow',function(){start();});
  preload(2);
  start();
})();
</script><?php endif; ?>
<header class="site">
<?php
$__navAbove = ((string)(sonika_nav_style()['position'] ?? 'below') === 'above');
ob_start();
?>
  <?php
  $__allPrimaryNav = wwi_nav('primary', false);
  $__hasPartnerNav = false;
  foreach ($__allPrimaryNav as $__navItem) {
      if (strpos((string)($__navItem['url'] ?? ''), '/sonika-partners') === 0) { $__hasPartnerNav = true; break; }
  }
  $__partnerSeeded = ((string)wwi_setting('sonika_partner_nav_seeded', '')) === '1';
  $__primaryNav = wwi_nav('primary');
  ?>
  <nav class="mainnav" aria-label="Menú principal">
    <div class="wrap">
      <ul>
        <li><a href="/">Inicio</a></li>
        <li><a href="/tienda">Tienda</a></li>
        <li><a href="/directorio" class="dir-link">Directorio Sonika</a></li>
        <?php if (!$__hasPartnerNav && !$__partnerSeeded): ?>
        <li><a href="/sonika-partners" class="dir-link has-mnl-sub"><span class="mnl-label">SONIKA Partners</span><span class="mnl-sub" data-anim="rise" style="--mnl-sub-interval:10s;--mnl-sub-size:10px;--mnl-sub-color:#c9c9c9"><span class="mnl-sub__inner">Comparte, vende y gana.</span></span></a></li>
        <?php endif; ?>
        <?php foreach ($__primaryNav as $it): if (in_array(mb_strtolower(trim((string)$it['label'])), ['inicio', 'tienda'], true)) continue; $hot = (mb_stripos((string)$it['label'], 'oferta') !== false); $__sub = trim((string)($it['subtitle'] ?? '')); $__subAnim = in_array((string)($it['subtitle_animation'] ?? 'none'), ['none', 'rise', 'fade', 'slide'], true) ? (string)$it['subtitle_animation'] : 'none'; $__subInterval = max(3, min(60, (int)($it['subtitle_interval'] ?? 10))); $__subSize = max(8, min(16, (int)($it['subtitle_size'] ?? 10))); $__subColor = preg_match('/^#[0-9a-f]{6}$/i', (string)($it['subtitle_color'] ?? '')) ? (string)$it['subtitle_color'] : ''; $__linkClass = trim(($hot ? 'hot ' : '') . ($__sub !== '' ? 'has-mnl-sub' : '')); ?>
        <li<?= !empty($it['children']) ? ' class="has-sub"' : '' ?>>
          <a href="<?= sonika_esc($it['url']) ?>"<?= $__linkClass !== '' ? ' class="' . sonika_esc($__linkClass) . '"' : '' ?>><span class="mnl-label"><?= sonika_esc($it['label']) ?><?= !empty($it['children']) ? ' <span aria-hidden="true">&#9662;</span>' : '' ?></span><?php if ($__sub !== ''): ?><span class="mnl-sub" data-anim="<?= sonika_esc($__subAnim) ?>" style="--mnl-sub-interval:<?= $__subInterval ?>s;--mnl-sub-size:<?= $__subSize ?>px;<?= $__subColor !== '' ? '--mnl-sub-color:' . sonika_esc($__subColor) . ';' : '' ?>"><span class="mnl-sub__inner"><?= sonika_esc($__sub) ?></span></span><?php endif; ?></a>
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
<?php if ($__directoryContext && !$__directoryHide): ?>
<style>
.directory-return{background:#fff;border-bottom:1px solid #eee;font-family:var(--font-body),system-ui,sans-serif}
.directory-return .wrap{display:flex;align-items:center;min-height:38px}
.directory-return a{display:inline-flex;align-items:center;gap:7px;color:#111;font-size:12px;font-weight:750;text-decoration:none}
.directory-return a:hover{color:#F20D16}
.directory-return a span{color:#F20D16;font-size:15px;line-height:1}
</style>
<div class="directory-return"><div class="wrap"><a href="/directorio"><span aria-hidden="true">&larr;</span> Volver al Directorio Sonika</a></div></div>
<?php endif; ?>
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
if (!function_exists('sonika_rating_stars')) {
    function sonika_rating_stars(float $average): string {
        $average = max(0.0, min(5.0, $average));
        $out = '<span class="pi-stars" aria-hidden="true">';
        for ($i = 1; $i <= 5; $i++) {
            $fill = $average - ($i - 1);
            $class = $fill >= 0.75 ? 'is-full' : ($fill >= 0.25 ? 'is-half' : 'is-empty');
            $out .= '<span class="pi-star ' . $class . '">&#9733;</span>';
        }
        return $out . '</span>';
    }
}
if (!function_exists('sonika_product_insights_html')) {
    function sonika_product_insights_html(array $insights, bool $pdp = false): string {
        if (empty($insights['show_rating']) && empty($insights['free_shipping'])) return '';
        $h = '<div class="' . ($pdp ? 'pdp-insights' : 'card-insights') . '">';
        if (!empty($insights['show_rating'])) {
            $h .= '<div class="pi-topline">';
            if (!empty($insights['show_top_badge'])) $h .= '<span class="pi-badge">Los mejor calificados</span>';
            $h .= '<span class="pi-rating">' . sonika_rating_stars((float)($insights['rating']['average'] ?? 0)) . '<b>' . number_format((float)($insights['rating']['average'] ?? 0), 1, ',', '.') . '</b><span>(' . sonika_esc((string)$insights['rating_label']) . ')</span></span></div>';
            if (!empty($insights['purchases_label'])) $h .= '<div class="pi-sales">' . sonika_esc((string)$insights['purchases_label']) . '</div>';
        }
        if (!empty($insights['free_shipping'])) {
            if ($pdp) {
                $h .= '<div class="pi-shipping"><b>Aplica Entrega GRATIS:</b><span>Entrega El ' . sonika_esc((string)$insights['delivery_label']) . ' dentro de Colombia</span><small>Fecha calculada hasta 5 d&iacute;as h&aacute;biles despu&eacute;s de confirmada la compra. D&iacute;as h&aacute;biles: lunes a viernes.</small></div>';
            } else {
                $h .= '<div class="pi-shipping" title="Fecha calculada hasta 5 d&iacute;as h&aacute;biles despu&eacute;s de confirmada la compra."><b>Env&iacute;o gratis</b><span>Llega ' . sonika_esc((string)$insights['delivery_label']) . '</span></div>';
            }
        }
        return $h . '</div>';
    }
}
if (!function_exists('sonika_featured_instruments')) {
    function sonika_featured_instruments(): array {
        $types = ['electric_guitar' => 1, 'acoustic_guitar' => 1, 'accordion' => 1, 'tumbadora' => 1, 'timbales' => 1, 'piano' => 1, 'bass' => 1, 'speaker' => 1];
        $out = [];
        $svc = new \App\Services\ProductInsightsService();
        foreach (sonika_products() as $p) {
            if (!sonika_available($p)) continue;
            $price = sonika_price($p);
            $type = $svc->topType($p, $price);
            if (!isset($types[$type])) continue;
            if (!isset($out[$type]) || $price > sonika_price($out[$type])) $out[$type] = $p;
        }
        // La parrilla editorial se disena en dos filas de 4. Si un tipo curado
        // no tiene producto disponible, completa la octava ficha con el mejor
        // candidato restante para no dejar una fila coja.
        $target = count($types);
        if (count($out) < $target) {
            $selected = [];
            foreach ($out as $p) $selected[(string)($p['handle'] ?? '')] = true;
            $fallback = [];
            foreach (sonika_products() as $p) {
                if (!sonika_available($p)) continue;
                $handle = (string)($p['handle'] ?? '');
                if ($handle === '' || isset($selected[$handle]) || sonika_img($p) === '') continue;
                $fallback[] = $p;
            }
            $families = [];
            foreach ($out as $p) $families[sonika_product_family($p)] = true;
            usort($fallback, static function ($a, $b) use ($families) {
                $newA = !isset($families[sonika_product_family($a)]);
                $newB = !isset($families[sonika_product_family($b)]);
                if ($newA !== $newB) return $newA ? -1 : 1;
                return sonika_price($b) <=> sonika_price($a);
            });
            foreach ($fallback as $p) {
                $out[] = $p;
                if (count($out) >= $target) break;
            }
        }
        return array_values($out);
    }
}
if (!function_exists('sonika_product_card')) {
    function sonika_stock_alert_html(array $p, bool $pdp = false): string {
        $ref = (string)($p['handle'] ?? '');
        $title = (string)($p['title'] ?? '');
        $uid = 'sn-' . substr(md5($ref), 0, 10);
        return '<div class="stock-alert' . ($pdp ? ' stock-alert--pdp' : '') . '" data-stock-notify data-ref="' . sonika_esc($ref) . '">'
            . '<div class="stock-alert__head"><span class="stock-alert__badge">Producto agotado</span><p>Déjanos tus datos y te avisaremos cuando vuelva a estar disponible.</p></div>'
            . '<label class="stock-alert__toggle" for="' . sonika_esc($uid) . '-toggle"><input type="checkbox" id="' . sonika_esc($uid) . '-toggle" data-stock-toggle> Notificarme cuando esté disponible</label>'
            . '<form class="stock-alert__form" data-stock-form hidden novalidate>'
            . '<div class="stock-alert__step"><span>Paso 1 de 2</span><b>Tu alerta de disponibilidad</b></div>'
            . '<div class="stock-alert__bar" aria-hidden="true"><span></span></div>'
            . '<label class="stock-alert__consent"><input type="checkbox" data-stock-consent> Acepto la <a href="/politica-de-privacidad" target="_blank" rel="noopener">política de tratamiento de datos</a></label>'
            . '<label class="stock-alert__field">Nombre completo<input type="text" data-stock-name maxlength="120" autocomplete="name" required placeholder="Escribe tu nombre completo"></label>'
            . '<label class="stock-alert__field">Correo electrónico<input type="email" data-stock-email maxlength="190" autocomplete="email" required placeholder="tu@correo.com"></label>'
            . '<button type="submit" class="stock-alert__submit">Notificarme</button>'
            . '<p class="stock-alert__msg" data-stock-msg role="status" aria-live="polite"></p>'
            . '</form></div>';
    }

    function sonika_impact_note_html(): string {
        try {
            $cfg = (new \App\Services\ImpactNoteService())->config();
            if (empty($cfg['enabled'])) return '';
            $phrases = array_values(array_filter((array)($cfg['phrases'] ?? [])));
            if (!$phrases) return '';
            $h = '<div class="impact-note impact-note--rotate" role="note" data-impact-note data-interval="' . (int)$cfg['interval'] . '">'
                . '<svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8l1.1 1.1L12 21l7.8-7.5 1.1-1.1a5.5 5.5 0 0 0-.1-7.8Z"/></svg>'
                . '<div class="impact-note__body"><span class="impact-note__label">' . sonika_esc((string)$cfg['label']) . '</span>'
                . '<div class="impact-note__rotator" data-impact-rotator>';
            foreach ($phrases as $i => $phrase) {
                $h .= '<span class="impact-note__phrase' . ($i === 0 ? ' is-active' : '') . '" data-impact-phrase aria-hidden="' . ($i === 0 ? 'false' : 'true') . '">' . sonika_esc($phrase) . '</span>';
            }
            return $h . '</div></div></div>';
        } catch (\Throwable $e) {
            return '';
        }
    }

    function sonika_product_card(array $p): void {
        $img = sonika_img($p);
        $avail = sonika_available($p);
        $price = sonika_price($p);
        $cmp = sonika_compare($p);
        $quoteCat = function_exists('sonika_effective_category') ? trim((string)sonika_effective_category($p)) : '';
        if ($quoteCat === '') $quoteCat = trim((string)($p['product_type'] ?? ''));
        if ($quoteCat === '') $quoteCat = 'Productos';
        $insights = (new \App\Services\ProductInsightsService())->forProduct($p, $price);
        if (!sonika_show_sales_for_product($p)) unset($insights['purchases_label']);
        $__disableOosAdd = !empty(sonika_catalog_settings()['disable_oos_add']);
        ?>
        <div class="card">
          <a class="thumb" href="<?= sonika_esc(sonika_product_href((string)$p['handle'])) ?>">
            <?php if ($img): ?><img src="<?= sonika_esc($img) ?>" alt="<?= sonika_esc($p['title']) ?>" loading="lazy" /><?php endif; ?>
            <span class="badge <?= $avail ? '' : 'out' ?>"><?= $avail ? 'Disponible' : 'Agotado' ?></span>
            <?php if ($cmp > $price && $price > 0): ?><span class="badge off">Oferta</span><?php endif; ?>
            <?php if (!empty($p['_free_shipping']) && empty($insights['free_shipping'])): ?><span class="badge free">Envío gratis</span><?php endif; ?>
            <?php if (!empty($p['_tax_exempt'])): ?><span class="badge taxfree">Sin IVA</span><?php endif; ?>
          </a>
          <div class="card-body">
            <span class="vendor"><?= sonika_esc($p['vendor'] ?? '') ?></span>
            <a class="ptitle" href="<?= sonika_esc(sonika_product_href((string)$p['handle'])) ?>"><?= sonika_esc($p['title']) ?></a>
            <div class="pricebox">
              <span class="price"><?= sonika_cop($price) ?></span>
              <?php if ($cmp > $price && $price > 0): ?><span class="cmp"><?= sonika_cop($cmp) ?></span><?php endif; ?>
            </div>
            <?= sonika_product_insights_html($insights) ?>
            <?php if ($avail || !$__disableOosAdd): ?>
            <button class="add" type="button" data-add="<?= sonika_esc($p['handle']) ?>" data-title="<?= sonika_esc($p['title']) ?>" data-price="<?= (float)$price ?>" data-img="<?= sonika_esc($img) ?>" data-category="<?= sonika_esc($quoteCat) ?>" data-sku="<?= sonika_esc((string)(sonika_variant($p)['sku'] ?? '')) ?>" data-product-id="<?= (int)($p['id'] ?? 0) ?>" data-free-shipping="<?= !empty($p['_free_shipping']) ? '1' : '0' ?>"><?= sonika_esc((string)wwi_tc('sonika', 'card_add_label', 'Agregar')) ?></button>
            <?php else: ?>
            <?= sonika_stock_alert_html($p) ?>
            <?php endif; ?>
          </div>
        </div>
        <?php
    }
}
if (!function_exists('sonika_payments_config')) {
    function sonika_payments_config(): array {
        try {
            $cfg = (new \App\Services\BrebService())->publicConfig();
            try {
                $addi = new \App\Services\AddiService();
                $method = $addi->publicMethod();
                if ($method) {
                    $methods = (array)($cfg['methods'] ?? []);
                    $insertAt = max(0, count($methods) - 1);
                    array_splice($methods, $insertAt, 0, [$method]);
                    $cfg['methods'] = $methods;
                    $settings = $addi->settings();
                    $cfg['addi'] = [
                        'force_visible' => (bool)$settings['force_visible'],
                        'widget_enabled' => (bool)$settings['widget_enabled'],
                        'active' => (bool)($settings['last_config']['isActiveAlly'] ?? false),
                    ];
                }
            } catch (\Throwable $e) {
            }
            try {
                $method = (new \App\Services\SistecreditoService())->publicMethod();
                if ($method) {
                    $methods = (array)($cfg['methods'] ?? []);
                    $insertAt = max(0, count($methods) - 1);
                    array_splice($methods, $insertAt, 0, [$method]);
                    $cfg['methods'] = $methods;
                }
            } catch (\Throwable $e) {
            }
            try {
                $mp = new \App\Services\MercadoPagoService();
                $method = $mp->publicMethod();
                if ($method) {
                    // El brick reemplaza el enlace manual legado si ambos están activos.
                    $methods = array_values(array_filter((array)($cfg['methods'] ?? []), function ($m) {
                        return (string)($m['id'] ?? '') !== 'mercadopago';
                    }));
                    $insertAt = max(0, count($methods) - 1);
                    array_splice($methods, $insertAt, 0, [$method]);
                    $cfg['methods'] = $methods;
                    $cfg['mercadopago'] = $mp->publicConfig();
                }
            } catch (\Throwable $e) {
            }
            try {
                $storeSettings = (new \App\Services\StoreService())->settings();
                $logos = json_decode((string)($storeSettings['store_payment_logos'] ?? ''), true);
                if (is_array($logos)) {
                    $cfg['payment_logos'] = [];
                    foreach ($logos as $method => $url) {
                        $url = trim((string)$url);
                        if ($url !== '' && preg_match('~^(https?://|/assets/)~i', $url)) $cfg['payment_logos'][(string)$method] = $url;
                    }
                }
            } catch (\Throwable $e) {
            }
            try {
                $methodSvc = new \App\Services\CheckoutPaymentMethodService();
                $visible = $methodSvc->visible();
                $baseById = [];
                foreach ((array)($cfg['methods'] ?? []) as $baseMethod) {
                    $baseById[(string)($baseMethod['id'] ?? '')] = $baseMethod;
                }
                $ordered = [];
                foreach ($visible as $vm) {
                    $id = (string)($vm['id'] ?? '');
                    if ($id === '') continue;
                    $base = $baseById[$id] ?? $vm;
                    $base['label'] = (string)($vm['label'] ?? ($base['label'] ?? $id));
                    $base['description'] = (string)($vm['description'] ?? ($base['description'] ?? ''));
                    $base['badge'] = (string)($vm['badge'] ?? ($base['badge'] ?? ''));
                    $base['sort'] = (int)($vm['sort'] ?? 0);
                    $base['scheduled'] = !empty($vm['scheduled']);
                    $ordered[] = $base;
                }
                $cfg['methods'] = $ordered;
                $cfg['default_method'] = $methodSvc->defaultMethod($visible);
                $cfg['visible_methods_count'] = count($ordered);
                $cfg['checkout_methods_configured'] = $methodSvc->isConfigured();
            } catch (\Throwable $e) {
            }
            return $cfg;
        } catch (\Throwable $e) {
            return ['enabled' => false, 'available' => false, 'label' => 'Bre-B', 'methods' => [['id' => 'request', 'label' => 'Coordinar pago por WhatsApp', 'fallback' => true]], 'currency' => 'COP', 'breb' => []];
        }
    }
}
if (!function_exists('sonika_gift_card_checkout_section')) {
    function sonika_gift_card_checkout_section(): void {
        try {
            (new \App\Services\GiftCardService())->ensureTables();
        } catch (\Throwable $e) {
            return;
        }
        ?>
        <div class="skpay__section" id="skpayGiftCardSection">
          <h2>Tarjeta de regalo <small>opcional</small></h2>
          <p class="skpay__muted" style="margin:0 0 10px">Ingresa el código y el PIN de tu tarjeta SONIKA. Puedes usarla total o parcialmente.</p>
          <div style="display:grid;grid-template-columns:1.25fr .75fr auto auto;gap:7px;align-items:center">
            <input class="skpay__input" id="skpayGiftCardCode" placeholder="SNK-XXXX-XXXX-XXXX" autocomplete="off" maxlength="24" aria-label="Código de la tarjeta de regalo" />
            <input class="skpay__input" id="skpayGiftCardPin" placeholder="PIN de 6 dígitos" inputmode="numeric" pattern="[0-9]*" autocomplete="off" maxlength="6" aria-label="PIN de la tarjeta de regalo" />
            <button type="button" class="skpay__btn skpay__btn--secondary skpay__btn--sm" id="skpayGiftCardApply" style="margin:0">Aplicar</button>
            <button type="button" class="skpay__link" id="skpayGiftCardClear" style="font-size:12px">Quitar</button>
          </div>
          <p class="skpay__tiny" id="skpayGiftCardMsg" role="status" aria-live="polite" style="margin-top:7px"></p>
          <p class="skpay__tiny" style="margin:8px 0 0;color:#6b7280">¿No tienes tarjeta? <a href="/tarjetas-de-regalo" style="color:#F20D16;font-weight:700">Compra una tarjeta de regalo</a> o <a href="/tarjetas-de-regalo#consultar" style="color:#F20D16;font-weight:700">consulta tu saldo</a>.</p>
        </div>
        <?php
    }
}
if (!function_exists('sonika_payment_method_cards')) {
    function sonika_payment_method_cards(string $name = 'payment_method'): void {
        $cfg = sonika_payments_config();
        $methods = (array)($cfg['methods'] ?? []);
        if (!$methods) $methods = [['id' => 'request', 'label' => 'Coordinar pago por WhatsApp', 'fallback' => true]];
        $first = true;
        foreach ($methods as $m) {
            $id = (string)($m['id'] ?? 'request');
            $label = (string)($m['label'] ?? 'Pago');
            $disabled = !empty($m['disabled']);
            $comingSoon = !empty($m['coming_soon']);
            $desc = trim((string)($m['description'] ?? ''));
            $badge = trim((string)($m['badge'] ?? ''));
            $icon = '▦';
            $logo = (string)($cfg['payment_logos'][$id] ?? ($m['logo'] ?? ''));
            // Compatibilidad: el logo de Mercado Pago pudo guardarse como método manual.
            if ($logo === '' && $id === 'mercadopago') {
                foreach (['custom-mercado-pago', 'mp', 'mercado-pago'] as $legacyKey) {
                    $candidate = trim((string)($cfg['payment_logos'][$legacyKey] ?? ''));
                    if ($candidate !== '') { $logo = $candidate; break; }
                }
            }
            if ($logo !== '' && !preg_match('~^(https?://|/assets/)~i', $logo)) $logo = '';
            if ($id === 'breb') {
                if ($desc === '') $desc = 'Escanea el QR y paga desde tu app bancaria.';
                $icon = '▦';
            } elseif ($id === 'mercadopago') {
                if ($desc === '') $desc = 'Te llevaremos a Mercado Pago para completar el pago.';
                $icon = 'MP';
            } elseif ($id === 'epayco') {
                if ($desc === '') $desc = 'Te llevaremos a ePayco para completar el pago.';
                $icon = 'eP';
            } elseif ($id === 'addi') {
                if ($desc === '') $desc = 'Compra ahora y paga a cuotas con Addi.';
                $icon = 'A';
            } elseif ($id === 'sistecredito') {
                if ($desc === '') $desc = 'Compra ahora y paga a cuotas con Sistecrédito.';
                $icon = 'SC';
            } else {
                if ($desc === '') $desc = 'Coordinamos el pago y el envío contigo por WhatsApp.';
                $icon = 'WA';
            }
            ?>
            <label class="skpay__method" data-sk-method="<?= sonika_esc($id) ?>"<?= $disabled ? ' aria-disabled="true" style="opacity:.72"' : '' ?>>
              <input type="radio" name="<?= sonika_esc($name) ?>" value="<?= sonika_esc($id) ?>"<?= (!$disabled && $first) ? ' checked' : '' ?><?= $disabled ? ' disabled' : '' ?> />
              <span class="skpay__method-icon<?= $logo !== '' ? ' skpay__method-icon--logo' : '' ?>" aria-hidden="true"><?= $logo !== '' ? '<img src="' . sonika_esc($logo) . '" alt="" loading="lazy"/>' : sonika_esc($icon) ?></span>
              <span class="skpay__method-body">
                <span class="skpay__method-title"><?= sonika_esc($label) ?><?php if ($id === 'breb'): ?> <span class="skpay__badge" data-sk-save hidden></span><?php endif; ?><?php if ($badge !== ''): ?> <span class="skpay__badge skpay__badge--soft"><?= sonika_esc($badge) ?></span><?php endif; ?></span>
                <span class="skpay__method-desc"><?= sonika_esc($desc) ?></span>
                <?php if ($id === 'addi' && empty($cfg['addi']['active'])): ?><span class="skpay__method-flag">Próximamente compra con Addi</span><?php endif; ?>
              </span>
            </label>
            <?php
            if (!$disabled) $first = false;
        }
    }
}
if (!function_exists('sonika_payments_assets')) {
    function sonika_payments_assets(): void {
        $cfg = sonika_payments_config();
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<script>window.SONIKA_PAY=' . $json . ';</script>';
        echo '<script src="/assets/js/sonika-payments.js?v=' . (int)(@filemtime(ROOT_DIR . '/public/assets/js/sonika-payments.js') ?: 1) . '" defer></script>';
    }
}
if (!function_exists('sonika_recommendations_config')) {
    function sonika_recommendations_config(): array {
        try {
            $cfg = (new \App\Services\RecommendationService())->publicConfig();
            $cfg['endpoint'] = '/api/v1/public/store/recommendations';
            $cfg['cartKey'] = 'sonika_cart';
            return $cfg;
        } catch (\Throwable $e) {
            return ['enabled' => false, 'items' => []];
        }
    }
}
if (!function_exists('sonika_recommendations_assets')) {
    function sonika_recommendations_assets(): void {
        $cfg = sonika_recommendations_config();
        if (empty($cfg['enabled'])) return;
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        echo '<link rel="stylesheet" href="/assets/themes/sonika/recommendations.css?v=' . (int)(@filemtime(ROOT_DIR . '/public/assets/themes/sonika/recommendations.css') ?: 1) . '" />';
        echo '<script>window.SONIKA_RECS=' . $json . ';</script>';
        echo '<script src="/assets/js/recommendations.js?v=' . (int)(@filemtime(ROOT_DIR . '/public/assets/js/recommendations.js') ?: 1) . '" defer></script>';
    }
}
if (!function_exists('sonika_addi_widget')) {
    function sonika_addi_widget(float $price): void {
        try {
            echo (new \App\Services\AddiService())->widgetHtml($price);
        } catch (\Throwable $e) {
        }
    }
}
if (!function_exists('sonika_addi_assets')) {
    function sonika_addi_assets(): void {
        try {
            echo (new \App\Services\AddiService())->widgetAssets();
        } catch (\Throwable $e) {
        }
    }
}
if (!function_exists('sonika_origin')) {
    function sonika_origin(): string
    {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = (string)($_SERVER['HTTP_HOST'] ?? 'sonika.com.co');
        if (!preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $host)) $host = 'sonika.com.co';
        return ($https ? 'https' : 'http') . '://' . $host;
    }
}
if (!function_exists('sonika_aff_sanitize_code')) {
    function sonika_aff_sanitize_code($code): string
    {
        $code = strtolower(trim((string)$code));
        $code = preg_replace('/[^a-z0-9\-]/', '', $code) ?? '';
        return substr($code, 0, 64);
    }
}
if (!function_exists('sonika_aff_ref')) {
    function sonika_aff_ref(): string
    {
        if (!empty($GLOBALS['__sonika_aff_ref'])) return sonika_aff_sanitize_code((string)$GLOBALS['__sonika_aff_ref']);
        $fromQuery = sonika_aff_sanitize_code((string)($_GET['ref'] ?? ''));
        if ($fromQuery !== '') return $fromQuery;
        return sonika_aff_sanitize_code((string)($_COOKIE['sonika_aff'] ?? ''));
    }
}
if (!function_exists('sonika_set_aff_ref')) {
    function sonika_set_aff_ref($code): void
    {
        $code = sonika_aff_sanitize_code($code);
        if ($code !== '') $GLOBALS['__sonika_aff_ref'] = $code;
    }
}
if (!function_exists('sonika_aff_set_cookie')) {
    function sonika_aff_set_cookie(string $code): void
    {
        $code = sonika_aff_sanitize_code($code);
        if ($code === '') return;
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        if (!headers_sent()) {
            @setcookie('sonika_aff', $code, [
                'expires' => time() + (\App\Services\AffiliateService::COOKIE_DAYS * 86400),
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }
        $_COOKIE['sonika_aff'] = $code;
    }
}
if (!function_exists('sonika_aff_capture')) {
    /** Si llega ?ref=..., valida el código, registra el clic y fija la cookie persistente. */
    function sonika_aff_capture(): void
    {
        static $done = false;
        if ($done) return;
        $done = true;
        $fromQuery = sonika_aff_sanitize_code((string)($_GET['ref'] ?? ''));
        if ($fromQuery === '') {
            $cookie = sonika_aff_sanitize_code((string)($_COOKIE['sonika_aff'] ?? ''));
            if ($cookie !== '') $GLOBALS['__sonika_aff_ref'] = $cookie;
            return;
        }
        $GLOBALS['__sonika_aff_ref'] = $fromQuery;
        $current = sonika_aff_sanitize_code((string)($_COOKIE['sonika_aff'] ?? ''));
        if ($current === $fromQuery) return;
        try {
            $r = (new \App\Services\AffiliateService())->recordClick($fromQuery, (string)($_COOKIE['wwi_seo_sid'] ?? ''));
            if (!$r) return;
            $canonical = sonika_aff_sanitize_code((string)($r['code'] ?? $fromQuery));
            if ($canonical === '') return;
            $GLOBALS['__sonika_aff_ref'] = $canonical;
            sonika_aff_set_cookie($canonical);
        } catch (\Throwable $e) {
        }
    }
}
if (!function_exists('sonika_product_href')) {
    function sonika_product_href(string $handle, string $ref = ''): string
    {
        $handle = trim($handle);
        if ($handle === '') return '/tienda';
        $ref = $ref !== '' ? sonika_aff_sanitize_code($ref) : sonika_aff_ref();
        return '/producto/' . rawurlencode($handle) . ($ref !== '' ? '?ref=' . rawurlencode($ref) : '');
    }
}
if (!function_exists('sonika_partner_auto_login')) {
    /** Restaura la sesión del Partner desde la cookie “recordarme”. */
    function sonika_partner_auto_login(): ?array
    {
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) \App\Core\Session::start();
        } catch (\Throwable $e) {
            return null;
        }
        if (!empty($_SESSION['ff_affiliate_id'])) return null;
        $cookie = (string)($_COOKIE['sonika_partner_remember'] ?? '');
        if ($cookie === '') return null;
        try {
            $aff = (new \App\Services\AffiliateService())->validateRememberToken($cookie);
            if (!$aff) {
                if (!headers_sent()) {
                    @setcookie('sonika_partner_remember', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
                }
                return null;
            }
            $_SESSION['ff_affiliate_id'] = (int)$aff['id'];
            $_SESSION['ff_started_at'] = time();
            \App\Core\Session::touch();
            return $aff;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
if (!function_exists('sonika_attribution_assets')) {
    function sonika_attribution_assets(): void
    {
        $data = [
            'ref' => sonika_aff_ref(),
            'cookie_days' => \App\Services\AffiliateService::COOKIE_DAYS,
            'ttl_days' => \App\Services\AffiliateService::TTL_DAYS,
        ];
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $js = (int)(@filemtime(ROOT_DIR . '/public/assets/js/sonika-attribution.js') ?: 1);
        echo '<script>window.SONIKA_AFF=' . $json . ';</script>';
        echo '<script src="/assets/js/sonika-attribution.js?v=' . $js . '" defer></script>';
    }
}
if (!function_exists('sonika_partner_share_context')) {
    function sonika_partner_share_context(): array
    {
        $out = [
            'logged_in' => false,
            'approved' => false,
            'name' => '',
            'status' => '',
            'code' => '',
            'ref' => sonika_aff_ref(),
            'code_url' => '',
            'rate' => 0.03,
            'cap' => 0.05,
            'streak_bonus' => 0.0,
            'streak_level' => 0,
            'outlet_rate' => \App\Services\AffiliateService::OUTLET_RATE,
            'points' => 0.0,
            'tier_min' => 0.0,
            'next_points' => 10.0,
            'next_rate' => 0.035,
            'weeks' => 0,
            'orders' => 0,
            'month_sales' => 0.0,
            'profile_category' => '',
            'store_path' => '/tienda',
            'register_url' => '/sonika-partners#registro',
            'login_url' => '/mi-antro',
            'panel_url' => '/mi-antro',
            'contract_url' => '/sonika-partners/contrato',
        ];
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent() && !empty($_COOKIE[session_name()])) {
            try { \App\Core\Session::start(); } catch (\Throwable $e) {
            }
        }
        $id = (int)($_SESSION['ff_affiliate_id'] ?? 0);
        if ($id <= 0) return $out;
        try {
            $svc = new \App\Services\AffiliateService();
            $aff = $svc->getById($id);
            if (!$aff) return $out;
            $name = trim((string)($aff['public_name'] ?? ''));
            if ($name === '') $name = trim((string)($aff['artist_name'] ?? ''));
            if ($name === '') $name = trim((string)($aff['full_name'] ?? ''));
            $calc = $svc->pointsForMonth($id);
            $approved = (string)($aff['status'] ?? '') === 'APPROVED';
            $slug = trim((string)($aff['slug'] ?? ''));
            $points = (float)($calc['points'] ?? 0);
            $tierMin = 0.0;
            $nextPoints = 0.0;
            $nextRate = (float)($calc['rate'] ?? 0.03);
            foreach (\App\Services\AffiliateService::POINT_TIERS as $tier) {
                if ($points >= (float)$tier[0]) $tierMin = (float)$tier[0];
                if ((float)$tier[0] > $points) {
                    $nextPoints = (float)$tier[0];
                    $nextRate = (float)$tier[2];
                    break;
                }
            }
            $out['logged_in'] = true;
            $out['approved'] = $approved;
            $out['name'] = mb_substr($name, 0, 80);
            $out['status'] = (string)($aff['status'] ?? '');
            $out['code'] = $approved ? (string)$svc->ensureLink($id) : '';
            $out['code_url'] = $out['code'] !== '' ? '/r/' . rawurlencode($out['code']) : '';
            $out['ref'] = sonika_aff_ref();
            $out['rate'] = (float)($calc['rate'] ?? 0.03);
            $out['cap'] = (float)($calc['cap'] ?? 0.05);
            $out['streak_bonus'] = (float)($calc['streak_bonus'] ?? 0);
            $out['streak_level'] = (int)($calc['streak_level'] ?? 0);
            $out['points'] = $points;
            $out['tier_min'] = $tierMin;
            $out['next_points'] = $nextPoints;
            $out['next_rate'] = $nextRate;
            $out['weeks'] = (int)($calc['weeks'] ?? 0);
            $out['orders'] = (int)($calc['orders'] ?? 0);
            $out['month_sales'] = (float)($calc['sales'] ?? 0);
            $out['profile_category'] = (string)($aff['profile_category'] ?? '');
            $out['store_path'] = (string)$svc->publicPath($aff);
            $out['panel_url'] = $slug !== '' ? '/artista/' . rawurlencode($slug) . '/panel' : '/mi-antro';
        } catch (\Throwable $e) {
        }
        return $out;
    }
}
if (!function_exists('sonika_share_settings')) {
    function sonika_share_settings(): array
    {
        try {
            return (new \App\Services\ShareSettingsService())->get();
        } catch (\Throwable $e) {
            return (new \App\Services\ShareSettingsService())->defaults();
        }
    }
}
if (!function_exists('sonika_share_enabled')) {
    function sonika_share_enabled(string $context = 'cart'): bool
    {
        try {
            return (new \App\Services\ShareSettingsService())->enabledFor($context);
        } catch (\Throwable $e) {
            return true;
        }
    }
}
if (!function_exists('sonika_share_assets')) {
    function sonika_share_assets(): void
    {
        $ctx = sonika_partner_share_context();
        $svc = new \App\Services\ShareSettingsService();
        $share = $svc->get();
        $json = json_encode($ctx, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $shareJson = json_encode($share, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $css = (int)(@filemtime(ROOT_DIR . '/public/assets/themes/sonika/share.css') ?: 1);
        $js = (int)(@filemtime(ROOT_DIR . '/public/assets/js/sonika-share.js') ?: 1);
        $vars = [];
        foreach ($svc->cssVars() as $key => $value) $vars[] = $key . ':' . $value;
        echo '<link rel="stylesheet" href="/assets/themes/sonika/share.css?v=' . $css . '" />';
        if ($vars) echo '<style>:root{' . implode(';', $vars) . '}</style>';
        echo '<script>window.SONIKA_PARTNER=' . $json . ';window.SONIKA_SHARE=' . $shareJson . ';</script>';
        echo '<script src="/assets/js/sonika-share.js?v=' . $js . '" defer></script>';
    }
}
if (!function_exists('sonika_site_footer')) {
    function sonika_site_footer(): void {
        ?>
</main>
<?php echo \App\Widgets\WidgetRegistry::render('sonika-footer', []); ?>
<?php if (empty($GLOBALS['__sonika_quote_page'])): ?>
<?= \App\Widgets\WidgetRegistry::render('quote', ['storage_key' => 'sonika_quote', 'cart_key' => 'sonika_cart', 'checkout_url' => '/checkout']) ?>
<?php endif; ?>
<?php
try {
    $__bnRecovery = (new \App\Services\BannerService())->recoveryConfigs(2);
    foreach ($__bnRecovery as $__bnCfg) echo \App\Widgets\WidgetRegistry::render('banner', $__bnCfg);
} catch (\Throwable $e) {
}
?>
<?= CookieConsentService::render() ?>
<script><?= sonika_js() ?>;;(function(){var q=/[?&]tv=1\b/.test(location.search),ua=/SmartTV|Tizen|Web0S|webOS|NetCast|BRAVIA|AFT[A-Z]|AppleTV|Roku|HbbTV|VIDAA/i.test(navigator.userAgent||'');if(!q&&!ua)return;document.documentElement.classList.add('tv-mode');var els=[].slice.call(document.querySelectorAll('a,button,input,select,textarea,[tabindex]')).filter(function(el){return !el.disabled&&el.offsetParent!==null});document.addEventListener('keydown',function(e){if(['ArrowUp','ArrowDown','ArrowLeft','ArrowRight'].indexOf(e.key)<0)return;var a=document.activeElement;if(!a||a===document.body){if(els[0]){els[0].focus();e.preventDefault()}return}var r=a.getBoundingClientRect(),best=null,bd=1e9;els.forEach(function(el){if(el===a)return;var b=el.getBoundingClientRect(),dx=b.left-r.left,dy=b.top-r.top,ok=(e.key==='ArrowRight'&&dx>8)||(e.key==='ArrowLeft'&&dx<-8)||(e.key==='ArrowDown'&&dy>8)||(e.key==='ArrowUp'&&dy<-8);if(!ok)return;var d=Math.abs(dx)+Math.abs(dy);if(d<bd){bd=d;best=el}});if(best){best.focus();e.preventDefault()}})})();;;(function(){function upd(){var t=('ontouchstart' in window)||(navigator.maxTouchPoints>0)||(window.matchMedia&&matchMedia('(pointer:coarse)').matches);var c=t||window.innerWidth<=1024;document.documentElement.classList.toggle('wwi-compact',!!c);}upd();window.addEventListener('resize',upd);window.addEventListener('orientationchange',upd);})();;;;(function(){try{if(window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches)return;var S=64,cv=document.createElement('canvas');cv.width=cv.height=S;var cx=cv.getContext('2d');if(!cx)return;var lk=document.querySelector('link[rel~="icon"]');if(!lk){lk=document.createElement('link');lk.rel='icon';document.head.appendChild(lk);}function draw(t){cx.clearRect(0,0,S,S);cx.fillStyle='#F20D16';var n=5,bw=8,gp=3,tot=n*bw+(n-1)*gp,x0=(S-tot)/2,base=S-8;for(var i=0;i<n;i++){var h=13+Math.round((Math.sin(t+i*1.3)*0.5+0.5)*31);var x=x0+i*(bw+gp),y=base-h,r=bw/2;cx.beginPath();cx.moveTo(x,y+r);cx.arcTo(x,y,x+r,y,r);cx.lineTo(x+bw-r,y);cx.arcTo(x+bw,y,x+bw,y+r,r);cx.lineTo(x+bw,base);cx.lineTo(x,base);cx.closePath();cx.fill();}lk.type='image/png';lk.href=cv.toDataURL('image/png');}var t=0,tm=null;function tick(){t+=0.45;draw(t);}function start(){if(tm)return;tm=setInterval(tick,260);}function stop(){if(tm){clearInterval(tm);tm=null;}}document.addEventListener('visibilitychange',function(){document.hidden?stop():start();});if(window.matchMedia){var mq=matchMedia('(prefers-reduced-motion: reduce)');if(mq.addEventListener)mq.addEventListener('change',function(e){e.matches?stop():start();});}draw(0);start();}catch(e){}})();</script>
<?php if (wwi_setting('tia_embed_enabled', '1') !== '0'): ?>
<?php
$__tiaWa = preg_replace('/\D+/', '', (string)wwi_setting('pqrf_whatsapp', '3045650333'));
$__tiaWaUrl = $__tiaWa !== '' ? 'https://wa.me/' . (strlen($__tiaWa) === 10 ? '57' : '') . $__tiaWa : '';
$__tiaWelcome = 'Hola, soy TIA, el asistente virtual de SONIKA. Puedo ayudarte con productos, envíos, pagos, garantías y soporte. Si necesitas una persona, escríbenos por WhatsApp o usa el formulario.';
?>
<script src="/assets/js/tia-embed.js?v=<?= (int)(@filemtime(ROOT_DIR . '/public/assets/js/tia-embed.js') ?: 1) ?>"
        data-tia-api="/api/v1/public/tia/chat"
        data-tia-site="sonika"
        data-tia-mode="both"
        data-tia-mount="[data-tia-support]"
        data-tia-disable-on="/soporte"
        data-tia-color="#F20D16"
        data-tia-equalize-interval="9000"
        data-tia-title="<?= htmlspecialchars('TIA · Asistente SONIKA', ENT_QUOTES, 'UTF-8') ?>"
        data-tia-welcome="<?= htmlspecialchars($__tiaWelcome, ENT_QUOTES, 'UTF-8') ?>"
        data-tia-whatsapp="<?= htmlspecialchars($__tiaWaUrl, ENT_QUOTES, 'UTF-8') ?>"
        data-tia-form="/soporte#contacto"
        data-tia-pqrf="/pqrf"
        defer></script>
<?php endif; ?>
<?php $__socialProof = (new \App\Services\SocialProofService())->clientConfig(); ?>
<?php if (!empty($__socialProof['enabled'])): ?>
<script>window.SONIKA_SOCIAL_PROOF=<?= json_encode($__socialProof, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="/assets/js/social-proof.js?v=<?= (int)(@filemtime(ROOT_DIR . '/public/assets/js/social-proof.js') ?: 1) ?>" defer></script>
<?php endif; ?>
<script src="/assets/js/impact-note.js?v=<?= (int)(@filemtime(ROOT_DIR . '/public/assets/js/impact-note.js') ?: 1) ?>" defer></script>
<script src="/assets/js/product-history.js?v=<?= (int)(@filemtime(ROOT_DIR . '/public/assets/js/product-history.js') ?: 1) ?>" defer></script>
<script src="/assets/js/sonika-dialogs.js?v=<?= (int)(@filemtime(ROOT_DIR . '/public/assets/js/sonika-dialogs.js') ?: 1) ?>" defer></script>
<script src="/assets/js/stock-notifications.js?v=<?= (int)(@filemtime(ROOT_DIR . '/public/assets/js/stock-notifications.js') ?: 1) ?>" defer></script>
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
        /* SONIKA Announcement Banner — Franja compacta */
.topbar{--announcement-muted:#6c6877;--announcement-accent:var(--brand,#F20D16);--announcement-accent-secondary:#6b35b8;--announcement-border:rgba(35,25,60,.07);position:relative;isolation:isolate;width:100%;min-width:0;box-sizing:border-box;margin:0;padding:0;color:inherit;background:linear-gradient(105deg,#fff 0%,#faf8ff 55%,#f2ecff 100%);border:0;border-bottom:1px solid var(--announcement-border);border-radius:0;box-shadow:none;overflow:hidden}
.topbar::before{content:"";position:absolute;inset:0;z-index:0;pointer-events:none;background:radial-gradient(120% 170% at 100% 50%,rgba(107,53,184,.10),rgba(107,53,184,0) 62%),linear-gradient(100deg,rgba(107,53,184,0) 42%,rgba(107,53,184,.05) 66%,rgba(107,53,184,.10) 100%)}
.topbar__inner{position:relative;z-index:1;display:grid;grid-template-columns:minmax(0,1fr) auto;align-items:center;gap:16px;min-height:46px;padding:0 20px 0 clamp(215px,18vw,250px);line-height:1.2;font-family:inherit}
.topbar:not(.topbar--has-media) .topbar__inner{padding-left:20px}
.topbar__media{position:absolute;left:0;top:0;bottom:0;width:clamp(200px,17vw,235px);height:100%;overflow:hidden;pointer-events:none}
.topbar__media-img{position:absolute;left:-10px;top:50%;display:block;width:100%;height:clamp(180px,15vw,210px);object-fit:cover;object-position:left center;mix-blend-mode:multiply;opacity:0;backface-visibility:hidden;transform-origin:30% 55%;will-change:opacity,transform;transform:translate(-10px,-50%) scale(1.035);transition:opacity 480ms ease-in-out,transform 760ms ease-in-out;mask-image:linear-gradient(to right,rgba(0,0,0,1) 0%,rgba(0,0,0,.95) 45%,rgba(0,0,0,.55) 70%,rgba(0,0,0,0) 100%);-webkit-mask-image:linear-gradient(to right,rgba(0,0,0,1) 0%,rgba(0,0,0,.95) 45%,rgba(0,0,0,.55) 70%,rgba(0,0,0,0) 100%)}
.topbar__media-img.is-active{opacity:.98;transform:translate(0,-50%) scale(1);animation:sonikaInstrumentFloat 10s cubic-bezier(.22,.61,.36,1) both}
@keyframes sonikaInstrumentFloat{0%{scale:1;translate:0 0}52%{scale:1.028;translate:-4px -2px}100%{scale:1.012;translate:0 0}}
.topbar__media::after{content:"";position:absolute;inset:0;pointer-events:none;background:linear-gradient(105deg,transparent 34%,rgba(255,255,255,.62) 47%,rgba(255,255,255,.18) 55%,transparent 67%);translate:-140% 0;opacity:0;animation:sonikaInstrumentSheen 10s ease-in-out infinite}
@keyframes sonikaInstrumentSheen{0%,55%{translate:-140% 0;opacity:0}64%{opacity:.62}82%,100%{translate:140% 0;opacity:0}}
.topbar__media-img[data-focus="top"]{top:0;transform:translate(-10px,-11%) scale(1.035)}
.topbar__media-img[data-focus="top"].is-active{transform:translate(0,-11%) scale(1)}
.topbar__media-img[data-focus="head"]{top:clamp(-40px,-4vw,-14px);object-position:left top;transform:translateX(-10px) scale(1.035)}
.topbar__media-img[data-focus="head"].is-active{transform:translateX(0) scale(1)}
.topbar__benefit{display:none}
.topbar__msg{position:relative;z-index:1;grid-column:1;min-width:0;display:flex;align-items:center;justify-content:center;color:inherit;font-size:13px;font-weight:600;line-height:1.2;text-align:center;overflow-wrap:anywhere}
.topbar__msg .wwi-bn{display:flex;align-items:center;justify-content:center;gap:6px;min-width:0;max-width:100%;padding:0;background:none;color:inherit;font:inherit;font-size:inherit;font-weight:inherit;font-style:normal;line-height:inherit;letter-spacing:-.01em;text-align:center}
.topbar__msg .wwi-bn__track{display:block;flex:0 1 auto;min-width:0;max-width:100%}
.topbar__msg .wwi-bn__item{display:none}
.topbar__msg .wwi-bn__item.is-on{display:block;max-width:100%;text-align:center}
.topbar__msg .wwi-bn__ic,.topbar__msg .wwi-bn__im{display:none}
.topbar__msg .wwi-bn__tx{display:inline;font-weight:600;font-weight:max(500,calc(var(--wwi-bn-fw,800) - 200));font-style:var(--wwi-bn-fst,normal);text-align:center;overflow-wrap:anywhere}
.topbar__msg .wwi-bn__hl{color:var(--announcement-accent);font-weight:700;background:linear-gradient(transparent 64%,rgba(242,13,22,.10) 64%);padding:0 1px}
.topbar__msg .wwi-bn__cta{display:none}
.topbar__msg .wwi-bn__dots{display:inline-flex;align-items:center;justify-content:center;gap:3px;flex:0 0 auto;margin:0 0 0 4px;padding:0}
.topbar__msg .wwi-bn__dot{width:3px;height:3px;border-radius:999px;opacity:.28;background:var(--announcement-accent-secondary);border:0;padding:0;cursor:pointer;transition:width .2s ease,opacity .2s ease}
.topbar__msg .wwi-bn__dot.is-on{width:9px;opacity:.75}
.topbar__msg .wwi-bn__dot:focus-visible{outline:2px solid var(--announcement-accent-secondary);outline-offset:2px}
.topbar__msg .wwi-bn__cd{display:inline-flex;align-items:center;gap:4px;margin-left:4px;padding:1px 5px;border-radius:999px;background:rgba(107,53,184,.07);color:var(--announcement-accent-secondary);font-size:.85em;font-weight:800;font-variant-numeric:tabular-nums;white-space:nowrap}
.topbar__act{position:relative;z-index:1;grid-column:2;justify-self:end;display:inline-flex;align-items:center;justify-content:center;gap:6px;min-width:0;padding:7px 13px;border:1px solid rgba(35,25,60,.16);border-radius:8px;background:transparent;color:inherit;font-size:11.5px;font-weight:650;line-height:1;text-align:center;text-decoration:none;white-space:nowrap;transition:background-color .18s ease,color .18s ease,border-color .18s ease,transform .18s ease}
.topbar__act:hover{background:var(--announcement-accent);border-color:var(--announcement-accent);color:#fff}
.topbar__act:active{transform:translateY(1px)}
.topbar__act:focus-visible{outline:2px solid var(--announcement-accent-secondary);outline-offset:2px;border-radius:8px}
@media(max-width:900px){.topbar__inner{gap:12px;min-height:46px;padding:0 14px 0 clamp(172px,17vw,190px)}.topbar__msg{font-size:12px}.topbar__act{padding:6px 12px;font-size:10.5px}.topbar__media{width:clamp(160px,16vw,180px)}.topbar__media-img{height:clamp(150px,15vw,180px)}}
@media(max-width:767px){.topbar__inner{grid-template-columns:minmax(0,1fr);grid-template-rows:auto auto;gap:1px;min-height:48px;padding:0 12px}.topbar__media{width:86px}.topbar__media-img{left:-10px;width:100%;height:132px;object-fit:cover;object-position:left center}.topbar__msg{grid-column:1;grid-row:1;justify-self:center;width:100%;max-width:242px;font-size:11px;line-height:1.3}.topbar__act{grid-column:1;grid-row:2;justify-self:center;justify-content:center;padding:3px 9px;font-size:9px;gap:4px}.topbar__msg .wwi-bn__dots{display:none}}
@media(prefers-reduced-motion:reduce){.topbar,.topbar *{animation:none!important;transition:none!important;scroll-behavior:auto!important}.topbar__act{transition:none}}
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
        section.pad{padding-top:48px;padding-bottom:48px}
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
        .card{border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;background:#fff;display:flex;flex-direction:column;height:100%;transition:transform .2s,box-shadow .2s,border-color .2s}
        .card:hover{transform:translateY(-4px);box-shadow:0 16px 36px rgba(17,24,39,.10)}
        .thumb{position:relative;aspect-ratio:1;background:#F3F4F6;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .thumb img{width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply;transition:transform .4s}
        .card:hover .thumb img{transform:scale(1.06)}
        .badge{position:absolute;top:10px;left:10px;background:var(--ok);color:#fff;font-size:10.5px;font-weight:700;padding:4px 9px;border-radius:20px}
        .badge.out{background:#6B7280}.badge.off{background:var(--accent);left:auto;right:10px}.badge.free{background:#0d9f72;top:36px}.badge.taxfree{background:#7c3aed;left:auto;right:10px;top:36px}
        .card-body{padding:14px;display:flex;flex-direction:column;gap:5px;flex:1;min-width:0}
        .vendor{font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--brand);text-transform:uppercase}
        .ptitle{font-size:13.5px;font-weight:600;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:38px}
        .pricebox{display:flex;align-items:baseline;gap:8px;margin-top:1px;min-height:22px}
        .price{font-size:17px;font-weight:800}.cmp{font-size:12.5px;color:#9CA3AF;text-decoration:line-through}
        .card-insights{display:flex;flex-direction:column;gap:4px;margin:2px 0 1px;min-width:0}
        .pi-topline{display:flex;align-items:center;gap:6px;flex-wrap:wrap;min-width:0}
        .pi-badge{display:inline-flex;align-items:center;border:1px solid #fed7aa;border-radius:5px;background:#fff7ed;color:#9a3412;padding:2px 5px;font-size:8px;font-weight:800;line-height:1.2;white-space:nowrap}
        .pi-rating{display:inline-flex;align-items:center;gap:3px;min-width:0;color:#6b7280;font-size:10px;white-space:nowrap}
        .pi-rating b{color:#111;font-size:11px;font-weight:800}
        .pi-rating>span:last-child{color:#6b7280;font-size:9.5px}
        .pi-stars{display:inline-flex;align-items:center;gap:0;letter-spacing:0;line-height:1}
        .pi-star{display:inline-flex;align-items:center;justify-content:center;width:12px;height:13px;flex:0 0 12px;font-size:13px;line-height:1;color:#d1d5db;text-align:center}
        .pi-star.is-full{color:#F5A623}
        .pi-star.is-half{background:linear-gradient(90deg,#F5A623 50%,#d1d5db 50%);-webkit-background-clip:text;background-clip:text;color:transparent}
        .pi-sales{color:#6b7280;font-size:9.4px;line-height:1.3}
        .pi-shipping{border:1px solid #bbf7d0;border-radius:7px;background:#f0fdf4;padding:5px 7px;margin-top:1px;color:#047857;font-size:9.2px;line-height:1.3}
        .pi-shipping b{display:block;color:#065f46;font-size:9.5px;line-height:1.3}
        .pi-shipping span{display:block;color:#047857;font-weight:700;line-height:1.3}
        .pi-shipping small{display:block;color:#47735f;font-size:8px;line-height:1.3;margin-top:2px}
        .card-insights .pi-shipping{display:flex;align-items:center;gap:5px;padding:4px 6px;border-radius:5px;font-size:8.5px;line-height:1.2}
        .card-insights .pi-shipping b{display:inline;font-size:8.5px;line-height:1.2;margin:0}
        .card-insights .pi-shipping span{display:inline;font-size:8.5px;line-height:1.2;font-weight:700;margin:0}
        .pdp-insights{margin:9px 0 15px;max-width:620px}
        .pdp-insights .pi-topline{gap:9px}
        .pdp-insights .pi-badge{font-size:9px;padding:3px 7px}
        .pdp-insights .pi-rating{font-size:12px;gap:5px}
        .pdp-insights .pi-rating b{font-size:15px}
        .pdp-insights .pi-star{font-size:17px}
        .pdp-insights .pi-sales{font-size:11px}
        .pdp-insights .pi-shipping{padding:9px 11px;font-size:10.5px}
        .pdp-insights .pi-shipping b{font-size:11px}
        .pdp-insights .pi-shipping small{font-size:9px}
        .add{margin-top:auto;border:none;background:var(--ink);color:#fff;border-radius:10px;padding:10px;font-weight:700;cursor:pointer;font-size:13px;font-family:inherit;flex:0 0 auto}
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
        .qty button{width:44px;height:46px;border:none;background:#fff;font-size:18px;cursor:pointer}
        .qty input{width:46px;height:46px;border:none;text-align:center;font-size:15px;font-family:inherit}
        .btn-buy{background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;border:none;border-radius:12px;padding:14px 34px;font-weight:700;font-size:15px;cursor:pointer;box-shadow:0 10px 26px rgba(242,13,22,.3)}
        .impact-note{display:flex;align-items:flex-start;gap:8px;max-width:620px;margin:0 0 16px;padding:9px 11px;border:1px solid #eceff1;border-left:2px solid #f3c4c7;border-radius:10px;background:#fcfcfc;color:#66717f;font-size:11.5px;line-height:1.45}
        .impact-note svg{flex:0 0 auto;width:15px;height:15px;margin-top:1px;color:var(--brand)}
        .impact-note__body{min-width:0}
        .impact-note__label{display:block;margin-bottom:2px;color:#9a4a51;font-size:8.5px;font-weight:800;letter-spacing:.12em;text-transform:uppercase}
        .impact-note__rotator{display:grid;min-height:2.9em;align-items:start}
        .impact-note__phrase{grid-area:1/1;opacity:0;transform:translateY(2px);transition:opacity .45s ease,transform .45s ease;pointer-events:none}
        .impact-note__phrase.is-active{opacity:1;transform:none;pointer-events:auto}
        .impact-note b{color:#303b49;font-weight:700}
        .skpay .impact-note{max-width:none;margin:0 0 18px}
        .pdp-actions{display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:8px 0 20px}
        .pdp-actions .btn-buy{min-width:190px;white-space:nowrap;transition:background .2s ease,box-shadow .2s ease}
        .pdp-actions .btn-buy.is-added{background:linear-gradient(135deg,#0d9f72,#087a57);box-shadow:0 10px 26px rgba(13,159,114,.24)}
        .pdp-actions .btn-buy.is-go-cart{background:linear-gradient(135deg,#101d3a,#1d3b6e);box-shadow:0 10px 26px rgba(16,29,58,.22)}
        .pdp-actions .btn-buy:disabled{cursor:wait;opacity:.92}
        .pdp-add-more{border:0;background:transparent;color:#718096;font:600 11.5px/1.3 inherit;text-decoration:underline;text-underline-offset:2px;cursor:pointer;padding:4px 2px}
        .pdp-add-more:hover{color:#101d3a}
        .pdp-buy-status{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
        @media(max-width:640px){.pdp-actions{gap:8px}.pdp-actions .btn-buy{flex:1 1 180px;padding-left:16px;padding-right:16px}.pdp-add-more{flex-basis:100%;text-align:left}}
        .pdp-desc{margin-top:34px;padding-top:26px;border-top:1px solid var(--line)}
        .pdp-desc h3{font-family:var(--font-display);font-size:18px;margin-bottom:12px}
        .pdp-desc p{margin:10px 0;line-height:1.7;color:#374151}
        .pdp-desc ul{margin:10px 0 10px 20px;line-height:1.7;color:#374151}
        /* PDP · área segura y proporciones móviles */
        .pdp{box-sizing:border-box;padding:28px 22px 10px}
        @media(max-width:640px){
          .pdp{grid-template-columns:1fr;gap:18px;padding:14px 16px 8px}
          .pdp-gallery{border-radius:16px}
          .pdp-info{position:relative;padding:18px 16px 16px;background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 16px 38px rgba(16,29,58,.06)}
          .pdp h1{font-size:clamp(21px,6vw,27px);line-height:1.2;letter-spacing:-.01em;margin:9px 0 12px;overflow-wrap:anywhere;text-wrap:balance}
          .pdp .price{font-size:clamp(23px,6.6vw,28px);line-height:1.1}
          .pricebox{flex-wrap:wrap;gap:6px 8px;min-height:0}
          .cmp{font-size:12px}
          .pdp-vendor{font-size:11px}
          .pdp .meta{gap:7px;margin:12px 0 16px}
          .pdp .meta .chip{font-size:11px;padding:5px 8px}
          .pdp-actions{gap:9px;margin:6px 0 16px}
          .pdp-actions .btn-buy,.pdp-actions .cta.sec{flex:1 1 100%;width:100%;min-width:0;min-height:44px;justify-content:center}
          .pdp-actions .qty{margin-right:0}
          .pdp-add-more{flex-basis:100%;text-align:center}
          .pdp-desc{margin-top:24px;padding-top:20px}
          .pdp-desc h3{font-size:17px}
          .pdp-desc p,.pdp-desc ul{font-size:14.5px;line-height:1.65}
          .pdp-desc ul{padding-left:18px;margin-left:0}
        }
        /* SONIKA Product Content System - descripciones editoriales */
        .pdp-desc{--pc-ink:#1f2937;--pc-muted:#667085;--pc-line:#e5e7eb;--pc-soft:#f7f8fa;--pc-brand:#F20D16;--pc-radius:16px}
        .pdp-desc__head{display:flex;align-items:baseline;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:18px}
        .pdp-desc__head h2{font-family:var(--font-display);font-size:26px;line-height:1.2;letter-spacing:-.02em;margin:0;color:#111}
        .pdp-desc__meta{font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;color:var(--pc-muted);background:var(--pc-soft);border:1px solid var(--pc-line);border-radius:999px;padding:5px 10px;white-space:nowrap}
        .pdp-content{font-family:var(--font-body,Sora),sans-serif;color:var(--pc-ink);font-size:16px;line-height:1.7;max-width:980px;overflow-wrap:anywhere}
        .pdp-content *{box-sizing:border-box}
        .pdp-content p{margin:0 0 14px;color:var(--pc-ink)}
        .pdp-content h2,.pdp-content h3,.pdp-content h4{font-family:var(--font-display);color:#111;letter-spacing:-.015em;margin:0 0 12px;line-height:1.25}
        .pdp-content h2{font-size:26px}
        .pdp-content h3{font-size:19px}
        .pdp-content h4{font-size:16.5px}
        .pdp-content ul,.pdp-content ol{margin:0 0 16px;padding-left:1.25em}
        .pdp-content li{margin:5px 0}
        .pdp-content a{color:var(--pc-brand);text-decoration:underline;text-underline-offset:2px}
        .pdp-content img{max-width:100%;height:auto}
        .pdp-content blockquote{margin:18px 0;padding:12px 16px;border-left:3px solid var(--pc-brand);background:var(--pc-soft);border-radius:0 10px 10px 0;color:#475467}
        .pdp-content code{font-size:.9em;background:var(--pc-soft);border:1px solid var(--pc-line);border-radius:6px;padding:1px 5px}
        .pdp-intro{margin:0 0 30px}
        .pdp-lead__title{font-family:var(--font-display);font-size:clamp(20px,2.4vw,25px);line-height:1.25;margin:0 0 10px}
        .pdp-lead p{font-size:17px;line-height:1.72;color:#374151}
        .pdp-lead p:last-child{margin-bottom:0}
        .pdp-feature{display:grid;grid-template-columns:minmax(0,.92fr) minmax(0,1.08fr);gap:30px;align-items:center;margin:0 0 34px;padding:22px;border:1px solid var(--pc-line);border-radius:20px;background:linear-gradient(135deg,#fff,#f8fafc)}
        .pdp-feature__copy{max-width:52ch}
        .pdp-feature__copy h2{font-size:clamp(22px,2.5vw,28px);margin-bottom:10px}
        .pdp-feature__copy .pdp-lead__title{font-size:clamp(17px,1.8vw,20px);color:#475467}
        .pdp-eyebrow{display:inline-flex;align-items:center;gap:6px;font-size:10.5px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--pc-brand);margin-bottom:8px}
        .pdp-feature__media{min-width:0}
        .pdp-video{position:relative;display:block;width:100%;aspect-ratio:16/9;background:#0b1020;border-radius:14px;overflow:hidden;isolation:isolate}
        .pdp-video__poster{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:.92}
        .pdp-video::after{content:"";position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 40%,rgba(0,0,0,.55));pointer-events:none}
        .pdp-video__play{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:2;display:grid;place-items:center;width:64px;height:64px;border:0;border-radius:50%;background:linear-gradient(135deg,#F20D16,#C70A12);color:#fff;cursor:pointer;box-shadow:0 14px 34px rgba(242,13,22,.38);transition:transform .2s ease,box-shadow .2s ease}
        .pdp-video__play:hover,.pdp-video__play:focus-visible{transform:translate(-50%,-50%) scale(1.07);box-shadow:0 18px 42px rgba(242,13,22,.48);outline:3px solid rgba(242,13,22,.35);outline-offset:3px}
        .pdp-video__play svg{width:24px;height:24px;margin-left:2px}
        .pdp-video__fallback{position:absolute;right:12px;bottom:10px;z-index:2;color:#fff;font-size:11.5px;font-weight:700;text-decoration:none;background:rgba(0,0,0,.45);border:1px solid rgba(255,255,255,.28);border-radius:999px;padding:5px 10px;backdrop-filter:blur(6px)}
        .pdp-video iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
        .pdp-video.is-playing::after,.pdp-video.is-playing .pdp-video__poster,.pdp-video.is-playing .pdp-video__play,.pdp-video.is-playing .pdp-video__fallback{display:none}
        .pdp-video--file{aspect-ratio:16/9;background:#0b1020}
        .pdp-video--file video{width:100%;height:100%;object-fit:contain;display:block}
        .pdp-feature__image{margin:0;border-radius:14px;overflow:hidden;background:#f7f8fa;border:1px solid var(--pc-line)}
        .pdp-feature__image img{display:block;width:100%;height:auto;max-height:460px;object-fit:contain}
        .pdp-feature__image figcaption,.pdp-media-gallery figcaption{font-size:12px;line-height:1.5;color:var(--pc-muted);padding:8px 10px}
        .pdp-section{margin:0 0 34px}
        .pdp-section > h2{font-size:26px;margin-bottom:16px;padding-bottom:10px;border-bottom:1px solid var(--pc-line)}
        .pdp-checks{list-style:none;margin:18px 0 0;padding:0;display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px 18px}
        .pdp-checks li{position:relative;margin:0;padding:0 0 0 26px;font-size:15px;line-height:1.55;color:#344054}
        .pdp-checks li::before{content:"\2713";position:absolute;left:0;top:0;display:grid;place-items:center;width:18px;height:18px;border-radius:50%;background:#e8f7ef;color:#0d9f72;font-size:11px;font-weight:900}
        .pdp-checks--compact{grid-template-columns:1fr;gap:4px}
        .pdp-checks--compact li{font-size:14.5px}
        .pdp-checks--grid{grid-template-columns:repeat(2,minmax(0,1fr))}
        .pdp-specs .pdp-specs__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:0 24px;margin:0;border-top:1px solid var(--pc-line)}
        .pdp-specs__row{display:grid;grid-template-columns:minmax(120px,.8fr) minmax(0,1.2fr);gap:12px;padding:10px 0;border-bottom:1px solid var(--pc-line);break-inside:avoid}
        .pdp-specs__row dt{margin:0;font-size:12px;font-weight:800;letter-spacing:.05em;text-transform:uppercase;color:var(--pc-muted);line-height:1.45}
        .pdp-specs__row dd{margin:0;font-size:14.5px;line-height:1.55;color:#1f2937}
        .pdp-specs .pdp-checks{margin-top:14px}
        .pdp-table-wrap{width:100%;overflow-x:auto;margin:14px 0 0;border:1px solid var(--pc-line);border-radius:12px}
        .pdp-table{width:100%;border-collapse:collapse;font-size:14px;line-height:1.55;min-width:420px}
        .pdp-table th,.pdp-table td{padding:10px 12px;border-bottom:1px solid var(--pc-line);text-align:left;vertical-align:top}
        .pdp-table th{background:var(--pc-soft);font-size:11.5px;letter-spacing:.05em;text-transform:uppercase;color:var(--pc-muted)}
        .pdp-table tr:last-child td,.pdp-table tr:last-child th{border-bottom:0}
        .pdp-media-gallery__grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
        .pdp-media-gallery figure{margin:0;border:1px solid var(--pc-line);border-radius:12px;overflow:hidden;background:#f7f8fa}
        .pdp-media-gallery img{display:block;width:100%;aspect-ratio:1;object-fit:contain;background:#f7f8fa}
        .pdp-details{max-width:68ch}
        .pdp-more{margin:8px 0 0;border:1px solid var(--pc-line);border-radius:14px;background:#fff}
        .pdp-more > summary{display:flex;align-items:center;justify-content:space-between;gap:12px;cursor:pointer;list-style:none;padding:14px 16px;font-size:14px;font-weight:800;color:#101d3a;border-radius:14px}
        .pdp-more > summary::-webkit-details-marker{display:none}
        .pdp-more > summary::after{content:"+";display:grid;place-items:center;width:24px;height:24px;border-radius:50%;background:var(--pc-soft);border:1px solid var(--pc-line);font-size:16px;line-height:1;transition:transform .2s ease}
        .pdp-more[open] > summary::after{content:"\2212";transform:rotate(180deg)}
        .pdp-more > summary:hover{background:var(--pc-soft)}
        .pdp-more__body{padding:0 16px 16px;max-width:68ch}
        .pdp-more__body > h2:first-child,.pdp-more__body > h3:first-child{margin-top:4px}
        .pdp-more-videos__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}
        .pdp-empty{padding:26px;border:1px dashed var(--pc-line);border-radius:16px;background:var(--pc-soft);max-width:68ch}
        .pdp-empty h2{font-size:22px;margin-bottom:8px}
        .pdp-empty p{color:var(--pc-muted);margin-bottom:14px}
        .pdp-empty__cta{display:inline-flex;align-items:center;gap:8px;background:#101d3a;color:#fff;border-radius:10px;padding:10px 14px;font-weight:700;font-size:13.5px;text-decoration:none}
        @media(max-width:820px){
          .pdp-content{font-size:15.5px;line-height:1.68}
          .pdp-feature{grid-template-columns:1fr;gap:18px;padding:16px;border-radius:16px}
          .pdp-feature__media{order:-1}
          .pdp-feature__copy{max-width:none}
          .pdp-specs .pdp-specs__grid{grid-template-columns:1fr}
          .pdp-media-gallery__grid{grid-template-columns:repeat(2,minmax(0,1fr))}
          .pdp-checks--grid{grid-template-columns:1fr}
        }
        @media(max-width:640px){
          .pdp-desc__head{align-items:flex-start;gap:8px}
          .pdp-desc__head h2{font-size:22px}
          .pdp-desc__meta{font-size:10.5px;padding:4px 8px}
          .pdp-content{font-size:15px;line-height:1.65}
          .pdp-content h2{font-size:22px}
          .pdp-content h3{font-size:17.5px}
          .pdp-content h4{font-size:15.5px}
          .pdp-lead p{font-size:15.5px}
          .pdp-feature{padding:12px;gap:14px}
          .pdp-feature__copy h2{font-size:21px}
          .pdp-video__play{width:54px;height:54px}
          .pdp-video__play svg{width:20px;height:20px}
          .pdp-section{margin-bottom:26px}
          .pdp-section > h2{font-size:21px;margin-bottom:12px}
          .pdp-checks{grid-template-columns:1fr;gap:6px}
          .pdp-checks li{font-size:14.5px}
          .pdp-specs__row{grid-template-columns:1fr;gap:2px;padding:9px 0}
          .pdp-specs__row dt{font-size:11px}
          .pdp-specs__row dd{font-size:14px}
          .pdp-table{font-size:13px;min-width:360px}
          .pdp-table th,.pdp-table td{padding:8px 9px}
          .pdp-media-gallery__grid{gap:8px}
          .pdp-feature__image img{max-height:320px}
          .pdp-more > summary{padding:12px 13px;font-size:13.5px}
          .pdp-more__body{padding:0 13px 13px}
          .pdp-more-videos__grid{grid-template-columns:1fr}
        }
        @media(prefers-reduced-motion:reduce){.pdp-video__play,.pdp-more > summary::after{transition:none}}
        #descripcion,#especificaciones,#galeria,#incluye,#descripcion-completa{scroll-margin-top:110px}
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
        .mainnav li>a .mnl-label{display:inline-flex;align-items:center;gap:4px;white-space:nowrap}
        .mainnav li>a.has-mnl-sub{display:flex;flex-direction:column;align-items:flex-start;justify-content:center;gap:2px;padding-top:8px;padding-bottom:8px;white-space:normal;line-height:1.05}
        .mainnav .mnl-sub{display:block;overflow:hidden;height:1.25em;max-width:240px;color:var(--mnl-sub-color,#c9c9c9);font-size:var(--mnl-sub-size,10px);font-weight:500;line-height:1.2;letter-spacing:.01em;opacity:.92}
        .mainnav .mnl-sub__inner{display:block;transform:translateY(110%);opacity:0;will-change:transform,opacity}
        .mainnav .mnl-sub[data-anim="rise"] .mnl-sub__inner{animation:mnlSubRise var(--mnl-sub-interval,10s) cubic-bezier(.2,.8,.2,1) infinite}
        .mainnav .mnl-sub[data-anim="fade"] .mnl-sub__inner{animation:mnlSubFade var(--mnl-sub-interval,10s) ease-in-out infinite}
        .mainnav .mnl-sub[data-anim="slide"] .mnl-sub__inner{animation:mnlSubSlide var(--mnl-sub-interval,10s) cubic-bezier(.2,.8,.2,1) infinite}
        .mainnav .mnl-sub[data-anim="none"] .mnl-sub__inner,.mainnav .mnl-sub[data-anim=""] .mnl-sub__inner{transform:none;opacity:1;animation:none}
        @keyframes mnlSubRise{0%{transform:translateY(110%);opacity:0}7%{transform:translateY(0);opacity:1}88%{transform:translateY(0);opacity:1}100%{transform:translateY(-110%);opacity:0}}
        @keyframes mnlSubFade{0%{opacity:0}7%{opacity:1}88%{opacity:1}100%{opacity:0}}
        @keyframes mnlSubSlide{0%{transform:translateY(7px);opacity:0}7%{transform:translateY(0);opacity:1}88%{transform:translateY(0);opacity:1}100%{transform:translateY(-4px);opacity:0}}
        @media(prefers-reduced-motion:reduce){.mainnav .mnl-sub__inner{animation:none!important;transform:none!important;opacity:1!important}}
        .mainnav .submenu{position:absolute;left:0;top:100%;min-width:210px;background:#fff;border:1px solid var(--line);border-radius:12px;box-shadow:0 18px 40px rgba(0,0,0,.14);padding:8px;display:none;z-index:120}
        .mainnav li.has-sub:hover .submenu,.mainnav li.has-sub:focus-within .submenu{display:block}
        .mainnav .submenu a{display:block;padding:9px 12px;border-radius:8px;color:var(--ink);font-size:13px;font-weight:500}
        .mainnav .submenu a:hover{background:var(--soft);color:var(--brand)}
        @media(max-width:900px){.mainnav ul{justify-content:flex-start!important;padding-left:12px!important;padding-right:12px!important}}
        /* Buscador inteligente + sugerencias */
        .search{position:relative}
        .search .s-ic{position:absolute;left:13px;top:50%;transform:translateY(-50%);display:flex;width:16px;height:16px;color:var(--muted,#777);opacity:.55;pointer-events:none}
        .search .s-ic svg{display:block;width:16px;height:16px}
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
@media (max-width:900px){.nav{flex-wrap:wrap!important;gap:6px!important;padding:10px 0!important;justify-content:flex-start!important}.nav>.logo{order:1!important;margin-right:0!important}.nav-actions{order:2!important;gap:6px!important;margin-left:0!important}.nav>.search{order:3!important;flex:1 1 100%!important;margin-top:6px!important;min-width:0!important;width:100%!important}.icon-btn{width:38px!important;height:38px!important;padding:0!important;border-radius:10px!important;font-size:15px!important}.logo-img{height:24px!important;max-width:112px!important;object-fit:contain!important}.logo .mark{width:32px!important;height:32px!important;font-size:17px!important}.logo{gap:8px!important;font-size:18px!important}.nav-actions .lbl,.nav-actions .ska-acc-txt,.nav-actions .ska-acc-caret{display:none!important}.wwi-lang{display:none!important}.mn-toggle__txt,.mn-toggle__chev{display:none!important}.mn-toggle{padding:9px 11px!important}.nav>.search button[type=submit]{width:42px!important;height:42px!important;padding:0!important;font-size:0!important;border-radius:10px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;flex:0 0 auto!important}.nav>.search button[type=submit]::before{content:"";display:block;width:18px;height:18px;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='2.2' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='M16.2 16.2 21 21'/%3E%3C/svg%3E") center/contain no-repeat}.cart-badge{font-size:9px!important;padding:1px 5px!important;top:-4px!important;right:-4px!important}}
@media (max-width:900px){.ska-acc-menu{position:fixed!important;left:12px!important;right:12px!important;bottom:12px!important;top:auto!important;width:auto!important;min-width:0!important;max-height:70vh!important;overflow:auto!important;border-radius:16px!important;z-index:9995!important;transform:translateY(8px)!important}.ska-acc:focus-within .ska-acc-menu{transform:none!important}.ska-acc-menu a{padding:13px 14px!important;font-size:15px!important}.wwi-cart__panel{position:fixed!important;left:0!important;right:0!important;bottom:0!important;top:auto!important;width:100%!important;max-width:none!important;max-height:78vh!important;overflow:auto!important;border-radius:18px 18px 0 0!important;z-index:9995!important;padding-bottom:calc(14px + env(safe-area-inset-bottom))!important}.wwi-cart.open .wwi-cart__panel{transform:none!important}}
        @media(max-width:600px){.hero h1{font-size:34px}.nav{flex-wrap:wrap}.search{order:9;flex-basis:100%;margin-top:8px}.icon-btn .lbl{display:none}.cat-grid,.prod-grid{grid-template-columns:repeat(2,1fr);gap:12px}}
        .hero{position:relative;overflow:hidden}
        .hero-bg-video{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:0;opacity:.4}
        .hero>.wrap{position:relative;z-index:1}
        @media(prefers-reduced-motion:reduce){.hero-bg-video{display:none}}
/* === WWI Adaptive Tokens (Constitution v1.0) === */
:root{--wwi-tap:44px;--wwi-radius:12px;--wwi-gap:clamp(.5rem,.4rem + .5vw,.9rem);--wwi-maxw:1180px;--wwi-fs-body:1rem;--wwi-fs-input:1rem}
html,body{max-width:100%;overflow-x:clip}
img,video,iframe{max-width:100%}
.ah-rich,.ah-side__tags,.ah-tag,.ah-side__bio{overflow-wrap:anywhere}
@media(max-width:900px){input,select,textarea{font-size:16px!important}}
/* === TV / 10-foot UI (Constitution §12) === */
html.tv-mode{font-size:20px}
html.tv-mode body{overflow-x:clip}
html.tv-mode .wrap{max-width:1600px}
html.tv-mode .nav{padding:18px 0}
html.tv-mode :focus-visible{outline:4px solid #fff!important;outline-offset:4px!important;border-radius:8px}
html.tv-mode a,html.tv-mode button,html.tv-mode [tabindex]{min-height:52px;min-width:52px}
html.tv-mode .topbar{font-size:14px}
@media (min-width:1600px){.hero h1{font-size:52px}}
/* === Modo compacto determinista (touch o <=1024px) === */
html.wwi-compact .nav{flex-wrap:wrap!important;gap:6px!important;padding:10px 0!important;justify-content:flex-start!important}
html.wwi-compact .nav>.logo{order:1!important;margin-right:0!important}
html.wwi-compact .nav-actions{order:2!important;gap:6px!important;margin-left:0!important}
html.wwi-compact .nav>.search{order:3!important;flex:1 1 100%!important;margin-top:6px!important;min-width:0!important;width:100%!important}
html.wwi-compact .icon-btn{width:38px!important;height:38px!important;padding:0!important;border-radius:10px!important;font-size:15px!important}
html.wwi-compact .logo-img{height:24px!important;max-width:112px!important;object-fit:contain!important}
html.wwi-compact .logo{gap:8px!important;font-size:18px!important}
html.wwi-compact .nav-actions .lbl,html.wwi-compact .nav-actions .ska-acc-txt,html.wwi-compact .nav-actions .ska-acc-caret,html.wwi-compact .wwi-lang{display:none!important}
html.wwi-compact .cart-badge{font-size:9px!important;padding:1px 5px!important;top:-4px!important;right:-4px!important}
html.wwi-compact .ska-acc-menu{position:fixed!important;left:12px!important;right:12px!important;bottom:12px!important;top:auto!important;width:auto!important;min-width:0!important;max-height:70dvh!important;overflow:auto!important;border-radius:16px!important;z-index:9995!important;transform:translateY(8px)!important}
html.wwi-compact .ska-acc:focus-within .ska-acc-menu,html.wwi-compact .ska-acc:hover .ska-acc-menu{transform:none!important;opacity:1!important;visibility:visible!important}
html.wwi-compact .ska-acc-menu a{padding:13px 14px!important;font-size:15px!important}
html.wwi-compact .wwi-cart__panel{position:fixed!important;left:0!important;right:0!important;bottom:0!important;top:auto!important;width:100%!important;max-width:none!important;max-height:80dvh!important;overflow:auto!important;border-radius:18px 18px 0 0!important;z-index:9995!important;padding-bottom:calc(14px + env(safe-area-inset-bottom))!important}
html.wwi-compact .wwi-cart.open .wwi-cart__panel{transform:none!important;opacity:1!important;visibility:visible!important}
html.wwi-compact input,html.wwi-compact select,html.wwi-compact textarea{font-size:16px!important}html.wwi-compact .mn-toggle__txt,html.wwi-compact .mn-toggle__chev{display:none!important}html.wwi-compact .mn-toggle{padding:9px 11px!important}html.wwi-compact .nav>.search button[type=submit]{width:42px!important;height:42px!important;padding:0!important;font-size:0!important;border-radius:10px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;flex:0 0 auto!important}html.wwi-compact .nav>.search button[type=submit]::before{content:"";display:block;width:18px;height:18px;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='2.2' stroke-linecap='round'%3E%3Ccircle cx='11' cy='11' r='7'/%3E%3Cpath d='M16.2 16.2 21 21'/%3E%3C/svg%3E") center/contain no-repeat}
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
    add:function(item,qty){qty=qty||1;var c=read();var f=c.find(function(i){return i.handle===item.handle});if(f){f.qty+=qty}else{item.qty=qty;c.push(item)}write(c);try{window.dispatchEvent(new CustomEvent('sonika:cart-add',{detail:{handle:item.handle,title:item.title,price:item.price,qty:qty,sku:item.sku||'',product_id:item.product_id||''}}))}catch(e){}toast('Agregado al carrito')},
    set:function(h,q){write(read().map(function(i){if(i.handle===h)i.qty=q;return i}).filter(function(i){return i.qty>0}))},
    remove:function(h){write(read().filter(function(i){return i.handle!==h}))},
    clear:function(){write([])},
    items:read, count:count
  };
  window.sonikaWhatsapp=function(extra){
    if(!window.SONIKA_WA){if(window.SonikaDialog){SonikaDialog.alert('WhatsApp no configurado.',{title:'Atenci��n'});}else alert('WhatsApp no configurado.');return}
    var items=read();if(!items.length){alert('Tu carrito está vacío.');return}
    var t='Hola '+(window.SONIKA_STORE||'')+', quiero hacer este pedido:';var st=0;
    items.forEach(function(i){var l=(parseFloat(i.price)||0)*(parseInt(i.qty)||1);st+=l;t+='\n• '+(i.title||i.handle)+' x'+(parseInt(i.qty)||1)+' — '+money(l)});
    t+='\n\nTotal: '+money(st);
    if(extra)t+='\n'+extra;
    window.open('https://wa.me/'+window.SONIKA_WA+'?text='+encodeURIComponent(t),'_blank');
  };
  window.sonikaWhatsappFromCart=function(){window.sonikaWhatsapp('')};
  function renderCart(){
    if(window.SonikaPayments&&typeof window.SonikaPayments.renderCart==='function'){window.SonikaPayments.renderCart();return}
    var wrap=document.getElementById('cartItems'); if(!wrap) return;
    var sum=document.getElementById('cartSummary');
    var items=read();
    if(!items.length){wrap.innerHTML='<div class="empty">Tu carrito está vacío. <a href="/tienda" style="color:var(--brand);font-weight:600">Explorar tienda →</a></div>'; if(sum) sum.innerHTML=''; return;}
    var subtotal=0;
    wrap.innerHTML=items.map(function(i){subtotal+=(i.price||0)*(i.qty||0);var q=i.qty||1;
      return '<div class="cart-item"><img src="'+esc(i.img||'')+'" alt=""><div><a href="/producto/'+esc(i.handle)+'" style="font-weight:600">'+esc(i.title||'')+'</a><div style="color:var(--muted);font-size:14px;margin-top:4px">'+money(i.price)+(i.free_shipping?' <span style="color:#0d7a58;font-weight:700;font-size:12px">· Envío gratis</span>':'')+'</div></div><div style="text-align:right"><div class="qty"><button type="button" onclick="SonikaCart.set(\''+esc(i.handle)+'\','+(q-1)+')">−</button><input value="'+q+'" readonly><button type="button" onclick="SonikaCart.set(\''+esc(i.handle)+'\','+(q+1)+')">+</button></div><a href="#" onclick="event.preventDefault();SonikaCart.remove(\''+esc(i.handle)+'\')" style="display:block;margin-top:8px;color:#DC2626;font-size:13px">Eliminar</a></div></div>';
    }).join('');
    if(sum) sum.innerHTML='<h3 style="font-family:Sora,sans-serif;margin-bottom:10px">Resumen</h3><div class="row"><span>Subtotal</span><span>'+money(subtotal)+'</span></div><div class="row"><span>Envío</span><span>Calculado al pagar</span></div><div class="row total"><span>Total</span><span>'+money(subtotal)+'</span></div><a class="btn-buy" style="width:100%;margin-top:16px;display:block;text-align:center" href="/checkout">Finalizar compra</a>'+(window.SONIKA_WA?'<a class="cta" style="width:100%;margin:10px 0 0;justify-content:center;background:#25D366;color:#fff;border:0" href="#" onclick="event.preventDefault();window.sonikaWhatsappFromCart&&window.sonikaWhatsappFromCart()">Pedir por WhatsApp</a>':'')+'<a class="cta sec" href="/tienda" style="width:100%;margin:10px 0 0;justify-content:center">Seguir comprando</a>';
  }
  paint();
  function pdpReset(b){if(!b||!b.hasAttribute('data-pdp-add'))return;if(b._pdpTimer){clearTimeout(b._pdpTimer);b._pdpTimer=null}b.disabled=false;b.dataset.goCart='';b.classList.remove('is-added','is-go-cart');b.textContent=b.dataset.addLabel||'Agregar al carrito';var w=b.closest('.pdp-actions');if(w){var m=w.querySelector('[data-pdp-add-more]');if(m)m.hidden=true;var s=w.querySelector('[data-pdp-cart-shortcut]');if(s)s.hidden=false}var st=document.querySelector('[data-pdp-status]');if(st)st.textContent=''}
  document.querySelectorAll('[data-add]').forEach(function(b){
    if(b.hasAttribute('data-pdp-add'))b.dataset.addLabel=b.textContent.trim();
    b.addEventListener('click',function(e){e.preventDefault();
      if(b.dataset.goCart==='1'){window.location.href=b.dataset.cartUrl||'/carrito';return}
      var q=b.dataset.qtyTarget?parseInt((document.getElementById(b.dataset.qtyTarget)||{}).value||'1',10):1;
      if(!(q>0)) q=1;
      window.SonikaCart.add({handle:b.dataset.add,title:b.dataset.title,price:parseFloat(b.dataset.price),img:b.dataset.img,sku:b.dataset.sku||'',product_id:b.dataset.productId||'',free_shipping:b.dataset.freeShipping==='1'},q);
      if(b.hasAttribute('data-pdp-add')){
        var w=b.closest('.pdp-actions'),m=w?w.querySelector('[data-pdp-add-more]'):null,s=w?w.querySelector('[data-pdp-cart-shortcut]'):null,st=document.querySelector('[data-pdp-status]');
        if(s)s.hidden=true;b.disabled=true;b.classList.remove('is-go-cart');b.classList.add('is-added');b.textContent='Agregado \u2713';
        if(st)st.textContent='Producto agregado al carrito.';
        b._pdpTimer=setTimeout(function(){
          b.disabled=false;b.classList.remove('is-added');b.classList.add('is-go-cart');b.textContent='Ir al carrito de compras';b.dataset.goCart='1';
          if(m)m.hidden=false;
          if(st)st.textContent='Producto agregado. Puedes ir al carrito o agregar otra unidad.';
        },1500);
        if(w&&!w.dataset.pdpReady){
          w.dataset.pdpReady='1';
          var more=w.querySelector('[data-pdp-add-more]');
          if(more)more.addEventListener('click',function(){pdpReset(b);var q=document.getElementById(b.dataset.qtyTarget||'qty');if(q)q.focus()});
          var qty=w.querySelector('.qty');
          if(qty)qty.addEventListener('click',function(ev){if(ev.target.closest('button'))setTimeout(function(){pdpReset(b)},0)});
          var qtyInput=document.getElementById(b.dataset.qtyTarget||'qty');
          if(qtyInput)qtyInput.addEventListener('input',function(){if(b.dataset.goCart==='1'||b.classList.contains('is-added'))pdpReset(b)});
        }
      }else{
        var o=b.textContent;b.textContent='Agregado \u2713';b.style.background='#059669';setTimeout(function(){b.textContent=o;b.style.background=''},1200);
      }
    });
  });
  renderCart();
})();
JS;
    }
}
