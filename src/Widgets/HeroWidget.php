<?php
namespace App\Widgets;

class HeroWidget extends Widget
{
    public static function meta(): array { return ['id' => 'hero', 'name' => 'Hero', 'icon' => 'layout-top', 'category' => 'header', 'version' => '2.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text', 'default' => 'AI · TECHNOLOGY · BUSINESS OPERATIONS'],
            ['key' => 'badge_text', 'label' => 'Badge Text (legacy)', 'type' => 'text', 'default' => ''],
            ['key' => 'title', 'label' => 'Title (HTML)', 'type' => 'html', 'default' => 'Human talent. <span class="inh-blue">Intelligent technology.</span> Real impact.'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'We combine people, software and AI to help organizations scale intelligently through high-quality data operations, BPO services and technology-driven solutions.'],
            ['key' => 'cta_primary_text', 'label' => 'Primary CTA Text', 'type' => 'text', 'default' => 'Explore our solutions'],
            ['key' => 'cta_primary_url', 'label' => 'Primary CTA URL', 'type' => 'text', 'default' => '/technology'],
            ['key' => 'cta_secondary_text', 'label' => 'Secondary CTA Text', 'type' => 'text', 'default' => 'Watch video'],
            ['key' => 'cta_secondary_url', 'label' => 'Secondary CTA URL (video)', 'type' => 'text', 'default' => '#video'],
            ['key' => 'image', 'label' => 'Hero image (opcional)', 'type' => 'image', 'default' => ''],
            ['key' => 'image_alt', 'label' => 'Hero image alt', 'type' => 'text', 'default' => 'INTSOLCOM — Ventana al Mundo, Barranquilla'],
            ['key' => 'accent', 'label' => 'Accent color (vacío = #00A3FF)', 'type' => 'color', 'default' => ''],
            ['key' => 'cards', 'label' => 'Tarjetas flotantes', 'type' => 'repeater', 'fields' => [
                ['key' => 'title', 'label' => 'Título', 'type' => 'text'],
                ['key' => 'desc', 'label' => 'Descripción', 'type' => 'text'],
            ], 'default' => [
                ['title' => 'AI Data Operations', 'desc' => 'Video annotation, images, text and more.'],
                ['title' => 'BPO Services', 'desc' => 'Customer support, back-office and specialized processes.'],
                ['title' => 'Software & AI', 'desc' => 'Proprietary solutions to automate and scale operations.'],
                ['title' => 'Global Operations', 'desc' => 'Colombia · United States · Latin America'],
            ]],
            ['key' => 'metrics', 'label' => 'Métricas', 'type' => 'repeater', 'fields' => [
                ['key' => 'value', 'label' => 'Valor', 'type' => 'text'],
                ['key' => 'label', 'label' => 'Etiqueta', 'type' => 'text'],
            ], 'default' => [
                ['value' => '300+', 'label' => 'Professionals'],
                ['value' => '11+', 'label' => 'Active clients'],
                ['value' => '4', 'label' => 'Countries served'],
                ['value' => '98%', 'label' => 'Client retention'],
            ]],
            ['key' => 'show_metrics', 'label' => 'Mostrar métricas (1/0)', 'type' => 'text', 'default' => '1'],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:linear-gradient(135deg,#EAF5FF,#F8FAFC);border-radius:12px;padding:24px;text-align:center"><div style="font-size:20px;font-weight:800;color:#0D1F37">Hero · <span style="color:#00A3FF">Intelligent</span></div><div style="font-size:11px;color:#64748B;margin-top:4px">Eyebrow + H1 + CTAs + visual + métricas</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $accent = preg_match('/^#[0-9a-fA-F]{6}$/', (string)$c['accent']) ? (string)$c['accent'] : '#00A3FF';
        $eyebrow = trim((string)$c['eyebrow']);
        if ($eyebrow === '') $eyebrow = trim((string)$c['badge_text']);
        $cards = $this->safeJson($c['cards'] ?? []);
        $metrics = $this->safeJson($c['metrics'] ?? []);
        $showMetrics = (string)($c['show_metrics'] ?? '1') !== '0';

        $html = '<section class="inh-hero" id="inicio" style="--inh-blue:' . $accent . '">';
        $html .= '<style>'
            . '.inh-hero{--inh-blue:#00A3FF;--inh-ink:#0D1F37;--inh-graphite:#20252B;--inh-mut:#64748B;--inh-bd:#E2E8F0;--inh-bg:#F8FAFC;background:var(--inh-bg);padding:132px 24px 72px;position:relative;overflow:hidden;font-family:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}'
            . '.inh-grid{max-width:1200px;margin:0 auto;display:grid;grid-template-columns:minmax(0,48fr) minmax(0,52fr);gap:clamp(40px,5vw,72px);align-items:center}'
            . '.inh-eyebrow{font:700 12px/1.2 inherit;letter-spacing:.16em;text-transform:uppercase;color:var(--inh-mut);margin:0 0 20px}'
            . '.inh-title{font-weight:800;color:var(--inh-ink);letter-spacing:-.03em;line-height:1.06;font-size:clamp(38px,5vw,76px);margin:0 0 20px;text-wrap:balance}'
            . '.inh-blue{color:var(--inh-blue)}'
            . '.inh-sub{font-size:clamp(16px,1.3vw,20px);line-height:1.55;color:var(--inh-mut);max-width:600px;margin:0 0 30px}'
            . '.inh-ctas{display:flex;gap:12px;flex-wrap:wrap;align-items:center}'
            . '.inh-btn{display:inline-flex;align-items:center;gap:9px;border-radius:12px;padding:15px 26px;font:700 15px/1 inherit;text-decoration:none;transition:transform .18s ease,box-shadow .18s ease,background .18s ease,border-color .18s ease,color .18s ease;cursor:pointer}'
            . '.inh-btn:focus-visible{outline:2px solid var(--inh-blue);outline-offset:3px}'
            . '.inh-btn-primary{background:var(--inh-blue);color:#fff;box-shadow:0 10px 30px rgba(0,163,255,.28)}'
            . '.inh-btn-primary:hover{transform:translateY(-2px);box-shadow:0 16px 40px rgba(0,163,255,.38)}'
            . '.inh-arrow{transition:transform .18s ease}.inh-btn-primary:hover .inh-arrow{transform:translateX(4px)}'
            . '.inh-btn-ghost{background:#fff;color:var(--inh-graphite);border:1px solid var(--inh-bd)}'
            . '.inh-btn-ghost:hover{border-color:var(--inh-blue);color:var(--inh-blue)}'
            . '.inh-visual{position:relative;min-width:0}'
            . '.inh-frame{position:relative;border-radius:18px;overflow:hidden;background:linear-gradient(150deg,#0D1F37,#1B3B66);border:1px solid rgba(13,31,55,.12);aspect-ratio:4/3;box-shadow:0 40px 90px rgba(13,31,55,.18)}'
            . '.inh-frame img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block}'
            . '.inh-geo{position:absolute;inset:0;width:100%;height:100%}'
            . '.inh-ai{position:absolute;left:14px;top:14px;display:flex;align-items:center;gap:8px;background:rgba(13,31,55,.55);-webkit-backdrop-filter:blur(6px);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.16);border-radius:999px;padding:6px 12px;color:#eaf4ff;font:600 10.5px/1 inherit;letter-spacing:.05em;z-index:3}'
            . '.inh-ai i{width:7px;height:7px;border-radius:50%;background:var(--inh-blue);display:inline-block;animation:inhPulse 2.4s ease-in-out infinite}'
            . '.inh-ai .inh-line{width:30px;height:1px;background:linear-gradient(90deg,var(--inh-blue),transparent)}'
            . '@keyframes inhPulse{0%,100%{opacity:1}50%{opacity:.35}}'
            . '.inh-cards{position:absolute;right:-6px;bottom:-6px;display:grid;grid-template-columns:1fr 1fr;gap:10px;width:64%;z-index:4}'
            . '.inh-card{background:#fff;border:1px solid var(--inh-bd);border-radius:12px;padding:11px 13px;box-shadow:0 16px 34px rgba(13,31,55,.14)}'
            . '.inh-card b{display:block;font-size:12.5px;color:var(--inh-ink);font-weight:700;margin-bottom:3px}'
            . '.inh-card span{font-size:11px;color:var(--inh-mut);line-height:1.4;display:block}'
            . '.inh-metrics{max-width:1200px;margin:56px auto 0;display:grid;grid-template-columns:repeat(4,1fr);border-top:1px solid var(--inh-bd);padding-top:26px}'
            . '.inh-metric{padding:0 22px;border-left:1px solid var(--inh-bd)}'
            . '.inh-metric:first-child{border-left:0;padding-left:0}'
            . '.inh-metric b{display:block;font-size:clamp(26px,3vw,38px);font-weight:800;letter-spacing:-.02em;color:var(--inh-ink);font-variant-numeric:tabular-nums}'
            . '.inh-metric span{font-size:12.5px;color:var(--inh-mut)}'
            . '@media(max-width:1023px){.inh-grid{grid-template-columns:1fr;gap:34px}.inh-cards{position:static;width:auto;max-width:none;margin-top:14px}.inh-metrics{grid-template-columns:repeat(2,1fr);gap:20px 0}.inh-metric{border-left:0;padding:0}}'
            . '@media(max-width:560px){.inh-metrics{grid-template-columns:1fr;gap:16px}.inh-btn{width:100%;justify-content:center}}'
            . '@media(prefers-reduced-motion:reduce){.inh-btn,.inh-arrow{transition:none}.inh-ai i{animation:none}}'
            . '</style>';

        $html .= '<div class="inh-grid">';
        $html .= '<div class="inh-copy">';
        if ($eyebrow !== '') $html .= '<div class="inh-eyebrow">' . $this->esc($eyebrow) . '</div>';
        $html .= '<h1 class="inh-title">' . $c['title'] . '</h1>';
        $html .= '<p class="inh-sub">' . $this->esc($c['subtitle']) . '</p>';
        $html .= '<div class="inh-ctas">';
        $html .= '<a class="inh-btn inh-btn-primary" href="' . $this->esc($c['cta_primary_url']) . '">' . $this->esc($c['cta_primary_text']) . ' <span class="inh-arrow" aria-hidden="true">→</span></a>';
        if (trim((string)$c['cta_secondary_text']) !== '') {
            $html .= '<a class="inh-btn inh-btn-ghost" href="' . $this->esc($c['cta_secondary_url']) . '"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-width="1.6"/><path d="M10 8.5l6 3.5-6 3.5v-7z" fill="currentColor"/></svg> ' . $this->esc($c['cta_secondary_text']) . '</a>';
        }
        $html .= '</div></div>';

        $html .= '<div class="inh-visual"><div class="inh-frame">';
        if (trim((string)$c['image']) !== '') {
            $html .= '<img src="' . $this->esc($c['image']) . '" alt="' . $this->esc($c['image_alt']) . '" loading="eager" decoding="async"/>';
        } else {
            $html .= '<svg class="inh-geo" viewBox="0 0 800 600" preserveAspectRatio="xMidYMid slice" aria-hidden="true">'
                . '<defs><linearGradient id="inhg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#12294A"/><stop offset="1" stop-color="#0D1F37"/></linearGradient></defs>'
                . '<rect width="800" height="600" fill="url(#inhg)"/>'
                . '<g stroke="rgba(255,255,255,.10)" stroke-width="1">'
                . '<path d="M0 430 L800 300"/><path d="M0 500 L800 380"/><path d="M0 350 L800 220"/>'
                . '</g>'
                . '<g fill="rgba(0,163,255,.9)"><circle cx="180" cy="360" r="5"/><circle cx="400" cy="300" r="5"/><circle cx="620" cy="240" r="5"/></g>'
                . '<g stroke="rgba(0,163,255,.5)" stroke-width="1.5" fill="none"><path d="M180 360 L400 300 L620 240"/></g>'
                . '<g fill="rgba(255,255,255,.06)" stroke="rgba(255,255,255,.14)"><rect x="90" y="150" width="150" height="90" rx="10"/><rect x="320" y="110" width="150" height="90" rx="10"/><rect x="550" y="150" width="150" height="90" rx="10"/></g>'
                . '</svg>';
        }
        $html .= '<span class="inh-ai"><i></i> Data <span class="inh-line"></span> AI <span class="inh-line"></span> Operations <span class="inh-line"></span> Execution</span>';
        $html .= '</div>';
        if ($cards) {
            $html .= '<div class="inh-cards">';
            foreach ($cards as $card) {
                if (!is_array($card)) continue;
                $html .= '<div class="inh-card"><b>' . $this->esc((string)($card['title'] ?? '')) . '</b><span>' . $this->esc((string)($card['desc'] ?? '')) . '</span></div>';
            }
            $html .= '</div>';
        }
        $html .= '</div></div>';

        if ($showMetrics && $metrics) {
            $html .= '<div class="inh-metrics">';
            foreach ($metrics as $m) {
                if (!is_array($m)) continue;
                $html .= '<div class="inh-metric"><b>' . $this->esc((string)($m['value'] ?? '')) . '</b><span>' . $this->esc((string)($m['label'] ?? '')) . '</span></div>';
            }
            $html .= '</div>';
        }
        $html .= '</section>';
        return $html;
    }
}
