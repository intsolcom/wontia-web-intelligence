<?php
namespace App\Widgets;

class StoreCartWidget extends StoreWidgetBase
{
    public static function meta(): array
    {
        return ['id' => 'store-cart', 'name' => 'Store: Carrito', 'icon' => 'cart', 'category' => 'commerce', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'inline' => true, 'default' => 'Tu carrito'],
            ['key' => 'empty_text', 'label' => 'Texto carrito vacio', 'type' => 'text', 'inline' => true, 'default' => 'Tu carrito esta vacio.'],
            ['key' => 'catalog_label', 'label' => 'Texto volver al catalogo', 'type' => 'text', 'default' => 'Ver productos'],
            ['key' => 'checkout_label', 'label' => 'Texto boton checkout', 'type' => 'text', 'default' => 'Continuar con el pedido'],
            ['key' => 'checkout_anchor', 'label' => 'Ancla del checkout', 'type' => 'text', 'default' => 'st-checkout'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $html = $this->assets();
        $currency = $this->currency();
        $html .= '<section class="st-section" id="st-cart">';
        $html .= '<div class="st-head"><h2 data-editable="title">' . $this->esc($c['title']) . '</h2></div>';
        $html .= '<div id="st-cart-body"><div class="st-load">Cargando carrito...</div></div>';
        $html .= '<div class="st-pager"><a class="st-btn st-btn-outline" href="#' . $this->esc($c['checkout_anchor']) . '">' . $this->esc($c['catalog_label']) . '</a></div>';
        $html .= '</section>';
        $html .= '<script>(function(){var CUR="' . $this->esc($currency) . '";var EMPTY="' . $this->esc($c['empty_text']) . '";var CHECKOUT="' . $this->esc($c['checkout_label']) . '";var ANCHOR="' . $this->esc($c['checkout_anchor']) . '";'
            . 'function render(){var el=document.getElementById("st-cart-body");if(!el)return;var S=window.WWIStore;var items=S.get();'
            . 'if(!items.length){el.innerHTML=\'<div class="st-empty">\'+EMPTY+\'</div>\';return}'
            . 'var h="";items.forEach(function(it,i){h+=\'<div class="st-cart-item"><div>\'+(it.image?\'<img src="\'+it.image+\'" alt=""/>\':\'<div style="width:76px;height:76px;border-radius:12px;background:rgba(120,120,140,.1)"></div>\')+\'</div>\';'
            . 'h+=\'<div><div style="font-weight:700;font-size:14.5px">\'+it.name+(it.variant?\' <span style="font-weight:500;color:var(--muted,#6b7280)">(\'+it.variant+\')</span>\':\'\')+\'</div>\';'
            . 'h+=\'<div style="font-size:13px;color:var(--muted,#6b7280);margin:4px 0">\'+S.money(it.price_cents)+\'</div>\';'
            . 'h+=\'<div style="display:flex;gap:8px;align-items:center"><input class="st-qty" type="number" min="1" max="99" value="\'+(it.qty||1)+\'" onchange="WWIStore.qty(\'+i+\',this.value)"/>\';'
            . 'h+=\'<button class="st-btn st-btn-outline" style="padding:7px 12px" onclick="WWIStore.remove(\'+i+\')">Quitar</button></div></div>\';'
            . 'h+=\'<div style="font-weight:800">\'+S.money((parseInt(it.price_cents)||0)*(parseInt(it.qty)||1))+\'</div></div>\'});'
            . 'var sub=S.subtotal();'
            . 'h+=\'<div class="st-totals" style="margin-top:22px;max-width:420px;margin-left:auto"><div class="r"><span>Subtotal</span><span>\'+S.money(sub)+\'</span></div>\';'
            . 'h+=\'<div class="r" style="color:var(--muted,#6b7280);font-size:12.5px"><span>Envio</span><span>Se calcula en el checkout</span></div>\';'
            . 'h+=\'<div class="r big"><span>Total</span><span>\'+S.money(sub)+\'</span></div>\';'
            . 'h+=\'<a class="st-btn st-btn-primary" style="width:100%;margin-top:14px" href="#\'+ANCHOR+\'">\'+CHECKOUT+\'</a></div>\';'
            . 'el.innerHTML=h}'
            . 'document.addEventListener("wwi-cart-change",render);if(document.readyState!=="loading"){render()}else{document.addEventListener("DOMContentLoaded",render)}})();</script>';
        return $html;
    }
}
