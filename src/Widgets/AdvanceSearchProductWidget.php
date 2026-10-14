<?php
namespace App\Widgets;

/**
 * Advance Search Product — brick (widget) de búsqueda avanzada para WWI.
 * Reemplaza la caja de búsqueda hardcodeada por un buscador con FILTROS:
 * texto, rango de precios (sliders), marca, tipo, categoría, descuento mínimo,
 * ofertas, novedades (por fecha), disponibilidad y orden. Facetas con conteo.
 * El filtrado real es SERVER-SIDE (router del tema); el brick pinta la UI y
 * arma la query. No hardcodea contenido: datos/facetas por config.
 * Estado: INCUBADORA (alpha).
 */
class AdvanceSearchProductWidget extends Widget
{
    private static bool $assetsPrinted = false;

    public static function meta(): array
    {
        return ['id' => 'advance-search-product', 'name' => 'Advance Search Product', 'icon' => 'search', 'category' => 'ecommerce', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'placeholder', 'label' => 'Placeholder del buscador', 'type' => 'text', 'default' => '¿Qué instrumento estás buscando?'],
            ['key' => 'show_ai', 'label' => 'Botón "Buscar con IA (TIA)"', 'type' => 'toggle', 'default' => false],
            ['key' => 'show_price', 'label' => 'Filtro: rango de precios', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_brand', 'label' => 'Filtro: marcas', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_type', 'label' => 'Filtro: tipo de producto', 'type' => 'toggle', 'default' => false],
            ['key' => 'show_subcategory', 'label' => 'Filtro: subcategoría', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_category', 'label' => 'Filtro: categorías', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_discount', 'label' => 'Filtro: descuento mínimo', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_order', 'label' => 'Filtro: orden', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_offers', 'label' => 'Filtro: ofertas', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_new', 'label' => 'Filtro: novedades (por fecha)', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_available', 'label' => 'Filtro: disponibilidad', 'type' => 'toggle', 'default' => true],
            ['key' => 'new_days', 'label' => 'Novedades: días hacia atrás', 'type' => 'number', 'default' => 90],
            ['key' => 'max_price', 'label' => 'Precio máximo (0 = autodetectar)', 'type' => 'number', 'default' => 0],
        ];
    }

    /** Normaliza a [[value, label], ...] aceptando assoc (con conteo), lista de [v,l], lista o CSV. */
    private function pairs($value): array
    {
        $out = [];
        if (is_array($value)) {
            $isAssoc = array_keys($value) !== range(0, count($value) - 1);
            if ($isAssoc) {
                foreach ($value as $k => $v) {
                    $label = is_numeric($v) ? ($k . ' (' . (int)$v . ')') : (string)$v;
                    $out[] = [(string)$k, $label];
                }
            } else {
                foreach ($value as $item) {
                    if (is_array($item)) $out[] = [(string)($item[0] ?? ''), (string)($item[1] ?? $item[0] ?? '')];
                    elseif ($item !== '') $out[] = [(string)$item, (string)$item];
                }
            }
            return $out;
        }
        foreach (array_filter(array_map('trim', explode(',', (string)$value))) as $v) $out[] = [$v, $v];
        return $out;
    }

    private function money(float $n): string
    {
        return '$' . number_format($n, 0, ',', '.');
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $esc = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');

        $placeholder = (string)($c['placeholder'] ?? '¿Qué instrumento estás buscando?');
        $brands = $this->pairs($c['brands'] ?? []);
        $types = $this->pairs($c['types'] ?? []);
        $subcats = $this->pairs($c['subcats'] ?? []);
        $cats = $this->pairs($c['categories'] ?? []);
        $maxPrice = (float)($c['max_price'] ?? 0);
        $sliderMax = $maxPrice > 0 ? $maxPrice : 1000000;
        $step = max(1000, (int)round($sliderMax / 200 / 1000) * 1000);
        $newDays = max(1, (int)($c['new_days'] ?? 90));

        $g = fn($k) => isset($_GET[$k]) ? (string)$_GET[$k] : '';
        $q = $g('q');
        $fmin = (float)($g('min') ?: 0);
        $fmax = (float)($g('max') ?: 0);
        $fmaxV = $fmax > 0 ? min($fmax, $sliderMax) : $sliderMax;
        $fmarca = $g('marca'); $ftipo = $g('tipo'); $fsub = $g('sub'); $fcat = $g('cat'); $fdesc = $g('desc'); $orden = $g('orden');
        $oferta = isset($_GET['oferta']); $nuevo = isset($_GET['nuevo']); $disp = isset($_GET['disp']);

        ob_start();
        echo $this->assets();
        ?>
<form class="search ska" action="/buscar" method="get" role="search" autocomplete="off" id="ska-form" data-max="<?= (float)$sliderMax ?>" data-brands="<?= $esc(implode(',', array_map(fn($b) => $b[0], $brands))) ?>" data-types="<?= $esc(implode(',', array_map(fn($t) => $t[0], $types))) ?>" data-cats="<?= $esc(implode(',', array_map(fn($c) => $c[0], $cats))) ?>">
  <span class="s-ic" aria-hidden="true">&#128269;</span>
  <input type="search" name="q" id="skSearch" placeholder="<?= $esc($placeholder) ?>" aria-label="Buscar productos" value="<?= $esc($q) ?>" />
  <?php if (!empty($c['show_ai'])): ?><button type="button" class="ska-ai" onclick="skaAi()" title="Buscar con IA (TIA)">&#10024;</button><?php endif; ?>
  <button type="button" class="ska-toggle" onclick="skaToggle(this)" aria-expanded="false" aria-controls="ska-panel" title="Filtros avanzados" aria-label="Filtros avanzados">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><line x1="6" y1="3" x2="6" y2="21"/><line x1="12" y1="3" x2="12" y2="21"/><line x1="18" y1="3" x2="18" y2="21"/><circle cx="6" cy="8.5" r="2.6" fill="currentColor" stroke="none"/><circle cx="12" cy="15" r="2.6" fill="currentColor" stroke="none"/><circle cx="18" cy="6.5" r="2.6" fill="currentColor" stroke="none"/></svg>
  </button>
  <button type="submit">Buscar</button>
  <div class="sug" id="skSug" role="listbox" aria-label="Sugerencias" hidden></div>

  <div class="ska-panel" id="ska-panel" hidden>
    <?php if (!empty($c['show_price'])): ?>
    <div class="ska-group ska-full">
      <label class="ska-lbl">Rango de precios</label>
      <div class="ska-price">
        <input type="range" name="min" id="ska-min" min="0" max="<?= (float)$sliderMax ?>" step="<?= (int)$step ?>" value="<?= (float)$fmin ?>" oninput="skaSync()" aria-label="Precio mínimo">
        <input type="range" name="max" id="ska-max" min="0" max="<?= (float)$sliderMax ?>" step="<?= (int)$step ?>" value="<?= (float)$fmaxV ?>" oninput="skaSync()" aria-label="Precio máximo">
        <div class="ska-price-out"><span id="ska-min-o"><?= $this->money($fmin) ?></span> — <span id="ska-max-o"><?= $this->money($fmaxV) ?></span></div>
      </div>
    </div>
    <?php endif; ?>
    <?php if (!empty($c['show_brand']) && $brands): ?>
    <div class="ska-group"><label class="ska-lbl">Marca</label>
      <select name="marca" class="ska-sel"><option value="">Todas</option>
        <?php foreach ($brands as $b): ?><option value="<?= $esc($b[0]) ?>"<?= strcasecmp($fmarca, $b[0]) === 0 ? ' selected' : '' ?>><?= $esc($b[1]) ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <?php if (!empty($c['show_type']) && $types): ?>
    <div class="ska-group"><label class="ska-lbl">Tipo de producto</label>
      <select name="tipo" class="ska-sel"><option value="">Todos</option>
        <?php foreach ($types as $t): ?><option value="<?= $esc($t[0]) ?>"<?= strcasecmp($ftipo, $t[0]) === 0 ? ' selected' : '' ?>><?= $esc($t[1]) ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <?php if (!empty($c['show_subcategory']) && $subcats): ?>
    <div class="ska-group"><label class="ska-lbl">Subcategoría</label>
      <select name="sub" class="ska-sel"><option value="">Todas</option>
        <?php foreach ($subcats as $s): ?><option value="<?= $esc($s[0]) ?>"<?= strcasecmp($fsub, $s[0]) === 0 ? ' selected' : '' ?>><?= $esc($s[1]) ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <?php if (!empty($c['show_category']) && $cats): ?>
    <div class="ska-group"><label class="ska-lbl">Categoría</label>
      <select name="cat" class="ska-sel"><option value="">Todas</option>
        <?php foreach ($cats as $cat): ?><option value="<?= $esc($cat[0]) ?>"<?= $fcat === $cat[0] ? ' selected' : '' ?>><?= $esc($cat[1]) ?></option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <?php if (!empty($c['show_discount'])): ?>
    <div class="ska-group"><label class="ska-lbl">Descuento mínimo</label>
      <select name="desc" class="ska-sel">
        <option value="">Cualquiera</option>
        <?php foreach ([10, 20, 30, 50] as $d): ?><option value="<?= $d ?>"<?= $fdesc === (string)$d ? ' selected' : '' ?>>≥ <?= $d ?>%</option><?php endforeach; ?>
      </select></div>
    <?php endif; ?>
    <?php if (!empty($c['show_order'])): ?>
    <div class="ska-group"><label class="ska-lbl">Ordenar por</label>
      <select name="orden" class="ska-sel">
        <option value="">Relevancia</option>
        <option value="precio_asc"<?= $orden === 'precio_asc' ? ' selected' : '' ?>>Precio: menor a mayor</option>
        <option value="precio_desc"<?= $orden === 'precio_desc' ? ' selected' : '' ?>>Precio: mayor a menor</option>
        <option value="descuento"<?= $orden === 'descuento' ? ' selected' : '' ?>>Mayor descuento</option>
        <option value="nuevo"<?= $orden === 'nuevo' ? ' selected' : '' ?>>Novedades</option>
      </select></div>
    <?php endif; ?>
    <div class="ska-group ska-full ska-checks">
      <?php if (!empty($c['show_offers'])): ?><label><input type="checkbox" name="oferta" value="1"<?= $oferta ? ' checked' : '' ?>> Ofertas</label><?php endif; ?>
      <?php if (!empty($c['show_new'])): ?><label><input type="checkbox" name="nuevo" value="1"<?= $nuevo ? ' checked' : '' ?>> Novedades (<?= (int)$newDays ?> días)</label><?php endif; ?>
      <?php if (!empty($c['show_available'])): ?><label><input type="checkbox" name="disp" value="1"<?= $disp ? ' checked' : '' ?>> Solo disponibles</label><?php endif; ?>
    </div>
    <div class="ska-actions ska-full">
      <a href="/buscar" class="ska-clear">Limpiar</a>
      <button type="submit" class="ska-apply">Aplicar filtros</button>
    </div>
  </div>
</form>
        <?php
        return ob_get_clean();
    }

    private function assets(): string
    {
        if (self::$assetsPrinted) return '';
        self::$assetsPrinted = true;
        return <<<'HTML'
<style>
.ska{position:relative}
.ska .ska-toggle{border:none;background:transparent;color:var(--sk-muted,#777);cursor:pointer;font-size:15px;padding:0 10px;align-self:stretch;display:inline-flex;align-items:center;justify-content:center}
.ska .ska-toggle[aria-expanded="true"]{color:var(--sk-primary,#F20D16)}
.ska-panel{position:absolute;top:calc(100% + 8px);left:0;right:0;background:#fff;border:1px solid #E6E6E6;border-radius:14px;box-shadow:0 24px 60px rgba(0,0,0,.16);padding:16px;z-index:140;display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ska-panel[hidden]{display:none}
.ska-group{display:flex;flex-direction:column;gap:4px}
.ska-full{grid-column:1/-1}
.ska-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#777}
.ska-sel{width:100%;border:1px solid #E6E6E6;border-radius:9px;padding:9px 10px;font:inherit;font-size:13px;background:#fff;max-height:180px}
.ska-price input[type=range]{width:100%;accent-color:var(--sk-primary,#F20D16);margin:4px 0}
.ska-price-out{font-size:12.5px;font-weight:700;color:#111;font-family:'JetBrains Mono',Consolas,monospace}
.ska-checks{flex-direction:row;flex-wrap:wrap;gap:14px}
.ska-checks label{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#333}
.ska-actions{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:4px}
.ska-clear{font-size:12.5px;color:#777}
.ska-apply{border:none;border-radius:10px;background:var(--sk-primary,#F20D16);color:#fff;font-weight:700;font-size:13px;padding:11px 20px;cursor:pointer}
.ska-apply:hover{filter:brightness(.95)}
.ska-ai{border:none;background:transparent;color:var(--sk-primary,#F20D16);cursor:pointer;font-size:15px;padding:0 8px;align-self:stretch}
@media(max-width:900px){.ska-panel{grid-template-columns:1fr}}
</style>
<script>if(!window.skaToggle){window.skaToggle=function(b){var p=document.getElementById('ska-panel');if(!p)return;var on=p.hidden;p.hidden=!on;b.setAttribute('aria-expanded',on?'true':'false')};
window.skaSync=function(){var f=document.getElementById('ska-form');if(!f)return;var a=document.getElementById('ska-min'),b=document.getElementById('ska-max');if(!a||!b)return;var max=parseFloat(f.getAttribute('data-max'))||0;var mn=parseFloat(a.value),mx=parseFloat(b.value);if(mn>mx){if(document.activeElement===a)a.value=mx;else b.value=mn;mn=parseFloat(a.value);mx=parseFloat(b.value)}var fmt=function(n){return '$'+Math.round(n).toLocaleString('es-CO')};document.getElementById('ska-min-o').textContent=fmt(mn);document.getElementById('ska-max-o').textContent=fmt(mx)+(mx>=max?'+':'')};
window.skaAi=function(){var f=document.getElementById('ska-form');if(!f)return;var inp=document.getElementById('skSearch');var phrase=(inp&&inp.value||'').trim();if(phrase.length<3){if(inp){inp.focus();inp.style.boxShadow='0 0 0 2px rgba(242,13,22,.4)'}return}var btn=document.querySelector('.ska-ai');if(btn)btn.textContent='\u23F3';fetch('/api/v1/public/search/ai',{method:'POST',headers:{'Content-Type':'application/json; charset=UTF-8'},body:JSON.stringify({phrase:phrase,brands:f.getAttribute('data-brands')||'',types:f.getAttribute('data-types')||'',cats:f.getAttribute('data-cats')||''})}).then(function(r){return r.json()}).then(function(d){if(btn)btn.textContent='\u2728';if(!d||!d.ok){f.submit();return}var qs=[];Object.keys(d.filters||{}).forEach(function(k){qs.push(encodeURIComponent(k)+'='+encodeURIComponent(d.filters[k]))});window.location='/buscar?'+qs.join('&')}).catch(function(){if(btn)btn.textContent='\u2728';f.submit()})};window.addEventListener('submit',function(e){var f=e.target;if(!f||!f.classList||!f.classList.contains('ska'))return;if(!document.getElementById('skResults'))return;e.preventDefault();var qs=new URLSearchParams(new FormData(f)).toString();fetch('/buscar/live?'+qs).then(function(r){return r.json()}).then(function(d){if(!d||!d.ok){f.submit();return}var c=document.getElementById('skCount');if(c)c.textContent=d.count+' resultado(s)';var g=document.getElementById('skResults');if(g)g.innerHTML=d.html;try{history.pushState({},'','/buscar?'+qs)}catch(x){}}).catch(function(){f.submit()})});window.addEventListener('change',function(e){var f=(e.target&&e.target.closest)?e.target.closest('form.ska'):null;if(!f)return;if(!document.getElementById('skResults'))return;if(e.target.matches&&e.target.matches('select,input[type=checkbox]')){if(f.requestSubmit)f.requestSubmit();else f.submit()}});}</script>
HTML;
    }
}
