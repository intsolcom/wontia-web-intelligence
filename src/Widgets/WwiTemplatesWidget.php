<?php
namespace App\Widgets;

class WwiTemplatesWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-templates', 'name' => 'WWI: Templates', 'icon' => 'layout', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'default' => 'Plantillas para cada sector'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'default' => 'Categorías listas. TIA adapta colores, textos y secciones a tu negocio.'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $html = '<section id="plantillas" style="padding:90px 0;background:var(--bg2);border-top:1px solid var(--border)">';
        $html .= '<div class="wrap"><div class="h-sec reveal"><h2>' . $this->esc($c['title']) . '</h2><p>' . $this->esc($c['subtitle']) . '</p></div>';
        $html .= '<div class="wwi-grid-3" data-wwi-templates><div style="text-align:center;padding:40px;color:var(--muted);grid-column:1/-1">Cargando plantillas…</div></div>';
        $html .= '</div></section>';
        return $html;
    }
}
