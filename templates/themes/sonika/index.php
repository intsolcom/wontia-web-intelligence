<?php
use App\Services\AnalyticsService;

require_once __DIR__ . '/lib.php';

$page = $page ?? ['title' => 'SONIKA', 'slug' => 'home'];
AnalyticsService::track($_SERVER['REQUEST_URI'], $_SERVER['HTTP_REFERER'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

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

<section class="pad wrap" id="categorias">
  <div class="head"><h2>Explora por categoría</h2><a href="/tienda">Ver todo →</a></div>
  <div class="cat-grid">
    <?php
    $catSlugs = array_filter(array_map('trim', explode(',', (string)wwi_tc('sonika', 'home_categories', 'guitarras,acordeon,amplificadores,pianos,percusion,cuerdas-guitarras'))));
    $__subsMap = [];
    foreach ((array)wwi_tc('sonika', 'home_cat_subs', []) as $__r) {
        if (is_array($__r) && !empty($__r['slug'])) $__subsMap[strtolower(trim((string)$__r['slug']))] = (string)($__r['subtitle'] ?? '');
    }
    foreach ($catSlugs as $slug):
        $cat = sonika_category($slug);
        if (!$cat) continue;
        $cimg = sonika_category_image((string)$cat['slug'], 700);
        $__sub = $__subsMap[strtolower((string)$cat['slug'])] ?? (string)$cat['tags'];
        $__b = sonika_category_badges((string)$cat['slug']);
    ?>
      <a class="cat" href="/<?= sonika_esc($cat['slug']) ?>">
        <?php if ($cimg !== ''): ?><img class="cat__img" src="<?= sonika_esc($cimg) ?>" alt="<?= sonika_esc($cat['name']) ?>" loading="lazy" decoding="async"/><?php endif; ?>
        <span class="cat__scrim" aria-hidden="true"></span>
        <?php if ($__b['new']): ?><span class="cat__badge cat__badge--new">NUEVO</span><?php endif; ?>
        <?php if ($__b['offer']): ?><span class="cat__badge cat__badge--off">OFERTA</span><?php endif; ?>
        <span class="cat__body"><span class="cat__kicker"><?= sonika_esc($__sub) ?></span><span class="cat__ttl"><?= sonika_esc($cat['name']) ?></span></span>
        <span class="cat__arrow" aria-hidden="true">&#8594;</span>
      </a>
    <?php endforeach; ?>
  </div>
</section>

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
