<?php
namespace App\Widgets;

class QuoteWidget extends Widget
{
    private static bool $rendered = false;

    public static function meta(): array
    {
        return ['id' => 'quote', 'name' => 'Cotizador', 'icon' => 'quote', 'category' => 'ecommerce', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'enabled', 'label' => 'Mostrar el cotizador', 'type' => 'toggle', 'default' => true],
            ['key' => 'button_label', 'label' => 'Texto del botón de producto', 'type' => 'text', 'default' => 'Cotizar'],
            ['key' => 'panel_title', 'label' => 'Título del panel', 'type' => 'text', 'default' => 'Tu cotización'],
            ['key' => 'intro', 'label' => 'Texto de introducción', 'type' => 'text', 'default' => 'Agrega productos y consulta cómo quedaría tu compra.'],
            ['key' => 'checkout_label', 'label' => 'Texto para continuar al checkout', 'type' => 'text', 'default' => 'Continuar compra'],
            ['key' => 'prepare_label', 'label' => 'Texto del botón de preparación', 'type' => 'text', 'default' => 'Preparar Cotización'],
            ['key' => 'sheet_title', 'label' => 'Título del panel de acciones', 'type' => 'text', 'default' => 'Prepara tu cotización'],
            ['key' => 'empty_text', 'label' => 'Texto sin productos', 'type' => 'text', 'default' => 'Abre un producto y pulsa Cotizar para empezar.'],
            ['key' => 'note', 'label' => 'Nota al pie de la cotización', 'type' => 'text', 'default' => 'Cotización informativa: no reserva stock. Precios y disponibilidad sujetos a confirmación.'],
            ['key' => 'storage_key', 'label' => 'Clave de almacenamiento de la cotización', 'type' => 'text', 'default' => 'sonika_quote'],
            ['key' => 'cart_key', 'label' => 'Clave del carrito', 'type' => 'text', 'default' => 'sonika_cart'],
            ['key' => 'checkout_url', 'label' => 'URL del checkout', 'type' => 'text', 'default' => '/checkout'],
            ['key' => 'valid_days', 'label' => 'Días de vigencia de la cotización', 'type' => 'number', 'default' => '15'],
            ['key' => 'show_payment_methods', 'label' => 'Mostrar métodos de pago', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_whatsapp', 'label' => 'Permitir enviar por WhatsApp', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_print', 'label' => 'Permitir descargar PDF', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_floating', 'label' => 'Mostrar botón flotante del cotizador', 'type' => 'toggle', 'default' => true],
        ];
    }

