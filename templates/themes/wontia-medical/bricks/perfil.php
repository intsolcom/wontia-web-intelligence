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
              var row = [WM.el('span', {}, c.title), WM.el('span', { class: 'wm-badge' }, c.status)];
              if (c.file_name) {
                row.push(WM.el('a', { class: 'wm-btn wm-btn-ghost', href: WM.base() + '/api/v1/credentials/' + c.id + '/file', target: '_blank' }, 'Ver archivo'));
              }
              credList.append(WM.el('div', { class: 'wm-item' }, row));
            });
          }).catch(function (e) { err.textContent = e.message; });
        };
        var cBtn = WM.el('button', { class: 'wm-btn', onClick: function () {
          if (!cTitle.value.trim() || !cContent.value.trim()) return;
          WM.api('/api/v1/professionals/me/credentials', { method: 'POST', body: { title: cTitle.value, content: cContent.value } })
            .then(function () { cTitle.value = ''; cContent.value = ''; loadCreds(); })
            .catch(function (e) { err.textContent = e.message; });
        } }, 'Subir texto');
        var cFileTitle = WM.el('input', { class: 'wm-input', placeholder: 'Titulo del archivo (PDF/PNG)' });
        var cFile = WM.el('input', { class: 'wm-input', type: 'file', accept: 'application/pdf,image/png,image/jpeg,image/webp' });
        var cFileBtn = WM.el('button', { class: 'wm-btn', onClick: function () {
          var f = cFile.files && cFile.files[0];
          if (!f) { err.textContent = 'Selecciona un archivo.'; return; }
          if (!cFileTitle.value.trim()) { err.textContent = 'Pon un titulo al archivo.'; return; }
          var reader = new FileReader();
          reader.onload = function () {
            var b64 = String(reader.result).split(',')[1] || '';
            WM.api('/api/v1/professionals/me/credentials/upload', { method: 'POST', body: { title: cFileTitle.value, fileName: f.name, mime: f.type || 'application/octet-stream', data: b64 } })
              .then(function () { cFile.value = ''; cFileTitle.value = ''; loadCreds(); })
              .catch(function (e) { err.textContent = e.message; });
          };
          reader.readAsDataURL(f);
        } }, 'Subir archivo');
        creds = WM.el('div', {}, [WM.el('h3', {}, 'Credenciales profesionales'), cTitle, cContent, cBtn, cFileTitle, cFile, cFileBtn, credList]);
        loadCreds();
      }
      var logout = WM.el('button', { class: 'wm-btn wm-btn-ghost', onClick: function () {
        WM.api('/api/v1/auth/logout', { method: 'POST' }).catch(function () {}).finally(function () {
          WM.setToken(null);
          host.replaceChildren();
          WM.loginForm(host, function () { window.location.reload(); });
        });
      } }, 'Cerrar sesion');
      var adminLink = (d.user.roles || []).indexOf('admin') !== -1
        ? WM.el('a', { class: 'wm-btn wm-btn-ghost', href: '/gestion' }, 'Panel de verificacion')
        : null;
      out.replaceChildren(WM.el('div', { class: 'wm-list' }, rows), creds, WM.el('div', { class: 'wm-row' }, [logout, adminLink]), err);
    }).catch(function (e) {
      err.textContent = e.message;
      host.replaceChildren(err);
      WM.loginForm(host, function () { window.location.reload(); });
    });
    host.replaceChildren(out);
  });
})();
</script>
