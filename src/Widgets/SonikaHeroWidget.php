<?php
namespace App\Widgets;

use App\Services\HeroService;

/**
 * SonikaHeroWidget — SONIKA HERO BUILDER (renderer).
 * Presenta los slides PUBLICADOS (2 zonas) con bloques de contenido reales.
 * Render SSR + slider mínimo (sin frameworks). Degradación elegante por data-cap.
 */
class SonikaHeroWidget extends Widget
{
    private static bool $assets = false;
    private static bool $guitarIncluded = false;

    /** Coordenadas SVG (viewBox 0 0 1536 1024) por cuerda — índice 0 = cuerda 6 (E2) … 5 = cuerda 1 (E4). */
    private const GUITAR_STRINGS = [
        '798,20 783,180 760,350 741,500 720,650 697,820',
        '822,20 807,180 785,350 767,500 748,650 728,820',
        '846,20 832,180 810,350 792,500 775,650 759,820',
        '871,20 857,180 835,350 818,500 801,650 790,820',
        '896,20 882,180 860,350 843,500 827,650 821,820',
        '920,20 906,180 885,350 868,500 854,650 852,820',
    ];

    public static function meta(): array
    {
        return ['id' => 'sonika-hero', 'name' => 'SONIKA Hero Builder', 'icon' => 'zap', 'category' => 'header', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'enabled', 'label' => 'Mostrar el hero', 'type' => 'toggle', 'default' => false],
            ['key' => 'autoplay', 'label' => 'Autoplay (ms · 0 = off)', 'type' => 'number', 'default' => 7000],
            ['key' => 'height', 'label' => 'Altura en escritorio (px)', 'type' => 'number', 'default' => 460],
        ];
    }

    public static function defaultConfig(): array
    {
        return ['enabled' => false, 'autoplay' => 7000, 'height' => 460];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        if (empty($c['enabled'])) return '';
        $slides = (new HeroService())->published();
        if (!$slides) return '';

        $autoplay = max(0, min(20000, (int)($c['autoplay'] ?? 7000)));
        $height = max(320, min(720, (int)($c['height'] ?? 460)));

        $h = $this->assets();
        $h .= '<section class="sh" data-autoplay="' . $autoplay . '" style="--sh-h:' . $height . 'px" aria-roledescription="carrusel">';
        $h .= '<div class="sh-track">';
        foreach ($slides as $i => $s) {
            $bg = (string)($s['design']['bg'] ?? '#111111');
            if (!preg_match('/^#[0-9a-f]{3,8}$/i', $bg)) $bg = '#111111';
            $cols = $this->cols((string)($s['design']['cols'] ?? '50/50'));
            $va = (string)($s['design']['valign'] ?? 'center');
            $align = in_array($va, ['start', 'center', 'end'], true) ? $va : 'center';
            $padt = max(0, min(160, (int)($s['design']['pad_top'] ?? 0)));
            $padx = max(0, min(80, (int)($s['design']['pad_x'] ?? 22)));
            $h .= '<article class="sh-slide' . ($i === 0 ? ' is-on' : '') . '" data-i="' . $i . '" aria-hidden="' . ($i === 0 ? 'false' : 'true') . '" style="--sh-bg:' . $bg . ';--sh-cols:' . $cols . ';--sh-valign:' . $align . ';--sh-padt:' . $padt . 'px;--sh-padx:' . $padx . 'px">';
            $h .= '<div class="sh-grid">';
            $h .= $this->zone('left', (string)$s['left_type'], (array)$s['left_data']);
            $h .= $this->zone('right', (string)$s['right_type'], (array)$s['right_data']);
            $h .= '</div></article>';
        }
        $h .= '</div>';
        if (count($slides) > 1) {
            $h .= '<button type="button" class="sh-nav sh-prev" aria-label="Slide anterior">&#8249;</button>';
            $h .= '<button type="button" class="sh-nav sh-next" aria-label="Slide siguiente">&#8250;</button>';
            $h .= '<div class="sh-eqs" role="tablist" aria-label="Slides">';
            foreach ($slides as $i => $s) {
                $h .= '<button type="button" class="sh-eq' . ($i === 0 ? ' on' : '') . '" data-i="' . $i . '" role="tab" aria-label="Slide ' . ($i + 1) . '"><i></i><i></i><i></i><i></i></button>';
            }
            $h .= '</div>';
        }
        $h .= '</section>';
        return $h;
    }

    private function zone(string $side, string $type, array $d): string
    {
        $inner = $this->block($type, $d);
        if ($inner === '') return '';
        $bleed = ($type === 'video' && (string)($d['video_size'] ?? 'full') !== 'center') ? ' sh-zone--bleed' : '';
        return '<div class="sh-zone sh-zone--' . $side . $bleed . '">' . $inner . '</div>';
    }

    private function block(string $type, array $d): string
    {
        switch ($type) {
            case 'text':
                $h = '<div class="sh-copy">';
                if (!empty($d['eyebrow'])) $h .= '<span class="sh-eyebrow">' . $this->esc($d['eyebrow']) . '</span>';
                $h .= $this->titleHtml($d);
                $h .= $this->subHtml($d);
                if (!empty($d['text'])) $h .= '<p class="sh-text">' . $this->esc($d['text']) . '</p>';
                return $h . $this->ctas($d) . $this->attrs($d) . '</div>';
            case 'image':
                $h = '<div class="sh-media">';
                if (!empty($d['image'])) $h .= '<img src="' . $this->esc($d['image']) . '" alt="' . $this->esc($d['alt'] ?? ($d['title'] ?? '')) . '" loading="lazy" decoding="async"/>';
                $h .= '<div class="sh-media__cap">';
                if (!empty($d['title'])) $h .= '<h3>' . $this->esc($d['title']) . '</h3>';
                if (!empty($d['subtitle'])) $h .= '<p>' . $this->esc($d['subtitle']) . '</p>';
                $h .= $this->ctas($d) . '</div></div>';
                return $h;
            case 'video':
                $src = (string)($d['source'] ?? 'file');
                $auto = !empty($d['autoplay']);
                $mute = array_key_exists('mute', $d) ? !empty($d['mute']) : true;
                $loop = !empty($d['loop']);
                $ctrl = !empty($d['controls']);
                $vol = max(0, min(100, (int)($d['volume'] ?? 100)));
                $clean = array_key_exists('yt_clean', $d) ? ((string)$d['yt_clean'] !== '0') : true;
                $poster = trim((string)($d['poster'] ?? ''));
                $alt = trim((string)($d['alt'] ?? '')) !== '' ? trim((string)$d['alt']) : (string)($d['title'] ?? '');
                $inner = '';
                $veil = '';
                if ($src === 'youtube') {
                    $yt = $this->youtubeId((string)($d['yt_url'] ?? ($d['video'] ?? '')));
                    if ($yt !== '') {
                        $params = ['autoplay' => $auto ? 1 : 0, 'mute' => $mute ? 1 : 0, 'controls' => $ctrl ? 1 : 0, 'loop' => $loop ? 1 : 0, 'rel' => 0, 'playsinline' => 1, 'modestbranding' => 1, 'iv_load_policy' => 3, 'disablekb' => 1, 'fs' => 0];
                        if ($loop) $params['playlist'] = $yt;
                        $qs = http_build_query($params);
                        $inner = '<iframe class="sh-video__fr" src="https://www.youtube-nocookie.com/embed/' . $this->esc($yt) . '?' . $this->esc($qs) . '" title="' . $this->esc($alt !== '' ? $alt : 'Video') . '" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture" allowfullscreen></iframe>';
                        if ($clean) $veil = '<span class="sh-video__veil" aria-hidden="true"></span>';
                    }
                } elseif (!empty($d['video'])) {
                    $attr = ($auto ? ' autoplay' : '') . ($mute ? ' muted' : '') . ($loop ? ' loop' : '') . ($ctrl ? ' controls' : '') . ' playsinline preload="metadata" data-volume="' . $vol . '"';
                    $pa = $poster !== '' ? ' poster="' . $this->esc($poster) . '"' : '';
                    $inner = '<video class="sh-video__v"' . $attr . $pa . '><source src="' . $this->esc($d['video']) . '" type="video/mp4"></video>';
                }
                if ($inner === '' && $poster !== '') $inner = '<img class="sh-video__poster" src="' . $this->esc($poster) . '" alt="' . $this->esc($alt) . '" loading="lazy" decoding="async"/>';
                $size = (string)($d['video_size'] ?? 'full');
                $cls = $inner !== '' ? ($size === 'center' ? ' sh-video--center' : ' sh-video--fill') : '';
                $h = '<div class="sh-video' . $cls . '">';
                if ($inner !== '') $h .= '<div class="sh-video__box">' . $inner . $veil . '</div>';
                if (!empty($d['title'])) $h .= '<h3 class="sh-media__t">' . $this->esc($d['title']) . '</h3>';
                return $h . '</div>';
            case 'product':
                $p = (!empty($d['handle']) && function_exists('sonika_find')) ? sonika_find((string)$d['handle']) : null;
                if (!$p) return '<div class="sh-copy"><p class="sh-text">Producto no disponible.</p></div>';
                $img = function_exists('sonika_img') ? sonika_img($p, 520) : '';
                $price = function_exists('sonika_price') ? (float)sonika_price($p) : 0;
                $h = '<a class="sh-product" href="/producto/' . $this->esc((string)($p['handle'] ?? '')) . '">';
                if ($img !== '') $h .= '<span class="sh-product__img"><img src="' . $this->esc($img) . '" alt="' . $this->esc((string)($p['title'] ?? '')) . '" loading="lazy" decoding="async"/></span>';
                if (!empty($p['vendor'])) $h .= '<span class="sh-product__b">' . $this->esc((string)$p['vendor']) . '</span>';
                $h .= '<span class="sh-product__n">' . $this->esc((string)($p['title'] ?? '')) . '</span>';
                if ($price > 0 && function_exists('sonika_cop')) $h .= '<span class="sh-product__p">' . $this->esc(sonika_cop($price)) . '</span>';
                return $h . '</a>';
            case 'combo':
                $h = '<div class="sh-promo">';
                if (!empty($d['image'])) $h .= '<img src="' . $this->esc($d['image']) . '" alt="' . $this->esc($d['alt'] ?? ($d['title'] ?? '')) . '" loading="lazy"/>';
                if (!empty($d['title'])) $h .= '<h3>' . $this->esc($d['title']) . '</h3>';
                if (!empty($d['text'])) $h .= '<p>' . $this->esc($d['text']) . '</p>';
                return $h . $this->ctas($d) . '</div>';
            case 'guitar':
                return $this->guitarBlock($d);
            default:
                return '<div class="sh-inst"><div class="sh-inst__ph"><span aria-hidden="true">&#127928;</span><b>Instrumento interactivo</b><small>Disponible próximamente</small></div></div>';
        }
    }

    private function guitarBlock(array $d): string
    {
        $p = $this->findGuitar((string)($d['product'] ?? ''));
        $img = trim((string)($d['image'] ?? ''));
        if ($img === '') $img = $p ? (string)($p['images'][0]['src'] ?? '') : '';
        if ($img === '' && $p && function_exists('sonika_img')) $img = sonika_img($p, 900);
        if ($img === '') {
            return '<div class="sh-guitar sh-guitar--empty"><div class="sh-inst__ph"><span aria-hidden="true">&#127928;</span><b>Falta una imagen de guitarra</b><small>Selecciona un producto de guitarra eléctrica en la configuración.</small></div></div>' . $this->guitarAssets();
        }
        $alt = trim((string)($d['alt'] ?? '')) !== '' ? trim((string)$d['alt']) : (string)($p['title'] ?? 'Guitarra eléctrica');
        $points = (array)($d['string_points'] ?? []);
        if (count($points) !== 6) $points = self::GUITAR_STRINGS;
        $points = array_values($points);
        $unit = (string)($d['cal_unit'] ?? 'cm');
        $refCm = (float)($d['cal_ref'] ?? 40);
        if ($refCm <= 0) $refCm = 40;
        $u = 1.0;
        if ($unit === 'cm') $u = 1536 / $refCm;
        elseif ($unit === 'mm') $u = 153.6 / $refCm;
        $dx = (float)($d['cal_x'] ?? 0) * $u;
        $dy = (float)($d['cal_y'] ?? 0) * $u;
        $tilt = (float)($d['cal_tilt'] ?? 0);
        $spread = (float)($d['cal_spread'] ?? 100);
        if ($spread < 20 || $spread > 300) $spread = 100;
        $spread = $spread / 100;
        $allX = $allY = [];
        foreach ($points as $row) {
            foreach (preg_split('/\s+/', trim((string)$row)) as $pair) {
                if ($pair === '') continue;
                $pp = array_pad(explode(',', $pair), 2, 0);
                $allX[] = (float)$pp[0];
                $allY[] = (float)$pp[1];
            }
        }
        $cx = $allX ? array_sum($allX) / count($allX) : 808.0;
        $cy = $allY ? array_sum($allY) / count($allY) : 420.0;
        $tr = 'translate(' . round($dx, 2) . ' ' . round($dy, 2) . ')';
        if (abs($tilt) > 0.0001) $tr .= ' rotate(' . round($tilt, 3) . ' ' . round($cx, 1) . ' ' . round($cy, 1) . ')';
        if (abs($spread - 1) > 0.0001) $tr .= ' translate(' . round($cx, 1) . ' ' . round($cy, 1) . ') scale(' . round($spread, 4) . ' 1) translate(-' . round($cx, 1) . ' -' . round($cy, 1) . ')';
        $guides = !empty($d['cal_guides']) && (string)$d['cal_guides'] !== '0';
        $h = '<div class="sh-guitar" data-wwi-instrument="guitar" data-profile="clean" role="group" aria-label="Guitarra eléctrica interactiva" tabindex="0">';
        $h .= '<div class="sh-guitar__stage">';
        $h .= '<img class="sh-guitar__img" src="' . $this->esc($img) . '" alt="' . $this->esc($alt) . '" loading="eager" decoding="async"/>';
        $h .= '<svg class="sh-guitar__strings" viewBox="0 0 1536 1024" preserveAspectRatio="none" aria-label="Cuerdas">';
        $h .= '<g class="sh-str-g" transform="' . $this->esc($tr) . '">';
        foreach ($points as $i => $pts) {
            $pts = trim((string)$pts);
            if ($pts === '') continue;
            $per = (float)($d['cal_per' . $i] ?? 0) * $u;
            $h .= '<g class="sh-str-one"' . (abs($per) > 0.01 ? ' transform="translate(' . round($per, 2) . ' 0)"' : '') . '>';
            $h .= '<polyline class="sh-str-hit" data-string="' . $i . '" points="' . $this->esc($pts) . '" fill="none" stroke="transparent" stroke-width="34" stroke-linecap="round" stroke-linejoin="round" pointer-events="stroke" tabindex="0" role="button" aria-label="Tocar cuerda ' . ($i + 1) . '"></polyline>';
            $h .= '<polyline class="sh-str" points="' . $this->esc($pts) . '" fill="none" stroke="rgba(20,20,20,.3)" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" pointer-events="none"></polyline>';
            if ($guides) {
                foreach (preg_split('/\s+/', $pts) as $pair) {
                    if ($pair === '') continue;
                    $pp = array_pad(explode(',', $pair), 2, 0);
                    $h .= '<circle class="sh-str-dot" cx="' . round((float)$pp[0], 1) . '" cy="' . round((float)$pp[1], 1) . '" r="5" fill="rgba(242,13,22,.5)" stroke="#fff" stroke-width="1.5"/>';
                }
            }
            $h .= '</g>';
        }
        if ($guides) {
            $h .= '<line x1="0" y1="' . round($cy, 1) . '" x2="1536" y2="' . round($cy, 1) . '" stroke="rgba(242,13,22,.2)" stroke-width="1"/>';
            $h .= '<line x1="' . round($cx, 1) . '" y1="0" x2="' . round($cx, 1) . '" y2="1024" stroke="rgba(242,13,22,.2)" stroke-width="1"/>';
        }
        $h .= '</g></svg></div>';
        $h .= '<div class="sh-guitar__profiles" role="group" aria-label="Perfil de sonido">';
        foreach (['clean' => 'Clean', 'overdrive' => 'Overdrive', 'distortion' => 'Distortion', 'fuzz' => 'Fuzz'] as $k => $lbl) {
            $h .= '<button type="button" class="sh-prof' . ($k === 'clean' ? ' on' : '') . '" data-profile="' . $k . '">' . $lbl . '</button>';
        }
        $h .= '</div>';
        $h .= '<p class="sh-guitar__hint">Toca las cuerdas para escuchar las notas. Cambia de efecto para explorar otros sonidos.</p>';
        if ($p) $h .= $this->productCard($p);
        $h .= '</div>';
        return $h . $this->guitarAssets();
    }

    private function findGuitar(string $handle = ''): ?array
    {
        if (!function_exists('sonika_products')) return null;
        if ($handle !== '' && function_exists('sonika_find')) { $p = sonika_find($handle); if ($p) return $p; }
        $best = null;
        foreach (sonika_products() as $p) {
            $t = strtoupper((string)($p['title'] ?? ''));
            $isGuitar = str_contains($t, 'GUITARRA') && !str_contains($t, 'AMPLIFICADOR') && !str_contains($t, 'BAJO') && !str_contains($t, 'CUERDA') && !str_contains($t, 'FUNDA') && !str_contains($t, 'CORREA') && !str_contains($t, 'PEDAL');
            if (!$isGuitar) continue;
            if (str_contains($t, 'ELECTRIC') || str_contains($t, 'ELECTR')) return $p;
            if (!$best) $best = $p;
        }
        return $best;
    }

    private function productCard(array $p): string
    {
        $img = function_exists('sonika_img') ? sonika_img($p, 300) : '';
        $price = function_exists('sonika_price') ? (float)sonika_price($p) : 0;
        $h = '<a class="sh-pcard" href="/producto/' . $this->esc((string)($p['handle'] ?? '')) . '">';
        if ($img !== '') $h .= '<span class="sh-pcard__img"><img src="' . $this->esc($img) . '" alt="' . $this->esc((string)($p['title'] ?? '')) . '" loading="lazy" decoding="async"/></span>';
        $h .= '<span class="sh-pcard__info"><span class="sh-pcard__b">' . $this->esc((string)($p['vendor'] ?? '')) . '</span>';
        $h .= '<span class="sh-pcard__n">' . $this->esc((string)($p['title'] ?? '')) . '</span>';
        if ($price > 0 && function_exists('sonika_cop')) $h .= '<span class="sh-pcard__p">' . $this->esc(sonika_cop($price)) . '</span>';
        $h .= '<span class="sh-pcard__cta">Ver producto &#8250;</span></span></a>';
        return $h;
    }

    private function guitarAssets(): string
    {
        if (self::$guitarIncluded) return '';
        self::$guitarIncluded = true;
        return '<script defer src="/assets/js/instruments/guitar.js"></script><script>document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("[data-wwi-instrument=guitar]").forEach(function(r){if(window.WWIInstruments&&WWIInstruments.guitar)WWIInstruments.guitar.init(r);});});</script>';
    }

    private const DYN_EFFECTS = ['rollup', 'rolldown', 'fade', 'blur', 'slide', 'scale', 'flip', 'flipchars', 'typewriter'];

    private function titleHtml(array $d): string
    {
        $items = $this->dynTitleItems($d['dyn_items'] ?? []);
        if (empty($d['dyn_enabled']) || count($items) < 2) {
            return $d['title'] ?? '' ? '<h2 class="sh-h1">' . $this->accentTitle((string)$d['title'], (string)($d['title_accent'] ?? '')) . '</h2>' : '';
        }
        $eff = (string)($d['dyn_effect'] ?? 'rollup');
        if (!in_array($eff, self::DYN_EFFECTS, true)) $eff = 'rollup';
        $chars = in_array($eff, ['flipchars', 'typewriter'], true);
        $iv = max(1200, min(15000, (int)($d['dyn_interval'] ?? 2800)));
        $pause = (string)($d['dyn_pause_hover'] ?? '1') !== '0' ? '1' : '0';
        $h = '<h2 class="sh-h1 sh-dyn" data-effect="' . $eff . '" data-interval="' . $iv . '" data-pause-hover="' . $pause . '" data-lines="2">';
        foreach ($items as $i => $it) {
            $l1 = $this->dynLine($it['l1'], $it['accent'], $chars);
            $l2 = $it['l2'] !== '' ? '<br/>' . $this->dynLine($it['l2'], $it['accent'], $chars) : '';
            $h .= '<span class="sh-dyn__i' . ($i === 0 ? ' is-on' : '') . '"' . ($i === 0 ? '' : ' aria-hidden="true"') . '>' . $l1 . $l2 . '</span>';
        }
        return $h . '</h2>';
    }

    private function dynTitleItems($raw): array
    {
        if (!is_array($raw)) return [];
        $out = [];
        foreach ($raw as $it) {
            if (!is_array($it)) continue;
            $l1 = trim((string)($it['l1'] ?? ''));
            $l2 = trim((string)($it['l2'] ?? ''));
            if ($l1 === '' && $l2 === '' && isset($it['text'])) {
                $parts = preg_split('/\r\n|\r|\n/', (string)$it['text']);
                $l1 = trim((string)($parts[0] ?? ''));
                $l2 = trim((string)($parts[1] ?? ''));
            }
            if ($l1 === '' && $l2 === '') continue;
            $out[] = ['l1' => $l1, 'l2' => $l2, 'accent' => trim((string)($it['accent'] ?? ''))];
        }
        return $out;
    }

    private function dynLine(string $text, string $accent, bool $chars): string
    {
        if ($text === '') return '';
        return $chars ? $this->charsHtml($text, $accent) : $this->accentTitle($text, $accent);
    }

    private function subHtml(array $d): string
    {
        $items = $d['dyn_sub_items'] ?? [];
        $items = is_array($items) ? array_values(array_filter($items, fn($it) => is_array($it) && trim((string)($it['text'] ?? '')) !== '')) : [];
        if (empty($d['dyn_sub_enabled']) || count($items) < 2) {
            return !empty($d['subtitle']) ? '<p class="sh-sub">' . $this->esc($d['subtitle']) . '</p>' : '';
        }
        $eff = (string)($d['dyn_sub_effect'] ?? 'fade');
        if (!in_array($eff, self::DYN_EFFECTS, true)) $eff = 'fade';
        $iv = max(2000, min(30000, (int)($d['dyn_sub_interval'] ?? 10000)));
        $pause = (string)($d['dyn_pause_hover'] ?? '1') !== '0' ? '1' : '0';
        $h = '<p class="sh-sub sh-dyn sh-dyn--sub" data-effect="' . $eff . '" data-interval="' . $iv . '" data-pause-hover="' . $pause . '" data-lines="2">';
        foreach ($items as $i => $it) {
            $h .= '<span class="sh-dyn__i' . ($i === 0 ? ' is-on' : '') . '"' . ($i === 0 ? '' : ' aria-hidden="true"') . '>' . $this->esc((string)$it['text']) . '</span>';
        }
        return $h . '</p>';
    }

    private function charsHtml(string $text, string $accent): string
    {
        $accent = trim($accent);
        $pos = false;
        if ($accent !== '') $pos = mb_strpos(mb_strtolower($text, 'UTF-8'), mb_strtolower($accent, 'UTF-8'));
        $alen = mb_strlen($accent, 'UTF-8');
        $len = mb_strlen($text, 'UTF-8');
        $out = '';
        for ($k = 0; $k < $len; $k++) {
            $ch = mb_substr($text, $k, 1, 'UTF-8');
            if ($ch === "\n") { $out .= '<br/>'; continue; }
            $inAcc = ($pos !== false && $k >= $pos && $k < $pos + $alen);
            $out .= '<span class="sh-dyn__c' . ($inAcc ? ' sh-hl' : '') . '" style="--d:' . $k . '">' . ($ch === ' ' ? '&nbsp;' : $this->esc($ch)) . '</span>';
        }
        return $out;
    }

    private function accentTitle(string $title, string $accent): string
    {
        $accent = trim($accent);
        if ($accent === '') return $this->esc($title);
        $pos = mb_stripos($title, $accent);
        if ($pos === false) return $this->esc($title);
        $before = mb_substr($title, 0, $pos);
        $mid = mb_substr($title, $pos, mb_strlen($accent));
        $after = mb_substr($title, $pos + mb_strlen($accent));
        return $this->esc($before) . '<span class="sh-hl">' . $this->esc($mid) . '</span>' . $this->esc($after);
    }

    private function attrs(array $d): string
    {
        $list = $d['attrs'] ?? [];
        if (!is_array($list)) return '';
        $out = '';
        foreach ($list as $a) {
            if (!is_array($a)) continue;
            if (array_key_exists('enabled', $a) && (string)$a['enabled'] === '0') continue;
            $ic = trim((string)($a['icon'] ?? ''));
            $tt = trim((string)($a['title'] ?? ''));
            $tx = trim((string)($a['text'] ?? ''));
            if ($tt === '' && $tx === '') continue;
            $out .= '<li class="sh-attr">'
                . ($ic !== '' ? '<span class="sh-attr__i" aria-hidden="true">' . (function_exists('sonika_icon') ? sonika_icon($ic) : $this->esc($ic)) . '</span>' : '')
                . '<span class="sh-attr__b">' . ($tt !== '' ? '<b>' . $this->esc($tt) . '</b>' : '') . ($tx !== '' ? ($tt !== '' ? ' — ' : '') . $this->esc($tx) : '') . '</span></li>';
        }
        return $out !== '' ? '<ul class="sh-attrs">' . $out . '</ul>' : '';
    }

    private function youtubeId(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) return $url;
        if (preg_match('~(?:youtu\.be/|v=|/embed/|/shorts/|/live/)([A-Za-z0-9_-]{11})~i', $url, $m)) return $m[1];
        return '';
    }

    private function cols(string $v): string
    {
        if (preg_match('~^(\d{2})/(\d{2})$~', trim($v), $m)) {
            $a = max(30, min(70, (int)$m[1]));
            return $a . 'fr ' . (100 - $a) . 'fr';
        }
        return '1fr 1fr';
    }

    private function ctas(array $d): string
    {
        $radiusMap = ['pill' => '999px', 'rounded' => '10px', 'square' => '0'];
        $radius = $radiusMap[(string)($d['btn_radius'] ?? 'pill')] ?? '999px';
        $bg = $this->color((string)($d['btn_bg'] ?? ''), '#F20D16');
        $tc = $this->color((string)($d['btn_tc'] ?? ''), '#ffffff');
        $bg2 = $this->color((string)($d['btn2_bg'] ?? ''), 'rgba(255,255,255,.14)');
        $tc2 = $this->color((string)($d['btn2_tc'] ?? ''), '#ffffff');
        $l = [];
        if (!empty($d['cta']) && !empty($d['cta_url'])) {
            $l[] = '<a class="sh-btn sh-btn--p" style="background:' . $bg . ';color:' . $tc . ';border-radius:' . $radius . '" href="' . $this->esc($d['cta_url']) . '">' . $this->esc($d['cta']) . '</a>';
        }
        if (!empty($d['cta2']) && !empty($d['cta2_url'])) {
            $l[] = '<a class="sh-btn sh-btn--s" style="color:' . $tc2 . ';background:' . $bg2 . ';border-radius:' . $radius . '" href="' . $this->esc($d['cta2_url']) . '">' . $this->esc($d['cta2']) . '</a>';
        }
        return $l ? '<div class="sh-ctas">' . implode('', $l) . '</div>' : '';
    }

    private function color(string $c, string $def): string
    {
        $c = trim($c);
        if ($c === '') return $def;
        if (preg_match('/^#[0-9a-f]{3,8}$/i', $c)) return $c;
        if (preg_match('/^rgba?\([0-9.,\s%\/]+\)$/i', $c)) return $c;
        return $def;
    }

    private function assets(): string
    {
        if (self::$assets) return '';
        self::$assets = true;
        return <<<'HTML'
<style>
.sh{position:relative;background:var(--sh-bg,#111);color:#fff;overflow:hidden;height:var(--sh-h,460px)}
.sh-track{position:relative;height:100%}
.sh-slide{position:absolute;inset:0;opacity:0;visibility:hidden;transition:opacity .4s ease;background:var(--sh-bg,#111)}
.sh-slide.is-on{opacity:1;visibility:visible;position:relative}
.sh-grid{height:100%;max-width:1180px;margin:0 auto;padding:var(--sh-padt,0px) var(--sh-padx,22px);display:grid;grid-template-columns:var(--sh-cols,1fr 1fr);gap:24px;align-items:stretch}
.sh-zone{min-width:0;display:flex;flex-direction:column;justify-content:var(--sh-valign,center)}
.sh-copy{display:flex;flex-direction:column;gap:10px;max-width:560px}
.sh-eyebrow{font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#ffd9db}
.sh-h1{font-family:var(--font-display,Sora),sans-serif;font-size:clamp(26px,3.4vw,46px);font-weight:800;line-height:1.05;letter-spacing:-.02em;white-space:pre-line}
.sh-hl{color:var(--brand,#F20D16)}
.sh-dyn{position:relative;overflow:hidden;perspective:600px}
.sh-dyn__i{display:block;opacity:0;transform:translate(var(--ix,0),var(--iy,0)) scale(var(--is,1));filter:blur(var(--ib,0));will-change:transform,opacity}
.sh-dyn--go .sh-dyn__i{transition:transform .65s cubic-bezier(.22,1,.36,1),opacity .5s ease,filter .55s ease}
.sh-dyn__i.is-in,.sh-dyn__i.is-on{opacity:1;--ix:0;--iy:0;--is:1;--ib:0}
.sh-dyn[data-effect="rollup"] .sh-dyn__i{--iy:115%}
.sh-dyn[data-effect="rollup"] .sh-dyn__i.is-out{--iy:-115%}
.sh-dyn[data-effect="rolldown"] .sh-dyn__i{--iy:-115%}
.sh-dyn[data-effect="rolldown"] .sh-dyn__i.is-out{--iy:115%}
.sh-dyn[data-effect="slide"] .sh-dyn__i{--ix:70%}
.sh-dyn[data-effect="slide"] .sh-dyn__i.is-out{--ix:-70%}
.sh-dyn[data-effect="blur"] .sh-dyn__i{--ib:16px;--iy:10%}
.sh-dyn[data-effect="scale"] .sh-dyn__i{--is:.82}
.sh-dyn[data-effect="flip"] .sh-dyn__i{transform:rotateX(-92deg)}
.sh-dyn[data-effect="flip"] .sh-dyn__i.is-in,.sh-dyn[data-effect="flip"] .sh-dyn__i.is-on{transform:rotateX(0)}
.sh-dyn[data-effect="flip"] .sh-dyn__i.is-out{transform:rotateX(92deg)}
.sh-dyn__c{display:inline-block;transform-origin:50% 100%;backface-visibility:hidden}
.sh-dyn--go .sh-dyn__c{transition:transform .5s cubic-bezier(.22,1,.36,1),opacity .45s ease;transition-delay:calc(var(--d,0)*32ms)}
.sh-dyn[data-effect="flipchars"] .sh-dyn__i .sh-dyn__c{transform:rotateX(-92deg);opacity:0}
.sh-dyn[data-effect="flipchars"] .sh-dyn__i.is-in .sh-dyn__c,.sh-dyn[data-effect="flipchars"] .sh-dyn__i.is-on .sh-dyn__c{transform:rotateX(0);opacity:1}
.sh-dyn[data-effect="flipchars"] .sh-dyn__i.is-out .sh-dyn__c{transform:rotateX(92deg);opacity:0}
.sh-dyn[data-effect="typewriter"] .sh-dyn__i .sh-dyn__c{opacity:0}
.sh-dyn[data-effect="typewriter"] .sh-dyn__i.is-in .sh-dyn__c,.sh-dyn[data-effect="typewriter"] .sh-dyn__i.is-on .sh-dyn__c{opacity:1}
.sh-dyn[data-effect="typewriter"] .sh-dyn__i.is-out .sh-dyn__c{opacity:0}
@media(prefers-reduced-motion:reduce){.sh-dyn__i,.sh-dyn__c{transition:none!important;transform:none!important;opacity:1!important;filter:none!important}}
.sh-sub{font-size:clamp(15px,1.5vw,19px);opacity:.95}
.sh-text{font-size:14px;opacity:.8;max-width:52ch}
.sh-ctas{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px}
.sh-btn{display:inline-flex;align-items:center;padding:11px 20px;border-radius:999px;font-weight:800;font-size:14px;text-decoration:none}
.sh-btn--p{background:var(--brand,#F20D16);color:#fff}
.sh-btn--s{background:rgba(255,255,255,.14);color:#fff;border:1px solid rgba(255,255,255,.3)}
.sh-media,.sh-video,.sh-promo{display:flex;flex-direction:column;gap:8px}
.sh-attrs{list-style:none;margin:10px 0 0;padding:0;display:flex;flex-direction:column;gap:7px}
.sh-attr{display:flex;gap:9px;align-items:flex-start;font-size:13px;line-height:1.35;opacity:.92}
.sh-attr__i{font-size:15px;line-height:1.2}
.sh-attr__b b{font-weight:800}
.sh-video__box{position:relative;aspect-ratio:16/9;width:100%;border-radius:16px;overflow:hidden;background:#0c0c0c;border:1px solid rgba(255,255,255,.1)}
.sh-video__box iframe,.sh-video__box video,.sh-video__box img{position:absolute;inset:0;width:100%;height:100%;border:0;display:block;object-fit:cover}
.sh-video__veil{position:absolute;inset:0;z-index:2;pointer-events:none;background:linear-gradient(to bottom,rgba(10,10,10,.65) 0,rgba(10,10,10,0) 54px),linear-gradient(to top,rgba(10,10,10,.6) 0,rgba(10,10,10,0) 48px)}
.sh-video--fill{height:100%;display:flex;flex-direction:column;justify-content:center}
.sh-video--center{height:100%;display:flex;align-items:center;justify-content:center}
.sh-zone--bleed{margin-top:calc(-1 * var(--sh-padt,0px));margin-bottom:calc(-1 * var(--sh-padt,0px))}
.sh-zone--right.sh-zone--bleed{margin-right:calc((min(100vw,1180px) - 100vw) / 2 - var(--sh-padx,22px))}
.sh-zone--left.sh-zone--bleed{margin-left:calc((min(100vw,1180px) - 100vw) / 2 - var(--sh-padx,22px))}
.sh-video--fill .sh-video__box{height:100%;min-height:260px;aspect-ratio:auto;border-radius:0;-webkit-mask-image:linear-gradient(to right,transparent 0,#000 26%);mask-image:linear-gradient(to right,transparent 0,#000 26%)}
.sh-video--center .sh-video__box{width:86%;max-width:560px;aspect-ratio:16/9;border-radius:18px;-webkit-mask-image:linear-gradient(to right,transparent 0,#000 26%);mask-image:linear-gradient(to right,transparent 0,#000 26%)}
.sh-video--fill .sh-video__box video,.sh-video--fill .sh-video__box iframe,.sh-video--fill .sh-video__box img,.sh-video--center .sh-video__box video,.sh-video--center .sh-video__box iframe,.sh-video--center .sh-video__box img{object-fit:cover}
.sh-media img,.sh-video video,.sh-promo img,.sh-product__img img{width:100%;height:auto;border-radius:16px;display:block;object-fit:cover}
.sh-media__cap h3,.sh-media__t,.sh-promo h3{font-family:var(--font-display,Sora),sans-serif;font-size:20px;font-weight:800}
.sh-product{display:flex;flex-direction:column;gap:4px;color:#fff;text-decoration:none;max-width:320px}
.sh-product__img img{aspect-ratio:1/1;object-fit:cover}
.sh-product__b{font-size:11px;letter-spacing:.06em;text-transform:uppercase;opacity:.75}
.sh-product__n{font-size:15px;font-weight:700}
.sh-product__p{font-size:16px;font-weight:800}
.sh-inst{display:flex;align-items:center;justify-content:center;height:100%}
.sh-inst__ph{display:flex;flex-direction:column;align-items:center;gap:6px;padding:28px;border:1px dashed rgba(255,255,255,.3);border-radius:16px;text-align:center;opacity:.85}
.sh-inst__ph span{font-size:40px}
.sh-guitar{display:flex;flex-direction:column;gap:12px;max-width:460px}
.sh-guitar__stage{position:relative;aspect-ratio:1536/1024;border-radius:14px;overflow:hidden;background:#fff;border:1px solid rgba(255,255,255,.14)}
.sh-guitar__img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block}
.sh-guitar__strings{position:absolute;inset:0;width:100%;height:100%}
.sh-str{transition:stroke .12s,stroke-width .12s}
.sh-str-hit{cursor:pointer;outline:none}
.sh-str-hit.on + .sh-str,.sh-str-hit:focus-visible + .sh-str{stroke:var(--brand,#F20D16);stroke-width:7}
.sh-str-dot{pointer-events:none}
.sh-guitar__profiles{display:flex;gap:6px;flex-wrap:wrap}
.sh-prof{border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.06);color:#fff;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer}
.sh-prof.on{background:var(--brand,#F20D16);border-color:var(--brand,#F20D16)}
.sh-guitar__hint{font-size:12px;opacity:.8}
.sh-pcard{display:flex;gap:12px;align-items:center;color:#fff;text-decoration:none;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.14);border-radius:12px;padding:10px}
.sh-pcard__img img{width:84px;height:84px;object-fit:contain;background:#fff;border-radius:8px;display:block}
.sh-pcard__info{display:flex;flex-direction:column;gap:2px;min-width:0;flex:1}
.sh-pcard__b{font-size:10.5px;letter-spacing:.06em;text-transform:uppercase;opacity:.7}
.sh-pcard__n{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font-weight:700;font-size:13.5px;line-height:1.25}
.sh-pcard__p{font-weight:800;color:#ffd9db}
.sh-pcard__cta{font-size:12px;font-weight:800;text-decoration:underline}
html[data-cap="low"] .sh-guitar__strings{display:none}
.sh-nav{position:absolute;top:50%;transform:translateY(-50%);z-index:3;width:40px;height:40px;border-radius:50%;border:1px solid rgba(255,255,255,.35);background:rgba(0,0,0,.35);color:#fff;font-size:20px;cursor:pointer}
.sh-prev{left:12px}.sh-next{right:12px}
.sh-dots{position:absolute;left:0;right:0;bottom:14px;display:flex;gap:8px;justify-content:center;z-index:3}
.sh-dot{width:9px;height:9px;border-radius:50%;border:0;background:rgba(255,255,255,.4);cursor:pointer}
.sh-dot.on{background:var(--brand,#F20D16);width:24px;border-radius:999px}
.sh-eqs{position:absolute;left:0;right:0;bottom:14px;display:flex;gap:12px;justify-content:center;align-items:flex-end;z-index:3}
.sh-eq{display:inline-flex;align-items:flex-end;gap:2px;height:18px;padding:4px 6px;border:0;background:transparent;cursor:pointer}
.sh-eq i{display:block;width:3px;height:6px;border-radius:2px;background:rgba(255,255,255,.45);transition:background .2s;transform-origin:bottom}
.sh-eq:hover i{background:rgba(255,255,255,.8)}
.sh-eq.on i{background:var(--brand,#F20D16);animation:shEq .9s ease-in-out infinite}
.sh-eq.on i:nth-child(2){animation-delay:.15s}
.sh-eq.on i:nth-child(3){animation-delay:.3s}
.sh-eq.on i:nth-child(4){animation-delay:.45s}
.sh-eq:focus-visible{outline:2px solid rgba(255,255,255,.8);outline-offset:2px;border-radius:6px}
@keyframes shEq{0%,100%{height:6px}50%{height:16px}}
@media(prefers-reduced-motion:reduce){.sh-eq.on i{animation:none;height:12px}}
@media(max-width:820px){.sh{height:auto}.sh-slide.is-on{position:relative}.sh-grid{grid-template-columns:1fr;padding:22px;gap:16px}.sh{--sh-h:auto}.sh-zone--bleed{margin:0}.sh-video--fill,.sh-video--center{height:auto}.sh-video--fill .sh-video__box{height:auto;min-height:0;aspect-ratio:16/9;border-radius:16px}.sh-video--center .sh-video__box{width:100%;max-width:none;aspect-ratio:16/9}.sh-video--fill .sh-video__box,.sh-video--center .sh-video__box{-webkit-mask-image:none;mask-image:none}}
@media(prefers-reduced-motion:reduce){.sh-slide{transition:none}}
</style>
<script>if(!window.__shInit){window.__shInit=1;document.addEventListener('DOMContentLoaded',function(){
document.querySelectorAll('.sh').forEach(function(root){
 var slides=root.querySelectorAll('.sh-slide'),dots=root.querySelectorAll('.sh-eq'),i=0,n=slides.length,ap=parseInt(root.getAttribute('data-autoplay')||'0',10),t=null;
 if(n<2)return;
 function go(k){disposeSlide(slides[i]);i=(k+n)%n;slides.forEach(function(s,j){s.classList.toggle('is-on',j===i);s.setAttribute('aria-hidden',j===i?'false':'true')});dots.forEach(function(d,j){d.classList.toggle('on',j===i)});resumeSlide(slides[i])}
 function disposeSlide(el){if(!el)return;el.querySelectorAll('[data-wwi-instrument]').forEach(function(inst){var id=inst.getAttribute('data-wwi-instrument');if(window.WWIInstruments&&WWIInstruments[id]&&WWIInstruments[id].dispose)try{WWIInstruments[id].dispose()}catch(e){}});el.querySelectorAll('video').forEach(function(v){try{v.pause()}catch(e){}});el.querySelectorAll('iframe.sh-video__fr').forEach(function(f){try{if(f.src){f.setAttribute('data-src',f.src);f.removeAttribute('src')}}catch(e){}})}
 function resumeSlide(el){if(!el)return;el.querySelectorAll('iframe.sh-video__fr[data-src]').forEach(function(f){try{if(!f.src)f.src=f.getAttribute('data-src')}catch(e){}});el.querySelectorAll('video').forEach(function(v){var vol=parseInt(v.getAttribute('data-volume'),10);if(!isNaN(vol))try{v.volume=Math.max(0,Math.min(1,vol/100))}catch(e){}});el.querySelectorAll('video[autoplay]').forEach(function(v){try{var p=v.play();if(p&&p.catch)p.catch(function(){})}catch(e){}})}
 var next=root.querySelector('.sh-next'),prev=root.querySelector('.sh-prev');
 if(next)next.addEventListener('click',function(){go(i+1);stop()});
 if(prev)prev.addEventListener('click',function(){go(i-1);stop()});
 dots.forEach(function(d){d.addEventListener('click',function(){go(parseInt(d.getAttribute('data-i'),10));stop()})});
 function start(){if(ap>0&&!matchMedia('(prefers-reduced-motion: reduce)').matches)t=setInterval(function(){go(i+1)},ap)}
 function stop(){if(t){clearInterval(t);t=null}}
 root.addEventListener('mouseenter',stop);
 root.addEventListener('focusin',stop);
 root.addEventListener('pointerdown',stop);
 root.addEventListener('keydown',function(e){if(e.key==='ArrowLeft'){go(i-1);stop()}if(e.key==='ArrowRight'){go(i+1);stop()}});
 start();
});});}</script>
<script>if(!window.__shDyn){window.__shDyn=1;(function(){
function init(root){
 var items=Array.prototype.slice.call(root.querySelectorAll(':scope > .sh-dyn__i'));
 if(items.length<2)return;
 var eff=root.getAttribute('data-effect')||'rollup';
 var iv=Math.max(1200,parseInt(root.getAttribute('data-interval')||'2800',10)||2800);
 var pause=root.getAttribute('data-pause-hover')!=='0';
 var rm=window.matchMedia&&matchMedia('(prefers-reduced-motion: reduce)').matches;
 function measure(){var lh=parseFloat(getComputedStyle(root).lineHeight)||0;var lines=parseInt(root.getAttribute('data-lines')||'0',10)||0;var minH=lines>0?lh*lines:0;var m=0;items.forEach(function(el){el.style.position='absolute';el.style.top='0';el.style.left='0';el.style.right='0';var h=el.offsetHeight;if(h>m)m=h});root.style.minHeight=Math.ceil(Math.max(m,minH)+2)+'px';}
 measure();window.addEventListener('resize',measure);
 if(rm)return;
 requestAnimationFrame(function(){root.classList.add('sh-dyn--go')});
 var i=0,busy=false;
 function show(n){
  if(busy||n===i)return;busy=true;
  var out=items[i],inc=items[n];
  inc.classList.add('is-in');inc.setAttribute('aria-hidden','false');
  out.classList.add('is-out');out.classList.remove('is-on');
  setTimeout(function(){
   out.classList.remove('is-out','is-in');out.setAttribute('aria-hidden','true');
   inc.classList.remove('is-in');inc.classList.add('is-on');
   i=n;busy=false;
  },eff==='flipchars'?950:700);
 }
 function next(){show((i+1)%items.length)}
 var t=setInterval(next,iv);
 function stop(){if(t){clearInterval(t);t=null}}
 function start(){stop();t=setInterval(next,iv)}
 if(pause){root.addEventListener('mouseenter',stop);root.addEventListener('mouseleave',start)}
 root.addEventListener('focusin',stop);root.addEventListener('focusout',start);
 document.addEventListener('visibilitychange',function(){document.hidden?stop():start()});
 if('IntersectionObserver' in window){new IntersectionObserver(function(es){es.forEach(function(e){e.isIntersecting?start():stop()})},{threshold:.2}).observe(root)}
}
document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.sh-dyn').forEach(init)});
})();}</script>
HTML;
    }
}
