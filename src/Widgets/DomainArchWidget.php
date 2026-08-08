<?php
namespace App\Widgets;

class DomainArchWidget extends Widget
{
    public static function meta(): array { return ['id' => 'domain-arch', 'name' => 'Domain Architecture', 'icon' => 'globe', 'category' => 'content', 'version' => '2.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'default' => 'One Intelligence. Different Domains.'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'The intelligence layer remains consistent. The domain context, tools, workflows, and actions change.'],
            ['key' => 'domains', 'label' => 'Domains (JSON)', 'type' => 'code', 'default' => json_encode([['name'=>'Wontia Business','desc'=>'Business Intelligence & Operations','status'=>'available','icon'=>'briefcase'],['name'=>'Wontia Web','desc'=>'Intelligent Web Operations','status'=>'available','icon'=>'globe'],['name'=>'Wontia Food Security','desc'=>'Food Security Intelligence & Response','status'=>'development','icon'=>'package'],['name'=>'Wontia Health','desc'=>'Health Intelligence Engine','status'=>'future','icon'=>'heart'],['name'=>'Wontia Agriculture','desc'=>'Agriculture Intelligence Engine','status'=>'future','icon'=>'sun'],['name'=>'Wontia Industry','desc'=>'Industrial Intelligence Engine','status'=>'future','icon'=>'cpu'],['name'=>'Wontia Logistics','desc'=>'Supply Chain Intelligence','status'=>'future','icon'=>'truck'],['name'=>'Wontia Education','desc'=>'Learning Intelligence Engine','status'=>'future','icon'=>'book-open'],['name'=>'More','desc'=>'Architecture designed for expansion','status'=>'future','icon'=>'plus-circle']])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:#F3F3EF;border-radius:12px;padding:24px"><div style="font-size:16px;font-weight:700">Domain Architecture</div><div style="font-size:11px;color:#6B6B6B">8 domain cards with status badges</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $domains = $this->safeJson($c['domains']);
        $statusColors = ['available' => ['bg' => '#D9F2E2', 'text' => '#4A9E6E', 'label' => 'AVAILABLE'], 'development' => ['bg' => '#F7E8C8', 'text' => '#D4A54A', 'label' => 'IN DEVELOPMENT'], 'future' => ['bg' => '#EDE9FE', 'text' => '#7C3AED', 'label' => 'FUTURE']];
        $html = '
<section id="domain-arch" style="padding:100px 40px;background:#F3F3EF">
  <div style="max-width:1100px;margin:0 auto">
    <div style="text-align:center;margin-bottom:48px" class="reveal">
      <h2 style="font-size:clamp(28px,5vw,42px);font-weight:800;color:#1A1A1E;letter-spacing:-0.02em;margin-bottom:12px">' . $this->esc($c['headline']) . '</h2>
      <p style="font-size:15px;color:#6B6B6B;line-height:1.7;max-width:600px;margin:0 auto">' . $this->esc($c['subtitle']) . '</p>
    </div>
    <div style="display:flex;align-items:center;justify-content:center;margin-bottom:48px" class="reveal">
      <div style="text-align:center">
        <div style="display:inline-block;padding:16px 32px;background:linear-gradient(135deg,#9B8CDE,#B89EFF);border-radius:16px;color:#fff;font-weight:800;font-size:20px;letter-spacing:-0.02em;margin-bottom:8px">WONTIA</div>
        <div style="font-size:12px;color:#7C3AED;font-weight:600">+</div>
        <div style="display:inline-block;padding:10px 24px;background:#EDE9FE;border-radius:12px;color:#7C3AED;font-weight:700;font-size:15px;margin-top:8px">TIA Core</div>
      </div>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px">';
        foreach ($domains as $dom) {
            $sc = $statusColors[$dom['status']] ?? $statusColors['future'];
            $html .= '
      <div class="card reveal card-padded" style="text-align:center">
        <div style="display:inline-block;padding:3px 12px;border-radius:20px;font-size:9px;font-weight:700;background:' . $sc['bg'] . ';color:' . $sc['text'] . ';margin-bottom:12px;letter-spacing:0.05em">' . $sc['label'] . '</div>
        <div style="font-size:15px;font-weight:700;color:#1A1A1E;margin-bottom:4px">' . $this->esc($dom['name']) . '</div>
        <div style="font-size:11px;color:#6B6B6B;line-height:1.5">' . $this->esc($dom['desc']) . '</div>
      </div>';
        }
        $html .= '</div></div></section>';
        return $html;
    }
}
