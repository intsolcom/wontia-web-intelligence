<?php
use App\Services\AnalyticsService;

require_once __DIR__ . '/lib.php';

$page = $page ?? ['title' => 'SONIKA', 'slug' => 'home'];
AnalyticsService::track($_SERVER['REQUEST_URI'], $_SERVER['HTTP_REFERER'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

sonika_site_head('SONIKA — Instrumentos Musicales', 'Compra instrumentos musicales, audio, iluminación y accesorios con envío a toda Colombia. Sonido profesional, precio justo.');
?>

<div class="hero">
  <div class="wrap">
    <div class="hero-in">
      <div>
        <h1>Tu música, con <em>el mejor sonido</em> del mercado.</h1>
        <p>Instrumentos, audio profesional, iluminación y accesorios de las mejores marcas — con precio justo, asesoría real y entrega en toda Colombia.</p>
        <a class="cta" href="#catalogo">Explorar catálogo →</a>
        <a class="cta sec" href="/ofertas">Ver ofertas</a>
      </div>
      <div class="hero-art">
        <div class="art a">Percusión</div>
        <div class="art b">Cuerdas</div>
        <div class="art c">Vientos</div>
        <div class="art d">Audio &amp; Luces</div>
      </div>
    </div>
  </div>
</div>

<div class="wrap">
  <div class="trust">
    <span>✅ <b>Compra 100% segura</b></span>
    <span>🚚 <b>Envíos nacionales</b></span>
    <span>🛡️ <b>Garantía oficial</b></span>
    <span>🎧 <b>Asesoría experta</b></span>
  </div>
</div>

<section class="pad wrap" id="categorias">
  <div class="head"><h2>Explora por categoría</h2><a href="/tienda">Ver todo →</a></div>
  <div class="cat-grid">
    <?php foreach (sonika_categories() as $c): ?>
      <a class="cat" href="/<?= sonika_esc($c['slug']) ?>">
        <div class="ic"><?= $c['icon'] ?></div>
        <h3><?= sonika_esc($c['name']) ?></h3>
        <p><?= sonika_esc($c['tags']) ?></p>
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
