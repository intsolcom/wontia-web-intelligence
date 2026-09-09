<?php
namespace App\Widgets;

class FooterWidget extends Widget
{
    public static function meta(): array { return ['id' => 'footer', 'name' => 'Footer', 'icon' => 'layout-bottom', 'category' => 'footer', 'version' => '1.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'brand_name', 'label' => 'Brand', 'type' => 'text', 'default' => 'WONTIA'],
            ['key' => 'powered_by', 'label' => 'Powered By', 'type' => 'text', 'default' => 'Intsolcom, LLC'],
            ['key' => 'copyright', 'label' => 'Copyright', 'type' => 'text', 'default' => 'Intsolcom, LLC. Todos los derechos reservados.'],
            ['key' => 'address_us', 'label' => 'US Address', 'type' => 'text', 'default' => '390 NE 191st St, STE 17284, Miami, FL 33179'],
            ['key' => 'phone_us', 'label' => 'US Phone', 'type' => 'text', 'default' => '+1 (786) 386-1515'],
            ['key' => 'email_us', 'label' => 'US Email', 'type' => 'text', 'default' => 'customer@intsolcom.com'],
            ['key' => 'address_co', 'label' => 'CO Address', 'type' => 'text', 'default' => 'Cra 53 # 80 - 192, Barranquilla, Colombia'],
            ['key' => 'phone_co', 'label' => 'CO Phone', 'type' => 'text', 'default' => '+57 311 602 0005'],
            ['key' => 'email_co', 'label' => 'CO Email', 'type' => 'text', 'default' => 'cliente@intsolcom.com'],
            ['key' => 'solutions', 'label' => 'Solutions (JSON)', 'type' => 'code', 'default' => json_encode([
                ['name' => 'Wontia Business', 'url' => '#business', 'status' => 'available'],
                ['name' => 'Wontia AIP', 'url' => 'https://app.wontia.com/login', 'status' => 'available', 'child' => true],
                ['name' => 'Wontia Web Intelligence', 'url' => '#domain-arch', 'status' => 'available', 'child' => true],
                ['name' => 'Wontia Food Security', 'url' => '#food-security', 'status' => 'development'],
                ['name' => 'Wontia Health', 'url' => '#domain-arch', 'status' => 'future'],
                ['name' => 'Wontia Agriculture', 'url' => '#domain-arch', 'status' => 'future'],
                ['name' => 'Wontia Industry', 'url' => '#domain-arch', 'status' => 'future'],
                ['name' => 'Wontia Logistics', 'url' => '#domain-arch', 'status' => 'future'],
                ['name' => 'Wontia Education', 'url' => '#domain-arch', 'status' => 'future'],
            ])],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:#FBFBF8;border-radius:12px;padding:24px;text-align:center"><div style="font-size:18px;font-weight:700">Footer</div><div style="font-size:11px;color:#6B6B6B">Dual address footer</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $year = date('Y');
        $solutions = $this->safeJson($c['solutions']);
        $solMap = ['available' => ['dot' => '#4A9E6E', 'label' => 'AVAILABLE'], 'development' => ['dot' => '#D4A54A', 'label' => 'IN DEVELOPMENT'], 'future' => ['dot' => '#7C3AED', 'label' => 'FUTURE']];
        $solHtml = '<div style="display:flex;flex-direction:column;gap:8px;min-width:240px">
      <div style="font-size:10px;font-weight:700;color:#9A9A9A;text-transform:uppercase">Soluciones</div>';
        foreach ($solutions as $s) {
            $st = $solMap[$s['status'] ?? 'future'] ?? $solMap['future'];
            $isChild = !empty($s['child']);
            $solHtml .= '<a href="' . $this->esc($s['url'] ?? '#domain-arch') . '" style="font-size:' . ($isChild ? '11px' : '12px') . ';color:#4A4A4A;text-decoration:none;font-weight:' . ($isChild ? '400' : '600') . ';' . ($isChild ? 'padding-left:14px' : '') . '">' . $this->esc($s['name']) . ' <span style="color:' . $st['dot'] . ';font-size:8px;font-weight:700;letter-spacing:.04em">' . $st['label'] . '</span></a>';
        }
        $solHtml .= '</div>';
        return '
<footer class="footer">
  <div style="max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px">
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
      <div style="width:24px;height:24px;border-radius:8px;background:linear-gradient(135deg,#9B8CDE,#B89EFF);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:12px;color:#fff">W</div>
      <span style="font-size:12px;font-weight:600;color:#2F2F2F">' . $this->esc($c['brand_name']) . '</span>
      <span style="font-size:10px;color:#DCCFFF">|</span>
      <span style="font-size:11px;color:#9A9A9A">Powered by <strong style="color:#2F2F2F">' . $this->esc($c['powered_by']) . '</strong></span>
      <span style="font-size:10px;color:#DCCFFF">|</span>
      <span style="font-size:11px;color:#9A9A9A">Through <strong style="color:#2F2F2F">Nearshore Dev MarcasBPO</strong></span>
    </div>
    <div style="font-size:11px;color:#9A9A9A">&copy; ' . $year . ' | ' . $this->esc($c['copyright']) . '</div>
  </div>
</footer>
<div style="padding:32px 40px;background:#F3F3EF;border-top:1px solid rgba(80,80,80,0.04)">
  <div style="max-width:1100px;margin:0 auto;display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:32px">
    ' . $solHtml . '
    <div style="display:flex;flex-direction:column;gap:8px;min-width:240px">
      <div style="font-size:10px;font-weight:700;color:#9A9A9A;text-transform:uppercase">&#x1f1fa;&#x1f1f8; Intsolcom, LLC</div>
      <div style="font-size:12px;color:#4A4A4A">&#x1f4cd; ' . $this->esc($c['address_us']) . '</div>
      <div style="font-size:12px;color:#4A4A4A">&#x1f4de; <a href="tel:' . $this->esc($c['phone_us']) . '" style="color:#4A4A4A;text-decoration:none">' . $this->esc($c['phone_us']) . '</a></div>
      <div style="font-size:12px;color:#4A4A4A">&#x2709; <a href="mailto:' . $this->esc($c['email_us']) . '" style="color:#4A4A4A;text-decoration:none">' . $this->esc($c['email_us']) . '</a></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;min-width:240px">
      <div style="font-size:10px;font-weight:700;color:#9A9A9A;text-transform:uppercase">&#x1f1e8;&#x1f1f4; Intsolcom SAS</div>
      <div style="font-size:12px;color:#4A4A4A">&#x1f4cd; ' . $this->esc($c['address_co']) . '</div>
      <div style="font-size:12px;color:#4A4A4A">&#x1f4de; <a href="tel:' . $this->esc($c['phone_co']) . '" style="color:#4A4A4A;text-decoration:none">' . $this->esc($c['phone_co']) . '</a></div>
      <div style="font-size:12px;color:#4A4A4A">&#x2709; <a href="mailto:' . $this->esc($c['email_co']) . '" style="color:#4A4A4A;text-decoration:none">' . $this->esc($c['email_co']) . '</a></div>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;min-width:180px">
      <div style="font-size:10px;font-weight:700;color:#9A9A9A;text-transform:uppercase">Powered by</div>
      <div style="display:flex;align-items:center;gap:8px">
        <div style="width:28px;height:28px;border-radius:8px;background:linear-gradient(135deg,#9B8CDE,#B89EFF);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;color:#fff">W</div>
        <div><div style="font-size:11px;font-weight:700;color:#2F2F2F">' . $this->esc($c['brand_name']) . '</div><div style="font-size:9px;color:#9A9A9A">by MarcasBPO</div></div>
      </div>
    </div>
  </div>
</div>';
    }
}
