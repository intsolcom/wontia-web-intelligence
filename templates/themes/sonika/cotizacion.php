<?php
use App\Core\Session;

$__token = strtolower(trim((string)($segments[1] ?? '')));
$__svc = new \App\Services\QuoteService();
$__quote = $__svc->findByPublicToken($__token, false);
$__legacyCode = false;
if (!$__quote && preg_match('/^COT-[A-Z0-9-]{4,36}$/', strtoupper($__token))) {
    $__quote = $__svc->findByCode(strtoupper($__token), false);
    if ($__quote) { $__token = (string)$__quote['public_token']; $__legacyCode = true; }
}
$__settings = $__svc->settings();
$__admin = Session::isLoggedIn() && in_array((string)Session::userRole(), \App\Services\QuoteService::ROLES, true);
$__printMode = !empty($_GET['print']);
$__signedPrint = $__printMode && $__quote && $__svc->verifySigned($__quote, 'print', (string)($_GET['exp'] ?? ''), (string)($_GET['sig'] ?? ''));
$__publicAvailable = $__quote && $__svc->isPublicAvailable($__quote);
$__showQuote = $__quote && ($__admin || $__signedPrint || $__publicAvailable);
$__access = $__admin || $__signedPrint || !$__settings['quote_require_verification'] || ($__quote && $__svc->hasAccess($__quote));
$__gateError = '';

header('X-Robots-Tag: noindex, nofollow, noarchive');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');

if (!$__showQuote) {
    http_response_code($__quote ? 410 : 404);
    sonika_site_head('Cotización no disponible - SONIKA', 'Este enlace privado no está disponible.');
    ?>
    <section class="wrap qdoc-gate"><div class="qdoc-gate__card"><div class="qdoc-gate__mark">SONIKA</div><h1>Cotización no disponible</h1><p>El enlace venció, fue desactivado o no es válido. Solicita un nuevo enlace a nuestro equipo.</p><a class="qdoc-gate__link" href="/tienda">Volver a la tienda</a></div></section>
    <?php
    sonika_site_footer();
    return;
}

if ($__printMode && !$__signedPrint) {
    http_response_code(403);
    echo 'PDF no autorizado.';
    return;
}

if (!$__access && !$__printMode) {
    \App\Core\Session::start();
    $__nonceKey = 'quote_gate_' . (int)$__quote['id'];
    $__nonce = (string)Session::get($__nonceKey, '');
    if ($__nonce === '') {
        $__nonce = bin2hex(random_bytes(24));
        Session::set($__nonceKey, $__nonce);
    }
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        $__postedNonce = (string)($_POST['_nonce'] ?? '');
        if (!hash_equals($__nonce, $__postedNonce)) {
            $__gateError = 'La sesión del formulario expiró. Intenta de nuevo.';
        } else {
            $__result = $__svc->verifyAccess($__quote, (string)($_POST['identity'] ?? ''), (string)($_SERVER['REMOTE_ADDR'] ?? ''));
            if (!empty($__result['ok'])) {
                $__svc->grantAccess($__quote);
                header('Location: /cotizacion/' . rawurlencode($__token), true, 303);
                exit;
            }
            $__gateError = (string)($__result['message'] ?? 'No pudimos verificar el acceso.');
        }
    }
    sonika_site_head('Cotización privada - SONIKA', 'Acceso privado a tu cotización SONIKA.');
    ?>
    <link rel="stylesheet" href="/assets/themes/sonika/cotizacion.css?v=20261029-9.15.6" />
    <section class="qdoc-gate">
      <div class="qdoc-gate__card">
        <div class="qdoc-gate__brand"><?= sonika_logo_tag() ?></div>
        <span class="qdoc-gate__eyebrow">ENLACE PRIVADO</span>
        <h1>Tu cotización está protegida</h1>
        <p>Para proteger tus datos, confirma el correo o los últimos 4 dígitos del documento con el que generaste esta cotización.</p>
        <?php if ($__gateError !== ''): ?><div class="qdoc-gate__error" role="alert"><?= sonika_esc($__gateError) ?></div><?php endif; ?>
        <form method="post" action="/cotizacion/<?= rawurlencode($__token) ?>" autocomplete="off">
          <input type="hidden" name="_nonce" value="<?= sonika_esc($__nonce) ?>" />
          <label for="qdocIdentity">Correo o últimos 4 dígitos del documento</label>
          <input id="qdocIdentity" name="identity" required maxlength="190" autocomplete="off" inputmode="email" placeholder="correo@empresa.com o 1234" />
          <button type="submit">Acceder a mi cotización</button>
        </form>
        <small>Por seguridad, este acceso caduca automáticamente. No compartas este enlace con terceros.</small>
      </div>
    </section>
    <?php
    sonika_site_footer();
    return;
}

