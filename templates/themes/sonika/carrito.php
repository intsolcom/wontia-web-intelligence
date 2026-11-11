<?php $__payCfg = sonika_payments_config(); $__freeNet = (int)($__payCfg['breb']['free_shipping_net_cents'] ?? 30000000); $__freeLabel = '$' . number_format($__freeNet / 100, 0, ',', '.'); ?>
<section class="wrap skpay" id="cartPage">
  <div class="skpay__head">
    <div>
      <h1>Tu carrito</h1>
      <p>Revisa tus productos, elige cómo pagar y aprovecha el descuento de Bre-B.</p>
    </div>
    <a class="skpay__link" href="/tienda" style="font-size:13px;color:#4b5563">← Seguir comprando</a>
  </div>
  <?= sonika_impact_note_html() ?>
  <div class="skpay__grid skpay__grid--cart">
    <div class="skpay__card">
      <div class="skpay__section">
        <h2>Productos <small id="skpayCartCount"></small></h2>
        <div id="skpayCartItems" class="skpay__cart-items" aria-live="polite" aria-busy="true"></div>
        <p id="skpayCartError" class="skpay__alert" role="alert" hidden></p>
      </div>
      <div class="skpay__section" id="skpayRecommendationsSection" hidden>
        <div id="skpayRecommendations" aria-live="polite"></div>
      </div>
      <div class="skpay__section">
        <h2>Método de pago</h2>
        <p class="skpay__muted" style="margin:0 0 12px">Elige cómo quieres pagar. El descuento de Bre-B se aplica al seleccionarlo.</p>
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
    </div>
    <aside class="skpay__summary" id="skpaySummary" aria-live="polite" aria-busy="true"></aside>
  </div>
  <p class="skpay__page-note">Los precios incluyen IVA. El envío gratis aplica desde <?= sonika_esc($__freeLabel) ?> antes de IVA. Tu pedido queda sujeto a confirmación del pago y disponibilidad de los productos.</p>
  <?php sonika_payments_assets(); ?>
  <?php sonika_share_assets(); ?>
  <?php sonika_recommendations_assets(); ?>
</section>
