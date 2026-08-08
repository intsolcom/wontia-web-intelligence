<?php
namespace App\Widgets;

class FeaturesWidget extends Widget
{
    public static function meta(): array { return ['id' => 'features', 'name' => 'Features Grid', 'icon' => 'grid', 'category' => 'content', 'version' => '1.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Section Title', 'type' => 'text', 'default' => ''],
            ['key' => 'cards', 'label' => 'Feature Cards (JSON)', 'type' => 'code', 'default' => json_encode([
                ['title' => 'Pipeline Inteligente', 'desc' => 'Visualiza cada oportunidad. TIA sugiere el siguiente paso.', 'svg_a' => 'pipeline', 'svg_b' => 'pipeline_hover'],
                ['title' => 'Automatizacion', 'desc' => 'Flujos vinculados a cada etapa. Correos, WhatsApp, tareas.', 'svg_a' => 'automation', 'svg_b' => 'automation_hover'],
                ['title' => 'Multi-Canal', 'desc' => 'WhatsApp, email, redes. Una sola bandeja unificada.', 'svg_a' => 'multichannel', 'svg_b' => 'multichannel_hover'],
                ['title' => 'IA que Ejecuta', 'desc' => 'TIA redacta, envia, programa y analiza.', 'svg_a' => 'ai_exec', 'svg_b' => 'ai_exec_hover'],
                ['title' => 'KPIs en Tiempo Real', 'desc' => 'Metricas vivas de conversion e ingresos.', 'svg_a' => 'kpi', 'svg_b' => 'kpi_hover'],
                ['title' => 'Roles y Permisos', 'desc' => 'Cada rol ve lo que necesita. Sin friccion.', 'svg_a' => 'roles', 'svg_b' => 'roles_hover'],
            ])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:#F3F3EF;border-radius:12px;padding:24px;text-align:center"><div style="font-size:18px;font-weight:700">Features Grid</div><div style="font-size:11px;color:#6B6B6B;margin-top:4px">6 cards in 3-column grid</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $cards = $this->safeJson($c['cards']);
        $html = '<section style="padding:40px 40px 80px;max-width:1100px;margin:0 auto">';
        if ($c['title']) $html .= '<h2 style="text-align:center;font-size:34px;font-weight:800;margin-bottom:40px;color:#1A1A1E">' . $this->esc($c['title']) . '</h2>';
        $html .= '<div class="grid-3">';
        foreach ($cards as $card) {
            $html .= '
    <div class="card reveal">
      <div class="img-swap">
        <svg class="img-a img-fallback" viewBox="0 0 400 180" fill="none"><rect width="400" height="180" fill="#EBE9F5"/><rect x="40" y="40" width="320" height="100" rx="12" fill="#fff" stroke="#DCCFFF" stroke-width="1"/><circle cx="200" cy="90" r="25" fill="#EDE9FE"/><text x="200" y="96" text-anchor="middle" fill="#7C3AED" font-size="16" font-weight="700">' . $this->esc($card['title'][0] ?? '?') . '</text></svg>
        <svg class="img-b img-fallback" viewBox="0 0 400 180" fill="none"><rect width="400" height="180" fill="#F3F3EF"/><circle cx="200" cy="88" r="40" fill="none" stroke="#DCCFFF" stroke-width="1"/><circle cx="200" cy="88" r="20" fill="#B89EFF" opacity="0.3"/></svg>
      </div>
      <div style="padding:0 28px 32px"><div style="font-size:15px;font-weight:700;color:#1A1A1E;margin-bottom:6px">' . $this->esc($card['title']) . '</div><div style="font-size:12px;color:#6B6B6B;line-height:1.6">' . $this->esc($card['desc']) . '</div></div>
    </div>';
        }
        $html .= '</div></section>';
        return $html;
    }
}
