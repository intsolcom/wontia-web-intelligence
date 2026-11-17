(function () {
  'use strict';
  if (window.SonikaDialog) return;
  var active = null;

  function esc(value) {
    var d = document.createElement('div');
    d.textContent = value == null ? '' : String(value);
    return d.innerHTML;
  }

  function close(value) {
    if (!active) return;
    var current = active;
    active = null;
    document.removeEventListener('keydown', current.onKey, true);
    current.root.classList.add('is-closing');
    setTimeout(function () { current.root.remove(); }, 160);
    current.resolve(value);
  }

  function open(options) {
    options = options || {};
    if (active) close(null);
    return new Promise(function (resolve) {
      var root = document.createElement('div');
      root.className = 'skdlg' + (options.danger ? ' skdlg--danger' : '');
      root.innerHTML = '<div class="skdlg__backdrop"></div>'
        + '<div class="skdlg__panel" role="dialog" aria-modal="true" aria-labelledby="skdlgTitle">'
        + '<div class="skdlg__brand"><span class="skdlg__mark">S</span><span>SONIKA<small>El Sonido que te mueve</small></span></div>'
        + '<button type="button" class="skdlg__close" aria-label="Cerrar">&#215;</button>'
        + '<h2 class="skdlg__title" id="skdlgTitle">' + esc(options.title || 'SONIKA') + '</h2>'
        + '<p class="skdlg__message">' + esc(options.message || '') + '</p>'
        + '<input class="skdlg__input" type="' + esc(options.inputType || 'text') + '" value="' + esc(options.defaultValue || '') + '" />'
        + '<div class="skdlg__actions">'
        + '<button type="button" class="skdlg__btn skdlg__btn--cancel">' + esc(options.cancelText || 'Cancelar') + '</button>'
        + '<button type="button" class="skdlg__btn skdlg__btn--ok">' + esc(options.okText || (options.type === 'alert' ? 'Entendido' : (options.danger ? 'Eliminar' : 'Aceptar'))) + '</button>'
        + '</div></div>';
      document.body.appendChild(root);
      var input = root.querySelector('.skdlg__input');
      var cancel = root.querySelector('.skdlg__btn--cancel');
      var ok = root.querySelector('.skdlg__btn--ok');
      if (options.type !== 'prompt') input.remove();
      if (options.type === 'alert') cancel.remove();
      var finish = function (value) { close(value); };
      var onKey = function (e) {
        if (e.key === 'Escape') { e.preventDefault(); finish(options.type === 'prompt' ? null : (options.type === 'alert' ? true : false)); return; }
        if (e.key === 'Enter' && options.type !== 'alert') {
          e.preventDefault();
          finish(options.type === 'prompt' ? String(input.value || '') : true);
        }
        if (e.key !== 'Tab') return;
        var focusables = Array.prototype.filter.call(root.querySelectorAll('button:not([disabled]),input:not([disabled])'), function (el) { return el.offsetParent !== null; });
        if (!focusables.length) return;
        var first = focusables[0];
        var last = focusables[focusables.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
      };
      active = { root: root, resolve: resolve, onKey: onKey };
      document.addEventListener('keydown', onKey, true);
      root.querySelector('.skdlg__backdrop').addEventListener('click', function () { finish(options.type === 'prompt' ? null : false); });
      root.querySelector('.skdlg__close').addEventListener('click', function () { finish(options.type === 'prompt' ? null : false); });
      if (cancel) cancel.addEventListener('click', function () { finish(options.type === 'prompt' ? null : false); });
      ok.addEventListener('click', function () { finish(options.type === 'prompt' ? String(input.value || '') : true); });
      setTimeout(function () { (input || ok).focus(); }, 40);
    });
  }

  window.SonikaDialog = {
    alert: function (message, options) { return open(Object.assign({ type: 'alert', message: message }, options || {})); },
    confirm: function (message, options) { return open(Object.assign({ type: 'confirm', message: message }, options || {})); },
    prompt: function (message, defaultValue, options) { return open(Object.assign({ type: 'prompt', message: message, defaultValue: defaultValue || '' }, options || {})); },
    close: function () { close(null); }
  };
  window.alert = function (message) { window.SonikaDialog.alert(message, { title: 'SONIKA' }); };
})();
