<?php
$__orderUuid = (string)($segments[1] ?? '');
$__store = new \App\Services\StoreService();
$__order = $__orderUuid !== '' ? $__store->orderByUuid($__orderUuid) : null;
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: private, no-store, max-age=0');
if (!$__order) {
    http_response_code(404);
    sonika_site_head('Pedido no encontrado — SONIKA', 'No encontramos el pedido solicitado.');
    sonika_breadcrumb(['Pedido' => null]);
    ?>
    <section class="wrap skpay">
      <div class="skpay__empty" style="max-width:560px;margin:40px auto">
        <b>No encontramos este pedido.</b>
        <p class="skpay__muted">Verifica el enlace o escríbenos por WhatsApp para ayudarte.</p>
        <a class="skpay__btn skpay__btn--sm" href="/tienda" style="margin-top:12px">Volver a la tienda</a>
      </div>
    </section>
    <?php
    sonika_site_footer();
    return;
}

$__pay = (new \App\Services\BrebService())->paymentInfo($__order);
$__method = (string)($__order['payment_method'] ?? '');
$__status = (string)($__order['payment_status'] ?? 'pending');
$__proofStatus = (string)($__order['payment_proof_status'] ?? 'none');
$__currency = (string)($__order['currency'] ?? 'COP');
$__money = function (int $cents) use ($__currency): string {
    $sym = $__currency === 'USD' ? 'US$' : ($__currency === 'COP' ? '$' : $__currency . ' ');
    return $sym . number_format($cents / 100, $__currency === 'COP' ? 0 : 2, ',', '.');
};
$__fmtDate = function (?string $date): string {
    if (!$date) return '—';
    try {
        $dt = new \DateTime($date, new \DateTimeZone('UTC'));
        $dt->setTimezone(new \DateTimeZone('America/Bogota'));
        return $dt->format('d/m/Y H:i');
    } catch (\Throwable $e) {
        return (string)$date;
    }
};
$__statusLabel = match ($__status) {
    'paid' => 'Pago confirmado',
    'failed' => 'Pago no completado',
    'refunded' => 'Pedido reembolsado',
    default => 'Pendiente de pago',
};
$__statusClass = $__status === 'paid' ? ' skpay__status-pill--paid' : ($__status === 'failed' ? ' skpay__status-pill--failed' : '');
$__proofLabels = [
    'none' => 'Aún no has adjuntado comprobante.',
    'pending' => 'Comprobante recibido. Estamos verificando el pago.',
    'approved' => 'Comprobante aprobado. Pago verificado.',
    'rejected' => 'El comprobante fue rechazado. Puedes enviar otro.',
    'purged' => 'El comprobante anterior fue depurado. Puedes enviar uno nuevo.',
];
$__proofMsg = $__proofLabels[$__proofStatus] ?? '';
$__gcSvc = new \App\Services\GiftCardService();
$__gcPurchase = $__gcSvc->purchaseForOrder((int)$__order['id']);
$__gcCards = $__gcPurchase ? $__gcSvc->cardsForOrder((int)$__order['id']) : [];
$__gcGiftCards = [];
foreach ($__gcCards as $__gcCard) {
    $__gcGiftCards[] = [
        'code' => (string)$__gcCard['code'],
        'pin' => $__gcSvc->pinFor($__gcCard),
        'amount_cents' => (int)$__gcCard['amount_cents'],
        'balance_cents' => (int)$__gcCard['balance_cents'],
        'recipient_name' => (string)$__gcCard['recipient_name'],
        'recipient_email' => (string)$__gcCard['recipient_email'],
        'expires_at' => (string)($__gcCard['expires_at'] ?? ''),
        'status' => (string)$__gcCard['status'],
    ];
}
sonika_site_head('Pedido ' . (string)$__order['order_number'] . ' — SONIKA', 'Estado y pago de tu pedido.');
sonika_breadcrumb(['Carrito' => '/carrito', 'Pedido' => null]);
?><script>window.SONIKA_PURCHASE=<?= json_encode(['uuid'=>(string)$__order['uuid'],'order_number'=>(string)$__order['order_number'],'status'=>$__status,'value_cents'=>(int)$__order['total_cents'],'currency'=>$__currency], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<section class="wrap skpay" id="skpayConfirm">
  <div class="skpay__confirmation">
    <div class="skpay__card">
      <div class="skpay__confirm-top">
        <div class="skpay__icon" aria-hidden="true"><?= $__status === 'paid' ? '✓' : '✓' ?></div>
        <h1>Pedido recibido</h1>
        <p>Gracias, <?= sonika_esc((string)($__order['customer_name'] ?? '')) ?>. Registramos tu pedido y te contactaremos si necesitamos confirmar algún dato.</p>
        <div class="skpay__order-number">Pedido <b><?= sonika_esc((string)$__order['order_number']) ?></b></div>
        <div style="margin-top:9px"><span class="skpay__status-pill<?= $__statusClass ?>"><?= sonika_esc($__statusLabel) ?></span></div>
        <?php if ($__status === 'pending'): ?>
        <p class="skpay__tiny" style="margin-top:10px">Tu pedido queda sujeto a confirmación del pago y disponibilidad de los productos.</p>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($__gcPurchase): ?>
    <div class="skpay__card" style="margin-top:16px">
      <div class="skpay__section">
        <h2>Tu tarjeta de regalo</h2>
        <?php if ($__status === 'paid' && $__gcGiftCards): ?>
          <p class="skpay__muted" style="margin:0 0 12px">La tarjeta fue emitida y enviada a <b><?= sonika_esc((string)$__gcPurchase['recipient_email']) ?></b><?= !empty($__gcPurchase['delivery_at']) ? ' para el ' . sonika_esc(date('d/m/Y', strtotime((string)$__gcPurchase['delivery_at']))) : '' ?>. También enviamos una copia a tu correo.</p>
          <?php foreach ($__gcGiftCards as $__gc): ?>
          <div style="border:1px solid #e7e5df;border-radius:14px;padding:14px;margin-top:10px;background:#fbfbf8">
            <div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:flex-start">
              <div>
                <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:#777">Código</div>
                <div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:18px;font-weight:800;letter-spacing:.06em;color:#101d3a"><?= sonika_esc($__gc['code']) ?></div>
              </div>
              <div>
                <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:#777">PIN</div>
                <div style="font-family:ui-monospace,Menlo,Consolas,monospace;font-size:20px;font-weight:800;letter-spacing:.2em;color:#101d3a"><?= sonika_esc($__gc['pin']) ?></div>
              </div>
              <div style="text-align:right">
                <div style="font-size:10px;letter-spacing:.1em;text-transform:uppercase;color:#777">Saldo</div>
                <div style="font-weight:800;color:#101d3a"><?= sonika_esc($__money($__gc['balance_cents'])) ?></div>
              </div>
            </div>
            <div style="font-size:11px;color:#667085;margin-top:8px">Para <?= sonika_esc($__gc['recipient_name']) ?> · Vence <?= sonika_esc($__gc['expires_at'] !== '' ? date('d/m/Y', strtotime($__gc['expires_at'])) : 'sin vencimiento') ?></div>
          </div>
          <?php endforeach; ?>
          <p class="skpay__tiny" style="margin:12px 0 0">Guarda el código y el PIN. Puedes consultar el saldo en <a href="/tarjetas-de-regalo#consultar" style="color:#F20D16;font-weight:700">/tarjetas-de-regalo</a>.</p>
        <?php elseif ($__status === 'paid'): ?>
          <div class="skpay__alert skpay__alert--ok">Pago confirmado. Estamos emitiendo la tarjeta; si no la ves en unos minutos, revisa el correo del destinatario o escríbenos.</div>
        <?php else: ?>
          <div class="skpay__alert">Estamos confirmando el pago. Cuando se confirme, enviaremos el código y el PIN a <b><?= sonika_esc((string)$__gcPurchase['recipient_email']) ?></b><?= !empty($__gcPurchase['delivery_at']) ? ' el ' . sonika_esc(date('d/m/Y', strtotime((string)$__gcPurchase['delivery_at']))) : '' ?>.</div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="skpay__grid" style="margin-top:16px">
      <div class="skpay__card">
        <?php if ($__method === 'breb'): ?>
        <div class="skpay__section">
          <h2>Paga con Bre-B</h2>
          <?php if ($__status === 'paid'): ?>
            <div class="skpay__alert skpay__alert--ok">Pago confirmado. Estamos preparando tu pedido.</div>
          <?php else: ?>
            <?php if (!empty($__pay['breb']['qr_image'])): ?>
            <?php
              $__qrSrc = (string)$__pay['breb']['qr_image'];
              $__qrAlt = (string)($__pay['breb']['qr_alt'] ?? 'QR Bre-B de SONIKA');
              $__qrPdf = '/api/v1/public/store/orders/' . rawurlencode((string)$__order['uuid']) . '/breb-qr.pdf';
              $__qrPdfName = 'SONIKA-BreB-' . (preg_replace('/[^A-Za-z0-9._-]+/', '-', (string)$__order['order_number']) ?: 'pedido') . '.pdf';
            ?>
            <div class="skpay__qr-wrap">
              <div class="skpay__qr-col">
                <button type="button" class="skpay__qr-btn" data-sk-qr-open aria-label="Ampliar el QR de Bre-B para escanearlo">
                  <img class="skpay__qr" src="<?= sonika_esc($__qrSrc) ?>" alt="<?= sonika_esc($__qrAlt) ?>" width="220" height="220" loading="eager" fetchpriority="high" decoding="async" />
                  <span class="skpay__qr-hint" aria-hidden="true">Ampliar</span>
                </button>
                <button type="button" class="skpay__btn skpay__btn--sm skpay__qr-open-btn" data-sk-qr-open>Ver QR en grande</button>
                <a class="skpay__qr-pdf" href="<?= sonika_esc($__qrPdf) ?>" download="<?= sonika_esc($__qrPdfName) ?>" rel="noopener">Descargar QR en PDF</a>
              </div>
              <div>
                <p class="skpay__muted" style="margin:0 0 10px"><?= sonika_esc((string)($__pay['breb']['instructions'] ?? 'Escanea el QR y transfiere el monto exacto.')) ?></p>
                <div class="skpay__pay-data">
                  <div class="skpay__pay-row"><span>Monto exacto</span><b><?= sonika_esc($__money((int)$__order['total_cents'])) ?></b><button type="button" class="skpay__copy" data-sk-copy="<?= sonika_esc((string)($__order['total_cents'] / 100)) ?>">Copiar</button></div>
                  <?php if (!empty($__pay['breb']['key'])): ?>
                  <div class="skpay__pay-row"><span>Llave Bre-B</span><b><?= sonika_esc((string)$__pay['breb']['key']) ?></b><button type="button" class="skpay__copy" data-sk-copy="<?= sonika_esc((string)$__pay['breb']['key']) ?>">Copiar</button></div>
                  <?php endif; ?>
                  <?php if (!empty($__pay['breb']['account_name'])): ?>
                  <div class="skpay__pay-row"><span>Titular</span><b><?= sonika_esc((string)$__pay['breb']['account_name']) ?></b></div>
                  <?php endif; ?>
                  <div class="skpay__pay-row"><span>Referencia</span><b><?= sonika_esc((string)$__order['order_number']) ?></b><button type="button" class="skpay__copy" data-sk-copy="<?= sonika_esc((string)$__order['order_number']) ?>">Copiar</button></div>
                </div>
              </div>
            </div>
            <div class="skqr-modal" id="skqrModal" hidden>
              <div class="skqr-modal__backdrop" data-sk-qr-close></div>
              <div class="skqr-modal__panel" role="dialog" aria-modal="true" aria-labelledby="skqrModalTitle">
                <button type="button" class="skqr-modal__close" data-sk-qr-close aria-label="Cerrar">&times;</button>
                <h2 class="skqr-modal__title" id="skqrModalTitle"><?= sonika_esc($__qrAlt) ?></h2>
                <div class="skqr-modal__frame">
                  <img class="skqr-modal__img" src="<?= sonika_esc($__qrSrc) ?>" alt="<?= sonika_esc($__qrAlt) ?>" width="640" height="640" decoding="async" />
                </div>
                <p class="skqr-modal__amount">Monto exacto <b><?= sonika_esc($__money((int)$__order['total_cents'])) ?></b></p>
                <p class="skqr-modal__hint">Escanea el código desde tu app bancaria. Si lo necesitas, descarga el PDF para imprimirlo o guardarlo.</p>
                <a class="skpay__btn skpay__btn--sm skqr-modal__pdf" href="<?= sonika_esc($__qrPdf) ?>" download="<?= sonika_esc($__qrPdfName) ?>" rel="noopener">Descargar QR en PDF</a>
              </div>
            </div>
            <?php else: ?>
            <div class="skpay__alert">El QR de Bre-B aún no está disponible. Escríbenos por WhatsApp para coordinar tu pago.</div>
            <?php endif; ?>
            <div class="skpay__divider"></div>
            <p class="skpay__muted" style="margin:0">Después de pagar, adjunta una captura o el PDF del comprobante. El equipo verificará el pago manualmente.</p>
            <?php endif; ?>
          </div>
          <?php if ($__status === 'pending'): ?>
          <div class="skpay__section">
            <div class="skpay__proof">
              <div class="skpay__proof-head">
                <h3>Comprobante de pago</h3>
                <span class="skpay__badge skpay__badge--soft">Máx. 2 MB</span>
              </div>
              <?php if ($__proofMsg !== ''): ?>
              <p class="skpay__status <?= $__proofStatus === 'approved' ? 'skpay__status--ok' : ($__proofStatus === 'rejected' ? 'skpay__status--err' : 'skpay__status--pending') ?>" aria-live="polite" id="skpayProofState"><?= sonika_esc($__proofMsg) ?></p>
              <?php else: ?>
              <p class="skpay__status skpay__status--pending" aria-live="polite" id="skpayProofState"></p>
              <?php endif; ?>
              <?php if ($__proofStatus !== 'approved'): ?>
              <form id="skpayProofForm" action="/api/v1/public/store/orders/<?= sonika_esc((string)$__order['uuid']) ?>/proof" method="post" enctype="multipart/form-data" data-order-uuid="<?= sonika_esc((string)$__order['uuid']) ?>" data-max-mb="2" style="margin-top:10px">
                <label for="skpayProof" class="skpay__tiny" style="display:block;margin-bottom:6px">Captura o PDF del pago (JPG, PNG, WebP o PDF)</label>
                <input class="skpay__file" id="skpayProof" name="proof" type="file" accept=".jpg,.jpeg,.png,.webp,.pdf,image/jpeg,image/png,image/webp,application/pdf" required />
                <p class="skpay__filelist" id="skpayFileName"></p>
                <div class="skpay__progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" aria-label="Progreso de carga del comprobante"><span id="skpayProofBar"></span></div>
                <div class="skpay__progress-label"><span>Progreso de carga</span><span id="skpayProofPct">0%</span></div>
                <button type="submit" class="skpay__btn skpay__btn--sm" id="skpayProofBtn" style="margin-top:10px">Enviar comprobante</button>
                <p class="skpay__status" id="skpayProofStatus" role="status" aria-live="polite" style="margin-top:9px;min-height:18px"></p>
              </form>
              <?php else: ?>
              <p class="skpay__tiny">Tu comprobante ya fue aprobado. No necesitas enviar otro.</p>
              <?php endif; ?>
            </div>
          </div>
          <?php endif; ?>
        <?php elseif ($__method === 'sistecredito'): ?>
        <div class="skpay__section">
          <h2>Paga con Sistecrédito</h2>
          <?php if ($__status === 'paid'): ?>
            <div class="skpay__alert skpay__alert--ok">Pago aprobado con Sistecrédito. Estamos preparando tu pedido.</div>
          <?php elseif ($__status === 'failed'): ?>
            <div class="skpay__alert">La solicitud con Sistecrédito no fue aprobada o fue abandonada. Puedes intentarlo de nuevo con otro método de pago.</div>
            <a class="skpay__btn skpay__btn--sm" href="/checkout" style="margin-top:12px">Volver al checkout</a>
          <?php else: ?>
            <p class="skpay__muted" style="margin:0 0 12px">Estamos esperando la confirmación de Sistecrédito. La decisión puede tardar unos minutos.</p>
            <?php if (!empty($__pay['sistecredito']['redirect_url'])): ?>
              <a class="skpay__btn skpay__btn--sm" href="<?= sonika_esc((string)$__pay['sistecredito']['redirect_url']) ?>">Continuar solicitud en Sistecrédito</a>
            <?php else: ?>
              <div class="skpay__alert">Estamos preparando el enlace de pago. Esta página se actualizará automáticamente.</div>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php elseif ($__method === 'addi'): ?>
        <div class="skpay__section">
          <h2>Paga con Addi</h2>
          <?php if ($__status === 'paid'): ?>
            <div class="skpay__alert skpay__alert--ok">Crédito Addi aprobado. Estamos preparando tu pedido.</div>
          <?php elseif ($__status === 'failed'): ?>
            <div class="skpay__alert">La solicitud de crédito con Addi no fue aprobada o fue abandonada. Puedes intentarlo de nuevo con otro método de pago.</div>
            <a class="skpay__btn skpay__btn--sm" href="/checkout" style="margin-top:12px">Volver al checkout</a>
          <?php else: ?>
            <p class="skpay__muted" style="margin:0 0 12px">Estamos esperando la confirmación de Addi. La decisión puede tardar unos minutos.</p>
            <?php if (!empty($__pay['addi']['redirect_url'])): ?>
              <a class="skpay__btn skpay__btn--sm" href="<?= sonika_esc((string)$__pay['addi']['redirect_url']) ?>">Continuar solicitud en Addi</a>
            <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php elseif ($__method === 'mercadopago'): ?>
        <div class="skpay__section">
          <h2>Paga con Mercado Pago</h2>
          <?php
            $__mp = (array)($__pay['mercadopago'] ?? []);
            $__mpMode = (string)($__mp['mode'] ?? 'checkout_pro');
            $__mpStatus = (string)($__mp['status'] ?? 'pending');
            $__mpRedirect = (string)($__mp['redirect_url'] ?? ($__pay['external']['link'] ?? ''));
          ?>
          <?php if ($__status === 'paid'): ?>
            <div class="skpay__alert skpay__alert--ok">Pago aprobado por Mercado Pago. Estamos preparando tu pedido.</div>
            <?php if (!empty($__mp['payment_id'])): ?><p class="skpay__tiny">ID de pago: <b><?= sonika_esc((string)$__mp['payment_id']) ?></b></p><?php endif; ?>
          <?php elseif ($__status === 'failed'): ?>
            <div class="skpay__alert">Mercado Pago rechazó el pago o la operación fue cancelada. Puedes intentarlo con otra tarjeta o método de pago.</div>
            <a class="skpay__btn skpay__btn--sm" href="/checkout" style="margin-top:12px">Volver al checkout</a>
          <?php else: ?>
            <?php if ($__mpMode === 'checkout_api'): ?>
              <p class="skpay__muted" style="margin:0 0 12px">Ingresa los datos de tu tarjeta. La tokenización se hace de forma segura en el navegador; SONIKA nunca recibe el número completo.</p>
              <div id="mpBrickContainer" class="skpay__mp-brick"></div>
              <p class="skpay__status skpay__status--pending" id="mpBrickStatus" role="status" aria-live="polite" style="margin-top:10px"></p>
              <noscript><div class="skpay__alert">Activa JavaScript para pagar con tarjeta o usa Checkout Pro.</div></noscript>
            <?php elseif ($__mpRedirect !== ''): ?>
              <p class="skpay__muted" style="margin:0 0 12px">Te llevaremos al entorno seguro de Mercado Pago para completar el pago. Al terminar volverás a esta página.</p>
              <p class="skpay__tiny" style="margin:0 0 12px">Si eliges PSE, selecciona tu banco antes de pulsar “Pagar”. Si el botón aparece deshabilitado, prueba en una ventana incógnito o con “Sin cuenta de Mercado Pago”.</p>
              <a class="skpay__btn" href="<?= sonika_esc($__mpRedirect) ?>" rel="noopener">Ir a Mercado Pago</a>
            <?php else: ?>
              <div class="skpay__alert">Estamos preparando el enlace de pago. Recarga esta página en unos segundos.</div>
            <?php endif; ?>
            <?php if ($__mpStatus === 'pending' && $__mpMode === 'checkout_pro'): ?>
              <p class="skpay__tiny" style="margin-top:10px">El pedido queda pendiente hasta que Mercado Pago confirme el pago. Esta página se actualiza automáticamente.</p>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (!empty($__mp['payment_method_id']) || !empty($__mp['status_detail'])): ?>
          <div class="skpay__pay-data" style="margin-top:12px">
            <?php if (!empty($__mp['payment_method_id'])): ?><div class="skpay__pay-row"><span>Medio de pago</span><b><?= sonika_esc(strtoupper((string)$__mp['payment_method_id'])) ?></b></div><?php endif; ?>
            <?php if (!empty($__mp['installments']) && (int)$__mp['installments'] > 1): ?><div class="skpay__pay-row"><span>Cuotas</span><b><?= (int)$__mp['installments'] ?>x</b></div><?php endif; ?>
            <?php if (!empty($__mp['status_detail'])): ?><div class="skpay__pay-row"><span>Detalle</span><b><?= sonika_esc((string)$__mp['status_detail']) ?></b></div><?php endif; ?>
          </div>
          <?php endif; ?>
        </div>
        <?php elseif ($__method === 'epayco'): ?>
        <div class="skpay__section">
          <h2>Paga con ePayco</h2>
          <?php if ($__status === 'paid'): ?>
            <div class="skpay__alert skpay__alert--ok">Pago confirmado. Estamos preparando tu pedido.</div>
          <?php else: ?>
            <p class="skpay__muted" style="margin:0 0 12px">Completa el pago en la pasarela. El pedido queda pendiente hasta que verifiquemos el pago.</p>
            <?php if (!empty($__pay['external']['link'])): ?>
            <a class="skpay__btn" href="<?= sonika_esc((string)$__pay['external']['link']) ?>" target="_blank" rel="noopener">Ir a <?= sonika_esc((string)($__pay['external']['label'] ?? 'pagar')) ?></a>
            <?php else: ?>
            <div class="skpay__alert">La pasarela aún no está configurada. Escríbenos por WhatsApp.</div>
            <?php endif; ?>
            <p class="skpay__tiny" style="margin-top:10px">Después de pagar, guárdanos el comprobante y escríbenos con tu número de pedido para agilizar la verificación.</p>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="skpay__section">
          <h2>Coordina tu pago</h2>
          <?php if ($__status === 'paid'): ?>
            <div class="skpay__alert skpay__alert--ok">Pago confirmado. Estamos preparando tu pedido.</div>
          <?php else: ?>
            <p class="skpay__muted" style="margin:0">Coordinaremos el pago y el envío contigo.</p>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>

      <aside class="skpay__summary">
        <div class="skpay__summary-head"><h2>Tu pedido</h2><span class="skpay__status-pill<?= $__statusClass ?>"><?= sonika_esc($__statusLabel) ?></span></div>
        <div class="skpay__summary-body">
          <div class="skpay__cart-items" style="margin:0 0 12px">
            <?php foreach ((array)($__order['items'] ?? []) as $__it): ?>
            <div class="skpay__item" style="grid-template-columns:44px minmax(0,1fr) auto">
              <?php if (!empty($__it['image'])): ?><img src="<?= sonika_esc((string)$__it['image']) ?>" alt="" width="44" height="44" loading="lazy" style="width:44px;height:44px" /><?php else: ?><span class="skpay__item-ph" style="width:44px;height:44px" aria-hidden="true">♪</span><?php endif; ?>
              <div><p class="skpay__item-title"><?= sonika_esc((string)($__it['name'] ?? '')) ?></p><div class="skpay__item-meta">x<?= (int)($__it['qty'] ?? 1) ?></div></div>
              <div class="skpay__item-price"><span class="skpay__new"><?= sonika_esc($__money((int)($__it['subtotal_cents'] ?? 0))) ?></span></div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php $__discount = (int)($__order['discount_cents'] ?? 0); $__brebDiscount = (int)($__order['breb_discount_cents'] ?? 0); $__recDiscount = (int)($__order['recommendation_discount_cents'] ?? 0); $__pointsDiscount = (int)($__pay['points_discount_cents'] ?? 0); if ($__brebDiscount === 0 && $__method === 'breb') $__brebDiscount = max(0, $__discount - $__recDiscount); $__couponDiscount = max(0, $__discount - $__brebDiscount - $__recDiscount - $__pointsDiscount); ?>
          <?php if ($__discount > 0): ?>
            <div class="skpay__line"><span>Precio normal</span><span><?= sonika_esc($__money((int)$__order['subtotal_cents'])) ?></span></div>
            <?php if ($__brebDiscount > 0): ?><div class="skpay__line skpay__line--save"><span>Descuento Bre-B</span><span>−<?= sonika_esc($__money($__brebDiscount)) ?></span></div><?php endif; ?>
            <?php if ($__couponDiscount > 0): ?><div class="skpay__line skpay__line--save"><span>Cupón</span><span>−<?= sonika_esc($__money($__couponDiscount)) ?></span></div><?php endif; ?>
            <?php if ($__recDiscount > 0): ?><div class="skpay__line skpay__line--save"><span>Ahorro recomendados</span><span>&minus;<?= sonika_esc($__money($__recDiscount)) ?></span></div><?php endif; ?>
            <?php if ($__pointsDiscount > 0): ?><div class="skpay__line skpay__line--save"><span>Puntos</span><span>−<?= sonika_esc($__money($__pointsDiscount)) ?></span></div><?php endif; ?>
            <div class="skpay__line"><span>Subtotal</span><span><?= sonika_esc($__money((int)$__order['subtotal_cents'] - $__discount)) ?></span></div>
          <?php else: ?>
            <div class="skpay__line"><span>Subtotal</span><span><?= sonika_esc($__money((int)$__order['subtotal_cents'])) ?></span></div>
          <?php endif; ?>
          <div class="skpay__line skpay__line--muted"><span>IVA incluido</span><span><?= sonika_esc($__money((int)($__order['tax_cents'] ?? 0))) ?></span></div>
          <div class="skpay__line"><span>Envío</span><span class="<?= (int)$__order['shipping_cents'] === 0 ? 'skpay__free' : '' ?>"><?= (int)$__order['shipping_cents'] === 0 ? 'Gratis' : sonika_esc($__money((int)$__order['shipping_cents'])) ?></span></div>
          <?php $__gcDiscount = (int)($__order['gift_card_cents'] ?? 0); if ($__gcDiscount > 0): ?>
          <div class="skpay__line skpay__line--save"><span>Tarjeta de regalo</span><span>−<?= sonika_esc($__money($__gcDiscount)) ?></span></div>
          <?php endif; ?>
          <div class="skpay__line skpay__line--total"><span>Total</span><span><?= sonika_esc($__money((int)$__order['total_cents'])) ?></span></div>
          <div class="skpay__details" style="margin-top:14px">
            <div class="skpay__detail-row"><span>Pedido</span><b><?= sonika_esc((string)$__order['order_number']) ?></b></div>
            <div class="skpay__detail-row"><span>Fecha</span><b><?= sonika_esc($__fmtDate((string)($__order['created_at'] ?? ''))) ?></b></div>
            <div class="skpay__detail-row"><span>Pago</span><b><?= sonika_esc(ucfirst($__method === 'breb' ? 'Bre-B' : ($__method === 'mercadopago' ? 'Mercado Pago' : ($__method === 'epayco' ? 'ePayco' : ($__method === 'addi' ? 'Addi' : ($__method === 'sistecredito' ? 'Sistecrédito' : ($__method === 'gift_card' ? 'Tarjeta de regalo' : 'Asistido'))))))) ?></b></div>
          </div>
          <?php $__addr = (array)($__order['shipping_address'] ?? []); if (!empty($__addr['line1']) || !empty($__addr['city']) || !empty($__addr['notes'])): ?>
          <div class="skpay__delivery">
            <b>Entrega</b>
            <?php if (!empty($__addr['line1']) || !empty($__addr['city'])): ?><p><?= sonika_esc(trim(implode(', ', array_filter([(string)($__addr['line1'] ?? ''), (string)($__addr['city'] ?? ''), (string)($__addr['region'] ?? '')], fn($v) => trim($v) !== '')))) ?></p><?php endif; ?>
            <?php if (!empty($__addr['notes'])): ?><p class="skpay__tiny"><b>Indicaciones:</b> <?= sonika_esc((string)$__addr['notes']) ?></p><?php endif; ?>
          </div>
          <?php endif; ?>
          <div class="skpay__actions">
            <a class="skpay__btn skpay__btn--secondary" href="/tienda">Seguir comprando</a>
          </div>
          <p class="skpay__tiny" style="margin-top:11px">Guarda este enlace para consultar el estado de tu pedido.</p>
        </div>
      </aside>
    </div>
  </div>
  <?php if ($__method === 'mercadopago' && $__status === 'pending' && (($__pay['mercadopago']['mode'] ?? '') === 'checkout_api')): ?>
  <script>
  window.SONIKA_MP = <?= json_encode([
    'uuid' => (string)$__order['uuid'],
    'public_key' => (string)((new \App\Services\MercadoPagoService())->publicKey()),
    'amount' => round(((int)$__order['total_cents']) / 100, 2),
    'max_installments' => (int)((new \App\Services\MercadoPagoService())->maxInstallments()),
    'currency' => (string)$__currency,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  </script>
  <?php endif; ?>
  <?php sonika_payments_assets(); ?>
</section>
<?php if ($__method === 'sistecredito' && $__status === 'pending'): ?>
<script>
(function () {
  var uuid = <?= json_encode((string)$__order['uuid'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var tries = 0;
  function poll() {
    if (tries++ > 40) return;
    fetch('/api/v1/public/store/sistecredito/status?uuid=' + encodeURIComponent(uuid), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) { setTimeout(poll, 3000); return; }
        if (data.redirect_url) { window.location.href = data.redirect_url; return; }
        if (data.payment_status === 'paid' || data.payment_status === 'failed' || data.status === 'failed') { window.location.reload(); return; }
        setTimeout(poll, 3000);
      }).catch(function () { setTimeout(poll, 3000); });
  }
  setTimeout(poll, 2000);
})();
</script>
<?php endif; ?>
<?php if ($__method === 'addi' && $__status === 'pending'): ?>
<script>
(function () {
  var uuid = <?= json_encode((string)$__order['uuid'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var tries = 0;
  function poll() {
    if (tries++ > 150) return;
    fetch('/api/v1/public/addi/status?uuid=' + encodeURIComponent(uuid), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) { setTimeout(poll, 4000); return; }
        var st = String(data.payment_status || 'pending');
        if (st === 'paid' || st === 'failed' || st === 'refunded') { window.location.reload(); return; }
        setTimeout(poll, 4000);
      }).catch(function () { setTimeout(poll, 4000); });
  }
  setTimeout(poll, 3000);
})();
</script>
<?php endif; ?>
<?php if ($__method === 'mercadopago' && $__status === 'pending'): ?>
<script>
(function () {
  var uuid = <?= json_encode((string)$__order['uuid'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
  var tries = 0;
  function poll() {
    if (tries++ > 40) return;
    fetch('/api/v1/public/mercadopago/status?uuid=' + encodeURIComponent(uuid), { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) { return r.json(); })
      .then(function (data) {
        if (!data || !data.ok) { setTimeout(poll, 4000); return; }
        var st = String(data.payment_status || 'pending');
        if (st === 'paid' || st === 'failed' || st === 'refunded') { window.location.reload(); return; }
        setTimeout(poll, 4000);
      }).catch(function () { setTimeout(poll, 4000); });
  }
  setTimeout(poll, 2500);
})();
</script>
<?php endif; ?>
<?php sonika_site_footer(); ?>
