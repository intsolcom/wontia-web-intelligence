/* ============================================================
   SONIKA — Compartir producto desde el carrito
   Modo amigo + modo Partner con enlace de comisión.
   Sin dependencias. Flat, accesible y mobile-first.
   ============================================================ */
(function () {
  'use strict';

  var P = window.SONIKA_PARTNER || {};
  var S = window.SONIKA_SHARE || {};
  var root = null;
  var toastTimer = null;

  var IMG = {
    format: S.image_format === 'square' ? 'square' : 'story',
    bg: S.image_bg || '#F6F6F3',
    ink: S.image_ink || '#111111',
    accent: S.image_accent || '#F20D16',
    muted: S.image_muted || '#6f6b63',
    brand: S.image_brand || 'SONIKA',
    cta: S.image_cta || 'Compra en sonika.com.co',
    footer: S.image_footer || 'Envíos a toda Colombia · Garantía SONIKA',
    showLink: S.image_show_link === undefined ? true : !!S.image_show_link,
    labelFriend: S.image_link_label_friend || 'ENLACE DEL PRODUCTO',
    labelPartner: S.image_link_label_partner || 'TU ENLACE DE PARTNER',
    cardBorder: S.card_border || '#e7e5df'
  };

  function on(key, fallback) {
    var v = S[key];
    if (v === undefined || v === null) return fallback;
    return !!v;
  }

  function channel(key) {
    if (!S.channels || typeof S.channels !== 'object') return true;
    return !!S.channels[key];
  }

  var state = {
    mode: 'friend',
    product: null,
    productUrl: '',
    partnerUrl: '',
    template: 'directo',
    message: '',
    support: true,
    isOutlet: false,
    story: null,
    storyPromise: null,
    preview: null,
    lastFocus: null
  };

  var SVG = {
    close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
    wa: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20.5 3.5A11.8 11.8 0 0 0 12 0C5.4 0 0 5.4 0 12c0 2.1.5 4.1 1.6 5.9L0 24l6.3-1.6A11.9 11.9 0 0 0 12 24c6.6 0 12-5.4 12-12 0-3.2-1.3-6.2-3.5-8.5ZM12 22a9.9 9.9 0 0 1-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4A9.9 9.9 0 0 1 2 12C2 6.5 6.5 2 12 2s10 4.5 10 10-4.5 10-10 10Zm5.5-7.4c-.3-.1-1.8-.9-2.1-1-.3-.1-.5-.2-.7.2-.2.3-.8 1-1 1.2-.2.2-.4.2-.7.1-.3-.1-1.3-.5-2.5-1.6-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6l.5-.6c.2-.2.3-.3.4-.5.1-.2 0-.4 0-.5 0-.1-.7-1.6-.9-2.2-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.1.2 2 3.1 4.9 4.3.7.3 1.2.5 1.6.6.7.2 1.3.2 1.8.1.6-.1 1.8-.7 2-1.4.3-.7.3-1.3.2-1.4-.1-.1-.3-.2-.6-.3Z"/></svg>',
    ig: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.3" cy="6.7" r="1.1" fill="currentColor" stroke="none"/></svg>',
    fb: '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M22 12.1C22 6.5 17.5 2 12 2S2 6.5 2 12.1c0 5 3.7 9.2 8.5 9.9v-7H8v-2.9h2.5V9.9c0-2.5 1.5-3.9 3.8-3.9 1.1 0 2.2.2 2.2.2v2.4h-1.2c-1.2 0-1.6.8-1.6 1.6v1.9h2.7l-.4 2.9h-2.3v7c4.8-.7 8.3-4.9 8.3-9.9Z"/></svg>',
    img: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="4"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></svg>',
    copy: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
    share: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="m8.6 13.5 6.8 4M15.4 6.5l-6.8 4"/></svg>',
    mail: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>',
    arrow: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>',
    download: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>'
  };

  var DEFAULT_TEMPLATES = {
    directo: {
      friend: 'Mira este {title} en SONIKA ({price}). Te lo recomiendo: {url}',
      partner: 'Mira este {title} en SONIKA ({price}). Lo elegí para ti: {url}'
    },
    curioso: {
      friend: '¿Ya viste este {title}? Está en SONIKA por {price}. Míralo aquí: {url}',
      partner: '¿Ya viste este {title}? Está en SONIKA por {price}. Te dejo mi enlace: {url}'
    },
    recomiendo: {
      friend: 'Te recomiendo este {title}. Lo encuentras en SONIKA por {price}: {url}',
      partner: 'Te recomiendo este {title} de SONIKA. Sé que te va a gustar: {url}'
    }
  };

  function applyTemplate(template, p, u) {
    var t = String(template || '');
    var price = p.priceLabel || '';
    if (price) {
      t = t.replace(/\{price\}/g, price);
    } else {
      t = t.replace(/\s*\(\{price\}\)/g, '').replace(/\s+por\s+\{price\}/g, '').replace(/\{price\}/g, '');
    }
    return t.replace(/\{title\}/g, p.title).replace(/\{url\}/g, u);
  }

  function templateText(key, mode) {
    var configured = S['template_' + key + '_' + mode];
    if (typeof configured === 'string' && configured.trim() !== '') return configured;
    return DEFAULT_TEMPLATES[key][mode];
  }

  var TEMPLATES = {
    directo: {
      label: 'Directo',
      friend: function (p, u) { return applyTemplate(templateText('directo', 'friend'), p, u); },
      partner: function (p, u) { return applyTemplate(templateText('directo', 'partner'), p, u); }
    },
    curioso: {
      label: 'Curioso',
      friend: function (p, u) { return applyTemplate(templateText('curioso', 'friend'), p, u); },
      partner: function (p, u) { return applyTemplate(templateText('curioso', 'partner'), p, u); }
    },
    recomiendo: {
      label: 'Cercano',
      friend: function (p, u) { return applyTemplate(templateText('recomiendo', 'friend'), p, u); },
      partner: function (p, u) { return applyTemplate(templateText('recomiendo', 'partner'), p, u); }
    }
  };

  function esc(v) {
    return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function money(cents) {
    var n = Math.round((Number(cents) || 0) / 100);
    return '$' + n.toLocaleString('es-CO');
  }

  function pct(v) {
    var n = Math.round((Number(v) || 0) * 1000) / 10;
    return (n % 1 === 0 ? n.toFixed(0) : n.toFixed(1)) + '%';
  }

  function normalize(v) {
    var s = String(v == null ? '' : v).toLowerCase();
    try { return s.normalize('NFD').replace(/[\u0300-\u036f]/g, ''); } catch (e) { return s; }
  }

  function absoluteUrl(v) {
    try { return new URL(v, location.href).href; } catch (e) { return String(v || ''); }
  }

  function productUrl(ref) {
    var base = location.origin || (location.protocol + '//' + location.host);
    return base + '/producto/' + encodeURIComponent(ref);
  }

  function affiliateUrl(ref, code) {
    if (!code) return productUrl(ref);
    var base = location.origin || (location.protocol + '//' + location.host);
    return base + '/r/' + encodeURIComponent(code) + '/p/' + encodeURIComponent(ref);
  }

  function readProduct(btn) {
    var ref = String(btn.getAttribute('data-ref') || '');
    var cents = Math.max(0, parseInt(btn.getAttribute('data-price-cents') || '0', 10) || 0);
    var image = String(btn.getAttribute('data-image') || '');
    return {
      ref: ref,
      title: String(btn.getAttribute('data-title') || ref || 'Producto SONIKA'),
      priceCents: cents,
      priceLabel: cents > 0 ? money(cents) : '',
      imageUrl: image ? absoluteUrl(image) : ''
    };
  }

  function currentUrl() {
    if (state.mode === 'partner' && P.approved && P.code) return state.partnerUrl;
    return state.productUrl;
  }

  function currentRate() {
    return state.isOutlet ? (Number(P.outlet_rate) || 0.02) : (Number(P.rate) || 0.03);
  }

  function estimateCommission() {
    if (!state.product) return 0;
    return Math.round((Number(state.product.priceCents) || 0) * currentRate());
  }

  function buildMessage() {
    if (!state.product) return '';
    var t = TEMPLATES[state.template] || TEMPLATES.directo;
    var partnerMode = state.mode === 'partner' && P.approved;
    var msg = (partnerMode ? t.partner : t.friend)(state.product, currentUrl());
    if (partnerMode && state.support) msg += '\n\n' + (S.support_line || 'Comprando con mi enlace apoyas mi proyecto musical.');
    return msg;
  }

  function currentMessage() {
    var ta = root && root.querySelector('#skshare-message');
    var msg = String(ta ? ta.value : (state.message || '')).trim();
    var u = currentUrl();
    if (u && msg.indexOf(u) === -1) msg += (msg ? '\n\n' : '') + u;
    return msg;
  }

  function progressPct() {
    var points = Number(P.points) || 0;
    var tierMin = Number(P.tier_min) || 0;
    var next = Number(P.next_points) || 0;
    if (next <= 0) return 100;
    var span = Math.max(1, next - tierMin);
    return Math.max(0, Math.min(100, Math.round((points - tierMin) / span * 100)));
  }

  function commissionNote() {
    var note = '';
    if (state.isOutlet) note += 'Producto outlet: comisión fija del ' + pct(P.outlet_rate) + '. ';
    var next = Number(P.next_points) || 0;
    var points = Number(P.points) || 0;
    if (next > 0) {
      var missing = Math.max(1, Math.ceil(next - points));
      note += 'Cada $100.000 vendidos suma 1 punto. Te faltan <b>' + missing + '</b> para subir a <b>' + pct(P.next_rate) + '</b>. ';
    } else {
      note += 'Estás en el nivel máximo de Puntos SONIKA. ';
    }
    note += 'Techo del mes: <b>' + pct(P.cap) + '</b>.';
    return note;
  }

  function icon(name, cls) {
    return '<span class="skshare__ico ' + (cls || '') + '">' + (SVG[name] || SVG.share) + '</span>';
  }

  function txt(title, sub) {
    return '<span class="skshare__txt"><b>' + esc(title) + '</b>' + (sub ? '<small>' + esc(sub) + '</small>' : '') + '</span>';
  }

  function action(iconName, iconClass, title, sub, attrs, extra) {
    return '<button type="button" class="skshare__action ' + (extra || '') + '" ' + (attrs || '') + '>' +
      icon(iconName, iconClass) + txt(title, sub) + '</button>';
  }

  function headHtml(eyebrow, title, sub) {
    return '<div class="skshare__head">' +
      '<p class="skshare__eyebrow"><i></i>' + esc(eyebrow) + '</p>' +
      '<h2 class="skshare__title" id="skshare-title">' + esc(title) + '</h2>' +
      (sub ? '<p class="skshare__sub">' + esc(sub) + '</p>' : '') +
      '</div>';
  }

  function productHtml() {
    if (!state.product) return '';
    var img = state.product.imageUrl
      ? '<img class="skshare__thumb" src="' + esc(state.product.imageUrl) + '" alt="" loading="lazy" onerror="this.style.display=\'none\'" />'
      : '<span class="skshare__thumb-ph" aria-hidden="true">♪</span>';
    return '<div class="skshare__product">' + img +
      '<div><p class="skshare__pname">' + esc(state.product.title) + '</p><div class="skshare__pmeta" id="skshare-product-meta">Producto SONIKA</div></div>' +
      '<div class="skshare__price">' + esc(state.product.priceLabel || '') + '</div>' +
      '</div>';
  }

  function partnerCardHtml() {
    var name = String(P.name || '').trim();
    var hello = name ? ('Hola ' + name + ', comparte y gana con este producto.') : 'Hola, comparte y gana con este producto.';
    var showCommission = on('show_commission', true);
    var showPoints = on('show_points', true);
    var h = '<div class="skshare__partner">' +
      '<p class="skshare__hello">SONIKA PARTNERS</p>' +
      '<h3 class="skshare__ptitle">' + esc(hello) + '</h3>';
    if (showCommission) {
      h += '<div class="skshare__prow">' +
        '<div class="skshare__earn" id="skshare-earn">' + money(estimateCommission()) + '<small>Comisión estimada por unidad</small></div>' +
        '<span class="skshare__rate" id="skshare-rate">' + pct(currentRate()) + (state.isOutlet ? ' outlet' : ' actual') + '</span>' +
        '</div>';
    }
    if (showPoints) {
      h += '<div class="skshare__meter"><i style="width:' + progressPct() + '%"></i></div>' +
        '<p class="skshare__pnote" id="skshare-pnote">' + commissionNote() + '</p>';
    }
    h += '</div>';
    return h;
  }

  function actionsSection() {
    var partner = state.mode === 'partner' && P.approved;
    var formatLabel = IMG.format === 'square' ? 'Imagen cuadrada 1:1' : 'Imagen vertical 9:16';
    var h = '';
    if (channel('whatsapp')) {
      h += action('wa', 'skshare__ico--wa', partner ? 'WhatsApp con mi enlace' : 'Enviar por WhatsApp', partner ? 'Mensaje + enlace de Partner' : 'Mensaje listo para enviar', 'data-skshare-wa', 'skshare__action--wa skshare__action--wide');
    }
    if (channel('whatsapp_status')) h += action('img', 'skshare__ico--img', 'Estado de WhatsApp', formatLabel, 'data-skshare-story="whatsapp"');
    if (channel('instagram_story')) h += action('ig', 'skshare__ico--ig', 'Historia de Instagram', formatLabel, 'data-skshare-story="instagram"');
    if (channel('facebook')) h += action('fb', 'skshare__ico--fb', 'Facebook', 'Publicar el enlace', 'data-skshare-fb');
    if (channel('facebook_story')) h += action('img', 'skshare__ico--img', 'Historia de Facebook', formatLabel, 'data-skshare-story="facebook"');
    if (channel('copy_link')) h += action('copy', 'skshare__ico--copy', 'Copiar enlace', partner ? 'Tu enlace de Partner' : 'Listo para pegar', 'data-skshare-copy');
    if (channel('email')) h += action('mail', 'skshare__ico--copy', 'Email', 'Enviar por correo', 'data-skshare-email');
    if (channel('native_share')) h += action('share', 'skshare__ico--copy', 'Más opciones', 'Compartir del sistema', 'data-skshare-native', 'skshare__action--wide');
    if (!h) return '';
    return '<div class="skshare__section">' +
      '<div class="skshare__label"><b>Compartir ahora</b><span>1 toque</span></div>' +
      '<div class="skshare__actions">' + h + '</div></div>';
  }

  function composerHtml() {
    if (!on('show_composer', true)) return '';
    var showTemplates = on('show_templates', true);
    var chips = showTemplates ? Object.keys(TEMPLATES).map(function (k) {
      return '<button type="button" class="skshare__chip' + (state.template === k ? ' is-on' : '') + '" data-skshare-template="' + k + '">' + esc(TEMPLATES[k].label) + '</button>';
    }).join('') : '';
    var support = '';
    if (state.mode === 'partner' && P.approved) {
      support = '<label class="skshare__support"><input type="checkbox" data-skshare-support' + (state.support ? ' checked' : '') + ' /> <span>Incluir “' + esc(S.support_line || 'comprando con mi enlace apoyas mi proyecto musical') + '”.</span></label>';
    }
    return '<div class="skshare__composer">' +
      '<div class="skshare__label"><b>Tu mensaje</b><span>Editable</span></div>' +
      (chips ? '<div class="skshare__chips">' + chips + '</div>' : '') +
      '<textarea class="skshare__textarea" id="skshare-message" maxlength="700" aria-label="Mensaje para compartir">' + esc(state.message) + '</textarea>' +
      support +
      '<div class="skshare__composer-foot"><span>El enlace se agrega solo si falta.</span><button type="button" class="skshare__mini" data-skshare-copymsg>Copiar mensaje</button></div>' +
      '</div>';
  }

  function linkHtml() {
    if (!on('show_link_box', true)) return '';
    var partner = state.mode === 'partner' && P.approved;
    return '<div class="skshare__link">' +
      '<div class="skshare__label"><b>' + (partner ? 'Tu enlace de Partner' : 'Enlace del producto') + '</b><span>' + (partner ? 'Comisión activa' : 'Para pegar donde quieras') + '</span></div>' +
      '<div class="skshare__linkbox"><input readonly value="' + esc(currentUrl()) + '" aria-label="' + (partner ? 'Enlace de Partner' : 'Enlace del producto') + '" /><button type="button" class="skshare__copy" data-skshare-copy>Copiar</button></div>' +
      '</div>';
  }

  function footerHtml(kind) {
    if (kind === 'partner') {
      return '<div class="skshare__foot">' +
        '<p>Comisión estimada sobre el valor del producto. Se acredita cuando la compra se confirma y cumple las reglas del programa.</p>' +
        '<span><a class="skshare__switch" href="' + esc(P.contract_url || '/sonika-partners/contrato') + '" target="_blank" rel="noopener">Reglas</a> · <a class="skshare__switch" href="' + esc(P.panel_url || '/mi-antro') + '">Mi panel</a></span>' +
        '</div>';
    }
    if (kind === 'pending') {
      return '<div class="skshare__foot"><p>Tu solicitud sigue en revisión. Te avisaremos cuando puedas activar comisiones.</p><a class="skshare__switch" href="' + esc(P.panel_url || '/mi-antro') + '">Ver mi panel →</a></div>';
    }
    if (kind === 'join') {
      return '<div class="skshare__foot"><p>Sin cuotas. Sin letra pequeña. Tú compartes, nosotros medimos.</p><button type="button" class="skshare__switch" data-skshare-mode="friend">Solo quiero compartir con un amigo →</button></div>';
    }
    var partnerCta = P.logged_in && P.approved
      ? 'Hola ' + (P.name || 'Partner') + ', usa tu enlace y gana →'
      : 'Comparte y gana comisión →';
    return '<div class="skshare__foot"><p>¿Eres Partner SONIKA? Cada producto que compartes puede generar comisión.</p><button type="button" class="skshare__switch" data-skshare-mode="partner">' + esc(partnerCta) + '</button></div>';
  }

  function friendHtml() {
    return headHtml('Compartir producto', S.friend_title || 'Una recomendación que vale la pena.', S.friend_sub || 'Elige el canal. El mensaje ya está listo; tú decides cómo enviarlo.') +
      productHtml() + actionsSection() + composerHtml() + linkHtml() + footerHtml('friend');
  }

  function partnerJoinHtml() {
    return headHtml('SONIKA Partners', S.join_title || 'Comparte lo que amas. Gana por hacerlo.', S.join_sub || 'Tu recomendación puede convertirse en una comisión. Así funciona el programa.') +
      productHtml() +
      '<div class="skshare__join">' +
      '<div class="skshare__join-top"><div><p class="skshare__hello" style="color:#6f6b63">COMISIÓN POR VENTA</p><div class="skshare__big">3% → 7%</div><p class="skshare__sub" style="margin-top:6px">Base del 3%. Sube con Puntos SONIKA y racha semanal.</p></div><span class="skshare__seal">Partner<br>SONIKA</span></div>' +
      '<ul class="skshare__benefits">' +
      '<li><b>3% base</b> y hasta 5% con Puntos SONIKA.</li>' +
      '<li><b>+1% o +2%</b> con racha semanal sostenida.</li>' +
      '<li><b>Outlet:</b> 2% fijo, sin complicaciones.</li>' +
      '<li><b>Premium:</b> +20 puntos por producto de alto valor.</li>' +
      '<li>Panel, enlaces y comisiones en un solo lugar.</li>' +
      '</ul>' +
      '<div class="skshare__cta">' +
      '<a class="skshare__action skshare__action--primary" href="' + esc(P.register_url || '/sonika-partners#registro') + '">' + txt('Quiero ser Partner', 'Registro gratuito') + '</a>' +
      '<a class="skshare__action" href="' + esc(P.login_url || '/mi-antro') + '">' + txt('Ya soy Partner', 'Entrar a mi panel') + '</a>' +
      '</div></div>' +
      footerHtml('join');
  }

  function partnerPendingHtml() {
    return headHtml('SONIKA Partners', S.pending_title || 'Tu perfil está en revisión.', S.pending_sub || 'Cuando tu solicitud sea aprobada, tus enlaces activarán comisiones automáticamente.') +
      productHtml() +
      '<div class="skshare__notice"><b>Estamos revisando tu solicitud.</b>Mientras tanto, puedes compartir el producto con un amigo o familiar. Las comisiones se activan al aprobar tu perfil.</div>' +
      '<div class="skshare__section"><div class="skshare__actions">' +
      action('arrow', 'skshare__ico--copy', 'Ver mi panel', 'Estado de mi solicitud', 'data-skshare-panel') +
      action('share', 'skshare__ico--copy', 'Compartir con un amigo', 'Sin comisión por ahora', 'data-skshare-mode="friend"') +
      '</div></div>' +
      footerHtml('pending');
  }

  function partnerApprovedHtml() {
    return headHtml('SONIKA Partners', S.partner_title || 'Tu recomendación tiene recompensa.', S.partner_sub || 'Comparte con tu enlace. Tú pones la recomendación; SONIKA pone la tienda.') +
      productHtml() + partnerCardHtml() + actionsSection() + composerHtml() + linkHtml() + footerHtml('partner');
  }

  function bodyHtml() {
    if (state.mode === 'partner') {
      if (!P.logged_in) return partnerJoinHtml();
      if (!P.approved) return partnerPendingHtml();
      return partnerApprovedHtml();
    }
    return friendHtml();
  }

  function previewHtml() {
    if (!state.preview || !state.story) return '';
    return '<div class="skshare__imageview" data-skshare-imgview>' +
      '<div class="skshare__imagecard">' +
      '<button type="button" class="skshare__close" data-skshare-imgclose aria-label="Cerrar vista previa">' + SVG.close + '</button>' +
      '<p class="skshare__eyebrow"><i></i>' + esc(state.preview.label) + '</p>' +
      '<h3>Tu imagen está lista.</h3>' +
      '<div class="skshare__imwrap"><img src="' + esc(state.story.url) + '" alt="Vista previa para ' + esc(state.preview.label) + '"></div>' +
      '<div class="skshare__imageactions">' +
      '<button type="button" class="skshare__action skshare__action--primary" data-skshare-imgshare>' + icon('share', 'skshare__ico--copy') + txt('Compartir imagen', 'Abre la galería o tus apps') + '</button>' +
      '<button type="button" class="skshare__action" data-skshare-imgdownload>' + icon('download', 'skshare__ico--copy') + txt('Descargar', 'Guárdala en tu dispositivo') + '</button>' +
      '<button type="button" class="skshare__action skshare__action--wide" data-skshare-imgcopy>' + icon('copy', 'skshare__ico--copy') + txt('Copiar enlace', 'Para el sticker o el texto del estado') + '</button>' +
      '</div>' +
      '<p class="skshare__imnote">La imagen ya incluye el enlace visible. Para que sea clicable, copia el enlace y pégalo en el sticker de enlace de Instagram o en el texto del estado de WhatsApp.</p>' +
      '</div></div>';
  }

  function render() {
    var el = ensureRoot();
    var wasOpen = el.classList.contains('is-open');
    el.className = 'skshare' + (wasOpen ? ' is-open' : '');
    el.setAttribute('role', 'dialog');
    el.setAttribute('aria-modal', 'true');
    el.setAttribute('aria-labelledby', 'skshare-title');
    el.innerHTML = '<div class="skshare__dialog" role="document">' +
      '<button type="button" class="skshare__close" data-skshare-close aria-label="Cerrar">' + SVG.close + '</button>' +
      bodyHtml() +
      '</div>' + previewHtml();
  }

  function ensureRoot() {
    if (root) return root;
    root = document.createElement('div');
    root.id = 'skshare-root';
    document.body.appendChild(root);
    bindRoot(root);
    return root;
  }

  function bindRoot(el) {
    el.addEventListener('click', function (e) {
      if (e.target === el) { closeShare(); return; }
      var t = e.target;
      var hit = t && t.closest ? t.closest('[data-skshare-close],[data-skshare-mode],[data-skshare-template],[data-skshare-wa],[data-skshare-fb],[data-skshare-email],[data-skshare-copy],[data-skshare-copymsg],[data-skshare-native],[data-skshare-story],[data-skshare-imgclose],[data-skshare-imgshare],[data-skshare-imgdownload],[data-skshare-imgcopy],[data-skshare-panel]') : null;
      if (!hit) return;
      if (hit.hasAttribute('data-skshare-close')) { closeShare(); return; }
      if (hit.hasAttribute('data-skshare-imgclose')) { closePreview(); return; }
      if (hit.hasAttribute('data-skshare-mode')) { switchMode(hit.getAttribute('data-skshare-mode')); return; }
      if (hit.hasAttribute('data-skshare-template')) { setTemplate(hit.getAttribute('data-skshare-template')); return; }
      if (hit.hasAttribute('data-skshare-wa')) { shareWhatsApp(); return; }
      if (hit.hasAttribute('data-skshare-fb')) { shareFacebook(); return; }
      if (hit.hasAttribute('data-skshare-email')) { shareEmail(); return; }
      if (hit.hasAttribute('data-skshare-copy')) { copyText(currentUrl()); return; }
      if (hit.hasAttribute('data-skshare-copymsg')) { copyText(currentMessage()); return; }
      if (hit.hasAttribute('data-skshare-native')) { shareNative(); return; }
      if (hit.hasAttribute('data-skshare-story')) { openStory(hit.getAttribute('data-skshare-story')); return; }
      if (hit.hasAttribute('data-skshare-imgshare')) { shareStoryImage(); return; }
      if (hit.hasAttribute('data-skshare-imgdownload')) { downloadStory(); return; }
      if (hit.hasAttribute('data-skshare-imgcopy')) { copyStoryLink(); return; }
      if (hit.hasAttribute('data-skshare-panel')) { location.href = P.panel_url || '/mi-antro'; return; }
    });

    el.addEventListener('input', function (e) {
      if (e.target && e.target.id === 'skshare-message') state.message = e.target.value;
    });

    el.addEventListener('change', function (e) {
      if (e.target && e.target.hasAttribute('data-skshare-support')) {
        state.support = !!e.target.checked;
        state.message = buildMessage();
        var ta = el.querySelector('#skshare-message');
        if (ta) ta.value = state.message;
      }
    });
  }

  function openShare(mode, product) {
    if (!product || !product.ref) return;
    state.lastFocus = document.activeElement;
    state.mode = mode === 'partner' ? 'partner' : 'friend';
    state.product = product;
    state.productUrl = productUrl(product.ref);
    state.partnerUrl = (P.approved && P.code) ? affiliateUrl(product.ref, P.code) : state.productUrl;
    state.template = S.default_template || 'directo';
    state.support = true;
    state.message = buildMessage();
    state.isOutlet = false;
    state.preview = null;
    if (state.story && state.story.url) {
      try { URL.revokeObjectURL(state.story.url); } catch (e) {}
    }
    state.story = null;
    state.storyPromise = null;
    render();
    document.body.classList.add('skshare-lock');
    if (root) {
      void root.offsetWidth;
      root.classList.add('is-open');
    }
    setTimeout(function () {
      var close = root && root.querySelector('.skshare__close');
      if (close) { try { close.focus({ preventScroll: true }); } catch (e) { close.focus(); } }
    }, 30);
    if (state.mode === 'partner' && P.approved && P.code) setTimeout(loadProductMeta, 120);
    track('open');
  }

  function closeShare() {
    if (!root) return;
    var el = root;
    el.classList.remove('is-open');
    document.body.classList.remove('skshare-lock');
    if (state.story && state.story.url) {
      try { URL.revokeObjectURL(state.story.url); } catch (e) {}
    }
    state.story = null;
    state.storyPromise = null;
    state.preview = null;
    setTimeout(function () {
      if (el && el.parentNode) el.parentNode.removeChild(el);
    }, 230);
    root = null;
    if (state.lastFocus && state.lastFocus.focus) {
      try { state.lastFocus.focus({ preventScroll: true }); } catch (e) {}
    }
    track('close');
  }

  function switchMode(mode) {
    mode = mode === 'partner' ? 'partner' : 'friend';
    if (state.mode === mode) return;
    state.mode = mode;
    state.template = S.default_template || 'directo';
    state.preview = null;
    if (state.story && state.story.url) {
      try { URL.revokeObjectURL(state.story.url); } catch (e) {}
    }
    state.story = null;
    state.storyPromise = null;
    state.message = buildMessage();
    render();
    if (mode === 'partner' && P.approved && P.code) setTimeout(loadProductMeta, 80);
    track('mode_' + mode);
  }

  function setTemplate(key) {
    if (!TEMPLATES[key]) return;
    state.template = key;
    state.message = buildMessage();
    var ta = root && root.querySelector('#skshare-message');
    if (ta) { ta.value = state.message; ta.focus(); }
    var chips = root ? root.querySelectorAll('[data-skshare-template]') : [];
    Array.prototype.forEach.call(chips, function (c) {
      c.classList.toggle('is-on', c.getAttribute('data-skshare-template') === key);
    });
  }

  function shareWhatsApp() {
    var text = currentMessage();
    try { window.open('https://wa.me/?text=' + encodeURIComponent(text), '_blank', 'noopener'); } catch (e) {}
    track('whatsapp');
  }

  function shareFacebook() {
    var u = currentUrl();
    try { window.open('https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(u), '_blank', 'noopener'); } catch (e) {}
    track('facebook');
  }

  function shareEmail() {
    var subject = (state.product ? state.product.title : 'Producto SONIKA') + ' — SONIKA';
    var body = currentMessage();
    try {
      window.location.href = 'mailto:?subject=' + encodeURIComponent(subject) + '&body=' + encodeURIComponent(body);
    } catch (e) {}
    track('email');
  }

  function shareNative() {
    var text = currentMessage();
    if (navigator.share) {
      navigator.share({ title: state.product ? state.product.title : 'SONIKA', text: text }).then(function () {
        track('native');
      }).catch(function () {});
      return;
    }
    copyText(text).then(function () {
      toast('Mensaje y enlace copiados. Pégalo donde quieras.');
    });
  }

  function copyText(text, silent) {
    var done = function (ok) {
      if (!silent) toast(ok ? 'Copiado.' : 'No se pudo copiar. Selecciona el texto.');
      return ok;
    };
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(String(text || '')).then(function () {
        return done(true);
      }, function () {
        return Promise.resolve(fallbackCopy(text, silent));
      });
    }
    return Promise.resolve(fallbackCopy(text, silent));
  }

  function fallbackCopy(text, silent) {
    var ta = document.createElement('textarea');
    ta.value = String(text || '');
    ta.setAttribute('readonly', '');
    ta.style.cssText = 'position:fixed;left:-9999px;top:0;opacity:0';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    var ok = false;
    try { ok = document.execCommand('copy'); } catch (e) {}
    ta.remove();
    if (!silent) toast(ok ? 'Copiado.' : 'No se pudo copiar. Selecciona el texto.');
    return ok;
  }

  function toast(msg, duration) {
    var el = document.getElementById('skshare-toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'skshare-toast';
      el.className = 'skshare__toast';
      document.body.appendChild(el);
    }
    el.textContent = msg;
    el.classList.add('is-on');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () { el.classList.remove('is-on'); }, duration || 2200);
  }

  function loadProductMeta() {
    if (state.mode !== 'partner' || !P.approved || !P.code || !state.product || !state.product.ref) return;
    fetch('/api/v1/public/store/products/' + encodeURIComponent(state.product.ref), {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    }).then(function (r) { return r.json(); }).then(function (j) {
      if (!j || !j.ok || !j.data) return;
      var tags = j.data.tags;
      if (typeof tags === 'string') {
        try { tags = JSON.parse(tags); } catch (e) { tags = []; }
      }
      state.isOutlet = Array.isArray(tags) && tags.some(function (t) {
        return /outlet|liquidaci|margen_bajo|bajo_margen/.test(normalize(t));
      });
      applyProductMeta();
    }).catch(function () {});
  }

  function applyProductMeta() {
    if (!root) return;
    var earn = root.querySelector('#skshare-earn');
    if (earn) earn.innerHTML = money(estimateCommission()) + '<small>Comisión estimada por unidad</small>';
    var rate = root.querySelector('#skshare-rate');
    if (rate) rate.textContent = pct(currentRate()) + (state.isOutlet ? ' outlet' : ' actual');
    var note = root.querySelector('#skshare-pnote');
    if (note) note.innerHTML = commissionNote();
    var meter = root.querySelector('.skshare__meter i');
    if (meter) meter.style.width = progressPct() + '%';
    var meta = root.querySelector('#skshare-product-meta');
    if (meta && state.isOutlet) meta.textContent = 'Producto outlet · comisión 2%';
  }

  function openStory(kind) {
    var labels = {
      whatsapp: 'Estado de WhatsApp',
      instagram: 'Historia de Instagram',
      facebook: 'Historia de Facebook'
    };
    state.preview = { kind: kind, label: labels[kind] || 'Historia' };
    toast('Creando tu imagen…', 1400);
    ensureStoryImage().then(function () {
      if (!root || !state.preview) {
        if (root && state.story && state.story.url) {
          try { URL.revokeObjectURL(state.story.url); } catch (e) {}
          state.story = null;
        }
        return;
      }
      render();
      setTimeout(function () {
        var close = root && root.querySelector('[data-skshare-imgclose]');
        if (close) { try { close.focus({ preventScroll: true }); } catch (e) { close.focus(); } }
      }, 20);
      track('story_' + kind);
    }).catch(function () {
      state.preview = null;
      toast('No se pudo crear la imagen. Intenta de nuevo.');
    });
  }

  function closePreview() {
    state.preview = null;
    render();
  }

  function ensureStoryImage() {
    if (state.story && state.story.blob) return Promise.resolve(state.story);
    if (state.storyPromise) return state.storyPromise;
    state.storyPromise = createStoryBlob(state.product, state.mode).then(function (blob) {
      var url = URL.createObjectURL(blob);
      state.story = { blob: blob, url: url };
      state.storyPromise = null;
      return state.story;
    }, function (err) {
      state.storyPromise = null;
      throw err;
    });
    return state.storyPromise;
  }

  function storyFilename() {
    var ref = String((state.product && state.product.ref) || 'producto').replace(/[^a-z0-9_-]+/gi, '-').toLowerCase();
    return 'sonika-' + ref + '-' + (state.mode === 'partner' ? 'partner' : 'recomendado') + '.png';
  }

  function shareStoryImage() {
    if (!state.story || !state.story.blob) return;
    var file = null;
    try { file = new File([state.story.blob], storyFilename(), { type: 'image/png' }); } catch (e) {}
    var text = currentMessage();
    if (file && navigator.canShare && navigator.canShare({ files: [file] })) {
      navigator.share({ files: [file], text: text, title: state.product.title }).then(function () {
        track('story_share');
      }).catch(function (err) {
        if (err && err.name === 'AbortError') return;
        fallbackStoryDownload();
      });
      return;
    }
    fallbackStoryDownload();
  }

  function fallbackStoryDownload() {
    if (!state.story) return;
    downloadBlob(state.story.blob, storyFilename());
    copyText(currentMessage(), true);
    toast('Imagen descargada y mensaje copiado. Súbela como historia o estado.');
    track('story_download');
  }

  function downloadStory() {
    if (!state.story) return;
    downloadBlob(state.story.blob, storyFilename());
    toast('Imagen descargada.');
    track('story_download');
  }

  function copyStoryLink() {
    copyText(currentUrl(), true).then(function (ok) {
      toast(ok ? 'Enlace copiado. Pégalo en el sticker o en el texto del estado.' : 'No se pudo copiar. Selecciona el enlace del producto.');
    });
    track('story_link_copy');
  }

  function downloadBlob(blob, name) {
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = name;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    setTimeout(function () {
      if (a.parentNode) a.parentNode.removeChild(a);
      try { URL.revokeObjectURL(url); } catch (e) {}
    }, 1200);
  }

  function createStoryBlob(product, mode) {
    return new Promise(function (resolve, reject) {
      var canvas = document.createElement('canvas');
      var isSquare = IMG.format === 'square';
      canvas.width = 1080;
      canvas.height = isSquare ? 1080 : 1920;
      var ctx = canvas.getContext('2d');
      if (!ctx) { reject(new Error('canvas')); return; }
      var shareLink = currentUrl();
      var done = false;
      var finished = false;

      function finish() {
        if (finished) return;
        finished = true;
        if (canvas.toBlob) {
          canvas.toBlob(function (blob) {
            if (blob) resolve(blob);
            else reject(new Error('blob'));
          }, 'image/png', 0.95);
        } else {
          try { resolve(dataUrlToBlob(canvas.toDataURL('image/png'))); } catch (e) { reject(e); }
        }
      }

      function drawAll(img) {
        if (done) return;
        done = true;
        try {
          if (isSquare) drawStorySquare(ctx, img, product, mode, shareLink);
          else drawStory(ctx, img, product, mode, shareLink);
          finish();
        } catch (e) {
          reject(e);
        }
      }

      var src = product.imageUrl || '';
      if (!src) { drawAll(null); return; }
      var img = new Image();
      img.onload = function () { drawAll(img); };
      img.onerror = function () { drawAll(null); };
      try {
        var abs = new URL(src, location.href);
        if (abs.origin !== location.origin) img.crossOrigin = 'anonymous';
      } catch (e) {}
      img.src = src;
      setTimeout(function () { if (!done) drawAll(null); }, 2600);
    });
  }

  function drawStory(ctx, img, product, mode, link) {
    var W = 1080, H = 1920;
    ctx.fillStyle = IMG.bg;
    ctx.fillRect(0, 0, W, H);

    ctx.fillStyle = IMG.ink;
    ctx.fillRect(0, 0, W, 150);
    ctx.fillStyle = IMG.bg;
    ctx.textBaseline = 'middle';
    ctx.font = '900 44px Sora, system-ui, sans-serif';
    ctx.fillText(IMG.brand, 72, 76);
    ctx.fillStyle = IMG.accent;
    ctx.fillRect(72, 110, 64, 6);
    ctx.textBaseline = 'alphabetic';

    ctx.fillStyle = IMG.muted;
    ctx.font = '800 23px Sora, system-ui, sans-serif';
    ctx.fillText(mode === 'partner' ? 'PARTNER SONIKA' : 'RECOMENDADO', 72, 230);

    var cx = 72, cy = 260, cw = 936, ch = 700;
    ctx.fillStyle = '#fff';
    roundRect(ctx, cx, cy, cw, ch, 32);
    ctx.fill();
    ctx.strokeStyle = IMG.cardBorder;
    ctx.lineWidth = 2;
    ctx.stroke();

    if (img && img.width) {
      drawImageContain(ctx, img, cx + 48, cy + 48, cw - 96, ch - 96);
    } else {
      ctx.fillStyle = '#efeee9';
      roundRect(ctx, cx + 48, cy + 48, cw - 96, ch - 96, 24);
      ctx.fill();
      ctx.fillStyle = '#c9c5bb';
      ctx.font = '900 220px Sora, system-ui, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('♪', cx + cw / 2, cy + ch / 2 + 70);
      ctx.textAlign = 'left';
    }

    var title = product.title || 'Producto SONIKA';
    var titleFont = '800 58px Sora, system-ui, sans-serif';
    var lines = wrapText(ctx, title, 936, titleFont);
    if (lines.length > 3) {
      lines = lines.slice(0, 3);
      lines[2] = lines[2].replace(/\s+\S*$/, '') + '…';
    }
    ctx.fillStyle = IMG.ink;
    ctx.font = titleFont;
    var ty = 1030;
    lines.forEach(function (line, i) { ctx.fillText(line, 72, ty + i * 66); });

    var priceY = ty + lines.length * 66 + 30;
    if (product.priceLabel) {
      ctx.fillStyle = IMG.accent;
      ctx.font = '900 74px Sora, system-ui, sans-serif';
      ctx.fillText(product.priceLabel, 72, priceY);
    }

    if (mode === 'partner' && P.name) {
      ctx.fillStyle = IMG.muted;
      ctx.font = '700 28px Sora, system-ui, sans-serif';
      ctx.fillText('Recomendado por ' + String(P.name).slice(0, 42), 72, priceY + 54);
    }

    if (IMG.showLink) {
      var linkText = String(link || '').replace(/^https?:\/\//, '').replace(/\/$/, '');
      var linkLabel = mode === 'partner' ? IMG.labelPartner : IMG.labelFriend;
      var linkY = 1360;
      ctx.fillStyle = IMG.muted;
      ctx.font = '800 20px Sora, system-ui, sans-serif';
      ctx.fillText(linkLabel, 72, linkY - 14);
      ctx.fillStyle = '#fff';
      roundRect(ctx, 72, linkY, 936, 64, 14);
      ctx.fill();
      ctx.strokeStyle = IMG.cardBorder;
      ctx.lineWidth = 2;
      ctx.stroke();
      var linkFont = '800 30px Sora, system-ui, sans-serif';
      ctx.font = linkFont;
      if (ctx.measureText(linkText).width > 860) linkFont = '800 24px Sora, system-ui, sans-serif';
      ctx.font = linkFont;
      ctx.fillStyle = IMG.ink;
      ctx.textAlign = 'center';
      ctx.fillText(linkText, 72 + 468, linkY + 41);
      ctx.textAlign = 'left';
    }

    var by = IMG.showLink ? 1450 : 1360;
    ctx.fillStyle = IMG.ink;
    roundRect(ctx, 72, by, 936, 112, 18);
    ctx.fill();
    ctx.fillStyle = IMG.bg;
    ctx.font = '800 34px Sora, system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(IMG.cta, 72 + 468, by + 71);
    ctx.textAlign = 'left';

    ctx.fillStyle = IMG.muted;
    ctx.font = '600 24px Sora, system-ui, sans-serif';
    ctx.fillText(IMG.footer, 72, IMG.showLink ? 1630 : 1540);
  }

  function drawStorySquare(ctx, img, product, mode, link) {
    var W = 1080, H = 1080;
    ctx.fillStyle = IMG.bg;
    ctx.fillRect(0, 0, W, H);

    ctx.fillStyle = IMG.ink;
    ctx.fillRect(0, 0, W, 96);
    ctx.fillStyle = IMG.bg;
    ctx.textBaseline = 'middle';
    ctx.font = '900 38px Sora, system-ui, sans-serif';
    ctx.fillText(IMG.brand, 60, 49);
    ctx.fillStyle = IMG.accent;
    ctx.fillRect(60, 74, 56, 5);
    ctx.textBaseline = 'alphabetic';

    ctx.fillStyle = IMG.muted;
    ctx.font = '800 20px Sora, system-ui, sans-serif';
    ctx.fillText(mode === 'partner' ? 'PARTNER SONIKA' : 'RECOMENDADO', 60, 142);

    var cx = 60, cy = 170, cw = 420, ch = 420;
    ctx.fillStyle = '#fff';
    roundRect(ctx, cx, cy, cw, ch, 24);
    ctx.fill();
    ctx.strokeStyle = IMG.cardBorder;
    ctx.lineWidth = 2;
    ctx.stroke();
    if (img && img.width) {
      drawImageContain(ctx, img, cx + 32, cy + 32, cw - 64, ch - 64);
    } else {
      ctx.fillStyle = '#efeee9';
      roundRect(ctx, cx + 32, cy + 32, cw - 64, ch - 64, 18);
      ctx.fill();
      ctx.fillStyle = '#c9c5bb';
      ctx.font = '900 150px Sora, system-ui, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText('♪', cx + cw / 2, cy + ch / 2 + 50);
      ctx.textAlign = 'left';
    }

    var tx = 520, tw = 488;
    var titleFont = '800 44px Sora, system-ui, sans-serif';
    var lines = wrapText(ctx, product.title || 'Producto SONIKA', tw, titleFont);
    if (lines.length > 3) {
      lines = lines.slice(0, 3);
      lines[2] = lines[2].replace(/\s+\S*$/, '') + '…';
    }
    ctx.fillStyle = IMG.ink;
    ctx.font = titleFont;
    var ty = 220;
    lines.forEach(function (line, i) { ctx.fillText(line, tx, ty + i * 52); });

    var priceY = ty + lines.length * 52 + 34;
    if (product.priceLabel) {
      ctx.fillStyle = IMG.accent;
      ctx.font = '900 58px Sora, system-ui, sans-serif';
      ctx.fillText(product.priceLabel, tx, priceY);
    }

    if (mode === 'partner' && P.name) {
      ctx.fillStyle = IMG.muted;
      ctx.font = '700 22px Sora, system-ui, sans-serif';
      ctx.fillText('Recomendado por ' + String(P.name).slice(0, 42), tx, priceY + 42);
    }

    if (IMG.showLink) {
      var linkText = String(link || '').replace(/^https?:\/\//, '').replace(/\/$/, '');
      var linkLabel = mode === 'partner' ? IMG.labelPartner : IMG.labelFriend;
      var linkY = 630;
      ctx.fillStyle = IMG.muted;
      ctx.font = '800 17px Sora, system-ui, sans-serif';
      ctx.fillText(linkLabel, 60, linkY - 12);
      ctx.fillStyle = '#fff';
      roundRect(ctx, 60, linkY, 960, 56, 12);
      ctx.fill();
      ctx.strokeStyle = IMG.cardBorder;
      ctx.lineWidth = 2;
      ctx.stroke();
      var linkFont = '800 24px Sora, system-ui, sans-serif';
      ctx.font = linkFont;
      if (ctx.measureText(linkText).width > 880) linkFont = '800 20px Sora, system-ui, sans-serif';
      ctx.font = linkFont;
      ctx.fillStyle = IMG.ink;
      ctx.textAlign = 'center';
      ctx.fillText(linkText, 60 + 480, linkY + 36);
      ctx.textAlign = 'left';
    }

    var by = IMG.showLink ? 720 : 630;
    ctx.fillStyle = IMG.ink;
    roundRect(ctx, 60, by, 960, 92, 16);
    ctx.fill();
    ctx.fillStyle = IMG.bg;
    ctx.font = '800 30px Sora, system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(IMG.cta, 60 + 480, by + 58);
    ctx.textAlign = 'left';

    ctx.fillStyle = IMG.muted;
    ctx.font = '600 20px Sora, system-ui, sans-serif';
    ctx.fillText(IMG.footer, 60, IMG.showLink ? 860 : 780);
  }

  function roundRect(ctx, x, y, w, h, r) {
    r = Math.min(r, w / 2, h / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + w, y, x + w, y + h, r);
    ctx.arcTo(x + w, y + h, x, y + h, r);
    ctx.arcTo(x, y + h, x, y, r);
    ctx.arcTo(x, y, x + w, y, r);
    ctx.closePath();
  }

  function drawImageContain(ctx, img, x, y, w, h) {
    var ratio = Math.min(w / img.width, h / img.height);
    var dw = img.width * ratio;
    var dh = img.height * ratio;
    ctx.drawImage(img, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
  }

  function wrapText(ctx, text, maxWidth, font) {
    ctx.font = font;
    var words = String(text || '').split(/\s+/);
    var lines = [];
    var line = '';
    words.forEach(function (word) {
      var test = line ? line + ' ' + word : word;
      if (ctx.measureText(test).width > maxWidth && line) {
        lines.push(line);
        line = word;
      } else {
        line = test;
      }
    });
    if (line) lines.push(line);
    return lines.slice(0, 4);
  }

  function dataUrlToBlob(dataUrl) {
    var parts = dataUrl.split(',');
    var mime = parts[0].match(/:(.*?);/)[1];
    var bstr = atob(parts[1]);
    var n = bstr.length;
    var u8 = new Uint8Array(n);
    while (n--) u8[n] = bstr.charCodeAt(n);
    return new Blob([u8], { type: mime });
  }

  function track(action) {
    try {
      window.dispatchEvent(new CustomEvent('sonika:share', {
        detail: {
          mode: state.mode,
          action: action,
          ref: state.product ? state.product.ref : '',
          partner: !!P.approved
        }
      }));
    } catch (e) {}
  }

  function updatePartnerTriggers() {
    var nodes = document.querySelectorAll('.skshare-trigger--partner');
    var mode = String(S.partner_note_mode || 'auto');
    var custom = String(S.partner_note_text || '').trim();
    Array.prototype.forEach.call(nodes, function (btn) {
      var small = btn.querySelector('small');
      if (!small) return;
      var cents = Math.max(0, parseInt(btn.getAttribute('data-price-cents') || '0', 10) || 0);
      var rate = Number(P.rate) || 0.03;
      small.style.display = '';
      if (mode === 'none') {
        small.textContent = '';
        small.style.display = 'none';
        btn.setAttribute('title', 'Partner SONIKA');
        return;
      }
      if (mode === 'custom') {
        small.textContent = custom || 'Partner SONIKA';
        btn.setAttribute('title', 'Partner SONIKA');
        return;
      }
      if (mode === 'rate') {
        if (P.logged_in && P.approved) {
          small.textContent = 'Tu tasa ' + pct(rate);
          btn.setAttribute('title', 'Partner SONIKA · tu tasa actual');
        } else if (P.logged_in) {
          small.textContent = 'En revisión';
          btn.setAttribute('title', 'Partner SONIKA · perfil en revisión');
        } else {
          small.textContent = 'Hasta 7%';
          btn.setAttribute('title', 'Partner SONIKA · comisiones hasta 7%');
        }
        return;
      }
      if (mode === 'commission') {
        if (P.logged_in && P.approved) {
          small.textContent = cents > 0 ? ('Gana ~' + money(cents * rate)) : ('Tu tasa ' + pct(rate));
          btn.setAttribute('title', 'Partner SONIKA · comisión estimada por unidad');
        } else if (P.logged_in) {
          small.textContent = 'En revisión';
          btn.setAttribute('title', 'Partner SONIKA · perfil en revisión');
        } else {
          small.textContent = 'Hasta 7%';
          btn.setAttribute('title', 'Partner SONIKA · comisiones hasta 7%');
        }
        return;
      }
      if (P.logged_in && P.approved && P.code) {
        small.textContent = cents > 0 ? ('Gana ~' + money(cents * rate)) : ('Tu tasa ' + pct(rate));
        btn.setAttribute('title', 'Partner SONIKA · comisión estimada por unidad');
      } else if (P.logged_in && !P.approved) {
        small.textContent = 'En revisión';
        btn.setAttribute('title', 'Partner SONIKA · perfil en revisión');
      } else {
        small.textContent = 'Hasta 7%';
        btn.setAttribute('title', 'Partner SONIKA · comisiones hasta 7%');
      }
    });
  }

  updatePartnerTriggers();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', updatePartnerTriggers);
  window.addEventListener('sonika-quote', updatePartnerTriggers);
  window.addEventListener('wwi-cart-change', updatePartnerTriggers);
  setTimeout(updatePartnerTriggers, 350);
  setTimeout(updatePartnerTriggers, 1200);

  document.addEventListener('click', function (e) {
    var btn = e.target && e.target.closest ? e.target.closest('[data-skshare]') : null;
    if (!btn) return;
    e.preventDefault();
    openShare(btn.getAttribute('data-skshare'), readProduct(btn));
  });

  document.addEventListener('keydown', function (e) {
    if (!root) return;
    if (e.key === 'Escape') {
      if (state.preview) { closePreview(); } else { closeShare(); }
      return;
    }
    if (e.key !== 'Tab') return;
    var scope = state.preview ? (root.querySelector('[data-skshare-imgview]') || root) : root;
    var nodes = scope.querySelectorAll('button,[href],input,textarea,select,[tabindex]:not([tabindex="-1"])');
    var focusable = Array.prototype.filter.call(nodes, function (el) {
      return !el.disabled && el.offsetParent !== null;
    });
    if (!focusable.length) return;
    var first = focusable[0];
    var last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    }
  });
})();