    public static function defaultConfig(): array
    {
        return [
            'enabled' => true,
            'button_label' => 'Cotizar',
            'panel_title' => 'Tu cotización',
            'intro' => 'Agrega productos y consulta cómo quedaría tu compra.',
            'checkout_label' => 'Continuar compra',
            'prepare_label' => 'Preparar Cotización',
            'sheet_title' => 'Prepara tu cotización',
            'empty_text' => 'Abre un producto y pulsa Cotizar para empezar.',
            'note' => 'Cotización informativa: no reserva stock. Precios y disponibilidad sujetos a confirmación.',
            'storage_key' => 'sonika_quote',
            'cart_key' => 'sonika_cart',
            'checkout_url' => '/checkout',
            'valid_days' => '15',
            'show_payment_methods' => true,
            'show_whatsapp' => true,
            'show_print' => true,
            'show_floating' => true,
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="padding:18px;border:1px dashed #ccc;border-radius:8px;color:#666">Cotizador: botón gris "Cotizar" en cada producto, panel con cantidades, métodos de pago, IVA, transporte, impresión y envío por WhatsApp.</div>';
    }

    public function render(array $config = []): string
    {
        if (self::$rendered) return '';
        $c = $this->mergeConfig($config);
        if (empty($c['enabled'])) return '';
        self::$rendered = true;

        $pay = [];
        try {
            $pay = function_exists('sonika_payments_config') ? sonika_payments_config() : (new \App\Services\BrebService())->publicConfig();
        } catch (\Throwable $e) {
            $pay = ['methods' => [['id' => 'request', 'label' => 'Coordinar por WhatsApp']], 'breb' => [], 'currency' => 'COP'];
        }
        $methods = (array)($pay['methods'] ?? []);
        if (!$methods) $methods = [['id' => 'request', 'label' => 'Coordinar por WhatsApp', 'fallback' => true]];
        $wa = preg_replace('/\D+/', '', (string)($pay['breb']['whatsapp'] ?? ''));
        if ($wa === '' && function_exists('sonika_whatsapp')) $wa = preg_replace('/\D+/', '', sonika_whatsapp());
        $currency = (string)($pay['currency'] ?? 'COP');
        $storageKey = preg_replace('/[^a-z0-9_]/i', '', (string)($c['storage_key'] ?? 'sonika_quote')) ?: 'sonika_quote';
        $cartKey = preg_replace('/[^a-z0-9_]/i', '', (string)($c['cart_key'] ?? 'sonika_cart')) ?: 'sonika_cart';
        $checkoutUrl = trim((string)($c['checkout_url'] ?? '/checkout'));
        if ($checkoutUrl === '' || !preg_match('~^(https?://|/)~i', $checkoutUrl)) $checkoutUrl = '/checkout';
        $validDays = max(1, min(90, (int)($c['valid_days'] ?? 15)));
        $showPay = !empty($c['show_payment_methods']);
        $showWa = !empty($c['show_whatsapp']) && $wa !== '';
        $showPrint = !empty($c['show_print']);
        $showFab = !empty($c['show_floating']);
        $buttonLabel = trim((string)($c['button_label'] ?? 'Cotizar')) ?: 'Cotizar';
        $title = trim((string)($c['panel_title'] ?? 'Tu cotización')) ?: 'Tu cotización';
        $intro = trim((string)($c['intro'] ?? '')) ?: 'Agrega productos y consulta cómo quedaría tu compra.';
        $checkoutLabel = trim((string)($c['checkout_label'] ?? 'Continuar compra')) ?: 'Continuar compra';
        $prepareLabel = trim((string)($c['prepare_label'] ?? 'Preparar Cotización')) ?: 'Preparar Cotización';
        if ($prepareLabel === 'Prepara cotización') $prepareLabel = 'Preparar Cotización';
        $sheetTitle = trim((string)($c['sheet_title'] ?? 'Prepara tu cotización')) ?: 'Prepara tu cotización';
        $emptyText = trim((string)($c['empty_text'] ?? '')) ?: 'Abre un producto y pulsa Cotizar para empezar.';
        $note = trim((string)($c['note'] ?? '')) ?: 'Cotización informativa: no reserva stock. Precios y disponibilidad sujetos a confirmación.';

        $h = '';
        $h .= '<link rel="stylesheet" href="/assets/css/quote-widget.css?v=' . (int)(@filemtime(ROOT_DIR . '/public/assets/css/quote-widget.css') ?: 1) . '" />';
        if ($showFab) {
            $h .= '<button type="button" class="sq__fab" id="sqFab" hidden aria-haspopup="dialog" aria-expanded="false" aria-controls="sonikaQuote">';
            $h .= '<span class="sq__fab-icon" aria-hidden="true">∑</span><span>Cotizador</span><span class="sq__count" id="sqCount">0</span></button>';
        }
        $h .= '<div class="sq" id="sonikaQuote" hidden>';
        $h .= '<div class="sq__backdrop" data-sq-close></div>';
        $h .= '<aside class="sq__panel" role="dialog" aria-modal="true" aria-labelledby="sqTitle" tabindex="-1">';
        $h .= '<header class="sq__head"><div class="sq__doc-head"><div class="sq__doc-line"><span class="sq__doc-brand">SONIKA</span><span class="sq__doc-type">COTIZACIÓN</span><span class="sq__doc-status">Vigente</span></div><h2 id="sqTitle">' . $this->esc($title) . '</h2><div class="sq__meta-line"><p class="sq__ref" id="sqRef"></p><button type="button" class="sq__ref-copy" id="sqCopyRef" hidden>Copiar código</button></div></div><button type="button" class="sq__close" data-sq-close aria-label="Cerrar cotización">×</button></header>';
        $h .= '<div class="sq__body">';
        $h .= '<p class="sq__intro">' . $this->esc($intro) . '</p>';
        $h .= '<div class="sq__alert" id="sqError" role="alert" hidden></div>';
        $h .= '<div class="sq__items" id="sqItems" aria-live="polite"></div>';
        $h .= '<div class="sq__empty" id="sqEmpty">' . $this->esc($emptyText) . '</div>';
        if ($showPay) {
            $h .= '<section class="sq__pay"><h3>Forma de pago estimada</h3><div class="sq__methods" id="sqMethods">';
            foreach ($methods as $i => $m) {
                $id = (string)($m['id'] ?? 'request');
                $label = (string)($m['label'] ?? 'Pago');
                $desc = $id === 'breb' ? 'Escanea el QR desde tu app bancaria.' : ($id === 'mercadopago' ? 'Tarjeta, PSE y otros medios.' : ($id === 'epayco' ? 'Tarjeta y PSE.' : 'Te asesoramos para coordinar el pago.'));
                $h .= '<label class="sq-method" data-sq-method="' . $this->esc($id) . '"><input type="radio" name="sq_method" value="' . $this->esc($id) . '"' . ($i === 0 ? ' checked' : '') . ' /><span class="sq-method__dot" aria-hidden="true"></span><span class="sq-method__txt"><b>' . $this->esc($label) . '</b><small>' . $this->esc($desc) . '</small></span></label>';
            }
            $h .= '</div><p class="sq__hint">El método es una estimación. Podrás confirmarlo y cambiarlo en el checkout.</p></section>';
        }
        $waDisplay = $wa !== '' ? '+' . (strlen($wa) === 10 ? '57' . $wa : $wa) : '';
        $h .= '<div class="sq__print-only"><p><b>SONIKA</b> · El Sonido que te mueve</p><p>Cotización informativa. No reserva stock. Precios y disponibilidad sujetos a confirmación.</p><p>' . ($waDisplay !== '' ? 'WhatsApp: ' . $this->esc($waDisplay) . ' · ' : '') . 'sonika.com.co</p></div>';
        $h .= '</div>';
        $h .= '<footer class="sq__foot"><div class="sq__summary" id="sqSummary" aria-live="polite"></div>';
        $h .= '<div class="sq__customer" id="sqCustomer"><div class="sq__customer-head"><span>Tipo de cotización</span><span class="sq__customer-state" id="sqCustomerState">Pendiente</span></div>';
        $h .= '<div class="sq__customer-options" id="sqCustomerOptions"><label class="sq__customer-option"><input type="radio" name="sq_customer_type" value="natural" /><span class="sq__customer-txt"><b>Persona natural</b><small>Datos personales</small></span></label><label class="sq__customer-option"><input type="radio" name="sq_customer_type" value="company" /><span class="sq__customer-txt"><b>Persona jurídica</b><small>Empresa</small></span></label></div>';
        $h .= '<div class="sq__customer-summary" id="sqCustomerSummary" hidden></div>';
        $h .= '<p class="sq__customer-hint" id="sqCustomerHint">Selecciona el tipo y completa tus datos para preparar la cotización.</p></div>';
        $h .= '<button type="button" class="sq__prepare" id="sqPrepare" aria-haspopup="dialog" aria-expanded="false" aria-controls="sqSheet" disabled>' . $this->esc($prepareLabel) . '<span aria-hidden="true">→</span></button>';
        $h .= '</footer>';
        $h .= '<div class="sq__sheet" id="sqSheet" hidden>';
        $h .= '<div class="sq__sheet-backdrop" data-sq-sheet-close></div>';
        $h .= '<div class="sq__sheet-panel" role="dialog" aria-modal="true" aria-labelledby="sqSheetTitle">';
        $h .= '<div class="sq__sheet-head"><div><b id="sqSheetTitle">' . $this->esc($sheetTitle) . '</b><p>Elige una acción para tu cotización.</p></div><button type="button" class="sq__sheet-close" data-sq-sheet-close aria-label="Cerrar acciones">×</button></div>';
        $h .= '<p class="sq__sheet-note">' . $this->esc($note) . '</p>';
        $h .= '<div class="sq__sheet-actions">';
        $h .= '<button type="button" class="sq__btn sq__btn--primary" id="sqCheckout" disabled>' . $this->esc($checkoutLabel) . '</button>';
        if ($showWa) $h .= '<button type="button" class="sq__btn sq__btn--ghost" id="sqWa">Enviar por WhatsApp</button>';
        if ($showPrint) $h .= '<button type="button" class="sq__btn sq__btn--ghost" id="sqPrint">Descargar PDF</button>';
        $h .= '<button type="button" class="sq__clear" id="sqClear">Vaciar</button>';
        $h .= '</div>';
        if ($showWa) {
            $h .= '<div class="sq__wa-options" id="sqWaOptions" hidden>'
                . '<div class="sq__wa-head"><b>Cotización lista</b><span>Elige cómo abrir WhatsApp</span></div>'
                . '<a class="sq__btn sq__btn--primary sq__wa-web" id="sqWaWeb" href="#" target="_blank" rel="noopener">Abrir WhatsApp Web</a>'
                . '<a class="sq__btn sq__btn--ghost sq__wa-app" id="sqWaApp" href="#">Abrir app WhatsApp</a>'
                . '<div class="sq__wa-links"><button type="button" class="sq__wa-link" id="sqWaCopy">Copiar enlace de la cotización</button><button type="button" class="sq__wa-link" id="sqWaPdf" hidden>Compartir PDF</button></div>'
                . '</div>';
        }
        $h .= '</div></div>';
        $h .= '<div class="sq__customer-sheet" id="sqCustomerSheet" hidden>';
        $h .= '<div class="sq__customer-backdrop" data-sq-customer-close></div>';
        $h .= '<div class="sq__customer-panel" role="dialog" aria-modal="true" aria-labelledby="sqCustomerTitle">';
        $h .= '<header class="sq__customer-sheet-head"><div><span class="sq__form-step" id="sqCustomerStep">Paso 1 de 2</span><h3 id="sqCustomerTitle">Persona natural</h3><p id="sqCustomerSub">Cuéntanos quién solicita la cotización.</p></div><button type="button" class="sq__sheet-close" data-sq-customer-close aria-label="Cerrar datos">×</button></header>';
        $h .= '<div class="sq__form-progress" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="50"><span id="sqCustomerProgress"></span></div>';
        $h .= '<form id="sqCustomerForm" novalidate>';
        $h .= '<div class="sq__form-steps">';
        $h .= '<section class="sq__form-step-panel" data-customer-type="natural" data-customer-step="1">';
        $h .= '<p class="sq__form-note">Todos los campos son obligatorios excepto la dirección.</p>';
        $h .= '<div class="sq__field"><label class="sq__form-label" for="sqCustFullName">Nombre completo <span class="req">*</span></label><input class="sq__input" id="sqCustFullName" name="full_name" autocomplete="name" maxlength="100" required aria-describedby="sqCustFullNameError" /><p class="sq__field-error" id="sqCustFullNameError" hidden></p></div>';
        $h .= '<div class="sq__field"><label class="sq__form-label" for="sqCustDocument">Número de documento de identificación <span class="req">*</span></label><input class="sq__input" id="sqCustDocument" name="document" inputmode="numeric" maxlength="15" required aria-describedby="sqCustDocumentError" /><p class="sq__field-error" id="sqCustDocumentError" hidden></p></div>';
        $h .= '</section>';
        $h .= '<section class="sq__form-step-panel" data-customer-type="natural" data-customer-step="2" hidden>';
        $h .= '<div class="sq__field"><label class="sq__form-label" for="sqCustEmail">Correo electrónico <span class="req">*</span></label><input class="sq__input" id="sqCustEmail" name="email" type="email" autocomplete="email" maxlength="190" required aria-describedby="sqCustEmailError" /><p class="sq__field-error" id="sqCustEmailError" hidden></p></div>';
        $h .= '<div class="sq__field"><label class="sq__form-label" for="sqCustPhone">Teléfono de contacto <span class="req">*</span></label><input class="sq__input" id="sqCustPhone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="12" required aria-describedby="sqCustPhoneError" /><p class="sq__field-error" id="sqCustPhoneError" hidden></p></div>';
        $h .= '<div class="sq__field"><label class="sq__form-label" for="sqCustAddress">Dirección <small>opcional</small></label><input class="sq__input" id="sqCustAddress" name="address" autocomplete="street-address" maxlength="200" aria-describedby="sqCustAddressError" /><p class="sq__field-error" id="sqCustAddressError" hidden></p></div>';
        $h .= '</section>';
        $h .= '<section class="sq__form-step-panel" data-customer-type="company" data-customer-step="1" hidden>';
        $h .= '<p class="sq__form-note">Todos los campos son obligatorios excepto la dirección.</p>';
        $h .= '<div class="sq__field"><label class="sq__form-label" for="sqCustCompany">Nombre de la empresa / Razón social <span class="req">*</span></label><input class="sq__input" id="sqCustCompany" name="company_name" autocomplete="organization" maxlength="140" required aria-describedby="sqCustCompanyError" /><p class="sq__field-error" id="sqCustCompanyError" hidden></p></div>';
        $h .= '<div class="sq__field"><label class="sq__form-label" for="sqCustNit">NIT <span class="req">*</span></label><input class="sq__input" id="sqCustNit" name="nit" inputmode="numeric" maxlength="12" required aria-describedby="sqCustNitError" /><p class="sq__field-error" id="sqCustNitError" hidden></p></div>';
        $h .= '</section>';
        $h .= '<section class="sq__form-step-panel" data-customer-type="company" data-customer-step="2" hidden>';
        $h .= '<div class="sq__field"><label class="sq__form-label" for="sqCustContact">Nombre completo del contacto <span class="req">*</span></label><input class="sq__input" id="sqCustContact" name="contact_name" autocomplete="name" maxlength="100" required aria-describedby="sqCustContactError" /><p class="sq__field-error" id="sqCustContactError" hidden></p></div>';
        $h .= '<div class="sq__field"><label class="sq__form-label" for="sqCustPosition">Cargo <span class="req">*</span></label><input class="sq__input" id="sqCustPosition" name="position" autocomplete="organization-title" maxlength="80" required aria-describedby="sqCustPositionError" /><p class="sq__field-error" id="sqCustPositionError" hidden></p></div>';
        $h .= '<div class="sq__field"><label class="sq__form-label" for="sqCustCompanyEmail">Correo electrónico <span class="req">*</span></label><input class="sq__input" id="sqCustCompanyEmail" name="email" type="email" autocomplete="email" maxlength="190" required aria-describedby="sqCustCompanyEmailError" /><p class="sq__field-error" id="sqCustCompanyEmailError" hidden></p></div>';
        $h .= '<div class="sq__field"><label class="sq__form-label" for="sqCustCompanyPhone">Teléfono de contacto <span class="req">*</span></label><input class="sq__input" id="sqCustCompanyPhone" name="phone" type="tel" autocomplete="tel" inputmode="tel" maxlength="12" required aria-describedby="sqCustCompanyPhoneError" /><p class="sq__field-error" id="sqCustCompanyPhoneError" hidden></p></div>';
        $h .= '<div class="sq__field"><label class="sq__form-label" for="sqCustCompanyAddress">Dirección <small>opcional</small></label><input class="sq__input" id="sqCustCompanyAddress" name="address" autocomplete="street-address" maxlength="200" aria-describedby="sqCustCompanyAddressError" /><p class="sq__field-error" id="sqCustCompanyAddressError" hidden></p></div>';
        $h .= '</section>';
        $h .= '</div>';
        $h .= '<p class="sq__customer-form-error" id="sqCustomerFormError" role="alert" hidden></p>';
        $h .= '<footer class="sq__customer-form-foot"><button type="button" class="sq__btn sq__btn--ghost" id="sqCustomerBack" hidden>← Atrás</button><button type="button" class="sq__btn sq__btn--primary" id="sqCustomerNext">Continuar →</button><button type="submit" class="sq__btn sq__btn--primary" id="sqCustomerSave" hidden>Guardar datos</button></footer>';
        $h .= '</form></div></div>';
        $h .= '</aside></div>';

        $cfg = [
            'storageKey' => $storageKey,
            'cartKey' => $cartKey,
            'checkoutUrl' => $checkoutUrl,
            'wa' => $wa,
            'validDays' => $validDays,
            'currency' => $currency,
            'buttonLabel' => $buttonLabel,
            'showWhatsapp' => $showWa,
            'showPrint' => $showPrint,
            'showFloating' => $showFab,
            'labels' => [
                'added' => 'Agregado a la cotización',
                'empty' => $emptyText,
                'quote' => 'Cotización',
                'method' => 'Forma de pago',
            ],
        ];
        $json = json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $h .= '<script>window.SONIKA_QUOTE=' . $json . ';</script>';
        $h .= '<script src="/assets/js/quote-widget.js?v=' . (int)(@filemtime(ROOT_DIR . '/public/assets/js/quote-widget.js') ?: 1) . '" charset="utf-8" defer></script>';
        return $h;
    }
}
