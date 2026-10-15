<?php
$apiBase = medical_api_base();
?>
<section class="wm-section">
  <div class="wm-card">
    <span class="wm-chip">Expediente</span>
    <h2>Mi historial</h2>
    <p class="wm-muted">Datos declarados por ti, documentos y registro de accesos. Solo un profesional autorizado accede al resto.</p>
    <div id="wm-exp" data-api-base="<?= htmlspecialchars($apiBase) ?>"></div>
  </div>
</section>
<script>
(function () {
  var host = document.getElementById('wm-exp');
  if (!host || !window.WM) return;
  if (!WM.base()) { host.append(WM.el('p', { class: 'wm-note' }, 'Integracion pendiente: configura medical_api_url en el tema.')); return; }
  WM.ensureAuth(host, function () {
    var uid = '';
    var err = WM.el('p', { class: 'wm-toast' });
    var entries = WM.el('div', { class: 'wm-list' });
    var docs = WM.el('div', { class: 'wm-list' });
    var kind = WM.el('select', { class: 'wm-input' });
    ['antecedente', 'alergia', 'medicamento', 'nota'].forEach(function (k) { kind.append(WM.el('option', { value: k }, k)); });
    var content = WM.el('textarea', { class: 'wm-input', placeholder: 'Describe la informacion...' });
    var docTitle = WM.el('input', { class: 'wm-input', placeholder: 'Titulo del documento' });
    var docContent = WM.el('textarea', { class: 'wm-input', placeholder: 'Contenido' });

    var addBtn = WM.el('button', { class: 'wm-btn', onClick: function () {
      if (!content.value.trim()) return;
      WM.api('/api/v1/health/me/entries', { method: 'POST', body: { kind: kind.value, content: content.value } })
        .then(function () { content.value = ''; load(); })
        .catch(function (e) { err.textContent = e.message; });
    } }, 'Agregar');

    var docBtn = WM.el('button', { class: 'wm-btn', onClick: function () {
      if (!docTitle.value.trim() || !docContent.value.trim()) return;
      if (!uid) { err.textContent = 'No se pudo identificar al usuario.'; return; }
      WM.api('/api/v1/health/patients/' + uid + '/documents', { method: 'POST', body: { title: docTitle.value, content: docContent.value } })
        .then(function () { docTitle.value = ''; docContent.value = ''; load(); })
        .catch(function (e) { err.textContent = e.message; });
    } }, 'Subir documento');

    function load() {
      WM.api('/api/v1/me').then(function (d) { uid = d.user.id; }).catch(function () {});
      WM.api('/api/v1/health/me').then(function (d) {
        entries.replaceChildren();
        if (!d.entries || !d.entries.length) entries.append(WM.el('p', { class: 'wm-muted' }, 'Sin entradas.'));
        (d.entries || []).forEach(function (en) {
          var actions = [];
          if (en.source === 'declared' && en.status === 'draft') {
            actions.push(WM.el('button', { class: 'wm-btn wm-btn-ghost', onClick: function () {
              WM.api('/api/v1/health/entries/' + en.id, { method: 'DELETE' }).then(load).catch(function (e) { err.textContent = e.message; });
            } }, 'Eliminar'));
          }
          entries.append(WM.el('div', { class: 'wm-item' }, [
            WM.el('div', {}, [WM.el('div', {}, en.content), WM.el('span', { class: 'wm-badge' }, en.kind + ' · ' + en.source + ' · ' + en.status)]),
            WM.el('div', {}, actions)
          ]));
        });
        docs.replaceChildren();
        if (!d.documents || !d.documents.length) docs.append(WM.el('p', { class: 'wm-muted' }, 'Sin documentos.'));
        (d.documents || []).forEach(function (dc) {
          docs.append(WM.el('div', { class: 'wm-item' }, [WM.el('span', {}, dc.title), WM.el('span', { class: 'wm-badge' }, String(dc.sha256).slice(0, 8))]));
        });
      }).catch(function (e) { err.textContent = e.message; });
    }

    host.replaceChildren(WM.el('div', {}, [
      WM.el('h3', {}, 'Datos declarados'),
      WM.el('div', { class: 'wm-row' }, [kind]), content, addBtn, entries,
      WM.el('h3', {}, 'Documentos'), docTitle, docContent, docBtn, docs,
      err
    ]));
    load();
  });
})();
</script>
