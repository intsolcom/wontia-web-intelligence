<?php
namespace App\Widgets;

/**
 * Advance Search Product — brick (widget) de búsqueda avanzada para WWI.
 * Reemplaza la caja de búsqueda hardcodeada por un buscador con FILTROS
 * (rango de precios con sliders, marca, categoría, ofertas, novedades,
 * disponibilidad, orden). El filtrado real es SERVER-SIDE (router del tema
 * + StoreService/catálogo); este brick solo pinta la interfaz y arma la query.
 *
 * Estado: INCUBADORA (en desarrollo). No hardcodea contenido: los filtros y
 * los datos (marcas, categorías, precio máximo) se inyectan por config.
 */
class AdvanceSearchProductWidget extends Widget
{
    private static bool $assetsPrinted = false;

    public static function meta(): array
    {
        return ['id' => 'advance-search-product', 'name' => 'Advance Search Product', 'icon' => 'search', 'category' => 'ecommerce', 'version' => '0.1.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'placeholder', 'label' => 'Placeholder del buscador', 'type' => 'text', 'default' => '¿Qué instrumento estás buscando?'],
            ['key' => 'show_price', 'label' => 'Filtro: rango de precios', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_brand', 'label' => 'Filtro: marcas', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_category', 'label' => 'Filtro: categorías', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_order', 'label' => 'Filtro: orden', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_offers', 'label' => 'Filtro: ofertas', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_new', 'label' => 'Filtro: artículos nuevos', 'type' => 'toggle', 'default' => true],
            ['key' => 'show_available', 'label' => 'Filtro: disponibilidad', 'type' => 'toggle', 'default' => true],
            ['key' => 'brands', 'label' => 'Marcas (se autodetectan del catálogo)', 'type' => 'textarea', 'default' => ''],
            ['key' => 'categories', 'label' => 'Categorías (se autodetectan del catálogo)', 'type' => 'text', 'default' => ''],
            ['key' => 'max_price', 'label' => 'Precio máximo (0 = autodetectar)', 'type' => 'number', 'default' => 0],
        ];
    }

    private function list($value): array
    {
        if (is_array($value)) return array_values(array_filter(array_map('strval', $value), fn($v) => $v !== ''));
        $value = trim((string)$value);
        if ($value === '') return [];
        return array_values(array_filter(array_map('trim', explode(',', $value)), fn($v) => $v !== ''));
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
        $brands = $this->list($c['brands'] ?? []);
        $cats = [];
        foreach ((array)($c['categories'] ?? []) as $cat) {
            if (is_array($cat) && isset($cat[0])) $cats[] = [(string)$cat[0], (string)($cat[1] ?? $cat[0])];
            elseif (is_string($cat)) $cats[] = [$cat, ucfirst($cat)];
        }
        $maxPrice = (float)($c['max_price'] ?? 0);
        $sliderMax = $maxPrice > 0 ? $maxPrice : 1000000;
        $step = max(1000, (int)round($sliderMax / 200 / 1000) * 1000);

        $g = fn($k) => isset($_GET[$k]) ? (string)$_GET[$k] : '';
        $q = $g('q');
        $fmin = (float)($g('min') ?: 0);
        $fmax = (float)($g('max') ?: 0);
        $fmaxV = $fmax > 0 ? min($fmax, $sliderMax) : $sliderMax;
        $fmarca = $g('marca');
        $fcat = $g('cat');
        $orden = $g('orden');
        $oferta = isset($_GET['oferta']);
        $nuevo = isset($_GET['nuevo']);
        $disp = isset($_GET['disp']);

        ob_start();
        echo $this->assets();
        ?>
<form class="search ska" action="/buscar" method="get" role="search" autocomplete="off" id="ska-form" data-max="<?= (float)$sliderMax ?>">
  <span class="s-ic" aria-hidden="true">&#128269;</span>
  <input type="search" name="q" id="skSearch" placeholder="<?= $esc($placeholder) ?>" aria-label="Buscar productos" value="<?= $esc($q) ?>" />
  <button type="button" class="ska-toggle" onclick="skaToggle(this)" aria-expanded="false" aria-controls="ska-panel" title="Filtros avanzados">&#9881;</button>
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
        <?php foreach ($brands as $b): ?><option value="<?= $esc($b) ?>"<?= strcasecmp($fmarca, (string)$b) === 0 ? ' selected' : '' ?>><?= $esc($b) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <?php if (!empty($c['show_category']) && $cats): ?>
    <div class="ska-group"><label class="ska-lbl">Categoría</label>
      <select name="cat" class="ska-sel"><option value="">Todas</option>
        <?php foreach ($cats as $cat): ?><option value="<?= $esc($cat[0]) ?>"<?= $fcat === (string)$cat[0] ? ' selected' : '' ?>><?= $esc($cat[1]) ?></option><?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <?php if (!empty($c['show_order'])): ?>
    <div class="ska-group"><label class="ska-lbl">Ordenar por</label>
      <select name="orden" class="ska-sel">
        <option value="">Relevancia</option>
        <option value="precio_asc"<?= $orden === 'precio_asc' ? ' selected' : '' ?>>Precio: menor a mayor</option>
        <option value="precio_desc"<?= $orden === 'precio_desc' ? ' selected' : '' ?>>Precio: mayor a menor</option>
        <option value="nuevo"<?= $orden === 'nuevo' ? ' selected' : '' ?>>Novedades</option>
      </select>
    </div>
    <?php endif; ?>
    <div class="ska-group ska-full ska-checks">
      <?php if (!empty($c['show_offers'])): ?><label><input type="checkbox" name="oferta" value="1"<?= $oferta ? ' checked' : '' ?>> Ofertas</label><?php endif; ?>
      <?php if (!empty($c['show_new'])): ?><label><input type="checkbox" name="nuevo" value="1"<?= $nuevo ? ' checked' : '' ?>> Artículos nuevos</label><?php endif; ?>
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
.ska .ska-toggle{border:none;background:transparent;color:var(--sk-muted,#777);cursor:pointer;font-size:15px;padding:0 10px;align-self:stretch}
.ska .ska-toggle[aria-expanded="true"]{color:var(--sk-primary,#F20D16)}
.ska-panel{position:absolute;top:calc(100% + 8px);left:0;right:0;background:#fff;border:1px solid #E6E6E6;border-radius:14px;box-shadow:0 24px 60px rgba(0,0,0,.16);padding:16px;z-index:140;display:grid;grid-template-columns:1fr 1fr;gap:12px}
.ska-panel[hidden]{display:none}
.ska-group{display:flex;flex-direction:column;gap:4px}
.ska-full{grid-column:1/-1}
.ska-lbl{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:#777}
.ska-sel{width:100%;border:1px solid #E6E6E6;border-radius:9px;padding:9px 10px;font:inherit;font-size:13px;background:#fff}
.ska-price input[type=range]{width:100%;accent-color:var(--sk-primary,#F20D16);margin:4px 0}
.ska-price-out{font-size:12.5px;font-weight:700;color:#111;font-family:'JetBrains Mono',Consolas,monospace}
.ska-checks{flex-direction:row;flex-wrap:wrap;gap:14px}
.ska-checks label{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:600;color:#333}
.ska-actions{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:4px}
.ska-clear{font-size:12.5px;color:#777}
.ska-apply{border:none;border-radius:10px;background:var(--sk-primary,#F20D16);color:#fff;font-weight:700;font-size:13px;padding:11px 20px;cursor:pointer}
.ska-apply:hover{filter:brightness(.95)}
@media(max-width:900px){.ska-panel{grid-template-columns:1fr}}
</style>
<script>if(!window.skaToggle){window.skaToggle=function(b){var p=document.getElementById('ska-panel');if(!p)return;var on=p.hidden;p.hidden=!on;b.setAttribute('aria-expanded',on?'true':'false')};
window.skaSync=function(){var f=document.getElementById('ska-form');if(!f)return;var a=document.getElementById('ska-min'),b=document.getElementById('ska-max');if(!a||!b)return;var max=parseFloat(f.getAttribute('data-max'))||0;var mn=parseFloat(a.value),mx=parseFloat(b.value);if(mn>mx){if(document.activeElement===a)a.value=mx;else b.value=mn;mn=parseFloat(a.value);mx=parseFloat(b.value)}var fmt=function(n){return '$'+Math.round(n).toLocaleString('es-CO')};document.getElementById('ska-min-o').textContent=fmt(mn);document.getElementById('ska-max-o').textContent=fmt(mx)+(mx>=max?'+':'')};}</script>
HTML;
    }
}
