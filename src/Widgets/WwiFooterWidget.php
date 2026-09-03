<?php
namespace App\Widgets;

class WwiFooterWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-footer', 'name' => 'WWI: Footer', 'icon' => 'layout-bottom', 'category' => 'footer', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'tagline', 'label' => 'Tagline', 'type' => 'textarea', 'default' => 'Wontia Web Intelligence — la fábrica autónoma de sitios web con IA. Una persona, una idea, una compra, una conversación con TIA: tu negocio online.'],
            ['key' => 'links_es', 'label' => 'Links ES (JSON)', 'type' => 'code', 'default' => json_encode([
                ['label' => 'Planes', 'url' => '#planes'],
                ['label' => 'Beneficios', 'url' => '#beneficios'],
                ['label' => 'Plantillas', 'url' => '#plantillas'],
                ['label' => 'FAQ', 'url' => '#faq'],
            ])],
            ['key' => 'links_en', 'label' => 'Links EN (JSON)', 'type' => 'code', 'default' => json_encode([
                ['label' => 'Pricing', 'url' => '#planes'],
                ['label' => 'Benefits', 'url' => '#beneficios'],
                ['label' => 'Templates', 'url' => '#plantillas'],
                ['label' => 'FAQ', 'url' => '#faq'],
            ])],
            ['key' => 'copyright', 'label' => 'Copyright', 'type' => 'text', 'default' => '© 2026 Intsolcom, LLC. Todos los derechos reservados.'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $linksEs = $this->safeJson($c['links_es'] ?? '');
        $linksEn = $this->safeJson($c['links_en'] ?? '');
        $html = '<footer class="w-footer"><div class="wrap"><div class="cols">';
        $html .= '<div><div style="display:flex;align-items:center;gap:10px;margin-bottom:14px"><div class="w-nav-logo">W</div><span style="font-weight:800;letter-spacing:.06em">WONTIA WEB INTELLIGENCE</span></div><p>' . $this->esc($c['tagline']) . '</p></div>';
        $html .= '<div><h4>ES</h4>';
        foreach ($linksEs as $l) { if (is_array($l)) $html .= '<a href="' . $this->esc($l['url'] ?? '#') . '">' . $this->esc($l['label'] ?? '') . '</a>'; }
        $html .= '</div><div><h4>EN</h4>';
        foreach ($linksEn as $l) { if (is_array($l)) $html .= '<a href="' . $this->esc($l['url'] ?? '#') . '">' . $this->esc($l['label'] ?? '') . '</a>'; }
        $html .= '</div></div>';
        $html .= '<div class="legal"><span>' . $this->esc($c['copyright']) . '</span><span>Powered by <strong>Wontia</strong> — Intsolcom, LLC</span></div>';
        $html .= '</div></footer>';
        return $html;
    }
}
