(function () {
  'use strict';
  var KEY = 'sonika_view_history';
  var MAX_PRODUCTS = 30;
  var MAX_CATEGORIES = 10;
  var MAX_SEARCHES = 10;
  var MAX_AGE = 30 * 24 * 60 * 60 * 1000;

  function read() {
    try {
      var value = JSON.parse(localStorage.getItem(KEY) || '{}');
      return value && typeof value === 'object' ? value : {};
    } catch (e) {
      return {};
    }
  }

  function clean(list, max) {
    if (!Array.isArray(list)) return [];
    var cutoff = Date.now() - MAX_AGE;
    return list.filter(function (item) {
      var at = Number(item && item.at) || 0;
      return item && at >= cutoff;
    }).slice(0, max);
  }

  function save(data) {
    data.products = clean(data.products, MAX_PRODUCTS);
    data.categories = clean(data.categories, MAX_CATEGORIES);
    data.searches = clean(data.searches, MAX_SEARCHES);
    data.updated_at = new Date().toISOString();
    try { localStorage.setItem(KEY, JSON.stringify(data)); } catch (e) {}
  }

  function addProduct(handle, title) {
    var data = read();
    data.products = Array.isArray(data.products) ? data.products : [];
    data.products = data.products.filter(function (item) { return item.handle !== handle; });
    data.products.unshift({ handle: handle, title: title || '', at: Date.now() });
    save(data);
  }

  function addCategory(slug, name) {
    var data = read();
    data.categories = Array.isArray(data.categories) ? data.categories : [];
    data.categories = data.categories.filter(function (item) { return item.slug !== slug; });
    data.categories.unshift({ slug: slug, name: name || slug, at: Date.now() });
    save(data);
  }

  function addSearch(query) {
    query = String(query || '').trim().toLowerCase();
    if (!query) return;
    var data = read();
    data.searches = Array.isArray(data.searches) ? data.searches : [];
    data.searches = data.searches.filter(function (item) { return item.q !== query; });
    data.searches.unshift({ q: query, at: Date.now() });
    save(data);
  }

  var path = location.pathname.replace(/\/+$/, '');
  var product = path.match(/^\/producto\/([^/?#]+)/i);
  if (product) {
    var handle = '';
    try { handle = decodeURIComponent(product[1]); } catch (e) { handle = product[1]; }
    var h1 = document.querySelector('h1');
    addProduct(handle, h1 ? h1.textContent.trim() : document.title.replace(/\s*-\s*SONIKA.*$/i, '').trim());
  } else if (/^\/(tienda|categorias|marcas|ofertas|buscar)?$/i.test(path) === false) {
    var first = path.split('/')[1] || '';
    if (first && !/^(carrito|checkout|pedido|cuenta|contacto|soporte|politicas|r|artista|cotizacion|marcas)$/i.test(first)) {
      var heading = document.querySelector('h1');
      addCategory(first, heading ? heading.textContent.trim() : first);
    }
  }

  try {
    var params = new URLSearchParams(location.search);
    var q = params.get('q') || params.get('search') || params.get('buscar') || '';
    if (q) addSearch(q);
  } catch (e) {}
})();
