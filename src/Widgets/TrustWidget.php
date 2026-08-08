<?php
namespace App\Widgets;

class TrustWidget extends Widget
{
    public static function meta(): array { return ['id' => 'trust', 'name' => 'Trust & Control', 'icon' => 'shield', 'category' => 'content', 'version' => '2.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'default' => 'Intelligence you control. Actions you authorize.'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'Enterprise-grade governance built into every layer. TIA recommends, humans approve, the platform executes — with full transparency and auditability.'],
            ['key' => 'flow_steps', 'label' => 'Control Flow Steps (JSON)', 'type' => 'code', 'default' => json_encode([
                ['step' => 'RECOMMEND', 'desc' => 'TIA analyzes context and proposes the optimal action with supporting rationale and confidence score.'],
                ['step' => 'APPROVE', 'desc' => 'Human reviews, adjusts parameters if needed, and authorizes execution within defined boundaries.'],
                ['step' => 'EXECUTE', 'desc' => 'Wontia orchestrates the approved action across systems, verifying each step and logging everything.'],
            ])],
            ['key' => 'pillars', 'label' => 'Trust Pillars (JSON)', 'type' => 'code', 'default' => json_encode([
                ['title' => 'Permission-Based Access', 'desc' => 'Role-based permissions at every level. Define who can view, recommend, approve, and execute.', 'icon' => '&#x1F512;', 'bg' => '#EDE9FE'],
                ['title' => 'Policy Enforcement', 'desc' => 'Business rules and compliance policies automatically enforced before any action is executed.', 'icon' => '&#x1F4DC;', 'bg' => '#E8F2FE'],
                ['title' => 'Full Audit Trail', 'desc' => 'Every recommendation, approval, and execution is logged with timestamp, user, and context.', 'icon' => '&#x1F4CB;', 'bg' => '#E6F5EC'],
                ['title' => 'Transparent Reasoning', 'desc' => 'TIA explains why it recommends each action. No black boxes. Complete decision traceability.', 'icon' => '&#x1F50D;', 'bg' => '#FEF3C7'],
            ])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:#F6F6F3;border-radius:12px;padding:24px"><div style="font-size:16px;font-weight:700">Trust & Control</div><div style="font-size:11px;color:#6B6B6B">RECOMMEND → APPROVE → EXECUTE flow</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $steps = $this->safeJson($c['flow_steps']);
        $pillars = $this->safeJson($c['pillars']);
        $html = '
<section id="trust" style="padding:100px 40px;background:#FBFBF9">
  <div style="max-width:1100px;margin:0 auto">
    <div style="text-align:center;margin-bottom:56px" class="reveal">
      <div class="badge" style="background:#D9F2E2;color:#4A9E6E;margin-bottom:16px">ENTERPRISE TRUST</div>
      <h2 style="font-size:clamp(28px,5vw,42px);font-weight:800;color:#1A1A1E;letter-spacing:-0.02em;margin-bottom:16px">' . $this->esc($c['headline']) . '</h2>
      <p style="font-size:15px;color:#6B6B6B;line-height:1.8;max-width:680px;margin:0 auto">' . $this->esc($c['subtitle']) . '</p>
    </div>
    <div style="display:flex;align-items:center;justify-content:center;gap:0;margin-bottom:64px;flex-wrap:wrap" class="reveal">';

        $stepColors = ['#9B8CDE', '#F7E8C8', '#00B87D'];
        foreach ($steps as $i => $step) {
            $sc = $stepColors[$i] ?? '#9B8CDE';
            $html .= '
      <div style="text-align:center;padding:28px 24px;background:#F6F6F3;border-radius:20px;min-width:200px;flex:1;max-width:260px;position:relative">
        <div style="width:48px;height:48px;border-radius:14px;background:' . $sc . ';display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:20px;font-weight:800;color:#fff">' . ($i + 1) . '</div>
        <div style="font-size:12px;font-weight:700;color:' . $sc . ';letter-spacing:0.1em;margin-bottom:8px">' . $this->esc($step['step']) . '</div>
        <div style="font-size:12px;color:#6B6B6B;line-height:1.6">' . $this->esc($step['desc']) . '</div>
      </div>';
            if ($i < count($steps) - 1) {
                $html .= '<div style="font-size:24px;color:#DCCFFF;padding:0 8px;font-weight:300;align-self:center">&#x279E;</div>';
            }
        }

        $html .= '
    </div>
    <div class="grid-2">';

        foreach ($pillars as $p) {
            $bg = $p['bg'] ?? '#EDE9FE';
            $html .= '
      <div class="card reveal card-padded" style="display:flex;gap:16px;align-items:flex-start">
        <div style="width:44px;height:44px;border-radius:12px;background:' . $bg . ';display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0">' . $p['icon'] . '</div>
        <div>
          <div style="font-size:14px;font-weight:700;color:#1A1A1E;margin-bottom:4px">' . $this->esc($p['title']) . '</div>
          <div style="font-size:12px;color:#6B6B6B;line-height:1.6">' . $this->esc($p['desc']) . '</div>
        </div>
      </div>';
        }

        $html .= '
    </div>
  </div>
</section>';
        return $html;
    }
}
