<?php
namespace App\Widgets;

class DifferentiatorWidget extends Widget
{
    public static function meta(): array { return ['id' => 'differentiator', 'name' => 'Differentiator: Answers to Action', 'icon' => 'trending-up', 'category' => 'content', 'version' => '2.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'headline', 'label' => 'Headline', 'type' => 'html', 'default' => 'From AI that <span style="color:#9A9A9A">answers</span><br>to Intelligence that <span class="gradient-text">acts</span>'],
            ['key' => 'left_title', 'label' => 'Left Column Title', 'type' => 'text', 'default' => 'Traditional AI'],
            ['key' => 'left_items', 'label' => 'Left Items (JSON)', 'type' => 'code', 'default' => json_encode(['Question → Answer','Data → Dashboard → Human Decision','Reactive responses','Single interaction','Read-only'])],
            ['key' => 'right_title', 'label' => 'Right Column Title', 'type' => 'text', 'default' => 'Wontia + TIA'],
            ['key' => 'right_items', 'label' => 'Right Items (JSON)', 'type' => 'code', 'default' => json_encode(['Context → Understand → Decide → Act','Intelligent orchestration','Proactive execution','Multi-step workflows','Tools + Systems + Verification'])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:linear-gradient(135deg,#0f1117,#1a1d27);border-radius:12px;padding:24px;text-align:center"><div style="font-size:16px;font-weight:700;color:#fff">Answers → Action</div><div style="font-size:11px;color:#8b8fa3">Side by side comparison</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $left = $this->safeJson($c['left_items']);
        $right = $this->safeJson($c['right_items']);
        $html = '
<section id="differentiator" style="padding:120px 40px;background:#1A1A1E;color:#e1e4ed">
  <div style="max-width:1100px;margin:0 auto">
    <h2 style="text-align:center;font-size:clamp(30px,5vw,48px);font-weight:800;color:#fff;line-height:1.25;letter-spacing:-0.02em;margin-bottom:64px">' . $c['headline'] . '</h2>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:40px">
      <div class="reveal">
        <div style="font-size:12px;color:#9A9A9A;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:24px;font-weight:600">' . $this->esc($c['left_title']) . '</div>
        <div style="display:flex;flex-direction:column;gap:12px">';
        foreach ($left as $item) {
            $html .= '<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:14px 18px;font-size:14px;color:#8b8fa3;display:flex;align-items:center;gap:10px"><span style="color:#BE1341">&#x2716;</span> ' . $this->esc($item) . '</div>';
        }
        $html .= '</div></div>
      <div class="reveal">
        <div style="font-size:12px;color:#B89EFF;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:24px;font-weight:600">' . $this->esc($c['right_title']) . '</div>
        <div style="display:flex;flex-direction:column;gap:12px">';
        foreach ($right as $item) {
            $html .= '<div style="background:rgba(155,140,222,0.08);border:1px solid rgba(155,140,222,0.15);border-radius:10px;padding:14px 18px;font-size:14px;color:#e1e4ed;display:flex;align-items:center;gap:10px"><span style="color:#00B87D">&#x2714;</span> ' . $this->esc($item) . '</div>';
        }
        $html .= '</div></div></div></div></section>';
        return $html;
    }
}
