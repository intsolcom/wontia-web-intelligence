<?php
declare(strict_types=1);

define('DIRECTORY_APP_VERSION', '0.5.0');
define('WONTIA_START', microtime(true));
define('ROOT_DIR', dirname(__DIR__, 2));
define('APP_ENV', getenv('APP_ENV') ?: 'production');

$__dirPath = parse_url((string)($_SERVER['REQUEST_URI'] ?? '/directorio'), PHP_URL_PATH) ?: '/directorio';

if ($__dirPath === '/directorio/health') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode([
        'ok' => true,
        'app' => 'directorio-sonika',
        'version' => DIRECTORY_APP_VERSION,
        'time' => gmdate('c'),
    ], JSON_UNESCAPED_SLASHES);
    exit;
}

require_once ROOT_DIR . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Config;

Config::load();
$app = new App();
$app->boot();

require_once ROOT_DIR . '/templates/themes/sonika/lib.php';

if (preg_match('#^/directorio/perfil/([^/]+)/?$#', $__dirPath, $__profileMatch)) {
    $__dirProfileSlug = urldecode($__profileMatch[1]);
    require dirname(__DIR__) . '/views/profile.php';
    exit;
}
if ($__dirPath === '/directorio/top' || $__dirPath === '/directorio/top/') {
    require dirname(__DIR__) . '/views/top.php';
    exit;
}
if ($__dirPath === '/directorio/publicar' || $__dirPath === '/directorio/publicar/') {
    require dirname(__DIR__) . '/views/publish.php';
    exit;
}
if ($__dirPath === '/directorio/toques' || $__dirPath === '/directorio/toques/') {
    require dirname(__DIR__) . '/views/toques.php';
    exit;
}
if ($__dirPath === '/directorio/monas' || $__dirPath === '/directorio/monas/') {
    require dirname(__DIR__) . '/views/monas.php';
    exit;
}

$directoryCanonical = 'https://sonika.com.co/directorio';
$directoryTitle = 'Directorio Sonika | El ecosistema musical de Colombia';
$directoryDescription = 'El directorio musical de artistas, profesionales y protagonistas de la industria en Colombia. Publicar tu perfil es gratis y siempre lo será. Lanzamiento 31 de diciembre.';
$q = trim((string)($_GET['q'] ?? ''));
$__launchZone = new DateTimeZone('America/Bogota');
$__launchTarget = new DateTimeImmutable('2026-12-31 23:59:59', $__launchZone);
$__launchNow = new DateTimeImmutable('now', $__launchZone);
$__launchRemaining = max(0, $__launchTarget->getTimestamp() - $__launchNow->getTimestamp());
$__launchDays = intdiv($__launchRemaining, 86400);
$__launchHours = intdiv($__launchRemaining % 86400, 3600);
$__launchMinutes = intdiv($__launchRemaining % 3600, 60);
$__launchSeconds = $__launchRemaining % 60;
$__launched = $__launchRemaining <= 0;
$__launchIso = $__launchTarget->format('Y-m-d\TH:i:sP');

