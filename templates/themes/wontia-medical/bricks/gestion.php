<?php
$apiBase = medical_api_base();
?>
<section class="wm-section">
  <div class="wm-card">
    <span class="wm-chip">Administración</span>
    <h2>Verificación de profesionales</h2>
    <p class="wm-muted">Revisa credenciales y aprueba o rechaza cuentas profesionales.</p>
    <div id="wm-gestion" data-api-base="<?= htmlspecialchars($apiBase) ?>"></div>
  </div>
</section>
<script>
(function () {
  var host = document.getElementById('wm-gestion');
  if (!host || !window.WM) return;
  if (!WM.base()) { host.append(WM.el('p', { class: 'wm-note' }, 'Integracion pendiente: configura medical_api_url en el tema.')); return; }
  WM.ensureAuth(host, function () {
    var err = WM.el('p', { class: 'wm-toast' });
    var list = WM.el('div', { class: 'wm-list' });
    function load() {
      WM.api('/api/v1/admin/professionals/pending').then(function (d) {
        list.replaceChildren();
        if (!d.professionals.length) { list.append(WM.el('p', { class: 'wm-muted' }, 'Sin profesionales pendientes.')); return; }
        d.professionals.forEach(function (p) {
          var creds = WM.el('div', { class: 'wm-list' });
          var act = WM.el('div', { class: 'wm-row' }, [
            WM.el('button', { class: 'wm-btn wm-btn-ghost', onClick: function () {
              WM.api('/api/v1/admin/professionals/' + p.id + '/credentials').then(function (r) {
                creds.replaceChildren();
                if (!r.credentials.length) { creds.append(WM.el('p', { class: 'wm-muted' }, 'Sin credenciales.')); return; }
                r.credentials.forEach(function (c) {
                  var row = [WM.el('span', {}, c.title), WM.el('span', { class: 'wm-badge' }, c.status + ' · ' + String(c.sha256).slice(0, 8))];
                  if (c.file_name) {
                    row.push(WM.el('a', { class: 'wm-btn wm-btn-ghost', href: WM.base() + '/api/v1/credentials/' + c.id + '/file', target: '_blank' }, 'Ver archivo'));
                  }
                  creds.append(WM.el('div', { class: 'wm-item' }, row));
                });
              }).catch(function (e) { err.textContent = e.message; });
            } }, 'Ver credenciales'),
            WM.el('button', { class: 'wm-btn', onClick: function () {
              WM.api('/api/v1/admin/professionals/' + p.id + '/verify', { method: 'POST' }).then(load).catch(function (e) { err.textContent = e.message; });
            } }, 'Verificar'),
            WM.el('button', { class: 'wm-btn wm-btn-ghost', onClick: function () {
              WM.api('/api/v1/admin/professionals/' + p.id + '/reject', { method: 'POST' }).then(load).catch(function (e) { err.textContent = e.message; });
            } }, 'Rechazar')
          ]);
          list.append(WM.el('div', { class: 'wm-card' }, [
            WM.el('div', { class: 'wm-row between' }, [WM.el('strong', {}, p.display_name), WM.el('span', { class: 'wm-badge' }, p.status)]),
            WM.el('div', { class: 'wm-row' }, [WM.el('span', { class: 'wm-muted' }, 'Profesional ID: ' + String(p.id).slice(0, 8))]),
            act, creds
          ]));
        });
      }).catch(function (e) {
        list.replaceChildren(WM.el('p', { class: 'wm-note' }, 'Solo administradores pueden ver esta seccion.'));
      });
    }
    WM.api('/api/v1/me').then(function (me) {
      var roles = me.user.roles || [];
      if (roles.indexOf('admin') === -1) { host.replaceChildren(WM.el('p', { class: 'wm-note' }, 'Esta seccion es solo para administradores.')); return; }
      host.replaceChildren(WM.el('div', {}, [list, err]));
      load();
    }).catch(function (e) { err.textContent = e.message; host.replaceChildren(err); });
  });
})();
</script>
