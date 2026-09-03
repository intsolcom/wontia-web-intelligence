<?php
namespace App\Widgets;

class WwiStepsWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-steps', 'name' => 'WWI: Steps', 'icon' => 'steps', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'default' => '¿Cómo funciona?'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'default' => 'De "quiero una web" a "mi web está online" sin intervención humana.'],
            ['key' => 'steps', 'label' => 'Pasos (JSON)', 'type' => 'code', 'default' => json_encode([
                ['title' => 'Cuéntale a TIA sobre tu negocio', 'desc' => 'Escribe, pega texto, sube PDF/DOCX o tu URL. TIA clasifica todo y pregunta solo lo necesario.'],
                ['title' => 'Elige plantilla y revisa', 'desc' => 'TIA genera contenido, SEO y diseño. Revisa, pide cambios con texto o voz y aprueba.'],
                ['title' => 'Publicamos y administras', 'desc' => 'Dominio conectado, SSL, correos, Google-ready. TIA queda dentro para todo cambio futuro.'],
            ])],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $steps = $this->safeJson($c['steps'] ?? '');
        $html = '<section id="como" style="padding:90px 0">';
        $html .= '<div class="wrap"><div class="h-sec reveal"><h2>' . $this->esc($c['title']) . '</h2><p>' . $this->esc($c['subtitle']) . '</p></div>';
        $html .= '<div style="max-width:720px;margin:0 auto;display:flex;flex-direction:column;gap:26px">';
        foreach ($steps as $i => $step) {
            if (!is_array($step)) continue;
            $html .= '<div class="step reveal"><div class="step-num">' . ($i + 1) . '</div><div><h3>' . $this->esc($step['title'] ?? '') . '</h3><p>' . $this->esc($step['desc'] ?? '') . '</p></div></div>';
        }
        $html .= '</div></div></section>';
        return $html;
    }
}
