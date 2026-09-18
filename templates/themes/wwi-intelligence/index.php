<?php
use App\Core\Config;
use App\Core\Database;
use App\Services\CookieConsentService;
use App\Widgets\WidgetRegistry;

$page = $page ?? ['title' => Config::get('site_name', 'WWI'), 'meta_title' => '', 'meta_description' => '', 'slug' => ''];
$sections = $sections ?? [];

$wwiNav = [];
try {
    $navStmt = Database::instance()->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'wwi_nav' LIMIT 1");
    $navStmt->execute();
    $wwiNav = json_decode((string)$navStmt->fetchColumn(), true) ?: [];
} catch (\Throwable $e) {
    $wwiNav = [];
}
$wwiNav = array_merge([
    'brand' => 'WWI',
    'logo_letter' => 'W',
    'cta' => 'Crear mi sitio',
    'cta_url' => '#empezar',
    'links' => [
        ['label' => 'Planes', 'url' => '#planes'],
        ['label' => 'Beneficios', 'url' => '#beneficios'],
        ['label' => 'Plantillas', 'url' => '#plantillas'],
        ['label' => 'FAQ', 'url' => '#faq'],
    ],
], $wwiNav);
if (!is_array($wwiNav['links'] ?? null)) $wwiNav['links'] = [];
?>
<!DOCTYPE html>
<html lang="es" data-theme="dark">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width,initial-scale=1.0"/>
<script>try{if(localStorage.getItem('wwi_theme')==='light')document.documentElement.setAttribute('data-theme','light')}catch(e){}</script>
<title><?= htmlspecialchars($page['meta_title'] ?: $page['title']) ?></title>
<meta name="description" content="<?= htmlspecialchars($page['meta_description'] ?? '') ?>"/>
<link rel="preconnect" href="https://fonts.googleapis.com"/>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"/>
<style>
:root[data-theme='dark']{
    --bg:#070817;--bg2:#0d1024;
    --panel:rgba(255,255,255,.055);--panel2:rgba(255,255,255,.085);
    --border:rgba(255,255,255,.10);--border2:rgba(255,255,255,.16);
    --text:#f7f7ff;--muted:#a9acc5;
    --accent:#7c3cff;--accent2:#b78cff;
    --ok:#35d49a;--warn:#ffcc66;--bad:#ff667a;
    --glow:rgba(124,60,255,.42);--radius:18px;
    --nav-bg:rgba(7,8,23,.78);--soft:rgba(255,255,255,.04);--overlay:rgba(7,8,23,.86);
    --card-bg:rgba(255,255,255,.045);--shadow:0 24px 70px rgba(4,4,18,.55);
    color-scheme:dark;
}
:root[data-theme='light']{
    --bg:#f6f5ff;--bg2:#efeefb;
    --panel:rgba(255,255,255,.86);--panel2:rgba(255,255,255,.95);
    --border:rgba(20,16,60,.10);--border2:rgba(20,16,60,.18);
    --text:#15123a;--muted:#5d5a86;
    --accent:#6a2cff;--accent2:#8b5cf6;
    --ok:#0f9d6a;--warn:#b7791f;--bad:#d23c56;
    --glow:rgba(124,60,255,.22);--radius:18px;
    --nav-bg:rgba(246,245,255,.82);--soft:rgba(20,16,60,.03);--overlay:rgba(246,245,255,.92);
    --card-bg:rgba(255,255,255,.9);--shadow:0 24px 60px rgba(70,60,140,.14);
    color-scheme:light;
}
*{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;font-size:15px;line-height:1.6;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased;overflow-x:hidden}
::selection{background:rgba(124,60,255,.35);color:#fff}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}
h1,h2,h3,h4{letter-spacing:-.02em;line-height:1.15}
.mono,.num,.metric{font-family:'JetBrains Mono',Consolas,monospace;font-variant-numeric:tabular-nums}
.wrap{max-width:1180px;margin:0 auto;padding:0 26px}
main{position:relative;z-index:1}
.aurora{position:fixed;inset:-25%;z-index:0;pointer-events:none;background:
    radial-gradient(42% 48% at 18% 12%,rgba(124,60,255,.30),transparent 62%),
    radial-gradient(46% 52% at 84% 8%,rgba(183,140,255,.22),transparent 62%),
    radial-gradient(38% 44% at 62% 86%,rgba(84,190,255,.14),transparent 62%),
    radial-gradient(34% 40% at 12% 82%,rgba(236,72,153,.10),transparent 62%);
    filter:blur(60px);animation:auroraIQ 22s ease-in-out infinite alternate}
@keyframes auroraIQ{0%{transform:translate3d(0,0,0) scale(1)}50%{transform:translate3d(2%,-2%,0) scale(1.07)}100%{transform:translate3d(-2%,1%,0) scale(1.03)}}
.orbs i{position:fixed;border-radius:50%;filter:blur(70px);opacity:.34;z-index:0;pointer-events:none}
.orbs i:nth-child(1){width:360px;height:360px;background:#7c3cff;top:10%;left:4%;animation:orbIQ1 16s ease-in-out infinite}
.orbs i:nth-child(2){width:300px;height:300px;background:#b78cff;top:34%;right:6%;animation:orbIQ2 19s ease-in-out infinite}
.orbs i:nth-child(3){width:260px;height:260px;background:#54beff;bottom:10%;left:38%;animation:orbIQ3 23s ease-in-out infinite}
@keyframes orbIQ1{0%,100%{transform:translate(0,0)}50%{transform:translate(44px,-34px)}}
@keyframes orbIQ2{0%,100%{transform:translate(0,0)}50%{transform:translate(-56px,44px)}}
@keyframes orbIQ3{0%,100%{transform:translate(0,0)}50%{transform:translate(34px,-54px)}}
.grid-bg{position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(var(--border) 1px,transparent 1px),linear-gradient(90deg,var(--border) 1px,transparent 1px);background-size:52px 52px;-webkit-mask-image:radial-gradient(ellipse 90% 62% at 50% 0%,#000 28%,transparent 74%);mask-image:radial-gradient(ellipse 90% 62% at 50% 0%,#000 28%,transparent 74%);opacity:.28}
.cursor-glow{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:0;transition:opacity .4s;background:radial-gradient(560px circle at var(--cx,50%) var(--cy,0%),rgba(124,60,255,.14),rgba(183,140,255,.07) 45%,transparent 70%)}
.cursor-glow.on{opacity:1}
.scroll-progress{position:fixed;top:0;left:0;right:0;height:2px;z-index:101;background:linear-gradient(90deg,#7c3cff,#b78cff,#54beff);transform:scaleX(0);transform-origin:0 50%}
@supports (animation-timeline: scroll()){
    .scroll-progress{animation:iqProgress linear;animation-timeline:scroll(root)}
    @keyframes iqProgress{from{transform:scaleX(0)}to{transform:scaleX(1)}}
}
.w-nav{position:fixed;top:0;left:0;right:0;z-index:100;height:64px;display:flex;align-items:center;justify-content:space-between;padding:0 26px;background:var(--nav-bg);backdrop-filter:blur(18px);border-bottom:1px solid var(--border)}
.w-nav-brand{display:flex;align-items:center;gap:11px}
.w-nav-logo{width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#7c3cff,#b78cff);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;color:#fff;box-shadow:0 0 22px var(--glow)}
.w-nav-brand span{font-size:13px;font-weight:700;letter-spacing:.1em;text-transform:uppercase}
.w-nav-links{display:flex;align-items:center;gap:24px}
.w-nav-links a{font-size:13.5px;color:var(--muted);transition:color .15s}
.w-nav-links a:hover{color:var(--text)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;min-height:44px;padding:10px 18px;border-radius:12px;font-size:13.5px;font-weight:600;cursor:pointer;border:1px solid transparent;transition:transform .18s cubic-bezier(.22,1,.36,1),box-shadow .18s,border-color .18s,background .18s,color .18s;font-family:inherit}
.btn:focus-visible{outline:2px solid var(--accent2);outline-offset:2px}
.btn:disabled{opacity:.5;cursor:not-allowed}
.btn-primary{background:linear-gradient(120deg,#7c3cff,#b78cff);color:#fff;box-shadow:0 10px 34px var(--glow)}
.btn-primary:hover{transform:translateY(-2px);box-shadow:0 16px 44px var(--glow)}
.btn-ghost{background:var(--panel);border-color:var(--border2);color:var(--text);backdrop-filter:blur(10px)}
.btn-ghost:hover{border-color:var(--accent2)}
.btn-outline{background:transparent;border-color:var(--border2);color:var(--text)}
.btn-outline:hover{border-color:var(--accent2);color:var(--accent2)}
.btn-pulse{animation:iqPulse 2.6s infinite}
@keyframes iqPulse{0%,100%{box-shadow:0 0 0 0 rgba(124,60,255,.35)}50%{box-shadow:0 0 0 10px rgba(124,60,255,0)}}
.badge{display:inline-block;padding:5px 14px;border-radius:999px;font-size:11px;font-weight:600;letter-spacing:.05em;background:rgba(124,60,255,.14);border:1px solid rgba(183,140,255,.42);color:var(--accent2);backdrop-filter:blur(8px)}
.gradient-text{background:linear-gradient(120deg,#b78cff,#54beff,#ec4899,#b78cff);background-size:300% 300%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:iqGrad 9s ease infinite}
@keyframes iqGrad{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
.h-sec{text-align:center;margin-bottom:46px}
.h-sec h2{font-size:clamp(24px,3.4vw,34px);font-weight:800}
.h-sec p{font-size:14.5px;color:var(--muted);max-width:640px;margin:10px auto 0}
.panel,.card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);backdrop-filter:blur(14px)}
.panel{padding:24px}
.card{padding:22px;transition:transform .22s cubic-bezier(.22,1,.36,1),border-color .22s,box-shadow .22s}
.card:hover{transform:translateY(-4px);border-color:rgba(183,140,255,.42);box-shadow:0 18px 50px rgba(124,60,255,.16)}
.card .ic{width:42px;height:42px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:17px;font-weight:700;margin-bottom:14px;background:linear-gradient(135deg,rgba(124,60,255,.22),rgba(84,190,255,.16));border:1px solid rgba(183,140,255,.3);color:var(--accent2)}
.card h3{font-size:14.5px;font-weight:700;margin-bottom:7px}
.card p{font-size:13px;color:var(--muted);line-height:1.65}
.wwi-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:16px}
.wwi-grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:16px}
.wwi-gallery-grid{grid-template-columns:repeat(3,1fr)}
.input{width:100%;background:var(--bg2);border:1px solid var(--border2);border-radius:12px;padding:12px 15px;color:var(--text);font-size:14px;outline:none;font-family:inherit;transition:border .15s,box-shadow .15s}
.input:focus{border-color:var(--accent2);box-shadow:0 0 0 3px rgba(124,60,255,.18)}
.trust-row{display:flex;gap:18px;flex-wrap:wrap;justify-content:center;font-size:12.5px;color:var(--muted)}
.trust-row span::before{content:'✓ ';color:var(--ok);font-weight:700}
.stat{text-align:center;padding:26px 16px}
.stat .v{font-family:'JetBrains Mono',monospace;font-size:24px;font-weight:600;background:linear-gradient(120deg,#b78cff,#54beff);-webkit-background-clip:text;background-clip:text;color:transparent}
.stat .l{font-size:11px;text-transform:uppercase;letter-spacing:.07em;color:var(--muted);margin-top:6px}
.marquee{overflow:hidden;border-top:1px solid var(--border);border-bottom:1px solid var(--border);background:var(--soft);padding:15px 0}
.marquee .track{display:flex;gap:38px;white-space:nowrap;animation:mqIQ 30s linear infinite;width:max-content}
.marquee span{font-size:12.5px;color:var(--muted);letter-spacing:.06em;text-transform:uppercase}
.marquee span b{color:var(--accent2)}
@keyframes mqIQ{to{transform:translateX(-50%)}}
.step{display:flex;gap:18px;align-items:flex-start}
.step-num{font-family:'JetBrains Mono',monospace;font-size:14px;font-weight:600;min-width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,rgba(124,60,255,.2),rgba(84,190,255,.14));border:1px solid rgba(183,140,255,.35);color:var(--accent2);box-shadow:0 0 20px rgba(124,60,255,.18)}
.step h3{font-size:14.5px;font-weight:700;margin-bottom:6px}
.step p{font-size:13px;color:var(--muted);line-height:1.65}
.faq-item{border:1px solid var(--border);border-radius:14px;margin-bottom:10px;overflow:hidden;background:var(--panel);backdrop-filter:blur(10px)}
.faq-q{display:flex;justify-content:space-between;align-items:center;padding:16px 20px;cursor:pointer;font-size:14px;font-weight:600;user-select:none}
.faq-q::after{content:'+';font-family:'JetBrains Mono',monospace;color:var(--accent2);font-size:16px}
.faq-item.open .faq-q::after{content:'–'}
.faq-a{display:none;padding:0 20px 16px;font-size:13px;color:var(--muted);line-height:1.7}
.faq-item.open .faq-a{display:block}
.plan-card{display:flex;flex-direction:column;gap:15px;padding:28px;position:relative}
.plan-card.featured{border-color:rgba(124,60,255,.5);box-shadow:0 0 40px var(--glow)}
.plan-name{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:var(--muted)}
.plan-price{font-family:'JetBrains Mono',monospace;font-size:27px;font-weight:600}
.plan-price small{font-size:12px;color:var(--muted);font-weight:400}
.plan-feats{display:flex;flex-direction:column;gap:8px;flex:1}
.plan-feats div{font-size:12.5px;color:var(--muted);display:flex;gap:9px;align-items:center}
.plan-feats div::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--accent2);box-shadow:0 0 8px var(--accent2)}
.plan-mini{border:1px solid var(--border);border-radius:16px;padding:22px;text-align:center;cursor:pointer;transition:all .18s;background:var(--panel)}
.plan-mini:hover{border-color:var(--accent2);transform:translateY(-2px)}
.plan-mini .pr{font-family:'JetBrains Mono',monospace;font-size:22px;font-weight:600;margin:9px 0}
.plan-mini .nm{font-size:12.5px;font-weight:700}
.tpl-card{border:1px solid var(--border);border-radius:14px;padding:17px;text-align:center;cursor:pointer;transition:all .18s;background:var(--panel2)}
.tpl-card:hover{border-color:var(--accent2);transform:translateY(-2px)}
.tpl-card .nm{font-size:12.5px;font-weight:700}
.tpl-card .cat{font-size:10px;color:var(--muted);margin-top:4px;text-transform:uppercase;letter-spacing:.06em}
.dom-chip{border:1px solid var(--border2);border-radius:11px;padding:9px 15px;font-family:'JetBrains Mono',monospace;font-size:12px;cursor:pointer;transition:all .15s;background:var(--panel2);color:var(--text)}
.dom-chip:hover{border-color:var(--accent2);color:var(--accent2)}
.dom-chip.sel{border-color:var(--accent2);background:rgba(124,60,255,.12)}
.dom-row{display:flex;gap:9px;flex-wrap:wrap;margin-top:12px}
.dom-card{display:flex;gap:12px;align-items:flex-start;border:1px solid var(--border);border-radius:14px;padding:14px 16px;background:var(--panel2);animation:iqFade .28s ease}
.dom-card .ic{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:800;flex-shrink:0}
.dom-card.ok .ic{background:rgba(52,211,153,.15);color:#34d399}
.dom-card.bad .ic{background:rgba(248,113,113,.15);color:#f87171}
.dom-card.warn .ic{background:rgba(251,191,36,.15);color:#fbbf24}
.dom-card .t{font-weight:700;font-size:14px}
.dom-card .d{font-size:12px;color:var(--muted);margin-top:3px;line-height:1.55}
.dom-card .pr{font-family:'JetBrains Mono',monospace;font-size:11.5px;margin-top:7px;color:var(--text)}
.dom-card .acts{display:flex;gap:8px;margin-top:11px;flex-wrap:wrap}
.dom-tld{display:inline-flex;align-items:center;gap:8px;border:1px solid var(--border2);border-radius:10px;padding:8px 12px;font-size:12px;cursor:pointer;background:var(--panel2);color:var(--text);font-family:inherit;transition:.15s}
.dom-tld:hover{border-color:var(--accent2);transform:translateY(-1px)}
.dom-tld.ok{border-color:rgba(52,211,153,.5)}
.dom-tld.warn{border-color:rgba(251,191,36,.45)}
.dom-tld.bad{opacity:.6}
.dom-tld .dot{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.dom-recent{display:flex;gap:6px;flex-wrap:wrap;align-items:center;margin-top:10px;font-size:11px;color:var(--muted)}
#fs-domain .flow-sub{margin-bottom:16px}
#fs-domain .dom-card{padding:12px 14px}
#fs-domain .flow-actions{margin-top:12px}
#fs-domain .dom-row{margin-top:10px}
#fs-domain .flow-h1{font-size:22px}
@keyframes iqFade{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:none}}
.domain-box{display:flex;gap:10px;max-width:580px;margin:0 auto}
.domain-box .input{flex:1}
.domain-result{margin-top:12px;font-size:12.5px;min-height:20px;font-family:'JetBrains Mono',monospace}
.spin{display:none;width:24px;height:24px;border:2px solid var(--border2);border-top-color:var(--accent2);border-radius:50%;animation:spIQ .8s linear infinite;margin:0 auto}
@keyframes spIQ{to{transform:rotate(360deg)}}
.reveal{opacity:0;transform:translateY(16px);transition:opacity .55s ease,transform .55s ease}
.reveal.visible{opacity:1;transform:none}
@media(prefers-reduced-motion:no-preference){
    @supports (animation-timeline: view()){
        .reveal{opacity:1;transform:none;animation:iqReveal both;animation-timeline:view();animation-range:entry 5% entry 55%}
        @keyframes iqReveal{from{opacity:0;transform:translateY(18px)}to{opacity:1;transform:none}}
    }
}
.w-footer{border-top:1px solid var(--border);padding:52px 0 34px;background:var(--bg2)}
.w-footer .cols{display:grid;grid-template-columns:2fr 1fr 1fr;gap:32px}
.w-footer h4{font-size:11px;text-transform:uppercase;letter-spacing:.09em;color:var(--muted);margin-bottom:14px}
.w-footer a,.w-footer p{display:block;font-size:13px;color:var(--muted);margin-bottom:9px}
.w-footer a:hover{color:var(--accent2)}
.w-footer .legal{margin-top:34px;padding-top:20px;border-top:1px solid var(--border);font-size:11.5px;color:var(--muted);display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px}
.wwi-section{position:relative}
.wwi-iq-hero{display:grid;grid-template-columns:1.05fr .95fr;gap:34px;align-items:center;padding:150px 0 70px}
.wwi-iq-hero .wwi-iq-hero-main{min-width:0}
.wwi-iq-hero section{padding:0!important}
.wwi-iq-tia{display:flex;flex-direction:column;gap:18px}
.wwi-ai-orb{width:84px;aspect-ratio:1;border-radius:50%;background:radial-gradient(circle at 35% 30%,#fff 0 4%,#d7b8ff 8%,#8d4dff 32%,#4215a6 68%,transparent 72%);box-shadow:0 0 30px rgba(139,77,255,.55),0 0 80px rgba(139,77,255,.25);animation:tiaPulse 2.8s ease-in-out infinite;flex-shrink:0}
@keyframes tiaPulse{0%,100%{transform:scale(1);opacity:.88}50%{transform:scale(1.06);opacity:1}}
.tia-panel{background:var(--panel);border:1px solid var(--border);border-radius:20px;padding:22px;backdrop-filter:blur(16px);box-shadow:var(--shadow)}
.tia-panel .tia-head{display:flex;align-items:center;gap:14px;margin-bottom:16px}
.tia-panel .tia-head b{display:block;font-size:14px;letter-spacing:.06em}
.tia-panel .tia-head span{font-size:11px;color:var(--muted)}
.tia-list{display:flex;flex-direction:column;gap:9px;margin-bottom:16px}
.tia-list div{display:flex;align-items:center;gap:10px;font-size:12.5px;color:var(--muted)}
.tia-list div::before{content:'✓';width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;background:rgba(53,212,154,.16);color:var(--ok);border:1px solid rgba(53,212,154,.35);flex-shrink:0}
.tia-list div.working{color:var(--text)}
.tia-list div.working::before{content:'';border:2px solid rgba(183,140,255,.35);border-top-color:var(--accent2);background:transparent;animation:spIQ .9s linear infinite}
.tia-list div.pending::before{content:'';border:1px solid var(--border2);background:transparent}
.tia-bar{height:7px;border-radius:4px;background:var(--border);overflow:hidden;margin-bottom:6px}
.tia-bar i{display:block;height:100%;width:78%;border-radius:4px;background:linear-gradient(90deg,#7c3cff,#b78cff,#54beff);background-size:200% 100%;animation:iqBar 2.4s ease infinite}
@keyframes iqBar{0%,100%{background-position:0% 50%}50%{background-position:100% 50%}}
.tia-pct{font-family:'JetBrains Mono',monospace;font-size:11px;color:var(--muted);text-align:right}
.wwi-preview{background:var(--panel);border:1px solid var(--border);border-radius:20px;overflow:hidden;backdrop-filter:blur(16px);box-shadow:var(--shadow)}
.wwi-preview .pv-bar{display:flex;align-items:center;gap:6px;padding:11px 14px;border-bottom:1px solid var(--border);background:var(--soft)}
.wwi-preview .pv-bar i{width:9px;height:9px;border-radius:50%;background:var(--border2)}
.wwi-preview .pv-bar i:first-child{background:#ff667a}
.wwi-preview .pv-bar i:nth-child(2){background:#ffcc66}
.wwi-preview .pv-bar i:nth-child(3){background:#35d49a}
.wwi-preview .pv-url{margin-left:8px;flex:1;height:18px;border-radius:6px;background:var(--soft);border:1px solid var(--border)}
.wwi-preview .pv-body{padding:16px;display:flex;flex-direction:column;gap:10px}
.pv-hero{height:64px;border-radius:12px;background:linear-gradient(120deg,rgba(124,60,255,.30),rgba(84,190,255,.18))}
.pv-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}
.pv-card{height:44px;border-radius:10px;background:var(--soft);border:1px solid var(--border)}
.pv-line{height:9px;border-radius:5px;background:var(--soft);border:1px solid var(--border)}
.pv-line.short{width:62%}
.pv-line.tiny{width:38%}
@media(max-width:1100px){
    .wwi-grid-3{grid-template-columns:repeat(2,1fr)}
    .wwi-gallery-grid{grid-template-columns:repeat(2,1fr)}
    .wwi-iq-hero{grid-template-columns:1fr;padding:130px 0 50px}
    .w-footer .cols{grid-template-columns:1fr}
}
@media(max-width:720px){
    .wwi-grid-3,.wwi-grid-2{grid-template-columns:1fr}
    .w-nav-links{display:none}
    .wwi-gallery-grid{grid-template-columns:1fr}
    .wrap{padding:0 18px}
}
@media(prefers-reduced-motion:reduce){
    .aurora,.orbs i,.gradient-text,.marquee .track,.wwi-ai-orb,.tia-bar i,.btn-pulse,.spin,.tia-list div.working::before{animation:none}
    .reveal{opacity:1;transform:none}
}
@media(max-width:720px){.wwi-hide-mobile{display:none!important}}
@media(min-width:721px) and (max-width:1100px){.wwi-hide-tablet{display:none!important}}
</style>

</head>
<body>
<div class="aurora"></div>
<div class="orbs"><i></i><i></i><i></i></div>
<div class="grid-bg"></div>
<nav class="w-nav">
  <div class="w-nav-brand"><div class="w-nav-logo" data-source="settings:nav:logo_letter"><?= htmlspecialchars((string)$wwiNav['logo_letter']) ?></div><span data-source="settings:nav:brand"><?= htmlspecialchars((string)$wwiNav['brand']) ?></span></div>
  <div class="w-nav-links">
    <?php foreach ($wwiNav['links'] as $i => $l): ?>
    <a href="<?= htmlspecialchars((string)($l['url'] ?? '#')) ?>" data-source="settings:nav:link:<?= (int)$i ?>:label"><?= htmlspecialchars((string)($l['label'] ?? '')) ?></a>
    <?php endforeach; ?>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <button class="btn btn-ghost" id="wwi-theme-toggle" title="Cambiar tema" aria-label="Cambiar tema" style="width:36px;padding:8px 0;justify-content:center">☾</button>
    <a href="<?= htmlspecialchars((string)$wwiNav['cta_url']) ?>" class="btn btn-primary" data-source="settings:nav:cta"><?= htmlspecialchars((string)$wwiNav['cta']) ?></a>
  </div>
</nav>
<main>
<?php
$wwiAbIds = [];
foreach ($sections as $s) {
    if (!empty($s['widget_type']) && WidgetRegistry::get($s['widget_type'])) $wwiAbIds[] = (int)($s['id'] ?? 0);
}
$wwiAb = [];
if ($wwiAbIds) {
    try {
        $wwiVisitor = sha1(($_SERVER['REMOTE_ADDR'] ?? '') . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        $wwiAb = (new \App\Services\LiveEditorService())->pickVariants($wwiAbIds, $wwiVisitor);
    } catch (\Throwable $e) {
        $wwiAb = [];
    }
}
$wwiHeroDone = false;
$wwiTiaPanel = '<aside class="wwi-iq-tia">'
    . '<div class="tia-panel tia is-building" data-tia-state="building">'
    . '<div class="tia-head"><span class="wwi-ai-orb" aria-hidden="true"></span><div><b>TIA</b><span>Tecnología de Inteligencia Aplicada</span></div></div>'
    . '<div class="tia-list">'
    . '<div>Analizando información</div>'
    . '<div>Definiendo estructura</div>'
    . '<div>Seleccionando diseño</div>'
    . '<div class="working">Construyendo sitio</div>'
    . '<div class="pending">Optimizando contenido</div>'
    . '<div class="pending">Preparando publicación</div>'
    . '</div>'
    . '<div class="tia-bar" aria-hidden="true"><i></i></div>'
    . '<div class="tia-pct">78%</div>'
    . '</div>'
    . '<div class="wwi-preview" aria-hidden="true">'
    . '<div class="pv-bar"><i></i><i></i><i></i><span class="pv-url"></span></div>'
    . '<div class="pv-body">'
    . '<div class="pv-hero"></div>'
    . '<div class="pv-line short"></div>'
    . '<div class="pv-row"><div class="pv-card"></div><div class="pv-card"></div><div class="pv-card"></div></div>'
    . '<div class="pv-line"></div>'
    . '<div class="pv-line tiny"></div>'
    . '</div>'
    . '</div>'
    . '</aside>';
foreach ($sections as $section):
    $wwiSid = (int)($section['id'] ?? 0);
    $config = json_decode($section['config'] ?? '{}', true) ?: [];
    $wwiVariant = isset($wwiAb[$wwiSid]) ? (int)$wwiAb[$wwiSid]['variant_id'] : 0;
    if ($wwiVariant) $config = array_merge($config, $wwiAb[$wwiSid]['config']);
    $wwiHide = (!empty($config['_hide_mobile']) ? ' wwi-hide-mobile' : '') . (!empty($config['_hide_tablet']) ? ' wwi-hide-tablet' : '');
    $wwiIsHero = !$wwiHeroDone && !empty($section['widget_type']) && stripos((string)$section['widget_type'], 'hero') !== false;
    if ($wwiIsHero) $wwiHeroDone = true;
    $wwiIsWwiHero = $wwiIsHero && (string)($section['widget_type'] ?? '') === 'wwi-hero';
    echo '<div class="wwi-section' . $wwiHide . '" data-sid="' . $wwiSid . '" data-widget="' . htmlspecialchars((string)($section['widget_type'] ?? '')) . '"' . ($wwiVariant ? ' data-variant="' . $wwiVariant . '"' : '') . '>';
    if ($wwiIsWwiHero) echo '<div class="wwi-iq-hero-solo">';
    elseif ($wwiIsHero) echo '<div class="wwi-iq-hero"><div class="wwi-iq-hero-main">';
    if (!empty($section['widget_type']) && WidgetRegistry::get($section['widget_type'])):
        echo WidgetRegistry::render($section['widget_type'], $config);
    elseif ($section['type'] === 'custom' || $section['type'] === 'html'):
        echo '<section>' . ($section['content'] ?? '') . '</section>';
    else:
        if ($section['content']) echo '<section>' . $section['content'] . '</section>';
    endif;
    if ($wwiIsWwiHero) echo '</div>';
    elseif ($wwiIsHero) echo '</div>' . $wwiTiaPanel . '</div>';
    echo '</div>';
endforeach; ?>
>
</main>
<script>
(function(){
    var isEditor=false;
    try{isEditor=!!localStorage.getItem('wwi_token')}catch(e){}
    if(isEditor)return;
    document.querySelectorAll('.wwi-section[data-variant]').forEach(function(sec){
        var vid=sec.getAttribute('data-variant');
        try{var k='wwi_ab_seen_'+vid;if(sessionStorage.getItem(k))return;sessionStorage.setItem(k,'1')}catch(e){}
        fetch('/api/v1/public/variants/'+vid+'/track',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'view'})}).catch(function(){});
        sec.addEventListener('click',function(e){
            if(e.target.closest('a,button')){
                fetch('/api/v1/public/variants/'+vid+'/track',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({type:'click'})}).catch(function(){});
            }
        });
    });
})();
</script>
<?= CookieConsentService::render() ?>
<script>
(function(){
    var wwiPrev=!!window.__WWI_PREVIEW__;
    var r=document.querySelectorAll('.reveal');
    if('IntersectionObserver' in window){
        var o=new IntersectionObserver(function(e){e.forEach(function(en){if(en.isIntersecting)en.target.classList.add('visible')})},{threshold:0.08});
        r.forEach(function(el){o.observe(el)});
    }else{
        r.forEach(function(el){el.classList.add('visible')});
    }
    document.querySelectorAll('.faq-q').forEach(function(q){
        q.addEventListener('click',function(){q.parentElement.classList.toggle('open')});
    });
    var glow=wwiPrev?null:document.createElement('div');
    if(glow){glow.className='cursor-glow';document.body.appendChild(glow)}
    var progress=wwiPrev?null:document.createElement('div');
    if(progress){progress.className='scroll-progress';progress.setAttribute('aria-hidden','true');document.body.appendChild(progress)}
    var fine=window.matchMedia&&window.matchMedia('(pointer:fine)').matches;
    var calm=window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    if(glow&&fine&&!calm){
        var tx=window.innerWidth/2,ty=140,cx=tx,cy=ty,anim=false;
        var step=function(){
            cx+=(tx-cx)*.14;cy+=(ty-cy)*.14;
            document.documentElement.style.setProperty('--cx',cx.toFixed(1)+'px');
            document.documentElement.style.setProperty('--cy',cy.toFixed(1)+'px');
            if(Math.abs(tx-cx)>.5||Math.abs(ty-cy)>.5){requestAnimationFrame(step)}else{anim=false}
        };
        document.addEventListener('mousemove',function(e){tx=e.clientX;ty=e.clientY;if(!anim){anim=true;glow.classList.add('on');requestAnimationFrame(step)}},{passive:true});
    }
    var counters=document.querySelectorAll('[data-count]');
    if(!wwiPrev&&counters.length){
        var runCount=function(el){
            var target=parseFloat(el.getAttribute('data-count'))||0;
            var suffix=el.getAttribute('data-suffix')||'';
            if(calm){el.textContent=target+suffix;return}
            var t0=null;
            var stepCount=function(ts){
                if(!t0)t0=ts;
                var p=Math.min((ts-t0)/900,1);
                el.textContent=Math.round(target*(1-Math.pow(1-p,3)))+suffix;
                if(p<1)requestAnimationFrame(stepCount);
            };
            requestAnimationFrame(stepCount);
        };
        if('IntersectionObserver' in window){
            var cio=new IntersectionObserver(function(entries){
                entries.forEach(function(en){if(en.isIntersecting){runCount(en.target);cio.unobserve(en.target)}});
            },{threshold:.4});
            counters.forEach(function(el){cio.observe(el)});
        }else{counters.forEach(runCount)}
    }
    var themeBtn=document.getElementById('wwi-theme-toggle');
    if(themeBtn&&!wwiPrev){
        var isLight=function(){return document.documentElement.getAttribute('data-theme')==='light'};
        themeBtn.textContent=isLight()?'☀':'☾';
        themeBtn.addEventListener('click',function(e){
            var to=isLight()?'dark':'light';
            var apply=function(){
                document.documentElement.setAttribute('data-theme',to);
                try{localStorage.setItem('wwi_theme',to)}catch(err){}
                themeBtn.textContent=to==='light'?'☀':'☾';
            };
            if(document.startViewTransition&&!calm){
                var x=e.clientX||window.innerWidth-40,y=e.clientY||40;
                var rad=Math.hypot(Math.max(x,window.innerWidth-x),Math.max(y,window.innerHeight-y));
                var tr=document.startViewTransition(apply);
                tr.ready.then(function(){
                    document.documentElement.animate({clipPath:['circle(0px at '+x+'px '+y+'px)','circle('+rad+'px at '+x+'px '+y+'px)']},{duration:520,easing:'cubic-bezier(.22,1,.36,1)',pseudoElement:'::view-transition-new(root)'});
                });
            }else{apply()}
        });
    }
})();
async function wwiLoadPlans(){
    var boxes=document.querySelectorAll('[data-wwi-plans]');
    var sel=document.getElementById('wwi-co-plan');
    var planData=[];
    try{
        var r=await fetch('/api/v1/public/plans');
        var d=await r.json();
        var plans=(d.data||[]).filter(function(p){return p.price_cop>0});
        planData=plans;
        boxes.forEach(function(box){
            var L={cop:box.dataset.cop||'COP',usd:box.dataset.usd||'USD',one:box.dataset.one||'pago único',monthly:box.dataset.monthly||'suscripción mensual',prefix:box.dataset.prefix||'Elegir '};
            var html='';
            plans.forEach(function(p,i){
                var feats=(p.features||[]).slice(0,8);
                html+='<div class="card plan-card'+(i===0?' featured':'')+'"><div><div class="plan-name"><span data-source="plan:'+p.id+':name_es">'+wwiEsc(p.name_es)+'</span> <span style="color:var(--muted)">/ '+wwiEsc(p.name_en)+'</span></div><div class="plan-price" style="margin-top:8px" data-source="plan:'+p.id+':price_cop">$'+Number(p.price_cop).toLocaleString('es-CO')+' <small>'+wwiEsc(L.cop)+'</small>'+(p.price_usd?' <small>· $'+p.price_usd+' '+wwiEsc(L.usd)+'</small>':'')+'</div>'+(p.billing_type==='one_time'?'<div style="font-size:11px;color:var(--muted);margin-top:2px">'+wwiEsc(L.one)+'</div>':'<div style="font-size:11px;color:var(--muted);margin-top:2px">'+wwiEsc(L.monthly)+'</div>')+'</div><div class="plan-feats">'+feats.map(function(f,fi){return '<div data-source="plan:'+p.id+':feature:'+fi+'">'+wwiEsc(String(f).replace(/_/g,' '))+'</div>'}).join('')+'</div><a class="btn '+(i===0?'btn-primary':'btn-outline')+'" style="width:100%" href="#empezar" data-plan="'+p.id+'" data-source="plan:'+p.id+':name_es">'+wwiEsc(L.prefix)+wwiEsc(p.name_es)+'</a></div>';
            });
            box.innerHTML=html;
        });
        if(sel){
            sel.innerHTML=plans.map(function(p,i){return '<option value="'+p.id+'"'+(i===0?' selected':'')+'>'+wwiEsc(p.name_es)+' — $'+Number(p.price_cop).toLocaleString('es-CO')+' COP</option>'}).join('');
            window.wwiPlans=plans;
            wwiUpdateTotal();
            sel.addEventListener('change',wwiUpdateTotal);
        }
        document.querySelectorAll('a[data-plan]').forEach(function(a){
            a.addEventListener('click',function(){ if(sel){sel.value=a.dataset.plan;wwiUpdateTotal()} });
        });
    }catch(e){}
}
function wwiCoMsg(k,def){var c=document.getElementById('wwi-co');if(!c)return def;var v=c.getAttribute('data-msg-'+k);return v||def}
function wwiUpdateTotal(){
    var sel=document.getElementById('wwi-co-plan');
    var tot=document.getElementById('wwi-co-total');
    if(!sel||!tot||!window.wwiPlans)return;
    var p=window.wwiPlans.find(function(x){return String(x.id)===String(sel.value)});
    tot.textContent=p?(wwiCoMsg('total','Total: ')+'$'+Number(p.price_cop).toLocaleString('es-CO')+' '+wwiCoMsg('currency','COP')):'';
}
async function wwiCheckoutSubmit(){
    var btn=document.getElementById('wwi-co-submit');
    var res=document.getElementById('wwi-co-result');
    if(!btn||!res)return;
    var payload={
        plan_id:parseInt(document.getElementById('wwi-co-plan').value)||0,
        customer_name:document.getElementById('wwi-co-name').value,
        customer_email:document.getElementById('wwi-co-email').value,
        customer_phone:document.getElementById('wwi-co-phone').value,
        domain_name:document.getElementById('wwi-co-domain').value,
        locale:'es'
    };
    if(!payload.customer_name||!payload.customer_email){res.innerHTML='<div style="color:var(--bad);font-size:12px">Completa nombre y email.</div>';return}
    btn.disabled=true;btn.style.opacity=.6;var wwiBtnOrig=btn.textContent;btn.textContent=wwiCoMsg('creating','Creando…');
    try{
        var r=await fetch('/api/v1/public/orders',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        var d=await r.json();
        if(d.ok){
            var demoBtn=(wwiPayMode==='dummy')?'<button class="btn btn-primary" style="margin-top:10px" onclick="wwiDummyPay(\''+d.data.uuid+'\')">'+wwiEsc(wwiCoMsg('demo','Pagar (modo demo)'))+'</button>':'<div style="font-size:11px;color:var(--muted);margin-top:6px">Te contactaremos con el link de pago seguro.</div>';
            res.innerHTML='<div class="panel" style="padding:16px;border-color:rgba(52,211,153,.5)"><div style="color:var(--ok);font-size:13px;font-weight:600">'+wwiEsc(wwiCoMsg('created','✓ Pedido creado'))+' — '+wwiEsc(d.data.plan_name)+'</div><div class="mono" style="font-size:11px;color:var(--muted);margin-top:6px"># '+wwiEsc(d.data.uuid)+' · $'+Number(d.data.total).toLocaleString('es-CO')+' '+wwiEsc(wwiCoMsg('currency','COP'))+' · '+wwiEsc(d.data.status)+'</div>'+demoBtn+'</div>';
        }else{
            res.innerHTML='<div style="color:var(--bad);font-size:12px">'+wwiEsc(d.message||'Error al crear el pedido')+'</div>';
        }
    }catch(e){res.innerHTML='<div style="color:var(--bad);font-size:12px">'+wwiEsc(wwiCoMsg('conn','Error de conexión'))+'</div>'}
    btn.disabled=false;btn.style.opacity=1;btn.textContent=wwiBtnOrig||btn.textContent;
}
var wwiPayMode='';
async function wwiLoadPayMode(){
    try{var r=await fetch('/api/v1/public/payment-mode');var d=await r.json();wwiPayMode=(d.data&&d.data.provider)||''}catch(e){}
}
async function wwiDummyPay(uuid){
    var res=document.getElementById('wwi-co-result');
    try{
        var r=await fetch('/api/v1/public/payments/dummy/'+uuid,{method:'POST'});
        var d=await r.json();
        if(d.ok){
            var s=await fetch('/api/v1/public/orders/'+uuid);var sd=await s.json();
            res.innerHTML='<div class="panel" style="padding:16px;border-color:rgba(52,211,153,.5)"><div style="color:var(--ok);font-size:13px;font-weight:600">'+wwiEsc(wwiCoMsg('paid','✓ Pago demo aprobado'))+' — '+wwiEsc((sd.data&&sd.data.status)||'')+'</div><div style="font-size:11px;color:var(--muted);margin-top:6px">'+wwiEsc(wwiCoMsg('provision','El provisioning se ejecuta automáticamente en la cola de jobs.'))+'</div></div>';
        }else{
            res.innerHTML='<div style="color:var(--bad);font-size:12px">'+wwiEsc(d.message||'Error')+'</div>';
        }
    }catch(e){res.innerHTML='<div style="color:var(--bad);font-size:12px">'+wwiEsc(wwiCoMsg('conn','Error de conexión'))+'</div>'}
}
async function wwiLoadTemplates(){
    var boxes=document.querySelectorAll('[data-wwi-templates]');
    if(!boxes.length)return;
    try{
        var r=await fetch('/api/v1/public/templates');
        var d=await r.json();
        var tpls=(d.data&&d.data.templates)||[];
        boxes.forEach(function(box){
            var letter=box.dataset.letter||'W';
            box.innerHTML=tpls.map(function(t){
                return '<div class="card" style="text-align:center;padding:20px"><div style="font-size:22px;margin-bottom:8px">'+wwiEsc(letter)+'</div><div style="font-size:13px;font-weight:600" data-source="template:'+(t.id||t.slug)+':name_es">'+wwiEsc(t.name_es)+'</div><div style="font-size:11px;color:var(--muted);margin-top:3px" data-source="template:'+(t.id||t.slug)+':name_en">'+wwiEsc(t.name_en)+'</div><div style="font-size:10px;color:var(--accent);margin-top:6px;text-transform:uppercase;letter-spacing:.05em">'+wwiEsc(t.category_slug||'')+'</div></div>';
            }).join('')||'<div style="color:var(--muted);grid-column:1/-1">Sin plantillas aún</div>';
        });
    }catch(e){}
}
async function wwiCheckDomain(){
    var input=document.getElementById('wwi-domain-input');
    var res=document.getElementById('wwi-domain-result');
    if(!input||!res)return;
    var name=input.value.trim();
    if(!name){res.textContent='';return}
    res.innerHTML='<div class="spin" style="display:block"></div>';
    try{
        var r=await fetch('/api/v1/public/domain/check?name='+encodeURIComponent(name));
        var d=await r.json();
        var s=d.data||{};
        var color='var(--warn)';
        if(s.state==='AVAILABLE')color='var(--ok)';
        if(s.state==='INVALID'||s.state==='ERROR'||s.state==='TAKEN')color='var(--bad)';
        var msg=s.state;
        if(s.state==='AVAILABLE')msg='AVAILABLE — ¡libre! Inclúyelo con tu plan';
        if(s.state==='TAKEN')msg='TAKEN'+(s.message?' — '+s.message:'');
        if(s.state==='ERROR')msg='ERROR — '+(s.message||'revisa el dominio');
        var html='<span style="color:'+color+'">'+wwiEsc(msg)+'</span>';
        var sug=s.suggestions||[];
        if(sug.length){
            html+='<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:8px;margin-top:14px">';
            sug.forEach(function(x){
                var sc=x.state==='AVAILABLE'?'var(--ok)':(x.state==='CHECKING'||x.state==='ERROR'?'var(--warn)':'var(--bad)');
                var price=x.state==='AVAILABLE'&&x.price_reg!=null?'<span style="color:var(--text)">$'+x.price_reg+'</span> <span style="color:var(--muted)">USD/año</span>'+(x.price_ren!=null&&x.price_ren!==x.price_reg?'<div style="color:var(--muted)">ren. $'+x.price_ren+'</div>':''):'';
                var detail=x.state==='CHECKING'?'<div style="font-size:9px;color:var(--muted);margin-top:2px">verificar al registrar</div>':'';
                html+='<div class="panel" style="padding:10px 12px;text-align:left"><div class="mono" style="font-size:12px;color:'+sc+'">'+wwiEsc(x.name)+'</div><div class="mono" style="font-size:10px;margin-top:3px">'+(x.state==='AVAILABLE'?price:wwiEsc(x.state))+detail+'</div></div>';
            });
            html+='</div>';
        }
        res.innerHTML=html;
    }catch(e){res.textContent='Could not check right now.'}
}
function wwiEsc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
function wwiConfetti(){
    if(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;
    var colors=['#22d3ee','#8b5cf6','#ec4899','#f59e0b','#34d399'];
    for(var i=0;i<26;i++){
        var d=document.createElement('div');
        d.className='confetti';
        d.style.background=colors[i%colors.length];
        d.style.left=(window.innerWidth/2)+'px';
        d.style.top=(window.innerHeight/2)+'px';
        d.style.setProperty('--dx',(Math.random()*560-280)+'px');
        d.style.setProperty('--dy',(Math.random()*-420-80)+'px');
        document.body.appendChild(d);
        setTimeout((function(el){return function(){el.remove()}})(d),1700);
    }
}
wwiLoadPlans();
wwiLoadTemplates();
wwiLoadPayMode();
var coBtn=document.getElementById('wwi-co-submit');
if(coBtn)coBtn.addEventListener('click',wwiCheckoutSubmit);
var startBtn=document.getElementById('wwi-start');
if(startBtn)startBtn.addEventListener('click',function(e){e.preventDefault();wwiFlowOpen()});
</script>
<script>
var wwiFlow={idx:0,uuid:null,planId:null,addons:[],domain:null,attempts:0};
try{var _sd=localStorage.getItem('wwi_domain')||'';if(_sd)wwiFlow.domain=_sd}catch(e){}
function wwiFlowOpen(){
    document.getElementById('wwi-flow').classList.add('open');
    if(location.hash!=='#empezar')try{history.replaceState(null,'','#empezar')}catch(e){}
    wwiFlowGo(0);
    wwiFlowAttempts();
    if(!wwiFlow.booted){
        wwiFlowChat('tia','¡Hola! Soy TIA. Escribe cómo quieres tu sitio web. Por ejemplo: <em>soy arquitecto y quiero que mi sitio muestre mi trayectoria y los proyectos que he ejecutado</em>.');
        wwiFlow.booted=true;
    }
}
function wwiFlowClose(){
    document.getElementById('wwi-flow').classList.remove('open');
    if(location.hash==='#empezar')try{history.replaceState(null,'',location.pathname+location.search)}catch(e){}
}
function wwiFlowStartWithPlan(id){
    wwiFlow.planId=id;
    wwiFlow.addons=[];
    wwiFlowOpen();
    wwiFlowGo(4);
}
function wwiFlowMaybeOpen(){if(location.hash==='#empezar'&&document.getElementById('wwi-flow'))wwiFlowOpen()}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',wwiFlowMaybeOpen)}else{wwiFlowMaybeOpen()}
window.addEventListener('hashchange',wwiFlowMaybeOpen);
document.addEventListener('click',function(e){
    var a=e.target.closest?e.target.closest('a[href="#empezar"]'):null;
    if(!a)return;
    e.preventDefault();
    if(a.getAttribute('data-plan')&&window.wwiFlowStartWithPlan){wwiFlowStartWithPlan(parseInt(a.getAttribute('data-plan'))||0)}
    else{wwiFlowOpen()}
});
function wwiFlowGo(i){
    wwiFlow.idx=i;
    document.getElementById('wwi-flow-track').style.transform='translateX(-'+(i*100)+'%)';
    document.querySelectorAll('.flow-head .dot').forEach(function(d){d.classList.toggle('on',+d.dataset.d===i)});
    var labels=['Cuéntanos tu negocio','Tu vista previa','Elige plantilla','Elige tu plan','Tu dominio'];
    var lbl=document.getElementById('flow-step-label');
    if(lbl)lbl.textContent='Paso '+(i+1)+' · '+labels[i];
    if(i===2)wwiFlowLoadTpls();
    if(i===3)wwiFlowLoadPlans();
    if(i===4)setTimeout(function(){
        var el=document.getElementById('flow-dom-input');
        if(el){
            if(!el.value){try{var saved=localStorage.getItem('wwi_domain')||'';if(saved)el.value=saved}catch(e){}}
            el.focus();
        }
        wwiFlowRecentRender();
    },150);
}
function wwiFlowChip(el){
    var input=document.getElementById('flow-input');
    if(input){input.value=el.textContent;wwiFlowSend()}
}
function wwiFlowChat(who,html){
    var box=document.getElementById('flow-chat');if(!box)return null;
    var row=document.createElement('div');
    row.className='chat-row '+who;
    if(who==='tia')row.innerHTML='<div class="chat-ava">T</div><div class="chat-msg tia">'+html+'</div>';
    else row.innerHTML='<div class="chat-msg user">'+html+'</div>';
    box.appendChild(row);
    box.scrollTop=box.scrollHeight;
    return row;
}
async function wwiFlowAttempts(){
    var el=document.getElementById('flow-attempts');
    try{
        var r=await fetch('/api/v1/public/previews/attempts');
        var d=await r.json();
        if(d.data&&d.data.unlimited){wwiFlow.attempts=0;if(el)el.textContent='Modo constructor: prompts ilimitados.';}
        else{wwiFlow.attempts=(d.data&&d.data.used)||0;if(el)el.textContent='Intentos usados hoy: '+wwiFlow.attempts+' de 2.';}
    }catch(e){}
}
async function wwiFlowSend(){
    var input=document.getElementById('flow-input');
    var v=input.value.trim();
    if(!v)return;
    wwiFlowChat('user',wwiEsc(v));
    input.value='';
    var tr=wwiFlowChat('tia','<span class="typing"><i></i><i></i><i></i></span>');
    try{
        var r=await fetch('/api/v1/public/previews',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({prompt:v})});
        var d=await r.json();
        if(!d.ok){
            wwiFlowChat('tia','⚠ '+wwiEsc(d.message||'Error'));
            wwiFlowAttempts();
            if(d.data&&d.data.limit_reached){setTimeout(function(){wwiFlowGo(2)},1600);}
            return;
        }
        wwiFlow.uuid=d.data.uuid;
        wwiFlowAttempts();
        var ok=false;
        for(var i=0;i<40;i++){
            await new Promise(function(res){setTimeout(res,2500)});
            var s=await fetch('/api/v1/public/previews/'+wwiFlow.uuid);
            var sd=await s.json();
            if(sd.data&&sd.data.status==='ready'){ok=true;break}
            if(sd.data&&sd.data.status==='failed'){wwiFlowChat('tia','Algo falló al generar. Intenta de nuevo o usa el catálogo.');return}
        }
        if(ok){
            if(tr)tr.querySelector('.chat-msg').innerHTML='¡Listo! Tu vista previa está creada. 👇';
            wwiConfetti();
            document.getElementById('pv-view').onclick=function(){wwiFlowShowPreview(wwiFlow.uuid)};
            wwiFlowGo(1);
        }else{
            if(tr)tr.querySelector('.chat-msg').innerHTML='Está tardando más de lo normal. Revisa el preview o usa el catálogo.';
        }
    }catch(e){wwiFlowChat('tia','Error de conexión. Intenta de nuevo.')}
}
function wwiFlowShowPreview(uuid){
    document.getElementById('wwi-pv-frame').src='/api/v1/public/preview/'+uuid;
    document.getElementById('wwi-pv-modal').classList.add('open');
}
function wwiFlowDiscard(){
    wwiFlowAttempts();
    if(wwiFlow.attempts>=2){
        wwiFlowGo(2);
        return;
    }
    document.getElementById('flow-input').value='';
    wwiFlowGo(0);
}
async function wwiFlowLoadTpls(){
    var grid=document.getElementById('flow-tpl-grid');
    try{
        var r=await fetch('/api/v1/public/templates');
        var d=await r.json();
        var tpls=(d.data&&d.data.templates)||[];
        grid.innerHTML=tpls.map(function(t){return '<div class="tpl-card" onclick="wwiFlowPickTpl(\''+wwiEsc(t.slug)+'\')"><div class="nm">'+wwiEsc(t.name_es)+'</div><div class="cat">'+wwiEsc(t.category_slug||'')+'</div></div>'}).join('')||'Sin plantillas';
    }catch(e){grid.textContent='Error cargando plantillas'}
}
function wwiPvDevice(w){
    var box=document.getElementById('wwi-pv-box');
    if(box){box.style.maxWidth=w+'px';box.style.transition='max-width .3s ease'}
}
function wwiFlowPickTpl(slug){
    wwiFlow.template=slug;
    wwiFlowTplPreview(slug);
}
async function wwiFlowTplPreview(slug){
    try{
        var r=await fetch('/api/v1/public/previews/from-template',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({template:slug})});
        var d=await r.json();
        if(d.ok&&d.data&&d.data.uuid){
            wwiFlow.uuid=d.data.uuid;
            document.getElementById('pv-view').onclick=function(){wwiFlowShowPreview(wwiFlow.uuid)};
            document.getElementById('pv-title').textContent='Plantilla aplicada — vista previa lista';
            wwiFlowGo(1);
        }else{
            wwiFlowGo(3);
        }
    }catch(e){wwiFlowGo(3)}
}
async function wwiFlowLoadPlans(){
    var grid=document.getElementById('flow-plans-grid');
    try{
        var r=await fetch('/api/v1/public/plans');
        var d=await r.json();
        var core=['web-starter','web-business','web-catalog','ecommerce'];
        var all=(d.data||[]).filter(function(p){return p.price_cop>0&&p.slug!=='web-master'&&p.slug!=='web-catalog-pro'});
        var plans=all.filter(function(p){return core.indexOf(p.slug)>-1});
        if(plans.length<2)plans=all;
        plans=plans.slice(0,4);
        grid.innerHTML=plans.map(function(p){return '<div class="plan-mini" onclick="wwiFlowPickPlan('+p.id+')"><div class="nm">'+wwiEsc(p.name_es)+'</div><div class="pr">$'+Number(p.price_cop).toLocaleString('es-CO')+'</div><div style="font-size:10px;color:var(--muted)">COP · pago único</div></div>'}).join('')||'Sin planes';
    }catch(e){grid.textContent='Error cargando planes'}
}
function wwiFlowPickPlan(id){
    wwiFlow.planId=id;
    document.getElementById('wwi-xs-modal').classList.add('open');
}
function wwiXsConfirm(add){
    document.getElementById('wwi-xs-modal').classList.remove('open');
    wwiFlow.addons=add?['web-master']:[];
    wwiFlowGo(4);
}
function wwiFlowNormDomain(v){
    var s=String(v||'').trim().toLowerCase();
    s=s.replace(/^https?:\/\//,'').replace(/^www\./,'');
    s=s.split('/')[0].split('?')[0].split('#')[0].split('@').pop();
    s=s.replace(/[^a-z0-9.\-]/g,'').replace(/\.+/g,'.').replace(/^\.+|\.+$/g,'');
    return s;
}
function wwiFlowRecentLoad(){try{return JSON.parse(localStorage.getItem('wwi_dom_recent')||'[]')}catch(e){return[]}}
function wwiFlowRecentPush(name){var l=wwiFlowRecentLoad().filter(function(x){return x!==name});l.unshift(name);l=l.slice(0,5);try{localStorage.setItem('wwi_dom_recent',JSON.stringify(l))}catch(e){}wwiFlowRecentRender()}
function wwiFlowRecentRender(){
    var el=document.getElementById('flow-dom-recent');if(!el)return;
    var l=wwiFlowRecentLoad();
    if(!l.length){el.innerHTML='';return}
    el.innerHTML='<span style="opacity:.75">Recientes:</span>'+l.map(function(n){return '<button class="dom-chip" style="padding:4px 10px;font-size:11px" onclick="wwiFlowPickDom(\''+wwiEsc(n)+'\')">'+wwiEsc(n)+'</button>'}).join('')+'<button class="dom-chip" style="padding:4px 10px;font-size:11px" onclick="wwiFlowRecentClear()">limpiar</button>';
}
function wwiFlowRecentClear(){try{localStorage.removeItem('wwi_dom_recent')}catch(e){}wwiFlowRecentRender()}
function wwiFlowDomCard(cls,ic,title,desc,actions){
    return '<div class="dom-card '+cls+'"><div class="ic">'+ic+'</div><div style="flex:1"><div class="t">'+title+'</div><div class="d">'+(desc||'')+'</div>'+(actions||'')+'</div></div>';
}
function wwiFlowShowConfirm(name){
    var w=document.getElementById('flow-dom-confirm-wrap');
    var b=document.getElementById('flow-dom-confirm');
    if(w)w.style.display='flex';
    if(b)b.textContent=name?('Confirmar '+name+' y continuar'):'Confirmar dominio y continuar';
    if(w)setTimeout(function(){try{w.scrollIntoView({block:'nearest',behavior:'smooth'})}catch(e){}},140);
}
function wwiFlowTldChips(s,name){
    var row=document.getElementById('flow-dom-sugg');
    if(!row||!s.suggestions||!s.suggestions.length)return;
    var base=name.split('.')[0];
    row.innerHTML='<div style="width:100%;font-size:11px;color:var(--muted);margin-bottom:2px">Otras extensiones para <strong>'+wwiEsc(base)+'</strong>:</div>'+s.suggestions.map(function(x){
        var ok=x.state==='AVAILABLE';
        var cls=ok?'ok':(x.state==='CHECKING'?'warn':'bad');
        var dot=ok?'#34d399':(x.state==='CHECKING'?'#fbbf24':'#f87171');
        var price=ok&&x.price_reg!=null?'<span class="mono" style="font-size:10px">$'+x.price_reg+'/año</span>':'<span style="font-size:10px;opacity:.7">'+(x.state==='CHECKING'?'confirmar':'no disp.')+'</span>';
        return '<button class="dom-tld '+cls+'" onclick="wwiFlowPickDom(\''+wwiEsc(x.name)+'\')" '+(x.state==='TAKEN'?'':'')+'><span class="dot" style="background:'+dot+'"></span><span>'+wwiEsc(x.name)+'</span>'+price+'</button>';
    }).join('');
}
async function wwiFlowCheckDomain(opts){
    opts=opts||{};
    var input=document.getElementById('flow-dom-input');
    var btn=document.getElementById('flow-dom-btn');
    var res=document.getElementById('flow-dom-result');
    var name=wwiFlowNormDomain(input?input.value:'');
    if(input&&input.value!==name)input.value=name;
    if(!name){res.innerHTML=wwiFlowDomCard('bad','✕','Escribe un dominio','Ejemplo: tunegocio.com');return}
    if(!/^[a-z0-9-]+(\.[a-z0-9-]+)+$/.test(name)||name.length>80){res.innerHTML=wwiFlowDomCard('bad','✕','Formato inválido','Usa solo letras, números, guiones y al menos una extensión. Ejemplo: tunegocio.com');return}
    if(btn){btn.disabled=true;btn.textContent='Verificando…'}
    res.innerHTML=wwiFlowDomCard('warn','⏳','Verificando '+wwiEsc(name)+'…','Consultando el registrador, un momento.');
    try{
        var r=await fetch('/api/v1/public/domain/check?name='+encodeURIComponent(name));
        var d=await r.json();
        var s=d.data||{};
        wwiFlowRecentPush(name);
        var priceLine=(s.price_reg!=null?'<div class="pr">Registro $'+s.price_reg+' USD · Renovación $'+(s.price_ren!=null?s.price_ren:s.price_reg)+' USD</div>':'');
        if(s.state==='AVAILABLE'){
            wwiFlow.domain=name;
            try{localStorage.setItem('wwi_domain',name)}catch(e){}
            res.innerHTML=wwiFlowDomCard('ok','✓','¡Dominio disponible!','Puedes usarlo para tu sitio.',priceLine+'<div class="acts"><button class="btn btn-primary" style="padding:8px 16px;font-size:13px" onclick="wwiFlowConfirmDomain()">Usar este dominio</button><button class="btn btn-ghost" style="padding:8px 16px;font-size:13px" onclick="document.getElementById(\'flow-dom-input\').select()">Probar otro</button></div>');
            wwiFlowShowConfirm(name);
        }else if(s.state==='TAKEN'){
            wwiFlow.domain=null;
            res.innerHTML=wwiFlowDomCard('bad','✕','Dominio no disponible','Ya está registrado. Prueba con otro o elige una alternativa:',priceLine+'<div class="acts"><button class="btn btn-ghost" style="padding:8px 16px;font-size:13px" onclick="document.getElementById(\'flow-dom-input\').value=\'\';document.getElementById(\'flow-dom-input\').focus()">Buscar otro</button></div>');
            wwiFlowTldChips(s,name);
            if(!opts.noSuggest)wwiFlowSuggestDomains(name,true);
        }else if(s.state==='CHECKING'){
            wwiFlow.domain=name;
            try{localStorage.setItem('wwi_domain',name)}catch(e){}
            res.innerHTML=wwiFlowDomCard('warn','⏳','Parece libre','Sin DNS activo. Este TLD no tiene verificación en línea: se confirma al registrar.',priceLine+'<div class="acts"><button class="btn btn-primary" style="padding:8px 16px;font-size:13px" onclick="wwiFlowConfirmDomain()">Usar de todas formas</button></div>');
            wwiFlowShowConfirm(name);
            wwiFlowTldChips(s,name);
        }else if(s.state==='INVALID'){
            res.innerHTML=wwiFlowDomCard('bad','✕','Dominio inválido','Revisa el nombre e intenta de nuevo. Ejemplo: tunegocio.com');
        }else{
            res.innerHTML=wwiFlowDomCard('warn','!','No pudimos verificar',wwiEsc(s.message||'Intenta de nuevo en unos segundos.')+'<div class="acts"><button class="btn btn-ghost" style="padding:8px 16px;font-size:13px" onclick="wwiFlowCheckDomain()">Reintentar</button></div>');
        }
    }catch(e){res.innerHTML=wwiFlowDomCard('bad','✕','Sin conexión','No se pudo verificar. Revisa tu internet e intenta de nuevo.')}
    if(btn){btn.disabled=false;btn.textContent='Verificar'}
}
async function wwiFlowSuggestDomains(base,auto){
    var input=document.getElementById('flow-dom-input');
    var biz=base||wwiFlowNormDomain(input?input.value:'')||wwiFlow.bizName||'mi negocio';
    var row=document.getElementById('flow-dom-tia');
    if(!row)row=document.getElementById('flow-dom-sugg');
    if(row)row.innerHTML='<div style="width:100%;font-size:11px;color:var(--muted)">✨ TIA está buscando alternativas disponibles…</div>';
    try{
        var r=await fetch('/api/v1/public/previews/suggest-domains',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({business:biz})});
        var d=await r.json();
        var list=(d.data||[]).slice(0,6);
        if(!list.length){if(row)row.innerHTML='<div style="width:100%;font-size:11px;color:var(--muted)">Sin sugerencias por ahora. Prueba otro nombre.</div>';return}
        var checks=await Promise.all(list.map(function(n){return fetch('/api/v1/public/domain/check?name='+encodeURIComponent(n)).then(function(x){return x.json()}).then(function(j){return {name:n,data:j.data||{}}}).catch(function(){return {name:n,data:{state:'ERROR'}}})}));
        var avail=checks.filter(function(c){return c.data.state==='AVAILABLE'||c.data.state==='CHECKING'});
        var taken=checks.filter(function(c){return c.data.state==='TAKEN'});
        var html='<div style="width:100%;font-size:11px;color:var(--muted);margin-bottom:2px">'+(auto?'Alternativas disponibles:':'Sugerencias de TIA:')+'</div>';
        html+=avail.map(function(c){
            var ok=c.data.state==='AVAILABLE';
            var pr=c.data.price_reg!=null?'<span class="mono" style="font-size:10px">$'+c.data.price_reg+'/año</span>':'<span style="font-size:10px;opacity:.7">confirmar</span>';
            return '<button class="dom-tld '+(ok?'ok':'warn')+'" onclick="wwiFlowPickDom(\''+wwiEsc(c.name)+'\')"><span class="dot" style="background:'+(ok?'#34d399':'#fbbf24')+'"></span><span>'+wwiEsc(c.name)+'</span>'+pr+'</button>';
        }).join('');
        if(!avail.length)html+='<div style="width:100%;font-size:12px;color:var(--muted)">Ninguna sugerencia está libre. Escribe otro nombre.</div>';
        if(taken.length)html+='<div style="width:100%;font-size:10px;color:var(--muted);margin-top:4px">'+taken.length+' alternativa(s) también ocupadas.</div>';
        if(row)row.innerHTML=html;
    }catch(e){if(row)row.innerHTML='<div style="width:100%;font-size:11px;color:var(--muted)">No se pudieron cargar sugerencias.</div>'}
}
function wwiFlowPickDom(name){
    var input=document.getElementById('flow-dom-input');
    if(input)input.value=name;
    wwiFlowCheckDomain({noSuggest:true});
}
function wwiFlowOwnDomain(){
    var res=document.getElementById('flow-dom-result');
    var input=document.getElementById('flow-dom-input');
    var cur=input?wwiFlowNormDomain(input.value):'';
    wwiFlow.ownDomain=true;
    wwiFlow.domain=cur;
    try{localStorage.setItem('wwi_domain',cur)}catch(e){}
    res.innerHTML=wwiFlowDomCard('warn','🔗','Usar tu dominio actual','Escríbelo arriba (ej. tunegocio.com) y lo conectaremos: deberás apuntar el DNS a nuestro servidor. Te guiamos por correo.','<div class="acts"><button class="btn btn-primary" style="padding:8px 16px;font-size:13px" onclick="wwiFlowConfirmDomain()">Continuar con mi dominio</button></div>');
    wwiFlowShowConfirm(cur);
    if(input)input.focus();
}
function wwiFlowConfirmDomain(){
    var wrap=document.getElementById('flow-order-result');
    wrap.innerHTML='<div style="margin-top:10px"><input class="input" id="fo2-name" placeholder="Tu nombre" style="margin-bottom:8px"/><input class="input" id="fo2-email" type="email" placeholder="tu@email.com" style="margin-bottom:10px"/><button class="btn btn-primary" onclick="wwiFlowCreateOrder()">Crear mi pedido</button></div>';
    setTimeout(function(){try{wrap.scrollIntoView({block:'nearest',behavior:'smooth'})}catch(e){}},100);
}
async function wwiFlowCreateOrder(){
    var res=document.getElementById('flow-order-result');
    var payload={plan_id:wwiFlow.planId,customer_name:document.getElementById('fo2-name').value,customer_email:document.getElementById('fo2-email').value,domain_name:wwiFlow.domain||wwiFlowNormDomain(document.getElementById('flow-dom-input').value),addons:wwiFlow.addons,locale:'es'};
    if(!payload.customer_name||!payload.customer_email){res.innerHTML='<div style="color:var(--bad);font-size:12px">Completa nombre y email.</div>';return}
    try{
        var r=await fetch('/api/v1/public/orders',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        var d=await r.json();
        if(d.ok){
            var demoBtn=(wwiPayMode==='dummy')?'<button class="btn btn-primary" style="margin-top:10px" onclick="wwiDummyPay(\''+d.data.uuid+'\')">Pagar (modo demo)</button>':'';
            res.innerHTML='<div class="panel" style="padding:16px;border-color:rgba(52,211,153,.5)"><div style="color:var(--ok);font-size:13px;font-weight:600">✓ Pedido creado — '+wwiEsc(d.data.plan_name)+'</div><div class="mono" style="font-size:11px;color:var(--muted);margin-top:6px">Total $'+Number(d.data.total).toLocaleString('es-CO')+' COP'+(wwiFlow.addons.length?' · incluye Web Master':'')+'</div>'+demoBtn+'</div>';
        }else{res.innerHTML='<div style="color:var(--bad);font-size:12px">'+wwiEsc(d.message||'Error')+'</div>'}
    }catch(e){res.innerHTML='<div style="color:var(--bad);font-size:12px">Error de conexión</div>'}
}
document.addEventListener('DOMContentLoaded',function(){
    var fc=document.getElementById('flow-dom-confirm');
    if(fc)fc.addEventListener('click',wwiFlowConfirmDomain);
    var di=document.getElementById('flow-dom-input');
    if(di){
        var dt=null;
        di.addEventListener('input',function(){
            clearTimeout(dt);
            var v=wwiFlowNormDomain(di.value);
            if(v.indexOf('.')<0||v.length<5)return;
            dt=setTimeout(function(){
                if(di.value!==v)di.value=v;
                wwiFlowCheckDomain({noSuggest:true});
            },650);
        });
        di.addEventListener('blur',function(){var v=wwiFlowNormDomain(di.value);if(v&&di.value!==v)di.value=v});
    }
    var mic=document.getElementById('flow-mic');
    var SR=window.SpeechRecognition||window.webkitSpeechRecognition;
    if(mic&&SR){
        var rec=new SR();
        rec.lang='es-CO';
        rec.interimResults=false;
        mic.addEventListener('click',function(){try{rec.start();mic.style.borderColor='var(--accent)'}catch(e){}});
        rec.onresult=function(e){var t=e.results[0][0].transcript;var inp=document.getElementById('flow-input');if(inp)inp.value=t;mic.style.borderColor='';};
        rec.onerror=function(){mic.style.borderColor=''};
        rec.onend=function(){mic.style.borderColor=''};
    }else if(mic){mic.style.display='none'}
});
async function wwiHeroGpu(){
    if(window.__WWI_PREVIEW__)return;
    var cv=document.getElementById('wwi-hero-gpu');
    if(!cv||!navigator.gpu)return;
    if(window.matchMedia&&window.matchMedia('(prefers-reduced-motion: reduce)').matches)return;
    if(navigator.connection&&navigator.connection.saveData)return;
    try{
        if(navigator.getBattery){
            var bat=await navigator.getBattery();
            if(bat&&bat.level<0.2&&!bat.charging)return;
        }
    }catch(e){}
    try{
        var adapter=await navigator.gpu.requestAdapter();
        if(!adapter)return;
        var device=await adapter.requestDevice();
        device.lost.then(function(){cv.classList.remove('on')});
        var ctx=cv.getContext('webgpu');
        if(!ctx)return;
        var format=navigator.gpu.getPreferredCanvasFormat();
        ctx.configure({device:device,format:format,alphaMode:'premultiplied'});
        var wgsl=[
'struct U { time: f32, mx: f32, my: f32, pad: f32, res: vec2<f32>, pad2: vec2<f32> };',
'@group(0) @binding(0) var<uniform> u: U;',
'fn hash(p: vec2<f32>) -> f32 { return fract(sin(dot(p, vec2<f32>(127.1, 311.7))) * 43758.5453); }',
'fn vnoise(p: vec2<f32>) -> f32 {',
'  let i = floor(p); let f = fract(p);',
'  let a = hash(i); let b = hash(i + vec2<f32>(1.0, 0.0));',
'  let c = hash(i + vec2<f32>(0.0, 1.0)); let d = hash(i + vec2<f32>(1.0, 1.0));',
'  let uu = f * f * (3.0 - 2.0 * f);',
'  return mix(mix(a, b, uu.x), mix(c, d, uu.x), uu.y);',
'}',
'@vertex fn vs(@builtin(vertex_index) vi: u32) -> @builtin(position) vec4<f32> {',
'  var p = array<vec2<f32>, 3>(vec2<f32>(-1.0, -1.0), vec2<f32>(3.0, -1.0), vec2<f32>(-1.0, 3.0));',
'  return vec4<f32>(p[vi], 0.0, 1.0);',
'}',
'@fragment fn fs(@builtin(position) pos: vec4<f32>) -> @location(0) vec4<f32> {',
'  let uv = pos.xy / u.res;',
'  var p = vec2<f32>(uv.x * (u.res.x / u.res.y), uv.y) * 2.6;',
'  let t = u.time * 0.10;',
'  var v = 0.0; var q = p;',
'  for (var i = 0; i < 4; i = i + 1) {',
'    q = q + vec2<f32>(vnoise(q + t), vnoise(q.yx - t)) * 0.75;',
'    v = v + 0.5 / (1.0 + length(q - p));',
'  }',
'  let muv = vec2<f32>(u.mx / u.res.x, u.my / u.res.y);',
'  let glow = smoothstep(0.6, 0.0, distance(uv, muv)) * 0.25;',
'  let col = mix(vec3<f32>(0.13, 0.83, 0.93), vec3<f32>(0.55, 0.36, 0.96), clamp(v * 0.30, 0.0, 1.0));',
'  let fade = (1.0 - smoothstep(0.35, 1.0, uv.y)) * smoothstep(0.0, 0.15, uv.y);',
'  let amp = (0.10 + v * 0.10 + glow) * fade;',
'  return vec4<f32>(col * amp, amp);',
'}'
        ].join('\n');
        var module=device.createShaderModule({code:wgsl});
        if(module.getCompilationInfo){
            var info=await module.getCompilationInfo();
            if(info.messages.some(function(m){return m.type==='error'}))return;
        }
        var pipeline=device.createRenderPipeline({
            layout:'auto',
            vertex:{module:module,entryPoint:'vs'},
            fragment:{module:module,entryPoint:'fs',targets:[{format:format}]},
            primitive:{topology:'triangle-list'}
        });
        var ub=device.createBuffer({size:32,usage:GPUBufferUsage.UNIFORM|GPUBufferUsage.COPY_DST});
        var bg=device.createBindGroup({layout:pipeline.getBindGroupLayout(0),entries:[{binding:0,resource:{buffer:ub}}]});
        var data=new Float32Array(8);
        var mx=0,my=0,visible=false,running=false;
        document.addEventListener('mousemove',function(e){
            var r=cv.getBoundingClientRect();
            if(!r.width||!r.height)return;
            mx=(e.clientX-r.left)*(cv.width/r.width);
            my=(e.clientY-r.top)*(cv.height/r.height);
        },{passive:true});
        var resize=function(){
            var r=cv.getBoundingClientRect();
            var dpr=Math.min(window.devicePixelRatio||1,1.5);
            cv.width=Math.max(2,Math.round(r.width*dpr));
            cv.height=Math.max(2,Math.round(r.height*dpr));
        };
        resize();
        window.addEventListener('resize',resize);
        var t0=performance.now();
        var frame=function(){
            if(!visible||document.hidden){running=false;return}
            data[0]=(performance.now()-t0)/1000;data[1]=mx;data[2]=my;data[3]=0;
            data[4]=cv.width;data[5]=cv.height;data[6]=0;data[7]=0;
            device.queue.writeBuffer(ub,0,data);
            var enc=device.createCommandEncoder();
            var pass=enc.beginRenderPass({colorAttachments:[{view:ctx.getCurrentTexture().createView(),clearValue:{r:0,g:0,b:0,a:0},loadOp:'clear',storeOp:'store'}]});
            pass.setPipeline(pipeline);
            pass.setBindGroup(0,bg);
            pass.draw(3);
            pass.end();
            device.queue.submit([enc.finish()]);
            requestAnimationFrame(frame);
        };
        var start=function(){if(!running&&visible&&!document.hidden){running=true;requestAnimationFrame(frame)}};
        if('IntersectionObserver' in window){
            var io=new IntersectionObserver(function(es){es.forEach(function(en){visible=en.isIntersecting;if(visible)start()})},{threshold:0});
            io.observe(cv);
        }else{visible=true;start()}
        document.addEventListener('visibilitychange',function(){if(!document.hidden)start()});
        cv.classList.add('on');
    }catch(e){cv.classList.remove('on')}
}
if(document.readyState==='complete')wwiHeroGpu();else window.addEventListener('load',wwiHeroGpu);
</script>
<style>
.flow-overlay{position:fixed;inset:0;z-index:999;background:var(--overlay);backdrop-filter:blur(18px);display:none;align-items:center;justify-content:center;padding:20px}
.flow-overlay.open{display:flex}
.flow-shell{width:100%;max-width:880px;height:min(660px,92vh);background:linear-gradient(var(--panel),var(--panel)) padding-box,linear-gradient(135deg,rgba(124,60,255,.6),rgba(84,190,255,.45)) border-box;border:1px solid transparent;border-radius:22px;overflow:hidden;display:flex;flex-direction:column;position:relative;box-shadow:0 40px 110px rgba(4,4,18,.7)}
@supports(height:100dvh){.flow-shell{height:min(660px,calc(100dvh - 32px))}}
.flow-head{display:flex;align-items:center;gap:14px;padding:17px 24px;border-bottom:1px solid var(--border);position:relative;background:var(--soft)}
.flow-head .step-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;color:var(--accent2)}
.flow-head .dots{display:flex;gap:7px;flex:1}
.flow-head .dot{height:5px;flex:1;border-radius:3px;background:var(--border2);transition:background .3s}
.flow-head .dot.on{background:linear-gradient(120deg,#7c3cff,#b78cff)}
.flow-close{background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;line-height:1;transition:color .15s}
.flow-close:hover{color:var(--text)}
.flow-track{flex:1;display:flex;transition:transform .4s cubic-bezier(.22,1,.36,1);will-change:transform;min-height:0;overflow:hidden}
.flow-slide{min-width:100%;padding:30px 36px 34px;overflow-y:auto;overflow-x:hidden;box-sizing:border-box;height:100%;min-height:0;overscroll-behavior:contain;scrollbar-width:thin}
.flow-slide::-webkit-scrollbar{width:8px}
.flow-slide::-webkit-scrollbar-thumb{background:var(--border2);border-radius:4px}
.flow-h1{font-size:24px;font-weight:800;letter-spacing:-.02em;margin-bottom:9px}
.flow-sub{font-size:14px;color:var(--muted);margin-bottom:22px;line-height:1.7;max-width:580px}
.chat-box{display:flex;flex-direction:column;gap:13px;margin-bottom:17px;max-height:290px;overflow-y:auto;padding-right:4px}
.chat-row{display:flex;gap:11px;align-items:flex-end}
.chat-row.user{justify-content:flex-end}
.chat-ava{width:30px;height:30px;border-radius:10px;background:linear-gradient(135deg,#7c3cff,#b78cff);color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0;position:relative}
.chat-ava::after{content:'';position:absolute;inset:-3px;border-radius:13px;border:2px solid rgba(124,60,255,.5);animation:iqRing 2.2s ease-out infinite}
@keyframes iqRing{0%{opacity:.7;transform:scale(.9)}100%{opacity:0;transform:scale(1.25)}}
.chat-msg{padding:12px 16px;border-radius:16px;font-size:14px;max-width:78%;line-height:1.6}
.chat-msg.tia{background:rgba(124,60,255,.14);border:1px solid rgba(124,60,255,.3);border-bottom-left-radius:5px}
.chat-msg.user{background:rgba(84,190,255,.12);border:1px solid rgba(84,190,255,.3);border-bottom-right-radius:5px}
.typing{display:inline-flex;gap:4px;align-items:center;padding:2px 0}
.typing i{width:6px;height:6px;border-radius:50%;background:var(--accent2);opacity:.35;animation:iqTyping 1s infinite}
.typing i:nth-child(2){animation-delay:.15s}
.typing i:nth-child(3){animation-delay:.3s}
@keyframes iqTyping{0%,100%{opacity:.25;transform:translateY(0)}50%{opacity:1;transform:translateY(-3px)}}
.chips{display:flex;gap:9px;flex-wrap:wrap;margin-bottom:15px}
.chip{border:1px solid transparent;background:linear-gradient(var(--panel2),var(--panel2)) padding-box,linear-gradient(120deg,rgba(124,60,255,.4),rgba(84,190,255,.35)) border-box;color:var(--text);border-radius:999px;padding:8px 15px;font-size:12.5px;cursor:pointer;transition:all .18s;font-family:inherit}
.chip:hover{border-color:var(--accent2);color:var(--accent2);transform:translateY(-1px)}
.chat-in{display:flex;gap:9px}
.chat-in .input{flex:1}
.flow-actions{display:flex;gap:10px;margin-top:20px;flex-wrap:wrap}
.flow-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:11px}
.flow-modal{position:fixed;inset:0;z-index:1000;background:rgba(4,4,18,.8);backdrop-filter:blur(8px);display:none;align-items:center;justify-content:center;padding:18px}
.flow-modal.open{display:flex}
.flow-modal .box{width:100%;max-width:900px;height:min(85vh,640px);background:var(--panel);border:1px solid var(--border2);border-radius:18px;overflow:hidden;display:flex;flex-direction:column;transition:max-width .3s ease;box-shadow:var(--shadow)}
.flow-modal .box iframe{flex:1;border:none;background:#fff}
.confetti{position:fixed;z-index:1200;pointer-events:none;width:8px;height:8px;border-radius:2px;animation:iqConf 1.6s ease-out forwards}
@keyframes iqConf{0%{opacity:1;transform:translate(0,0) rotate(0)}100%{opacity:0;transform:translate(var(--dx),var(--dy)) rotate(540deg)}}
.chip:focus-visible,.dom-chip:focus-visible,.btn:focus-visible,.flow-close:focus-visible,.input:focus-visible{outline:2px solid var(--accent2);outline-offset:2px}
@media(prefers-reduced-motion:reduce){.flow-track{transition:none}.btn-pulse{animation:none}.typing i{animation:none}.chat-ava::after{animation:none}.chip:hover,.tpl-card:hover,.plan-mini:hover{transform:none}}
@media(max-width:720px){.flow-grid{grid-template-columns:repeat(2,1fr)}.flow-slide{padding:22px 18px}.chat-msg{max-width:88%}}
</style>
<style>
#wwi-hero-gpu{position:absolute;inset:0;width:100%;height:100%;z-index:0;pointer-events:none;opacity:0;transition:opacity 1.2s ease}
#wwi-hero-gpu.on{opacity:1}
#wwi-hero-gpu ~ .wrap{position:relative;z-index:1}
:root[data-theme='light'] #wwi-hero-gpu{display:none}
::view-transition-old(root),::view-transition-new(root){animation:none;mix-blend-mode:normal}
.wwi-section > section{padding:84px 0!important}
.wwi-iq-hero-solo{display:block!important;padding:0!important}
.wwi-iq-hero-solo .wwi-iq-hero-main{display:block;min-width:0}
.wwi-iq-hero .wwi-iq-hero-main section{padding:0!important}
.wwi-iq-tia{position:sticky;top:92px}
@media(max-width:1100px){.wwi-iq-tia{position:static}}
.tia.is-idle .wwi-ai-orb{animation-duration:4.5s}
.tia.is-thinking .wwi-ai-orb{animation-duration:1.4s}
.tia.is-building .wwi-ai-orb{animation-duration:.9s;box-shadow:0 0 34px rgba(139,77,255,.75),0 0 90px rgba(139,77,255,.35)}
.tia.is-waiting .wwi-ai-orb{animation-play-state:paused;opacity:.7}
.tia.is-success .wwi-ai-orb{box-shadow:0 0 30px rgba(53,212,154,.6),0 0 80px rgba(53,212,154,.3)}
.tia.is-error .wwi-ai-orb{box-shadow:0 0 30px rgba(255,102,122,.6),0 0 80px rgba(255,102,122,.3)}
.btn.loading{position:relative;pointer-events:none;color:transparent!important}
.btn.loading::after{content:'';position:absolute;width:18px;height:18px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spIQ .7s linear infinite}
.btn.success{border-color:rgba(53,212,154,.5)!important;box-shadow:0 0 0 2px rgba(53,212,154,.25)}
.btn.error{border-color:rgba(255,102,122,.5)!important;box-shadow:0 0 0 2px rgba(255,102,122,.25)}
.input.error{border-color:rgba(255,102,122,.6)!important}
.field-error{color:var(--bad);font-size:11.5px;margin-top:6px}
.img-swap{position:relative;overflow:hidden;border-radius:14px}
.img-swap img{transition:opacity .3s ease,transform .3s ease}
.img-swap img:last-child{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0}
.img-swap:hover img:first-child{opacity:0}
.img-swap:hover img:last-child{opacity:1;transform:scale(1.03)}
@media(prefers-reduced-motion:reduce){.btn.loading::after,.tia.is-building .wwi-ai-orb{animation:none}}
</style>

<div class="flow-overlay" id="wwi-flow">
  <div class="flow-shell">
    <div class="flow-head">
      <div class="step-label" id="flow-step-label">Paso 1 · Cuéntanos</div>
      <div class="dots"><div class="dot on" data-d="0"></div><div class="dot" data-d="1"></div><div class="dot" data-d="2"></div><div class="dot" data-d="3"></div><div class="dot" data-d="4"></div></div>
      <button class="flow-close" onclick="wwiFlowClose()" aria-label="Cerrar">&times;</button>
    </div>
    <div class="flow-track" id="wwi-flow-track">
      <div class="flow-slide" id="fs-prompt">
        <div class="flow-h1">Hablemos de tu sitio web</div>
        <div class="flow-sub">TIA construirá una vista previa a partir de lo que le cuentes. No necesitas saber de diseño. <span id="flow-voice-note"></span></div>
        <div class="chat-box" id="flow-chat"></div>
        <div class="chips" id="flow-chips">
          <button class="chip" onclick="wwiFlowChip(this)">Soy arquitecto y quiero mostrar mi trayectoria y proyectos</button>
          <button class="chip" onclick="wwiFlowChip(this)">Tengo un restaurante y quiero mostrar mi menú y reservas</button>
          <button class="chip" onclick="wwiFlowChip(this)">Soy abogado y quiero ofrecer consultas en línea</button>
        </div>
        <div class="chat-in"><input class="input" id="flow-input" placeholder="Escribe cómo quieres tu sitio web…" onkeydown="if(event.key==='Enter')wwiFlowSend()"/><button class="btn btn-ghost" id="flow-mic" title="Dictar por voz" aria-label="Dictar por voz">🎤</button><button class="btn btn-primary btn-pulse" onclick="wwiFlowSend()">Enviar</button></div>
        <div class="flow-sub" id="flow-attempts" style="margin-top:12px"></div>
        <div class="trust-row"><span>Vista previa gratis</span><span>Sin tarjeta de crédito</span><span>Listo en minutos</span></div>
        <div class="flow-actions"><button class="btn btn-ghost" onclick="wwiFlowGo(2)">Prefiero ver plantillas</button></div>
      </div>
      <div class="flow-slide" id="fs-preview">
        <div class="flow-h1" id="pv-title">Tu vista previa está lista</div>
        <div class="flow-sub">Esta es una muestra temporal (expira en 60 min). No es tu sitio final.</div>
        <div class="flow-actions">
          <button class="btn btn-primary" id="pv-view">Ver preview</button>
          <button class="btn btn-ghost" onclick="wwiFlowGo(0)">Editar el prompt</button>
          <button class="btn btn-ghost" onclick="wwiFlowDiscard()">Descartar, probar otro prompt</button>
        </div>
        <div class="flow-sub" style="margin-top:18px" id="pv-note"></div>
      </div>
      <div class="flow-slide" id="fs-templates">
        <div class="flow-h1">O elige una plantilla lista</div>
        <div class="flow-sub">Diseños por sector listos para usar. TIA los adapta a tu negocio.</div>
        <div class="flow-grid" id="flow-tpl-grid">Cargando…</div>
      </div>
      <div class="flow-slide" id="fs-plans">
        <div class="flow-h1">Elige tu plan</div>
        <div class="flow-sub">Todo incluido. Dominio el primer año. Sin sorpresas.</div>
        <div class="flow-grid" id="flow-plans-grid">Cargando…</div>
      </div>
      <div class="flow-slide" id="fs-domain">
        <div class="flow-h1">¿Qué dominio quieres?</div>
        <div class="flow-sub">Escríbelo y lo verificamos al instante con el registrador. También puedes dejar que TIA te sugiera opciones.</div>
        <div class="chat-in"><input class="input" id="flow-dom-input" placeholder="tunegocio.com" autocomplete="off" spellcheck="false" aria-label="Dominio" onkeydown="if(event.key==='Enter'){event.preventDefault();wwiFlowCheckDomain()}"/><button class="btn btn-primary" id="flow-dom-btn" onclick="wwiFlowCheckDomain()">Verificar</button></div>
        <div class="flow-actions" style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-ghost" onclick="wwiFlowSuggestDomains()">✨ Sugerir nombres con TIA</button>
          <button class="btn btn-ghost" onclick="wwiFlowOwnDomain()">Ya tengo dominio</button>
        </div>
        <div class="dom-recent" id="flow-dom-recent"></div>
        <div id="flow-dom-result" role="status" aria-live="polite" style="margin-top:14px"></div>
        <div class="dom-row" id="flow-dom-sugg"></div>
        <div class="dom-row" id="flow-dom-tia"></div>
        <div class="flow-actions" id="flow-dom-confirm-wrap" style="display:none"><button class="btn btn-primary" id="flow-dom-confirm">Confirmar dominio y continuar</button></div>
        <div id="flow-order-result" style="margin-top:14px"></div>
      </div>
    </div>
  </div>
</div>
<div class="flow-modal" id="wwi-pv-modal">
  <div class="box" id="wwi-pv-box">
    <div class="flow-head"><div style="flex:1;font-size:12px;font-weight:700;letter-spacing:.06em">VISTA PREVIA TEMPORAL</div><div style="display:flex;gap:4px"><button class="btn btn-ghost" style="padding:4px 10px;font-size:11px" onclick="wwiPvDevice(900)">Desktop</button><button class="btn btn-ghost" style="padding:4px 10px;font-size:11px" onclick="wwiPvDevice(700)">Tablet</button><button class="btn btn-ghost" style="padding:4px 10px;font-size:11px" onclick="wwiPvDevice(390)">Mobile</button></div><button class="flow-close" onclick="document.getElementById('wwi-pv-modal').classList.remove('open')">&times;</button></div>
    <iframe id="wwi-pv-frame" src="about:blank"></iframe>
  </div>
</div>
<div class="flow-modal" id="wwi-xs-modal">
  <div class="box" style="max-width:480px;height:auto;padding:26px">
    <div class="flow-h1" style="font-size:18px">Venta cruzada — Web Master</div>
    <div class="flow-sub">Mantenimiento mensual, mejoras continuas, TIA prioritaria y soporte dedicado por solo <strong class="mono">$149.000 COP/mes</strong>.</div>
    <div class="flow-actions" style="justify-content:flex-end">
      <button class="btn btn-ghost" onclick="wwiXsConfirm(false)">No, gracias</button>
      <button class="btn btn-primary" onclick="wwiXsConfirm(true)">Agregar al carrito</button>
    </div>
  </div>
</div>
<?php require ROOT_DIR . '/templates/themes/_shared/live-editor.php'; ?>
</body>
</html>
