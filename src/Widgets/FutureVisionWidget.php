<?php
namespace App\Widgets;

class FutureVisionWidget extends Widget
{
    public static function meta(): array { return ['id' => 'future-vision', 'name' => 'Future Vision', 'icon' => 'compass', 'category' => 'content', 'version' => '2.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'default' => 'The intelligence layer remains consistent. The domain context changes.'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'WONTIA and TIA form a universal intelligence core. Around it, an expanding ecosystem of domain-specific applications — each with its own tools, data, workflows, and actions. Same intelligence. Different outcomes.'],
            ['key' => 'domains', 'label' => 'Future Domains (JSON)', 'type' => 'code', 'default' => json_encode([
                ['name' => 'Wontia Business', 'desc' => 'Business Intelligence & Operations', 'status' => 'available', 'icon' => '&#x1F4BC;'],
                ['name' => 'Wontia Web', 'desc' => 'Intelligent Web Operations', 'status' => 'available', 'icon' => '&#x1F310;'],
                ['name' => 'Wontia Food Security', 'desc' => 'Food Security Intelligence & Response', 'status' => 'development', 'icon' => '&#x1F4E6;'],
                ['name' => 'Wontia Health', 'desc' => 'Health Intelligence Engine', 'status' => 'future', 'icon' => '&#x2764;'],
                ['name' => 'Wontia Agriculture', 'desc' => 'Agriculture Intelligence Engine', 'status' => 'future', 'icon' => '&#x2600;'],
                ['name' => 'Wontia Industry', 'desc' => 'Industrial Intelligence Engine', 'status' => 'future', 'icon' => '&#x2699;'],
                ['name' => 'Wontia Logistics', 'desc' => 'Supply Chain Intelligence', 'status' => 'future', 'icon' => '&#x1F69A;'],
                ['name' => 'Wontia Education', 'desc' => 'Learning Intelligence Engine', 'status' => 'future', 'icon' => '&#x1F4DA;'],
                ['name' => 'More Domains', 'desc' => 'Architecture designed for continuous expansion', 'status' => 'future', 'icon' => '&#x2795;'],
            ])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:linear-gradient(135deg,#0f1117,#1a1d27);border-radius:12px;padding:24px;text-align:center"><div style="font-size:16px;font-weight:700;color:#B89EFF">Future Vision</div><div style="font-size:11px;color:#8b8fa3;margin-top:4px">Domain ecosystem tree</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $domains = $this->safeJson($c['domains']);
        $statusColors = [
            'available' => ['bg' => '#D9F2E2', 'text' => '#4A9E6E', 'label' => 'AVAILABLE'],
            'development' => ['bg' => '#F7E8C8', 'text' => '#D4A54A', 'label' => 'IN DEVELOPMENT'],
            'future' => ['bg' => '#EDE9FE', 'text' => '#7C3AED', 'label' => 'FUTURE'],
        ];
        $html = '
<section id="future" style="padding:100px 40px;background:#1A1A1E;color:#e1e4ed;position:relative;overflow:hidden">
  <div style="position:absolute;top:0;left:0;right:0;bottom:0;pointer-events:none;z-index:0;opacity:0.06">
    <svg width="100%" height="100%"><defs><pattern id="grid" width="60" height="60" patternUnits="userSpaceOnUse"><circle cx="30" cy="30" r="1.5" fill="#B89EFF"/></pattern></defs><rect width="100%" height="100%" fill="url(#grid)"/></svg>
  </div>
  <div style="max-width:1100px;margin:0 auto;position:relative;z-index:1">
    <div style="text-align:center;margin-bottom:48px" class="reveal">
      <div class="badge" style="background:rgba(155,140,222,0.15);color:#B89EFF;margin-bottom:16px">ONE PLATFORM · ONE INTELLIGENCE · MULTIPLE DOMAINS</div>
      <h2 style="font-size:clamp(28px,5vw,42px);font-weight:800;color:#fff;letter-spacing:-0.02em;margin-bottom:16px">' . $this->esc($c['headline']) . '</h2>
      <p style="font-size:15px;color:#8b8fa3;line-height:1.8;max-width:680px;margin:0 auto">' . $this->esc($c['subtitle']) . '</p>
    </div>
    <div style="display:flex;align-items:center;justify-content:center;margin-bottom:48px" class="reveal">
      <div style="text-align:center">
        <div style="display:inline-block;padding:18px 36px;background:linear-gradient(135deg,#9B8CDE,#B89EFF);border-radius:20px;color:#fff;font-weight:800;font-size:22px;letter-spacing:-0.02em;margin-bottom:12px;box-shadow:0 12px 48px rgba(155,140,222,.3)">WONTIA</div>
        <div style="font-size:14px;color:#B89EFF;font-weight:700;margin:8px 0">&#x2B07;</div>
        <div style="display:inline-block;padding:14px 28px;background:rgba(155,140,222,0.12);border:1px solid rgba(155,140,222,0.2);border-radius:16px;color:#B89EFF;font-weight:700;font-size:17px;margin-bottom:12px">TIA Core</div>
        <div style="font-size:14px;color:#B89EFF;font-weight:700;margin:8px 0">&#x2B07;</div>
        <div style="display:inline-flex;align-items:center;gap:6px;padding:10px 20px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);border-radius:12px;color:#8b8fa3;font-size:12px;font-weight:600">
          <span style="color:#B89EFF">&#x25C6;</span> DOMAIN INTELLIGENCE LAYER
        </div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px">';

        foreach ($domains as $dom) {
            $sc = $statusColors[$dom['status']] ?? $statusColors['future'];
            $html .= '
      <div class="reveal" style="background:rgba(255,255,255,0.03);border:1px solid rgba(255,255,255,0.06);border-radius:16px;padding:24px;text-align:center;transition:border-color .3s">
        <div style="font-size:28px;margin-bottom:12px">' . $dom['icon'] . '</div>
        <div style="display:inline-block;padding:3px 12px;border-radius:20px;font-size:9px;font-weight:700;background:' . $sc['bg'] . ';color:' . $sc['text'] . ';margin-bottom:10px;letter-spacing:0.05em">' . $sc['label'] . '</div>
        <div style="font-size:15px;font-weight:700;color:#fff;margin-bottom:4px">' . $this->esc($dom['name']) . '</div>
        <div style="font-size:11px;color:#8b8fa3;line-height:1.5">' . $this->esc($dom['desc']) . '</div>
      </div>';
        }

        $html .= '
    </div>
  </div>
</section>';
        return $html;
    }
}
