(function () {
  'use strict';
  var KEY = 'wwi_medical_token';
  var WM = {};

  WM.base = function () {
    var b = window.WM_API_BASE || '';
    return String(b).replace(/\/+$/, '');
  };

  WM.token = function () {
    try { return localStorage.getItem(KEY) || ''; } catch (e) { return ''; }
  };

  WM.setToken = function (t) {
    try { if (t) localStorage.setItem(KEY, t); else localStorage.removeItem(KEY); } catch (e) {}
  };

  WM.el = function (tag, props, children) {
    var el = document.createElement(tag);
    props = props || {};
    Object.keys(props).forEach(function (k) {
      var v = props[k];
      if (k === 'class') el.className = v;
      else if (k === 'text') el.textContent = v;
      else if (k.indexOf('on') === 0 && typeof v === 'function') el.addEventListener(k.slice(2).toLowerCase(), v);
      else if (v === true) el.setAttribute(k, '');
      else if (v !== false && v != null) el.setAttribute(k, v);
    });
    (Array.isArray(children) ? children : (children == null ? [] : [children])).forEach(function (c) {
      if (c == null || c === false) return;
      el.append(c instanceof Node ? c : document.createTextNode(String(c)));
    });
    return el;
  };

  WM.api = function (path, opts) {
    opts = opts || {};
    var base = WM.base();
    var headers = Object.assign({}, opts.headers || {});
    if (opts.body !== undefined && !headers['content-type']) headers['content-type'] = 'application/json';
    var t = WM.token();
    if (t) headers.authorization = 'Bearer ' + t;
    return fetch(base + path, {
      method: opts.method || 'GET',
      headers: headers,
      credentials: 'include',
      body: opts.body === undefined ? undefined : JSON.stringify(opts.body)
    }).then(function (res) {
      return res.text().then(function (txt) {
        var data = null;
        try { data = txt ? JSON.parse(txt) : null; } catch (e) { data = null; }
        if (res.status === 401) WM.setToken(null);
        if (!res.ok) {
          var err = new Error((data && (data.message || data.error)) || ('Error ' + res.status));
          err.status = res.status;
          throw err;
        }
        return data;
      });
    });
  };

  WM.loginForm = function (host, onReady, onCancel) {
    var error = WM.el('p', { class: 'wm-toast' });
    var mode = 'login';
    var email = WM.el('input', { class: 'wm-input', type: 'email', placeholder: 'tu@correo.com', autocomplete: 'email' });
    var password = WM.el('input', { class: 'wm-input', type: 'password', placeholder: '••••••••', autocomplete: 'current-password' });
    var submit = WM.el('button', { class: 'wm-btn', type: 'submit' }, 'Entrar');
    var toggle = WM.el('button', { class: 'wm-btn wm-btn-ghost', type: 'button' }, 'Crear cuenta');

    toggle.addEventListener('click', function () {
      mode = mode === 'login' ? 'register' : 'login';
      submit.textContent = mode === 'login' ? 'Entrar' : 'Crear cuenta';
      toggle.textContent = mode === 'login' ? 'Crear cuenta' : 'Ya tengo cuenta';
      error.textContent = '';
    });

    var form = WM.el('form', {}, [
      WM.el('div', { class: 'wm-field' }, [WM.el('label', {}, 'Correo'), email]),
      WM.el('div', { class: 'wm-field' }, [WM.el('label', {}, 'Contrasena'), password]),
      WM.el('div', { class: 'wm-row' }, [submit, toggle]),
      error
    ]);

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      error.textContent = '';
      submit.disabled = true;
      var path = mode === 'login' ? '/api/v1/auth/login' : '/api/v1/auth/register';
      WM.api(path, { method: 'POST', body: { email: email.value, password: password.value } })
        .then(function (data) {
          WM.setToken(data.token);
          onReady();
        })
        .catch(function (err) { error.textContent = err.message; })
        .finally(function () { submit.disabled = false; });
    });

    host.replaceChildren(WM.el('div', { class: 'wm-card' }, [
      WM.el('h2', {}, 'Acceso Wontia Medical'),
      WM.el('p', { class: 'wm-muted' }, 'Inicia sesion o crea tu cuenta para continuar.'),
      form
    ]));
    if (onCancel) host.append(WM.el('p', { class: 'wm-hint' }, [WM.el('a', { href: '#', onClick: function (e) { e.preventDefault(); onCancel(); } }, 'Volver')]));
  };

  WM.ensureAuth = function (host, onReady) {
    if (WM.token()) { onReady(); return; }
    WM.loginForm(host, onReady);
  };

  window.WM = WM;
})();
