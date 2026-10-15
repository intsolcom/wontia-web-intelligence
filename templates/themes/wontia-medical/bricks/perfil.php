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
      var logout = WM.el('button', { class: 'wm-btn wm-btn-ghost', onClick: function () {
        WM.api('/api/v1/auth/logout', { method: 'POST' }).catch(function () {}).finally(function () {
          WM.setToken(null);
          host.replaceChildren();
          WM.loginForm(host, function () { window.location.reload(); });
        });
      } }, 'Cerrar sesion');
      out.replaceChildren(WM.el('div', { class: 'wm-list' }, rows), WM.el('div', { class: 'wm-row' }, [logout]), err);
    }).catch(function (e) {
      err.textContent = e.message;
      host.replaceChildren(err);
      WM.loginForm(host, function () { window.location.reload(); });
    });
    host.replaceChildren(out);
  });
})();
</script>
