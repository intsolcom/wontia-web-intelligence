<?php
$apiBase = medical_api_base();
?>
<section class="wm-section wm-tia">
  <div class="wm-card wm-card-tia">
    <span class="wm-chip wm-chip-tia">TIA</span>
    <h2>Tecnologia de Inteligencia Aplicada</h2>
    <p class="wm-muted">
      TIA te acompana con objetivos de bienestar, habitos y orientacion general.
      No emite diagnosticos, no prescribe ni modifica tratamientos.
    </p>
    <div id="wm-tia" data-api-base="<?= htmlspecialchars($apiBase) ?>"></div>
    <p class="wm-hint">Ante sintomas de alarma, busca atencion urgente.</p>
  </div>
</section>
<script>
(function () {
  var host = document.getElementById('wm-tia');
  if (!host || !window.WM) return;
  if (!WM.base()) { host.append(WM.el('p', { class: 'wm-note' }, 'Integracion pendiente: configura medical_api_url en el tema.')); return; }
  WM.ensureAuth(host, function () {
    var convId = null, messages = [];
    var chat = WM.el('div', { class: 'wm-chat' });
    var input = WM.el('input', { class: 'wm-input', placeholder: 'Escribe tu mensaje...' });
    var send = WM.el('button', { class: 'wm-btn wm-btn-tia' }, 'Enviar');
    var err = WM.el('p', { class: 'wm-toast' });
    function draw() {
      chat.replaceChildren();
      if (!messages.length) chat.append(WM.el('div', { class: 'wm-muted' }, 'Cuentame que te gustaria mejorar.'));
      messages.forEach(function (m) { chat.append(WM.el('div', { class: 'wm-msg ' + (m.role === 'user' ? 'user' : 'tia') }, m.content)); });
      chat.scrollTop = chat.scrollHeight;
    }
    function submit() {
      var text = input.value.trim();
      if (!text) return;
      input.value = '';
      messages.push({ role: 'user', content: text });
      draw();
      send.disabled = true;
      err.textContent = '';
      var ensure = convId ? Promise.resolve() : WM.api('/api/v1/tia/conversations', { method: 'POST', body: {} }).then(function (r) { convId = r.conversation.id; });
      ensure
        .then(function () { return WM.api('/api/v1/tia/conversations/' + convId + '/messages', { method: 'POST', body: { text: text } }); })
        .then(function (r) {
          messages.push({ role: 'tia', content: r.reply.content });
          if (r.guardrail && r.guardrail.action === 'urgent') chat.append(WM.el('span', { class: 'wm-badge urgent' }, 'Señal de alarma'));
          draw();
        })
        .catch(function (e) { err.textContent = e.message; messages.pop(); draw(); })
        .finally(function () { send.disabled = false; input.focus(); });
    }
    send.addEventListener('click', submit);
    input.addEventListener('keydown', function (e) { if (e.key === 'Enter') submit(); });
    host.replaceChildren(WM.el('div', {}, [chat, WM.el('div', { class: 'wm-row' }, [input, send]), err]));
    draw();
  });
})();
</script>
