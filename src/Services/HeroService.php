<?php
namespace App\Services;

use App\Core\Database;

/**
 * HeroService — SONIKA HERO BUILDER (Fase 3: base).
 * Slides de 2 zonas (izquierda/derecha) con tipos de contenido extensibles.
 * Borradores/activo, orden, duplicar, publicar, versiones. Multi-tenant (@site_id).
 */
class HeroService
{
    public const THEMES = [
        'classic' => ['name' => 'Clásico', 'description' => 'Hero actual basado en slides y sus dos zonas.'],
        'the-sound-takes-over' => ['name' => 'El Sonido Se Apodera', 'description' => 'Experiencia cinematográfica con copy editorial, categorías y beneficios.'],
    ];

    private const THEME_SETTING = 'theme.sonika.hero_theme';
    private const THEME_CONFIG_SETTING = 'theme.sonika.hero_theme_config';

    public const CONTENT_TYPES = [
        'text' => ['label' => 'Texto comercial', 'available' => true],
        'image' => ['label' => 'Imagen personalizada', 'available' => true],
        'video' => ['label' => 'Video personalizado', 'available' => true],
        'product' => ['label' => 'Producto del catálogo', 'available' => true],
        'combo' => ['label' => 'Combo o promoción', 'available' => true],
        'none' => ['label' => 'Sin contenido / ocultar zona', 'available' => true],
        'guitar' => ['label' => 'Instrumento: Guitarra eléctrica', 'available' => true, 'engine' => 'guitar'],
        'piano' => ['label' => 'Instrumento: Piano Casio', 'available' => false],
        'bass' => ['label' => 'Instrumento: Bajo', 'available' => false],
        'drums' => ['label' => 'Instrumento: Batería', 'available' => false],
        'tumbadora' => ['label' => 'Instrumento: Tumbadora', 'available' => false],
        'timbal' => ['label' => 'Instrumento: Timbal', 'available' => false],
        'sax' => ['label' => 'Instrumento: Saxofón', 'available' => false],
    ];

    private static bool $ensured = false;

