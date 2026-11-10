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
    private static bool $soundAssets = false;
    private static array $instrumentImageCache = [];

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
        return ['id' => 'sonika-hero', 'name' => 'SONIKA Hero Builder', 'icon' => 'zap', 'category' => 'header', 'version' => '1.1.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'enabled', 'label' => 'Mostrar el hero', 'type' => 'toggle', 'default' => false],
            ['key' => 'autoplay', 'label' => 'Autoplay (ms · 0 = off)', 'type' => 'number', 'default' => 7000],
            ['key' => 'height', 'label' => 'Altura en escritorio (px)', 'type' => 'number', 'default' => 391],
        ];
    }

    public static function defaultConfig(): array
    {
        return ['enabled' => false, 'autoplay' => 7000, 'height' => 391];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        if (empty($c['enabled'])) return '';
        $svc = new HeroService();
        $theme = $svc->activeTheme();
        if ($theme === 'the-sound-takes-over') {
            return $this->renderSoundTakesOver($svc->themeConfig(), $svc->published());
        }
        $slides = $svc->published();
        if (!$slides) return '';

        $autoplay = max(0, min(20000, (int)($c['autoplay'] ?? 7000)));
        $height = max(320, min(720, (int)($c['height'] ?? 391)));

        $h = $this->assets();
        $h .= '<section class="sh" data-autoplay="' . $autoplay . '" style="--sh-h:' . $height . 'px" aria-roledescription="carrusel">';
        $h .= '<div class="sh-track">';
        foreach ($slides as $i => $s) {
            if ((string)($s['layout'] ?? 'split') === 'background') {
                $h .= $this->backgroundSlide($s, $i);
                continue;
            }
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

    private function renderSoundTakesOver(array $cfg, array $slides): string
    {
        $slidesData = $this->soundSlides($cfg, $slides);
        if (!$slidesData) return '';
        $first = $slidesData[0];
        $instruments = !empty($cfg['show_categories']) ? $this->soundInstruments($cfg) : [];
        $benefits = !empty($cfg['show_benefits']) ? $this->soundBenefits($cfg) : [];
        $autoplay = max(0, min(30000, (int)($cfg['autoplay'] ?? 9000)));
        $height = max(360, min(820, (int)($cfg['height'] ?? 448)));
        $overlay = max(0, min(85, (int)($cfg['overlay'] ?? 55))) / 100;
        $focusIndex = max(1, min(6, (int)($cfg['focus_index'] ?? 3)));
        $focusIndexMobile = max(1, min(3, (int)($cfg['focus_index_mobile'] ?? 2)));
        $focusScale = max(1.0, min(1.33, ((int)($cfg['focus_scale'] ?? 132)) / 100));
        $focusScaleMobile = max(1.0, min(1.35, ((int)($cfg['focus_scale_mobile'] ?? 132)) / 100));
        $edgeOpacity = max(.2, min(1, ((int)($cfg['edge_opacity'] ?? 41)) / 100));
        $edgeScale = max(.7, min(1, ((int)($cfg['edge_scale'] ?? 85)) / 100));
        $nearScale = max(.8, min(1, ((int)($cfg['near_scale'] ?? 90)) / 100));
        $imageMs = max(2000, min(12000, (int)($cfg['instrument_image_ms'] ?? 5000)));
        $labelMax = max(10, min(24, (int)($cfg['instrument_label_max'] ?? 16)));

        // Ajustes móviles: posición y escala sin reflujo de la página.
        $mCopyPx = HeroService::lengthToPx((float)($cfg['mobile_copy_offset'] ?? 0), (string)($cfg['mobile_copy_offset_unit'] ?? 'px'));
        $mCardsPx = HeroService::lengthToPx((float)($cfg['mobile_cards_offset'] ?? 0), (string)($cfg['mobile_cards_offset_unit'] ?? 'px'));
        $mCopyPx = max(-100, min(240, $mCopyPx));
        $mCardsPx = max(-100, min(240, $mCardsPx));
        $mCopyY = rtrim(rtrim(number_format($mCopyPx, 2, '.', ''), '0'), '.') . 'px';
        $mCardsY = rtrim(rtrim(number_format($mCardsPx, 2, '.', ''), '0'), '.') . 'px';
        $mCopyScale = max(.6, min(1.3, ((int)($cfg['mobile_group_scale'] ?? 100)) / 100));
        $mEyebrowScale = max(.6, min(1.4, ((int)($cfg['mobile_eyebrow_scale'] ?? 100)) / 100));
        $mTitleScale = max(.6, min(1.4, ((int)($cfg['mobile_title_scale'] ?? 100)) / 100));
        $mSubScale = max(.6, min(1.4, ((int)($cfg['mobile_sub_scale'] ?? 100)) / 100));
        $mCtaScale = max(.6, min(1.4, ((int)($cfg['mobile_cta_scale'] ?? 100)) / 100));
        $mCtaMode = in_array((string)($cfg['mobile_cta_width_mode'] ?? ''), ['full', 'auto', 'custom'], true)
            ? (string)$cfg['mobile_cta_width_mode'] : 'full';
        $mCtaUnit = in_array((string)($cfg['mobile_cta_width_unit'] ?? ''), ['%', 'px'], true)
            ? (string)$cfg['mobile_cta_width_unit'] : '%';
        $mCtaValue = (int)($cfg['mobile_cta_width_value'] ?? 100);
        $mCtaValue = $mCtaUnit === '%' ? max(20, min(100, $mCtaValue)) : max(40, min(340, $mCtaValue));
        $mCtaWidth = $mCtaValue . $mCtaUnit;
        $fmtScale = static function (float $value): string {
            return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
        };

        $n = count($slidesData);
        $hasVideo = false;
        foreach ($slidesData as $slide) {
            if (($slide['cta2_embed'] ?? '') !== '' && ($slide['cta2_label'] ?? '') !== '') { $hasVideo = true; break; }
        }
        $hasPrimary = ($first['cta_label'] ?? '') !== '' && ($first['cta_url'] ?? '') !== '';
        $hasFirstVideo = ($first['cta2_label'] ?? '') !== '' && ($first['cta2_embed'] ?? '') !== '';
        $hasFirstLink = ($first['cta2_label'] ?? '') !== '' && ($first['cta2_url'] ?? '') !== '' && !$hasFirstVideo;
        $hasActions = $hasPrimary || $hasFirstVideo || $hasFirstLink;

        $h = $this->soundAssets();
        $h .= '<div class="sth sth--m-cta-' . $mCtaMode . '" data-sth-hero data-sth-autoplay="' . $autoplay . '" data-sth-focus="' . $focusIndex . '" data-sth-focus-mobile="' . $focusIndexMobile . '" data-sth-img-ms="' . $imageMs . '" data-sth-label-max="' . $labelMax . '" style="--sth-h:' . $height . 'px;--sth-ov:' . round($overlay, 2) . ';--sth-focus-scale:' . $focusScale . ';--sth-focus-scale-mobile:' . $focusScaleMobile . ';--sth-edge-opacity:' . $edgeOpacity . ';--sth-edge-scale:' . $edgeScale . ';--sth-near-scale:' . $nearScale . ';--sth-m-copy-y:' . $mCopyY . ';--sth-m-cards-y:' . $mCardsY . ';--sth-m-copy-scale:' . $fmtScale($mCopyScale) . ';--sth-m-eyebrow-scale:' . $fmtScale($mEyebrowScale) . ';--sth-m-title-scale:' . $fmtScale($mTitleScale) . ';--sth-m-sub-scale:' . $fmtScale($mSubScale) . ';--sth-m-cta-scale:' . $fmtScale($mCtaScale) . ';--sth-m-cta-w:' . $mCtaWidth . '">';
        $h .= '<section class="sth__stage" aria-label="SONIKA - El Sonido Se Apodera">';
        $h .= '<div class="sth__bg" aria-hidden="true">';
        foreach ($slidesData as $i => $slide) {
            $focal = $this->esc((string)$slide['focal']);
            $alt = $this->esc((string)$slide['alt']);
            $desktop = $this->esc((string)$slide['desktop']);
            $mobile = $this->esc((string)$slide['mobile']);
            $h .= '<picture class="sth__pic' . ($i === 0 ? ' is-on' : '') . '" style="--sth-focal:' . $focal . '" data-sth-pic="' . $this->esc((string)$slide['id']) . '">';
            if ($mobile !== '' && $mobile !== $desktop) $h .= '<source media="(max-width: 820px)" srcset="' . $mobile . '"/>';
            $h .= '<img src="' . $desktop . '" alt="' . $alt . '" loading="' . ($i === 0 ? 'eager' : 'lazy') . '" decoding="async"' . ($i === 0 ? ' fetchpriority="high"' : '') . '/>';
            $h .= '</picture>';
        }
        $h .= '</div>';
        $h .= '<span class="sth__scrim" aria-hidden="true"></span>';
        $h .= '<div class="sth__inner">';
        $h .= '<div class="sth__copy" data-sth-copy>';
        $h .= '<span class="sth__eyebrow" data-sth-eyebrow' . (($first['eyebrow'] ?? '') === '' ? ' hidden' : '') . '>' . $this->esc((string)$first['eyebrow']) . '</span>';
        $titleScale = max(0.6, min(1.4, ((int)($first['title_size'] ?? 100)) / 100));
        $h .= '<h1 class="sth__title" data-sth-title style="--sth-title-scale:' . $titleScale . '"' . (($first['title_html'] ?? '') === '' ? ' hidden' : '') . '>' . ($first['title_html'] ?? '') . '</h1>';
        $h .= '<p class="sth__sub" data-sth-sub' . (($first['subtitle_html'] ?? '') === '' ? ' hidden' : '') . '>' . ($first['subtitle_html'] ?? '') . '</p>';
        $h .= '<div class="sth__actions" data-sth-actions' . ($hasActions ? '' : ' hidden') . '>';
        $h .= '<a class="sth__btn sth__btn--primary" data-sth-cta href="' . $this->esc($hasPrimary ? (string)$first['cta_url'] : '#') . '"' . ($hasPrimary ? '' : ' hidden') . '><span data-sth-cta-label>' . $this->esc($hasPrimary ? (string)$first['cta_label'] : '') . '</span> <span aria-hidden="true">&#8594;</span></a>';
        $h .= '<button type="button" class="sth__btn sth__btn--ghost" data-sth-video-open data-sth-cta2-video data-sth-video-embed="' . $this->esc($hasFirstVideo ? (string)$first['cta2_embed'] : '') . '"' . ($hasFirstVideo ? '' : ' hidden') . '><span class="sth__play" aria-hidden="true">&#9654;</span> <span data-sth-cta2-label>' . $this->esc($hasFirstVideo ? (string)$first['cta2_label'] : '') . '</span></button>';
        $h .= '<a class="sth__btn sth__btn--ghost" data-sth-cta2-link href="' . $this->esc($hasFirstLink ? (string)$first['cta2_url'] : '#') . '"' . ($hasFirstLink ? '' : ' hidden') . '><span data-sth-cta2-link-label>' . $this->esc($hasFirstLink ? (string)$first['cta2_label'] : '') . '</span> <span aria-hidden="true">&#8594;</span></a>';
        $h .= '</div>';
        $h .= '<span class="sth__sr" data-sth-live aria-live="polite"></span>';
        $h .= '</div>';
        if ($instruments) {
            $h .= '<aside class="sth__cats" aria-label="Instrumentos destacados">';
            $h .= '<div class="sth__cats-head"><span>Instrumentos</span><span>' . str_pad((string)count($instruments), 2, '0', STR_PAD_LEFT) . '</span></div>';
            $h .= '<div class="sth__inst-viewport" data-sth-inst-viewport>';
            $h .= '<div class="sth__inst-nav" role="group" aria-label="Navegación de instrumentos">';
            $h .= '<button type="button" class="sth__inst-arrow" data-sth-inst-prev aria-label="Instrumentos anteriores"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 14l6-6 6 6"/></svg></button>';
            $h .= '<button type="button" class="sth__inst-arrow" data-sth-inst-next aria-label="Instrumentos siguientes"><svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 10l6 6 6-6"/></svg></button>';
            $h .= '</div>';
            $h .= '<div class="sth__inst-track" data-sth-inst-track>';
            foreach ($instruments as $i => $cat) {
                $edge = (($i === 0 || $i === 5) && $i !== ($focusIndex - 1)) ? ' is-edge' : '';
                $near = (($i === 1 || $i === 4) && $i !== ($focusIndex - 1)) ? ' is-near' : '';
                $images = array_values((array)($cat['images'] ?? []));
                if (!$images) $images = [(string)($cat['image'] ?? '')];
                $images = array_values(array_filter($images, fn($u) => trim((string)$u) !== ''));
                $h .= '<a class="sth__cat' . ($i === ($focusIndex - 1) ? ' is-focus' : '') . $edge . $near . '" data-sth-inst data-sth-slug="' . $this->esc((string)($cat['slug'] ?? '')) . '" data-sth-imgs="' . $this->esc(json_encode($images, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) . '" href="' . $this->esc((string)$cat['href']) . '" title="' . $this->esc((string)$cat['label']) . '">';
                $h .= '<span class="sth__catimg"><img class="is-on" src="' . $this->esc((string)($images[0] ?? '')) . '" alt="" loading="lazy" decoding="async"/></span>';
                $h .= '<span class="sth__catlabel">' . $this->esc((string)$cat['label']) . '</span>';
                $h .= '<span class="sth__catarrow" aria-hidden="true">&#8250;</span>';
                $h .= '</a>';
            }
            $h .= '</div></div></aside>';
            $h .= '<script>(function(){var v=document.querySelector("[data-sth-inst-viewport]");if(!v)return;var r=v.closest("[data-sth-hero]");if(!r)return;var m=window.matchMedia&&window.matchMedia("(max-width:900px)").matches;var n=(parseInt(m?r.getAttribute("data-sth-focus-mobile"):r.getAttribute("data-sth-focus"),10)||(m?2:3))-1;var a=v.querySelectorAll("[data-sth-inst]");for(var i=0;i<a.length;i++){a[i].classList.toggle("is-focus",i===n);if(m){a[i].classList.remove("is-edge");a[i].classList.remove("is-near");}}})();</script>';
        }
        $h .= '</div>';
        if ($n > 1) {
            $h .= '<div class="sth__nav" role="group" aria-label="Navegación del hero">';
            $h .= '<button type="button" class="sth__arrow" data-sth-prev aria-label="Slide anterior">&#8249;</button>';
            $h .= '<div class="sth__dots" role="tablist" aria-label="Slides">';
            foreach ($slidesData as $i => $slide) {
                $dotLabel = 'Slide ' . ($i + 1);
                if (($slide['title_plain'] ?? '') !== '') $dotLabel .= ': ' . $slide['title_plain'];
                $h .= '<button type="button" class="sth__dot' . ($i === 0 ? ' is-on' : '') . '" data-sth-dot="' . $i . '" role="tab" aria-label="' . $this->esc($dotLabel) . '" aria-selected="' . ($i === 0 ? 'true' : 'false') . '"></button>';
            }
            $h .= '</div>';
            $h .= '<span class="sth__slidecount" data-sth-count>01 / ' . str_pad((string)$n, 2, '0', STR_PAD_LEFT) . '</span>';
            $h .= '<button type="button" class="sth__pause" data-sth-pause aria-label="Pausar carrusel" aria-pressed="false"><span data-sth-pause-icon aria-hidden="true">&#10074;&#10074;</span></button>';
            $h .= '<button type="button" class="sth__arrow" data-sth-next aria-label="Slide siguiente">&#8250;</button>';
            $h .= '</div>';
        }
        $h .= '</section>';
        if ($benefits) {
            $h .= '<div class="sth-benefits"><div class="sth-benefits__in">';
            foreach ($benefits as $i => $b) {
                $icon = function_exists('sonika_icon') ? sonika_icon((string)$b['icon']) : '';
                $h .= '<div class="sth-benefit' . ($i === 0 ? ' is-active' : '') . '" data-sth-benefit><span class="sth-benefit__icon" aria-hidden="true">' . $icon . '</span><span><b>' . $this->esc((string)$b['title']) . '</b><small>' . $this->esc((string)$b['text']) . '</small></span></div>';
            }
            $h .= '</div></div>';
        }
        if ($hasVideo) {
            $h .= '<dialog class="sth-video" data-sth-dialog aria-label="Video del Hero"><button type="button" class="sth-video__close" data-sth-video-close aria-label="Cerrar video">&#215;</button><div class="sth-video__box" data-sth-video-frame></div></dialog>';
        }
        $h .= '<script type="application/json" data-sth-slides-json>' . json_encode($slidesData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) . '</script>';
        $h .= '</div>';
        return $h;
    }

    private function soundTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '') return '';
        $safe = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safe = preg_replace('/\*(.+?)\*/u', '<em>$1</em>', $safe);
        return nl2br((string)$safe, false);
    }

    private function soundBackgrounds(array $cfg, array $slides): array
    {
        $out = [];
        foreach ((array)($cfg['backgrounds'] ?? []) as $row) {
            if (empty($row['enabled'])) continue;
            $desktop = $this->mediaUrl((string)($row['desktop'] ?? ''));
            $mobile = $this->mediaUrl((string)($row['mobile'] ?? ''));
            if ($desktop === '' && $mobile === '') continue;
            $focal = trim((string)($row['focal'] ?? '50% 50%'));
            if (!preg_match('/^\d{1,3}%\s+\d{1,3}%$/', $focal)) $focal = '50% 50%';
            $out[] = ['desktop' => $desktop !== '' ? $desktop : $mobile, 'mobile' => $mobile, 'alt' => trim((string)($row['alt'] ?? '')), 'focal' => $focal];
        }
        if ($out) return $out;
        foreach ($slides as $s) {
            $d = (array)($s['design'] ?? []);
            $desktop = $this->mediaUrl((string)($d['bg_image'] ?? ''));
            $mobile = $this->mediaUrl((string)($d['bg_image_mobile'] ?? ''));
            $poster = $this->mediaUrl((string)($d['bg_poster'] ?? ''));
            if ($desktop === '' && $poster !== '') $desktop = $poster;
            if ($desktop === '' && $mobile === '') continue;
            $x = max(0, min(100, (int)($d['bg_x'] ?? 50)));
            $y = max(0, min(100, (int)($d['bg_y'] ?? 50)));
            $out[] = ['desktop' => $desktop !== '' ? $desktop : $mobile, 'mobile' => $mobile, 'alt' => trim((string)($d['bg_alt'] ?? '')), 'focal' => $x . '% ' . $y . '%'];
        }
        if ($out) return $out;
        return [['desktop' => '/assets/uploads/wontia_2c020fab54677229.webp', 'mobile' => '/assets/uploads/wontia_4a97a4f3251c0c73.png', 'alt' => 'Escenario SONIKA', 'focal' => '42% 50%']];
    }

    private function soundSlides(array $cfg, array $classicSlides): array
    {
        $out = [];
        foreach ((array)($cfg['slides'] ?? []) as $i => $row) {
            if (!is_array($row) || empty($row['enabled'])) continue;
            $desktop = $this->mediaUrl((string)($row['desktop'] ?? ''));
            $mobile = $this->mediaUrl((string)($row['mobile'] ?? ''));
            if ($desktop === '' && $mobile === '') continue;

            $id = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($row['id'] ?? ''))) ?: 'slide-' . ($i + 1);
            $eyebrow = trim((string)($row['eyebrow'] ?? ''));
            $title = trim((string)($row['title'] ?? ''));
            $subtitle = trim((string)($row['subtitle'] ?? ''));
            $ctaLabel = trim((string)($row['cta_label'] ?? ''));
            $ctaUrl = $this->linkUrl((string)($row['cta_url'] ?? ''));
            $cta2Label = trim((string)($row['cta2_label'] ?? ''));
            $cta2Video = trim((string)($row['cta2_video'] ?? ''));
            $cta2Url = $this->linkUrl((string)($row['cta2_url'] ?? ''));

            $embed = '';
            if ($cta2Video !== '') {
                $yt = $this->youtubeId($cta2Video);
                if ($yt !== '') $embed = 'https://www.youtube-nocookie.com/embed/' . $yt . '?autoplay=1&rel=0&modestbranding=1&playsinline=1';
            }

            $overlay = $row['overlay'] ?? null;
            if ($overlay === null || $overlay === '') $overlay = round(((int)($cfg['overlay'] ?? 55)) / 100, 2);
            else $overlay = round(((int)$overlay) / 100, 2);

            $accent = trim((string)($row['accent'] ?? ''));
            if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $accent)) $accent = '#F20D16';

            $focal = trim((string)($row['focal'] ?? '50% 50%'));
            if (!preg_match('/^\d{1,3}%\s+\d{1,3}%$/', $focal)) $focal = '50% 50%';

            $titlePlain = trim(preg_replace('/\*(.+?)\*/u', '$1', $title));
            $titleSize = max(60, min(140, (int)($row['title_size'] ?? 100)));
            $out[] = [
                'id' => $id,
                'eyebrow' => $eyebrow,
                'title' => $title,
                'title_size' => $titleSize,
                'title_plain' => $titlePlain,
                'title_html' => $this->soundTitle($title),
                'subtitle' => $subtitle,
                'subtitle_html' => $subtitle !== '' ? nl2br($this->esc($subtitle), false) : '',
                'cta_label' => $ctaLabel,
                'cta_url' => $ctaLabel !== '' ? $ctaUrl : '',
                'cta2_label' => $cta2Label,
                'cta2_video' => $cta2Video,
                'cta2_embed' => $embed,
                'cta2_url' => $cta2Url,
                'desktop' => $desktop !== '' ? $desktop : $mobile,
                'mobile' => $mobile,
                'alt' => trim((string)($row['alt'] ?? '')),
                'focal' => $focal,
                'overlay' => $overlay,
                'accent' => $accent,
                'instrument_focus' => preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($row['instrument_focus'] ?? ''))),
            ];
        }
        if ($out) return $out;
        if (array_key_exists('slides', $cfg)) return [];

        $backgrounds = $this->soundBackgrounds($cfg, $classicSlides);
        $eyebrow = trim((string)($cfg['eyebrow'] ?? ''));
        $title = trim((string)($cfg['title'] ?? ''));
        $subtitle = trim((string)($cfg['subtitle'] ?? ''));
        $ctaLabel = trim((string)($cfg['cta_label'] ?? ''));
        $ctaUrl = $this->linkUrl((string)($cfg['cta_url'] ?? ''));
        $cta2Label = trim((string)($cfg['cta2_label'] ?? ''));
        $cta2Video = trim((string)($cfg['cta2_video'] ?? ''));
        $cta2Url = '';
        $embed = '';
        if ($cta2Video !== '') {
            $yt = $this->youtubeId($cta2Video);
            if ($yt !== '') $embed = 'https://www.youtube-nocookie.com/embed/' . $yt . '?autoplay=1&rel=0&modestbranding=1&playsinline=1';
        }
        $overlay = round(((int)($cfg['overlay'] ?? 55)) / 100, 2);
        $titlePlain = trim(preg_replace('/\*(.+?)\*/u', '$1', $title));
        foreach ($backgrounds as $i => $bg) {
            $out[] = [
                'id' => 'slide-' . ($i + 1),
                'eyebrow' => $eyebrow,
                'title' => $title,
                'title_size' => 100,
                'title_plain' => $titlePlain,
                'title_html' => $this->soundTitle($title),
                'subtitle' => $subtitle,
                'subtitle_html' => $subtitle !== '' ? nl2br($this->esc($subtitle), false) : '',
                'cta_label' => $ctaLabel,
                'cta_url' => $ctaLabel !== '' ? $ctaUrl : '',
                'cta2_label' => $cta2Label,
                'cta2_video' => $cta2Video,
                'cta2_embed' => $embed,
                'cta2_url' => $cta2Url,
                'desktop' => (string)$bg['desktop'],
                'mobile' => (string)($bg['mobile'] ?? ''),
                'alt' => (string)($bg['alt'] ?? ''),
                'focal' => (string)($bg['focal'] ?? '50% 50%'),
                'overlay' => $overlay,
                'accent' => '#F20D16',
                'instrument_focus' => $i === 0 ? 'guitarras' : '',
            ];
        }
        return $out;
    }

    private function soundInstruments(array $cfg): array
    {
        $out = [];
        foreach ((array)($cfg['categories'] ?? []) as $row) {
            if (empty($row['enabled'])) continue;
            $label = trim((string)($row['label'] ?? ''));
            if ($label === '') continue;
            $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower((string)($row['slug'] ?? '')));
            $href = $this->mediaUrl((string)($row['href'] ?? ''));
            if ($href === '' || str_starts_with($href, '/assets/')) $href = '/tienda';
            $image = $this->mediaUrl((string)($row['image'] ?? ''));
            if ($slug === 'baquetas' && ($image === '' || str_contains($image, 'BAQUETAVICFIRTHA.CLASSIC.PUNTAMADERA5AVG'))) {
                $image = 'https://cdn.shopify.com/s/files/1/0269/4178/4117/products/BAQ039.png?v=1574455593&width=640';
            }
            if ($image === '' && $slug !== '' && function_exists('sonika_category_image')) $image = sonika_category_image($slug, 360);
            if ($image === '') continue;
            $images = $this->soundInstrumentImages($label, $slug, $href, $image, 3);
            $out[] = [
                'label' => $label,
                'slug' => $slug,
                'href' => $href,
                'image' => $images[0] ?? $image,
                'images' => $images,
            ];
        }
        return $out;
    }

    private function soundInstrumentImages(string $label, string $slug, string $href, string $fallback, int $limit = 3): array
    {
        $limit = max(1, $limit);
        $fallback = $this->thumbUrl($fallback, 240);
        $cacheKey = strtolower($slug . '|' . $label . '|' . $fallback . '|' . $limit);
        if (isset(self::$instrumentImageCache[$cacheKey])) return self::$instrumentImageCache[$cacheKey];

        $pool = [];
        $seen = [];
        $add = function (string $url) use (&$pool, &$seen, $limit): void {
            $url = trim($url);
            if ($url === '' || count($pool) >= $limit) return;
            $key = $this->imageKey($url);
            if ($key === '' || isset($seen[$key])) return;
            $seen[$key] = 1;
            $pool[] = $url;
        };

        $add($fallback);

        $products = function_exists('sonika_products') ? sonika_products() : [];
        $matched = null;
        if ($fallback !== '' && $products) {
            $fallbackKey = $this->imageKey($fallback);
            foreach ($products as $product) {
                foreach ((array)($product['images'] ?? []) as $img) {
                    $src = is_array($img) ? (string)($img['src'] ?? '') : (string)$img;
                    if ($src !== '' && $this->imageKey($src) === $fallbackKey) { $matched = $product; break 2; }
                }
            }
        }

        $query = $this->instrumentQuery($label, $slug, $href);
        $categorySlug = $this->instrumentCategorySlug($slug);
        $categoryFirst = in_array($slug, ['cuerdas-guitarras', 'amplificadores', 'microfono-miche', 'cabezas-moviles', 'pianos'], true);
        $candidates = [];
        if ($matched) $candidates[] = $matched;

        $addSearch = function () use (&$candidates, $query, $slug): void {
            if ($query === '' || !function_exists('sonika_search')) return;
            foreach (sonika_search($query) as $product) {
                if (!$this->instrumentMatches($product, $slug)) continue;
                $candidates[] = $product;
                if (count($candidates) >= 16) break;
            }
        };
        $addCategory = function () use (&$candidates, $categorySlug, $slug): void {
            if ($categorySlug === '' || !function_exists('sonika_category_products') || !function_exists('sonika_category')) return;
            if (!sonika_category($categorySlug)) return;
            $sub = $slug === 'bajos' ? 'bajos' : '';
            foreach (sonika_category_products($categorySlug, $sub) as $product) {
                if (!$this->instrumentMatches($product, $slug)) continue;
                $candidates[] = $product;
                if (count($candidates) >= 32) break;
            }
        };

        if ($categoryFirst) { $addCategory(); $addSearch(); }
        else { $addSearch(); $addCategory(); }

        foreach ($candidates as $product) {
            foreach ($this->productImageUrls($product, 240) as $url) {
                $add($url);
                if (count($pool) >= $limit) break 2;
            }
        }

        self::$instrumentImageCache[$cacheKey] = $pool;
        return $pool;
    }

    private function instrumentQuery(string $label, string $slug, string $href): string
    {
        $map = [
            'guitarras' => 'guitarra', 'pianos' => 'piano', 'acordeon' => 'acordeon', 'baterias' => 'bateria',
            'bajos' => 'bajo', 'violines' => 'violin', 'ukelele' => 'ukelele', 'saxofon' => 'saxofon',
            'timbales' => 'timbal', 'bongoes' => 'bongo', 'tumbadoras' => 'tumbadora', 'baquetas' => 'baqueta',
            'microfono-miche' => 'microfono', 'cabinas' => 'cabina', 'amplificadores-fender' => 'amplificador fender',
            'amplificadores' => 'amplificador', 'audifonos' => 'audifonos', 'cabezas-moviles' => 'cabeza movil',
            'cuerdas-guitarras' => 'cuerda guitarra',
        ];
        if (isset($map[$slug])) return $map[$slug];
        if (preg_match('~[?&]q=([^&]+)~i', $href, $m)) return urldecode($m[1]);
        return trim($label);
    }

    private function instrumentCategorySlug(string $slug): string
    {
        $map = [
            'guitarras' => 'guitarras', 'pianos' => 'pianos', 'acordeon' => 'acordeon', 'baterias' => 'percusion',
            'bajos' => 'guitarras', 'violines' => 'guitarras', 'ukelele' => 'guitarras', 'saxofon' => 'acordeon',
            'timbales' => 'percusion', 'bongoes' => 'percusion', 'tumbadoras' => 'percusion', 'baquetas' => 'percusion',
            'microfono-miche' => 'microfono-miche', 'cabinas' => 'amplificadores', 'amplificadores-fender' => 'amplificadores',
            'amplificadores' => 'amplificadores', 'audifonos' => 'amplificadores', 'cabezas-moviles' => 'cabeza-movil',
            'cuerdas-guitarras' => 'cuerdas-guitarras',
        ];
        return $map[$slug] ?? $slug;
    }

    private function instrumentKeywords(string $slug): array
    {
        $map = [
            'guitarras' => ['GUITARRA'],
            'pianos' => ['PIANO', 'TECLADO', 'SINTETIZ', 'CELVIANO', 'PRIVIA'],
            'acordeon' => ['ACORDE'],
            'baterias' => ['BATER', 'REDOBL', 'PLATILL', 'TAROLA', 'BOMBO'],
            'bajos' => ['BAJO'],
            'violines' => ['VIOLIN', 'VIOLA', 'VIOLONCHELO'],
            'ukelele' => ['UKELELE', 'UKULELE'],
            'saxofon' => ['SAXOF', 'SAX'],
            'timbales' => ['TIMBAL'],
            'bongoes' => ['BONGO'],
            'tumbadoras' => ['CONGA', 'TUMBA', 'DJEMBE'],
            'baquetas' => ['BAQUETA'],
            'microfono-miche' => ['MICROFON', 'MICRO'],
            'cabinas' => ['CABINA', 'PARLANTE', 'ALTAVOZ', 'BAFLE', 'SUBWOOFER'],
            'amplificadores-fender' => ['AMPLIF', 'FENDER'],
            'amplificadores' => ['AMPLIF', 'MIXER', 'CONSOLA', 'PARLANTE', 'CABINA', 'PROCESAD', 'INTERFAZ', 'AUDIFONO', 'POTENCIA', 'MONITOR'],
            'audifonos' => ['AUDIFONO', 'AURICULAR'],
            'cabezas-moviles' => ['CABEZA', 'PAR LED', 'PARLED', 'MOVIL', 'LUZ', 'REFLECTOR', 'BLINDER'],
            'cuerdas-guitarras' => [],
        ];
        return $map[$slug] ?? [];
    }

    private function instrumentMatches(array $product, string $slug): bool
    {
        $raw = trim((string)($product['title'] ?? '') . ' ' . (string)($product['product_type'] ?? ''));
        if (function_exists('sonika_product_tags')) {
            $raw .= ' ' . implode(' ', sonika_product_tags($product));
        }
        $hay = function_exists('sonika_fold') ? sonika_fold($raw) : mb_strtolower($raw, 'UTF-8');
        if (!in_array($slug, ['cuerdas-guitarras', 'baquetas'], true)) {
            $accessory = ['accesorio', 'adaptador', 'cable', 'forro', 'funda', 'correa', 'soporte', 'stand', 'pedal', 'estuche', 'atril', 'pick', 'capodastro', 'clavijero', 'repuesto', 'limpiador', 'protector', 'rack', 'clamp', 'acople'];
            foreach ($accessory as $word) { if (str_contains($hay, $word)) return false; }
        }
        $keywords = $this->instrumentKeywords($slug);
        if (!$keywords) return true;
        $hits = 0;
        foreach ($keywords as $word) {
            $needle = function_exists('sonika_fold') ? sonika_fold($word) : mb_strtolower($word, 'UTF-8');
            if (str_contains($hay, $needle)) $hits++;
        }
        return $slug === 'amplificadores-fender' ? $hits >= 2 : $hits >= 1;
    }

    private function productImageUrls(array $product, int $w = 240): array
    {
        $out = [];
        foreach ((array)($product['images'] ?? []) as $img) {
            $src = is_array($img) ? (string)($img['src'] ?? '') : (string)$img;
            if ($src === '') continue;
            $out[] = $this->thumbUrl($src, $w);
        }
        return $out;
    }

    private function thumbUrl(string $url, int $w = 240): string
    {
        $url = trim($url);
        if ($url === '' || $w <= 0) return $url;
        if (!str_starts_with($url, '/') && !preg_match('~^https?://~i', $url)) return $url;
        if (preg_match('/[?&]width=\d+/i', $url)) return (string)preg_replace('/([?&])width=\d+/i', '${1}width=' . $w, $url);
        return $url . (str_contains($url, '?') ? '&' : '?') . 'width=' . $w;
    }

    private function imageKey(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        $url = (string)preg_replace('/([?&])(width|height)=\d+/i', '$1', $url);
        $url = (string)preg_replace('/[?&]+$/', '', $url);
        return strtolower($url);
    }

    private function soundBenefits(array $cfg): array
    {
        $out = [];
        foreach ((array)($cfg['benefits'] ?? []) as $row) {
            if (empty($row['enabled'])) continue;
            $title = trim((string)($row['title'] ?? ''));
            if ($title === '') continue;
            $out[] = [
                'icon' => preg_replace('/[^a-z0-9_\-]/i', '', (string)($row['icon'] ?? 'check')) ?: 'check',
                'title' => $title,
                'text' => trim((string)($row['text'] ?? '')),
            ];
        }
        return $out;
    }

    private function soundAssets(): string
    {
        if (self::$soundAssets) return '';
        self::$soundAssets = true;
        return <<<'HTML'
<style id="sth-css">
.sth{--sth-red:#F20D16;--sth-ink:#07080d;position:relative;background:#07080d;color:#fff;font-family:'Sora',system-ui,sans-serif}
.sth__stage{position:relative;isolation:isolate;overflow:hidden;height:var(--sth-h,448px);min-height:var(--sth-h,448px);background:#07080d;touch-action:pan-y}
.sth__bg{position:absolute;inset:0;z-index:0;overflow:hidden;background:#07080d}
.sth__pic{position:absolute;inset:0;display:block;opacity:0;transition:opacity .9s cubic-bezier(.22,1,.36,1)}
.sth__pic.is-on{opacity:1}
.sth__pic img{display:block;width:100%;height:100%;object-fit:cover;object-position:var(--sth-focal,50% 50%);transform:scale(1.015)}
.sth__scrim{position:absolute;inset:0;z-index:1;pointer-events:none;background:linear-gradient(90deg,rgba(4,5,9,.94) 0%,rgba(4,5,9,.78) 32%,rgba(4,5,9,.26) 62%,rgba(4,5,9,.72) 100%),linear-gradient(0deg,rgba(4,5,9,var(--sth-ov,.55)) 0%,rgba(4,5,9,.12) 46%,rgba(4,5,9,0) 72%)}
.sth__inner{position:relative;z-index:2;box-sizing:border-box;width:100%;max-width:1440px;height:100%;margin:0 auto;padding:clamp(26px,3.5vw,48px) clamp(19px,4.25vw,60px) 29px;display:grid;grid-template-columns:minmax(0,1fr) minmax(238px,280px);gap:clamp(17px,2.9vw,46px);align-items:center}
.sth__copy{max-width:720px;min-width:0;min-height:clamp(200px,22vw,280px);display:flex;flex-direction:column;justify-content:center;transition:opacity .18s ease,transform .18s ease}
.sth__copy.is-changing{opacity:0;transform:translateY(8px)}
.sth [hidden]{display:none!important}
.sth__sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.sth__eyebrow{display:inline-flex;align-items:center;gap:7px;font-size:8.5px;font-weight:800;letter-spacing:.18em;text-transform:uppercase;color:rgba(255,255,255,.82)}
.sth__eyebrow::before{content:"";width:16px;height:2px;border-radius:2px;background:var(--sth-red);box-shadow:0 0 14px rgba(242,13,22,.8)}
.sth__title{margin:clamp(12px,1.7vw,20px) 0 13px;font-size:calc(clamp(35px,4.9vw,75px) * var(--sth-title-scale,1));line-height:.9;letter-spacing:-.05em;font-weight:800;text-transform:uppercase;text-wrap:balance;text-shadow:0 12px 36px rgba(0,0,0,.4)}
.sth__title em{font-style:normal;color:var(--sth-red)}
.sth__sub{max-width:332px;margin:0 0 20px;color:rgba(255,255,255,.76);font-size:clamp(11px,.9vw,13.2px);line-height:1.6}
.sth__actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.sth__btn{display:inline-flex;align-items:center;justify-content:center;gap:7px;min-height:35px;padding:0 14px;border-radius:999px;font:800 10px/1 'Sora',system-ui,sans-serif;text-decoration:none;cursor:pointer;transition:transform .18s ease,background-color .18s ease,border-color .18s ease,color .18s ease;border:1px solid transparent}
.sth__btn:focus-visible{outline:2px solid #fff;outline-offset:3px}
.sth__btn--primary{background:var(--sth-red);color:#fff;box-shadow:0 10px 28px rgba(242,13,22,.28)}
.sth__btn--primary:hover{background:#d80b15;transform:translateY(-2px)}
.sth__btn--ghost{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.24);color:#fff;backdrop-filter:blur(10px)}
.sth__btn--ghost:hover{background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.48);transform:translateY(-2px)}
.sth__play{font-size:8px;line-height:1;color:var(--sth-red)}
.sth__cats{display:flex;flex-direction:column;gap:7px;min-width:0;max-height:100%;padding:2px 2px 2px 0;touch-action:auto}
.sth__cats::-webkit-scrollbar{display:none}
.sth__cats-head{display:flex;justify-content:space-between;align-items:center;padding:0 4px 2px;font-size:7px;font-weight:800;letter-spacing:.16em;text-transform:uppercase;color:rgba(255,255,255,.5)}
.sth__cats-head span{color:rgba(255,255,255,.32);font-variant-numeric:tabular-nums}
.sth__inst-viewport{position:relative;overflow:hidden;box-sizing:border-box;height:327px;padding:0 35px}
.sth__inst-nav{position:absolute;right:3px;top:4px;bottom:4px;display:flex;flex-direction:column;justify-content:space-between;z-index:3;pointer-events:none}
.sth__inst-arrow{pointer-events:auto;width:24px;height:24px;padding:0;border-radius:50%;border:1px solid rgba(255,255,255,.14);background:rgba(8,9,14,.34);backdrop-filter:blur(8px);color:rgba(255,255,255,.58);display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color .2s ease,border-color .2s ease,color .2s ease,transform .2s ease,box-shadow .2s ease;animation:sthInstPulse 2.8s ease-in-out infinite}
.sth__inst-arrow:hover,.sth__inst-arrow:focus-visible{background:var(--sth-red);border-color:var(--sth-red);color:#fff;transform:scale(1.08);animation:none}
.sth__inst-arrow:active{transform:scale(.96)}
.sth__inst-arrow:focus-visible{outline:2px solid #fff;outline-offset:3px}
.sth__inst-arrow svg{display:block}
@keyframes sthInstPulse{0%,100%{box-shadow:0 0 0 0 rgba(255,255,255,0)}50%{box-shadow:0 0 0 5px rgba(255,255,255,.06)}}
.sth__inst-track{display:flex;flex-direction:column;gap:9px;transition:transform .58s cubic-bezier(.22,1,.36,1);will-change:transform}
.sth__cat{display:grid;grid-template-columns:39px minmax(0,1fr) 13px;align-items:center;gap:8px;flex:0 0 47px;height:47px;box-sizing:border-box;padding:5px 8px 5px 5px;border-radius:9px;background:rgba(8,9,14,.68);border:1px solid rgba(255,255,255,.12);color:#fff;text-decoration:none;backdrop-filter:blur(12px);transition:transform .35s ease,background-color .35s ease,border-color .35s ease,box-shadow .35s ease,opacity .35s ease}
.sth__cat:hover{transform:translateX(-2px);border-color:rgba(255,255,255,.34);background:rgba(17,18,26,.86)}
.sth__cat.is-focus{transform:scale(var(--sth-focus-scale,1.32));transform-origin:center;border-color:rgba(242,13,22,.75);background:rgba(242,13,22,.14);box-shadow:0 10px 26px rgba(242,13,22,.16);z-index:2}
.sth__cat.is-focus:hover{transform:scale(var(--sth-focus-scale,1.32)) translateX(-2px);background:rgba(242,13,22,.18);border-color:rgba(242,13,22,.85)}
.sth__cat.is-focus .sth__catimg{transform:scale(1.08)}
.sth__cat.is-edge{opacity:var(--sth-edge-opacity,.41);transform:scale(var(--sth-edge-scale,.85));transform-origin:center}
.sth__cat.is-near{transform:scale(var(--sth-near-scale,.9));transform-origin:center}
.sth__cat.is-edge:hover,.sth__cat.is-edge:focus-visible{opacity:1;transform:scale(var(--sth-edge-scale,.85)) translateX(-2px)}
.sth__cat.is-near:hover,.sth__cat.is-near:focus-visible{transform:scale(var(--sth-near-scale,.9)) translateX(-2px)}
.sth__cat.is-slide-focus{box-shadow:0 0 0 1px var(--sth-red),0 0 24px rgba(242,13,22,.24)}
.sth__catimg{position:relative;width:39px;height:35px;border-radius:6px;overflow:hidden;display:block;background:#f7f7f8;transition:transform .35s ease}
.sth__catimg img{position:absolute;inset:0;display:block;width:100%;height:100%;object-fit:contain;opacity:0;transition:opacity .45s ease}
.sth__catimg img.is-on{opacity:1}
.sth__catlabel{min-width:0;font-size:9.5px;font-weight:800;letter-spacing:-.01em;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sth__catarrow{font-size:15px;line-height:1;opacity:.72;text-align:right}
.sth__cat:focus-visible{outline:2px solid #fff;outline-offset:2px}
.sth__nav{position:absolute;left:max(19px,calc((100% - 1440px)/2 + 60px));bottom:13px;z-index:3;display:flex;align-items:center;gap:7px}
.sth__arrow{width:24px;height:24px;border-radius:50%;border:1px solid rgba(255,255,255,.25);background:rgba(0,0,0,.22);color:#fff;font-size:15px;line-height:1;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color .18s ease,border-color .18s ease}
.sth__arrow:hover{background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.55)}
.sth__dots{display:flex;align-items:center;gap:4px}
.sth__dot{width:17px;height:3px;padding:0;border:0;border-radius:99px;background:rgba(255,255,255,.28);cursor:pointer;transition:width .2s ease,background-color .2s ease}
.sth__dot.is-on{width:29px;background:var(--sth-red)}
.sth__dot:focus-visible{outline:2px solid #fff;outline-offset:3px}
.sth__slidecount{font-size:8px;font-weight:800;letter-spacing:.12em;color:rgba(255,255,255,.55);font-variant-numeric:tabular-nums;margin:0 2px;white-space:nowrap}
.sth__pause{width:24px;height:24px;padding:0;border-radius:50%;border:1px solid rgba(255,255,255,.25);background:rgba(0,0,0,.22);color:#fff;font-size:8px;line-height:1;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;transition:background-color .18s ease,border-color .18s ease}
.sth__pause:hover{background:rgba(255,255,255,.16);border-color:rgba(255,255,255,.55)}
.sth__pause:focus-visible{outline:2px solid #fff;outline-offset:3px}
.sth-benefits{background:#fff;color:#111114;border-bottom:1px solid #e6e6e8}
.sth-benefits__in{max-width:1440px;margin:0 auto;padding:11px clamp(19px,4.25vw,60px);display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:13px}
.sth-benefit{position:relative;display:flex;align-items:center;gap:8px;min-width:0;padding:3px 5px;border-radius:8px;transition:background-color .35s ease,transform .35s ease}
.sth-benefit::after{content:"";position:absolute;left:8px;right:8px;bottom:0;height:2px;border-radius:2px;background:var(--sth-red);transform:scaleX(0);transform-origin:left;transition:transform .35s ease}
.sth-benefit:hover,.sth-benefit.is-active{background:rgba(242,13,22,.055);transform:translateY(-1px)}
.sth-benefit:hover::after,.sth-benefit.is-active::after{transform:scaleX(1)}
.sth-benefit__icon{flex:0 0 auto;display:flex;color:var(--sth-red);transition:transform .35s ease}
.sth-benefit:hover .sth-benefit__icon,.sth-benefit.is-active .sth-benefit__icon{transform:translateY(-1px) scale(1.08)}
.sth-benefit__icon svg{width:16px;height:16px;display:block}
.sth-benefit b{display:block;font-size:9.5px;font-weight:800;line-height:1.2;transition:color .35s ease}
.sth-benefit:hover b,.sth-benefit.is-active b{color:#a8070e}
.sth-benefit small{display:block;margin-top:2px;color:#6d6d76;font-size:8.5px;line-height:1.3}
.sth-video{position:fixed;inset:0;z-index:9999;margin:auto;width:min(92vw,980px);height:max-content;max-height:none;padding:0;border:0;border-radius:16px;background:#000;color:#fff;box-shadow:0 30px 100px rgba(0,0,0,.65);overflow:visible}
.sth-video::backdrop{background:rgba(0,0,0,.84);backdrop-filter:blur(4px)}
.sth-video.is-fallback{box-shadow:0 0 0 100vmax rgba(0,0,0,.84),0 30px 100px rgba(0,0,0,.65)}
.sth-video__box{position:relative;aspect-ratio:16/9;background:#000}
.sth-video__box iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
.sth-video__close{position:absolute;top:-42px;right:0;width:32px;height:32px;padding:0;border-radius:50%;border:1px solid rgba(255,255,255,.35);background:rgba(0,0,0,.35);color:#fff;font-size:22px;line-height:1;cursor:pointer;display:grid;place-items:center}
html.sth-video-open,html.sth-video-open body{overflow:hidden}
@media(max-width:900px){.sth__stage{height:auto;min-height:var(--sth-h,448px)}.sth__inner{grid-template-columns:1fr;align-content:center;gap:20px;padding-bottom:46px}.sth__copy{max-width:760px;min-height:0}.sth__cats{display:flex;flex-direction:column;gap:8px;max-height:none;padding:0}.sth__inst-viewport{height:auto;overflow:hidden;padding:8px 4px}.sth__inst-nav{position:static;flex-direction:row;justify-content:flex-end;gap:6px;padding:0 2px 4px;pointer-events:auto}.sth__inst-arrow{width:26px;height:26px}.sth__inst-arrow svg{transform:rotate(-90deg)}.sth__inst-track{flex-direction:row;gap:7px}.sth__cat.is-focus{transform:scale(var(--sth-focus-scale-mobile,1.32))}.sth__cat.is-focus:hover{transform:scale(var(--sth-focus-scale-mobile,1.32)) translateX(-2px)}.sth__cat{flex:0 0 135px;width:135px;height:45px;min-height:45px;grid-template-columns:36px minmax(0,1fr) 13px;gap:6px}.sth__catimg{width:36px;height:32px}.sth__catlabel{font-size:9.5px}.sth__cat.is-edge:not(.is-focus),.sth__cat.is-near:not(.is-focus){opacity:1;transform:none}.sth__cat.is-edge:not(.is-focus):hover,.sth__cat.is-edge:not(.is-focus):focus-visible,.sth__cat.is-near:not(.is-focus):hover,.sth__cat.is-near:not(.is-focus):focus-visible{transform:none}.sth__nav{left:clamp(19px,4.25vw,60px);bottom:12px}}
@media(max-width:700px){.sth__stage{min-height:0;height:auto}.sth__inner{display:flex;flex-direction:column;align-items:stretch;gap:18px;padding:27px 14px 19px}.sth__title{font-size:calc(clamp(31px,8.5vw,46px) * var(--sth-title-scale,1));margin:12px 0 10px}.sth__sub{font-size:11px;margin-bottom:15px}.sth__actions{display:grid;grid-template-columns:1fr;gap:8px}.sth__btn{width:100%;min-height:35px}.sth__cats{gap:7px;margin:0 -14px;padding:0 14px}.sth__cats::-webkit-scrollbar{display:none}.sth__cats-head{display:flex;font-size:7px;padding:0 2px 2px}.sth__inst-viewport{height:auto;padding:8px 4px}.sth__inst-track{flex-direction:row;gap:22px}.sth__cat{flex:0 0 98px;width:98px;height:43px;min-height:43px;grid-template-columns:28px minmax(0,1fr);gap:4px;padding:4px 5px 4px 4px}.sth__catimg{width:28px;height:25px}.sth__catlabel{font-size:8.5px;line-height:1.1;white-space:normal;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}.sth__catarrow{display:none}.sth__nav{position:static;justify-content:center;margin-top:15px}.sth-benefits__in{grid-template-columns:repeat(2,minmax(0,1fr));gap:9px 12px;padding:9px 14px}.sth-benefit b{font-size:9.5px}.sth-benefit small{font-size:8.5px}.sth-video__close{top:-36px}
.sth__copy{transform:translateY(var(--sth-m-copy-y,0px)) scale(var(--sth-m-copy-scale,1));transform-origin:left top}
.sth__copy.is-changing{transform:translateY(calc(var(--sth-m-copy-y,0px) + 8px)) scale(var(--sth-m-copy-scale,1))}
.sth__eyebrow{transform:scale(var(--sth-m-eyebrow-scale,1));transform-origin:left center}
.sth__title{transform:scale(var(--sth-m-title-scale,1));transform-origin:left top}
.sth__sub{transform:scale(var(--sth-m-sub-scale,1));transform-origin:left top}
.sth__actions{transform:scale(var(--sth-m-cta-scale,1));transform-origin:left top}
.sth__cats,.sth__nav{transform:translateY(var(--sth-m-cards-y,0px))}
.sth--m-cta-full .sth__actions{display:grid;grid-template-columns:1fr;gap:8px}
.sth--m-cta-full .sth__btn{width:100%}
.sth--m-cta-auto .sth__actions{display:flex;flex-wrap:wrap;gap:8px}
.sth--m-cta-auto .sth__btn{width:auto;min-width:0}
.sth--m-cta-custom .sth__actions{display:flex;flex-wrap:wrap;gap:8px}
.sth--m-cta-custom .sth__btn{width:var(--sth-m-cta-w,100%);max-width:100%;min-width:0}}
@media(prefers-reduced-motion:reduce){.sth__pic,.sth__copy,.sth__btn,.sth__cat,.sth__arrow,.sth__dot,.sth__pause,.sth__inst-arrow,.sth__inst-track,.sth-benefit,.sth-benefit__icon,.sth-benefit::after,.sth-benefit b{transition:none!important}.sth__inst-arrow{animation:none!important}.sth__pic img{transform:none}.sth__inst-viewport{overflow-y:auto!important;scrollbar-width:thin}.sth-benefit:hover,.sth-benefit.is-active{transform:none}}
@media(max-width:900px) and (prefers-reduced-motion:reduce){.sth__inst-viewport{overflow-x:auto!important;overflow-y:hidden!important}}
</style>
<script>
(function(){
  function init(){
  'use strict';
  var root=document.querySelector('[data-sth-hero]');
  if(!root||root.getAttribute('data-sth-ready')==='1')return;
  root.setAttribute('data-sth-ready','1');

  var pics=[].slice.call(root.querySelectorAll('.sth__pic'));
  var dots=[].slice.call(root.querySelectorAll('[data-sth-dot]'));
  var copy=root.querySelector('[data-sth-copy]');
  var eyebrow=root.querySelector('[data-sth-eyebrow]');
  var titleEl=root.querySelector('[data-sth-title]');
  var subEl=root.querySelector('[data-sth-sub]');
  var actions=root.querySelector('[data-sth-actions]');
  var cta=root.querySelector('[data-sth-cta]');
  var ctaLabel=root.querySelector('[data-sth-cta-label]');
  var videoBtn=root.querySelector('[data-sth-cta2-video]');
  var videoLabel=root.querySelector('[data-sth-cta2-label]');
  var linkBtn=root.querySelector('[data-sth-cta2-link]');
  var linkLabel=root.querySelector('[data-sth-cta2-link-label]');
  var live=root.querySelector('[data-sth-live]');
  var pauseBtn=root.querySelector('[data-sth-pause]');
  var countEl=root.querySelector('[data-sth-count]');
  var slidesData=[];
  try{slidesData=JSON.parse((root.querySelector('[data-sth-slides-json]')||{}).textContent||'[]')}catch(e){slidesData=[]}
  if(!Array.isArray(slidesData)||!slidesData.length)return;
  var n=slidesData.length;
  var index=0,timer=null,userPaused=false,dialogOpen=false,heroVisible=true;
  var delay=parseInt(root.getAttribute('data-sth-autoplay')||'0',10);
  var imageMs=parseInt(root.getAttribute('data-sth-img-ms')||'5000',10);
  var labelMax=parseFloat(root.getAttribute('data-sth-label-max')||'16')||16;
  var reduce=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var defaultAccent=(getComputedStyle(root).getPropertyValue('--sth-red')||'').trim()||'#F20D16';

  function pad(v){return (v<10?'0':'')+v}
  function announce(s,idx){if(live)live.textContent='Slide '+(idx+1)+' de '+n+(s&&s.title_plain?': '+s.title_plain:'')}
  function updateUrl(idx){
    try{
      var u=new URL(location.href);
      if(idx>0)u.searchParams.set('slide',String(idx+1));else u.searchParams.delete('slide');
      history.replaceState(null,'',u.pathname+u.search+(u.hash||''));
    }catch(e){}
  }
  function paintCopy(s){
    if(!s)return;
    if(eyebrow){eyebrow.textContent=s.eyebrow||'';eyebrow.hidden=!s.eyebrow;}
    if(titleEl){titleEl.innerHTML=s.title_html||'';titleEl.hidden=!s.title_html;titleEl.style.setProperty('--sth-title-scale',String((parseInt(s.title_size,10)||100)/100));}
    if(subEl){subEl.innerHTML=s.subtitle_html||'';subEl.hidden=!s.subtitle_html;}
    var hasPrimary=!!(s.cta_label&&s.cta_url);
    if(cta){cta.hidden=!hasPrimary;if(hasPrimary)cta.href=s.cta_url;}
    if(ctaLabel)ctaLabel.textContent=s.cta_label||'';
    var hasVideo=!!(s.cta2_label&&s.cta2_embed);
    var hasLink=!!(s.cta2_label&&s.cta2_url);
    if(videoBtn){videoBtn.hidden=!hasVideo;if(hasVideo)videoBtn.setAttribute('data-sth-video-embed',s.cta2_embed);}
    if(videoLabel)videoLabel.textContent=s.cta2_label||'';
    if(linkBtn){linkBtn.hidden=!(hasLink&&!hasVideo);if(hasLink)linkBtn.href=s.cta2_url;}
    if(linkLabel)linkLabel.textContent=s.cta2_label||'';
    if(actions)actions.hidden=!(hasPrimary||hasVideo||hasLink);
    if(countEl)countEl.textContent=pad(index+1)+' / '+pad(n);
  }
  var copyTimer=null;
  function applyCopy(s,animate){
    if(copyTimer){clearTimeout(copyTimer);copyTimer=null;}
    if(animate&&!reduce&&copy){
      copy.classList.add('is-changing');
      copyTimer=setTimeout(function(){paintCopy(s);copy.classList.remove('is-changing');copyTimer=null;},180);
    }else{
      if(copy)copy.classList.remove('is-changing');
      paintCopy(s);
    }
  }
  function dispatch(name,detail){try{document.dispatchEvent(new CustomEvent(name,{detail:detail}))}catch(e){}}
  function show(next,opts){
    if(n<1)return;
    next=(next+n)%n;
    index=next;
    pics.forEach(function(p,j){p.classList.toggle('is-on',j===index)});
    dots.forEach(function(d,j){d.classList.toggle('is-on',j===index);d.setAttribute('aria-selected',j===index?'true':'false')});
    var s=slidesData[index]||{};
    if(s.overlay!==undefined&&s.overlay!==null&&s.overlay!=='')root.style.setProperty('--sth-ov',s.overlay);
    root.style.setProperty('--sth-red',s.accent||defaultAccent);
    applyCopy(s,!(opts&&opts.initial));
    if(s.instrument_focus)instHighlight(s.instrument_focus);else instClearFocus();
    if(countEl)countEl.textContent=pad(index+1)+' / '+pad(n);
    if(opts&&opts.manual){updateUrl(index);announce(s,index);}
    dispatch('sonika:hero-slide',{id:s.id||'',index:index,title:s.title_plain||'',manual:!!(opts&&opts.manual)});
  }
  function stop(){if(timer!==null){clearInterval(timer);timer=null;}stopBenefits();}
  function start(){
    if(userPaused||reduce||dialogOpen)return;
    if(timer===null&&n>1&&delay>0){timer=setInterval(function(){if(document.hidden){stop();return;}show(index+1,{manual:false});},delay);}
    startBenefits();instStart();
  }

  var benefits=[].slice.call(root.querySelectorAll('[data-sth-benefit]'));
  var benefitZone=root.querySelector('.sth-benefits');
  var benefitIndex=0,benefitTimer=null,benefitPaused=false;
  function setBenefit(i){if(!benefits.length)return;benefits[benefitIndex].classList.remove('is-active');benefitIndex=(i+benefits.length)%benefits.length;benefits[benefitIndex].classList.add('is-active');}
  function stopBenefits(){if(benefitTimer!==null){clearInterval(benefitTimer);benefitTimer=null;}}
  function startBenefits(){if(userPaused||reduce||benefitPaused||benefits.length<2||benefitTimer!==null)return;benefitTimer=setInterval(function(){if(document.hidden)return;setBenefit(benefitIndex+1);},10000);}
  if(benefitZone){benefitZone.addEventListener('mouseenter',function(){benefitPaused=true;stopBenefits();});benefitZone.addEventListener('mouseleave',function(){benefitPaused=false;startBenefits();});benefitZone.addEventListener('focusin',function(){benefitPaused=true;stopBenefits();});benefitZone.addEventListener('focusout',function(){benefitPaused=false;startBenefits();});}

  var instViewport=root.querySelector('[data-sth-inst-viewport]');
  var instTrack=root.querySelector('[data-sth-inst-track]');
  var instItems=instTrack?[].slice.call(instTrack.querySelectorAll('[data-sth-inst]')):[];
  var instOriginal=instItems.length;
  var instFocusDesktop=Math.max(0,Math.min(5,(parseInt(root.getAttribute('data-sth-focus'),10)||3)-1));
  var instFocusMobile=Math.max(0,Math.min(2,(parseInt(root.getAttribute('data-sth-focus-mobile'),10)||2)-1));
  var instStep=0,instTimer=null,instPaused=false,instMobile=false,instPitch=0,instFocusTimer=null,instManualTimer=null,instManualDir=0;
  function instCanRotate(){return instOriginal>(instMobile?3:6);}
  function instMeasure(){
    instMobile=!!(window.matchMedia&&window.matchMedia('(max-width:900px)').matches);
    if(!instItems.length)return;
    var first=instItems[0];
    var gap=parseFloat(getComputedStyle(instTrack).gap)||0;
    var size=instMobile?first.getBoundingClientRect().width:first.getBoundingClientRect().height;
    instPitch=size+gap;
  }
  function fitLabels(){
    var labels=root.querySelectorAll('.sth__catlabel');
    if(!labels.length)return;
    var mobile=!!(window.matchMedia&&window.matchMedia('(max-width:900px)').matches);
    var max=mobile?Math.min(labelMax,11.5):labelMax;
    var min=9.5;
    labels.forEach(function(label){
      label.style.fontSize='';
      if(mobile){label.style.fontSize=max+'px';return;}
      var avail=label.clientWidth;
      if(!avail)return;
      var size=max;
      label.style.fontSize=size+'px';
      while(size>min&&label.scrollWidth>avail){
        size-=0.25;
        if(size<min)size=min;
        label.style.fontSize=size+'px';
      }
    });
  }
  function instTabbables(){
    if(!instItems.length||reduce)return;
    var visible=instMobile?3:6;
    instItems.forEach(function(el,i){
      var on=i>=instStep&&i<instStep+visible;
      if(on){el.removeAttribute('aria-hidden');el.setAttribute('tabindex','0');}
      else{el.setAttribute('aria-hidden','true');el.setAttribute('tabindex','-1');}
    });
  }
  function instApply(step,animate){
    if(!instTrack||!instItems.length)return;
    instStep=step;
    instTrack.style.transition=animate?'':'none';
    instTrack.style.transform=(instMobile?'translateX(':'translateY(')+(-step*instPitch)+'px)';
    if(!animate){instTrack.getBoundingClientRect();instTrack.style.transition='';}
    instItems.forEach(function(el){el.classList.remove('is-focus');el.classList.remove('is-edge');el.classList.remove('is-near');});
    var visible=instMobile?3:6;
    var fi=instMobile?instFocusMobile:instFocusDesktop;
    if(fi>=visible)fi=visible-1;
    var focusIndex=step+fi;
    var focus=instItems[focusIndex];
    if(focus)focus.classList.add('is-focus');
    if(!instMobile&&instItems.length>=6){
      if(instItems[step]&&step!==focusIndex)instItems[step].classList.add('is-edge');
      if(instItems[step+5]&&step+5!==focusIndex)instItems[step+5].classList.add('is-edge');
      if(instItems[step+1]&&step+1!==focusIndex)instItems[step+1].classList.add('is-near');
      if(instItems[step+4]&&step+4!==focusIndex)instItems[step+4].classList.add('is-near');
    }
    instTabbables();
  }
  function instClearFocus(){
    if(instFocusTimer){clearTimeout(instFocusTimer);instFocusTimer=null;}
    instItems.forEach(function(el){el.classList.remove('is-slide-focus')});
  }
  function instHighlight(slug){
    if(!slug||!instItems.length)return;
    instClearFocus();
    var found=false;
    instItems.forEach(function(el){if(el.getAttribute('data-sth-slug')===slug){el.classList.add('is-slide-focus');found=true;}});
    if(!found)return;
    instFocusTimer=setTimeout(function(){instItems.forEach(function(el){el.classList.remove('is-slide-focus')});instFocusTimer=null;},2600);
  }
  function instNext(){
    if(!instItems.length||!instCanRotate())return;
    var next=instStep+1;
    if(next>instOriginal){instApply(0,false);return;}
    instApply(next,true);
    if(next===instOriginal){setTimeout(function(){if(instStep===instOriginal)instApply(0,false);},700);}
  }
  function instPrev(){
    if(!instItems.length||!instCanRotate())return;
    if(instStep<=0){instApply(Math.max(0,instOriginal-1),false);return;}
    instApply(instStep-1,true);
  }
  function instStop(){if(instTimer!==null){clearInterval(instTimer);instTimer=null;}}
  function instStart(){
    if(userPaused||instPaused||reduce||instTimer!==null||!instCanRotate())return;
    instTimer=setInterval(function(){if(!document.hidden)instNext();},7000);
  }
  function instManualStep(dir){
    if(dir<0)instPrev();else instNext();
  }
  function instManualStart(dir){
    if(instManualTimer!==null){clearInterval(instManualTimer);instManualTimer=null;}
    instManualDir=dir;
    instPaused=true;
    instStop();
    instManualStep(dir);
    instManualTimer=setInterval(function(){instManualStep(instManualDir);},600);
  }
  function instManualStop(){
    if(instManualTimer!==null){clearInterval(instManualTimer);instManualTimer=null;}
  }
  function initImageRotators(){
    if(reduce)return;
    var cards=[].slice.call(root.querySelectorAll('[data-sth-imgs]'));
    if(!cards.length)return;
    var groups={};
    cards.forEach(function(card){
      var labelEl=card.querySelector('.sth__catlabel');
      var slug=card.getAttribute('data-sth-slug')||(labelEl?labelEl.textContent:'');
      if(!slug)return;
      if(!groups[slug])groups[slug]={slug:slug,cards:[],images:[],idx:0,paused:false,locks:0,timer:null};
      groups[slug].cards.push(card);
    });
    Object.keys(groups).forEach(function(slug,gi){
      var group=groups[slug];
      var first=group.cards[0];
      var imgs=[];
      try{imgs=JSON.parse(first.getAttribute('data-sth-imgs')||'[]')}catch(e){imgs=[]}
      if(!Array.isArray(imgs)||imgs.length<2)return;
      group.images=imgs.filter(function(u){return typeof u==='string'&&u!==''});
      if(group.images.length<2)return;
      group.cards.forEach(function(card){
        var box=card.querySelector('.sth__catimg');
        if(!box)return;
        var base=box.querySelector('img');
        if(!base)return;
        var alt=document.createElement('img');
        alt.alt='';
        alt.loading='lazy';
        alt.decoding='async';
        box.appendChild(alt);
        card.__imgLayers=[base,alt];
        card.__imgActive=0;
        card.addEventListener('mouseenter',function(){group.locks++;group.paused=true;});
        card.addEventListener('mouseleave',function(){group.locks=Math.max(0,group.locks-1);group.paused=group.locks>0;});
        card.addEventListener('focusin',function(){group.locks++;group.paused=true;});
        card.addEventListener('focusout',function(){group.locks=Math.max(0,group.locks-1);group.paused=group.locks>0;});
      });
      function swap(card,url){
        var layers=card.__imgLayers;
        if(!layers)return;
        var active=card.__imgActive||0;
        var incoming=layers[1-active];
        var outgoing=layers[active];
        incoming.src=url;
        incoming.classList.add('is-on');
        outgoing.classList.remove('is-on');
        card.__imgActive=1-active;
      }
      function nextImage(){
        if(group.paused||userPaused||dialogOpen||document.hidden||!heroVisible)return;
        var next=(group.idx+1)%group.images.length;
        var pre=new Image();
        pre.onload=function(){
          if(group.paused||userPaused||dialogOpen||document.hidden||!heroVisible)return;
          group.cards.forEach(function(card){swap(card,group.images[next])});
          group.idx=next;
        };
        pre.onerror=function(){};
        pre.src=group.images[next];
      }
      var startDelay=2200+(gi%6)*380;
      setTimeout(function(){
        nextImage();
        group.timer=setInterval(nextImage,Math.max(2000,imageMs)+(gi%5)*320);
      },startDelay);
    });
  }
  if(instViewport&&instTrack&&instOriginal){
    instMeasure();
    if(!reduce&&instCanRotate()){
      instTrack.insertAdjacentHTML('beforeend',instTrack.innerHTML);
      instItems=[].slice.call(instTrack.querySelectorAll('[data-sth-inst]'));
      instMeasure();
      instViewport.addEventListener('mouseenter',function(){instPaused=true;instStop();});
      instViewport.addEventListener('mouseleave',function(){instPaused=false;instStart();});
      instViewport.addEventListener('focusin',function(){instPaused=true;instStop();});
      instViewport.addEventListener('focusout',function(){setTimeout(function(){if(!instViewport.contains(document.activeElement)){instPaused=false;instStart();}},0);});
      window.addEventListener('resize',function(){instStop();instMeasure();fitLabels();instApply(instStep,false);instStart();});
    }
    instApply(0,false);
  }
  function bindInstArrow(btn,dir){
    if(!btn)return;
    function start(){instManualStart(dir);}
    function stop(){instManualStop();}
    btn.addEventListener('mouseenter',start);
    btn.addEventListener('mouseleave',stop);
    btn.addEventListener('pointerdown',function(e){if(e.pointerType==='mouse')return;e.preventDefault();start();});
    btn.addEventListener('pointerup',function(e){if(e.pointerType==='mouse')return;stop();instPaused=false;instStart();});
    btn.addEventListener('pointercancel',function(e){if(e.pointerType==='mouse')return;stop();instPaused=false;instStart();});
    btn.addEventListener('pointerleave',function(e){if(e.pointerType==='mouse')return;stop();instPaused=false;instStart();});
    btn.addEventListener('click',function(e){if(e.detail===0)instManualStep(dir);});
  }
  bindInstArrow(root.querySelector('[data-sth-inst-prev]'),-1);
  bindInstArrow(root.querySelector('[data-sth-inst-next]'),1);
  fitLabels();
  initImageRotators();
  if(document.fonts&&document.fonts.ready)document.fonts.ready.then(fitLabels);

  function syncPause(){
    if(!pauseBtn)return;
    pauseBtn.setAttribute('aria-pressed',userPaused?'true':'false');
    pauseBtn.setAttribute('aria-label',userPaused?'Reproducir carrusel':'Pausar carrusel');
    var icon=pauseBtn.querySelector('[data-sth-pause-icon]');
    if(icon)icon.textContent=userPaused?'\u25B6':'\u275A\u275A';
    root.classList.toggle('is-paused',userPaused);
  }
  if(pauseBtn){
    if(delay<=0)pauseBtn.hidden=true;
    pauseBtn.addEventListener('click',function(){userPaused=!userPaused;if(userPaused){stop();instStop();}else start();syncPause();});
  }

  var prev=root.querySelector('[data-sth-prev]'),next=root.querySelector('[data-sth-next]');
  if(prev)prev.addEventListener('click',function(){stop();show(index-1,{manual:true});start();});
  if(next)next.addEventListener('click',function(){stop();show(index+1,{manual:true});start();});
  dots.forEach(function(dot){dot.addEventListener('click',function(){stop();show(parseInt(dot.getAttribute('data-sth-dot'),10)||0,{manual:true});start();});});

  var sx=0,sy=0,swiping=false;
  root.addEventListener('pointerdown',function(e){
    if(e.pointerType==='mouse')return;
    if(e.target&&e.target.closest&&e.target.closest('a,button,.sth__cats,.sth__nav,.sth__pause'))return;
    sx=e.clientX;sy=e.clientY;swiping=true;
  },{passive:true});
  root.addEventListener('pointerup',function(e){
    if(!swiping)return;swiping=false;
    var dx=e.clientX-sx,dy=e.clientY-sy;
    if(Math.abs(dx)<40||Math.abs(dx)<Math.abs(dy))return;
    stop();show(index+(dx<0?1:-1),{manual:true});start();
  },{passive:true});
  root.addEventListener('pointercancel',function(){swiping=false;},{passive:true});

  if(copy)copy.addEventListener('click',function(e){
    var a=e.target&&e.target.closest?e.target.closest('[data-sth-cta],[data-sth-cta2-link],[data-sth-cta2-video]'):null;
    if(!a)return;
    var s=slidesData[index]||{};
    var kind=a.hasAttribute('data-sth-cta')?'primary':(a.hasAttribute('data-sth-cta2-video')?'video':'secondary');
    var labelEl=a.querySelector('[data-sth-cta-label],[data-sth-cta2-label],[data-sth-cta2-link-label]');
    var label=labelEl?labelEl.textContent.trim():(a.textContent||'').trim();
    dispatch('sonika:hero-cta',{slide:s.id||'',index:index,cta:kind,label:label,href:a.getAttribute('href')||a.getAttribute('data-sth-video-embed')||''});
  });
  var catsPanel=root.querySelector('.sth__cats');
  if(catsPanel)catsPanel.addEventListener('click',function(e){
    var a=e.target&&e.target.closest?e.target.closest('[data-sth-inst]'):null;
    if(!a)return;
    var s=slidesData[index]||{};
    var label=a.querySelector('.sth__catlabel');
    dispatch('sonika:hero-instrument',{slide:s.id||'',index:index,label:label?label.textContent:'',href:a.getAttribute('href')||''});
  });

  root.addEventListener('mouseenter',stop);
  root.addEventListener('mouseleave',start);
  root.addEventListener('focusin',stop);
  root.addEventListener('focusout',function(){setTimeout(function(){if(!root.contains(document.activeElement))start();},0);});
  document.addEventListener('visibilitychange',function(){if(document.hidden){stop();instStop();}else start();});
  if('IntersectionObserver' in window){new IntersectionObserver(function(entries){heroVisible=!!(entries[0]&&entries[0].isIntersecting);if(heroVisible)start();else{stop();instStop();}},{threshold:.15}).observe(root);}else{start();}

  var dialog=root.querySelector('[data-sth-dialog]');
  var frame=root.querySelector('[data-sth-video-frame]');
  var opener=root.querySelector('[data-sth-video-open]');
  if(dialog&&frame&&opener){
    var closeDialog=function(){
      if(dialog.open)dialog.close();else dialog.removeAttribute('open');
      dialog.classList.remove('is-fallback');
      document.documentElement.classList.remove('sth-video-open');
      frame.innerHTML='';
      dialogOpen=false;
      start();
    };
    opener.addEventListener('click',function(){
      var embed=opener.getAttribute('data-sth-video-embed')||'';
      if(!embed){return;}
      if(!frame.querySelector('iframe')){var ifr=document.createElement('iframe');ifr.src=embed;ifr.title=opener.textContent.trim();ifr.allow='autoplay; encrypted-media; picture-in-picture';ifr.allowFullscreen=true;frame.appendChild(ifr);}
      dialogOpen=true;
      stop();
      instStop();
      document.documentElement.classList.add('sth-video-open');
      if(typeof dialog.showModal==='function'){dialog.showModal();var close=dialog.querySelector('[data-sth-video-close]');if(close)close.focus();}else{dialog.setAttribute('open','');dialog.classList.add('is-fallback');}
    });
    var closer=dialog.querySelector('[data-sth-video-close]');
    if(closer)closer.addEventListener('click',closeDialog);
    dialog.addEventListener('click',function(e){if(e.target===dialog)closeDialog();});
    dialog.addEventListener('cancel',function(e){e.preventDefault();closeDialog();});
    dialog.addEventListener('close',function(){document.documentElement.classList.remove('sth-video-open');frame.innerHTML='';dialogOpen=false;start();});
  }
  window.addEventListener('pagehide',function(){stop();instStop();instManualStop();});

  var initial=0;
  try{
    var qp=new URLSearchParams(location.search).get('slide');
    var hm=location.hash&&location.hash.match(/^#slide-(\d+)$/);
    var v=qp?parseInt(qp,10):(hm?parseInt(hm[1],10):0);
    if(v>0&&v<=n)initial=v-1;
  }catch(e){}
  show(initial,{initial:true,manual:false});
  syncPause();
  start();
  }
  function boot(){
    if(!document.querySelector('[data-sth-hero]')){
      if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});
      return;
    }
    init();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',boot,{once:true});else setTimeout(boot,0);
})();
</script>
HTML;
    }

    private function backgroundSlide(array $s, int $i): string
    {
        $d = (array)($s['design'] ?? []);
        $bg = (string)($d['bg'] ?? '#111111');
        if (!preg_match('/^#[0-9a-f]{3,8}$/i', $bg)) $bg = '#111111';
        $height = 'var(--sh-h,391px)';
        $padt = max(0, min(160, (int)($d['pad_top'] ?? 0)));
        $padx = max(0, min(80, (int)($d['pad_x'] ?? 22)));
        $va = (string)($d['content_valign'] ?? ($d['valign'] ?? 'center'));
        $va = in_array($va, ['start', 'center', 'end'], true) ? $va : 'center';
        $cvalign = ['start' => 'flex-start', 'center' => 'center', 'end' => 'flex-end'][$va];
        $ca = (string)($d['content_align'] ?? 'left');
        $ca = in_array($ca, ['left', 'center', 'right'], true) ? $ca : 'left';
        $cjustify = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'][$ca];
        $cmax = max(320, min(900, (int)($d['content_max'] ?? 560)));
        $mobileCustom = (string)($d['mobile_mode'] ?? 'inherit') === 'custom';
        $mobileClasses = '';
        if ($mobileCustom) {
            $mobileClasses .= ' sh-mobile--custom';
            $mva = (string)($d['mobile_valign'] ?? 'center');
            if (in_array($mva, ['start', 'center', 'end'], true)) $mobileClasses .= ' sh-mobile--v-' . $mva;
            $mal = (string)($d['mobile_align'] ?? 'left');
            if (in_array($mal, ['left', 'center', 'right'], true)) $mobileClasses .= ' sh-mobile--a-' . $mal;
            foreach ([
                'mobile_show_eyebrow' => 'eyebrow',
                'mobile_show_title' => 'title',
                'mobile_show_subtitle' => 'subtitle',
                'mobile_show_text' => 'text',
                'mobile_show_cta' => 'cta',
                'mobile_show_cta2' => 'cta2',
            ] as $key => $suffix) {
                if ((string)($d[$key] ?? '1') === '0') $mobileClasses .= ' sh-mobile--hide-' . $suffix;
            }
            if ((string)($d['mobile_cta_full'] ?? '0') === '1') $mobileClasses .= ' sh-mobile--cta-full';
            $visible = false;
            foreach (['mobile_show_eyebrow', 'mobile_show_title', 'mobile_show_subtitle', 'mobile_show_text', 'mobile_show_cta', 'mobile_show_cta2'] as $key) {
                if ((string)($d[$key] ?? '1') !== '0') { $visible = true; break; }
            }
            if (!$visible) $mobileClasses .= ' sh-mobile--hide-content';
        }
        $type = (string)($d['bg_type'] ?? 'image');
        if (!in_array($type, ['image', 'video', 'youtube'], true)) $type = 'image';
        $image = $this->mediaUrl((string)($d['bg_image'] ?? ''));
        $mobileImage = $this->mediaUrl((string)($d['bg_image_mobile'] ?? ''));
        if ($image === '' && $mobileImage !== '') $image = $mobileImage;
        $video = $this->mediaUrl((string)($d['bg_video'] ?? ''));
        $poster = $this->mediaUrl((string)($d['bg_poster'] ?? ''));
        $yt = $this->youtubeId((string)($d['bg_yt'] ?? ''));
        $alt = (string)($d['bg_alt'] ?? '');
        $x = max(0, min(100, (int)($d['bg_x'] ?? 50)));
        $y = max(0, min(100, (int)($d['bg_y'] ?? 50)));
        $position = $x . '% ' . $y . '%';
        $overlay = max(0, min(80, (int)($d['bg_overlay'] ?? 45)));
        $ostyle = (string)($d['bg_overlay_style'] ?? 'dark');
        if (!in_array($ostyle, ['dark', 'left', 'bottom', 'none'], true)) $ostyle = 'dark';
        $alpha = $overlay / 100;
        $overlayCss = 'transparent';
        if ($ostyle === 'dark') $overlayCss = 'linear-gradient(180deg,rgba(0,0,0,' . round($alpha * .45, 3) . ') 0%,rgba(0,0,0,' . round($alpha, 3) . ') 100%)';
        elseif ($ostyle === 'left') $overlayCss = 'linear-gradient(90deg,rgba(0,0,0,' . round($alpha, 3) . ') 0%,rgba(0,0,0,' . round($alpha * .55, 3) . ') 55%,rgba(0,0,0,0) 100%)';
        elseif ($ostyle === 'bottom') $overlayCss = 'linear-gradient(0deg,rgba(0,0,0,' . round($alpha, 3) . ') 0%,rgba(0,0,0,' . round($alpha * .35, 3) . ') 58%,rgba(0,0,0,0) 100%)';

        $content = (string)($s['left_type'] ?? 'text') === 'text' ? (array)($s['left_data'] ?? []) : (array)($s['right_data'] ?? []);
        if (!$content) $content = (array)($s['left_data'] ?? []);

        $h = '<article class="sh-slide sh-slide--bg sh-bg--' . $ca . $mobileClasses . ($i === 0 ? ' is-on' : '') . '" data-i="' . $i . '" aria-hidden="' . ($i === 0 ? 'false' : 'true') . '" style="--sh-bg:' . $bg . ';--sh-cvalign:' . $cvalign . ';--sh-cjustify:' . $cjustify . ';--sh-cmax:' . $cmax . 'px;--sh-padt:' . $padt . 'px;--sh-padx:' . $padx . 'px">';
        $h .= '<div class="sh-bg" aria-hidden="true">';
        if ($poster !== '') $h .= '<img class="sh-bg__poster" src="' . $this->esc($poster) . '" alt="" loading="eager" decoding="async" style="object-position:' . $position . '"/>';
        if ($type === 'image' && $image !== '') {
            if ($mobileImage !== '' && $mobileImage !== $image) {
                $h .= '<picture class="sh-bg__picture"><source media="(max-width: 820px)" srcset="' . $this->esc($mobileImage) . '"/><img class="sh-bg__img" src="' . $this->esc($image) . '" alt="' . $this->esc($alt) . '" loading="eager" decoding="async" fetchpriority="high" style="object-position:' . $position . '"/></picture>';
            } else {
                $h .= '<img class="sh-bg__img" src="' . $this->esc($image) . '" alt="' . $this->esc($alt) . '" loading="eager" decoding="async" fetchpriority="high" style="object-position:' . $position . '"/>';
            }
        } elseif ($type === 'video' && $video !== '') {
            $h .= '<video class="sh-bg__video" autoplay muted loop playsinline preload="metadata"' . ($poster !== '' ? ' poster="' . $this->esc($poster) . '"' : '') . '><source src="' . $this->esc($video) . '" type="video/mp4"></video>';
        } elseif ($type === 'youtube' && $yt !== '') {
            $qs = http_build_query(['autoplay' => 1, 'mute' => 1, 'controls' => 0, 'loop' => 1, 'playlist' => $yt, 'playsinline' => 1, 'rel' => 0, 'modestbranding' => 1, 'iv_load_policy' => 3, 'disablekb' => 1, 'fs' => 0]);
            $h .= '<iframe class="sh-bg__video sh-bg__iframe" src="https://www.youtube-nocookie.com/embed/' . $this->esc($yt) . '?' . $this->esc($qs) . '" title="Video de fondo" tabindex="-1" loading="lazy" allow="autoplay; encrypted-media; picture-in-picture"></iframe>';
        }
        $h .= '</div>';
        if ($ostyle !== 'none' || $overlay > 0) $h .= '<span class="sh-bg__ov" style="background:' . $overlayCss . '"></span>';
        $h .= '<div class="sh-bg__content"><div class="sh-bg__inner">' . $this->block('text', $content) . '</div></div>';
        return $h . '</article>';
    }

    private function mediaUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if (str_starts_with($url, '/') || preg_match('~^https?://~i', $url)) return $url;
        return '';
    }

    private function linkUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';
        if ($url[0] === '/' && !str_starts_with($url, '//')) return $url;
        if (str_starts_with($url, '#')) return $url;
        if (preg_match('~^https?://~i', $url)) return $url;
        if (preg_match('~^(mailto|tel):~i', $url)) return $url;
        return '';
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
            case 'none':
                return '';
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
.sh{position:relative;background:var(--sh-bg,#111);color:#fff;overflow:hidden;height:var(--sh-h,391px)}
.sh-track{position:relative;height:100%}
.sh-slide{position:absolute;inset:0;opacity:0;visibility:hidden;transition:opacity .4s ease;background:var(--sh-bg,#111)}
.sh-slide.is-on{opacity:1;visibility:visible;position:relative}
.sh-slide--bg{background:var(--sh-bg,#111);height:100%}
.sh-bg{position:absolute;inset:0;z-index:0;overflow:hidden;background:var(--sh-bg,#111)}
.sh-bg img,.sh-bg video,.sh-bg iframe{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border:0;display:block}
.sh-bg__picture{position:absolute;inset:0;display:block;width:100%;height:100%;z-index:1}
.sh-bg__picture img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover}
.sh-bg__poster{z-index:0}
.sh-bg__img,.sh-bg__video{z-index:1}
.sh-bg__iframe{pointer-events:none}
.sh-bg__ov{position:absolute;inset:0;z-index:1;pointer-events:none}
.sh-bg__content{position:relative;z-index:2;height:100%;max-width:1180px;margin:0 auto;padding:var(--sh-padt,0px) var(--sh-padx,22px);display:flex;align-items:var(--sh-cvalign,center);justify-content:var(--sh-cjustify,flex-start)}
.sh-bg__inner{width:100%;max-width:var(--sh-cmax,560px)}
.sh-bg__inner .sh-copy{max-width:none}
.sh-bg--center .sh-copy{align-items:center;text-align:center}
.sh-bg--center .sh-ctas{justify-content:center}
.sh-bg--right .sh-copy{align-items:flex-end;text-align:right}
.sh-bg--right .sh-ctas{justify-content:flex-end}
.sh-grid{height:100%;max-width:1180px;margin:0 auto;padding:var(--sh-padt,0px) var(--sh-padx,22px);display:grid;grid-template-columns:var(--sh-cols,1fr 1fr);gap:24px;align-items:stretch}
.sh-zone{min-width:0;display:flex;flex-direction:column;justify-content:var(--sh-valign,center)}
.sh-copy{display:flex;flex-direction:column;gap:10px;max-width:560px}
.sh-eyebrow{font-size:12px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:#ffd9db}
.sh-h1{font-family:var(--font-display,Sora),sans-serif;font-size:clamp(22px,2.9vw,39px);font-weight:800;line-height:1.05;letter-spacing:-.02em;white-space:pre-line}
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
@media(max-width:820px){.sh{height:auto}.sh-slide.is-on{position:relative}.sh-slide--bg{height:auto;min-height:442px}.sh-bg__content{height:auto;min-height:442px;box-sizing:border-box;padding:24px 19px}.sh-bg__inner{max-width:100%!important}.sh-grid{grid-template-columns:1fr;padding:22px;gap:16px}.sh{--sh-h:auto}.sh-zone--bleed{margin:0}.sh-video--fill,.sh-video--center{height:auto}.sh-video--fill .sh-video__box{height:auto;min-height:0;aspect-ratio:16/9;border-radius:16px}.sh-video--center .sh-video__box{width:100%;max-width:none;aspect-ratio:16/9}.sh-video--fill .sh-video__box,.sh-video--center .sh-video__box{-webkit-mask-image:none;mask-image:none}.sh-mobile--v-start .sh-bg__content{align-items:flex-start;padding-top:max(30px,var(--sh-padt,0px))}.sh-mobile--v-center .sh-bg__content{align-items:center}.sh-mobile--v-end .sh-bg__content{align-items:flex-end;padding-bottom:30px}.sh-mobile--a-left .sh-bg__content{justify-content:flex-start}.sh-mobile--a-center .sh-bg__content{justify-content:center}.sh-mobile--a-right .sh-bg__content{justify-content:flex-end}.sh-mobile--a-center .sh-copy{text-align:center;align-items:center}.sh-mobile--a-center .sh-ctas{justify-content:center}.sh-mobile--a-right .sh-copy{text-align:right;align-items:flex-end}.sh-mobile--a-right .sh-ctas{justify-content:flex-end}.sh-mobile--hide-eyebrow .sh-eyebrow,.sh-mobile--hide-title .sh-h1,.sh-mobile--hide-subtitle .sh-sub,.sh-mobile--hide-text .sh-text,.sh-mobile--hide-cta .sh-btn--p,.sh-mobile--hide-cta2 .sh-btn--s{display:none!important}.sh-mobile--hide-content .sh-bg__content{display:none!important}.sh-mobile--cta-full .sh-ctas{flex-direction:column;align-items:stretch}.sh-mobile--cta-full .sh-btn{width:100%;justify-content:center}}
@media(prefers-reduced-motion:reduce){.sh-slide{transition:none}.sh-bg video,.sh-bg iframe{display:none}}
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
