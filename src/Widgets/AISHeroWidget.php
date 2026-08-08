<?php
namespace App\Widgets;

class AISHeroWidget extends Widget
{
    public static function meta(): array { return ['id' => 'ais-hero', 'name' => 'Hero: AIS', 'icon' => 'zap', 'category' => 'header', 'version' => '3.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'eyebrow', 'label' => 'Eyebrow', 'type' => 'text', 'default' => 'APPLIED INTELLIGENCE SYSTEM'],
            ['key' => 'headline', 'label' => 'Headline', 'type' => 'html', 'default' => 'Turn AI into<br><span class="gradient-text">Applied Intelligence</span>'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'WONTIA is an Applied Intelligence System powered by TIA — Technology of Applied Intelligence. Understand context. Make decisions. Execute actions. Across any domain, in any language, through any interface.'],
            ['key' => 'cta_primary_text', 'label' => 'Primary CTA', 'type' => 'text', 'default' => 'Explore Wontia Business'],
            ['key' => 'cta_primary_url', 'label' => 'Primary CTA URL', 'type' => 'text', 'default' => '#ais-concept'],
            ['key' => 'cta_secondary_text', 'label' => 'Secondary CTA', 'type' => 'text', 'default' => 'Meet TIA'],
            ['key' => 'cta_secondary_url', 'label' => 'Secondary CTA URL', 'type' => 'text', 'default' => '#tia-command'],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:linear-gradient(135deg,#1A1A1E,#2D2240);border-radius:12px;padding:24px;text-align:center"><div style="font-size:11px;color:#B89EFF;letter-spacing:0.1em;margin-bottom:8px">APPLIED INTELLIGENCE SYSTEM</div><div style="font-size:22px;font-weight:800;color:#fff">Turn AI into <span style="color:#B89EFF">Applied Intelligence</span></div><div style="font-size:11px;color:#8b8fa3;margin-top:8px">AIS positioning hero</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        return '
<section style="padding:200px 40px 120px;max-width:1100px;margin:0 auto;text-align:center;position:relative;overflow:hidden">
  <div style="position:absolute;top:-80px;left:50%;transform:translateX(-50%);width:900px;height:500px;pointer-events:none;z-index:0;opacity:0.3">
    <svg viewBox="0 0 900 500" fill="none">
      <defs><radialGradient id="hg-ais" cx="50%" cy="50%"><stop offset="0%" stop-color="#DCCFFF" stop-opacity="0.5"/><stop offset="100%" stop-color="transparent"/></radialGradient></defs>
      <circle cx="450" cy="250" r="200" fill="url(#hg-ais)"/>
      <circle cx="450" cy="250" r="130" stroke="#DCCFFF" stroke-opacity="0.25" stroke-width="1" fill="none"/>
      <circle cx="450" cy="250" r="60" stroke="#B89EFF" stroke-opacity="0.2" stroke-width="1.5" fill="none"/>
      <circle cx="450" cy="250" r="18" fill="#9B8CDE" opacity="0.6"/>
      <circle cx="450" cy="250" r="6" fill="#fff" opacity="0.8"/>
      <line x1="450" y1="250" x2="280" y2="180" stroke="#DCCFFF" stroke-opacity="0.3" stroke-width="0.5"/>
      <line x1="450" y1="250" x2="620" y2="170" stroke="#DCCFFF" stroke-opacity="0.3" stroke-width="0.5"/>
      <line x1="450" y1="250" x2="280" y2="330" stroke="#DCCFFF" stroke-opacity="0.3" stroke-width="0.5"/>
      <line x1="450" y1="250" x2="620" y2="340" stroke="#DCCFFF" stroke-opacity="0.3" stroke-width="0.5"/>
      <circle cx="280" cy="180" r="8" fill="#CFE6FF" opacity="0.5"/>
      <circle cx="620" cy="170" r="7" fill="#D9F2E2" opacity="0.5"/>
      <circle cx="280" cy="330" r="6" fill="#F7E8C8" opacity="0.5"/>
      <circle cx="620" cy="340" r="9" fill="#DCCFFF" opacity="0.5"/>
    </svg>
  </div>
  <div style="position:relative;z-index:1">
    <div style="font-size:10px;font-weight:700;color:#7C3AED;letter-spacing:0.15em;margin-bottom:24px;text-transform:uppercase">' . $this->esc($c['eyebrow']) . '</div>
    <h1 style="font-size:clamp(42px,8vw,72px);font-weight:800;color:#1A1A1E;line-height:1.08;letter-spacing:-0.03em;margin-bottom:24px">' . $c['headline'] . '</h1>
    <p style="font-size:18px;color:#6B6B6B;line-height:1.8;max-width:700px;margin:0 auto 40px">' . $this->esc($c['subtitle']) . '</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="' . $this->esc($c['cta_primary_url']) . '" class="btn-primary" style="padding:16px 40px;font-size:16px">' . $this->esc($c['cta_primary_text']) . '</a>
      <a href="' . $this->esc($c['cta_secondary_url']) . '" class="btn-outline" style="padding:16px 40px;font-size:16px">' . $this->esc($c['cta_secondary_text']) . '</a>
    </div>
  </div>
</section>';
    }
}
