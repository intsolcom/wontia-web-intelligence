<?php
namespace App\Widgets;

use App\Core\Config;
use App\Services\StoreService;

abstract class StoreWidgetBase extends Widget
{
    protected static bool $assetsPrinted = false;

    protected function siteId(): int
    {
        return (int)Config::get('SITE_ID', '1');
    }

    protected function store(): StoreService
    {
        return new StoreService();
    }

    protected function money(int $cents, string $currency = 'COP'): string
    {
        return (new StoreService())->money($cents, $currency);
    }

    protected function currency(): string
    {
        $s = $this->store()->settings();
        return $s['store_currency'] ?: 'COP';
    }

    protected function assets(): string
    {
        if (static::$assetsPrinted) return '';
        static::$assetsPrinted = true;
        $siteId = $this->siteId();
        $currency = $this->currency();
        $css = <<<'CSS'
<style id="wwi-store-css">
.st-section{padding:80px 24px;max-width:1160px;margin:0 auto}
.st-head{text-align:center;margin-bottom:34px}
.st-head h2{font-size:clamp(26px,3.4vw,40px);font-weight:800;letter-spacing:-.02em;margin:0 0 10px}
.st-head p{color:var(--muted,#6b7280);font-size:15px;margin:0}
.st-filters{display:flex;gap:10px;flex-wrap:wrap;align-items:center;justify-content:center;margin-bottom:26px}
.st-chip{display:inline-block;padding:8px 16px;border-radius:999px;border:1px solid rgba(120,120,140,.25);font-size:13px;text-decoration:none;color:inherit;transition:.18s}
.st-chip:hover{border-color:var(--accent,#7C3AED)}
.st-chip.on{background:var(--accent,#7C3AED);border-color:var(--accent,#7C3AED);color:#fff}
.st-search{display:flex;gap:8px}
.st-search input{flex:1;min-width:180px;padding:10px 14px;border-radius:10px;border:1px solid rgba(120,120,140,.3);font:inherit;font-size:14px;background:transparent;color:inherit}
.st-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:22px}
.st-card{border:1px solid rgba(120,120,140,.16);border-radius:18px;overflow:hidden;background:var(--panel,#fff);display:flex;flex-direction:column;transition:.2s}
.st-card:hover{transform:translateY(-3px);box-shadow:0 14px 40px rgba(20,20,40,.08)}
.st-thumb{position:relative;aspect-ratio:1/1;background:rgba(120,120,140,.08);overflow:hidden}
.st-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.st-thumb .st-ph{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:34px;font-weight:800;color:rgba(120,120,140,.5)}
.st-flag{position:absolute;top:10px;left:10px;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:700;background:#111827;color:#fff}
.st-body{padding:16px;display:flex;flex-direction:column;gap:8px;flex:1}
.st-name{font-size:15px;font-weight:700;line-height:1.35;margin:0}
.st-name a{color:inherit;text-decoration:none}
.st-price{font-size:17px;font-weight:800}
.st-cmp{font-size:13px;color:var(--muted,#6b7280);text-decoration:line-through;margin-left:8px;font-weight:500}
.st-desc{font-size:12.5px;color:var(--muted,#6b7280);line-height:1.5;margin:0}
.st-actions{margin-top:auto;display:flex;gap:8px}
.st-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:11px 18px;border-radius:11px;border:none;font:inherit;font-size:13.5px;font-weight:700;cursor:pointer;text-decoration:none;transition:.18s}
.st-btn-primary{background:var(--accent,#7C3AED);color:#fff;flex:1}
.st-btn-primary:hover{filter:brightness(1.08)}
.st-btn-outline{background:transparent;border:1px solid rgba(120,120,140,.3);color:inherit}
.st-btn:disabled{opacity:.5;cursor:not-allowed}
.st-stock{font-size:11.5px;font-weight:600;color:#b45309}
.st-stock.out{color:#b91c1c}
.st-empty{text-align:center;padding:60px 20px;color:var(--muted,#6b7280)}
.st-pager{display:flex;gap:10px;justify-content:center;margin-top:34px}
.st-pd{display:grid;grid-template-columns:1.05fr 1fr;gap:44px;align-items:start}
.st-pd-gal{border-radius:20px;overflow:hidden;border:1px solid rgba(120,120,140,.16);background:rgba(120,120,140,.06)}
.st-pd-gal img{width:100%;display:block}
.st-pd-info h1{font-size:clamp(24px,3vw,36px);font-weight:800;letter-spacing:-.02em;margin:0 0 12px}
.st-pd-price{font-size:26px;font-weight:800;margin:6px 0 14px}
.st-pd-desc{font-size:14.5px;line-height:1.7;color:var(--muted,#4b5563);white-space:pre-line}
.st-field{margin-bottom:14px}
.st-field label{display:block;font-size:12.5px;font-weight:600;margin-bottom:6px}
.st-field input,.st-field select,.st-field textarea{width:100%;padding:11px 13px;border-radius:10px;border:1px solid rgba(120,120,140,.3);font:inherit;font-size:14px;background:transparent;color:inherit}
.st-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.st-cart-item{display:grid;grid-template-columns:76px 1fr auto;gap:14px;align-items:center;padding:14px 0;border-bottom:1px solid rgba(120,120,140,.14)}
.st-cart-item img{width:76px;height:76px;object-fit:cover;border-radius:12px;background:rgba(120,120,140,.1)}
.st-qty{width:64px;padding:8px;border-radius:8px;border:1px solid rgba(120,120,140,.3);font:inherit;text-align:center;background:transparent;color:inherit}
.st-totals{border:1px solid rgba(120,120,140,.16);border-radius:16px;padding:22px;background:var(--panel,#fff)}
.st-totals .r{display:flex;justify-content:space-between;font-size:14px;padding:7px 0}
.st-totals .r.big{font-size:19px;font-weight:800;border-top:1px solid rgba(120,120,140,.16);margin-top:8px;padding-top:14px}
.st-pay{display:flex;flex-direction:column;gap:10px}
.st-pay label{display:flex;gap:10px;align-items:flex-start;border:1px solid rgba(120,120,140,.25);border-radius:12px;padding:12px 14px;cursor:pointer;font-size:13.5px}
.st-pay input{margin-top:3px}
.st-msg{padding:14px 16px;border-radius:12px;font-size:13.5px;margin-bottom:16px}
.st-msg.err{background:rgba(185,28,28,.08);color:#b91c1c}
.st-msg.ok{background:rgba(5,150,105,.1);color:#047857}
.st-load{text-align:center;padding:50px;color:var(--muted,#6b7280)}
@media(max-width:860px){.st-pd{grid-template-columns:1fr;gap:26px}.st-row{grid-template-columns:1fr}}
</style>
CSS;
        $js = '<script>if(!window.WWIStore){(function(){var S={siteId:' . $siteId . ',key:"wwi_cart_' . $siteId . '",currency:"' . $this->esc($currency) . '"};'
            . 'S.get=function(){try{var v=JSON.parse(localStorage.getItem(S.key)||"[]");return Array.isArray(v)?v:[]}catch(e){return[]}};'
            . 'S.set=function(v){try{localStorage.setItem(S.key,JSON.stringify(v))}catch(e){}document.dispatchEvent(new Event("wwi-cart-change"))};'
            . 'S.add=function(it){var c=S.get(),f=-1,i;for(i=0;i<c.length;i++){if(c[i].product_id===it.product_id&&(c[i].variant_id||null)===(it.variant_id||null)){f=i;break}}if(f>-1){c[f].qty=(c[f].qty||1)+(it.qty||1)}else{c.push(it)}S.set(c)};'
            . 'S.remove=function(i){var c=S.get();c.splice(i,1);S.set(c)};'
            . 'S.qty=function(i,q){var c=S.get();if(!c[i])return;c[i].qty=Math.max(1,parseInt(q)||1);S.set(c)};'
            . 'S.count=function(){return S.get().reduce(function(a,b){return a+(parseInt(b.qty)||1)},0)};'
            . 'S.subtotal=function(){return S.get().reduce(function(a,b){return a+(parseInt(b.price_cents)||0)*(parseInt(b.qty)||1)},0)};'
            . 'S.money=function(c){var v=(parseInt(c)||0)/100;try{return (S.currency==="USD"?"US$":"$")+v.toLocaleString("es-CO",{maximumFractionDigits:S.currency==="COP"?0:2})}catch(e){return "$"+v}};'
            . 'S.badge=function(){var n=S.count();document.querySelectorAll("[data-st-cart-count]").forEach(function(el){el.textContent=n;el.style.display=n>0?"":"none"})};'
            . 'S.clear=function(){S.set([])};'
            . 'window.WWIStore=S;document.addEventListener("wwi-cart-change",S.badge);if(document.readyState!=="loading"){S.badge()}else{document.addEventListener("DOMContentLoaded",S.badge)}})()}'
            . 'function wwiAddToCart(el,item){window.WWIStore.add(item);var t=el.textContent;el.textContent="Añadido";el.disabled=true;setTimeout(function(){el.textContent=t;el.disabled=false},1200)}'
            . '</script>';
        return $css . $js;
    }

    protected function thumb(array $p, string $cls = ''): string
    {
        $img = $p['images'][0] ?? '';
        if ($img) {
            return '<div class="st-thumb"><img src="' . $this->esc($img) . '" alt="' . $this->esc((string)$p['name']) . '" loading="lazy"/></div>';
        }
        $initial = mb_strtoupper(mb_substr((string)$p['name'], 0, 1));
        return '<div class="st-thumb"><div class="st-ph">' . $this->esc($initial) . '</div></div>';
    }

    protected function cartItemJson(array $p, ?array $v = null): string
    {
        return htmlspecialchars($this->cartItemRaw($p, $v), ENT_QUOTES, 'UTF-8');
    }

    protected function cartItemRaw(array $p, ?array $v = null): string
    {
        return json_encode([
            'product_id' => (int)$p['id'],
            'variant_id' => $v ? (int)$v['id'] : null,
            'name' => (string)$p['name'],
            'variant' => $v ? (string)$v['name'] : '',
            'price_cents' => $v && (int)$v['price_cents'] > 0 ? (int)$v['price_cents'] : (int)$p['price_cents'],
            'image' => (string)($p['images'][0] ?? ''),
            'slug' => (string)$p['slug'],
            'qty' => 1,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
    }
}
