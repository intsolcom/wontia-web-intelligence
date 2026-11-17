<?php $__payCfg = sonika_payments_config(); $__freeNet = (int)($__payCfg['breb']['free_shipping_net_cents'] ?? 30000000); $__taxRate = (float)($__payCfg['breb']['tax_rate'] ?? 19); $__freeGross = (int)round($__freeNet * (1 + $__taxRate / 100)); $__freeLabel = '$' . number_format($__freeNet / 100, 0, ',', '.'); $__freeGrossLabel = '$' . number_format($__freeGross / 100, 0, ',', '.'); $__termsUrl = (string)($__payCfg['breb']['terms_url'] ?? '/politicas'); $__pts = (array)($__payCfg['points'] ?? []); $__me = null; $__addrDefault = []; try { $__me = (new \App\Services\CustomerService())->current(); if ($__me) { $__addrs = (new \App\Services\CustomerService())->addresses((int)$__me['id']); if ($__addrs) $__addrDefault = (array)$__addrs[0]; } } catch (\Throwable $e) {} $__departments = []; try { $__departments = (new \App\Services\ShippingService())->departments(); } catch (\Throwable $e) {} $__ptBalance = (int)($__pts['balance'] ?? 0); $__ptRedeem = (int)($__pts['redeem_cents'] ?? 0); $__ptValue = '$' . number_format($__ptRedeem / 100, 0, ',', '.'); ?>
<section class="wrap skpay" id="checkoutPage">
  <div class="skpay__head">
    <div>
      <h1>Finalizar compra</h1>
      <p>Completa tus datos y elige el método de pago. Los precios incluyen IVA.</p>
    </div>
    <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:center">
      <a class="skpay__link" href="/carrito" style="font-size:13px;color:#4b5563">← Volver al carrito</a>
    </div>
  </div>
  <div class="skpay__grid">
    <form id="skpayForm" class="skpay__card" autocomplete="on" novalidate>
      <div class="skpay__section">
        <h2>Tu pedido</h2>
        <div id="skpayCheckoutItems" class="skpay__cart-items" aria-live="polite" aria-busy="true"></div>
      </div>
      <div class="skpay__section">
        <h2>Datos de contacto</h2>
        <p class="skpay__tiny" style="margin:-4px 0 12px">Todos los campos son obligatorios. Usamos tus datos solo para gestionar el pedido y la entrega.</p>
        <div class="skpay__form">
          <div class="skpay__row">
            <div class="skpay__field">
              <label for="skpayName">Nombre completo <span class="req">*</span></label>
              <input class="skpay__input" id="skpayName" name="name" autocomplete="name" maxlength="120" value="<?= sonika_esc((string)($__me['name'] ?? '')) ?>" required aria-describedby="skpayNameError" />
              <p class="skpay__field-error" id="skpayNameError" hidden></p>
            </div>
            <div class="skpay__field">
              <label for="skpayEmail">Correo electrónico <span class="req">*</span></label>
              <input class="skpay__input" id="skpayEmail" name="email" type="email" autocomplete="email" maxlength="190" value="<?= sonika_esc((string)($__me['email'] ?? '')) ?>" required aria-describedby="skpayEmailError" />
              <p class="skpay__field-error" id="skpayEmailError" hidden></p>
            </div>
          </div>
          <div class="skpay__row">
            <div class="skpay__field">
              <label for="skpayPhone">Teléfono / WhatsApp <span class="req">*</span></label>
              <input class="skpay__input" id="skpayPhone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="30" value="<?= sonika_esc((string)($__me['phone'] ?? '')) ?>" required aria-describedby="skpayPhoneError" />
              <p class="skpay__field-error" id="skpayPhoneError" hidden></p>
            </div>
            <div class="skpay__field">
              <label for="skpayDocument">Documento (CC/NIT/CE) <span class="req">*</span></label>
              <input class="skpay__input" id="skpayDocument" name="document" autocomplete="off" maxlength="30" value="<?= sonika_esc((string)($__me['document'] ?? '')) ?>" required aria-describedby="skpayDocumentError" />
              <p class="skpay__field-error" id="skpayDocumentError" hidden></p>
            </div>
          </div>
        </div>
      </div>
      <div class="skpay__section">
        <h2>Entrega</h2>
        <div class="skpay__form">
          <div class="skpay__field">
            <label for="skpayLine1">Dirección <span class="req">*</span></label>
            <input class="skpay__input" id="skpayLine1" name="line1" autocomplete="street-address" maxlength="190" value="<?= sonika_esc((string)($__addrDefault['line1'] ?? '')) ?>" placeholder="Calle, carrera, número y complemento" required aria-describedby="skpayLine1Error" />
            <p class="skpay__field-error" id="skpayLine1Error" hidden></p>
          </div>
          <div class="skpay__row">
            <div class="skpay__field">
              <label for="skpayDepartment">Departamento <span class="req">*</span></label>
              <select class="skpay__input" id="skpayDepartment" name="department_code" autocomplete="address-level1" required aria-describedby="skpayDepartmentError">
                <option value="">Selecciona el departamento</option>
                <?php foreach ($__departments as $__d): ?>
                  <option value="<?= sonika_esc((string)$__d['code']) ?>"><?= sonika_esc((string)$__d['name']) ?></option>
                <?php endforeach; ?>
              </select>
              <p class="skpay__field-error" id="skpayDepartmentError" hidden></p>
            </div>
            <div class="skpay__field">
              <label for="skpayMunicipality">Ciudad / municipio <span class="req">*</span></label>
              <select class="skpay__input" id="skpayMunicipality" name="municipality_code" autocomplete="address-level2" required disabled aria-describedby="skpayMunicipalityError">
                <option value="">Selecciona primero el departamento</option>
              </select>
              <p class="skpay__field-error" id="skpayMunicipalityError" hidden></p>
            </div>
          </div>
          <div class="skpay__field">
            <label for="skpayNotes">Indicaciones de entrega <span class="req">*</span></label>
            <textarea class="skpay__textarea" id="skpayNotes" name="notes" rows="3" maxlength="500" placeholder="Ej.: Casa de rejas blancas, portón negro, Torre 1, Apto 301, piso 3, conjunto Brisas del Parque." required aria-describedby="skpayNotesHelp skpayNotesError"><?= sonika_esc((string)($__addrDefault['notes'] ?? '')) ?></textarea>
            <div class="skpay__notes-meta">
              <p class="skpay__tiny" id="skpayNotesHelp" style="margin:0">Mínimo 30 caracteres. Incluye torre, piso, apartamento, color de fachada o un punto de referencia.</p>
              <span class="skpay__notes-count" id="skpayNotesCount" aria-live="polite">0/30</span>
            </div>
            <div class="skpay__chips" aria-label="Ayudas para completar las indicaciones">
              <button type="button" class="skpay__chip" data-sk-note-chip="Casa ">Casa</button>
              <button type="button" class="skpay__chip" data-sk-note-chip="Apartamento ">Apartamento</button>
              <button type="button" class="skpay__chip" data-sk-note-chip="Conjunto ">Conjunto</button>
              <button type="button" class="skpay__chip" data-sk-note-chip="Torre ">Torre</button>
              <button type="button" class="skpay__chip" data-sk-note-chip="Piso ">Piso</button>
              <button type="button" class="skpay__chip" data-sk-note-chip="Fachada ">Fachada</button>
              <button type="button" class="skpay__chip" data-sk-note-chip="Portón ">Portón</button>
              <button type="button" class="skpay__chip" data-sk-note-chip="Punto de referencia: ">Punto de referencia</button>
            </div>
            <p class="skpay__field-error" id="skpayNotesError" hidden></p>
          </div>
          <details class="skpay__details-help">
            <summary>¿Por qué pedimos estos datos?</summary>
            <p>La dirección y las indicaciones permiten que la transportadora encuentre tu domicilio sin retrasos. El documento se usa para la factura y la entrega. No compartimos tus datos con terceros distintos al operador logístico.</p>
          </details>
          <p class="skpay__tiny" style="margin:0">Envío a toda Colombia. Si tu compra supera <?= sonika_esc($__freeLabel) ?> antes de IVA (<?= sonika_esc($__freeGrossLabel) ?> con IVA), el envío es gratis. Los productos marcados como <b>Envío gratis</b> no pagan flete y no suman al umbral.</p>
        </div>
      </div>
      <div class="skpay__section">
        <h2>Método de pago</h2>
        <div class="skpay__methods" id="skpayMethods">
          <?php sonika_payment_method_cards(); ?>
        </div>
      </div>
      <div class="skpay__section">
        <h2>Cupón <small>opcional</small></h2>
        <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
          <input class="skpay__input" id="skpayCoupon" name="coupon" placeholder="Código de cupón" autocomplete="off" style="flex:1;min-width:140px" />
          <button type="button" class="skpay__btn skpay__btn--secondary skpay__btn--sm" id="skpayCouponApply" style="margin:0">Aplicar</button>
          <button type="button" class="skpay__link" id="skpayCouponClear" style="font-size:12px">Quitar</button>
        </div>
        <p class="skpay__tiny" id="skpayCouponMsg" role="status" aria-live="polite" style="margin-top:7px"></p>
      </div>
      <?php sonika_gift_card_checkout_section(); ?>
      <?php if (!empty($__pts['enabled']) && $__ptBalance > 0 && $__ptRedeem > 0): ?>
      <div class="skpay__section">
        <h2>Puntos <small>opcional</small></h2>
        <p class="skpay__muted" style="margin:0 0 10px">Tienes <b><?= (int)$__ptBalance ?></b> puntos disponibles. Cada punto vale <?= sonika_esc($__ptValue) ?>.</p>
        <div style="display:flex;gap:7px;align-items:center;flex-wrap:wrap">
          <input class="skpay__input" id="skpayPoints" type="number" min="0" max="<?= (int)$__ptBalance ?>" step="1" value="0" style="flex:1;min-width:110px" />
          <button type="button" class="skpay__btn skpay__btn--secondary skpay__btn--sm" id="skpayPointsApply" style="margin:0">Usar puntos</button>
          <button type="button" class="skpay__link" id="skpayPointsClear" style="font-size:12px">No usar</button>
        </div>
        <p class="skpay__tiny" id="skpayPointsMsg" role="status" aria-live="polite" style="margin-top:7px"></p>
      </div>
      <?php endif; ?>
      <div class="skpay__section">
        <div class="skpay__check">
          <input type="checkbox" id="skpayTerms" name="terms" required aria-describedby="skpayTermsError" />
          <label for="skpayTerms">Acepto los <a href="<?= sonika_esc($__termsUrl) ?>" target="_blank" rel="noopener" style="text-decoration:underline">términos y condiciones</a> y entiendo que el pedido queda sujeto a confirmación del pago y disponibilidad.</label>
        </div>
        <p class="skpay__field-error" id="skpayTermsError" hidden></p>
        <p id="skpayError" class="skpay__error" role="alert" aria-live="polite" hidden></p>
        <button type="submit" class="skpay__btn" id="skpaySubmit" disabled>Confirmar pedido</button>
        <p class="skpay__page-note">No se realizará ningún cobro automático. En Bre-B pagas escaneando el QR y luego adjuntas tu comprobante.</p>
      </div>
    </form>
    <aside class="skpay__summary" id="skpayCheckoutSummary" aria-live="polite" aria-busy="true"></aside>
  </div>
  <?php sonika_payments_assets(); ?>
</section>
