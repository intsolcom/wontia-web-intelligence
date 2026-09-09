<?php
namespace App\Widgets;

class WwiCheckoutWidget extends Widget
{
    public static function meta(): array
    {
        return ['id' => 'wwi-checkout', 'name' => 'WWI: Checkout', 'icon' => 'cart', 'category' => 'commerce', 'version' => '1.0.0'];
    }

    public static function configSchema(): array
    {
        return [
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'default' => 'Crea tu sitio ahora'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'default' => 'Completa tus datos. Al pagar, TIA comienza a construir tu sitio automáticamente.'],
            ['key' => 'button', 'label' => 'Botón', 'type' => 'text', 'default' => 'Crear pedido'],
            ['key' => 'note_es', 'label' => 'Nota ES', 'type' => 'text', 'default' => 'Recibirás la confirmación y el link de pago por email. Dominio incluido el primer año según plan.'],
            ['key' => 'note_en', 'label' => 'Nota EN', 'type' => 'text', 'default' => 'You will receive confirmation and the payment link by email. Domain included for the first year per plan.'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $html = '<section id="contacto" style="padding:90px 0;background:var(--bg2);border-top:1px solid var(--border)">';
        $html .= '<div class="wrap"><div class="h-sec reveal"><h2>' . $this->esc($c['title']) . '</h2><p>' . $this->esc($c['subtitle']) . '</p></div>';
        $html .= '<div class="panel reveal" style="max-width:560px;margin:0 auto;padding:30px">';
        $html .= '<div class="w-form-group" style="margin-bottom:14px"><label style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:block;margin-bottom:6px">Plan</label><select class="input" id="wwi-co-plan" style="font-family:inherit"></select></div>';
        $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">';
        $html .= '<div><label style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:block;margin-bottom:6px">Nombre</label><input class="input" id="wwi-co-name" placeholder="Tu nombre"/></div>';
        $html .= '<div><label style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:block;margin-bottom:6px">Email</label><input class="input" id="wwi-co-email" type="email" placeholder="tu@email.com"/></div>';
        $html .= '</div>';
        $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">';
        $html .= '<div><label style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:block;margin-bottom:6px">WhatsApp</label><input class="input" id="wwi-co-phone" placeholder="+57 300 000 0000"/></div>';
        $html .= '<div><label style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:block;margin-bottom:6px">Dominio deseado</label><input class="input" id="wwi-co-domain" placeholder="tunegocio.com"/></div>';
        $html .= '</div>';
        $html .= '<div class="mono" style="font-size:11px;color:var(--muted);margin-bottom:16px" id="wwi-co-total"></div>';
        $html .= '<button class="btn btn-primary" style="width:100%;padding:12px" id="wwi-co-submit">' . $this->esc($c['button']) . '</button>';
        $html .= '<div id="wwi-co-result" style="margin-top:14px"></div>';
        $html .= '<div style="font-size:11px;color:var(--muted);margin-top:14px;line-height:1.6">' . $this->esc($c['note_es']) . '</div>';
        $html .= '</div></div></section>';
        return $html;
    }
}
