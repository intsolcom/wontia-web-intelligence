(function () {
  'use strict';

  var cfg = window.SONIKA_SOCIAL_PROOF || {};
  if (!cfg.enabled || window.__sonikaSocialProofStarted) return;
  window.__sonikaSocialProofStarted = true;

  var STORAGE_KEY = 'sonika_social_proof_dismissed_v1';
  var root = null;
  var card = null;
  var textEl = null;
  var ageEl = null;
  var closeBtn = null;
  var nextTimer = null;
  var hideTimer = null;
  var ageTimer = null;
  var visible = false;
  var age = 0;

  function randomInt(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
  }

  function dismissed() {
    try {
      return sessionStorage.getItem(STORAGE_KEY) === '1';
    } catch (error) {
      return false;
    }
  }

  function markDismissed() {
    try {
      sessionStorage.setItem(STORAGE_KEY, '1');
    } catch (error) {
    }
  }

  function reducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  }

  function build() {
    if (root) return;
    root = document.createElement('div');
    root.id = 'sonika-social-proof';
    root.className = 'sonika-sp';
    root.setAttribute('data-pos', cfg.position || 'bottom-left');
    root.setAttribute('data-theme', cfg.theme || 'light');
    root.setAttribute('data-in', reducedMotion() ? 'fade' : (cfg.animation_in || 'slide-up'));
    root.setAttribute('data-out', reducedMotion() ? 'fade' : (cfg.animation_out || 'fade'));
    root.style.setProperty('--sp-accent', cfg.accent || '#F20D16');
    root.setAttribute('role', 'status');
    root.setAttribute('aria-live', 'polite');
    root.setAttribute('aria-atomic', 'true');
    root.hidden = true;

    card = document.createElement('div');
    card.className = 'sonika-sp__card';

    var dot = document.createElement('span');
    dot.className = 'sonika-sp__dot';
    dot.setAttribute('aria-hidden', 'true');

    var body = document.createElement('div');
    body.className = 'sonika-sp__body';

    var eyebrow = document.createElement('span');
    eyebrow.className = 'sonika-sp__eyebrow';
    eyebrow.textContent = 'Compra reciente';

    textEl = document.createElement('p');
    textEl.className = 'sonika-sp__text';

    ageEl = document.createElement('span');
    ageEl.className = 'sonika-sp__age';

    body.appendChild(eyebrow);
    body.appendChild(textEl);
    body.appendChild(ageEl);

    if (cfg.dismissible !== false) {
      closeBtn = document.createElement('button');
      closeBtn.type = 'button';
      closeBtn.className = 'sonika-sp__close';
      closeBtn.setAttribute('aria-label', 'Cerrar aviso de compra');
      closeBtn.innerHTML = '&times;';
      closeBtn.addEventListener('click', function () {
        markDismissed();
        hide();
      });
      card.appendChild(closeBtn);
    }

    card.appendChild(dot);
    card.appendChild(body);
    root.appendChild(card);
    document.body.appendChild(root);
  }

  function render(event) {
    build();
    textEl.textContent = '';

    var name = document.createElement('b');
    name.textContent = event.name || 'Un cliente';
    textEl.appendChild(name);
    textEl.appendChild(document.createTextNode(' de ' + (event.city || 'Valledupar, Cesar') + ' ha comprado: '));

    var product = document.createElement('a');
    product.textContent = event.product || 'un producto del catálogo';
    if (cfg.product_link !== false && event.url) {
      product.href = event.url;
      product.setAttribute('rel', 'nofollow');
    } else {
      product = document.createElement('b');
      product.textContent = event.product || 'un producto del catálogo';
    }
    textEl.appendChild(product);

    age = Math.max(1, Math.min(60, parseInt(event.age_seconds, 10) || randomInt(4, 58)));
    updateAge();

    var visibleSeconds = Math.max(3, Math.min(60, parseInt(event.visible_seconds, 10) || parseInt(cfg.visible_seconds, 10) || 8));
    root.hidden = false;
    visible = true;
    root.classList.remove('is-out');
    window.requestAnimationFrame(function () {
      root.classList.add('is-in');
    });

    clearTimeout(hideTimer);
    clearTimeout(ageTimer);
    ageTimer = setInterval(function () {
      if (age < 60) {
        age++;
        updateAge();
      }
    }, 1000);
    hideTimer = setTimeout(hide, visibleSeconds * 1000);
  }

  function updateAge() {
    ageEl.textContent = 'hace ' + age + (age === 1 ? ' segundo' : ' segundos');
  }

  function hide() {
    if (!root || !visible) {
      schedule();
      return;
    }
    visible = false;
    clearInterval(ageTimer);
    clearTimeout(hideTimer);
    root.classList.remove('is-in');
    root.classList.add('is-out');
    var delay = reducedMotion() ? 0 : 500;
    setTimeout(function () {
      if (!root) return;
      root.hidden = true;
      root.classList.remove('is-out');
    }, delay);
    schedule();
  }

  function schedule(delayMs) {
    clearTimeout(nextTimer);
    if (dismissed()) return;
    var min = Math.max(300, parseInt(cfg.interval_min, 10) || 300);
    var max = Math.max(min, parseInt(cfg.interval_max, 10) || min);
    var wait = typeof delayMs === 'number' ? delayMs : randomInt(min, max) * 1000;
    nextTimer = setTimeout(show, wait);
  }

  function show() {
    if (dismissed()) return;
    if (document.visibilityState === 'hidden') {
      schedule();
      return;
    }
    fetch((cfg.endpoint || '/api/v1/public/store/social-proof') + '?t=' + Date.now(), {
      cache: 'no-store',
      headers: { Accept: 'application/json' }
    }).then(function (response) {
      return response.json();
    }).then(function (payload) {
      if (!payload || !payload.ok || !payload.data) throw new Error('No disponible');
      render(payload.data);
    }).catch(function () {
      schedule(60000);
    });
  }

  function start() {
    if (dismissed()) return;
    var initial = Math.max(5, Math.min(120, parseInt(cfg.initial_delay, 10) || 12));
    nextTimer = setTimeout(show, initial * 1000);
  }

  document.addEventListener('visibilitychange', function () {
    if (document.hidden) {
      clearTimeout(nextTimer);
      if (visible) hide();
    } else if (!visible && !dismissed()) {
      schedule();
    }
  });

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start, { once: true });
  } else {
    start();
  }
})();
