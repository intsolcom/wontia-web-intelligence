<?php
namespace App\Widgets;

class WwiBuilderDemoWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-builder-demo', 'name' => 'WWI: Demo interactiva (sitio de ejemplo)', 'icon' => 'play', 'category' => 'media', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'url', 'label' => 'Dominio mostrado en la barra', 'type' => 'text', 'inline' => true, 'default' => 'tunegocio.com'],
            ['key' => 'pad', 'label' => 'Espaciado vertical px', 'type' => 'text', 'default' => '60'],
        ];
    }

    public static function adminPreview(): string
    {
        return '<div style="background:#0d1024;border:1px solid rgba(148,163,184,.25);border-radius:12px;padding:12px">'
            . '<div style="display:flex;gap:4px;margin-bottom:8px"><span style="width:8px;height:8px;border-radius:50%;background:#ff5f57"></span><span style="width:8px;height:8px;border-radius:50%;background:#febc2e"></span><span style="width:8px;height:8px;border-radius:50%;background:#28c840"></span></div>'
            . '<div style="height:56px;border-radius:8px;background:linear-gradient(120deg,rgba(124,60,255,.5),rgba(84,190,255,.35))"></div>'
            . '<div style="font-size:10px;color:#8593ab;margin-top:8px">Demo interactiva: el visitante personaliza y prueba un sitio real</div></div>';
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $pad = max(0, (int)$c['pad']);
        $hero = new WwiHeroWidget();
        return '<section style="padding:' . $pad . 'px 0!important">'
            . '<div style="max-width:640px;margin:0 auto;padding:0 24px">'
            . $hero->renderDemo(['url' => (string)$c['url']])
            . '</div></section>';
    }
}
