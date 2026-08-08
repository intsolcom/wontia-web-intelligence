<?php
namespace App\Widgets;

class TiaCommandWidget extends Widget
{
    public static function meta(): array { return ['id' => 'tiacommand', 'name' => 'TIA Command Center', 'icon' => 'terminal', 'category' => 'content', 'version' => '2.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'headline', 'label' => 'Headline', 'type' => 'text', 'default' => 'Talk to your system. Let TIA act.'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea', 'default' => 'The TIA Command Center is an operational interface to Wontia. Not a chatbot. An intelligence layer that understands text, voice, commands, and executes across systems.'],
            ['key' => 'examples', 'label' => 'Example Commands (JSON)', 'type' => 'code', 'default' => json_encode(['Show me what needs attention','Analyze today\'s operations','Create the recommended workflow','Why did you recommend this?','Execute the approved actions'])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:linear-gradient(135deg,#1a1d27,#0f1117);border-radius:12px;padding:24px"><div style="font-size:16px;font-weight:700;color:#B89EFF">TIA Command Center</div><div style="font-size:11px;color:#8b8fa3;margin-top:4px">Text + Voice interface mockup</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $examples = $this->safeJson($c['examples']);
        $html = '
<section id="tia-command" style="padding:100px 40px;background:linear-gradient(180deg,#F6F6F3,#EDE9FE,#F6F6F3)">
  <div style="max-width:1100px;margin:0 auto">
    <div style="text-align:center;margin-bottom:48px" class="reveal">
      <div class="badge" style="background:#DCCFFF;color:#7C3AED;margin-bottom:16px">TIA Command Center</div>
      <h2 style="font-size:clamp(28px,5vw,42px);font-weight:800;color:#1A1A1E;letter-spacing:-0.02em;margin-bottom:16px">' . $this->esc($c['headline']) . '</h2>
      <p style="font-size:15px;color:#6B6B6B;line-height:1.8;max-width:640px;margin:0 auto">' . $this->esc($c['subtitle']) . '</p>
    </div>
    <div style="display:flex;gap:40px;align-items:center;flex-wrap:wrap">
      <div style="flex:1;min-width:300px">
        <div style="background:#1A1A1E;border-radius:16px;padding:28px;border:1px solid #2a2d3a;position:relative;overflow:hidden">
          <div style="position:absolute;top:0;right:0;width:100px;height:100px;background:radial-gradient(circle,rgba(155,140,222,0.15),transparent 70%)"></div>
          <div style="display:flex;align-items:center;gap:8px;margin-bottom:20px">
            <div style="width:8px;height:8px;border-radius:50%;background:#BE1341"></div>
            <div style="width:8px;height:8px;border-radius:50%;background:#F7E8C8"></div>
            <div style="width:8px;height:8px;border-radius:50%;background:#00B87D"></div>
            <span style="margin-left:auto;font-size:10px;color:#8b8fa3">TIA v2.0</span>
          </div>
          <div style="display:flex;flex-direction:column;gap:10px">';
        foreach ($examples as $i => $ex) {
            $isUser = $i % 2 === 0;
            if ($isUser) {
                $html .= '<div style="display:flex;justify-content:flex-end"><div style="background:rgba(155,140,222,0.15);color:#e1e4ed;padding:10px 16px;border-radius:12px 12px 4px 12px;font-size:12px;max-width:80%">' . $this->esc($ex) . '</div></div>';
            } else {
                $html .= '<div style="display:flex;justify-content:flex-start"><div style="background:rgba(255,255,255,0.06);color:#8b8fa3;padding:10px 16px;border-radius:12px 12px 12px 4px;font-size:12px;max-width:80%"><span style="color:#B89EFF;font-size:10px">TIA </span>' . $this->esc($ex) . '</div></div>';
            }
        }
        $html .= '<div style="display:flex;gap:8px;margin-top:8px"><input style="flex:1;padding:10px 14px;border-radius:8px;border:1px solid #2a2d3a;background:#0f1117;color:#e1e4ed;font-size:12px;outline:none" placeholder="Type a command..."/><button style="padding:10px 18px;border-radius:8px;border:none;background:linear-gradient(135deg,#9B8CDE,#B89EFF);color:#fff;font-size:12px;font-weight:600;cursor:pointer">Send</button></div>';
        $html .= '</div></div>
      </div>
      <div style="flex:1;min-width:300px;display:flex;flex-direction:column;gap:12px" class="reveal">
        <div style="padding:20px 24px;background:#FBFBF9;border-radius:12px;border:1px solid rgba(80,80,80,0.06);display:flex;align-items:center;gap:12px">
          <div style="width:36px;height:36px;border-radius:10px;background:#EDE9FE;display:flex;align-items:center;justify-content:center;font-size:16px">&#x1F4AC;</div>
          <div><div style="font-size:13px;font-weight:600;color:#1A1A1E">Text Commands</div><div style="font-size:11px;color:#6B6B6B">Type naturally. TIA understands context and intent.</div></div>
        </div>
        <div style="padding:20px 24px;background:#FBFBF9;border-radius:12px;border:1px solid rgba(80,80,80,0.06);display:flex;align-items:center;gap:12px">
          <div style="width:36px;height:36px;border-radius:10px;background:#E8F2FE;display:flex;align-items:center;justify-content:center;font-size:16px">&#x1F399;</div>
          <div><div style="font-size:13px;font-weight:600;color:#1A1A1E">Voice Commands</div><div style="font-size:11px;color:#6B6B6B">Speak to TIA. Hands-free operation in real time.</div></div>
        </div>
        <div style="padding:20px 24px;background:#FBFBF9;border-radius:12px;border:1px solid rgba(80,80,80,0.06);display:flex;align-items:center;gap:12px">
          <div style="width:36px;height:36px;border-radius:10px;background:#E6F5EC;display:flex;align-items:center;justify-content:center;font-size:16px">&#x1F4E6;</div>
          <div><div style="font-size:13px;font-weight:600;color:#1A1A1E">System Integrations</div><div style="font-size:11px;color:#6B6B6B">Connected to your tools, workflows, and data sources.</div></div>
        </div>
        <div style="padding:20px 24px;background:#FBFBF9;border-radius:12px;border:1px solid rgba(80,80,80,0.06);display:flex;align-items:center;gap:12px">
          <div style="width:36px;height:36px;border-radius:10px;background:#FEF3C7;display:flex;align-items:center;justify-content:center;font-size:16px">&#x2705;</div>
          <div><div style="font-size:13px;font-weight:600;color:#1A1A1E">Authorized Actions</div><div style="font-size:11px;color:#6B6B6B">TIA executes within boundaries you define. Full audit trail.</div></div>
        </div>
      </div>
    </div>
  </div>
</section>';
        return $html;
    }
}
