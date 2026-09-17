<?php
namespace App\Widgets;

class StoreProductWidget extends StoreWidgetBase
{
    public static function meta(): array
    {
        return ['id' => 'store-product', 'name' => 'Store: Producto', 'icon' => 'box', 'category' => 'commerce', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'not_found_text', 'label' => 'Texto si no existe', 'type' => 'text', 'inline' => true, 'default' => 'Selecciona un producto del catalogo.'],
            ['key' => 'add_label', 'label' => 'Texto boton agregar', 'type' => 'text', 'default' => 'Agregar al carrito'],
            ['key' => 'back_label', 'label' => 'Texto volver', 'type' => 'text', 'default' => 'Volver al catalogo'],
            ['key' => 'show_related', 'label' => 'Mostrar relacionados (1/0)', 'type' => 'text', 'default' => '1'],
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
        $currency = $svc->settings()['store_currency'] ?: 'COP';
        $slug = trim((string)($_GET['p'] ?? ''));
        $p = $slug !== '' ? $svc->productBySlug($slug) : null;
        $base = strtok($_SERVER['REQUEST_URI'] ?? '/', '?') ?: '/';

        if (!$p) {
            return $html . '<section class="st-section"><div class="st-empty" data-editable="not_found_text">' . $this->esc($c['not_found_text']) . '</div>'
                . '<div class="st-pager"><a class="st-btn st-btn-outline" href="' . $this->esc($base) . '">' . $this->esc($c['back_label']) . '</a></div></section>';
        }

        $variants = array_values(array_filter($svc->variants((int)$p['id']), fn($v) => (int)$v['is_active'] === 1));
        $out = (int)$p['track_stock'] === 1 && (int)$p['stock'] <= 0;

        $html .= '<section class="st-section">';
        $html .= '<div class="st-pd">';
        $html .= '<div class="st-pd-gal">';
        $images = $p['images'] ?: [];
        if ($images) {
            $html .= '<img id="st-pd-img" src="' . $this->esc((string)$images[0]) . '" alt="' . $this->esc((string)$p['name']) . '"/>';
            if (count($images) > 1) {
                $html .= '<div style="display:flex;gap:8px;padding:10px;flex-wrap:wrap">';
                foreach ($images as $i => $img) {
                    $html .= '<img src="' . $this->esc((string)$img) . '" onclick="document.getElementById(\'st-pd-img\').src=this.src" style="width:56px;height:56px;object-fit:cover;border-radius:8px;cursor:pointer" alt=""/>';
                }
                $html .= '</div>';
            }
        } else {
            $html .= '<div class="st-thumb" style="aspect-ratio:1/1"><div class="st-ph">' . $this->esc(mb_strtoupper(mb_substr((string)$p['name'], 0, 1))) . '</div></div>';
        }
        $html .= '</div>';

        $html .= '<div class="st-pd-info">';
        $html .= '<h1 data-editable="name">' . $this->esc((string)$p['name']) . '</h1>';
        if ($p['short_description']) $html .= '<p class="st-desc" style="font-size:14.5px">' . $this->esc((string)$p['short_description']) . '</p>';
        $html .= '<div class="st-pd-price" id="st-pd-price">' . $this->esc($this->money((int)$p['price_cents'], $currency));
        if ((int)$p['compare_price_cents'] > (int)$p['price_cents']) $html .= '<span class="st-cmp">' . $this->esc($this->money((int)$p['compare_price_cents'], $currency)) . '</span>';
        $html .= '</div>';
        if ($out) $html .= '<div class="st-stock out" style="font-size:13px;margin-bottom:12px">Agotado</div>';

        if ($variants) {
            $html .= '<div class="st-field"><label>Presentacion</label><select id="st-pd-variant" onchange="wwiPdVariant(this)">';
            foreach ($variants as $v) {
                $vPrice = (int)$v['price_cents'] > 0 ? (int)$v['price_cents'] : (int)$p['price_cents'];
                $vOut = (int)$p['track_stock'] === 1 && (int)$v['stock'] <= 0;
                $html .= '<option value="' . (int)$v['id'] . '" data-price="' . $vPrice . '" data-out="' . ($vOut ? '1' : '0') . '"' . ($vOut ? ' disabled' : '') . '>'
                    . $this->esc((string)$v['name']) . ' — ' . $this->esc($this->money($vPrice, $currency)) . ($vOut ? ' (agotado)' : '') . '</option>';
            }
            $html .= '</select></div>';
        }

        $html .= '<div class="st-row" style="align-items:end">';
        $html .= '<div class="st-field" style="margin:0"><label>Cantidad</label><input type="number" id="st-pd-qty" value="1" min="1" max="99" style="max-width:110px"/></div>';
        $html .= '<div class="st-field" style="margin:0"><button class="st-btn st-btn-primary" id="st-pd-add" style="width:100%;padding:13px 18px"' . ($out ? ' disabled' : '') . ' onclick="wwiPdAdd()">' . $this->esc($c['add_label']) . '</button></div>';
        $html .= '</div>';
        $html .= '<div id="st-pd-msg" style="margin-top:12px"></div>';

        if ($p['description']) {
            $html .= '<div style="margin-top:26px"><h3 style="font-size:16px;margin-bottom:10px">Descripcion</h3><div class="st-pd-desc" data-editable="description">' . $this->esc((string)$p['description']) . '</div></div>';
        }

        $html .= '<div class="st-pager" style="justify-content:flex-start"><a class="st-btn st-btn-outline" href="' . $this->esc($base) . '">' . $this->esc($c['back_label']) . '</a></div>';
        $html .= '</div></div>';

        if (!empty($c['show_related']) && $c['show_related'] !== '0' && !empty($p['category_id'])) {
            $rel = $svc->products(['status' => 'active', 'category_id' => (int)$p['category_id'], 'limit' => 5]);
            $rel = array_values(array_filter($rel, fn($r) => (int)$r['id'] !== (int)$p['id']));
            if ($rel) {
                $html .= '<div style="margin-top:70px"><h2 style="font-size:22px;font-weight:800;margin-bottom:20px">Tambien te puede interesar</h2><div class="st-grid">';
                foreach (array_slice($rel, 0, 4) as $r) {
                    $rOut = (int)$r['track_stock'] === 1 && (int)$r['stock'] <= 0;
                    $html .= '<article class="st-card"><a href="' . $this->esc($base) . '?p=' . urlencode((string)$r['slug']) . '" style="text-decoration:none;color:inherit">' . $this->thumb($r) . '</a><div class="st-body">'
                        . '<h3 class="st-name"><a href="' . $this->esc($base) . '?p=' . urlencode((string)$r['slug']) . '">' . $this->esc((string)$r['name']) . '</a></h3>'
                        . '<div class="st-price">' . $this->esc($this->money((int)$r['price_cents'], $currency)) . '</div>'
                        . '<div class="st-actions"><a class="st-btn st-btn-outline" style="flex:1" href="' . $this->esc($base) . '?p=' . urlencode((string)$r['slug']) . '">Ver</a></div>'
                        . ($rOut ? '<div class="st-stock out">Agotado</div>' : '')
                        . '</div></article>';
                }
                $html .= '</div></div>';
            }
        }

        $html .= '</section>';

        $variantsJs = json_encode(array_map(fn($v) => ['id' => (int)$v['id'], 'price' => (int)$v['price_cents']], $variants));
        $html .= '<script>(function(){var P=' . $this->cartItemRaw($p) . ';var V=' . ($variantsJs ?: '[]') . ';var C="' . $this->esc($currency) . '";'
            . 'window.wwiPdVariant=function(sel){var o=sel.options[sel.selectedIndex];document.getElementById("st-pd-price").innerHTML=window.WWIStore.money(parseInt(o.dataset.price))+"";'
            . 'var btn=document.getElementById("st-pd-add");var qty=document.getElementById("st-pd-qty");if(o.dataset.out==="1"){btn.disabled=true;qty.disabled=true}else{btn.disabled=false;qty.disabled=false}};'
            . 'window.wwiPdAdd=function(){var sel=document.getElementById("st-pd-variant");var item=Object.assign({},P);'
            . 'if(sel){var o=sel.options[sel.selectedIndex];item.variant_id=parseInt(sel.value);item.variant=o.textContent.split(" — ")[0];item.price_cents=parseInt(o.dataset.price)}'
            . 'item.qty=Math.max(1,parseInt(document.getElementById("st-pd-qty").value)||1);'
            . 'window.WWIStore.add(item);var m=document.getElementById("st-pd-msg");m.innerHTML=\'<div class="st-msg ok">Producto agregado al carrito. <a href="#st-cart" style="color:inherit;text-decoration:underline">Ver carrito</a></div>\';'
            . 'setTimeout(function(){m.innerHTML=""},4000)};})();</script>';

        return $html;
    }
}
