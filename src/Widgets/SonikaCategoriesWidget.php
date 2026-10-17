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
            ['key' => 'per_view', 'label' => 'Fichas visibles por fila', 'type' => 'number', 'default' => '4'],
            ['key' => 'speed', 'label' => 'Velocidad (segundos entre avances)', 'type' => 'number', 'default' => '6'],
            ['key' => 'autoplay', 'label' => 'Reproducción automática', 'type' => 'toggle', 'default' => true],
            ['key' => 'pause_hover', 'label' => 'Pausar al pasar el cursor', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_arrows', 'label' => 'Mostrar flechas', 'type' => 'toggle', 'default' => true],
            ['key' => '_h_card', 'label' => 'Apariencia de las fichas', 'type' => 'heading'],
            ['key' => 'shape', 'label' => 'Forma', 'type' => 'select', 'options' => ['rounded' => 'Redondeada', 'square' => 'Cuadrada (sin redondeo)'], 'default' => 'rounded'],
            ['key' => 'radius', 'label' => 'Radio (px, si es redondeada)', 'type' => 'number', 'default' => '18'],
            ['key' => 'aspect', 'label' => 'Proporción', 'type' => 'select', 'options' => ['3/4' => 'Vertical (3:4)', '1/1' => 'Cuadrada (1:1)', '4/3' => 'Horizontal (4:3)', '16/9' => 'Panorámica (16:9)'], 'default' => '3/4'],
            ['key' => 'accent', 'label' => 'Acento', 'type' => 'color', 'default' => '#F20D16'],
            ['key' => 'show_badges', 'label' => 'Mostrar NUEVO/OFERTA', 'type' => 'toggle', 'default' => true],
            ['key' => 'gap', 'label' => 'Separación (px)', 'type' => 'number', 'default' => '16'],
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
        $accent = $this->col((string)($c['accent'] ?? ''), '#F20D16');
        $gap = max(0, min(40, (int)($c['gap'] ?? 16)));
        $showBadges = !empty($c['show_badges']);

        $items = $this->safeJson($c['categories'] ?? []);
        if (!$items && function_exists('sonika_categories')) {
            foreach (sonika_categories() as $slug => $cat) {
                $items[] = ['slug' => $slug, 'label' => $cat['name'], 'subtitle' => $cat['tags'], 'image' => ''];
            }
        }

        $uid = 'sc' . substr(md5(uniqid('', true)), 0, 6);
        $h = '<style>'
            . '.scats{position:relative}'
            . '.scats__head{display:flex;align-items:flex-end;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-bottom:20px}'
            . '.scats__head h2{font-family:var(--font-display,Sora),sans-serif;font-size:30px;letter-spacing:-.02em;margin:0}'
            . '.scats__head a{color:' . $accent . ';font-weight:600;font-size:14px;text-decoration:none}'
            . '.scats__wrap{position:relative}'
            . '.scats__track{display:grid;grid-auto-flow:column;grid-template-rows:repeat(' . $rows . ',minmax(0,1fr));grid-auto-columns:calc((100% - (' . $per . ' - 1)*' . $gap . 'px)/' . $per . ');gap:' . $gap . 'px;overflow-x:auto;scroll-snap-type:x mandatory;scroll-behavior:smooth;padding:2px 2px 8px;scrollbar-width:none;-ms-overflow-style:none}'
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
            . '</style>';

        $h .= '<section class="scats" id="' . $uid . '" data-speed="' . $speed . '" data-autoplay="' . ($auto ? '1' : '0') . '" data-pause="' . ($pause ? '1' : '0') . '">';
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
            if ($slug === '' && function_exists('sonika_category')) {
                $slug = (string)($it['slug'] ?? '');
            }
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
            $url = $slug !== '' ? '/' . rawurlencode($slug) : '#';
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

        $h .= '</div></div>';
        $h .= '<script>(function(){var el=document.getElementById("' . $uid . '");if(!el||el.getAttribute("data-ready"))return;el.setAttribute("data-ready","1");var t=el.querySelector(".scats__track");if(!t)return;var speed=parseInt(el.getAttribute("data-speed"),10)||6000;var auto=el.getAttribute("data-autoplay")==="1";var pause=el.getAttribute("data-pause")==="1";var reduce=window.matchMedia&&matchMedia("(prefers-reduced-motion: reduce)").matches;function step(){var c=t.querySelector(".sc__card");var w=c?c.getBoundingClientRect().width+16:t.clientWidth;return Math.max(1,Math.round(t.clientWidth/w))*w;}var paused=false,timer=null;function go(dir){var max=t.scrollWidth-t.clientWidth-2;if(dir>0&&t.scrollLeft>=max){t.scrollTo({left:0,behavior:reduce?"auto":"smooth"});return;}t.scrollBy({left:dir*step(),behavior:reduce?"auto":"smooth"});}function stop(){if(timer){clearInterval(timer);timer=null;}}function start(){stop();if(!auto||reduce)return;timer=setInterval(function(){if(!paused)go(1);},speed);}if(pause){t.addEventListener("mouseenter",function(){paused=true;});t.addEventListener("mouseleave",function(){paused=false;});t.addEventListener("focusin",function(){paused=true;});t.addEventListener("focusout",function(){paused=false;});}var p=el.querySelector(".scats__prev"),n=el.querySelector(".scats__next");if(p)p.addEventListener("click",function(){go(-1);});if(n)n.addEventListener("click",function(){go(1);});start();})();</script>';
        $h .= '</section>';
        return $h;
    }
}
