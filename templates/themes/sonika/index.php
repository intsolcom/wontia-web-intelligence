<?php
use App\Services\AnalyticsService;

require_once __DIR__ . '/lib.php';

$page = $page ?? ['title' => 'SONIKA', 'slug' => 'home'];
AnalyticsService::track($_SERVER['REQUEST_URI'], $_SERVER['HTTP_REFERER'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

$__cmsSlug = (string)($page['slug'] ?? 'home');
if ($__cmsSlug !== 'home') {
    $__cmsTitle = trim((string)($page['meta_title'] ?? '')) ?: (string)($page['title'] ?? 'SONIKA');
    sonika_site_head($__cmsTitle, (string)($page['meta_description'] ?? ''));
    echo '<style>.sonika-cms{background:#fff;color:#17212b}.sonika-cms .cms-head{max-width:940px;margin:0 auto;padding:30px 22px 4px}.sonika-cms .cms-head h1{font:800 clamp(1.5rem,3.2vw,2.1rem)/1.15 Sora,sans-serif;color:#101d3a;margin:0}.sonika-cms .wwi-section{max-width:940px;margin:0 auto;padding:0 22px}.sonika-cms .prose{padding:10px 0 40px;font-size:15px;line-height:1.75;color:#334155}.sonika-cms .prose h2{font:800 20px Sora,sans-serif;color:#101d3a;margin:30px 0 10px;scroll-margin-top:90px}.sonika-cms .prose h3{font:800 16px Sora,sans-serif;color:#17212b;margin:20px 0 8px}.sonika-cms .prose p{margin:0 0 12px}.sonika-cms .prose ul,.sonika-cms .prose ol{margin:0 0 14px;padding-left:22px}.sonika-cms .prose li{margin:4px 0}.sonika-cms .prose a{color:#F20D16;font-weight:600;text-decoration:none;border-bottom:1px solid rgba(242,13,22,.3)}.sonika-cms .prose strong{color:#101d3a}.sonika-cms .legal-meta{background:#f6fbf9;border:1px solid #e6eeea;border-radius:12px;padding:12px 14px;font-size:13px;color:#51607a}.sonika-cms .legal-foot{margin-top:26px;padding-top:16px;border-top:1px solid #eef2f0;font-size:13px;color:#64748b}.sonika-cms .legal-foot b{color:#101d3a}@media(max-width:640px){.sonika-cms .prose{font-size:14.5px}.sonika-cms .wwi-section{padding:0 16px}}</style>';
    echo '<main class="sonika-cms"><div class="cms-head"><h1>' . sonika_esc((string)$page['title']) . '</h1></div>';
    $__secs = is_array($sections ?? null) ? $sections : [];
    if (!$__secs) {
        echo '<div class="wwi-section"><div class="prose"><p>Contenido en preparación.</p></div></div>';
    }
    foreach ($__secs as $__s) {
        $__cfg = json_decode((string)($__s['config'] ?? '{}'), true) ?: [];
        $__isWidget = !empty($__s['widget_type']) && \App\Widgets\WidgetRegistry::get($__s['widget_type']);
        $__html = $__isWidget ? \App\Widgets\WidgetRegistry::render($__s['widget_type'], $__cfg) : (string)($__s['content'] ?? '');
        echo '<section class="wwi-section" data-sid="' . (int)$__s['id'] . '">' . ($__isWidget ? $__html : '<div class="prose">' . $__html . '</div>') . '</section>';
    }
    echo '</main>';
    sonika_site_footer();
    require ROOT_DIR . '/templates/themes/_shared/live-editor.php';
    return;
}

sonika_site_head('SONIKA — Instrumentos Musicales', 'Compra instrumentos musicales, audio, iluminación y accesorios con envío a toda Colombia. Sonido profesional, precio justo.');
?>

<?php $__heroBuilder = \App\Widgets\WidgetRegistry::render('sonika-hero', ['enabled' => 1]); ?>
<?php if (trim($__heroBuilder) !== ''): ?>
<?= $__heroBuilder ?>
<?php else: ?>
<div class="hero">
  <?php $bgv = trim((string)wwi_tc('sonika', 'hero_bg_video', '')); $bgp = trim((string)wwi_tc('sonika', 'hero_bg_poster', '')); if ($bgv !== ''): ?>
  <video class="hero-bg-video" autoplay muted loop playsinline preload="metadata"<?= $bgp !== '' ? ' poster="' . sonika_esc($bgp) . '"' : '' ?>><source src="<?= sonika_esc($bgv) ?>"></video>
  <?php endif; ?>
  <div class="wrap">
    <div class="hero-in">
      <div>
        <span class="eyebrow" style="display:inline-block;font-size:12.5px;font-weight:800;letter-spacing:.14em;color:#111111;text-transform:uppercase;margin-bottom:14px"><?= sonika_esc((string)wwi_tc('sonika', 'hero_eyebrow', 'Instrumentos | Audio | Tu pasión')) ?></span>
        <?php $hTitle = (string)wwi_tc('sonika', 'hero_title', 'EL SONIDO QUE TE *MUEVE*.'); ?>
        <h1><?= preg_replace('/\*(.+?)\*/', '<em>$1</em>', sonika_esc($hTitle)) ?></h1>
        <p><?= sonika_esc((string)wwi_tc('sonika', 'hero_subtitle', 'Instrumentos y equipos de audio para llevar tu música más lejos.')) ?></p>
        <a class="cta" href="<?= sonika_esc((string)wwi_tc('sonika', 'hero_cta_url', '/tienda')) ?>"><?= sonika_esc((string)wwi_tc('sonika', 'hero_cta_label', 'Explorar productos')) ?> &#8594;</a>
        <?php $c2 = (string)wwi_tc('sonika', 'hero_cta2_label', ''); if ($c2 !== ''): ?>
        <a class="cta sec" href="<?= sonika_esc((string)wwi_tc('sonika', 'hero_cta2_url', '/ofertas')) ?>"><?= sonika_esc($c2) ?></a>
        <?php endif; ?>
      </div>
      <div class="hero-art">
        <div class="art a">Acordeón</div>
        <div class="art b">Guitarra eléctrica</div>
        <div class="art c">Monitores de estudio</div>
        <div class="art d">Audífonos</div>
        <div class="art e" style="grid-column:1 / -1">Consola &amp; mezcla</div>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<div class="wrap">
  <div class="trust">
    <?php foreach ((array)wwi_tc('sonika', 'benefits', []) as $b): ?>
    <span><?= sonika_icon((string)($b['icon'] ?? '')) ?> <b><?= sonika_esc((string)($b['text'] ?? '')) ?></b></span>
    <?php endforeach; ?>
  </div>
</div>

<?= \App\Widgets\WidgetRegistry::render('sonika-categories', []) ?>

<section class="pad wrap" id="catalogo">
  <div class="head"><h2>Productos destacados</h2><a href="/tienda">Ver tienda →</a></div>
  <?php $featured = array_slice(sonika_products(), 0, 12); ?>
  <?php if (!empty($featured)): ?>
    <div class="prod-grid">
      <?php foreach ($featured as $p) sonika_product_card($p); ?>
    </div>
  <?php else: ?>
    <div class="empty">Catálogo en preparación. Muy pronto verás aquí nuestros productos destacados.</div>
  <?php endif; ?>
</section>

<section class="pad wrap">
  <div class="band">
    <div>
      <h2>¿No sabes cuál elegir?</h2>
      <p>Nuestro asistente te ayuda a encontrar el instrumento ideal según tu nivel, tu estilo y tu presupuesto.</p>
    </div>
    <a class="cta sec" href="/asesor" style="background:#fff;color:#111827">Pedir asesoría →</a>
  </div>
</section>

<?php sonika_site_footer(); ?>
