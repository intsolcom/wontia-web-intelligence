<?php
namespace App\Widgets;

use App\Services\MegaMenuService;

/**
 * MegaMenuWidget — brick "Mega Menú" (categoría header).
 * Panel lateral izquierdo (off-canvas) + megamenú contextual SSR con categorías
 * hasta 5 niveles, marcas, rangos de precio, populares y filtro instantáneo.
 *
 * Rendimiento: TODO el árbol se pre-renderiza en servidor y se inyecta como HTML
 * (abrir = toggle de clase, sin peticiones).
 * Apilamiento: el drawer y el overlay se "portan" a <body> (JS) para NO quedar
 * atrapados en el contexto de apilamiento del header sticky, y se muestran por
 * ENCIMA de todo (z-index alto).
 * Seguridad: escape total. Aislado bajo la clase raíz `.mn-*`. Estado: INCUBADORA.
 */
class MegaMenuWidget extends Widget
{
    private static bool $assetsPrinted = false;

    public static function meta(): array
    {
        return ['id' => 'mega-menu', 'name' => 'Mega Menú', 'icon' => 'menu', 'category' => 'header', 'version' => '1.2.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'enabled', 'label' => 'Activar el menú lateral + megamenú', 'type' => 'toggle', 'default' => false],
            ['key' => 'button_text', 'label' => 'Texto del botón', 'type' => 'text', 'default' => 'Menú'],
            ['key' => 'greeting', 'label' => 'Saludo de la cabecera lateral', 'type' => 'text', 'default' => '¡Hola!'],
            ['key' => 'width', 'label' => 'Ancho del panel lateral (px)', 'type' => 'number', 'default' => 300],
            ['key' => 'header_color', 'label' => 'Color de la cabecera de categoría', 'type' => 'color', 'default' => '#C6F24E'],
            ['key' => 'header_text_color', 'label' => 'Color del texto de la cabecera', 'type' => 'color', 'default' => '#111111'],
            ['key' => 'max_columns', 'label' => 'Máximo de columnas', 'type' => 'number', 'default' => 4],
            ['key' => 'show_view_all', 'label' => 'Mostrar “Ver todo”', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_badges', 'label' => 'Mostrar etiquetas (NUEVO/OFERTA)', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_images', 'label' => 'Mostrar imagen promocional', 'type' => 'toggle', 'default' => true],
            ['key' => 'hide_empty', 'label' => 'Ocultar categorías sin productos', 'type' => 'toggle', 'default' => true],
            ['key' => 'max_categories', 'label' => 'Máx. categorías en el menú (0 = todas)', 'type' => 'number', 'default' => 40],
            ['key' => 'sort', 'label' => 'Orden de las categorías', 'type' => 'select', 'options' => ['alpha' => 'Alfabético (A-Z)', 'count' => 'Más productos primero'], 'default' => 'alpha'],
            ['key' => 'mobile_drill', 'label' => 'Móvil: navegación por dentro del panel', 'type' => 'toggle', 'default' => true],
            ['key' => 'transition_ms', 'label' => 'Duración de la transición (ms)', 'type' => 'number', 'default' => 200],
            ['key' => 'promo_enabled', 'label' => 'Promoción (imagen global)', 'type' => 'toggle', 'default' => false],
            ['key' => 'promo_image', 'label' => 'Imagen promocional', 'type' => 'image', 'default' => ''],
            ['key' => 'promo_alt', 'label' => 'Texto alternativo', 'type' => 'text', 'default' => ''],
            ['key' => 'promo_href', 'label' => 'Enlace de la promoción', 'type' => 'text', 'default' => ''],
            ['key' => 'popular', 'label' => 'Mostrar “Populares ahora”', 'type' => 'toggle', 'default' => true],
            ['key' => 'popular_title', 'label' => 'Título de populares', 'type' => 'text', 'default' => 'Populares ahora'],
            ['key' => 'popular_limit', 'label' => 'Nº de populares', 'type' => 'number', 'default' => 8],
            ['key' => 'show_info', 'label' => 'Incluir nodo “Información” (páginas publicadas)', 'type' => 'toggle', 'default' => false],
            ['key' => 'info_label', 'label' => 'Etiqueta del nodo Información', 'type' => 'text', 'default' => 'Información'],
            ['key' => 'info_icon', 'label' => 'Ícono del nodo Información', 'type' => 'text', 'default' => '📄'],
            ['key' => 'category_overrides', 'label' => 'Renombrar categorías (override: slug o nombre → nuevo nombre/ícono)', 'type' => 'repeater', 'default' => [], 'fields' => [
                ['key' => 'match', 'label' => 'Categoría (slug o nombre)', 'type' => 'text'],
                ['key' => 'label', 'label' => 'Nuevo nombre', 'type' => 'text'],
                ['key' => 'icon', 'label' => 'Ícono', 'type' => 'text'],
            ]],
            ['key' => 'footer_links', 'label' => 'Enlaces inferiores', 'type' => 'repeater', 'default' => [], 'fields' => [
                ['key' => 'label', 'label' => 'Etiqueta', 'type' => 'text'],
                ['key' => 'url', 'label' => 'URL', 'type' => 'text'],
            ]],
            ['key' => 'category_promos', 'label' => 'Promociones por categoría (imagen)', 'type' => 'repeater', 'default' => [], 'fields' => [
                ['key' => 'category', 'label' => 'Categoría (slug o nombre)', 'type' => 'text'],
                ['key' => 'image', 'label' => 'Imagen', 'type' => 'image'],
                ['key' => 'alt', 'label' => 'Texto alternativo', 'type' => 'text'],
                ['key' => 'href', 'label' => 'Enlace', 'type' => 'text'],
            ]],
        ];
    }

