<?php
use App\Core\Config;
use App\Services\SeoService;
use App\Services\CookieConsentService;
use App\Services\AnalyticsService;

if (!function_exists('sonika_esc')) {
    function sonika_esc($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('sonika_cop')) {
    function sonika_cop($value): string { return '$' . number_format((float)$value, 0, ',', '.'); }
}
if (!function_exists('sonika_safe_html')) {
    function sonika_safe_html(string $html): string {
        $html = preg_replace('#<\s*script\b[^>]*>.*?<\s*/\s*script\s*>#is', '', $html);
        $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html);
        $html = preg_replace('#(href|src)\s*=\s*("|\')\s*javascript:[^"\']*("|\')#i', '$1="#"', $html);
        return $html;
    }
}
if (!function_exists('sonika_json')) {
    function sonika_json(string $url, int $ttl = 600): array {
        $cache = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'sonika_' . md5($url) . '.json';
        if (is_file($cache) && (time() - filemtime($cache)) < $ttl) {
            $c = json_decode((string)@file_get_contents($cache), true);
            if (is_array($c)) return $c;
        }
        $raw = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_FOLLOWLOCATION => true, CURLOPT_USERAGENT => 'SonikaCatalog/1.0 (+https://sonika.com.co)']);
            $raw = curl_exec($ch);
            curl_close($ch);
        }
        if (!$raw && ini_get('allow_url_fopen')) {
            $ctx = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: SonikaCatalog/1.0\r\n"]]);
            $raw = @file_get_contents($url, false, $ctx);
        }
        if (!is_string($raw) || $raw === '') return [];
        @file_put_contents($cache, $raw);
        $d = json_decode($raw, true);
        return is_array($d) ? $d : [];
    }
}
if (!function_exists('sonika_catalog')) {
    function sonika_catalog(): array {
        static $c = null;
        if ($c !== null) return $c;
        $f = __DIR__ . '/catalog.json';
        if (is_file($f)) {
            $c = json_decode((string)@file_get_contents($f), true) ?: [];
        } else {
            $c = sonika_json('https://www.miche.com.co/collections/all/products.json?limit=250');
        }
        return $c;
    }
}
if (!function_exists('sonika_products')) {
    function sonika_products(): array { $c = sonika_catalog(); return $c['products'] ?? []; }
}
if (!function_exists('sonika_find')) {
    function sonika_find(string $handle): ?array {
        foreach (sonika_products() as $p) { if (($p['handle'] ?? '') === $handle) return $p; }
        return null;
    }
}
if (!function_exists('sonika_variant')) {
    function sonika_variant(array $p): array { return $p['variants'][0] ?? []; }
}
if (!function_exists('sonika_price')) {
    function sonika_price(array $p): float { return (float)(sonika_variant($p)['price'] ?? 0); }
}
if (!function_exists('sonika_compare')) {
    function sonika_compare(array $p): float { return (float)(sonika_variant($p)['compare_at_price'] ?? 0); }
}
if (!function_exists('sonika_available')) {
    function sonika_available(array $p): bool { return !empty(sonika_variant($p)['available']); }
}
if (!function_exists('sonika_img')) {
    function sonika_img(array $p, int $w = 480): string {
        $src = $p['images'][0]['src'] ?? '';
        return $src ? $src . (str_contains($src, '?') ? '&' : '?') . 'width=' . $w : '';
    }
}
if (!function_exists('sonika_categories')) {
    function sonika_categories(): array {
        return [
            ['slug' => 'percusion',     'name' => 'Percusión',   'icon' => '🥁', 'tags' => 'Baterías · Redoblantes · Cajones', 'kw' => ['PERCUS','BATER','REDOBL','PLATILL','CONGA','BONGO','DJEMBE','CAJON','CAJA','TIMBAL','MARAC','CAMPAN','PANDER','TAMBOR','GUIRO','CLAVE','CASTAN','XILOF','METALOF','PARCHE','BAQUETA','SHAKER','GUACHE']],
            ['slug' => 'guitarras',     'name' => 'Cuerdas',     'icon' => '🎸', 'tags' => 'Guitarras · Bajos · Ukeleles',    'kw' => ['GUITAR','BAJO','UKELELE','VIOLIN','VIOLA','VIOLONCHELO','CUERDA','PICK','CORREA','CAPODASTRO','ENCOR','PUENTE','CLAVIJERO']],
            ['slug' => 'acordeon',      'name' => 'Vientos',     'icon' => '🪗', 'tags' => 'Acordeones · Saxos · Trompetas','kw' => ['ACORDE','ARMONICA','CLARINET','FLAUTA','GAITA','MELODICA','SAXOF','SAX','TROMB','TROMPET','CORNO','OBOE','FAGOT','OCARINA']],
            ['slug' => 'pianos',        'name' => 'Teclados',    'icon' => '🎹', 'tags' => 'Pianos · Sintetizadores',        'kw' => ['PIANO','TECLADO','SYNTH','SINTETIZ','CONTROLADOR','CASIO','CELVIANO','PRIVIA']],
            ['slug' => 'amplificadores', 'name' => 'Audio',       'icon' => '🔊', 'tags' => 'Amplificadores · Mixers · Parlantes','kw' => ['AMPLIF','CABINA','MIXER','PARLANT','PLANTA','PROCESAD','INTERFAZ','INTERFACE','MONITOR','AUDIFONO','CONSOLA','POTENCIA','CROSSOVER','ECUALIZ','DI ']],
            ['slug' => 'microfono-miche', 'name' => 'Micrófonos', 'icon' => '🎤', 'tags' => 'Shure · Inalámbricos',          'kw' => ['MICROFON','MICRO','SHURE','INALAMBR']],
            ['slug' => 'cabeza-movil',  'name' => 'Iluminación',  'icon' => '💡', 'tags' => 'Cabezas móviles · Par LED',     'kw' => ['CABEZA','PAR LED','PARLED','LED','HUMO','TRUSS','TENSOR','DIMMER','STROBO','MOVIL','LUZ','LUCE','BLINDER','REFLECTOR']],
            ['slug' => 'cuerdas-guitarras', 'name' => 'Accesorios','icon' => '🎛️', 'tags' => 'Cuerdas · Correas · Forros',   'kw' => ['FORRO','FUNDA','SOPORTE','STAND','SILLA','ATRIL','RACK','CLAMP','CABLE','ACOPLE','LIMPIADOR','PROTECT','EMPUNADURA','AFINADOR','SORDINA','SLIDE','BANQUETA']],
        ];
    }
}
if (!function_exists('sonika_category')) {
    function sonika_category(string $slug): ?array {
        foreach (sonika_categories() as $c) { if ($c['slug'] === $slug) return $c; }
        return null;
    }
}
if (!function_exists('sonika_match')) {
    function sonika_match(array $p, array $kw): bool {
        $hay = strtoupper(($p['title'] ?? '') . ' ' . ($p['product_type'] ?? '') . ' ' . ($p['vendor'] ?? ''));
        foreach ($kw as $k) { if (str_contains($hay, $k)) return true; }
        return false;
    }
}
if (!function_exists('sonika_category_products')) {
    function sonika_category_products(string $slug): array {
        $cat = sonika_category($slug);
        if (!$cat) return [];
        $out = [];
        foreach (sonika_products() as $p) { if (sonika_match($p, $cat['kw'])) $out[] = $p; }
        return $out;
    }
}
if (!function_exists('sonika_search')) {
    function sonika_search(string $q): array {
        $q = trim(mb_strtolower($q));
        if ($q === '') return [];
        $terms = preg_split('/\s+/', $q) ?: [];
        $out = [];
        foreach (sonika_products() as $p) {
            $hay = mb_strtolower(($p['title'] ?? '') . ' ' . ($p['vendor'] ?? '') . ' ' . ($p['product_type'] ?? ''));
            $ok = true;
            foreach ($terms as $t) { if ($t !== '' && !str_contains($hay, $t)) { $ok = false; break; } }
            if ($ok) $out[] = $p;
        }
        return $out;
    }
}
if (!function_exists('sonika_related')) {
    function sonika_related(array $p, int $n = 4): array {
        $type = strtoupper($p['product_type'] ?? '');
        $out = [];
        foreach (sonika_products() as $q) {
            if ($q['handle'] === ($p['handle'] ?? '')) continue;
            if ($type !== '' && strtoupper($q['product_type'] ?? '') === $type) { $out[] = $q; if (count($out) >= $n) break; }
        }
        if (count($out) < $n) {
            $vendor = strtoupper($p['vendor'] ?? '');
            foreach (sonika_products() as $q) {
                if ($q['handle'] === ($p['handle'] ?? '')) continue;
                if (strtoupper($q['vendor'] ?? '') === $vendor && !in_array($q, $out, true)) { $out[] = $q; if (count($out) >= $n) break; }
            }
        }
        return array_slice($out, 0, $n);
    }
}

