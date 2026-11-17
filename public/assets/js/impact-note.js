(function () {
  'use strict';

  var boxes = document.querySelectorAll('[data-impact-note]');
  if (!boxes.length) return;

  boxes.forEach(function (box) {
    var phrases = [].slice.call(box.querySelectorAll('[data-impact-phrase]'));
    if (phrases.length < 2) return;

    var interval = Math.max(4, Math.min(60, parseInt(box.getAttribute('data-interval'), 10) || 9)) * 1000;
    var current = 0;
    var timer = null;
    var paused = false;

    function show(index) {
      current = index;
      phrases.forEach(function (phrase, i) {
        var active = i === index;
        phrase.classList.toggle('is-active', active);
        phrase.setAttribute('aria-hidden', active ? 'false' : 'true');
      });
    }

    function start() {
      if (timer || paused || phrases.length < 2) return;
      timer = setInterval(function () {
        if (!document.hidden) show((current + 1) % phrases.length);
      }, interval);
    }

    function stop() {
      if (timer) {
        clearInterval(timer);
        timer = null;
      }
    }

    box.addEventListener('mouseenter', function () {
      paused = true;
      stop();
    });
    box.addEventListener('mouseleave', function () {
      paused = false;
      start();
    });
    box.addEventListener('focusin', function () {
      paused = true;
      stop();
    });
    box.addEventListener('focusout', function () {
      paused = false;
      start();
    });
    document.addEventListener('visibilitychange', function () {
      if (document.hidden) {
        stop();
      } else if (!paused) {
        start();
      }
    });

    show(0);
    start();
  });
})();
