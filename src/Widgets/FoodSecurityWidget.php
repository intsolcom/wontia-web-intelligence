<?php
namespace App\Widgets;

class FoodSecurityWidget extends Widget
{
    public static function meta(): array { return ['id' => 'food-security', 'name' => 'Food Security', 'icon' => 'package', 'category' => 'content', 'version' => '2.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'status_label', 'label' => 'Status', 'type' => 'text', 'default' => 'NEXT VERTICAL — IN DEVELOPMENT'],
            ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'default' => 'Wontia Food Security'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'Applied Intelligence for Food Security — proving that the same architecture can extend beyond business into domains that impact lives.'],
            ['key' => 'engine_name', 'label' => 'Engine Name', 'type' => 'text', 'default' => 'Food Security Response Engine'],
            ['key' => 'insight_text', 'label' => 'TIA Insight', 'type' => 'textarea', 'default' => '2.8 tons of food are at high risk of being lost within 36 hours. Redistribution plan ready for review.'],
            ['key' => 'metrics', 'label' => 'Metrics (JSON)', 'type' => 'code', 'default' => json_encode([['label'=>'Food Available','value'=>'12.4T'],['label'=>'At Risk','value'=>'2.8T'],['label'=>'Communities','value'=>'14'],['label'=>'Meals Enabled','value'=>'1,900']])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:linear-gradient(135deg,#1a1d27,#0f1117);border-radius:12px;padding:24px"><div style="font-size:10px;color:#D4A54A;text-transform:uppercase;font-weight:700">IN DEVELOPMENT</div><div style="font-size:18px;font-weight:700;color:#fff;margin-top:4px">Food Security</div><div style="font-size:11px;color:#8b8fa3">Response Engine mockup</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $metrics = $this->safeJson($c['metrics']);
        $html = '
<section id="food-security" style="padding:100px 40px;background:#1A1A1E;color:#e1e4ed">
  <div style="max-width:1100px;margin:0 auto">
    <div style="text-align:center;margin-bottom:48px" class="reveal">
      <div class="badge" style="background:rgba(212,165,74,0.15);color:#D4A54A;margin-bottom:16px">' . $this->esc($c['status_label']) . '</div>
      <h2 style="font-size:clamp(28px,5vw,42px);font-weight:800;color:#fff;letter-spacing:-0.02em;margin-bottom:16px">' . $this->esc($c['headline']) . '</h2>
      <p style="font-size:15px;color:#8b8fa3;line-height:1.8;max-width:680px;margin:0 auto">' . $this->esc($c['subtitle']) . '</p>
    </div>
    <div style="display:flex;gap:40px;flex-wrap:wrap;margin-bottom:40px">
      <div style="flex:1;min-width:280px">
        <div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.08);border-radius:16px;padding:28px">
          <div style="font-size:10px;color:#B89EFF;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:16px;font-weight:700">' . $this->esc($c['engine_name']) . '</div>
          <div style="display:flex;flex-direction:column;gap:8px;margin-bottom:20px">';
        foreach (['DETECT','UNDERSTAND','PRIORITIZE','DECIDE','COORDINATE','ACT','MEASURE IMPACT'] as $i => $step) {
            $color = ['#DCCFFF','#CFE6FF','#D9F2E2','#F7E8C8','#EDE9FE','#B89EFF','#00B87D'][$i];
            $html .= '<div style="display:flex;align-items:center;gap:10px;padding:10px 14px;background:rgba(255,255,255,0.03);border-radius:8px"><div style="width:24px;height:24px;border-radius:6px;background:' . $color . ';display:flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#1A1A1E">' . ($i + 1) . '</div><span style="font-size:12px;font-weight:500">' . $step . '</span></div>';
        }
        $html .= '</div></div></div>
      <div style="flex:1;min-width:280px" class="reveal">
        <div style="background:rgba(155,140,222,0.06);border:1px solid rgba(155,140,222,0.15);border-radius:16px;padding:24px;margin-bottom:16px">
          <div style="font-size:10px;color:#B89EFF;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:8px;font-weight:700">TIA Insight <span style="color:#D4A54A;margin-left:8px">CONCEPT</span></div>
          <p style="font-size:13px;color:#e1e4ed;line-height:1.7">' . $this->esc($c['insight_text']) . '</p>
          <div style="display:flex;gap:8px;margin-top:16px">
            <button style="padding:8px 16px;border-radius:8px;border:1px solid rgba(155,140,222,0.3);background:transparent;color:#B89EFF;font-size:11px;font-weight:600;cursor:pointer">REVIEW PLAN</button>
            <button style="padding:8px 16px;border-radius:8px;border:none;background:linear-gradient(135deg,#9B8CDE,#B89EFF);color:#fff;font-size:11px;font-weight:600;cursor:pointer">EXECUTE</button>
          </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px">';
        foreach ($metrics as $m) {
            $html .= '<div style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:10px;padding:14px;text-align:center"><div style="font-size:20px;font-weight:800;color:#B89EFF;margin-bottom:4px">' . $this->esc($m['value']) . '</div><div style="font-size:10px;color:#8b8fa3;text-transform:uppercase;letter-spacing:0.05em">' . $this->esc($m['label']) . '</div></div>';
        }
        $html .= '</div></div></div></div></section>';
        return $html;
    }
}
