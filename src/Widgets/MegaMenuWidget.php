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
            ['key' => 'header_color', 'label' => 'Color de la cabecera de categoría', 'type' => 'color', 'default' => '#F20D16'],
            ['key' => 'header_text_color', 'label' => 'Color del texto de la cabecera', 'type' => 'color', 'default' => '#FFFFFF'],
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
                ['key' => 'icon', 'label' => 'Ícono (librería)', 'type' => 'icon'],
            ]],
            ['key' => 'banner_enabled', 'label' => 'Mostrar banner inferior', 'type' => 'toggle', 'default' => true],
            ['key' => 'banner_title', 'label' => 'Banner inferior: título', 'type' => 'text', 'default' => 'Descubre todo lo que SONIKA tiene para ti.'],
            ['key' => 'banner_text', 'label' => 'Banner inferior: texto', 'type' => 'text', 'default' => 'Explora nuestro catálogo de instrumentos y equipos musicales.'],
            ['key' => 'banner_button', 'label' => 'Banner inferior: texto del botón', 'type' => 'text', 'default' => 'Explorar catálogo'],
            ['key' => 'banner_href', 'label' => 'Banner inferior: enlace', 'type' => 'text', 'default' => '/tienda'],
            ['key' => 'banner_image', 'label' => 'Banner inferior: imagen de fondo (opcional)', 'type' => 'image', 'default' => ''],
            ['key' => 'brand_logos', 'label' => 'Logotipos de marca (marca → imagen)', 'type' => 'repeater', 'default' => [], 'fields' => [
                ['key' => 'brand', 'label' => 'Marca', 'type' => 'text'],
                ['key' => 'image', 'label' => 'Logotipo', 'type' => 'image'],
            ]],
            ['key' => 'footer_links', 'label' => 'Enlaces inferiores', 'type' => 'repeater', 'default' => [], 'fields' => [
                ['key' => 'label', 'label' => 'Etiqueta', 'type' => 'text'],
                ['key' => 'url', 'label' => 'URL', 'type' => 'text'],
            ]],
            ['key' => 'category_promos', 'label' => 'Banner/hero por categoría (imagen y textos)', 'type' => 'repeater', 'default' => [], 'fields' => [
                ['key' => 'category', 'label' => 'Categoría (slug o nombre)', 'type' => 'text'],
                ['key' => 'image', 'label' => 'Imagen protagonista', 'type' => 'image'],
                ['key' => 'eyebrow', 'label' => 'Etiqueta (eyebrow)', 'type' => 'text'],
                ['key' => 'title', 'label' => 'Título', 'type' => 'text'],
                ['key' => 'text', 'label' => 'Descripción', 'type' => 'text'],
                ['key' => 'button', 'label' => 'Texto del botón', 'type' => 'text'],
                ['key' => 'href', 'label' => 'Enlace', 'type' => 'text'],
                ['key' => 'alt', 'label' => 'Texto alternativo de la imagen', 'type' => 'text'],
            ]],
        ];
    }

    public static function defaultConfig(): array
    {
        return [
            'enabled' => false, 'button_text' => 'Menú', 'greeting' => '¡Hola!', 'width' => 300,
            'header_color' => '#F20D16', 'header_text_color' => '#FFFFFF', 'max_columns' => 4,
            'show_view_all' => true, 'show_badges' => true, 'show_images' => true, 'hide_empty' => true, 'max_categories' => 40, 'sort' => 'alpha', 'mobile_drill' => true,
            'transition_ms' => 200, 'promo_enabled' => false, 'promo_image' => '', 'promo_alt' => '', 'promo_href' => '',
            'popular' => true, 'popular_title' => 'Populares ahora', 'popular_limit' => 8, 'category_promos' => [],
            'show_info' => false, 'info_label' => 'Información', 'info_icon' => '📄', 'category_overrides' => [],
            'banner_enabled' => true, 'banner_title' => 'Descubre todo lo que SONIKA tiene para ti.', 'banner_text' => 'Explora nuestro catálogo de instrumentos y equipos musicales.', 'banner_button' => 'Explorar catálogo', 'banner_href' => '/tienda', 'banner_image' => '', 'brand_logos' => [],
            'footer_links' => [],
        ];
    }

    public function render(array $config = []): string
    {
        $cfg = $this->mergeConfig($config);
        if (empty($cfg['enabled'])) return '';

        $products = function_exists('sonika_products_effective') ? sonika_products_effective() : (function_exists('sonika_products') ? sonika_products() : null);
        $sort = ((string)($cfg['sort'] ?? 'alpha') === 'count') ? 'count' : 'alpha';
        $megaCats = is_array($cfg['categories'] ?? null) ? $cfg['categories'] : [];
        $tree = (new MegaMenuService())->tree($megaCats, $products, 90, $sort);
        $nodes = $tree['categories'] ?? [];
        $nodes = $this->applyOverrides($nodes, (array)($cfg['category_overrides'] ?? []));
        if (!empty($cfg['guitar_subs']) && is_array($cfg['guitar_subs'])) {
            $subs = [];
            $tot = 0;
            foreach ($cfg['guitar_subs'] as $sx) {
                $cnt = (int)($sx['count'] ?? 0);
                if ($cnt <= 0) continue;
                $tot += $cnt;
                $subs[] = ['name' => (string)($sx['name'] ?? ''), 'count' => $cnt, 'thumb' => (string)($sx['thumb'] ?? ''), 'url' => '/guitarras?sub=' . rawurlencode((string)($sx['slug'] ?? ''))];
            }
            if ($subs) {
                $hasG = false;
                foreach ($nodes as &$nn) {
                    if (($nn['slug'] ?? '') === 'guitarras') { $nn['types'] = $subs; $nn['count'] = $tot; $nn['url'] = '/guitarras'; $hasG = true; }
                }
                unset($nn);
                if (!$hasG) {
                    array_unshift($nodes, [
                        'slug' => 'guitarras', 'name' => 'Cuerdas', 'kind' => 'Categoría', 'icon' => '🎸', 'subtitle' => 'Guitarras · Bajos',
                        'url' => '/guitarras', 'count' => $tot, 'badge_new' => false, 'badge_offer' => false,
                        'types' => $subs, 'brands' => [], 'models' => [], 'level5' => [],
                        'kinds' => [2 => 'Tipo', 3 => 'Marca', 4 => 'Modelo'], 'hero' => '', 'samples' => [], 'prices' => [],
                    ]);
                }
            }
        }
        if (!empty($cfg['show_info'])) {
            $info = $this->infoNode((string)($cfg['info_label'] ?? 'Información'), (string)($cfg['info_icon'] ?? '📄'));
            if ($info) $nodes[] = $info;
        }
        $maxCat = (int)($cfg['max_categories'] ?? 40);
        if ($maxCat > 0 && count($nodes) > $maxCat) {
            $nodes = array_slice($nodes, 0, $maxCat);
        }
        if (!$nodes) return '';

        $width = max(220, min(520, (int)($cfg['width'] ?? 300)));
        $trans = max(0, min(1000, (int)($cfg['transition_ms'] ?? 200)));
        $headColor = $this->safeColor((string)($cfg['header_color'] ?? '#F20D16'), '#F20D16');
        $headText = $this->safeColor((string)($cfg['header_text_color'] ?? '#FFFFFF'), '#FFFFFF');
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
        $brandLogos = $this->brandLogoMap((array)($cfg['brand_logos'] ?? []));

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
            $slug = (string)$n['slug'];
            $h .= '<li class="mn-cat' . ($has ? ' has-panel' : '') . '" data-mn-cat="' . $this->esc($slug) . '">'
                . '<div class="mn-cat__row">'
                . '<a class="mn-cat__link" href="' . $this->esc($n['url']) . '">'
                . '<span class="mn-cat__ic" aria-hidden="true">' . $this->mnSvg((string)($n['icon'] !== '' ? $n['icon'] : $slug)) . '</span>'
                . '<span class="mn-cat__nm">' . $this->esc($n['name']) . '</span>'
                . ($showBadges && !empty($n['badge_new']) ? '<span class="mn-dot mn-dot--new" title="Nuevo" aria-label="Nuevo"></span>' : '')
                . ($showBadges && !empty($n['badge_offer']) ? '<span class="mn-dot mn-dot--off" title="Oferta" aria-label="Oferta"></span>' : '')
                . '<span class="mn-cat__ct">' . (int)$n['count'] . '</span>'
                . '</a>'
                . ($has ? '<button type="button" class="mn-cat__chev" aria-expanded="false" aria-controls="mn-sub-' . $this->esc($slug) . '" aria-label="Desplegar ' . $this->esc($n['name']) . '">' . $this->mnSvg('chevron') . '</button>' : '')
                . '</div>';
            if ($has && !empty($n['types'])) {
                $h .= '<ul class="mn-cat__sub" id="mn-sub-' . $this->esc($slug) . '" data-mn-sub="' . $this->esc($slug) . '" hidden>';
                foreach (array_slice($n['types'], 0, 14) as $t) $h .= '<li><a href="' . $this->esc($t['url']) . '">' . $this->esc($t['name']) . (isset($t['count']) ? ' <span>' . (int)$t['count'] . '</span>' : '') . '</a></li>';
                if (count($n['types']) > 14) $h .= '<li><a class="mn-cat__sub-all" href="' . $this->esc($n['url']) . '">Ver todo &#8250;</a></li>';
                $h .= '</ul>';
            }
            $h .= '</li>';
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
                . '<h3 class="mn-mega__title">' . $this->esc($n['name']) . '</h3>'
                . ($showAll ? '<a class="mn-mega__all" href="' . $this->esc($n['url']) . '">Ver todo el catálogo &#8250;</a>' : '')
                . '</header>';
            $k2 = (string)($n['kinds'][2] ?? 'Subcategoría');
            $hero = $this->heroConfig($n, $promoMap, $cfg, $showImages);
            $h .= '<div class="mn-top">';
            $h .= '<a class="mn-hero' . ($hero['image'] !== '' ? ' has-img' : '') . '" href="' . $this->esc($hero['href']) . '"'
                . ($hero['image'] !== '' ? ' style="--mn-hero-img:url(\'' . $this->esc($hero['image']) . '\')"' : '') . '>'
                . '<span class="mn-hero__scrim" aria-hidden="true"></span>'
                . '<span class="mn-hero__content">'
                . '<span class="mn-hero__eyebrow">' . $this->esc($hero['eyebrow']) . '</span>'
                . '<span class="mn-hero__title">' . $this->esc($hero['title']) . '</span>'
                . ($hero['text'] !== '' ? '<span class="mn-hero__text">' . $this->esc($hero['text']) . '</span>' : '')
                . ($hero['button'] !== '' ? '<span class="mn-hero__btn">' . $this->esc($hero['button']) . ' &#8250;</span>' : '')
                . '</span></a>';
            if (!empty($n['types'])) {
                $h .= '<div class="mn-block"><div class="mn-block__t">' . $this->esc($k2) . '</div><div class="mn-cards">';
                foreach (array_slice($n['types'], 0, 6) as $t) {
                    $cim = !empty($t['thumb']) ? (string)$t['thumb'] : (string)($n['catimg'] ?? '');
                    if ($cim === '' && !empty($n['types'][0]['thumb'])) $cim = (string)$n['types'][0]['thumb'];
                    $h .= '<a class="mn-card" href="' . $this->esc($t['url']) . '">'
                        . '<span class="mn-card__img">' . ($cim !== '' ? '<img src="' . $this->esc($cim) . '" alt="' . $this->esc($t['name']) . '" loading="lazy" decoding="async"/>' : '<span class="mn-card__ph" aria-hidden="true">&#9835;</span>') . '</span>'
                        . '<span class="mn-card__nm">' . $this->esc($t['name']) . '</span>'
                        . (isset($t['count']) ? '<span class="mn-card__ct">' . (int)$t['count'] . ' productos</span>' : '')
                        . '</a>';
                }
                if (count($n['types']) > 6 && $showAll) $h .= '<a class="mn-card mn-card--all" href="' . $this->esc($n['url']) . '"><span class="mn-card__nm">Ver todas &#8250;</span></a>';
                $h .= '</div></div>';
            }
            $h .= '</div>';
            if (!empty($n['samples'])) {
                $h .= '<div class="mn-block mn-block--samples"><div class="mn-block__t">Modelos destacados</div><div class="mn-products">';
                foreach (array_slice($n['samples'], 0, 6) as $s) {
                    $h .= '<a class="mn-prod" href="' . $this->esc($s['url']) . '">'
                        . '<span class="mn-prod__img"><img src="' . $this->esc($s['img']) . '" alt="' . $this->esc($s['title']) . '" loading="lazy"/></span>'
                        . (!empty($s['brand']) ? '<span class="mn-prod__brand">' . $this->esc($s['brand']) . '</span>' : '')
                        . '<span class="mn-prod__nm">' . $this->esc($s['title']) . '</span>'
                        . ($s['price'] > 0 ? '<span class="mn-prod__pr">' . $this->money((float)$s['price']) . '</span>' : '')
                        . '</a>';
                }
                $h .= '</div></div>';
            }
            if (!empty($n['brands'])) {
                $h .= '<div class="mn-block mn-block--brands"><div class="mn-block__t">Comprar por marca</div><div class="mn-brands">';
                foreach (array_slice($n['brands'], 0, 10) as $b) {
                    $lg = $brandLogos[strtolower((string)$b['name'])] ?? '';
                    $h .= '<a class="mn-brand" href="' . $this->esc($b['url']) . '">' . ($lg !== '' ? '<img class="mn-brand__logo" src="' . $this->esc($lg) . '" alt="' . $this->esc($b['name']) . '" loading="lazy"/>' : '<b>' . $this->esc($b['name']) . '</b>') . (isset($b['count']) ? '<span>' . (int)$b['count'] . '</span>' : '') . '</a>';
                }
                $h .= '</div></div>';
            }
            if (!empty($n['prices'])) {
                $h .= '<div class="mn-block mn-block--prices"><div class="mn-block__t">Precio</div><div class="mn-chips">';
                foreach ($n['prices'] as $pr) $h .= '<a class="mn-chip" href="' . $this->esc($pr['url']) . '">' . $this->esc($pr['name']) . '</a>';
                $h .= '</div></div>';
            }
            if (!empty($cfg['banner_enabled']) && trim((string)($cfg['banner_title'] ?? '')) !== '') {
                $bimg = trim((string)($cfg['banner_image'] ?? ''));
                $h .= '<a class="mn-banner' . ($bimg !== '' ? ' has-img' : '') . '" href="' . $this->esc((string)($cfg['banner_href'] ?? '/tienda')) . '"' . ($bimg !== '' ? ' style="--mn-banner-img:url(\'' . $this->esc($bimg) . '\')"' : '') . '>'
                    . '<b>' . $this->esc((string)$cfg['banner_title']) . '</b>'
                    . (trim((string)($cfg['banner_text'] ?? '')) !== '' ? '<span>' . $this->esc((string)$cfg['banner_text']) . '</span>' : '')
                    . (trim((string)($cfg['banner_button'] ?? '')) !== '' ? '<em>' . $this->esc((string)$cfg['banner_button']) . ' &#8250;</em>' : '')
                    . '</a>';
            }
            $h .= '</div></div>';
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
            $map[$key] = ['image' => $img, 'alt' => (string)($r['alt'] ?? ''), 'href' => (string)($r['href'] ?? ''), 'eyebrow' => (string)($r['eyebrow'] ?? ''), 'title' => (string)($r['title'] ?? ''), 'text' => (string)($r['text'] ?? ''), 'button' => (string)($r['button'] ?? '')];
        }
        return $map;
    }

    private function heroConfig(array $node, array $promoMap, array $cfg, bool $showImages): array
    {
        $name = (string)($node['name'] ?? '');
        $p = null;
        foreach ([strtolower((string)($node['slug'] ?? '')), strtolower($name)] as $k) {
            if ($k !== '' && isset($promoMap[$k])) { $p = $promoMap[$k]; break; }
        }
        $img = (is_array($p) && !empty($p['image'])) ? (string)$p['image'] : '';
        if ($img === '' && $showImages && !empty($cfg['promo_enabled'])) $img = (string)($cfg['promo_image'] ?? '');
        if ($img === '') $img = (string)($node['hero'] ?? '');
        if ($img === '' && $showImages) $img = (string)($node['catimg'] ?? '');
        if ($img === '' && $showImages) {
            foreach ((array)($node['samples'] ?? []) as $sp) { if (!empty($sp['img'])) { $img = (string)$sp['img']; break; } }
            if ($img === '') foreach ((array)($node['types'] ?? []) as $tp) { if (!empty($tp['thumb'])) { $img = (string)$tp['thumb']; break; } }
        }
        $ln = mb_strtolower($name, 'UTF-8');
        return [
            'image' => $img,
            'eyebrow' => (is_array($p) && !empty($p['eyebrow'])) ? (string)$p['eyebrow'] : mb_strtoupper($name, 'UTF-8'),
            'title' => (is_array($p) && !empty($p['title'])) ? (string)$p['title'] : ('Explora ' . $ln),
            'text' => (is_array($p) && !empty($p['text'])) ? (string)$p['text'] : ('Descubre ' . $ln . ' de las mejores marcas y modelos.'),
            'button' => (is_array($p) && !empty($p['button'])) ? (string)$p['button'] : ('Ver todos los ' . $ln),
            'href' => (is_array($p) && !empty($p['href'])) ? (string)$p['href'] : (string)($node['url'] ?? '/tienda'),
        ];
    }

    private function money(float $n): string
    {
        return $n > 0 ? '$' . number_format($n, 0, ',', '.') : '';
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

    private function brandLogoMap(array $rows): array
    {
        $m = [];
        foreach ($rows as $r) {
            if (!is_array($r)) continue;
            $b = strtolower(trim((string)($r['brand'] ?? '')));
            $img = trim((string)($r['image'] ?? ''));
            if ($b !== '' && $img !== '') $m[$b] = $img;
        }
        return $m;
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

    /** Iconos flat/outline (single-color, currentColor) por categoría — reemplazan emojis. */
    private function mnSvg(string $slug): string
    {
        $slug = trim($slug);
        if (str_starts_with($slug, '<svg')) {
            $svg = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $slug);
            $svg = preg_replace('/\bon\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', (string)$svg);
            $svg = preg_replace('/javascript:/i', '', (string)$svg);
            return (string)$svg;
        }
        $icons = [
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
            'star'      => '<path d="M12 3l2.6 5.4 5.9.8-4.3 4.1 1 5.9-5.2-2.8L7.8 19l1-5.9L4.5 9.2l5.9-.8z"/>',
            'tag'       => '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>',
            'chevron'   => '<path d="M9 6l6 6-6 6"/>',
            'info'      => '<path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z"/><path d="M14 3v5h5"/>',
        ];
        $p = $icons[$slug] ?? '<path d="M4 4h7v7H4zM13 4h7v7h-7zM4 13h7v7H4zM13 13h7v7h-7z"/>';
        return '<svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p . '</svg>';
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
.mn-cat__row{display:flex;align-items:center;position:relative;border-left:3px solid transparent;border-radius:10px}
.mn-cat__row:has(.mn-cat__link):hover{background:var(--soft,#F5F5F5)}
.mn-cat__link{display:flex;align-items:center;gap:10px;padding:11px 6px 11px 9px;text-decoration:none;color:var(--ink,#111);font-weight:600;font-size:14px;flex:1;min-width:0}
.mn-cat__link:hover{background:transparent}
.mn-cat.is-active .mn-cat__row{background:var(--soft,#F5F5F5);border-left-color:var(--mn-head,#F20D16)}
.mn-cat.is-active .mn-cat__chev{color:var(--mn-head,#F20D16)}
.mn-cat__ic{flex:0 0 20px;width:20px;height:20px;color:#3a3a3a;display:inline-flex;align-items:center;justify-content:center}
.mn-cat__ic svg{width:20px;height:20px;display:block}
.mn-cat__nm{flex:1;min-width:4ch;white-space:normal;line-height:1.25;overflow-wrap:break-word;word-break:normal;font-family:'Sora',sans-serif;font-weight:600;letter-spacing:-.01em}
.mn-cat__ct{font-size:11px;color:var(--muted,#999);background:var(--soft,#F1F1F1);border-radius:999px;padding:1px 8px;flex:0 0 auto}
.mn-dot{width:7px;height:7px;border-radius:50%;flex:0 0 auto}
.mn-dot--new{background:#111}
.mn-dot--off{background:var(--brand,#F20D16)}
.mn-cat__chev{flex:0 0 auto;border:0;background:transparent;color:#6b6b6b;cursor:pointer;padding:8px 10px;display:inline-flex;align-items:center;transition:transform .2s ease}
.mn-cat__chev svg{width:18px;height:18px}
.mn-cat__chev[aria-expanded="true"]{transform:rotate(90deg);color:var(--mn-head,#F20D16)}
.mn-cat__sub{list-style:none;margin:0 0 6px;padding:0 0 4px 30px;display:flex;flex-direction:column;gap:2px}
.mn-cat__sub[hidden]{display:none}
.mn-cat__sub li a{display:flex;justify-content:space-between;gap:8px;padding:7px 10px;border-radius:8px;text-decoration:none;color:var(--ink,#111);font-size:13px}
.mn-cat__sub li a:hover{background:var(--soft,#F5F5F5)}
.mn-cat__sub li a span{color:var(--muted,#999);font-size:11px}
.mn-cat__sub-all{color:var(--mn-head,#F20D16)!important;font-weight:700}
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
.mn-filter__hd{padding:9px 12px 3px;font-size:10.5px;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--muted,#999)}
.mn-filter__results li a.is-cur{background:var(--soft,#F5F5F5);box-shadow:inset 3px 0 0 var(--mn-head,#F20D16)}
.mn-filter__results mark{background:rgba(242,13,22,.16);color:inherit;padding:0 1px;border-radius:3px}
.mn-mega{background:#fff;border-right:1px solid var(--line,#E6E6E6);box-shadow:0 24px 60px rgba(0,0,0,.16);max-width:min(90vw,1000px);overflow:auto}
.mn-mega__panel{min-height:100%}
.mn-mega__bg{padding:0 0 22px}
.mn-mega__head{display:flex;align-items:center;gap:12px;background:var(--mn-head,#F20D16);color:var(--mn-head-txt,#fff);padding:15px 22px;position:sticky;top:0;z-index:2}
.mn-mega__ic{font-size:22px}
.mn-mega__title{font-family:var(--font-display,Sora),sans-serif;font-size:17px;font-weight:800;margin:0;flex:1}
.mn-mega__all{color:inherit;text-decoration:none;font-weight:700;font-size:12.5px;white-space:nowrap;background:rgba(255,255,255,.16);padding:7px 13px;border-radius:999px}
.mn-mega__all:hover{background:rgba(255,255,255,.3)}
.mn-back{display:none;border:0;background:transparent;color:inherit;font:inherit;font-weight:700;cursor:pointer;padding:0}
.mn-top{display:grid;grid-template-columns:minmax(0,1.05fr) minmax(0,.95fr);gap:18px;padding:18px 24px 6px;align-items:stretch}
.mn-hero{position:relative;display:block;border-radius:16px;overflow:hidden;min-height:236px;background:#111;color:#fff;text-decoration:none;box-shadow:0 10px 30px rgba(0,0,0,.14)}
.mn-hero.has-img{background-image:var(--mn-hero-img);background-size:cover;background-position:center center}
.mn-hero__scrim{position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,.02) 25%,rgba(0,0,0,.8) 100%)}
.mn-hero__content{position:absolute;inset:0;z-index:1;box-sizing:border-box;display:flex;flex-direction:column;justify-content:flex-end;align-items:flex-start;gap:7px;padding:22px 22px 24px 22px}
.mn-hero__eyebrow{font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;opacity:.9}
.mn-hero__title{font-family:var(--font-display,Sora),sans-serif;font-size:clamp(20px,2vw,28px);font-weight:800;line-height:1.1}
.mn-hero__text{font-size:13px;max-width:36ch;opacity:.92}
.mn-hero__btn{align-self:flex-start;margin-top:6px;background:#fff;color:#111;font-weight:800;font-size:12.5px;padding:8px 16px;border-radius:999px}
.mn-block__t{font-family:var(--font-display,Sora),sans-serif;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--muted,#8a8a8a);margin:0 0 10px}
.mn-top .mn-block{margin:0;display:flex;flex-direction:column;min-width:0}
.mn-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:10px}
.mn-card{display:flex;flex-direction:column;gap:6px;text-decoration:none;color:var(--ink,#111);background:#fff;border:1px solid var(--line,#ECECEC);border-radius:12px;padding:9px;transition:border-color .15s,box-shadow .15s}
.mn-card:hover{border-color:var(--brand,#F20D16);box-shadow:0 6px 18px rgba(242,13,22,.12)}
.mn-card__img{aspect-ratio:1/1;border-radius:9px;overflow:hidden;background:var(--soft,#F5F5F5);display:flex;align-items:center;justify-content:center}
.mn-card__img img{width:100%;height:100%;object-fit:cover}
.mn-card__ph{font-size:20px;opacity:.4}
.mn-card__nm{font-size:12.5px;font-weight:700;line-height:1.2;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.mn-card__ct{font-size:10.5px;color:var(--muted,#999)}
.mn-card--all{align-items:center;justify-content:center;border-style:dashed}
.mn-block--samples,.mn-block--brands,.mn-block--prices{padding:10px 24px 0}
.mn-products{display:grid;grid-template-columns:repeat(auto-fill,minmax(138px,1fr));gap:12px}
.mn-prod{display:flex;flex-direction:column;gap:4px;text-decoration:none;color:var(--ink,#111)}
.mn-prod__img{aspect-ratio:1/1;border-radius:12px;overflow:hidden;background:var(--soft,#F5F5F5);border:1px solid var(--line,#ECECEC)}
.mn-prod__img img{width:100%;height:100%;object-fit:cover}
.mn-prod:hover .mn-prod__img{border-color:var(--brand,#F20D16)}
.mn-prod__brand{font-size:10.5px;text-transform:uppercase;letter-spacing:.05em;color:var(--muted,#999);font-weight:700}
.mn-prod__nm{font-size:12px;font-weight:600;line-height:1.25;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
.mn-prod__pr{font-size:12.5px;font-weight:800}
.mn-brands{display:flex;flex-wrap:wrap;gap:8px}
.mn-brand{display:inline-flex;align-items:center;gap:8px;text-decoration:none;color:var(--ink,#111);border:1px solid var(--line,#ECECEC);border-radius:10px;padding:8px 12px;font-size:12.5px;font-weight:700}
.mn-brand:hover{border-color:var(--brand,#F20D16);color:var(--brand,#F20D16)}
.mn-brand span{color:var(--muted,#999);font-weight:600;font-size:11px}
.mn-brand__logo{height:20px;width:auto;max-width:84px;display:block;object-fit:contain}
.mn-chips{display:flex;flex-wrap:wrap;gap:6px}
.mn-mega .mn-chip{font-size:12px;padding:5px 11px;border-radius:999px;background:var(--soft,#F5F5F5);color:var(--ink,#222);text-decoration:none}
.mn-mega .mn-chip:hover{background:var(--brand,#F20D16);color:#fff}
.mn-banner{display:flex;flex-direction:column;gap:4px;margin:14px 24px 0;padding:16px 18px;border-radius:14px;background:linear-gradient(120deg,#111,#2b2b2b);color:#fff;text-decoration:none}
.mn-banner.has-img{background-image:var(--mn-banner-img);background-size:cover;background-position:center;box-shadow:inset 0 0 0 2000px rgba(0,0,0,.55)}
.mn-banner b{font-family:var(--font-display,Sora),sans-serif;font-size:15px}
.mn-banner span{font-size:12.5px;opacity:.85}
.mn-banner em{margin-top:8px;align-self:flex-start;font-style:normal;font-weight:800;font-size:12px;background:var(--brand,#F20D16);padding:7px 14px;border-radius:999px}
html.mn-locked,html.mn-locked body{overflow:hidden}
@media(max-width:900px){
  .mn-drawer{max-width:100vw}
  .mn-side{width:100%;max-width:none}
  .mn-mega{max-width:100vw}
  .mn-drawer.drill[data-view="panel"] .mn-side{display:none}
  .mn-drawer.drill:not([data-view="panel"]) .mn-mega{display:none}
  .mn-drawer.drill .mn-back{display:inline-block}
  .mn-top{grid-template-columns:1fr;padding:14px 16px 6px}
  .mn-cards{grid-template-columns:repeat(2,minmax(0,1fr))}
  .mn-block--samples,.mn-block--brands,.mn-block--prices{padding:10px 16px 0}
  .mn-banner{margin:12px 16px 0}
}
@media(max-width:520px){.mn-products{grid-template-columns:repeat(2,minmax(0,1fr))}}
@media(prefers-reduced-motion:reduce){.mn-drawer,.mn-overlay{transition:none}}
</style>
<script>if(!window.__mnInit){window.__mnInit=1;(function(){
  var D=document, state={open:false}, lastFocus=null, hoverT=null, searchT=null;
  function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]});}
  function hi(txt,q){txt=String(txt==null?'':txt);if(!q)return esc(txt);var i=txt.toLowerCase().indexOf(q);if(i<0)return esc(txt);return esc(txt.slice(0,i))+'<mark>'+esc(txt.slice(i,i+q.length))+'</mark>'+esc(txt.slice(i+q.length));}
  function recent(){try{return JSON.parse(localStorage.getItem('wwi_mn_recent')||'[]');}catch(e){return [];}}
  function pushRecent(q){q=String(q||'').trim();if(q.length<2)return;var r=recent().filter(function(x){return String(x).toLowerCase()!==q.toLowerCase()});r.unshift(q);try{localStorage.setItem('wwi_mn_recent',JSON.stringify(r.slice(0,6)));}catch(e){}}
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
    var rec=e.target.closest('[data-mn-rec]');
    if(rec){e.preventDefault();var fi=D.querySelector('.mn-filter__in');if(fi){fi.value=rec.getAttribute('data-mn-rec');fi.dispatchEvent(new Event('input',{bubbles:true}));fi.focus();}return;}
    var chev=e.target.closest('.mn-cat__chev');
    if(chev){e.preventDefault();var cli=chev.closest('.mn-cat');var csub=cli?cli.querySelector('.mn-cat__sub'):null;var copen=chev.getAttribute('aria-expanded')==='true';chev.setAttribute('aria-expanded',copen?'false':'true');if(csub)csub.hidden=copen;return;}
    var link=e.target.closest('.mn-cat__link');
    if(link&&state.open){var li=link.closest('.mn-cat'); if(li&&li.classList.contains('has-panel')){e.preventDefault();setActive(li.getAttribute('data-mn-cat'));}}
  },false);
  D.addEventListener('mouseover',function(e){
    if(!state.open)return; var li=e.target.closest&&e.target.closest('.mn-cat'); if(!li||!li.classList.contains('has-panel'))return;
    clearTimeout(hoverT); var slug=li.getAttribute('data-mn-cat'); hoverT=setTimeout(function(){setActive(slug);},300);
  },false);
  D.addEventListener('keydown',function(e){
    if(e.key==='Escape'&&state.open){e.preventDefault();openDrawer(false);return;}
    var inp=e.target.closest&&e.target.closest('.mn-filter__in'); if(!inp)return;
    var dr=drawer(); var res=dr?dr.querySelector('.mn-filter__results'):null; if(!res||res.hidden)return;
    var items=res.querySelectorAll('li a'); if(!items.length)return; var cur=-1;
    for(var i=0;i<items.length;i++){if(items[i].classList.contains('is-cur')){cur=i;break;}}
    if(e.key==='ArrowDown'){e.preventDefault();cur=Math.min(items.length-1,cur+1);}
    else if(e.key==='ArrowUp'){e.preventDefault();cur=Math.max(0,cur-1);}
    else if(e.key==='Enter'){if(cur>=0){e.preventDefault();items[cur].click();}return;}
    else return;
    for(var j=0;j<items.length;j++)items[j].classList.toggle('is-cur',j===cur);
    if(cur>=0&&items[cur].scrollIntoView)items[cur].scrollIntoView({block:'nearest'});
  });
  D.addEventListener('input',function(e){
    var inp=e.target.closest&&e.target.closest('.mn-filter__in'); if(!inp)return; var dr=drawer(); var q=inp.value.trim().toLowerCase(); var qq=inp.value.trim();
    var cats=dr.querySelectorAll('.mn-cat');
    for(var i=0;i<cats.length;i++){var nm=(cats[i].querySelector('.mn-cat__nm')||{}).textContent||''; cats[i].style.display=(!q||nm.toLowerCase().indexOf(q)>=0)?'':'none';}
    var res=dr.querySelector('.mn-filter__results'); var idx=D.querySelector('.mn-index');
    if(!q){clearTimeout(searchT);var rc=recent();if(res){if(rc.length){res.hidden=false;res.innerHTML='<li class="mn-filter__hd">Búsquedas recientes</li>'+rc.map(function(x){return '<li><a href="#" data-mn-rec="'+esc(x)+'"><span>'+esc(x)+'</span><small>reciente</small></a></li>';}).join('');}else{res.hidden=true;res.innerHTML='';}}return;}
    var data=[]; try{data=JSON.parse((idx&&idx.textContent)||'[]');}catch(x){}
    var hits=[],catHit=null;
    for(var k=0;k<data.length;k++){var n=data[k]; if(((n.n||'').toLowerCase().indexOf(q)>=0)||((n.c||'').toLowerCase().indexOf(q)>=0)){if(!catHit&&n.l===1)catHit=n; if(hits.length<20)hits.push(n);}}
    if(res){res.hidden=false; res.innerHTML=hits.map(function(n){return '<li><a href="'+esc(n.u)+'"><span>'+hi(n.n,q)+'</span><small>'+esc(n.c)+'</small></a></li>';}).join('');}
    if(catHit){var cs=dr.querySelector('.mn-cat[data-mn-cat="'+(catHit.c||'').toLowerCase().replace(/[^a-z0-9]+/g,'-')+'"]'); if(!cs){var all=dr.querySelectorAll('.mn-cat'); for(var z=0;z<all.length;z++){var an=(all[z].querySelector('.mn-cat__nm')||{}).textContent||''; if(an.toLowerCase()===catHit.n.toLowerCase()){cs=all[z];break;}}} if(cs)catHit=cs.getAttribute('data-mn-cat'); else catHit=null;}
    if(catHit)setActive(catHit);
    clearTimeout(searchT);
    if(qq.length>=2){ pushRecent(qq); searchT=setTimeout(function(){
      fetch('/buscar/sugerencias?q='+encodeURIComponent(qq)).then(function(r){return r.json();}).then(function(d){
        var items=((d&&d.data&&d.data.products)||d&&d.data||[]); if(!Object.prototype.toString.call(items).match(/Array/))items=[]; items=items.slice(0,6);
        var res2=dr.querySelector('.mn-filter__results'); if(!res2||!items.length)return;
        var ph=items.map(function(p){var u=p.handle?('/producto/'+esc(p.handle)):('/buscar?q='+encodeURIComponent(qq)); return '<li><a href="'+u+'"><span>'+hi(p.title||p.n||'',q)+'</span><small>'+esc(p.vendor||p.c||'')+'</small></a></li>';}).join('');
        res2.hidden=false; res2.innerHTML='<li class="mn-filter__hd">Productos</li>'+ph;
      }).catch(function(){});
    },220); }
  },false);
  if(D.readyState!=='loading')portal(); else D.addEventListener('DOMContentLoaded',portal);
})();}</script>
HTML;
    }
}
