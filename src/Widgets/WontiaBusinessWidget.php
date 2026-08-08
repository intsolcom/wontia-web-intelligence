<?php
namespace App\Widgets;

class WontiaBusinessWidget extends Widget
{
    public static function meta(): array { return ['id' => 'wontia-business', 'name' => 'Wontia Business', 'icon' => 'briefcase', 'category' => 'content', 'version' => '2.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'status_label', 'label' => 'Status Badge', 'type' => 'text', 'default' => 'AVAILABLE — CURRENT'],
            ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'default' => 'Wontia Business'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'The first domain-specific application of Wontia\'s Applied Intelligence architecture. Business operations powered by TIA — not a CRM, but an operating environment where CRM is one capability among many.'],
            ['key' => 'capabilities', 'label' => 'Capabilities (JSON)', 'type' => 'code', 'default' => json_encode([['title'=>'Customer Intelligence','desc'=>'Understand leads and clients through behavioral patterns and context.'],['title'=>'Sales Intelligence','desc'=>'Pipeline visibility with AI-suggested next actions.'],['title'=>'Operations Intelligence','desc'=>'Automated workflows triggered by business events.'],['title'=>'Decision Support','desc'=>'TIA analyzes data and recommends the best course of action.'],['title'=>'AI Agents','desc'=>'Autonomous agents that execute, verify, and learn.'],['title'=>'Task Orchestration','desc'=>'Coordinate people, systems, and timelines automatically.']])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:#F6F6F3;border-radius:12px;padding:24px"><div style="font-size:10px;color:#00B87D;text-transform:uppercase;font-weight:700">AVAILABLE</div><div style="font-size:18px;font-weight:700;margin-top:4px">Wontia Business</div><div style="font-size:11px;color:#6B6B6B">6 capability cards</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $caps = $this->safeJson($c['capabilities']);
        $html = '
<section id="business" style="padding:100px 40px;max-width:1100px;margin:0 auto">
  <div style="text-align:center;margin-bottom:56px" class="reveal">
    <div class="badge" style="background:#D9F2E2;color:#4A9E6E;margin-bottom:16px">' . $this->esc($c['status_label']) . '</div>
    <h2 style="font-size:clamp(30px,5vw,44px);font-weight:800;color:#1A1A1E;letter-spacing:-0.02em;margin-bottom:16px">' . $this->esc($c['headline']) . '</h2>
    <p style="font-size:15px;color:#6B6B6B;line-height:1.8;max-width:680px;margin:0 auto">' . $this->esc($c['subtitle']) . '</p>
  </div>
  <div class="grid-3">';
        foreach ($caps as $cap) {
            $html .= '
    <div class="card reveal card-padded">
      <div style="font-size:24px;margin-bottom:12px">&#x25C6;</div>
      <div style="font-size:15px;font-weight:700;color:#1A1A1E;margin-bottom:6px">' . $this->esc($cap['title']) . '</div>
      <div style="font-size:12px;color:#6B6B6B;line-height:1.6">' . $this->esc($cap['desc']) . '</div>
    </div>';
        }
        $html .= '</div></section>';
        return $html;
    }
}
