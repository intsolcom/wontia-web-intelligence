<?php
namespace App\Widgets;

class WwiStatsWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-stats', 'name' => 'WWI: Métricas', 'icon' => 'bar-chart', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Título (opcional)', 'type' => 'text', 'inline' => true, 'default' => ''],
            ['key' => 'items', 'label' => 'Métricas', 'type' => 'repeater', 'fields' => [
                ['key' => 'value', 'label' => 'Valor', 'type' => 'text'],
                ['key' => 'label', 'label' => 'Etiqueta', 'type' => 'text'],
            ], 'default' => [
                ['value' => '24h', 'label' => 'o antes, online'],
                ['value' => '0', 'label' => 'programadores necesarios'],
                ['value' => '100%', 'label' => 'gestionado por IA'],
            ]],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $items = $this->safeJson($c['items'] ?? []);
        $html = '<section style="padding:60px 0"><div class="wrap">';
        if (!empty($c['title'])) $html .= '<div class="h-sec" style="margin-bottom:26px"><h2 data-editable="title">' . $this->esc($c['title']) . '</h2></div>';
        $html .= '<div class="wwi-grid-3">';
        foreach ($items as $i => $st) {
            if (!is_array($st)) continue;
            $val = (string)($st['value'] ?? '');
            $attrs = '';
            if (preg_match('/^(\d+)(.*)$/u', $val, $m)) $attrs = ' data-count="' . $m[1] . '" data-suffix="' . $this->esc($m[2]) . '"';
            $html .= '<div class="panel stat"><div class="v"' . $attrs . ' data-editable="items.' . $i . '.value">' . $this->esc($val) . '</div><div class="l" data-editable="items.' . $i . '.label">' . $this->esc($st['label'] ?? '') . '</div></div>';
        }
        $html .= '</div></div></section>';
        return $html;
    }
}
