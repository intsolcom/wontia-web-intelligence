/* WWI SEO Tracking — visitas, links, CTA, formularios (envíos efectivos), tiempo y scroll. */
(function () {
  var API = '/api/v1/public/seo/track';
  function consentCookie(value) {
    document.cookie = 'sonika_cookie_consent=' + encodeURIComponent(value) + '; Path=/; Max-Age=15552000; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  }
  function analyticsAllowed() {
    try {
      var raw = localStorage.getItem('wontia_cookie_consent');
      if (!raw) return false;
      if (raw === 'all') { consentCookie('all'); return true; }
      var parsed = JSON.parse(raw);
      if (parsed && parsed.analytics) { consentCookie(raw); return true; }
      return false;
    } catch (e) { return false; }
  }
  if (!analyticsAllowed()) return;
  var sid = sessionStorage.getItem('wwi_seo_sid');
  if (!sid) {
    try {
      var cm = document.cookie.match(/(?:^|; )wwi_seo_sid=([^;]+)/);
      if (cm) sid = decodeURIComponent(cm[1]);
    } catch (e) {}
    if (!sid) sid = 's' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10);
    sessionStorage.setItem('wwi_seo_sid', sid);
  }
  try {
    document.cookie = 'wwi_seo_sid=' + encodeURIComponent(sid) + '; Path=/; Max-Age=1800; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
  } catch (e) {}
  var page = location.pathname + location.search;
  var q = new URLSearchParams(location.search);
  var utm = {};
  ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(function (k) { if (q.get(k)) utm[k] = q.get(k); });
  var fbclid = q.get('fbclid') || '';
  var gclid = q.get('gclid') || '';
  var stored = null;
  try { stored = JSON.parse(sessionStorage.getItem('wwi_seo_utm') || 'null'); } catch (e) {}
  if (Object.keys(utm).length || fbclid || gclid) {
    var landing = (stored && stored.landing) ? stored.landing : location.href;
    var mergedUtm = Object.keys(utm).length ? utm : ((stored && stored.utm) ? stored.utm : {});
    var attribution = { utm: mergedUtm, fbclid: fbclid, gclid: gclid, landing: landing };
    try { sessionStorage.setItem('wwi_seo_utm', JSON.stringify(attribution)); } catch (e) {}
    stored = attribution;
    utm = mergedUtm;
  }
  if (stored) {
    if (!Object.keys(utm).length && stored.utm) utm = stored.utm;
    if (!fbclid && stored.fbclid) fbclid = stored.fbclid;
    if (!gclid && stored.gclid) gclid = stored.gclid;
    if (!utm.landing) utm.landing = stored.landing || location.href;
  } else {
    utm.landing = location.href;
  }
  if (fbclid) utm.fbclid = fbclid;
  if (gclid) utm.gclid = gclid;
  var start = Date.now(), maxScroll = 0, sent = false, checkoutSubmitted = false, buf = [];
  function dev() { var w = window.innerWidth; return w < 600 ? 'mobile' : (w < 1024 ? 'tablet' : 'desktop'); }
  function cartItems() {
    try {
      var items = JSON.parse(localStorage.getItem('sonika_cart') || '[]');
      if (!Array.isArray(items)) return [];
      return items.slice(0, 50).map(function (it) {
        return {
          handle: String(it.handle || it.ref || ''),
          title: String(it.title || it.name || ''),
          price: Number(it.price) || 0,
          qty: Math.max(1, parseInt(it.qty || 1, 10) || 1),
          sku: String(it.sku || ''),
          product_id: String(it.product_id || ''),
          img: String(it.img || it.image || '')
        };
      }).filter(function (it) { return it.handle !== '' || it.title !== ''; });
    } catch (e) { return []; }
  }
  function push(e) {
    if (!e.utm) e.utm = utm;
    if (!e.fbclid && fbclid) e.fbclid = fbclid;
    if (!e.gclid && gclid) e.gclid = gclid;
    if (!e.landing_url) e.landing_url = utm.landing || location.href;
    if (['cart_add', 'cart_snapshot', 'checkout_start', 'checkout_abandon'].indexOf(e.type) !== -1 && !e.cart_items) e.cart_items = cartItems();
    buf.push(e);
    if (buf.length > 30) flush(false);
  }
  function flush(beacon) {
    if (!buf.length) return;
    var payload = JSON.stringify({ events: buf });
    buf = [];
    try {
      if (beacon && navigator.sendBeacon) { navigator.sendBeacon(API, new Blob([payload], { type: 'application/json' })); return; }
      fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: payload, keepalive: true }).catch(function () {});
    } catch (e) {}
  }
  function tiaOutcome(type, target, value, order) {
    var uid = '';
    try { uid = sessionStorage.getItem('wwi_tia_interaction') || ''; } catch (e) {}
    if (!uid) return;
    try {
      fetch('/api/v1/public/tia/event', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ interaction_id: uid, event: type, target: target || '', value_cents: value || 0, order_uuid: order || '' }),
        keepalive: true
      }).catch(function () {});
    } catch (e) {}
  }
  push({ type: 'page_view', session_id: sid, page_url: page, referrer: document.referrer || '', utm: utm, device: dev() });
  if (location.pathname.indexOf('/producto/') === 0) push({ type: 'product_view', session_id: sid, page_url: page, target: location.pathname, label: document.title || '', device: dev() });
  if (window.SONIKA_PURCHASE && window.SONIKA_PURCHASE.status === 'paid') {
    var purchaseKey = 'wwi_purchase_' + (window.SONIKA_PURCHASE.uuid || '');
    try {
      if (!sessionStorage.getItem(purchaseKey)) {
        push({ type: 'purchase', session_id: sid, page_url: page, target: window.SONIKA_PURCHASE.order_number || '', label: String(window.SONIKA_PURCHASE.value_cents || 0), utm: utm, device: dev() });
        tiaOutcome('purchase', window.SONIKA_PURCHASE.order_number || '', parseInt(window.SONIKA_PURCHASE.value_cents || 0, 10), window.SONIKA_PURCHASE.uuid || '');
        sessionStorage.setItem(purchaseKey, '1');
      }
    } catch (e) {}
  }
  window.addEventListener('sonika:cart-add', function (ev) {
    var d = ev.detail || {};
    push({ type: 'cart_add', session_id: sid, page_url: page, target: d.handle || '', label: d.title || '', utm: utm, device: dev() });
    tiaOutcome('add_to_cart', d.handle || '', 0, '');
    flush(false);
  });
  window.addEventListener('scroll', function () {
    var h = document.documentElement;
    var p = h.scrollTop / (h.scrollHeight - h.clientHeight || 1) * 100;
    if (p > maxScroll) maxScroll = Math.min(100, Math.round(p));
  }, { passive: true });
  document.addEventListener('click', function (ev) {
    var t = ev.target;
    var a = t && t.closest ? t.closest('a') : null;
    var cta = t && t.closest ? t.closest('[data-cta],[data-track],.cta,.btn') : null;
    if (a && a.href) {
      push({ type: 'link_click', session_id: sid, page_url: page, target: a.href, label: (a.getAttribute('aria-label') || a.textContent || '').trim().slice(0, 120), device: dev() });
      if (a.hostname && a.hostname !== location.hostname) push({ type: 'outbound', session_id: sid, page_url: page, target: a.href, device: dev() });
    }
    if (cta) {
      push({ type: 'cta_click', session_id: sid, page_url: page, target: cta.tagName === 'A' ? cta.href : '', label: (cta.getAttribute('data-track') || cta.getAttribute('aria-label') || cta.textContent || 'cta').trim().slice(0, 120), device: dev() });
    }
    flush(false);
  }, { capture: true });
  document.addEventListener('submit', function (ev) {
    var f = ev.target;
    push({ type: 'form_submit', session_id: sid, page_url: page, label: (f.id || f.getAttribute('name') || f.getAttribute('action') || 'form').slice(0, 120), target: f.getAttribute('action') || '', device: dev() });
    if (location.pathname === '/checkout') { checkoutSubmitted = true; push({ type: 'checkout_start', session_id: sid, page_url: page, target: f.id || 'checkout', device: dev() }); tiaOutcome('checkout_start', 'checkout', 0, ''); }
    flush(true);
  }, { capture: true });
  function guestCart() {
    if (window.SONIKA_PURCHASE) return null;
    try {
      var items = JSON.parse(localStorage.getItem('sonika_cart') || '[]');
      if (!Array.isArray(items) || !items.length) return null;
      var qty = 0, value = 0;
      items.forEach(function (it) { var n = Math.max(1, parseInt(it.qty || 1, 10)); qty += n; value += Math.round((parseFloat(it.price) || 0) * n * 100); });
      if (!qty || !value) return null;
      var sig = qty + ':' + value;
      try { if (sessionStorage.getItem('wwi_cart_sig') === sig) return null; } catch (e) {}
      return { type: 'cart_snapshot', session_id: sid, page_url: page, target: JSON.stringify({ qty: qty, value_cents: value }), label: 'guest_cart', utm: utm, device: dev() };
    } catch (e) { return null; }
  }
  function leave() {
    if (sent) return; sent = true;
    var snapshot = guestCart();
    if (snapshot) {
      push(snapshot);
      try { var d = JSON.parse(snapshot.target); sessionStorage.setItem('wwi_cart_sig', d.qty + ':' + d.value_cents); } catch (e) {}
      if (location.pathname === '/checkout' && !checkoutSubmitted) push({ type: 'checkout_abandon', session_id: sid, page_url: page, target: 'checkout', label: snapshot.target, utm: utm, device: dev() });
    }
    push({ type: 'page_leave', session_id: sid, page_url: page, duration_ms: Date.now() - start, scroll_pct: maxScroll, device: dev() });
    flush(true);
  }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') leave(); });
  window.addEventListener('pagehide', leave);
  setInterval(function () { flush(false); }, 15000);
  flush(false);
})();
