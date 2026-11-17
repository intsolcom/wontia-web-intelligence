(function () {
  'use strict';
  var CFG = window.SONIKA_QUOTE || {};
  var panel = document.getElementById('sonikaQuote');
  if (!panel) return;
  var fab = document.getElementById('sqFab');
  var fabCount = document.getElementById('sqCount');
  var itemsEl = document.getElementById('sqItems');
  var emptyEl = document.getElementById('sqEmpty');
  var summaryEl = document.getElementById('sqSummary');
  var errorEl = document.getElementById('sqError');
  var refEl = document.getElementById('sqRef');
  var checkoutBtn = document.getElementById('sqCheckout');
  var waBtn = document.getElementById('sqWa');
  var printBtn = document.getElementById('sqPrint');
  var clearBtn = document.getElementById('sqClear');
  var prepareBtn = document.getElementById('sqPrepare');
  var sheet = document.getElementById('sqSheet');
  var copyRefBtn = document.getElementById('sqCopyRef');
  var waOptions = document.getElementById('sqWaOptions');
  var waWeb = document.getElementById('sqWaWeb');
  var waApp = document.getElementById('sqWaApp');
  var waCopy = document.getElementById('sqWaCopy');
  var waPdf = document.getElementById('sqWaPdf');
  var KEY = CFG.storageKey || 'sonika_quote';
  var CARTKEY = CFG.cartKey || 'sonika_cart';
  var MKEY = 'sonika_quote_method';
  var IDKEY = 'sonika_quote_id';
  var DATEKEY = 'sonika_quote_created';
  var SCODEKEY = 'sonika_quote_server_code';
  var SURLKEY = 'sonika_quote_server_url';
  var SEXPKEY = 'sonika_quote_server_expires';
  var SCREATEDKEY = 'sonika_quote_server_created';
  var CUSTKEY = 'sonika_quote_customer';
  var PDFKEY = 'sonika_quote_pdf_url';
  var state = {
    items: read(), method: '', quote: null, busy: false, seq: 0, lastFocus: null,
    serverCode: storageGet(SCODEKEY), serverUrl: storageGet(SURLKEY),
    serverExpires: storageGet(SEXPKEY), serverCreated: storageGet(SCREATEDKEY), pdfUrl: storageGet(PDFKEY),
    customer: readCustomer(), formType: '', formStep: 1, customerBusy: false,
    savePromise: null, saveTimer: null
  };

  function storageGet(key) {
    try { return String(localStorage.getItem(key) || ''); } catch (e) { return ''; }
  }

  function storageSet(key, value) {
    try {
      if (value) localStorage.setItem(key, String(value));
      else localStorage.removeItem(key);
    } catch (e) {}
  }

  function cleanText(v) {
    return String(v == null ? '' : v).replace(/<[^>]*>/g, ' ').replace(/[\u0000-\u001f\u007f]/g, ' ').replace(/\s+/g, ' ').trim();
  }

  function readCustomer() {
    try {
      var c = JSON.parse(localStorage.getItem(CUSTKEY) || 'null');
      if (!c || (c.type !== 'natural' && c.type !== 'company')) return null;
      var check = validateCustomerData(c.type, c);
      return check.ok ? check.customer : null;
    } catch (e) { return null; }
  }

  function saveCustomer(customer) {
    state.customer = customer;
    storageSet(CUSTKEY, JSON.stringify(customer));
  }

  function removeCustomer() {
    state.customer = null;
    storageSet(CUSTKEY, '');
  }

  function foldText(v) {
    return cleanText(v).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function isSequential(value) {
    var v = String(value || '');
    if (!v) return false;
    return '01234567890123456789'.indexOf(v) !== -1 || '98765432109876543210'.indexOf(v) !== -1;
  }

  function repeatedDigits(value) {
    return /^(\d)\1+$/.test(String(value || ''));
  }

  function garbageProblem(value, label, requireVowel) {
    var norm = foldText(value);
    if (!norm) throw new Error('Completa el ' + label + '.');
    if (/(.)\1{3,}/.test(norm)) throw new Error('El ' + label + ' contiene texto repetido.');
    var badWords = ['prueba', 'test', 'testing', 'demo', 'ejemplo', 'falso', 'fake', 'asdf', 'qwerty', 'zxcv', 'nombre', 'apellido', 'empresa', 'cliente', 'usuario', 'ninguno', 'ninguna', 'n/a', 'na', 'xxx', 'xxxx', '1234', '4321', 'abcd', 'direccion', 'correo', 'telefono'];
    var words = norm.split(/\s+/);
    for (var i = 0; i < badWords.length; i++) {
      if (norm === badWords[i] || words.indexOf(badWords[i]) !== -1) throw new Error('Escribe un dato real en el ' + label + '.');
    }
    if (/qwerty|asdf|zxcv|1234|4321|9876|abcd/.test(norm)) throw new Error('El ' + label + ' no parece v\u00e1lido.');
    var letters = norm.replace(/[^a-z]/g, '');
    if (letters.length < 2) throw new Error('Escribe un dato real en el ' + label + '.');
    if (requireVowel && !/[aeiou]/.test(letters)) throw new Error('El ' + label + ' no parece v\u00e1lido.');
    if (letters.length >= 8) {
      var seen = {};
      var unique = 0;
      for (var j = 0; j < letters.length; j++) {
        if (!seen[letters.charAt(j)]) { seen[letters.charAt(j)] = 1; unique++; }
      }
      if (unique / letters.length < 0.35) throw new Error('El ' + label + ' no parece v\u00e1lido.');
    }
  }

  function nameValue(value, label, twoWords, allowDigits, requireVowel) {
    var v = cleanText(value);
    if (v.length < 2) throw new Error('Completa el ' + label + '.');
    if (v.length > (allowDigits ? 140 : 100)) throw new Error('El ' + label + ' es demasiado largo.');
    if (/https?:\/\/|www\.|@|<|>/i.test(v)) throw new Error('El ' + label + ' contiene un formato no permitido.');
    var letters = 'A-Za-z\\u00c0-\\u00d6\\u00d8-\\u00f6\\u00f8-\\u00ff';
    var pattern = allowDigits
      ? new RegExp('^[' + letters + '0-9][' + letters + '0-9\\s\'&.,-]*$')
      : new RegExp('^[' + letters + '][' + letters + '\\s\'\\/.-]*$');
    if (!pattern.test(v)) throw new Error('El ' + label + ' contiene caracteres no permitidos.');
    garbageProblem(v, label, requireVowel);
    if (twoWords && v.split(/\s+/).length < 2) throw new Error('Escribe el ' + label + ' completo.');
    return v;
  }

  function documentValue(value) {
    if (/[a-z]/i.test(String(value == null ? '' : value))) throw new Error('El n\u00famero de documento solo puede contener d\u00edgitos.');
    var v = String(value == null ? '' : value).replace(/[\s.-]+/g, '');
    if (!/^\d{5,15}$/.test(v)) throw new Error('Escribe un n\u00famero de documento v\u00e1lido, solo d\u00edgitos (5 a 15).');
    if (repeatedDigits(v) || isSequential(v) || v === '00000000') throw new Error('El n\u00famero de documento no parece v\u00e1lido.');
    return v;
  }

  function nitValue(value) {
    if (/[a-z]/i.test(String(value == null ? '' : value))) throw new Error('El NIT solo puede contener d\u00edgitos.');
    var v = String(value == null ? '' : value).replace(/[\s.]+/g, '').replace(/-/g, '');
    if (!/^\d{9,10}$/.test(v) || repeatedDigits(v) || isSequential(v)) throw new Error('Escribe un NIT v\u00e1lido de 9 o 10 d\u00edgitos.');
    var base = v.slice(0, -1);
    var check = parseInt(v.slice(-1), 10);
    var weights = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];
    var sum = 0;
    var pos = 0;
    for (var i = base.length - 1; i >= 0; i--) sum += parseInt(base.charAt(i), 10) * weights[pos++];
    var mod = sum % 11;
    var dv = mod > 1 ? 11 - mod : mod;
    if (dv !== check) throw new Error('El d\u00edgito de verificaci\u00f3n del NIT no es v\u00e1lido.');
    return v;
  }

  function emailValue(value) {
    var v = String(value == null ? '' : value).trim().toLowerCase();
    if (v.length > 190 || !/^[a-z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/i.test(v)) {
      throw new Error('Escribe un correo electr\u00f3nico v\u00e1lido.');
    }
    if (/\.\./.test(v) || /[<>(),;:\\"]/.test(v)) throw new Error('El correo contiene un formato no permitido.');
    var parts = v.split('@');
    var local = parts[0] || '';
    var domain = parts[1] || '';
    if (local.length < 3 || domain.indexOf('.') === -1) throw new Error('Escribe un correo electr\u00f3nico v\u00e1lido.');
    var tld = domain.slice(domain.lastIndexOf('.') + 1);
    if (tld.length < 2 || !/^[a-z]{2,}$/.test(tld)) throw new Error('El dominio del correo no es v\u00e1lido.');
    var disposable = ['mailinator.com', 'guerrillamail.com', '10minutemail.com', 'tempmail.com', 'temp-mail.org', 'yopmail.com', 'throwawaymail.com', 'sharklasers.com', 'getnada.com', 'maildrop.cc', 'dispostable.com', 'trashmail.com', 'fakeinbox.com', 'mailnesia.com', 'spam4.me'];
    if (disposable.indexOf(domain) !== -1 || local.indexOf('test') !== -1 || domain.indexOf('example') !== -1) {
      throw new Error('Usa un correo personal o empresarial real.');
    }
    return v;
  }

  function phoneValue(value) {
    if (/[a-z]/i.test(String(value == null ? '' : value))) throw new Error('El tel\u00e9fono solo puede contener n\u00fameros y separadores.');
    var v = digits(value);
    if (v.length === 12 && v.indexOf('57') === 0) v = v.slice(2);
    if (v.length !== 10) throw new Error('Escribe un tel\u00e9fono de 10 d\u00edgitos.');
    if (!/^(3|60)/.test(v)) throw new Error('Escribe un tel\u00e9fono de contacto v\u00e1lido.');
    if (repeatedDigits(v) || isSequential(v) || v === '3000000000') throw new Error('El tel\u00e9fono no parece v\u00e1lido.');
    return v;
  }

  function addressValue(value) {
    var v = cleanText(value);
    if (!v) return '';
    if (v.length < 5 || v.length > 200) throw new Error('La direcci\u00f3n debe tener entre 5 y 200 caracteres.');
    if (/https?:\/\/|www\.|<|>/i.test(v)) throw new Error('La direcci\u00f3n contiene un formato no permitido.');
    var letters = foldText(v).replace(/[^a-z]/g, '');
    if (letters.length < 3) throw new Error('Escribe una direcci\u00f3n v\u00e1lida.');
    garbageProblem(v, 'direcci\u00f3n', false);
    return v;
  }

  function validateCustomerData(type, input) {
    var errors = {};
    var out = { type: type };
    if (type !== 'natural' && type !== 'company') {
      return { ok: false, errors: { type: 'Selecciona Persona natural o Persona jur\u00eddica.' }, customer: out };
    }
    var attempt = function (name, fn) {
      try { out[name] = fn(); } catch (e) { errors[name] = e.message || 'Dato no v\u00e1lido.'; }
    };
    attempt('email', function () { return emailValue(input.email); });
    attempt('phone', function () { return phoneValue(input.phone); });
    attempt('address', function () { return addressValue(input.address); });
    if (type === 'natural') {
      attempt('full_name', function () { return nameValue(input.full_name || input.name, 'nombre completo', true, false, true); });
      attempt('document', function () { return documentValue(input.document); });
      out.name = out.full_name || '';
      out.display_name = out.full_name || '';
    } else {
      attempt('company_name', function () { return nameValue(input.company_name, 'raz\u00f3n social', false, true, false); });
      attempt('nit', function () { return nitValue(input.nit); });
      attempt('contact_name', function () { return nameValue(input.contact_name, 'nombre del contacto', true, false, true); });
      attempt('position', function () { return nameValue(input.position, 'cargo', false, false, false); });
      out.name = out.contact_name || '';
      out.display_name = out.company_name || '';
    }
    return { ok: Object.keys(errors).length === 0, errors: errors, customer: out };
  }

  function fieldId(type, name) {
    var ids = type === 'company'
      ? { company_name: 'sqCustCompany', nit: 'sqCustNit', contact_name: 'sqCustContact', position: 'sqCustPosition', email: 'sqCustCompanyEmail', phone: 'sqCustCompanyPhone', address: 'sqCustCompanyAddress' }
      : { full_name: 'sqCustFullName', document: 'sqCustDocument', email: 'sqCustEmail', phone: 'sqCustPhone', address: 'sqCustAddress' };
    return ids[name] || '';
  }

  function clearServerQuote() {
    state.serverCode = '';
    state.serverUrl = '';
    state.serverExpires = '';
    state.serverCreated = '';
    state.pdfUrl = '';
    storageSet(SCODEKEY, '');
    storageSet(SURLKEY, '');
    storageSet(SEXPKEY, '');
    storageSet(SCREATEDKEY, '');
    storageSet(PDFKEY, '');
  }

  function read() {
    try {
      var v = JSON.parse(localStorage.getItem(KEY) || '[]');
      if (!Array.isArray(v)) return [];
      return v.filter(function (i) { return i && i.handle; }).map(function (i) {
        var out = {
          handle: String(i.handle),
          title: String(i.title || ''),
          price: Number(i.price) || 0,
          img: String(i.img || i.image || ''),
          category: String(i.category || 'Productos').trim() || 'Productos',
          qty: Math.max(1, Math.min(99, parseInt(i.qty, 10) || 1))
        };
        if (i.rec && typeof i.rec === 'object') out.rec = i.rec;
        return out;
      });
    } catch (e) { return []; }
  }

  function write(items) {
    state.items = items;
    try { localStorage.setItem(KEY, JSON.stringify(items)); } catch (e) {}
    if (items.length && !storageGet(IDKEY)) ensureId();
    if (!items.length) {
      try { localStorage.removeItem(IDKEY); localStorage.removeItem(DATEKEY); } catch (e) {}
      clearServerQuote();
    }
    updateFab();
  }

  function ensureId() {
    var id = '';
    try { id = localStorage.getItem(IDKEY) || ''; } catch (e) {}
    if (!id) {
      var d = new Date();
      var stamp = String(d.getFullYear()).slice(-2) + String(d.getMonth() + 1).padStart(2, '0') + String(d.getDate()).padStart(2, '0');
      var rnd = Math.random().toString(36).slice(2, 6).toUpperCase();
      id = 'COT-' + stamp + '-' + rnd;
      try { localStorage.setItem(IDKEY, id); localStorage.setItem(DATEKEY, d.toISOString()); } catch (e) {}
    }
    return id;
  }

  function publicUrl() {
    if (!state.serverUrl) return '';
    var url = String(state.serverUrl);
    if (/^https?:/i.test(url)) return url;
    return location.origin + url;
  }

  function quoteRef() {
    if (state.serverCode) {
      var fmtServer = function (value) {
        var p = String(value || '').slice(0, 10).split('-');
        return p.length === 3 ? p[2] + '/' + p[1] + '/' + p[0] : '';
      };
      var parts = [state.serverCode];
      var created = fmtServer(state.serverCreated);
      var until = fmtServer(state.serverExpires);
      if (created) parts.push(created);
      if (until) parts.push('v\u00e1lida hasta ' + until);
      return parts.join(' \u00b7 ');
    }
    var id = '';
    var createdAt = '';
    try { id = localStorage.getItem(IDKEY) || ''; createdAt = localStorage.getItem(DATEKEY) || ''; } catch (e) {}
    if (!id) return '';
    var d = createdAt ? new Date(createdAt) : new Date();
    if (isNaN(d.getTime())) d = new Date();
    var days = Math.max(1, parseInt(CFG.validDays, 10) || 15);
    var untilDate = new Date(d.getTime() + days * 86400000);
    var fmt = function (x) { return String(x.getDate()).padStart(2, '0') + '/' + String(x.getMonth() + 1).padStart(2, '0') + '/' + x.getFullYear(); };
    return id + ' \u00b7 ' + fmt(d) + ' \u00b7 v\u00e1lida hasta ' + fmt(untilDate);
  }

  function digits(v) { return String(v == null ? '' : v).replace(/\D+/g, ''); }

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

  function count() {
    return state.items.reduce(function (a, i) { return a + (parseInt(i.qty, 10) || 0); }, 0);
  }

  function updateFab() {
    if (fabCount) fabCount.textContent = count() > 99 ? '99+' : String(count());
    if (fab) {
      fab.hidden = !(CFG.showFloating !== false && state.items.length > 0);
      fab.setAttribute('aria-expanded', panel.classList.contains('is-open') ? 'true' : 'false');
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

  function toast(msg) {
    var t = document.createElement('div');
    t.className = 'sq__toast';
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(function () { t.classList.add('is-on'); });
    setTimeout(function () { t.classList.remove('is-on'); setTimeout(function () { t.remove(); }, 250); }, 1600);
  }

  function add(item, qty) {
    qty = Math.max(1, Math.min(99, parseInt(qty, 10) || 1));
    var found = state.items.filter(function (i) { return i.handle === item.handle; })[0];
    if (found) found.qty = Math.max(1, Math.min(99, found.qty + qty));
    else state.items.push({
      handle: String(item.handle), title: String(item.title || ''),
      price: Number(item.price) || 0, img: String(item.img || ''),
      category: String(item.category || 'Productos').trim() || 'Productos', qty: qty
    });
    ensureId();
    write(state.items);
    open();
    toast(CFG.labels && CFG.labels.added ? CFG.labels.added : 'Agregado a la cotizaci\u00f3n');
  }

  function setQty(handle, qty) {
    qty = Math.max(1, Math.min(99, parseInt(qty, 10) || 1));
    state.items = state.items.map(function (i) { if (i.handle === handle) i.qty = qty; return i; });
    write(state.items);
    refresh();
  }

  function remove(handle) {
    state.items = state.items.filter(function (i) { return i.handle !== handle; });
    write(state.items);
    refresh();
  }

  function open() {
    state.lastFocus = document.activeElement;
    panel.hidden = false;
    document.body.classList.add('sq-lock');
    if (fab) fab.setAttribute('aria-expanded', 'true');
    requestAnimationFrame(function () { panel.classList.add('is-open'); });
    setTimeout(function () {
      var close = panel.querySelector('.sq__close');
      if (close) close.focus();
    }, 30);
    refresh();
  }

  function close() {
    closeSheet();
    var customerSheet = document.getElementById('sqCustomerSheet');
    if (customerSheet && !customerSheet.hidden) {
      customerSheet.classList.remove('is-open');
      customerSheet.hidden = true;
    }
    panel.classList.remove('is-open');
    document.body.classList.remove('sq-lock');
    if (fab) fab.setAttribute('aria-expanded', 'false');
    setTimeout(function () { panel.hidden = true; }, 230);
    if (state.lastFocus && typeof state.lastFocus.focus === 'function') {
      try { state.lastFocus.focus(); } catch (e) {}
    }
  }

  function openSheet() {
    if (!sheet || !state.quote || !state.items.length || state.busy) return;
    hideWaOptions();
    sheet.hidden = false;
    if (prepareBtn) prepareBtn.setAttribute('aria-expanded', 'true');
    setTimeout(function () {
      var first = sheet.querySelector('.sq__btn--primary:not([disabled])') || sheet.querySelector('.sq__sheet-close');
      if (first) first.focus();
    }, 20);
  }

  function closeSheet() {
    if (!sheet || sheet.hidden) return;
    hideWaOptions();
    sheet.hidden = true;
    if (prepareBtn) {
      prepareBtn.setAttribute('aria-expanded', 'false');
      try { prepareBtn.focus(); } catch (e) {}
    }
  }

  function customerReady() {
    return !!(state.customer && validateCustomerData(state.customer.type, state.customer).ok);
  }

  function updatePrepare() {
    if (!prepareBtn) return;
    prepareBtn.disabled = !state.items.length || !!state.busy || !customerReady();
  }

  function syncCustomerRadios(type) {
    var options = panel.querySelectorAll('input[name="sq_customer_type"]');
    Array.prototype.forEach.call(options, function (input) {
      input.checked = input.value === type;
      var card = input.closest('.sq__customer-option');
      if (card) card.classList.toggle('is-selected', input.checked);
    });
  }

  function renderCustomer() {
    var summary = document.getElementById('sqCustomerSummary');
    var options = document.getElementById('sqCustomerOptions');
    var stateEl = document.getElementById('sqCustomerState');
    var hint = document.getElementById('sqCustomerHint');
    if (!stateEl && !summary && !hint) return;
    var type = state.customer ? state.customer.type : state.formType;
    syncCustomerRadios(type);
    var valid = customerReady();
    if (stateEl) {
      stateEl.textContent = valid ? 'Completos' : 'Pendiente';
      stateEl.classList.toggle('is-complete', valid);
    }
    if (options) options.hidden = valid;
    if (summary) {
      if (valid) {
        var c = state.customer;
        var label = c.type === 'company' ? 'Persona jur\u00eddica' : 'Persona natural';
        var detail = c.type === 'company'
          ? (c.display_name || c.company_name || '') + ' \u00b7 NIT ' + (c.nit || '') + ' \u00b7 ' + (c.contact_name || '')
          : (c.display_name || c.full_name || '') + ' \u00b7 CC ' + (c.document || '');
        summary.innerHTML = '<div class="sq__customer-summary-row"><div><b>' + esc(label) + '</b><small>' + esc(detail) + '</small></div><div class="sq__customer-actions"><button type="button" class="sq__customer-change" id="sqCustomerChange">Cambiar tipo</button><button type="button" class="sq__customer-edit" id="sqCustomerEdit">Editar</button></div></div>';
        summary.hidden = false;
      } else {
        summary.innerHTML = '';
        summary.hidden = true;
      }
    }
    if (hint) {
      hint.hidden = valid;
      hint.classList.remove('is-error');
      hint.textContent = valid
        ? 'Datos completos. Ya puedes preparar la cotizaci\u00f3n.'
        : (type ? 'Completa el formulario para continuar.' : 'Selecciona el tipo y completa tus datos para preparar la cotizaci\u00f3n.');
    }
    updatePrepare();
  }

  function setCustomerStep(step) {
    state.formStep = step;
    var type = state.formType;
    var sections = panel.querySelectorAll('[data-customer-type][data-customer-step]');
    Array.prototype.forEach.call(sections, function (section) {
      section.hidden = section.getAttribute('data-customer-type') !== type || parseInt(section.getAttribute('data-customer-step'), 10) !== step;
    });
    var back = document.getElementById('sqCustomerBack');
    var next = document.getElementById('sqCustomerNext');
    var save = document.getElementById('sqCustomerSave');
    if (back) back.hidden = step === 1;
    if (next) next.hidden = step !== 1;
    if (save) save.hidden = step !== 2;
    var stepEl = document.getElementById('sqCustomerStep');
    var title = document.getElementById('sqCustomerTitle');
    var sub = document.getElementById('sqCustomerSub');
    var progress = document.getElementById('sqCustomerProgress');
    var bar = document.querySelector('.sq__form-progress');
    if (stepEl) stepEl.textContent = 'Paso ' + step + ' de 2';
    if (title) title.textContent = type === 'company' ? 'Persona jur\u00eddica' : 'Persona natural';
    if (sub) sub.textContent = type === 'company'
      ? (step === 1 ? 'Datos de la empresa que solicita la cotizaci\u00f3n.' : 'Datos del contacto responsable.')
      : (step === 1 ? 'Datos de la persona que solicita la cotizaci\u00f3n.' : '\u00bfC\u00f3mo te contactamos?');
    if (progress) progress.style.width = step === 1 ? '50%' : '100%';
    if (bar) bar.setAttribute('aria-valuenow', step === 1 ? '50' : '100');
    var error = document.getElementById('sqCustomerFormError');
    if (error) { error.hidden = true; error.textContent = ''; }
    setTimeout(function () {
      var visible = panel.querySelector('.sq__form-step-panel:not([hidden]) input, .sq__form-step-panel:not([hidden]) button');
      if (visible) { try { visible.focus(); } catch (e) {} }
    }, 30);
  }

  function fillCustomerForm(customer) {
    var values = customer || {};
    var fields = {
      sqCustFullName: values.full_name || values.name || '',
      sqCustDocument: values.document || '',
      sqCustEmail: values.email || '',
      sqCustPhone: values.phone || '',
      sqCustAddress: values.address || '',
      sqCustCompany: values.company_name || '',
      sqCustNit: values.nit || '',
      sqCustContact: values.contact_name || '',
      sqCustPosition: values.position || '',
      sqCustCompanyEmail: values.email || '',
      sqCustCompanyPhone: values.phone || '',
      sqCustCompanyAddress: values.address || ''
    };
    Object.keys(fields).forEach(function (id) {
      var input = document.getElementById(id);
      if (input) input.value = fields[id];
    });
    showCustomerErrors(state.formType, {});
  }

  function collectCustomerForm(type) {
    var value = function (id) { var el = document.getElementById(id); return el ? String(el.value || '').trim() : ''; };
    return type === 'company'
      ? { type: type, company_name: value('sqCustCompany'), nit: value('sqCustNit'), contact_name: value('sqCustContact'), position: value('sqCustPosition'), email: value('sqCustCompanyEmail'), phone: value('sqCustCompanyPhone'), address: value('sqCustCompanyAddress') }
      : { type: type, full_name: value('sqCustFullName'), document: value('sqCustDocument'), email: value('sqCustEmail'), phone: value('sqCustPhone'), address: value('sqCustAddress') };
  }

  function showCustomerErrors(type, errors) {
    panel.querySelectorAll('.sq__input').forEach(function (input) {
      input.classList.remove('is-invalid');
      input.removeAttribute('aria-invalid');
    });
    panel.querySelectorAll('.sq__field-error').forEach(function (el) {
      el.hidden = true;
      el.textContent = '';
    });
    var first = null;
    Object.keys(errors || {}).forEach(function (name) {
      var id = fieldId(type, name);
      if (!id) return;
      var input = document.getElementById(id);
      var message = document.getElementById(id + 'Error');
      if (input) {
        input.classList.add('is-invalid');
        input.setAttribute('aria-invalid', 'true');
        if (!first) first = input;
      }
      if (message) {
        message.textContent = errors[name];
        message.hidden = false;
      }
    });
    var formError = document.getElementById('sqCustomerFormError');
    if (formError) {
      var has = Object.keys(errors || {}).length > 0;
      formError.textContent = has ? 'Revisa los campos marcados para continuar.' : '';
      formError.hidden = !has;
    }
    if (first && state.formStep) {
      try { first.focus(); } catch (e) {}
    }
  }

  function customerStepFields(type, step) {
    if (type === 'company') return step === 1 ? ['company_name', 'nit'] : ['contact_name', 'position', 'email', 'phone', 'address'];
    return step === 1 ? ['full_name', 'document'] : ['email', 'phone', 'address'];
  }

  function validateCustomerStep(type, step) {
    var check = validateCustomerData(type, collectCustomerForm(type));
    var relevant = {};
    customerStepFields(type, step).forEach(function (name) {
      if (check.errors[name]) relevant[name] = check.errors[name];
    });
    showCustomerErrors(type, relevant);
    return Object.keys(relevant).length === 0;
  }

  function openCustomerForm(type, prefill) {
    if (!type) return;
    state.formType = type;
    state.formStep = 1;
    fillCustomerForm(prefill && state.customer && state.customer.type === type ? state.customer : null);
    syncCustomerRadios(type);
    var customerSheet = document.getElementById('sqCustomerSheet');
    if (!customerSheet) return;
    customerSheet.hidden = false;
    requestAnimationFrame(function () { customerSheet.classList.add('is-open'); });
    setCustomerStep(1);
    renderCustomer();
  }

  function closeCustomerForm() {
    var customerSheet = document.getElementById('sqCustomerSheet');
    if (!customerSheet || customerSheet.hidden) return;
    customerSheet.classList.remove('is-open');
    setTimeout(function () { customerSheet.hidden = true; }, 180);
    state.customerBusy = false;
    renderCustomer();
    if (fab) { try { fab.focus(); } catch (e) {} }
  }

  function submitCustomerForm() {
    if (state.customerBusy) return;
    var type = state.formType;
    var check = validateCustomerData(type, collectCustomerForm(type));
    if (!check.ok) {
      var stepFields = customerStepFields(type, 1);
      var onStep1 = Object.keys(check.errors).some(function (name) { return stepFields.indexOf(name) !== -1; });
      state.formStep = onStep1 ? 1 : 2;
      setCustomerStep(state.formStep);
      showCustomerErrors(type, check.errors);
      return;
    }
    var save = document.getElementById('sqCustomerSave');
    var next = document.getElementById('sqCustomerNext');
    state.customerBusy = true;
    if (save) { save.disabled = true; save.textContent = 'Validando...'; }
    if (next) next.disabled = true;
    fetch('/api/v1/public/store/quote-customer/validate', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(collectCustomerForm(type))
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d || !d.ok || !d.data || !d.data.customer) throw new Error((d && d.message) || 'No pudimos validar tus datos.');
      saveCustomer(d.data.customer);
      state.formType = d.data.customer.type;
      if (save) { save.disabled = false; save.textContent = 'Guardar datos'; }
      if (next) next.disabled = false;
      state.customerBusy = false;
      var customerSheet = document.getElementById('sqCustomerSheet');
      if (customerSheet) {
        customerSheet.classList.remove('is-open');
        setTimeout(function () { customerSheet.hidden = true; }, 180);
      }
      renderCustomer();
      renderSummary();
      toast('Datos guardados. Ya puedes preparar tu cotizaci\u00f3n.');
    }).catch(function (e) {
      state.customerBusy = false;
      if (save) { save.disabled = false; save.textContent = 'Guardar datos'; }
      if (next) next.disabled = false;
      var formError = document.getElementById('sqCustomerFormError');
      if (formError) {
        formError.textContent = (e && e.message) ? e.message : 'No pudimos validar tus datos. Intenta de nuevo.';
        formError.hidden = false;
      }
    });
  }

  function prepareQuote() {
    if (!state.quote || !state.items.length || state.busy) return;
    if (!customerReady()) {
      openCustomerForm(state.customer ? state.customer.type : state.formType, !!state.customer);
      return;
    }
    var old = prepareBtn ? prepareBtn.textContent : '';
    if (prepareBtn) {
      prepareBtn.disabled = true;
      prepareBtn.textContent = 'Guardando…';
    }
    saveQuote().then(function () {
      openSheet();
    }).catch(function (e) {
      setError((e && e.message) ? e.message : 'No se pudo guardar la cotización. El enlace público no está disponible.');
      openSheet();
    }).then(function () {
      if (prepareBtn) {
        prepareBtn.textContent = old;
        updatePrepare();
      }
    });
  }

  function quoteFor() {
    var items = state.items.map(function (i) { return { ref: i.handle, qty: parseInt(i.qty, 10) || 1 }; });
    return fetch('/api/v1/public/checkout/quote', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: items, payment_method: state.method || 'request' })
    }).then(function (r) { return r.json(); });
  }

  function refresh() {
    updateFab();
    if (!state.items.length) {
      state.quote = null;
      state.busy = false;
      renderItems();
      renderSummary();
      renderRef();
      setError('');
      return;
    }
    ensureMethod();
    var seq = ++state.seq;
    state.busy = true;
    if (checkoutBtn) checkoutBtn.disabled = true;
    if (prepareBtn) prepareBtn.disabled = true;
    setError('');
    if (!state.quote) renderLoading();
    quoteFor().then(function (d) {
      if (seq !== state.seq) return;
      state.busy = false;
      if (!d || !d.ok || !d.data) {
        state.quote = null;
        setError((d && d.message) || 'No pudimos calcular la cotizaci\u00f3n. Intenta de nuevo.');
        renderItems();
        renderSummary();
        return;
      }
      state.quote = d.data;
      renderItems();
      renderSummary();
      renderRef();
      scheduleSave();
    }).catch(function () {
      if (seq !== state.seq) return;
      state.busy = false;
      state.quote = null;
      setError('Error de conexi\u00f3n. Intenta de nuevo.');
      renderItems();
      renderSummary();
    });
  }

  function setError(msg) {
    if (!errorEl) return;
    errorEl.textContent = msg || '';
    errorEl.hidden = !msg;
  }

  function renderLoading() {
    if (itemsEl) itemsEl.innerHTML = '<div class="sq__item"><div class="sq__item-ph">\u2026</div><div><p class="sq__item-title">Calculando cotizaci\u00f3n\u2026</p><p class="sq__item-meta">Estamos validando precios y disponibilidad.</p></div></div>';
    if (emptyEl) emptyEl.hidden = true;
    if (summaryEl) summaryEl.innerHTML = '<p class="sq__summary-empty">Calculando valores\u2026</p>';
    if (checkoutBtn) checkoutBtn.disabled = true;
    if (prepareBtn) prepareBtn.disabled = true;
  }

  function renderItems() {
    if (!itemsEl) return;
    var q = state.quote;
    if (!state.items.length) {
      itemsEl.innerHTML = '';
      if (emptyEl) emptyEl.hidden = false;
      return;
    }
    if (emptyEl) emptyEl.hidden = true;
    var lines = q && q.items && q.items.length ? q.items : state.items.map(function (i) {
      return { ref: i.handle, title: i.title, image: i.img, qty: i.qty, original_unit_cents: Math.round(i.price * 100), original_line_cents: Math.round(i.price * 100) * i.qty, subtotal_after_cents: Math.round(i.price * 100) * i.qty, discount_cents: 0, available: true, issue: 'ok' };
    });
    itemsEl.innerHTML = lines.map(function (l) {
      var ref = String(l.ref || '');
      var title = l.title || ref;
      var qty = Math.max(1, parseInt(l.qty, 10) || 1);
      var orig = Number(l.original_line_cents) || 0;
      var after = Number(l.subtotal_after_cents) || orig;
      var save = Number(l.discount_cents) || 0;
      var bad = l.available === false || (l.issue && l.issue !== 'ok');
      var img = l.image ? '<img src="' + escAttr(l.image) + '" alt="" width="50" height="50" loading="lazy" />' : '<span class="sq__item-ph" aria-hidden="true">\u266a</span>';
      var price = '<span class="sq__new">' + money(after) + '</span>';
      if (save > 0) price = '<span class="sq__old">' + money(orig) + '</span>' + price;
      var       warn = bad ? '<span class="sq__item-badge">' + esc(l.issue === 'out_of_stock' ? 'Agotado' : 'No disponible') + '</span>' : '';
      return '<div class="sq__item" data-sq-line="' + escAttr(ref) + '">' + img +
        '<div><p class="sq__item-title"><a href="/producto/' + encodeURIComponent(ref) + '" title="' + escAttr(title) + '">' + esc(title) + '</a>' + warn + '</p>' +
        '<div class="sq__item-controls"><div class="sq__qty" aria-label="Cantidad"><button type="button" data-sq-qty="dec" data-ref="' + escAttr(ref) + '" aria-label="Quitar una unidad">\u2212</button><input type="number" inputmode="numeric" min="1" max="99" value="' + qty + '" data-sq-qty-input data-ref="' + escAttr(ref) + '" aria-label="Cantidad de ' + escAttr(title) + '" /><button type="button" data-sq-qty="inc" data-ref="' + escAttr(ref) + '" aria-label="Agregar una unidad">+</button></div><button type="button" class="sq__remove" data-sq-remove data-ref="' + escAttr(ref) + '">Quitar</button></div></div>' +
        '<div class="sq__item-price">' + price + '</div></div>';
    }).join('');
  }

  function renderSummary() {
    if (!summaryEl) return;
    var q = state.quote;
    if (!state.items.length) {
      summaryEl.innerHTML = '';
      if (checkoutBtn) checkoutBtn.disabled = true;
      if (waBtn) waBtn.disabled = true;
      if (prepareBtn) prepareBtn.disabled = true;
      return;
    }
    if (!q) {
      summaryEl.innerHTML = '<p class="sq__summary-empty">Calculando valores\u2026</p>';
      if (checkoutBtn) checkoutBtn.disabled = true;
      if (prepareBtn) prepareBtn.disabled = true;
      return;
    }
    var net = Number(q.net_after_cents) || 0;
    var tax = Number(q.tax_after_cents) || 0;
    var shipping = Number(q.shipping_cents) || 0;
    var total = Number(q.total_cents) || 0;
    var save = Number(q.breb_discount_cents) || 0;
    var free = !!q.free_shipping;
    var rate = Number(q.tax_rate) || 0;
    var blocked = !state.items.length || !!q.issue_count || !!state.busy;
    var canAct = state.items.length > 0 && !state.busy;
    var h = '';
    if (save > 0) h += '<div class="sq__saving"><span>Ahorro Bre-B aplicado</span><b>\u2212' + money(save) + '</b></div>';
    h += '<div class="sq__totals">';
    h += '<div class="sq__metric"><span>Antes de IVA</span><b>' + money(net) + '</b></div>';
    h += '<div class="sq__metric"><span>IVA' + (rate > 0 ? ' (' + rate + '%)' : '') + '</span><b>' + money(tax) + '</b></div>';
    h += '<div class="sq__metric"><span>Transporte</span><b class="' + (free ? 'sq__free' : '') + '">' + (free ? 'Gratis' : money(shipping)) + '</b></div>';
    h += '<div class="sq__metric sq__metric--pay"><span>Forma de pago</span><b>' + esc(methodShort()) + '</b></div>';
    h += '</div>';
    h += '<div class="sq__grand"><span>Total</span><b>' + money(total) + '</b></div>';
    h += '<div class="sq__fine">IVA incluido \u00b7 No reserva stock' + (free ? ' \u00b7 Env\u00edo gratis aplicado' : ' \u00b7 Env\u00edo gratis desde ' + money(q.free_shipping_net_cents) + ' antes de IVA') + '</div>';
    if (q.issue_count > 0) h += '<div class="sq__fine" style="color:#b42318">Revisa la disponibilidad antes de comprar.</div>';
    summaryEl.innerHTML = h;
    if (checkoutBtn) checkoutBtn.disabled = blocked;
    if (waBtn) waBtn.disabled = !canAct;
    updatePrepare();
  }

  function methodLabel() {
    var on = document.querySelector('.sq-method input[name="sq_method"]:checked');
    if (on) {
      var label = on.closest('.sq-method');
      var b = label && label.querySelector('b');
      return b ? b.textContent : on.value;
    }
    return state.method === 'breb' ? 'Bre-B' : 'Pago estimado';
  }

  function methodShort() {
    var on = document.querySelector('.sq-method input[name="sq_method"]:checked');
    var v = on ? on.value : (state.method || 'request');
    if (v === 'breb') return 'Bre-B';
    if (v === 'mercadopago') return 'Mercado Pago';
    if (v === 'epayco') return 'ePayco';
    return 'WhatsApp';
  }

  function ensureMethod() {
    var inputs = Array.prototype.slice.call(document.querySelectorAll('.sq-method input[name="sq_method"]'));
    if (!inputs.length) {
      state.method = 'request';
      return;
    }
    var saved = '';
    try { saved = localStorage.getItem(MKEY) || ''; } catch (e) {}
    var match = inputs.filter(function (i) { return i.value === saved; })[0];
    if (!match) match = inputs.filter(function (i) { return i.checked; })[0] || inputs[0];
    state.method = match.value;
    inputs.forEach(function (i) {
      i.checked = i === match;
      var card = i.closest('.sq-method');
      if (card) card.classList.toggle('is-on', i === match);
    });
  }

  function setMethod(id) {
    state.method = id;
    try { localStorage.setItem(MKEY, id); } catch (e) {}
    var inputs = document.querySelectorAll('.sq-method input[name="sq_method"]');
    inputs.forEach(function (i) {
      i.checked = i.value === id;
      var card = i.closest('.sq-method');
      if (card) card.classList.toggle('is-on', i.value === id);
    });
    refresh();
  }

  function renderRef() {
    if (!refEl) return;
    var has = state.items.length > 0;
    refEl.textContent = has ? quoteRef() : 'Cotizaci\u00f3n informativa, sin compromiso.';
    if (copyRefBtn) {
      copyRefBtn.hidden = !has;
      copyRefBtn.disabled = !has;
      copyRefBtn.textContent = state.serverUrl ? 'Copiar enlace' : 'Copiar c\u00f3digo';
      copyRefBtn.setAttribute('aria-label', state.serverUrl ? 'Copiar enlace p\u00fablico de la cotizaci\u00f3n' : 'Copiar c\u00f3digo de la cotizaci\u00f3n');
    }
  }

  function saveQuote() {
    if (!state.items.length) return Promise.reject(new Error('Sin productos'));
    if (!customerReady()) return Promise.reject(new Error('Completa tus datos para preparar la cotizaci\u00f3n.'));
    if (state.savePromise) return state.savePromise;
    var payload = {
      code: state.serverCode || '',
      payment_method: state.method || 'request',
      valid_days: parseInt(CFG.validDays, 10) || 15,
      customer: state.customer,
      items: state.items.map(function (i) {
        return { ref: i.handle, qty: parseInt(i.qty, 10) || 1, category: i.category || 'Productos' };
      })
    };
    state.savePromise = fetch('/api/v1/public/store/quotes', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d || !d.ok || !d.data || !d.data.code) throw new Error((d && d.message) || 'No se pudo guardar la cotización.');
      state.serverCode = String(d.data.code);
      state.serverUrl = String(d.data.url || ('/cotizacion/' + state.serverCode));
      state.serverExpires = String(d.data.expires_at || (d.data.quote && d.data.quote.expires_at) || '');
      state.serverCreated = String((d.data.quote && d.data.quote.created_at) || state.serverCreated || '');
      state.pdfUrl = String(d.data.pdf_url || '');
      storageSet(SCODEKEY, state.serverCode);
      storageSet(SURLKEY, state.serverUrl);
      storageSet(SEXPKEY, state.serverExpires);
      storageSet(SCREATEDKEY, state.serverCreated);
      storageSet(PDFKEY, state.pdfUrl);
      if (d.data.quote && d.data.quote.items) state.quote = d.data.quote;
      renderRef();
      return d.data;
    });
    state.savePromise.then(function () { state.savePromise = null; }, function () { state.savePromise = null; });
    return state.savePromise;
  }

  function scheduleSave() {
    if (!state.serverCode || !state.items.length || !customerReady()) return;
    clearTimeout(state.saveTimer);
    state.saveTimer = setTimeout(function () { saveQuote().catch(function () {}); }, 650);
  }

  function copyText(text) {
    var legacy = function () {
      var ta = document.createElement('textarea');
      ta.value = String(text);
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      try { document.execCommand('copy'); } catch (e) {}
      ta.remove();
      return Promise.resolve();
    };
    if (navigator.clipboard && window.isSecureContext) {
      return navigator.clipboard.writeText(String(text)).catch(legacy);
    }
    return legacy();
  }

  function openQuote() {
    if (!state.items.length) {
      open();
      return;
    }
    open();
  }

  function convert() {
    if (!state.quote || !state.items.length) return;
    var cart = [];
    try { cart = JSON.parse(localStorage.getItem(CARTKEY) || '[]') || []; } catch (e) { cart = []; }
    state.items.forEach(function (item) {
      var found = cart.filter(function (c) { return c.handle === item.handle; })[0];
      if (found) found.qty = Math.max(1, Math.min(99, (parseInt(found.qty, 10) || 1) + item.qty));
      else cart.push({ handle: item.handle, title: item.title, price: item.price, img: item.img, qty: item.qty });
    });
    try {
      localStorage.setItem(CARTKEY, JSON.stringify(cart));
      localStorage.setItem('sonika_pay_method', state.method || 'request');
      if (state.serverCode) localStorage.setItem('sonika_quote_code', state.serverCode);
      localStorage.removeItem(KEY);
      localStorage.removeItem(IDKEY);
      localStorage.removeItem(DATEKEY);
      clearServerQuote();
      window.dispatchEvent(new CustomEvent('wwi-cart-change', { detail: { items: cart } }));
    } catch (e) {}
    window.location.href = CFG.checkoutUrl || '/checkout';
  }

  function quoteMeta() {
    if (state.serverCode) {
      var serverUntil = state.serverExpires ? new Date(String(state.serverExpires).replace(' ', 'T')) : new Date();
      if (isNaN(serverUntil.getTime())) serverUntil = new Date();
      return { id: state.serverCode, until: serverUntil };
    }
    var id = ensureId();
    var created = '';
    try { created = localStorage.getItem(DATEKEY) || ''; } catch (e) {}
    var d = created ? new Date(created) : new Date();
    if (isNaN(d.getTime())) d = new Date();
    var days = Math.max(1, parseInt(CFG.validDays, 10) || 15);
    return { id: id, until: new Date(d.getTime() + days * 86400000) };
  }

  function formatLongDate(d) {
    var months = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    return d.getDate() + ' de ' + months[d.getMonth()] + ' de ' + d.getFullYear();
  }

  function categoryDisplay(category) {
    var s = String(category || '').toLowerCase();
    if (s.indexOf('acople') >= 0 || s.indexOf('tarima') >= 0 || s.indexOf('accesorio') >= 0 || s.indexOf('soporte') >= 0) return 'Accesorios para tarima';
    if (s.indexOf('controlador') >= 0 || s.indexOf('sintetiz') >= 0) return 'Controladores musicales';
    if (s.indexOf('guitarra') >= 0 || s.indexOf('bajo') >= 0 || s.indexOf('cuerda') >= 0) return 'Guitarras y cuerdas';
    if (s.indexOf('audio') >= 0 || s.indexOf('monitor') >= 0 || s.indexOf('mezcla') >= 0) return 'Audio y monitores';
    if (s.indexOf('micro') >= 0) return 'Micr\u00f3fonos';
    if (s.indexOf('ilumin') >= 0) return 'Iluminaci\u00f3n';
    return String(category || 'Productos').trim() || 'Productos';
  }

  function categoryEmoji(category) {
    var s = String(category || '').toLowerCase();
    if (s.indexOf('controlador') >= 0 || s.indexOf('sintetiz') >= 0 || s.indexOf('teclado') >= 0) return '\ud83e\udd41';
    if (s.indexOf('tarima') >= 0 || s.indexOf('accesorio') >= 0 || s.indexOf('soporte') >= 0 || s.indexOf('acople') >= 0) return '\ud83c\udf9a';
    if (s.indexOf('guitarra') >= 0 || s.indexOf('bajo') >= 0 || s.indexOf('cuerda') >= 0) return '\ud83c\udfb8';
    if (s.indexOf('bater') >= 0 || s.indexOf('percus') >= 0) return '\ud83e\udd41';
    if (s.indexOf('viento') >= 0 || s.indexOf('saxo') >= 0) return '\ud83c\udfb7';
    if (s.indexOf('acorde') >= 0) return '\ud83c\udfbc';
    if (s.indexOf('audio') >= 0 || s.indexOf('monitor') >= 0 || s.indexOf('mezcla') >= 0) return '\ud83d\udd0a';
    if (s.indexOf('micro') >= 0) return '\ud83c\udfa4';
    if (s.indexOf('ilumin') >= 0) return '\ud83d\udca1';
    if (s.indexOf('cable') >= 0) return '\ud83d\udd0c';
    return '\ud83c\udfb5';
  }

  function whatsappPhone() {
    var n = String(CFG.wa || '').replace(/\D+/g, '');
    if (n.length === 10) n = '57' + n;
    return n;
  }

  function whatsappMessage() {
    var code = state.serverCode || (state.quote && state.quote.code) || '';
    var url = publicUrl();
    var lines = ['Hola, quiero compartir mi cotización SONIKA' + (code ? ' ' + code : '') + '.'];
    if (state.items.length) {
      lines.push('');
      lines.push('Productos:');
      state.items.slice(0, 10).forEach(function (item) {
        lines.push('• ' + String(item.title || item.handle || 'Producto') + ' × ' + (parseInt(item.qty, 10) || 1));
      });
    }
    if (state.quote && state.quote.total_cents) {
      lines.push('');
      lines.push('Total: ' + money(state.quote.total_cents));
    }
    if (url) {
      lines.push('');
      lines.push('Ver cotización: ' + url);
    }
    lines.push('');
    lines.push('Quedo atento para coordinar el pago. ¡Gracias!');
    return lines.join('\n');
  }

  function hideWaOptions() {
    if (waOptions) waOptions.hidden = true;
  }

  function showWaOptions() {
    if (!waOptions) return;
    var phone = whatsappPhone();
    var text = encodeURIComponent(whatsappMessage());
    if (waWeb) waWeb.href = 'https://web.whatsapp.com/send?' + (phone ? 'phone=' + phone + '&' : '') + 'text=' + text;
    if (waApp) waApp.href = 'whatsapp://send?' + (phone ? 'phone=' + phone + '&' : '') + 'text=' + text;
    if (waPdf) waPdf.hidden = !state.pdfUrl;
    waOptions.hidden = false;
    try { waOptions.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); } catch (e) {}
  }

  function prepareWhatsApp() {
    if (!waBtn || state.whatsappBusy) return;
    state.whatsappBusy = true;
    hideWaOptions();
    waBtn.disabled = true;
    waBtn.setAttribute('aria-busy', 'true');
    waBtn.textContent = 'Generando cotización...';
    saveQuote().then(function () {
      if (sheet && !sheet.hidden) showWaOptions();
      toast('Cotización lista. Elige WhatsApp Web o la app.');
    }).catch(function (err) {
      toast((err && err.message) || 'No se pudo generar la cotización.');
    }).then(function () {
      state.whatsappBusy = false;
      waBtn.disabled = false;
      waBtn.removeAttribute('aria-busy');
      waBtn.textContent = 'Enviar por WhatsApp';
    });
  }

  function shareQuotePdf(phone, url) {
    if (!url) return Promise.resolve(false);
    return fetch(url, { credentials: 'same-origin' }).then(function (r) {
      if (!r.ok) throw new Error('PDF no disponible');
      return r.blob();
    }).then(function (blob) {
      var file = new File([blob], 'Cotizacion-' + (state.serverCode || 'SONIKA') + '.pdf', { type: 'application/pdf' });
      if (navigator.canShare && navigator.canShare({ files: [file] })) {
        return navigator.share({ files: [file], title: 'Cotización ' + (state.serverCode || 'SONIKA') });
      }
      var a = document.createElement('a');
      a.href = URL.createObjectURL(blob);
      a.download = 'Cotizacion-' + (state.serverCode || 'SONIKA') + '.pdf';
      a.click();
      setTimeout(function () { URL.revokeObjectURL(a.href); }, 3000);
      if (phone) window.open('https://wa.me/' + phone, '_blank', 'noopener');
      toast('PDF descargado. Adjúntalo en el chat de WhatsApp.');
      return true;
    }).catch(function () {
      toast('No pudimos preparar el PDF. Intenta de nuevo.');
      return false;
    });
  }

  function whatsapp() {
    var n = String(CFG.wa || '').replace(/\D+/g, '');
    if (!n) return;
    if (n.length === 10) n = '57' + n;
    if (state.pdfUrl) return shareQuotePdf(n, state.pdfUrl);
    toast('El PDF no est\u00e1 disponible. Intenta de nuevo.');
    return;
    var q = state.quote;
    var meta = quoteMeta();
    var lines = [];
    var groups = Object.create(null);
    var quoteLines = {};
    if (q && q.items) q.items.forEach(function (line) { quoteLines[line.ref] = line; });
    state.items.forEach(function (item) {
      var category = categoryDisplay(item.category);
      if (!groups[category]) groups[category] = [];
      groups[category].push(item);
    });
    lines.push('\ud83c\udfb5 *\u00a1Tu cotizaci\u00f3n SONIKA est\u00e1 lista!*');
    lines.push('');
    lines.push('\u00a1Hola! \ud83d\udc4b Gracias por elegir SONIKA. Estos son los equipos y accesorios de tu cotizaci\u00f3n:');
    if (state.customer) {
      var custName = state.customer.type === 'company'
        ? (state.customer.display_name || state.customer.company_name || '')
        : (state.customer.display_name || state.customer.full_name || '');
      if (custName) lines.push('\ud83d\udc64 Cliente: ' + custName);
    }
    lines.push('');
    lines.push('\ud83d\uded2 *DETALLE DE PRODUCTOS*');
    Object.keys(groups).forEach(function (category) {
      var group = groups[category];
      lines.push('');
      lines.push(categoryEmoji(category) + ' *' + category.toLocaleUpperCase('es-CO') + '* (' + group.length + ')');
      lines.push('');
      group.forEach(function (item) {
        var line = quoteLines[item.handle];
        var lineTotal = line ? (Number(line.subtotal_after_cents) || 0) : Math.round((Number(item.price) || 0) * 100) * (parseInt(item.qty, 10) || 1);
        lines.push('\u2022 ' + String(item.title || item.handle).toLocaleUpperCase('es-CO') + ' \u00d7 ' + item.qty + ' \u2014 ' + money(lineTotal));
      });
    });
    if (q) {
      var currency = String(q.currency || CFG.currency || 'COP');
      lines.push('');
      lines.push('\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501');
      lines.push('\ud83d\udcb0 *RESUMEN DE TU COMPRA*');
      lines.push('');
      lines.push('Subtotal antes de IVA: ' + money(q.net_after_cents));
      lines.push('IVA: ' + money(q.tax_after_cents));
      lines.push('Transporte: ' + (q.free_shipping ? 'Gratis' : money(q.shipping_cents)));
      if (Number(q.breb_discount_cents) > 0) lines.push('Ahorro Bre-B: \u2212' + money(q.breb_discount_cents));
      lines.push('');
      lines.push('\ud83d\udcb5 *TOTAL A PAGAR: ' + money(q.total_cents) + ' ' + currency + '*');
      lines.push('\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501\u2501');
    }
    lines.push('');
    if (state.method === 'breb') {
      lines.push('\ud83d\udcb3 *\u00bfQuieres pagar por Bre-B?*');
      lines.push('');
      lines.push('Consulta con nuestro equipo el beneficio disponible para pagos por Bre-B y confirma el total antes de realizar tu transferencia.');
    } else if (state.method === 'mercadopago' || state.method === 'epayco') {
      lines.push('\ud83d\udcb3 *Forma de pago estimada: ' + methodLabel() + '*');
      lines.push('');
      lines.push('Completa el pago en la pasarela y confirma con nuestro equipo.');
    } else {
      lines.push('\ud83d\udcac *Coordinemos tu pago*');
      lines.push('');
      lines.push('Escr\u00edbenos por WhatsApp y te ayudamos a completar la compra.');
    }
    lines.push('');
    lines.push('\ud83d\udcac \u00a1Estamos listos para ayudarte a completar tu compra!');
    lines.push('');
    lines.push('\ud83c\udfb6 *SONIKA \u2014 \u00a1El Sonido que te mueve!*');
    lines.push('');
    lines.push('\ud83d\udccb Cotizaci\u00f3n: ' + meta.id);
    lines.push('\ud83d\udcc5 V\u00e1lida hasta el ' + formatLongDate(meta.until) + '.');
    if (publicUrl()) lines.push('Enlace: ' + publicUrl());
    lines.push('');
    lines.push('\ud83c\udf10 ' + location.origin + '/tienda');
    lines.push('_Cotizaci\u00f3n informativa. No reserva stock. Precios y disponibilidad sujetos a confirmaci\u00f3n._');
    openWhatsApp(n, lines.join('\n'));
  }

  function downloadQuotePdf() {
    if (!state.items.length) return;
    var ready = state.pdfUrl ? Promise.resolve(state.pdfUrl) : saveQuote().then(function (data) {
      return String((data && data.pdf_url) || state.pdfUrl || '');
    });
    ready.then(function (url) {
      if (!url) throw new Error('El PDF no est\u00e1 disponible.');
      var a = document.createElement('a');
      a.href = url;
      a.download = 'Cotizacion-' + (state.serverCode || 'SONIKA') + '.pdf';
      a.rel = 'nofollow';
      a.style.display = 'none';
      document.body.appendChild(a);
      a.click();
      setTimeout(function () { a.remove(); }, 500);
      closeSheet();
      toast('Descargando PDF...');
    }).catch(function (err) {
      toast((err && err.message) || 'No se pudo preparar el PDF.');
    });
  }

  async function clear() {
    if (!state.items.length) return;
    var ok = window.SonikaDialog ? await window.SonikaDialog.confirm('\u00bfVaciar la cotizaci\u00f3n?', { title: 'Vaciar cotizaci\u00f3n', okText: 'Vaciar', danger: true }) : window.confirm('\u00bfVaciar la cotizaci\u00f3n?');
    if (!ok) return;
    closeSheet();
    state.items = [];
    state.quote = null;
    state.formType = '';
    removeCustomer();
    write([]);
    refresh();
    renderItems();
    renderSummary();
    renderCustomer();
    renderRef();
  }

  function injectButtons() {
    document.querySelectorAll('[data-add]').forEach(function (add) {
      if (add.getAttribute('data-sq-injected') === '1' || add.closest('#sonikaQuote')) return;
      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'sq-add-btn';
      btn.textContent = CFG.buttonLabel || 'Cotizar';
      btn.setAttribute('data-sq-add', add.getAttribute('data-add') || '');
      btn.setAttribute('data-sq-title', add.getAttribute('data-title') || '');
      btn.setAttribute('data-sq-price', add.getAttribute('data-price') || '0');
      btn.setAttribute('data-sq-img', add.getAttribute('data-img') || '');
      btn.setAttribute('data-sq-category', add.getAttribute('data-category') || 'Productos');
      btn.setAttribute('data-sq-qty-target', add.getAttribute('data-qty-target') || '');
      btn.setAttribute('aria-label', (CFG.buttonLabel || 'Cotizar') + (add.getAttribute('data-title') ? ': ' + add.getAttribute('data-title') : ''));
      add.setAttribute('data-sq-injected', '1');
      add.insertAdjacentElement('afterend', btn);
      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var target = btn.getAttribute('data-sq-qty-target');
        var qty = target ? parseInt((document.getElementById(target) || {}).value || '1', 10) : 1;
        addItemFromButton(btn, qty);
      });
    });
  }

  function addItemFromButton(btn, qty) {
    add({
      handle: btn.getAttribute('data-sq-add') || '',
      title: btn.getAttribute('data-sq-title') || '',
      price: parseFloat(btn.getAttribute('data-sq-price')) || 0,
      img: btn.getAttribute('data-sq-img') || '',
      category: btn.getAttribute('data-sq-category') || 'Productos'
    }, qty);
  }

  function init() {
    if (state.items.length) ensureId();
    else clearServerQuote();
    ensureMethod();
    updateFab();
    renderRef();
    renderCustomer();
    if (!state.items.length) {
      renderItems();
      renderSummary();
    } else {
      refresh();
    }
    injectButtons();
    if (document.body) {
      var timer = null;
      new MutationObserver(function () {
        clearTimeout(timer);
        timer = setTimeout(injectButtons, 80);
      }).observe(document.body, { childList: true, subtree: true });
    }
    if (fab) fab.addEventListener('click', open);
    if (prepareBtn) prepareBtn.addEventListener('click', prepareQuote);
    if (sheet) sheet.addEventListener('click', function (e) {
      if (e.target.closest('[data-sq-sheet-close]')) closeSheet();
    });
    panel.addEventListener('click', function (e) {
      if (e.target.closest('[data-sq-close]')) { close(); return; }
      if (e.target.closest('[data-sq-customer-close]')) { closeCustomerForm(); return; }
      if (e.target.closest('#sqCustomerChange')) { state.formType = ''; removeCustomer(); renderCustomer(); return; }
      if (e.target.closest('#sqCustomerEdit') && state.customer) { openCustomerForm(state.customer.type, true); return; }
      var option = e.target.closest('.sq__customer-option');
      if (option) {
        var optionInput = option.querySelector('input[name="sq_customer_type"]');
        if (optionInput) {
          state.formType = optionInput.value;
          openCustomerForm(optionInput.value, !!state.customer && state.customer.type === optionInput.value);
        }
        return;
      }
      var q = e.target.closest('[data-sq-qty]');
      if (q) {
        e.preventDefault();
        var ref = q.getAttribute('data-ref') || '';
        var found = state.items.filter(function (i) { return i.handle === ref; })[0];
        if (found) setQty(ref, found.qty + (q.getAttribute('data-sq-qty') === 'inc' ? 1 : -1));
        return;
      }
      var rm = e.target.closest('[data-sq-remove]');
      if (rm) { e.preventDefault(); remove(rm.getAttribute('data-ref') || ''); return; }
    });
    panel.addEventListener('change', function (e) {
      if (e.target.matches('.sq-method input[name="sq_method"]')) { setMethod(e.target.value); return; }
      if (e.target.matches('input[name="sq_customer_type"]')) {
        var type = e.target.value;
        if (state.customer && state.customer.type !== type) removeCustomer();
        state.formType = type;
        var customerSheetEl = document.getElementById('sqCustomerSheet');
        if (!customerSheetEl || customerSheetEl.hidden) openCustomerForm(type, !!state.customer);
        else renderCustomer();
        return;
      }
      if (e.target.matches('[data-sq-qty-input]')) {
        setQty(e.target.getAttribute('data-ref') || '', e.target.value);
      }
    });
    var customerForm = document.getElementById('sqCustomerForm');
    if (customerForm) customerForm.addEventListener('submit', function (e) {
      e.preventDefault();
      submitCustomerForm();
    });
    var customerNext = document.getElementById('sqCustomerNext');
    if (customerNext) customerNext.addEventListener('click', function () {
      var type = state.formType;
      if (!validateCustomerStep(type, 1)) return;
      setCustomerStep(2);
    });
    var customerBack = document.getElementById('sqCustomerBack');
    if (customerBack) customerBack.addEventListener('click', function () { setCustomerStep(1); });
    if (checkoutBtn) checkoutBtn.addEventListener('click', convert);
    if (waBtn) waBtn.addEventListener('click', function () {
      if (!customerReady()) {
        closeSheet();
        openCustomerForm(state.customer ? state.customer.type : state.formType, !!state.customer);
        return;
      }
      prepareWhatsApp();
    });
    if (waCopy) waCopy.addEventListener('click', function () {
      var value = publicUrl() || state.serverCode || '';
      if (!value) return;
      copyText(value).then(function () {
        toast('Enlace de la cotización copiado.');
      });
    });
    if (waPdf) waPdf.addEventListener('click', function () {
      if (state.pdfUrl) shareQuotePdf(whatsappPhone(), state.pdfUrl);
    });
    if (printBtn) printBtn.addEventListener('click', downloadQuotePdf);
    if (clearBtn) clearBtn.addEventListener('click', clear);
    if (copyRefBtn) copyRefBtn.addEventListener('click', function () {
      var done = function (value) {
        if (!value) return;
        copyText(value).then(function () {
          copyRefBtn.textContent = 'Copiado';
          setTimeout(function () {
            copyRefBtn.textContent = state.serverUrl ? 'Copiar enlace' : 'Copiar c\u00f3digo';
          }, 1400);
        });
      };
      if (state.serverUrl) { done(publicUrl()); return; }
      saveQuote().then(function () { done(publicUrl() || state.serverCode); }).catch(function () {
        var id = '';
        try { id = localStorage.getItem(IDKEY) || ''; } catch (e) {}
        done(id);
      });
    });
    document.addEventListener('keydown', function (e) {
      if (!panel.classList.contains('is-open')) return;
      var customerSheetEl = document.getElementById('sqCustomerSheet');
      if (customerSheetEl && !customerSheetEl.hidden) {
        if (e.key === 'Escape') { e.preventDefault(); closeCustomerForm(); return; }
        if (e.key !== 'Tab') return;
        var cf = customerSheetEl.querySelectorAll('button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])');
        if (!cf.length) return;
        var cfirst = cf[0], clast = cf[cf.length - 1];
        if (e.shiftKey && document.activeElement === cfirst) { e.preventDefault(); clast.focus(); }
        else if (!e.shiftKey && document.activeElement === clast) { e.preventDefault(); cfirst.focus(); }
        return;
      }
      if (sheet && !sheet.hidden) {
        if (e.key === 'Escape') { e.preventDefault(); closeSheet(); return; }
        if (e.key !== 'Tab') return;
        var sf = sheet.querySelectorAll('button:not([disabled]),a[href],input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])');
        if (!sf.length) return;
        var sfirst = sf[0], slast = sf[sf.length - 1];
        if (e.shiftKey && document.activeElement === sfirst) { e.preventDefault(); slast.focus(); }
        else if (!e.shiftKey && document.activeElement === slast) { e.preventDefault(); sfirst.focus(); }
        return;
      }
      if (e.key === 'Escape') { e.preventDefault(); close(); return; }
      if (e.key !== 'Tab') return;
      var focusable = panel.querySelectorAll('a[href],button:not([disabled]),input:not([disabled]),select,textarea,[tabindex]:not([tabindex="-1"])');
      if (!focusable.length) return;
      var first = focusable[0], last = focusable[focusable.length - 1];
      if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
      else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
  }

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
