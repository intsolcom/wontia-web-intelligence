(function () {
  'use strict';
  var CFG = window.SONIKA_GIFTCARD_CONFIG || {};
  var form = document.getElementById('gcForm');
  var balanceForm = document.getElementById('gcBalanceForm');
  if (!form && !balanceForm) return;

  var occasionButtons = Array.prototype.slice.call(document.querySelectorAll('[data-gc-occasion]'));
  var ticket = document.getElementById('gcTicket');
  var ticketIcon = document.getElementById('gcTicketIcon');
  var ticketOcc = document.getElementById('gcTicketOcc');
  var ticketAmount = document.getElementById('gcTicketAmount');
  var ticketTo = document.getElementById('gcTicketTo');
  var ticketMsg = document.getElementById('gcTicketMsg');
  var hint = document.getElementById('gcOccasionHint');
  var hiddenOccasion = document.getElementById('gcOccasion');
  var amountInput = document.getElementById('gcAmount');
  var presetsBox = document.getElementById('gcAmountPresets');
  var messageInput = document.getElementById('gcMessage');
  var recipientInput = document.getElementById('gcRecipientName');
  var errorBox = document.getElementById('gcError');
  var submit = document.getElementById('gcSubmit');
  var currentDefaultMessage = messageInput ? String(messageInput.value || '') : '';
  var selectedAmountCents = 0;

  function esc(v) {
    var d = document.createElement('div');
    d.textContent = v == null ? '' : String(v);
    return d.innerHTML;
  }
  function money(cents) {
    var n = (Number(cents) || 0) / 100;
    var cur = String(CFG.currency || 'COP');
    try {
      return new Intl.NumberFormat('es-CO', {
        style: 'currency', currency: cur,
        minimumFractionDigits: cur === 'COP' ? 0 : 2,
        maximumFractionDigits: cur === 'COP' ? 0 : 2
      }).format(n);
    } catch (e) {
      return '$' + Math.round(n).toLocaleString('es-CO');
    }
  }
  function showError(msg) {
    if (!errorBox) return;
    errorBox.textContent = String(msg || '');
    errorBox.classList.toggle('is-on', !!msg);
  }
  function setBusy(b) {
    if (!submit) return;
    submit.disabled = !!b;
    submit.textContent = b ? 'Creando pedido…' : 'Comprar tarjeta de regalo';
  }
  function validEmail(v) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v || '').trim());
  }

  function paintAmount() {
    var cents = selectedAmountCents;
    if (!cents && amountInput) {
      var units = parseInt(String(amountInput.value || '').replace(/[^\d]/g, ''), 10) || 0;
      cents = units * 100;
    }
    if (ticketAmount) ticketAmount.textContent = money(cents || 10000000);
    Array.prototype.forEach.call(document.querySelectorAll('[data-gc-amount]'), function (b) {
      b.classList.toggle('is-active', Number(b.getAttribute('data-gc-amount')) === cents);
    });
  }

  function paintMessage() {
    if (ticketMsg && messageInput) ticketMsg.textContent = String(messageInput.value || '').trim() || currentDefaultMessage;
  }

  function paintRecipient() {
    if (!ticketTo) return;
    var n = recipientInput ? String(recipientInput.value || '').trim() : '';
    ticketTo.textContent = n !== '' ? 'Para ' + n : 'Para quien hace música con el corazón';
  }

  function setDefaultMessage(msg) {
    if (!messageInput) return;
    var current = String(messageInput.value || '').trim();
    if (current === '' || current === String(currentDefaultMessage || '').trim()) {
      messageInput.value = msg || '';
    }
    currentDefaultMessage = String(msg || '');
    paintMessage();
  }

  function renderPresets(amounts) {
    if (!presetsBox) return;
    var list = Array.isArray(amounts) ? amounts : [];
    if (!list.length) {
      presetsBox.innerHTML = '<button type="button" class="gc-amount" data-gc-amount="5000000">$50.000</button>'
        + '<button type="button" class="gc-amount" data-gc-amount="10000000">$100.000</button>'
        + '<button type="button" class="gc-amount" data-gc-amount="20000000">$200.000</button>';
    } else {
      presetsBox.innerHTML = list.map(function (cents) {
        return '<button type="button" class="gc-amount" data-gc-amount="' + Number(cents) + '">' + money(cents) + '</button>';
      }).join('');
    }
    bindAmountButtons();
  }

  function bindAmountButtons() {
    Array.prototype.forEach.call(presetsBox ? presetsBox.querySelectorAll('[data-gc-amount]') : [], function (b) {
      b.addEventListener('click', function () {
        selectedAmountCents = Number(b.getAttribute('data-gc-amount')) || 0;
        if (amountInput) amountInput.value = String(Math.round(selectedAmountCents / 100));
        paintAmount();
      });
    });
  }

  function selectOccasion(btn) {
    if (!btn) return;
    occasionButtons.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
    var slug = btn.getAttribute('data-slug') || '';
    var name = btn.getAttribute('data-name') || '';
    var icon = btn.getAttribute('data-icon') || '🎁';
    var from = btn.getAttribute('data-color-from') || '#F20D16';
    var to = btn.getAttribute('data-color-to') || '#101d3a';
    var amounts = String(btn.getAttribute('data-amounts') || '').split(',').map(function (v) { return parseInt(v, 10) || 0; }).filter(Boolean);
    if (hiddenOccasion) hiddenOccasion.value = slug;
    if (ticketOcc) ticketOcc.textContent = name;
    if (ticketIcon) ticketIcon.textContent = icon;
    if (hint) hint.innerHTML = 'Ocasión seleccionada: <b>' + esc(name) + '</b>';
    if (ticket) ticket.style.background = 'linear-gradient(135deg,' + from + ',' + to + ')';
    setDefaultMessage(btn.getAttribute('data-message') || '');
    renderPresets(amounts);
    selectedAmountCents = amounts.length ? amounts[0] : 0;
    if (amountInput) amountInput.value = selectedAmountCents ? String(Math.round(selectedAmountCents / 100)) : '';
    paintAmount();
  }

  occasionButtons.forEach(function (btn) {
    btn.addEventListener('click', function () { selectOccasion(btn); });
  });

  if (amountInput) {
    amountInput.addEventListener('input', function () {
      selectedAmountCents = 0;
      paintAmount();
    });
  }
  if (messageInput) messageInput.addEventListener('input', paintMessage);
  if (recipientInput) recipientInput.addEventListener('input', paintRecipient);

  // Inicializa el preview con la ocasión activa.
  var active = occasionButtons.filter(function (b) { return b.classList.contains('is-active'); })[0] || occasionButtons[0];
  if (active) {
    var initialAmounts = String(active.getAttribute('data-amounts') || '').split(',').map(function (v) { return parseInt(v, 10) || 0; }).filter(Boolean);
    selectedAmountCents = initialAmounts.length ? initialAmounts[0] : 0;
    if (amountInput && !amountInput.value) amountInput.value = selectedAmountCents ? String(Math.round(selectedAmountCents / 100)) : '';
    if (ticket) {
      var f = active.getAttribute('data-color-from') || '#F20D16';
      var t = active.getAttribute('data-color-to') || '#101d3a';
      ticket.style.background = 'linear-gradient(135deg,' + f + ',' + t + ')';
    }
    paintAmount();
  }
  paintMessage();
  paintRecipient();

  if (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      showError('');
      var amountCents = selectedAmountCents;
      if (!amountCents && amountInput) {
        var units = parseInt(String(amountInput.value || '').replace(/[^\d]/g, ''), 10) || 0;
        amountCents = units * 100;
      }
      var min = Number(CFG.min_cents) || 10000;
      var max = Number(CFG.max_cents) || 2000000;
      if (amountCents < min) { showError('El monto mínimo es ' + money(min) + '.'); return; }
      if (amountCents > max) { showError('El monto máximo es ' + money(max) + '.'); return; }
      var senderName = String((document.getElementById('gcSenderName') || {}).value || '').trim();
      var senderEmail = String((document.getElementById('gcSenderEmail') || {}).value || '').trim();
      var recipientName = String((document.getElementById('gcRecipientName') || {}).value || '').trim();
      var recipientEmail = String((document.getElementById('gcRecipientEmail') || {}).value || '').trim();
      var terms = document.getElementById('gcTerms');
      if (senderName.length < 2) { showError('Escribe tu nombre.'); return; }
      if (!validEmail(senderEmail)) { showError('Escribe un correo válido para ti.'); return; }
      if (recipientName.length < 2) { showError('Escribe el nombre de quien recibe la tarjeta.'); return; }
      if (!validEmail(recipientEmail)) { showError('Escribe un correo válido para el destinatario.'); return; }
      if (!terms || !terms.checked) { showError('Debes aceptar los términos y condiciones.'); return; }
      var methodEl = form.querySelector('input[name="payment_method"]:checked');
      var payload = {
        occasion_slug: hiddenOccasion ? hiddenOccasion.value : '',
        amount_cents: amountCents,
        sender_name: senderName,
        sender_email: senderEmail,
        recipient_name: recipientName,
        recipient_email: recipientEmail,
        message: String((document.getElementById('gcMessage') || {}).value || '').trim(),
        delivery_at: String((document.getElementById('gcDeliveryAt') || {}).value || ''),
        payment_method: methodEl ? methodEl.value : '',
        terms: true
      };
      setBusy(true);
      fetch(CFG.purchase_url || '/api/v1/public/gift-cards/purchase', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d && d.ok && d.data && d.data.uuid) {
          var pay = d.data.payment || {};
          if ((pay.method === 'mercadopago' || pay.method === 'epayco') && pay.redirect_url) {
            window.location.href = pay.redirect_url;
            return;
          }
          window.location.href = '/pedido/' + encodeURIComponent(d.data.uuid);
          return;
        }
        setBusy(false);
        showError((d && d.message) || 'No pudimos crear el pedido. Intenta de nuevo.');
      }).catch(function () {
        setBusy(false);
        showError('Error de conexión. Revisa tu internet e intenta de nuevo.');
      });
    });
  }

  // Consulta de saldo
  if (balanceForm) {
    var balanceResult = document.getElementById('gcBalanceResult');
    var balanceSubmit = document.getElementById('gcBalanceSubmit');
    var typeLabels = {
      issue: 'Emisión',
      redeem: 'Uso en pedido',
      release: 'Devolución de reserva',
      void: 'Anulación',
      adjust: 'Ajuste',
      refund: 'Reembolso'
    };
    balanceForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!balanceResult) return;
      balanceResult.classList.remove('is-on');
      balanceResult.innerHTML = '<span style="color:#c7d0df">Consultando…</span>';
      balanceResult.classList.add('is-on');
      var code = String((document.getElementById('gcBalanceCode') || {}).value || '').trim();
      var pin = String((document.getElementById('gcBalancePin') || {}).value || '').replace(/\D+/g, '');
      if (!code || pin.length !== 6) {
        balanceResult.innerHTML = '<span class="gc-balance__error">Escribe el código completo y el PIN de 6 dígitos.</span>';
        return;
      }
      if (balanceSubmit) { balanceSubmit.disabled = true; balanceSubmit.textContent = '…'; }
      fetch(CFG.balance_url || '/api/v1/public/gift-cards/balance', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ code: code, pin: pin })
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (balanceSubmit) { balanceSubmit.disabled = false; balanceSubmit.textContent = 'Consultar'; }
        if (!d || !d.ok || !d.data) {
          balanceResult.innerHTML = '<span class="gc-balance__error">' + esc((d && d.message) || 'No pudimos consultar la tarjeta.') + '</span>';
          return;
        }
        var c = d.data;
        var tx = Array.isArray(c.transactions) ? c.transactions : [];
        var html = '<div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">'
          + '<div><div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:#aab6c8">Saldo disponible</div>'
          + '<div style="font:800 28px Sora,system-ui,sans-serif;color:#fff;margin-top:3px">' + esc(money(c.balance_cents)) + '</div></div>'
          + '<div style="text-align:right;font-size:11px;color:#c7d0df"><div>Tarjeta <b style="color:#fff">' + esc(c.code_masked || '') + '</b></div>'
          + '<div>Vence: <b style="color:#fff">' + esc(c.expires_at ? String(c.expires_at).slice(0, 10) : 'Sin vencimiento') + '</b></div>'
          + (c.occasion ? '<div>Ocasión: <b style="color:#fff">' + esc(c.occasion.icon + ' ' + c.occasion.name) + '</b></div>' : '')
          + '</div></div>';
        if (tx.length) {
          html += '<div style="margin-top:12px;border-top:1px solid rgba(255,255,255,.14);padding-top:10px"><div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:#aab6c8;margin-bottom:6px">Movimientos</div>';
          tx.slice(0, 8).forEach(function (x) {
            var sign = Number(x.amount_cents) > 0 ? '+' : '';
            html += '<div style="display:flex;justify-content:space-between;gap:10px;padding:5px 0;border-top:1px solid rgba(255,255,255,.06);font-size:11.5px">'
              + '<span>' + esc(typeLabels[x.type] || x.type) + ' · ' + esc(String(x.created_at || '').slice(0, 10)) + '</span>'
              + '<b style="color:' + (Number(x.amount_cents) > 0 ? '#8ef0c8' : '#fff') + '">' + sign + esc(money(Math.abs(Number(x.amount_cents) || 0))) + '</b></div>';
          });
          html += '</div>';
        }
        balanceResult.innerHTML = html;
      }).catch(function () {
        if (balanceSubmit) { balanceSubmit.disabled = false; balanceSubmit.textContent = 'Consultar'; }
        balanceResult.innerHTML = '<span class="gc-balance__error">Error de conexión. Intenta de nuevo.</span>';
      });
    });
  }

  // Enlace directo /tarjetas-de-regalo/consultar o #consultar
  try {
    if (window.location.hash === '#consultar' || /\/tarjetas-de-regalo\/consultar\/?$/.test(window.location.pathname)) {
      var consult = document.getElementById('consultar');
      if (consult) setTimeout(function () { consult.scrollIntoView({ behavior: 'smooth', block: 'start' }); }, 120);
    }
  } catch (e) {}
})();
