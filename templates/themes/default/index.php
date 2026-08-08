<?php
use App\Core\Config;
use App\Services\SeoService;
use App\Services\CookieConsentService;
use App\Services\AnalyticsService;
use App\Widgets\WidgetRegistry;

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
    <script src="/ice-pricing.js" defer></script>
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        html{scroll-behavior:smooth}
        body{font-family:'Inter',-apple-system,sans-serif;color:#2F2F2F;background:#F6F6F3;-webkit-font-smoothing:antialiased;overflow-x:hidden}
        .reveal{opacity:0;transform:translateY(30px);transition:opacity .7s ease,transform .7s ease}
        .reveal.visible{opacity:1;transform:translateY(0)}
        .nav{position:fixed;top:0;left:0;right:0;z-index:100;padding:16px 40px;display:flex;align-items:center;justify-content:space-between;background:rgba(246,246,243,.85);backdrop-filter:blur(20px);border-bottom:1px solid rgba(80,80,80,.06)}
        .nav a{color:#6B6B6B;font-size:13px;font-weight:500;text-decoration:none;transition:color .2s}
        .nav a:hover{color:#2F2F2F}
        .btn-primary{display:inline-block;padding:14px 36px;border-radius:12px;border:none;background:linear-gradient(135deg,#9B8CDE,#B89EFF);color:#fff;cursor:pointer;font-size:15px;font-weight:700;text-decoration:none;box-shadow:0 8px 32px rgba(156,140,222,.35);transition:all .2s}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 12px 40px rgba(156,140,222,.45)}
        .btn-outline{display:inline-block;padding:14px 36px;border-radius:12px;border:1px solid rgba(156,140,222,.4);background:transparent;color:#7C3AED;cursor:pointer;font-size:15px;font-weight:600;text-decoration:none;transition:all .2s}
        .btn-outline:hover{background:rgba(156,140,222,.06)}
        .badge{display:inline-block;padding:6px 16px;border-radius:20px;font-size:12px;font-weight:600}
        .card{border-radius:18px;background:#FBFBF9;border:1px solid rgba(80,80,80,.06);box-shadow:0 2px 8px rgba(0,0,0,.03),inset 0 1px 2px rgba(255,255,255,.70);overflow:hidden}
        .card-padded{padding:32px 28px}
        .gradient-text{background:linear-gradient(135deg,#9B8CDE,#B89EFF);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .section{padding:100px 40px;max-width:1100px;margin:0 auto}
        .hero{padding:160px 40px 100px;max-width:1100px;margin:0 auto;text-align:center}
        .grid-3{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:20px}
        .grid-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:24px}
        .footer{padding:48px 40px;border-top:1px solid rgba(80,80,80,.06);background:#FBFBF8}
        .img-swap{position:relative;width:100%;height:180px;border-radius:14px;overflow:hidden;margin-bottom:20px;background:#F3F3EF}
        .img-swap .img-a,.img-swap .img-b{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:opacity .5s ease,transform .5s ease}
        .img-swap .img-b{opacity:0;transform:scale(1.03)}
        .img-swap:hover .img-a{opacity:0;transform:scale(1.03)}
        .img-swap:hover .img-b{opacity:1;transform:scale(1)}
        .hero-bg{position:absolute;top:-120px;left:50%;transform:translateX(-50%);width:800px;height:400px;pointer-events:none;z-index:0;opacity:.4}
        .hero-bg svg{width:100%;height:100%}
        .list-item{display:flex;gap:12px;margin-bottom:16px;align-items:flex-start}
        .list-num{width:22px;height:22px;border-radius:6px;background:#EDE9FE;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px}
        .list-num span{font-size:11px;color:#7C3AED;font-weight:700}
        @media(max-width:768px){
            .nav{padding:12px 20px;flex-wrap:wrap;gap:10px}
            .nav-links{display:none}
            .hero{padding:120px 20px 60px}
            .hero h1{font-size:36px}
            .section{padding:60px 20px}
            .grid-3,.grid-2{grid-template-columns:1fr}
        }
        .img-fallback{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
    </style>
</head>
<body>

<nav class="nav">
  <div style="display:flex;align-items:center;gap:10px">
    <div style="width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#9B8CDE,#B89EFF);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;color:#fff">W</div>
    <span style="font-size:18px;font-weight:700;color:#2F2F2F;letter-spacing:-0.02em">WONTIA</span>
  </div>
  <div class="nav-links" style="display:flex;align-items:center;gap:24px">
    <a href="#que-es">Que es Wontia</a>
    <a href="#aip">AIP</a>
    <a href="#tia">TIA</a>
    <a href="#precios">Precios</a>
    <a href="#funciona">Como funciona</a>
  </div>
  <a href="https://app.wontia.com/login" class="btn-primary" style="padding:9px 22px;font-size:13px;box-shadow:0 4px 16px rgba(156,140,222,.3)">Ingresar</a>
</nav>

<main>
    <?php foreach ($sections as $section):
        if (!empty($section['widget_type']) && WidgetRegistry::get($section['widget_type'])):
            $config = json_decode($section['config'] ?? '{}', true) ?: [];
            echo WidgetRegistry::render($section['widget_type'], $config);
        elseif ($section['type'] === 'custom' || $section['type'] === 'html'):
            echo '<section class="section">' . ($section['content'] ?? '') . '</section>';
        else:
            echo "\n<!-- Section type: " . htmlspecialchars($section['type']) . ' | BRICK: ' . htmlspecialchars($section['widget_type'] ?? 'none') . " -->\n";
            if ($section['content']) echo '<section class="section">' . $section['content'] . '</section>';
        endif;
    endforeach; ?>
</main>

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
