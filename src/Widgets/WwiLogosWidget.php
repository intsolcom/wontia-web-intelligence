<?php
namespace App\Widgets;

class WwiLogosWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-logos', 'name' => 'WWI: Logos / Clientes', 'icon' => 'award', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Título (opcional)', 'type' => 'text', 'inline' => true, 'default' => 'Negocios que confían en Wontia'],
            ['key' => 'items', 'label' => 'Logos', 'type' => 'repeater', 'fields' => [
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text'],
                ['key' => 'image', 'label' => 'Imagen (opcional)', 'type' => 'image'],
            ], 'default' => [
                ['name' => 'La Espiga', 'image' => ''],
                ['name' => 'Ruiz & Asociados', 'image' => ''],
                ['name' => 'Clínica Vida', 'image' => ''],
                ['name' => 'Hotel Mirador', 'image' => ''],
                ['name' => 'Transportes Águila', 'image' => ''],
            ]],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $items = $this->safeJson($c['items'] ?? []);
        $html = '<section style="padding:56px 0"><div class="wrap">';
        if (!empty($c['title'])) $html .= '<div class="h-sec" style="margin-bottom:22px"><h2 data-editable="title" style="font-size:18px;color:var(--muted);font-weight:600">' . $this->esc($c['title']) . '</h2></div>';
        $html .= '<div style="display:flex;flex-wrap:wrap;gap:14px;justify-content:center;align-items:center">';
        foreach ($items as $i => $it) {
            if (!is_array($it)) continue;
            $img = trim((string)($it['image'] ?? ''));
            $html .= '<div class="panel" style="padding:14px 22px;display:flex;align-items:center;justify-content:center;min-width:150px">';
            if ($img !== '') {
                $html .= '<img src="' . $this->esc($img) . '" alt="' . $this->esc((string)($it['name'] ?? '')) . '" data-editable="items.' . $i . '.image" style="max-height:34px;max-width:150px;object-fit:contain"/>';
            } else {
                $html .= '<span style="font-weight:700;letter-spacing:.04em;color:var(--muted)" data-editable="items.' . $i . '.name">' . $this->esc((string)($it['name'] ?? '')) . '</span>';
            }
            $html .= '</div>';
        }
        $html .= '</div></div></section>';
        return $html;
    }
}
