<?php
namespace App\Widgets;

class StoreCheckoutWidget extends StoreWidgetBase
{
    public static function meta(): array
    {
        return ['id' => 'store-checkout', 'name' => 'Store: Checkout', 'icon' => 'card', 'category' => 'commerce', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'inline' => true, 'default' => 'Finalizar pedido'],
            ['key' => 'submit_label', 'label' => 'Texto boton pagar', 'type' => 'text', 'default' => 'Confirmar pedido'],
            ['key' => 'success_text', 'label' => 'Mensaje de exito', 'type' => 'textarea', 'inline' => true, 'default' => 'Recibimos tu pedido. Te contactaremos para confirmar la entrega.'],
            ['key' => 'empty_text', 'label' => 'Texto carrito vacio', 'type' => 'text', 'default' => 'Agrega productos al carrito para continuar.'],
            ['key' => 'wompi_label', 'label' => 'Etiqueta pago en linea', 'type' => 'text', 'default' => 'Pago en linea (tarjeta, PSE, Nequi)'],
            ['key' => 'cod_label', 'label' => 'Etiqueta contra entrega', 'type' => 'text', 'default' => 'Pago contra entrega'],
            ['key' => 'manual_label', 'label' => 'Etiqueta pago asistido', 'type' => 'text', 'default' => 'Coordinar pago por WhatsApp'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $svc = $this->store();
        $html = $this->assets();
        if (!$svc->ready()) {
            return $html . '<section class="st-section"><div class="st-empty">La tienda aun no esta configurada.</div></section>';
        }
        $settings = $svc->settings();
        $currency = $settings['store_currency'] ?: 'COP';
        $methods = $svc->paymentMethods();
        $zones = $svc->zones(true);
        $whatsapp = preg_replace('/[^0-9]/', '', (string)$settings['store_whatsapp']);
        $labels = ['wompi' => $c['wompi_label'], 'cod' => $c['cod_label'], 'manual' => $c['manual_label']];
        $hints = [
            'wompi' => 'Paga ahora con tarjeta, PSE o Nequi.',
            'cod' => 'Pagas en efectivo al recibir tu pedido.',
            'manual' => $whatsapp !== '' ? 'Te contactamos por WhatsApp para coordinar el pago.' : 'Te contactamos para coordinar el pago.',
        ];

        $html .= '<section class="st-section" id="st-checkout">';
        $html .= '<div class="st-head"><h2 data-editable="title">' . $this->esc($c['title']) . '</h2></div>';
        $html .= '<div id="st-co-msg"></div>';
        $html .= '<div id="st-co-wrap" class="st-pd" style="grid-template-columns:1.2fr .8fr;align-items:start">';

        $html .= '<form id="st-co-form" onsubmit="return wwiStoreCheckout(event)">';
        $html .= '<div class="st-row"><div class="st-field"><label>Nombre completo *</label><input name="name" required/></div>';
        $html .= '<div class="st-field"><label>Documento / NIT</label><input name="document"/></div></div>';
        $html .= '<div class="st-row"><div class="st-field"><label>Email *</label><input type="email" name="email" required/></div>';
        $html .= '<div class="st-field"><label>Telefono / WhatsApp *</label><input name="phone" required/></div></div>';
        $html .= '<div class="st-field"><label>Direccion de entrega *</label><input name="line1" required/></div>';
        $html .= '<div class="st-row"><div class="st-field"><label>Ciudad *</label><input name="city" required/></div>';
        $html .= '<div class="st-field"><label>Departamento</label><input name="region"/></div></div>';

        if ($zones) {
            $html .= '<div class="st-field"><label>Zona de envio *</label><select name="zone_id" required onchange="wwiStoreZone(this)">';
            $html .= '<option value="">Selecciona tu zona</option>';
            foreach ($zones as $z) {
                $html .= '<option value="' . (int)$z['id'] . '" data-cost="' . (int)$z['cost_cents'] . '" data-free="' . (int)$z['free_over_cents'] . '">'
                    . $this->esc((string)$z['name']) . ' — ' . $this->esc($this->money((int)$z['cost_cents'], $currency))
                    . ($z['eta_days'] ? ' (' . $this->esc((string)$z['eta_days']) . ')' : '') . '</option>';
            }
            $html .= '</select></div>';
        }

        $html .= '<div class="st-field"><label>Notas del pedido</label><textarea name="notes" rows="2"></textarea></div>';

        $html .= '<div class="st-field"><label>Metodo de pago *</label><div class="st-pay">';
        foreach ($methods as $i => $m) {
            $html .= '<label><input type="radio" name="payment_method" value="' . $this->esc($m) . '"' . ($i === 0 ? ' checked' : '') . '/>'
                . '<span><strong>' . $this->esc($labels[$m] ?? $m) . '</strong><br/><span style="color:var(--muted,#6b7280);font-size:12px">' . $this->esc($hints[$m] ?? '') . '</span></span></label>';
        }
        $html .= '</div></div>';

        $html .= '<button class="st-btn st-btn-primary" type="submit" id="st-co-btn" style="width:100%;padding:15px">' . $this->esc($c['submit_label']) . '</button>';
        $html .= '</form>';

        $html .= '<div class="st-totals"><h3 style="font-size:15px;margin:0 0 12px">Resumen</h3><div id="st-co-summary"></div></div>';
        $html .= '</div>';

        $html .= '<div id="st-co-success" style="display:none"></div>';
        $html .= '</section>';

        $cfg = json_encode([
            'currency' => $currency,
            'empty' => $c['empty_text'],
            'success' => $c['success_text'],
            'whatsapp' => $whatsapp,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $html .= '<script>(function(){var CFG=' . $cfg . ';var S=window.WWIStore;'
            . 'function summary(){var el=document.getElementById("st-co-summary");if(!el)return;var items=S.get();'
            . 'if(!items.length){el.innerHTML=\'<div class="st-empty" style="padding:14px 0">\'+CFG.empty+\'</div>\';return}'
            . 'var h="";items.forEach(function(it){h+=\'<div class="r" style="display:flex;justify-content:space-between;font-size:13px;padding:5px 0"><span>\'+(it.qty||1)+"x "+it.name+(it.variant?" ("+it.variant+")":"")+\'</span><span>\'+S.money((parseInt(it.price_cents)||0)*(parseInt(it.qty)||1))+\'</span></div>\'});'
            . 'var sel=document.querySelector(\'#st-co-form [name="zone_id"]\');var ship=0;'
            . 'if(sel&&sel.value){var o=sel.options[sel.selectedIndex];var cost=parseInt(o.dataset.cost)||0;var free=parseInt(o.dataset.free)||0;ship=(free>0&&S.subtotal()>=free)?0:cost}'
            . 'h+=\'<div style="display:flex;justify-content:space-between;font-size:13px;padding:5px 0"><span>Envio</span><span>\'+(ship?S.money(ship):"—")+\'</span></div>\';'
            . 'h+=\'<div class="r big" style="display:flex;justify-content:space-between;font-size:18px;font-weight:800;border-top:1px solid rgba(120,120,140,.16);margin-top:8px;padding-top:12px"><span>Total</span><span>\'+S.money(S.subtotal()+ship)+\'</span></div>\';'
            . 'el.innerHTML=h}'
            . 'window.wwiStoreZone=function(){summary()};'
            . 'window.wwiStoreCheckout=function(e){e.preventDefault();var f=e.target;var items=S.get();var msg=document.getElementById("st-co-msg");'
            . 'if(!items.length){msg.innerHTML=\'<div class="st-msg err">\'+CFG.empty+\'</div>\';return false}'
            . 'var btn=document.getElementById("st-co-btn");btn.disabled=true;btn.textContent="Procesando...";'
            . 'var fd=new FormData(f);var payload={items:items.map(function(it){return{product_id:it.product_id,variant_id:it.variant_id,qty:parseInt(it.qty)||1}}),'
            . 'customer:{name:fd.get("name"),email:fd.get("email"),phone:fd.get("phone"),document:fd.get("document")||""},'
            . 'shipping:{zone_id:fd.get("zone_id")||null,address:{line1:fd.get("line1"),city:fd.get("city"),region:fd.get("region")||""}},'
            . 'payment_method:fd.get("payment_method"),notes:fd.get("notes")||""};'
            . 'fetch("/api/v1/public/store/orders",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(payload)}).then(function(r){return r.json()}).then(function(d){'
            . 'if(!d.ok){throw new Error(d.message||"No se pudo crear el pedido")}'
            . 'S.clear();var order=d.data.order;var pay=d.data.payment;'
            . 'if(pay.method==="wompi"&&pay.public_key){var u="https://checkout.wompi.co/p/?public-key="+encodeURIComponent(pay.public_key)+"&currency="+encodeURIComponent(pay.currency)+"&amount-in-cents="+pay.amount_in_cents+"&reference="+encodeURIComponent(pay.reference)+"&signature:integrity="+pay.signature+"&redirect-url="+encodeURIComponent(location.href.split("?")[0]+"?order="+pay.reference);location.href=u;return}'
            . 'var wa=CFG.whatsapp?(" https://wa.me/"+CFG.whatsapp+"?text="+encodeURIComponent("Hola, acabo de hacer el pedido "+order.order_number)):"";'
            . 'document.getElementById("st-co-wrap").style.display="none";'
            . 'var ok=document.getElementById("st-co-success");ok.style.display="block";'
            . 'ok.innerHTML=\'<div class="st-msg ok"><strong>Pedido \'+order.order_number+\' recibido.</strong><br/>\'+CFG.success+(\' <a href="?order=\'+order.uuid+\'" style="color:inherit;text-decoration:underline">Ver estado</a>\')+(wa?\' <a href="\'+wa+\'" style="color:inherit;text-decoration:underline">Avisar por WhatsApp</a>\':\'\')+\'</div>\';'
            . 'window.scrollTo({top:document.getElementById("st-checkout").offsetTop-40,behavior:"smooth"})'
            . '}).catch(function(err){msg.innerHTML=\'<div class="st-msg err">\'+(err.message||"Error")+\'</div>\';btn.disabled=false;btn.textContent="' . $this->esc($c['submit_label']) . '"})'
            . ';return false};'
            . 'document.addEventListener("wwi-cart-change",summary);if(document.readyState!=="loading"){summary()}else{document.addEventListener("DOMContentLoaded",summary)}'
            . 'var op=new URLSearchParams(location.search).get("order");'
            . 'if(op){fetch("/api/v1/public/store/orders/"+encodeURIComponent(op)).then(function(r){return r.json()}).then(function(d){if(!d.ok)return;var o=d.data;'
            . 'document.getElementById("st-co-wrap").style.display="none";var el=document.getElementById("st-co-success");el.style.display="block";'
            . 'var st={pending:"Pendiente de pago",paid:"Pagado",failed:"Pago fallido",refunded:"Reembolsado"}[o.payment_status]||o.payment_status;'
            . 'el.innerHTML=\'<div class="st-msg ok"><strong>Pedido \'+o.order_number+\'</strong><br/>Estado del pago: \'+st+\'<br/>Total: \'+S.money(o.total_cents)+\'</div>\'})}'
            . '})();</script>';

        return $html;
    }
}
