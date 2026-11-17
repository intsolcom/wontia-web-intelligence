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
  var state = { messages: [], sending: false, typing: false, progress: 0, progressTimer: null, progressStarted: 0 };
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
    if (/^https?:\/\//i.test(url) || /^mailto:/i.test(url) || /^\//.test(url)) return url;
    return fallback || '#';
  }

  function tiaEvent(type, target, value, order) {
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

  function tiaExperiment() {
    try {
      var raw = sessionStorage.getItem('wwi_tia_experiment') || '';
      var data = raw ? JSON.parse(raw) : null;
      if (data && data.key && (data.variant === 'A' || data.variant === 'B')) return data;
    } catch (e) {}
    return null;
  }

  function tiaRememberExperiment(exp) {
    if (!exp || !exp.key || (exp.variant !== 'A' && exp.variant !== 'B')) return;
    try { sessionStorage.setItem('wwi_tia_experiment', JSON.stringify({ key: exp.key, variant: exp.variant })); } catch (e) {}
  }

  function tiaExperimentEvent(type, target) {
    var exp = tiaExperiment();
    if (!exp) return;
    try {
      fetch('/api/v1/public/tia/experiment-event', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ experiment_key: exp.key, experiment_variant: exp.variant, event: type, target: target || '' }),
        keepalive: true
      }).catch(function () {});
    } catch (e) {}
  }

  function orbCss() {
    return `
:host(.tia-host--fab){--tia-orb-size:64px;--tia-orb-right:24px;--tia-orb-bottom:24px;--tia-orb-halo:8px}
.tia-fab.tia-orb{--tia-red:#f20d20;--tia-red-dark:#a90013;--tia-red-light:#ff3545;position:fixed;right:calc(var(--tia-orb-right,24px) + env(safe-area-inset-right));bottom:calc(var(--tia-orb-bottom,24px) + env(safe-area-inset-bottom));z-index:2;width:var(--tia-orb-size,64px);height:var(--tia-orb-size,64px);display:grid;place-items:center;border:0;border-radius:50%;padding:0;color:#fff;background:radial-gradient(circle at 32% 24%,var(--tia-red-light) 0%,var(--tia-red) 42%,var(--tia-red-dark) 100%);box-shadow:0 10px 30px rgba(242,13,32,.28),inset 0 1px 2px rgba(255,255,255,.5),inset 0 -6px 14px rgba(80,0,10,.28);isolation:isolate;overflow:hidden;cursor:pointer;pointer-events:auto;-webkit-tap-highlight-color:transparent;transform:translateZ(0);transition:transform .2s ease,box-shadow .2s ease,opacity .2s ease,filter .2s ease}
.tia-orb__surface{position:absolute;inset:0;border-radius:inherit;pointer-events:none;z-index:0;background:radial-gradient(ellipse at 28% 18%,rgba(255,255,255,.38),transparent 38%),radial-gradient(ellipse at 76% 84%,rgba(80,0,10,.26),transparent 58%)}
.tia-orb__halo{position:fixed;right:calc(var(--tia-orb-right,24px) + env(safe-area-inset-right) - var(--tia-orb-halo,8px));bottom:calc(var(--tia-orb-bottom,24px) + env(safe-area-inset-bottom) - var(--tia-orb-halo,8px));width:calc(var(--tia-orb-size,64px) + var(--tia-orb-halo,8px) + var(--tia-orb-halo,8px));height:calc(var(--tia-orb-size,64px) + var(--tia-orb-halo,8px) + var(--tia-orb-halo,8px));border-radius:50%;background:radial-gradient(circle,rgba(242,13,32,.32) 0%,rgba(242,13,32,.1) 52%,rgba(242,13,32,0) 72%);pointer-events:none;z-index:1;opacity:.55;transition:opacity .25s ease,transform .25s ease}
.tia-orb__equalizer{position:absolute;left:50%;top:calc(var(--tia-orb-size,64px)*.105);width:calc(var(--tia-orb-size,64px)*.60);height:calc(var(--tia-orb-size,64px)*.60);transform:translateX(-50%);z-index:1;opacity:.5;pointer-events:none}
.tia-orb__bars rect{fill:#fff;transform-box:fill-box;transform-origin:center;transform:scaleY(1);transition:transform .2s ease}
.tia-orb__label{position:absolute;left:50%;bottom:calc(var(--tia-orb-size,64px)*.105);transform:translateX(-50%);z-index:2;font-family:inherit;font-size:calc(var(--tia-orb-size,64px)*.18);font-weight:800;line-height:1;letter-spacing:.06em;text-shadow:0 1px 3px rgba(70,0,8,.42);pointer-events:none}
.tia-orb__ring{position:absolute;inset:4px;border-radius:50%;pointer-events:none;z-index:1;opacity:0;background:conic-gradient(from 0deg,transparent 0 56%,rgba(255,255,255,.86) 69%,rgba(255,255,255,.15) 82%,transparent 100%);-webkit-mask:radial-gradient(farthest-side,transparent calc(100% - 3px),#000 calc(100% - 2px));mask:radial-gradient(farthest-side,transparent calc(100% - 3px),#000 calc(100% - 2px));transition:opacity .2s ease}
.tia-fab.tia-orb:hover{filter:none;transform:scale(1.04);box-shadow:0 12px 36px rgba(242,13,32,.36),inset 0 1px 3px rgba(255,255,255,.55),inset 0 -6px 14px rgba(80,0,10,.26)}
.tia-fab.tia-orb:hover + .tia-orb__halo{opacity:.82;transform:scale(1.06)}
.tia-fab.tia-orb:hover .tia-orb__equalizer{opacity:.65}
.tia-fab.tia-orb:active{transform:scale(.98)}
.tia-fab.tia-orb:focus-visible{outline:3px solid #fff;outline-offset:3px;box-shadow:0 0 0 6px rgba(242,13,32,.3),0 12px 36px rgba(242,13,32,.34),inset 0 1px 3px rgba(255,255,255,.55)}
.tia-fab.tia-orb.is-hidden{opacity:0;visibility:hidden;pointer-events:none;transform:scale(.85)}
.tia-fab.tia-orb.is-hidden + .tia-orb__halo{opacity:0;visibility:hidden;transform:scale(.9)}
.tia-fab.tia-orb.tia-fab--disabled{background:radial-gradient(circle at 32% 24%,#c9cdd3,#8b9199 58%,#5f6670);box-shadow:0 6px 18px rgba(0,0,0,.16),inset 0 1px 1px rgba(255,255,255,.35);filter:grayscale(.55);opacity:.7;cursor:default}
.tia-fab.tia-orb.tia-fab--disabled + .tia-orb__halo{opacity:.12}
.tia-fab.tia-orb.tia-fab--disabled .tia-orb__equalizer{opacity:.12}
.tia-orb--equalizing .tia-orb__bars rect{animation:tiaOrbEq .65s ease-in-out infinite alternate}
.tia-orb--equalizing .tia-orb__bars rect:nth-child(2),.tia-orb--equalizing .tia-orb__bars rect:nth-child(4){animation-delay:.1s}
.tia-orb--equalizing .tia-orb__bars rect:nth-child(3){animation-delay:.2s}
.tia-orb--equalizing + .tia-orb__halo{animation:tiaOrbHalo 1.9s ease-in-out}
.tia-orb--equalizing .tia-orb__surface{animation:tiaOrbSurface 1.9s ease-in-out}
.tia-orb--equalizing .tia-orb__equalizer{animation:tiaOrbEqReveal 1.9s ease-in-out;opacity:.95}
.tia-orb--equalizing .tia-orb__label{text-shadow:0 1px 3px rgba(70,0,8,.5)}
.tia-orb--thinking .tia-orb__ring{opacity:1;animation:tiaOrbRing 1.8s linear infinite}
.tia-orb--thinking .tia-orb__bars rect{animation:tiaOrbThink 2.2s ease-in-out infinite alternate}
.tia-orb--thinking .tia-orb__equalizer{opacity:.55}
.tia-orb--thinking + .tia-orb__halo{opacity:.7}
.tia-orb--listening .tia-orb__equalizer{opacity:.7}
.tia-orb--listening .tia-orb__bars rect{animation:tiaOrbListen 1.1s ease-in-out infinite alternate}
.tia-orb--listening .tia-orb__bars rect:nth-child(2),.tia-orb--listening .tia-orb__bars rect:nth-child(4){animation-delay:.1s}
.tia-orb--listening .tia-orb__bars rect:nth-child(3){animation-delay:.2s}
.tia-orb--listening + .tia-orb__halo{opacity:.7}
.tia-orb--speaking .tia-orb__equalizer{opacity:.8}
.tia-orb--speaking .tia-orb__bars rect{animation:tiaOrbSpeak .48s ease-in-out infinite alternate}
.tia-orb--speaking .tia-orb__bars rect:nth-child(2),.tia-orb--speaking .tia-orb__bars rect:nth-child(4){animation-delay:.08s}
.tia-orb--speaking .tia-orb__bars rect:nth-child(3){animation-delay:.16s}
.tia-orb--speaking + .tia-orb__halo{opacity:.8}
.tia-orb--open .tia-orb__bars rect,.tia-orb--open .tia-orb__ring{animation:none!important}
.tia-orb--open .tia-orb__ring{opacity:0}
.tia-orb--open .tia-orb__bars rect{transform:scaleY(1)}
.tia-orb--disabled .tia-orb__bars rect{animation:none!important;transform:scaleY(1)}
@keyframes tiaOrbEq{from{transform:scaleY(.55)}to{transform:scaleY(1.08)}}
@keyframes tiaOrbHalo{0%,100%{opacity:.55;transform:scale(1)}45%{opacity:.9;transform:scale(1.06)}}
@keyframes tiaOrbEqReveal{0%{opacity:.5}18%{opacity:.95}75%{opacity:.95}100%{opacity:.5}}
@keyframes tiaOrbSurface{0%,100%{transform:scale(1)}45%{transform:scale(1.035)}}
@keyframes tiaOrbRing{to{transform:rotate(360deg)}}
@keyframes tiaOrbThink{from{transform:scaleY(.85)}to{transform:scaleY(1.03)}}
@keyframes tiaOrbListen{from{transform:scaleY(.7)}to{transform:scaleY(1.03)}}
@keyframes tiaOrbSpeak{from{transform:scaleY(.55)}to{transform:scaleY(1.08)}}
.tia-head .tia-av--orb{position:relative;overflow:hidden;background:radial-gradient(circle at 32% 24%,var(--tia-red-light,#ff3545) 0%,var(--tia-red,#f20d20) 44%,var(--tia-red-dark,#a90013) 100%);box-shadow:0 4px 12px rgba(242,13,32,.28),inset 0 1px 1px rgba(255,255,255,.4);border:1px solid rgba(255,255,255,.18)}
.tia-av__eq{position:absolute;left:50%;top:3.5px;width:22px;height:22px;transform:translateX(-50%);opacity:.5;pointer-events:none}
.tia-av__eq rect{fill:#fff;transform-box:fill-box;transform-origin:center;transform:scaleY(1)}
.tia-av__label{position:absolute;left:50%;bottom:3px;transform:translateX(-50%);z-index:2;font-size:9px;font-weight:800;line-height:1;letter-spacing:.06em;text-shadow:0 1px 2px rgba(70,0,8,.32);pointer-events:none}
.tia-av__ring{position:absolute;inset:2px;border-radius:50%;opacity:0;pointer-events:none;z-index:1;background:conic-gradient(from 0deg,transparent 0 56%,rgba(255,255,255,.9) 69%,rgba(255,255,255,.15) 82%,transparent 100%);-webkit-mask:radial-gradient(farthest-side,transparent calc(100% - 2px),#000 calc(100% - 1px));mask:radial-gradient(farthest-side,transparent calc(100% - 2px),#000 calc(100% - 1px));transition:opacity .2s ease}
.tia-av--thinking .tia-av__ring{opacity:1;animation:tiaOrbRing 1.8s linear infinite}
.tia-av--thinking .tia-av__eq{opacity:.65}
.tia-av--speaking .tia-av__eq rect{animation:tiaOrbSpeak .48s ease-in-out infinite alternate}
.tia-av--speaking .tia-av__eq{opacity:.8}
.tia-av--listening .tia-av__eq rect{animation:tiaOrbListen 1.1s ease-in-out infinite alternate}
.tia-av--listening .tia-av__eq{opacity:.75}
.tia-msg .tia-av{background:radial-gradient(circle at 32% 24%,#ff3545,#f20d20 44%,#a90013);box-shadow:inset 0 1px 2px rgba(255,255,255,.3)}
@media(max-width:1024px){:host(.tia-host--fab){--tia-orb-size:60px}}
@media(max-width:640px){:host(.tia-host--fab){--tia-orb-size:56px;--tia-orb-right:16px;--tia-orb-bottom:16px;--tia-orb-halo:7px}}
@media(prefers-reduced-motion:reduce){.tia-fab.tia-orb:hover{transform:none}.tia-orb__halo,.tia-orb__surface,.tia-orb__bars rect,.tia-av__eq rect,.tia-av__ring{animation:none!important;transition:none!important}.tia-orb__bars rect{transform:scaleY(1)}.tia-av__eq rect{transform:scaleY(1)}}
`;
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
      '.tia-fab.is-hidden{opacity:0;visibility:hidden;pointer-events:none;transform:scale(.85)}',
      orbCss(),
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
      '.tia-products{display:flex;flex-direction:column;gap:7px;margin-top:9px;white-space:normal}',
      '.tia-feedback{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:9px;padding-top:8px;border-top:1px solid #E7E9EE;white-space:normal;font-size:10px;color:#667085}',
      '.tia-feedback button{border:1px solid #DDE1E8;background:#fff;color:#344054;border-radius:7px;padding:4px 8px;font:inherit;font-size:10px;font-weight:700;cursor:pointer;transition:border-color .15s,background .15s,color .15s}',
      '.tia-feedback button:hover{border-color:var(--tia-brand);color:var(--tia-brand);background:#FFF8F8}',
      '.tia-feedback button:focus-visible{outline:2px solid var(--tia-brand);outline-offset:2px}',
      '.tia-feedback__done{font-size:10px;color:#667085}',
      '.tia-handoff{margin-top:9px;padding:9px;border:1px solid #E3D9FF;background:#FAF7FF;border-radius:10px;white-space:normal;color:#3F2D68;font-size:10.5px;line-height:1.45}',
      '.tia-handoff__title{font-size:9px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;margin-bottom:4px}',
      '.tia-handoff__text{font-size:10.5px;color:#5B4A7A;margin-bottom:7px}',
      '.tia-handoff__actions{display:flex;gap:6px;flex-wrap:wrap;align-items:center}',
      '.tia-handoff__actions button,.tia-handoff__actions a{border:1px solid #D8C9FF;background:#fff;color:#5B3FA8;border-radius:7px;padding:4px 8px;font:inherit;font-size:10px;font-weight:700;text-decoration:none;cursor:pointer}',
      '.tia-compare{margin-top:9px;white-space:normal;border:1px solid #E8EAF0;border-radius:11px;overflow:hidden;background:#fff}',
      '.tia-compare__head{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:#E8EAF0}',
      '.tia-compare__product{background:#fff;padding:8px;min-width:0}',
      '.tia-compare__product img{width:100%;height:72px;object-fit:contain;background:#F7F8FA;border-radius:7px}',
      '.tia-compare__product b{display:block;font-size:10px;line-height:1.3;margin-top:5px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}',
      '.tia-compare__product small{display:block;font-size:9px;color:#667085;margin-top:2px}',
      '.tia-compare__rows{padding:4px 8px 7px}',
      '.tia-compare__row{display:grid;grid-template-columns:1fr 1fr;gap:8px;padding:5px 0;border-top:1px solid #F0F2F5;font-size:9.5px}',
      '.tia-compare__label{grid-column:1/-1;font-size:8px;font-weight:800;letter-spacing:.06em;text-transform:uppercase;color:#98A2B3;padding-top:5px}',
      '.tia-compare__row>div{min-width:0;overflow-wrap:anywhere;line-height:1.35}',
      '.tia-compare__note{font-size:9px;color:#98A2B3;line-height:1.4;padding:0 8px 8px}',
      '.tia-image-link{display:block;margin-top:8px;border:1px solid #E8EAF0;border-radius:10px;overflow:hidden;background:#F7F8FA;text-decoration:none}',
      '.tia-image-link img{display:block;width:100%;max-height:260px;object-fit:contain;background:#fff}',
      '.tia-image-caption{display:block;padding:7px 9px;font-size:10px;font-weight:700;line-height:1.35;color:#344054}',
      '@media(max-width:360px){.tia-compare__product img{height:58px}}',
      '.tia-product{display:flex;gap:9px;align-items:center;padding:7px;border:1px solid #ECEFF3;border-radius:11px;background:#fff;color:#111;text-decoration:none;transition:border-color .2s,transform .2s}',
      '.tia-product:hover{border-color:var(--tia-brand);transform:translateY(-1px)}',
      '.tia-product__img{width:54px;height:54px;flex:0 0 54px;border-radius:8px;background:#F7F8FA;object-fit:contain;display:block}',
      '.tia-product__body{min-width:0;flex:1}',
      '.tia-product__brand{font-size:9px;text-transform:uppercase;letter-spacing:.05em;color:var(--tia-muted);line-height:1.3}',
      '.tia-product__title{font-size:11.5px;font-weight:700;line-height:1.35;margin-top:2px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}',
      '.tia-product__foot{display:flex;align-items:center;justify-content:space-between;gap:6px;margin-top:5px}',
      '.tia-product__price{font-size:11.5px;font-weight:800;display:flex;align-items:center;gap:5px;flex-wrap:wrap}',
      '.tia-product__old{font-size:9.5px;color:#98A2B3;text-decoration:line-through;font-weight:500}',
      '.tia-product__final{color:#B42318}',
      '.tia-product__benefit{font-size:9.5px;font-weight:800;color:#15803d;margin-top:3px}',
      '.tia-product__stock{font-size:9px;font-weight:700;color:#15803d;background:#ECFDF3;border-radius:999px;padding:2px 6px;white-space:nowrap}',
      '.tia-product__stock.is-out{color:#B42318;background:#FEF3F2}',
      '.tia-product__arrow{font-size:9px;font-weight:800;color:var(--tia-brand);margin-left:2px;white-space:nowrap}',
      '.tia-product-empty{width:54px;height:54px;flex:0 0 54px;border-radius:8px;background:#F7F8FA;display:flex;align-items:center;justify-content:center;font-size:10px;color:var(--tia-muted)}',
      '.tia-typing .tia-bubble{display:flex;gap:4px;align-items:center;min-width:52px}',
      '.tia-typing i{width:6px;height:6px;border-radius:50%;background:#9CA3AF;display:block;animation:tiaDot 1s infinite ease-in-out}',
      '.tia-typing i:nth-child(2){animation-delay:.15s}.tia-typing i:nth-child(3){animation-delay:.3s}',
      '.tia-typing .tia-progress{display:flex;flex-direction:column;gap:6px;min-width:190px}',
      '.tia-progress__label{font-size:11px;font-weight:700;color:#4B5563}',
      '.tia-progress__track{height:5px;border-radius:99px;background:#EEF0F4;overflow:hidden}',
      '.tia-progress__bar{height:100%;border-radius:99px;background:var(--tia-brand);transition:width .35s ease}',
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

  function equalizerSvg(className) {
    // Misma silueta de la «O» ecualizada del logo SONIKA: 5 barras
    // verticales con alturas simétricas que forman un círculo.
    return '<svg class="' + className + '" viewBox="0 0 100 100" aria-hidden="true" focusable="false">'
      + '<g class="tia-orb__bars">'
      + '<rect x="0" y="32.3" width="15.3" height="37.4" rx="7.65"></rect>'
      + '<rect x="19.7" y="17.5" width="15.3" height="66.5" rx="7.65"></rect>'
      + '<rect x="40.1" y="0" width="17.8" height="100" rx="8.9"></rect>'
      + '<rect x="63.1" y="17.5" width="15.3" height="66.5" rx="7.65"></rect>'
      + '<rect x="83.4" y="32.3" width="15.3" height="38.1" rx="7.65"></rect>'
      + '</g></svg>';
  }

  function makeBubble(disabled) {
    var h = makeHost('tia-host--fab');
    var btn = el('button', 'tia-fab tia-orb' + (disabled ? ' tia-fab--disabled tia-orb--disabled' : ' tia-orb--idle'));
    btn.type = 'button';
    btn.setAttribute('aria-label', disabled ? 'TIA está disponible en el chat de esta página' : 'Abrir chat de TIA');
    if (disabled) {
      btn.disabled = true;
      btn.setAttribute('aria-disabled', 'true');
      btn.title = 'Ya estás en el Centro de Soporte';
    }
    btn.innerHTML = '<span class="tia-orb__surface" aria-hidden="true"></span>'
      + '<span class="tia-orb__ring" aria-hidden="true"></span>'
      + equalizerSvg('tia-orb__equalizer')
      + '<span class="tia-orb__label" aria-hidden="true">TIA</span>';
    var halo = el('span', 'tia-orb__halo');
    halo.setAttribute('aria-hidden', 'true');
    h.root.appendChild(btn);
    h.root.appendChild(halo);
    document.body.appendChild(h.host);
    return { host: h.host, root: h.root, btn: btn, halo: halo };
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
    var headOrb = el('span', 'tia-av tia-av--orb');
    headOrb.setAttribute('aria-hidden', 'true');
    headOrb.innerHTML = '<span class="tia-av__ring"></span>'
      + equalizerSvg('tia-av__eq')
      + '<span class="tia-av__label">TIA</span>';
    head.appendChild(headOrb);
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
      orbAvatar: headOrb,
      embedded: embedded
    };

    function scroll() {
      messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function money(cents) {
      cents = Number(cents || 0);
      try {
        return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(cents / 100);
      } catch (e) {
        return '$' + Math.round(cents / 100).toLocaleString('es-CO');
      }
    }

    function renderCards(cards) {
      var wrap = el('div', 'tia-products');
      (Array.isArray(cards) ? cards : []).slice(0, 4).forEach(function (c) {
        var a = el('a', 'tia-product');
        a.href = safeUrl(c.url, '/tienda');
        a.target = '_blank';
        a.rel = 'noopener noreferrer';
        a.setAttribute('aria-label', String(c.title || 'Ver producto'));
        if (c.image) {
          var img = el('img', 'tia-product__img');
          img.src = safeUrl(c.image, '');
          img.alt = String(c.title || 'Producto SONIKA');
          img.loading = 'lazy';
          img.decoding = 'async';
          img.referrerPolicy = 'no-referrer';
          img.onerror = function () {
            img.style.display = 'none';
            if (!a.querySelector('.tia-product-empty')) a.insertBefore(el('span', 'tia-product-empty', 'TIA'), a.firstChild);
          };
          a.appendChild(img);
        } else {
          a.appendChild(el('span', 'tia-product-empty', 'TIA'));
        }
        var body = el('div', 'tia-product__body');
        if (c.brand) body.appendChild(el('div', 'tia-product__brand', String(c.brand)));
        body.appendChild(el('div', 'tia-product__title', String(c.title || 'Producto')));
        var foot = el('div', 'tia-product__foot');
        var price = el('span', 'tia-product__price');
        if (c.discount_cents > 0 && c.final_price_cents > 0) {
          price.appendChild(el('span', 'tia-product__old', money(c.price_cents)));
          price.appendChild(el('span', 'tia-product__final', money(c.final_price_cents)));
        } else {
          price.textContent = c.price_cents > 0 ? money(c.price_cents) : 'Ver precio';
        }
        foot.appendChild(price);
        var stock = el('span', 'tia-product__stock' + (c.available ? '' : ' is-out'), c.available ? 'Disponible' : 'Agotado');
        foot.appendChild(stock);
        body.appendChild(foot);
        if (c.benefit) body.appendChild(el('div', 'tia-product__benefit', String(c.benefit)));
        a.appendChild(body);
        a.appendChild(el('span', 'tia-product__arrow', c.action === 'buy' ? 'Comprar' : 'Ver'));
        a.addEventListener('click', function () { tiaEvent('card_click', c.ref || c.url || '', 0, ''); });
        wrap.appendChild(a);
      });
      return wrap;
    }

    function sendFeedback(m, rating, reason) {
      if (!m || m.feedback || !m.interaction) return;
      m.feedback = rating;
      m.feedbackReasonOpen = false;
      save();
      render();
      try {
        fetch('/api/v1/public/tia/feedback', {
          method: 'POST',
          credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
          body: JSON.stringify({ interaction_id: m.interaction, rating: rating, reason: reason || '' })
        }).catch(function () {});
      } catch (e) {}
    }

    function renderCompare(data) {
      var wrap = el('div', 'tia-compare');
      var products = (data.products || []).slice(0, 3);
      var columns = Math.max(2, products.length);
      var head = el('div', 'tia-compare__head');
      head.style.gridTemplateColumns = 'repeat(' + columns + ',1fr)';
      products.forEach(function (p) {
        var cell = el('a', 'tia-compare__product');
        cell.href = safeUrl(p.url || '#', '#');
        if (/^https?:\/\//i.test(cell.href)) { cell.target = '_blank'; cell.rel = 'noopener noreferrer'; }
        if (p.image) {
          var img = document.createElement('img');
          img.src = safeUrl(p.image, '');
          img.alt = '';
          img.loading = 'lazy';
          img.addEventListener('error', function () { img.style.display = 'none'; });
          cell.appendChild(img);
        }
        cell.appendChild(el('b', '', String(p.title || '')));
        if (p.brand) cell.appendChild(el('small', '', String(p.brand)));
        head.appendChild(cell);
      });
      wrap.appendChild(head);
      var rows = el('div', 'tia-compare__rows');
      (data.rows || []).forEach(function (r) {
        var row = el('div', 'tia-compare__row');
        row.appendChild(el('div', 'tia-compare__label', String(r.label || '')));
        if (r.values && r.values.length) {
          row.style.gridTemplateColumns = 'repeat(' + Math.max(2, r.values.length) + ',1fr)';
          r.values.forEach(function (value) { row.appendChild(el('div', '', String(value || '—'))); });
        } else {
          row.appendChild(el('div', '', String(r.a || '—')));
          row.appendChild(el('div', '', String(r.b || '—')));
        }
        rows.appendChild(row);
      });
      wrap.appendChild(rows);
      if (data.note) wrap.appendChild(el('div', 'tia-compare__note', String(data.note)));
      return wrap;
    }

    function renderImageView(data) {
      if (!data || !data.url) return null;
      var link = el('a', 'tia-image-link');
      link.href = safeUrl(data.product_url, '#');
      if (/^https?:\/\//i.test(link.href)) { link.target = '_blank'; link.rel = 'noopener noreferrer'; }
      var img = document.createElement('img');
      img.src = safeUrl(data.url, '');
      img.alt = String(data.alt || data.title || 'Producto SONIKA');
      img.loading = 'lazy';
      img.decoding = 'async';
      img.referrerPolicy = 'no-referrer';
      link.appendChild(img);
      link.appendChild(el('span', 'tia-image-caption', 'Abrir ficha de ' + String(data.title || 'el producto')));
      return link;
    }

    function appendTextWithLinks(node, text) {
      String(text || '').split(/(https?:\/\/[^\s<>"']+)/g).forEach(function (part) {
        if (/^https?:\/\//i.test(part)) {
          var a = el('a', '', part);
          a.href = safeUrl(part, '#');
          a.target = '_blank';
          a.rel = 'noopener noreferrer';
          node.appendChild(a);
        } else if (part !== '') {
          node.appendChild(document.createTextNode(part));
        }
      });
    }

    function renderMessages() {
      messagesEl.textContent = '';
      state.messages.forEach(function (m) {
        var row = el('div', 'tia-msg tia-msg--' + (m.role === 'user' ? 'user' : (m.role === 'system' ? 'system' : 'tia')));
        if (m.role !== 'user') row.appendChild(el('span', 'tia-av', m.role === 'system' ? '!' : 'TIA'));
        var bubble = el('div', 'tia-bubble');
        appendTextWithLinks(bubble, m.text);
        if (m.image_view && m.image_view.url) bubble.appendChild(renderImageView(m.image_view));
        else if (m.comparison && m.comparison.products && m.comparison.products.length) bubble.appendChild(renderCompare(m.comparison));
        else if (m.cards && m.cards.length) bubble.appendChild(renderCards(m.cards));
        if (m.role === 'tia' && m.handoff && m.handoff.summary) {
          var hb = el('div', 'tia-handoff');
          hb.appendChild(el('div', 'tia-handoff__title', 'Resumen para la persona que te atiende'));
          hb.appendChild(el('div', 'tia-handoff__text', String(m.handoff.summary)));
          var ha = el('div', 'tia-handoff__actions');
          var copy = el('button', '', 'Copiar resumen');
          copy.type = 'button';
          copy.addEventListener('click', function () {
            try {
              navigator.clipboard.writeText(String(m.handoff.summary)).then(function () {
                copy.textContent = 'Copiado';
              });
            } catch (e) {}
          });
          ha.appendChild(copy);
          if (m.handoff.url) {
            var open = el('a', '', String(m.handoff.label || 'Abrir canal'));
            open.href = safeUrl(m.handoff.url, '#');
            if (/^https?:\/\//i.test(open.href)) {
              open.target = '_blank';
              open.rel = 'noopener noreferrer';
            }
            ha.appendChild(open);
          }
          hb.appendChild(ha);
          bubble.appendChild(hb);
        }
        if (m.role === 'tia' && m.interaction) {
          var fb = el('div', 'tia-feedback');
          if (m.feedback) {
            fb.appendChild(el('span', 'tia-feedback__done', m.feedback === 'up' ? 'Gracias por tu valoración.' : 'Gracias. Lo revisaremos.'));
          } else if (m.feedbackReasonOpen) {
            fb.appendChild(el('span', '', '¿Qué falló?'));
            ['No me entendió', 'Precio', 'Stock', 'No era lo que buscaba', 'Quiero una persona'].forEach(function (reason) {
              var rb = el('button', '', reason);
              rb.type = 'button';
              rb.addEventListener('click', function () { sendFeedback(m, 'down', reason); });
              fb.appendChild(rb);
            });
          } else {
            fb.appendChild(el('span', '', '¿Te sirvió?'));
            var up = el('button', '', 'Útil');
            up.type = 'button';
            up.setAttribute('aria-label', 'Respuesta útil');
            up.addEventListener('click', function () { sendFeedback(m, 'up'); });
            var down = el('button', '', 'No útil');
            down.type = 'button';
            down.setAttribute('aria-label', 'Respuesta no útil');
            down.addEventListener('click', function () { m.feedbackReasonOpen = true; save(); render(); });
            fb.appendChild(up);
            fb.appendChild(down);
          }
          bubble.appendChild(fb);
        }
        row.appendChild(bubble);
        messagesEl.appendChild(row);
      });
      if (state.typing) {
        var t = el('div', 'tia-msg tia-msg--tia tia-typing');
        t.appendChild(el('span', 'tia-av', 'TIA'));
        var b = el('div', 'tia-bubble');
        if (state.progress > 0) {
          b.className = 'tia-bubble tia-progress';
          var label = el('div', 'tia-progress__label', 'Revisando tu solicitud... ' + state.progress + '%');
          label.setAttribute('role', 'status');
          label.setAttribute('aria-live', 'polite');
          var track = el('div', 'tia-progress__track');
          track.setAttribute('aria-hidden', 'true');
          var bar = el('div', 'tia-progress__bar');
          bar.style.width = state.progress + '%';
          track.appendChild(bar);
          b.appendChild(label);
          b.appendChild(track);
        } else {
          b.appendChild(el('i'));
          b.appendChild(el('i'));
          b.appendChild(el('i'));
        }
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

    function clearProgress() {
      if (state.progressTimer) clearInterval(state.progressTimer);
      state.progressTimer = null;
      state.progress = 0;
    }

    function startProgress(text) {
      clearProgress();
      var research = /compar|diferenc|caracter|especific|ficha|material|tama|incluye|investig|medida|acabado/i.test(String(text || ''));
      state.progressStarted = Date.now();
      var begin = function () {
        if (!state.typing) return;
        state.progress = research ? 8 : 4;
        render();
        state.progressTimer = setInterval(function () {
          if (!state.typing) { clearProgress(); return; }
          state.progress = Math.min(92, Math.max(state.progress + 4, 8 + Math.round((Date.now() - state.progressStarted) / 900 * 18)));
          render();
        }, 550);
      };
      if (research) begin();
      else state.progressTimer = setTimeout(begin, 450);
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
      startProgress(text);
      input.value = '';
      autoGrow();
      save();
      render();
      setOrbState('thinking');
      fetch(cfg.api, {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify((function () {
          var exp = tiaExperiment();
          var body = { message: text, page: location.pathname, locale: cfg.locale };
          if (exp) {
            body.experiment_key = exp.key;
            body.experiment_variant = exp.variant;
          }
          return body;
        })())
      }).then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          return { res: res, data: data };
        });
      }).then(function (out) {
        clearProgress();
        state.typing = false;
        if (!out.res.ok || !out.data || !out.data.ok) {
          var msg = (out.data && out.data.message) || 'No pudimos conectar con TIA. Usa WhatsApp o el formulario de soporte.';
          state.messages.push({ role: 'system', text: msg });
          if (out.data && out.data.handoff && out.data.handoff.url) {
            if (out.data.handoff.kind === 'form') cfg.form = out.data.handoff.url;
            else if (out.data.handoff.kind === 'whatsapp') cfg.whatsapp = out.data.handoff.url;
          }
          setOrbState('idle');
        } else {
          if (out.data.interaction_id) {
            try { sessionStorage.setItem('wwi_tia_interaction', String(out.data.interaction_id)); } catch (e) {}
          }
          state.messages.push({ role: 'tia', text: String(out.data.reply || ''), cards: Array.isArray(out.data.cards) ? out.data.cards : [], comparison: out.data.comparison || null, image_view: out.data.image_view || null, interaction: out.data.interaction_id ? String(out.data.interaction_id) : '', handoff: out.data.handoff && out.data.handoff.summary ? out.data.handoff : null });
          if (out.data.handoff && out.data.handoff.url) {
            if (out.data.handoff.kind === 'form') cfg.form = out.data.handoff.url;
            else if (out.data.handoff.kind === 'whatsapp') cfg.whatsapp = out.data.handoff.url;
          }
          if (floatingOpen || (chat && chat.embedded)) setOrbState('speaking');
          else setOrbState('idle');
        }
      }).catch(function () {
        clearProgress();
        state.typing = false;
        state.messages.push({ role: 'system', text: 'No pudimos conectar. Revisa tu conexión o usa WhatsApp/formulario.' });
        setOrbState('idle');
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

  function productCoach() {
    var btn = document.querySelector('[data-pdp-add]');
    if (!btn || location.pathname.indexOf('/producto/') !== 0) return;
    var handle = btn.getAttribute('data-add') || '';
    if (!handle) return;
    var seenKey = 'wwi_tia_coach_' + handle;
    try { if (sessionStorage.getItem(seenKey)) return; } catch (e) {}
    var title = btn.getAttribute('data-title') || document.title;
    var price = parseFloat(btn.getAttribute('data-price') || '0');
    var image = btn.getAttribute('data-img') || '';
    var url = btn.getAttribute('data-url') || ('/producto/' + encodeURIComponent(handle));
    var available = btn.getAttribute('data-available') !== '0';
    var style = document.createElement('style');
    style.id = 'tia-coach-css';
    style.textContent = '#tia-pdp-coach{position:fixed;left:18px;bottom:92px;z-index:9000;width:min(350px,calc(100vw - 24px));background:#fff;color:#111;border:1px solid #E8EAEF;border-radius:16px;box-shadow:0 18px 50px rgba(16,24,40,.18);padding:12px;font-family:Sora,Inter,system-ui,sans-serif;display:flex;gap:10px;align-items:flex-start;animation:tiaCoachIn .28s ease-out}#tia-pdp-coach img{width:66px;height:66px;object-fit:contain;border-radius:10px;background:#F7F8FA;flex:0 0 66px}#tia-pdp-coach .tc-body{min-width:0;flex:1}#tia-pdp-coach .tc-brand{font-size:9px;font-weight:800;letter-spacing:.09em;color:#F20D16;text-transform:uppercase}#tia-pdp-coach .tc-title{font-size:11.5px;font-weight:800;line-height:1.35;margin-top:2px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}#tia-pdp-coach .tc-text{font-size:11px;color:#667085;line-height:1.45;margin-top:4px}#tia-pdp-coach .tc-price{font-size:12px;font-weight:800;margin-top:5px}#tia-pdp-coach .tc-actions{display:flex;align-items:center;gap:8px;margin-top:8px;flex-wrap:wrap}#tia-pdp-coach .tc-cta{display:inline-flex;align-items:center;justify-content:center;background:#F20D16;color:#fff;text-decoration:none;border-radius:8px;padding:7px 11px;font-size:10.5px;font-weight:800;border:0;cursor:pointer}#tia-pdp-coach .tc-link{font-size:10px;color:#667085;text-decoration:underline}#tia-pdp-coach .tc-close{position:absolute;top:5px;right:7px;border:0;background:transparent;color:#98A2B3;font-size:17px;line-height:1;cursor:pointer;padding:3px}#tia-pdp-coach .tc-avatar{width:22px;height:22px;border-radius:7px;background:#111;color:#fff;font-size:8px;font-weight:800;display:grid;place-items:center;flex:0 0 22px}@keyframes tiaCoachIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}@keyframes tiaAddPulse{0%,100%{box-shadow:0 0 0 0 rgba(242,13,22,.5),0 10px 26px rgba(242,13,22,.3)}50%{box-shadow:0 0 0 9px rgba(242,13,22,0),0 14px 34px rgba(242,13,22,.42)}}.tia-add-pulse{animation:tiaAddPulse 1.25s ease-in-out 4!important;position:relative;z-index:2}@media(max-width:640px){#tia-pdp-coach{left:12px;right:12px;bottom:86px;width:auto}}@media(prefers-reduced-motion:reduce){#tia-pdp-coach{animation:none}.tia-add-pulse{animation:none!important}}';
    if (!document.getElementById('tia-coach-css')) document.head.appendChild(style);
    var card = el('aside', 'tia-pdp-coach');
    card.id = 'tia-pdp-coach';
    card.setAttribute('role', 'complementary');
    card.setAttribute('aria-label', 'TIA te ayuda a completar la compra');
    if (image) {
      var img = el('img');
      img.src = safeUrl(image, '');
      img.alt = title;
      img.loading = 'lazy';
      card.appendChild(img);
    }
    var body = el('div', 'tc-body');
    var brand = el('div', 'tc-brand');
    brand.appendChild(el('span', 'tc-avatar', 'TIA'));
    brand.appendChild(document.createTextNode(' TIA te acompaña'));
    body.appendChild(brand);
    body.appendChild(el('div', 'tc-title', title));
    body.appendChild(el('div', 'tc-text', available ? 'Está disponible. Agrégalo al carrito y paga con Bre-B para aprovechar el descuento.' : 'Por ahora aparece agotado. Puedo mostrarte alternativas.'));
    if (price > 0) body.appendChild(el('div', 'tc-price', '$' + Math.round(price).toLocaleString('es-CO')));
    var actions = el('div', 'tc-actions');
    var cta = el('a', 'tc-cta', available ? 'Agregar al carrito' : 'Ver alternativas');
    cta.href = available ? '#' : '/tienda';
    cta.setAttribute('role', 'button');
    if (available) {
      cta.addEventListener('click', function (e) {
        e.preventDefault();
        if (cta.getAttribute('data-added') === '1') { window.location.href = '/carrito'; return; }
        btn.click();
        cta.setAttribute('data-added', '1');
        cta.textContent = 'Ver carrito';
        tiaEvent('add_to_cart', handle, 0, '');
      });
    }
    var link = el('a', 'tc-link', 'Ver ficha');
    link.href = safeUrl(url, '/tienda');
    actions.appendChild(cta);
    actions.appendChild(link);
    body.appendChild(actions);
    card.appendChild(body);
    var close = el('button', 'tc-close', '×');
    close.type = 'button';
    close.setAttribute('aria-label', 'Cerrar ayuda de TIA');
    close.addEventListener('click', function () {
      card.remove();
      try { sessionStorage.setItem(seenKey, '1'); } catch (e) {}
      btn.classList.remove('tia-add-pulse');
    });
    card.appendChild(close);
    document.body.appendChild(card);
    try { sessionStorage.setItem(seenKey, '1'); } catch (e) {}
    if (available) {
      btn.classList.add('tia-add-pulse');
      setTimeout(function () { btn.classList.remove('tia-add-pulse'); }, 12000);
      btn.addEventListener('click', function () { btn.classList.remove('tia-add-pulse'); }, { once: true });
      window.addEventListener('sonika:cart-add', function (ev) {
        var d = ev.detail || {};
        if ((d.handle || '') === handle && cta.getAttribute('data-added') !== '1') {
          cta.setAttribute('data-added', '1');
          cta.textContent = 'Ver carrito';
        }
      });
    }
  }

  function proactiveNudge() {
    if (floatingOpen || !wantFloating) return;
    try { if (sessionStorage.getItem('wwi_tia_nudge_seen')) return; } catch (e) {}
    var path = location.pathname || '/';
    if (path.indexOf('/producto/') === 0 || path.indexOf('/cuenta') === 0 || path.indexOf('/pqrf') === 0 || path.indexOf('/soporte') === 0) return;
    var q = new URLSearchParams(location.search).get('q') || '';
    fetch('/api/v1/public/tia/nudge?page=' + encodeURIComponent(path) + '&q=' + encodeURIComponent(q))
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok || !data.show || (floatingOpen && chat)) return;
        if (document.getElementById('tia-nudge')) return;
        try { sessionStorage.setItem('wwi_tia_nudge_seen', '1'); } catch (e) {}
        var style = document.createElement('style');
        style.id = 'tia-nudge-css';
        style.textContent = '#tia-nudge{position:fixed;left:18px;bottom:92px;z-index:8999;width:min(340px,calc(100vw - 24px));background:#fff;color:#111;border:1px solid #E8EAEF;border-radius:16px;box-shadow:0 18px 50px rgba(16,24,40,.16);padding:12px;font-family:Sora,Inter,system-ui,sans-serif;animation:tiaNudgeIn .25s ease-out}#tia-nudge .tn-head{display:flex;align-items:center;gap:6px;font-size:9px;font-weight:800;letter-spacing:.08em;color:#F20D16;text-transform:uppercase;padding-right:18px}#tia-nudge .tn-av{width:20px;height:20px;border-radius:6px;background:#111;color:#fff;font-size:7px;display:grid;place-items:center}#tia-nudge .tn-msg{font-size:11.5px;color:#475467;line-height:1.45;margin-top:5px}#tia-nudge .tn-list{display:flex;flex-direction:column;gap:5px;margin-top:8px}#tia-nudge .tn-item{display:flex;gap:7px;align-items:center;text-decoration:none;color:#111;border:1px solid #F0F2F5;border-radius:9px;padding:5px}#tia-nudge .tn-item:hover{border-color:#F20D16}#tia-nudge .tn-item img{width:40px;height:40px;object-fit:contain;border-radius:7px;background:#F7F8FA;flex:0 0 40px}#tia-nudge .tn-item b{display:block;font-size:10px;line-height:1.3;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}#tia-nudge .tn-item small{display:block;font-size:9.5px;color:#667085;margin-top:2px}#tia-nudge .tn-foot{display:flex;gap:7px;align-items:center;margin-top:9px}#tia-nudge .tn-talk{background:#F20D16;color:#fff;border:0;border-radius:8px;padding:6px 10px;font:800 10px Sora,Inter,sans-serif;cursor:pointer}#tia-nudge .tn-close{position:absolute;top:5px;right:7px;border:0;background:transparent;color:#98A2B3;font-size:16px;cursor:pointer;padding:2px}@keyframes tiaNudgeIn{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:none}}@media(max-width:640px){#tia-nudge{left:12px;right:12px;bottom:86px;width:auto}}@media(prefers-reduced-motion:reduce){#tia-nudge{animation:none}}';
        if (!document.getElementById('tia-nudge-css')) document.head.appendChild(style);
        var card = el('aside');
        card.id = 'tia-nudge';
        card.setAttribute('role', 'complementary');
        card.setAttribute('aria-label', 'Recomendación de TIA');
        var head = el('div', 'tn-head');
        head.appendChild(el('span', 'tn-av', 'TIA'));
        head.appendChild(document.createTextNode(' TIA puede ayudarte'));
        card.appendChild(head);
        card.appendChild(el('div', 'tn-msg', String(data.message || '¿Te ayudo a elegir?')));
        var list = el('div', 'tn-list');
        (data.cards || []).slice(0, 3).forEach(function (c) {
          var a = el('a', 'tn-item');
          a.href = safeUrl(c.url, '/tienda');
          if (c.image) {
            var img = el('img');
            img.src = safeUrl(c.image, '');
            img.alt = c.title || 'Producto';
            img.loading = 'lazy';
            a.appendChild(img);
          }
          var body = el('span');
          body.appendChild(el('b', '', String(c.title || 'Producto')));
          body.appendChild(el('small', '', c.price_cents > 0 ? money(c.price_cents) : 'Ver precio'));
          a.appendChild(body);
          a.addEventListener('click', function () {
            tiaRememberExperiment(data.experiment);
            tiaEvent('card_click', c.ref || c.url || '', 0, '');
            tiaExperimentEvent('nudge_product_click');
          });
          list.appendChild(a);
        });
        card.appendChild(list);
        var foot = el('div', 'tn-foot');
        var talk = el('button', 'tn-talk', String(data.cta || 'Hablar con TIA'));
        talk.type = 'button';
        talk.addEventListener('click', function () {
          tiaRememberExperiment(data.experiment);
          tiaExperimentEvent('nudge_click');
          card.remove();
          open();
        });
        foot.appendChild(talk);
        card.appendChild(foot);
        var close = el('button', 'tn-close', '×');
        close.type = 'button';
        close.setAttribute('aria-label', 'Cerrar recomendación de TIA');
        close.addEventListener('click', function () { card.remove(); });
        card.appendChild(close);
        document.body.appendChild(card);
      }).catch(function () {});
  }

  function money(cents) {
    try { return new Intl.NumberFormat('es-CO', { style: 'currency', currency: 'COP', maximumFractionDigits: 0 }).format(Number(cents || 0) / 100); } catch (e) { return '$' + Math.round(Number(cents || 0) / 100).toLocaleString('es-CO'); }
  }

  var bubble = null;
  var chat = null;
  var floatingOpen = false;
  var lastFocus = null;
  var orbState = '';
  var orbTimer = null;
  var orbStateTimer = null;
  var pendingOrbState = '';
  var equalizeMs = Math.max(8000, Math.min(12000, parseInt(attr('data-tia-equalize-interval', '9000'), 10) || 9000));
  var reducedMotion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : { matches: false };
  var orbStates = ['idle', 'equalizing', 'listening', 'thinking', 'speaking', 'open', 'disabled'];

  function canAnimateOrb() {
    return !!bubble && !!bubble.btn && !bubble.btn.disabled && !floatingOpen
      && !(chat && chat.embedded) && document.visibilityState === 'visible'
      && !reducedMotion.matches;
  }

  function stopOrbTimer() {
    if (orbTimer) { clearTimeout(orbTimer); orbTimer = null; }
  }

  function scheduleOrb() {
    stopOrbTimer();
    if (!canAnimateOrb() || orbState !== 'idle') return;
    orbTimer = setTimeout(function () {
      orbTimer = null;
      if (!canAnimateOrb() || orbState !== 'idle') return;
      setOrbState('equalizing');
      orbTimer = setTimeout(function () {
        orbTimer = null;
        if (orbState === 'equalizing') setOrbState('idle');
      }, 1900);
    }, equalizeMs);
  }

  function setOrbState(next) {
    if (orbStates.indexOf(next) < 0) next = 'idle';
    var changed = next !== orbState;
    if (orbStateTimer) { clearTimeout(orbStateTimer); orbStateTimer = null; }
    orbState = next;
    if (bubble && bubble.btn) {
      orbStates.forEach(function (s) { bubble.btn.classList.remove('tia-orb--' + s); });
      bubble.btn.classList.add('tia-orb--' + next);
      bubble.btn.setAttribute('data-tia-state', next);
      if (next === 'thinking') bubble.btn.setAttribute('aria-busy', 'true');
      else bubble.btn.removeAttribute('aria-busy');
    }
    if (chat && chat.orbAvatar) {
      orbStates.forEach(function (s) { chat.orbAvatar.classList.remove('tia-av--' + s); });
      chat.orbAvatar.classList.add('tia-av--' + next);
      chat.orbAvatar.setAttribute('data-tia-state', next);
    }
    if (next === 'idle') {
      if (changed) scheduleOrb();
    } else {
      stopOrbTimer();
    }
    if (next === 'speaking') {
      orbStateTimer = setTimeout(function () {
        orbStateTimer = null;
        if (orbState === 'speaking') setOrbState('idle');
      }, 1600);
    }
  }

  function exposeOrbState(state) {
    if (typeof state !== 'string' || state === '') return;
    if (!bubble && !chat) pendingOrbState = state;
    setOrbState(state);
  }

  window.SONIKA_TIA_ORB = {
    setState: exposeOrbState,
    getState: function () { return orbState; }
  };
  window.addEventListener('sonika:tia-state', function (e) {
    if (e && e.detail && e.detail.state) exposeOrbState(e.detail.state);
  });

  try {
    if (reducedMotion.addEventListener) {
      reducedMotion.addEventListener('change', function () {
        if (reducedMotion.matches) {
          stopOrbTimer();
          if (orbState === 'equalizing') setOrbState('idle');
        } else if (orbState === 'idle') {
          scheduleOrb();
        }
      });
    }
  } catch (e) {}

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'hidden') {
      stopOrbTimer();
      if (orbState === 'equalizing') setOrbState('idle');
    } else if (orbState === 'idle') {
      scheduleOrb();
    }
  });

  window.addEventListener('pagehide', function () {
    stopOrbTimer();
    if (orbStateTimer) { clearTimeout(orbStateTimer); orbStateTimer = null; }
  }, { once: true });

  function open() {
    if (!chat || chat.embedded) return;
    floatingOpen = true;
    chat.panel.classList.add('is-open');
    chat.panel.setAttribute('aria-hidden', 'false');
    if (bubble) {
      bubble.btn.setAttribute('aria-expanded', 'true');
      bubble.btn.classList.add('is-hidden');
      lastFocus = bubble.btn;
    }
    setOrbState('open');
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
      bubble.btn.classList.remove('is-hidden');
      bubble.btn.setAttribute('aria-expanded', 'false');
      bubble.btn.focus();
    }
    setOrbState('idle');
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
      setOrbState('idle');
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
      var initialOrbState = pendingOrbState || (disabledPath ? 'disabled' : 'idle');
      pendingOrbState = '';
      setOrbState(initialOrbState);
      if (initialOrbState === 'idle') scheduleOrb();
    } else if (disabledPath) {
      bubble = makeBubble(true);
      setOrbState('disabled');
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && floatingOpen) close();
    });
    setTimeout(productCoach, 2200);
    setTimeout(proactiveNudge, 7000);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }
})();
