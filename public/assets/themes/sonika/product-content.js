/**
 * SONIKA Product Content System — video click-to-play.
 *
 * No carga el iframe hasta que la persona pulsa reproducir. Así la ficha
 * conserva el video principal sin penalizar LCP, datos móviles ni altura.
 */
(function () {
  'use strict';

  function playVideo(box) {
    if (!box || box.getAttribute('data-pdp-video-ready') === '1') return;
    var src = box.getAttribute('data-src') || '';
    if (!src) return;
    box.setAttribute('data-pdp-video-ready', '1');

    var iframe = document.createElement('iframe');
    iframe.setAttribute('src', src);
    iframe.setAttribute('title', box.getAttribute('data-title') || 'Video del producto');
    iframe.setAttribute('loading', 'eager');
    iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
    iframe.setAttribute('allowfullscreen', '');
    iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    iframe.setAttribute('frameborder', '0');

    box.classList.add('is-playing');
    box.appendChild(iframe);

    try {
      box.dispatchEvent(new CustomEvent('sonika:pdp-video', {
        bubbles: true,
        detail: {
          provider: box.getAttribute('data-provider') || '',
          id: box.getAttribute('data-id') || '',
          title: box.getAttribute('data-title') || ''
        }
      }));
    } catch (e) {
      // Navegadores sin CustomEvent: el video ya está en marcha.
    }
  }

  function bind(box) {
    if (!box || box.getAttribute('data-pdp-video-bound') === '1') return;
    box.setAttribute('data-pdp-video-bound', '1');

    var trigger = box.querySelector('[data-pdp-video-play]');
    if (trigger) {
      trigger.addEventListener('click', function (event) {
        event.preventDefault();
        event.stopPropagation();
        playVideo(box);
      });
    }

    box.addEventListener('click', function (event) {
      if (event.target && event.target.closest && event.target.closest('.pdp-video__fallback')) return;
      if (event.target && event.target.closest && event.target.closest('iframe')) return;
      playVideo(box);
    });

    box.addEventListener('keydown', function (event) {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        playVideo(box);
      }
    });
  }

  function init(root) {
    var scope = root && root.querySelectorAll ? root : document;
    var boxes = scope.querySelectorAll('[data-pdp-video]');
    for (var i = 0; i < boxes.length; i++) bind(boxes[i]);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(document); });
  } else {
    init(document);
  }

  // Compatibilidad con contenido inyectado por AJAX o editores.
  document.addEventListener('sonika:content-updated', function () { init(document); });
})();
