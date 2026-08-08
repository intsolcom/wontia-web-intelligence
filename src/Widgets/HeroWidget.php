<?php
namespace App\Widgets;

class HeroWidget extends Widget
{
    public static function meta(): array { return ['id' => 'hero', 'name' => 'Hero', 'icon' => 'layout-top', 'category' => 'header', 'version' => '1.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'badge_text', 'label' => 'Badge Text', 'type' => 'text', 'default' => 'Plataforma de Inteligencia Aplicada'],
            ['key' => 'title', 'label' => 'Title (HTML)', 'type' => 'html', 'default' => 'La plataforma que <span class="gradient-text">piensa</span> por ti'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'WONTIA es una Applied Intelligence Platform que integra inteligencia artificial en cada etapa del proceso comercial.'],
            ['key' => 'cta_primary_text', 'label' => 'Primary CTA Text', 'type' => 'text', 'default' => 'Comenzar ahora'],
            ['key' => 'cta_primary_url', 'label' => 'Primary CTA URL', 'type' => 'text', 'default' => 'https://app.wontia.com/login?tab=register'],
            ['key' => 'cta_secondary_text', 'label' => 'Secondary CTA Text', 'type' => 'text', 'default' => 'Conoce a TIA'],
            ['key' => 'cta_secondary_url', 'label' => 'Secondary CTA URL', 'type' => 'text', 'default' => '#tia'],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:linear-gradient(135deg,#EDE9FE,#F3F3EF);border-radius:12px;padding:24px;text-align:center"><div style="font-size:22px;font-weight:800;color:#1A1A1E">Hero Section</div><div style="font-size:11px;color:#6B6B6B;margin-top:4px">Badge + Title + CTAs</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        return '
<section class="hero reveal" id="que-es" style="position:relative">
  <div class="hero-bg">
    <svg viewBox="0 0 800 400" fill="none"><defs><radialGradient id="hg" cx="50%" cy="50%"><stop offset="0%" stop-color="#DCCFFF" stop-opacity="0.6"/><stop offset="100%" stop-color="transparent"/></radialGradient></defs><circle cx="400" cy="200" r="180" fill="url(#hg)"/><circle cx="400" cy="200" r="120" stroke="#DCCFFF" stroke-opacity="0.3" stroke-width="1" fill="none"/><circle cx="400" cy="200" r="80" stroke="#B89EFF" stroke-opacity="0.2" stroke-width="1.5" fill="none"/><circle cx="400" cy="200" r="40" stroke="#9B8CDE" stroke-opacity="0.15" stroke-width="2" fill="none"/></svg>
  </div>
  <div style="position:relative;z-index:1">
    <div class="badge" style="background:#EDE9FE;color:#7C3AED;margin-bottom:28px">' . $this->esc($c['badge_text']) . '</div>
    <h1 style="font-size:56px;font-weight:800;color:#1A1A1E;line-height:1.15;letter-spacing:-0.03em;margin-bottom:20px">' . $c['title'] . '</h1>
    <p style="font-size:18px;color:#6B6B6B;line-height:1.7;max-width:680px;margin:0 auto 36px">' . $this->esc($c['subtitle']) . '</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="' . $this->esc($c['cta_primary_url']) . '" class="btn-primary">' . $this->esc($c['cta_primary_text']) . '</a>
      <a href="' . $this->esc($c['cta_secondary_url']) . '" class="btn-outline">&#x26A1; ' . $this->esc($c['cta_secondary_text']) . '</a>
    </div>
  </div>
</section>';
    }
}
