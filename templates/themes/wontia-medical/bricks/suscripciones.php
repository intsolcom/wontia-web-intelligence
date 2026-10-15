<?php
$apiBase = medical_api_base();
?>
<section class="wm-section">
  <div class="wm-card">
    <span class="wm-chip">Suscripciones</span>
    <h2>Planes y beneficios</h2>
    <div id="wm-subs" data-api-base="<?= htmlspecialchars($apiBase) ?>"></div>
  </div>
</section>
<script>
(function () {
  var host = document.getElementById('wm-subs');
  if (!host || !window.WM) return;
  if (!WM.base()) { host.append(WM.el('p', { class: 'wm-note' }, 'Integracion pendiente: configura medical_api_url en el tema.')); return; }
  WM.ensureAuth(host, function () {
    var err = WM.el('p', { class: 'wm-toast' });
    var plans = WM.el('div', { class: 'wm-list' });
    var current = WM.el('div', { class: 'wm-list' });
    function loadCurrent() {
      WM.api('/api/v1/subscriptions/me').then(function (d) {
        current.replaceChildren();
        if (!d.subscription) { current.append(WM.el('p', { class: 'wm-muted' }, 'Sin suscripcion activa.')); return; }
        current.append(WM.el('div', { class: 'wm-item' }, [
          WM.el('span', {}, 'Plan actual'), WM.el('span', { class: 'wm-badge' }, d.subscription.status)
        ]));
      }).catch(function (e) { err.textContent = e.message; });
    }
    WM.api('/api/v1/plans').then(function (d) {
      plans.replaceChildren();
      (d.plans || []).forEach(function (p) {
        plans.append(WM.el('div', { class: 'wm-item' }, [
          WM.el('div', {}, [WM.el('div', {}, p.name), WM.el('span', { class: 'wm-muted' }, (p.price_cents / 100).toFixed(2) + ' ' + p.currency + ' / ' + p.interval)]),
          WM.el('button', { class: 'wm-btn', onClick: function () {
            WM.api('/api/v1/subscriptions', { method: 'POST', body: { planCode: p.code } })
              .then(loadCurrent)
              .catch(function (e) { err.textContent = e.message; });
          } }, 'Elegir')
        ]));
      });
    }).catch(function (e) { err.textContent = e.message; });
    host.replaceChildren(WM.el('div', {}, [WM.el('h3', {}, 'Mi suscripcion'), current, WM.el('h3', {}, 'Planes'), plans, err]));
    loadCurrent();
  });
})();
</script>
