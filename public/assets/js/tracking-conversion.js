/* WWI Tracking & Conversion — Meta Pixel, GA4, UTM first/last y ledger interno. */
(function () {
  'use strict';

  var cfg = window.WWI_TRACKING_CONFIG || null;
  if (!cfg || !cfg.enabled) return;
  var noRemote = !!window.WWI_TRACKING_NO_REMOTE;

  var state = {
    revoked: false,
    metaInit: false,
    gaInit: false,
    pageViewSent: false,
    viewContentSent: false,
    checkoutSent: false,
    purchaseBusy: false,
    lastCartAdd: 0
  };

  var DIST = {
    meta: {
      page_view: 'PageView',
      view_content: 'ViewContent',
      add_to_cart: 'AddToCart',
      initiate_checkout: 'InitiateCheckout',
      purchase: 'Purchase'
    },
    ga4: {
      page_view: 'page_view',
      view_content: 'view_item',
      add_to_cart: 'add_to_cart',
      initiate_checkout: 'begin_checkout',
      purchase: 'purchase'
    }
  };

  function log() {
    if (!cfg.debug) return;
    try {
      var args = Array.prototype.slice.call(arguments);
      args.unshift('[WWI Tracking]');
      console.log.apply(console, args);
    } catch (e) {}
  }

  function safeGet(store, key) {
    try { return store.getItem(key); } catch (e) { return null; }
  }

  function safeSet(store, key, value) {
    try { store.setItem(key, value); } catch (e) {}
  }

  function cookieGet(name) {
    try {
      var match = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '=([^;]*)'));
      return match ? decodeURIComponent(match[1]) : '';
    } catch (e) { return ''; }
  }

  function cookieSet(name, value, days) {
    try {
      var maxAge = Math.max(1, parseInt(days || 90, 10)) * 86400;
      var secure = location.protocol === 'https:' ? '; Secure' : '';
      document.cookie = name + '=' + encodeURIComponent(value) + '; Path=/; Max-Age=' + maxAge + '; SameSite=Lax' + secure;
    } catch (e) {}
  }

  function cookieDelete(name) {
    try {
      document.cookie = name + '=; Path=/; Max-Age=0; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    } catch (e) {}
  }

  function parseConsent(raw) {
    var out = { necessary: true, analytics: false, marketing: false };
    raw = String(raw || '').trim();
    if (!raw) return out;
    if (raw === 'all') { out.analytics = true; out.marketing = true; return out; }
    if (raw === 'necessary') return out;
    try {
      var data = JSON.parse(raw);
      if (data && typeof data === 'object') {
        out.analytics = !!data.analytics;
        out.marketing = !!data.marketing;
      }
    } catch (e) {}
    return out;
  }

  function consent() {
    var raw = safeGet(window.localStorage, 'wontia_cookie_consent');
    if (!raw) raw = cookieGet('sonika_cookie_consent');
    return parseConsent(raw);
  }

  function consentAllows(category) {
    if (!cfg.consent_required) return true;
    var c = consent();
    return !!c[category];
  }

  function consentSnapshot() {
    var c = consent();
    return (c.analytics ? 'A' : '-') + (c.marketing ? 'M' : '-');
  }

  function uid(prefix) {
    return String(prefix || 'event') + '_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
  }

  function sessionId() {
    var sid = safeGet(window.sessionStorage, 'wwi_tracking_sid');
    if (!sid) {
      sid = 't' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
      safeSet(window.sessionStorage, 'wwi_tracking_sid', sid);
    }
    return sid;
  }

  function cleanUrl(value) {
    var raw = String(value || '').trim();
    if (!raw) return '';
    try {
      var u = new URL(raw, location.href);
      return (u.origin + u.pathname).slice(0, 500);
    } catch (e) {
      return raw.split('?')[0].slice(0, 500);
    }
  }

  function readAttribution() {
    var raw = cookieGet(cfg.attribution.cookie || 'wwi_attr_v1');
    if (!raw) return { first: null, last: null };
    try {
      var data = JSON.parse(raw);
      if (!data || typeof data !== 'object') return { first: null, last: null };
      return { first: data.first || null, last: data.last || null, ts: data.ts || 0 };
    } catch (e) {
      return { first: null, last: null };
    }
  }

  function cleanTouch(touch) {
    if (!touch || typeof touch !== 'object') return null;
    var out = {};
    ['source', 'medium', 'campaign', 'content', 'term', 'fbclid', 'gclid', 'landing', 'referrer'].forEach(function (k) {
      var v = touch[k];
      if (v === undefined || v === null) return;
      v = String(v).trim();
      if (!v) return;
      out[k] = v.slice(0, k === 'fbclid' || k === 'gclid' ? 255 : 500);
    });
    out.ts = parseInt(touch.ts || Math.floor(Date.now() / 1000), 10) || Math.floor(Date.now() / 1000);
    return out;
  }

  function mergeTouch(base, incoming) {
    var out = cleanTouch(base) || {};
    var next = cleanTouch(incoming) || {};
    var hasCampaign = !!(next.source || next.medium || next.campaign || next.content || next.term);
    var hasClick = !!(next.fbclid || next.gclid);
    if (hasCampaign || hasClick) {
      if (!next.fbclid) delete out.fbclid;
      if (!next.gclid) delete out.gclid;
    }
    Object.keys(next).forEach(function (k) {
      if (k === 'ts') return;
      if (next[k] !== '') out[k] = next[k];
    });
    out.ts = next.ts || Math.floor(Date.now() / 1000);
    return out;
  }

  function captureAttribution() {
    if (!cfg.attribution || !consentAllows('analytics')) return;
    var params = new URLSearchParams(location.search || '');
    var touch = {
      source: params.get('utm_source') || '',
      medium: params.get('utm_medium') || '',
      campaign: params.get('utm_campaign') || '',
      content: params.get('utm_content') || '',
      term: params.get('utm_term') || '',
      fbclid: params.get('fbclid') || '',
      gclid: params.get('gclid') || '',
      landing: cleanUrl(location.href),
      referrer: cleanUrl(document.referrer || ''),
      ts: Math.floor(Date.now() / 1000)
    };
    var hasCampaign = !!(touch.source || touch.medium || touch.campaign || touch.content || touch.term || touch.fbclid || touch.gclid);
    var current = readAttribution();
    var retention = Math.max(1, parseInt(cfg.attribution.retention_days || 90, 10));
    var first = cleanTouch(current.first);
    var last = cleanTouch(current.last);

    if (first && first.ts && (Math.floor(Date.now() / 1000) - first.ts) > retention * 86400) {
      first = null;
      last = null;
    }
    if (!first) first = cleanTouch(touch);
    if (hasCampaign) last = last ? mergeTouch(last, touch) : cleanTouch(touch);
    if (!last) last = cleanTouch(touch);
    if (!first || !last) return;

    cookieSet(cfg.attribution.cookie || 'wwi_attr_v1', JSON.stringify({ v: 1, first: first, last: last }), retention);
  }

  function attribution() {
    return readAttribution();
  }

  function productId(item) {
    if (!item) return '';
    if (cfg.content_id_type === 'product_id') return String(item.product_id || item.productId || item.id || item.handle || '');
    if (cfg.content_id_type === 'handle') return String(item.handle || item.sku || item.id || item.product_id || '');
    return String(item.sku || item.product_id || item.productId || item.id || item.handle || '');
  }

  function normalizeItem(item) {
    if (!item) return null;
    var qty = Math.max(1, parseInt(item.qty || item.quantity || 1, 10) || 1);
    var price = 0;
    if (item.price_cents !== undefined && item.price_cents !== null) price = Number(item.price_cents) / 100;
    else price = Number(item.price || item.item_price || 0);
    if (!isFinite(price) || price < 0) price = 0;
    var id = productId(item);
    if (!id) return null;
    return {
      id: id,
      name: String(item.name || item.item_name || item.title || ''),
      price: Math.round(price * 100) / 100,
      qty: qty,
      sku: String(item.sku || ''),
      product_id: String(item.product_id || item.productId || ''),
      handle: String(item.handle || '')
    };
  }

  function normalizeItems(items) {
    var out = [];
    (Array.isArray(items) ? items : []).forEach(function (item) {
      var normalized = normalizeItem(item);
      if (normalized) out.push(normalized);
    });
    return out;
  }

  function sumItems(items) {
    var value = 0;
    var qty = 0;
    items.forEach(function (item) {
      value += Number(item.price || 0) * Number(item.qty || 1);
      qty += Number(item.qty || 1);
    });
    return { value: Math.round(value * 100) / 100, qty: qty };
  }

  function metaParams(eventName, items, extra) {
    var totals = sumItems(items);
    var params = {
      value: totals.value,
      currency: (extra && extra.currency) || cfg.currency || 'COP',
      num_items: totals.qty
    };
    if (items.length) {
      params.content_type = 'product';
      params.content_ids = items.map(function (i) { return i.id; });
      params.contents = items.map(function (i) {
        return { id: i.id, quantity: i.qty, item_price: i.price };
      });
      params.content_name = items[0].name || '';
    }
    if (eventName === 'purchase' && extra && extra.order_id) params.order_id = extra.order_id;
    return params;
  }

  function gaParams(eventName, items, extra) {
    var totals = sumItems(items);
    var params = {
      currency: (extra && extra.currency) || cfg.currency || 'COP',
      value: totals.value
    };
    if (eventName === 'page_view') {
      params.page_location = location.href;
      params.page_title = document.title || '';
      params.page_path = location.pathname + location.search;
      return params;
    }
    if (items.length) {
      params.items = items.map(function (i) {
        return {
          item_id: i.id,
          item_name: i.name,
          price: i.price,
          quantity: i.qty
        };
      });
    }
    if (eventName === 'purchase' && extra) {
      params.transaction_id = String(extra.order_number || extra.order_id || '');
    }
    return params;
  }

  function metaInit() {
    if (state.metaInit || !cfg.meta || !cfg.meta.enabled || !cfg.meta.pixel_id) return;
    if (!consentAllows('marketing')) return;
    state.metaInit = true;
    try {
      if (!window.fbq && !noRemote) {
        !function (f, b, e, v, n, t, s) {
          if (f.fbq) return;
          n = f.fbq = function () { n.callMethod ? n.callMethod.apply(n, arguments) : n.queue.push(arguments); };
          if (!f._fbq) f._fbq = n;
          n.push = n;
          n.loaded = true;
          n.version = '2.0';
          n.queue = [];
          t = b.createElement(e);
          t.async = true;
          t.src = v;
          s = b.getElementsByTagName(e)[0];
          s.parentNode.insertBefore(t, s);
        }(window, document, 'script', 'https://connect.facebook.net/en_US/fbevents.js');
      }
      window.fbq('init', cfg.meta.pixel_id);
      log('Meta Pixel inicializado', cfg.meta.pixel_id);
    } catch (e) {
      log('Meta Pixel no disponible', e);
    }
  }

  function gaInit() {
    if (state.gaInit || !cfg.ga4 || !cfg.ga4.enabled || !cfg.ga4.measurement_id) return;
    if (!consentAllows('analytics')) return;
    state.gaInit = true;
    try {
      window.dataLayer = window.dataLayer || [];
      window.gtag = window.gtag || function () { window.dataLayer.push(arguments); };
      if (!noRemote && !document.querySelector('script[src*="googletagmanager.com/gtag/js"]')) {
        var s = document.createElement('script');
        s.async = true;
        s.src = 'https://www.googletagmanager.com/gtag/js?id=' + encodeURIComponent(cfg.ga4.measurement_id);
        s.setAttribute('data-ga-loader', '1');
        document.head.appendChild(s);
      }
      window.gtag('js', new Date());
      window.gtag('config', cfg.ga4.measurement_id, {
        send_page_view: false,
        debug_mode: !!cfg.ga4.debug
      });
      log('GA4 inicializado', cfg.ga4.measurement_id);
    } catch (e) {
      log('GA4 no disponible', e);
    }
  }

  function sendMeta(eventName, items, eventId, extra) {
    if (!cfg.meta || !cfg.meta.enabled || !consentAllows('marketing')) {
      return { status: 'skipped_no_consent' };
    }
    if (!cfg.events[eventName]) return { status: 'skipped_no_consent' };
    metaInit();
    if (!state.metaInit || typeof window.fbq !== 'function') {
      return { status: 'failed', error: 'meta_not_available' };
    }
    try {
      var dest = DIST.meta[eventName];
      if (!dest) return { status: 'failed', error: 'unsupported_event' };
      var params = eventName === 'page_view' ? {} : metaParams(eventName, items, extra);
      window.fbq('track', dest, params, { eventID: eventId });
      return { status: 'dispatched' };
    } catch (e) {
      return { status: 'failed', error: String(e && e.message ? e.message : e).slice(0, 255) };
    }
  }

  function sendGa(eventName, items, eventId, extra) {
    if (!cfg.ga4 || !cfg.ga4.enabled || !consentAllows('analytics')) {
      return { status: 'skipped_no_consent' };
    }
    if (!cfg.events[eventName]) return { status: 'skipped_no_consent' };
    gaInit();
    if (!state.gaInit || typeof window.gtag !== 'function') {
      return { status: 'failed', error: 'ga4_not_available' };
    }
    try {
      var dest = DIST.ga4[eventName];
      if (!dest) return { status: 'failed', error: 'unsupported_event' };
      var params = gaParams(eventName, items, extra);
      params.event_id = eventId;
      window.gtag('event', dest, params);
      return { status: 'dispatched' };
    } catch (e) {
      return { status: 'failed', error: String(e && e.message ? e.message : e).slice(0, 255) };
    }
  }

  function eventValueCents(items) {
    return Math.round(sumItems(items).value * 100);
  }

  function logEvent(eventName, eventId, items, platforms, extra) {
    try {
      var body = {
        event_name: eventName,
        event_id: eventId,
        session_id: sessionId(),
        consent_snapshot: consentSnapshot(),
        order_uuid: extra && extra.order_uuid ? extra.order_uuid : '',
        order_number: extra && extra.order_number ? extra.order_number : '',
        params: {
          page_path: location.pathname + location.search,
          items: items,
          value: sumItems(items).value,
          value_cents: eventValueCents(items),
          currency: (extra && extra.currency) || cfg.currency,
          content_ids: items.map(function (i) { return i.id; })
        },
        value_cents: eventValueCents(items),
        currency: (extra && extra.currency) || cfg.currency,
        platforms: platforms
      };
      var url = cfg.endpoints.event;
      var json = JSON.stringify(body);
      if (navigator.sendBeacon) {
        var blob = new Blob([json], { type: 'application/json' });
        if (navigator.sendBeacon(url, blob)) return;
      }
      fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: json,
        credentials: 'same-origin',
        keepalive: true
      }).catch(function () {});
    } catch (e) {}
  }

  function track(eventName, items, options) {
    if (state.revoked) return null;
    items = normalizeItems(items || []);
    options = options || {};
    var eventId = String(options.event_id || uid(eventName));
    var extra = {
      currency: options.currency || cfg.currency,
      order_id: options.order_id || '',
      order_number: options.order_number || '',
      order_uuid: options.order_uuid || ''
    };
    var allowed = {
      meta: !!(cfg.meta && cfg.meta.enabled && cfg.events[eventName] && consentAllows('marketing')),
      ga4: !!(cfg.ga4 && cfg.ga4.enabled && cfg.events[eventName] && consentAllows('analytics'))
    };
    if (Array.isArray(options.platforms)) {
      var allowedPlatforms = {};
      options.platforms.forEach(function (p) { if (p && p.platform) allowedPlatforms[p.platform] = true; });
      allowed.meta = allowed.meta && !!allowedPlatforms.meta;
      allowed.ga4 = allowed.ga4 && !!allowedPlatforms.ga4;
    }
    if (!allowed.meta && !allowed.ga4) return null;

    var platforms = {};
    if (allowed.meta) platforms.meta = sendMeta(eventName, items, eventId, extra);
    if (allowed.ga4) platforms.ga4 = sendGa(eventName, items, eventId, extra);
    if (!platforms.meta && !platforms.ga4) return null;
    logEvent(eventName, eventId, items, platforms, extra);
    log(eventName, eventId, { items: items.length, platforms: platforms });
    return { event_id: eventId, platforms: platforms };
  }

  function pageView() {
    if (state.pageViewSent) return;
    state.pageViewSent = true;
    track('page_view', [], {});
  }

  function viewContent() {
    if (state.viewContentSent) return;
    if (location.pathname.indexOf('/producto/') !== 0) return;
    var product = window.SONIKA_PRODUCT || null;
    if (!product) {
      var button = document.querySelector('[data-pdp-add]');
      if (button) {
        product = {
          id: button.getAttribute('data-product-id') || '',
          sku: button.getAttribute('data-sku') || '',
          handle: button.getAttribute('data-add') || '',
          title: button.getAttribute('data-title') || document.title,
          price: parseFloat(button.getAttribute('data-price') || '0') || 0,
          category: button.getAttribute('data-category') || ''
        };
      }
    }
    if (!product) return;
    var item = normalizeItem({
      id: product.id || product.sku || product.handle,
      sku: product.sku,
      product_id: product.id,
      handle: product.handle,
      name: product.title || product.name,
      price: product.price,
      qty: 1
    });
    if (!item) return;
    state.viewContentSent = true;
    track('view_content', [item], { currency: product.currency || cfg.currency });
  }

  function cartItems() {
    try {
      var items = JSON.parse(localStorage.getItem('sonika_cart') || '[]');
      return normalizeItems(Array.isArray(items) ? items : []);
    } catch (e) { return []; }
  }

  function checkout() {
    if (state.checkoutSent) return;
    if (location.pathname !== '/checkout' && location.pathname.indexOf('/checkout/') !== 0) return;
    var items = cartItems();
    if (!items.length) return;
    var signature = items.map(function (i) { return i.id + ':' + i.qty; }).sort().join('|');
    var key = 'wwi_ic_' + signature;
    if (safeGet(window.sessionStorage, key)) return;
    state.checkoutSent = true;
    safeSet(window.sessionStorage, key, '1');
    track('initiate_checkout', items, {});
  }

  function purchase() {
    if (state.purchaseBusy) return;
    var order = window.SONIKA_PURCHASE;
    if (!order || order.status !== 'paid' || !order.uuid) return;
    if (!consentAllows('marketing') && !consentAllows('analytics')) return;
    state.purchaseBusy = true;
    fetch(cfg.endpoints.purchase + encodeURIComponent(order.uuid), {
      method: 'GET',
      credentials: 'same-origin',
      cache: 'no-store'
    }).then(function (r) { return r.json(); }).then(function (res) {
      var data = res && res.ok ? res.data : null;
      if (!data || !data.platforms || !data.platforms.length) {
        log('Purchase sin payload listo', order.uuid);
        return;
      }
      var items = normalizeItems(data.items || []);
      if (!items.length) return;
      track('purchase', items, {
        event_id: data.event_id,
        platforms: data.platforms,
        order_uuid: data.order_uuid,
        order_number: data.order_number,
        currency: data.currency,
        order_id: data.order_uuid
      });
    }).catch(function (e) {
      log('No se pudo consultar Purchase', e);
    }).then(function () {
      state.purchaseBusy = false;
    });
  }

  function refresh() {
    state.revoked = false;
    if (consentAllows('analytics')) captureAttribution();
    else cookieDelete(cfg.attribution.cookie || 'wwi_attr_v1');
    if (consentAllows('marketing')) metaInit();
    if (consentAllows('analytics')) gaInit();
    pageView();
    viewContent();
    checkout();
    purchase();
  }

  function revoke() {
    state.revoked = true;
    state.metaInit = false;
    state.gaInit = false;
    state.pageViewSent = false;
    state.viewContentSent = false;
    state.checkoutSent = false;
    cookieDelete(cfg.attribution.cookie || 'wwi_attr_v1');
    log('Consentimiento retirado; tracking detenido');
  }

  function onCartAdd(ev) {
    if (!consentAllows('marketing') && !consentAllows('analytics')) return;
    var now = Date.now();
    if (now - state.lastCartAdd < 250) return;
    state.lastCartAdd = now;
    var d = (ev && ev.detail) || {};
    var item = normalizeItem({
      id: d.product_id || d.sku || d.handle,
      sku: d.sku,
      product_id: d.product_id,
      handle: d.handle,
      name: d.title,
      price: d.price,
      qty: d.qty || 1
    });
    if (!item) return;
    track('add_to_cart', [item], {});
  }

  window.WWITracking = {
    refresh: refresh,
    revoke: revoke,
    track: track,
    attribution: attribution,
    consent: consent
  };

  window.addEventListener('sonika:cart-add', onCartAdd);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refresh);
  } else {
    refresh();
  }
})();
