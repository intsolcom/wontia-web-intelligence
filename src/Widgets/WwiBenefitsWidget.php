<?php
namespace App\Widgets;

class WwiBenefitsWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-benefits', 'name' => 'WWI: Benefits', 'icon' => 'grid', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'default' => 'Todo lo que tu negocio necesita para estar online'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'default' => 'Un solo pago. TIA se encarga del resto.'],
            ['key' => 'items', 'label' => 'Items (JSON)', 'type' => 'code', 'default' => json_encode([
                ['icon' => 'W', 'title' => 'Dominio + Hosting + SSL', 'desc' => 'Tu dominio .com, hosting rápido y certificado SSL incluidos.'],
                ['icon' => 'M', 'title' => '3 correos corporativos', 'desc' => 'tu@negocio.com listos para usar, con alias y reenvío.'],
                ['icon' => 'T', 'title' => 'TIA construye por ti', 'desc' => 'Cuéntale sobre tu negocio: ella escribe, diseña y publica.'],
                ['icon' => 'S', 'title' => 'SEO básico incluido', 'desc' => 'Meta tags, sitemap, robots.txt y Google-ready desde el día uno.'],
                ['icon' => 'R', 'title' => 'Diseño responsive', 'desc' => 'Perfecto en móvil, tablet y escritorio. Mobile-first.'],
                ['icon' => 'P', 'title' => 'Editor + TIA permanente', 'desc' => 'Pide cambios con texto o voz: "hazlo más elegante". Hecho.'],
            ])],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $items = $this->safeJson($c['items'] ?? '');
        $html = '<section id="beneficios" style="padding:90px 0;background:var(--bg2);border-top:1px solid var(--border);border-bottom:1px solid var(--border)">';
        $html .= '<div class="wrap"><div class="h-sec reveal"><h2>' . $this->esc($c['title']) . '</h2><p>' . $this->esc($c['subtitle']) . '</p></div>';
        $html .= '<div class="wwi-grid-3">';
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $html .= '<div class="card reveal"><div class="ic">' . $this->esc($item['icon'] ?? 'W') . '</div><h3>' . $this->esc($item['title'] ?? '') . '</h3><p>' . $this->esc($item['desc'] ?? '') . '</p></div>';
        }
        $html .= '</div></div></section>';
        return $html;
    }
}
