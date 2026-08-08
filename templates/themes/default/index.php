<?php
use App\Core\Config;
use App\Core\Session;
use App\Services\SeoService;
use App\Services\CookieConsentService;
use App\Services\AnalyticsService;

$page = $page ?? ['title' => Config::get('site_name', 'Wontia'), 'meta_title' => '', 'meta_description' => '', 'slug' => ''];
$sections = $sections ?? [];
$pageMeta = array_merge($page, ['meta_title' => $page['meta_title'] ?: $page['title']]);

AnalyticsService::track($_SERVER['REQUEST_URI'], $_SERVER['HTTP_REFERER'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?= SeoService::metaTags($pageMeta) ?>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='10' fill='%239B8CDE'/%3E%3Ctext x='16' y='23' font-family='sans-serif' font-size='20' font-weight='800' fill='white' text-anchor='middle'%3EW%3C/text%3E%3C/svg%3E" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        html{scroll-behavior:smooth}
        body{font-family:'Inter',-apple-system,sans-serif;color:#2F2F2F;background:#F6F6F3;-webkit-font-smoothing:antialiased;overflow-x:hidden}
        .reveal{opacity:0;transform:translateY(30px);transition:opacity .7s ease,transform .7s ease}
        .reveal.visible{opacity:1;transform:translateY(0)}
        .nav{position:fixed;top:0;left:0;right:0;z-index:100;padding:16px 40px;display:flex;align-items:center;justify-content:space-between;background:rgba(246,246,243,.85);backdrop-filter:blur(20px);border-bottom:1px solid rgba(80,80,80,.06)}
        .nav a{color:#6B6B6B;font-size:13px;font-weight:500;text-decoration:none;transition:color .2s}
        .nav a:hover{color:#2F2F2F}
        .section{padding:100px 40px;max-width:1100px;margin:0 auto}
        @media(max-width:768px){.section{padding:60px 20px}}
    </style>
</head>
<body>

<nav class="nav">
    <div style="display:flex;align-items:center;gap:10px">
        <div style="width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#9B8CDE,#B89EFF);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;color:#fff">W</div>
        <span style="font-size:18px;font-weight:700;color:#2F2F2F;letter-spacing:-0.02em">WONTIA</span>
    </div>
    <div style="display:flex;align-items:center;gap:24px">
        <a href="/">Home</a>
        <a href="/blog">Blog</a>
    </div>
</nav>

<main>
    <?php foreach ($sections as $section): ?>
        <?php if ($section['type'] === 'hero'): ?>
            <section class="section" style="padding-top:160px;text-align:center">
                <h1 style="font-size:56px;font-weight:800;color:#1A1A1E;line-height:1.15;letter-spacing:-0.03em;margin-bottom:20px"><?= htmlspecialchars($section['title']) ?></h1>
                <p style="font-size:18px;color:#6B6B6B;line-height:1.7;max-width:680px;margin:0 auto"><?= htmlspecialchars($section['subtitle'] ?? '') ?></p>
                <?= $section['content'] ?>
            </section>
        <?php elseif ($section['type'] === 'features'): ?>
            <section class="section">
                <h2 style="text-align:center;font-size:34px;font-weight:800;margin-bottom:16px"><?= htmlspecialchars($section['title']) ?></h2>
                <p style="text-align:center;font-size:15px;color:#6B6B6B;margin-bottom:48px"><?= htmlspecialchars($section['subtitle'] ?? '') ?></p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px">
                    <?= $section['content'] ?>
                </div>
            </section>
        <?php elseif ($section['type'] === 'cta'): ?>
            <section class="section" style="text-align:center;padding:120px 40px">
                <h2 style="font-size:36px;font-weight:800;color:#1A1A1E;margin-bottom:16px"><?= htmlspecialchars($section['title']) ?></h2>
                <p style="font-size:16px;color:#6B6B6B;margin-bottom:32px"><?= htmlspecialchars($section['subtitle'] ?? '') ?></p>
                <?= $section['content'] ?>
            </section>
        <?php elseif ($section['type'] === 'custom'): ?>
            <section class="section"><?= $section['content'] ?></section>
        <?php else: ?>
            <section class="section">
                <?php if ($section['title']): ?><h2><?= htmlspecialchars($section['title']) ?></h2><?php endif; ?>
                <?= $section['content'] ?>
            </section>
        <?php endif; ?>
    <?php endforeach; ?>
</main>

<footer style="padding:48px 40px;border-top:1px solid rgba(80,80,80,.06);background:#FBFBF8;text-align:center;font-size:12px;color:#9A9A9A">
    &copy; <?= date('Y') ?> <?= htmlspecialchars(Config::get('site_name', 'Wontia')) ?>. Powered by Wontia Web Intelligence.
</footer>

<?= CookieConsentService::render() ?>

<script>
(function(){
    var r=document.querySelectorAll('.reveal');
    var o=new IntersectionObserver(function(e){e.forEach(function(e){if(e.isIntersecting)e.target.classList.add('visible')})},{threshold:0.1,rootMargin:'0px 0px -40px 0px'});
    r.forEach(function(el){o.observe(el)});
})();
</script>

</body>
</html>