sonika_site_head($directoryTitle, $directoryDescription, $directoryCanonical);
?>
<style>
.directory-app{--dr-ink:#141414;--dr-muted:#616161;--dr-line:#e5e2dc;--dr-paper:#f7f5f1;--dr-card:#fff;--dr-red:#F20D16;--dr-gold:#b8862d;background:var(--dr-paper);color:var(--dr-ink)}
.directory-app *{box-sizing:border-box}
.directory-app .dr-wrap{max-width:1180px;margin:0 auto;padding:0 22px}
.directory-app .dr-nav{background:#fff;border-bottom:1px solid var(--dr-line);position:sticky;top:0;z-index:40}
.directory-app .dr-nav__in{display:flex;align-items:center;gap:8px;min-height:52px;overflow:auto;scrollbar-width:none}
.directory-app .dr-nav__in::-webkit-scrollbar{display:none}
.directory-app .dr-nav a{flex:0 0 auto;padding:8px 12px;border-radius:999px;font-size:12.5px;font-weight:750;color:#4b4b4b;white-space:nowrap}
.directory-app .dr-nav a:hover,.directory-app .dr-nav a:focus-visible{background:#f4f2ee;color:var(--dr-ink);outline:none}
.directory-app .dr-nav a.is-active{background:var(--dr-ink);color:#fff}
.directory-app .dr-launch{background:var(--dr-ink);color:#fff;border-bottom:1px solid #2a2a2a}
.directory-app .dr-launch__in{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:24px;align-items:center;padding-top:20px;padding-bottom:20px}
.directory-app .dr-launch__k{display:inline-flex;align-items:center;gap:8px;font-size:10px;font-weight:850;letter-spacing:.16em;text-transform:uppercase;color:#d8b46a}
.directory-app .dr-launch__k:before{content:"";width:22px;height:2px;background:#d8b46a}
.directory-app .dr-launch h2{font-family:var(--font-display,Sora),system-ui,sans-serif;font-size:clamp(1.15rem,2.1vw,1.65rem);line-height:1.2;letter-spacing:-.02em;margin:8px 0 5px}
.directory-app .dr-launch p{font-size:12.5px;line-height:1.55;color:#cfcfcf;margin:0;max-width:70ch}
.directory-app .dr-launch p b{color:#fff}
.directory-app .dr-count{display:flex;gap:6px;align-items:stretch}
.directory-app .dr-count__u{min-width:58px;text-align:center;background:rgba(255,255,255,.08);border:1px solid rgba(255,255,255,.16);border-radius:10px;padding:8px 7px}
.directory-app .dr-count__u b{display:block;font-family:var(--font-display,Sora),system-ui,sans-serif;font-size:23px;line-height:1;font-weight:850;color:#fff;font-variant-numeric:tabular-nums}
.directory-app .dr-count__u span{display:block;margin-top:4px;font-size:8.5px;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:#bdbdbd}
.directory-app .dr-launch__cta{display:inline-flex;align-items:center;gap:7px;margin-top:12px;color:#d8b46a;font-size:11.5px;font-weight:850;border-bottom:1px solid rgba(216,180,106,.45);padding-bottom:2px}
.directory-app .dr-launch__cta:hover{color:#f0d49b}
.directory-app .dr-launch.is-done .dr-count{display:none}
.directory-app .dr-launch.is-done h2{color:#d8b46a}
.directory-app .dr-hero{padding:clamp(38px,6vw,76px) 0 34px;background:linear-gradient(180deg,#fff 0%,var(--dr-paper) 100%);border-bottom:1px solid var(--dr-line)}
.directory-app .dr-hero__grid{display:grid;grid-template-columns:minmax(0,1.3fr) minmax(320px,.7fr);gap:clamp(28px,5vw,72px);align-items:start}
.directory-app .dr-eyebrow{display:inline-flex;align-items:center;gap:8px;font-size:11px;font-weight:850;letter-spacing:.16em;text-transform:uppercase;color:var(--dr-red)}
.directory-app .dr-eyebrow:before{content:"";width:24px;height:2px;background:var(--dr-red)}
.directory-app h1{font-family:var(--font-display,Sora),system-ui,sans-serif;font-size:clamp(2.1rem,5.2vw,4.2rem);line-height:1.02;letter-spacing:-.045em;font-weight:850;margin:16px 0 18px;max-width:14ch}
.directory-app .dr-hero p{font-size:clamp(15px,1.7vw,18px);line-height:1.65;color:var(--dr-muted);max-width:58ch;margin:0}
.directory-app .dr-search{margin-top:26px;display:flex;align-items:center;gap:8px;background:#fff;border:1px solid #d9d5cd;border-radius:16px;padding:7px;box-shadow:0 18px 46px rgba(20,20,20,.08);max-width:680px}
.directory-app .dr-search input{flex:1;min-width:0;border:0;background:transparent;padding:12px 14px;font:inherit;font-size:15px;color:var(--dr-ink);outline:none}
.directory-app .dr-search input::placeholder{color:#929292}
.directory-app .dr-search button{border:0;border-radius:11px;background:var(--dr-red);color:#fff;padding:11px 18px;font:inherit;font-size:13px;font-weight:800;cursor:pointer;transition:background .2s ease}
.directory-app .dr-search button:hover{background:#c80a12}
.directory-app .dr-chips{display:flex;flex-wrap:wrap;gap:7px;margin-top:12px}
.directory-app .dr-chips a{font-size:11.5px;font-weight:700;color:#4d4d4d;background:#fff;border:1px solid #e3dfd7;border-radius:999px;padding:6px 10px}
.directory-app .dr-chips a:hover{border-color:#bdb7ad;color:#111}
.directory-app .dr-note{display:flex;align-items:flex-start;gap:10px;margin-top:18px;font-size:12.5px;color:#555;line-height:1.55}
.directory-app .dr-note b{color:var(--dr-ink)}
.directory-app .dr-note__dot{flex:0 0 auto;width:8px;height:8px;border-radius:50%;background:#0e9f6e;margin-top:5px;box-shadow:0 0 0 4px rgba(14,159,110,.12)}
.directory-app .dr-panel{background:var(--dr-ink);color:#fff;border-radius:22px;padding:24px;box-shadow:0 24px 60px rgba(20,20,20,.18);position:relative;overflow:hidden}
.directory-app .dr-panel:after{content:"";position:absolute;right:-70px;top:-70px;width:190px;height:190px;border-radius:50%;background:radial-gradient(circle,rgba(242,13,22,.55),transparent 68%)}
.directory-app .dr-panel__k{font-size:10px;letter-spacing:.16em;text-transform:uppercase;color:#f0b8bb;font-weight:850}
.directory-app .dr-panel h2{font-family:var(--font-display,Sora),system-ui,sans-serif;font-size:23px;line-height:1.12;margin:10px 0 8px;letter-spacing:-.02em}
.directory-app .dr-panel p{font-size:13px;line-height:1.6;color:#d7d7d7;margin:0 0 18px}
.directory-app .dr-panel__rows{display:grid;gap:8px;position:relative;z-index:1}
.directory-app .dr-panel__row{display:flex;align-items:center;justify-content:space-between;gap:14px;border-top:1px solid rgba(255,255,255,.13);padding-top:9px;font-size:12.5px;color:#f1f1f1}
.directory-app .dr-panel__row b{color:#fff}
.directory-app .dr-panel__cta{display:inline-flex;align-items:center;gap:8px;margin-top:18px;background:#fff;color:#111;border-radius:11px;padding:11px 15px;font-size:12.5px;font-weight:800;position:relative;z-index:1}
.directory-app .dr-free-band{background:#fff;border-bottom:1px solid var(--dr-line);border-top:1px solid var(--dr-line)}
.directory-app .dr-free-band__in{display:flex;align-items:flex-start;gap:16px;padding-top:18px;padding-bottom:18px}
.directory-app .dr-free-band__tag{flex:0 0 auto;display:inline-flex;align-items:center;border:1px solid #d8b46a;border-radius:999px;padding:6px 10px;color:#8a641e;background:#fbf5e8;font-size:9.5px;font-weight:850;letter-spacing:.1em;text-transform:uppercase;white-space:nowrap}
.directory-app .dr-free-band b{display:block;font-size:13.5px;line-height:1.4;color:var(--dr-ink)}
.directory-app .dr-free-band p{font-size:12px;line-height:1.55;color:var(--dr-muted);margin:3px 0 0;max-width:82ch}
.directory-app .dr-section{padding:clamp(36px,5vw,58px) 0}
.directory-app .dr-sec-head{display:flex;align-items:flex-end;justify-content:space-between;gap:20px;margin-bottom:20px}
.directory-app .dr-sec-head h2{font-family:var(--font-display,Sora),system-ui,sans-serif;font-size:clamp(1.35rem,2.4vw,2rem);letter-spacing:-.025em;margin:0}
.directory-app .dr-sec-head p{font-size:13px;color:var(--dr-muted);margin:6px 0 0;max-width:56ch;line-height:1.55}
.directory-app .dr-sec-head a{font-size:12px;font-weight:800;color:var(--dr-ink);white-space:nowrap;border-bottom:1px solid #bbb}
.directory-app .dr-cats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
.directory-app .dr-cat{display:flex;flex-direction:column;min-height:136px;background:#fff;border:1px solid #e4e0d8;border-radius:15px;padding:16px;transition:transform .2s ease,border-color .2s ease,box-shadow .2s ease}
.directory-app .dr-cat:hover,.directory-app .dr-cat:focus-visible{transform:translateY(-3px);border-color:#bdb6aa;box-shadow:0 14px 30px rgba(20,20,20,.08);outline:none}
.directory-app .dr-cat__n{font-size:10px;font-weight:850;letter-spacing:.1em;text-transform:uppercase;color:#a3a3a3}
.directory-app .dr-cat b{font-size:16px;line-height:1.2;margin:8px 0 6px;letter-spacing:-.01em}
.directory-app .dr-cat span{font-size:12px;color:var(--dr-muted);line-height:1.5;margin-top:auto}
.directory-app .dr-artists{background:var(--dr-ink);color:#fff;border-radius:24px;padding:clamp(24px,4vw,40px);display:grid;grid-template-columns:minmax(0,1.1fr) minmax(280px,.9fr);gap:28px;align-items:center;overflow:hidden;position:relative}
.directory-app .dr-artists:before{content:"";position:absolute;font-size:240px;line-height:1;right:4%;top:-90px;color:rgba(184,134,45,.14);font-family:Georgia,serif}
.directory-app .dr-artists h2{font-family:var(--font-display,Sora),system-ui,sans-serif;font-size:clamp(1.5rem,2.7vw,2.2rem);margin:10px 0;letter-spacing:-.025em}
.directory-app .dr-artists h2 em{font-style:normal;color:#d8b46a}
.directory-app .dr-artists p{font-size:13.5px;line-height:1.65;color:#d2d2d2;margin:0;max-width:58ch}
.directory-app .dr-artists__side{border-left:1px solid rgba(255,255,255,.14);padding-left:28px;position:relative;z-index:1}
.directory-app .dr-artists__side span{display:block;font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:#d8b46a;font-weight:850;margin-bottom:8px}
.directory-app .dr-artists__side b{display:block;font-size:14px;line-height:1.5;color:#fff;font-weight:650}
.directory-app .dr-artists__side a{display:inline-flex;margin-top:14px;background:#d8b46a;color:#17120a;border-radius:10px;padding:10px 14px;font-size:12px;font-weight:850}
.directory-app .dr-events{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:12px}
.directory-app .dr-event{background:#fff;border:1px solid #e4e0d8;border-radius:15px;padding:16px;display:flex;flex-direction:column;gap:6px;min-height:150px}
.directory-app .dr-event__date{font-size:10px;font-weight:850;letter-spacing:.1em;text-transform:uppercase;color:var(--dr-red)}
.directory-app .dr-event b{font-size:15px;line-height:1.25}
.directory-app .dr-event span{font-size:12px;color:var(--dr-muted);line-height:1.5}
.directory-app .dr-event a{margin-top:auto;font-size:11.5px;font-weight:800;color:var(--dr-ink);border-bottom:1px solid #c8c1b6;align-self:flex-start}
.directory-app .dr-cta{background:var(--dr-red);color:#fff;border-radius:22px;padding:clamp(24px,4vw,38px);display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap}
.directory-app .dr-cta h2{font-family:var(--font-display,Sora),system-ui,sans-serif;font-size:clamp(1.35rem,2.5vw,2rem);margin:0 0 6px;letter-spacing:-.025em}
.directory-app .dr-cta p{font-size:13px;line-height:1.55;margin:0;color:rgba(255,255,255,.85);max-width:62ch}
.directory-app .dr-cta a{background:#fff;color:#111;border-radius:11px;padding:12px 17px;font-size:12.5px;font-weight:850;white-space:nowrap}
.directory-app .dr-terms{background:#fff;border:1px solid var(--dr-line);border-radius:18px;padding:clamp(20px,3vw,30px);max-width:860px}
.directory-app .dr-terms h2{font-family:var(--font-display,Sora),system-ui,sans-serif;font-size:clamp(1.2rem,2.2vw,1.6rem);letter-spacing:-.02em;margin:0 0 12px}
.directory-app .dr-terms p{font-size:12.5px;line-height:1.65;color:#555;margin:0 0 10px}
.directory-app .dr-terms b{color:#111}
.directory-app .dr-terms__note{font-size:11.5px!important;color:#777!important;border-top:1px solid #eee;padding-top:10px;margin-top:14px!important}
.directory-app .dr-notice{background:#fff;border:1px solid #e4e0d8;border-radius:12px;padding:12px 14px;font-size:12.5px;color:#5b5b5b;margin-top:16px}
.directory-app .dr-notice b{color:#111}
@media(max-width:980px){.directory-app .dr-launch__in{grid-template-columns:1fr}.directory-app .dr-count{justify-content:flex-start}.directory-app .dr-hero__grid{grid-template-columns:1fr}.directory-app .dr-panel{max-width:560px}.directory-app .dr-cats{grid-template-columns:repeat(2,minmax(0,1fr))}.directory-app .dr-artists{grid-template-columns:1fr}.directory-app .dr-artists__side{border-left:0;border-top:1px solid rgba(255,255,255,.14);padding:20px 0 0}.directory-app .dr-events{grid-template-columns:1fr}}
@media(max-width:560px){.directory-app .dr-wrap{padding:0 16px}.directory-app .dr-count{width:100%}.directory-app .dr-count__u{flex:1;min-width:0}.directory-app .dr-launch__in{gap:16px}.directory-app .dr-free-band__in{flex-direction:column;gap:8px}.directory-app .dr-search{display:grid;grid-template-columns:1fr}.directory-app .dr-search button{width:100%}.directory-app .dr-cats{grid-template-columns:1fr}.directory-app .dr-sec-head{align-items:flex-start;flex-direction:column;gap:8px}.directory-app .dr-artists__side{padding-top:16px}.directory-app .dr-cta{align-items:flex-start;flex-direction:column}}
</style>

<div class="directory-app" id="directorio-app">
  <nav class="dr-nav" aria-label="Navegación del Directorio Sonika">
    <div class="dr-wrap dr-nav__in">
      <a class="is-active" href="/directorio" aria-current="page">Directorio Sonika</a>
      <a href="#categorias">Explorar</a>
      <a href="#artistas-sonika">Sonika Partners</a>
      <a href="/directorio/top">Top 10</a>
      <a href="/directorio/toques">Toques</a>
      <a href="/directorio/monas">Moñas</a>
      <a href="#eventos">Eventos</a>
      <a href="/directorio/publicar">Publicar mi perfil</a>
      <a href="#condiciones">Condiciones</a>
      <a href="/tienda?from=directorio">Ir a la tienda</a>
    </div>
  </nav>

  <section class="dr-launch<?= $__launched ? ' is-done' : '' ?>" data-dir-launch data-target="<?= sonika_esc($__launchIso) ?>" aria-label="Lanzamiento del Directorio Sonika">
    <div class="dr-wrap dr-launch__in">
      <div>
        <span class="dr-launch__k"><?= $__launched ? 'Directorio abierto' : 'Lanzamiento oficial' ?></span>
        <h2><?= $__launched ? 'El Directorio Sonika ya está aquí.' : 'El directorio musical más grande de Colombia.' ?></h2>
        <p><?= $__launched ? 'Publica tu perfil y empieza a hacer parte del ecosistema musical.' : 'Faltan <b id="dir-launch-days">' . (int)$__launchDays . '</b> días para el <b>31 de diciembre</b>. Sé de los primeros en las listas top, haz que te encuentren y promueve tu carrera.' ?></p>
        <a class="dr-launch__cta" href="/directorio/publicar"><?= $__launched ? 'Explorar el directorio' : 'Quiero ser de los primeros' ?> <span aria-hidden="true">&rarr;</span></a>
      </div>
      <div class="dr-count" id="dir-countdown" role="timer" aria-label="Tiempo restante para el lanzamiento"<?= $__launched ? ' hidden' : '' ?>>
        <div class="dr-count__u"><b data-count="days"><?= (int)$__launchDays ?></b><span>días</span></div>
        <div class="dr-count__u"><b data-count="hours"><?= (int)$__launchHours ?></b><span>horas</span></div>
        <div class="dr-count__u"><b data-count="minutes"><?= (int)$__launchMinutes ?></b><span>min</span></div>
        <div class="dr-count__u"><b data-count="seconds"><?= (int)$__launchSeconds ?></b><span>seg</span></div>
      </div>
    </div>
  </section>

  <section class="dr-hero">
    <div class="dr-wrap dr-hero__grid">
      <div>
        <span class="dr-eyebrow">Directorio Sonika</span>
        <h1>El mapa vivo del ecosistema musical.</h1>
        <p>Encuentra músicos, docentes, luthiers, estudios, escuelas, venues y eventos. Todo el negocio de la música, organizado en un solo lugar.</p>

        <form class="dr-search" action="/directorio" method="get" role="search">
          <label class="sr-only" for="dir-q" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">Buscar en el directorio</label>
          <input id="dir-q" name="q" type="search" value="<?= sonika_esc($q) ?>" placeholder="Busca guitarristas, luthiers, escuelas, karaoke, eventos..." autocomplete="off">
          <button type="submit">Buscar</button>
        </form>
        <div class="dr-chips" aria-label="Búsquedas sugeridas">
          <a href="/directorio?q=guitarristas">Guitarristas</a>
          <a href="/directorio?q=docentes">Docentes</a>
          <a href="/directorio?q=luthiers">Luthiers</a>
          <a href="/directorio?q=estudios">Estudios</a>
          <a href="/directorio?q=karaoke">Karaoke</a>
          <a href="/directorio?q=eventos">Eventos</a>
        </div>
        <div class="dr-note">
          <span class="dr-note__dot" aria-hidden="true"></span>
          <span><b>Aparecer en el Directorio Sonika es gratis y siempre lo será.</b> Sin comisiones por contacto.</span>
        </div>
        <?php if ($q !== ''): ?>
        <div class="dr-notice"><b>Buscador en construcción:</b> estamos preparando los perfiles del directorio. La búsqueda de �S<?= sonika_esc($q) ?>⬝ estará disponible en la siguiente fase.</div>
        <?php endif; ?>
      </div>

      <aside class="dr-panel" aria-label="El directorio en tres pasos">
        <div class="dr-panel__k">Hecho para el ecosistema musical</div>
        <h2>De la búsqueda a la conexión.</h2>
        <p>Un directorio práctico, minimalista y pensado para que encuentres a la persona o el negocio correcto sin ruido.</p>
        <div class="dr-panel__rows">
          <div class="dr-panel__row"><span>Perfiles categorizados</span><b>Por rol e instrumento</b></div>
          <div class="dr-panel__row"><span>Sonika Partners</span><b>Socios destacados</b></div>
          <div class="dr-panel__row"><span>Eventos</span><b>Música en vivo</b></div>
          <div class="dr-panel__row"><span>Escuelas</span><b>Cursos y programas</b></div>
        </div>
        <a class="dr-panel__cta" href="/directorio/publicar">Publicar mi perfil gratis <span aria-hidden="true">&rarr;</span></a>
      </aside>
    </div>
  </section>

  <section class="dr-free-band" aria-label="Directorio Sonika gratis para siempre">
    <div class="dr-wrap dr-free-band__in">
      <span class="dr-free-band__tag">Gratis para siempre</span>
      <div>
        <b>Aparecer en el Directorio Sonika es gratis y siempre lo será.</b>
        <p>No cobramos por publicar tu perfil, ser encontrado, recibir reseñas ni participar en el Top 10. Sonika solo te muestra su catálogo y te invita a visitar la tienda cuando sea relevante.</p>
      </div>
    </div>
  </section>

  <section class="dr-section" id="categorias">
    <div class="dr-wrap">
      <div class="dr-sec-head">
        <div>
          <h2>Explora por especialidad</h2>
          <p>Todo lo que mueve la música, estrictamente categorizado para que encuentres rápido.</p>
        </div>
        <a href="/directorio">Ver todas las categorías</a>
      </div>
      <div class="dr-cats">
        <a class="dr-cat" href="/directorio?cat=musicos"><span class="dr-cat__n">01</span><b>Músicos e intérpretes</b><span>Instrumentistas, cantantes, compositores, DJs y directores.</span></a>
        <a class="dr-cat" href="/directorio?cat=docencia"><span class="dr-cat__n">02</span><b>Docencia y formación</b><span>Profesores, academias, conservatorios y talleres.</span></a>
        <a class="dr-cat" href="/directorio?cat=produccion"><span class="dr-cat__n">03</span><b>Producción y estudio</b><span>Grabación, mezcla, masterización, diseño sonoro y beatmakers.</span></a>
        <a class="dr-cat" href="/directorio?cat=tecnicos"><span class="dr-cat__n">04</span><b>Técnicos y oficios</b><span>Luthiers, afinadores, reparación, sonido, luces y backline.</span></a>
        <a class="dr-cat" href="/directorio?cat=industria"><span class="dr-cat__n">05</span><b>Industria musical</b><span>Management, booking, agencias, sellos, editoriales y marketing.</span></a>
        <a class="dr-cat" href="/directorio?cat=eventos"><span class="dr-cat__n">06</span><b>Eventos y venues</b><span>Salas, teatros, bares con música, karaoke y festivales.</span></a>
        <a class="dr-cat" href="/directorio?cat=comercio"><span class="dr-cat__n">07</span><b>Comercio y fabricación</b><span>Tiendas, fabricantes, artesanos, alquiler e importadores.</span></a>
        <a class="dr-cat" href="/directorio?cat=escuelas"><span class="dr-cat__n">08</span><b>Escuelas y universidades</b><span>Programas, cursos, clases y formación musical.</span></a>
      </div>
    </div>
  </section>

  <section class="dr-section" id="artistas-sonika" style="padding-top:0">
    <div class="dr-wrap">
      <div class="dr-artists">
        <div>
          <span class="dr-eyebrow" style="color:#d8b46a">Programa de socios</span>
          <h2>Sonika <em>Partners</em></h2>
          <p>Un espacio privilegiado para los artistas que construyen con nosotros. Perfil verificado, título dorado, reel de fotos y videos, trayectoria, eventos y contacto para booking.</p>
        </div>
        <div class="dr-artists__side">
          <span>Marca la diferencia</span>
          <b>Un perfil pensado para presentarte, contar tu historia y conectar con quienes quieren contratarte.</b>
          <a href="/sonika-partners">Conocer el programa</a>
        </div>
      </div>
    </div>
  </section>

  <section class="dr-section" id="eventos" style="padding-top:0">
    <div class="dr-wrap">
      <div class="dr-sec-head">
        <div>
          <h2>Música en vivo</h2>
          <p>Conciertos, festivales, karaoke, clases magistrales y eventos del ecosistema.</p>
        </div>
        <a href="#eventos">Ver agenda</a>
      </div>
      <div class="dr-events">
        <article class="dr-event"><span class="dr-event__date">Próximamente</span><b>Agenda de conciertos</b><span>Descubre qué está pasando en tu ciudad y guarda tus eventos.</span><a href="#eventos">Explorar eventos</a></article>
        <article class="dr-event"><span class="dr-event__date">Para organizadores</span><b>Publica tu evento</b><span>Llega a personas que buscan música, cultura y experiencias en vivo.</span><a href="#publicar">Publicar evento</a></article>
        <article class="dr-event"><span class="dr-event__date">Para venues</span><b>Karaoke y música en vivo</b><span>Haz visible tu espacio y conecta con artistas y audiencias.</span><a href="/directorio?cat=eventos">Ver venues</a></article>
      </div>
    </div>
  </section>

  <section class="dr-section" id="publicar" style="padding-top:0">
    <div class="dr-wrap">
      <div class="dr-cta">
        <div>
          <h2>Tu lugar en el ecosistema musical.</h2>
          <p>Si eres músico, docente, luthier, estudio, escuela, venue, productor o parte de la industria, publica tu perfil y haz que te encuentren.</p>
        </div>
        <a href="/directorio/publicar">Publicar mi perfil gratis</a>
      </div>
    </div>
  </section>

  <section class="dr-section" id="condiciones" style="padding-top:0">
    <div class="dr-wrap">
      <div class="dr-terms">
        <h2>Condiciones del Directorio Sonika</h2>
        <p><b>Gratuito para siempre.</b> Publicar un perfil, buscar, contactar, recibir reseñas y participar en el Top 10 no tiene costo para artistas, profesionales ni clientes.</p>
        <p><b>La retribución para Sonika es la visibilidad de su catálogo.</b> El directorio puede mostrar productos, ofertas y recomendaciones de la tienda, e invitar al usuario a visitarla. Esto no obliga a comprar ni condiciona la publicación o el rango del perfil.</p>
        <p><b>Sin compra de reputación.</b> No se cobra por aparecer, no se venden reseñas, no se compran estrellas y el Top 10 Confianza Sonika no se puede adquirir.</p>
        <p><b>Reseñas verificadas.</b> Solo se publican después de una contratación o colaboración confirmada. Cualquier intento de manipulación puede retirar el perfil del Top 10 y de la plataforma.</p>
        <p><b>Datos personales.</b> La información de contacto se trata conforme a la política de privacidad y a las autorizaciones del titular. Sonika no vende bases de datos.</p>
        <p class="dr-terms__note">Al publicar un perfil aceptas estas condiciones. Las condiciones completas y la política de privacidad forman parte de los términos del servicio.</p>
      </div>
    </div>
  </section>
</div>

<script>
(function () {
  function markDirectoryContext() {
    document.cookie = 'sonika_from_directory=1;path=/;max-age=3600;SameSite=Lax';
  }
  document.addEventListener('click', function (event) {
    var link = event.target.closest('a[href^="/"]');
    if (!link) return;
    var href = link.getAttribute('href') || '';
    if (href.indexOf('/directorio') === 0 || href.indexOf('/assets') === 0 || href.indexOf('/api') === 0) return;
    markDirectoryContext();
  }, true);
  document.addEventListener('submit', function (event) {
    var form = event.target;
    var action = (form && form.getAttribute && form.getAttribute('action')) || '';
    if (action.indexOf('/directorio') === 0) return;
    markDirectoryContext();
  }, true);
})();
</script>
<script>
(function () {
  var box = document.querySelector('[data-dir-launch]');
  var target = box && box.getAttribute('data-target');
  if (!box || !target) return;
  var end = new Date(target).getTime();
  if (isNaN(end)) return;
  var daysEl = box.querySelector('[data-count="days"]');
  var hoursEl = box.querySelector('[data-count="hours"]');
  var minutesEl = box.querySelector('[data-count="minutes"]');
  var secondsEl = box.querySelector('[data-count="seconds"]');
  var daysLabel = document.getElementById('dir-launch-days');
  var timer = document.getElementById('dir-countdown');
  function pad(n) { return String(n).padStart(2, '0'); }
  function tick() {
    var remaining = Math.max(0, end - Date.now());
    var days = Math.floor(remaining / 86400000);
    var hours = Math.floor((remaining % 86400000) / 3600000);
    var minutes = Math.floor((remaining % 3600000) / 60000);
    var seconds = Math.floor((remaining % 60000) / 1000);
    if (daysEl) daysEl.textContent = String(days);
    if (hoursEl) hoursEl.textContent = pad(hours);
    if (minutesEl) minutesEl.textContent = pad(minutes);
    if (secondsEl) secondsEl.textContent = pad(seconds);
    if (daysLabel) daysLabel.textContent = String(days);
    if (remaining <= 0) {
      box.classList.add('is-done');
      if (timer) timer.hidden = true;
    }
  }
  tick();
  setInterval(tick, 1000);
})();
</script>
<?php sonika_site_footer(); ?>