    public function ensureTables(): array
    {
        if (self::$ensured) return ['ok' => true];
        $f = dirname(__DIR__, 2) . '/install/hero.sql';
        if (!is_file($f)) return ['ok' => false];
        $sql = preg_replace('/^\s*--.*$/m', '', (string)file_get_contents($f));
        $db = Database::instance();
        $n = 0;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $s) {
            if ($s === '') continue;
            try { $db->exec($s); $n++; } catch (\Throwable $e) { }
        }
        self::$ensured = true;
        return ['ok' => true, 'statements' => $n];
    }

    public function contentTypes(): array { return self::CONTENT_TYPES; }

    public function themes(): array
    {
        return self::THEMES;
    }

    public function activeTheme(): string
    {
        try {
            $st = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1");
            $st->execute([self::THEME_SETTING]);
            $v = trim((string)$st->fetchColumn());
            return isset(self::THEMES[$v]) ? $v : 'classic';
        } catch (\Throwable $e) {
            return 'classic';
        }
    }

    public function setTheme(string $id): array
    {
        if (!isset(self::THEMES[$id])) return ['ok' => false, 'message' => 'Theme no válido.'];
        try {
            $st = Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            $st->execute([self::THEME_SETTING, $id]);
            return ['ok' => true, 'active_theme' => $id, 'themes' => $this->themes()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar el theme.'];
        }
    }

    /**
     * Convierte una longitud editorial (px, pt o cm) a píxeles CSS.
     * Se usa tanto al guardar como al renderizar para que la vista previa
     * del admin y el sitio público midan exactamente igual.
     */
    public static function lengthToPx(float $value, string $unit): float
    {
        if ($unit === 'pt') return $value * 96 / 72;
        if ($unit === 'cm') return $value * 96 / 2.54;
        return $value;
    }

    public static function pxToLength(float $px, string $unit): float
    {
        if ($unit === 'pt') return $px * 72 / 96;
        if ($unit === 'cm') return $px * 2.54 / 96;
        return $px;
    }

    public function defaultThemeConfig(): array
    {
        $config = [
            'eyebrow' => 'SONIKA PRESENTA',
            'title' => "EL SONIDO\nSE *APODERA*.",
            'subtitle' => 'Tu música. Tu escenario. Tu momento.',
            'cta_label' => 'Explorar Instrumentos',
            'cta_url' => '/guitarras',
            'cta2_label' => 'Ver la Experiencia',
            'cta2_video' => 'https://www.youtube.com/watch?v=dsD03vmVe30',
            'autoplay' => 9000,
            'height' => 448,
            'focus_index' => 3,
            'focus_index_mobile' => 2,
            'focus_scale' => 132,
            'focus_scale_mobile' => 132,
            'edge_opacity' => 41,
            'edge_scale' => 85,
            'near_scale' => 90,
            'overlay' => 55,
            'show_categories' => 1,
            'show_benefits' => 1,
            'instrument_image_ms' => 5000,
            'instrument_label_max' => 16,
            'mobile_height' => 580,
            'mobile_description_lines' => 2,
            'mobile_copy_offset' => 0,
            'mobile_copy_offset_unit' => 'px',
            'mobile_cards_offset' => 0,
            'mobile_cards_offset_unit' => 'px',
            'mobile_group_scale' => 100,
            'mobile_eyebrow_scale' => 100,
            'mobile_title_scale' => 100,
            'mobile_sub_scale' => 100,
            'mobile_cta_scale' => 100,
            'mobile_cta_width_mode' => 'full',
            'mobile_cta_width_value' => 100,
            'mobile_cta_width_unit' => '%',
            'categories' => [
                ['label' => 'Guitarras', 'slug' => 'guitarras', 'href' => '/guitarras', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/3_c0fcc64c-78ef-4675-b9c0-04cc0127a521.png?v=1748015983&width=640', 'enabled' => 1],
                ['label' => 'Pianos', 'slug' => 'pianos', 'href' => '/pianos', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/1_82429ffb-d1db-43da-b2b2-f5d95dcb861a.png?v=1758898900&width=640', 'enabled' => 1],
                ['label' => 'Acordeones', 'slug' => 'acordeon', 'href' => '/acordeon', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/products/ACOR36.png?v=1717789153&width=640', 'enabled' => 1],
                ['label' => 'Baterías', 'slug' => 'baterias', 'href' => '/buscar?q=bateria', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/1_04b0d32c-6821-410d-865d-c04aba234935.png?v=1776099590&width=640', 'enabled' => 1],
                ['label' => 'Bajos', 'slug' => 'bajos', 'href' => '/guitarras?sub=bajos', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/BAJOELECFENDERSQDEBUTPBASSSUNBURTMOD0379700503.png?v=1791214002&width=640', 'enabled' => 1],
                ['label' => 'Violines', 'slug' => 'violines', 'href' => '/buscar?q=violin', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/1_1addf835-272f-4619-a99c-b104923b7845.png?v=1764197651&width=640', 'enabled' => 1],
                ['label' => 'Ukelele', 'slug' => 'ukelele', 'href' => '/buscar?q=ukelele', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/products/Ukelele_florecer_miche-instrumentos_musicales_tienda_en_linea-1.jpg?v=1631538264&width=640', 'enabled' => 1],
                ['label' => 'Saxofón', 'slug' => 'saxofon', 'href' => '/buscar?q=saxofon', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/ARTICULOSMICHE_3_211c9030-1f81-4ba1-b3d5-313d344763a3.png?v=1760458403&width=640', 'enabled' => 1],
                ['label' => 'Timbales', 'slug' => 'timbales', 'href' => '/buscar?q=timbal', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/LP1415-KP_5a484e93-8e61-4402-a275-91ed107e38b0.webp?v=1727973167&width=640', 'enabled' => 1],
                ['label' => 'Bongoes', 'slug' => 'bongoes', 'href' => '/buscar?q=bongo', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/products/BON067.png?v=1586384906&width=640', 'enabled' => 1],
                ['label' => 'Tumbadoras', 'slug' => 'tumbadoras', 'href' => '/buscar?q=tumbadora', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/products/CONGACP1212CHERRYENFIBRAVIDRIOTUMBACONAROCONFORTMICHETIENDACON137.png?v=1692204700&width=640', 'enabled' => 1],
                ['label' => 'Baquetas', 'slug' => 'baquetas', 'href' => '/buscar?q=baqueta', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/products/BAQ039.png?v=1574455593&width=640', 'enabled' => 1],
                ['label' => 'Micrófonos', 'slug' => 'microfono-miche', 'href' => '/microfono-miche', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/MICROFONOAUDIOTECHNICAAT2020CONDENSADOR.png?v=1791216440&width=640', 'enabled' => 1],
                ['label' => 'Cabinas', 'slug' => 'cabinas', 'href' => '/buscar?q=cabina', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/CABINAACTIVAEVETX-15SPUSSUBWOOFERETX-15SP.png?v=1790694499&width=640', 'enabled' => 1],
                ['label' => 'Amplificadores Fender', 'slug' => 'amplificadores-fender', 'href' => '/buscar?q=amplificador%20fender', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/2330300000_fen_amp_frt_1_nr.png?v=1741811461&width=640', 'enabled' => 1],
                ['label' => 'Audio Profesional', 'slug' => 'amplificadores', 'href' => '/amplificadores', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/1_175f6da6-d872-4f86-8371-b7976ffb28bc.png?v=1771707885&width=640', 'enabled' => 1],
                ['label' => 'Audífonos', 'slug' => 'audifonos', 'href' => '/buscar?q=audifono', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/AUDIFONOAUDIOTECHNICAATHM20XBTNEGROATHM20XBT.png?v=1790267571&width=640', 'enabled' => 1],
                ['label' => 'Cabezas móviles', 'slug' => 'cabezas-moviles', 'href' => '/buscar?q=cabeza%20movil', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/FORMATODECREACIONART_9_71047ce9-a78f-42f3-bf86-d73b40f047c5.png?v=1785446032&width=640', 'enabled' => 1],
                ['label' => 'Accesorios', 'slug' => 'cuerdas-guitarras', 'href' => '/cuerdas-guitarras', 'image' => 'https://cdn.shopify.com/s/files/1/0269/4178/4117/files/1_67a602cb-ddb7-41e0-b756-d3cb589f6893.png?v=1776956607&width=640', 'enabled' => 1],
            ],
            'benefits' => [
                ['icon' => 'truck', 'title' => 'Envío rápido', 'text' => 'a todo Colombia', 'enabled' => 1],
                ['icon' => 'shield', 'title' => 'Compra segura', 'text' => 'pagos 100% protegidos', 'enabled' => 1],
                ['icon' => 'card', 'title' => 'Cuotas fáciles', 'text' => 'con Sistecrédito y más', 'enabled' => 1],
                ['icon' => 'headset', 'title' => 'Asesoría experta', 'text' => 'antes y después de tu compra', 'enabled' => 1],
            ],
            'backgrounds' => [
                ['desktop' => '/assets/uploads/wontia_5ddad38e1699f7db.webp', 'mobile' => '/assets/uploads/wontia_5ddad38e1699f7db.webp', 'alt' => 'Primer plano de guitarra eléctrica', 'focal' => '50% 45%', 'enabled' => 1],
                ['desktop' => '/assets/uploads/wontia_2c020fab54677229.webp', 'mobile' => '/assets/uploads/wontia_4a97a4f3251c0c73.png', 'alt' => 'Escenario SONIKA con guitarra', 'focal' => '42% 50%', 'enabled' => 1],
            ],
        ];
        $config['slides'] = $this->defaultSlides($config);
        return $config;
    }

    private function defaultSlides(array $config): array
    {
        $rows = [];
        $copy = [
            'eyebrow' => (string)($config['eyebrow'] ?? ''),
            'title' => (string)($config['title'] ?? ''),
            'subtitle' => (string)($config['subtitle'] ?? ''),
            'cta_label' => (string)($config['cta_label'] ?? ''),
            'cta_url' => (string)($config['cta_url'] ?? ''),
            'cta2_label' => (string)($config['cta2_label'] ?? ''),
            'cta2_video' => (string)($config['cta2_video'] ?? ''),
            'cta2_url' => '',
        ];
        foreach (array_slice((array)($config['backgrounds'] ?? []), 0, 8) as $i => $bg) {
            $rows[] = array_merge($copy, [
                'id' => 'slide-' . ($i + 1),
                'enabled' => !empty($bg['enabled']) ? 1 : 0,
                'desktop' => (string)($bg['desktop'] ?? ''),
                'mobile' => (string)($bg['mobile'] ?? ''),
                'alt' => (string)($bg['alt'] ?? ''),
                'focal' => (string)($bg['focal'] ?? '50% 50%'),
                'overlay' => (int)($config['overlay'] ?? 55),
                'accent' => '#F20D16',
                'title_size_desktop' => 100,
                'title_size_desktop_unit' => '%',
                'title_size_mobile' => 100,
                'title_size_mobile_unit' => '%',
                'instrument_focus' => $i === 0 ? 'guitarras' : '',
            ]);
        }
        return $rows;
    }

    public function themeConfig(): array
    {
        $defaults = $this->defaultThemeConfig();
        try {
            $st = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = ? LIMIT 1");
            $st->execute([self::THEME_CONFIG_SETTING]);
            $raw = (string)$st->fetchColumn();
            $stored = $raw !== '' ? json_decode($raw, true) : null;
            if (!is_array($stored)) return $defaults;
            return $this->normalizeThemeConfig($stored, $defaults);
        } catch (\Throwable $e) {
            return $defaults;
        }
    }

    public function saveThemeConfig(array $input): array
    {
        $defaults = $this->defaultThemeConfig();
        $config = $this->normalizeThemeConfig($input, $defaults);
        try {
            $st = Database::instance()->prepare("INSERT INTO settings (site_id, `key`, `value`) VALUES (@site_id, ?, ?) ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)");
            $st->execute([self::THEME_CONFIG_SETTING, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]);
            return ['ok' => true, 'config' => $config];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'No se pudo guardar la configuración del theme.'];
        }
    }

    private function normalizeThemeConfig(array $input, array $defaults): array
    {
        $out = $defaults;

        $slides = $this->normalizeSlides($input, $defaults);
        $out['slides'] = $slides;

        $primary = null;
        foreach ($slides as $slide) {
            if (!empty($slide['enabled'])) { $primary = $slide; break; }
        }
        if (!$primary) $primary = $slides[0] ?? ($defaults['slides'][0] ?? []);

        $out['eyebrow'] = trim((string)($primary['eyebrow'] ?? $defaults['eyebrow']));
        $out['title'] = trim((string)($primary['title'] ?? $defaults['title']));
        $out['subtitle'] = trim((string)($primary['subtitle'] ?? $defaults['subtitle']));
        $out['cta_label'] = trim((string)($primary['cta_label'] ?? $defaults['cta_label']));
        $out['cta_url'] = $this->safeThemeUrl((string)($primary['cta_url'] ?? $defaults['cta_url']), '/guitarras');
        $out['cta2_label'] = trim((string)($primary['cta2_label'] ?? $defaults['cta2_label']));
        $out['cta2_video'] = $this->safeThemeUrl((string)($primary['cta2_video'] ?? $defaults['cta2_video']), '');

        $out['autoplay'] = max(0, min(30000, (int)($input['autoplay'] ?? $defaults['autoplay'])));
        $out['height'] = max(360, min(820, (int)($input['height'] ?? $defaults['height'])));
        $out['focus_index'] = max(1, min(6, (int)($input['focus_index'] ?? $defaults['focus_index'])));
        $out['focus_index_mobile'] = max(1, min(3, (int)($input['focus_index_mobile'] ?? $defaults['focus_index_mobile'])));
        $out['focus_scale'] = max(100, min(133, (int)($input['focus_scale'] ?? $defaults['focus_scale'])));
        $out['focus_scale_mobile'] = max(100, min(135, (int)($input['focus_scale_mobile'] ?? $defaults['focus_scale_mobile'])));
        $out['edge_opacity'] = max(20, min(100, (int)($input['edge_opacity'] ?? $defaults['edge_opacity'])));
        $out['edge_scale'] = max(70, min(100, (int)($input['edge_scale'] ?? $defaults['edge_scale'])));
        $out['near_scale'] = max(80, min(100, (int)($input['near_scale'] ?? $defaults['near_scale'])));
        $out['overlay'] = max(0, min(85, (int)($input['overlay'] ?? $defaults['overlay'])));
        $out['show_categories'] = !empty($input['show_categories'] ?? $defaults['show_categories']) ? 1 : 0;
        $out['show_benefits'] = !empty($input['show_benefits'] ?? $defaults['show_benefits']) ? 1 : 0;
        $out['instrument_image_ms'] = max(2000, min(12000, (int)($input['instrument_image_ms'] ?? $defaults['instrument_image_ms'])));
        $out['instrument_label_max'] = max(10, min(24, (int)($input['instrument_label_max'] ?? $defaults['instrument_label_max'])));

        $units = ['px', 'pt', 'cm'];
        $copyUnit = in_array((string)($input['mobile_copy_offset_unit'] ?? ''), $units, true)
            ? (string)$input['mobile_copy_offset_unit']
            : (string)($defaults['mobile_copy_offset_unit'] ?? 'px');
        $copyPx = self::lengthToPx((float)($input['mobile_copy_offset'] ?? $defaults['mobile_copy_offset']), $copyUnit);
        $copyPx = max(-100, min(240, $copyPx));
        $out['mobile_copy_offset'] = round(self::pxToLength($copyPx, $copyUnit), 2);
        $out['mobile_copy_offset_unit'] = $copyUnit;

        $cardsUnit = in_array((string)($input['mobile_cards_offset_unit'] ?? ''), $units, true)
            ? (string)$input['mobile_cards_offset_unit']
            : (string)($defaults['mobile_cards_offset_unit'] ?? 'px');
        $cardsPx = self::lengthToPx((float)($input['mobile_cards_offset'] ?? $defaults['mobile_cards_offset']), $cardsUnit);
        $cardsPx = max(-100, min(240, $cardsPx));
        $out['mobile_cards_offset'] = round(self::pxToLength($cardsPx, $cardsUnit), 2);
        $out['mobile_cards_offset_unit'] = $cardsUnit;

        $out['mobile_height'] = max(480, min(720, (int)($input['mobile_height'] ?? $defaults['mobile_height'])));
        $out['mobile_description_lines'] = max(1, min(4, (int)($input['mobile_description_lines'] ?? $defaults['mobile_description_lines'])));
        $out['mobile_group_scale'] = max(60, min(130, (int)($input['mobile_group_scale'] ?? $defaults['mobile_group_scale'])));
        $out['mobile_eyebrow_scale'] = max(60, min(140, (int)($input['mobile_eyebrow_scale'] ?? $defaults['mobile_eyebrow_scale'])));
        $out['mobile_title_scale'] = max(60, min(140, (int)($input['mobile_title_scale'] ?? $defaults['mobile_title_scale'])));
        $out['mobile_sub_scale'] = max(60, min(140, (int)($input['mobile_sub_scale'] ?? $defaults['mobile_sub_scale'])));
        $out['mobile_cta_scale'] = max(60, min(140, (int)($input['mobile_cta_scale'] ?? $defaults['mobile_cta_scale'])));

        $ctaModes = ['full', 'auto', 'custom'];
        $out['mobile_cta_width_mode'] = in_array((string)($input['mobile_cta_width_mode'] ?? ''), $ctaModes, true)
            ? (string)$input['mobile_cta_width_mode']
            : (string)($defaults['mobile_cta_width_mode'] ?? 'full');
        $ctaUnit = in_array((string)($input['mobile_cta_width_unit'] ?? ''), ['%', 'px'], true)
            ? (string)$input['mobile_cta_width_unit']
            : (string)($defaults['mobile_cta_width_unit'] ?? '%');
        $ctaValue = (int)($input['mobile_cta_width_value'] ?? $defaults['mobile_cta_width_value']);
        $out['mobile_cta_width_value'] = $ctaUnit === '%' ? max(20, min(100, $ctaValue)) : max(40, min(340, $ctaValue));
        $out['mobile_cta_width_unit'] = $ctaUnit;

        if (array_key_exists('categories', $input) && is_array($input['categories'])) {
            $categories = [];
            foreach (array_slice($input['categories'], 0, 24) as $row) {
                if (!is_array($row)) continue;
                $label = trim((string)($row['label'] ?? ''));
                if ($label === '') continue;
                $categories[] = [
                    'label' => mb_substr($label, 0, 80),
                    'slug' => preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($row['slug'] ?? ''))),
                    'href' => $this->safeThemeUrl((string)($row['href'] ?? ''), '#'),
                    'image' => $this->safeThemeImage((string)($row['image'] ?? '')),
                    'enabled' => !empty($row['enabled']) ? 1 : 0,
                    'highlight' => !empty($row['highlight']) ? 1 : 0,
                ];
            }
            $out['categories'] = $categories;
        }

        if (array_key_exists('benefits', $input) && is_array($input['benefits'])) {
            $benefits = [];
            foreach (array_slice($input['benefits'], 0, 6) as $row) {
                if (!is_array($row)) continue;
                $title = trim((string)($row['title'] ?? ''));
                if ($title === '') continue;
                $icon = preg_replace('/[^a-z0-9_\-]/i', '', (string)($row['icon'] ?? 'check')) ?: 'check';
                $benefits[] = [
                    'icon' => $icon,
                    'title' => mb_substr($title, 0, 80),
                    'text' => mb_substr(trim((string)($row['text'] ?? '')), 0, 140),
                    'enabled' => !empty($row['enabled']) ? 1 : 0,
                ];
            }
            $out['benefits'] = $benefits;
        }

        $out['backgrounds'] = $this->backgroundsFromSlides($slides);

        return $out;
    }

    private function normalizeSlides(array $input, array $defaults): array
    {
        $rows = [];
        if (isset($input['slides']) && is_array($input['slides'])) {
            $rows = $input['slides'];
        } elseif (isset($input['backgrounds']) && is_array($input['backgrounds'])) {
            $copy = [
                'eyebrow' => (string)($input['eyebrow'] ?? $defaults['eyebrow']),
                'title' => (string)($input['title'] ?? $defaults['title']),
                'subtitle' => (string)($input['subtitle'] ?? $defaults['subtitle']),
                'cta_label' => (string)($input['cta_label'] ?? $defaults['cta_label']),
                'cta_url' => (string)($input['cta_url'] ?? $defaults['cta_url']),
                'cta2_label' => (string)($input['cta2_label'] ?? $defaults['cta2_label']),
                'cta2_video' => (string)($input['cta2_video'] ?? $defaults['cta2_video']),
                'cta2_url' => '',
            ];
            foreach (array_slice($input['backgrounds'], 0, 8) as $i => $bg) {
                if (!is_array($bg)) continue;
                $rows[] = array_merge($copy, [
                    'id' => 'slide-' . ($i + 1),
                    'enabled' => !empty($bg['enabled']) ? 1 : 0,
                    'desktop' => (string)($bg['desktop'] ?? ''),
                    'mobile' => (string)($bg['mobile'] ?? ''),
                    'alt' => (string)($bg['alt'] ?? ''),
                    'focal' => (string)($bg['focal'] ?? '50% 50%'),
                    'overlay' => $input['overlay'] ?? null,
                    'accent' => '#F20D16',
                    'title_size_desktop' => 100,
                    'title_size_desktop_unit' => '%',
                    'title_size_mobile' => 100,
                    'title_size_mobile_unit' => '%',
                    'instrument_focus' => $i === 0 ? 'guitarras' : '',
                ]);
            }
        } else {
            return $defaults['slides'] ?? [];
        }

        $slides = [];
        $usedIds = [];
        foreach (array_slice($rows, 0, 8) as $i => $row) {
            if (!is_array($row)) continue;

            $desktop = $this->safeThemeImage((string)($row['desktop'] ?? ''));
            $mobile = $this->safeThemeImage((string)($row['mobile'] ?? ''));
            $eyebrow = trim((string)($row['eyebrow'] ?? ''));
            $title = trim((string)($row['title'] ?? ''));
            $subtitle = trim((string)($row['subtitle'] ?? ''));
            $ctaLabel = trim((string)($row['cta_label'] ?? ''));
            $cta2Label = trim((string)($row['cta2_label'] ?? ''));
            $cta2Video = $this->safeThemeUrl((string)($row['cta2_video'] ?? ''), '');
            $cta2Url = $this->safeThemeUrl((string)($row['cta2_url'] ?? ''), '');

            if ($desktop === '' && $mobile === '' && $eyebrow === '' && $title === '' && $subtitle === '') continue;

            $id = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($row['id'] ?? '')));
            if ($id === '') $id = 'slide-' . ($i + 1);
            if (isset($usedIds[$id])) {
                $base = $id;
                $n = 2;
                while (isset($usedIds[$id . '-' . $n])) $n++;
                $id = $id . '-' . $n;
            }
            $usedIds[$id] = 1;

            $focal = trim((string)($row['focal'] ?? '50% 50%'));
            if (!preg_match('/^\d{1,3}%\s+\d{1,3}%$/', $focal)) $focal = '50% 50%';

            $overlayRaw = $row['overlay'] ?? '';
            $overlay = ($overlayRaw === '' || $overlayRaw === null) ? null : max(0, min(85, (int)$overlayRaw));

            $accent = trim((string)($row['accent'] ?? ''));
            if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $accent)) $accent = '#F20D16';

            $legacySize = max(40, min(200, (float)($row['title_size'] ?? 100)));
            $allowedUnits = ['%', 'pt', 'px'];
            $desktopUnit = (string)($row['title_size_desktop_unit'] ?? '%');
            if (!in_array($desktopUnit, $allowedUnits, true)) $desktopUnit = '%';
            $mobileUnit = (string)($row['title_size_mobile_unit'] ?? '%');
            if (!in_array($mobileUnit, $allowedUnits, true)) $mobileUnit = '%';
            $desktopSize = $this->normalizeTitleSize((float)($row['title_size_desktop'] ?? $legacySize), $desktopUnit);
            $mobileSize = $this->normalizeTitleSize((float)($row['title_size_mobile'] ?? $legacySize), $mobileUnit);

            $slides[] = [
                'id' => $id,
                'enabled' => !empty($row['enabled']) ? 1 : 0,
                'eyebrow' => mb_substr($eyebrow, 0, 80),
                'title' => mb_substr($title, 0, 200),
                'subtitle' => mb_substr($subtitle, 0, 500),
                'cta_label' => mb_substr($ctaLabel, 0, 80),
                'cta_url' => $this->safeThemeUrl((string)($row['cta_url'] ?? ''), ''),
                'cta2_label' => mb_substr($cta2Label, 0, 80),
                'cta2_video' => $cta2Video,
                'cta2_url' => $cta2Url,
                'desktop' => $desktop !== '' ? $desktop : $mobile,
                'mobile' => $mobile,
                'alt' => mb_substr(trim((string)($row['alt'] ?? '')), 0, 160),
                'focal' => $focal,
                'overlay' => $overlay,
                'accent' => $accent,
                'title_size_desktop' => $desktopSize,
                'title_size_desktop_unit' => $desktopUnit,
                'title_size_mobile' => $mobileSize,
                'title_size_mobile_unit' => $mobileUnit,
                'instrument_focus' => preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($row['instrument_focus'] ?? ''))),
            ];
        }

        return $slides;
    }

    private function normalizeTitleSize(float $value, string $unit): float
    {
        if ($unit === 'pt') return round(max(8, min(120, $value)), 2);
        if ($unit === 'px') return round(max(10, min(180, $value)), 2);
        return round(max(40, min(200, $value)), 2);
    }

    private function backgroundsFromSlides(array $slides): array
    {
        $out = [];
        foreach ($slides as $slide) {
            $out[] = [
                'desktop' => (string)($slide['desktop'] ?? ''),
                'mobile' => (string)($slide['mobile'] ?? ''),
                'alt' => (string)($slide['alt'] ?? ''),
                'focal' => (string)($slide['focal'] ?? '50% 50%'),
                'enabled' => !empty($slide['enabled']) ? 1 : 0,
            ];
        }
        return $out;
    }

    private function safeThemeUrl(string $url, string $fallback = ''): string
    {
        $url = trim($url);
        if ($url === '') return $fallback;
        if ($url[0] === '/' && !str_starts_with($url, '//')) return $url;
        if (str_starts_with($url, '#')) return $url;
        if (preg_match('~^https?://~i', $url)) return $url;
        return $fallback;
    }

    private function safeThemeImage(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if ($url[0] === '/' && !str_starts_with($url, '//')) return $url;
        if (preg_match('~^https?://~i', $url)) return $url;
        return '';
    }

    public function all(): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT * FROM wwi_hero_slides WHERE site_id = @site_id ORDER BY sort_order ASC, id ASC");
        $st->execute();
        return array_map([$this, 'hydrate'], $st->fetchAll());
    }

    public function published(): array
    {
        $this->ensureTables();
        $st = Database::instance()->prepare("SELECT * FROM wwi_hero_slides WHERE site_id = @site_id AND status = 'active' ORDER BY sort_order ASC, id ASC");
        $st->execute();
        return array_map([$this, 'hydrate'], $st->fetchAll());
    }

    public function get(int $id): ?array
    {
        $st = Database::instance()->prepare("SELECT * FROM wwi_hero_slides WHERE id = :id AND site_id = @site_id LIMIT 1");
        $st->execute(['id' => $id]);
        $r = $st->fetch();
        return $r ? $this->hydrate($r) : null;
    }

    public function save(array $d): array
    {
        $this->ensureTables();
        $db = Database::instance();
        $id = (int)($d['id'] ?? 0);
        $name = trim((string)($d['name'] ?? 'Slide')) ?: 'Slide';
        $status = (($d['status'] ?? 'draft') === 'active') ? 'active' : 'draft';
        $layoutIn = (string)($d['layout'] ?? 'split');
        $layout = in_array($layoutIn, ['split', 'background', 'full', 'left', 'right'], true) ? $layoutIn : 'split';
        $lt = array_key_exists((string)($d['left_type'] ?? ''), self::CONTENT_TYPES) ? (string)$d['left_type'] : 'text';
        $rt = array_key_exists((string)($d['right_type'] ?? ''), self::CONTENT_TYPES) ? (string)$d['right_type'] : 'text';
        if ($layout === 'background') {
            $lt = 'text';
            $rt = 'none';
        }
        if ($status === 'active' && (!self::CONTENT_TYPES[$lt]['available'] || !self::CONTENT_TYPES[$rt]['available'])) $status = 'draft';
        $ld = json_encode($d['left_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $rd = json_encode($d['right_data'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $de = json_encode($d['design'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($id > 0) {
            $cur = $this->get($id);
            if ($cur) $this->snapshot($id, $cur, (string)($d['actor'] ?? ''));
            $db->prepare("UPDATE wwi_hero_slides SET name=:n,status=:s,layout=:l,left_type=:lt,left_data=:ld,right_type=:rt,right_data=:rd,design=:de WHERE id=:id AND site_id=@site_id")
                ->execute(['n' => $name, 's' => $status, 'l' => $layout, 'lt' => $lt, 'ld' => $ld, 'rt' => $rt, 'rd' => $rd, 'de' => $de, 'id' => $id]);
        } else {
            $mx = (int)$db->query("SELECT COALESCE(MAX(sort_order),0)+1 FROM wwi_hero_slides WHERE site_id = @site_id")->fetchColumn();
            $db->prepare("INSERT INTO wwi_hero_slides (site_id,uuid,name,status,sort_order,layout,left_type,left_data,right_type,right_data,design) VALUES (@site_id,:u,:n,:s,:o,:l,:lt,:ld,:rt,:rd,:de)")
                ->execute(['u' => $this->uuid4(), 'n' => $name, 's' => $status, 'o' => $mx, 'l' => $layout, 'lt' => $lt, 'ld' => $ld, 'rt' => $rt, 'rd' => $rd, 'de' => $de]);
            $id = (int)$db->lastInsertId();
        }
        return ['ok' => true, 'slide' => $this->get($id)];
    }

    public function duplicate(int $id): array
    {
        $s = $this->get($id);
        if (!$s) return ['ok' => false, 'message' => 'No existe'];
        $db = Database::instance();
        $db->prepare("INSERT INTO wwi_hero_slides (site_id,uuid,name,status,sort_order,layout,left_type,left_data,right_type,right_data,design) VALUES (@site_id,:u,:n,'draft',:o,:l,:lt,:ld,:rt,:rd,:de)")
            ->execute(['u' => $this->uuid4(), 'n' => $s['name'] . ' (copia)', 'o' => $s['sort_order'] + 1, 'l' => $s['layout'], 'lt' => $s['left_type'], 'ld' => json_encode($s['left_data'], JSON_UNESCAPED_UNICODE), 'rt' => $s['right_type'], 'rd' => json_encode($s['right_data'], JSON_UNESCAPED_UNICODE), 'de' => json_encode($s['design'], JSON_UNESCAPED_UNICODE)]);
        return ['ok' => true, 'id' => (int)$db->lastInsertId()];
    }

    public function delete(int $id): array
    {
        Database::instance()->prepare("DELETE FROM wwi_hero_slides WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        return ['ok' => true];
    }

    public function reorder(array $ids): array
    {
        $db = Database::instance();
        $st = $db->prepare("UPDATE wwi_hero_slides SET sort_order = :o WHERE id = :id AND site_id = @site_id");
        $i = 0;
        foreach ($ids as $id) { $st->execute(['o' => $i++, 'id' => (int)$id]); }
        return ['ok' => true];
    }

    public function publish(int $id): array
    {
        $s = $this->get($id);
        if (!$s) return ['ok' => false, 'message' => 'No existe'];
        foreach ([$s['left_type'], $s['right_type']] as $t) {
            if (!self::CONTENT_TYPES[$t]['available']) return ['ok' => false, 'message' => 'No se puede publicar: el motor de “' . self::CONTENT_TYPES[$t]['label'] . '” no está implementado.'];
        }
        if ((string)($s['layout'] ?? 'split') === 'background') {
            $bg = (array)($s['design'] ?? []);
            $bt = (string)($bg['bg_type'] ?? 'image');
            $hasMedia = $bt === 'image'
                ? trim((string)($bg['bg_image'] ?? '')) !== ''
                : ($bt === 'video'
                    ? (trim((string)($bg['bg_video'] ?? '')) !== '' || trim((string)($bg['bg_poster'] ?? '')) !== '')
                    : (trim((string)($bg['bg_yt'] ?? '')) !== '' || trim((string)($bg['bg_poster'] ?? '')) !== ''));
            if (!$hasMedia) return ['ok' => false, 'message' => 'No se puede publicar: configura una imagen, video o poster de fondo.'];
        }
        $this->snapshot($id, $s, 'publish');
        Database::instance()->prepare("UPDATE wwi_hero_slides SET status='active' WHERE id = :id AND site_id = @site_id")->execute(['id' => $id]);
        return ['ok' => true];
    }

    public function versions(int $slideId): array
    {
        $st = Database::instance()->prepare("SELECT id, created_at, created_by FROM wwi_hero_versions WHERE slide_id = :s AND site_id = @site_id ORDER BY id DESC LIMIT 30");
        $st->execute(['s' => $slideId]);
        return $st->fetchAll();
    }

    public function version(int $id): ?array
    {
        $st = Database::instance()->prepare("SELECT id, slide_id, data, created_by, created_at FROM wwi_hero_versions WHERE id = :id AND site_id = @site_id LIMIT 1");
        $st->execute(['id' => $id]);
        $v = $st->fetch();
        if (!$v) return null;
        $v['data'] = json_decode((string)$v['data'], true) ?: [];
        return $v;
    }

    public function restore(int $versionId): array
    {
        $st = Database::instance()->prepare("SELECT slide_id, data FROM wwi_hero_versions WHERE id = :id AND site_id = @site_id LIMIT 1");
        $st->execute(['id' => $versionId]);
        $v = $st->fetch();
        if (!$v) return ['ok' => false, 'message' => 'Versión no encontrada'];
        $x = json_decode((string)$v['data'], true) ?: [];
        $x['id'] = (int)$v['slide_id'];
        return $this->save($x);
    }

    private function snapshot(int $id, array $slide, string $actor): void
    {
        try {
            Database::instance()->prepare("INSERT INTO wwi_hero_versions (site_id, slide_id, data, created_by) VALUES (@site_id, :s, :d, :a)")
                ->execute(['s' => $id, 'd' => json_encode($slide, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'a' => $actor]);
        } catch (\Throwable $e) {
        }
    }

    private function hydrate(array $r): array
    {
        foreach (['left_data', 'right_data', 'design'] as $k) $r[$k] = json_decode((string)($r[$k] ?? ''), true) ?: [];
        $r['id'] = (int)$r['id'];
        $r['sort_order'] = (int)$r['sort_order'];
        return $r;
    }

    private function uuid4(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}
