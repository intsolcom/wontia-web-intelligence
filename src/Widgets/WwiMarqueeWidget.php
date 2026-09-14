<?php
namespace App\Widgets;

class WwiMarqueeWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-marquee', 'name' => 'WWI: Cinta animada', 'icon' => 'activity', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'items', 'label' => 'Elementos', 'type' => 'repeater', 'fields' => [
                ['key' => 'text', 'label' => 'Texto', 'type' => 'text'],
            ], 'default' => [
                ['text' => 'Restaurantes'], ['text' => 'Abogados'], ['text' => 'Medicos'], ['text' => 'Inmobiliarias'],
                ['text' => 'Hoteles'], ['text' => 'Gimnasios'], ['text' => 'Consultores'], ['text' => 'Tiendas'],
                ['text' => 'Cafeterias'], ['text' => 'Veterinarias'], ['text' => 'Arquitectos'], ['text' => 'Transporte'],
            ]],
            ['key' => 'icon', 'label' => 'Ícono', 'type' => 'text', 'inline' => true, 'default' => '◆'],
            ['key' => 'speed', 'label' => 'Velocidad (segundos)', 'type' => 'text', 'default' => '30'],
            ['key' => 'title', 'label' => 'Título (opcional)', 'type' => 'text', 'inline' => true, 'default' => ''],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $items = $this->safeJson($c['items'] ?? []);
        $texts = [];
        foreach ($items as $i => $it) {
            $t = is_array($it) ? (string)($it['text'] ?? '') : (string)$it;
            if (trim($t) !== '') $texts[] = ['i' => $i, 'text' => $t];
        }
        if (!$texts) return '';
        $speed = max(5, (int)($c['speed'] ?: 30));
        $icon = (string)($c['icon'] ?? '◆');
        $html = '<section style="padding:34px 0"><div class="wrap">';
        if (!empty($c['title'])) $html .= '<div class="h-sec" style="margin-bottom:18px"><h2 data-editable="title" style="font-size:20px">' . $this->esc($c['title']) . '</h2></div>';
        $html .= '<div class="marquee" style="border-radius:12px;margin:0"><div class="track" style="animation-duration:' . $speed . 's">';
        foreach ($texts as $t) $html .= '<span data-editable="items.' . $t['i'] . '.text"><b data-editable="icon">' . $this->esc($icon) . '</b> ' . $this->esc($t['text']) . '</span>';
        foreach ($texts as $t) $html .= '<span aria-hidden="true"><b>' . $this->esc($icon) . '</b> ' . $this->esc($t['text']) . '</span>';
        $html .= '</div></div></div></section>';
        return $html;
    }
}
