<?php
$apiBase = medical_api_base();
?>
<section class="wm-section">
  <div class="wm-card">
    <span class="wm-chip">Agenda</span>
    <h2>Reserva tu cita</h2>
    <div id="wm-agenda" data-api-base="<?= htmlspecialchars($apiBase) ?>"></div>
  </div>
</section>
<script>
(function () {
  var host = document.getElementById('wm-agenda');
  if (!host || !window.WM) return;
  if (!WM.base()) { host.append(WM.el('p', { class: 'wm-note' }, 'Integracion pendiente: configura medical_api_url en el tema.')); return; }
  WM.ensureAuth(host, function () {
    var err = WM.el('p', { class: 'wm-toast' });
    var specBox = WM.el('div', { class: 'wm-row' });
    var profSel = WM.el('select', { class: 'wm-input' });
    var date = WM.el('input', { class: 'wm-input', type: 'date' });
    var slots = WM.el('div', { class: 'wm-list' });
    var appts = WM.el('div', { class: 'wm-list' });
    var tz = 'UTC';
    try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC'; } catch (e) {}
    date.value = new Date(Date.now() + 86400000).toISOString().slice(0, 10);

    function loadAppts() {
      WM.api('/api/v1/appointments').then(function (d) {
        appts.replaceChildren();
        if (!d.appointments.length) { appts.append(WM.el('p', { class: 'wm-muted' }, 'Aun no tienes citas.')); return; }
        d.appointments.forEach(function (a) {
          appts.append(WM.el('div', { class: 'wm-item' }, [
            WM.el('div', {}, [WM.el('div', {}, new Date(a.starts_at).toLocaleString()), WM.el('span', { class: 'wm-badge' }, a.status)]),
            WM.el('button', { class: 'wm-btn wm-btn-ghost', onClick: function () {
              WM.api('/api/v1/appointments/' + a.id + '/cancel', { method: 'POST', body: {} }).then(loadAppts).catch(function (e) { err.textContent = e.message; });
            } }, 'Cancelar')
          ]));
        });
      }).catch(function (e) { err.textContent = e.message; });
    }
    function loadSlots() {
      slots.replaceChildren(WM.el('p', { class: 'wm-muted' }, 'Cargando horarios...'));
      if (!profSel.value) { slots.replaceChildren(WM.el('p', { class: 'wm-muted' }, 'Sin profesionales.')); return; }
      WM.api('/api/v1/professionals/' + profSel.value + '/slots?date=' + date.value + '&tz=' + encodeURIComponent(tz)).then(function (d) {
        slots.replaceChildren();
        if (!d.slots.length) { slots.append(WM.el('p', { class: 'wm-muted' }, 'Sin horarios ese dia.')); return; }
        d.slots.forEach(function (s) {
          slots.append(WM.el('button', { class: 'wm-btn wm-btn-ghost', onClick: function () {
            WM.api('/api/v1/appointments', { method: 'POST', body: { professionalId: profSel.value, startsAt: s.start, timezone: tz } })
              .then(function () { loadSlots(); loadAppts(); })
              .catch(function (e) { err.textContent = e.message; });
          } }, new Date(s.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })));
        });
      }).catch(function (e) { err.textContent = e.message; });
    }
    WM.api('/api/v1/specialties').then(function (d) {
      specBox.replaceChildren.apply(specBox, d.specialties.map(function (s) { return WM.el('span', { class: 'wm-badge' }, s.name); }));
    }).catch(function () {});
    WM.api('/api/v1/professionals').then(function (d) {
      profSel.replaceChildren();
      d.professionals.forEach(function (p) { profSel.append(WM.el('option', { value: p.id }, p.display_name || p.id)); });
      if (d.professionals.length) loadSlots();
      else slots.replaceChildren(WM.el('p', { class: 'wm-muted' }, 'Todavia no hay profesionales registrados.'));
    }).catch(function (e) { err.textContent = e.message; });
    profSel.addEventListener('change', loadSlots);
    date.addEventListener('change', loadSlots);
    host.replaceChildren(WM.el('div', {}, [
      WM.el('p', { class: 'wm-muted' }, 'Especialidades disponibles:'), specBox,
      WM.el('div', { class: 'wm-row' }, [profSel, date]), slots,
      WM.el('h3', {}, 'Mis citas'), appts, err
    ]));
    loadAppts();
  });
})();
</script>
