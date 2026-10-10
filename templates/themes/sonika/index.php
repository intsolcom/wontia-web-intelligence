<?php
use App\Core\Config;
use App\Services\SeoService;
use App\Services\CookieConsentService;
use App\Services\AnalyticsService;

$page = $page ?? ['title' => 'SONIKA', 'meta_title' => '', 'meta_description' => '', 'slug' => ''];
$pageMeta = array_merge($page, [
    'meta_title' => ($page['meta_title'] ?? '') ?: 'SONIKA — Instrumentos Musicales',
    'meta_description' => ($page['meta_description'] ?? '') ?: 'Compra instrumentos musicales, audio, iluminación y accesorios con envío en Colombia. Sonido profesional, precio justo.',
]);
AnalyticsService::track($_SERVER['REQUEST_URI'], $_SERVER['HTTP_REFERER'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

if (!function_exists('sonika_json')) {
    function sonika_json(string $url, int $ttl = 600): array {
        $cache = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'sonika_' . md5($url) . '.json';
        if (is_file($cache) && (time() - filemtime($cache)) < $ttl) {
            $cached = json_decode((string)@file_get_contents($cache), true);
            if (is_array($cached)) return $cached;
        }
        $raw = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 8,
                CURLOPT_CONNECTTIMEOUT => 5,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 3,
                CURLOPT_USERAGENT => 'SonikaCatalog/1.0 (+https://sonika.com.co)',
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
        if (!$raw && ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: SonikaCatalog/1.0\r\n"]]);
            $raw = @file_get_contents($url, false, $ctx);
        }
        if (!is_string($raw) || $raw === '') {
            return is_file($cache) ? (json_decode((string)@file_get_contents($cache), true) ?: []) : [];
        }
        @file_put_contents($cache, $raw);
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
if (!function_exists('sonika_cop')) {
    function sonika_cop($value): string {
        return '$' . number_format((float)$value, 0, ',', '.');
    }
}
if (!function_exists('sonika_esc')) {
    function sonika_esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}

$catalogFile = __DIR__ . '/catalog.json';
if (is_file($catalogFile)) {
    $feed = json_decode((string)@file_get_contents($catalogFile), true) ?: [];
} else {
    $feed = sonika_json('https://www.miche.com.co/collections/all/products.json?limit=12');
}
$products = $feed['products'] ?? [];
if (count($products) > 12) {
    $products = array_slice($products, 0, 12);
}

$categories = [
    ['name' => 'Percusión',   'slug' => 'percusion',   'icon' => '🥁', 'tags' => 'Baterías · Redoblantes · Cajones'],
    ['name' => 'Cuerdas',     'slug' => 'guitarras',    'icon' => '🎸', 'tags' => 'Guitarras · Bajos · Ukeleles'],
    ['name' => 'Vientos',     'slug' => 'acordeon',     'icon' => '🪗', 'tags' => 'Acordeones · Saxos · Trompetas'],
    ['name' => 'Teclados',    'slug' => 'pianos',       'icon' => '🎹', 'tags' => 'Pianos · Sintetizadores'],
    ['name' => 'Audio',       'slug' => 'amplificadores','icon' => '🔊', 'tags' => 'Amplificadores · Mixers · Parlantes'],
    ['name' => 'Micrófonos',  'slug' => 'microfono-miche','icon' => '🎤', 'tags' => 'Shure · Inalámbricos'],
    ['name' => 'Iluminación', 'slug' => 'cabeza-movil','icon' => '💡', 'tags' => 'Cabezas móviles · Par LED'],
    ['name' => 'Accesorios',  'slug' => 'cuerdas-guitarras','icon' => '🎛️', 'tags' => 'Cuerdas · Correas · Forros'],
];

?><!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <?= SeoService::metaTags($pageMeta) ?>
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='9' fill='%234F46E5'/%3E%3Ctext x='16' y='23' font-family='sans-serif' font-size='19' font-weight='800' fill='white' text-anchor='middle'%3ES%3C/text%3E%3C/svg%3E" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Sora:wght@600;700;800&display=swap" rel="stylesheet" />
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        :root{--ink:#111827;--muted:#6B7280;--line:#E5E7EB;--bg:#FFFFFF;--soft:#F7F7FB;--brand:#4F46E5;--brand2:#7C3AED;--accent:#FF7A18;--ok:#059669;--radius:16px}
        body{font-family:'Inter',system-ui,sans-serif;color:var(--ink);background:var(--bg);-webkit-font-smoothing:antialiased}
        a{color:inherit;text-decoration:none}
        .wrap{max-width:1180px;margin:0 auto;padding:0 22px}
        .topbar{background:linear-gradient(90deg,var(--brand),var(--brand2));color:#fff;font-size:12.5px;text-align:center;padding:8px 12px;font-weight:600;letter-spacing:.01em}
        header.site{position:sticky;top:0;z-index:80;background:rgba(255,255,255,.92);backdrop-filter:blur(14px);border-bottom:1px solid var(--line)}
        .nav{display:flex;align-items:center;gap:20px;padding:14px 0}
        .logo{display:flex;align-items:center;gap:10px;font-family:'Sora',sans-serif;font-weight:800;font-size:21px;letter-spacing:-.02em}
        .logo .mark{width:36px;height:36px;border-radius:11px;background:linear-gradient(135deg,var(--brand),var(--brand2));display:flex;align-items:center;justify-content:center;color:#fff;font-size:19px;box-shadow:0 6px 18px rgba(79,70,229,.35)}
        .search{flex:1;display:flex;align-items:center;gap:8px;background:var(--soft);border:1px solid var(--line);border-radius:12px;padding:10px 14px;min-width:140px}
        .search input{flex:1;border:none;background:none;outline:none;font-size:14px;font-family:inherit}
        .search button{border:none;background:var(--brand);color:#fff;border-radius:9px;padding:8px 14px;font-weight:600;cursor:pointer;font-size:13px}
        .nav-actions{display:flex;align-items:center;gap:8px}
        .icon-btn{width:42px;height:42px;border-radius:11px;border:1px solid var(--line);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:18px;position:relative}
        .cart-badge{position:absolute;top:-6px;right:-6px;background:var(--accent);color:#fff;font-size:10px;font-weight:800;border-radius:20px;padding:2px 6px}
        .cats{display:flex;gap:18px;padding:10px 0;overflow-x:auto;font-size:13.5px;font-weight:500;color:var(--muted)}
        .cats a{white-space:nowrap;padding:6px 2px;border-bottom:2px solid transparent}
        .cats a:hover{color:var(--ink);border-color:var(--brand)}
        .hero{position:relative;overflow:hidden;background:radial-gradient(1200px 500px at 80% -10%,#EEF2FF 0%,#FFFFFF 55%);border-bottom:1px solid var(--line)}
        .hero-in{display:grid;grid-template-columns:1.1fr .9fr;gap:40px;align-items:center;padding:64px 0}
        .hero h1{font-family:'Sora',sans-serif;font-size:48px;line-height:1.05;letter-spacing:-.03em;font-weight:800}
        .hero h1 em{font-style:normal;background:linear-gradient(120deg,var(--brand),var(--brand2));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
        .hero p{margin:18px 0 26px;color:var(--muted);font-size:17px;max-width:520px;line-height:1.6}
        .cta{display:inline-flex;align-items:center;gap:10px;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;padding:15px 30px;border-radius:13px;font-weight:700;box-shadow:0 12px 30px rgba(79,70,229,.32)}
        .cta.sec{background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:none;margin-left:10px}
        .hero-art{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .art{border-radius:18px;min-height:130px;display:flex;align-items:flex-end;padding:14px;color:#fff;font-weight:700;font-size:15px;background-size:cover;background-position:center;box-shadow:0 10px 30px rgba(17,24,39,.10)}
        .art.a{background:linear-gradient(135deg,#4F46E5,#7C3AED)}
        .art.b{background:linear-gradient(135deg,#FF7A18,#F59E0B)}
        .art.c{background:linear-gradient(135deg,#0EA5E9,#22D3EE)}
        .art.d{background:linear-gradient(135deg,#111827,#374151)}
        .trust{display:flex;flex-wrap:wrap;gap:22px;justify-content:center;padding:20px 0;border-bottom:1px solid var(--line);color:var(--muted);font-size:13px;font-weight:600}
        .trust b{color:var(--ink)}
        section.pad{padding:56px 0}
        .head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:26px}
        .head h2{font-family:'Sora',sans-serif;font-size:30px;letter-spacing:-.02em}
        .head a{color:var(--brand);font-weight:600;font-size:14px}
        .cat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
        .cat{border:1px solid var(--line);border-radius:var(--radius);padding:22px;background:#fff;transition:transform .2s,box-shadow .2s,border-color .2s}
        .cat:hover{transform:translateY(-4px);box-shadow:0 16px 36px rgba(17,24,39,.08);border-color:#D9DBFF}
        .cat .ic{font-size:30px}
        .cat h3{margin:12px 0 4px;font-size:16px;font-weight:700}
        .cat p{color:var(--muted);font-size:12.5px;line-height:1.5}
        .prod-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
        .card{border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;background:#fff;display:flex;flex-direction:column;transition:transform .2s,box-shadow .2s}
        .card:hover{transform:translateY(-4px);box-shadow:0 16px 36px rgba(17,24,39,.10)}
        .thumb{position:relative;aspect-ratio:1;background:#F3F4F6;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .thumb img{width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply;transition:transform .4s}
        .card:hover .thumb img{transform:scale(1.06)}
        .badge{position:absolute;top:10px;left:10px;background:var(--ok);color:#fff;font-size:10.5px;font-weight:700;padding:4px 9px;border-radius:20px}
        .badge.out{background:#6B7280}
        .card-body{padding:14px;display:flex;flex-direction:column;gap:6px;flex:1}
        .vendor{font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--brand);text-transform:uppercase}
        .card-body h3{font-size:13.5px;font-weight:600;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:38px}
        .price{font-size:17px;font-weight:800;margin-top:auto}
        .add{margin-top:8px;border:none;background:var(--ink);color:#fff;border-radius:10px;padding:10px;font-weight:600;cursor:pointer;font-size:13px;font-family:inherit}
        .add:hover{background:var(--brand)}
        .empty{padding:40px;text-align:center;color:var(--muted);border:1px dashed var(--line);border-radius:var(--radius)}
        .band{background:linear-gradient(120deg,#111827,#312E81);color:#fff;border-radius:22px;padding:42px;display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap}
        .band h2{font-family:'Sora',sans-serif;font-size:28px;letter-spacing:-.02em}
        .band p{color:#C7D2FE;margin-top:8px;max-width:520px}
        footer.site{background:#0B1020;color:#9CA3AF;padding:48px 0 26px;margin-top:20px}
        .fgrid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:28px}
        footer.site h4{color:#fff;font-size:14px;margin-bottom:12px}
        footer.site a{display:block;padding:4px 0;font-size:13px}
        footer.site a:hover{color:#fff}
        .fbot{border-top:1px solid #1F2937;margin-top:30px;padding-top:18px;font-size:12px;text-align:center}
        @media(max-width:960px){.hero-in{grid-template-columns:1fr;padding:40px 0}.cat-grid,.prod-grid{grid-template-columns:repeat(2,1fr)}.fgrid{grid-template-columns:1fr 1fr}}
        @media(max-width:600px){.hero h1{font-size:34px}.search{display:none}.cat-grid,.prod-grid{grid-template-columns:repeat(2,1fr);gap:12px}.topbar{font-size:11px}}
    </style>
</head>
<body>

<div class="topbar">Envío a toda Colombia · 3 a 5 días hábiles · Pago seguro</div>

<header class="site">
  <div class="wrap">
    <div class="nav">
      <a class="logo" href="/"><span class="mark">S</span>SONIKA</a>
      <form class="search" action="/buscar" method="get" role="search">
        <input type="search" name="q" placeholder="Busca instrumentos, marcas, referencias…" aria-label="Buscar" />
        <button type="submit">Buscar</button>
      </form>
      <div class="nav-actions">
        <a class="icon-btn" href="/cuenta" aria-label="Mi cuenta">👤</a>
        <a class="icon-btn" href="/carrito" aria-label="Carrito">🛒<span class="cart-badge" id="cartCount">0</span></a>
      </div>
    </div>
    <nav class="cats" aria-label="Categorías">
      <?php foreach ($categories as $c): ?>
        <a href="/<?= sonika_esc($c['slug']) ?>"><?= sonika_esc($c['name']) ?></a>
      <?php endforeach; ?>
      <a href="/ofertas" style="color:var(--accent);font-weight:700">Ofertas</a>
    </nav>
  </div>
</header>

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

<div class="truthwrap wrap">
  <div class="trust">
    <span>✅ <b>Compra 100% segura</b></span>
    <span>🚚 <b>Envíos nacionales</b></span>
    <span>🛡️ <b>Garantía oficial</b></span>
    <span>🎧 <b>Asesoría experta</b></span>
  </div>
</div>

<section class="pad wrap" id="categorias">
  <div class="head"><h2>Explora por categoría</h2><a href="#catalogo">Ver todo →</a></div>
  <div class="cat-grid">
    <?php foreach ($categories as $c): ?>
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
  <?php if (!empty($products)): ?>
    <div class="prod-grid">
      <?php foreach ($products as $p):
        $v = $p['variants'][0] ?? [];
        $img = $p['images'][0]['src'] ?? '';
        $price = $v['price'] ?? 0;
        $avail = !empty($v['available']);
      ?>
        <a class="card" href="/producto/<?= sonika_esc($p['handle']) ?>">
          <div class="thumb">
            <?php if ($img): ?><img src="<?= sonika_esc($img) ?>?width=480" alt="<?= sonika_esc($p['title']) ?>" loading="lazy" /><?php endif; ?>
            <span class="badge <?= $avail ? '' : 'out' ?>"><?= $avail ? 'Disponible' : 'Agotado' ?></span>
          </div>
          <div class="card-body">
            <span class="vendor"><?= sonika_esc($p['vendor'] ?? '') ?></span>
            <h3><?= sonika_esc($p['title']) ?></h3>
            <span class="price"><?= sonika_cop($price) ?></span>
            <button class="add" type="button" data-add="<?= sonika_esc($p['handle']) ?>">Agregar</button>
          </div>
        </a>
      <?php endforeach; ?>
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

<footer class="site">
  <div class="wrap">
    <div class="fgrid">
      <div>
        <a class="logo" href="/" style="color:#fff"><span class="mark">S</span>SONIKA</a>
        <p style="margin-top:14px;font-size:13px;line-height:1.6;max-width:300px">Instrumentos musicales, audio e iluminación profesional. Sonido de alto nivel para músicos, estudios y eventos.</p>
      </div>
      <div><h4>Tienda</h4><a href="/tienda">Todos los productos</a><a href="/ofertas">Ofertas</a><a href="/marcas">Marcas</a></div>
      <div><h4>Ayuda</h4><a href="/envios">Envíos y entregas</a><a href="/devoluciones">Devoluciones</a><a href="/garantia">Garantía</a><a href="/contacto">Contacto</a></div>
      <div><h4>Legal</h4><a href="/privacidad">Privacidad</a><a href="/terminos">Términos</a><a href="/cookies">Cookies</a></div>
    </div>
    <div class="fbot">© <?= date('Y') ?> SONIKA · Todos los derechos reservados.</div>
  </div>
</footer>

<?= CookieConsentService::render() ?>

<script>
(function(){
  var n=0, badge=document.getElementById('cartCount');
  document.querySelectorAll('[data-add]').forEach(function(b){
    b.addEventListener('click',function(e){
      e.preventDefault();
      n++; if(badge) badge.textContent=n;
      b.textContent='Agregado ✓'; b.style.background='#059669';
      setTimeout(function(){ b.textContent='Agregar'; b.style.background=''; },1400);
    });
  });
})();
</script>

</body>
</html>
