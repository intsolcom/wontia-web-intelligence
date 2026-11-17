<?php
use App\Services\DirectoryService;
$directory = new DirectoryService();
$monas = $directory->monyas('active');
sonika_site_head('Moñas | Directorio Sonika', 'Agrupaciones y proyectos musicales del Directorio Sonika. Moñas verificadas con artistas y músicos de confianza.', 'https://sonika.com.co/directorio/monas');
?>
<style>
.dirmn{--m-ink:#141414;--m-muted:#666;--m-line:#e5e2dc;--m-paper:#f7f5f1;--m-red:#F20D16;--m-gold:#b8862d;background:var(--m-paper);color:var(--m-ink)}
.dirmn *{box-sizing:border-box}.dirmn .m-wrap{max-width:1080px;margin:0 auto;padding:0 22px}
.dirmn .m-crumb{padding:16px 0 0;font-size:12px;color:#777}.dirmn .m-crumb a{color:#444;border-bottom:1px solid #ccc}
.dirmn .m-head{padding:32px 0 24px;border-bottom:1px solid var(--m-line)}
.dirmn .m-kicker{font-size:10px;font-weight:850;letter-spacing:.16em;text-transform:uppercase;color:var(--m-gold)}
.dirmn h1{font:800 clamp(30px,5vw,48px)/1.05 Sora,sans-serif;letter-spacing:-.04em;margin:10px 0 10px}
.dirmn .m-head p{font-size:14px;line-height:1.65;color:var(--m-muted);max-width:65ch;margin:0}
.dirmn .m-free{display:inline-flex;background:#fbf5e8;border:1px solid #e5cc91;color:#6b501b;border-radius:999px;padding:7px 10px;font-size:11px;font-weight:800;margin-top:13px}
.dirmn .m-section{padding:24px 0 50px}.dirmn .m-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}
.dirmn .m-card{background:#fff;border:1px solid var(--m-line);border-radius:16px;padding:18px}
.dirmn .m-card h2{font:800 21px/1.2 Sora,sans-serif;letter-spacing:-.02em;margin:0 0 5px}
.dirmn .m-meta{font-size:12px;color:var(--m-muted);line-height:1.5}.dirmn .m-desc{font-size:13px;line-height:1.65;color:#555;margin:9px 0 0}
.dirmn .m-badge{display:inline-flex;border:1px solid #e5cc91;background:#fbf1d7;color:#7c5818;border-radius:999px;padding:5px 9px;font-size:9px;font-weight:850;letter-spacing:.08em;text-transform:uppercase}
.dirmn .m-empty{background:#fff;border:1px dashed #d8d2c8;border-radius:16px;padding:34px 20px;text-align:center;color:#666;font-size:13px;line-height:1.7}.dirmn .m-empty b{display:block;font:800 20px Sora,sans-serif;color:#111;margin-bottom:5px}
.dirmn .m-note{background:#fff;border:1px solid var(--m-line);border-radius:14px;padding:16px;margin-top:20px;font-size:12px;line-height:1.65;color:#666}.dirmn .m-note b{color:#111}
@media(max-width:700px){.dirmn .m-wrap{padding:0 16px}.dirmn .m-grid{grid-template-columns:1fr}.dirmn .m-head{padding-top:22px}}
</style>
<div class="dirmn">
  <div class="m-wrap">
    <nav class="m-crumb"><a href="/directorio">Directorio Sonika</a> <span>/</span> <span>Moñas</span></nav>
    <header class="m-head">
      <div class="m-kicker">Proyectos musicales</div>
      <h1>Moñas.</h1>
      <p>Agrupaciones y proyectos del ecosistema musical. Las moñas verificadas se construyen con perfiles de confianza y se invitan desde el directorio.</p>
      <span class="m-free">Crear, invitar y consultar moñas es gratis y siempre lo será.</span>
    </header>
    <section class="m-section">
      <?php if (!$monas): ?>
        <div class="m-empty"><b>Todavía no hay moñas publicadas.</b>Las moñas verificadas se crean desde perfiles con rango Referente o Legado. Si tienes un proyecto musical, publica tu perfil y empieza a construir tu historial.</div>
      <?php else: ?>
        <div class="m-grid">
          <?php foreach ($monas as $m): ?>
          <article class="m-card">
            <span class="m-badge">Moña verificada</span>
            <h2 style="margin-top:9px"><?= sonika_esc((string)$m['name']) ?></h2>
            <div class="m-meta"><?= sonika_esc((string)($m['city'] ?: 'Colombia')) ?><?= !empty($m['genre']) ? ' · ' . sonika_esc((string)$m['genre']) : '' ?><?= !empty($m['leader_name']) ? ' · Líder: ' . sonika_esc((string)$m['leader_name']) : '' ?></div>
            <?php if (!empty($m['description'])): ?><p class="m-desc"><?= sonika_esc((string)$m['description']) ?></p><?php endif; ?>
          </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <div class="m-note"><b>Cómo se accede.</b> Crear una moña requiere rango Referente o superior. Recibir una invitación requiere rango Escenario o superior. El directorio no cobra por crear, invitar o participar; el objetivo es conectar proyectos musicales de confianza.</div>
    </section>
  </div>
</div>
<?php sonika_site_footer(); ?>
