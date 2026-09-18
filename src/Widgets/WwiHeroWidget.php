<?php
namespace App\Widgets;

class WwiHeroWidget extends Widget
{
    private static bool $assetsPrinted = false;

    public static function meta(): array
    {
        return ['id' => 'wwi-hero', 'name' => 'WWI: Hero', 'icon' => 'zap', 'category' => 'header', 'version' => '2.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'badge', 'label' => 'Badge', 'type' => 'text', 'inline' => true, 'default' => 'Sitio web profesional en 24 horas · TIA construye'],
            ['key' => 'title', 'label' => 'Titulo (usa | para resaltar en gradiente)', 'type' => 'text', 'inline' => true, 'default' => 'Tu negocio online hoy|TIA lo construye por ti.'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'inline' => true, 'default' => 'Cuéntale a TIA sobre tu negocio y mira tu sitio en minutos: diseño, contenido, dominio, hosting, SSL y correos. Sin programador, sin diseñador, sin agencia.'],
            ['key' => 'prompt_placeholder', 'label' => 'Placeholder del prompt', 'type' => 'text', 'default' => 'Describe tu negocio: "soy arquitecto y quiero mostrar mis proyectos"'],
            ['key' => 'prompt_cta', 'label' => 'Texto boton prompt', 'type' => 'text', 'default' => 'Construir mi sitio'],
            ['key' => 'chips', 'label' => 'Chips de ejemplo', 'type' => 'repeater', 'fields' => [
                ['key' => 'label', 'label' => 'Etiqueta', 'type' => 'text'],
                ['key' => 'prompt', 'label' => 'Prompt', 'type' => 'text'],
                ['key' => 'mock', 'label' => 'Mockup (restaurant/law/shop/fitness/tech)', 'type' => 'text'],
            ], 'default' => [
                ['label' => 'Restaurante', 'prompt' => 'Tengo un restaurante y quiero mostrar el menú y recibir reservas', 'mock' => 'restaurant'],
                ['label' => 'Abogado', 'prompt' => 'Soy abogado y quiero ofrecer consultas en línea', 'mock' => 'law'],
                ['label' => 'Tienda', 'prompt' => 'Tengo una tienda y quiero vender mis productos online', 'mock' => 'shop'],
                ['label' => 'Gimnasio', 'prompt' => 'Tengo un gimnasio y quiero mostrar planes y horarios', 'mock' => 'fitness'],
            ]],
            ['key' => 'cta_primary', 'label' => 'CTA Principal', 'type' => 'text', 'inline' => true, 'default' => 'Empezar ahora'],
            ['key' => 'cta_primary_url', 'label' => 'CTA URL', 'type' => 'link', 'default' => '#empezar'],
            ['key' => 'cta_secondary', 'label' => 'CTA Secundario', 'type' => 'text', 'default' => 'Ver cómo funciona'],
            ['key' => 'cta_secondary_url', 'label' => 'CTA Secundario URL', 'type' => 'link', 'default' => '#como'],
            ['key' => 'price_note', 'label' => 'Texto de precio', 'type' => 'text', 'inline' => true, 'default' => 'desde $299.000 COP · pago único · dominio el primer año'],
            ['key' => 'social_text', 'label' => 'Prueba social', 'type' => 'text', 'default' => '★ 4.9 · Negocios en 12 países'],
            ['key' => 'mock_url', 'label' => 'URL del mockup', 'type' => 'text', 'default' => 'tunegocio.com'],
            ['key' => 'stats', 'label' => 'Estadísticas', 'type' => 'repeater', 'fields' => [
                ['key' => 'value', 'label' => 'Valor', 'type' => 'text'],
                ['key' => 'label', 'label' => 'Etiqueta', 'type' => 'text'],
            ], 'default' => [
                ['value' => '24h', 'label' => 'o antes, online'],
                ['value' => '0', 'label' => 'programadores necesarios'],
                ['value' => '100%', 'label' => 'gestionable sin código'],
                ['value' => 'TIA', 'label' => 'construye por ti'],
            ]],
            ['key' => 'sectors', 'label' => 'Sectores (marquee)', 'type' => 'repeater', 'fields' => [
                ['key' => 'name', 'label' => 'Sector', 'type' => 'text'],
            ], 'default' => [
                ['name' => 'Restaurantes'], ['name' => 'Abogados'], ['name' => 'Medicos'], ['name' => 'Inmobiliarias'],
                ['name' => 'Hoteles'], ['name' => 'Gimnasios'], ['name' => 'Consultores'], ['name' => 'Tiendas'],
                ['name' => 'Cafeterias'], ['name' => 'Veterinarias'], ['name' => 'Arquitectos'], ['name' => 'Transporte'],
                ['name' => 'Educacion'], ['name' => 'Eventos'], ['name' => 'Moda'], ['name' => 'Tecnologia'],
            ]],
            ['key' => 'trust', 'label' => 'Barra de confianza', 'type' => 'repeater', 'fields' => [
                ['key' => 'text', 'label' => 'Texto', 'type' => 'text'],
            ], 'default' => [
                ['text' => 'Vista previa gratis'],
                ['text' => 'Sin tarjeta'],
                ['text' => 'Sin programador'],
            ]],
            ['key' => 'sector_icon', 'label' => 'Ícono de la cinta', 'type' => 'text', 'inline' => true, 'default' => '◆'],
        ];
    }

