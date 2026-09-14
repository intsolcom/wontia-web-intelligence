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
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'inline' => true, 'default' => 'Plantillas para cada sector'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'inline' => true, 'default' => 'Categorías listas. TIA adapta colores, textos y secciones a tu negocio.'],
            ['key' => 'loading_text', 'label' => 'Texto de carga', 'type' => 'text', 'inline' => true, 'default' => 'Cargando plantillas…'],
            ['key' => 'card_letter', 'label' => 'Letra de tarjeta', 'type' => 'text', 'default' => 'W'],
        ];
    }

    public static function editContract(): array
    {
        $c = parent::editContract();
        $c['sources'] = ['templates' => ['label' => 'Catálogo de plantillas', 'editable' => ['name_es', 'name_en', 'status']]];
        $c['dynamic'] = true;
        return $c;
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $html = '<section id="plantillas" style="padding:90px 0;background:var(--bg2);border-top:1px solid var(--border)">';
        $html .= '<div class="wrap"><div class="h-sec reveal"><h2 data-editable="title">' . $this->esc($c['title']) . '</h2><p data-editable="subtitle">' . $this->esc($c['subtitle']) . '</p></div>';
        $html .= '<div class="wwi-grid-3" data-wwi-templates data-letter="' . $this->esc($c['card_letter']) . '"><div data-editable="loading_text" style="text-align:center;padding:40px;color:var(--muted);grid-column:1/-1">' . $this->esc($c['loading_text']) . '</div></div>';
        $html .= '</div></section>';
        return $html;
    }
}
