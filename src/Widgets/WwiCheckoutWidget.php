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
            ['key' => 'title', 'label' => 'Titulo', 'type' => 'text', 'inline' => true, 'default' => 'Crea tu sitio ahora'],
            ['key' => 'subtitle', 'label' => 'Subtitulo', 'type' => 'textarea', 'inline' => true, 'default' => 'Completa tus datos. Al pagar, TIA comienza a construir tu sitio automáticamente.'],
            ['key' => 'button', 'label' => 'Botón', 'type' => 'text', 'inline' => true, 'default' => 'Crear pedido'],
            ['key' => 'note_es', 'label' => 'Nota ES', 'type' => 'text', 'inline' => true, 'default' => 'Recibirás la confirmación y el link de pago por email. Dominio incluido el primer año según plan.'],
            ['key' => 'note_en', 'label' => 'Nota EN', 'type' => 'text', 'default' => 'You will receive confirmation and the payment link by email. Domain included for the first year per plan.'],
            ['key' => 'plan_label', 'label' => 'Etiqueta Plan', 'type' => 'text', 'inline' => true, 'default' => 'Plan'],
            ['key' => 'name_label', 'label' => 'Etiqueta Nombre', 'type' => 'text', 'inline' => true, 'default' => 'Nombre'],
            ['key' => 'email_label', 'label' => 'Etiqueta Email', 'type' => 'text', 'inline' => true, 'default' => 'Email'],
            ['key' => 'whatsapp_label', 'label' => 'Etiqueta WhatsApp', 'type' => 'text', 'inline' => true, 'default' => 'WhatsApp'],
            ['key' => 'domain_label', 'label' => 'Etiqueta Dominio', 'type' => 'text', 'inline' => true, 'default' => 'Dominio deseado'],
            ['key' => 'name_placeholder', 'label' => 'Placeholder Nombre', 'type' => 'text', 'default' => 'Tu nombre'],
            ['key' => 'email_placeholder', 'label' => 'Placeholder Email', 'type' => 'text', 'default' => 'tu@email.com'],
            ['key' => 'phone_placeholder', 'label' => 'Placeholder WhatsApp', 'type' => 'text', 'default' => '+57 300 000 0000'],
            ['key' => 'domain_placeholder', 'label' => 'Placeholder Dominio', 'type' => 'text', 'default' => 'tunegocio.com'],
            ['key' => 'msg_creating', 'label' => 'Mensaje creando', 'type' => 'text', 'default' => 'Creando…'],
            ['key' => 'msg_demo', 'label' => 'Botón pago demo', 'type' => 'text', 'default' => 'Pagar (modo demo)'],
            ['key' => 'msg_created', 'label' => 'Mensaje pedido creado', 'type' => 'text', 'default' => '✓ Pedido creado'],
            ['key' => 'msg_total_prefix', 'label' => 'Prefijo total', 'type' => 'text', 'default' => 'Total: '],
            ['key' => 'msg_connection', 'label' => 'Mensaje error conexión', 'type' => 'text', 'default' => 'Error de conexión'],
            ['key' => 'msg_currency', 'label' => 'Moneda', 'type' => 'text', 'default' => 'COP'],
            ['key' => 'msg_paid', 'label' => 'Mensaje pago aprobado', 'type' => 'text', 'default' => '✓ Pago demo aprobado'],
            ['key' => 'msg_provision', 'label' => 'Mensaje provisioning', 'type' => 'text', 'default' => 'El provisioning se ejecuta automáticamente en la cola de jobs.'],
        ];
    }

    public function render(array $config = []): string
    {
        $c = $this->mergeConfig($config);
        $html = '<section id="contacto" style="padding:90px 0;background:var(--bg2);border-top:1px solid var(--border)">';
        $html .= '<div class="wrap"><div class="h-sec reveal"><h2 data-editable="title">' . $this->esc($c['title']) . '</h2><p data-editable="subtitle">' . $this->esc($c['subtitle']) . '</p></div>';
        $html .= '<div class="panel reveal" id="wwi-co" data-msg-creating="' . $this->esc($c['msg_creating']) . '" data-msg-demo="' . $this->esc($c['msg_demo']) . '" data-msg-created="' . $this->esc($c['msg_created']) . '" data-msg-total="' . $this->esc($c['msg_total_prefix']) . '" data-msg-conn="' . $this->esc($c['msg_connection']) . '" data-msg-currency="' . $this->esc($c['msg_currency']) . '" data-msg-paid="' . $this->esc($c['msg_paid']) . '" data-msg-provision="' . $this->esc($c['msg_provision']) . '" style="max-width:560px;margin:0 auto;padding:30px">';
        $html .= '<div class="w-form-group" style="margin-bottom:14px"><label data-editable="plan_label" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:block;margin-bottom:6px">' . $this->esc($c['plan_label']) . '</label><select class="input" id="wwi-co-plan" style="font-family:inherit"></select></div>';
        $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">';
        $html .= '<div><label data-editable="name_label" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:block;margin-bottom:6px">' . $this->esc($c['name_label']) . '</label><input class="input" id="wwi-co-name" placeholder="' . $this->esc($c['name_placeholder']) . '"/></div>';
        $html .= '<div><label data-editable="email_label" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:block;margin-bottom:6px">' . $this->esc($c['email_label']) . '</label><input class="input" id="wwi-co-email" type="email" placeholder="' . $this->esc($c['email_placeholder']) . '"/></div>';
        $html .= '</div>';
        $html .= '<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px">';
        $html .= '<div><label data-editable="whatsapp_label" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:block;margin-bottom:6px">' . $this->esc($c['whatsapp_label']) . '</label><input class="input" id="wwi-co-phone" placeholder="' . $this->esc($c['phone_placeholder']) . '"/></div>';
        $html .= '<div><label data-editable="domain_label" style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:block;margin-bottom:6px">' . $this->esc($c['domain_label']) . '</label><input class="input" id="wwi-co-domain" placeholder="' . $this->esc($c['domain_placeholder']) . '"/></div>';
        $html .= '</div>';
        $html .= '<div class="mono" style="font-size:11px;color:var(--muted);margin-bottom:16px" id="wwi-co-total"></div>';
        $html .= '<button class="btn btn-primary" data-editable="button" style="width:100%;padding:12px" id="wwi-co-submit">' . $this->esc($c['button']) . '</button>';
        $html .= '<div id="wwi-co-result" style="margin-top:14px"></div>';
        $html .= '<div style="font-size:11px;color:var(--muted);margin-top:14px;line-height:1.6" data-editable="note_es">' . $this->esc($c['note_es']) . '</div>';
        $html .= '</div></div></section>';
        return $html;
    }
}
