/* ============================================================
   SONIKA — Atribución persistente de Partners
   Mantiene el código de atribución en la cookie y en los
   enlaces internos de producto, incluso si el visitante copia
   la URL desde la barra del navegador.
   ============================================================ */
(function () {
  'use strict';

  var CFG = window.SONIKA_AFF || {};
  var COOKIE = 'sonika_aff';
  var COOKIE_DAYS = Math.max(1, parseInt(CFG.cookie_days || 180, 10) || 180);
  var current = '';

  function clean(v) {
    return String(v == null ? '' : v).toLowerCase().replace(/[^a-z0-9-]/g, '').slice(0, 64);
  }

  function readCookie(name) {
    var parts = String(document.cookie || '').split(';');
    for (var i = 0; i < parts.length; i++) {
      var p = parts[i].trim();
      if (p.indexOf(name + '=') === 0) return clean(decodeURIComponent(p.slice(name.length + 1)));
    }
    return '';
  }

  function writeCookie(name, value, days) {
    if (!value) return;
    var secure = location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = name + '=' + encodeURIComponent(value) +
      '; Path=/; Max-Age=' + Math.round(days * 86400) +
      '; SameSite=Lax' + secure;
  }

  function queryRef() {
    try {
      return clean(new URLSearchParams(location.search).get('ref') || '');
    } catch (e) {
      var m = String(location.search || '').match(/[?&]ref=([^&]+)/);
      return m ? clean(decodeURIComponent(m[1])) : '';
    }
  }

  function decorate(href) {
    if (!current || !href) return href;
    try {
      var url = new URL(href, location.href);
      if (url.origin !== location.origin) return href;
      if (!/^\/producto\//.test(url.pathname)) return href;
      if (url.searchParams.get('ref')) return href;
      url.searchParams.set('ref', current);
      return url.pathname + url.search + url.hash;
    } catch (e) {
      return href;
    }
  }

  function decorateLinks(root) {
    if (!current) return;
    var scope = root && root.querySelectorAll ? root : document;
    var links = scope.querySelectorAll('a[href]');
    Array.prototype.forEach.call(links, function (a) {
      var href = a.getAttribute('href');
      if (!href || href.charAt(0) === '#') return;
      var next = decorate(href);
      if (next !== href) a.setAttribute('href', next);
    });
  }

  function keepRefInAddressBar() {
    if (!current || !/^\/producto\//.test(location.pathname)) return;
    try {
      var url = new URL(location.href);
      if (url.searchParams.get('ref') === current) return;
      url.searchParams.set('ref', current);
      history.replaceState(history.state, '', url.pathname + url.search + url.hash);
    } catch (e) {}
  }

  var fromQuery = queryRef();
  var fromCookie = clean(CFG.ref || '') || readCookie(COOKIE);
  current = fromQuery || fromCookie;

  if (current) {
    writeCookie(COOKIE, current, COOKIE_DAYS);
    keepRefInAddressBar();
  }

  function refresh() {
    current = queryRef() || clean(CFG.ref || '') || readCookie(COOKIE);
    decorateLinks(document);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', refresh);
  } else {
    refresh();
  }

  // La tienda y el carrito renderizan enlaces después de cargar.
  if (window.MutationObserver) {
    var pending = false;
    var observer = new MutationObserver(function () {
      if (pending) return;
      pending = true;
      setTimeout(function () {
        pending = false;
        decorateLinks(document);
      }, 80);
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
  }

  window.addEventListener('wwi-cart-change', refresh);
  window.addEventListener('sonika-quote', refresh);

  window.SonikaAff = {
    ref: function () { return current; },
    link: function (handle) {
      var h = String(handle || '');
      return '/producto/' + encodeURIComponent(h) + (current ? '?ref=' + encodeURIComponent(current) : '');
    },
    storeLink: function (code) {
      var c = clean(code) || current;
      return c ? '/r/' + encodeURIComponent(c) : '/tienda';
    },
    decorate: refresh
  };
})();
