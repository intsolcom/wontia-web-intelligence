<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Session;

Config::load();
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
Session::start();

$loggedIn = Session::isLoggedIn();
$user = Session::user();

if (!$loggedIn):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>WWI Wontia Web Intelligence — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"/>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#06080f;color:#e6edf7;display:flex;align-items:center;justify-content:center;min-height:100vh;-webkit-font-smoothing:antialiased}
        body::before{content:'';position:fixed;inset:0;pointer-events:none;background:radial-gradient(42% 50% at 15% 0%,rgba(34,211,238,.08),transparent 60%),radial-gradient(45% 45% at 88% 10%,rgba(139,92,246,.1),transparent 60%)}
        .login-box{position:relative;background:#0d1220;padding:48px 40px;border-radius:16px;border:1px solid rgba(148,163,184,.18);width:100%;max-width:400px;box-shadow:0 30px 90px rgba(0,0,0,.55)}
        .login-box h1{font-size:22px;font-weight:800;margin-bottom:8px;letter-spacing:-.01em}
        .login-box p{font-size:13px;color:#8593ab;margin-bottom:28px}
        .login-box label{font-size:12px;color:#8593ab;display:block;margin-bottom:6px;font-weight:500}
        .login-box input{width:100%;padding:12px 14px;border-radius:8px;border:1px solid rgba(148,163,184,.22);background:#0a0e18;color:#e6edf7;font-size:14px;margin-bottom:16px;outline:none;transition:border .2s,box-shadow .2s}
        .login-box input:focus{border-color:#22d3ee;box-shadow:0 0 0 3px rgba(34,211,238,.12)}
        .login-box button{width:100%;padding:12px;border-radius:8px;border:none;background:linear-gradient(120deg,#22d3ee,#8b5cf6);color:#041018;font-size:14px;font-weight:700;cursor:pointer;transition:filter .2s}
        .login-box button:hover{filter:brightness(1.1)}
        .login-box button.ghost{background:transparent;border:1px solid rgba(148,163,184,.28);color:#a9b6c9}
        .login-error{color:#f87171;font-size:12px;margin-bottom:12px;display:none}
        .login-ok{color:#34d399;font-size:12px;margin-bottom:12px;display:none}
        .login-alt{text-align:center;margin-top:16px}
        .login-alt a{font-size:12px;color:#67e8f9;text-decoration:none}
        .login-alt a:hover{text-decoration:underline}
        .otp-input{letter-spacing:8px;text-align:center;font-family:'JetBrains Mono',Consolas,monospace;font-size:20px}
        .logo{display:flex;align-items:center;gap:8px;margin-bottom:24px}
        .logo-icon{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#22d3ee,#8b5cf6);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:13px;color:#041018}
    </style>
</head>
<body>
<div class="login-box" id="login-box">
    <div class="logo"><div class="logo-icon">W</div><span style="font-weight:600;font-size:15px">WWI Wontia Web Intelligence</span></div>
    <h1>Welcome back</h1>
    <p>Sign in to manage your website</p>
    <div class="login-error" id="login-error">Invalid credentials</div>
    <div class="login-ok" id="login-ok"></div>
    <form id="login-form">
        <label>Username or Email</label>
        <input type="text" id="login-user" placeholder="admin" autocomplete="username" required/>
        <label>Password</label>
        <input type="password" id="login-pass" placeholder="Password" autocomplete="current-password" required/>
        <button type="submit">Sign in</button>
    </form>
    <div class="login-alt"><a href="#" id="forgot-link">¿Olvidaste tu contraseña?</a></div>
</div>

<div class="login-box" id="twofa-box" style="display:none">
    <div class="logo"><div class="logo-icon">W</div><span style="font-weight:600;font-size:15px">Verificación en dos pasos</span></div>
    <h1>Código de seguridad</h1>
    <p>Abre Google Authenticator e introduce el código de 6 dígitos.</p>
    <div class="login-error" id="twofa-error">Código incorrecto</div>
    <form id="twofa-form">
        <input type="hidden" id="twofa-challenge"/>
        <input type="hidden" id="twofa-recovery-mode" value="0"/>
        <label id="twofa-code-label">Código de autenticación</label>
        <input type="text" id="twofa-code" class="otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="9" placeholder="000000" required/>
        <button type="submit">Verificar</button>
    </form>
    <div class="login-alt"><a href="#" id="twofa-recovery-toggle">Usar código de recuperación</a></div>
    <div class="login-alt"><a href="#" id="twofa-cancel">Volver al inicio de sesión</a></div>
</div>

<div class="login-box" id="forgot-box" style="display:none">
    <div class="logo"><div class="logo-icon">W</div><span style="font-weight:600;font-size:15px">Recuperar acceso</span></div>
    <h1>Recuperar contraseña</h1>
    <p>Te enviaremos un código de verificación a tu correo registrado.</p>
    <div class="login-error" id="forgot-error">Error</div>

    <form id="forgot-step1">
        <label>Usuario o correo</label>
        <input type="text" id="forgot-login" placeholder="usuario o correo registrado" required/>
        <button type="submit">Enviar código</button>
    </form>

    <form id="forgot-step2" style="display:none">
        <div class="login-error" id="forgot-error2">Código inválido o expirado</div>
        <label>Código de verificación (6 dígitos)</label>
        <input type="text" id="otp-code" class="otp-input" inputmode="numeric" autocomplete="one-time-code" maxlength="6" placeholder="000000" required/>
        <button type="submit">Verificar código</button>
    </form>

    <form id="forgot-step3" style="display:none">
        <div class="login-error" id="forgot-error3">Error</div>
        <input type="hidden" id="reset-token"/>
        <label>Nueva contraseña</label>
        <input type="password" id="reset-pass" autocomplete="new-password" placeholder="Mín. 10, mayúscula, minúscula y número" required/>
        <label>Confirmar contraseña</label>
        <input type="password" id="reset-pass2" autocomplete="new-password" placeholder="Repite la contraseña" required/>
        <button type="submit">Restablecer contraseña</button>
    </form>

    <div class="login-alt"><a href="#" id="forgot-back">Volver al inicio de sesión</a></div>
</div>
<script>
(function(){
    var $=function(id){return document.getElementById(id)};
    function api(url,body){
        return fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(body||{})})
            .then(function(r){return r.json().then(function(d){return {status:r.status,body:d}})})
            .catch(function(){return {status:0,body:{message:'Connection error. Intenta de nuevo.'}}});
    }
    function err(id,msg){var e=$(id);e.textContent=msg;e.style.display='block'}
    function hideErr(id){var e=$(id);if(e)e.style.display='none'}
    function box(id,show){$(id).style.display=show?'block':'none'}
    function done(d){try{localStorage.setItem('wwi_token',d.token||'')}catch(x){}window.location.reload()}

    $('login-form').addEventListener('submit',async function(e){
        e.preventDefault();
        hideErr('login-error');
        var r=await api('/api/v1/admin/auth/login',{username:$('login-user').value,password:$('login-pass').value});
        var d=r.body;
        if(d.ok){done(d);return}
        if(d.twofa_required){$('twofa-challenge').value=d.challenge||'';box('login-box',false);box('twofa-box',true);$('twofa-code').focus();return}
        err('login-error',d.message||'Invalid credentials');
    });

    $('twofa-form').addEventListener('submit',async function(e){
        e.preventDefault();
        hideErr('twofa-error');
        var r=await api('/api/v1/admin/auth/2fa/verify',{challenge:$('twofa-challenge').value,code:$('twofa-code').value,recovery:$('twofa-recovery-mode').value==='1'});
        var d=r.body;
        if(d.ok){done(d);return}
        err('twofa-error',d.message||'Código incorrecto');
    });
    $('twofa-recovery-toggle').addEventListener('click',function(e){
        e.preventDefault();
        var m=$('twofa-recovery-mode');m.value=m.value==='1'?'0':'1';
        var rec=m.value==='1';
        $('twofa-code-label').textContent=rec?'Código de recuperación':'Código de autenticación';
        $('twofa-code').setAttribute('inputmode',rec?'text':'numeric');
        $('twofa-code').setAttribute('maxlength',rec?'9':'6');
        $('twofa-code').value='';
        this.textContent=rec?'Usar código de autenticación':'Usar código de recuperación';
    });
    $('twofa-cancel').addEventListener('click',function(e){e.preventDefault();box('twofa-box',false);box('login-box',true);$('twofa-code').value=''});

    $('forgot-link').addEventListener('click',function(e){e.preventDefault();box('login-box',false);box('forgot-box',true);$('forgot-login').focus()});
    $('forgot-back').addEventListener('click',function(e){e.preventDefault();box('forgot-box',false);box('login-box',true)});

    $('forgot-step1').addEventListener('submit',async function(e){
        e.preventDefault();
        hideErr('forgot-error');
        var r=await api('/api/v1/admin/auth/forgot',{login:$('forgot-login').value});
        if(r.status===429){err('forgot-error',r.body.message||'Demasiadas solicitudes.');return}
        box('forgot-step1',false);box('forgot-step2',true);$('otp-code').focus();
    });
    $('forgot-step2').addEventListener('submit',async function(e){
        e.preventDefault();
        hideErr('forgot-error2');
        var r=await api('/api/v1/admin/auth/verify-otp',{login:$('forgot-login').value,code:$('otp-code').value});
        var d=r.body;
        if(!d.ok){err('forgot-error2',d.message||'Código inválido o expirado');return}
        $('reset-token').value=d.reset_token||'';
        box('forgot-step2',false);box('forgot-step3',true);$('reset-pass').focus();
    });
    $('forgot-step3').addEventListener('submit',async function(e){
        e.preventDefault();
        hideErr('forgot-error3');
        var r=await api('/api/v1/admin/auth/reset-password',{token:$('reset-token').value,password:$('reset-pass').value,password_confirm:$('reset-pass2').value});
        var d=r.body;
        if(!d.ok){err('forgot-error3',d.message||'Error');return}
        box('forgot-box',false);box('login-box',true);
        hideErr('login-error');
        var ok=$('login-ok');ok.textContent='Contraseña actualizada. Inicia sesión con tu nueva contraseña.';ok.style.display='block';
        $('login-pass').value='';$('login-pass').focus();
    });
})();
</script>
</body>
</html>
<?php
exit;
endif;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1.0"/>
    <title>WWI Wontia Web Intelligence</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="/assets/css/admin.css?v=<?= filemtime(__DIR__ . '/assets/css/admin.css') ?>"/>
</head>
<body>

<aside class="w-sidebar" id="sidebar">
    <div class="w-sidebar-brand">
        <div class="w-sidebar-logo">W</div>
        <span>WWI Wontia</span>
    </div>
    <nav class="w-sidebar-nav">
        <a href="#dashboard" class="w-nav-item active" data-panel="dashboard">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Dashboard
        </a>
        <?php if ((($user['role'] ?? '') === 'superadmin') && is_dir('/app/deploy-queue')): ?>
        <a href="#wwi" class="w-nav-item" data-panel="wwi">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/></svg>
            WWI — Sistema
        </a>
        <?php endif; ?>
        <a href="#pages" class="w-nav-item" data-panel="pages">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Pages
        </a>
        <a href="#bricks" class="w-nav-item" data-panel="bricks">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Bricks
            <span id="bh-badge" class="w-badge w-badge-published" style="display:none;margin-left:auto;font-size:10px;padding:2px 8px;border-radius:10px;background:linear-gradient(120deg,#22d3ee,#8b5cf6);color:#041018;font-weight:700">0</span>
        </a>
        <?php if (($user['role'] ?? '') === 'client'): ?>
        <a href="#portal" class="w-nav-item" data-panel="portal">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
            Mi Portal
        </a>
        <?php endif; ?>
        <a href="#store" class="w-nav-item" data-panel="store">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 002 1.61h9.72a2 2 0 002-1.61L23 6H6"/></svg>
            Tienda
        </a>
        <a href="#blog" class="w-nav-item" data-panel="blog">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Blog
        </a>
        <a href="#media" class="w-nav-item" data-panel="media">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            Media
        </a>
        <a href="#banners" class="w-nav-item" data-panel="banners">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l18-8v18l-18-8z"/><path d="M11.6 16.8a3 3 0 1 1-5.8-1.6"/></svg>
            Banners
        </a>
        <a href="#analytics" class="w-nav-item" data-panel="analytics">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
            Analytics
        </a>
        <a href="#settings" class="w-nav-item" data-panel="settings">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/></svg>
            Settings
        </a>
        <?php if ($user['role'] === 'superadmin'): ?>
        <a href="#users" class="w-nav-item" data-panel="users">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg>
            Users
        </a>
        <?php endif; ?>
    </nav>
    <div class="w-sidebar-footer">
        <div style="font-size:11px;color:#8593ab"><?= htmlspecialchars($user['username'] ?? '') ?> <span style="color:#67e8f9">(<?= $user['role'] ?? '' ?>)</span></div>
        <a href="#" onclick="wontia.logout();return false" style="font-size:11px;color:#8593ab;text-decoration:none">Logout</a>
    </div>
</aside>

<main class="w-main">
    <header class="w-topbar">
        <button class="w-mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">&#9776;</button>
        <h1 id="panel-title">Dashboard</h1>
        <div style="flex:1"></div>
        <?php if (($user['role'] ?? '') === 'superadmin' && is_dir('/app/deploy-queue')): ?>
        <button onclick="wontia.factoryWizard()" title="Nuevo sitio" style="background:none;border:1px solid rgba(148,163,184,.3);border-radius:8px;color:#67e8f9;font-size:12px;font-weight:600;padding:6px 12px;margin-right:12px;cursor:pointer">+ Nuevo sitio</button>
        <button id="w-bridge-bell" onclick="wontia.agentBridgeOpen()" title="Canal de agentes" style="position:relative;background:none;border:none;cursor:pointer;font-size:16px;color:#8593ab;margin-right:14px">&#128276;<span id="w-bridge-badge" style="display:none;position:absolute;top:-6px;right:-10px;min-width:16px;height:16px;padding:0 4px;border-radius:999px;background:#22d3ee;color:#041018;font-size:10px;font-weight:800;line-height:16px;text-align:center"></span></button>
        <?php endif; ?>
        <a href="/" target="_blank" style="font-size:12px;color:#67e8f9;text-decoration:none">View Site &#8599;</a>
    </header>
    <div class="w-content" id="wontia-app"></div>
</main>

<div id="w-toast-container" style="position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px"></div>
<div class="w-modal-overlay" id="w-modal" style="display:none" onclick="if(event.target===this)wontia.closeModal()"><div class="w-modal" id="w-modal-content"></div></div>

<script>window.__WWI_VER='<?= filemtime(__DIR__ . '/assets/js/admin.js') ?>';window.__WWI_IS_CENTRAL=<?= is_dir('/app/deploy-queue') ? 'true' : 'false' ?>;</script>
<script src="/assets/js/admin.js?v=<?= filemtime(__DIR__ . '/assets/js/admin.js') ?>"></script>
</body>
</html>
