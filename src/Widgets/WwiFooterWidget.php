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
            ['key' => 'brand', 'label' => 'Marca', 'type' => 'text', 'inline' => true, 'default' => 'WONTIA WEB INTELLIGENCE'],
            ['key' => 'logo_letter', 'label' => 'Letra del logo', 'type' => 'text', 'inline' => true, 'default' => 'W'],
            ['key' => 'tagline', 'label' => 'Tagline', 'type' => 'textarea', 'inline' => true, 'default' => 'Wontia Web Intelligence — la fábrica autónoma de sitios web con IA. Una persona, una idea, una compra, una conversación con TIA: tu negocio online.'],
            ['key' => 'links_es', 'label' => 'Links ES', 'type' => 'repeater', 'fields' => [
                ['key' => 'label', 'label' => 'Texto', 'type' => 'text'],
                ['key' => 'url', 'label' => 'URL', 'type' => 'text'],
            ], 'default' => [
                ['label' => 'Planes', 'url' => '#planes'],
                ['label' => 'Beneficios', 'url' => '#beneficios'],
                ['label' => 'Plantillas', 'url' => '#plantillas'],
                ['label' => 'FAQ', 'url' => '#faq'],
            ]],
            ['key' => 'links_en', 'label' => 'Links EN', 'type' => 'repeater', 'fields' => [
                ['key' => 'label', 'label' => 'Texto', 'type' => 'text'],
                ['key' => 'url', 'label' => 'URL', 'type' => 'text'],
            ], 'default' => [
                ['label' => 'Pricing', 'url' => '#planes'],
                ['label' => 'Benefits', 'url' => '#beneficios'],
                ['label' => 'Templates', 'url' => '#plantillas'],
                ['label' => 'FAQ', 'url' => '#faq'],
            ]],
            ['key' => 'copyright', 'label' => 'Copyright', 'type' => 'text', 'inline' => true, 'default' => '© 2026 Intsolcom, LLC. Todos los derechos reservados.'],
            ['key' => 'links_es_label', 'label' => 'Título columna ES', 'type' => 'text', 'inline' => true, 'default' => 'ES'],
            ['key' => 'links_en_label', 'label' => 'Título columna EN', 'type' => 'text', 'inline' => true, 'default' => 'EN'],
            ['key' => 'powered_by', 'label' => 'Powered by', 'type' => 'text', 'inline' => true, 'default' => 'Powered by Wontia — Intsolcom, LLC'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $linksEs = $this->safeJson($c['links_es'] ?? '');
        $linksEn = $this->safeJson($c['links_en'] ?? '');
        $html = '<footer class="w-footer"><div class="wrap"><div class="cols">';
        $html .= '<div><div style="display:flex;align-items:center;gap:10px;margin-bottom:14px"><div class="w-nav-logo" data-editable="logo_letter">' . $this->esc($c['logo_letter']) . '</div><span data-editable="brand" style="font-weight:800;letter-spacing:.06em">' . $this->esc($c['brand']) . '</span></div><p data-editable="tagline">' . $this->esc($c['tagline']) . '</p></div>';
        $html .= '<div><h4 data-editable="links_es_label">' . $this->esc($c['links_es_label']) . '</h4>';
        foreach ($linksEs as $i => $l) { if (is_array($l)) $html .= '<a href="' . $this->esc($l['url'] ?? '#') . '" data-editable="links_es.' . $i . '.label">' . $this->esc($l['label'] ?? '') . '</a>'; }
        $html .= '</div><div><h4 data-editable="links_en_label">' . $this->esc($c['links_en_label']) . '</h4>';
        foreach ($linksEn as $i => $l) { if (is_array($l)) $html .= '<a href="' . $this->esc($l['url'] ?? '#') . '" data-editable="links_en.' . $i . '.label">' . $this->esc($l['label'] ?? '') . '</a>'; }
        $html .= '</div></div>';
        $html .= '<div class="legal"><span data-editable="copyright">' . $this->esc($c['copyright']) . '</span><span data-editable="powered_by">' . $this->esc($c['powered_by']) . '</span></div>';
        $html .= '</div></footer>';
        return $html;
    }
}
