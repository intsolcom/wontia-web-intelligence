<?php
$apiBase = medical_api_base();
?>
<section class="wm-section">
  <div class="wm-card">
    <span class="wm-chip">Teleconsulta</span>
    <h2>Sala virtual</h2>
    <p class="wm-muted">Requiere consentimiento informado. La grabacion esta desactivada.</p>
    <div id="wm-tele" data-api-base="<?= htmlspecialchars($apiBase) ?>"></div>
  </div>
</section>
<script>
(function () {
  var host = document.getElementById('wm-tele');
  if (!host || !window.WM) return;
  if (!WM.base()) { host.append(WM.el('p', { class: 'wm-note' }, 'Integracion pendiente: configura medical_api_url en el tema.')); return; }
  WM.ensureAuth(host, function () {
    var err = WM.el('p', { class: 'wm-toast' });
    var list = WM.el('div', { class: 'wm-list' });
    var panel = WM.el('div', {});
    function draw() {
      panel.replaceChildren(err);
    }
    function openSession(session) {
      var chat = WM.el('div', { class: 'wm-chat' });
      var input = WM.el('input', { class: 'wm-input', placeholder: 'Mensaje a la consulta...' });
      var send = WM.el('button', { class: 'wm-btn wm-btn-tia' }, 'Enviar');
      function loadMessages() {
        WM.api('/api/v1/tele/sessions/' + session.id + '/messages').then(function (d) {
          chat.replaceChildren();
          (d.messages || []).forEach(function (m) { chat.append(WM.el('div', { class: 'wm-msg ' + (m.user_id ? 'user' : 'tia') }, m.content)); });
          if (!d.messages || !d.messages.length) chat.append(WM.el('div', { class: 'wm-muted' }, 'La consulta aun no tiene mensajes.'));
          chat.scrollTop = chat.scrollHeight;
        }).catch(function (e) { err.textContent = e.message; });
      }
      function sendMsg() {
        var text = input.value.trim(); if (!text) return; input.value = '';
        WM.api('/api/v1/tele/sessions/' + session.id + '/messages', { method: 'POST', body: { content: text } })
          .then(loadMessages).catch(function (e) { err.textContent = e.message; });
      }
      send.addEventListener('click', sendMsg);
      input.addEventListener('keydown', function (e) { if (e.key === 'Enter') sendMsg(); });
      panel.replaceChildren(WM.el('div', { class: 'wm-card' }, [
        WM.el('h3', {}, 'Consulta activa'),
        WM.el('p', { class: 'wm-muted' }, 'Sala ' + session.room_id + ' · Proveedor ' + session.provider),
        WM.el('button', { class: 'wm-btn', onClick: function () {
          WM.api('/api/v1/tele/sessions/' + session.id + '/consent', { method: 'POST', body: {} })
            .then(function () { return WM.api('/api/v1/tele/sessions/' + session.id + '/join', { method: 'POST' }); })
            .then(function () { loadMessages(); })
            .catch(function (e) { err.textContent = e.message; });
        } }, 'Aceptar y entrar'),
        chat,
        WM.el('div', { class: 'wm-row' }, [input, send]),
        WM.el('div', { class: 'wm-row' }, [
          WM.el('button', { class: 'wm-btn wm-btn-ghost', onClick: function () { WM.api('/api/v1/tele/sessions/' + session.id + '/leave', { method: 'POST' }).catch(function () {}); } }, 'Salir'),
          WM.el('button', { class: 'wm-btn wm-btn-ghost', onClick: function () {
            WM.api('/api/v1/tele/sessions/' + session.id + '/end', { method: 'POST' }).then(draw).catch(function (e) { err.textContent = e.message; });
          } }, 'Finalizar')
        ]),
        err
      ]));
    }
    WM.api('/api/v1/appointments').then(function (d) {
      var confirmed = (d.appointments || []).filter(function (a) { return a.status === 'confirmed'; });
      list.replaceChildren();
      if (!confirmed.length) { list.append(WM.el('p', { class: 'wm-muted' }, 'No tienes citas confirmadas para iniciar una consulta.')); return; }
      confirmed.forEach(function (a) {
        list.append(WM.el('div', { class: 'wm-item' }, [
          WM.el('div', {}, [WM.el('div', {}, new Date(a.starts_at).toLocaleString()), WM.el('span', { class: 'wm-badge' }, a.status)]),
          WM.el('button', { class: 'wm-btn', onClick: function () {
            WM.api('/api/v1/tele/sessions', { method: 'POST', body: { appointmentId: a.id } })
              .then(function (r) { openSession(r.session); })
              .catch(function (e) { err.textContent = e.message; });
          } }, 'Abrir consulta')
        ]));
      });
    }).catch(function (e) { err.textContent = e.message; });
    host.replaceChildren(WM.el('div', {}, [WM.el('h3', {}, 'Citas confirmadas'), list, panel]));
  });
})();
</script>
