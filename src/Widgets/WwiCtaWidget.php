<?php
namespace App\Widgets;

class WwiCtaWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-cta', 'name' => 'WWI: CTA', 'icon' => 'arrow-right', 'category' => 'footer', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'default' => 'Tu negocio merece estar en Internet'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'default' => 'Compra. Cuéntanos quién eres. TIA construye tu sitio web.'],
            ['key' => 'button', 'label' => 'Botón', 'type' => 'text', 'default' => 'Crear mi sitio ahora'],
            ['key' => 'button_url', 'label' => 'URL', 'type' => 'text', 'default' => '#planes'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $html = '<section id="contacto" style="padding:110px 0">';
        $html .= '<div class="wrap" style="text-align:center">';
        $html .= '<div class="panel reveal" style="max-width:760px;margin:0 auto;padding:56px 34px;background:linear-gradient(160deg,var(--panel) 0%,rgba(139,92,246,.08) 100%);border-color:rgba(34,211,238,.35)">';
        $html .= '<h2 style="font-size:30px;font-weight:800;letter-spacing:-.02em;margin-bottom:12px">' . $this->esc($c['title']) . '</h2>';
        $html .= '<p style="color:var(--muted);font-size:14px;max-width:480px;margin:0 auto 26px;line-height:1.7">' . $this->esc($c['subtitle']) . '</p>';
        $html .= '<a class="btn btn-primary" style="padding:13px 30px;font-size:14px" href="' . $this->esc($c['button_url']) . '">' . $this->esc($c['button']) . '</a>';
        $html .= '</div></div></section>';
        return $html;
    }
}
