<?php
namespace App\Widgets;

class WwiTrustBarWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-trust-bar', 'name' => 'WWI: Barra de confianza', 'icon' => 'check', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'items', 'label' => 'Elementos', 'type' => 'repeater', 'fields' => [
                ['key' => 'text', 'label' => 'Texto', 'type' => 'text'],
            ], 'default' => [
                ['text' => 'Vista previa gratis'],
                ['text' => 'Sin tarjeta'],
                ['text' => 'Sin programador'],
                ['text' => 'Listo en 24 horas'],
            ]],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $items = $this->safeJson($c['items'] ?? []);
        $html = '<section style="padding:26px 0"><div class="wrap" style="text-align:center"><div class="trust-row" style="justify-content:center;margin-top:0">';
        foreach ($items as $i => $it) {
            $t = is_array($it) ? (string)($it['text'] ?? '') : (string)$it;
            if (trim($t) === '') continue;
            $html .= '<span data-editable="items.' . $i . '.text">' . $this->esc($t) . '</span>';
        }
        $html .= '</div></div></section>';
        return $html;
    }
}
