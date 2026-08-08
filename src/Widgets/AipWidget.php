<?php
namespace App\Widgets;

class AipWidget extends Widget
{
    public static function meta(): array { return ['id' => 'aip', 'name' => 'AIP Cards', 'icon' => 'layers', 'category' => 'content', 'version' => '1.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'badge', 'label' => 'Badge', 'type' => 'text', 'default' => 'Applied Intelligence Platform'],
            ['key' => 'title', 'label' => 'Title (HTML)', 'type' => 'html', 'default' => 'El concepto <span class="gradient-text">AIP</span>'],
            ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => 'AIP es la filosofia que define como Wontia integra la inteligencia artificial. La IA no es una funcionalidad: es el sistema operativo.'],
            ['key' => 'cards', 'label' => 'Cards (JSON)', 'type' => 'code', 'default' => json_encode([['title'=>'Inteligencia Aplicada','desc'=>'La IA no reemplaza a tu equipo. Lo potencia. TIA decide basandose en datos reales.'],['title'=>'Orquestacion','desc'=>'Wontia orquesta procesos completos. TIA evalua, elige el canal, redacta, agenda.'],['title'=>'Defensa en Profundidad','desc'=>'Capas de validacion: reglas de negocio, permisos, historial. Nada sin filtros.'],['title'=>'Metricas que Importan','desc'=>'Velocidad, tasa de respuesta, tiempo de cierre. Resultados de negocio.']])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:#F3F3EF;border-radius:12px;padding:24px;text-align:center"><div style="font-size:18px;font-weight:700">AIP Cards</div><div style="font-size:11px;color:#6B6B6B">2x2 philosophy grid</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $cards = $this->safeJson($c['cards']);
        $html = '<section id="aip" class="section">
  <div style="text-align:center;margin-bottom:60px" class="reveal">
    <div class="badge" style="background:#CFE6FF;color:#3B82F6;margin-bottom:20px">' . $this->esc($c['badge']) . '</div>
    <h2 style="font-size:38px;font-weight:800;color:#1A1A1E;line-height:1.2;letter-spacing:-0.02em;margin-bottom:16px">' . $c['title'] . '</h2>
    <p style="font-size:16px;color:#6B6B6B;line-height:1.8;max-width:680px;margin:0 auto">' . $this->esc($c['description']) . '</p>
  </div>
  <div class="grid-2">';
        foreach ($cards as $card) {
            $html .= '
    <div class="card reveal card-padded"><div style="font-size:15px;font-weight:700;color:#1A1A1E;margin-bottom:6px">' . $this->esc($card['title']) . '</div><div style="font-size:12px;color:#6B6B6B;line-height:1.6">' . $this->esc($card['desc']) . '</div></div>';
        }
        $html .= '</div></section>';
        return $html;
    }
}