if (!function_exists('sonika_site_head')) {
    function sonika_site_head(string $title, string $desc = ''): void {
        $pageMeta = [
            'title' => $title,
            'meta_title' => $title,
            'meta_description' => $desc ?: 'SONIKA — Instrumentos musicales, audio e iluminación. Envíos a toda Colombia.',
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
    <style><?= sonika_css() ?></style>
</head>
<body>
<div class="topbar">Envío a toda Colombia · 3 a 5 días hábiles · Pago seguro</div>
<header class="site">
  <div class="wrap">
    <div class="nav">
      <a class="logo" href="/"><span class="mark">S</span>SONIKA</a>
      <form class="search" action="/buscar" method="get" role="search">
        <input type="search" name="q" placeholder="Busca instrumentos, marcas, referencias…" aria-label="Buscar" value="<?= sonika_esc($_GET['q'] ?? '') ?>" />
        <button type="submit">Buscar</button>
      </form>
      <div class="nav-actions">
        <a class="icon-btn" href="/cuenta" aria-label="Mi cuenta">👤</a>
        <a class="icon-btn" href="/carrito" aria-label="Carrito">🛒<span class="cart-badge" id="cartCount">0</span></a>
      </div>
    </div>
    <nav class="cats" aria-label="Categorías">
      <?php foreach (sonika_categories() as $c): ?>
        <a href="/<?= sonika_esc($c['slug']) ?>"><?= sonika_esc($c['name']) ?></a>
      <?php endforeach; ?>
      <a href="/ofertas" style="color:var(--accent);font-weight:700">Ofertas</a>
    </nav>
  </div>
</header>
<main>
<?php
    }
}
if (!function_exists('sonika_breadcrumb')) {
    function sonika_breadcrumb(array $items): void {
        echo '<nav class="crumb wrap"><a href="/">Inicio</a>';
        foreach ($items as $label => $href) {
            echo ' <span>/</span> ';
            echo $href ? '<a href="' . sonika_esc($href) . '">' . sonika_esc($label) . '</a>' : '<span>' . sonika_esc($label) . '</span>';
        }
        echo '</nav>';
    }
}
if (!function_exists('sonika_product_card')) {
    function sonika_product_card(array $p): void {
        $img = sonika_img($p);
        $avail = sonika_available($p);
        $price = sonika_price($p);
        $cmp = sonika_compare($p);
        ?>
        <div class="card">
          <a class="thumb" href="/producto/<?= sonika_esc($p['handle']) ?>">
            <?php if ($img): ?><img src="<?= sonika_esc($img) ?>" alt="<?= sonika_esc($p['title']) ?>" loading="lazy" /><?php endif; ?>
            <span class="badge <?= $avail ? '' : 'out' ?>"><?= $avail ? 'Disponible' : 'Agotado' ?></span>
            <?php if ($cmp > $price && $price > 0): ?><span class="badge off">Oferta</span><?php endif; ?>
          </a>
          <div class="card-body">
            <span class="vendor"><?= sonika_esc($p['vendor'] ?? '') ?></span>
            <a class="ptitle" href="/producto/<?= sonika_esc($p['handle']) ?>"><?= sonika_esc($p['title']) ?></a>
            <div class="pricebox">
              <span class="price"><?= sonika_cop($price) ?></span>
              <?php if ($cmp > $price && $price > 0): ?><span class="cmp"><?= sonika_cop($cmp) ?></span><?php endif; ?>
            </div>
            <button class="add" type="button" data-add="<?= sonika_esc($p['handle']) ?>" data-title="<?= sonika_esc($p['title']) ?>" data-price="<?= (float)$price ?>" data-img="<?= sonika_esc($img) ?>">Agregar</button>
          </div>
        </div>
        <?php
    }
}
if (!function_exists('sonika_site_footer')) {
    function sonika_site_footer(): void {
        ?>
</main>
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
<script><?= sonika_js() ?></script>
</body>
</html>
<?php
    }
}
if (!function_exists('sonika_css')) {
    function sonika_css(): string {
        return <<<'CSS'
        *{box-sizing:border-box;margin:0;padding:0}
        :root{--ink:#111827;--muted:#6B7280;--line:#E5E7EB;--soft:#F7F7FB;--brand:#4F46E5;--brand2:#7C3AED;--accent:#FF7A18;--ok:#059669;--radius:16px}
        body{font-family:'Inter',system-ui,sans-serif;color:var(--ink);background:#fff;-webkit-font-smoothing:antialiased}
        a{color:inherit;text-decoration:none}
        .wrap{max-width:1180px;margin:0 auto;padding:0 22px}
        .topbar{background:linear-gradient(90deg,var(--brand),var(--brand2));color:#fff;font-size:12.5px;text-align:center;padding:8px 12px;font-weight:600}
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
        .hero{overflow:hidden;background:radial-gradient(1200px 500px at 80% -10%,#EEF2FF 0%,#fff 55%);border-bottom:1px solid var(--line)}
        .hero-in{display:grid;grid-template-columns:1.1fr .9fr;gap:40px;align-items:center;padding:64px 0}
        .hero h1{font-family:'Sora',sans-serif;font-size:48px;line-height:1.05;letter-spacing:-.03em;font-weight:800}
        .hero h1 em{font-style:normal;background:linear-gradient(120deg,var(--brand),var(--brand2));-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent}
        .hero p{margin:18px 0 26px;color:var(--muted);font-size:17px;max-width:520px;line-height:1.6}
        .cta{display:inline-flex;align-items:center;gap:10px;background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;padding:15px 30px;border-radius:13px;font-weight:700;box-shadow:0 12px 30px rgba(79,70,229,.32)}
        .cta.sec{background:#fff;color:var(--ink);border:1px solid var(--line);box-shadow:none;margin-left:10px}
        .hero-art{display:grid;grid-template-columns:1fr 1fr;gap:14px}
        .art{border-radius:18px;min-height:130px;display:flex;align-items:flex-end;padding:14px;color:#fff;font-weight:700;font-size:15px}
        .art.a{background:linear-gradient(135deg,#4F46E5,#7C3AED)}.art.b{background:linear-gradient(135deg,#FF7A18,#F59E0B)}.art.c{background:linear-gradient(135deg,#0EA5E9,#22D3EE)}.art.d{background:linear-gradient(135deg,#111827,#374151)}
        .trust{display:flex;flex-wrap:wrap;gap:22px;justify-content:center;padding:20px 0;border-bottom:1px solid var(--line);color:var(--muted);font-size:13px;font-weight:600}
        .trust b{color:var(--ink)}
        section.pad{padding:48px 0}
        .head{display:flex;align-items:flex-end;justify-content:space-between;margin-bottom:26px;gap:12px;flex-wrap:wrap}
        .head h1,.head h2{font-family:'Sora',sans-serif;font-size:30px;letter-spacing:-.02em}
        .head a{color:var(--brand);font-weight:600;font-size:14px}
        .crumb{font-size:13px;color:var(--muted);padding:18px 22px 0;max-width:1180px;margin:0 auto}
        .crumb a:hover{color:var(--brand)}.crumb span{color:#9CA3AF}
        .cat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px}
        .cat{border:1px solid var(--line);border-radius:var(--radius);padding:22px;background:#fff;transition:transform .2s,box-shadow .2s,border-color .2s}
        .cat:hover{transform:translateY(-4px);box-shadow:0 16px 36px rgba(17,24,39,.08);border-color:#D9DBFF}
        .cat .ic{font-size:30px}.cat h3{margin:12px 0 4px;font-size:16px;font-weight:700}.cat p{color:var(--muted);font-size:12.5px;line-height:1.5}
        .prod-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px}
        .card{border:1px solid var(--line);border-radius:var(--radius);overflow:hidden;background:#fff;display:flex;flex-direction:column;transition:transform .2s,box-shadow .2s}
        .card:hover{transform:translateY(-4px);box-shadow:0 16px 36px rgba(17,24,39,.10)}
        .thumb{position:relative;aspect-ratio:1;background:#F3F4F6;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .thumb img{width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply;transition:transform .4s}
        .card:hover .thumb img{transform:scale(1.06)}
        .badge{position:absolute;top:10px;left:10px;background:var(--ok);color:#fff;font-size:10.5px;font-weight:700;padding:4px 9px;border-radius:20px}
        .badge.out{background:#6B7280}.badge.off{background:var(--accent);left:auto;right:10px}
        .card-body{padding:14px;display:flex;flex-direction:column;gap:6px;flex:1}
        .vendor{font-size:11px;font-weight:700;letter-spacing:.06em;color:var(--brand);text-transform:uppercase}
        .ptitle{font-size:13.5px;font-weight:600;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:38px}
        .pricebox{display:flex;align-items:baseline;gap:8px;margin-top:auto}
        .price{font-size:17px;font-weight:800}.cmp{font-size:12.5px;color:#9CA3AF;text-decoration:line-through}
        .add{margin-top:8px;border:none;background:var(--ink);color:#fff;border-radius:10px;padding:10px;font-weight:600;cursor:pointer;font-size:13px;font-family:inherit}
        .add:hover{background:var(--brand)}
        .empty{padding:40px;text-align:center;color:var(--muted);border:1px dashed var(--line);border-radius:var(--radius)}
        .band{background:linear-gradient(120deg,#111827,#312E81);color:#fff;border-radius:22px;padding:42px;display:flex;align-items:center;justify-content:space-between;gap:24px;flex-wrap:wrap}
        .band h2{font-family:'Sora',sans-serif;font-size:28px;letter-spacing:-.02em}.band p{color:#C7D2FE;margin-top:8px;max-width:520px}
        .pdp{display:grid;grid-template-columns:1.05fr .95fr;gap:44px;padding:28px 0 10px}
        .pdp-gallery{border:1px solid var(--line);border-radius:20px;overflow:hidden;background:#F8F8FB;aspect-ratio:1;display:flex;align-items:center;justify-content:center}
        .pdp-gallery img{width:100%;height:100%;object-fit:contain;mix-blend-mode:multiply}
        .pdp-vendor{font-size:12px;font-weight:700;letter-spacing:.08em;color:var(--brand);text-transform:uppercase}
        .pdp h1{font-family:'Sora',sans-serif;font-size:32px;line-height:1.15;letter-spacing:-.02em;margin:8px 0 14px}
        .pdp .price{font-size:30px}
        .pdp .meta{display:flex;gap:10px;flex-wrap:wrap;margin:14px 0 20px}
        .chip{font-size:12px;font-weight:600;padding:6px 12px;border-radius:20px;background:var(--soft);border:1px solid var(--line)}
        .chip.ok{background:#ECFDF5;color:#047857;border-color:#A7F3D0}
        .chip.no{background:#F3F4F6;color:#6B7280}
        .qty{display:inline-flex;align-items:center;border:1px solid var(--line);border-radius:12px;overflow:hidden;margin-right:12px}
        .qty button{width:40px;height:46px;border:none;background:#fff;font-size:18px;cursor:pointer}
        .qty input{width:46px;height:46px;border:none;text-align:center;font-size:15px;font-family:inherit}
        .btn-buy{background:linear-gradient(135deg,var(--brand),var(--brand2));color:#fff;border:none;border-radius:12px;padding:14px 34px;font-weight:700;font-size:15px;cursor:pointer;box-shadow:0 10px 26px rgba(79,70,229,.3)}
        .pdp-desc{margin-top:34px;padding-top:26px;border-top:1px solid var(--line)}
        .pdp-desc h3{font-family:'Sora',sans-serif;font-size:18px;margin-bottom:12px}
        .pdp-desc p{margin:10px 0;line-height:1.7;color:#374151}
        .pdp-desc ul{margin:10px 0 10px 20px;line-height:1.7;color:#374151}
        .toolbar{display:flex;gap:12px;align-items:center;justify-content:space-between;flex-wrap:wrap;margin-bottom:18px}
        .resultcount{color:var(--muted);font-size:14px}
        .filters{display:flex;gap:8px;flex-wrap:wrap}
        .filters a{font-size:13px;font-weight:600;padding:8px 14px;border:1px solid var(--line);border-radius:20px;background:#fff}
        .filters a.on,.filters a:hover{border-color:var(--brand);color:var(--brand)}
        .cart-wrap{display:grid;grid-template-columns:1.6fr 1fr;gap:30px;padding:24px 0 40px;align-items:start}
        .cart-item{display:grid;grid-template-columns:80px 1fr auto;gap:14px;align-items:center;padding:14px 0;border-bottom:1px solid var(--line)}
        .cart-item img{width:80px;height:80px;object-fit:contain;background:#F3F4F6;border-radius:12px;mix-blend-mode:multiply}
        .summary{border:1px solid var(--line);border-radius:18px;padding:22px;position:sticky;top:120px}
        .summary .row{display:flex;justify-content:space-between;padding:8px 0;font-size:14px}
        .summary .total{font-size:20px;font-weight:800;border-top:1px solid var(--line);margin-top:10px;padding-top:14px}
        footer.site{background:#0B1020;color:#9CA3AF;padding:48px 0 26px;margin-top:20px}
        .fgrid{display:grid;grid-template-columns:2fr 1fr 1fr 1fr;gap:28px}
        footer.site h4{color:#fff;font-size:14px;margin-bottom:12px}
        footer.site a{display:block;padding:4px 0;font-size:13px}footer.site a:hover{color:#fff}
        .fbot{border-top:1px solid #1F2937;margin-top:30px;padding-top:18px;font-size:12px;text-align:center}
        .toast{position:fixed;bottom:24px;right:24px;background:#111827;color:#fff;padding:14px 20px;border-radius:12px;font-size:14px;font-weight:600;box-shadow:0 12px 30px rgba(0,0,0,.25);opacity:0;transform:translateY(10px);transition:.3s;z-index:9998}
        .toast.show{opacity:1;transform:translateY(0)}
        .pager{display:flex;flex-wrap:wrap;gap:6px;justify-content:center;align-items:center;margin:34px 0 6px}
        .pg{display:inline-flex;align-items:center;justify-content:center;min-width:38px;height:38px;padding:0 12px;border:1px solid var(--line);border-radius:10px;background:#fff;font-size:13.5px;font-weight:600;color:var(--ink)}
        .pg:hover{border-color:var(--brand);color:var(--brand)}
        .pg.on{background:var(--brand);border-color:var(--brand);color:#fff}
        .pgap{padding:0 6px;color:#9CA3AF}
        @media(max-width:960px){.hero-in,.pdp,.cart-wrap{grid-template-columns:1fr}.cat-grid,.prod-grid{grid-template-columns:repeat(2,1fr)}.fgrid{grid-template-columns:1fr 1fr}.summary{position:static}}
        @media(max-width:600px){.hero h1{font-size:34px}.search{display:none}.cat-grid,.prod-grid{grid-template-columns:repeat(2,1fr);gap:12px}.topbar{font-size:11px}}
CSS;
    }
}
if (!function_exists('sonika_js')) {
    function sonika_js(): string {
        return <<<'JS'
(function(){
  var CART='sonika_cart';
  function read(){try{return JSON.parse(localStorage.getItem(CART))||[]}catch(e){return []}}
  function count(){return read().reduce(function(a,i){return a+(i.qty||0)},0)}
  function paint(){document.querySelectorAll('#cartCount').forEach(function(el){el.textContent=count()})}
  function write(c){localStorage.setItem(CART,JSON.stringify(c));paint();renderCart()}
  function money(n){return '$'+Number(n||0).toLocaleString('es-CO')}
  function esc(s){var d=document.createElement('div');d.textContent=(s==null?'':String(s));return d.innerHTML}
  function toast(m){var t=document.createElement('div');t.className='toast';t.textContent=m;document.body.appendChild(t);requestAnimationFrame(function(){t.classList.add('show')});setTimeout(function(){t.classList.remove('show');setTimeout(function(){t.remove()},300)},1800)}
  window.SonikaCart={
    add:function(item,qty){qty=qty||1;var c=read();var f=c.find(function(i){return i.handle===item.handle});if(f){f.qty+=qty}else{item.qty=qty;c.push(item)}write(c);toast('Agregado al carrito')},
    set:function(h,q){write(read().map(function(i){if(i.handle===h)i.qty=q;return i}).filter(function(i){return i.qty>0}))},
    remove:function(h){write(read().filter(function(i){return i.handle!==h}))},
    clear:function(){write([])},
    items:read, count:count
  };
  function renderCart(){
    var wrap=document.getElementById('cartItems'); if(!wrap) return;
    var sum=document.getElementById('cartSummary');
    var items=read();
    if(!items.length){wrap.innerHTML='<div class="empty">Tu carrito está vacío. <a href="/tienda" style="color:var(--brand);font-weight:600">Explorar tienda →</a></div>'; if(sum) sum.innerHTML=''; return;}
    var subtotal=0;
    wrap.innerHTML=items.map(function(i){subtotal+=(i.price||0)*(i.qty||0);var q=i.qty||1;
      return '<div class="cart-item"><img src="'+esc(i.img||'')+'" alt=""><div><a href="/producto/'+esc(i.handle)+'" style="font-weight:600">'+esc(i.title||'')+'</a><div style="color:var(--muted);font-size:14px;margin-top:4px">'+money(i.price)+'</div></div><div style="text-align:right"><div class="qty"><button type="button" onclick="SonikaCart.set(\''+esc(i.handle)+'\','+(q-1)+')">−</button><input value="'+q+'" readonly><button type="button" onclick="SonikaCart.set(\''+esc(i.handle)+'\','+(q+1)+')">+</button></div><a href="#" onclick="event.preventDefault();SonikaCart.remove(\''+esc(i.handle)+'\')" style="display:block;margin-top:8px;color:#DC2626;font-size:13px">Eliminar</a></div></div>';
    }).join('');
    if(sum) sum.innerHTML='<h3 style="font-family:Sora,sans-serif;margin-bottom:10px">Resumen</h3><div class="row"><span>Subtotal</span><span>'+money(subtotal)+'</span></div><div class="row"><span>Envío</span><span>Calculado al pagar</span></div><div class="row total"><span>Total</span><span>'+money(subtotal)+'</span></div><button class="btn-buy" style="width:100%;margin-top:16px" onclick="alert(\'Checkout en construcción (Milestone 3).\')">Finalizar compra</button><a class="cta sec" href="/tienda" style="width:100%;margin:10px 0 0;justify-content:center">Seguir comprando</a>';
  }
  paint();
  document.querySelectorAll('[data-add]').forEach(function(b){
    b.addEventListener('click',function(e){e.preventDefault();
      var q=b.dataset.qtyTarget?parseInt((document.getElementById(b.dataset.qtyTarget)||{}).value||'1',10):1;
      if(!(q>0)) q=1;
      window.SonikaCart.add({handle:b.dataset.add,title:b.dataset.title,price:parseFloat(b.dataset.price),img:b.dataset.img},q);
      var o=b.textContent;b.textContent='Agregado ✓';b.style.background='#059669';setTimeout(function(){b.textContent=o;b.style.background=''},1200);
    });
  });
  renderCart();
})();
JS;
    }
}
