(function () {
  if (window.__SONIKA_TIA_EMBED__) return;
  window.__SONIKA_TIA_EMBED__ = true;

  var script = document.currentScript;
  if (!script) return;

  var attr = function (name, fallback) {
    var v = script.getAttribute(name);
    return v === null || v === '' ? fallback : v;
  };

  var cfg = {
    api: attr('data-tia-api', '/api/v1/public/tia/chat'),
    site: attr('data-tia-site', 'sonika'),
    title: attr('data-tia-title', 'TIA · Asistente SONIKA'),
    welcome: attr('data-tia-welcome', 'Hola, soy TIA, el asistente virtual de SONIKA. Puedo ayudarte con productos, envíos, pagos, garantías y soporte. Si necesitas una persona, escríbenos por WhatsApp o usa el formulario.'),
    mode: attr('data-tia-mode', 'both'),
    mount: attr('data-tia-mount', '[data-tia-support]'),
    disableOn: attr('data-tia-disable-on', '/soporte'),
    whatsapp: attr('data-tia-whatsapp', ''),
    form: attr('data-tia-form', '/soporte#contacto'),
    pqrf: attr('data-tia-pqrf', '/pqrf'),
    color: attr('data-tia-color', '#F20D16'),
    locale: (document.documentElement.getAttribute('lang') || 'es').slice(0, 2).toLowerCase()
  };

  if (!/^#[0-9a-f]{3,8}$/i.test(cfg.color)) cfg.color = '#F20D16';
  if (cfg.locale !== 'en') cfg.locale = 'es';

  var path = location.pathname.replace(/\/+$/, '') || '/';
  var disabledPath = cfg.disableOn && (path === cfg.disableOn || path.indexOf(cfg.disableOn + '/') === 0);
  var mount = cfg.mount ? document.querySelector(cfg.mount) : null;
  var wantEmbedded = !!mount && cfg.mode !== 'bubble';
  var wantFloating = !wantEmbedded && cfg.mode !== 'embedded';

  var storeKey = 'sonika_tia_messages_v1_' + cfg.site;
  var state = { messages: [], sending: false, typing: false };
  try {
    var saved = sessionStorage.getItem(storeKey);
    if (saved) {
      var parsed = JSON.parse(saved);
      if (Array.isArray(parsed)) state.messages = parsed.filter(function (m) {
        return m && typeof m.text === 'string' && (m.role === 'user' || m.role === 'tia' || m.role === 'system');
      }).slice(-40);
    }
  } catch (e) {}

  if (!state.messages.length) {
    state.messages.push({ role: 'tia', text: cfg.welcome });
  }

  function save() {
    try { sessionStorage.setItem(storeKey, JSON.stringify(state.messages.slice(-40))); } catch (e) {}
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  }

  function safeUrl(url, fallback) {
    url = String(url || '').trim();
    if (/^https?:\/\//i.test(url) || /^\//.test(url)) return url;
    return fallback || '#';
  }

  function css() {
    return [
      ':host{all:initial;font-family:Sora,Inter,system-ui,-apple-system,sans-serif;color:#111;--tia-brand:' + cfg.color + ';--tia-line:#E6E6E6;--tia-muted:#6B6B6B;--tia-soft:#F7F7F7;}',
      '*,*::before,*::after{box-sizing:border-box}',
      ':host(.tia-host--fab){position:fixed;inset:0;z-index:9002;pointer-events:none}',
      ':host(.tia-host--float){position:fixed;inset:0;z-index:9001;pointer-events:none}',
      ':host(.tia-host--embedded){display:block;width:100%;}',
      '.tia-fab{position:fixed;right:18px;bottom:calc(18px + env(safe-area-inset-bottom));z-index:9002;width:58px;height:58px;border:0;border-radius:50%;background:var(--tia-brand);color:#fff;font:800 13px/1 Sora,Inter,system-ui,sans-serif;letter-spacing:.06em;display:flex;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 14px 34px rgba(242,13,22,.35);pointer-events:auto;transition:transform .18s ease,filter .18s ease,opacity .18s ease}',
      '.tia-fab:hover{filter:brightness(.94);transform:translateY(-1px)}',
      '.tia-fab:active{transform:translateY(1px)}',
      '.tia-fab:focus-visible{outline:3px solid rgba(242,13,22,.35);outline-offset:3px}',
      '.tia-fab--disabled{background:#9CA3AF;color:#fff;box-shadow:none;opacity:.62;cursor:default;pointer-events:none;filter:grayscale(1)}',
      '.tia-panel{position:fixed;right:18px;bottom:calc(88px + env(safe-area-inset-bottom));z-index:9001;width:min(382px,calc(100vw - 24px));height:min(580px,calc(100vh - 120px));height:min(580px,calc(100dvh - 120px));background:#fff;border:1px solid var(--tia-line);border-radius:18px;box-shadow:0 28px 80px rgba(0,0,0,.24);display:flex;flex-direction:column;overflow:hidden;visibility:hidden;opacity:0;transform:translateY(12px);pointer-events:none;transition:opacity .18s ease,transform .18s ease,visibility .18s}',
      '.tia-panel.is-open{visibility:visible;opacity:1;transform:none;pointer-events:auto}',
      '.tia-embedded{width:100%;height:clamp(380px,52vh,520px);height:clamp(380px,52dvh,520px);background:#fff;border:1px solid var(--tia-line);border-radius:14px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 10px 28px rgba(0,0,0,.05)}',
      '.tia-head{display:flex;align-items:center;gap:10px;padding:13px 14px;background:#0b0b0b;color:#fff;border-bottom:1px solid rgba(255,255,255,.1);flex:0 0 auto}',
      '.tia-av{width:38px;height:38px;border-radius:50%;background:var(--tia-brand);color:#fff;display:grid;place-items:center;font-weight:800;font-size:12px;letter-spacing:.04em;flex:0 0 auto}',
      '.tia-head-main{min-width:0;flex:1}',
      '.tia-head h3{margin:0;font-size:14px;line-height:1.25;font-weight:800;letter-spacing:-.01em;color:#fff}',
      '.tia-head small{display:block;margin-top:2px;font-size:11px;line-height:1.3;color:#A3A3A3;font-weight:500}',
      '.tia-x{width:40px;height:40px;border:0;border-radius:50%;background:transparent;color:#fff;font-size:22px;line-height:1;cursor:pointer;display:grid;place-items:center;flex:0 0 auto}',
      '.tia-x:hover{background:rgba(255,255,255,.1)}',
      '.tia-x:focus-visible{outline:2px solid #fff;outline-offset:2px}',
      '.tia-messages{flex:1 1 auto;min-height:0;overflow-y:auto;overscroll-behavior:contain;padding:14px 12px;display:flex;flex-direction:column;gap:10px;background:#fff;scrollbar-width:thin}',
      '.tia-msg{display:flex;align-items:flex-start;gap:7px;max-width:100%}',
      '.tia-msg--user{justify-content:flex-end}',
      '.tia-msg--user .tia-bubble{background:var(--tia-brand);color:#fff;border-radius:14px 14px 4px 14px}',
      '.tia-msg--tia .tia-bubble{background:#F5F5F5;color:#111;border-radius:14px 14px 14px 4px}',
      '.tia-msg--system .tia-bubble{background:#FFF5F5;color:#7A1015;border:1px solid #F5C2C2;border-radius:12px}',
      '.tia-msg .tia-av{width:26px;height:26px;font-size:9.5px;margin-top:2px}',
      '.tia-bubble{max-width:85%;padding:10px 12px;font-size:13.5px;line-height:1.52;white-space:pre-wrap;overflow-wrap:anywhere}',
      '.tia-typing .tia-bubble{display:flex;gap:4px;align-items:center;min-width:52px}',
      '.tia-typing i{width:6px;height:6px;border-radius:50%;background:#9CA3AF;display:block;animation:tiaDot 1s infinite ease-in-out}',
      '.tia-typing i:nth-child(2){animation-delay:.15s}.tia-typing i:nth-child(3){animation-delay:.3s}',
      '@keyframes tiaDot{0%,80%,100%{transform:translateY(0);opacity:.45}40%{transform:translateY(-3px);opacity:1}}',
      '.tia-quick{display:flex;gap:6px;overflow-x:auto;padding:8px 12px;background:#fff;border-top:1px solid var(--tia-line);scrollbar-width:none;flex:0 0 auto}',
      '.tia-quick::-webkit-scrollbar{display:none}',
      '.tia-quick[hidden]{display:none}',
      '.tia-chip{flex:0 0 auto;border:1px solid var(--tia-line);background:#fff;color:#333;border-radius:999px;padding:7px 11px;font:650 12px/1.2 Sora,Inter,system-ui,sans-serif;cursor:pointer;white-space:nowrap;text-decoration:none;min-height:34px;display:inline-flex;align-items:center}',
      '.tia-chip:hover{border-color:var(--tia-brand);color:var(--tia-brand)}',
      '.tia-chip:focus-visible{outline:2px solid var(--tia-brand);outline-offset:2px}',
      '.tia-foot{display:flex;gap:10px;flex-wrap:wrap;align-items:center;padding:8px 12px;border-top:1px solid var(--tia-line);font-size:11px;line-height:1.4;background:#fff;flex:0 0 auto}',
      '.tia-foot a{color:var(--tia-brand);font-weight:700;text-decoration:none}',
      '.tia-foot a:hover{text-decoration:underline}',
      '.tia-foot a:focus-visible{outline:2px solid var(--tia-brand);outline-offset:2px;border-radius:3px}',
      '.tia-note{padding:0 12px 8px;color:#8A8A8A;font-size:10.5px;line-height:1.4;background:#fff;flex:0 0 auto}',
      '.tia-inputrow{display:flex;align-items:flex-end;gap:8px;padding:10px 12px calc(10px + env(safe-area-inset-bottom));border-top:1px solid var(--tia-line);background:#fff;flex:0 0 auto}',
      '.tia-input{flex:1 1 auto;min-width:0;min-height:42px;max-height:92px;padding:10px 11px;border:1px solid var(--tia-line);border-radius:10px;background:#fff;color:#111;font:inherit;font-size:16px;line-height:1.4;resize:none;outline:none}',
      '.tia-input:focus{border-color:var(--tia-brand);box-shadow:0 0 0 3px rgba(242,13,22,.1)}',
      '.tia-send{width:42px;height:42px;border:0;border-radius:50%;background:var(--tia-brand);color:#fff;font-size:18px;display:grid;place-items:center;cursor:pointer;flex:0 0 auto}',
      '.tia-send:disabled{opacity:.42;cursor:default}',
      '.tia-send:focus-visible{outline:3px solid rgba(242,13,22,.32);outline-offset:2px}',
      '@media(max-width:640px){.tia-panel{right:0;bottom:0;width:100%;height:100vh;height:100dvh;border-radius:0;border:0}.tia-panel .tia-head{padding-top:calc(13px + env(safe-area-inset-top))}.tia-fab{right:14px;bottom:calc(14px + env(safe-area-inset-bottom));width:54px;height:54px}.tia-embedded{height:min(62vh,520px);height:min(62dvh,520px);border-radius:12px}}',
      '@media(prefers-reduced-motion:reduce){*{transition:none!important;animation:none!important;scroll-behavior:auto!important}}'
    ].join('');
  }

  function makeHost(className) {
    var host = document.createElement('div');
    host.className = 'tia-host ' + className;
    var root = host.attachShadow({ mode: 'open' });
    var style = document.createElement('style');
    style.textContent = css();
    root.appendChild(style);
    return { host: host, root: root };
  }

  function makeBubble(disabled) {
    var h = makeHost('tia-host--fab');
    var btn = el('button', 'tia-fab' + (disabled ? ' tia-fab--disabled' : ''), 'TIA');
    btn.type = 'button';
    btn.setAttribute('aria-label', disabled ? 'TIA está disponible en el chat de esta página' : 'Abrir chat de TIA');
    if (disabled) {
      btn.disabled = true;
      btn.setAttribute('aria-disabled', 'true');
      btn.title = 'Ya estás en el Centro de Soporte';
    }
    h.root.appendChild(btn);
    document.body.appendChild(h.host);
    return { host: h.host, root: h.root, btn: btn };
  }

  function makeChat(container, embedded) {
    var h = makeHost(embedded ? 'tia-host--embedded' : 'tia-host--float');
    container.appendChild(h.host);
    var root = h.root;

    var panel = el('section', embedded ? 'tia-embedded' : 'tia-panel');
    panel.setAttribute('role', embedded ? 'region' : 'dialog');
    panel.setAttribute('aria-label', 'Chat de TIA');
    if (!embedded) {
      panel.setAttribute('aria-modal', 'true');
      panel.setAttribute('aria-hidden', 'true');
    }

    var head = el('div', 'tia-head');
    head.appendChild(el('span', 'tia-av', 'TIA'));
    var headMain = el('div', 'tia-head-main');
    var h3 = el('h3', '', cfg.title);
    var small = el('small', '', 'Asistente automático · respuestas por IA');
    headMain.appendChild(h3);
    headMain.appendChild(small);
    head.appendChild(headMain);
    var closeBtn = null;
    if (!embedded) {
      closeBtn = el('button', 'tia-x', '×');
      closeBtn.type = 'button';
      closeBtn.setAttribute('aria-label', 'Cerrar chat');
      head.appendChild(closeBtn);
    }
    panel.appendChild(head);

    var messagesEl = el('div', 'tia-messages');
    messagesEl.setAttribute('role', 'log');
    messagesEl.setAttribute('aria-live', 'polite');
    messagesEl.setAttribute('aria-relevant', 'additions');
    panel.appendChild(messagesEl);

    var quick = el('div', 'tia-quick');
    panel.appendChild(quick);

    var foot = el('div', 'tia-foot');
    panel.appendChild(foot);

    var note = el('div', 'tia-note', 'TIA es un asistente automático. Para casos formales usa PQRF.');
    panel.appendChild(note);

    var inputRow = el('div', 'tia-inputrow');
    var input = el('textarea', 'tia-input');
    input.rows = 1;
    input.maxLength = 1000;
    input.placeholder = 'Escribe tu mensaje a TIA…';
    input.setAttribute('aria-label', 'Escribe tu mensaje a TIA');
    var sendBtn = el('button', 'tia-send', '➤');
    sendBtn.type = 'button';
    sendBtn.setAttribute('aria-label', 'Enviar mensaje');
    inputRow.appendChild(input);
    inputRow.appendChild(sendBtn);
    panel.appendChild(inputRow);

    root.appendChild(panel);

    var ui = {
      panel: panel,
      root: root,
      messagesEl: messagesEl,
      quick: quick,
      foot: foot,
      input: input,
      sendBtn: sendBtn,
      closeBtn: closeBtn,
      embedded: embedded
    };

    function scroll() {
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function renderMessages() {
      messagesEl.textContent = '';
      state.messages.forEach(function (m) {
        var row = el('div', 'tia-msg tia-msg--' + (m.role === 'user' ? 'user' : (m.role === 'system' ? 'system' : 'tia')));
        if (m.role !== 'user') row.appendChild(el('span', 'tia-av', m.role === 'system' ? '!' : 'TIA'));
        var bubble = el('div', 'tia-bubble', m.text);
        row.appendChild(bubble);
        messagesEl.appendChild(row);
      });
      if (state.typing) {
        var t = el('div', 'tia-msg tia-msg--tia tia-typing');
        t.appendChild(el('span', 'tia-av', 'TIA'));
        var b = el('div', 'tia-bubble');
        b.appendChild(el('i'));
        b.appendChild(el('i'));
        b.appendChild(el('i'));
        t.appendChild(b);
        messagesEl.appendChild(t);
      }
      scroll();
    }

    function renderQuick() {
      quick.textContent = '';
      var used = state.messages.some(function (m) { return m.role === 'user'; });
      quick.hidden = used;
      if (used) return;
      ['Elegir un instrumento', 'Envíos', 'Garantías'].forEach(function (text) {
        var b = el('button', 'tia-chip', text);
        b.type = 'button';
        b.addEventListener('click', function () { send(text); });
        quick.appendChild(b);
      });
      var handoff = el('a', 'tia-chip', 'Hablar con una persona');
      handoff.href = safeUrl(cfg.whatsapp, cfg.form);
      if (/^https?:\/\//i.test(handoff.href)) {
        handoff.target = '_blank';
        handoff.rel = 'noopener noreferrer';
      }
      handoff.addEventListener('click', function (e) {
        if (!cfg.whatsapp) {
          e.preventDefault();
          location.href = safeUrl(cfg.form, '/soporte#contacto');
        }
      });
      quick.appendChild(handoff);
    }

    function renderFoot() {
      foot.textContent = '';
      var form = el('a', '', 'Formulario');
      form.href = safeUrl(cfg.form, '/soporte#contacto');
      foot.appendChild(form);
      if (cfg.whatsapp) {
        var wa = el('a', '', 'WhatsApp');
        wa.href = safeUrl(cfg.whatsapp, '#');
        wa.target = '_blank';
        wa.rel = 'noopener noreferrer';
        foot.appendChild(wa);
      }
      var pqrf = el('a', '', 'PQRF');
      pqrf.href = safeUrl(cfg.pqrf, '/pqrf');
      foot.appendChild(pqrf);
    }

    function updateSend() {
      sendBtn.disabled = state.sending || input.value.trim() === '';
    }

    function autoGrow() {
      input.style.height = 'auto';
      input.style.height = Math.min(input.scrollHeight, 92) + 'px';
    }

    function render() {
      renderMessages();
      renderQuick();
      renderFoot();
      updateSend();
    }

    function send(text) {
      text = String(text || '').trim();
      if (!text || state.sending) return;
      state.messages.push({ role: 'user', text: text });
      state.sending = true;
      state.typing = true;
      input.value = '';
      autoGrow();
      save();
      render();
      fetch(cfg.api, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ message: text, page: location.pathname, locale: cfg.locale })
      }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          return { res: res, data: data };
        });
      }).then(function (out) {
        state.typing = false;
        if (!out.res.ok || !out.data || !out.data.ok) {
          var msg = (out.data && out.data.message) || 'No pudimos conectar con TIA. Usa WhatsApp o el formulario de soporte.';
          state.messages.push({ role: 'system', text: msg });
          if (out.data && out.data.handoff && out.data.handoff.url) {
            if (out.data.handoff.kind === 'form') cfg.form = out.data.handoff.url;
            else cfg.whatsapp = out.data.handoff.url;
          }
        } else {
          state.messages.push({ role: 'tia', text: String(out.data.reply || '') });
          if (out.data.handoff && out.data.handoff.url) {
            if (out.data.handoff.kind === 'form') cfg.form = out.data.handoff.url;
            else cfg.whatsapp = out.data.handoff.url;
          }
        }
      }).catch(function () {
        state.typing = false;
        state.messages.push({ role: 'system', text: 'No pudimos conectar. Revisa tu conexión o usa WhatsApp/formulario.' });
      }).then(function () {
        state.sending = false;
        save();
        render();
        input.focus();
      });
    }

    input.addEventListener('input', function () { autoGrow(); updateSend(); });
    input.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) {
        e.preventDefault();
        send(input.value);
      }
    });
    sendBtn.addEventListener('click', function () { send(input.value); });
    if (closeBtn) closeBtn.addEventListener('click', function () { close(); });
    panel.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !embedded) close();
      if (!embedded) trapFocus(e);
    });

    render();
    return ui;
  }

  var bubble = null;
  var chat = null;
  var floatingOpen = false;
  var lastFocus = null;

  function open() {
    if (!chat || chat.embedded) return;
    floatingOpen = true;
    chat.panel.classList.add('is-open');
    chat.panel.setAttribute('aria-hidden', 'false');
    if (bubble) {
      bubble.btn.setAttribute('aria-expanded', 'true');
      lastFocus = bubble.btn;
    }
    document.body.style.overflow = 'hidden';
    setTimeout(function () { chat.input.focus(); }, 30);
  }

  function close() {
    if (!chat || chat.embedded) return;
    floatingOpen = false;
    chat.panel.classList.remove('is-open');
    chat.panel.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    if (bubble) {
      bubble.btn.setAttribute('aria-expanded', 'false');
      bubble.btn.focus();
    }
  }

  function trapFocus(e) {
    if (!chat || chat.embedded || !floatingOpen || e.key !== 'Tab') return;
    var nodes = chat.panel.querySelectorAll('a[href],button:not([disabled]),textarea,input,[tabindex]:not([tabindex="-1"])');
    if (!nodes.length) return;
    var first = nodes[0];
    var last = nodes[nodes.length - 1];
    var active = (chat.root && chat.root.activeElement) || document.activeElement;
    if (e.shiftKey && active === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && active === last) {
      e.preventDefault();
      first.focus();
    }
  }

  function boot() {
    if (wantEmbedded) {
      chat = makeChat(mount, true);
      bubble = makeBubble(true);
      return;
    }
    if (wantFloating) {
      chat = makeChat(document.body, false);
      bubble = makeBubble(disabledPath);
      if (!disabledPath) {
        bubble.btn.setAttribute('aria-expanded', 'false');
        bubble.btn.addEventListener('click', function () {
          if (floatingOpen) close(); else open();
        });
      }
    } else if (disabledPath) {
      bubble = makeBubble(true);
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && floatingOpen) close();
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
