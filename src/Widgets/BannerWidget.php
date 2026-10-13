<?php
namespace App\Widgets;

use App\Core\Database;

/**
 * BannerWidget — brick "Banner / Barra de avisos" (categoría header).
 * Reutiliza lo mejor del módulo de IA Annotation y lo adapta a WWI:
 * múltiples avisos con rotación, posiciones, colores/degradado, tamaño relativo,
 * animaciones de entrada, efectos de letras, frecuencia (localStorage),
 * descartable, CTA, programación (días/horas/fechas + zona horaria) y
 * segmentación web (dispositivo/audiencia/páginas). Multi-tenant.
 */
class BannerWidget extends Widget
{
    private static bool $assetsPrinted = false;

    public const MODES = ['bar', 'fixed-top', 'fixed-bottom', 'modal', 'toast'];
    public const ANIMS = ['none', 'slide-down', 'slide-up', 'slide-left', 'slide-right', 'fade', 'zoom', 'bounce'];
    public const FREQS = ['once', 'three', 'session', 'always'];
    public const LETTERS = ['none', 'highlight', 'gradient', 'typewriter'];
    public const DEVICES = ['all', 'desktop', 'mobile'];

    public static function meta(): array
    {
        return ['id' => 'banner', 'name' => 'Banner / Aviso', 'icon' => 'megaphone', 'category' => 'header', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => '_h_mode', 'label' => 'Posición y comportamiento', 'type' => 'heading'],
            ['key' => 'mode', 'label' => 'Modo', 'type' => 'select', 'options' => ['bar' => 'Barra en su lugar', 'fixed-top' => 'Fija arriba', 'fixed-bottom' => 'Fija abajo', 'modal' => 'Modal (popup)'], 'default' => 'bar'],
            ['key' => 'animation', 'label' => 'Animación de aparición', 'type' => 'select', 'options' => array_combine(self::ANIMS, self::ANIMS), 'default' => 'slide-down'],
            ['key' => 'rotation_ms', 'label' => 'Rotación entre avisos (ms · 0 = sin rotar)', 'type' => 'number', 'default' => 5000],
            ['key' => 'letters', 'label' => 'Efecto en las letras', 'type' => 'select', 'options' => ['none' => 'Ninguno', 'highlight' => 'Resaltar (==palabra==)', 'gradient' => 'Degradado animado', 'typewriter' => 'Máquina de escribir'], 'default' => 'none'],

            ['key' => '_h_items', 'label' => 'Avisos', 'type' => 'heading'],
            ['key' => 'items', 'label' => 'Mensajes (usa **negrita**)', 'type' => 'repeater', 'fields' => [
                ['key' => 'icon', 'label' => 'Icono (emoji)', 'type' => 'text'],
                ['key' => 'text', 'label' => 'Texto', 'type' => 'text'],
                ['key' => 'text_b', 'label' => 'Texto variante B (A/B, opcional)', 'type' => 'text'],
                ['key' => 'image', 'label' => 'Imagen (opcional)', 'type' => 'image'],
                ['key' => 'link', 'label' => 'Enlace (CTA, opcional)', 'type' => 'url'],
                ['key' => 'link_label', 'label' => 'Texto del enlace', 'type' => 'text'],
            ], 'default' => [
                ['icon' => '🚚', 'text' => 'Envíos a toda Colombia', 'link' => '', 'link_label' => ''],
                ['icon' => '🛡️', 'text' => 'Compra 100% segura', 'link' => '', 'link_label' => ''],
                ['icon' => '✅', 'text' => 'Garantía oficial', 'link' => '', 'link_label' => ''],
            ]],

            ['key' => '_h_style', 'label' => 'Estilo', 'type' => 'heading'],
            ['key' => 'preset', 'label' => 'Preset de color (1 clic)', 'type' => 'select', 'options' => ['none' => 'Personalizado', 'info' => 'Info (azul)', 'success' => 'Éxito (verde)', 'warning' => 'Aviso (ámbar)', 'error' => 'Error (rojo)', 'promo' => 'Promo (degradado)', 'dark' => 'Oscuro'], 'default' => 'none'],
            ['key' => 'bg_color', 'label' => 'Fondo', 'type' => 'color', 'default' => '#111111'],
            ['key' => 'bg_color2', 'label' => 'Fondo 2 (degradado, opcional)', 'type' => 'color', 'default' => ''],
            ['key' => 'text_color', 'label' => 'Color de letra', 'type' => 'color', 'default' => '#ffffff'],
            ['key' => 'font_size', 'label' => 'Tamaño de letra (0.6–2.0 rem)', 'type' => 'number', 'default' => '0.8'],
            ['key' => 'bold', 'label' => 'Negrita', 'type' => 'toggle', 'default' => true],
            ['key' => 'italic', 'label' => 'Itálica', 'type' => 'toggle', 'default' => false],
            ['key' => 'align', 'label' => 'Alineación', 'type' => 'select', 'options' => ['center' => 'Centro', 'left' => 'Izquierda', 'right' => 'Derecha'], 'default' => 'center'],
            ['key' => 'bg_image', 'label' => 'Imagen de fondo (opcional)', 'type' => 'image', 'default' => ''],
            ['key' => 'overlay', 'label' => 'Velo sobre la imagen (0-100)', 'type' => 'number', 'default' => '55'],
            ['key' => 'bare', 'label' => 'Sin fondo propio (para incrustar en una barra existente)', 'type' => 'toggle', 'default' => false],

            ['key' => '_h_behavior', 'label' => 'Comportamiento', 'type' => 'heading'],
            ['key' => 'dismissible', 'label' => 'Descartable (✕)', 'type' => 'toggle', 'default' => true],
            ['key' => 'close_label', 'label' => 'Etiqueta de cierre (modal)', 'type' => 'text', 'default' => 'Entendido'],
            ['key' => 'frequency', 'label' => 'Frecuencia', 'type' => 'select', 'options' => ['always' => 'Siempre', 'once' => 'Una vez', 'three' => '3 veces', 'session' => 'Una por sesión'], 'default' => 'always'],
            ['key' => 'marquee', 'label' => 'Marquesina (texto en movimiento)', 'type' => 'toggle', 'default' => false],
            ['key' => 'critical', 'label' => 'Crítico (no descartable, prioridad alta)', 'type' => 'toggle', 'default' => false],
            ['key' => 'countdown_to', 'label' => 'Cuenta atrás hasta (YYYY-MM-DDTHH:MM, opcional)', 'type' => 'text', 'default' => ''],
            ['key' => 'exit_intent', 'label' => 'Mostrar al intentar salir (solo modal)', 'type' => 'toggle', 'default' => false],
            ['key' => 'track', 'label' => 'Medir impresiones y clics', 'type' => 'toggle', 'default' => true],
            ['key' => 'ab', 'label' => 'Probar A/B (usa la variante B del texto)', 'type' => 'toggle', 'default' => false],

            ['key' => '_h_target', 'label' => 'Segmentación y programación', 'type' => 'heading'],
            ['key' => 'device', 'label' => 'Dispositivo', 'type' => 'select', 'options' => ['all' => 'Todos', 'desktop' => 'Solo escritorio', 'mobile' => 'Solo móvil'], 'default' => 'all'],
            ['key' => 'audience', 'label' => 'Audiencia', 'type' => 'select', 'options' => ['all' => 'Todos', 'guest' => 'Solo invitados', 'logged' => 'Solo registrados'], 'default' => 'all'],
            ['key' => 'pages', 'label' => 'Solo en páginas (slugs separados por coma; vacío = todas)', 'type' => 'text', 'default' => ''],
            ['key' => 'days', 'label' => 'Días (0=Dom … 6=Sáb; vacío = todos)', 'type' => 'text', 'default' => ''],
            ['key' => 'from', 'label' => 'Hora desde (HH:MM)', 'type' => 'text', 'default' => ''],
            ['key' => 'to', 'label' => 'Hora hasta (HH:MM)', 'type' => 'text', 'default' => ''],
            ['key' => 'start_at', 'label' => 'Fecha inicio (YYYY-MM-DD)', 'type' => 'text', 'default' => ''],
            ['key' => 'end_at', 'label' => 'Fecha fin (YYYY-MM-DD)', 'type' => 'text', 'default' => ''],
            ['key' => 'tz_offset', 'label' => 'Zona horaria (horas UTC, ej. -5)', 'type' => 'number', 'default' => '-5'],
        ];
    }

    public static function defaultConfig(): array
    {
        return [
            'mode' => 'bar', 'animation' => 'slide-down', 'rotation_ms' => 5000, 'letters' => 'none',
            'items' => [
                ['icon' => '🚚', 'text' => 'Envíos a toda Colombia', 'link' => '', 'link_label' => ''],
            ],
            'bg_color' => '#111111', 'bg_color2' => '', 'text_color' => '#ffffff', 'font_size' => '0.8', 'preset' => 'none',
            'bold' => true, 'italic' => false, 'align' => 'center', 'bg_image' => '', 'overlay' => '55', 'bare' => false,
            'dismissible' => true, 'close_label' => 'Entendido', 'frequency' => 'always',
            'marquee' => false, 'critical' => false, 'countdown_to' => '', 'exit_intent' => false, 'track' => true, 'ab' => false,
            'device' => 'all', 'audience' => 'all', 'pages' => '', 'days' => '', 'from' => '', 'to' => '',
            'start_at' => '', 'end_at' => '', 'tz_offset' => '-5',
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="padding:18px;border:1px dashed #ccc;border-radius:8px;color:#666">📣 Banner / Barra de avisos: múltiples mensajes con rotación, colores, animación, efectos de letras y programación.</div>';
    }

    private function color($v, string $def): string
    {
        $s = strtolower(trim((string)$v));
        return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/', $s) ? $s : $def;
    }

    private function inSchedule(array $c): bool
    {
        $tz = (int)($c['tz_offset'] ?? -5);
        $now = time() + $tz * 3600;
        $day = (int)date('w', $now);
        $hhmm = date('H:i', $now);
        $date = date('Y-m-d', $now);

        $daysRaw = trim((string)($c['days'] ?? ''));
        $days = [];
        if ($daysRaw !== '') {
            $days = array_values(array_filter(array_map('intval', preg_split('/[^0-9]+/', $daysRaw)), fn($d) => $d >= 0 && $d <= 6));
        }
        if ($days && !in_array($day, $days, true)) return false;

        $start = trim((string)($c['start_at'] ?? ''));
        $end = trim((string)($c['end_at'] ?? ''));
        if ($start !== '' && $date < $start) return false;
        if ($end !== '' && $date > $end) return false;

        $from = preg_match('/^\d{2}:\d{2}$/', (string)($c['from'] ?? '')) ? (string)$c['from'] : '';
        $to = preg_match('/^\d{2}:\d{2}$/', (string)($c['to'] ?? '')) ? (string)$c['to'] : '';
        if ($from !== '' && $to !== '') {
            if ($from <= $to) { if ($hhmm < $from || $hhmm > $to) return false; }
            else { if ($hhmm < $from && $hhmm > $to) return false; }
        } elseif ($from !== '') { if ($hhmm < $from) return false; }
        elseif ($to !== '') { if ($hhmm > $to) return false; }
        return true;
    }

    private function isMobile(): bool
    {
        return (bool)preg_match('/Mobile|Android|iPhone|iPad|iPod|Opera Mini|IEMobile/i', (string)($_SERVER['HTTP_USER_AGENT'] ?? ''));
    }

    private function matchDevice(string $dev): bool
    {
        $m = $this->isMobile();
        if ($dev === 'mobile') return $m;
        if ($dev === 'desktop') return !$m;
        return true;
    }

    private function matchAudience(string $aud): bool
    {
        if ($aud === 'all') return true;
        $logged = false;
        try {
            $logged = (new \App\Services\CustomerService())->current() !== null;
        } catch (\Throwable $e) {
        }
        return $aud === 'logged' ? $logged : !$logged;
    }

    private function matchPages(string $csv): bool
    {
        $csv = trim($csv);
        if ($csv === '') return true;
        $path = trim((string)parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');
        $seg = explode('/', $path)[0] ?? '';
        $allowed = array_filter(array_map('trim', explode(',', strtolower($csv))));
        return in_array(strtolower($seg), $allowed, true) || in_array(strtolower($path), $allowed, true);
    }

    private function readText(string $text): string
    {
        $t = $this->esc($text);
        $t = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $t);
        $t = preg_replace('/==(.+?)==/s', '<span class="wwi-bn__hl">$1</span>', $t);
        return $t;
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        if (!$this->inSchedule($c)) return '';
        if (!$this->matchDevice((string)($c['device'] ?? 'all'))) return '';
        if (!$this->matchAudience((string)($c['audience'] ?? 'all'))) return '';
        if (!$this->matchPages((string)($c['pages'] ?? ''))) return '';

        $mode = in_array((string)$c['mode'], self::MODES, true) ? (string)$c['mode'] : 'bar';
        $anim = in_array((string)$c['animation'], self::ANIMS, true) ? (string)$c['animation'] : 'slide-down';
        $freq = in_array((string)$c['frequency'], self::FREQS, true) ? (string)$c['frequency'] : 'always';
        $letters = in_array((string)$c['letters'], self::LETTERS, true) ? (string)$c['letters'] : 'none';

        $items = [];
        foreach ((array)($c['items'] ?? []) as $it) {
            if (!is_array($it)) continue;
            $txt = trim((string)($it['text'] ?? ''));
            if ($txt === '') continue;
            $items[] = [
                'icon' => trim((string)($it['icon'] ?? '')),
                'text' => $txt,
                'text_b' => trim((string)($it['text_b'] ?? '')),
                'image' => trim((string)($it['image'] ?? '')),
                'link' => trim((string)($it['link'] ?? '')),
                'link_label' => trim((string)($it['link_label'] ?? '')),
            ];
        }
        if (!$items) return '';

        $presets = ['info' => ['#1D4ED8', ''], 'success' => ['#047857', ''], 'warning' => ['#B45309', ''], 'error' => ['#B91C1C', ''], 'promo' => ['#4F46E5', '#6366F1'], 'dark' => ['#111111', '']];
        $preset = (string)($c['preset'] ?? 'none');
        if (isset($presets[$preset])) {
            $c['bg_color'] = $presets[$preset][0];
            $c['bg_color2'] = $presets[$preset][1];
            $c['text_color'] = '#ffffff';
        }
        $marquee = !empty($c['marquee']);
        $critical = !empty($c['critical']);
        $countdown = trim((string)($c['countdown_to'] ?? ''));
        if ($countdown !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2})?$/', $countdown)) $countdown = '';
        $exit = !empty($c['exit_intent']);
        $track = !empty($c['track']);
        $ab = !empty($c['ab']);
        $overlay = max(0, min(100, (int)($c['overlay'] ?? 55)));

        $bg = $this->color($c['bg_color'] ?? '', '#111111');
        $bg2 = $this->color($c['bg_color2'] ?? '', '');
        $fg = $this->color($c['text_color'] ?? '', '#ffffff');
        $fs = max(0.6, min(2.0, (float)($c['font_size'] ?? 0.8)));
        $pad = round($fs * 0.9, 2) . 'rem';
        $bold = !empty($c['bold']);
        $italic = !empty($c['italic']);
        $align = in_array((string)($c['align'] ?? 'center'), ['left', 'center', 'right'], true) ? (string)$c['align'] : 'center';
        $rot = max(0, (int)($c['rotation_ms'] ?? 0));
        $dismissible = !empty($c['dismissible']) && !$critical;
        $closeLabel = trim((string)($c['close_label'] ?? '')) ?: 'Entendido';
        $bgImg = trim((string)($c['bg_image'] ?? ''));
        if ($bgImg !== '' && !preg_match('~^(https?://|/)~i', $bgImg)) $bgImg = '';
        $bare = !empty($c['bare']);

        $uid = 'bn' . substr(md5($mode . implode('|', array_column($items, 'text')) . $freq), 0, 6);

        $style = '--wwi-bn-bg:' . $bg . ';--wwi-bn-fg:' . $fg . ';--wwi-bn-fs:' . $fs . 'rem;--wwi-bn-pad:' . $pad . ';--wwi-bn-align:' . $align . ';--wwi-bn-fw:' . ($bold ? '800' : '500') . ';--wwi-bn-fst:' . ($italic ? 'italic' : 'normal');
        if ($bg2 !== '') $style .= ';--wwi-bn-bg2:' . $bg2;
        $style .= ';--wwi-bn-ov:' . ($overlay / 100);
        if ($bgImg !== '') $style .= ';--wwi-bn-img:url(\'' . $this->esc($bgImg) . '\')';

        $h = $this->assets();
        $h .= '<div class="wwi-bn wwi-bn--' . $mode . ' wwi-bn--anim-' . $anim . ($bare ? ' wwi-bn--bare' : '') . ($bgImg !== '' ? ' has-img' : '') . ($bg2 !== '' ? ' has-grad' : '') . ($marquee ? ' wwi-bn--marquee' : '') . ($critical ? ' wwi-bn--critical' : '') . (($mode === 'modal' && $exit) ? ' wwi-bn--exit' : '') . ($ab ? ' wwi-bn--ab' : '') . '" id="' . $uid . '" style="' . $style . '"'
            . ' data-rot="' . $rot . '" data-freq="' . $freq . '" data-letters="' . $letters . '" data-session="1"' . ($countdown !== '' ? ' data-cd="' . $this->esc($countdown) . '"' : '') . ($track ? ' data-uid="' . $uid . '" data-track="1"' : '')
            . ' role="' . ($critical ? 'alert' : 'status') . '" aria-live="' . ($critical ? 'assertive' : 'polite') . '">';
        if ($mode === 'modal') {
            $h .= '<div class="wwi-bn__overlay" data-bn-close></div><div class="wwi-bn__box" role="dialog" aria-modal="true">';
        }
        $h .= '<div class="wwi-bn__track">';
        foreach ($items as $i => $it) {
            $h .= '<span class="wwi-bn__item' . ($i === 0 ? ' is-on' : '') . '" data-i="' . $i . '">';
            if ($it['icon'] !== '') $h .= '<span class="wwi-bn__ic" aria-hidden="true">' . $this->esc($it['icon']) . '</span>';
            if ((string)$it['image'] !== '' && preg_match('~^(https?://|/)~i', (string)$it['image'])) {
                $h .= '<img class="wwi-bn__im" src="' . $this->esc($it['image']) . '" alt="" loading="lazy"/>';
            }
            if ($ab && (string)$it['text_b'] !== '') {
                $h .= '<span class="wwi-bn__tx wwi-bn__vA">' . $this->readText($it['text']) . '</span><span class="wwi-bn__tx wwi-bn__vB">' . $this->readText($it['text_b']) . '</span>';
            } else {
                $h .= '<span class="wwi-bn__tx">' . $this->readText($it['text']) . '</span>';
            }
            if ($it['link'] !== '') {
                $lbl = $it['link_label'] !== '' ? $it['link_label'] : $it['link'];
                $h .= ' <a class="wwi-bn__cta" href="' . $this->esc($it['link']) . '" rel="noopener">' . $this->esc($lbl) . '</a>';
            }
            $h .= '</span>';
        }
        $h .= '</div>';
        if ($countdown !== '') $h .= '<span class="wwi-bn__cd">⏳</span>';
        if (count($items) > 1 && $rot > 0) {
            $h .= '<span class="wwi-bn__dots" aria-hidden="true">';
            foreach ($items as $i => $it) $h .= '<button type="button" class="wwi-bn__dot' . ($i === 0 ? ' is-on' : '') . '" data-go="' . $i . '"></button>';
            $h .= '</span>';
        }
        if ($dismissible) {
            if ($mode === 'modal') $h .= '<button type="button" class="wwi-bn__ok" data-bn-close>' . $this->esc($closeLabel) . '</button>';
            else $h .= '<button type="button" class="wwi-bn__x" data-bn-close aria-label="Cerrar">✕</button>';
        }
        if ($mode === 'modal') $h .= '</div>';
        $h .= '</div>';
        return $h;
    }

    private function assets(): string
    {
        if (self::$assetsPrinted) return '';
        self::$assetsPrinted = true;
        return <<<'HTML'
<style>
.wwi-bn{position:relative;display:block;background:var(--wwi-bn-bg);color:var(--wwi-bn-fg);font-size:var(--wwi-bn-fs);font-weight:var(--wwi-bn-fw);font-style:var(--wwi-bn-fst);padding:var(--wwi-bn-pad) 2.6rem;text-align:var(--wwi-bn-align);line-height:1.45;letter-spacing:.01em}
.wwi-bn.has-grad{background:linear-gradient(135deg,var(--wwi-bn-bg),var(--wwi-bn-bg2))}
.wwi-bn.has-img{background-image:var(--wwi-bn-img);background-size:cover;background-position:center}
.wwi-bn--fixed-top{position:fixed;top:0;left:0;right:0;z-index:200;box-shadow:0 4px 18px rgba(0,0,0,.25)}
.wwi-bn--fixed-bottom{position:fixed;bottom:0;left:0;right:0;z-index:200;box-shadow:0 -4px 18px rgba(0,0,0,.25)}
.wwi-bn--bare{background:none!important;padding:0;display:inline-block;box-shadow:none}
.wwi-bn--bare .wwi-bn__x{display:none}
.wwi-bn__track{position:relative;display:inline-block;max-width:1100px}
.wwi-bn__item{display:none;align-items:center;gap:.5em;white-space:pre-wrap}
.wwi-bn__item.is-on{display:inline-flex;flex-wrap:wrap;justify-content:center}
.wwi-bn__hl{color:var(--brand,#ffd166);font-weight:800}
.wwi-bn__cta{color:inherit;text-decoration:underline;font-weight:800}
.wwi-bn__x{position:absolute;top:50%;right:.6rem;transform:translateY(-50%);background:rgba(255,255,255,.16);border:0;color:inherit;border-radius:6px;padding:.15em .6em;font:inherit;font-size:.85em;cursor:pointer;line-height:1}
.wwi-bn__dots{display:inline-flex;gap:5px;margin-left:10px;vertical-align:middle}
.wwi-bn__dot{width:7px;height:7px;border-radius:50%;border:0;background:currentColor;opacity:.35;cursor:pointer;padding:0}
.wwi-bn__dot.is-on{opacity:1}
.wwi-bn--modal{position:fixed;inset:0;z-index:230;background:none;padding:0}
.wwi-bn__overlay{position:absolute;inset:0;background:rgba(2,6,23,.62)}
.wwi-bn__box{position:relative;width:min(92vw,440px);min-height:min(60vh,380px);margin:auto;top:50%;transform:translateY(-50%);border-radius:16px;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1em;box-shadow:0 24px 60px rgba(0,0,0,.5);background:var(--wwi-bn-bg);color:var(--wwi-bn-fg);font-size:calc(var(--wwi-bn-fs) * 1.1);text-align:center;padding:2rem 1.4rem}
.wwi-bn--modal.has-grad .wwi-bn__box{background:linear-gradient(135deg,var(--wwi-bn-bg),var(--wwi-bn-bg2))}
.wwi-bn__ok{background:rgba(255,255,255,.22);border:0;color:inherit;border-radius:8px;padding:.5em 1.4em;font:inherit;font-weight:700;cursor:pointer}
.wwi-bn__cd{display:inline-block;margin-left:.6em;font-weight:800;font-variant-numeric:tabular-nums;letter-spacing:.03em}
.wwi-bn--marquee{overflow:hidden}
.wwi-bn--marquee .wwi-bn__item.is-on{display:inline-block!important;white-space:nowrap;animation:wwiBnMarquee 16s linear infinite}
@keyframes wwiBnMarquee{from{transform:translateX(100%)}to{transform:translateX(-100%)}}
.wwi-bn--critical{box-shadow:inset 0 0 0 2px rgba(255,255,255,.28)}
.wwi-bn--critical .wwi-bn__ic{animation:wwiBnPulse 1.4s ease-in-out infinite}
@keyframes wwiBnPulse{0%,100%{opacity:1}50%{opacity:.45}}
.wwi-bn--exit{display:none}
.wwi-bn--exit.is-open{display:block}
.wwi-bn--ab .wwi-bn__vB{display:none}
.wwi-bn--ab.ab-b .wwi-bn__vA{display:none}
.wwi-bn--ab.ab-b .wwi-bn__vB{display:inline}
.wwi-bn.has-img::before{content:"";position:absolute;inset:0;background:var(--wwi-bn-bg);opacity:var(--wwi-bn-ov,.55);z-index:0}
.wwi-bn.has-img .wwi-bn__track,.wwi-bn.has-img .wwi-bn__cd,.wwi-bn.has-img .wwi-bn__x,.wwi-bn.has-img .wwi-bn__dots{position:relative;z-index:1}
.wwi-bn__im{width:26px;height:26px;object-fit:cover;border-radius:5px;vertical-align:middle}
.wwi-bn--toast{position:fixed;right:20px;bottom:20px;left:auto;max-width:360px;border-radius:12px;box-shadow:0 18px 48px rgba(0,0,0,.35);padding:14px 2.6rem 14px 16px;text-align:left;z-index:210}
.wwi-bn--anim-slide-down{animation:wwiBnDown .45s cubic-bezier(.2,.8,.3,1) both}
.wwi-bn--anim-slide-up{animation:wwiBnUp .45s cubic-bezier(.2,.8,.3,1) both}
.wwi-bn--anim-slide-left{animation:wwiBnLeft .45s cubic-bezier(.2,.8,.3,1) both}
.wwi-bn--anim-slide-right{animation:wwiBnRight .45s cubic-bezier(.2,.8,.3,1) both}
.wwi-bn--anim-fade{animation:wwiBnFade .6s ease both}
.wwi-bn--anim-zoom{animation:wwiBnZoom .4s ease both}
.wwi-bn--anim-bounce{animation:wwiBnBounce .7s ease both}
@keyframes wwiBnDown{from{transform:translateY(-100%);opacity:.4}to{transform:none;opacity:1}}
@keyframes wwiBnUp{from{transform:translateY(100%);opacity:.4}to{transform:none;opacity:1}}
@keyframes wwiBnLeft{from{transform:translateX(100%);opacity:.4}to{transform:none;opacity:1}}
@keyframes wwiBnRight{from{transform:translateX(-100%);opacity:.4}to{transform:none;opacity:1}}
@keyframes wwiBnFade{from{opacity:0}to{opacity:1}}
@keyframes wwiBnZoom{from{transform:scale(.7);opacity:0}to{transform:none;opacity:1}}
@keyframes wwiBnBounce{0%{transform:translateY(-120%);opacity:.3}60%{transform:translateY(6%);opacity:1}80%{transform:translateY(-3%)}100%{transform:none}}
.wwi-bn__tx.grad{background:linear-gradient(90deg,var(--wwi-bn-fg),var(--wwi-bn-bg2,#ffd166),var(--wwi-bn-fg));background-size:200% 100%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:wwiBnGrad 4s linear infinite}
@keyframes wwiBnGrad{to{background-position:200% 0}}
.wwi-bn[hidden]{display:none}
@media(prefers-reduced-motion:reduce){.wwi-bn,.wwi-bn *{animation:none!important}}
</style>
<script>if(!window.__wwiBnInit){window.__wwiBnInit=1;(function(){
function seen(k){try{return Number(localStorage.getItem(k)||0)}catch(e){return 0}}
function setSeen(k,v){try{localStorage.setItem(k,v)}catch(e){}}
function init(el){var id=el.id;var freq=el.getAttribute('data-freq')||'always';var sk='wwi-bn-'+id;
 if((el.getAttribute('data-session')==='1')&&sessionStorage.getItem(sk+'-closed')==='1'){el.remove();return}
 if(freq==='once'&&seen(sk)>=1){el.remove();return}
 if(freq==='three'&&seen(sk)>=3){el.remove();return}
 if(freq==='session'&&sessionStorage.getItem(sk+'-seen')==='1'){el.remove();return}
 if(freq==='once'||freq==='three')setSeen(sk,seen(sk)+1);
 if(freq==='session')try{sessionStorage.setItem(sk+'-seen','1')}catch(e){}
 var items=el.querySelectorAll('.wwi-bn__item');var dots=el.querySelectorAll('.wwi-bn__dot');var cur=0;
 function go(i){if(!items.length)return;cur=(i+items.length)%items.length;for(var j=0;j<items.length;j++){items[j].classList.toggle('is-on',j===cur)}for(var d=0;d<dots.length;d++){dots[d].classList.toggle('is-on',d===cur)}
  if(el.getAttribute('data-letters')==='typewriter'){var tx=items[cur].querySelector('.wwi-bn__tx');if(tx&&!tx.getAttribute('data-typed')){var full=tx.textContent;tx.textContent='';tx.setAttribute('data-typed','1');var n=0;var iv=setInterval(function(){tx.textContent=full.slice(0,++n);if(n>=full.length)clearInterval(iv)},28)}}}
 var rot=parseInt(el.getAttribute('data-rot')||'0',10);var timer=null;
 if(rot>0&&items.length>1){timer=setInterval(function(){go(cur+1)},rot);el.addEventListener('mouseenter',function(){clearInterval(timer)});el.addEventListener('mouseleave',function(){if(timer)timer=setInterval(function(){go(cur+1)},rot)})}
 for(var k=0;k<dots.length;k++){dots[k].addEventListener('click',function(e){e.preventDefault();var i=parseInt(this.getAttribute('data-go'),10);if(timer)clearInterval(timer);go(i)})}
 var letters=el.getAttribute('data-letters');if(letters==='gradient'){el.querySelectorAll('.wwi-bn__tx').forEach(function(t){t.classList.add('grad')})}
 if(letters==='typewriter'){go(0)}
 el.querySelectorAll('[data-bn-close]').forEach(function(b){b.addEventListener('click',function(){try{sessionStorage.setItem(sk+'-closed','1')}catch(e){}el.remove()})});
 var cdv=el.getAttribute('data-cd');var cds=el.querySelector('.wwi-bn__cd');if(cdv&&cds){var to=new Date(cdv.length<=10?cdv+'T00:00':cdv).getTime();var tick=function(){var d=to-Date.now();if(d<0)d=0;var s=Math.floor(d/1000);var dd=Math.floor(s/86400),hh=Math.floor((s%86400)/3600),mm=Math.floor((s%3600)/60),ss=s%60;function p(n){return (n<10?'0':'')+n}cds.textContent='⏳ '+(dd>0?dd+'d ':'')+p(hh)+':'+p(mm)+':'+p(ss)};tick();setInterval(tick,1000)}
 if(el.classList.contains('wwi-bn--exit')){(function(){var shown=false;function open(){if(shown)return;shown=true;el.classList.add('is-open')}var t=setTimeout(open,20000);document.addEventListener('mouseout',function(e){if(!e.relatedTarget&&e.clientY<=0){clearTimeout(t);open()}})})()}
 var ab=el.classList.contains('wwi-bn--ab');var variant='a';
 if(ab){try{variant=localStorage.getItem('wwi-bn-ab-'+id)||''}catch(e){}if(variant!=='a'&&variant!=='b'){variant=Math.random()<0.5?'a':'b';try{localStorage.setItem('wwi-bn-ab-'+id,variant)}catch(e){}}el.classList.add('ab-'+variant)}
 var tuid=el.getAttribute('data-uid');if(el.getAttribute('data-track')==='1'&&tuid){var evt=function(t){try{fetch('/api/v1/public/banner/event',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({uid:tuid,variant:variant,type:t}),keepalive:true})}catch(e){}};evt('impression');el.querySelectorAll('.wwi-bn__cta').forEach(function(a){a.addEventListener('click',function(){evt('click')})});}
 if(el.classList.contains('wwi-bn--modal')){document.addEventListener('keydown',function(e){if(e.key==='Escape'&&document.body.contains(el)){el.remove()}})}
}
document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.wwi-bn:not([data-ready])').forEach(function(el){el.setAttribute('data-ready','1');init(el)})});
if(document.readyState!=='loading'){document.querySelectorAll('.wwi-bn:not([data-ready])').forEach(function(el){el.setAttribute('data-ready','1');init(el)})}
})();}</script>
HTML;
    }
}
