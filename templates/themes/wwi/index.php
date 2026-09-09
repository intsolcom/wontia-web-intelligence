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
}
:root[data-theme='light']{
    --bg:#f4f6fb;--bg2:#eef1f8;--panel:#ffffff;--panel2:#f7f9fd;
    --border:rgba(15,23,42,.1);--border2:rgba(15,23,42,.22);
    --text:#0f172a;--muted:#5b6b84;
    --accent:#0891b2;--accent2:#7c3aed;
    --ok:#059669;--warn:#d97706;--bad:#dc2626;
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Inter',Segoe UI,system-ui;font-size:14px;line-height:1.45;background:var(--bg);color:var(--text);-webkit-font-smoothing:antialiased}
.mono,.num,.metric{font-family:'JetBrains Mono',Consolas,monospace;font-variant-numeric:tabular-nums}
a{color:inherit;text-decoration:none}
.grid-bg{position:fixed;inset:0;z-index:0;pointer-events:none;background-image:linear-gradient(var(--border) 1px,transparent 1px),linear-gradient(90deg,var(--border) 1px,transparent 1px);background-size:42px 42px;-webkit-mask-image:radial-gradient(ellipse 90% 60% at 50% 0%,#000 30%,transparent 75%);mask-image:radial-gradient(ellipse 90% 60% at 50% 0%,#000 30%,transparent 75%);opacity:.35}
main{position:relative;z-index:1}
.w-nav{position:fixed;top:0;left:0;right:0;z-index:100;height:60px;display:flex;align-items:center;justify-content:space-between;padding:0 28px;background:rgba(6,8,15,.82);backdrop-filter:blur(14px);border-bottom:1px solid var(--border)}
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
<div class="grid-bg"></div>
<nav class="w-nav">
  <div class="w-nav-brand"><div class="w-nav-logo">W</div><span>WWI</span></div>
  <div class="w-nav-links">
    <a href="#planes">Planes</a>
    <a href="#beneficios">Beneficios</a>
    <a href="#plantillas">Plantillas</a>
    <a href="#faq">FAQ</a>
  </div>
  <a href="#planes" class="btn btn-primary">Crear mi sitio</a>
</nav>
<main>
<?php foreach ($sections as $section):
    if (!empty($section['widget_type']) && WidgetRegistry::get($section['widget_type'])):
        $config = json_decode($section['config'] ?? '{}', true) ?: [];
        echo WidgetRegistry::render($section['widget_type'], $config);
    elseif ($section['type'] === 'custom' || $section['type'] === 'html'):
        echo '<section>' . ($section['content'] ?? '') . '</section>';
    else:
        if ($section['content']) echo '<section>' . $section['content'] . '</section>';
    endif;
endforeach; ?>
</main>
<?= CookieConsentService::render() ?>
<script>
(function(){
    var r=document.querySelectorAll('.reveal');
    var o=new IntersectionObserver(function(e){e.forEach(function(el){if(el.isIntersecting)el.classList.add('visible')})},{threshold:0.08});
    r.forEach(function(el){o.observe(el)});
    document.querySelectorAll('.faq-q').forEach(function(q){
        q.addEventListener('click',function(){q.parentElement.classList.toggle('open')});
    });
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
wwiLoadPlans();
wwiLoadTemplates();
wwiLoadPayMode();
var coBtn=document.getElementById('wwi-co-submit');
if(coBtn)coBtn.addEventListener('click',wwiCheckoutSubmit);
</script>
</body>
</html>
