<?php
namespace App\Widgets;

class AISHeroWidget extends Widget
{
    public static function meta(): array { return ['id' => 'ais-hero', 'name' => 'Hero: AIS', 'icon' => 'zap', 'category' => 'header', 'version' => '3.1.0']; }

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
            ['key' => 'preview_enabled', 'label' => 'Enable interactive business preview (feature flag)', 'type' => 'toggle', 'default' => false],
            ['key' => 'preview_eyebrow', 'label' => 'Preview eyebrow', 'type' => 'text', 'default' => 'INTELIGENCIA APLICADA PARA NEGOCIOS'],
            ['key' => 'preview_headline', 'label' => 'Preview headline', 'type' => 'html', 'default' => 'Tu negocio, listo para <span class="wwi-ais-preview-gradient">dar el siguiente paso.</span>'],
            ['key' => 'preview_subtitle', 'label' => 'Preview subtitle', 'type' => 'textarea', 'default' => 'Explora cómo Wontia puede convertir contexto en decisiones y acciones útiles para tu negocio.'],
            ['key' => 'preview_cta_primary_text', 'label' => 'Preview primary CTA', 'type' => 'text', 'default' => 'Explorar Wontia Business'],
            ['key' => 'preview_cta_primary_url', 'label' => 'Preview primary CTA URL', 'type' => 'text', 'default' => '#business'],
            ['key' => 'preview_cta_secondary_text', 'label' => 'Preview secondary CTA', 'type' => 'text', 'default' => 'Conoce cómo funciona'],
            ['key' => 'preview_cta_secondary_url', 'label' => 'Preview secondary CTA URL', 'type' => 'text', 'default' => '#tia-command'],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:linear-gradient(135deg,#1A1A1E,#2D2240);border-radius:12px;padding:24px;text-align:center"><div style="font-size:11px;color:#B89EFF;letter-spacing:0.1em;margin-bottom:8px">APPLIED INTELLIGENCE SYSTEM</div><div style="font-size:22px;font-weight:800;color:#fff">Turn AI into <span style="color:#B89EFF">Applied Intelligence</span></div><div style="font-size:11px;color:#8b8fa3;margin-top:8px">AIS hero · optional interactive preview</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        return !empty($c['preview_enabled']) ? $this->renderPreview($c) : $this->renderLegacy($c);
    }

    private function renderLegacy(array $c): string
    {
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
    <div style="font-size:10px;font-weight:700;color:#7C3AED;letter-spacing:0.15em;margin-bottom:24px;text-transform:uppercase">' . $this->esc((string)$c['eyebrow']) . '</div>
    <h1 style="font-size:clamp(42px,8vw,72px);font-weight:800;color:#1A1A1E;line-height:1.08;letter-spacing:-0.03em;margin-bottom:24px">' . $this->rich((string)$this->rich((string)$c['headline'])) . '</h1>
    <p style="font-size:18px;color:#6B6B6B;line-height:1.8;max-width:700px;margin:0 auto 40px">' . $this->esc((string)$c['subtitle']) . '</p>
    <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
      <a href="' . $this->esc((string)$c['cta_primary_url']) . '" class="btn-primary" style="padding:16px 40px;font-size:16px">' . $this->esc((string)$c['cta_primary_text']) . '</a>
      <a href="' . $this->esc((string)$c['cta_secondary_url']) . '" class="btn-outline" style="padding:16px 40px;font-size:16px">' . $this->esc((string)$c['cta_secondary_text']) . '</a>
    </div>
  </div>
</section>';
    }

