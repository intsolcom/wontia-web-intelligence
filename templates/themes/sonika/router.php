<?php
use App\Services\AnalyticsService;

require_once __DIR__ . '/lib.php';

AnalyticsService::track($_SERVER['REQUEST_URI'], $_SERVER['HTTP_REFERER'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '');

if (!function_exists('sonika_grid')) {
    function sonika_grid(array $products): void {
        if (empty($products)) { echo '<div class="empty">No encontramos productos en esta sección.</div>'; return; }
        echo '<div class="prod-grid">';
        foreach ($products as $p) sonika_product_card($p);
        echo '</div>';
    }
}
if (!function_exists('sonika_slice')) {
    function sonika_slice(array $items, int $perPage = 24): array {
        $page = max(1, (int)($_GET['page'] ?? 1));
        $total = count($items);
        $offset = ($page - 1) * $perPage;
        return ['items' => array_slice($items, $offset, $perPage), 'total' => $total, 'page' => $page, 'perPage' => $perPage];
    }
}
if (!function_exists('sonika_pager')) {
    function sonika_pager(int $total, int $perPage, int $current, string $base): void {
        $pages = (int)ceil($total / max(1, $perPage));
        if ($pages <= 1) return;
        $sep = str_contains($base, '?') ? '&' : '?';
        echo '<nav class="pager">';
        if ($current > 1) echo '<a class="pg" href="' . sonika_esc($base . $sep . 'page=' . ($current - 1)) . '">‹ Anterior</a>';
        for ($i = 1; $i <= $pages; $i++) {
            if ($pages > 14 && $i > 3 && $i < $pages - 2 && abs($i - $current) > 1) {
                if ($i === 4 || $i === $pages - 3) echo '<span class="pgap">…</span>';
                continue;
            }
            echo '<a class="pg' . ($i === $current ? ' on' : '') . '" href="' . sonika_esc($base . $sep . 'page=' . $i) . '">' . $i . '</a>';
        }
        if ($current < $pages) echo '<a class="pg" href="' . sonika_esc($base . $sep . 'page=' . ($current + 1)) . '">Siguiente ›</a>';
        echo '</nav>';
    }
}

$path = trim((string)$uri, '/');
$segments = $path === '' ? [] : explode('/', $path);

if (($segments[0] ?? '') === 'producto' && !empty($segments[1])) {
    $product = sonika_find($segments[1]);
    if ($product) {
        $cat = null;
        foreach (sonika_categories() as $c) { if (sonika_match($product, $c['kw'])) { $cat = $c; break; } }
        sonika_site_head(($product['title'] ?? 'Producto') . ' — SONIKA', mb_substr(strip_tags($product['body_html'] ?? ''), 0, 155));
        $crumbs = [];
        if ($cat) { $crumbs[$cat['name']] = '/' . $cat['slug']; }
        $crumbs[$product['title'] ?? 'Producto'] = null;
        sonika_breadcrumb($crumbs);
        $img = sonika_img($product, 900);
        $price = sonika_price($product);
        $cmp = sonika_compare($product);
        $avail = sonika_available($product);
        ?>
        <section class="wrap pdp">
          <div><div class="pdp-gallery"><?php if ($img): ?><img src="<?= sonika_esc($img) ?>" alt="<?= sonika_esc($product['title']) ?>" /><?php endif; ?></div></div>
          <div>
            <span class="pdp-vendor"><?= sonika_esc($product['vendor'] ?? '') ?></span>
            <h1><?= sonika_esc($product['title']) ?></h1>
            <div class="pricebox">
              <span class="price"><?= sonika_cop($price) ?></span>
              <?php if ($cmp > $price && $price > 0): ?><span class="cmp"><?= sonika_cop($cmp) ?></span><?php endif; ?>
            </div>
            <div class="meta">
              <span class="chip <?= $avail ? 'ok' : 'no' ?>"><?= $avail ? '● Disponible' : '● Agotado' ?></span>
              <?php if (!empty($product['product_type'])): ?><span class="chip"><?= sonika_esc($product['product_type']) ?></span><?php endif; ?>
              <span class="chip">SKU <?= sonika_esc(sonika_variant($product)['sku'] ?? '—') ?></span>
            </div>
            <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px;margin:8px 0 20px">
              <div class="qty">
                <button type="button" onclick="var i=document.getElementById('qty');i.value=Math.max(1,(+i.value)-1)">−</button>
                <input id="qty" type="text" value="1" inputmode="numeric" />
                <button type="button" onclick="var i=document.getElementById('qty');i.value=(+i.value)+1">+</button>
              </div>
              <button class="btn-buy" type="button" data-add="<?= sonika_esc($product['handle']) ?>" data-qty-target="qty" data-title="<?= sonika_esc($product['title']) ?>" data-price="<?= (float)$price ?>" data-img="<?= sonika_esc(sonika_img($product)) ?>">Agregar al carrito</button>
              <a class="cta sec" href="/carrito" style="margin:0">Ir al carrito</a>
            </div>
            <div class="trust" style="justify-content:flex-start;gap:16px;border:none;padding:0">
              <span>🚚 <b>Envío nacional</b></span><span>🛡️ <b>Garantía</b></span><span>💳 <b>Pago seguro</b></span>
            </div>
          </div>
        </section>

        <?php if (!empty($product['body_html'])): ?>
        <section class="wrap pdp-desc">
          <h3>Descripción del producto</h3>
          <div><?= sonika_safe_html((string)$product['body_html']) ?></div>
        </section>
        <?php endif; ?>

        <?php $rel = sonika_related($product, 4); if (!empty($rel)): ?>
        <section class="pad wrap">
          <div class="head"><h2>Productos relacionados</h2></div>
          <?php sonika_grid($rel); ?>
        </section>
        <?php endif; ?>
        <?php
        sonika_site_footer();
        return true;
    }
    return false;
}

$cat = sonika_category($path);
if ($cat) {
    $pg = sonika_slice(sonika_category_products($cat['slug']), 24);
    sonika_site_head($cat['name'] . ' — SONIKA', $cat['name'] . ': ' . $cat['tags'] . '. Compra en SONIKA con envío a toda Colombia.');
    sonika_breadcrumb([$cat['name'] => null]);
    ?>
    <section class="pad wrap">
      <div class="head"><h1><?= sonika_esc($cat['name']) ?></h1><a href="/tienda">Ver toda la tienda →</a></div>
      <div class="toolbar">
        <span class="resultcount"><?= (int)$pg['total'] ?> producto(s) en <?= sonika_esc($cat['name']) ?></span>
        <div class="filters">
          <?php foreach (sonika_categories() as $c): ?>
            <a class="<?= $c['slug'] === $cat['slug'] ? 'on' : '' ?>" href="/<?= sonika_esc($c['slug']) ?>"><?= sonika_esc($c['name']) ?></a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php sonika_grid($pg['items']); sonika_pager($pg['total'], $pg['perPage'], $pg['page'], '/' . $cat['slug']); ?>
    </section>
    <?php
    sonika_site_footer();
    return true;
}

switch ($segments[0] ?? '') {
    case 'tienda':
        $pg = sonika_slice(sonika_products(), 24);
        sonika_site_head('Tienda — SONIKA', 'Todos los instrumentos, audio, iluminación y accesorios disponibles en SONIKA.');
        sonika_breadcrumb(['Tienda' => null]);
        ?>
        <section class="pad wrap">
          <div class="head"><h1>Tienda</h1><span class="resultcount"><?= (int)$pg['total'] ?> productos</span></div>
          <div class="filters" style="margin-bottom:20px">
            <a href="/tienda" class="on">Todos</a>
            <?php foreach (sonika_categories() as $c): ?><a href="/<?= sonika_esc($c['slug']) ?>"><?= sonika_esc($c['name']) ?></a><?php endforeach; ?>
          </div>
          <?php sonika_grid($pg['items']); sonika_pager($pg['total'], $pg['perPage'], $pg['page'], '/tienda'); ?>
        </section>
        <?php
        sonika_site_footer();
        return true;

    case 'ofertas':
        $all = array_values(array_filter(sonika_products(), function ($p) { return sonika_compare($p) > sonika_price($p) && sonika_price($p) > 0; }));
        $pg = sonika_slice($all, 24);
        sonika_site_head('Ofertas — SONIKA', 'Instrumentos y equipos en oferta en SONIKA.');
        sonika_breadcrumb(['Ofertas' => null]);
        ?>
        <section class="pad wrap">
          <div class="head"><h1>Ofertas 🔥</h1><span class="resultcount"><?= (int)$pg['total'] ?> productos en promoción</span></div>
          <?php sonika_grid($pg['items']); sonika_pager($pg['total'], $pg['perPage'], $pg['page'], '/ofertas'); ?>
        </section>
        <?php
        sonika_site_footer();
        return true;

    case 'buscar':
        if (($segments[1] ?? '') === 'sugerencias') {
            $q = trim((string)($_GET['q'] ?? ''));
            $res = $q === '' ? [] : array_slice(sonika_search($q), 0, 6);
            $out = array_map(function ($p) {
                return [
                    'title' => (string)($p['title'] ?? ''),
                    'vendor' => (string)($p['vendor'] ?? ''),
                    'price' => sonika_price($p),
                    'thumb' => sonika_img($p, 90),
                    'url' => '/producto/' . ($p['handle'] ?? ''),
                ];
            }, $res);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['q' => $q, 'results' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return true;
        }
        $q = trim((string)($_GET['q'] ?? ''));
        $fmin = (float)($_GET['min'] ?? 0);
        $fmax = (float)($_GET['max'] ?? 0);
        $fmarca = trim((string)($_GET['marca'] ?? ''));
        $fcat = trim((string)($_GET['cat'] ?? ''));
        $orden = trim((string)($_GET['orden'] ?? ''));
        $oferta = isset($_GET['oferta']);
        $nuevo = isset($_GET['nuevo']);
        $disp = isset($_GET['disp']);
        $hasFilter = ($q !== '' || $fmin > 0 || $fmax > 0 || $fmarca !== '' || $fcat !== '' || $oferta || $nuevo || $disp);
        if (!$hasFilter) {
            $all = [];
        } elseif ($fcat !== '' && sonika_category($fcat)) {
            $all = sonika_category_products($fcat);
        } elseif ($q !== '') {
            $all = sonika_search($q);
        } else {
            $all = sonika_products();
        }
        $all = array_values(array_filter($all, function ($p) use ($fmin, $fmax, $fmarca, $oferta, $disp) {
            $price = sonika_price($p);
            if ($fmin > 0 && $price < $fmin) return false;
            if ($fmax > 0 && $price > $fmax) return false;
            if ($fmarca !== '' && strcasecmp((string)($p['vendor'] ?? ''), $fmarca) !== 0) return false;
            if ($oferta && !(sonika_compare($p) > $price && $price > 0)) return false;
            if ($disp && !sonika_available($p)) return false;
            return true;
        }));
        if ($orden === 'precio_asc') { usort($all, fn($a, $b) => sonika_price($a) <=> sonika_price($b)); }
        elseif ($orden === 'precio_desc') { usort($all, fn($a, $b) => sonika_price($b) <=> sonika_price($a)); }
        elseif ($orden === 'nuevo' && isset($all[0]['published_at'])) { usort($all, fn($a, $b) => strcmp((string)($b['published_at'] ?? ''), (string)($a['published_at'] ?? ''))); }
        $pg = sonika_slice($all, 24);
        sonika_site_head('Buscar' . ($q ? ': ' . $q : '') . ' — SONIKA', 'Resultados de búsqueda en SONIKA.');
        sonika_breadcrumb(['Buscar' => null]);
        ?>
        <section class="pad wrap">
          <div class="head"><h1><?= $q !== '' ? 'Resultados para “' . sonika_esc($q) . '”' : 'Buscar' ?></h1><span class="resultcount"><?= (int)$pg['total'] ?> resultado(s)</span></div>
          <?php sonika_grid($pg['items']); $__qs = $_GET; unset($__qs['page']); sonika_pager($pg['total'], $pg['perPage'], $pg['page'], '/buscar?' . http_build_query($__qs)); ?>
        </section>
        <?php
        sonika_site_footer();
        return true;

    case 'carrito':
        sonika_site_head('Carrito — SONIKA', 'Revisa los productos de tu carrito.');
        sonika_breadcrumb(['Carrito' => null]);
        ?>
        <section class="wrap" id="cartPage">
          <h1 style="font-family:'Sora',sans-serif;padding:24px 0 6px">Tu carrito</h1>
          <div class="cart-wrap"><div id="cartItems"></div><aside class="summary" id="cartSummary"></aside></div>
        </section>
        <?php
        sonika_site_footer();
        return true;

    case 'marcas':
        $brands = [];
        foreach (sonika_products() as $p) {
            $v = trim((string)($p['vendor'] ?? ''));
            if ($v === '') continue;
            $brands[$v] = ($brands[$v] ?? 0) + 1;
        }
        ksort($brands);
        sonika_site_head('Marcas — SONIKA', 'Marcas disponibles en SONIKA.');
        sonika_breadcrumb(['Marcas' => null]);
        ?>
        <section class="pad wrap">
          <div class="head"><h1>Marcas</h1><span class="resultcount"><?= count($brands) ?> marcas</span></div>
          <div class="cat-grid">
            <?php foreach ($brands as $name => $n): ?>
              <a class="cat" href="/buscar?q=<?= urlencode($name) ?>"><h3><?= sonika_esc($name) ?></h3><p><?= (int)$n ?> producto(s)</p></a>
            <?php endforeach; ?>
          </div>
        </section>
        <?php
        sonika_site_footer();
        return true;
}

return false;
