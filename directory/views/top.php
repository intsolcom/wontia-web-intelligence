<?php
use App\Services\DirectoryService;

$directory = new DirectoryService();
$top = $directory->top(10);
$canonical = 'https://sonika.com.co/directorio/top';
sonika_site_head('Top 10 Confianza Sonika | Directorio Sonika', 'Los perfiles mejor calificados por clientes verificados en el Directorio Sonika. El premio de confianza no se compra.', $canonical);
?>
<style>
.dirt{--t-ink:#141414;--t-muted:#666;--t-line:#e5e2dc;--t-paper:#f7f5f1;--t-gold:#b8862d;--t-red:#F20D16;background:var(--t-paper);color:var(--t-ink)}
.dirt *{box-sizing:border-box}.dirt .t-wrap{max-width:1080px;margin:0 auto;padding:0 22px}
.dirt .t-crumb{padding:16px 0 0;font-size:12px;color:#777}.dirt .t-crumb a{color:#444;border-bottom:1px solid #ccc}
.dirt .t-hero{padding:34px 0 26px;border-bottom:1px solid var(--t-line)}
.dirt .t-kicker{font-size:10px;font-weight:850;letter-spacing:.16em;text-transform:uppercase;color:var(--t-gold)}
.dirt h1{font:800 clamp(30px,5vw,52px)/1.02 Sora,sans-serif;letter-spacing:-.045em;margin:10px 0 12px;max-width:16ch}
.dirt .t-hero p{font-size:14px;line-height:1.7;color:var(--t-muted);max-width:65ch;margin:0}
.dirt .t-free{display:inline-flex;align-items:center;gap:8px;margin-top:16px;background:#fbf5e8;border:1px solid #e5cc91;border-radius:999px;padding:7px 11px;color:#6b501b;font-size:11px;font-weight:800}
.dirt .t-section{padding:28px 0 50px}
.dirt .t-grid{display:grid;gap:10px}
.dirt .t-item{display:grid;grid-template-columns:42px minmax(0,1fr) auto;gap:14px;align-items:center;background:#fff;border:1px solid var(--t-line);border-radius:14px;padding:14px;text-decoration:none;color:inherit;transition:transform .2s,border-color .2s}
.dirt .t-item:hover{transform:translateY(-2px);border-color:#c9c0b1}
.dirt .t-rank{font:850 24px/1 Sora,sans-serif;color:#b9b2a7;text-align:center}.dirt .t-item:first-child .t-rank{color:var(--t-gold)}
.dirt .t-name{font-size:15px;font-weight:800}.dirt .t-meta{font-size:11.5px;color:#777;margin-top:4px}.dirt .t-stars{font-size:13px;color:#f5a623;letter-spacing:1px;white-space:nowrap}.dirt .t-off{color:#d6d6d6}
.dirt .t-score{text-align:right;min-width:92px}.dirt .t-score b{display:block;font:800 18px Sora,sans-serif}.dirt .t-score span{display:block;font-size:10px;color:#888;margin-top:2px}
.dirt .t-empty{background:#fff;border:1px dashed #d8d2c8;border-radius:16px;padding:36px 22px;text-align:center;color:#666;font-size:13px;line-height:1.7}
.dirt .t-empty b{display:block;color:#111;font:800 20px Sora,sans-serif;margin-bottom:5px}
.dirt .t-note{background:#fff;border:1px solid var(--t-line);border-radius:16px;padding:20px;margin-top:22px;font-size:12px;line-height:1.7;color:#555}.dirt .t-note b{color:#111}
@media(max-width:600px){.dirt .t-wrap{padding:0 16px}.dirt .t-item{grid-template-columns:32px minmax(0,1fr)}.dirt .t-score{grid-column:2;text-align:left;margin-top:4px}.dirt .t-score b,.dirt .t-score span{display:inline;margin-right:5px}}
</style>
<div class="dirt">
  <div class="t-wrap">
    <nav class="t-crumb"><a href="/directorio">Directorio Sonika</a> <span>/</span> <span>Top 10</span></nav>
    <header class="t-hero">
      <div class="t-kicker">Premio de confianza</div>
      <h1>Top 10 Confianza Sonika.</h1>
      <p>Los perfiles mejor calificados por clientes verificados. Un reconocimiento basado en contrataciones reales, reseñas y comportamiento, no en publicidad.</p>
      <span class="t-free">Aparecer y competir por el Top 10 es gratis y siempre lo será.</span>
    </header>

    <section class="t-section">
      <?php if (!$top): ?>
        <div class="t-empty"><b>El Top 10 se está construyendo.</b>Necesitamos perfiles con al menos 3 reseñas verificadas para publicar el primer ranking. Si eres parte del ecosistema musical, publica tu perfil y empieza a construir tu historial.<br><br><a href="/directorio" style="color:#F20D16;font-weight:800">Explorar el directorio</a></div>
      <?php else: ?>
        <div class="t-grid">
          <?php foreach ($top as $item): $s = $item['summary']; ?>
          <a class="t-item" href="/directorio/perfil/<?= sonika_esc(rawurlencode((string)$item['slug'])) ?>">
            <div class="t-rank"><?= (int)$item['top_rank'] ?></div>
            <div>
              <div class="t-name"><?= sonika_esc((string)$item['name']) ?><?= !empty($item['is_sonika_artist']) ? ' <span style="font-size:9px;color:#8a641e;border:1px solid #e5cc91;background:#fbf1d7;border-radius:99px;padding:3px 6px;text-transform:uppercase;letter-spacing:.08em">Partner SONIKA</span>' : '' ?></div>
              <div class="t-meta"><?= sonika_esc((string)($item['category'] ?: 'Perfil del directorio')) ?><?= !empty($item['city']) ? ' · ' . sonika_esc((string)$item['city']) : '' ?><?= !empty($item['instrument']) ? ' · ' . sonika_esc((string)$item['instrument']) : '' ?></div>
              <div class="t-stars" aria-label="<?= (float)$s['average'] ?> de 5 estrellas"><?php for ($i = 1; $i <= 5; $i++) echo '<span class="' . ($i <= round((float)$s['average']) ? '' : 't-off') . '">&#9733;</span>'; ?><span style="color:#666;font-size:11px;letter-spacing:0;margin-left:5px"><?= number_format((float)$s['average'], 1) ?> (<?= (int)$s['count'] ?>)</span></div>
            </div>
            <div class="t-score"><b><?= number_format((float)$item['confidence_score'], 2) ?></b><span>Confianza</span></div>
          </a>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="t-note">
        <b>Cómo se calcula.</b> Se exigen al menos 3 reseñas aprobadas, promedio mínimo de 4.3, contrataciones verificadas y ausencia de sanciones. El ranking combina reseñas ponderadas, volumen de contrataciones, verificación, respuesta y antigüedad activa.
        <br><br><b>El Top 10 no se compra.</b> No se cobra por participar, por aparecer ni por recibir el premio. Sonika no vende reseñas ni posiciones.
      </div>
    </section>
  </div>
</div>
<?php sonika_site_footer(); ?>
