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

        <?= \App\Widgets\WidgetRegistry::render('product-reviews', ['product_ref' => (string)$product['handle']]) ?>

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
        if (($segments[1] ?? '') === 'live') {
            $all = sonika_filter_products($_GET);
            $pg = sonika_slice($all, 24);
            ob_start();
            sonika_grid($pg['items']);
            $__qs = $_GET; unset($__qs['page']);
            sonika_pager($pg['total'], $pg['perPage'], $pg['page'], '/buscar?' . http_build_query($__qs));
            $html = ob_get_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'count' => (int)$pg['total'], 'html' => $html], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            return true;
        }
        $q = trim((string)($_GET['q'] ?? ''));
        $all = sonika_filter_products($_GET);
        try { (new \App\Services\SearchAnalyticsService())->log($q, ['marca' => (string)($_GET['marca'] ?? ''), 'tipo' => (string)($_GET['tipo'] ?? ''), 'cat' => (string)($_GET['cat'] ?? ''), 'min' => (float)($_GET['min'] ?? 0), 'max' => (float)($_GET['max'] ?? 0), 'desc' => (float)($_GET['desc'] ?? 0), 'oferta' => !empty($_GET['oferta']), 'nuevo' => !empty($_GET['nuevo']), 'disp' => !empty($_GET['disp']), 'orden' => (string)($_GET['orden'] ?? '')], count($all)); } catch (\Throwable $e) {}
        $pg = sonika_slice($all, 24);
        sonika_site_head('Buscar' . ($q ? ': ' . $q : '') . ' — SONIKA', 'Resultados de búsqueda en SONIKA.');
        sonika_breadcrumb(['Buscar' => null]);
        ?>
        <section class="pad wrap">
          <div class="head"><h1><?= $q !== '' ? 'Resultados para “' . sonika_esc($q) . '”' : 'Buscar' ?></h1><span class="resultcount" id="skCount"><?= (int)$pg['total'] ?> resultado(s)</span></div>
          <div id="skResults"><?php sonika_grid($pg['items']); $__qs = $_GET; unset($__qs['page']); sonika_pager($pg['total'], $pg['perPage'], $pg['page'], '/buscar?' . http_build_query($__qs)); ?></div>
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

    case 'cuenta':
        sonika_site_head('Mi cuenta — SONIKA', 'Administra tu cuenta, pedidos, direcciones y favoritos.');
        sonika_breadcrumb(['Mi cuenta' => null]);
        ?>
        <section class="wrap" style="padding:24px 0 48px;max-width:960px">
          <h1 style="font-family:'Sora',sans-serif;margin-bottom:14px">Mi cuenta</h1>
          <div id="acct">Cargando…</div>
        </section>
        <script>var SITE=<?= (int)\App\Core\Config::get('SITE_ID', '1') ?>;
        (function(){
          var box=document.getElementById('acct');
          function esc(s){var d=document.createElement('div');d.textContent=(s==null?'':String(s));return d.innerHTML}
          function money(n){return '$'+Math.round(n||0).toLocaleString('es-CO')}
          function inp(id,ph,type,val){return '<input id="'+id+'" type="'+(type||'text')+'" placeholder="'+ph+'" value="'+(val||'')+'" style="width:100%;border:1px solid #E6E6E6;border-radius:9px;padding:10px;font:inherit;font-size:13px;margin-top:6px">'}
          function btn(id,label){return '<button id="'+id+'" style="margin-top:10px;border:none;border-radius:10px;background:#F20D16;color:#fff;font-weight:700;font-size:13px;padding:11px 20px;cursor:pointer">'+label+'</button>'}
          function link(id,label){return ' <a href="#" id="'+id+'" style="font-size:13px;color:#777;margin-left:10px">'+label+'</a>'}
          function api(u,o){return fetch(u,Object.assign({headers:{'Content-Type':'application/json; charset=UTF-8'}},o||{})).then(function(r){return r.json()})}
          function qs(){var o={};location.search.replace(/^\?/,'').split('&').forEach(function(p){if(!p)return;var kv=p.split('=');o[kv[0]]=decodeURIComponent(kv[1]||'')});return o}
          function login(err){box.innerHTML='<div class="card" style="padding:22px;max-width:440px"><h3>Iniciar sesión</h3>'+(err?'<div style="color:#DC2626;font-size:13px;margin:6px 0">'+esc(err)+'</div>':'')+inp('ac-email','Email','email')+inp('ac-pass','Contraseña','password')+btn('ac-login','Entrar')+link('ac-toreg','Crear cuenta')+link('ac-forgot','¿Olvidaste tu contraseña?')+'</div>';
            document.getElementById('ac-login').onclick=async function(){var r=await api('/api/v1/public/account/login',{method:'POST',body:JSON.stringify({email:document.getElementById('ac-email').value,password:document.getElementById('ac-pass').value})});if(r.ok){location.search=''}else{login(r.message||'Credenciales inválidas')}};
            document.getElementById('ac-toreg').onclick=function(e){e.preventDefault();location.search='?tab=register'};
            document.getElementById('ac-forgot').onclick=function(e){e.preventDefault();forgot()}}
          function forgot(){box.innerHTML='<div class="card" style="padding:22px;max-width:440px"><h3>Recuperar contraseña</h3><p style="color:#777;font-size:13px;margin:6px 0">Te enviamos un código a tu correo registrado.</p>'+inp('fg-email','Email','email')+btn('fg-go','Enviar código')+'<div id="fg-step2" hidden style="margin-top:6px">'+inp('fg-code','Código (6 dígitos)')+inp('fg-pass','Nueva contraseña (mín 8)','password')+btn('fg-reset','Restablecer')+'</div>' + link('fg-back','Volver') + '</div>';
            document.getElementById('fg-go').onclick=async function(){await api('/api/v1/public/account/forgot',{method:'POST',body:JSON.stringify({email:document.getElementById('fg-email').value})});document.getElementById('fg-step2').hidden=false};
            document.getElementById('fg-reset').onclick=async function(){var r=await api('/api/v1/public/account/reset',{method:'POST',body:JSON.stringify({email:document.getElementById('fg-email').value,code:document.getElementById('fg-code').value,password:document.getElementById('fg-pass').value})});if(r.ok){location.search=''}else{alert(r.message||'Código inválido')}};
            document.getElementById('fg-back').onclick=function(e){e.preventDefault();location.search=''}}
          function reg(){box.innerHTML='<div class="card" style="padding:22px;max-width:440px"><h3>Crear cuenta</h3>'+inp('rg-name','Nombre')+inp('rg-email','Email','email')+inp('rg-pass','Contraseña (mín 8)','password')+btn('rg-go','Crear cuenta')+link('rg-to','Ya tengo cuenta')+'</div>';
            document.getElementById('rg-go').onclick=async function(){var r=await api('/api/v1/public/account/register',{method:'POST',body:JSON.stringify({name:document.getElementById('rg-name').value,email:document.getElementById('rg-email').value,password:document.getElementById('rg-pass').value})});if(r.ok){location.search=''}else{login(r.message||'No se pudo crear')}};
            document.getElementById('rg-to').onclick=function(e){e.preventDefault();location.search=''}}
          function account(d){var c=d.customer||{},tab=qs().tab||'summary';
            function tb(id,l){return '<a href="?tab='+id+'" style="padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;text-decoration:none;'+(tab===id?'background:#F20D16;color:#fff':'color:#555')+'">'+l+'</a>'}
            box.innerHTML='<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">'+tb('summary','Resumen')+tb('orders','Pedidos')+tb('favorites','Favoritos')+tb('addresses','Direcciones')+tb('profile','Perfil')+tb('reviews','Reseñas')+tb('points','Puntos')+'</div><div id="ac-body"></div>';
            var b=document.getElementById('ac-body');
            if(tab==='orders'){$o(b)} else if(tab==='favorites'){$f(b)} else if(tab==='addresses'){$a(b,d.addresses||[])} else if(tab==='profile'){$p(b,c)} else if(tab==='reviews'){$rv(b)} else if(tab==='points'){$pts(b)} else {b.innerHTML='<div class="card" style="padding:20px"><p>Hola, <b>'+esc(c.name||c.email)+'</b>.</p><p style="color:#777;font-size:13px;margin-top:6px">Pedidos: '+(c.orders_count||0)+'</p></div>'+(c.email_verified_at?'':'<div class="card" style="padding:16px;margin-top:12px;border-left:3px solid #F20D16"><b>Verifica tu email</b><p style="color:#777;font-size:13px;margin:6px 0">Enviamos un código a tu correo para activar tu cuenta y las alertas.</p>'+inp('ve-code','Código (6 dígitos)')+btn('ve-go','Verificar')+' <a href="#" id="ve-resend" style="font-size:13px">Reenviar código</a></div>');
              if(!c.email_verified_at){document.getElementById('ve-go').onclick=async function(){var r=await api('/api/v1/public/account/verify-email',{method:'POST',body:JSON.stringify({email:c.email,code:document.getElementById('ve-code').value})});if(r.ok)location.reload();else alert(r.message||'Código inválido')};document.getElementById('ve-resend').onclick=async function(e){e.preventDefault();await api('/api/v1/public/account/verify-email/resend',{method:'POST'});alert('Código reenviado a tu correo.')}}}}
          async function $o(b){var r=await api('/api/v1/public/account/orders');var rows=r.data||[];b.innerHTML='<div class="card" style="padding:18px">'+(rows.length?'<table style="width:100%;font-size:13px;border-collapse:collapse"><tr style="text-align:left;color:#777"><th>Pedido</th><th>Fecha</th><th>Total</th><th>Estado</th><th></th></tr>'+rows.map(function(o){var act=(o.payment_status==='paid')?('<a href="#" onclick="skaReturn(\''+esc(o.uuid)+'\');return false">Devolver</a>'):'';return '<tr><td style="padding:6px 0">#'+esc(o.order_number||String(o.uuid||'').slice(0,8))+'</td><td>'+esc(o.created_at)+'</td><td>'+money((o.total_cents||0)/100)+'</td><td>'+esc((o.payment_status||'')+' / '+(o.fulfillment_status||''))+'</td><td>'+act+'</td></tr>'}).join('')+'</table>':'<div style="color:#777">Aún no tienes pedidos.</div>')+'</div>'}
          window.skaReturn=async function(uuid){var reason=prompt('Motivo de la devolución:');if(!reason)return;var r=await api('/api/v1/public/account/orders/'+encodeURIComponent(uuid)+'/return',{method:'POST',body:JSON.stringify({reason:reason})});alert((r&&(r.message||(r.data&&r.data.message)))||(r&&r.ok?'Solicitud enviada.':'Error'))};
          async function $f(b){var r=await api('/api/v1/public/account/favorites');var rows=r.data||[];b.innerHTML='<div class="card" style="padding:18px">'+(rows.length?rows.map(function(x){var ap=(x.alert_price==null||+x.alert_price)?"checked":"";var asf=(x.alert_stock==null||+x.alert_stock)?"checked":"";return '<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;padding:8px 0;border-bottom:1px solid #EEE"><div><a href="'+esc(x.url||'#')+'" style="font-weight:600">'+esc(x.title||x.product_ref)+'</a><div style="font-size:12px;color:#777;margin-top:4px"><label style="margin-right:12px"><input type="checkbox" '+ap+' onchange="skaFavAlert(\''+esc(x.product_ref)+'\',\'alert_price\',this.checked)"> 🔔 Baja de precio</label><label><input type="checkbox" '+asf+' onchange="skaFavAlert(\''+esc(x.product_ref)+'\',\'alert_stock\',this.checked)"> 📦 Vuelve a estar</label></div></div><span>'+money((x.price_cents||0)/100)+' <a href="#" onclick="skaFavDel(\''+esc(x.product_ref)+'\');return false" style="color:#DC2626">✕</a></span></div>'}).join(''):'<div style="color:#777">Sin favoritos.</div>')+'</div>'}
          window.skaFavDel=function(ref){api('/api/v1/public/account/favorites/'+encodeURIComponent(ref),{method:'DELETE'}).then(function(){$f(document.getElementById('ac-body'))})};
          window.skaFavAlert=function(ref,k,v){var o={};o[k]=v;api('/api/v1/public/account/favorites/'+encodeURIComponent(ref)+'/alert',{method:'POST',body:JSON.stringify(o)})};
          function $a(b,list){b.innerHTML='<div class="card" style="padding:18px"><h3>Direcciones</h3>'+(list.length?list.map(function(x){return '<div style="padding:8px 0;border-bottom:1px solid #EEE;font-size:13px"><b>'+esc(x.label||'')+'</b> · '+esc(x.line1||'')+' · '+esc(x.city||'')+'</div>'}).join(''):'<div style="color:#777">Sin direcciones.</div>')+'<h4 style="margin-top:14px">Añadir dirección</h4>'+inp('ad-label','Etiqueta (Casa)')+inp('ad-line1','Dirección')+inp('ad-city','Ciudad')+btn('ad-go','Guardar')+'</div>';document.getElementById('ad-go').onclick=async function(){var r=await api('/api/v1/public/account/addresses',{method:'POST',body:JSON.stringify({label:document.getElementById('ad-label').value,line1:document.getElementById('ad-line1').value,city:document.getElementById('ad-city').value,is_default:1})});if(r.ok)$f2()};
            function $f2(){api('/api/v1/public/account/me').then(function(r){account(r.data)})}}
          function $p(b,c){b.innerHTML='<div class="card" style="padding:18px"><h3>Perfil</h3>'+inp('pf-name','Nombre',null,c.name||'')+inp('pf-phone','Teléfono',null,c.phone||'')+inp('pf-pass','Nueva contraseña (opcional)','password')+btn('pf-go','Guardar')+'<div style="margin-top:16px;border-top:1px solid #EEE;padding-top:12px;font-size:13px"><a href="/api/v1/public/account/export" target="_blank">Descargar mis datos</a> &nbsp;·&nbsp; <a href="#" id="pf-del" style="color:#DC2626">Eliminar mi cuenta</a></div></div>';document.getElementById('pf-go').onclick=async function(){var body={name:document.getElementById('pf-name').value,phone:document.getElementById('pf-phone').value};var p=document.getElementById('pf-pass').value;if(p)body.password=p;var r=await api('/api/v1/public/account/profile',{method:'PUT',body:JSON.stringify(body)});if(r.ok)location.reload()};document.getElementById('pf-del').onclick=async function(e){e.preventDefault();if(!confirm('¿Eliminar tu cuenta y tus datos personales? Es irreversible.'))return;var r=await api('/api/v1/public/account',{method:'DELETE'});if(r.ok)location.search=''}}
          async function $rv(b){var r=await api('/api/v1/public/account/reviews');var rows=r.data||[];b.innerHTML='<div class="card" style="padding:18px"><h3>Mis reseñas</h3>'+(rows.length?rows.map(function(x){var st=x.rating||0;var stars='';for(var i=1;i<=5;i++)stars+='<span style="color:'+(i<=st?'#F5A623':'#DDD')+'">★</span>';var badge=x.status==='approved'?'<span style="color:#1E7A4B">publicada</span>':(x.status==='rejected'?'<span style="color:#DC2626">rechazada</span>':'<span style="color:#B45309">en revisión</span>');return '<div style="padding:8px 0;border-bottom:1px solid #EEE"><b>'+esc(x.title||x.product_ref)+'</b> '+stars+' <small>'+badge+'</small><div style="font-size:13px;color:#555;margin-top:2px">'+esc(x.body||'')+'</div><small style="color:#999">'+esc(String(x.created_at||'').slice(0,10))+'</small></div>'}).join(''):'<div style="color:#777">Aún no has escrito reseñas. Puedes hacerlo desde la página de cada producto.</div>')+'</div>'}
          async function $pts(b){var r=await api('/api/v1/public/account/loyalty');var d=r.data||{};if(!d.enabled){b.innerHTML='<div class="card" style="padding:18px;color:#777">El programa de puntos no está activo.</div>';return}var h=(d.history||[]);b.innerHTML='<div class="card" style="padding:18px"><h3>Mis puntos</h3><p style="font-size:30px;font-weight:700;margin:6px 0">'+(d.balance||0)+' <span style="font-size:14px;font-weight:400;color:#777">puntos</span></p>'+(h.length?'<table style="width:100%;font-size:13px;border-collapse:collapse"><tr style="text-align:left;color:#777"><th>Fecha</th><th>Puntos</th><th>Detalle</th></tr>'+h.map(function(x){return '<tr><td style="padding:5px 0">'+esc(String(x.created_at||'').slice(0,10))+'</td><td>'+(x.points>0?'+':'')+x.points+'</td><td>'+esc(x.note||x.reason)+'</td></tr>'}).join('')+'</table>':'<div style="color:#777">Sin movimientos todavía. Gana puntos con cada compra.</div>')+'</div>'}
          api('/api/v1/public/account/me').then(function(r){if(r&&r.data&&r.data.customer){account(r.data)}else if(qs().tab==='register'){reg()}else{login()}}).catch(function(){login()});
        })();
        </script>
        <?php
        sonika_site_footer();
        return true;
}

return false;
