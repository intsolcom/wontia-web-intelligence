<?php
use App\Core\Config;
use App\Services\SeoService;
use App\Services\CookieConsentService;
use App\Services\AnalyticsService;
use App\Widgets\WidgetRegistry;

require_once ROOT_DIR . '/templates/themes/_shared/brand.php';

$page = $page ?? ['title' => Config::get('site_name', 'INTSOLCOM'), 'meta_title' => '', 'meta_description' => '', 'slug' => ''];
$sections = $sections ?? [];
$pageMeta = array_merge($page, ['meta_title' => $page['meta_title'] ?: $page['title']]);

$brand = wwi_brand();

AnalyticsService::track($_SERVER['REQUEST_URI'], $_SERVER['HTTP_REFERER'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

$navItems = [['text' => 'Technology', 'url' => '/technology'], ['text' => 'Nearshore Dev', 'url' => '/nearshore-development'], ['text' => 'Business Units', 'url' => '/business-units'], ['text' => 'Contact', 'url' => '/contact', 'cta' => true]];
try {
    $navStmt = $db->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'nav_items' LIMIT 1");
    $navStmt->execute();
    $navRaw = $navStmt->fetchColumn();
    if ($navRaw) {
        $navJson = json_decode((string)$navRaw, true);
        if (is_array($navJson) && !empty($navJson)) $navItems = $navJson;
    }
} catch (\Throwable $e) {}

$navLinks = '';
$navMobile = '';
foreach ($navItems as $item) {
    $label = htmlspecialchars((string)($item['text'] ?? ''), ENT_QUOTES, 'UTF-8');
    $url = htmlspecialchars((string)($item['url'] ?? '#'), ENT_QUOTES, 'UTF-8');
    if (!empty($item['cta'])) {
        $navLinks .= '<a href="' . $url . '" class="btn-primary nav-cta" style="padding:9px 22px;font-size:13px">' . $label . '</a>';
        $navMobile .= '<a href="' . $url . '" class="btn-primary" style="margin-top:18px;text-align:center;padding:12px 20px">' . $label . '</a>';
    } else {
        $navLinks .= '<a href="' . $url . '" class="navlink">' . $label . '</a>';
        $navMobile .= '<a href="' . $url . '">' . $label . '</a>';
    }
}

$brandStmt = $db->prepare("SELECT `value` FROM settings WHERE site_id = @site_id AND `key` = 'wwi_brand_primary'");
$brandStmt->execute();
$brandPrimary = $brandStmt->fetchColumn();
if (!$brandPrimary || !preg_match('/^#[0-9a-fA-F]{6}$/', (string)$brandPrimary)) $brandPrimary = '#00C896';
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?= SeoService::metaTags($pageMeta) ?>
    <?= wwi_favicon_links() ?: '<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 32 32\'%3E%3Crect width=\'32\' height=\'32\' rx=\'10\' fill=\'%2300C896\'/%3E%3Ctext x=\'16\' y=\'23\' font-family=\'sans-serif\' font-size=\'20\' font-weight=\'800\' fill=\'white\' text-anchor=\'middle\'%3EI%3C/text%3E%3C/svg%3E" />' ?>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet" />
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
        html{scroll-behavior:smooth}
        body{font-family:'Inter',-apple-system,sans-serif;color:#2F2F2F;background:#FFFFFF;-webkit-font-smoothing:antialiased;overflow-x:hidden}
        .reveal{opacity:0;transform:translateY(30px);transition:opacity .7s ease,transform .7s ease}
        .reveal.visible{opacity:1;transform:translateY(0)}
        .nav{position:fixed;top:0;left:0;right:0;z-index:100;padding:14px 40px;display:flex;align-items:center;justify-content:space-between;background:rgba(255,255,255,.92);backdrop-filter:blur(20px);border-bottom:1px solid rgba(15,23,42,.06)}
        .nav a{color:#475569;font-size:13.5px;font-weight:500;text-decoration:none;transition:color .2s}
        .nav a:hover{color:#0F172A}
        .nav a.navlink{position:relative;padding:6px 2px}
        .nav a.navlink:hover{color:<?= $brandPrimary ?>}
        .btn-primary{display:inline-block;padding:14px 36px;border-radius:12px;border:none;background:<?= $brandPrimary ?>;color:#fff;cursor:pointer;font-size:15px;font-weight:700;text-decoration:none;box-shadow:0 8px 32px rgba(0,200,150,.3);transition:all .2s}
        .btn-primary:hover{transform:translateY(-2px);box-shadow:0 12px 40px rgba(0,200,150,.42)}
        .btn-outline{display:inline-block;padding:14px 36px;border-radius:12px;border:1px solid rgba(0,200,150,.45);background:transparent;color:#00A67D;cursor:pointer;font-size:15px;font-weight:600;text-decoration:none;transition:all .2s}
        .btn-outline:hover{background:rgba(0,200,150,.07)}
        .badge{display:inline-block;padding:6px 16px;border-radius:20px;font-size:12px;font-weight:600}
        .card{border-radius:16px;background:#FFFFFF;border:1px solid #E2E8F0;box-shadow:0 1px 3px rgba(15,23,42,.04);overflow:hidden}
        .card-padded{padding:28px 24px}
        .gradient-text{background:linear-gradient(135deg,<?= $brandPrimary ?>,#2563EB);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
        .section{padding:72px 40px;max-width:1100px;margin:0 auto}
        .hero{padding:150px 40px 90px;max-width:1100px;margin:0 auto;text-align:center}
        .grid-3{display:grid;grid-template-columns:repeat(auto-fit,minmax(290px,1fr));gap:18px}
        .grid-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:22px}
        .footer{padding:48px 40px;border-top:1px solid rgba(15,23,42,.06);background:#0F172A;color:#94A3B8}
        .footer a{color:#94A3B8;text-decoration:none}
        .footer a:hover{color:<?= $brandPrimary ?>}
        .img-swap{position:relative;width:100%;height:150px;border-radius:12px;overflow:hidden;margin-bottom:18px;background:#F1F5F9}
        .img-swap .img-a,.img-swap .img-b{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;transition:opacity .5s ease,transform .5s ease}
        .img-swap .img-b{opacity:0;transform:scale(1.03)}
        .img-swap:hover .img-a{opacity:0;transform:scale(1.03)}
        .img-swap:hover .img-b{opacity:1;transform:scale(1)}
        .h-sec{text-align:center;max-width:640px;margin:0 auto 44px}
        .h-sec h2{font-family:'Space Grotesk','Inter',sans-serif;font-size:clamp(1.6rem,3vw,2.4rem);font-weight:700;letter-spacing:-.02em;color:#0F172A;margin-bottom:10px}
        .h-sec p{font-size:15px;color:#475569;line-height:1.65}
        .wrap{max-width:1100px;margin:0 auto}
        .hero{position:relative}
        .hero-bg{display:none}
        .nav-hamburger{display:none;background:none;border:none;cursor:pointer;padding:8px;flex-direction:column;gap:4px}
        .nav-hamburger span{display:block;width:20px;height:2px;background:#0F172A;border-radius:2px}
        .nav-mobile{display:none;position:absolute;top:100%;left:0;right:0;background:#FFFFFF;border-bottom:1px solid #E2E8F0;box-shadow:0 20px 40px rgba(15,23,42,.1);padding:14px 20px 24px;flex-direction:column}
        .nav-mobile.open{display:flex}
        .nav-mobile a{color:#475569;font-size:14px;font-weight:500;text-decoration:none;padding:9px 4px;border-bottom:1px solid rgba(15,23,42,.05)}
        .lang-switch{display:flex;gap:4px;margin-left:10px}
        .lang-switch a{padding:4px 10px;border-radius:8px;border:1px solid #E2E8F0;font-size:11.5px;font-weight:600;color:#64748B;text-decoration:none}
        .lang-switch a.active{background:<?= $brandPrimary ?>;border-color:<?= $brandPrimary ?>;color:#fff}
        @media(max-width:768px){
            .nav{padding:12px 20px;flex-wrap:wrap;gap:10px}
            .nav-links{display:none}
            .nav-cta{display:none}
            .nav-hamburger{display:flex}
            .hero{padding:120px 20px 60px}
            .hero h1{font-size:34px}
            .section{padding:56px 20px}
            .grid-3,.grid-2{grid-template-columns:1fr}
        }
        .img-fallback{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
    </style>
</head>
<body>

<nav class="nav">
  <div style="display:flex;align-items:center;gap:10px">
    <?php if (wwi_brand()['logo'] !== ''): ?>
      <a href="/" style="display:flex;align-items:center;gap:10px;text-decoration:none"><?= wwi_logo_img(120) ?><?php if (wwi_brand()['show_text']): ?><span style="font-family:'Space Grotesk',sans-serif;font-size:17px;font-weight:700;color:#0F172A;letter-spacing:-.02em"><?= htmlspecialchars(wwi_brand()['text']) ?></span><?php endif; ?></a>
    <?php else: ?>
    <div style="width:32px;height:32px;border-radius:10px;background:<?= $brandPrimary ?>;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:15px;color:#fff">I</div>
    <span style="font-family:'Space Grotesk',sans-serif;font-size:17px;font-weight:700;color:#0F172A;letter-spacing:-.02em">INTSOL<span style="color:<?= $brandPrimary ?>">COM</span></span>
    <?php endif; ?>
  </div>
  <div class="nav-links" style="display:flex;align-items:center;gap:22px">
    <?= $navLinks ?>
  </div>
  <div style="display:flex;align-items:center;gap:12px">
    <div class="lang-switch">
      <a href="?lang=en" class="active">EN</a>
      <a href="?lang=es">ES</a>
    </div>
    <button type="button" class="nav-hamburger" aria-label="Menu"><span></span><span></span><span></span></button>
  </div>
  <div class="nav-mobile" id="navMobile">
    <?= $navMobile ?>
  </div>
</nav>

<main>
    <?php
    $wwiBuilderHtml = '';
    try {
        $wwiBuilderSvc = new \App\Services\BuilderService();
        if ($wwiBuilderSvc->ready() && $wwiBuilderSvc->hasLayout((int)$page['id'])) {
            $wwiBuilderHtml = $wwiBuilderSvc->renderPage((int)$page['id']);
        }
    } catch (\Throwable $e) {
        $wwiBuilderHtml = '';
    }
    if ($wwiBuilderHtml !== ''):
        echo $wwiBuilderHtml;
    else:
    foreach ($sections as $section):
        $wwiSid = (int)($section['id'] ?? 0);
        $config = json_decode($section['config'] ?? '{}', true) ?: [];
        $wwiHide = (!empty($config['_hide_mobile']) ? ' wwi-hide-mobile' : '') . (!empty($config['_hide_tablet']) ? ' wwi-hide-tablet' : '') . (!empty($config['_hide_desktop']) ? ' wwi-hide-desktop' : '');
        echo '<div class="wwi-section' . $wwiHide . '" data-sid="' . $wwiSid . '" data-widget="' . htmlspecialchars((string)($section['widget_type'] ?? '')) . '">';
        if (!empty($section['widget_type']) && WidgetRegistry::get($section['widget_type'])):
            echo WidgetRegistry::render($section['widget_type'], $config);
        elseif ($section['type'] === 'custom' || $section['type'] === 'html'):
            echo '<section class="section" style="max-width:760px">' . ($section['content'] ?? '') . '</section>';
        else:
            if ($section['content']) echo '<section class="section">' . $section['content'] . '</section>';
        endif;
        echo '</div>';
    endforeach;
    endif; ?>
</main>

<?php if (!empty($wwiBuilderHtml)) { require ROOT_DIR . '/templates/themes/_shared/builder-only.php'; } else { require ROOT_DIR . '/templates/themes/_shared/live-editor.php'; } ?>

<?= CookieConsentService::render() ?>

<script>
(function(){
    var r=document.querySelectorAll('.reveal');
    var o=new IntersectionObserver(function(e){e.forEach(function(e){if(e.isIntersecting)e.target.classList.add('visible')})},{threshold:0.1,rootMargin:'0px 0px -40px 0px'});
    r.forEach(function(el){o.observe(el)});
    var ham=document.querySelector('.nav-hamburger'),mob=document.getElementById('navMobile');
    if(ham&&mob){
        ham.addEventListener('click',function(e){e.stopPropagation();mob.classList.toggle('open')});
        mob.querySelectorAll('a').forEach(function(a){a.addEventListener('click',function(){mob.classList.remove('open')})});
    }
})();
</script>

</body>
</html>
