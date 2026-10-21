/* WWI SEO Tracking — visitas, links, CTA, formularios (envíos efectivos), tiempo y scroll. */
(function () {
  var API = '/api/v1/public/seo/track';
  var sid = sessionStorage.getItem('wwi_seo_sid');
  if (!sid) { sid = 's' + Date.now().toString(36) + Math.random().toString(36).slice(2, 10); sessionStorage.setItem('wwi_seo_sid', sid); }
  var page = location.pathname + location.search;
  var q = new URLSearchParams(location.search);
  var utm = {};
  ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'].forEach(function (k) { if (q.get(k)) utm[k] = q.get(k); });
  var start = Date.now(), maxScroll = 0, sent = false, buf = [];
  function dev() { var w = window.innerWidth; return w < 600 ? 'mobile' : (w < 1024 ? 'tablet' : 'desktop'); }
  function push(e) { buf.push(e); if (buf.length > 30) flush(false); }
  function flush(beacon) {
    if (!buf.length) return;
    var payload = JSON.stringify({ events: buf });
    buf = [];
    try {
      if (beacon && navigator.sendBeacon) { navigator.sendBeacon(API, new Blob([payload], { type: 'application/json' })); return; }
      fetch(API, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: payload, keepalive: true }).catch(function () {});
    } catch (e) {}
  }
  push({ type: 'page_view', session_id: sid, page_url: page, referrer: document.referrer || '', utm: utm, device: dev() });
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
    flush(true);
  }, { capture: true });
  function leave() {
    if (sent) return; sent = true;
    push({ type: 'page_leave', session_id: sid, page_url: page, duration_ms: Date.now() - start, scroll_pct: maxScroll, device: dev() });
    flush(true);
  }
  document.addEventListener('visibilitychange', function () { if (document.visibilityState === 'hidden') leave(); });
  window.addEventListener('pagehide', leave);
  setInterval(function () { flush(false); }, 15000);
  flush(false);
})();
