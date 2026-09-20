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
.iqb{border:1px solid var(--border2);border-radius:18px;background:var(--panel);backdrop-filter:blur(14px);overflow:hidden;box-shadow:0 40px 100px rgba(0,0,0,.4);transform:rotateY(-7deg) rotateX(2.5deg);transition:transform .5s cubic-bezier(.22,1,.36,1);position:relative}
.iqb:hover{transform:none}
.iqb-chrome{display:flex;align-items:center;gap:6px;padding:10px 13px;border-bottom:1px solid var(--border);background:var(--panel2)}
.iqb-chrome i{width:9px;height:9px;border-radius:50%;background:var(--border2)}
.iqb-chrome i:nth-child(1){background:#ff5f57}.iqb-chrome i:nth-child(2){background:#febc2e}.iqb-chrome i:nth-child(3){background:#28c840}
.iqb-url{margin-left:8px;flex:1;font-family:'JetBrains Mono',Consolas,monospace;font-size:10.5px;color:var(--muted);background:var(--bg);border:1px solid var(--border);border-radius:7px;padding:4px 10px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.iqb-live{font-family:'JetBrains Mono',Consolas,monospace;font-size:10px;color:var(--accent2);border:1px solid var(--border2);border-radius:999px;padding:3px 8px;white-space:nowrap}
.iqb-body{display:grid;grid-template-columns:1fr 138px;min-height:296px}
.iqb-canvas{padding:14px 12px 10px;display:flex;flex-direction:column;gap:9px;max-height:346px;overflow-y:auto;scrollbar-width:thin;transition:max-width .3s ease}
.iqb-canvas::-webkit-scrollbar{width:7px}
.iqb-canvas::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px}
.iqb-canvas[data-device="tablet"]{max-width:76%;margin:0 auto}
.iqb-canvas[data-device="mobile"]{max-width:42%;margin:0 auto}
.iqb-sec{position:relative;border:1px dashed transparent;border-radius:10px;padding:7px;transition:border-color .15s,background .15s;cursor:grab}
.iqb-sec:hover,.iqb-sec.on{border-color:var(--accent2);background:rgba(124,60,255,.07)}
.iqb-sec.dragging{opacity:.4}
.iqb-sec.hidden-sec{opacity:.35}
.iqb-sec-tools{position:absolute;top:-13px;left:8px;display:none;align-items:center;gap:2px;background:var(--panel);border:1px solid var(--border2);border-radius:8px;padding:2px;z-index:6;box-shadow:0 10px 24px rgba(0,0,0,.35)}
.iqb-sec:hover .iqb-sec-tools,.iqb-sec.on .iqb-sec-tools{display:flex}
.iqb-sec-tools button{width:20px;height:20px;border:none;background:none;color:var(--muted);cursor:pointer;font-size:11px;border-radius:5px;line-height:1}
.iqb-sec-tools button:hover{background:var(--panel2);color:var(--text)}
.iqb-sec-tools .lb{font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:.07em;color:var(--accent2);padding:0 6px}
.iqb-wire{border-radius:8px;overflow:hidden;background:rgba(255,255,255,.045);border:1px solid var(--border);padding:8px;display:flex;flex-direction:column;gap:6px}
.iqb-wire .r{display:flex;gap:6px}
.iqb-wire .b{border-radius:5px;background:var(--panel2);border:1px solid var(--border);flex:1;min-height:10px}
.iqb-wire .b.acc{background:linear-gradient(120deg,rgba(124,60,255,.5),rgba(84,190,255,.35));border-color:transparent}
.iqb-wire .b.tall{min-height:30px}
.iqb-wire .b.line{min-height:7px;flex:1}
.iqb-wire .b.line.short{flex:.6}
.iqb-wire .b.sq{min-height:34px}
.iqb-wire .b.ctr{max-width:56%;margin:0 auto}
.iqb-wire .cap{font-size:8.5px;color:var(--muted);text-align:center;font-family:'JetBrains Mono',Consolas,monospace}
.iqb-palette{border-left:1px solid var(--border);background:var(--panel2);padding:10px 9px;display:flex;flex-direction:column;gap:6px;overflow-y:auto;max-height:346px}
.iqb-palette-head{font-size:9.5px;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);margin-bottom:2px}
.iqb-brick{display:flex;align-items:center;gap:7px;border:1px solid var(--border);border-radius:9px;background:var(--panel);color:var(--text);font:inherit;font-size:11px;padding:6px 8px;cursor:grab;text-align:left;transition:.15s}
.iqb-brick:hover{border-color:var(--accent2);transform:translateY(-1px)}
.iqb-brick .ic{width:18px;height:18px;border-radius:5px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-size:10px;flex-shrink:0}
.iqb-bar{display:flex;align-items:center;gap:6px;padding:9px 12px;border-top:1px solid var(--border);background:var(--panel2);flex-wrap:wrap}
.iqb-t{width:26px;height:26px;border-radius:8px;border:1px solid var(--border);background:var(--panel);color:var(--muted);cursor:pointer;font-size:12px;line-height:1}
.iqb-t:hover,.iqb-t.on{color:var(--text);border-color:var(--accent2)}
.iqb-t:disabled{opacity:.4;cursor:not-allowed}
.iqb-sep{width:1px;height:18px;background:var(--border)}
.iqb-tpl{border:1px solid var(--border);border-radius:8px;background:var(--panel);color:var(--muted);font:inherit;font-size:11px;padding:5px 6px;cursor:pointer}
.iqb-stat{margin-left:auto;font-family:'JetBrains Mono',Consolas,monospace;font-size:10.5px;color:var(--muted)}
.iqb-use{border:none;border-radius:9px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font:inherit;font-size:11.5px;font-weight:700;padding:7px 12px;cursor:pointer}
.iqb-use:hover{filter:brightness(1.08)}
.iqb-toast{position:absolute;left:50%;bottom:54px;transform:translateX(-50%) translateY(8px);background:rgba(10,10,20,.94);border:1px solid var(--border2);color:var(--text);font-size:11.5px;border-radius:999px;padding:7px 15px;opacity:0;transition:.25s;pointer-events:none;display:flex;gap:9px;align-items:center;z-index:8;white-space:nowrap}
.iqb-toast.show{opacity:1;transform:translateX(-50%)}
.iqb-toast button{background:none;border:none;color:var(--accent2);font:inherit;font-size:11.5px;font-weight:800;cursor:pointer;padding:0}
.iqb-drop{height:3px;border-radius:2px;background:var(--accent2);box-shadow:0 0 12px var(--accent2);margin:-4px 2px}
.iqb-empty{padding:30px 14px;text-align:center;color:var(--muted);font-size:11.5px;border:1px dashed var(--border2);border-radius:10px}
.iqb-nav{position:sticky;top:0;z-index:6;display:flex;align-items:center;gap:10px;padding:8px 10px;margin:-14px -12px 10px;background:var(--panel2);border-bottom:1px solid var(--border);backdrop-filter:blur(8px);font-size:10.5px}
.iqb-nav b{font-size:11px;font-weight:800;letter-spacing:-.01em;white-space:nowrap}
.iqb-nav-links{display:flex;gap:9px;flex:1;overflow:hidden}
.iqb-nav-links a{color:var(--muted);text-decoration:none;white-space:nowrap}
.iqb-nav-links a:hover{color:var(--text)}
.iqb-nav-b{border:0;border-radius:7px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font:inherit;font-size:10px;font-weight:700;padding:5px 9px;cursor:pointer;white-space:nowrap}
.iqb-sec-body{border-radius:10px;overflow:hidden}
.iqb-s{padding:16px 14px;background:var(--panel);border-bottom:1px solid var(--border);font-size:11px}
.iqb-s h4{margin:0 0 10px;font-size:12.5px;font-weight:800;letter-spacing:-.01em}
.iqb-s p{margin:0;color:var(--muted);line-height:1.5}
.iqb-s-hero{padding:22px 16px;background:linear-gradient(140deg,rgba(124,60,255,.22),rgba(84,190,255,.12));border-bottom:1px solid var(--border)}
.iqb-s-tag{display:inline-block;font-size:9px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:var(--accent2);border:1px solid var(--border2);border-radius:999px;padding:2px 8px;margin-bottom:8px}
.iqb-s-hero h3{margin:0 0 6px;font-size:17px;font-weight:800;line-height:1.2;letter-spacing:-.02em}
.iqb-s-hero p{font-size:11px;color:var(--muted);max-width:280px;margin-bottom:12px}
.iqb-s-btns{display:flex;gap:7px;flex-wrap:wrap}
.iqb-s-b{border:0;border-radius:8px;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font:inherit;font-size:10.5px;font-weight:700;padding:7px 12px;cursor:pointer;text-decoration:none;display:inline-block}
.iqb-s-b.ghost{background:transparent;border:1px solid var(--border2);color:var(--text)}
.iqb-s-cards{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.iqb-s-card{border:1px solid var(--border);border-radius:9px;padding:9px;background:var(--panel2);display:flex;flex-direction:column;gap:3px;position:relative}
.iqb-s-card.feat{border-color:var(--accent2);box-shadow:0 0 0 1px rgba(183,140,255,.3)}
.iqb-s-card i{font-style:normal;font-size:13px;color:var(--accent2)}
.iqb-s-card b{font-size:10.5px}
.iqb-s-card span{font-size:9.5px;color:var(--muted);line-height:1.4}
.iqb-s-card .pr{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:800}
.iqb-s-card .pr small{font-size:9px;color:var(--muted);font-weight:500}
.iqb-s-card .iqb-s-b{margin-top:6px;font-size:9.5px;padding:5px 8px;text-align:center}
.iqb-s-gal{display:grid;grid-template-columns:repeat(3,1fr);gap:6px}
.iqb-s-gal img{width:100%;height:56px;object-fit:cover;border-radius:7px;display:block}
.iqb-s-video{position:relative;border-radius:9px;overflow:hidden}
.iqb-s-video img{width:100%;height:96px;object-fit:cover;display:block}
.iqb-s-play{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(6,6,16,.35);border:0;color:#fff;font-size:22px;cursor:pointer}
.iqb-s-q{display:flex;align-items:center;gap:6px;margin-bottom:5px}
.iqb-s-q i{width:22px;height:22px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent2));color:#fff;font-style:normal;font-size:9px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.iqb-s-q b{display:block;font-size:10px}
.iqb-s-q span{font-size:9px;color:var(--muted)}
.iqb-s-card p{font-size:10px;font-style:italic}
.iqb-s-faq .iqb-s-f{border:1px solid var(--border);border-radius:8px;padding:8px 10px;margin-bottom:6px;cursor:pointer;background:var(--panel2)}
.iqb-s-faq .iqb-s-f b{display:flex;justify-content:space-between;align-items:center;font-size:10.5px}
.iqb-s-faq .iqb-s-f i{font-style:normal;color:var(--accent2)}
.iqb-s-faq .iqb-s-f p{display:none;margin-top:6px;font-size:10px}
.iqb-s-faq .iqb-s-f.open p{display:block}
.iqb-s-faq .iqb-s-f.open i{transform:rotate(45deg)}
.iqb-s-contact{display:grid;grid-template-columns:1.2fr 1fr;gap:10px}
.iqb-s-fields{display:flex;flex-direction:column;gap:5px}
.iqb-s-fields input,.iqb-s-fields textarea{border:1px solid var(--border);border-radius:7px;background:var(--bg);color:var(--muted);font:inherit;font-size:10px;padding:6px 8px;pointer-events:none}
.iqb-s-fields textarea{min-height:38px;resize:none}
.iqb-s-contact ul{margin:0;padding-left:14px;font-size:10px;color:var(--muted);line-height:1.7}
.iqb-s-cta{display:flex;align-items:center;justify-content:space-between;gap:10px;background:linear-gradient(120deg,rgba(124,60,255,.2),rgba(84,190,255,.12))}
.iqb-s-cta b{font-size:12px}
.iqb-s-footer{display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:var(--panel2)}
.iqb-s-footer b{display:block;font-size:11px}
.iqb-s-footer span{font-size:9.5px;color:var(--muted)}
.iqb-s-links{display:flex;gap:8px;margin-left:auto}
.iqb-s-links a{font-size:9.5px;color:var(--muted);text-decoration:none}
.iqb-s-links a:hover{color:var(--text)}
.iqb-s-copy{width:100%;font-size:9px;opacity:.7}
.iqb-sec.iqb-flash{animation:iqbFlash 1.4s ease}
@keyframes iqbFlash{0%,100%{box-shadow:none}30%{box-shadow:0 0 0 2px var(--accent2),0 0 30px rgba(183,140,255,.5)}}
.iqb-new{animation:iqbIn .35s ease}
.iqb-live{position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0);white-space:nowrap}
@keyframes iqbIn{from{opacity:0;transform:translateY(-7px)}to{opacity:1;transform:none}}
@media(max-width:1020px){
.iqb-body{grid-template-columns:1fr}
.iqb-palette{border-left:none;border-top:1px solid var(--border);flex-direction:row;flex-wrap:wrap;max-height:none;overflow-x:auto}
.iqb-palette-head{display:none}
.iqb-brick{white-space:nowrap}
.iqb-stat{margin-left:0}
}
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
.iqh-orb,.iqh-badge{animation:none}
.iqh-mock{transform:none}
.iqb,.iqb-new{animation:none;transition:none}
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
    document.querySelectorAll('.iqh-chip').forEach(function(c){c.classList.toggle('on',c===el)});
    var k=el.getAttribute('data-mock');
    if(k&&window.__iqbSetSector)window.__iqbSetSector(k);
    if(window.wwiHeroPrompt)wwiHeroPrompt();
};
(function(){
    var root=document.getElementById('iqb');
    if(!root||root.__init)return;root.__init=1;
    var U='https://images.unsplash.com/photo-';
    var PACKS={
        restaurant:{name:'Sabor Local',tag:'Cocina artesanal',hero:'El autentico sabor del pan artesanal',sub:'Masa madre, hornada diaria y cafe de origen en el corazon de Salento.',cta:'Reservar mesa',
            services:[['\u25C6','Panaderia','Masa madre horneada cada manana'],['\u2615','Cafeteria','Cafe de origen y reposteria'],['\u25CE','Eventos','Catering para tus celebraciones']],
            gallery:[U+'1509440159596-0249088772ff?w=600&q=70',U+'1504674900247-0877df9cc836?w=600&q=70',U+'1517248135467-4c7edcad34c4?w=600&q=70'],
            plans:[['Basico','29','3 productos'],['Popular','59','10 productos'],['Premium','99','Catalogo completo']],
            quotes:[['Ana M.','Cliente frecuente','El mejor pan de la ciudad, sin duda'],['Luis R.','Chef','Ingredientes impecables y frescos']],
            faq:[['Hacen domicilios?','Si, entregamos en 30 minutos en el centro.'],['Tienen opciones sin gluten?','Si, horneamos en area separada.']],
            contact:['Calle 12 #4-56, Salento','+57 300 123 4567','hola@saborlocal.co'],
            cta:'Listo para probar nuestro pan?',ctaBtn:'Pedir ahora'},
        law:{name:'Estudio Juridico',tag:'Asesoria legal',hero:'Defendemos tus derechos con precision',sub:'Consultas en linea, contratos y representacion con respuesta en 24 horas.',cta:'Agendar consulta',
            services:[['\u2696','Derecho civil','Contratos, sucesiones y familia'],['\u25A4','Derecho laboral','Despidos, liquidaciones y acuerdos'],['\u25C7','Empresas','Constitucion y cumplimiento']],
            gallery:[U+'1589829545856-d10d557cf95f?w=600&q=70',U+'1521791136064-7986c2920216?w=600&q=70',U+'1450101499163-c8848c66ca85?w=600&q=70'],
            plans:[['Consulta','49','1 hora de asesoria'],['Contrato','199','Revision y firma'],['Representacion','499','Caso completo']],
            quotes:[['Marta G.','Cliente','Resolvieron mi caso en dos semanas'],['Jorge P.','Empresa','Claridad y transparencia total']],
            faq:[['La primera consulta tiene costo?','No, la primera orientacion es gratuita.'],['Atienden en linea?','Si, por videollamada y correo.']],
            contact:['Av. 6 #10-22, Bogota','+57 601 555 0123','contacto@estudiojuridico.co'],
            cta:'Necesitas asesoria hoy?',ctaBtn:'Hablar con un abogado'},
        shop:{name:'Tienda Nova',tag:'Envio nacional',hero:'Productos que llegan a tu puerta',sub:'Catalogo curado, pago seguro y envios a todo el pais en 2 a 5 dias.',cta:'Comprar ahora',
            services:[['\u2605','Destacados','Lo mas vendido del mes'],['\u21BB','Devoluciones','30 dias sin preguntas'],['\u2708','Envio gratis','En compras sobre $150.000']],
            gallery:[U+'1441986300917-64674bd600d8?w=600&q=70',U+'1472851294608-062f824d29cc?w=600&q=70',U+'1560343090-f0409e92791a?w=600&q=70'],
            plans:[['Basico','29','3 productos'],['Popular','59','10 productos'],['Premium','99','Catalogo completo']],
            quotes:[['Sofia L.','Compradora','Llego en dos dias y perfecto'],['Andres C.','Cliente','La tienda mas facil de usar']],
            faq:[['Cuanto tarda el envio?','Entre 2 y 5 dias habiles.'],['Puedo pagar contra entrega?','Si, en ciudades principales.']],
            contact:['Cra 7 #45-10, Medellin','+57 310 888 2211','ventas@tiendanova.co'],
            cta:'Tu pedido esta a un clic',ctaBtn:'Ver catalogo'},
        fitness:{name:'Pulse Fitness',tag:'Entrena con proposito',hero:'Transforma tu cuerpo en 12 semanas',sub:'Planes personalizados, seguimiento y comunidad que te empuja a mas.',cta:'Probar gratis',
            services:[['\u26A1','Funcional','Fuerza y movilidad en grupo'],['\u2665','Cardio','Quema y resistencia guiada'],['\u25CE','Nutricion','Plan segun tu objetivo']],
            gallery:[U+'1534438327276-14e5300c3a48?w=600&q=70',U+'1517836357463-d25dfeac3438?w=600&q=70',U+'1544367567-0f2fcb009e0b?w=600&q=70'],
            plans:[['Basico','39','Acceso libre'],['Popular','69','Clases + plan'],['Premium','119','Coach personal']],
            quotes:[['Camilo R.','Miembro','Baje 8 kilos en 3 meses'],['Valeria T.','Miembro','El mejor ambiente para entrenar']],
            faq:[['Hay clase de prueba?','Si, una semana completa gratis.'],['Necesito experiencia?','No, adaptamos el plan a tu nivel.']],
            contact:['Calle 50 #12-30, Cali','+57 315 222 3344','hola@pulsefitness.co'],
            cta:'Empieza hoy tu cambio',ctaBtn:'Reservar clase'},
        default:{name:'Mi Negocio',tag:'Bienvenido',hero:'Tu negocio online, listo hoy',sub:'Cuentale a TIA sobre tu negocio y mira tu sitio en minutos.',cta:'Empezar',
            services:[['\u25C6','Servicio 1','Describe tu propuesta de valor'],['\u25A4','Servicio 2','Explica el beneficio principal'],['\u25CE','Servicio 3','Muestra el resultado que entregas']],
            gallery:[U+'1522199755839-a2bacb67c546?w=600&q=70',U+'1467232004584-a241de8bcf5d?w=600&q=70',U+'1551434678-e076c223a692?w=600&q=70'],
            plans:[['Basico','29','Esencial'],['Popular','59','Recomendado'],['Premium','99','Completo']],
            quotes:[['Cliente A.','Sector','Un servicio excelente'],['Cliente B.','Sector','Los recomiendo siempre']],
            faq:[['Pregunta frecuente 1','Respuesta clara y util.'],['Pregunta frecuente 2','Respuesta clara y util.']],
            contact:['Tu direccion','+57 300 000 0000','hola@minegocio.co'],
            cta:'Listo para empezar?',ctaBtn:'Contactar'}
    };
    var LABELS={hero:'Hero',features:'Servicios',gallery:'Galeria',video:'Video',pricing:'Precios',testimonials:'Testimonios',faq:'FAQ',contact:'Contacto',cta:'CTA',footer:'Footer'};
    var ICONS={hero:'\u25A3',features:'\u25A4',gallery:'\u25A6',video:'\u25B6',pricing:'$',testimonials:'\u275D',faq:'?',contact:'\u2709',cta:'\u279C',footer:'\u25AC'};
    var TEMPLATES={
        restaurant:['hero','features','gallery','testimonials','pricing','contact','footer'],
        portfolio:['hero','gallery','features','testimonials','cta','footer'],
        shop:['hero','gallery','pricing','faq','contact','footer'],
        agency:['hero','features','testimonials','pricing','faq','contact','footer']
    };
    var DEFAULT=[{type:'hero'},{type:'features'},{type:'gallery'},{type:'pricing'},{type:'contact'},{type:'footer'}];
    var sector='restaurant';
    var canvas=document.getElementById('iqb-canvas');
    var palette=document.getElementById('iqb-palette');
    var stat=document.getElementById('iqb-stat');
    var toast=document.getElementById('iqb-toast');
    var live=document.getElementById('iqb-live');
    var pct=document.getElementById('iqh-pct');
    var state=[],hist=[],hi=-1,sel=-1,dragIdx=-1,dragType='';
    function snap(){return JSON.parse(JSON.stringify(state))}
    function push(){hist=hist.slice(0,hi+1);hist.push(snap());if(hist.length>40)hist.shift();hi=hist.length-1}
    function save(){try{localStorage.setItem('wwi_hero_layout',JSON.stringify(state))}catch(e){}}
    function load(){
        try{
            var s=JSON.parse(localStorage.getItem('wwi_hero_layout')||'null');
            if(s&&s.length)state=s.map(function(x){return typeof x==='string'?{type:x}:x});
        }catch(e){}
        if(!state.length)state=DEFAULT.slice();
    }
    function say(t){if(live)live.textContent=t}
    function score(){
        var types={},n=state.filter(function(s){return !s.hidden}).length;
        state.forEach(function(s){types[s.type]=1});
        var v=Object.keys(types).length;
        return Math.max(35,Math.min(100,55+n*5+v*4));
    }
    function toastMsg(t,undo){
        if(!toast)return;
        toast.innerHTML=t+(undo?' <button type="button" id="iqb-undo">Deshacer</button>':'');
        toast.classList.add('show');
        var b=document.getElementById('iqb-undo');
        if(b)b.addEventListener('click',function(){undoFn();toast.classList.remove('show')});
        clearTimeout(root.__tt);
        root.__tt=setTimeout(function(){toast.classList.remove('show')},undo?4200:1800);
    }
    function sec(type,pack){
        var p=pack||PACKS[sector]||PACKS.default;
        var id='iqb-s-'+type;
        if(type==='hero'){
            return '<section class="iqb-s iqb-s-hero" data-sec="hero" id="'+id+'"><span class="iqb-s-tag">'+p.tag+'</span><h3>'+p.hero+'</h3><p>'+p.sub+'</p><div class="iqb-s-btns"><button type="button" class="iqb-s-b" data-wizard="1">'+p.cta+'</button><a class="iqb-s-b ghost" href="#'+id+'" data-scroll="#iqb-s-features">Ver servicios</a></div></section>';
        }
        if(type==='features'){
            return '<section class="iqb-s" data-sec="features" id="'+id+'"><h4>Servicios</h4><div class="iqb-s-cards">'
                +p.services.map(function(s){return '<div class="iqb-s-card"><i>'+s[0]+'</i><b>'+s[1]+'</b><span>'+s[2]+'</span></div>'}).join('')
                +'</div></section>';
        }
        if(type==='gallery'){
            return '<section class="iqb-s" data-sec="gallery" id="'+id+'"><h4>Galeria</h4><div class="iqb-s-gal">'
                +p.gallery.map(function(u,i){return '<img src="'+u+'" alt="Trabajo '+(i+1)+'" loading="lazy"/>'}).join('')
                +'</div></section>';
        }
        if(type==='video'){
            return '<section class="iqb-s" data-sec="video" id="'+id+'"><h4>Video</h4><div class="iqb-s-video"><img src="'+p.gallery[0]+'" alt="Video" loading="lazy"/><button type="button" class="iqb-s-play" data-wizard="1" aria-label="Reproducir video">\u25B6</button></div></section>';
        }
        if(type==='pricing'){
            return '<section class="iqb-s" data-sec="pricing" id="'+id+'"><h4>Precios</h4><div class="iqb-s-cards">'
                +p.plans.map(function(pl,i){return '<div class="iqb-s-card'+(i===1?' feat':'')+'"><b>'+pl[0]+'</b><div class="pr">$'+pl[1]+'<small>/mes</small></div><span>'+pl[2]+'</span><button type="button" class="iqb-s-b" data-wizard="1">Elegir</button></div>'}).join('')
                +'</div></section>';
        }
        if(type==='testimonials'){
            return '<section class="iqb-s" data-sec="testimonials" id="'+id+'"><h4>Testimonios</h4><div class="iqb-s-cards">'
                +p.quotes.map(function(q){return '<div class="iqb-s-card"><div class="iqb-s-q"><i>'+q[0].charAt(0)+'</i><div><b>'+q[0]+'</b><span>'+q[1]+'</span></div></div><p>\u201C'+q[2]+'\u201D</p></div>'}).join('')
                +'</div></section>';
        }
        if(type==='faq'){
            return '<section class="iqb-s" data-sec="faq" id="'+id+'"><h4>Preguntas frecuentes</h4><div class="iqb-s-faq">'
                +p.faq.map(function(f){return '<div class="iqb-s-f" data-faq="1"><b>'+f[0]+'<i>+</i></b><p>'+f[1]+'</p></div>'}).join('')
                +'</div></section>';
        }
        if(type==='contact'){
            return '<section class="iqb-s" data-sec="contact" id="'+id+'"><h4>Contacto</h4><div class="iqb-s-contact"><div class="iqb-s-fields">'
                +'<input type="text" placeholder="Tu nombre" readonly tabindex="-1"/><input type="email" placeholder="tu@email.com" readonly tabindex="-1"/><textarea placeholder="Cuentanos que necesitas" readonly tabindex="-1"></textarea><button type="button" class="iqb-s-b" data-wizard="1">Enviar mensaje</button>'
                +'</div><ul>'+p.contact.map(function(c){return '<li>'+c+'</li>'}).join('')+'</ul></div></section>';
        }
        if(type==='cta'){
            return '<section class="iqb-s iqb-s-cta" data-sec="cta" id="'+id+'"><b>'+p.cta+'</b><button type="button" class="iqb-s-b" data-wizard="1">'+p.ctaBtn+'</button></section>';
        }
        if(type==='footer'){
            return '<section class="iqb-s iqb-s-footer" data-sec="footer" id="'+id+'"><div><b>'+p.name+'</b><span>'+p.tag+'</span></div><div class="iqb-s-links"><a href="#iqb-s-features" data-scroll="#iqb-s-features">Servicios</a><a href="#iqb-s-gallery" data-scroll="#iqb-s-gallery">Galeria</a><a href="#iqb-s-contact" data-scroll="#iqb-s-contact">Contacto</a></div><span class="iqb-s-copy">\u00A9 '+new Date().getFullYear()+' '+p.name+'</span></section>';
        }
        return '';
    }
    function navHtml(){
        var p=PACKS[sector]||PACKS.default;
        var links=[];
        if(state.some(function(s){return s.type==='features'&&!s.hidden}))links.push(['#iqb-s-features','Servicios']);
        if(state.some(function(s){return s.type==='gallery'&&!s.hidden}))links.push(['#iqb-s-gallery','Galeria']);
        if(state.some(function(s){return s.type==='pricing'&&!s.hidden}))links.push(['#iqb-s-pricing','Precios']);
        if(state.some(function(s){return s.type==='contact'&&!s.hidden}))links.push(['#iqb-s-contact','Contacto']);
        return '<nav class="iqb-nav"><b>'+p.name+'</b><span class="iqb-nav-links">'
            +links.map(function(l){return '<a href="'+l[0]+'" data-scroll="'+l[0]+'">'+l[1]+'</a>'}).join('')
            +'</span><button type="button" class="iqb-nav-b" data-wizard="1">'+p.ctaBtn+'</button></nav>';
    }
    function render(newIdx){
        if(!canvas)return;
        if(!state.length){
            canvas.innerHTML='<div class="iqb-empty">Tu sitio esta vacio. Haz clic en una seccion de la derecha para agregarla.</div>';
        }else{
            var pack=PACKS[sector]||PACKS.default;
            canvas.innerHTML=navHtml()+state.map(function(s,idx){
                var label=LABELS[s.type]||s.type;
                return '<div class="iqb-sec'+(idx===sel?' on':'')+(s.hidden?' hidden-sec':'')+(idx===newIdx?' iqb-new':'')+'" data-idx="'+idx+'" data-type="'+s.type+'" draggable="true" role="listitem" tabindex="0" aria-label="Seccion '+label+'">'
                    +'<div class="iqb-sec-tools"><span class="lb">'+label+'</span>'
                    +'<button type="button" data-act="up" title="Subir" aria-label="Subir">\u2191</button>'
                    +'<button type="button" data-act="down" title="Bajar" aria-label="Bajar">\u2193</button>'
                    +'<button type="button" data-act="dup" title="Duplicar" aria-label="Duplicar">\u29C9</button>'
                    +'<button type="button" data-act="hide" title="'+(s.hidden?'Mostrar':'Ocultar')+'" aria-label="Ocultar">'+(s.hidden?'\u25CC':'\u{1F441}')+'</button>'
                    +'<button type="button" data-act="del" title="Eliminar" aria-label="Eliminar">\u{1F5D1}</button>'
                    +'</div><div class="iqb-sec-body">'+sec(s.type,pack)+'</div></div>';
            }).join('');
        }
        if(stat)stat.textContent=state.length+' secciones \u00B7 score '+score();
        if(pct)pct.textContent=score()+'%';
        save();
        try{root.dispatchEvent(new CustomEvent('iqb:change',{detail:{sections:state.slice(),sector:sector}}))}catch(e){}
    }
    function setSector(k){
        if(!PACKS[k])return;
        sector=k;
        render();
        say('Contenido actualizado: '+PACKS[k].name);
    }
    window.__iqbSetSector=setSector;
    function scrollToEl(el,offset){
        if(!el)return;
        var top=el.getBoundingClientRect().top-canvas.getBoundingClientRect().top+canvas.scrollTop-(offset||54);
        try{canvas.scrollTo({top:top,behavior:'smooth'})}catch(e){canvas.scrollTop=top}
    }
    function highlight(type){
        var el=canvas.querySelector('.iqb-sec[data-type="'+type+'"]');
        if(!el){add(type,state.length);return}
        scrollToEl(el,60);
        el.classList.add('iqb-flash');
        setTimeout(function(){el.classList.remove('iqb-flash')},1400);
        say((LABELS[type]||type)+' ya esta en tu sitio');
        toastMsg('Ya esta en tu sitio \u2014 usa Shift+clic para duplicar');
    }
    function add(type,at){
        state.splice(at,0,{type:type});
        sel=at;push();render(at);say('Seccion '+((LABELS[type]||type))+' agregada');
        toastMsg('Brick a\u00F1adido');
    }
    function undoFn(){if(hi>0){hi--;state=JSON.parse(JSON.stringify(hist[hi]));render();toastMsg('Deshecho')}}
    function redoFn(){if(hi<hist.length-1){hi++;state=JSON.parse(JSON.stringify(hist[hi]));render();toastMsg('Rehecho')}}
    function reset(){state=DEFAULT.slice();sel=-1;push();render();toastMsg('Layout reiniciado')}
    if(palette){
        Object.keys(LABELS).forEach(function(k){
            var btn=document.createElement('button');
            btn.type='button';btn.className='iqb-brick';btn.draggable=true;btn.setAttribute('data-type',k);
            btn.innerHTML='<span class="ic">'+ICONS[k]+'</span>'+LABELS[k];
            btn.addEventListener('click',function(e){
                var exists=state.some(function(s){return s.type===k&&!s.hidden});
                if(exists&&!e.shiftKey){highlight(k);return}
                add(k,sel>=0?sel+1:state.length);
            });
            btn.addEventListener('dragstart',function(e){dragType=k;dragIdx=-1;if(e.dataTransfer){e.dataTransfer.effectAllowed='copy';try{e.dataTransfer.setData('text/plain',k)}catch(x){}}});
            btn.addEventListener('dragend',function(){dragType='';render()});
            palette.appendChild(btn);
        });
    }
    canvas.addEventListener('click',function(e){
        var sc=e.target.closest?e.target.closest('[data-scroll]'):null;
        if(sc){
            e.preventDefault();
            scrollToEl(canvas.querySelector(sc.getAttribute('data-scroll')),54);
            return;
        }
        var faq=e.target.closest?e.target.closest('[data-faq]'):null;
        if(faq){faq.classList.toggle('open');return}
        var wiz=e.target.closest?e.target.closest('[data-wizard]'):null;
        if(wiz){
            var pack=PACKS[sector]||PACKS.default;
            if(window.wwiFlowOpen){
                wwiFlowOpen();
                var fi=document.getElementById('flow-input');
                if(fi){fi.value='Quiero un sitio como '+pack.name+': '+pack.hero+'. '+pack.sub;if(window.wwiFlowSend)wwiFlowSend()}
            }
            return;
        }
        var t=e.target.closest?e.target.closest('button[data-act]'):null;
        var sec=e.target.closest?e.target.closest('.iqb-sec'):null;
        if(t&&sec){
            e.stopPropagation();
            var idx=parseInt(sec.getAttribute('data-idx'));
            var act=t.getAttribute('data-act');
            if(act==='up'&&idx>0){var a=state.splice(idx,1)[0];state.splice(idx-1,0,a);sel=idx-1;push();render()}
            else if(act==='down'&&idx<state.length-1){var b2=state.splice(idx,1)[0];state.splice(idx+1,0,b2);sel=idx+1;push();render()}
            else if(act==='dup'){state.splice(idx+1,0,{type:state[idx].type});sel=idx+1;push();render(idx+1);toastMsg('Seccion duplicada')}
            else if(act==='hide'){state[idx].hidden=!state[idx].hidden;push();render();toastMsg(state[idx].hidden?'Seccion oculta':'Seccion visible')}
            else if(act==='del'){
                state.splice(idx,1);sel=Math.min(idx,state.length-1);push();render();
                say('Seccion eliminada');toastMsg('Seccion eliminada',true);
            }
            return;
        }
        if(sec){sel=parseInt(sec.getAttribute('data-idx'));render();say('Seccion '+((LABELS[state[sel].type]||''))+' seleccionada')}
    });
    canvas.addEventListener('dragstart',function(e){
        var sec=e.target.closest?e.target.closest('.iqb-sec'):null;
        if(!sec)return;
        dragIdx=parseInt(sec.getAttribute('data-idx'));
        sec.classList.add('dragging');
        if(e.dataTransfer){e.dataTransfer.effectAllowed='move';try{e.dataTransfer.setData('text/plain','sec')}catch(x){}}
    });
    canvas.addEventListener('dragend',function(e){
        var sec=e.target.closest?e.target.closest('.iqb-sec'):null;
        if(sec)sec.classList.remove('dragging');
        dragIdx=-1;dragType='';
        render();
    });
    canvas.addEventListener('dragover',function(e){
        e.preventDefault();
        var sec=e.target.closest?e.target.closest('.iqb-sec'):null;
        var old=canvas.querySelector('.iqb-drop');if(old)old.remove();
        var ind=document.createElement('div');ind.className='iqb-drop';
        if(sec)sec.parentNode.insertBefore(ind,sec.nextSibling);else canvas.appendChild(ind);
    });
    canvas.addEventListener('dragleave',function(e){if(e.target===canvas){var d=canvas.querySelector('.iqb-drop');if(d)d.remove()}});
    canvas.addEventListener('drop',function(e){
        e.preventDefault();
        var d=canvas.querySelector('.iqb-drop');if(d)d.remove();
        var sec=e.target.closest?e.target.closest('.iqb-sec'):null;
        var at=sec?parseInt(sec.getAttribute('data-idx'))+1:state.length;
        if(dragType){add(dragType,at);dragType='';return}
        if(dragIdx>=0){
            var moved=state.splice(dragIdx,1)[0];
            if(dragIdx<at)at--;
            state.splice(at,0,moved);
            sel=at;push();render();toastMsg('Seccion movida');
        }
    });
    canvas.addEventListener('keydown',function(e){
        if(e.key==='ArrowDown'||e.key==='ArrowUp'){
            e.preventDefault();
            if(!state.length)return;
            sel=Math.max(0,Math.min(state.length-1,(sel<0?0:sel+(e.key==='ArrowDown'?1:-1))));
            render();
            var el=canvas.querySelector('.iqb-sec[data-idx="'+sel+'"]');if(el)el.focus();
        }else if(e.key==='Delete'||e.key==='Backspace'){
            if(sel>=0&&state[sel]){e.preventDefault();state.splice(sel,1);sel=Math.min(sel,state.length-1);push();render();toastMsg('Seccion eliminada',true)}
        }else if(e.key==='Enter'&&sel>=0){
            e.preventDefault();state.splice(sel+1,0,{type:state[sel].type});push();render(sel+1);
        }
    });
    root.addEventListener('keydown',function(e){
        if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='z'){e.preventDefault();if(e.shiftKey)redoFn();else undoFn()}
        else if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='y'){e.preventDefault();redoFn()}
    });
    root.querySelectorAll('.iqb-t[data-act]').forEach(function(b){
        b.addEventListener('click',function(){
            var a=b.getAttribute('data-act');
            if(a==='undo')undoFn();else if(a==='redo')redoFn();else if(a==='reset')reset();
        });
    });
    root.querySelectorAll('.iqb-dev').forEach(function(b){
        b.addEventListener('click',function(){
            root.querySelectorAll('.iqb-dev').forEach(function(x){x.classList.remove('on')});
            b.classList.add('on');
            canvas.setAttribute('data-device',b.getAttribute('data-dev'));
        });
    });
    var tpl=document.getElementById('iqb-tpl');
    if(tpl)tpl.addEventListener('change',function(){
        var t=tpl.value;if(!t||!TEMPLATES[t])return;
        state=TEMPLATES[t].map(function(x){return {type:x}});sel=-1;push();render();toastMsg('Plantilla aplicada');tpl.value='';
    });
    var use=document.getElementById('iqb-use');
    if(use)use.addEventListener('click',function(){
        var names=state.filter(function(s){return !s.hidden}).map(function(s){return LABELS[s.type]||s.type});
        var prompt='Quiero un sitio con estas secciones: '+names.join(', ')+'.';
        var i=document.getElementById('iqh-prompt-input');
        if(i)i.value=prompt;
        if(window.wwiHeroPrompt)wwiHeroPrompt({preventDefault:function(){}});
    });
    load();push();render();
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
        $html .= '<div class="iqb" id="iqb">';
        $html .= '<div class="iqb-chrome"><i></i><i></i><i></i><span class="iqb-url" data-editable="mock_url">' . $this->esc($c['mock_url']) . '</span><span class="iqb-live" id="iqh-pct">78%</span></div>';
        $html .= '<div class="iqb-body">';
        $html .= '<div class="iqb-canvas" id="iqb-canvas" data-device="desktop" role="list" aria-label="Secciones de la demo"></div>';
        $html .= '<div class="iqb-palette" id="iqb-palette" aria-label="Bricks disponibles"><div class="iqb-palette-head">Bricks · arrastra o haz clic</div></div>';
        $html .= '</div>';
        $html .= '<div class="iqb-bar">';
        $html .= '<button type="button" class="iqb-t" data-act="undo" title="Deshacer (Ctrl+Z)" aria-label="Deshacer">&#8630;</button>';
        $html .= '<button type="button" class="iqb-t" data-act="redo" title="Rehacer (Ctrl+Y)" aria-label="Rehacer">&#8631;</button>';
        $html .= '<button type="button" class="iqb-t" data-act="reset" title="Reiniciar" aria-label="Reiniciar">&#10226;</button>';
        $html .= '<span class="iqb-sep"></span>';
        $html .= '<button type="button" class="iqb-t iqb-dev on" data-dev="desktop" title="Escritorio" aria-label="Escritorio">&#9647;</button>';
        $html .= '<button type="button" class="iqb-t iqb-dev" data-dev="tablet" title="Tablet" aria-label="Tablet">&#9649;</button>';
        $html .= '<button type="button" class="iqb-t iqb-dev" data-dev="mobile" title="Movil" aria-label="Movil">&#9646;</button>';
        $html .= '<span class="iqb-sep"></span>';
        $html .= '<select class="iqb-tpl" id="iqb-tpl" aria-label="Plantillas rapidas"><option value="">Plantilla…</option><option value="restaurant">Restaurante</option><option value="portfolio">Portafolio</option><option value="shop">Tienda</option><option value="agency">Agencia</option></select>';
        $html .= '<span class="iqb-stat" id="iqb-stat">6 secciones</span>';
        $html .= '<button type="button" class="iqb-use" id="iqb-use">Usar esta estructura</button>';
        $html .= '</div>';
        $html .= '<div class="iqb-toast" id="iqb-toast" aria-hidden="true"></div>';
        $html .= '<span class="iqb-live" id="iqb-live" role="status" aria-live="polite"></span>';
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
