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
            .nav-cta{display:none}
            .nav-hamburger{display:flex}
            .hero{padding:120px 20px 60px}
            .hero h1{font-size:36px}
            .section{padding:60px 20px}
            .grid-3,.grid-2{grid-template-columns:1fr}
        }
        .img-fallback{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
        .nav-item{position:relative}
        .nav-sol{display:flex;align-items:center;gap:5px;background:none;border:none;padding:0;cursor:pointer;color:#6B6B6B;font-size:13px;font-weight:500;font-family:inherit;transition:color .2s}
        .nav-sol:hover{color:#2F2F2F}
        .nav-chev{font-size:9px;transition:transform .2s}
        .nav-item.open .nav-chev{transform:rotate(180deg)}
        .nav-dropdown{position:absolute;top:calc(100% + 10px);left:50%;transform:translate(-50%,8px);background:#FBFBF9;border:1px solid rgba(80,80,80,.08);border-radius:14px;box-shadow:0 16px 48px rgba(31,31,41,.12);min-width:300px;padding:10px;opacity:0;visibility:hidden;transition:opacity .2s,transform .2s,visibility .2s;z-index:200}
        .nav-item:hover .nav-dropdown,.nav-item:focus-within .nav-dropdown,.nav-item.open .nav-dropdown{opacity:1;visibility:visible;transform:translate(-50%,0)}
        .nd-item{display:flex;align-items:center;gap:8px;padding:9px 12px;border-radius:8px;font-size:12.5px;color:#4A4A4A;text-decoration:none;font-weight:500;transition:background .15s}
        .nd-item:hover{background:#F3F3EF}
        .nd-child{padding-left:28px}
        .nd-dot{width:6px;height:6px;border-radius:50%;flex-shrink:0}
        .nd-badge{margin-left:auto;font-size:8px;font-weight:700;letter-spacing:.05em;padding:2px 8px;border-radius:10px;flex-shrink:0}
        .nd-sep{height:1px;background:rgba(80,80,80,.08);margin:6px 4px}
        .nav-hamburger{display:none;background:none;border:none;cursor:pointer;padding:8px;flex-direction:column;gap:4px}
        .nav-hamburger span{display:block;width:20px;height:2px;background:#2F2F2F;border-radius:2px}
        .nav-mobile{display:none;position:absolute;top:100%;left:0;right:0;background:#FBFBF9;border-bottom:1px solid rgba(80,80,80,.08);box-shadow:0 20px 40px rgba(31,31,41,.1);padding:14px 20px 24px;flex-direction:column}
        .nav-mobile.open{display:flex}
        .nav-mobile a{color:#4A4A4A;font-size:14px;font-weight:500;text-decoration:none;padding:9px 4px;border-bottom:1px solid rgba(80,80,80,.05);display:flex;align-items:center;gap:8px}
        .nav-mobile a.nm-child{padding-left:22px;font-size:13px}
        .nm-title{font-size:10px;font-weight:700;color:#9A9A9A;text-transform:uppercase;letter-spacing:.1em;margin:14px 4px 2px}
    </style>
<?php
$brandStmt = $db->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'wwi_brand_primary'");
$brandStmt->execute();
$brandPrimary = $brandStmt->fetchColumn();
if ($brandPrimary && preg_match('/^#[0-9a-fA-F]{6}$/', (string)$brandPrimary)):
?>
    <style>
        .btn-primary{background:<?= $brandPrimary ?> !important}
        .gradient-text{background:<?= $brandPrimary ?> !important;-webkit-background-clip:text !important;background-clip:text !important}
        .hero-badge,.badge{background:<?= $brandPrimary ?>22 !important;color:<?= $brandPrimary ?> !important}
        .nav-logo,.w-sidebar-logo{background:<?= $brandPrimary ?> !important}
    </style>
<?php endif; ?>
</head>
<body>

<nav class="nav">
  <div style="display:flex;align-items:center;gap:10px">
    <div style="width:32px;height:32px;border-radius:10px;background:linear-gradient(135deg,#9B8CDE,#B89EFF);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:16px;color:#fff">W</div>
    <span style="font-size:18px;font-weight:700;color:#2F2F2F;letter-spacing:-0.02em">WONTIA</span>
  </div>
  <div class="nav-links" style="display:flex;align-items:center;gap:22px">
    <a href="#ais-concept">AIS</a>
    <a href="#tia-command">TIA</a>
    <div class="nav-item">
      <button type="button" class="nav-sol" aria-haspopup="true" aria-expanded="false">Soluciones <span class="nav-chev">&#9662;</span></button>
      <div class="nav-dropdown">
        <a href="#business" class="nd-item"><span class="nd-dot" style="background:#4A9E6E"></span>Wontia Business<span class="nd-badge" style="background:#D9F2E2;color:#4A9E6E">AVAILABLE</span></a>
        <a href="https://app.wontia.com/login" class="nd-item nd-child"><span class="nd-dot" style="background:#4A9E6E"></span>Wontia AIP<span class="nd-badge" style="background:#D9F2E2;color:#4A9E6E">AVAILABLE</span></a>
        <a href="#domain-arch" class="nd-item nd-child"><span class="nd-dot" style="background:#4A9E6E"></span>Wontia Web Intelligence<span class="nd-badge" style="background:#D9F2E2;color:#4A9E6E">AVAILABLE</span></a>
        <div class="nd-sep"></div>
        <a href="#food-security" class="nd-item"><span class="nd-dot" style="background:#D4A54A"></span>Wontia Food Security<span class="nd-badge" style="background:#F7E8C8;color:#D4A54A">IN DEVELOPMENT</span></a>
        <div class="nd-sep"></div>
        <a href="#domain-arch" class="nd-item"><span class="nd-dot" style="background:#7C3AED"></span>Wontia Health<span class="nd-badge" style="background:#EDE9FE;color:#7C3AED">FUTURE</span></a>
        <a href="#domain-arch" class="nd-item"><span class="nd-dot" style="background:#7C3AED"></span>Wontia Agriculture<span class="nd-badge" style="background:#EDE9FE;color:#7C3AED">FUTURE</span></a>
        <a href="#domain-arch" class="nd-item"><span class="nd-dot" style="background:#7C3AED"></span>Wontia Industry<span class="nd-badge" style="background:#EDE9FE;color:#7C3AED">FUTURE</span></a>
        <a href="#domain-arch" class="nd-item"><span class="nd-dot" style="background:#7C3AED"></span>Wontia Logistics<span class="nd-badge" style="background:#EDE9FE;color:#7C3AED">FUTURE</span></a>
        <a href="#domain-arch" class="nd-item"><span class="nd-dot" style="background:#7C3AED"></span>Wontia Education<span class="nd-badge" style="background:#EDE9FE;color:#7C3AED">FUTURE</span></a>
      </div>
    </div>
  </div>
  <div style="display:flex;align-items:center;gap:12px">
    <a href="https://app.wontia.com/login" class="btn-primary nav-cta" style="padding:9px 22px;font-size:13px;box-shadow:0 4px 16px rgba(156,140,222,.3)">Ingresar</a>
    <button type="button" class="nav-hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
  <div class="nav-mobile" id="navMobile">
    <a href="#ais-concept">AIS</a>
    <a href="#tia-command">TIA</a>
    <div class="nm-title">Soluciones</div>
    <a href="#business"><span class="nd-dot" style="background:#4A9E6E"></span>Wontia Business<span class="nd-badge" style="margin-left:auto;background:#D9F2E2;color:#4A9E6E">AVAILABLE</span></a>
    <a href="https://app.wontia.com/login" class="nm-child"><span class="nd-dot" style="background:#4A9E6E"></span>Wontia AIP<span class="nd-badge" style="margin-left:auto;background:#D9F2E2;color:#4A9E6E">AVAILABLE</span></a>
    <a href="#domain-arch" class="nm-child"><span class="nd-dot" style="background:#4A9E6E"></span>Wontia Web Intelligence<span class="nd-badge" style="margin-left:auto;background:#D9F2E2;color:#4A9E6E">AVAILABLE</span></a>
    <a href="#food-security"><span class="nd-dot" style="background:#D4A54A"></span>Wontia Food Security<span class="nd-badge" style="margin-left:auto;background:#F7E8C8;color:#D4A54A">IN DEVELOPMENT</span></a>
    <a href="#domain-arch"><span class="nd-dot" style="background:#7C3AED"></span>Wontia Health<span class="nd-badge" style="margin-left:auto;background:#EDE9FE;color:#7C3AED">FUTURE</span></a>
    <a href="#domain-arch"><span class="nd-dot" style="background:#7C3AED"></span>Wontia Agriculture<span class="nd-badge" style="margin-left:auto;background:#EDE9FE;color:#7C3AED">FUTURE</span></a>
    <a href="#domain-arch"><span class="nd-dot" style="background:#7C3AED"></span>Wontia Industry<span class="nd-badge" style="margin-left:auto;background:#EDE9FE;color:#7C3AED">FUTURE</span></a>
    <a href="#domain-arch"><span class="nd-dot" style="background:#7C3AED"></span>Wontia Logistics<span class="nd-badge" style="margin-left:auto;background:#EDE9FE;color:#7C3AED">FUTURE</span></a>
    <a href="#domain-arch"><span class="nd-dot" style="background:#7C3AED"></span>Wontia Education<span class="nd-badge" style="margin-left:auto;background:#EDE9FE;color:#7C3AED">FUTURE</span></a>
    <a href="https://app.wontia.com/login" class="btn-primary" style="margin-top:18px;text-align:center;padding:12px 20px">Ingresar</a>
  </div>
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
    var items=document.querySelectorAll('.nav-item');
    items.forEach(function(it){
        var btn=it.querySelector('.nav-sol');
        if(btn)btn.addEventListener('click',function(e){e.stopPropagation();it.classList.toggle('open');btn.setAttribute('aria-expanded',it.classList.contains('open')?'true':'false')});
    });
    document.addEventListener('click',function(){items.forEach(function(it){it.classList.remove('open');var b=it.querySelector('.nav-sol');if(b)b.setAttribute('aria-expanded','false')})});
    var ham=document.querySelector('.nav-hamburger'),mob=document.getElementById('navMobile');
    if(ham&&mob){
        ham.addEventListener('click',function(e){e.stopPropagation();mob.classList.toggle('open')});
        mob.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){mob.classList.remove('open')})});
    }
})();
</script>

</body>
</html>
