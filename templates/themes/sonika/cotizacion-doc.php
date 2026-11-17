<?php
$__money = function (int $cents) use ($__quote): string {
    $currency = (string)($__quote['currency'] ?? 'COP');
    $sym = $currency === 'USD' ? 'US$' : ($currency === 'COP' ? '$' : $currency . ' ');
    return $sym . number_format($cents / 100, $currency === 'COP' ? 0 : 2, ',', '.');
};
$__date = function (?string $value): string {
    if (!$value) return '';
    $p = substr($value, 0, 10);
    $d = explode('-', $p);
    return count($d) === 3 ? $d[2] . '/' . $d[1] . '/' . $d[0] : $p;
};
$__methodMap = ['breb' => 'Bre-B', 'mercadopago' => 'Mercado Pago', 'epayco' => 'ePayco', 'request' => 'Coordinar por WhatsApp'];
$__method = (string)($__quote['payment_method'] ?? 'request');
$__methodLabel = $__methodMap[$__method] ?? 'Pago asistido';
$__statusLabels = ['draft' => 'Vigente', 'sent' => 'Enviada', 'converted' => 'Convertida', 'expired' => 'Vencida'];
$__status = (string)($__quote['effective_status'] ?? $__quote['status'] ?? 'draft');
$__statusLabel = $__statusLabels[$__status] ?? 'Vigente';
$__statusClass = $__status === 'converted' ? ' is-converted' : ($__status === 'expired' ? ' is-expired' : '');
$__customer = (array)($__quote['customer'] ?? []);
$__type = (string)($__customer['type'] ?? '');
$__customerTypeLabel = $__type === 'company' ? 'Persona jurídica' : ($__type === 'natural' ? 'Persona natural' : 'Cliente');
$__customerName = (string)($__customer['display_name'] ?? $__customer['company_name'] ?? $__customer['full_name'] ?? 'Cliente');
$__customerDoc = $__type === 'company' ? (string)($__customer['nit'] ?? '') : (string)($__customer['document'] ?? '');
$__customerDocLabel = $__type === 'company' ? 'NIT' : 'Documento';
$__customerEmail = (string)($__customer['email'] ?? '');
$__customerPhone = (string)($__customer['phone'] ?? '');
$__customerAddress = (string)($__customer['address'] ?? '');
$__companyCity = (string)($__settings['quote_company_city'] ?? 'Barranquilla, Colombia');
$__publicBase = rtrim((string)($__settings['quote_public_base_url'] ?? ''), '/') ?: 'https://sonika.com.co';
$__companyNit = (string)($__settings['quote_company_nit'] ?? '');
$__wa = preg_replace('/\D+/', '', function_exists('sonika_whatsapp') ? sonika_whatsapp() : '') ?: '';
$__waLabel = $__wa !== '' ? '+' . (strlen($__wa) === 10 ? '57' . $__wa : $__wa) : '';
$__categoryLabel = function (string $cat): string {
    $s = mb_strtolower($cat);
    if (str_contains($s, 'acople') || str_contains($s, 'tarima') || str_contains($s, 'accesorio') || str_contains($s, 'soporte')) return 'Accesorios para tarima';
    if (str_contains($s, 'controlador') || str_contains($s, 'sintetiz')) return 'Controladores musicales';
    if (str_contains($s, 'guitarra') || str_contains($s, 'bajo') || str_contains($s, 'cuerda')) return 'Guitarras y cuerdas';
    if (str_contains($s, 'audio') || str_contains($s, 'monitor') || str_contains($s, 'mezcla')) return 'Audio y monitores';
    if (str_contains($s, 'micro')) return 'Micrófonos';
    if (str_contains($s, 'ilumin')) return 'Iluminación';
    return trim($cat) !== '' ? trim($cat) : 'Productos';
};
$__items = array_values((array)($__quote['items'] ?? []));
$__taxRate = (float)($__quote['meta']['tax_rate'] ?? 19);
$__issueDate = $__date((string)($__quote['created_at'] ?? ''));
$__validUntil = $__date((string)($__quote['expires_at'] ?? ''));
$__publicUntil = $__date((string)($__quote['public_expires_at'] ?? ''));
?>
<article class="qdoc__paper<?= !empty($__printMode) ? ' qdoc__paper--print' : '' ?>" id="quoteDocument">
  <header class="qdoc__head">
    <div class="qdoc__brand">
      <div class="qdoc__logo"><?= sonika_logo_tag() ?></div>
      <p class="qdoc__legal">Intsolcom S.A.S.</p>
      <p class="qdoc__tagline">El Sonido que te mueve</p>
    </div>
    <div class="qdoc__identity">
      <span class="qdoc__eyebrow">COTIZACIÓN</span>
      <b class="qdoc__code"><?= sonika_esc((string)($__quote['code'] ?? '')) ?></b>
      <span class="qdoc__status<?= $__statusClass ?>"><?= sonika_esc($__statusLabel) ?></span>
      <dl class="qdoc__meta">
        <div><dt>Fecha</dt><dd><?= sonika_esc($__issueDate) ?></dd></div>
        <div><dt>Ciudad</dt><dd><?= sonika_esc($__companyCity) ?></dd></div>
        <div><dt>Válida hasta</dt><dd><?= sonika_esc($__validUntil) ?></dd></div>
      </dl>
    </div>
  </header>

  <section class="qdoc__client">
    <div>
      <span class="qdoc__label">PREPARADA PARA</span>
      <h1><?= sonika_esc($__customerName) ?></h1>
      <p><?= sonika_esc($__customerTypeLabel) ?><?= $__customerDoc !== '' ? ' · ' . sonika_esc($__customerDocLabel) . ' ' . sonika_esc($__customerDoc) : '' ?></p>
      <p><?= sonika_esc($__customerEmail) ?><?= $__customerEmail !== '' && $__customerPhone !== '' ? ' · ' : '' ?><?= sonika_esc($__customerPhone) ?></p>
      <?php if ($__customerAddress !== ''): ?><p class="qdoc__muted"><?= sonika_esc($__customerAddress) ?></p><?php endif; ?>
    </div>
    <div class="qdoc__payment-box">
      <span class="qdoc__label">FORMA DE PAGO</span>
      <b><?= sonika_esc($__methodLabel) ?></b>
      <span class="qdoc__muted">Cotización informativa · No reserva stock</span>
    </div>
  </section>

  <section class="qdoc__products">
    <div class="qdoc__section-head"><span>DETALLE DE PRODUCTOS</span><span><?= count($__items) ?> artículo<?= count($__items) === 1 ? '' : 's' ?></span></div>
    <?php if (!$__items): ?>
      <div class="qdoc__empty">Esta cotización no tiene productos.</div>
    <?php else: ?>
    <table class="qdoc__table">
      <thead><tr><th class="qdoc__num">#</th><th>Producto</th><th class="qdoc__qty-col">Cant.</th><th class="qdoc__money-col">Vlr. unitario</th><th class="qdoc__money-col">Total</th></tr></thead>
      <tbody>
      <?php foreach ($__items as $__i => $__it): $__line = (int)($__it['subtotal_after_cents'] ?? $__it['original_line_cents'] ?? 0); $__unit = (int)($__it['new_unit_cents'] ?? $__it['original_unit_cents'] ?? 0); $__old = (int)($__it['original_line_cents'] ?? 0); $__ref = (string)($__it['ref'] ?? ''); $__cat = $__categoryLabel((string)($__it['category'] ?? 'Productos')); ?>
        <tr>
          <td class="qdoc__num"><?= str_pad((string)($__i + 1), 2, '0', STR_PAD_LEFT) ?></td>
          <td class="qdoc__product">
            <?php if (!empty($__it['image'])): ?><img src="<?= sonika_esc((string)$__it['image']) ?>" alt="" width="44" height="44" /><?php else: ?><span class="qdoc__ph" aria-hidden="true">S</span><?php endif; ?>
            <div><a href="<?= sonika_esc($__publicBase . '/producto/' . rawurlencode($__ref)) ?>"<?= empty($__printMode) ? ' target="_blank" rel="noopener"' : '' ?>><?= sonika_esc((string)($__it['title'] ?? $__ref)) ?></a><small><?= sonika_esc($__cat) ?> · SKU <?= sonika_esc($__ref) ?></small><?php if (($__it['available'] ?? true) === false || (($__it['issue'] ?? 'ok') !== 'ok')): ?><em>No disponible</em><?php endif; ?></div>
          </td>
          <td class="qdoc__qty-col"><?= (int)($__it['qty'] ?? 1) ?></td>
          <td class="qdoc__money-col"><?= sonika_esc($__money($__unit)) ?></td>
          <td class="qdoc__money-col qdoc__line-total"><?php if ($__old > $__line): ?><small><?= sonika_esc($__money($__old)) ?></small><?php endif; ?><?= sonika_esc($__money($__line)) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <?php endif; ?>
  </section>

  <section class="qdoc__bottom">
    <div class="qdoc__conditions">
      <span class="qdoc__label">CONDICIONES</span>
      <p>Cotización informativa, no reserva stock. Precios y disponibilidad sujetos a confirmación. Los valores incluyen IVA y se expresan en <?= sonika_esc((string)($__quote['currency'] ?? 'COP')) ?>. El envío se calcula según la compra.</p>
      <p>Los productos están enlazados a su ficha en sonika.com.co. La cotización es válida hasta la fecha indicada.</p>
    </div>
    <div class="qdoc__totals">
      <div><span>Antes de IVA</span><b><?= sonika_esc($__money((int)($__quote['net_after_cents'] ?? 0))) ?></b></div>
      <div><span>IVA (<?= sonika_esc((string)$__taxRate) ?>%)</span><b><?= sonika_esc($__money((int)($__quote['tax_after_cents'] ?? 0))) ?></b></div>
      <div><span>Transporte</span><b><?= (int)($__quote['shipping_cents'] ?? 0) === 0 ? 'Gratis' : sonika_esc($__money((int)($__quote['shipping_cents'] ?? 0))) ?></b></div>
      <?php if ((int)($__quote['breb_discount_cents'] ?? 0) > 0): ?><div class="qdoc__discount"><span>Ahorro Bre-B</span><b>−<?= sonika_esc($__money((int)$__quote['breb_discount_cents'])) ?></b></div><?php endif; ?>
      <?php if ((int)($__quote['coupon_discount_cents'] ?? 0) > 0): ?><div class="qdoc__discount"><span>Descuento</span><b>−<?= sonika_esc($__money((int)$__quote['coupon_discount_cents'])) ?></b></div><?php endif; ?>
      <div class="qdoc__total"><span>TOTAL</span><b><?= sonika_esc($__money((int)($__quote['total_cents'] ?? 0))) ?></b></div>
      <small>IVA incluido · Moneda <?= sonika_esc((string)($__quote['currency'] ?? 'COP')) ?></small>
    </div>
  </section>

  <footer class="qdoc__footer">
    <div class="qdoc__footer-top">
      <div class="qdoc__footer-brand">
        <div class="qdoc__footer-wordmark">SONIKA<span>.</span></div>
        <span class="qdoc__footer-tagline">El Sonido que te mueve</span>
        <small>Una marca de Intsolcom, LLC, operada en Colombia por Intsolcom S.A.S.</small>
      </div>
      <div class="qdoc__footer-col">
        <span class="qdoc__footer-label"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11zm0-8.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5z"/></svg>Sede principal</span>
        <address>Cra 55 # 100-51, Piso 13<br/>Centro Empresarial Blue Garden<br/>Barranquilla, Colombia</address>
      </div>
      <div class="qdoc__footer-col">
        <span class="qdoc__footer-label"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 4h16v12H8l-4 3V4zm3 4h10M7 11h7"/></svg>Contacto</span>
        <a href="<?= $__waLabel !== '' ? 'https://wa.me/' . sonika_esc(preg_replace('/\D+/', '', $__waLabel)) : '#' ?>"><?= $__waLabel !== '' ? 'WhatsApp ' . sonika_esc($__waLabel) : 'Atenci&oacute;n por WhatsApp' ?></a>
        <a href="mailto:cliente@intsolcom.com">cliente@intsolcom.com</a>
        <a href="<?= sonika_esc($__publicBase) ?>">sonika.com.co</a>
      </div>
      <div class="qdoc__footer-col">
        <span class="qdoc__footer-label"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M4 4h16v16H4V4zm4 4h8M8 12h8M8 16h5"/></svg>Datos legales</span>
        <?php if ($__companyNit !== ''): ?><span>NIT <?= sonika_esc($__companyNit) ?></span><?php endif; ?>
        <span>Intsolcom, LLC &middot; Miami, FL</span>
        <span>Documento electr&oacute;nico</span>
      </div>
    </div>
    <div class="qdoc__footer-bottom">
      <span><b>Cotizaci&oacute;n</b> <?= sonika_esc((string)($__quote['code'] ?? '')) ?></span>
      <span class="qdoc__footer-thanks">Gracias por elegir SONIKA</span>
      <span><?= $__publicUntil !== '' ? 'Disponible hasta ' . sonika_esc($__publicUntil) : '' ?> &middot; <a href="<?= sonika_esc($__publicBase) ?>">sonika.com.co</a></span>
    </div>
  </footer>

</article>
