(function () {
  'use strict';
  var PAY = window.SONIKA_PAY || {};
  var CARTKEY = window.SONIKA_CARTKEY || 'sonika_cart';
  var MKEY = 'sonika_pay_method';
  var CKEY = 'sonika_coupon';
  var methods = (PAY.methods || []).slice();
  if (!methods.length) methods = [{ id: 'request', label: 'Coordinar pago por WhatsApp' }];
  var cartRoot = document.getElementById('skpayCartItems');
  var checkoutRoot = document.getElementById('skpayCheckoutItems');
  var isCart = !!cartRoot;
  var isCheckout = !!checkoutRoot;
  var proofForm = document.getElementById('skpayProofForm');
  var qrModal = document.getElementById('skqrModal');
  if (!isCart && !isCheckout && !proofForm && !qrModal) return;

  var state = { items: readItems(), method: '', coupon: readCoupon(), points: 0, quote: null, busy: false, seq: 0, error: '' };

  function readItems() {
    try {
      var v = JSON.parse(localStorage.getItem(CARTKEY) || '[]');
      if (!Array.isArray(v)) return [];
      return v.filter(function (i) { return i && (i.handle || i.ref); }).map(function (i) {
        var out = {
          handle: String(i.handle || i.ref),
          title: String(i.title || ''),
          price: Number(i.price) || 0,
          img: String(i.img || i.image || ''),
          category: String(i.category || 'Productos'),
          qty: Math.max(1, Math.min(99, parseInt(i.qty, 10) || 1))
        };
        if (i.rec && typeof i.rec === 'object') {
          out.rec = {
            source: String(i.rec.source || ''),
            campaign: String(i.rec.campaign || ''),
            slot: String(i.rec.slot || ''),
            reason: String(i.rec.reason || '')
          };
        }
        return out;
      });
    } catch (e) { return []; }
  }

  function readCoupon() {
    try { return String(localStorage.getItem(CKEY) || '').trim().toUpperCase(); } catch (e) { return ''; }
  }
  function saveCoupon(code) {
    state.coupon = String(code || '').trim().toUpperCase();
    try {
      if (state.coupon) localStorage.setItem(CKEY, state.coupon);
      else localStorage.removeItem(CKEY);
    } catch (e) {}
  }

  function writeItems(items) {
    state.items = items;
    try { localStorage.setItem(CARTKEY, JSON.stringify(items)); } catch (e) {}
    paintCount();
    try { window.dispatchEvent(new CustomEvent('wwi-cart-change', { detail: { items: items } })); } catch (e) {}
  }

  function paintCount() {
    var n = state.items.reduce(function (a, i) { return a + (parseInt(i.qty, 10) || 0); }, 0);
    document.querySelectorAll('#cartCount,[data-cart-count]').forEach(function (el) {
      el.textContent = n > 99 ? '99+' : String(n);
      if (el.id === 'cartCount') el.style.display = n > 0 ? '' : 'none';
    });
    var label = document.getElementById('skpayCartCount');
    if (label) label.textContent = n > 0 ? '(' + n + ' artículo' + (n === 1 ? '' : 's') + ')' : '';
  }

  function enabledMethods() { return methods.filter(function (m) { return !m.disabled; }); }
  function firstEnabledMethod() {
    var enabled = enabledMethods();
    return enabled.length ? String(enabled[0].id) : 'request';
  }
  function methodIds() { return enabledMethods().map(function (m) { return String(m.id); }); }
  function findMethod(id) {
    for (var i = 0; i < methods.length; i++) if (String(methods[i].id) === String(id) && !methods[i].disabled) return methods[i];
    return null;
  }
  function initialMethod() {
    var saved = '';
    try { saved = localStorage.getItem(MKEY) || ''; } catch (e) {}
    if (findMethod(saved)) return String(saved);
    return firstEnabledMethod();
  }
  function setMethod(id) {
    state.method = findMethod(id) ? String(id) : firstEnabledMethod();
    try { localStorage.setItem(MKEY, state.method); } catch (e) {}
    syncRadios();
  }
  function syncRadios() {
    var found = false;
    document.querySelectorAll('.skpay__method').forEach(function (card) {
      var input = card.querySelector('input[type="radio"]');
      if (!input) return;
      var on = String(input.value) === String(state.method);
      input.checked = on;
      if (on) found = true;
      card.classList.toggle('is-selected', on);
    });
    if (!found) {
      state.method = firstEnabledMethod();
      var first = document.querySelector('.skpay__method[data-sk-method="' + state.method + '"] input[type="radio"]');
      if (first) { first.checked = true; var card = first.closest('.skpay__method'); if (card) card.classList.add('is-selected'); }
    }
  }

  function esc(v) {
    var d = document.createElement('div');
    d.textContent = v == null ? '' : String(v);
    return d.innerHTML;
  }
  function escAttr(v) {
    return esc(v).replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }
  function money(cents) {
    var n = (Number(cents) || 0) / 100;
    var cur = String(PAY.currency || 'COP');
    try {
      return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: cur,
        minimumFractionDigits: cur === 'COP' ? 0 : 2,
        maximumFractionDigits: cur === 'COP' ? 0 : 2
      }).format(n);
    } catch (e) {
      return '$' + Math.round(n).toLocaleString('es-CO');
    }
  }
  function qtyOf(ref) {
    var found = state.items.filter(function (i) { return i.handle === ref; })[0];
    return found ? found.qty : 1;
  }
  function setQty(ref, q) {
    q = Math.max(1, Math.min(99, parseInt(q, 10) || 1));
    var items = state.items.map(function (i) {
      if (i.handle === ref) i.qty = q;
      return i;
    });
    writeItems(items);
    refresh();
  }
  function removeItem(ref) {
    writeItems(state.items.filter(function (i) { return i.handle !== ref; }));
    refresh();
  }
  function itemRef(i) { return String(i.handle || i.ref || ''); }

  function errorBoxes() {
    return [document.getElementById('skpayError'), document.getElementById('skpayCartError')].filter(Boolean);
  }
  function setError(msg) {
    state.error = String(msg || '');
    errorBoxes().forEach(function (el) {
      el.textContent = state.error;
      el.hidden = state.error === '';
      el.classList.toggle('skpay__alert', state.error !== '');
    });
  }
  function clearError() { setError(''); }

  function digits(v) { return String(v == null ? '' : v).replace(/\D+/g, ''); }

  function setFieldError(name, msg) {
    var el = document.getElementById('skpay' + name.charAt(0).toUpperCase() + name.slice(1));
    var err = document.getElementById('skpay' + name.charAt(0).toUpperCase() + name.slice(1) + 'Error');
    if (!el) return;
    var bad = !!msg;
    el.classList.toggle('is-invalid', bad);
    el.setAttribute('aria-invalid', bad ? 'true' : 'false');
    if (err) {
      err.textContent = msg || '';
      err.hidden = !bad;
    }
  }

  function validateCheckoutField(name) {
    var el = document.getElementById('skpay' + name.charAt(0).toUpperCase() + name.slice(1));
    if (!el) return true;
    var v = String(el.value || '').trim();
    var msg = '';
    if (name === 'name' && v.length < 2) msg = 'Escribe tu nombre completo.';
    if (name === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) msg = 'Escribe un correo electrónico válido.';
    if (name === 'phone' && (digits(v).length < 7 || digits(v).length > 15)) msg = 'Escribe un teléfono válido (7 a 15 dígitos).';
    if (name === 'document' && v.length < 5) msg = 'Escribe tu documento (CC, NIT o CE).';
    if (name === 'line1' && v.length < 5) msg = 'Escribe la dirección de entrega.';
    if (name === 'department' && v.length !== 2) msg = 'Selecciona el departamento.';
    if (name === 'municipality' && v.length !== 5) msg = 'Selecciona la ciudad o municipio.';
    if (name === 'notes' && v.length < 30) msg = 'Escribe al menos 30 caracteres con indicaciones para la entrega.';
    if (name === 'notes' && v.length > 500) msg = 'Máximo 500 caracteres.';
    if (name === 'terms' && !el.checked) msg = 'Debes aceptar los términos y condiciones.';
    setFieldError(name, msg);
    return !msg;
  }

  function validateCheckoutForm() {
    var names = ['name', 'email', 'phone', 'document', 'line1', 'department', 'municipality', 'notes', 'terms'];
    var first = null;
    for (var i = 0; i < names.length; i++) {
      var ok = validateCheckoutField(names[i]);
      if (!ok && !first) first = document.getElementById('skpay' + names[i].charAt(0).toUpperCase() + names[i].slice(1));
    }
    if (first) {
      setError('Revisa los campos marcados. Todos los datos son obligatorios.');
      try { first.focus({ preventScroll: true }); } catch (e) { first.focus(); }
      try { first.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) {}
      return false;
    }
    return true;
  }

  function updateNotesCounter() {
    var notes = document.getElementById('skpayNotes');
    var count = document.getElementById('skpayNotesCount');
    if (!notes || !count) return;
    var n = String(notes.value || '').trim().length;
    count.textContent = n < 30 ? n + '/30' : n + ' caracteres';
    count.classList.toggle('is-ok', n >= 30);
    count.setAttribute('aria-label', n + ' caracteres. Mínimo 30, máximo 500.');
  }

  function initCheckoutValidation(form) {
    var fields = ['name', 'email', 'phone', 'document', 'line1', 'department', 'municipality', 'notes'];
    fields.forEach(function (name) {
      var el = document.getElementById('skpay' + name.charAt(0).toUpperCase() + name.slice(1));
      if (!el) return;
      el.addEventListener('blur', function () { validateCheckoutField(name); });
      el.addEventListener('input', function () {
        if (name === 'notes') updateNotesCounter();
        if (el.getAttribute('aria-invalid') === 'true') validateCheckoutField(name);
      });
    });
    var terms = document.getElementById('skpayTerms');
    if (terms) terms.addEventListener('change', function () { if (terms.checked) validateCheckoutField('terms'); });
    document.querySelectorAll('[data-sk-note-chip]').forEach(function (chip) {
      chip.addEventListener('click', function () {
        var notes = document.getElementById('skpayNotes');
        if (!notes) return;
        var text = chip.getAttribute('data-sk-note-chip') || '';
        var start = typeof notes.selectionStart === 'number' ? notes.selectionStart : notes.value.length;
        var end = typeof notes.selectionEnd === 'number' ? notes.selectionEnd : notes.value.length;
        notes.value = notes.value.slice(0, start) + text + notes.value.slice(end);
        notes.focus();
        var pos = start + text.length;
        try { notes.setSelectionRange(pos, pos); } catch (e) {}
        notes.dispatchEvent(new Event('input', { bubbles: true }));
      });
    });
    updateNotesCounter();
  }

  function shippingContext() {
    var department = document.getElementById('skpayDepartment');
    var municipality = document.getElementById('skpayMunicipality');
    return {
      department_code: department ? department.value : '',
      municipality_code: municipality ? municipality.value : ''
    };
  }

  function shippingChanged() {
    if (!isCheckout || !state.items.length) return;
    state.quote = null;
    renderLoading();
    refresh();
  }

  function initShippingFields() {
    var department = document.getElementById('skpayDepartment');
    var municipality = document.getElementById('skpayMunicipality');
    if (!department || !municipality) return;
    var selectedMunicipality = municipality.getAttribute('data-selected') || '';

    function fillMunicipalities(rows, selected) {
      municipality.innerHTML = '<option value="">Selecciona la ciudad o municipio</option>';
      rows.forEach(function (row) {
        var option = document.createElement('option');
        option.value = String(row.code || '');
        option.textContent = String(row.name || '');
        if (String(row.code || '') === String(selected || '')) option.selected = true;
        municipality.appendChild(option);
      });
      municipality.disabled = rows.length === 0;
      if (rows.length === 0) municipality.innerHTML = '<option value="">Sin municipios disponibles</option>';
    }

    function loadMunicipalities(code, selected) {
      if (!code) {
        municipality.disabled = true;
        municipality.innerHTML = '<option value="">Selecciona primero el departamento</option>';
        return;
      }
      municipality.disabled = true;
      municipality.innerHTML = '<option value="">Cargando municipios...</option>';
      fetch('/api/v1/public/shipping/municipalities?department=' + encodeURIComponent(code), { credentials: 'same-origin' })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (!data || !data.ok || !Array.isArray(data.data)) throw new Error('Sin municipios');
          fillMunicipalities(data.data, selected);
        })
        .catch(function () {
          municipality.disabled = true;
          municipality.innerHTML = '<option value="">No se pudieron cargar los municipios</option>';
        });
    }

    department.addEventListener('change', function () {
      municipality.value = '';
      loadMunicipalities(department.value, '');
      validateCheckoutField('department');
      validateCheckoutField('municipality');
    });
    municipality.addEventListener('change', function () {
      validateCheckoutField('municipality');
      if (municipality.value) shippingChanged();
    });
    loadMunicipalities(department.value, selectedMunicipality);
  }

  function quoteFor(method) {
    var items = state.items.map(function (i) {
      var out = { ref: itemRef(i), qty: parseInt(i.qty, 10) || 1 };
      if (i.rec && i.rec.source) out.rec = i.rec;
      return out;
    });
    return fetch('/api/v1/public/checkout/quote', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        items: items,
        payment_method: method,
        coupon: state.coupon,
        points: state.points,
        shipping: shippingContext()
      })
    }).then(function (r) { return r.json(); });
  }

  function addiCard() {
    return document.querySelector('.skpay__method[data-sk-method="addi"]');
  }

  function addiFallbackMethod() {
    for (var i = 0; i < methods.length; i++) if (String(methods[i].id) !== 'addi') return String(methods[i].id);
    return '';
  }

  function addiSetState(active, note, disabled) {
    var card = addiCard();
    if (!card) return;
    var input = card.querySelector('input[type="radio"]');
    card.hidden = !active;
    if (input) input.disabled = !!disabled;
    var desc = card.querySelector('.skpay__method-desc');
    var hint = card.querySelector('[data-sk-addi-note]');
    if (desc && note) {
      if (!hint) {
        hint = document.createElement('span');
        hint.setAttribute('data-sk-addi-note', '');
        hint.className = 'skpay__tiny';
        hint.style.display = 'block';
        hint.style.marginTop = '4px';
        desc.parentNode.appendChild(hint);
      }
      hint.textContent = note;
    } else if (hint) {
      hint.remove();
    }
    if (input && input.checked && (!active || disabled)) {
      var fallback = addiFallbackMethod();
      if (fallback) setMethod(fallback);
    }
  }

  function refreshAddi() {
    var card = addiCard();
    if (!card || !state.quote) return;
    var amount = Number(state.quote.total_cents) || 0;
    var force = !!(PAY.addi && PAY.addi.force_visible);
    var seq = (state.addiSeq || 0) + 1;
    state.addiSeq = seq;
    fetch('/api/v1/public/addi/config?amount_cents=' + encodeURIComponent(amount), { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        if (seq !== state.addiSeq) return;
        var cfg = d && d.ok ? (d.data || {}) : null;
        var active = !!(cfg && cfg.isActiveAlly);
        if (!active && !force) {
          addiSetState(true, 'Estamos habilitando este medio de pago.', true);
          return;
        }
        var min = Number(cfg && cfg.minAmount) || 0;
        var max = Number(cfg && cfg.maxAmount) || 0;
        var valid = !!cfg && active && amount >= min * 100 && (max <= 0 || amount <= max * 100);
        if (!valid) {
          var note = active ? 'Addi aplica para compras entre ' + money(min * 100) + ' y ' + money(max * 100) + '.' : 'Addi no está activo para este comercio.';
          addiSetState(true, note, true);
        } else {
          addiSetState(true, '', false);
        }
      })
      .catch(function () {
        if (seq !== state.addiSeq) return;
        addiSetState(true, 'No se pudo verificar Addi. Intenta de nuevo más tarde.', true);
      });
  }

  function refresh() {
    state.items = readItems();
    if (!state.items.length) {
      state.quote = null;
      state.busy = false;
      state.seq++;
      clearError();
      renderEmpty();
      return;
    }
    if (!findMethod(state.method)) setMethod(initialMethod());
    var seq = ++state.seq;
    state.busy = true;
    clearError();
    if (!state.quote) renderLoading();
    quoteFor(state.method).then(function (d) {
      if (seq !== state.seq) return;
      state.busy = false;
      if (!d || !d.ok || !d.data) {
        state.quote = null;
        setError((d && d.message) || 'No pudimos calcular los totales. Intenta de nuevo.');
        renderError(d && d.message);
        return;
      }
      state.quote = d.data;
      renderAll();
      refreshAddi();
      try { window.dispatchEvent(new CustomEvent('sonika-quote', { detail: { quote: state.quote } })); } catch (e) {}
    }).catch(function () {
      if (seq !== state.seq) return;
      state.busy = false;
      setError('Error de conexión. Revisa tu carrito e intenta de nuevo.');
      renderError('Error de conexión. Revisa tu carrito e intenta de nuevo.');
    });
  }

  function renderEmpty() {
    var html = '<div class="skpay__empty"><b>Tu carrito está vacío.</b><p class="skpay__muted">Explora la tienda y agrega tus productos para continuar.</p><a class="skpay__btn skpay__btn--sm" href="/tienda" style="margin-top:12px">Explorar tienda</a></div>';
    if (cartRoot) cartRoot.innerHTML = html;
    if (checkoutRoot) checkoutRoot.innerHTML = html;
    ['skpaySummary', 'skpayCheckoutSummary'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) { el.innerHTML = ''; el.removeAttribute('aria-busy'); }
    });
    disableActions(true);
  }

  function renderLoading() {
    var sk = '<div class="skpay__loading" aria-hidden="true"><div class="skpay__skeleton"></div><div class="skpay__skeleton"></div><div class="skpay__skeleton"></div></div>';
    if (cartRoot) cartRoot.innerHTML = sk;
    if (checkoutRoot) checkoutRoot.innerHTML = sk;
    ['skpaySummary', 'skpayCheckoutSummary'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) { el.innerHTML = sk; el.setAttribute('aria-busy', 'true'); }
    });
  }

  function renderError(msg) {
    var html = '<div class="skpay__alert">' + esc(msg || 'No pudimos calcular los totales.') + '</div>';
    if (cartRoot) cartRoot.innerHTML = html;
    if (checkoutRoot) checkoutRoot.innerHTML = html;
    ['skpaySummary', 'skpayCheckoutSummary'].forEach(function (id) {
      var el = document.getElementById(id);
      if (el) { el.innerHTML = ''; el.removeAttribute('aria-busy'); }
    });
    disableActions(true);
  }

  function issueLabel(issue) {
    if (issue === 'out_of_stock') return 'Agotado';
    if (issue === 'not_found') return 'No disponible';
    if (issue === 'price_changed') return 'Precio actualizado';
    return 'Revisar';
  }

  function lineHtml(line, compact) {
    var ref = String(line.ref || '');
    var qty = Math.max(1, parseInt(line.qty, 10) || 1);
    var orig = Number(line.original_line_cents) || 0;
    var after = Number(line.subtotal_after_cents) || 0;
    var save = Number(line.discount_cents) || 0;
    var recSave = Number(line.recommendation_discount_cents) || 0;
    var bad = line.available === false || (line.issue && line.issue !== 'ok');
    var img = line.image
      ? '<img src="' + escAttr(line.image) + '" alt="" width="58" height="58" loading="lazy" />'
      : '<span class="skpay__item-ph" aria-hidden="true"></span>';
    var price = '<span class="skpay__new">' + money(after) + '</span>';
    if (save > 0 || recSave > 0) price = '<span class="skpay__old">' + money(orig) + '</span>' + price;
    var title = line.title || ref;
    var titleHtml = '<a href="/producto/' + encodeURIComponent(ref) + '">' + esc(title) + '</a>';
    var lineSaves = '';
    if (save > 0) lineSaves += '<span class="skpay__item-save">Bre-B -' + money(save) + '</span>';
    if (recSave > 0) lineSaves += '<span class="skpay__item-save skpay__item-save--rec">Recomendado -' + money(recSave) + '</span>';
    var warn = bad ? '<span class="skpay__badge skpay__badge--warn">' + esc(issueLabel(line.issue)) + '</span>' : '';
    var qtyHtml = '';
    if (!compact) {
      qtyHtml = '<div class="skpay__qty" aria-label="Cantidad">' +
        '<button type="button" data-skq="dec" data-ref="' + escAttr(ref) + '" aria-label="Quitar una unidad">−</button>' +
        '<input value="' + qty + '" readonly aria-label="Cantidad de ' + escAttr(title) + '" />' +
        '<button type="button" data-skq="inc" data-ref="' + escAttr(ref) + '" aria-label="Agregar una unidad">+</button>' +
        '</div><button type="button" class="skpay__link" data-skremove data-ref="' + escAttr(ref) + '">Eliminar</button>';
    }
    var shareHtml = '';
    if (isCart && !compact && ref && !bad) {
      var sharePrice = Number(line.original_unit_cents) || Math.round(after / qty);
      var shareAttrs = 'data-ref="' + escAttr(ref) + '" data-title="' + escAttr(title) + '" data-price-cents="' + Math.max(0, Math.round(sharePrice)) + '" data-image="' + escAttr(line.image || '') + '"';
      shareHtml = '<div class="skpay__item-share">' +
        '<button type="button" class="skshare-trigger" data-skshare="friend" ' + shareAttrs + ' data-track="compartir producto" aria-label="Compartir ' + escAttr(title) + ' con un amigo, familiar o conocido">' +
        '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg><span>Compartir</span></button>' +
        '<button type="button" class="skshare-trigger skshare-trigger--partner" data-skshare="partner" ' + shareAttrs + ' data-track="compartir partner" aria-label="Comparte este producto como Partner SONIKA y gana comisión">' +
        '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2l1.8 6.2L20 10l-6.2 1.8L12 18l-1.8-6.2L4 10l6.2-1.8L12 2Z"/></svg><span><b>Comparte y gana</b><small>Partner SONIKA</small></span></button>' +
        '</div>';
    }
    return '<div class="skpay__item" data-line-ref="' + escAttr(ref) + '">' +
      img +
      '<div><p class="skpay__item-title">' + titleHtml + '</p><div class="skpay__item-meta"><span>x' + qty + '</span>' + lineSaves + warn + '</div>' + qtyHtml + '</div>' +
      '<div class="skpay__item-price">' + price + '</div>' +
      shareHtml +
      '</div>';
  }

  function renderItems() {
    var q = state.quote;
    if (cartRoot) {
      cartRoot.innerHTML = q && q.items && q.items.length
        ? q.items.map(function (l) { return lineHtml(l, false); }).join('')
        : '<div class="skpay__empty"><b>No hay productos disponibles.</b><p class="skpay__muted">Regresa a la tienda y elige otros productos.</p></div>';
    }
    if (checkoutRoot) {
      checkoutRoot.innerHTML = q && q.items && q.items.length
        ? q.items.map(function (l) { return lineHtml(l, true); }).join('')
        : '<div class="skpay__empty"><b>Tu carrito está vacío.</b></div>';
    }
  }

  function summaryHtml() {
    var q = state.quote;
    if (!q) return '';
    var brebDiscount = Number(q.breb_discount_cents) || 0;
    var recDiscount = Number(q.recommendation_discount_cents) || 0;
    var couponDiscount = Number(q.coupon_discount_cents) || 0;
    var tax = Number(q.tax_after_cents) || 0;
    var taxRate = Number(q.tax_rate) || 0;
    var total = Number(q.total_cents) || 0;
    var free = !!q.free_shipping;
    var h = '';
    if (brebDiscount > 0) {
      h += '<div class="skpay__badge-line"><span>Ahorras con Bre-B</span><b>' + money(brebDiscount) + '</b></div>';
      h += '<div class="skpay__line"><span>Precio normal</span><span>' + money(q.subtotal_before_cents) + '</span></div>';
      h += '<div class="skpay__line skpay__line--save"><span>Descuento Bre-B</span><span>−' + money(brebDiscount) + '</span></div>';
      h += '<div class="skpay__line"><span>Subtotal</span><span>' + money(q.subtotal_after_cents) + '</span></div>';
    } else {
      h += '<div class="skpay__line"><span>Subtotal</span><span>' + money(q.subtotal_after_cents) + '</span></div>';
    }
    if (couponDiscount > 0) {
      h += '<div class="skpay__line skpay__line--save"><span>Cupón ' + esc(q.coupon_code || '') + '</span><span>−' + money(couponDiscount) + '</span></div>';
    }
    if (recDiscount > 0) {
      h += '<div class="skpay__line skpay__line--save"><span>Ahorro recomendados</span><span>-' + money(recDiscount) + '</span></div>';
    }
    if (Number(q.points_discount_cents) > 0) {
      h += '<div class="skpay__line skpay__line--save"><span>Puntos (' + Number(q.points_used) + ')</span><span>-' + money(q.points_discount_cents) + '</span></div>';
    }
    h += '<div class="skpay__line skpay__line--muted"><span>IVA incluido' + (taxRate > 0 ? ' (' + taxRate + '%)' : '') + '</span><span>' + money(tax) + '</span></div>';
    h += '<div class="skpay__line"><span>Envío</span><span class="' + (free ? 'skpay__free' : '') + '">' + (free ? 'Gratis' : money(q.shipping_cents)) + '</span></div>';
    if (q.shipping && q.shipping.eta_label && q.shipping.location_selected) {
      var place = (q.shipping.municipality || '') === (q.shipping.department || '') ? (q.shipping.municipality || '') : ((q.shipping.municipality || '') + ', ' + (q.shipping.department || ''));
      h += '<div class="skpay__hint">Entrega estimada: ' + esc(q.shipping.eta_label) + ' en ' + esc(place) + '.</div>';
    } else if (q.shipping && q.shipping.location_missing) {
      h += '<div class="skpay__hint">Selecciona departamento y municipio para calcular el envío exacto.</div>';
    }
    if (free) {
      h += '<div class="skpay__hint">Envío gratis aplicado: tu compra supera el mínimo antes de IVA.</div>';
    } else {
      var missing = Math.max(0, (Number(q.free_shipping_net_cents) || 0) - (Number(q.net_before_cents) || 0));
      h += '<div class="skpay__hint skpay__hint--warn">Te faltan ' + money(missing) + ' (antes de IVA) para obtener envío gratis.</div>';
    }
    h += '<div class="skpay__line skpay__line--total"><span>Total</span><span>' + money(total) + '</span></div>';
    if (recDiscount > 0) {
      h += '<div class="skpay__rec-total"><span>Ahorro total en recomendados</span><b>-' + money(recDiscount) + '</b></div>';
    }
    if (state.method === 'breb') {
      h += '<p class="skpay__tiny" style="margin:10px 0 0">Al confirmar, recibirás el QR de Bre-B y podrás adjuntar tu comprobante. El pedido queda pendiente de verificación.</p>';
    } else if (state.method === 'mercadopago' || state.method === 'epayco') {
      h += '<p class="skpay__tiny" style="margin:10px 0 0">Al confirmar, te llevaremos a la pasarela para completar el pago.</p>';
    } else if (state.method === 'addi') {
      h += '<p class="skpay__tiny" style="margin:10px 0 0">Al confirmar, te llevaremos a Addi para solicitar tu crédito y completar la compra.</p>';
    } else {
      h += '<p class="skpay__tiny" style="margin:10px 0 0">Al confirmar, coordinaremos el pago y el envío por WhatsApp.</p>';
    }
    if (q.issue_count > 0) {
      var names = (q.issues || []).slice(0, 3).map(function (i) { return i.title || i.ref || ''; }).filter(Boolean);
      h += '<div class="skpay__alert" style="margin-top:11px">Revisa la disponibilidad' + (names.length ? ': ' + esc(names.join(', ')) : '') + ' antes de continuar.</div>';
    }
    return h;
  }

  function renderSummary() {
    var h = summaryHtml();
    ['skpaySummary', 'skpayCheckoutSummary'].forEach(function (id) {
      var el = document.getElementById(id);
      if (!el) return;
      el.innerHTML = '<div class="skpay__summary-head"><h2>Resumen</h2><span class="skpay__status-pill">' + esc(methodLabel()) + '</span></div><div class="skpay__summary-body">' + h;
      if (id === 'skpaySummary' && state.quote) {
        el.innerHTML += '<a class="skpay__btn" id="skpayGoCheckout" href="/checkout">Continuar con el pago</a>';
        el.innerHTML += '</div>';
      } else if (id === 'skpayCheckoutSummary' && state.quote) {
        el.innerHTML += '<div class="skpay__trust"><span>✓ Pago por verificar</span><span>✓ Envío a toda Colombia</span></div></div>';
      } else {
        el.innerHTML += '</div>';
      }
      el.setAttribute('aria-busy', 'false');
    });
    var save = document.querySelector('[data-sk-save]');
    if (save) {
      var d = state.quote ? Number(state.quote.breb_discount_cents) || 0 : 0;
      if (state.method === 'breb' && d > 0) {
        save.hidden = false;
        save.textContent = 'Ahorras ' + money(d);
      } else {
        save.hidden = true;
        save.textContent = '';
      }
    }
    renderCouponMsg();
    renderPointsMsg();
    disableActions(false);
  }

  function renderPointsMsg() {
    var el = document.getElementById('skpayPointsMsg');
    if (!el) return;
    var q = state.quote;
    if (state.points <= 0) {
      el.textContent = '';
      el.style.color = '';
      return;
    }
    if (!q) {
      el.textContent = 'Calculando puntos…';
      el.style.color = '#6b7280';
      return;
    }
    if (Number(q.points_used) > 0) {
      el.textContent = 'Usando ' + Number(q.points_used) + ' puntos: −' + money(q.points_discount_cents);
      el.style.color = '#166534';
    } else {
      el.textContent = q.points_message || 'No se pudieron aplicar los puntos.';
      el.style.color = '#b42318';
    }
  }

  function renderCouponMsg() {
    var el = document.getElementById('skpayCouponMsg');
    if (!el) return;
    var q = state.quote;
    if (!state.coupon) {
      el.textContent = '';
      el.style.color = '';
      return;
    }
    if (!q) {
      el.textContent = 'Validando cupón…';
      el.style.color = '#6b7280';
      return;
    }
    if (q.coupon_ok === true && Number(q.coupon_discount_cents) > 0) {
      el.textContent = 'Cupón ' + (q.coupon_code || state.coupon) + ' aplicado: −' + money(q.coupon_discount_cents);
      el.style.color = '#166534';
    } else if (q.coupon_ok === false) {
      el.textContent = q.coupon_message || 'El cupón no es válido.';
      el.style.color = '#b42318';
    } else {
      el.textContent = '';
      el.style.color = '';
    }
  }

  function methodLabel() {
    var m = findMethod(state.method);
    return m ? m.label : 'Pago';
  }

  function disableActions(disabled) {
    var blocked = disabled || !state.quote || state.quote.issue_count > 0 || state.quote.coupon_ok === false || (state.points > 0 && Number(state.quote.points_used) <= 0);
    var go = document.getElementById('skpayGoCheckout');
    if (go) {
      go.setAttribute('aria-disabled', blocked ? 'true' : 'false');
      go.style.opacity = blocked ? '.55' : '';
      go.style.pointerEvents = blocked ? 'none' : '';
    }
    var submit = document.getElementById('skpaySubmit');
    if (submit) submit.disabled = blocked || state.busy;
  }

  function renderAll() {
    renderItems();
    renderSummary();
    syncRadios();
  }

  function bindActions() {
    document.addEventListener('click', function (e) {
      var qty = e.target.closest('[data-skq]');
      if (qty) {
        e.preventDefault();
        var ref = qty.getAttribute('data-ref') || '';
        var q = qtyOf(ref);
        setQty(ref, qty.getAttribute('data-skq') === 'inc' ? q + 1 : q - 1);
        return;
      }
      var rm = e.target.closest('[data-skremove]');
      if (rm) {
        e.preventDefault();
        removeItem(rm.getAttribute('data-ref') || '');
        return;
      }
      var go = e.target.closest('#skpayGoCheckout');
      if (go) {
        if (!state.quote || state.quote.issue_count > 0 || state.quote.coupon_ok === false) {
          e.preventDefault();
          setError(state.quote && state.quote.coupon_ok === false ? (state.quote.coupon_message || 'Revisa el cupón.') : 'Revisa la disponibilidad de tu carrito antes de continuar.');
        }
        return;
      }
      var apply = e.target.closest('#skpayCouponApply');
      if (apply) {
        e.preventDefault();
        var input = document.getElementById('skpayCoupon');
        var code = input ? String(input.value || '').trim().toUpperCase() : '';
        saveCoupon(code);
        if (input && code) input.value = code;
        state.quote = null;
        renderLoading();
        refresh();
        return;
      }
      var clear = e.target.closest('#skpayCouponClear');
      if (clear) {
        e.preventDefault();
        saveCoupon('');
        var ci = document.getElementById('skpayCoupon');
        if (ci) ci.value = '';
        state.quote = null;
        renderLoading();
        refresh();
        return;
      }
      var ptsApply = e.target.closest('#skpayPointsApply');
      if (ptsApply) {
        e.preventDefault();
        var pin = document.getElementById('skpayPoints');
        var bal = Number(PAY.points && PAY.points.balance) || 0;
        var val = Math.max(0, Math.min(bal, parseInt(pin && pin.value, 10) || 0));
        state.points = val;
        if (pin) pin.value = String(val);
        state.quote = null;
        renderLoading();
        refresh();
        return;
      }
      var ptsClear = e.target.closest('#skpayPointsClear');
      if (ptsClear) {
        e.preventDefault();
        state.points = 0;
        var pi = document.getElementById('skpayPoints');
        if (pi) pi.value = '0';
        state.quote = null;
        renderLoading();
        refresh();
        return;
      }
    });

    document.addEventListener('change', function (e) {
      if (!e.target.matches('.skpay__method input[type="radio"]')) return;
      setMethod(e.target.value);
      state.quote = null;
      renderLoading();
      refresh();
    });

    document.addEventListener('keydown', function (e) {
      if (e.key !== 'Enter' || !e.target || e.target.id !== 'skpayCoupon') return;
      e.preventDefault();
      var apply = document.getElementById('skpayCouponApply');
      if (apply) apply.click();
    });
  }

  function initCart() {
    if (!isCart) return;
    setMethod(initialMethod());
    bindActions();
    refresh();
  }

  function initCheckout() {
    if (!isCheckout) return;
    setMethod(initialMethod());
    bindActions();
    var form = document.getElementById('skpayForm');
    if (!form) return;
    var submit = document.getElementById('skpaySubmit');
    var label = submit ? submit.textContent : 'Confirmar pedido';
    initCheckoutValidation(form);
    initShippingFields();
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (state.busy) return;
      clearError();
      if (!state.items.length) { setError('Tu carrito está vacío.'); return; }
      if (!state.quote) { setError('Espera un momento mientras calculamos el total.'); return; }
      if (state.quote.issue_count > 0) { setError('Revisa la disponibilidad de tu carrito antes de continuar.'); return; }
      if (state.quote.coupon_ok === false) { setError(state.quote.coupon_message || 'Revisa el cupón antes de continuar.'); return; }
      if (state.points > 0 && Number(state.quote.points_used) <= 0) { setError(state.quote.points_message || 'No se pudieron aplicar los puntos.'); return; }
      var couponEl = document.getElementById('skpayCoupon');
      if (couponEl && String(couponEl.value || '').trim().toUpperCase() !== state.coupon) {
        setError('Presiona “Aplicar” para validar el cupón antes de confirmar.');
        couponEl.focus();
        return;
      }
      var pointsEl = document.getElementById('skpayPoints');
      if (pointsEl && (parseInt(pointsEl.value, 10) || 0) > 0 && state.points <= 0) {
        setError('Presiona “Usar puntos” para aplicar tus puntos antes de confirmar.');
        pointsEl.focus();
        return;
      }
      var addiInput = document.querySelector('.skpay__method[data-sk-method="addi"] input[type="radio"]');
      if (state.method === 'addi' && addiInput && addiInput.disabled) {
        setError('Addi no está disponible para este monto o en este momento.');
        return;
      }
      if (!validateCheckoutForm()) return;
      var name = (form.elements['name'] || {}).value || '';
      var email = (form.elements['email'] || {}).value || '';
      var phone = (form.elements['phone'] || {}).value || '';
      var doc = (form.elements['document'] || {}).value || '';
      var line1 = (form.elements['line1'] || {}).value || '';
      var department = (form.elements['department_code'] || {}).value || '';
      var municipality = (form.elements['municipality_code'] || {}).value || '';
      var departmentName = department ? String((form.elements['department_code'].options[form.elements['department_code'].selectedIndex] || {}).text || '').trim() : '';
      var municipalityName = municipality ? String((form.elements['municipality_code'].options[form.elements['municipality_code'].selectedIndex] || {}).text || '').trim() : '';
      var notes = (form.elements['notes'] || {}).value || '';
      var payload = {
        items: state.items.map(function (i) {
          var out = { ref: itemRef(i), qty: parseInt(i.qty, 10) || 1 };
          if (i.rec && i.rec.source) out.rec = i.rec;
          return out;
        }),
        customer: { name: name.trim(), email: email.trim(), phone: phone.trim(), document: doc.trim() },
        shipping: {
          department_code: department,
          municipality_code: municipality,
          address: {
            line1: line1.trim(),
            city: municipalityName,
            region: departmentName,
            department_code: department,
            municipality_code: municipality
          }
        },
        notes: notes.trim(),
        terms: true,
        payment_method: state.method,
        coupon: state.coupon,
        points: state.points,
        quote_code: (function () { try { return localStorage.getItem('sonika_quote_code') || ''; } catch (e) { return ''; } })()
      };
      state.busy = true;
      if (submit) { submit.disabled = true; submit.textContent = 'Creando pedido…'; }
      fetch('/api/v1/public/store/catalog-order', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
      }).then(function (r) { return r.json(); }).then(function (d) {
        if (d && d.ok && d.data && d.data.uuid) {
          try { localStorage.removeItem(CARTKEY); localStorage.removeItem(CKEY); localStorage.removeItem('sonika_quote_code'); } catch (e) {}
          state.items = [];
          state.coupon = '';
          state.points = 0;
          paintCount();
          var pay = d.data.payment || {};
          if ((pay.method === 'addi' || pay.method === 'sistecredito') && pay.redirect_url) {
            window.location.href = pay.redirect_url;
            return;
          }
          window.location.href = '/pedido/' + encodeURIComponent(d.data.uuid);
          return;
        }
        state.busy = false;
        if (submit) { submit.disabled = false; submit.textContent = label; }
        setError((d && d.message) || 'No pudimos crear el pedido. Intenta de nuevo.');
      }).catch(function () {
        state.busy = false;
        if (submit) { submit.disabled = false; submit.textContent = label; }
        setError('Error de conexión. Intenta de nuevo.');
      });
    });
    refresh();
  }

  function initProof() {
    if (!proofForm) return;
    var input = document.getElementById('skpayProof');
    var btn = document.getElementById('skpayProofBtn');
    var bar = document.getElementById('skpayProofBar');
    var pct = document.getElementById('skpayProofPct');
    var status = document.getElementById('skpayProofStatus');
    var filename = document.getElementById('skpayFileName');
    var uuid = proofForm.getAttribute('data-order-uuid') || '';
    var maxMb = parseFloat(proofForm.getAttribute('data-max-mb')) || 2;
    var label = btn ? btn.textContent : 'Enviar comprobante';
    var allowed = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];

    function proofStatus(msg, cls) {
      if (!status) return;
      status.textContent = msg;
      status.className = 'skpay__status' + (cls ? ' skpay__status--' + cls : '');
    }
    function progress(n) {
      n = Math.max(0, Math.min(100, Math.round(n)));
      if (bar) { bar.style.width = n + '%'; bar.parentNode.setAttribute('aria-valuenow', String(n)); }
      if (pct) pct.textContent = n + '%';
    }
    if (input) input.addEventListener('change', function () {
      var f = input.files && input.files[0];
      if (!f) { if (filename) filename.textContent = ''; return; }
      if (filename) filename.textContent = f.name + ' · ' + (f.size / 1048576).toFixed(2) + ' MB';
      if (f.size > maxMb * 1048576) proofStatus('El archivo supera ' + maxMb + ' MB.', 'err');
      else if (allowed.indexOf(f.type) < 0) proofStatus('Formato no permitido. Usa JPG, PNG, WebP o PDF.', 'err');
      else proofStatus('');
    });
    proofForm.addEventListener('submit', function (e) {
      e.preventDefault();
      if (!uuid) return;
      var f = input && input.files && input.files[0];
      if (!f) { proofStatus('Selecciona un comprobante.', 'err'); return; }
      if (f.size > maxMb * 1048576) { proofStatus('El archivo supera ' + maxMb + ' MB.', 'err'); return; }
      if (allowed.indexOf(f.type) < 0) { proofStatus('Formato no permitido. Usa JPG, PNG, WebP o PDF.', 'err'); return; }
      var fd = new FormData();
      fd.append('proof', f, f.name);
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '/api/v1/public/store/orders/' + encodeURIComponent(uuid) + '/proof');
      xhr.setRequestHeader('Accept', 'application/json');
      if (btn) { btn.disabled = true; btn.textContent = 'Enviando…'; }
      progress(0);
      proofStatus('Subiendo comprobante…');
      xhr.upload.addEventListener('progress', function (ev) {
        if (ev.lengthComputable) progress(ev.loaded / ev.total * 100);
      });
      xhr.onload = function () {
        var d = null;
        try { d = JSON.parse(xhr.responseText || '{}'); } catch (err) {}
        if (xhr.status >= 200 && xhr.status < 300 && d && d.ok) {
          progress(100);
          proofStatus(d.message || 'Comprobante recibido. Queda pendiente de verificación.', 'ok');
          if (input) input.value = '';
          if (filename) filename.textContent = '';
          var stateEl = document.getElementById('skpayProofState');
          if (stateEl) { stateEl.textContent = 'Comprobante recibido · pendiente de verificación'; stateEl.className = 'skpay__status-pill'; }
        } else {
          proofStatus((d && d.message) || 'No pudimos recibir el comprobante.', 'err');
        }
        if (btn) { btn.disabled = false; btn.textContent = label; }
      };
      xhr.onerror = function () {
        proofStatus('Error de conexión. Intenta de nuevo.', 'err');
        if (btn) { btn.disabled = false; btn.textContent = label; }
      };
      xhr.send(fd);
    });
  }

  function initQrModal() {
    var modal = document.getElementById('skqrModal');
    if (!modal) return;
    var panel = modal.querySelector('.skqr-modal__panel');
    var img = modal.querySelector('.skqr-modal__img');
    var closeBtn = modal.querySelector('.skqr-modal__close');
    var trigger = null;

    function open(from) {
      if (!modal.hidden) return;
      trigger = from || document.activeElement;
      modal.hidden = false;
      document.documentElement.classList.add('skqr-modal-open');
      // Solo QRs planos y pequenos: en tarjetas con texto el suavizado se ve mejor
      if (img && img.naturalWidth && img.naturalHeight) {
        var ratio = img.naturalWidth / img.naturalHeight;
        if (img.naturalWidth < 640 && ratio > 0.85 && ratio < 1.18) img.classList.add('is-pixelated');
      }
      if (closeBtn) closeBtn.focus();
    }

    function close() {
      if (modal.hidden) return;
      modal.hidden = true;
      document.documentElement.classList.remove('skqr-modal-open');
      if (trigger && typeof trigger.focus === 'function') trigger.focus();
      trigger = null;
    }

    document.addEventListener('click', function (e) {
      var opener = e.target.closest('[data-sk-qr-open]');
      if (opener) { e.preventDefault(); open(opener); return; }
      if (e.target.closest('[data-sk-qr-close]')) { e.preventDefault(); close(); }
    });

    document.addEventListener('keydown', function (e) {
      if (modal.hidden) return;
      if (e.key === 'Escape') { e.preventDefault(); close(); return; }
      if (e.key !== 'Tab' || !panel) return;
      var focusables = Array.prototype.filter.call(
        panel.querySelectorAll('button:not([disabled]), a[href]'),
        function (el) { return el.offsetParent !== null; }
      );
      if (!focusables.length) return;
      var first = focusables[0];
      var last = focusables[focusables.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  }

  function initCopy() {
    document.addEventListener('click', function (e) {
      var b = e.target.closest('[data-sk-copy]');
      if (!b) return;
      var text = b.getAttribute('data-sk-copy') || '';
      var done = function () {
        var old = b.textContent;
        b.textContent = 'Copiado';
        setTimeout(function () { b.textContent = old; }, 1400);
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done).catch(function () {});
      } else {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); } catch (err) {}
        ta.remove();
      }
    });
  }

  function init() {
    state.method = initialMethod();
    paintCount();
    var couponInput = document.getElementById('skpayCoupon');
    if (couponInput) couponInput.value = state.coupon;
    initCart();
    initCheckout();
    initProof();
    initQrModal();
    initCopy();
  }

  window.SonikaPayments = {
    renderCart: function () { if (isCart) refresh(); },
    refresh: refresh
  };

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