    public static function defaultConfig(): array
    {
        return [
            'enabled' => false, 'button_text' => 'Menú', 'greeting' => '¡Hola!', 'width' => 300,
            'header_color' => '#C6F24E', 'header_text_color' => '#111111', 'max_columns' => 4,
            'show_view_all' => true, 'show_badges' => true, 'show_images' => true, 'hide_empty' => true, 'max_categories' => 40, 'sort' => 'alpha', 'mobile_drill' => true,
            'transition_ms' => 200, 'promo_enabled' => false, 'promo_image' => '', 'promo_alt' => '', 'promo_href' => '',
            'popular' => true, 'popular_title' => 'Populares ahora', 'popular_limit' => 8, 'category_promos' => [],
            'show_info' => false, 'info_label' => 'Información', 'info_icon' => '📄', 'category_overrides' => [],
            'footer_links' => [],
        ];
    }

    public function render(array $config = []): string
    {
        $cfg = $this->mergeConfig($config);
        if (empty($cfg['enabled'])) return '';

        $products = function_exists('sonika_products') ? sonika_products() : null;
        $sort = ((string)($cfg['sort'] ?? 'alpha') === 'count') ? 'count' : 'alpha';
        $tree = (new MegaMenuService())->tree([], $products, 90, $sort);
        $nodes = $tree['categories'] ?? [];
        $nodes = $this->applyOverrides($nodes, (array)($cfg['category_overrides'] ?? []));
        if (!empty($cfg['show_info'])) {
            $info = $this->infoNode((string)($cfg['info_label'] ?? 'Información'), (string)($cfg['info_icon'] ?? '📄'));
            if ($info) $nodes[] = $info;
        }
        $maxCat = (int)($cfg['max_categories'] ?? 40);
        if ($maxCat > 0 && count($nodes) > $maxCat) {
            $nodes = array_slice($nodes, 0, $maxCat);
            $shown = [];
            foreach ($nodes as $n) $shown[strtolower((string)$n['name'])] = 1;
            $tree['index'] = array_values(array_filter((array)($tree['index'] ?? []), fn($e) => isset($shown[strtolower((string)($e['c'] ?? ''))])));
        }
        if (!$nodes) return '';

        $width = max(220, min(520, (int)($cfg['width'] ?? 300)));
        $trans = max(0, min(1000, (int)($cfg['transition_ms'] ?? 200)));
        $headColor = $this->safeColor((string)($cfg['header_color'] ?? '#C6F24E'), '#C6F24E');
        $headText = $this->safeColor((string)($cfg['header_text_color'] ?? '#111111'), '#111111');
        $maxCols = max(1, min(6, (int)($cfg['max_columns'] ?? 4)));
        $showAll = !empty($cfg['show_view_all']);
        $showBadges = !empty($cfg['show_badges']);
        $showImages = !empty($cfg['show_images']);
        $hideEmpty = !empty($cfg['hide_empty']);
        $greeting = (string)($cfg['greeting'] ?? '¡Hola!');
        $btnText = (string)($cfg['button_text'] ?? 'Menú');
        $drill = !empty($cfg['mobile_drill']);
        $popularTitle = (string)($cfg['popular_title'] ?? 'Populares ahora');
        $popular = !empty($cfg['popular']) ? $this->popularSearches(max(1, min(20, (int)($cfg['popular_limit'] ?? 8)))) : [];
        $promoMap = $this->promoMap((array)($cfg['category_promos'] ?? []));

        $vars = '--mn-w:' . $width . 'px;--mn-head:' . $headColor . ';--mn-head-txt:' . $headText . ';--mn-maxcol:' . $maxCols . ';--mn-ms:' . $trans . 'ms';

        $h = $this->assets();

        $h .= '<div class="mn" data-mn>';
        $h .= '<button type="button" class="mn-toggle" aria-expanded="false" aria-controls="mn-drawer" aria-haspopup="true">'
            . '<span class="mn-toggle__ic" aria-hidden="true">&#9776;</span><span class="mn-toggle__txt">' . $this->esc($btnText) . '</span></button>';
        $h .= '</div>';

        $h .= '<div class="mn-overlay" data-mn-close hidden></div>';

        $h .= '<div class="mn-drawer' . ($drill ? ' drill' : '') . '" id="mn-drawer" role="dialog" aria-modal="true" aria-label="Menú de categorías" style="' . $vars . '" hidden>';
        $h .= '<aside class="mn-side">';
        $h .= '<div class="mn-side__head"><span class="mn-side__hello">' . $this->esc($greeting) . '</span>'
            . '<button type="button" class="mn-close" data-mn-close aria-label="Cerrar menú">&#10005;</button></div>';
        $h .= '<div class="mn-filter"><input type="search" class="mn-filter__in" placeholder="Filtrar categorías…" aria-label="Filtrar categorías"/></div>';
        $h .= '<nav class="mn-cats" aria-label="Categorías"><ul class="mn-cats__list">';
        foreach ($nodes as $n) {
            if ($hideEmpty && (int)$n['count'] === 0) continue;
            $has = (!empty($n['types']) || !empty($n['brands']) || !empty($n['models']));
            $h .= '<li class="mn-cat' . ($has ? ' has-panel' : '') . '" data-mn-cat="' . $this->esc($n['slug']) . '">'
                . '<a class="mn-cat__link" href="' . $this->esc($n['url']) . '">'
                . ($n['icon'] !== '' ? '<span class="mn-cat__ic" aria-hidden="true">' . $this->esc($n['icon']) . '</span>' : '')
                . '<span class="mn-cat__nm">' . $this->esc($n['name']) . '</span>'
                . ($showBadges && !empty($n['badge_new']) ? '<span class="mn-tag mn-tag--new">NUEVO</span>' : '')
                . ($showBadges && !empty($n['badge_offer']) ? '<span class="mn-tag mn-tag--off">OFERTA</span>' : '')
                . '<span class="mn-cat__ct">' . (int)$n['count'] . '</span>'
                . ($has ? '<span class="mn-cat__chev" aria-hidden="true">&#8250;</span>' : '')
                . '</a></li>';
        }
        $h .= '</ul><ul class="mn-filter__results" hidden></ul></nav>';
        if ($popular) {
            $h .= '<div class="mn-pop"><div class="mn-pop__t">' . $this->esc($popularTitle) . '</div><div class="mn-pop__chips">';
            foreach ($popular as $pq) $h .= '<a class="mn-chip" href="/buscar?q=' . urlencode($pq) . '">' . $this->esc($pq) . '</a>';
            $h .= '</div></div>';
        }
        $foot = $this->safeLinks((array)($cfg['footer_links'] ?? []));
        if ($foot) {
            $h .= '<div class="mn-side__foot">';
            foreach ($foot as $fl) $h .= '<a href="' . $this->esc($fl['url']) . '">' . $this->esc($fl['label']) . '</a>';
            $h .= '</div>';
        }
        $h .= '</aside>';

        $h .= '<section class="mn-mega" aria-live="polite">';
        foreach ($nodes as $n) {
            $has = (!empty($n['types']) || !empty($n['brands']) || !empty($n['models']));
            if (!$has) continue;
            $h .= '<div class="mn-mega__panel" data-mn-panel="' . $this->esc($n['slug']) . '" hidden>';
            $h .= '<div class="mn-mega__bg">';
            $h .= '<header class="mn-mega__head">'
                . '<button type="button" class="mn-back" data-mn-back aria-label="Volver">&#8249; Volver</button>'
                . '<span class="mn-mega__ic" aria-hidden="true">' . $this->esc($n['icon']) . '</span>'
                . '<h3 class="mn-mega__title">' . $this->esc($n['name']) . '</h3>'
                . ($showAll ? '<a class="mn-mega__all" href="' . $this->esc($n['url']) . '">Ver todo &#8250;</a>' : '')
                . '</header>';
            $h .= '<div class="mn-cols">';
            $k2 = (string)($n['kinds'][2] ?? 'Subcategoría'); $k3 = (string)($n['kinds'][3] ?? 'Marca'); $k4 = (string)($n['kinds'][4] ?? 'Modelo');
            if (!empty($n['types']))  { $h .= $this->column($k2, $n['types'], $n['url'], $showAll); }
            if (!empty($n['brands'])) { $h .= $this->column($k3, $n['brands'], null, $showAll); }
            if (!empty($n['models'])) { $h .= $this->column($k4, $n['models'], null, $showAll); }
            if (!empty($n['level5'])) { $h .= $this->column('Nivel 5', $n['level5'], null, $showAll); }
            if (!empty($n['prices'])) { $h .= $this->column('Precio', $n['prices'], null, false); }
            $promo = $this->resolvePromo($n, $promoMap, $cfg, $showImages);
            if ($promo) {
                $imgTag = '<img class="mn-promo__img" src="' . $this->esc($promo['image']) . '" alt="' . $this->esc($promo['alt']) . '" loading="lazy"/>';
                $h .= '<div class="mn-col mn-promo">' . ($promo['href'] !== '' ? '<a href="' . $this->esc($promo['href']) . '">' . $imgTag . '</a>' : $imgTag) . '</div>';
            }
            $h .= '</div></div></div>';
        }
        $h .= '</section>';
        $h .= '</div>';

        $h .= '<script type="application/json" class="mn-index">' . json_encode($tree['index'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) . '</script>';
        return $h;
    }

    private function popularSearches(int $limit): array
    {
        $cache = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'wwi_mn_pop.json';
        if (is_file($cache) && (time() - filemtime($cache)) < 300) {
            $c = json_decode((string)@file_get_contents($cache), true);
            if (is_array($c)) return array_slice(array_values(array_filter($c, 'is_string')), 0, $limit);
        }
        $out = [];
        try {
            $rows = (new \App\Services\SearchAnalyticsService())->top($limit * 2);
            foreach ($rows as $r) {
                $q = trim((string)($r['q'] ?? ''));
                if (mb_strlen($q) < 3) continue;
                if ((int)($r['avg_results'] ?? 0) <= 0) continue;
                $out[] = $q;
                if (count($out) >= $limit) break;
            }
        } catch (\Throwable $e) {
            return [];
        }
        @file_put_contents($cache, json_encode($out, JSON_UNESCAPED_UNICODE));
        return $out;
    }

    private function promoMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $img = trim((string)($r['image'] ?? ''));
            if ($img === '') continue;
            $key = strtolower(trim((string)($r['category'] ?? '')));
            if ($key === '') continue;
            $map[$key] = ['image' => $img, 'alt' => (string)($r['alt'] ?? ''), 'href' => (string)($r['href'] ?? '')];
        }
        return $map;
    }

    private function resolvePromo(array $node, array $promoMap, array $cfg, bool $showImages): ?array
    {
        if (!$showImages) return null;
        foreach ([strtolower((string)($node['slug'] ?? '')), strtolower((string)($node['name'] ?? ''))] as $k) {
            if ($k !== '' && isset($promoMap[$k])) return $promoMap[$k];
        }
        if (!empty($cfg['promo_enabled']) && (string)($cfg['promo_image'] ?? '') !== '') {
            return ['image' => (string)$cfg['promo_image'], 'alt' => (string)($cfg['promo_alt'] ?? ''), 'href' => (string)($cfg['promo_href'] ?? '')];
        }
        return null;
    }

    private function applyOverrides(array $nodes, array $rows): array
    {
        if (!$rows) return $nodes;
        $map = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $k = strtolower(trim((string)($r['match'] ?? '')));
            if ($k === '') continue;
            $map[$k] = ['label' => trim((string)($r['label'] ?? '')), 'icon' => trim((string)($r['icon'] ?? ''))];
        }
        if (!$map) return $nodes;
        foreach ($nodes as &$n) {
            foreach ([strtolower((string)($n['slug'] ?? '')), strtolower((string)($n['name'] ?? ''))] as $k) {
                if ($k !== '' && isset($map[$k])) {
                    if ($map[$k]['label'] !== '') $n['name'] = $map[$k]['label'];
                    if ($map[$k]['icon'] !== '') $n['icon'] = $map[$k]['icon'];
                    break;
                }
            }
        }
        unset($n);
        return $nodes;
    }

    private function infoNode(string $label, string $icon): ?array
    {
        $pages = $this->publishedPages();
        if (!$pages) return null;
        $items = [];
        foreach ($pages as $p) $items[] = ['name' => $p['title'], 'url' => $p['url']];
        return [
            'slug' => 'informacion', 'name' => $label, 'kind' => 'Página', 'icon' => $icon, 'subtitle' => '',
            'url' => (string)($items[0]['url'] ?? '#'), 'count' => count($items), 'badge_new' => false, 'badge_offer' => false,
            'types' => $items, 'brands' => [], 'models' => [], 'level5' => [], 'kinds' => [2 => 'Página'], 'prices' => [],
        ];
    }

    private function publishedPages(): array
    {
        $out = [];
        try {
            $st = \App\Core\Database::instance()->prepare("SELECT title, slug FROM pages WHERE site_id = @site_id AND status = 'published' ORDER BY title ASC LIMIT 40");
            $st->execute();
            foreach ($st->fetchAll() as $r) {
                $slug = trim((string)($r['slug'] ?? ''));
                if ($slug === '') continue;
                $out[] = ['title' => (string)($r['title'] ?? $slug), 'url' => '/' . ltrim($slug, '/')];
            }
        } catch (\Throwable $e) {
        }
        return $out;
    }

    private function column(string $title, array $items, ?string $viewAllUrl, bool $showAll): string
    {
        $h = '<div class="mn-col"><h4 class="mn-col__t">' . $this->esc($title) . '</h4><ul class="mn-col__list">';
        foreach ($items as $it) {
            $name = (string)($it['name'] ?? '');
            $url = (string)($it['url'] ?? '#');
            $cnt = isset($it['count']) ? ' <span class="mn-col__c">' . (int)$it['count'] . '</span>' : '';
            $h .= '<li><a href="' . $this->esc($url) . '">' . $this->esc($name) . $cnt . '</a></li>';
        }
        if ($showAll && $viewAllUrl) $h .= '<li><a class="mn-col__all" href="' . $this->esc($viewAllUrl) . '">Ver todo &#8250;</a></li>';
        return $h . '</ul></div>';
    }

    private function fallbackCategories(): array
    {
        $svc = new MegaMenuService();
        $out = [];
        foreach ($svc->catalog() as $p) {
            $t = trim((string)($p['product_type'] ?? ''));
            if ($t === '') continue;
            $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $t));
            if (isset($out[$slug])) continue;
            $out[$slug] = ['slug' => $slug, 'name' => ucfirst($t), 'icon' => '', 'tags' => '', 'kw' => [strtoupper($t)]];
        }
        return array_values($out);
    }

    private function safeLinks(array $rows): array
    {
        $out = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $label = trim((string)($r['label'] ?? ''));
            $url = trim((string)($r['url'] ?? ''));
            if ($label === '' || $url === '') continue;
            if (!preg_match('#^(/|https?://)#i', $url)) continue;
            $out[] = ['label' => $label, 'url' => $url];
        }
        return $out;
    }

    private function safeColor(string $c, string $fallback): string
    {
        return preg_match('/^#[0-9a-f]{3,8}$/i', trim($c)) ? trim($c) : $fallback;
    }

    private function assets(): string
    {
        if (self::$assetsPrinted) return '';
        self::$assetsPrinted = true;
        return <<<'HTML'
<style>
.mn{display:inline-flex;align-items:center}
.mn-toggle{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--line,#E6E6E6);background:#fff;color:var(--ink,#111);border-radius:11px;padding:9px 13px;font:inherit;font-size:13px;font-weight:700;cursor:pointer;line-height:1}
.mn-toggle:hover{border-color:var(--brand,#F20D16);color:var(--brand,#F20D16)}
.mn-toggle__ic{font-size:17px;line-height:1}
.mn-overlay{position:fixed;inset:0;background:rgba(8,10,16,.5);opacity:0;transition:opacity .18s ease;z-index:9000;border:0}
.mn-overlay.is-open{opacity:1}
.mn-drawer{position:fixed;top:0;left:0;bottom:0;display:flex;transform:translateX(-100%);transition:transform var(--mn-ms,200ms) cubic-bezier(.22,.61,.36,1);z-index:9010;max-width:100vw}
.mn-drawer.is-open{transform:translateX(0)}
.mn-side{width:var(--mn-w,300px);max-width:86vw;background:#fff;border-right:1px solid var(--line,#E6E6E6);display:flex;flex-direction:column;min-height:0}
.mn-side__head{display:flex;align-items:center;justify-content:space-between;padding:16px 18px;border-bottom:1px solid var(--line,#EEE)}
.mn-side__hello{font-family:Sora,sans-serif;font-weight:800;font-size:18px}
.mn-close{border:0;background:transparent;font-size:20px;line-height:1;cursor:pointer;color:var(--muted,#666)}
.mn-filter{padding:10px 14px;border-bottom:1px solid var(--line,#EEE)}
.mn-filter__in{width:100%;padding:9px 12px;border:1px solid var(--line,#E6E6E6);border-radius:9px;font:inherit;font-size:14px}
.mn-cats{flex:1;overflow:auto;padding:6px 8px}
.mn-cats__list,.mn-filter__results{list-style:none;margin:0;padding:0}
.mn-cat__link{display:flex;align-items:center;gap:10px;padding:11px 12px;border-radius:10px;text-decoration:none;color:var(--ink,#111);font-weight:600;font-size:14px;position:relative;border-left:3px solid transparent}
.mn-cat__link:hover{background:var(--soft,#F5F5F5)}
.mn-cat.is-active .mn-cat__link{background:var(--soft,#F5F5F5);border-left-color:var(--mn-head,#C6F24E)}
.mn-cat.is-active .mn-cat__chev{color:var(--mn-head,#C6F24E)}
.mn-cat__ic{font-size:17px;line-height:1}
.mn-cat__nm{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.mn-cat__ct{font-size:11px;color:var(--muted,#999);background:var(--soft,#F1F1F1);border-radius:999px;padding:1px 8px}
.mn-cat__chev{color:var(--muted,#bbb);font-size:18px;line-height:1}
.mn-tag{font-size:9px;font-weight:800;letter-spacing:.04em;padding:2px 6px;border-radius:5px}
.mn-tag--new{background:#111;color:#fff}
.mn-tag--off{background:var(--brand,#F20D16);color:#fff}
.mn-side__foot{border-top:1px solid var(--line,#EEE);padding:10px 16px;display:flex;flex-wrap:wrap;gap:12px}
.mn-side__foot a{font-size:12px;color:var(--muted,#666);text-decoration:none}
.mn-pop{padding:10px 14px;border-top:1px solid var(--line,#EEE)}
.mn-pop__t{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted,#999);margin-bottom:8px}
.mn-pop__chips{display:flex;flex-wrap:wrap;gap:6px}
.mn-chip{font-size:12px;padding:5px 10px;border-radius:999px;background:var(--soft,#F5F5F5);color:var(--ink,#222);text-decoration:none}
.mn-chip:hover{background:var(--brand,#F20D16);color:#fff}
.mn-filter__results{overflow:auto}
.mn-filter__results li a{display:flex;justify-content:space-between;gap:10px;padding:9px 12px;border-radius:8px;text-decoration:none;color:var(--ink,#111);font-size:13px}
.mn-filter__results li a:hover{background:var(--soft,#F5F5F5)}
.mn-filter__results small{color:var(--muted,#999)}
.mn-mega{background:#fff;border-right:1px solid var(--line,#E6E6E6);box-shadow:0 24px 60px rgba(0,0,0,.16);max-width:min(88vw,920px);overflow:auto}
.mn-mega__panel{min-height:100%}
.mn-mega__bg{padding:0 0 24px}
.mn-mega__head{display:flex;align-items:center;gap:12px;background:var(--mn-head,#C6F24E);color:var(--mn-head-txt,#111);padding:16px 22px;border-radius:0 22px 22px 0;margin:0 0 18px}
.mn-mega__ic{font-size:22px}
.mn-mega__title{font-family:Sora,sans-serif;font-size:17px;font-weight:800;margin:0;flex:1}
.mn-mega__all{color:inherit;text-decoration:none;font-weight:700;font-size:13px;border-bottom:2px solid currentColor;white-space:nowrap}
.mn-back{display:none;border:0;background:transparent;color:inherit;font:inherit;font-weight:700;cursor:pointer;padding:0}
.mn-cols{display:grid;grid-template-columns:repeat(var(--mn-maxcol,4),minmax(0,1fr));gap:22px 30px;padding:0 24px}
.mn-col{min-width:0}
.mn-col__t{font-size:12px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted,#888);margin:0 0 10px}
.mn-col__list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:2px}
.mn-col__list a{display:flex;justify-content:space-between;gap:8px;padding:6px 8px;border-radius:7px;text-decoration:none;color:var(--ink,#222);font-size:13px}
.mn-col__list a:hover{background:var(--soft,#F5F5F5);color:var(--brand,#F20D16)}
.mn-col__c{color:var(--muted,#aaa);font-size:11px}
.mn-col__all{font-weight:700;color:var(--brand,#F20D16)}
.mn-promo img{width:100%;height:auto;border-radius:12px;display:block;object-fit:cover}
html.mn-locked,html.mn-locked body{overflow:hidden}
@media(max-width:900px){
  .mn-drawer{max-width:100vw}
  .mn-side{width:100%;max-width:none}
  .mn-mega{max-width:100vw}
  .mn-drawer.drill[data-view="panel"] .mn-side{display:none}
  .mn-drawer.drill:not([data-view="panel"]) .mn-mega{display:none}
  .mn-drawer.drill .mn-back{display:inline-block}
  .mn-cols{grid-template-columns:repeat(2,minmax(0,1fr));padding:0 16px}
}
@media(max-width:520px){.mn-cols{grid-template-columns:1fr}}
@media(prefers-reduced-motion:reduce){.mn-drawer,.mn-overlay{transition:none}}
</style>
<script>if(!window.__mnInit){window.__mnInit=1;(function(){
  var D=document, state={open:false}, lastFocus=null, hoverT=null;
  function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]});}
  function drawer(){return D.querySelector('.mn-drawer');}
  function overlay(){return D.querySelector('.mn-overlay');}
  function portal(){
    var b=D.body, d=drawer(), o=overlay();
    if(o&&o.parentNode!==b)b.appendChild(o);
    if(d&&d.parentNode!==b)b.appendChild(d);
  }
  function openDrawer(open){
    var dr=drawer(); if(!dr)return; var ov=overlay(); var tg=D.querySelector('.mn-toggle'); state.open=open;
    if(open){lastFocus=D.activeElement; dr.hidden=false; if(ov)ov.hidden=false; D.documentElement.classList.add('mn-locked');
      requestAnimationFrame(function(){dr.classList.add('is-open'); if(ov)ov.classList.add('is-open');});
      if(tg)tg.setAttribute('aria-expanded','true'); var f=dr.querySelector('.mn-close'); if(f)f.focus();
    } else {dr.classList.remove('is-open'); if(ov)ov.classList.remove('is-open'); if(tg)tg.setAttribute('aria-expanded','false'); D.documentElement.classList.remove('mn-locked');
      setTimeout(function(){dr.hidden=true; if(ov)ov.hidden=true;},240); setActive(null); if(lastFocus&&lastFocus.focus)lastFocus.focus();}
  }
  function setActive(slug){
    var dr=drawer(); if(!dr)return; var cats=dr.querySelectorAll('.mn-cat');
    for(var i=0;i<cats.length;i++)cats[i].classList.toggle('is-active',cats[i].getAttribute('data-mn-cat')===slug);
    var ps=dr.querySelectorAll('[data-mn-panel]');
    for(var j=0;j<ps.length;j++)ps[j].hidden=(ps[j].getAttribute('data-mn-panel')!==slug);
    if(slug)dr.setAttribute('data-view','panel'); else dr.removeAttribute('data-view');
  }
  D.addEventListener('click',function(e){
    if(e.target.closest('.mn-toggle')){e.preventDefault();openDrawer(!state.open);return;}
    if(e.target.closest('[data-mn-close]')){e.preventDefault();openDrawer(false);return;}
    if(e.target.closest('[data-mn-back]')){e.preventDefault();setActive(null);return;}
    var link=e.target.closest('.mn-cat__link');
    if(link&&state.open){var li=link.closest('.mn-cat'); if(li&&li.classList.contains('has-panel')){e.preventDefault();setActive(li.getAttribute('data-mn-cat'));}}
  },false);
  D.addEventListener('mouseover',function(e){
    if(!state.open)return; var li=e.target.closest&&e.target.closest('.mn-cat'); if(!li||!li.classList.contains('has-panel'))return;
    clearTimeout(hoverT); var slug=li.getAttribute('data-mn-cat'); hoverT=setTimeout(function(){setActive(slug);},300);
  },false);
  D.addEventListener('keydown',function(e){ if(e.key==='Escape'&&state.open){e.preventDefault();openDrawer(false);} });
  D.addEventListener('input',function(e){
    var inp=e.target.closest&&e.target.closest('.mn-filter__in'); if(!inp)return; var dr=drawer(); var q=inp.value.trim().toLowerCase();
    var cats=dr.querySelectorAll('.mn-cat');
    for(var i=0;i<cats.length;i++){var nm=(cats[i].querySelector('.mn-cat__nm')||{}).textContent||''; cats[i].style.display=(!q||nm.toLowerCase().indexOf(q)>=0)?'':'none';}
    var res=dr.querySelector('.mn-filter__results'); var idx=D.querySelector('.mn-index');
    if(!q){if(res){res.hidden=true;res.innerHTML='';}return;}
    var data=[]; try{data=JSON.parse((idx&&idx.textContent)||'[]');}catch(x){}
    var hits=[]; for(var k=0;k<data.length&&hits.length<20;k++){var n=data[k]; if(((n.n||'').toLowerCase().indexOf(q)>=0)||((n.c||'').toLowerCase().indexOf(q)>=0))hits.push(n);}
    if(res){res.hidden=false; res.innerHTML=hits.map(function(n){return '<li><a href="'+esc(n.u)+'"><span>'+esc(n.n)+'</span><small>'+esc(n.c)+'</small></a></li>';}).join('');}
  },false);
  if(D.readyState!=='loading')portal(); else D.addEventListener('DOMContentLoaded',portal);
})();}</script>
HTML;
    }
}
