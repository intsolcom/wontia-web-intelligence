<?php
namespace App\Widgets;

class WwiFaqWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-faq', 'name' => 'WWI: FAQ', 'icon' => 'help', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'default' => 'Preguntas frecuentes'],
            ['key' => 'items', 'label' => 'Preguntas (JSON)', 'type' => 'code', 'default' => json_encode([
                ['q' => '¿El dominio está incluido?', 'a' => 'Sí, durante el primer período contratado. La renovación anual se factura según la tarifa vigente del registrador.'],
                ['q' => '¿Cuánto tarda en estar online?', 'a' => 'Nuestro objetivo es 24 horas o menos desde que el pago se verifica y TIA recibe la información del negocio.'],
                ['q' => '¿Necesito saber de programación o diseño?', 'a' => 'No. TIA construye el sitio por ti. Tú solo cuentas tu negocio, eliges plantilla y apruebas.'],
                ['q' => '¿Puedo pedir cambios después de publicado?', 'a' => 'Sí. TIA queda dentro de tu panel: pídele cambios con texto o voz ("cambia el color a azul", "agrega testimonios") y los ejecuta.'],
                ['q' => '¿Qué pasa si no tengo logo ni fotos?', 'a' => 'TIA crea un Brand Kit con tu paleta y tipografía, y usa imágenes adecuadas. Luego puedes subir las tuyas.'],
                ['q' => '¿Incluye correos corporativos?', 'a' => 'Sí, hasta 3 buzones tipo tu@negocio.com según el plan, con reenvío y alias.'],
            ])],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $items = $this->safeJson($c['items'] ?? '');
        $html = '<section id="faq" style="padding:90px 0">';
        $html .= '<div class="wrap"><div class="h-sec reveal"><h2>' . $this->esc($c['title']) . '</h2></div>';
        $html .= '<div style="max-width:720px;margin:0 auto">';
        foreach ($items as $item) {
            if (!is_array($item)) continue;
            $html .= '<div class="faq-item reveal"><div class="faq-q">' . $this->esc($item['q'] ?? '') . '</div><div class="faq-a">' . $this->esc($item['a'] ?? '') . '</div></div>';
        }
        $html .= '</div></div></section>';
        return $html;
    }
}
