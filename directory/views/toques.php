<?php
use App\Services\DirectoryService;
$directory = new DirectoryService();
$toques = $directory->toques('open');
sonika_site_head('Toques | Directorio Sonika', 'Oportunidades musicales publicadas en el Directorio Sonika. Toques abiertos y curados para artistas y músicos.', 'https://sonika.com.co/directorio/toques');
?>
<style>
.dirtq{--q-ink:#141414;--q-muted:#666;--q-line:#e5e2dc;--q-paper:#f7f5f1;--q-red:#F20D16;--q-gold:#b8862d;background:var(--q-paper);color:var(--q-ink)}
.dirtq *{box-sizing:border-box}.dirtq .q-wrap{max-width:1080px;margin:0 auto;padding:0 22px}
.dirtq .q-crumb{padding:16px 0 0;font-size:12px;color:#777}.dirtq .q-crumb a{color:#444;border-bottom:1px solid #ccc}
.dirtq .q-head{padding:32px 0 24px;border-bottom:1px solid var(--q-line)}
.dirtq .q-kicker{font-size:10px;font-weight:850;letter-spacing:.16em;text-transform:uppercase;color:var(--q-red)}
.dirtq h1{font:800 clamp(30px,5vw,48px)/1.05 Sora,sans-serif;letter-spacing:-.04em;margin:10px 0 10px}
.dirtq .q-head p{font-size:14px;line-height:1.65;color:var(--q-muted);max-width:65ch;margin:0}
.dirtq .q-free{display:inline-flex;background:#fbf5e8;border:1px solid #e5cc91;color:#6b501b;border-radius:999px;padding:7px 10px;font-size:11px;font-weight:800;margin-top:13px}
.dirtq .q-section{padding:24px 0 50px}.dirtq .q-list{display:grid;gap:10px}
.dirtq .q-card{background:#fff;border:1px solid var(--q-line);border-radius:14px;padding:18px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:16px;align-items:start}
.dirtq .q-date{font-size:10px;font-weight:850;letter-spacing:.1em;text-transform:uppercase;color:var(--q-red)}.dirtq .q-card h2{font:800 20px/1.2 Sora,sans-serif;margin:5px 0 4px;letter-spacing:-.02em}
.dirtq .q-meta{font-size:12px;color:var(--q-muted);line-height:1.5}.dirtq .q-desc{font-size:13px;line-height:1.65;color:#555;margin:8px 0 0;max-width:75ch}
.dirtq .q-badge{display:inline-flex;align-items:center;border:1px solid #d8d2c8;background:#f8f6f2;border-radius:999px;padding:5px 9px;font-size:9px;font-weight:850;letter-spacing:.08em;text-transform:uppercase;color:#5d5549;white-space:nowrap}
.dirtq .q-badge--curated{border-color:#e5cc91;background:#fbf1d7;color:#7c5818}.dirtq .q-side{text-align:right}.dirtq .q-side a{display:inline-block;margin-top:8px;font-size:11px;font-weight:800;color:#111;border-bottom:1px solid #bbb}
.dirtq .q-empty{background:#fff;border:1px dashed #d8d2c8;border-radius:16px;padding:34px 20px;text-align:center;color:#666;font-size:13px;line-height:1.7}.dirtq .q-empty b{display:block;font:800 20px Sora,sans-serif;color:#111;margin-bottom:5px}
.dirtq .q-note{background:#fff;border:1px solid var(--q-line);border-radius:14px;padding:16px;margin-top:20px;font-size:12px;line-height:1.65;color:#666}.dirtq .q-note b{color:#111}
@media(max-width:650px){.dirtq .q-wrap{padding:0 16px}.dirtq .q-card{grid-template-columns:1fr}.dirtq .q-side{text-align:left}.dirtq .q-head{padding-top:22px}}
</style>
<div class="dirtq">
  <div class="q-wrap">
    <nav class="q-crumb"><a href="/directorio">Directorio Sonika</a> <span>/</span> <span>Toques</span></nav>
    <header class="q-head">
      <div class="q-kicker">Oportunidades del ecosistema</div>
      <h1>Toques.</h1>
      <p>Eventos, presentaciones y proyectos que buscan músicos. Los toques abiertos están disponibles para perfiles verificados; los curados se gestionan por invitación.</p>
      <span class="q-free">Publicar y consultar toques es gratis y siempre lo será.</span>
    </header>
    <section class="q-section">
      <?php if (!$toques): ?>
        <div class="q-empty"><b>No hay toques abiertos por ahora.</b>Cuando un organizador publique una oportunidad, aparecerá aquí. Si necesitas un músico o quieres publicar un toque, escríbenos desde <a href="/soporte#contacto" style="color:#F20D16;font-weight:800">Contacto / Soporte</a>.</div>
      <?php else: ?>
        <div class="q-list">
          <?php foreach ($toques as $t): ?>
          <article class="q-card">
            <div>
              <div class="q-date"><?= !empty($t['event_date']) ? sonika_esc(date('d/m/Y', strtotime((string)$t['event_date']))) : 'Fecha por confirmar' ?><?= !empty($t['city']) ? ' · ' . sonika_esc((string)$t['city']) : '' ?></div>
              <h2><?= sonika_esc((string)$t['title']) ?></h2>
              <div class="q-meta"><?= sonika_esc((string)($t['instrument'] ?: 'Músicos')) ?><?= !empty($t['genre']) ? ' · ' . sonika_esc((string)$t['genre']) : '' ?><?= !empty($t['budget']) ? ' · ' . sonika_esc((string)$t['budget']) : '' ?></div>
              <?php if (!empty($t['description'])): ?><p class="q-desc"><?= sonika_esc((string)$t['description']) ?></p><?php endif; ?>
            </div>
            <div class="q-side">
              <span class="q-badge<?= (string)$t['toque_type'] === 'curated' ? ' q-badge--curated' : '' ?>"><?= (string)$t['toque_type'] === 'curated' ? 'Curado' : 'Abierto' ?></span>
              <div style="margin-top:8px"><a href="/directorio">Ver perfiles</a></div>
            </div>
          </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="q-note"><b>¿Cómo funciona?</b> Los perfiles verificados pueden ser invitados a toques. Para los toques curados se exige rango Escenario o superior. La publicación y la consulta son gratuitas; Sonika no cobra comisión por el toque.</div>
    </section>
  </div>
</div>
<?php sonika_site_footer(); ?>
