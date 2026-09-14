<?php
use App\Core\Config;
use App\Services\CookieConsentService;
use App\Widgets\WidgetRegistry;

$page = $page ?? ['title' => Config::get('site_name', 'WWI'), 'meta_title' => '', 'meta_description' => '', 'slug' => ''];
$sections = $sections ?? [];
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
    --bg:#06080f;--bg2:#0a0e18;--panel:#0d1220;--panel2:#111828;
    --border:rgba(148,163,184,.14);--border2:rgba(148,163,184,.26);
    --text:#e6edf7;--muted:#8593ab;
    --accent:#22d3ee;--accent2:#8b5cf6;
    --ok:#34d399;--warn:#fbbf24;--bad:#f87171;
    --glow:rgba(34,211,238,.08);--radius:10px;color-scheme:dark;
    --nav-bg:rgba(6,8,15,.82);--soft:rgba(255,255,255,.015);--overlay:rgba(4,6,10,.94);
}
:root[data-theme='light']{
    --bg:#f4f6fb;--bg2:#eef1f8;--panel:#ffffff;--panel2:#f7f9fd;
    --border:rgba(15,23,42,.1);--border2:rgba(15,23,42,.22);
    --text:#0f172a;--muted:#5b6b84;
    --accent:#0891b2;--accent2:#7c3aed;
    --ok:#059669;--warn:#d97706;--bad:#dc2626;
    --glow:rgba(8,145,178,.08);--radius:10px;color-scheme:light;
    --nav-bg:rgba(244,246,251,.85);--soft:rgba(15,23,42,.02);--overlay:rgba(244,246,251,.95);
}
:root[data-theme='light'] .orbs i{opacity:.16}
:root[data-theme='light'] .aurora{opacity:.45}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',Segoe UI,system-ui;font-size:14px;line-height:1.45;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
.mono,.num,.metric{font-family:'JetBrains Mono',Consolas,monospace;font-variant-numeric:tabular-nums}
a{color:inherit;text-decoration:none}
.grid-bg{position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(var(--border) 1px,transparent 1px),linear-gradient(90deg,var(--border) 1px,transparent 1px);background-size:42px 42px;-webkit-mask-image:radial-gradient(ellipse 90% 60% at 50% 0%,#000 30%,transparent 75%);mask-image:radial-gradient(ellipse 90% 60% at 50% 0%,#000 30%,transparent 75%);opacity:.35}
main{position:relative;z-index:1}
.w-nav{position:fixed;top:0;left:0;right:0;z-index:100;height:60px;display:flex;align-items:center;justify-content:space-between;padding:0 28px;background:var(--nav-bg);backdrop-filter:blur(14px);border-bottom:1px solid var(--border)}
.w-nav-brand{display:flex;align-items:center;gap:10px}
.w-nav-logo{width:30px;height:30px;border-radius:8px;background:linear-gradient(135deg,#22d3ee,#8b5cf6);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;color:#041018}
.w-nav-brand span{font-size:13px;font-weight:700;letter-spacing:.08em}
.w-nav-links{display:flex;align-items:center;gap:22px}
.w-nav-links a{font-size:13px;color:var(--muted);transition:color .15s}
.w-nav-links a:hover{color:var(--accent)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid transparent;transition:all .15s}
.btn-ghost{background:var(--panel2);border-color:var(--border2);color:var(--text)}
.btn-ghost:hover{border-color:var(--accent)}
.btn-primary{background:linear-gradient(120deg,#22d3ee,#8b5cf6);color:#041018;font-weight:700}
.btn-primary:hover{filter:brightness(1.1)}
.btn-outline{background:transparent;border-color:var(--border2);color:var(--text)}
.btn-outline:hover{border-color:var(--accent);color:var(--accent)}
section{position:relative}
.wrap{max-width:1120px;margin:0 auto;padding:0 24px}
.badge{display:inline-block;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:600;letter-spacing:.04em;background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.45);color:var(--accent)}
.h-sec{text-align:center;margin-bottom:44px}
.h-sec h2{font-size:28px;font-weight:800;letter-spacing:-.02em;margin-bottom:10px}
.h-sec p{font-size:14px;color:var(--muted);max-width:620px;margin:0 auto}
.gradient-text{background:linear-gradient(120deg,#22d3ee,#8b5cf6);-webkit-background-clip:text;background-clip:text;color:transparent}
.panel{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius)}
.wwi-grid-3{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.wwi-grid-2{display:grid;grid-template-columns:repeat(2,1fr);gap:14px}
.card{background:var(--panel);border:1px solid var(--border);border-radius:var(--radius);padding:22px;transition:border .15s}
.card:hover{border-color:var(--border2)}
.card .ic{width:38px;height:38px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:17px;margin-bottom:12px;background:rgba(34,211,238,.08);border:1px solid rgba(34,211,238,.25)}
.card h3{font-size:14px;font-weight:700;margin-bottom:6px}
.card p{font-size:12.5px;color:var(--muted);line-height:1.6}
.input{width:100%;background:var(--bg2);border:1px solid var(--border2);border-radius:8px;padding:11px 14px;color:var(--text);font-size:13px;outline:none;font-family:inherit;transition:border .15s}
.input:focus{border-color:var(--accent)}
.plan-card{display:flex;flex-direction:column;gap:14px;padding:26px;position:relative}
.plan-card.featured{border-color:rgba(34,211,238,.45);box-shadow:0 0 24px var(--glow)}
.plan-name{font-size:13px;font-weight:600;text-transform:uppercase;letter-spacing:.02em;color:var(--muted)}
.plan-price{font-family:'JetBrains Mono',monospace;font-size:26px;font-weight:600}
.plan-price small{font-size:12px;color:var(--muted);font-weight:400}
.plan-feats{display:flex;flex-direction:column;gap:7px;flex:1}
.plan-feats div{font-size:12px;color:var(--muted);display:flex;gap:8px;align-items:center}
.plan-feats div::before{content:'';width:5px;height:5px;border-radius:50%;background:var(--accent)}
.step{display:flex;gap:16px;align-items:flex-start}
.step-num{font-family:'JetBrains Mono',monospace;font-size:13px;font-weight:600;min-width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;background:rgba(139,92,246,.1);border:1px solid rgba(139,92,246,.4);color:var(--accent2)}
.step h3{font-size:14px;font-weight:700;margin-bottom:5px}
.step p{font-size:12.5px;color:var(--muted);line-height:1.6}
.faq-item{border:1px solid var(--border);border-radius:var(--radius);margin-bottom:8px;overflow:hidden;background:var(--panel)}
.faq-q{display:flex;justify-content:space-between;align-items:center;padding:15px 18px;cursor:pointer;font-size:13.5px;font-weight:600;user-select:none}
.faq-q::after{content:'+';font-family:'JetBrains Mono',monospace;color:var(--accent);font-size:15px}
.faq-item.open .faq-q::after{content:'–'}
.faq-a{display:none;padding:0 18px 15px;font-size:12.5px;color:var(--muted);line-height:1.65}
.faq-item.open .faq-a{display:block}
.stat{text-align:center;padding:26px 14px}
.stat .v{font-family:'JetBrains Mono',monospace;font-size:22px;font-weight:600;background:linear-gradient(120deg,#22d3ee,#8b5cf6);-webkit-background-clip:text;background-clip:text;color:transparent}
.stat .l{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-top:5px}
.w-footer{border-top:1px solid var(--border);padding:44px 0 30px;background:var(--bg2)}
.w-footer .cols{display:grid;grid-template-columns:2fr 1fr 1fr;gap:28px}
.w-footer h4{font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:var(--muted);margin-bottom:12px}
.w-footer a,.w-footer p{display:block;font-size:12.5px;color:var(--muted);margin-bottom:8px}
.w-footer a:hover{color:var(--accent)}
.w-footer .legal{margin-top:30px;padding-top:18px;border-top:1px solid var(--border);font-size:11px;color:var(--muted);display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px}
.domain-box{display:flex;gap:10px;max-width:560px;margin:0 auto}
.domain-box .input{flex:1}
.domain-result{margin-top:10px;font-size:12px;min-height:18px;font-family:'JetBrains Mono',monospace}
.reveal{opacity:0;transform:translateY(14px);transition:opacity .5s ease,transform .5s ease}
.reveal.visible{opacity:1;transform:none}
.spin{display:none;width:22px;height:22px;border:2px solid var(--border2);border-top-color:var(--accent);border-radius:50%;animation:sp .8s linear infinite;margin:0 auto}
@keyframes sp{to{transform:rotate(360deg)}}
@media(max-width:1100px){.wwi-grid-3{grid-template-columns:repeat(2,1fr)}.w-footer .cols{grid-template-columns:1fr}}
@media(max-width:720px){.wwi-grid-3,.wwi-grid-2{grid-template-columns:1fr}.w-nav-links{display:none}.h-sec h2{font-size:23px}}
</style>
</head>
<body>
<div class="aurora"></div>
<div class="orbs"><i></i><i></i><i></i></div>
<div class="grid-bg"></div>
<nav class="w-nav">
  <div class="w-nav-brand"><div class="w-nav-logo">W</div><span>WWI</span></div>
  <div class="w-nav-links">
    <a href="#planes">Planes</a>
    <a href="#beneficios">Beneficios</a>
    <a href="#plantillas">Plantillas</a>
    <a href="#faq">FAQ</a>
  </div>
  <div style="display:flex;align-items:center;gap:10px">
    <button class="btn btn-ghost" id="wwi-theme-toggle" title="Cambiar tema" aria-label="Cambiar tema" style="width:36px;padding:8px 0;justify-content:center">☾</button>
    <a href="#planes" class="btn btn-primary">Crear mi sitio</a>
  </div>
</nav>
<main>
<?php foreach ($sections as $section):
    echo '<div class="wwi-section" data-sid="' . (int)($section['id'] ?? 0) . '" data-widget="' . htmlspecialchars((string)($section['widget_type'] ?? '')) . '">';
    if (!empty($section['widget_type']) && WidgetRegistry::get($section['widget_type'])):
        $config = json_decode($section['config'] ?? '{}', true) ?: [];
        echo WidgetRegistry::render($section['widget_type'], $config);
    elseif ($section['type'] === 'custom' || $section['type'] === 'html'):
        echo '<section>' . ($section['content'] ?? '') . '</section>';
    else:
        if ($section['content']) echo '<section>' . $section['content'] . '</section>';
    endif;
    echo '</div>';
endforeach; ?>
</main>
<?= CookieConsentService::render() ?>
<script>
(function(){
    var wwiPrev=!!window.__WWI_PREVIEW__;
    var r=document.querySelectorAll('.reveal');
    var o=new IntersectionObserver(function(e){e.forEach(function(el){if(el.isIntersecting)el.classList.add('visible')})},{threshold:0.08});
    r.forEach(function(el){o.observe(el)});
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
            var html='';
            plans.forEach(function(p,i){
                var feats=(p.features||[]).slice(0,8);
                html+='<div class="card plan-card'+(i===0?' featured':'')+'"><div><div class="plan-name">'+wwiEsc(p.name_es)+' <span style="color:var(--muted)">/ '+wwiEsc(p.name_en)+'</span></div><div class="plan-price" style="margin-top:8px">$'+Number(p.price_cop).toLocaleString('es-CO')+' <small>COP</small>'+(p.price_usd?' <small>· $'+p.price_usd+' USD</small>':'')+'</div>'+(p.billing_type==='one_time'?'<div style="font-size:11px;color:var(--muted);margin-top:2px">pago único · dominio incluido 1er año</div>':'<div style="font-size:11px;color:var(--muted);margin-top:2px">suscripción mensual</div>')+'</div><div class="plan-feats">'+feats.map(function(f){return '<div>'+wwiEsc(String(f).replace(/_/g,' '))+'</div>'}).join('')+'</div><a class="btn '+(i===0?'btn-primary':'btn-outline')+'" style="width:100%" href="#contacto" data-plan="'+p.id+'">Elegir '+wwiEsc(p.name_es)+'</a></div>';
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
function wwiUpdateTotal(){
    var sel=document.getElementById('wwi-co-plan');
    var tot=document.getElementById('wwi-co-total');
    if(!sel||!tot||!window.wwiPlans)return;
    var p=window.wwiPlans.find(function(x){return String(x.id)===String(sel.value)});
    tot.textContent=p?('Total: $'+Number(p.price_cop).toLocaleString('es-CO')+' COP · pago único'):'';
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
    btn.disabled=true;btn.style.opacity=.6;btn.textContent='Creando…';
    try{
        var r=await fetch('/api/v1/public/orders',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)});
        var d=await r.json();
        if(d.ok){
            var demoBtn=(wwiPayMode==='dummy')?'<button class="btn btn-primary" style="margin-top:10px" onclick="wwiDummyPay(\''+d.data.uuid+'\')">Pagar (modo demo)</button>':'<div style="font-size:11px;color:var(--muted);margin-top:6px">Te contactaremos con el link de pago seguro.</div>';
            res.innerHTML='<div class="panel" style="padding:16px;border-color:rgba(52,211,153,.5)"><div style="color:var(--ok);font-size:13px;font-weight:600">✓ Pedido creado — '+wwiEsc(d.data.plan_name)+'</div><div class="mono" style="font-size:11px;color:var(--muted);margin-top:6px"># '+wwiEsc(d.data.uuid)+' · $'+Number(d.data.total).toLocaleString('es-CO')+' COP · '+wwiEsc(d.data.status)+'</div>'+demoBtn+'</div>';
        }else{
            res.innerHTML='<div style="color:var(--bad);font-size:12px">'+wwiEsc(d.message||'Error al crear el pedido')+'</div>';
        }
    }catch(e){res.innerHTML='<div style="color:var(--bad);font-size:12px">Error de conexión</div>'}
    btn.disabled=false;btn.style.opacity=1;btn.textContent='Crear pedido';
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
            res.innerHTML='<div class="panel" style="padding:16px;border-color:rgba(52,211,153,.5)"><div style="color:var(--ok);font-size:13px;font-weight:600">✓ Pago demo aprobado — '+wwiEsc((sd.data&&sd.data.status)||'')+'</div><div style="font-size:11px;color:var(--muted);margin-top:6px">El provisioning se ejecuta automáticamente en la cola de jobs.</div></div>';
        }else{
            res.innerHTML='<div style="color:var(--bad);font-size:12px">'+wwiEsc(d.message||'Error')+'</div>';
        }
    }catch(e){res.innerHTML='<div style="color:var(--bad);font-size:12px">Error de conexión</div>'}
}
async function wwiLoadTemplates(){
    var boxes=document.querySelectorAll('[data-wwi-templates]');
    if(!boxes.length)return;
    try{
        var r=await fetch('/api/v1/public/templates');
        var d=await r.json();
        var tpls=(d.data&&d.data.templates)||[];
        boxes.forEach(function(box){
            box.innerHTML=tpls.map(function(t){
                return '<div class="card" style="text-align:center;padding:20px"><div style="font-size:22px;margin-bottom:8px">W</div><div style="font-size:13px;font-weight:600">'+wwiEsc(t.name_es)+'</div><div style="font-size:11px;color:var(--muted);margin-top:3px">'+wwiEsc(t.name_en)+'</div><div style="font-size:10px;color:var(--accent);margin-top:6px;text-transform:uppercase;letter-spacing:.05em">'+wwiEsc(t.category_slug||'')+'</div></div>';
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
function wwiFlowOpen(){
    document.getElementById('wwi-flow').classList.add('open');
    wwiFlowGo(0);
    wwiFlowAttempts();
    if(!wwiFlow.booted){
        wwiFlowChat('tia','¡Hola! Soy TIA. Escribe cómo quieres tu sitio web. Por ejemplo: <em>soy arquitecto y quiero que mi sitio muestre mi trayectoria y los proyectos que he ejecutado</em>.');
        wwiFlow.booted=true;
    }
}
function wwiFlowClose(){document.getElementById('wwi-flow').classList.remove('open')}
function wwiFlowGo(i){
    wwiFlow.idx=i;
    document.getElementById('wwi-flow-track').style.transform='translateX(-'+(i*100)+'%)';
    document.querySelectorAll('.flow-head .dot').forEach(function(d){d.classList.toggle('on',+d.dataset.d===i)});
    var labels=['Cuéntanos tu negocio','Tu vista previa','Elige plantilla','Elige tu plan','Tu dominio'];
    var lbl=document.getElementById('flow-step-label');
    if(lbl)lbl.textContent='Paso '+(i+1)+' · '+labels[i];
    if(i===2)wwiFlowLoadTpls();
    if(i===3)wwiFlowLoadPlans();
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
        var plans=(d.data||[]).filter(function(p){return p.price_cop>0&&p.slug!=='web-master'&&p.slug!=='web-catalog-pro'}).slice(0,3);
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
async function wwiFlowCheckDomain(){
    var input=document.getElementById('flow-dom-input');
    var res=document.getElementById('flow-dom-result');
    var name=input.value.trim();
    if(!name)return;
    res.textContent='Verificando…';
    try{
        var r=await fetch('/api/v1/public/domain/check?name='+encodeURIComponent(name));
        var d=await r.json();
        var s=d.data||{};
        if(s.state==='AVAILABLE'){
            wwiFlow.domain=name;
            res.innerHTML='<span style="color:var(--ok)">✓ '+wwiEsc(name)+' está disponible.</span>';
            document.getElementById('flow-dom-confirm-wrap').style.display='flex';
        }else{
            res.innerHTML='<span style="color:var(--bad)">'+wwiEsc(s.state)+' — '+wwiEsc(s.message||'')+'</span>';
        }
    }catch(e){res.textContent='No se pudo verificar.'}
}
async function wwiFlowSuggestDomains(){
    var input=document.getElementById('flow-dom-input');
    var business=input.value.trim()||wwiFlow.bizName||'mi negocio';
    wwiFlowChat('tia','Sugiriendo dominios…');
    var res=document.getElementById('flow-dom-sugg');
    try{
        var r=await fetch('/api/v1/public/previews/suggest-domains',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({business:business})});
        var d=await r.json();
        var list=d.data||[];
        res.innerHTML=list.map(function(x){return '<button class="dom-chip" onclick="wwiFlowPickDom(\''+wwiEsc(x)+'\')">'+wwiEsc(x)+'</button>'}).join('');
    }catch(e){res.textContent='No se pudo sugerir.'}
}
function wwiFlowPickDom(name){
    document.getElementById('flow-dom-input').value=name;
    document.querySelectorAll('#flow-dom-sugg .dom-chip').forEach(function(c){c.classList.toggle('sel',c.textContent===name)});
    wwiFlowCheckDomain();
}
function wwiFlowConfirmDomain(){
    var wrap=document.getElementById('flow-order-result');
    wrap.innerHTML='<div style="margin-top:10px"><input class="input" id="fo2-name" placeholder="Tu nombre" style="margin-bottom:8px"/><input class="input" id="fo2-email" type="email" placeholder="tu@email.com" style="margin-bottom:10px"/><button class="btn btn-primary" onclick="wwiFlowCreateOrder()">Crear mi pedido</button></div>';
}
async function wwiFlowCreateOrder(){
    var res=document.getElementById('flow-order-result');
    var payload={plan_id:wwiFlow.planId,customer_name:document.getElementById('fo2-name').value,customer_email:document.getElementById('fo2-email').value,domain_name:wwiFlow.domain||document.getElementById('flow-dom-input').value,addons:wwiFlow.addons,locale:'es'};
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
.flow-overlay{position:fixed;inset:0;z-index:999;background:var(--overlay);backdrop-filter:blur(14px);display:none;align-items:center;justify-content:center;padding:20px}
.flow-overlay.open{display:flex}
.flow-shell{width:100%;max-width:860px;height:min(640px,92vh);background:linear-gradient(var(--panel),var(--panel)) padding-box,linear-gradient(135deg,rgba(34,211,238,.5),rgba(139,92,246,.5)) border-box;border:1px solid transparent;border-radius:18px;overflow:hidden;display:flex;flex-direction:column;position:relative;box-shadow:0 30px 90px rgba(0,0,0,.6)}
.flow-head{display:flex;align-items:center;gap:14px;padding:16px 22px;border-bottom:1px solid var(--border);position:relative;background:var(--soft)}
.flow-head .step-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.09em;color:var(--accent)}
.flow-head .dots{display:flex;gap:6px;flex:1}
.flow-head .dot{height:5px;flex:1;border-radius:3px;background:var(--border2);transition:background .3s}
.flow-head .dot.on{background:linear-gradient(120deg,#22d3ee,#8b5cf6)}
.flow-close{background:none;border:none;color:var(--muted);font-size:20px;cursor:pointer;line-height:1;transition:color .15s}
.flow-close:hover{color:var(--text)}
.flow-track{flex:1;display:flex;transition:transform .38s cubic-bezier(.4,0,.2,1);will-change:transform}
.flow-slide{min-width:100%;padding:28px 34px;overflow-y:auto;box-sizing:border-box}
.flow-h1{font-size:23px;font-weight:800;letter-spacing:-.015em;margin-bottom:8px}
.flow-sub{font-size:13.5px;color:var(--muted);margin-bottom:20px;line-height:1.65;max-width:560px}
.chat-box{display:flex;flex-direction:column;gap:12px;margin-bottom:16px;max-height:290px;overflow-y:auto;padding-right:4px}
.chat-row{display:flex;gap:10px;align-items:flex-end}
.chat-row.user{justify-content:flex-end}
.chat-ava{width:28px;height:28px;border-radius:9px;background:linear-gradient(135deg,#22d3ee,#8b5cf6);color:#041018;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:800;flex-shrink:0}
.chat-msg{padding:11px 15px;border-radius:14px;font-size:13.5px;max-width:78%;line-height:1.55}
.chat-msg.tia{background:rgba(139,92,246,.12);border:1px solid rgba(139,92,246,.3);border-bottom-left-radius:4px}
.chat-msg.user{background:rgba(34,211,238,.1);border:1px solid rgba(34,211,238,.35);border-bottom-right-radius:4px}
.typing{display:inline-flex;gap:4px;align-items:center;padding:2px 0}
.typing i{width:6px;height:6px;border-radius:50%;background:var(--accent);opacity:.35;animation:tp 1s infinite}
.typing i:nth-child(2){animation-delay:.15s}
.typing i:nth-child(3){animation-delay:.3s}
@keyframes tp{0%,100%{opacity:.25;transform:translateY(0)}50%{opacity:1;transform:translateY(-3px)}}
.chips{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.chip{border:1px solid var(--border2);background:var(--panel2);color:var(--text);border-radius:999px;padding:7px 14px;font-size:12px;cursor:pointer;transition:all .15s;font-family:inherit}
.chip:hover{border-color:var(--accent);color:var(--accent);transform:translateY(-1px)}
.trust-row{display:flex;gap:16px;flex-wrap:wrap;justify-content:center;margin-top:16px;font-size:11px;color:var(--muted)}
.trust-row span::before{content:'✓ ';color:var(--ok);font-weight:700}
.chat-in{display:flex;gap:8px}
.chat-in .input{flex:1}
.btn-pulse{animation:bp 2.4s infinite}
@keyframes bp{0%,100%{box-shadow:0 0 0 0 rgba(34,211,238,.35)}50%{box-shadow:0 0 0 8px rgba(34,211,238,0)}}
.flow-actions{display:flex;gap:10px;margin-top:18px;flex-wrap:wrap}
.flow-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.flow-grid .tpl-card{border:1px solid var(--border);border-radius:12px;padding:16px;text-align:center;cursor:pointer;transition:all .15s;background:var(--panel2)}
.flow-grid .tpl-card:hover{border-color:var(--accent);transform:translateY(-2px)}
.tpl-card .nm{font-size:12.5px;font-weight:700}
.tpl-card .cat{font-size:10px;color:var(--muted);margin-top:4px;text-transform:uppercase;letter-spacing:.06em}
.plan-mini{border:1px solid var(--border);border-radius:14px;padding:20px;text-align:center;cursor:pointer;transition:all .15s;background:var(--panel2)}
.plan-mini:hover{border-color:var(--accent);transform:translateY(-2px)}
.plan-mini .pr{font-family:'JetBrains Mono',monospace;font-size:21px;font-weight:600;margin:8px 0}
.plan-mini .nm{font-size:12.5px;font-weight:700}
.dom-row{display:flex;gap:8px;flex-wrap:wrap;margin-top:10px}
.dom-chip{border:1px solid var(--border2);border-radius:9px;padding:8px 14px;font-family:'JetBrains Mono',monospace;font-size:12px;cursor:pointer;transition:all .15s;background:var(--panel2);color:var(--text)}
.dom-chip:hover{border-color:var(--accent);color:var(--accent)}
.dom-chip.sel{border-color:var(--accent);background:rgba(34,211,238,.1)}
.flow-modal{position:fixed;inset:0;z-index:1000;background:rgba(0,0,0,.78);display:none;align-items:center;justify-content:center;padding:18px}
.flow-modal.open{display:flex}
.flow-modal .box{width:100%;max-width:900px;height:min(85vh,640px);background:var(--panel);border:1px solid var(--border2);border-radius:16px;overflow:hidden;display:flex;flex-direction:column;transition:max-width .3s ease}
.flow-modal .box iframe{flex:1;border:none;background:#fff}
.chip:focus-visible,.dom-chip:focus-visible,.btn:focus-visible,.flow-close:focus-visible,.input:focus-visible{outline:2px solid var(--accent);outline-offset:2px}
@media(prefers-reduced-motion:reduce){.flow-track{transition:none}.btn-pulse{animation:none}.typing i{animation:none}.chip:hover,.tpl-card:hover,.plan-mini:hover{transform:none}}
@media(max-width:720px){.flow-grid{grid-template-columns:repeat(2,1fr)}.flow-slide{padding:22px 18px}.chat-msg{max-width:88%}}
.aurora{position:fixed;inset:-20%;z-index:0;pointer-events:none;background:radial-gradient(40% 50% at 20% 20%,rgba(34,211,238,.22),transparent 60%),radial-gradient(45% 55% at 80% 15%,rgba(139,92,246,.25),transparent 60%),radial-gradient(40% 45% at 65% 80%,rgba(236,72,153,.16),transparent 60%),radial-gradient(35% 40% at 15% 75%,rgba(52,211,153,.14),transparent 60%);filter:blur(42px);animation:aurora 18s ease-in-out infinite alternate}
@keyframes aurora{0%{transform:translate3d(0,0,0) scale(1)}50%{transform:translate3d(2%,-2%,0) scale(1.06)}100%{transform:translate3d(-2%,1%,0) scale(1.03)}}
.orbs i{position:fixed;border-radius:50%;filter:blur(64px);opacity:.42;z-index:0;pointer-events:none}
.orbs i:nth-child(1){width:340px;height:340px;background:#22d3ee;top:12%;left:6%;animation:orb1 14s ease-in-out infinite}
.orbs i:nth-child(2){width:280px;height:280px;background:#8b5cf6;top:30%;right:8%;animation:orb2 17s ease-in-out infinite}
.orbs i:nth-child(3){width:240px;height:240px;background:#ec4899;bottom:12%;left:35%;animation:orb3 20s ease-in-out infinite}
@keyframes orb1{0%,100%{transform:translate(0,0)}50%{transform:translate(40px,-30px)}}
@keyframes orb2{0%,100%{transform:translate(0,0)}50%{transform:translate(-50px,40px)}}
@keyframes orb3{0%,100%{transform:translate(0,0)}50%{transform:translate(30px,-50px)}}
.gradient-text{background:linear-gradient(120deg,#22d3ee,#8b5cf6,#ec4899,#22d3ee);background-size:300% 300%;-webkit-background-clip:text;background-clip:text;color:transparent;animation:gtext 8s ease infinite}
@keyframes gtext{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
.btn-primary{position:relative;overflow:hidden}
.btn-primary::after{content:'';position:absolute;top:0;left:-120%;width:60%;height:100%;background:linear-gradient(100deg,transparent,rgba(255,255,255,.4),transparent);transform:skewX(-20deg);animation:shine 3.4s ease-in-out infinite}
@keyframes shine{0%,55%{left:-120%}80%,100%{left:140%}}
.marquee{overflow:hidden;border-top:1px solid var(--border);border-bottom:1px solid var(--border);background:var(--soft);padding:14px 0;margin-top:38px}
.marquee .track{display:flex;gap:36px;white-space:nowrap;animation:mq 30s linear infinite;width:max-content}
.marquee span{font-size:12px;color:var(--muted);letter-spacing:.06em;text-transform:uppercase}
.marquee span b{color:var(--accent)}
@keyframes mq{to{transform:translateX(-50%)}}
.card .ic{background:linear-gradient(135deg,rgba(34,211,238,.14),rgba(139,92,246,.14));border-color:rgba(139,92,246,.3)}
.card:hover{transform:translateY(-3px);box-shadow:0 14px 40px rgba(34,211,238,.08)}
.step-num{background:linear-gradient(135deg,rgba(34,211,238,.16),rgba(139,92,246,.18));box-shadow:0 0 18px rgba(34,211,238,.15)}
.chat-ava{position:relative}
.chat-ava::after{content:'';position:absolute;inset:-3px;border-radius:12px;border:2px solid rgba(34,211,238,.5);animation:ring 2.2s ease-out infinite}
@keyframes ring{0%{opacity:.7;transform:scale(.9)}100%{opacity:0;transform:scale(1.25)}}
.chip{background:linear-gradient(var(--panel2),var(--panel2)) padding-box,linear-gradient(120deg,rgba(34,211,238,.35),rgba(139,92,246,.35)) border-box;border:1px solid transparent}
.chip:hover{background:linear-gradient(120deg,rgba(34,211,238,.12),rgba(139,92,246,.14));color:var(--text)}
.confetti{position:fixed;z-index:1200;pointer-events:none;width:8px;height:8px;border-radius:2px;animation:conf 1.6s ease-out forwards}
@keyframes conf{0%{opacity:1;transform:translate(0,0) rotate(0)}100%{opacity:0;transform:translate(var(--dx),var(--dy)) rotate(540deg)}}
.cursor-glow{position:fixed;inset:0;z-index:0;pointer-events:none;opacity:0;transition:opacity .4s;background:radial-gradient(520px circle at var(--cx,50%) var(--cy,0%),rgba(34,211,238,.1),rgba(139,92,246,.06) 45%,transparent 70%)}
.cursor-glow.on{opacity:1}
.scroll-progress{position:fixed;top:0;left:0;right:0;height:2px;z-index:101;background:linear-gradient(90deg,#22d3ee,#8b5cf6,#ec4899);transform:scaleX(0);transform-origin:0 50%}
@supports (animation-timeline: scroll()){
    .scroll-progress{animation:wwi-progress linear;animation-timeline:scroll(root)}
    @keyframes wwi-progress{from{transform:scaleX(0)}to{transform:scaleX(1)}}
}
@media(prefers-reduced-motion:no-preference){
    @supports (animation-timeline: view()){
        .reveal{opacity:1;transform:none;animation:wwi-reveal both;animation-timeline:view();animation-range:entry 5% entry 55%}
        @keyframes wwi-reveal{from{opacity:0;transform:translateY(16px)}to{opacity:1;transform:none}}
    }
}
::view-transition-old(root),::view-transition-new(root){animation:none;mix-blend-mode:normal}
#wwi-hero-gpu{position:absolute;inset:0;width:100%;height:100%;z-index:0;pointer-events:none;opacity:0;transition:opacity 1.2s ease}
#wwi-hero-gpu.on{opacity:1}
#wwi-hero-gpu ~ .wrap{position:relative;z-index:1}
:root[data-theme='light'] #wwi-hero-gpu{display:none}
@media(prefers-reduced-motion:reduce){.aurora,.orbs i,.gradient-text,.btn-primary::after,.marquee .track,.chat-ava::after{animation:none}}
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
        <div class="flow-sub">Escríbelo o deja que TIA te sugiera opciones.</div>
        <div class="chat-in"><input class="input" id="flow-dom-input" placeholder="tunegocio.com" onkeydown="if(event.key==='Enter')wwiFlowCheckDomain()"/><button class="btn btn-primary" onclick="wwiFlowCheckDomain()">Verificar</button></div>
        <div class="flow-actions"><button class="btn btn-ghost" onclick="wwiFlowSuggestDomains()">Sugerir nombres con TIA</button></div>
        <div class="dom-row" id="flow-dom-sugg"></div>
        <div id="flow-dom-result" class="flow-sub" style="margin-top:14px"></div>
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
<script>window.__WWI_EDIT_CTX__=<?= json_encode(['pageId' => (int)($page['id'] ?? 0), 'pageTitle' => (string)($page['title'] ?? ''), 'pageSlug' => (string)($page['slug'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<style>
.wwi-ed-bar{position:fixed;bottom:18px;right:18px;z-index:99998;background:var(--panel);border:1px solid var(--border2);border-radius:999px;padding:8px 14px;display:flex;gap:12px;align-items:center;font-size:12px;box-shadow:0 10px 30px rgba(0,0,0,.4)}
.wwi-ed-bar .u{color:var(--muted);font-size:11px}
.wwi-ed-toggle{display:flex;align-items:center;gap:6px;cursor:pointer;font-weight:600}
.wwi-ed-side{position:fixed;top:0;right:0;bottom:0;width:380px;max-width:92vw;background:var(--panel);border-left:1px solid var(--border2);z-index:100000;display:flex;flex-direction:column;transform:translateX(105%);transition:transform .25s cubic-bezier(.22,1,.36,1);box-shadow:-20px 0 60px rgba(0,0,0,.45)}
.wwi-ed-open .wwi-ed-side{transform:none}
.wwi-ed-resize{position:absolute;left:-3px;top:0;bottom:0;width:6px;cursor:col-resize}
.wwi-ed-resize:hover{background:rgba(34,211,238,.4)}
.wwi-ed-head{padding:14px 16px;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px}
.wwi-ed-crumb{flex:1;min-width:0}
.wwi-ed-crumb b{display:block;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wwi-ed-crumb span{font-size:10px;color:var(--muted)}
.wwi-ed-x{background:none;border:none;color:var(--muted);font-size:18px;cursor:pointer}
.wwi-ed-tabs{display:flex;border-bottom:1px solid var(--border)}
.wwi-ed-tab{flex:1;padding:10px 6px;background:none;border:none;color:var(--muted);font-size:12px;font-weight:600;cursor:pointer;border-bottom:2px solid transparent}
.wwi-ed-tab.on{color:var(--text);border-bottom-color:var(--accent)}
.wwi-ed-body{flex:1;overflow-y:auto;padding:16px}
.wwi-ed-body label{display:block;font-size:11px;color:var(--muted);margin-bottom:10px}
.wwi-ed-body input,.wwi-ed-body textarea,.wwi-ed-body select{width:100%;margin-top:4px;background:var(--bg2);border:1px solid var(--border2);border-radius:8px;padding:8px 10px;color:var(--text);font-size:12px;font-family:inherit;outline:none}
.wwi-ed-body textarea{min-height:70px;resize:vertical}
.wwi-ed-check{display:flex;align-items:center;gap:8px}
.wwi-ed-check input{width:auto;margin:0}
.wwi-ed-actions{display:flex;gap:6px;flex-wrap:wrap;margin-top:12px}
.wwi-ed-save{background:linear-gradient(120deg,#22d3ee,#8b5cf6);color:#041018;font-weight:700;border:none;border-radius:8px;padding:8px 14px;cursor:pointer;font-size:12px}
.wwi-ed-btn{background:var(--panel2);border:1px solid var(--border2);color:var(--text);border-radius:8px;padding:7px 12px;cursor:pointer;font-size:12px;font-family:inherit}
.wwi-ed-btn:hover{border-color:var(--accent);color:var(--accent)}
.wwi-ed-cat{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.wwi-ed-cat button{background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:12px 10px;color:var(--text);cursor:pointer;font-size:12px;text-align:left;transition:border-color .15s,transform .15s;font-family:inherit}
.wwi-ed-cat button:hover{border-color:var(--accent);transform:translateY(-2px)}
.wwi-ed-cat button i{display:block;font-style:normal;font-size:16px;margin-bottom:4px}
.wwi-ed-cat button small{display:block;color:var(--muted);font-size:10px;margin-top:2px}
.wwi-ed-tree-item{display:flex;align-items:center;gap:6px;padding:8px 10px;border:1px solid var(--border);border-radius:8px;margin-bottom:6px;cursor:pointer;font-size:12px;background:var(--bg2)}
.wwi-ed-tree-item:hover{border-color:var(--border2)}
.wwi-ed-tree-item.on{border-color:var(--accent);background:rgba(34,211,238,.08)}
.wwi-ed-tree-item .nm{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.wwi-ed-media{display:grid;grid-template-columns:1fr 1fr 1fr;gap:8px}
.wwi-ed-media button{padding:0;border:1px solid var(--border);border-radius:8px;overflow:hidden;cursor:pointer;background:var(--bg2);aspect-ratio:1}
.wwi-ed-media img{width:100%;height:100%;object-fit:cover;display:block}
.wwi-ed-hint{font-size:12px;color:var(--muted);line-height:1.7}
.wwi-ed-toast{position:fixed;bottom:80px;right:18px;z-index:100001;background:rgba(52,211,153,.16);border:1px solid rgba(52,211,153,.4);color:#34d399;padding:10px 16px;border-radius:10px;font-size:12px;backdrop-filter:blur(8px)}
.wwi-ed-toast.err{background:rgba(248,113,113,.16);border-color:rgba(248,113,113,.4);color:#f87171}
.wwi-section{position:relative}
.wwi-edit-on .wwi-section{outline:1px dashed transparent;transition:outline-color .2s}
.wwi-edit-on .wwi-section:hover{outline-color:rgba(34,211,238,.5)}
.wwi-edit-on .wwi-section.wwi-sel{outline:2px solid rgba(34,211,238,.8);outline-offset:-2px}
.wwi-edit-on [data-editable]{cursor:text}
.wwi-edit-on [data-editable]:hover{outline:1px dashed rgba(139,92,246,.8);outline-offset:2px;border-radius:4px}
.wwi-edit-on [data-editable].wwi-sel-el{outline:2px solid rgba(139,92,246,.9);outline-offset:2px;border-radius:4px}
.wwi-ed-tools{display:none;position:sticky;top:72px;z-index:99990;justify-content:flex-end;gap:4px;height:34px;margin:0 10px -34px 0}
.wwi-edit-on .wwi-section:hover>.wwi-ed-tools{display:flex}
.wwi-ed-tools button{width:30px;height:30px;border-radius:8px;border:1px solid var(--border2);background:rgba(6,8,15,.85);color:var(--text);cursor:pointer;font-size:13px;backdrop-filter:blur(8px)}
.wwi-ed-tools button:hover{border-color:var(--accent);color:var(--accent)}
.wwi-dropzone{height:10px;margin:8px 0;border-radius:6px;background:rgba(34,211,238,.10);border:1px dashed rgba(34,211,238,.45);transition:background .15s}
.wwi-dropzone.over{background:rgba(34,211,238,.4)}
.wwi-client .wwi-ed-tab[data-tab="add"]{display:none}
.wwi-client .wwi-ed-tools button[data-act="up"],.wwi-client .wwi-ed-tools button[data-act="down"],.wwi-client .wwi-ed-tools button[data-act="toggle"]{display:none}
@media(prefers-reduced-motion:reduce){.wwi-edit-on .wwi-section{transition:none}.wwi-ed-side{transition:none}.wwi-dropzone{transition:none}}
</style>
<script>
(function(){
    var token=null;
    try{token=localStorage.getItem('wwi_token')||null}catch(e){}
    if(!token||window.__WWI_PREVIEW__)return;
    var CTX=window.__WWI_EDIT_CTX__||{};
    var S={open:false,tab:'content',sel:null,sec:null,timer:null,undo:[],saving:false};
    function api(url,opts){
        opts=opts||{};
        opts.headers=opts.headers||{};
        opts.headers['Authorization']='Bearer '+token;
        if(opts.body){opts.headers['Content-Type']='application/json';opts.body=JSON.stringify(opts.body)}
        return fetch(url,opts).then(function(r){return r.json()});
    }
    function el(id){return document.getElementById(id)}
    function q(sel){return document.querySelector(sel)}
    function esc(s){return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}
    function toast(msg,err){
        var t=document.createElement('div');
        t.className='wwi-ed-toast'+(err?' err':'');
        t.textContent=msg;
        document.body.appendChild(t);
        setTimeout(function(){t.remove()},2600);
    }
    function setStatus(t){var s=el('wwi-ed-status');if(s)s.textContent=t||''}
    api('/api/v1/admin/auth/me').then(function(d){
        if(!d||!d.ok||!d.user)return;
        build(d.user);
    }).catch(function(){});
    function build(user){
        var bar=document.createElement('div');
        bar.className='wwi-ed-bar';
        bar.innerHTML='<span class="u">✎ '+esc(user.username||'')+'</span><label class="wwi-ed-toggle"><input type="checkbox" id="wwi-ed-on"/> Editar sitio</label><label class="wwi-ed-toggle"><input type="checkbox" id="wwi-ed-client"/> Modo cliente</label>';
        document.body.appendChild(bar);
        el('wwi-ed-client').checked=localStorage.getItem('wwi_ed_client')==='1';
        document.body.classList.toggle('wwi-client',el('wwi-ed-client').checked);
        el('wwi-ed-client').addEventListener('change',function(){
            try{localStorage.setItem('wwi_ed_client',this.checked?'1':'0')}catch(e){}
            document.body.classList.toggle('wwi-client',this.checked);
            renderBody();
        });
        var side=document.createElement('aside');
        side.className='wwi-ed-side';
        side.id='wwi-ed-side';
        side.innerHTML='<div class="wwi-ed-resize" id="wwi-ed-resize"></div>'
            +'<div class="wwi-ed-head"><div class="wwi-ed-crumb"><b id="wwi-ed-crumb">'+esc(CTX.pageTitle||'Página')+'</b><span id="wwi-ed-status"></span></div><button class="wwi-ed-x" id="wwi-ed-close" title="Cerrar">✕</button></div>'
            +'<div class="wwi-ed-tabs"><button class="wwi-ed-tab on" data-tab="content">Contenido</button><button class="wwi-ed-tab" data-tab="add">Añadir</button><button class="wwi-ed-tab" data-tab="page">Página</button><button class="wwi-ed-tab" data-tab="quality">Calidad</button></div>'
            +'<div class="wwi-ed-body" id="wwi-ed-body"></div>';
        document.body.appendChild(side);
        document.querySelectorAll('.wwi-ed-tab').forEach(function(b){
            b.addEventListener('click',function(){S.tab=b.dataset.tab;renderTabs();renderBody()});
        });
        el('wwi-ed-close').onclick=function(){openSide(false);el('wwi-ed-on').checked=false;document.body.classList.remove('wwi-edit-on')};
        el('wwi-ed-on').addEventListener('change',function(){
            document.body.classList.toggle('wwi-edit-on',this.checked);
            openSide(this.checked);
            if(this.checked)decorate();
        });
        if(localStorage.getItem('wwi_ed_on')==='1'){
            document.body.classList.add('wwi-edit-on');
            el('wwi-ed-on').checked=true;
            openSide(true);
        }
        decorate();
        bindSelect();
        initResize();
        document.addEventListener('keydown',function(e){
            if(e.key==='Escape'){deselect()}
            else if((e.ctrlKey||e.metaKey)&&e.key.toLowerCase()==='z'&&S.sel){e.preventDefault();undo()}
        });
        renderBody();
    }
    function renderTabs(){
        document.querySelectorAll('.wwi-ed-tab').forEach(function(b){b.classList.toggle('on',b.dataset.tab===S.tab)});
    }
    function openSide(open){
        S.open=open;
        document.body.classList.toggle('wwi-ed-open',open);
        try{localStorage.setItem('wwi_ed_on',open?'1':'0')}catch(e){}
    }
    function initResize(){
        var h=el('wwi-ed-resize');if(!h)return;
        var w=parseInt(localStorage.getItem('wwi_ed_w')||'380',10);
        if(w>=300&&w<=720)el('wwi-ed-side').style.width=w+'px';
        h.addEventListener('mousedown',function(e){
            e.preventDefault();
            var startX=e.clientX,startW=el('wwi-ed-side').offsetWidth;
            function mv(ev){var nw=Math.min(720,Math.max(300,startW+(startX-ev.clientX)));el('wwi-ed-side').style.width=nw+'px'}
            function up(){
                document.removeEventListener('mousemove',mv);
                document.removeEventListener('mouseup',up);
                try{localStorage.setItem('wwi_ed_w',String(el('wwi-ed-side').offsetWidth))}catch(e){}
            }
            document.addEventListener('mousemove',mv);
            document.addEventListener('mouseup',up);
        });
    }
    function bindSelect(){
        document.addEventListener('click',function(e){
            if(!document.body.classList.contains('wwi-edit-on'))return;
            if(e.target.closest('.wwi-ed-side')||e.target.closest('.wwi-ed-bar')||e.target.closest('.wwi-ed-toast')||e.target.closest('.wwi-ed-tools'))return;
            if(e.target.closest('[contenteditable="true"]'))return;
            var edit=e.target.closest('[data-editable]');
            var sec=e.target.closest('.wwi-section[data-sid]');
            if(sec){
                e.preventDefault();e.stopPropagation();
                var sid=parseInt(sec.dataset.sid,10);
                if(edit)selectElement(sid,edit.getAttribute('data-editable'));
                else selectSection(sid);
                return;
            }
            deselect();
        },true);
        document.addEventListener('mouseover',function(e){
            if(!document.body.classList.contains('wwi-edit-on'))return;
            if(e.target.closest('.wwi-ed-side')||e.target.closest('.wwi-ed-bar')||e.target.closest('.wwi-ed-tools'))return;
            var edit=e.target.closest('[data-editable]');
            document.querySelectorAll('[data-editable].wwi-sel-el').forEach(function(x){if(x!==edit)x.classList.remove('wwi-sel-el')});
            if(edit)edit.classList.add('wwi-sel-el');
        });
        document.addEventListener('dblclick',function(e){
            if(!document.body.classList.contains('wwi-edit-on'))return;
            var edit=e.target.closest('[data-editable]');
            if(!edit)return;
            e.preventDefault();e.stopPropagation();
            var sec=edit.closest('.wwi-section[data-sid]');
            if(!sec)return;
            var sid=parseInt(sec.dataset.sid,10);
            edit.setAttribute('contenteditable','true');
            edit.style.outline='2px solid rgba(139,92,246,.9)';
            edit.style.outlineOffset='2px';
            try{edit.focus()}catch(err){}
            var done=false;
            var finish=function(commit){
                if(done)return;done=true;
                edit.removeAttribute('contenteditable');
                edit.style.outline='';edit.style.outlineOffset='';
                edit.removeEventListener('blur',onBlur);
                if(!commit)return;
                var val=edit.textContent.replace(/\s+/g,' ').trim();
                loadSection(sid,function(s){saveElement(s,edit.getAttribute('data-editable'),val)});
            };
            var onBlur=function(){finish(true)};
            edit.addEventListener('blur',onBlur);
            edit.addEventListener('keydown',function(ev){
                if(ev.key==='Enter'&&!ev.shiftKey){ev.preventDefault();finish(true)}
                else if(ev.key==='Escape'){ev.preventDefault();finish(false)}
            });
        });
    }
    function deselect(){
        S.sel=null;S.sec=null;
        document.querySelectorAll('.wwi-sel').forEach(function(x){x.classList.remove('wwi-sel')});
        document.querySelectorAll('.wwi-sel-el').forEach(function(x){x.classList.remove('wwi-sel-el')});
        if(S.tab==='content')renderBody();
    }
    function selectSection(sid){
        document.querySelectorAll('.wwi-sel').forEach(function(x){x.classList.remove('wwi-sel')});
        var sec=q('.wwi-section[data-sid="'+sid+'"]');
        if(sec)sec.classList.add('wwi-sel');
        S.sel={kind:'section',sid:sid};
        openSide(true);S.tab='content';renderTabs();
        setStatus('Cargando…');
        loadSection(sid,function(s){S.sec=s;setStatus('');renderBody()});
    }
    function selectElement(sid,key){
        document.querySelectorAll('.wwi-sel').forEach(function(x){x.classList.remove('wwi-sel')});
        var sec=q('.wwi-section[data-sid="'+sid+'"]');
        if(sec)sec.classList.add('wwi-sel');
        S.sel={kind:'element',sid:sid,key:key};
        openSide(true);S.tab='content';renderTabs();
        setStatus('Cargando…');
        loadSection(sid,function(s){S.sec=s;setStatus('');renderBody()});
    }
    function loadSection(sid,cb){
        api('/api/v1/admin/sections/'+sid).then(function(d){
            if(!d.ok||!d.data){toast('No se pudo cargar la sección',true);return}
            var s=d.data;
            var chain=Promise.resolve({data:{configSchema:[]}});
            if(s.widget_type)chain=api('/api/v1/admin/bricks/'+encodeURIComponent(s.widget_type)).catch(function(){return{data:{}}});
            chain.then(function(bd){
                s._schema=(bd.data&&bd.data.configSchema)||[];
                cb(s);
            });
        });
    }
    function cfgOf(s){try{return JSON.parse(s.config||'{}')||{}}catch(e){return{}}}
    function renderBody(){
        var b=el('wwi-ed-body');if(!b)return;
        if(S.timer){clearTimeout(S.timer);S.timer=null}
        if(S.tab==='add'){renderAdd(b);return}
        if(S.tab==='page'){renderPage(b);return}
        if(S.tab==='quality'){renderQuality(b);return}
        renderContent(b);
    }
    function renderContent(b){
        if(!S.sel){b.innerHTML='<div class="wwi-ed-hint">Haz clic en una <strong>sección</strong> del sitio para editarla, o directamente en un <strong>texto / botón / precio</strong> para editarlo.<br><br>Arrastra el borde izquierdo para redimensionar. Los cambios se guardan automáticamente (Ctrl+Z deshace).</div>';return}
        var s=S.sec;if(!s){b.innerHTML='<div class="wwi-ed-hint">Cargando…</div>';return}
        if(S.sel.kind==='element'){renderElement(b,s);return}
        var cfg=cfgOf(s);
        var h='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">'+esc(s.widget_type||s.type||'sección')+'</div>';
        h+='<label>Título<input id="wwi-ed-title" value="'+esc(s.title||'')+'"/></label>';
        h+='<label>Subtítulo<textarea id="wwi-ed-subtitle">'+esc(s.subtitle||'')+'</textarea></label>';
        if(s.type==='html'||s.type==='custom'){
            h+='<label>Contenido (HTML)<textarea id="wwi-ed-content" style="min-height:150px;font-family:monospace;font-size:11px">'+esc(s.content||'')+'</textarea></label>';
        }
        (s._schema||[]).forEach(function(f){
            var v=(cfg[f.key]!==undefined&&cfg[f.key]!==null)?cfg[f.key]:(f.default!==undefined?f.default:'');
            var id='wwi-ed-f-'+f.key;
            if(f.type==='toggle')h+='<label class="wwi-ed-check"><input type="checkbox" id="'+id+'" '+(v?'checked':'')+'/> '+esc(f.label)+'</label>';
            else if(f.type==='select'){
                var o='';
                for(var k in (f.options||{}))o+='<option value="'+esc(k)+'"'+(String(v)===String(k)?' selected':'')+'>'+esc(f.options[k])+'</option>';
                h+='<label>'+esc(f.label)+'<select id="'+id+'">'+o+'</select></label>';
            }
            else if(f.type==='textarea'||f.type==='code')h+='<label>'+esc(f.label)+'<textarea id="'+id+'">'+esc(typeof v==='object'?JSON.stringify(v):v)+'</textarea></label>';
            else h+='<label>'+esc(f.label)+'<input id="'+id+'" value="'+esc(v)+'"/></label>';
        });
        h+='<label class="wwi-ed-check"><input type="checkbox" id="wwi-ed-active" '+(s.is_active==1||s.is_active==='1'?'checked':'')+'/> Visible en el sitio</label>';
        var client=document.body.classList.contains('wwi-client');
        h+='<div class="wwi-ed-actions"><button class="wwi-ed-save" id="wwi-ed-save">Guardar</button>'+(client?'':'<button class="wwi-ed-btn" id="wwi-ed-up" title="Subir">▲</button><button class="wwi-ed-btn" id="wwi-ed-down" title="Bajar">▼</button><button class="wwi-ed-btn" id="wwi-ed-dup">Duplicar</button><button class="wwi-ed-btn" id="wwi-ed-del" style="color:#f87171">Eliminar</button>')+'</div>';
        b.innerHTML=h;
        b.querySelectorAll('input,textarea,select').forEach(function(inp){
            inp.addEventListener('input',function(){scheduleAuto(function(){saveSection(s,collect(s),true)})});
        });
        el('wwi-ed-save').onclick=function(){saveSection(s,collect(s),false)};
        if(!client){
            el('wwi-ed-up').onclick=function(){moveSection(s.id,-1)};
            el('wwi-ed-down').onclick=function(){moveSection(s.id,1)};
            el('wwi-ed-dup').onclick=function(){duplicateSection(s.id)};
            el('wwi-ed-del').onclick=function(){deleteSection(s.id,s.title||s.widget_type||('#'+s.id))};
        }
    }
    function collect(s){
        var data={title:(el('wwi-ed-title')||{value:''}).value,subtitle:(el('wwi-ed-subtitle')||{value:''}).value,is_active:el('wwi-ed-active')&&el('wwi-ed-active').checked?1:0};
        if(s.type==='html'||s.type==='custom')data.content=(el('wwi-ed-content')||{value:''}).value;
        if((s._schema||[]).length){
            var cfg={};
            s._schema.forEach(function(f){
                var fEl=el('wwi-ed-f-'+f.key);if(!fEl)return;
                if(f.type==='toggle')cfg[f.key]=fEl.checked?1:0;
                else if(f.type==='code'){try{cfg[f.key]=JSON.parse(fEl.value||'null')}catch(e){cfg[f.key]=fEl.value}}
                else cfg[f.key]=fEl.value;
            });
            data.config=cfg;
        }
        return data;
    }
    function snapshot(s){
        var snap={title:s.title||'',subtitle:s.subtitle||'',is_active:(s.is_active==1||s.is_active==='1')?1:0};
        if(s.type==='html'||s.type==='custom')snap.content=s.content||'';
        if(s.widget_type)snap.config=cfgOf(s);
        return snap;
    }
    function saveSection(s,data,silent){
        if(S.saving)return;
        S.saving=true;setStatus('Guardando…');
        var prev=snapshot(s);
        api('/api/v1/admin/sections/'+s.id,{method:'PUT',body:data}).then(function(r){
            S.saving=false;
            if(!r.ok){setStatus('Error al guardar');toast(r.message||'Error al guardar',true);return}
            S.undo.push({sid:s.id,data:prev});
            if(S.undo.length>25)S.undo.shift();
            setStatus('Guardado ✓ '+new Date().toTimeString().slice(0,5));
            if(!silent)toast('Guardado ✓');
            s.title=data.title;s.subtitle=data.subtitle;s.is_active=data.is_active;
            if(data.config)s.config=JSON.stringify(data.config);
            if(data.content!==undefined)s.content=data.content;
            refresh(s.id);
        }).catch(function(){S.saving=false;setStatus('Error de conexión');toast('Error de conexión',true)});
    }
    function scheduleAuto(fn){
        if(S.timer)clearTimeout(S.timer);
        S.timer=setTimeout(function(){S.timer=null;fn()},1200);
    }
    function undo(){
        var last=S.undo.pop();
        if(!last){toast('Nada que deshacer',true);return}
        api('/api/v1/admin/sections/'+last.sid,{method:'PUT',body:last.data}).then(function(r){
            if(!r.ok){toast(r.message||'Error al deshacer',true);return}
            toast('Deshecho');
            refresh(last.sid);
            if(S.sel&&S.sel.sid===last.sid)loadSection(last.sid,function(s){S.sec=s;renderBody()});
        });
    }
    function refresh(sid){
        api('/api/v1/admin/sections/'+sid+'/render').then(function(r){
            if(!r.ok||!r.data)return;
            var sec=q('.wwi-section[data-sid="'+sid+'"]');
            if(!sec)return;
            var tb=sec.querySelector('.wwi-ed-tools');
            sec.innerHTML=r.data.html;
            if(tb)sec.insertBefore(tb,sec.firstChild);
        });
    }
    function renderElement(b,s){
        var key=S.sel.key;
        var cfg=cfgOf(s);
        var schema=(s._schema||[]).filter(function(f){return f.key===key})[0];
        var val=cfg[key]!==undefined?cfg[key]:(schema&&schema.default!==undefined?schema.default:'');
        var label=(schema&&schema.label)||key;
        var long=String(val).length>70||(schema&&(schema.type==='textarea'||schema.type==='code'));
        var h='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Elemento · '+esc(label)+'</div>';
        h+=long?'<label>'+esc(label)+'<textarea id="wwi-ed-el">'+esc(typeof val==='object'?JSON.stringify(val):val)+'</textarea></label>':'<label>'+esc(label)+'<input id="wwi-ed-el" value="'+esc(val)+'"/></label>';
        h+='<div class="wwi-ed-actions"><button class="wwi-ed-save" id="wwi-ed-el-save">Guardar</button><button class="wwi-ed-btn" id="wwi-ed-el-ai">✨ Mejorar con IA</button><button class="wwi-ed-btn" id="wwi-ed-el-sec">Editar sección completa</button></div>';
        b.innerHTML=h;
        var inp=el('wwi-ed-el');
        inp.addEventListener('input',function(){scheduleAuto(function(){saveElement(s,key,inp.value)})});
        el('wwi-ed-el-save').onclick=function(){saveElement(s,key,inp.value)};
        el('wwi-ed-el-ai').onclick=function(){aiImprove(inp)};
        el('wwi-ed-el-sec').onclick=function(){selectSection(s.id)};
        try{inp.focus()}catch(e){}
    }
    function saveElement(s,key,val){
        var cfg=cfgOf(s);
        cfg[key]=val;
        saveSection(s,{title:s.title||'',subtitle:s.subtitle||'',is_active:(s.is_active==1||s.is_active==='1')?1:0,config:cfg});
    }
    function renderAdd(b){
        var h='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Insertar en esta página</div><div class="wwi-ed-cat">';
        h+='<button id="wwi-add-brick"><i>🧱</i>Sección / Brick<small>Desde el Marketplace</small></button>';
        h+='<button id="wwi-add-text"><i>¶</i>Texto<small>Párrafo editable</small></button>';
        h+='<button id="wwi-add-title"><i>H</i>Título<small>Encabezado grande</small></button>';
        h+='<button id="wwi-add-btn"><i>▢</i>Botón<small>CTA con enlace</small></button>';
        h+='<button id="wwi-add-img"><i>▣</i>Imagen<small>Desde la biblioteca</small></button>';
        h+='<button id="wwi-add-2col"><i>▥</i>Fila 2 columnas<small>Grid responsive</small></button>';
        h+='<button id="wwi-add-3col"><i>▦</i>Fila 3 columnas<small>Grid responsive</small></button>';
        h+='<button id="wwi-add-code"><i>{ }</i>Código<small>HTML embebido</small></button>';
        h+='<button id="wwi-add-sep"><i>—</i>Separador<small>Línea divisoria</small></button>';
        h+='</div><div id="wwi-add-slot" style="margin-top:14px"></div>';
        b.innerHTML=h;
        el('wwi-add-brick').onclick=brickPicker;
        el('wwi-add-text').onclick=function(){addHtml('<section style="padding:56px 0"><div class="wrap"><p style="font-size:15px;color:var(--muted);line-height:1.8">Escribe aquí tu texto…</p></div></section>','Texto')};
        el('wwi-add-title').onclick=function(){addHtml('<section style="padding:56px 0"><div class="wrap"><h2 style="font-size:28px;font-weight:800;letter-spacing:-.02em">Tu título aquí</h2></div></section>','Título')};
        el('wwi-add-btn').onclick=function(){addHtml('<section style="padding:36px 0"><div class="wrap" style="text-align:center"><a class="btn btn-primary" href="#">Mi botón</a></div></section>','Botón')};
        el('wwi-add-img').onclick=mediaPicker;
        el('wwi-add-2col').onclick=function(){addHtml('<section style="padding:56px 0"><div class="wrap" style="display:grid;grid-template-columns:1fr 1fr;gap:18px"><div class="card">Columna 1</div><div class="card">Columna 2</div></div></section>','Fila 2 columnas')};
        el('wwi-add-3col').onclick=function(){addHtml('<section style="padding:56px 0"><div class="wrap" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px"><div class="card">Columna 1</div><div class="card">Columna 2</div><div class="card">Columna 3</div></div></section>','Fila 3 columnas')};
        el('wwi-add-code').onclick=function(){addHtml('<section style="padding:40px 0"><div class="wrap"><pre style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px;overflow:auto;font-size:12px"><code>&lt;!-- tu código aquí --&gt;</code></pre></div></section>','Código')};
        el('wwi-add-sep').onclick=function(){addHtml('<section style="padding:18px 0"><div class="wrap"><hr style="border:none;border-top:1px solid var(--border)"/></div></section>','Separador')};
        b.querySelectorAll('.wwi-ed-cat button').forEach(function(btn){
            if(btn.id==='wwi-add-img'||btn.id==='wwi-add-brick')return;
            btn.setAttribute('draggable','true');
            btn.addEventListener('dragstart',function(e){
                e.dataTransfer.setData('text/plain',JSON.stringify({kind:'tpl',key:btn.id.replace('wwi-add-','')}));
                e.dataTransfer.effectAllowed='copy';
                buildDropzones();
            });
            btn.addEventListener('dragend',cleanupDropzones);
        });
    }
    function addHtml(html,title,cb){
        if(!CTX.pageId){toast('Página no disponible',true);return}
        setStatus('Insertando…');
        api('/api/v1/admin/pages/'+CTX.pageId+'/sections',{method:'POST',body:{type:'html',title:title,content:html,config:{}}}).then(function(r){
            if(!r.ok){setStatus('');toast(r.message||'Error al insertar',true);return}
            appendSection(r.data.id,cb);
            toast('"'+title+'" insertado');
            setStatus('');
        }).catch(function(){setStatus('');toast('Error de conexión',true)});
    }
    function appendSection(id,cb){
        api('/api/v1/admin/sections/'+id+'/render').then(function(r){
            if(!r.ok)return;
            var main=q('main');
            var div=document.createElement('div');
            div.className='wwi-section';
            div.dataset.sid=id;
            div.innerHTML=r.data.html;
            main.appendChild(div);
            decorate();
            if(cb){cb(id)}
            else{
                selectSection(id);
                try{div.scrollIntoView({behavior:'smooth',block:'center'})}catch(e){}
            }
        });
    }
    function brickPicker(){
        var slot=el('wwi-add-slot');if(!slot)return;
        slot.innerHTML='<div class="wwi-ed-hint">Cargando bricks…</div>';
        api('/api/v1/admin/bricks').then(function(d){
            var items=d.data||{};
            var h='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Bricks disponibles</div><div style="display:flex;flex-direction:column;gap:6px">';
            var n=0;
            for(var id in items){
                var b=items[id];
                if(b.functional===false)continue;
                n++;
                h+='<button class="wwi-ed-btn" data-brick="'+esc(id)+'" data-name="'+esc(b.name)+'" style="text-align:left">'+esc(b.name)+' <small style="color:var(--muted);margin-left:6px">'+esc(b.category||'')+'</small></button>';
            }
            h+='</div>';
            slot.innerHTML=n?h:'<div class="wwi-ed-hint">Sin bricks disponibles</div>';
            slot.querySelectorAll('[data-brick]').forEach(function(btn){
                btn.setAttribute('draggable','true');
                btn.addEventListener('dragstart',function(e){
                    e.dataTransfer.setData('text/plain',JSON.stringify({kind:'brick',id:btn.dataset.brick,name:btn.dataset.name}));
                    e.dataTransfer.effectAllowed='copy';
                    buildDropzones();
                });
                btn.addEventListener('dragend',cleanupDropzones);
                btn.onclick=function(){
                    api('/api/v1/admin/pages/'+CTX.pageId+'/sections',{method:'POST',body:{type:'widget',widget_type:btn.dataset.brick,title:btn.dataset.name,config:{}}}).then(function(r){
                        if(r.ok){appendSection(r.data.id);toast('"'+btn.dataset.name+'" añadido')}
                        else toast(r.message||'Error',true);
                    });
                };
            });
        });
    }
    function mediaPicker(){
        var slot=el('wwi-add-slot');if(!slot)return;
        slot.innerHTML='<div class="wwi-ed-hint">Cargando imágenes…</div>';
        api('/api/v1/admin/media').then(function(d){
            var items=d.data||[];
            if(!items.length){slot.innerHTML='<div class="wwi-ed-hint">No hay imágenes aún. Súbelas desde el admin → Media.</div>';return}
            var h='<div class="wwi-ed-media">';
            items.forEach(function(m){h+='<button data-url="'+esc(m.url)+'" data-alt="'+esc(m.alt_text||m.filename||'')+'"><img src="'+esc(m.url)+'" alt=""/></button>'});
            h+='</div>';
            slot.innerHTML=h;
            slot.querySelectorAll('[data-url]').forEach(function(btn){
                btn.onclick=function(){
                    addHtml('<section style="padding:40px 0"><div class="wrap"><img src="'+btn.dataset.url+'" alt="'+btn.dataset.alt+'" style="width:100%;border-radius:12px"/></div></section>','Imagen');
                };
            });
        });
    }
    function renderPage(b){
        b.innerHTML='<div class="wwi-ed-hint">Cargando…</div>';
        Promise.all([api('/api/v1/admin/pages'),api('/api/v1/admin/pages/'+CTX.pageId+'/sections')]).then(function(res){
            var pages=res[0].data||[],secs=res[1].data||[];
            var h='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Página actual</div>';
            h+='<div style="font-size:13px;font-weight:700">'+esc(CTX.pageTitle||'')+'</div><div style="font-size:11px;color:var(--muted);margin-bottom:14px">/'+esc(CTX.pageSlug||'')+'</div>';
            h+='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Secciones ('+secs.length+')</div>';
            h+='<div id="wwi-ed-tree">';
            secs.forEach(function(s){
                h+='<div class="wwi-ed-tree-item'+(S.sel&&S.sel.sid===s.id?' on':'')+'" data-sid="'+s.id+'"><span class="nm">'+esc(s.title||s.widget_type||('#'+s.id))+'</span><button class="wwi-ed-btn" data-act="up" style="padding:2px 7px">▲</button><button class="wwi-ed-btn" data-act="down" style="padding:2px 7px">▼</button></div>';
            });
            h+='</div>';
            h+='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin:16px 0 8px">Ir a otra página</div>';
            h+='<div style="display:flex;flex-direction:column;gap:6px">';
            pages.forEach(function(p){
                var url=(p.slug==='home')?'/':'/'+p.slug;
                h+='<a class="wwi-ed-btn" href="'+esc(url)+'" style="text-decoration:none">'+esc(p.title)+' <small style="color:var(--muted);margin-left:6px">/'+esc(p.slug)+'</small></a>';
            });
            h+='</div>';
            b.innerHTML=h;
            b.querySelectorAll('.wwi-ed-tree-item').forEach(function(row){
                row.addEventListener('click',function(e){
                    var act=(e.target&&e.target.dataset)?e.target.dataset.act:null;
                    var sid=parseInt(row.dataset.sid,10);
                    if(act==='up'||act==='down'){e.stopPropagation();moveSection(sid,act==='up'?-1:1);return}
                    selectSection(sid);
                });
            });
        });
    }
    function moveSection(sid,dir){
        var sec=q('.wwi-section[data-sid="'+sid+'"]');
        if(!sec)return;
        var sib=dir<0?sec.previousElementSibling:sec.nextElementSibling;
        if(!sib||!sib.classList.contains('wwi-section'))return;
        if(dir<0)sec.parentNode.insertBefore(sec,sib);
        else sec.parentNode.insertBefore(sib,sec);
        saveOrder();
    }
    function saveOrder(){
        var items=[];
        document.querySelectorAll('.wwi-section[data-sid]').forEach(function(s,i){items.push({id:parseInt(s.dataset.sid,10),sort_order:i})});
        api('/api/v1/admin/sections/reorder',{method:'PUT',body:{items:items}}).then(function(r){
            toast(r.ok?'Orden guardado':'No se pudo guardar el orden',!r.ok);
        });
    }
    function duplicateSection(sid){
        api('/api/v1/admin/sections/'+sid).then(function(d){
            if(!d.ok||!d.data)return;
            var s=d.data;
            var cfg={};try{cfg=JSON.parse(s.config||'{}')||{}}catch(e){}
            api('/api/v1/admin/pages/'+s.page_id+'/sections',{method:'POST',body:{type:s.type,widget_type:s.widget_type,title:(s.title||'')+' (copia)',subtitle:s.subtitle,content:s.content,config:cfg}}).then(function(r){
                if(!r.ok){toast(r.message||'Error',true);return}
                var orig=q('.wwi-section[data-sid="'+sid+'"]');
                api('/api/v1/admin/sections/'+r.data.id+'/render').then(function(rr){
                    if(!rr.ok)return;
                    var main=q('main');
                    var div=document.createElement('div');
                    div.className='wwi-section';
                    div.dataset.sid=r.data.id;
                    div.innerHTML=rr.data.html;
                    if(orig&&orig.nextElementSibling)main.insertBefore(div,orig.nextElementSibling);
                    else main.appendChild(div);
                    decorate();
                    selectSection(r.data.id);
                    saveOrder();
                    toast('Sección duplicada');
                });
            });
        });
    }
    function deleteSection(sid,name){
        if(!window.confirm('¿Eliminar la sección "'+name+'"? Esta acción no se puede deshacer.'))return;
        api('/api/v1/admin/sections/'+sid,{method:'DELETE'}).then(function(r){
            if(!r.ok){toast(r.message||'Error',true);return}
            var sec=q('.wwi-section[data-sid="'+sid+'"]');
            if(sec)sec.remove();
            deselect();
            toast('Sección eliminada');
        });
    }
    function toggleVisible(sid){
        api('/api/v1/admin/sections/'+sid).then(function(d){
            if(!d.ok||!d.data)return;
            var s=d.data;
            var active=(s.is_active==1||s.is_active==='1')?0:1;
            var cfg={};try{cfg=JSON.parse(s.config||'{}')||{}}catch(e){}
            api('/api/v1/admin/sections/'+sid,{method:'PUT',body:{title:s.title,subtitle:s.subtitle,is_active:active,config:cfg}}).then(function(r){
                if(r.ok){
                    var sec=q('.wwi-section[data-sid="'+sid+'"]');
                    if(sec)sec.style.opacity=active?'':'0.4';
                    toast(active?'Sección visible':'Sección oculta');
                }else toast(r.message||'Error',true);
            });
        });
    }
    var TEMPLATES={
        text:{title:'Texto',html:'<section style="padding:56px 0"><div class="wrap"><p style="font-size:15px;color:var(--muted);line-height:1.8">Escribe aquí tu texto…</p></div></section>'},
        title:{title:'Título',html:'<section style="padding:56px 0"><div class="wrap"><h2 style="font-size:28px;font-weight:800;letter-spacing:-.02em">Tu título aquí</h2></div></section>'},
        btn:{title:'Botón',html:'<section style="padding:36px 0"><div class="wrap" style="text-align:center"><a class="btn btn-primary" href="#">Mi botón</a></div></section>'},
        '2col':{title:'Fila 2 columnas',html:'<section style="padding:56px 0"><div class="wrap" style="display:grid;grid-template-columns:1fr 1fr;gap:18px"><div class="card">Columna 1</div><div class="card">Columna 2</div></div></section>'},
        '3col':{title:'Fila 3 columnas',html:'<section style="padding:56px 0"><div class="wrap" style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:18px"><div class="card">Columna 1</div><div class="card">Columna 2</div><div class="card">Columna 3</div></div></section>'},
        code:{title:'Código',html:'<section style="padding:40px 0"><div class="wrap"><pre style="background:var(--bg2);border:1px solid var(--border);border-radius:10px;padding:16px;overflow:auto;font-size:12px"><code>&lt;!-- tu código aquí --&gt;</code></pre></div></section>'},
        sep:{title:'Separador',html:'<section style="padding:18px 0"><div class="wrap"><hr style="border:none;border-top:1px solid var(--border)"/></div></section>'}
    };
    function buildDropzones(){
        cleanupDropzones();
        var main=q('main');if(!main)return;
        document.querySelectorAll('main .wwi-section[data-sid]').forEach(function(sec){
            main.insertBefore(mkZone(sec),sec);
        });
        main.appendChild(mkZone(null));
    }
    function mkZone(before){
        var z=document.createElement('div');
        z.className='wwi-dropzone';
        z.addEventListener('dragover',function(e){e.preventDefault();z.classList.add('over')});
        z.addEventListener('dragleave',function(){z.classList.remove('over')});
        z.addEventListener('drop',function(e){
            e.preventDefault();
            var data={};
            try{data=JSON.parse(e.dataTransfer.getData('text/plain'))||{}}catch(err){}
            dropInsert(before,data);
        });
        return z;
    }
    function cleanupDropzones(){document.querySelectorAll('.wwi-dropzone').forEach(function(z){z.remove()})}
    function dropInsert(before,data){
        cleanupDropzones();
        if(!data||!data.kind)return;
        if(data.kind==='tpl'){
            var t=TEMPLATES[data.key];
            if(!t){toast('Elemento no válido',true);return}
            addHtml(t.html,t.title,function(id){moveBefore(id,before);toast('"'+t.title+'" insertado')});
        }else if(data.kind==='brick'){
            api('/api/v1/admin/pages/'+CTX.pageId+'/sections',{method:'POST',body:{type:'widget',widget_type:data.id,title:data.name,config:{}}}).then(function(r){
                if(!r.ok){toast(r.message||'Error',true);return}
                appendSection(r.data.id,function(){moveBefore(r.data.id,before);toast('"'+data.name+'" añadido')});
            });
        }
    }
    function moveBefore(sid,before){
        var div=q('.wwi-section[data-sid="'+sid+'"]');
        if(!div)return;
        var main=q('main');
        if(before&&before.parentNode===main)main.insertBefore(div,before);
        else main.appendChild(div);
        saveOrder();
    }
    function aiImprove(inp){
        var txt=(inp.value||'').trim();
        if(!txt){toast('Escribe algo primero',true);return}
        setStatus('IA pensando…');
        api('/api/v1/admin/brick/request',{method:'POST',body:{
            system_prompt:'Eres copywriter senior de marketing digital. Mejora el texto manteniendo idioma, significado y tono profesional, claro y persuasivo. Devuelve SOLO el texto mejorado, sin comillas ni explicaciones.',
            messages:[{role:'user',content:txt}],
            system_id:'wontia',module:'live_editor',function:'improve_copy',temperature:0.6,max_tokens:500
        }}).then(function(r){
            setStatus('');
            var out=(r&&r.data&&r.data.content)?String(r.data.content).trim():'';
            if(!out){toast((r&&r.data&&r.data.error)?r.data.error:'IA sin respuesta',true);return}
            inp.value=out;
            toast('Texto mejorado ✨ (revisa y guarda)');
        }).catch(function(){setStatus('');toast('Error de IA',true)});
    }
    function renderQuality(b){
        var issues=[];
        var sections=document.querySelectorAll('main .wwi-section[data-sid]');
        var imgs=document.querySelectorAll('main img');
        var noAlt=0;
        imgs.forEach(function(im){if(!im.getAttribute('alt'))noAlt++});
        if(noAlt)issues.push({sev:'warn',txt:noAlt+' imagen(es) sin atributo alt',target:null});
        var h1=document.querySelectorAll('main h1');
        if(h1.length===0)issues.push({sev:'warn',txt:'La página no tiene H1',target:null});
        if(h1.length>1)issues.push({sev:'warn',txt:h1.length+' elementos H1 (debería haber 1)',target:h1[1]});
        var emptyLinks=0;
        document.querySelectorAll('main a').forEach(function(a){if(!a.textContent.trim()&&!a.querySelector('img'))emptyLinks++});
        if(emptyLinks)issues.push({sev:'warn',txt:emptyLinks+' enlace(s) sin texto',target:null});
        var noLabel=0;
        document.querySelectorAll('main input,main textarea,main select').forEach(function(inp){
            if(inp.type==='hidden')return;
            var has=inp.getAttribute('aria-label')||inp.closest('label');
            var id=inp.getAttribute('id');
            if(!has&&id)has=document.querySelector('label[for="'+id+'"]');
            if(!has)noLabel++;
        });
        if(noLabel)issues.push({sev:'warn',txt:noLabel+' campo(s) sin etiqueta accesible',target:null});
        if(!(document.title||'').trim())issues.push({sev:'bad',txt:'La página no tiene <title>',target:null});
        var meta=q('meta[name="description"]');
        if(!meta||!(meta.getAttribute('content')||'').trim())issues.push({sev:'warn',txt:'Falta meta description',target:null});
        var words=(q('main')?q('main').textContent:'').trim().split(/\s+/).length;
        if(words<120)issues.push({sev:'info',txt:'Poco contenido: '+words+' palabras en la página',target:null});
        var score=100;
        issues.forEach(function(i){score-=i.sev==='bad'?15:(i.sev==='warn'?8:3)});
        if(score<0)score=0;
        var col=score>=85?'#34d399':(score>=60?'#fbbf24':'#f87171');
        var h='<div style="display:flex;align-items:center;gap:12px;margin-bottom:14px"><div style="font-size:30px;font-weight:800;color:'+col+'">'+score+'</div><div><div style="font-size:12px;font-weight:700">Calidad de la página</div><div style="font-size:10px;color:var(--muted)">'+sections.length+' secciones · '+imgs.length+' imágenes · '+words+' palabras</div></div></div>';
        h+='<div style="font-size:10px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:8px">Hallazgos ('+issues.length+')</div>';
        if(!issues.length)h+='<div class="wwi-ed-hint">¡Todo en orden! No se detectaron problemas.</div>';
        issues.forEach(function(i,idx){
            var ic=i.sev==='bad'?'⛔':(i.sev==='warn'?'⚠':'ℹ');
            h+='<div class="wwi-ed-tree-item" data-qi="'+idx+'"><span class="nm">'+ic+' '+esc(i.txt)+'</span></div>';
        });
        h+='<div style="font-size:10px;color:var(--muted);margin-top:12px;line-height:1.6">Chequeos en vivo: alt de imágenes, H1 único, enlaces con texto, etiquetas de formularios, title y meta description, volumen de contenido.</div>';
        b.innerHTML=h;
        b.querySelectorAll('[data-qi]').forEach(function(row){
            row.addEventListener('click',function(){
                var i=issues[parseInt(row.dataset.qi,10)];
                if(i&&i.target){
                    var sec=i.target.closest('.wwi-section[data-sid]');
                    if(sec){selectSection(parseInt(sec.dataset.sid,10));try{sec.scrollIntoView({behavior:'smooth',block:'center'})}catch(e){}}
                }
            });
        });
    }
    function decorate(){
        document.querySelectorAll('.wwi-section[data-sid]').forEach(function(sec){
            if(sec.dataset.edReady)return;
            sec.dataset.edReady='1';
            var tb=document.createElement('div');
            tb.className='wwi-ed-tools';
            tb.innerHTML='<button data-act="edit" title="Editar sección">✎</button><button data-act="up" title="Subir">▲</button><button data-act="down" title="Bajar">▼</button><button data-act="toggle" title="Ocultar / mostrar">👁</button>';
            sec.insertBefore(tb,sec.firstChild);
            tb.addEventListener('click',function(e){
                var b=e.target.closest('button');
                if(!b)return;
                e.preventDefault();e.stopPropagation();
                var sid=parseInt(sec.dataset.sid,10);
                if(b.dataset.act==='edit')selectSection(sid);
                else if(b.dataset.act==='up')moveSection(sid,-1);
                else if(b.dataset.act==='down')moveSection(sid,1);
                else if(b.dataset.act==='toggle')toggleVisible(sid);
            });
        });
    }
})();
</script>
</body>
</html>
