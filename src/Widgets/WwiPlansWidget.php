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
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'default' => 'Planes simples. Todo incluido.'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'default' => 'Los precios vienen del motor de pricing de WWI — cámbialos en el panel sin tocar código.'],
            ['key' => 'note', 'label' => 'Nota inferior', 'type' => 'text', 'default' => 'Dominio incluido el primer año. Renovación según tarifa vigente. Precios en COP.'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $html = '<section id="planes" style="padding:90px 0">';
        $html .= '<div class="wrap"><div class="h-sec reveal">';
        $html .= '<div class="badge" style="margin-bottom:14px">Planes</div>';
        $html .= '<h2>' . $this->esc($c['title']) . '</h2><p>' . $this->esc($c['subtitle']) . '</p></div>';
        $html .= '<div class="wwi-grid-3" data-wwi-plans><div style="text-align:center;padding:40px;color:var(--muted);grid-column:1/-1">Cargando planes…</div></div>';
        if ($c['note']) $html .= '<div class="mono" style="text-align:center;font-size:11px;color:var(--muted);margin-top:22px">' . $this->esc($c['note']) . '</div>';
        $html .= '</div></section>';
        return $html;
    }
}
