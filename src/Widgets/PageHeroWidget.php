<?php
namespace App\Widgets;

class PageHeroWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'page-hero', 'name' => 'INTSOLCOM: Page Hero (compact)', 'icon' => 'layout', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'kicker', 'label' => 'Kicker', 'type' => 'text', 'default' => ''],
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => ''],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => ''],
            ['key' => 'cta_text', 'label' => 'CTA text (optional)', 'type' => 'text', 'default' => ''],
            ['key' => 'cta_url', 'label' => 'CTA URL (optional)', 'type' => 'text', 'default' => ''],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $e = fn($s) => $this->esc((string)$s);
        $title = (string)($c['title'] ?? '');
        $hasCta = trim((string)($c['cta_text'] ?? '')) !== '' && trim((string)($c['cta_url'] ?? '')) !== '';

        $html = '<style>'
            . '.ph{background:linear-gradient(180deg,#F7FAFD 0%,#FFFFFF 100%);border-bottom:1px solid #E9EEF4;padding:124px 0 48px;font-family:Inter,-apple-system,sans-serif}'
            . '.ph *{box-sizing:border-box}'
            . '.ph-wrap{max-width:1080px;margin:0 auto;padding:0 24px}'
            . '.ph-kicker{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:700;letter-spacing:.12em;text-transform:uppercase;color:#00A3FF;margin-bottom:14px}'
            . '.ph-kicker::before{content:"";width:20px;height:2px;background:#00A3FF;border-radius:2px}'
            . '.ph h1{font-family:"Space Grotesk",Inter,sans-serif;font-size:clamp(1.9rem,3.6vw,2.75rem);font-weight:700;letter-spacing:-.03em;line-height:1.12;color:#0F172A;margin:0 0 12px;max-width:820px}'
            . '.ph h1 em{font-style:normal;color:#00A3FF}'
            . '.ph p{font-size:1rem;line-height:1.6;color:#5B6773;max-width:640px;margin:0 0 22px}'
            . '.ph-cta{display:inline-flex;align-items:center;gap:8px;background:#00A3FF;color:#fff;font-weight:700;font-size:14px;text-decoration:none;border-radius:10px;padding:13px 24px;box-shadow:0 8px 22px rgba(0,163,255,.22);transition:background .18s ease,transform .18s ease}'
            . '.ph-cta:hover{background:#008FE0;transform:translateY(-1px)}'
            . '.ph-cta:focus-visible{outline:3px solid #9BD4FA;outline-offset:2px}'
            . '@media(max-width:620px){.ph{padding:104px 0 36px}.ph h1{font-size:clamp(1.6rem,6vw,2rem)}.ph p{font-size:.95rem}}'
            . '</style>';

        $html .= '<section class="ph"><div class="ph-wrap">';
        if (trim((string)($c['kicker'] ?? '')) !== '') $html .= '<span class="ph-kicker">' . $e($c['kicker']) . '</span>';
        $html .= '<h1>' . $title . '</h1>';
        if (trim((string)($c['subtitle'] ?? '')) !== '') $html .= '<p>' . $e($c['subtitle']) . '</p>';
        if ($hasCta) $html .= '<a class="ph-cta" href="' . $e($c['cta_url']) . '">' . $e($c['cta_text']) . ' <span aria-hidden="true">&#8594;</span></a>';
        $html .= '</div></section>';

        return $html;
    }
}