    private function assets(): string
    {
        if (self::$assetsPrinted) return '';
        self::$assetsPrinted = true;
        return <<<'HTML'
<style id="wwi-hero-css">
.iqh{position:relative;padding:132px 0 34px;overflow:hidden;isolation:isolate}
.iqh-bg{position:absolute;inset:0;z-index:0;pointer-events:none;overflow:hidden}
.iqh-grid{position:absolute;inset:0;background-image:linear-gradient(var(--border) 1px,transparent 1px),linear-gradient(90deg,var(--border) 1px,transparent 1px);background-size:46px 46px;opacity:.5;-webkit-mask-image:radial-gradient(70% 60% at 50% 22%,#000 0%,transparent 78%);mask-image:radial-gradient(70% 60% at 50% 22%,#000 0%,transparent 78%)}
.iqh-orb{position:absolute;border-radius:50%;filter:blur(70px);opacity:.5}
.iqh-orb-a{width:520px;height:520px;left:-140px;top:-160px;background:radial-gradient(circle at 30% 30%,var(--accent),transparent 62%);animation:iqhFloat 14s ease-in-out infinite}
.iqh-orb-b{width:460px;height:460px;right:-120px;top:-40px;background:radial-gradient(circle at 60% 40%,var(--accent2),transparent 62%);animation:iqhFloat 18s ease-in-out infinite reverse}
.iqh-wrap{position:relative;z-index:2;max-width:1200px;margin:0 auto;padding:0 26px;display:grid;grid-template-columns:1.04fr .96fr;gap:58px;align-items:center}
.iqh-eyebrow{display:inline-flex;align-items:center;gap:9px;padding:7px 15px;border:1px solid var(--border2);border-radius:999px;background:var(--panel);backdrop-filter:blur(10px);font-size:12px;font-weight:600;color:var(--muted);letter-spacing:.01em}
.iqh-dot{width:7px;height:7px;border-radius:50%;background:var(--ok);box-shadow:0 0 0 0 rgba(53,212,154,.55);animation:iqhPulse 2.2s infinite;flex-shrink:0}
.iqh-title{font-size:clamp(34px,4.7vw,58px);line-height:1.05;letter-spacing:-.032em;font-weight:800;margin:18px 0 16px;max-width:620px;text-wrap:balance}
.iqh-sub{font-size:16.5px;line-height:1.72;color:var(--muted);max-width:555px;margin:0 0 24px}
.iqh-prompt{display:flex;gap:8px;padding:8px;border:1px solid var(--border2);border-radius:16px;background:var(--panel);backdrop-filter:blur(12px);max-width:600px;box-shadow:0 14px 44px rgba(0,0,0,.22);transition:border-color .2s,box-shadow .2s}
.iqh-prompt:focus-within{border-color:var(--accent2);box-shadow:0 0 0 4px rgba(124,60,255,.14),0 14px 44px rgba(0,0,0,.22)}
.iqh-prompt input{flex:1;min-width:0;border:none;background:transparent;color:var(--text);font:inherit;font-size:14px;padding:11px 12px;outline:none}
.iqh-prompt input::placeholder{color:var(--muted);opacity:.85}
.iqh-prompt .btn{white-space:nowrap}
.iqh-chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:11px}
.iqh-chip{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;border:1px solid var(--border2);border-radius:999px;background:var(--panel);color:var(--muted);font:inherit;font-size:12px;cursor:pointer;transition:.18s}
.iqh-chip:hover{color:var(--text);border-color:var(--accent2);transform:translateY(-1px)}
.iqh-chip.on{color:var(--text);border-color:var(--accent2);background:linear-gradient(120deg,rgba(124,60,255,.16),rgba(84,190,255,.12))}
.iqh-ctas{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:24px}
.iqh-micro{font-size:12px;color:var(--muted);margin-top:13px}
.iqh-trust{display:flex;gap:18px;flex-wrap:wrap;margin-top:15px;font-size:12.5px;color:var(--muted)}
.iqh-trust span{display:inline-flex;align-items:center;gap:7px}
.iqh-trust span::before{content:'';width:15px;height:15px;border-radius:50%;background:rgba(53,212,154,.16);border:1px solid rgba(53,212,154,.45);display:inline-block;background-image:linear-gradient(135deg,rgba(53,212,154,.9),rgba(53,212,154,.6));-webkit-mask:radial-gradient(circle at 50% 50%,transparent 0 3px,#000 4px);mask:radial-gradient(circle at 50% 50%,transparent 0 3px,#000 4px)}
.iqh-social{display:flex;align-items:center;gap:12px;margin-top:24px;font-size:12.5px;color:var(--muted)}
.iqh-social b{color:var(--text)}
.iqh-avatars{display:flex;padding-left:8px}
.iqh-avatars i{width:31px;height:31px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-style:normal;font-size:11px;font-weight:800;color:#fff;background:linear-gradient(135deg,var(--accent),var(--accent2));border:2px solid var(--bg);margin-left:-8px}
.iqh-visual{position:relative;perspective:1400px}
.iqh-mock{border:1px solid var(--border2);border-radius:18px;background:var(--panel);backdrop-filter:blur(14px);overflow:hidden;box-shadow:0 40px 100px rgba(0,0,0,.4);transform:rotateY(-7deg) rotateX(2.5deg);transition:transform .5s cubic-bezier(.22,1,.36,1)}
.iqh-mock:hover{transform:none}
.iqh-mock-bar{display:flex;align-items:center;gap:6px;padding:11px 14px;border-bottom:1px solid var(--border);background:var(--panel2)}
.iqh-mock-bar i{width:9px;height:9px;border-radius:50%;background:var(--border2)}
.iqh-mock-bar i:nth-child(1){background:#ff5f57}.iqh-mock-bar i:nth-child(2){background:#febc2e}.iqh-mock-bar i:nth-child(3){background:#28c840}
.iqh-url{margin-left:8px;flex:1;font-family:'JetBrains Mono',Consolas,monospace;font-size:10.5px;color:var(--muted);background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:4px 10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.iqh-mock-body{padding:18px;display:flex;flex-direction:column;gap:11px;min-height:284px}
.iqh-m-hero{height:96px;border-radius:12px;background:linear-gradient(120deg,rgba(124,60,255,.5),rgba(84,190,255,.35));position:relative;overflow:hidden}
.iqh-m-hero::after{content:'';position:absolute;inset:0;background:linear-gradient(100deg,transparent 20%,rgba(255,255,255,.22) 50%,transparent 80%);transform:translateX(-100%);animation:iqhShine 3.4s infinite}
.iqh-m-line{height:11px;border-radius:6px;background:var(--panel2);border:1px solid var(--border)}
.iqh-m-line.short{width:62%}
.iqh-m-line.tiny{width:38%}
.iqh-m-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:9px}
.iqh-m-cards i{height:62px;border-radius:10px;background:var(--panel2);border:1px solid var(--border);display:block}
.iqh-mock[data-mock="restaurant"] .iqh-m-hero{background:linear-gradient(120deg,rgba(255,138,76,.55),rgba(255,64,129,.35))}
.iqh-mock[data-mock="law"] .iqh-m-hero{background:linear-gradient(120deg,rgba(84,120,255,.5),rgba(40,60,140,.45))}
.iqh-mock[data-mock="shop"] .iqh-m-hero{background:linear-gradient(120deg,rgba(53,212,154,.5),rgba(84,190,255,.35))}
.iqh-mock[data-mock="fitness"] .iqh-m-hero{background:linear-gradient(120deg,rgba(255,204,102,.55),rgba(255,94,87,.4))}
.iqh-mock[data-mock="law"] .iqh-m-cards{grid-template-columns:1fr 1fr}
.iqh-mock[data-mock="shop"] .iqh-m-cards{grid-template-columns:repeat(4,1fr)}
.iqh-building{display:flex;align-items:center;gap:9px;padding:11px 15px;border-top:1px solid var(--border);font-size:11.5px;color:var(--muted);background:var(--panel2)}
.iqh-building b{margin-left:auto;font-family:'JetBrains Mono',Consolas,monospace;color:var(--text)}
.iqh-spin{width:13px;height:13px;border-radius:50%;border:2px solid var(--border2);border-top-color:var(--accent2);animation:iqhSpin .8s linear infinite;flex-shrink:0}
.iqh-badge{position:absolute;display:inline-flex;align-items:center;gap:7px;padding:8px 13px;border-radius:12px;border:1px solid var(--border2);background:var(--panel);backdrop-filter:blur(12px);font-size:11.5px;font-weight:600;box-shadow:0 14px 34px rgba(0,0,0,.3);animation:iqhFloat 6s ease-in-out infinite;white-space:nowrap}
.iqh-badge-1{top:-16px;left:-18px}
.iqh-badge-2{top:38%;right:-22px;animation-delay:1.2s}
.iqh-badge-3{bottom:-18px;right:16%;animation-delay:2.1s}
.iqh-stats{position:relative;z-index:2;max-width:1200px;margin:54px auto 0;padding:0 26px;display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
.iqh-stat{border:1px solid var(--border);border-radius:16px;background:var(--panel);backdrop-filter:blur(12px);padding:20px 22px;transition:.2s}
.iqh-stat:hover{transform:translateY(-3px);border-color:var(--border2)}
.iqh-stat b{display:block;font-size:26px;font-weight:800;letter-spacing:-.02em;font-family:'JetBrains Mono',Consolas,monospace}
.iqh-stat span{font-size:12px;color:var(--muted)}
.iqh-marquee{position:relative;z-index:2;max-width:1200px;margin:34px auto 0;padding:0 26px}
.iqh-marquee .marquee{max-width:none;margin:0;border-radius:14px}
@keyframes iqhPulse{0%{box-shadow:0 0 0 0 rgba(53,212,154,.5)}70%{box-shadow:0 0 0 9px rgba(53,212,154,0)}100%{box-shadow:0 0 0 0 rgba(53,212,154,0)}}
@keyframes iqhFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-9px)}}
@keyframes iqhSpin{to{transform:rotate(360deg)}}
@keyframes iqhShine{0%{transform:translateX(-100%)}55%,100%{transform:translateX(100%)}}
@media(max-width:1020px){
.iqh{padding:120px 0 26px}
.iqh-wrap{grid-template-columns:1fr;gap:40px}
.iqh-visual{order:2;max-width:560px;margin:0 auto;width:100%}
.iqh-mock{transform:none}
.iqh-badge-1{top:-14px;left:4px}
.iqh-badge-2{right:0}
.iqh-stats{grid-template-columns:repeat(2,1fr);margin-top:40px}
}
@media(max-width:560px){
.iqh-wrap,.iqh-stats,.iqh-marquee{padding:0 18px}
.iqh-title{font-size:clamp(30px,8.4vw,40px)}
.iqh-prompt{flex-direction:column}
.iqh-prompt .btn{width:100%}
.iqh-badge-2{display:none}
.iqh-stat{padding:16px 18px}
.iqh-stat b{font-size:22px}
}
@media(prefers-reduced-motion:reduce){
.iqh-orb,.iqh-badge,.iqh-m-hero::after{animation:none}
.iqh-mock{transform:none}
}
</style>
HTML;
    }

    private function js(): string
    {
        return <<<'HTML'
<script>if(!window.wwiHeroInit){window.wwiHeroInit=1;
window.wwiHeroPrompt=function(e){
    if(e&&e.preventDefault)e.preventDefault();
    var i=document.getElementById('iqh-prompt-input');
    var v=(i&&i.value||'').trim();
    if(!v){if(i)i.focus();return false}
    if(window.wwiFlowOpen){
        wwiFlowOpen();
        var fi=document.getElementById('flow-input');
        if(fi){fi.value=v;if(window.wwiFlowSend)wwiFlowSend()}
    }
    return false;
};
window.wwiHeroChip=function(el){
    var i=document.getElementById('iqh-prompt-input');
    if(i)i.value=el.getAttribute('data-prompt')||el.textContent;
    var m=document.getElementById('iqh-mock');
    var k=el.getAttribute('data-mock');
    if(m&&k)m.setAttribute('data-mock',k);
    document.querySelectorAll('.iqh-chip').forEach(function(c){c.classList.toggle('on',c===el)});
    if(window.wwiHeroPrompt)wwiHeroPrompt();
};
(function(){
    var pct=document.getElementById('iqh-pct');
    if(!pct)return;
    var calm=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if(calm)return;
    var v=78;
    setInterval(function(){v=v>=99?61:v+1;pct.textContent=v+'%'},1100);
})();
}</script>
HTML;
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $titleParts = explode('|', (string)$c['title']);
        $t1 = $this->esc(trim((string)($titleParts[0] ?? '')));
        $t2 = $this->esc(trim((string)($titleParts[1] ?? '')));

        $html = $this->assets();
        $html .= '<section class="iqh" id="iqh-hero">';
        $html .= '<div class="iqh-bg" aria-hidden="true"><div class="iqh-grid"></div><div class="iqh-orb iqh-orb-a"></div><div class="iqh-orb iqh-orb-b"></div></div>';

        $html .= '<div class="iqh-wrap">';
        $html .= '<div class="iqh-copy">';
        if ($c['badge']) {
            $html .= '<div class="iqh-eyebrow"><span class="iqh-dot"></span><span data-editable="badge">' . $this->esc($c['badge']) . '</span></div>';
        }
        $html .= '<h1 class="iqh-title" data-editable="title">' . $t1 . ($t2 ? ' <span class="gradient-text">' . $t2 . '</span>' : '') . '</h1>';
        $html .= '<p class="iqh-sub" data-editable="subtitle">' . $this->esc($c['subtitle']) . '</p>';

        $html .= '<form class="iqh-prompt" onsubmit="return wwiHeroPrompt(event)">';
        $html .= '<input id="iqh-prompt-input" type="text" autocomplete="off" aria-label="Describe tu negocio" data-editable="prompt_placeholder" placeholder="' . $this->esc($c['prompt_placeholder']) . '"/>';
        $html .= '<button class="btn btn-primary" type="submit" data-editable="prompt_cta">' . $this->esc($c['prompt_cta']) . '</button>';
        $html .= '</form>';

        $chips = $this->safeJson($c['chips'] ?? []);
        if ($chips) {
            $html .= '<div class="iqh-chips">';
            foreach ($chips as $i => $ch) {
                if (!is_array($ch)) continue;
                $label = trim((string)($ch['label'] ?? ''));
                if ($label === '') continue;
                $mock = (string)($ch['mock'] ?? '');
                $html .= '<button type="button" class="iqh-chip" data-mock="' . $this->esc($mock) . '" data-prompt="' . $this->esc((string)($ch['prompt'] ?? $label)) . '" data-editable="chips.' . $i . '.label" onclick="wwiHeroChip(this)">' . $this->esc($label) . '</button>';
            }
            $html .= '</div>';
        }

        $html .= '<div class="iqh-ctas">';
        $html .= '<a class="btn btn-primary btn-pulse" data-editable="cta_primary" id="wwi-start" style="padding:14px 30px;font-size:15px" href="' . $this->esc($c['cta_primary_url']) . '">' . $this->esc($c['cta_primary']) . '</a>';
        if ($c['cta_secondary']) {
            $html .= '<a class="btn btn-ghost" data-editable="cta_secondary" style="padding:14px 24px;font-size:14px" href="' . $this->esc($c['cta_secondary_url']) . '">' . $this->esc($c['cta_secondary']) . '</a>';
        }
        $html .= '</div>';
        if ($c['price_note']) $html .= '<div class="iqh-micro mono" data-editable="price_note">' . $this->esc($c['price_note']) . '</div>';

        $trust = $this->safeJson($c['trust'] ?? []);
        if ($trust) {
            $html .= '<div class="iqh-trust">';
            foreach ($trust as $i => $t) {
                $txt = is_array($t) ? (string)($t['text'] ?? '') : (string)$t;
                if ($txt !== '') $html .= '<span data-editable="trust.' . $i . '.text">' . $this->esc($txt) . '</span>';
            }
            $html .= '</div>';
        }

        if ($c['social_text']) {
            $html .= '<div class="iqh-social"><div class="iqh-avatars"><i>A</i><i>M</i><i>J</i><i>R</i><i>+</i></div><div data-editable="social_text">' . $this->esc($c['social_text']) . '</div></div>';
        }
        $html .= '</div>';

        $html .= '<div class="iqh-visual">';
        $html .= '<div class="iqh-mock" id="iqh-mock" data-mock="restaurant">';
        $html .= '<div class="iqh-mock-bar"><i></i><i></i><i></i><span class="iqh-url" data-editable="mock_url">' . $this->esc($c['mock_url']) . '</span></div>';
        $html .= '<div class="iqh-mock-body"><div class="iqh-m-hero"></div><div class="iqh-m-line"></div><div class="iqh-m-line short"></div><div class="iqh-m-cards"><i></i><i></i><i></i></div><div class="iqh-m-line tiny"></div></div>';
        $html .= '<div class="iqh-building"><span class="iqh-spin"></span> TIA construyendo tu sitio <b id="iqh-pct">78%</b></div>';
        $html .= '</div>';
        $html .= '<div class="iqh-badge iqh-badge-1">🔒 SSL incluido</div>';
        $html .= '<div class="iqh-badge iqh-badge-2">🌐 Dominio .com</div>';
        $html .= '<div class="iqh-badge iqh-badge-3">⚡ Online en 24h</div>';
        $html .= '</div>';
        $html .= '</div>';

        $stats = $this->safeJson($c['stats'] ?? []);
        if ($stats) {
            $html .= '<div class="iqh-stats">';
            foreach ($stats as $i => $st) {
                if (!is_array($st)) continue;
                $val = (string)($st['value'] ?? '');
                $attrs = '';
                if (preg_match('/^(\d+)(.*)$/u', $val, $m)) $attrs = ' data-count="' . $m[1] . '" data-suffix="' . $this->esc($m[2]) . '"';
                $html .= '<div class="iqh-stat"><b' . $attrs . ' data-editable="stats.' . $i . '.value">' . $this->esc($val) . '</b><span data-editable="stats.' . $i . '.label">' . $this->esc((string)($st['label'] ?? '')) . '</span></div>';
            }
            $html .= '</div>';
        }

        $sectors = [];
        foreach ($this->safeJson($c['sectors'] ?? []) as $sec) {
            if (is_array($sec)) $sec = (string)($sec['name'] ?? '');
            $sec = trim((string)$sec);
            if ($sec !== '') $sectors[] = $sec;
        }
        if ($sectors) {
            $sectorCount = count($sectors);
            $sectorIcon = (string)($c['sector_icon'] ?? '◆');
            $html .= '<div class="iqh-marquee"><div class="marquee"><div class="track">';
            foreach (array_merge($sectors, $sectors) as $i => $s) {
                $ed = $i < $sectorCount ? ' data-editable="sectors.' . $i . '.name"' : ' aria-hidden="true"';
                $iconEd = $i === 0 ? ' data-editable="sector_icon"' : '';
                $html .= '<span' . $ed . '><b' . $iconEd . '>' . $this->esc($sectorIcon) . '</b> ' . $this->esc($s) . '</span>';
            }
            $html .= '</div></div></div>';
        }

        $html .= '</section>';
        $html .= $this->js();
        return $html;
    }
}
