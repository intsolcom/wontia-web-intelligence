<?php
namespace App\Widgets;

class WwiGalleryWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-gallery', 'name' => 'WWI: Galería', 'icon' => 'image', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'inline' => true, 'default' => 'Nuestro trabajo'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'inline' => true, 'default' => 'Una muestra de lo que hacemos.'],
            ['key' => 'columns', 'label' => 'Columnas (2-4)', 'type' => 'text', 'default' => '3'],
            ['key' => 'items', 'label' => 'Imágenes', 'type' => 'repeater', 'fields' => [
                ['key' => 'image', 'label' => 'Imagen', 'type' => 'image'],
                ['key' => 'caption', 'label' => 'Descripción', 'type' => 'text'],
            ], 'default' => [
                ['image' => '', 'caption' => 'Proyecto 1'],
                ['image' => '', 'caption' => 'Proyecto 2'],
                ['image' => '', 'caption' => 'Proyecto 3'],
            ]],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $items = $this->safeJson($c['items'] ?? []);
        $cols = max(2, min(4, (int)($c['columns'] ?: 3)));
        $html = '<section style="padding:90px 0"><div class="wrap">';
        $html .= '<div class="h-sec reveal"><h2 data-editable="title">' . $this->esc($c['title']) . '</h2><p data-editable="subtitle">' . $this->esc($c['subtitle']) . '</p></div>';
        $html .= '<div style="display:grid;grid-template-columns:repeat(' . $cols . ',1fr);gap:14px" class="wwi-gallery-grid">';
        foreach ($items as $i => $it) {
            if (!is_array($it)) continue;
            $img = trim((string)($it['image'] ?? ''));
            $cap = (string)($it['caption'] ?? '');
            $html .= '<figure class="card" style="padding:0;overflow:hidden;margin:0">';
            if ($img !== '') {
                $html .= '<a href="' . $this->esc($img) . '" target="_blank" rel="noopener"><img src="' . $this->esc($img) . '" alt="' . $this->esc($cap) . '" data-editable="items.' . $i . '.image" style="width:100%;aspect-ratio:4/3;object-fit:cover;display:block"/></a>';
            } else {
                $html .= '<div style="width:100%;aspect-ratio:4/3;background:var(--bg2);border:1px dashed var(--border)"></div>';
            }
            if ($cap !== '') $html .= '<figcaption style="padding:10px 12px;font-size:12px;color:var(--muted)" data-editable="items.' . $i . '.caption">' . $this->esc($cap) . '</figcaption>';
            $html .= '</figure>';
        }
        $html .= '</div></div></section>';
        return $html;
    }
}
