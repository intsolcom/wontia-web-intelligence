<?php
$__gcSvc = new \App\Services\GiftCardService();
$__gcCfg = $__gcSvc->publicConfig();
$__gcOccasions = (array)($__gcCfg['occasions'] ?? []);
$__gcMethods = (array)($__gcCfg['methods'] ?? []);
$__gcCurrency = (string)($__gcCfg['currency'] ?? 'COP');
$__gcMoney = function (int $cents) use ($__gcCurrency): string {
    $sym = $__gcCurrency === 'USD' ? 'US$' : ($__gcCurrency === 'COP' ? '$' : $__gcCurrency . ' ');
    return $sym . number_format($cents / 100, $__gcCurrency === 'COP' ? 0 : 2, ',', '.');
};
$__gcSelected = null;
$__gcWanted = strtolower(trim((string)($_GET['ocasion'] ?? '')));
foreach ($__gcOccasions as $__occ) {
    if ($__gcWanted !== '' && (string)$__occ['slug'] === $__gcWanted) { $__gcSelected = $__occ; break; }
}
if (!$__gcSelected) $__gcSelected = $__gcOccasions[0] ?? null;
$__gcCodePrefill = strtoupper(trim((string)($_GET['code'] ?? '')));
?>
<link rel="stylesheet" href="/assets/css/gift-cards.css?v=<?= (int)(@filemtime(ROOT_DIR . '/public/assets/css/gift-cards.css') ?: 1) ?>" />
<?php if (empty($__gcCfg['enabled'])): ?>
<section class="gc-page"><div class="gc-wrap gc-section"><div class="gc-empty" style="padding:60px 20px"><b style="font-size:18px;color:#101d3a">Las tarjetas de regalo no están disponibles en este momento.</b><p>Estamos preparando una nueva experiencia de regalo. Vuelve pronto o escríbenos por WhatsApp para ayudarte.</p><a class="gc-submit" style="display:inline-block;width:auto;margin-top:12px;text-decoration:none" href="/tienda">Ir a la tienda</a></div></div></section>
<?php return; endif; ?>
<section class="gc-page" id="tarjetas-de-regalo">
  <div class="gc-hero">
    <div class="gc-wrap gc-hero__in">
      <div>
        <span class="gc-kick">SONIKA · Tarjetas de regalo</span>
        <h1>Regala música.<br><em>Regala SONIKA.</em></h1>
        <p>Elige la ocasión, el monto y el mensaje. Enviamos una tarjeta digital con código y PIN para usar en toda la tienda, total o parcialmente.</p>
        <div class="gc-hero__points">
          <span class="gc-hero__point">🎁 Para cualquier ocasión</span>
          <span class="gc-hero__point">⚡ Entrega inmediata o programada</span>
          <span class="gc-hero__point">🔒 Código único + PIN</span>
          <span class="gc-hero__point">💳 Pago seguro en línea</span>
        </div>
      </div>
      <div class="gc-hero__card">
        <div class="gc-hero__card-top">
          <div>
            <div class="gc-hero__brand">SONIKA</div>
            <div style="font-size:11px;color:rgba(255,255,255,.78);margin-top:4px">Tarjeta de regalo digital</div>
          </div>
          <div class="gc-hero__icon">🎁</div>
        </div>
        <div>
          <div class="gc-hero__amount">$100.000</div>
          <div class="gc-hero__foot">Para quien hace música con el corazón.</div>
        </div>
      </div>
    </div>
  </div>

  <div class="gc-wrap gc-section">
    <div class="gc-head">
      <div>
        <h2>¿Para qué ocasión es el regalo?</h2>
        <p>Selecciona una ocasión y la tarjeta se adapta con su diseño, mensaje sugerido y montos recomendados. Puedes personalizar todo antes de pagar.</p>
      </div>
    </div>
    <?php if (!$__gcOccasions): ?>
      <div class="gc-empty">Las tarjetas de regalo todavía no están configuradas.</div>
    <?php else: ?>
    <div class="gc-occasions" role="list" aria-label="Ocasiones para tarjetas de regalo">
      <?php foreach ($__gcOccasions as $__i => $__occ): ?>
      <button type="button" role="listitem" class="gc-occasion<?= $__gcSelected && (string)$__occ['slug'] === (string)$__gcSelected['slug'] ? ' is-active' : '' ?>"
        data-gc-occasion
        data-slug="<?= sonika_esc((string)$__occ['slug']) ?>"
        data-name="<?= sonika_esc((string)$__occ['name']) ?>"
        data-icon="<?= sonika_esc((string)$__occ['icon']) ?>"
        data-tagline="<?= sonika_esc((string)$__occ['tagline']) ?>"
        data-message="<?= sonika_esc((string)$__occ['default_message']) ?>"
        data-color-from="<?= sonika_esc((string)$__occ['color_from']) ?>"
        data-color-to="<?= sonika_esc((string)$__occ['color_to']) ?>"
        data-amounts="<?= sonika_esc(implode(',', (array)$__occ['suggested_amounts'])) ?>">
        <span class="gc-occasion__icon"><?= sonika_esc((string)$__occ['icon']) ?></span>
        <span class="gc-occasion__name"><?= sonika_esc((string)$__occ['name']) ?></span>
        <span class="gc-occasion__tag"><?= sonika_esc((string)$__occ['tagline']) ?></span>
      </button>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>

  <?php if ($__gcOccasions && !$__gcMethods): ?>
  <div class="gc-wrap"><div class="gc-empty" style="padding:40px 20px"><b>No hay métodos de pago disponibles para tarjetas de regalo.</b><p>Estamos actualizando la pasarela. Escríbenos por WhatsApp o vuelve pronto.</p></div></div>
  <?php endif; ?>
  <?php if ($__gcOccasions && $__gcMethods): ?>
  <div class="gc-wrap">
    <div class="gc-layout">
      <form class="gc-card gc-form" id="gcForm" novalidate>
        <div class="gc-block">
          <h3>Diseña tu tarjeta</h3>
          <p class="gc-sub" id="gcOccasionHint">Ocasión seleccionada: <b><?= sonika_esc((string)($__gcSelected['name'] ?? '')) ?></b></p>
          <input type="hidden" name="occasion_slug" id="gcOccasion" value="<?= sonika_esc((string)($__gcSelected['slug'] ?? '')) ?>" />
          <label class="gc-label" for="gcAmount">Monto de la tarjeta</label>
          <div class="gc-amounts" id="gcAmountPresets">
            <?php foreach ((array)($__gcSelected['suggested_amounts'] ?? []) as $__amt): ?>
            <button type="button" class="gc-amount" data-gc-amount="<?= (int)$__amt ?>"><?= sonika_esc($__gcMoney((int)$__amt)) ?></button>
            <?php endforeach; ?>
          </div>
          <div class="gc-amount-custom">
            <input class="gc-input" id="gcAmount" name="amount" type="number" inputmode="numeric" min="<?= (int)($__gcCfg['min_cents'] / 100) ?>" max="<?= (int)($__gcCfg['max_cents'] / 100) ?>" step="1000" placeholder="Otro monto en <?= sonika_esc($__gcCurrency) ?>" />
            <span>Mín. <?= sonika_esc($__gcMoney((int)$__gcCfg['min_cents'])) ?> · Máx. <?= sonika_esc($__gcMoney((int)$__gcCfg['max_cents'])) ?></span>
          </div>
        </div>

        <div class="gc-block">
          <h3>¿Quién envía?</h3>
          <p class="gc-sub">Te enviaremos una copia del código y el PIN.</p>
          <div class="gc-row">
            <div>
              <label class="gc-label" for="gcSenderName">Tu nombre</label>
              <input class="gc-input" id="gcSenderName" name="sender_name" maxlength="190" autocomplete="name" required />
            </div>
            <div>
              <label class="gc-label" for="gcSenderEmail">Tu correo</label>
              <input class="gc-input" id="gcSenderEmail" name="sender_email" type="email" maxlength="190" autocomplete="email" required />
            </div>
          </div>
        </div>

        <div class="gc-block">
          <h3>¿Quién recibe?</h3>
          <p class="gc-sub">La tarjeta llegará a este correo con el código y el PIN.</p>
          <div class="gc-row">
            <div>
              <label class="gc-label" for="gcRecipientName">Nombre de quien recibe</label>
              <input class="gc-input" id="gcRecipientName" name="recipient_name" maxlength="190" autocomplete="off" required />
            </div>
            <div>
              <label class="gc-label" for="gcRecipientEmail">Correo de quien recibe</label>
              <input class="gc-input" id="gcRecipientEmail" name="recipient_email" type="email" maxlength="190" autocomplete="off" required />
            </div>
          </div>
          <div style="margin-top:12px">
            <label class="gc-label" for="gcMessage">Mensaje personalizado <span style="font-weight:500;text-transform:none;letter-spacing:0">(máx. 300)</span></label>
            <textarea class="gc-textarea" id="gcMessage" name="message" maxlength="300" placeholder="Escribe algo que le haga sonreír…"><?= sonika_esc((string)($__gcSelected['default_message'] ?? '')) ?></textarea>
          </div>
          <div style="margin-top:12px">
            <label class="gc-label" for="gcDeliveryAt">Fecha de entrega <span style="font-weight:500;text-transform:none;letter-spacing:0">(opcional)</span></label>
            <input class="gc-input" id="gcDeliveryAt" name="delivery_at" type="date" min="<?= date('Y-m-d') ?>" />
            <p class="gc-note" style="text-align:left;margin:6px 0 0">Si eliges una fecha futura, enviamos la tarjeta ese día a las 9:00 a. m. (hora Colombia).</p>
          </div>
        </div>

        <div class="gc-block">
          <h3>Método de pago</h3>
          <p class="gc-sub">El pago se procesa en un entorno seguro. La tarjeta se emite cuando se confirma.</p>
          <div class="gc-pay">
            <?php foreach ($__gcMethods as $__mi => $__m): ?>
            <label>
              <input type="radio" name="payment_method" value="<?= sonika_esc((string)$__m['id']) ?>" <?= $__mi === 0 ? 'checked' : '' ?> />
              <span><b><?= sonika_esc((string)$__m['label']) ?></b><small><?= sonika_esc((string)($__m['description'] ?? '')) ?></small></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="gc-block">
          <label class="gc-terms">
            <input type="checkbox" id="gcTerms" name="terms" required />
            <span>Acepto los <a href="<?= sonika_esc((string)($__gcCfg['terms_url'] ?? '/politicas')) ?>" target="_blank" rel="noopener">términos y condiciones</a> y entiendo que la tarjeta es un saldo prepago canjeable en la tienda SONIKA, sujeto a vencimiento y disponibilidad.</span>
          </label>
          <p class="gc-error" id="gcError" role="alert" aria-live="polite"></p>
          <button type="submit" class="gc-submit" id="gcSubmit">Comprar tarjeta de regalo</button>
          <p class="gc-note">Pago seguro. Recibirás la confirmación y, cuando se confirme el pago, el código y el PIN por correo.</p>
        </div>
      </form>

      <aside class="gc-preview" aria-label="Vista previa de la tarjeta">
        <div class="gc-ticket" id="gcTicket">
          <div class="gc-ticket__top">
            <div>
              <div class="gc-ticket__brand">SONIKA</div>
              <div class="gc-ticket__occ" id="gcTicketOcc"><?= sonika_esc((string)($__gcSelected['name'] ?? '')) ?></div>
            </div>
            <div class="gc-ticket__icon" id="gcTicketIcon"><?= sonika_esc((string)($__gcSelected['icon'] ?? '🎁')) ?></div>
          </div>
          <div>
            <div class="gc-ticket__amount" id="gcTicketAmount">$100.000</div>
            <div class="gc-ticket__to" id="gcTicketTo">Para quien hace música con el corazón</div>
            <div class="gc-ticket__msg" id="gcTicketMsg"><?= sonika_esc((string)($__gcSelected['default_message'] ?? '')) ?></div>
          </div>
          <div class="gc-ticket__foot">
            <span>Código<br><span class="gc-ticket__code">SNK-••••-••••-••••</span></span>
            <span style="text-align:right">Tarjeta de regalo<br>Digital · SONIKA</span>
          </div>
        </div>
        <div class="gc-steps">
          <h4>Cómo funciona</h4>
          <div class="gc-step"><span class="gc-step__n">1</span><span><b>Elige y personaliza</b>Ocasión, monto y mensaje.</span></div>
          <div class="gc-step"><span class="gc-step__n">2</span><span><b>Paga en línea</b>Mercado Pago, Bre-B o pago asistido.</span></div>
          <div class="gc-step"><span class="gc-step__n">3</span><span><b>Recibe el código</b>Llega al correo del destinatario con su PIN.</span></div>
          <div class="gc-step"><span class="gc-step__n">4</span><span><b>Usa el saldo</b>Total o parcialmente en la tienda. Si falta, paga la diferencia.</span></div>
        </div>
      </aside>
    </div>
  </div>

  <div class="gc-wrap">
    <div class="gc-balance" id="consultar">
      <div>
        <h2>¿Ya tienes una tarjeta?</h2>
        <p>Consulta el saldo, el vencimiento y los movimientos. Necesitas el código y el PIN que recibiste por correo.</p>
      </div>
      <form class="gc-balance__form" id="gcBalanceForm" novalidate>
        <div>
          <label class="gc-label" for="gcBalanceCode">Código</label>
          <input class="gc-input" id="gcBalanceCode" name="code" placeholder="SNK-XXXX-XXXX-XXXX" autocomplete="off" value="<?= sonika_esc($__gcCodePrefill) ?>" maxlength="24" required />
        </div>
        <div>
          <label class="gc-label" for="gcBalancePin">PIN</label>
          <input class="gc-input" id="gcBalancePin" name="pin" inputmode="numeric" pattern="[0-9]*" placeholder="6 dígitos" autocomplete="off" maxlength="6" required />
        </div>
        <button type="submit" class="gc-submit" id="gcBalanceSubmit">Consultar</button>
        <div class="gc-balance__result" id="gcBalanceResult" role="status" aria-live="polite"></div>
      </form>
    </div>

    <div class="gc-faq">
      <details><summary>¿La tarjeta es un cupón?</summary><p>No. Es un saldo prepago real. Se descuenta del total de tu compra y puedes usarla varias veces hasta agotar el saldo. Si el saldo no cubre todo, pagas la diferencia con otro método.</p></details>
      <details><summary>¿Puedo usarla en cualquier producto?</summary><p>Sí, en los productos disponibles de la tienda SONIKA. No aplica para servicios de contratación artística, eventos, toques, moñas, booking o representación, salvo que el producto lo indique.</p></details>
      <details><summary>¿Qué pasa si no uso todo el saldo?</summary><p>El saldo restante queda disponible para futuras compras hasta la fecha de vencimiento. Puedes consultarlo cuando quieras con tu código y PIN.</p></details>
      <details><summary>¿Puedo enviarla en una fecha especial?</summary><p>Sí. Elige la fecha de entrega y programamos el correo para ese día a las 9:00 a. m. (hora Colombia).</p></details>
      <details><summary>¿La tarjeta es reembolsable?</summary><p>Las tarjetas de regalo no son reembolsables en dinero, salvo lo exigido por la ley. Revisa los términos y condiciones y las políticas de la tienda.</p></details>
      <details><summary>¿Puedo pedir una tarjeta física?</summary><p>Estamos preparando la versión física. Por ahora la entrega es digital por correo, con código y PIN.</p></details>
    </div>
  </div>
  <?php endif; ?>
</section>
<script>window.SONIKA_GIFTCARD_CONFIG = <?= json_encode([
    'min_cents' => (int)($__gcCfg['min_cents'] ?? 1000000),
    'max_cents' => (int)($__gcCfg['max_cents'] ?? 200000000),
    'currency' => $__gcCurrency,
    'purchase_url' => '/api/v1/public/gift-cards/purchase',
    'validate_url' => '/api/v1/public/gift-cards/validate',
    'balance_url' => '/api/v1/public/gift-cards/balance',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="/assets/js/gift-cards.js?v=<?= (int)(@filemtime(ROOT_DIR . '/public/assets/js/gift-cards.js') ?: 1) ?>" defer></script>
