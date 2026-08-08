<?php
namespace App\Widgets;

class PricingWidget extends Widget
{
    public static function meta(): array { return ['id' => 'pricing', 'name' => 'Pricing (ICE)', 'icon' => 'dollar', 'category' => 'commerce', 'version' => '1.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Precios'],
            ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text', 'default' => 'Encuentra el plan que se adapta a tu equipo.'],
            ['key' => 'ice_workspace', 'label' => 'ICE Workspace ID', 'type' => 'text', 'default' => '1'],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:#F3F3EF;border-radius:12px;padding:24px;text-align:center"><div style="font-size:18px;font-weight:700">Pricing (ICE)</div><div style="font-size:11px;color:#6B6B6B">Embedded pricing widget</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        return '<section id="precios" style="padding:60px 20px;min-height:400px" class="reveal">
  <div data-ice-pricing data-ice-workspace="' . $this->esc($c['ice_workspace']) . '" data-ice-title="' . $this->esc($c['title']) . '" data-ice-subtitle="' . $this->esc($c['subtitle']) . '"></div>
</section>';
    }
}
