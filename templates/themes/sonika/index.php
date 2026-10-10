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
        <span class="eyebrow" style="display:inline-block;font-size:12.5px;font-weight:800;letter-spacing:.14em;color:#111111;text-transform:uppercase;margin-bottom:14px">Instrumentos | Audio | Tu pasión</span>
        <h1>EL SONIDO QUE TE <em>MUEVE</em>.</h1>
        <p>Instrumentos y equipos de audio para llevar tu música más lejos.</p>
        <a class="cta" href="/tienda">Explorar productos →</a>
        <a class="cta sec" href="/ofertas">Ver ofertas</a>
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

<div class="wrap">
  <div class="trust">
    <span>🚚 <b>Envíos a toda Colombia</b></span>
    <span>🛡️ <b>Compra segura y garantizada</b></span>
    <span>🎧 <b>Asesoría especializada</b></span>
  </div>
</div>

<section class="pad wrap" id="categorias">
  <div class="head"><h2>Explora por categoría</h2><a href="/tienda">Ver todo →</a></div>
  <div class="cat-grid">
    <?php foreach ([['guitarras','Guitarras','🎸'],['acordeon','Acordeones','🪗'],['amplificadores','Audio profesional','🔊'],['pianos','Teclados','🎹'],['percusion','Baterías y percusión','🥁'],['cuerdas-guitarras','Accesorios','🎛️']] as $fc): ?>
      <a class="cat" href="/<?= sonika_esc($fc[0]) ?>">
        <div class="ic"><?= $fc[2] ?></div>
        <h3><?= sonika_esc($fc[1]) ?></h3>
        <p><?= sonika_esc(sonika_category($fc[0])['tags'] ?? '') ?></p>
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
