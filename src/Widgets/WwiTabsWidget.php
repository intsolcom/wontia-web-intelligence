<?php
namespace App\Widgets;

class WwiTabsWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-tabs', 'name' => 'WWI: Pestañas', 'icon' => 'layers', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'inline' => true, 'default' => 'Todo lo que necesitas saber'],
            ['key' => 'items', 'label' => 'Pestañas', 'type' => 'repeater', 'fields' => [
                ['key' => 'label', 'label' => 'Etiqueta', 'type' => 'text'],
                ['key' => 'content', 'label' => 'Contenido', 'type' => 'richtext'],
            ], 'default' => [
                ['label' => 'Servicios', 'content' => 'Describe aquí tus servicios principales.'],
                ['label' => 'Proceso', 'content' => 'Explica cómo trabajas, paso a paso.'],
                ['label' => 'Precios', 'content' => 'Resume tus precios o planes.'],
            ]],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $items = $this->safeJson($c['items'] ?? []);
        $clean = [];
        foreach ($items as $i => $it) {
            if (is_array($it)) $clean[] = ['i' => $i, 'label' => (string)($it['label'] ?? ''), 'content' => (string)($it['content'] ?? '')];
        }
        if (!$clean) return '';
        $uid = 't' . substr(md5(json_encode($clean)), 0, 8);
        $css = '#' . $uid . ' .tb-p{display:none}';
        foreach ($clean as $k => $t) $css .= '#' . $uid . '-' . $k . ':checked~#' . $uid . '-p' . $k . '{display:block}';
        $css .= '#' . $uid . ' .tb-l{color:var(--muted);border:1px solid var(--border2);background:var(--panel2)}';
        $css .= '#' . $uid . ' .tb-r{position:absolute;opacity:0;pointer-events:none}';
        foreach ($clean as $k => $t) $css .= '#' . $uid . '-' . $k . ':checked~.tb-nav label[for="' . $uid . '-' . $k . '"]{color:var(--accent);border-color:var(--accent)}';
        $html = '<section style="padding:80px 0"><div class="wrap">';
        if (!empty($c['title'])) $html .= '<div class="h-sec reveal"><h2 data-editable="title">' . $this->esc($c['title']) . '</h2></div>';
        $html .= '<style>' . $css . '</style>';
        $html .= '<div id="' . $uid . '" style="max-width:820px;margin:0 auto;position:relative">';
        foreach ($clean as $k => $t) {
            $html .= '<input class="tb-r" type="radio" name="' . $uid . '" id="' . $uid . '-' . $k . '"' . ($k === 0 ? ' checked' : '') . '/>';
        }
        $html .= '<div class="tb-nav" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:16px;justify-content:center">';
        foreach ($clean as $k => $t) {
            $html .= '<label class="tb-l btn" for="' . $uid . '-' . $k . '" data-editable="items.' . $t['i'] . '.label" style="cursor:pointer">' . $this->esc($t['label']) . '</label>';
        }
        $html .= '</div>';
        foreach ($clean as $k => $t) {
            $html .= '<div class="tb-p panel" id="' . $uid . '-p' . $k . '" style="padding:22px;font-size:13.5px;line-height:1.8"><div data-editable="items.' . $t['i'] . '.content">' . $this->rich($t['content']) . '</div></div>';
        }
        $html .= '</div></div></section>';
        return $html;
    }
}
