<?php
namespace App\Widgets;

class PlatformArchWidget extends Widget
{
    public static function meta(): array { return ['id' => 'platform-arch', 'name' => 'Platform Architecture', 'icon' => 'layers', 'category' => 'content', 'version' => '2.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'default' => 'One Platform. One Intelligence Layer.'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'Wontia is built on a unified architecture that connects data, intelligence, decisions, and actions across any domain.'],
            ['key' => 'layers', 'label' => 'Architecture Layers (JSON)', 'type' => 'code', 'default' => json_encode([
                ['label' => 'WONTIA CORE', 'desc' => 'Platform foundation: authentication, permissions, tenants, integrations, APIs', 'color' => '#9B8CDE'],
                ['label' => 'TIA CORE', 'desc' => 'Intelligence engine: understanding, reasoning, learning, context awareness', 'color' => '#B89EFF'],
                ['label' => 'DOMAIN INTELLIGENCE', 'desc' => 'Domain-specific models, ontologies, rules, and knowledge bases', 'color' => '#CFE6FF'],
                ['label' => 'TOOLS · DATA · SYSTEMS', 'desc' => 'Connectors, APIs, databases, third-party services, ERP, CRM', 'color' => '#D9F2E2'],
                ['label' => 'DECISION ENGINE', 'desc' => 'Analysis, prioritization, risk assessment, recommendation logic', 'color' => '#F7E8C8'],
                ['label' => 'ACTION ORCHESTRATION', 'desc' => 'Workflow execution, multi-step automation, human-in-the-loop verification', 'color' => '#DCCFFF'],
                ['label' => 'MEASURABLE OUTCOMES', 'desc' => 'KPIs, impact metrics, audit logs, continuous improvement feedback loop', 'color' => '#00B87D'],
            ])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:#F6F6F3;border-radius:12px;padding:24px"><div style="font-size:16px;font-weight:700">Platform Architecture</div><div style="font-size:11px;color:#6B6B6B">7-layer architecture diagram</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $layers = $this->safeJson($c['layers']);
        $html = '
<section id="platform-arch" style="padding:100px 40px;background:linear-gradient(180deg,#F6F6F3,#F3F3EF)">
  <div style="max-width:1100px;margin:0 auto">
    <div style="text-align:center;margin-bottom:56px" class="reveal">
      <h2 style="font-size:clamp(28px,5vw,42px);font-weight:800;color:#1A1A1E;letter-spacing:-0.02em;margin-bottom:16px">' . $this->esc($c['headline']) . '</h2>
      <p style="font-size:15px;color:#6B6B6B;line-height:1.8;max-width:640px;margin:0 auto">' . $this->esc($c['subtitle']) . '</p>
    </div>
    <div class="reveal" style="display:flex;flex-direction:column;gap:0;max-width:720px;margin:0 auto">';

        foreach ($layers as $i => $layer) {
            $color = $layer['color'] ?? '#9B8CDE';
            $isLast = $i === count($layers) - 1;
            $html .= '
      <div style="display:flex;align-items:stretch;gap:0">
        <div style="display:flex;flex-direction:column;align-items:center;min-width:48px">
          <div style="width:14px;height:14px;border-radius:50%;background:' . $color . ';border:2px solid #F6F6F3;position:relative;z-index:1;flex-shrink:0"></div>';
            if (!$isLast) {
                $html .= '<div style="width:2px;flex:1;background:' . $color . ';opacity:0.3;min-height:20px"></div>';
            }
            $html .= '
        </div>
        <div class="card" style="flex:1;margin-bottom:' . ($isLast ? '0' : '12px') . ';padding:18px 22px;border-left:3px solid ' . $color . '">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px">
            <span style="font-size:10px;font-weight:700;color:' . $color . ';background:' . $color . '1A;padding:2px 8px;border-radius:4px;letter-spacing:0.05em">0' . ($i + 1) . '</span>
            <span style="font-size:14px;font-weight:700;color:#1A1A1E">' . $this->esc($layer['label']) . '</span>
          </div>
          <div style="font-size:12px;color:#6B6B6B;line-height:1.6">' . $this->esc($layer['desc']) . '</div>
        </div>
      </div>';
        }

        $html .= '
    </div>
    <div style="text-align:center;margin-top:48px" class="reveal">
      <div style="display:inline-flex;align-items:center;gap:8px;background:#EDE9FE;padding:10px 20px;border-radius:24px;font-size:12px;color:#7C3AED;font-weight:600">
        <span style="font-size:16px">&#x21BB;</span> Continuous feedback loop — every outcome improves the system
      </div>
    </div>
  </div>
</section>';
        return $html;
    }
}
