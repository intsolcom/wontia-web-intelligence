<?php
$apiBase = medical_api_base();
?>
<section class="wm-section">
  <div class="wm-card">
    <span class="wm-chip">Perfil</span>
    <h2>Mi cuenta</h2>
    <div id="wm-perfil" data-api-base="<?= htmlspecialchars($apiBase) ?>"></div>
  </div>
</section>
<script>
(function () {
  var host = document.getElementById('wm-perfil');
  if (!host || !window.WM) return;
  if (!WM.base()) { host.append(WM.el('p', { class: 'wm-note' }, 'Integracion pendiente: configura medical_api_url en el tema.')); return; }
  WM.ensureAuth(host, function () {
    var err = WM.el('p', { class: 'wm-toast' });
    var out = WM.el('div', {});
    WM.api('/api/v1/me').then(function (d) {
      var rows = [
        WM.el('div', { class: 'wm-item' }, ['Correo', d.user.email]),
        WM.el('div', { class: 'wm-item' }, ['Idioma', d.user.locale]),
        WM.el('div', { class: 'wm-item' }, ['Roles', (d.user.roles || []).join(', ')])
      ];
      if (d.professional) {
        var estado = d.professional.verified
          ? 'Verificado'
          : (d.professional.status === 'rejected' ? 'Rechazado' : 'Pendiente de verificacion');
        rows.push(WM.el('div', { class: 'wm-item' }, ['Estado profesional', estado]));
        if (!d.professional.verified && d.professional.status !== 'rejected') {
          rows.push(WM.el('p', { class: 'wm-note' }, 'Tu cuenta profesional esta en revision. Un administrador validara tus credenciales.'));
        }
      }
      var creds = null;
      if (d.professional) {
        var credList = WM.el('div', { class: 'wm-list' });
        var cTitle = WM.el('input', { class: 'wm-input', placeholder: 'Titulo (ej. Tarjeta profesional)' });
        var cContent = WM.el('textarea', { class: 'wm-input', placeholder: 'Detalle o numero de registro' });
        var loadCreds = function () {
          WM.api('/api/v1/professionals/me/credentials').then(function (r) {
            credList.replaceChildren();
            if (!r.credentials.length) { credList.append(WM.el('p', { class: 'wm-muted' }, 'Sin credenciales cargadas.')); return; }
            r.credentials.forEach(function (c) {
              credList.append(WM.el('div', { class: 'wm-item' }, [WM.el('span', {}, c.title), WM.el('span', { class: 'wm-badge' }, c.status)]));
            });
          }).catch(function (e) { err.textContent = e.message; });
        };
        var cBtn = WM.el('button', { class: 'wm-btn', onClick: function () {
          if (!cTitle.value.trim() || !cContent.value.trim()) return;
          WM.api('/api/v1/professionals/me/credentials', { method: 'POST', body: { title: cTitle.value, content: cContent.value } })
            .then(function () { cTitle.value = ''; cContent.value = ''; loadCreds(); })
            .catch(function (e) { err.textContent = e.message; });
        } }, 'Subir credencial');
        creds = WM.el('div', {}, [WM.el('h3', {}, 'Credenciales profesionales'), cTitle, cContent, cBtn, credList]);
        loadCreds();
      }
      var logout = WM.el('button', { class: 'wm-btn wm-btn-ghost', onClick: function () {
        WM.api('/api/v1/auth/logout', { method: 'POST' }).catch(function () {}).finally(function () {
          WM.setToken(null);
          host.replaceChildren();
          WM.loginForm(host, function () { window.location.reload(); });
        });
      } }, 'Cerrar sesion');
      out.replaceChildren(WM.el('div', { class: 'wm-list' }, rows), creds, WM.el('div', { class: 'wm-row' }, [logout]), err);
    }).catch(function (e) {
      err.textContent = e.message;
      host.replaceChildren(err);
      WM.loginForm(host, function () { window.location.reload(); });
    });
    host.replaceChildren(out);
  });
})();
</script>
