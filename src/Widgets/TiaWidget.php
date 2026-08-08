<?php
namespace App\Widgets;

class TiaWidget extends Widget
{
    public static function meta(): array { return ['id' => 'tia', 'name' => 'TIA Section', 'icon' => 'brain', 'category' => 'content', 'version' => '1.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'badge', 'label' => 'Badge', 'type' => 'text', 'default' => 'Tecnologia de Inteligencia Aplicada'],
            ['key' => 'title', 'label' => 'Title (HTML)', 'type' => 'html', 'default' => 'TIA es el <span class="gradient-text">cerebro</span> de Wontia'],
            ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => 'TIA no es un chatbot. Es un sistema de inteligencia aplicada que opera en cada capa de la plataforma.'],
            ['key' => 'items', 'label' => 'Capabilities (JSON)', 'type' => 'code', 'default' => json_encode([['title'=>'Analisis Predictivo','desc'=>'Identifica oportunidades con alta probabilidad de cierre.'],['title'=>'Ejecucion Autonoma','desc'=>'Redacta y envia correos y mensajes segun contexto.'],['title'=>'Orquestacion de Flujos','desc'=>'Activa tareas cuando se cumplen condiciones.'],['title'=>'Asistencia en Vivo','desc'=>'Responde preguntas y ejecuta comandos por voz o texto.'],['title'=>'Aprendizaje Continuo','desc'=>'Cada interaccion alimenta el modelo. TIA mejora.']])],
            ['key' => 'blob_message', 'label' => 'TIA Message', 'type' => 'textarea', 'default' => 'Analice tu pipeline. Tienes 3 oportunidades listas para cerrar esta semana.'],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:linear-gradient(180deg,#F6F6F3,#EDE9FE);border-radius:12px;padding:24px;text-align:center"><div style="font-size:18px;font-weight:700">TIA Section</div><div style="font-size:11px;color:#6B6B6B">5 capabilities + Blob</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $items = $this->safeJson($c['items']);
        $html = '<section id="tia" style="padding:100px 40px;background:linear-gradient(180deg, #F6F6F3, #F3F3EF, #F6F6F3)">
  <div style="max-width:1100px;margin:0 auto;display:flex;gap:60px;align-items:center;flex-wrap:wrap">
    <div style="flex:1;min-width:300px" class="reveal">
      <div class="badge" style="background:#DCCFFF;color:#7C3AED;margin-bottom:20px">' . $this->esc($c['badge']) . '</div>
      <h2 style="font-size:38px;font-weight:800;color:#1A1A1E;line-height:1.2;letter-spacing:-0.02em;margin-bottom:16px">' . $c['title'] . '</h2>
      <p style="font-size:15px;color:#6B6B6B;line-height:1.8">' . $this->esc($c['description']) . '</p>
      <div style="margin-top:20px">';
        $i = 1;
        foreach ($items as $item) {
            $html .= '
        <div class="list-item">
          <div class="list-num"><span>' . $i . '</span></div>
          <div><div style="font-size:14px;font-weight:600;color:#1A1A1E;margin-bottom:3px">' . $this->esc($item['title']) . '</div><div style="font-size:12px;color:#6B6B6B;line-height:1.6">' . $this->esc($item['desc']) . '</div></div>
        </div>';
            $i++;
        }
        $html .= '</div></div>
    <div style="flex:1;min-width:300px;display:flex;justify-content:center;align-items:center" class="reveal">
      <div style="width:340px;height:380px;border-radius:24px;background:linear-gradient(180deg,#FBFBF9,#F3F3EF);border:1px solid rgba(80,80,80,.06);box-shadow:0 4px 24px rgba(0,0,0,.04);display:flex;align-items:center;justify-content:center;position:relative;overflow:hidden">
        <div style="position:absolute;top:-40px;right:-40px;width:160px;height:160px;border-radius:50%;background:radial-gradient(circle,rgba(184,158,255,.18),transparent 70%)"></div>
        <div style="position:relative;z-index:1;text-align:center">
          <div style="width:100px;height:100px;margin:0 auto 16px;position:relative">
            <svg viewBox="0 0 120 120" fill="none" style="width:100%;height:100%">
              <defs>
                <filter id="tiaGoo"><feGaussianBlur in="SourceGraphic" stdDeviation="5" result="blur"/><feColorMatrix in="blur" mode="matrix" values="1 0 0 0 0 0 1 0 0 0 0 0 1 0 0 0 0 0 20 -8"/></filter>
                <radialGradient id="tiaBlobGrad"><stop offset="0%" stop-color="#D4C3FF"/><stop offset="55%" stop-color="#B89EFF"/><stop offset="100%" stop-color="#9B8CDE"/></radialGradient>
              </defs>
              <circle cx="60" cy="60" r="34" fill="#B89EFF" opacity="0.12"><animate attributeName="r" values="34;38;34" dur="3s" repeatCount="indefinite"/></circle>
              <g filter="url(#tiaGoo)">
                <circle cx="60" cy="60" r="17" fill="url(#tiaBlobGrad)"><animate attributeName="cx" values="60;63;57;60" dur="5s" repeatCount="indefinite"/></circle>
                <circle cx="60" cy="60" r="14" fill="url(#tiaBlobGrad)"><animate attributeName="cx" values="60;56;62;60" dur="6s" repeatCount="indefinite"/></circle>
                <circle cx="60" cy="60" r="12" fill="url(#tiaBlobGrad)"><animate attributeName="cx" values="60;62;59;60" dur="4.5s" repeatCount="indefinite"/></circle>
              </g>
              <text x="47" y="66" font-family="Inter" font-size="15" font-weight="700" fill="#fff" stroke="#5A4AC7" stroke-width="0.5">T</text>
              <text x="58" y="66" font-family="Inter" font-size="15" font-weight="700" fill="#fff" stroke="#5A4AC7" stroke-width="0.5">i</text>
              <text x="61" y="66" font-family="Inter" font-size="15" font-weight="700" fill="#fff" stroke="#5A4AC7" stroke-width="0.5">a</text>
            </svg>
          </div>
          <div style="font-size:20px;font-weight:700;color:#1A1A1E;margin-bottom:8px">Hola, soy TIA</div>
          <div style="font-size:12px;color:#6B6B6B;line-height:1.6;max-width:240px;margin:0 auto">"' . $this->esc($c['blob_message']) . '"</div>
        </div>
      </div>
    </div>
  </div>
</section>';
        return $html;
    }
}
