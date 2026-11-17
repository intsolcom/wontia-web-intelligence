<?php
use App\Services\DirectoryService;

$directory = new DirectoryService();
$profile = $directory->profile((string)($__dirProfileSlug ?? ''));
if (!$profile) {
    http_response_code(404);
    sonika_site_head('Perfil no encontrado | Directorio Sonika', 'El perfil que buscas no está disponible.', null, true);
    echo '<div style="max-width:760px;margin:0 auto;padding:60px 22px;text-align:center"><h1 style="font:800 30px Sora,sans-serif;margin:0 0 10px">Perfil no encontrado</h1><p style="color:#666">Este perfil no existe o todavía no está publicado.</p><p><a href="/directorio" style="color:#F20D16;font-weight:800">Volver al Directorio Sonika</a></p></div>';
    sonika_site_footer();
    exit;
}

$profileRef = (string)$profile['slug'];
$message = '';
$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'review') {
    if (trim((string)($_POST['website'] ?? '')) !== '') {
        $error = 'No pudimos procesar la solicitud.';
    } else {
        $result = $directory->submitReview($profileRef, $_POST, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
        if (!empty($result['ok'])) {
            $message = (string)$result['message'];
        } else {
            $error = (string)($result['message'] ?? 'No pudimos guardar tu reseña.');
        }
    }
}

$summary = $directory->summary($profileRef);
$reviews = $directory->reviews($profileRef, 30);
$topRank = $directory->topRank($profileRef);
$canonical = 'https://sonika.com.co/directorio/perfil/' . rawurlencode($profileRef);
$name = (string)$profile['name'];
$role = (string)($profile['category'] ?: 'Profesional de la música');
$location = trim((string)($profile['city'] ?? '') . (($profile['region'] ?? '') ? ', ' . $profile['region'] : ''));
$description = mb_substr(trim((string)($profile['bio'] ?? '')) ?: ($role . ($location !== '' ? ' en ' . $location : '') . '. Perfil en el Directorio Sonika.'), 0, 300);

sonika_site_head($name . ' | Directorio Sonika', $description, $canonical);
?>
<style>
.dirp{--p-ink:#141414;--p-muted:#666;--p-line:#e5e2dc;--p-paper:#f7f5f1;--p-red:#F20D16;--p-gold:#b8862d;background:var(--p-paper);color:var(--p-ink)}
.dirp *{box-sizing:border-box}
.dirp .p-wrap{max-width:1080px;margin:0 auto;padding:0 22px}
.dirp .p-crumb{padding:16px 0 0;font-size:12px;color:#777}.dirp .p-crumb a{color:#444;border-bottom:1px solid #ccc}
.dirp .p-head{padding:28px 0 24px;display:grid;grid-template-columns:112px minmax(0,1fr) auto;gap:20px;align-items:center}
.dirp .p-avatar{width:112px;height:112px;border-radius:22px;background:#111;color:#fff;display:flex;align-items:center;justify-content:center;font:800 38px Sora,sans-serif;overflow:hidden;border:1px solid #d9d5cd}
.dirp .p-avatar img{width:100%;height:100%;object-fit:cover}
.dirp .p-name{display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.dirp .p-name h1{font:800 clamp(26px,4vw,40px)/1.05 Sora,sans-serif;letter-spacing:-.03em;margin:0}
.dirp .p-gold{font-size:10px;font-weight:850;letter-spacing:.1em;text-transform:uppercase;color:#7c5818;background:#fbf1d7;border:1px solid #e5cc91;border-radius:999px;padding:5px 9px}
.dirp .p-rank{font-size:10px;font-weight:850;letter-spacing:.08em;text-transform:uppercase;color:#4e4e4e;background:#f1eee8;border:1px solid #ddd6ca;border-radius:999px;padding:5px 9px}
.dirp .p-founder{font-size:10px;font-weight:850;letter-spacing:.08em;text-transform:uppercase;color:#6b501b;background:#fff8e6;border:1px solid #e5cc91;border-radius:999px;padding:5px 9px}
.dirp .p-meta{font-size:13px;color:#5d5d5d;margin-top:8px;line-height:1.5}
.dirp .p-verified{font-size:11px;font-weight:800;color:#0d7a58;display:inline-flex;align-items:center;gap:5px;margin-top:7px}
.dirp .p-verified:before{content:"";width:7px;height:7px;border-radius:50%;background:#0d7a58}
.dirp .p-top{display:flex;flex-direction:column;align-items:flex-end;gap:8px;text-align:right;min-width:160px}
.dirp .p-top__badge{display:flex;flex-direction:column;align-items:flex-end;gap:3px;background:#111;color:#d8b46a;border:1px solid #d8b46a;border-radius:12px;padding:10px 12px;font-size:10px;font-weight:850;letter-spacing:.08em;text-transform:uppercase}
.dirp .p-top__badge b{font:800 24px Sora,sans-serif;color:#fff;letter-spacing:-.03em}
.dirp .p-top__badge small{font-size:9px;color:#d8b46a;letter-spacing:.12em}
.dirp .p-top a{display:block;margin-top:0;font-size:11.5px;font-weight:800;color:#111;border-bottom:1px solid #aaa;white-space:nowrap}
.dirp .p-actions{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}
.dirp .p-btn{display:inline-flex;align-items:center;justify-content:center;border-radius:10px;padding:10px 14px;font-size:12px;font-weight:800;text-decoration:none;border:1px solid #d4cec2;background:#fff;color:#111}
.dirp .p-btn--red{background:var(--p-red);border-color:var(--p-red);color:#fff}
.dirp .p-section{border-top:1px solid var(--p-line);padding:26px 0}
.dirp .p-grid{display:grid;grid-template-columns:minmax(0,1.1fr) minmax(300px,.9fr);gap:26px;align-items:start}
.dirp .p-card{background:#fff;border:1px solid var(--p-line);border-radius:16px;padding:18px}
.dirp .p-card h2,.dirp .p-section>h2{font:800 22px/1.2 Sora,sans-serif;letter-spacing:-.02em;margin:0 0 12px}
.dirp .p-card p,.dirp .p-copy{font-size:13px;line-height:1.7;color:#555;margin:0 0 10px}
.dirp .p-free{background:#fbf5e8;border:1px solid #e5cc91;border-radius:12px;padding:12px 14px;font-size:12px;line-height:1.6;color:#5f4a20;margin-top:14px}
.dirp .p-free b{color:#3f3216}
.dirp .p-stars{display:inline-flex;align-items:center;gap:2px;vertical-align:middle;font-size:18px;line-height:1;letter-spacing:1px;color:#d4d4d4}
.dirp .p-stars .on{color:#f5a623}.dirp .p-stars .half{color:#f5a623;opacity:.55}
.dirp .p-ratingline{display:flex;align-items:center;gap:8px;flex-wrap:wrap;margin-top:10px;font-size:12px;color:#666}
.dirp .p-ratingline b{font:800 20px Sora,sans-serif;color:#111}
.dirp .p-summary{display:grid;grid-template-columns:auto 1fr;gap:18px;align-items:center;margin-bottom:18px}
.dirp .p-summary__n{font:800 44px/1 Sora,sans-serif;letter-spacing:-.05em}
.dirp .p-dist{display:grid;gap:5px;min-width:170px}.dirp .p-dist__row{display:grid;grid-template-columns:12px 1fr 24px;gap:6px;align-items:center;font-size:10px;color:#777}
.dirp .p-dist__bar{height:6px;background:#eee;border-radius:99px;overflow:hidden}.dirp .p-dist__bar i{display:block;height:100%;background:#f5a623;border-radius:99px}
.dirp .p-review{border-top:1px solid #eee;padding:16px 0}.dirp .p-review:first-of-type{border-top:0;padding-top:0}
.dirp .p-review__head{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.dirp .p-review__head b{font-size:13px}.dirp .p-review__head span{font-size:11px;color:#888}
.dirp .p-review__body{font-size:13px;line-height:1.65;color:#444;margin:7px 0 0;white-space:pre-line}
.dirp .p-review__dims{font-size:10.5px;color:#888;margin-top:7px}
.dirp .p-empty{background:#faf9f6;border:1px dashed #d8d2c8;border-radius:12px;padding:18px;color:#777;font-size:13px;line-height:1.6}
.dirp .p-form{display:grid;gap:10px}.dirp .p-form label{font-size:11px;font-weight:800;color:#444;display:grid;gap:5px}
.dirp .p-form input,.dirp .p-form select,.dirp .p-form textarea{border:1px solid #ddd8cf;border-radius:9px;padding:10px;font:inherit;font-size:13px;background:#fff;color:#111;width:100%}
.dirp .p-form textarea{min-height:110px;resize:vertical}.dirp .p-form .p-row{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.dirp .p-form .p-consent{display:flex;grid-template-columns:auto 1fr;align-items:flex-start;gap:8px;font-weight:500;line-height:1.5}
.dirp .p-form .p-consent input{width:auto;margin-top:3px}.dirp .p-form button{border:0;border-radius:9px;background:var(--p-red);color:#fff;padding:11px;font:800 12px Sora,sans-serif;cursor:pointer}
.dirp .p-msg{border-radius:10px;padding:10px 12px;font-size:12px;line-height:1.5}.dirp .p-msg--ok{background:#e9f7ef;color:#0d6b4e;border:1px solid #bce5d1}.dirp .p-msg--err{background:#fdecec;color:#a12920;border:1px solid #f3c3bf}
.dirp .p-hp{position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden}
.dirp .p-foot{margin-top:8px;font-size:11px;color:#888;line-height:1.6}
@media(max-width:800px){.dirp .p-head{grid-template-columns:78px minmax(0,1fr);gap:14px}.dirp .p-avatar{width:78px;height:78px;border-radius:16px;font-size:26px}.dirp .p-top{grid-column:1/-1;text-align:left;align-items:flex-start}.dirp .p-top__badge{align-items:flex-start}.dirp .p-grid{grid-template-columns:1fr}.dirp .p-form .p-row{grid-template-columns:1fr}}
@media(max-width:520px){.dirp .p-wrap{padding:0 16px}.dirp .p-head{padding-top:20px}.dirp .p-summary{grid-template-columns:1fr}.dirp .p-free{font-size:11.5px}}
</style>

<div class="dirp">
  <div class="p-wrap">
    <nav class="p-crumb"><a href="/directorio">Directorio Sonika</a> <span>/</span> <span><?= sonika_esc($name) ?></span></nav>

    <header class="p-head">
      <div class="p-avatar"><?php if (!empty($profile['photo'])): ?><img src="<?= sonika_esc($profile['photo']) ?>" alt="<?= sonika_esc($name) ?>" /><?php else: ?><?= sonika_esc(mb_substr($name, 0, 1)) ?><?php endif; ?></div>
      <div>
        <div class="p-name">
          <h1><?= sonika_esc($name) ?></h1>
          <?php if (!empty($profile['is_sonika_artist'])): ?><span class="p-gold">Partner SONIKA</span><?php endif; ?>
          <?php if (!empty($profile['founder'])): ?><span class="p-founder">Fundador</span><?php endif; ?>
          <span class="p-rank"><?= sonika_esc($directory->rankLabel((string)($profile['rank_key'] ?? 'semilla'))) ?> · <?= (int)($profile['points'] ?? 0) ?> pts</span>
        </div>
        <div class="p-meta"><?= sonika_esc($role) ?><?= $location !== '' ? ' · ' . sonika_esc($location) : '' ?><?= !empty($profile['instrument']) ? ' · ' . sonika_esc((string)$profile['instrument']) : '' ?></div>
        <?php if (!empty($profile['verified'])): ?><div class="p-verified">Perfil verificado</div><?php endif; ?>
        <div class="p-ratingline">
          <span class="p-stars" aria-hidden="true"><?php
            for ($i = 1; $i <= 5; $i++) {
                $fill = (float)$summary['average'] - ($i - 1);
                echo '<span class="' . ($fill >= .75 ? 'on' : ($fill >= .25 ? 'half' : '')) . '">&#9733;</span>';
            }
          ?></span>
          <b><?= $summary['count'] ? number_format((float)$summary['average'], 1) : 'Nuevo' ?></b>
          <span><?= $summary['count'] ? (int)$summary['count'] . ' reseña' . ((int)$summary['count'] === 1 ? '' : 's') : 'Aún sin reseñas' ?></span>
          <?php if ($topRank > 0): ?><span style="color:#8a641e;font-weight:800">Top <?= (int)$topRank ?> Confianza Sonika</span><?php endif; ?>
        </div>
        <div class="p-actions">
          <a class="p-btn p-btn--red" href="#resenas">Dejar una reseña</a>
          <?php if (!empty($profile['store_link'])): ?><a class="p-btn" href="<?= sonika_esc((string)$profile['store_link']) ?>" target="_blank" rel="noopener">Ver catálogo con mi link</a><?php endif; ?>
          <a class="p-btn" href="/directorio/top">Ver Top 10</a>
        </div>
      </div>
      <div class="p-top">
        <?php if ($topRank > 0): ?>
        <span class="p-top__badge"><small>Premio de confianza</small><b>Top <?= (int)$topRank ?></b><small>Sonika</small></span>
        <?php endif; ?>
        <a href="/directorio">Volver al directorio</a>
      </div>
    </header>

    <section class="p-section">
      <div class="p-grid">
        <div>
          <h2>Sobre este perfil</h2>
          <p class="p-copy"><?= nl2br(sonika_esc((string)($profile['bio'] ?: 'Perfil del ecosistema musical colombiano en el Directorio Sonika.'))) ?></p>
          <?php if (!empty($profile['website'])): ?><p class="p-copy"><a href="<?= sonika_esc((string)$profile['website']) ?>" target="_blank" rel="noopener" style="color:#F20D16;font-weight:700">Sitio web del perfil</a></p><?php endif; ?>
          <div class="p-free"><b>Aparecer en el Directorio Sonika es gratis y siempre lo será.</b><br>Sonika no cobra al artista ni al cliente por publicar, buscar, contactar, recibir reseñas o participar en el Top 10. La única retribución es mostrar el catálogo de la tienda y sugerir una visita cuando sea relevante.</div>
        </div>
        <aside class="p-card">
          <h2>Confianza Sonika</h2>
          <div class="p-summary">
            <div class="p-summary__n"><?= $summary['count'] ? number_format((float)$summary['average'], 1) : '—' ?></div>
            <div>
              <div class="p-stars" aria-hidden="true"><?php for ($i = 1; $i <= 5; $i++) { $fill = (float)$summary['average'] - ($i - 1); echo '<span class="' . ($fill >= .75 ? 'on' : ($fill >= .25 ? 'half' : '')) . '">&#9733;</span>'; } ?></div>
              <div style="font-size:11px;color:#777;margin-top:5px"><?= (int)$summary['count'] ?> reseñas aprobadas</div>
            </div>
          </div>
          <div class="p-dist">
            <?php for ($star = 5; $star >= 1; $star--): $n = (int)($summary['dist'][$star] ?? 0); $pct = $summary['count'] ? round($n / $summary['count'] * 100) : 0; ?>
            <div class="p-dist__row"><span><?= $star ?></span><span class="p-dist__bar"><i style="width:<?= $pct ?>%"></i></span><span><?= $n ?></span></div>
            <?php endfor; ?>
          </div>
          <p class="p-foot">Solo se publican reseñas de clientes verificados. Las reseñas no se compran ni se eliminan por ser negativas.</p>
        </aside>
      </div>
    </section>

    <section class="p-section" id="resenas">
      <div class="p-grid">
        <div>
          <h2>Reseñas de clientes</h2>
          <?php if ($message !== ''): ?><div class="p-msg p-msg--ok" style="margin-bottom:12px"><?= sonika_esc($message) ?></div><?php endif; ?>
          <?php if ($error !== ''): ?><div class="p-msg p-msg--err" style="margin-bottom:12px"><?= sonika_esc($error) ?></div><?php endif; ?>
          <?php if (!$reviews): ?>
            <div class="p-empty">Aún no hay reseñas aprobadas. Si contrataste a <?= sonika_esc($name) ?>, puedes dejar la primera reseña; será verificada antes de publicarse.</div>
          <?php else: ?>
            <?php foreach ($reviews as $review): ?>
            <article class="p-review">
              <div class="p-review__head">
                <b><?= sonika_esc((string)$review['reviewer_name']) ?></b>
                <span class="p-stars" aria-label="<?= (int)$review['rating'] ?> de 5 estrellas"><?php for ($i = 1; $i <= 5; $i++) echo '<span class="' . ($i <= (int)$review['rating'] ? 'on' : '') . '">&#9733;</span>'; ?></span>
                <?php if (!empty($review['verified'])): ?><span style="color:#0d7a58;font-weight:800">Cliente verificado</span><?php endif; ?>
                <span><?= sonika_esc(substr((string)$review['created_at'], 0, 10)) ?></span>
              </div>
              <?php if (!empty($review['title'])): ?><div style="font-weight:800;font-size:13px;margin-top:6px"><?= sonika_esc((string)$review['title']) ?></div><?php endif; ?>
              <p class="p-review__body"><?= nl2br(sonika_esc((string)$review['body'])) ?></p>
              <div class="p-review__dims">Puntualidad <?= (int)$review['punctuality'] ?>/5 · Calidad <?= (int)$review['quality'] ?>/5 · Profesionalismo <?= (int)$review['professionalism'] ?>/5 · Comunicación <?= (int)$review['communication'] ?>/5</div>
            </article>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
        <aside class="p-card">
          <h2>Deja tu reseña</h2>
          <p class="p-copy">Cuéntanos cómo fue tu experiencia. Revisaremos la contratación antes de publicarla.</p>
          <form class="p-form" method="post" action="/directorio/perfil/<?= sonika_esc(rawurlencode($profileRef)) ?>#resenas">
            <input type="hidden" name="action" value="review" />
            <div class="p-hp" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off" /></label></div>
            <div class="p-row">
              <label>Nombre completo<input type="text" name="reviewer_name" maxlength="160" required value="<?= sonika_esc((string)($_POST['reviewer_name'] ?? '')) ?>" /></label>
              <label>Correo<input type="email" name="reviewer_email" maxlength="190" required value="<?= sonika_esc((string)($_POST['reviewer_email'] ?? '')) ?>" /></label>
            </div>
            <div class="p-row">
              <label>Calificación general<select name="rating" required><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"<?= (string)($_POST['rating'] ?? '') === (string)$i ? ' selected' : '' ?>><?= $i ?> estrella<?= $i === 1 ? '' : 's' ?></option><?php endfor; ?></select></label>
              <label>Fecha del servicio<input type="date" name="service_date" value="<?= sonika_esc((string)($_POST['service_date'] ?? '')) ?>" /></label>
            </div>
            <div class="p-row">
              <label>Puntualidad<select name="punctuality"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?>/5</option><?php endfor; ?></select></label>
              <label>Calidad musical<select name="quality"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?>/5</option><?php endfor; ?></select></label>
            </div>
            <div class="p-row">
              <label>Profesionalismo<select name="professionalism"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?>/5</option><?php endfor; ?></select></label>
              <label>Comunicación<select name="communication"><?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= $i ?>/5</option><?php endfor; ?></select></label>
            </div>
            <label>Título breve (opcional)<input type="text" name="title" maxlength="160" value="<?= sonika_esc((string)($_POST['title'] ?? '')) ?>" /></label>
            <label>Tu experiencia<textarea name="body" maxlength="2000" required placeholder="¿Cómo fue el toque, el servicio o la colaboración?"><?= sonika_esc((string)($_POST['body'] ?? '')) ?></textarea></label>
            <label class="p-consent"><input type="checkbox" name="consent" value="1" required /> Acepto las condiciones del Directorio Sonika y autorizo la publicación de mi reseña una vez verificada.</label>
            <button type="submit">Enviar reseña</button>
            <p class="p-foot">No publicamos reseñas anónimas ni reseñas compradas. La verificación puede tardar unos días.</p>
          </form>
        </aside>
      </div>
    </section>
  </div>
</div>
<?php sonika_site_footer(); ?>
