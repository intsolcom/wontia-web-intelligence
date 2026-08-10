<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Config;
use App\Core\Session;

Config::load();
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
    <title>Wontia Admin — Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Inter',sans-serif;background:#0f1117;color:#e1e4ed;display:flex;align-items:center;justify-content:center;min-height:100vh}
        .login-box{background:#1a1d27;padding:48px 40px;border-radius:16px;border:1px solid #2a2d3a;width:100%;max-width:400px}
        .login-box h1{font-size:22px;font-weight:700;margin-bottom:8px}
        .login-box p{font-size:13px;color:#8b8fa3;margin-bottom:28px}
        .login-box label{font-size:12px;color:#8b8fa3;display:block;margin-bottom:6px;font-weight:500}
        .login-box input{width:100%;padding:12px 14px;border-radius:8px;border:1px solid #2a2d3a;background:#0f1117;color:#e1e4ed;font-size:14px;margin-bottom:16px;outline:none;transition:border .2s}
        .login-box input:focus{border-color:#B89EFF}
        .login-box button{width:100%;padding:12px;border-radius:8px;border:none;background:linear-gradient(135deg,#9B8CDE,#B89EFF);color:#fff;font-size:14px;font-weight:600;cursor:pointer;transition:opacity .2s}
        .login-box button:hover{opacity:.9}
        .login-error{color:#ef4444;font-size:12px;margin-bottom:12px;display:none}
        .logo{display:flex;align-items:center;gap:8px;margin-bottom:24px}
        .logo-icon{width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#9B8CDE,#B89EFF);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#fff}
    </style>
</head>
<body>
<div class="login-box">
    <div class="logo"><div class="logo-icon">W</div><span style="font-weight:600;font-size:15px">Wontia Admin</span></div>
    <h1>Welcome back</h1>
    <p>Sign in to manage your website</p>
    <div class="login-error" id="login-error">Invalid credentials</div>
    <form id="login-form">
        <label>Username or Email</label>
        <input type="text" id="login-user" placeholder="admin" required/>
        <label>Password</label>
        <input type="password" id="login-pass" placeholder="Password" required/>
        <button type="submit">Sign in</button>
    </form>
</div>
<script>
document.getElementById('login-form').addEventListener('submit',async function(e){
    e.preventDefault();
    var u=document.getElementById('login-user').value,p=document.getElementById('login-pass').value,e=document.getElementById('login-error');
    try{
        var r=await fetch('/api/v1/admin/auth/login',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({username:u,password:p})});
        var d=await r.json();
        if(d.ok){window.location.reload()}else{e.textContent=d.message||'Invalid credentials';e.style.display='block'}
    }catch(x){e.textContent='Connection error';e.style.display='block'}
});
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
    <title>Wontia Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link rel="stylesheet" href="/assets/css/admin.css"/>
</head>
<body>

<aside class="w-sidebar" id="sidebar">
    <div class="w-sidebar-brand">
        <div class="w-sidebar-logo">W</div>
        <span>Wontia</span>
    </div>
    <nav class="w-sidebar-nav">
        <a href="#dashboard" class="w-nav-item active" data-panel="dashboard">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Dashboard
        </a>
        <a href="#pages" class="w-nav-item" data-panel="pages">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            Pages
        </a>
        <a href="#sections" class="w-nav-item" data-panel="sections">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/></svg>
            Sections
        </a>
        <a href="#bricks" class="w-nav-item" data-panel="bricks">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Bricks
        </a>
        <a href="#brickhub" class="w-nav-item" data-panel="brickhub">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 00-1-1.73l-7-4a2 2 0 00-2 0l-7 4A2 2 0 003 8v8a2 2 0 001 1.73l7 4a2 2 0 002 0l7-4A2 2 0 0021 16z"/><polyline points="3.27 6.96 12 12.01 20.73 6.96"/><line x1="12" y1="22.08" x2="12" y2="12"/></svg>
            BrickHub
            <span id="bh-badge" class="w-badge w-badge-published" style="display:none;margin-left:auto;font-size:10px;padding:2px 8px;border-radius:10px;background:var(--w-accent);color:#fff">0</span>
        </a>
        <a href="#blog" class="w-nav-item" data-panel="blog">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 00-2 2v14a2 2 0 002 2h14a2 2 0 002-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 013 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
            Blog
        </a>
        <a href="#media" class="w-nav-item" data-panel="media">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            Media
        </a>
        <a href="#seo" class="w-nav-item" data-panel="seo">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M2 12h20"/><path d="M12 2a15.3 15.3 0 014 10 15.3 15.3 0 01-4 10"/></svg>
            SEO
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
        <div style="font-size:11px;color:#8b8fa3"><?= htmlspecialchars($user['username'] ?? '') ?> <span style="color:#9B8CDE">(<?= $user['role'] ?? '' ?>)</span></div>
        <a href="#" onclick="wontia.logout();return false" style="font-size:11px;color:#8b8fa3;text-decoration:none">Logout</a>
    </div>
</aside>

<main class="w-main">
    <header class="w-topbar">
        <button class="w-mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')">&#9776;</button>
        <h1 id="panel-title">Dashboard</h1>
        <div style="flex:1"></div>
        <a href="/" target="_blank" style="font-size:12px;color:#B89EFF;text-decoration:none">View Site &#8599;</a>
    </header>
    <div class="w-content" id="wontia-app"></div>
</main>

<div id="w-toast-container" style="position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:8px"></div>
<div class="w-modal-overlay" id="w-modal" style="display:none" onclick="if(event.target===this)wontia.closeModal()"><div class="w-modal" id="w-modal-content"></div></div>

<script src="/assets/js/admin.js"></script>
</body>
</html>