    private function safeHref(string $url): string
    {
        $url = trim($url);
        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/') || preg_match('/^https?:\\/\\//i', $url) || preg_match('/^mailto:/i', $url)) {
            return $this->esc($url === '' ? '#' : $url);
        }
        return '#';
    }
    private function renderPreview(array $c): string
    {
        return '
<style>
.wwi-ais-preview{--wap-ink:#24212f;--wap-muted:#686577;--wap-line:#e9e5f2;--wap-purple:#7650e8;--wap-lilac:#f1edff;display:grid;grid-template-columns:minmax(0,1fr) minmax(400px,1.08fr);align-items:center;gap:clamp(32px,5vw,72px);max-width:1240px;margin:0 auto;padding:clamp(112px,14vw,176px) 32px 88px;color:var(--wap-ink);text-align:left}
.wwi-ais-preview *{box-sizing:border-box}.wwi-ais-preview-copy{min-width:0}.wwi-ais-preview-sr{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.wwi-ais-preview-eyebrow{margin:0 0 22px;color:var(--wap-purple);font-size:11px;font-weight:750;letter-spacing:.15em;text-transform:uppercase}
.wwi-ais-preview h1{margin:0 0 22px;color:var(--wap-ink);font-size:clamp(40px,5vw,66px);font-weight:800;line-height:1.04;letter-spacing:-.045em}
.wwi-ais-preview-gradient{background:linear-gradient(110deg,#7550e8,#aa83f4);background-clip:text;-webkit-background-clip:text;color:transparent}
.wwi-ais-preview-subtitle{max-width:560px;margin:0 0 30px;color:var(--wap-muted);font-size:17px;line-height:1.7}
.wwi-ais-preview-actions{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.wwi-ais-preview-actions a{min-height:48px;padding:14px 22px;font-size:14px;text-align:center;text-decoration:none}
.wwi-ais-preview-trust{display:flex;align-items:center;gap:9px;margin:18px 0 0;color:var(--wap-muted);font-size:12px;line-height:1.5}.wwi-ais-preview-trust svg{flex:0 0 18px;color:var(--wap-purple)}
.wwi-ais-preview-card{min-width:0;padding:22px;border:1px solid var(--wap-line);border-radius:22px;background:rgba(255,255,255,.94);box-shadow:0 18px 60px rgba(118,80,232,.09)}
.wwi-ais-preview-card-head{display:flex;align-items:flex-start;justify-content:space-between;gap:12px;margin-bottom:18px}.wwi-ais-preview-card-head h2{margin:0;color:var(--wap-ink);font-size:20px;line-height:1.3}
.wwi-ais-preview-label{flex:0 0 auto;padding:6px 9px;border-radius:999px;background:var(--wap-lilac);color:#6241c7;font-size:9px;font-weight:800;letter-spacing:.08em}
.wwi-ais-preview-group{margin:0 0 16px;padding:0;border:0}.wwi-ais-preview-group legend{margin-bottom:8px;color:var(--wap-ink);font-size:12px;font-weight:700}
.wwi-ais-preview-options{display:flex;gap:8px;flex-wrap:wrap}.wwi-ais-preview-option{display:inline-flex;min-height:42px;align-items:center;gap:7px;padding:8px 11px;border:1px solid var(--wap-line);border-radius:10px;background:#fff;color:#4d495a;font:inherit;font-size:12px;cursor:pointer;transition:border-color .18s ease,background-color .18s ease,color .18s ease}
.wwi-ais-preview-option:hover{border-color:#b5a3f2}.wwi-ais-preview-option[aria-pressed="true"]{border-color:var(--wap-purple);background:var(--wap-lilac);color:#5e3cc8}
.wwi-ais-preview-option:focus-visible,.wwi-ais-preview-actions a:focus-visible{outline:3px solid #8a6af0;outline-offset:3px}
.wwi-ais-preview-result{overflow:hidden;border:1px solid var(--wap-line);border-radius:14px;background:#fbfafc}
.wwi-ais-preview-site{position:relative;min-height:180px;padding:18px;display:flex;align-items:flex-end;background:linear-gradient(120deg,#ede5d9,#faf5ed 50%,#ded0bd)}
.wwi-ais-preview-site-art{position:absolute;inset:0 0 0 42%;overflow:hidden}.wwi-ais-preview-site-art:before{position:absolute;top:14%;right:18%;width:40%;height:72%;border-radius:52% 48% 12% 12%;background:linear-gradient(145deg,#657a57,#a8b49a 38%,#566c50);content:""}.wwi-ais-preview-site-art:after{position:absolute;right:8%;bottom:0;width:42%;height:52%;border-radius:50% 50% 0 0;background:linear-gradient(120deg,#b88755,#e1c09b);content:""}
.wwi-ais-preview-site-copy{position:relative;z-index:1;max-width:56%}.wwi-ais-preview-site-kicker{margin:0 0 7px;color:#665f53;font-size:9px;font-weight:700;letter-spacing:.16em;text-transform:uppercase}
.wwi-ais-preview-site-title{margin:0 0 7px;color:#25231e;font-family:Georgia,serif;font-size:23px;line-height:1.05}.wwi-ais-preview-site-desc{margin:0;color:#4f4a42;font-size:10px;line-height:1.45}
.wwi-ais-preview-status{position:absolute;top:12px;right:12px;padding:6px 9px;border-radius:99px;background:#e2f5e9;color:#31734c;font-size:9px;font-weight:800}
.wwi-ais-preview-flow{display:grid;grid-template-columns:1fr auto 1fr auto 1fr;align-items:center;gap:8px;padding:14px 10px}
.wwi-ais-preview-step{display:flex;align-items:center;gap:7px;color:#413b51;font-size:10px;font-weight:600;line-height:1.35}.wwi-ais-preview-step-icon{display:grid;width:28px;height:28px;flex:0 0 28px;place-items:center;border-radius:50%;background:var(--wap-lilac);color:var(--wap-purple);font-size:13px}
.wwi-ais-preview-arrow{color:#8875bd}.wwi-ais-preview-caption{margin:0;padding:0 10px 14px;color:#777284;font-size:10px;line-height:1.5}
@media(max-width:900px){.wwi-ais-preview{grid-template-columns:1fr;max-width:760px;padding-top:112px}.wwi-ais-preview-card{max-width:620px}}
@media(max-width:520px){.wwi-ais-preview{padding:96px 18px 60px}.wwi-ais-preview h1{font-size:clamp(36px,11vw,48px)}.wwi-ais-preview-subtitle{font-size:15px}.wwi-ais-preview-card{padding:16px;border-radius:17px}.wwi-ais-preview-card-head{display:block}.wwi-ais-preview-label{display:inline-block;margin-top:8px}.wwi-ais-preview-options{display:grid;grid-template-columns:1fr 1fr}.wwi-ais-preview-option{justify-content:center;padding:8px 6px;font-size:11px}.wwi-ais-preview-site{min-height:160px}.wwi-ais-preview-flow{grid-template-columns:1fr;gap:7px}.wwi-ais-preview-arrow{display:none}.wwi-ais-preview-step{font-size:11px}}
@media(prefers-reduced-motion:reduce){.wwi-ais-preview-option{transition:none}}
</style>
<section class="wwi-ais-preview" data-wwi-ais-preview>
  <div class="wwi-ais-preview-copy">
    <p class="wwi-ais-preview-eyebrow">' . $this->esc((string)$c['preview_eyebrow']) . '</p>
    <h1>' . $this->rich((string)$c['preview_headline']) . '</h1>
    <p class="wwi-ais-preview-subtitle">' . $this->esc((string)$c['preview_subtitle']) . '</p>
    <div class="wwi-ais-preview-actions">
      <a class="btn-primary" href="' . $this->safeHref((string)$c['preview_cta_primary_url']) . '">' . $this->esc((string)$c['preview_cta_primary_text']) . '</a>
      <a class="btn-outline" href="' . $this->safeHref((string)$c['preview_cta_secondary_url']) . '">' . $this->esc((string)$c['preview_cta_secondary_text']) . '</a>
    </div>
    <p class="wwi-ais-preview-trust"><svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 3 5 6v5c0 4.5 2.8 8 7 10 4.2-2 7-5.5 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.6"/><path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg><span>Alcance e inversión claros antes de contratar.</span></p>
  </div>
  <div class="wwi-ais-preview-card">
    <div class="wwi-ais-preview-card-head"><h2>Explora una idea para tu negocio</h2><span class="wwi-ais-preview-label">EJEMPLO INTERACTIVO</span></div>
    <fieldset class="wwi-ais-preview-group" data-preview-group="business"><legend>Tipo de negocio</legend><div class="wwi-ais-preview-options">
      <button class="wwi-ais-preview-option" type="button" data-preview-value="store" aria-pressed="true">Tienda</button>
      <button class="wwi-ais-preview-option" type="button" data-preview-value="restaurant" aria-pressed="false">Restaurante</button>
      <button class="wwi-ais-preview-option" type="button" data-preview-value="services" aria-pressed="false">Servicios</button>
    </div></fieldset>
    <fieldset class="wwi-ais-preview-group" data-preview-group="goal"><legend>Objetivo</legend><div class="wwi-ais-preview-options">
      <button class="wwi-ais-preview-option" type="button" data-preview-value="sell" aria-pressed="true">Vender</button>
      <button class="wwi-ais-preview-option" type="button" data-preview-value="inquiries" aria-pressed="false">Recibir consultas</button>
      <button class="wwi-ais-preview-option" type="button" data-preview-value="bookings" aria-pressed="false">Reservas</button>
    </div></fieldset>
    <div class="wwi-ais-preview-result">
      <div class="wwi-ais-preview-site"><div class="wwi-ais-preview-site-art" aria-hidden="true"></div><span class="wwi-ais-preview-status">WONTIA BUSINESS · DISPONIBLE</span>
        <div class="wwi-ais-preview-site-copy"><p class="wwi-ais-preview-site-kicker" data-preview-kicker>TIENDA · EJEMPLO DE EXPERIENCIA</p><h3 class="wwi-ais-preview-site-title" data-preview-title>Luna</h3><p class="wwi-ais-preview-site-desc" data-preview-description>Una muestra conceptual de presencia digital para una marca de bienestar.</p></div>
      </div>
      <div class="wwi-ais-preview-flow" aria-label="Flujo ilustrativo de inteligencia aplicada">
        <div class="wwi-ais-preview-step"><span class="wwi-ais-preview-step-icon" aria-hidden="true">1</span><span>Entiende la necesidad</span></div><span class="wwi-ais-preview-arrow" aria-hidden="true">→</span>
        <div class="wwi-ais-preview-step"><span class="wwi-ais-preview-step-icon" aria-hidden="true">2</span><span>Decide el siguiente paso</span></div><span class="wwi-ais-preview-arrow" aria-hidden="true">→</span>
        <div class="wwi-ais-preview-step"><span class="wwi-ais-preview-step-icon" aria-hidden="true">3</span><span>Actúa con TIA</span></div>
      </div>
      <p class="wwi-ais-preview-caption">Vista ilustrativa; las capacidades dependen del plan y la configuración.</p><p class="wwi-ais-preview-sr" role="status" aria-live="polite" aria-atomic="true" data-preview-announcement></p>
    </div>
  </div>
</section>
<script src="/assets/js/ais-hero-preview.js" defer></script>';
    }
}
