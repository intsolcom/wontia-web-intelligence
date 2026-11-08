<?php
/**
 * Guía visual del panel del artista: tarjeta de progreso, wizard de primeros
 * pasos y tooltips en lenguaje sencillo.
 *
 * Variables esperadas desde router.php:
 * $__affPP, $__wizardSteps, $__wizDone, $__wizTotal, $__wizPct,
 * $__wizNextIndex, $__wizNextTitle, $__wizStatus, $__isDemo.
 */
$__wizCfg = [
    'id' => (int)($__affPP['id'] ?? 0),
    'status' => (string)($__wizStatus ?? ''),
    'isDemo' => !empty($__isDemo),
    'pct' => (int)$__wizPct,
    'nextIndex' => (int)$__wizNextIndex,
    'steps' => array_map(static function (array $s): array {
        return [
            'key' => (string)($s['key'] ?? ''),
            'title' => (string)($s['title'] ?? ''),
            'desc' => (string)($s['desc'] ?? ''),
            'why' => (string)($s['why'] ?? ''),
            'tip' => (string)($s['tip'] ?? ''),
            'done' => !empty($s['done']),
            'action' => (string)($s['action'] ?? ''),
        ];
    }, (array)$__wizardSteps),
];
?>
<style>
.ahp-guide{position:relative;display:flex;gap:16px;align-items:center;flex-wrap:wrap;background:linear-gradient(135deg,#f6fbf9,#fff 58%,#f5f3ff);border:1px solid #dcefe7;border-radius:18px;padding:16px 18px;margin:0 0 18px;box-shadow:0 10px 28px rgba(16,24,40,.06);overflow:hidden}
.ahp-guide:before{content:"";position:absolute;right:-40px;top:-50px;width:180px;height:180px;border-radius:50%;background:radial-gradient(circle,rgba(124,92,255,.16),transparent 70%);pointer-events:none}
.ahp-guide__ring{--p:0;width:78px;height:78px;border-radius:50%;background:conic-gradient(#0d9f72 calc(var(--p)*1%),#e7f1ec 0);display:grid;place-items:center;flex:none;position:relative}
.ahp-guide__ring:before{content:"";position:absolute;inset:7px;border-radius:50%;background:#fff}
.ahp-guide__ring b{position:relative;font-size:16px;color:#0d7a58;font-weight:900}
.ahp-guide__body{flex:1;min-width:220px;position:relative;z-index:1}
.ahp-guide__eyebrow{font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#7c5cff}
.ahp-guide h2{font-size:18px;margin:3px 0;color:#101d3a}
.ahp-guide p{font-size:12.5px;color:#64748b;line-height:1.5;margin:0}
.ahp-guide__bar{height:8px;border-radius:999px;background:#e8f1ed;overflow:hidden;margin:10px 0 5px;max-width:420px}
.ahp-guide__bar span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#0d9f72,#7c5cff);transition:width .5s ease}
.ahp-guide__meta{font-size:11.5px;color:#94a3b8;font-weight:700}
.ahp-guide__actions{display:flex;flex-direction:column;gap:5px;align-items:stretch;position:relative;z-index:1}
.ahp-guide__cta{border:0;border-radius:11px;background:linear-gradient(135deg,#0d9f72,#0b7a5a);color:#fff;padding:10px 14px;font:inherit;font-size:12.5px;font-weight:900;cursor:pointer;box-shadow:0 8px 18px rgba(13,159,114,.25);transition:transform .2s,box-shadow .2s}
.ahp-guide__cta:hover{transform:translateY(-1px);box-shadow:0 12px 24px rgba(13,159,114,.32)}
.ahp-guide__link{border:0;background:transparent;color:#64748b;font:inherit;font-size:11.5px;font-weight:800;cursor:pointer;text-decoration:underline;padding:4px}
.ahp-guide.is-complete{border-color:#f5d98a;background:linear-gradient(135deg,#fffaf0,#fff 58%,#f0fdf4)}
.ahp-guide.is-complete .ahp-guide__ring{background:conic-gradient(#f59e0b calc(var(--p)*1%),#f3e8d3 0)}
.ahp-guide.is-complete .ahp-guide__ring b{color:#b45309}
.ahp-guide.is-complete .ahp-guide__eyebrow{color:#b45309}
.ahp-guide__btn{display:inline-flex;align-items:center;gap:5px;border:1px solid #cfe3db;background:#f0fdf4;color:#0d7a58;border-radius:999px;padding:6px 11px;font:inherit;font-size:11.5px;font-weight:900;cursor:pointer;margin-left:8px}
.ahp-guide__btn:hover{background:#dcfce7}
.ahp-wiz{position:fixed;inset:0;z-index:99999;display:grid;place-items:center;padding:18px}
.ahp-wiz[hidden]{display:none}
.ahp-wiz__backdrop{position:absolute;inset:0;background:rgba(6,10,24,.64);backdrop-filter:blur(4px)}
.ahp-wiz__panel{position:relative;width:min(700px,100%);max-height:min(88vh,780px);overflow:auto;background:#fff;border-radius:20px;padding:24px;box-shadow:0 30px 80px rgba(0,0,0,.45);font-family:Sora,system-ui,sans-serif;animation:ahpWizIn .25s ease}
.ahp-wiz__panel::-webkit-scrollbar{width:8px}.ahp-wiz__panel::-webkit-scrollbar-thumb{background:#dbe7e1;border-radius:999px}
@keyframes ahpWizIn{from{opacity:0;transform:translateY(14px) scale(.98)}to{opacity:1;transform:none}}
.ahp-wiz__x{position:absolute;top:12px;right:14px;width:36px;height:36px;border:0;border-radius:50%;background:#f1f5f9;color:#334155;font-size:20px;line-height:1;cursor:pointer}
.ahp-wiz__x:hover{background:#e2e8f0}
.ahp-wiz__eyebrow{font-size:10px;font-weight:900;letter-spacing:.14em;text-transform:uppercase;color:#7c5cff}
.ahp-wiz h2{font-size:22px;margin:4px 0 10px;color:#101d3a;padding-right:36px}
.ahp-wiz__progress{display:flex;align-items:center;gap:10px;margin-bottom:14px}
.ahp-wiz__bar{flex:1;height:8px;border-radius:999px;background:#eef2f0;overflow:hidden}
.ahp-wiz__bar span{display:block;height:100%;border-radius:999px;background:linear-gradient(90deg,#0d9f72,#7c5cff);transition:width .4s}
#ahp-wiz-count{font-size:11.5px;color:#64748b;font-weight:800;white-space:nowrap}
.ahp-wiz__dots{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:14px}
.ahp-wiz__dot{width:28px;height:28px;border-radius:50%;border:1px solid #dbe7e1;background:#fff;color:#64748b;font:inherit;font-size:11px;font-weight:900;cursor:pointer}
.ahp-wiz__dot:hover{border-color:#7c5cff;color:#4338ca}
.ahp-wiz__dot.is-current{border-color:#7c5cff;background:#eef2ff;color:#4338ca;box-shadow:0 0 0 3px rgba(124,92,255,.14)}
.ahp-wiz__dot.is-done{border-color:#0d9f72;background:#ecfdf5;color:#047857}
.ahp-wiz__step{display:flex;gap:14px;background:#f8fbfa;border:1px solid #e6f0eb;border-radius:16px;padding:16px}
.ahp-wiz__num{width:38px;height:38px;border-radius:12px;background:linear-gradient(135deg,#101d3a,#283a63);color:#fff;display:grid;place-items:center;font-size:16px;font-weight:900;flex:none}
.ahp-wiz__stepbody{min-width:0}
.ahp-wiz__stepbody h3{font-size:17px;margin:0 0 4px;color:#101d3a}
.ahp-wiz__stepbody p{font-size:13px;line-height:1.6;color:#51607a;margin:0 0 10px}
.ahp-wiz__why{background:#fff;border:1px dashed #dbe7e1;border-radius:10px;padding:9px 11px;font-size:12px;line-height:1.55;color:#51607a}
.ahp-wiz__why b{color:#0d7a58}
.ahp-wiz__hint{margin-top:8px;font-size:11.5px;line-height:1.55;color:#7c5cff;font-weight:700}
.ahp-wiz__done{margin-top:9px;font-size:12px;font-weight:900;color:#047857}
.ahp-wiz__foot{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:16px}
.ahp-wiz__ghost{border:1px solid #dbe7e1;background:#fff;color:#334155;border-radius:10px;padding:9px 13px;font:inherit;font-size:12px;font-weight:800;cursor:pointer}
.ahp-wiz__ghost:hover{background:#f6fbf9}
.ahp-wiz__ghost:disabled{opacity:.45;cursor:default}
.ahp-wiz__cta{border:0;border-radius:10px;background:linear-gradient(135deg,#0d9f72,#0b7a5a);color:#fff;padding:9px 15px;font:inherit;font-size:12px;font-weight:900;cursor:pointer}
.ahp-wiz__cta:disabled{opacity:.55;cursor:default}
.ahp-wiz__hide{margin-left:auto;border:0;background:transparent;color:#94a3b8;font:inherit;font-size:11px;font-weight:700;cursor:pointer;text-decoration:underline}
.ahp-i{display:inline-grid;place-items:center;width:16px;height:16px;border-radius:50%;border:1px solid #cbd5e1;background:#fff;color:#64748b;font-size:9.5px;font-weight:900;line-height:1;cursor:help;margin-left:6px;vertical-align:middle;padding:0;font-family:inherit}
.ahp-i:hover,.ahp-i:focus-visible{border-color:#0d7a58;color:#0d7a58;background:#f0fdf4;outline:none}
.ahp-tab[data-tip]::after{content:"i";display:inline-grid;place-items:center;width:15px;height:15px;margin-left:5px;border-radius:50%;border:1px solid rgba(100,116,139,.35);font-size:9px;font-weight:900;color:#64748b;font-style:normal;vertical-align:middle}
.ahp-tab[data-tip]:hover::after,.ahp-tab[data-tip]:focus-visible::after{border-color:#0d7a58;color:#0d7a58;background:#f0fdf4}
#ahp-tip{position:fixed;z-index:100001;max-width:300px;background:#0b1226;color:#e8eeff;border:1px solid rgba(124,92,255,.5);border-radius:12px;padding:10px 12px;font:500 12px/1.55 Sora,system-ui,sans-serif;box-shadow:0 18px 50px rgba(0,0,0,.45);pointer-events:none;white-space:pre-line;opacity:0;transform:translateY(4px);transition:opacity .15s,transform .15s}
#ahp-tip.on{opacity:1;transform:none}
#ahp-tip[hidden]{display:none}
.ahp-flash{animation:ahpFlash 1.7s ease}
@keyframes ahpFlash{0%,100%{box-shadow:none}30%{box-shadow:0 0 0 4px rgba(124,92,255,.32)}}
.ahp .link .ahp-linkcopy{border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.13);color:#fff;border-radius:8px;padding:7px 10px;font:inherit;font-size:11.5px;font-weight:800;cursor:pointer}
.ahp .link .ahp-linkcopy:hover{background:rgba(255,255,255,.24)}
@media(max-width:640px){
  .ahp-guide{padding:14px;gap:12px;border-radius:16px}
  .ahp-guide__ring{width:64px;height:64px}
  .ahp-guide__actions{width:100%;flex-direction:row;flex-wrap:wrap}
  .ahp-guide__cta{flex:1}
  .ahp-wiz{padding:10px}
  .ahp-wiz__panel{padding:18px 15px;border-radius:16px}
  .ahp-wiz__step{padding:13px;gap:10px}
  .ahp-wiz__num{width:32px;height:32px;font-size:14px}
  .ahp-wiz__foot{display:grid;grid-template-columns:1fr 1fr}
  .ahp-wiz__cta{grid-column:1/-1}
  .ahp-wiz__hide{grid-column:1/-1;text-align:left;margin-left:0}
}
</style>

<div class="ahp-guide<?= $__wizPct >= 100 ? ' is-complete' : '' ?>" id="ahp-guide">
  <div class="ahp-guide__ring" id="ahp-guide-ring" style="--p:<?= (int)$__wizPct ?>"><b id="ahp-guide-pct"><?= (int)$__wizPct ?>%</b></div>
  <div class="ahp-guide__body">
    <div class="ahp-guide__eyebrow">Guía de primeros pasos</div>
    <h2 id="ahp-guide-title"><?= $__wizPct >= 100 ? '¡Todo listo! Tu perfil está completo' : 'Tus primeros pasos como Partner SONIKA' ?></h2>
    <p id="ahp-guide-desc"><?= $__wizPct >= 100 ? 'Ahora comparte tu enlace y revisa tus solicitudes. Si algo cambia, puedes volver a esta guía cuando quieras.' : 'Completa estos pasos para que tu perfil quede listo. Te decimos qué hacer y dónde hacerlo, sin términos técnicos.' ?></p>
    <div class="ahp-guide__bar"><span id="ahp-guide-bar" style="width:<?= (int)$__wizPct ?>%"></span></div>
    <div class="ahp-guide__meta" id="ahp-guide-meta"><?= (int)$__wizDone ?> de <?= (int)$__wizTotal ?> completados<?= $__wizNextTitle !== '' ? ' · Siguiente: ' . sonika_esc($__wizNextTitle) : ' · ¡Todo listo!' ?></div>
  </div>
  <div class="ahp-guide__actions">
    <button type="button" class="ahp-guide__cta" data-wiz-open data-wiz-start="<?= (int)($__wizNextIndex >= 0 ? $__wizNextIndex : 0) ?>"><?= $__wizPct >= 100 ? 'Ver mi guía' : 'Continuar paso a paso' ?></button>
    <button type="button" class="ahp-guide__link" data-wiz-open data-wiz-start="0">Ver todos los pasos</button>
  </div>
</div>

<div class="ahp-wiz" id="ahp-wiz" hidden>
  <div class="ahp-wiz__backdrop" data-wiz-close></div>
  <div class="ahp-wiz__panel" role="dialog" aria-modal="true" aria-labelledby="ahp-wiz-title">
    <button type="button" class="ahp-wiz__x" data-wiz-close aria-label="Cerrar guía">&times;</button>
    <div class="ahp-wiz__eyebrow">Guía de primeros pasos</div>
    <h2 id="ahp-wiz-title">Tu camino como Partner SONIKA</h2>
    <div class="ahp-wiz__progress"><div class="ahp-wiz__bar"><span id="ahp-wiz-bar"></span></div><span id="ahp-wiz-count"></span></div>
    <div class="ahp-wiz__dots" id="ahp-wiz-dots" aria-label="Pasos de la guía"></div>
    <div id="ahp-wiz-step"></div>
    <div class="ahp-wiz__foot">
      <button type="button" class="ahp-wiz__ghost" id="ahp-wiz-prev">Anterior</button>
      <button type="button" class="ahp-wiz__ghost" id="ahp-wiz-next">Siguiente</button>
      <button type="button" class="ahp-wiz__cta" id="ahp-wiz-action">Hacer este paso</button>
      <button type="button" class="ahp-wiz__hide" id="ahp-wiz-hide">No mostrar al entrar</button>
    </div>
  </div>
</div>

<div id="ahp-tip" role="tooltip" hidden></div>

<script>window.__AHP_WIZ = <?= json_encode($__wizCfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script>
(function () {
  'use strict';
  var cfg = window.__AHP_WIZ || {};
  if (!cfg.steps || !cfg.steps.length) return;
  var steps = cfg.steps.map(function (s) { return Object.assign({}, s); });
  var current = 0;
  var modal = document.getElementById('ahp-wiz');
  var card = document.getElementById('ahp-guide');
  var tip = document.getElementById('ahp-tip');
  var tipEl = null;
  var lastFocus = null;
  var hideKey = 'sonika_wiz_hide_' + cfg.id;
  var seenKey = 'sonika_wiz_seen_' + cfg.id + '_v1';

  function storeGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function storeSet(k, v) { try { localStorage.setItem(k, v); } catch (e) {} }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); }
  function doneCount() { return steps.filter(function (s) { return s.done; }).length; }
  function pct() { return Math.round(doneCount() / steps.length * 100); }
  function nextStep() { for (var i = 0; i < steps.length; i++) { if (!steps[i].done) return i; } return -1; }

  function renderCard() {
    if (!card) return;
    var p = pct();
    var n = doneCount();
    var ring = document.getElementById('ahp-guide-ring');
    var pctEl = document.getElementById('ahp-guide-pct');
    var bar = document.getElementById('ahp-guide-bar');
    var meta = document.getElementById('ahp-guide-meta');
    var title = document.getElementById('ahp-guide-title');
    var desc = document.getElementById('ahp-guide-desc');
    var primary = card.querySelector('[data-wiz-open]');
    if (ring) ring.style.setProperty('--p', p);
    if (pctEl) pctEl.textContent = p + '%';
    if (bar) bar.style.width = p + '%';
    var next = nextStep();
    if (meta) meta.textContent = n + ' de ' + steps.length + ' completados' + (next >= 0 ? ' · Siguiente: ' + steps[next].title : ' · ¡Todo listo!');
    if (title) title.textContent = p >= 100 ? '¡Todo listo! Tu perfil está completo' : 'Tus primeros pasos como Partner SONIKA';
    if (desc) desc.textContent = p >= 100 ? 'Ahora comparte tu enlace y revisa tus solicitudes. Si algo cambia, puedes volver a esta guía cuando quieras.' : 'Completa estos pasos para que tu perfil quede listo. Te decimos qué hacer y dónde hacerlo, sin términos técnicos.';
    if (primary) { primary.textContent = p >= 100 ? 'Ver mi guía' : 'Continuar paso a paso'; primary.setAttribute('data-wiz-start', next >= 0 ? next : 0); }
    card.classList.toggle('is-complete', p >= 100);
    if (modal && !modal.hidden) render();
  }

  function renderDots() {
    var box = document.getElementById('ahp-wiz-dots');
    if (!box) return;
    box.innerHTML = '';
    steps.forEach(function (s, i) {
      var b = document.createElement('button');
      b.type = 'button';
      b.className = 'ahp-wiz__dot' + (i === current ? ' is-current' : '') + (s.done ? ' is-done' : '');
      b.textContent = s.done ? '✓' : String(i + 1);
      b.setAttribute('aria-label', 'Paso ' + (i + 1) + ': ' + s.title);
      b.addEventListener('click', function () { current = i; render(); });
      box.appendChild(b);
    });
  }

  function render() {
    if (!modal) return;
    var s = steps[current] || steps[0];
    var p = pct();
    var bar = document.getElementById('ahp-wiz-bar');
    var count = document.getElementById('ahp-wiz-count');
    var stepBox = document.getElementById('ahp-wiz-step');
    var action = document.getElementById('ahp-wiz-action');
    var prev = document.getElementById('ahp-wiz-prev');
    var next = document.getElementById('ahp-wiz-next');
    if (bar) bar.style.width = p + '%';
    if (count) count.textContent = doneCount() + ' de ' + steps.length + ' completados';
    renderDots();
    if (stepBox) {
      stepBox.innerHTML = '<div class="ahp-wiz__step"><div class="ahp-wiz__num">' + (current + 1) + '</div><div class="ahp-wiz__stepbody">'
        + '<h3>' + esc(s.title) + '</h3><p>' + esc(s.desc) + '</p>'
        + '<div class="ahp-wiz__why"><b>¿Por qué es importante?</b><br>' + esc(s.why) + '</div>'
        + (s.tip ? '<div class="ahp-wiz__hint">💡 ' + esc(s.tip) + '</div>' : '')
        + (s.done ? '<div class="ahp-wiz__done">✓ Ya completaste este paso</div>' : '')
        + '</div></div>';
    }
    if (prev) prev.disabled = current <= 0;
    if (next) next.disabled = current >= steps.length - 1;
    if (action) {
      action.disabled = !!s.done || s.key === 'approval' || !s.action;
      action.textContent = s.done ? 'Ya completado' : (s.key === 'approval' ? 'Esperando aprobación' : (s.action ? 'Hacer este paso' : 'Entendido'));
    }
    document.getElementById('ahp-wiz-title').textContent = 'Tu camino como Partner SONIKA';
  }

  function open(start) {
    if (!modal) return;
    if (typeof start === 'number' && start >= 0 && start < steps.length) current = start;
    else current = Math.max(0, nextStep());
    lastFocus = document.activeElement;
    render();
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    var x = modal.querySelector('.ahp-wiz__x');
    if (x) setTimeout(function () { x.focus(); }, 30);
  }

  function close() {
    if (!modal) return;
    modal.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus && lastFocus.focus) lastFocus.focus();
  }

  function markDone(key) {
    var changed = false;
    steps.forEach(function (s) { if (s.key === key && !s.done) { s.done = true; changed = true; } });
    if (changed) { renderCard(); if (modal && !modal.hidden) render(); }
  }

  function goTab(tab) {
    var btn = document.querySelector('.ahp-tab[data-tab="' + tab + '"]');
    if (btn) btn.click();
  }

  function goAction() {
    var s = steps[current];
    if (!s || !s.action) { close(); return; }
    if (s.action === 'contract') { location.href = '/artista/contrato'; return; }
    var parts = s.action.split(':');
    var tab = parts[0];
    var target = parts.slice(1).join(':');
    close();
    setTimeout(function () {
      goTab(tab);
      setTimeout(function () {
        var el = document.querySelector(target);
        if (!el) return;
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('ahp-flash');
        setTimeout(function () { el.classList.remove('ahp-flash'); }, 1800);
        if (target === '#ahp-copy-link' && el.focus) el.focus();
      }, 180);
    }, 60);
  }

  // Listeners del wizard
  document.querySelectorAll('[data-wiz-open]').forEach(function (b) {
    b.addEventListener('click', function () {
      var raw = b.getAttribute('data-wiz-start');
      var st = raw === null ? -1 : parseInt(raw, 10);
      open(isNaN(st) ? -1 : st);
    });
  });
  modal.querySelectorAll('[data-wiz-close]').forEach(function (b) { b.addEventListener('click', close); });
  document.getElementById('ahp-wiz-prev').addEventListener('click', function () { if (current > 0) { current--; render(); } });
  document.getElementById('ahp-wiz-next').addEventListener('click', function () { if (current < steps.length - 1) { current++; render(); } });
  document.getElementById('ahp-wiz-action').addEventListener('click', goAction);
  document.getElementById('ahp-wiz-hide').addEventListener('click', function () { storeSet(hideKey, '1'); close(); });
  modal.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { e.preventDefault(); close(); return; }
    if (e.key !== 'Tab') return;
    var f = modal.querySelectorAll('button:not([disabled]), [href], input, [tabindex]:not([tabindex="-1"])');
    if (!f.length) return;
    var first = f[0], last = f[f.length - 1];
    if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
    else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
  });

  window.AHPWiz = { open: open, close: close, markDone: markDone, steps: steps };

  // Copiar enlace + registrar el paso "compartir"
  function bootCopy() {
    var copyBtn = document.getElementById('ahp-copy-link');
    if (!copyBtn) return;
    copyBtn.addEventListener('click', function () {
      var code = document.querySelector('.ahp .link code');
      var text = code ? code.textContent.trim() : '';
      var done = function () {
        var old = copyBtn.textContent;
        copyBtn.textContent = '¡Copiado!';
        setTimeout(function () { copyBtn.textContent = old; }, 1600);
        try {
          fetch('/api/v1/public/affiliates/wizard', {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ step: 'share' })
          });
        } catch (e) {}
        markDone('share');
      };
      var fallback = function () {
        var ta = document.createElement('textarea');
        ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
        document.body.appendChild(ta); ta.select();
        var ok = false;
        try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
        ta.remove();
        if (ok) done();
        else { copyBtn.textContent = 'No se pudo copiar'; setTimeout(function () { copyBtn.textContent = 'Copiar enlace'; }, 1600); }
      };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done).catch(fallback);
      } else {
        fallback();
      }
    });
  }

  // Auto-abrir una vez para artistas nuevos / en revisión
  if (!cfg.isDemo && cfg.pct < 100 && cfg.status !== 'APPROVED' && !storeGet(hideKey) && !storeGet(seenKey)) {
    storeSet(seenKey, '1');
    setTimeout(function () { open(cfg.nextIndex >= 0 ? cfg.nextIndex : 0); }, 900);
  }

  // ── Tooltips en lenguaje sencillo ────────────────────────────────
  var SECTION_TIPS = [
    ['Mi Pulso', 'Cada vez que entras te damos ideas concretas para avanzar: compartir tu enlace, responder solicitudes o mejorar tu perfil.'],
    ['Estadísticas de ventas', 'Un resumen fácil de tus clics, pedidos y ticket promedio. Un clic es una visita; un pedido es una compra.'],
    ['Pedidos atribuidos', 'Pedidos que llegaron por tu enlace. Aquí ves cuánto se vendió y cuánto ganaste de comisión.'],
    ['Productos que visitaron tus seguidores', 'Los productos que más miró la gente que llegó por tu enlace. Te ayuda a saber qué recomendar.'],
    ['Retiro', 'Aquí pides que te paguen tus comisiones. El mínimo es $100.000 y confirmas con un código que llega a tu correo.'],
    ['Mis Ganancias', 'Te mostramos en qué estado está cada comisión, desde “estimada” hasta “pagada”.'],
    ['Solicitudes de contratación', 'Personas u organizaciones que quieren contratarte. Responde rápido para no perder la oportunidad.'],
    ['Aportes de agradecimiento', 'Propinas o aportes que tus fans te dejan. Son un extra voluntario, no una comisión.'],
    ['Estudio Visual', 'Sube tus fotos. Te indicamos el tamaño ideal y las optimizamos para que tu página cargue rápido.'],
    ['Trayectoria', 'Cuenta tu historia, las bandas con las que has tocado y tus logros. Esto ayuda a que te encuentren y confíen en ti.'],
    ['Perfil público', 'Así te ven los fans. Escribe una descripción corta y clara; puedes cambiarla cuando quieras.'],
    ['SONIKA Sound League', 'Tu nivel sube con las ventas del mes. Es una forma de medir tu crecimiento; no cambia el porcentaje de tu comisión.'],
    ['Logros y Premios', 'Reconocimientos que ganas al alcanzar metas. Se muestran en tu panel como motivación.']
  ];
  var TAB_TIPS = {
    ventas: 'Mira cuántas personas entraron con tu enlace, qué compraron y cuánto has ganado.',
    ganancias: 'Consulta tus comisiones y pide tu retiro cuando tengas el mínimo.',
    tienda: 'Tu enlace personal lleva a tu vitrina. Compártelo en redes; si alguien compra después de entrar por él, la venta se te atribuye por 30 días.',
    comunidad: 'Revisa solicitudes de contratación y aportes de tus fans.',
    perfil: 'Edita tu foto, portada, historia y redes para que tu página se vea profesional.'
  };
  var METRIC_TIPS = {
    'Clicks (atribución)': 'Personas que entraron a SONIKA usando tu enlace. No todas compran; es normal.',
    'Billete Ganado (ventas atribuidas)': 'Valor de las ventas que llegaron por tu enlace.',
    'Comisiones (total)': 'Dinero que has ganado por esas ventas. Puede estar pendiente de validación.',
    'Billete pendiente por entrar': 'Comisiones que aún se están revisando o están programadas para pago.',
    'Conversión (clics → pedidos)': 'De cada 100 personas que entraron por tu enlace, cuántas compraron.',
    'Ticket promedio': 'Valor promedio de los pedidos que llegaron por tu enlace.',
    'Pedidos atribuidos': 'Cantidad de pedidos que se asociaron a tu enlace.'
  };
  var BTN_TIPS = {
    'Solicitar retiro': 'Pide el pago de tus comisiones disponibles. Te enviaremos un código al correo para confirmar.',
    'Guardar perfil': 'Guarda los cambios de tu presentación. Se publican al instante en tu página.',
    'Guardar trayectoria': 'Guarda tu historia, bandas y logros. Puedes editarlo cuando quieras.',
    'Copiar enlace': 'Copia tu enlace personal para compartirlo en WhatsApp, Instagram o donde quieras.',
    'Confirmar retiro': 'Escribe el código que llegó a tu correo para confirmar el retiro.',
    'Ver mi página': 'Abre tu página pública tal como la ven tus fans.',
    'Contrato': 'Lee, firma o consulta tu contrato del programa.',
    'Guía': 'Abre esta guía cuando quieras. Te muestra el siguiente paso y cómo hacerlo.'
  };

  function addInfo(el, text, withIcon) {
    if (!el || !text || el.querySelector('.ahp-i')) return;
    if (withIcon === false) {
      if (!el.getAttribute('data-tip')) el.setAttribute('data-tip', text);
      return;
    }
    var s = document.createElement('span');
    s.className = 'ahp-i';
    s.textContent = 'i';
    s.tabIndex = 0;
    s.setAttribute('role', 'button');
    s.setAttribute('aria-label', 'Más información');
    s.setAttribute('data-tip', text);
    s.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      if (tipEl === s) hideTip(); else showTip(s);
    });
    s.addEventListener('keydown', function (e) {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); showTip(s); }
    });
    el.appendChild(s);
  }

  function showTip(el) {
    if (!tip) return;
    var text = el.getAttribute('data-tip') || '';
    if (!text) return;
    tip.textContent = text;
    tip.hidden = false;
    tip.style.visibility = 'hidden';
    var r = el.getBoundingClientRect();
    var tw = tip.offsetWidth, th = tip.offsetHeight;
    var left = Math.max(10, Math.min(window.innerWidth - tw - 10, r.left + r.width / 2 - tw / 2));
    var top = r.top - th - 10;
    if (top < 8) top = Math.max(8, Math.min(window.innerHeight - th - 8, r.bottom + 10));
    tip.style.left = left + 'px';
    tip.style.top = top + 'px';
    tip.style.visibility = '';
    requestAnimationFrame(function () { tip.classList.add('on'); });
    tipEl = el;
  }

  function hideTip() {
    if (!tip) return;
    tip.classList.remove('on');
    tip.hidden = true;
    tipEl = null;
  }

  document.addEventListener('mouseover', function (e) {
    var el = e.target.closest && e.target.closest('[data-tip]');
    if (el && el !== tipEl) showTip(el);
  });
  document.addEventListener('mouseout', function (e) {
    var el = e.target.closest && e.target.closest('[data-tip]');
    if (el && !el.contains(e.relatedTarget)) hideTip();
  });
  document.addEventListener('focusin', function (e) {
    var el = e.target.closest && e.target.closest('[data-tip]');
    if (el) showTip(el);
  });
  document.addEventListener('focusout', function (e) {
    var el = e.target.closest && e.target.closest('[data-tip]');
    if (el) hideTip();
  });
  document.addEventListener('click', function (e) {
    if (e.target.closest && e.target.closest('.ahp-i')) return;
    var el = e.target.closest && e.target.closest('[data-tip]');
    if (el) { if (tipEl === el) hideTip(); else showTip(el); }
    else hideTip();
  });
  document.addEventListener('scroll', hideTip, true);
  window.addEventListener('resize', hideTip);

  function bootTips() {
    document.querySelectorAll('.ahp-tab').forEach(function (b) {
      var key = b.getAttribute('data-tab');
      if (TAB_TIPS[key]) addInfo(b, TAB_TIPS[key], false);
    });
    document.querySelectorAll('.ahp-sec h2, .ahp-ms-title h2, .ahp-pub h3, .ahp-tr h3').forEach(function (h) {
      var txt = (h.textContent || '').trim();
      for (var i = 0; i < SECTION_TIPS.length; i++) {
        if (txt.indexOf(SECTION_TIPS[i][0]) === 0) { addInfo(h, SECTION_TIPS[i][1]); break; }
      }
    });
    document.querySelectorAll('.ahp-c small').forEach(function (s) {
      var txt = (s.textContent || '').trim();
      if (METRIC_TIPS[txt]) addInfo(s, METRIC_TIPS[txt]);
    });
    document.querySelectorAll('.ahp button, .ahp a').forEach(function (el) {
      var txt = (el.textContent || '').trim();
      Object.keys(BTN_TIPS).forEach(function (needle) {
        if (txt.indexOf(needle) === 0) addInfo(el, BTN_TIPS[needle], false);
      });
    });
    var linkLabel = document.querySelector('.ahp .link > span');
    if (linkLabel) addInfo(linkLabel, 'Este es tu enlace personal. Compártelo en tus redes; si alguien compra después de entrar por él, la venta se te atribuye por 30 días.');
  }

  function bootAll() { bootTips(); bootCopy(); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', bootAll);
  else bootAll();
})();
</script>
