<?php
namespace App\Widgets;

class WwiContactWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-contact', 'name' => 'WWI: Contacto', 'icon' => 'phone', 'category' => 'content', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'inline' => true, 'default' => 'Hablemos de tu negocio'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'inline' => true, 'default' => 'Escríbenos y te respondemos el mismo día.'],
            ['key' => 'email', 'label' => 'Email', 'type' => 'text', 'inline' => true, 'default' => 'hola@tunegocio.com'],
            ['key' => 'phone', 'label' => 'Teléfono', 'type' => 'text', 'inline' => true, 'default' => '+57 300 000 0000'],
            ['key' => 'whatsapp', 'label' => 'WhatsApp (solo número)', 'type' => 'text', 'inline' => true, 'default' => ''],
            ['key' => 'address', 'label' => 'Dirección', 'type' => 'text', 'inline' => true, 'default' => ''],
            ['key' => 'button_text', 'label' => 'Texto del botón', 'type' => 'text', 'inline' => true, 'default' => 'Escribir por WhatsApp'],
            ['key' => 'email_label', 'label' => 'Etiqueta Email', 'type' => 'text', 'inline' => true, 'default' => 'Email'],
            ['key' => 'phone_label', 'label' => 'Etiqueta Teléfono', 'type' => 'text', 'inline' => true, 'default' => 'Teléfono'],
            ['key' => 'address_label', 'label' => 'Etiqueta Dirección', 'type' => 'text', 'inline' => true, 'default' => 'Dirección'],
            ['key' => 'map_embed', 'label' => 'URL de mapa (embed)', 'type' => 'text', 'default' => ''],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $html = '<section id="contacto" style="padding:80px 0"><div class="wrap">';
        $html .= '<div class="h-sec reveal"><h2 data-editable="title">' . $this->esc($c['title']) . '</h2><p data-editable="subtitle">' . $this->esc($c['subtitle']) . '</p></div>';
        $html .= '<div class="wwi-grid-3" style="align-items:start">';
        $html .= '<div class="card"><div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:6px" data-editable="email_label">' . $this->esc($c['email_label']) . '</div><div style="font-size:13.5px;font-weight:600" data-editable="email">' . $this->esc($c['email']) . '</div></div>';
        $html .= '<div class="card"><div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:6px" data-editable="phone_label">' . $this->esc($c['phone_label']) . '</div><div style="font-size:13.5px;font-weight:600" data-editable="phone">' . $this->esc($c['phone']) . '</div></div>';
        $html .= '<div class="card"><div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);margin-bottom:6px" data-editable="address_label">' . $this->esc($c['address_label']) . '</div><div style="font-size:13.5px;font-weight:600" data-editable="address">' . $this->esc($c['address']) . '</div></div>';
        $html .= '</div>';
        $wa = preg_replace('/[^0-9]/', '', (string)$c['whatsapp']);
        if ($wa !== '') {
            $html .= '<div style="text-align:center;margin-top:24px"><a class="btn btn-primary" href="https://wa.me/' . $this->esc($wa) . '" target="_blank" rel="noopener" data-editable="button_text" style="padding:12px 26px">' . $this->esc($c['button_text']) . '</a></div>';
        }
        $map = trim((string)$c['map_embed']);
        if ($map !== '' && preg_match('#^https://#i', $map)) {
            $html .= '<div style="margin-top:26px"><iframe src="' . $this->esc($map) . '" style="width:100%;height:340px;border:1px solid var(--border);border-radius:12px" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>';
        }
        $html .= '</div></section>';
        return $html;
    }
}
