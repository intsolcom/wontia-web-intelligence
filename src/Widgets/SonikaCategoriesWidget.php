<?php
namespace App\Widgets;

class SonikaCategoriesWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'sonika-categories', 'name' => 'SONIKA — Explora por categoría (carrusel)', 'icon' => 'grid', 'category' => 'home', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => '_h_cont', 'label' => 'Cabecera', 'type' => 'heading'],
            ['key' => 'title', 'label' => 'Título', 'type' => 'text', 'default' => 'Explora por categoría'],
            ['key' => 'view_all_label', 'label' => 'Texto "Ver todo"', 'type' => 'text', 'default' => 'Ver todo →'],
            ['key' => 'view_all_url', 'label' => 'URL "Ver todo"', 'type' => 'text', 'default' => '/tienda'],
            ['key' => '_h_content', 'label' => 'Categorías', 'type' => 'heading'],
            ['key' => 'categories', 'label' => 'Fichas (vacío = las 8 del catálogo)', 'type' => 'repeater', 'subfields' => [
                ['key' => 'slug', 'label' => 'Slug de categoría', 'type' => 'text'],
                ['key' => 'label', 'label' => 'Nombre', 'type' => 'text'],
                ['key' => 'subtitle', 'label' => 'Subtítulo', 'type' => 'text'],
                ['key' => 'image', 'label' => 'Imagen', 'type' => 'image'],
            ], 'default' => []],
            ['key' => '_h_car', 'label' => 'Carrusel', 'type' => 'heading'],
            ['key' => 'rows', 'label' => 'Filas', 'type' => 'select', 'options' => ['1' => '1 fila', '2' => '2 filas'], 'default' => '2'],
            ['key' => 'per_view', 'label' => 'Fichas visibles por fila (solo Clásico)', 'type' => 'number', 'default' => '4'],
            ['key' => 'speed', 'label' => 'Velocidad (segundos entre avances)', 'type' => 'number', 'default' => '6'],
            ['key' => 'autoplay', 'label' => 'Reproducción automática', 'type' => 'toggle', 'default' => true],
            ['key' => 'pause_hover', 'label' => 'Pausar al pasar el cursor', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_arrows', 'label' => 'Mostrar flechas', 'type' => 'toggle', 'default' => true],
            ['key' => '_h_theme', 'label' => 'Theme visual del carrusel', 'type' => 'heading'],
            ['key' => 'theme', 'label' => 'Theme del carrusel', 'type' => 'select', 'options' => [
                'classic' => 'Clásico (actual, claro)',
                'midnight-blue' => 'Midnight Blue — azul marino',
                'electric-violet' => 'Electric Violet — violeta eléctrico',
                'emerald-pulse' => 'Emerald Pulse — verde esmeralda',
                'arctic-cyan' => 'Arctic Cyan — cyan ártico',
                'amber-stage' => 'Amber Stage — ámbar escénico',
                'monochrome' => 'Monochrome — monocromático',
            ], 'default' => 'classic'],
            ['key' => 'card_glow', 'label' => 'Glow en hover', 'type' => 'toggle', 'default' => true],
            ['key' => 'hover_lift', 'label' => 'Elevar tarjeta en hover', 'type' => 'toggle', 'default' => true],
            ['key' => 'transition_ms', 'label' => 'Duración de transición (ms)', 'type' => 'number', 'default' => '220'],
            ['key' => 'card_width', 'label' => 'Ancho de tarjeta (px, themes Spectrum)', 'type' => 'number', 'default' => '220'],
            ['key' => 'card_height', 'label' => 'Alto de tarjeta (px, themes Spectrum)', 'type' => 'number', 'default' => '190'],
            ['key' => '_h_card', 'label' => 'Apariencia de las fichas', 'type' => 'heading'],
            ['key' => 'shape', 'label' => 'Forma', 'type' => 'select', 'options' => ['rounded' => 'Redondeada', 'square' => 'Cuadrada (sin redondeo)'], 'default' => 'rounded'],
            ['key' => 'radius', 'label' => 'Radio (px, si es redondeada)', 'type' => 'number', 'default' => '18'],
            ['key' => 'aspect', 'label' => 'Proporción', 'type' => 'select', 'options' => ['3/4' => 'Vertical (3:4)', '1/1' => 'Cuadrada (1:1)', '4/3' => 'Horizontal (4:3)', '16/9' => 'Panorámica (16:9)'], 'default' => '3/4'],
            ['key' => 'accent', 'label' => 'Acento', 'type' => 'color', 'default' => '#F20D16'],
            ['key' => 'show_badges', 'label' => 'Mostrar NUEVO/OFERTA', 'type' => 'toggle', 'default' => true],
            ['key' => 'gap', 'label' => 'Separación (px)', 'type' => 'number', 'default' => '16'],
            ['key' => '_h_layout', 'label' => 'Contenedor y título', 'type' => 'heading'],
            ['key' => 'max_width', 'label' => 'Ancho máximo (px, 0 = todo el ancho)', 'type' => 'number', 'default' => '1180'],
            ['key' => 'top_space', 'label' => 'Espacio superior (px)', 'type' => 'number', 'default' => '0'],
            ['key' => 'side_pad', 'label' => 'Espacio lateral de seguridad (px)', 'type' => 'number', 'default' => '22'],
            ['key' => 'title_color', 'label' => 'Color del título', 'type' => 'color', 'default' => '#111111'],
            ['key' => 'title_align', 'label' => 'Alineación del título', 'type' => 'select', 'options' => ['left' => 'Izquierda', 'center' => 'Centrado', 'right' => 'Derecha'], 'default' => 'left'],
            ['key' => 'title_size', 'label' => 'Tamaño del título (px)', 'type' => 'number', 'default' => '30'],
            ['key' => 'top_space', 'label' => 'Espacio superior / separación de la sección anterior (px)', 'type' => 'number', 'default' => '24'],
            ['key' => 'direction', 'label' => 'Dirección del carrusel', 'type' => 'select', 'options' => ['left' => 'Automático hacia la izquierda (avanza)', 'right' => 'Automático hacia la derecha (retrocede)'], 'default' => 'left'],
        ];
    }

    public static function defaultConfig(): array
    {
        $c = [];
        foreach (self::configSchema() as $f) if (isset($f['default'])) $c[$f['key']] = $f['default'];
        return $c;
    }

    private function col(string $v, string $d): string
    {
        return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v) ? $v : $d;
    }

    private static function spectrumThemes(): array
    {
        return [
            'midnight-blue' => [
                'c1' => '#07111F', 'c2' => '#0D1B2A', 'border' => '#244C78', 'accent' => '#3B82F6',
                'glow' => 'rgba(59,130,246,.22)', 'text' => '#FFFFFF', 'muted' => '#A8B5C7',
            ],
            'electric-violet' => [
                'c1' => '#0B0918', 'c2' => '#171126', 'border' => '#50358B', 'accent' => '#A855F7',
                'glow' => 'rgba(168,85,247,.24)', 'text' => '#FFFFFF', 'muted' => '#C4B5FD',
            ],
            'emerald-pulse' => [
                'c1' => '#07130F', 'c2' => '#10231B', 'border' => '#245E45', 'accent' => '#34D399',
                'glow' => 'rgba(52,211,153,.20)', 'text' => '#FFFFFF', 'muted' => '#A7C8B7',
            ],
            'arctic-cyan' => [
                'c1' => '#06131B', 'c2' => '#0D202B', 'border' => '#176A82', 'accent' => '#22D3EE',
                'glow' => 'rgba(34,211,238,.20)', 'text' => '#FFFFFF', 'muted' => '#A8D7E0',
            ],
            'amber-stage' => [
                'c1' => '#15100A', 'c2' => '#21180F', 'border' => '#805421', 'accent' => '#F59E0B',
                'glow' => 'rgba(245,158,11,.20)', 'text' => '#FFFFFF', 'muted' => '#D6C2A3',
            ],
            'monochrome' => [
                'c1' => '#09090B', 'c2' => '#18181B', 'border' => '#52525B', 'accent' => '#D4D4D8',
                'glow' => 'rgba(212,212,216,.16)', 'text' => '#FFFFFF', 'muted' => '#A1A1AA',
            ],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $rows = ((int)($c['rows'] ?? 2)) === 1 ? 1 : 2;
        $per = max(1, min(6, (int)($c['per_view'] ?? 4)));
        $speed = max(1, min(120, (int)($c['speed'] ?? 6))) * 1000;
        $auto = !empty($c['autoplay']);
        $pause = !empty($c['pause_hover']);
        $arrows = !empty($c['show_arrows']);
        $shape = (string)($c['shape'] ?? 'rounded') === 'square' ? 'square' : 'rounded';
        $radius = $shape === 'square' ? 0 : max(0, min(48, (int)($c['radius'] ?? 18)));
        $aspect = in_array((string)($c['aspect'] ?? '3/4'), ['3/4', '1/1', '4/3', '16/9'], true) ? (string)$c['aspect'] : '3/4';
        $themes = self::spectrumThemes();
        $themeId = (string)($c['theme'] ?? 'classic');
        $isSpectrum = isset($themes[$themeId]);
        if (!$isSpectrum) $themeId = 'classic';
        $theme = $isSpectrum ? $themes[$themeId] : [];
        $accent = $this->col((string)($c['accent'] ?? ''), '#F20D16');
        if ($isSpectrum) $accent = (string)$theme['accent'];
        $gap = max(0, min(40, (int)($c['gap'] ?? 16)));
        $showBadges = !empty($c['show_badges']);
        $maxw = max(0, min(2400, (int)($c['max_width'] ?? 1180)));
        $sidepad = max(0, min(200, (int)($c['side_pad'] ?? 22)));
        $topSpace = max(0, min(300, (int)($c['top_space'] ?? 0)));
        $titleColor = $this->col((string)($c['title_color'] ?? ''), '#111111');
        if ($isSpectrum) $titleColor = (string)$theme['text'];
        $titleAlign = in_array((string)($c['title_align'] ?? 'left'), ['left', 'center', 'right'], true) ? (string)$c['title_align'] : 'left';
        $titleSize = max(12, min(72, (int)($c['title_size'] ?? 30)));
        $topSpace = max(0, min(240, (int)($c['top_space'] ?? 24)));
        $dir = (string)($c['direction'] ?? 'left') === 'right' ? -1 : 1;
        $glow = !isset($c['card_glow']) || !empty($c['card_glow']);
        $lift = !isset($c['hover_lift']) || !empty($c['hover_lift']);
        $dur = max(80, min(800, (int)($c['transition_ms'] ?? 220)));
        $cardW = max(140, min(320, (int)($c['card_width'] ?? 220)));
        $cardH = max(140, min(320, (int)($c['card_height'] ?? 190)));
        $rootClass = 'scats' . ($isSpectrum ? ' scats--spectrum scats--theme-' . $themeId : '') . (!$glow ? ' scats--no-glow' : '') . (!$lift ? ' scats--no-lift' : '');
        $rootStyle = $isSpectrum
            ? '--scats-bg:' . $theme['c1'] . ';--scats-card:' . $theme['c2'] . ';--scats-border:' . $theme['border'] . ';--scats-accent:' . $theme['accent'] . ';--scats-glow:' . $theme['glow'] . ';--scats-text:' . $theme['text'] . ';--scats-muted:' . $theme['muted'] . ';--scats-dur:' . $dur . 'ms;--scats-card-w:' . $cardW . 'px;--scats-card-h:' . $cardH . 'px;'
            : '';

        $items = $this->safeJson($c['categories'] ?? []);
        if (!$items && function_exists('sonika_categories')) {
            foreach (sonika_categories() as $cat) {
                $items[] = ['slug' => (string)($cat['slug'] ?? ''), 'label' => (string)($cat['name'] ?? ''), 'subtitle' => (string)($cat['tags'] ?? ''), 'image' => ''];
            }
        }

        $uid = 'sc' . substr(md5(uniqid('', true)), 0, 6);
        $themeCss = <<<'CSS'
.scats--spectrum{padding-bottom:38px;background:radial-gradient(900px 380px at 82% -10%,var(--scats-glow),transparent 64%),var(--scats-bg);color:var(--scats-text);border-top:1px solid rgba(255,255,255,.06);border-bottom:1px solid rgba(255,255,255,.06);transition:background-color var(--scats-dur) ease,color var(--scats-dur) ease}
.scats--spectrum .scats__head{margin-bottom:22px}
.scats--spectrum .scats__head h2{color:var(--scats-text);text-align:left}
.scats--spectrum .scats__head a{color:var(--scats-accent)}
.scats--spectrum .scats__track{grid-auto-columns:var(--scats-card-w);scroll-snap-type:x mandatory;padding:2px 2px 8px}
.scats--spectrum .sc__card{width:var(--scats-card-w);height:var(--scats-card-h);aspect-ratio:auto;border-radius:12px;box-sizing:border-box;background:var(--scats-card);border:1px solid var(--scats-border);box-shadow:0 10px 28px rgba(0,0,0,.22);transition:transform var(--scats-dur) ease,box-shadow var(--scats-dur) ease,border-color var(--scats-dur) ease}
.scats--spectrum .sc__img{filter:none;transition:transform .45s cubic-bezier(.22,1,.36,1),filter var(--scats-dur) ease}
.scats--spectrum .sc__scrim{background:linear-gradient(180deg,rgba(0,0,0,.02) 20%,rgba(0,0,0,.12) 42%,rgba(0,0,0,.68) 72%,rgba(0,0,0,.94) 100%)}
.scats--spectrum .sc__body{color:var(--scats-text);padding:14px 15px;gap:0}
.scats--spectrum .sc__kick{display:none}
.scats--spectrum .sc__ttl{color:var(--scats-text);font-size:18px;font-weight:700;line-height:1.2;letter-spacing:-.02em;text-shadow:0 1px 8px rgba(0,0,0,.55);overflow-wrap:anywhere}
.scats--spectrum .sc__arrow{display:none}
.scats--spectrum .sc__badge--new{background:var(--scats-accent)}
.scats--spectrum .sc__badge--off{background:rgba(0,0,0,.55);border-color:var(--scats-border)}
.scats--spectrum .sc__card:hover,.scats--spectrum .sc__card:focus-visible{border-color:var(--scats-accent);box-shadow:0 0 0 1px var(--scats-accent),0 0 14px var(--scats-glow);transform:translateY(-3px)}
.scats--spectrum .sc__card:hover .sc__img,.scats--spectrum .sc__card:focus-visible .sc__img{transform:scale(1.035);filter:none}
.scats--spectrum .sc__card:focus-visible{outline:2px solid var(--scats-accent);outline-offset:3px;box-shadow:0 0 0 1px var(--scats-accent),0 0 14px var(--scats-glow)}
.scats--spectrum .scats__nav{position:absolute;top:50%;transform:translateY(-50%);z-index:5;width:42px;height:42px;border-radius:50%;border:1px solid var(--scats-border);background:rgba(8,10,16,.78);color:var(--scats-text);font-size:22px;line-height:1;display:flex;align-items:center;justify-content:center;box-shadow:0 8px 24px rgba(0,0,0,.35);transition:background-color .16s ease,border-color .16s ease,color .16s ease}
.scats--spectrum .scats__prev{left:8px}
.scats--spectrum .scats__next{right:8px}
.scats--spectrum .scats__nav:hover{background:var(--scats-accent);color:#fff;border-color:var(--scats-accent)}
.scats--spectrum .scats__nav:focus-visible{outline:2px solid var(--scats-accent);outline-offset:3px}
.scats--theme-monochrome .sc__img{filter:grayscale(1)}
.scats--theme-monochrome .sc__card:hover .sc__img,.scats--theme-monochrome .sc__card:focus-visible .sc__img{filter:grayscale(0)}
.scats--no-lift .sc__card:hover,.scats--no-lift .sc__card:focus-visible{transform:none}
.scats--no-glow .sc__card:hover,.scats--no-glow .sc__card:focus-visible{box-shadow:0 10px 28px rgba(0,0,0,.22)}
@media(max-width:1024px){.scats--spectrum .scats__track{grid-auto-columns:min(var(--scats-card-w),205px)}.scats--spectrum .sc__card{width:min(var(--scats-card-w),205px);height:min(var(--scats-card-h),180px)}}
@media(max-width:640px){.scats--spectrum .scats__head{margin-bottom:16px}.scats--spectrum .scats__head h2{font-size:21px}.scats--spectrum .scats__track{grid-auto-columns:min(var(--scats-card-w),170px);gap:12px}.scats--spectrum .sc__card{width:min(var(--scats-card-w),170px);height:min(var(--scats-card-h),165px);border-radius:10px}.scats--spectrum .sc__body{padding:12px}.scats--spectrum .sc__ttl{font-size:16px}.scats--spectrum .scats__nav{width:36px;height:36px;font-size:19px}.scats--spectrum .scats__prev{left:4px}.scats--spectrum .scats__next{right:4px}}
@media(prefers-reduced-motion:reduce){.scats--spectrum,.scats--spectrum .sc__card,.scats--spectrum .sc__img,.scats--spectrum .scats__nav{transition:none!important}.scats--spectrum .sc__card:hover,.scats--spectrum .sc__card:focus-visible{transform:none}.scats--spectrum .sc__card:hover .sc__img,.scats--spectrum .sc__card:focus-visible .sc__img{transform:none}}
CSS;
        $h = '<style>'
            . '.scats{position:relative;padding-top:' . $topSpace . 'px}'
            . '.scats__inner{' . ($maxw > 0 ? 'max-width:' . $maxw . 'px;' : 'max-width:none;') . 'margin:0 auto;padding:0 ' . $sidepad . 'px;box-sizing:border-box}'
            . '.scats__head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px}'
            . '.scats__head h2{font-family:var(--font-display,Sora),sans-serif;letter-spacing:-.02em;margin:0;flex:1;text-align:' . $titleAlign . ';color:' . $titleColor . ';font-size:' . $titleSize . 'px}'
            . '.scats__head a{color:' . $accent . ';font-weight:600;font-size:14px;text-decoration:none}'
            . '.scats__wrap{position:relative}'
            . '.scats__track{display:grid;grid-auto-flow:column;grid-template-rows:repeat(' . $rows . ',minmax(0,1fr));grid-auto-columns:calc((100% - (' . $per . ' - 1)*' . $gap . 'px)/' . $per . ');gap:' . $gap . 'px;overflow-x:auto;scroll-snap-type:x proximity;scroll-behavior:smooth;padding:2px 2px 8px;scrollbar-width:none;-ms-overflow-style:none}'
            . '.scats__track::-webkit-scrollbar{display:none}'
            . '.sc__card{position:relative;display:block;overflow:hidden;border-radius:' . $radius . 'px;aspect-ratio:' . $aspect . ';scroll-snap-align:start;background:#111;color:#fff;isolation:isolate;text-decoration:none;box-shadow:0 8px 22px rgba(17,24,39,.10);transition:transform .25s ease,box-shadow .25s ease}'
            . '.sc__card:hover,.sc__card:focus-visible{transform:translateY(-4px);box-shadow:0 16px 40px rgba(17,24,39,.18);outline:none}'
            . '.sc__card:focus-visible{box-shadow:0 0 0 3px ' . $accent . ',0 16px 40px rgba(17,24,39,.18)}'
            . '.sc__img{position:absolute;inset:0;z-index:0;width:100%;height:100%;object-fit:cover;transition:transform .6s cubic-bezier(.22,1,.36,1)}'
            . '.sc__card:hover .sc__img,.sc__card:focus-visible .sc__img{transform:scale(1.07)}'
            . '.sc__scrim{position:absolute;inset:0;z-index:1;background:linear-gradient(180deg,rgba(10,10,10,0) 32%,rgba(10,10,10,.55) 66%,rgba(10,10,10,.93) 100%)}'
            . '.sc__body{position:absolute;left:0;right:0;bottom:0;z-index:2;padding:14px 15px;display:flex;flex-direction:column;gap:2px}'
            . '.sc__kick{font-size:10px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;opacity:.82;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}'
            . '.sc__ttl{font-family:var(--font-display,Sora),sans-serif;font-size:19px;font-weight:800;line-height:1.1}'
            . '.sc__arrow{position:absolute;top:12px;right:12px;z-index:2;width:30px;height:30px;border-radius:50%;background:' . $accent . ';color:#fff;display:flex;align-items:center;justify-content:center;font-size:15px;opacity:0;transform:translateY(-4px);transition:opacity .2s,transform .2s}'
            . '.sc__card:hover .sc__arrow,.sc__card:focus-visible .sc__arrow{opacity:1;transform:none}'
            . '.sc__badge{position:absolute;top:10px;left:10px;z-index:2;font-size:9.5px;font-weight:800;letter-spacing:.05em;padding:3px 7px;border-radius:6px;color:#fff}'
            . '.sc__badge--new{background:' . $accent . '}'
            . '.sc__badge--off{background:#111;border:1px solid rgba(255,255,255,.3)}'
            . '.scats__nav{position:absolute;top:50%;transform:translateY(-50%);z-index:3;width:40px;height:40px;border-radius:50%;border:1px solid rgba(0,0,0,.1);background:#fff;color:#111;font-size:20px;line-height:1;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 6px 18px rgba(0,0,0,.12)}'
            . '.scats__prev{left:-8px}.scats__next{right:-8px}'
            . '.scats__nav:hover{background:' . $accent . ';color:#fff;border-color:' . $accent . '}'
            . '.scats__nav:focus-visible{outline:2px solid ' . $accent . ';outline-offset:2px}'
            . '@media(max-width:900px){.scats__track{grid-auto-columns:calc((100% - (' . min(2, $per) . ' - 1)*' . $gap . 'px)/' . min(2, $per) . ')}}'
            . '@media(max-width:560px){.scats__track{grid-auto-columns:78%}.scats__nav{display:none}}'
            . '@media(prefers-reduced-motion:reduce){.scats__track{scroll-behavior:auto}.sc__card,.sc__img{transition:none}}'
            . ($isSpectrum ? $themeCss : '')
            . '</style>';

        $h .= '<section class="' . $rootClass . '" id="' . $uid . '" style="' . $rootStyle . '" data-theme="' . $themeId . '" data-speed="' . $speed . '" data-autoplay="' . ($auto ? '1' : '0') . '" data-pause="' . ($pause ? '1' : '0') . '" data-dir="' . $dir . '">';
        $h .= '<div class="scats__inner">';
        $h .= '<div class="scats__head"><h2>' . $this->esc((string)($c['title'] ?? '')) . '</h2>'
            . ((string)($c['view_all_url'] ?? '') !== '' ? '<a href="' . $this->esc((string)$c['view_all_url']) . '">' . $this->esc((string)($c['view_all_label'] ?? '')) . '</a>' : '')
            . '</div>';

        $h .= '<div class="scats__wrap">';
        if ($arrows) {
            $h .= '<button type="button" class="scats__nav scats__prev" aria-label="Anterior">&#8249;</button>';
            $h .= '<button type="button" class="scats__nav scats__next" aria-label="Siguiente">&#8250;</button>';
        }
        $h .= '<div class="scats__track" role="list">';

        foreach ($items as $it) {
                $slug = trim((string)($it['slug'] ?? ''));
                $label = trim((string)($it['label'] ?? ''));
                $sub = trim((string)($it['subtitle'] ?? ''));
                $img = trim((string)($it['image'] ?? ''));
                if ($img === '' && $slug !== '' && function_exists('sonika_category_images')) {
                    $imgs = sonika_category_images($slug, 700);
                    $img = $imgs[0] ?? '';
                }
                if ($img === '' && function_exists('sonika_category')) {
                    $cat = sonika_category($slug);
                    if ($cat) { $label = $label ?: $cat['name']; $sub = $sub ?: $cat['tags']; }
                }
                if ($label === '' && $slug === '') continue;
                $url = $slug !== '' ? (function_exists('sonika_category_url') ? sonika_category_url($slug) : '/' . implode('/', array_map('rawurlencode', explode('/', $slug)))) : '#';
                $b = ($showBadges && $slug !== '' && function_exists('sonika_category_badges')) ? sonika_category_badges($slug) : ['new' => false, 'offer' => false];
                $bimg = $img !== '' ? '<img class="sc__img" src="' . $this->esc($img) . '" alt="' . $this->esc($label) . '" loading="lazy" decoding="async"/>' : '';
                $h .= '<a class="sc__card" role="listitem" href="' . $this->esc($url) . '">'
                    . $bimg . '<span class="sc__scrim" aria-hidden="true"></span>'
                    . ($b['new'] ? '<span class="sc__badge sc__badge--new">NUEVO</span>' : '')
                    . ($b['offer'] ? '<span class="sc__badge sc__badge--off">OFERTA</span>' : '')
                    . '<span class="sc__body">' . ($sub !== '' ? '<span class="sc__kick">' . $this->esc($sub) . '</span>' : '')
                    . '<span class="sc__ttl">' . $this->esc($label) . '</span></span>'
                    . '<span class="sc__arrow" aria-hidden="true">&#8594;</span></a>';
        }

        $h .= '</div></div></div>';
        $h .= '<script>(function(){var el=document.getElementById("' . $uid . '");if(!el||el.getAttribute("data-ready"))return;el.setAttribute("data-ready","1");var t=el.querySelector(".scats__track");if(!t)return;var speed=parseInt(el.getAttribute("data-speed"),10)||6000;var auto=el.getAttribute("data-autoplay")==="1";var pause=el.getAttribute("data-pause")==="1";var dir=parseInt(el.getAttribute("data-dir"),10)||1;var reduce=window.matchMedia&&matchMedia("(prefers-reduced-motion: reduce)").matches;var n=t.children.length;var C=0;if(n>0){t.insertAdjacentHTML("beforeend",t.innerHTML);var dup=t.children[n];if(dup){C=dup.offsetLeft-t.children[0].offsetLeft;if(C>0){t.style.scrollBehavior="auto";t.scrollLeft=C;t.style.scrollBehavior="";}}}var last=t.scrollLeft;var paused=false,timer=null;function cardStep(){var c=t.querySelector(".sc__card");if(!c)return Math.max(1,t.clientWidth);var gap=parseInt(getComputedStyle(t).gap,10)||16;return c.getBoundingClientRect().width+gap;}function normalize(){var s=t.scrollLeft;if(s>last+0.5&&s>=C){t.style.scrollBehavior="auto";t.scrollLeft=s-C;t.style.scrollBehavior="";}else if(s<last-0.5&&s<=0.5){t.style.scrollBehavior="auto";t.scrollLeft=s+C;t.style.scrollBehavior="";}last=t.scrollLeft;}function go(d){var max=t.scrollWidth-t.clientWidth;if(max<=4)return;t.scrollBy({left:d*cardStep(),behavior:reduce?"auto":"smooth"});if(reduce){normalize();}else{setTimeout(normalize,420);}}function stop(){if(timer){clearInterval(timer);timer=null;}}function start(){stop();if(!auto||reduce)return;timer=setInterval(function(){if(!paused)go(dir);},speed);}if(pause){t.addEventListener("mouseenter",function(){paused=true;});t.addEventListener("mouseleave",function(){paused=false;});t.addEventListener("focusin",function(){paused=true;});t.addEventListener("focusout",function(){paused=false;});}var p=el.querySelector(".scats__prev"),n=el.querySelector(".scats__next");if(p)p.addEventListener("click",function(){go(-1);});if(n)n.addEventListener("click",function(){go(1);});start();})();</script>';
        $h .= '</section>';
        return $h;
    }
}
