<?php
namespace App\Widgets;

class WwiTestimonialsWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-testimonials', 'name' => 'WWI: Testimonios', 'icon' => 'message-circle', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'inline' => true, 'default' => 'Lo que dicen nuestros clientes'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'inline' => true, 'default' => 'Negocios reales que ya están online con TIA.'],
            ['key' => 'items', 'label' => 'Testimonios', 'type' => 'repeater', 'fields' => [
                ['key' => 'quote', 'label' => 'Testimonio', 'type' => 'textarea'],
                ['key' => 'name', 'label' => 'Nombre', 'type' => 'text'],
                ['key' => 'role', 'label' => 'Cargo / negocio', 'type' => 'text'],
                ['key' => 'rating', 'label' => 'Estrellas (1-5)', 'type' => 'text'],
            ], 'default' => [
                ['quote' => 'En una tarde tenía mi sitio online. Nunca imaginé que fuera tan simple.', 'name' => 'Ana Gómez', 'role' => 'Panadería La Espiga', 'rating' => '5'],
                ['quote' => 'TIA entendió mi negocio mejor que varias agencias. El sitio quedó impecable.', 'name' => 'Carlos Ruiz', 'role' => 'Bufete Ruiz & Asociados', 'rating' => '5'],
                ['quote' => 'Los correos corporativos y el dominio listos el mismo día. Excelente.', 'name' => 'María Torres', 'role' => 'Consultora independiente', 'rating' => '5'],
            ]],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $items = $this->safeJson($c['items'] ?? []);
        $html = '<section style="padding:90px 0;background:var(--bg2);border-top:1px solid var(--border);border-bottom:1px solid var(--border)">';
        $html .= '<div class="wrap"><div class="h-sec reveal"><h2 data-editable="title">' . $this->esc($c['title']) . '</h2><p data-editable="subtitle">' . $this->esc($c['subtitle']) . '</p></div>';
        $html .= '<div class="wwi-grid-3">';
        foreach ($items as $i => $t) {
            if (!is_array($t)) continue;
            $rating = max(0, min(5, (int)($t['rating'] ?? 5)));
            $stars = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
            $html .= '<div class="card reveal" style="display:flex;flex-direction:column;gap:10px">'
                . '<div style="color:var(--warn);font-size:13px;letter-spacing:2px" data-editable="items.' . $i . '.rating">' . $stars . '</div>'
                . '<p style="font-size:13px;line-height:1.7" data-editable="items.' . $i . '.quote">' . $this->esc($t['quote'] ?? '') . '</p>'
                . '<div style="margin-top:auto"><div style="font-size:12.5px;font-weight:700" data-editable="items.' . $i . '.name">' . $this->esc($t['name'] ?? '') . '</div>'
                . '<div style="font-size:11px;color:var(--muted)" data-editable="items.' . $i . '.role">' . $this->esc($t['role'] ?? '') . '</div></div>'
                . '</div>';
        }
        $html .= '</div></div></section>';
        return $html;
    }
}
