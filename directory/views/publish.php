<?php
use App\Services\DirectoryService;

$directory = new DirectoryService();
$message = '';
$error = '';
$result = null;
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (trim((string)($_POST['company_site'] ?? '')) !== '') {
        $error = 'No pudimos procesar la solicitud.';
    } else {
        $result = $directory->createProfile($_POST);
        if (!empty($result['ok'])) {
            $message = (string)$result['message'];
        } else {
            $error = (string)($result['message'] ?? 'No pudimos crear el perfil.');
        }
    }
}

$canonical = 'https://sonika.com.co/directorio/publicar';
sonika_site_head('Publicar mi perfil | Directorio Sonika', 'Publica tu perfil gratis en el Directorio Sonika. Aparecer es gratis y siempre lo será.', $canonical);
?>
<style>
.dirpub{--u-ink:#141414;--u-muted:#666;--u-line:#e5e2dc;--u-paper:#f7f5f1;--u-red:#F20D16;background:var(--u-paper);color:var(--u-ink)}
.dirpub *{box-sizing:border-box}.dirpub .u-wrap{max-width:900px;margin:0 auto;padding:0 22px}
.dirpub .u-crumb{padding:16px 0 0;font-size:12px;color:#777}.dirpub .u-crumb a{color:#444;border-bottom:1px solid #ccc}
.dirpub .u-head{padding:30px 0 22px}.dirpub .u-kicker{font-size:10px;font-weight:850;letter-spacing:.16em;text-transform:uppercase;color:var(--u-red)}
.dirpub h1{font:800 clamp(28px,5vw,46px)/1.05 Sora,sans-serif;letter-spacing:-.04em;margin:10px 0 10px;max-width:15ch}
.dirpub .u-head p{font-size:14px;line-height:1.65;color:var(--u-muted);max-width:62ch;margin:0}
.dirpub .u-free{display:inline-flex;align-items:center;gap:7px;background:#fbf5e8;border:1px solid #e5cc91;color:#6b501b;border-radius:999px;padding:7px 10px;font-size:11px;font-weight:800;margin-top:13px}
.dirpub .u-card{background:#fff;border:1px solid var(--u-line);border-radius:18px;padding:clamp(18px,3vw,26px);margin:8px 0 50px}
.dirpub .u-form{display:grid;gap:12px}.dirpub .u-form h2{font:800 19px Sora,sans-serif;margin:0 0 4px}
.dirpub .u-form p.u-hint{font-size:12px;color:#777;line-height:1.55;margin:-3px 0 4px}
.dirpub .u-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.dirpub .u-form label{font-size:11px;font-weight:800;color:#444;display:grid;gap:5px}
.dirpub .u-form input,.dirpub .u-form select,.dirpub .u-form textarea{border:1px solid #ddd8cf;border-radius:9px;padding:10px 11px;font:inherit;font-size:13px;background:#fff;color:#111;width:100%}
.dirpub .u-form textarea{min-height:118px;resize:vertical}
.dirpub .u-form button{border:0;border-radius:10px;background:var(--u-red);color:#fff;padding:12px 16px;font:800 12px Sora,sans-serif;cursor:pointer}
.dirpub .u-form .u-consent{display:flex;grid-template-columns:auto 1fr;align-items:flex-start;gap:8px;font-weight:500;line-height:1.55}.dirpub .u-form .u-consent input{width:auto;margin-top:3px}
.dirpub .u-msg{background:#e9f7ef;border:1px solid #bce5d1;color:#0d6b4e;border-radius:12px;padding:16px;font-size:13px;line-height:1.65;margin-bottom:14px}.dirpub .u-msg b{display:block;font:800 18px Sora,sans-serif;margin-bottom:4px}
.dirpub .u-msg--err{background:#fdecec;border-color:#f3c3bf;color:#a12920}
.dirpub .u-note{font-size:11.5px;color:#777;line-height:1.6;border-top:1px solid #eee;padding-top:11px;margin-top:2px}
@media(max-width:620px){.dirpub .u-wrap{padding:0 16px}.dirpub .u-row{grid-template-columns:1fr}.dirpub .u-head{padding-top:22px}}
</style>
<div class="dirpub">
  <div class="u-wrap">
    <nav class="u-crumb"><a href="/directorio">Directorio Sonika</a> <span>/</span> <span>Publicar mi perfil</span></nav>
    <header class="u-head">
      <div class="u-kicker">Directorio Sonika</div>
      <h1>Tu lugar en la música empieza aquí.</h1>
      <p>Cuéntanos quién eres y qué haces. Revisaremos tu perfil y lo publicaremos para que clientes, colegas y proyectos puedan encontrarte.</p>
      <span class="u-free">Publicar tu perfil es gratis y siempre lo será.</span>
    </header>

    <div class="u-card">
      <?php if ($message !== ''): ?>
        <div class="u-msg"><b>Perfil recibido.</b><?= sonika_esc($message) ?><br><br>Te avisaremos cuando esté publicado. Mientras tanto puedes seguir explorando el directorio.</div>
        <p><a href="/directorio" style="display:inline-block;background:#111;color:#fff;border-radius:9px;padding:10px 14px;font-size:12px;font-weight:800;text-decoration:none">Volver al directorio</a></p>
      <?php else: ?>
        <?php if ($error !== ''): ?><div class="u-msg u-msg--err"><?= sonika_esc($error) ?></div><?php endif; ?>
        <form class="u-form" method="post" action="/directorio/publicar">
          <h2>Datos del perfil</h2>
          <p class="u-hint">Los campos marcados con * son obligatorios. La publicación es gratuita y no cobramos por recibir contactos.</p>
          <div class="u-hint" aria-hidden="true" style="position:absolute;left:-9999px"><label>Sitio<input type="text" name="company_site" tabindex="-1" autocomplete="off" /></label></div>
          <div class="u-row">
            <label>Nombre del perfil *<input name="display_name" maxlength="190" required value="<?= sonika_esc((string)($_POST['display_name'] ?? '')) ?>" placeholder="Tu nombre artístico o nombre del negocio" /></label>
            <label>Tipo de perfil *<select name="profile_type"><option value="artist">Artista / músico</option><option value="teacher">Docente</option><option value="business">Negocio</option><option value="school">Escuela / universidad</option><option value="venue">Venue / espacio</option><option value="producer">Producción / estudio</option><option value="technician">Técnico / luthier</option><option value="other">Otro profesional</option></select></label>
          </div>
          <div class="u-row">
            <label>Categoría principal *<select name="category" required><option value="">Selecciona</option><option>Músico e intérprete</option><option>Docencia y formación</option><option>Producción y estudio</option><option>Técnicos y oficios</option><option>Industria musical</option><option>Eventos y venues</option><option>Comercio y fabricación</option><option>Escuela y universidad</option><option>Servicios creativos</option></select></label>
            <label>Instrumento / especialidad<input name="instrument" maxlength="100" value="<?= sonika_esc((string)($_POST['instrument'] ?? '')) ?>" placeholder="Guitarra, piano, canto, luthería..." /></label>
          </div>
          <div class="u-row">
            <label>Ciudad *<input name="city" maxlength="120" required value="<?= sonika_esc((string)($_POST['city'] ?? '')) ?>" placeholder="Bogotá" /></label>
            <label>Departamento / región<input name="region" maxlength="120" value="<?= sonika_esc((string)($_POST['region'] ?? '')) ?>" placeholder="Cundinamarca" /></label>
          </div>
          <label>Sobre ti *<textarea name="bio" maxlength="2500" required placeholder="Cuéntanos tu trayectoria, servicios, géneros, proyectos o lo que quieres que sepan de ti."><?= sonika_esc((string)($_POST['bio'] ?? '')) ?></textarea></label>
          <div class="u-row">
            <label>Foto o imagen de perfil (URL)<input name="photo" maxlength="500" value="<?= sonika_esc((string)($_POST['photo'] ?? '')) ?>" placeholder="https://..." /></label>
            <label>Sitio web (opcional)<input name="website" maxlength="500" value="<?= sonika_esc((string)($_POST['website'] ?? '')) ?>" placeholder="https://..." /></label>
          </div>
          <div class="u-row">
            <label>Correo de contacto *<input type="email" name="owner_email" maxlength="190" required value="<?= sonika_esc((string)($_POST['owner_email'] ?? '')) ?>" /></label>
            <label>WhatsApp / teléfono<input name="owner_phone" maxlength="40" value="<?= sonika_esc((string)($_POST['owner_phone'] ?? '')) ?>" placeholder="Opcional" /></label>
          </div>
          <label class="u-consent"><input type="checkbox" name="consent" value="1" required /> Acepto las <a href="/directorio#condiciones" target="_blank" rel="noopener" style="color:#F20D16">condiciones del Directorio Sonika</a> y autorizo la publicación de mi perfil una vez verificado.</label>
          <button type="submit">Enviar perfil para revisión</button>
          <p class="u-note">No cobramos por publicar, por recibir contactos, por reseñas ni por participar en el Top 10. Sonika podrá mostrar sugerencias de su catálogo; no estás obligado a comprar.</p>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php sonika_site_footer(); ?>
