<?php
namespace App\Widgets;

class SonikaFooterWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'sonika-footer', 'name' => 'SONIKA — Footer corporativo', 'icon' => 'grid', 'category' => 'footer', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => '_h_layout', 'label' => 'Disposición', 'type' => 'heading'],
            ['key' => 'layout', 'label' => 'Columnas', 'type' => 'select', 'options' => ['2' => '2 columnas', '3' => '3 columnas', '4' => '4 columnas', '5' => '5 columnas — Corporativo'], 'default' => '5'],
            ['key' => '_h_brand', 'label' => 'Marca', 'type' => 'heading'],
            ['key' => 'desc', 'label' => 'Descripción de la marca', 'type' => 'textarea', 'default' => 'Instrumentos musicales, audio e iluminación profesional. Sonido de alto nivel para músicos, estudios y eventos.'],
            ['key' => 'disclosure', 'label' => 'Divulgación corporativa', 'type' => 'textarea', 'default' => 'Sónika es una marca comercial de Intsolcom, LLC, USA. Operado por Intsolcom S.A.S. en Colombia.'],
            ['key' => '_h_pol', 'label' => 'Políticas', 'type' => 'heading'],
            ['key' => 'policies', 'label' => 'Enlaces de políticas', 'type' => 'repeater', 'subfields' => [
                ['key' => 'label', 'label' => 'Título', 'type' => 'text'],
                ['key' => 'url', 'label' => 'URL', 'type' => 'url'],
            ], 'default' => [
                ['label' => 'Política de pagos en línea', 'url' => '/politica-de-pagos-en-linea'],
                ['label' => 'Devoluciones, Retracto y Garantía', 'url' => '/politica-de-devoluciones'],
                ['label' => 'Política de Envíos', 'url' => '/politicas#envios'],
                ['label' => 'Privacidad y Tratamiento de Datos', 'url' => '/politicas#privacidad'],
                ['label' => 'Términos y Condiciones', 'url' => '/politicas#terminos'],
                ['label' => 'Información sobre Promociones', 'url' => '/politicas#promociones'],
                ['label' => 'PQRS', 'url' => '/politicas#pqrs'],
            ]],
            ['key' => '_h_contact', 'label' => 'Contacto corporativo', 'type' => 'heading'],
            ['key' => 'us_name', 'label' => 'EE.UU. — Entidad', 'type' => 'text', 'default' => '🇺🇸 Intsolcom, LLC'],
            ['key' => 'us_country', 'label' => 'EE.UU. — País', 'type' => 'text', 'default' => ''],
            ['key' => 'us_address', 'label' => 'EE.UU. — Dirección', 'type' => 'text', 'default' => '390 NE 191st St, STE 17284, Miami, FL 33179'],
            ['key' => 'us_phone', 'label' => 'EE.UU. — Teléfono', 'type' => 'text', 'default' => '+1 (786) 386-1515'],
            ['key' => 'us_email', 'label' => 'EE.UU. — Email', 'type' => 'text', 'default' => 'customer@intsolcom.com'],
            ['key' => 'co_name', 'label' => 'Colombia — Entidad', 'type' => 'text', 'default' => '🇨🇴 Intsolcom SAS'],
            ['key' => 'co_country', 'label' => 'Colombia — Sede', 'type' => 'text', 'default' => ''],
            ['key' => 'co_address', 'label' => 'Colombia — Dirección', 'type' => 'text', 'default' => 'Cra 55 # 100 - 51 Piso 13, Centro Empresarial Blue Garden'],
            ['key' => 'co_phone', 'label' => 'Colombia — Teléfono', 'type' => 'text', 'default' => '+57 3116020005'],
            ['key' => 'co_email', 'label' => 'Colombia — Email', 'type' => 'text', 'default' => 'cliente@intsolcom.com'],
            ['key' => '_h_cred', 'label' => 'Créditos y estilo', 'type' => 'heading'],
            ['key' => 'credits', 'label' => 'Créditos', 'type' => 'text', 'default' => 'Theme: SONIKA es de autoría de Wontia WWI, perteneciente a Intsolcom, LLC — US | Intsolcom S.A.S. — COL | © 2026'],
            ['key' => 'show_credits', 'label' => 'Mostrar créditos', 'type' => 'toggle', 'default' => true],
            ['key' => 'bg', 'label' => 'Fondo', 'type' => 'color', 'default' => '#0b0b0b'],
            ['key' => 'fg', 'label' => 'Texto principal', 'type' => 'color', 'default' => '#ffffff'],
            ['key' => 'muted', 'label' => 'Texto secundario', 'type' => 'color', 'default' => '#9a9a9a'],
            ['key' => 'accent', 'label' => 'Acento', 'type' => 'color', 'default' => '#F20D16'],
        ];
    }

    public static function defaultConfig(): array
    {
        $c = [];
        foreach (self::configSchema() as $f) {
            if (isset($f['default'])) $c[$f['key']] = $f['default'];
        }
        return $c;
    }

    private function col(string $v, string $d): string
    {
        return preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $v) ? $v : $d;
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $cols = max(2, min(5, (int)($c['layout'] ?? 5)));
        $bg = $this->col((string)($c['bg'] ?? ''), '#0b0b0b');
        $fg = $this->col((string)($c['fg'] ?? ''), '#ffffff');
        $mut = $this->col((string)($c['muted'] ?? ''), '#9a9a9a');
        $acc = $this->col((string)($c['accent'] ?? ''), '#F20D16');

        $explora = [];
        if (function_exists('wwi_nav')) {
            foreach (['footer_shop', 'footer_help'] as $loc) {
                $items = wwi_nav($loc);
                if (is_array($items)) foreach ($items as $it) $explora[] = ['label' => $it['label'], 'url' => $it['url']];
            }
        }
        if (!$explora) $explora = [['label' => 'Tienda', 'url' => '/tienda'], ['label' => 'Categorías', 'url' => '/categorias'], ['label' => 'Mi cuenta', 'url' => '/cuenta'], ['label' => 'Carrito', 'url' => '/carrito']];

        $cats = [];
        if (function_exists('sonika_categories')) {
            foreach (sonika_categories() as $slug => $cat) $cats[] = ['label' => $cat['name'], 'url' => '/buscar?cat=' . $slug];
        }

        $pol = $this->safeJson($c['policies'] ?? []);

        $h = '<style>.sf{background:' . $bg . ';color:' . $fg . ';font-size:13.5px;border-top:1px solid rgba(255,255,255,.08)}.sf *{box-sizing:border-box}.sf__wrap{max-width:1180px;margin:0 auto;padding:44px 22px 0}.sf__grid{display:grid;grid-template-columns:repeat(' . $cols . ',1fr);gap:30px}.sf__col h4{font-size:12px;font-weight:800;letter-spacing:.08em;text-transform:uppercase;color:' . $fg . ';margin:0 0 12px}.sf__col ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:8px}.sf__col a{color:' . $mut . ';text-decoration:none;transition:color .15s}.sf__col a:hover,.sf__col a:focus-visible{color:' . $acc . '}.sf__logo img{height:32px;width:auto;filter:brightness(0) invert(1)}.sf__desc{margin:14px 0 0;color:' . $mut . ';line-height:1.6;max-width:300px}.sf__disc{margin:10px 0 0;font-size:11.5px;color:' . $mut . ';line-height:1.55;max-width:320px}.sf__ent{margin-bottom:14px}.sf__ent strong{display:block;color:' . $fg . '}.sf__ent span{display:block;font-size:11.5px;color:' . $mut . ';margin-bottom:4px}.sf__ent a{font-size:12px}.sf__credits{border-top:1px solid rgba(255,255,255,.08);margin-top:34px;padding:16px 0;font-size:12px;color:' . $mut . ';text-align:center;line-height:1.5}@media(max-width:900px){.sf__grid{grid-template-columns:repeat(2,1fr);gap:22px}}@media(max-width:560px){.sf__grid{grid-template-columns:1fr}}</style>';

        $h .= '<footer class="sf" role="contentinfo"><div class="sf__wrap"><div class="sf__grid">';

        $logo = function_exists('wwi_logo_img') ? wwi_logo_img(120) : '';
        $h .= '<div class="sf__col sf__brand">' . ($logo !== '' ? '<a class="sf__logo" href="/" aria-label="SONIKA — Inicio">' . str_replace('display:block', 'display:block', $logo) . '</a>' : '<a class="sf__logo" href="/" aria-label="SONIKA — Inicio"><strong>SONIKA</strong></a>')
            . '<p class="sf__desc">' . $this->esc((string)($c['desc'] ?? '')) . '</p>'
            . ((string)($c['disclosure'] ?? '') !== '' ? '<p class="sf__disc">' . $this->esc((string)$c['disclosure']) . '</p>' : '')
            . '</div>';

        $h .= '<div class="sf__col"><h4>Explora SONIKA</h4><ul>';
        foreach ($explora as $it) $h .= '<li><a href="' . $this->esc((string)$it['url']) . '">' . $this->esc((string)$it['label']) . '</a></li>';
        $h .= '</ul></div>';

        $h .= '<div class="sf__col"><h4>Categorías</h4><ul>';
        foreach ($cats as $it) $h .= '<li><a href="' . $this->esc($it['url']) . '">' . $this->esc($it['label']) . '</a></li>';
        $h .= '</ul></div>';

        $h .= '<div class="sf__col"><h4>Políticas</h4><ul>';
        foreach ($pol as $it) {
            $lbl = trim((string)($it['label'] ?? '')); $u = trim((string)($it['url'] ?? ''));
            if ($lbl === '') continue;
            $h .= '<li>' . ($u !== '' ? '<a href="' . $this->esc($u) . '">' . $this->esc($lbl) . '</a>' : $this->esc($lbl)) . '</li>';
        }
        $h .= '</ul></div>';

        $h .= '<div class="sf__col sf__contact"><h4>Contacto corporativo</h4>';
        foreach ([['us', 'Intsolcom, LLC'], ['co', 'Intsolcom S.A.S.']] as $ent) {
            $p = $ent[0];
            $name = trim((string)($c[$p . '_name'] ?? '')); if ($name === '') continue;
            $h .= '<div class="sf__ent"><strong>' . $this->esc($name) . '</strong>';
            if (trim((string)($c[$p . '_country'] ?? '')) !== '') $h .= '<span>' . $this->esc((string)$c[$p . '_country']) . '</span>';
            if (trim((string)($c[$p . '_address'] ?? '')) !== '') $h .= '<span>' . $this->esc((string)$c[$p . '_address']) . '</span>';
            $ph = trim((string)($c[$p . '_phone'] ?? ''));
            if ($ph !== '') $h .= '<a href="tel:' . $this->esc(preg_replace('/[^0-9+]/', '', $ph)) . '">' . $this->esc($ph) . '</a><br>';
            $em = trim((string)($c[$p . '_email'] ?? ''));
            if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)) $h .= '<a href="mailto:' . $this->esc($em) . '">' . $this->esc($em) . '</a>';
            $h .= '</div>';
        }
        $h .= '</div>';

        $h .= '</div>';
        if (!empty($c['show_credits'])) $h .= '<div class="sf__credits">' . $this->esc((string)($c['credits'] ?? '')) . '</div>';
        $h .= '</div></footer>';
        return $h;
    }
}
