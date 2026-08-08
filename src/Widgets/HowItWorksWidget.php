<?php
namespace App\Widgets;

class HowItWorksWidget extends Widget
{
    public static function meta(): array { return ['id' => 'howitworks', 'name' => 'How It Works', 'icon' => 'steps', 'category' => 'content', 'version' => '1.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Section Title', 'type' => 'text', 'default' => 'Como funciona'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => 'Tres pasos. Una plataforma. Cero friccion.'],
            ['key' => 'steps', 'label' => 'Steps (JSON)', 'type' => 'code', 'default' => json_encode([['title'=>'Conecta tus canales','desc'=>'WhatsApp, email, Facebook, Instagram. Conectalos en minutos.','image'=>'canales-wontia.jpg'],['title'=>'Define tu pipeline','desc'=>'Crea etapas, reglas y flujos. TIA te guia con plantillas inteligentes.','image'=>''],['title'=>'Deja que TIA opere','desc'=>'TIA analiza, ejecuta, sugiere y aprende. Tu equipo supervisa.','image'=>'']])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:#F3F3EF;border-radius:12px;padding:24px;text-align:center"><div style="font-size:18px;font-weight:700">How It Works</div><div style="font-size:11px;color:#6B6B6B">3 steps layout</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $steps = $this->safeJson($c['steps']);
        $html = '<section id="funciona" style="padding:80px 40px;background:#F3F3EF">
  <div style="max-width:1100px;margin:0 auto">
    <div style="text-align:center;margin-bottom:60px" class="reveal">
      <h2 style="font-size:34px;font-weight:800;color:#1A1A1E;letter-spacing:-0.02em;margin-bottom:12px">' . $this->esc($c['title']) . '</h2>
      <p style="font-size:15px;color:#6B6B6B">' . $this->esc($c['subtitle']) . '</p>
    </div>
    <div class="grid-3">';
        $svgIdx = 0;
        foreach ($steps as $step) {
            if ($step['image']) {
                $imgHtml = '<img src="' . $this->esc($step['image']) . '" alt="' . $this->esc($step['title']) . '" style="max-width:100%;max-height:140px;border-radius:12px;object-fit:contain">';
            } else {
                $colors = [['#DCCFFF','#EBE9F5'],['#CFE6FF','#EBE9F5'],['#D9F2E2','#EBE9F5']];
                $c1 = $colors[$svgIdx][0] ?? '#DCCFFF';
                $c2 = $colors[$svgIdx][1] ?? '#EBE9F5';
                $imgHtml = '<svg viewBox="0 0 280 140" fill="none"><rect width="280" height="140" fill="' . $c2 . '"/><circle cx="140" cy="55" r="35" fill="none" stroke="' . $c1 . '" stroke-width="1.5"/><circle cx="140" cy="55" r="10" fill="' . $c1 . '" opacity="0.5"/></svg>';
            }
            $html .= '
      <div style="text-align:center;padding:40px 24px" class="reveal">
        <div class="img-swap" style="height:140px;margin-bottom:20px">
          <div class="img-a img-fallback" style="display:flex;align-items:center;justify-content:center;width:100%;height:100%">' . $imgHtml . '</div>
          <div class="img-b img-fallback" style="display:flex;align-items:center;justify-content:center;width:100%;height:100%">' . $imgHtml . '</div>
        </div>
        <div style="font-size:17px;font-weight:700;color:#1A1A1E;margin-bottom:10px">' . $this->esc($step['title']) . '</div>
        <div style="font-size:13px;color:#6B6B6B;line-height:1.7">' . $this->esc($step['desc']) . '</div>
      </div>';
            $svgIdx++;
        }
        $html .= '</div></div></section>';
        return $html;
    }
}