if (!$__printMode && $__legacyCode) {
    header('Location: /cotizacion/' . rawurlencode($__token), true, 302);
    exit;
}
if (!$__printMode) {
    $__svc->markView($__quote, (string)($_SERVER['REMOTE_ADDR'] ?? ''));
    sonika_site_head('Cotización ' . (string)$__quote['code'] . ' - SONIKA', 'Consulta segura de tu cotización SONIKA.');
    $__pdfUrl = !empty($__settings['quote_pdf_enabled']) ? $__svc->signedPath($__quote, 'pdf', 900) : '';
    $__printUrl = $__svc->signedPath($__quote, 'print', 900);
    $__publicUrl = $__svc->publicUrl($__quote);
    $__continueDisabled = !empty($__quote['expired']);
    $__checkoutItems = [];
    foreach ((array)($__quote['items'] ?? []) as $__it) {
        if (($__it['available'] ?? true) === false || (($__it['issue'] ?? 'ok') !== 'ok')) $__continueDisabled = true;
    }
    foreach ((array)($__quote['items'] ?? []) as $__it) {
        $__checkoutItems[] = [
            'handle' => (string)($__it['ref'] ?? ''),
            'title' => (string)($__it['title'] ?? ''),
            'price' => ((int)($__it['original_unit_cents'] ?? 0)) / 100,
            'img' => (string)($__it['image'] ?? ''),
            'category' => (string)($__it['category'] ?? 'Productos'),
            'qty' => max(1, (int)($__it['qty'] ?? 1)),
        ];
    }
    ?>
    <link rel="stylesheet" href="/assets/themes/sonika/cotizacion.css?v=20261029-9.15.6" />
    <section class="wrap qdoc-page" id="publicQuote">
      <div class="qdoc-toolbar">
        <div><span class="qdoc-toolbar__eyebrow">DOCUMENTO PRIVADO</span><b>Cotización <?= sonika_esc((string)$__quote['code']) ?></b></div>
        <div class="qdoc-toolbar__actions">
          <button type="button" class="qdoc-btn" id="qdocCopyLink">Copiar enlace</button>
          <button type="button" class="qdoc-btn qdoc-btn--continue" id="qdocContinue"<?= $__continueDisabled ? ' disabled' : '' ?>>Continuar compra</button>
        </div>
      </div>
      <?php if ($__pdfUrl !== ''): ?>
      <div class="qdoc-quote-actions">
        <span class="qdoc-quote-actions__label">Comparte o guarda tu cotización</span>
        <div class="qdoc-quote-actions__buttons">
          <?php if (!empty($__settings['quote_whatsapp_enabled'])): ?><button type="button" class="qdoc-btn qdoc-btn--whatsapp" id="qdocWhatsapp" data-pdf="<?= sonika_esc($__pdfUrl) ?>" data-code="<?= sonika_esc((string)$__quote['code']) ?>">Enviar por WhatsApp</button><?php endif; ?>
          <a class="qdoc-btn qdoc-btn--primary" id="qdocDownload" href="<?= sonika_esc($__pdfUrl) ?>" rel="nofollow" download>Descargar PDF</a>
        </div>
      </div>
      <?php endif; ?>
      <div class="qdoc-page__paper">
        <?php $__printMode = false; include __DIR__ . '/cotizacion-doc.php'; ?>
      </div>
      <div class="qdoc-page__footnote"><span>Enlace privado protegido · Disponible hasta <?= sonika_esc(substr((string)($__quote['public_expires_at'] ?? ''), 0, 10)) ?></span><span>SONIKA · sonika.com.co</span></div>
    </section>
    <script>
    (function () {
      var code = <?= json_encode((string)$__quote['code'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      var token = <?= json_encode((string)$__quote['public_token'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      var checkout = <?= json_encode($__checkoutItems, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
      var publicUrl = <?= json_encode($__publicUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
      var disabled = <?= $__continueDisabled ? 'true' : 'false' ?>;
      function toast(message) {
        var el = document.createElement('div');
        el.className = 'qdoc-toast';
        el.textContent = message;
        document.body.appendChild(el);
        setTimeout(function () { el.remove(); }, 2600);
      }
      function copy(text) {
        if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (e) {}
        ta.remove();
        return Promise.resolve();
      }
      function track(event) {
        fetch('/api/v1/public/store/quotes/' + encodeURIComponent(token) + '/event', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ event: event }), keepalive: true }).catch(function () {});
      }
      function sharePdfUrl(url) {
        return fetch(url, { credentials: 'same-origin' }).then(function (r) {
          if (!r.ok) throw new Error('PDF no disponible');
          return r.blob();
        }).then(function (blob) {
          track('whatsapp');
          var file = new File([blob], 'Cotizacion-' + code + '.pdf', { type: 'application/pdf' });
          if (navigator.canShare && navigator.canShare({ files: [file] })) {
            return navigator.share({ files: [file], title: 'Cotizaci\u00f3n ' + code, text: 'Cotizaci\u00f3n SONIKA ' + code + ' \u00b7 ' + publicUrl });
          }
          var a = document.createElement('a');
          a.href = URL.createObjectURL(blob);
          a.download = 'Cotizacion-' + code + '.pdf';
          a.click();
          setTimeout(function () { URL.revokeObjectURL(a.href); }, 3000);
          window.open('https://wa.me/?text=' + encodeURIComponent('Cotizaci\u00f3n SONIKA ' + code + ' \u00b7 ' + publicUrl), '_blank', 'noopener');
          toast('PDF descargado. Elige el contacto y adj\u00fantalo en WhatsApp.');
          return true;
        });
      }
      var copyBtn = document.getElementById('qdocCopyLink');
      if (copyBtn) copyBtn.addEventListener('click', function () {
        copy(location.origin + <?= json_encode($__publicUrl, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>).then(function () {
          copyBtn.textContent = 'Copiado';
          setTimeout(function () { copyBtn.textContent = 'Copiar enlace'; }, 1600);
          fetch('/api/v1/public/store/quotes/' + encodeURIComponent(token) + '/event', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ event: 'link_copy' }), keepalive: true }).catch(function () {});
        });
      });
      var shareBtn = document.getElementById('qdocWhatsapp');
      if (shareBtn) shareBtn.addEventListener('click', function () {
        var url = shareBtn.getAttribute('data-pdf') || '';
        var label = shareBtn.textContent;
        if (!url) return;
        shareBtn.disabled = true;
        shareBtn.textContent = 'Preparando PDF...';
        sharePdfUrl(url).catch(function () {
          toast('No pudimos preparar el PDF. Intenta de nuevo.');
        }).then(function () {
          shareBtn.disabled = false;
          shareBtn.textContent = label;
        });
      });
      var downloadBtn = document.getElementById('qdocDownload');
      if (downloadBtn) downloadBtn.addEventListener('click', function () { track('pdf'); });
      var continueBtn = document.getElementById('qdocContinue');
      if (continueBtn) continueBtn.addEventListener('click', function () {
        if (disabled) return;
        var cart = [];
        try { cart = JSON.parse(localStorage.getItem('sonika_cart') || '[]') || []; } catch (e) { cart = []; }
        checkout.forEach(function (item) {
          var found = cart.filter(function (c) { return (c.handle || c.ref) === item.handle; })[0];
          if (found) found.qty = Math.max(1, Math.min(99, (parseInt(found.qty, 10) || 1) + item.qty));
          else cart.push({ handle: item.handle, title: item.title, price: item.price, img: item.img, qty: item.qty, category: item.category });
        });
        try {
          localStorage.setItem('sonika_quote_code', code);
          localStorage.setItem('sonika_cart', JSON.stringify(cart));
          localStorage.setItem('sonika_pay_method', 'request');
          window.dispatchEvent(new CustomEvent('wwi-cart-change', { detail: { items: cart } }));
        } catch (e) {}
        fetch('/api/v1/public/store/quotes/' + encodeURIComponent(token) + '/event', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ event: 'checkout' }), keepalive: true }).catch(function () {});
        window.location.href = '/checkout';
      });
    })();
    </script>
    <?php
    sonika_site_footer();
    return;
}

$__printMode = true;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="robots" content="noindex,nofollow,noarchive" />
<title>Cotización <?= sonika_esc((string)$__quote['code']) ?> - SONIKA</title>
<link rel="stylesheet" href="/assets/themes/sonika/cotizacion.css?v=20261029-9.15.6" />
<style>@page{size:A4;margin:0}html,body{margin:0;padding:0;background:#fff}.qdoc-print-body{padding:0}.qdoc__paper--print{margin:0 auto;box-shadow:none;border:0;border-radius:0;max-width:none;width:210mm;min-height:0;padding:14mm 14mm 0}</style>
</head>
<body class="qdoc-print-body">
<?php include __DIR__ . '/cotizacion-doc.php'; ?>
</body>
</html>
