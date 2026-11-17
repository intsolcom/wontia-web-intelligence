(function () {
  'use strict';
  var CFG = window.SONIKA_RECS || {};
  var mount = document.getElementById('skpayRecommendations');
  if (!mount || !CFG.enabled) return;

  var cartKey = CFG.cartKey || 'sonika_cart';
  var dismissedKey = 'sonika_recs_dismissed';
  var state = { items: [], added: {}, expanded: false, busy: false };

  function esc(value) {
    var d = document.createElement('div');
    d.textContent = value == null ? '' : String(value);
    return d.innerHTML;
  }

  function money(cents) {
    var n = (Number(cents) || 0) / 100;
    try {
      return new Intl.NumberFormat('es-CO', {
        style: 'currency',
        currency: CFG.currency || 'COP',
        minimumFractionDigits: (CFG.currency || 'COP') === 'COP' ? 0 : 2,
        maximumFractionDigits: (CFG.currency || 'COP') === 'COP' ? 0 : 2
      }).format(n);
    } catch (e) {
      return '$' + Math.round(n).toLocaleString('es-CO');
    }
  }

  function readCart() {
    try {
      var value = JSON.parse(localStorage.getItem(cartKey) || '[]');
      return Array.isArray(value) ? value.filter(function (i) { return i && (i.handle || i.ref); }) : [];
    } catch (e) {
      return [];
    }
  }

  function writeCart(items) {
    try { localStorage.setItem(cartKey, JSON.stringify(items)); } catch (e) {}
    try { window.dispatchEvent(new CustomEvent('wwi-cart-change', { detail: { items: items } })); } catch (e) {}
    try { if (window.SonikaPayments && window.SonikaPayments.refresh) window.SonikaPayments.refresh(); } catch (e) {}
  }

  function addedSet() {
    var out = {};
    readCart().forEach(function (item) {
      var handle = String(item.handle || item.ref || '');
      if (handle) out[handle] = true;
    });
    return out;
  }

  function cartItems() {
    return readCart().map(function (item) {
      return {
        ref: String(item.handle || item.ref || ''),
        title: String(item.title || ''),
        qty: Math.max(1, parseInt(item.qty, 10) || 1)
      };
    });
  }

  function viewHistory() {
    try {
      var history = JSON.parse(localStorage.getItem('sonika_view_history') || '{}');
      return {
        products: Array.isArray(history.products) ? history.products.slice(0, 30) : [],
        categories: Array.isArray(history.categories) ? history.categories.slice(0, 10) : [],
        searches: Array.isArray(history.searches) ? history.searches.slice(0, 10) : []
      };
    } catch (e) {
      return { products: [], categories: [], searches: [] };
    }
  }

  function setVisible(visible) {
    mount.hidden = !visible;
    var section = document.getElementById('skpayRecommendationsSection');
    if (section) section.hidden = !visible;
  }

  function dismiss() {
    try { sessionStorage.setItem(dismissedKey, '1'); } catch (e) {}
    mount.innerHTML = '';
    setVisible(false);
  }

  function render() {
    if (!state.items.length) {
      mount.innerHTML = '';
      setVisible(false);
      return;
    }
    setVisible(true);
    var max = Math.max(1, Number(CFG.max) || 3);
    var visible = state.expanded ? state.items.length : Math.min(max, state.items.length);
    var cards = state.items.slice(0, visible).map(function (item) {
      var added = !!state.added[item.handle];
      var image = item.image
        ? '<img src="' + esc(item.image) + '" alt="" loading="lazy" />'
        : '<span class="skrec__placeholder" aria-hidden="true"></span>';
      var reason = CFG.show_reasons && item.reason
        ? '<p class="skrec__reason">' + esc(item.reason) + '</p>'
        : '';
      var label = item.label ? '<span class="skrec__tag">' + esc(item.label) + '</span>' : '';
      return '<article class="skrec__card" data-handle="' + esc(item.handle) + '">'
        + '<a class="skrec__image" href="' + esc(item.url) + '">' + image + '</a>'
        + '<div class="skrec__body">'
        + label
        + '<a class="skrec__title" href="' + esc(item.url) + '">' + esc(item.title) + '</a>'
        + '<div class="skrec__price">' + money(item.price_cents) + '</div>'
        + reason
        + '<button type="button" class="skrec__add' + (added ? ' is-added' : '') + '" data-skrec-add="' + esc(item.handle) + '"' + (added ? ' disabled' : '') + '>'
        + (added ? 'Agregado' : 'Agregar') + '</button>'
        + '</div></article>';
    }).join('');

    mount.innerHTML = '<section class="skrec" aria-labelledby="skrecTitle">'
      + '<div class="skrec__head">'
      + '<div><span class="skrec__eyebrow">Recomendados para tu compra</span><h2 id="skrecTitle">' + esc(CFG.title || 'Completa tu sonido') + '</h2>'
      + '<p class="skrec__sub">' + esc(CFG.subtitle || 'Productos que combinan con tu compra') + '</p></div>'
      + '<button type="button" class="skrec__dismiss" data-skrec-dismiss aria-label="Ocultar recomendaciones">No, gracias</button>'
      + '</div>'
      + '<div class="skrec__benefit">APROVECHA Y OBT&Eacute;N UN AHORRO POR COMPRA ADICIONAL !</div>'
      + '<div class="skrec__slider">'
      + '<div class="skrec__grid" data-skrec-track>' + cards + '</div>'
      + '<div class="skrec__nav"><button type="button" class="skrec__arrow" data-skrec-prev aria-label="Ver recomendados anteriores">&#8249;</button><button type="button" class="skrec__arrow" data-skrec-next aria-label="Ver más recomendados">&#8250;</button></div>'
      + '</div>'
      + '<div class="skrec__foot">'
      + '<span class="skrec__count">' + state.items.length + ' sugerencia' + (state.items.length === 1 ? '' : 's') + '</span>'
      + (state.items.length > max ? '<button type="button" class="skrec__more" data-skrec-more>' + (state.expanded ? 'Ver menos' : 'Ver ' + state.items.length + ' recomendaciones') + '</button>' : '')
      + (state.items.length > 1 ? '<button type="button" class="skrec__add-all" data-skrec-add-all>Agregar los ' + Math.min(max, state.items.length) + '</button>' : '')
      + '<div class="skrec__savings" data-skrec-savings-wrap><span>Ahorro total recomendados</span><b data-skrec-savings>$0</b></div>'
      + '</div></section>';
  }

  function load() {
    if (sessionStorage.getItem(dismissedKey) === '1') return;
    var cart = cartItems();
    if (!cart.length) { setVisible(false); return; }
    fetch(CFG.endpoint || '/api/v1/public/store/recommendations', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ items: cart, limit: Number(CFG.max_more) || 6, history: viewHistory() })
    }).then(function (r) { return r.json(); }).then(function (d) {
      if (!d || !d.ok || !d.data || !d.data.enabled) { setVisible(false); return; }
      state.items = Array.isArray(d.data.items) ? d.data.items : [];
      state.added = addedSet();
      render();
    }).catch(function () { setVisible(false); });
  }

  function addItem(handle) {
    var item = state.items.filter(function (i) { return i.handle === handle; })[0];
    if (!item || state.added[handle]) return;
    var cart = readCart();
    var found = cart.filter(function (i) { return String(i.handle || i.ref) === handle; })[0];
    if (found) {
      found.qty = Math.max(1, Math.min(99, (parseInt(found.qty, 10) || 1) + 1));
    } else {
      cart.push({
        handle: handle,
        title: item.title,
        price: (Number(item.price_cents) || 0) / 100,
        img: item.image || '',
        category: item.label || 'Productos',
        qty: 1,
        rec: {
          source: 'recommendations',
          campaign: 'cart-cross-sell',
          slot: item.slot || '',
          reason: item.reason || ''
        }
      });
    }
    state.added[handle] = true;
    writeCart(cart);
    render();
  }

  function addAll() {
    if (state.busy) return;
    state.busy = true;
    var items = state.items.slice(0, Math.max(1, Number(CFG.max) || 3));
    var cart = readCart();
    items.forEach(function (item) {
      var found = cart.filter(function (i) { return String(i.handle || i.ref) === item.handle; })[0];
      if (found) {
        found.qty = Math.max(1, Math.min(99, (parseInt(found.qty, 10) || 1) + 1));
      } else {
        cart.push({
          handle: item.handle, title: item.title, price: (Number(item.price_cents) || 0) / 100,
          img: item.image || '', category: item.label || 'Productos', qty: 1,
          rec: { source: 'recommendations', campaign: 'cart-cross-sell', slot: item.slot || '', reason: item.reason || '' }
        });
      }
      state.added[item.handle] = true;
    });
    writeCart(cart);
    state.busy = false;
    render();
  }

  function slide(direction) {
    var track = mount.querySelector('[data-skrec-track]');
    if (!track) return;
    var card = track.querySelector('.skrec__card');
    var gap = 10;
    var step = card ? card.getBoundingClientRect().width + gap : 240;
    track.scrollBy({ left: direction * step, behavior: 'smooth' });
  }

  mount.addEventListener('click', function (e) {
    if (e.target.closest('[data-skrec-dismiss]')) { dismiss(); return; }
    if (e.target.closest('[data-skrec-more]')) { state.expanded = !state.expanded; render(); return; }
    if (e.target.closest('[data-skrec-prev]')) { slide(-1); return; }
    if (e.target.closest('[data-skrec-next]')) { slide(1); return; }
    var add = e.target.closest('[data-skrec-add]');
    if (add) { addItem(add.getAttribute('data-skrec-add') || ''); return; }
    if (e.target.closest('[data-skrec-add-all]')) addAll();
  });

  window.addEventListener('sonika-quote', function (e) {
    var quote = e && e.detail && e.detail.quote;
    var value = Number(quote && quote.recommendation_discount_cents) || 0;
    var wrap = mount.querySelector('[data-skrec-savings-wrap]');
    var amount = mount.querySelector('[data-skrec-savings]');
    if (amount) amount.textContent = money(value);
    if (wrap) wrap.classList.toggle('is-zero', value <= 0);
  });
  window.addEventListener('storage', function (e) { if (e.key === cartKey) load(); });

  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', load);
  else load();
})();
