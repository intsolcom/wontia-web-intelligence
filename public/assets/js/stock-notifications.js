(function () {
  'use strict';
  var boxes = document.querySelectorAll('[data-stock-notify]');
  if (!boxes.length) return;
  var endpoint = '/api/v1/public/store/stock-notifications';

  function fieldError(input, message) {
    if (input) input.classList.toggle('is-invalid', !!message);
    return message;
  }

  boxes.forEach(function (box) {
    var toggle = box.querySelector('[data-stock-toggle]');
    var form = box.querySelector('[data-stock-form]');
    var consent = box.querySelector('[data-stock-consent]');
    var name = box.querySelector('[data-stock-name]');
    var email = box.querySelector('[data-stock-email]');
    var submit = box.querySelector('.stock-alert__submit');
    var message = box.querySelector('[data-stock-msg]');
    var step = box.querySelector('.stock-alert__step span');
    if (!toggle || !form || !submit) return;

    toggle.addEventListener('change', function () {
      form.hidden = !toggle.checked;
      if (toggle.checked) {
        setTimeout(function () { if (consent) consent.focus(); }, 80);
      }
    });

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      if (submit.disabled) return;
      var missing = '';
      if (!consent || !consent.checked) missing = 'Debes aceptar la política de tratamiento de datos.';
      else if (!name || name.value.trim().length < 2) missing = fieldError(name, 'Escribe tu nombre completo.');
      else if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) missing = fieldError(email, 'Escribe un correo electrónico válido.');
      if (missing) {
        if (message) message.textContent = missing;
        return;
      }
      fieldError(name, '');
      fieldError(email, '');
      submit.disabled = true;
      if (message) { message.textContent = 'Guardando tu solicitud...'; message.style.color = '#6b7280'; }

      fetch(endpoint, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          product_ref: box.getAttribute('data-ref') || '',
          name: name.value.trim(),
          email: email.value.trim(),
          consent: true
        })
      }).then(function (response) {
        return response.json().then(function (data) { return { status: response.status, data: data }; });
      }).then(function (result) {
        var data = result.data || {};
        if (result.status < 200 || result.status >= 300 || !data.ok) throw new Error(data.message || 'No se pudo registrar la solicitud.');
        box.classList.add('is-success');
        if (step) step.textContent = 'Solicitud enviada';
        if (message) { message.textContent = data.message || 'Listo. Te avisaremos cuando esté disponible.'; message.style.color = '#047857'; }
        submit.textContent = 'Solicitud registrada';
        submit.disabled = true;
        if (name) name.disabled = true;
        if (email) email.disabled = true;
        if (consent) consent.disabled = true;
      }).catch(function (error) {
        submit.disabled = false;
        if (message) { message.textContent = error.message || 'No se pudo registrar la solicitud.'; message.style.color = '#b42318'; }
      });
    });
  });
})();
