<?php
use App\Services\AnalyticsService;

require_once __DIR__ . '/lib.php';

AnalyticsService::track($_SERVER['REQUEST_URI'], $_SERVER['HTTP_REFERER'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

if (!function_exists('sonika_grid')) {
    function sonika_grid(array $products): void {
        if (empty($products)) { echo '<div class="empty">No encontramos productos en esta sección.</div>'; return; }
        echo '<div class="prod-grid">';
        foreach ($products as $p) sonika_product_card($p);
        echo '</div>';
    }
}
if (!function_exists('sonika_slice')) {
    function sonika_slice(array $items, int $perPage = 24): array {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $total = count($items);
        $offset = ($page - 1) * $perPage;
        return ['items' => array_slice($items, $offset, $perPage), 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }
}
if (!function_exists('sonika_pager')) {
    function sonika_pager(int $total, int $perPage, int $current, string $base): void {
        $pages = (int)ceil($total / max(1, $perPage));
        if ($pages <= 1) return;
        $sep = str_contains($base, '?') ? '&' : '?';
        echo '<nav class="pager">';
        if ($current > 1) echo '<a class="pg" href="' . sonika_esc($base . $sep . 'page=' . ($current - 1)) . '">‹ Anterior</a>';
        for ($i = 1; $i <= $pages; $i++) {
            if ($pages > 14 && $i > 3 && $i < $pages - 2 && abs($i - $current) > 1) {
                if ($i === 4 || $i === $pages - 3) echo '<span class="pgap">…</span>';
                continue;
            }
            echo '<a class="pg' . ($i === $current ? ' on' : '') . '" href="' . sonika_esc($base . $sep . 'page=' . $i) . '">' . $i . '</a>';
        }
        if ($current < $pages) echo '<a class="pg" href="' . sonika_esc($base . $sep . 'page=' . ($current + 1)) . '">Siguiente ›</a>';
        echo '</nav>';
    }
}

$path = trim((string)$uri, '/');
$segments = $path === '' ? [] : explode('/', $path);

if (($segments[0] ?? '') === 'r' && !empty($segments[1])) {
    $__aff = (new \App\Services\AffiliateService())->recordClick((string)$segments[1], (string)($_COOKIE['wwi_seo_sid'] ?? ''));
    if ($__aff) setcookie('sonika_aff', (string)$__aff['code'], time() + 30 * 86400, '/', '', false, false);
    header('Location: /', true, 302);
    exit;
}

// SONIKA Artist Hub — landing pública del programa (/artistas)
if (($segments[0] ?? '') === 'artistas' && count($segments) === 1) {
    sonika_site_head('Programa de Artistas — SONIKA', 'Convierte tu música en ingresos con SONIKA Artist Partners y SONIKA Sound League.');
    echo '<section style="background:#fff;border-bottom:1px solid #eef2f0"><div style="max-width:1100px;margin:0 auto;padding:18px 22px;display:flex;gap:12px;flex-wrap:wrap;justify-content:center"><a href="/artista/demo-artist" style="background:#101d3a;color:#fff;border-radius:12px;padding:12px 18px;font-weight:800;text-decoration:none">Ver página de ejemplo →</a><a href="/artista/demo-artist/panel" style="background:#F20D16;color:#fff;border-radius:12px;padding:12px 18px;font-weight:800;text-decoration:none">Ver panel del artista →</a></div></section>';
    echo \App\Widgets\WidgetRegistry::render('sonika-affiliates', ['enabled' => true]);
    sonika_site_footer();
    return true;
}

// SONIKA Artist Hub — consulta de estado de la solicitud (/artista/solicitud)
if (($segments[0] ?? '') === 'artista' && ($segments[1] ?? '') === 'solicitud') {
    sonika_site_head('Estado de tu solicitud — SONIKA Artist', 'Consulta el estado de tu solicitud al programa SONIKA Artist Partners.');
    echo '<style>.req{max-width:620px;margin:0 auto;padding:40px 22px 70px;font-family:Sora,system-ui,sans-serif}.req h1{font-size:26px;color:#101d3a;margin:0 0 6px}.req p{color:#51607a;font-size:14px;margin:0 0 22px}.req form{background:#fff;border:1px solid #e9efec;border-radius:16px;padding:20px;display:grid;gap:12px;box-shadow:0 10px 28px rgba(16,24,40,.06)}.req input{padding:11px 12px;border:1px solid #dbe7e1;border-radius:11px;font:inherit;font-size:14px}.req button{padding:12px;border:0;border-radius:11px;background:#F20D16;color:#fff;font-weight:800;font-size:14px;cursor:pointer}.req .out{margin-top:16px;font-size:14px}.req .card{background:#f6fbf9;border:1px solid #e9efec;border-radius:14px;padding:16px}.req .pill{display:inline-block;border-radius:999px;padding:3px 11px;font-size:12px;font-weight:800}.req .APPROVED{background:#e5f5ef;color:#0d7a58}.req .REVIEW_REQUIRED,.req .DRAFT,.req .EMAIL_PENDING,.req .PHONE_PENDING,.req .DOCUMENTS_PENDING{background:#fff3cd;color:#8a6d00}.req .REJECTED,.req .SUSPENDED,.req .TERMINATED{background:#fdecec;color:#b42318}.req .steps{margin-top:12px;font-size:12.5px;color:#64748b;line-height:1.7}</style>';
    echo '<section class="req"><h1>Estado de tu solicitud</h1><p>Ingresa tu correo y los <b>últimos 4 dígitos</b> de tu documento para consultar el estado.</p><form id="req-form"><input name="email" type="email" placeholder="Correo electrónico" required><input name="doc" inputmode="numeric" placeholder="Últimos 4 del documento" required><button type="submit">Consultar estado</button></form><div class="out" id="req-out"></div><h2 style="font-size:17px;margin:26px 0 8px;color:#101d3a">Subir documento (privado)</h2><p style="font-size:12.5px;color:#64748b;margin:0 0 12px">PDF/JPG/PNG · máx 8 MB. Solo lo ve el equipo de verificación.</p><form id="req-up-form" enctype="multipart/form-data"><input name="email" type="email" placeholder="Correo" required><input name="doc" inputmode="numeric" placeholder="Últimos 4 del documento" required><input name="file" type="file" accept=".pdf,.jpg,.jpeg,.png" required><button type="submit">Subir documento</button></form><div class="out" id="req-up-out"></div><script>(function(){var f=document.getElementById("req-up-form");if(!f)return;var o=document.getElementById("req-up-out");f.addEventListener("submit",function(e){e.preventDefault();var fd=new FormData(f);o.textContent="Subiendo…";fetch("/api/v1/public/affiliates/document",{method:"POST",body:fd}).then(function(r){return r.json();}).then(function(j){o.textContent=j.ok?"✅ Documento recibido. En revisión.":(j.message||"Error");f.reset();}).catch(function(){o.textContent="Error de conexión.";});});})();</script></section>';
    echo '<script>(function(){var f=document.getElementById("req-form"),o=document.getElementById("req-out");var L={DRAFT:"Borrador",EMAIL_PENDING:"Verificación de correo pendiente",PHONE_PENDING:"Verificación de teléfono pendiente",DOCUMENTS_PENDING:"Documentos pendientes",REVIEW_REQUIRED:"En revisión",APPROVED:"Aprobado",REJECTED:"Rechazado",SUSPENDED:"Suspendido",TERMINATED:"Terminado"};f.addEventListener("submit",function(e){e.preventDefault();var d={};new FormData(f).forEach(function(v,k){d[k]=v;});o.textContent="Consultando…";fetch("/api/v1/public/affiliates/status",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(d)}).then(function(r){return r.json();}).then(function(j){if(!j.ok){o.innerHTML="<div class=\'card\'>"+ (j.message||"No encontrado.") +"</div>";return;}o.innerHTML="<div class=\'card\'><b>"+j.name+"</b><br><span class=\'pill "+j.status+"\'>"+(L[j.status]||j.status)+"</span><div class=\'steps\'>Flujo: Borrador → Verificación (correo/teléfono) → Documentos → Revisión → Aprobado.<br>Recibida: "+(j.created_at||"").substring(0,10)+".</div><button type=\'button\' id=\'req-otp\' style=\'margin-top:10px;background:#101d3a;color:#fff;border:0;border-radius:10px;padding:9px 14px;font-weight:700;cursor:pointer\'>Verificar mi correo</button></div>";var ob=document.getElementById("req-otp");if(ob)ob.addEventListener("click",function(){ob.disabled=true;ob.textContent="Enviando…";fetch("/api/v1/public/affiliates/otp/send",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({email:d.email})}).then(function(r){return r.json();}).then(function(k){ob.disabled=false;ob.textContent="Verificar mi correo";if(!k.ok){alert(k.message||"No se pudo enviar");return;}var c=prompt("Ingresa el código de 6 dígitos enviado a tu correo:");if(!c)return;fetch("/api/v1/public/affiliates/otp/verify",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({email:d.email,code:c})}).then(function(r){return r.json();}).then(function(v){alert(v.ok?"✅ Correo verificado":"❌ "+(v.message||"Código incorrecto"));});});});}).catch(function(){o.innerHTML="<div class=\'card\'>Error de conexión.</div>";});});})();</script>';
    sonika_site_footer();
    return true;
}

// Recuperar contraseña (OTP)
if (($segments[0] ?? '') === 'recuperar' && count($segments) === 1) {
    sonika_site_head('Recuperar contraseña — SONIKA Artist', 'Recupera tu acceso con un código OTP.');
    echo '<style>.ffl{max-width:460px;margin:0 auto;padding:48px 22px 80px;font-family:Sora,system-ui}.ffl h1{font-size:24px;color:#101d3a;margin:0 0 4px}.ffl p{color:#51607a;font-size:14px;margin:0 0 20px}.ffl form{background:#fff;border:1px solid #e9efec;border-radius:16px;padding:20px;display:grid;gap:12px;box-shadow:0 10px 28px rgba(16,24,40,.06)}.ffl input{padding:11px 12px;border:1px solid #dbe7e1;border-radius:11px;font:inherit;font-size:14px}.ffl button{padding:12px;border:0;border-radius:11px;background:#0d9f72;color:#fff;font-weight:800;font-size:14px;cursor:pointer}.ffl .out{font-size:13px;min-height:16px;margin-top:8px;font-weight:600}</style>';
    echo '<section class="ffl"><h1>Recuperar contraseña</h1><p>Te enviaremos un código a tu correo para confirmar que eres tú.</p><form id="ff-rec"><input name="email" type="email" placeholder="Correo" required autocomplete="username"><button type="submit">Enviar código</button><div class="out" id="ff-rec-out"></div></form><form id="ff-rec2" style="display:none;margin-top:12px"><p style="margin:0 0 8px;font-size:13px;color:#51607a">Ingresa el código y tu nueva contraseña.</p><input name="code" inputmode="numeric" placeholder="Código OTP" required><input name="password" type="password" minlength="8" placeholder="Nueva contraseña (mín. 8)" required autocomplete="new-password"><button type="submit">Cambiar contraseña</button><div class="out" id="ff-rec2-out"></div></form><p style="margin-top:14px;font-size:12.5px"><a href="/mi-antro" style="color:#0d7a58;font-weight:700">← Volver a iniciar sesión</a></p></section>';
    echo '<script>(function(){var f=document.getElementById("ff-rec"),f2=document.getElementById("ff-rec2"),o=document.getElementById("ff-rec-out"),o2=document.getElementById("ff-rec2-out");var em="";f.addEventListener("submit",function(e){e.preventDefault();em=f.email.value.trim();o.style.color="#51607a";o.textContent="Enviando…";fetch("/api/v1/public/affiliates/recover",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({email:em})}).then(function(r){return r.json();}).then(function(j){if(j.ok){f.style.display="none";f2.style.display="grid";o.textContent="";}else{o.style.color="#d64545";o.textContent=j.message||"Error";}}).catch(function(){o.style.color="#d64545";o.textContent="Error de conexión.";});});f2.addEventListener("submit",function(e){e.preventDefault();var d={email:em,code:f2.code.value.trim(),password:f2.password.value};o2.style.color="#51607a";o2.textContent="Cambiando…";fetch("/api/v1/public/affiliates/recover/confirm",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(d)}).then(function(r){return r.json();}).then(function(j){if(j.ok){o2.style.color="#0d7a58";o2.textContent=j.message||"Contraseña actualizada.";setTimeout(function(){location.href="/mi-antro";},1200);}else{o2.style.color="#d64545";o2.textContent=j.message||"Error";}}).catch(function(){o2.style.color="#d64545";o2.textContent="Error de conexión.";});});})();</script>';
    sonika_site_footer();
    return true;
}

// Mi Antro — login del artista (acceso privado al panel)
if (($segments[0] ?? '') === 'mi-antro' && count($segments) === 1) {
    \App\Core\Session::start();
    if (!empty($_SESSION['ff_affiliate_id'])) {
        $__p0 = (new \App\Services\AffiliateService())->getById((int)$_SESSION['ff_affiliate_id']);
        if ($__p0) { header('Location: /artista/' . $__p0['slug'] . '/panel'); exit; }
    }
    sonika_site_head('Mi Antro — Iniciar sesión', 'Panel de control del artista — SONIKA Artist Partners.');
    echo '<style>.ffl{max-width:460px;margin:0 auto;padding:48px 22px 80px;font-family:Sora,system-ui}.ffl h1{font-size:24px;color:#101d3a;margin:0 0 4px}.ffl p{color:#51607a;font-size:14px;margin:0 0 20px}.ffl form{background:#fff;border:1px solid #e9efec;border-radius:16px;padding:20px;display:grid;gap:12px;box-shadow:0 10px 28px rgba(16,24,40,.06)}.ffl input{padding:11px 12px;border:1px solid #dbe7e1;border-radius:11px;font:inherit;font-size:14px}.ffl button{padding:12px;border:0;border-radius:11px;background:#0d9f72;color:#fff;font-weight:800;font-size:14px;cursor:pointer}.ffl .out{font-size:13px;min-height:16px;margin-top:8px;font-weight:600}</style>';
    echo '<section class="ffl"><h1>Mi Antro</h1><p>Panel de control · inicia sesión con tu correo y contraseña.</p><form id="ff-login"><input name="email" type="email" placeholder="Correo" required autocomplete="username"><input name="password" type="password" placeholder="Contraseña" required autocomplete="current-password"><button type="submit">Entrar</button><div class="out" id="ff-login-out"></div></form><form id="ff-otp" style="display:none;margin-top:12px"><p style="margin:0 0 8px;font-size:13px;color:#51607a">Ingresa el código de 6 dígitos enviado a tu correo.</p><input name="code" inputmode="numeric" placeholder="Código OTP" required><button type="submit">Verificar y entrar</button><div class="out" id="ff-otp-out"></div></form><p style="margin-top:14px;font-size:12.5px"><a href="/artistas" style="color:#0d7a58;font-weight:700">¿Aún no eres artista? Únete</a> · <a href="/recuperar" style="color:#64748b">¿Olvidaste tu contraseña?</a></p></section>';
    echo '<script>(function(){var f=document.getElementById("ff-login"),otp=document.getElementById("ff-otp"),o=document.getElementById("ff-login-out"),oe=document.getElementById("ff-otp-out");var em="";f.addEventListener("submit",function(e){e.preventDefault();var d={};new FormData(f).forEach(function(v,k){d[k]=v;});em=d.email;o.style.color="#51607a";o.textContent="Verificando…";fetch("/api/v1/public/affiliates/login",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(d)}).then(function(r){return r.json();}).then(function(j){if(j.ok){f.style.display="none";otp.style.display="grid";o.textContent="";}else{o.style.color="#d64545";o.textContent=j.message||"Error";}}).catch(function(){o.style.color="#d64545";o.textContent="Error de conexión.";});});otp.addEventListener("submit",function(e){e.preventDefault();var d={};new FormData(otp).forEach(function(v,k){d[k]=v;});d.email=em;oe.style.color="#51607a";oe.textContent="Verificando…";fetch("/api/v1/public/affiliates/login/verify",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(d)}).then(function(r){return r.json();}).then(function(j){if(j.ok){location.href="/artista/"+j.slug+"/panel";}else{oe.style.color="#d64545";oe.textContent=j.message||"Código incorrecto.";}}).catch(function(){oe.style.color="#d64545";oe.textContent="Error de conexión.";});});})();</script>';
    sonika_site_footer();
    return true;
}

// SONIKA Artist Hub — PANEL del artista (Mis Ventas / Mis Ganancias)
if (($segments[0] ?? '') === 'artista' && ($segments[2] ?? '') === 'panel' && !empty($segments[1])) {
    $__svcP = new \App\Services\AffiliateService();
    $__affPP = $__svcP->getBySlug((string)$segments[1]);
    if ($__affPP) {
        \App\Core\Session::start();
        if (empty($_SESSION['ff_affiliate_id'])) { header('Location: /mi-antro'); exit; }
        if ((int)$_SESSION['ff_affiliate_id'] !== (int)$__affPP['id']) { http_response_code(403); echo 'Acceso denegado.'; exit; }
        $__nameP = trim(($__affPP['artist_name'] ?: ($__affPP['public_name'] ?: $__affPP['full_name'])));
        $__d = $__svcP->dashboard((int)$__affPP['id']);
        $__states = ['ESTIMATED' => 'Comisión estimada', 'PENDING_VALIDATION' => 'Pendiente de validación', 'APPROVED' => 'Aprobada', 'PAYMENT_QUEUED' => 'Programada para pago', 'PAID' => 'Pagada', 'REJECTED' => 'Rechazada', 'REVERSED' => 'Reversada', 'ON_HOLD' => 'En espera'];
        $__codeP = $__svcP->ensureLink((int)$__affPP['id']);
        $__affUrlP = (((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'sonika.com.co')) . '/r/' . $__codeP;
        $__money = function ($c) { return '$ ' . number_format((float)$c, 0, ',', '.'); };
        sonika_site_head($__nameP . ' — Mis Ganancias', 'Panel del artista — SONIKA Artist Partners');
        echo '<style>.ahp{max-width:1120px;margin:0 auto;padding:28px 22px 56px;font-family:Sora,system-ui,sans-serif;color:#17212b}.ahp-h{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:22px}.ahp-h h1{font-size:26px;margin:0}.ahp-h .badge{background:#e5f5ef;color:#0d7a58;border-radius:999px;padding:5px 12px;font-size:12px;font-weight:800}.ahp-cards{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:26px}.ahp-c{background:#fff;border:1px solid #e9efec;border-radius:16px;padding:16px;box-shadow:0 8px 22px rgba(16,24,40,.05)}.ahp-c small{display:block;color:#64748b;font-size:12px;font-weight:600;margin-bottom:6px}.ahp-c b{font-size:22px;color:#101d3a}.ahp-sec h2{font-size:17px;margin:22px 0 10px}.ahp table{width:100%;border-collapse:collapse;font-size:13.5px;background:#fff;border:1px solid #eef2f0;border-radius:12px;overflow:hidden}.ahp th,.ahp td{padding:10px 12px;text-align:left;border-bottom:1px solid #f1f5f3}.ahp th{background:#f6fbf9;font-size:12px;color:#64748b;text-transform:uppercase;letter-spacing:.04em}.ahp td.n{text-align:right;font-weight:700}.ahp .st{display:inline-block;border-radius:999px;padding:2px 9px;font-size:11px;font-weight:800;background:#eef4fb;color:#155eeb}.ahp .st.PAID{background:#e5f5ef;color:#0d7a58}.ahp .st.REVERSED,.ahp .st.REJECTED{background:#fdecec;color:#b42318}.ahp .note{font-size:12px;color:#94a3b8;margin-top:12px}.ahp .link{display:flex;gap:8px;align-items:center;flex-wrap:wrap;background:#101d3a;color:#fff;border-radius:14px;padding:14px 16px;margin-top:22px;font-size:13px}.ahp .link code{background:rgba(255,255,255,.12);padding:6px 10px;border-radius:8px;word-break:break-all}.ahp-tabs{display:flex;gap:8px;overflow-x:auto;margin:4px 0 18px;padding-bottom:4px;scrollbar-width:none}.ahp-tabs::-webkit-scrollbar{display:none}.ahp-tab{flex:1;min-width:112px;padding:11px 14px;border:1px solid #e6eeea;background:#fff;border-radius:12px;font:inherit;font-weight:800;font-size:13.5px;color:#334155;cursor:pointer;white-space:nowrap}.ahp-tab.on{background:#101d3a;color:#fff;border-color:#101d3a}@media(max-width:800px){.ahp-cards{grid-template-columns:repeat(2,1fr)}}@media(max-width:480px){.ahp-cards{grid-template-columns:1fr}}</style>';
        $__lg0 = is_array($__d['league'] ?? null) ? $__d['league'] : [];
        $__lgIcons = ['semicorchea' => '♬', 'corchea' => '♪', 'negra' => '♩', 'blanca' => '𝅗𝅥', 'redonda' => '𝅝'];
        $__rangoIcon = $__lgIcons[$__lg0['code'] ?? ''] ?? '♫';
        echo '<style>@media(max-width:720px){.ahp{padding:18px 14px 44px}.ahp-h h1{font-size:22px}.ahp-cards{grid-template-columns:repeat(2,minmax(0,1fr))!important;gap:10px}.ahp-c{padding:12px}.ahp-tabs{position:sticky;top:0;z-index:6;background:rgba(255,255,255,.94);backdrop-filter:blur(10px);padding:6px 0;margin:0 0 14px;scroll-snap-type:x proximity}.ahp-tab{min-width:106px;scroll-snap-align:start;min-height:44px}.ahp input,.ahp textarea,.ahp select{font-size:16px!important}.ahp table{display:block;overflow-x:auto;white-space:nowrap;border-radius:12px}.ahp-ms-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}.ahp-pub{grid-template-columns:1fr!important}}@media(max-width:420px){.ahp-ms-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}.ahp button,.ahp a{-webkit-tap-highlight-color:transparent}</style>';
        echo '<style>@media(max-width:760px){.ahp-tr .rte__bar button{min-width:44px!important;height:44px!important;font-size:13px!important}.ahp-tr .tag button{min-width:32px;min-height:32px}}</style>';
        echo '<section class="ahp"><div class="ahp-h"><h1>Mi Antro</h1><span class="badge">Hola, ' . sonika_esc($__nameP) . '</span><span class="badge" style="background:#101d3a;color:#fff">Tu rango: ' . $__rangoIcon . ' ' . sonika_esc((string)($__lg0['level'] ?? '—')) . '</span><a href="/artista/' . sonika_esc($__affPP['slug']) . '" style="margin-left:auto;font-size:12.5px;color:#64748b;text-decoration:none">Ver mi página ↗</a></div>';
        $__pendingStates = ['ESTIMATED', 'PENDING_VALIDATION', 'ON_HOLD', 'PAYMENT_QUEUED'];
        $__pending = 0.0;
        foreach ($__pendingStates as $__ps) { if (isset($__d['by_status'][$__ps])) $__pending += (float)$__d['by_status'][$__ps]['amount']; }
        $__cutDay = max(1, min(28, (int)wwi_setting('affiliate_payout_day', 1)));
        $__cutDt = new DateTime(date('Y-m-') . str_pad((string)$__cutDay, 2, '0', STR_PAD_LEFT));
        if ($__cutDt < new DateTime('today')) $__cutDt->modify('+1 month');
        $__nextCutTxt = $__cutDt->format('d/m/Y');
        echo '<div class="ahp-cards">';
        echo '<div class="ahp-c"><small>Clicks (atribución)</small><b>' . (int)$__d['clicks'] . '</b></div>';
        echo '<div class="ahp-c"><small>Billete Ganado (ventas atribuidas)</small><b>' . $__money($__d['sales']) . '</b></div>';
        echo '<div class="ahp-c"><small>Comisiones (total)</small><b>' . $__money($__d['commission_total']) . '</b></div>';
        echo '<div class="ahp-c"><small>Billete pendiente por entrar</small><b>' . $__money($__pending) . '</b><small style="margin-top:4px;color:#94a3b8;font-weight:400;font-size:11px">Próximo corte: ' . $__nextCutTxt . '</small></div>';
        echo '</div>';
        $__lg = is_array($__d['league'] ?? null) ? $__d['league'] : ['level' => '—', 'sales' => 0, 'progress' => 0, 'next' => null];
        $__crAll = $__svcP->contractRequestsFor((int)$__affPP['id']);
        $__crNew = 0;
        foreach ($__crAll as $__cr0) { if (($__cr0['status'] ?? '') === 'new') $__crNew++; }
        $__tipsTot = $__svcP->tipsTotal((int)$__affPP['id']);
        $__postsAll = $__svcP->postsFor((int)$__affPP['id']);
        $__lgP = is_array($__d['league'] ?? null) ? $__d['league'] : [];
        $__sugg = [];
        if ((int)($__d['clicks'] ?? 0) === 0) $__sugg[] = 'Pon a Sonar: comparte tu enlace para empezar a generar clics y comisiones.';
        if ($__crNew > 0) $__sugg[] = 'Tienes ' . $__crNew . ' invitación(es) a unirte a una moña (solicitud(es) de contratación sin responder).';
        if ($__tipsTot > 0) $__sugg[] = 'Recibiste $ ' . number_format($__tipsTot, 0, ',', '.') . ' en aportes. ¡Agradece a tus fans!';
        if (!empty($__lgP['next']) && isset($__lgP['next_min'])) { $__need = max(0, (float)$__lgP['next_min'] - (float)($__lgP['sales'] ?? 0)); $__nextIcon = $__lgIcons[strtolower((string)$__lgP['next'])] ?? '♫'; $__sugg[] = 'Te faltan $ ' . number_format($__need, 0, ',', '.') . ' para llegar al Rango ' . $__nextIcon . ' ' . $__lgP['next'] . '.'; }
        if (empty($__postsAll)) $__sugg[] = 'Publica en tu Muro para conectar con tus fans.';
        if (empty($__sugg)) $__sugg[] = '¡Vas muy bien! Sigue compartiendo y respondiendo solicitudes.';
        echo '<div class="ahp-sec" style="margin-top:0"><h2>Mi Pulso — acciones sugeridas</h2><div style="background:#101d3a;color:#fff;border-radius:14px;padding:16px"><ul style="margin:0;padding-left:18px;line-height:1.8;font-size:13.5px">';
        foreach ($__sugg as $__s) echo '<li>' . sonika_esc($__s) . '</li>';
        echo '</ul></div></div>';
        echo '<nav class="ahp-tabs" role="tablist" aria-label="Secciones de Mi Antro">';
        foreach (['ventas' => 'Ventas', 'ganancias' => 'Ganancias', 'tienda' => 'Mi Tienda', 'comunidad' => 'Comunidad', 'perfil' => 'Mi Perfil'] as $__tk => $__tl) {
            echo '<button type="button" class="ahp-tab' . ($__tk === 'ventas' ? ' on' : '') . '" data-tab="' . $__tk . '" role="tab">' . sonika_esc($__tl) . '</button>';
        }
        echo '</nav>';
        $__media = $__svcP->mediaFor((int)$__affPP['id']);
        $__galM = json_decode((string)($__media['gallery'] ?? '[]'), true);
        if (!is_array($__galM)) $__galM = [];
        $__bioCur = '';
        try { $__stB = \App\Core\Database::instance()->prepare('SELECT bio FROM affiliate_stores WHERE affiliate_id = :a AND site_id = @site_id LIMIT 1'); $__stB->execute(['a' => (int)$__affPP['id']]); $__bioCur = (string)($__stB->fetchColumn() ?: ''); } catch (\Throwable $e) {}
        $__pubHtml = <<<'HTML'
<div class="ahp-sec" data-tab="perfil"><style>
.ahp-pub{display:grid;grid-template-columns:1.15fr .85fr;gap:16px;max-width:960px}
@media(max-width:820px){.ahp-pub{grid-template-columns:1fr}}
.ahp-pub-card{background:#fff;border:1px solid #eef2f0;border-radius:16px;padding:16px;box-shadow:0 10px 26px rgba(16,24,40,.05)}
.ahp-pub-card h3{margin:0 0 3px;font-size:15px;color:#101d3a}
.ahp-pub-card .hint{margin:0 0 12px;font-size:12px;color:#64748b}
.ahp-pub label{display:block;font-size:12px;font-weight:700;color:#51607a;margin:12px 0 5px}
.ahp-pub input,.ahp-pub textarea{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #dbe7e1;border-radius:10px;font:inherit;font-size:13px;background:#fbfdfc;transition:border-color .2s,box-shadow .2s}
.ahp-pub input:focus,.ahp-pub textarea:focus{outline:none;border-color:#7c5cff;box-shadow:0 0 0 3px rgba(124,92,255,.16);background:#fff}
.ahp-pub textarea{min-height:98px;resize:vertical;line-height:1.55}
.ahp-pub .count{font-size:11px;color:#94a3b8;text-align:right;margin-top:4px;font-weight:700}
.ahp-pub .count.warn{color:#d97706}
.ahp-pub .chips{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.ahp-pub .chip{font-size:11px;font-weight:700;color:#3b4a63;background:#f1f5ff;border:1px solid #dbe4ff;border-radius:999px;padding:4px 10px;cursor:pointer;transition:.18s;user-select:none}
.ahp-pub .chip:hover{background:#e6edff;transform:translateY(-1px)}
.ahp-pub .namebox{display:flex;align-items:center;gap:10px;background:#f6fbf9;border:1px dashed #cfe6dd;border-radius:12px;padding:10px 12px}
.ahp-pub .namebox b{color:#101d3a;font-size:14px}
.ahp-pub .namebox small{color:#0d7a58;font-weight:700;font-size:11px}
.ahp-pub .preview{position:relative;border-radius:16px;padding:18px;background:radial-gradient(400px 200px at 80% 0%,rgba(124,92,255,.35),transparent 60%),linear-gradient(160deg,#0b1226,#1a1240);color:#fff;overflow:hidden;box-shadow:0 16px 36px rgba(11,18,38,.28)}
.ahp-pub .preview small{font-size:10px;letter-spacing:.12em;text-transform:uppercase;color:#a9bbff;font-weight:800}
.ahp-pub .preview .q{font-style:italic;font-size:14px;line-height:1.6;color:#e6ecff;margin:8px 0 0;min-height:48px}
.ahp-pub .preview .q.empty{color:#7f8db3;font-style:normal}
.ahp-pub .preview .meta{font-size:11px;color:#9fb0d8;margin-top:10px}
.ahp-pub .save{width:100%;margin-top:14px;background:linear-gradient(135deg,#287bff,#7c5cff);color:#fff;border:0;border-radius:11px;padding:12px;font-weight:800;font-size:13px;cursor:pointer;transition:transform .2s,box-shadow .2s}
.ahp-pub .save:hover{transform:translateY(-1px);box-shadow:0 12px 26px rgba(124,92,255,.4)}
.ahp-pub .msg{font-size:12.5px;margin-top:8px;min-height:16px}
.ahp-pub details{margin-top:12px;border-top:1px solid #eef2f0;padding-top:10px}
.ahp-pub summary{cursor:pointer;font-size:12px;font-weight:800;color:#51607a;list-style:none}
.ahp-pub summary::-webkit-details-marker{display:none}
.ahp-pub summary:before{content:"＋ ";color:#7c5cff}
.ahp-pub details[open] summary:before{content:"− "}
</style>
<form id="ah-media" class="ahp-pub" novalidate>
<div class="ahp-pub-card">
<h3>Perfil público</h3><p class="hint">Así te presentas en tu página de artista. Todo se actualiza al instante.</p>
<div class="namebox"><span style="font-size:18px">👤</span><div><b>{{NAME}}</b><br><small>✓ Nombre tomado de tu registro</small></div></div>
<label for="ahp-bio">Descripción breve</label>
<textarea id="ahp-bio" name="bio" maxlength="220" placeholder="Ej: Guitarrista de rock. Recomiendo guitarras eléctricas, pedales y accesorios para sonar con todo.">{{BIO}}</textarea>
<div class="count" id="ahp-bio-count">0 / 220</div>
<div class="chips" id="ahp-bio-chips"><span class="chip" data-t="Guitarrista de rock. Recomiendo guitarras eléctricas, pedales y accesorios para sonar con todo.">Guitarrista rock</span><span class="chip" data-t="Músico versátil: estudio, tarima y producción. Te ayudo a encontrar tu sonido.">Versátil</span><span class="chip" data-t="Amante del blues y el rock clásico. Equipos y accesorios probados en vivo.">Blues &amp; rock</span></div>
<details><summary>Redes sociales y video</summary>
<label>Instagram (usuario o URL)</label><input name="instagram" value="{{INSTA}}" placeholder="@usuario" autocomplete="off">
<label>TikTok (usuario o URL)</label><input name="tiktok" value="{{TIKTOK}}" placeholder="https://tiktok.com/@usuario" autocomplete="off">
<label>Video de YouTube para la galería</label><input name="video_url" value="{{VIDEO}}" placeholder="https://youtu.be/... (se agrega al carrusel)" autocomplete="off"><div style="font-size:11px;color:#94a3b8;margin-top:4px">Se muestra como miniatura con ▶ y se reproduce en el cuadro principal.</div>
</details>
<details><summary>Imágenes por URL (opcional)</summary>
<label>Portada</label><input name="cover" value="{{COVER}}" autocomplete="off">
<label>Galería 1</label><input name="gallery0" value="{{G0}}" autocomplete="off">
<label>Galería 2</label><input name="gallery1" value="{{G1}}" autocomplete="off">
<label>Galería 3</label><input name="gallery2" value="{{G2}}" autocomplete="off">
</details>
<button type="submit" class="save">Guardar perfil</button>
<div class="msg" id="ah-media-msg"></div>
</div>
<div class="ahp-pub-card preview"><small>Vista previa · Hero de tu página</small><div class="q" id="ahp-bio-prev">{{BIO_PREV}}</div><div class="meta">{{NAME}} · Artista SONIKA</div><div class="meta" style="margin-top:6px">Los botones de Instagram / YouTube / TikTok aparecen solo si están configurados.</div></div>
</form>
<script>(function(){var f=document.getElementById('ah-media');if(!f)return;var t=f.querySelector('textarea[name=bio]'),c=document.getElementById('ahp-bio-count'),p=document.getElementById('ahp-bio-prev'),m=document.getElementById('ah-media-msg'),MAX=220;function upd(){if(!t)return;var v=t.value.trim();if(c){c.textContent=t.value.length+' / '+MAX;c.classList.toggle('warn',t.value.length>MAX-30);}if(p){p.textContent=v||'Aún sin descripción. Escribe una frase que te presente.';p.classList.toggle('empty',!v);}}if(t){t.addEventListener('input',upd);upd();}f.querySelectorAll('#ahp-bio-chips .chip').forEach(function(ch){ch.addEventListener('click',function(){if(!t)return;t.value=ch.getAttribute('data-t')||'';upd();t.focus();});});f.addEventListener('submit',function(e){e.preventDefault();var d={};new FormData(f).forEach(function(v,k){d[k]=v;});m.style.color='#51607a';m.textContent='Guardando…';fetch('/api/v1/public/affiliates/media',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)}).then(function(r){return r.json();}).then(function(j){m.style.color=j.ok?'#0d7a58':'#d64545';m.textContent=(j.ok?'✓ ':'')+(j.message||(j.ok?'Guardado':'Error'));}).catch(function(){m.style.color='#d64545';m.textContent='Error de conexión.';});});})();</script>
</div>
HTML;
        $__pubHtml = strtr($__pubHtml, [
            '{{NAME}}' => sonika_esc((string)$__nameP),
            '{{BIO}}' => sonika_esc($__bioCur),
            '{{BIO_PREV}}' => sonika_esc($__bioCur !== '' ? $__bioCur : 'Aún sin descripción. Escribe una frase que te presente.'),
            '{{VIDEO}}' => sonika_esc((string)($__media['video_url'] ?? '')),
            '{{INSTA}}' => sonika_esc((string)($__media['instagram'] ?? '')),
            '{{TIKTOK}}' => sonika_esc((string)($__media['tiktok'] ?? '')),
            '{{COVER}}' => sonika_esc((string)($__media['cover'] ?? '')),
            '{{G0}}' => sonika_esc((string)($__galM[0] ?? '')),
            '{{G1}}' => sonika_esc((string)($__galM[1] ?? '')),
            '{{G2}}' => sonika_esc((string)($__galM[2] ?? '')),
        ]);
        echo $__pubHtml;
        $__cvE = $__svcP->cvFor((int)$__affPP['id']) ?: [];
        $__cvEBands = json_decode((string)($__cvE['bands'] ?? '[]'), true); if (!is_array($__cvEBands)) $__cvEBands = [];
        $__cvEAch = json_decode((string)($__cvE['achievements'] ?? '[]'), true); if (!is_array($__cvEAch)) $__cvEAch = [];
        $__cvBioHtml = $__svcP->cvBioHtml((string)($__cvE['bio'] ?? ''));
        $__trHtml = <<<'HTML'
<div class="ahp-sec" data-tab="perfil"><style>
.ahp-tr{max-width:960px;background:#fff;border:1px solid #eef2f0;border-radius:16px;padding:18px}
.ahp-tr h3{margin:0 0 3px;font-size:15px;color:#101d3a}
.ahp-tr .hint{margin:0 0 14px;font-size:12px;color:#64748b}
.ahp-tr .grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.ahp-tr .full{grid-column:1/-1}
.ahp-tr label{display:block;font-size:11.5px;font-weight:700;color:#51607a;margin:0 0 5px}
.ahp-tr label span{font-weight:600;color:#94a3b8}
.ahp-tr > .grid > div > input[type=number]{width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid #e6eeea;border-radius:10px;font:inherit;font-size:13px;background:#fcfdfd}
.ahp-tr > .grid > div > input:focus{outline:none;border-color:#7c5cff;box-shadow:0 0 0 3px rgba(124,92,255,.14);background:#fff}
.ahp-tr .rte{border:1px solid #e6eeea;border-radius:12px;background:#fff;overflow:hidden}
.ahp-tr .rte__bar{display:flex;flex-wrap:wrap;gap:3px;padding:6px;border-bottom:1px solid #eef2f0;background:#fbfdfc;align-items:center}
.ahp-tr .rte__bar button{min-width:26px;height:26px;border:1px solid transparent;background:transparent;border-radius:7px;font:inherit;font-size:11.5px;font-weight:800;color:#3b4a63;cursor:pointer;display:inline-grid;place-items:center;padding:0 5px}
.ahp-tr .rte__bar button:hover{background:#eef4ff;border-color:#dbe4ff;color:#1d4ed8}
.ahp-tr .rte__bar .sep{width:1px;height:16px;background:#e2eae6;margin:0 3px}
.ahp-tr .rte__area{min-height:150px;max-height:280px;overflow:auto;padding:12px 14px;font-size:13px;line-height:1.65;color:#17212b;outline:none}
.ahp-tr .rte__area:empty:before{content:attr(data-placeholder);color:#a7b2c4}
.ahp-tr .rte__area h2{font-size:16px;margin:8px 0 4px}
.ahp-tr .rte__area h3{font-size:14px;margin:8px 0 4px}
.ahp-tr .rte__area ul,.ahp-tr .rte__area ol{margin:6px 0;padding-left:20px}
.ahp-tr .rte__area a{color:#2563eb}
.ahp-tr .rte__emoji{display:flex;flex-wrap:wrap;gap:2px;padding:6px;border-top:1px solid #eef2f0;background:#fbfdfc}
.ahp-tr .rte__emoji button{border:0;background:transparent;font-size:16px;cursor:pointer;border-radius:6px;padding:2px 4px}
.ahp-tr .rte__emoji button:hover{background:#eef4ff}
.ahp-tr .count{font-size:11px;color:#94a3b8;text-align:right;margin-top:4px;font-weight:700}
.ahp-tr .count.warn{color:#d97706}
.ahp-tr .tags{display:flex;flex-wrap:wrap;gap:6px;padding:7px 8px;border:1px solid #e6eeea;border-radius:10px;background:#fcfdfd;min-height:42px;align-items:center;transition:border-color .2s,box-shadow .2s}
.ahp-tr .tags:focus-within{border-color:#7c5cff;box-shadow:0 0 0 3px rgba(124,92,255,.14);background:#fff}
.ahp-tr .tags input{border:0;background:transparent;outline:none;flex:1;min-width:110px;font:inherit;font-size:12.5px;padding:3px}
.ahp-tr .tag{display:inline-flex;align-items:center;gap:5px;background:#eef4ff;border:1px solid #dbe4ff;color:#1e3a8a;border-radius:999px;padding:3px 5px 3px 10px;font-size:11.5px;font-weight:700}
.ahp-tr .tag button{border:0;background:transparent;color:#64748b;cursor:pointer;font-size:13px;line-height:1;padding:0 3px}
.ahp-tr .tag button:hover{color:#dc2626}
.ahp-tr .hint2{font-size:10.5px;color:#94a3b8;margin-top:5px}
.ahp-tr .save{margin-top:14px;background:#101d3a;color:#fff;border:0;border-radius:10px;padding:11px 20px;font-weight:800;font-size:12.5px;cursor:pointer;transition:.2s}
.ahp-tr .save:hover{background:#1d2f57;transform:translateY(-1px)}
.ahp-tr .msg{font-size:12.5px;margin-top:8px;min-height:16px}
.ahp-tr .note{font-size:11.5px;color:#94a3b8;margin-top:10px;line-height:1.5}
@media(max-width:700px){.ahp-tr .grid{grid-template-columns:1fr}}
</style>
<form id="ah-cv" class="ahp-tr" novalidate>
<h3>Trayectoria</h3><p class="hint">Edita con formato: lo que ves es lo que se publica. Puedes pegar desde Word, Docs o redes y se conserva el formato.</p>
<div class="grid">
<div class="full"><label>Historia / experiencia</label>
<div class="rte" data-rte>
<div class="rte__bar" role="toolbar" aria-label="Formato de texto">
<button type="button" data-cmd="bold" title="Negrita (Ctrl+B)"><b>B</b></button>
<button type="button" data-cmd="italic" title="Cursiva (Ctrl+I)"><i>I</i></button>
<button type="button" data-cmd="underline" title="Subrayado (Ctrl+U)"><u>U</u></button>
<span class="sep"></span>
<button type="button" data-block="h2" title="Título">H2</button>
<button type="button" data-block="h3" title="Subtítulo">H3</button>
<button type="button" data-block="p" title="Párrafo">¶</button>
<span class="sep"></span>
<button type="button" data-cmd="insertUnorderedList" title="Viñetas">•</button>
<button type="button" data-cmd="insertOrderedList" title="Numeración">1.</button>
<span class="sep"></span>
<button type="button" data-cmd="justifyLeft" title="Alinear a la izquierda">⇤</button>
<button type="button" data-cmd="justifyCenter" title="Centrar">↔</button>
<button type="button" data-cmd="justifyRight" title="Alinear a la derecha">⇥</button>
<span class="sep"></span>
<button type="button" data-act="link" title="Insertar enlace">🔗</button>
<button type="button" data-act="emoji" title="Emojis">😀</button>
<button type="button" data-act="plain" title="El próximo pegado será texto plano">T</button>
<button type="button" data-cmd="removeFormat" title="Quitar formato">⌫</button>
</div>
<div class="rte__area" contenteditable="true" role="textbox" aria-multiline="true" data-placeholder="Escribe o pega tu trayectoria: títulos, viñetas, emojis…">{{BIOHTML}}</div>
<div class="rte__emoji" hidden></div>
</div>
<input type="hidden" name="bio" id="ah-cv-bio">
<div class="count" id="ah-cv-count">0 / 8000</div></div>
<div><label for="ah-cv-years">Años de experiencia</label><input id="ah-cv-years" name="experience_years" type="number" min="0" max="60" value="{{YEARS}}" placeholder="8"></div>
<div><label>Bandas / artistas <span>(Enter o coma para agregar)</span></label><div class="tags" data-tags="bands" data-initial="{{BANDS_JSON}}"><input type="text" placeholder="Ej: Tierra Seca" aria-label="Agregar banda"></div><input type="hidden" name="bands" id="ah-cv-bands" value="{{BANDS_TEXT}}"><div class="hint2">Se publican como etiquetas enlazadas a la búsqueda del sitio (SEO).</div></div>
<div class="full"><label>Logros <span>(Enter o coma para agregar)</span></label><div class="tags" data-tags="ach" data-initial="{{ACH_JSON}}"><input type="text" placeholder="Ej: Gira nacional 2024" aria-label="Agregar logro"></div><input type="hidden" name="achievements" id="ah-cv-ach" value="{{ACH_TEXT}}"></div>
</div>
<button type="submit" class="save">Guardar trayectoria</button>
<div class="msg" id="ah-cv-msg"></div>
<div class="note">Consejo SEO: usa títulos y viñetas, y agrega las bandas/artistas con los que has trabajado; se indexan como metadatos de tu perfil.</div>
</form>
<script>(function(){
var form=document.getElementById('ah-cv');if(!form)return;
var rte=form.querySelector('[data-rte]'),area=rte.querySelector('.rte__area'),hidden=document.getElementById('ah-cv-bio'),count=document.getElementById('ah-cv-count'),emojiBox=rte.querySelector('.rte__emoji'),m=document.getElementById('ah-cv-msg');
function sync(){hidden.value=area.innerHTML;var n=(area.textContent||'').length;if(count){count.textContent=n+' / 8000';count.classList.toggle('warn',n>7200);}}
area.addEventListener('input',sync);
function sanitize(html){var d=document.createElement('div');d.innerHTML=html;d.querySelectorAll('script,style,iframe,object,embed,meta,link').forEach(function(n){n.remove();});var ok={P:1,BR:1,STRONG:1,B:1,EM:1,I:1,U:1,H2:1,H3:1,H4:1,UL:1,OL:1,LI:1,BLOCKQUOTE:1,A:1,DIV:1,SPAN:1};function walk(node){[].slice.call(node.childNodes).forEach(function(ch){if(ch.nodeType!==1)return;if(!ok[ch.tagName]){var f=document.createDocumentFragment();while(ch.firstChild)f.appendChild(ch.firstChild);ch.parentNode.replaceChild(f,ch);walk(node);return;}[].slice.call(ch.attributes).forEach(function(a){if(!(ch.tagName==='A'&&a.name==='href'))ch.removeAttribute(a.name);});if(ch.tagName==='A'){ch.target='_blank';ch.rel='nofollow noopener';}walk(ch);});}walk(d);return d.innerHTML;}
area.addEventListener('paste',function(e){var cd=e.clipboardData||window.clipboardData;if(!cd)return;if(area.dataset.plain==='1'){e.preventDefault();area.dataset.plain='0';document.execCommand('insertText',false,cd.getData('text/plain'));setTimeout(sync,0);return;}var html=cd.getData('text/html');if(html){e.preventDefault();document.execCommand('insertHTML',false,sanitize(html));setTimeout(sync,0);}else{var txt=cd.getData('text/plain');if(txt){e.preventDefault();var lines=txt.replace(/\r\n?/g,'\n').split('\n');var out=[];lines.forEach(function(ln){var s=ln.trim();if(!s){out.push('');return;}s=s.replace(/\*\*(.+?)\*\*/g,'<strong>$1</strong>');if(/^[-•]\s+/.test(s)){out.push('<ul><li>'+s.replace(/^[-•]\s+/,'')+'</li></ul>');}else if(/^\d+[.)]\s+/.test(s)){out.push('<ol><li>'+s.replace(/^\d+[.)]\s+/,'')+'</li></ol>');}else if(/^#{2,3}\s+/.test(s)){out.push('<h3>'+s.replace(/^#{2,3}\s+/,'')+'</h3>');}else{out.push('<p>'+s+'</p>');}});document.execCommand('insertHTML',false,sanitize(out.join('')));setTimeout(sync,0);}}});
rte.querySelectorAll('.rte__bar [data-cmd]').forEach(function(b){b.addEventListener('click',function(){area.focus();document.execCommand(b.getAttribute('data-cmd'),false,null);sync();});});
rte.querySelectorAll('.rte__bar [data-block]').forEach(function(b){b.addEventListener('click',function(){area.focus();document.execCommand('formatBlock',false,b.getAttribute('data-block'));sync();});});
var linkBtn=rte.querySelector('[data-act=link]');if(linkBtn)linkBtn.addEventListener('click',function(){var u=prompt('URL del enlace (https://…)','https://');if(!u)return;if(!/^https?:\/\//i.test(u)){u='https://'+u;}area.focus();document.execCommand('createLink',false,u);var as=area.querySelectorAll('a');[].slice.call(as).forEach(function(a){a.target='_blank';a.rel='nofollow noopener';});sync();});
var plainBtn=rte.querySelector('[data-act=plain]');if(plainBtn)plainBtn.addEventListener('click',function(){area.dataset.plain='1';area.focus();if(m){m.style.color='#51607a';m.textContent='Modo pegado limpio activado para el próximo Ctrl+V.';}});
var emoBtn=rte.querySelector('[data-act=emoji]');if(emoBtn)emoBtn.addEventListener('click',function(){emojiBox.hidden=!emojiBox.hidden;});
var EMOJIS=['🎶','🎸','🥁','🎤','🎹','🎺','🎷','🎧','🔥','🌟','⚡','💥','✨','🏆','🎬','📸','📍','🎵','🌎','💫','👏','🙌','🚀','❤️'];
EMOJIS.forEach(function(em){var b=document.createElement('button');b.type='button';b.textContent=em;b.addEventListener('click',function(){area.focus();document.execCommand('insertText',false,em);sync();});emojiBox.appendChild(b);});
form.querySelectorAll('[data-tags]').forEach(function(box){var key=box.getAttribute('data-tags'),hid=document.getElementById(key==='bands'?'ah-cv-bands':'ah-cv-ach'),input=box.querySelector('input'),items=[];try{items=JSON.parse(box.getAttribute('data-initial')||'[]');}catch(e){items=[];}if(!Array.isArray(items))items=[];function render(){[].slice.call(box.querySelectorAll('.tag')).forEach(function(n){n.remove();});items.forEach(function(v,i){var s=document.createElement('span');s.className='tag';var t=document.createElement('span');t.textContent=v;var x=document.createElement('button');x.type='button';x.innerHTML='&times;';x.setAttribute('aria-label','Quitar '+v);x.addEventListener('click',function(){items.splice(i,1);render();});s.appendChild(t);s.appendChild(x);box.insertBefore(s,input);});hid.value=items.join('\n');}function add(){var v=(input.value||'').replace(/\s+/g,' ').trim();if(!v)return;if(items.indexOf(v)<0&&items.length<12){items.push(v.slice(0,80));}input.value='';render();}input.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===','){e.preventDefault();add();}else if(e.key==='Backspace'&&!input.value&&items.length){items.pop();render();}});input.addEventListener('blur',add);render();});
sync();
form.addEventListener('submit',function(e){e.preventDefault();sync();var d={};new FormData(form).forEach(function(v,k){d[k]=v;});m.style.color='#51607a';m.textContent='Guardando…';fetch('/api/v1/public/affiliates/cv',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)}).then(function(r){return r.json();}).then(function(j){m.style.color=j.ok?'#0d7a58':'#d64545';m.textContent=(j.ok?'✓ ':'')+(j.message||(j.ok?'Guardado':'Error'));}).catch(function(){m.style.color='#d64545';m.textContent='Error de conexión.';});});
})();</script>
</div>
HTML;
        $__trHtml = strtr($__trHtml, [
            '{{BIOHTML}}' => $__cvBioHtml,
            '{{YEARS}}' => sonika_esc((string)($__cvE['experience_years'] ?? '')),
            '{{BANDS_JSON}}' => sonika_esc(json_encode($__cvEBands, JSON_UNESCAPED_UNICODE)),
            '{{ACH_JSON}}' => sonika_esc(json_encode($__cvEAch, JSON_UNESCAPED_UNICODE)),
            '{{BANDS_TEXT}}' => sonika_esc(implode("\n", array_map('strval', $__cvEBands))),
            '{{ACH_TEXT}}' => sonika_esc(implode("\n", array_map('strval', $__cvEAch))),
        ]);
        echo $__trHtml;
        $__mCover = (string)($__media['cover'] ?? '');
        $__mAvatar = (string)($__media['avatar'] ?? '');
        $__usedM = 0;
        foreach (['cover', 'avatar'] as $__mk) { $__mp = '/app/public' . (string)($__media[$__mk] ?? ''); if ($__mp !== '/app/public' && is_file($__mp)) $__usedM += (int)filesize($__mp); }
        foreach ($__galM as $__mg) { $__mp = '/app/public' . (string)$__mg; if ($__mp !== '/app/public' && is_file($__mp)) $__usedM += (int)filesize($__mp); }
        $__msCards = [
            ['slot' => 'cover', 'label' => 'Portada', 'icon' => '🎬', 'src' => $__mCover, 'title' => 'Foto de portada', 'em' => 'Fondo de tu hero y miniatura al compartir.', 'specs' => ['Ideal: <b>1600 × 900 px</b> (16:9).', 'Mínimo <b>1200 px</b> de ancho · hasta 5 MB.', 'Buena luz, sin texto en los bordes.'], 'gain' => 'Hasta <b>+40%</b> de clics a tu perfil.'],
            ['slot' => 'avatar', 'label' => 'Perfil', 'icon' => '🎤', 'src' => $__mAvatar, 'title' => 'Foto de perfil', 'em' => 'Tu rostro en el círculo del hero.', 'specs' => ['Ideal: <b>800 × 800 px</b> (1:1).', 'Rostro centrado y nítido · hasta 5 MB.', 'Se recorta en círculo.'], 'gain' => 'Más confianza y <b>contrataciones</b>.'],
            ['slot' => 'gallery0', 'label' => 'Galería 1', 'icon' => '📸', 'src' => (string)($__galM[0] ?? ''), 'title' => 'Galería — foto 1', 'em' => 'Primera imagen de tu carrusel.', 'specs' => ['Ideal: <b>1200 × 1200 px</b> o vertical.', 'Mínimo <b>800 px</b> · hasta 5 MB.'], 'gain' => 'Más tiempo en tu página.'],
            ['slot' => 'gallery1', 'label' => 'Galería 2', 'icon' => '🎶', 'src' => (string)($__galM[1] ?? ''), 'title' => 'Galería — foto 2', 'em' => 'Segunda imagen de tu carrusel.', 'specs' => ['Ideal: <b>1200 × 1200 px</b> o vertical.', 'Mínimo <b>800 px</b> · hasta 5 MB.'], 'gain' => 'Perfil más <b>profesional</b>.'],
            ['slot' => 'gallery2', 'label' => 'Galería 3', 'icon' => '⚡', 'src' => (string)($__galM[2] ?? ''), 'title' => 'Galería — foto 3', 'em' => 'Tercera imagen de tu carrusel.', 'specs' => ['Ideal: <b>1200 × 1200 px</b> o vertical.', 'Mínimo <b>800 px</b> · hasta 5 MB.'], 'gain' => 'Cierre <b>memorable</b>.'],
        ];
        $__msCss = <<<'CSS'
.ahp-ms{position:relative;border-radius:16px;padding:13px 15px;background:linear-gradient(150deg,#0b1020,#111a33 60%,#0b1226);border:1px solid rgba(124,92,255,.28);box-shadow:0 14px 34px rgba(11,18,38,.22);color:#e6ecff;font-family:Sora,system-ui,sans-serif;overflow:visible}
.ahp-ms:before{content:"";position:absolute;inset:0;border-radius:16px;background-image:linear-gradient(90deg,rgba(124,92,255,.09) 1px,transparent 1px),linear-gradient(0deg,rgba(124,92,255,.09) 1px,transparent 1px);background-size:38px 38px;-webkit-mask-image:radial-gradient(circle at 50% 0%,#000,transparent 70%);mask-image:radial-gradient(circle at 50% 0%,#000,transparent 70%);pointer-events:none;overflow:hidden}
.ahp-ms-head{position:relative;z-index:1;display:flex;align-items:center;gap:10px;margin-bottom:10px}
.ahp-ms-title{display:flex;gap:9px;align-items:center;min-width:0}
.ahp-ms-title h2{margin:0;font-size:13.5px;letter-spacing:.01em;background:linear-gradient(90deg,#c9d6ff,#7ce8ff 60%,#b39bff);-webkit-background-clip:text;background-clip:text;color:transparent;white-space:nowrap}
.ahp-ms-title p{margin:0;font-size:10.5px;color:#8ea0c9;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ahp-ms-eq{display:inline-flex;gap:2px;align-items:flex-end;height:18px;flex:none}
.ahp-ms-eq i{width:3px;border-radius:2px;background:linear-gradient(180deg,#7c5cff,#37e8c8);height:5px;animation:ahpEq 1.15s ease-in-out infinite}
.ahp-ms-eq i:nth-child(2){animation-delay:.14s}.ahp-ms-eq i:nth-child(3){animation-delay:.28s}.ahp-ms-eq i:nth-child(4){animation-delay:.42s}.ahp-ms-eq i:nth-child(5){animation-delay:.56s}
@keyframes ahpEq{0%,100%{height:5px;opacity:.6}50%{height:18px;opacity:1}}
.ahp-ms-quota{margin-left:auto;display:flex;align-items:center;gap:8px;flex:none}
.ahp-ms-quota-bar{width:76px;height:5px;border-radius:999px;background:rgba(255,255,255,.13);overflow:hidden}
.ahp-ms-quota-bar span{display:block;height:100%;width:0;border-radius:999px;background:linear-gradient(90deg,#37e8c8,#7c5cff);transition:width .4s ease}
.ahp-ms-quota-txt{font-size:10px;color:#93a4c9;white-space:nowrap}
.ahp-ms-quota-txt b{color:#dbe6ff;font-weight:700}
.ahp-ms-grid{position:relative;z-index:1;display:grid;grid-template-columns:repeat(5,minmax(0,104px));gap:9px;justify-content:start}
.ahp-ms-card{display:flex;flex-direction:column;gap:3px;min-width:0}
.ahp-ms-card-top{display:flex;align-items:center;justify-content:space-between;gap:4px;padding:0 1px}
.ahp-ms-badge{font-size:9px;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:#9fb0d8;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ahp-ms-info{position:relative;flex:none;width:15px;height:15px;border-radius:50%;border:1px solid rgba(255,255,255,.26);background:rgba(255,255,255,.06);color:#c9d6ff;font-weight:800;font-size:9px;line-height:1;display:grid;place-items:center;cursor:help;padding:0}
.ahp-ms-info:hover,.ahp-ms-info:focus{background:rgba(124,92,255,.4);outline:none}
.ahp-ms-tip{position:absolute;top:135%;right:-4px;width:224px;padding:10px 11px;border-radius:11px;background:#090f22;border:1px solid rgba(124,92,255,.5);box-shadow:0 18px 40px rgba(0,0,0,.55);color:#c9d6ff;font-size:10.5px;line-height:1.45;text-align:left;opacity:0;visibility:hidden;transform:translateY(-4px);transition:.18s;z-index:40;pointer-events:none}
.ahp-ms-info:hover .ahp-ms-tip,.ahp-ms-info:focus .ahp-ms-tip{opacity:1;visibility:visible;transform:none}
.ahp-ms-tip b{display:block;color:#fff;font-size:11.5px;margin-bottom:2px}
.ahp-ms-tip em{display:block;font-style:normal;color:#9fb0d8;margin-bottom:6px}
.ahp-ms-tip ul{margin:0 0 6px;padding-left:14px}
.ahp-ms-tip li{margin:1px 0}
.ahp-ms-tip li b{display:inline;font-size:10.5px;color:#8fe9ff}
.ahp-ms-gain{display:block;padding-top:6px;border-top:1px dashed rgba(255,255,255,.16);color:#7cf0cf;font-size:10px;line-height:1.4}
.ahp-ms-drop{position:relative;aspect-ratio:1/1;border-radius:10px;border:1.5px dashed rgba(160,180,255,.32);background:rgba(6,10,24,.55);display:flex;align-items:center;justify-content:center;overflow:hidden;cursor:pointer;transition:border-color .2s,background .2s,transform .2s}
.ahp-ms-drop:hover{border-color:rgba(124,92,255,.7);background:rgba(124,92,255,.1);transform:translateY(-1px)}
.ahp-ms-drop:focus-visible{outline:2px solid #37e8c8;outline-offset:2px}
.ahp-ms-drop.is-over{border-color:#37e8c8;background:rgba(55,232,200,.1)}
.ahp-ms-card.is-done .ahp-ms-drop{border-style:solid;border-color:rgba(55,232,200,.42)}
.ahp-ms-empty{display:flex;flex-direction:column;align-items:center;gap:5px;text-align:center;padding:4px;transition:opacity .25s,transform .25s}
.ahp-ms-empty p,.ahp-ms-empty small{display:none}
.ahp-ms-icon{font-size:15px;filter:drop-shadow(0 3px 8px rgba(124,92,255,.5))}
.ahp-ms-file{position:relative;overflow:hidden;border-radius:7px;padding:4px 10px;font-weight:800;font-size:9.5px;letter-spacing:.03em;color:#fff;background:linear-gradient(135deg,#287bff,#7c5cff);box-shadow:0 5px 14px rgba(80,80,255,.3);transition:transform .2s,box-shadow .2s,filter .2s;display:inline-block;white-space:nowrap}
.ahp-ms-file:after{content:"";position:absolute;top:0;left:-70%;width:45%;height:100%;background:linear-gradient(90deg,transparent,rgba(255,255,255,.6),transparent);transform:skewX(-22deg);transition:left .55s}
.ahp-ms-drop:hover .ahp-ms-file{transform:translateY(-1px);box-shadow:0 8px 18px rgba(124,92,255,.55);filter:brightness(1.08)}
.ahp-ms-drop:hover .ahp-ms-file:after{left:125%}
.ahp-ms-preview{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;opacity:0;transform:scale(1.06);transition:opacity .4s,transform .5s}
.ahp-ms-card.is-preview .ahp-ms-empty,.ahp-ms-card.is-done .ahp-ms-empty{opacity:0;visibility:hidden;transform:scale(.95)}
.ahp-ms-card.is-preview .ahp-ms-preview,.ahp-ms-card.is-done .ahp-ms-preview{opacity:1;transform:none}
.ahp-ms-progress{position:absolute;left:6px;right:6px;bottom:6px;height:5px;border-radius:999px;background:rgba(255,255,255,.18);overflow:hidden}
.ahp-ms-progress span{display:block;height:100%;width:0;border-radius:999px;background:linear-gradient(90deg,#37e8c8,#7c5cff,#287bff);transition:width .18s linear}
.ahp-ms-pct{position:absolute;top:5px;right:5px;padding:2px 6px;border-radius:999px;background:rgba(6,10,24,.78);border:1px solid rgba(255,255,255,.2);font-size:9px;font-weight:800;color:#dbe6ff}
.ahp-ms-status{font-size:9.5px;line-height:1.3;color:#7f8fb6;padding:0 1px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.ahp-ms-status.ok{color:#5ff0cc}
.ahp-ms-status.err{color:#ff9a9a}
.ahp-ms-retry{margin-left:3px;border:1px solid rgba(255,154,154,.5);background:transparent;color:#ff9a9a;border-radius:6px;padding:0 5px;font:inherit;font-size:9px;font-weight:800;cursor:pointer}
.ahp-ms-foot{position:relative;z-index:1;display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:10px;padding-top:9px;border-top:1px solid rgba(255,255,255,.1)}
#ahp-ms-summary{font-size:10.5px;color:#9fb0d8}
#ahp-ms-summary b{color:#7cf0cf}
.ahp-ms-cta{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:8px;border:1px solid rgba(124,92,255,.55);background:rgba(124,92,255,.16);color:#dbe6ff;text-decoration:none;font-weight:800;font-size:10.5px;transition:.2s}
.ahp-ms-cta:hover{background:rgba(124,92,255,.34);transform:translateY(-1px)}
@media(max-width:760px){.ahp-ms-grid{grid-template-columns:repeat(auto-fit,minmax(82px,1fr))}.ahp-ms-title p{display:none}.ahp-ms-quota-bar{width:54px}.ahp-ms-tip{right:auto;left:-40px}}
CSS;
        echo '<div class="ahp-sec ahp-media-studio" data-tab="perfil"><div class="ahp-ms"><style>' . $__msCss . '</style>';
        echo '<div class="ahp-ms-head"><div class="ahp-ms-title"><span class="ahp-ms-eq" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i></span><h2>Estudio Visual</h2><p>· optimización WebP automática</p></div><div class="ahp-ms-quota"><div class="ahp-ms-quota-bar"><span id="ahp-ms-quota-fill"></span></div><div class="ahp-ms-quota-txt"><b id="ahp-ms-quota-used">0 KB</b> / 5 MB</div></div></div>';
        echo '<div class="ahp-ms-grid">';
        foreach ($__msCards as $__c) {
            $__src = (string)$__c['src'];
            $__has = $__src !== '';
            echo '<article class="ahp-ms-card' . ($__has ? ' is-done' : '') . '" data-slot="' . sonika_esc($__c['slot']) . '">';
            echo '<div class="ahp-ms-card-top"><span class="ahp-ms-badge">' . sonika_esc($__c['label']) . '</span><button type="button" class="ahp-ms-info" aria-label="Qué subir en ' . sonika_esc($__c['label']) . '">i<span class="ahp-ms-tip" role="tooltip"><b>' . sonika_esc($__c['title']) . '</b><em>' . $__c['em'] . '</em><ul>';
            foreach ($__c['specs'] as $__s) echo '<li>' . $__s . '</li>';
            echo '</ul><span class="ahp-ms-gain">🚀 ' . $__c['gain'] . '</span></span></button></div>';
            echo '<div class="ahp-ms-drop" role="button" tabindex="0" aria-label="Subir ' . sonika_esc($__c['label']) . '"><input type="file" accept="image/jpeg,image/png,image/webp" hidden><img class="ahp-ms-preview" alt="' . sonika_esc($__c['label']) . '"' . ($__has ? ' src="' . sonika_esc($__src) . '"' : '') . '><div class="ahp-ms-empty"><div class="ahp-ms-icon">' . $__c['icon'] . '</div><span class="ahp-ms-file">Subir</span></div><div class="ahp-ms-progress" hidden><span></span></div><div class="ahp-ms-pct" hidden>0%</div></div>';
            echo '<div class="ahp-ms-status' . ($__has ? ' ok' : '') . '" aria-live="polite">' . ($__has ? '✓ Publicada' : 'Sin foto') . '</div>';
            echo '</article>';
        }
        echo '</div>';
        echo '<div class="ahp-ms-foot"><div id="ahp-ms-summary"></div><a class="ahp-ms-cta" href="/artista/' . sonika_esc((string)$__affPP['slug']) . '" target="_blank" rel="noopener">Ver mi página ↗</a></div>';
        echo '</div></div>';
        $__msJs = <<<'JS'
(function(){
  var endpoint='/api/v1/public/affiliates/media/upload';
  var QUOTA=5*1024*1024;
  var PHRASES=['✓ Guardada','✓ Lista','✓ Optimizada','✓ Publicada','✓ Perfecta'];
  var cards=[].slice.call(document.querySelectorAll('.ahp-ms-card'));
  var sum=document.getElementById('ahp-ms-summary');
  var usedTxt=document.getElementById('ahp-ms-quota-used');
  var fill=document.getElementById('ahp-ms-quota-fill');
  function human(b){return b>=1048576?(b/1048576).toFixed(1)+' MB':Math.max(1,Math.round(b/1024))+' KB';}
  function setQuota(u){if(fill)fill.style.width=Math.min(100,u/QUOTA*100)+'%';if(usedTxt)usedTxt.textContent=human(u);}
  function refresh(){var done=cards.filter(function(c){return c.classList.contains('is-done');}).length;if(sum){sum.innerHTML=done===cards.length?('<b>¡Todo listo!</b> '+done+'/'+cards.length+' publicadas 🎉'):(done+'/'+cards.length+' fotos listas');}}
  setQuota(window.__MS_USED||0);refresh();
  cards.forEach(function(card){
    var input=card.querySelector('input[type=file]');
    var drop=card.querySelector('.ahp-ms-drop');
    var img=card.querySelector('.ahp-ms-preview');
    var status=card.querySelector('.ahp-ms-status');
    var bar=card.querySelector('.ahp-ms-progress span');
    var pw=card.querySelector('.ahp-ms-progress');
    var pct=card.querySelector('.ahp-ms-pct');
    var last=null;
    function say(t,cls){status.className='ahp-ms-status'+(cls?' '+cls:'');status.textContent=t;}
    function pick(f){
      if(!f)return;
      if(!/^image\/(jpeg|png|webp)$/.test(f.type)){say('Usa JPG, PNG o WebP','err');return;}
      if(f.size>5*1024*1024){say('Máximo 5 MB','err');return;}
      last=f;
      card.classList.add('is-preview');
      img.src=URL.createObjectURL(f);
      say(human(f.size)+' · subiendo…','');
      send(f);
    }
    function send(f){
      var xhr=new XMLHttpRequest();
      pw.hidden=false;pct.hidden=false;bar.style.width='0%';pct.textContent='0%';
      xhr.upload.onprogress=function(e){if(e.lengthComputable){var p=Math.round(e.loaded/e.total*100);bar.style.width=p+'%';pct.textContent=p+'%';}};
      xhr.onload=function(){
        var j=null;try{j=JSON.parse(xhr.responseText);}catch(e){}
        pw.hidden=true;pct.hidden=true;
        if(j&&j.ok){
          card.classList.add('is-done');card.classList.remove('is-preview');
          say(PHRASES[Math.floor(Math.random()*PHRASES.length)],'ok');
          if(typeof j.used==='number')setQuota(j.used);
          refresh();
        }else{
          card.classList.remove('is-done');
          say((j&&j.message)||'No se pudo guardar','err');
          if(!status.querySelector('.ahp-ms-retry')){var b=document.createElement('button');b.type='button';b.className='ahp-ms-retry';b.textContent='Reintentar';b.onclick=function(){if(last)send(last);};status.appendChild(b);}
        }
      };
      xhr.onerror=function(){pw.hidden=true;pct.hidden=true;say('Error de conexión','err');};
      var fd=new FormData();fd.append(card.getAttribute('data-slot'),f);xhr.send(fd);
    }
    drop.addEventListener('click',function(e){if(e.target.closest('.ahp-ms-info'))return;input.click();});
    drop.addEventListener('keydown',function(e){if(e.key==='Enter'||e.key===' '){e.preventDefault();input.click();}});
    input.addEventListener('change',function(){pick(input.files&&input.files[0]);});
    ['dragenter','dragover'].forEach(function(ev){drop.addEventListener(ev,function(e){e.preventDefault();drop.classList.add('is-over');});});
    ['dragleave','drop'].forEach(function(ev){drop.addEventListener(ev,function(e){e.preventDefault();drop.classList.remove('is-over');});});
    drop.addEventListener('drop',function(e){if(e.dataTransfer&&e.dataTransfer.files&&e.dataTransfer.files[0])pick(e.dataTransfer.files[0]);});
  });
})();
JS;
        echo '<script>window.__MS_USED=' . (int)$__usedM . ';</script><script>' . $__msJs . '</script>';
        echo '<div class="ahp-sec" data-tab="perfil"><h2>SONIKA Sound League</h2><div style="background:#fff;border:1px solid #eef2f0;border-radius:14px;padding:16px"><div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap"><div><span style="font-size:12px;color:#64748b">Tu nivel</span><div style="font-size:22px;font-weight:800;color:#101d3a">' . sonika_esc((string)($__lg['level'] ?? '—')) . '</div></div><div style="text-align:right;font-size:12px;color:#64748b">' . (!empty($__lg['next']) ? ('Próximo: <b>' . sonika_esc((string)$__lg['next']) . '</b>') : 'Nivel máximo') . '<br>Ventas del mes: <b>' . $__money($__lg['sales'] ?? 0) . '</b></div></div><div style="margin-top:12px;height:10px;background:#eef2f0;border-radius:999px;overflow:hidden"><div style="height:100%;width:' . (int)($__lg['progress'] ?? 0) . '%;background:linear-gradient(90deg,#0d9f72,#7c5cff)"></div></div><div style="font-size:11.5px;color:#94a3b8;margin-top:6px">Progreso al siguiente nivel: ' . (int)($__lg['progress'] ?? 0) . '%</div></div></div>';
        $__awards = $__svcP->awardsFor((int)$__affPP['id']);
        echo '<div class="ahp-sec" data-tab="perfil"><h2>Logros y Premios</h2>';
        if (!$__awards) {
            echo '<div style="background:#fff;border:1px dashed #dbe7e1;border-radius:12px;padding:16px;color:#94a3b8;font-size:13px">Aún sin logros. ¡A vender y subir de nivel!</div>';
        } else {
            echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">';
            foreach ($__awards as $__aw) {
                echo '<div style="background:#fff;border:1px solid #eef2f0;border-radius:14px;padding:14px"><div style="font-size:11px;color:#b7791f;font-weight:800;letter-spacing:.05em;text-transform:uppercase">' . sonika_esc((string)$__aw['kind']) . '</div><div style="font-weight:800;color:#101d3a;margin:4px 0 2px">' . sonika_esc((string)$__aw['title']) . '</div>' . (!empty($__aw['description']) ? '<div style="font-size:12.5px;color:#64748b">' . sonika_esc((string)$__aw['description']) . '</div>' : '') . '<div style="font-size:11px;color:#94a3b8;margin-top:6px">' . sonika_esc(substr((string)$__aw['created_at'], 0, 10)) . '</div></div>';
            }
            echo '</div>';
        }
        echo '</div>';
        $__creqs = $__svcP->contractRequestsFor((int)$__affPP['id']);
        echo '<div class="ahp-sec" data-tab="comunidad"><h2>Solicitudes de contratación</h2>';
        if (!$__creqs) {
            echo '<div style="background:#fff;border:1px dashed #dbe7e1;border-radius:12px;padding:16px;color:#94a3b8;font-size:13px">Sin solicitudes todavía.</div>';
        } else {
            echo '<table><thead><tr><th>De</th><th>Tipo</th><th>Fecha</th><th>Lugar</th><th>Contacto</th><th>Estado</th></tr></thead><tbody>';
            foreach ($__creqs as $__cr) {
                echo '<tr><td>' . sonika_esc((string)$__cr['name']) . '</td><td>' . sonika_esc((string)($__cr['event_type'] ?? '—')) . '</td><td>' . sonika_esc((string)($__cr['event_date'] ?? '—')) . '</td><td>' . sonika_esc((string)($__cr['place'] ?? '—')) . '</td><td><a href="mailto:' . sonika_esc((string)$__cr['email']) . '">' . sonika_esc((string)$__cr['email']) . '</a></td><td>' . sonika_esc((string)$__cr['status']) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
        $__tips = $__svcP->tipsFor((int)$__affPP['id']);
        $__tipsTotal = $__svcP->tipsTotal((int)$__affPP['id']);
        echo '<div class="ahp-sec" data-tab="comunidad"><h2>Aportes de agradecimiento · ' . sonika_esc('$ ' . number_format($__tipsTotal, 0, ',', '.')) . '</h2>';
        if (!$__tips) {
            echo '<div style="background:#fff;border:1px dashed #dbe7e1;border-radius:12px;padding:16px;color:#94a3b8;font-size:13px">Sin aportes todavía.</div>';
        } else {
            echo '<table><thead><tr><th>De</th><th>Monto</th><th>Mensaje</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>';
            foreach ($__tips as $__t) {
                echo '<tr><td>' . sonika_esc((string)($__t['sender_name'] ?: '—')) . '</td><td>$ ' . number_format((float)$__t['amount'], 0, ',', '.') . '</td><td>' . sonika_esc((string)($__t['message'] ?? '')) . '</td><td>' . sonika_esc((string)$__t['status']) . '</td><td>' . sonika_esc(substr((string)$__t['created_at'], 0, 10)) . '</td></tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
        $__avail = $__svcP->availableBalance((int)$__affPP['id']);
        echo '<div class="ahp-sec" data-tab="ganancias"><h2>Retiro</h2><div style="background:#fff;border:1px solid #eef2f0;border-radius:14px;padding:16px"><div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center"><div><div style="font-size:12px;color:#64748b">Saldo disponible para retirar</div><div style="font-size:22px;font-weight:800;color:#0d7a58">' . $__money($__avail) . '</div><div style="font-size:11.5px;color:#94a3b8">Mínimo $ 100.000 · confirmación por OTP al correo</div></div><button type="button" id="ah-payout" style="background:#0d9f72;color:#fff;border:0;border-radius:12px;padding:12px 18px;font-weight:800;cursor:pointer"' . ($__avail >= 100000 ? '' : ' disabled') . '>Solicitar retiro</button></div><div id="ah-payout-otp" style="display:none;margin-top:12px"><p style="font-size:13px;color:#51607a;margin:0 0 8px">Ingresa el código enviado a tu correo para confirmar.</p><div style="display:flex;gap:8px;flex-wrap:wrap"><input id="ah-payout-code" inputmode="numeric" placeholder="Código OTP" style="padding:10px;border:1px solid #dbe7e1;border-radius:10px;font:inherit;max-width:170px"><button type="button" id="ah-payout-ok" style="background:#101d3a;color:#fff;border:0;border-radius:10px;padding:10px 16px;font-weight:700;cursor:pointer">Confirmar retiro</button></div><div id="ah-payout-msg" style="font-size:12.5px;margin-top:8px"></div></div></div></div>';
        echo '<script>(function(){var b=document.getElementById("ah-payout"),k=document.getElementById("ah-payout-ok"),c=document.getElementById("ah-payout-code"),w=document.getElementById("ah-payout-otp"),m=document.getElementById("ah-payout-msg");if(!b)return;function msg(t,ok){m.style.color=ok?"#0d7a58":"#d64545";m.textContent=t;}b.addEventListener("click",function(){b.disabled=true;msg("Enviando código…",true);fetch("/api/v1/public/affiliates/payout/request",{method:"POST",headers:{"Content-Type":"application/json"},body:"{}"}).then(function(r){return r.json();}).then(function(j){if(j.ok){w.style.display="block";msg(j.message||"Revisa tu correo.",true);}else{msg(j.message||"Error",false);b.disabled=false;}}).catch(function(){msg("Error de conexión.",false);b.disabled=false;});});k.addEventListener("click",function(){msg("Confirmando…",true);fetch("/api/v1/public/affiliates/payout/confirm",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({code:c.value.trim()})}).then(function(r){return r.json();}).then(function(j){msg(j.message||(j.ok?"Listo":"Error"),j.ok);if(j.ok)w.style.display="none";}).catch(function(){msg("Error de conexión.",false);});});})();</script>';
        echo '<div class="ahp-sec" data-tab="ganancias"><h2>Mis Ganancias — estados exactos</h2><table><thead><tr><th>Estado</th><th>Movimientos</th><th class="n">Monto</th></tr></thead><tbody>';
        if (empty($__d['by_status'])) echo '<tr><td colspan="3" style="color:#94a3b8">Aún no hay comisiones. Comparte tu enlace para empezar.</td></tr>';
        foreach ($__states as $__codeS => $__lblS) {
            if (!isset($__d['by_status'][$__codeS])) continue;
            $__b = $__d['by_status'][$__codeS];
            echo '<tr><td><span class="st ' . sonika_esc($__codeS) . '">' . sonika_esc($__lblS) . '</span></td><td>' . (int)$__b['count'] . '</td><td class="n">' . $__money($__b['amount']) . '</td></tr>';
        }
        echo '</tbody></table><p class="note">Una comisión <b>estimada</b> no es un ingreso garantizado; requiere validación y aprobación. Modo simulación.</p></div>';
        $__ss = $__svcP->salesStats((int)$__affPP['id']);
        echo '<div class="ahp-sec" data-tab="ventas"><h2>Estadísticas de ventas</h2>';
        echo '<div class="ahp-cards" style="margin-bottom:16px"><div class="ahp-c"><small>Conversión (clics → pedidos)</small><b>' . $__ss['conversion'] . '%</b></div><div class="ahp-c"><small>Ticket promedio</small><b>' . $__money($__ss['avg_ticket']) . '</b></div><div class="ahp-c"><small>Pedidos atribuidos</small><b>' . (int)$__ss['orders'] . '</b></div><div class="ahp-c"><small>Clicks</small><b>' . (int)$__ss['clicks'] . '</b></div></div>';
        if (!empty($__ss['top_sold'])) {
            $__best = $__ss['top_sold'][0];
            echo '<div style="background:linear-gradient(135deg,#fff,#f6fbf9);border:1px solid #e9efec;border-radius:14px;padding:14px;margin-bottom:14px"><small style="font-size:12px;color:#64748b">⭐ Producto más vendido</small><div style="font-weight:800;color:#101d3a;margin-top:4px">' . sonika_esc((string)$__best['name']) . '</div><div style="font-size:12px;color:#0d7a58;font-weight:700">' . (int)$__best['qty'] . ' unidades · $ ' . number_format((float)$__best['amt'], 0, ',', '.') . '</div></div>';
            echo '<table><thead><tr><th>Producto</th><th>Unidades</th><th>Ingresos</th></tr></thead><tbody>';
            foreach (array_slice($__ss['top_sold'], 0, 8) as $__ps2) echo '<tr><td>' . sonika_esc((string)$__ps2['name']) . '</td><td>' . (int)$__ps2['qty'] . '</td><td>$ ' . number_format((float)$__ps2['amt'], 0, ',', '.') . '</td></tr>';
            echo '</tbody></table>';
        } else {
            echo '<div style="background:#fff;border:1px dashed #dbe7e1;border-radius:12px;padding:16px;color:#94a3b8;font-size:13px">Aún sin ventas atribuidas para mostrar productos.</div>';
        }
        echo '<h2 style="font-size:15px;margin:18px 0 8px">Productos que visitaron tus seguidores</h2>';
        if (!empty($__ss['top_viewed'])) {
            echo '<ul style="margin:0;padding-left:18px;line-height:1.9;font-size:13.5px;color:#334155">';
            foreach ($__ss['top_viewed'] as $__pv) {
                $__pvUrl = (string)$__pv['page_url'];
                $__pvLbl = $__pvUrl;
                if (preg_match('~^/producto/([^/?#]+)~', $__pvUrl, $__mm)) {
                    $__pvP = sonika_find(urldecode($__mm[1]));
                    if ($__pvP) $__pvLbl = (string)($__pvP['title'] ?? $__pvUrl);
                }
                echo '<li>' . sonika_esc($__pvLbl) . ' <b>(' . (int)$__pv['v'] . ')</b></li>';
            }
            echo '</ul>';
        } else {
            echo '<div style="color:#94a3b8;font-size:13px">Aún sin visitas a productos de tus seguidores.</div>';
        }
        echo '</div>';
        echo '<div class="ahp-sec" data-tab="ventas"><h2>Pedidos atribuidos</h2><table><thead><tr><th>Pedido</th><th>Base</th><th>Tasa</th><th>Comisión</th><th>Estado</th><th>Fecha</th></tr></thead><tbody>';
        if (empty($__d['recent'])) echo '<tr><td colspan="6" style="color:#94a3b8">Sin pedidos atribuidos todavía.</td></tr>';
        foreach ($__d['recent'] as $__r) {
            echo '<tr><td>' . sonika_esc((string)$__r['order_ref']) . '</td><td class="n">' . $__money($__r['base_amount']) . '</td><td class="n">' . number_format((float)$__r['tier_rate'] * 100, 2, ',', '.') . '%</td><td class="n">' . $__money($__r['amount']) . '</td><td><span class="st ' . sonika_esc((string)$__r['status']) . '">' . sonika_esc($__states[$__r['status']] ?? $__r['status']) . '</span></td><td>' . sonika_esc(substr((string)$__r['created_at'], 0, 10)) . '</td></tr>';
        }
        echo '</tbody></table></div>';
        echo '<div class="link" data-tab="tienda"><span>Tu enlace: </span><code>' . sonika_esc($__affUrlP) . '</code></div>';
        echo '<script>(function(){var bar=document.querySelector(".ahp-tabs");if(!bar)return;var tabs=bar.querySelectorAll(".ahp-tab"),secs=document.querySelectorAll(".ahp [data-tab]");function go(t){tabs.forEach(function(b){b.classList.toggle("on",b.getAttribute("data-tab")===t)});secs.forEach(function(s){if(s.classList.contains("ahp-tab"))return;s.hidden=(s.getAttribute("data-tab")!==t)});}tabs.forEach(function(b){b.addEventListener("click",function(){go(b.getAttribute("data-tab"))})});var init="ventas";try{init=new URLSearchParams(location.search).get("tab")||"ventas";}catch(e){}go(init);})();</script>';
        echo '</section>';
        if (empty($_SESSION['ff_started_at'])) $_SESSION['ff_started_at'] = time();
        echo '<style>.ahp-exp{position:fixed;right:18px;bottom:18px;z-index:9998;max-width:380px;font-family:Sora,system-ui,sans-serif}.ahp-exp[hidden]{display:none}.ahp-exp__card{display:flex;gap:12px;background:rgba(12,18,34,.97);border:1px solid rgba(124,92,255,.38);border-radius:14px;padding:14px 15px;color:#e8eeff;box-shadow:0 20px 50px rgba(0,0,0,.45);backdrop-filter:blur(8px);animation:ahpExpIn .28s ease}@keyframes ahpExpIn{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}.ahp-exp__ico{font-size:17px;line-height:1.2}.ahp-exp__body{flex:1;min-width:0}.ahp-exp__body b{display:block;font-size:13px;margin-bottom:2px}.ahp-exp__body p{margin:0 0 10px;font-size:11.5px;color:#a9b8d8}.ahp-exp__body strong{color:#ffd166}.ahp-exp__btns{display:flex;gap:8px;justify-content:flex-end}.ahp-exp__btns button{border:0;border-radius:9px;padding:7px 13px;font:inherit;font-size:11.5px;font-weight:800;cursor:pointer;transition:background .2s,transform .2s}.ahp-exp__btns button:hover{transform:translateY(-1px)}.ahp-exp__out{background:#e5484d;color:#fff}.ahp-exp__out:hover{background:#c93a3f}.ahp-exp__keep{background:#12b76a;color:#fff}.ahp-exp__keep:hover{background:#0d9f5b}@media(max-width:520px){.ahp-exp{left:14px;right:14px;bottom:14px;max-width:none}}</style>';
        echo '<div class="ahp-exp" id="ahp-exp" hidden role="dialog" aria-modal="true" aria-labelledby="ahp-exp-t"><div class="ahp-exp__card"><div class="ahp-exp__ico">⏳</div><div class="ahp-exp__body"><b id="ahp-exp-t">Tu sesión está próxima a expirar</b><p>Expira en <strong id="ahp-exp-m">2</strong> minuto(s).</p><div class="ahp-exp__btns"><button type="button" class="ahp-exp__out" id="ahp-exp-out">Cerrar sesión</button><button type="button" class="ahp-exp__keep" id="ahp-exp-keep">Continuar</button></div></div></div></div>';
        echo '<script>(function(){var box=document.getElementById("ahp-exp");if(!box)return;var m=document.getElementById("ahp-exp-m"),out=document.getElementById("ahp-exp-out"),keep=document.getElementById("ahp-exp-keep");function show(v){m.textContent=Math.max(1,Math.ceil(v/60));box.hidden=false;}function hide(){box.hidden=true;}function poll(){if(!document.body.contains(box))return;fetch("/api/v1/public/affiliates/session",{cache:"no-store"}).then(function(r){return r.json();}).then(function(j){if(!j||!j.ok){hide();return;}var s=Number(j.expires_in)||0;if(s<=120)show(s);else hide();}).catch(function(){});}if(out)out.addEventListener("click",function(){out.disabled=true;fetch("/api/v1/public/affiliates/logout",{method:"POST",headers:{"Content-Type":"application/json"},body:"{}"}).then(function(){location.href="/mi-antro";},function(){location.href="/mi-antro";});});if(keep)keep.addEventListener("click",function(){keep.disabled=true;fetch("/api/v1/public/affiliates/session/extend",{method:"POST",headers:{"Content-Type":"application/json"},body:"{}"}).then(function(r){return r.json();}).then(function(){keep.disabled=false;hide();}).catch(function(){keep.disabled=false;hide();});});poll();setInterval(poll,30000);})();</script>';
        sonika_site_footer();
        return true;
    }
}

// SONIKA Artist Hub — página pública del artista (/artista/{slug} y vanity /{slug})
$__artSlug = ($segments[0] ?? '') === 'artista' ? (string)($segments[1] ?? '') : (count($segments) === 1 ? (string)$segments[0] : '');
$__res2 = ['producto','buscar','tienda','carrito','checkout','cuenta','categorias','marcas','contacto','soporte','ofertas','politicas','r','artista'];
if ($__artSlug !== '' && !in_array($__artSlug, $__res2, true) && !(function_exists('sonika_category') && sonika_category($__artSlug))) {
    $__affSvc = new \App\Services\AffiliateService();
    $__affP = $__affSvc->getBySlug($__artSlug);
    if ($__affP) {
        $__name = trim(($__affP['artist_name'] ?: ($__affP['public_name'] ?: $__affP['full_name'])));
        $__store = [];
        try { $st = \App\Core\Database::instance()->prepare('SELECT * FROM affiliate_stores WHERE affiliate_id = :a AND site_id = @site_id LIMIT 1'); $st->execute(['a' => (int)$__affP['id']]); $__store = $st->fetch() ?: []; } catch (\Throwable $e) {}
        $__bio = (string)($__store['bio'] ?? '');
        $__img = (string)($__store['image'] ?? '');
        try { $__mHP = $__affSvc->mediaFor((int)$__affP['id']); if (!empty($__mHP['cover'])) $__img = (string)$__mHP['cover']; } catch (\Throwable $e) {}
        $__code = $__affSvc->ensureLink((int)$__affP['id']);
        $__origin = ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'sonika.com.co');
        $__affUrl = $__origin . '/r/' . $__code;
        $__instr = (string)($__affP['music_instrument'] ?? '');
        $__genre = (string)($__affP['music_genre'] ?? '');
        $__level = (string)($__affP['music_level'] ?? '');
        $__seoBio = '';
        try { $__cvSeo = $__affSvc->cvFor((int)$__affP['id']); if ($__cvSeo) $__seoBio = (string)($__cvSeo['bio'] ?? ''); } catch (\Throwable $e) {}
        if (trim($__seoBio) === '') $__seoBio = $__bio;
        $__seoDesc = trim((string)preg_replace('/\s+/', ' ', strip_tags($__seoBio)));
        if ($__seoDesc !== '') $__seoDesc = function_exists('mb_substr') ? mb_substr($__seoDesc, 0, 160) : substr($__seoDesc, 0, 160);
        sonika_site_head($__name . ' — SONIKA ARTIST', $__seoDesc !== '' ? $__seoDesc : ('Descubre la selección musical de ' . $__name . ' en SONIKA.'));
        echo '<style>.ah{--ah1:#F20D16;--ah2:#101d3a}.ah-hero{position:relative;overflow:hidden;background:radial-gradient(900px 400px at 80% -10%,#ffe9e9,transparent 60%),linear-gradient(135deg,#101d3a,#2a3550);color:#fff;padding:56px 0 40px}.ah-hero__in{max-width:1180px;margin:0 auto;padding:0 22px;display:flex;gap:24px;align-items:center;flex-wrap:wrap}.ah-ava{width:104px;height:104px;border-radius:24px;background:linear-gradient(135deg,#F20D16,#8b0a10);display:grid;place-items:center;font:800 40px/1 Sora,sans-serif;overflow:hidden;flex:none}.ah-ava img{width:100%;height:100%;object-fit:cover}.ah-kick{font-size:11px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#ffb3b7}.ah-name{font:800 clamp(1.8rem,4vw,2.8rem)/1.05 Sora,sans-serif;margin:4px 0 6px}.ah-meta{color:#c9d2e0;font-size:14px}.ah-chips{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px}.ah-chip{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.18);border-radius:999px;padding:4px 11px;font-size:12px;font-weight:600}.ah-share{margin-left:auto;display:flex;gap:8px;flex-wrap:wrap}.ah-btn{display:inline-flex;align-items:center;gap:8px;background:var(--ah1);color:#fff;border:0;border-radius:12px;padding:11px 16px;font-weight:800;font-size:13.5px;cursor:pointer;text-decoration:none}.ah-btn.ghost{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.22)}.ah-sec{max-width:1180px;margin:0 auto;padding:34px 22px}.ah-h{font:800 22px Sora,sans-serif;color:var(--ah2);margin:0 0 16px}.ah-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}.ah-empty{color:#78849a;background:#f6f9f7;border:1px dashed #dbe7e1;border-radius:14px;padding:22px;text-align:center;font-size:14px}.ah-cta{max-width:1180px;margin:0 auto;padding:0 22px 50px}.ah-cta__box{background:linear-gradient(135deg,#fff,#f6fbf9);border:1px solid #e9efec;border-radius:20px;padding:28px;display:flex;gap:18px;align-items:center;flex-wrap:wrap}.ah-cta__box h3{font:800 22px Sora,sans-serif;color:var(--ah2);margin:0 0 6px}.ah-cta__box p{margin:0;color:#51607a;font-size:14px}.ah-tabs{max-width:1180px;margin:20px auto 0;padding:0 22px;display:grid;grid-template-columns:1fr 1fr;gap:10px}.ah-tab{display:block;text-align:center;padding:14px;border-radius:14px;border:1px solid #e6eeea;background:#fff;color:#334155;font-weight:800;font-size:15px;text-decoration:none;transition:.15s}.ah-tab.on{background:#101d3a;color:#fff;border-color:#101d3a}.ah-tab:hover{background:#f6fbf9}@media(max-width:900px){.ah-grid{grid-template-columns:repeat(2,1fr)}}@media(max-width:560px){.ah-grid{grid-template-columns:1fr}.ah-share{margin-left:0;width:100%}}</style>';
        echo '<div class="sonika-artist-page"><style>.sonika-artist-page{background:radial-gradient(ellipse at 75% 5%,rgba(49,75,150,.16),transparent 42%),linear-gradient(180deg,#05080d,#080d15 48%,#05080d);color:#f5f7fb;padding-bottom:50px}.sonika-artist-page .ah-hero{background-color:#080b12 !important;border-radius:0 0 18px 18px;padding:0;position:relative;isolation:isolate;overflow:hidden}.sonika-artist-page .ah-hero::before{content:"";position:absolute;inset:0;z-index:0;pointer-events:none;background:linear-gradient(0deg,rgba(3,5,10,.9),rgba(3,5,10,.15) 60%)}.sonika-artist-page .ah-hero__in{position:relative;z-index:1;display:flex;gap:22px;align-items:flex-end;flex-wrap:wrap;padding:110px 22px 22px;max-width:1180px;margin:0 auto}.sonika-artist-page .ah-ava{width:118px;height:118px;border-radius:50%;border:3px solid rgba(255,255,255,.85);position:relative}.sonika-artist-page .ah-verified{position:absolute;right:6px;bottom:6px;width:26px;height:26px;border-radius:50%;background:#1d9bf0;color:#fff;display:grid;place-items:center;font-size:14px;font-weight:800;border:2px solid #05080d}.sonika-artist-page .ah-hero__body{flex:1;min-width:260px}.sonika-artist-page .ah-hero__titlerow{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.sonika-artist-page .ah-badge2{display:inline-block;background:linear-gradient(135deg,#f7d488,#e0a438);color:#3a2a08;font-weight:800;font-size:11px;padding:4px 10px;border-radius:999px}.sonika-artist-page .ah-spec{font-size:12px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#c9a7ff;margin-top:6px}.sonika-artist-page .ah-quote{font-style:italic;color:#dbe3ef;font-size:15px;line-height:1.5;margin:10px 0 0;max-width:560px}.sonika-artist-page .ah-social{display:flex;gap:8px;margin-top:12px}.sonika-artist-page .ah-social a{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.1);border:1px solid rgba(174,194,222,.24);display:grid;place-items:center;color:#fff;font-size:12px;font-weight:800;text-decoration:none}.sonika-artist-page .ah-actions{display:flex;gap:10px;margin-left:auto;flex-wrap:wrap}.sonika-artist-page .ah-anchors{display:flex;gap:6px;overflow-x:auto;max-width:1180px;margin:8px auto 0;padding:0 22px;scrollbar-width:none}.sonika-artist-page .ah-anchors::-webkit-scrollbar{display:none}.sonika-artist-page .ah-anchors a{white-space:nowrap;padding:9px 14px;border-radius:10px;font-size:13px;font-weight:700;color:#b5c0d0;text-decoration:none}.sonika-artist-page .ah-anchors a.on,.sonika-artist-page .ah-anchors a:hover{background:linear-gradient(135deg,#287bff,#6246ff);color:#fff}.sonika-artist-page .ah-kick{color:#c9a7ff}.sonika-artist-page .ah-name{color:#fff;text-shadow:0 2px 18px rgba(0,0,0,.5)}.sonika-artist-page .ah-meta{color:#b5c0d0}.sonika-artist-page .ah-chip{background:rgba(98,70,255,.22) !important;border-color:rgba(174,194,222,.24) !important;color:#eef1f8}.sonika-artist-page .ah-btn{background:linear-gradient(135deg,#287bff,#6246ff) !important;box-shadow:0 8px 22px rgba(98,70,255,.35)}.sonika-artist-page .ah-btn.ghost{background:rgba(255,255,255,.08) !important;border:1px solid rgba(174,194,222,.24) !important}.sonika-artist-page .ah-ava{background:linear-gradient(135deg,#287bff,#6246ff);border:2px solid rgba(255,255,255,.55)}.sonika-artist-page .ah-tabs{max-width:1180px;margin:16px auto 6px;padding:0 22px}.sonika-artist-page .ah-tab{background:rgba(10,16,25,.9);border-color:rgba(174,194,222,.2);color:#b5c0d0}.sonika-artist-page .ah-tab.on{background:linear-gradient(135deg,#287bff,#6246ff);color:#fff;border-color:transparent}.sonika-artist-page .ah-sec{color:#f5f7fb}.sonika-artist-page .ah-sec h2,.sonika-artist-page .ah-cta__box h3{color:#fff}.sonika-artist-page .ah-sec p,.sonika-artist-page .ah-sec td,.sonika-artist-page .ah-sec li,.sonika-artist-page .ah-cta__box p{color:#b5c0d0}.sonika-artist-page .ah-sec article{background:#0a1019 !important;border-color:rgba(174,194,222,.18) !important;color:#e8ecf5}.sonika-artist-page .ah-cta__box{background:rgba(10,16,25,.9) !important;border-color:rgba(174,194,222,.2) !important}.sonika-artist-page .ah-cta__box input,.sonika-artist-page .ah-cta__box select,.sonika-artist-page .ah-cta__box textarea{background:rgba(5,8,13,.85) !important;border-color:rgba(174,194,222,.22) !important;color:#f5f7fb !important}.sonika-artist-page .ah-cta__box button{background:linear-gradient(135deg,#287bff,#6246ff) !important;color:#fff}.sonika-artist-page .ah-grid a,.sonika-artist-page .ah-grid .card,.sonika-artist-page .ah-grid .prod-card{background:#0a1019;border-color:rgba(174,194,222,.18);color:#f5f7fb}.sonika-artist-page .ah-grid .price{color:#d9c2ff}.sonika-artist-page .ah-shop{background:#fff;color:#17212b;border-radius:0;padding:34px 0;margin:0;width:100%;max-width:100%;box-sizing:border-box}.sonika-artist-page .ah-shop>h2,.sonika-artist-page .ah-shop>h3,.sonika-artist-page .ah-shop>div,.sonika-artist-page .ah-shop>p{max-width:1180px;margin-left:auto;margin-right:auto;padding-left:22px;padding-right:22px;width:100%;box-sizing:border-box}.sonika-artist-page .ah-shop .ah-h{color:#101d3a}.sonika-artist-page .ah-shop>h3{margin-left:auto!important;margin-right:auto!important}.sonika-artist-page .ah-shop>p{margin-left:auto!important;margin-right:auto!important;text-align:center}.sonika-artist-page .ah-shop p,.sonika-artist-page .ah-shop a{color:#17212b}.sonika-artist-page .ah-shop .ah-grid a,.sonika-artist-page .ah-shop .ah-grid .card,.sonika-artist-page .ah-shop .ah-grid .prod-card{background:#fff !important;border-color:#e6eeea !important;color:#17212b !important}.sonika-artist-page .ah-shop .ah-grid .price{color:#F20D16}.sonika-artist-page .ah-shop .ah-grid .cta,.sonika-artist-page .ah-shop .ah-grid button{background:linear-gradient(135deg,#F20D16,#C70A12)}</style>';
        $__ava = (string)($__mHP['avatar'] ?? '');
        if ($__ava === '') $__ava = $__img;
        echo '<section class="ah ah-hero" id="top"' . ($__img !== '' ? ' style="background-image:linear-gradient(0deg,rgba(3,5,10,.85),rgba(3,5,10,.3)),url(' . sonika_esc($__img) . ');background-size:cover;background-position:center 30%"' : '') . '><div class="ah-hero__in"><div class="ah-ava' . ($__ava !== '' ? ' ah-ava--zoom' : '') . '"' . ($__ava !== '' ? ' data-zoom="' . sonika_esc($__ava) . '" role="button" tabindex="0" aria-label="Ampliar foto de perfil"' : '') . '>' . ($__ava !== '' ? '<img src="' . sonika_esc($__ava) . '" alt="">' : sonika_esc(mb_substr($__name, 0, 1))) . '<span class="ah-verified">✓</span></div><div class="ah-hero__body"><div class="ah-hero__titlerow"><h1 class="ah-name">' . sonika_esc($__name) . '</h1><span class="ah-badge2">👑 Artista SONIKA</span></div>';
        if ($__instr !== '' || $__genre !== '') echo '<div class="ah-spec">' . sonika_esc(mb_strtoupper(trim($__instr . ($__genre ? ' · ' . $__genre : '')))) . '</div>';
        if ($__bio !== '') echo '<p class="ah-quote">“' . sonika_esc($__bio) . '”</p>';
        echo '<div class="ah-chips">';
        foreach ([$__genre, $__level] as $__ch) { if ($__ch !== '') echo '<span class="ah-chip">' . sonika_esc($__ch) . '</span>'; }
        echo '</div><div class="ah-social">';
        if (!empty($__mHP['instagram'])) echo '<a href="https://instagram.com/' . sonika_esc((string)$__mHP['instagram']) . '" target="_blank" rel="noopener" aria-label="Instagram">IG</a>';
        if (!empty($__mHP['video_url'])) echo '<a href="' . sonika_esc((string)$__mHP['video_url']) . '" target="_blank" rel="noopener" aria-label="YouTube">▶</a>';
        if (!empty($__mHP['tiktok'])) echo '<a href="' . sonika_esc((string)$__mHP['tiktok']) . '" target="_blank" rel="noopener" aria-label="TikTok">♪</a>';
        echo '</div></div><div class="ah-actions"><a class="ah-btn" href="#contratame">Contratar</a><a class="ah-btn ghost" target="_blank" rel="noopener" href="https://wa.me/?text=' . rawurlencode('Escucha y apoya a ' . $__name . ' en SONIKA: ' . $__affUrl) . '">Compartir</a></div></div></section>';
        $__mediaP = $__affSvc->mediaFor((int)$__affP['id']);
        $__galP = json_decode((string)($__mediaP['gallery'] ?? '[]'), true);
        if (!is_array($__galP)) $__galP = [];
        $__cvP = $__affSvc->cvFor((int)$__affP['id']);
        if (!is_array($__cvP)) $__cvP = [];
        $__trBands = json_decode((string)($__cvP['bands'] ?? '[]'), true);
        if (!is_array($__trBands)) $__trBands = [];
        $__trAch = json_decode((string)($__cvP['achievements'] ?? '[]'), true);
        if (!is_array($__trAch)) $__trAch = [];
        $__trBio = trim((string)($__cvP['bio'] ?? ''));
        if ($__trBio === '') $__trBio = trim($__bio);
        $__trYears = trim((string)($__cvP['experience_years'] ?? ''));
        $__stageImgs = array_values(array_unique(array_filter(array_merge([$__img], $__galP))));
        $__igV = trim((string)($__mediaP['instagram'] ?? ''));
        $__igUrl = $__igV === '' ? '' : (preg_match('~^https?://~i', $__igV) ? $__igV : 'https://instagram.com/' . ltrim($__igV, '@'));
        $__ttV = trim((string)($__mediaP['tiktok'] ?? ''));
        $__ttUrl = $__ttV === '' ? '' : (preg_match('~^https?://~i', $__ttV) ? $__ttV : 'https://tiktok.com/@' . ltrim($__ttV, '@'));
        $__ytUrl = trim((string)($__mediaP['video_url'] ?? ''));
        $__ytId = ($__ytUrl !== '' && preg_match('~(?:youtu\.be/|v=|embed/|shorts/)([A-Za-z0-9_-]{11})~', $__ytUrl, $__ym)) ? $__ym[1] : '';
        $__slideCount = count($__stageImgs) + ($__ytId !== '' ? 1 : 0);
        $__msCss2 = <<<'CSS'
.sonika-artist-page .ah-showcase{padding-top:10px}
.sonika-artist-page .ah-showcase__grid{display:grid;grid-template-columns:minmax(0,440px) minmax(0,1fr);gap:26px;max-width:1040px;margin:0 auto;align-items:stretch}
@media(max-width:900px){.sonika-artist-page .ah-showcase__grid{grid-template-columns:1fr}}
.sonika-artist-page .ah-stage{position:relative;display:flex;flex-direction:column}
.sonika-artist-page .ah-stage__frame{position:relative;width:100%;aspect-ratio:1/1;height:auto;margin:0;border-radius:16px;overflow:hidden;background:linear-gradient(160deg,#0b1226,#131a33);border:1px solid rgba(124,92,255,.3);box-shadow:0 18px 44px rgba(0,0,0,.45)}
.sonika-artist-page .ah-stage__img{width:100%;height:100%;object-fit:cover;display:block;transition:opacity .22s ease,transform .5s ease}
.sonika-artist-page .ah-stage__img.is-fade{opacity:0;transform:scale(1.03)}
.sonika-artist-page .ah-stage__frame.is-video{width:100%;aspect-ratio:16/9}
.sonika-artist-page .ah-stage__video{position:absolute;inset:0;z-index:2;background:#000}
.sonika-artist-page .ah-stage__video iframe{width:100%;height:100%;border:0;display:block}
.sonika-artist-page .ah-thumb.is-video img{opacity:.82}
.sonika-artist-page .ah-thumb.is-video:after{content:"▶";position:absolute;inset:0;display:grid;place-items:center;background:rgba(0,0,0,.3);color:#fff;font-size:18px;text-shadow:0 2px 8px rgba(0,0,0,.65)}
.sonika-artist-page .ah-play{position:absolute;left:50%;top:50%;transform:translate(-50%,-50%);z-index:3;width:62px;height:62px;border-radius:50%;border:0;background:rgba(255,0,51,.92);color:#fff;cursor:pointer;display:grid;place-items:center;box-shadow:0 12px 30px rgba(255,0,51,.45);transition:transform .2s,box-shadow .2s}
.sonika-artist-page .ah-play:hover{transform:translate(-50%,-50%) scale(1.08);box-shadow:0 16px 38px rgba(255,0,51,.6)}
.sonika-artist-page .ah-play svg{width:26px;height:26px;margin-left:3px}
.sonika-artist-page .ah-stage__empty{position:absolute;inset:0;display:grid;place-items:center;text-align:center;color:#8ea0c9;font-size:13px;padding:20px}
.sonika-artist-page .ah-stage__empty b{display:block;font-size:32px;margin-bottom:8px;filter:drop-shadow(0 6px 16px rgba(124,92,255,.5))}
.sonika-artist-page .ah-nav{position:absolute;top:50%;transform:translateY(-50%);z-index:4;width:46px;height:46px;border-radius:50%;border:1px solid rgba(255,255,255,.22);background:rgba(8,12,24,.55);backdrop-filter:blur(10px);color:#fff;display:grid;place-items:center;cursor:pointer;transition:transform .2s,background .2s,box-shadow .2s,border-color .2s;padding:0}
.sonika-artist-page .ah-nav svg{width:20px;height:20px}
.sonika-artist-page .ah-nav.prev{left:14px}
.sonika-artist-page .ah-nav.next{right:14px}
.sonika-artist-page .ah-nav:hover{background:linear-gradient(135deg,#287bff,#7c5cff);border-color:transparent;transform:translateY(-50%) scale(1.09);box-shadow:0 12px 30px rgba(124,92,255,.6)}
.sonika-artist-page .ah-nav:active{transform:translateY(-50%) scale(.96)}
.sonika-artist-page .ah-stage__count{position:absolute;left:14px;bottom:14px;z-index:3;padding:4px 10px;border-radius:999px;background:rgba(6,10,24,.66);border:1px solid rgba(255,255,255,.18);font-size:11px;font-weight:800;color:#dbe6ff;letter-spacing:.04em}
.sonika-artist-page .ah-thumbs{display:flex;justify-content:center;gap:10px;margin-top:12px;overflow-x:auto;padding:4px 2px 6px;scrollbar-width:none}
.sonika-artist-page .ah-thumbs::-webkit-scrollbar{display:none}
.sonika-artist-page .ah-thumb{flex:0 0 68px;height:48px;border-radius:9px;overflow:hidden;border:2px solid transparent;background:#0b1120;padding:0;cursor:pointer;opacity:.6;transition:opacity .2s,transform .2s,border-color .2s,box-shadow .2s;position:relative}
.sonika-artist-page .ah-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.sonika-artist-page .ah-thumb:hover{opacity:1;transform:translateY(-2px)}
.sonika-artist-page .ah-thumb.on{opacity:1;border-color:#7c5cff;box-shadow:0 0 0 3px rgba(124,92,255,.28)}
.sonika-artist-page .ah-side{position:relative;background:linear-gradient(180deg,rgba(255,255,255,.05),rgba(255,255,255,.015));border:1px solid rgba(255,255,255,.1);border-radius:16px;padding:15px 17px;display:flex;flex-direction:column}
.sonika-artist-page .ah-side__h{font:800 17px Sora,sans-serif;color:#fff;margin:0 0 2px;display:flex;align-items:center;gap:8px}
.sonika-artist-page .ah-side__h:before{content:"";width:6px;height:20px;border-radius:3px;background:linear-gradient(180deg,#7c5cff,#37e8c8)}
.sonika-artist-page .ah-side__kicker{font-size:10px;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:#8ea0c9;margin:0 0 9px}
.sonika-artist-page .ah-side__bio{font-size:12.6px;line-height:1.6;color:#c8d3e6;margin:0}
.sonika-artist-page .ah-rich{max-height:186px;overflow:auto;padding-right:4px}
.sonika-artist-page .ah-rich p{margin:0 0 7px}
.sonika-artist-page .ah-rich h2,.sonika-artist-page .ah-rich h3{color:#fff;margin:9px 0 4px;line-height:1.3}
.sonika-artist-page .ah-rich h2{font-size:14px}
.sonika-artist-page .ah-rich h3{font-size:13px}
.sonika-artist-page .ah-rich ul,.sonika-artist-page .ah-rich ol{margin:5px 0 8px;padding-left:17px}
.sonika-artist-page .ah-rich li{margin:2px 0}
.sonika-artist-page .ah-rich strong{color:#fff}
.sonika-artist-page .ah-rich a{color:#8fe9ff}
.sonika-artist-page .ah-tag{display:inline-block;font-size:11px;font-weight:700;color:#c9d6ff;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:999px;padding:3px 10px;margin:0 6px 6px 0;text-decoration:none;transition:.18s}
.sonika-artist-page .ah-tag:hover{background:rgba(124,92,255,.28);border-color:rgba(124,92,255,.6);color:#fff;transform:translateY(-1px)}
.sonika-artist-page .ah-side__stats{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:11px}
.sonika-artist-page .ah-stat{border-left:2px solid rgba(124,92,255,.65);padding:1px 0 1px 9px}
.sonika-artist-page .ah-stat b{display:block;font-size:15px;color:#fff;line-height:1.2}
.sonika-artist-page .ah-stat span{font-size:10px;letter-spacing:.07em;text-transform:uppercase;color:#9fb0d8}
.sonika-artist-page .ah-side__tags{display:flex;gap:7px;flex-wrap:wrap;margin-top:12px}
.sonika-artist-page .ah-side__tags i{font-style:normal;font-size:11px;font-weight:700;color:#c9d6ff;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.14);border-radius:999px;padding:3px 10px}
.sonika-artist-page .ah-side__socials{margin-top:auto;padding-top:12px}
.sonika-artist-page .ah-side__socials small{display:block;font-size:10.5px;letter-spacing:.11em;text-transform:uppercase;color:#8ea0c9;margin-bottom:9px}
.sonika-artist-page .ah-socrow{display:flex;gap:10px;flex-wrap:wrap}
.sonika-artist-page .ah-soc{display:inline-flex;align-items:center;gap:8px;padding:9px 14px;border-radius:11px;border:1px solid rgba(255,255,255,.18);background:rgba(255,255,255,.06);color:#e9eefc;text-decoration:none;font-weight:800;font-size:12.5px;transition:transform .2s,background .2s,border-color .2s,box-shadow .2s}
.sonika-artist-page .ah-soc svg{width:16px;height:16px;flex:none}
.sonika-artist-page .ah-soc:hover{transform:translateY(-2px);color:#fff;border-color:transparent}
.sonika-artist-page .ah-soc.ig:hover{background:linear-gradient(135deg,#833ab4,#fd1d1d,#fcb045);box-shadow:0 10px 24px rgba(253,29,29,.4)}
.sonika-artist-page .ah-soc.tt:hover{background:#000;border-color:#25f4ee;box-shadow:0 10px 24px rgba(37,244,238,.3)}
.sonika-artist-page .ah-soc.yt:hover{background:#ff0033;box-shadow:0 10px 24px rgba(255,0,51,.4)}
.sonika-artist-page .ah-ava--zoom{cursor:zoom-in}
.sonika-artist-page .ah-ava--zoom:after{content:"⤢";position:absolute;inset:0;display:grid;place-items:center;border-radius:50%;background:rgba(5,8,13,.5);color:#fff;font-size:22px;opacity:0;transition:opacity .2s;z-index:2}
.sonika-artist-page .ah-ava--zoom:hover:after,.sonika-artist-page .ah-ava--zoom:focus-visible:after{opacity:1}
.sonika-artist-page .ah-zoom{position:fixed;inset:0;z-index:9999;background:rgba(3,5,10,.9);backdrop-filter:blur(10px);display:grid;place-items:center;padding:24px;opacity:0;visibility:hidden;transition:opacity .25s,visibility .25s}
.sonika-artist-page .ah-zoom.on{opacity:1;visibility:visible}
.sonika-artist-page .ah-zoom img{max-width:min(92vw,680px);max-height:86vh;border-radius:20px;border:2px solid rgba(255,255,255,.14);box-shadow:0 40px 90px rgba(0,0,0,.7);transform:scale(.93);transition:transform .3s}
.sonika-artist-page .ah-zoom.on img{transform:scale(1)}
.sonika-artist-page .ah-zoom__x{position:absolute;top:18px;right:18px;width:44px;height:44px;border-radius:50%;border:1px solid rgba(255,255,255,.3);background:rgba(255,255,255,.1);color:#fff;font-size:20px;cursor:pointer;line-height:1}
.sonika-artist-page .ah-zoom__x:hover{background:rgba(255,60,60,.75)}
@media(max-width:640px){.sonika-artist-page .ah-nav{width:40px;height:40px}.sonika-artist-page .ah-thumb{flex-basis:74px;height:56px}}
CSS;
        echo '<style>' . $__msCss2 . '</style>';
        echo '<section class="ah-sec ah-showcase" id="galeria"><div class="ah-showcase__grid"><div class="ah-stage" id="ah-stage">';
        echo '<div class="ah-stage__frame" id="ah-frame">';
        if ($__ytId !== '') {
            echo '<img class="ah-stage__img" id="ah-stage-img" src="https://i.ytimg.com/vi/' . sonika_esc($__ytId) . '/hqdefault.jpg" alt="Video de ' . sonika_esc($__name) . '">';
        } elseif ($__stageImgs) {
            echo '<img class="ah-stage__img" id="ah-stage-img" src="' . sonika_esc((string)$__stageImgs[0]) . '" alt="' . sonika_esc($__name) . '">';
        } else {
            echo '<div class="ah-stage__empty"><div><b>🎸</b>Aún sin fotos.<br>Sube tu portada y galería desde Mi Antro.</div></div>';
        }
        echo '<div class="ah-stage__video" id="ah-stage-video" hidden></div>';
        if ($__ytId !== '') {
            echo '<button type="button" class="ah-play" id="ah-play" data-video="' . sonika_esc($__ytId) . '" aria-label="Reproducir video"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg></button>';
        }
        if ($__slideCount > 1) {
            echo '<button type="button" class="ah-nav prev" aria-label="Anterior"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M15 5l-7 7 7 7"/></svg></button>';
            echo '<button type="button" class="ah-nav next" aria-label="Siguiente"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 5l7 7-7 7"/></svg></button>';
            echo '<div class="ah-stage__count" id="ah-stage-count">1 / ' . $__slideCount . '</div>';
        }
        echo '</div>';
        if ($__slideCount > 1) {
            echo '<div class="ah-thumbs" role="tablist" aria-label="Galería del artista">';
            if ($__ytId !== '') {
                echo '<button type="button" class="ah-thumb is-video on" role="tab" aria-selected="true" aria-label="Video de YouTube" data-video="' . sonika_esc($__ytId) . '"><img src="https://i.ytimg.com/vi/' . sonika_esc($__ytId) . '/mqdefault.jpg" alt="" loading="lazy"></button>';
            }
            foreach ($__stageImgs as $__si => $__sImg) {
                $__on = ($__ytId === '' && $__si === 0);
                echo '<button type="button" class="ah-thumb' . ($__on ? ' on' : '') . '" role="tab" aria-selected="' . ($__on ? 'true' : 'false') . '" aria-label="Foto ' . ($__si + 1) . '" data-src="' . sonika_esc((string)$__sImg) . '"><img src="' . sonika_esc((string)$__sImg) . '" alt="" loading="lazy"></button>';
            }
            echo '</div>';
            echo '<div class="ah-dots" id="ah-dots">';
            for ($__d = 0; $__d < $__slideCount; $__d++) {
                echo '<button type="button" class="ah-dot' . ($__d === 0 ? ' on' : '') . '" aria-label="Ir a la diapositiva ' . ($__d + 1) . '" aria-selected="' . ($__d === 0 ? 'true' : 'false') . '"></button>';
            }
            echo '</div>';
        }
        echo '</div>';
        echo '<aside class="ah-side"><h2 class="ah-side__h">Trayectoria</h2><p class="ah-side__kicker">' . sonika_esc($__name) . ' · Historia musical</p>';
        $__trBioHtml = $__affSvc->cvBioHtml((string)($__cvP['bio'] ?? ''));
        if ($__trBioHtml === '') $__trBioHtml = $__affSvc->cvBioHtml((string)$__bio);
        if ($__trBioHtml === '') $__trBioHtml = '<p>Este artista todavía está escribiendo su historia. Vuelve pronto para conocer más.</p>';
        echo '<div class="ah-side__bio ah-rich">' . $__trBioHtml . '</div>';
        $__stats = [];
        if ($__trYears !== '') $__stats[] = [$__trYears . ' años', 'Experiencia'];
        if ($__trBands) $__stats[] = [count($__trBands) . '', 'Bandas / proyectos'];
        if ($__trAch) $__stats[] = [count($__trAch) . '', 'Logros'];
        if ($__stats) {
            echo '<div class="ah-side__stats">';
            foreach ($__stats as $__s) echo '<div class="ah-stat"><b>' . sonika_esc((string)$__s[0]) . '</b><span>' . sonika_esc((string)$__s[1]) . '</span></div>';
            echo '</div>';
        }
        $__plainTags = [];
        if ($__instr !== '') $__plainTags[] = $__instr;
        if ($__genre !== '') $__plainTags[] = $__genre;
        if ($__level !== '') $__plainTags[] = $__level;
        if ($__plainTags) {
            echo '<div class="ah-side__tags">';
            foreach (array_unique($__plainTags) as $__tg) echo '<i>' . sonika_esc($__tg) . '</i>';
            echo '</div>';
        }
        if ($__trBands) {
            echo '<div class="ah-side__tags" aria-label="Bandas y artistas">';
            foreach ($__trBands as $__tb) {
                echo '<a class="ah-tag" rel="tag" href="/buscar?q=' . rawurlencode((string)$__tb) . '" title="Ver ' . sonika_esc((string)$__tb) . ' en SONIKA">' . sonika_esc((string)$__tb) . '</a>';
            }
            echo '</div>';
        }
        if ($__trAch) echo '<p style="font-size:12px;color:#9fb0d8;line-height:1.6;margin:12px 0 0"><b style="color:#c9d6ff">Logros:</b> ' . sonika_esc(implode(' · ', array_map('strval', $__trAch))) . '</p>';
        if ($__igUrl !== '' || $__ytUrl !== '' || $__ttUrl !== '') {
            echo '<div class="ah-side__socials"><small>Sígueme</small><div class="ah-socrow">';
            if ($__igUrl !== '') echo '<a class="ah-soc ig" href="' . sonika_esc($__igUrl) . '" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1.2" fill="currentColor" stroke="none"/></svg>Instagram</a>';
            if ($__ytUrl !== '') echo '<a class="ah-soc yt" href="' . sonika_esc($__ytUrl) . '" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M23 12s0-3.5-.45-5.2a2.7 2.7 0 0 0-1.9-1.9C18.9 4.5 12 4.5 12 4.5s-6.9 0-8.65.4a2.7 2.7 0 0 0-1.9 1.9C1 8.5 1 12 1 12s0 3.5.45 5.2a2.7 2.7 0 0 0 1.9 1.9c1.75.4 8.65.4 8.65.4s6.9 0 8.65-.4a2.7 2.7 0 0 0 1.9-1.9C23 15.5 23 12 23 12ZM9.75 15.5v-7l6 3.5-6 3.5Z"/></svg>YouTube</a>';
            if ($__ttUrl !== '') echo '<a class="ah-soc tt" href="' . sonika_esc($__ttUrl) . '" target="_blank" rel="noopener"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16.5 3c.4 2.3 1.7 3.8 4 4.1v3.1c-1.5 0-2.9-.5-4-1.3v6.3a5.7 5.7 0 1 1-5.7-5.7c.3 0 .6 0 .9.1v3.2a2.6 2.6 0 1 0 1.8 2.5V3h3Z"/></svg>TikTok</a>';
            echo '</div></div>';
        }
        echo '</aside></div></section>';
        $__ldDesc = trim((string)preg_replace('/\s+/', ' ', strip_tags($__trBioHtml)));
        $__ld = ['@context' => 'https://schema.org', '@type' => 'Person', 'name' => $__name, 'jobTitle' => ($__genre !== '' ? $__genre : 'Músico'), 'description' => (function_exists('mb_substr') ? mb_substr($__ldDesc, 0, 300) : substr($__ldDesc, 0, 300)), 'url' => $__origin . '/artista/' . $__affP['slug']];
        if ($__ava !== '') $__ld['image'] = (preg_match('~^https?://~i', $__ava) ? $__ava : $__origin . $__ava);
        $__ldSame = array_values(array_filter([$__igUrl, $__ytUrl, $__ttUrl]));
        if ($__ldSame) $__ld['sameAs'] = $__ldSame;
        $__ldKnows = array_values(array_filter(array_merge([$__instr, $__genre], $__trBands)));
        if ($__ldKnows) $__ld['knowsAbout'] = $__ldKnows;
        if ($__trAch) $__ld['award'] = array_values(array_map('strval', $__trAch));
        if ($__trBands) $__ld['memberOf'] = array_map(function ($b) { return ['@type' => 'MusicGroup', 'name' => (string)$b]; }, $__trBands);
        echo '<script type="application/ld+json">' . json_encode($__ld, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . '</script>';
        echo '<div class="ah-stickybar" id="ah-stickybar" hidden><a class="ah-btn" href="#contratame">Contratar</a><a class="ah-btn ghost" target="_blank" rel="noopener" href="' . sonika_esc('https://wa.me/?text=' . rawurlencode('Escucha y apoya a ' . $__name . ' en SONIKA: ' . $__affUrl)) . '">Compartir</a></div>';
        echo '<style>
.sonika-artist-page .ah-dots{display:none;justify-content:center;gap:7px;margin-top:8px}
.sonika-artist-page .ah-dot{width:7px;height:7px;border-radius:999px;border:0;padding:0;background:rgba(255,255,255,.25);cursor:pointer;transition:width .25s,background .25s}
.sonika-artist-page .ah-dot.on{width:20px;background:linear-gradient(90deg,#37e8c8,#7c5cff)}
.sonika-artist-page .ah-stickybar{position:fixed;left:12px;right:12px;bottom:12px;z-index:9990;display:none;gap:8px;padding:8px;border-radius:14px;background:rgba(8,12,24,.88);border:1px solid rgba(255,255,255,.14);backdrop-filter:blur(12px);box-shadow:0 16px 40px rgba(0,0,0,.45)}
.sonika-artist-page .ah-stickybar .ah-btn{flex:1;justify-content:center;min-height:44px}
.sonika-artist-page [id]{scroll-margin-top:72px}
.sonika-artist-page .ah-btn:active,.sonika-artist-page .ah-soc:active,.sonika-artist-page .ah-zoom__x:active{transform:scale(.97)}
.sonika-artist-page a:focus-visible,.sonika-artist-page button:focus-visible{outline:2px solid #37e8c8;outline-offset:2px}
.ahf{opacity:0;transform:translateY(14px);transition:opacity .5s ease,transform .5s ease}
.ahf.in{opacity:1;transform:none}
.ahp input,.ahp textarea,.ahp select{-webkit-appearance:none}
@media(max-width:900px){.sonika-artist-page .ah-showcase{padding-top:6px}}
@media(max-width:720px){
.ahp{padding:18px 14px 44px}
.ahp-h h1{font-size:22px}
.ahp-cards{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}
.ahp-c{padding:12px}
.ahp-tabs{position:sticky;top:0;z-index:6;background:rgba(255,255,255,.94);backdrop-filter:blur(10px);padding:6px 0;margin:0 0 14px;scroll-snap-type:x proximity}
.ahp-tab{min-width:106px;scroll-snap-align:start;min-height:44px}
.ahp input,.ahp textarea,.ahp select{font-size:16px!important}
.ahp table{display:block;overflow-x:auto;white-space:nowrap;border-radius:12px}
.ahp-ms-grid{grid-template-columns:repeat(3,minmax(0,1fr))!important}
.ahp-pub{grid-template-columns:1fr!important}
}
@media(max-width:640px){
.sonika-artist-page .ah-hero__in{padding:84px 18px 18px;gap:16px}
.sonika-artist-page .ah-ava{width:92px;height:92px;border-width:2.5px}
.sonika-artist-page .ah-hero__body{min-width:0}
.sonika-artist-page .ah-name{font-size:clamp(1.5rem,7vw,2rem)}
.sonika-artist-page .ah-actions{width:100%;margin-left:0;gap:8px}
.sonika-artist-page .ah-actions .ah-btn{flex:1;justify-content:center;min-height:44px}
.sonika-artist-page .ah-sec{padding:22px 16px}
.sonika-artist-page .ah-thumbs{justify-content:flex-start;scroll-snap-type:x proximity;-webkit-overflow-scrolling:touch;padding-bottom:8px}
.sonika-artist-page .ah-thumb{scroll-snap-align:center;height:54px;flex-basis:76px}
.sonika-artist-page .ah-nav{width:44px;height:44px}
.sonika-artist-page .ah-side{padding:14px}
.sonika-artist-page .ah-rich{max-height:none;-webkit-overflow-scrolling:touch}
.sonika-artist-page .ah-side__socials{margin-top:14px}
.sonika-artist-page .ah-soc{min-height:44px}
.sonika-artist-page .ah-dots{display:flex}
.sonika-artist-page .ah-stage__count{display:none}
.sonika-artist-page .ah-stickybar:not([hidden]){display:flex}
}
@media(max-width:420px){.ahp-ms-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}}
@media (prefers-reduced-motion: reduce){.ahf{opacity:1;transform:none;transition:none}.sonika-artist-page .ah-dot,.sonika-artist-page .ah-btn,.sonika-artist-page .ah-thumb,.sonika-artist-page .ah-nav{transition:none!important}}
</style>';
        echo '<script>(function(){var bar=document.getElementById("ah-stickybar"),hero=document.querySelector(".ah-actions");if(bar&&hero){var upd=function(){var r=hero.getBoundingClientRect();bar.hidden=(r.bottom>0)||window.scrollY<220;};window.addEventListener("scroll",upd,{passive:true});upd();}var box=document.getElementById("ah-zoom");if(box){var y0=null;box.addEventListener("touchstart",function(e){y0=e.touches[0].clientY;},{passive:true});box.addEventListener("touchend",function(e){if(y0===null)return;var dy=e.changedTouches[0].clientY-y0;if(dy>90){box.classList.remove("on");document.body.style.overflow="";}y0=null;},{passive:true});}var thumbs=[].slice.call(document.querySelectorAll(".ah-thumb")),dots=[].slice.call(document.querySelectorAll(".ah-dot"));if(dots.length&&thumbs.length){var sync=function(){var idx=thumbs.findIndex(function(t){return t.classList.contains("on");});dots.forEach(function(d,k){d.classList.toggle("on",k===idx);d.setAttribute("aria-selected",k===idx?"true":"false");});};thumbs.forEach(function(t){new MutationObserver(sync).observe(t,{attributes:true,attributeFilter:["class"]});});dots.forEach(function(d,k){d.addEventListener("click",function(){if(thumbs[k])thumbs[k].click();});});sync();}var els=[].slice.call(document.querySelectorAll(".sonika-artist-page .ah-showcase,.sonika-artist-page .ah-shop,.sonika-artist-page .ah-sec"));if("IntersectionObserver" in window&&els.length){els.forEach(function(el){el.classList.add("ahf");});var io=new IntersectionObserver(function(es){es.forEach(function(e){if(e.isIntersecting){e.target.classList.add("in");io.unobserve(e.target);}});},{threshold:.05});els.forEach(function(el){io.observe(el);});}else{els.forEach(function(e){e.classList.add("in");});}})();</script>';
        echo '<script>(function(){var st=document.getElementById("ah-stage");if(!st)return;var th=[].slice.call(st.querySelectorAll(".ah-thumb"));var main=document.getElementById("ah-stage-img");var fr=document.getElementById("ah-frame");var vw=document.getElementById("ah-stage-video");var cnt=document.getElementById("ah-stage-count");var play=document.getElementById("ah-play");var i=0,t=null;function stopAuto(){if(t){clearInterval(t);t=null;}}function startAuto(){stopAuto();if(!th.length)return;if(th[i]&&th[i].getAttribute("data-video"))return;t=setInterval(function(){go(i+1);},6000);}function hideVideo(){if(!vw)return;vw.hidden=true;vw.innerHTML="";if(fr)fr.classList.remove("is-video");if(main)main.style.opacity="1";if(play)play.hidden=true;}function showVideo(id,auto){if(!vw)return;if(fr)fr.classList.add("is-video");vw.hidden=false;vw.innerHTML="<iframe loading=\"lazy\" src=\"https://www.youtube-nocookie.com/embed/"+id+"?rel=0&playsinline=1"+(auto?"&autoplay=1":"")+"\" title=\"Video\" allow=\"accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture\" allowfullscreen></iframe>";if(main)main.style.opacity="0";if(play)play.hidden=true;stopAuto();}function go(n){if(!th.length)return;i=(n+th.length)%th.length;var b=th[i];var vid=b.getAttribute("data-video");if(vid){showVideo(vid,true);}else{hideVideo();if(main){main.classList.add("is-fade");setTimeout(function(){main.src=b.getAttribute("data-src");main.classList.remove("is-fade");},150);}}th.forEach(function(x,k){x.classList.toggle("on",k===i);x.setAttribute("aria-selected",k===i?"true":"false");});if(cnt)cnt.textContent=(i+1)+" / "+th.length;if(!vid)startAuto();}var nx=st.querySelector(".ah-nav.next"),pv=st.querySelector(".ah-nav.prev");if(nx)nx.addEventListener("click",function(){go(i+1);});if(pv)pv.addEventListener("click",function(){go(i-1);});th.forEach(function(x,k){x.addEventListener("click",function(){go(k);});});if(play)play.addEventListener("click",function(){showVideo(play.getAttribute("data-video"),true);});st.addEventListener("mouseenter",stopAuto);st.addEventListener("mouseleave",function(){if(!(th[i]&&th[i].getAttribute("data-video")))startAuto();});var x0=null;st.addEventListener("touchstart",function(e){x0=e.touches[0].clientX;},{passive:true});st.addEventListener("touchend",function(e){if(x0===null)return;var dx=e.changedTouches[0].clientX-x0;if(Math.abs(dx)>40){go(dx<0?i+1:i-1);}x0=null;},{passive:true});startAuto();})();</script>';
        echo '<script>(function(){var z=document.getElementById("ah-zoom");if(!z)return;var zi=document.getElementById("ah-zoom-img");function close(){z.classList.remove("on");document.body.style.overflow="";}function open(s){zi.src=s;z.classList.add("on");document.body.style.overflow="hidden";}document.querySelectorAll("[data-zoom]").forEach(function(el){el.addEventListener("click",function(e){e.preventDefault();var s=el.getAttribute("data-zoom");if(s)open(s);});el.addEventListener("keydown",function(e){if(e.key==="Enter"||e.key===" "){e.preventDefault();var s=el.getAttribute("data-zoom");if(s)open(s);}});});z.addEventListener("click",function(e){if(e.target===z||e.target.classList.contains("ah-zoom__x"))close();});document.addEventListener("keydown",function(e){if(e.key==="Escape")close();});})();</script>';
        if ($__ava !== '') echo '<div class="ah-zoom" id="ah-zoom" role="dialog" aria-modal="true" aria-label="Foto de perfil ampliada"><button type="button" class="ah-zoom__x" aria-label="Cerrar">✕</button><img id="ah-zoom-img" alt="' . sonika_esc($__name) . '"></div>';
        $__tab = ((($_GET['tab'] ?? 'vitrina') === 'pagina') ? 'pagina' : 'vitrina');
        echo '<nav class="ah-tabs" aria-label="Secciones del artista"><a class="ah-tab ' . ($__tab === 'vitrina' ? 'on' : '') . '" href="?tab=vitrina">Mi Vitrina</a><a class="ah-tab ' . ($__tab === 'pagina' ? 'on' : '') . '" href="?tab=pagina">Mi Página</a></nav>';
        if ($__tab === 'vitrina') {
        echo '<section class="ah-shop"><h2 class="ah-h">Mi Vitrina</h2>';
        $__cols = $__affSvc->collections((int)$__affP['id']);
        if (!empty($__cols)) {
            foreach ($__cols as $__col) {
                $__handles = json_decode((string)($__col['products'] ?? '[]'), true);
                if (!is_array($__handles) || !$__handles) continue;
                echo '<h3 class="ah-h" style="font-size:17px;margin:22px 0 10px">' . sonika_esc((string)$__col['name']) . '</h3><div class="ah-grid">';
                $__n = 0;
                foreach ($__handles as $__h) {
                    $__p = sonika_find((string)$__h);
                    if ($__p) { sonika_product_card($__p); $__n++; }
                    if ($__n >= 8) break;
                }
                echo '</div>';
            }
        } else {
            $__prods = array_slice(sonika_products(), 0, 8);
            if (!empty($__prods)) { echo '<div class="ah-grid">'; foreach ($__prods as $__p) sonika_product_card($__p); echo '</div>'; }
            else { echo '<div class="ah-empty">La selección de ' . sonika_esc($__name) . ' estará disponible pronto.</div>'; }
        }
        echo '<p style="margin:16px 0 0"><a href="/tienda" style="color:#F20D16;font-weight:700">Ver todo el catálogo →</a></p></section>';
        }
        if ($__tab === 'pagina') {
        $__cv = $__affSvc->cvFor((int)$__affP['id']);
        $__posts = $__affSvc->postsFor((int)$__affP['id']);
        if ($__posts) {
            echo '<section class="ah-sec"><h2 class="ah-h">Muro</h2><div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:14px">';
            foreach ($__posts as $__po) {
                echo '<article style="background:#fff;border:1px solid #eef2f0;border-radius:14px;overflow:hidden">';
                if (!empty($__po['image'])) echo '<img src="' . sonika_esc((string)$__po['image']) . '" alt="" loading="lazy" style="width:100%;height:150px;object-fit:cover;display:block">';
                echo '<div style="padding:12px 14px"><div style="font-size:13.5px;color:#334155;line-height:1.5">' . nl2br(sonika_esc((string)$__po['content'])) . '</div>';
                if (!empty($__po['link'])) echo '<a href="' . sonika_esc((string)$__po['link']) . '" style="display:inline-block;margin-top:8px;color:#F20D16;font-weight:700;font-size:12.5px" target="_blank" rel="noopener">Ver más →</a>';
                echo '<div style="font-size:11px;color:#94a3b8;margin-top:8px">' . sonika_esc(substr((string)$__po['created_at'], 0, 10)) . '</div></div></article>';
            }
            echo '</div></section>';
        }
        echo '<section class="ah-cta" id="contratame"><div class="ah-cta__box"><div style="flex:1;min-width:240px"><h3>Contrátame</h3><p>¿Quieres a ' . sonika_esc($__name) . ' en tu evento o proyecto? Envía una solicitud y coordinamos disponibilidad y condiciones.</p></div><form id="ah-contract" style="display:grid;gap:8px;min-width:280px;flex:1"><input type="hidden" name="artist" value="<?= sonika_esc($__artSlug) ?>"><input name="name" placeholder="Tu nombre" required style="padding:10px;border:1px solid #dbe7e1;border-radius:10px;font:inherit"><input name="email" type="email" placeholder="Correo" required style="padding:10px;border:1px solid #dbe7e1;border-radius:10px;font:inherit"><input name="phone" placeholder="Teléfono" style="padding:10px;border:1px solid #dbe7e1;border-radius:10px;font:inherit"><div style="display:flex;gap:8px"><select name="event_type" style="flex:1;padding:10px;border:1px solid #dbe7e1;border-radius:10px;font:inherit"><option value="">Tipo de evento</option><option>Concierto</option><option>Evento privado</option><option>Sesión/estudio</option><option>Otro</option></select><input name="event_date" type="date" style="flex:1;padding:10px;border:1px solid #dbe7e1;border-radius:10px;font:inherit"></div><input name="place" placeholder="Ciudad / lugar" style="padding:10px;border:1px solid #dbe7e1;border-radius:10px;font:inherit"><textarea name="message" placeholder="Cuéntale del evento" rows="2" style="padding:10px;border:1px solid #dbe7e1;border-radius:10px;font:inherit"></textarea><button class="ah-btn" type="submit">Enviar solicitud</button><div id="ah-contract-msg" style="font-size:12.5px;min-height:16px"></div></form><script>(function(){var f=document.getElementById("ah-contract");if(!f)return;var o=document.getElementById("ah-contract-msg");f.addEventListener("submit",function(e){e.preventDefault();var d={};new FormData(f).forEach(function(v,k){d[k]=v;});o.style.color="#51607a";o.textContent="Enviando…";fetch("/api/v1/public/affiliates/contract",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(d)}).then(function(r){return r.json();}).then(function(j){o.style.color=j.ok?"#0d7a58":"#d64545";o.textContent=j.message||(j.ok?"Enviado":"Error");if(j.ok)f.reset();}).catch(function(){o.style.color="#d64545";o.textContent="Error de conexión.";});});})();</script></div></section>';
        echo '<section class="ah-cta" style="padding-top:0"><div class="ah-cta__box" style="background:linear-gradient(135deg,#fffbeb,#fff)"><div style="flex:1;min-width:240px"><h3>Aporte de agradecimiento</h3><p>Si quieres apoyar a ' . sonika_esc($__name) . ', puedes dejar un aporte voluntario. <b>Es opcional</b> y no otorga beneficios ni derechos adicionales.</p></div><form id="ah-tip" style="display:grid;gap:8px;min-width:280px;flex:1"><input type="hidden" name="artist" value="' . sonika_esc($__artSlug) . '"><input name="name" placeholder="Tu nombre" style="padding:10px;border:1px solid #dbe7e1;border-radius:10px;font:inherit"><input name="email" type="email" placeholder="Correo (opcional)" style="padding:10px;border:1px solid #dbe7e1;border-radius:10px;font:inherit"><input name="amount" type="number" min="1000" step="1000" placeholder="Monto en COP (ej. 10000)" required style="padding:10px;border:1px solid #dbe7e1;border-radius:10px;font:inherit"><input name="message" placeholder="Mensaje (opcional)" style="padding:10px;border:1px solid #dbe7e1;border-radius:10px;font:inherit"><button class="ah-btn" type="submit" style="background:#b7791f">Dejar mi aporte</button><div id="ah-tip-msg" style="font-size:12.5px;min-height:16px"></div></form></div></section><script>(function(){var f=document.getElementById("ah-tip");if(!f)return;var o=document.getElementById("ah-tip-msg");f.addEventListener("submit",function(e){e.preventDefault();var d={};new FormData(f).forEach(function(v,k){d[k]=v;});o.style.color="#51607a";o.textContent="Enviando…";fetch("/api/v1/public/affiliates/tip",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(d)}).then(function(r){return r.json();}).then(function(j){o.style.color=j.ok?"#0d7a58":"#d64545";o.textContent=j.message||(j.ok?"Gracias":"Error");if(j.ok)f.reset();}).catch(function(){o.style.color="#d64545";o.textContent="Error de conexión.";});});})();</script>';
        }
        echo '</div>';
        sonika_site_footer();
        return true;
    }
}

if (($segments[0] ?? '') === 'politicas') {
    $pol = [
        'pagos' => 'Política de pagos en línea',
        'devoluciones' => 'Política de Devoluciones, Retracto y Garantía',
        'envios' => 'Política de Envíos',
        'privacidad' => 'Política de Privacidad y Tratamiento de Datos Personales',
        'terminos' => 'Términos y Condiciones del Servicio',
        'promociones' => 'Información sobre Promociones',
        'pqrs' => 'PQRS — Peticiones, Quejas, Reclamos y Sugerencias',
    ];
    sonika_site_head('Políticas — SONIKA', 'Políticas, términos y condiciones de SONIKA.');
    sonika_breadcrumb(['Políticas' => null]);
    echo '<section class="pad wrap" style="max-width:900px">';
    echo '<div class="head"><h1>Políticas y condiciones</h1></div>';
    echo '<p style="display:inline-block;background:#fff5f5;color:#b00;border:1px solid #f3c2c2;border-radius:8px;padding:6px 12px;font-size:12px;font-weight:700;margin-bottom:18px">BORRADOR — contenido pendiente de revisión legal</p>';
    echo '<p style="color:#777;margin-bottom:10px">Los textos definitivos de cada política serán publicados tras la revisión legal correspondiente. Este es el índice de políticas en preparación.</p>';
    echo '<div style="line-height:1.8">';
    foreach ($pol as $slug => $title) {
        echo '<h2 id="' . $slug . '" style="font-size:18px;margin:28px 0 8px">' . sonika_esc($title) . '</h2>';
                echo $slug === 'pagos'
                    ? '<p style="color:#334155">Consulta la <a href="/politica-de-pagos-en-linea" style="color:#F20D16;font-weight:700">Política de Pagos en Línea completa</a>.</p>'
                    : '<p style="color:#999">Contenido en preparación.</p>';
    }
    echo '</div></section>';
    sonika_site_footer();
    return true;
}

if (($segments[0] ?? '') === 'producto' && !empty($segments[1])) {
    $product = sonika_find($segments[1]);
    if ($product) {
        $cat = null;
        foreach (sonika_categories() as $c) { if (sonika_match($product, $c['kw'])) { $cat = $c; break; } }
        sonika_site_head(($product['title'] ?? 'Producto') . ' — SONIKA', mb_substr(strip_tags($product['body_html'] ?? ''), 0, 155));
        $crumbs = [];
        if ($cat) { $crumbs[$cat['name']] = '/' . $cat['slug']; }
        $crumbs[$product['title'] ?? 'Producto'] = null;
        sonika_breadcrumb($crumbs);
        $img = sonika_img($product, 900);
        $price = sonika_price($product);
        $cmp = sonika_compare($product);
        $avail = sonika_available($product);
        ?>
        <section class="wrap pdp">
          <div><div class="pdp-gallery"><?php if ($img): ?><img src="<?= sonika_esc($img) ?>" alt="<?= sonika_esc($product['title']) ?>" /><?php endif; ?></div></div>
          <div>
            <span class="pdp-vendor"><?= sonika_esc($product['vendor'] ?? '') ?></span>
            <h1><?= sonika_esc($product['title']) ?></h1>
            <div class="pricebox">
              <span class="price"><?= sonika_cop($price) ?></span>
              <?php if ($cmp > $price && $price > 0): ?><span class="cmp"><?= sonika_cop($cmp) ?></span><?php endif; ?>
            </div>
            <div class="meta">
              <span class="chip <?= $avail ? 'ok' : 'no' ?>"><?= $avail ? '● ' . sonika_esc(\App\Core\Lang::get('available', 'Disponible')) : '● ' . sonika_esc(\App\Core\Lang::get('out_of_stock', 'Agotado')) ?></span>
              <?php if (!empty($product['product_type'])): ?><span class="chip"><?= sonika_esc($product['product_type']) ?></span><?php endif; ?>
              <span class="chip">SKU <?= sonika_esc(sonika_variant($product)['sku'] ?? '—') ?></span>
            </div>
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:8px 0 20px">
              <div class="qty">
                <button type="button" onclick="var i=document.getElementById('qty');i.value=Math.max(1,(+i.value)-1)">−</button>
                <input id="qty" type="text" value="1" inputmode="numeric" />
                <button type="button" onclick="var i=document.getElementById('qty');i.value=(+i.value)+1">+</button>
              </div>
              <button class="btn-buy" type="button" data-add="<?= sonika_esc($product['handle']) ?>" data-qty-target="qty" data-title="<?= sonika_esc($product['title']) ?>" data-price="<?= (float)$price ?>" data-img="<?= sonika_esc(sonika_img($product)) ?>"><?= sonika_esc(\App\Core\Lang::get('add_to_cart', 'Agregar al carrito')) ?></button>
              <a class="cta sec" href="/carrito" style="margin:0"><?= sonika_esc(\App\Core\Lang::get('go_to_cart', 'Ir al carrito')) ?></a>
            </div>
            <div class="trust" style="justify-content:flex-start;gap:16px;border:none;padding:0">
              <span>🚚 <b><?= sonika_esc(\App\Core\Lang::get('shipping_national', 'Envío nacional')) ?></b></span><span>🛡️ <b><?= sonika_esc(\App\Core\Lang::get('warranty', 'Garantía')) ?></b></span><span>💳 <b><?= sonika_esc(\App\Core\Lang::get('secure_payment', 'Pago seguro')) ?></b></span>
            </div>
          </div>
        </section>

        <?php if (!empty($product['body_html'])): ?>
        <section class="wrap pdp-desc">
          <h3><?= sonika_esc(\App\Core\Lang::get('product_description', 'Descripción del producto')) ?></h3>
          <div><?= sonika_safe_html((string)$product['body_html']) ?></div>
        </section>
        <?php endif; ?>

        <?= \App\Widgets\WidgetRegistry::render('product-reviews', ['product_ref' => (string)$product['handle']]) ?>

        <?php $rel = sonika_related($product, 4); if (!empty($rel)): ?>
        <section class="pad wrap">
          <div class="head"><h2><?= sonika_esc(\App\Core\Lang::get('related_products', 'Productos relacionados')) ?></h2></div>
          <?php sonika_grid($rel); ?>
        </section>
        <?php endif; ?>
        <?php
        sonika_site_footer();
        return true;
    }
    return false;
}

$cat = sonika_category($path);
if ($cat) {
    $sub = (string)($_GET['sub'] ?? '');
    $qsub = $sub !== '' ? '&sub=' . rawurlencode($sub) : '';
    $pg = sonika_slice(sonika_category_products($cat['slug'], $sub), 24);
    sonika_site_head($cat['name'] . ' — SONIKA', $cat['name'] . ': ' . $cat['tags'] . '. Compra en SONIKA con envío a toda Colombia.');
    sonika_breadcrumb([$cat['name'] => null]);
    ?>
    <section class="pad wrap">
      <div class="head"><h1><?= sonika_esc($cat['name']) ?></h1><a href="/tienda">Ver toda la tienda →</a></div>
      <div class="toolbar">
        <span class="resultcount"><?= (int)$pg['total'] ?> producto(s) en <?= sonika_esc($cat['name']) ?></span>
        <div class="filters">
          <?php foreach (sonika_categories() as $c): ?>
            <a class="<?= $c['slug'] === $cat['slug'] ? 'on' : '' ?>" href="/<?= sonika_esc($c['slug']) ?>"><?= sonika_esc($c['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php if ($cat['slug'] === 'guitarras'): ?>
      <div class="filters" style="margin:2px 0 18px">
        <a class="<?= $sub === '' ? 'on' : '' ?>" href="/guitarras">Todas</a>
        <?php foreach (sonika_guitar_subs_used() as $s2): if ((int)$s2['count'] <= 0) continue; ?>
          <a class="<?= $sub === $s2['slug'] ? 'on' : '' ?>" href="/guitarras?sub=<?= sonika_esc($s2['slug']) ?>"><?= sonika_esc($s2['name']) ?> (<?= (int)$s2['count'] ?>)</a>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <?php sonika_grid($pg['items']); sonika_pager($pg['total'], $pg['perPage'], $pg['page'], '/' . $cat['slug'] . ($qq = ($sub !== '' ? '?sub=' . rawurlencode($sub) : ''))); ?>
    </section>
    <?php
    sonika_site_footer();
    return true;
}

switch ($segments[0] ?? '') {
    case 'tienda':
        $pg = sonika_slice(sonika_products(), 24);
        sonika_site_head('Tienda — SONIKA', 'Todos los instrumentos, audio, iluminación y accesorios disponibles en SONIKA.');
        sonika_breadcrumb(['Tienda' => null]);
        ?>
        <section class="pad wrap">
          <div class="head"><h1>Tienda</h1><span class="resultcount"><?= (int)$pg['total'] ?> productos</span></div>
          <div class="filters" style="margin-bottom:20px">
            <a href="/tienda" class="on">Todos</a>
            <?php foreach (sonika_categories() as $c): ?><a href="/<?= sonika_esc($c['slug']) ?>"><?= sonika_esc($c['name']) ?></a><?php endforeach; ?>
          </div>
          <?php sonika_grid($pg['items']); sonika_pager($pg['total'], $pg['perPage'], $pg['page'], '/tienda'); ?>
        </section>
        <?php
        sonika_site_footer();
        return true;

    case 'ofertas':
        $all = array_values(array_filter(sonika_products(), function ($p) { return sonika_compare($p) > sonika_price($p) && sonika_price($p) > 0; }));
        $pg = sonika_slice($all, 24);
        sonika_site_head('Ofertas — SONIKA', 'Instrumentos y equipos en oferta en SONIKA.');
        sonika_breadcrumb(['Ofertas' => null]);
        ?>
        <section class="pad wrap">
          <div class="head"><h1>Ofertas 🔥</h1><span class="resultcount"><?= (int)$pg['total'] ?> productos en promoción</span></div>
          <?php sonika_grid($pg['items']); sonika_pager($pg['total'], $pg['perPage'], $pg['page'], '/ofertas'); ?>
        </section>
        <?php
        sonika_site_footer();
        return true;

    case 'buscar':
        if (($segments[1] ?? '') === 'sugerencias') {
            $q = trim((string)($_GET['q'] ?? ''));
            $res = $q === '' ? [] : array_slice(sonika_search($q), 0, 6);
            $out = array_map(function ($p) {
                return [
                    'title' => (string)($p['title'] ?? ''),
                    'vendor' => (string)($p['vendor'] ?? ''),
                    'price' => sonika_price($p),
                    'thumb' => sonika_img($p, 90),
                    'url' => '/producto/' . ($p['handle'] ?? ''),
                ];
            }, $res);
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(['q' => $q, 'results' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return true;
        }
        if (($segments[1] ?? '') === 'live') {
            $all = sonika_filter_products($_GET);
            $pg = sonika_slice($all, 24);
            $pages = max(1, (int)ceil($pg['total'] / max(1, $pg['perPage'])));
            ob_start();
            sonika_grid($pg['items']);
            $items = ob_get_clean();
            ob_start();
            $__qs = $_GET; unset($__qs['page']);
            sonika_pager($pg['total'], $pg['perPage'], $pg['page'], '/buscar?' . http_build_query($__qs));
            $pager = ob_get_clean();
            header('Content-Type: application/json; charset=utf-8');
            header('Cache-Control: no-store');
            echo json_encode(['ok' => true, 'count' => (int)$pg['total'], 'html' => $items . $pager, 'items' => $items, 'page' => (int)$pg['page'], 'pages' => $pages, 'has_more' => (int)$pg['page'] < $pages], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return true;
        }
        $q = trim((string)($_GET['q'] ?? ''));
        $all = sonika_filter_products($_GET);
        try { (new \App\Services\SearchAnalyticsService())->log($q, ['marca' => (string)($_GET['marca'] ?? ''), 'tipo' => (string)($_GET['tipo'] ?? ''), 'cat' => (string)($_GET['cat'] ?? ''), 'min' => (float)($_GET['min'] ?? 0), 'max' => (float)($_GET['max'] ?? 0), 'desc' => (float)($_GET['desc'] ?? 0), 'oferta' => !empty($_GET['oferta']), 'nuevo' => !empty($_GET['nuevo']), 'disp' => !empty($_GET['disp']), 'orden' => (string)($_GET['orden'] ?? '')], count($all)); } catch (\Throwable $e) {}
        $pg = sonika_slice($all, 24);
        sonika_site_head('Buscar' . ($q ? ': ' . $q : '') . ' — SONIKA', 'Resultados de búsqueda en SONIKA.');
        sonika_breadcrumb(['Buscar' => null]);
        ?>
        <section class="pad wrap">
          <div class="head"><h1><?= $q !== '' ? 'Resultados para “' . sonika_esc($q) . '”' : 'Buscar' ?></h1><span class="resultcount" id="skCount"><?= (int)$pg['total'] ?> resultado(s)</span></div>
          <div id="skResults"><?php sonika_grid($pg['items']); $__qs = $_GET; unset($__qs['page']); sonika_pager($pg['total'], $pg['perPage'], $pg['page'], '/buscar?' . http_build_query($__qs)); ?></div>
        </section>
        <?php
        sonika_site_footer();
        return true;

    case 'carrito':
        sonika_site_head('Carrito — SONIKA', 'Revisa los productos de tu carrito.');
        sonika_breadcrumb(['Carrito' => null]);
        ?>
        <section class="wrap" id="cartPage">
          <h1 style="font-family:'Sora',sans-serif;padding:24px 0 6px">Tu carrito</h1>
          <div class="cart-wrap"><div id="cartItems"></div><aside class="summary" id="cartSummary"></aside></div>
        </section>
        <?php
        sonika_site_footer();
        return true;

    case 'checkout':
        sonika_site_head('Finalizar compra - SONIKA', 'Completa tus datos para finalizar la compra.');
        sonika_breadcrumb(['Carrito' => '/carrito', 'Finalizar compra' => null]);
        ?>
        <section class="wrap" id="checkoutPage" style="padding:24px 0 64px">
          <h1 style="font-family:'Sora',sans-serif;margin-bottom:18px">Finalizar compra</h1>
          <div class="cart-wrap">
            <form id="ckForm" autocomplete="on" style="display:grid;gap:12px;align-content:start">
              <div id="ckItems" style="display:grid;gap:10px"></div>
              <input name="name" placeholder="Nombre completo *" required style="padding:12px;border:1px solid var(--line,#E6E6E6);border-radius:10px;font:inherit">
              <input name="email" type="email" placeholder="Correo electrónico *" required style="padding:12px;border:1px solid var(--line,#E6E6E6);border-radius:10px;font:inherit">
              <input name="phone" placeholder="Teléfono / WhatsApp *" required style="padding:12px;border:1px solid var(--line,#E6E6E6);border-radius:10px;font:inherit">
              <input name="line1" placeholder="Dirección (opcional)" style="padding:12px;border:1px solid var(--line,#E6E6E6);border-radius:10px;font:inherit">
              <input name="city" placeholder="Ciudad (opcional)" style="padding:12px;border:1px solid var(--line,#E6E6E6);border-radius:10px;font:inherit">
              <textarea name="notes" rows="3" placeholder="Notas para el pedido (opcional)" style="padding:12px;border:1px solid var(--line,#E6E6E6);border-radius:10px;font:inherit"></textarea>
              <button type="submit" class="btn-buy" style="padding:14px;font-size:16px">Confirmar pedido</button>
              <?php if (sonika_whatsapp() !== ''): ?>
              <a href="#" id="ckWa" class="btn-buy" style="padding:14px;font-size:16px;background:#25D366;color:#fff;text-align:center;text-decoration:none">Pedir por WhatsApp</a>
              <?php endif; ?>
              <p id="ckErr" style="color:#DC2626;font-size:14px;margin:0"></p>
              <p style="color:var(--muted,#777);font-size:13px;margin:0">Al confirmar, registramos tu pedido y te contactamos por WhatsApp para coordinar el pago y el envío.</p>
            </form>
            <aside class="summary" id="ckSummary"></aside>
          </div>
        </section>
        <script>
        (function(){
          var KEY='sonika_cart';
          function read(){try{return JSON.parse(localStorage.getItem(KEY))||[]}catch(e){return[]}}
          function money(n){return '$'+Math.round(n||0).toLocaleString('es-CO')}
          function esc(s){var d=document.createElement('div');d.textContent=(s==null?'':String(s));return d.innerHTML}
          var items=read();
          var page=document.getElementById('checkoutPage');if(!page)return;
          var it=document.getElementById('ckItems');var sum=document.getElementById('ckSummary');
          var btn=page.querySelector('button[type=submit]');
          var wa=document.getElementById('ckWa');
          if(wa)wa.addEventListener('click',function(e){e.preventDefault();var f=document.getElementById('ckForm');var extra=[];
            if(f){['name','email','phone','line1','city'].forEach(function(k){var el=f.elements[k];if(el&&el.value)extra.push(k+': '+el.value)})}
            if(window.sonikaWhatsapp)window.sonikaWhatsapp(extra.join(' | '))});
          if(!items.length){it.innerHTML='<div class="empty" style="padding:20px;border:1px dashed var(--line,#E6E6E6);border-radius:12px">Tu carrito está vacío. <a href="/tienda" style="color:var(--brand);font-weight:600">Explorar tienda →</a></div>';if(sum)sum.innerHTML='';if(btn)btn.disabled=true;return}
          var st=0;it.innerHTML=items.map(function(i){var l=(parseFloat(i.price)||0)*(parseInt(i.qty)||1);st+=l;var im=i.img||i.image;
            return '<div class="cart-item" style="display:flex;gap:12px;align-items:center;border:1px solid var(--line,#E6E6E6);border-radius:12px;padding:10px">'+(im?'<img src="'+esc(im)+'" alt="" style="width:54px;height:54px;object-fit:cover;border-radius:8px;background:#F5F5F5">':'')+'<div style="flex:1"><div style="font-weight:600;font-size:14px">'+esc(i.title||i.handle||'')+'</div><div style="color:var(--muted,#777);font-size:13px">x'+(parseInt(i.qty)||1)+'</div></div><div style="font-weight:700">'+money(l)+'</div></div>'}).join('');
          if(sum)sum.innerHTML='<h3 style="font-family:Sora,sans-serif;margin-bottom:10px">Resumen</h3><div class="row"><span>Subtotal</span><span>'+money(st)+'</span></div><div class="row"><span>Envío</span><span>Se coordina al pagar</span></div><div class="row total"><span>Total</span><span>'+money(st)+'</span></div>';
          page.querySelector('#ckForm').addEventListener('submit',function(e){e.preventDefault();
            var f=e.target;
            var payload={items:items.map(function(i){return{ref:i.handle,qty:parseInt(i.qty)||1}}),
              customer:{name:f.elements['name'].value,email:f.elements['email'].value,phone:f.elements['phone'].value},
              shipping:{address:{line1:f.elements['line1'].value,city:f.elements['city'].value}},
              notes:f.elements['notes'].value};
            if(btn){btn.disabled=true;btn.textContent='Enviando…'}
            fetch('/api/v1/public/store/catalog-order',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(payload)})
              .then(function(r){return r.json()}).then(function(d){
                if(d&&d.ok){try{localStorage.removeItem(KEY)}catch(e){}
                  var o=(d.data&&d.data.order_number)||'';
                  page.innerHTML='<div style="max-width:560px;margin:40px auto;text-align:center"><div style="font-size:52px">✅</div><h2 style="font-family:Sora,sans-serif;margin:8px 0">¡Pedido recibido!</h2><p style="color:var(--muted,#777)">Número de pedido: <b>'+esc(o)+'</b></p><p>Te contactaremos por WhatsApp para coordinar el pago y el envío.</p><a class="btn-buy" href="/tienda" style="display:inline-block;margin-top:14px;padding:12px 22px">Seguir comprando</a></div>';
                  document.querySelectorAll('.wwi-cart').forEach(function(r){window.wwiCartRender&&window.wwiCartRender(r)});
                  document.querySelectorAll('#cartCount').forEach(function(el){el.textContent='0';el.style.display='none'});
                }else{var msg=(d&&d.message)||'No se pudo enviar el pedido.';var er=document.getElementById('ckErr');if(er)er.textContent=msg;if(btn){btn.disabled=false;btn.textContent='Confirmar pedido'}}
              }).catch(function(){var er=document.getElementById('ckErr');if(er)er.textContent='Error de conexión. Intenta de nuevo.';if(btn){btn.disabled=false;btn.textContent='Confirmar pedido'}});
          });
        })();
        </script>
        <?php
        sonika_site_footer();
        return true;

    case 'marcas':
        $brands = [];
        foreach (sonika_products() as $p) {
            $v = trim((string)($p['vendor'] ?? ''));
            if ($v === '') continue;
            $brands[$v] = ($brands[$v] ?? 0) + 1;
        }
        ksort($brands);
        sonika_site_head('Marcas — SONIKA', 'Marcas disponibles en SONIKA.');
        sonika_breadcrumb(['Marcas' => null]);
        ?>
        <section class="pad wrap">
          <div class="head"><h1>Marcas</h1><span class="resultcount"><?= count($brands) ?> marcas</span></div>
          <div class="cat-grid">
            <?php foreach ($brands as $name => $n): ?>
              <a class="cat" href="/buscar?q=<?= urlencode($name) ?>"><h3><?= sonika_esc($name) ?></h3><p><?= (int)$n ?> producto(s)</p></a>
            <?php endforeach; ?>
          </div>
        </section>
        <?php
        sonika_site_footer();
        return true;

    case 'cuenta':
        sonika_site_head('Mi cuenta — SONIKA', 'Administra tu cuenta, pedidos, direcciones y favoritos.');
        sonika_breadcrumb(['Mi cuenta' => null]);
        ?>
        <?php
        $acctImg = trim((string)wwi_tc('sonika', 'account_visual_image', ''));
        $acctAlt = (string)wwi_tc('sonika', 'account_visual_alt', '');
        $acctEyebrow = (string)wwi_tc('sonika', 'account_visual_eyebrow', \App\Core\Lang::get('acct_eyebrow', 'SONIKA'));
        $acctTitle = (string)wwi_tc('sonika', 'account_visual_title', \App\Core\Lang::get('acct_title', 'Todo tu *mundo musical* en un solo lugar.'));
        $acctText = (string)wwi_tc('sonika', 'account_visual_text', \App\Core\Lang::get('acct_text', 'Accede a tus pedidos, guarda tus favoritos y descubre nuevos instrumentos y equipos para llevar tu pasión más lejos.'));
        $acctBenefits = wwi_tc('sonika', 'account_benefits', null);
        if (!is_array($acctBenefits) || !$acctBenefits) {
            $acctBenefits = [
                ['icon' => '♥', 'title' => \App\Core\Lang::get('acct_b1t', 'Tus favoritos siempre a mano'), 'text' => \App\Core\Lang::get('acct_b1d', 'Guarda los productos que más te gustan.')],
                ['icon' => '📦', 'title' => \App\Core\Lang::get('acct_b2t', 'Consulta tus pedidos'), 'text' => \App\Core\Lang::get('acct_b2d', 'Revisa el estado de tus compras.')],
                ['icon' => '👤', 'title' => \App\Core\Lang::get('acct_b3t', 'Gestiona tus datos'), 'text' => \App\Core\Lang::get('acct_b3d', 'Actualiza tu información y tus direcciones de entrega.')],
            ];
        }
        $acctHl = function (string $s): string {
            return preg_replace('/\*(.+?)\*/s', '<span class="sonika-account-login__visual-highlight">$1</span>', htmlspecialchars($s, ENT_QUOTES));
        };
        $acctLayout = (string)wwi_tc('sonika', 'account_layout', 'split');
        if (!in_array($acctLayout, ['split', 'minimal'], true)) $acctLayout = 'split';
        $acctSide = ((string)wwi_tc('sonika', 'account_visual_side', 'right') === 'left') ? 'left' : 'right';
        $acctPos = (string)wwi_tc('sonika', 'account_visual_image_position', 'center');
        if (!in_array($acctPos, ['center', 'top', 'bottom', 'left', 'right'], true)) $acctPos = 'center';
        $acctOverlay = max(0, min(100, (int)wwi_tc('sonika', 'account_visual_overlay', 88)));
        $acctVideo = trim((string)wwi_tc('sonika', 'account_visual_video', ''));
        $acctVideoSafe = ($acctVideo !== '' && preg_match('~^(https?://|/)~i', $acctVideo)) ? $acctVideo : '';
        $acctFormWidth = (int)wwi_tc('sonika', 'account_form_width', 560);
        if ($acctFormWidth < 320 || $acctFormWidth > 900) $acctFormWidth = 560;
        $acctSocial = (bool)wwi_tc('sonika', 'account_social_proof', true);
        $acctSocialText = trim((string)wwi_tc('sonika', 'account_social_text', '4.9/5 · +1.200 clientes nos eligen'));
        $acctImgSafe = ($acctImg !== '' && preg_match('~^(https?://|/)~i', $acctImg)) ? $acctImg : '';
        $acctStyle = '--sonika-acct-pos:' . $acctPos . ';--sonika-acct-overlay:' . ($acctOverlay / 100);
        if ($acctImgSafe) $acctStyle .= ';--sonika-account-login-background-image:url(\'' . htmlspecialchars($acctImgSafe, ENT_QUOTES) . '\')';
        $acctVisualClass = $acctVideoSafe !== '' ? ' has-video' : ($acctImgSafe !== '' ? ' has-background-image' : '');
        $panelHtml = '<aside class="sonika-account-login__visual' . $acctVisualClass . '" style="' . $acctStyle . '"'
            . ' aria-hidden="true">'
            . ($acctVideoSafe !== '' ? '<video class="sonika-account-login__vbg" autoplay muted loop playsinline' . ($acctImgSafe !== '' ? ' poster="' . htmlspecialchars($acctImgSafe, ENT_QUOTES) . '"' : '') . '><source src="' . htmlspecialchars($acctVideoSafe, ENT_QUOTES) . '"/></video>' : '')
            . '<div class="sonika-account-login__visual-content">'
            . '<p class="sonika-account-login__eyebrow">' . htmlspecialchars($acctEyebrow, ENT_QUOTES) . '</p>'
            . '<h2 class="sonika-account-login__visual-title">' . $acctHl($acctTitle) . '</h2>'
            . '<p class="sonika-account-login__visual-description">' . htmlspecialchars($acctText, ENT_QUOTES) . '</p>'
            . '<div class="sonika-account-login__benefits">';
        foreach ($acctBenefits as $b) {
            if (!is_array($b)) continue;
            $bi = (string)($b['icon'] ?? '');
            $bt = (string)($b['title'] ?? '');
            $bd = (string)($b['text'] ?? '');
            if ($bt === '' && $bd === '') continue;
            $panelHtml .= '<div class="sonika-account-login__benefit"><div class="sonika-account-login__benefit-icon" aria-hidden="true">' . htmlspecialchars($bi, ENT_QUOTES) . '</div><div><p class="sonika-account-login__benefit-title">' . htmlspecialchars($bt, ENT_QUOTES) . '</p><p class="sonika-account-login__benefit-description">' . htmlspecialchars($bd, ENT_QUOTES) . '</p></div></div>';
        }
        if ($acctSocial && $acctSocialText !== '') {
            $panelHtml .= '<div class="sonika-account-login__social">' . htmlspecialchars($acctSocialText, ENT_QUOTES) . '</div>';
        }
        $panelHtml .= '</div></div></aside>';
        $acctI18n = [
            'eyebrow' => \App\Core\Lang::get('acct_eyebrow', 'MI CUENTA'),
            'loginTitle' => \App\Core\Lang::get('acct_login_title', '¡Qué bueno tenerte de vuelta!'),
            'loginDesc' => \App\Core\Lang::get('acct_login_desc', 'Inicia sesión para acceder a tu cuenta y seguir disfrutando de la música.'),
            'email' => \App\Core\Lang::get('acct_email', 'Correo electrónico'),
            'password' => \App\Core\Lang::get('password', 'Contraseña'),
            'forgot' => \App\Core\Lang::get('acct_forgot', '¿Olvidaste tu contraseña?'),
            'enter' => \App\Core\Lang::get('acct_enter', 'Entrar'),
            'noAccount' => \App\Core\Lang::get('acct_no_account', '¿Todavía no tienes cuenta?'),
            'create' => \App\Core\Lang::get('create_account', 'Crear cuenta'),
            'badCred' => \App\Core\Lang::get('acct_bad_cred', 'Credenciales inválidas.'),
            'regTitle' => \App\Core\Lang::get('acct_reg_title', 'Crea tu cuenta'),
            'regDesc' => \App\Core\Lang::get('acct_reg_desc', 'Regístrate para comprar más rápido y seguir tus pedidos.'),
            'name' => \App\Core\Lang::get('acct_name', 'Nombre'),
            'passMin' => \App\Core\Lang::get('acct_pass_min', 'Contraseña (mín 8)'),
            'haveAccount' => \App\Core\Lang::get('acct_have_account', '¿Ya tienes cuenta?'),
            'signin' => \App\Core\Lang::get('login', 'Iniciar sesión'),
            'recTitle' => \App\Core\Lang::get('acct_rec_title', 'Recupera tu contraseña'),
            'recDesc' => \App\Core\Lang::get('acct_rec_desc', 'Te enviamos un código a tu correo registrado.'),
            'sendCode' => \App\Core\Lang::get('acct_send_code', 'Enviar código'),
            'code' => \App\Core\Lang::get('acct_code', 'Código (6 dígitos)'),
            'newPass' => \App\Core\Lang::get('acct_new_pass', 'Nueva contraseña (mín 8)'),
            'reset' => \App\Core\Lang::get('acct_reset', 'Restablecer'),
            'back' => \App\Core\Lang::get('acct_back', 'Volver'),
            'invalidCode' => \App\Core\Lang::get('acct_invalid_code', 'Código inválido'),
            'failCreate' => \App\Core\Lang::get('acct_fail_create', 'No se pudo crear la cuenta'),
        ];
        ?>
        <style>
        .sonika-account-login{width:100%;color:var(--ink);box-sizing:border-box}
        .sonika-account-login *,.sonika-account-login *::before,.sonika-account-login *::after{box-sizing:border-box}
        .sonika-account-login__layout{display:grid;grid-template-columns:minmax(0,.92fr) minmax(0,1.08fr);gap:clamp(20px,2.6vw,44px);align-items:stretch}
        .sonika-account-login__panel{min-width:0}
        .sonika-account-login__form-card{width:100%;max-width:var(--sonika-acct-formw,560px);margin-inline:auto;padding:clamp(22px,2.8vw,38px);background:#fff;border:1px solid var(--line);border-radius:16px;box-shadow:0 10px 34px rgba(0,0,0,.06)}
        .sonika-account-login__eyebrow{display:flex;align-items:center;gap:10px;margin:0 0 14px;color:var(--brand);font-size:12px;font-weight:800;letter-spacing:.1em;text-transform:uppercase}
        .sonika-account-login__eyebrow::before{content:"";width:38px;height:2px;background:var(--brand)}
        .sonika-account-login__title{margin:0 0 8px;color:var(--ink);font-family:'Sora',sans-serif;font-size:clamp(25px,2.3vw,34px);font-weight:800;line-height:1.12;letter-spacing:-.03em}
        .sonika-account-login__description{margin:0 0 22px;color:var(--muted);font-size:15px;line-height:1.6}
        .sonika-account-login__notice{margin:0 0 14px;padding:10px 12px;border-radius:9px;background:#FDECEC;border:1px solid #F5C2C2;color:#B42318;font-size:13px}
        .sonika-account-login__field{margin-bottom:16px}
        .sonika-account-login__label{display:block;margin-bottom:7px;color:var(--ink);font-size:13.5px;font-weight:650}
        .sonika-account-login__input{display:block;width:100%;min-height:48px;padding:12px 14px;color:var(--ink);background:#fff;border:1px solid var(--line);border-radius:9px;font:inherit;font-size:15px;transition:border-color .14s ease,box-shadow .14s ease}
        .sonika-account-login__input:hover{border-color:#BDBDBD}
        .sonika-account-login__input:focus-visible{border-color:var(--brand);outline:none;box-shadow:0 0 0 3px rgba(242,13,22,.12)}
        .sonika-account-login__row{display:flex;justify-content:flex-end;margin:-4px 0 16px}
        .sonika-account-login__secondary-link{color:var(--brand);font-size:13px;font-weight:650;text-decoration:underline;text-decoration-color:transparent;text-underline-offset:3px;transition:text-decoration-color .14s ease}
        .sonika-account-login__secondary-link:hover{text-decoration-color:currentColor}
        .sonika-account-login__secondary-link:focus-visible{outline:2px solid var(--brand);outline-offset:3px;border-radius:4px}
        .sonika-account-login__submit{display:inline-flex;align-items:center;justify-content:center;gap:10px;width:100%;min-height:48px;padding:12px 18px;border:0;border-radius:9px;background:var(--brand);color:#fff;font:inherit;font-size:15px;font-weight:700;cursor:pointer;transition:filter .14s ease,transform .14s ease,box-shadow .14s ease}
        .sonika-account-login__submit:hover{filter:brightness(.95)}
        .sonika-account-login__submit:focus-visible{outline:3px solid rgba(242,13,22,.3);outline-offset:3px}
        .sonika-account-login__submit:active{transform:translateY(1px)}
        .sonika-account-login__register{margin-top:18px;padding:16px;background:var(--soft);border-radius:11px;text-align:center;font-size:14px;color:var(--ink)}
        .sonika-account-login__visual{position:relative;isolation:isolate;display:flex;flex-direction:column;justify-content:center;min-width:0;min-height:560px;overflow:hidden;padding:clamp(26px,3vw,46px);border-radius:16px;background:radial-gradient(circle at 85% 20%,rgba(242,13,22,.14),transparent 44%),linear-gradient(135deg,#fff 0%,var(--soft) 100%)}
        .sonika-account-login__visual.has-background-image{background-image:linear-gradient(90deg,rgba(255,255,255,.97) 0%,rgba(255,255,255,.88) 44%,rgba(255,255,255,.22) 100%),var(--sonika-account-login-background-image);background-size:cover;background-position:center;background-repeat:no-repeat}
        .sonika-account-login__visual-content{position:relative;z-index:1;width:100%;max-width:470px}
        .sonika-account-login__visual-title{margin:16px 0;color:var(--ink);font-family:'Sora',sans-serif;font-size:clamp(28px,2.9vw,42px);font-weight:800;line-height:1.08;letter-spacing:-.04em}
        .sonika-account-login__visual-highlight{color:var(--brand)}
        .sonika-account-login__visual-description{max-width:430px;margin:0;color:var(--muted);font-size:15px;line-height:1.65}
        .sonika-account-login__benefits{display:grid;gap:14px;margin-top:26px}
        .sonika-account-login__benefit{display:grid;grid-template-columns:46px minmax(0,1fr);align-items:center;gap:14px}
        .sonika-account-login__benefit-icon{display:grid;place-items:center;width:46px;height:46px;border-radius:11px;background:rgba(255,255,255,.9);border:1px solid rgba(0,0,0,.06);font-size:20px;color:var(--brand)}
        .sonika-account-login__benefit-title{margin:0 0 3px;color:var(--ink);font-size:14px;font-weight:700}
        .sonika-account-login__benefit-description{margin:0;color:var(--muted);font-size:12.5px;line-height:1.5}
        @media(max-width:1024px){.sonika-account-login__layout{grid-template-columns:minmax(0,.95fr) minmax(0,1.05fr);gap:22px}.sonika-account-login__form-card{padding:26px}.sonika-account-login__visual{min-height:500px;padding:26px}}
        @media(max-width:760px){.sonika-account-login__layout{grid-template-columns:minmax(0,1fr);gap:18px}.sonika-account-login__form-card{max-width:none;padding:22px 18px;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,.05)}.sonika-account-login__visual{min-height:auto;padding:26px 20px;border-radius:12px}.sonika-account-login__benefits{margin-top:20px}}
        .sonika-account-login__visual.has-background-image{background-image:var(--sonika-account-login-background-image);background-size:cover;background-position:var(--sonika-acct-pos,center);background-repeat:no-repeat}
        .sonika-account-login__visual.has-background-image::before,.sonika-account-login__visual.has-video::before{content:"";position:absolute;inset:0;z-index:0;background:linear-gradient(90deg,#fff 0%,rgba(255,255,255,.9) 42%,rgba(255,255,255,.15) 100%);opacity:var(--sonika-acct-overlay,.88);pointer-events:none}
        .sonika-account-login__vbg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;z-index:-1}
        .sonika-account-login__social{margin-top:22px;padding-top:14px;border-top:1px solid rgba(0,0,0,.08);font-size:13px;font-weight:600;color:var(--ink)}
        .sonika-account-login--left .sonika-account-login__visual{order:-1}
        .sonika-account-login--minimal{display:flex;align-items:center;justify-content:center;min-height:min(78vh,760px)}
        .sonika-account-login--minimal .sonika-account-login__panel{width:100%;max-width:520px}
        @media(prefers-reduced-motion:reduce){.sonika-account-login *{transition-duration:.01ms!important;animation-duration:.01ms!important}}
        </style>
        <section class="wrap" style="padding:28px 0 56px">
          <div id="acct" style="--sonika-acct-formw:<?= (int)$acctFormWidth ?>px">Cargando…</div>
        </section>
        <script>var SITE=<?= (int)\App\Core\Config::get('SITE_ID', '1') ?>;
        var E=<?= json_encode($acctI18n, JSON_UNESCAPED_UNICODE) ?>;
        var VIS=<?= json_encode($panelHtml, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
        var LAYOUT=<?= json_encode($acctLayout) ?>;var SIDE=<?= json_encode($acctSide) ?>;
        (function(){
          var box=document.getElementById('acct');
          function esc(s){var d=document.createElement('div');d.textContent=(s==null?'':String(s));return d.innerHTML}
          function money(n){return '$'+Math.round(n||0).toLocaleString('es-CO')}
          function inp(id,ph,type,val){return '<input id="'+id+'" type="'+(type||'text')+'" placeholder="'+ph+'" value="'+(val||'')+'" style="width:100%;border:1px solid #E6E6E6;border-radius:9px;padding:10px;font:inherit;font-size:13px;margin-top:6px">'}
          function btn(id,label){return '<button id="'+id+'" style="margin-top:10px;border:none;border-radius:10px;background:#F20D16;color:#fff;font-weight:700;font-size:13px;padding:11px 20px;cursor:pointer">'+label+'</button>'}
          function link(id,label){return ' <a href="#" id="'+id+'" style="font-size:13px;color:#777;margin-left:10px">'+label+'</a>'}
          function api(u,o){return fetch(u,Object.assign({headers:{'Content-Type':'application/json; charset=UTF-8'}},o||{})).then(function(r){return r.json()})}
          function qs(){var o={};location.search.replace(/^\?/,'').split('&').forEach(function(p){if(!p)return;var kv=p.split('=');o[kv[0]]=decodeURIComponent(kv[1]||'')});return o}
          function acField(label,id,type,ac){return '<div class="sonika-account-login__field"><label class="sonika-account-login__label" for="'+id+'">'+label+'</label><input class="sonika-account-login__input" id="'+id+'" type="'+(type||'text')+'" autocomplete="'+(ac||'off')+'" /></div>'}
          function acHead(eyebrow,title,desc){return '<p class="sonika-account-login__eyebrow">'+eyebrow+'</p><h2 class="sonika-account-login__title">'+title+'</h2>'+(desc?'<p class="sonika-account-login__description">'+desc+'</p>':'')}
          function acShell(inner){var card='<div class="sonika-account-login__panel"><div class="sonika-account-login__form-card">'+inner+'</div></div>';if(LAYOUT==='minimal'){return '<div class="sonika-account-login sonika-account-login--minimal">'+card+'</div>'}return '<div class="sonika-account-login sonika-account-login--split sonika-account-login--'+SIDE+'"><div class="sonika-account-login__layout">'+card+VIS+'</div></div>'}
          function login(err){box.innerHTML=acShell(acHead(E.eyebrow,E.loginTitle,E.loginDesc)+(err?'<div class="sonika-account-login__notice">'+esc(err)+'</div>':'')+acField(E.email,'ac-email','email','email')+acField(E.password,'ac-pass','password','current-password')+'<div class="sonika-account-login__row"><a href="#" id="ac-forgot" class="sonika-account-login__secondary-link">'+E.forgot+'</a></div><button class="sonika-account-login__submit" id="ac-login">'+E.enter+'</button><div class="sonika-account-login__register">'+E.noAccount+' <a href="#" id="ac-toreg" class="sonika-account-login__secondary-link">'+E.create+'</a></div>');
            document.getElementById('ac-login').onclick=async function(){var r=await api('/api/v1/public/account/login',{method:'POST',body:JSON.stringify({email:document.getElementById('ac-email').value,password:document.getElementById('ac-pass').value})});if(r.ok){location.search=''}else{login(r.message||E.badCred)}};
            document.getElementById('ac-toreg').onclick=function(e){e.preventDefault();location.search='?tab=register'};
            document.getElementById('ac-forgot').onclick=function(e){e.preventDefault();forgot()}}
          function forgot(){box.innerHTML=acShell(acHead(E.eyebrow,E.recTitle,E.recDesc)+acField(E.email,'fg-email','email','email')+'<button class="sonika-account-login__submit" id="fg-go">'+E.sendCode+'</button><div id="fg-step2" hidden style="margin-top:16px">'+acField(E.code,'fg-code','text')+acField(E.newPass,'fg-pass','password','new-password')+'<button class="sonika-account-login__submit" id="fg-reset">'+E.reset+'</button></div><div class="sonika-account-login__row" style="justify-content:flex-start;margin-top:14px"><a href="#" id="fg-back" class="sonika-account-login__secondary-link">'+E.back+'</a></div>');
            document.getElementById('fg-go').onclick=async function(){await api('/api/v1/public/account/forgot',{method:'POST',body:JSON.stringify({email:document.getElementById('fg-email').value})});document.getElementById('fg-step2').hidden=false};
            document.getElementById('fg-reset').onclick=async function(){var r=await api('/api/v1/public/account/reset',{method:'POST',body:JSON.stringify({email:document.getElementById('fg-email').value,code:document.getElementById('fg-code').value,password:document.getElementById('fg-pass').value})});if(r.ok){location.search=''}else{alert(r.message||E.invalidCode)}};
            document.getElementById('fg-back').onclick=function(e){e.preventDefault();location.search=''}}
          function reg(){box.innerHTML=acShell(acHead(E.eyebrow,E.regTitle,E.regDesc)+acField(E.name,'rg-name','text','name')+acField(E.email,'rg-email','email','email')+acField(E.passMin,'rg-pass','password','new-password')+'<button class="sonika-account-login__submit" id="rg-go">'+E.create+'</button><div class="sonika-account-login__register">'+E.haveAccount+' <a href="#" id="rg-to" class="sonika-account-login__secondary-link">'+E.signin+'</a></div>');
            document.getElementById('rg-go').onclick=async function(){var r=await api('/api/v1/public/account/register',{method:'POST',body:JSON.stringify({name:document.getElementById('rg-name').value,email:document.getElementById('rg-email').value,password:document.getElementById('rg-pass').value})});if(r.ok){location.search=''}else{login(r.message||E.failCreate)}};
            document.getElementById('rg-to').onclick=function(e){e.preventDefault();location.search=''}}
          function account(d){var c=d.customer||{},tab=qs().tab||'summary';
            var L=<?= json_encode([
                'summary' => \App\Core\Lang::get('tab_summary', 'Resumen'),
                'orders' => \App\Core\Lang::get('tab_orders', 'Pedidos'),
                'favorites' => \App\Core\Lang::get('tab_favorites', 'Favoritos'),
                'addresses' => \App\Core\Lang::get('tab_addresses', 'Direcciones'),
                'profile' => \App\Core\Lang::get('tab_profile', 'Perfil'),
                'reviews' => \App\Core\Lang::get('tab_reviews', 'Reseñas'),
                'points' => \App\Core\Lang::get('tab_points', 'Puntos'),
                'payment' => \App\Core\Lang::get('tab_payment', 'Pago'),
                'logout' => \App\Core\Lang::get('logout', 'Cerrar sesión'),
            ], JSON_UNESCAPED_UNICODE) ?>;
            function tb(id,l){return '<a href="?tab='+id+'" style="padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;'+(tab===id?'background:#F20D16;color:#fff':'color:#555')+'">'+l+'</a>'}
            box.innerHTML='<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">'+tb('summary',L.summary)+tb('orders',L.orders)+tb('favorites',L.favorites)+tb('addresses',L.addresses)+tb('profile',L.profile)+tb('reviews',L.reviews)+tb('points',L.points)+tb('payment',L.payment)+'</div><div id="ac-body"></div>';
            var b=document.getElementById('ac-body');
            if(tab==='orders'){$o(b)} else if(tab==='favorites'){$f(b)} else if(tab==='addresses'){$a(b,d.addresses||[])} else if(tab==='profile'){$p(b,c)} else if(tab==='reviews'){$rv(b)} else if(tab==='points'){$pts(b)} else if(tab==='payment'){$pm(b)} else {b.innerHTML='<div class="card" style="padding:20px"><p>Hola, <b>'+esc(c.name||c.email)+'</b>.</p><p style="color:#777;font-size:13px;margin-top:6px">Pedidos: '+(c.orders_count||0)+'</p></div>'+(c.email_verified_at?'':'<div class="card" style="padding:16px;margin-top:12px;border-left:3px solid #F20D16"><b>Verifica tu email</b><p style="color:#777;font-size:13px;margin:6px 0">Enviamos un código a tu correo para activar tu cuenta y las alertas.</p>'+inp('ve-code','Código (6 dígitos)')+btn('ve-go','Verificar')+' <a href="#" id="ve-resend" style="font-size:13px">Reenviar código</a></div>');
              if(!c.email_verified_at){document.getElementById('ve-go').onclick=async function(){var r=await api('/api/v1/public/account/verify-email',{method:'POST',body:JSON.stringify({email:c.email,code:document.getElementById('ve-code').value})});if(r.ok)location.reload();else alert(r.message||'Código inválido')};document.getElementById('ve-resend').onclick=async function(e){e.preventDefault();await api('/api/v1/public/account/verify-email/resend',{method:'POST'});alert('Código reenviado a tu correo.')}}}}
          async function $o(b){var r=await api('/api/v1/public/account/orders');var rows=r.data||[];b.innerHTML='<div class="card" style="padding:18px">'+(rows.length?'<table style="width:100%;font-size:13px;border-collapse:collapse"><tr style="text-align:left;color:#777"><th>Pedido</th><th>Fecha</th><th>Total</th><th>Estado</th><th></th></tr>'+rows.map(function(o){var act=(o.payment_status==='paid')?('<a href="#" onclick="skaReturn(\''+esc(o.uuid)+'\');return false">Devolver</a>'):'';return '<tr><td style="padding:6px 0">#'+esc(o.order_number||String(o.uuid||'').slice(0,8))+'</td><td>'+esc(o.created_at)+'</td><td>'+money((o.total_cents||0)/100)+'</td><td>'+esc((o.payment_status||'')+' / '+(o.fulfillment_status||''))+'</td><td>'+act+'</td></tr>'}).join('')+'</table>':'<div style="color:#777">Aún no tienes pedidos.</div>')+'</div>'}
          window.skaReturn=async function(uuid){var reason=prompt('Motivo de la devolución:');if(!reason)return;var r=await api('/api/v1/public/account/orders/'+encodeURIComponent(uuid)+'/return',{method:'POST',body:JSON.stringify({reason:reason})});alert((r&&(r.message||(r.data&&r.data.message)))||(r&&r.ok?'Solicitud enviada.':'Error'))};
          async function $f(b){var r=await api('/api/v1/public/account/favorites');var rows=r.data||[];b.innerHTML='<div class="card" style="padding:18px">'+(rows.length?rows.map(function(x){var ap=(x.alert_price==null||+x.alert_price)?"checked":"";var asf=(x.alert_stock==null||+x.alert_stock)?"checked":"";return '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid #EEE"><div><a href="'+esc(x.url||'#')+'" style="font-weight:600">'+esc(x.title||x.product_ref)+'</a><div style="font-size:12px;color:#777;margin-top:4px"><label style="margin-right:12px"><input type="checkbox" '+ap+' onchange="skaFavAlert(\''+esc(x.product_ref)+'\',\'alert_price\',this.checked)"> 🔔 Baja de precio</label><label><input type="checkbox" '+asf+' onchange="skaFavAlert(\''+esc(x.product_ref)+'\',\'alert_stock\',this.checked)"> 📦 Vuelve a estar</label></div></div><span>'+money((x.price_cents||0)/100)+' <a href="#" onclick="skaFavDel(\''+esc(x.product_ref)+'\');return false" style="color:#DC2626">✕</a></span></div>'}).join(''):'<div style="color:#777">Sin favoritos.</div>')+'</div>'}
          window.skaFavDel=function(ref){api('/api/v1/public/account/favorites/'+encodeURIComponent(ref),{method:'DELETE'}).then(function(){$f(document.getElementById('ac-body'))})};
          window.skaFavAlert=function(ref,k,v){var o={};o[k]=v;api('/api/v1/public/account/favorites/'+encodeURIComponent(ref)+'/alert',{method:'POST',body:JSON.stringify(o)})};
          function $a(b,list){b.innerHTML='<div class="card" style="padding:18px"><h3>Direcciones</h3>'+(list.length?list.map(function(x){return '<div style="padding:8px 0;border-bottom:1px solid #EEE;font-size:13px"><b>'+esc(x.label||'')+'</b> · '+esc(x.line1||'')+' · '+esc(x.city||'')+'</div>'}).join(''):'<div style="color:#777">Sin direcciones.</div>')+'<h4 style="margin-top:14px">Añadir dirección</h4>'+inp('ad-label','Etiqueta (Casa)')+inp('ad-line1','Dirección')+inp('ad-city','Ciudad')+btn('ad-go','Guardar')+'</div>';document.getElementById('ad-go').onclick=async function(){var r=await api('/api/v1/public/account/addresses',{method:'POST',body:JSON.stringify({label:document.getElementById('ad-label').value,line1:document.getElementById('ad-line1').value,city:document.getElementById('ad-city').value,is_default:1})});if(r.ok)$f2()};
            function $f2(){api('/api/v1/public/account/me').then(function(r){account(r.data)})}}
          function $p(b,c){b.innerHTML='<div class="card" style="padding:18px"><h3>Perfil</h3>'+inp('pf-name','Nombre',null,c.name||'')+inp('pf-phone','Teléfono',null,c.phone||'')+inp('pf-pass','Nueva contraseña (opcional)','password')+btn('pf-go','Guardar')+'<div style="margin-top:16px;border-top:1px solid #EEE;padding-top:12px;font-size:13px"><a href="/api/v1/public/account/export" target="_blank">Descargar mis datos</a> &nbsp;·&nbsp; <a href="#" id="pf-del" style="color:#DC2626">Eliminar mi cuenta</a></div></div>';document.getElementById('pf-go').onclick=async function(){var body={name:document.getElementById('pf-name').value,phone:document.getElementById('pf-phone').value};var p=document.getElementById('pf-pass').value;if(p)body.password=p;var r=await api('/api/v1/public/account/profile',{method:'PUT',body:JSON.stringify(body)});if(r.ok)location.reload()};document.getElementById('pf-del').onclick=async function(e){e.preventDefault();if(!confirm('¿Eliminar tu cuenta y tus datos personales? Es irreversible.'))return;var r=await api('/api/v1/public/account',{method:'DELETE'});if(r.ok)location.search=''}}
          async function $rv(b){var r=await api('/api/v1/public/account/reviews');var rows=r.data||[];b.innerHTML='<div class="card" style="padding:18px"><h3>Mis reseñas</h3>'+(rows.length?rows.map(function(x){var st=x.rating||0;var stars='';for(var i=1;i<=5;i++)stars+='<span style="color:'+(i<=st?'#F5A623':'#DDD')+'">★</span>';var badge=x.status==='approved'?'<span style="color:#1E7A4B">publicada</span>':(x.status==='rejected'?'<span style="color:#DC2626">rechazada</span>':'<span style="color:#B45309">en revisión</span>');return '<div style="padding:8px 0;border-bottom:1px solid #EEE"><b>'+esc(x.title||x.product_ref)+'</b> '+stars+' <small>'+badge+'</small><div style="font-size:13px;color:#555;margin-top:2px">'+esc(x.body||'')+'</div><small style="color:#999">'+esc(String(x.created_at||'').slice(0,10))+'</small></div>'}).join(''):'<div style="color:#777">Aún no has escrito reseñas. Puedes hacerlo desde la página de cada producto.</div>')+'</div>'}
          async function $pts(b){var r=await api('/api/v1/public/account/loyalty');var d=r.data||{};if(!d.enabled){b.innerHTML='<div class="card" style="padding:18px;color:#777">El programa de puntos no está activo.</div>';return}var h=(d.history||[]);b.innerHTML='<div class="card" style="padding:18px"><h3>Mis puntos</h3><p style="font-size:30px;font-weight:700;margin:6px 0">'+(d.balance||0)+' <span style="font-size:14px;font-weight:400;color:#777">puntos</span></p>'+(h.length?'<table style="width:100%;font-size:13px;border-collapse:collapse"><tr style="text-align:left;color:#777"><th>Fecha</th><th>Puntos</th><th>Detalle</th></tr>'+h.map(function(x){return '<tr><td style="padding:5px 0">'+esc(String(x.created_at||'').slice(0,10))+'</td><td>'+(x.points>0?'+':'')+x.points+'</td><td>'+esc(x.note||x.reason)+'</td></tr>'}).join('')+'</table>':'<div style="color:#777">Sin movimientos todavía. Gana puntos con cada compra.</div>')+'</div>'}
          async function $pm(b){var r=await api('/api/v1/public/account/payment-methods');var rows=r.data||[];var L={wompi:'Tarjeta',cod:'Contra entrega',manual:'Pago asistido'};b.innerHTML='<div class="card" style="padding:18px"><h3>Métodos de pago</h3>'+(rows.length?rows.map(function(x){return '<div style="display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #EEE"><div><b>'+esc(x.label||L[x.provider]||x.provider)+'</b> <small style="color:#777">'+esc(L[x.provider]||'')+(x.last4?(' ····'+esc(x.last4)):(x.brand?(' '+esc(x.brand)):''))+'</small>'+(+x.is_default?' <span style="font-size:11px;background:#D9F2E2;color:#1E7A4B;padding:2px 8px;border-radius:20px">Predeterminado</span>':'')+'</div><div>'+(+x.is_default?'':'<a href="#" onclick="skaPmDefault('+x.id+');return false" style="font-size:12px">Predeterminar</a> ')+'<a href="#" onclick="skaPmDel('+x.id+');return false" style="color:#DC2626">✕</a></div></div>'}).join(''):'<div style="color:#777">Sin métodos guardados.</div>')+'<div style="margin-top:14px;border-top:1px solid #EEE;padding-top:12px"><h4>Añadir método</h4><select id="pm-prov" style="padding:9px;border:1px solid #ddd;border-radius:8px"><option value="cod">Contra entrega</option><option value="manual">Pago asistido</option><option value="wompi">Tarjeta (referencia del proveedor)</option></select><input id="pm-label" placeholder="Etiqueta (ej. Casa)" style="display:block;width:100%;box-sizing:border-box;padding:9px;margin:8px 0;border:1px solid #ddd;border-radius:8px"/><label style="font-size:12px"><input type="checkbox" id="pm-def"/> Usar por defecto</label> <button class="btn" id="pm-go" style="margin-left:8px">Guardar</button></div></div>';document.getElementById('pm-go').onclick=async function(){var r=await api('/api/v1/public/account/payment-methods',{method:'POST',body:JSON.stringify({provider:document.getElementById('pm-prov').value,label:document.getElementById('pm-label').value,is_default:document.getElementById('pm-def').checked})});if(r.ok)$pm(document.getElementById('ac-body'));else alert(r.message||(r.data&&r.data.message)||'Error')}}
          window.skaPmDefault=function(id){api('/api/v1/public/account/payment-methods/'+id+'/default',{method:'POST',body:{}}).then(function(){$pm(document.getElementById('ac-body'))})};
          window.skaPmDel=function(id){api('/api/v1/public/account/payment-methods/'+id,{method:'DELETE'}).then(function(){$pm(document.getElementById('ac-body'))})};
          api('/api/v1/public/account/me').then(function(r){if(r&&r.data&&r.data.customer){account(r.data)}else if(qs().tab==='register'){reg()}else{login()}}).catch(function(){login()});
        })();
        </script>
        <?php
        sonika_site_footer();
        return true;
}

return false;
