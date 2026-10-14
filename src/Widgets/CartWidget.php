<?php
namespace App\Widgets;

use App\Core\Config;

/**
 * CartWidget — brick "Carrito" (categoría header).
 * Icono + badge + mini-carrito (drawer) + barra de envío gratis + validación
 * server-side (re-cotización/stock). Lee/escribe el carrito del sitio en localStorage
 * (mismo formato que WWIStore) para no romper el checkout existente.
 */
class CartWidget extends Widget
{
    private static bool $assetsPrinted = false;

    public static function meta(): array
    {
        return ['id' => 'cart', 'name' => 'Carrito de compras', 'icon' => 'cart', 'category' => 'header', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'icon', 'label' => 'Icono (emoji)', 'type' => 'text', 'default' => '🛒'],
            ['key' => 'label', 'label' => 'Etiqueta', 'type' => 'text', 'default' => 'Carrito'],
            ['key' => 'show_label', 'label' => 'Mostrar etiqueta', 'type' => 'toggle', 'default' => true],
            ['key' => 'drawer', 'label' => 'Mini-carrito desplegable', 'type' => 'toggle', 'default' => true],
            ['key' => 'position', 'label' => 'Posición del panel', 'type' => 'select', 'options' => ['right' => 'Derecha', 'left' => 'Izquierda'], 'default' => 'right'],
            ['key' => 'accent', 'label' => 'Color de acento', 'type' => 'color', 'default' => ''],
            ['key' => 'cart_url', 'label' => 'URL del carrito', 'type' => 'text', 'default' => '/carrito'],
            ['key' => 'checkout_url', 'label' => 'URL de pago/checkout', 'type' => 'text', 'default' => '/carrito'],
            ['key' => 'title', 'label' => 'Título del panel', 'type' => 'text', 'default' => 'Tu carrito'],
            ['key' => 'empty_text', 'label' => 'Texto carrito vacío', 'type' => 'text', 'default' => 'Tu carrito está vacío.'],
            ['key' => 'free_shipping', 'label' => 'Envío gratis desde (centavos · 0 = ocultar)', 'type' => 'number', 'default' => '0'],
            ['key' => 'validate', 'label' => 'Validar precios/stock al abrir (server)', 'type' => 'toggle', 'default' => true],
        ];
    }

    public static function defaultConfig(): array
    {
        return ['icon' => '🛒', 'label' => 'Carrito', 'show_label' => true, 'drawer' => true, 'position' => 'right', 'accent' => '', 'cart_url' => '/carrito', 'checkout_url' => '/carrito', 'title' => 'Tu carrito', 'empty_text' => 'Tu carrito está vacío.', 'free_shipping' => '0', 'validate' => true];
    }

    public static function adminPreview(): string
    {
        return '<div style="padding:18px;border:1px dashed #ccc;border-radius:8px;color:#666">🛒 Carrito: icono + contador + mini-carrito desplegable con validación de precios/stock.</div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $icon = trim((string)($c['icon'] ?? '🛒')) ?: '🛒';
        $label = (string)($c['label'] ?? 'Carrito');
        $showLabel = !empty($c['show_label']);
        $drawer = !empty($c['drawer']);
        $pos = ((string)($c['position'] ?? 'right') === 'left') ? 'left' : 'right';
        $accent = strtolower(trim((string)($c['accent'] ?? '')));
        if (!preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $accent)) $accent = '';
        $cartUrl = (string)($c['cart_url'] ?? '/carrito') ?: '/carrito';
        $checkoutUrl = (string)($c['checkout_url'] ?? '/carrito') ?: '/carrito';
        $title = (string)($c['title'] ?? 'Tu carrito');
        $empty = (string)($c['empty_text'] ?? 'Tu carrito está vacío.');
        $free = max(0, (int)($c['free_shipping'] ?? 0));
        $doValidate = !empty($c['validate']);
        $site = (int)Config::get('SITE_ID', '1');

        $uid = 'wc' . substr(md5($accent . $pos . $site), 0, 5);
        $h = $this->assets();
        $h .= '<div class="wwi-cart" id="' . $uid . '" data-site="' . $site . '" data-drawer="' . ($drawer ? '1' : '0') . '" data-pos="' . $pos . '"'
            . ' data-cart="' . $this->esc($cartUrl) . '" data-checkout="' . $this->esc($checkoutUrl) . '" data-empty="' . $this->esc($empty) . '" data-title="' . $this->esc($title) . '"'
            . ' data-free="' . $free . '" data-validate="' . ($doValidate ? '1' : '0') . '"'
            . ($accent !== '' ? ' style="--wwi-cart-accent:' . $accent . '"' : '') . '>';
        $h .= '<button type="button" class="icon-btn wwi-cart__btn" aria-haspopup="' . ($drawer ? 'true' : 'false') . '" aria-label="' . $this->esc($label) . '">'
            . '<span class="ic" aria-hidden="true">' . $this->esc($icon) . '</span>'
            . ($showLabel ? '<span class="lbl">' . $this->esc($label) . '</span>' : '')
            . '<span class="wwi-cart__badge" id="cartCount">0</span></button>';
        if ($drawer) {
            $h .= '<div class="wwi-cart__panel" role="dialog" aria-label="' . $this->esc($title) . '">'
                . '<div class="wwi-cart__head"><b>' . $this->esc($title) . '</b><button type="button" class="wwi-cart__x" data-close aria-label="Cerrar">✕</button></div>'
                . '<div class="wwi-cart__body"></div>'
                . '<div class="wwi-cart__foot"></div></div>';
        }
        $h .= '</div>';
        return $h;
    }

    private function assets(): string
    {
        if (self::$assetsPrinted) return '';
        self::$assetsPrinted = true;
        return <<<'HTML'
<style>
.wwi-cart{position:relative;display:inline-flex}
.wwi-cart__badge{position:absolute;top:-6px;right:-6px;min-width:18px;height:18px;border-radius:10px;background:var(--wwi-cart-accent,var(--brand,#F20D16));color:#fff;font-size:10px;font-weight:800;display:none;align-items:center;justify-content:center;padding:0 4px}
.wwi-cart__panel{position:absolute;top:calc(100% + 10px);right:0;width:min(92vw,360px);background:#fff;color:var(--ink,#111);border:1px solid var(--line,#E6E6E6);border-radius:14px;box-shadow:0 24px 60px rgba(0,0,0,.18);opacity:0;visibility:hidden;transform:translateY(8px);transition:.16s;z-index:220;text-align:left}
.wwi-cart[data-pos="left"] .wwi-cart__panel{right:auto;left:0}
.wwi-cart.open .wwi-cart__panel{opacity:1;visibility:visible;transform:none}
.wwi-cart__head{display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-bottom:1px solid var(--line,#E6E6E6)}
.wwi-cart__x{border:0;background:transparent;color:var(--muted,#777);cursor:pointer;font-size:15px}
.wwi-cart__body{max-height:52vh;overflow:auto;padding:6px 14px}
.wwi-cart__item{display:flex;gap:10px;align-items:center;padding:10px 0;border-bottom:1px solid #F0F0F0}
.wwi-cart__item img{width:44px;height:44px;object-fit:cover;border-radius:8px;background:#F5F5F5}
.wwi-cart__nm{font-size:13px;font-weight:600;line-height:1.25}
.wwi-cart__qty{display:inline-flex;align-items:center;gap:6px;margin-top:4px}
.wwi-cart__qty button{width:22px;height:22px;border:1px solid var(--line,#E6E6E6);background:#fff;border-radius:6px;cursor:pointer;line-height:1}
.wwi-cart__pr{font-size:13px;font-weight:700;white-space:nowrap}
.wwi-cart__rm{border:0;background:transparent;color:#DC2626;cursor:pointer;font-size:12px}
.wwi-cart__free{margin:8px 0;font-size:12px;color:var(--muted,#777)}
.wwi-cart__freebar{height:6px;border-radius:6px;background:#EEE;overflow:hidden;margin-top:4px}
.wwi-cart__freebar i{display:block;height:100%;background:var(--wwi-cart-accent,var(--brand,#F20D16))}
.wwi-cart__foot{padding:12px 14px;border-top:1px solid var(--line,#E6E6E6)}
.wwi-cart__sub{display:flex;justify-content:space-between;font-weight:800;margin-bottom:10px}
.wwi-cart__cta{display:flex;gap:8px}
.wwi-cart__cta a{flex:1;text-align:center;padding:10px;border-radius:8px;text-decoration:none;font-weight:700;font-size:13px}
.wwi-cart__cta a.p1{background:var(--wwi-cart-accent,var(--brand,#F20D16));color:#fff}
.wwi-cart__cta a.p2{background:#F5F5F5;color:var(--ink,#111)}
.wwi-cart__warn{margin:6px 0;font-size:12px;color:#B45309}
.wwi-cart__empty{padding:22px 6px;color:var(--muted,#777);text-align:center;font-size:13px}
@media(prefers-reduced-motion:reduce){.wwi-cart__panel{transition:none}}
</style>
<script>if(!window.__wwiCartInit){window.__wwiCartInit=1;document.addEventListener('click',function(e){
 var root=e.target.closest?e.target.closest('.wwi-cart'):null;var any=document.querySelector('.wwi-cart.open');
 if(root){if(e.target.closest('[data-close]')){root.classList.remove('open');return}
   var isBtn=e.target.closest('.wwi-cart__btn');
   if(isBtn){e.preventDefault();if(root.getAttribute('data-drawer')==='1'){root.classList.toggle('open');if(root.classList.contains('open'))window.wwiCartRender&&window.wwiCartRender(root)}else{location.href=root.getAttribute('data-cart')}return}
   return}
 if(any)any.classList.remove('open');
},true);}
window.wwiCartRender=function(root){
 if(!root)return;var site=root.getAttribute('data-site');var KEY='wwi_cart_'+site;
 var read=function(){try{return JSON.parse(localStorage.getItem(KEY))||[]}catch(e){return []}};
 var write=function(c){try{localStorage.setItem(KEY,JSON.stringify(c))}catch(e){}};
 var money=function(n){return '$'+Math.round(n||0).toLocaleString('es-CO')};
 var items=read();var badge=document.getElementById('cartCount');var count=items.reduce(function(a,i){return a+(parseInt(i.qty)||0)},0);
 if(badge){badge.textContent=count;badge.style.display=count>0?'flex':'none'}
 if(root.getAttribute('data-drawer')!=='1')return;
 var body=root.querySelector('.wwi-cart__body');var foot=root.querySelector('.wwi-cart__foot');
 if(!items.length){body.innerHTML='<div class="wwi-cart__empty">'+root.getAttribute('data-empty')+'</div>';foot.innerHTML='';return}
 var sub=0;var h='';
 items.forEach(function(it,i){var p=parseFloat(it.price)||0;var q=parseInt(it.qty)||1;sub+=p*q;
  h+='<div class="wwi-cart__item">'+(it.image?'<img src="'+it.image+'" alt=""/>':'<img src="data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22/%3E" alt=""/>')
   +'<div style="flex:1;min-width:0"><div class="wwi-cart__nm">'+(it.title||it.handle||'')+'</div>'
   +'<div class="wwi-cart__qty"><button type="button" data-q="'+i+'" data-d="-1">−</button><span>'+q+'</span><button type="button" data-q="'+i+'" data-d="1">+</button>'
   +'<button type="button" class="wwi-cart__rm" data-rm="'+i+'">Quitar</button></div></div>'
   +'<div class="wwi-cart__pr">'+money(p*q)+'</div></div>'});
 body.innerHTML=h;
 var free=parseInt(root.getAttribute('data-free')||'0',10);var fh='';
 if(free>0){var pct=Math.min(100,Math.round(sub*100/free));var left=free-sub;
  fh='<div class="wwi-cart__free">'+(left>0?('Te faltan '+money(left)+' para el <b>envío gratis</b>'):'¡Tienes <b>envío gratis</b>!' )+'<div class="wwi-cart__freebar"><i style="width:'+pct+'%"></i></div></div>'}
 foot.innerHTML=fh+'<div class="wwi-cart__sub"><span>Subtotal</span><span>'+money(sub)+'</span></div><div class="wwi-cart__cta"><a class="p2" href="'+root.getAttribute('data-cart')+'">Ver carrito</a><a class="p1" href="'+root.getAttribute('data-checkout')+'">Pagar</a></div>';
 body.querySelectorAll('[data-q]').forEach(function(b){b.onclick=function(){var i=parseInt(b.getAttribute('data-q'),10);var d=parseInt(b.getAttribute('data-d'),10);items[i].qty=Math.max(1,(parseInt(items[i].qty)||1)+d);write(items);window.wwiCartRender(root);if(window.WWIStore&&WWIStore.paint)try{WWIStore.paint()}catch(e){}}});
 body.querySelectorAll('[data-rm]').forEach(function(b){b.onclick=function(){var i=parseInt(b.getAttribute('data-rm'),10);var it=items[i];if(!confirm('¿Quitar "'+(it.title||'')+'" del carrito?'))return;items.splice(i,1);write(items);window.wwiCartRender(root);if(window.WWIStore&&WWIStore.paint)try{WWIStore.paint()}catch(e){}}});
 if(root.getAttribute('data-validate')==='1'){
  var payload=items.map(function(it){return{ref:it.handle,qty:parseInt(it.qty)||1,price_cents:Math.round((parseFloat(it.price)||0)*100)}});
  fetch('/api/v1/public/cart/validate',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({items:payload})}).then(function(r){return r.json()}).then(function(d){
   if(!d||!d.ok||!d.data||!d.data.issues)return;var bad=d.data.items.filter(function(x){return x.issue!=='ok'});
   if(bad.length){var w='<div class="wwi-cart__warn">';bad.forEach(function(x){w+='⚠ '+(x.title||x.ref)+': '+(x.issue==='out_of_stock'?'agotado':(x.issue==='price_changed'?'precio actualizado a '+money(x.price_cents/100):'no disponible'))+'<br/>'});w+='</div>';foot.insertAdjacentHTML('afterbegin',w)}
  }).catch(function(){});
 }
};
document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.wwi-cart').forEach(function(r){window.wwiCartRender(r)})});
if(document.readyState!=='loading')document.querySelectorAll('.wwi-cart').forEach(function(r){window.wwiCartRender(r)});
</script>
HTML;
    }
}
