<?php
namespace App\Widgets;

class CtaWidget extends Widget
{
    public static function meta(): array { return ['id' => 'cta', 'name' => 'CTA Section', 'icon' => 'arrow-right', 'category' => 'footer', 'version' => '1.0.0']; }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'default' => 'Deja que la inteligencia trabaje por ti'],
            ['key' => 'description', 'label' => 'Description', 'type' => 'textarea', 'default' => 'Wontia no es un CRM. Es una Applied Intelligence Platform que convierte cada oportunidad en un resultado medible.'],
            ['key' => 'button_text', 'label' => 'Button Text', 'type' => 'text', 'default' => 'Ingresar a Wontia'],
            ['key' => 'button_url', 'label' => 'Button URL', 'type' => 'text', 'default' => 'https://app.wontia.com/login'],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:linear-gradient(135deg,#EDE9FE,#F6F6F3);border-radius:12px;padding:24px;text-align:center"><div style="font-size:18px;font-weight:700">CTA</div><div style="font-size:11px;color:#6B6B6B">Final call to action</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        return '<section style="padding:120px 40px;text-align:center;max-width:680px;margin:0 auto" class="reveal">
  <h2 style="font-size:36px;font-weight:800;color:#1A1A1E;letter-spacing:-0.02em;margin-bottom:16px">' . $this->esc($c['title']) . '</h2>
  <p style="font-size:16px;color:#6B6B6B;line-height:1.7;margin-bottom:36px">' . $this->esc($c['description']) . '</p>
  <a href="' . $this->esc($c['button_url']) . '" class="btn-primary">' . $this->esc($c['button_text']) . '</a>
</section>';
    }
}
