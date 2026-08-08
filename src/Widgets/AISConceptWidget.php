<?php
namespace App\Widgets;

class AISConceptWidget extends Widget
{
    public static function meta(): array { return ['id' => 'ais-concept', 'name' => 'AIS Concept: System Architecture', 'icon' => 'layers', 'category' => 'content', 'version' => '3.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'section_label', 'label' => 'Section Label', 'type' => 'text', 'default' => 'A New Category'],
            ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'default' => 'What is an Applied Intelligence System?'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'Most software captures and displays data. An Applied Intelligence System goes further — it understands context, reasons about situations, makes decisions, and executes actions. It doesn\'t just inform. It operates.'],
            ['key' => 'wontia_desc', 'label' => 'WONTIA Description', 'type' => 'text', 'default' => 'The Product & Ecosystem'],
            ['key' => 'ais_desc', 'label' => 'AIS Description', 'type' => 'text', 'default' => 'Applied Intelligence System — The Technological Category'],
            ['key' => 'tia_desc', 'label' => 'TIA Description', 'type' => 'text', 'default' => 'Technology of Applied Intelligence — The Intelligence That Operates'],
            ['key' => 'domains_desc', 'label' => 'Domains Description', 'type' => 'text', 'default' => 'Business \u00b7 Web \u00b7 Food Security \u00b7 Health \u00b7 Agriculture \u00b7 Industry \u00b7 Logistics \u00b7 More'],
            ['key' => 'differentiators', 'label' => 'Differentiators (JSON)', 'type' => 'code', 'default' => json_encode([
                ['title' => 'Not a CRM', 'desc' => 'CRM is a capability within a vertical — not the definition of the system. WONTIA is the intelligence layer that powers CRM, web operations, food security, and more.'],
                ['title' => 'Not a Chatbot', 'desc' => 'TIA is not a conversational assistant. It\'s an operational intelligence core that understands, decides, and acts through commands, voice, workflows, and integrations.'],
                ['title' => 'Not Single-Domain', 'desc' => 'The same WONTIA + TIA architecture powers Business, Web, Food Security, and will expand into Health, Agriculture, Industry, and beyond.'],
            ])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:#F6F6F3;border-radius:12px;padding:24px;text-align:center"><div style="font-size:16px;font-weight:700">AIS Concept</div><div style="font-size:11px;color:#6B6B6B">WONTIA → AIS → TIA → Domains diagram</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $diffs = $this->safeJson($c['differentiators']);
        $html = '
<section id="ais-concept" style="padding:100px 40px;background:#F6F6F3">
  <div style="max-width:1100px;margin:0 auto;text-align:center">
    <div class="reveal" style="margin-bottom:16px">
      <span style="font-size:11px;font-weight:700;color:#7C3AED;letter-spacing:0.1em;text-transform:uppercase">' . $this->esc($c['section_label']) . '</span>
    </div>
    <h2 class="reveal" style="font-size:clamp(28px,5vw,42px);font-weight:800;color:#1A1A1E;letter-spacing:-0.02em;margin-bottom:16px">' . $this->esc($c['headline']) . '</h2>
    <p class="reveal" style="font-size:15px;color:#6B6B6B;line-height:1.8;max-width:680px;margin:0 auto 48px">' . $this->esc($c['subtitle']) . '</p>

    <div class="reveal" style="display:flex;flex-direction:column;align-items:center;gap:0;max-width:560px;margin:0 auto 48px">
      <div style="padding:22px 40px;border-radius:16px;text-align:center;width:100%;background:linear-gradient(135deg,#9B8CDE,#B89EFF);color:#fff;box-shadow:0 12px 40px rgba(156,140,222,.35)">
        <div style="font-size:22px;font-weight:800;letter-spacing:-0.02em;margin-bottom:4px">WONTIA</div>
        <div style="font-size:12px;opacity:.85;font-weight:500">' . $this->esc($c['wontia_desc']) . '</div>
      </div>
      <div style="width:2px;height:24px;background:#DCCFFF"></div>
      <div style="padding:18px 32px;border-radius:14px;text-align:center;width:100%;background:#FBFBF9;border:2px solid #DCCFFF">
        <div style="font-size:16px;font-weight:700;color:#7C3AED;margin-bottom:4px">AIS</div>
        <div style="font-size:12px;color:#6B6B6B">' . $this->esc($c['ais_desc']) . '</div>
      </div>
      <div style="width:2px;height:24px;background:repeating-linear-gradient(0deg,#DCCFFF 0,#DCCFFF 4px,transparent 4px,transparent 8px)"></div>
      <div style="padding:18px 32px;border-radius:14px;text-align:center;width:100%;background:#EDE9FE;border:1px solid rgba(156,140,222,.3)">
        <div style="font-size:16px;font-weight:700;color:#7C3AED;margin-bottom:4px">TIA Core</div>
        <div style="font-size:12px;color:#6B6B6B">' . $this->esc($c['tia_desc']) . '</div>
      </div>
      <div style="width:2px;height:24px;background:repeating-linear-gradient(0deg,#DCCFFF 0,#DCCFFF 4px,transparent 4px,transparent 8px)"></div>
      <div style="padding:16px 24px;border-radius:12px;text-align:center;width:100%;background:#1A1A1E;border:1px dashed rgba(156,140,222,.2)">
        <div style="font-size:13px;font-weight:600;color:#B89EFF;margin-bottom:4px">DOMAIN INTELLIGENCE</div>
        <div style="font-size:11px;color:#8b8fa3">' . $this->esc($c['domains_desc']) . '</div>
      </div>
    </div>

    <div class="grid-3">';
        foreach ($diffs as $d) {
            $html .= '
      <div class="card reveal card-padded" style="text-align:left">
        <div style="font-size:13px;font-weight:700;color:#1A1A1E;margin-bottom:6px">' . $this->esc($d['title']) . '</div>
        <p style="font-size:12px;color:#6B6B6B;line-height:1.6">' . $this->esc($d['desc']) . '</p>
      </div>';
        }
        $html .= '
    </div>
  </div>
</section>';
        return $html;
    }
}
