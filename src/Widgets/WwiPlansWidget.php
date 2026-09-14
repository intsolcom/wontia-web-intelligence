<?php
namespace App\Widgets;

class WwiPlansWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-plans', 'name' => 'WWI: Plans', 'icon' => 'dollar', 'category' => 'commerce', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'badge', 'label' => 'Badge', 'type' => 'text', 'inline' => true, 'default' => 'Planes'],
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'inline' => true, 'default' => 'Planes simples. Todo incluido.'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'inline' => true, 'default' => 'Los precios vienen del motor de pricing de WWI — cámbialos en el panel sin tocar código.'],
            ['key' => 'note', 'label' => 'Nota inferior', 'type' => 'text', 'inline' => true, 'default' => 'Dominio incluido el primer año. Renovación según tarifa vigente. Precios en COP.'],
            ['key' => 'loading_text', 'label' => 'Texto de carga', 'type' => 'text', 'inline' => true, 'default' => 'Cargando planes…'],
            ['key' => 'currency_cop_label', 'label' => 'Etiqueta COP', 'type' => 'text', 'default' => 'COP'],
            ['key' => 'currency_usd_label', 'label' => 'Etiqueta USD', 'type' => 'text', 'default' => 'USD'],
            ['key' => 'one_time_note', 'label' => 'Nota pago único', 'type' => 'text', 'default' => 'pago único · dominio incluido 1er año'],
            ['key' => 'monthly_note', 'label' => 'Nota suscripción', 'type' => 'text', 'default' => 'suscripción mensual'],
            ['key' => 'button_prefix', 'label' => 'Prefijo del botón', 'type' => 'text', 'default' => 'Elegir '],
        ];
    }

    public static function editContract(): array
    {
        $c = parent::editContract();
        $c['sources'] = ['plans' => ['label' => 'Motor de precios', 'editable' => ['name_es', 'name_en', 'price_cop', 'price_usd', 'features', 'is_active']]];
        $c['dynamic'] = true;
        return $c;
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $html = '<section id="planes" style="padding:90px 0">';
        $html .= '<div class="wrap"><div class="h-sec reveal">';
        if ($c['badge']) $html .= '<div class="badge" data-editable="badge" style="margin-bottom:14px">' . $this->esc($c['badge']) . '</div>';
        $html .= '<h2 data-editable="title">' . $this->esc($c['title']) . '</h2><p data-editable="subtitle">' . $this->esc($c['subtitle']) . '</p></div>';
        $html .= '<div class="wwi-grid-3" data-wwi-plans'
            . ' data-cop="' . $this->esc($c['currency_cop_label']) . '"'
            . ' data-usd="' . $this->esc($c['currency_usd_label']) . '"'
            . ' data-one="' . $this->esc($c['one_time_note']) . '"'
            . ' data-monthly="' . $this->esc($c['monthly_note']) . '"'
            . ' data-prefix="' . $this->esc($c['button_prefix']) . '"'
            . '><div data-editable="loading_text" style="text-align:center;padding:40px;color:var(--muted);grid-column:1/-1">' . $this->esc($c['loading_text']) . '</div></div>';
        if ($c['note']) $html .= '<div class="mono" data-editable="note" style="text-align:center;font-size:11px;color:var(--muted);margin-top:22px">' . $this->esc($c['note']) . '</div>';
        $html .= '</div></section>';
        return $html;
    }
}
