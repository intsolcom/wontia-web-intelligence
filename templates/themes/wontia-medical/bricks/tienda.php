<?php
$apiBase = medical_api_base();
?>
<section class="wm-section">
  <div class="wm-card">
    <span class="wm-chip">Tienda</span>
    <h2>Productos de bienestar</h2>
    <div id="wm-tienda" data-api-base="<?= htmlspecialchars($apiBase) ?>"></div>
  </div>
</section>
<script>
(function () {
  var host = document.getElementById('wm-tienda');
  if (!host || !window.WM) return;
  if (!WM.base()) { host.append(WM.el('p', { class: 'wm-note' }, 'Integracion pendiente: configura medical_api_url en el tema.')); return; }
  WM.ensureAuth(host, function () {
    var err = WM.el('p', { class: 'wm-toast' });
    var products = WM.el('div', { class: 'wm-list' });
    var orders = WM.el('div', { class: 'wm-list' });
    function loadOrders() {
      WM.api('/api/v1/orders').then(function (d) {
        orders.replaceChildren();
        if (!d.orders.length) { orders.append(WM.el('p', { class: 'wm-muted' }, 'Sin pedidos.')); return; }
        d.orders.forEach(function (o) {
          orders.append(WM.el('div', { class: 'wm-item' }, [
            WM.el('div', {}, [WM.el('div', {}, '#' + String(o.id).slice(0, 8)), WM.el('span', { class: 'wm-badge' }, o.status)]),
            WM.el('span', { class: 'wm-muted' }, '$ ' + (o.total_cents / 100).toFixed(2))
          ]));
        });
      }).catch(function (e) { err.textContent = e.message; });
    }
    WM.api('/api/v1/products').then(function (d) {
      products.replaceChildren();
      if (!d.products.length) { products.append(WM.el('p', { class: 'wm-muted' }, 'Catalogo vacio.')); return; }
      d.products.forEach(function (p) {
        products.append(WM.el('div', { class: 'wm-item' }, [
          WM.el('div', {}, [WM.el('div', {}, p.name), WM.el('span', { class: 'wm-muted' }, '$ ' + (p.price_cents / 100).toFixed(2))]),
          WM.el('button', { class: 'wm-btn', onClick: function () {
            WM.api('/api/v1/orders', { method: 'POST', body: { items: [{ productId: p.id, quantity: 1 }] } })
              .then(loadOrders)
              .catch(function (e) { err.textContent = e.message; });
          } }, 'Comprar')
        ]));
      });
    }).catch(function (e) { err.textContent = e.message; });
    host.replaceChildren(WM.el('div', {}, [products, WM.el('h3', {}, 'Mis pedidos'), orders, err]));
    loadOrders();
  });
})();
</script>
